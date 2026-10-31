<?php
/**
 * OYC Members' Bulletin Board — For Sale and Wanted posts by members.
 *
 * Built on the same pattern as the Member Photo Gallery (inc/photo-gallery.php):
 * members-only page, front-end forms that post to admin-post.php, a nonce and
 * an ownership/capability check on every write, and the same image validation
 * (whitelisted MIME, real-image check, size cap, client-side downscale).
 *
 * Each post is an `oyc_listing` (non-public post type) owned by the member:
 *   post_title    title
 *   post_content  description (plain text)
 *   _oyc_bb_type     'sale' | 'wanted'
 *   _oyc_bb_price    free text ("$1,200 OBO", "Free", "Make an offer")
 *   _oyc_bb_contact  free text (phone and/or email); emails/phones are linked
 *   _oyc_bb_photo    attachment ID (optional; required for For Sale)
 *   _oyc_bb_closed   '1' once it is marked Sold / Found
 *   _oyc_bb_reminded unix time the last "please update your post" email went out
 *
 * Posts go live immediately and never expire. When a post has not been updated
 * for 60 days its owner gets one email asking them to update it (or mark it
 * Sold/Found, or delete it); any update restarts the 60 days. Members can edit
 * and delete only their own posts; officers (oyc_manage_members) and admins can
 * edit or delete any post.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_BB_POST_TYPE', 'oyc_listing' );
define( 'OYC_BB_SLUG', 'bulletin-board' );
define( 'OYC_BB_REMIND_DAYS', 60 );               // email the owner after this long without an update
define( 'OYC_BB_MAX_ACTIVE', 10 );                 // open (not Sold/Found) posts per member
define( 'OYC_BB_MAX_BYTES', 12 * 1024 * 1024 );    // same cap as the gallery

/* ─────────────────────────────────────────────────────────────────────────────
 * Post type + routing
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	register_post_type( OYC_BB_POST_TYPE, array(
		'label'               => __( 'Bulletin Board', 'orienta-yacht-club' ),
		'labels'              => array(
			'name'          => __( 'Bulletin Board Posts', 'orienta-yacht-club' ),
			'singular_name' => __( 'Bulletin Board Post', 'orienta-yacht-club' ),
			'menu_name'     => __( 'Bulletin Board', 'orienta-yacht-club' ),
		),
		// Never public: no single URLs, archives, search, feeds or REST.
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		// Admins can see/clean up posts in wp-admin under the OYC Inbox menu.
		'show_ui'             => true,
		'show_in_menu'        => 'oyc-applications',
		'supports'            => array( 'title', 'editor', 'author' ),
		'capability_type'     => 'post',
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'        => true,
	) );
} );

/**
 * URL of the board. Works with or without a WP Page record (see fallback below).
 */
function oyc_bb_url( $args = array() ) {
	$page = get_page_by_path( OYC_BB_SLUG );
	$url  = $page ? get_permalink( $page ) : home_url( '/' . OYC_BB_SLUG . '/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * True when the current request is the board page (Page record or fallback).
 */
function oyc_bb_is_board() {
	global $oyc_bb_fallback;
	return ! empty( $oyc_bb_fallback ) || is_page( OYC_BB_SLUG );
}

/**
 * Serve page-bulletin-board.php at /bulletin-board/ even when no Page with that
 * slug exists (same approach as /member-login-setup/), so the feature works the
 * moment the theme updates. If Brad later creates the Page, WordPress' own
 * page-{slug}.php hierarchy renders it and this filter steps aside.
 */
add_filter( 'template_include', function ( $template ) {
	global $wp, $wp_query, $oyc_bb_fallback;
	if ( ! is_404() || ! isset( $wp->request ) || OYC_BB_SLUG !== untrailingslashit( $wp->request ) ) {
		return $template;
	}
	$board = locate_template( 'page-bulletin-board.php' );
	if ( ! $board ) {
		return $template;
	}
	$oyc_bb_fallback  = true;
	$wp_query->is_404 = false;
	status_header( 200 );
	add_filter( 'pre_get_document_title', fn() => __( 'Bulletin Board', 'orienta-yacht-club' ) . ' — ' . get_bloginfo( 'name' ) );
	add_filter( 'body_class', fn( $c ) => array_merge( array_diff( $c, array( 'error404' ) ), array( 'page', 'has-hero-header', 'oyc-page-' . OYC_BB_SLUG, 'oyc-immersive' ) ) );
	return $board;
}, 20 );

// Members-only and per-user: never page-cache it, never index it.
add_action( 'template_redirect', function () {
	if ( is_page( OYC_BB_SLUG ) || ( isset( $GLOBALS['wp']->request ) && OYC_BB_SLUG === untrailingslashit( $GLOBALS['wp']->request ) ) ) {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
}, 1 );
add_filter( 'wp_robots', function ( $robots ) {
	if ( oyc_bb_is_board() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
} );

// Board stylesheet, only on the board page (works under the parent or OYC Fun).
add_action( 'wp_enqueue_scripts', function () {
	if ( ! oyc_bb_is_board() ) {
		return;
	}
	$rel = '/assets/bulletin-board.css';
	$f   = get_template_directory() . $rel;
	wp_enqueue_style( 'oyc-bulletin-board', get_template_directory_uri() . $rel, array( 'oyc-style' ), file_exists( $f ) ? (string) filemtime( $f ) : OYC_VERSION );
}, 100 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Helpers
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Officers and admins may edit or delete any post.
 */
function oyc_bb_can_moderate() {
	return current_user_can( 'manage_options' ) || current_user_can( 'oyc_manage_members' );
}

/**
 * Current user owns the post, or may moderate.
 */
function oyc_bb_can_manage( $post ) {
	if ( ! $post || OYC_BB_POST_TYPE !== $post->post_type || ! is_user_logged_in() ) {
		return false;
	}
	return (int) $post->post_author === get_current_user_id() || oyc_bb_can_moderate();
}

/**
 * Post/Redirect/Get back to the board. `t` makes every result URL unique so no
 * browser or page cache can hand back the board as it was before the change;
 * $post_id scrolls to (and highlights) the post that was just saved.
 */
function oyc_bb_redirect( $notice, $args = array(), $post_id = 0 ) {
	$url = oyc_bb_url( array_merge( array( 'bb' => $notice, 't' => time() ), $args ) );
	wp_safe_redirect( $url . ( $post_id ? '#post-' . (int) $post_id : '#board' ) );
	exit;
}

/**
 * All posts, newest first. Sold/Found ones stay up (so members can see what
 * has gone) but sort to the end of the board.
 */
function oyc_bb_listings( $limit = -1 ) {
	$posts = get_posts( array(
		'post_type'      => OYC_BB_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	usort( $posts, function ( $a, $b ) {
		return (int) get_post_meta( $a->ID, '_oyc_bb_closed', true ) - (int) get_post_meta( $b->ID, '_oyc_bb_closed', true );
	} );
	return $posts;
}

function oyc_bb_active_count( $user_id ) {
	$q = new WP_Query( array(
		'post_type'      => OYC_BB_POST_TYPE,
		'post_status'    => 'publish',
		'author'         => $user_id,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array(
			array( 'key' => '_oyc_bb_closed', 'compare' => 'NOT EXISTS' ),
		),
	) );
	return (int) $q->found_posts;
}

/**
 * Has the post gone 60+ days without an update?
 */
function oyc_bb_is_stale( $post ) {
	return ( time() - (int) get_post_modified_time( 'U', true, $post ) ) > OYC_BB_REMIND_DAYS * DAY_IN_SECONDS;
}

/**
 * Format the price as currency when it starts with a number:
 * "1000000" -> "$1,000,000", "1200 obo" -> "$1,200 obo", "$85.5" -> "$85.50".
 * Text-only prices ("Free", "Make an offer") are left as typed.
 */
function oyc_bb_format_price( $price ) {
	$price = trim( (string) $price );
	if ( ! preg_match( '/^\$?\s*([\d,]*\.?\d+)\s*(k)?\b(.*)$/i', $price, $m ) ) {
		return $price;
	}
	$num = (float) str_replace( ',', '', $m[1] );
	if ( ! empty( $m[2] ) ) {
		$num *= 1000;
	}
	$dec = ( floor( $num ) == $num ) ? 0 : 2; // phpcs:ignore Universal.Operators.StrictComparisons
	$out = '$' . number_format( $num, $dec );
	$rest = trim( $m[3] );
	return '' !== $rest ? $out . ' ' . $rest : $out;
}

function oyc_bb_clip( $s, $max ) {
	return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max ) : substr( $s, 0, $max );
}

/**
 * Escape the contact text and turn emails and US phone numbers into links.
 */
function oyc_bb_contact_html( $text ) {
	$out = esc_html( $text );
	$out = preg_replace_callback( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', function ( $m ) {
		return is_email( $m[0] ) ? '<a href="mailto:' . esc_attr( $m[0] ) . '">' . $m[0] . '</a>' : $m[0];
	}, $out );
	$out = preg_replace_callback( '/(?<![\d@])(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]\d{3}[\s.\-]\d{4}(?!\d)/', function ( $m ) {
		return '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $m[0] ) ) . '">' . $m[0] . '</a>';
	}, $out );
	return nl2br( $out );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Photo upload — same checks as oyc_gallery_handle_upload(), one file.
 * Members lack `upload_files`, so wp_handle_upload + wp_insert_attachment.
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * @return int|false|null attachment ID, false on a bad file, null when no file sent.
 */
function oyc_bb_store_photo( $listing_id ) {
	if ( empty( $_FILES['oyc_bb_photo'] ) || empty( $_FILES['oyc_bb_photo']['name'] ) ) {
		return null;
	}
	$file = $_FILES['oyc_bb_photo'];
	if ( ! empty( $file['error'] ) || (int) $file['size'] > OYC_BB_MAX_BYTES ) {
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	wp_raise_memory_limit( 'image' );

	$mimes = oyc_gallery_allowed_mimes();
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
	if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) || false === @getimagesize( $file['tmp_name'] ) ) {
		return false;
	}
	$file['type'] = $check['type'];

	$uploaded = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
	if ( ! is_array( $uploaded ) || isset( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
		return false;
	}

	$attach_id = wp_insert_attachment( array(
		'post_mime_type' => $uploaded['type'],
		'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
		'post_status'    => 'inherit',
		'post_author'    => get_current_user_id(),
	), $uploaded['file'], $listing_id );

	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return false;
	}
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $uploaded['file'] ) );
	update_post_meta( $attach_id, '_oyc_bb_photo', '1' );
	return (int) $attach_id;
}

function oyc_bb_delete_listing( $id ) {
	$photo = (int) get_post_meta( $id, '_oyc_bb_photo', true );
	if ( $photo ) {
		wp_delete_attachment( $photo, true );
	}
	wp_delete_post( $id, true );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Handlers (admin-post.php)
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Create or update a post.
 */
function oyc_bb_handle_save() {
	if ( ! is_user_logged_in() ) {
		oyc_bb_redirect( 'login' );
	}
	check_admin_referer( 'oyc_bb_save' );

	$id   = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
	$post = $id ? get_post( $id ) : null;
	if ( $id && ! oyc_bb_can_manage( $post ) ) {
		oyc_bb_redirect( 'denied' );
	}
	if ( ! $id && oyc_bb_active_count( get_current_user_id() ) >= OYC_BB_MAX_ACTIVE ) {
		oyc_bb_redirect( 'limit' );
	}

	$type    = ( isset( $_POST['oyc_bb_type'] ) && 'wanted' === $_POST['oyc_bb_type'] ) ? 'wanted' : 'sale';
	$title   = oyc_bb_clip( sanitize_text_field( wp_unslash( $_POST['oyc_bb_title'] ?? '' ) ), 80 );
	$price   = oyc_bb_clip( oyc_bb_format_price( sanitize_text_field( wp_unslash( $_POST['oyc_bb_price'] ?? '' ) ) ), 40 );
	$desc    = oyc_bb_clip( sanitize_textarea_field( wp_unslash( $_POST['oyc_bb_desc'] ?? '' ) ), 1500 );
	$contact = oyc_bb_clip( sanitize_textarea_field( wp_unslash( $_POST['oyc_bb_contact'] ?? '' ) ), 200 );

	if ( '' === $title || '' === $desc || '' === $contact ) {
		oyc_bb_redirect( 'missing', $id ? array( 'bb_edit' => $id ) : array() );
	}

	$data = array(
		'post_type'    => OYC_BB_POST_TYPE,
		'post_title'   => $title,
		'post_content' => $desc,
		'post_status'  => 'publish',
	);
	if ( $id ) {
		$data['ID'] = $id;
		$result     = wp_update_post( $data, true );
	} else {
		$data['post_author'] = get_current_user_id();
		$result              = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $result ) || ! $result ) {
		oyc_bb_redirect( 'failed' );
	}
	$id = (int) $result;

	update_post_meta( $id, '_oyc_bb_type', $type );
	update_post_meta( $id, '_oyc_bb_price', $price );
	update_post_meta( $id, '_oyc_bb_contact', $contact );
	$old_photo = (int) get_post_meta( $id, '_oyc_bb_photo', true );
	if ( $old_photo && ! empty( $_POST['oyc_bb_remove_photo'] ) ) {
		wp_delete_attachment( $old_photo, true );
		delete_post_meta( $id, '_oyc_bb_photo' );
		$old_photo = 0;
	}
	$new_photo = oyc_bb_store_photo( $id );
	if ( $new_photo ) {
		if ( $old_photo ) {
			wp_delete_attachment( $old_photo, true );
		}
		update_post_meta( $id, '_oyc_bb_photo', $new_photo );
	}

	// For Sale posts need a picture. A brand-new post with no usable photo is
	// rolled back so the member can try again.
	$has_photo = (bool) get_post_meta( $id, '_oyc_bb_photo', true );
	if ( 'sale' === $type && ! $has_photo ) {
		if ( ! $post ) {
			wp_delete_post( $id, true );
			oyc_bb_redirect( false === $new_photo ? 'badphoto' : 'nophoto' );
		}
		oyc_bb_redirect( false === $new_photo ? 'badphoto' : 'nophoto', array( 'bb_edit' => $id ) );
	}

	oyc_bb_redirect( false === $new_photo ? 'badphoto' : ( $post ? 'updated' : 'posted' ), array(), $id );
}
add_action( 'admin_post_oyc_bb_save', 'oyc_bb_handle_save' );

/**
 * Owner/officer actions on one post: delete, mark Sold/Found (toggle), or
 * "still current" (restarts the 60-day reminder clock without other changes).
 */
function oyc_bb_handle_action() {
	if ( ! is_user_logged_in() ) {
		oyc_bb_redirect( 'login' );
	}
	check_admin_referer( 'oyc_bb_action' );

	$id   = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
	$post = $id ? get_post( $id ) : null;
	$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	if ( ! oyc_bb_can_manage( $post ) ) {
		oyc_bb_redirect( 'denied' );
	}
	switch ( $do ) {
		case 'delete':
			oyc_bb_delete_listing( $id );
			oyc_bb_redirect( 'deleted' );
			break;
		case 'close':
			$closed = get_post_meta( $id, '_oyc_bb_closed', true );
			$closed ? delete_post_meta( $id, '_oyc_bb_closed' ) : update_post_meta( $id, '_oyc_bb_closed', '1' );
			oyc_bb_redirect( $closed ? 'reopened' : 'closed', array(), $id );
			break;
		case 'current':
			wp_update_post( array( 'ID' => $id ) ); // bumps post_modified.
			oyc_bb_redirect( 'current', array(), $id );
			break;
	}
	oyc_bb_redirect( 'denied' );
}
add_action( 'admin_post_oyc_bb_action', 'oyc_bb_handle_action' );

/* ─────────────────────────────────────────────────────────────────────────────
 * Daily reminder: email the owner of any post not updated for 60 days, once
 * per 60 days. Updating the post (or pressing "Still current") resets it.
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'oyc_bb_remind' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'oyc_bb_remind' );
	}
} );

function oyc_bb_send_reminders() {
	$cutoff = time() - OYC_BB_REMIND_DAYS * DAY_IN_SECONDS;
	$posts  = get_posts( array(
		'post_type'      => OYC_BB_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'no_found_rows'  => true,
		'date_query'     => array(
			array( 'column' => 'post_modified_gmt', 'before' => gmdate( 'Y-m-d H:i:s', $cutoff ) ),
		),
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array(
			array( 'key' => '_oyc_bb_closed', 'compare' => 'NOT EXISTS' ),
		),
	) );

	foreach ( $posts as $post ) {
		$last = (int) get_post_meta( $post->ID, '_oyc_bb_reminded', true );
		if ( $last > $cutoff ) {
			continue; // already reminded in the last 60 days.
		}
		$user = get_userdata( $post->post_author );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}
		$name = $user->first_name ? $user->first_name : $user->display_name;
		$type = 'wanted' === get_post_meta( $post->ID, '_oyc_bb_type', true ) ? __( 'Wanted', 'orienta-yacht-club' ) : __( 'For Sale', 'orienta-yacht-club' );
		/* translators: %s: post title. */
		$subject = sprintf( __( 'Is your Bulletin Board post still current? "%s"', 'orienta-yacht-club' ), $post->post_title );
		$body    = sprintf(
			/* translators: 1: first name, 2: For Sale/Wanted, 3: title, 4: board link. */
			__( "Hi %1$s,\n\nYour %2$s post \"%3$s\" on the Orienta Yacht Club Bulletin Board has been up for 60 days without an update.\n\nPlease take a moment to update it:\n- Still current? Press \"Still current\" or edit the post.\n- Sold or found? Press \"Mark Sold\" or \"Mark Found\".\n- No longer needed? Delete it.\n\n%4$s\n\nThank you,\nOrienta Yacht Club", 'orienta-yacht-club' ),
			$name,
			$type,
			$post->post_title,
			oyc_bb_url() . '#post-' . $post->ID
		);
		if ( wp_mail( $user->user_email, $subject, $body ) ) {
			update_post_meta( $post->ID, '_oyc_bb_reminded', time() );
		}
	}
}
add_action( 'oyc_bb_remind', 'oyc_bb_send_reminders' );

// Deleting a post in wp-admin also removes its photo.
add_action( 'before_delete_post', function ( $id ) {
	if ( OYC_BB_POST_TYPE === get_post_type( $id ) ) {
		$photo = (int) get_post_meta( $id, '_oyc_bb_photo', true );
		if ( $photo ) {
			wp_delete_attachment( $photo, true );
		}
	}
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Rendering
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * One pinned card on the board.
 *
 * @param WP_Post $post Listing.
 */
function oyc_bb_card( $post ) {
	$id       = $post->ID;
	$type     = 'wanted' === get_post_meta( $id, '_oyc_bb_type', true ) ? 'wanted' : 'sale';
	$price    = oyc_bb_format_price( get_post_meta( $id, '_oyc_bb_price', true ) );
	$contact  = get_post_meta( $id, '_oyc_bb_contact', true );
	$closed   = (bool) get_post_meta( $id, '_oyc_bb_closed', true );
	$photo    = (int) get_post_meta( $id, '_oyc_bb_photo', true );
	$img      = $photo ? wp_get_attachment_image_url( $photo, 'medium_large' ) : '';
	$full     = $photo ? wp_get_attachment_image_url( $photo, 'full' ) : '';
	$is_owner = (int) $post->post_author === get_current_user_id();
	$can      = $is_owner || oyc_bb_can_moderate();
	$who      = get_the_author_meta( 'first_name', $post->post_author );
	$last     = get_the_author_meta( 'last_name', $post->post_author );
	$who      = trim( $who . ' ' . $last ) ?: get_the_author_meta( 'display_name', $post->post_author );
	$posted   = get_the_date( 'M j', $post );
	$closed_l = 'wanted' === $type ? __( 'Found', 'orienta-yacht-club' ) : __( 'Sold', 'orienta-yacht-club' );
	$classes = 'bb-card bb-card--' . $type . ( $closed ? ' is-closed' : '' ) . ( $img ? '' : ' bb-card--nophoto' ) . ( $is_owner ? ' is-mine' : '' );
	echo '<article class="' . esc_attr( $classes ) . '" data-type="' . esc_attr( $type ) . '"' . ( $is_owner ? ' data-mine="1"' : '' ) . ' id="post-' . (int) $id . '">';
	echo '<span class="bb-pin" aria-hidden="true"></span>';

	if ( $img ) {
		echo '<a class="bb-card__photo" href="' . esc_url( $full ) . '" target="_blank" rel="noopener">';
		echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $post->post_title ) . '" loading="lazy">';
		echo '</a>';
	}

	echo '<div class="bb-card__body">';
	echo '<div class="bb-card__top">';
	// Once Sold/Found, the card shows only that label (not For Sale / Wanted).
	if ( $closed ) {
		echo '<span class="bb-tag bb-tag--closed">' . esc_html( $closed_l ) . '</span>';
	} else {
		echo '<span class="bb-tag bb-tag--' . esc_attr( $type ) . '">' . esc_html( 'wanted' === $type ? __( 'Wanted', 'orienta-yacht-club' ) : __( 'For Sale', 'orienta-yacht-club' ) ) . '</span>';
	}
	if ( '' !== $price ) {
		echo '<span class="bb-card__price">' . esc_html( $price ) . '</span>';
	}
	echo '</div>';
	echo '<div class="bb-card__title">' . esc_html( $post->post_title ) . '</div>';
	echo '<div class="bb-card__desc">' . nl2br( esc_html( $post->post_content ) ) . '</div>';
	echo '<div class="bb-card__contact"><span class="bb-card__label">' . esc_html__( 'Contact', 'orienta-yacht-club' ) . '</span>' . oyc_bb_contact_html( $contact ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in oyc_bb_contact_html().
	/* translators: 1: member name, 2: date posted. */
	echo '<div class="bb-card__meta">' . esc_html( sprintf( __( '%1$s · %2$s', 'orienta-yacht-club' ), $who, $posted ) ) . '</div>';
	echo '</div>';

	if ( $can ) {
		echo '<div class="bb-card__actions">';
		if ( $is_owner && ! $closed && oyc_bb_is_stale( $post ) ) {
			oyc_bb_action_button( $id, 'current', __( 'Still current', 'orienta-yacht-club' ), 'btn-primary' );
		}
		echo '<a class="btn btn-sm bb-btn" href="' . esc_url( oyc_bb_url( array( 'bb_edit' => $id ) ) ) . '#bb-form">' . esc_html__( 'Edit', 'orienta-yacht-club' ) . '</a>';
		/* translators: %s: "Sold" or "Found". */
		oyc_bb_action_button( $id, 'close', $closed ? __( 'Relist', 'orienta-yacht-club' ) : sprintf( __( 'Mark %s', 'orienta-yacht-club' ), $closed_l ), 'bb-btn' );
		oyc_bb_action_button( $id, 'delete', __( 'Delete', 'orienta-yacht-club' ), 'bb-btn bb-btn--danger', __( 'Delete this post? This cannot be undone.', 'orienta-yacht-club' ) );
		echo '</div>';
	}
	echo '</article>';
}

function oyc_bb_action_button( $id, $do, $label, $class, $confirm = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : '' ) . '>';
	echo '<input type="hidden" name="action" value="oyc_bb_action">';
	echo '<input type="hidden" name="listing_id" value="' . (int) $id . '">';
	echo '<input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	wp_nonce_field( 'oyc_bb_action' );
	echo '<button type="submit" class="btn btn-sm ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
	echo '</form>';
}

/**
 * Dashboard strip: the newest posts, in the same .video-thumbs markup as
 * Member Photos so it sits naturally on the Member Dashboard.
 */
function oyc_bb_thumbs( $heading = 'Bulletin Board', $limit = 4 ) {
	$url   = oyc_bb_url();
	$posts = array_slice( array_filter( oyc_bb_listings( 12 ), function ( $p ) {
		return ! get_post_meta( $p->ID, '_oyc_bb_closed', true );
	} ), 0, $limit );

	echo '<div class="video-thumbs photo-thumbs bb-thumbs">';
	echo '<h3 class="video-thumbs__heading">' . esc_html( $heading ) . '</h3>';
	echo '<div class="video-thumbs__grid">';
	foreach ( $posts as $p ) {
		$photo = (int) get_post_meta( $p->ID, '_oyc_bb_photo', true );
		$img   = $photo ? wp_get_attachment_image_url( $photo, 'medium_large' ) : '';
		$type  = 'wanted' === get_post_meta( $p->ID, '_oyc_bb_type', true ) ? __( 'Wanted', 'orienta-yacht-club' ) : __( 'For Sale', 'orienta-yacht-club' );
		echo '<a class="video-thumb bb-thumb' . ( $img ? '' : ' bb-thumb--text' ) . '" href="' . esc_url( $url . '#post-' . $p->ID ) . '">';
		if ( $img ) {
			echo '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" />';
		}
		echo '<span class="video-thumb__title">' . esc_html( $type . ': ' . $p->post_title ) . '</span>';
		echo '</a>';
	}
	if ( ! $posts ) {
		echo '<a class="video-thumb bb-thumb bb-thumb--text" href="' . esc_url( $url ) . '"><span class="video-thumb__title">' . esc_html__( 'Nothing posted yet. Selling or looking for something?', 'orienta-yacht-club' ) . '</span></a>';
	}
	echo '</div>';
	echo '<p class="video-thumbs__more"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Post an item for sale or wanted →', 'orienta-yacht-club' ) . '</a></p>';
	echo '</div>';
}

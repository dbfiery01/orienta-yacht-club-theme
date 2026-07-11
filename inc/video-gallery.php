<?php
/**
 * OYC Member Videos — self-serve club-video submissions by members.
 *
 * Members paste a YouTube or Vimeo link; each submission is stored as an
 * `oyc_video` post owned by the member and held at `pending` until an admin
 * approves it (moderation ON). Approved videos appear in the Club Videos strip
 * and on the /video-gallery/ page. Mirrors inc/photo-gallery.php (moderation,
 * per-item + bulk actions, owner/admin delete) but for links, not file uploads.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_VIDEO_STATUS', '_oyc_video_status' );

/**
 * Register the private CPT that stores member video submissions. Not public and
 * no admin UI — moderated from the front-end /video-gallery/ page like photos.
 */
add_action( 'init', function () {
	register_post_type( 'oyc_video', array(
		'labels'          => array( 'name' => __( 'Member Videos', 'orienta-yacht-club' ) ),
		'public'          => false,
		'show_ui'         => false,
		'has_archive'     => false,
		'rewrite'         => false,
		'supports'        => array( 'title', 'author' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	) );
} );

/**
 * Parse a YouTube/Vimeo URL into array( provider, id ). False if unrecognised.
 */
function oyc_video_parse_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}
	// YouTube: watch?v= | youtu.be/ | /embed/ | /shorts/ | /live/
	if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
		return array( 'youtube', $m[1] );
	}
	// Vimeo: vimeo.com/ID or vimeo.com/video/ID
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d{6,})~i', $url, $m ) ) {
		return array( 'vimeo', $m[1] );
	}
	return false;
}

/**
 * Canonical watch URL for a provider + id.
 */
function oyc_video_watch_url( $provider, $id ) {
	return 'youtube' === $provider
		? 'https://www.youtube.com/watch?v=' . $id
		: 'https://vimeo.com/' . $id;
}

/**
 * Best-effort oEmbed lookup → array( 'title', 'thumb' ) (empty on failure).
 */
function oyc_video_oembed( $provider, $id, $url ) {
	$endpoint = 'youtube' === $provider
		? 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( $url )
		: 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( $url );

	$title = '';
	$thumb = '';
	$res   = wp_remote_get( $endpoint, array( 'timeout' => 6 ) );
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $data ) ) {
			$title = isset( $data['title'] ) ? (string) $data['title'] : '';
			$thumb = isset( $data['thumbnail_url'] ) ? (string) $data['thumbnail_url'] : '';
		}
	}
	// YouTube thumbnails are deterministic — always available as a fallback.
	if ( '' === $thumb && 'youtube' === $provider ) {
		$thumb = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
	}
	return array( 'title' => $title, 'thumb' => $thumb );
}

/**
 * Thumbnail URL for a stored video (deterministic YouTube fallback).
 */
function oyc_video_thumb_url( $video_id ) {
	$thumb = get_post_meta( $video_id, '_oyc_video_thumb', true );
	if ( ! $thumb && 'youtube' === get_post_meta( $video_id, '_oyc_video_provider', true ) ) {
		$vid = get_post_meta( $video_id, '_oyc_video_vid', true );
		if ( $vid ) {
			$thumb = 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg';
		}
	}
	return $thumb;
}

/**
 * Permalink of the /video-gallery/ page, with a safe fallback.
 */
function oyc_video_gallery_url() {
	$page = get_page_by_path( 'video-gallery' );
	return $page ? get_permalink( $page ) : home_url( '/video-gallery/' );
}

/**
 * Redirect back to the gallery with a notice code (Post/Redirect/Get).
 */
function oyc_video_gallery_redirect( $notice ) {
	wp_safe_redirect( add_query_arg( 'oyc_video', rawurlencode( $notice ), oyc_video_gallery_url() ) );
	exit;
}

/**
 * Query member videos by moderation status, newest first.
 *
 * @param string $status 'approved' or 'pending'.
 * @param int    $limit  Max items (-1 for all).
 * @return WP_Post[]
 */
function oyc_video_gallery_items( $status = 'approved', $limit = -1 ) {
	return get_posts( array(
		'post_type'      => 'oyc_video',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array( array( 'key' => OYC_VIDEO_STATUS, 'value' => $status ) ),
	) );
}

/**
 * Handle a member submission: validate the link, store it pending approval.
 */
function oyc_video_handle_submit() {
	if ( ! is_user_logged_in() ) {
		oyc_video_gallery_redirect( 'login' );
	}
	check_admin_referer( 'oyc_video_submit' );

	$url    = isset( $_POST['oyc_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['oyc_video_url'] ) ) : '';
	$parsed = oyc_video_parse_url( $url );
	if ( ! $parsed ) {
		oyc_video_gallery_redirect( 'badurl' );
	}
	list( $provider, $vid ) = $parsed;

	$caption = isset( $_POST['oyc_video_caption'] ) ? sanitize_text_field( wp_unslash( $_POST['oyc_video_caption'] ) ) : '';
	$caption = function_exists( 'mb_substr' ) ? mb_substr( $caption, 0, 160 ) : substr( $caption, 0, 160 );

	$watch = oyc_video_watch_url( $provider, $vid );
	$meta  = oyc_video_oembed( $provider, $vid, $watch );
	if ( '' === $caption ) {
		$caption = $meta['title'] ? $meta['title'] : __( 'Club video', 'orienta-yacht-club' );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'oyc_video',
		'post_status' => 'publish', // exists in DB; visibility gated by status meta.
		'post_title'  => $caption,
		'post_author' => get_current_user_id(),
	), true );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		oyc_video_gallery_redirect( 'failed' );
	}

	update_post_meta( $post_id, '_oyc_video_provider', $provider );
	update_post_meta( $post_id, '_oyc_video_vid', $vid );
	update_post_meta( $post_id, '_oyc_video_url', $watch );
	update_post_meta( $post_id, '_oyc_video_thumb', $meta['thumb'] );
	update_post_meta( $post_id, OYC_VIDEO_STATUS, 'pending' );

	oyc_video_gallery_redirect( 'submitted' );
}
add_action( 'admin_post_oyc_video_submit', 'oyc_video_handle_submit' );

/**
 * Admin approves a pending video → visible to all members.
 */
function oyc_video_handle_approve() {
	if ( ! current_user_can( 'manage_options' ) ) {
		oyc_video_gallery_redirect( 'denied' );
	}
	check_admin_referer( 'oyc_video_moderate' );
	$id = isset( $_POST['video_id'] ) ? (int) $_POST['video_id'] : 0;
	if ( $id && 'oyc_video' === get_post_type( $id ) ) {
		update_post_meta( $id, OYC_VIDEO_STATUS, 'approved' );
	}
	oyc_video_gallery_redirect( 'approved' );
}
add_action( 'admin_post_oyc_video_approve', 'oyc_video_handle_approve' );

/**
 * Delete a video. Allowed for the submitter or any admin.
 */
function oyc_video_handle_delete() {
	if ( ! is_user_logged_in() ) {
		oyc_video_gallery_redirect( 'login' );
	}
	check_admin_referer( 'oyc_video_delete' );
	$id   = isset( $_POST['video_id'] ) ? (int) $_POST['video_id'] : 0;
	$post = $id ? get_post( $id ) : null;

	if ( $post && 'oyc_video' === $post->post_type ) {
		if ( (int) $post->post_author === get_current_user_id() || current_user_can( 'manage_options' ) ) {
			wp_delete_post( $id, true );
			oyc_video_gallery_redirect( 'deleted' );
		}
	}
	oyc_video_gallery_redirect( 'denied' );
}
add_action( 'admin_post_oyc_video_delete', 'oyc_video_handle_delete' );

/**
 * Admin bulk moderation: approve or delete the SELECTED videos at once.
 */
function oyc_video_handle_bulk() {
	if ( ! current_user_can( 'manage_options' ) ) {
		oyc_video_gallery_redirect( 'denied' );
	}
	check_admin_referer( 'oyc_video_bulk' );

	$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	$ids = isset( $_POST['video_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['video_ids'] ) ) : array();
	$ids = array_filter( $ids );

	if ( empty( $ids ) ) {
		oyc_video_gallery_redirect( 'noselect' );
	}
	if ( 'approve' !== $do && 'delete' !== $do ) {
		oyc_video_gallery_redirect( 'denied' );
	}

	foreach ( $ids as $id ) {
		if ( 'oyc_video' !== get_post_type( $id ) ) {
			continue;
		}
		if ( 'approve' === $do ) {
			update_post_meta( $id, OYC_VIDEO_STATUS, 'approved' );
		} else {
			wp_delete_post( $id, true );
		}
	}

	oyc_video_gallery_redirect( 'delete' === $do ? 'bulkdeleted' : 'bulkapproved' );
}
add_action( 'admin_post_oyc_video_bulk', 'oyc_video_handle_bulk' );

/**
 * Render one member-video card: thumbnail + play badge (opens the video),
 * caption, uploader (admins only), and moderation actions.
 *
 * @param WP_Post $video        oyc_video post.
 * @param bool    $show_approve Show the admin "Approve" button.
 * @param bool    $can_delete   Show the "Remove" button (owner or admin).
 * @param bool    $select_mode  Also render a bulk-select checkbox.
 */
function oyc_video_card( $video, $show_approve = false, $can_delete = false, $select_mode = false ) {
	$provider = get_post_meta( $video->ID, '_oyc_video_provider', true );
	$vid      = get_post_meta( $video->ID, '_oyc_video_vid', true );
	if ( ! $provider || ! $vid ) {
		return;
	}
	$thumb    = oyc_video_thumb_url( $video->ID );
	$watch    = oyc_video_watch_url( $provider, $vid );
	$caption  = $video->post_title;
	$uploader = get_the_author_meta( 'first_name', $video->post_author );
	if ( ! $uploader ) {
		$uploader = get_the_author_meta( 'display_name', $video->post_author );
	}
	$is_admin   = current_user_can( 'manage_options' );
	$admin_post = esc_url( admin_url( 'admin-post.php' ) );

	echo '<figure class="gallery-item gallery-item--video' . ( $select_mode ? ' gallery-item--select' : '' ) . '">';

	if ( $select_mode ) {
		echo '<label class="gallery-select"><input type="checkbox" name="video_ids[]" value="' . (int) $video->ID . '" form="oyc-bulk-moderate-video"><span class="gallery-select__label">' . esc_html__( 'Select', 'orienta-yacht-club' ) . '</span></label>';
	}

	echo '<a class="gallery-link video-thumb" href="' . esc_url( $watch ) . '" target="_blank" rel="noopener">';
	if ( $thumb ) {
		echo '<img class="gallery-photo" src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $caption ) . '" loading="lazy" />';
	}
	echo '<span class="video-thumb__play" aria-hidden="true"></span>';
	echo '</a>';

	if ( $caption || ( $uploader && $is_admin ) ) {
		echo '<figcaption class="gallery-caption">';
		if ( $caption ) {
			echo '<span class="gallery-caption__text">' . esc_html( $caption ) . '</span>';
		}
		if ( $uploader && $is_admin ) {
			/* translators: %s: member first name (shown to admins only). */
			echo '<span class="gallery-caption__by">' . esc_html( sprintf( __( 'by %s', 'orienta-yacht-club' ), $uploader ) ) . '</span>';
		}
		echo '</figcaption>';
	}

	if ( $show_approve || $can_delete ) {
		echo '<div class="gallery-actions">';
		if ( $show_approve ) {
			echo '<form method="post" action="' . $admin_post . '"><input type="hidden" name="action" value="oyc_video_approve"><input type="hidden" name="video_id" value="' . (int) $video->ID . '">';
			wp_nonce_field( 'oyc_video_moderate' );
			echo '<button type="submit" class="btn btn-primary btn-sm">' . esc_html__( 'Approve', 'orienta-yacht-club' ) . '</button></form>';
		}
		if ( $can_delete ) {
			echo '<form method="post" action="' . $admin_post . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this video?', 'orienta-yacht-club' ) ) . '\');"><input type="hidden" name="action" value="oyc_video_delete"><input type="hidden" name="video_id" value="' . (int) $video->ID . '">';
			wp_nonce_field( 'oyc_video_delete' );
			echo '<button type="submit" class="btn btn-sm gallery-remove-btn">' . esc_html__( 'Remove', 'orienta-yacht-club' ) . '</button></form>';
		}
		echo '</div>';
	}

	echo '</figure>';
}

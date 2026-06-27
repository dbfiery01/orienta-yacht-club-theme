<?php
/**
 * OYC Member Photo Gallery — self-serve front-end photo uploads by members.
 *
 * Photos are stored as Media Library attachments tagged with `_oyc_gallery = 1`.
 * Each new upload starts as `_oyc_gallery_status = pending` and stays hidden
 * until an admin approves it (moderation ON). Members may delete their own
 * photos; admins may approve pending ones and delete any. Every state change
 * goes through admin-post.php with a nonce + capability check.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_GALLERY_META', '_oyc_gallery' );
define( 'OYC_GALLERY_STATUS', '_oyc_gallery_status' );
define( 'OYC_GALLERY_MAX_FILES', 10 );
define( 'OYC_GALLERY_MAX_BYTES', 12 * 1024 * 1024 ); // 12 MB per file

/**
 * Image MIME types accepted for upload (whitelist).
 */
function oyc_gallery_allowed_mimes() {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
	);
}

/**
 * Permalink of the gallery page (slug `photo-gallery`), with a safe fallback.
 */
function oyc_gallery_url() {
	$page = get_page_by_path( 'photo-gallery' );
	return $page ? get_permalink( $page ) : home_url( '/photo-gallery/' );
}

/**
 * Redirect back to the gallery with a notice code (Post/Redirect/Get).
 */
function oyc_gallery_redirect( $notice ) {
	wp_safe_redirect( add_query_arg( 'oyc_gallery', rawurlencode( $notice ), oyc_gallery_url() ) );
	exit;
}

/**
 * Query gallery photos by moderation status, newest first.
 *
 * @param string $status 'approved' or 'pending'.
 * @return WP_Post[] attachment posts.
 */
function oyc_gallery_photos( $status = 'approved', $limit = -1 ) {
	return get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => OYC_GALLERY_META, 'value' => '1' ),
			array( 'key' => OYC_GALLERY_STATUS, 'value' => $status ),
		),
	) );
}

/**
 * Handle a member upload: validate each image, store it in the Media Library
 * owned by the current user, and mark it pending approval.
 *
 * Note: members (subscriber-level) lack the `upload_files` cap, so we use the
 * lower-level wp_handle_upload + wp_insert_attachment flow (which doesn't gate
 * on that cap) rather than media_handle_upload, after validating ourselves.
 */
function oyc_gallery_handle_upload() {
	if ( ! is_user_logged_in() ) {
		oyc_gallery_redirect( 'login' );
	}
	check_admin_referer( 'oyc_gallery_upload' );

	if ( empty( $_FILES['oyc_gallery_photo'] ) || empty( $_FILES['oyc_gallery_photo']['name'] ) ) {
		oyc_gallery_redirect( 'nofile' );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$caption = isset( $_POST['oyc_gallery_caption'] )
		? sanitize_textarea_field( wp_unslash( $_POST['oyc_gallery_caption'] ) )
		: '';
	$caption = function_exists( 'mb_substr' ) ? mb_substr( $caption, 0, 300 ) : substr( $caption, 0, 300 );

	$files = $_FILES['oyc_gallery_photo'];
	$names = (array) $files['name'];
	$count = min( count( $names ), OYC_GALLERY_MAX_FILES );
	$mimes = oyc_gallery_allowed_mimes();
	$ok    = 0;

	for ( $i = 0; $i < $count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) || ! empty( $files['error'][ $i ] ) ) {
			continue;
		}
		if ( (int) $files['size'][ $i ] > OYC_GALLERY_MAX_BYTES ) {
			continue;
		}

		// Confirm the bytes really are an allowed image (not just the extension).
		$check = wp_check_filetype_and_ext( $files['tmp_name'][ $i ], $files['name'][ $i ], $mimes );
		if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) {
			continue;
		}
		if ( false === @getimagesize( $files['tmp_name'][ $i ] ) ) {
			continue;
		}

		$single = array(
			'name'     => $files['name'][ $i ],
			'type'     => $check['type'],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		$uploaded = wp_handle_upload( $single, array( 'test_form' => false, 'mimes' => $mimes ) );
		if ( ! is_array( $uploaded ) || isset( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			continue;
		}

		$attach_id = wp_insert_attachment( array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
			'post_excerpt'   => $caption,
			'post_status'    => 'inherit',
			'post_author'    => get_current_user_id(),
		), $uploaded['file'] );

		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			continue;
		}

		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $uploaded['file'] ) );
		update_post_meta( $attach_id, OYC_GALLERY_META, '1' );
		update_post_meta( $attach_id, OYC_GALLERY_STATUS, 'pending' );
		$ok++;
	}

	oyc_gallery_redirect( $ok > 0 ? 'uploaded' : 'failed' );
}
add_action( 'admin_post_oyc_gallery_upload', 'oyc_gallery_handle_upload' );

/**
 * Admin approves a pending photo → it becomes visible to all members.
 */
function oyc_gallery_handle_approve() {
	if ( ! current_user_can( 'manage_options' ) ) {
		oyc_gallery_redirect( 'denied' );
	}
	check_admin_referer( 'oyc_gallery_moderate' );
	$id = isset( $_POST['photo_id'] ) ? (int) $_POST['photo_id'] : 0;
	if ( $id && get_post_meta( $id, OYC_GALLERY_META, true ) ) {
		update_post_meta( $id, OYC_GALLERY_STATUS, 'approved' );
	}
	oyc_gallery_redirect( 'approved' );
}
add_action( 'admin_post_oyc_gallery_approve', 'oyc_gallery_handle_approve' );

/**
 * Delete a gallery photo. Allowed for the photo's owner or any admin.
 */
function oyc_gallery_handle_delete() {
	if ( ! is_user_logged_in() ) {
		oyc_gallery_redirect( 'login' );
	}
	check_admin_referer( 'oyc_gallery_delete' );
	$id   = isset( $_POST['photo_id'] ) ? (int) $_POST['photo_id'] : 0;
	$post = $id ? get_post( $id ) : null;

	if ( $post && get_post_meta( $id, OYC_GALLERY_META, true ) ) {
		$is_owner = ( (int) $post->post_author === get_current_user_id() );
		if ( $is_owner || current_user_can( 'manage_options' ) ) {
			wp_delete_attachment( $id, true );
			oyc_gallery_redirect( 'deleted' );
		}
	}
	oyc_gallery_redirect( 'denied' );
}
add_action( 'admin_post_oyc_gallery_delete', 'oyc_gallery_handle_delete' );

/**
 * Render one gallery photo card (image + caption + uploader + actions).
 *
 * @param WP_Post $photo        Attachment post.
 * @param bool    $show_approve Show the admin "Approve" button (pending list).
 * @param bool    $can_delete   Show the "Remove" button (owner or admin).
 */
function oyc_gallery_card( $photo, $show_approve = false, $can_delete = false ) {
	$img = wp_get_attachment_image(
		$photo->ID,
		'large',
		false,
		array( 'class' => 'gallery-photo', 'loading' => 'lazy' )
	);
	if ( ! $img ) {
		return;
	}
	$full     = wp_get_attachment_image_url( $photo->ID, 'full' );
	$caption  = $photo->post_excerpt;
	$uploader = get_the_author_meta( 'first_name', $photo->post_author );
	if ( ! $uploader ) {
		$uploader = get_the_author_meta( 'display_name', $photo->post_author );
	}
	$admin_post = esc_url( admin_url( 'admin-post.php' ) );

	echo '<figure class="gallery-item">';
	if ( $full ) {
		echo '<a href="' . esc_url( $full ) . '" class="gallery-link" target="_blank" rel="noopener">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	if ( $caption || $uploader ) {
		echo '<figcaption class="gallery-caption">';
		if ( $caption ) {
			echo '<span class="gallery-caption__text">' . esc_html( $caption ) . '</span>';
		}
		if ( $uploader ) {
			/* translators: %s: member first name. */
			echo '<span class="gallery-caption__by">' . esc_html( sprintf( __( 'by %s', 'orienta-yacht-club' ), $uploader ) ) . '</span>';
		}
		echo '</figcaption>';
	}

	if ( $show_approve || $can_delete ) {
		echo '<div class="gallery-actions">';
		if ( $show_approve ) {
			echo '<form method="post" action="' . $admin_post . '">';
			echo '<input type="hidden" name="action" value="oyc_gallery_approve">';
			echo '<input type="hidden" name="photo_id" value="' . (int) $photo->ID . '">';
			wp_nonce_field( 'oyc_gallery_moderate' );
			echo '<button type="submit" class="btn btn-primary btn-sm">' . esc_html__( 'Approve', 'orienta-yacht-club' ) . '</button>';
			echo '</form>';
		}
		if ( $can_delete ) {
			echo '<form method="post" action="' . $admin_post . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this photo?', 'orienta-yacht-club' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="oyc_gallery_delete">';
			echo '<input type="hidden" name="photo_id" value="' . (int) $photo->ID . '">';
			wp_nonce_field( 'oyc_gallery_delete' );
			echo '<button type="submit" class="btn btn-ghost btn-sm">' . esc_html__( 'Remove', 'orienta-yacht-club' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
	}

	echo '</figure>';
}

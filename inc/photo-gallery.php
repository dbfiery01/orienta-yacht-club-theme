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

// Photo-use consent. Bump the version if the wording below changes — uploaders
// are then re-prompted, and each photo records the exact version agreed to.
define( 'OYC_PHOTO_CONSENT_VERSION', '1.0' );

/**
 * The photo-use permission text shown in the upload consent popup.
 */
function oyc_photo_consent_text() {
	return __( 'By uploading, you give Orienta Yacht Club permission to use your photo(s) anywhere on this website and in club communications and materials, with no restrictions. Please confirm you have the right to share these photos. Uploading your photo constitutes that permission.', 'orienta-yacht-club' );
}

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

	// Require the photo-use consent acknowledgement (tracked per photo below).
	$consent_version = isset( $_POST['oyc_consent_version'] ) ? sanitize_text_field( wp_unslash( $_POST['oyc_consent_version'] ) ) : '';
	if ( empty( $_POST['oyc_consent'] ) || '' === $consent_version ) {
		oyc_gallery_redirect( 'noconsent' );
	}
	$consent_at = current_time( 'mysql' );

	if ( empty( $_FILES['oyc_gallery_photo'] ) || empty( $_FILES['oyc_gallery_photo']['name'] ) ) {
		oyc_gallery_redirect( 'nofile' );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';

	// Give image processing as much memory/time as allowed — large photos
	// generate several thumbnail sizes. (Primary protection is the client-side
	// downscale before upload; this is a server-side safety net.)
	wp_raise_memory_limit( 'image' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 );
	}

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
		// Photo-use consent record: uploader is the attachment author (user id);
		// store which consent version was agreed to and when.
		update_post_meta( $attach_id, '_oyc_consent_version', $consent_version );
		update_post_meta( $attach_id, '_oyc_consent_at', $consent_at );
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
 * Admin bulk moderation: approve or delete the SELECTED photos at once.
 * The button pressed sets `do` = approve|delete. Admin only.
 */
function oyc_gallery_handle_bulk() {
	if ( ! current_user_can( 'manage_options' ) ) {
		oyc_gallery_redirect( 'denied' );
	}
	check_admin_referer( 'oyc_gallery_bulk' );

	$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	$ids = isset( $_POST['photo_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['photo_ids'] ) ) : array();
	$ids = array_filter( $ids );

	if ( empty( $ids ) ) {
		oyc_gallery_redirect( 'noselect' );
	}
	if ( 'approve' !== $do && 'delete' !== $do ) {
		oyc_gallery_redirect( 'denied' );
	}

	foreach ( $ids as $id ) {
		if ( ! get_post_meta( $id, OYC_GALLERY_META, true ) ) {
			continue; // not a gallery photo.
		}
		if ( 'approve' === $do ) {
			update_post_meta( $id, OYC_GALLERY_STATUS, 'approved' );
		} else {
			wp_delete_attachment( $id, true );
		}
	}

	oyc_gallery_redirect( 'delete' === $do ? 'bulkdeleted' : 'bulkapproved' );
}
add_action( 'admin_post_oyc_gallery_bulk', 'oyc_gallery_handle_bulk' );

/**
 * Render one gallery photo card (image + caption + uploader + per-photo actions).
 *
 * @param WP_Post $photo        Attachment post.
 * @param bool    $show_approve Show the admin "Approve" button (pending list).
 * @param bool    $can_delete   Show the "Remove" button (owner or admin).
 * @param bool    $select_mode  Also render a multi-select checkbox (associated
 *                              with the bulk-moderation form via the form= attr).
 */
function oyc_gallery_card( $photo, $show_approve = false, $can_delete = false, $select_mode = false ) {
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
	// Uploader name + consent record are shown to admins only (for moderation).
	$is_admin = current_user_can( 'manage_options' );
	$show_by  = ( $uploader && $is_admin );
	$consent_v  = $is_admin ? get_post_meta( $photo->ID, '_oyc_consent_version', true ) : '';
	$consent_at = $is_admin ? get_post_meta( $photo->ID, '_oyc_consent_at', true ) : '';

	echo '<figure class="gallery-item' . ( $select_mode ? ' gallery-item--select' : '' ) . '">';

	// Multi-select checkbox — belongs to the bulk form via the form= attribute,
	// so it can coexist with the per-photo Approve/Remove forms (no nested forms).
	if ( $select_mode ) {
		echo '<label class="gallery-select">';
		echo '<input type="checkbox" name="photo_ids[]" value="' . (int) $photo->ID . '" form="oyc-bulk-moderate">';
		echo '<span class="gallery-select__label">' . esc_html__( 'Select', 'orienta-yacht-club' ) . '</span>';
		echo '</label>';
	}

	if ( $full ) {
		echo '<a href="' . esc_url( $full ) . '" class="gallery-link" target="_blank" rel="noopener">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	if ( $caption || $show_by || $consent_v ) {
		echo '<figcaption class="gallery-caption">';
		if ( $caption ) {
			echo '<span class="gallery-caption__text">' . esc_html( $caption ) . '</span>';
		}
		if ( $show_by ) {
			/* translators: %s: member first name (shown to admins only). */
			echo '<span class="gallery-caption__by">' . esc_html( sprintf( __( 'by %s', 'orienta-yacht-club' ), $uploader ) ) . '</span>';
		}
		if ( $consent_v ) {
			/* translators: 1: consent version, 2: date/time agreed (admins only). */
			echo '<span class="gallery-caption__consent" title="' . esc_attr__( 'Photo-use consent on file', 'orienta-yacht-club' ) . '">' . esc_html( sprintf( __( '✔ Consent v%1$s · %2$s', 'orienta-yacht-club' ), $consent_v, $consent_at ) ) . '</span>';
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
			echo '<button type="submit" class="btn btn-sm gallery-remove-btn">' . esc_html__( 'Remove', 'orienta-yacht-club' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
	}

	echo '</figure>';
}

/**
 * Render a strip of Member Photo thumbnails (latest approved gallery photos),
 * mirroring oyc_video_thumbs(): same .video-thumbs markup/classes so it matches
 * the Club Videos section, but the tiles link to the gallery and there is no
 * play badge. The footer link reads "Add your own photos →".
 *
 * @param string $heading Heading above the strip.
 * @param int    $limit   Max thumbnails to show.
 */
function oyc_photo_thumbs( $heading = 'Member Photos', $limit = 8 ) {
	$url    = oyc_gallery_url();
	$photos = oyc_gallery_photos( 'approved', $limit );

	echo '<div class="video-thumbs photo-thumbs">';
	if ( $heading ) {
		echo '<h3 class="video-thumbs__heading">' . esc_html( $heading ) . '</h3>';
	}
	echo '<div class="video-thumbs__grid">';

	if ( $photos ) {
		foreach ( $photos as $photo ) {
			$img = wp_get_attachment_image_url( $photo->ID, 'large' );
			if ( ! $img ) {
				continue;
			}
			$cap = $photo->post_excerpt;
			echo '<a class="video-thumb" href="' . esc_url( $url ) . '"' . ( $cap ? ' aria-label="' . esc_attr( $cap ) . '"' : '' ) . '>';
			echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $cap ) . '" loading="lazy" />';
			if ( $cap ) {
				echo '<span class="video-thumb__title">' . esc_html( $cap ) . '</span>';
			}
			echo '</a>';
		}
	} else {
		// No approved photos yet → one inviting placeholder tile.
		$rel = '/assets/dashthumbs/photo-gallery.jpg';
		$ph  = '';
		if ( file_exists( get_stylesheet_directory() . $rel ) ) {
			$ph = get_stylesheet_directory_uri() . $rel;
		} elseif ( file_exists( get_template_directory() . $rel ) ) {
			$ph = get_template_directory_uri() . $rel;
		}
		echo '<a class="video-thumb photo-thumb--empty" href="' . esc_url( $url ) . '">';
		if ( $ph ) {
			echo '<img src="' . esc_url( $ph ) . '" alt="" loading="lazy" />';
		}
		echo '<span class="video-thumb__title">' . esc_html__( 'No member photos yet — add the first!', 'orienta-yacht-club' ) . '</span>';
		echo '</a>';
	}

	echo '</div>';
	echo '<p class="video-thumbs__more"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Add your own photos →', 'orienta-yacht-club' ) . '</a></p>';
	echo '</div>';
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Admin: "Photo Consents" list — every gallery upload + its metadata and the
 * photo-use consent record (user, date, version). Lives under the OYC Inbox menu.
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
	// Parent 'oyc-applications' is registered by inc/admin-inbox.php.
	add_submenu_page(
		'oyc-applications',
		__( 'Photo Consents — OYC', 'orienta-yacht-club' ),
		__( 'Photo Consents', 'orienta-yacht-club' ),
		'manage_options',
		'oyc-photo-consents',
		'oyc_render_photo_consents_page'
	);
}, 20 );

/**
 * CSV export of all photo consents (runs before any admin output).
 */
add_action( 'admin_init', function () {
	if ( empty( $_GET['page'] ) || 'oyc-photo-consents' !== $_GET['page'] || empty( $_GET['oyc_export'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'oyc_consents_export' );

	$photos = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array( array( 'key' => OYC_GALLERY_META, 'value' => '1' ) ),
	) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=oyc-photo-consents.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Attachment ID', 'File URL', 'Caption', 'User ID', 'Name', 'Email', 'Uploaded', 'Status', 'Consent Version', 'Consent At' ) );
	foreach ( $photos as $p ) {
		fputcsv( $out, array(
			$p->ID,
			wp_get_attachment_url( $p->ID ),
			$p->post_excerpt,
			$p->post_author,
			get_the_author_meta( 'display_name', $p->post_author ),
			get_the_author_meta( 'user_email', $p->post_author ),
			$p->post_date,
			get_post_meta( $p->ID, OYC_GALLERY_STATUS, true ),
			get_post_meta( $p->ID, '_oyc_consent_version', true ),
			get_post_meta( $p->ID, '_oyc_consent_at', true ),
		) );
	}
	fclose( $out );
	exit;
} );

/**
 * Render the Photo Consents admin list.
 */
function oyc_render_photo_consents_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not authorized.', 'orienta-yacht-club' ) );
	}

	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$per    = 30;

	$meta_query = array( array( 'key' => OYC_GALLERY_META, 'value' => '1' ) );
	if ( 'pending' === $status || 'approved' === $status ) {
		$meta_query[] = array( 'key' => OYC_GALLERY_STATUS, 'value' => $status );
	}

	$q = new WP_Query( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => $per,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => $meta_query,
	) );

	$base       = admin_url( 'admin.php?page=oyc-photo-consents' );
	$export_url = wp_nonce_url( $base . '&oyc_export=1', 'oyc_consents_export' );
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:12px;">
			<?php esc_html_e( 'Photo Consents', 'orienta-yacht-club' ); ?>
			<span style="font-size:13px;font-weight:400;color:#646970;"><?php echo (int) $q->found_posts; ?> <?php echo esc_html( $status ? $status : __( 'total', 'orienta-yacht-club' ) ); ?></span>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button" style="font-size:12px;"><?php esc_html_e( 'Export CSV', 'orienta-yacht-club' ); ?></a>
		</h1>
		<p style="max-width:760px;color:#50575e;"><?php esc_html_e( 'Every photo uploaded to the member gallery, with the uploader and the photo-use consent (version + date) recorded at upload. Uploading constitutes permission for the club to use the photo without restriction.', 'orienta-yacht-club' ); ?></p>

		<p class="subsubsub" style="margin:0 0 8px;">
			<a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'orienta-yacht-club' ); ?></a> |
			<a href="<?php echo esc_url( $base . '&status=pending' ); ?>" class="<?php echo 'pending' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Pending', 'orienta-yacht-club' ); ?></a> |
			<a href="<?php echo esc_url( $base . '&status=approved' ); ?>" class="<?php echo 'approved' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Approved', 'orienta-yacht-club' ); ?></a>
		</p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:70px;"><?php esc_html_e( 'Photo', 'orienta-yacht-club' ); ?></th>
					<th><?php esc_html_e( 'Caption', 'orienta-yacht-club' ); ?></th>
					<th><?php esc_html_e( 'Member', 'orienta-yacht-club' ); ?></th>
					<th><?php esc_html_e( 'Uploaded', 'orienta-yacht-club' ); ?></th>
					<th><?php esc_html_e( 'Status', 'orienta-yacht-club' ); ?></th>
					<th><?php esc_html_e( 'Consent', 'orienta-yacht-club' ); ?></th>
					<th><?php esc_html_e( 'File', 'orienta-yacht-club' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			if ( $q->have_posts() ) :
				while ( $q->have_posts() ) :
					$q->the_post();
					$id        = get_the_ID();
					$author_id = (int) get_post_field( 'post_author', $id );
					$st        = get_post_meta( $id, OYC_GALLERY_STATUS, true );
					$cv        = get_post_meta( $id, '_oyc_consent_version', true );
					$ca        = get_post_meta( $id, '_oyc_consent_at', true );
					$url       = wp_get_attachment_url( $id );
					?>
					<tr>
						<td><?php echo wp_get_attachment_image( $id, array( 60, 60 ), true, array( 'style' => 'width:60px;height:60px;object-fit:cover;border-radius:4px;' ) ); // phpcs:ignore ?></td>
						<td><?php echo esc_html( get_post_field( 'post_excerpt', $id ) ); ?></td>
						<td>
							<strong><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></strong> <span style="color:#646970;">#<?php echo (int) $author_id; ?></span><br>
							<span style="color:#646970;"><?php echo esc_html( get_the_author_meta( 'user_email', $author_id ) ); ?></span>
						</td>
						<td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $id ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $st ? $st : 'pending' ) ); ?></td>
						<td>
							<?php if ( $cv ) : ?>
								<span style="color:#2e7d32;font-weight:600;">✔ v<?php echo esc_html( $cv ); ?></span><br>
								<span style="color:#646970;"><?php echo esc_html( $ca ); ?></span>
							<?php else : ?>
								<span style="color:#b32d2e;"><?php esc_html_e( 'none on file', 'orienta-yacht-club' ); ?></span>
							<?php endif; ?>
						</td>
						<td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'orienta-yacht-club' ); ?></a></td>
					</tr>
					<?php
				endwhile;
				wp_reset_postdata();
			else :
				echo '<tr><td colspan="7">' . esc_html__( 'No photos uploaded yet.', 'orienta-yacht-club' ) . '</td></tr>';
			endif;
			?>
			</tbody>
		</table>

		<?php if ( $q->max_num_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo paginate_links( array(
					'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $base . ( $status ? '&status=' . $status : '' ) ) ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $q->max_num_pages,
					'prev_text' => '‹',
					'next_text' => '›',
				) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div></div>
		<?php endif; ?>
	</div>
	<?php
}

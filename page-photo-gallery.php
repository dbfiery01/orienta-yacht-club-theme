<?php
/**
 * Template Name: Photo Gallery
 * Members-only, self-serve photo gallery. Logged-in members upload their own
 * photos (held for admin approval); approved photos appear in the grid.
 * Handler + helpers live in inc/photo-gallery.php.
 *
 * @package Orienta_Yacht_Club
 */

// Members only — send guests to login, returning here afterward.
if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

get_header();

$is_admin = current_user_can( 'manage_options' );
$me       = get_current_user_id();
$notice   = isset( $_GET['oyc_gallery'] ) ? sanitize_key( wp_unslash( $_GET['oyc_gallery'] ) ) : '';
$approved = oyc_gallery_photos( 'approved' );
$pending  = $is_admin ? oyc_gallery_photos( 'pending' ) : array();

$messages = array(
	'uploaded' => array( 'ok',  __( 'Thanks! Your photo was submitted and is awaiting club-admin approval.', 'orienta-yacht-club' ) ),
	'failed'   => array( 'err', __( 'Sorry, that upload didn’t work. Please use image files (JPG, PNG, GIF or WebP) under 12 MB.', 'orienta-yacht-club' ) ),
	'nofile'   => array( 'err', __( 'Please choose at least one photo to upload.', 'orienta-yacht-club' ) ),
	'deleted'  => array( 'ok',  __( 'Photo removed.', 'orienta-yacht-club' ) ),
	'approved'    => array( 'ok',  __( 'Photo approved — it’s now visible to members.', 'orienta-yacht-club' ) ),
	'bulkapproved'=> array( 'ok',  __( 'Selected photos approved — they’re now visible to members.', 'orienta-yacht-club' ) ),
	'bulkdeleted' => array( 'ok',  __( 'Selected photos deleted.', 'orienta-yacht-club' ) ),
	'noselect'    => array( 'err', __( 'Select at least one photo first.', 'orienta-yacht-club' ) ),
	'denied'      => array( 'err', __( 'You don’t have permission to do that.', 'orienta-yacht-club' ) ),
	'login'       => array( 'err', __( 'Please log in to add photos.', 'orienta-yacht-club' ) ),
);
?>

<div class="page-hero">
	<div class="container">
		<h1 class="page-hero-title"><?php the_title(); ?></h1>
	</div>
</div>

<section class="section">
	<div class="container page-content gallery-page">

		<?php if ( isset( $messages[ $notice ] ) ) : ?>
			<div class="gallery-notice gallery-notice--<?php echo esc_attr( $messages[ $notice ][0] ); ?>">
				<?php echo esc_html( $messages[ $notice ][1] ); ?>
			</div>
		<?php endif; ?>

		<!-- Upload form -->
		<div class="gallery-upload">
			<h2 class="dashboard-heading"><?php esc_html_e( 'Add Your Photos', 'orienta-yacht-club' ); ?></h2>
			<form class="gallery-upload-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oyc_gallery_upload">
				<?php wp_nonce_field( 'oyc_gallery_upload' ); ?>
				<label class="gallery-field">
					<span class="gallery-field__label"><?php esc_html_e( 'Choose photo(s)', 'orienta-yacht-club' ); ?></span>
					<input type="file" name="oyc_gallery_photo[]" accept="image/*" multiple required>
				</label>
				<label class="gallery-field">
					<span class="gallery-field__label"><?php esc_html_e( 'Caption (optional)', 'orienta-yacht-club' ); ?></span>
					<input type="text" name="oyc_gallery_caption" maxlength="300" placeholder="<?php esc_attr_e( 'e.g. Sunset over the East Basin', 'orienta-yacht-club' ); ?>">
				</label>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Upload', 'orienta-yacht-club' ); ?></button>
				<p class="gallery-note"><?php esc_html_e( 'Photos are reviewed by a club admin before they appear here. Images only, up to 12 MB each.', 'orienta-yacht-club' ); ?></p>
			</form>
				<p class="gallery-upload-error" role="alert" hidden></p>
				<script>
				(function(){
					var form = document.querySelector('.gallery-upload-form');
					if (!form) return;
					var fileInput = form.querySelector('input[type="file"]');
					var btn = form.querySelector('button[type="submit"]');
					var errEl = form.parentNode.querySelector('.gallery-upload-error');
					var UPLOADING = <?php echo wp_json_encode( __( 'Uploading…', 'orienta-yacht-club' ) ); ?>;
					var TOO_BIG = <?php echo wp_json_encode( __( 'That photo is too large (max 12 MB). Please choose a smaller image, or use a JPG/PNG.', 'orienta-yacht-club' ) ); ?>;
					var ORIG = btn ? btn.textContent : 'Upload';
					var MAX_EDGE = 2560, MAX_BYTES = 12 * 1024 * 1024;
					function showError(m){ if (errEl){ errEl.textContent = m; errEl.hidden = false; } }
					function resetBtn(){ if (btn){ btn.classList.remove('is-loading'); btn.disabled = false; btn.textContent = ORIG; } }
					function shrink(file){
						return new Promise(function(resolve){
							if (!file.type || file.type.indexOf('image/') !== 0) { resolve(file); return; }
							var url = URL.createObjectURL(file);
							var img = new Image();
							img.onload = function(){
								var w = img.naturalWidth, h = img.naturalHeight;
								URL.revokeObjectURL(url);
								if (!w || !h || Math.max(w, h) <= MAX_EDGE) { resolve(file); return; }
								var sc = MAX_EDGE / Math.max(w, h);
								var cw = Math.round(w * sc), ch = Math.round(h * sc);
								var c = document.createElement('canvas'); c.width = cw; c.height = ch;
								try { c.getContext('2d').drawImage(img, 0, 0, cw, ch); } catch (e) { resolve(file); return; }
								c.toBlob(function(blob){
									if (!blob) { resolve(file); return; }
									var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
									resolve(new File([blob], name, { type: 'image/jpeg' }));
								}, 'image/jpeg', 0.85);
							};
							img.onerror = function(){ URL.revokeObjectURL(url); resolve(file); };
							img.src = url;
						});
					}
					form.addEventListener('submit', async function(e){
						if (!fileInput || !fileInput.files || !fileInput.files.length) return;
						e.preventDefault();
						if (errEl) errEl.hidden = true;
						if (btn) { btn.classList.add('is-loading'); btn.disabled = true; btn.textContent = UPLOADING; }
						var files = Array.prototype.slice.call(fileInput.files);
						var outs = [];
						for (var i = 0; i < files.length; i++) { outs.push(await shrink(files[i])); }
						for (var j = 0; j < outs.length; j++) {
							if (outs[j].size > MAX_BYTES) { showError(TOO_BIG); resetBtn(); return; }
						}
						var fd = new FormData();
						var add = function(sel){ var el = form.querySelector(sel); if (el) fd.set(el.name, el.value); };
						add('input[name="action"]');
						add('input[name="_wpnonce"]');
						add('input[name="_wp_http_referer"]');
						add('input[name="oyc_gallery_caption"]');
						for (var k = 0; k < outs.length; k++) { fd.append('oyc_gallery_photo[]', outs[k], outs[k].name || files[k].name); }
						try {
							var res = await fetch(form.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' });
							window.location.href = res.url || window.location.href;
						} catch (err) { window.location.reload(); }
					});
					document.querySelectorAll('.gallery-page .gallery-actions form').forEach(function(af){
						af.addEventListener('submit', function(ev){
							var b = ev.submitter || af.querySelector('button[type="submit"]');
							if (b) b.classList.add('is-loading');
						});
					});
				})();
				</script>
		</div>

			<?php if ( $is_admin && ! empty( $pending ) ) : ?>
				<div class="gallery-pending">
					<h2 class="dashboard-heading">
						<?php /* translators: %d: number of photos awaiting approval. */ ?>
						<?php printf( esc_html__( 'Pending Approval (%d)', 'orienta-yacht-club' ), count( $pending ) ); ?>
					</h2>
						<p class="gallery-moderate__hint"><?php esc_html_e( 'Tick photos and use Approve/Delete selected to act on several at once \u2014 or use the buttons on each photo.', 'orienta-yacht-club' ); ?></p>
						<form id="oyc-bulk-moderate" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gallery-moderate">
							<input type="hidden" name="action" value="oyc_gallery_bulk">
							<?php wp_nonce_field( 'oyc_gallery_bulk' ); ?>
							<div class="gallery-moderate__bar">
								<label class="gallery-moderate__all"><input type="checkbox" class="gallery-check-all"> <?php esc_html_e( 'Select all', 'orienta-yacht-club' ); ?></label>
								<span class="gallery-moderate__buttons">
									<button type="submit" name="do" value="approve" class="btn btn-primary btn-sm"><?php esc_html_e( 'Approve selected', 'orienta-yacht-club' ); ?></button>
									<button type="submit" name="do" value="delete" class="btn btn-sm gallery-remove-btn" onclick="return confirm('<?php echo esc_js( __( 'Delete the selected photos? This cannot be undone.', 'orienta-yacht-club' ) ); ?>');"><?php esc_html_e( 'Delete selected', 'orienta-yacht-club' ); ?></button>
								</span>
							</div>
						</form>
						<div class="gallery-grid">
							<?php foreach ( $pending as $photo ) : oyc_gallery_card( $photo, true, true, true ); endforeach; ?>
						</div>
						<script>
						(function(){
							var all = document.querySelector('.gallery-check-all');
							if (all) all.addEventListener('change', function(){
								document.querySelectorAll('input[name="photo_ids[]"]').forEach(function(c){ c.checked = all.checked; });
							});
							var bulk = document.getElementById('oyc-bulk-moderate');
							if (bulk) bulk.addEventListener('submit', function(e){
								if (!document.querySelectorAll('input[name="photo_ids[]"]:checked').length) { e.preventDefault(); return; }
								var b = e.submitter || bulk.querySelector('button[type="submit"]');
								if (b) b.classList.add('is-loading');
							});
						})();
						</script>
				</div>
			<?php endif; ?>

		<h2 class="dashboard-heading"><?php esc_html_e( 'Member Photos', 'orienta-yacht-club' ); ?></h2>
		<?php if ( ! empty( $approved ) ) : ?>
			<div class="gallery-grid">
				<?php
				foreach ( $approved as $photo ) :
					$can_delete = $is_admin || ( (int) $photo->post_author === $me );
					oyc_gallery_card( $photo, false, $can_delete );
				endforeach;
				?>
			</div>
		<?php else : ?>
			<p class="gallery-empty"><?php esc_html_e( 'No photos yet — be the first to add one!', 'orienta-yacht-club' ); ?></p>
		<?php endif; ?>

	</div>
</section>

<?php get_footer(); ?>

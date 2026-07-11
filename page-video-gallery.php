<?php
/**
 * Template Name: Video Gallery
 * Members-only, self-serve club videos. Logged-in members submit a YouTube or
 * Vimeo link (held for admin approval); approved videos appear in the grid.
 * Handler + helpers live in inc/video-gallery.php.
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
$notice   = isset( $_GET['oyc_video'] ) ? sanitize_key( wp_unslash( $_GET['oyc_video'] ) ) : '';
$approved = oyc_video_gallery_items( 'approved' );
$pending  = $is_admin ? oyc_video_gallery_items( 'pending' ) : array();

$messages = array(
	'submitted'    => array( 'ok',  __( 'Thanks! Your video was submitted and is awaiting club-admin approval.', 'orienta-yacht-club' ) ),
	'badurl'       => array( 'err', __( 'That didn’t look like a YouTube or Vimeo link. Please paste the full video URL.', 'orienta-yacht-club' ) ),
	'failed'       => array( 'err', __( 'Sorry, that didn’t work. Please try again.', 'orienta-yacht-club' ) ),
	'deleted'      => array( 'ok',  __( 'Video removed.', 'orienta-yacht-club' ) ),
	'approved'     => array( 'ok',  __( 'Video approved — it’s now visible to members.', 'orienta-yacht-club' ) ),
	'bulkapproved' => array( 'ok',  __( 'Selected videos approved — they’re now visible to members.', 'orienta-yacht-club' ) ),
	'bulkdeleted'  => array( 'ok',  __( 'Selected videos deleted.', 'orienta-yacht-club' ) ),
	'noselect'     => array( 'err', __( 'Select at least one video first.', 'orienta-yacht-club' ) ),
	'denied'       => array( 'err', __( 'You don’t have permission to do that.', 'orienta-yacht-club' ) ),
	'login'        => array( 'err', __( 'Please log in to add videos.', 'orienta-yacht-club' ) ),
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

		<!-- Submit form -->
		<div class="gallery-upload">
			<h2 class="dashboard-heading"><?php esc_html_e( 'Add a Club Video', 'orienta-yacht-club' ); ?></h2>
			<form class="gallery-upload-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oyc_video_submit">
				<?php wp_nonce_field( 'oyc_video_submit' ); ?>
				<label class="gallery-field">
					<span class="gallery-field__label"><?php esc_html_e( 'YouTube or Vimeo link', 'orienta-yacht-club' ); ?></span>
					<input type="url" name="oyc_video_url" inputmode="url" required
					       placeholder="<?php esc_attr_e( 'e.g. https://www.youtube.com/watch?v=…', 'orienta-yacht-club' ); ?>">
				</label>
				<label class="gallery-field">
					<span class="gallery-field__label"><?php esc_html_e( 'Title / caption (optional)', 'orienta-yacht-club' ); ?></span>
					<input type="text" name="oyc_video_caption" maxlength="160"
					       placeholder="<?php esc_attr_e( 'e.g. Frostbite racing, January', 'orienta-yacht-club' ); ?>">
				</label>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Submit video', 'orienta-yacht-club' ); ?></button>
				<p class="gallery-note"><?php esc_html_e( 'Videos are reviewed by a club admin before they appear here. Please only share videos you have the right to share.', 'orienta-yacht-club' ); ?></p>
			</form>
			<script>
			(function(){
				document.querySelectorAll('.gallery-page form').forEach(function(f){
					f.addEventListener('submit', function(e){
						var b = e.submitter || f.querySelector('button[type="submit"]');
						if (b) b.classList.add('is-loading');
					});
				});
			})();
			</script>
		</div>

		<?php if ( $is_admin && ! empty( $pending ) ) : ?>
			<div class="gallery-pending">
				<h2 class="dashboard-heading">
					<?php /* translators: %d: number of videos awaiting approval. */ ?>
					<?php printf( esc_html__( 'Pending Approval (%d)', 'orienta-yacht-club' ), count( $pending ) ); ?>
				</h2>
				<p class="gallery-moderate__hint"><?php esc_html_e( 'Tick videos and use Approve/Delete selected to act on several at once — or use the buttons on each video.', 'orienta-yacht-club' ); ?></p>
				<form id="oyc-bulk-moderate-video" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gallery-moderate">
					<input type="hidden" name="action" value="oyc_video_bulk">
					<?php wp_nonce_field( 'oyc_video_bulk' ); ?>
					<div class="gallery-moderate__bar">
						<label class="gallery-moderate__all"><input type="checkbox" class="gallery-check-all"> <?php esc_html_e( 'Select all', 'orienta-yacht-club' ); ?></label>
						<span class="gallery-moderate__buttons">
							<button type="submit" name="do" value="approve" class="btn btn-primary btn-sm"><?php esc_html_e( 'Approve selected', 'orienta-yacht-club' ); ?></button>
							<button type="submit" name="do" value="delete" class="btn btn-sm gallery-remove-btn" onclick="return confirm('<?php echo esc_js( __( 'Delete the selected videos? This cannot be undone.', 'orienta-yacht-club' ) ); ?>');"><?php esc_html_e( 'Delete selected', 'orienta-yacht-club' ); ?></button>
						</span>
					</div>
				</form>
				<div class="gallery-grid">
					<?php foreach ( $pending as $video ) : oyc_video_card( $video, true, true, true ); endforeach; ?>
				</div>
				<script>
				(function(){
					var all = document.querySelector('.gallery-check-all');
					if (all) all.addEventListener('change', function(){
						document.querySelectorAll('input[name="video_ids[]"]').forEach(function(c){ c.checked = all.checked; });
					});
					var bulk = document.getElementById('oyc-bulk-moderate-video');
					if (bulk) bulk.addEventListener('submit', function(e){
						if (!document.querySelectorAll('input[name="video_ids[]"]:checked').length) { e.preventDefault(); return; }
						var b = e.submitter || bulk.querySelector('button[type="submit"]');
						if (b) b.classList.add('is-loading');
					});
				})();
				</script>
			</div>
		<?php endif; ?>

		<h2 class="dashboard-heading"><?php esc_html_e( 'Member Videos', 'orienta-yacht-club' ); ?></h2>
		<?php if ( ! empty( $approved ) ) : ?>
			<div class="gallery-grid">
				<?php
				foreach ( $approved as $video ) :
					$can_delete = $is_admin || ( (int) $video->post_author === $me );
					oyc_video_card( $video, false, $can_delete );
				endforeach;
				?>
			</div>
		<?php else : ?>
			<p class="gallery-empty"><?php esc_html_e( 'No member videos yet — be the first to add one!', 'orienta-yacht-club' ); ?></p>
		<?php endif; ?>

	</div>
</section>

<?php get_footer(); ?>

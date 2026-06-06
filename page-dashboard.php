<?php
/**
 * Template Name: Member Dashboard
 * Members-only landing page after login.
 *
 * @package Orienta_Yacht_Club
 */

// Redirect guests to login
if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

$user       = wp_get_current_user();
$first_name = $user->first_name ? $user->first_name : $user->display_name;

$hour = (int) current_time( 'H' );
if ( $hour < 12 ) {
	$greeting = __( 'Good Morning,', 'orienta-yacht-club' );
} elseif ( $hour < 18 ) {
	$greeting = __( 'Good Afternoon,', 'orienta-yacht-club' );
} else {
	$greeting = __( 'Good Evening,', 'orienta-yacht-club' );
}

get_header();
?>

<div class="page-hero page-hero--dashboard">
	<div class="container">
		<p class="page-hero-eyebrow"><?php echo esc_html( $greeting ); ?></p>
		<h1 class="page-hero-title"><?php echo esc_html( $first_name ); ?></h1>
	</div>
</div>

<section class="section dashboard-section">
	<div class="container">

		<!-- Quick links grid -->
		<h2 class="dashboard-heading"><?php esc_html_e( 'Quick Links', 'orienta-yacht-club' ); ?></h2>
		<div class="dashboard-grid">

			<a href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'Club Calendar', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="<?php echo esc_url( home_url( '/reciprocity-list/' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'Reciprocity List', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="<?php echo esc_url( home_url( '/oyc-resources/' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'OYC Resources', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="<?php echo esc_url( home_url( '/storm-warnings/' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 0 1 0 9z"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'Storm Warnings', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="<?php echo esc_url( home_url( '/my-sound/' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'My Sound', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club" target="_blank" rel="noopener" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l9-13 9 13H3z"/><line x1="12" y1="4" x2="12" y2="17"/><line x1="3" y1="17" x2="21" y2="17"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'Dock Reservations', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="<?php echo esc_url( home_url( '/mamaroneck-harbor/' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'Mamaroneck Harbor', 'orienta-yacht-club' ); ?></span>
			</a>

			<a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>" class="dash-card">
				<span class="dash-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
				</span>
				<span class="dash-card__label"><?php esc_html_e( 'Contact Club Office', 'orienta-yacht-club' ); ?></span>
			</a>

		</div>

		<!-- Account -->
		<div class="dashboard-account">
			<h2 class="dashboard-heading"><?php esc_html_e( 'Your Account', 'orienta-yacht-club' ); ?></h2>
			<div class="dashboard-account-row">
				<div class="dashboard-account-info">
					<p><strong><?php esc_html_e( 'Name', 'orienta-yacht-club' ); ?>:</strong> <?php echo esc_html( $user->display_name ); ?></p>
					<p><strong><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?>:</strong> <?php echo esc_html( $user->user_email ); ?></p>
					<p><strong><?php esc_html_e( 'Thank you for being a member since', 'orienta-yacht-club' ); ?>:</strong> <?php echo esc_html( date( 'F Y', strtotime( $user->user_registered ) ) ); ?></p>
				</div>
				<div class="dashboard-account-actions">
					<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/edit-profile/' ) ); ?>"><?php esc_html_e( 'Edit Profile', 'orienta-yacht-club' ); ?></a>
					<a class="btn btn-ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log Out', 'orienta-yacht-club' ); ?></a>
				</div>
			</div>
		</div>

		<!-- Club Videos -->
		<div class="dashboard-videos">
			<?php oyc_video_thumbs(); ?>
		</div>

		<!-- Logout -->
		<div class="dashboard-logout">
			<a class="btn btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
				<?php esc_html_e( 'Log Out', 'orienta-yacht-club' ); ?>
			</a>
		</div>

	</div>
</section>

<?php get_footer(); ?>

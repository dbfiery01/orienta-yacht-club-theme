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
			<?php
			$dash_links = array(
				array( 'url' => home_url( '/calendar/' ),             'label' => __( 'Club Calendar', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/reciprocity-list/' ),     'label' => __( 'Reciprocity List', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/fleet-roster/' ),         'label' => __( 'Fleet Roster', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/2026-fee-schedule/' ),    'label' => __( 'Fee Schedule', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/oyc-resources/' ),        'label' => __( 'OYC Resources', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/mamaroneck-harbor/' ),    'label' => __( 'Mamaroneck Harbor', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/sailing-instructions/' ), 'label' => __( 'Sailing Instructions', 'orienta-yacht-club' ) ),
				array( 'url' => home_url( '/storm-warnings/' ),       'label' => __( 'Storm Warnings', 'orienta-yacht-club' ) ),
				array( 'url' => 'https://lisicos.uconn.edu/',         'label' => __( 'My Sound', 'orienta-yacht-club' ), 'external' => true ),
				array( 'url' => 'https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club', 'label' => __( 'Dock Reservations', 'orienta-yacht-club' ), 'external' => true ),
				array( 'url' => home_url( '/#contact' ),              'label' => __( 'Contact Club Office', 'orienta-yacht-club' ) ),
			);
			foreach ( $dash_links as $link ) {
				$ext = ! empty( $link['external'] );
				printf(
					'<a href="%1$s" class="dash-card"%2$s>%3$s<span class="dash-card__label">%4$s</span></a>',
					esc_url( $link['url'] ),
					$ext ? ' target="_blank" rel="noopener"' : '',
					oyc_dash_thumb( $link['url'] ),
					esc_html( $link['label'] )
				);
			}
			?>
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

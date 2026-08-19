<?php
/**
 * Template Name: Officer Hub
 * Landing page for the front-end officer area.
 *
 * @package Orienta_Yacht_Club
 */

require_once get_template_directory() . '/inc/officer-admin.php';

oyc_officer_guard( 'oyc_access_officer_area' );

$user       = wp_get_current_user();
$first_name = $user->first_name ? $user->first_name : $user->display_name;

// Section tiles, minus the hub itself.
$oyc_tiles = oyc_officer_subsections();

// Counts shown on the tiles.
$oyc_counts = array();

if ( current_user_can( 'oyc_manage_messages' ) && function_exists( 'oyc_unread_count' ) ) {
	$unread = (int) oyc_unread_count();
	$oyc_counts['messages'] = $unread
		? sprintf( _n( '%d unread message', '%d unread messages', $unread, 'orienta-yacht-club' ), $unread )
		: __( 'No unread messages', 'orienta-yacht-club' );
}

if ( current_user_can( 'oyc_manage_events' ) ) {
	$ev = wp_count_posts( 'events' );
	$oyc_counts['events'] = sprintf(
		__( '%d events on the calendar', 'orienta-yacht-club' ),
		isset( $ev->publish ) ? (int) $ev->publish : 0
	);
}

if ( current_user_can( 'oyc_manage_members' ) ) {
	$totals = count_users();
	$oyc_counts['members'] = sprintf(
		__( '%d member accounts', 'orienta-yacht-club' ),
		(int) $totals['total_users']
	);
}

get_header();
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Officer Area', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php echo esc_html( $first_name ); ?></h1>
	</div>
</div>

<section class="section officer-section">
	<div class="container">

		<?php oyc_officer_nav( 'hub' ); ?>
		<?php oyc_officer_render_notices(); ?>

		<p class="officer-intro">
			<?php esc_html_e( 'Manage the club calendar, read messages from the website, and administer member accounts. Changes here go live on the public site immediately.', 'orienta-yacht-club' ); ?>
		</p>

		<div class="officer-tiles">
			<?php foreach ( $oyc_tiles as $tile ) : ?>
				<a class="officer-tile" href="<?php echo esc_url( $tile['url'] ); ?>">
					<h2 class="officer-tile__title"><?php echo esc_html( $tile['label'] ); ?></h2>
					<?php if ( ! empty( $oyc_counts[ $tile['key'] ] ) ) : ?>
						<p class="officer-tile__meta"><?php echo esc_html( $oyc_counts[ $tile['key'] ] ); ?></p>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>

		<p class="officer-footnote">
			<a href="<?php echo esc_url( home_url( '/members-area/' ) ); ?>">
				<?php esc_html_e( 'Back to Member Dashboard', 'orienta-yacht-club' ); ?>
			</a>
		</p>

	</div>
</section>

<?php
get_footer();

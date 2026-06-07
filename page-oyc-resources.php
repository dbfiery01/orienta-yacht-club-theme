<?php
/**
 * Template Name: OYC Resources
 * Members-only document library.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

/**
 * Resource list.
 * Each entry: [ 'title', 'url', 'date' (optional), 'desc' (optional) ]
 * Set 'url' to the media-library URL once files are uploaded.
 * 'type' can be 'pdf' | 'doc' | 'xls' | 'link' — controls the icon shown.
 */
$oyc_resources = array(
	array(
		'title' => 'Fleet Roster 2026',
		'url'   => '#',
		'date'  => '2026',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Slip Waiting List — October 2026 Update',
		'url'   => '#',
		'date'  => 'October 2026',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Dock Assignments',
		'url'   => '#',
		'date'  => '',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Constitution and Bylaws 2026',
		'url'   => '#',
		'date'  => '2026',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Member Guidelines 2026',
		'url'   => '#',
		'date'  => '2026',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Fees 2026',
		'url'   => '#',
		'date'  => '2026',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Club Rental Agreement',
		'url'   => '#',
		'date'  => '',
		'type'  => 'pdf',
	),
	array(
		'title' => 'Weather — NWS Marine Forecast',
		'url'   => 'https://www.weather.gov/marine/',
		'date'  => '',
		'type'  => 'link',
	),
	array(
		'title' => 'Windy',
		'url'   => 'https://www.windy.com/40.939/-73.715?40.462,-73.715,8',
		'date'  => '',
		'type'  => 'link',
	),
	array(
		'title' => 'Passage Weather',
		'url'   => 'https://www.passageweather.com/',
		'date'  => '',
		'type'  => 'link',
	),
	array(
		'title' => 'VesselFinder',
		'url'   => 'https://www.vesselfinder.com/',
		'date'  => '',
		'type'  => 'link',
	),
);

// SVG icons by type
function oyc_resource_icon( $type ) {
	switch ( $type ) {
		case 'xls':
			// spreadsheet icon
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><line x1="12" y1="9" x2="12" y2="21"/></svg>';
		case 'link':
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
		default: // pdf / doc
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
	}
}

get_header();
?>

<div class="page-hero page-hero--dashboard">
	<div class="container">
		<p class="page-hero-eyebrow">
			<a class="hero-back-link" href="<?php echo esc_url( home_url( '/members-area/' ) ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
				<?php esc_html_e( 'Back to Dashboard', 'orienta-yacht-club' ); ?>
			</a>
		</p>
		<h1 class="page-hero-title"><?php esc_html_e( 'OYC Resources', 'orienta-yacht-club' ); ?></h1>
		<p class="page-hero-sub"><?php esc_html_e( 'Club documents and reference materials for members.', 'orienta-yacht-club' ); ?></p>
	</div>
</div>

<section class="section dashboard-section">
	<div class="container">

		<div class="members-badge" style="margin-bottom:2rem;">
			<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
			<?php esc_html_e( 'Members Only', 'orienta-yacht-club' ); ?>
		</div>

		<ul class="resource-list" role="list">
			<?php foreach ( $oyc_resources as $doc ) :
				$is_placeholder = ( $doc['url'] === '#' );
				$target = $is_placeholder ? '' : ' target="_blank" rel="noopener"';
			?>
			<li class="resource-item<?php echo $is_placeholder ? ' resource-item--pending' : ''; ?>">
				<a href="<?php echo esc_url( $doc['url'] ); ?>" class="resource-link"<?php echo $target; ?>>
					<span class="resource-icon" aria-hidden="true">
						<?php echo oyc_resource_icon( $doc['type'] ); ?>
					</span>
					<span class="resource-info">
						<span class="resource-title"><?php echo esc_html( $doc['title'] ); ?></span>
						<?php if ( $doc['date'] ) : ?>
							<span class="resource-date"><?php echo esc_html( $doc['date'] ); ?></span>
						<?php endif; ?>
					</span>
					<span class="resource-action" aria-hidden="true">
						<?php if ( $is_placeholder ) : ?>
							<span class="resource-pending-badge"><?php esc_html_e( 'Coming soon', 'orienta-yacht-club' ); ?></span>
						<?php elseif ( $doc['type'] === 'link' ) : ?>
							<span class="resource-ext-arrow">&#8599;&#65039;</span>
						<?php else : ?>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
						<?php endif; ?>
					</span>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>

	</div>
</section>

<?php get_footer(); ?>

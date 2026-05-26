<?php
/**
 * OYC Progressive Web App Support
 *
 * • Serves /manifest.json dynamically (picks up site name + URL from WP settings)
 * • Serves /sw.js from the theme's assets directory at root scope
 * • Injects <link rel="manifest">, theme-color, and Apple PWA meta tags into <head>
 * • Registers the service worker via a footer script
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ──────────────────────────────────────────────────────────────────────
 * 1. Serve /manifest.json and /sw.js from the WordPress root scope
 * ────────────────────────────────────────────────────────────────────── */

add_action( 'template_redirect', function () {
	$path = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );

	/* ── Web App Manifest ─────────────────────────────────────────────── */
	if ( $path === '/manifest.json' ) {
		$theme = get_template_directory_uri();
		$name  = get_bloginfo( 'name' ) ?: 'Orienta Yacht Club';

		$manifest = array(
			'name'             => $name,
			'short_name'       => 'OYC',
			'description'      => get_bloginfo( 'description' ) ?: 'Orienta Yacht Club — the friendly, family-focused sailing club.',
			'start_url'        => home_url( '/' ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'orientation'      => 'portrait-primary',
			'background_color' => '#0b2a4a',
			'theme_color'      => '#0b2a4a',
			'icons'            => array(
				array(
					'src'     => $theme . '/assets/icons/icon-192.png',
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
				array(
					'src'     => $theme . '/assets/icons/icon-512.png',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
			),
			'shortcuts' => array(
				array(
					'name'        => 'Events Calendar',
					'short_name'  => 'Events',
					'description' => 'View upcoming OYC events',
					'url'         => home_url( '/events/' ),
					'icons'       => array(
						array( 'src' => $theme . '/assets/icons/icon-192.png', 'sizes' => '192x192' ),
					),
				),
				array(
					'name'        => 'Members Area',
					'short_name'  => 'Members',
					'description' => 'Access the members-only area',
					'url'         => home_url( '/members-area/' ),
					'icons'       => array(
						array( 'src' => $theme . '/assets/icons/icon-192.png', 'sizes' => '192x192' ),
					),
				),
				array(
					'name'        => 'Apply for Membership',
					'short_name'  => 'Apply',
					'description' => 'Submit a membership application',
					'url'         => home_url( '/membership-application/' ),
					'icons'       => array(
						array( 'src' => $theme . '/assets/icons/icon-192.png', 'sizes' => '192x192' ),
					),
				),
			),
		);

		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		echo json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}

	/* ── Service Worker ───────────────────────────────────────────────── */
	if ( $path === '/sw.js' ) {
		$sw_file = get_template_directory() . '/assets/sw.js';
		if ( ! file_exists( $sw_file ) ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );   // allow full-site scope
		header( 'Cache-Control: no-cache' );
		readfile( $sw_file );
		exit;
	}
}, 1 ); // priority 1 → runs before WP's own template selection

/* ──────────────────────────────────────────────────────────────────────
 * 2. Inject PWA meta tags into <head>
 * ────────────────────────────────────────────────────────────────────── */

add_action( 'wp_head', function () {
	$theme = get_template_directory_uri();
	$name  = esc_attr( get_bloginfo( 'name' ) ?: 'Orienta Yacht Club' );
	?>
<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.json' ) ); ?>">
<meta name="theme-color" content="#0b2a4a">

<!-- iOS / Safari PWA -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo $name; ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $theme . '/assets/icons/apple-touch-icon.png' ); ?>">

<!-- MS Tile (Edge / Windows) -->
<meta name="msapplication-TileColor" content="#0b2a4a">
<meta name="msapplication-TileImage" content="<?php echo esc_url( $theme . '/assets/icons/icon-192.png' ); ?>">
	<?php
}, 1 );

/* ──────────────────────────────────────────────────────────────────────
 * 3. Register the service worker (footer script)
 * ────────────────────────────────────────────────────────────────────── */

add_action( 'wp_footer', function () {
	?>
<script>
if ( 'serviceWorker' in navigator ) {
	window.addEventListener( 'load', function () {
		navigator.serviceWorker.register( '/sw.js', { scope: '/' } )
			.then( function ( reg ) {
				// SW registered — check for updates every 60 min
				setInterval( function () { reg.update(); }, 60 * 60 * 1000 );
			} )
			.catch( function ( err ) {
				console.warn( 'OYC SW registration failed:', err );
			} );
	} );
}
</script>
	<?php
} );

<?php
/**
 * OYC Fun — child theme of orienta-yacht-club-theme.
 * Loads the parent's full stylesheet first; the child style.css (enqueued by
 * the parent as "oyc-style") layers a light refresh on top. Keeps the parent's
 * Open Sans fonts — no extra webfonts.
 *
 * @package OYC_Fun
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Parent base styles (parent enqueues get_stylesheet_uri(), which in a child
// theme points at THIS theme, so load the parent's CSS explicitly first).
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'oyc-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		'fun-base'
	);
}, 5 );

// Cache-bust the child stylesheet by its file mtime on every deploy.
add_action( 'wp_enqueue_scripts', function () {
	global $wp_styles;
	if ( isset( $wp_styles->registered['oyc-style'] ) ) {
		$f = get_stylesheet_directory() . '/style.css';
		if ( file_exists( $f ) ) {
			$wp_styles->registered['oyc-style']->ver = (string) filemtime( $f );
		}
	}
}, 99 );

// Auto-scrolling photo marquee (home-* images) placed just below the menu on
// every page. Output at wp_footer, then moved directly after the header via JS.
add_action( 'wp_footer', function () {
	$dir = get_template_directory() . '/assets/photos/';
	$uri = get_template_directory_uri() . '/assets/photos/';
	$files = glob( $dir . 'home*.{jpg,jpeg,png,webp}', GLOB_BRACE );
	if ( ! $files ) { return; }
	sort( $files );
	$cells = '';
	foreach ( $files as $f ) {
		$cells .= '<span style="background-image:url(' . esc_url( $uri . basename( $f ) ) . ')"></span>';
	}
	// Track is duplicated so the loop is seamless (translateX -50% = one full set).
	echo '<div class="oyc-marquee" aria-hidden="true"><div class="oyc-marquee__track">' . $cells . $cells . '</div></div>';
	echo '<script>(function(){var m=document.querySelector(".oyc-marquee");var h=document.querySelector(".site-header,header");if(!m||!h||!h.parentNode){return;}h.parentNode.insertBefore(m,h.nextSibling);function s(){m.style.marginTop=(h.offsetHeight||88)+"px";}s();window.addEventListener("resize",s);document.body.classList.add("oyc-has-marquee");})();</script>';
} );

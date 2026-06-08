<?php
/**
 * OYC Fun — child theme of orienta-yacht-club-theme.
 * Loads the parent's full stylesheet first, then the playful fonts; the child
 * style.css (enqueued by the parent as "oyc-style") layers on top.
 *
 * @package OYC_Fun
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', function () {
	// Parent base styles (the parent enqueues get_stylesheet_uri(), which in a
	// child theme points at THIS theme — so load the parent's CSS explicitly).
	wp_enqueue_style(
		'oyc-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		'fun-1.0.0'
	);
	// Playful Google fonts used by the fun palette.
	wp_enqueue_style(
		'oyc-fun-fonts',
		'https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap',
		array(),
		null
	);
}, 5 );

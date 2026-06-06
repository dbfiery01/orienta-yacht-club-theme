<?php
/**
 * Orienta Yacht Club theme functions.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_VERSION', '1.0.43' );

/**
 * Theme setup.
 */
function oyc_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'custom-logo', array(
		'height'      => 88,
		'width'       => 88,
		'flex-width'  => true,
		'flex-height' => true,
	) );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'orienta-yacht-club' ),
		'footer'  => __( 'Footer Menu', 'orienta-yacht-club' ),
	) );

	load_theme_textdomain( 'orienta-yacht-club', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'oyc_setup' );

/**
 * Enqueue front-end assets.
 */
function oyc_enqueue_assets() {
	wp_enqueue_style(
		'oyc-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'oyc-style',
		get_stylesheet_uri(),
		array( 'oyc-fonts' ),
		OYC_VERSION
	);

	// Inject the theme's wave SVG path so the CSS can use it.
	$waves_url = get_template_directory_uri() . '/assets/waves.svg';
	wp_add_inline_style( 'oyc-style', ':root { --oyc-waves: url("' . esc_url( $waves_url ) . '"); }' );

	wp_enqueue_script(
		'oyc-main',
		get_template_directory_uri() . '/assets/main.js',
		array(),
		OYC_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'oyc_enqueue_assets' );

/**
 * Default content map (used by both Customizer and front-end).
 */
require_once get_template_directory() . '/inc/defaults.php';

/**
 * Customizer settings.
 */
require_once get_template_directory() . '/inc/customizer.php';

/**
 * Google Calendar → The Events Calendar auto-importer.
 */
require_once get_template_directory() . '/inc/google-cal-import.php';

/**
 * Members login experience: custom login page, dashboard, members-only restriction.
 */
require_once get_template_directory() . '/inc/login-experience.php';

/**
 * Membership application: CPT registration + CF7 save hook + admin columns.
 */
require_once get_template_directory() . '/inc/application-handler.php';

/**
 * Admin inbox: OYC Inbox menu, applications + messages pages, contact-form save hook.
 */
require_once get_template_directory() . '/inc/admin-inbox.php';

/**
 * Progressive Web App: manifest, service worker, and iOS meta tags.
 */
require_once get_template_directory() . '/inc/pwa.php';

/**
 * Dynamic iCal feed for the club calendar (live from Calendarize it!).
 */
require_once get_template_directory() . '/inc/calendar-feed.php';

/**
 * Helper: get a Customizer setting, falling back to the central defaults map
 * so the live site shows the same starter copy that appears in the Customizer
 * inputs — even before the user clicks Publish.
 *
 * @param string      $key      Theme-mod key.
 * @param string|null $fallback Override the default-map value if needed.
 */
function oyc_get( $key, $fallback = null ) {
	if ( $fallback === null ) {
		$defaults = oyc_defaults();
		$fallback = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
	return get_theme_mod( $key, $fallback );
}

/**
 * Helper: render the club mark — uses the Customizer's "Site Logo" if set,
 * otherwise falls back to the bundled placeholder burgee SVG.
 *
 * @param string $class CSS class for the image.
 */
function oyc_burgee( $class = 'brand-mark' ) {
	// 1. Logo PNG dropped into the theme's assets folder (highest priority).
	$logo_file = get_template_directory() . '/assets/oyc-logo.png';
	if ( file_exists( $logo_file ) ) {
		printf(
			'<img src="%1$s" alt="%2$s" class="%3$s" />',
			esc_url( get_template_directory_uri() . '/assets/oyc-logo.png' ),
			esc_attr( get_bloginfo( 'name' ) ),
			esc_attr( $class . ' logo-img' )
		);
		return;
	}

	// 2. Customizer → Site Identity → Logo (WP media library).
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( ! $logo_url ) {
			$logo_url = wp_get_attachment_url( $logo_id );
		}
		if ( $logo_url ) {
			printf(
				'<img src="%1$s" alt="%2$s" class="%3$s" />',
				esc_url( $logo_url ),
				esc_attr( get_bloginfo( 'name' ) ),
				esc_attr( $class )
			);
			return;
		}
	}

	// 3. Fallback: bundled placeholder burgee SVG.
	printf(
		'<img src="%1$s" alt="" class="%2$s" />',
		esc_url( get_template_directory_uri() . '/assets/burgee.svg' ),
		esc_attr( $class )
	);
}

/**
 * Returns true when a real club logo file is present in the assets folder.
 * Templates use this to decide whether to show/hide the text lockup beside the logo.
 */
function oyc_has_real_logo() {
	return file_exists( get_template_directory() . '/assets/oyc-logo.png' )
		|| (bool) get_theme_mod( 'custom_logo' );
}

/**
 * Allow SVG uploads in the media library so the club's real burgee/logo can be uploaded.
 * Restricted to users who can already upload files.
 */
function oyc_allow_svg_uploads( $mimes ) {
	if ( current_user_can( 'upload_files' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}
	return $mimes;
}
add_filter( 'upload_mimes', 'oyc_allow_svg_uploads' );

/**
 * Mark the front page and all pages (which render a full hero) so the header
 * can blend into the hero image — transparent over the hero, solid on scroll —
 * giving every page the same "one full-size image" treatment as the home page.
 * Post-type archives (e.g. the events calendar) are excluded and keep the solid header.
 */
function oyc_hero_header_body_class( $classes ) {
	if ( is_front_page() || is_page() ) {
		$classes[] = 'has-hero-header';
	}
	// Fully-immersive pages (fixed photo background): the menu stays transparent
	// the whole way down instead of turning solid on scroll.
	if ( is_front_page() || is_page( array( 'about', 'welcome-guests', 'membership-application', 'calendar', 'contact', 'members-area' ) ) ) {
		$classes[] = 'oyc-immersive';
	}
	return $classes;
}
add_filter( 'body_class', 'oyc_hero_header_body_class' );

/**
 * Pretty-print bullets entered as one-per-line in the Customizer.
 */
function oyc_render_bullets( $raw ) {
	if ( empty( $raw ) ) {
		return;
	}
	$lines = preg_split( "/\r\n|\n|\r/", $raw );
	echo '<ul class="bullets">';
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		// Optional link syntax: "Label | https://example.com" → linked bullet.
		if ( strpos( $line, '|' ) !== false ) {
			list( $label, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( $url !== '' && filter_var( $url, FILTER_VALIDATE_URL ) ) {
				echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a></li>';
				continue;
			}
			$line = $label;
		}
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul>';
}

/**
 * Render a strip of club video thumbnails that link to the /videos/ gallery.
 * Used at the bottom of the homepage Membership section and the member dashboard.
 *
 * @param string $heading Optional heading shown above the thumbnails.
 */
function oyc_video_thumbs( $heading = 'Club Videos' ) {
	// YouTube video ID => caption. Thumbnails are pulled from img.youtube.com.
	$videos = array(
		'-cYW29F4Qn4' => "Governor's Cup 2024",
		'zNLmKy_COpE' => 'Mamaroneck Harbor in 4K',
		'y9SNwmwNHkY' => 'On the Water at OYC',
		'T7UzxJq4wQU' => 'Stop-Motion Boat Haul',
	);
	$url = home_url( '/videos/' );

	echo '<div class="video-thumbs">';
	if ( $heading ) {
		echo '<h3 class="video-thumbs__heading">' . esc_html( $heading ) . '</h3>';
	}
	echo '<div class="video-thumbs__grid">';
	foreach ( $videos as $id => $title ) {
		echo '<a class="video-thumb" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $title ) . '">';
		echo '<img src="' . esc_url( 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg' ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" />';
		echo '<span class="video-thumb__play" aria-hidden="true"></span>';
		echo '<span class="video-thumb__title">' . esc_html( $title ) . '</span>';
		echo '</a>';
	}
	echo '</div>';
	echo '<p class="video-thumbs__more"><a href="' . esc_url( $url ) . '">' . esc_html__( 'View all videos →', 'orienta-yacht-club' ) . '</a></p>';
	echo '</div>';
}

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

// Photo carousel (home-* images) placed just below the page title banner.
// Static, auto-advances to the right every 5s, with left/right arrows.
add_action( 'wp_footer', function () {
	$dir = get_template_directory() . '/assets/photos/';
	$uri = get_template_directory_uri() . '/assets/photos/';
	$files = glob( $dir . 'home*.{jpg,jpeg,png,webp}', GLOB_BRACE );
	if ( ! $files ) { return; }
	sort( $files );
	$cells = '';
	foreach ( $files as $f ) {
		$cells .= '<span class="oyc-carousel__cell" style="background-image:url(' . esc_url( $uri . basename( $f ) ) . ')"></span>';
	}
	// Club videos (YouTube) — thumbnails link to the videos page, with a play badge.
	$videos = array( '-cYW29F4Qn4', 'zNLmKy_COpE', 'y9SNwmwNHkY', 'T7UzxJq4wQU' );
	$vurl   = esc_url( home_url( '/videos/' ) );
	foreach ( $videos as $vid ) {
		$thumb = 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg';
		$cells .= '<a class="oyc-carousel__cell oyc-carousel__cell--video" href="' . $vurl . '" style="background-image:url(' . esc_url( $thumb ) . ')"><span class="oyc-carousel__play" aria-hidden="true"></span></a>';
	}
	// Track duplicated so the auto-advance can loop seamlessly.
	echo '<div class="oyc-carousel">'
		. '<button type="button" class="oyc-carousel__nav oyc-carousel__nav--prev" aria-label="Previous photos">&#8249;</button>'
		. '<div class="oyc-carousel__viewport"><div class="oyc-carousel__track">' . $cells . $cells . '</div></div>'
		. '<button type="button" class="oyc-carousel__nav oyc-carousel__nav--next" aria-label="Next photos">&#8250;</button>'
		. '</div>';
	echo '<script>(function(){var c=document.querySelector(".oyc-carousel");if(!c)return;var home=document.body.classList.contains("home");if(home){var h=document.querySelector(".site-header,header");if(!h||!h.parentNode){if(c.parentNode)c.parentNode.removeChild(c);return;}h.parentNode.insertBefore(c,h.nextSibling);var mt=function(){c.style.marginTop=(h.offsetHeight||88)+"px";};mt();window.addEventListener("resize",mt);document.body.classList.add("oyc-home-reel-top");}else{var ph=document.querySelector(".page-hero");if(!ph||!ph.parentNode){if(c.parentNode)c.parentNode.removeChild(c);return;}ph.parentNode.insertBefore(c,ph.nextSibling);}var t=c.querySelector(".oyc-carousel__track");var n=t.children.length/2;var i=0;function w(){return t.children[0].getBoundingClientRect().width;}function set(anim){t.style.transition=anim?"transform .6s ease":"none";t.style.transform="translateX("+(-i*w())+"px)";}function next(){i++;set(true);if(i>=n){setTimeout(function(){i=0;set(false);},650);}}function prev(){if(i<=0){i=n;set(false);void t.offsetWidth;}i--;set(true);}var tm;function arm(){clearInterval(tm);tm=setInterval(next,10000);}c.querySelector(".oyc-carousel__nav--next").addEventListener("click",function(){next();arm();});c.querySelector(".oyc-carousel__nav--prev").addEventListener("click",function(){prev();arm();});window.addEventListener("resize",function(){set(false);});arm();})();</script>';
} );

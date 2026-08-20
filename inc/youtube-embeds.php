<?php
/**
 * Repair YouTube oEmbed markup.
 *
 * WordPress caches the oEmbed HTML it fetched when a URL was first embedded, in
 * post meta, effectively forever. Embeds added years ago therefore still render
 * YouTube's old iframe format:
 *
 *   <iframe src="…/embed/ID?feature=oembed" frameborder="0" allowfullscreen>
 *
 * That format predates the `allow` and `referrerpolicy` attributes the player
 * now requires, and YouTube fails it with "Error 153 — video player
 * configuration error". A lazy-loader that parks the real URL in data-src and
 * sets src="about:blank" makes it worse, because the player cannot then work out
 * which domain is embedding it.
 *
 * This filter runs on render, so it repairs cached markup without needing the
 * oEmbed cache flushed, and keeps working for anything embedded later.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Add the attributes a modern YouTube embed needs, and mark the iframe so
 * lazy-loaders leave its src alone.
 *
 * @param string $html The oEmbed HTML.
 * @return string
 */
function oyc_fix_youtube_embed_html( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<iframe' ) ) {
		return $html;
	}

	if ( ! preg_match( '~(youtube(-nocookie)?\.com|youtu\.be)~i', $html ) ) {
		return $html;
	}

	$add = array();

	// The player needs these to configure itself; old cached markup has neither.
	if ( ! preg_match( '~\sallow\s*=~i', $html ) ) {
		$add[] = 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"';
	}

	if ( ! preg_match( '~\sreferrerpolicy\s*=~i', $html ) ) {
		$add[] = 'referrerpolicy="strict-origin-when-cross-origin"';
	}

	// Opt out of JS lazy-loading: swapping src from about:blank loses the
	// embedding context. Native loading="lazy" gives the same benefit safely.
	if ( ! preg_match( '~\sdata-no-lazy\s*=~i', $html ) ) {
		$add[] = 'data-no-lazy="1"';
		$add[] = 'data-skip-lazy="1"';
	}

	if ( ! preg_match( '~\sloading\s*=~i', $html ) ) {
		$add[] = 'loading="lazy"';
	}

	if ( $add ) {
		$html = preg_replace( '~<iframe~i', '<iframe ' . implode( ' ', $add ), $html, 1 );
	}

	// Lazy-load plugins key off these class names.
	if ( preg_match( '~<iframe[^>]*\sclass\s*=\s*"([^"]*)"~i', $html, $m ) ) {
		if ( false === stripos( $m[1], 'no-lazyload' ) ) {
			$html = str_replace( 'class="' . $m[1] . '"', 'class="' . $m[1] . ' no-lazyload skip-lazy"', $html );
		}
	} else {
		$html = preg_replace( '~<iframe~i', '<iframe class="no-lazyload skip-lazy"', $html, 1 );
	}

	return $html;
}

add_filter( 'embed_oembed_html', 'oyc_fix_youtube_embed_html', 20 );
add_filter( 'oembed_result', 'oyc_fix_youtube_embed_html', 20 );
add_filter( 'the_content', 'oyc_fix_youtube_embed_html', 20 );

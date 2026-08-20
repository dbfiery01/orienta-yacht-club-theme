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
 * Replace a YouTube iframe with a thumbnail that opens the video on YouTube.
 *
 * The embedded player returns "Error 153 — video player configuration error" on
 * this site even with correct markup: the videos are public and embeddable
 * (their oEmbed endpoints all return 200), the iframe carries allow and
 * referrerpolicy, lazy-loading is disabled on it, and the page sends no header
 * that would interfere. The failure is inside YouTube's player and is not
 * something the embedding page can fix.
 *
 * A thumbnail facade always works, weighs a few KB against the player's ~800KB,
 * and loads no third-party scripts. Filterable so embeds can be restored if
 * YouTube starts cooperating again.
 *
 * @param string $html The oEmbed HTML.
 * @return string
 */
function oyc_fix_youtube_embed_html( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<iframe' ) ) {
		return $html;
	}

	/**
	 * Return true to keep the real YouTube iframe instead of the facade.
	 *
	 * @param bool $use_player Whether to embed the player.
	 */
	if ( apply_filters( 'oyc_youtube_use_player', false ) ) {
		return $html;
	}

	// data-src as well as src: a lazy-loader may already have swapped it out.
	if ( ! preg_match( '~(?:data-)?src=["\']https?://(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})~i', $html, $m ) ) {
		return $html;
	}

	$id    = $m[1];
	$title = preg_match( '~title=["\']([^"\']*)~i', $html, $t ) ? $t[1] : __( 'Club video', 'orienta-yacht-club' );

	return sprintf(
		'<a class="oyc-yt" href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">'
			. '<img class="oyc-yt__thumb" src="%3$s" alt="" loading="lazy" width="480" height="360" />'
			. '<span class="oyc-yt__play" aria-hidden="true"></span>'
		. '</a>',
		esc_url( 'https://www.youtube.com/watch?v=' . $id ),
		esc_attr( sprintf( __( 'Watch %s on YouTube', 'orienta-yacht-club' ), $title ) ),
		esc_url( 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg' )
	);
}

/**
 * Content-level pass, for iframes written directly into a post rather than
 * produced by oEmbed. Replaces each iframe individually — oyc_fix_youtube_embed_html()
 * returns a single facade, so it must never be handed a whole document.
 *
 * @param string $content Post content.
 * @return string
 */
function oyc_fix_youtube_embeds_in_content( $content ) {
	if ( ! is_string( $content ) || false === stripos( $content, '<iframe' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'~<iframe\b[^>]*>.*?</iframe>|<iframe\b[^>]*/?>~is',
		function ( $m ) {
			return oyc_fix_youtube_embed_html( $m[0] );
		},
		$content
	);
}

add_filter( 'embed_oembed_html', 'oyc_fix_youtube_embed_html', 20 );
add_filter( 'the_content', 'oyc_fix_youtube_embeds_in_content', 21 );

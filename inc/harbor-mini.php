<?php
/**
 * [oyc_harbor_mini] — compact live "Harbor Conditions" widget (time, current
 * tide, sunrise, sunset) that links to the full /weather/ signage board.
 *
 * Drop the shortcode into any page/post (e.g. a Divi Text module) to render it.
 * Assets (harbor-mini.css / harbor-mini.js) are enqueued only on singular
 * content whose body actually contains the shortcode, so they never load
 * site-wide. The JS/CSS live in real files (not inline) so WordPress's
 * auto-formatting can't mangle them.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditionally enqueue the widget assets.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( $post && has_shortcode( $post->post_content, 'oyc_harbor_mini' ) ) {
		wp_enqueue_style(
			'oyc-harbor-mini',
			get_template_directory_uri() . '/assets/harbor-mini.css',
			array(),
			OYC_VERSION
		);
		wp_enqueue_script(
			'oyc-harbor-mini',
			get_template_directory_uri() . '/assets/harbor-mini.js',
			array(),
			OYC_VERSION,
			true
		);
	}
} );

/**
 * Render the widget markup. Values are filled in client-side by harbor-mini.js.
 */
add_shortcode( 'oyc_harbor_mini', function () {
	$url = esc_url( home_url( '/weather/' ) );
	ob_start();
	?>
	<a class="oyc-harbor-mini" href="<?php echo $url; ?>" aria-label="Live Mamaroneck Harbor conditions — open the full board">
		<div class="ohm-head">
			<span>Harbor Conditions</span>
			<span class="ohm-live"><span class="ohm-dot"></span>Live</span>
		</div>
		<div class="ohm-grid">
			<div class="ohm-cell"><span class="ohm-k">Time</span><span class="ohm-v" data-ohm="time">&mdash;</span></div>
			<div class="ohm-cell"><span class="ohm-k">Tide</span><span class="ohm-v" data-ohm="tide">&mdash;</span></div>
			<div class="ohm-cell"><span class="ohm-k">Sunrise</span><span class="ohm-v" data-ohm="sunrise">&mdash;</span></div>
			<div class="ohm-cell"><span class="ohm-k">Sunset</span><span class="ohm-v" data-ohm="sunset">&mdash;</span></div>
		</div>
		<div class="ohm-cta">View full live conditions <span class="ohm-arr">&rarr;</span></div>
	</a>
	<?php
	return ob_get_clean();
} );

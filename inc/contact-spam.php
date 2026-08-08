<?php
/**
 * Contact-form spam protection — honeypot + submission timing check.
 *
 * Two complementary layers, both server-side, no third-party dependencies:
 *
 * 1. Honeypot — a hidden field bots fill but humans never see. Catches simple
 *    bots that blindly populate every input.
 *
 * 2. Timing check — JS stamps the page-load time into a hidden field; the
 *    server rejects submissions that arrive in under OYC_SPAM_MIN_SECONDS.
 *    Catches JS-capable bots that skip the honeypot but still submit faster
 *    than any human can read and fill a form. If JS never ran (headless
 *    scrapers, curl) the timestamp field is empty, which is also flagged.
 *
 * For stronger coverage still, enable Cloudflare Turnstile or reCAPTCHA v3
 * in Contact → Integration (invisible, no user friction).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Minimum seconds a real human needs to fill in a contact form.
define( 'OYC_SPAM_MIN_SECONDS', 4 );

/**
 * 1. Inject the honeypot + timing field into every CF7 form.
 *    Both are off-screen / aria-hidden so real users are unaffected.
 *    The timing field is populated by a small inline script on page load.
 */
add_filter( 'wpcf7_form_elements', function ( $html ) {
	$honeypot = '<div class="oyc-hp-wrap" aria-hidden="true" '
		. 'style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden;">'
		. '<label>Please leave this field blank'
		. '<input type="text" name="oyc-website" value="" tabindex="-1" autocomplete="off">'
		. '</label></div>';

	$timing = '<input type="hidden" name="oyc-ts" value="" aria-hidden="true">'
		. '<script>(function(){'
		. 'var f=document.querySelector("input[name=\'oyc-ts\']");'
		. 'if(f){f.value=Math.floor(Date.now()/1000);}'
		. '}());</script>';

	return $html . $honeypot . $timing;
} );

/**
 * 2. Flag the submission as spam when either check fails.
 */
add_filter( 'wpcf7_spam', function ( $spam, $submission = null ) {
	if ( $spam ) {
		return $spam;
	}

	$log = function ( $reason ) use ( $submission ) {
		if ( $submission && method_exists( $submission, 'add_spam_log' ) ) {
			$submission->add_spam_log( array( 'agent' => 'oyc_spam', 'reason' => $reason ) );
		}
	};

	// Honeypot check.
	$hp = isset( $_POST['oyc-website'] ) ? trim( (string) wp_unslash( $_POST['oyc-website'] ) ) : '';
	if ( '' !== $hp ) {
		$log( 'Honeypot field was filled — likely an automated bot.' );
		return true;
	}

	// Timing check.
	$ts = isset( $_POST['oyc-ts'] ) ? (int) wp_unslash( $_POST['oyc-ts'] ) : 0;
	if ( 0 === $ts ) {
		$log( 'Timing field was empty — JS did not run; likely a headless bot.' );
		return true;
	}
	$elapsed = time() - $ts;
	if ( $elapsed < OYC_SPAM_MIN_SECONDS ) {
		$log( sprintf( 'Form submitted in %ds — faster than any human (min %ds).', $elapsed, OYC_SPAM_MIN_SECONDS ) );
		return true;
	}

	return $spam;
}, 10, 2 );

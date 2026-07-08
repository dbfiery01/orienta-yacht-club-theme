<?php
/**
 * Contact-form spam protection — honeypot (no plugin, no third-party, invisible).
 *
 * When the site went public, bots began crawling and auto-submitting the CF7
 * contact form. This adds a honeypot: a hidden field a real person never sees or
 * fills, but automated bots populate. Any submission with it filled is flagged
 * as spam, so CF7 blocks the email and the OYC Inbox hook (wpcf7_mail_sent /
 * wpcf7_mail_failed) never saves it.
 *
 * A honeypot is used because it has virtually zero false positives — a member
 * can't fill a field they can't see — so no legitimate inquiry is lost. It is
 * also cache-safe: the field is static (always empty) in the page HTML, and the
 * spam check runs server-side on the POST, which is never cached.
 *
 * For stronger coverage against JS-capable bots, enable Cloudflare Turnstile or
 * reCAPTCHA v3 in Contact → Integration (invisible, no user friction).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Inject the honeypot into every CF7 form. Pushed off-screen and marked
 *    aria-hidden + tabindex="-1" so screen-reader and keyboard users skip it.
 *    Named "oyc-website" because bots eagerly fill website/URL-looking fields.
 */
add_filter( 'wpcf7_form_elements', function ( $html ) {
	$field = '<div class="oyc-hp-wrap" aria-hidden="true" '
		. 'style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden;">'
		. '<label>Please leave this field blank'
		. '<input type="text" name="oyc-website" value="" tabindex="-1" autocomplete="off">'
		. '</label></div>';
	return $html . $field;
} );

/**
 * 2. Flag the submission as spam when the honeypot was filled.
 */
add_filter( 'wpcf7_spam', function ( $spam, $submission = null ) {
	if ( $spam ) {
		return $spam;
	}
	$hp = isset( $_POST['oyc-website'] ) ? trim( (string) wp_unslash( $_POST['oyc-website'] ) ) : '';
	if ( '' !== $hp ) {
		if ( $submission && method_exists( $submission, 'add_spam_log' ) ) {
			$submission->add_spam_log( array(
				'agent'  => 'oyc_honeypot',
				'reason' => 'Honeypot field was filled — likely an automated bot.',
			) );
		}
		return true;
	}
	return $spam;
}, 10, 2 );

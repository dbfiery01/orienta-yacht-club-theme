<?php
/**
 * Protected member documents.
 *
 * Some member documents (the slip waiting list, dock assignments) contain member
 * PII and must NOT be reachable at a public URL. This mirrors the Fleet Roster
 * PDF protection: the file is moved out of the public uploads path into a
 * deny-all folder, and streamed through /member-doc/{key}/ only after a login
 * check. The PII never lives in the theme repo — only on the server, in the
 * deny-all folder, keyed by an option.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of protected documents.
 *
 * key => array(
 *   'src'      => path under wp-content/uploads/ of the originally-uploaded PDF,
 *   'download' => filename presented when the protected file is served.
 * )
 */
function oyc_protected_docs() {
	return array(
		'slip-waiting-list' => array(
			'src'      => '2025/10/Slip-Wait-List-Oct-2025.pdf',
			'download' => 'Slip-Waiting-List.pdf',
		),
		'dock-assignments'  => array(
			'src'      => '2025/03/2025-Dock-assignment.pdf',
			'download' => 'Dock-Assignments.pdf',
		),
	);
}

/**
 * Members-only URL used to reach a protected document.
 *
 * @param string $key Registry key.
 * @return string
 */
function oyc_protected_doc_url( $key ) {
	return home_url( '/member-doc/' . $key . '/' );
}

/**
 * Move one registered document out of public uploads into the deny-all folder.
 * Idempotent — once the option points at an existing file it is a no-op.
 *
 * @param string $key Registry key.
 * @return bool True when the document is protected (just now or already).
 */
function oyc_protect_doc( $key ) {
	$docs = oyc_protected_docs();
	if ( ! isset( $docs[ $key ] ) ) {
		return false;
	}

	$opt = 'oyc_protected_doc_' . $key;
	$cur = (string) get_option( $opt, '' );
	if ( $cur && file_exists( $cur ) ) {
		return true; // already protected
	}

	$up  = wp_upload_dir();
	$dir = $up['basedir'] . '/oyc-protected';
	$src = $up['basedir'] . '/' . ltrim( $docs[ $key ]['src'], '/' );

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Block direct web access to everything in the folder.
	file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
	file_put_contents( $dir . '/index.html', '' );

	if ( ! file_exists( $src ) ) {
		return false; // nothing to move (not uploaded, or already moved away)
	}

	$dest = $dir . '/' . $key . '-' . wp_generate_password( 16, false, false ) . '.pdf';
	if ( @rename( $src, $dest ) || ( @copy( $src, $dest ) && @unlink( $src ) ) ) {
		update_option( $opt, $dest, false );
		return true;
	}
	return false;
}

/**
 * After a deploy, move any not-yet-protected documents into the deny-all folder
 * automatically the next time an admin loads wp-admin. No manual step needed.
 */
add_action( 'admin_init', function () {
	foreach ( array_keys( oyc_protected_docs() ) as $key ) {
		if ( ! get_option( 'oyc_protected_doc_' . $key, '' ) ) {
			oyc_protect_doc( $key );
		}
	}
} );

/**
 * Admin-only REST trigger to (re)run protection on demand.
 * POST /wp-json/oyc/v1/protect-doc            → protect all
 * POST /wp-json/oyc/v1/protect-doc?key=slug   → protect one
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'oyc/v1', '/protect-doc', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( $req ) {
			$key = sanitize_key( (string) $req->get_param( 'key' ) );
			if ( $key ) {
				return array( 'ok' => oyc_protect_doc( $key ) );
			}
			$out = array();
			foreach ( array_keys( oyc_protected_docs() ) as $k ) {
				$out[ $k ] = oyc_protect_doc( $k );
			}
			return array( 'ok' => true, 'results' => $out );
		},
	) );
} );

/**
 * Members-only streaming of a protected document at /member-doc/{key}/.
 * Non-members are sent to the login screen; the file lives in the deny-all
 * folder, so its original public URL no longer serves it.
 */
add_action( 'template_redirect', function () {
	$path = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
	$path = trim( (string) $path, '/' );
	if ( 0 !== strpos( $path, 'member-doc/' ) ) {
		return;
	}

	$key  = sanitize_key( substr( $path, strlen( 'member-doc/' ) ) );
	$docs = oyc_protected_docs();
	if ( ! $key || ! isset( $docs[ $key ] ) ) {
		status_header( 404 );
		exit;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}

	$file = (string) get_option( 'oyc_protected_doc_' . $key, '' );
	if ( ! $file || ! file_exists( $file ) ) {
		status_header( 404 );
		exit;
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . $docs[ $key ]['download'] . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'X-Robots-Tag: noindex, nofollow' );
	readfile( $file );
	exit;
}, 0 );

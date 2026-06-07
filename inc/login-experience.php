<?php
/**
 * OYC Members Login Experience
 * - Custom-styled login page
 * - Post-login redirect to member dashboard
 * - Members-only page restriction
 * - Registration / application link on login page
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── 1. Custom login page styles & branding ───────────────── */

add_action( 'login_enqueue_scripts', function () {
	wp_enqueue_style(
		'oyc-login',
		get_template_directory_uri() . '/assets/login.css',
		array(),
		OYC_VERSION
	);
} );

// Replace WordPress logo URL with OYC home page
add_filter( 'login_headerurl',  fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );

// Add "Not a member? Apply for membership →" and back-to-site link below the form
add_action( 'login_footer', function () {
	$apply_url = home_url( '/membership-application/' );
	$home_url  = home_url( '/' );
	echo '<p class="login-footer-note">' .
		'Not a member? <a href="' . esc_url( $apply_url ) . '">Apply for membership &rarr;</a>' .
		'</p>';
} );

// Show club name as page title above the form
add_filter( 'login_title', fn( $title ) => get_bloginfo( 'name' ) . ' — Member Login' );

/* ── 2. Redirect after login → member dashboard ──────────── */

add_filter( 'login_redirect', function ( $redirect_to, $requested_redirect_to, $user ) {
	if ( $user && ! is_wp_error( $user ) ) {
		// Admins go to their profile page; members go to members area
		if ( user_can( $user, 'manage_options' ) ) {
			return admin_url( 'profile.php' );
		}
		return home_url( '/members-area/' );
	}
	return $redirect_to;
}, 10, 3 );

/* ── 3. Members-only page restriction ────────────────────── */

add_action( 'template_redirect', function () {
	// Only check singular pages
	if ( ! is_singular( 'page' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( get_post_meta( $post_id, '_oyc_members_only', true ) && ! is_user_logged_in() ) {
		wp_redirect( wp_login_url( get_permalink() ) );
		exit;
	}
} );

// Mark pages as members-only via postmeta
// Usage: add meta _oyc_members_only = 1 to any page post

/* ── 4. Admin column: show members-only badge on Pages list ─ */

add_filter( 'manage_pages_columns', function ( $columns ) {
	$columns['oyc_members_only'] = 'Members Only';
	return $columns;
} );

add_action( 'manage_pages_custom_column', function ( $column, $post_id ) {
	if ( $column === 'oyc_members_only' ) {
		echo get_post_meta( $post_id, '_oyc_members_only', true )
			? '<span style="color:#D4A851;font-weight:700;">&#x1F512; Yes</span>'
			: '—';
	}
}, 10, 2 );

/* ── 5. Header login button: local wp_login_url ──────────── */
// (header.php is updated separately — this hook ensures the
//  logout link also redirects cleanly back to the home page)

add_filter( 'logout_redirect', fn() => home_url( '/' ) );

/* ── 6. Replace "Howdy" in the WordPress admin toolbar ──────── */

add_filter( 'gettext', function ( $translated, $original ) {
	if ( $original === 'Howdy, %s' ) {
		$hour = (int) current_time( 'H' );
		if ( $hour < 12 ) {
			return 'Good Morning, %s';
		} elseif ( $hour < 18 ) {
			return 'Good Afternoon, %s';
		} else {
			return 'Good Evening, %s';
		}
	}
	return $translated;
}, 10, 2 );

/* ── 7. Base the greeting on the visitor's BROWSER timezone, and address
 *  them by FIRST NAME ─────────────────────────────────────────────────
 *  The PHP greeting above uses the site/server time. The server can be in a
 *  different timezone than the user, so re-evaluate the time of day in the
 *  browser and correct the toolbar greeting + dashboard greeting client-side.
 *  We also swap the toolbar's display name for the user's first name. */

function oyc_browser_greeting_script() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$user    = wp_get_current_user();
	$first   = $user->first_name ? $user->first_name : $user->display_name;
	$display = $user->display_name;
	?>
	<script>
	(function () {
		var first   = <?php echo wp_json_encode( $first ); ?>;
		var display = <?php echo wp_json_encode( $display ); ?>;
		function apply() {
			var h  = new Date().getHours();
			var g  = h < 12 ? 'Good Morning' : ( h < 18 ? 'Good Afternoon' : 'Good Evening' );
			var rx = /Good (Morning|Afternoon|Evening)/;
			// WordPress admin toolbar greeting word ("Good X, Name")
			var acct = document.querySelector('#wp-admin-bar-my-account > .ab-item');
			if ( acct ) {
				Array.prototype.forEach.call( acct.childNodes, function ( n ) {
					if ( n.nodeType === 3 && rx.test( n.nodeValue ) ) { n.nodeValue = n.nodeValue.replace( rx, g ); }
				} );
			}
			// Toolbar: address the user by first name instead of the display name.
			// Only touch spans that actually hold the display name (not e.g. "Edit Profile").
			Array.prototype.forEach.call( document.querySelectorAll('#wpadminbar .display-name'), function ( el ) {
				if ( el.textContent.trim() === display ) { el.textContent = first; }
			} );
			// Member dashboard page greeting word.
			var eb = document.querySelector('.page-hero--dashboard .page-hero-eyebrow');
			if ( eb && rx.test( eb.textContent ) ) { eb.textContent = eb.textContent.replace( rx, g ); }
		}
		// The admin bar is rendered later in the footer than this script tag, so
		// wait for the full DOM before querying it.
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', apply );
		} else {
			apply();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'oyc_browser_greeting_script' );
add_action( 'admin_footer', 'oyc_browser_greeting_script' );

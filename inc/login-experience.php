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

// Two clearly separated routes below the form. The single old line ("Not a
// member? Apply for membership") was the only option offered, which sent
// existing members — who have already joined and just lack a login — into the
// prospective-member application by mistake.
add_action( 'login_footer', function () {
	$signup_url = home_url( '/member-login-setup/' );
	?>
	<div class="login-routes">
		<p class="login-route">
			<span class="login-route-q"><?php esc_html_e( 'Already a member, but no login yet?', 'orienta-yacht-club' ); ?></span>
			<a href="<?php echo esc_url( $signup_url ); ?>"><?php esc_html_e( 'Set up your login', 'orienta-yacht-club' ); ?> &rarr;</a>
		</p>
	</div>
	<?php
} );

/* ── 1b. Explain why a visitor was bounced to the login screen ─────────────
 * The members-only gate below redirects guests here silently, which reads as a
 * glitch. The gate appends oyc_members_only=1, and this renders a short line
 * above the form saying what happened. */
add_filter( 'login_message', function ( $message ) {
	if ( empty( $_GET['oyc_members_only'] ) ) {
		return $message;
	}
	return $message . '<p class="login-gate-note">'
		. esc_html__( 'That page is for club members. Sign in below, or set up your login if you have not yet.', 'orienta-yacht-club' )
		. '</p>';
} );

// Show club name as page title above the form
add_filter( 'login_title', fn( $title ) => get_bloginfo( 'name' ) . ' — Member Login' );

// Never page-cache the login-setup page. It hosts the WP-Members registration
// form, which carries a security nonce and is per-request dynamic — a cached
// copy serves stale nonces (breaking submissions) and, as seen, can even cache
// a blank/form-less version. DONOTCACHEPAGE is honoured by WP-Optimize et al.
add_action( 'template_redirect', function () {
	if ( is_page( 'member-login-setup' ) ) {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
} );

/* ── Member Login Setup: extra OPTIONAL profile fields on registration ──────
 * WP-Members' register form only carries its own configured fields. Add the
 * remaining /edit-profile/ fields — Country, Display Name, About Me and
 * Emergency Contact (name/phone/relationship) — so signup collects the same
 * information, saving to the same meta the profile page reads. All optional.
 *
 * NOTE: relies on WP-Members' register-form-rows filter (version-specific);
 * verify on staging that the fields render AND save. */

function oyc_signup_extra_fields() {
	return array(
		array( 'meta' => 'billing_country',            'label' => 'Country',                        'type' => 'text' ),
		array( 'meta' => 'display_name',               'label' => 'Display Name',                   'type' => 'text' ),
		array( 'meta' => 'description',                'label' => 'About Me',                       'type' => 'textarea' ),
		array( 'meta' => 'oyc_emergency_name',         'label' => 'Emergency Contact Name',         'type' => 'text' ),
		array( 'meta' => 'oyc_emergency_phone',        'label' => 'Emergency Contact Phone',        'type' => 'tel' ),
		array( 'meta' => 'oyc_emergency_relationship', 'label' => 'Emergency Contact Relationship', 'type' => 'text' ),
	);
}

// Add the optional rows to the registration form (inserted just before the
// password fields so the account credentials stay last).
add_filter( 'wpmem_register_form_rows', function ( $rows, $tag ) {
	if ( 'register' !== $tag || ! is_array( $rows ) ) {
		return $rows;
	}
	$new = array();
	foreach ( oyc_signup_extra_fields() as $f ) {
		$meta = $f['meta'];
		$val  = isset( $_POST[ $meta ] ) ? wp_unslash( $_POST[ $meta ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- WP-Members owns the register nonce; value is escaped below for repopulation only.
		if ( 'textarea' === $f['type'] ) {
			$field = '<textarea name="' . esc_attr( $meta ) . '" id="' . esc_attr( $meta ) . '" class="textbox" rows="4">' . esc_textarea( $val ) . '</textarea>';
		} else {
			$field = '<input name="' . esc_attr( $meta ) . '" type="' . esc_attr( $f['type'] ) . '" id="' . esc_attr( $meta ) . '" value="' . esc_attr( $val ) . '" class="textbox" />';
		}
		$new[] = array(
			'meta'         => $meta,
			'type'         => $f['type'],
			'value'        => $val,
			'label_text'   => $f['label'],
			'row_before'   => '',
			'label'        => '<label for="' . esc_attr( $meta ) . '" class="text">' . esc_html( $f['label'] ) . '</label>',
			'field_before' => '<div class="div_text">',
			'field'        => $field,
			'field_after'  => '</div>',
			'row_after'    => '',
		);
	}
	// Find the first password row and insert our fields before it; else append.
	$at = count( $rows );
	foreach ( $rows as $i => $r ) {
		if ( isset( $r['meta'] ) && in_array( $r['meta'], array( 'password', 'confirm_password' ), true ) ) {
			$at = $i;
			break;
		}
	}
	array_splice( $rows, $at, 0, $new );
	return $rows;
}, 10, 2 );

// Save the optional fields on registration to the same targets edit-profile uses.
add_action( 'user_register', function ( $user_id ) {
	$submitted = false;
	foreach ( oyc_signup_extra_fields() as $f ) {
		if ( isset( $_POST[ $f['meta'] ] ) ) { $submitted = true; break; }
	}
	if ( ! $submitted ) {
		return; // Not our form.
	}
	$user_update = array( 'ID' => $user_id );
	foreach ( oyc_signup_extra_fields() as $f ) {
		if ( ! isset( $_POST[ $f['meta'] ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_POST[ $f['meta'] ] ); // phpcs:ignore WordPress.Security.NonceVerification -- WP-Members verified the register nonce before user_register fires.
		if ( 'description' === $f['meta'] ) {
			$user_update['description'] = sanitize_textarea_field( $raw );
		} elseif ( 'display_name' === $f['meta'] ) {
			$dn = sanitize_text_field( $raw );
			if ( '' !== $dn ) {
				$user_update['display_name'] = $dn;
			}
		} else {
			update_user_meta( $user_id, $f['meta'], sanitize_text_field( $raw ) );
		}
	}
	if ( count( $user_update ) > 1 ) {
		wp_update_user( $user_update );
	}
} );

/* ── 2. Redirect after login → member dashboard ──────────── */

add_filter( 'login_redirect', function ( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! $user || is_wp_error( $user ) ) {
		return $redirect_to;
	}

	// If the user was sent to the login screen FROM a specific page (e.g. a
	// members-only page like /photo-gallery/), return them to that page rather
	// than a generic landing screen. WordPress puts that page in
	// $requested_redirect_to; the bare wp-admin URL means "no specific page".
	$requested = is_string( $requested_redirect_to ) ? trim( $requested_redirect_to ) : '';
	$generic   = array_map(
		'untrailingslashit',
		array( admin_url(), admin_url( 'profile.php' ), admin_url( 'index.php' ) )
	);
	if ( '' !== $requested && ! in_array( untrailingslashit( $requested ), $generic, true ) ) {
		$safe = wp_validate_redirect( $requested, '' ); // keep it on-site
		if ( $safe ) {
			return $safe;
		}
	}

	// No specific page requested → default landing.
	if ( user_can( $user, 'manage_options' ) ) {
		return admin_url(); // admin dashboard (not profile.php)
	}
	return home_url( '/members-area/' );
}, 10, 3 );

/* ── 2b. Make the "Login" menu button remember the current page ──────────
 * The Login nav item points at wp-login.php with no redirect. Append the page
 * the visitor is currently on as redirect_to, so after login they land back
 * where they were (the login_redirect filter above then honors it). */
add_filter( 'nav_menu_link_attributes', function ( $atts, $item, $args ) {
	if ( is_user_logged_in() || empty( $atts['href'] ) ) {
		return $atts;
	}
	if ( false !== strpos( $atts['href'], 'wp-login.php' ) && false === strpos( $atts['href'], 'redirect_to=' ) ) {
		global $wp;
		$path    = isset( $wp->request ) && $wp->request !== '' ? trailingslashit( $wp->request ) : '';
		$current = home_url( $path );
		$atts['href'] = add_query_arg( 'redirect_to', rawurlencode( $current ), $atts['href'] );
	}
	return $atts;
}, 10, 3 );

/* ── 3. Members-only page restriction ────────────────────── */

add_action( 'template_redirect', function () {
	// Only check singular pages
	if ( ! is_singular( 'page' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	$slug    = get_post_field( 'post_name', $post_id );
	$always_members = array( // always members-only by slug
			'2026-fee-schedule',
			'slip-waiting-list',
			'dock-assignments',
			'constitution-and-bylaws-2026',
			'club-rental-agreement',
			'member-guidelines-2026',
			'member-guidelines',        // real content page (the -2026 slug 301-redirects here)
			'member-rental-agreement',  // real content page (club-rental-agreement 301-redirects here)
		);
	$is_members_only = in_array( $slug, $always_members, true ) || get_post_meta( $post_id, '_oyc_members_only', true );
	if ( ! $is_members_only ) {
		return;
	}
	// Guests: send to the login screen, with a note explaining why.
	if ( ! is_user_logged_in() ) {
		wp_redirect( add_query_arg( 'oyc_members_only', '1', wp_login_url( get_permalink() ) ) );
		exit;
	}
	// Signed in but not yet approved. Being logged in is not enough — a
	// self-registered account an officer has not approved must not reach
	// members-only content, or the approval step is decorative. Do NOT redirect
	// to wp-login.php here: they are already authenticated, so login would bounce
	// them straight back and loop. Stop with an explanation instead.
	if ( ! oyc_user_may_view_members_content() ) {
		wp_die(
			'<h1>' . esc_html__( 'Your login is awaiting approval', 'orienta-yacht-club' ) . '</h1>'
			. '<p>' . esc_html__( 'Thanks for signing up. A club officer still needs to confirm your membership before the members’ area opens up. You will get an email as soon as that is done.', 'orienta-yacht-club' ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Return to the Orienta Yacht Club home page', 'orienta-yacht-club' ) . '</a></p>',
			esc_html__( 'Awaiting approval', 'orienta-yacht-club' ),
			array( 'response' => 403 )
		);
	}
} );

/**
 * Cutover for moderated registration.
 *
 * Every account created BEFORE this moment predates self-registration and is
 * trusted without an activation record. Set it to when moderated registration
 * was switched on in Settings → WP-Members.
 */
if ( ! defined( 'OYC_MODERATION_START' ) ) {
	define( 'OYC_MODERATION_START', '2026-07-23 00:00:00' );
}

/**
 * Whether the current user is allowed to see members-only content.
 *
 * Logged in AND approved. Approval is WP-Members' `active` user meta, but this
 * deliberately does NOT delegate to wpmem_is_user_activated(): that treats a
 * MISSING value as "not activated" (its checks are `$var != 1`), so calling it
 * would lock every long-standing member out of the members' area the moment
 * moderated registration was switched on — none of them have an activation
 * record. Nor can a missing value simply be trusted, or a freshly registered
 * account would walk straight in if WP-Members happens not to stamp a 0.
 *
 * So: an explicit value decides it, and when there is no value at all the
 * registration date does — which is correct either way.
 *
 * @return bool
 */
function oyc_user_may_view_members_content() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	// Admins always get through.
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	$user_id = get_current_user_id();
	$active  = (string) get_user_meta( $user_id, 'active', true );

	if ( '1' === $active ) {
		return true;                      // approved by an officer
	}
	if ( '' !== $active ) {
		return false;                     // explicit 0 — pending or deactivated
	}

	// No activation record at all: trust it only if the account predates moderation.
	$user = get_userdata( $user_id );
	if ( ! $user || empty( $user->user_registered ) ) {
		return false;
	}
	return strtotime( $user->user_registered ) < strtotime( OYC_MODERATION_START );
}

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
	// Login screen: soften "Lost your password?" into a friendlier prompt (same
	// password-reset link underneath).
	if ( $original === 'Lost your password?' ) {
		return 'Having Trouble Logging in?';
	}
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

/* ── 8. Simplify the user Profile screen (profile.php) ───────────────────────
 *  Keep: Username, First/Last name, Nickname, Display name, Email, Account
 *  Management, and WP-Members custom fields. Hide everything else — Personal
 *  Options (syntax highlighting, color scheme, keyboard shortcuts, toolbar,
 *  language, visual editor), Website, About Yourself (bio + avatar), and
 *  Application Passwords. */

// Remove the Application Passwords section entirely.
add_filter( 'wp_is_application_passwords_available', '__return_false' );

// Drop the social/extra contact-method fields (Facebook, Instagram, LinkedIn,
// MySpace, Pinterest, SoundCloud, Tumblr, Wikipedia, X/Twitter, YouTube, etc.
// added by Yoast). Email stays (it's a core field, not a contact method).
add_filter( 'user_contactmethods', function () { return array(); }, 9999 );

add_action( 'admin_head-profile.php', function () {
	?>
	<style>
		/* Personal Options rows */
		.user-rich-editing-wrap,
		.user-syntax-highlighting-wrap,
		.user-admin-color-wrap,
		.user-comment-shortcuts-wrap,
		.user-admin-bar-front-wrap,
		.user-language-wrap,
		/* Contact Info: Website */
		.user-url-wrap,
		/* About Yourself: Biographical Info + Profile Picture */
		.user-description-wrap,
		.user-profile-picture { display: none !important; }
	</style>
	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		var hide = [ 'personal options', 'about yourself', 'application passwords', 'two factor authentication' ];
		Array.prototype.forEach.call( document.querySelectorAll( '.wrap h2, .wrap h3' ), function ( h ) {
			if ( hide.indexOf( h.textContent.trim().toLowerCase() ) === -1 ) { return; }
			h.style.display = 'none';
			var el = h.nextElementSibling;
			while ( el && el.tagName !== 'H2' && el.tagName !== 'H3' ) {
				el.style.display = 'none';
				el = el.nextElementSibling;
			}
		} );
	} );
	</script>
	<?php
} );

/* Rename WP-Members' "WP-Members Additional Fields" heading to
 *  "Member Contact Information" on the profile + edit-user screens. */
add_action( 'admin_head-profile.php', 'oyc_rename_wpmembers_heading' );
add_action( 'admin_head-user-edit.php', 'oyc_rename_wpmembers_heading' );
function oyc_rename_wpmembers_heading() {
	?>
	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.wrap h2, .wrap h3' ), function ( h ) {
			if ( h.textContent.trim().toLowerCase() === 'wp-members additional fields' ) {
				h.textContent = 'Member Contact Information';
			}
		} );
	} );
	</script>
	<?php
}

/* ── 9. Address + Emergency Contact fields on the user profile ───────────── */

function oyc_profile_extra_fields( $user ) {
	$v = function ( $key ) use ( $user ) {
		return esc_attr( get_user_meta( $user->ID, $key, true ) );
	};
	$row = function ( $id, $label ) use ( $v ) {
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>'
			. '<td><input type="text" name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" value="' . $v( $id ) . '" class="regular-text" /></td></tr>';
	};
	// Address is handled by WP-Members' own "Additional Fields" on this screen,
	// so the theme no longer renders a duplicate Address Information block here.
	?>
	<h2><?php esc_html_e( 'Emergency Contact Information', 'orienta-yacht-club' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		$row( 'oyc_emergency_name', __( 'Contact Name', 'orienta-yacht-club' ) );
		$row( 'oyc_emergency_phone', __( 'Contact Phone', 'orienta-yacht-club' ) );
		$row( 'oyc_emergency_relationship', __( 'Relationship', 'orienta-yacht-club' ) );
		?>
	</table>
	<?php
}
add_action( 'show_user_profile', 'oyc_profile_extra_fields' );
add_action( 'edit_user_profile', 'oyc_profile_extra_fields' );

function oyc_profile_extra_fields_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	// Address fields intentionally omitted here — WP-Members owns the address on
	// the wp-admin profile screen. Only the theme's emergency-contact fields save.
	$keys = array( 'oyc_emergency_name', 'oyc_emergency_phone', 'oyc_emergency_relationship' );
	foreach ( $keys as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_user_meta( $user_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
}
add_action( 'personal_options_update', 'oyc_profile_extra_fields_save' );
add_action( 'edit_user_profile_update', 'oyc_profile_extra_fields_save' );

/* ── 10. Keep non-admin members out of the WP admin dashboard ─────────────
 *  Admins are unaffected. Members get the front-end members area instead, but
 *  can still reach their own Profile via the front-end members area. */

// Hide the admin toolbar on the front end for non-admins.
add_filter( 'show_admin_bar', function ( $show ) {
	return current_user_can( 'manage_options' ) ? $show : false;
} );

// Redirect non-admins away from wp-admin entirely (AJAX still allowed).
add_action( 'admin_init', function () {
	if ( current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}
	wp_safe_redirect( home_url( '/members-area/' ) );
	exit;
} );

/* ── 11. Auth-aware primary menu CTAs ─────────────────────────────────────
 *  Logged out: Join + Login (as set in the menu).
 *  Logged in:  hide Join, turn "Login" into "My Account" (→ members area),
 *              and append a "Logout" item. */
add_filter( 'wp_nav_menu_objects', function ( $items, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $items;
	}
	$person_icon = '<svg class="cta-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ';

	if ( ! is_user_logged_in() ) {
		// Logged out: drop "Join", turn "Login" into "Reservations" → Dockwa
		// (new tab), then append a clear "Member Login" item with the person icon.
		$ref = null;
		$out = array();
		foreach ( $items as $item ) {
			$title = strtolower( trim( wp_strip_all_tags( $item->title ) ) );
			if ( 'join' === $title ) {
				continue; // drop Join to free up room on the menu line
			}
			if ( 'login' === $title ) {
				$item->title  = __( 'Reservations', 'orienta-yacht-club' );
				$item->url    = 'https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club?utm_campaign=marina_site_referral&utm_medium=web_badge&utm_source=3gcrvl-orienta-yacht-club&form=transient';
				$item->target = '_blank';
				$item->xfn    = 'noopener';
				$ref          = $item;
			}
			$out[] = $item;
		}
		if ( $ref ) {
			$login          = clone $ref;
			$login->ID      = 'oyc-member-login';
			$login->db_id   = 0;
			$login->title   = $person_icon . __( 'Member Login', 'orienta-yacht-club' );
			$login->url     = wp_login_url( home_url( '/members-area/' ) );
			$login->target  = '';
			$login->xfn     = '';
			$login->classes = array( 'menu-item', 'cta', 'cta--login' );
			$out[]          = $login;
		}
		return $out;
	}

	$out = array();
	foreach ( $items as $item ) {
		$title = strtolower( trim( wp_strip_all_tags( $item->title ) ) );

		if ( 'join' === $title ) {
			continue; // members don't need Join
		}

		if ( 'login' === $title ) {
			// Repurpose Login → My Account (with a person/user icon).
			$item->title   = $person_icon . __( 'My Account', 'orienta-yacht-club' );
			$item->url     = home_url( '/members-area/' );
			$item->classes = array( 'menu-item', 'cta', 'cta--login' );
			$out[]         = $item;

			// Append a Logout button.
			$logout          = clone $item;
			$logout->ID      = 'oyc-logout';
			$logout->db_id   = 0;
			$logout->title   = __( 'Logout', 'orienta-yacht-club' );
			$logout->url     = wp_logout_url( home_url( '/' ) );
			$logout->classes = array( 'menu-item', 'cta', 'cta--login', 'cta--logout' );
			$out[]           = $logout;
			continue;
		}

		$out[] = $item;
	}
	return $out;
}, 10, 2 );

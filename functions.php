<?php
/**
 * Orienta Yacht Club theme functions.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_VERSION', '1.7.75' );

// Strip any [TEST] prefix — runs at priority 9999 to catch anything added late.
add_filter( 'wp_mail', function ( $args ) {
	$args['subject'] = preg_replace( '/^\[TEST\]\s*/i', '', $args['subject'] );
	return $args;
}, 9999 );

// Also strip at the CF7 level before wp_mail is called.
add_filter( 'wpcf7_mail_components', function ( $components ) {
	if ( ! empty( $components['subject'] ) ) {
		$components['subject'] = preg_replace( '/^\[TEST\]\s*/i', '', $components['subject'] );
	}
	return $components;
}, 9999 );

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
		'https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,600;0,700;0,800;1,400;1,700&display=swap',
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
 * Contact-form spam protection: honeypot that flags bot submissions as spam
 * (blocks the email and keeps them out of the OYC Inbox).
 */
require_once get_template_directory() . '/inc/contact-spam.php';

/**
 * Tap-to-call: linkify plain phone numbers in content as tel: links.
 */
require_once get_template_directory() . '/inc/phone-links.php';

/**
 * Progressive Web App: manifest, service worker, and iOS meta tags.
 */
require_once get_template_directory() . '/inc/pwa.php';

/**
 * Dynamic iCal feed for the club calendar (live from Calendarize it!).
 */
require_once get_template_directory() . '/inc/calendar-feed.php';

/**
 * Calendar API: create/list/delete Calendarize it! events programmatically.
 */
require_once get_template_directory() . '/inc/calendar-api.php';

/**
 * Fleet Roster: members-only searchable directory (data stays in the DB).
 */
require_once get_template_directory() . '/inc/fleet-roster.php';

/**
 * Protected member documents: PII PDFs (slip waiting list, dock assignments)
 * moved to a deny-all folder and streamed only to logged-in members.
 */
require_once get_template_directory() . '/inc/protected-docs.php';

/**
 * Member Photo Gallery: members-only, self-serve front-end photo uploads
 * (held for admin approval before they appear).
 */
require_once get_template_directory() . '/inc/photo-gallery.php';

/**
 * Member Videos: members submit YouTube/Vimeo links (held for admin approval).
 */
require_once get_template_directory() . '/inc/video-gallery.php';

/**
 * SEO: staging noindex, LocalBusiness schema, per-page titles/descriptions,
 * and the /fishing/ → /boating/ redirect.
 */
require_once get_template_directory() . '/inc/seo.php';

/**
 * [oyc_harbor_mini] — compact live harbor-conditions widget linking to /weather/.
 */
require_once get_template_directory() . '/inc/harbor-mini.php';

/**
 * Inject Customizer-overridden section background images as inline CSS.
 * Only outputs rules when a Customizer image has been set (non-empty),
 * so the bundled CSS fallbacks stay in effect when nothing is configured.
 */
add_action( 'wp_head', function () {
	$rules = '';

	$sailing = oyc_get( 'oyc_img_sailing' );
	if ( $sailing ) {
		$rules .= '.section-photo-hero--sailing .section-photo-bg { background-image: url("' . esc_url( $sailing ) . '"); }';
	}

	$fishing = oyc_get( 'oyc_img_fishing' );
	if ( $fishing ) {
		$rules .= '.section-photo-hero--fishing .section-photo-bg { background-image: url("' . esc_url( $fishing ) . '"); }';
		$rules .= '@media (max-width:600px) { .section-photo-hero--fishing .section-photo-bg { background-image: url("' . esc_url( $fishing ) . '"); } }';
	}

	$visitors = oyc_get( 'oyc_img_visitors' );
	if ( $visitors ) {
		$rules .= '.section-photo-hero--visitors .section-photo-bg { background-image: url("' . esc_url( $visitors ) . '"); }';
		$rules .= '@media (max-width:600px) { .section-photo-hero--visitors .section-photo-bg { background-image: url("' . esc_url( $visitors ) . '"); } }';
	}

	$footer = oyc_get( 'oyc_img_footer' );
	if ( $footer ) {
		$rules .= '.site-footer { background-image: url("' . esc_url( $footer ) . '"); }';
	}

	if ( $rules ) {
		echo '<style id="oyc-customizer-images">' . $rules . '</style>';
	}
} );

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
 * Recipient for OYC Inbox notifications (new contact messages & applications).
 * Stored in the `oyc_notify_email` option (set in the database, not in code) so
 * no personal address lives in the theme source. Falls back to the site admin
 * email. Overridable via the `oyc_inbox_notify_email` filter.
 */
function oyc_inbox_email() {
	$email = get_option( 'oyc_notify_email', '' );
	if ( ! $email || ! is_email( $email ) ) {
		$email = get_option( 'admin_email' );
	}
	return apply_filters( 'oyc_inbox_notify_email', $email );
}

/**
 * Register the notification-email option so it can be read/updated via the
 * Settings REST endpoint (admin only).
 */
add_action( 'init', function () {
	register_setting( 'options', 'oyc_notify_email', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_email',
		'show_in_rest'      => true,
		'default'           => '',
	) );
} );

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
		$mimes['csv'] = 'text/csv';
	}
	return $mimes;
}
add_filter( 'upload_mimes', 'oyc_allow_svg_uploads' );

/**
 * Route ?inquiry= links to the contact form.
 *
 * If the contact form is on the current page (e.g. /contact/), pre-select its
 * "inquiry-type" dropdown from the ?inquiry= param. If the form is NOT on the
 * page (e.g. an old /?inquiry=membership#contact link landing on the homepage,
 * which no longer has a contact section), redirect to /contact/ carrying the
 * param so the dropdown pre-selects there. /contact/ itself is the fallback,
 * so the link always ends up on the contact page even if pre-select misses.
 */
add_action( 'wp_footer', function () {
	?>
	<script>
	(function(){
		var inquiry = new URLSearchParams(window.location.search).get('inquiry');
		if (!inquiry) return;
		var want = ({ 'membership': 'Contact Membership Chair' })[ inquiry ];
		var sel  = document.querySelector('select[name="inquiry-type"]');

		if (sel) {
			// Form is on this page — pre-select now and again after CF7 re-renders.
			function apply(){ var s = document.querySelector('select[name="inquiry-type"]'); if (s && want) { s.value = want; } }
			apply();
			document.addEventListener('wpcf7domloaded', apply);
			setTimeout(apply, 300);
			return;
		}

		// No contact form here — send the user to /contact/ (carrying the param),
		// unless we're already on /contact/ (avoid a redirect loop).
		if (!/\/contact\/?$/.test(window.location.pathname)) {
			window.location.replace('/contact/?inquiry=' + encodeURIComponent(inquiry));
		}
	})();
	</script>
	<?php
} );

// Allow admins to upload CSVs — the real-MIME check can otherwise reject them
// (a .csv is often sniffed as text/plain or application/vnd.ms-excel).
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( current_user_can( 'upload_files' ) && preg_match( '/\.csv$/i', $filename ) ) {
		$data['ext']  = 'csv';
		$data['type'] = 'text/csv';
	}
	return $data;
}, 10, 3 );

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
	// Portable per-page hook: a slug-based body class (e.g. oyc-page-mamaroneck-harbor)
	// so page-specific CSS survives moving between installs (numeric IDs differ per site).
	if ( is_page() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( $slug ) {
			$classes[] = 'oyc-page-' . sanitize_html_class( $slug );
		}
	}
	// Fully-immersive pages (fixed photo background): the menu stays transparent
	// the whole way down instead of turning solid on scroll.
	if ( is_front_page() || is_page( array( 'about', 'boating', 'fishing', 'visitors', 'membership', 'membership-application', 'calendar', 'contact', 'members-area', 'mamaroneck-harbor', 'thank-you-application', 'oyc-resources', 'videos', '2026-fee-schedule', 'edit-profile', 'storm-warnings', 'facilities', 'reciprocity-list', 'approach', 'fleet-roster', 'photo-gallery', 'video-streaming', 'live-video-streaming', 'slip-waiting-list', 'dock-assignments', 'constitution-and-bylaws-2026', 'club-rental-agreement', 'member-guidelines-2026', 'member-guidelines', 'member-rental-agreement', 'video-gallery', 'dock-and-dine' ) ) ) {
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
	$url     = home_url( '/videos/' );
	$gallery = function_exists( 'oyc_video_gallery_url' ) ? oyc_video_gallery_url() : home_url( '/video-gallery/' );

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
	// Member-submitted, approved videos → link to the member video gallery.
	if ( function_exists( 'oyc_video_gallery_items' ) ) {
		foreach ( oyc_video_gallery_items( 'approved', 8 ) as $mv ) {
			$thumb = oyc_video_thumb_url( $mv->ID );
			$title = $mv->post_title;
			echo '<a class="video-thumb" href="' . esc_url( $gallery ) . '" aria-label="' . esc_attr( $title ) . '">';
			if ( $thumb ) {
				echo '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" />';
			}
			echo '<span class="video-thumb__play" aria-hidden="true"></span>';
			echo '<span class="video-thumb__title">' . esc_html( $title ) . '</span>';
			echo '</a>';
		}
	}
	echo '</div>';
	echo '<p class="video-thumbs__more"><a href="' . esc_url( $url ) . '">' . esc_html__( 'View all videos →', 'orienta-yacht-club' ) . '</a> <a href="' . esc_url( $gallery ) . '">' . esc_html__( 'Add your own videos →', 'orienta-yacht-club' ) . '</a></p>';
	echo '</div>';
}

/**
 * Return a "mini thumbnail" of a page as a background-image span, generated by
 * WordPress.com's mShots screenshot service (a live preview of whatever the URL
 * renders). Used on the member dashboard so each quick-link shows a snapshot of
 * the page it goes to instead of a generic icon. The URL fragment is dropped so
 * mShots screenshots the page, not the anchor.
 *
 * @param string $url The page URL to screenshot.
 * @return string HTML for the thumbnail span.
 */
function oyc_dash_thumb( $url ) {
	$clean = strtok( $url, '#' );

	// Prefer a bundled screenshot (named <slug>.jpg in assets/dashthumbs/) when one
	// exists — used for members-only pages that the external mShots service can only
	// see as a login screen. Checked in the child theme first, then the parent.
	$path = (string) wp_parse_url( $clean, PHP_URL_PATH );
	$slug = $path ? basename( trim( $path, '/' ) ) : '';
	if ( $slug ) {
		$rel = '/assets/dashthumbs/' . $slug . '.jpg';
		$src = '';
		if ( file_exists( get_stylesheet_directory() . $rel ) ) {
			$src = get_stylesheet_directory_uri() . $rel;
		} elseif ( file_exists( get_template_directory() . $rel ) ) {
			$src = get_template_directory_uri() . $rel;
		}
		if ( $src ) {
			return sprintf(
				'<span class="dash-card__thumb" aria-hidden="true" style="background-image:url(%s)"></span>',
				esc_url( $src )
			);
		}
	}

	// Otherwise generate a live preview via WordPress.com's mShots service. The
	// `?v=N` appended to the target forces mShots to re-screenshot when a page's
	// design changes; bump it to refresh all generated thumbnails.
	$bust = ( false === strpos( $clean, '?' ) ? '?' : '&' ) . 'v=2';
	$shot = 'https://s.wordpress.com/mshots/v1/' . rawurlencode( $clean . $bust ) . '?w=480&h=300';
	return sprintf(
		'<span class="dash-card__thumb" aria-hidden="true" style="background-image:url(%s)"></span>',
		esc_url( $shot )
	);
}

/**
 * Return a placeholder document icon for member-document links that have no page
 * preview yet (e.g. "coming soon" pages). Mirrors the thumbnail markup so the
 * cards line up in the grid.
 */
function oyc_dash_icon() {
	$svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
	return '<span class="dash-card__thumb dash-card__thumb--icon" aria-hidden="true">' . $svg . '</span>';
}

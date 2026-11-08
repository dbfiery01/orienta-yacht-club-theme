<?php
/**
 * Public desktop navigation (logged-out visitors, screens wider than 880px).
 *
 * Six top-level items with short dropdowns, then the weather glyph, the
 * light/dark toggle and the Member Login / Reservations buttons on the right.
 * The WordPress "Primary" menu still drives the mobile hamburger and the
 * logged-in header; on desktop its text links are hidden for logged-out
 * visitors and only its weather / Member Login / Reservations items are kept
 * (see the "Public desktop navigation" block at the end of style.css).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Store and Dockwa links (external, open in a new tab). */
define( 'OYC_DESK_NAV_STORE_URL', 'https://www.team1newport.com/Orienta-Yacht-Club/products/1083/' );
define( 'OYC_DESK_NAV_DOCKWA_URL', 'https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club?utm_campaign=marina_site_referral&utm_medium=web_badge&utm_source=3gcrvl-orienta-yacht-club&form=transient' );

/**
 * Whether the public desktop menu is shown on this request.
 */
function oyc_desk_nav_enabled() {
	return ! is_user_logged_in();
}

/**
 * Menu structure: label => [ url, children[] ]. A child is [ label, url, external? ].
 */
function oyc_desk_nav_items() {
	return array(
		array(
			'label'    => __( 'About', 'orienta-yacht-club' ),
			'url'      => home_url( '/about/' ),
			'children' => array(
				array( __( 'About the Club', 'orienta-yacht-club' ), home_url( '/about/' ) ),
				array( __( 'Our History', 'orienta-yacht-club' ), home_url( '/our-history/' ) ),
				array( __( 'Officers & Trustees', 'orienta-yacht-club' ), home_url( '/officers-trustees/' ) ),
				array( __( 'Facilities', 'orienta-yacht-club' ), home_url( '/facilities/' ) ),
				array( __( 'Club Store', 'orienta-yacht-club' ), OYC_DESK_NAV_STORE_URL, true ),
			),
		),
		array(
			'label'    => __( 'Membership', 'orienta-yacht-club' ),
			'url'      => home_url( '/membership/' ),
			'children' => array(
				array( __( 'Why Join', 'orienta-yacht-club' ), home_url( '/membership/' ) ),
				array( __( 'Apply Now', 'orienta-yacht-club' ), home_url( '/membership-application/' ) ),
			),
		),
		array(
			'label'    => __( 'Boating', 'orienta-yacht-club' ),
			'url'      => home_url( '/boating/' ),
			'children' => array(
				array( __( 'Sailing & Angling', 'orienta-yacht-club' ), home_url( '/boating/' ) ),
				array( __( 'Racing', 'orienta-yacht-club' ), home_url( '/racing/' ) ),
				array( __( 'Social Events', 'orienta-yacht-club' ), home_url( '/social-events/' ) ),
			),
		),
		array(
			'label'    => __( 'Visitors', 'orienta-yacht-club' ),
			'url'      => home_url( '/visitors/' ),
			'children' => array(
				array( __( 'Visiting Boaters', 'orienta-yacht-club' ), home_url( '/visitors/' ) ),
				array( __( 'Harbor Guide', 'orienta-yacht-club' ), home_url( '/mamaroneck-harbor/' ) ),
				array( __( 'Reserve a Mooring', 'orienta-yacht-club' ), OYC_DESK_NAV_DOCKWA_URL, true ),
			),
		),
		array(
			'label'    => __( 'Calendar', 'orienta-yacht-club' ),
			'url'      => home_url( '/calendar/' ),
			'children' => array(),
		),
		array(
			'label'    => __( 'Contact', 'orienta-yacht-club' ),
			'url'      => home_url( '/contact/' ),
			'children' => array(),
		),
	);
}

/**
 * Path of a URL with a trailing slash, for matching the current page.
 */
function oyc_desk_nav_path( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	return trailingslashit( $path ? $path : '/' );
}

/**
 * Echo the desktop menu <ul>. Called from header.php inside .primary-nav.
 */
function oyc_render_desktop_nav() {
	if ( ! oyc_desk_nav_enabled() ) {
		return;
	}
	$current = oyc_desk_nav_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$ext_ico = '<svg class="oyc-desk-ext" viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 2h6v6M10 2 3 9"/></svg>';
	$caret   = '<svg class="oyc-desk-caret" viewBox="0 0 10 6" width="10" height="6" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>';

	echo '<ul class="oyc-desk-nav" aria-label="' . esc_attr__( 'Main', 'orienta-yacht-club' ) . '">';
	foreach ( oyc_desk_nav_items() as $i => $item ) {
		$has_kids = ! empty( $item['children'] );
		$active   = oyc_desk_nav_path( $item['url'] ) === $current;
		foreach ( $item['children'] as $kid ) {
			if ( empty( $kid[2] ) && oyc_desk_nav_path( $kid[1] ) === $current ) {
				$active = true;
			}
		}
		$cls = 'oyc-desk-item' . ( $has_kids ? ' has-dd' : '' ) . ( $active ? ' is-current' : '' );
		$dd  = 'oyc-desk-dd-' . $i;
		echo '<li class="' . esc_attr( $cls ) . '">';
		echo '<a class="oyc-desk-top" href="' . esc_url( $item['url'] ) . '"'
			. ( $active ? ' aria-current="page"' : '' )
			. ( $has_kids ? ' aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr( $dd ) . '"' : '' )
			. '>' . esc_html( $item['label'] ) . ( $has_kids ? $caret : '' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $has_kids ) {
			echo '<ul class="oyc-desk-dd" id="' . esc_attr( $dd ) . '">';
			foreach ( $item['children'] as $kid ) {
				$ext   = ! empty( $kid[2] );
				$k_cur = ! $ext && oyc_desk_nav_path( $kid[1] ) === $current;
				echo '<li><a href="' . esc_url( $kid[1] ) . '"'
					. ( $ext ? ' target="_blank" rel="noopener"' : '' )
					. ( $k_cur ? ' aria-current="page" class="is-current"' : '' )
					. '>' . esc_html( $kid[0] ) . ( $ext ? $ext_ico : '' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

/** Mark the nav so the desktop CSS only applies when the menu is rendered. */
function oyc_desk_nav_class() {
	return oyc_desk_nav_enabled() ? ' has-desk-nav' : '';
}

/**
 * Dropdown behavior: hover/focus opens via CSS; this adds tap-to-open for
 * touch screens (first tap opens, second follows the link), Escape to close,
 * and keeps aria-expanded in step.
 */
add_action( 'wp_footer', function () {
	if ( ! oyc_desk_nav_enabled() ) {
		return;
	}
	?>
	<script>(function(){var nav=document.querySelector('.oyc-desk-nav');if(!nav)return;
	var items=[].slice.call(nav.querySelectorAll('.oyc-desk-item.has-dd'));
	function setOpen(li,on){li.classList.toggle('is-open',on);var a=li.querySelector('.oyc-desk-top');if(a)a.setAttribute('aria-expanded',on?'true':'false');}
	function closeAll(except){items.forEach(function(li){if(li!==except)setOpen(li,false);});}
	items.forEach(function(li){var a=li.querySelector('.oyc-desk-top');
		li.addEventListener('pointerenter',function(e){if(e.pointerType==='mouse')setOpen(li,true);});
		li.addEventListener('pointerleave',function(e){if(e.pointerType==='mouse')setOpen(li,false);});
		li.addEventListener('focusin',function(e){if(e.target.matches(':focus-visible')){closeAll(li);setOpen(li,true);}});
		li.addEventListener('focusout',function(e){if(!li.contains(e.relatedTarget))setOpen(li,false);});
		a.addEventListener('click',function(e){var touch=window.matchMedia&&window.matchMedia('(hover: none)').matches;
			if(touch&&!li.classList.contains('is-open')){e.preventDefault();closeAll(li);setOpen(li,true);}});
	});
	document.addEventListener('click',function(e){if(!nav.contains(e.target))closeAll(null);});
	document.addEventListener('keydown',function(e){if(e.key==='Escape')closeAll(null);});
	})();</script>
	<?php
} );

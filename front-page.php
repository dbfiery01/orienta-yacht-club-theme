<?php
/**
 * Front page — single-page layout assembled from Customizer settings.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<section id="top" class="hero">
	<div class="hero-slides" aria-hidden="true">
		<div class="hero-slide hero-slide--active" style="background-image:url('<?php echo esc_url( get_template_directory_uri() . '/assets/photos/home-slide-a.jpg' ); ?>')"></div>
		<div class="hero-slide" style="background-image:url('<?php echo esc_url( get_template_directory_uri() . '/assets/photos/home-slide-b.jpg' ); ?>')"></div>
		<div class="hero-slide" style="background-image:url('<?php echo esc_url( get_template_directory_uri() . '/assets/photos/home-slide-c.jpg' ); ?>')"></div>
		<div class="hero-slide" style="background-image:url('<?php echo esc_url( get_template_directory_uri() . '/assets/photos/home-slide-d.jpg' ); ?>')"></div>
		<div class="hero-slide" style="background-image:url('<?php echo esc_url( get_template_directory_uri() . '/assets/photos/home-slide-e.jpg' ); ?>')"></div>
		<div class="hero-slide" style="background-image:url('<?php echo esc_url( get_template_directory_uri() . '/assets/photos/home-slide-f.jpg' ); ?>')"></div>
	</div>
	<div class="hero-overlay"></div>
	<div class="container hero-inner">
		<p class="eyebrow"><?php echo esc_html( oyc_get( 'oyc_hero_eyebrow' ) ); ?></p>
		<h1><?php echo esc_html( oyc_get( 'oyc_hero_headline' ) ); ?></h1>
		<p class="lede"><?php echo esc_html( oyc_get( 'oyc_hero_lede' ) ); ?></p>
		<div class="hero-cta">
			<?php if ( oyc_get( 'oyc_hero_cta1_text' ) ) : ?>
				<a class="btn btn-primary" href="<?php echo esc_url( oyc_get( 'oyc_hero_cta1_url' ) ); ?>">
					<?php echo esc_html( oyc_get( 'oyc_hero_cta1_text' ) ); ?>
				</a>
			<?php endif; ?>
			<?php if ( oyc_get( 'oyc_hero_cta2_text' ) ) : ?>
				<a class="btn btn-ghost" href="<?php echo esc_url( oyc_get( 'oyc_hero_cta2_url' ) ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( oyc_get( 'oyc_hero_cta2_text' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<div class="hero-scroll" aria-hidden="true"><span></span></div>
</section>

<script>
(function () {
	// Hero slideshow
	var slides = document.querySelectorAll( '.hero-slide' );
	if ( slides.length > 1 ) {
		var current = 0;
		setInterval( function () {
			slides[ current ].classList.remove( 'hero-slide--active' );
			current = ( current + 1 ) % slides.length;
			slides[ current ].classList.add( 'hero-slide--active' );
		}, 5000 );
	}

	var DOCKWA_URL = 'https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club';

	// Pre-select inquiry type based on ?inquiry= URL parameter
	var params = new URLSearchParams( window.location.search );
	var inquiryMap = {
		'membership': 'Contact Membership Chair'
	};
	var inquiryValue = inquiryMap[ params.get( 'inquiry' ) ];
	if ( inquiryValue ) {
		function preselectInquiry() {
			var sel = document.querySelector( 'select[name="inquiry-type"]' );
			if ( sel ) {
				sel.value = inquiryValue;
				var section = document.getElementById( 'contact' );
				if ( section ) { section.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
			} else {
				setTimeout( preselectInquiry, 100 );
			}
		}
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', preselectInquiry );
		} else {
			preselectInquiry();
		}
	}

	// Navigate to Dockwa when "Dock Reservations" is selected
	function bindReservationsRedirect() {
		var sel = document.querySelector( 'select[name="inquiry-type"]' );
		if ( sel ) {
			sel.addEventListener( 'change', function () {
				if ( this.value === 'Dock Reservations' ) {
					window.open( DOCKWA_URL, '_blank', 'noopener' );
					// Reset dropdown back to default so the form stays usable
					this.value = 'General Inquiry';
					// Pre-fill the message textarea
					var msg = document.querySelector( 'textarea[name="your-message"]' );
					if ( msg && ! msg.value.trim() ) {
						msg.value = 'Re: a Dockwa reservation\n\n';
						msg.focus();
					}
				}
			} );
		} else {
			setTimeout( bindReservationsRedirect, 100 );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bindReservationsRedirect );
	} else {
		bindReservationsRedirect();
	}

	// Phone auto-formatter for contact form tel field
	function formatPhone( raw ) {
		var d = raw.replace( /\D/g, '' ).slice( 0, 10 );
		if ( d.length < 4 ) return d;
		if ( d.length < 7 ) return '(' + d.slice(0,3) + ') ' + d.slice(3);
		return '(' + d.slice(0,3) + ') ' + d.slice(3,6) + '-' + d.slice(6);
	}
	function initContactPhoneFormat() {
		document.querySelectorAll( '.wpcf7-form input[type="tel"]' ).forEach( function( inp ) {
			if ( inp.dataset.pf ) return;
			inp.dataset.pf = '1';
			inp.setAttribute( 'inputmode', 'tel' );
			inp.addEventListener( 'input', function() {
				var pos = this.selectionStart, before = this.value.length;
				this.value = formatPhone( this.value );
				var d = this.value.length - before;
				this.setSelectionRange( pos + d, pos + d );
			} );
			inp.addEventListener( 'blur', function() {
				if ( this.value ) this.value = formatPhone( this.value );
			} );
		} );
	}
	document.addEventListener( 'wpcf7domloaded', initContactPhoneFormat );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initContactPhoneFormat );
	} else {
		initContactPhoneFormat();
	}
}());
</script>

<?php
get_footer();

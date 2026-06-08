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
				<a class="btn btn-ghost" href="<?php echo esc_url( oyc_get( 'oyc_hero_cta2_url' ) ); ?>">
					<?php echo esc_html( oyc_get( 'oyc_hero_cta2_text' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<div class="hero-scroll" aria-hidden="true"><span></span></div>
</section>

<section id="about" class="section">
	<div class="container two-col">
		<div>
			<p class="kicker"><?php echo esc_html( oyc_get( 'oyc_about_kicker' ) ); ?></p>
			<h2><?php echo esc_html( oyc_get( 'oyc_about_headline' ) ); ?></h2>
			<p><?php echo esc_html( oyc_get( 'oyc_about_p1' ) ); ?></p>
			<p><?php echo esc_html( oyc_get( 'oyc_about_p2' ) ); ?></p>
			<ul class="facts">
				<?php for ( $i = 1; $i <= 4; $i++ ) :
					$value = oyc_get( "oyc_fact{$i}_value" );
					$label = oyc_get( "oyc_fact{$i}_label" );
					if ( ! $value && ! $label ) { continue; }
				?>
					<li>
						<strong><?php echo esc_html( $value ); ?></strong>
						<span><?php echo esc_html( $label ); ?></span>
					</li>
				<?php endfor; ?>
			</ul>
		</div>
		<aside class="card-stack">
			<?php for ( $i = 1; $i <= 3; $i++ ) :
				$title = oyc_get( "oyc_card{$i}_title" );
				$body  = oyc_get( "oyc_card{$i}_body" );
				if ( ! $title && ! $body ) { continue; }
			?>
				<article class="tile tile--on-photo">
					<h3><?php echo esc_html( $title ); ?></h3>
					<p><?php echo esc_html( $body ); ?></p>
				</article>
			<?php endfor; ?>
		</aside>
	</div>
</section>

<section id="membership" class="section section-tinted">
	<div class="container">
		<p class="kicker"><?php echo esc_html( oyc_get( 'oyc_mem_kicker' ) ); ?></p>
		<h2><?php echo esc_html( oyc_get( 'oyc_mem_headline' ) ); ?></h2>
		<p class="section-lede"><?php echo esc_html( oyc_get( 'oyc_mem_lede' ) ); ?></p>
		<div class="grid-2">
			<article class="tile tile--on-photo">
				<h3><?php esc_html_e( 'Membership Types', 'orienta-yacht-club' ); ?></h3>
				<ul class="membership-types">
					<li><strong>Regular</strong> &mdash; Full privileges and voting rights</li>
					<li><strong>Jr</strong> &mdash; Full privileges and no voting rights</li>
					<li><strong>Social</strong> &mdash; Events, Clubhouse privileges and no voting rights</li>
				</ul>
			</article>
			<article class="tile tile--on-photo">
				<h3><?php esc_html_e( 'Fees Schedule', 'orienta-yacht-club' ); ?></h3>
				<table class="fees-table">
					<thead>
						<tr><th><?php esc_html_e( 'Category', 'orienta-yacht-club' ); ?></th><th><?php esc_html_e( 'Annual Dues', 'orienta-yacht-club' ); ?></th><th><?php esc_html_e( 'Initiation Fee', 'orienta-yacht-club' ); ?></th></tr>
					</thead>
					<tbody>
						<tr><td>Regular</td><td>$1,750</td><td>$3,500</td></tr>
						<tr><td>Jr</td><td>$50</td><td>$0</td></tr>
						<tr><td>Social</td><td>$100</td><td>$0</td></tr>
					</tbody>
				</table>
				<p class="fees-more"><a href="<?php echo esc_url( home_url( '/2026-fee-schedule/' ) ); ?>"><?php esc_html_e( 'Complete fee schedule', 'orienta-yacht-club' ); ?></a></p>
			</article>
		</div>
		<div class="cta-row">
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/membership-application/' ) ); ?>"><?php esc_html_e( 'Apply Now', 'orienta-yacht-club' ); ?></a>
			<a class="btn btn-link" href="<?php echo esc_url( home_url( '/?inquiry=membership#contact' ) ); ?>"><?php esc_html_e( 'Speak with the Membership Chair →', 'orienta-yacht-club' ); ?></a>
		</div>
		<?php oyc_video_thumbs(); ?>
	</div>
</section>

<section id="sailing" class="section section-photo-hero">
	<div class="section-photo-bg" aria-hidden="true"></div>
	<div class="section-photo-overlay" aria-hidden="true"></div>
	<div class="container section-photo-inner">
		<p class="kicker on-dark"><?php echo esc_html( oyc_get( 'oyc_sail_kicker' ) ); ?></p>
		<h2><?php echo esc_html( oyc_get( 'oyc_sail_headline' ) ); ?></h2>
		<p><?php echo wp_kses_post( oyc_get( 'oyc_sail_body' ) ); ?></p>
		<?php oyc_render_bullets( oyc_get( 'oyc_sail_bullets' ) ); ?>
		<p class="sail-docs-link"><a href="<?php echo esc_url( home_url( '/sailing-instructions/' ) ); ?>"><?php esc_html_e( 'Sailing Instructions', 'orienta-yacht-club' ); ?> &rarr;</a></p>
	</div>
</section>

<section id="fishing" class="section section-photo-hero section-photo-hero--fishing">
	<div class="section-photo-bg" aria-hidden="true"></div>
	<div class="section-photo-overlay" aria-hidden="true"></div>
	<div class="container section-photo-inner">
		<p class="kicker on-dark"><?php echo esc_html( oyc_get( 'oyc_fish_kicker' ) ); ?></p>
		<h2><?php echo esc_html( oyc_get( 'oyc_fish_headline' ) ); ?></h2>
		<p><?php echo wp_kses_post( oyc_get( 'oyc_fish_body' ) ); ?></p>
		<?php oyc_render_bullets( oyc_get( 'oyc_fish_bullets' ) ); ?>
	</div>
</section>

<section id="visitors" class="section section-photo-hero section-photo-hero--visitors">
	<div class="section-photo-bg" aria-hidden="true"></div>
	<div class="section-photo-overlay" aria-hidden="true"></div>
	<div class="container section-photo-inner section-photo-inner--wide">
		<p class="kicker on-dark"><?php echo esc_html( oyc_get( 'oyc_vis_kicker' ) ); ?></p>
		<h2><?php echo esc_html( oyc_get( 'oyc_vis_headline' ) ); ?></h2>
		<p class="section-lede"><?php echo esc_html( oyc_get( 'oyc_vis_lede' ) ); ?></p>
		<div class="grid-3">
			<?php for ( $i = 1; $i <= 3; $i++ ) :
				$title = oyc_get( "oyc_vis_tile{$i}_title" );
				$body  = oyc_get( "oyc_vis_tile{$i}_body" );
				if ( ! $title && ! $body ) { continue; }
			?>
				<article class="tile tile--on-photo">
					<h3>
						<?php if ( $i === 1 ) : ?>
							<a href="<?php echo esc_url( home_url( '/approach/' ) ); ?>" class="tile-heading-link">
								<?php echo esc_html( $title ); ?> <span class="tile-link-icon" aria-hidden="true">↗</span>
							</a>
						<?php elseif ( $i === 2 ) : ?>
							<a href="https://dockwa.com/explore/destination/3gcrvl-orienta-yacht-club?form=transient" target="_blank" rel="noopener" class="tile-heading-link">
								<?php echo esc_html( $title ); ?> <span class="tile-link-icon" aria-hidden="true">↗</span>
							</a>
						<?php elseif ( $i === 3 ) : ?>
							<a href="<?php echo esc_url( home_url( '/mamaroneck-harbor/' ) ); ?>" class="tile-heading-link">
								<?php echo esc_html( $title ); ?> <span class="tile-link-icon" aria-hidden="true">↗</span>
							</a>
						<?php else : ?>
							<?php echo esc_html( $title ); ?>
						<?php endif; ?>
					</h3>
					<p><?php echo esc_html( $body ); ?></p>
				</article>
			<?php endfor; ?>
		</div>
	</div>
</section>

<!-- Contact photo banner -->
<div class="contact-photo-header" id="contact">
	<div class="contact-photo-bg" aria-hidden="true"></div>
	<div class="contact-photo-overlay" aria-hidden="true"></div>
	<div class="container contact-photo-inner">
		<p class="kicker on-dark"><?php echo esc_html( oyc_get( 'oyc_con_kicker' ) ); ?></p>
		<h2><?php echo esc_html( oyc_get( 'oyc_con_headline' ) ); ?></h2>
	</div>
</div>

<section class="section section-tinted contact-body">
	<div class="container two-col">
		<div>
			<p><?php echo esc_html( oyc_get( 'oyc_con_body' ) ); ?></p>
			<dl class="contact-list">
				<?php if ( oyc_get( 'oyc_con_address' ) ) : ?>
					<dt><?php esc_html_e( 'Address', 'orienta-yacht-club' ); ?></dt>
					<dd><?php echo nl2br( esc_html( oyc_get( 'oyc_con_address' ) ) ); ?></dd>
				<?php endif; ?>
				<?php if ( oyc_get( 'oyc_con_phone' ) ) : ?>
					<dt><?php esc_html_e( 'Office', 'orienta-yacht-club' ); ?></dt>
					<dd><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', oyc_get( 'oyc_con_phone' ) ) ); ?>"><?php echo esc_html( oyc_get( 'oyc_con_phone' ) ); ?></a></dd>
				<?php endif; ?>
				<?php if ( oyc_get( 'oyc_con_email' ) ) : ?>
					<dt><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?></dt>
					<dd><a href="mailto:<?php echo esc_attr( antispambot( oyc_get( 'oyc_con_email' ) ) ); ?>"><?php echo esc_html( antispambot( oyc_get( 'oyc_con_email' ) ) ); ?></a></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Radio', 'orienta-yacht-club' ); ?></dt>
				<dd><?php esc_html_e( 'VHF Channel 68', 'orienta-yacht-club' ); ?></dd>
				<dt class="contact-list__section"><?php esc_html_e( 'Emergency Services', 'orienta-yacht-club' ); ?></dt>
				<dd></dd>
				<dt><?php esc_html_e( 'Harbor Patrol', 'orienta-yacht-club' ); ?></dt>
				<dd>
					<?php esc_html_e( 'Radio: VHF Channel 16', 'orienta-yacht-club' ); ?><br>
					<a href="tel:+19147777764">(914) 777-7764</a>
				</dd>
			</dl>
		</div>
		<div class="contact-form">
			<?php echo do_shortcode( '[contact-form-7 id="39" title="Contact Orienta Yacht Club"]' ); ?>
		</div>
	</div>
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

<?php
/**
 * Template Name: Application Thank You
 * Shown after a membership application is successfully submitted.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<div class="page-hero page-hero--apply">
	<div class="container">
		<p class="kicker on-dark"><?php esc_html_e( 'Application Received', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Thank You for Applying', 'orienta-yacht-club' ); ?></h1>
		<p class="page-hero-sub"><?php esc_html_e( 'We look forward to welcoming you to Orienta Yacht Club.', 'orienta-yacht-club' ); ?></p>
	</div>
</div>

<section class="section section-tinted">
	<div class="container">
		<div class="thankyou-card">

			<div class="thankyou-icon" aria-hidden="true">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
			</div>

			<h2><?php esc_html_e( 'Your application has been received.', 'orienta-yacht-club' ); ?></h2>

			<p><?php esc_html_e( 'A confirmation has been sent to the email address you provided. A representative from the Membership Committee will be in touch with you shortly to discuss next steps.', 'orienta-yacht-club' ); ?></p>

			<div class="thankyou-steps">
				<h3><?php esc_html_e( "What happens next", 'orienta-yacht-club' ); ?></h3>
				<ol class="thankyou-steps-list">
					<li>
						<span class="thankyou-step-num">1</span>
						<div>
							<strong><?php esc_html_e( 'Membership Committee Review', 'orienta-yacht-club' ); ?></strong>
							<p><?php esc_html_e( 'Your application will be reviewed by the Membership Chair, who will reach out to arrange a visit to the club.', 'orienta-yacht-club' ); ?></p>
						</div>
					</li>
					<li>
						<span class="thankyou-step-num">2</span>
						<div>
							<strong><?php esc_html_e( 'Visit the Club', 'orienta-yacht-club' ); ?></strong>
							<p><?php esc_html_e( 'We\'ll invite you for a tour of the facilities and an opportunity to meet members.', 'orienta-yacht-club' ); ?></p>
						</div>
					</li>
					<li>
						<span class="thankyou-step-num">3</span>
						<div>
							<strong><?php esc_html_e( 'Sponsorship & Board Approval', 'orienta-yacht-club' ); ?></strong>
							<p><?php esc_html_e( 'Two current members will sponsor your application, which is then presented at the next monthly Board meeting.', 'orienta-yacht-club' ); ?></p>
						</div>
					</li>
					<li>
						<span class="thankyou-step-num">4</span>
						<div>
							<strong><?php esc_html_e( 'Welcome to OYC!', 'orienta-yacht-club' ); ?></strong>
							<p><?php esc_html_e( 'Once approved, you\'ll receive your membership materials and be ready to enjoy everything the club has to offer.', 'orienta-yacht-club' ); ?></p>
						</div>
					</li>
				</ol>
			</div>

			<div class="thankyou-contact">
				<p><?php esc_html_e( 'Questions? Contact us at any time:', 'orienta-yacht-club' ); ?></p>
				<div class="thankyou-contact-row">
					<a href="tel:+19146989858" class="thankyou-contact-item">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
						(914) 698-9858
					</a>
					<a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>" class="thankyou-contact-item">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						<?php esc_html_e( 'Send a message', 'orienta-yacht-club' ); ?>
					</a>
				</div>
			</div>

			<div class="thankyou-actions">
				<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php esc_html_e( 'Return to Home', 'orienta-yacht-club' ); ?>
				</a>
				<a class="btn btn-ghost-navy" href="<?php echo esc_url( home_url( '/membership-info/' ) ); ?>">
					<?php esc_html_e( 'Learn More About Membership', 'orienta-yacht-club' ); ?>
				</a>
			</div>

		</div>
	</div>
</section>

<?php get_footer(); ?>

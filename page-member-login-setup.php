<?php
/**
 * Template Name: Member Login Setup
 *
 * Account setup for people who are ALREADY members of the club but have no
 * website login yet. This is deliberately not the membership application —
 * prospective members are sent to /membership-application/ instead.
 *
 * The form itself is WP-Members' own registration form (the plugin is active
 * and already owns the address fields on the profile screen), so registration,
 * validation and the approval queue are the plugin's, not the theme's.
 *
 * Accounts are held until an officer approves them — see the moderated
 * registration setting in Settings → WP-Members.
 *
 * Auto-renders for a Page with slug "member-login-setup" (page-{slug} hierarchy).
 *
 * @package Orienta_Yacht_Club
 */

// Already signed in? There's nothing to set up — unless this is the email
// confirmation link, whose result must still be shown (an officer testing a
// signup is usually signed in).
$oyc_confirm = function_exists( 'oyc_signup_confirmation_state' ) ? oyc_signup_confirmation_state() : '';
if ( is_user_logged_in() && '' === $oyc_confirm ) {
	wp_safe_redirect( home_url( '/members-area/' ) );
	exit;
}

get_header();
?>

<div class="page-hero">
	<div class="container">
		<h1 class="page-hero-title"><?php esc_html_e( 'Set Up Your Member Login', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section">
	<div class="container page-content signup-layout">

		<div class="signup-intro">
			<p class="signup-lede">
				<?php esc_html_e( 'Already a member of Orienta Yacht Club? Create your website login here to reach the members’ area — the fee schedule, dock assignments, club documents and the member roster.', 'orienta-yacht-club' ); ?>
			</p>
			<p class="signup-note">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
				<span><?php esc_html_e( 'Requests are checked against the club roster, so your login will not work right away. You will get an email once it is approved — usually within a few days.', 'orienta-yacht-club' ); ?></span>
			</p>
		</div>

		<div class="signup-form">
			<?php
			if ( 'confirmed' === $oyc_confirm ) {
				// Arrived from the "confirm your email" link and WP-Members accepted it.
				?>
				<div class="profile-notice profile-notice--success" role="status">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
					<span><?php esc_html_e( 'Your email address is confirmed. The club secretary needs to confirm your account. After a few days your account will be active.', 'orienta-yacht-club' ); ?></span>
				</div>
				<?php
			} elseif ( 'invalid' === $oyc_confirm ) {
				?>
				<div class="profile-notice profile-notice--error" role="alert">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					<span><?php esc_html_e( 'That confirmation link is not valid or has expired. Please contact the club office and we will confirm your email for you.', 'orienta-yacht-club' ); ?></span>
				</div>
				<?php
			} elseif ( shortcode_exists( 'wpmem_form' ) ) {
				$oyc_reg_form = do_shortcode( '[wpmem_form register]' );
				// Inject the optional profile fields (Country, Display Name, About Me,
				// Emergency Contact) just before the Register button, so signup collects
				// the same information as /edit-profile/.
				// Only the register form's button: after a signup WP-Members also renders
				// its login form, which has the same button_div and must not get these.
				$oyc_reg_at = preg_match( '/id=["\']wpmem_reg["\']/', $oyc_reg_form, $oyc_m, PREG_OFFSET_CAPTURE ) ? $oyc_m[0][1] : false;
				$oyc_btn_at = false !== $oyc_reg_at ? strpos( $oyc_reg_form, '<div class="button_div">', $oyc_reg_at ) : false;
				if ( function_exists( 'oyc_signup_extra_fields_html' ) && false !== $oyc_btn_at ) {
					$oyc_reg_form = substr_replace( $oyc_reg_form, oyc_signup_extra_fields_html(), $oyc_btn_at, 0 );
				}
				// Replace WP-Members' default success message (which wrongly tells the
				// member to log in with an emailed password) with the moderated-approval
				// flow: the account is pending until an officer approves it.
				$oyc_reg_form = preg_replace(
					'/Congratulations!.*?emailed to you\./s',
					esc_html__( 'Your registration has been received. Once your account is approved — usually within a few days — you can log in with the password that was emailed to you.', 'orienta-yacht-club' ),
					$oyc_reg_form
				);
				echo $oyc_reg_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP-Members markup + individually-escaped fields.
			} else {
				// WP-Members deactivated — don't render a broken shortcode string.
				?>
				<div class="profile-notice profile-notice--error" role="alert">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					<?php esc_html_e( 'Account signup is temporarily unavailable. Please contact the club office and we will set your login up for you.', 'orienta-yacht-club' ); ?>
				</div>
				<?php
			}
			?>
		</div>

		<div class="signup-alt">
			<h2 class="signup-alt-title"><?php esc_html_e( 'Not a member yet?', 'orienta-yacht-club' ); ?></h2>
			<p><?php esc_html_e( 'This page is only for setting up a login. If you would like to join the club, start with a membership application.', 'orienta-yacht-club' ); ?></p>
			<a class="btn btn-ghost-navy" href="<?php echo esc_url( home_url( '/membership-application/' ) ); ?>">
				<?php esc_html_e( 'Apply for Membership', 'orienta-yacht-club' ); ?>
			</a>
		</div>

		<p class="signup-back">
			<?php esc_html_e( 'Already have a login?', 'orienta-yacht-club' ); ?>
			<a href="<?php echo esc_url( wp_login_url( home_url( '/members-area/' ) ) ); ?>"><?php esc_html_e( 'Sign in', 'orienta-yacht-club' ); ?></a>
		</p>

	</div>
</section>

<?php get_footer(); ?>

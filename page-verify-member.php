<?php
/**
 * Template Name: Verify Membership
 * Public page club staff reach by scanning a member's card QR code. Shows a
 * clear VALID / NOT VALID result with the member's name, number and type only.
 * Served at /verify-member/ with or without a Page record (inc/member-card.php).
 *
 * @package Orienta_Yacht_Club
 */

$oyc_code   = isset( $_GET['c'] ) ? sanitize_text_field( wp_unslash( $_GET['c'] ) ) : '';
$oyc_lookup = '' !== $oyc_code ? oyc_card_lookup( $oyc_code ) : array( 'user' => null, 'reason' => 'none' );
$oyc_user   = $oyc_lookup['user'];
$oyc_card   = $oyc_user ? oyc_card_data( $oyc_user ) : null;
$oyc_status = $oyc_user ? oyc_card_status( $oyc_user ) : array( 'valid' => false, 'reason' => $oyc_lookup['reason'] );
$oyc_valid  = $oyc_status['valid'];

$oyc_reasons = array(
	'none'       => __( 'Scan the QR code on a member\'s digital card to check it.', 'orienta-yacht-club' ),
	'unreadable' => __( 'This code is not an Orienta Yacht Club card.', 'orienta-yacht-club' ),
	'unknown'    => __( 'No member matches this card.', 'orienta-yacht-club' ),
	'reissued'   => __( 'This card has been replaced. Ask the member to open the current card on the website.', 'orienta-yacht-club' ),
	'not_member' => __( 'This account is not a confirmed membership.', 'orienta-yacht-club' ),
	/* translators: %s: year. */
	'expired'    => $oyc_card ? sprintf( __( 'Dues are recorded as paid through %s.', 'orienta-yacht-club' ), $oyc_card['through'] ) : '',
);

get_header();
?>

<div class="page-hero page-hero--card">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Club staff', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Verify Membership', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section mcard-section">
	<div class="container mcard-wrap">

		<?php if ( 'none' === $oyc_lookup['reason'] ) : ?>

			<div class="mverify mverify--idle">
				<p class="mverify__result"><?php esc_html_e( 'Ready to scan', 'orienta-yacht-club' ); ?></p>
				<p class="mverify__reason"><?php echo esc_html( $oyc_reasons['none'] ); ?></p>
				<p class="mverify__reason"><?php esc_html_e( 'Use the phone\'s camera app: point it at the code and tap the link that appears.', 'orienta-yacht-club' ); ?></p>
			</div>

		<?php else : ?>

			<div class="mverify <?php echo $oyc_valid ? 'mverify--ok' : 'mverify--bad'; ?>" role="status">
				<div class="mverify__icon" aria-hidden="true">
					<?php if ( $oyc_valid ) : ?>
						<svg viewBox="0 0 48 48" width="72" height="72"><circle cx="24" cy="24" r="22" fill="none" stroke="currentColor" stroke-width="4"/><path d="M14 25l7 7 13-15" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<?php else : ?>
						<svg viewBox="0 0 48 48" width="72" height="72"><circle cx="24" cy="24" r="22" fill="none" stroke="currentColor" stroke-width="4"/><path d="M16 16l16 16M32 16L16 32" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/></svg>
					<?php endif; ?>
				</div>
				<p class="mverify__result"><?php echo $oyc_valid ? esc_html__( 'Valid member', 'orienta-yacht-club' ) : esc_html__( 'Not valid', 'orienta-yacht-club' ); ?></p>

				<?php if ( $oyc_card ) : ?>
					<p class="mverify__name"><?php echo esc_html( $oyc_card['name'] ); ?></p>
					<dl class="mverify__facts">
						<div>
							<dt><?php esc_html_e( 'Member No.', 'orienta-yacht-club' ); ?></dt>
							<dd><?php echo $oyc_card['number'] ? esc_html( $oyc_card['number'] ) : esc_html__( 'Not on file', 'orienta-yacht-club' ); ?></dd>
						</div>
						<?php if ( $oyc_card['type'] ) : ?>
							<div>
								<dt><?php esc_html_e( 'Membership', 'orienta-yacht-club' ); ?></dt>
								<dd><?php echo esc_html( $oyc_card['type'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( $oyc_card['through'] ) : ?>
							<div>
								<dt><?php esc_html_e( 'Dues Paid Through', 'orienta-yacht-club' ); ?></dt>
								<dd><?php echo esc_html( $oyc_card['through'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( $oyc_card['since'] ) : ?>
							<div>
								<dt><?php esc_html_e( 'Member Since', 'orienta-yacht-club' ); ?></dt>
								<dd><?php echo esc_html( $oyc_card['since'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				<?php endif; ?>

				<?php if ( ! $oyc_valid && ! empty( $oyc_reasons[ $oyc_status['reason'] ] ) ) : ?>
					<p class="mverify__reason"><?php echo esc_html( $oyc_reasons[ $oyc_status['reason'] ] ); ?></p>
				<?php endif; ?>

				<p class="mverify__checked">
					<?php esc_html_e( 'Checked live with the club website', 'orienta-yacht-club' ); ?>
					· <time data-oyc-clock></time>
				</p>
			</div>

			<p class="mcard-help"><?php esc_html_e( 'A screenshot or photo of this page proves nothing. Scan the member\'s own phone each time.', 'orienta-yacht-club' ); ?></p>

		<?php endif; ?>

	</div>
</section>

<?php
get_footer();

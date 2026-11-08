<?php
/**
 * Template Name: Membership Card
 * The logged-in member's digital membership card with a QR code that club
 * staff scan to verify. Served at /member-card/ with or without a Page record
 * (inc/member-card.php). Officers can preview a member's card with ?uid=.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( oyc_card_url() ) );
	exit;
}

$oyc_viewer  = wp_get_current_user();
$oyc_card_u  = $oyc_viewer;
$oyc_preview = false;

if ( isset( $_GET['uid'] ) && current_user_can( 'oyc_manage_members' ) ) {
	require_once get_template_directory() . '/inc/officer-admin.php';
	$oyc_uid = (int) $_GET['uid'];
	if ( $oyc_uid && $oyc_uid !== $oyc_viewer->ID && oyc_officer_can_manage_user( $oyc_uid ) ) {
		$oyc_target = get_userdata( $oyc_uid );
		if ( $oyc_target ) {
			$oyc_card_u  = $oyc_target;
			$oyc_preview = true;
		}
	}
}

$oyc_allowed = $oyc_preview || oyc_user_may_view_members_content();
$oyc_card    = oyc_card_data( $oyc_card_u );
$oyc_status  = oyc_card_status( $oyc_card_u );
$oyc_year    = (int) current_time( 'Y' );
$oyc_season  = $oyc_card['through'] ? $oyc_card['through'] : '';

get_header();
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php echo $oyc_preview ? esc_html__( 'Officer preview', 'orienta-yacht-club' ) : esc_html__( 'Members only', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Membership Card', 'orienta-yacht-club' ); ?></h1>
		<p class="page-hero-sub"><?php esc_html_e( 'Show it at the club. Tap the card to flip it to the QR code staff scan.', 'orienta-yacht-club' ); ?></p>
	</div>
</div>

<section class="section mcard-section">
	<div class="container page-content mcard-page">

		<?php if ( ! $oyc_allowed ) : ?>

			<div class="mcard-note mcard-note--warn">
				<p><?php esc_html_e( 'Your card will appear here once the club has confirmed your membership.', 'orienta-yacht-club' ); ?></p>
			</div>

		<?php else : ?>
			<div class="mcard-layout">
			<div class="mcard-stage">

			<?php if ( $oyc_preview ) : ?>
				<div class="mcard-note">
					<p><?php printf( esc_html__( 'You are viewing %s\'s card. The QR code below works for staff exactly as it does on their phone.', 'orienta-yacht-club' ), esc_html( $oyc_card['name'] ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $oyc_status['valid'] ) : ?>
				<div class="mcard-note mcard-note--warn">
					<p>
						<?php
						echo 'expired' === $oyc_status['reason']
							/* translators: %s: year dues were last paid through. */
							? esc_html( sprintf( __( 'Dues are recorded as paid through %s, so this card reads NOT VALID when scanned. If they are paid, ask the club treasurer to update the website.', 'orienta-yacht-club' ), $oyc_card['through'] ) )
							: esc_html__( 'This account is not a confirmed membership, so the card will read NOT VALID when scanned.', 'orienta-yacht-club' );
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php $oyc_badges = oyc_card_get_badges( $oyc_card_u->ID ); $oyc_badge_names = oyc_card_badge_list(); $oyc_certs = oyc_card_get_certs( $oyc_card_u->ID ); ?>

			<div class="mcard-flip" data-oyc-flip>
				<button type="button" class="mcard-flip__inner <?php echo $oyc_status['valid'] ? '' : 'mcard--invalid'; ?>"
				        aria-label="<?php esc_attr_e( 'Membership card. Tap to flip between the front and the QR code on the back.', 'orienta-yacht-club' ); ?>" aria-pressed="false">

					<!-- FRONT -->
					<span class="mcard mcard--front<?php echo OYC_CARD_NOT_LIVE ? ' has-notlive' : ''; ?>">
						<span class="mcard__shine" aria-hidden="true"></span>
						<?php echo oyc_card_not_live_band(); // phpcs:ignore -- escaped in helper ?>
						<span class="mcard__row">
							<img class="mcard__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/oyc-logo-dark.png' ); ?>"
							     alt="<?php esc_attr_e( 'Orienta Yacht Club', 'orienta-yacht-club' ); ?>" loading="eager" decoding="async" />
							<?php if ( $oyc_season ) : ?>
								<span class="mcard__season"><?php echo esc_html( $oyc_season ); ?></span>
							<?php endif; ?>
						</span>
						<span class="mcard__club-sub"><?php esc_html_e( 'Membership Card', 'orienta-yacht-club' ); ?></span>

						<?php if ( $oyc_badges ) : ?>
							<span class="mcard__badges">
								<?php foreach ( $oyc_badges as $b ) : ?>
									<span class="mcard__badge mcard__badge--<?php echo esc_attr( $b ); ?>" title="<?php echo esc_attr( $oyc_badge_names[ $b ] ); ?>">
										<span class="mcard__badge-icon"><?php echo oyc_card_badge_svg( $b ); // phpcs:ignore -- static SVG ?></span>
										<span class="mcard__badge-label"><?php echo esc_html( $oyc_badge_names[ $b ] ); ?></span>
									</span>
								<?php endforeach; ?>
							</span>
						<?php endif; ?>

						<span class="mcard__name"><?php echo esc_html( $oyc_card['name'] ); ?></span>
						<span class="mcard__facts">
							<span><span class="mcard__k"><?php esc_html_e( 'Member No.', 'orienta-yacht-club' ); ?></span>
								<span class="mcard__v mcard__number"><?php echo $oyc_card['number'] ? esc_html( $oyc_card['number'] ) : '<span class="mcard__pending">' . esc_html__( 'Pending', 'orienta-yacht-club' ) . '</span>'; ?></span></span>
							<?php if ( $oyc_card['type'] ) : ?>
								<span><span class="mcard__k"><?php esc_html_e( 'Membership', 'orienta-yacht-club' ); ?></span><span class="mcard__v"><?php echo esc_html( $oyc_card['type'] ); ?></span></span>
							<?php endif; ?>
							<?php if ( $oyc_card['since'] ) : ?>
								<span><span class="mcard__k"><?php esc_html_e( 'Since', 'orienta-yacht-club' ); ?></span><span class="mcard__v"><?php echo esc_html( $oyc_card['since'] ); ?></span></span>
							<?php endif; ?>
						</span>
						<span class="mcard__corner" aria-hidden="true"><?php esc_html_e( 'Tap for QR', 'orienta-yacht-club' ); ?> &#x21bb;</span>
					</span>

					<!-- BACK -->
					<span class="mcard mcard--back<?php echo OYC_CARD_NOT_LIVE ? ' has-notlive' : ''; ?>">
						<span class="mcard__shine" aria-hidden="true"></span>
						<?php echo oyc_card_not_live_band(); // phpcs:ignore -- escaped in helper ?>
						<span class="mcard__stripe" aria-hidden="true"></span>
						<span class="mcard__back-body">
							<span class="mcard__qr" data-oyc-qr="<?php echo esc_attr( oyc_card_verify_url( $oyc_card_u->ID ) ); ?>" role="img"
							      aria-label="<?php esc_attr_e( 'QR code for staff to verify this membership', 'orienta-yacht-club' ); ?>"></span>
							<span class="mcard__back-info">
								<span class="mcard__back-name"><?php echo esc_html( $oyc_card['name'] ); ?></span>
								<?php if ( $oyc_card['number'] ) : ?>
									<span class="mcard__back-num">#<?php echo esc_html( $oyc_card['number'] ); ?></span>
								<?php endif; ?>
								<?php echo oyc_card_certs_html( $oyc_certs ); // phpcs:ignore -- escaped in helper ?>
								<span class="mcard__live"><span class="mcard__live-dot" aria-hidden="true"></span><time class="mcard__clock" data-oyc-clock></time></span>
								<span class="mcard__scan"><?php esc_html_e( 'Staff: scan to verify', 'orienta-yacht-club' ); ?></span>
								<span class="mcard__club-line"><?php esc_html_e( 'Orienta Yacht Club · Mamaroneck, NY', 'orienta-yacht-club' ); ?></span>
							</span>
						</span>
						<span class="mcard__corner" aria-hidden="true">&#x21bb; <?php esc_html_e( 'Front', 'orienta-yacht-club' ); ?></span>
					</span>
				</button>
			</div>
			<p class="mcard-fliphint"><?php esc_html_e( 'Tap the card to flip it. The QR code is on the back.', 'orienta-yacht-club' ); ?></p>
			</div><!-- .mcard-stage -->

			<div class="mcard-aside">

			<?php if ( isset( $_GET['badges'] ) ) : ?>
				<div class="mcard-note"><p><?php esc_html_e( 'Card updated.', 'orienta-yacht-club' ); ?></p></div>
			<?php endif; ?>

			<details class="mcard-tips mcard-badges-form"<?php echo ( $oyc_badges || $oyc_certs ) ? '' : ' open'; ?>>
				<summary><?php echo $oyc_preview ? esc_html__( 'Badges, licenses & certifications', 'orienta-yacht-club' ) : esc_html__( 'Your badges, licenses & certifications', 'orienta-yacht-club' ); ?></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="oyc_card_badges" />
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $oyc_card_u->ID ); ?>" />
					<?php wp_nonce_field( 'oyc_card_badges', 'oyc_card_badges_nonce' ); ?>
					<p class="mcard-form-label"><?php esc_html_e( 'Badges on the front', 'orienta-yacht-club' ); ?></p>
					<div class="mcard-badge-picks">
						<?php foreach ( $oyc_badge_names as $slug => $label ) : ?>
							<label class="mcard-badge-pick">
								<input type="checkbox" name="badges[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $oyc_badges, true ) ); ?> />
								<span class="mcard__badge-icon"><?php echo oyc_card_badge_svg( $slug ); // phpcs:ignore -- static SVG ?></span>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="mcard-form-label"><?php esc_html_e( 'Licenses & certifications', 'orienta-yacht-club' ); ?></p>
					<p class="mcard-form-hint"><?php esc_html_e( 'Shown on the back of the card and to staff when they scan it. Leave the year blank if it does not expire.', 'orienta-yacht-club' ); ?></p>
					<div class="mcard-certs-edit">
						<?php
						$oyc_cert_rows = $oyc_certs;
						$oyc_cert_target = min( 6, max( 4, count( $oyc_certs ) + 1 ) );
						while ( count( $oyc_cert_rows ) < $oyc_cert_target ) {
							$oyc_cert_rows[] = array( 'name' => '', 'expires' => '' );
						}
						foreach ( $oyc_cert_rows as $i => $c ) :
						?>
							<div class="mcard-cert-row">
								<input type="text" name="cert_name[]" list="oyc-cert-list" maxlength="60" value="<?php echo esc_attr( $c['name'] ); ?>"
								       placeholder="<?php esc_attr_e( 'e.g. NY Boater Safety Certificate', 'orienta-yacht-club' ); ?>" aria-label="<?php esc_attr_e( 'License or certification', 'orienta-yacht-club' ); ?>" />
								<input type="text" name="cert_expires[]" inputmode="numeric" maxlength="4" value="<?php echo esc_attr( $c['expires'] ); ?>"
								       placeholder="<?php esc_attr_e( 'Expires', 'orienta-yacht-club' ); ?>" aria-label="<?php esc_attr_e( 'Expiry year', 'orienta-yacht-club' ); ?>" />
							</div>
						<?php endforeach; ?>
						<datalist id="oyc-cert-list">
							<?php foreach ( oyc_card_cert_suggestions() as $sug ) : ?>
								<option value="<?php echo esc_attr( $sug ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save', 'orienta-yacht-club' ); ?></button>
				</form>
			</details>

			<?php if ( ! $oyc_card['number'] && ! $oyc_preview ) : ?>
				<p class="mcard-help"><?php esc_html_e( 'Your member number has not been added to the website yet. The club will add it; your card still verifies in the meantime.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

			<?php if ( ! $oyc_preview ) : ?>
				<details class="mcard-tips">
					<summary><?php esc_html_e( 'Keep your card on your phone', 'orienta-yacht-club' ); ?></summary>
					<ul>
						<li><strong><?php esc_html_e( 'iPhone:', 'orienta-yacht-club' ); ?></strong> <?php esc_html_e( 'open this page in Safari, tap Share, then "Add to Home Screen".', 'orienta-yacht-club' ); ?></li>
						<li><strong><?php esc_html_e( 'Android:', 'orienta-yacht-club' ); ?></strong> <?php esc_html_e( 'open this page in Chrome, tap the ⋮ menu, then "Add to Home screen".', 'orienta-yacht-club' ); ?></li>
						<li><?php esc_html_e( 'Once you have opened it while online, the card also shows with no signal at the dock.', 'orienta-yacht-club' ); ?></li>
						<li><?php esc_html_e( 'Turn your screen brightness up when staff scan it.', 'orienta-yacht-club' ); ?></li>
					</ul>
				</details>
			<?php endif; ?>

			<p class="mcard-back">
				<?php if ( $oyc_preview ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'edit_user', $oyc_card_u->ID, oyc_officer_page_url( 'page-officer-members.php', 'officer-members' ) ) ); ?>">&larr; <?php esc_html_e( 'Back to this member', 'orienta-yacht-club' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/members-area/' ) ); ?>">&larr; <?php esc_html_e( 'Member Dashboard', 'orienta-yacht-club' ); ?></a>
				<?php endif; ?>
			</p>

			</div><!-- .mcard-aside -->
			</div><!-- .mcard-layout -->

		<?php endif; ?>

	</div>
</section>

<?php
get_footer();

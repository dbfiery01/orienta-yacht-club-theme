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

<div class="page-hero page-hero--card">
	<div class="container">
		<p class="page-hero-eyebrow"><?php echo $oyc_preview ? esc_html__( 'Officer preview', 'orienta-yacht-club' ) : esc_html__( 'Members', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Membership Card', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section mcard-section">
	<div class="container mcard-wrap">

		<?php if ( ! $oyc_allowed ) : ?>

			<div class="mcard-note mcard-note--warn">
				<p><?php esc_html_e( 'Your card will appear here once the club has confirmed your membership.', 'orienta-yacht-club' ); ?></p>
			</div>

		<?php else : ?>

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

			<article class="mcard <?php echo $oyc_status['valid'] ? '' : 'mcard--invalid'; ?>" aria-label="<?php esc_attr_e( 'Orienta Yacht Club membership card', 'orienta-yacht-club' ); ?>">
				<div class="mcard__shine" aria-hidden="true"></div>

				<header class="mcard__top">
					<span class="mcard__brand">
						<img class="mcard__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/oyc-logo-dark.png' ); ?>"
						     alt="<?php esc_attr_e( 'Orienta Yacht Club', 'orienta-yacht-club' ); ?>" loading="eager" decoding="async" />
						<span class="mcard__club-sub"><?php esc_html_e( 'Membership Card · Mamaroneck, NY', 'orienta-yacht-club' ); ?></span>
					</span>
					<?php if ( $oyc_season ) : ?>
						<span class="mcard__season"><?php echo esc_html( $oyc_season ); ?></span>
					<?php endif; ?>
				</header>

				<div class="mcard__name"><?php echo esc_html( $oyc_card['name'] ); ?></div>

				<dl class="mcard__facts">
					<div>
						<dt><?php esc_html_e( 'Member No.', 'orienta-yacht-club' ); ?></dt>
						<dd class="mcard__number"><?php echo $oyc_card['number'] ? esc_html( $oyc_card['number'] ) : '<span class="mcard__pending">' . esc_html__( 'Pending', 'orienta-yacht-club' ) . '</span>'; ?></dd>
					</div>
					<?php if ( $oyc_card['type'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Membership', 'orienta-yacht-club' ); ?></dt>
							<dd><?php echo esc_html( $oyc_card['type'] ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $oyc_card['since'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Member Since', 'orienta-yacht-club' ); ?></dt>
							<dd><?php echo esc_html( $oyc_card['since'] ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<div class="mcard__qr-row">
					<div class="mcard__qr" data-oyc-qr="<?php echo esc_attr( oyc_card_verify_url( $oyc_card_u->ID ) ); ?>" role="img"
					     aria-label="<?php esc_attr_e( 'QR code for staff to verify this membership', 'orienta-yacht-club' ); ?>"></div>
					<div class="mcard__live">
						<span class="mcard__live-label"><span class="mcard__live-dot" aria-hidden="true"></span><?php esc_html_e( 'Live card', 'orienta-yacht-club' ); ?></span>
						<time class="mcard__clock" data-oyc-clock></time>
						<span class="mcard__live-hint"><?php esc_html_e( 'Staff: scan the code to verify.', 'orienta-yacht-club' ); ?></span>
					</div>
				</div>
			</article>

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

		<?php endif; ?>

	</div>
</section>

<?php
get_footer();

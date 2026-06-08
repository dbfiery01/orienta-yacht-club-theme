<?php
/**
 * Template Name: Fleet Roster
 *
 * Members-only searchable member/boat directory. Data comes from the
 * `oyc_fleet_roster` DB option (see inc/fleet-roster.php) and is rendered
 * ONLY for logged-in members.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
$is_member = is_user_logged_in();
$roster    = $is_member ? oyc_get_roster() : array();
?>

<div class="page-hero">
	<div class="container">
		<h1 class="page-hero-title"><?php esc_html_e( 'Fleet Roster', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section">
	<div class="container page-content">

		<?php if ( ! $is_member ) : ?>
			<div class="roster-gate">
				<p><?php esc_html_e( 'The Fleet Roster is available to Orienta Yacht Club members only.', 'orienta-yacht-club' ); ?></p>
				<a class="btn btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Member Login', 'orienta-yacht-club' ); ?></a>
			</div>

		<?php elseif ( empty( $roster ) ) : ?>
			<p class="roster-empty"><?php esc_html_e( 'The roster has not been loaded yet.', 'orienta-yacht-club' ); ?></p>

		<?php else : ?>
			<div class="roster-tools">
				<input type="search" id="roster-search" class="roster-search"
					placeholder="<?php esc_attr_e( 'Search by boat, name, phone, email, or address…', 'orienta-yacht-club' ); ?>"
					aria-label="<?php esc_attr_e( 'Search the fleet roster', 'orienta-yacht-club' ); ?>" autocomplete="off" />
				<p class="roster-count"><span id="roster-shown"><?php echo count( $roster ); ?></span> <?php esc_html_e( 'members', 'orienta-yacht-club' ); ?></p>
			</div>

			<p class="roster-pdf"><a href="<?php echo esc_url( home_url( '/fleet-roster-pdf/' ) ); ?>" target="_blank" rel="noopener">&#128196; <?php esc_html_e( 'Download the full Fleet Roster (PDF)', 'orienta-yacht-club' ); ?></a></p>

			<div id="roster-list" class="roster-list">
				<?php
				foreach ( $roster as $m ) :
					$name    = isset( $m['name'] ) ? $m['name'] : '';
					$spouse  = isset( $m['spouse'] ) ? $m['spouse'] : '';
					$email   = isset( $m['email'] ) ? $m['email'] : '';
					$street  = isset( $m['street'] ) ? $m['street'] : '';
					$city    = isset( $m['city'] ) ? $m['city'] : '';
					$state   = isset( $m['state'] ) ? $m['state'] : '';
					$zip     = isset( $m['zip'] ) ? $m['zip'] : '';
					$boat    = isset( $m['boat'] ) ? $m['boat'] : '';
					$blen    = isset( $m['boatLen'] ) ? $m['boatLen'] : '';
					$btype   = isset( $m['boatType'] ) ? $m['boatType'] : '';
					$cat     = isset( $m['category'] ) ? $m['category'] : '';
					$phones  = isset( $m['phones'] ) && is_array( $m['phones'] ) ? $m['phones'] : array();
					$cityln  = trim( $city . ( $state ? ', ' . $state : '' ) . ( $zip ? ' ' . $zip : '' ), ', ' );
					$boatln  = trim( $boat . ' ' . $blen . ' ' . $btype );
					$hay     = strtolower( implode( ' ', array( $name, $spouse, $boat, $btype, $cat, $email, $street, $cityln, implode( ' ', $phones ) ) ) );
					?>
					<article class="roster-card" data-search="<?php echo esc_attr( $hay ); ?>">
						<h2 class="roster-card__name">
							<?php echo esc_html( $name ); ?>
							<?php if ( $spouse ) : ?><span class="roster-card__spouse">(<?php echo esc_html( $spouse ); ?>)</span><?php endif; ?>
							<?php if ( 'Associate' === $cat ) : ?><span class="roster-badge"><?php esc_html_e( 'Associate', 'orienta-yacht-club' ); ?></span><?php endif; ?>
						</h2>
						<?php if ( $boatln ) : ?>
							<p class="roster-card__boat">&#9973; <?php echo esc_html( $boatln ); ?></p>
						<?php endif; ?>
						<ul class="roster-card__contact">
							<?php if ( $email ) : ?>
								<li><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
							<?php endif; ?>
							<?php foreach ( $phones as $ph ) : ?>
								<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $ph ) ); ?>"><?php echo esc_html( $ph ); ?></a></li>
							<?php endforeach; ?>
							<?php if ( $street || $cityln ) : ?>
								<li class="roster-card__addr"><?php echo esc_html( trim( $street . ( $cityln ? ', ' . $cityln : '' ), ', ' ) ); ?></li>
							<?php endif; ?>
						</ul>
					</article>
				<?php endforeach; ?>
			</div>

			<p class="roster-noresults" hidden><?php esc_html_e( 'No members match your search.', 'orienta-yacht-club' ); ?></p>

			<script>
			(function () {
				var input = document.getElementById('roster-search');
				var cards = Array.prototype.slice.call(document.querySelectorAll('#roster-list .roster-card'));
				var shown = document.getElementById('roster-shown');
				var none = document.querySelector('.roster-noresults');
				function apply() {
					var q = input.value.trim().toLowerCase();
					var n = 0;
					cards.forEach(function (c) {
						var hit = !q || c.getAttribute('data-search').indexOf(q) !== -1;
						c.hidden = !hit;
						if (hit) { n++; }
					});
					if (shown) { shown.textContent = n; }
					if (none) { none.hidden = n !== 0; }
				}
				input.addEventListener('input', apply);
			})();
			</script>
		<?php endif; ?>

	</div>
</section>

<?php get_footer(); ?>

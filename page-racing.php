<?php
/**
 * Template Name: Racing
 * Race RSVP and the racers-only hub. Renders for a Page with slug "racing", or
 * at /racing/ with no Page (fallback in inc/racing.php). Handlers and helpers
 * live in inc/racing.php.
 *
 * Views (?view=): races (default) · race (&race=ID) · board · sailors · me ·
 * committee. Guests and members who have not RSVP'd yet see only the race list
 * and the RSVP / sign-up forms.
 *
 * @package Orienta_Yacht_Club
 */

$me        = wp_get_current_user();
$logged_in = is_user_logged_in();
$is_racer  = oyc_is_racer();
$can_rc    = oyc_race_can_manage();
$view      = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'races';
$notice    = isset( $_GET['rs'] ) ? sanitize_key( wp_unslash( $_GET['rs'] ) ) : '';
$race      = isset( $_GET['race'] ) ? oyc_race_get( (int) $_GET['race'] ) : null;

if ( 'race' === $view && ! $race ) {
	$view = 'races';
}
// Racers-only views.
if ( ! $is_racer && in_array( $view, array( 'board', 'sailors', 'me' ), true ) ) {
	if ( ! $logged_in ) {
		wp_safe_redirect( wp_login_url( oyc_race_url( array( 'view' => $view ) ) ) );
		exit;
	}
	$view = 'races';
}
if ( 'committee' === $view && ! $can_rc ) {
	$view = 'races';
}
if ( ! $is_racer && ! in_array( $view, array( 'races', 'race' ), true ) ) {
	$view = 'races';
}

$upcoming = oyc_race_races( 'upcoming' );

$messages = array(
	'rsvped'         => array( 'ok', __( 'You’re entered! We emailed you a confirmation. The racers’ area is now open to you.', 'orienta-yacht-club' ) ),
	'rsvpupdated'    => array( 'ok', __( 'Your RSVP is updated.', 'orienta-yacht-club' ) ),
	'withdrawn'      => array( 'ok', __( 'Your RSVP is cancelled.', 'orienta-yacht-club' ) ),
	'signedup'       => array( 'ok', __( 'Thanks! A race officer will review your entry. You will get an email with your racer login once it is approved (check your spam folder too).', 'orienta-yacht-club' ) ),
	'haveaccount'    => array( 'err', __( 'That email address already has a login on this site. Please log in to RSVP, or use “Forgot password” on the login page.', 'orienta-yacht-club' ) ),
	'already'        => array( 'err', __( 'You are already logged in, so just RSVP from the race page.', 'orienta-yacht-club' ) ),
	'sumissing'      => array( 'err', __( 'Please fill in your name, email, phone, the race, and your boat name if you are the skipper.', 'orienta-yacht-club' ) ),
	'noboat'         => array( 'err', __( 'Skippers, please give your boat’s name.', 'orienta-yacht-club' ) ),
	'closed'         => array( 'err', __( 'RSVPs for that race are closed.', 'orienta-yacht-club' ) ),
	'pending'        => array( 'err', __( 'Your login still needs to be approved before you can RSVP.', 'orienta-yacht-club' ) ),
	'members'        => array( 'err', __( 'That page is for Orienta members. Your racer login opens the racers’ area.', 'orienta-yacht-club' ) ),
	'notracer'       => array( 'err', __( 'RSVP to a race to open the racers’ area.', 'orienta-yacht-club' ) ),
	'norace'         => array( 'err', __( 'That race no longer exists.', 'orienta-yacht-club' ) ),
	'raceadded'      => array( 'ok', __( 'Race added.', 'orienta-yacht-club' ) ),
	'raceupdated'    => array( 'ok', __( 'Race updated.', 'orienta-yacht-club' ) ),
	'racedeleted'    => array( 'ok', __( 'Race deleted.', 'orienta-yacht-club' ) ),
	'racemissing'    => array( 'err', __( 'A race needs a name and a date.', 'orienta-yacht-club' ) ),
	'badfile'        => array( 'err', __( 'The race was saved, but a document didn’t upload. Use PDF, JPG, PNG, GIF or WebP files under 20 MB.', 'orienta-yacht-club' ) ),
	'approved'       => array( 'ok', __( 'Approved. They have been emailed a link to set their password.', 'orienta-yacht-club' ) ),
	'approvednomail' => array( 'err', __( 'Approved, but the email didn’t send. Check WP Mail SMTP (Tools → Email Test).', 'orienta-yacht-club' ) ),
	'declined'       => array( 'ok', __( 'Declined. The sign-up and its RSVP were removed.', 'orienta-yacht-club' ) ),
	/* translators: %d: number of racers. */
	'mailsent'       => array( 'ok', sprintf( __( 'Email sent to %d racers.', 'orienta-yacht-club' ), (int) ( $_GET['n'] ?? 0 ) ) ),
	/* translators: %d: number of racers. */
	'mailpartial'    => array( 'err', sprintf( __( 'Only %d racers could be emailed. Check WP Mail SMTP (Tools → Email Test).', 'orienta-yacht-club' ), (int) ( $_GET['n'] ?? 0 ) ) ),
	'mailmissing'    => array( 'err', __( 'Please write a subject and a message.', 'orienta-yacht-club' ) ),
	'mailnobody'     => array( 'err', __( 'Nobody is on that list yet.', 'orienta-yacht-club' ) ),
	'linksaved'      => array( 'ok', __( 'Section saved.', 'orienta-yacht-club' ) ),
	'linkdeleted'    => array( 'ok', __( 'Section removed.', 'orienta-yacht-club' ) ),
	'linkmoved'      => array( 'ok', __( 'Section moved.', 'orienta-yacht-club' ) ),
	'linkmissing'    => array( 'err', __( 'A section needs a heading. If you were editing, someone may have just removed it.', 'orienta-yacht-club' ) ),
	'profilesaved'   => array( 'ok', __( 'Your profile is saved.', 'orienta-yacht-club' ) ),
	'yachtsaved'     => array( 'ok', __( 'Yacht saved.', 'orienta-yacht-club' ) ),
	'yachtdeleted'   => array( 'ok', __( 'Yacht removed.', 'orienta-yacht-club' ) ),
	'badphoto'       => array( 'err', __( 'Saved, but the photo didn’t upload. Please use a JPG, PNG, GIF or WebP image under 12 MB.', 'orienta-yacht-club' ) ),
	'adposted'       => array( 'ok', __( 'Your post is up on the crew board.', 'orienta-yacht-club' ) ),
	'adupdated'      => array( 'ok', __( 'Post updated.', 'orienta-yacht-club' ) ),
	'addeleted'      => array( 'ok', __( 'Post deleted.', 'orienta-yacht-club' ) ),
	'adclosed'       => array( 'ok', __( 'Marked as filled.', 'orienta-yacht-club' ) ),
	'adreopened'     => array( 'ok', __( 'Post is open again.', 'orienta-yacht-club' ) ),
	'admissing'      => array( 'err', __( 'Please give your post a title and some details.', 'orienta-yacht-club' ) ),
	'msgsent'        => array( 'ok', __( 'Message sent. Their reply will come to your email.', 'orienta-yacht-club' ) ),
	'msgmissing'     => array( 'err', __( 'Please write a message.', 'orienta-yacht-club' ) ),
	'slowdown'       => array( 'err', __( 'That’s a lot in a short time. Please try again later.', 'orienta-yacht-club' ) ),
	'denied'         => array( 'err', __( 'You can’t change that.', 'orienta-yacht-club' ) ),
	'login'          => array( 'err', __( 'Please log in first.', 'orienta-yacht-club' ) ),
	'failed'         => array( 'err', __( 'Sorry, that didn’t save. Please try again.', 'orienta-yacht-club' ) ),
);

$post_url = esc_url( admin_url( 'admin-post.php' ) );
$tab_url  = function ( $v, $extra = array() ) {
	return oyc_race_url( array_merge( 'races' === $v ? array() : array( 'view' => $v ), $extra ) );
};

/** RSVP fields shared by the member RSVP form and the visiting-sailor sign-up. */
$rsvp_fields = function ( $m, $contact ) {
	$m = wp_parse_args( $m, array( 'role' => 'skipper', 'boat' => '', 'sail' => '', 'class' => '', 'rating' => '', 'crew' => '', 'need' => '', 'notes' => '' ) );
	?>
	<div class="rc-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'I am racing as', 'orienta-yacht-club' ); ?>">
		<label><input type="radio" name="oyc_role" value="skipper" <?php checked( 'crew' !== $m['role'] ); ?>> <span><?php esc_html_e( 'Skipper (I’m bringing a boat)', 'orienta-yacht-club' ); ?></span></label>
		<label><input type="radio" name="oyc_role" value="crew" <?php checked( 'crew' === $m['role'] ); ?>> <span><?php esc_html_e( 'Crew', 'orienta-yacht-club' ); ?></span></label>
	</div>
	<div class="rc-grid" data-for="skipper">
		<label class="rc-field rc-field--wide"><span><?php esc_html_e( 'Boat name', 'orienta-yacht-club' ); ?></span>
			<input type="text" name="oyc_boat" maxlength="60" value="<?php echo esc_attr( $m['boat'] ); ?>" list="rc-my-yachts"></label>
		<label class="rc-field"><span><?php esc_html_e( 'Sail number', 'orienta-yacht-club' ); ?></span>
			<input type="text" name="oyc_sail" maxlength="20" value="<?php echo esc_attr( $m['sail'] ); ?>"></label>
		<label class="rc-field"><span><?php esc_html_e( 'Class / design', 'orienta-yacht-club' ); ?></span>
			<input type="text" name="oyc_class" maxlength="40" value="<?php echo esc_attr( $m['class'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. J/24, Catalina 30', 'orienta-yacht-club' ); ?>"></label>
		<label class="rc-field"><span><?php esc_html_e( 'PHRF rating', 'orienta-yacht-club' ); ?></span>
			<input type="text" name="oyc_rating" maxlength="12" value="<?php echo esc_attr( $m['rating'] ); ?>" inputmode="numeric"></label>
		<label class="rc-field"><span><?php esc_html_e( 'Crew on board (incl. you)', 'orienta-yacht-club' ); ?></span>
			<input type="number" name="oyc_crew" min="0" max="30" value="<?php echo esc_attr( $m['crew'] ); ?>"></label>
	</div>
	<label class="rc-check" data-for="skipper"><input type="checkbox" name="oyc_need" value="1" <?php checked( $m['need'] && 'crew' !== $m['role'] ); ?>> <?php esc_html_e( 'I’m looking for more crew for this race', 'orienta-yacht-club' ); ?></label>
	<label class="rc-check" data-for="crew"><input type="checkbox" name="oyc_need" value="1" <?php checked( $m['need'] && 'crew' === $m['role'] ); ?>> <?php esc_html_e( 'I’m looking for a boat to sail on', 'orienta-yacht-club' ); ?></label>
	<div class="rc-grid">
		<label class="rc-field"><span><?php esc_html_e( 'Your mobile phone', 'orienta-yacht-club' ); ?></span>
			<input type="tel" name="oyc_phone" maxlength="30" value="<?php echo esc_attr( $contact['oyc_phone'] ); ?>" required></label>
		<label class="rc-field"><span><?php esc_html_e( 'Emergency contact', 'orienta-yacht-club' ); ?></span>
			<input type="text" name="oyc_emergency_name" maxlength="80" value="<?php echo esc_attr( $contact['oyc_emergency_name'] ); ?>"></label>
		<label class="rc-field"><span><?php esc_html_e( 'Emergency phone', 'orienta-yacht-club' ); ?></span>
			<input type="tel" name="oyc_emergency_phone" maxlength="30" value="<?php echo esc_attr( $contact['oyc_emergency_phone'] ); ?>"></label>
		<label class="rc-field"><span><?php esc_html_e( 'Relationship', 'orienta-yacht-club' ); ?></span>
			<input type="text" name="oyc_emergency_relationship" maxlength="40" value="<?php echo esc_attr( $contact['oyc_emergency_relationship'] ); ?>"></label>
		<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Notes for the Race Committee (optional)', 'orienta-yacht-club' ); ?></span>
			<textarea name="oyc_notes" rows="2" maxlength="500"><?php echo esc_textarea( $m['notes'] ); ?></textarea></label>
	</div>
	<p class="rc-note"><?php esc_html_e( 'Your phone and emergency contact are seen only by the Race Committee.', 'orienta-yacht-club' ); ?></p>
	<?php
};

$my_contact = array();
foreach ( array( 'oyc_phone', 'oyc_emergency_name', 'oyc_emergency_phone', 'oyc_emergency_relationship', 'oyc_racer_club' ) as $k ) {
	$my_contact[ $k ] = $logged_in ? (string) get_user_meta( $me->ID, $k, true ) : '';
}
$my_yachts = $logged_in ? oyc_race_yachts( $me->ID ) : array();

get_header();
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php echo esc_html( $is_racer ? __( 'Racers only', 'orienta-yacht-club' ) : __( 'Racing at Orienta', 'orienta-yacht-club' ) ); ?></p>
		<h1 class="page-hero-title"><?php echo esc_html( 'race' === $view ? $race->post_title : __( 'Racing', 'orienta-yacht-club' ) ); ?></h1>
		<p class="page-hero-sub"><?php echo esc_html( 'race' === $view
			? trim( oyc_race_fmt_date( get_post_meta( $race->ID, '_oyc_race_date', true ) ) . ( get_post_meta( $race->ID, '_oyc_race_area', true ) ? ' · ' . get_post_meta( $race->ID, '_oyc_race_area', true ) : '' ) )
			: __( 'RSVP for races, find crew or a boat, and get the race details.', 'orienta-yacht-club' ) ); ?></p>
	</div>
</div>

<section class="section">
	<div class="container page-content rc-page" id="racing">

		<?php if ( isset( $messages[ $notice ] ) ) : ?>
			<div class="rc-notice rc-notice--<?php echo esc_attr( $messages[ $notice ][0] ); ?>" role="status"><?php echo esc_html( $messages[ $notice ][1] ); ?></div>
		<?php endif; ?>

		<?php if ( $is_racer ) : ?>
			<nav class="rc-tabs" aria-label="<?php esc_attr_e( 'Racing', 'orienta-yacht-club' ); ?>">
				<?php
				$tabs = array(
					'races'   => __( 'Races', 'orienta-yacht-club' ),
					'board'   => __( 'Crew board', 'orienta-yacht-club' ),
					'sailors' => __( 'Sailors & yachts', 'orienta-yacht-club' ),
					'me'      => __( 'My profile', 'orienta-yacht-club' ),
				);
				if ( $can_rc ) {
					$tabs['committee'] = __( 'Race Committee', 'orienta-yacht-club' );
				}
				/**
				 * Extra racers-only tabs (view slug => label). Render one with the
				 * `oyc_race_view_{slug}` action.
				 */
				$tabs = (array) apply_filters( 'oyc_race_tabs', $tabs );
				$active = 'race' === $view ? 'races' : $view;
				foreach ( $tabs as $k => $label ) {
					printf( '<a class="rc-tab%1$s" href="%2$s"%3$s>%4$s</a>', $active === $k ? ' is-active' : '', esc_url( $tab_url( $k ) ), $active === $k ? ' aria-current="page"' : '', esc_html( $label ) );
				}
				?>
			</nav>
		<?php endif; ?>

		<?php if ( $my_yachts ) : ?>
			<datalist id="rc-my-yachts">
				<?php foreach ( $my_yachts as $y ) : ?>
					<option value="<?php echo esc_attr( $y->post_title ); ?>" data-sail="<?php echo esc_attr( get_post_meta( $y->ID, '_sail', true ) ); ?>" data-class="<?php echo esc_attr( get_post_meta( $y->ID, '_class', true ) ); ?>" data-rating="<?php echo esc_attr( get_post_meta( $y->ID, '_rating', true ) ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
		<?php endif; ?>

		<?php
		/* ══════════════════════════════════════════════════════════════════════
		 * RACES (list)
		 * ══════════════════════════════════════════════════════════════════════ */
		if ( 'races' === $view ) :
			?>
			<h2 class="rc-h2"><?php esc_html_e( 'Upcoming races', 'orienta-yacht-club' ); ?></h2>
			<?php if ( $upcoming ) : ?>
				<div class="rc-races">
					<?php foreach ( $upcoming as $r ) :
						$entries = oyc_race_entries( $r->ID );
						$mine    = $logged_in ? oyc_race_user_entry( $r->ID, $me->ID ) : null;
						$time    = oyc_race_fmt_time( get_post_meta( $r->ID, '_oyc_race_time', true ) );
						$dead    = (string) get_post_meta( $r->ID, '_oyc_race_deadline', true );
						$link    = $tab_url( 'race', array( 'race' => $r->ID ) );
						$boats   = count( array_filter( $entries, function ( $e ) { return 'crew' !== get_post_meta( $e->ID, '_role', true ); } ) );
						?>
						<article class="rc-race">
							<div class="rc-race__date">
								<span class="rc-race__mon"><?php echo esc_html( wp_date( 'M', strtotime( get_post_meta( $r->ID, '_oyc_race_date', true ) . ' 12:00' ) ) ); ?></span>
								<span class="rc-race__day"><?php echo esc_html( wp_date( 'j', strtotime( get_post_meta( $r->ID, '_oyc_race_date', true ) . ' 12:00' ) ) ); ?></span>
							</div>
							<div class="rc-race__body">
								<div class="rc-race__title"><?php echo esc_html( $r->post_title ); ?> <?php echo oyc_race_status_badge( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
								<div class="rc-race__meta">
									<?php echo esc_html( oyc_race_fmt_date( get_post_meta( $r->ID, '_oyc_race_date', true ) ) ); ?>
									<?php if ( $time ) : ?> · <?php /* translators: %s: time */ echo esc_html( sprintf( __( 'First warning %s', 'orienta-yacht-club' ), $time ) ); ?><?php endif; ?>
									<?php if ( get_post_meta( $r->ID, '_oyc_race_area', true ) ) : ?> · <?php echo esc_html( get_post_meta( $r->ID, '_oyc_race_area', true ) ); ?><?php endif; ?>
								</div>
								<div class="rc-race__meta rc-race__meta--sub">
									<?php /* translators: 1: boats, 2: sailors */ echo esc_html( sprintf( _n( '%1$d boat entered', '%1$d boats entered', $boats, 'orienta-yacht-club' ), $boats ) . ' · ' . sprintf( _n( '%d sailor', '%d sailors', count( $entries ), 'orienta-yacht-club' ), count( $entries ) ) ); ?>
									<?php if ( oyc_race_date_ok( $dead ) ) : ?> · <?php /* translators: %s: date */ echo esc_html( sprintf( __( 'RSVP by %s', 'orienta-yacht-club' ), oyc_race_fmt_date( $dead ) ) ); ?><?php endif; ?>
								</div>
							</div>
							<div class="rc-race__cta">
								<?php if ( $mine ) : ?>
									<span class="rc-badge rc-badge--ok"><?php echo esc_html( 'pending' === $mine->post_status ? __( 'Awaiting approval', 'orienta-yacht-club' ) : __( 'You’re entered', 'orienta-yacht-club' ) ); ?></span>
									<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Details', 'orienta-yacht-club' ); ?></a>
								<?php elseif ( $logged_in ) : ?>
									<a class="btn btn-sm btn-primary" href="<?php echo esc_url( $link . '#rsvp' ); ?>"><?php echo esc_html( oyc_race_rsvp_open( $r ) ? __( 'RSVP', 'orienta-yacht-club' ) : __( 'Details', 'orienta-yacht-club' ) ); ?></a>
								<?php else : ?>
									<a class="btn btn-sm btn-primary" href="#signup" data-race="<?php echo (int) $r->ID; ?>"><?php esc_html_e( 'RSVP', 'orienta-yacht-club' ); ?></a>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'No races are scheduled yet. Check back soon.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

			<?php if ( $can_rc ) : ?>
				<p><a class="btn btn-primary" href="<?php echo esc_url( $tab_url( 'committee' ) . '#race-form' ); ?>"><?php esc_html_e( '+ Add a race', 'orienta-yacht-club' ); ?></a></p>
			<?php endif; ?>

			<?php if ( ! $logged_in ) : ?>
				<div class="rc-split">
					<div class="rc-card">
						<h2 class="rc-h3"><?php esc_html_e( 'Orienta member?', 'orienta-yacht-club' ); ?></h2>
						<p class="rc-p"><?php esc_html_e( 'Log in with your member login, then RSVP from the race.', 'orienta-yacht-club' ); ?></p>
						<a class="btn btn-primary" href="<?php echo esc_url( wp_login_url( oyc_race_url() ) ); ?>"><?php esc_html_e( 'Log in to RSVP', 'orienta-yacht-club' ); ?></a>
					</div>
					<div class="rc-card rc-card--wide" id="signup">
						<h2 class="rc-h3"><?php esc_html_e( 'Visiting sailor? Sign up to race', 'orienta-yacht-club' ); ?></h2>
						<p class="rc-p"><?php esc_html_e( 'Sailors from other clubs are welcome. A race officer confirms your entry and emails you a racer login for the racers’ area: race details, charts, sailing instructions and the Crew Wanted board.', 'orienta-yacht-club' ); ?></p>
						<?php if ( $upcoming ) : ?>
							<form class="rc-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
								<input type="hidden" name="action" value="oyc_race_signup">
								<input type="hidden" name="oyc_t" value="<?php echo (int) time(); ?>">
								<?php wp_nonce_field( 'oyc_race_signup' ); ?>
								<div class="rc-hp" aria-hidden="true"><label>Website <input type="text" name="oyc_website" tabindex="-1" autocomplete="off"></label></div>
								<div class="rc-grid">
									<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Race', 'orienta-yacht-club' ); ?></span>
										<select name="race_id" required>
											<?php foreach ( $upcoming as $r ) : if ( ! oyc_race_rsvp_open( $r ) ) { continue; } ?>
												<option value="<?php echo (int) $r->ID; ?>"><?php echo esc_html( $r->post_title . ' — ' . oyc_race_fmt_date( get_post_meta( $r->ID, '_oyc_race_date', true ) ) ); ?></option>
											<?php endforeach; ?>
										</select></label>
									<label class="rc-field"><span><?php esc_html_e( 'First name', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_first" maxlength="40" required autocomplete="given-name"></label>
									<label class="rc-field"><span><?php esc_html_e( 'Last name', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_last" maxlength="40" required autocomplete="family-name"></label>
									<label class="rc-field"><span><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?></span><input type="email" name="oyc_email" maxlength="100" required autocomplete="email"></label>
									<label class="rc-field"><span><?php esc_html_e( 'Your club', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_racer_club" maxlength="80"></label>
								</div>
								<?php $rsvp_fields( array(), $my_contact ); ?>
								<div class="rc-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( 'Send my entry', 'orienta-yacht-club' ); ?></button></div>
							</form>
						<?php else : ?>
							<p class="rc-empty"><?php esc_html_e( 'Sign-up opens when the next race is posted.', 'orienta-yacht-club' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php elseif ( ! $is_racer ) : ?>
				<p class="rc-note"><?php esc_html_e( 'RSVP to a race to open the racers’ area: race details, charts, the crew board, and crew and yacht profiles.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

			<?php
			// "Regattas, results & links": from the Racing Page's content until
			// the Race Committee edits them here (see oyc_race_link_sections()).
			$legacy    = oyc_race_page_sections();
			$links     = oyc_race_link_sections();
			$link_edit = $can_rc && isset( $_GET['linkedit'] ) ? sanitize_key( wp_unslash( $_GET['linkedit'] ) ) : '';
			$link_form = function ( $sec ) use ( $post_url ) {
				$body = $sec ? $sec['body'] : '';
				?>
				<form class="rc-form rc-link-form" id="links-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
					<input type="hidden" name="action" value="oyc_race_links">
					<?php if ( $sec ) : ?><input type="hidden" name="link_id" value="<?php echo esc_attr( $sec['id'] ); ?>"><?php endif; ?>
					<?php wp_nonce_field( 'oyc_race_links' ); ?>
					<label class="rc-field"><span><?php esc_html_e( 'Heading', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_title" maxlength="100" required value="<?php echo esc_attr( $sec ? $sec['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. 2027 Regatta Calendar', 'orienta-yacht-club' ); ?>"></label>
					<div class="rc-field"><span><?php esc_html_e( 'Text and links', 'orienta-yacht-club' ); ?></span>
						<?php
						if ( function_exists( 'wp_editor' ) ) {
							wp_editor( $body, 'oyc_link_body_' . ( $sec ? $sec['id'] : 'new' ), array( 'textarea_name' => 'oyc_body', 'teeny' => true, 'media_buttons' => false, 'textarea_rows' => 8, 'quicktags' => true ) );
						} else {
							echo '<textarea name="oyc_body" rows="8">' . esc_textarea( $body ) . '</textarea>';
						}
						?>
						<small class="rc-muted"><?php esc_html_e( 'Use the link button to add a web address, or paste one on its own line.', 'orienta-yacht-club' ); ?></small>
					</div>
					<div class="rc-actions">
						<button type="submit" class="btn btn-primary"><?php echo esc_html( $sec ? __( 'Save section', 'orienta-yacht-club' ) : __( 'Add section', 'orienta-yacht-club' ) ); ?></button>
						<a class="btn rc-btn" href="<?php echo esc_url( oyc_race_url( array(), 'links' ) ); ?>"><?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?></a>
					</div>
				</form>
				<?php
			};
			if ( $links || '' !== $legacy['intro'] || $can_rc ) : ?>
				<h2 class="rc-h2" id="links"><?php esc_html_e( 'Regattas, results & links', 'orienta-yacht-club' ); ?></h2>
				<?php if ( '' !== $legacy['intro'] ) : ?><div class="rc-card rc-legacy"><?php echo wp_kses_post( $legacy['intro'] ); ?></div><?php endif; ?>
				<?php if ( $can_rc ) : ?>
					<div class="rc-links-bar">
						<span class="rc-muted"><?php esc_html_e( 'Race Committee: add, edit, reorder or remove these sections.', 'orienta-yacht-club' ); ?></span>
						<?php if ( 'new' !== $link_edit ) : ?><a class="btn btn-sm btn-primary" href="<?php echo esc_url( oyc_race_url( array( 'linkedit' => 'new' ), 'links-form' ) ); ?>"><?php esc_html_e( '+ Add a section', 'orienta-yacht-club' ); ?></a><?php endif; ?>
					</div>
					<?php
					if ( 'new' === $link_edit ) {
						$link_form( null );
					} elseif ( $link_edit ) {
						foreach ( $links as $sec ) {
							if ( $sec['id'] === $link_edit ) {
								$link_form( $sec );
							}
						}
					}
					?>
				<?php endif; ?>
				<div class="rc-links">
					<?php
					$n = count( $links );
					foreach ( $links as $i => $sec ) :
						if ( $link_edit === $sec['id'] ) {
							continue; // Being edited in the form above.
						}
						?>
						<section class="rc-link-card">
							<h3 class="rc-link-card__title"><?php echo esc_html( $sec['title'] ); ?></h3>
							<div class="rc-legacy"><?php echo wp_kses_post( $sec['body'] ); ?></div>
							<?php if ( $can_rc ) : ?>
								<div class="rc-link-card__tools">
									<a class="btn btn-sm rc-btn" href="<?php echo esc_url( oyc_race_url( array( 'linkedit' => $sec['id'] ), 'links-form' ) ); ?>"><?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?></a>
									<?php
									if ( $i > 0 ) {
										oyc_race_post_button( 'oyc_race_links', array( 'link_id' => $sec['id'], 'do' => 'up' ), __( 'Move up', 'orienta-yacht-club' ) );
									}
									if ( $i < $n - 1 ) {
										oyc_race_post_button( 'oyc_race_links', array( 'link_id' => $sec['id'], 'do' => 'down' ), __( 'Move down', 'orienta-yacht-club' ) );
									}
									/* translators: %s: section heading. */
									oyc_race_post_button( 'oyc_race_links', array( 'link_id' => $sec['id'], 'do' => 'delete' ), __( 'Remove', 'orienta-yacht-club' ), 'rc-btn rc-btn--danger', sprintf( __( 'Remove the “%s” section?', 'orienta-yacht-club' ), $sec['title'] ) );
									?>
								</div>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_racer ) :
				$past = oyc_race_races( 'past', 10 );
				if ( $past ) : ?>
					<h2 class="rc-h2"><?php esc_html_e( 'Recent races', 'orienta-yacht-club' ); ?></h2>
					<ul class="rc-list">
						<?php foreach ( $past as $r ) : ?>
							<li><a href="<?php echo esc_url( $tab_url( 'race', array( 'race' => $r->ID ) ) ); ?>"><?php echo esc_html( $r->post_title ); ?></a> <span class="rc-muted"><?php echo esc_html( oyc_race_fmt_date( get_post_meta( $r->ID, '_oyc_race_date', true ) ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif;
			endif; ?>

		<?php
		/* ══════════════════════════════════════════════════════════════════════
		 * ONE RACE
		 * ══════════════════════════════════════════════════════════════════════ */
		elseif ( 'race' === $view ) :
			$rid      = $race->ID;
			$entries  = oyc_race_entries( $rid, $can_rc ? 'any' : 'publish' );
			$mine     = $logged_in ? oyc_race_user_entry( $rid, $me->ID ) : null;
			$open     = oyc_race_rsvp_open( $race );
			$time     = oyc_race_fmt_time( get_post_meta( $rid, '_oyc_race_time', true ) );
			$dead     = (string) get_post_meta( $rid, '_oyc_race_deadline', true );
			$docs     = array_values( array_filter( (array) get_post_meta( $rid, '_oyc_race_docs', true ), 'is_array' ) );
			$skippers = array();
			$crew     = array();
			foreach ( $entries as $e ) {
				$m = oyc_race_entry_meta( $e );
				if ( 'crew' === $m['role'] ) {
					$crew[] = array( $e, $m );
				} else {
					$skippers[] = array( $e, $m );
				}
			}
			?>
			<p class="rc-back"><a href="<?php echo esc_url( oyc_race_url() ); ?>">← <?php esc_html_e( 'All races', 'orienta-yacht-club' ); ?></a></p>

			<div class="rc-card rc-race-head">
				<div class="rc-facts">
					<div><span class="rc-label"><?php esc_html_e( 'Date', 'orienta-yacht-club' ); ?></span><?php echo esc_html( oyc_race_fmt_date( get_post_meta( $rid, '_oyc_race_date', true ) ) ); ?> <?php echo oyc_race_status_badge( $race ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php if ( $time ) : ?><div><span class="rc-label"><?php esc_html_e( 'First warning', 'orienta-yacht-club' ); ?></span><?php echo esc_html( $time ); ?></div><?php endif; ?>
					<?php if ( get_post_meta( $rid, '_oyc_race_area', true ) ) : ?><div><span class="rc-label"><?php esc_html_e( 'Course / area', 'orienta-yacht-club' ); ?></span><?php echo esc_html( get_post_meta( $rid, '_oyc_race_area', true ) ); ?></div><?php endif; ?>
					<?php if ( oyc_race_date_ok( $dead ) ) : ?><div><span class="rc-label"><?php esc_html_e( 'RSVP by', 'orienta-yacht-club' ); ?></span><?php echo esc_html( oyc_race_fmt_date( $dead ) ); ?></div><?php endif; ?>
				</div>
				<?php if ( $can_rc ) : ?>
					<div class="rc-head-actions">
						<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'committee', array( 'edit' => $rid ) ) . '#race-form' ); ?>"><?php esc_html_e( 'Edit race', 'orienta-yacht-club' ); ?></a>
						<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'committee', array( 'aud' => 'race:' . $rid ) ) . '#email' ); ?>"><?php esc_html_e( 'Email entrants', 'orienta-yacht-club' ); ?></a>
						<a class="btn btn-sm rc-btn" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'oyc_race_csv', 'race' => $rid ), admin_url( 'admin-post.php' ) ), 'oyc_race_csv' ) ); ?>"><?php esc_html_e( 'Download entry list (CSV)', 'orienta-yacht-club' ); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $is_racer ) : ?>
				<?php if ( '' !== trim( $race->post_content ) ) : ?>
					<h2 class="rc-h2"><?php esc_html_e( 'Race details', 'orienta-yacht-club' ); ?></h2>
					<div class="rc-card rc-details"><?php echo wp_kses_post( make_clickable( nl2br( esc_html( $race->post_content ) ) ) ); ?></div>
				<?php endif; ?>

				<?php if ( $docs ) : ?>
					<h2 class="rc-h2"><?php esc_html_e( 'Charts & documents', 'orienta-yacht-club' ); ?></h2>
					<div class="rc-docs">
						<?php foreach ( $docs as $i => $d ) : ?>
							<a class="rc-doc" href="<?php echo esc_url( oyc_race_doc_url( $rid, $i ) ); ?>" target="_blank" rel="noopener">
								<span class="rc-doc__icon" aria-hidden="true"><?php echo 'application/pdf' === $d['mime'] ? 'PDF' : 'IMG'; ?></span>
								<span class="rc-doc__label"><?php echo esc_html( $d['label'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<h2 class="rc-h2"><?php /* translators: %d: boats */ echo esc_html( sprintf( __( 'Entries (%d boats)', 'orienta-yacht-club' ), count( $skippers ) ) ); ?></h2>
				<?php if ( $skippers ) : ?>
					<div class="rc-table-wrap">
						<table class="rc-table">
							<thead><tr>
								<th><?php esc_html_e( 'Boat', 'orienta-yacht-club' ); ?></th>
								<th><?php esc_html_e( 'Sail #', 'orienta-yacht-club' ); ?></th>
								<th><?php esc_html_e( 'Class', 'orienta-yacht-club' ); ?></th>
								<th><?php esc_html_e( 'PHRF', 'orienta-yacht-club' ); ?></th>
								<th><?php esc_html_e( 'Skipper', 'orienta-yacht-club' ); ?></th>
								<th><?php esc_html_e( 'Crew', 'orienta-yacht-club' ); ?></th>
								<?php if ( $can_rc ) : ?><th><?php esc_html_e( 'Phone · emergency', 'orienta-yacht-club' ); ?></th><?php endif; ?>
								<th></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $skippers as list( $e, $m ) ) : $uid = (int) $e->post_author; ?>
								<tr<?php echo 'pending' === $e->post_status ? ' class="is-pending"' : ''; ?>>
									<td><strong><?php echo esc_html( $m['boat'] ); ?></strong><?php if ( $m['need'] ) : ?> <span class="rc-badge rc-badge--warn"><?php esc_html_e( 'Wants crew', 'orienta-yacht-club' ); ?></span><?php endif; ?><?php if ( 'pending' === $e->post_status ) : ?> <span class="rc-badge"><?php esc_html_e( 'Awaiting approval', 'orienta-yacht-club' ); ?></span><?php endif; ?></td>
									<td><?php echo esc_html( $m['sail'] ); ?></td>
									<td><?php echo esc_html( $m['class'] ); ?></td>
									<td><?php echo esc_html( $m['rating'] ); ?></td>
									<td><?php echo esc_html( oyc_race_name( $uid ) ); ?><?php if ( oyc_is_guest_racer( $uid ) && get_user_meta( $uid, 'oyc_racer_club', true ) ) : ?> <span class="rc-muted">(<?php echo esc_html( get_user_meta( $uid, 'oyc_racer_club', true ) ); ?>)</span><?php endif; ?></td>
									<td><?php echo esc_html( $m['crew'] ); ?></td>
									<?php if ( $can_rc ) : ?>
										<td class="rc-small"><?php echo esc_html( get_user_meta( $uid, 'oyc_phone', true ) ); ?><br><?php echo esc_html( trim( get_user_meta( $uid, 'oyc_emergency_name', true ) . ' ' . get_user_meta( $uid, 'oyc_emergency_phone', true ) ) ); ?><?php if ( $m['notes'] ) : ?><br><em><?php echo esc_html( $m['notes'] ); ?></em><?php endif; ?></td>
									<?php endif; ?>
									<td><?php oyc_race_msg_button( $uid ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p class="rc-empty"><?php esc_html_e( 'No boats entered yet.', 'orienta-yacht-club' ); ?></p>
				<?php endif; ?>

				<?php if ( $crew ) : ?>
					<h2 class="rc-h2"><?php esc_html_e( 'Crew entered', 'orienta-yacht-club' ); ?></h2>
					<div class="rc-chips">
						<?php foreach ( $crew as list( $e, $m ) ) : $uid = (int) $e->post_author; ?>
							<div class="rc-chip<?php echo $m['need'] ? ' rc-chip--need' : ''; ?>">
								<span class="rc-chip__name"><?php echo esc_html( oyc_race_name( $uid ) ); ?></span>
								<?php if ( $m['need'] ) : ?><span class="rc-chip__sub"><?php esc_html_e( 'Looking for a boat', 'orienta-yacht-club' ); ?></span><?php endif; ?>
								<?php $pos = oyc_race_positions_label( get_user_meta( $uid, 'oyc_crew_positions', true ) ); if ( $pos ) : ?><span class="rc-chip__sub"><?php echo esc_html( $pos ); ?></span><?php endif; ?>
								<?php if ( $can_rc ) : $em = trim( get_user_meta( $uid, 'oyc_emergency_name', true ) . ' ' . get_user_meta( $uid, 'oyc_emergency_phone', true ) ); ?><span class="rc-chip__sub"><?php echo esc_html( get_user_meta( $uid, 'oyc_phone', true ) . ( '' !== $em ? ' · ' . __( 'Emergency:', 'orienta-yacht-club' ) . ' ' . $em : '' ) ); ?></span><?php endif; ?>
								<?php if ( 'pending' === $e->post_status ) : ?><span class="rc-badge"><?php esc_html_e( 'Awaiting approval', 'orienta-yacht-club' ); ?></span><?php endif; ?>
								<?php oyc_race_msg_button( $uid ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php $ads = array_filter( oyc_race_ads( $rid ), function ( $a ) { return ! get_post_meta( $a->ID, '_closed', true ); } );
				if ( $ads ) : ?>
					<h2 class="rc-h2"><?php esc_html_e( 'Crew board for this race', 'orienta-yacht-club' ); ?></h2>
					<ul class="rc-list">
						<?php foreach ( $ads as $a ) : ?>
							<li><span class="rc-tag rc-tag--<?php echo esc_attr( get_post_meta( $a->ID, '_type', true ) ); ?>"><?php echo esc_html( 'boat' === get_post_meta( $a->ID, '_type', true ) ? __( 'Boat wanted', 'orienta-yacht-club' ) : __( 'Crew wanted', 'orienta-yacht-club' ) ); ?></span>
								<a href="<?php echo esc_url( $tab_url( 'board' ) . '#ad-' . $a->ID ); ?>"><?php echo esc_html( $a->post_title ); ?></a> <span class="rc-muted"><?php echo esc_html( oyc_race_name( $a->post_author ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; // racer ?>

			<!-- RSVP -->
			<h2 class="rc-h2" id="rsvp"><?php echo esc_html( $mine ? __( 'Your RSVP', 'orienta-yacht-club' ) : __( 'RSVP', 'orienta-yacht-club' ) ); ?></h2>
			<?php if ( ! $logged_in ) : ?>
				<div class="rc-card">
					<p class="rc-p"><?php esc_html_e( 'Members: log in to RSVP. Visiting sailors: use the sign-up form on the Racing page.', 'orienta-yacht-club' ); ?></p>
					<a class="btn btn-primary" href="<?php echo esc_url( wp_login_url( $tab_url( 'race', array( 'race' => $rid ) ) ) ); ?>"><?php esc_html_e( 'Log in', 'orienta-yacht-club' ); ?></a>
					<a class="btn rc-btn" href="<?php echo esc_url( oyc_race_url() . '#signup' ); ?>"><?php esc_html_e( 'Visiting sailor sign-up', 'orienta-yacht-club' ); ?></a>
				</div>
			<?php elseif ( ! $open && ! $can_rc ) : ?>
				<p class="rc-empty"><?php echo esc_html( $mine ? __( 'You’re entered. RSVPs are now closed, so contact the Race Committee about any changes.', 'orienta-yacht-club' ) : __( 'RSVPs for this race are closed.', 'orienta-yacht-club' ) ); ?></p>
			<?php else : ?>
				<div class="rc-card">
					<?php if ( $mine && 'pending' === $mine->post_status ) : ?>
						<p class="rc-note"><?php esc_html_e( 'Your entry is waiting for a race officer to approve it.', 'orienta-yacht-club' ); ?></p>
					<?php endif; ?>
					<form class="rc-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
						<input type="hidden" name="action" value="oyc_race_rsvp">
						<input type="hidden" name="race_id" value="<?php echo (int) $rid; ?>">
						<?php wp_nonce_field( 'oyc_race_rsvp' ); ?>
						<?php
						$prefill = $mine ? oyc_race_entry_meta( $mine ) : array();
						if ( ! $mine && $my_yachts ) {
							$y       = $my_yachts[0];
							$prefill = array( 'boat' => $y->post_title, 'sail' => get_post_meta( $y->ID, '_sail', true ), 'class' => get_post_meta( $y->ID, '_class', true ), 'rating' => get_post_meta( $y->ID, '_rating', true ) );
						}
						$rsvp_fields( $prefill, $my_contact );
						?>
						<div class="rc-actions">
							<button type="submit" class="btn btn-primary"><?php echo esc_html( $mine ? __( 'Update my RSVP', 'orienta-yacht-club' ) : __( 'RSVP to this race', 'orienta-yacht-club' ) ); ?></button>
						</div>
					</form>
					<?php if ( $mine ) : ?>
						<div class="rc-actions"><?php oyc_race_post_button( 'oyc_race_withdraw', array( 'entry_id' => $mine->ID ), __( 'Cancel my RSVP', 'orienta-yacht-club' ), 'rc-btn rc-btn--danger', __( 'Cancel your RSVP for this race?', 'orienta-yacht-club' ) ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		<?php
		/* ══════════════════════════════════════════════════════════════════════
		 * CREW BOARD
		 * ══════════════════════════════════════════════════════════════════════ */
		elseif ( 'board' === $view ) :
			$ads     = oyc_race_ads();
			$edit_ad = isset( $_GET['ad_edit'] ) ? get_post( (int) $_GET['ad_edit'] ) : null;
			if ( $edit_ad && ! oyc_race_can_edit_post( $edit_ad, 'oyc_crew_ad' ) ) {
				$edit_ad = null;
			}
			$fa = array(
				'type'  => $edit_ad ? get_post_meta( $edit_ad->ID, '_type', true ) : ( isset( $_GET['type'] ) && 'boat' === $_GET['type'] ? 'boat' : 'crew' ),
				'title' => $edit_ad ? $edit_ad->post_title : '',
				'desc'  => $edit_ad ? $edit_ad->post_content : '',
				'race'  => $edit_ad ? (int) get_post_meta( $edit_ad->ID, '_race', true ) : 0,
				'pos'   => $edit_ad ? (array) get_post_meta( $edit_ad->ID, '_positions', true ) : array(),
			);
			$n = array( 'crew' => 0, 'boat' => 0, 'mine' => 0 );
			foreach ( $ads as $a ) {
				$n[ 'boat' === get_post_meta( $a->ID, '_type', true ) ? 'boat' : 'crew' ]++;
				if ( (int) $a->post_author === $me->ID ) {
					$n['mine']++;
				}
			}
			?>
			<div class="rc-toolbar">
				<div class="rc-filters" role="toolbar">
					<button type="button" class="rc-filter is-active" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'orienta-yacht-club' ); ?> <span><?php echo count( $ads ); ?></span></button>
					<button type="button" class="rc-filter" data-filter="crew" aria-pressed="false"><?php esc_html_e( 'Crew wanted', 'orienta-yacht-club' ); ?> <span><?php echo (int) $n['crew']; ?></span></button>
					<button type="button" class="rc-filter" data-filter="boat" aria-pressed="false"><?php esc_html_e( 'Boat wanted', 'orienta-yacht-club' ); ?> <span><?php echo (int) $n['boat']; ?></span></button>
					<?php if ( $n['mine'] ) : ?><button type="button" class="rc-filter" data-filter="mine" aria-pressed="false"><?php esc_html_e( 'My posts', 'orienta-yacht-club' ); ?> <span><?php echo (int) $n['mine']; ?></span></button><?php endif; ?>
				</div>
				<a class="btn btn-primary rc-new" href="#ad-form"<?php echo $edit_ad ? ' hidden' : ''; ?>><?php esc_html_e( '+ Post on the crew board', 'orienta-yacht-club' ); ?></a>
			</div>

			<details class="rc-compose" id="ad-form"<?php echo ( $edit_ad || 'admissing' === $notice ) ? ' open' : ''; ?>>
				<summary><?php echo esc_html( $edit_ad ? __( 'Edit post', 'orienta-yacht-club' ) : __( 'Post on the crew board', 'orienta-yacht-club' ) ); ?> <span class="rc-close"><?php esc_html_e( 'Close', 'orienta-yacht-club' ); ?></span></summary>
				<form class="rc-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
					<input type="hidden" name="action" value="oyc_race_ad">
					<?php if ( $edit_ad ) : ?><input type="hidden" name="ad_id" value="<?php echo (int) $edit_ad->ID; ?>"><?php endif; ?>
					<?php wp_nonce_field( 'oyc_race_ad' ); ?>
					<div class="rc-seg" role="radiogroup">
						<label><input type="radio" name="oyc_type" value="crew" <?php checked( 'boat' !== $fa['type'] ); ?>> <span><?php esc_html_e( 'My boat needs crew', 'orienta-yacht-club' ); ?></span></label>
						<label><input type="radio" name="oyc_type" value="boat" <?php checked( 'boat' === $fa['type'] ); ?>> <span><?php esc_html_e( 'I’m looking for a boat', 'orienta-yacht-club' ); ?></span></label>
					</div>
					<div class="rc-grid">
						<label class="rc-field rc-field--wide"><span><?php esc_html_e( 'Headline', 'orienta-yacht-club' ); ?></span>
							<input type="text" name="oyc_title" maxlength="90" required value="<?php echo esc_attr( $fa['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. J/24 needs bow and trimmer for Saturday', 'orienta-yacht-club' ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Race', 'orienta-yacht-club' ); ?></span>
							<select name="oyc_race">
								<option value="0"><?php esc_html_e( 'Any race / the season', 'orienta-yacht-club' ); ?></option>
								<?php foreach ( $upcoming as $r ) : ?>
									<option value="<?php echo (int) $r->ID; ?>" <?php selected( $fa['race'], $r->ID ); ?>><?php echo esc_html( $r->post_title . ' — ' . oyc_race_fmt_date( get_post_meta( $r->ID, '_oyc_race_date', true ) ) ); ?></option>
								<?php endforeach; ?>
							</select></label>
						<fieldset class="rc-field rc-field--full rc-pos"><legend><?php esc_html_e( 'Positions', 'orienta-yacht-club' ); ?></legend>
							<?php foreach ( oyc_race_positions() as $k => $label ) : ?>
								<label><input type="checkbox" name="oyc_positions[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $fa['pos'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</fieldset>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Details', 'orienta-yacht-club' ); ?></span>
							<textarea name="oyc_desc" rows="4" maxlength="1500" required placeholder="<?php esc_attr_e( 'Experience wanted, boat, dock time, how many races…', 'orienta-yacht-club' ); ?>"><?php echo esc_textarea( $fa['desc'] ); ?></textarea></label>
					</div>
					<div class="rc-actions">
						<button type="submit" class="btn btn-primary"><?php echo esc_html( $edit_ad ? __( 'Save changes', 'orienta-yacht-club' ) : __( 'Post it', 'orienta-yacht-club' ) ); ?></button>
						<?php if ( $edit_ad ) : ?><a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'board' ) ); ?>"><?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?></a><?php endif; ?>
						<span class="rc-note"><?php esc_html_e( 'Only racers can see the board. Replies come to you by email; your address isn’t shown.', 'orienta-yacht-club' ); ?></span>
					</div>
				</form>
			</details>

			<?php if ( $ads ) : ?>
				<div class="rc-ads">
					<?php foreach ( $ads as $a ) :
						$type   = 'boat' === get_post_meta( $a->ID, '_type', true ) ? 'boat' : 'crew';
						$closed = (bool) get_post_meta( $a->ID, '_closed', true );
						$ar     = oyc_race_get( (int) get_post_meta( $a->ID, '_race', true ) );
						$pos    = oyc_race_positions_label( get_post_meta( $a->ID, '_positions', true ) );
						$own    = (int) $a->post_author === $me->ID;
						$club   = get_user_meta( $a->post_author, 'oyc_racer_club', true );
						?>
						<article class="rc-ad rc-ad--<?php echo esc_attr( $type ); ?><?php echo $closed ? ' is-closed' : ''; ?>" id="ad-<?php echo (int) $a->ID; ?>" data-type="<?php echo esc_attr( $type ); ?>"<?php echo $own ? ' data-mine="1"' : ''; ?>>
							<div class="rc-ad__top">
								<span class="rc-tag rc-tag--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $closed ? __( 'Filled', 'orienta-yacht-club' ) : ( 'boat' === $type ? __( 'Boat wanted', 'orienta-yacht-club' ) : __( 'Crew wanted', 'orienta-yacht-club' ) ) ); ?></span>
								<span class="rc-muted"><?php echo esc_html( $ar ? $ar->post_title . ' · ' . oyc_race_fmt_date( get_post_meta( $ar->ID, '_oyc_race_date', true ) ) : __( 'Any race', 'orienta-yacht-club' ) ); ?></span>
							</div>
							<div class="rc-ad__title"><?php echo esc_html( $a->post_title ); ?></div>
							<?php if ( $pos ) : ?><div class="rc-ad__pos"><?php echo esc_html( $pos ); ?></div><?php endif; ?>
							<div class="rc-ad__desc"><?php echo nl2br( esc_html( $a->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
							<div class="rc-ad__meta"><?php echo esc_html( oyc_race_name( $a->post_author ) . ( oyc_is_guest_racer( $a->post_author ) && $club ? ' (' . $club . ')' : '' ) . ' · ' . get_the_date( 'M j', $a ) ); ?></div>
							<div class="rc-ad__actions">
								<?php if ( ! $own && ! $closed ) { oyc_race_msg_button( $a->post_author, __( 'Reply', 'orienta-yacht-club' ), $a->ID ); } ?>
								<?php if ( $own || $can_rc ) : ?>
									<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'board', array( 'ad_edit' => $a->ID ) ) . '#ad-form' ); ?>"><?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?></a>
									<?php oyc_race_post_button( 'oyc_race_ad', array( 'ad_id' => $a->ID, 'do' => 'close' ), $closed ? __( 'Reopen', 'orienta-yacht-club' ) : __( 'Mark filled', 'orienta-yacht-club' ) ); ?>
									<?php oyc_race_post_button( 'oyc_race_ad', array( 'ad_id' => $a->ID, 'do' => 'delete' ), __( 'Delete', 'orienta-yacht-club' ), 'rc-btn rc-btn--danger', __( 'Delete this post?', 'orienta-yacht-club' ) ); ?>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<p class="rc-empty rc-empty--filtered" hidden><?php esc_html_e( 'Nothing here yet.', 'orienta-yacht-club' ); ?></p>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'The crew board is empty. Need crew, or looking for a ride? Post it here.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

		<?php
		/* ══════════════════════════════════════════════════════════════════════
		 * SAILORS & YACHTS
		 * ══════════════════════════════════════════════════════════════════════ */
		elseif ( 'sailors' === $view ) :
			$people = oyc_race_crew_profiles();
			$yachts = array_filter( oyc_race_yachts(), function ( $y ) { return ! get_post_meta( $y->ID, '_hidden', true ); } );
			$levels = oyc_race_levels();
			?>
			<h2 class="rc-h2"><?php esc_html_e( 'Yachts', 'orienta-yacht-club' ); ?></h2>
			<?php if ( $yachts ) : ?>
				<div class="rc-cards">
					<?php foreach ( $yachts as $y ) :
						$photo = (int) get_post_meta( $y->ID, '_oyc_bb_photo', true );
						$img   = $photo ? wp_get_attachment_image_url( $photo, 'medium_large' ) : '';
						$facts = array_filter( array(
							get_post_meta( $y->ID, '_class', true ),
							get_post_meta( $y->ID, '_sail', true ) ? '#' . get_post_meta( $y->ID, '_sail', true ) : '',
							get_post_meta( $y->ID, '_rating', true ) ? 'PHRF ' . get_post_meta( $y->ID, '_rating', true ) : '',
							get_post_meta( $y->ID, '_loa', true ) ? get_post_meta( $y->ID, '_loa', true ) . ' LOA' : '',
						) );
						?>
						<article class="rc-pcard">
							<?php if ( $img ) : ?><div class="rc-pcard__img" style="background-image:url(<?php echo esc_url( $img ); ?>)"></div><?php endif; ?>
							<div class="rc-pcard__body">
								<div class="rc-pcard__name"><?php echo esc_html( $y->post_title ); ?></div>
								<?php if ( $facts ) : ?><div class="rc-pcard__facts"><?php echo esc_html( implode( ' · ', $facts ) ); ?></div><?php endif; ?>
								<div class="rc-pcard__sub"><?php /* translators: %s: name */ echo esc_html( sprintf( __( 'Skipper: %s', 'orienta-yacht-club' ), oyc_race_name( $y->post_author ) ) . ( get_post_meta( $y->ID, '_club', true ) ? ' · ' . get_post_meta( $y->ID, '_club', true ) : '' ) ); ?></div>
								<?php if ( $y->post_content ) : ?><div class="rc-pcard__about"><?php echo nl2br( esc_html( $y->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
								<div class="rc-pcard__actions">
									<?php oyc_race_msg_button( $y->post_author, __( 'Message skipper', 'orienta-yacht-club' ), $y->ID ); ?>
									<?php if ( (int) $y->post_author === $me->ID || $can_rc ) : ?>
										<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'me', (int) $y->post_author === $me->ID ? array( 'yedit' => $y->ID ) : array( 'yedit' => $y->ID, 'uid' => $y->post_author ) ) . '#yacht-form' ); ?>"><?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?></a>
									<?php endif; ?>
								</div>
								<?php do_action( 'oyc_race_yacht_profile_extra', $y ); ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'No yacht profiles yet. Skippers can add one under My profile.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

			<h2 class="rc-h2"><?php esc_html_e( 'Crew', 'orienta-yacht-club' ); ?></h2>
			<?php if ( $people ) : ?>
				<div class="rc-cards">
					<?php foreach ( $people as $u ) :
						$lv   = (string) get_user_meta( $u->ID, 'oyc_crew_level', true );
						$club = oyc_is_guest_racer( $u->ID ) ? (string) get_user_meta( $u->ID, 'oyc_racer_club', true ) : 'Orienta YC';
						?>
						<article class="rc-pcard rc-pcard--crew">
							<div class="rc-pcard__body">
								<div class="rc-pcard__name"><?php echo esc_html( oyc_race_name( $u->ID ) ); ?></div>
								<div class="rc-pcard__facts"><?php echo esc_html( implode( ' · ', array_filter( array( $club, $levels[ $lv ] ?? '' ) ) ) ); ?></div>
								<div class="rc-pcard__sub"><?php echo esc_html( oyc_race_positions_label( get_user_meta( $u->ID, 'oyc_crew_positions', true ) ) ); ?></div>
								<?php if ( get_user_meta( $u->ID, 'oyc_crew_about', true ) ) : ?><div class="rc-pcard__about"><?php echo nl2br( esc_html( get_user_meta( $u->ID, 'oyc_crew_about', true ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
								<div class="rc-pcard__actions">
									<?php oyc_race_msg_button( $u->ID ); ?>
									<?php if ( $u->ID === $me->ID || $can_rc ) : ?>
										<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'me', $u->ID === $me->ID ? array() : array( 'uid' => $u->ID ) ) . '#profile' ); ?>"><?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'No crew profiles yet. Add yours under My profile so skippers can find you.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

		<?php
		/* ══════════════════════════════════════════════════════════════════════
		 * MY PROFILE
		 * ══════════════════════════════════════════════════════════════════════ */
		elseif ( 'me' === $view ) :
			// The Race Committee can open anyone's profile with &uid=.
			$who_id = $me->ID;
			if ( $can_rc && ! empty( $_GET['uid'] ) && get_userdata( (int) $_GET['uid'] ) ) {
				$who_id = (int) $_GET['uid'];
			}
			$other       = $who_id !== $me->ID;
			$who_contact = array();
			foreach ( array_keys( $my_contact ) as $k ) {
				$who_contact[ $k ] = (string) get_user_meta( $who_id, $k, true );
			}
			$who_yachts = $other ? oyc_race_yachts( $who_id ) : $my_yachts;
			$my_pos   = (array) get_user_meta( $who_id, 'oyc_crew_positions', true );
			$my_level = (string) get_user_meta( $who_id, 'oyc_crew_level', true );
			$y_edit   = isset( $_GET['yedit'] ) ? get_post( (int) $_GET['yedit'] ) : null;
			if ( $y_edit && ! oyc_race_can_edit_post( $y_edit, 'oyc_yacht' ) ) {
				$y_edit = null;
			}
			$my_entries = oyc_race_user_entries( $who_id );
			?>
			<?php if ( $other ) : ?>
				<div class="rc-notice rc-notice--ok"><?php /* translators: %s: name */ echo esc_html( sprintf( __( 'You are editing %s’s racing profile (Race Committee).', 'orienta-yacht-club' ), oyc_race_name( $who_id ) ) ); ?> <a href="<?php echo esc_url( $tab_url( 'sailors' ) ); ?>"><?php esc_html_e( 'Back to Sailors & yachts', 'orienta-yacht-club' ); ?></a></div>
			<?php endif; ?>
			<h2 class="rc-h2"><?php echo esc_html( $other ? __( 'Races', 'orienta-yacht-club' ) : __( 'My races', 'orienta-yacht-club' ) ); ?></h2>
			<?php if ( $my_entries ) : ?>
				<ul class="rc-list">
					<?php foreach ( $my_entries as $e ) : $er = get_post( $e->post_parent ); if ( ! $er ) { continue; } $m = oyc_race_entry_meta( $e ); ?>
						<li><a href="<?php echo esc_url( $tab_url( 'race', array( 'race' => $er->ID ) ) ); ?>"><?php echo esc_html( $er->post_title ); ?></a>
							<span class="rc-muted"><?php echo esc_html( oyc_race_fmt_date( get_post_meta( $er->ID, '_oyc_race_date', true ) ) . ' · ' . ( 'crew' === $m['role'] ? __( 'Crew', 'orienta-yacht-club' ) : sprintf( /* translators: %s boat */ __( 'Skipper, %s', 'orienta-yacht-club' ), $m['boat'] ) ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'You haven’t entered any races yet.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>
			<?php $until = (string) get_user_meta( $who_id, 'oyc_racer_until', true ); if ( $until ) : ?>
				<p class="rc-note"><?php /* translators: %s: date */ echo esc_html( sprintf( __( 'Your racers’ area access runs through %s.', 'orienta-yacht-club' ), oyc_race_fmt_date( $until ) ) ); ?></p>
			<?php endif; ?>

			<h2 class="rc-h2" id="profile"><?php esc_html_e( 'Crew profile & contact details', 'orienta-yacht-club' ); ?></h2>
			<div class="rc-card">
				<form class="rc-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
					<input type="hidden" name="action" value="oyc_race_profile">
					<?php if ( $other ) : ?><input type="hidden" name="user_id" value="<?php echo (int) $who_id; ?>"><?php endif; ?>
					<?php wp_nonce_field( 'oyc_race_profile' ); ?>
					<fieldset class="rc-field rc-pos"><legend><?php esc_html_e( 'Positions I sail', 'orienta-yacht-club' ); ?></legend>
						<?php foreach ( oyc_race_positions() as $k => $label ) : ?>
							<label><input type="checkbox" name="oyc_positions[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $my_pos, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<div class="rc-grid">
						<label class="rc-field"><span><?php esc_html_e( 'Experience', 'orienta-yacht-club' ); ?></span>
							<select name="oyc_level"><option value=""><?php esc_html_e( '—', 'orienta-yacht-club' ); ?></option>
								<?php foreach ( oyc_race_levels() as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $my_level, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
							</select></label>
						<?php if ( oyc_is_guest_racer( $who_id ) ) : ?>
							<label class="rc-field"><span><?php esc_html_e( 'Your club', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_racer_club" maxlength="80" value="<?php echo esc_attr( $who_contact['oyc_racer_club'] ); ?>"></label>
						<?php endif; ?>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'About me as a sailor', 'orienta-yacht-club' ); ?></span>
							<textarea name="oyc_about" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'Boats you’ve sailed, regattas, what you’d like to learn…', 'orienta-yacht-club' ); ?>"><?php echo esc_textarea( get_user_meta( $who_id, 'oyc_crew_about', true ) ); ?></textarea></label>
						<label class="rc-field"><span><?php esc_html_e( 'Mobile phone', 'orienta-yacht-club' ); ?></span><input type="tel" name="oyc_phone" maxlength="30" value="<?php echo esc_attr( $who_contact['oyc_phone'] ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Emergency contact', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_emergency_name" maxlength="80" value="<?php echo esc_attr( $who_contact['oyc_emergency_name'] ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Emergency phone', 'orienta-yacht-club' ); ?></span><input type="tel" name="oyc_emergency_phone" maxlength="30" value="<?php echo esc_attr( $who_contact['oyc_emergency_phone'] ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Relationship', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_emergency_relationship" maxlength="40" value="<?php echo esc_attr( $who_contact['oyc_emergency_relationship'] ); ?>"></label>
					</div>
					<label class="rc-check"><input type="checkbox" name="oyc_hidden" value="1" <?php checked( (bool) get_user_meta( $who_id, 'oyc_crew_hidden', true ) ); ?>> <?php esc_html_e( 'Hide my crew profile from other racers', 'orienta-yacht-club' ); ?></label>
					<p class="rc-note"><?php esc_html_e( 'Racers see your name, positions, experience and “about me”. Your phone and emergency contact are seen only by the Race Committee. Your profile stays up until you hide it.', 'orienta-yacht-club' ); ?></p>
					<div class="rc-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save profile', 'orienta-yacht-club' ); ?></button></div>
				</form>
			</div>

			<h2 class="rc-h2" id="yachts"><?php esc_html_e( 'My yachts', 'orienta-yacht-club' ); ?></h2>
			<?php if ( $who_yachts ) : ?>
				<ul class="rc-list">
					<?php foreach ( $who_yachts as $y ) : ?>
						<li><strong><?php echo esc_html( $y->post_title ); ?></strong> <span class="rc-muted"><?php echo esc_html( implode( ' · ', array_filter( array( get_post_meta( $y->ID, '_class', true ), get_post_meta( $y->ID, '_sail', true ) ) ) ) ); ?></span>
							<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'me', $other ? array( 'yedit' => $y->ID, 'uid' => $who_id ) : array( 'yedit' => $y->ID ) ) . '#yacht-form' ); ?>"><?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! $other || $y_edit ) : ?>
			<details class="rc-compose" id="yacht-form"<?php echo ( $y_edit || ! $who_yachts ) ? ' open' : ''; ?>>
				<summary><?php echo esc_html( $y_edit ? __( 'Edit yacht', 'orienta-yacht-club' ) : __( 'Add a yacht', 'orienta-yacht-club' ) ); ?> <span class="rc-close"><?php esc_html_e( 'Close', 'orienta-yacht-club' ); ?></span></summary>
				<form class="rc-form" method="post" enctype="multipart/form-data" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
					<input type="hidden" name="action" value="oyc_race_yacht">
					<?php if ( $y_edit ) : ?><input type="hidden" name="yacht_id" value="<?php echo (int) $y_edit->ID; ?>"><?php endif; ?>
					<?php wp_nonce_field( 'oyc_race_yacht' ); ?>
					<?php $yv = function ( $k ) use ( $y_edit ) { return $y_edit ? (string) get_post_meta( $y_edit->ID, '_' . $k, true ) : ''; }; ?>
					<div class="rc-grid">
						<label class="rc-field rc-field--wide"><span><?php esc_html_e( 'Boat name', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_boat" maxlength="60" required value="<?php echo esc_attr( $y_edit ? $y_edit->post_title : '' ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Sail number', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_sail" maxlength="20" value="<?php echo esc_attr( $yv( 'sail' ) ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Class / design', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_class" maxlength="40" value="<?php echo esc_attr( $yv( 'class' ) ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'PHRF rating', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_rating" maxlength="12" value="<?php echo esc_attr( $yv( 'rating' ) ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Length (LOA)', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_loa" maxlength="12" value="<?php echo esc_attr( $yv( 'loa' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. 30 ft', 'orienta-yacht-club' ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Home club', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_club" maxlength="80" value="<?php echo esc_attr( $yv( 'club' ) ); ?>"></label>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'About the boat', 'orienta-yacht-club' ); ?></span><textarea name="oyc_about" rows="3" maxlength="1500"><?php echo esc_textarea( $y_edit ? $y_edit->post_content : '' ); ?></textarea></label>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Photo (optional)', 'orienta-yacht-club' ); ?></span><input type="file" name="oyc_bb_photo" accept="image/*"></label>
					</div>
					<label class="rc-check"><input type="checkbox" name="oyc_hidden" value="1" <?php checked( $y_edit && get_post_meta( $y_edit->ID, '_hidden', true ) ); ?>> <?php esc_html_e( 'Hide this yacht from other racers', 'orienta-yacht-club' ); ?></label>
					<div class="rc-actions">
						<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save yacht', 'orienta-yacht-club' ); ?></button>
						<?php if ( $y_edit ) : ?>
							<button type="submit" name="oyc_delete" value="1" class="btn btn-sm rc-btn rc-btn--danger" formnovalidate onclick="return confirm('<?php echo esc_js( __( 'Remove this yacht?', 'orienta-yacht-club' ) ); ?>');"><?php esc_html_e( 'Remove yacht', 'orienta-yacht-club' ); ?></button>
						<?php endif; ?>
					</div>
				</form>
			</details>
			<?php endif; ?>

		<?php
		/* ══════════════════════════════════════════════════════════════════════
		 * RACE COMMITTEE
		 * ══════════════════════════════════════════════════════════════════════ */
		elseif ( 'committee' === $view ) :
			$pending = oyc_race_pending_racers();
			$edit    = isset( $_GET['edit'] ) ? oyc_race_get( (int) $_GET['edit'] ) : null;
			$ev      = function ( $k ) use ( $edit ) { return $edit ? (string) get_post_meta( $edit->ID, '_oyc_race_' . $k, true ) : ''; };
			$aud_sel = isset( $_GET['aud'] ) ? sanitize_text_field( wp_unslash( $_GET['aud'] ) ) : 'season';
			$log     = (array) get_option( 'oyc_race_mail_log', array() );
			?>
			<h2 class="rc-h2" id="pending"><?php /* translators: %d: number */ echo esc_html( sprintf( __( 'Visiting sailors to approve (%d)', 'orienta-yacht-club' ), count( $pending ) ) ); ?></h2>
			<?php if ( $pending ) : ?>
				<div class="rc-table-wrap">
					<table class="rc-table">
						<thead><tr><th><?php esc_html_e( 'Name', 'orienta-yacht-club' ); ?></th><th><?php esc_html_e( 'Club', 'orienta-yacht-club' ); ?></th><th><?php esc_html_e( 'Contact', 'orienta-yacht-club' ); ?></th><th><?php esc_html_e( 'Entry', 'orienta-yacht-club' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php foreach ( $pending as $u ) : ?>
							<tr>
								<td><strong><?php echo esc_html( oyc_race_name( $u->ID ) ); ?></strong><br><span class="rc-muted"><?php echo esc_html( human_time_diff( strtotime( $u->user_registered ) ) . ' ' . __( 'ago', 'orienta-yacht-club' ) ); ?></span></td>
								<td><?php echo esc_html( get_user_meta( $u->ID, 'oyc_racer_club', true ) ); ?></td>
								<td class="rc-small"><?php echo esc_html( $u->user_email ); ?><br><?php echo esc_html( get_user_meta( $u->ID, 'oyc_phone', true ) ); ?></td>
								<td class="rc-small"><?php foreach ( oyc_race_user_entries( $u->ID ) as $e ) { $m = oyc_race_entry_meta( $e ); echo esc_html( get_the_title( $e->post_parent ) . ': ' . ( 'crew' === $m['role'] ? __( 'crew', 'orienta-yacht-club' ) : __( 'skipper of', 'orienta-yacht-club' ) . ' ' . $m['boat'] . ( $m['sail'] ? ' #' . $m['sail'] : '' ) ) ); echo '<br>'; } ?></td>
								<td class="rc-row-actions">
									<?php oyc_race_post_button( 'oyc_race_review', array( 'user_id' => $u->ID, 'do' => 'approve' ), __( 'Approve', 'orienta-yacht-club' ), 'btn-primary' ); ?>
									<?php oyc_race_post_button( 'oyc_race_review', array( 'user_id' => $u->ID, 'do' => 'decline' ), __( 'Decline', 'orienta-yacht-club' ), 'rc-btn rc-btn--danger', __( 'Decline and remove this sign-up?', 'orienta-yacht-club' ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'Nobody is waiting.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>

			<h2 class="rc-h2" id="email"><?php esc_html_e( 'Email racers', 'orienta-yacht-club' ); ?></h2>
			<div class="rc-card">
				<form class="rc-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Send this email now?', 'orienta-yacht-club' ) ); ?>');">
					<input type="hidden" name="action" value="oyc_race_email">
					<?php wp_nonce_field( 'oyc_race_email' ); ?>
					<div class="rc-grid">
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Send to', 'orienta-yacht-club' ); ?></span>
							<select name="oyc_aud">
								<option value="season" <?php selected( $aud_sel, 'season' ); ?>><?php /* translators: %d: number */ echo esc_html( sprintf( __( 'All racers this season (%d)', 'orienta-yacht-club' ), count( oyc_race_season_racers() ) ) ); ?></option>
								<?php foreach ( $upcoming as $r ) : foreach ( array( 'race', 'skippers', 'crew' ) as $k ) : $a = $k . ':' . $r->ID; ?>
									<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $aud_sel, $a ); ?>><?php echo esc_html( oyc_race_audience_label( $a ) . ' (' . count( oyc_race_audience( $a ) ) . ')' ); ?></option>
								<?php endforeach; endforeach; ?>
							</select></label>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Subject', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_subject" maxlength="140" required placeholder="<?php esc_attr_e( 'e.g. Saturday’s race: postponed one hour for wind', 'orienta-yacht-club' ); ?>"></label>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Message', 'orienta-yacht-club' ); ?></span><textarea name="oyc_message" rows="7" maxlength="8000" required></textarea></label>
					</div>
					<p class="rc-note"><?php esc_html_e( 'Racers get it by email with their addresses hidden from each other. Replies come back to you.', 'orienta-yacht-club' ); ?></p>
					<div class="rc-actions">
						<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Send email', 'orienta-yacht-club' ); ?></button>
						<a class="btn btn-sm rc-btn" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'oyc_race_csv' ), admin_url( 'admin-post.php' ) ), 'oyc_race_csv' ) ); ?>"><?php esc_html_e( 'Download all racers (CSV)', 'orienta-yacht-club' ); ?></a>
					</div>
				</form>
				<?php if ( $log ) : ?>
					<details class="rc-log"><summary><?php esc_html_e( 'Recent emails', 'orienta-yacht-club' ); ?></summary>
						<ul class="rc-list">
							<?php foreach ( $log as $l ) : ?>
								<li><strong><?php echo esc_html( $l['subject'] ); ?></strong> <span class="rc-muted"><?php echo esc_html( wp_date( 'M j, g:i a', (int) $l['time'] ) . ' · ' . $l['aud'] . ' · ' . sprintf( /* translators: 1 sent 2 total */ __( '%1$d of %2$d sent', 'orienta-yacht-club' ), (int) $l['sent'], (int) $l['total'] ) . ' · ' . oyc_race_name( (int) $l['by'] ) ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			</div>

			<h2 class="rc-h2" id="race-form"><?php echo esc_html( $edit ? __( 'Edit race', 'orienta-yacht-club' ) : __( 'Add a race', 'orienta-yacht-club' ) ); ?></h2>
			<div class="rc-card">
				<form class="rc-form" method="post" enctype="multipart/form-data" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
					<input type="hidden" name="action" value="oyc_race_save">
					<?php if ( $edit ) : ?><input type="hidden" name="race_id" value="<?php echo (int) $edit->ID; ?>"><?php endif; ?>
					<?php wp_nonce_field( 'oyc_race_save' ); ?>
					<div class="rc-grid">
						<label class="rc-field rc-field--wide"><span><?php esc_html_e( 'Race name', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_title" maxlength="100" required value="<?php echo esc_attr( $edit ? $edit->post_title : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Fall Series Race 3', 'orienta-yacht-club' ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'Status', 'orienta-yacht-club' ); ?></span>
							<select name="oyc_status">
								<option value=""><?php esc_html_e( 'On', 'orienta-yacht-club' ); ?></option>
								<option value="postponed" <?php selected( $ev( 'status' ), 'postponed' ); ?>><?php esc_html_e( 'Postponed', 'orienta-yacht-club' ); ?></option>
								<option value="cancelled" <?php selected( $ev( 'status' ), 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'orienta-yacht-club' ); ?></option>
							</select></label>
						<label class="rc-field"><span><?php esc_html_e( 'Date', 'orienta-yacht-club' ); ?></span><input type="date" name="oyc_date" required value="<?php echo esc_attr( $ev( 'date' ) ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'First warning signal', 'orienta-yacht-club' ); ?></span><input type="time" name="oyc_time" value="<?php echo esc_attr( $ev( 'time' ) ); ?>"></label>
						<label class="rc-field"><span><?php esc_html_e( 'RSVP deadline (optional)', 'orienta-yacht-club' ); ?></span><input type="date" name="oyc_deadline" value="<?php echo esc_attr( $ev( 'deadline' ) ); ?>"></label>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Course / area', 'orienta-yacht-club' ); ?></span><input type="text" name="oyc_area" maxlength="120" value="<?php echo esc_attr( $ev( 'area' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Western Long Island Sound, off Execution Rocks', 'orienta-yacht-club' ); ?>"></label>
						<label class="rc-field rc-field--full"><span><?php esc_html_e( 'Race details (racers only)', 'orienta-yacht-club' ); ?></span><textarea name="oyc_details" rows="7" maxlength="6000" placeholder="<?php esc_attr_e( 'Skippers’ meeting, VHF channel, start line, marks, classes, after-party…', 'orienta-yacht-club' ); ?>"><?php echo esc_textarea( $edit ? $edit->post_content : '' ); ?></textarea></label>
					</div>
					<fieldset class="rc-field rc-docs-edit"><legend><?php esc_html_e( 'Charts & documents (racers only)', 'orienta-yacht-club' ); ?></legend>
						<?php if ( $edit ) : foreach ( array_values( array_filter( (array) get_post_meta( $edit->ID, '_oyc_race_docs', true ), 'is_array' ) ) as $i => $d ) : ?>
							<label class="rc-check"><input type="checkbox" name="oyc_doc_remove[]" value="<?php echo (int) $i; ?>"> <?php /* translators: %s: label */ echo esc_html( sprintf( __( 'Remove “%s”', 'orienta-yacht-club' ), $d['label'] ) ); ?></label>
						<?php endforeach; endif; ?>
						<?php foreach ( array( __( 'Notice of Race', 'orienta-yacht-club' ), __( 'Sailing Instructions', 'orienta-yacht-club' ), __( 'Course chart', 'orienta-yacht-club' ) ) as $ph ) : ?>
							<div class="rc-doc-row">
								<input type="text" name="oyc_race_doc_label[]" maxlength="80" placeholder="<?php echo esc_attr( $ph ); ?>" aria-label="<?php esc_attr_e( 'Document name', 'orienta-yacht-club' ); ?>">
								<input type="file" name="oyc_race_doc[]" accept=".pdf,image/*">
							</div>
						<?php endforeach; ?>
						<span class="rc-note"><?php esc_html_e( 'PDF or image, up to 20 MB each. Only racers who are logged in can open them.', 'orienta-yacht-club' ); ?></span>
					</fieldset>
					<?php if ( ! $edit && function_exists( 'oyc_create_calendar_event' ) ) : ?>
						<label class="rc-check"><input type="checkbox" name="oyc_add_cal" value="1" checked> <?php esc_html_e( 'Also add it to the club calendar (name and time only)', 'orienta-yacht-club' ); ?></label>
					<?php endif; ?>
					<div class="rc-actions">
						<button type="submit" class="btn btn-primary"><?php echo esc_html( $edit ? __( 'Save race', 'orienta-yacht-club' ) : __( 'Add race', 'orienta-yacht-club' ) ); ?></button>
						<?php if ( $edit ) : ?><a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'committee' ) ); ?>"><?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?></a><?php endif; ?>
					</div>
				</form>
				<?php if ( $edit ) : ?>
					<div class="rc-actions"><?php oyc_race_post_button( 'oyc_race_delete', array( 'race_id' => $edit->ID ), __( 'Delete this race', 'orienta-yacht-club' ), 'rc-btn rc-btn--danger', __( 'Delete this race and all its RSVPs? This cannot be undone.', 'orienta-yacht-club' ) ); ?></div>
				<?php endif; ?>
			</div>

			<h2 class="rc-h2"><?php esc_html_e( 'All races', 'orienta-yacht-club' ); ?></h2>
			<?php $all = oyc_race_races( 'all' ); if ( $all ) : ?>
				<ul class="rc-list">
					<?php foreach ( array_reverse( $all ) as $r ) : ?>
						<li><a href="<?php echo esc_url( $tab_url( 'race', array( 'race' => $r->ID ) ) ); ?>"><?php echo esc_html( $r->post_title ); ?></a>
							<span class="rc-muted"><?php echo esc_html( oyc_race_fmt_date( get_post_meta( $r->ID, '_oyc_race_date', true ) ) . ' · ' . sprintf( /* translators: %d */ _n( '%d RSVP', '%d RSVPs', count( oyc_race_entries( $r->ID, 'any' ) ), 'orienta-yacht-club' ), count( oyc_race_entries( $r->ID, 'any' ) ) ) ); ?></span>
							<?php echo oyc_race_status_badge( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<a class="btn btn-sm rc-btn" href="<?php echo esc_url( $tab_url( 'committee', array( 'edit' => $r->ID ) ) . '#race-form' ); ?>"><?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="rc-empty"><?php esc_html_e( 'No races yet. Add the first one above.', 'orienta-yacht-club' ); ?></p>
			<?php endif; ?>
			<p class="rc-note"><?php esc_html_e( 'Officers and admins are on the Race Committee automatically. To add someone else, give them the “Race Committee” role on the Officer → Members page.', 'orienta-yacht-club' ); ?></p>
		<?php elseif ( $is_racer ) : ?>
			<?php do_action( 'oyc_race_view_' . $view ); // extra tabs added through the oyc_race_tabs filter. ?>
		<?php endif; ?>

		<?php if ( $is_racer ) : ?>
			<!-- Message another racer (shared dialog) -->
			<dialog class="rc-dialog" id="rc-msg">
				<form class="rc-form" method="post" action="<?php echo $post_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
					<input type="hidden" name="action" value="oyc_race_message">
					<input type="hidden" name="to" value="">
					<input type="hidden" name="about" value="">
					<input type="hidden" name="oyc_return" value="<?php echo esc_attr( add_query_arg( array() ) ); ?>">
					<?php wp_nonce_field( 'oyc_race_message' ); ?>
					<div class="rc-dialog__title"><?php esc_html_e( 'Message', 'orienta-yacht-club' ); ?> <span class="rc-dialog__to"></span></div>
					<label class="rc-field"><span class="screen-reader-text"><?php esc_html_e( 'Message', 'orienta-yacht-club' ); ?></span><textarea name="oyc_message" rows="6" maxlength="3000" required></textarea></label>
					<p class="rc-note"><?php esc_html_e( 'Sent by email. Your email address is included so they can reply to you.', 'orienta-yacht-club' ); ?></p>
					<div class="rc-actions">
						<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Send', 'orienta-yacht-club' ); ?></button>
						<button type="button" class="btn btn-sm rc-btn" value="cancel" data-close><?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?></button>
					</div>
				</form>
			</dialog>
		<?php endif; ?>

		<script>
		(function(){
			// Skipper / crew fields follow the radio choice.
			document.querySelectorAll('.rc-form').forEach(function(form){
				var radios = form.querySelectorAll('input[name="oyc_role"]');
				if (!radios.length) return;
				function sync(){
					var r = (form.querySelector('input[name="oyc_role"]:checked') || {}).value || 'skipper';
					form.querySelectorAll('[data-for]').forEach(function(el){
						var on = el.getAttribute('data-for') === r;
						el.hidden = !on;
						el.querySelectorAll('input').forEach(function(i){ i.disabled = !on; });
					});
					var boat = form.querySelector('input[name="oyc_boat"]');
					if (boat) boat.required = (r === 'skipper');
				}
				radios.forEach(function(x){ x.addEventListener('change', sync); });
				sync();
				// Picking one of my yachts fills in its sail number, class and rating.
				var boat = form.querySelector('input[name="oyc_boat"][list]'), dl = document.getElementById('rc-my-yachts');
				if (boat && dl) boat.addEventListener('change', function(){
					dl.querySelectorAll('option').forEach(function(o){
						if (o.value !== boat.value) return;
						['sail','class','rating'].forEach(function(k){ var f = form.querySelector('input[name="oyc_' + k + '"]'); if (f && o.getAttribute('data-' + k)) f.value = o.getAttribute('data-' + k); });
					});
				});
			});

			// RSVP buttons in the race list pick that race in the sign-up form.
			document.querySelectorAll('a[href="#signup"][data-race]').forEach(function(a){
				a.addEventListener('click', function(){ var s = document.querySelector('#signup select[name="race_id"]'); if (s) s.value = a.getAttribute('data-race'); });
			});

			// Crew board filters.
			var ads = document.querySelectorAll('.rc-ad'), empty = document.querySelector('.rc-empty--filtered');
			document.querySelectorAll('.rc-filter').forEach(function(b){
				b.addEventListener('click', function(){
					var f = b.getAttribute('data-filter'), shown = 0;
					document.querySelectorAll('.rc-filter').forEach(function(o){ var on = o === b; o.classList.toggle('is-active', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
					ads.forEach(function(c){ var ok = f === 'all' || (f === 'mine' ? c.hasAttribute('data-mine') : c.getAttribute('data-type') === f); c.hidden = !ok; if (ok) shown++; });
					if (empty) empty.hidden = shown > 0;
				});
			});
			var compose = document.getElementById('ad-form'), newBtn = document.querySelector('.rc-new');
			if (compose && newBtn) {
				newBtn.addEventListener('click', function(e){ e.preventDefault(); compose.open = true; newBtn.hidden = true; compose.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
				compose.addEventListener('toggle', function(){ newBtn.hidden = compose.open; });
			}

			// Message dialog.
			var dlg = document.getElementById('rc-msg');
			if (dlg && dlg.showModal) {
				document.querySelectorAll('.rc-msg').forEach(function(b){
					b.addEventListener('click', function(){
						dlg.querySelector('input[name="to"]').value = b.getAttribute('data-to');
						dlg.querySelector('input[name="about"]').value = b.getAttribute('data-about') || '';
						dlg.querySelector('.rc-dialog__to').textContent = b.getAttribute('data-name');
						dlg.showModal();
						dlg.querySelector('textarea').focus();
					});
				});
				dlg.querySelector('[data-close]').addEventListener('click', function(){ dlg.close(); });
			}
		})();
		</script>
	</div>
</section>

<?php get_footer(); ?>

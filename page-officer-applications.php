<?php
/**
 * Template Name: Officer — Applications
 * Membership applications from the OYC inbox, in the same expanding-card layout
 * as the Messages page.
 *
 * Reads the oyc_application CPT populated by inc/application-handler.php.
 *
 * @package Orienta_Yacht_Club
 */

require_once get_template_directory() . '/inc/officer-admin.php';

oyc_officer_guard( 'oyc_manage_applications' );

/* ── Actions ──────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_app_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_app_nonce'] ) ), 'oyc_app_action' ) ) {

	$app_id = (int) ( $_POST['app_id'] ?? 0 );

	if ( $app_id && 'oyc_application' !== get_post_type( $app_id ) ) {
		$app_id = 0;
	}

	if ( $app_id && 'delete' === sanitize_key( wp_unslash( $_POST['app_action'] ?? '' ) ) ) {
		$who = trim(
			get_post_meta( $app_id, 'oyc_app_first_name', true ) . ' ' .
			get_post_meta( $app_id, 'oyc_app_last_name', true )
		);
		wp_delete_post( $app_id, true );
		oyc_audit_log( 'application.delete', sprintf( 'Deleted application from %s', $who ), $app_id );
		oyc_officer_notice( __( 'Application deleted.', 'orienta-yacht-club' ) );
	}
}

/* ── Data for the view ────────────────────────────────────────────────────── */

$oyc_search = sanitize_text_field( wp_unslash( $_GET['aq'] ?? '' ) );
$oyc_paged  = max( 1, (int) ( $_GET['apage'] ?? 1 ) );

$oyc_args = array(
	'post_type'      => 'oyc_application',
	'post_status'    => 'publish',
	'posts_per_page' => 25,
	'paged'          => $oyc_paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

if ( $oyc_search ) {
	$oyc_args['s'] = $oyc_search;
}

$oyc_query = new WP_Query( $oyc_args );

/**
 * Field groups shown inside an expanded application.
 * Empty values are skipped when rendering.
 */
$oyc_app_groups = array(
	__( 'Contact', 'orienta-yacht-club' ) => array(
		'email'        => __( 'Email', 'orienta-yacht-club' ),
		'mobile_phone' => __( 'Mobile', 'orienta-yacht-club' ),
		'home_phone'   => __( 'Home phone', 'orienta-yacht-club' ),
		'address'      => __( 'Address', 'orienta-yacht-club' ),
		'city'         => __( 'City', 'orienta-yacht-club' ),
		'state'        => __( 'State', 'orienta-yacht-club' ),
		'zip'          => __( 'Postal code', 'orienta-yacht-club' ),
		'employer'     => __( 'Employer', 'orienta-yacht-club' ),
		'family_names' => __( 'Family', 'orienta-yacht-club' ),
	),
	__( 'Boating', 'orienta-yacht-club' ) => array(
		'owns_boat'          => __( 'Owns a boat', 'orienta-yacht-club' ),
		'boat_description'   => __( 'Boat', 'orienta-yacht-club' ),
		'boat_location'      => __( 'Kept at', 'orienta-yacht-club' ),
		'previous_boats'     => __( 'Previous boats', 'orienta-yacht-club' ),
		'boating_experience' => __( 'Experience', 'orienta-yacht-club' ),
		'boating_duration'   => __( 'Years boating', 'orienta-yacht-club' ),
		'boating_frequency'  => __( 'How often', 'orienta-yacht-club' ),
		'competence_rating'  => __( 'Self-rating', 'orienta-yacht-club' ),
		'has_licenses'       => __( 'Licences', 'orienta-yacht-club' ),
		'training_courses'   => __( 'Training', 'orienta-yacht-club' ),
	),
	__( 'Club', 'orienta-yacht-club' ) => array(
		'join_reason'           => __( 'Reason for joining', 'orienta-yacht-club' ),
		'join_reason_other'     => __( 'Reason (other)', 'orienta-yacht-club' ),
		'know_members'          => __( 'Knows members', 'orienta-yacht-club' ),
		'know_members_who'      => __( 'Who', 'orienta-yacht-club' ),
		'other_club'            => __( 'Member elsewhere', 'orienta-yacht-club' ),
		'other_club_which'      => __( 'Which club', 'orienta-yacht-club' ),
		'previous_club'         => __( 'Previous club', 'orienta-yacht-club' ),
		'previous_club_details' => __( 'Previous club detail', 'orienta-yacht-club' ),
		'skills_to_contribute'  => __( 'Skills offered', 'orienta-yacht-club' ),
		'hear_source'           => __( 'Heard about us via', 'orienta-yacht-club' ),
		'hear_source_other'     => __( 'Heard via (other)', 'orienta-yacht-club' ),
	),
);

get_header();
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Officer Area', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Applications', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section officer-section">
	<div class="container">

		<?php oyc_officer_nav( 'applications' ); ?>
		<?php oyc_officer_render_notices(); ?>

		<div class="officer-toolbar">
			<form method="get" class="officer-search">
				<label class="screen-reader-text" for="aq"><?php esc_html_e( 'Search applications', 'orienta-yacht-club' ); ?></label>
				<input type="search" id="aq" name="aq" value="<?php echo esc_attr( $oyc_search ); ?>"
				       placeholder="<?php esc_attr_e( 'Search by name or email', 'orienta-yacht-club' ); ?>" />
				<button type="submit" class="officer-btn"><?php esc_html_e( 'Search', 'orienta-yacht-club' ); ?></button>
				<?php if ( $oyc_search ) : ?>
					<a class="officer-btn" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Clear', 'orienta-yacht-club' ); ?></a>
				<?php endif; ?>
			</form>

			<p class="officer-count">
				<?php printf(
					esc_html( _n( '%d application', '%d applications', (int) $oyc_query->found_posts, 'orienta-yacht-club' ) ),
					(int) $oyc_query->found_posts
				); ?>
			</p>
		</div>

		<?php if ( ! $oyc_query->have_posts() ) : ?>
			<p class="officer-empty"><?php esc_html_e( 'No applications to show.', 'orienta-yacht-club' ); ?></p>
		<?php else : ?>
			<div class="officer-messages">
				<?php
				while ( $oyc_query->have_posts() ) :
					$oyc_query->the_post();
					$aid   = get_the_ID();
					$get   = function ( $key ) use ( $aid ) {
						return trim( (string) get_post_meta( $aid, 'oyc_app_' . $key, true ) );
					};
					$name  = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
					$email = $get( 'email' );
					$sub   = $get( 'submitted_at' );
					$boat  = $get( 'owns_boat' );
				?>
					<details class="officer-message">
						<summary class="officer-message__summary">
							<span class="officer-message__from"><?php echo esc_html( $name ? $name : __( '(no name)', 'orienta-yacht-club' ) ); ?></span>
							<?php if ( $boat ) : ?>
								<span class="officer-message__type"><?php echo esc_html( $boat ); ?></span>
							<?php endif; ?>
							<span class="officer-message__date">
								<?php echo esc_html( $sub ? mysql2date( 'j M Y', $sub ) : get_the_date() ); ?>
							</span>
						</summary>

						<div class="officer-message__body">
							<?php foreach ( $oyc_app_groups as $group_label => $fields ) :
								$rows = array();
								foreach ( $fields as $key => $label ) {
									$val = $get( $key );
									if ( '' !== $val ) {
										$rows[ $label ] = $val;
									}
								}
								if ( ! $rows ) {
									continue;
								}
							?>
								<h3 class="officer-app__group"><?php echo esc_html( $group_label ); ?></h3>
								<dl class="officer-message__meta">
									<?php foreach ( $rows as $label => $val ) : ?>
										<dt><?php echo esc_html( $label ); ?></dt>
										<dd>
											<?php if ( is_email( $val ) ) : ?>
												<a href="mailto:<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $val ); ?></a>
											<?php elseif ( preg_match( '~^[0-9 ()+.-]{7,}$~', $val ) ) : ?>
												<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $val ) ); ?>"><?php echo esc_html( $val ); ?></a>
											<?php else : ?>
												<?php echo nl2br( esc_html( $val ) ); ?>
											<?php endif; ?>
										</dd>
									<?php endforeach; ?>
								</dl>
							<?php endforeach; ?>

							<div class="officer-message__actions">
								<?php if ( $email ) : ?>
									<a class="officer-btn" href="mailto:<?php echo esc_attr( $email ); ?>?subject=<?php echo esc_attr( rawurlencode( __( 'Your Orienta Yacht Club membership application', 'orienta-yacht-club' ) ) ); ?>">
										<?php esc_html_e( 'Reply by email', 'orienta-yacht-club' ); ?>
									</a>
								<?php endif; ?>

								<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this application permanently?', 'orienta-yacht-club' ) ); ?>');">
									<?php wp_nonce_field( 'oyc_app_action', 'oyc_app_nonce' ); ?>
									<input type="hidden" name="app_id" value="<?php echo esc_attr( $aid ); ?>" />
									<input type="hidden" name="app_action" value="delete" />
									<button type="submit" class="officer-btn officer-btn--danger"><?php esc_html_e( 'Delete', 'orienta-yacht-club' ); ?></button>
								</form>
							</div>
						</div>
					</details>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>

			<?php if ( $oyc_query->max_num_pages > 1 ) : ?>
				<div class="officer-pagination">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( array( 'aq' => $oyc_search, 'apage' => '%#%' ), get_permalink() ),
						'format'    => '',
						'current'   => $oyc_paged,
						'total'     => $oyc_query->max_num_pages,
						'prev_text' => __( '&laquo; Newer', 'orienta-yacht-club' ),
						'next_text' => __( 'Older &raquo;', 'orienta-yacht-club' ),
					) );
					?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

	</div>
</section>

<?php
get_footer();

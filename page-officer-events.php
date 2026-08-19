<?php
/**
 * Template Name: Officer — Calendar Events
 * Add and remove Calendarize it! events from the front end.
 *
 * @package Orienta_Yacht_Club
 */

require_once get_template_directory() . '/inc/officer-admin.php';

oyc_officer_guard( 'oyc_manage_events' );

/* ── Create ───────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_event_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_event_nonce'] ) ), 'oyc_event_create' ) ) {

	$title      = sanitize_text_field( wp_unslash( $_POST['event_title'] ?? '' ) );
	$content    = wp_kses_post( wp_unslash( $_POST['event_content'] ?? '' ) );
	$allday     = ! empty( $_POST['event_allday'] );
	$start_date = sanitize_text_field( wp_unslash( $_POST['event_start_date'] ?? '' ) );
	$end_date   = sanitize_text_field( wp_unslash( $_POST['event_end_date']   ?? '' ) );
	$start_time = sanitize_text_field( wp_unslash( $_POST['event_start_time'] ?? '' ) );
	$end_time   = sanitize_text_field( wp_unslash( $_POST['event_end_time']   ?? '' ) );
	$cal_terms  = array_map( 'intval', (array) ( $_POST['event_calendar'] ?? array() ) );

	if ( '' === $title || '' === $start_date ) {
		oyc_officer_notice( __( 'An event needs at least a title and a start date.', 'orienta-yacht-club' ), 'error' );
	} else {
		$end_date = $end_date ? $end_date : $start_date;

		if ( $allday ) {
			$start = $start_date;
			$end   = $end_date;
		} else {
			$start_time = $start_time ? $start_time . ':00' : '00:00:00';
			$end_time   = $end_time   ? $end_time   . ':00' : $start_time;
			$start      = $start_date . ' ' . $start_time;
			$end        = $end_date . ' ' . $end_time;
		}

		$result = oyc_create_calendar_event( array(
			'title'    => $title,
			'content'  => $content,
			'start'    => $start,
			'end'      => $end,
			'allday'   => $allday,
			'calendar' => $cal_terms,
			'status'   => 'publish',
		) );

		if ( is_wp_error( $result ) ) {
			oyc_officer_notice(
				sprintf( __( 'The event could not be saved: %s', 'orienta-yacht-club' ), $result->get_error_message() ),
				'error'
			);
		} else {
			if ( function_exists( 'oyc_bump_cal_rev' ) ) {
				oyc_bump_cal_rev();
			}
			oyc_audit_log( 'event.create', sprintf( 'Created event "%s" on %s', $title, $start_date ), $result );
			oyc_officer_notice( sprintf( __( '"%s" was added to the calendar.', 'orienta-yacht-club' ), $title ) );
		}
	}
}

/* ── Delete ───────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_event_delete_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_event_delete_nonce'] ) ), 'oyc_event_delete' ) ) {

	$del_id = (int) ( $_POST['event_id'] ?? 0 );
	$title  = oyc_delete_calendar_event( $del_id );

	if ( is_wp_error( $title ) ) {
		oyc_officer_notice( __( 'That event no longer exists.', 'orienta-yacht-club' ), 'error' );
	} else {
		if ( function_exists( 'oyc_bump_cal_rev' ) ) {
			oyc_bump_cal_rev();
		}
		oyc_audit_log( 'event.delete', sprintf( 'Deleted event "%s"', $title ), $del_id );
		oyc_officer_notice( sprintf( __( '"%s" was removed from the calendar.', 'orienta-yacht-club' ), $title ) );
	}
}

/* ── Data for the view ────────────────────────────────────────────────────── */

$oyc_cal_terms = get_terms( array( 'taxonomy' => 'calendar', 'hide_empty' => false ) );
if ( is_wp_error( $oyc_cal_terms ) ) {
	$oyc_cal_terms = array();
}

$oyc_events = get_posts( array(
	'post_type'      => 'events',
	'post_status'    => 'publish',
	'posts_per_page' => 100,
	'meta_key'       => 'fc_start',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'meta_query'     => array(
		array(
			'key'     => 'fc_start',
			'value'   => current_time( 'Y-m-d' ),
			'compare' => '>=',
			'type'    => 'DATE',
		),
	),
) );

get_header();
?>

<div class="page-hero page-hero--dashboard">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Officer Area', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Calendar Events', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section officer-section">
	<div class="container">

		<?php oyc_officer_nav( 'events' ); ?>
		<?php oyc_officer_render_notices(); ?>

		<div class="officer-panel">
			<h2 class="officer-panel__title"><?php esc_html_e( 'Add an Event', 'orienta-yacht-club' ); ?></h2>

			<form method="post" class="officer-form">
				<?php wp_nonce_field( 'oyc_event_create', 'oyc_event_nonce' ); ?>

				<div class="officer-form__row">
					<label for="event_title"><?php esc_html_e( 'Event Title', 'orienta-yacht-club' ); ?></label>
					<input type="text" id="event_title" name="event_title" required />
				</div>

				<div class="officer-form__row officer-form__row--check">
					<input type="checkbox" id="event_allday" name="event_allday" value="1" checked />
					<label for="event_allday"><?php esc_html_e( 'All-day event', 'orienta-yacht-club' ); ?></label>
				</div>

				<div class="officer-form__grid">
					<div class="officer-form__row">
						<label for="event_start_date"><?php esc_html_e( 'Start Date', 'orienta-yacht-club' ); ?></label>
						<input type="date" id="event_start_date" name="event_start_date" required />
					</div>
					<div class="officer-form__row">
						<label for="event_start_time"><?php esc_html_e( 'Start Time', 'orienta-yacht-club' ); ?></label>
						<input type="time" id="event_start_time" name="event_start_time" />
					</div>
					<div class="officer-form__row">
						<label for="event_end_date"><?php esc_html_e( 'End Date', 'orienta-yacht-club' ); ?></label>
						<input type="date" id="event_end_date" name="event_end_date" />
					</div>
					<div class="officer-form__row">
						<label for="event_end_time"><?php esc_html_e( 'End Time', 'orienta-yacht-club' ); ?></label>
						<input type="time" id="event_end_time" name="event_end_time" />
					</div>
				</div>

				<?php if ( $oyc_cal_terms ) : ?>
					<fieldset class="officer-form__row">
						<legend><?php esc_html_e( 'Calendar', 'orienta-yacht-club' ); ?></legend>
						<div class="officer-checks">
							<?php foreach ( $oyc_cal_terms as $term ) : ?>
								<label class="officer-check">
									<input type="checkbox" name="event_calendar[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
									<?php echo esc_html( $term->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif; ?>

				<div class="officer-form__row">
					<label for="event_content"><?php esc_html_e( 'Description', 'orienta-yacht-club' ); ?> <span class="officer-form__optional">(<?php esc_html_e( 'optional', 'orienta-yacht-club' ); ?>)</span></label>
					<textarea id="event_content" name="event_content" rows="4"></textarea>
				</div>

				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Add Event', 'orienta-yacht-club' ); ?></button>
			</form>
		</div>

		<div class="officer-panel">
			<h2 class="officer-panel__title"><?php esc_html_e( 'Upcoming Events', 'orienta-yacht-club' ); ?></h2>

			<?php if ( ! $oyc_events ) : ?>
				<p class="officer-empty"><?php esc_html_e( 'No upcoming events on the calendar.', 'orienta-yacht-club' ); ?></p>
			<?php else : ?>
				<table class="officer-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Event', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Starts', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Ends', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'orienta-yacht-club' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $oyc_events as $ev ) :
							$ev_start  = get_post_meta( $ev->ID, 'fc_start', true );
							$ev_end    = get_post_meta( $ev->ID, 'fc_end', true );
							$ev_allday = get_post_meta( $ev->ID, 'fc_allday', true );
							$ev_stime  = get_post_meta( $ev->ID, 'fc_start_time', true );
						?>
							<tr>
								<td><?php echo esc_html( get_the_title( $ev->ID ) ); ?></td>
								<td>
									<?php
									echo esc_html( mysql2date( 'D j M Y', $ev_start ) );
									if ( ! $ev_allday && $ev_stime ) {
										echo ' · ' . esc_html( mysql2date( 'g:i a', $ev_start . ' ' . $ev_stime ) );
									}
									?>
								</td>
								<td><?php echo esc_html( $ev_end ? mysql2date( 'D j M Y', $ev_end ) : '—' ); ?></td>
								<td class="officer-table__actions">
									<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this event? This cannot be undone.', 'orienta-yacht-club' ) ); ?>');">
										<?php wp_nonce_field( 'oyc_event_delete', 'oyc_event_delete_nonce' ); ?>
										<input type="hidden" name="event_id" value="<?php echo esc_attr( $ev->ID ); ?>" />
										<button type="submit" class="officer-btn officer-btn--danger"><?php esc_html_e( 'Delete', 'orienta-yacht-club' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>
</section>

<?php
get_footer();

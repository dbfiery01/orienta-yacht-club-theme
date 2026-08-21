<?php
/**
 * Template Name: Officer — Messages
 * Read and triage contact-form submissions from the front end.
 *
 * Reads the oyc_message CPT populated by inc/admin-inbox.php.
 *
 * @package Orienta_Yacht_Club
 */

require_once get_template_directory() . '/inc/officer-admin.php';

oyc_officer_guard( 'oyc_manage_messages' );

/* ── Actions ──────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_msg_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_msg_nonce'] ) ), 'oyc_msg_action' ) ) {

	$action = sanitize_key( wp_unslash( $_POST['msg_action'] ?? '' ) );
	$msg_id = (int) ( $_POST['msg_id'] ?? 0 );

	if ( $msg_id && 'oyc_message' !== get_post_type( $msg_id ) ) {
		$msg_id = 0;
	}

	switch ( $action ) {
		case 'mark_read':
			if ( $msg_id ) {
				update_post_meta( $msg_id, 'oyc_msg_read', '1' );
			}
			break;

		case 'mark_unread':
			if ( $msg_id ) {
				update_post_meta( $msg_id, 'oyc_msg_read', '0' );
			}
			break;

		case 'delete':
			if ( $msg_id ) {
				$who = get_post_meta( $msg_id, 'oyc_msg_your_name', true );
				wp_delete_post( $msg_id, true );
				oyc_audit_log( 'message.delete', sprintf( 'Deleted message from %s', $who ), $msg_id );
				oyc_officer_notice( __( 'Message deleted.', 'orienta-yacht-club' ) );
			}
			break;

		case 'mark_all_read':
			$all = get_posts( array(
				'post_type'      => 'oyc_message',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => 'oyc_msg_read', 'value' => '0', 'compare' => '=' ),
				),
			) );
			foreach ( $all as $id ) {
				update_post_meta( $id, 'oyc_msg_read', '1' );
			}
			oyc_officer_notice( sprintf(
				_n( '%d message marked read.', '%d messages marked read.', count( $all ), 'orienta-yacht-club' ),
				count( $all )
			) );
			break;
	}
}

/* ── Data for the view ────────────────────────────────────────────────────── */

$oyc_filter = isset( $_GET['show'] ) && 'unread' === $_GET['show'] ? 'unread' : 'all';
$oyc_paged  = max( 1, (int) ( $_GET['mpage'] ?? 1 ) );

$oyc_args = array(
	'post_type'      => 'oyc_message',
	'post_status'    => 'publish',
	'posts_per_page' => 25,
	'paged'          => $oyc_paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

if ( 'unread' === $oyc_filter ) {
	$oyc_args['meta_query'] = array(
		array( 'key' => 'oyc_msg_read', 'value' => '0', 'compare' => '=' ),
	);
}

$oyc_query  = new WP_Query( $oyc_args );
$oyc_unread = function_exists( 'oyc_unread_count' ) ? (int) oyc_unread_count() : 0;

get_header();
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Officer Area', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Messages', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section officer-section">
	<div class="container">

		<?php oyc_officer_nav( 'messages' ); ?>
		<?php oyc_officer_render_notices(); ?>

		<p class="officer-intro">
			<?php esc_html_e( 'Every enquiry sent through the website is kept here permanently, whether or not you reply by email. Opening a message marks it read — there is nothing to tick off.', 'orienta-yacht-club' ); ?>
		</p>

		<div class="officer-toolbar">
			<div class="officer-filters">
				<a class="officer-btn<?php echo 'all' === $oyc_filter ? ' is-current' : ''; ?>"
				   href="<?php echo esc_url( add_query_arg( 'show', 'all', get_permalink() ) ); ?>">
					<?php esc_html_e( 'All', 'orienta-yacht-club' ); ?>
				</a>
				<a class="officer-btn<?php echo 'unread' === $oyc_filter ? ' is-current' : ''; ?>"
				   href="<?php echo esc_url( add_query_arg( 'show', 'unread', get_permalink() ) ); ?>">
					<?php printf( esc_html__( 'Unread (%d)', 'orienta-yacht-club' ), (int) $oyc_unread ); ?>
				</a>
			</div>

			<?php if ( $oyc_unread ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'oyc_msg_action', 'oyc_msg_nonce' ); ?>
					<input type="hidden" name="msg_action" value="mark_all_read" />
					<button type="submit" class="officer-btn"><?php esc_html_e( 'Mark all read', 'orienta-yacht-club' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<?php if ( ! $oyc_query->have_posts() ) : ?>
			<p class="officer-empty"><?php esc_html_e( 'No messages to show.', 'orienta-yacht-club' ); ?></p>
		<?php else : ?>
			<div class="officer-messages">
				<?php
				while ( $oyc_query->have_posts() ) :
					$oyc_query->the_post();
					$mid     = get_the_ID();
					$is_read = '1' === get_post_meta( $mid, 'oyc_msg_read', true );
					$name    = get_post_meta( $mid, 'oyc_msg_your_name', true );
					$email   = get_post_meta( $mid, 'oyc_msg_your_email', true );
					$phone   = get_post_meta( $mid, 'oyc_msg_your_phone', true );
					$inquiry = get_post_meta( $mid, 'oyc_msg_inquiry_type', true );
					$body    = get_post_meta( $mid, 'oyc_msg_your_message', true );
					$rcv     = get_post_meta( $mid, 'oyc_msg_received_at', true );
				?>
					<details class="officer-message<?php echo $is_read ? '' : ' is-unread'; ?>" data-msg-id="<?php echo esc_attr( $mid ); ?>">
						<summary class="officer-message__summary">
							<span class="officer-message__from"><?php echo esc_html( $name ? $name : __( '(no name)', 'orienta-yacht-club' ) ); ?></span>
							<span class="officer-message__type"><?php echo esc_html( $inquiry ); ?></span>
							<span class="officer-message__date"><?php echo esc_html( $rcv ? mysql2date( 'j M Y, g:i a', $rcv ) : get_the_date() ); ?></span>
							<?php if ( ! $is_read ) : ?>
								<span class="officer-message__badge"><?php esc_html_e( 'New', 'orienta-yacht-club' ); ?></span>
							<?php endif; ?>
						</summary>

						<div class="officer-message__body">
							<dl class="officer-message__meta">
								<?php if ( $email ) : ?>
									<dt><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?></dt>
									<dd><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></dd>
								<?php endif; ?>
								<?php if ( $phone ) : ?>
									<dt><?php esc_html_e( 'Phone', 'orienta-yacht-club' ); ?></dt>
									<dd><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></dd>
								<?php endif; ?>
							</dl>

							<p class="officer-message__text"><?php echo nl2br( esc_html( $body ) ); ?></p>

							<div class="officer-message__actions">
								<?php if ( $email ) : ?>
									<a class="officer-btn" href="mailto:<?php echo esc_attr( $email ); ?>?subject=<?php echo esc_attr( rawurlencode( __( 'Re: your message to Orienta Yacht Club', 'orienta-yacht-club' ) ) ); ?>">
										<?php esc_html_e( 'Reply by email', 'orienta-yacht-club' ); ?>
									</a>
								<?php endif; ?>

								<form method="post">
									<?php wp_nonce_field( 'oyc_msg_action', 'oyc_msg_nonce' ); ?>
									<input type="hidden" name="msg_id" value="<?php echo esc_attr( $mid ); ?>" />
									<input type="hidden" name="msg_action" value="<?php echo $is_read ? 'mark_unread' : 'mark_read'; ?>" />
									<button type="submit" class="officer-btn">
										<?php echo $is_read
											? esc_html__( 'Mark unread', 'orienta-yacht-club' )
											: esc_html__( 'Mark read', 'orienta-yacht-club' ); ?>
									</button>
								</form>

								<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this message permanently?', 'orienta-yacht-club' ) ); ?>');">
									<?php wp_nonce_field( 'oyc_msg_action', 'oyc_msg_nonce' ); ?>
									<input type="hidden" name="msg_id" value="<?php echo esc_attr( $mid ); ?>" />
									<input type="hidden" name="msg_action" value="delete" />
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
						'base'      => add_query_arg( array( 'show' => $oyc_filter, 'mpage' => '%#%' ), get_permalink() ),
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

<?php if ( $oyc_query->have_posts() || $oyc_unread ) : ?>
<script>
(function () {
	var endpoint = <?php echo wp_json_encode( esc_url_raw( rest_url( 'oyc/v1/message-read/' ) ) ); ?>;
	var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

	document.querySelectorAll('.officer-message[data-msg-id]').forEach(function (el) {
		el.addEventListener('toggle', function () {
			if (!el.open || el.dataset.marked === '1') { return; }
			el.dataset.marked = '1';
			el.classList.remove('is-unread');

			fetch(endpoint + el.dataset.msgId, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': nonce }
			}).catch(function () { /* the manual button remains as a fallback */ });
		});
	});
}());
</script>
<?php endif; ?>

<?php
get_footer();

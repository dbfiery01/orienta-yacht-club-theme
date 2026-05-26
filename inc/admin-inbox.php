<?php
/**
 * OYC Admin Inbox — Applications & Contact Messages
 *
 * - Registers the oyc_message CPT for storing contact-form submissions.
 * - Hooks CF7 form 39 (contact) to persist messages on mail_sent OR mail_failed.
 * - Builds a unified "OYC Inbox" admin menu with Applications and Messages sub-pages.
 * - All pages are restricted to users with manage_options (admins only).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ─────────────────────────────────────────────────────────────────────────────
 * 1. oyc_message custom post type
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	register_post_type( 'oyc_message', array(
		'label'         => 'Messages',
		'labels'        => array(
			'name'          => 'Contact Messages',
			'singular_name' => 'Contact Message',
		),
		'public'        => false,
		'show_ui'       => false, // managed entirely by our custom pages
		'supports'      => array( 'title', 'custom-fields' ),
		'capability_type' => 'post',
		'map_meta_cap'  => true,
	) );
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * 2. Capture CF7 form 39 (contact form) on mail_sent AND mail_failed
 *    so submissions are always stored regardless of mail-server availability.
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_save_contact_message( $cf7 ) {
	if ( (int) $cf7->id() !== 39 ) {
		return;
	}

	$sub = WPCF7_Submission::get_instance();
	if ( ! $sub ) {
		return;
	}

	$data    = $sub->get_posted_data();
	$name    = sanitize_text_field( $data['your-name']    ?? '' );
	$inquiry = sanitize_text_field( $data['inquiry-type'] ?? 'General Inquiry' );

	$post_id = wp_insert_post( array(
		'post_type'   => 'oyc_message',
		'post_title'  => $name . ' — ' . $inquiry,
		'post_status' => 'publish',
		'post_author' => 1,
	) );

	if ( is_wp_error( $post_id ) ) {
		return;
	}

	$fields = array(
		'your-name'    => 'your_name',
		'your-email'   => 'your_email',
		'your-phone'   => 'your_phone',
		'inquiry-type' => 'inquiry_type',
		'your-message' => 'your_message',
	);

	foreach ( $fields as $cf7_key => $meta_suffix ) {
		update_post_meta(
			$post_id,
			'oyc_msg_' . $meta_suffix,
			sanitize_textarea_field( $data[ $cf7_key ] ?? '' )
		);
	}

	update_post_meta( $post_id, 'oyc_msg_read', '0' );
	update_post_meta( $post_id, 'oyc_msg_received_at', current_time( 'mysql' ) );
}

add_action( 'wpcf7_mail_sent',   'oyc_save_contact_message' );
add_action( 'wpcf7_mail_failed', 'oyc_save_contact_message' );

/* ─────────────────────────────────────────────────────────────────────────────
 * 3. Helper: count unread messages
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_unread_count() {
	static $count = null;
	if ( $count !== null ) {
		return $count;
	}
	$q = new WP_Query( array(
		'post_type'      => 'oyc_message',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array( 'key' => 'oyc_msg_read', 'value' => '0', 'compare' => '=' ),
		),
	) );
	$count = $q->found_posts;
	return $count;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * 4. Admin menu registration
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
	$unread      = oyc_unread_count();
	$badge       = $unread ? ' <span class="awaiting-mod update-plugins">' . (int) $unread . '</span>' : '';

	// Top-level menu lands on Applications
	add_menu_page(
		'OYC Inbox',
		'OYC Inbox' . $badge,
		'manage_options',
		'oyc-applications',
		'oyc_render_applications_page',
		'dashicons-clipboard',
		25
	);

	add_submenu_page(
		'oyc-applications',
		'Membership Applications — OYC',
		'Applications',
		'manage_options',
		'oyc-applications',
		'oyc_render_applications_page'
	);

	add_submenu_page(
		'oyc-applications',
		'Contact Messages — OYC',
		'Messages' . $badge,
		'manage_options',
		'oyc-messages',
		'oyc_render_messages_page'
	);
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * 5. Shared admin styles (injected only on OYC pages)
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	$is_oyc = in_array( $screen->id, array(
		'toplevel_page_oyc-applications',
		'oyc-inbox_page_oyc-messages',
	), true );
	if ( ! $is_oyc ) {
		return;
	}
	?>
<style>
/* ── OYC Inbox shared styles ───────────────────────────────── */
.oyc-wrap { max-width: 1200px; }
.oyc-wrap h1 { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

/* Table container */
.oyc-table-wrap { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; margin-top: 4px; overflow-x: auto; }
.oyc-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
.oyc-table th { background: #f6f7f7; padding: 10px 14px; text-align: left; border-bottom: 1px solid #c3c4c7; font-weight: 600; color: #1d2327; white-space: nowrap; }
.oyc-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.oyc-table tr:last-child td { border-bottom: none; }
.oyc-row:hover td { background: #f6f7f7; cursor: pointer; }
.oyc-unread td { font-weight: 600; }

/* Badges */
.oyc-badge { display: inline-block; background: #0B2A4A; color: #fff; border-radius: 3px; font-size: 11px; padding: 2px 6px; font-weight: 600; letter-spacing: .02em; }
.oyc-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #D4A851; flex-shrink: 0; }

/* Detail view */
.oyc-detail-wrap { max-width: 860px; margin-top: 16px; }
.oyc-detail-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px; }
.oyc-detail-card-hd { padding: 12px 20px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; background: #f6f7f7; border-radius: 4px 4px 0 0; }
.oyc-detail-card-hd h3 { margin: 0; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #646970; font-weight: 600; }
.oyc-detail-row { display: grid; grid-template-columns: 200px 1fr; border-bottom: 1px solid #f0f0f0; }
.oyc-detail-row:last-child { border-bottom: none; }
.oyc-detail-label { padding: 10px 14px 10px 20px; color: #646970; font-size: 12px; background: #fafafa; border-right: 1px solid #f0f0f0; display: flex; align-items: flex-start; padding-top: 12px; }
.oyc-detail-value { padding: 10px 20px; font-size: 13px; line-height: 1.5; }
.oyc-detail-value:empty::before { content: '—'; color: #aaa; }

/* Toolbar */
.oyc-toolbar { display: flex; align-items: center; gap: 8px; margin: 12px 0; flex-wrap: wrap; }
.oyc-toolbar form { display: flex; gap: 6px; margin-left: auto; }
.oyc-toolbar input[type="text"] { width: 220px; }

/* Actions row */
.oyc-actions { display: flex; gap: 8px; margin-top: 20px; flex-wrap: wrap; align-items: center; }
.oyc-actions .oyc-delete-btn { margin-left: auto; color: #b32d2e !important; }

/* Empty state */
.oyc-empty { text-align: center; color: #646970; padding: 48px 20px; font-size: 14px; }

/* Back link in h1 */
.oyc-back-link { font-size: 13px; font-weight: 400; text-decoration: none; color: #2271b1; white-space: nowrap; }

/* Message body */
.oyc-message-body { padding: 20px; white-space: pre-wrap; font-size: 14px; line-height: 1.7; color: #1d2327; }

/* Stat cards (dashboard) */
.oyc-stat-row { display: flex; gap: 16px; margin: 16px 0 24px; flex-wrap: wrap; }
.oyc-stat { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px 24px; min-width: 140px; }
.oyc-stat .n { font-size: 36px; font-weight: 700; color: #0B2A4A; line-height: 1; }
.oyc-stat .lbl { font-size: 12px; color: #646970; margin-top: 4px; text-transform: uppercase; letter-spacing: .04em; }
.oyc-stat.highlight .n { color: #D4A851; }
</style>
	<?php
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * 6. Applications page
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_render_applications_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not authorized.' );
	}

	// ── Detail view ──
	if ( isset( $_GET['view'], $_GET['id'] ) && $_GET['view'] === 'detail' ) {
		oyc_render_application_detail( (int) $_GET['id'] );
		return;
	}

	// ── Delete action ──
	if (
		isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) &&
		$_GET['action'] === 'delete' &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'oyc_del_app_' . (int) $_GET['id'] )
	) {
		wp_delete_post( (int) $_GET['id'], true );
		echo '<div class="notice notice-success is-dismissible"><p>Application deleted.</p></div>';
	}

	$search = sanitize_text_field( $_GET['s'] ?? '' );
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$per    = 20;

	$args = array(
		'post_type'      => 'oyc_application',
		'post_status'    => 'publish',
		'posts_per_page' => $per,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $search ) {
		$args['s'] = $search;
	}
	$q = new WP_Query( $args );
	?>
<div class="wrap oyc-wrap">
	<h1>
		Membership Applications
		<span style="font-size:14px;font-weight:400;color:#646970"><?php echo (int) $q->found_posts; ?> total</span>
	</h1>

	<div class="oyc-toolbar">
		<form method="get">
			<input type="hidden" name="page" value="oyc-applications">
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name…">
			<?php submit_button( 'Search', 'secondary', '', false ); ?>
			<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=oyc-applications' ) ); ?>" class="button">Clear</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="oyc-table-wrap">
	<?php if ( $q->have_posts() ) : ?>
		<table class="oyc-table">
			<thead>
				<tr>
					<th>Applicant</th>
					<th>Email</th>
					<th>Phone</th>
					<th>Reason for Joining</th>
					<th>Owns Boat</th>
					<th>Competence</th>
					<th>Submitted</th>
					<th style="width:1px"></th>
				</tr>
			</thead>
			<tbody>
			<?php while ( $q->have_posts() ) : $q->the_post();
				$id     = get_the_ID();
				$email  = get_post_meta( $id, 'oyc_app_email',        true );
				$phone  = get_post_meta( $id, 'oyc_app_mobile_phone', true )
				       ?: get_post_meta( $id, 'oyc_app_home_phone',   true );
				$reason = get_post_meta( $id, 'oyc_app_join_reason',  true );
				$boat   = get_post_meta( $id, 'oyc_app_owns_boat',    true );
				$rating = get_post_meta( $id, 'oyc_app_competence_rating', true );
				$sub_at = get_post_meta( $id, 'oyc_app_submitted_at', true );
				$url    = admin_url( 'admin.php?page=oyc-applications&view=detail&id=' . $id );
				$del    = wp_nonce_url( admin_url( 'admin.php?page=oyc-applications&action=delete&id=' . $id ), 'oyc_del_app_' . $id );
			?>
				<tr class="oyc-row" onclick="location.href='<?php echo esc_js( $url ); ?>'">
					<td><strong><?php the_title(); ?></strong></td>
					<td><?php echo $email ? '<a href="mailto:' . esc_attr( $email ) . '" onclick="event.stopPropagation()">' . esc_html( $email ) . '</a>' : '—'; ?></td>
					<td style="white-space:nowrap"><?php echo esc_html( $phone ?: '—' ); ?></td>
					<td style="max-width:220px"><?php echo esc_html( $reason ? wp_trim_words( $reason, 10 ) : '—' ); ?></td>
					<td><?php
						if ( $boat === 'Yes' ) {
							echo '<span style="color:#27ae60;font-weight:600">Yes</span>';
						} elseif ( $boat ) {
							echo '<span style="color:#888">' . esc_html( $boat ) . '</span>';
						} else {
							echo '—';
						}
					?></td>
					<td><?php echo $rating ? '<span class="oyc-badge">' . (int) $rating . ' / 10</span>' : '—'; ?></td>
					<td style="white-space:nowrap;color:#646970"><?php echo $sub_at ? esc_html( date_i18n( 'M j, Y', strtotime( $sub_at ) ) ) : get_the_date( 'M j, Y' ); ?></td>
					<td onclick="event.stopPropagation()" style="white-space:nowrap">
						<a href="<?php echo esc_url( $url ); ?>" class="button button-small">View</a>
						<a href="<?php echo esc_url( $del ); ?>" class="button button-small"
						   style="color:#b32d2e"
						   onclick="return confirm('Permanently delete this application?')">Delete</a>
					</td>
				</tr>
			<?php endwhile; wp_reset_postdata(); ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="oyc-empty">No applications have been submitted yet.</p>
	<?php endif; ?>
	</div>

	<?php oyc_admin_pagination( $q->max_num_pages, $paged, 'oyc-applications', array( 's' => $search ) ); ?>
</div>
	<?php
}

/* ─────────────────────────────────────────────────────────────────────────────
 * 7. Application detail view
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_render_application_detail( $id ) {
	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'oyc_application' ) {
		echo '<div class="wrap"><p>Application not found.</p></div>';
		return;
	}

	$m = array();
	foreach ( get_post_meta( $id ) as $key => $val ) {
		$m[ $key ] = $val[0] ?? '';
	}

	$back  = admin_url( 'admin.php?page=oyc-applications' );
	$del   = wp_nonce_url( admin_url( 'admin.php?page=oyc-applications&action=delete&id=' . $id ), 'oyc_del_app_' . $id );
	$email = $m['oyc_app_email'] ?? '';
	$sub   = $m['oyc_app_submitted_at'] ?? '';
	?>
<div class="wrap oyc-wrap">
	<h1>
		<a href="<?php echo esc_url( $back ); ?>" class="oyc-back-link">← Applications</a>
		<?php echo esc_html( $post->post_title ); ?>
	</h1>
	<?php if ( $sub ) : ?>
		<p style="color:#646970;margin-top:4px">Submitted <?php echo esc_html( date_i18n( 'F j, Y \a\t g:i a', strtotime( $sub ) ) ); ?></p>
	<?php endif; ?>

	<div class="oyc-detail-wrap">

		<!-- Personal Information -->
		<div class="oyc-detail-card">
			<div class="oyc-detail-card-hd"><h3>Personal Information</h3></div>
			<div>
				<?php
				oyc_admin_row( 'First Name',   $m['oyc_app_first_name']   ?? '' );
				oyc_admin_row( 'Last Name',    $m['oyc_app_last_name']    ?? '' );
				oyc_admin_row( 'Address',      $m['oyc_app_address']      ?? '' );
				oyc_admin_row( 'City',         $m['oyc_app_city']         ?? '' );
				oyc_admin_row( 'State',        $m['oyc_app_state']        ?? '' );
				oyc_admin_row( 'ZIP',          $m['oyc_app_zip']          ?? '' );
				oyc_admin_row( 'Employer',     $m['oyc_app_employer']     ?? '' );
				oyc_admin_row( 'Family Members', $m['oyc_app_family_names'] ?? '' );
				?>
			</div>
		</div>

		<!-- Contact -->
		<div class="oyc-detail-card">
			<div class="oyc-detail-card-hd"><h3>Contact</h3></div>
			<div>
				<?php
				$em = $m['oyc_app_email'] ?? '';
				oyc_admin_row( 'Email',       $em ? '<a href="mailto:' . esc_attr( $em ) . '">' . esc_html( $em ) . '</a>' : '', true );
				oyc_admin_row( 'Mobile Phone', $m['oyc_app_mobile_phone'] ?? '' );
				oyc_admin_row( 'Home Phone',   $m['oyc_app_home_phone']   ?? '' );
				?>
			</div>
		</div>

		<!-- Membership -->
		<div class="oyc-detail-card">
			<div class="oyc-detail-card-hd"><h3>Membership</h3></div>
			<div>
				<?php
				oyc_admin_row( 'Principal Reason for Joining', $m['oyc_app_join_reason']       ?? '' );
				oyc_admin_row( 'Reason (Other)',               $m['oyc_app_join_reason_other']  ?? '' );
				oyc_admin_row( 'How They Heard About OYC',     $m['oyc_app_hear_source']        ?? '' );
				oyc_admin_row( 'Source (Other)',               $m['oyc_app_hear_source_other']  ?? '' );
				oyc_admin_row( 'Knows Current Members',        $m['oyc_app_know_members']       ?? '' );
				oyc_admin_row( 'Members Known',                $m['oyc_app_know_members_who']   ?? '' );
				oyc_admin_row( 'Member of Another Club',       $m['oyc_app_other_club']         ?? '' );
				oyc_admin_row( 'Other Club Name(s)',           $m['oyc_app_other_club_which']   ?? '' );
				oyc_admin_row( 'Previous Club Membership',     $m['oyc_app_previous_club']      ?? '' );
				oyc_admin_row( 'Previous Club Details',        $m['oyc_app_previous_club_details'] ?? '' );
				oyc_admin_row( 'Skills to Contribute',         $m['oyc_app_skills_to_contribute']  ?? '' );
				?>
			</div>
		</div>

		<!-- Boating Experience -->
		<div class="oyc-detail-card">
			<div class="oyc-detail-card-hd"><h3>Boating Experience</h3></div>
			<div>
				<?php
				oyc_admin_row( 'Currently Owns a Boat',     $m['oyc_app_owns_boat']         ?? '' );
				oyc_admin_row( 'Boat Description',          $m['oyc_app_boat_description']  ?? '' );
				oyc_admin_row( 'Boating Experience',        $m['oyc_app_boating_experience'] ?? '' );
				oyc_admin_row( 'Years Boating',             $m['oyc_app_boating_duration']  ?? '' );
				oyc_admin_row( 'Boating Frequency',         $m['oyc_app_boating_frequency'] ?? '' );
				oyc_admin_row( 'Base of Operations',        $m['oyc_app_boat_location']     ?? '' );
				oyc_admin_row( 'Previous Boats',            $m['oyc_app_previous_boats']    ?? '' );
				oyc_admin_row( 'Training Courses',          $m['oyc_app_training_courses']  ?? '' );
				oyc_admin_row( 'Holds Licenses/Certs',      $m['oyc_app_has_licenses']      ?? '' );
				oyc_admin_row( 'Competence Rating (1–10)',  $m['oyc_app_competence_rating'] ?? '' );
				?>
			</div>
		</div>

		<div class="oyc-actions">
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>" class="button button-primary">Reply by Email</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $back ); ?>" class="button">← Back to Applications</a>
			<a href="<?php echo esc_url( $del ); ?>" class="button oyc-delete-btn"
			   onclick="return confirm('Permanently delete this application?')">Delete Application</a>
		</div>

	</div>
</div>
	<?php
}

/* ─────────────────────────────────────────────────────────────────────────────
 * 8. Messages page
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_render_messages_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not authorized.' );
	}

	// ── Detail view ──
	if ( isset( $_GET['view'], $_GET['id'] ) && $_GET['view'] === 'detail' ) {
		oyc_render_message_detail( (int) $_GET['id'] );
		return;
	}

	// ── Actions ──
	if ( isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
		$mid   = (int) $_GET['id'];
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( wp_verify_nonce( $nonce, 'oyc_msg_' . $mid ) ) {
			switch ( $_GET['action'] ) {
				case 'delete':
					wp_delete_post( $mid, true );
					echo '<div class="notice notice-success is-dismissible"><p>Message deleted.</p></div>';
					break;
				case 'mark_read':
					update_post_meta( $mid, 'oyc_msg_read', '1' );
					break;
				case 'mark_unread':
					update_post_meta( $mid, 'oyc_msg_read', '0' );
					break;
			}
		}
	}

	// ── Mark all read ──
	if (
		isset( $_GET['action'], $_GET['_wpnonce'] ) &&
		$_GET['action'] === 'mark_all_read' &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'oyc_mark_all_read' )
	) {
		$ids = get_posts( array(
			'post_type'      => 'oyc_message',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'oyc_msg_read', 'value' => '0' ) ),
		) );
		foreach ( $ids as $uid ) {
			update_post_meta( $uid, 'oyc_msg_read', '1' );
		}
		// reset cached count
		echo '<div class="notice notice-success is-dismissible"><p>All messages marked as read.</p></div>';
	}

	$search  = sanitize_text_field( $_GET['s']      ?? '' );
	$filter  = sanitize_text_field( $_GET['filter'] ?? '' ); // 'unread' | ''
	$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$per     = 20;

	$args = array(
		'post_type'      => 'oyc_message',
		'post_status'    => 'publish',
		'posts_per_page' => $per,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $search ) {
		$args['s'] = $search;
	}
	if ( $filter === 'unread' ) {
		$args['meta_query'] = array(
			array( 'key' => 'oyc_msg_read', 'value' => '0', 'compare' => '=' ),
		);
	}
	$q       = new WP_Query( $args );
	$unread  = oyc_unread_count();
	$base    = admin_url( 'admin.php?page=oyc-messages' );
	?>
<div class="wrap oyc-wrap">
	<h1>
		Contact Messages
		<span style="font-size:14px;font-weight:400;color:#646970"><?php echo (int) $q->found_posts; ?> <?php echo $filter === 'unread' ? 'unread' : 'total'; ?></span>
		<?php if ( $unread > 0 ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( $base . '&action=mark_all_read', 'oyc_mark_all_read' ) ); ?>"
			   class="button" style="font-size:12px;">Mark all read</a>
		<?php endif; ?>
	</h1>

	<div class="oyc-toolbar">
		<a href="<?php echo esc_url( $base ); ?>"
		   class="button<?php echo $filter !== 'unread' ? ' button-primary' : ''; ?>">All</a>
		<a href="<?php echo esc_url( $base . '&filter=unread' ); ?>"
		   class="button<?php echo $filter === 'unread' ? ' button-primary' : ''; ?>">
			Unread
			<?php if ( $unread ) : ?><span class="awaiting-mod update-plugins"><?php echo (int) $unread; ?></span><?php endif; ?>
		</a>

		<form method="get" style="margin-left:auto;display:flex;gap:6px;">
			<input type="hidden" name="page" value="oyc-messages">
			<?php if ( $filter ) : ?>
				<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
			<?php endif; ?>
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search messages…" style="width:220px">
			<?php submit_button( 'Search', 'secondary', '', false ); ?>
			<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( $base . ( $filter ? '&filter=' . urlencode( $filter ) : '' ) ); ?>" class="button">Clear</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="oyc-table-wrap">
	<?php if ( $q->have_posts() ) : ?>
		<table class="oyc-table">
			<thead>
				<tr>
					<th style="width:12px"></th>
					<th>Name</th>
					<th>Email</th>
					<th>Inquiry Type</th>
					<th>Message Preview</th>
					<th>Received</th>
					<th style="width:1px"></th>
				</tr>
			</thead>
			<tbody>
			<?php while ( $q->have_posts() ) : $q->the_post();
				$id      = get_the_ID();
				$is_read = get_post_meta( $id, 'oyc_msg_read', true ) === '1';
				$name    = get_post_meta( $id, 'oyc_msg_your_name',    true );
				$email   = get_post_meta( $id, 'oyc_msg_your_email',   true );
				$inquiry = get_post_meta( $id, 'oyc_msg_inquiry_type', true );
				$msg     = get_post_meta( $id, 'oyc_msg_your_message', true );
				$rcv     = get_post_meta( $id, 'oyc_msg_received_at',  true );
				$url     = admin_url( 'admin.php?page=oyc-messages&view=detail&id=' . $id );
				$del     = wp_nonce_url( admin_url( 'admin.php?page=oyc-messages&action=delete&id=' . $id ), 'oyc_msg_' . $id );
				$toggle_action = $is_read ? 'mark_unread' : 'mark_read';
				$toggle_label  = $is_read ? 'Unread' : 'Read';
				$toggle_url    = wp_nonce_url( admin_url( 'admin.php?page=oyc-messages&action=' . $toggle_action . '&id=' . $id ), 'oyc_msg_' . $id );
			?>
				<tr class="oyc-row<?php echo $is_read ? '' : ' oyc-unread'; ?>" onclick="location.href='<?php echo esc_js( $url ); ?>'">
					<td style="padding-left:10px">
						<?php if ( ! $is_read ) : ?>
							<span class="oyc-dot" title="Unread"></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $name ?: get_the_title() ); ?></td>
					<td><?php echo $email ? '<a href="mailto:' . esc_attr( $email ) . '" onclick="event.stopPropagation()">' . esc_html( $email ) . '</a>' : '—'; ?></td>
					<td><?php echo esc_html( $inquiry ?: '—' ); ?></td>
					<td style="color:#646970;max-width:260px"><?php echo esc_html( $msg ? wp_trim_words( $msg, 14 ) : '—' ); ?></td>
					<td style="white-space:nowrap;color:#646970">
						<?php echo $rcv ? esc_html( date_i18n( 'M j, Y', strtotime( $rcv ) ) ) : get_the_date( 'M j, Y' ); ?>
					</td>
					<td onclick="event.stopPropagation()" style="white-space:nowrap">
						<a href="<?php echo esc_url( $url ); ?>" class="button button-small">View</a>
						<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small">Mark <?php echo esc_html( $toggle_label ); ?></a>
						<a href="<?php echo esc_url( $del ); ?>" class="button button-small"
						   style="color:#b32d2e"
						   onclick="return confirm('Permanently delete this message?')">Delete</a>
					</td>
				</tr>
			<?php endwhile; wp_reset_postdata(); ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="oyc-empty">No messages yet.</p>
	<?php endif; ?>
	</div>

	<?php oyc_admin_pagination( $q->max_num_pages, $paged, 'oyc-messages', array( 's' => $search, 'filter' => $filter ) ); ?>
</div>
	<?php
}

/* ─────────────────────────────────────────────────────────────────────────────
 * 9. Message detail view
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_render_message_detail( $id ) {
	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'oyc_message' ) {
		echo '<div class="wrap"><p>Message not found.</p></div>';
		return;
	}

	// Auto-mark read when opened
	update_post_meta( $id, 'oyc_msg_read', '1' );

	$name    = get_post_meta( $id, 'oyc_msg_your_name',    true );
	$email   = get_post_meta( $id, 'oyc_msg_your_email',   true );
	$phone   = get_post_meta( $id, 'oyc_msg_your_phone',   true );
	$inquiry = get_post_meta( $id, 'oyc_msg_inquiry_type', true );
	$msg     = get_post_meta( $id, 'oyc_msg_your_message', true );
	$rcv     = get_post_meta( $id, 'oyc_msg_received_at',  true );

	$back  = admin_url( 'admin.php?page=oyc-messages' );
	$del   = wp_nonce_url( admin_url( 'admin.php?page=oyc-messages&action=delete&id=' . $id ), 'oyc_msg_' . $id );
	$unrd  = wp_nonce_url( admin_url( 'admin.php?page=oyc-messages&action=mark_unread&id=' . $id ), 'oyc_msg_' . $id );
	?>
<div class="wrap oyc-wrap">
	<h1>
		<a href="<?php echo esc_url( $back ); ?>" class="oyc-back-link">← Messages</a>
		<?php echo esc_html( $name ?: $post->post_title ); ?>
	</h1>
	<?php if ( $rcv ) : ?>
		<p style="color:#646970;margin-top:4px">Received <?php echo esc_html( date_i18n( 'F j, Y \a\t g:i a', strtotime( $rcv ) ) ); ?></p>
	<?php endif; ?>

	<div class="oyc-detail-wrap">

		<div class="oyc-detail-card">
			<div class="oyc-detail-card-hd"><h3>Sender</h3></div>
			<div>
				<?php
				oyc_admin_row( 'Name',         $name    ?: '' );
				oyc_admin_row( 'Email',        $email   ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '', true );
				oyc_admin_row( 'Phone',        $phone   ?: '' );
				oyc_admin_row( 'Inquiry Type', $inquiry ?: '' );
				?>
			</div>
		</div>

		<div class="oyc-detail-card">
			<div class="oyc-detail-card-hd"><h3>Message</h3></div>
			<div class="oyc-message-body"><?php echo esc_html( $msg ?: '(no message body)' ); ?></div>
		</div>

		<div class="oyc-actions">
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>?subject=Re:+<?php echo esc_attr( $inquiry ?: 'Your inquiry' ); ?>"
				   class="button button-primary">Reply by Email</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $unrd ); ?>" class="button">Mark as Unread</a>
			<a href="<?php echo esc_url( $back ); ?>" class="button">← Back to Messages</a>
			<a href="<?php echo esc_url( $del ); ?>" class="button oyc-delete-btn"
			   onclick="return confirm('Permanently delete this message?')">Delete</a>
		</div>

	</div>
</div>
	<?php
}

/* ─────────────────────────────────────────────────────────────────────────────
 * 10. Shared output helpers
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Render one label/value row in a detail card.
 *
 * @param string $label Human-readable label.
 * @param string $value The value to display.
 * @param bool   $raw   If true, output value as raw HTML (already escaped); otherwise escape it.
 */
function oyc_admin_row( $label, $value, $raw = false ) {
	if ( (string) $value === '' ) {
		return; // omit blank rows to keep cards tidy
	}
	echo '<div class="oyc-detail-row">';
	echo '<div class="oyc-detail-label">' . esc_html( $label ) . '</div>';
	echo '<div class="oyc-detail-value">';
	if ( $raw ) {
		echo wp_kses_post( $value );
	} else {
		echo nl2br( esc_html( $value ) );
	}
	echo '</div>';
	echo '</div>';
}

/**
 * Render simple numbered pagination links.
 *
 * @param int    $max_pages
 * @param int    $current
 * @param string $page_slug  The admin page slug (e.g. 'oyc-messages').
 * @param array  $extra_args Key/value pairs to append to each link (empty strings are skipped).
 */
function oyc_admin_pagination( $max_pages, $current, $page_slug, $extra_args = array() ) {
	if ( $max_pages <= 1 ) {
		return;
	}
	$qs = array_filter( $extra_args, fn( $v ) => (string) $v !== '' );
	echo '<div style="margin-top:12px;display:flex;gap:4px;align-items:center;">';
	echo '<span style="color:#646970;font-size:13px;margin-right:4px">Page:</span>';
	for ( $i = 1; $i <= $max_pages; $i++ ) {
		$url   = add_query_arg( array_merge( array( 'page' => $page_slug, 'paged' => $i ), $qs ), admin_url( 'admin.php' ) );
		$class = ( $i === $current ) ? 'button button-primary' : 'button';
		printf( '<a href="%s" class="%s">%d</a>', esc_url( $url ), $class, $i );
	}
	echo '</div>';
}

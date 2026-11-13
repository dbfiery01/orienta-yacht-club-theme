<?php
/**
 * Template Name: Officer — Members
 * Full user administration from the front end: create, edit, role changes,
 * password resets and deletion.
 *
 * Guardrails live in inc/officer-admin.php and are enforced on every action:
 * Administrator accounts are untouchable by non-administrators, the Administrator
 * role can never be granted by a non-administrator, nobody may delete their own
 * account, and the last Administrator cannot be removed.
 *
 * @package Orienta_Yacht_Club
 */

require_once get_template_directory() . '/inc/officer-admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

oyc_officer_guard( 'oyc_manage_members' );

$oyc_profile_fields = array(
	'oyc_member_since'           => __( 'Member Since (e.g. June 2022)', 'orienta-yacht-club' ),
	'billing_address_1'          => __( 'Street Address', 'orienta-yacht-club' ),
	'billing_address_2'          => __( 'Address Line 2', 'orienta-yacht-club' ),
	'billing_city'               => __( 'City', 'orienta-yacht-club' ),
	'billing_state'              => __( 'State', 'orienta-yacht-club' ),
	'billing_postcode'           => __( 'Postal Code', 'orienta-yacht-club' ),
	'billing_country'            => __( 'Country', 'orienta-yacht-club' ),
	'oyc_emergency_name'         => __( 'Emergency Contact Name', 'orienta-yacht-club' ),
	'oyc_emergency_phone'        => __( 'Emergency Contact Phone', 'orienta-yacht-club' ),
	'oyc_emergency_relationship' => __( 'Emergency Contact Relationship', 'orienta-yacht-club' ),
);

/* ── Create ───────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_user_create_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_user_create_nonce'] ) ), 'oyc_user_create' ) ) {

	$login = sanitize_user( wp_unslash( $_POST['new_login'] ?? '' ), true );
	$email = sanitize_email( wp_unslash( $_POST['new_email'] ?? '' ) );
	$first = sanitize_text_field( wp_unslash( $_POST['new_first'] ?? '' ) );
	$last  = sanitize_text_field( wp_unslash( $_POST['new_last'] ?? '' ) );
	$role  = sanitize_key( wp_unslash( $_POST['new_role'] ?? '' ) );

	$allowed = oyc_officer_assignable_roles();

	if ( '' === $login || ! is_email( $email ) ) {
		oyc_officer_notice( __( 'A username and a valid email address are required.', 'orienta-yacht-club' ), 'error' );
	} elseif ( ! isset( $allowed[ $role ] ) ) {
		oyc_officer_notice( __( 'That role cannot be assigned.', 'orienta-yacht-club' ), 'error' );
	} elseif ( username_exists( $login ) ) {
		oyc_officer_notice( __( 'That username is already taken.', 'orienta-yacht-club' ), 'error' );
	} elseif ( email_exists( $email ) ) {
		oyc_officer_notice( __( 'An account already uses that email address.', 'orienta-yacht-club' ), 'error' );
	} else {
		$new_id = wp_insert_user( array(
			'user_login' => $login,
			'user_email' => $email,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'first_name' => $first,
			'last_name'  => $last,
			'role'       => $role,
		) );

		if ( is_wp_error( $new_id ) ) {
			oyc_officer_notice(
				sprintf( __( 'The account could not be created: %s', 'orienta-yacht-club' ), $new_id->get_error_message() ),
				'error'
			);
		} else {
			wp_send_new_user_notifications( $new_id, 'user' );
			oyc_audit_log( 'user.create', sprintf( 'Created account "%s" (%s) with role %s', $login, $email, $role ), $new_id );
			oyc_officer_notice( sprintf(
				__( 'Account "%s" was created. A set-password email has been sent.', 'orienta-yacht-club' ),
				$login
			) );
		}
	}
}

/* ── Update ───────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_user_update_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_user_update_nonce'] ) ), 'oyc_user_update' ) ) {

	$uid = (int) ( $_POST['user_id'] ?? 0 );

	if ( ! oyc_officer_can_manage_user( $uid ) ) {
		oyc_officer_notice( __( 'You do not have permission to edit that account.', 'orienta-yacht-club' ), 'error' );
	} else {
		$update = array(
			'ID'           => $uid,
			'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
			'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
			'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			// Core user field rather than meta, and a textarea — sanitize_text_field
			// would flatten the line breaks.
			'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
		);

		$new_email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$existing  = email_exists( $new_email );

		if ( $new_email && is_email( $new_email ) && ( ! $existing || (int) $existing === $uid ) ) {
			$update['user_email'] = $new_email;
		} elseif ( $new_email ) {
			oyc_officer_notice( __( 'That email address is already in use — the address was left unchanged.', 'orienta-yacht-club' ), 'error' );
		}

		// Role changes: never above the actor's own level, never on your own account.
		$role    = sanitize_key( wp_unslash( $_POST['role'] ?? '' ) );
		$allowed = oyc_officer_assignable_roles();

		$target  = get_userdata( $uid );

		if ( $role && isset( $allowed[ $role ] ) && $target && ! in_array( $role, (array) $target->roles, true ) ) {
			if ( $uid === get_current_user_id() && ! current_user_can( 'administrator' ) ) {
				oyc_officer_notice( __( 'You cannot change your own role.', 'orienta-yacht-club' ), 'error' );
			} elseif ( ! oyc_officer_can_change_role_of( $target ) ) {
				oyc_officer_notice( __( 'Only a site administrator can change this account\'s role.', 'orienta-yacht-club' ), 'error' );
			} else {
				$update['role'] = $role;
			}
		}

		$result = wp_update_user( $update );

		if ( is_wp_error( $result ) ) {
			oyc_officer_notice(
				sprintf( __( 'The account could not be saved: %s', 'orienta-yacht-club' ), $result->get_error_message() ),
				'error'
			);
		} else {
			foreach ( array_keys( $oyc_profile_fields ) as $key ) {
				update_user_meta( $uid, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) );
			}

			// Club fields: locker, dinghy rack (inc/roster-import.php).
			foreach ( array_keys( oyc_member_club_fields() ) as $key ) {
				update_user_meta( $uid, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) );
			}

			// Membership card fields (inc/member-card.php).
			foreach ( array_keys( oyc_card_fields() ) as $key ) {
				update_user_meta( $uid, $key, oyc_card_clean_field( $key, wp_unslash( $_POST[ $key ] ?? '' ) ) );
			}
			$oyc_num  = (string) get_user_meta( $uid, 'oyc_member_number', true );
			$oyc_dupe = oyc_card_number_taken_by( $oyc_num, $uid );
			if ( $oyc_dupe ) {
				oyc_officer_notice( sprintf(
					/* translators: 1: member number, 2: other member's name. */
					__( 'Member number %1$s is also on %2$s\'s account. Please check which is right.', 'orienta-yacht-club' ),
					$oyc_num,
					$oyc_dupe[0]->display_name
				), 'error' );
			}

			$who = get_userdata( $uid );
			oyc_audit_log( 'user.update', sprintf( 'Updated account "%s"', $who ? $who->user_login : $uid ), $uid );
			oyc_officer_notice( __( 'Account updated.', 'orienta-yacht-club' ) );
		}
	}
}

/* ── Password reset ───────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_user_reset_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_user_reset_nonce'] ) ), 'oyc_user_reset' ) ) {

	$uid = (int) ( $_POST['user_id'] ?? 0 );

	if ( ! oyc_officer_can_manage_user( $uid ) ) {
		oyc_officer_notice( __( 'You do not have permission to manage that account.', 'orienta-yacht-club' ), 'error' );
	} else {
		$who  = get_userdata( $uid );
		$sent = function_exists( 'retrieve_password' )
			? retrieve_password( $who->user_login )
			: wp_send_new_user_notifications( $uid, 'user' );

		if ( is_wp_error( $sent ) ) {
			oyc_officer_notice(
				sprintf( __( 'The reset email could not be sent: %s', 'orienta-yacht-club' ), $sent->get_error_message() ),
				'error'
			);
		} else {
			oyc_audit_log( 'user.password_reset', sprintf( 'Sent password reset to "%s"', $who->user_login ), $uid );
			oyc_officer_notice( sprintf( __( 'A password reset email was sent to %s.', 'orienta-yacht-club' ), $who->user_email ) );
		}
	}
}

/* ── Confirm membership ───────────────────────────────────────────────────────
 * Sets WP-Members' `active` meta to 1, which is what
 * oyc_user_may_view_members_content() reads to allow the members' area.
 * ─────────────────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_user_confirm_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_user_confirm_nonce'] ) ), 'oyc_user_confirm' ) ) {

	$cid = (int) ( $_POST['user_id'] ?? 0 );

	if ( ! oyc_officer_can_manage_user( $cid ) ) {
		oyc_officer_notice( __( 'You do not have permission to manage that account.', 'orienta-yacht-club' ), 'error' );
	} else {
		$who = get_userdata( $cid );
		// A signup awaiting approval goes through WP-Members' own activation, so it
		// sends the "Registration is Moderated, User is Approved" email (and, when
		// the signup form has no password field, the password to log in with).
		// A long-standing account only gets the flag: it already has a password,
		// which WP-Members would otherwise replace.
		if ( function_exists( 'wpmem_activate_user' ) && 'pending' === oyc_user_approval_state( $cid ) ) {
			wpmem_activate_user( $cid, true, false );
		} else {
			update_user_meta( $cid, 'active', '1' );
		}
		oyc_audit_log( 'user.confirm', sprintf( 'Confirmed membership for "%s"', $who->user_login ), $cid );
		oyc_officer_notice( sprintf(
			__( '%s is confirmed and can now reach the members\' area.', 'orienta-yacht-club' ),
			$who->display_name
		) );
	}
}

/* ── Reissue membership card ──────────────────────────────────────────────────
 * New card key: every existing QR for this member (screenshots, printouts, a
 * lost phone) now reads NOT VALID. The member just reopens /member-card/.
 * ─────────────────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_card_reissue_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_card_reissue_nonce'] ) ), 'oyc_card_reissue' ) ) {

	$rid = (int) ( $_POST['user_id'] ?? 0 );

	if ( ! oyc_officer_can_manage_user( $rid ) ) {
		oyc_officer_notice( __( 'You do not have permission to manage that account.', 'orienta-yacht-club' ), 'error' );
	} else {
		$who = get_userdata( $rid );
		oyc_card_reissue( $rid );
		oyc_audit_log( 'user.card_reissue', sprintf( 'Reissued membership card for "%s"', $who->user_login ), $rid );
		oyc_officer_notice( sprintf(
			__( '%s has a new card. Old QR codes for this member now show NOT VALID.', 'orienta-yacht-club' ),
			$who->display_name
		) );
	}
}

/* ── Import member numbers (CSV) ─────────────────────────────────────────── */

$oyc_import = null;

if ( isset( $_POST['oyc_card_import_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_card_import_nonce'] ) ), 'oyc_card_import' ) ) {

	$file    = $_FILES['oyc_card_csv'] ?? null;
	$dry_run = ! empty( $_POST['oyc_card_dry_run'] );
	$do_dir  = ! empty( $_POST['oyc_roster_directory'] );

	if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		oyc_officer_notice( __( 'Please choose the roster file to upload.', 'orienta-yacht-club' ), 'error' );
	} elseif ( (int) $file['size'] > 2 * 1024 * 1024 ) {
		oyc_officer_notice( __( 'That file is too large (2 MB limit).', 'orienta-yacht-club' ), 'error' );
	} elseif ( ! preg_match( '/\.(xlsx|csv|txt)$/i', (string) $file['name'] ) ) {
		oyc_officer_notice( __( 'Please upload the roster as an Excel (.xlsx) or .csv file.', 'orienta-yacht-club' ), 'error' );
	} else {
		$oyc_import            = oyc_roster_import( $file['tmp_name'], (string) $file['name'], $dry_run, $do_dir );
		$oyc_import['dry_run'] = $dry_run;
		if ( $oyc_import['error'] ) {
			oyc_officer_notice( $oyc_import['error'], 'error' );
			$oyc_import = null;
		} else {
			oyc_officer_notice( sprintf(
				$dry_run
					/* translators: 1: rows that would change, 2: unchanged rows, 3: unmatched rows. */
					? __( 'Test run, nothing saved: %1$d members would be updated, %2$d already match, %3$d rows did not match an account.', 'orienta-yacht-club' )
					/* translators: 1: rows changed, 2: unchanged rows, 3: unmatched rows. */
					: __( 'Import done: %1$d members updated, %2$d already matched, %3$d rows did not match an account.', 'orienta-yacht-club' ),
				$oyc_import['updated'],
				$oyc_import['unchanged'],
				count( $oyc_import['unmatched'] )
			), $oyc_import['unmatched'] ? 'error' : 'success' );
			if ( null !== $oyc_import['directory'] ) {
				oyc_officer_notice( 0 === $oyc_import['directory']
					? __( 'Fleet Roster directory not changed: the file has no boat, spouse, phone or address columns.', 'orienta-yacht-club' )
					: sprintf(
						$dry_run
							/* translators: 1: members, 2: rows left out. */
							? __( 'Test run: the Fleet Roster directory would be replaced with %1$d members (%2$d Expelled/Deceased rows left out).', 'orienta-yacht-club' )
							/* translators: 1: members, 2: rows left out. */
							: __( 'Fleet Roster directory replaced with %1$d members (%2$d Expelled/Deceased rows left out).', 'orienta-yacht-club' ),
						$oyc_import['directory'],
						$oyc_import['dir_skipped']
					), 0 === $oyc_import['directory'] ? 'error' : 'success' );
			}
		}
	}
}

/* ── Delete ───────────────────────────────────────────────────────────────── */

if ( isset( $_POST['oyc_user_delete_nonce'] ) &&
     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_user_delete_nonce'] ) ), 'oyc_user_delete' ) ) {

	$uid     = (int) ( $_POST['user_id'] ?? 0 );
	$typed   = sanitize_user( wp_unslash( $_POST['confirm_login'] ?? '' ), true );
	$allowed = oyc_officer_can_delete_user( $uid );

	if ( is_wp_error( $allowed ) ) {
		oyc_officer_notice( $allowed->get_error_message(), 'error' );
	} else {
		$who = get_userdata( $uid );

		if ( ! $who || $typed !== $who->user_login ) {
			oyc_officer_notice( __( 'The username you typed did not match. Nothing was deleted.', 'orienta-yacht-club' ), 'error' );
		} else {
			$login = $who->user_login;

			// Reassign any authored content to the acting officer rather than orphaning it.
			wp_delete_user( $uid, get_current_user_id() );

			oyc_audit_log( 'user.delete', sprintf( 'Deleted account "%s"; content reassigned', $login ), $uid );
			oyc_officer_notice( sprintf( __( 'Account "%s" was deleted.', 'orienta-yacht-club' ), $login ) );
		}
	}
}

/* ── Data for the view ────────────────────────────────────────────────────── */

$oyc_edit_id = (int) ( $_GET['edit_user'] ?? 0 );
$oyc_edit    = ( $oyc_edit_id && oyc_officer_can_manage_user( $oyc_edit_id ) ) ? get_userdata( $oyc_edit_id ) : null;

if ( $oyc_edit_id && ! $oyc_edit ) {
	oyc_officer_notice( __( 'That account is not available for editing.', 'orienta-yacht-club' ), 'error' );
}

$oyc_search   = sanitize_text_field( wp_unslash( $_GET['muser'] ?? '' ) );
$oyc_upaged   = max( 1, (int) ( $_GET['upage'] ?? 1 ) );
$oyc_per_page = 25;

// A search that is exactly a member number finds that member.
$oyc_num_hit = ( '' !== $oyc_search && preg_match( '/^#?\w{1,20}$/', $oyc_search ) )
	? get_users( array( 'meta_key' => 'oyc_member_number', 'meta_value' => ltrim( $oyc_search, '#' ), 'fields' => 'ID', 'number' => 50 ) )
	: array();

$oyc_user_query = new WP_User_Query( $oyc_num_hit ? array(
	'include'        => $oyc_num_hit,
	'number'         => $oyc_per_page,
	'paged'          => $oyc_upaged,
	'orderby'        => 'display_name',
	'order'          => 'ASC',
) : array(
	'search'         => $oyc_search ? '*' . $oyc_search . '*' : '',
	'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
	'number'         => $oyc_per_page,
	'paged'          => $oyc_upaged,
	'orderby'        => 'display_name',
	'order'          => 'ASC',
) );

$oyc_users       = $oyc_user_query->get_results();
$oyc_total_users = $oyc_user_query->get_total();
$oyc_total_pages = (int) ceil( $oyc_total_users / $oyc_per_page );
$oyc_roles       = oyc_officer_assignable_roles();

get_header();
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Officer Area', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Members', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section officer-section">
	<div class="container">

		<?php oyc_officer_nav( 'members' ); ?>
		<?php oyc_officer_render_notices(); ?>

		<?php if ( $oyc_edit ) :
			$edit_roles = (array) $oyc_edit->roles;
			$edit_role  = $edit_roles ? $edit_roles[0] : '';
			$can_delete = oyc_officer_can_delete_user( $oyc_edit->ID );
		?>

			<div class="officer-panel">
				<h2 class="officer-panel__title">
					<?php printf( esc_html__( 'Editing %s', 'orienta-yacht-club' ), esc_html( $oyc_edit->display_name ) ); ?>
					<span class="officer-panel__sub"><?php echo esc_html( $oyc_edit->user_login ); ?></span>
				</h2>

				<form method="post" class="officer-form">
					<?php wp_nonce_field( 'oyc_user_update', 'oyc_user_update_nonce' ); ?>
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $oyc_edit->ID ); ?>" />

					<div class="officer-form__grid">
						<div class="officer-form__row">
							<label for="first_name"><?php esc_html_e( 'First Name', 'orienta-yacht-club' ); ?></label>
							<input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $oyc_edit->first_name ); ?>" />
						</div>
						<div class="officer-form__row">
							<label for="last_name"><?php esc_html_e( 'Last Name', 'orienta-yacht-club' ); ?></label>
							<input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $oyc_edit->last_name ); ?>" />
						</div>
						<div class="officer-form__row">
							<label for="display_name"><?php esc_html_e( 'Display Name', 'orienta-yacht-club' ); ?></label>
							<input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $oyc_edit->display_name ); ?>" />
						</div>
						<div class="officer-form__row">
							<label for="email"><?php esc_html_e( 'Email Address', 'orienta-yacht-club' ); ?></label>
							<input type="email" id="email" name="email" value="<?php echo esc_attr( $oyc_edit->user_email ); ?>" />
						</div>
					</div>

					<div class="officer-form__row">
						<label for="role"><?php esc_html_e( 'Role', 'orienta-yacht-club' ); ?></label>
						<?php if ( $oyc_edit->ID === get_current_user_id() && ! current_user_can( 'administrator' ) ) : ?>
							<p class="officer-form__locked"><?php echo esc_html( translate_user_role( wp_roles()->get_names()[ $edit_role ] ?? $edit_role ) ); ?>
								<span class="officer-form__hint"><?php esc_html_e( 'You cannot change your own role.', 'orienta-yacht-club' ); ?></span>
							</p>
						<?php elseif ( ! oyc_officer_can_change_role_of( $oyc_edit ) ) : ?>
							<p class="officer-form__locked"><?php echo esc_html( translate_user_role( wp_roles()->get_names()[ $edit_role ] ?? $edit_role ) ); ?>
								<span class="officer-form__hint"><?php esc_html_e( 'Only a site administrator can change this role.', 'orienta-yacht-club' ); ?></span>
							</p>
						<?php else : ?>
							<?php
							// Keep a role the picker no longer offers (e.g. Editor) if this
							// member already has it, so saving doesn't change it.
							$oyc_pick = $oyc_roles;
							if ( $edit_role && ! isset( $oyc_pick[ $edit_role ] ) ) {
								$oyc_pick = array( $edit_role => wp_roles()->get_names()[ $edit_role ] ?? $edit_role ) + $oyc_pick;
							}
							?>
							<select id="role" name="role">
								<?php foreach ( $oyc_pick as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $edit_role, $slug ); ?>>
										<?php echo esc_html( translate_user_role( $label ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</div>

					<div class="officer-form__row">
						<label for="description"><?php esc_html_e( 'About Me', 'orienta-yacht-club' ); ?></label>
						<textarea id="description" name="description" rows="3"><?php echo esc_textarea( $oyc_edit->description ); ?></textarea>
						<p class="officer-form__hint"><?php esc_html_e( 'The member can also edit this on their own profile page.', 'orienta-yacht-club' ); ?></p>
					</div>

					<h3 class="officer-form__subhead"><?php esc_html_e( 'Contact & Emergency Details', 'orienta-yacht-club' ); ?></h3>
					<div class="officer-form__grid">
						<?php foreach ( $oyc_profile_fields as $key => $label ) : ?>
							<div class="officer-form__row">
								<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
								       value="<?php echo esc_attr( get_user_meta( $oyc_edit->ID, $key, true ) ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>

					<h3 class="officer-form__subhead"><?php esc_html_e( 'Membership Card', 'orienta-yacht-club' ); ?></h3>
					<div class="officer-form__grid">
						<?php foreach ( oyc_card_fields() as $key => $label ) : ?>
							<div class="officer-form__row">
								<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
								       value="<?php echo esc_attr( get_user_meta( $oyc_edit->ID, $key, true ) ); ?>"
								       <?php echo 'oyc_membership_type' === $key ? 'list="oyc-membership-types"' : ''; ?>
								       <?php echo 'oyc_dues_paid_through' === $key ? 'inputmode="numeric" placeholder="' . esc_attr( current_time( 'Y' ) ) . '"' : ''; ?> />
							</div>
						<?php endforeach; ?>
					</div>
					<h3 class="officer-form__subhead"><?php esc_html_e( 'Club Facilities', 'orienta-yacht-club' ); ?></h3>
					<div class="officer-form__grid">
						<?php foreach ( oyc_member_club_fields() as $key => $label ) : ?>
							<div class="officer-form__row">
								<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
								       value="<?php echo esc_attr( get_user_meta( $oyc_edit->ID, $key, true ) ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>
					<datalist id="oyc-membership-types">
						<?php foreach ( oyc_card_membership_types() as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
					<p class="officer-form__hint">
						<?php esc_html_e( 'Shown on the member\'s digital card. Leave Dues Paid Through blank if the club does not track it here; when it is set, the card reads NOT VALID after that year ends.', 'orienta-yacht-club' ); ?>
						<a href="<?php echo esc_url( oyc_card_url( array( 'uid' => $oyc_edit->ID ) ) ); ?>"><?php esc_html_e( 'Preview their card', 'orienta-yacht-club' ); ?></a>
					</p>

					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save Changes', 'orienta-yacht-club' ); ?></button>
					<a class="officer-btn" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?></a>
				</form>
			</div>

			<div class="officer-panel">
				<h2 class="officer-panel__title"><?php esc_html_e( 'Account Actions', 'orienta-yacht-club' ); ?></h2>

				<form method="post" class="officer-form officer-form--inline">
					<?php wp_nonce_field( 'oyc_user_reset', 'oyc_user_reset_nonce' ); ?>
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $oyc_edit->ID ); ?>" />
					<button type="submit" class="officer-btn"><?php esc_html_e( 'Send password reset email', 'orienta-yacht-club' ); ?></button>
					<span class="officer-form__hint"><?php esc_html_e( 'Emails a secure link. You never see or set their password.', 'orienta-yacht-club' ); ?></span>
				</form>

				<form method="post" class="officer-form officer-form--inline"
				      onsubmit="return confirm('<?php echo esc_js( __( 'Reissue this card? Any saved screenshot or printout of the old QR code will stop working.', 'orienta-yacht-club' ) ); ?>');">
					<?php wp_nonce_field( 'oyc_card_reissue', 'oyc_card_reissue_nonce' ); ?>
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $oyc_edit->ID ); ?>" />
					<button type="submit" class="officer-btn"><?php esc_html_e( 'Reissue membership card', 'orienta-yacht-club' ); ?></button>
					<span class="officer-form__hint"><?php esc_html_e( 'For a lost phone or a shared screenshot. Old QR codes show NOT VALID; the member just reopens their card.', 'orienta-yacht-club' ); ?></span>
				</form>
			</div>

			<div class="officer-panel officer-panel--danger">
				<h2 class="officer-panel__title"><?php esc_html_e( 'Delete Account', 'orienta-yacht-club' ); ?></h2>

				<?php if ( is_wp_error( $can_delete ) ) : ?>
					<p class="officer-empty"><?php echo esc_html( $can_delete->get_error_message() ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'This permanently removes the account. Anything they authored is reassigned to you. This cannot be undone.', 'orienta-yacht-club' ); ?></p>

					<form method="post" class="officer-form officer-form--inline"
					      onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this account?', 'orienta-yacht-club' ) ); ?>');">
						<?php wp_nonce_field( 'oyc_user_delete', 'oyc_user_delete_nonce' ); ?>
						<input type="hidden" name="user_id" value="<?php echo esc_attr( $oyc_edit->ID ); ?>" />

						<div class="officer-form__row">
							<label for="confirm_login">
								<?php printf(
									esc_html__( 'Type %s to confirm', 'orienta-yacht-club' ),
									'<code>' . esc_html( $oyc_edit->user_login ) . '</code>'
								); ?>
							</label>
							<input type="text" id="confirm_login" name="confirm_login" autocomplete="off" required />
						</div>

						<button type="submit" class="officer-btn officer-btn--danger"><?php esc_html_e( 'Delete this account', 'orienta-yacht-club' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

		<?php else : ?>

			<div class="officer-panel">
				<form method="get" class="officer-search">
					<label class="screen-reader-text" for="muser"><?php esc_html_e( 'Search members', 'orienta-yacht-club' ); ?></label>
					<input type="search" id="muser" name="muser" value="<?php echo esc_attr( $oyc_search ); ?>"
					       placeholder="<?php esc_attr_e( 'Search by name, username, email or member #', 'orienta-yacht-club' ); ?>" />
					<button type="submit" class="officer-btn"><?php esc_html_e( 'Search', 'orienta-yacht-club' ); ?></button>
					<?php if ( $oyc_search ) : ?>
						<a class="officer-btn" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Clear', 'orienta-yacht-club' ); ?></a>
					<?php endif; ?>
					<a class="officer-btn officer-btn--export" href="<?php echo esc_url( oyc_roster_export_url() ); ?>" title="<?php esc_attr_e( 'Download the full club roster as a spreadsheet (opens in Excel)', 'orienta-yacht-club' ); ?>">&#8681; <?php esc_html_e( 'Export Roster', 'orienta-yacht-club' ); ?></a>
				</form>

				<p class="officer-count">
					<?php printf(
						esc_html( _n( '%d account', '%d accounts', $oyc_total_users, 'orienta-yacht-club' ) ),
						(int) $oyc_total_users
					); ?>
				</p>

				<div class="officer-table-wrap">
				<table class="officer-table officer-table--members">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name / Username', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Member #', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Role', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last login', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Profile', 'orienta-yacht-club' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $oyc_users as $u ) :
							$u_roles    = (array) $u->roles;
							$u_role     = $u_roles ? $u_roles[0] : '';
							$manageable = oyc_officer_can_manage_user( $u->ID );
							// display_name defaults to the username when no real name is
							// recorded, which made the Name column read as usernames.
							$u_name     = trim( $u->first_name . ' ' . $u->last_name );
							$u_name     = oyc_proper_case( '' !== $u_name ? $u_name : $u->display_name );
							$u_state    = oyc_user_approval_state( $u->ID );
						?>
							<tr>
								<td class="officer-table__name"><?php echo esc_html( $u_name ); ?><span class="officer-table__login-name"><?php echo esc_html( $u->user_login ); ?></span></td>
								<td class="officer-table__nowrap"><?php $u_num = (string) get_user_meta( $u->ID, 'oyc_member_number', true ); echo '' !== $u_num ? esc_html( $u_num ) : '—'; ?></td>
								<td class="officer-table__email"><a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a></td>
								<td><?php echo esc_html( $u_role ? translate_user_role( wp_roles()->get_names()[ $u_role ] ?? $u_role ) : '—' ); ?></td>
								<td>
									<span class="officer-status officer-status--<?php echo esc_attr( $u_state ); ?>">
										<?php echo esc_html( oyc_user_approval_label( $u_state ) ); ?>
									</span>
								</td>
								<td class="officer-table__last-login"><?php echo oyc_last_login_html( $u->ID ); ?></td>
								<td class="officer-table__actions">
									<?php if ( $manageable && ! oyc_user_is_confirmed( $u->ID ) ) : ?>
										<form method="post" style="display:inline">
											<?php wp_nonce_field( 'oyc_user_confirm', 'oyc_user_confirm_nonce' ); ?>
											<input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>" />
											<button type="submit" class="officer-btn officer-btn--confirm">
												<?php esc_html_e( 'Confirm', 'orienta-yacht-club' ); ?>
											</button>
										</form>
									<?php endif; ?>
									<?php if ( $manageable ) : ?>
										<a class="officer-btn" href="<?php echo esc_url( add_query_arg( 'edit_user', $u->ID, get_permalink() ) ); ?>">
											<?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?>
										</a>
										<?php if ( function_exists( 'oyc_card_url' ) ) : ?>
											<a class="officer-btn" href="<?php echo esc_url( oyc_card_url( array( 'uid' => $u->ID ) ) ); ?>" title="<?php esc_attr_e( 'View this member\'s membership card', 'orienta-yacht-club' ); ?>">
												<?php esc_html_e( 'Card', 'orienta-yacht-club' ); ?>
											</a>
										<?php endif; ?>
									<?php else : ?>
										<span class="officer-locked"><?php esc_html_e( 'Site administrator', 'orienta-yacht-club' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
				<?php oyc_last_login_local_script(); ?>

				<?php if ( $oyc_total_pages > 1 ) : ?>
					<div class="officer-pagination">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( array( 'muser' => $oyc_search, 'upage' => '%#%' ), get_permalink() ),
							'format'    => '',
							'current'   => $oyc_upaged,
							'total'     => $oyc_total_pages,
							'prev_text' => __( '&laquo; Previous', 'orienta-yacht-club' ),
							'next_text' => __( 'Next &raquo;', 'orienta-yacht-club' ),
						) );
						?>
					</div>
				<?php endif; ?>

				<?php
				// Roster members with no website account (Fleet Roster directory entries
				// not matched to any account by email or first + last name).
				$oyc_noacct = array();
				if ( function_exists( 'oyc_get_roster' ) ) {
					$oyc_norm   = fn( $v ) => strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $v ) );
					$oyc_emails = array();
					$oyc_names  = array();
					foreach ( get_users( array( 'fields' => array( 'ID', 'user_email' ) ) ) as $oyc_a ) {
						$oyc_emails[ strtolower( trim( $oyc_a->user_email ) ) ] = true;
						$oyc_names[ $oyc_norm( get_user_meta( $oyc_a->ID, 'first_name', true ) . get_user_meta( $oyc_a->ID, 'last_name', true ) ) ] = true;
					}
					$oyc_q = strtolower( trim( (string) $oyc_search ) );
					foreach ( oyc_get_roster() as $oyc_e ) {
						if ( ! is_array( $oyc_e ) ) {
							continue;
						}
						$oyc_em = strtolower( trim( (string) ( $oyc_e['email'] ?? '' ) ) );
						if ( ( '' !== $oyc_em && isset( $oyc_emails[ $oyc_em ] ) ) || isset( $oyc_names[ $oyc_norm( $oyc_e['name'] ?? '' ) ] ) ) {
							continue;
						}
						if ( '' !== $oyc_q ) {
							$oyc_hay = strtolower( implode( ' ', array( $oyc_e['name'] ?? '', $oyc_e['listed'] ?? '', $oyc_em, $oyc_e['number'] ?? '', $oyc_e['boat'] ?? '' ) ) );
							if ( false === strpos( $oyc_hay, $oyc_q ) ) {
								continue;
							}
						}
						$oyc_noacct[] = $oyc_e;
					}
					usort( $oyc_noacct, fn( $a, $b ) => strcasecmp( ( $a['last'] ?? '' ) . ( $a['name'] ?? '' ), ( $b['last'] ?? '' ) . ( $b['name'] ?? '' ) ) );
				}
				?>
				<?php if ( $oyc_noacct ) : ?>
					<details class="officer-noacct"<?php echo '' !== (string) $oyc_search ? ' open' : ''; ?>>
						<summary class="officer-count">
							<?php printf(
								/* translators: %d: number of roster members without a website account. */
								esc_html( _n( '%d roster member without a website account', '%d roster members without a website account', count( $oyc_noacct ), 'orienta-yacht-club' ) ),
								count( $oyc_noacct )
							); ?>
						</summary>
						<p class="officer-noacct__hint"><?php esc_html_e( 'On the club roster (Upload Roster) but not signed up on the website yet, so they have no login or card. They show in the Fleet Roster directory.', 'orienta-yacht-club' ); ?></p>
						<div class="officer-table-wrap">
						<table class="officer-table officer-table--members">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Name', 'orienta-yacht-club' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Member #', 'orienta-yacht-club' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Phone', 'orienta-yacht-club' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Member Type', 'orienta-yacht-club' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Role', 'orienta-yacht-club' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'orienta-yacht-club' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $oyc_noacct as $oyc_e ) :
									$oyc_ph = isset( $oyc_e['phones'] ) && is_array( $oyc_e['phones'] ) ? (string) reset( $oyc_e['phones'] ) : '';
									$oyc_em = (string) ( $oyc_e['email'] ?? '' );
								?>
									<tr>
										<td class="officer-table__name"><?php echo esc_html( oyc_proper_case( (string) ( $oyc_e['name'] ?? '' ) ) ); ?></td>
										<td class="officer-table__nowrap"><?php echo '' !== (string) ( $oyc_e['number'] ?? '' ) ? esc_html( $oyc_e['number'] ) : '—'; ?></td>
										<td class="officer-table__email"><?php if ( '' !== $oyc_em ) : ?><a href="mailto:<?php echo esc_attr( $oyc_em ); ?>"><?php echo esc_html( $oyc_em ); ?></a><?php else : ?>—<?php endif; ?></td>
										<td class="officer-table__nowrap"><?php echo '' !== $oyc_ph ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $oyc_ph ) ) . '">' . esc_html( $oyc_ph ) . '</a>' : '—'; ?></td>
										<td><?php echo esc_html( (string) ( $oyc_e['category'] ?? '' ) ); ?></td>
										<td><?php esc_html_e( 'Not Registered', 'orienta-yacht-club' ); ?></td>
										<td><span class="officer-status officer-status--none"><?php esc_html_e( 'No account', 'orienta-yacht-club' ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					</details>
				<?php endif; ?>
			</div>

			<details class="officer-panel officer-panel--collapsible">
				<summary class="officer-panel__title"><?php esc_html_e( 'Add a Member Account', 'orienta-yacht-club' ); ?></summary>

				<form method="post" class="officer-form">
					<?php wp_nonce_field( 'oyc_user_create', 'oyc_user_create_nonce' ); ?>

					<div class="officer-form__grid">
						<div class="officer-form__row">
							<label for="new_first"><?php esc_html_e( 'First Name', 'orienta-yacht-club' ); ?></label>
							<input type="text" id="new_first" name="new_first" />
						</div>
						<div class="officer-form__row">
							<label for="new_last"><?php esc_html_e( 'Last Name', 'orienta-yacht-club' ); ?></label>
							<input type="text" id="new_last" name="new_last" />
						</div>
						<div class="officer-form__row">
							<label for="new_login"><?php esc_html_e( 'Username', 'orienta-yacht-club' ); ?></label>
							<input type="text" id="new_login" name="new_login" autocomplete="off" required />
						</div>
						<div class="officer-form__row">
							<label for="new_email"><?php esc_html_e( 'Email Address', 'orienta-yacht-club' ); ?></label>
							<input type="email" id="new_email" name="new_email" required />
						</div>
					</div>

					<div class="officer-form__row">
						<label for="new_role"><?php esc_html_e( 'Role', 'orienta-yacht-club' ); ?></label>
						<select id="new_role" name="new_role">
							<?php foreach ( $oyc_roles as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( 'subscriber', $slug ); ?>>
									<?php echo esc_html( translate_user_role( $label ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<p class="officer-form__hint"><?php esc_html_e( 'The new member receives an email inviting them to set their own password. No password is created here.', 'orienta-yacht-club' ); ?></p>

					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Create Account', 'orienta-yacht-club' ); ?></button>
				</form>
			</details>

			<details class="officer-panel officer-panel--collapsible"<?php echo $oyc_import ? ' open' : ''; ?>>
				<summary class="officer-panel__title"><?php esc_html_e( 'Upload Roster', 'orienta-yacht-club' ); ?></summary>
				<div class="officer-import">

				<p><?php esc_html_e( 'Upload the club roster spreadsheet (.xlsx or .csv) as it is. Each row is matched to a website account by Email, then Username, then First + Last Name, and fills in Member No., Member Type, Member Since, Locker and Dinghy Rack on that member\'s profile. It can also replace the members-only Fleet Roster directory (names, spouse, boat, phones, address).', 'orienta-yacht-club' ); ?></p>
				<p class="officer-form__hint"><a href="<?php echo esc_url( oyc_roster_export_url() ); ?>"><?php esc_html_e( 'Export the current roster', 'orienta-yacht-club' ); ?></a> <?php esc_html_e( 'to get a spreadsheet in the same layout: edit it in Excel and upload it here.', 'orienta-yacht-club' ); ?></p>
				<p class="officer-form__hint"><?php esc_html_e( 'The heading row can be anywhere in the first 10 rows. Blank cells never erase what is already saved. Expelled and Deceased members are left out of the directory. Names, emails and addresses on accounts are not changed.', 'orienta-yacht-club' ); ?></p>

				<form method="post" enctype="multipart/form-data" class="officer-form">
					<?php wp_nonce_field( 'oyc_card_import', 'oyc_card_import_nonce' ); ?>
					<div class="officer-form__row">
						<label for="oyc_card_csv"><?php esc_html_e( 'Roster file (.xlsx or .csv)', 'orienta-yacht-club' ); ?></label>
						<input type="file" id="oyc_card_csv" name="oyc_card_csv" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required />
					</div>
					<div class="officer-form__row">
						<label><input type="checkbox" name="oyc_roster_directory" value="1" checked /> <?php esc_html_e( 'Also replace the Fleet Roster directory with this file', 'orienta-yacht-club' ); ?></label>
					</div>
					<div class="officer-form__row">
						<label><input type="checkbox" name="oyc_card_dry_run" value="1" checked /> <?php esc_html_e( 'Test run first (shows what would change, saves nothing)', 'orienta-yacht-club' ); ?></label>
					</div>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Upload', 'orienta-yacht-club' ); ?></button>
				</form>

				<?php if ( $oyc_import && $oyc_import['inactive'] ) : ?>
					<h3 class="officer-form__subhead"><?php esc_html_e( 'Expelled or Deceased members who still have an account', 'orienta-yacht-club' ); ?></h3>
					<ul class="officer-import-list">
						<?php foreach ( $oyc_import['inactive'] as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $oyc_import && ( $oyc_import['unmatched'] || $oyc_import['duplicates'] ) ) : ?>
					<?php if ( $oyc_import['unmatched'] ) : ?>
						<h3 class="officer-form__subhead"><?php esc_html_e( 'Rows with no matching account', 'orienta-yacht-club' ); ?></h3>
						<p class="officer-form__hint"><?php esc_html_e( 'These members have no website account, or their email and name differ from the account. Fix the account (or the roster) and upload again.', 'orienta-yacht-club' ); ?></p>
						<ul class="officer-import-list">
							<?php foreach ( array_slice( $oyc_import['unmatched'], 0, 200 ) as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $oyc_import['duplicates'] ) : ?>
						<h3 class="officer-form__subhead"><?php esc_html_e( 'Repeated member numbers', 'orienta-yacht-club' ); ?></h3>
						<ul class="officer-import-list">
							<?php foreach ( array_slice( $oyc_import['duplicates'], 0, 100 ) as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
				</div>
			</details>

		<?php endif; ?>

	</div>
</section>

<?php
get_footer();

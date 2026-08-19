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
	'billing_address_1'          => __( 'Street Address', 'orienta-yacht-club' ),
	'billing_address_2'          => __( 'Address Line 2', 'orienta-yacht-club' ),
	'billing_city'               => __( 'City', 'orienta-yacht-club' ),
	'billing_state'              => __( 'State', 'orienta-yacht-club' ),
	'billing_postcode'           => __( 'Postal Code', 'orienta-yacht-club' ),
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

		if ( $role && isset( $allowed[ $role ] ) ) {
			if ( $uid === get_current_user_id() && ! current_user_can( 'administrator' ) ) {
				oyc_officer_notice( __( 'You cannot change your own role.', 'orienta-yacht-club' ), 'error' );
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

$oyc_user_query = new WP_User_Query( array(
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
							<p class="officer-form__locked"><?php echo esc_html( translate_user_role( $oyc_roles[ $edit_role ] ?? $edit_role ) ); ?>
								<span class="officer-form__hint"><?php esc_html_e( 'You cannot change your own role.', 'orienta-yacht-club' ); ?></span>
							</p>
						<?php else : ?>
							<select id="role" name="role">
								<?php foreach ( $oyc_roles as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $edit_role, $slug ); ?>>
										<?php echo esc_html( translate_user_role( $label ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
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
					       placeholder="<?php esc_attr_e( 'Search by name, username or email', 'orienta-yacht-club' ); ?>" />
					<button type="submit" class="officer-btn"><?php esc_html_e( 'Search', 'orienta-yacht-club' ); ?></button>
					<?php if ( $oyc_search ) : ?>
						<a class="officer-btn" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Clear', 'orienta-yacht-club' ); ?></a>
					<?php endif; ?>
				</form>

				<p class="officer-count">
					<?php printf(
						esc_html( _n( '%d account', '%d accounts', $oyc_total_users, 'orienta-yacht-club' ) ),
						(int) $oyc_total_users
					); ?>
				</p>

				<table class="officer-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Username', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Role', 'orienta-yacht-club' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'orienta-yacht-club' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $oyc_users as $u ) :
							$u_roles    = (array) $u->roles;
							$u_role     = $u_roles ? $u_roles[0] : '';
							$manageable = oyc_officer_can_manage_user( $u->ID );
						?>
							<tr>
								<td><?php echo esc_html( $u->display_name ); ?></td>
								<td><?php echo esc_html( $u->user_login ); ?></td>
								<td><a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a></td>
								<td><?php echo esc_html( $u_role ? translate_user_role( wp_roles()->get_names()[ $u_role ] ?? $u_role ) : '—' ); ?></td>
								<td class="officer-table__actions">
									<?php if ( $manageable ) : ?>
										<a class="officer-btn" href="<?php echo esc_url( add_query_arg( 'edit_user', $u->ID, get_permalink() ) ); ?>">
											<?php esc_html_e( 'Edit', 'orienta-yacht-club' ); ?>
										</a>
									<?php else : ?>
										<span class="officer-locked"><?php esc_html_e( 'Site administrator', 'orienta-yacht-club' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

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
			</div>

			<div class="officer-panel">
				<h2 class="officer-panel__title"><?php esc_html_e( 'Add a Member Account', 'orienta-yacht-club' ); ?></h2>

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
			</div>

		<?php endif; ?>

	</div>
</section>

<?php
get_footer();

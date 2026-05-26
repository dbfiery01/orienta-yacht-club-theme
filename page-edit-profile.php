<?php
/**
 * Template Name: Edit Profile
 * Lets logged-in members update their profile and change their password.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( get_permalink() ) );
	exit;
}

$user        = wp_get_current_user();
$notices     = array(); // ['type' => 'success|error', 'msg' => '...']

/* ---------------------------------------------------------------
 * Process form submissions
 * --------------------------------------------------------------- */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

	// ---------- Profile Info ----------
	if ( isset( $_POST['oyc_profile_nonce'] ) &&
	     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_profile_nonce'] ) ), 'oyc_profile_save' ) ) {

		$update = array( 'ID' => $user->ID );

		$first_name   = sanitize_text_field( wp_unslash( $_POST['first_name']   ?? '' ) );
		$last_name    = sanitize_text_field( wp_unslash( $_POST['last_name']    ?? '' ) );
		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$description  = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$new_email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		$update['first_name']   = $first_name;
		$update['last_name']    = $last_name;
		$update['display_name'] = $display_name ?: "$first_name $last_name";
		$update['description']  = $description;

		// Email change — verify it's not already taken
		$email_changed = false;
		if ( $new_email && $new_email !== $user->user_email ) {
			if ( ! is_email( $new_email ) ) {
				$notices[] = array( 'type' => 'error', 'msg' => 'Please enter a valid email address.' );
			} elseif ( email_exists( $new_email ) ) {
				$notices[] = array( 'type' => 'error', 'msg' => 'That email address is already in use.' );
			} else {
				$update['user_email'] = $new_email;
				$email_changed = true;
			}
		}

		// Only save if no errors above
		if ( ! array_filter( $notices, fn( $n ) => $n['type'] === 'error' ) ) {
			$result = wp_update_user( $update );
			if ( is_wp_error( $result ) ) {
				$notices[] = array( 'type' => 'error', 'msg' => $result->get_error_message() );
			} else {
				$user = wp_get_current_user(); // refresh
				$msg  = 'Profile updated successfully.';
				if ( $email_changed ) {
					$msg .= ' Your email address has been updated.';
				}
				$notices[] = array( 'type' => 'success', 'msg' => $msg );
			}
		}
	}

	// ---------- Password Change ----------
	if ( isset( $_POST['oyc_password_nonce'] ) &&
	     wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oyc_password_nonce'] ) ), 'oyc_password_save' ) ) {

		$current_pw  = wp_unslash( $_POST['current_password']  ?? '' );
		$new_pw      = wp_unslash( $_POST['new_password']      ?? '' );
		$confirm_pw  = wp_unslash( $_POST['confirm_password']  ?? '' );

		if ( empty( $current_pw ) || empty( $new_pw ) || empty( $confirm_pw ) ) {
			$notices[] = array( 'type' => 'error', 'msg' => 'Please fill in all password fields.' );
		} elseif ( ! wp_check_password( $current_pw, $user->user_pass, $user->ID ) ) {
			$notices[] = array( 'type' => 'error', 'msg' => 'Your current password is incorrect.' );
		} elseif ( $new_pw !== $confirm_pw ) {
			$notices[] = array( 'type' => 'error', 'msg' => 'New passwords do not match.' );
		} elseif ( strlen( $new_pw ) < 8 ) {
			$notices[] = array( 'type' => 'error', 'msg' => 'New password must be at least 8 characters.' );
		} else {
			wp_set_password( $new_pw, $user->ID );
			// Re-authenticate so the session stays valid after password change
			$user = get_user_by( 'id', $user->ID );
			wp_set_auth_cookie( $user->ID, true );
			$notices[] = array( 'type' => 'success', 'msg' => 'Password changed successfully.' );
		}
	}
}

// Re-fetch user so form fields show current saved values
$user = wp_get_current_user();

get_header();
?>

<div class="page-hero page-hero--dashboard">
	<div class="container">
		<p class="page-hero-eyebrow">
			<a class="hero-back-link" href="<?php echo esc_url( home_url( '/members-area/' ) ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
				<?php esc_html_e( 'Back to Dashboard', 'orienta-yacht-club' ); ?>
			</a>
		</p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Edit Profile', 'orienta-yacht-club' ); ?></h1>
	</div>
</div>

<section class="section dashboard-section">
	<div class="container profile-layout">

		<?php if ( $notices ) : ?>
			<?php foreach ( $notices as $n ) : ?>
				<div class="profile-notice profile-notice--<?php echo esc_attr( $n['type'] ); ?>" role="alert">
					<?php if ( $n['type'] === 'success' ) : ?>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
					<?php else : ?>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					<?php endif; ?>
					<?php echo esc_html( $n['msg'] ); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- ===== Profile Info ===== -->
		<div class="profile-card">
			<h2 class="profile-card__title">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				<?php esc_html_e( 'Personal Information', 'orienta-yacht-club' ); ?>
			</h2>

			<form method="post" class="profile-form" novalidate>
				<?php wp_nonce_field( 'oyc_profile_save', 'oyc_profile_nonce' ); ?>

				<div class="profile-form__row">
					<div class="profile-form__group">
						<label for="first_name"><?php esc_html_e( 'First Name', 'orienta-yacht-club' ); ?></label>
						<input type="text" id="first_name" name="first_name"
						       value="<?php echo esc_attr( $user->first_name ); ?>"
						       autocomplete="given-name" required />
					</div>
					<div class="profile-form__group">
						<label for="last_name"><?php esc_html_e( 'Last Name', 'orienta-yacht-club' ); ?></label>
						<input type="text" id="last_name" name="last_name"
						       value="<?php echo esc_attr( $user->last_name ); ?>"
						       autocomplete="family-name" required />
					</div>
				</div>

				<div class="profile-form__group">
					<label for="display_name"><?php esc_html_e( 'Display Name', 'orienta-yacht-club' ); ?></label>
					<input type="text" id="display_name" name="display_name"
					       value="<?php echo esc_attr( $user->display_name ); ?>"
					       autocomplete="nickname" />
					<p class="profile-form__hint"><?php esc_html_e( 'This is how your name appears on the site.', 'orienta-yacht-club' ); ?></p>
				</div>

				<div class="profile-form__group">
					<label for="email"><?php esc_html_e( 'Email Address', 'orienta-yacht-club' ); ?></label>
					<input type="email" id="email" name="email"
					       value="<?php echo esc_attr( $user->user_email ); ?>"
					       autocomplete="email" required />
				</div>

				<div class="profile-form__group">
					<label for="description"><?php esc_html_e( 'About Me', 'orienta-yacht-club' ); ?> <span class="profile-form__optional">(optional)</span></label>
					<textarea id="description" name="description" rows="4"><?php echo esc_textarea( $user->description ); ?></textarea>
					<p class="profile-form__hint"><?php esc_html_e( 'A short bio visible to club staff.', 'orienta-yacht-club' ); ?></p>
				</div>

				<div class="profile-form__actions">
					<button type="submit" class="btn btn-primary">
						<?php esc_html_e( 'Save Changes', 'orienta-yacht-club' ); ?>
					</button>
					<a href="<?php echo esc_url( home_url( '/members-area/' ) ); ?>" class="btn btn-ghost-navy">
						<?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?>
					</a>
				</div>
			</form>
		</div>

		<!-- ===== Change Password ===== -->
		<div class="profile-card">
			<h2 class="profile-card__title">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				<?php esc_html_e( 'Change Password', 'orienta-yacht-club' ); ?>
			</h2>

			<form method="post" class="profile-form" novalidate id="password-form">
				<?php wp_nonce_field( 'oyc_password_save', 'oyc_password_nonce' ); ?>

				<div class="profile-form__group">
					<label for="current_password"><?php esc_html_e( 'Current Password', 'orienta-yacht-club' ); ?></label>
					<div class="profile-form__pw-wrap">
						<input type="password" id="current_password" name="current_password"
						       autocomplete="current-password" />
						<button type="button" class="pw-toggle" aria-label="<?php esc_attr_e( 'Show password', 'orienta-yacht-club' ); ?>" data-target="current_password">
							<svg class="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						</button>
					</div>
				</div>

				<div class="profile-form__row">
					<div class="profile-form__group">
						<label for="new_password"><?php esc_html_e( 'New Password', 'orienta-yacht-club' ); ?></label>
						<div class="profile-form__pw-wrap">
							<input type="password" id="new_password" name="new_password"
							       autocomplete="new-password" />
							<button type="button" class="pw-toggle" aria-label="<?php esc_attr_e( 'Show password', 'orienta-yacht-club' ); ?>" data-target="new_password">
								<svg class="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
							</button>
						</div>
						<div class="pw-strength-bar" id="pw-strength-bar"><span></span></div>
						<p class="profile-form__hint pw-strength-label" id="pw-strength-label"></p>
					</div>
					<div class="profile-form__group">
						<label for="confirm_password"><?php esc_html_e( 'Confirm New Password', 'orienta-yacht-club' ); ?></label>
						<div class="profile-form__pw-wrap">
							<input type="password" id="confirm_password" name="confirm_password"
							       autocomplete="new-password" />
							<button type="button" class="pw-toggle" aria-label="<?php esc_attr_e( 'Show password', 'orienta-yacht-club' ); ?>" data-target="confirm_password">
								<svg class="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
							</button>
						</div>
						<p class="profile-form__hint pw-match-hint" id="pw-match-hint"></p>
					</div>
				</div>

				<p class="profile-form__hint"><?php esc_html_e( 'Minimum 8 characters. Use a mix of letters, numbers and symbols for a stronger password.', 'orienta-yacht-club' ); ?></p>

				<div class="profile-form__actions">
					<button type="submit" class="btn btn-primary">
						<?php esc_html_e( 'Change Password', 'orienta-yacht-club' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Account info (read-only) -->
		<div class="profile-meta">
			<p><?php esc_html_e( 'Username', 'orienta-yacht-club' ); ?>: <strong><?php echo esc_html( $user->user_login ); ?></strong></p>
			<p><?php esc_html_e( 'Member since', 'orienta-yacht-club' ); ?>: <strong><?php echo esc_html( date( 'F Y', strtotime( $user->user_registered ) ) ); ?></strong></p>
		</div>

	</div>
</section>

<script>
(function () {
	// ── Show / Hide password toggles ──────────────────────────────
	document.querySelectorAll('.pw-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = document.getElementById(this.dataset.target);
			if (!input) return;
			var showing = input.type === 'text';
			input.type = showing ? 'password' : 'text';
			this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
			this.querySelector('.eye-icon').style.opacity = showing ? '1' : '0.45';
		});
	});

	// ── Password strength meter ───────────────────────────────────
	var newPw     = document.getElementById('new_password');
	var bar       = document.querySelector('#pw-strength-bar span');
	var label     = document.getElementById('pw-strength-label');
	var confirmPw = document.getElementById('confirm_password');
	var matchHint = document.getElementById('pw-match-hint');

	function scorePassword(pw) {
		if (!pw) return 0;
		var score = 0;
		if (pw.length >= 8)  score++;
		if (pw.length >= 12) score++;
		if (/[A-Z]/.test(pw)) score++;
		if (/[0-9]/.test(pw)) score++;
		if (/[^A-Za-z0-9]/.test(pw)) score++;
		return score; // 0-5
	}

	var levels = [
		{ pct: 0,   color: 'transparent', text: '' },
		{ pct: 20,  color: '#e74c3c',     text: 'Very weak' },
		{ pct: 40,  color: '#e67e22',     text: 'Weak' },
		{ pct: 60,  color: '#f1c40f',     text: 'Fair' },
		{ pct: 80,  color: '#2ecc71',     text: 'Strong' },
		{ pct: 100, color: '#27ae60',     text: 'Very strong' },
	];

	if (newPw && bar && label) {
		newPw.addEventListener('input', function () {
			var score = scorePassword(this.value);
			var lvl   = levels[score];
			bar.style.width           = lvl.pct + '%';
			bar.style.backgroundColor = lvl.color;
			label.textContent         = lvl.text;
			label.style.color         = lvl.color === 'transparent' ? '' : lvl.color;
			checkMatch();
		});
	}

	function checkMatch() {
		if (!confirmPw || !matchHint || !newPw) return;
		if (!confirmPw.value) { matchHint.textContent = ''; return; }
		if (confirmPw.value === newPw.value) {
			matchHint.textContent = '✓ Passwords match';
			matchHint.style.color = '#27ae60';
		} else {
			matchHint.textContent = '✗ Passwords do not match';
			matchHint.style.color = '#e74c3c';
		}
	}

	if (confirmPw) {
		confirmPw.addEventListener('input', checkMatch);
	}

	// Scroll to first error / success notice on page load
	var firstNotice = document.querySelector('.profile-notice');
	if (firstNotice) {
		firstNotice.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}
}());
</script>

<?php get_footer(); ?>

<?php
/**
 * Template Name: Bulletin Board
 * Members-only For Sale / Wanted board. Renders for a Page with slug
 * "bulletin-board", or at /bulletin-board/ with no Page (fallback in
 * inc/bulletin-board.php). Handlers + helpers live in inc/bulletin-board.php.
 *
 * @package Orienta_Yacht_Club
 */

// Members only — send guests to login, returning here afterward.
if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( oyc_bb_url() ) );
	exit;
}

get_header();

$me      = wp_get_current_user();
$notice  = isset( $_GET['bb'] ) ? sanitize_key( wp_unslash( $_GET['bb'] ) ) : '';
$edit_id = isset( $_GET['bb_edit'] ) ? (int) $_GET['bb_edit'] : 0;
$edit    = $edit_id ? get_post( $edit_id ) : null;
if ( $edit && ! oyc_bb_can_manage( $edit ) ) {
	$edit = null;
}
$posts = oyc_bb_listings();

$counts = array( 'sale' => 0, 'wanted' => 0, 'mine' => 0 );
foreach ( $posts as $p ) {
	$counts[ 'wanted' === get_post_meta( $p->ID, '_oyc_bb_type', true ) ? 'wanted' : 'sale' ]++;
	if ( (int) $p->post_author === $me->ID ) {
		$counts['mine']++;
	}
}

$f = array(
	'type'    => $edit ? get_post_meta( $edit->ID, '_oyc_bb_type', true ) : 'sale',
	'title'   => $edit ? $edit->post_title : '',
	'price'   => $edit ? get_post_meta( $edit->ID, '_oyc_bb_price', true ) : '',
	'desc'    => $edit ? $edit->post_content : '',
	'contact' => $edit ? get_post_meta( $edit->ID, '_oyc_bb_contact', true ) : $me->user_email,
	'photo'   => $edit ? (int) get_post_meta( $edit->ID, '_oyc_bb_photo', true ) : 0,
);
$editing_other = $edit && (int) $edit->post_author !== $me->ID;
// The form starts open when editing or after a form error; the "+ Post an item"
// button is hidden whenever the form is open (it has its own Close).
$compose_open  = $edit || in_array( $notice, array( 'missing', 'nophoto', 'badphoto' ), true );

$messages = array(
	'posted'   => array( 'ok',  __( 'Your post is up on the board.', 'orienta-yacht-club' ) ),
	'updated'  => array( 'ok',  __( 'Post updated.', 'orienta-yacht-club' ) ),
	'deleted'  => array( 'ok',  __( 'Post deleted.', 'orienta-yacht-club' ) ),
	'closed'   => array( 'ok',  __( 'Marked as done. It stays on the board, labelled, until you delete it.', 'orienta-yacht-club' ) ),
	'reopened' => array( 'ok',  __( 'Post is open again.', 'orienta-yacht-club' ) ),
	'current'  => array( 'ok',  __( 'Thanks! Your post is marked as still current.', 'orienta-yacht-club' ) ),
	'missing'  => array( 'err', __( 'Please fill in a title, a description and how to contact you.', 'orienta-yacht-club' ) ),
	'nophoto'  => array( 'err', __( 'For Sale posts need a photo of the item.', 'orienta-yacht-club' ) ),
	'badphoto' => array( 'err', __( 'That photo didn’t upload. Please use a JPG, PNG, GIF or WebP image under 12 MB.', 'orienta-yacht-club' ) ),
	/* translators: %d: max open posts per member. */
	'limit'    => array( 'err', sprintf( __( 'You already have %d open posts. Mark one Sold/Found or delete one first.', 'orienta-yacht-club' ), OYC_BB_MAX_ACTIVE ) ),
	'failed'   => array( 'err', __( 'Sorry, that didn’t save. Please try again.', 'orienta-yacht-club' ) ),
	'denied'   => array( 'err', __( 'You can only change your own posts.', 'orienta-yacht-club' ) ),
	'login'    => array( 'err', __( 'Please log in to use the bulletin board.', 'orienta-yacht-club' ) ),
);
?>

<div class="page-hero">
	<div class="container">
		<p class="page-hero-eyebrow"><?php esc_html_e( 'Members only', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Bulletin Board', 'orienta-yacht-club' ); ?></h1>
		<p class="page-hero-sub"><?php esc_html_e( 'Boats, gear and anything else members are selling or looking for.', 'orienta-yacht-club' ); ?></p>
	</div>
</div>

<section class="section">
	<div class="container page-content bb-page" id="board">

		<?php if ( isset( $messages[ $notice ] ) ) : ?>
			<div class="bb-notice bb-notice--<?php echo esc_attr( $messages[ $notice ][0] ); ?>" role="status">
				<?php echo esc_html( $messages[ $notice ][1] ); ?>
			</div>
		<?php endif; ?>

		<div class="bb-toolbar">
			<div class="bb-filters" role="toolbar" aria-label="<?php esc_attr_e( 'Show', 'orienta-yacht-club' ); ?>">
				<button type="button" class="bb-filter is-active" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'orienta-yacht-club' ); ?> <span class="bb-filter__n"><?php echo count( $posts ); ?></span></button>
				<button type="button" class="bb-filter" data-filter="sale" aria-pressed="false"><?php esc_html_e( 'For Sale', 'orienta-yacht-club' ); ?> <span class="bb-filter__n"><?php echo (int) $counts['sale']; ?></span></button>
				<button type="button" class="bb-filter" data-filter="wanted" aria-pressed="false"><?php esc_html_e( 'Wanted', 'orienta-yacht-club' ); ?> <span class="bb-filter__n"><?php echo (int) $counts['wanted']; ?></span></button>
				<?php if ( $counts['mine'] ) : ?>
					<button type="button" class="bb-filter" data-filter="mine" aria-pressed="false"><?php esc_html_e( 'My posts', 'orienta-yacht-club' ); ?> <span class="bb-filter__n"><?php echo (int) $counts['mine']; ?></span></button>
				<?php endif; ?>
			</div>
			<a class="btn btn-primary bb-new-btn" href="#bb-form"<?php echo $compose_open ? ' hidden' : ''; ?>><?php esc_html_e( '+ Post an item', 'orienta-yacht-club' ); ?></a>
		</div>

		<!-- Post / edit form -->
		<details class="bb-compose" id="bb-form"<?php echo $compose_open ? ' open' : ''; ?>>
			<summary class="bb-compose__summary"><?php echo esc_html( $edit ? __( 'Edit post', 'orienta-yacht-club' ) : __( 'Post an item', 'orienta-yacht-club' ) ); ?> <span class="bb-compose__close"><?php esc_html_e( 'Close', 'orienta-yacht-club' ); ?></span></summary>
			<form class="bb-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oyc_bb_save">
				<?php if ( $edit ) : ?>
					<input type="hidden" name="listing_id" value="<?php echo (int) $edit->ID; ?>">
				<?php endif; ?>
				<?php wp_nonce_field( 'oyc_bb_save' ); ?>

				<?php if ( $editing_other ) : ?>
					<div class="bb-form__officer"><?php echo esc_html( sprintf( /* translators: %s: member name */ __( 'You are editing a post by %s (officer edit).', 'orienta-yacht-club' ), get_the_author_meta( 'display_name', $edit->post_author ) ) ); ?></div>
				<?php endif; ?>

				<div class="bb-type" role="radiogroup" aria-label="<?php esc_attr_e( 'Type of post', 'orienta-yacht-club' ); ?>">
					<label class="bb-type__opt bb-type__opt--sale"><input type="radio" name="oyc_bb_type" value="sale" <?php checked( 'wanted' !== $f['type'] ); ?>> <span><?php esc_html_e( 'For Sale', 'orienta-yacht-club' ); ?></span></label>
					<label class="bb-type__opt bb-type__opt--wanted"><input type="radio" name="oyc_bb_type" value="wanted" <?php checked( 'wanted' === $f['type'] ); ?>> <span><?php esc_html_e( 'Wanted', 'orienta-yacht-club' ); ?></span></label>
				</div>

				<div class="bb-form__grid">
					<label class="bb-field bb-field--wide">
						<span class="bb-field__label"><?php esc_html_e( 'Title', 'orienta-yacht-club' ); ?></span>
						<input type="text" name="oyc_bb_title" maxlength="80" required value="<?php echo esc_attr( $f['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. 9.9 hp Yamaha outboard, 2019', 'orienta-yacht-club' ); ?>">
					</label>
					<label class="bb-field">
						<span class="bb-field__label" data-sale="<?php esc_attr_e( 'Price', 'orienta-yacht-club' ); ?>" data-wanted="<?php esc_attr_e( 'Budget (optional)', 'orienta-yacht-club' ); ?>"><?php echo esc_html( 'wanted' === $f['type'] ? __( 'Budget (optional)', 'orienta-yacht-club' ) : __( 'Price', 'orienta-yacht-club' ) ); ?></span>
						<input type="text" name="oyc_bb_price" maxlength="40" value="<?php echo esc_attr( $f['price'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. $850 OBO, Free', 'orienta-yacht-club' ); ?>">
					</label>
					<label class="bb-field bb-field--wide">
						<span class="bb-field__label"><?php esc_html_e( 'Description', 'orienta-yacht-club' ); ?></span>
						<textarea name="oyc_bb_desc" rows="5" maxlength="1500" required placeholder="<?php esc_attr_e( 'Condition, size, where it can be seen, pick-up details…', 'orienta-yacht-club' ); ?>"><?php echo esc_textarea( $f['desc'] ); ?></textarea>
					</label>
					<label class="bb-field">
						<span class="bb-field__label"><?php esc_html_e( 'How to contact you', 'orienta-yacht-club' ); ?></span>
						<textarea name="oyc_bb_contact" rows="3" maxlength="200" required placeholder="<?php esc_attr_e( 'Phone and/or email', 'orienta-yacht-club' ); ?>"><?php echo esc_textarea( $f['contact'] ); ?></textarea>
					</label>
					<div class="bb-field bb-field--wide">
						<span class="bb-field__label" data-sale="<?php esc_attr_e( 'Photo', 'orienta-yacht-club' ); ?>" data-wanted="<?php esc_attr_e( 'Photo (optional)', 'orienta-yacht-club' ); ?>"><?php echo esc_html( 'wanted' === $f['type'] ? __( 'Photo (optional)', 'orienta-yacht-club' ) : __( 'Photo', 'orienta-yacht-club' ) ); ?></span>
						<?php if ( $f['photo'] ) : ?>
							<div class="bb-current-photo">
								<img src="<?php echo esc_url( wp_get_attachment_image_url( $f['photo'], 'thumbnail' ) ); ?>" alt="">
								<label><input type="checkbox" name="oyc_bb_remove_photo" value="1"> <?php esc_html_e( 'Remove this photo', 'orienta-yacht-club' ); ?></label>
							</div>
						<?php endif; ?>
						<input type="file" name="oyc_bb_photo" accept="image/*"<?php echo ( ! $f['photo'] && 'wanted' !== $f['type'] ) ? ' required' : ''; ?>>
						<span class="bb-note"><?php echo esc_html( $f['photo'] ? __( 'Choose a new image to replace the current one. JPG, PNG, GIF or WebP, up to 12 MB.', 'orienta-yacht-club' ) : __( 'One image, JPG, PNG, GIF or WebP, up to 12 MB.', 'orienta-yacht-club' ) ); ?></span>
					</div>
				</div>

				<p class="bb-upload-error" role="alert" hidden></p>
				<div class="bb-form__actions">
					<button type="submit" class="btn btn-primary"><?php echo esc_html( $edit ? __( 'Save changes', 'orienta-yacht-club' ) : __( 'Post to the board', 'orienta-yacht-club' ) ); ?></button>
					<?php if ( $edit ) : ?>
						<a class="btn btn-sm bb-btn" href="<?php echo esc_url( oyc_bb_url() ); ?>"><?php esc_html_e( 'Cancel', 'orienta-yacht-club' ); ?></a>
					<?php endif; ?>
					<span class="bb-note"><?php esc_html_e( 'Only logged-in members can see the board. Your post goes up right away.', 'orienta-yacht-club' ); ?></span>
				</div>
			</form>
		</details>

		<!-- The board -->
		<?php if ( $posts ) : ?>
			<div class="bb-board">
				<?php foreach ( $posts as $p ) { oyc_bb_card( $p ); } ?>
			</div>
			<p class="bb-empty bb-empty--filtered" hidden><?php esc_html_e( 'Nothing here yet.', 'orienta-yacht-club' ); ?></p>
		<?php else : ?>
			<div class="bb-board bb-board--empty">
				<p class="bb-empty"><?php esc_html_e( 'The board is empty. Selling something, or looking for something? Be the first to post.', 'orienta-yacht-club' ); ?></p>
			</div>
		<?php endif; ?>

		<script>
		(function(){
			// Filter tabs.
			var cards = document.querySelectorAll('.bb-card');
			var empty = document.querySelector('.bb-empty--filtered');
			document.querySelectorAll('.bb-filter').forEach(function(b){
				b.addEventListener('click', function(){
					var f = b.getAttribute('data-filter'), shown = 0;
					document.querySelectorAll('.bb-filter').forEach(function(o){ var on = o === b; o.classList.toggle('is-active', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
					cards.forEach(function(c){
						var ok = f === 'all' || (f === 'mine' ? c.hasAttribute('data-mine') : c.getAttribute('data-type') === f);
						c.hidden = !ok; if (ok) shown++;
					});
					if (empty) empty.hidden = shown > 0;
				});
			});

			// "+ Post an item" opens the form.
			var compose = document.getElementById('bb-form');
			var newBtn = document.querySelector('.bb-new-btn');
			var EDITING = <?php echo $edit ? 'true' : 'false'; ?>, BOARD = <?php echo wp_json_encode( oyc_bb_url() ); ?>;
			if (newBtn && compose) compose.addEventListener('toggle', function(){
				// Closing an edit leaves edit mode, so "+ Post an item" starts a fresh post.
				if (EDITING && !compose.open) { window.location.href = BOARD; return; }
				newBtn.hidden = compose.open;
			});
			if (newBtn && compose) newBtn.addEventListener('click', function(e){
				e.preventDefault();
				compose.open = !compose.open;
				if (compose.open) { compose.scrollIntoView({ behavior: 'smooth', block: 'start' }); var t = compose.querySelector('input[name="oyc_bb_title"]'); if (t) setTimeout(function(){ t.focus({ preventScroll: true }); }, 300); }
			});

			var form = document.querySelector('.bb-form');
			if (!form) return;
			var fileInput = form.querySelector('input[type="file"]');
			var hasPhoto = !!form.querySelector('.bb-current-photo');

			// For Sale needs a photo; Wanted doesn't. Swap the labels to match.
			function syncType(){
				var t = (form.querySelector('input[name="oyc_bb_type"]:checked') || {}).value || 'sale';
				form.querySelectorAll('[data-sale]').forEach(function(l){ l.textContent = l.getAttribute(t === 'wanted' ? 'data-wanted' : 'data-sale'); });
				if (fileInput) fileInput.required = (t === 'sale' && !hasPhoto);
			}
			form.querySelectorAll('input[name="oyc_bb_type"]').forEach(function(r){ r.addEventListener('change', syncType); });
			syncType();

			// Show the price as currency as soon as the member leaves the field
			// (the server applies the same formatting when it saves).
			var priceIn = form.querySelector('input[name="oyc_bb_price"]');
			if (priceIn) priceIn.addEventListener('blur', function(){
				var m = priceIn.value.trim().match(/^\$?\s*([\d,]*\.?\d+)\s*(k)?\b(.*)$/i);
				if (!m) return;
				var n = parseFloat(m[1].replace(/,/g, '')) * (m[2] ? 1000 : 1);
				var whole = Math.floor(n) === n;
				var out = '$' + n.toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 });
				var rest = m[3].trim();
				priceIn.value = rest ? out + ' ' + rest : out;
			});

			// Shrink big photos in the browser before upload (same as the Photo Gallery).
			var btn = form.querySelector('button[type="submit"]');
			var errEl = form.querySelector('.bb-upload-error');
			var SAVING = <?php echo wp_json_encode( __( 'Saving…', 'orienta-yacht-club' ) ); ?>;
			var TOO_BIG = <?php echo wp_json_encode( __( 'That photo is too large (max 12 MB). Please choose a smaller image.', 'orienta-yacht-club' ) ); ?>;
			var MAX_EDGE = 2048, MAX_BYTES = 12 * 1024 * 1024, ready = false;
			function shrink(file){
				return new Promise(function(resolve){
					if (!file.type || file.type.indexOf('image/') !== 0) { resolve(file); return; }
					var url = URL.createObjectURL(file), img = new Image();
					img.onload = function(){
						var w = img.naturalWidth, h = img.naturalHeight; URL.revokeObjectURL(url);
						if (!w || !h || Math.max(w, h) <= MAX_EDGE) { resolve(file); return; }
						var sc = MAX_EDGE / Math.max(w, h), c = document.createElement('canvas');
						c.width = Math.round(w * sc); c.height = Math.round(h * sc);
						try { c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); } catch (e) { resolve(file); return; }
						c.toBlob(function(blob){ resolve(blob ? new File([blob], (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.85);
					};
					img.onerror = function(){ URL.revokeObjectURL(url); resolve(file); };
					img.src = url;
				});
			}
			form.addEventListener('submit', function(e){
				if (ready) return;
				e.preventDefault();
				if (errEl) errEl.hidden = true;
				if (btn) { btn.classList.add('is-loading'); btn.disabled = true; btn.textContent = SAVING; }
				var f = fileInput && fileInput.files && fileInput.files[0];
				(f ? shrink(f) : Promise.resolve(null)).then(function(out){
					if (out && out.size > MAX_BYTES) {
						if (errEl) { errEl.textContent = TOO_BIG; errEl.hidden = false; }
						if (btn) { btn.classList.remove('is-loading'); btn.disabled = false; btn.textContent = <?php echo wp_json_encode( $edit ? __( 'Save changes', 'orienta-yacht-club' ) : __( 'Post to the board', 'orienta-yacht-club' ) ); ?>; }
						return;
					}
					if (out && out !== f && window.DataTransfer) {
						try { var dt = new DataTransfer(); dt.items.add(out); fileInput.files = dt.files; } catch (err) {}
					}
					ready = true;
					HTMLFormElement.prototype.submit.call(form);
				});
			});

			document.querySelectorAll('.bb-card__actions form').forEach(function(af){
				af.addEventListener('submit', function(ev){ if (ev.defaultPrevented) return; var b = af.querySelector('button'); if (b) b.classList.add('is-loading'); });
			});
		})();
		</script>

	</div>
</section>

<?php get_footer(); ?>

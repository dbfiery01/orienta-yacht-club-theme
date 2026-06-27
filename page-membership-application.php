<?php
/**
 * Template Name: Membership Application
 * Full membership application form — open to the public.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<div class="page-hero page-hero--apply">
	<div class="container">
		<p class="kicker on-dark"><?php esc_html_e( 'Join Orienta Yacht Club', 'orienta-yacht-club' ); ?></p>
		<h1 class="page-hero-title"><?php esc_html_e( 'Membership Application', 'orienta-yacht-club' ); ?></h1>
		<p class="page-hero-sub"><?php esc_html_e( 'Complete the form below to begin the application process. The Membership Chair will follow up with next steps.', 'orienta-yacht-club' ); ?></p>
	</div>
</div>

<section class="section section-tinted">
	<div class="container apply-layout">

		<!-- Sidebar -->
		<aside class="apply-sidebar">
			<div class="apply-info-card">
				<h3><?php esc_html_e( 'Before You Apply', 'orienta-yacht-club' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Membership requires sponsorship by two current members in good standing.', 'orienta-yacht-club' ); ?></li>
					<li><?php esc_html_e( "If you don't know any members yet, the Membership Chair can introduce you.", 'orienta-yacht-club' ); ?></li>
					<li><?php esc_html_e( 'Applications are reviewed at the monthly Board meeting.', 'orienta-yacht-club' ); ?></li>
					<li><?php esc_html_e( 'An application fee is due upon submission of the formal application.', 'orienta-yacht-club' ); ?></li>
				</ul>
				<div class="apply-contact-block">
					<p class="apply-contact-label"><?php esc_html_e( 'Questions?', 'orienta-yacht-club' ); ?></p>
					<p><?php esc_html_e( 'Contact the Membership Chair:', 'orienta-yacht-club' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-ghost-navy btn-sm">
						<?php esc_html_e( 'Send a Message', 'orienta-yacht-club' ); ?>
					</a>
				</div>
			</div>

			<div class="apply-steps">
				<h4><?php esc_html_e( 'Application Steps', 'orienta-yacht-club' ); ?></h4>
				<ol class="apply-steps-list">
					<li class="apply-step apply-step--active"><?php esc_html_e( 'Submit this form', 'orienta-yacht-club' ); ?></li>
					<li class="apply-step"><?php esc_html_e( 'Visit the club', 'orienta-yacht-club' ); ?></li>
					<li class="apply-step"><?php esc_html_e( 'Secure two sponsors', 'orienta-yacht-club' ); ?></li>
					<li class="apply-step"><?php esc_html_e( 'Board review', 'orienta-yacht-club' ); ?></li>
					<li class="apply-step"><?php esc_html_e( 'Welcome to OYC!', 'orienta-yacht-club' ); ?></li>
				</ol>
			</div>
		</aside>

		<!-- Form -->
		<div class="apply-form-wrap">
			<?php
			// Find the "Membership Application" Contact Form 7 form by title so this
			// works across staging/production without a hard-coded ID. CF7 6.x uses a
			// hash in the shortcode (stored in the _hash post meta).
			$oyc_cf7 = get_posts( array(
				'post_type'   => 'wpcf7_contact_form',
				'title'       => 'Membership Application',
				'post_status' => 'publish',
				'numberposts' => 1,
			) );
			if ( $oyc_cf7 ) {
				$oyc_hash = get_post_meta( $oyc_cf7[0]->ID, '_hash', true );
				$oyc_id   = $oyc_hash ? $oyc_hash : $oyc_cf7[0]->ID;
				echo do_shortcode( '[contact-form-7 id="' . esc_attr( $oyc_id ) . '" title="Membership Application"]' );
			} else {
				echo '<p class="app-form-missing">The membership application form is being set up. Please check back soon, or contact the Membership Chair below.</p>';
			}
			?>
		</div>

	</div>
</section>

<script>
(function () {
	// ── Duplicate email check ─────────────────────────────────────
	// Runs outside CF7's pipeline: a plain admin-ajax.php call on blur,
	// with the result cached so the submit-time check needs no extra round-trip.

	var ajaxUrl  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var dupeCache = { email: null, isDupe: false };

	function checkEmailDupe( email, callback ) {
		if ( !email ) { callback( false ); return; }
		if ( dupeCache.email === email ) { callback( dupeCache.isDupe ); return; }

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.onload = function () {
			var isDupe = false;
			try { isDupe = !!JSON.parse( xhr.responseText ).duplicate; } catch (e) {}
			dupeCache = { email: email, isDupe: isDupe };
			callback( isDupe );
		};
		xhr.onerror = function () { callback( false ); };
		xhr.send( 'action=oyc_check_email&email=' + encodeURIComponent( email ) );
	}

	function showDupeError( field ) {
		var wrap = field.closest( '.app-field' );
		if ( wrap ) { wrap.classList.add( 'app-field--invalid' ); }
		var tip = field.parentNode.querySelector( '.oyc-dupe-tip' );
		if ( !tip ) {
			tip = document.createElement( 'span' );
			tip.className = 'oyc-dupe-tip wpcf7-not-valid-tip';
			tip.setAttribute( 'aria-live', 'polite' );
			field.parentNode.appendChild( tip );
		}
		tip.textContent = 'An application has already been submitted with this email address. The Membership Committee will be in touch with you soon.';
	}

	function clearDupeError( field ) {
		var wrap = field.closest( '.app-field' );
		if ( wrap ) { wrap.classList.remove( 'app-field--invalid' ); }
		var tip = field.parentNode.querySelector( '.oyc-dupe-tip' );
		if ( tip ) { tip.remove(); }
	}

	function initDupeCheck() {
		var field = document.querySelector( 'input[name="email"]' );
		if ( !field ) { setTimeout( initDupeCheck, 200 ); return; }
		var form  = field.closest( 'form' );

		// Instant feedback when the user leaves the email field
		field.addEventListener( 'blur', function () {
			var email = this.value.trim();
			clearDupeError( this );
			if ( !email ) return;
			var self = this;
			checkEmailDupe( email, function ( isDupe ) {
				if ( isDupe ) { showDupeError( self ); }
			} );
		} );

		// Clear error and invalidate cache when they start retyping
		field.addEventListener( 'input', function () {
			dupeCache = { email: null, isDupe: false };
			clearDupeError( this );
		} );

		// Block CF7 before it starts if a duplicate is already confirmed
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var email = field.value.trim();
				if ( dupeCache.email === email && dupeCache.isDupe ) {
					e.preventDefault();
					e.stopImmediatePropagation();
					showDupeError( field );
					field.focus();
				}
			}, true ); // capture phase — fires before CF7's listener
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initDupeCheck );
	} else {
		initDupeCheck();
	}
	document.addEventListener( 'wpcf7domloaded', initDupeCheck );

	// ── Phone number auto-formatter ───────────────────────────────
	// Formats any tel input to (###) ###-#### as the user types.
	// CF7's validator strips parens/spaces before checking, so this
	// format passes its regex and is visually clear to the applicant.

	function formatPhone(raw) {
		var digits = raw.replace(/\D/g, '').slice(0, 10);
		if (digits.length < 4)  return digits;
		if (digits.length < 7)  return '(' + digits.slice(0,3) + ') ' + digits.slice(3);
		return '(' + digits.slice(0,3) + ') ' + digits.slice(3,6) + '-' + digits.slice(6);
	}

	function attachPhoneFormatter(input) {
		input.setAttribute('inputmode', 'tel');
		input.addEventListener('input', function () {
			var pos    = this.selectionStart;
			var before = this.value.length;
			this.value = formatPhone(this.value);
			// Keep cursor roughly in place after reformatting
			var delta  = this.value.length - before;
			this.setSelectionRange(pos + delta, pos + delta);
		});
		input.addEventListener('blur', function () {
			if (this.value) this.value = formatPhone(this.value);
		});
	}

	function initPhoneFormatters() {
		document.querySelectorAll('input[type="tel"]').forEach(function (inp) {
			if (!inp.dataset.phoneFormatted) {
				attachPhoneFormatter(inp);
				inp.dataset.phoneFormatted = '1';
			}
		});
	}

	// Run now and after CF7 renders (it injects inputs async)
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPhoneFormatters);
	} else {
		initPhoneFormatters();
	}
	// Also catch any late CF7 re-renders
	document.addEventListener('wpcf7domloaded', initPhoneFormatters);

	// ── Conditional field show/hide ───────────────────────────────
	// Fields with [data-shows-when="fieldname=Value"] are hidden by default
	// and revealed when the named radio group matches the value.

	function initConditionals() {
		var conditionals = document.querySelectorAll('.app-conditional');
		if (!conditionals.length) {
			setTimeout(initConditionals, 150);
			return;
		}

		conditionals.forEach(function (el) {
			var rule = el.getAttribute('data-shows-when'); // "fieldname=Value"
			if (!rule) return;

			var parts    = rule.split('=');
			var field    = parts[0];
			var showVal  = parts[1];

			// Hide by default (CSS also hides, this is JS insurance)
			el.style.display = 'none';

			var radios = document.querySelectorAll('input[name="' + field + '"]');
			if (!radios.length) return;

			function update() {
				var checked = document.querySelector('input[name="' + field + '"]:checked');
				var val = checked ? checked.value : '';
				var show = (val === showVal);
				el.style.display = show ? '' : 'none';
				// Clear hidden required fields so CF7 validation doesn't block submit
				if (!show) {
					el.querySelectorAll('input, textarea, select').forEach(function (inp) {
						inp.value = '';
					});
				}
			}

			radios.forEach(function (r) { r.addEventListener('change', update); });
			update(); // run once on init to respect any pre-filled state
		});
	}

	// CF7 renders asynchronously; wait for it
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initConditionals);
	} else {
		initConditionals();
	}

	// ── Competence rating: highlight selected number ──────────────
	function initRatingField() {
		var ratingInput = document.querySelector('input[name="competence-rating"]');
		if (!ratingInput) { setTimeout(initRatingField, 150); return; }

		// Wrap in a custom rating widget
		var wrap = document.createElement('div');
		wrap.className = 'competence-rating-widget';

		for (var i = 1; i <= 10; i++) {
			(function(num) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'cr-btn';
				btn.textContent = num;
				btn.setAttribute('data-val', num);
				btn.addEventListener('click', function () {
					ratingInput.value = num;
					wrap.querySelectorAll('.cr-btn').forEach(function(b) {
						b.classList.toggle('cr-btn--active', parseInt(b.dataset.val) <= num);
					});
				});
				wrap.appendChild(btn);
			})(i);
		}

		ratingInput.style.display = 'none';
		ratingInput.parentNode.insertBefore(wrap, ratingInput.nextSibling);
	}
	initRatingField();

	// ── Validation popup ─────────────────────────────────────────
	// Build popup DOM once, reuse on every failed submission.

	var popup     = null;
	var popupList = null;

	function buildPopup() {
		if (popup) return;

		// Overlay
		var overlay = document.createElement('div');
		overlay.className = 'vp-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-labelledby', 'vp-title');

		// Card
		var card = document.createElement('div');
		card.className = 'vp-card';

		card.innerHTML =
			'<div class="vp-icon" aria-hidden="true">' +
				'<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
			'</div>' +
			'<h2 class="vp-title" id="vp-title">Please complete the required fields</h2>' +
			'<p class="vp-subtitle">The following fields need your attention before the application can be submitted:</p>' +
			'<ul class="vp-list" id="vp-list"></ul>' +
			'<div class="vp-actions">' +
				'<button type="button" class="btn btn-primary vp-fix-btn">Review Fields</button>' +
			'</div>';

		overlay.appendChild(card);
		document.body.appendChild(overlay);

		popup     = overlay;
		popupList = overlay.querySelector('#vp-list');

		// Close on "Review Fields" → scroll to first error
		overlay.querySelector('.vp-fix-btn').addEventListener('click', function () {
			closePopup();
			var first = document.querySelector('.app-field--invalid');
			if (first) {
				first.scrollIntoView({ behavior: 'smooth', block: 'center' });
				var inp = first.querySelector('input:not([type=hidden]), textarea, select');
				if (inp) { setTimeout(function () { inp.focus(); }, 400); }
			}
		});

		// Close on overlay click outside card
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) { closePopup(); }
		});

		// Close on Escape
		document.addEventListener('keydown', function (e) {
			if ((e.key === 'Escape' || e.keyCode === 27) && popup && popup.classList.contains('vp-open')) {
				closePopup();
			}
		});
	}

	function openPopup(fields) {
		buildPopup();
		popupList.innerHTML = '';
		fields.forEach(function (name) {
			var li = document.createElement('li');
			li.textContent = name;
			popupList.appendChild(li);
		});
		popup.classList.add('vp-open');
		document.body.classList.add('vp-body-lock');
		popup.querySelector('.vp-fix-btn').focus();
	}

	function closePopup() {
		if (!popup) return;
		popup.classList.remove('vp-open');
		document.body.classList.remove('vp-body-lock');
	}

	// Extract a readable label from the field's .app-field container
	function fieldLabel(el) {
		var container = el.closest('.app-field');
		if (!container) return el.getAttribute('name') || 'Unknown field';
		var lbl = container.querySelector('label');
		if (!lbl) return el.getAttribute('name') || 'Unknown field';
		// Clone so we can strip child elements (abbr, span.app-label-hint)
		var clone = lbl.cloneNode(true);
		clone.querySelectorAll('abbr, .app-label-hint').forEach(function (n) { n.remove(); });
		return clone.textContent.trim().replace(/\s+/g, ' ');
	}

	// ── Validation highlighting & popup ─────────────────────────────
	// CF7 6.x (SWV) may add `wpcf7-not-valid` to fields either before or
	// independently of the `wpcf7invalid` DOM event, so we use a
	// MutationObserver as the primary trigger — it fires as soon as any
	// class attribute changes inside the form, regardless of event order.

	var validationDebounce = null;

	function gatherErrors( form ) {
		// Remove stale highlights
		form.querySelectorAll( '.app-field--invalid' ).forEach( function ( f ) {
			f.classList.remove( 'app-field--invalid' );
		} );

		var invalids = form.querySelectorAll( '.wpcf7-not-valid' );
		var names    = [];
		var seen     = {};

		invalids.forEach( function ( el ) {
			var container = el.closest( '.app-field' );
			if ( container && !seen[ container ] ) {
				container.classList.add( 'app-field--invalid' );
				seen[ container ] = true;
				var name = fieldLabel( el );
				if ( name && names.indexOf( name ) === -1 ) { names.push( name ); }
			}
		} );

		if ( names.length ) {
			openPopup( names );
			// Also re-enable the submit button — CF7 6.x SWV may not fire wpcf7invalid,
			// so the shared reEnableBtn fallback in the events section may never run.
			reEnableBtn( form );
		}
	}

	function startValidationObserver() {
		var form = document.querySelector( '.wpcf7-form' );
		if ( !form ) { setTimeout( startValidationObserver, 200 ); return; }

		var observer = new MutationObserver( function ( mutations ) {
			var saw = false;
			for ( var i = 0; i < mutations.length; i++ ) {
				var t = mutations[ i ].target;
				if ( t.classList && t.classList.contains( 'wpcf7-not-valid' ) ) {
					saw = true; break;
				}
			}
			if ( !saw ) return;
			clearTimeout( validationDebounce );
			validationDebounce = setTimeout( function () { gatherErrors( form ); }, 60 );
		} );

		observer.observe( form, {
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class' ],
		} );
	}

	// Boot the observer once the form is in the DOM
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', startValidationObserver );
	} else {
		startValidationObserver();
	}
	// Also restart after CF7 re-renders (e.g. after a successful submit resets the form)
	document.addEventListener( 'wpcf7domloaded', startValidationObserver );

	// Keep `wpcf7invalid` as an explicit fallback for server-side failures
	document.addEventListener( 'wpcf7invalid', function ( e ) {
		// e.target may be the <form> or the outer .wpcf7 <div>
		var form = ( e.target.tagName === 'FORM' )
			? e.target
			: e.target.querySelector( 'form' );
		if ( !form ) return;
		clearTimeout( validationDebounce );
		setTimeout( function () { gatherErrors( form ); }, 60 );
	}, false );

	// Clear highlight as soon as the user starts fixing a field
	document.addEventListener( 'input', function ( e ) {
		var c = e.target.closest( '.app-field--invalid' );
		if ( c ) { c.classList.remove( 'app-field--invalid' ); }
	}, true );
	document.addEventListener( 'change', function ( e ) {
		var c = e.target.closest( '.app-field--invalid' );
		if ( c ) { c.classList.remove( 'app-field--invalid' ); }
	}, true );

	// ── Submission feedback ───────────────────────────────────────────────
	// Strategy: watch CF7's status class on the form element via MutationObserver.
	// CF7 always mutates that class to reflect the outcome (sent / failed / invalid /
	// spam) — regardless of whether JS events bubble correctly to document. The
	// wpcf7* event listeners below remain as a secondary fallback.

	var submitTimeoutId  = null;
	var statusObserver   = null;
	var thankYouUrl      = '<?php echo esc_js( home_url( '/thank-you-application/' ) ); ?>';

	function goToThankYou() {
		if ( submitTimeoutId ) { clearTimeout( submitTimeoutId ); submitTimeoutId = null; }
		if ( statusObserver  ) { statusObserver.disconnect();     statusObserver  = null; }
		window.location.href = thankYouUrl;
	}

	function reEnableBtn( form ) {
		if ( submitTimeoutId ) { clearTimeout( submitTimeoutId ); submitTimeoutId = null; }
		if ( statusObserver  ) { statusObserver.disconnect();     statusObserver  = null; }
		var btn = form && form.querySelector( '[type="submit"]' );
		if ( !btn || !btn.disabled ) return;
		var orig = btn.dataset.oycOrig || 'Submit Application';
		if ( btn.tagName === 'INPUT' ) { btn.value = orig; } else { btn.textContent = orig; }
		btn.disabled = false;
	}

	// CF7 sets one of these classes on the form after every submission attempt.
	var SUCCESS_CLASSES  = [ 'sent', 'wpcf7-sent', 'mail_sent', 'wpcf7-mail_sent' ];
	var FAILURE_CLASSES  = [ 'failed', 'wpcf7-failed', 'mail_failed', 'wpcf7-mail_failed' ];
	var INVALID_CLASSES  = [ 'invalid', 'wpcf7-invalid', 'spam', 'wpcf7-spam', 'aborted', 'wpcf7-aborted' ];

	function hasAny( classList, names ) {
		for ( var i = 0; i < names.length; i++ ) {
			if ( classList.contains( names[i] ) ) return true;
		}
		return false;
	}

	function attachStatusObserver() {
		// Watch both the outer .wpcf7 div and the inner .wpcf7-form element.
		var targets = document.querySelectorAll( '.wpcf7, .wpcf7-form' );
		if ( !targets.length ) { setTimeout( attachStatusObserver, 200 ); return; }

		if ( statusObserver ) { statusObserver.disconnect(); }

		statusObserver = new MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				var m = mutations[ i ];
				if ( m.attributeName !== 'class' ) continue;
				var cl = m.target.classList;
				if ( hasAny( cl, SUCCESS_CLASSES ) || hasAny( cl, FAILURE_CLASSES ) ) {
					goToThankYou(); return;
				}
				if ( hasAny( cl, INVALID_CLASSES ) ) {
					var frm = m.target.closest( 'form' ) || m.target.querySelector( 'form' );
					reEnableBtn( frm ); return;
				}
			}
		} );

		targets.forEach( function ( t ) {
			statusObserver.observe( t, { attributes: true, attributeFilter: [ 'class' ] } );
		} );
	}

	// Show "Submitting…" when the user clicks Submit and arm both fallbacks.
	document.addEventListener( 'wpcf7submit', function ( e ) {
		var form = e.target.tagName === 'FORM' ? e.target : e.target.querySelector( 'form' );
		var btn  = e.target.querySelector( '[type="submit"]' );
		if ( btn ) {
			btn.dataset.oycOrig = btn.tagName === 'INPUT' ? btn.value : btn.textContent;
			if ( btn.tagName === 'INPUT' ) { btn.value = 'Submitting…'; }
			else { btn.textContent = 'Submitting…'; }
			btn.disabled = true;
		}

		// Hard timeout — re-enable button if nothing resolves within 15 s
		submitTimeoutId = setTimeout( function () { reEnableBtn( form ); }, 15000 );

		// Start watching CF7's status class (primary redirect mechanism)
		attachStatusObserver();
	}, false );

	// CF7 DOM events as secondary fallbacks (may or may not fire in CF7 6.x)
	document.addEventListener( 'wpcf7mailsent',   goToThankYou, false );
	document.addEventListener( 'wpcf7mailfailed',  goToThankYou, false );
	document.addEventListener( 'wpcf7invalid', function ( e ) {
		var f = e.target.tagName === 'FORM' ? e.target : e.target.querySelector( 'form' );
		reEnableBtn( f );
	}, false );
	document.addEventListener( 'wpcf7spam', function ( e ) {
		var f = e.target.tagName === 'FORM' ? e.target : e.target.querySelector( 'form' );
		reEnableBtn( f );
	}, false );

}());
</script>

<?php get_footer(); ?>

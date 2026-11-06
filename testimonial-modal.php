<?php
/**
 * testimonial-modal.php — the ask, rendered only when there is one.
 *
 * Included near the end of a page that can produce a win. It emits
 * NOTHING AT ALL unless testimonial_offer() says this player should be
 * asked, so the common case costs one query and no markup.
 *
 * The page decides WHEN to open it by calling skTestimonialOffer().
 * realms.php calls it when the raid result overlay closes, which is
 * the moment somebody has just watched themselves take a realm.
 *
 * Expects $conn and a signed-in session. Safe to include otherwise:
 * it checks both.
 */
if (!function_exists('testimonial_offer')) require_once __DIR__ . '/testimonials-lib.php';
$tst_uid   = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;
$tst_offer = ($tst_uid > 0 && isset($conn) && is_object($conn)) ? testimonial_offer($conn, $tst_uid) : null;
if ($tst_offer):
?>
<div class="tst-overlay" id="tst-overlay" role="dialog" aria-modal="true" aria-labelledby="tst-title">
	<div class="tst-card">
		<h2 id="tst-title"><?php echo htmlspecialchars($tst_offer['prompt']); ?></h2>
		<?php
		/*
		 * SAY WHAT IT IS FOR. People write to a brief. Without one the
		 * answer is "its fun", which helps nobody; told that a stranger
		 * is deciding whether to join, they write the reason they would
		 * give a friend.
		 */
		?>
		<p class="tst-brief">We are collecting a few honest words from players who stuck around,
		   to show people who are deciding whether to start. What drew you in, what you keep
		   coming back for, what a newcomer would not guess. The more specific it is, the more
		   it helps the server grow.</p>

		<textarea id="tst-body" maxlength="<?php echo (int) $tst_offer['max']; ?>"
		          placeholder="In your own words..."></textarea>
		<div class="tst-meter"><span id="tst-count">0</span> / <?php echo (int) $tst_offer['max']; ?>
		     <span class="tst-min">at least <?php echo (int) $tst_offer['min']; ?></span></div>

		<?php
		/*
		 * NO PICKER. Sending it is the consent, and the line below
		 * says exactly what sending it means. An "anonymous" choice
		 * was here and went: an unattributed quote is weak proof, and
		 * it does not buy the privacy it looks like it does, because
		 * the avatar URL carries the Discord id while a username does
		 * not. Withdrawing later is on the profile, where somebody
		 * changing their mind actually goes.
		 */
		?>
		<p class="tst-fine">An owner reads it before anything is published, and you can withdraw
		   it from your profile at any time. A Discord post cannot be unposted.</p>
		<label class="tst-consent">
			<input type="checkbox" id="tst-consent">
			<span>Publish this with my username and avatar. Both are already on my public
			      profile and the world map.</span>
		</label>

		<div class="tst-err" id="tst-err" hidden></div>

		<div class="tst-actions">
			<button type="button" class="tst-skip" id="tst-later">
				<?php echo $tst_offer['last_call'] ? 'No thanks' : 'Not now'; ?>
			</button>
			<button type="button" class="tst-go" id="tst-send">Send it</button>
		</div>
		<?php if ($tst_offer['last_call']): ?>
		<p class="tst-last">This is the last time we will ask.</p>
		<?php endif; ?>
	</div>
</div>

<div class="tst-overlay" id="tst-thanks" role="dialog" aria-modal="true">
	<div class="tst-card tst-card-thanks">
		<h2>Thank you</h2>
		<p class="tst-brief">It goes to an owner to read. If you want to put it out now,
		   the Skulliance account can pick it up from X.</p>
		<a class="tst-x" id="tst-share" href="#" target="_blank" rel="noopener">Share it on X</a>
		<button type="button" class="tst-skip" id="tst-done">Close</button>
	</div>
</div>

<script type="text/javascript">
/*
 * The modal is OFFERED, not shown. A page opens it at a moment of its
 * own choosing -- realms.php when the raid result closes -- so that
 * nobody is interrupted on arrival by a request for marketing copy.
 */
(function () {
	var offer   = <?php echo json_encode(array(
		'prompt_key' => $tst_offer['prompt_key'],
		'min'        => (int) $tst_offer['min'],
	)); ?>;
	var overlay = document.getElementById('tst-overlay');
	var thanks  = document.getElementById('tst-thanks');
	var body    = document.getElementById('tst-body');
	var count   = document.getElementById('tst-count');
	var err     = document.getElementById('tst-err');
	var sent    = false, opened = false;

	function post(data) {
		var p = new URLSearchParams(data);
		return fetch('ajax/testimonial.php', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: p.toString()
		}).then(function (r) { return r.json(); });
	}

	window.skTestimonialOffer = function () {
		if (opened || sent || !overlay) return;
		opened = true;
		overlay.classList.add('on');
		/* Counted the moment it is seen, so a reload cannot farm asks. */
		post({ action: 'shown' });
		if (body) body.focus();
	};

	if (body) {
		body.addEventListener('input', function () {
			count.textContent = body.value.trim().length;
		});
	}

	document.getElementById('tst-later').addEventListener('click', function () {
		/* "No thanks" on the last ask means never; "Not now" means a
		   fortnight. The server decides which by the `never` flag. */
		post({ action: 'dismiss', never: <?php echo $tst_offer['last_call'] ? '1' : '0'; ?> });
		overlay.classList.remove('on');
	});

	/* The send button stays out of reach until the box is ticked, so
	   nobody can publish their name by clicking past a sentence. */
	var consent = document.getElementById('tst-consent');
	var sendBtn = document.getElementById('tst-send');
	function syncConsent() { sendBtn.disabled = !consent.checked; }
	consent.addEventListener('change', syncConsent);
	syncConsent();

	sendBtn.addEventListener('click', function () {
		var txt = (body.value || '').trim();
		err.hidden = true;
		if (txt.length < offer.min) {
			err.textContent = 'A line or two more would help. At least ' + offer.min + ' characters.';
			err.hidden = false;
			return;
		}
		this.disabled = true;
		var btn = this;
		post({ action: 'save', prompt_key: offer.prompt_key, body: txt,
		       visibility: 'named', consent: consent.checked ? 1 : 0 })
			.then(function (d) {
				btn.disabled = false;
				if (!d || !d.success) {
					err.textContent = (d && d.error) ? d.error : 'That did not save. Try again in a moment.';
					err.hidden = false;
					return;
				}
				sent = true;
				overlay.classList.remove('on');
				document.getElementById('tst-share').href = d.share || '#';
				thanks.classList.add('on');
			})
			.catch(function () {
				btn.disabled = false;
				err.textContent = 'That did not save. Try again in a moment.';
				err.hidden = false;
			});
	});

	document.getElementById('tst-done').addEventListener('click', function () {
		thanks.classList.remove('on');
	});
})();
</script>
<?php endif; ?>

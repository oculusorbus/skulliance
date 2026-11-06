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
require_once __DIR__ . '/lib/realm-preview.php';
$tst_uid   = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;
$tst_offer = ($tst_uid > 0 && isset($conn) && is_object($conn))
	? testimonial_offer_cached($conn, $tst_uid) : null;

/*
 * THE PREVIEW, FOR SOMEBODY WHO HAS TO READ THIS SCREEN ON PURPOSE.
 *
 * The modal is otherwise unreachable on demand: it offers after a raid
 * win, three times at most, and never again once somebody has written
 * one. Winning a raid to check a layout is not a review process.
 *
 * It writes NOTHING. No ask is counted, dismissing does not mute the
 * account, and sending does not save -- otherwise one look would spend
 * a real nudge and the second look would be of a modal that no longer
 * offers.
 */
/* $_SESSION BY VALUE, AND IT MAY NOT EXIST. db.php only starts a
   session when a cookie is already present, so a cookieless visitor
   -- every first-time arrival and every crawler on the public map --
   has no $_SESSION at all. isset() on an element is safe; handing the
   whole array to a function is not, and warned into the page. */
$tst_preview = testimonial_preview_requested($_GET, isset($_SESSION) ? $_SESSION : array());
/*
 * WHO OPENS IT, DECIDED HERE RATHER THAN BY THE PAGE.
 *
 * This read a $tst_self_open the including page was supposed to set
 * first. header.php includes this now, and header.php runs near the
 * TOP of a page, so realms.php could not have set anything yet -- the
 * flag would have been read before it was written, every time.
 *
 * The one page with a better moment is realms.php, which calls
 * skTestimonialOffer() when a raid result closes. Asking the path is
 * ugly and it is honest: there is exactly one exception and this is
 * the only place that can know about it before the markup is written.
 */
$tst_self_open = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) !== 'realms.php';
if ($tst_preview && !$tst_offer) {
	$tst_keys = array_keys(testimonial_prompts());
	$tst_offer = array(
		'prompt_key' => $tst_keys[0],
		'prompt'     => testimonial_prompts()[$tst_keys[0]],
		'last_call'  => isset($_GET['last']),
		'min'        => (int) TESTIMONIAL_MIN,
		'max'        => (int) TESTIMONIAL_MAX,
	);
}
if ($tst_offer):
?>
<?php
/*
 * THE ONE RULE THAT MATTERS, INLINE.
 *
 * Everything else about this modal is cosmetic; this single line is
 * what keeps it from being page content. It shipped once without its
 * stylesheet -- an allowlist in header.php that a new include site
 * was never added to -- and both dialogs unrolled into the footer of
 * Launchpad underneath somebody's game results.
 *
 * Forty bytes next to the markup it governs means that cannot happen
 * again from a missing link, a cache miss or a 404 on the stylesheet.
 */
?>
<style>.tst-overlay{display:none}</style>
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
		<p class="tst-brief">We are collecting a few honest words from active players, to show
		   visitors who are deciding whether to join. What drew you in? What keeps you coming
		   back? What's something that would attract a newcomer? The more specific it is, the
		   more it helps our community grow.</p>

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
		<p class="tst-fine">The founder reads it before anything is published, and you can revise
		   it or withdraw it from your profile at any time.</p>
		<label class="tst-consent">
			<input type="checkbox" id="tst-consent">
			<span>Publish this with my username and avatar.</span>
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
		<p class="tst-brief">It goes to the founder to read. If you want to put it out now,
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
		'preview'    => (bool) $tst_preview,
	)); ?>;
	var overlay = document.getElementById('tst-overlay');
	var thanks  = document.getElementById('tst-thanks');
	var body    = document.getElementById('tst-body');
	var count   = document.getElementById('tst-count');
	var err     = document.getElementById('tst-err');
	var sent    = false, opened = false;

	function post(data) {
		/* A preview is a look, not a session. Nothing it does reaches
		   the database, including the "shown" count. */
		if (offer.preview) return Promise.resolve({ success: true, share: '#', preview: true });
		var p = new URLSearchParams(data);
		return fetch('ajax/testimonial.php', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: p.toString()
		}).then(function (r) { return r.json(); });
	}

	/*
	 * WHO OPENS IT. realms.php calls skTestimonialOffer() when a raid
	 * result closes, which is the best moment on the platform and
	 * exists only for people who play Realms. Everywhere else the
	 * modal opens itself a few seconds in -- long enough that it
	 * follows the page rather than greeting somebody with it, and the
	 * eligibility gate has already decided this is a player with a
	 * week of activity behind them who has not been asked in a
	 * fortnight.
	 */
	var selfOpen = <?php echo $tst_self_open ? 'true' : 'false'; ?>;
	if (offer.preview) {
		document.addEventListener('DOMContentLoaded', function () {
			overlay.classList.add('on');
		});
	} else if (selfOpen) {
		setTimeout(function () { window.skTestimonialOffer(); }, 4000);
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

	/*
	 * THE BUTTON SAYS WHY, IT DOES NOT JUST STOP WORKING.
	 *
	 * This disabled Send until the box was ticked. Nothing can get
	 * past a disabled button, which sounds like the safe choice and
	 * is a worse one: somebody writes three sentences, presses a
	 * button that does nothing, and is told nothing. The modal
	 * already explains the length problem when they press it, so
	 * consent is checked the same way and in the same place.
	 *
	 * The guarantee was never here in any case. testimonial_save()
	 * refuses without the confirmation, so the tick is enforced on the
	 * server whatever the page does.
	 */
	var consent = document.getElementById('tst-consent');
	var sendBtn = document.getElementById('tst-send');

	sendBtn.addEventListener('click', function () {
		var txt = (body.value || '').trim();
		err.hidden = true;
		if (txt.length < offer.min) {
			err.textContent = 'A line or two more would help. At least ' + offer.min + ' characters.';
			err.hidden = false;
			return;
		}
		if (!consent.checked) {
			err.textContent = 'Tick the box to confirm your username and avatar can be published.';
			err.hidden = false;
			consent.focus();
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

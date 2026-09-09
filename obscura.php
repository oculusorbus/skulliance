<?php
/*
 * Obscura -- prototype.
 *
 * Deliberately NOT wired to CARBON, leaderboards or the hub yet. The point of
 * this build is to answer one question: are the crops fair? Everything else
 * is cheap to add afterwards and expensive to tune blind.
 */
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'obscura-lib.php';
include 'header.php';

$ob_uid   = intval($_SESSION['userData']['user_id'] ?? 0);
$ob_state = $ob_uid > 0 ? obscuraState($conn, $ob_uid) : array('error' => 'not_logged_in');
?>

<div class="row" id="row1">
  <div class="col1of3" style="max-width:760px;margin:0 auto;flex:1 1 100%;">

	<h2>Obscura</h2>
	<div class="ob-tagline">A sliver of artwork. Name the collection it came from.
		Wrong answers widen the view &mdash; but the deeper your streak, the less you get.</div>

	<?php if (isset($ob_state['error'])): ?>
		<div class="ob-notice">
		<?php if ($ob_state['error'] === 'not_migrated'): ?>
			Obscura needs its table before it can run. See the migration at the top of
			<code>obscura-lib.php</code>.
		<?php elseif ($ob_state['error'] === 'not_logged_in'): ?>
			<a href="index.php">Log in</a> to play &mdash; runs are saved to your account.
		<?php else: ?>
			No artwork is cached locally yet, so there is nothing safe to show.
			Obscura only uses images already on this server, never the IPFS gateway.
		<?php endif; ?>
		</div>
	<?php else: ?>

	<div id="ob-game" data-v="<?php echo htmlspecialchars($ob_state['crop_v']); ?>">

		<div class="ob-hud">
			<span>Streak <strong id="ob-streak"><?php echo $ob_state['streak']; ?></strong></span>
			<span>Best <strong id="ob-best"><?php echo $ob_state['best']; ?></strong></span>
			<span id="ob-attempts">
				<?php echo $ob_state['attempts'] - $ob_state['used']; ?> of
				<?php echo $ob_state['attempts']; ?> attempts left
			</span>
		</div>

		<!-- The terms in force now, NOT an announcement -- it has to keep telling
		     the truth after the streak changes, so load() refreshes it like the
		     rest of the HUD. Tier CHANGES are announced in #ob-message instead. -->
		<div class="ob-tier" id="ob-tier"><?php echo htmlspecialchars($ob_state['tier_terms']); ?></div>

		<!-- Only the visible region ever reaches the browser. The full artwork
		     is cropped server-side (ajax/obscura-crop.php) because the image
		     IS the answer -- doing it in CSS shipped the whole thing and asked
		     the browser not to look. -->
		<img id="ob-view" alt="A fragment of an NFT" src="ajax/obscura-crop.php?v=<?php echo urlencode($ob_state['crop_v']); ?>">

		<div id="ob-options" style="--ob-cols:<?php echo intval($ob_state['columns']); ?>">
			<?php foreach ($ob_state['options'] as $o): ?>
				<button type="button" class="ob-opt" data-id="<?php echo intval($o['id']); ?>"
					<?php echo in_array(intval($o['id']), $ob_state['wrong'], true) ? 'disabled' : ''; ?>>
					<span class="ob-opt-project"><?php echo htmlspecialchars($o['project'] ?? ''); ?></span>
					<span class="ob-opt-name"><?php echo htmlspecialchars($o['name']); ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<div id="ob-message"></div>

		<!-- The reveal is the payoff, so it waits for the player rather than
		     being timed out from under them. Hidden until a puzzle resolves. -->
		<button type="button" id="ob-next" hidden></button>
	</div>
	<?php endif; ?>

  </div>
</div>

<style>
.ob-tagline { font-size:.82rem; color:rgba(255,255,255,.5); margin:-6px 0 18px; line-height:1.5; }
.ob-notice  { background:rgba(0,200,160,.06); border:1px solid rgba(0,200,160,.3); border-radius:8px; padding:14px 16px; font-size:.86rem; }
.ob-notice a { color:#00c8a0; }
.ob-hud { display:flex; gap:20px; font-size:.8rem; color:rgba(255,255,255,.55); margin-bottom:10px; flex-wrap:wrap; }
.ob-hud strong { color:#00c8a0; font-size:1rem; }
/* Standing context, so it is muted. Yellow here read as a warning on every
   page load; the tier CHANGE keeps the yellow, in #ob-message, where it is
   actually news. */
.ob-tier { font-size:.78rem; color:rgba(255,255,255,.45); border-left:2px solid rgba(255,255,255,.15); padding-left:10px; margin-bottom:12px; }
#ob-view {
  display:block; width:100%; aspect-ratio:1/1; max-width:420px; margin:0 auto 18px;
  border-radius:8px; border:1px solid rgba(255,255,255,.08);
  background-color:#0a1929; object-fit:cover;
  transition:opacity .25s ease;
}
#ob-view.ob-swapping { opacity:.35; }
/* The reveal: the whole artwork, pulled back from the sliver. object-fit
   switches to contain so nothing is cut off, and the scale settles from
   slightly-too-close to 1 so it reads as zooming out rather than cutting. */
#ob-view.ob-reveal {
  object-fit:contain;
  border-color:rgba(0,200,160,.5);
  animation:ob-pullback .55s ease-out;
}
@keyframes ob-pullback {
  from { transform:scale(1.18); opacity:.55; }
  to   { transform:scale(1);    opacity:1; }
}
@media (prefers-reduced-motion:reduce) { #ob-view.ob-reveal { animation:none; } }
/* Fixed columns per tier (--ob-cols, set from obscuraColumns): 6->3, 8->4,
   10->5, 12->4. grid-auto-rows:1fr is what keeps EVERY button the same
   height -- grid already equalises within a row, but without this a row
   holding a two-line collection name would be taller than the rest. */
#ob-options {
  display:grid;
  grid-template-columns:repeat(var(--ob-cols,3), minmax(0,1fr));
  grid-auto-rows:1fr;
  gap:8px;
}
.ob-opt {
  /* column flex: project sits above collection, and the pair stays vertically
     centred however many lines either wraps to. */
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  text-align:center; gap:2px;
  min-height:3.9em; line-height:1.2; hyphens:auto; overflow-wrap:anywhere;
  background:#0d1e30; color:inherit;
  border:1px solid rgba(255,255,255,.1); border-radius:6px; padding:10px 8px; cursor:pointer;
  transition:background-color .15s ease, border-color .15s ease;
}
/* Project is context, collection is the answer -- so the collection carries
   the weight and the project sits quietly above it. */
.ob-opt-project { font-size:.66rem; color:rgba(255,255,255,.45); letter-spacing:.04em; text-transform:uppercase; }
.ob-opt-name    { font-size:.86rem; font-weight:bold; }
/* Five across is unreadable on a phone; collapse to two whatever the tier. */
@media (max-width:560px) { #ob-options { grid-template-columns:repeat(2,minmax(0,1fr)); } }
.ob-opt:hover:not(:disabled) { background:#10263c; border-color:#00c8a0; }
.ob-opt:disabled { opacity:.3; cursor:default; text-decoration:line-through; }
.ob-opt.ob-correct { border-color:#00c8a0; background:rgba(0,200,160,.18); }
.ob-opt.ob-miss    { border-color:#ff4d4d; background:rgba(255,77,77,.12); }
/* While the reveal is up the board is spent -- dim it so it does not read as
   still answerable, and stop clicks landing on it. */
#ob-options.ob-resolved { opacity:.4; pointer-events:none; }
#ob-next {
  display:block; margin:16px auto 0; padding:11px 26px;
  background:#00c8a0; color:#04121d; font-weight:bold; font-size:.9rem;
  border:0; border-radius:6px; cursor:pointer;
  transition:filter .15s ease;
}
#ob-next:hover { filter:brightness(1.1); }
/* The UA's [hidden] rule loses to the display above, so restate it. */
#ob-next[hidden] { display:none; }
#ob-message { margin-top:14px; font-size:.88rem; min-height:1.4em; }
#ob-message .ob-win  { color:#00c8a0; font-weight:bold; }
#ob-message .ob-lose { color:#ff4d4d; font-weight:bold; }
</style>

<script>
(function () {
  var game = document.getElementById('ob-game');
  if (!game) return;
  var view = document.getElementById('ob-view');
  var msg  = document.getElementById('ob-message');
  var next = document.getElementById('ob-next');
  var busy = false;
  var revealing = false;
  var pending = null;   // the next puzzle, held until the player asks for it
  // The crop endpoint derives everything from the run row, so the client only
  // ever needs a cache-busting token to force a re-fetch when the view widens.
  function showCrop(v) {
    revealing = false;
    view.classList.remove('ob-reveal');
    view.classList.add('ob-swapping');
    view.src = 'ajax/obscura-crop.php?v=' + encodeURIComponent(v);
  }
  // Once judged, the puzzle is over and the whole artwork is the payoff.
  // Served straight from the local cache -- no crop to apply any more.
  function showReveal(url) {
    if (!url) return;
    revealing = true;
    view.classList.remove('ob-swapping');
    view.classList.add('ob-reveal');
    view.src = url;
  }
  /*
   * A puzzle is over. Park the next one and let the player sit with the full
   * artwork for as long as they like -- taking it in IS the reward, and timing
   * it out from under them was the one bit of hurry left in the game.
   */
  function resolve(label, nextState) {
    pending = nextState;
    document.getElementById('ob-options').classList.add('ob-resolved');
    next.textContent = label;
    next.hidden = false;
    next.focus({ preventScroll: true });   // Enter/Space advances without a reach for the mouse
  }
  next.addEventListener('click', function () {
    if (!pending) return;
    msg.textContent = '';   // cleared here, not in load(), which the reroll
                            // path calls while its own notice is worth reading
    var s = pending; pending = null;
    load(s);
  });

  view.addEventListener('load',  function () { view.classList.remove('ob-swapping'); });
  // A crop that will not render must never cost an attempt -- ask for a
  // different puzzle instead. The server rerolls without touching the streak.
  view.addEventListener('error', function () {
    // A reveal that fails is cosmetic: the guess is already judged and the next
    // puzzle is queued, so rerolling here would swap out a puzzle for nothing.
    if (revealing) { revealing = false; return; }
    msg.innerHTML = '<span style="color:rgba(255,255,255,.5)">That artwork would not load &mdash; swapping in another. Nothing lost.</span>';
    post({ action: 'reroll' }, function (d) { if (d && d.next) load(d.next); });
  });

  function post(data, done) {
    var body = Object.keys(data).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
    }).join('&');
    fetch('ajax/obscura-action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (r) { return r.json(); })
      .then(done)
      .catch(function () {
        msg.innerHTML = '<span class="ob-lose">Connection lost. Your streak is safe &mdash; reload to carry on.</span>';
        busy = false;
      });
  }

  function load(state) {
    // Cleared first, before the bail-out below: a dead Next button left sitting
    // under an error message is worse than no button.
    pending = null;
    next.hidden = true;
    document.getElementById('ob-options').classList.remove('ob-resolved');

    if (!state || state.error) {
      msg.innerHTML = '<span class="ob-lose">Could not load the next puzzle. Reload to carry on.</span>';
      return;
    }
    document.getElementById('ob-streak').textContent = state.streak;
    document.getElementById('ob-best').textContent   = state.best;
    document.getElementById('ob-attempts').textContent =
      (state.attempts - state.used) + ' of ' + state.attempts + ' attempts left';
    document.getElementById('ob-tier').textContent = state.tier_terms || '';

    var box = document.getElementById('ob-options');
    // The column count changes with the tier, so it has to move with the
    // options rather than being set once at page load.
    box.style.setProperty('--ob-cols', state.columns || 3);
    box.innerHTML = '';
    state.options.forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'ob-opt'; b.dataset.id = o.id;
      // Must mirror the PHP render exactly -- project above collection.
      // textContent alone would drop the project on every puzzle after the
      // first, since only the initial board is server-rendered.
      var p = document.createElement('span');
      p.className = 'ob-opt-project'; p.textContent = o.project || '';
      var n = document.createElement('span');
      n.className = 'ob-opt-name'; n.textContent = o.name;
      b.appendChild(p); b.appendChild(n);
      box.appendChild(b);
    });
    showCrop(state.crop_v);
    busy = false;
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ob-opt');
    if (!btn || busy || btn.disabled) return;
    busy = true;

    post({ action: 'guess', collection_id: btn.dataset.id }, function (d) {
      if (!d || d.error) { busy = false; return; }

      if (d.result === 'wrong') {
        btn.disabled = true;
        btn.classList.add('ob-miss');
        document.getElementById('ob-attempts').textContent =
          (d.attempts - d.used) + ' of ' + d.attempts + ' attempts left';
        showCrop(d.crop_v);   // the reward for being wrong: a wider view
        msg.textContent = 'Not that one. A little more of it, then.';
        busy = false;
        return;
      }

      if (d.result === 'correct') {
        btn.classList.add('ob-correct');
        showReveal(d.reveal);   // pull back to the whole artwork
        // Name it back to them: on a solve the only clue they had was a sliver,
        // and the option they clicked scrolls out of mind fast.
        var named = btn.querySelector('.ob-opt-name');
        msg.innerHTML = '<span class="ob-win">Got it'
          + (named ? ' &mdash; ' + named.textContent : '') + '. Streak ' + d.streak + '.</span>'
          + (d.tier_changed && d.tier_label ? '<br><span style="color:#ffcc44">' + d.tier_label + '</span>' : '');
        resolve('Next', d.next);
        return;
      }

      if (d.result === 'failed') {
        btn.classList.add('ob-miss');
        showReveal(d.reveal);
        msg.innerHTML = '<span class="ob-lose">It was ' + d.answer_name + '.</span> '
          + 'Run over. Best streak: ' + d.best + '.';
        resolve('Start a new run', d.next);
      }
    });
  });

  // First crop is already in the img src from PHP; nothing to paint.
})();
</script>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>

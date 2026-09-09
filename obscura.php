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

	<div id="ob-game"
	     data-art="<?php echo htmlspecialchars($ob_state['art']); ?>"
	     data-x="<?php echo $ob_state['crop_x']; ?>"
	     data-y="<?php echo $ob_state['crop_y']; ?>"
	     data-zoom="<?php echo $ob_state['zoom']; ?>">

		<div class="ob-hud">
			<span>Streak <strong id="ob-streak"><?php echo $ob_state['streak']; ?></strong></span>
			<span>Best <strong id="ob-best"><?php echo $ob_state['best']; ?></strong></span>
			<span id="ob-attempts">
				<?php echo $ob_state['attempts'] - $ob_state['used']; ?> of
				<?php echo $ob_state['attempts']; ?> attempts left
			</span>
		</div>

		<?php if ($ob_state['tier_label'] !== ''): ?>
			<div class="ob-tier"><?php echo htmlspecialchars($ob_state['tier_label']); ?></div>
		<?php endif; ?>

		<!-- The crop is a window onto the real image: background-size zooms it,
		     background-position picks the spot. No image processing, no new
		     assets -- the same cached file every other page uses. -->
		<div id="ob-view"></div>

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
.ob-tier { font-size:.78rem; color:#ffcc44; border-left:2px solid #ffcc44; padding-left:10px; margin-bottom:12px; }
#ob-view {
  width:100%; aspect-ratio:1/1; max-width:420px; margin:0 auto 18px;
  border-radius:8px; border:1px solid rgba(255,255,255,.08);
  background-repeat:no-repeat; background-color:#0a1929;
  image-rendering:auto; transition:background-size .45s ease, background-position .45s ease;
}
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
  var busy = false;
  // The current puzzle, held here rather than read back out of CSS. Widening
  // the crop only changes the zoom, so art and position must survive intact.
  var cur = { art: '', x: 50, y: 50, zoom: 15 };

  // Render the crop. zoom is the percentage of the artwork visible, so a
  // smaller zoom means a bigger background -- 15% visible = 1/0.15 scale.
  function paint(art, x, y, zoom) {
    cur = { art: art, x: x, y: y, zoom: zoom };
    var scale = (100 / zoom) * 100;
    view.style.backgroundImage = "url('" + art + "')";
    view.style.backgroundSize  = scale + '% ' + scale + '%';
    view.style.backgroundPosition = x + '% ' + y + '%';
    verify(art);
  }
  // Widen the same crop -- art and position unchanged, only the zoom moves.
  function widen(zoom) { paint(cur.art, cur.x, cur.y, zoom); }

  /*
   * A broken image must never cost a run. background-image gives no error
   * event, so the same url is loaded through an Image() purely to find out
   * whether it renders. If it does not, ask the server for a different
   * puzzle -- no attempt spent, streak untouched.
   */
  function verify(art) {
    var probe = new Image();
    probe.onerror = function () {
      msg.innerHTML = '<span style="color:rgba(255,255,255,.5)">That artwork would not load &mdash; swapping in another. Nothing lost.</span>';
      post({ action: 'reroll' }, function (d) { if (d && d.next) load(d.next); });
    };
    probe.src = art;
  }

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
    if (!state || state.error) {
      msg.innerHTML = '<span class="ob-lose">Could not load the next puzzle. Reload to carry on.</span>';
      return;
    }
    document.getElementById('ob-streak').textContent = state.streak;
    document.getElementById('ob-best').textContent   = state.best;
    document.getElementById('ob-attempts').textContent =
      (state.attempts - state.used) + ' of ' + state.attempts + ' attempts left';

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
    paint(state.art, state.crop_x, state.crop_y, state.zoom);
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
        widen(d.zoom);   // the reward for being wrong
        msg.textContent = 'Not that one. A little more of it, then.';
        busy = false;
        return;
      }

      if (d.result === 'correct') {
        btn.classList.add('ob-correct');
        msg.innerHTML = '<span class="ob-win">Got it. Streak ' + d.streak + '.</span>'
          + (d.tier_changed && d.tier_label ? '<br><span style="color:#ffcc44">' + d.tier_label + '</span>' : '');
        setTimeout(function () { load(d.next); msg.textContent = ''; }, d.tier_changed ? 2200 : 900);
        return;
      }

      if (d.result === 'failed') {
        btn.classList.add('ob-miss');
        msg.innerHTML = '<span class="ob-lose">It was ' + d.answer_name + '.</span> '
          + 'Run over. Best streak: ' + d.best + '.';
        setTimeout(function () { load(d.next); msg.textContent = ''; }, 2600);
      }
    });
  });

  paint(game.dataset.art, game.dataset.x, game.dataset.y, game.dataset.zoom);
})();
</script>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>

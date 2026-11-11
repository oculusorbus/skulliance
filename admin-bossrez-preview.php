<?php
/*
 * admin-bossrez-preview.php — the crew resurrection, on demand.
 *
 * It fires inside the REAL Arena board. The page loads dhcarena.php in an
 * iframe, waits for its timeline player to exist, and hands it a genuine
 * 'rez' event built by dhcb_rez()'s own payload shape -- so what is
 * previewed is the handler that ships, the cue that ships and the CSS that
 * ships. A mock of the animation would be a drawing of the animation.
 *
 * WHY IT HAS TO EXIST. A resurrection needs a boss crew taken to zero by a
 * real Crew inside a live week, which is a thing that happens a handful of
 * times across seven days and never on demand. Without this the only way
 * to look at it twice is to wait for a wave to fall.
 *
 * Writes nothing, commits no damage, settles no run.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/dhcbosses-lib.php';
admin_require();

/* The real bosses, so the names on the bars are the names players see. */
$abp_names = array();
foreach (dhcb_bosses() as $abp_b) $abp_names[] = $abp_b['name'];

include 'header.php';
admin_chrome('bossrez-preview');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Crew resurrection</h3>
	<small>
		The boss crew falls and the next wave rises in its place, mid-run. This fires
		the real <code>'rez'</code> event into the real Arena board below: same handler,
		same five-layer reversed cue, same CSS. Nothing is committed and no run is
		settled -- it is the animation, played on demand.
	</small>

	<p>
		<label style="font-size:.8rem;color:#8fa7bb">Rising to wave
			<input type="number" id="abpWave" min="2" max="10" value="2" style="width:64px;margin-left:4px">
		</label>
		<button type="button" class="button" id="abpGo" style="width:auto;margin-left:10px">▶ Resurrect</button>
		<span id="abpNote" style="margin-left:10px;font-size:.8rem;color:#f5a623"></span>
	</p>

	<iframe id="abpFrame" src="dhcarena.php" title="DHC Arena board"
	        style="width:100%;height:760px;border:1px solid #1b3346;border-radius:4px;background:#07111d"></iframe>
</div></div>

<script>
(function () {
	var NAMES = <?php echo json_encode($abp_names); ?>;
	var frame = document.getElementById('abpFrame');
	var note  = document.getElementById('abpNote');

	document.getElementById('abpGo').addEventListener('click', function () {
		var w = Math.max(2, Math.min(10, parseInt(document.getElementById('abpWave').value, 10) || 2));
		var win = frame.contentWindow;
		/*
		 * THE BOARD'S OWN PLAYER, NOT A COPY. If the Arena has not exposed
		 * it the preview says so rather than drawing something that only
		 * resembles the real thing -- which is the failure mode this page
		 * exists to avoid.
		 */
		if (!win || typeof win.DHCA_PLAY_FX !== 'function') {
			note.textContent = 'The board has not finished loading, or it has no battle open.';
			return;
		}
		note.textContent = '';
		/* Built in dhcb_rez()'s payload shape: wave, hue, power multiplier
		   and the three bars at full. */
		var foes = NAMES.map(function (n) {
			var hp = 9000 + Math.round(Math.random() * 4000);
			return { name: n, hp: hp, maxHp: hp };
		});
		win.DHCA_PLAY_FX([{ k: 'rez', wave: w, hue: ((w - 1) % 5) * 72,
		                    pow: Math.pow(1.18, w - 1), foes: foes }]);
	});
})();
</script>
</html>

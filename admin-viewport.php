<?php
/**
 * admin-viewport.php — what the two viewports are doing, live, on the
 * phone you are holding.
 *
 * WHY IT EXISTS. The back-to-top arrow stalls halfway up the screen on
 * mobile and nowhere else. At 390px in a desktop browser the geometry is
 * exact -- 48 frames of continuous scroll hold the gap at 10px -- so
 * there was nothing left to measure from this end, and guessing at a
 * phone from a laptop is how the drop-shadow took two passes.
 *
 * A phone has two viewports. position:fixed resolves against the LAYOUT
 * one, which browsers size to the window with the URL bar hidden; what
 * you can actually see is the VISUAL one. The difference between them is
 * the number this page prints, and it is the number the arrow is off by.
 *
 * SCROLL THIS PAGE UP AND DOWN. If "gap between viewports" goes above
 * zero as the URL bar slides, that is the whole bug, and the arrow in
 * the corner should now stay put anyway -- it is pinned to the visual
 * viewport. The live readout is the proof either way.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

include 'header.php';
admin_chrome('viewport');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Viewport diagnostic</h3>
	<p class="adm-note">Open this on the phone and scroll up and down. Everything below
	   updates live. The arrow in the bottom corner is the real one.</p>

	<div class="adm-card">
		<table class="vp-t">
			<tr><td>layout viewport height</td><td id="vp-layout">-</td></tr>
			<tr><td>visual viewport height</td><td id="vp-visual">-</td></tr>
			<tr><td>visual viewport offsetTop</td><td id="vp-offset">-</td></tr>
			<tr class="vp-hero"><td><b>gap between viewports</b></td><td id="vp-gap">-</td></tr>
			<tr><td>--btt-lift (what the fix applies)</td><td id="vp-lift">-</td></tr>
			<tr><td>safe-area-inset-bottom</td><td id="vp-safe">-</td></tr>
			<tr class="vp-hero"><td><b>arrow gap to visible bottom</b></td><td id="vp-arrow">-</td></tr>
			<tr><td>arrow position / filter</td><td id="vp-css">-</td></tr>
			<tr><td>worst arrow gap seen</td><td id="vp-worst">-</td></tr>
			<tr><td>scroll Y</td><td id="vp-y">-</td></tr>
		</table>
	</div>

	<p class="adm-note"><b>What good looks like:</b> "arrow gap to visible bottom" stays at
	   10 the whole way up and down, and "worst arrow gap seen" never moves off 10. If the
	   gap between viewports climbs while that arrow gap stays at 10, the fix is doing its
	   job. If the arrow gap climbs with it, the fix is not being applied and the readout
	   says so.</p>

	<?php /* Something to scroll past. */ ?>
	<?php for ($i = 1; $i <= 14; $i++): ?>
	<div class="adm-card"><p class="adm-note">Filler <?php echo $i; ?> of 14 &mdash; keep scrolling.</p></div>
	<?php endfor; ?>
</div></div>

<style>
.vp-t { width: 100%; border-collapse: collapse; font-size: .86rem; }
.vp-t td { padding: 6px 8px; border-bottom: 1px solid rgba(0,200,160,.12); }
.vp-t td:last-child { text-align: right; font-variant-numeric: tabular-nums; color: #00c8a0; }
.vp-t .vp-hero td { background: rgba(0,200,160,.07); }
.vp-bad td:last-child { color: #e07a7a; }
</style>
<script>
(function () {
	var $ = function (id) { return document.getElementById(id); };
	var btn = document.getElementById('back-to-top-button');
	var worst = null;
	function read() {
		var de = document.documentElement;
		var vv = window.visualViewport;
		var layout = de.clientHeight;
		var vis    = vv ? Math.round(vv.height) : layout;
		var off    = vv ? Math.round(vv.offsetTop) : 0;
		var gap    = Math.max(0, layout - (vis + off));
		$('vp-layout').textContent = layout + 'px';
		$('vp-visual').textContent = vv ? vis + 'px' : 'no visualViewport';
		$('vp-offset').textContent = off + 'px';
		$('vp-gap').textContent    = gap + 'px';
		$('vp-lift').textContent   =
			(getComputedStyle(de).getPropertyValue('--btt-lift') || '0px').trim();
		$('vp-safe').textContent   = getComputedStyle(de).getPropertyValue('--sa-probe') || 'n/a';
		$('vp-y').textContent      = Math.round(window.pageYOffset) + 'px';
		if (btn) {
			var cs = getComputedStyle(btn);
			/* Against the VISIBLE bottom, which is what the eye judges. */
			var r  = btn.getBoundingClientRect();
			var visibleBottom = (vv ? vv.height + vv.offsetTop : layout);
			var ag = Math.round(visibleBottom - r.bottom);
			$('vp-arrow').textContent = cs.visibility === 'hidden' ? 'hidden (scroll down)' : ag + 'px';
			$('vp-css').textContent   = cs.position + ' / ' + (cs.filter === 'none' ? 'no filter' : cs.filter);
			if (cs.visibility !== 'hidden') {
				if (worst === null || Math.abs(ag - 10) > Math.abs(worst - 10)) worst = ag;
				$('vp-worst').textContent = worst + 'px';
				$('vp-arrow').parentNode.className = Math.abs(ag - 10) > 3 ? 'vp-hero vp-bad' : 'vp-hero';
			}
		}
	}
	var queued = false;
	function soon() { if (!queued) { queued = true; requestAnimationFrame(function () { queued = false; read(); }); } }
	window.addEventListener('scroll', soon, { passive: true });
	window.addEventListener('resize', soon);
	if (window.visualViewport) {
		window.visualViewport.addEventListener('resize', soon);
		window.visualViewport.addEventListener('scroll', soon);
	}
	read();
})();
</script>
</html>

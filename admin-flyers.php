<?php
/**
 * admin-flyers.php — the "is now live on Skulliance" flyer, built in the browser.
 *
 * The designer who made the partner flyers left no PSD, so this rebuilds
 * the layout from the finished ones: brand mark top left, the project's
 * art in a slanted dry-brushed window, the project logo over its bottom
 * edge, the points icon in a ring, and the fixed text stack. Colours are
 * per flyer, and can be sampled straight from the art.
 *
 * Nothing is stored. The drawing is js/flyer-builder.js on a canvas and
 * the download is that canvas, so this page has no POST and writes
 * nothing to disk -- which is also why admin-harness.php's write-path
 * checks (POST gate, adminIsSuper re-check) do not apply to it.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

$FLYER_PROJECTS = array();
foreach (adm_projects($conn) as $id => $p) {
	$ic = admin_currency_icon($p['currency']);
	$FLYER_PROJECTS[] = array(
		'id' => $id, 'name' => $p['name'], 'currency' => $p['currency'],
		'icon' => is_file(__DIR__ . '/' . $ic) ? $ic : '',
	);
}

include 'header.php';   // GLOBAL scope: header.php reads $name and $avatar_url
admin_chrome('flyers');
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Saira+Semi+Condensed:wght@800&family=Rajdhani:wght@700&family=Russo+One&family=Open+Sans:ital,wght@0,400;0,600;1,600&display=block" rel="stylesheet">

<p class="adm-note">Builds the &ldquo;is now live on&rdquo; announcement flyer. Pick a project to fill in its name,
  ticker and points icon, add the art and the logo, and download a 1500&times;1560 PNG. Nothing is saved.</p>

<div class="fly">
  <div class="fly-controls">

    <div class="adm-card">
      <h3>Project</h3>
      <div class="adm-form">
        <label>Fill in from
          <select id="f-project">
            <option value="">&mdash; pick a project (optional) &mdash;</option>
            <?php foreach ($FLYER_PROJECTS as $p): ?>
              <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name'] . ' (' . $p['currency'] . ')'); ?></option>
            <?php endforeach; ?>
          </select></label>
        <div class="adm-grid">
          <label>Project name
            <input type="text" id="f-name" placeholder="Used if there is no logo">
            <small>Drawn as text only when no logo is uploaded.</small></label>
          <label>Points ticker
            <input type="text" id="f-ticker" placeholder="e.g. MUSE"></label>
        </div>
      </div>
    </div>

    <div class="adm-card">
      <h3>Images</h3>
      <div class="adm-form">
        <label>Project art
          <input type="file" id="f-art" accept="image/*">
          <small>Fills the slanted window. Drag the preview to move it.</small></label>
        <label>Project logo
          <input type="file" id="f-logo" accept="image/*">
          <small>A transparent PNG works best.</small></label>
        <label class="adm-inline"><input type="checkbox" id="f-logoWhite"> Make the logo white</label>
        <label>Points icon
          <input type="file" id="f-icon" accept="image/*">
          <small>Filled in from the project's currency icon when there is one.</small></label>
        <label class="adm-inline"><input type="checkbox" id="f-iconWhite" checked> Make the icon white</label>
        <label class="adm-inline"><input type="checkbox" id="f-showIcon" checked> Show the icon ring</label>
      </div>
    </div>

    <div class="adm-card">
      <h3>Colors</h3>
      <div class="adm-form">
        <div class="fly-swatches" id="f-swatches"><small>Upload art to get colors from it.</small></div>
        <div class="fly-colors">
          <label><span>Applies to</span>
            <select id="f-target">
              <option value="all">Everything</option>
              <option value="colHighlight">&ldquo;Is now live on&rdquo;</option>
              <option value="colDivider">Divider line</option>
              <option value="colBullets">Bullet line</option>
              <option value="colRing">Icon ring + ticker</option>
            </select></label>
          <label>&ldquo;Is now live on&rdquo; <input type="color" id="f-colHighlight" value="#dc3b55"></label>
          <label>Divider <input type="color" id="f-colDivider" value="#dc3b55"></label>
          <label>Bullets <input type="color" id="f-colBullets" value="#dc3b55"></label>
          <label>Ring + ticker <input type="color" id="f-colRing" value="#dc3b55"></label>
        </div>
        <label>Hue shift <input type="range" id="f-hue" min="-180" max="180" value="0">
          <small>Slides the color(s) above around the wheel.</small></label>
        <label class="adm-inline"><input type="checkbox" id="f-dropper"> Eyedropper: click the preview to pick a color</label>
      </div>
    </div>

    <div class="adm-card">
      <h3>Art &amp; window</h3>
      <div class="adm-form">
        <div class="adm-grid">
          <label>Zoom <input type="range" id="f-artZoom" min="0.4" max="3" step="0.01" value="1"></label>
          <label>Move left/right <input type="range" id="f-artX" min="-1" max="1" step="0.005" value="0"></label>
          <label>Move up/down <input type="range" id="f-artY" min="-1" max="1" step="0.005" value="0"></label>
          <label>Shade at the bottom <input type="range" id="f-shade" min="0" max="1" step="0.01" value="0.35"></label>
          <label>Top edge, left <input type="range" id="f-winTopL" min="0" max="700" value="360"></label>
          <label>Top edge, right <input type="range" id="f-winTopR" min="-700" max="400" value="-180"></label>
          <label>Bottom edge, left <input type="range" id="f-winBotL" min="700" max="1300" value="1080"></label>
          <label>Bottom edge, right <input type="range" id="f-winBotR" min="400" max="1200" value="705"></label>
          <label>Grit <input type="range" id="f-grit" min="0" max="2" step="0.05" value="1"></label>
        </div>
        <button type="button" id="f-reroll" class="fly-ghost">Re-roll the brush strokes</button>
      </div>
    </div>

    <div class="adm-card">
      <h3>Layout</h3>
      <div class="adm-form">
        <div class="adm-grid">
          <label>Logo size <input type="range" id="f-logoScale" min="0.3" max="1.6" step="0.01" value="1"></label>
          <label>Logo up/down <input type="range" id="f-logoY" min="-200" max="160" value="0"></label>
          <label>Icon left/right <input type="range" id="f-iconX" min="100" max="1400" value="1300"></label>
          <label>Icon up/down <input type="range" id="f-iconY" min="250" max="1150" value="830"></label>
          <label>Icon size inside ring <input type="range" id="f-iconScale" min="0.4" max="1.4" step="0.01" value="1"></label>
          <label>Headline font
            <select id="f-headFont">
              <option value='800 "Saira Semi Condensed"'>Saira Semi Condensed</option>
              <option value='700 "Rajdhani"'>Rajdhani</option>
              <option value='400 "Russo One"'>Russo One</option>
            </select></label>
        </div>
      </div>
    </div>

    <div class="adm-card">
      <h3>Text</h3>
      <div class="adm-form">
        <label>Highlight line <input type="text" id="f-line1" value="Is now live on"></label>
        <label>Headline <input type="text" id="f-line2" value="Skulliance Staking Platform"></label>
        <label>Tagline <input type="text" id="f-line3" value="Non-custodial staking with off-chain points"></label>
        <div class="adm-grid">
          <label>Bullet 1 <input type="text" id="f-b1" value="Daily rewards"></label>
          <label>Bullet 2 <input type="text" id="f-b2" value="Member incentives"></label>
          <label>Bullet 3 <input type="text" id="f-b3" value="Holder leaderboards"></label>
        </div>
        <label>URL <input type="text" id="f-url" value="skulliance.io/staking"></label>
      </div>
    </div>
  </div>

  <div class="fly-preview">
    <canvas id="f-canvas" width="1500" height="1560"></canvas>
    <div class="fly-actions">
      <button type="button" id="f-download">Download PNG</button>
      <span class="adm-note" id="f-status"></span>
    </div>
  </div>
</div>

</div></div>

<style>
.fly{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:18px;align-items:start}
.fly-preview{position:sticky;top:12px}
.fly-preview canvas{width:100%;height:auto;display:block;border:1px solid rgba(0,200,160,.14);cursor:grab;touch-action:none}
.fly-preview canvas.dropper{cursor:crosshair}
.fly-actions{display:flex;gap:14px;align-items:center;margin-top:12px;flex-wrap:wrap}
.fly-actions .adm-note{margin:0}
.fly-swatches{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.adm .fly-swatches button{width:34px;height:34px;padding:0;border:2px solid rgba(255,255,255,.25);cursor:pointer;align-self:auto}
.adm .fly-swatches button:hover{border-color:#fff}
.fly-colors{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.adm .fly-colors input[type=color]{height:38px;padding:2px;width:100%}
.adm input[type=range]{padding:0;border:0;background:transparent;accent-color:#00c8a0}
.adm .fly-ghost{background:transparent;color:#00c8a0;border:1px solid rgba(0,200,160,.4)}
.adm .fly-ghost:hover{background:rgba(0,200,160,.12)}
@media (max-width:900px){.fly{grid-template-columns:1fr}.fly-preview{position:static;order:-1}}
</style>
<?php include 'admin-css.php'; ?>

<script src="js/flyer-builder.js?v=<?php echo @filemtime(__DIR__ . '/js/flyer-builder.js'); ?>"></script>
<script>
(function () {
	var PROJECTS = <?php echo json_encode($FLYER_PROJECTS, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
	var $ = function (id) { return document.getElementById('f-' + id); };
	var canvas = $('canvas'), status = $('status');
	var imgs = { brand: null, art: null, logo: null, icon: null };
	var seed = 1337;
	var COLS = ['colHighlight', 'colDivider', 'colBullets', 'colRing'];
	var NUM = ['artZoom', 'artX', 'artY', 'shade', 'winTopL', 'winTopR', 'winBotL', 'winBotR', 'grit',
	           'logoScale', 'logoY', 'iconX', 'iconY', 'iconScale'];

	function loadImg(src) {
		return new Promise(function (ok, no) {
			var i = new Image(); i.onload = function () { ok(i); }; i.onerror = no; i.src = src;
		});
	}
	function fromFile(input) {
		var f = input.files && input.files[0];
		if (!f) return Promise.resolve(null);
		return new Promise(function (ok, no) {
			var r = new FileReader();
			r.onload = function () { loadImg(r.result).then(ok, no); };
			r.onerror = no; r.readAsDataURL(f);
		});
	}

	function state() {
		var s = { seed: seed };
		NUM.forEach(function (k) { s[k] = parseFloat($(k).value); });
		COLS.forEach(function (k) { s[k] = $(k).value; });
		['logoWhite', 'iconWhite', 'showIcon'].forEach(function (k) { s[k] = $(k).checked; });
		['name', 'ticker', 'line1', 'line2', 'line3', 'url', 'headFont'].forEach(function (k) { s[k] = $(k).value; });
		s.bullets = [$('b1').value, $('b2').value, $('b3').value];
		return s;
	}

	/* One frame per animation tick, however many inputs fire. */
	var queued = false;
	function draw() {
		if (queued) return; queued = true;
		requestAnimationFrame(function () { queued = false; FlyerBuilder.render(canvas, state(), imgs); });
	}

	document.querySelectorAll('.fly input[type=range], .fly input[type=text], .fly input[type=checkbox], .fly input[type=color], #f-headFont')
		.forEach(function (el) { if (el.id !== 'f-hue' && el.id !== 'f-dropper') el.addEventListener('input', draw); });
	$('headFont').addEventListener('change', draw);

	/* ---- colours ---- */
	function targets() { var t = $('target').value; return t === 'all' ? COLS : [t]; }
	function setColor(hex) { targets().forEach(function (k) { $(k).value = hex; }); hueBase(); draw(); }

	function hexToHsl(hex) {
		var r = parseInt(hex.substr(1, 2), 16) / 255, g = parseInt(hex.substr(3, 2), 16) / 255, b = parseInt(hex.substr(5, 2), 16) / 255;
		var mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, h = 0, s = 0, d = mx - mn;
		if (d) {
			s = d / (1 - Math.abs(2 * l - 1));
			if (mx === r) h = ((g - b) / d) % 6; else if (mx === g) h = (b - r) / d + 2; else h = (r - g) / d + 4;
			h = (h * 60 + 360) % 360;
		}
		return [h, s, l];
	}
	function hslToHex(h, s, l) {
		var c = (1 - Math.abs(2 * l - 1)) * s, x = c * (1 - Math.abs((h / 60) % 2 - 1)), m = l - c / 2, rgb;
		if (h < 60) rgb = [c, x, 0]; else if (h < 120) rgb = [x, c, 0]; else if (h < 180) rgb = [0, c, x];
		else if (h < 240) rgb = [0, x, c]; else if (h < 300) rgb = [x, 0, c]; else rgb = [c, 0, x];
		return '#' + rgb.map(function (v) { var t = Math.round((v + m) * 255).toString(16); return t.length < 2 ? '0' + t : t; }).join('');
	}
	/* The hue slider shifts from where the colours were when it was last
	   touched by anything else, so dragging it back to 0 undoes it. */
	var base = {};
	function hueBase() { COLS.forEach(function (k) { base[k] = $(k).value; }); $('hue').value = 0; }
	$('hue').addEventListener('input', function () {
		var dh = parseFloat(this.value);
		targets().forEach(function (k) {
			var hsl = hexToHsl(base[k]);
			$(k).value = hslToHex((hsl[0] + dh + 360) % 360, hsl[1], hsl[2]);
		});
		draw();
	});
	$('target').addEventListener('change', hueBase);
	COLS.forEach(function (k) { $(k).addEventListener('change', hueBase); });
	hueBase();

	function swatches() {
		var box = $('swatches');
		if (!imgs.art) return;
		var list = FlyerBuilder.palette(imgs.art, 8);
		box.innerHTML = '';
		list.forEach(function (hex) {
			var b = document.createElement('button');
			b.type = 'button'; b.style.background = hex; b.title = hex;
			b.addEventListener('click', function () { setColor(hex); });
			box.appendChild(b);
		});
		if (!list.length) box.innerHTML = '<small>No strong colors found in the art.</small>';
	}

	$('dropper').addEventListener('change', function () { canvas.classList.toggle('dropper', this.checked); });

	/* ---- canvas: drag to pan, or click to sample ---- */
	function toCanvas(e) {
		var r = canvas.getBoundingClientRect();
		return { x: (e.clientX - r.left) * canvas.width / r.width, y: (e.clientY - r.top) * canvas.height / r.height };
	}
	var drag = null;
	canvas.addEventListener('pointerdown', function (e) {
		var p = toCanvas(e);
		if ($('dropper').checked) {
			var d = canvas.getContext('2d').getImageData(Math.round(p.x), Math.round(p.y), 1, 1).data;
			setColor('#' + [d[0], d[1], d[2]].map(function (v) { var t = v.toString(16); return t.length < 2 ? '0' + t : t; }).join(''));
			$('dropper').checked = false; canvas.classList.remove('dropper');
			return;
		}
		if (!imgs.art) return;
		drag = { x: p.x, y: p.y, ax: parseFloat($('artX').value), ay: parseFloat($('artY').value) };
		canvas.setPointerCapture(e.pointerId);
	});
	canvas.addEventListener('pointermove', function (e) {
		if (!drag) return;
		var p = toCanvas(e), bh = Math.max(+$('winBotL').value, +$('winBotR').value) - Math.min(+$('winTopL').value, +$('winTopR').value) + 300;
		$('artX').value = Math.max(-1, Math.min(1, drag.ax + (p.x - drag.x) / (FlyerBuilder.W / 2)));
		$('artY').value = Math.max(-1, Math.min(1, drag.ay + (p.y - drag.y) / (bh / 2)));
		draw();
	});
	canvas.addEventListener('pointerup', function () { drag = null; });

	/* ---- inputs ---- */
	function wire(id, key, after) {
		$(id).addEventListener('change', function () {
			fromFile(this).then(function (img) { imgs[key] = img; if (after) after(); draw(); },
			                    function () { status.textContent = 'That file could not be read as an image.'; });
		});
	}
	wire('art', 'art', function () { swatches(); });
	wire('logo', 'logo');
	wire('icon', 'icon');
	$('reroll').addEventListener('click', function () { seed = (Math.random() * 1e9) | 0; draw(); });

	$('project').addEventListener('change', function () {
		var id = parseInt(this.value, 10), p = null;
		PROJECTS.forEach(function (q) { if (q.id === id) p = q; });
		if (!p) return;
		$('name').value = p.name;
		$('ticker').value = p.currency;
		$('icon').value = '';
		imgs.icon = null;
		if (p.icon) loadImg(p.icon + '?v=' + Date.now()).then(function (i) { imgs.icon = i; draw(); }, function () {});
		draw();
	});

	$('download').addEventListener('click', function () {
		var name = ($('name').value || $('ticker').value || 'flyer').replace(/[^A-Za-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'flyer';
		try {
			canvas.toBlob(function (b) {
				var a = document.createElement('a');
				a.href = URL.createObjectURL(b); a.download = name + '.png';
				document.body.appendChild(a); a.click(); a.remove();
				setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
			}, 'image/png');
		} catch (err) {
			status.textContent = 'The browser would not export the canvas: ' + err.message;
		}
	});

	/* Fonts first, or the first frame is drawn in a fallback face and
	   the measured headline width is wrong. */
	var faces = ['800 40px "Saira Semi Condensed"', '700 40px "Rajdhani"', '400 40px "Russo One"',
	             '400 20px "Open Sans"', '600 20px "Open Sans"', 'italic 600 20px "Open Sans"'];
	Promise.all(faces.map(function (f) { return document.fonts.load(f).catch(function () {}); }))
		.then(function () { return loadImg('images/skulliancelogo.png').then(function (i) { imgs.brand = i; }, function () {}); })
		.then(draw);
	draw();
})();
</script>

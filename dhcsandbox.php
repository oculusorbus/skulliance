<?php
// DIGITAL HELL CITIZENS 2 -- TRAIT ASSEMBLY SANDBOX
//
// A public proof-of-concept for the trait layering system, built so Maxingo and
// Oculus Orbus can verify combinations before anything real is built on top of it.
//
// INCLUDES NOTHING -- not db.php, not skulliance.php, not header.php. Same rule
// guardiansgame.php and skullracergame.php follow: no session, no login, no
// database, so it renders identically for every visitor and cannot break when
// something else does. That matters here because the whole point is handing a
// link to an artist outside the platform.
//
// The trait art is NOT in this repo (it is another artist's source work, and it
// is large) -- it is uploaded to the server separately. This page DISCOVERS what
// is there at request time with glob() rather than carrying a hardcoded list, so
// adding, renaming or removing art needs no code change here.
//
// LAYER ORDER is the thing being tested. See dhc/LAYER-MANIFEST.json for how it
// was derived. "Weapon (behind)" exists because some weapons wrap around the
// body -- Maxingo already supplies three of them as two files -- and which half
// belongs behind the torso is the open question this page is meant to answer.

// Where the art landed. Checked in order so a different upload path needs one
// edit here, not a hunt through the file.
$dhc_base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $dhc_base = $c; break; }
}

// slot key => [label, directory under <base>/<size>/, optional]
$dhc_slots = array(
	'background' => array('Background',      'background', false),
	// EFFECTS SIT ON THE BACKGROUND, not over the character. They are scene
	// atmosphere -- flames, sparks, attack bursts -- and drawing them last put
	// them across the Fighter's face. Behind the body they read as the
	// environment the character is standing in, which is what they are.
	'effects1'   => array('Effects 1',       'effects',    true),
	'effects2'   => array('Effects 2',       'effects',    true),
	'weaponBack' => array('Weapon (behind)', 'weapon',     true),
	'torso'      => array('Torso',           'torso',      false),
	// WEAPON BEFORE ARMS. A weapon is gripped, so the hand and forearm belong in
	// front of it -- drawing arms first put the grip over the knuckles. Reported
	// from the sandbox; the guessed order in LAYER-MANIFEST.json had it backwards.
	'weapon'     => array('Weapon',          'weapon',     true),
	'arms'       => array('Arms',            'arms',       true),
	'head'       => array('Head',            'head',       false),
	'headgear'   => array('Headgear',        'headgear',   true),
	'companion'  => array('Companion',       'companion',  true),
);

// WHICH WEAPONS BELONG IN WHICH SLOT.
//
// A PARTITION, not a preference: each weapon belongs to exactly one slot. The
// seven below are drawn in front of the body; every other weapon is drawn behind
// it. Neither list offers the other's art, so a weapon can only ever be placed
// where it actually works.
//
// It also resolves the three two-part weapons -- one half of each pair is in
// front, the other necessarily behind:
//
//   DH Spike Blaster 1  front   <->  DH Spike Blaster   back
//   Lil Fren            front   <->  Lil Fren 1         back
//   Mega Taser Cannon 1 front   <->  Mega Taser Cannon  back
$dhc_weapon_front = array(
	'annihilation-belt', 'dh-raider-equipment', 'dh-spike-blaster-1',
	'electric-morning-star', 'lil-fren', 'mega-taser-cannon-1', 'plastic-blaster',
);

// Display names come from trait-index.json when it is uploaded alongside the
// art; otherwise they are derived from the slug, so the page still reads well
// with nothing but the images present.
$dhc_index = array();
if ($dhc_base !== '' && is_file(__DIR__ . '/' . $dhc_base . '/trait-index.json')) {
	$dhc_index = json_decode(file_get_contents(__DIR__ . '/' . $dhc_base . '/trait-index.json'), true) ?: array();
}
function dhc_title($slug) {
	$s = str_replace('-', ' ', $slug);
	$s = preg_replace_callback('/\b([a-z])/', function ($m) { return strtoupper($m[1]); }, $s);
	return preg_replace('/\b(Dh|Xlon|Mk|Ue|Zx|Vr|Dhc)\b/', '\\1', $s);
}

$dhc_traits = array();
foreach ($dhc_slots as $key => $s) {
	$dir = $s[1];
	$out = array();
	if ($dhc_base !== '') {
		foreach ((array)glob(__DIR__ . '/' . $dhc_base . '/250/' . $dir . '/*.png') as $f) {
			$slug = basename($f, '.png');
			// Filtered at the source: each weapon appears in one slot only.
			if ($key === 'weapon'     && !in_array($slug, $dhc_weapon_front, true)) continue;
			if ($key === 'weaponBack' &&  in_array($slug, $dhc_weapon_front, true)) continue;
			$name = isset($dhc_index[$dir][$slug]['name']) ? $dhc_index[$dir][$slug]['name'] : dhc_title($slug);
			$out[] = array('slug' => $slug, 'name' => $name);
		}
	}
	$dhc_traits[$key] = $out;
}
/*
 * ARMLESS TORSO VARIANTS, optional and per-torso.
 *
 * Every torso is drawn WITH arms, so an Augmented Arms trait overlays a limb that
 * is already there and the original shows around it. There is no way to separate
 * them automatically -- the arms are as thick as the body, so neither a lateral
 * mask nor a morphological opening isolates them, and the union of the arm traits
 * swallows 94% of the chest. It needs hand-editing against the layered source.
 *
 * So: if web/<size>/torso-noarms/<slug>.png exists, it is used INSTEAD of the
 * normal torso whenever an arms trait is selected. Checked per torso rather than
 * all-or-nothing, so the set can be filled in one at a time and each one starts
 * working the moment it lands. Nothing breaks while they are missing.
 */
$dhc_noarms = array();
if ($dhc_base !== '') {
	foreach ((array)glob(__DIR__ . '/' . $dhc_base . '/1000/torso-noarms/*.png') as $f) {
		$dhc_noarms[] = basename($f, '.png');
	}
}

$dhc_total = 0;
foreach (array('background','torso','arms','head','headgear','weapon','companion','effects1') as $k) {
	$dhc_total += count($dhc_traits[$k]);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DHC2 Trait Sandbox &mdash; Skulliance</title>
<meta name="description" content="Assemble a Digital Hell Citizens 2 Fighter from 194 individual traits.">
<?php
/*
 * SOCIAL CARD. Both og:image AND twitter:card=summary_large_image are required
 * -- with only og:image, X renders a small square thumbnail instead of the wide
 * card, which is the failure mode already documented in skullpaper/MAINTENANCE.md
 * for the game share buttons.
 *
 * ABSOLUTE urls, because a crawler has no page context to resolve a relative one
 * against. That is the one place on this platform where a relative link is wrong;
 * everywhere else it is required, since the login cookie is host-only.
 *
 * The card art is a pre-rendered composite that ships with the trait upload, not
 * something generated per request -- no GD dependency, nothing to fail on a
 * shared host, and a crawler gets a fast static file.
 *
 * NOT noindex any more: a noindex page can still be shared, but leaving it
 * crawlable means the card is validated and cached by X the first time anyone
 * posts it rather than on the visitor's own fetch.
 */
$dhc_url = 'https://skulliance.io/staking/dhcsandbox.php';
$dhc_card = $dhc_base !== ''
    ? 'https://skulliance.io/staking/' . $dhc_base . '/card.png'
    : 'https://skulliance.io/staking/images/skulliance.png';
?>
<link rel="canonical" href="<?php echo $dhc_url; ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $dhc_url; ?>">
<meta property="og:title" content="DHC2 Trait Sandbox">
<meta property="og:description" content="Assemble a Digital Hell Citizens 2 Fighter from 194 individual traits. Art by Maxingo.">
<meta property="og:image" content="<?php echo $dhc_card; ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="A Digital Hell Citizens 2 Fighter assembled from layered traits.">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="DHC2 Trait Sandbox">
<meta name="twitter:description" content="Assemble a Digital Hell Citizens 2 Fighter from 194 individual traits. Art by Maxingo.">
<meta name="twitter:image" content="<?php echo $dhc_card; ?>">
<meta name="twitter:image:alt" content="A Digital Hell Citizens 2 Fighter assembled from layered traits.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=JetBrains+Mono:wght@400;600&display=swap">
<style>
:root{
  --ink:#100d0d; --panel:#191414; --panel2:#221b1b; --line:#312727;
  --bone:#ece7dc; --dim:#9b9086; --blood:#d0463a; --ochre:#e0a13c; --teal:#4fa39c;
}
*{box-sizing:border-box}
html,body{margin:0;height:100%}
body{
  background:var(--ink); color:var(--bone);
  font:14px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  -webkit-font-smoothing:antialiased;
}
h1,h2,h3,.btn,.tab{font-family:"Archivo Black",Impact,sans-serif;font-weight:400}
a{color:var(--ochre)}

.top{display:flex;align-items:baseline;gap:16px;flex-wrap:wrap;
  padding:14px 20px;border-bottom:1px solid var(--line);background:var(--panel)}
.top h1{font-size:17px;margin:0;letter-spacing:.02em}
.top .sub{font-size:11px;color:var(--dim)}
.top .spacer{flex:1}
.badge{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink);
  background:var(--ochre);padding:3px 8px;border-radius:2px}

.shell{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:0;height:calc(100% - 52px)}
@media (max-width:900px){.shell{grid-template-columns:1fr;height:auto}}

/* ---- stage ---- */
.stage{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
  padding:22px;gap:14px;min-width:0;overflow:auto}
.frame{position:relative;width:min(66vh,100%);aspect-ratio:1;background:var(--panel2);
  border:1px solid var(--line);box-shadow:0 18px 60px rgba(0,0,0,.55)}
.frame img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;
  image-rendering:auto;pointer-events:none}
.frame .empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  color:var(--dim);font-size:12px;text-align:center;padding:30px;line-height:1.7}
.bar{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);
  padding:9px 15px;font-size:12px;cursor:pointer;border-radius:2px;letter-spacing:.03em}
.btn:hover{border-color:var(--ochre);color:var(--ochre)}
.btn:focus-visible{outline:2px solid var(--ochre);outline-offset:2px}
.btn.primary{background:var(--blood);border-color:var(--blood);color:#fff}
.btn.primary:hover{background:#e0554a;border-color:#e0554a;color:#fff}

/* ---- stack readout ---- */
.stack{width:min(66vh,100%);border:1px solid var(--line);background:var(--panel)}
.stack h3{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  color:var(--dim);border-bottom:1px solid var(--line)}
.stack ol{margin:0;padding:6px 0;list-style:none;font-size:11.5px}
.stack li{display:flex;gap:10px;padding:3px 12px;color:var(--dim)}
.stack li b{color:var(--bone);font-weight:400;min-width:112px}
.stack li.off{opacity:.34}
.stack li .n{color:var(--teal)}

/* ---- picker ---- */
.picker{border-left:1px solid var(--line);background:var(--panel);display:flex;
  flex-direction:column;min-height:0}
@media (max-width:900px){.picker{border-left:0;border-top:1px solid var(--line)}}
.tabs{display:flex;flex-wrap:wrap;gap:1px;padding:10px;border-bottom:1px solid var(--line)}
.tab{background:var(--panel2);border:1px solid transparent;color:var(--dim);font-size:10.5px;
  padding:6px 9px;cursor:pointer;letter-spacing:.04em;border-radius:2px}
.tab:hover{color:var(--bone)}
.tab[aria-selected="true"]{background:var(--blood);color:#fff}
.tab .dot{color:var(--ochre)}
.grid{flex:1;overflow:auto;padding:10px;display:grid;
  grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:8px;align-content:start}
.cell{background:var(--panel2);border:1px solid var(--line);cursor:pointer;padding:0;
  display:flex;flex-direction:column;border-radius:2px;overflow:hidden}
.cell:hover{border-color:var(--ochre)}
.cell:focus-visible{outline:2px solid var(--ochre);outline-offset:1px}
.cell[aria-pressed="true"]{border-color:var(--blood);box-shadow:inset 0 0 0 1px var(--blood)}
.cell img{width:100%;aspect-ratio:1;object-fit:contain;background:
  repeating-conic-gradient(#1b1616 0% 25%,#221b1b 0% 50%) 50%/12px 12px}
.cell span{font-size:9.5px;line-height:1.3;padding:5px 6px;color:var(--dim);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cell[aria-pressed="true"] span{color:var(--bone)}
.cell.none img{background:var(--panel);display:flex}
.hint{padding:10px 12px;font-size:11px;color:var(--dim);border-top:1px solid var(--line);line-height:1.6}
.warn{margin:22px;padding:18px;border:1px solid var(--blood);background:rgba(208,70,58,.09);
  font-size:12.5px;line-height:1.7;border-radius:2px}
.warn code{color:var(--ochre)}
</style>
</head>
<body>

<div class="top">
  <h1>DHC2 Trait Sandbox</h1>
  <span class="sub">Digital Hell Citizens 2: Fighters &middot; art by Maxingo</span>
  <span class="spacer"></span>
  <span class="badge">Proof of concept</span>
</div>

<?php if ($dhc_base === ''): ?>
  <div class="warn">
    <b>No trait art found.</b><br>
    This page looks for a folder containing <code>1000/</code> and <code>250/</code> next to it &mdash;
    it tried <code>web/</code>, <code>dhc/</code>, <code>dhc/web/</code> and <code>traits/</code>.<br><br>
    Upload the <code>web</code> folder into the same directory as this file, or add your path to
    the <code>$dhc_base</code> list at the top of <code>dhcsandbox.php</code>.
  </div>
<?php else: ?>

<div class="shell">
  <div class="stage">
    <div class="frame" id="frame">
      <div class="empty" id="empty">Pick a background and a torso to begin &mdash;<br>or hit Randomise.</div>
    </div>
    <div class="bar">
      <button class="btn primary" id="rand">Randomise</button>
      <button class="btn" id="randFull">Randomise (everything)</button>
      <button class="btn" id="clear">Clear</button>
      <button class="btn" id="share">Copy link to this build</button>
    </div>
    <div class="stack">
      <h3>Draw order &mdash; back to front</h3>
      <ol id="stackList"></ol>
    </div>
  </div>

  <div class="picker">
    <div class="tabs" id="tabs" role="tablist"></div>
    <div class="grid" id="grid" role="tabpanel"></div>
    <div class="hint">
      Each weapon belongs to one slot only. <b>Weapon</b> holds the seven that sit in front of the
      body; <b>Weapon (behind)</b> holds the rest, drawn before the torso so the body covers part
      of them.<br><br>
      The three two-part weapons pair across the two slots &mdash; <b>DH Spike Blaster</b> behind
      with <b>DH Spike Blaster 1</b> in front, <b>Lil Fren 1</b> behind with <b>Lil Fren</b> in
      front, <b>Mega Taser Cannon</b> behind with <b>Mega Taser Cannon 1</b> in front.
    </div>
  </div>
</div>

<script>
(function () {
  var BASE   = <?php echo json_encode($dhc_base); ?>;
  var SLOTS  = <?php echo json_encode(array_map(function ($k, $s) {
      return array('key'=>$k,'label'=>$s[0],'dir'=>$s[1],'optional'=>$s[2]);
  }, array_keys($dhc_slots), $dhc_slots)); ?>;
  var TRAITS = <?php echo json_encode($dhc_traits); ?>;
  var NOARMS = <?php echo json_encode($dhc_noarms); ?>;   // torsos with a hand-made armless variant

  var sel = {}, active = SLOTS[0].key;
  var frame = document.getElementById('frame');
  var empty = document.getElementById('empty');
  var tabsEl = document.getElementById('tabs');
  var gridEl = document.getElementById('grid');
  var stackEl = document.getElementById('stackList');

  function url(dir, slug, size) { return BASE + '/' + size + '/' + dir + '/' + slug + '.png'; }
  function slotByKey(k) { for (var i=0;i<SLOTS.length;i++) if (SLOTS[i].key===k) return SLOTS[i]; }

  /* ---- render the composite. One <img> per slot, kept in DOM order so the
         browser paints them back-to-front exactly as SLOTS is declared. ---- */
  function paint() {
    SLOTS.forEach(function (s) {
      var id = 'L-' + s.key, el = document.getElementById(id);
      if (!sel[s.key]) { if (el) el.remove(); return; }
      if (!el) {
        el = document.createElement('img');
        el.id = id; el.alt = '';
        frame.appendChild(el);
      }
      // Swap in the armless torso when arms are on and a variant exists for it.
      var dir = s.dir;
      if (s.key === 'torso' && sel.arms && NOARMS.indexOf(sel[s.key]) !== -1) dir = 'torso-noarms';
      var want = url(dir, sel[s.key], 1000);
      if (el.getAttribute('src') !== want) el.setAttribute('src', want);
    });
    // keep DOM order == layer order, regardless of the order things were picked
    SLOTS.forEach(function (s) {
      var el = document.getElementById('L-' + s.key);
      if (el) frame.appendChild(el);
    });
    empty.style.display = Object.keys(sel).length ? 'none' : 'flex';
    paintStack();
    writeHash();
  }

  function paintStack() {
    stackEl.innerHTML = '';
    SLOTS.forEach(function (s, i) {
      var li = document.createElement('li');
      var chosen = sel[s.key];
      if (!chosen) li.className = 'off';
      var name = '—';
      if (chosen) {
        var list = TRAITS[s.key] || [];
        for (var j=0;j<list.length;j++) if (list[j].slug===chosen) name = list[j].name;
      }
      var tag = '';
      if (s.key === 'torso' && chosen && sel.arms)
        tag = NOARMS.indexOf(chosen) !== -1
            ? ' <em style="color:var(--teal);font-style:normal">armless</em>'
            : ' <em style="color:var(--ochre);font-style:normal">arms underneath</em>';
      li.innerHTML = '<span class="n">' + (i+1) + '</span><b>' + s.label + '</b><span>' + name + tag + '</span>';
      stackEl.appendChild(li);
    });
  }

  /* ---- picker ---- */
  /* Clears first: this is called again on every selection to repaint the
     dot markers, and an append-only version stacked a fresh set of ten tabs
     on each click. Idempotent here rather than relying on callers to reach
     for a separate refresh helper -- there is now only one function to call. */
  function buildTabs() {
    tabsEl.innerHTML = '';
    SLOTS.forEach(function (s) {
      var b = document.createElement('button');
      b.className = 'tab'; b.type = 'button'; b.setAttribute('role','tab');
      b.dataset.key = s.key;
      b.innerHTML = s.label + (sel[s.key] ? ' <span class="dot">&bull;</span>' : '');
      b.setAttribute('aria-selected', s.key === active ? 'true' : 'false');
      b.addEventListener('click', function () { active = s.key; buildTabs(); buildGrid(); });
      tabsEl.appendChild(b);
    });
  }

  function buildGrid() {
    var s = slotByKey(active), list = TRAITS[active] || [];
    gridEl.innerHTML = '';
    if (s.optional) {
      var none = document.createElement('button');
      none.className = 'cell none'; none.type = 'button';
      none.setAttribute('aria-pressed', !sel[active] ? 'true' : 'false');
      none.innerHTML = '<img alt=""><span>None</span>';
      none.addEventListener('click', function () { delete sel[active]; buildTabs(); paint(); buildGrid(); });
      gridEl.appendChild(none);
    }
    list.forEach(function (t) {
      var b = document.createElement('button');
      b.className = 'cell'; b.type = 'button'; b.title = t.name;
      b.setAttribute('aria-pressed', sel[active] === t.slug ? 'true' : 'false');
      var i = document.createElement('img');
      i.loading = 'lazy'; i.alt = t.name; i.src = url(s.dir, t.slug, 250);
      var sp = document.createElement('span'); sp.textContent = t.name;
      b.appendChild(i); b.appendChild(sp);
      b.addEventListener('click', function () {
        sel[active] = t.slug; buildTabs(); paint(); buildGrid();
      });
      gridEl.appendChild(b);
    });
  }

  /* ---- shuffle ---- */
  function pick(k) {
    var l = TRAITS[k] || [];
    return l.length ? l[Math.floor(Math.random()*l.length)].slug : null;
  }
  function randomise(all) {
    sel = {};
    SLOTS.forEach(function (s) {
      if (s.key === 'weaponBack') return;                 // opt-in only, it is the thing under test
      if (!s.optional) { var v = pick(s.key); if (v) sel[s.key] = v; return; }
      // roughly mirror the real collection: optional slots are absent more often than present
      var odds = (s.key === 'effects2') ? 0.15 : (s.key === 'companion' ? 0.2 : 0.45);
      if (all || Math.random() < odds) { var x = pick(s.key); if (x) sel[s.key] = x; }
    });
    buildTabs(); paint(); buildGrid();
  }

  /* ---- share a build in the url, so a combination can be sent to someone ---- */
  function writeHash() {
    var parts = [];
    SLOTS.forEach(function (s) { if (sel[s.key]) parts.push(s.key + '=' + sel[s.key]); });
    history.replaceState(null, '', parts.length ? '#' + parts.join('&') : location.pathname);
  }
  function readHash() {
    var h = location.hash.replace(/^#/, '');
    if (!h) return false;
    var got = false;
    h.split('&').forEach(function (p) {
      var kv = p.split('='), s = slotByKey(kv[0]);
      if (!s || !kv[1]) return;
      var list = TRAITS[kv[0]] || [];
      for (var i=0;i<list.length;i++) if (list[i].slug === kv[1]) { sel[kv[0]] = kv[1]; got = true; }
    });
    return got;
  }

  document.getElementById('rand').addEventListener('click', function () { randomise(false); });
  document.getElementById('randFull').addEventListener('click', function () { randomise(true); });
  document.getElementById('clear').addEventListener('click', function () {
    sel = {}; buildTabs(); paint(); buildGrid();
  });
  document.getElementById('share').addEventListener('click', function () {
    var btn = this, was = btn.textContent;
    writeHash();
    var done = function () { btn.textContent = 'Link copied'; setTimeout(function(){ btn.textContent = was; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, done); else done();
  });

  buildTabs();
  if (!readHash()) randomise(false); else { buildTabs(); paint(); }
  buildGrid();
})();
</script>
<?php endif; ?>
</body>
</html>

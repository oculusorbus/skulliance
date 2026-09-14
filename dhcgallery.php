<?php
/**
 * DHC FIGHTERS -- THE COLLECTION
 *
 * Every Fighter assembled on the platform, browsable. dhcfighters.php is the
 * workshop; this is where the collection actually gets seen, which is what
 * makes building a distinctive one worth doing.
 *
 * A STAKER page, not a public one -- it takes the session like every other
 * platform page. Owners are shown on every Fighter regardless of their profile
 * visibility setting: that flag governs a staker's own holdings, and an
 * assembled Fighter is not a holding. Nobody owns these.
 *
 * Filters live in the query string so a view can be linked to -- "every mythic
 * Fighter" is a URL worth sending someone.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$dhcg_user = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

/* ---- controls ---- */
$dhcg_sort  = isset($_GET['sort'])  ? preg_replace('/[^a-z]/', '', $_GET['sort'])  : 'rarest';
$dhcg_tier  = isset($_GET['tier'])  ? preg_replace('/[^a-z]/', '', $_GET['tier'])  : '';
$dhcg_owner = isset($_GET['owner']) ? (int)$_GET['owner'] : 0;
$dhcg_first = !empty($_GET['first']);
$dhcg_mine  = !empty($_GET['mine']);

$order = 'f.rarity_score DESC, f.created_at DESC';
if ($dhcg_sort === 'newest')  $order = 'f.created_at DESC';
if ($dhcg_sort === 'oldest')  $order = 'f.created_at ASC';
if ($dhcg_sort === 'serial')  $order = 'f.serial ASC';

$where = array('f.disassembled_at IS NULL');
if ($dhcg_owner) $where[] = 'f.user_id = ' . $dhcg_owner;
if ($dhcg_mine && $dhcg_user) $where[] = 'f.user_id = ' . $dhcg_user;

$sql = "SELECT f.*, u.username, u.discord_id, u.avatar
        FROM dhc_fighters f
        INNER JOIN users u ON u.id = f.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order";
$res = $conn->query($sql);

/*
 * Tier and originality are decided in PHP, not SQL: one lives inside a JSON
 * column and the other is a comparison against every other row. At a few
 * hundred Fighters that is cheaper than making the schema carry derived data
 * that would then need keeping in step.
 */
$dhcg_all = array();
$dhcg_owners = array();
$dhcg_tier_counts = array('mythic'=>0,'legendary'=>0,'epic'=>0,'uncommon'=>0,'common'=>0);
$first_by_hash = array();      // hash => the id that holds the original claim

if ($res) {
	while ($row = $res->fetch_assoc()) {
		$row['traits'] = json_decode($row['traits'], true) ?: array();
		$row['display'] = dhcf_display_name($row);

		// every trait's metadata, rarest first -- the detail view and the tier
		// filter both want the same thing
		$parts = array(); $best = 'common'; $rank = array('common'=>0,'uncommon'=>1,'epic'=>2,'legendary'=>3,'mythic'=>4);
		foreach ($row['traits'] as $slot => $slug) {
			$cat  = dhcf_slot_category($slot);
			$info = dhcf_trait_info($cat, $slug);
			if (!$info) continue;
			$parts[] = array(
				'slot' => $slot, 'cat' => $cat, 'slug' => $slug,
				'name' => dhcf_trait_name($cat, $slug),
				'tier' => $info[0], 'worn' => (int)$info[1], 'rate' => (float)$info[2],
				'pts'  => dhcf_trait_points($info[2]),
			);
			if ($rank[$info[0]] > $rank[$best]) $best = $info[0];
		}
		usort($parts, function ($a, $b) { return $a['rate'] <=> $b['rate']; });
		$row['parts'] = $parts;
		$row['best']  = $parts ? $best : 'common';

		$dhcg_owners[(int)$row['user_id']] = $row['username'];
		if (!empty($row['traits_hash']) && !isset($first_by_hash[$row['traits_hash']])) {
			$first_by_hash[$row['traits_hash']] = (int)$row['id'];
		}
		$dhcg_all[] = $row;
	}
}

// originality resolved after the whole set is read: earliest row per hash wins
foreach ($dhcg_all as $i => $row) {
	$dhcg_all[$i]['first'] = (!empty($row['traits_hash'])
	                          && isset($first_by_hash[$row['traits_hash']])
	                          && $first_by_hash[$row['traits_hash']] === (int)$row['id']);
	$dhcg_tier_counts[$dhcg_all[$i]['best']]++;
}

$dhcg_total = count($dhcg_all);
$dhcg_rows  = array_values(array_filter($dhcg_all, function ($r) use ($dhcg_tier, $dhcg_first) {
	if ($dhcg_tier !== '' && $r['best'] !== $dhcg_tier) return false;
	if ($dhcg_first && empty($r['first'])) return false;
	return true;
}));
if ($dhcg_sort === 'traits') {
	usort($dhcg_rows, function ($a, $b) { return count($b['parts']) <=> count($a['parts']); });
}

$dhc_base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $dhc_base = $c; break; }
}

/** A link to this page with one control changed, everything else preserved. */
function dhcg_url($changes) {
	$q = array_merge($_GET, $changes);
	foreach ($q as $k => $v) if ($v === '' || $v === null || $v === 0) unset($q[$k]);
	return 'dhcgallery.php' . ($q ? '?' . http_build_query($q) : '');
}

include 'header.php';
?>

<style>
.dhcg-wrap{padding:14px;max-width:100%;overflow-x:clip;
  color:var(--bone,#e8eaed);font:14px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace}
.dhcg-wrap h1,.dhcg-wrap h2{font-family:"Archivo Black",Impact,sans-serif;font-weight:400}
.dhcg-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 18px;margin:0 0 6px}
.dhcg-head h1{margin:0;font-size:22px}
.dhcg-head .sub{font-size:12px;opacity:.65}
.dhcg-note{font-size:11.5px;opacity:.6;margin:0 0 14px;max-width:80ch;line-height:1.6}
.dhcg-bar{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px;align-items:center}
.dhcg-bar .lbl{font-size:9px;letter-spacing:.14em;text-transform:uppercase;opacity:.5;margin-right:2px}
.dhcg-bar a{font-size:10px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;
  border:1px solid var(--line,#1b3346);color:var(--dim,#7a9eb0);padding:4px 9px;border-radius:999px;
  display:flex;align-items:center;gap:5px}
.dhcg-bar a:hover{border-color:var(--ochre,#00c8a0);color:var(--ochre,#00c8a0)}
.dhcg-bar a.on{border-color:var(--ochre,#00c8a0);color:var(--ink,#07111d);background:var(--ochre,#00c8a0)}
.dhcg-bar a i{width:5px;height:5px;border-radius:50%;background:currentColor;font-style:normal}
.dhcg-bar .sep{width:1px;height:18px;background:var(--line,#1b3346);margin:0 6px}
.dhcg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:12px}
.dhcg-card{border:1px solid var(--line,#1b3346);border-radius:3px;overflow:hidden;background:var(--panel,#0a1929);
  padding:0;cursor:pointer;color:inherit;font:inherit;text-align:left;display:block;width:100%}
.dhcg-card:hover{border-color:var(--ochre,#00c8a0)}
.dhcg-card:focus-visible{outline:2px solid var(--ochre,#00c8a0);outline-offset:1px}
.dhcg-art{position:relative;aspect-ratio:1;background:var(--panel2,#0d1e2e);overflow:hidden}
.dhcg-art img.layer{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.dhcg-meta{padding:8px 9px 3px;display:flex;justify-content:space-between;align-items:baseline;gap:8px}
.dhcg-nm{font-size:11.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dhcg-pts{font-size:10px;opacity:.65;font-variant-numeric:tabular-nums;white-space:nowrap}
/* Everything that is not the artwork lives in this footer row: the owner on
   the left, the tier on the right. Nothing is allowed back over the art. */
.dhcg-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 9px 8px}
.dhcg-owner{display:flex;align-items:center;gap:6px;min-width:0;opacity:.72}
.dhcg-owner img{width:16px;height:16px;border-radius:50%;flex:none}
.dhcg-owner span{font-size:9.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dhcg-flag{display:flex;gap:4px;flex:none}
.dhcg-flag b{font-size:8px;letter-spacing:.1em;padding:2px 6px;border-radius:999px;
  border:1px solid currentColor}
.t-common{color:#7a9eb0}.t-uncommon{color:#00c8a0}.t-epic{color:#8b7bd8}
.t-legendary{color:#f5a623}.t-mythic{color:#ff4f8b}
.dhcg-empty{border:1px dashed var(--line,#1b3346);border-radius:3px;padding:26px;font-size:13px;opacity:.7}

/* detail */
#dhcg-veil{position:fixed;inset:0;z-index:9998;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.86);padding:20px}
#dhcg-veil.on{display:flex}
/* Wider than the metadata needs, because the art is the point: the grid shows
   Fighters at 250px and this is the only place one is seen large. The art
   column takes the larger share and is capped to the panel height so a short
   window scrolls the trait list rather than the character. */
#dhcg-panel{width:min(1180px,100%);max-height:90vh;overflow:auto;background:var(--panel,#0a1929);
  border:1px solid var(--line,#1b3346);border-radius:4px;display:grid;
  grid-template-columns:minmax(0,1.35fr) minmax(0,1fr)}
@media (max-width:760px){#dhcg-panel{grid-template-columns:1fr}}
#dhcg-panel .big{position:relative;aspect-ratio:1;max-height:90vh;background:var(--panel2,#0d1e2e)}
#dhcg-panel .big img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
#dhcg-info{padding:18px 20px}
#dhcg-info h2{margin:0 0 2px;font-size:20px}
#dhcg-info .by{font-size:11px;opacity:.65;display:flex;align-items:center;gap:6px;margin:0 0 14px}
#dhcg-info .by img{width:20px;height:20px;border-radius:50%}
#dhcg-stat{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
#dhcg-stat div{border:1px solid var(--line,#1b3346);border-radius:3px;padding:6px 10px}
#dhcg-stat b{display:block;font-size:15px;font-variant-numeric:tabular-nums}
#dhcg-stat span{font-size:9px;letter-spacing:.12em;text-transform:uppercase;opacity:.55}
#dhcg-traits{list-style:none;margin:0;padding:0;font-size:11.5px}
#dhcg-traits li{display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:baseline;
  padding:6px 0;border-top:1px solid var(--line,#1b3346)}
#dhcg-traits .sl{font-size:9px;letter-spacing:.1em;text-transform:uppercase;opacity:.45;display:block}
#dhcg-traits .tr{font-size:9px;letter-spacing:.1em;text-transform:uppercase}
#dhcg-traits .rt{font-size:10px;opacity:.6;font-variant-numeric:tabular-nums}
#dhcg-close{position:absolute;right:14px;top:12px;background:none;border:1px solid var(--line,#1b3346);
  color:var(--dim,#7a9eb0);font:inherit;font-size:10px;letter-spacing:.1em;text-transform:uppercase;
  padding:5px 10px;border-radius:2px;cursor:pointer}
#dhcg-close:hover{border-color:var(--ochre,#00c8a0);color:var(--ochre,#00c8a0)}
</style>

<div class="dhcg-wrap">

  <div class="dhcg-head">
    <h1>The Collection</h1>
    <span class="sub">Every Fighter assembled on Skulliance &middot; art by Maxingo</span>
  </div>
  <p class="dhcg-note">
    <?php echo number_format($dhcg_total); ?> assembled so far.
    These are platform features, <b>not NFTs</b> &mdash; they cannot be minted and nobody owns the
    artwork. Build your own in <a href="dhcfighters.php" style="color:var(--ochre,#00c8a0)">DHC Fighters</a>.
  </p>

  <?php
    $tiers = array('mythic'=>'Mythic','legendary'=>'Legendary','epic'=>'Epic',
                   'uncommon'=>'Uncommon','common'=>'Common');
    $sorts = array('rarest'=>'Rarest','newest'=>'Newest','oldest'=>'Oldest',
                   'serial'=>'By number','traits'=>'Most traits');
  ?>
  <div class="dhcg-bar">
    <span class="lbl">Sort</span>
    <?php foreach ($sorts as $k => $label): ?>
      <a class="<?php echo $dhcg_sort === $k ? 'on' : ''; ?>"
         href="<?php echo htmlspecialchars(dhcg_url(array('sort' => $k))); ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>

    <span class="sep"></span>
    <span class="lbl">Rarest trait</span>
    <a class="<?php echo $dhcg_tier === '' ? 'on' : ''; ?>"
       href="<?php echo htmlspecialchars(dhcg_url(array('tier' => ''))); ?>">Any</a>
    <?php foreach ($tiers as $k => $label): if (!$dhcg_tier_counts[$k]) continue; ?>
      <a class="t-<?php echo $k; ?> <?php echo $dhcg_tier === $k ? 'on' : ''; ?>"
         href="<?php echo htmlspecialchars(dhcg_url(array('tier' => $k))); ?>"><i></i><?php
         echo $label; ?> <?php echo $dhcg_tier_counts[$k]; ?></a>
    <?php endforeach; ?>

    <span class="sep"></span>
    <a class="<?php echo $dhcg_first ? 'on' : ''; ?>"
       href="<?php echo htmlspecialchars(dhcg_url(array('first' => $dhcg_first ? '' : 1))); ?>">Originals</a>
    <?php if ($dhcg_user): ?>
      <a class="<?php echo $dhcg_mine ? 'on' : ''; ?>"
         href="<?php echo htmlspecialchars(dhcg_url(array('mine' => $dhcg_mine ? '' : 1, 'owner' => ''))); ?>">Mine</a>
    <?php endif; ?>
    <?php if ($dhcg_owner && isset($dhcg_owners[$dhcg_owner])): ?>
      <a class="on" href="<?php echo htmlspecialchars(dhcg_url(array('owner' => ''))); ?>">
        <?php echo htmlspecialchars($dhcg_owners[$dhcg_owner]); ?> &times;</a>
    <?php endif; ?>
  </div>

  <?php if (!$dhcg_rows): ?>
    <div class="dhcg-empty">
      <?php echo $dhcg_total ? 'No Fighters match those filters.' : 'No Fighters have been assembled yet. Be the first.'; ?>
    </div>
  <?php else: ?>
  <div class="dhcg-grid">
    <?php foreach ($dhcg_rows as $f):
      $av = (!empty($f['discord_id']) && !empty($f['avatar']))
          ? 'https://cdn.discordapp.com/avatars/' . $f['discord_id'] . '/' . $f['avatar'] . '.jpg'
          : 'icons/skulliance.png';
      $card = array(
        'name'    => $f['display'],
        'serial'  => (int)$f['serial'],
        'owner'   => $f['username'],
        'ownerId' => (int)$f['user_id'],
        'avatar'  => $av,
        'score'   => (int)$f['rarity_score'],
        'first'   => (bool)$f['first'],
        'created' => $f['created_at'],
        'parts'   => $f['parts'],
        'layers'  => array(),
      );
      // same draw order everything else uses, so a card cannot disagree with
      // the canvas or the Discord render
      foreach (dhcf_layer_order($f['traits']) as $slot) {
        if (empty($f['traits'][$slot])) continue;
        $slug = $f['traits'][$slot];
        // Category and slug rather than a path: the card wants 250px and the
        // detail view wants the 1000px master, and the size is the only
        // difference between them.
        $card['layers'][] = array(
          'c' => dhcf_slot_category($slot), 's' => $slug,
          'n' => isset(DHCF_NUDGE[$slug]) ? (float)DHCF_NUDGE[$slug] / 10 : 0,
        );
      }
    ?>
    <button type="button" class="dhcg-card" data-f="<?php echo htmlspecialchars(json_encode($card), ENT_QUOTES); ?>">
      <div class="dhcg-art">
        <?php foreach ($card['layers'] as $l): ?>
          <img class="layer" loading="lazy" alt=""
               <?php if ($l['n']): ?>style="transform:translateY(<?php echo $l['n']; ?>%)"<?php endif; ?>
               src="<?php echo htmlspecialchars($dhc_base . '/250/' . $l['c'] . '/' . $l['s'] . '.png'); ?>">
        <?php endforeach; ?>
      </div>
      <div class="dhcg-meta">
        <span class="dhcg-nm"><?php echo htmlspecialchars($f['display']); ?></span>
        <span class="dhcg-pts"><?php echo number_format((int)$f['rarity_score']); ?></span>
      </div>
      <?php /* Below the art, never over it -- the whole point of the grid is
               seeing Maxingo's work, and a badge sat on the character was
               covering the thing it was captioning. */ ?>
      <div class="dhcg-foot">
        <span class="dhcg-owner">
          <img loading="lazy" alt="" src="<?php echo htmlspecialchars($av); ?>">
          <span><?php echo htmlspecialchars($f['username']); ?></span>
        </span>
        <span class="dhcg-flag">
          <b class="t-<?php echo $f['best']; ?>"><?php echo strtoupper($f['best']); ?></b>
          <?php if ($f['first']): ?><b class="t-legendary">FIRST</b><?php endif; ?>
        </span>
      </div>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div id="dhcg-veil" role="dialog" aria-modal="true" aria-labelledby="dhcg-name">
  <div id="dhcg-panel">
    <div class="big" id="dhcg-big"></div>
    <div id="dhcg-info">
      <button type="button" id="dhcg-close">Close</button>
      <h2 id="dhcg-name"></h2>
      <p class="by" id="dhcg-by"></p>
      <div id="dhcg-stat"></div>
      <ul id="dhcg-traits"></ul>
    </div>
  </div>
</div>

<script>
(function () {
  var veil = document.getElementById('dhcg-veil');
  var big  = document.getElementById('dhcg-big');
  var BASE = <?php echo json_encode($dhc_base); ?>;

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function open(f) {
    // The same layer list the card used, so this is the card at a larger size
    // rather than a second opinion about draw order -- but pointed at the
    // 1000px masters. This is the only place a Fighter is shown big enough for
    // the detail in Maxingo's art to be worth the bytes.
    big.innerHTML = f.layers.map(function (l) {
      return '<img alt="" src="' + esc(BASE + '/1000/' + l.c + '/' + l.s + '.png') + '"' +
             (l.n ? ' style="transform:translateY(' + l.n + '%)"' : '') + '>';
    }).join('');

    document.getElementById('dhcg-name').textContent = f.name;
    document.getElementById('dhcg-by').innerHTML =
      '<img alt="" src="' + esc(f.avatar) + '"> assembled by ' + esc(f.owner) +
      ' · <a href="dhcgallery.php?owner=' + f.ownerId + '" style="color:var(--ochre,#00c8a0)">see their Fighters</a>';

    var made = (f.created || '').replace(' ', ' · ').slice(0, 16);
    document.getElementById('dhcg-stat').innerHTML =
      '<div><b>' + f.score.toLocaleString() + '</b><span>Rarity score</span></div>' +
      '<div><b>' + f.parts.length + '</b><span>Traits</span></div>' +
      '<div><b>DHC2F' + f.serial + '</b><span>Number</span></div>' +
      (f.first ? '<div><b style="color:#f5a623">First</b><span>To build this</span></div>' : '') +
      '<div><b style="font-size:11px">' + esc(made) + '</b><span>Assembled</span></div>';

    document.getElementById('dhcg-traits').innerHTML = f.parts.map(function (p) {
      var worn = p.worn > 0 ? p.worn + ' of 226 wear this' : 'in no minted Fighter';
      return '<li><span><span class="sl">' + esc(p.slot) + '</span>' + esc(p.name) +
             '<span class="sl" style="opacity:.4">' + worn + '</span></span>' +
             '<span class="tr t-' + p.tier + '">' + p.tier + '</span>' +
             '<span class="rt">' + p.pts + ' pts</span></li>';
    }).join('');

    veil.classList.add('on');
    document.getElementById('dhcg-close').focus();
  }

  function close() { veil.classList.remove('on'); }
  document.getElementById('dhcg-close').addEventListener('click', close);
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  document.querySelectorAll('.dhcg-card').forEach(function (c) {
    c.addEventListener('click', function () {
      try { open(JSON.parse(c.dataset.f)); } catch (err) {}
    });
  });
})();
</script>

<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>

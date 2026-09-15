<?php
/**
 * DHC FIGHTERS
 *
 * The trait game. Earn traits by playing everything else on the platform,
 * assemble them into Fighters, save them, compete on rarity.
 *
 * Unlike dhcsandbox.php -- which is public and deliberately includes nothing --
 * this is a platform page: it needs the session to know whose traits to show,
 * so it takes db.php, skulliance.php and header.php like every other game.
 *
 * The assembler is the same dhc-assembler.php the sandbox uses, handed an
 * ownership filter instead of the full trait list.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$dhcf_user = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

/*
 * EDIT MODE. ?edit=<id> re-opens a saved Fighter for changes instead of
 * building a new one, so tweaking a trait no longer costs the Fighter its
 * number and name -- disassembly is for dumping a character, not editing one.
 *
 * It is a page load rather than a client-side toggle because the trait picker
 * is filtered server-side: the Fighter's own traits are committed to it, and
 * only dhcf_available()'s $ignore_id makes them selectable again. Doing this
 * in JS would mean a second, parallel idea of what is available -- the exact
 * split that let a saved Fighter's traits look placeable once already.
 */
$dhcf_edit = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$dhcf_editing = null;
if ($dhcf_edit > 0 && $dhcf_user) {
	$r = $conn->query(sprintf(
		"SELECT id, serial, name, traits, rarity_score FROM dhc_fighters
		 WHERE id = %d AND user_id = %d AND disassembled_at IS NULL LIMIT 1",
		$dhcf_edit, $dhcf_user));
	if ($r && $r->num_rows) {
		$dhcf_editing = $r->fetch_assoc();
		$dhcf_editing['traits']  = json_decode($dhcf_editing['traits'], true) ?: array();
		$dhcf_editing['display'] = dhcf_display_name($dhcf_editing);
	} else {
		$dhcf_edit = 0;   // gone, disassembled, or not theirs -- fall back to building
	}
}

// With a Fighter open for editing, its own traits count as available to it.
$dhcf_avail    = $dhcf_user ? dhcf_available($conn, $dhcf_user, $dhcf_edit) : array();
$dhcf_roster   = $dhcf_user ? dhcf_fighters($conn, $dhcf_user)  : array();
$dhcf_next     = dhcf_default_name(dhcf_next_serial($conn));
$dhcf_lb_ath   = dhcf_leaderboard($conn, 'ath', 10);
$dhcf_lb_month = dhcf_leaderboard($conn, 'monthly', 10);

// totals for the header strip
$dhcf_owned_n = 0; $dhcf_free_n = 0;
foreach ($dhcf_avail as $cat => $traits) {
	foreach ($traits as $slug => $t) { $dhcf_owned_n += $t['copies']; $dhcf_free_n += $t['free']; }
}
$dhcf_best = 0;
foreach ($dhcf_roster as $f) if ((int)$f['rarity_score'] > $dhcf_best) $dhcf_best = (int)$f['rarity_score'];

/*
 * The collection, platform-wide. dhcgallery.php is where assembled Fighters
 * actually get seen, which is most of the reason to build a distinctive one --
 * but nothing on this page pointed at it. A link carrying a real count is a
 * reason to follow it; "Collection" on its own is not.
 */
$dhcf_coll_n = 0; $dhcf_coll_u = 0;
$dhcf_cres = $conn->query(
	"SELECT COUNT(*) n, COUNT(DISTINCT user_id) u FROM dhc_fighters WHERE disassembled_at IS NULL");
if ($dhcf_cres && ($dhcf_crow = $dhcf_cres->fetch_assoc())) {
	$dhcf_coll_n = (int)$dhcf_crow['n'];
	$dhcf_coll_u = (int)$dhcf_crow['u'];
}

/*
 * PER-CATEGORY PROGRESS, for the games column.
 *
 * "held" counts DISTINCT traits, not copies: the column answers "how much of
 * this category have I seen", which is what tells a player which game to go
 * play. Copies matter for building a second Fighter, and the stats strip above
 * already carries that number.
 */
$dhcf_cat_total = array();
foreach (dhcf_rarity() as $cat => $traits) $dhcf_cat_total[$cat] = count($traits);
$dhcf_cat_held = array();
foreach ($dhcf_avail as $cat => $traits) $dhcf_cat_held[$cat] = count($traits);
$dhcf_distinct = array_sum($dhcf_cat_held);
// What is still claimable today, so the table can say so before a player
// grinds a game that has nothing left to give them.
$dhcf_today = $dhcf_user ? dhcf_drops_today($conn, $dhcf_user) : array();
$dhcf_all      = array_sum($dhcf_cat_total);

// Usernames for the leaderboards, resolved in one pass rather than per row.
$dhcf_names = array();
$dhcf_ids = array();
foreach (array_merge($dhcf_lb_ath, $dhcf_lb_month) as $r) $dhcf_ids[(int)$r['user_id']] = true;
if ($dhcf_ids) {
	$in = implode(',', array_map('intval', array_keys($dhcf_ids)));
	$res = $conn->query("SELECT id, username FROM users WHERE id IN ($in)");
	if ($res) while ($row = $res->fetch_assoc()) $dhcf_names[(int)$row['id']] = $row['username'];
}
function dhcf_user_label($id) {
	global $dhcf_names;
	return isset($dhcf_names[$id]) ? htmlspecialchars($dhcf_names[$id]) : 'player ' . (int)$id;
}

include 'header.php';

// Hand the assembler the player's holdings. Everything the picker offers is
// something they actually hold a free copy of.
$dhca_mode    = 'fighters';
$dhca_owned   = $dhcf_avail;
$dhca_preload = $dhcf_editing ? $dhcf_editing['traits'] : null;
?>

<style>
/* Page chrome only -- the assembler brings its own styles. */
/* No max-width of its own: the platform's .container already caps at 2000px
   and centres, so clamping again just made this page narrower than the header
   above it. */
.dhcf-wrap{padding:14px;max-width:100%;overflow-x:clip}
/* One row across the full width. The intro takes what it needs and the counts
   sit hard right, so the band is used rather than leaving two thirds empty. */
.dhcf-masthead{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;
  gap:18px 32px;margin:0 0 16px}
.dhcf-intro{flex:1 1 420px;min-width:0}
.dhcf-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:12px;margin:0 0 4px}
.dhcf-head h1{margin:0;font-size:22px;letter-spacing:.02em}
.dhcf-head .sub{font-size:12px;opacity:.7}
.dhcf-note{font-size:11.5px;opacity:.65;line-height:1.6;margin:0;max-width:82ch}
.dhcf-note b{opacity:.95}
.dhcf-note a{color:var(--ochre)}
/* flex:0 1 auto + min-width:0, NOT 0 0 auto. A flex item that cannot shrink
   keeps its single-line max-content width, so wrap never engages and five
   tiles push the page sideways on a phone. Allowing it to shrink is what lets
   its own flex-wrap do its job. */
.dhcf-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0;flex:0 1 auto;min-width:0}
/* Sized by content rather than a fixed floor, so tiles pack tighter as the
   screen narrows instead of forcing a scroll. */
.dhcf-stat{border:1px solid var(--line);border-radius:3px;padding:7px 12px;
  min-width:0;flex:0 1 auto}
.dhcf-stat b,.dhcf-stat span{white-space:nowrap}
@media (max-width:520px){
  /* Two clean rows on a phone rather than a ragged three. */
  .dhcf-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%}
  .dhcf-stat{padding:6px 8px}
  .dhcf-stat b{font-size:15px}
}
/* The only tile in the row that is a destination rather than a statistic, so
   it is the only one wearing the accent -- that is what separates a link from
   the counts sitting beside it. */
a.dhcf-stat{text-decoration:none;color:inherit;border-color:var(--ochre)}
a.dhcf-stat:hover{background:rgba(0,200,160,.09)}
a.dhcf-stat b{color:var(--ochre)}
a.dhcf-stat span{opacity:.85}
/* Edit mode: the bar is doing something different from a normal save, and it
   should look like it before the player clicks. */
.dhcf-save.editing{border-color:var(--ochre)}
.dhcf-editing{font-size:12px;opacity:.8}
.dhcf-editing b{opacity:1}
.dhcf-cancel{font-size:11px;color:var(--dim);text-decoration:none;border-bottom:1px solid transparent}
.dhcf-cancel:hover{color:var(--ochre);border-bottom-color:currentColor}
.dhcf-card .acts .dhcf-edit{text-decoration:none;display:inline-flex;align-items:center}
.dhcf-stat b{display:block;font-size:17px;font-variant-numeric:tabular-nums}
.dhcf-stat span{font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;opacity:.6}
/* Reference block, below everything you actually operate. It answers "what
   should I play next", which is worth having on the page but never worth
   pushing the assembler down the screen for. */
.dhcf-games{border:1px solid var(--line);border-radius:3px;overflow:hidden;margin-top:14px}
.dhcf-games ul{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
.dhcf-games li+li{border-top:0}
.dhcf-games h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  opacity:.7;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:8px}
.dhcf-games h2 span{opacity:.6;letter-spacing:0;font-variant-numeric:tabular-nums}
.dhcf-games ul{list-style:none;margin:0;padding:4px 0}
.dhcf-games li{display:grid;grid-template-columns:1fr auto;gap:2px 10px;padding:7px 12px;position:relative}
.dhcf-games li{border-top:1px solid var(--line)}
.dhcf-games .g{font-size:12px;color:inherit;text-decoration:none}
.dhcf-games .g:hover{color:var(--ochre);text-decoration:underline}
.dhcf-games li:hover{background:rgba(0,200,160,.05)}
.dhcf-games .c{grid-column:1;font-size:9.5px;opacity:.55;text-transform:uppercase;letter-spacing:.08em}
.dhcf-games .c em{font-style:normal;opacity:.75;text-transform:none;letter-spacing:0;display:block}
.dhcf-games .n{grid-row:1/3;align-self:center;font-size:14px;font-variant-numeric:tabular-nums}
.dhcf-games .n i{font-style:normal;font-size:10px;opacity:.45}
.dhcf-games .left{grid-column:1;font-size:9px;letter-spacing:.06em;text-transform:uppercase;
  color:var(--teal);opacity:.85}
.dhcf-games .left.out{color:#ff5c5c;opacity:.9}
.dhcf-games .bar{grid-column:1/-1;height:3px;background:var(--line);border-radius:2px;overflow:hidden}
.dhcf-games .bar i{display:block;height:100%;background:var(--ochre)}
.dhcf-games li.done .bar i{background:var(--teal)}
.dhcf-games li.done .n{color:var(--teal)}
.dhcf-panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin-top:18px}
.dhcf-panel{border:1px solid var(--line);border-radius:3px;overflow:hidden}
.dhcf-panel h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  opacity:.65;border-bottom:1px solid var(--line);display:flex;flex-wrap:wrap;
  justify-content:space-between;gap:2px 10px}
.dhcf-panel h2 a{color:var(--ochre);text-decoration:none;opacity:.9;letter-spacing:.08em;white-space:nowrap}
.dhcf-panel h2 a:hover{text-decoration:underline}
.dhcf-panel .body{padding:10px 12px}
.dhcf-roster{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.dhcf-card{border:1px solid var(--line);border-radius:3px;overflow:hidden;position:relative}
.dhcf-card .art{position:relative;aspect-ratio:1;background:var(--panel2);overflow:hidden;
  display:block;width:100%;padding:0;border:0;cursor:pointer}
.dhcf-card .art:hover{outline:1px solid var(--ochre);outline-offset:-1px}
.dhcf-card .art:focus-visible{outline:2px solid var(--ochre);outline-offset:-2px}
.dhcf-card .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.dhcf-card .meta{padding:6px 8px}
.dhcf-card .nm{font-size:11.5px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dhcf-card .sc{font-size:10px;opacity:.6;font-variant-numeric:tabular-nums}
.dhcf-card .firstb{color:var(--ochre);opacity:1;font-size:8.5px;letter-spacing:.12em;
  border:1px solid var(--ochre);border-radius:999px;padding:1px 5px;margin-left:4px}
.dhcf-card .acts{display:flex;gap:5px;padding:0 8px 8px}
.dhcf-card .acts button{flex:1;font:inherit;font-size:9px;letter-spacing:.08em;text-transform:uppercase;
  padding:4px;cursor:pointer;background:none;border:1px solid var(--line);border-radius:2px;color:inherit}
.dhcf-card .acts button:hover{border-color:var(--ochre);color:var(--ochre)}
.dhcf-lb{width:100%;border-collapse:collapse;font-size:12px}
.dhcf-lb th{text-align:left;font-size:9px;letter-spacing:.12em;text-transform:uppercase;opacity:.55;padding:4px 6px}
.dhcf-lb td{padding:5px 6px;border-top:1px solid var(--line);font-variant-numeric:tabular-nums}
.dhcf-lb .r{width:26px;opacity:.6}
.dhcf-save{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0 0}
.dhcf-save input{flex:1;min-width:170px;font:inherit;font-size:12px;padding:7px 9px;border-radius:2px;
  background:var(--ink);border:1px solid var(--line);color:inherit}
.dhcf-save button{font:inherit;font-size:11px;letter-spacing:.08em;text-transform:uppercase;padding:8px 16px;
  cursor:pointer;border-radius:2px;background:var(--ochre);border:1px solid var(--ochre);color:var(--ink)}
.dhcf-save button:hover{filter:brightness(1.12)}
.dhcf-save button:disabled{background:transparent;border-color:var(--line);color:var(--dim);cursor:not-allowed;filter:none}
.dhcf-say{font-size:11.5px;min-height:16px;opacity:.85}
</style>

<div class="dhcf-wrap">

  <div class="dhcf-masthead">
    <div class="dhcf-intro">
      <div class="dhcf-head">
        <h1>DHC Fighters</h1>
        <span class="sub"><a href="https://www.wayup.io/collection/b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5"
           target="_blank" rel="noopener">Digital Hell Citizens 2: Fighters</a> &middot; art by Maxingo</span>
      </div>
      <?php /* ONE paragraph. The subtitle above already says "art by Maxingo", so a
               second sentence establishing that was repeating itself, and the rights
               notice does not need its own block to be read -- it needs to be short,
               unambiguous, and to point somewhere. */ ?>
      <p class="dhcf-note">
        Earn traits by playing across the platform, then assemble and save Fighters &mdash; your
        best fighter's rarity score sets your place on the leaderboard, and everything you save
        joins the <a href="dhcgallery.php">Fighter Collection</a> for every staker to browse.
        Rarity comes from the original NFT collection: how many of the 226 minted Fighters
        actually wear that trait. Maxingo shared
        the art so this could be built, and what you assemble here is <b>not an NFT</b> &mdash; it
        cannot be minted, and earning a trait gives you no ownership of the artwork. The genuine,
        ownable Fighters are from the official NFT collection.
      </p>
    </div>

    <div class="dhcf-stats">
      <div class="dhcf-stat"><b><?php echo (int)$dhcf_owned_n; ?></b><span>Traits held</span></div>
      <div class="dhcf-stat"><b><?php echo (int)$dhcf_free_n; ?></b><span>Unused</span></div>
      <div class="dhcf-stat"><b><?php echo count($dhcf_roster); ?></b><span>Fighters</span></div>
      <div class="dhcf-stat"><b><?php echo number_format($dhcf_best); ?></b><span>Best score</span></div>
      <div class="dhcf-stat"><b><?php echo htmlspecialchars($dhcf_next); ?></b><span>Next number</span></div>
      <a class="dhcf-stat" href="dhcgallery.php"
         title="Every Fighter assembled on the platform &mdash; <?php
           echo number_format($dhcf_coll_n) . ' built by ' . number_format($dhcf_coll_u)
              . ' staker' . ($dhcf_coll_u === 1 ? '' : 's'); ?>"><b><?php echo number_format($dhcf_coll_n); ?></b><span>Collection &rsaquo;</span></a>
    </div>
  </div>

  <?php include __DIR__ . '/dhc-assembler.php'; ?>

  <div class="dhcf-save<?php echo $dhcf_editing ? ' editing' : ''; ?>">
    <?php if ($dhcf_editing): ?>
      <?php /* No name field: an edit keeps the Fighter's name, which is most of
               the reason to edit rather than rebuild. Rename is its own button
               on the card. */ ?>
      <span class="dhcf-editing">Editing <b><?php echo htmlspecialchars($dhcf_editing['display']); ?></b>
        &middot; keeps its number and name</span>
      <button type="button" id="dhcfSave" data-edit="<?php echo (int)$dhcf_editing['id']; ?>">Update Fighter</button>
      <a class="dhcf-cancel" href="dhcfighters.php">Cancel</a>
    <?php else: ?>
      <input type="text" id="dhcfName" maxlength="48"
             placeholder="Name this Fighter (optional &mdash; defaults to <?php echo htmlspecialchars($dhcf_next); ?>)">
      <button type="button" id="dhcfSave">Save Fighter</button>
    <?php endif; ?>
    <span class="dhcf-say" id="dhcfSay"></span>
  </div>

  <div class="dhcf-panels">

    <div class="dhcf-panel">
      <h2>Your Fighters<a href="dhcgallery.php?mine=1">In the collection &rsaquo;</a></h2>
      <div class="body">
        <?php if (!$dhcf_roster): ?>
          <p style="font-size:12px;opacity:.6;margin:2px 0">Nothing saved yet.</p>
        <?php else: ?>
        <div class="dhcf-roster">
          <?php foreach ($dhcf_roster as $f): ?>
            <div class="dhcf-card" data-id="<?php echo (int)$f['id']; ?>"
                 data-traits="<?php echo htmlspecialchars(json_encode($f['traits']), ENT_QUOTES); ?>">
              <?php /* A button, not a div with a click handler: this is a real
                       control and should be reachable by keyboard like one. */ ?>
              <button type="button" class="art" title="View <?php echo htmlspecialchars($f['display']); ?> on the canvas">
                <?php
                  /* dhcf_layer_order(), not dhcf_slots(): the raw slot order
                     ignores every exception, so a Fighter wearing Code Sea
                     Predator or Xlon's Black Fire rendered one way here, another
                     on the canvas, and a third in Discord. One function decides
                     draw order everywhere. */
                  foreach (dhcf_layers($f['traits'], __DIR__ . '/' . $dhc_base) as $L) {
                      $st = trim(($L['nudge'] ? 'transform:translateY(' . $L['nudge'] . '%);' : '')
                                 . dhcf_layer_clip_css($L['clip']));
                      echo '<img loading="lazy" alt=""'
                         . ($st ? ' style="' . $st . '"' : '')
                         . ' src="' . htmlspecialchars($dhc_base . '/250/' . $L['cat'] . '/' . $L['slug'] . '.png') . '">';
                  }
                ?>
              </button>
              <div class="meta">
                <span class="nm"><?php echo htmlspecialchars($f['display']); ?></span>
                <span class="sc"><?php echo number_format((int)$f['rarity_score']); ?> pts<?php
                  /* Only shown while they still hold the claim -- recomputed
                     rather than stored, so it reflects the table as it is now. */
                  if (!empty($f['traits_hash'])
                      && dhcf_is_first_build($conn, $dhcf_user, $f['traits_hash'], (int)$f['id'])) {
                      echo ' <b class="firstb" title="No one else has built this configuration">FIRST</b>';
                  }
                ?></span>
              </div>
              <div class="acts">
                <a class="dhcf-edit" href="dhcfighters.php?edit=<?php echo (int)$f['id']; ?>">Edit</a>
                <button type="button" class="dhcf-rename">Rename</button>
                <button type="button" class="dhcf-scrap">Disassemble</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="dhcf-panel">
      <h2>All-time &mdash; best Fighter</h2>
      <div class="body">
        <?php echo dhcf_board_html($dhcf_lb_ath); ?>
      </div>
    </div>

    <div class="dhcf-panel">
      <h2>This month</h2>
      <div class="body">
        <?php echo dhcf_board_html($dhcf_lb_month); ?>
      </div>
    </div>

  </div>

  <?php
      /*
       * WHERE TRAITS COME FROM -- shown always, not just to an empty account.
       * It is the answer to "what should I play next", which is a question a
       * player with 40 traits asks more often than one with none, and the
       * counts turn it from a legend into a progress list.
       */
    ?>
  <div class="dhcf-games">
      <h2>Where traits drop
        <span><?php echo (int)$dhcf_distinct; ?>/<?php echo (int)$dhcf_all; ?></span>
      </h2>
      <ul>
      <?php foreach ($GLOBALS['DHCF_GAMES'] as $gkey => $g):
          $cat   = $g['category'];
          $wild  = ($cat === 'wildcard');
          $held  = $wild ? $dhcf_distinct : (isset($dhcf_cat_held[$cat])  ? $dhcf_cat_held[$cat]  : 0);
          $tot   = $wild ? $dhcf_all      : (isset($dhcf_cat_total[$cat]) ? $dhcf_cat_total[$cat] : 0);
          $pct   = $tot ? round($held / $tot * 100) : 0;
          $done  = ($tot && $held >= $tot);
        ?>
        <li<?php echo $done ? ' class="done"' : ''; ?>>
          <?php /* One click to the game that pays this category -- relative, because
                   the login cookie is host-only and an absolute link would drop the
                   session for anyone on the other hostname. */ ?>
          <a class="g" href="<?php echo htmlspecialchars($g['url']); ?>"><?php
             echo htmlspecialchars($g['label']); ?></a>
          <span class="c"><?php echo $wild ? 'any trait' : htmlspecialchars($cat); ?>
            <em><?php echo htmlspecialchars($g['trigger']); ?></em></span>
          <span class="n"><?php echo (int)$held; ?><i>/<?php echo (int)$tot; ?></i></span>
          <?php
            /* Drops left today. A trait hunter grinding a game that has nothing
               left to give is the frustration this whole table exists to
               prevent, so it says so rather than letting them find out by
               getting nothing. Counted off the same CURDATE() boundary the
               claim endpoint enforces. */
            $cap  = dhcf_cap($gkey);
            $used = isset($dhcf_today[$gkey]) ? $dhcf_today[$gkey] : 0;
            $left = max(0, $cap - $used);
            // A source whose real limit is not a daily count says so instead --
            // quoting "3 left today" for the 7-day streak promises three today.
            $note = isset($g['limit_note']) ? $g['limit_note'] : '';
          ?>
          <span class="left<?php echo ($note || $left) ? '' : ' out'; ?>"><?php
            if ($note)      echo htmlspecialchars($note);
            elseif ($left)  echo $left . ' of ' . $cap . ' left today';
            else            echo 'none left today';
          ?></span>
          <span class="bar"><i style="width:<?php echo (int)$pct; ?>%"></i></span>
        </li>
      <?php endforeach; ?>
      </ul>
    </div>
</div>

<?php
function dhcf_board_html($rows) {
	if (!$rows) return '<p style="font-size:12px;opacity:.6;margin:2px 0">No Fighters saved yet.</p>';
	$h = '<table class="dhcf-lb"><tr><th class="r">#</th><th>Player</th><th>Best</th><th>Fighters</th></tr>';
	$i = 0;
	foreach ($rows as $r) {
		$i++;
		$h .= '<tr><td class="r">' . $i . '</td><td>' . dhcf_user_label((int)$r['user_id']) . '</td>'
		    . '<td>' . number_format((int)$r['best_score']) . '</td>'
		    . '<td>' . (int)$r['fighters'] . '</td></tr>';
	}
	return $h . '</table>';
}
?>

<script>
(function () {
  var say = document.getElementById('dhcfSay');
  function msg(t, good) { say.textContent = t; say.style.color = good ? 'var(--ochre)' : '#ff5c5c'; }

  /* The assembler exposes its current selection on window.DHC_SELECTION -- the
     save button reads it rather than the DOM, so what gets stored is exactly
     what the renderer was told to draw. */
  /* Mirrors DHCF_REQUIRED server-side. The server is the authority; this exists
     so the button can say what is missing instead of letting you click into a
     rejection. */
  var REQUIRED = <?php echo json_encode(DHCF_REQUIRED); ?>;
  var saveBtn  = document.getElementById('dhcfSave');

  function missingRequired() {
    var sel = (window.DHC_SELECTION && window.DHC_SELECTION()) || {};
    return REQUIRED.filter(function (slot) { return !sel[slot]; });
  }

  /* Kept in step with the canvas: every paint re-checks, so the button state
     always describes the Fighter actually on screen. */
  function syncSave() {
    var missing = missingRequired();
    // Unaffordable traits block a save too, not just missing slots. Randomize
    // could otherwise leave a build on the canvas that looked saveable and was
    // refused by the server -- the button has to reflect what will happen.
    var issues = (window.DHC_SELECTION_ISSUES && window.DHC_SELECTION_ISSUES()) || [];

    saveBtn.disabled = missing.length > 0 || issues.length > 0;

    var note = '';
    if (missing.length) note = 'Needs a ' + missing.join(', ') + ' to save.';
    else if (issues.length) note = issues[0].why;

    saveBtn.title = note || 'Save this Fighter';
    if (note) {
      say.textContent = note;
      say.style.color = '';
      say.style.opacity = '.6';
    } else if (say.style.opacity === '.6') {
      say.textContent = ''; say.style.opacity = '';
    }
  }
  syncSave();
  document.addEventListener('click', function () { setTimeout(syncSave, 0); });

  saveBtn.addEventListener('click', function () {
    var sel = (window.DHC_SELECTION && window.DHC_SELECTION()) || {};
    if (!Object.keys(sel).length) { msg('Pick some traits first.', false); return; }
    var missing = missingRequired();
    if (missing.length) { msg('A Fighter needs a ' + missing.join(', ') + '.', false); return; }
    var issues = (window.DHC_SELECTION_ISSUES && window.DHC_SELECTION_ISSUES()) || [];
    if (issues.length) { msg(issues[0].why, false); return; }
    var btn = saveBtn; btn.disabled = true;

    /* Editing an existing Fighter or creating a new one -- same canvas, same
       validation, different endpoint. The id on the button is the whole
       difference, and it is only there in edit mode. */
    var editId = saveBtn.getAttribute('data-edit');
    var url    = editId ? 'ajax/dhc-update-fighter.php' : 'ajax/dhc-save-fighter.php';
    var nameEl = document.getElementById('dhcfName');
    var body   = 'traits=' + encodeURIComponent(JSON.stringify(sel)) +
                 (editId ? '&id=' + encodeURIComponent(editId)
                         : '&name=' + encodeURIComponent(nameEl ? nameEl.value : ''));
    msg(editId ? 'Updating...' : 'Saving...', true);

    fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body
    }).then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false;
        if (d.ok) {
          // The originality bonus is worth naming, or it just looks like the
          // score came out higher than the traits explain.
          var line;
          if (editId) {
            line = d.display + ' updated — ' + d.score + ' pts';
            if (d.first) line += ' (first to build this)';
          } else {
            line = 'Saved as ' + d.display + ' — ' + d.score + ' pts';
            if (d.bonus > 0) line += ' (first to build this: +' + d.bonus + ')';
          }
          msg(line, true);
          // Back to the build page after an edit, so the roster shows the
          // result rather than leaving ?edit= in the URL.
          setTimeout(function () {
            // Leaving ?edit= in the URL would re-open the editor on reload.
            if (editId) location.href = 'dhcfighters.php';
            else location.reload();
          }, 900);
        }
        else { msg(d.message || 'Could not save.', false); syncSave(); }
      })
      .catch(function () { btn.disabled = false; msg('Network error.', false); });
  });

  /* Click a saved Fighter to put it on the canvas. This is still VIEWING --
     its traits are committed to it, so the picker greys them out. Changing it
     is the Edit button, which reloads with those traits freed. */
  document.querySelectorAll('.dhcf-card .art').forEach(function (art) {
    art.addEventListener('click', function () {
      var card = art.closest('.dhcf-card');
      var traits;
      try { traits = JSON.parse(card.dataset.traits); } catch (e) { return; }
      if (!window.DHC_LOAD) return;
      DHC_LOAD(traits);
      syncSave();
      var frame = document.getElementById('frame');
      if (frame) frame.scrollIntoView({ behavior: 'smooth', block: 'center' });
      msg('Viewing ' + card.querySelector('.nm').textContent
          + ' — use Edit to change it, or Disassemble to free its traits.', true);
    });
  });

  document.querySelectorAll('.dhcf-rename').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.dhcf-card');
      var cur  = card.querySelector('.nm').textContent;
      var name = prompt('Name for this Fighter (blank to use its number):', cur);
      if (name === null) return;
      fetch('ajax/dhc-rename-fighter.php', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + card.dataset.id + '&name=' + encodeURIComponent(name)
      }).then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) card.querySelector('.nm').textContent = d.display; });
    });
  });

  document.querySelectorAll('.dhcf-scrap').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.dhcf-card');
      if (!confirm('Disassemble ' + card.querySelector('.nm').textContent +
                   '? Its traits return to your unused pile. The number is retired.')) return;
      fetch('ajax/dhc-delete-fighter.php', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + card.dataset.id
      }).then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    });
  });
})();
</script>

<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>

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

/*
 * WHAT EACH FIGHTER WILL ACTUALLY BE IN A FIGHT, so the roster can be sorted
 * on it. dhca_build_fighter() is the SAME function the Arena's Crew picker
 * and the battle itself use -- deriving a second opinion here would let this
 * page call a Fighter deadly that the Arena then disagrees with, which is
 * precisely the trap the picker's own comment warns about.
 *
 * dhcarena-engine.php is safe to pull in from here: it is deliberately
 * isolated from the DB and session layers (no $conn, no $_SESSION, no echo),
 * and dhcarena-lib.php already loads it alongside dhcfighters-lib.php, so the
 * two have always coexisted.
 *
 * Rarity score is NOT recomputed -- it is already on the row, and the stored
 * column is what the leaderboard ranks on, so the roster must agree with it.
 */
require_once __DIR__ . '/dhcarena-engine.php';
$dhcf_rar = dhcf_rarity();
foreach ($dhcf_roster as $i => $f) {
	$built = dhca_build_fighter(is_array($f['traits']) ? $f['traits'] : array(),
	                            '', 'f' . $f['id'], $dhcf_rar);
	$dhcf_roster[$i]['pow']  = (int)$built['power'];
	$dhcf_roster[$i]['hp']   = (int)$built['maxHp'];
	$dhcf_roster[$i]['crit'] = (int)round($built['critC'] * 100);
}
$dhcf_next     = dhcf_default_name(dhcf_next_serial($conn));
/*
 * The ladder is ONE panel now, so all-time has a whole column's worth of
 * space under the monthly list rather than a panel of its own. Fetch deep
 * enough to fill it and let CSS decide how much actually shows: how tall the
 * column is depends on how many Fighters the player has (the roster beside it
 * sets the row height) AND on how many cards fit per row at this width, which
 * is not knowable from here. So the count is a ceiling, not a guess.
 *
 * Monthly stays short on purpose -- it IS the short list, and it goes first.
 */
$dhcf_lb_ath   = dhcf_leaderboard($conn, 'ath', 30);
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
/* The canvas download. Signed-in only -- dhc-download.php will not draw
   traits the player does not own, so a guest's button could only refuse. */
$dhca_can_download = $dhcf_user > 0;
/* The live rank strip. Editing passes the Fighter's own id so it is left
   out of the pool it is ranked against -- otherwise it competes with
   itself and a build about to be the rarest reads as second. */
$dhca_can_rank = $dhcf_user > 0;
$dhca_edit_id  = $dhcf_editing ? (int)$dhcf_editing['id'] : 0;
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
/* NO MEASURE CAP. .dhcf-intro already grows to meet the stats, so the empty
   band on a wide screen was inside it: max-width:82ch held the text to a
   readable line and left the rest of the box blank, seven lines tall. Letting
   it wrap the full width turns those seven lines into three.

   Long lines are the point here -- this is a masthead blurb read once, not body
   copy read for minutes, and splitting it into columns chopped one paragraph
   into two that had to be read in order. */
.dhcf-note{font-size:11.5px;opacity:.65;line-height:1.6;margin:0}
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
/* NO DOWNLOAD ARROW ON THE CARD. It used to sit in the corner of the art,
   which was the right place for it while the roster was a full-width panel
   of 150px cards. In the picker column the cards are half that and run seven
   to a row, so twenty arrows read as noise over the artwork -- and the route
   is already there: select a Fighter and Download 1000px PNG is on the
   canvas beside it, operating on exactly what you are looking at.
   dhc-download.php is untouched and still serves the file. */
.dhcf-stat b{display:block;font-size:17px;font-variant-numeric:tabular-nums}
.dhcf-stat span{font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;opacity:.6}
/* Reference block, below everything you actually operate. It answers "what
   should I play next", which is worth having on the page but never worth
   pushing the assembler down the screen for. */
/* It is a .dhcf-panel now, which already supplies the border, the radius and
   the clip -- and it sits IN the panels grid, so the standalone block's own
   top margin would show up as a gap only this column had. */
.dhcf-games{overflow:hidden}
/* One game per row in a column, rather than the two-up grid it used across
   the full page width. auto-fill at 240px would still manage two columns in a
   wide panel and the rows read better as a list. */
.dhcf-games ul{display:block}
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
/* SIX UP ON A BIG SCREEN. The twelve sources fall into two clean rows of six
   instead of the three ragged rows auto-fill gives at a 240px minimum, which
   is what pushes the panels below it off the fold on a desktop.

   Fixed count rather than a smaller auto-fill minimum, because "six" is the
   point -- auto-fill would give five or seven depending on the window and the
   rows would go ragged again. minmax(0,1fr) rather than 1fr so a long trigger
   line wraps inside its track instead of widening it and breaking the six.

   1400px is where .main (flex:70% of the row, less its 20px padding and the
   wrapper's 14px) leaves each of six tracks enough width for the count and the
   "n of n left today" line. Below it the auto-fill above still applies.

   Two rows is a property of there being twelve sources, not of this rule --
   add a thirteenth and it becomes three. */
@media (min-width:1400px){
  .dhcf-games ul{grid-template-columns:repeat(6,minmax(0,1fr))}
  .dhcf-games li{padding:7px 9px}
}
/* TWO columns now, not three: the roster moved up into the picker's dead
   space, leaving the trait-drop list and the ladder to share the row.
 *
 * AND THEY SPLIT WHERE THE ASSEMBLER SPLITS. An auto-fit grid divided these
 * two 50/50 while the stage/picker seam directly above them sits at
 * --stage, so the page ran two vertical rules a couple of hundred pixels
 * apart, one below the other.
 *
 * NO COLUMN GAP, because the edge that has to line up is the RIGHT panel's
 * LEFT edge against the picker's left edge -- those two boxes are the same
 * column of the page and the eye follows them as one line. With a 14px
 * gutter that is impossible: the gutter puts the right panel 14px past
 * wherever the left one ends, so either the left panel's right edge meets
 * the picker's border or the right panel's left edge does, never both. An
 * earlier pass aligned the left one, which is why this still read as
 * crooked -- the misaligned edge was the one being looked at.
 *
 * .shell solves it the same way and always has: gap:0, stage and picker
 * sharing an edge with a single border between them. This row now mirrors
 * that, so the two blocks read as one grid rather than two.
 *
 * The +1px is .shell's own border: its first track starts at x+1 while this
 * panel's border box starts at x. Measured: .shell 14, .stage 15, panel 14.
 * .dhcf-games drops its right border so the seam stays ONE rule and not two
 * touching -- the surviving rule is the ladder's left border, landing on the
 * same pixels as the picker's.
 *
 * Same 900px breakpoint as .shell, so the two collapse together rather than
 * one stacking while the other is still split.
 */
.dhcf-panels{display:grid;
  grid-template-columns:minmax(0,calc(var(--stage) + 1px)) minmax(0,1fr);
  column-gap:0;row-gap:14px;margin-top:18px}
.dhcf-panels > .dhcf-games{border-right:0}
@media (max-width:900px){
  /* Stacked, they are not columns at all and each needs its own frame. */
  .dhcf-panels{grid-template-columns:1fr;row-gap:14px}
  .dhcf-panels > .dhcf-games{border-right:1px solid var(--line)}
}

/* ---- the roster, inside the picker column ---------------------------------
   It is a .dhcf-panel for its internals (header, cards, pager) but it is no
   longer a panel in a grid: the picker already supplies the frame, so the
   outer border would double every edge against it. */
.picker > .dhcf-aside{border:0;border-radius:0;border-top:1px solid var(--line)}
.picker > .dhcf-aside > .body{flex:1;min-height:0;overflow-y:auto;padding:8px 10px}
/* Smaller cards here than on a full-width panel. This is a character select
   in a ~380-600px column, not the gallery -- at the roster's own 150px floor
   it managed two per row and the pager did all the work. */
.picker > .dhcf-aside .dhcf-roster{grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:7px}
.picker > .dhcf-aside .dhcf-card .acts{gap:3px;padding:0 5px 5px}
.picker > .dhcf-aside .dhcf-card .acts button,
.picker > .dhcf-aside .dhcf-card .acts .dhcf-edit{font-size:8px;padding:3px 2px}
.picker > .dhcf-aside .dhcf-card .acts .dhcf-scrap{padding:3px 4px}
.picker > .dhcf-aside .dhcf-card .acts .dhcf-scrap svg{width:10px;height:10px}
.picker > .dhcf-aside .dhcf-card .meta{padding:4px 6px}
.picker > .dhcf-aside .dhcf-card .nm{font-size:10px}
.picker > .dhcf-aside .dhcf-card .sc{font-size:9px}
.picker > .dhcf-aside .dhcf-card .sc.st{font-size:8px}
@media (max-width:900px){
  /* Single column: the whole roster laid out, nothing scrolling inside it.
     The cap and the pin are lifted in dhc-assembler.php's own mobile block,
     NOT here -- that stylesheet is emitted at the include point, after this
     one, so an equal-specificity rule here loses the cascade and the
     clipping survives. This only has to clear the body's own scroller. */
  .picker > .dhcf-aside > .body{overflow:visible;max-height:none}
}
/* ---- the ladder column ----------------------------------------------------
   Grid items stretch, so this panel is already exactly as tall as the roster
   beside it -- which is as tall as the player's own Fighter count makes it.
   The monthly list takes the space it needs and all-time takes the REST, so
   the column fills rather than ending in dead space or pushing the page
   taller. min-height:0 on both the body and the scroller is the part that is
   easy to miss: a flex child's default min-height is auto, so without it the
   all-time table refuses to shrink and overflows the panel instead of
   scrolling inside it. */
.dhcf-ladder{display:flex;flex-direction:column}
.dhcf-ladder .body{flex:1;min-height:0;display:flex;flex-direction:column;gap:12px}
.dhcf-ladder .lb-sec h3{margin:0 0 5px;font-size:9px;letter-spacing:.14em;text-transform:uppercase;
  opacity:.5;font-weight:normal}
.dhcf-ladder .lb-ath{flex:1;min-height:0;overflow-y:auto}
/* The sub-heading has to stay put while its own list scrolls under it. */
.dhcf-ladder .lb-ath h3{position:sticky;top:0;background:var(--ink);padding-bottom:4px;z-index:1}
@media (max-width:900px){
  /* 900, not 760: that is where .shell and .dhcf-panels both go single
     column, and between the two numbers the ladder was already stacked --
     no column height left to fill -- while still scrolling inside itself.
     A nested scroller in a page that already scrolls is a trap, and one on
     a block that is no longer a column is a trap for nothing. */
  .dhcf-ladder .lb-ath{overflow:visible;max-height:none}
}
.dhcf-panel{border:1px solid var(--line);border-radius:3px;overflow:hidden}
.dhcf-panel h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  opacity:.65;border-bottom:1px solid var(--line);display:flex;flex-wrap:wrap;
  justify-content:space-between;gap:2px 10px}
.dhcf-panel h2 a{color:var(--ochre);text-decoration:none;opacity:.9;letter-spacing:.08em;white-space:nowrap}
.dhcf-panel h2 a:hover{text-decoration:underline}
.dhcf-panel .body{padding:10px 12px}
.dhcf-roster{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
/* PAGING. A card is eight <img> layers, so a staker with two hundred Fighters
   was being handed sixteen hundred images in one panel. Off-page cards are
   display:none rather than removed -- a browser does not fetch images inside a
   display:none subtree, so the pages you are not looking at cost nothing, and
   nothing has to be rebuilt to page back to them. */
.dhcf-card.off{display:none}
.dhcf-pager{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:10px;
  font-size:11px;opacity:.7}
.dhcf-pager button{background:var(--panel2);color:var(--bone);border:1px solid var(--line);
  border-radius:3px;padding:3px 10px;font:inherit;cursor:pointer}
.dhcf-pager button:disabled{opacity:.35;cursor:default}
.dhcf-pager button:not(:disabled):hover{border-color:var(--ochre);color:var(--ochre)}
.dhcf-card{border:1px solid var(--line);border-radius:3px;overflow:hidden;position:relative}
.dhcf-card .art{position:relative;aspect-ratio:1;background:var(--panel2);overflow:hidden;
  display:block;width:100%;padding:0;border:0;cursor:pointer}
.dhcf-card .art:hover{outline:1px solid var(--ochre);outline-offset:-1px}
.dhcf-card .art:focus-visible{outline:2px solid var(--ochre);outline-offset:-2px}
.dhcf-card .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.dhcf-card .meta{padding:6px 8px}
.dhcf-card .nm{font-size:11.5px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dhcf-card .sc{font-size:10px;opacity:.6;font-variant-numeric:tabular-nums}
.dhcf-card .sc.st{display:block;font-size:9px;opacity:.45;letter-spacing:.04em}
/* Sits in the panel's <h2>, which is a flex row ending in the collection
   link -- margin-left:auto on the link already pushes that to the right, so
   this only has to not stretch. */
.dhcf-sort{display:inline-flex;align-items:center;gap:6px;margin-left:14px;font-size:9.5px;
  letter-spacing:.12em;text-transform:uppercase;opacity:.6;font-weight:normal}
.dhcf-sort select{font:inherit;font-size:10px;letter-spacing:.04em;text-transform:none;
  background:var(--panel2);color:inherit;border:1px solid var(--line);border-radius:0;
  padding:3px 6px}
.dhcf-sort select:hover{border-color:var(--ochre)}
@media (max-width:560px){
  .dhcf-sort{margin-left:0;width:100%}
  .dhcf-sort select{flex:1;min-width:0}
}
.dhcf-card .firstb{color:var(--ochre);opacity:1;font-size:8.5px;letter-spacing:.12em;
  border:1px solid var(--ochre);border-radius:999px;padding:1px 5px;margin-left:4px}
.dhcf-card .acts{display:flex;gap:5px;padding:0 8px 8px}
/* The trash button does not share the 1fr the two word buttons take -- it is
   as wide as its icon, which is what gives Edit and Rename room to stay
   words. */
.dhcf-card .acts .dhcf-scrap{flex:0 0 auto;display:inline-flex;align-items:center;
  justify-content:center;padding:3px 6px}
.dhcf-card .acts .dhcf-scrap svg{width:11px;height:11px;display:block;
  fill:none;stroke:currentColor;stroke-width:1.3;stroke-linecap:round;stroke-linejoin:round}
.dhcf-card .acts button{flex:1;font:inherit;font-size:9px;letter-spacing:.08em;text-transform:uppercase;
  padding:4px;cursor:pointer;background:none;border:1px solid var(--line);border-radius:2px;color:inherit}
.dhcf-card .acts button:hover{border-color:var(--ochre);color:var(--ochre)}
.dhcf-lb{width:100%;border-collapse:collapse;font-size:12px}
.dhcf-lb th{text-align:left;font-size:9px;letter-spacing:.12em;text-transform:uppercase;opacity:.55;padding:4px 6px}
.dhcf-lb td{padding:5px 6px;border-top:1px solid var(--line);font-variant-numeric:tabular-nums}
.dhcf-lb .r{width:26px;opacity:.6}
/* Breathing room when a Fighter card scrolls the assembler to the top -- see
   the .art click handler. Flush against the viewport edge reads as cut off. */
.dhcf-wrap .shell{scroll-margin-top:12px}
/* margin BELOW now, not above: it leads the assembler rather than trailing
   it. The .dhcf-stats strip above supplies its own spacing. */
.dhcf-save{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}
.dhcf-save input{flex:1;min-width:170px;font:inherit;font-size:12px;padding:7px 9px;border-radius:2px;
  background:var(--ink);border:1px solid var(--line);color:inherit}
.dhcf-save button{font:inherit;font-size:11px;letter-spacing:.08em;text-transform:uppercase;padding:8px 16px;
  cursor:pointer;border-radius:2px;background:var(--ochre);border:1px solid var(--ochre);color:var(--ink)}
.dhcf-save button:hover{filter:brightness(1.12)}
.dhcf-save button:disabled{background:transparent;border-color:var(--line);color:var(--dim);cursor:not-allowed;filter:none}
.dhcf-say{font-size:11.5px;min-height:16px;opacity:.85}
</style>

<div class="dhcf-wrap">

<?php $dhcnav_at = 'fighters'; include 'dhc-nav.php'; ?>

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

  <?php
  /*
   * THE ROSTER GOES IN THE PICKER COLUMN, not in the grid below.
   *
   * The picker is as tall as the stage beside it -- canvas, buttons, ranks
   * and the whole draw-order list -- and one slot's worth of thumbnails
   * almost never reaches the bottom of that. On Torso it left most of a
   * screen of empty panel. The roster is the right thing to put there: it is
   * the character select, and choosing who to work on then dressing them is
   * one movement, not two parts of the page.
   *
   * Buffered into a string and handed to the assembler rather than included
   * after it, because dhc-assembler.php has to stay something dhcsandbox.php
   * can include with no roster, no session and no database. See $dhca_aside
   * there.
   */
  /* dhcf_art_dir(), not the assembler's own $dhc_base: that is set inside
     dhc-assembler.php, which has not run yet at this point. Reading it here
     is what put "Undefined variable $dhc_base" into the alt text of every
     roster card. Same function the assembler itself now calls. */
  $dhc_base = dhcf_art_dir();
  ob_start();
  ?>
    <div class="dhcf-panel dhcf-aside">
      <h2>Your Fighters<?php if (count($dhcf_roster) > 1): ?><label class="dhcf-sort"><span>Sort</span><select id="dhcfSort">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
            <option value="rarest">Rarest</option>
            <option value="commonest">Least rare</option>
            <option value="deadliest">Deadliest</option>
            <option value="toughest">Toughest</option>
            <option value="name">Name</option>
          </select></label><?php endif; ?><a href="dhcgallery.php?mine=1">In the collection &rsaquo;</a></h2>
      <div class="body">
        <?php if (!$dhcf_roster): ?>
          <p style="font-size:12px;opacity:.6;margin:2px 0">Nothing saved yet.</p>
        <?php else: ?>
        <div class="dhcf-roster">
          <?php foreach ($dhcf_roster as $f): ?>
            <?php /* Every sortable fact as a data attribute. The sort is
                     client-side because the roster is already fully rendered
                     and paged in the browser -- see the pager comment in the
                     script block -- and a round trip to reorder cards that
                     are all here already would be pure latency. */ ?>
            <div class="dhcf-card" data-id="<?php echo (int)$f['id']; ?>"
                 data-score="<?php echo (int)$f['rarity_score']; ?>"
                 data-pow="<?php echo (int)$f['pow']; ?>"
                 data-hp="<?php echo (int)$f['hp']; ?>"
                 data-created="<?php echo (int)strtotime($f['created_at']); ?>"
                 data-name="<?php echo htmlspecialchars($f['display'], ENT_QUOTES); ?>"
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
                <?php /* No FIRST badge: a trait set can only exist once now, so
                         every Fighter would wear one and a badge everything has
                         says nothing. See dhcf_save_fighter(). */ ?>
                <?php /* POW and HP are on the card because otherwise "sort by
                         deadliest" reorders the grid on a number the player
                         cannot see, which reads as the sort being broken. */ ?>
                <span class="sc"><?php echo number_format((int)$f['rarity_score']); ?> pts</span>
                <span class="sc st"><?php echo (int)$f['pow']; ?> POW &middot; <?php echo (int)$f['hp']; ?> HP</span>
              </div>
              <div class="acts">
                <a class="dhcf-edit" href="dhcfighters.php?edit=<?php echo (int)$f['id']; ?>">Edit</a>
                <button type="button" class="dhcf-rename">Rename</button>
                <?php /* An icon, because the word does not fit. "Disassemble" was
                         already the longest label in a three-control row, and in
                         the picker column the cards are ~96px wide -- it rendered
                         as "DISA". A clipped word is worse than no word: it reads
                         as a layout fault and says nothing about what the button
                         does. An inline SVG rather than the 🗑 emoji, which is a
                         different shape, weight and colour on every platform and
                         would not take currentColor on hover. The name survives
                         in title and aria-label, and the confirm still spells
                         out what disassembly costs. */ ?>
                <button type="button" class="dhcf-scrap" title="Disassemble <?php echo htmlspecialchars($f['display']); ?>"
                        aria-label="Disassemble <?php echo htmlspecialchars($f['display']); ?>"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6.5 1.5h3M2.5 3.5h11M4.5 3.5l.7 10a1 1 0 0 0 1 .95h3.6a1 1 0 0 0 1-.95l.7-10M6.8 6.3v5.4M9.2 6.3v5.4"/></svg></button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="dhcf-pager" id="dhcfPager" style="display:none">
          <button type="button" id="dhcfPrev">&lsaquo; Prev</button>
          <span id="dhcfPageLbl"></span>
          <button type="button" id="dhcfNext">Next &rsaquo;</button>
        </div>
        <?php endif; ?>
      </div>
    </div>

  <?php
  $dhca_aside = ob_get_clean();
  ?>

  <?php /* ABOVE THE ASSEMBLER, NOT BELOW IT. It used to sit under the shell,
           which on a wide screen put it beneath the draw-order list on one
           side and twenty roster cards on the other -- the one control the
           whole page exists to reach, in the busiest place on it. Here it is
           the first thing under the header, on its own line, with nothing
           else competing. It still acts on whatever is on the canvas; being
           above it changes nothing but where you look for it. */ ?>
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

  <?php include __DIR__ . '/dhc-assembler.php'; ?>

  <div class="dhcf-panels">

    <?php /* SECOND, between the roster and the ladder. It was a full-width
             block below the panels, so on a wide screen you scrolled past
             two half-empty ladders to reach the one thing that answers
             "what should I play next" -- and putting it third would have
             left it last in reading order for the same reason. It sits
             beside the roster because the two are read together: what you
             have, and where the next piece comes from. */ ?>
    <div class="dhcf-panel dhcf-games">

      <?php
        /*
         * WHERE TRAITS COME FROM -- shown always, not just to an empty
         * account. It is the answer to "what should I play next", which a
         * player with 40 traits asks more often than one with none, and the
         * counts turn it from a legend into a progress list.
         */
      ?>
      <h2>Where traits drop
        <span><?php echo (int)$dhcf_distinct; ?>/<?php echo (int)$dhcf_all; ?></span>
      </h2>
      <ul>
      <?php foreach ($GLOBALS['DHCF_GAMES'] as $gkey => $g):
          // A source flagged 'unlisted' still pays, and is still counted and
          // capped like any other -- it just does not belong in a list of games
          // to go and play. See DHCF_GAMES['dailystreak'].
          if (!empty($g['unlisted'])) continue;
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

    <?php /* ONE ladder panel, LAST. It used to be two panels side by side,
             which pushed "Where traits drop" below the fold on a desktop --
             and the two lists are the same four columns of the same table,
             so reading as two separate things was never right either.
             Monthly leads inside it because it is the short list and the
             live one; all-time fills whatever height the roster column
             creates. Last of the three because it is the only one that is
             about other people. */ ?>
    <div class="dhcf-panel dhcf-ladder">
      <h2>Ladder &mdash; best Fighter</h2>
      <div class="body">
        <div class="lb-sec">
          <h3>This month</h3>
          <?php echo dhcf_board_html($dhcf_lb_month); ?>
        </div>
        <div class="lb-sec lb-ath">
          <h3>All time</h3>
          <?php echo dhcf_board_html($dhcf_lb_ath); ?>
        </div>
      </div>
    </div>

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
  /* ---- roster paging ----------------------------------------------------
     Hidden, not removed: every card carries its traits in a data attribute and
     its own Edit / Rename / Disassemble wiring, and rebuilding the grid per
     page would mean re-attaching all of it for no gain. */
  /* 20, not 12. In the picker column the cards sit at a 96px floor and run
     seven or more to a row, so a dozen was two rows and a pager -- more
     paging than choosing. */
  var PER_PAGE = 20, rpage = 0;
  var rcards = [].slice.call(document.querySelectorAll('.dhcf-card'));
  var rpager = document.getElementById('dhcfPager');
  function rpaint() {
    var n = Math.max(1, Math.ceil(rcards.length / PER_PAGE));
    if (rpage >= n) rpage = n - 1;
    if (rpage < 0) rpage = 0;
    rcards.forEach(function (c, i) {
      c.classList.toggle('off', Math.floor(i / PER_PAGE) !== rpage);
    });
    if (!rpager) return;
    rpager.style.display = n > 1 ? 'flex' : 'none';
    document.getElementById('dhcfPageLbl').textContent =
      'Page ' + (rpage + 1) + ' of ' + n + ' · ' + rcards.length + ' Fighters';
    document.getElementById('dhcfPrev').disabled = (rpage === 0);
    document.getElementById('dhcfNext').disabled = (rpage === n - 1);
  }
  if (rpager) {
    document.getElementById('dhcfPrev').addEventListener('click', function () { rpage--; rpaint(); });
    document.getElementById('dhcfNext').addEventListener('click', function () { rpage++; rpaint(); });
  }

  /* ---- roster sorting ---------------------------------------------------
     Reorders the same cards rather than re-rendering: they carry their own
     wiring and their own 250px layer stack, so moving the nodes keeps both.
     Every order ends with the same two tiebreaks, so equal Fighters never
     shuffle between sorts and the grid cannot appear to change at random. */
  var rgrid = document.querySelector('.dhcf-roster');
  var rsort = document.getElementById('dhcfSort');
  var SORT_KEY = 'dhcf.roster.sort';

  function rnum(c, k) { var v = parseFloat(c.getAttribute('data-' + k)); return isNaN(v) ? 0 : v; }
  function rname(c) { return (c.getAttribute('data-name') || '').toLowerCase(); }
  function tiebreak(a, b) {
    return (rnum(b, 'score') - rnum(a, 'score')) || (rnum(b, 'created') - rnum(a, 'created'));
  }
  var ORDERS = {
    /* The server already hands them over newest-first, but say so explicitly
       -- the default must survive being switched away from and back. */
    newest:    function (a, b) { return rnum(b, 'created') - rnum(a, 'created') || tiebreak(a, b); },
    oldest:    function (a, b) { return rnum(a, 'created') - rnum(b, 'created') || tiebreak(a, b); },
    rarest:    function (a, b) { return rnum(b, 'score') - rnum(a, 'score') || tiebreak(a, b); },
    /* The one that answers "which should I work on": least rare FIRST. */
    commonest: function (a, b) { return rnum(a, 'score') - rnum(b, 'score') || tiebreak(a, b); },
    deadliest: function (a, b) { return rnum(b, 'pow') - rnum(a, 'pow') || tiebreak(a, b); },
    toughest:  function (a, b) { return rnum(b, 'hp') - rnum(a, 'hp') || tiebreak(a, b); },
    name:      function (a, b) { return rname(a).localeCompare(rname(b)) || tiebreak(a, b); }
  };

  function rapply(key, remember) {
    var cmp = ORDERS[key] || ORDERS.newest;
    rcards.sort(cmp);
    /* appendChild MOVES an existing node, so this reorders in place. */
    if (rgrid) rcards.forEach(function (c) { rgrid.appendChild(c); });
    rpage = 0;
    rpaint();
    /* Storage throws in a private window and can simply be unavailable; the
       sort has to work either way, so this is best-effort on both sides. */
    if (remember) { try { localStorage.setItem(SORT_KEY, key); } catch (e) {} }
  }

  if (rsort) {
    var saved = null;
    try { saved = localStorage.getItem(SORT_KEY); } catch (e) {}
    if (saved && ORDERS[saved]) { rsort.value = saved; rapply(saved, false); }
    rsort.addEventListener('change', function () { rapply(rsort.value, true); });
  }

  rpaint();

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
          } else {
            /* No "first to build this" any more: a trait set can only exist
               once, so saying it of every save says nothing. */
            line = 'Saved as ' + d.display + ' — ' + d.score + ' pts';
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
      /* THE TOP OF THE ASSEMBLER, not the middle of the canvas. The canvas
         is 66vh tall, so centring it put its midpoint at the viewport's
         midpoint -- which on any normal window scrolls very nearly to the
         top of the PAGE, past the header and the stats strip, and left you
         scrolling back down to see what you had just clicked. 'start' with a
         scroll-margin on .shell lands the assembly image just under the top
         edge instead. The navbar is position:relative, so nothing is
         covering it. */
      var top = document.querySelector('.shell') || document.getElementById('frame');
      if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
      /* Names Edit explicitly. Until it existed, disassembly was the only way
         to change a Fighter at all, so reaching for it to tweak one is the
         habit players arrive with -- and this is the point where it costs them
         the number permanently. */
      if (!confirm('Disassemble ' + card.querySelector('.nm').textContent +
                   '?\n\nThis removes the character for good. Its traits return to your ' +
                   'unused pile and its number goes back in the pool.\n\n' +
                   'To change it instead, use Edit — that keeps the number and name.')) return;
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

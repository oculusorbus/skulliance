<?php
/**
 * DHC FIGHTERS -- THE COLLECTION
 *
 * Every Fighter assembled on the platform, browsable. dhcfighters.php is the
 * workshop; this is where the collection actually gets seen, which is what
 * makes building a distinctive one worth doing.
 *
 * A PUBLIC page. It was staker-only, which made the sentence above false in
 * practice: "where the collection actually gets seen" cannot be true of a page
 * only the people who built them can open. Filters live in the query string so
 * a view can be linked to -- "every mythic Fighter" is a URL worth sending
 * someone, and until now sending it to anyone outside the platform produced an
 * error page.
 *
 * Owners are shown on every Fighter regardless of their profile visibility
 * setting: that flag governs a staker's own holdings, and an assembled Fighter
 * is not a holding. Nobody owns these -- which is also why showing them to the
 * world costs nobody anything.
 *
 * The gate is conditional, exactly as dhcarena.php does it: skulliance.php
 * redirects an anonymous visitor to error.php, but verify.php defines
 * checkUser() and skulliance.php calls it to resolve user_id, so the pair has
 * to run in full for a member or a signed-in staker loses their "Mine" filter.
 * Merge the cookie, never assign -- see skulliance.php's own note.
 */
include 'db.php';

if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$dhcg_ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($dhcg_ck)) {
		$_SESSION = array_merge((array)$_SESSION, $dhcg_ck);
	} else {
		// undecodable, so it is resent on every request; clear it and carry on
		setcookie('SessionCookie', '', time() - 3600);
	}
	unset($dhcg_ck);
}
$dhcg_guest = empty($_SESSION['logged_in']);
if (!$dhcg_guest) { include 'verify.php'; include 'skulliance.php'; }
require_once __DIR__ . '/dhcfighters-lib.php';
/*
 * The Arena engine, for its numbers only -- it is pure, so reading a Fighter's
 * health and power costs nothing and needs no battle. Rarity score says what a
 * Fighter is WORTH; these say what it can DO, and until now the collection only
 * showed the first, which is why a player had no way to tell a beast from an
 * expensive ornament.
 */
require_once __DIR__ . '/dhcarena-engine.php';
/* One definition of Rarest / Deadliest / Toughest / Hardest hitting, shared
   with the assembler's live rank preview. */
require_once __DIR__ . '/dhc-ranks.php';

$dhcg_user = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

/* ---- controls ---- */
$dhcg_sort  = isset($_GET['sort'])  ? preg_replace('/[^a-z]/', '', $_GET['sort'])  : 'rarest';
$dhcg_tier  = isset($_GET['tier'])  ? preg_replace('/[^a-z]/', '', $_GET['tier'])  : '';
$dhcg_owner = isset($_GET['owner']) ? (int)$_GET['owner'] : 0;
$dhcg_mine  = !empty($_GET['mine']);
$dhcg_kit   = isset($_GET['kit']) ? preg_replace('/[^a-z]/', '', $_GET['kit']) : '';

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
$dhcg_rarity   = dhcf_rarity();
$dhcg_kits     = array();      // kit id => label, for the filter bar
foreach (dhca_kits() as $k) $dhcg_kits[$k['id']] = $k;
$dhcg_kit_counts = array();
/* Every saved Fighter's four numbers, each axis sorted. Read ONCE for the
   whole page, not per row -- it is disk-cached against COUNT(*) and
   MAX(updated_at), but re-reading it inside the loop would still be a
   file read and a json_decode per Fighter. */
$dhcg_pool = dhcf_rank_pool($conn);

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

		/* What the Arena will make of it. Torso sets health, weapon sets power
		   and fighting style, headgear sets how often a hit lands big -- so
		   these three numbers, and not the score, are what a Crew is picked on. */
		$built        = dhca_build_fighter($row['traits'], '', 'g'.$row['id'], $dhcg_rarity);
		$row['roles'] = isset($built['roles']) ? $built['roles'] : array();
		$row['kit']   = $built['kit'];
		$row['crit']  = (float)$built['critC'];
		$row['resist']= (float)$built['resist'];
		$row['assist']= (float)$built['assist'];
		$row['charge']= (float)$built['charge'];
		/* The four sort axes come from dhc-ranks.php, which is also what the
		   assembler's live preview ranks against. They were defined here
		   first; they are defined THERE now, so "Deadliest" cannot come to
		   mean one thing on this page and another on the canvas. $built is
		   passed in so this stays one pass per row. */
		$rv           = dhcf_rank_values($row['traits'], (int)$row['rarity_score'], $built);
		$row['hp']    = $rv['tough'];
		$row['pow']   = $rv['power'];
		$row['might'] = $rv['might'];
		/* WHERE IT PLACES, on the same four axes the assembler previews live.
		   Against the WHOLE collection, not against whatever this page is
		   filtered to -- "#3 of 9 legendary Fighters" would be a different
		   and much less interesting claim than "#3 of 108". dhcf_rank_pool()
		   is read once outside this loop and is disk-cached, so ranking a row
		   is a binary search and nothing more. */
		$row['rank'] = array();
		foreach (dhcf_rank_axes() as $rk => $rlabel) {
			$row['rank'][$rk] = dhcf_rank_of($dhcg_pool[$rk], $rv[$rk]);
		}
		$row['rankOf'] = (int)$dhcg_pool['_n'];

		$dhcg_owners[(int)$row['user_id']] = $row['username'];
		$dhcg_all[] = $row;
	}
}

/* No originality pass any more: a trait set can only exist once, enforced when
   a Fighter is saved, so every Fighter here is the only one of its kind and a
   badge saying so would be on all of them. */
foreach ($dhcg_all as $i => $row) {
	$dhcg_tier_counts[$dhcg_all[$i]['best']]++;
	$kid = $dhcg_all[$i]['kit']['id'];
	$dhcg_kit_counts[$kid] = (isset($dhcg_kit_counts[$kid]) ? $dhcg_kit_counts[$kid] : 0) + 1;
}

$dhcg_total = count($dhcg_all);
$dhcg_rows  = array_values(array_filter($dhcg_all, function ($r) use ($dhcg_tier, $dhcg_kit) {
	if ($dhcg_tier !== '' && $r['best'] !== $dhcg_tier) return false;
	if ($dhcg_kit !== '' && $r['kit']['id'] !== $dhcg_kit) return false;
	return true;
}));
if ($dhcg_sort === 'traits') {
	usort($dhcg_rows, function ($a, $b) { return count($b['parts']) <=> count($a['parts']); });
}
/* Sorted in PHP like the others: these come out of the engine, not the table. */
if ($dhcg_sort === 'tough')  usort($dhcg_rows, function($a,$b){ return $b['hp']    <=> $a['hp']; });
if ($dhcg_sort === 'power')  usort($dhcg_rows, function($a,$b){ return $b['pow']   <=> $a['pow']; });
if ($dhcg_sort === 'might')  usort($dhcg_rows, function($a,$b){ return $b['might'] <=> $a['might']; });

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

/*
 * SHAREABLE, which is most of the point of opening it up. A filtered view is
 * the unit people actually send -- "every mythic Fighter" -- so the description
 * follows the filter rather than being one fixed sentence for every URL.
 * Canonical points at the bare page: the filters are views of one collection,
 * not separate pages, and letting every permutation claim to be its own is how
 * a gallery turns into a thousand near-duplicates.
 *
 * Absolute URLs are correct in SEO markup and nowhere else -- the login cookie
 * is host-only, so an absolute link somebody can CLICK can hop hosts and sign
 * them out. No chain or token wording: this is the public face of the site.
 */
$dhcg_canonical = 'https://www.skulliance.io/staking/dhcgallery.php';
$dhcg_what = $dhcg_tier ? ucfirst($dhcg_tier).' Fighters' : 'Every Fighter';
$dhcg_title = ($dhcg_tier ? ucfirst($dhcg_tier).' Fighters' : 'The Fighter Collection')
            . ' — DHC Fighters | Skulliance';
$dhcg_desc  = $dhcg_what.' assembled on Skulliance, browsable by anyone. '
            . number_format($dhcg_total).' built so far, each one put together piece by '
            . 'piece from a shared set of parts — and no two the same. See what every '
            . 'trait is worth, what it does in a fight, and who built it.';
/*
 * ?fighter=SERIAL MAKES THIS A PAGE ABOUT ONE FIGHTER, as far as X and every
 * other crawler is concerned. Without per-Fighter tags a shared link carries
 * the generic collection image, which is the same picture for all 108 of
 * them -- and a share that shows somebody else's logo instead of the thing
 * you built is not worth posting. x.com/intent/post cannot attach a file;
 * the ONLY image a post carries is the one the shared URL declares here.
 *
 * Its own query, not a scan of $dhcg_rows: the rows are whatever this page
 * is filtered and paged to, and a link to a Fighter outside that filter must
 * still unfurl. Cheap, indexed on serial, and only runs when the parameter
 * is present.
 *
 * The CARD is dhc-card.php, which is 1200x630 -- X centre-crops
 * summary_large_image, so handing it the square render would behead the
 * Fighter.
 */
$dhcg_one = null;
if (isset($_GET['fighter']) && (int)$_GET['fighter'] > 0) {
	$fq = $conn->prepare("SELECT f.serial, f.name, f.rarity_score, f.traits, u.username
	                      FROM dhc_fighters f
	                      LEFT JOIN users u ON u.id = f.user_id
	                      WHERE f.serial = ? AND f.disassembled_at IS NULL LIMIT 1");
	if ($fq) {
		$fs = (int)$_GET['fighter'];
		$fq->bind_param('i', $fs);
		$fq->execute();
		$dhcg_one = $fq->get_result()->fetch_assoc() ?: null;
		$fq->close();
	}
	if ($dhcg_one) {
		$dhcg_one['traits']  = json_decode($dhcg_one['traits'], true) ?: array();
		$dhcg_one['display'] = dhcf_display_name($dhcg_one);
		$ov = dhcf_rank_values($dhcg_one['traits'], (int)$dhcg_one['rarity_score']);
		$obest = array();
		foreach (dhcf_rank_axes() as $ok => $olabel) {
			$obest[] = array('label' => $olabel, 'rank' => dhcf_rank_of($dhcg_pool[$ok], $ov[$ok]));
		}
		usort($obest, function ($x, $y) { return $x['rank'] <=> $y['rank']; });
		$dhcg_one['blurb'] = '#' . number_format($obest[0]['rank']) . ' ' . strtolower($obest[0]['label'])
		                   . ', #' . number_format($obest[1]['rank']) . ' ' . strtolower($obest[1]['label'])
		                   . ' of ' . number_format((int)$dhcg_pool['_n']) . ' DHC Fighters'
		                   . (!empty($dhcg_one['username']) ? ', assembled by ' . $dhcg_one['username'] : '')
		                   . '. Built piece by piece from traits earned across Skulliance.';
		$dhcg_canonical = 'https://www.skulliance.io/staking/dhcgallery.php?fighter=' . (int)$dhcg_one['serial'];
		$dhcg_title     = $dhcg_one['display'] . ' - DHC Fighters | Skulliance';
		$dhcg_desc      = $dhcg_one['blurb'];
	}
}

$page_title_override = $dhcg_title;
/* One Fighter gets its own card; the collection keeps the house image. */
$dhcg_card_img = $dhcg_one
    ? 'https://www.skulliance.io/staking/dhc-card.php?serial=' . (int)$dhcg_one['serial']
    : 'https://www.skulliance.io/staking/images/og.jpg';
$dhcg_card_alt = $dhcg_one
    ? $dhcg_one['display'] . ', a DHC Fighter assembled on Skulliance'
    : 'The DHC Fighter Collection - characters assembled from a shared set of traits';

$extra_head = '
<meta name="description" content="'.htmlspecialchars($dhcg_desc).'">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="'.$dhcg_canonical.'">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="'.$dhcg_canonical.'">
<meta property="og:title" content="'.htmlspecialchars($dhcg_title).'">
<meta property="og:description" content="'.htmlspecialchars($dhcg_desc).'">
<meta property="og:image" content="'.htmlspecialchars($dhcg_card_img).'">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="'.htmlspecialchars($dhcg_card_alt).'">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="'.htmlspecialchars($dhcg_title).'">
<meta name="twitter:description" content="'.htmlspecialchars($dhcg_desc).'">
<meta name="twitter:image" content="'.htmlspecialchars($dhcg_card_img).'">
<meta name="twitter:image:alt" content="'.htmlspecialchars($dhcg_card_alt).'">
';

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
.dhcg-stats{display:flex;gap:7px;align-items:center;padding:0 7px 5px;font-size:10px;
  opacity:.72;flex-wrap:wrap;font-variant-numeric:tabular-nums}
.dhcg-stats .s-kit{opacity:.8;margin-left:auto;white-space:nowrap;overflow:hidden;
  text-overflow:ellipsis;min-width:0}
/* Masked, not an <img>, so it takes the colour of whatever it sits in -- the
   filter chip when active, the card label when muted. */
.dhcg-ico{display:inline-block;width:12px;height:12px;vertical-align:-2px;
  background-color:currentColor;
  -webkit-mask:var(--ico) center/contain no-repeat; mask:var(--ico) center/contain no-repeat}
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
/* The padding carries the iPhone safe-area insets. In the installed PWA the
   page draws under the clock and battery by design (viewport-fit=cover plus
   black-translucent), so a nearly full-height panel puts its close button --
   12px from the panel top -- inside the strip iOS owns: visible, unclickable.
   env() is 0 everywhere else. */
#dhcg-veil{position:fixed;inset:0;z-index:9998;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.86);
  padding:calc(env(safe-area-inset-top,0px) + 20px) 20px calc(env(safe-area-inset-bottom,0px) + 20px)}
#dhcg-veil.on{display:flex}
/* Wider than the metadata needs, because the art is the point: the grid shows
   Fighters at 250px and this is the only place one is seen large. The art
   column takes the larger share and is capped to the panel height so a short
   window scrolls the trait list rather than the character. */
/* 100% not 90vh: vh ignores the veil's padding, so the panel could still
   grow back up into the inset the padding just cleared. */
#dhcg-panel{width:min(1180px,100%);max-height:100%;overflow:auto;background:var(--panel,#0a1929);
  border:1px solid var(--line,#1b3346);border-radius:4px;display:grid;
  grid-template-columns:minmax(0,1.35fr) minmax(0,1fr)}
@media (max-width:760px){#dhcg-panel{grid-template-columns:1fr}}
#dhcg-panel .big{position:relative;aspect-ratio:1;max-height:100%;background:var(--panel2,#0d1e2e)}
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
/* Only ever shown on your own Fighters -- see the note above open(). */
#dhcg-get{display:none;align-items:center;gap:7px;margin:0 0 14px;text-decoration:none;
  border:1px solid var(--ochre,#00c8a0);color:var(--ochre,#00c8a0);font-size:10px;
  letter-spacing:.1em;text-transform:uppercase;padding:7px 12px;border-radius:2px}
#dhcg-get.on{display:inline-flex}
#dhcg-get:hover{background:var(--ochre,#00c8a0);color:var(--ink,#07111d)}
#dhcg-get small{letter-spacing:0;text-transform:none;opacity:.7;font-size:10px}
.dhcg-acts{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.dhcg-acts #dhcg-get{margin:0}
/* Same shape as the download beside it -- they are the two things you do
   with a Fighter you own, and one looking like a button while the other
   looks like a link would imply one of them is the real one. */
#dhcg-share{display:none;align-items:center;gap:7px;text-decoration:none;
  font-size:11px;letter-spacing:.08em;text-transform:uppercase;
  border:1px solid var(--ochre,#00c8a0);color:var(--ochre,#00c8a0);padding:7px 12px}
#dhcg-share.on{display:inline-flex}
#dhcg-share:hover{background:var(--ochre,#00c8a0);color:var(--ink,#07111d)}
/* The four ranks, ahead of the raw stats: where it places is the headline,
   what it is made of is the detail. */
#dhcg-rank{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;align-items:flex-end}
#dhcg-rank div{border:1px solid var(--line,#1b3346);padding:6px 10px;min-width:74px}
#dhcg-rank b{display:block;font-size:16px;font-variant-numeric:tabular-nums;color:var(--ochre,#00c8a0)}
#dhcg-rank span{font-size:8.5px;letter-spacing:.1em;text-transform:uppercase;opacity:.55}
#dhcg-rank .of{border:0;padding:0 0 6px;font-size:9.5px;letter-spacing:.08em;
  text-transform:uppercase;opacity:.45;min-width:0}
#dhcg-close{position:absolute;right:14px;top:12px;background:none;border:1px solid var(--line,#1b3346);
  color:var(--dim,#7a9eb0);font:inherit;font-size:10px;letter-spacing:.1em;text-transform:uppercase;
  padding:5px 10px;border-radius:2px;cursor:pointer}
#dhcg-close:hover{border-color:var(--ochre,#00c8a0);color:var(--ochre,#00c8a0)}
</style>

<div class="dhcg-wrap">

<?php $dhcnav_at = 'collection'; $dhcnav_guest = $dhcg_guest; include 'dhc-nav.php'; ?>

  <div class="dhcg-head">
    <h1>The Collection</h1>
    <span class="sub">Every Fighter assembled on Skulliance &middot; art by Maxingo</span>
  </div>
  <p class="dhcg-note">
    <?php echo number_format($dhcg_total); ?> assembled so far.
    These are platform features, <b>not NFTs</b> &mdash; they cannot be minted and nobody owns the
    artwork.
    <?php /* dhcfighters.php is behind the gate this page just stepped around, so
             for a visitor that link is an error page, not an invitation. */ ?>
    <?php if ($dhcg_guest): ?>
      <a href="index.php" style="color:var(--ochre,#00c8a0)">Sign in</a> to build your own,
      or <a href="dhcarena.php" style="color:var(--ochre,#00c8a0)">play the Arena</a> right now
      &mdash; no account needed.
    <?php else: ?>
      Build your own in <a href="dhcfighters.php" style="color:var(--ochre,#00c8a0)">DHC Fighters</a>.
    <?php endif; ?>
  </p>

  <?php
    $tiers = array('mythic'=>'Mythic','legendary'=>'Legendary','epic'=>'Epic',
                   'uncommon'=>'Uncommon','common'=>'Common');
    /* The four stat axes and their labels come from dhcf_rank_axes(), so the
       button here and the chip on the assembler canvas cannot end up calling
       the same number two different things. The rest are orderings of the
       table, not axes, and stay local. */
    $sorts = dhcf_rank_axes() + array('newest'=>'Newest','oldest'=>'Oldest',
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
    <?php /* The weapon is the only trait that changes HOW a Fighter fights, so
             it is the one worth filtering on -- "show me every Fighter of mine
             that hits a whole rank" is a question the collection could not
             answer before. */ ?>
    <span class="lbl">Fights like</span>
    <a class="<?php echo $dhcg_kit === '' ? 'on' : ''; ?>"
       href="<?php echo htmlspecialchars(dhcg_url(array('kit' => ''))); ?>">Any</a>
    <?php foreach ($dhcg_kits as $kid => $kit):
            if (empty($dhcg_kit_counts[$kid])) continue; ?>
      <a class="<?php echo $dhcg_kit === $kid ? 'on' : ''; ?>"
         title="<?php echo htmlspecialchars($kit['note']); ?>"
         href="<?php echo htmlspecialchars(dhcg_url(array('kit' => $kid))); ?>"><i
         class="dhcg-ico" style="--ico:url(icons/<?php echo htmlspecialchars($kit['icon']); ?>.png)"></i> <?php
         echo htmlspecialchars($kit['name']) . ' ' . $dhcg_kit_counts[$kid]; ?></a>
    <?php endforeach; ?>

    <span class="sep"></span>
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
        'hp'      => (int)$f['hp'],
        'pow'     => (int)$f['pow'],
        'crit'    => round($f['crit'] * 100),
        'resist'  => round($f['resist'] * 100),
        'assist'  => round($f['assist'] * 100),
        'charge'  => round($f['charge'], 2),
        'roles'   => $f['roles'],
        'kit'     => $f['kit']['emoji'] . ' ' . $f['kit']['name'] . ' — ' . $f['kit']['note'],
        'rank'    => $f['rank'],
        'rankOf'  => (int)$f['rankOf'],
        'created' => $f['created_at'],
        'parts'   => $f['parts'],
        'layers'  => array(),
      );
      // same draw order everything else uses, so a card cannot disagree with
      // the canvas or the Discord render
      // dhcf_layers() decides the armless-torso swap and the single-sided-arm
      // restore layer, so a card agrees with the canvas and the Discord render.
      // Category and slug rather than a finished path: the card wants 250px and
      // the detail view the 1000px master, and size is the only difference.
      foreach (dhcf_layers($f['traits'], __DIR__ . '/' . $dhc_base) as $L) {
        $card['layers'][] = array(
          'c' => $L['cat'], 's' => $L['slug'], 'n' => $L['nudge'],
          'k' => $L['clip'],
        );
      }
    ?>
    <button type="button" class="dhcg-card" data-f="<?php echo htmlspecialchars(json_encode($card), ENT_QUOTES); ?>">
      <div class="dhcg-art">
        <?php foreach ($card['layers'] as $l):
                $st = trim(($l['n'] ? 'transform:translateY(' . $l['n'] . '%);' : '')
                           . dhcf_layer_clip_css($l['k'])); ?>
          <img class="layer" loading="lazy" alt=""
               <?php if ($st): ?>style="<?php echo $st; ?>"<?php endif; ?>
               src="<?php echo htmlspecialchars($dhc_base . '/250/' . $l['c'] . '/' . $l['s'] . '.png'); ?>">
        <?php endforeach; ?>
      </div>
      <div class="dhcg-meta">
        <span class="dhcg-nm"><?php echo htmlspecialchars($f['display']); ?></span>
        <span class="dhcg-pts"><?php echo number_format((int)$f['rarity_score']); ?></span>
      </div>
      <?php /* What it can DO, beside what it is worth. A collection that only
               showed rarity score was answering the wrong question for anybody
               picking a Crew. */ ?>
      <div class="dhcg-stats" title="<?php echo htmlspecialchars(
             $f['kit']['name'] . ' — ' . $f['kit']['note']
             . ' · ' . $f['hp'] . ' health, ' . $f['pow'] . ' power'); ?>">
        <span class="s-hp">♥ <?php echo (int)$f['hp']; ?></span>
        <span class="s-pw">⚔ <?php echo (int)$f['pow']; ?></span>
        <span class="s-kit"><i class="dhcg-ico" style="--ico:url(icons/<?php
          echo htmlspecialchars($f['kit']['icon']); ?>.png)"></i> <?php
          echo htmlspecialchars($f['kit']['name']); ?></span>
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
      <div class="dhcg-acts">
        <a id="dhcg-get" href="#" download>&#8595; Full size <small>1000px PNG</small></a>
        <?php /* target=_blank: the composer must not replace the Collection
                 the player is still browsing. */ ?>
        <a id="dhcg-share" href="#" target="_blank" rel="noopener">&#120143; Share</a>
      </div>
      <div id="dhcg-rank"></div>
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

  /*
   * THE DOWNLOAD BUTTON IS SHOWN ON YOUR OWN FIGHTERS ONLY.
   *
   * This page is public and most of what it shows belongs to somebody else. A
   * full-size, flattened, named picture of an assembly is the owner's to hand
   * out, not a stranger's to take -- it shipped ungated for a day on the
   * argument that the layers below are public anyway, and that argument was
   * wrong. But a player browsing the Collection and finding their own Fighter
   * should not have to go back to the workshop for it.
   *
   * ME is 0 for a signed-out visitor, so the `> 0` matters: without it every
   * guest would match every Fighter whose ownerId came back as 0.
   * dhc-download.php checks ownership again in its WHERE clause regardless --
   * this only decides whether to offer the button.
   */
  var ME = <?php echo (int)$dhcg_user; ?>;

  /* The four axes, in the order dhcf_rank_axes() defines them, so the strip
     and the share sentence cannot disagree with the assembler's preview. */
  var AXES = <?php
    $ax = array();
    foreach (dhcf_rank_axes() as $k => $l) $ax[] = array($k, $l);
    echo json_encode($ax);
  ?>;

  /*
   * THE SHARE SENTENCE.
   *
   * Written here rather than through db.php's shareOnXButton() because this
   * one is built in the browser from whichever Fighter is open, and because
   * the interesting claim is the RANK, not the score -- "#4 deadliest of 108"
   * says something a stranger can weigh, where "6,846" says nothing at all.
   * Only the two best placements are named: four of them reads as a stat
   * dump, and X truncates anyway.
   *
   * X counts every URL as 23 characters whatever its length, which is what
   * the budget below accounts for -- the same arithmetic shareOnXUrl() does
   * server-side.
   */
  var X_HANDLE = '@skulliance';

  function shareText(f) {
    var best = AXES.map(function (a) { return { label: a[1], rank: f.rank[a[0]] }; })
                   .sort(function (x, y) { return x.rank - y.rank; })
                   .slice(0, 2)
                   .map(function (r) { return '#' + r.rank.toLocaleString() + ' ' + r.label.toLowerCase(); });

    var body = f.name + ' - ' + best.join(', ') + ' of ' +
               f.rankOf.toLocaleString() + ' DHC Fighters.';
    /* What it is made of, only if it fits. */
    var tail = ' ' + f.pow + ' power, ' + f.hp + ' health, assembled from ' +
               f.parts.length + ' earned traits.';
    var limit = 280 - 24 - ('\n\n' + X_HANDLE).length;
    if ((body + tail).length <= limit) body += tail;
    if (body.length > limit) body = body.slice(0, limit - 1).replace(/\s+\S*$/, '') + '…';
    return body + '\n\n' + X_HANDLE;
  }

  function shareHref(f) {
    /* The link names the Fighter so the post lands on it, not on the front
       of the Collection. */
    var url = 'https://skulliance.io/staking/dhcgallery.php?fighter=' + f.serial;
    return 'https://x.com/intent/post?text=' + encodeURIComponent(shareText(f)) +
           '&url=' + encodeURIComponent(url);
  }

  function open(f) {
    // The same layer list the card used, so this is the card at a larger size
    // rather than a second opinion about draw order -- but pointed at the
    // 1000px masters. This is the only place a Fighter is shown big enough for
    // the detail in Maxingo's art to be worth the bytes.
    big.innerHTML = f.layers.map(function (l) {
      var st = (l.n ? 'transform:translateY(' + l.n + '%);' : '') +
               (l.k === 'right' ? 'clip-path:inset(0 0 0 <?php echo (int)DHCF_ONE_ARM_SPLIT; ?>%)'
                : l.k === 'left' ? 'clip-path:inset(0 <?php echo 100 - (int)DHCF_ONE_ARM_SPLIT; ?>% 0 0)' : '');
      return '<img alt="" src="' + esc(BASE + '/1000/' + l.c + '/' + l.s + '.png') + '"' +
             (st ? ' style="' + st + '"' : '') + '>';
    }).join('');

    document.getElementById('dhcg-name').textContent = f.name;
    document.getElementById('dhcg-by').innerHTML =
      '<img alt="" src="' + esc(f.avatar) + '"> assembled by ' + esc(f.owner) +
      ' · <a href="dhcgallery.php?owner=' + f.ownerId + '" style="color:var(--ochre,#00c8a0)">see their Fighters</a>';

    var get = document.getElementById('dhcg-get');
    var mine = ME > 0 && f.ownerId === ME;
    get.classList.toggle('on', mine);
    get.href = mine ? 'dhc-download.php?serial=' + f.serial : '#';

    /* SHARE IS OWNER-ONLY, the same rule the download follows and for the
       same reason: posting somebody else's assembly as the thing you built
       is not a share, and the composer would prefill it in their voice. */
    var share = document.getElementById('dhcg-share');
    share.classList.toggle('on', mine);
    share.href = mine ? shareHref(f) : '#';

    /* The URL names the Fighter, so a share lands on it rather than on the
       front of the collection. replaceState, not pushState: opening a card
       should not add a history entry that Back then has to walk through. */
    if (f.serial) {
      try { history.replaceState(null, '', 'dhcgallery.php?fighter=' + f.serial); } catch (e) {}
    }

    var made = (f.created || '').replace(' ', ' · ').slice(0, 16);
    /* Rarity score first because it is what the board ranks on, then what the
       Arena will actually get: health from the torso, power from the weapon,
       and the fighting style the weapon decides. The two answer different
       questions and a collection should not imply they are the same one. */
    document.getElementById('dhcg-stat').innerHTML =
      '<div><b>' + f.score.toLocaleString() + '</b><span>Rarity score</span></div>' +
      '<div><b>' + f.hp + '</b><span>Health</span></div>' +
      '<div><b>' + f.pow + '</b><span>Power</span></div>' +
      '<div><b>' + f.crit + '%</b><span>Crit chance</span></div>' +
      (f.resist > 0 ? '<div><b>' + f.resist + '%</b><span>Damage resisted</span></div>' : '') +
      (f.assist > 0 ? '<div><b>' + f.assist + '%</b><span>Companion assist</span></div>' : '') +
      (f.charge > 1 ? '<div><b>&times;' + f.charge + '</b><span>Charge rate</span></div>' : '') +
      '<div><b style="font-size:11px">' + esc(f.kit.split(' — ')[0]) + '</b><span>In the Arena</span></div>' +
      '<div><b>' + f.parts.length + '</b><span>Traits</span></div>' +
      '<div><b>DHC2F' + f.serial + '</b><span>Number</span></div>' +
      '<div><b style="font-size:11px">' + esc(made) + '</b><span>Assembled</span></div>';

    /* WHERE IT PLACES, which is the thing worth saying out loud about a
       Fighter and the thing a share is built from. Same four axes and the
       same pool the assembler previews against, so a Fighter does not rank
       one way on the canvas and another here. */
    document.getElementById('dhcg-rank').innerHTML = f.rank
      ? AXES.map(function (a) {
          return '<div><b>#' + f.rank[a[0]].toLocaleString() + '</b><span>' + a[1] + '</span></div>';
        }).join('') + '<div class="of">of ' + f.rankOf.toLocaleString() + ' Fighters</div>'
      : '';

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

  function close() {
    veil.classList.remove('on');
    /* Drop the ?fighter= again so a refresh or a copied URL from here is the
       Collection, not whichever card happened to be open last. */
    try {
      var u = new URL(window.location.href);
      if (u.searchParams.has('fighter')) {
        u.searchParams.delete('fighter');
        history.replaceState(null, '', u.pathname + (u.search || '') + u.hash);
      }
    } catch (e) {}
  }

  /*
   * ?fighter=SERIAL opens that card on load, which is what makes a shared
   * link worth clicking. Reads the cards already on the page rather than
   * fetching: if the Fighter is not in the current filter or page of
   * results, there is nothing to open and the Collection is still a
   * reasonable place to land.
   */
  (function () {
    var want = new URLSearchParams(window.location.search).get('fighter');
    if (!want) return;
    var cards = document.querySelectorAll('.dhcg-card');
    for (var i = 0; i < cards.length; i++) {
      var data;
      try { data = JSON.parse(cards[i].getAttribute('data-f')); } catch (e) { continue; }
      if (String(data.serial) === String(want)) { open(data); break; }
    }
  })();
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

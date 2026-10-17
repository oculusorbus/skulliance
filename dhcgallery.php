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
/* dhca_performance(): what the Arena ledger demonstrates, as against what a
   Fighter's traits claim. The engine alone does not carry it. */
require_once __DIR__ . '/dhcarena-lib.php';
/* One definition of Rarest / Deadliest / Toughest / Hardest hitting, shared
   with the assembler's live rank preview. */
require_once __DIR__ . '/dhc-ranks.php';
require_once __DIR__ . '/dhc-type.php';   // one typeface for every DHC surface

$dhcg_user = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

/* ---- controls ---- */
$dhcg_sort  = isset($_GET['sort'])  ? preg_replace('/[^a-z]/', '', $_GET['sort'])  : 'rarest';
$dhcg_tier  = isset($_GET['tier'])  ? preg_replace('/[^a-z]/', '', $_GET['tier'])  : '';
$dhcg_owner = isset($_GET['owner']) ? (int)$_GET['owner'] : 0;
$dhcg_mine  = !empty($_GET['mine']);
$dhcg_kit   = isset($_GET['kit']) ? preg_replace('/[^a-z]/', '', $_GET['kit']) : '';
/*
 * ?trait=<category>:<slug> -- CATEGORY AND SLUG, NOT SLUG ALONE.
 *
 * Nine slugs are both a head and a torso (dh-alien-punk, golden-cyborg,
 * silver-cyborg, mk100, mk200, c73, dh-cop-2, inferno-wasp-guardian,
 * planet-8tz-specter). They are different traits with different art, different
 * rarity and separate ledger rows, so "filter by mk100" is not a question --
 * dhcf_committed() keys on "category|slug" for the same reason. A slug-only
 * filter would quietly pool two traits and report a count belonging to
 * neither.
 */
$dhcg_trait = isset($_GET['trait']) ? dhcf_trait_filter($_GET['trait']) : '';

/*
 * ?perf=<attack|defend|proven> -- filters on what a Fighter has DONE, where
 * tier, kit and trait all filter on what it is.
 *
 * `attack` and `defend` are not "has attacked" -- every fielded Fighter has
 * done both. They mean the ledger shows this Fighter measurably BETTER on
 * that side of the board: dhca_performance_score() only sets `edge` when both
 * records clear DHCA_PROFILE_MIN and the win rates differ by DHCA_EDGE_PCT or
 * more, so an even Fighter is in neither bucket and an unproven one is in no
 * bucket at all.
 */
$dhcg_perf_f = isset($_GET['perf']) ? preg_replace('/[^a-z]/', '', $_GET['perf']) : '';
if (!in_array($dhcg_perf_f, array('attack', 'defend', 'proven'), true)) $dhcg_perf_f = '';

$order = 'f.rarity_score DESC, f.created_at DESC';
if ($dhcg_sort === 'newest')  $order = 'f.created_at DESC';
if ($dhcg_sort === 'oldest')  $order = 'f.created_at ASC';
if ($dhcg_sort === 'serial')  $order = 'f.serial ASC';

$where = array('f.disassembled_at IS NULL');
if ($dhcg_owner) $where[] = 'f.user_id = ' . $dhcg_owner;
if ($dhcg_mine && $dhcg_user) $where[] = 'f.user_id = ' . $dhcg_user;

/* The Arena record is LEFT joined: a Fighter that has never been fielded has
   no row in dhc_arena_fighters at all, and must still appear in its owner's
   Collection. wins/losses are career totals and the schema marks them public.
   COALESCE so the payload is always two integers rather than two nulls. */
$sql = "SELECT f.*, u.username, u.discord_id, u.avatar,
               COALESCE(af.wins, 0) AS arena_wins, COALESCE(af.losses, 0) AS arena_losses
        FROM dhc_fighters f
        INNER JOIN users u ON u.id = f.user_id
        LEFT JOIN dhc_arena_fighters af ON af.fighter_id = f.id
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

/*
 * AND THE SAME THING FROM THE LEDGER. Two reads for the whole page, like the
 * rank pool above and for the same reason -- the Collection ranks every
 * Fighter against every other, so this cannot be a query per row.
 *
 * $dhcg_ppool counts only Fighters that have fought DHCA_PROFILE_MIN battles,
 * so its _n is smaller than $dhcg_pool['_n'] and the two denominators must
 * not be swapped: "#3 of 108" and "#3 of 31" are different claims.
 */
$dhcg_perf  = dhca_performance($conn);
$dhcg_ppool = dhca_performance_pool($dhcg_perf);

/* How many Fighters each builder has, for the "see their N Fighters" link in
   the detail panel. Its own query rather than a tally of $dhcg_rows: those
   are whatever this page is filtered and paged to, so counting them would
   say "see their 3 Fighters" to someone looking at a tier filter when the
   builder has thirty. One grouped read for the whole page. */
$dhcg_counts = array();
$cq = $conn->query("SELECT user_id, COUNT(*) AS n FROM dhc_fighters
                    WHERE disassembled_at IS NULL GROUP BY user_id");
if ($cq) while ($cr = $cq->fetch_assoc()) $dhcg_counts[(int)$cr['user_id']] = (int)$cr['n'];

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

		/* WHERE THE LEDGER PUTS IT, on the three axes that have evidence.
		   Absent entirely for a Fighter that has not fought enough -- an
		   unproven Fighter has no proven rank, and filling one in with its
		   position among four battles would be the one number on this page
		   that is not true. */
		$row['perf']  = isset($dhcg_perf[(int)$row['id']]) ? $dhcg_perf[(int)$row['id']] : null;
		$row['prank'] = array();
		if ($row['perf'] && $row['perf']['all']['proven']) {
			foreach (dhca_proven_axes() as $pk => $pmeta) {
				$row['prank'][$pk] = dhcf_rank_of($dhcg_ppool[$pk], $row['perf']['all'][$pk]);
			}
		}
		$row['prankOf'] = (int)$dhcg_ppool['_n'];

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

/*
 * How many Fighters each record filter would actually show.
 *
 * THESE DOUBLE AS THE SWITCH FOR THE WHOLE FEATURE. Before the first battle is
 * logged every count is zero, and a sort button that cannot order anything or
 * a chip that leads to an empty grid is worse than no button: it implies the
 * page is broken rather than that the Arena is new. So the proven sorts and
 * the record chips are drawn only where there is something to drive them, and
 * they appear on their own as the ledger fills up.
 */
$dhcg_pcounts = array('proven' => 0, 'attack' => 0, 'defend' => 0);
foreach ($dhcg_all as $r) {
	if (empty($r['perf']['all']['proven'])) continue;
	$dhcg_pcounts['proven']++;
	if ($r['perf']['edge'] === 'attack') $dhcg_pcounts['attack']++;
	if ($r['perf']['edge'] === 'defend') $dhcg_pcounts['defend']++;
}

/*
 * THE CLAIMED AXES, RANKED AMONG THE FIGHTERS THAT HAVE FOUGHT -- and this is
 * the whole reason the comparison is trustworthy.
 *
 * "#4 deadliest proven, #19 deadliest claimed" is not a sentence about one
 * Fighter, because those two numbers have different denominators: the proven
 * rank is out of the few dozen Fighters with a real record, the claimed rank
 * is out of every Fighter ever assembled. #4 of 31 and #19 of 108 are nearly
 * the same standing, so printing them side by side would manufacture a
 * disagreement out of arithmetic.
 *
 * So the claim is re-ranked over exactly the population the evidence covers.
 * Both numbers are then out of the same _n, a gap between them is a real
 * finding, and "it is ranked 2nd toughest of the Fighters that have fought,
 * but only 14th by what actually survived" means what it appears to mean.
 *
 * hp/pow/might are the same three values dhcf_rank_values() returns for
 * tough/power/might, already on the row from the loop above.
 */
/*
 * hp/pow/might are the same three values dhcf_rank_values() returns for
 * tough/power/might, so this page hands them straight to dhca_claim_pool()
 * rather than letting it re-query and re-build all 226 Fighters. The pool and
 * the ranking themselves live in dhcarena-lib.php, because ajax/dhc-fighter.php
 * serves the SAME panel for one Fighter and the two must not drift: a gap
 * computed one way here and another way there is the one thing that would
 * make the whole comparison untrustworthy.
 */
$dhcg_known = array();
foreach ($dhcg_all as $r) {
	$dhcg_known[(int)$r['id']] = array(
		'tough' => $r['hp'], 'power' => $r['pow'], 'might' => $r['might'],
	);
}
$dhcg_cpool = dhca_claim_pool($conn, $dhcg_perf, $dhcg_known);
foreach ($dhcg_all as $i => $r) {
	$dhcg_all[$i]['crank'] = dhca_claim_ranks(
		$dhcg_cpool,
		array('tough' => $r['hp'], 'power' => $r['pow'], 'might' => $r['might']),
		!empty($r['perf']['all']['proven']));
}

$dhcg_total = count($dhcg_all);
$dhcg_rows  = array_values(array_filter($dhcg_all, function ($r) use ($dhcg_tier, $dhcg_kit, $dhcg_trait, $dhcg_perf_f) {
	if ($dhcg_tier !== '' && $r['best'] !== $dhcg_tier) return false;
	if ($dhcg_kit !== '' && $r['kit']['id'] !== $dhcg_kit) return false;
	/* In PHP like tier and kit, and for the same reason: the traits live in a
	   JSON column. The rule itself is in the library so it can be driven
	   without a database -- see dhcf_traits_include(). */
	if (!dhcf_traits_include($r['parts'], $dhcg_trait)) return false;
	/* On the record, not on the build. A Fighter with no ledger row fails all
	   three of these, which is correct: it has demonstrated nothing. */
	if ($dhcg_perf_f === 'proven' && empty($r['perf']['all']['proven'])) return false;
	if ($dhcg_perf_f === 'attack' && (!$r['perf'] || $r['perf']['edge'] !== 'attack')) return false;
	if ($dhcg_perf_f === 'defend' && (!$r['perf'] || $r['perf']['edge'] !== 'defend')) return false;
	return true;
}));
if ($dhcg_sort === 'traits') {
	usort($dhcg_rows, function ($a, $b) { return count($b['parts']) <=> count($a['parts']); });
}
/* Sorted in PHP like the others: these come out of the engine, not the table. */
if ($dhcg_sort === 'tough')  usort($dhcg_rows, function($a,$b){ return $b['hp']    <=> $a['hp']; });
if ($dhcg_sort === 'power')  usort($dhcg_rows, function($a,$b){ return $b['pow']   <=> $a['pow']; });
if ($dhcg_sort === 'might')  usort($dhcg_rows, function($a,$b){ return $b['might'] <=> $a['might']; });

/*
 * THE PROVEN AXES, sorted here for the same reason as the three above: they
 * are not columns. Keys are the claimed key with a p- prefix (p-real tests
 * might, p-live tests tough, p-dmg tests power) and ?sort= strips everything
 * but a-z, so they arrive as preal/plive/pdmg.
 *
 * UNPROVEN FIGHTERS SORT LAST AS A BLOCK, never interleaved. Treating "no
 * evidence" as a zero would bury a Fighter nobody has fielded below one that
 * has demonstrably never landed a hit, and those are not the same finding.
 * Within the block the order is left alone, so it keeps whatever the SQL
 * ORDER BY gave it rather than inventing a ranking from no data.
 */
$dhcg_paxis = array('preal' => 'real', 'plive' => 'live', 'pdmg' => 'dmg');
if (isset($dhcg_paxis[$dhcg_sort])) {
	$pa = $dhcg_paxis[$dhcg_sort];
	usort($dhcg_rows, function ($a, $b) use ($pa) {
		$ap = !empty($a['perf']['all']['proven']);
		$bp = !empty($b['perf']['all']['proven']);
		if ($ap !== $bp) return $bp <=> $ap;          // proven ahead of unproven
		if (!$ap) return 0;
		return $b['perf']['all'][$pa] <=> $a['perf']['all'][$pa];
	});
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
	/*
	 * $conn->query(), NOT prepare()+get_result().
	 *
	 * mysqli_stmt::get_result() needs mysqlnd, and this server's mysqli is
	 * not built against it -- the call is simply undefined there. It is a
	 * fatal, not a warning, and php -l cannot see it: the method exists in
	 * the class as far as the parser is concerned. This shipped and every
	 * shared ?fighter= link died on it.
	 *
	 * The whole platform uses $conn->query() with the value cast or escaped,
	 * 544 times in db.php alone, for exactly this reason. A serial is an
	 * int, and (int) is the cast that makes it safe.
	 */
	$fs = (int)$_GET['fighter'];
	$fq = $conn->query("SELECT f.serial, f.name, f.rarity_score, f.traits, u.username
	                    FROM dhc_fighters f
	                    LEFT JOIN users u ON u.id = f.user_id
	                    WHERE f.serial = $fs AND f.disassembled_at IS NULL LIMIT 1");
	if ($fq) {
		$dhcg_one = $fq->fetch_assoc() ?: null;
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

<?php dhc_type_styles();   /* one typeface for every DHC surface */ ?>
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
/* The slot, inside the active trait chip. The chip's own colour is the ochre
   fill, so this cannot be dimmed with opacity against the dark palette the way
   the bar's other secondary text is -- it sits on the fill, not on the page. */
.dhcg-bar .dhcg-chipcat{font-size:8.5px;letter-spacing:.14em;opacity:.62;
  padding-right:5px;margin-right:1px;border-right:1px solid currentColor}
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
/* The Fighter detail panel -- its CSS, markup and behaviour are all in
   dhc-fighter-modal.php now, shared with the assembler. */
</style>

<div class="dhcg-wrap">

<?php $dhcnav_at = 'collection'; $dhcnav_guest = $dhcg_guest; include 'dhc-nav.php'; ?>

  <div class="dhcg-head">
    <h1>The Collection</h1>
    <span class="sub">Every Fighter assembled on Skulliance &middot; art by <?php echo dhcf_artist_link(); ?></span>
  </div>
  <p class="dhcg-note">
    <?php echo number_format($dhcg_total); ?> assembled so far.
    <?php /* "Maxingo owns the artwork", not "nobody owns" it. The old wording
             read as a claim that the art is unowned, which is both untrue and
             the opposite of what this notice is for -- the point is that
             assembling a Fighter here gives you no claim on it, BECAUSE
             somebody already holds it. */ ?>
    These are platform features, <b>not NFTs</b> &mdash; they cannot be minted and
    <?php echo dhcf_artist_link(); ?> owns the artwork.
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

    <?php
      /*
       * THE SAME THREE CLAIMS, SETTLED BY THE LEDGER. Deadliest, Toughest and
       * Hardest hitting appear twice in this bar on purpose: once above, where
       * they are computed from the traits a Fighter is wearing, and once here,
       * where they are computed from what it actually did in the Arena. The
       * labels match deliberately -- the interesting thing about this feature
       * is the disagreement, and renaming the proven axis to something else
       * would hide it.
       *
       * dhca_proven_axes() carries which claim each one tests, so the title
       * attribute cannot drift from the arithmetic in dhcarena-lib.php.
       */
      if ($dhcg_pcounts['proven']):
        $claimed = dhcf_rank_axes(); ?>
      <span class="sep"></span>
      <span class="lbl" title="Ranked on the Arena ledger instead of on traits">Proven</span>
      <?php foreach (dhca_proven_axes() as $pk => $pm): ?>
        <a class="<?php echo $dhcg_sort === 'p' . $pk ? 'on' : ''; ?>"
           title="<?php echo htmlspecialchars('Who the battle record says is really the '
                  . strtolower($pm['label']) . ', measured in ' . $pm['unit']
                  . '. Compare with ' . strtolower($claimed[$pm['claim']])
                  . ', which is read off traits.'); ?>"
           href="<?php echo htmlspecialchars(dhcg_url(array('sort' => 'p' . $pk))); ?>"><?php
           echo htmlspecialchars($pm['label']); ?></a>
      <?php endforeach; ?>
    <?php endif; ?>

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

    <?php
      /* OFFENCE AND DEFENCE AS A FILTER, which is a different question from
         either sort above: not "who is best" but "which side of the board is
         this Fighter better on". A chip is drawn only when it would show
         something, so an Arena where nobody has yet proven a lopsided record
         shows no Attackers chip rather than an empty one. */
      if ($dhcg_pcounts['proven']): ?>
      <span class="sep"></span>
      <span class="lbl">Record</span>
      <a class="<?php echo $dhcg_perf_f === '' ? 'on' : ''; ?>"
         href="<?php echo htmlspecialchars(dhcg_url(array('perf' => ''))); ?>">Any</a>
      <a class="<?php echo $dhcg_perf_f === 'proven' ? 'on' : ''; ?>"
         title="<?php echo DHCA_PROFILE_MIN; ?>+ battles fought, so its rates mean something"
         href="<?php echo htmlspecialchars(dhcg_url(array('perf' => 'proven'))); ?>">Proven <?php
         echo $dhcg_pcounts['proven']; ?></a>
      <?php if ($dhcg_pcounts['attack']): ?>
        <a class="<?php echo $dhcg_perf_f === 'attack' ? 'on' : ''; ?>"
           title="Wins at least <?php echo DHCA_EDGE_PCT; ?> points more often attacking than defending"
           href="<?php echo htmlspecialchars(dhcg_url(array('perf' => 'attack'))); ?>">Attackers <?php
           echo $dhcg_pcounts['attack']; ?></a>
      <?php endif; ?>
      <?php if ($dhcg_pcounts['defend']): ?>
        <a class="<?php echo $dhcg_perf_f === 'defend' ? 'on' : ''; ?>"
           title="Wins at least <?php echo DHCA_EDGE_PCT; ?> points more often defending than attacking"
           href="<?php echo htmlspecialchars(dhcg_url(array('perf' => 'defend'))); ?>">Defenders <?php
           echo $dhcg_pcounts['defend']; ?></a>
      <?php endif; ?>
    <?php endif; ?>

    <span class="sep"></span>
    <?php if ($dhcg_user): ?>
      <a class="<?php echo $dhcg_mine ? 'on' : ''; ?>"
         href="<?php echo htmlspecialchars(dhcg_url(array('mine' => $dhcg_mine ? '' : 1, 'owner' => ''))); ?>">Mine</a>
    <?php endif; ?>
    <?php if ($dhcg_owner && isset($dhcg_owners[$dhcg_owner])): ?>
      <a class="on" href="<?php echo htmlspecialchars(dhcg_url(array('owner' => ''))); ?>">
        <?php echo htmlspecialchars($dhcg_owners[$dhcg_owner]); ?> &times;</a>
    <?php endif; ?>
    <?php /* The trait chip, cleared the same way the builder chip is. Named by
             its DISPLAY name, not its slug: the slug is a filename and the
             panel the player clicked in said "DH Samurai Helmet". The slot is
             spelled out beside it because nine slugs exist in two categories
             and "MK100" alone would not say which one is being shown. */
          if ($dhcg_trait !== ''):
            list($tcc, $tss) = explode(':', $dhcg_trait, 2); ?>
      <a class="on" href="<?php echo htmlspecialchars(dhcg_url(array('trait' => ''))); ?>"
         title="Showing every Fighter wearing this trait">
        <span class="dhcg-chipcat"><?php echo htmlspecialchars($tcc); ?></span>
        <?php echo htmlspecialchars(dhcf_trait_name($tcc, $tss)) . ' ' . count($dhcg_rows); ?> &times;</a>
    <?php endif; ?>
  </div>

  <?php if (!$dhcg_rows): ?>
    <div class="dhcg-empty">
      <?php echo $dhcg_total ? 'No Fighters match those filters.' : 'No Fighters have been assembled yet. Be the first.'; ?>
    </div>
  <?php else: ?>
  <div class="dhcg-grid">
    <?php foreach ($dhcg_rows as $f):
      /* avatar_url(): .jpg 404s on some avatars where .png is fine, and an
         animated one is only served as .gif. This payload feeds the card AND
         the Fighter panel, so a wrong extension here broke both at once. */
      $av = avatar_url($f['discord_id'], $f['avatar']);
      $card = array(
        'name'    => $f['display'],
        'serial'  => (int)$f['serial'],
        'owner'   => $f['username'],
        'ownerId' => (int)$f['user_id'],
        'avatar'  => $av,
        'ownerN'  => isset($dhcg_counts[(int)$f['user_id']]) ? $dhcg_counts[(int)$f['user_id']] : 0,
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
        /* The ledger's verdict travels with the card so the modal can set the
           two side by side without a second request. `prank` is an empty
           array for an unproven Fighter and the modal keys off that. */
        'perf'    => $f['perf'] ? $f['perf']['all'] : null,
        'edge'    => $f['perf'] ? $f['perf']['edge'] : '',
        'prank'   => $f['prank'],
        'prankOf' => (int)$f['prankOf'],
        /* The trait claim re-ranked over the same population, so the modal can
           subtract one from the other honestly. See $dhcg_cpool. */
        'crank'   => $f['crank'],
        'created' => $f['created_at'],
        'aw'      => (int)$f['arena_wins'],
        'al'      => (int)$f['arena_losses'],
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

<?php
  /* The detail panel, shared with dhcfighters.php. This page owns the
     ?fighter= URL, so it is the one that sets $dhcm_deeplink. */
  $dhcm_user = $dhcg_user; $dhcm_base = $dhc_base; $dhcm_deeplink = true;
  include __DIR__ . '/dhc-fighter-modal.php';
?>

<script>
(function () {

  /* esc(), the config, shareText/shareHref, open() and close() all live in
     dhc-fighter-modal.php. What stays here is only how THIS page chooses a
     Fighter to show it. */
  var open = function (f) { window.DHC_MODAL.open(f); };

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

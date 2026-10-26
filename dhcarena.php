<?php
/**
 * dhcarena.php — DHC Arena, and DHC Bosses.
 *
 * TWO INTERFACES SERVED FROM ONE FILE. dhcbosses.php is `$DHC_MODE = 'bosses'`
 * and an include of this file: same board, same client, same Crew picker, and a
 * setup column that branches. See the block above $DHC_MODE for why that shape
 * and not two pages or one page with both panels.
 *
 * The real thing. dhcarena-prototype.php proved the game; this is it wired to
 * the platform: real Fighters, a real Crew, a real opponent, a server that owns
 * every rule, and traits that actually land in the ledger.
 *
 * WHAT THE CLIENT DOES AND DOES NOT DO. It draws the board and animates what it
 * is told. It does NOT decide anything: a move is sent to
 * ajax/dhcarena-action.php as "slide A to B", the engine resolves the whole
 * exchange including the defending Crew's replies, and the reply carries both
 * the new state and an fx TIMELINE to play back. Nothing the browser says can
 * change a number.
 *
 * Design: dhcarena.md. Rules: dhcarena-engine.php. Economy: dhcarena-lib.php.
 * Tables: dhcarena-schema.md, run once by hand.
 */
include_once 'db.php';
include 'message.php';

/*
 * THIS PAGE IS PUBLIC, and deliberately so: practice is the whole game with
 * nothing at stake, it touches no table and needs no account, so a visitor who
 * has never heard of Skulliance can play it from a shared link. That is the
 * only version of this game anyone can be shown without asking them to sign up
 * first, which makes it the one worth promoting.
 *
 * So skulliance.php cannot be included unconditionally -- it redirects an
 * anonymous visitor to error.php, which is exactly the wall this removes. It
 * still runs in full for members, because everything ranked depends on what it
 * does: verify.php defines checkUser(), skulliance.php calls it to resolve
 * user_id into the session, and without that a signed-in player looks like a
 * guest and loses their Crew.
 *
 * The cookie restore is lifted from skulliance.php rather than delegated to it,
 * because the decision has to be made BEFORE including the file that redirects.
 * Merge, never assign -- see skulliance.php's own note on what a raw assign
 * here once did to every other page's session.
 */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$dhca_ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($dhca_ck)) {
		$_SESSION = array_merge((array)$_SESSION, $dhca_ck);
	} else {
		/* Undecodable, so it will be sent again on every request. skulliance.php
		   clears it on the way to error.php; here there is no error page to go
		   to, so clear it and carry on as a guest. */
		setcookie('SessionCookie', '', time() - 3600);
	}
	unset($dhca_ck);
}
$dhca_guest = empty($_SESSION['logged_in']);
if (!$dhca_guest) { include 'verify.php'; include 'skulliance.php'; }

require_once __DIR__ . '/dhcarena-lib.php';

/*
 * ============================================================================
 * TWO INTERFACES, ONE IMPLEMENTATION.
 * ============================================================================
 *
 * dhcbosses.php sets $DHC_MODE = 'bosses' and includes this file. The battle
 * screen, the board, the engine, the client and the Crew picker are shared;
 * only the setup column, the ladder and the buttons differ.
 *
 * WHY NOT A SECOND PAGE WITH ITS OWN COPY: the client below is two thousand
 * lines of board, animation, timeline and end-card. A copy of it would be
 * correct on the day it was made and wrong from the next engine fix onward,
 * which is the same reasoning that made DHC Bosses an override of the Arena
 * rather than a second game.
 *
 * WHY NOT ONE PAGE WITH BOTH PANELS, which is what shipped first: one Crew
 * picker serving two games has to show two cooldowns at once, and no amount
 * of labelling makes that read clearly. Each interface now shows ONE clock,
 * which is the clock for the game you are on.
 */
/* REQUIRED HERE, not down in the setup reads, because the Crew the picker is
   built from is overlaid with the boss cooldown before anything else touches
   it, and because the mode itself depends on it. Optional on purpose: this
   page renders with the library absent, which is what a host looks like
   between a deploy and the migration. */
$dhcb_lib = is_file(__DIR__ . '/dhcbosses-lib.php');
if ($dhcb_lib) require_once __DIR__ . '/dhcbosses-lib.php';

/* THE LIBRARY IS PART OF THE MODE. Boss mode prints DHCB_ constants in the
   masthead and the ladder, so without the library there is no boss interface
   to show and the Arena is the honest answer -- a blank page would not be. */
$DHC_MODE = (isset($DHC_MODE) && $DHC_MODE === 'bosses' && $dhcb_lib) ? 'bosses' : 'arena';
$IS_BOSS  = ($DHC_MODE === 'bosses');

/*
 * SHAREABLE, which is the point of being public. Without these a link to the
 * Arena posted anywhere renders as a bare URL, and the reason the end-card
 * share button was pulled was precisely that nothing could scrape a card off a
 * page behind the login gate. Absolute URLs are correct HERE and only here --
 * canonical and OG are not clickable navigation, so they cannot bounce a
 * visitor between www and the bare host and drop a host-only session cookie.
 *
 * No chain or token wording: this is the public face of the site and the funnel
 * deliberately keeps it about the game.
 *
 * $dhca_og_image is the platform's generic card, verified 200, because there is
 * no Arena screenshot on the server yet -- images ship by FTP, not in the repo.
 * Drop one at images/dhcarena.png and change this one line.
 */
$dhca_canonical = 'https://www.skulliance.io/staking/'
                . ($IS_BOSS ? 'dhcbosses.php' : 'dhcarena.php');
$dhca_og_image  = 'https://www.skulliance.io/staking/images/og.jpg';

/*
 * ?battle=N MAKES THE UNFURL ABOUT THAT MATCH.
 *
 * A shared result carried this page's generic card -- the same picture for
 * every result anybody has ever posted -- so the post said "I won" over a
 * stock image. With a battle named, og:image becomes dhc-arena-card.php,
 * which draws the six Fighters that actually fought with the fallen dimmed.
 *
 * THE PAGE ITSELF IS UNCHANGED. This only rewrites meta: the visitor still
 * lands on the playable Arena, which is the point of sharing it. The
 * canonical stays the bare page too, so every shared match consolidates
 * onto one indexable URL instead of spawning thousands.
 *
 * Validated as an integer and nothing more -- the card endpoint decides
 * whether that battle exists, and a bad number there is a 404, not a page
 * that fails to render.
 */
$dhca_share_battle = isset($_GET['battle']) ? (int)$_GET['battle'] : 0;
if ($dhca_share_battle > 0) {
	$dhca_og_image = 'https://www.skulliance.io/staking/dhc-arena-card.php?battle='
	               . $dhca_share_battle;
}
/*
 * ?run=N DOES THE SAME FOR A BOSS RUN, and it is the same endpoint: your
 * Crew of three against the boss crew, the fallen dimmed. Most runs end
 * with the Crew wiped and the bosses standing, which is precisely the
 * picture worth posting -- it says what the fight is without a word on it.
 *
 * Boss mode only. ?run= on the Arena would claim a boss card for a page
 * about ranked battles, and the canonical above already keeps every shared
 * result consolidated on one indexable URL either way.
 */
$dhca_share_run = ($IS_BOSS && isset($_GET['run'])) ? (int)$_GET['run'] : 0;
if ($dhca_share_run > 0) {
	$dhca_og_image = 'https://www.skulliance.io/staking/dhc-arena-card.php?run='
	               . $dhca_share_run;
}
if ($IS_BOSS) {
	/* ITS OWN CARD, because it is its own game as far as anybody sharing it
	   is concerned. A link to the boss fight that unfurled as "free Crew vs
	   Crew puzzle battler" would describe the wrong fight. */
	$dhca_title = 'DHC Bosses — three bosses, one week, everybody swinging | Skulliance';
	$dhca_desc  = 'Three bosses with one health bar each, shared by every player and '
	            . 'standing for a week. Send a Crew of three, chip the bar down, and the '
	            . 'traits everyone who took part rolls for get better the further the '
	            . 'crew falls. Same board and same rules as DHC Arena.';
	$dhca_short = 'Three bosses, one health bar each, shared by everybody for a week. '
	            . 'Every point of damage counts.';
} else {
	$dhca_title = 'DHC Arena — free Crew vs Crew puzzle battler | Skulliance';
	$dhca_desc  = 'Three Fighters against three on one shared board. Match a Fighter\'s '
	            . 'gem to make them act, and match size is reach: three touches their '
	            . 'front rank, five or more reaches the back. Free to play in your '
	            . 'browser, no account and no download.';
	$dhca_short = 'Three Fighters against three on one shared board. Match a gem to make '
	            . 'that Fighter act. Free, instant, no account.';
}
$dhca_alt = $IS_BOSS
	? 'DHC Bosses - three bosses standing against crews of three Fighters'
	: 'DHC Arena - a Crew of three Fighters facing three more across a gem board';
$page_title_override = $dhca_title;
$extra_head = '
<meta name="description" content="'.htmlspecialchars($dhca_desc).'">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="'.$dhca_canonical.'">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="'.$dhca_canonical.'">
<meta property="og:title" content="'.htmlspecialchars($dhca_title).'">
<meta property="og:description" content="'.htmlspecialchars($dhca_desc).'">
<meta property="og:image" content="'.$dhca_og_image.'">
<meta property="og:image:alt" content="'.($dhca_share_battle ? 'The two Crews from this DHC Arena match, the fallen Fighters dimmed' : ($dhca_share_run ? 'The Crew sent at the DHC boss crew, the fallen Fighters dimmed' : $dhca_alt)).'">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="'.htmlspecialchars($dhca_title).'">
<meta name="twitter:description" content="'.htmlspecialchars($dhca_short).'">
<meta name="twitter:image" content="'.$dhca_og_image.'">
<meta name="twitter:image:alt" content="'.htmlspecialchars($dhca_alt).'">
';

include 'header.php';

$user_id   = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
/* The picker is shared, so it simply takes whichever roster this page is
   for -- dhcb_roster() is dhca_crew() with the boss cooldown laid over it,
   so every card renders identically and `available` means the right thing. */
$crew      = $user_id ? dhca_crew($conn, $user_id) : array();
if ($IS_BOSS && $user_id && $dhcb_lib) $crew = dhcb_roster($conn, $user_id, $crew);
$available = dhca_available($crew);
/* ARENA-ONLY READS. Each one is a query, and none of them has any meaning
   on the Bosses page -- there are no rivals to pick from, no six-a-day
   allowance and no monthly board over there. Skipping them is most of why
   the Bosses page is the cheaper of the two to render. */
$block     = '';
$spent     = 0;
$foesList  = array();
$standouts = array();
$standTags = array();
$paidToday = array();
$ladder    = array();
$ladderAth = array();
if (!$IS_BOSS) {
$block     = $user_id ? dhca_entry_block($conn, $user_id) : 'Sign in to enter the Arena.';
$spent     = $user_id ? dhca_battles_today($conn, $user_id) : 0;
$foesList  = $user_id ? dhca_opponents($conn, $user_id) : array();

/*
 * THE ARCH NEMESIS, PINNED TO THE FRONT.
 *
 * dhca_opponents() is ORDER BY best DESC LIMIT 24 -- the same two dozen
 * highest-rarity players for everybody -- so the one opponent you actually
 * have a history with is very often not on the screen at all. Highlighting a
 * card that never renders is the whole feature failing silently, so the
 * nemesis is hoisted when present and inserted when not.
 *
 * It is also the first entry in that list chosen by something predictive: the
 * ordering it jumps is by rarity_score, which the comment on dhca_defenders()
 * measures at +0.04 correlation with combat strength.
 */
/*
 * THREE FLAGS, HIGHLIGHTED WHERE THEY STAND.
 *
 * NOT hoisted to the front. The list is ordered by rarity and that order
 * is the one a player reads it in, so pulling three cards out of it to
 * cluster at the top reorders everybody else's position for the sake of
 * a label. A flag is a mark on a rival, not a ranking of its own.
 *
 * BUT A FLAG ON A CARD THAT NEVER RENDERS IS THE WHOLE FEATURE FAILING
 * SILENTLY, and that is why the nemesis was hoisted in the first place:
 * dhca_opponents() is ORDER BY best DESC LIMIT 24, the same two dozen
 * highest-rarity players for everybody, so the person you actually have
 * a history with is very often not on the screen at all. So a flagged
 * rival who is missing is still INSERTED -- just at the place their own
 * rarity puts them rather than at the top, which is where they would
 * have been if the list were not cut at 24.
 *
 * ONE PERSON CAN WEAR TWO, and usually does -- whoever you fight most is
 * whoever you have the most record against. They are merged onto one card
 * rather than listed twice, which is why $standTags is keyed by user id.
 */
$standouts = $user_id ? dhca_standouts($conn, $user_id) : array();
foreach (array('nemesis', 'tormentor', 'duck') as $sk) {
	if (empty($standouts[$sk])) continue;
	$sid = (int)$standouts[$sk]['user_id'];
	if (!isset($standTags[$sid])) $standTags[$sid] = array('row' => $standouts[$sk], 'tags' => array());
	$standTags[$sid]['tags'][] = $sk;
}
foreach ($standTags as $sid => $st) {
	$have = false;
	foreach ($foesList as $i => $o) {
		if ((int)$o['user_id'] !== $sid) continue;
		/* ALREADY THERE: leave the card exactly where it is, but take the
		   row with the history on it -- the ordinary rows carry no record
		   at all, and the chip needs one to print. */
		$foesList[$i] = $st['row'];
		$have = true;
		break;
	}
	if ($have) continue;
	/* Missing from the top 24: slotted in by rarity, so the list still
	   reads in one order. */
	$at = count($foesList);
	foreach ($foesList as $i => $o) {
		if ((int)$st['row']['best'] > (int)$o['best']) { $at = $i; break; }
	}
	array_splice($foesList, $at, 0, array($st['row']));
}
$foesList = array_values($foesList);
/* Which of them have already paid out today -- one query for the whole
   list, the same fact dhca_already_rewarded() checks per battle. */
$paidToday = $user_id ? dhca_paid_today($conn, $user_id) : array();
$ladder    = dhca_ladder($conn, null, 10);
/* AND THE SAME BOARD WITH NO MONTH BOUNDARY. Monthly answers "who is winning
   right now", which resets and is the live race; all-time answers "who has
   ever been good at this", which a new month cannot take away. The Fighters
   page carries both in one panel for exactly this reason and this mirrors it.
   Shorter than monthly on purpose -- it changes slowly, so a long list of it
   is a long list of the same names. */
$ladderAth = dhca_ladder($conn, DHCA_LADDER_ALL_TIME, 10);
} /* !$IS_BOSS */

/*
 * THE BOSSES SIDE OF THE SETUP COLUMN.
 *
 * Read on BOTH pages, because the Arena still carries a one-line card
 * pointing at the fight -- a player on the Arena has to be able to find out
 * a boss week is running. Only the boss page does the expensive reads.
 *
 * Degrades to absent: the library is only required if it is installed, and
 * dhcb_state() returns empty until the migration has been run, which every
 * read below treats as "not switched on yet" rather than as an error.
 */
$dhcb_on = array(); $dhcb_prog = null; $dhcb_block = ''; $dhcb_runs = 0;
$dhcb_slots = array(); $dhcb_lad = array(); $dhcb_days = 0; $dhcb_left = 0; $dhcb_round = 1; $dhcb_feed = array();
if ($dhcb_lib) {
	$dhcb_on = dhcb_state($conn);
	if ($dhcb_on) {
		$dhcb_prog = dhcb_progress($dhcb_on);
		if ($IS_BOSS) {
			$dhcb_block = $user_id ? dhcb_entry_block($conn, $user_id, $crew) : 'Sign in to send a Crew.';
			$dhcb_runs  = $user_id ? dhcb_runs_today($conn, $user_id) : 0;
			$dhcb_slots = $user_id ? dhcb_cache_slots($conn, $user_id) : array();
			/* Which round, and how long is left of it -- one query, because
			   the page shows a live countdown to the next one rather than
			   asking a player to infer an invisible boundary. */
			$dhcb_rd    = dhcb_round($conn);
			$dhcb_left  = $dhcb_rd['left'];
			$dhcb_round = $dhcb_rd['n'];
			$dhcb_days  = $user_id ? dhcb_cache_days($conn, $user_id) : 0;
			$dhcb_lad   = dhcb_ladder($conn, null, 10);
			/* The week's Crews, newest first, for the feed under the panels.
			   Twelve rather than the admin report's 200: this is "what is
			   happening", not an audit, and every row draws three Fighters. */
			$dhcb_feed  = dhcb_runs_report($conn, null, 12);
		}
	}
}


/*
 * WHAT EACH FIGHTER WILL ACTUALLY BE, derived here so the picker can sort and
 * filter on it. Same function the battle uses, so a card cannot promise
 * something the Arena then disagrees with. Rarity score stays on the card too,
 * but it is no longer the only thing a Crew can be chosen on -- which it was,
 * and which is the one number §4 refuses to let decide a fight.
 */
$rarity   = dhcf_rarity();
$kitCount = array();
foreach ($crew as $i => $f) {
	$built = dhca_build_fighter(is_array($f['traits']) ? $f['traits'] : array(),
	                            '', 'p'.$f['id'], $rarity);
	$crew[$i]['hp']   = (int)$built['maxHp'];
	$crew[$i]['pow']  = (int)$built['power'];
	$crew[$i]['crit'] = round($built['critC'] * 100);
	$crew[$i]['kit']  = $built['kit'];
	$kitCount[$built['kit']['id']] = (isset($kitCount[$built['kit']['id']]) ? $kitCount[$built['kit']['id']] : 0) + 1;
}
$allKits = array();
foreach (dhca_kits() as $k) if (!empty($kitCount[$k['id']])) $allKits[] = $k;

/* Art root, resolved the same way the assembler resolves it. */
$ART = '';
foreach (array('dhc/web','web','dhc','traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $ART = $c; break; }
}
?>
<div class="arena-wrap">

<?php /* Build, browse, fight -- one activity across three pages. A guest gets
         the other two pointed at sign-in rather than at error.php. */ ?>
<?php $dhcnav_at = $IS_BOSS ? 'bosses' : 'arena'; $dhcnav_guest = $dhca_guest;
      include 'dhc-nav.php'; ?>
<?php
  /* One typeface for every DHC surface -- the Arena's titles were the
     monospace body face while the Collection's were the display one. */
  require_once __DIR__ . '/dhc-type.php'; dhc_type_styles();
?>
<style>
/* ================== TWO SKINS, AND THE SEAM BETWEEN THEM ====================
   THE SELECTION SCREEN IS A PLATFORM PAGE. It sits in the platform header, next
   to platform navigation, and it should look like everything else on
   Skulliance -- same ground, same accent, same typeface. It had been wearing
   the prototype's own dark monospace skin, which was written for a standalone
   page with nothing above it, and next to the real header it read as a foreign
   object bolted onto a navy interface.

   THE BATTLE IS A GAME WORLD and keeps its own. That is the same call
   dhc-assembler.php makes about the sandbox, and for the same reason: a board
   with five gem colours, damage reds and shield blues cannot be recoloured to
   a UI palette without losing what the colours are for.

   So the palette below is Skulliance, sampled from the platform's own
   stylesheet exactly as the assembler samples it -- #07111d ground, #00c8a0
   accent, #7a9eb0 muted -- and #arenaBattle further down overrides the whole
   set with the game's.
   ========================================================================== */
.arena-wrap{--ink:#07111d;--panel:#0a1929;--panel2:#0d1e2e;--line:#1b3346;
  --bone:#e8eaed;--dim:#7a9eb0;--teal:#00a882;--ochre:#00c8a0;--blood:#00c8a0;
  --shield:#5aa9ff;--warn:#d0463a;}
.arena-wrap,.arena-wrap *{box-sizing:border-box}
/* No background and no font of its own: the page ground and Arial come from
   the platform, which is the whole point. */
.arena-wrap{margin:0}
/* The board's own world. Everything the game draws lives in here. */
.arena-wrap #arenaBattle{--ink:#0d0f13;--panel:#151922;--panel2:#1d2230;--line:#2b3345;
  --bone:#e8e6e1;--dim:#8b93a7;--teal:#00c8a0;--ochre:#f5a623;--blood:#e0466b;
  --shield:#5aa9ff;--warn:#e0466b;
  --g0:#e0466b;--g1:#f5a623;--g2:#8b7bd8;--g3:#5aa9ff;--g4:#00c8a0;
  background:var(--ink);color:var(--bone);border:1px solid var(--line);border-radius:4px;
  padding:10px;font:13px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  -webkit-user-select:none;user-select:none}
/* NO MAX-WIDTH OF ITS OWN. The platform's .container already caps at 2000px and
   centres, so capping again here just made the Arena 500px narrower than the
   header sitting above it. The prototype was a standalone page and needed its
   own cap; this is not, and dhcfighters.php learned the same lesson already --
   see the note on .dhcf-wrap. The board is still capped separately, against
   viewport HEIGHT, which is a different question and still the right one. */
.arena-wrap{max-width:100%;margin:0 auto;padding:12px;overflow-x:clip}
/* Nothing may scroll the page sideways. A phone has no spare width and a
   horizontal scrollbar makes a board feel broken to drag on. */
.arena-wrap{max-width:100%;overflow-x:clip}
.arena-wrap h1{font-size:15px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 2px}
.arena-wrap .sub{color:var(--dim);font-size:10.5px;margin:0 0 10px}
.arena-wrap button{font:inherit;cursor:pointer;border-radius:3px}
/* The share is an <a>, not a <button>, because it opens a composer in a new tab
   and that is a link's job. It still has to sit in the row looking like the
   buttons beside it, so it borrows what the button reset gives them. */
.arena-wrap a.btn{border-radius:3px;text-decoration:none;cursor:pointer;
  display:inline-flex;align-items:center;line-height:1.35}
.arena-wrap .btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);padding:6px 12px}
.arena-wrap .btn:hover{border-color:var(--ochre);color:var(--ochre)}
.arena-wrap .btn.go{background:var(--blood);border-color:var(--blood);color:#fff}
.arena-wrap .top{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.arena-wrap #howto{display:none;padding:6px 11px}
.arena-wrap .turnflag{font-size:10px;letter-spacing:.12em;text-transform:uppercase;padding:3px 9px;
  border-radius:999px;border:1px solid var(--line);color:var(--dim)}
.arena-wrap .turnflag.you{border-color:var(--teal);color:var(--teal)}
.arena-wrap .turnflag.foe{border-color:var(--blood);color:var(--blood)}
/* The Fighter columns SCALE rather than sitting at a fixed width. A flat 320px
   looked right on a 2000px screen and quietly stole the board's width on a
   1440px laptop, where the board is limited by what is left over rather than by
   height -- so widening the sides made the board smaller on exactly the screens
   that had least to spare. Scaling by viewport keeps the height cap in charge
   on every normal desktop and still lets the Fighters grow on a big monitor. */
.arena-wrap .arena{position:relative;display:grid;
  grid-template-columns:minmax(0,clamp(230px,19vw,340px)) minmax(0,1fr)
                        minmax(0,clamp(230px,19vw,340px));
  gap:16px;align-items:start}
/* The board must not simply eat the extra width -- a 1000px square does not fit
   a laptop viewport -- so it is capped against viewport HEIGHT and centred, and
   the width that frees up goes to the Fighters, which is the point.
   The cap belongs to the whole column, not just the board: applied only to the
   boardwrap it left the reach line and the legend stretching the full column
   width, so they ran wider than the thing they describe.

   THE SECOND NUMBER IS A CEILING, NOT THE ANSWER. It was 760px, which on any
   screen taller than about 1030px is SMALLER than the height cap -- so the
   board stopped growing at 760 and left the rest of the screen empty, which is
   not what a height cap is for.

   The target is not the viewport, it is the COLUMN BESIDE IT. Three Fighters at
   210px of art each, plus their names, kits and health bars, come to about
   940px, and the grid row is as tall as its tallest column -- so a 760px board
   sat in a 940px row with a couple of hundred pixels of nothing beside it.
   82vh lands the board column within a few pixels of both the Fighters beside
   it and the bottom of a 1069px window, which is what makes the three columns
   read as one thing and keeps the battle log in view.

   The pixel ceiling only catches what the height cap cannot: a short, very wide
   window, where 84vh of WIDTH would run past the Fighters. */
.arena-wrap .boardcol > *{max-width:min(82vh,1180px);margin-left:auto;margin-right:auto}
.arena-wrap .teamcol{display:grid;gap:6px}
.arena-wrap .coltag{font-size:9px;letter-spacing:.16em;text-transform:uppercase;margin-bottom:5px;
  padding-bottom:4px;border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;gap:8px}
/* CHARGE IS A TEAM METER. Every living Fighter gains it together, so showing it
   three times in three token corners was repetition -- and once the text was
   shortened to "⚡ 7" it stopped saying what it was counting towards, which is
   the only thing you actually want to know. One bar per Crew, beside the name. */
.arena-wrap .chg{display:flex;align-items:center;gap:5px;letter-spacing:0;text-transform:none;
  font-size:9px;color:var(--dim);white-space:nowrap}
.arena-wrap .chg i{display:block;width:46px;height:5px;border-radius:3px;background:#0b0d11;
  overflow:hidden;flex:none}
.arena-wrap .chg i b{display:block;height:100%;width:0;background:var(--g4);
  transition:width .35s cubic-bezier(.2,.7,.3,1)}
.arena-wrap .chg.full{color:var(--g4);animation:chgPulse 1s ease-in-out infinite}
.arena-wrap .chg.full i b{box-shadow:0 0 8px var(--g4)}
@keyframes chgPulse{0%,100%{opacity:1}50%{opacity:.55}}
.arena-wrap .coltag.you{color:var(--teal)}
.arena-wrap .coltag.foe{color:var(--blood)}
.arena-wrap .boardcol{min-width:0}
.arena-wrap .legend.mobonly, .arena-wrap .reach.mobonly{display:none}
@media (max-width:1000px){/* Stack, and put the enemies above the board where they read as the opposition */
  .arena-wrap .arena{grid-template-columns:1fr}.arena-wrap .teamcol{grid-template-columns:repeat(3,minmax(0,1fr))}.arena-wrap .teamwrap.foes{order:-1}.arena-wrap .teamwrap.mine{order:1}/* Enemies, board and your Crew have to fit one screen together, so the
     legend goes below all three rather than wedging between the board and
     your own Fighters. */
  .arena-wrap .legend.deskonly, .arena-wrap .reach.deskonly{display:none}.arena-wrap .legend.mobonly{display:flex;margin-top:8px}.arena-wrap .reach.mobonly{display:flex;margin-top:10px}/* PACKED TIGHT. Every gap between your Crew, the board and theirs is space
     the board could be using, and on a phone the three have to share one
     screen. The title hides with the intro rather than being deleted -- both
     come back together behind the ? button, so nothing is lost, it is just not
     paying rent on a 750px screen. */
  .arena-wrap{padding:8px}.arena-wrap .arena{gap:5px}.arena-wrap .arenabg{inset:0}/* no bleed: it was pushing 6px past the viewport */
  .arena-wrap .boardwrap{padding:5px}.arena-wrap .coltag{font-size:8px;margin-bottom:2px;padding-bottom:2px}.arena-wrap .top{margin-bottom:6px;gap:6px}.arena-wrap .teamcol{gap:4px}/* Squat tokens. Three-up at phone width the art would be square at ~128px, and two rows of that plus the board came to about 794px -- over the fold on
     most phones even after the legend moved. 96px brings the core play area
     near 730px, which fits. The whole figure still shows; it is letterboxed
     into a shorter frame rather than cropped. */
  .arena-wrap .teamcol .tok .art{aspect-ratio:auto;height:96px}/* Three tokens shoulder to shoulder with a 4px gap: an 8% pop-out overlaps
     its neighbours. Smaller on a phone, full size in the desktop columns. */
  .arena-wrap .tok.act{animation:actSmall .34s}.arena-wrap .teamcol .tok{padding:4px}.arena-wrap .teamcol .tok .nm{font-size:9px}.arena-wrap .teamcol .tok .kitn{font-size:7.5px}.arena-wrap .coltag{margin-bottom:3px;padding-bottom:3px}/* Title and intro are one unit behind the ? -- a wall of rules plus a
     heading costs most of a screen on a phone, and neither is needed while
     you are playing. One tap brings both back. */
  .arena-wrap.playing h1, .arena-wrap.playing .a-sub, .arena-wrap.playing .a-stats{display:none}
  /* and the DHC strip: mid-battle there is nowhere else to be, and a link out
     of a live turn is a link somebody will regret taking */
  .arena-wrap.playing .dhcnav{display:none}
  .arena-wrap.playing.showintro h1, .arena-wrap.playing.showintro .a-sub{display:block}
  .arena-wrap #howto{display:inline-block}
}
/* ---- teams ---- */
.arena-wrap .tok{background:var(--panel2);border:1px solid var(--line);border-radius:3px;padding:5px;
  position:relative;transition:transform .16s,opacity .3s,border-color .15s}
.arena-wrap .tok{border-left:3px solid var(--gem)}
.arena-wrap .tok .rk{font-size:7.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);
  display:flex;justify-content:space-between;gap:4px}
.arena-wrap .tok .mygem{display:inline-flex;align-items:center;justify-content:center;
  width:19px;height:19px;border-radius:50%;background:var(--gem);font-size:11px;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12);flex:none}
.arena-wrap .tok .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:repeating-conic-gradient(#191419 0% 25%,#201b20 0% 50%) 50%/9px 9px;margin:2px 0}
.arena-wrap .tok .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
/* PER-LAYER MOTION. Only possible because a Fighter is still separate images at
   render time -- the same separation the layering rules needed.
   Kept SMALL on purpose. Each layer is a full 250px frame that is mostly
   transparent, so it rotates about the frame's centre rather than the weapon's
   grip, and .art clips at the edge. A few degrees reads as a swing; twenty
   would read as the picture coming apart.
   Transform only, no filters: transforms are composited on the GPU, so this
   costs nothing per frame, which matters after the audio lesson. */
.arena-wrap .tok .art img[data-l="weapon"]{transform-origin:50% 65%}
.arena-wrap .tok .art img.swing{animation:swing .34s ease-out}
@keyframes swing{0%{transform:rotate(0) translateX(0)}30%{transform:rotate(-7deg) translateX(-2%)}55%{transform:rotate(4deg) translateX(1%)}100%{transform:rotate(0) translateX(0)}}
.arena-wrap .tok .art img.bob{animation:bob .42s ease-in-out}
@keyframes bob{0%{transform:translateY(0)}40%{transform:translateY(-5%)}100%{transform:translateY(0)}}
.arena-wrap .tok .art img.jolt{animation:jolt .30s ease-out}
@keyframes jolt{0%{transform:translateX(0)}35%{transform:translateX(3%) rotate(2deg)}100%{transform:translateX(0)}}
/* the Fighter's own background fills the frame and sits well back */
/* NO TREATMENT. The background is drawn as it is, same as the assembler and
   the gallery draw it. It had a brightness filter, a saturate filter and a
   dark scrim stacked on it to keep the figure readable, and the result was
   art that looked broken rather than art that sat back. If a busy background
   ever does swallow a figure, that is a reason to say so about that trait --
   not to dim all 42 of them. */
.arena-wrap .tok .art img.bg{object-fit:cover}
.arena-wrap .tok .nm{font-size:9.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .tok .kitn{font-size:8px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .hpwrap{position:relative;height:6px;background:#0b0d11;border-radius:2px;overflow:hidden;margin-top:3px}
/* .18s, not .4s. The bar now moves on the hit itself, and a wave's hold is
   190ms -- at .4s it was still travelling when the next link of the cascade
   cleared, so every chunk blurred into the one after it and the health read as
   one slow slide arriving late. Short enough to land inside its own beat. */
.arena-wrap .hp{position:absolute;inset:0;background:var(--teal);transform-origin:left;
  transition:transform .18s cubic-bezier(.2,.7,.3,1)}
.arena-wrap .hp.low{background:var(--ochre)}
.arena-wrap .hp.crit{background:var(--blood)}
.arena-wrap .sh{position:absolute;top:0;left:0;height:100%;background:var(--shield);opacity:.8;transition:width .18s}
/* Fixed height and no wrapping. This line gains "sh 24" and "charge 7/10" the
   moment Shield or Charge match, and on a phone-width token that wrapped to a
   second line -- so the token grew, the row shoved, and the Crew jolted. On
   desktop there was room, so it never wrapped and nothing moved: the same
   report from both ends. */
.arena-wrap .hpn{font-size:8px;color:var(--dim);font-variant-numeric:tabular-nums;display:flex;
  justify-content:space-between;gap:4px;white-space:nowrap;overflow:hidden;
  height:12px;line-height:12px}
.arena-wrap .hpn span{overflow:hidden;text-overflow:ellipsis}
/* The layers now carry the fade themselves, so the token only desaturates.
   Stacking the old opacity:.3 on top of layers that already end near .2 left
   the art at about 6% -- a fallen Fighter should read as a wreck you can still
   name, not an empty box. The text stays legible for the same reason: whose
   Fighter died, and on what HP, is information. */
.arena-wrap .tok.ko{opacity:.8;filter:grayscale(1);transition:opacity .5s,filter .5s}
.arena-wrap .tok.ko .nm{text-decoration:line-through}
/* While a Fighter is coming apart it must not already be greyed out -- the
   whole point is watching it happen. Three classes beats two, so this wins
   over .tok.ko until the sequence finishes. */
/* Full colour WHILE it comes apart -- you should watch a Fighter fall in the
   art it was built from -- and then the grey settles over the pieces where
   they landed. */
.arena-wrap .tok.ko.dying{opacity:1;filter:none}
.arena-wrap .tok.dying{animation:deathShake .55s}
@keyframes deathShake{0%{transform:translateX(0)}12%{transform:translateX(-4%) rotate(-1.5deg)}30%{transform:translateX(3.5%) rotate(1.2deg)}52%{transform:translateX(-2.5%) rotate(-.8deg)}74%{transform:translateX(1.5%)}100%{transform:translateX(0)}}
/* TRAIT BY TRAIT. Each layer falls out of the composite on its own, staggered
   from the top of the stack down, so a Fighter comes apart in the order it was
   assembled rather than simply fading. Two variants so pieces do not all spin
   the same way -- alternating by index reads as debris, one direction reads as
   a single object rotating.
   Ends at .22 rather than 0 and stays there (forwards): the fallen Fighter
   should be a scattered wreck you can still recognise, not an empty frame. */
/* An aura goes out. It is the one layer that SHOULD fade, because that is what
   fire stopping looks like. */
.arena-wrap .tok .art img.snuff{animation:snuff .34s ease-out forwards}
@keyframes snuff{0%{opacity:1;transform:none}100%{opacity:0;transform:scale(1.12)}}
.arena-wrap .tok .art img.dis{animation:disA .62s cubic-bezier(.3,0,.7,1) forwards}
.arena-wrap .tok .art img.dis.alt{animation-name:disB}
/* NO FADE, and NOTHING ELSE CHANGED. The motion is exactly what it was -- same
   distance, same rotation, same scale -- with the opacity taken out. The pieces
   used to finish at 22% opacity, so a Fighter came apart and then dissolved
   where it stood, and half-transparent artwork reads as a rendering fault
   rather than a death. They come to rest in full colour now, and the greyscale
   arrives over the top of them a moment later. */
@keyframes disA{0%{transform:none}16%{transform:translateY(-4%) scale(1.04)}100%{transform:translateY(28%) rotate(9deg) scale(.84)}}
@keyframes disB{0%{transform:none}16%{transform:translateY(-5%) scale(1.05)}100%{transform:translateY(24%) rotate(-11deg) scale(.86)}}
/* The companion goes all the way out. Declared after .alt so it wins whichever
   half of the stagger it lands on. */
.arena-wrap .tok .art img.dis.gone{animation-name:disGone}
@keyframes disGone{0%{transform:none}16%{transform:translateY(-6%) scale(1.04)}100%{transform:translateY(120%) rotate(15deg) scale(.82)}}
.arena-wrap .tok.hit{animation:hit .3s}
@keyframes hit{0%{transform:translateX(0)}30%{transform:translateX(-3%)}60%{transform:translateX(2.4%)}100%{transform:translateX(0)}}
.arena-wrap .tok.act{animation:act .34s}
@keyframes act{0%{transform:scale(1)}40%{transform:scale(1.08)}100%{transform:scale(1)}}
@keyframes actSmall{0%{transform:scale(1)}40%{transform:scale(1.035)}100%{transform:scale(1)}}
/* z-index matters here and was missing. .flash is the token's FIRST child with
   no z-index, and .tok .art is position:relative and comes after it -- so with
   both at auto they paint in DOM order and the ART COVERED THE FLASH. Damage
   has been flashing behind the character all along, which is most of why a hit
   did not read. Above the art now, below the damage number. */
.arena-wrap .flash{position:absolute;inset:0;opacity:0;pointer-events:none;border-radius:3px;z-index:4;
  background:var(--blood)}
.arena-wrap .flash.on{animation:fl .30s}
@keyframes fl{0%{opacity:.50}100%{opacity:0}}
/* A crit is white and lingers -- it should look different from a normal hit, not merely bigger. */
.arena-wrap .flash.big{background:#fff}
.arena-wrap .flash.big.on{animation:flBig .40s}
@keyframes flBig{0%{opacity:.80}35%{opacity:.35}100%{opacity:0}}
/* Healing and shielding get their own colours, so Drain reads as taking
   something rather than only as damage. */
.arena-wrap .flash.heal{background:var(--teal)}
.arena-wrap .flash.shield{background:var(--shield)}
/* AT RISK. A Fighter whose bar has gone red pulses, because the bar is 8px of a
   270px token and on a phone it is 8px of a three-across row -- the one piece
   of information you most need mid-move was the smallest thing on screen.
   Same threshold as .hp.crit, deliberately: the glow and the red bar must never
   disagree about who is about to die.

   ITS OWN ELEMENT, not an animation on .tok. The token already animates on
   .act, .hit and .hit.big, and `animation` is one property -- a pulse declared
   there would be cancelled by the next hit and would cancel the lunge in turn.
   A separate layer pulses continuously underneath all of that. Under .flash
   (z-index 4) so an incoming hit still reads over the warning. */
.arena-wrap .danger{position:absolute;inset:0;opacity:0;pointer-events:none;border-radius:3px;
  z-index:3;box-shadow:inset 0 0 0 2px var(--blood),inset 0 0 14px rgba(224,70,107,.55)}
.arena-wrap .tok.crit .danger{animation:danger 1.15s ease-in-out infinite}
@keyframes danger{0%,100%{opacity:.30}50%{opacity:1}}
/* The bar breathes with it, so the two read as one signal rather than two. */
.arena-wrap .tok.crit .hp.crit{animation:dangerBar 1.15s ease-in-out infinite}
@keyframes dangerBar{0%,100%{opacity:1}50%{opacity:.55}}
/* A Fighter who is already down is not at risk of anything. */
.arena-wrap .tok.ko .danger{animation:none;opacity:0}
.arena-wrap .tok.hit.big{animation:hitBig .40s}
@keyframes hitBig{0%{transform:translateX(0)}20%{transform:translateX(-5%)}45%{transform:translateX(4%)}70%{transform:translateX(-2%)}100%{transform:translateX(0)}}
.arena-wrap .pop{position:absolute;left:50%;top:22%;transform:translateX(-50%);font-size:14px;font-weight:700;
  pointer-events:none;opacity:0;text-shadow:0 2px 6px #000;z-index:5;white-space:nowrap}
.arena-wrap .pop.on{animation:pp .9s}
.arena-wrap .pop.heal{color:var(--teal)}
.arena-wrap .pop.big{font-size:19px;color:var(--ochre)}
@keyframes pp{0%{opacity:0;transform:translate(-50%,6px)}18%{opacity:1}100%{opacity:0;transform:translate(-50%,-26px)}}
/* In the side-column layout three stacked tokens would stand far taller than
   the board, so the art gets a fixed height there and letterboxes inside it --
   object-fit:contain already centres it. */
/* NOT ".arena-wrap @media". A prefixed at-rule is invalid and the browser
   throws the WHOLE block away -- which is what happened here: every desktop
   refinement below, the full-figure art, the taller frame, the larger type and
   the enemy mirroring, was dead from the moment the prototype's stylesheet was
   scoped to .arena-wrap. The scoping pass moved a prefix past a comment and
   landed it in front of the @media. The rules inside are already scoped
   individually, which is why they read correctly and still did nothing. */
@media (min-width:1001px){/* THE WHOLE FIGHTER, not a bust crop. The crop showed the head and torso
     larger, but it cut the legs, the weapon and half of any companion off --
     and the art is the reason to care about a Fighter at all.
     210px is the size that fits: three tokens then stand about 890px against
     the board column's ~820, so the columns finish near level instead of
     running far past it. The square art letterboxes into the 270px width with
     small bars either side, over the checkerboard, which reads as a frame. */
  /* Scaled to the viewport, not fixed. At a flat 210px a Fighter column comes
     to about 940px whatever screen it is on, so on a 1366x768 laptop the Crew
     alone was taller than the window and the whole battle had to be scrolled
     through. Tied to height it tracks the board, which is capped the same way,
     and the two columns stay roughly level instead of one dictating the row. */
  .arena-wrap .teamcol .tok .art{aspect-ratio:auto;height:clamp(120px,19vh,230px)}.arena-wrap .teamcol .tok .art img{object-fit:contain}/* Enemies face the player. The art is all drawn facing one way, so the right
     column mirrors and the two Crews look at each other across the board
     instead of everyone staring the same direction. Only in the side-column
     layout -- stacked on a phone they are above you, not opposite you, and a
     mirrored row there just looks like different art. */
  /* On the container, not the images. A transform on the img would be
     overwritten the moment a layer animates, flipping enemies back mid-swing.
     Mirroring the frame instead leaves every layer's own transform free. */
  .arena-wrap .teamcol.foes .tok .art{transform:scaleX(-1)}.arena-wrap .teamcol .tok{padding:8px}.arena-wrap .teamcol .tok .nm{font-size:12px}.arena-wrap .teamcol .tok .kitn{font-size:10px}.arena-wrap .teamcol .tok .rk{font-size:9px}.arena-wrap .teamcol .tok .hpn{font-size:9.5px;height:14px;line-height:14px}.arena-wrap .teamcol .tok .hpwrap{height:8px}.arena-wrap .teamcol{gap:10px}
}
/* ---- board ---- */
/* WHOSE BOARD IT IS, at a glance. This was a 55%-opacity border and a 1px
   ring, which is invisible if you are watching the gems -- which is the only
   thing anybody watches. It is the standing state for the whole of their
   exchange, so it earns being seen, and it pulses slowly rather than sitting
   still so that a glance anywhere near the board catches it. */
.arena-wrap .boardwrap.foeturn{border-color:rgba(224,70,107,.9);
  box-shadow:0 0 0 2px rgba(224,70,107,.3), 0 0 18px rgba(224,70,107,.22);
  animation:foeTurn 2.2s ease-in-out infinite}
@keyframes foeTurn{
  0%,100%{box-shadow:0 0 0 2px rgba(224,70,107,.3), 0 0 18px rgba(224,70,107,.18)}
  50%    {box-shadow:0 0 0 2px rgba(224,70,107,.45), 0 0 26px rgba(224,70,107,.34)}}
@media (prefers-reduced-motion:reduce){
  .arena-wrap .boardwrap.foeturn{animation:none}}
.arena-wrap .boardwrap{position:relative;border:1px solid var(--line);border-radius:4px;
  transition:border-color .2s,box-shadow .2s;
  background:rgba(21,25,34,.80);
  padding:8px;overflow:hidden}
/* Was inset in the board at 13% opacity behind an opaque grid, which meant the
   terrain -- a real mechanic, drawn from a real trait -- was invisible. It now
   backs the entire arena, with a scrim so nothing over it loses contrast. */
.arena-wrap .arenabg{position:absolute;inset:-14px;z-index:0;border-radius:6px;overflow:hidden;
  /* The negative inset is a deliberate bleed so the backdrop reaches past the
     columns, but it is also 14px of width the page does not have on a phone --
     see the mobile block, where it is pulled back to the arena's own edges. */
  background-size:cover;background-position:center;opacity:.30;
  filter:saturate(.75) contrast(.95);pointer-events:none}
.arena-wrap .arenabg:after{content:'';position:absolute;inset:0;
  background:radial-gradient(120% 90% at 50% 40%,rgba(13,15,19,.35),rgba(13,15,19,.88))}
.arena-wrap .arena > .teamwrap, .arena-wrap .arena > .boardcol{position:relative;z-index:1}
.arena-wrap .grid{position:relative;z-index:2;display:grid;gap:3px;touch-action:none}
.arena-wrap .cell{position:relative;aspect-ratio:1;border-radius:4px;display:flex;align-items:center;
  justify-content:center;cursor:pointer;background:#10131a;border:1px solid transparent;
  transition:transform .12s,border-color .12s}
.arena-wrap .cell:hover{border-color:var(--line)}
.arena-wrap .cell.sel{border-color:var(--bone);transform:scale(.9)}
.arena-wrap .cell .g{width:72%;height:72%;border-radius:50%;background:var(--gc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:inset 0 -3px 6px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.08);
  transition:transform .18s,opacity .18s}
.arena-wrap .cell.sq .g{border-radius:4px}
.arena-wrap .cell.di .g{border-radius:3px;transform:rotate(45deg) scale(.82)}
/* Flat shoulders, straight flanks, tapering to a point at the bottom -- the
   same silhouette as the emoji sitting on it. */
.arena-wrap .cell.shield .g{border-radius:3px 3px 0 0;
  clip-path:polygon(0% 0%,100% 0%,100% 48%,86% 78%,50% 100%,14% 78%,0% 48%)}
/* the point steals height from the bottom, so the emoji rides a little high */
.arena-wrap .cell.shield .g .em{transform:translateY(-8%)}
.arena-wrap .cell.hex .g{clip-path:polygon(25% 5%,75% 5%,100% 50%,75% 95%,25% 95%,0 50%)}
.arena-wrap .cell .em{font-size:clamp(11px,2.4vw,19px);line-height:1;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6));
  pointer-events:none;
  /* A BOX, not a bare inline span. Everything inside sizes itself as a
     percentage, and a percentage of an inline span with no dimensions is zero
     -- that is how a bomb once rendered at no size at all. Still true for the
     masked icons, which are also sized in percent. */
  display:flex;align-items:center;justify-content:center;width:100%;height:100%}
/* the rotated diamond must not rotate its icon with it */
.arena-wrap .cell.di .g .em{transform:rotate(-45deg)}
/* ONE RULE FOR EVERY ICON. A mask rather than an <img> src, so the artwork is
   tinted by currentColor and inherits whatever the thing around it is doing --
   a legend chip greys out with .gone, a Fighter's gem takes the rank colour,
   and a knocked-out token desaturates -- none of which an <img> would follow.
   The set is white on alpha, so masking loses nothing. */
.arena-wrap .ico{display:inline-block;width:1.15em;height:1.15em;vertical-align:-.2em;
  background-color:currentColor;
  -webkit-mask:var(--ico) center/contain no-repeat;
          mask:var(--ico) center/contain no-repeat}
/* On the board the glyph is the whole cell's face, so it fills the gem rather
   than sitting on a text baseline. */
.arena-wrap .cell .g .ico{width:58%;height:58%;vertical-align:0;color:#fff;
  filter:drop-shadow(0 1px 2px rgba(0,0,0,.55))}
.arena-wrap .cell.bomb .g .ico{width:64%;height:64%}
.arena-wrap .lchip b .ico{width:15px;height:15px;vertical-align:-3px}
.arena-wrap .mygem .ico{width:12px;height:12px;vertical-align:-1px}
.arena-wrap .fc-gem b .ico{width:13px;height:13px;vertical-align:-2px}
/* A bomb keeps its colour -- you detonate it by matching that colour -- but it
   has to be unmistakable on a busy board, so it pulses and wears a ring. */
/* THE RING IS ON THE GEM, THE GLOW IS ON THE CELL.
   Both used to be one box-shadow on .g -- and .g is clip-path'd into a hexagon
   for Charge and a shield for Shield, which clips a box-shadow dead. So on two
   of the five gem colours a bomb had no glow whatsoever. The cell is never
   clipped, so the glow lives there and survives every gem shape.
   The board bomb's glow was also var(--ochre), which is #f5a623 -- byte for
   byte the amber gem colour -- so on amber it was lighting the tile in the
   tile's own colour. It is white-cored now, which collides with none of the
   five, and it has to be the louder of the two anyway: it clears the board,
   the grenade clears a line. */
/* The edge is static and tight: it marks the gem as armed without lighting
   anything up. A drop-shadow rather than a box-shadow so it follows the
   hexagon and the shield, which are clip-paths. */
.arena-wrap .cell .bglow{position:absolute;inset:0;display:flex;
  align-items:center;justify-content:center;pointer-events:none}
.arena-wrap .cell.bomb .bglow{filter:drop-shadow(0 0 1.5px #fff)}
.arena-wrap .cell.bomb2 .bglow{filter:drop-shadow(0 0 1px #fff) drop-shadow(0 0 1px #fff)
  drop-shadow(0 0 1px #fff)}
.arena-wrap .cell.bomb .g{box-shadow:inset 0 -3px 6px rgba(0,0,0,.45);
  animation:bmb 1.5s ease-in-out infinite}
/* THE GLOW HUGS THE GEM, NOT THE TILE.
   It lived on .cell for a while, which is a square whatever shape the gem is
   -- so a hexagon sat inside a square halo, and the halo read as the thing
   that was lit rather than the bomb.
   filter: drop-shadow() follows the element's actual alpha, clip-path and all,
   where box-shadow follows its border box. That is also why the white ring was
   missing on hexagons and shields and present on diamonds: the ring was a
   box-shadow, hex and shield are clip-paths which clip it away, and a diamond
   is a rotate, which does not. So the ring and the halo are both drop-shadows
   now and both appear on every shape. */
/* NO GLOW ON EITHER, JUST A THICKER LINE.
   The detonator had a pulsing white-and-ochre halo and it was blinding -- a
   gem lit that brightly stops being a gem you read and becomes a lamp on the
   board. Both bombs wear a white outline now and the detonator's is simply
   heavier, which is enough: it also pulses half again as fast.
   Stacked tight drop-shadows rather than one blurred one, because stacking
   thickens the edge while a wider blur just spreads it into a glow again. */
.arena-wrap .cell.bomb2 .g{box-shadow:inset 0 -3px 6px rgba(0,0,0,.45);
  animation:bmb .9s ease-in-out infinite}
@keyframes bmb{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
/* The cross bomb's blast shape, drawn on the gem: a bar across and a bar down. */
/* The hand-drawn cross is gone. It existed because no emoji read as "row and
   column" at gem size across platforms -- a real problem, solved better by an
   icon from the platform's own set than by two CSS bars. */
/* Detonation: everything the blast takes lights up before it goes. */

@keyframes blastPop{0%{transform:scale(1);filter:brightness(1)}35%{transform:scale(1.35);filter:brightness(3.2)}100%{transform:scale(.2);filter:brightness(1);opacity:0}}
@keyframes blastPop2{0%{transform:scale(1) rotate(0);filter:brightness(1)}30%{transform:scale(1.5) rotate(8deg);filter:brightness(4)}100%{transform:scale(.15) rotate(-6deg);opacity:0}}
.arena-wrap .boardwrap.shake{animation:bshake .38s}
@keyframes bshake{0%,100%{transform:translate(0,0)}20%{transform:translate(-5px,3px)}45%{transform:translate(4px,-3px)}70%{transform:translate(-3px,-2px)}}
/* THE DIAMOND PULSES TOO, it just has to carry its own rotation while it does.
   This was animation:none, because @keyframes bmb animates `transform` and a
   diamond IS a transform -- rotate(45deg) scale(.82) -- so the shared pulse
   flattened the gem back into a square. Switching the pulse off was the quick
   answer and it left violet the one shape on the board that never moved when
   it held a bomb. A keyframe that keeps the rotation fixes it properly: same
   8% swell as every other shape, still a diamond throughout. */
@keyframes bmbDi{0%,100%{transform:rotate(45deg) scale(.82)}
  50%{transform:rotate(45deg) scale(.89)}}
.arena-wrap .cell.di.bomb .g{animation:bmbDi 1.5s ease-in-out infinite}
.arena-wrap .cell.di.bomb2 .g{animation:bmbDi .9s ease-in-out infinite}
/* GOING OFF BEATS SITTING THERE. These come last and are written specific
   enough to outrank every idle bomb pulse above, including the diamond's,
   which carries an extra class and therefore outranked a plain .blast rule.
   A bomb that detonated on a violet tile was playing its idle pulse through
   its own explosion. */
.arena-wrap .cell.blast .g,
.arena-wrap .cell.blast.bomb .g,
.arena-wrap .cell.blast.bomb.di .g{animation:blastPop .42s ease-out}
.arena-wrap .cell.blast2 .g,
.arena-wrap .cell.blast2.bomb .g,
.arena-wrap .cell.blast2.bomb.di .g{animation:blastPop2 .5s ease-out}
.arena-wrap .cell.clear .g{transform:scale(0);opacity:0}
.arena-wrap .cell.drop{animation:drp .22s}
.arena-wrap .cell.settle{animation:stl .16s}
/* No bomb animation on .cell any more -- it all lives on .g, which .drop and
   .settle never touch -- so the two no longer collide and need no composing. */
@keyframes stl{0%{transform:scale(1.06)}100%{transform:scale(1)}}
@keyframes drp{0%{transform:translateY(-16px);opacity:.4}100%{transform:translateY(0);opacity:1}}
/* ============================ THE ENTRANCE ==================================
   A battle used to appear all at once, fully drawn, which gave the biggest
   moment in the game no moment at all. It arrives in three beats now: the
   arena and what it does to the fight, then the board falling in, then the two
   Crews walking on.

   IT IS SKIPPABLE AND IT IS SHORT. Six battles a day means seeing this six
   times a day, so the whole sequence is under three seconds and a tap anywhere
   ends it immediately. It also never plays on RESUME -- picking a battle back
   up mid-move is not an entrance, and replaying a moment that already happened
   is the exact mistake the drop-reveal queue made.
   ========================================================================== */
.arena-wrap .a-intro{position:absolute;inset:-14px;z-index:30;border-radius:6px;overflow:hidden;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:7px;text-align:center;padding:18px;cursor:pointer;
  background-size:cover;background-position:center}
.arena-wrap .a-intro:after{content:'';position:absolute;inset:0;
  background:radial-gradient(120% 90% at 50% 45%,rgba(13,15,19,.55),rgba(13,15,19,.95))}
.arena-wrap .a-intro > *{position:relative;z-index:1}
.arena-wrap .a-intro.go{animation:introIn .45s ease-out}
.arena-wrap .a-intro.out{animation:introOut .40s ease-in forwards;pointer-events:none}
@keyframes introIn{0%{opacity:0}100%{opacity:1}}
@keyframes introOut{0%{opacity:1}100%{opacity:0}}
.arena-wrap .ti-k{font-size:9px;letter-spacing:.28em;text-transform:uppercase;color:var(--dim);
  animation:tiUp .5s .05s both}
.arena-wrap .ti-n{font-size:clamp(19px,4.4vw,34px);letter-spacing:.06em;text-transform:uppercase;
  color:var(--bone);text-shadow:0 3px 18px #000;animation:tiName .7s .12s both}
/* The atmosphere line: quieter than the name, warmer than the rule, and given
   a measure so it breaks where a sentence should rather than running the width
   of a monitor. */
.arena-wrap .ti-l{font-size:13px;line-height:1.5;color:var(--bone);opacity:.78;
  max-width:40ch;font-style:italic;animation:tiUp .6s .34s both}
.arena-wrap .ti-e{font-size:11.5px;color:var(--ochre);max-width:34ch;animation:tiUp .5s .62s both}
.arena-wrap .ti-v{margin-top:6px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  color:var(--dim);animation:tiUp .5s .82s both}
.arena-wrap .ti-v b{color:var(--bone);font-weight:400}
.arena-wrap .ti-s{position:absolute;bottom:9px;right:12px;z-index:1;font-size:8.5px;
  letter-spacing:.14em;text-transform:uppercase;color:var(--dim);
  /* Held back until the text has arrived. Offering a way out of a panel before
     the panel has finished appearing reads as "this is going to take a while". */
  animation:tiUp .5s 1.5s both;opacity:.6}
@keyframes tiUp{0%{opacity:0;transform:translateY(7px)}100%{opacity:1;transform:translateY(0)}}
@keyframes tiName{0%{opacity:0;transform:scale(1.14);letter-spacing:.22em}
  100%{opacity:1;transform:scale(1);letter-spacing:.06em}}
/* Beat two and three. The gems fall in on a per-cell delay set in JS, and the
   Crews walk on from their own side of the board.

   THE BOARD IS HIDDEN, NOT MERELY ANIMATED, until its beat arrives. The panel
   fades in over 450ms, and a board that is only animating is a board you can
   watch through the fade -- which is exactly backwards: the first thing you saw
   was the finished picture, and the cinematic then covered it up. Hiding the
   grid outright means there is nothing behind the panel to see. */
.arena-wrap .cine .grid{visibility:hidden}
.arena-wrap .cine.board .grid{visibility:visible}
.arena-wrap .cine.board .cell{animation:gemIn .34s both}
@keyframes gemIn{0%{opacity:0;transform:translateY(-26px) scale(.55)}
  60%{opacity:1;transform:translateY(2px) scale(1.04)}100%{transform:translateY(0) scale(1)}}
.arena-wrap .cine .teamwrap{opacity:0}
.arena-wrap .cine.crew .teamwrap.foes{animation:crewIn .5s both}
.arena-wrap .cine.crew .teamwrap.mine{animation:crewIn .5s .12s both}
.arena-wrap .cine.crew .teamwrap.foes .tok{animation:tokIn .42s both}
.arena-wrap .cine.crew .teamwrap.mine .tok{animation:tokIn .42s both}
@keyframes crewIn{0%{opacity:0}100%{opacity:1}}
@keyframes tokIn{0%{opacity:0;transform:translateY(14px) scale(.94)}
  100%{opacity:1;transform:translateY(0) scale(1)}}

/* =========================== THE FIGHTER CARD ===============================
   Tap any Fighter, yours or theirs, and it opens.

   It exists because the game's central rule is invisible. A Fighter's weapon
   decides HOW it fights, its torso how long it lasts and its headgear how often
   it lands big -- and arms, effects and companion do nothing at all except look
   like something. Nobody can deduce that from a board, and a player who does
   not know it is choosing a Crew on rarity score, which is the one number the
   Arena deliberately refuses to let decide a fight.
   ========================================================================== */
.arena-wrap .fcard{position:fixed;inset:0;z-index:60;display:flex;align-items:center;
  justify-content:center;padding:16px;background:rgba(5,7,10,.78)}
.arena-wrap .fcard[hidden]{display:none}
.arena-wrap .fc-box{background:var(--panel);border:1px solid var(--line);border-radius:5px;
  width:min(560px,100%);max-height:calc(100vh - 32px);overflow:auto;position:relative;
  box-shadow:0 18px 60px rgba(0,0,0,.6)}
.arena-wrap .fc-x{position:absolute;top:6px;right:8px;z-index:2;background:none;border:0;
  color:var(--dim);font-size:20px;line-height:1;padding:4px 8px;cursor:pointer}
.arena-wrap .fc-x:hover{color:var(--ochre)}
.arena-wrap .fc-top{display:flex;gap:12px;padding:12px;border-bottom:1px solid var(--line)}
.arena-wrap .fc-art{position:relative;width:118px;flex:none;aspect-ratio:1;border-radius:3px;
  overflow:hidden;background:var(--panel2)}
.arena-wrap .fc-art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.arena-wrap .fc-art img.bg{object-fit:cover}
.arena-wrap .fc-id{min-width:0;flex:1}
.arena-wrap .fc-id h3{margin:0 0 3px;font-size:16px;letter-spacing:.01em}
.arena-wrap .fc-who{font-size:10px;letter-spacing:.12em;text-transform:uppercase;opacity:.6}
.arena-wrap .fc-gem{display:inline-flex;align-items:center;gap:6px;margin-top:7px;
  padding:3px 9px;border-radius:999px;border:1px solid var(--gemc);font-size:11px}
.arena-wrap .fc-gem b{display:inline-flex;align-items:center;justify-content:center;
  width:19px;height:19px;border-radius:50%;background:var(--gemc);font-size:11px}
.arena-wrap .fc-kit{margin-top:8px;font-size:12px}
.arena-wrap .fc-kit b{color:var(--ochre);font-weight:400}
.arena-wrap .fc-body{padding:11px 12px}
.arena-wrap .fc-h{font-size:9px;letter-spacing:.16em;text-transform:uppercase;opacity:.55;
  margin:0 0 6px}
.arena-wrap .fc-rows{display:grid;gap:5px;margin:0 0 12px}
.arena-wrap .fc-row{display:grid;grid-template-columns:78px 62px 1fr;gap:9px;align-items:baseline;
  font-size:11.5px}
.arena-wrap .fc-row .k{opacity:.6;font-size:10px;letter-spacing:.1em;text-transform:uppercase}
.arena-wrap .fc-row .v{font-variant-numeric:tabular-nums;color:var(--ochre)}
.arena-wrap .fc-row .src{opacity:.7;min-width:0;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .fc-row .src em{font-style:normal;opacity:.55;font-size:10px}
.arena-wrap .fc-cos{font-size:11px;opacity:.55;line-height:1.7}
.arena-wrap .fc-cos b{opacity:.85;font-weight:400}
.arena-wrap .fc-note{margin:10px 0 0;font-size:11px;opacity:.6;line-height:1.6;
  border-top:1px solid var(--line);padding-top:9px}
.arena-wrap .tok{cursor:pointer}
@media (max-width:520px){
  .arena-wrap .fc-top{flex-direction:column;align-items:center;text-align:center}
  .arena-wrap .fc-art{width:150px}
  .arena-wrap .fc-row{grid-template-columns:70px 56px 1fr;gap:7px;font-size:11px}
}

/* Sits over the settled board rather than in a side panel, because the end of
   a battle should land where you were looking. Fades in only once the last
   cascade has come to rest. */
/* MUST COME FIRST AND MUST EXIST. `hidden` gets display:none from the browser's
   own stylesheet, which is the weakest source there is -- the .endcard rule
   below sets display:flex and silently beat it, so this overlay sat over the
   board from page load, covering every gem and swallowing every click. The page
   looked dead and a refresh could not help, because it was never alive.
   The attribute selector outranks the bare class, so it wins. */
.arena-wrap .endcard[hidden]{display:none}
.arena-wrap .endcard{position:absolute;inset:0;z-index:8;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:9px;text-align:center;
  background:rgba(13,15,19,.86);backdrop-filter:blur(3px);animation:ecIn .45s ease-out}
@keyframes ecIn{0%{opacity:0}100%{opacity:1}}
.arena-wrap .ec-title{font-size:clamp(20px,5vw,30px);letter-spacing:.14em;
  text-transform:uppercase;font-weight:700}
/* The card is the board's size now rather than the page's, so on a phone it has
   a couple of hundred pixels less to work with and the text has to give. */
.arena-wrap .endcard{padding:10px}
@media (max-width:1000px){
  .arena-wrap .ec-sub{font-size:11px;max-width:92%}
  .arena-wrap .ec-stats{gap:10px;font-size:9px}
  .arena-wrap .ec-stats b{font-size:14px}
  .arena-wrap .endcard{gap:7px}
}
.arena-wrap .endcard.win .ec-title{color:var(--teal);text-shadow:0 0 26px rgba(0,200,160,.45)}
.arena-wrap .endcard.lose .ec-title{color:var(--blood);text-shadow:0 0 26px rgba(224,70,107,.4)}
.arena-wrap .ec-sub{font-size:12px;color:var(--bone);opacity:.85;max-width:74%}
.arena-wrap .ec-stats{display:flex;gap:16px;flex-wrap:wrap;justify-content:center;
  font-size:10px;color:var(--dim);letter-spacing:.06em;text-transform:uppercase}
.arena-wrap .ec-stats b{display:block;font-size:17px;color:var(--bone);letter-spacing:0;
  font-variant-numeric:tabular-nums}
.arena-wrap .legend{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.arena-wrap .lchip{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--bone);
  padding:4px 9px 4px 4px;border-radius:999px;background:#10131a;
  border:1px solid var(--line);border-left:3px solid var(--lc);white-space:nowrap;
  max-width:100%;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .lchip b{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;
  border-radius:50%;background:var(--lc);font-size:12px;font-weight:400;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12)}
.arena-wrap .lchip.gone{opacity:.32;text-decoration:line-through}
.arena-wrap .reach{font-size:10px;color:var(--dim);margin-top:7px;display:flex;flex-wrap:wrap;gap:4px 14px;
  align-items:baseline}
.arena-wrap .reach b{color:var(--ochre)}
.arena-wrap .reach .terr{color:var(--teal);margin-left:auto;cursor:help;
  border-bottom:1px dotted currentColor;white-space:nowrap}
/* The effect rides along hidden and opens on hover, or on a tap where there is
   no hover to have. nowrap comes off when it opens, or a long note shoves the
   reach line sideways on a phone. */
.arena-wrap .reach .terr .tnote{display:none;font-style:normal;opacity:.85}
.arena-wrap .reach .terr:hover, .arena-wrap .reach .terr.on{white-space:normal}
.arena-wrap .reach .terr:hover .tnote, .arena-wrap .reach .terr.on .tnote{display:inline}
.arena-wrap .reach b{color:var(--ochre)}
/* ---- side ---- */
.arena-wrap .panel{border:1px solid var(--line);border-radius:4px;background:var(--panel);padding:9px;margin-bottom:10px}
.arena-wrap .panel h2{margin:0 0 6px;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
.arena-wrap .logbox{border:1px solid var(--line);border-radius:4px;background:var(--panel);
  margin-top:10px;padding:8px 10px}
.arena-wrap .logbox summary{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);
  cursor:pointer;list-style:none}
.arena-wrap .logbox summary::-webkit-details-marker{display:none}
.arena-wrap .logbox summary:before{content:'▸ ';}
.arena-wrap .logbox[open] summary:before{content:'▾ ';}
.arena-wrap #log{height:150px;overflow:auto;font-size:10px;line-height:1.55;margin-top:6px}
.arena-wrap #log div{padding:1px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.arena-wrap .log-you{color:var(--teal)}
.arena-wrap .log-foe{color:var(--blood)}
.arena-wrap .log-sys{color:var(--dim)}
.arena-wrap .log-big{color:var(--ochre)}
.arena-wrap .combo{position:absolute;left:50%;top:38%;transform:translateX(-50%);z-index:9;
  font-size:26px;font-weight:700;color:var(--ochre);text-shadow:0 3px 14px #000;opacity:0;pointer-events:none}
.arena-wrap .combo.on{animation:cb 1s}
@keyframes cb{0%{opacity:0;transform:translate(-50%,10px) scale(.8)}20%{opacity:1;transform:translate(-50%,0) scale(1.1)}70%{opacity:1}100%{opacity:0;transform:translate(-50%,-14px)}}
.arena-wrap .ec-acts{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.arena-wrap .ec-acts .btn[hidden]{display:none}
.arena-wrap .top .btn[hidden]{display:none}
.arena-wrap .over{text-align:center;padding:14px}
.arena-wrap .over h2{font-size:15px;color:var(--ochre);margin:0 0 4px}
/* ---- Arena shell ------------------------------------------------------------
   Written against dhcfighters.php's idioms rather than invented again: the same
   masthead, the same stat tiles, the same bordered panel with an uppercase
   caption bar and a padded body. Two sibling pages in the same feature should
   not each have their own idea of what a panel is.
   -------------------------------------------------------------------------- */
.arena-wrap{max-width:100%;margin:0 auto;padding:14px;overflow-x:clip;text-align:left}
/* One row across the full width -- blurb takes what it needs, counts sit hard
   right -- the way the Fighters masthead does it. */
.arena-wrap .a-masthead{display:flex;flex-wrap:wrap;align-items:center;
  justify-content:space-between;gap:18px 32px;margin:0 0 16px}
.arena-wrap .a-intro-txt{flex:1 1 420px;min-width:0}
.arena-wrap .a-head{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin:0 0 4px}
.arena-wrap .a-head h1{font-size:22px;letter-spacing:.02em;margin:0}
.arena-wrap .a-sub{font-size:11.5px;opacity:.65;line-height:1.6;margin:0}
.arena-wrap .a-sub b{opacity:.95}
.arena-wrap .a-sub a{color:var(--ochre)}
/* flex:0 1 auto + min-width:0 so the tiles can shrink and wrap instead of
   pushing the page sideways on a phone -- the same trap .dhcf-stats documents. */
.arena-wrap .a-stats{display:flex;gap:8px;flex-wrap:wrap;margin:0;flex:0 1 auto;min-width:0}
.arena-wrap .a-stat{border:1px solid var(--line);border-radius:3px;padding:7px 12px;
  min-width:0;flex:0 1 auto}
.arena-wrap .a-stat b{display:block;font-size:17px;font-variant-numeric:tabular-nums;white-space:nowrap}
.arena-wrap .a-stat span{font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;
  opacity:.6;white-space:nowrap}
@media (max-width:520px){
  .arena-wrap .a-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));width:100%}
  .arena-wrap .a-stat{padding:6px 8px}
  .arena-wrap .a-stat b{font-size:15px}
}
/* The live-match box. Lives under the rival list and is empty most of the
   time, so it is hidden rather than styled to look deliberate when idle. */
.arena-wrap .a-live{margin-top:10px;border:1px solid var(--ochre);border-radius:3px;
  padding:10px 12px;font-size:12px;background:rgba(245,166,35,.06)}
.arena-wrap .a-live b{color:var(--ochre)}
.arena-wrap .a-live .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px}
.arena-wrap .a-live .note{display:block;margin-top:6px;font-size:11px;opacity:.65;line-height:1.5}
/* The turn clock, in the battle toolbar beside the turn flag. Tabular so the
   digits do not jitter the row every second. */
.arena-wrap .lclock{font-size:10px;letter-spacing:.1em;text-transform:uppercase;
  padding:3px 9px;border-radius:999px;border:1px solid var(--line);color:var(--dim);
  font-variant-numeric:tabular-nums}
.arena-wrap .lclock.low{border-color:var(--blood);color:var(--blood)}
.arena-wrap .a-block-acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:10px}
	.arena-wrap .a-block-note{font-size:11px;color:#5a7888;line-height:1.5}
	.arena-wrap .a-block{border:1px solid var(--ochre);background:rgba(0,200,160,.07);
  border-radius:3px;padding:9px 12px;font-size:12px;margin-bottom:14px}
.arena-wrap .a-block a{color:var(--ochre)}
.arena-wrap .a-panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
  gap:14px;align-items:start}
.arena-wrap .a-panel{border:1px solid var(--line);border-radius:3px;overflow:hidden}
.arena-wrap .a-panel h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;
  text-transform:uppercase;opacity:.65;border-bottom:1px solid var(--line);
  display:flex;flex-wrap:wrap;justify-content:space-between;gap:2px 10px}
.arena-wrap .a-panel h2 span{opacity:.75;letter-spacing:.08em;white-space:nowrap}
.arena-wrap .a-panel .body{padding:10px 12px}
/* The two ladder windows inside one panel. The h3 is quieter than the panel's
   own h2 -- it is a divider within a thing, not another thing -- and the
   second section carries the rule, so the first does not start with one. */
.arena-wrap .a-lsec + .a-lsec{margin-top:14px;border-top:1px solid var(--line);padding-top:12px}


.arena-wrap .a-lsec h3{margin:0 0 6px;font-size:9px;letter-spacing:.14em;text-transform:uppercase;
  opacity:.55;font-weight:600;display:flex;gap:7px;align-items:baseline}
.arena-wrap .a-lsec h3 span{opacity:.7;letter-spacing:.08em;font-weight:400}
/*
 * THE CHIP ROW. Sort, Fights like and Record all live in .a-tools now --
 * they are three ways of narrowing one list, and as two separate rows with
 * a note each they read as unrelated tools stacked up.
 *
 * (.a-pf-filter was that second row's class. It briefly had no rules at all
 * and took the browser's default button -- light pills with blue text under
 * a row of dark ones -- then shared these. It has no markup left.)
 */
.arena-wrap .a-tools{display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin:0 0 9px;
  font-size:11px}
.arena-wrap .a-tools .lbl{font-size:9px;letter-spacing:.14em;text-transform:uppercase;opacity:.5}
.arena-wrap .a-tools .sep{width:1px;height:14px;background:var(--line);margin:0 4px}
.arena-wrap .a-tools button{font:inherit;font-size:11px;background:none;color:inherit;
  border:1px solid var(--line);border-radius:999px;padding:3px 9px;cursor:pointer;opacity:.75}
.arena-wrap .a-tools button:hover{border-color:var(--ochre);opacity:1}
.arena-wrap .a-tools button.on{border-color:var(--ochre);color:var(--ochre);
  background:rgba(0,200,160,.08);opacity:1}
/* The count inside a chip, quieter than its label. */
.arena-wrap .a-tools button b{font-weight:600;opacity:.85;margin-left:2px}
/* (.a-pf-note was this row's own sentence. The one explanation covering all
   three filters is a plain .a-sub above them now, so the class is gone.) */
/*
 * WHAT THE FILTER ACTUALLY DOES. The handler toggles this class on a card
 * and nothing else; without a rule the class went on, came off, and the
 * grid never changed -- the filter looked inert because visually it was.
 *
 * Not prefixed a- like the rest of this page's classes, which is why the
 * harness check for unstyled classes walked straight past it. It reads
 * classList.toggle() calls now as well as class attributes.
 */
.arena-wrap .a-card.pf-hid{display:none}
.arena-wrap .a-card .st{display:flex;align-items:center;gap:6px;font-size:9.5px;opacity:.75;
  font-variant-numeric:tabular-nums;margin-top:2px}
.arena-wrap .a-card .st em{font-style:normal;margin-left:auto;width:13px;height:13px}
.arena-wrap .a-tools button .ico{width:13px;height:13px;vertical-align:-2px}
.arena-wrap .a-pick{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:7px}
.arena-wrap .a-card{border:1px solid var(--line);border-radius:3px;padding:5px;
  cursor:pointer;position:relative;text-align:left}
.arena-wrap .a-card:hover{background:rgba(0,200,160,.05);border-color:var(--ochre)}
.arena-wrap .a-card.sel{border-color:var(--ochre);box-shadow:inset 0 0 0 1px var(--ochre);
  background:rgba(0,200,160,.08)}
/* Dimmed because it cannot take a RANKED battle, not because it cannot be
   chosen -- a live match will have it. So no cursor:not-allowed, and a picked
   one comes back to full strength so the Crew reads as three, not two and a
   ghost. */
.arena-wrap .a-card.out{opacity:.45}
.arena-wrap .a-card.out.sel{opacity:1}
.arena-wrap .a-card .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:var(--panel2)}
.arena-wrap .a-card .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.arena-wrap .a-card .art img.bg{object-fit:cover}
.arena-wrap .a-card .nm{font-size:9.5px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .a-card .sc{font-size:9px;opacity:.6;font-variant-numeric:tabular-nums}
.arena-wrap .a-card .wl{font-size:8.5px;opacity:.5}
.arena-wrap .a-card .pk{position:absolute;top:4px;left:4px;z-index:2;font-size:8px;
  letter-spacing:.1em;text-transform:uppercase;padding:2px 5px;border-radius:2px;
  background:var(--ink);border:1px solid var(--ochre);color:var(--ochre)}
/* READABLE, which the first version was not: 9px of --blood (#e0466b) on a
   dark scrim over busy artwork is red-on-dark at the smallest size on the page,
   and red is the one hue that loses most contrast against a near-black ground.
   The word is bone, the clock is ochre -- and ochre is the right colour for it
   anyway, because a recovering Fighter is a wait, not an error. */
.arena-wrap .a-card .recover{position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:3px;border-radius:3px;text-align:center;
  padding:4px;background:rgba(8,10,14,.85);
  font-size:9.5px;letter-spacing:.11em;text-transform:uppercase;color:var(--bone);
  text-shadow:0 1px 3px #000}
.arena-wrap .a-card .recover b{font-weight:400;font-size:12px;letter-spacing:0;
  text-transform:none;color:var(--ochre);font-variant-numeric:tabular-nums}
/* A Fighter being brought back reads differently from one catching its breath.
   The word is longer, so it gets room to wrap rather than being squeezed. */
.arena-wrap .a-card .recover.fell{letter-spacing:.06em}
.arena-wrap .a-card .recover.fell b{color:var(--warn)}
.arena-wrap .a-card.out:hover{background:none;border-color:var(--line)}
/* ---- paging. A staker with two hundred Fighters should not be handed two
   hundred cards, and the cards are the expensive part: eight <img> layers each.
   Off-page cards are display:none rather than removed, which is what makes the
   picks survive a page change -- and a browser does not fetch images inside a
   display:none subtree, so the pages you are not looking at cost nothing. */
.arena-wrap .a-card.off{display:none}
.arena-wrap .a-pager{display:flex;align-items:center;justify-content:center;gap:10px;
  margin-top:10px;font-size:11px;opacity:.7}
.arena-wrap .a-pager button{background:var(--panel2);color:var(--bone);border:1px solid var(--line);
  border-radius:3px;padding:3px 10px;font:inherit;cursor:pointer}
.arena-wrap .a-pager button:disabled{opacity:.35;cursor:default}
.arena-wrap .a-pager button:not(:disabled):hover{border-color:var(--ochre);color:var(--ochre)}
/* THE PICKS STAY ON SCREEN. Choosing three Fighters and then paging away from
   them left the formation invisible at the moment you most need it -- the
   order IS the formation. */
.arena-wrap .a-picked{display:flex;flex-wrap:wrap;gap:5px;margin:0 0 9px;min-height:23px;
  align-items:center;font-size:11px;opacity:.85}
.arena-wrap .a-picked .chip{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;
  border:1px solid var(--ochre);border-radius:999px;color:var(--bone);background:rgba(0,200,160,.08);
  cursor:pointer;max-width:180px}
.arena-wrap .a-picked .chip b{font-weight:400;color:var(--ochre);font-size:8.5px;
  letter-spacing:.1em;text-transform:uppercase;flex:none}
.arena-wrap .a-picked .chip s{text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.arena-wrap .a-picked .chip i{font-style:normal;opacity:.55;flex:none}
/* ---- DHC Bosses panel ---- */
.arena-wrap .a-bosses{display:flex;gap:8px;margin:0 0 10px}
.arena-wrap .a-boss{flex:1 1 0;min-width:0;text-align:center}
.arena-wrap .a-boss img{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:5px;
  background:#0d2030;display:block;transform:translateZ(0)}
.arena-wrap .a-boss.dead img{filter:grayscale(100%);opacity:.45}
/*
 * A WAVE LOOKS LIKE WHAT IT IS.
 *
 * The crew is dropped and gets back up the same week, angrier. There is one
 * flat picture per boss and no appetite for three more sets of art, so the
 * wave is a COLOURWAY of the picture that is already there.
 *
 * HUE ONLY. The first pass also pushed saturation, contrast and brightness,
 * and that reads as a filter laid over the art rather than as the art -- the
 * tonality shifts and the piece stops looking like itself. A pure
 * hue-rotate() leaves every value and every relationship exactly where the
 * artist put them and moves only the colour, which is what a colourway is.
 *
 * Even fifths around the wheel, walked in one direction, so the series reads
 * as a sequence rather than four unrelated colours.
 *
 * Wave 1 is deliberately unrotated: the first crew of the week is the
 * baseline the others are read against.
 *
 * The rotation is scoped from the WRAPPER so the dead state below still
 * wins -- a downed boss is grey whatever wave it belongs to.
 */
.arena-wrap .a-bosses[data-wave="2"] .a-boss img{filter:hue-rotate(72deg)}
.arena-wrap .a-bosses[data-wave="3"] .a-boss img{filter:hue-rotate(144deg)}
.arena-wrap .a-bosses[data-wave="4"] .a-boss img{filter:hue-rotate(216deg)}
.arena-wrap .a-bosses[data-wave="5"] .a-boss img{filter:hue-rotate(288deg)}
.arena-wrap .a-bosses[data-wave="2"] .a-boss.dead img,
.arena-wrap .a-bosses[data-wave="3"] .a-boss.dead img,
.arena-wrap .a-bosses[data-wave="4"] .a-boss.dead img,
.arena-wrap .a-bosses[data-wave="5"] .a-boss.dead img{filter:grayscale(100%)}
/* The wave itself, stated. A colourway tells you something changed; only a
   number tells you it is the third time this week. Neutral chrome on
   purpose -- the art carries the identity, and a badge painted to guess at
   a rotated hue would only ever nearly match it. */
.arena-wrap .a-wave{display:inline-flex;align-items:center;gap:6px;margin:0 0 8px;
  padding:3px 9px;border-radius:3px;font-size:9px;letter-spacing:.14em;
  text-transform:uppercase;border:1px solid var(--line);color:var(--dim);
  background:var(--panel2)}
.arena-wrap .a-wave b{font-weight:600;letter-spacing:.02em;font-size:11px;color:var(--ochre)}
/* AND THE SAME COLOURWAY ON THE BOARD. A crew that is one colour on the
   panel and another in the fight is two different crews as far as the player
   is concerned. Scoped to [data-s="boss"] so a ranked opponent's Fighters
   are never touched by it, whatever the column happens to be carrying. */
.arena-wrap .teamcol.foes[data-wave="2"] img[data-s="boss"]{filter:hue-rotate(72deg)}
.arena-wrap .teamcol.foes[data-wave="3"] img[data-s="boss"]{filter:hue-rotate(144deg)}
.arena-wrap .teamcol.foes[data-wave="4"] img[data-s="boss"]{filter:hue-rotate(216deg)}
.arena-wrap .teamcol.foes[data-wave="5"] img[data-s="boss"]{filter:hue-rotate(288deg)}
.arena-wrap .a-bname{font-size:9px;letter-spacing:.06em;text-transform:uppercase;
  color:var(--ochre);margin:5px 0 3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.arena-wrap .a-bbar{height:5px;border-radius:3px;background:rgba(255,255,255,.10);overflow:hidden}
.arena-wrap .a-bbar i{display:block;height:100%;background:var(--warn);border-radius:3px}
.arena-wrap .a-boss.dead .a-bbar i{background:rgba(255,255,255,.18)}
.arena-wrap .a-bhp{font-size:9px;color:var(--dim);margin-top:3px;font-variant-numeric:tabular-nums}
.arena-wrap .a-bblurb{font-size:9px;color:var(--dim);margin-top:4px;line-height:1.45}
.arena-wrap .a-bsum{font-size:11px;color:var(--dim);margin:0 0 6px}
.arena-wrap .a-bsum b{color:#e8eaed}
/* ---- the trait cache ---- */
.arena-wrap .a-cache{display:flex;gap:4px;margin:0 0 6px}
.arena-wrap .a-slot{flex:1 1 0;min-width:0;text-align:center}
.arena-wrap .a-slot i{display:block;height:26px;border-radius:4px;
  border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.03)}
.arena-wrap .a-slot span{display:block;font-size:8px;letter-spacing:.05em;text-transform:uppercase;
  color:var(--dim);margin-top:3px}
.arena-wrap .a-slot.filled i{background:var(--teal);border-color:var(--teal);
  box-shadow:0 0 7px rgba(0,200,160,.45)}
.arena-wrap .a-slot.filled span{color:var(--teal)}
.arena-wrap .a-slot.today i{border-color:var(--ochre);border-style:dashed;
  animation:aSlotPulse 1.8s ease-in-out infinite}
.arena-wrap .a-slot.today span{color:var(--ochre)}
.arena-wrap .a-slot.missed i{background:transparent;border-style:dotted;opacity:.4}
.arena-wrap .a-slot.missed span{opacity:.4}
@keyframes aSlotPulse{0%,100%{opacity:.55}50%{opacity:1}}
@media (prefers-reduced-motion:reduce){ .arena-wrap .a-slot.today i{animation:none;opacity:.9} }
.arena-wrap .a-cachenote{margin-bottom:10px}
/* The round strip. Its own row rather than a clause in a paragraph,
   because it answers "why can I send more" at a glance and that question
   was asked twice of a sentence. */
.arena-wrap .a-round{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 14px;
  margin:0 0 8px;padding:6px 9px;border:1px solid var(--line);border-radius:3px;
  background:var(--panel2);font-size:10px;letter-spacing:.06em;text-transform:uppercase;
  color:var(--dim)}
.arena-wrap .a-round b{font-weight:600;letter-spacing:0;font-size:12px;color:#e8eaed;
  font-variant-numeric:tabular-nums}
.arena-wrap .a-round .a-rclk{margin-left:auto}
.arena-wrap .a-round .a-rclk b{color:var(--ochre)}
.arena-wrap .a-round.turned{border-color:var(--ochre)}
.arena-wrap .a-round.turned .a-rclk b{color:var(--teal)}
@media (max-width:520px){ .arena-wrap .a-round .a-rclk{margin-left:0;width:100%} }
@media (max-width:420px){ .arena-wrap .a-bblurb{display:none} }
/* Removing is the one destructive affordance on this screen, and --blood is
   the platform's own accent here, so it needs a colour of its own to read as
   "this takes something away". */
.arena-wrap .a-picked .chip:hover{border-color:var(--warn);background:rgba(208,70,58,.10)}
.arena-wrap .a-picked .chip:hover i{color:var(--warn);opacity:1}
/* padding-top so the Arch nemesis tab has somewhere to be. It is positioned
   at top:-7px, i.e. OUTSIDE its row, and this is a 330px scrollport with
   overflow:auto -- which clips to its padding box, so with no padding the
   tab on the first row was sliced in half. 9px gives it 2px of clearance.
   The nemesis is always the first row, so this is the only row it can
   happen to. */
/*
 * THE WINDOW IS THE SCREEN'S, NOT A NUMBER. It was max-height:330px, which
 * scrolled nine rivals inside a page that already scrolls while the column
 * had several hundred pixels of nothing under it -- reported exactly that way.
 *
 * But the cap was not pointless, and removing it outright is the wrong fix:
 * Enter the Arena and Practice sit directly BELOW this list, and
 * dhca_opponents() can return 24 rivals, so an unbounded list puts the page's
 * primary action a thousand pixels down for anyone with a full roster of them.
 *
 * So it takes the height the viewport actually has. The 340px is everything
 * else in the column that has to stay reachable -- the page header and stats
 * strip above, the panel heading, and the two buttons plus the practice note
 * below. Measured on the reporter's own viewport: 9 rivals no longer scroll,
 * and a full 24 still leaves both buttons on screen.
 *
 * The padding-top is for the ARCH NEMESIS badge, which sits above the first
 * row's top edge; without it the badge is clipped by this scrollport.
 */
.arena-wrap .a-foes{display:flex;flex-direction:column;gap:5px;padding-top:9px;
  max-height:calc(100vh - 340px);overflow:auto}
.arena-wrap .a-foe{display:flex;align-items:center;gap:10px;padding:7px 9px;border:1px solid var(--line);
  border-radius:3px;cursor:pointer}
.arena-wrap .a-foe:hover{border-color:var(--ochre);background:rgba(0,200,160,.05)}
.arena-wrap .a-foe.sel{border-color:var(--ochre);box-shadow:inset 0 0 0 1px var(--ochre);
  background:rgba(0,200,160,.08)}
.arena-wrap .a-foe img{width:28px;height:28px;border-radius:50%;flex:none;background:var(--panel2)}
.arena-wrap .a-foe .n{flex:1;min-width:0;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .a-foe .m{font-size:10px;opacity:.6;white-space:nowrap}
/* WHAT THE FIGHT PAYS. Sits after the Fighter count because the count is
   who they are and this is what they are worth -- reading the row left to
   right should answer "who" then "why bother". Dim on a level fight so
   the ladder is legible as a ladder: the eye finds the bright rows. */
.arena-wrap .a-foe .odds{font-size:10px;opacity:.45;white-space:nowrap;flex:none}
.arena-wrap .a-foe.up .odds{opacity:.85;color:var(--ochre)}
.arena-wrap .a-foe.top .odds{opacity:1;color:var(--bone);font-weight:600}
/* The whole row lifts at the top band, so a player scanning the list sees
   the best fight available without reading a single percentage. */
.arena-wrap .a-foe.top{border-color:rgba(232,177,76,.45)}

/* THE ARCH NEMESIS ROW.
   Deliberately a DIFFERENT colour from .top, which is the ochre reward band.
   Those two mean unrelated things -- one is what the fight pays, the other is
   who it is against -- and a nemesis who also happens to pay well must read
   as both at once rather than as a brighter version of one. The row wraps
   because it carries a second line the others do not have. */
.arena-wrap .a-foe.nem{border-color:rgba(224,70,107,.55);background:rgba(224,70,107,.06);
  flex-wrap:wrap;position:relative}
/*
 * HOVER AND SELECT ARE THE SAME EVERYWHERE, INCLUDING HERE.
 *
 * .a-foe.nem is declared AFTER .a-foe.sel and carries the same specificity,
 * so it won on background and border-color and a selected nemesis kept its
 * crimson while every other rival turned ochre -- selection read as a
 * different thing depending on which row you clicked. Reported as "it throws
 * me off", and it is a cascade-order accident rather than a decision.
 *
 * The crimson stays where it belongs: the resting border and the ARCH NEMESIS
 * badge, which is what marks the row out. What you DID to the row is feedback
 * about the interaction, and that has to mean the same thing on every row.
 * Hover is included deliberately -- fixing only the click would give this one
 * row a crimson hover that snaps to ochre on selection, which is a bigger
 * jump than the problem.
 */
.arena-wrap .a-foe.nem:hover{border-color:var(--ochre);background:rgba(0,200,160,.05)}
.arena-wrap .a-foe.nem.sel{border-color:var(--ochre);box-shadow:inset 0 0 0 1px var(--ochre);
  background:rgba(0,200,160,.08)}
/* Right, not left: on the left it sat directly above the avatar and read as
   a label ON the picture. On the right it has the row's empty end to itself.

   CONTRAST, MEASURED. Near-black on the bright #e0466b is 4.78:1 -- nominally
   AA, and nowhere near enough for 8.5px uppercase, which is how it was
   reported. White needs a DARKER fill to work at all: white on #e0466b is
   3.99:1 and actually worse. White on #a31f42 is 7.42:1, and the extra half
   pixel of size with less tracking does the rest. */
/*
 * THE CHIPS. One per flag, each keeping its OWN colour -- a card can wear
 * two (whoever you fight most is usually whoever you have the most record
 * against) and blending purple with red gives maroon, which reads as
 * neither of them. Two chips, two colours, one meaning each.
 *
 * A row rather than a single absolutely-positioned tag, so a second chip
 * has somewhere to go instead of landing on top of the first.
 */
.arena-wrap .a-foe .nemtags{position:absolute;top:-7px;right:9px;display:flex;gap:4px;
  flex-wrap:wrap;justify-content:flex-end;max-width:calc(100% - 18px)}
.arena-wrap .a-foe .nemtag{position:static;font-size:9px;letter-spacing:.04em;
  text-transform:uppercase;font-weight:700;color:#fff;background:#a31f42;
  padding:1.5px 6px;border-radius:2px;white-space:nowrap}
/* Purple: you two fight constantly. A volume fact, not a warning. */
.arena-wrap .a-foe .nemtag.t-nemesis{background:#7a3ed8}
/* Red: they beat you. This one is meant to give somebody pause. */
.arena-wrap .a-foe .nemtag.t-tormentor{background:#c42348}
/* Yellow: you beat them. Dark text, because white on yellow is unreadable
   -- #e0b500 against #07111d is the only pairing here that needs it. */
.arena-wrap .a-foe .nemtag.t-duck{background:#e0b500;color:#2a1d00}
/* The card follows whichever chip should change what you do next. */
.arena-wrap .a-foe.lead-nemesis{border-color:rgba(122,62,216,.55);background:rgba(122,62,216,.07)}
.arena-wrap .a-foe.lead-tormentor{border-color:rgba(196,35,72,.6);background:rgba(196,35,72,.07)}
.arena-wrap .a-foe.lead-duck{border-color:rgba(224,181,0,.5);background:rgba(224,181,0,.06)}
/* EVERY NUMBER CARRIES ITS SUBJECT. This was "21-10", then "Won 21 · Lost
   10", and neither said WHOSE -- reported twice. The labels are quiet and
   the figures are loud, so it scans as three facts rather than a sentence.
   No nowrap: three labelled stats will not fit one line on a phone, and
   wrapping is better than clipping a number off the end. */
/* opacity .85, not .7: at .7 the labels land at 7.6:1 and looked washed
   beside figures that were themselves too dim. */
/*
 * THE HISTORY TAKES ITS OWN LINE, AND ONLY IT.
 *
 * flex:1 0 100% breaks the row, so whatever follows this in the markup
 * is pushed down with it -- which is how the odds ended up on a third
 * line, left-aligned, while every other row kept them at the right of
 * the first. The eye should not have to scan sideways to compare a
 * column. The odds are ordered BEFORE this now, so the first line of a
 * rivalry row is laid out exactly like a plain one.
 */
.arena-wrap .a-foe .nemrec{flex:1 0 100%;font-size:10.5px;color:var(--bone);opacity:.85;
  font-weight:400;padding-left:38px;line-height:1.35;margin-top:1px}
.arena-wrap .a-foe .nemrec em{font-style:normal;white-space:nowrap}
/* #e0466b measured 4.12:1 on this card -- under the 4.5 floor, so the figures
   were the LEAST readable thing on the row despite being the point of it.
   #ff6b8a is 6.06:1 and still unmistakably the nemesis colour. The fill
   behind the row stays #e0466b: that is a border and a wash, not text. */
.arena-wrap .a-foe .nemrec b{color:#ff6b8a;font-weight:700;opacity:1;margin-left:2px}
/* The separator carries the only gap between stats, so the spacing is in one
   place rather than split between a margin here and a space in the markup. */
.arena-wrap .a-foe .nemrec i{font-style:normal;opacity:.3;padding:0 5px}
@media (max-width:560px){
  .arena-wrap .a-foe .nemrec{padding-left:0}
}
@media (max-width:560px){
  /* The name gives up its ellipsis before the odds give up their line:
     a truncated username still identifies a rival, a truncated
     percentage is noise. */
  .arena-wrap .a-foe .m{display:none}
}
/* Already paid out today. Dimmed, not disabled: the rematch still counts for
   the ladder and the win record, so this is information and not a block.
   Namespaced under .a-foe like everything else here -- a bare .paid would be
   one more global class in a stylesheet that already owns .top. */
.arena-wrap .a-foe.paid{opacity:.62}
.arena-wrap .a-foe.paid:hover,.arena-wrap .a-foe.paid.sel{opacity:1}
.arena-wrap .a-foe .paidtag{font-size:8.5px;letter-spacing:.08em;text-transform:uppercase;
  white-space:nowrap;color:var(--dim);border:1px solid var(--line);border-radius:2px;
  padding:2px 5px;flex:none}
/*
 * THE RIVAL LIST SCROLLED IN BOTH DIRECTIONS ON A PHONE.
 *
 * Vertically because .a-foes is a 330px window with overflow:auto -- a
 * short scroller inside a page that already scrolls, which is the same
 * trap the DHC trait grid had. Horizontally because each row is a flex
 * line whose children refuse to shrink: .m and .paidtag are both
 * white-space:nowrap, so the row's min-content width is the avatar plus
 * the full "35 Fighters - best 2,176" plus the full "Beaten today - no
 * trait available". Past about 500px of content that exceeds the column, .n
 * ellipsises away to nothing (which is why the names vanished) and
 * overflow:auto turns the remainder into a sideways scroll.
 *
 * So on a phone: no window, and the row becomes a grid that stacks the
 * three pieces beside the avatar instead of fighting for one line. The
 * avatar spans the rows so the text block aligns under itself.
 */
@media (max-width:700px){
  .arena-wrap .a-foes{max-height:none;overflow:visible}
  .arena-wrap .a-foe{display:grid;grid-template-columns:28px minmax(0,1fr);
    column-gap:10px;row-gap:2px;align-items:center}
  .arena-wrap .a-foe img{grid-row:1 / span 2;align-self:start}
  .arena-wrap .a-foe .n{grid-column:2;white-space:normal}
  .arena-wrap .a-foe .m{grid-column:2;white-space:normal}
  /* justify-self so the pill is its own width rather than the column's. */
  .arena-wrap .a-foe .paidtag{grid-column:2;justify-self:start;white-space:normal}
  /* THE NEMESIS LINE NEEDS PLACING TOO. Every child of this grid is assigned
     column 2 explicitly, so one that is not gets auto-placed into the 28px
     avatar column and crushed. Its flex:1 0 100% means nothing here, and the
     padding that indents it past the avatar in the flex layout would double
     the gap, so both are reset. */
  .arena-wrap .a-foe .nemrec{grid-column:2;padding-left:0;white-space:normal}
  /* AND THE ODDS, for the same reason -- it was the one child never
     placed, so it auto-placed into the 28px avatar column and rendered
     as a crushed stack of single characters. */
  .arena-wrap .a-foe .odds{grid-column:2;white-space:normal}
}
.arena-wrap .a-go{margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
/* The platform's buttons, not the game's. .btn inside #arenaBattle keeps the
   monospace board styling; out here it should look like every other control on
   Skulliance. */
.arena-wrap .a-go .btn{font:inherit;border-radius:3px;padding:7px 14px;
  background:var(--panel2);color:var(--bone);border:1px solid var(--line);cursor:pointer}
.arena-wrap .a-go .btn.go{background:rgba(0,200,160,.12);border-color:var(--ochre);color:var(--ochre)}
.arena-wrap .a-go .btn.go:hover:not(:disabled){background:rgba(0,200,160,.2)}
.arena-wrap .a-go .btn:disabled{opacity:.4;cursor:default}
.arena-wrap .a-lad{width:100%;border-collapse:collapse;font-size:12px}
.arena-wrap .a-lad th{text-align:left;font-size:9px;letter-spacing:.12em;text-transform:uppercase;
  opacity:.6;font-weight:400;padding:5px 7px;border-bottom:1px solid var(--line)}
.arena-wrap .a-lad td{padding:6px 7px;border-bottom:1px solid var(--line)}
.arena-wrap .a-lad tr:last-child td{border-bottom:0}
.arena-wrap .a-lad tbody tr:hover{background:rgba(0,200,160,.05)}
.arena-wrap .a-lad .r{opacity:.65;font-variant-numeric:tabular-nums}
.arena-wrap .a-lad .me{color:var(--ochre)}
.arena-wrap .a-lad .me .r{opacity:1}
/* ---- the week's Crews, a full-width row under the panels ---- */
.arena-wrap .a-feed{margin-top:14px;border:1px solid var(--line);border-radius:3px;overflow:hidden}
.arena-wrap .a-feed h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;
  text-transform:uppercase;color:var(--dim);background:var(--panel2);
  border-bottom:1px solid var(--line);font-weight:600}
.arena-wrap .a-feed .body{padding:4px 12px 10px}
.arena-wrap .a-run{display:flex;align-items:center;gap:14px;padding:8px 0;
  border-bottom:1px solid var(--line)}
.arena-wrap .a-run:last-child{border-bottom:0}
.arena-wrap .a-runwho{display:flex;align-items:center;gap:7px;min-width:0;
  flex:0 0 170px;font-size:12px;color:#e8eaed}
.arena-wrap .a-runwho span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.arena-wrap .a-av{width:24px;height:24px;border-radius:50%;object-fit:cover;flex:0 0 auto;
  border:1px solid var(--line)}
/* The Crew. Fixed squares so every row lines up whatever the art is. */
.arena-wrap .a-runcrew{display:flex;gap:4px;flex:0 0 auto}
/* A STACK, not one picture: the layers sit on top of each other inside the
   tile, which is why it is positioned and they are absolute. */
.arena-wrap .a-rf{position:relative;display:block;width:38px;height:38px;border-radius:3px;
  overflow:hidden;border:1px solid var(--line);background:var(--panel2)}
.arena-wrap .a-rf img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
/*
 * FELL IS GREY, STANDING IS FULL COLOUR AND A LIT EDGE.
 *
 * The share card greys a fallen Fighter with grayscale + opacity .45, and
 * copying that here was wrong: it is tuned for a 180px tile, and at 38px it
 * erased the Crew completely. Rendered the realistic case -- almost every
 * run is a wipe, so almost every row is three greyed Fighters -- and the
 * whole feed came out as unreadable mush, which defeats the one thing it is
 * for, showing what people are throwing at the bar.
 *
 * So the dimming is lighter and the real signal moved to the BORDER, which
 * survives at thumbnail size where a saturation difference does not. A
 * survivor is the rare thing here and now it is the one that glows.
 */
.arena-wrap .a-rf.fell img{filter:grayscale(100%);opacity:.72}
.arena-wrap .a-rf:not(.fell){border-color:var(--teal);box-shadow:0 0 0 1px rgba(0,200,160,.25)}
.arena-wrap .a-runnum{flex:0 0 76px;text-align:right;font-size:11px;color:var(--dim)}
.arena-wrap .a-runnum b{display:block;font-size:13px;color:#e8eaed;font-weight:600;
  font-variant-numeric:tabular-nums}
.arena-wrap .a-runnum span{font-size:9px;letter-spacing:.1em;text-transform:uppercase;opacity:.7}
.arena-wrap .a-runout{margin-left:auto;font-size:9px;letter-spacing:.11em;text-transform:uppercase;
  color:var(--dim);white-space:nowrap}
.arena-wrap .a-runout.ok{color:var(--teal)}
@media (max-width:640px){
  /* Two lines on a phone: who and the Crew, then the figures. The row is
     six things wide and none of them survives a 400px squeeze. */
  .arena-wrap .a-run{flex-wrap:wrap;gap:8px 10px}
  .arena-wrap .a-runwho{flex:1 1 100%}
  .arena-wrap .a-runnum{flex:0 0 auto;text-align:left}
  .arena-wrap .a-runout{margin-left:0}
}
.arena-wrap #arenaBattle{display:none;margin-top:12px}
.arena-wrap #arenaBattle.on{display:block}
.arena-wrap .a-busy{opacity:.55;pointer-events:none}

/* ---- the Guide ----
 *
 * Scoped to #ag-veil rather than hung off .arena-wrap: it is a fixed
 * overlay and belongs to the page, not to the board. The prose inside is
 * rendered from skullpaper/games-dhc-arena.md, so the selectors have to
 * cover whatever Markdown produces -- h2/h3/p/ul/ol/table/blockquote --
 * rather than classes this file controls.
 */
#ag-veil{position:fixed;inset:0;z-index:9997;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.88);
  padding:calc(env(safe-area-inset-top,0px) + 18px) 18px calc(env(safe-area-inset-bottom,0px) + 18px)}
#ag-veil.on{display:flex}
#ag-box{width:min(760px,100%);max-height:100%;display:flex;flex-direction:column;
  background:#0a1929;border:1px solid #1b3346;border-radius:5px;box-shadow:0 24px 70px rgba(0,0,0,.6)}
#ag-head{display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid #1b3346;
  flex:0 0 auto}
#ag-head h2{margin:0;font-size:15px;letter-spacing:.04em;flex:1 1 auto;color:#e8eaed}
#ag-body{overflow:auto;padding:4px 20px 20px;-webkit-overflow-scrolling:touch;color:#c7d3dc;
  font-size:13.5px;line-height:1.72}
#ag-body h2{font-size:15px;color:#00c8a0;margin:22px 0 6px;letter-spacing:.03em}
#ag-body h3{font-size:13px;color:#e8eaed;margin:16px 0 4px}
#ag-body h2:first-child{margin-top:14px}
#ag-body p,#ag-body ul,#ag-body ol{margin:0 0 10px}
#ag-body ul,#ag-body ol{padding-left:20px}
#ag-body li{margin:0 0 4px}
#ag-body strong{color:#e8eaed}
#ag-body a{color:#00c8a0}
#ag-body code{background:#071320;border:1px solid #1b3346;border-radius:2px;padding:1px 4px;font-size:12px}
#ag-body blockquote{margin:0 0 10px;padding:8px 12px;border-left:2px solid #00c8a0;
  background:#071320;color:#9fb6c4}
#ag-body blockquote p:last-child{margin:0}
/* A rules table is the one thing here that can be wider than the panel. */
#ag-body table{width:100%;border-collapse:collapse;margin:0 0 12px;font-size:12.5px;display:block;
  overflow-x:auto}
#ag-body th,#ag-body td{border:1px solid #1b3346;padding:5px 8px;text-align:left;vertical-align:top}
#ag-body th{color:#e8eaed;background:#071320;white-space:nowrap}
#ag-foot{flex:0 0 auto;padding:11px 16px;border-top:1px solid #1b3346;font-size:11px;color:#7a9eb0}
#ag-foot a{color:#00c8a0}
</style>

  <!-- One masthead row: the blurb takes what it needs and the counts sit hard
       right, the same shape DHC Fighters uses. Two sibling pages in one feature
       should not introduce themselves differently. -->
  <div class="a-masthead">
    <div class="a-intro-txt">
      <div class="a-head"><h1><?php echo $IS_BOSS ? 'DHC Bosses' : 'DHC Arena'; ?></h1></div>
      <?php if ($IS_BOSS): ?>
      <p class="a-sub">Three bosses with one health bar each, shared by every player and
         standing all week. Send a Crew of <?php echo DHCA_CREW_SIZE; ?> and chip the bar
         down — <b>every point of damage counts</b>, and the traits everyone who took part
         rolls for get better the further the crew falls. Same board, same rules, and a
         recovery clock of its own: your Arena Crew is untouched.</p>
      <?php elseif ($dhca_guest): ?>
      <p class="a-sub">Three Fighters against three, on a shared board. Each of your
         Crew owns a gem — matching it makes them act, and <b>match size is reach</b>:
         a match of 3 only touches their front rank, 5 or more reaches the back.
         <b>You are playing a practice battle with two random Crews.</b> Nothing is at
         stake and there is no limit.</p>
      <?php else: ?>
      <p class="a-sub">Three of your Fighters against three of theirs, on a shared board.
         Each of your Crew owns a gem — matching it makes them act, and <b>match size is
         reach</b>. Win and a trait lands in your collection.</p>
      <?php endif; ?>
    </div>
    <?php /* Four tiles either way. The count is not decoration -- the phone
             breakpoint lays .a-stats out as repeat(4,...), so a guest variant
             with a different number of tiles would collapse it. */ ?>
    <div class="a-stats">
      <?php if ($IS_BOSS && $dhca_guest): ?>
      <?php /* A guest has no Crew, so a 0-of-3 allowance tile would be a count
               of nothing dressed up as a status. Four tiles either way, since
               the phone breakpoint lays this out as repeat(4,...). */ ?>
      <div class="a-stat"><b><?php echo count($dhcb_on); ?></b><span>Bosses standing</span></div>
      <div class="a-stat"><b><?php echo DHCA_CREW_SIZE; ?></b><span>Per Crew</span></div>
      <div class="a-stat"><b><?php echo DHCB_DAILY_RUNS; ?></b><span>Crews a round</span></div>
      <div class="a-stat"><b><?php echo DHCB_CACHE_MAX; ?></b><span>Traits a week</span></div>
      <?php elseif ($IS_BOSS): ?>
      <div class="a-stat"><b><?php echo count($crew); ?></b><span>Fighters</span></div>
      <div class="a-stat"><b><?php echo count($available); ?></b><span>Rested</span></div>
      <div class="a-stat"><b><?php echo max(0, DHCB_DAILY_RUNS - $dhcb_runs); ?>/<?php
        echo DHCB_DAILY_RUNS; ?></b><span>Crews this round</span></div>
      <div class="a-stat"><b><?php echo (int)$dhcb_days; ?>/<?php
        echo DHCB_CACHE_MAX; ?></b><span>Traits banked</span></div>
      <?php elseif ($dhca_guest): ?>
      <div class="a-stat"><b><?php echo DHCA_CREW_SIZE; ?></b><span>Per Crew</span></div>
      <div class="a-stat"><b><?php echo count(dhca_kits()); ?></b><span>Fighting styles</span></div>
      <div class="a-stat"><b>∞</b><span>Practice</span></div>
      <div class="a-stat"><b><?php echo DHCA_DAILY_BATTLES; ?></b><span>Daily, signed in</span></div>
      <?php else: ?>
      <div class="a-stat"><b><?php echo count($crew); ?></b><span>Fighters</span></div>
      <div class="a-stat"><b><?php echo count($available); ?></b><span>Standing</span></div>
      <div class="a-stat"><b><?php echo max(0, DHCA_DAILY_BATTLES - $spent); ?>/<?php echo DHCA_DAILY_BATTLES; ?></b><span>Battles left</span></div>
      <div class="a-stat"><b><?php echo count($foesList); ?></b><span>Rivals</span></div>
      <?php endif; ?>
    </div>
  </div>

<?php if ($IS_BOSS): ?>
  <?php /* THE BOSSES SAY IT IN THEIR OWN PANEL, where the button they are
           talking about is. dhca_entry_block()'s banner is about the ranked
           allowance and an open Arena battle, neither of which stops a Crew
           going at a boss. */ ?>
  <?php if ($dhca_guest): ?>
  <div class="a-block">Three bosses, standing until Thursday 4pm CT, and everybody's damage
    lands on the same health bar. <a href="index.php">Sign in</a> to build Fighters from
    your own DHC traits and send a Crew at them.</div>
  <?php elseif (count($crew) < DHCA_CREW_SIZE): ?>
  <div class="a-block">A Crew is <?php echo DHCA_CREW_SIZE; ?> Fighters and you have
    <?php echo count($crew); ?>. Build them in <a href="dhcfighters.php">DHC Fighters</a>.</div>
  <?php endif; ?>
<?php elseif ($dhca_guest): ?>
  <?php /* A guest is not blocked from anything they came here for, so this is an
           invitation rather than dhca_entry_block()'s refusal. No "build a Crew"
           link: dhcfighters.php is behind the gate this page just stepped around,
           and sending a curious visitor into error.php is the whole problem. */ ?>
  <div class="a-block">Practice is the whole game — the same rules, the same engine,
    nothing simplified. <a href="index.php">Sign in</a> to build your own Fighters,
    field a Crew, and fight other players for traits and a place on the ladder.</div>
<?php elseif ($block): ?>
  <div class="a-block"><?php echo htmlspecialchars($block); ?>
    <?php if (count($crew) < DHCA_CREW_SIZE): ?>
      Build them in <a href="dhcfighters.php">DHC Fighters</a>.
    <?php endif; ?>
    <?php
      /*
       * RESUME OR FORFEIT, AND THE PLAYER DECIDES.
       *
       * A battle no longer times out. The board is kept exactly as it was
       * left, so there is nothing to gain by stalling -- which means there is
       * no reason to take the choice away. Previously the Arena resolved it
       * as a defeat after six hours and the player found out whenever they
       * next came back, with no say in it.
       */
      $openNow = $user_id ? dhca_open_battle($conn, $user_id) : null;
      if ($openNow):
    ?>
      <div class="a-block-acts">
        <button class="btn go" id="aResumeBlocked" type="button">Resume it</button>
        <button class="btn" id="aForfeit" type="button">Forfeit it</button>
        <span class="a-block-note">Resuming puts you back on the same board, exactly as you
          left it. Forfeiting records a defeat.</span>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

  <div class="a-panels" id="arenaSetup">
    <div class="a-panel">
      <?php if ($dhca_guest): ?>
      <?php /* A guest has no Crew to pick, and an empty picker is a dead panel
               on the one screen a newcomer sees. They are about to be dropped
               straight into a battle, so this is the panel that tells them how
               to play it -- the same rules the battle uses, not a pitch. */ ?>
      <h2>How a battle works</h2>
      <div class="body">
        <p class="a-sub" style="margin:0 0 9px">Each Fighter in a Crew owns one gem
           colour. <b>Slide a row or column</b> to line up three or more of a colour and
           that Fighter attacks — so you are not matching gems, you are choosing who
           swings.</p>
        <p class="a-sub" style="margin:0 0 9px"><b>Match size is reach.</b> A match of 3
           only touches their front rank, 4 reaches mid, and 5 or more reaches the back
           <em>and</em> hands you another turn. The Fighter you most want protected
           stands at the back.</p>
        <p class="a-sub" style="margin:0 0 9px"><b>Shield</b> gems armour the whole Crew.
           <b>Charge</b> builds for everyone and erupts at 10, hitting all three of them
           at once.</p>
        <p class="a-sub" style="margin:0 0 9px"><b>Bombs.</b> A match of 4 arms a grenade,
           5 or more a detonator. It sits on the board wearing a colour and
           <b>either side can set it off</b>, so leaving one lying around is a real risk.
           Chain several and they hit far harder than one.</p>
        <p class="a-sub" style="margin:0">A Fighter's traits are its build: its torso sets
           health, its weapon sets how it fights and how hard, its headgear sets how often
           it lands a big one. Tap any Fighter mid-battle to see exactly which trait is
           doing what.</p>
      <?php else: ?>
      <h2>Your Crew — pick <?php echo DHCA_CREW_SIZE; ?>, in order</h2>
      <div class="body">
      <p class="a-sub" style="margin:0 0 9px">The order you pick sets the formation:
         first is <b>front</b>, then <b>mid</b>, then <b>back</b>. A match of 3 only
         reaches their front rank, 4 reaches mid, 5 or more reaches the back — so put
         the Fighter you most want protected last.</p>
      <?php if (!$crew): ?>
        <p class="a-sub" style="margin:0">No Fighters yet.</p>
      <?php else: ?>
      <?php
        /* Counted from the roster rather than guessed, so a filter that would
           show nothing is not offered at all. Up here because both the
           sentence and the chips below are built from it. */
        $pfCounts = array();
        foreach ($crew as $f) {
          $k = isset($f['profile']) ? $f['profile']['key'] : 'unproven';
          $pfCounts[$k] = (isset($pfCounts[$k]) ? $pfCounts[$k] : 0) + 1;
        }
        $pfLabels = array('reliable' => 'Reliable', 'even' => 'Even',
                          'risky' => 'Risky', 'winless' => 'Winless', 'unproven' => 'Unproven');
        /*
         * A CLAUSE PER LABEL, and only for labels that are on screen. This
         * used to define "Reliable" whatever the roster looked like -- and
         * nobody has five battles yet, so the chips offered are Winless and
         * Unproven while the explanation led with a word that was nowhere on
         * the page. It grows into the rest as the ledger fills up.
         */
        $pfWhat = array(
          'reliable' => '<b>Reliable</b> survives most of the battles its Crew wins',
          'even'     => '<b>Even</b> survives about half of them',
          'risky'    => '<b>Risky</b> falls in more than half',
          'winless'  => '<b>Winless</b> has not won one yet',
          'unproven' => '<b>Unproven</b> has fought fewer than ' . DHCA_PROFILE_MIN
                      . ', too few to tell',
        );
        $pfNote = array();
        foreach ($pfWhat as $k => $txt) if (!empty($pfCounts[$k])) $pfNote[] = $txt;
        $pfShow = (count($pfCounts) > 1);
      ?>
      <?php /* Sorting and filtering happen in the browser, not through a
               reload: a reload would throw away the Fighters already picked,
               and picking is the whole reason this list exists.

               ONE ROW, ONE EXPLANATION. Record arrived as a second row with
               a note of its own, which made the panel read as two unrelated
               tools stacked up. They are three ways of narrowing one list
               and belong together, under a single sentence covering all of
               them. */ ?>
      <p class="a-sub" style="margin:0 0 7px">Sort the list, narrow it to one fighting
         style, or narrow it by what a Fighter has actually done.<?php
         if ($pfShow): ?> <?php echo implode('. ', $pfNote); ?>.
         A Fighter at the front is hit far more often than one at the back, so read
         the position in its tooltip alongside this.<?php endif; ?></p>
      <div class="a-tools" id="aPfFilter">
        <span class="lbl">Sort</span>
        <button type="button" class="on" data-sort="might">Deadliest</button>
        <button type="button" data-sort="hp">Toughest</button>
        <button type="button" data-sort="pow">Hardest hitting</button>
        <button type="button" data-sort="score">Rarest</button>
        <?php if (count($allKits) > 1): ?>
          <span class="sep"></span>
          <span class="lbl">Fights like</span>
          <button type="button" class="on" data-kit="">Any</button>
          <?php foreach ($allKits as $k): ?>
            <button type="button" data-kit="<?php echo htmlspecialchars($k['id']); ?>"
              title="<?php echo htmlspecialchars($k['note']); ?>"><?php
              echo '<i class="ico" style="--ico:url(icons/'.htmlspecialchars($k['icon']).'.png)"></i> '
                 . htmlspecialchars($k['name']).' '.$kitCount[$k['id']]; ?></button>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($pfShow): ?>
          <span class="sep"></span>
          <span class="lbl">Record</span>
          <button type="button" class="on" data-pf="">All <b><?php echo count($crew); ?></b></button>
          <?php foreach ($pfLabels as $k => $lbl): if (empty($pfCounts[$k])) continue; ?>
            <button type="button" data-pf="<?php echo $k; ?>"><?php echo $lbl; ?>
              <b><?php echo (int)$pfCounts[$k]; ?></b></button>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="a-picked" id="aPicked"></div>
      <div class="a-pick">
      <?php foreach ($crew as $f):
        $t = $f['traits']; $ok = $f['available']; ?>
        <?php /* ONE CLOCK, AND IT IS THIS PAGE'S CLOCK. $crew came from
                 dhcb_roster() on the Bosses page and dhca_crew() on the
                 Arena, so `available` already means "ready for the game you
                 are looking at". The two-pip card this replaces existed only
                 because one picker was serving both games. */ ?>
        <div class="a-card<?php echo $ok?'':' out'; ?>" data-fid="<?php echo (int)$f['id']; ?>"
             data-ok="<?php echo $ok?1:0; ?>"
             data-hp="<?php echo (int)$f['hp']; ?>"
             data-pow="<?php echo (int)$f['pow']; ?>" data-kit="<?php echo htmlspecialchars($f['kit']['id']); ?>"
             data-score="<?php echo (int)$f['rarity_score']; ?>"
             data-pf="<?php echo htmlspecialchars(isset($f['profile']) ? $f['profile']['key'] : 'unproven'); ?>"
             data-dpb="<?php echo (int)(isset($f['profile']) ? $f['profile']['dpb'] : 0); ?>"
             title="<?php echo htmlspecialchars($f['display'].' — '.$f['kit']['name'].', '.$f['kit']['note']
                     .' · '.$f['hp'].' health, '.$f['pow'].' power, '.$f['crit'].'% crit'); ?>">
          <div class="art">
            <?php if (!empty($t['background'])): ?>
              <img class="bg" loading="lazy" alt="" src="<?php echo $ART; ?>/250/background/<?php echo htmlspecialchars($t['background']); ?>.png" onerror="this.remove()">
            <?php endif; ?>
            <?php /* dhcf_layer_order(), not a hand-written list -- the same call the
                     assembler, the gallery and the Discord render all use. The list
                     that was here named 'effects', and a SAVED Fighter has no such
                     slot: it stores effects1 and effects2, so every effect a player
                     had equipped was silently missing from their own Crew cards. It
                     also had no weaponBack, and it ignored the exceptions
                     dhcf_layer_order() applies -- a companion that belongs under the
                     arms, arms that go behind the torso, an effect that sits behind
                     it. The SLOT names the layer, the CATEGORY names the art folder,
                     and for effects1/weaponBack those differ. */ ?>
            <?php foreach (dhcf_layer_order($t) as $k):
              if ($k === 'background' || empty($t[$k])) continue;
              $kc = dhcf_slot_category($k); ?>
              <img loading="lazy" alt="" src="<?php echo $ART; ?>/250/<?php echo $kc; ?>/<?php echo htmlspecialchars($t[$k]); ?>.png" onerror="this.remove()">
            <?php endforeach; ?>
            <?php if (!$ok): ?>
              <div class="recover<?php echo !empty($f['fell']) ? ' fell' : ''; ?>"><?php
                echo !empty($f['fell']) ? 'Resurrecting' : 'Recovering'; ?>
                <b><?php echo dhca_hms($f['bench_left']); ?></b></div>
            <?php endif; ?>
          </div>
          <div class="nm"><?php echo htmlspecialchars($f['display']); ?></div>
          <div class="st"><span>♥ <?php echo (int)$f['hp']; ?></span><span>⚔ <?php echo (int)$f['pow']; ?></span>
            <em class="ico" style="--ico:url(icons/<?php echo htmlspecialchars($f['kit']['icon']); ?>.png)"></em></div>
          <?php
            /*
             * THE LABEL IS THE POINT, the numbers are the receipt.
             *
             * Picking a Crew is one question -- do I field this -- and a
             * record of 21W/10L does not answer it, partly because it is
             * survival rather than victory and partly because reading two
             * numbers per card across a dozen cards is not a glance.
             *
             * The label answers it. The title carries the fact behind it so
             * the label is checkable rather than something to be trusted,
             * and the position is in there because a match of 3 reaches the
             * FRONT rank -- 60% survival at the front is a better Fighter
             * than 60% at the back, and hiding that would be inventing a
             * number nobody can verify.
             */
            $pf = isset($f['profile']) ? $f['profile'] : null;
          ?>
          <div class="wl"><?php echo number_format((int)$f['rarity_score']); ?> pts
            <?php if ($pf): ?>
              &middot; <span class="pf pf-<?php echo $pf['key']; ?>"
                title="<?php echo htmlspecialchars($pf['fact']
                        . ($pf['dpb'] ? ' · ' . number_format($pf['dpb']) . ' damage a battle' : '')); ?>"><?php
                echo htmlspecialchars($pf['label']); ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
      <div class="a-pager" id="aPager" style="display:none">
        <button type="button" id="aPrev">&lsaquo; Prev</button>
        <span id="aPageLbl"></span>
        <button type="button" id="aNext">Next &rsaquo;</button>
      </div>
      <?php endif; /* $crew */ ?>
      <?php endif; /* $dhca_guest */ ?>
      </div>
    </div>

    <?php /* ARENA ONLY: there is nobody to choose on the Bosses page, and the
             two buttons at the bottom of it are the ranked entry and the live
             challenge, neither of which exists over there. */ ?>
    <?php if (!$IS_BOSS): ?>
    <div class="a-panel">
      <h2><?php echo $dhca_guest ? 'Play' : 'Choose a rival'; ?></h2>
      <div class="body">
      <?php if ($dhca_guest): ?>
        <?php /* $foesList is empty for a guest because there is no user_id to
                 find rivals for -- NOT because nobody has a Crew. Printing the
                 "nobody else has a Crew yet" line here would be a flat lie about
                 how busy the Arena is, on the one page built to promote it. */ ?>
        <p class="a-sub" style="margin:0 0 9px">Two random Crews, the real rules, nothing
           at stake. Play as many as you like — no account, no limit, nothing to lose.</p>
      <?php elseif (!$foesList): ?>
        <p class="a-sub" style="margin:0">Nobody else has a Crew yet. Check back once more stakers have built three Fighters.</p>
      <?php else: ?>
      <div class="a-foes">
      <?php foreach ($foesList as $o):
        /* avatar_url() picks the extension from the hash -- an animated avatar
           is served as .gif only, and .jpg 404s on some. '' when there is none,
           which this template already handles. */
        $av = ($o['discord_id'] && $o['avatar'])
            ? avatar_url($o['discord_id'], $o['avatar']) : ''; ?>
        <?php /* PAID means you already took a trait off this rival today. Still
                 selectable -- the ladder and the win record both still count,
                 and a player may well want the rematch -- but say so before
                 the battle rather than after it. */
              $paid = isset($paidToday[(int)$o['user_id']]); ?>
        <?php /* A flagged row carries its own history; no other row has
                 these keys at all. */
              $nem = isset($o['battles']);
              $tags = isset($standTags[(int)$o['user_id']])
                      ? $standTags[(int)$o['user_id']]['tags'] : array();
              /* THE CARD TAKES THE RECORD LABEL'S COLOUR, not the
                 nemesis's, when it wears both. Tormentor and Sitting duck
                 are the ones that should change what you do next; the
                 nemesis is a volume fact. Blending the two colours was the
                 other option and a blend means neither of the things it is
                 made of -- nobody can read maroon as "purple and red". */
              $lead = in_array('tormentor', $tags, true) ? 'tormentor'
                    : (in_array('duck', $tags, true) ? 'duck'
                    : (in_array('nemesis', $tags, true) ? 'nemesis' : '')); ?>
        <div class="a-foe<?php echo $paid ? ' paid' : ''; ?><?php echo $nem ? ' nem' : ''; ?><?php
             echo $lead !== '' ? ' lead-' . $lead : ''; ?>" data-uid="<?php echo (int)$o['user_id']; ?>"
             data-best="<?php echo (int)$o['best']; ?>">
          <?php if ($tags): ?>
            <span class="nemtags">
<?php         $tagText = array('nemesis' => 'Arch nemesis', 'tormentor' => 'Tormentor',
                               'duck'    => 'Sitting duck');
              /* THE RECORD ONCE, on the last chip. The word alone does not
                 make anybody pause -- 11-12 does -- but both chips on a
                 card describe the SAME opponent, so printing it on each
                 was the same two numbers twice. */
              $lastTag = $tags[count($tags) - 1];
              foreach ($tags as $t): ?>
              <span class="nemtag t-<?php echo $t; ?>"><?php echo $tagText[$t];
                echo $t === $lastTag
                     ? ' ' . (int)$o['my_wins'] . '-' . (int)$o['their_wins'] : ''; ?></span>
<?php         endforeach; ?>
            </span>
          <?php endif; ?>
          <?php /* NO INLINE onerror. It used to hide the image, which takes the
                   refresh's turn away: header.php's listener asks Discord for the
                   current hash and swaps it in, and an element that has already
                   been hidden is a repair nobody sees. A dead hash now falls to
                   the skull like everywhere else. */ ?>
          <img src="<?php echo htmlspecialchars($av); ?>" alt="">
          <span class="n"><?php echo htmlspecialchars($o['username']); ?></span>
          <span class="m"><?php echo (int)$o['fighters']; ?> Fighters · best <?php echo number_format((int)$o['best']); ?></span>
          <?php /* Filled by paintOdds() once a Crew is picked: the gap depends
                   on which Fighters you bring, so it cannot be rendered here.

                   BEFORE THE RECORD, AND NOT INSIDE ITS GUARD. .nemrec is
                   flex:1 0 100%, so it breaks the line and takes whatever
                   follows it down with it -- which put these percentages on
                   a third line, at the left, while every row without a
                   rivalry kept them at the right of the first. Every row has
                   this span: it is the one column the whole list is compared
                   down. */ ?>
          <span class="odds"></span>
          <?php if ($nem): ?>
          <?php /* The record, which is the claim. Then how many of those
                   they started -- the half a defender is never told about,
                   since only the attacker is benched and only the attacker
                   reaches the ladder.

                   LABELLED, NOT A SCORELINE. This read "21-10 over 31
                   battles", and a bare pair does not say whose number is
                   whose -- reported as exactly that. The total went with it:
                   outcome is only ever 1 or 2, so there are no draws and
                   "over 31" was 21+10 restated.

                   Each stat is one <em>, and each <em> is nowrap. The line
                   itself wraps, but only BETWEEN stats -- otherwise a long
                   row breaks after "They started" and leaves the number
                   stranded on its own line, which is what it did on a
                   phone. */ ?>
          <span class="nemrec"><em>You won <b><?php echo (int)$o['my_wins']; ?></b></em><i>&middot;</i><em>They won <b><?php echo (int)$o['their_wins']; ?></b></em><?php /* A PERCENTAGE, NOT A COUNT. "They started 20" against a 21-11
                   record means adding the two up and comparing, in your head,
                   every time you look at it. The question this stat exists to
                   answer is "who comes after whom", and a share answers it
                   without arithmetic. Exact numbers stay on the title.
                   $battles is the row count from dhca_nemesis(), not
                   my_wins+their_wins -- same figure, but one of them is the
                   authority and the other is a coincidence of there being no
                   draws. Shown even at 0%, which is itself the answer: you
                   have started every one of them. */
                $pct = (int)round((int)$o['they_attacked'] / max(1, (int)$o['battles']) * 100); ?><i>&middot;</i><em title="<?php echo (int)$o['they_attacked']; ?> of <?php echo (int)$o['battles']; ?> battles">They started <b><?php echo $pct; ?>%</b></em></span>
          <?php endif; ?>
          <?php if ($paid): ?><span class="paidtag" title="One trait per opponent per day. A rematch still counts for the ladder.">Beaten today &middot; no trait available</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="a-go">
        <?php /* Kept in the DOM for a guest rather than dropped: startFailed()
                 reaches for it without a guard, and a hidden button is cheaper
                 than making every caller defensive for a case that cannot fire. */ ?>
        <button class="btn go" id="aStart" <?php echo $block?'disabled':''; ?>
          <?php echo $dhca_guest ? 'hidden' : ''; ?>>Enter the Arena</button>
        <?php /* Never disabled, whatever the allowance says -- that is the point
                 of it. A player out of battles, short of a Crew, or waiting on
                 three Fighters to come back can still play. */ ?>
        <button class="btn<?php echo $dhca_guest ? ' go' : ''; ?>" id="aPractice"><?php
          echo $dhca_guest ? 'New battle' : 'Practice'; ?></button>
        <?php if (!$dhca_guest): ?>
        <?php /* LIVE. Needs the same Crew and rival the ranked button needs, so
                 it sits beside it rather than in a mode of its own. Not shown to
                 a guest: a live match is between two named players and is
                 announced with their names on it. */ ?>
        <button class="btn" id="aChallenge" disabled>Challenge live</button>
        <?php endif; ?>
        <span class="a-sub" style="margin:0" id="aMsg"></span>
      </div>
      <?php if (!$dhca_guest): ?>
      <div class="a-live" id="aLive" hidden></div>
      <?php endif; ?>
      <?php if ($dhca_guest): ?>
      <?php /* No crypto or chain wording on the public face of the site -- see the
               funnel note in skullpaper. "Players", not "stakers", and DHC is named
               as a collection rather than explained. */ ?>
      <p class="a-sub" style="margin:8px 0 0">Signed in, the same Arena plays for keeps:
         build Fighters from your own DHC traits, field a Crew of <?php echo DHCA_CREW_SIZE; ?>,
         and fight other players <?php echo DHCA_DAILY_BATTLES; ?> times a day for traits
         and a place on the ladder. <a href="index.php">Sign in</a>.</p>
      <?php else: ?>
      <p class="a-sub" style="margin:8px 0 0">Practice pits two random Crews against
         each other with the real rules and nothing at stake — no allowance, no
         recovery, no traits, no ladder. Play as many as you like.</p>
      <?php endif; ?>
      </div>
    </div>
    <?php endif; /* !$IS_BOSS */ ?>

    <?php /* NO BOSS PANEL HERE. The Arena carried a card pointing at the
             other game, which is a whole panel in the column where the Crew
             is picked, spent on one link. It is a tab in the strip at the top
             of the page now, beside Arena, which is where a player looks for
             another place to go. */ ?>
    <?php if ($IS_BOSS && $dhcb_on): $dp = $dhcb_prog; ?>
    <div class="a-panel" id="aBossPanel">
      <h2>Bosses Crew</h2>
      <div class="body">
        <p class="a-sub" style="margin:0 0 10px">Three bosses, one health bar each, shared by
           everybody, standing all week. <b>Every point of damage counts</b> — the traits
           everyone who takes part rolls for when the week closes get better the further the
           crew falls, and dropping all three unlocks the best table.</p>
        <p class="a-sub" style="margin:0 0 10px"><b><?php echo DHCB_DAILY_RUNS; ?> Crews every
           24 hours</b>, and every round you send one banks another trait. Seven rounds,
           seven traits.
           <?php /* "A DAY" IS NOT TRUE AND IT READ AS A BUG. The round turns at 4pm CT with
                    the week, so somebody who sent three Crews at 2pm and came back at half
                    past four was told they had sent none "today" -- and reported the page
                    was letting them exceed the cap. Say when it turns instead. */ ?>
           <b>Rounds turn at 4pm CT</b>, same as the week.
           <?php /* NUMBERED, NOT NAMED. The week turns at 4pm Thursday like every
                    other weekly game here, so a "day" runs 4pm to 4pm and straddles
                    two dates -- calling one "Friday" would be wrong by eight hours
                    for everybody and wrong by more outside Central time. */ ?>
           The week turns <b>Thursday at 4pm CT</b>.</p>

        <?php
          /* WAVES, AND WHY THE PANEL LEADS WITH THEM once there has been one.
             The crew is scaled to fall mid-week, so dropping it is the
             expected outcome rather than the end of the week -- and the
             thing a player wants to know on arrival is which crew is
             standing and how many the community has already put down. */
          $wv = max(1, (int)$dp['wave']);
        ?>
        <?php if ($wv > 1 || $dp['downed'] > 0): ?>
        <div class="a-wave">
          <span>Wave</span> <b><?php echo $wv; ?></b>
          <span><?php echo (int)$dp['downed']; ?> down this week</span>
        </div>
        <p class="a-sub" style="margin:0 0 10px">You already dropped
           <?php echo $dp['downed'] === 1 ? 'a crew' : (int)$dp['downed'] . ' crews'; ?>
           this week, so <b>the best trait table is locked in</b> for everyone who takes
           part — nothing below can take it back. They get back up angrier each time:
           what is left is the ladder, and your cache.</p>
        <?php endif; ?>

        <div class="a-bosses" data-wave="<?php echo min(5, $wv); ?>">
          <?php foreach ($dhcb_on as $bz):
            $pct = $bz['max_health'] > 0 ? max(0, min(100, round(100 * $bz['health'] / $bz['max_health']))) : 0; ?>
          <div class="a-boss<?php echo $bz['dead'] ? ' dead' : ''; ?>">
            <img loading="lazy" alt="<?php echo htmlspecialchars($bz['name']); ?>"
                 src="<?php echo htmlspecialchars($bz['art']); ?>" onerror="this.remove()">
            <div class="a-bname"><?php echo htmlspecialchars($bz['name']); ?></div>
            <div class="a-bbar"><i style="width:<?php echo $pct; ?>%"></i></div>
            <div class="a-bhp"><?php echo $bz['dead']
                ? 'down'
                : number_format($bz['health']) . ' / ' . number_format($bz['max_health']); ?></div>
            <div class="a-bblurb"><?php echo htmlspecialchars($bz['blurb']); ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($dhcb_slots): $filled = 0;
          foreach ($dhcb_slots as $sl) if ($sl['state'] === 'filled') $filled++; ?>
        <?php /* THE CACHE, AS SEVEN BOXES RATHER THAN A NUMBER.
                 A count going up is information; Wednesday sitting visibly
                 empty is a reason to open the game on Wednesday. One slot
                 per day you turn up with a real run, and with three Crews a
                 day there is no way to fill it in one sitting -- the only
                 route to seven is coming back seven times. */ ?>
        <div class="a-cache">
          <?php foreach ($dhcb_slots as $sl): ?>
          <div class="a-slot <?php echo $sl['state']; ?>"
               title="<?php echo 'Round ' . htmlspecialchars($sl['label']) . ' of 7 — ' . (
                 $sl['state'] === 'filled' ? 'banked'
                 : ($sl['state'] === 'today' ? 'now, still open'
                 : ($sl['state'] === 'missed' ? 'missed' : 'still to come'))); ?>">
            <i></i><span><?php echo htmlspecialchars($sl['label']); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php /*
               * THE ROUND, STATED, WITH A CLOCK ON IT.
               *
               * The allowance turns at 4pm CT with the week, not at
               * midnight, and an invisible boundary was reported as a
               * broken cap twice -- three Crews sent at 2pm, three more
               * owed at 4pm, and nothing on the page saying why. Renaming
               * "today" to "this round" was not enough on its own: a
               * boundary you cannot see is still a boundary you cannot
               * see. So it gets a number, a count and a countdown, next
               * to the button the question is asked at.
               */ ?>
        <div class="a-round">
          <span>Round <b><?php echo (int)$dhcb_round; ?></b> of <?php echo DHCB_CACHE_MAX; ?></span>
          <span><b><?php echo max(0, DHCB_DAILY_RUNS - (int)$dhcb_runs); ?></b> of
            <?php echo DHCB_DAILY_RUNS; ?> Crews left</span>
          <span class="a-rclk">Next round in <b id="aRoundLeft"
            data-left="<?php echo (int)$dhcb_left; ?>"><?php echo dhca_hms($dhcb_left); ?></b></span>
        </div>
        <div class="a-bsum a-cachenote">
          <b><?php echo (int)$filled; ?></b> of <?php echo count($dhcb_slots); ?> traits banked
          <?php if ($filled < count($dhcb_slots)): ?>
            · one more for every round you send a Crew
          <?php else: ?>
            · a full week
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="a-bsum">
          <b><?php echo round(100 * $dp['pct']); ?>%</b> of the crew is down ·
          <?php echo number_format($dp['dealt']); ?> of <?php echo number_format($dp['max']); ?> damage
          <?php if ($dp['dead'] > 0 && !$dp['cleared']): ?>
            · <b><?php echo (int)$dp['dead']; ?></b> of <?php echo (int)$dp['total']; ?> down
          <?php endif; ?>
        </div>

        <?php
          /* The table everybody is currently on, named in the words the end
             screen uses, so the panel and the payout cannot disagree. */
          $tlabels = array('run'=>'standard', 'placement_10'=>'improved',
                           'placement_3'=>'rare', 'placement_1'=>'best');
          $tnow = dhcb_tier_table($dp);
        ?>
        <div class="a-bsum">Rolling on the <b><?php echo $tlabels[$tnow]; ?></b> table<?php
          echo $dp['downed'] > 0 ? ', locked in.' : ' right now.'; ?></div>

        <?php if ($dhca_guest): ?>
          <p class="a-sub" style="margin:10px 0 0">Sign in to send a Crew.</p>
        <?php elseif ($dhcb_block !== ''): ?>
          <p class="a-sub" style="margin:10px 0 0"><?php echo htmlspecialchars($dhcb_block); ?></p>
        <?php else: ?>
          <?php /* .btn go, like every other button on this page. .button is
                   the GLOBAL flexbox.css style -- the old drop-shadowed one --
                   and it lands in the middle of a panel where nothing else
                   has a shadow. The Arena restyled its own controls and this
                   one was written against the platform default. */ ?>
          <?php /*
                 * INSIDE .a-go, WHICH IS WHERE THE BUTTON STYLE LIVES.
                 *
                 * .btn.go on its own is the page's loud variant -- solid
                 * fill, white text -- and it was never meant to be used
                 * bare: every button on this page sits in .a-go, which
                 * restyles .go as the quiet outlined control the Arena's
                 * "Enter the Arena" is, AND carries the :disabled rule.
                 * Outside it this button was a solid green slab that still
                 * looked live with no Crew picked, because the only thing
                 * saying "disabled" was an attribute nothing styled.
                 *
                 * DISABLED IN THE MARKUP. The gate below runs on the first
                 * paint, but between parse and paint the button is live, and
                 * a click in that window posts an empty Crew. Starting dead
                 * costs nothing -- there is no state in which three Fighters
                 * are already picked when the page loads.
                 *
                 * #aMsg rides with it for the same reason it does on the
                 * Arena: the picker writes "Pick one more" into it and the
                 * start paths write their failures into it.
                 */ ?>
          <div class="a-go">
            <button class="btn go" id="aBossGo" type="button" disabled>Send Crew at the Bosses</button>
            <span class="a-sub" style="margin:0" id="aMsg"></span>
          </div>
          <p class="a-sub" style="margin:8px 0 0">Fighters rest from Bosses only — your
            Arena Crew is untouched.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php /* ONE panel, two windows -- the same shape the Fighters page settled
             on. They are the same five columns of the same table, so two
             panels side by side would read as two different things and cost a
             whole column. This month leads because it is the live race; all
             time sits under it because it is the slower, longer answer. */ ?>
    <?php if (!$IS_BOSS): ?>
    <div class="a-panel">
      <h2>Ladder</h2>
      <div class="body">
        <div class="a-lsec">
          <h3>This month <span><?php echo htmlspecialchars(dhca_season()); ?></span></h3>
          <?php echo dhca_ladder_html($ladder, $user_id, $dhca_guest,
                    'No battles fought this season yet. Be first.'); ?>
        </div>
        <div class="a-lsec">
          <h3>All time</h3>
          <?php echo dhca_ladder_html($ladderAth, $user_id, $dhca_guest,
                    'No battles fought yet.'); ?>
        </div>
      </div>
    </div>
    <?php else: ?>
    <?php /* THE BOSSES' OWN LADDER, and only one window on it. There is no
             all-time board here on purpose: the ranking is damage per Fighter
             fielded within one week, and summing that across every week ever
             would rank the player who sent one perfect Crew in March above
             somebody who has turned up every day since. The week IS the
             season, and the CARBON is paid against it. */ ?>
    <div class="a-panel">
      <h2>Ladder</h2>
      <div class="body">
        <div class="a-lsec">
          <h3>This week <span>closes Thursday 4pm CT</span></h3>
          <?php echo dhcb_ladder_html($dhcb_lad, $user_id, $dhca_guest,
                    'Nobody has finished a Crew against them yet this week. Be first.'); ?>
        </div>
        <p class="a-sub" style="margin:0">Ranked on <b>damage per Fighter fielded</b>, so a
           deep Crew list is an advantage and not a win on its own. A minimum of
           <?php echo (int)DHCB_LADDER_MIN_RUNS; ?> finished Crews puts you on the board, and
           <b><?php echo number_format(DHCB_CARBON); ?> CARBON</b> is split down it when the
           week closes — first place takes the largest share, and each rank below takes
           the pool divided by its own position.</p>
      </div>
    </div>
    <?php endif; ?>

    <?php /* THE MAP MOVED TO ITS OWN PAGE. It lived here first and did not
             survive real data: a ten-player graph in a 450px column is
             something you scroll past, not something you read. A graph needs
             width and height at once, so it got a page and this got a link --
             which is also the honest size of the relationship, since the
             ladder is what people come to this page for. */ ?>
  </div>

<?php if ($IS_BOSS && $dhcb_feed): ?>
<?php
/*
 * WHO WENT IN, AND WHAT CAME BACK.
 *
 * A FULL-WIDTH ROW UNDER THE PANELS, not a fourth column. It is a wide
 * thing -- a face, three Fighters, three figures -- and squeezing it into
 * a 320px grid cell would make it a list of names, which is what the admin
 * report already is. This exists to be looked at.
 *
 * THE CREW IS DRAWN, NOT NAMED, and that is the whole point of it: this is
 * a shared bar, so the question a player has when they open the page is
 * what everyone else is throwing at it. Three portraits answer that
 * instantly and three names do not.
 *
 * GREYED MEANS FELL. Per Fighter, from dhc_boss_run_fighters.fell, so a run
 * where one survived reads differently from a wipe at a glance -- and a
 * wipe is the normal outcome here, which makes the survivor the
 * interesting thing rather than the loss.
 *
 * STACKED TRAIT LAYERS, THE WAY THE PICKER AND THE COLLECTION DO IT -- not
 * a server-side composite.
 *
 * The first version called dhcf_render_fighter() and drew nothing at all,
 * for two reasons that both had to be found before it would have worked:
 *
 *   THAT FUNCTION IS NOT LOADED HERE. It lives in dhcfighters-notify.php,
 *   which dhcarena-lib.php only includes INSIDE dhca_announce() -- a
 *   function that runs when a battle ends, not when a page renders. So it
 *   was undefined, the function_exists() guard returned '', and every
 *   Fighter was skipped in silence. A guard that turns a missing
 *   dependency into an empty row is worse than the crash it prevents.
 *
 *   AND 500 IS THE SHARED CACHE, not 250. The size is only in the filename
 *   when it is not 500, so asking for 250 writes a second cache that
 *   nothing else on the platform fills -- every Fighter here would have
 *   composed a thousand-pixel stack from scratch on first view. Twelve
 *   runs is thirty-six of those on one page load.
 *
 * Layers cost the server nothing, the trait PNGs are shared by every
 * surface on the site so they are already in the browser's cache, and
 * loading="lazy" means a feed below the fold fetches none of them until it
 * is scrolled to. Same call the Collection makes, so a Crew here cannot
 * disagree with the same Fighter anywhere else.
 */
?>
  <div class="a-feed">
    <h2>Crews sent this week</h2>
    <div class="body">
      <?php foreach ($dhcb_feed as $rn):
        $fell = 0; foreach ($rn['fighters'] as $f) if (!empty($f['fell'])) $fell++;
        $stood = count($rn['fighters']) - $fell;
        $av = ($rn['discord_id'] && $rn['avatar']) ? avatar_url($rn['discord_id'], $rn['avatar']) : '';
        $who = ($rn['username'] !== null && $rn['username'] !== '') ? $rn['username'] : 'Unknown';
      ?>
      <div class="a-run">
        <div class="a-runwho">
          <?php if ($av !== ''): ?>
            <img class="a-av" loading="lazy" alt="" src="<?php echo htmlspecialchars($av); ?>"
                 onerror="this.remove()">
          <?php endif; ?>
          <span><?php echo htmlspecialchars($who); ?></span>
        </div>
        <div class="a-runcrew">
          <?php foreach ($rn['fighters'] as $f):
            $t = json_decode((string)$f['traits'], true);
            if (!is_array($t) || !$t) continue; ?>
          <span class="a-rf<?php echo !empty($f['fell']) ? ' fell' : ''; ?>"
                title="<?php echo htmlspecialchars($f['display']
                  . (!empty($f['fell']) ? ' — fell' : ' — walked away')
                  . ' · ' . number_format((int)$f['dealt']) . ' damage'); ?>">
            <?php /* dhcf_layer_order(), the same call the picker and the
                     assembler make -- it handles the armless-torso swap and
                     the effects1/weaponBack slots, which a hand-written list
                     got wrong once already. The SLOT names the layer and the
                     CATEGORY names the folder, and for two of them they
                     differ. */ ?>
            <?php foreach (dhcf_layer_order($t) as $k):
              if (empty($t[$k])) continue;
              $kc = dhcf_slot_category($k); ?>
              <img loading="lazy" alt="" src="<?php echo $ART; ?>/250/<?php echo $kc; ?>/<?php
                echo htmlspecialchars($t[$k]); ?>.png" onerror="this.remove()">
            <?php endforeach; ?>
          </span>
          <?php endforeach; ?>
        </div>
        <div class="a-runnum"><b><?php echo number_format((int)$rn['dealt']); ?></b><span>damage</span></div>
        <div class="a-runnum"><b><?php echo (int)$rn['rounds']; ?></b><span>rounds</span></div>
        <?php /* The outcome a player cares about is not win or lose -- almost
                 every run is a wipe and still pays. What is worth saying is
                 whether anybody walked away from it. */ ?>
        <div class="a-runout<?php echo $stood > 0 ? ' ok' : ''; ?>"><?php
          echo $stood > 0
            ? $stood . ' walked away'
            : 'Crew wiped'; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

  <!-- the board, hidden until a battle starts -->
  <div id="arenaBattle">
    <div class="top">
      <button class="btn" id="aLeave">Leave</button>
      <?php /* Practice only -- a ranked opponent is chosen, not dealt. */ ?>
      <button class="btn" id="aReroll" hidden title="Deal two new Crews">New battle</button>
      <button class="btn" id="mute" title="Mute sound">🔊</button>
      <!-- Phone only. The title and the blurb cost most of a screen while you
           are playing and neither is needed then, so they hide together and
           come back together. Nothing is deleted. -->
      <button class="btn" id="howto" title="Show the title and how it works">?</button>
      <?php /* The rules, on the page they are the rules of. Not phone-only:
               the question "what does a bomb do" is asked at a desk too. */ ?>
      <button class="btn" id="aGuide" title="How the Arena works">Guide</button>
      <span class="turnflag" id="flag">—</span>
      <span class="lclock" id="lclock" hidden></span>
      <span class="sub" style="margin:0" id="round"></span>
    </div>
    <div class="arena">
      <div class="arenabg" id="terrain"></div>
      <div class="teamwrap mine"><div class="coltag you"><span>Your Crew</span>
        <span class="chg" id="chgMine"></span></div>
        <div class="teamcol mine" id="myTeam"></div></div>
      <div class="boardcol">
        <div class="boardwrap">
          <div class="combo" id="combo"></div>
          <div class="grid" id="grid"></div>
          <?php /* INSIDE the board, not beside it. The card is
                   position:absolute;inset:0, and as a sibling of .arena it had
                   no positioned ancestor at all -- so it anchored to the top of
                   the DOCUMENT and covered the first screenful of it. On a
                   desktop the battle happens to be about one screen tall so it
                   looked deliberate; on a phone the layout stacks, and the
                   first screenful is the enemy Crew and the board, which is
                   exactly what you want to look at when a battle ends.
                   .boardwrap is already position:relative for the combo banner,
                   so the card now covers the board and nothing else. */ ?>
          <div class="endcard" id="endcard" hidden>
            <div class="ec-title" id="ecTitle"></div>
            <div class="ec-sub" id="ecSub"></div>
            <div class="ec-stats" id="ecStats"></div>
            <?php /* The share is back. It was pulled because this page sat behind
                     skulliance.php, so the link in a post was a login prompt for
                     everyone who was not already a member and X could not scrape a
                     card off it -- the share cost reach rather than earning it.
                     Both halves of that are now fixed: the page is public and it
                     carries OpenGraph, so the link opens a playable game for anyone
                     who taps it. Same shape as the other five games' share buttons. */ ?>
            <div class="ec-acts">
              <a class="btn" id="ecShare" target="_blank" rel="noopener" href="#">𝕏 Share</a>
              <button class="btn go" id="ecAgain">Back to the Arena</button>
              <?php /* Practice only. A ranked battle's "Back to the Arena" already
                       leaves, but in practice that button starts the next battle --
                       which left the player with no way out of practice at all except
                       the Leave button up in the toolbar, above the card they are
                       looking at and easy to miss. */ ?>
              <button class="btn" id="ecLeave" hidden>Leave practice</button>
            </div>
          </div>
        </div><!-- /.boardwrap -->
        <div class="reach deskonly" id="reach"></div>
        <div class="legend deskonly" id="legend"></div>
      </div>
      <div class="teamwrap foes"><div class="coltag foe"><span>Enemy Crew</span>
        <span class="chg" id="chgFoes"></span></div>
        <div class="teamcol foes" id="foeTeam"></div></div>
    </div>
    <div class="reach mobonly" id="reachM"></div>
    <div class="legend mobonly" id="legendM"></div>
    <details class="logbox" open><summary>Battle log</summary><div id="log"></div></details>
    <div class="fcard" id="fcard" hidden>
      <div class="fc-box" id="fcBox"></div>
    </div>
  </div>

</div>

<?php
/*
 * THE GUIDE, RENDERED FROM THE SKULL PAPER.
 *
 * Not a second copy of the rules. An in-game guide and the documentation
 * say the same things, and the moment they are two pieces of text one of
 * them is wrong -- which here would be the one players read while
 * playing. doc_sections() takes the sections of
 * skullpaper/games-dhc-arena.md that are about PLAYING and leaves out the
 * ones about getting in, the ladder and the map.
 *
 * ORDERED FOR SOMEBODY LOOKING AT THE BOARD: what is in front of them
 * first, then whose turn it is, then the Fighters, then how to pick and
 * place a Crew, and the rewards last. The documentation is ordered for
 * somebody reading it start to finish, which is a different job.
 *
 * A renamed heading comes back in 'missing' and the harness fails on it,
 * rather than the guide quietly losing a section.
 */
require_once __DIR__ . '/lib/doc-sections.php';
$ag = doc_sections('games-dhc-arena', array(
	'The board',
	'Every piece does something',
	'Whose move it is',
	'Fighters',
	'Reading a Fighter before you field it',
	'Where to put them',
	'Your arch nemesis',
	'The trait drop',
));
if ($ag['html'] !== ''):
?>
<div id="ag-veil" role="dialog" aria-modal="true" aria-labelledby="ag-title" hidden>
  <div id="ag-box">
    <div id="ag-head">
      <h2 id="ag-title">How the Arena works</h2>
      <button class="btn" id="ag-close" type="button">Close</button>
    </div>
    <div id="ag-body"><?php echo $ag['html']; ?></div>
    <div id="ag-foot">
      This is the <a href="skullpaper.php?page=games-dhc-arena">Arena page of the Skull Paper</a>,
      which has the rest of it: getting in, the ladder, and the rivalry map.
    </div>
  </div>
</div>
<?php endif; ?>

<script>
/* ============================================================================
   DHC Arena — the client.

   It draws and it animates. It decides NOTHING. A move goes to
   ajax/dhcarena-action.php as "slide A to B"; the engine resolves the whole
   exchange, the defending Crew's replies included, and answers with the new
   state plus an fx TIMELINE — an ordered list of what happened. Everything
   below is a player for that timeline.

   The one rule duplicated here is "does this slide make a match", and only to
   REFUSE a move locally: a slide that matches nothing is free in this game, and
   making the player wait on a round trip to be told so would ruin the feel. The
   server checks it again and is the only opinion that counts.
   ============================================================================ */
(function(){
var ART = <?php echo json_encode($ART); ?>;
var N = 7, BOMB_CROSS = 1, BOMB_BOARD = 2;
var SHAPE = ['','sq','di','shield','hex'];
var SHARED = {3:{emoji:'🛡️',icon:'titanium-armor',name:'Shield',note:'shields crew'},
              4:{emoji:'⚡',icon:'special-attack', name:'Charge',note:'erupts at 10'}};
/* THE PLATFORM'S OWN ICONS, not emoji. Every one is white line art on alpha --
   the same set Monstrocity and Crypt Crawl draw from -- so the board reads as
   one thing instead of as eight vendors' emoji fonts. Emoji were also the one
   element here that looked different on every device; the cross bomb has been
   a hand-drawn CSS shape from the start for exactly that reason.
   Drawn as a BACKGROUND on the glyph span that already exists, never as an
   <img>: the board rewrites all 49 cells on every drop, and adding an element
   per cell would put 49 new nodes through that on every cascade. */
var ICON_BASE = 'icons/';
function iconUrl(name){ return ICON_BASE + name + '.png'; }
var CREW_SIZE = <?php echo DHCA_CREW_SIZE; ?>;
/* THE REWARD LADDER, AUTHORED BY THE SERVER. dhca_reward_bands() probes
   the same dhcf_table_for() that dhca_pay() calls, so these percentages
   are the ones that actually pay. The client does the subtraction and
   the lookup and nothing else -- it never decides what a band is worth,
   which is the line this page holds everywhere else. */
var ARENA_BANDS = <?php echo json_encode(dhca_reward_bands()); ?>;
/* Why the player cannot enter, if they cannot. The server decides this again on
   every start -- this only keeps the button honest. */
var BLOCKED = <?php echo json_encode($block); ?>;

/*
 * The reliability filter on the Crew picker.
 *
 * Hides cards rather than removing them, so a Fighter already chosen for the
 * Crew is never filtered out from under the player -- the selection is held
 * on the card itself, and a hidden-but-selected Fighter still fights. Picking
 * a Crew and then narrowing the list must not silently change the Crew.
 */
(function () {
  var bar = document.getElementById('aPfFilter');
  if (!bar) return;
  bar.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-pf]');
    if (!btn) return;
    var want = btn.getAttribute('data-pf');
    /* ONLY THE RECORD BUTTONS. They share a row with Sort and Fights like
       now, so an unqualified querySelectorAll would clear the active state
       off those two every time a Record chip was pressed -- the sort would
       still be applied and nothing would look selected. */
    bar.querySelectorAll('button[data-pf]').forEach(function (b) {
      b.classList.toggle('on', b === btn);
    });
    document.querySelectorAll('.a-card').forEach(function (c) {
      /* `.sel` is the chosen-for-the-Crew class, NOT `.on` -- `.on` is the
         filter button's own state and no card ever carries it, so the first
         version hid Fighters the player had already picked. */
      var keep = !want || c.getAttribute('data-pf') === want || c.classList.contains('sel');
      c.classList.toggle('pf-hid', !keep);
    });
  });
})();

/*
 * The resume-or-forfeit choice on the entry block.
 *
 * Both buttons act on the battle the server already knows about, so neither
 * needs a battle id. Forfeit confirms first: it is the only control on this
 * page that records a defeat without a move being played.
 */
(function () {
  var rb = document.getElementById('aResumeBlocked'),
      fb = document.getElementById('aForfeit');
  if (rb) rb.addEventListener('click', function () {
    rb.disabled = true;
    post({ do: 'resume' }, function (res) {
      if (!res || !res.ok) { rb.disabled = false; alert((res && res.message) || 'Could not resume.'); return; }
      openBattle(res, !res.state.moves);
    });
  });
  if (fb) fb.addEventListener('click', function () {
    if (!confirm('Forfeit this battle? It is recorded as a defeat, and every Fighter in the '
               + 'Crew is treated as having fallen. Resuming instead costs you nothing — the '
               + 'board is exactly as you left it.')) return;
    fb.disabled = true;
    post({ do: 'forfeit' }, function (res) {
      if (!res || !res.ok) { fb.disabled = false; alert((res && res.message) || 'Could not forfeit.'); return; }
      location.reload();
    });
  });
})();
/* Nobody is signed in. Everything ranked is unreachable for this visitor -- the
   Crew picker, the rival list, resume, the ladder position -- and practice is
   the whole page. */
var GUEST = <?php echo $dhca_guest ? 'true' : 'false'; ?>;

var S = null, battleId = 0, busy = false, drag = null, sfxOn = true;
/* PRACTICE. The same board, the same engine, nothing at stake -- and no row
   anywhere: the battle lives in this spec, which is posted back with each move
   and rebuilt server-side. See dhcarena-practice.php. */
var PRACTICE_URL = 'ajax/dhcarena-practice.php';
var practice = null;      // the spec while a practice battle is running, else null
/* LIVE. Human against human, no AI on either side unless somebody stalls.
   {id, seat, seq, yours} while a live match is running, else null. The server
   flips the board's perspective before sending it, so this client never learns
   which seat it is in -- 'mine' is always whoever is looking. See §8d. */
var LIVE_URL = 'ajax/dhcarena-live.php';
var live = null, livePoll = null, liveTick = null, liveClock = 0;
try { sfxOn = localStorage.getItem('dhcarena_sfx') !== '0'; } catch (e) {}

var $ = function(id){ return document.getElementById(id); };
var setup = $('arenaSetup'), battle = $('arenaBattle'), gridEl = $('grid');

/* ---------------------------------------------------------------- sound ----
   One voice per sound, restarted rather than layered — Skull Swap's model.
   Stacking a fresh voice per event cost 33 overlapping sounds on a single move
   and stalled tile animation on a phone. */
var SFX = {
  pick:['sounds/select.ogg',.40], bad:['sounds/badmove.ogg',.45],
  clear:['sounds/gem_shatters.ogg',.42], land:['sounds/hyperspace_gem_land_1.ogg',.22],
  chain:['sounds/speedmatch1.ogg',.55], great:['sounds/voice_excellent.ogg',.60],
  shield:['sounds/hyperspace_gem_land_2.ogg',.45], erupt:['sounds/badgeawarded.ogg',.60],
  ko:['sounds/skullcoinlose.ogg',.55], armX:['sounds/powergem_created.ogg',.70],
  armB:['sounds/hypercube_create.ogg',.80], boom:['sounds/bomb_explode.ogg',.75],
  start:['sounds/voice_go.ogg',.55], win:['sounds/voice_levelcomplete.ogg',.75],
  lose:['sounds/voice_gameover.ogg',.75]
};
var actx = null, sfxBuf = {}, sfxEl = {}, sfxCur = {};
function sfxInit(){
  if (actx) return;
  var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
  try { actx = new AC(); } catch (e) { return; }
  Object.keys(SFX).forEach(function(k){
    try {
      fetch(SFX[k][0]).then(function(r){ return r.arrayBuffer(); })
        .then(function(buf){ return new Promise(function(res, rej){ actx.decodeAudioData(buf, res, rej); }); })
        .then(function(b){ sfxBuf[k] = b; }).catch(function(){});
    } catch (e) {}
  });
}
function sfx(name){
  if (!sfxOn) return;
  var def = SFX[name]; if (!def) return;
  if (actx && sfxBuf[name]) {
    try {
      if (actx.state === 'suspended') actx.resume();
      var prev = sfxCur[name];
      if (prev) { try { prev.gain.gain.setTargetAtTime(0, actx.currentTime, .005);
                        prev.src.stop(actx.currentTime + .015); } catch (e) {} }
      var src = actx.createBufferSource(); src.buffer = sfxBuf[name];
      var g = actx.createGain(); g.gain.value = def[1];
      src.connect(g); g.connect(actx.destination);
      sfxCur[name] = {src:src, gain:g};
      src.onended = function(){ if (sfxCur[name] && sfxCur[name].src === src) sfxCur[name] = null; };
      src.start(0); return;
    } catch (e) {}
  }
  try {
    var a = sfxEl[name]; if (!a) { a = sfxEl[name] = new Audio(def[0]); a.volume = def[1]; }
    a.currentTime = 0; var pr = a.play(); if (pr && pr.catch) pr.catch(function(){});
  } catch (e) {}
}

/* ------------------------------------------------------------- geometry ----
   Read out of the engine so the preview matches what the server will do. */
function idx(r,c){ return r*N + c; }
function slid(b,a,z){
  if (a === z) return null;
  var ra = Math.floor(a/N), ca = a%N, rz = Math.floor(z/N), cz = z%N, x, y;
  if (ra !== rz && ca !== cz) return null;
  var n = b.slice(), t = b[a];
  if (ra === rz) {
    if (ca < cz) { for (x=ca; x<cz; x++) n[idx(ra,x)] = b[idx(ra,x+1)]; }
    else         { for (x=ca; x>cz; x--) n[idx(ra,x)] = b[idx(ra,x-1)]; }
    n[idx(ra,cz)] = t;
  } else {
    if (ra < rz) { for (y=ra; y<rz; y++) n[idx(y,ca)] = b[idx(y+1,ca)]; }
    else         { for (y=ra; y>rz; y--) n[idx(y,ca)] = b[idx(y-1,ca)]; }
    n[idx(rz,ca)] = t;
  }
  return n;
}
function findMatches(b){
  var raw = [], r, c, i;
  for (r=0; r<N; r++) { c=0; while (c<N) { var run=1;
    while (c+run<N && b[idx(r,c+run)] === b[idx(r,c)] && b[idx(r,c)] !== -1) run++;
    if (run>=3) { var g=[]; for (i=0;i<run;i++) g.push(idx(r,c+i)); raw.push({cells:g,type:b[idx(r,c)]}); }
    c += run; } }
  for (c=0; c<N; c++) { r=0; while (r<N) { var run2=1;
    while (r+run2<N && b[idx(r+run2,c)] === b[idx(r,c)] && b[idx(r,c)] !== -1) run2++;
    if (run2>=3) { var g2=[]; for (i=0;i<run2;i++) g2.push(idx(r+i,c)); raw.push({cells:g2,type:b[idx(r,c)]}); }
    r += run2; } }
  return raw;   // enough to answer "is this a move"; shape merging is the server's job
}

/* --------------------------------------------------------------- lookups ---
   fx carries an index into a side's array, which is also that Fighter's rank —
   the engine builds both teams front, mid, back. uid is what the DOM knows. */
function fighterAt(side, i){ return S && S[side] ? S[side][i] : null; }
function elFor(f){ return f ? document.querySelector('[data-id="'+f.uid+'"]') : null; }
function cellEl(i){ return gridEl.querySelector('[data-i="'+i+'"]'); }
function gemInfo(side, g){
  if (SHARED[g]) return SHARED[g];
  var list = S[side] || [];
  for (var i=0; i<list.length; i++) if (list[i].rank === g) return list[i].kit;
  return {emoji:'·', name:'—', note:''};
}
function artUrl(slot, name, size){ return ART+'/'+size+'/'+slot+'/'+name+'.png'; }
/* Boss art is a path from the staking root, not from the trait tree. */
var ART_ROOT = <?php echo json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/staking/x'), '/') . '/'); ?>;
function logLine(kind, text){
  var l = $('log'); if (!l) return;
  var d = document.createElement('div'); d.className = 'log-'+kind; d.textContent = text;
  l.appendChild(d); l.scrollTop = l.scrollHeight;
}
/**
 * The one banner over the board, and the reason it takes a priority.
 *
 * A single wave announces itself several times in the SAME TICK. The engine
 * emits, in order: the wave (a chain number), then a multi-match, then every
 * hit, then a detonation, then any bomb armed -- and each of those handlers
 * returns a zero delay, because they are one simultaneous event. With a plain
 * last-writer-wins banner the final call was the only one ever seen, and the
 * final call is `arm`.
 *
 * So the biggest moment in the game was the one most reliably hidden: a MEGA
 * MULTI-MATCH is nine or more gems across two or more matches, which nearly
 * always contains a run of four, which arms a bomb, whose banner replaced it
 * in the same frame. Players saw "BOMB ARMED" and concluded multi-matches were
 * not implemented.
 *
 * Ranking them fixes it without holding the action back: a louder event still
 * interrupts immediately, a quieter one within the hold window is dropped
 * rather than queued, and anything arriving after the window shows normally.
 * Queueing was the other option and it is worse -- the banners would trail the
 * board they are describing.
 */
var BANNER_HOLD = 620, bannerAt = 0, bannerPri = 0;
function banner(text, pri){
  var c = $('combo'); if (!c) return;
  pri = pri || 1;
  var now = Date.now();
  if (now - bannerAt < BANNER_HOLD && pri <= bannerPri) return;
  bannerAt = now; bannerPri = pri;
  c.textContent = text; c.classList.remove('on'); void c.offsetWidth; c.classList.add('on');
}

/* ------------------------------------------------------------ token art ----
   Built ONCE per battle. Rewriting innerHTML per move re-creates all fourteen
   <img> layers, which flashes the whole Crew on a phone and kills any animation
   mid-flight by replacing the element running it. */
/* SLOT vs CATEGORY. The slot names the layer -- and data-l, which the death
   animation groups on -- while the category names the art FOLDER. The two
   differ for the two effects slots and the back weapon, and treating a slot as
   a folder asks the server for /250/effects1/ which does not exist. */
var SLOT_CAT = {effects1:'effects', effects2:'effects', weaponBack:'weapon'};
/* Only for a state saved before the server began sending an order. Lists BOTH
   shapes -- a saved Fighter's effects1/effects2 and a practice Fighter's
   effects -- because naming only one is the bug this replaces. */
var LAYERS_FALLBACK = ['weaponBack','torso','weapon','arms','effects','effects1','effects2',
                       'head','headgear','companion'];
/**
 * One Fighter's art, in the order the assembler would draw it. The order comes
 * from the server (dhcf_layer_order), so the Arena cannot disagree with the
 * gallery, the assembler or the Discord render about what a Fighter looks
 * like -- and the per-trait exceptions (a companion under the arms, arms or an
 * effect behind the torso) come along for free.
 */
function layerImgs(f, tagged){
  /* A BOSS IS ONE PICTURE. DHC Bosses sends `art` instead of traits, because
     its opponents are painted collection pieces rather than assembled
     Fighters. Everything else about them -- stats, ranks, damage, the death
     animation's data-l hook -- is identical, so this is the only place the
     Arena has to know bosses exist. */
  if (f.art) {
    return '<img'+(tagged ? ' data-l="torso" data-s="boss"' : '')+' loading="lazy" alt="" src="'
         + ART_ROOT + f.art + '" onerror="this.remove()">';
  }
  var t = f.traits || {};
  var order = (f.layers && f.layers.length) ? f.layers : LAYERS_FALLBACK;
  return (t.background
      ? '<img class="bg" loading="lazy" alt="" src="'+artUrl('background',t.background,250)+'" onerror="this.remove()">' : '')
    + order.filter(function(k){ return k !== 'background' && t[k]; })
      .map(function(k){
        /* data-s carries the SLUG, not just the slot. killAnim() needs to
           know which trait this is, because one of them must not fall --
           see DEATH_ANCHORED. */
        return '<img'+(tagged ? ' data-l="'+k+'" data-s="'+t[k]+'"' : '')+' loading="lazy" alt="" src="'
             + artUrl(SLOT_CAT[k] || k, t[k], 250) + '" onerror="this.remove()">';
      }).join('');
}
function tokHtml(f, mine){
  var t = f.traits || {};
  var layers = layerImgs(f, true);
  var rank = ['front','mid','back'][f.rank];
  return '<div class="tok'+(mine?' mine':' foe')+(f.ko?' ko':'')+'" data-id="'+f.uid+'"'
    + ' style="--gem:var(--g'+f.rank+')">'
    + '<div class="flash"></div>'
    + '<div class="danger"></div>'
    + '<div class="rk"><span>'+rank+'</span>'
    +   '<span class="mygem" title="'+(mine?'your':'their')+' '+rank+' — '+f.kit.name+', '+f.kit.note+'">'
    +   (f.kit.icon ? '<i class="ico" style="--ico:url('+iconUrl(f.kit.icon)+')"></i>' : f.kit.emoji)
    +   '</span></div>'
    + '<div class="art">'+layers+'</div>'
    + '<div class="nm">'+f.name+'</div>'
    + '<div class="kitn" title="'+f.kit.name+' — '+f.kit.note+'">'+f.kit.note+'</div>'
    + '<div class="hpwrap"><div class="hp"></div><div class="sh"></div></div>'
    + '<div class="hpn"><span></span><span></span></div>'
    + '</div>';
}
function buildTeams(){
  var byRank = function(a,b){ return a.rank - b.rank; };
  $('foeTeam').innerHTML = S.foes.slice().sort(byRank).map(function(f){ return tokHtml(f,false); }).join('');
  $('myTeam').innerHTML  = S.mine.slice().sort(byRank).map(function(f){ return tokHtml(f,true);  }).join('');
}
/* Patch only what moves. Art, name, rank and gem never change mid-battle.
   Split per Fighter so the timeline can repaint ONE of them the instant its own
   hit plays, rather than every bar waiting for the end of the turn. */
function paintTok(f){
  var e = elFor(f); if (!e) return;
  if (f.ko) e.classList.add('ko'); else e.classList.remove('ko');
  var pct = f.hp / f.maxHp, bar = e.querySelector('.hp');
  if (bar) { bar.style.transform = 'scaleX('+pct+')';
             bar.className = 'hp'+(pct<=.25?' crit':pct<=.55?' low':''); }
  /* Same threshold the bar uses, so the pulse and the red never disagree --
     and never at zero, or the killing blow flashes "at risk" for a frame on the
     way down, now that the bar empties on the hit rather than at the end. */
  e.classList.toggle('crit', !f.ko && pct > 0 && pct <= .25);
  var sh = e.querySelector('.sh');
  if (sh) sh.style.width = Math.min(100, (f.shield/f.maxHp)*100)+'%';
  var n = e.querySelector('.hpn');
  if (n) n.innerHTML = '<span>'+f.hp+'/'+f.maxHp+'</span><span>'
    + (f.shield>0 ? '🛡 '+f.shield+' ' : '') + (f.bleed>0 ? '🗡' : '') + '</span>';
}
function paintCharge(sd){
  var live = S[sd].filter(function(f){ return !f.ko; });
  var v = live.length ? Math.max.apply(null, live.map(function(f){ return f.surge; })) : 0;
  var el = $(sd === 'mine' ? 'chgMine' : 'chgFoes'); if (!el) return;
  el.className = 'chg'+(v>=10?' full':'');
  el.innerHTML = '⚡ <i><b style="width:'+(v/10*100)+'%"></b></i>'+v+'/10';
  el.title = v>=10 ? 'Charged — the next Charge match erupts' : 'Charge: '+v+' of 10';
}
function paintTeams(){
  ['mine','foes'].forEach(function(sd){
    S[sd].forEach(paintTok);
    paintCharge(sd);
  });
}
/* Shared by the legend and the bomb key below it. */
function chip(colour, icon, emoji, does, dead){
  var face = icon ? '<i class="ico" style="--ico:url('+iconUrl(icon)+')"></i>' : emoji;
  return '<span class="lchip'+(dead?' gone':'')+'" style="--lc:'+colour+'"><b>'+face+'</b>'+does+'</span>';
}

/*
 * THE BOMB KEY -- what the cell tooltip used to be the only source of.
 *
 * Shown only while a bomb is actually on the board, which is the whole point
 * of putting it here instead of in a permanent help panel: it appears next to
 * the gem legend at the moment it means something, and takes no room the rest
 * of the battle. A bomb wears its underlying gem's colour, so the chips take
 * a fixed warning colour rather than pretending to match one.
 */
function paintBombKey(){
  var cross = false, board = false, i, b;
  if (S && S.bomb) {
    for (i = 0; i < S.bomb.length; i++) {
      b = Math.abs(S.bomb[i] || 0);
      if (b === BOMB_CROSS) cross = true;
      else if (b === BOMB_BOARD) board = true;
    }
  }
  var h = (cross ? chip('var(--ochre)', 'grenade',    '', 'bomb &middot; clears its row and column') : '')
        + (board ? chip('var(--blood)', 'demolition', '', 'board bomb &middot; clears everything')   : '');
  document.querySelectorAll('.bombkey').forEach(function (el) { el.innerHTML = h; });
}

function paintBoard(dropAnim, settle){
  gridEl.style.gridTemplateColumns = 'repeat('+N+',1fr)';
  var h = '', i;
  for (i=0; i<N*N; i++) {
    var v = S.board[i], bm = S.bomb ? Math.abs(S.bomb[i]||0) : 0, gi = gemInfo('mine', v);
    /* TWO BOMBS, TWO ICONS. A cross bomb clears its row and column; a board
       bomb clears everything, and telling them apart by ring colour alone is
       far too thin a signal for that difference in consequence. The grenade
       goes on the cross bomb because that is the one you see constantly, and
       the plunger detonator on the board bomb because it reads as levelling
       the place rather than throwing something. */
    var face = bm === BOMB_BOARD
             ? '<span class="ico" style="--ico:url(' + iconUrl('demolition') + ')"></span>'
             : bm === BOMB_CROSS
             ? '<span class="ico" style="--ico:url(' + iconUrl('grenade') + ')"></span>'
             : (gi.icon ? '<span class="ico" style="--ico:url(' + iconUrl(gi.icon) + ')"></span>' : gi.emoji);
    /*
     * NO TITLE ON A CELL.
     *
     * Every one of the forty-nine carried a native tooltip, so reading the
     * board -- which is the whole game, and is done by moving the cursor
     * across it -- raised a pop-up roughly once a second, each one landing
     * under the cursor on top of the tiles being compared. Reported as
     * distracting while making a move, which is the only time it happened.
     *
     * Nothing is lost. For an ordinary gem the tooltip repeated the legend
     * below the board, which is on screen for the whole battle and says the
     * same thing in a fixed place. For a bomb it said something the legend
     * did not, so the bomb key now joins the legend while a bomb is live.
     */
    h += '<div class="cell '+SHAPE[v]+(bm?' bomb'+(bm===BOMB_BOARD?' bomb2':''):'')
       + (dropAnim?' drop':'')+(settle?' settle':'')+'" data-i="'+i+'"'
       + ' style="--gc:var(--g'+v+')">'
       /* A BOMB'S GEM GETS AN UNCLIPPED WRAPPER, and only a bomb's.
          clip-path is applied AFTER filter on the same element, so a
          drop-shadow on .g is clipped away by .g's own hexagon or shield --
          which is why those two shapes never glowed however the glow was
          written. The shadow has to be cast by an ancestor that is not
          clipped, and it cannot be .cell, because .cell paints an opaque
          square and the shadow would trace that instead of the gem.
          Added per bomb rather than per cell: there are rarely more than a
          handful, and the other forty-odd keep the markup they had. */
       + (bm ? '<span class="bglow">' : '')
       + '<div class="g"><span class="em">'+face+'</span></div>'
       + (bm ? '</span>' : '')
       + '</div>';
  }
  gridEl.innerHTML = h;
  paintBombKey();
}
function paintChrome(){
  var tb = $('terrain');
  if (tb && S.terrainBg) tb.style.backgroundImage = 'url("'+artUrl('background', S.terrainBg, 1000)+'")';
  var lg = [0,1,2].map(function(i){
    var f = null;
    S.mine.forEach(function(x){ if (x.rank === i) f = x; });
    return f ? chip('var(--g'+i+')', f.kit.icon, f.kit.emoji, f.kit.note, f.ko) : '';
  }).join('')
  + chip('var(--g3)', SHARED[3].icon, SHARED[3].emoji, 'shields crew')
  + chip('var(--g4)', SHARED[4].icon, SHARED[4].emoji, 'erupts at 10')
  /* Filled by paintBombKey, not here: a bomb is armed and spent inside a
     cascade, and paintBoard runs on its own several times during one (see
     the settle and land calls) without paintChrome following it. Writing the
     key here would leave it a cascade behind. */
  + '<span class="bombkey"></span>';
  $('legend').innerHTML = lg; $('legendM').innerHTML = lg;
  paintBombKey();
  var reach = '<b>3</b> front · <b>4</b> mid · <b>5+</b> back · cascades multiply'
    /* The arena's name sits under the board all battle, and until now it was
       the only thing there that did not say what it meant -- you are told you
       are fighting in an Aracnyd Web and left to work out what a web does.
       Hover says so on a desktop; TAP says so on a phone, where hover does not
       exist and a title attribute is decoration. */
    + (S.terrainName
        ? '<span class="terr" id="terrTip" title="' + (S.terrainNote || '') + '">'
          + S.terrainName
          + (S.terrainNote ? '<i class="tnote"> — ' + S.terrainNote + '</i>' : '')
          + '</span>'
        : '');
  $('reach').innerHTML = reach; $('reachM').innerHTML = reach;
  /* Both copies of the reach line exist at once -- one for the side-column
     layout, one for the stacked one -- so wire whichever is on screen. */
  document.querySelectorAll('.reach .terr').forEach(function(el){
    el.addEventListener('click', function(){ el.classList.toggle('on'); });
  });
  var bw = document.querySelector('.boardwrap');
  if (bw) { if (!S.over && S.turn === 'foes') bw.classList.add('foeturn'); else bw.classList.remove('foeturn'); }
  var fl = $('flag');
  fl.className = 'turnflag ' + (S.over ? '' : (S.turn === 'mine' ? 'you' : 'foe'));
  fl.textContent = S.over ? (S.over === 'mine' ? 'victory' : 'defeat')
                          : (S.turn === 'mine' ? 'your move' : 'their move');
  $('round').textContent = S.over ? '' : 'Round '+S.round;
}

/* -------------------------------------------------------- token effects ----
   Straight out of the prototype: the same motion, driven by the server's
   timeline instead of by a local rules pass. */
function flashTok(f, kind){
  var e = elFor(f); if (!e) return;
  var fl = e.querySelector('.flash'); if (!fl) return;
  fl.className = 'flash'+(kind?' '+kind:''); void fl.offsetWidth; fl.classList.add('on');
}
/* Top of the stack downward — companion first, torso last — so a Fighter comes
   apart in the reverse of the order it was built. The background stays: it is
   the ground the pieces fall against, not part of the body. */
/* EFFECTS ARE NOT PART OF THE BODY. Flames, sparks and smoke are drawn AROUND
   a Fighter rather than worn by it, so tumbling them out of frame with the
   limbs read as broken -- a burst of fire does not fall over. They are cut
   instead, which is what happens to an aura when the thing making it dies. */
/* GROUPS, NOT SLOTS, for the same reason layerAnim() takes a list: headgear
   sits ON the head and the two are one object as far as motion goes. Given
   separate delays and opposite spins they came apart from each other -- the
   helmet cocked off the skull and hung across its face, which is not a
   collapse, it is two objects behaving independently.
   Top of the stack downward, so a Fighter comes apart in the reverse of the
   order it was built. */
/* Each group PINS its spin rather than taking whichever the running index
   happens to land on. Grouping the head with its headgear shifted every index
   below it by one, which quietly swapped the spin on half the body -- and gave
   the HEAD the headgear's tumble instead of the headgear taking the head's, so
   the skull went one way and left a gap at the neck.
   `alt` is the variant each slot had before any of this, with headgear changed
   to follow the head. */
var DEATH_ORDER = [
  {slots:['companion'],        alt:false, gone:true},
  {slots:['headgear','head'],  alt:false},   // headgear follows the HEAD, not the reverse
  {slots:['arms'],             alt:true},
  {slots:['weapon','weaponBack'], alt:false},  // the back weapon goes with the weapon
  {slots:['torso'],            alt:true},
];
var DEATH_CUT   = ['effects','effects1','effects2'];
/* PIECES THAT STAY PUT. Art drawn as hanging from the top of the frame is cut
   off at that edge on purpose, so dropping it exposes the cut and the frame
   shows through. Pinned, the Fighter falls out of it and it keeps hanging.
   From DHCF_DEATH_ANCHORED so the list has one home. */
var DEATH_ANCHORED = <?php echo json_encode(array_values(DHCF_DEATH_ANCHORED)); ?>;

function killAnim(f){
  var e = elFor(f); if (!e) return;
  e.classList.add('dying'); flashTok(f,'big');
  function anchored(img){
    return !!img && DEATH_ANCHORED.indexOf(img.getAttribute('data-s')) !== -1;
  }
  DEATH_CUT.forEach(function(slot){
    var img = e.querySelector('.art img[data-l="'+slot+'"]');
    if (img && !anchored(img)) img.className = 'snuff';
  });
  var step = 80, n = 0;
  DEATH_ORDER.forEach(function(group){
    var found = false;
    group.slots.forEach(function(slot){
      var img = e.querySelector('.art img[data-l="'+slot+'"]'); if (!img) return;
      /* An anchored piece is left exactly where it is -- not animated, not
         counted. The group it belonged to still falls: a head grouped with
         an anchored headgear drops out of it, which is the point. */
      if (anchored(img)) return;
      found = true;
      // one delay and one spin for the whole group, so its pieces stay together
      img.style.animationDelay = (n*step)+'ms';
      img.className = 'dis' + (group.alt ? ' alt' : '') + (group.gone ? ' gone' : '');
    });
    if (found) n++;      // keep the stagger contiguous when a slot is empty
  });
  setTimeout(function(){ e.classList.remove('dying'); }, n*step + 640);
}
function shakeTok(f, big){
  var e = elFor(f); if (!e) return;
  e.classList.remove('hit','big'); void e.offsetWidth;
  e.classList.add('hit'); if (big) e.classList.add('big');
  flashTok(f, big?'big':'');
}
/** Animate trait layers together and in step. TAKES A LIST because some layers
 *  are rigidly attached in the art — headgear sits ON the head, so jolting the
 *  head alone slides the skull out from under its own helmet. */
function layerAnim(f, slots, cls){
  var e = elFor(f); if (!e) return;
  if (typeof slots === 'string') slots = [slots];
  slots.forEach(function(slot){
    var img = e.querySelector('.art img[data-l="'+slot+'"]'); if (!img) return;
    img.classList.remove(cls); void img.offsetWidth; img.classList.add(cls);
  });
}
function actTok(f){
  var e = elFor(f); if (!e) return;
  e.classList.remove('act'); void e.offsetWidth; e.classList.add('act');
  layerAnim(f,'weapon','swing');       // the weapon that did it actually swings
  layerAnim(f,'companion','bob');      // and the companion reacts alongside it
}
function pop(f, txt, kind){
  var e = elFor(f); if (!e) return;
  var p = document.createElement('div');
  p.className = 'pop on '+(kind||''); p.textContent = txt;
  e.appendChild(p); setTimeout(function(){ p.remove(); }, 950);
}
function shakeBoard(){
  var bw = document.querySelector('.boardwrap'); if (!bw) return;
  bw.classList.remove('shake'); void bw.offsetWidth; bw.classList.add('shake');
}

/* ================================ THE TIMELINE PLAYER =======================
   fx is what the server did, in order. Each event is handled and returns how
   long to wait before the next one. Nothing here reads a rule or a number that
   did not come down the wire.

   Every handler is guarded. An exception escaping this loop is not cosmetic: it
   would leave busy true and the board dead with the turn never handed back,
   which is exactly what one dangling reference did to the prototype.
   ============================================================================ */
/* How long the board sits still, with THEIR MOVE up and the cell they are
   about to slide lit, before a defending move actually happens. 640ms was not
   enough for players to register the handover; a second reads as "something
   else is taking a turn now" rather than as lag. The second and later slides
   of the same exchange are quicker -- by then you know who is acting. */
var FOE_LEAD = 1000, FOE_LEAD_AGAIN = 700;

function playTimeline(fx, state, done){
  var i = 0, hold = 190;      // how long the current clear sits before it falls
  /* Reset per exchange, not per event: the announcement belongs to the first
     defending slide in THIS timeline. */
  var foeAnnounced = false;

  function step(){
    if (i >= fx.length) { finish(); return; }
    var e = fx[i++], wait = 0;
    try { wait = handle(e); } catch (err) {
      logLine('sys','(effect skipped)');
      if (window.console) console.error('fx', e && e.k, err);
    }
    setTimeout(step, wait || 0);
  }

  function handle(e){
    switch (e.k) {

    case 'slide':
      /* A DEFENDING MOVE IS ANNOUNCED, not merely preceded by a pause.
         The pause existed and was not enough: players reported mistaking the
         defending Crew's move for their own, or for a glitch damaging them out
         of nowhere. A quiet beat and a faint red border are both easy to miss
         while you are watching the gems -- so the handover now says so, in the
         same place the game already shouts CHAIN and BOMB, and holds long
         enough to be read before anything moves.
         Only the FIRST slide of their exchange announces it; an extra turn is
         already announced by 'again', and "THEIR MOVE" three times in a row
         would be noise rather than information. */
      if (e.side === 'foes') {
        S.turn = 'foes'; paintChrome();
        var c = cellEl(e.a); if (c) c.classList.add('sel');
        var lead = FOE_LEAD;
        if (!foeAnnounced) {
          foeAnnounced = true;
          banner('THEIR MOVE', 10);   // outranks everything: it is the context
          sfx('pick');
        } else {
          lead = FOE_LEAD_AGAIN;      // they are already established as acting
        }
        setTimeout(function(){ doSlide(e); }, lead - 220);
        return lead;
      }
      doSlide(e);
      return 150;

    case 'wave':
      /* A wave is a new beat, so the hold starts over. Without this the window
         spans waves: one multi-match on the first link of a cascade outranked
         every CHAIN announcement that followed it, and chains stopped appearing
         at all -- a fix for one hidden announcement that hid a different one.
         Priority is for resolving a collision INSIDE a wave; across waves the
         newer event simply wins. */
      bannerAt = 0;
      sfx('clear');
      // a deep chain is worth more than a shallow one, and outranks a bomb
      if (e.chain > 1) { sfx('chain'); banner('CHAIN x'+e.chain, e.chain >= 3 ? 4 : 3); }
      (e.cells||[]).forEach(function(j){ var el = cellEl(j); if (el) el.classList.add('clear'); });
      if (e.len >= 5) sfx('great');
      hold = 190;
      return 0;

    case 'multi':
      banner(e.mega ? '✦✦ MEGA MULTI-MATCH' : '✦ MULTI-MATCH', e.mega ? 6 : 5);
      sfx('chain');
      return 0;

    case 'act':    actTok(fighterAt(e.side, e.i)); return 0;

    case 'hit': {
      var t = fighterAt(e.side, e.i);
      pop(t, '-'+e.v, e.crit ? 'big' : '');
      shakeTok(t, !!e.crit);
      layerAnim(t, ['head','headgear'], 'jolt');   // together, or the skull slides out
      if (t) { t.hp = Math.max(0, t.hp - e.v); paintTok(t); }
      return 0;
    }
    case 'shielded': {
      var t2 = fighterAt(e.side, e.i);
      pop(t2, '-'+e.v+' shield', 'heal');
      if (t2) { t2.shield = Math.max(0, t2.shield - e.v); paintTok(t2); }
      return 0;
    }
    case 'sunder': {
      /* Break shattering shield. Its own event rather than a 'shielded', because
         nothing was absorbed -- the shield was taken off before the hit lands. */
      var t3 = fighterAt(e.side, e.i);
      pop(t3, '\u2715 '+e.v+' shield', '');
      if (t3) { t3.shield = Math.max(0, t3.shield - e.v); paintTok(t3); }
      return 0;
    }
    case 'heal': {
      var t4 = fighterAt(e.side, e.i);
      pop(t4, '+'+e.v, 'heal'); flashTok(t4, 'heal');
      if (t4) { t4.hp = Math.min(t4.maxHp, t4.hp + e.v); paintTok(t4); }
      return 0;
    }
    case 'shield': {
      var t5 = fighterAt(e.side, e.i);
      flashTok(t5, 'shield');
      if (t5) { t5.shield += e.v; paintTok(t5); }
      if (e.i === 0) sfx('shield');
      return 0;
    }
    case 'charge':
      /* Values, not a delta: each Fighter charges at its own rate. */
      for (var ci in (e.s||{})) {
        var cf = fighterAt(e.side, +ci); if (cf) cf.surge = e.s[ci];
      }
      paintCharge(e.side);
      return 0;

    case 'erupt': {
      var f = fighterAt(e.side, e.i);
      actTok(f); pop(f, 'SURGE!', 'big'); sfx('erupt');
      if (f) { f.surge = 0; paintCharge(e.side); }   // spent, and the meter says so
      return 0;
    }
    case 'ko': {
      sfx('ko');
      var kf = fighterAt(e.side, e.i);
      killAnim(kf);
      // .ko while .dying is a case the stylesheet already handles, so the
      // collapse plays at full opacity and the grey only lands after it
      if (kf) { kf.ko = true; paintTok(kf); paintCharge(e.side); }
      return 0;
    }

    case 'boom':
      (e.cells||[]).forEach(function(j){
        var el = cellEl(j); if (el) el.classList.add(e.kind === BOMB_BOARD ? 'blast2' : 'blast');
      });
      /* A CHAIN SAYS SO. Half of all detonations set off more than one bomb,
         and the damage climbs steeply with each -- five of them hit twelve
         times as hard as one. Announcing every detonation as "BOMB" hid the
         difference between a tidy clear and the thing you had been hoarding
         for. It outranks almost everything when it is big, because it IS. */
      var n = e.n || 1;
      banner(n > 1 ? '💥 ' + n + ' BOMBS CHAIN'
                   : (e.kind === BOMB_BOARD ? '💣 BOARD BOMB' : '✛ BOMB'),
             n >= 3 ? 8 : (n > 1 ? 6 : (e.kind === BOMB_BOARD ? 5 : 3)));
      sfx('boom'); shakeBoard();
      hold = 460;         // the blast is held longer, or it is skipped past
      return 0;

    case 'arm':
      sfx(e.big ? 'armB' : 'armX');
      // armed, not detonated: a promise rather than an event, so it yields to
      // anything that actually happened this wave
      banner(e.up ? (e.big ? '💣 BOMB UPGRADED' : '✛ BOMB UPGRADED')
                  : (e.big ? '💣 BOARD BOMB ARMED' : '✛ BOMB ARMED'), e.big ? 3 : 2);
      return 0;

    case 'drop': {
      /* The authoritative board after this link of the cascade. The client has
         no collapse of its own — it is handed the result. */
      var h = hold; hold = 190;
      setTimeout(function(){
        S.board = e.board.slice(); S.bomb = e.bomb.slice();
        paintBoard(true); paintTeams(); sfx('land');
      }, h);
      return h + 170;
    }

    case 'reshuffle':
      logLine('sys','No moves left — board reshuffled.');
      banner('RESHUFFLE', 4);
      return 260;

    case 'again':
      /* WHOSE extra turn. A bare "AGAIN" during a defending chain read as the
         player's own board misbehaving. */
      banner(e.side === 'foes' ? 'THEY GO AGAIN' : 'YOU GO AGAIN', e.side === 'foes' ? 9 : 6);
      return e.side === 'foes' ? 460 : 320;

    case 'hurrah':
      /* Every bomb left on the board goes off once the battle is decided. Pure
         spectacle — the result is already settled, nothing is resolved by it. */
      banner('💥 LAST HURRAH', 9);
      (e.cells||[]).forEach(function(j){
        var el = cellEl(j); if (el) el.classList.add(e.kind === BOMB_BOARD ? 'blast2' : 'blast');
      });
      sfx('boom'); shakeBoard();
      return 700;

    default: return 0;
    }
  }

  function doSlide(e){
    var nb = slid(S.board, e.a, e.z);
    if (nb) { S.bomb = slid(S.bomb, e.a, e.z) || S.bomb; S.board = nb; }
    /* The settle pulse is for a board that has just moved on screen, which is
       true of a defending slide and no longer true of your own -- the preview
       has been showing the result since you let go, so pulsing it here is a
       double-take on a move you already made. */
    paintBoard(false, e.side === 'foes');
  }

  function finish(){
    // whatever the animation did to the local copy, the server's word is final
    S = state;
    paintBoard(); paintTeams(); paintChrome();
    (S.log||[]).forEach(function(line){ logLine(lineKind(line), line); });
    done && done();
  }

  step();
}
function lineKind(line){
  if (line.indexOf('☠') === 0 || line.indexOf('—') === 0) return 'big';
  if (line.indexOf('bleeds') !== -1 || line.indexOf('reshuffled') !== -1) return 'sys';
  return 'you';
}

/* --------------------------------------------------------------- moving ---- */
/**
 * One request. `fail` is not optional decoration: without it every failure ended
 * up in #log, which lives INSIDE the hidden battle view, so a start that went
 * wrong printed its explanation somewhere the player could not see and left the
 * button disabled with no way back but a refresh.
 *
 * A throw inside `cb` is treated as a failure too. It lands in the same promise
 * chain as a network error, and swallowing it silently is what turns a small
 * rendering bug into "the Arena stalls".
 */
function post(body, cb, fail, url){
  var fd = new FormData();
  Object.keys(body).forEach(function(k){
    if (Array.isArray(body[k])) body[k].forEach(function(v){ fd.append(k+'[]', v); });
    else fd.append(k, body[k]);
  });
  var done = false;
  // A fetch that never settles would leave the page waiting for ever. Twenty
  // seconds is far beyond a normal turn and still short enough to act on.
  var timer = setTimeout(function(){
    if (done) return;
    done = true;
    oops(new Error('timed out'));
  }, 20000);
  function oops(err){
    if (window.console) console.error('arena', body['do'], err);
    if (fail) fail(err); else logLine('sys','Lost contact with the Arena — your move was not played.');
    busy = false;
  }
  fetch(url || 'ajax/dhcarena-action.php', {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.text();
    })
    .then(function(txt){
      // .json() would throw a parse error that says nothing about WHAT came
      // back. A PHP warning printed ahead of the payload is the likeliest
      // cause, and the first hundred characters of it name the file and line.
      var res;
      try { res = JSON.parse(txt); }
      catch (e) { throw new Error('bad reply: ' + txt.slice(0, 160)); }
      if (done) return;
      done = true; clearTimeout(timer);
      cb(res);
    })
    .catch(function(err){
      if (done) return;
      done = true; clearTimeout(timer);
      oops(err);
    });
}
function sendMove(a, z){
  busy = true;
  var body = live
    ? {do:'move', live:live.id, a:a, z:z}
    : practice
    ? {do:'move', spec:JSON.stringify(practice), a:a, z:z}
    : bossRun
    ? {do:'move', run_id:bossRun, a:a, z:z}
    : {do:'move', battle_id:battleId, a:a, z:z};
  /*
   * A BATTLE THAT IS NOT LIVE, NOT PRACTICE, NOT A BOSS RUN AND HAS NO
   * ID IS NOT A BATTLE, and must not be posted anywhere.
   *
   * Reported as "Lost contact with the Arena - that move was not
   * played", over and over, on practice as a guest. The route there:
   * startPractice() caught a draw error, set practice = null, and left
   * the board on screen. Every later move then fell through this
   * ternary to the RANKED endpoint with battle_id 0 -- and
   * ajax/dhcarena-action.php answers a logged-out request with a 302 to
   * HTML, which fetch follows and JSON.parse cannot read. A cosmetic
   * drawing bug became a dead board that blamed the network.
   *
   * A ranked battle always has a real id, so this cannot fire on one.
   *
   * AND A BOSS RUN IS THE THIRD KIND, which this did not know about.
   * A run is identified by run_id and openBattle() is handed battle_id 0
   * deliberately, so every single move on the boss board was caught here,
   * refused with the Arena's wording and never posted -- the board looked
   * alive and answered every match with the no-move sound. The ternary
   * directly above already had the boss arm; this guard, written for the
   * two kinds that existed then, did not. That is the hazard of a list of
   * kinds: the ternary fails loudly when a kind is missing and a negated
   * list fails silently.
   */
  if (!live && !practice && !bossRun && !(battleId > 0)) {
    busy = false;
    paintBoard();
    logLine('sys', BOSS_MODE
      ? 'This run is not connected to the Bosses any more — send a new Crew.'
      : 'This battle is not connected to the Arena any more — start a new one.');
    sfx('bad');
    return;
  }
  post(body, function(res){
    if (res && res.ok && res.spec) practice = res.spec;
    if (live && res) liveTook(res);
    if (!res || !res.ok) {
      busy = false;
      logLine('sys', (res && res.message) || 'That move was refused.');
      if (res && res.state) S = res.state;
      // Repaint either way: the board is still holding the dropped gem where
      // snapPreview() left it, and a refused move must not be left looking
      // like it was made.
      paintBoard(); paintTeams(); paintChrome();
      sfx('bad');
      return;
    }
    try {
      playTimeline(res.state.fx || [], res.state, function(){
        busy = false;
        if (res.over) showEnd(res);
      });
    } catch (e) {
      // The exchange happened on the server whatever the browser managed to
      // draw, so take the state and let play continue rather than freezing.
      busy = false;
      S = res.state; paintBoard(); paintTeams(); paintChrome();
      logLine('sys','(the animation was skipped)');
      if (window.console) console.error('arena timeline', e);
      if (res.over) showEnd(res);
    }
  }, function(){
    // Same reason, for a request that never came back at all: the preview is
    // standing in for a board state the server never confirmed, so put the
    // real one back rather than leaving a slide that did not happen on screen.
    paintBoard();
    logLine('sys','Lost contact with the Arena — that move was not played.');
  }, live ? LIVE_URL : practice ? PRACTICE_URL : bossRun ? BOSS_URL : null);
}

/* ------------------------------------------------------------ the end ------ */
/**
 * The post X will open with. Written from what actually happened rather than a
 * fixed line, because a result nobody can tell apart from anyone else's is not
 * worth posting -- and the url is the public Arena, which now opens a playable
 * battle for whoever taps it instead of a login prompt.
 *
 * No score to quote: this game ends in a Crew standing or not, so the boast is
 * the margin and the chain. X wraps every url as 23 characters no matter its
 * length, so the body is budgeted against that the way shareOnXUrl() does.
 */
function shareLink(won, standing){
  var el = $('ecShare'); if (!el) return;

  /*
   * WHO, NOT JUST WHAT. "Swept the DHC Arena" read identically whoever was
   * on the other side and whoever did it -- so the post named no opponent,
   * no Fighter, and gave a reader nothing to be curious about.
   *
   * NAMED IN WORDS, NEVER @-TAGGED. S.meta.defenderName is a Discord
   * username; the platform stores no X handle for anybody, so an @ would
   * tag whichever stranger holds that name over there. dhc-fighter-modal.php
   * credits a Fighter's builder the same way and for the same reason.
   */
  /* S.defenderName, not S.meta.defenderName: dhca_public() does not send
     meta -- it holds the Fighter ids and the move list -- so reading it
     there found nothing every time and every post said "a real opponent". */
  var foe = (S && S.defenderName) ? String(S.defenderName) : '';
  /*
   * PRACTICE HAS NO OPPONENT, so it must not claim one. A practice battle is
   * built from a generated spec with no meta at all -- the board is never
   * stored server-side -- so the name is simply absent, and the first
   * version fell through to "a real opponent" and posted "(Practice) My Crew
   * went down to a real opponent", which is both untrue and contradicts its
   * own first word.
   *
   * The remaining nameless case is a ranked battle resumed from before
   * defenderName was carried, where "a real opponent" is accurate.
   */
  /*
   * AND WHAT THEY ARE TO YOU, when the game already knows.
   *
   * Lifted from a player who hand-edited his own post to read "Swept (my
   * tormentor) @oculusorbus's Crew". The Arena has tracked that standing
   * since the rivalry panel shipped; the share simply never offered it,
   * so he typed it himself.
   *
   * ONLY THE TWO THAT POINT AT THE POSTER. defenderTag is 'tormentor' or
   * 'nemesis' and never 'duck' -- the server will not send the third,
   * because "(my sitting duck)" names and humiliates somebody who did not
   * write the post. These two read as the poster admitting something.
   *
   * IN FRONT OF THE NAME, where it reads as an aside rather than a title,
   * and inside the budget trim below like everything else.
   */
  var tagWord = (S && S.defenderTag) ? String(S.defenderTag) : '';
  var aside = (tagWord === 'tormentor') ? '(my tormentor) '
            : (tagWord === 'nemesis')   ? '(my nemesis) '
            : '';
  var vs = practice ? 'a practice Crew'
         : (foe ? aside + foe + "'s Crew" : 'a real opponent');

  /* The Fighters that did it, by first name -- the same shorthand the end
     card uses, so the post matches the screen it was shared from. */
  var heroes = (S && S[won ? 'mine' : 'foes'] || []).filter(function (f) { return !f.ko; })
                 .map(function (f) { return f.name.split(' ')[0]; });

  var body;
  if (won) {
    body = standing === 3
      ? 'Swept ' + vs + ' in the DHC Arena - their whole Crew down, and I did not lose a Fighter.'
      : 'Beat ' + vs + ' in the DHC Arena with ' + standing + ' Fighter'
        + (standing === 1 ? '' : 's') + ' still standing.';
    if (heroes.length && standing < 3) body += ' ' + heroes.join(' and ') + ' held the line.';
  } else {
    body = 'My Crew went down to ' + vs + ' in the DHC Arena. Going again.';
  }
  if (S && S.stats && S.stats.best > 1) body += ' Best chain x' + S.stats.best + '.';
  /* IN FRONT, because the budget trims from the END. Appended, a long
     opponent name could push "(Practice)" off the post and leave a practice
     result reading as a ranked one. */
  if (practice) body = '(Practice) ' + body;

  /*
   * THE TAIL INVITES AND THEN CREDITS, the same one the Collection share
   * carries. A bare @skulliance says who made the thing and nothing about
   * why a stranger should turn up, and none of this exists without
   * Maxingo's art -- a post going out uncredited is the wrong default.
   */
  var tail = '\n\nJoin @skulliance and battle other players in the Arena, free and in any browser.'
           + '\n\nArt by @MMAXI404';
  /* X counts ANY url as 23 characters and cuts the overflow off the END,
     which is where the invitation and the credit live. Budget for it. */
  var limit = 280 - 24 - tail.length;
  if (body.length > limit) body = body.slice(0, limit - 1).replace(/\s+\S*$/, '') + '…';
  /* The link names the BATTLE, which is what gives the post a picture of
     the match instead of the site's stock card. It still opens the playable
     Arena; only the meta differs. */
  var url = 'https://skulliance.io/staking/dhcarena.php'
          + (battleId > 0 ? '?battle=' + battleId : '');
  el.href = 'https://x.com/intent/post?text=' + encodeURIComponent(body + tail)
          + '&url=' + encodeURIComponent(url);
}
function showEnd(res){
  var won = res.over === 'mine';
  var card = $('endcard');
  var standing = S[won ? 'mine' : 'foes'].filter(function(f){ return !f.ko; });
  card.className = 'endcard '+(won?'win':'lose');
  $('ecTitle').textContent = won ? 'Victory' : 'Defeat';
  var sub;
  if (won) {
    sub = standing.length === 3
      ? 'Their Crew is down and yours did not lose a Fighter.'
      : standing.length+' of your Fighters still standing — '
        + standing.map(function(f){ return f.name.split(' ')[0]; }).join(' and ') + '.';
    /* Practice pays nothing and says so, rather than staying quiet and letting
       a player wonder where their trait went. */
    /* NAME THE ACTUAL RULE. This used to blame the daily cap for everything,
       which was wrong twice over: a repeat win off the same rival is a
       different rule, and a genuinely spent cap leaves rewarded === true and
       so said nothing at all. Reported as "I won and got no trait -- bug?",
       which is what an unexplained blank looks like. */
    if (practice)                       sub += ' Practice — nothing was at stake.';
    else if (res.drop)                  sub += ' A trait dropped — check your collection.';
    else if (res.nodrop === 'opponent') sub += ' No trait: this rival already paid out today. '
                                             + 'One trait per opponent — beat someone new for another.';
    else if (res.nodrop === 'cap')      sub += ' No trait: your three Arena drops for today are spent.';
    else if (res.rewarded === false)    sub += ' No trait this time.';
  } else {
    sub = practice
      ? 'Your Crew is down. Practice — nothing was at stake, so go again.'
      : 'Your Crew is down. The Fighters you sent are resurrecting — they will be back.';
  }
  /*
   * A BOSS RUN IS NOT WON OR LOST THE WAY A BATTLE IS.
   *
   * Most runs end with the Crew wiped and that is the normal, paying
   * outcome -- the day's trait is decided by damage, not by kills. Saying
   * "Defeat" and nothing else would tell a player their run was worthless
   * when it was the entire point of the feature.
   */
  if (bossRun) {
    var dealt = res.dealt || 0;
    $('ecTitle').textContent = (res.killed && res.killed.length)
      ? (res.killed.join(' and ') + ' is down')
      : (won ? 'The crew is down' : 'Crew wiped');
    card.className = 'endcard ' + ((won || (res.killed && res.killed.length)) ? 'win' : 'lose');
    sub = dealt.toLocaleString() + ' damage landed on the boss crew.';
    /*
     * DID THIS RUN DROP THE CREW. The reply carries the week's state AFTER
     * the damage was committed, so a wave number higher than the one this
     * run was fought on means the crew fell and the next one is already
     * standing. That is the biggest thing that can happen in a week and it
     * should not be left to a percentage.
     */
    var pr = res.progress;
    var dropped = pr && (pr.cleared || (res.wave && pr.wave > res.wave));
    if (dropped) {
      $('ecTitle').textContent = 'The crew is down';
      card.className = 'endcard win';
      sub = dealt.toLocaleString() + ' damage, and that is the crew down — '
          + (pr.downed === 1 ? 'the first' : 'number ' + pr.downed) + ' this week. '
          + 'The best trait table is locked in for everyone who takes part, and it '
          + 'cannot be taken back.';
      if (pr.wave > 1) sub += ' They are getting back up angrier: wave ' + pr.wave + '.';
    } else if (pr) {
      sub += ' They are at ' + Math.round(100 * (1 - pr.pct)) + '% and still standing.';
      sub += ' Everyone who takes part is paid when the week closes.';
    }
  }
  $('ecSub').textContent = sub;
  $('ecStats').innerHTML =
      '<span><b>'+S.round+'</b>rounds</span>'
    + '<span><b>'+S.stats.bombs+'</b>bombs armed</span>'
    + '<span><b>'+S.stats.blasts+'</b>detonated</span>'
    + '<span><b>x'+S.stats.best+'</b>best chain</span>';
  $('ecAgain').textContent = practice ? 'Practice again'
                           : bossRun ? 'Back to the Bosses' : 'Back to the Arena';
  $('ecLeave').hidden = !practice;
  shareLink(won, standing.length);
  /*
   * A BOSS RUN POSTS WHAT IT ACTUALLY DID, and the text is built on the
   * SERVER -- dhcb_share_text() -- so a post cannot claim a kill the run
   * did not get and the 280-character budget is enforced in one place
   * rather than in two that disagree. shareLink() above has already set a
   * ranked href; this replaces it for the boss case.
   */
  if (bossRun && res.share) {
    var bel = $('ecShare');
    if (bel) {
      var btail = '\n\nJoin @skulliance and take on the DHC boss crew, free and in any browser.'
                + '\n\nArt by @MMAXI404';
      var bbody = String(res.share);
      var blimit = 280 - 24 - btail.length;
      if (bbody.length > blimit) bbody = bbody.slice(0, blimit - 1).replace(/\s+\S*$/, '') + '…';
      /* NAMING THE RUN is what gives the post a picture of the fight
         instead of the site's stock card -- same mechanism the ranked
         share uses, and the link still opens the playable page. */
      var burl = 'https://skulliance.io/staking/dhcbosses.php'
               + (bossRun > 0 ? '?run=' + bossRun : '');
      bel.href = 'https://x.com/intent/post?text=' + encodeURIComponent(bbody + btail)
               + '&url=' + encodeURIComponent(burl);
    }
  }
  card.hidden = false;
  sfx(won ? 'win' : 'lose');
  logLine('big', won ? 'VICTORY — their Crew is down.' : 'DEFEAT — your Crew is down.');
}


/* ------------------------------------------------------- input: dragging ---
   Live preview while dragging, because that IS the feel: the gem follows your
   finger along one axis and the gems it displaces shift the other way, so you
   see the move before committing it. Pointer events so mouse and touch are one
   path. A slide that matches nothing reverts for free and costs no turn. */
function cellSize(){
  var c = gridEl.querySelector('.cell'); if (!c) return 40;
  var r = c.getBoundingClientRect();
  var g = parseFloat(getComputedStyle(gridEl).gap) || 3;
  return r.width + g;
}
function clearOffsets(){
  gridEl.querySelectorAll('.cell').forEach(function(e){
    e.style.transition = ''; e.style.transform = ''; e.style.zIndex = '';
  });
}
function previewDrag(){
  if (!drag || !drag.axis) return;
  var sz = drag.size, a = drag.from, ra = Math.floor(a/N), ca = a%N;
  var raw = drag.axis === 'row' ? drag.dx : drag.dy;
  var maxBack = (drag.axis === 'row' ? ca : ra) * sz;
  var maxFwd  = ((N-1) - (drag.axis === 'row' ? ca : ra)) * sz;
  var off = Math.max(-maxBack, Math.min(maxFwd, raw));
  var steps = Math.round(off/sz);
  drag.to = drag.axis === 'row' ? idx(ra, ca+steps) : idx(ra+steps, ca);

  clearOffsets();
  var lead = cellEl(a);
  if (lead) { lead.style.zIndex = '6';
    lead.style.transform = drag.axis === 'row' ? 'translateX('+off+'px) scale(1.06)'
                                               : 'translateY('+off+'px) scale(1.06)'; }
  if (steps !== 0) {
    var dir = steps > 0 ? 1 : -1;
    for (var k=1; k<=Math.abs(steps); k++) {
      var i = drag.axis === 'row' ? idx(ra, ca+dir*k) : idx(ra+dir*k, ca);
      var e = cellEl(i); if (!e) continue;
      e.style.transition = 'transform .08s';
      e.style.transform = drag.axis === 'row' ? 'translateX('+(-dir*sz)+'px)'
                                              : 'translateY('+(-dir*sz)+'px)';
    }
  }
}
/**
 * A COMMITTED SLIDE DOES NOT SPRING BACK.
 *
 * clearOffsets() used to run before this even knew whether the move was being
 * committed, so every accepted slide played out as: gem snaps home, pause,
 * board jumps to the match. On a phone that pause is the network round trip
 * and it is long enough to read as a bug -- it looks like the move was
 * rejected and then reconsidered.
 *
 * It only looks that way because of WHERE the rules live now. In the prototype
 * the board updated in the same frame as the release, so wiping the drag
 * preview and redrawing were one step. Here the authoritative board is a
 * request away, and the preview is the only thing standing in for it until the
 * answer lands -- so it has to stay standing.
 *
 * The gem is snapped to the cell it was dropped on and LEFT there. When the
 * reply arrives the board is repainted into the arrangement it is already
 * showing, so there is nothing to see. A move that makes no match still
 * springs back, which is correct: that one really was refused.
 */
function snapPreview(from, to, axis, sz){
  var ra = Math.floor(from / N), ca = from % N;
  var steps = (axis === 'row') ? ((to % N) - ca) : (Math.floor(to / N) - ra);
  if (!steps) return;
  var dir = steps > 0 ? 1 : -1, off = steps * sz, k, i, e;
  var lead = cellEl(from);
  if (lead) {
    lead.style.transition = 'transform .09s ease-out';
    lead.style.zIndex = '6';
    // the 1.06 lift goes: the gem is landing now, not being carried
    lead.style.transform = (axis === 'row') ? 'translateX(' + off + 'px)'
                                            : 'translateY(' + off + 'px)';
  }
  for (k = 1; k <= Math.abs(steps); k++) {
    i = (axis === 'row') ? idx(ra, ca + dir * k) : idx(ra + dir * k, ca);
    e = cellEl(i); if (!e) continue;
    e.style.transition = 'transform .09s ease-out';
    e.style.transform = (axis === 'row') ? 'translateX(' + (-dir * sz) + 'px)'
                                         : 'translateY(' + (-dir * sz) + 'px)';
  }
}
function endDrag(commit){
  if (!drag) return;
  var from = drag.from, to = drag.to, axis = drag.axis, sz = drag.size;
  drag = null;
  if (!commit || to === undefined || to === from) { clearOffsets(); return; }
  var nb = slid(S.board, from, to);
  if (!nb) { clearOffsets(); return; }
  if (!findMatches(nb).length) {
    clearOffsets();          // a free revert, and it SHOULD look like one
    sfx('bad'); logLine('sys','No match on that slide — free, try another.');
    return;
  }
  snapPreview(from, to, axis, sz);
  sendMove(from, to);
}
gridEl.addEventListener('pointerdown', function(e){
  if (busy || !S || S.over || S.turn !== 'mine') return;
  var c = e.target.closest('.cell'); if (!c) return;
  e.preventDefault();
  if (gridEl.setPointerCapture) gridEl.setPointerCapture(e.pointerId);
  drag = {from:+c.getAttribute('data-i'), x0:e.clientX, y0:e.clientY, dx:0, dy:0,
          axis:null, size:cellSize(), to:undefined};
  sfx('pick'); c.classList.add('sel');
});
gridEl.addEventListener('pointermove', function(e){
  if (!drag) return;
  drag.dx = e.clientX - drag.x0; drag.dy = e.clientY - drag.y0;
  if (!drag.axis) {
    if (Math.abs(drag.dx) > 6 && Math.abs(drag.dx) > Math.abs(drag.dy)) drag.axis = 'row';
    else if (Math.abs(drag.dy) > 6 && Math.abs(drag.dy) > Math.abs(drag.dx)) drag.axis = 'col';
  }
  if (drag.axis) previewDrag();
});
function release(){
  if (!drag) return;
  gridEl.querySelectorAll('.cell.sel').forEach(function(x){ x.classList.remove('sel'); });
  endDrag(true);
}
gridEl.addEventListener('pointerup', release);
gridEl.addEventListener('pointercancel', function(){ if (drag) { clearOffsets(); drag = null; } });
window.addEventListener('pointerup', function(){ if (drag) release(); });

/* ------------------------------------------------------------- the shell --- */
var picked = [], rival = 0;
/* DHC BOSSES IS A MODE, NOT A SECOND CLIENT. bossRun is the id of a run in
   progress and doubles as the mode flag, exactly as `live` and `practice` do
   above it -- every branch below that already asks "which kind of battle is
   this" gains one more arm and nothing else changes. */
var bossRun = 0;
var BOSS_URL = 'ajax/dhcbosses-action.php';
/* WHICH PAGE THIS IS. Not a toggle the player can flip -- the mode is the URL
   they are on, so there is no hidden state to discover and the cards carry one
   cooldown, the one that applies here. It is read only for the few sentences
   the two pages word differently. */
var BOSS_MODE = <?php echo $IS_BOSS ? 'true' : 'false'; ?>;
/*
 * THE ROUND CLOCK, TICKING.
 *
 * A server-rendered figure is right for one second and then quietly wrong,
 * and this page is one people leave open between runs -- which is exactly
 * the window in which the round turns underneath them and the allowance
 * appears to break. The number has to move.
 *
 * IT COUNTS DOWN FROM THE SERVER'S FIGURE RATHER THAN FROM A LOCAL CLOCK.
 * The boundary is 4pm America/Chicago computed in MySQL, and the browser
 * may be in any timezone with any amount of drift; subtracting elapsed
 * local time from a server-supplied remainder is correct whatever the
 * device thinks the time is.
 *
 * At zero it does NOT reload. The player may be mid-run, and throwing away
 * a board to refresh a counter would be a worse bug than the one this
 * fixes. It says the round turned and leaves the choice alone.
 */
(function(){
  var el = document.getElementById('aRoundLeft');
  if (!el) return;
  var left = parseInt(el.getAttribute('data-left'), 10);
  if (!(left > 0)) return;
  var strip = el.closest ? el.closest('.a-round') : null;
  var t0 = Date.now();
  function hms(s){
    var h = Math.floor(s/3600), m = Math.floor((s%3600)/60), x = s%60;
    return h ? h+'h '+m+'m' : (m ? m+'m '+x+'s' : x+'s');
  }
  function tick(){
    var s = left - Math.floor((Date.now() - t0) / 1000);
    if (s <= 0) {
      el.textContent = 'now — reload for ' + CREW_SIZE + ' more';
      if (strip) strip.classList.add('turned');
      clearInterval(iv);
      return;
    }
    el.textContent = hms(s);
  }
  var iv = setInterval(tick, 1000);
  tick();
}());
/*
 * WHICH CREW IS ON THE BOARD, for the colour grade. The bosses get back up
 * within the week one wave higher and the grade is how that reads -- the
 * panel does it from PHP, the board has to be told, because the board is
 * drawn from a JSON reply. Cleared on anything that is not a boss run so a
 * ranked opponent can never inherit it.
 */
function bossWave(res){
  var col = document.querySelector('.teamcol.foes');
  if (!col) return;
  var w = (res && res.progress && res.progress.wave) ? Math.min(5, res.progress.wave) : 0;
  if (w > 1) col.setAttribute('data-wave', w);
  else       col.removeAttribute('data-wave');
}

/* ---------------------------------------------------------------- paging ----
   Cards are hidden, never removed: a pick made on page 1 has to survive a walk
   to page 4, and rebuilding the grid would throw away the selected elements
   along with eight <img> layers apiece. */
var PER_PAGE = 12, page = 0, sortBy = 'might', kitFilter = '';
var allCards = [].slice.call(document.querySelectorAll('.a-card'));
var viewCards = allCards.slice();      // what the filter and sort left, in order
var pickGrid = document.querySelector('.a-pick');

function cardNum(c, k){ return +(c.getAttribute('data-'+k) || 0); }
/**
 * Apply the sort and the filter, then re-lay the grid.
 *
 * The cards are MOVED, never rebuilt. Rebuilding would drop the selection, and
 * selection is the entire point of this list -- a player who sorts by Toughest
 * to reconsider their back rank must not lose the two they had already chosen.
 * It also keeps the eight art layers per card out of the browser's way.
 */
function applyView(){
  viewCards = allCards.filter(function(c){
    return !kitFilter || c.getAttribute('data-kit') === kitFilter;
  });
  viewCards.sort(function(a, b){
    if (sortBy === 'might') return (cardNum(b,'hp')*cardNum(b,'pow')) - (cardNum(a,'hp')*cardNum(a,'pow'));
    return cardNum(b, sortBy) - cardNum(a, sortBy);
  });
  // a card the filter removed is hidden, not detached, so its pick survives
  allCards.forEach(function(c){ c.classList.add('off'); });
  viewCards.forEach(function(c){ pickGrid.appendChild(c); });
  page = 0;
  paintPage();
}
function pageCount(){ return Math.max(1, Math.ceil(viewCards.length / PER_PAGE)); }
function paintPage(){
  var n = pageCount();
  if (page >= n) page = n - 1;
  if (page < 0) page = 0;
  allCards.forEach(function(c){ c.classList.add('off'); });
  viewCards.forEach(function(c, i){
    if (Math.floor(i / PER_PAGE) === page) c.classList.remove('off');
  });
  var pg = $('aPager');
  if (pg) {
    pg.style.display = n > 1 ? 'flex' : 'none';
    $('aPageLbl').textContent = 'Page ' + (page+1) + ' of ' + n + ' · ' + viewCards.length
      + (viewCards.length === allCards.length ? ' Fighters' : ' of ' + allCards.length);
    $('aPrev').disabled = (page === 0);
    $('aNext').disabled = (page === n - 1);
  }
}
document.querySelectorAll('.a-tools button[data-sort]').forEach(function(btn){
  btn.addEventListener('click', function(){
    sortBy = btn.getAttribute('data-sort');
    document.querySelectorAll('.a-tools button[data-sort]').forEach(function(b){
      b.classList.toggle('on', b === btn);
    });
    applyView();
  });
});
document.querySelectorAll('.a-tools button[data-kit]').forEach(function(btn){
  btn.addEventListener('click', function(){
    kitFilter = btn.getAttribute('data-kit');
    document.querySelectorAll('.a-tools button[data-kit]').forEach(function(b){
      b.classList.toggle('on', b === btn);
    });
    applyView();
  });
});
/** The picks, as chips, always on screen. Click one to drop it. */
function paintPicked(){
  var box = $('aPicked'); if (!box) return;
  if (!picked.length) {
    box.innerHTML = '<span>Nobody picked yet — the order you pick is the formation.</span>';
    return;
  }
  var byId = {};
  allCards.forEach(function(c){ byId[+c.getAttribute('data-fid')] = c; });
  box.innerHTML = picked.map(function(id, i){
    var c = byId[id], nm = c ? c.querySelector('.nm').textContent : ('#'+id);
    return '<span class="chip" data-drop="'+id+'" title="Click to remove">'
         + '<b>'+['front','mid','back'][i]+'</b><s>'+nm+'</s><i>&times;</i></span>';
  }).join('');
  box.querySelectorAll('.chip').forEach(function(ch){
    ch.addEventListener('click', function(){
      var at = picked.indexOf(+ch.getAttribute('data-drop'));
      if (at !== -1) { picked.splice(at, 1); paintPicker(); }
    });
  });
}

/**
 * WHAT EACH RIVAL IS WORTH, ON THE RIVAL ITSELF.
 *
 * Punching up has always paid better -- dhca_pay() bands the trait roll
 * on the gap between the two Crews' best rarity scores, and the top band
 * is sixteen times the mythic chance of a level fight. Nothing said so.
 * The rival card showed "12 Fighters, best 4,310" and left the player to
 * know the function existed, know the thresholds, and do the subtraction.
 * So the rational play was to hammer the weakest Crew every time, which
 * is the opposite of what the bands were built to encourage.
 *
 * Shown only once a Crew is picked, because the gap is measured against
 * the Fighters you bring, not the ones you own. Guessing with your best
 * Fighter would quote odds that change the moment you pick anyone else.
 */
function paintOdds(){
  var foes = document.querySelectorAll('.a-foe');
  if (!foes.length || !ARENA_BANDS.length) return;
  var mine = 0;
  picked.forEach(function(id){
    var c = byFid(id);
    if (c) mine = Math.max(mine, +(c.getAttribute('data-score') || 0));
  });
  foes.forEach(function(el){
    var slot = el.querySelector('.odds');
    if (!slot) return;
    if (!picked.length) { slot.textContent = ''; el.classList.remove('up','top'); return; }
    var steps = Math.round(((+el.getAttribute('data-best') || 0) - mine) / 100);
    var band  = ARENA_BANDS[0];
    ARENA_BANDS.forEach(function(b){ if (steps >= b.from) band = b; });
    slot.textContent = band.mythic + '% mythic · ' + band.legendary + '% legendary';
    /* Two classes, not one: "better than a level fight" and "the best
       there is" are different decisions and the list should show both at
       a glance. */
    el.classList.toggle('up',  band !== ARENA_BANDS[0]);
    el.classList.toggle('top', !!band.best);
  });
}

function paintPicker(){
  allCards.forEach(function(c){
    var at = picked.indexOf(+c.getAttribute('data-fid'));
    c.classList.toggle('sel', at !== -1);
    /* The badge is the whole reason the picker is ordered. Without it the
       formation is decided by click order and never says so, which is a real
       decision taken away from the player by silence. */
    var b = c.querySelector('.pk');
    if (at === -1) { if (b) b.remove(); }
    else {
      if (!b) { b = document.createElement('div'); b.className = 'pk'; c.appendChild(b); }
      b.textContent = ['front','mid','back'][at] || (at+1);
    }
  });
  paintPicked();
  paintOdds();
  var ready = (picked.length === CREW_SIZE && rival > 0);
  /* How many of the chosen Crew cannot fight a RANKED battle. The server
     decides this again on both paths -- dhca_start() refuses them and
     dhcal_challenge() does not -- this only keeps the two buttons honest. */
  var resting = picked.filter(function(id){
    var c = byFid(id); return c && c.getAttribute('data-ok') !== '1';
  }).length;
  var go = $('aStart');
  if (go) go.disabled = !!BLOCKED || !ready || resting > 0;
  /* Live needs the same Crew and rival, but NOT the allowance and NOT a rested
     Crew -- BLOCKED is about the daily six, and the bench prices ranked
     battles. A live match has no price, so neither applies. */
  var ch = $('aChallenge');
  if (ch) ch.disabled = !ready;
  /*
   * THE BOSSES NEED A CREW BUT NO RIVAL, so they cannot ride `ready` --
   * that one requires a rival and would keep the button dead forever.
   *
   * `resting` above is already the boss cooldown on this page -- the cards
   * are rendered from dhcb_roster() here -- so there is one clock to read
   * and no chance of the button disagreeing with the card beside it.
   *
   * The button only exists when the server says entry is open, so there is
   * nothing to check for the allowance or a downed crew.
   */
  var bg = $('aBossGo');
  if (bg) bg.disabled = (picked.length !== CREW_SIZE) || resting > 0;
  var msg = $('aMsg');
  if (!msg) return;
  if (picked.length !== CREW_SIZE) msg.textContent = 'Pick '+(CREW_SIZE - picked.length)+' more.';
  else if (!rival && !BOSS_MODE)   msg.textContent = 'Now pick a rival.';
  else if (resting > 0)            msg.textContent = BOSS_MODE
      ? (resting === 1 ? 'One of them is still recovering from the Bosses.'
                       : resting + ' of them are still recovering from the Bosses.')
      : (resting === 1 ? 'One of them is still recovering — live only.'
                       : resting + ' of them are still recovering — live only.');
  else if (BLOCKED)                msg.textContent = '';
  else                             msg.textContent = '';
}
/* The card for a Fighter id. allCards is rebuilt by paging, so this is looked
   up rather than cached. */
function byFid(id){
  for (var i = 0; i < allCards.length; i++)
    if (+allCards[i].getAttribute('data-fid') === id) return allCards[i];
  return null;
}
allCards.forEach(function(c){
  c.addEventListener('click', function(){
    /* A recovering Fighter IS selectable, because a LIVE match allows it --
       nothing is at stake there, so the bench has nothing to protect. Refusing
       the click made that impossible to discover and contradicted the server,
       which accepts them. What the Fighter's state gates is the ACTION, not
       the selection: Enter the Arena goes dark and says why, Challenge live
       does not. */
    var id = +c.getAttribute('data-fid'), at = picked.indexOf(id);
    if (at !== -1) picked.splice(at,1);
    else if (picked.length < CREW_SIZE) picked.push(id);
    paintPicker();
  });
});
if ($('aPrev')) $('aPrev').addEventListener('click', function(){ page--; paintPage(); });
if ($('aNext')) $('aNext').addEventListener('click', function(){ page++; paintPage(); });

document.querySelectorAll('.a-foe').forEach(function(r){
  r.addEventListener('click', function(){
    document.querySelectorAll('.a-foe').forEach(function(x){ x.classList.remove('sel'); });
    r.classList.add('sel'); rival = +r.getAttribute('data-uid');
    paintPicker();
  });
});
var wrap = document.querySelector('.arena-wrap');
/* --------------------------------------------------------- Fighter card ----
   What the board cannot say. Three traits decide a Fighter and five do not,
   and there is nowhere else in the game that tells you which is which.
   ---------------------------------------------------------------------- */
var SLOT_LABEL = {background:'Background', torso:'Torso', head:'Head', headgear:'Headgear',
                  arms:'Arms', weapon:'Weapon', effects:'Effects', effects1:'Effects',
                  effects2:'Effects 2', companion:'Companion', weaponBack:'Weapon (behind)'};
function prettySlug(sl){
  return String(sl||'').replace(/-/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
}
function traitOf(f, cat){
  var t = f.traits || {};
  if (t[cat]) return t[cat];
  // saved Fighters store effects1/effects2; a practice Crew stores effects
  if (cat === 'effects') return t.effects1 || t.effects2 || '';
  return '';
}
function tierOf(f, cat){
  var ti = f.tiers || {};
  return ti[cat] || (cat === 'effects' ? (ti.effects1 || ti.effects2 || '') : '');
}
function srcCell(f, cat){
  var sl = traitOf(f, cat);
  if (!sl) return '<span class="src">—</span>';
  var tier = tierOf(f, cat);
  return '<span class="src">' + prettySlug(sl)
       + (tier ? ' <em>' + tier + '</em>' : '') + '</span>';
}
/* Which worn pieces gave a Fighter this, named. A stat with no source says so
   rather than showing an empty cell -- "no optics" is the actionable version of
   a 6% crit chance. */
var ROLE_WORD = {optic:'optics', armour:'armour', weapon:'weapon',
                 energy:'energy', beast:'companion', plain:'no effect'};
function roleSrc(f, role, none){
  var r = f.roles || {}, hits = [];
  Object.keys(r).forEach(function(slot){
    if (r[slot] === role && traitOf(f, slot)) hits.push(prettySlug(traitOf(f, slot)));
  });
  if (!hits.length) return '<span class="src">' + (none || '—') + '</span>';
  return '<span class="src">' + hits.join(', ') + '</span>';
}
/* THE WHOLE POINT OF THE CARD. A trait does what it IS, not what slot it hangs
   on -- a blaster arm adds damage and a protector arm adds armour, from the same
   hook. Nothing else in the game says so, so this lists every piece and names
   the job it is doing. */
function pieceRows(f){
  var r = f.roles || {}, out = '';
  ['head','headgear','arms','effects','effects1','effects2','companion'].forEach(function(slot){
    var sl = traitOf(f, slot); if (!sl || !r[slot]) return;
    out += '<div class="fc-row"><span class="k">' + (SLOT_LABEL[slot] || slot) + '</span>'
         + '<span class="v" style="font-size:10px">' + (ROLE_WORD[r[slot]] || r[slot]) + '</span>'
         + srcCell(f, slot) + '</div>';
  });
  return out || '<div class="fc-row"><span class="src">Nothing but a torso, a head and a weapon.</span></div>';
}
function openCard(f, mine){
  var box = $('fcBox'); if (!box) return;
  var t = f.traits || {};
  var rank = ['front','mid','back'][f.rank];
  var layers = layerImgs(f, false);

  var pct = Math.round(f.hp / f.maxHp * 100);
  box.innerHTML =
      '<button class="fc-x" id="fcX" title="Close">&times;</button>'
    + '<div class="fc-top">'
    +   '<div class="fc-art">'+layers+'</div>'
    +   '<div class="fc-id">'
    +     '<h3>'+f.name+'</h3>'
    +     '<div class="fc-who">'+(mine?'your':'enemy')+' '+rank+' rank</div>'
    +     '<div class="fc-gem" style="--gemc:var(--g'+f.rank+')"><b>'
    +       (f.kit.icon ? '<i class="ico" style="--ico:url('+iconUrl(f.kit.icon)+')"></i>' : f.kit.emoji)
    +     '</b>'
    +       'matching this gem makes them act</div>'
    +     '<div class="fc-kit"><b>'+f.kit.name+'</b> — '+f.kit.note+'</div>'
    +   '</div>'
    + '</div>'
    + '<div class="fc-body">'
    +   '<p class="fc-h">What decides this Fighter</p>'
    +   '<div class="fc-rows">'
    +     row('Health', f.hp+' / '+f.maxHp+' <i style="opacity:.5">('+pct+'%)</i>', srcCell(f,'torso'))
    +     row('Power',  f.power, srcCell(f,'weapon'))
    +     row('Style',  f.kit.name, srcCell(f,'weapon'))
    +     row('Crit',   Math.round((f.crit||0)*100)+'%', roleSrc(f,'optic','no optics'))
    +     (f.resist > 0 ? row('Armour', Math.round(f.resist*100)+'% less',
                             roleSrc(f,'armour','')) : '')
    +     (f.assist > 0 ? row('Assist', Math.round(f.assist*100)+'% extra hit',
                             roleSrc(f,'beast','')) : '')
    +     (f.charge > 1 ? row('Charge', '&times;'+f.charge.toFixed(2)+' rate',
                             roleSrc(f,'energy','')) : '')
    +   '</div>'
    +   '<p class="fc-h">Every piece, and what it does</p>'
    +   '<div class="fc-rows">' + pieceRows(f) + '</div>'
    +   '<p class="fc-h">Right now</p>'
    +   '<div class="fc-rows">'
    +     row('Shield', f.shield || 0, '<span class="src">absorbs damage before health</span>')
    +     row('Charge', (f.surge||0)+' / 10', '<span class="src">erupts on the Crew\u2019s next '+SHARED[4].emoji+'</span>')
    +     row('Damage', f.dealt || 0, '<span class="src">dealt so far this battle</span>')
    +     (f.bleed > 0 ? row('Bleeding', f.bleed+' rounds', '<span class="src">loses health at the end of each</span>') : '')
    +   '</div>'
    +   '<p class="fc-note">Every piece does something, and what it does comes from'
    +     ' what it <b>is</b> rather than the slot it sits in — a blaster arm adds damage,'
    +     ' a protector arm adds armour. Reach is match size, not this Fighter: <b>3</b>'
    +     ' touches their front rank, <b>4</b> reaches mid, <b>5 or more</b> reaches the back.'
    +     ' The background matters for one Fighter only — the defender\u2019s front rank,'
    +     ' whose background is the arena.</p>'
    + '</div>';
  function row(k, v, src){
    return '<div class="fc-row"><span class="k">'+k+'</span><span class="v">'+v+'</span>'+src+'</div>';
  }
  $('fcard').hidden = false;
  var x = $('fcX'); if (x) x.addEventListener('click', closeCard);
}
function closeCard(){ var c = $('fcard'); if (c) c.hidden = true; }
var fcardEl = $('fcard');
if (fcardEl) {
  fcardEl.addEventListener('click', function(e){
    if (e.target === fcardEl) closeCard();       // the backdrop, not the card
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !fcardEl.hidden) closeCard();
  });
}
/* Delegated, because the tokens are rebuilt at the start of every battle. */
['myTeam','foeTeam'].forEach(function(id){
  var host = $(id); if (!host) return;
  host.addEventListener('click', function(e){
    var tok = e.target.closest('.tok'); if (!tok || !S) return;
    var uid = tok.getAttribute('data-id');
    var side = id === 'myTeam' ? 'mine' : 'foes', f = null;
    S[side].forEach(function(x){ if (x.uid === uid) f = x; });
    if (f) openCard(f, side === 'mine');
  });
});

/* ------------------------------------------------------------- entrance ----
   Three beats: the arena and what it does, the board falling in, the two Crews
   walking on. Under three seconds, skippable with a tap, and it never runs on
   resume -- see the note in the stylesheet.

   EVERY STEP IS OPTIONAL. The battle is already loaded and playable before this
   starts; the sequence only holds input for its own duration. If anything in
   here throws, finish() still runs and the board is live. A cinematic must
   never be able to cost somebody their turn. */
var cineTimers = [], cineDone = null;
/**
 * End the sequence.
 *
 * @param keepCover  leave the board hidden instead of revealing it. Used when
 *   the reason we are stopping is that ANOTHER battle is on its way: dealing a
 *   new practice battle, or going again from the end card, both stop the
 *   current sequence and then wait on a request. Revealing the old board for
 *   the length of that round trip is a flash of exactly the thing the entrance
 *   exists to build up to -- and it reads as the board appearing before the
 *   cinematic, which is what it looked like the first time this went wrong.
 */
function cineStop(keepCover){
  cineTimers.forEach(function(t){ clearTimeout(t); });
  cineTimers = [];
  var el = document.querySelector('.a-intro');
  if (el) el.remove();
  var ar = document.querySelector('.arena');
  if (ar) {
    ar.classList.remove('board', 'crew');
    if (keepCover) ar.classList.add('cine'); else ar.classList.remove('cine');
  }
  gridEl.querySelectorAll('.cell').forEach(function(c){ c.style.animationDelay = ''; });
  if (cineDone) { var d = cineDone; cineDone = null; d(); }
}
function cineAt(ms, fn){ cineTimers.push(setTimeout(fn, ms)); }

function playEntrance(done){
  var ar = document.querySelector('.arena');
  if (!ar) { done(); return; }
  cineDone = done;
  ar.classList.add('cine');          // already set by openBattle(); harmless twice
  busy = true;                       // no moves until the Crews are on

  var terr = S.terrainBg ? artUrl('background', S.terrainBg, 1000) : '';
  var intro = document.createElement('div');
  intro.className = 'a-intro go';
  if (terr) intro.style.backgroundImage = 'url("' + terr + '")';
  intro.innerHTML =
      '<div class="ti-k">The arena</div>'
    + '<div class="ti-n">' + (S.terrainName || 'Unknown Ground') + '</div>'
    /* The vibe first, then the rule. A line about the place earns the three
       seconds the panel holds for; the effect underneath is what you act on. */
    + (S.terrainLine ? '<div class="ti-l">' + S.terrainLine + '</div>' : '')
    + '<div class="ti-e">' + (S.terrainNote || '') + '</div>'
    + '<div class="ti-v">' + (S.mine[0] ? '<b>Your Crew</b>' : '') + ' versus <b>'
    +   (S.foes[0] ? S.foes[0].name.split(' ')[0] + "'s Crew" : 'the defenders') + '</b></div>'
    + '<div class="ti-s">tap to continue</div>';
  ar.appendChild(intro);

  /* BEAT TWO: the board falls in, a diagonal sweep rather than all at once.
     The delays are set BEFORE the grid is revealed, so `both` holds every cell
     at its opening frame until its own turn comes round. */
  function boardIn(){
    intro.classList.add('out');
    gridEl.querySelectorAll('.cell').forEach(function(c, i){
      var r = Math.floor(i / N), col = i % N;
      c.style.animationDelay = ((r + col) * 26) + 'ms';
    });
    ar.classList.add('board');
    sfx('land');
  }
  /* BEAT THREE: the Crews walk on. */
  function crewIn(){
    if (intro.parentNode) intro.remove();
    ar.classList.add('crew');
    document.querySelectorAll('.teamcol .tok').forEach(function(t, i){
      t.style.animationDelay = ((i % 3) * 90) + 'ms';
    });
    sfx('start');
  }

  /* A TAP SKIPS THE HOLD, NOT THE SEQUENCE.
     It used to call cineStop(), which threw the whole thing away and cut
     straight to a finished board -- so the only way past a panel you had
     already read was to lose the board falling in and the Crews walking on,
     which are the good bits. Now it drops the reading pause and plays the rest
     at full length. */
  var started = false;
  function goOn(){
    if (started) return;
    started = true;
    cineTimers.forEach(function(t){ clearTimeout(t); });
    cineTimers = [];
    boardIn();
    cineAt(BOARD_MS, crewIn);
    cineAt(BOARD_MS + CREW_MS, cineStop);
  }
  intro.addEventListener('click', goOn);

  cineAt(READ_MS, goOn);
}
/* Long enough to take the place in and read what it does to the fight. The
   text finishes arriving at about 1.1s, so this leaves nearly two seconds of
   actually looking at it -- and a tap moves it along for anyone who would
   rather not. */
var READ_MS = 3000, BOARD_MS = 620, CREW_MS = 700;

/**
 * @param res    the start/resume reply
 * @param fresh  true for a battle that begins now. A resumed battle is drawn
 *               immediately: it is not an entrance, and replaying the fanfare
 *               for a fight already in progress is the drop-reveal mistake.
 */
function openBattle(res, fresh){
  battleId = res.battle_id; S = res.state;
  setup.style.display = 'none';
  battle.classList.add('on');
  wrap.classList.add('playing');
  $('endcard').hidden = true;
  $('log').innerHTML = '';
  /* BEFORE anything is drawn, not after. playEntrance() used to add this at the
     end of openBattle(), which left a window -- one synchronous task today, but
     one await away from being a visible frame of the finished board -- in which
     the board existed on screen with nothing over it. Setting it first means
     the grid is never in a visible state at any point before its beat. */
  var arEl = document.querySelector('.arena');
  if (arEl && fresh && !res.state.over) arEl.classList.add('cine');
  $('aReroll').hidden = !practice;
  buildTeams(); paintBoard(); paintTeams(); paintChrome();
  (S.log||[]).forEach(function(l){ logLine('sys', l); });
  sfxInit();
  battle.scrollIntoView({behavior:'smooth', block:'start'});
  if (!fresh || S.over) { sfx('start'); return; }
  try { playEntrance(function(){ busy = false; }); }
  catch (e) {
    // the battle is already drawn and live; the sequence was the optional part
    busy = false; sfx('start');
    if (window.console) console.error('arena entrance', e);
  }
}
/**
 * Draw two fresh Crews and open the battle. Every route into practice goes
 * through here -- the Practice button on the setup screen, New battle in the
 * toolbar, and Practice again on the end card -- so all three behave the same
 * and there is one place where "start a practice battle" is defined.
 *
 * @param onFail  what to do if it does not come back; the setup screen can say
 *                so in its message line, the in-battle button cannot.
 */
function startPractice(onFail){
  if (busy) return;
  // cineStop() runs the pending done-callback, which clears busy -- so claim it
  // afterwards or this claims nothing. keepCover: the board stays hidden until
  // the battle we are fetching is ready to show it.
  cineStop(true);
  busy = true;
  $('endcard').hidden = true;
  post({do:'new'}, function(res){
    busy = false;
    if (!res || !res.ok) { cineStop(); onFail && onFail('Could not start practice.'); return; }
    practice = res.spec;
    battleId = 0;
    try { openBattle(res, true); }
    catch (e) {
      /*
       * CLOSE IT, do not just forget it. This used to null `practice`
       * and leave whatever had been drawn on screen -- a board that
       * looks playable, is not, and sends its moves to an endpoint that
       * redirects. Returning to setup is honest: the battle could not
       * be drawn, so there is no battle.
       */
      practice = null;
      if (window.console) console.error('arena practice', e);
      try { leaveBattle(); } catch (e2) {}
      onFail && onFail('Could not draw the battle — try again.');
    }
  }, function(){
    busy = false;
    cineStop();          // uncover whatever is there; nothing new is coming
    onFail && onFail('The Arena did not answer.');
  }, PRACTICE_URL);
}

var practiceBtn = $('aPractice');
if (practiceBtn) practiceBtn.addEventListener('click', function(){
  if (busy || battle.classList.contains('on')) return;
  practiceBtn.disabled = true;
  $('aMsg').textContent = 'Drawing two Crews…';
  startPractice(function(msg){ $('aMsg').textContent = msg; });
  // re-enabled either way: openBattle() hides the setup screen on success
  setTimeout(function(){ practiceBtn.disabled = false; }, 400);
});

/* NEW BATTLE, mid-practice. Practice Crews are dealt at random, so the first
   thing a player wants is often a different hand -- a matchup they fancy, or a
   kit they have never seen. Making them finish or leave to get one is the
   difference between a sandbox and a queue.
   Practice only: a ranked opponent is chosen, not dealt. */
var rerollBtn = $('aReroll');
if (rerollBtn) rerollBtn.addEventListener('click', function(){
  if (!practice || busy) return;
  rerollBtn.disabled = true;
  startPractice(function(){ logLine('sys','Could not draw a new battle.'); });
  setTimeout(function(){ rerollBtn.disabled = false; }, 400);
});

var startBtn = $('aStart');
function startFailed(msg){
  busy = false;
  paintPicker();                 // re-enables the button if a Crew is still picked
  startBtn.disabled = false;
  $('aMsg').textContent = msg;
  /*
   * THE BATTLE MAY EXIST ANYWAY. dhca_start() inserts the row and saves the
   * board before the reply is written, so a request that fails on the way back
   * -- or a reply this page cannot render -- still leaves a real battle on the
   * server. That is exactly the state where every further click was refused
   * with "you are in the middle of a battle" and only a refresh recovered,
   * because a refresh is the one thing that runs resume.
   *
   * So run resume here instead of making the player discover that.
   */
  post({do:'resume'}, function(res){
    if (!res || !res.ok) return;
    $('aMsg').textContent = '';
    // A battle nobody has moved in yet is a battle that is only just starting,
    // whatever went wrong on the way here, so it still gets its entrance.
    openBattle(res, !res.state.moves);
    logLine('sys','Recovered a battle that had already started.');
  }, function(){});
}
if (startBtn) startBtn.addEventListener('click', function(){
  if (busy) return;
  if (battle.classList.contains('on')) return;   // already in one
  busy = true; startBtn.disabled = true; practice = null; bossRun = 0;
  $('aMsg').textContent = 'Entering…';
  post({do:'start', defender:rival, fighters:picked}, function(res){
    if (!res || !res.ok) { startFailed((res && res.message) || 'Could not start.'); return; }
    busy = false; startBtn.disabled = false;
    $('aMsg').textContent = '';
    /* A throw in here used to vanish into the promise chain, leaving the setup
       screen up, the battle live on the server and no explanation anywhere. */
    try { openBattle(res, true); }
    catch (e) { startFailed('Could not draw the battle. Reopening it…'); }
  }, function(err){
    startFailed('The Arena did not answer. Checking whether the battle started…');
  });
});
/*
 * SEND A CREW AT THE BOSSES.
 *
 * The same picker, the same three Fighters, the same board. Only the
 * endpoint and the id differ, which is the whole of what a mode is here.
 */
var bossBtn = $('aBossGo');
if (bossBtn) bossBtn.addEventListener('click', function(){
  if (busy) return;
  if (battle.classList.contains('on')) return;
  if (picked.length !== 3) { $('aMsg').textContent = 'Pick three Fighters first.'; return; }
  busy = true; bossBtn.disabled = true; practice = null; live = null;
  $('aMsg').textContent = 'Sending…';
  post({do:'start', fighters:picked}, function(res){
    busy = false; bossBtn.disabled = false;
    if (!res || !res.ok) { $('aMsg').textContent = (res && res.message) || 'Could not send.'; return; }
    $('aMsg').textContent = '';
    bossRun = res.run_id || 0;
    bossWave(res);
    try { openBattle({battle_id:0, state:res.state}, true); }
    catch (e) { $('aMsg').textContent = 'Could not draw the run. Reload and resume.'; }
  }, function(){
    busy = false; bossBtn.disabled = false;
    $('aMsg').textContent = 'The Bosses did not answer. Reload to see whether the run started.';
  }, BOSS_URL);
});

/* Leaving does not abandon anything: a ranked battle is on the server and
   resume picks it up exactly where it was, and a practice battle was never
   worth keeping. A boss run is on the server too, and dhcb_sweep_stale()
   closes it if it is genuinely abandoned -- the damage it already landed
   stays landed, because it genuinely landed. */
function leaveBattle(){
  /* Walking out of a live match ends it for both players. There is nothing to
     forfeit and no record to write -- with no stake, "somebody left" is just
     the end of the game, and leaving the other player polling an abandoned
     board would be worse than saying so. */
  if (live) {
    post({do:'quit', live:live.id}, function(){}, function(){}, LIVE_URL);
    liveStopPoll(); liveTickStop(); live = null;
    $('lclock').hidden = true;
  }
  cineStop(); practice = null; bossRun = 0; bossWave(null);
  $('endcard').hidden = true;
  // hidden by #arenaBattle going away regardless, but leaving it set would be a
  // stale flag waiting to be believed
  $('aReroll').hidden = true;
  battle.classList.remove('on'); wrap.classList.remove('playing'); setup.style.display = '';
  setup.scrollIntoView({behavior:'smooth', block:'start'});
}
$('ecLeave').addEventListener('click', leaveBattle);
$('aLeave').addEventListener('click', leaveBattle);
$('ecAgain').addEventListener('click', function(){
  // Practice costs nothing, so going again should cost nothing either -- not a
  // page load and not a trip through the Crew picker.
  if (practice) startPractice(function(){ location.reload(); });
  else location.reload();
});

$('howto').addEventListener('click', function(){ wrap.classList.toggle('showintro'); });

/*
 * THE GUIDE. Rendered server-side from the Skull Paper, so there is
 * nothing to fetch and nothing to build -- this only shows and hides it.
 *
 * GUARDED ON THE ELEMENTS EXISTING, because the panel is not printed at
 * all when doc_sections() comes back empty (a missing or unreadable
 * markdown file). A listener bound to null throws, and a throw here takes
 * every handler registered after it with it -- which is exactly how both
 * Rivalries buttons shipped dead.
 */
(function(){
  var veil = document.getElementById('ag-veil');
  var btn  = document.getElementById('aGuide');
  if (!veil || !btn) { if (btn) btn.hidden = true; return; }
  var body = document.getElementById('ag-body');
  var shut = document.getElementById('ag-close');
  function open(){ veil.hidden = false; veil.classList.add('on'); if (body) body.scrollTop = 0;
                   if (shut) shut.focus(); }
  function close(){ veil.classList.remove('on'); veil.hidden = true; btn.focus(); }
  btn.addEventListener('click', open);
  if (shut) shut.addEventListener('click', close);
  /* The backdrop closes it; the panel itself must not. */
  veil.addEventListener('click', function(e){ if (e.target === veil) close(); });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !veil.hidden) close();
  });
})();

var muteBtn = $('mute');
function paintMute(){
  muteBtn.textContent = sfxOn ? '🔊' : '🔇';
  muteBtn.title = sfxOn ? 'Mute sound' : 'Unmute sound';
}
muteBtn.addEventListener('click', function(){
  sfxOn = !sfxOn;
  try { localStorage.setItem('dhcarena_sfx', sfxOn ? '1' : '0'); } catch (e) {}
  paintMute(); if (sfxOn) { sfxInit(); sfx('pick'); }
});
paintMute();
applyView();
paintPicker();

/* ============================================================================
   LIVE MATCHES — human against human (§8d)

   The only mode where the other side is a person. Everything about it is a
   sport: no ladder, no traits, no Fighter record, no bench, no allowance. The
   server enforces that by isolation, not by a rule -- see dhcarena-live.php.

   The client does no more here than it does anywhere else. The server flips the
   board's perspective before sending it, so 'mine' is always whoever is
   looking and every existing guard -- the drag's `S.turn !== 'mine'`, the turn
   flag, the enemy mirroring -- works untouched.
   ============================================================================ */

/* A username reaches the lobby box through innerHTML, and a username is the
   one string on this page somebody else chose. */
function esc(t){
  return String(t == null ? '' : t).replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
/** Take what a live reply says about whose turn it is and how long is left. */
function liveTook(res){
  if (!live || !res) return;
  if (typeof res.seq === 'number')   live.seq = res.seq;
  if (typeof res.yours === 'boolean') live.yours = res.yours;
  if (typeof res.clock === 'number') liveClock = res.clock;
  paintClock();
  // Poll only while waiting on them; a client that polls on its own turn is
  // asking the server to repeat what it already knows.
  if (live.yours || (S && S.over)) liveStopPoll(); else liveStartPoll();
}

function paintClock(){
  var el = $('lclock'); if (!el) return;
  if (!live || !S || S.over) { el.hidden = true; return; }
  el.hidden = false;
  var n = Math.max(0, Math.round(liveClock));
  el.textContent = (live.yours ? 'you ' : 'them ') + n + 's';
  el.className = 'lclock' + (n <= 15 ? ' low' : '');
}

/* One second of the displayed clock. Cosmetic only -- the server decides when a
   turn has actually run out, and every reply resets this from its answer. */
function liveTickStart(){
  if (liveTick) return;
  liveTick = setInterval(function(){
    if (!live) { liveTickStop(); return; }
    if (liveClock > 0) liveClock--;
    paintClock();
  }, 1000);
}
function liveTickStop(){ if (liveTick) { clearInterval(liveTick); liveTick = null; } }

function liveStartPoll(){
  if (livePoll || !live) return;
  livePoll = setInterval(livePollOnce, 1500);
}
function liveStopPoll(){ if (livePoll) { clearInterval(livePoll); livePoll = null; } }

function livePollOnce(){
  if (!live || busy) return;
  post({do:'poll', live:live.id, since:live.seq}, function(res){
    if (!live) return;
    if (!res || !res.ok) return;                 // a blip; the next tick retries
    if (res.status === 3) { liveClosed('The match was closed.'); return; }
    if (typeof res.seq !== 'number' || res.seq <= live.seq) { liveTook(res); return; }

    /* They moved. Same path a ranked defending reply takes -- the timeline is
       animated and the server's state is taken at the end of it. A resync
       (this tab was asleep longer than the server keeps timelines) skips the
       animation rather than inventing one. */
    var st = res.state;
    live.seq = res.seq;
    busy = true;
    var finish = function(){
      busy = false;
      liveTook(res);
      if (res.over) showEnd(res);
    };
    if (res.resync || !st.fx || !st.fx.length) {
      S = st; paintBoard(); paintTeams(); paintChrome();
      if (res.resync) logLine('sys','Caught up.');
      finish();
    } else {
      try { playTimeline(st.fx, st, finish); }
      catch (e) {
        S = st; paintBoard(); paintTeams(); paintChrome();
        if (window.console) console.error('arena live timeline', e);
        finish();
      }
    }
  /* LIVE_URL. Without it post() falls back to the RANKED endpoint, which has
     no 'poll' action -- it answers ok:false, this handler returns quietly, and
     the waiting player sits on "their move" for ever while the other one has
     already gone. Every live call has to name the endpoint; there is no
     default that is right. */
  }, function(){ /* a dropped poll is not an error; the next one covers it */ },
     LIVE_URL);
}

function liveClosed(msg){
  liveStopPoll(); liveTickStop();
  live = null;
  $('lclock').hidden = true;
  logLine('sys', msg);
}

/** Open a live battle from a payload, for whichever side got there first. */
function liveOpen(res){
  live = {id:res.live, seat:res.seat, seq:res.seq || 0, yours:!!res.yours};
  liveClock = typeof res.clock === 'number' ? res.clock : 0;
  practice = null;
  openBattle({battle_id:0, state:res.state}, true);
  liveTickStart();
  liveTook(res);
  logLine('sys','Live match against ' + (res.them || 'a rival') + '. No stakes — just the win.');
}

/* ---------- the lobby, on the setup screen --------------------------------- */

function liveLobbyPaint(res){
  var box = $('aLive'); if (!box) return;
  if (!res || !res.live) { box.hidden = true; box.innerHTML = ''; return; }
  box.hidden = false;
  if (res.status === 0 && res.seat === 'guest') {
    box.innerHTML = '<b>' + esc(res.them) + '</b> has challenged you to a live match.'
      + '<span class="note">Pick ' + CREW_SIZE + ' Fighters above, then accept. Nothing is at '
      + 'stake — no traits, no ladder, no recovery. Fighters still recovering can play.</span>'
      + '<div class="row"><button class="btn go" id="aAccept">Accept</button>'
      + '<button class="btn" id="aDecline">Decline</button>'
      + '<span class="a-sub" style="margin:0" id="aLiveMsg"></span></div>';
    $('aAccept').addEventListener('click', function(){ liveAccept(res.live); });
    $('aDecline').addEventListener('click', function(){
      post({do:'decline', live:res.live}, function(){ liveLobbyPaint(null); }, function(){}, LIVE_URL);
    });
  } else if (res.status === 0) {
    box.innerHTML = 'Waiting for <b>' + esc(res.them) + '</b> to accept…'
      + '<span class="note">They need to open the Arena and pick a Crew. The challenge '
      + 'stands for a few minutes.</span>'
      + '<div class="row"><button class="btn" id="aCancel">Cancel</button></div>';
    $('aCancel').addEventListener('click', function(){
      post({do:'decline', live:res.live}, function(){ liveLobbyPaint(null); }, function(){}, LIVE_URL);
    });
  } else if (res.status === 1) {
    box.innerHTML = 'Live match against <b>' + esc(res.them) + '</b> is running.';
  }
}

function liveLobbyPoll(){
  /* NOT `battle.classList.contains('on')`. That was any battle at all, which
     included the ranked one resume() opens on page load -- so a host who had an
     unfinished battle somewhere never learned their challenge was accepted, and
     sat looking at a different board while the other player waited. The poll
     keeps asking; what changes is whether the answer takes over the screen. */
  if (live || busy) return;
  post({do:'lobby'}, function(res){
    if (!res || !res.ok) return;
    if (res.live && res.status === 1 && res.state) {
      /* IT TAKES THE SCREEN, whatever is on it. The player asked for this match
         and the other one is sitting in front of it with a clock running, so
         deferring is the wrong answer -- and there is nowhere to tell them it
         is waiting, because the setup screen they would read it on is the one
         thing that is not showing. Nothing is lost either way: a ranked battle
         is saved server-side after every move and resume picks it up, and
         practice was never worth keeping. */
      var interrupted = !practice && battle.classList.contains('on');
      liveOpen(res);
      if (interrupted) logLine('sys','Your other battle is saved — it will be waiting.');
      return;
    }
    liveLobbyPaint(res.live ? res : null);
  }, function(){}, LIVE_URL);
}

function liveAccept(id){
  var msg = $('aLiveMsg');
  if (picked.length !== CREW_SIZE) {
    if (msg) msg.textContent = 'Pick ' + CREW_SIZE + ' Fighters first.';
    return;
  }
  if (msg) msg.textContent = 'Dealing…';
  post({do:'accept', live:id, fighters:picked}, function(res){
    if (!res || !res.ok) { if (msg) msg.textContent = (res && res.message) || 'Could not accept.'; return; }
    liveOpen(res);
  }, function(){ if (msg) msg.textContent = 'The Arena did not answer.'; }, LIVE_URL);
}

var chalBtn = $('aChallenge');
if (chalBtn) chalBtn.addEventListener('click', function(){
  if (busy || live) return;
  if (picked.length !== CREW_SIZE || !rival) {
    $('aMsg').textContent = 'Pick ' + CREW_SIZE + ' Fighters and a rival first.';
    return;
  }
  chalBtn.disabled = true;
  $('aMsg').textContent = 'Challenging…';
  post({do:'challenge', guest:rival, fighters:picked}, function(res){
    chalBtn.disabled = false;
    if (!res || !res.ok) { $('aMsg').textContent = (res && res.message) || 'Could not challenge.'; return; }
    $('aMsg').textContent = '';
    liveLobbyPaint(res);
  }, function(){
    chalBtn.disabled = false;
    $('aMsg').textContent = 'The Arena did not answer.';
  }, LIVE_URL);
});

if (GUEST) {
  /* STRAIGHT INTO A BATTLE. A visitor arriving from a shared link should be
     playing, not reading a setup screen about a Crew they cannot pick -- so the
     page opens the way the Practice button would, cinematic and all. The setup
     screen is still behind it, and Leave goes back to it.
     No resume: that endpoint is behind the login gate, and a guest has nothing
     to resume by definition.

     NOT ON THE BOSSES PAGE. A visitor there followed a link about three bosses
     standing at a particular health, and throwing them into a practice match
     against two random Crews answers a question they did not ask. The boss
     panel and the week's board are what that page is; the Arena is one click
     away and opens playing. */
  if (!BOSS_MODE) {
    $('aMsg').textContent = 'Drawing two Crews…';
    startPractice(function(msg){ $('aMsg').textContent = msg; });
  }
} else if (BOSS_MODE) {
/*
 * THE BOSS PAGE RESUMES A BOSS RUN, which is a different endpoint and a
 * different flag. Pointing the Arena's resume at this page would have opened
 * a ranked battle on the Bosses screen -- same board, same client, and the
 * next move posted to the wrong game.
 *
 * NO LIVE LOBBY POLL EITHER. A live challenge is an Arena thing and this page
 * has no lobby to paint it into, so the poll would have been a request every
 * four seconds for a panel that is not here.
 */
post({do:'resume'}, function(res){
  if (!res || !res.ok) return;
  if (battle.classList.contains('on') || practice || live) return;
  bossRun = res.run_id || 0;
  bossWave(res);
  try {
    openBattle({battle_id:0, state:res.state}, !res.state.moves);
    logLine('sys', res.state.moves ? 'Picked up where you left off.'
                                   : 'A run was waiting for you.');
  } catch (e) {
    bossRun = 0;
    $('aMsg').textContent = 'A run is in progress but could not be drawn. Try reloading.';
    if (window.console) console.error('bosses resume', e);
  }
}, function(){ /* nothing in progress is the normal case; stay quiet */ }, BOSS_URL);
} else {
/* Is somebody waiting to fight you? Cheap, and it is the only way a challenge
   issued in Discord thirty seconds ago reaches the page you just opened. */
liveLobbyPoll();
setInterval(liveLobbyPoll, 4000);

/* A battle left open in another tab, or on a phone that went to sleep, is still
   the one you owe a move to — the server will refuse a new one until it ends. */
post({do:'resume'}, function(res){
  if (!res || !res.ok) return;
  // The player can press Enter before this lands; whoever opened a battle first
  // owns the screen.
  /* ...or a LIVE match. The lobby poll and this fire together on load, and
     whichever answers last used to win -- so a resumed ranked battle could
     land on top of a live one that had already opened. */
  if (battle.classList.contains('on') || practice || live) return;
  try {
    openBattle(res, !res.state.moves);
    logLine('sys', res.state.moves ? 'Picked up where you left off.'
                                   : 'A battle was waiting for you.');
  } catch (e) {
    $('aMsg').textContent = 'A battle is in progress but could not be drawn. Try reloading.';
    if (window.console) console.error('arena resume', e);
  }
}, function(){ /* nothing in progress is the normal case; stay quiet */ });
}
})();
</script>


<?php
/**
 * One ladder table, rendered twice -- this month and all time.
 *
 * It is a function rather than two copies of the markup for the reason this
 * codebase keeps relearning: a duplicated list drifts. The two boards are the
 * same five columns of the same table, and a column added to one and not the
 * other is a bug nothing would catch, because both would still render.
 *
 * $empty is passed in because the two windows are empty for different reasons
 * and the player should be told which: nobody has fought THIS MONTH is a
 * standing invitation, nobody has fought AT ALL is a different sentence.
 */
function dhca_ladder_html($rows, $user_id, $guest, $empty) {
	if (!$rows) {
		return '<p class="a-sub" style="margin:0">' . htmlspecialchars($empty) . '</p>';
	}
	$h = '<table class="a-lad"><thead><tr><th>#</th><th>'
	   . ($guest ? 'Player' : 'Staker')
	   . '</th><th>W</th><th>L</th><th>Best chain</th></tr></thead><tbody>';
	foreach ($rows as $i => $l) {
		$me = ((int)$l['user_id'] === (int)$user_id) ? ' class="me"' : '';
		$h .= '<tr' . $me . '>'
		    . '<td class="r">' . ($i + 1) . '</td>'
		    . '<td>' . htmlspecialchars($l['username']) . '</td>'
		    . '<td class="r">' . (int)$l['wins'] . '</td>'
		    . '<td class="r">' . (int)$l['losses'] . '</td>'
		    . '<td class="r">x' . (int)$l['best_chain'] . '</td>'
		    . '</tr>';
	}
	return $h . '</tbody></table>';
}

/*
 * dhc-dropmodal.php is already pulled in by header.php platform-wide, so a win
 * that drops a trait reveals it here without this page doing anything.
 */
$conn->close();
?>
</html>

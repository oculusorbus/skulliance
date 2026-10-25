<?php
/**
 * partner.php -- the invitation. PUBLIC.
 *
 * This replaces explaining the platform down a DM and pasting screenshots.
 * Send an artist or a project one link and it makes the whole case: what
 * Skulliance sets up for them, what it costs (nothing), what their holders
 * get, and the short list of things we actually need back.
 *
 * ?for=Name PERSONALISES IT, including the link preview. An invitation that
 * opens with the project's own name is read; a generic landing page is
 * skimmed. The name is echoed in the hero, the closing ask, the <title> and
 * the og:title, so the card that unfurls in a DM already says who it is for.
 * Everything still reads correctly with no parameter at all.
 *
 * PUBLIC LIKE skullpaper.php, and for the same reason: the people this is
 * written for do not have accounts yet. It must not include skulliance.php
 * (which redirects anonymous visitors to error.php) or the heavy verify.php.
 * db.php gives a session only if a cookie already exists, plus $conn.
 *
 * THE NUMBERS ON THIS PAGE ARE READ FROM THE CODE THAT ENFORCES THEM, not
 * typed in: the currency rule comes from admin_currency_problem(), the
 * Monstrocity art list from monstrocity_theme_subdirs() and the default
 * character roster, the chains from admin-collections.php's $CHAINS. A
 * marketing page that promises something the platform does not do is worse
 * than no page, and these are exactly the values that drift.
 */
include_once 'db.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/monstrocity-themes-lib.php';

/* Same session restore as skullpaper.php: light up the member navbar if a
   cookie is already there, otherwise render the public one further down. */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = array_merge((array)$_SESSION, $cookie); } // merge, not replace
}
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	$name = $_SESSION['userData']['name'] ?? null;
	$pt_did = $_SESSION['userData']['discord_id'] ?? null;
	$pt_av  = $_SESSION['userData']['avatar'] ?? null;
	if ($name !== null && $pt_did && $pt_av) $avatar_url = avatar_url($pt_did, $pt_av);
}

/*
 * WHO IT IS FOR.
 *
 * Reflected into the page and into the meta, so it is sanitised hard rather
 * than merely escaped on output: letters, digits, spaces and the handful of
 * punctuation marks that appear in real project names. Anything else is
 * dropped, and the result is capped -- a 400-character "name" would wreck
 * the hero and the og:title both.
 */
$pt_for = isset($_GET['for']) ? trim((string)$_GET['for']) : '';
$pt_for = preg_replace('/[^A-Za-z0-9 \'&.,!_-]/u', '', $pt_for);
$pt_for = trim(preg_replace('/\s+/', ' ', $pt_for));
if (mb_strlen($pt_for) > 48) $pt_for = rtrim(mb_substr($pt_for, 0, 48));

$pt_who   = $pt_for !== '' ? $pt_for : 'your project';
/*
 * THE TITLE HAS TO SAY WHAT THE PAGE SAYS. The hero and the description were
 * rewritten to "you have been invited" and this was left reading "Bring X to
 * Skulliance" -- so the Discord embed led with the old pitch over the new
 * one. The harness only checked that the title VARIED with ?for=, which it
 * did, which is why nothing caught it.
 */
$pt_title = $pt_for !== ''
	? $pt_for . ', you have been invited to Skulliance'
	: 'You have been invited to Skulliance';
/* header.php renders <title> from this or falls back to a bare "Skulliance",
   which is what the tab and some crawlers were showing. */
$page_title_override = $pt_title;
$pt_desc  = ($pt_for !== '' ? $pt_for . ', you have been invited. ' : 'You have been invited. ')
          . 'Free staking, missions, realms and a dozen games for your collectors. We build all '
          . 'of it: your chain, your collections, your points, your realm, your Monstrocity '
          . 'roster. No application, no fee.';
$pt_url   = 'https://www.skulliance.io/staking/partner.php'
          . ($pt_for !== '' ? '?for=' . rawurlencode($pt_for) : '');

/*
 * THE MARQUEE IS LIVE, not a hardcoded list.
 *
 * It is the single most persuasive thing on the page -- thirty-odd projects
 * already here, each with its own currency and icon -- and a hardcoded copy
 * would start lying the first time a project joined or left. Only projects
 * whose icon actually exists on disk are shown, because a row of fallback
 * skulls makes the opposite point to the one intended.
 */
$pt_icons = array();        // only those with an icon on disk -- the marquee
$pt_names = array();        // all of them -- the roster
$pt_curs  = array();        // every ticker in use -- the name collision check
$pt_res = $conn->query("SELECT name, currency FROM projects
                         WHERE currency IS NOT NULL AND currency <> ''
                         ORDER BY name ASC");
if ($pt_res) {
	while ($row = $pt_res->fetch_assoc()) {
		$pt_names[] = $row['name'];
		/* EVERY currency, not just the ones with an icon on disk: this list
		   is the answer to "is my name free", and a name missing from it
		   because its project never uploaded an icon would be the one
		   answer that matters getting it wrong. */
		$pt_curs[strtoupper($row['currency'])] = true;
		$file = admin_currency_icon($row['currency']);         // icons/{currency}.png
		if (!is_file(__DIR__ . '/' . $file)) continue;
		$pt_icons[] = array('name' => $row['name'], 'cur' => strtoupper($row['currency']), 'img' => $file);
	}
}

/* The artist flyers the homepage shows, from the one file both read. */
$pt_flyers   = require __DIR__ . '/lib/partner-flyers.php';
$pt_founders = require __DIR__ . '/lib/founding-artists.php';
/* Where a DHC screenshot lives, shared with dhcgame.php. */
require_once __DIR__ . '/lib/dhc-shots.php';

/* The Monstrocity ask, counted rather than remembered. */
$pt_mon_chars = count(require __DIR__ . '/monstrocity-default-characters.php');
$pt_mon_sets  = monstrocity_theme_subdirs();                    // base, leader, battle-damaged
$pt_mon_total = $pt_mon_chars * count($pt_mon_sets);

/* The chains, from the file that validates them. */
$pt_chains = array('Cardano', 'XRPL', 'Solana');

$pt_e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

/*
 * THE LINK PREVIEW, because this page is sent in a DM and the unfurled card
 * is the first thing an invitee sees.
 *
 * images/og.jpg: the six founding artists' work with the Skulliance
 * wordmark on it. 1.35MB, which unfurls fine -- what did not was pointing
 * og:image at skulliancebackground.png, a 4.6MB PNG the embed proxy skips.
 *
 * THE NAME IS IN THE TITLE, NOT THE IMAGE. There is no TTF in this repo
 * (see dhc-card.php, which went text-free for the same reason) and both X
 * and Discord render og:title at full size beside the card, so the
 * personalisation lands without touching the art.
 */
$pt_card   = 'https://www.skulliance.io/staking/images/og.jpg';
$pt_card_w = 1416; $pt_card_h = 1113;
$extra_head = '
  <meta name="description" content="' . $pt_e($pt_desc) . '">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="' . $pt_e($pt_url) . '">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="' . $pt_e($pt_url) . '">
  <meta property="og:title" content="' . $pt_e($pt_title) . '">
  <meta property="og:description" content="' . $pt_e($pt_desc) . '">
  <meta property="og:image" content="' . $pt_e($pt_card) . '">
  <meta property="og:image:width" content="' . (int)$pt_card_w . '">
  <meta property="og:image:height" content="' . (int)$pt_card_h . '">
  <meta property="og:image:alt" content="Artwork from the projects that stake on Skulliance">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@skulliance">
  <meta name="twitter:title" content="' . $pt_e($pt_title) . '">
  <meta name="twitter:description" content="' . $pt_e($pt_desc) . '">
  <meta name="twitter:image" content="' . $pt_e($pt_card) . '">
';

include 'header.php';

/* header.php's navbar lives inside `if(isset($name))`, so a stranger -- which
   is everyone this page is written for -- would otherwise get no navigation
   at all. Same fix the Skull Paper carries. */
$pt_public_nav = !isset($name);
if ($pt_public_nav) include __DIR__ . '/site-header.php';
?>
<style>
#pt{--ink:#07111d;--panel:#0a1929;--panel2:#0d1e2e;--line:#1b3346;--dim:#7a9eb0;
    --ochre:#00c8a0;--bone:#e8eaed;--gold:#f5a623;
    color:var(--bone);max-width:1280px;margin:0 auto;padding:0 22px 70px}
/* TEXT RUNS THE FULL WIDTH, deliberately and against the usual advice. The
   measure was capped in ch, which left every paragraph hugging the left of a
   1280px page with half of it empty; laying the prose out in two columns
   fixed the emptiness and made the page read as two unrelated texts. The
   call here is the page owner's: full width throughout, nothing capped. */
#pt h1,#pt h2,#pt h3{margin:0;line-height:1.2;text-wrap:balance}
#pt p{margin:0}

/* ---- hero ---- */
#pt .pt-hero{padding:54px 0 40px;border-bottom:1px solid var(--line)}
#pt .pt-eyebrow{font-size:10px;letter-spacing:.24em;text-transform:uppercase;color:var(--ochre);
  margin:0 0 14px}
#pt .pt-hero h1{font-size:clamp(30px,5.4vw,52px);font-weight:700;letter-spacing:-.01em}
#pt .pt-hero h1 em{font-style:normal;color:var(--ochre)}
#pt .pt-lede{margin:18px 0 0;font-size:clamp(15px,2vw,18px);line-height:1.65;color:#c7d3dc}
/* A STATEMENT, NOT A CONTROL. Boxed in a bordered rounded rectangle it read
   as a button, and clicking it did nothing -- which is worse than not having
   it. Same words, no box: a rule and a coloured line. */
#pt .pt-free{display:flex;align-items:center;gap:12px;margin:26px 0 0;font-size:12px;
  letter-spacing:.14em;text-transform:uppercase;color:var(--ochre)}
#pt .pt-free::after{content:"";flex:1 1 auto;height:1px;background:var(--line);max-width:260px}

/* ---- sections ---- */
#pt .pt-sect{padding:40px 0;border-bottom:1px solid var(--line)}
#pt .pt-rule{border-bottom:1px solid var(--line);margin:24px 0 0}
#pt .pt-kicker{font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--dim);
  margin:0 0 10px}
#pt .pt-sect h2{font-size:clamp(21px,3vw,28px);font-weight:700}
#pt .pt-sect > p{margin:14px 0 0;color:#c7d3dc;line-height:1.7}

/* ---- the build list: what WE do ---- */
#pt .pt-build{display:grid;grid-template-columns:repeat(auto-fit,minmax(248px,1fr));gap:12px;
  margin:26px 0 0;list-style:none;padding:0}
#pt .pt-build li{background:var(--panel2);border:1px solid var(--line);border-radius:3px;
  padding:16px 16px 17px}
#pt .pt-build b{display:block;font-size:13px;margin:0 0 6px;color:var(--bone)}
#pt .pt-build span{font-size:12.5px;line-height:1.6;color:var(--dim)}

/* ---- prose that has to fill a wide page ----
   WIDENING THE CONTAINER WAS ONLY HALF THE JOB. Running text is capped in
   ch so it stays readable, which at 1280px left every paragraph hugging the
   left edge with half the page empty beside it. Sections that end in a grid
   fill the width themselves; the ones that are ONLY prose need the text laid
   out in two columns, so the measure stays short and the block still spans
   the page. Collapses to one column before the columns get too narrow. */

/* ---- the six founders ---- */
#pt .pt-founders{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin:26px 0 0;
  padding:0;list-style:none}
#pt .pt-founders a{display:block;text-decoration:none;text-align:center}
#pt .pt-founders img{width:100%;height:auto;aspect-ratio:1;object-fit:cover;border-radius:6px;
  border:1px solid var(--line);display:block}
#pt .pt-founders span{display:block;margin:8px 0 0;font-size:11px;color:var(--dim)}
#pt .pt-founders a:hover span{color:var(--ochre)}
@media (max-width:760px){#pt .pt-founders{grid-template-columns:repeat(3,1fr)}}

/* ---- the artist flyers ----
   The homepage's hp-strip pattern, under this page's own prefix: full-bleed
   rows (the calc shift breaks out of the wrapper), a duplicated track
   sliding -50% for a seamless loop, opposite directions per row, pause on
   hover, edge fade, and no animation at all for reduced motion.

   The DATA is shared (lib/partner-flyers.php) and the presentation is not,
   deliberately: the list is the thing that drifts when an artist joins,
   while these thirty lines belong to a page with its own tokens and its own
   type scale. Sharing the markup too would mean parameterising a prefix
   through both pages for no benefit either of them can see. */
/* BOUNDED BY THE COLUMN, NOT THE VIEWPORT. This was the homepage's
   full-bleed treatment -- width:100vw with a calc shift -- and 100vw
   INCLUDES THE VERTICAL SCROLLBAR, so on a 2560px window with a 15px bar
   the strip measured 2560 against a 2545 content area and hung 7px off each
   side, which turned on a horizontal scrollbar for the whole page. The
   homepage gets away with it because it sets body{overflow-x:hidden}; doing
   that here would create a scroll container and break the sticky nav. At
   1280px the strip is wide enough that bleeding to the edge was never worth
   a page-wide scrollbar. */
#pt .pt-strip{overflow:hidden;padding:16px 0;
  -webkit-mask-image:linear-gradient(to right,transparent 0,#000 6%,#000 94%,transparent 100%);
          mask-image:linear-gradient(to right,transparent 0,#000 6%,#000 94%,transparent 100%)}
#pt .pt-strip + .pt-strip{padding-top:0}
#pt .pt-strip-track{display:flex;align-items:flex-start;gap:22px;width:max-content;
  animation:pt-strip-scroll 60s linear infinite;will-change:transform}
#pt .pt-strip-track.pt-reverse{animation-direction:reverse;animation-duration:66s}
#pt .pt-strip-track:hover,#pt .pt-strip-track:focus-within{animation-play-state:paused}
@keyframes pt-strip-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
#pt .pt-strip-card{flex:0 0 auto;text-align:center;text-decoration:none}
#pt .pt-strip-card img{height:140px;width:auto;max-width:240px;object-fit:contain;
  border-radius:8px;border:1px solid var(--line);display:block}
#pt .pt-strip-card span{display:block;margin:8px 0 0;font-size:11px;color:var(--dim)}
#pt .pt-strip-card:hover span{color:var(--ochre)}

/* ---- who is already here ----
   A DENSE WRAP, NOT CARDS. Thirty-five cards reads as thirty-five things to
   evaluate one at a time; the point of this section is the crowd, so the
   names run together and the count does the talking. */
#pt .pt-roster{display:flex;flex-wrap:wrap;gap:8px;margin:22px 0 0;padding:0;list-style:none}
#pt .pt-roster li{font-size:12.5px;color:#c7d3dc;background:var(--panel2);
  border:1px solid var(--line);border-radius:999px;padding:6px 13px}

/* ---- the live icon marquee ---- */
#pt .pt-rail{margin:26px 0 0;overflow:hidden;position:relative;
  border-block:1px solid var(--line);padding:18px 0;
  -webkit-mask:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent);
          mask:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent)}
#pt .pt-track{display:flex;gap:30px;width:max-content;animation:pt-slide 48s linear infinite}
#pt .pt-rail:hover .pt-track,#pt .pt-rail:focus-within .pt-track{animation-play-state:paused}
@keyframes pt-slide{from{transform:translateX(0)}to{transform:translateX(-50%)}}
#pt .pt-chip{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:7px;width:96px}
#pt .pt-chip img{width:40px;height:40px;object-fit:contain;opacity:.92}
#pt .pt-chip b{font-size:10px;letter-spacing:.08em;color:var(--ochre)}
#pt .pt-chip span{font-size:10px;color:var(--dim);text-align:center;line-height:1.35}
/* The second copy only exists to make the loop seamless, so it is hidden
   from anyone listening rather than read out twice. */
#pt .pt-track[aria-hidden="true"]{}

/* ---- screenshots and key art ----
   One figure component, used by five sections. Everything is lazy and
   async: these are 200KB-1.2MB PNGs on the server and none of them is
   above the fold, so none of them should be in the way of the hero. */
#pt .pt-shots{display:grid;gap:12px;margin:24px 0 0}
#pt .pt-shots.two{grid-template-columns:1fr 1fr}
#pt .pt-shots.three{grid-template-columns:repeat(3,1fr)}
#pt .pt-shots.four{grid-template-columns:repeat(4,1fr)}
#pt figure{margin:0}
#pt figure img{display:block;width:100%;height:auto;border:1px solid var(--line);
  border-radius:4px;background:var(--panel2)}
#pt figure figcaption{margin:7px 0 0;font-size:11px;color:var(--dim);letter-spacing:.04em}
/* Key art is portrait-ish and wants cropping to a common shape, or four
   different aspect ratios make the row look broken. */
#pt .pt-shots.four figure img{aspect-ratio:3/4;object-fit:cover}
@media (max-width:760px){
  #pt .pt-shots.two,#pt .pt-shots.three{grid-template-columns:1fr}
  #pt .pt-shots.four{grid-template-columns:1fr 1fr}
}

/* ---- a plain statement, for the thing people are worried about ----
   Not a spec box and not a card: those read as detail you can skip. This is
   the paragraph that decides whether an artist keeps reading, so it gets the
   page's quietest treatment and its largest body text. */
#pt .pt-plain{margin:24px 0 0;padding:20px 22px;background:var(--panel);
  border:1px solid var(--line);border-radius:3px}
#pt .pt-plain h3{font-size:15px;margin:0 0 10px;color:var(--bone)}
#pt .pt-plain p{font-size:13.5px;line-height:1.75;color:#c7d3dc}
#pt .pt-plain p + p{margin-top:12px}

/* ---- the spec box ---- */
#pt .pt-spec{margin:24px 0 0;background:var(--panel);border:1px solid var(--line);
  border-left:3px solid var(--gold);border-radius:3px;padding:18px 20px}
#pt .pt-spec h3{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);
  margin:0 0 12px}
#pt .pt-spec dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:8px 18px;font-size:13px}
#pt .pt-spec dt{color:var(--dim);white-space:nowrap}
#pt .pt-spec dd{margin:0;color:var(--bone)}
#pt .pt-spec dd code{background:var(--ink);border:1px solid var(--line);border-radius:2px;
  padding:1px 6px;font-size:12px}

/* ---- the games grid ---- */
/* EXPLICIT COLUMN COUNTS, not auto-fill. There are twelve games and twelve
   divides by 6, 4, 3 and 2 -- auto-fill picked five across and left a
   two-item hole in the last row, which reads as a failed load rather than a
   tidy list. */
#pt .pt-games{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;
  margin:24px 0 0;list-style:none;padding:0}
@media (max-width:980px){#pt .pt-games{grid-template-columns:repeat(4,1fr)}}
@media (max-width:700px){#pt .pt-games{grid-template-columns:repeat(3,1fr)}}
@media (max-width:460px){#pt .pt-games{grid-template-columns:repeat(2,1fr)}}
#pt .pt-games li{display:flex;align-items:center;gap:8px;background:var(--panel2);
  border:1px solid var(--line);border-radius:3px;padding:11px 11px;font-size:12px;
  min-width:0}
#pt .pt-games li span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#pt .pt-games i{font-style:normal;font-size:15px;line-height:1}

/* ---- what we need back ---- */
#pt .pt-taken{display:block;margin-top:5px;font-size:12px;color:var(--dim);line-height:1.6;
  word-break:break-word}
#pt .pt-ask{margin:24px 0 0;padding:0;list-style:none;counter-reset:ask}
#pt .pt-ask li{counter-increment:ask;display:flex;gap:14px;padding:13px 0;
  border-top:1px solid var(--line);font-size:13.5px;line-height:1.6;color:#c7d3dc}
#pt .pt-ask li::before{content:counter(ask,decimal-leading-zero);flex:0 0 auto;
  font-size:11px;letter-spacing:.1em;color:var(--ochre);padding-top:3px}
/* THE TEXT IS ONE FLEX ITEM, not however many elements it happens to
   contain. Without this wrapper the <b> was its own item: "Monstrocity art"
   wrapped onto two lines of its own column while the rest of the sentence
   sat beside it, and the flex gap opened a space in front of every comma. */
#pt .pt-ask li > span{flex:1 1 auto;min-width:0}
#pt .pt-ask b{color:var(--bone)}

/* ---- close ---- */
#pt .pt-close{padding:44px 0 0;text-align:center}
#pt .pt-close h2{font-size:clamp(20px,3vw,26px)}
#pt .pt-cta{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:24px 0 0}
#pt .pt-cta a{display:inline-block;padding:12px 22px;border-radius:3px;font-size:12px;
  letter-spacing:.1em;text-transform:uppercase;text-decoration:none;
  border:1px solid var(--line);color:var(--bone);background:transparent}
#pt .pt-cta a.go{background:var(--ochre);border-color:var(--ochre);color:var(--ink);font-weight:700}
#pt .pt-cta a:hover{border-color:var(--ochre);color:var(--ochre)}
#pt .pt-cta a.go:hover{filter:brightness(1.1);color:var(--ink)}
#pt .pt-more{margin:26px 0 0;font-size:12.5px;color:var(--dim)}
#pt .pt-more a{color:var(--ochre)}

@media (max-width:620px){
  #pt .pt-hero{padding:34px 0 28px}
  #pt .pt-spec dl{grid-template-columns:1fr;gap:3px 0}
  #pt .pt-spec dt{margin-top:8px}
}
@media (prefers-reduced-motion:reduce){
  #pt .pt-track{animation:none;flex-wrap:wrap;width:auto;justify-content:center}
}
</style>

<div id="pt">

  <div class="pt-hero">
    <p class="pt-eyebrow">By invitation</p>
    <h1><?php if ($pt_for !== ''): ?>Welcome, <em><?php echo $pt_e($pt_for); ?></em>.<br>You have been
        invited to join Skulliance.<?php
        else: ?>You have been invited<br>to join <em>Skulliance</em>.<?php endif; ?></h1>
    <p class="pt-lede">There is no application form and no listing fee, because there is no
      list to buy your way onto. Projects are here because we went and asked them, and
      <?php echo $pt_for !== '' ? $pt_e($pt_for) : 'yours'; ?> is one we want.</p>
    <p class="pt-lede">What that gets you is a home for your collectors: somewhere to stake
      what they own, earn off-chain points you name, and play a dozen games alongside
      <?php echo count($pt_names) >= 10 ? count($pt_names) . ' other projects' : 'the other projects already here'; ?>
      and their communities. You build none of it. We do, and it costs you nothing.</p>
    <p class="pt-free">Free to join &middot; Free to stay</p>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">What this is</p>
    <h2>Six artists built somewhere their collectors would want to stay</h2>
      <p>Skulliance started as a group of artists specialising in skull art on Cardano, who came
        together under <a href="https://www.x.com/oculusorbus" target="_blank" rel="noopener"
        style="color:var(--ochre)">Oculus Orbus</a> - a collector of their work who also happened
        to be a developer. He built the staking platform so that holding any of their art earned
        something from the first night, whether or not the holder ever played anything.</p>
      <p>It worked, so they started inviting other artists. Then it outgrew one chain, and now
        runs on <?php echo $pt_e(implode(', ', $pt_chains)); ?> with every project's holders on
        the same boards and the same account. It keeps growing for a plain reason: a single
        collection cannot justify building staking, missions, realms, a marketplace and a dozen
        games, and <?php echo count($pt_names) >= 10 ? count($pt_names) : 'forty'; ?> of them
        together can. Every artist who joins makes the thing every other artist is part of
        bigger, which is why nobody here is competing for the same collectors.</p>
      <p>It is a small team and a public one. Everyone is reachable, the mechanics are all
        written down in the <a href="skullpaper.php" style="color:var(--ochre)">Skull Paper</a>,
      and nothing about how the platform works is a secret from the people on it.</p>
    <?php /* THE SIX THEMSELVES, the way the homepage shows them, from the
             one file both read. A platform screenshot here answered a
             question nobody had asked yet -- this section is about who
             started it, so it shows them. */ ?>
    <ul class="pt-founders">
      <?php foreach ($pt_founders as $fd): list($df, $dn, $du) = $fd; ?>
        <li><a href="<?php echo $pt_e($du); ?>" target="_blank" rel="noopener">
          <img src="https://www.skulliance.io/staking/images/projects/<?php echo $pt_e($df); ?>"
               alt="<?php echo $pt_e($dn); ?>" loading="lazy" decoding="async">
          <span><?php echo $pt_e($dn); ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if (count($pt_names) >= 6): ?>
  <div class="pt-sect" style="border-bottom:0;padding-bottom:4px">
    <p class="pt-kicker">Who else was asked</p>
    <h2><?php echo count($pt_names); ?> projects, and the artists behind them</h2>
    <p>Six artists founded Skulliance. Every one since was invited, the same way you are being
      invited now. Their holders will see <?php echo $pt_e($pt_who); ?> on the boards from the day
      you join, and yours will see them.</p>
  </div>
  <?php /* THE SAME ROSTER THE HOMEPAGE SHOWS, from the same file. Two
           counter-scrolling rows, each holding its list twice so the -50%
           translate loops seamlessly; the second pass is aria-hidden so a
           screen reader hears each artist once. The flyer set is a SUBSET
           of the projects that stake -- not everyone has artwork on file --
           which is why the count above comes from the database and not from
           this list. */ ?>
  <?php foreach (array_chunk($pt_flyers, (int)ceil(count($pt_flyers) / 2)) as $ri => $row): ?>
  <div class="pt-strip">
    <div class="pt-strip-track<?php echo $ri % 2 ? ' pt-reverse' : ''; ?>">
      <?php for ($pass = 0; $pass < 2; $pass++): foreach ($row as $fl):
            list($ff, $fn, $fu) = $fl; ?>
        <a class="pt-strip-card" href="<?php echo $pt_e($fu); ?>" target="_blank" rel="noopener"<?php
           echo $pass ? ' aria-hidden="true" tabindex="-1"' : ''; ?>>
          <img src="https://www.skulliance.io/staking/images/projects/<?php echo $pt_e($ff); ?>"
               alt="<?php echo $pass ? '' : $pt_e($fn); ?>" loading="lazy" decoding="async">
          <span><?php echo $pt_e($fn); ?></span>
        </a>
      <?php endforeach; endfor; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php /* The strips are full-bleed, so the section rule that would normally
           close this block has to be drawn on its own. */ ?>
  <div class="pt-rule"></div>
  <?php endif; ?>

  <div class="pt-sect">
    <p class="pt-kicker">What we set up</p>
    <h2>The whole thing, configured for you</h2>
    <p>This is the part that usually takes a studio months. For you it is a conversation and some
      artwork.</p>
    <ul class="pt-build">
      <li><b>Your project, on your chain</b><span>Skulliance is multichain:
        <?php echo $pt_e(implode(', ', $pt_chains)); ?>. We find your collections on chain
        ourselves - you do not need to dig out a policy id - and holders across chains compete on
        the same boards.</span></li>
      <li><b>Every collection you have</b><span>Not just the flagship. We add all of them and set
        each one's rate, so a 1/1 series and a 10k PFP set can pay differently and still belong to
        the same project.</span></li>
      <li><b>Your points</b><span>Your own named points, earned nightly by your holders and spent
        across the platform. Off chain, not a token - see below. We need two things from you for
        them, and they are both below.</span></li>
      <li><b>Your missions</b><span>We write and configure the whole mission set. Holders send NFTs
        out on timed runs for your points; bigger holdings mean better success rates.</span></li>
      <li><b>Your realm theme</b><span>We build the artwork for your realm so holders can claim land
        under your banner, train soldiers, join a faction and raid.</span></li>
      <li><b>Your Monstrocity roster</b><span>A full playable theme in the match-3 RPG, plus boss
        battles gated to your collections. Art list below.</span></li>
    </ul>
    <div class="pt-shots two">
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/missions.png" alt="Missions, configured per project" loading="lazy" decoding="async"><figcaption>Missions, configured per project</figcaption></figure>
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/realms.png" alt="Realms, with your theme" loading="lazy" decoding="async"><figcaption>Realms, with your theme</figcaption></figure>
    </div>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">Your points</p>
    <h2>Pick a word. Send one icon.</h2>
    <p>That is the entire ask. Your points show up everywhere on the platform - in the Store, on
      the leaderboards, on every NFT panel, as a tile on the Skull Swap board - so the icon gets
      a lot of use.</p>

    <?php /*
     * THE FEAR THIS SECTION HAS TO ANSWER, and it has to answer it here
     * rather than in a footnote. An artist reading "your own currency"
     * hears "you want me to launch a token": a contract, a listing, a price
     * chart, holders who turn up to speculate, and a regulatory question
     * nobody wants. None of that is what this is, and the page lost people
     * on the word alone. So the word is gone and the facts are stated flat
     * -- off chain, not minted, not tradeable, worth nothing outside the
     * platform -- before anything about what they are for.
     */ ?>
    <div class="pt-plain">
      <h3>Points are not a token</h3>
      <p>They are a number in a database, held entirely <b>off chain</b>. Nothing is minted,
        nothing is issued on any chain, and there is no contract anywhere. Points cannot be
        bought, sold, sent between people, traded, swapped, bridged or withdrawn, and they have
        no value off the platform. Nobody can speculate on them because there is nothing to
        speculate on.</p>
      <p>Your holders earn them by holding your art and turning up, and spend them in the Store
        for things you and we put there. That is the whole mechanism. Joining Skulliance does not
        put your project anywhere near a token launch, and it never will.</p>
    </div>

    <?php if ($pt_icons): ?>
    <?php /* Doubled so the translateX(-50%) loop is seamless. The copy is
             aria-hidden so a screen reader hears each project once. */ ?>
    <div class="pt-rail">
      <div class="pt-track">
        <?php for ($copy = 0; $copy < 2; $copy++): ?>
        <?php foreach ($pt_icons as $ic): ?>
          <div class="pt-chip"<?php echo $copy ? ' aria-hidden="true"' : ''; ?>>
            <img src="<?php echo $pt_e($ic['img']); ?>" alt="<?php echo $copy ? '' : $pt_e($ic['cur']); ?>"
                 loading="lazy" onerror="this.closest('.pt-chip').remove()">
            <b><?php echo $pt_e($ic['cur']); ?></b>
            <span><?php echo $pt_e($ic['name']); ?></span>
          </div>
        <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="pt-spec">
      <h3>What to send</h3>
      <dl>
        <dt>Name</dt>
        <dd>2 to 16 letters or digits. It becomes the icon's filename and a tile on the game
          board, so no spaces or punctuation.</dd>
        <dt>Icon</dt>
        <dd><code>150 &times; 150</code> PNG, <b>white</b> artwork on a <b>transparent</b>
          background. White because it is tinted and placed on a dozen different backgrounds;
          transparent because it is never shown on a square.</dd>
        <dt>That is it</dt>
        <dd>We handle the rate, the rounding, the store pricing and every page it appears on.</dd>
      </dl>
    </div>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">Monstrocity &amp; Boss Battles</p>
    <h2>Your characters, in a game people already play</h2>
    <p>Monstrocity is a turn-based match-3 RPG. A theme puts <em>your</em> art in it: your holders
      fight as their own NFTs against a roster you supply, with your logo and your backdrop.
      Boss Battles reuse the same engine as co-op PvE, with bosses gated to your collections so
      only your holders can fight them.</p>
    <div class="pt-shots two">
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/monstrocity.png" alt="Monstrocity, themed per project" loading="lazy" decoding="async"><figcaption>Monstrocity, themed per project</figcaption></figure>
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/boss-battles.png" alt="Boss Battles, gated to your collections" loading="lazy" decoding="async"><figcaption>Boss Battles, gated to your collections</figcaption></figure>
    </div>
    <p style="margin-top:14px">We build the theme. Working from the art you have already
      published, we produce the roster, the backdrop and the board logo - there is nothing for you
      to draw unless you want to.</p>
    <div class="pt-spec">
      <h3>What a complete theme is</h3>
      <dl>
        <dt>Opponents</dt>
        <dd><b><?php echo (int)$pt_mon_chars; ?> characters</b>, each in
          <?php echo count($pt_mon_sets); ?> states -
          <?php echo $pt_e(implode(', ', $pt_mon_sets)); ?> -
          so <b><?php echo (int)$pt_mon_total; ?> images</b> for a complete roster.</dd>
        <dt>Backdrop</dt>
        <dd>One background image for the board.</dd>
        <dt>Logo</dt>
        <dd>One project logo, shown above the board.</dd>
        <dt>Bosses</dt>
        <dd>One image per boss, as many as you want, whenever you want them.</dd>
      </dl>
    </div>
    <p style="margin-top:16px">If you would rather draw them yourself, or you have existing
      character art you want used, send it and we will use that instead. Either way a partial
      roster plays fine and we fill it in over time.</p>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">What your holders get</p>
    <h2>A dozen reasons to keep the NFT</h2>
    <p>Every one of these is already built, already live, and comes switched on the day you join.
      Your holders do not download anything.</p>
    <div class="pt-shots four">
        <?php /* NOT DHC Arena: it has its own section further down, and
                 leading the games row with it spends the first tile on
                 something the page is about to cover properly. */ ?>
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/skull-swap.png" alt="Skull Swap" loading="lazy" decoding="async"><figcaption>Skull Swap</figcaption></figure>
        <figure><img src="https://www.skulliance.io/staking/images/cryptcrawl.png" alt="Crypt Crawl" loading="lazy" decoding="async"><figcaption>Crypt Crawl</figcaption></figure>
        <figure><img src="https://www.skulliance.io/staking/images/cryptconquest.png" alt="Crypt Conquest" loading="lazy" decoding="async"><figcaption>Crypt Conquest</figcaption></figure>
        <figure><img src="https://www.skulliance.io/staking/images/guardians.png" alt="Realm Guardians" loading="lazy" decoding="async"><figcaption>Realm Guardians</figcaption></figure>
    </div>
    <ul class="pt-games">
      <li><i>&#129516;</i><span>DHC Fighters</span></li>
      <li><i>&#127967;</i><span>DHC Arena</span></li>
      <li><i>&#128126;</i><span>Monstrocity</span></li>
      <li><i>&#128121;</i><span>Boss Battles</span></li>
      <li><i>&#128260;</i><span>Skull Swap</span></li>
      <li><i>&#129354;</i><span>Gauntlets</span></li>
      <li><i>&#128128;</i><span>Crypt Crawl</span></li>
      <li><i>&#128081;</i><span>Crypt Conquest</span></li>
      <li><i>&#127937;</i><span>Skull Racer</span></li>
      <li><i>&#128269;</i><span>Obscura</span></li>
      <li><i>&#128640;</i><span>Drop Ship</span></li>
      <li><i>&#128715;</i><span>Oculus Lounge</span></li>
    </ul>
    <p style="margin-top:18px">Plus idle <b>missions</b>, <b>realms</b> with land, soldiers,
      factions and raids, daily rewards with streaks, and a marketplace with auctions and raffles.</p>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">DHC</p>
    <h2>The deep end, for the ones who want it</h2>
    <p>Most stakers stake. A few want something to sink months into, and DHC is what they find.
      It is the largest thing on the platform and it runs on top of everything else, so your
      holders walk into it already playing.</p>
    <ul class="pt-build">
      <li><b>Traits drop from every game</b><span>Win a run of Crypt Crawl, hold a realm, place in
        the Arena - any game can pay out a trait. Rarity is not invented: a trait's tier is how
        many of the 226 original Fighters wore it, read from chain.</span></li>
      <li><b>Fighters get assembled</b><span>Traits are parts. Players build their own Fighter out
        of what they have earned, name it, and keep it - a thing that did not exist until they
        made it.</span></li>
      <li><b>And then they fight</b><span>DHC Arena is Crew versus Crew, player against player.
        Rarity does not decide a fight, so a Fighter built from common parts can beat one built
        from mythics.</span></li>
      <li><b>All of it is public</b><span>The Collection is browsable by anyone, signed in or not,
        with its own share cards. Your holders' Fighters are a thing they can post, which is
        reach you did not pay for.</span></li>
    </ul>
    <?php
    /*
     * THE REAL DHC SCREENSHOTS, through dhc_shot_src() rather than hardcoded
     * paths -- dhcgame.php shows the same three and the helper prefers a
     * -sm.jpg derivative when one is uploaded, which only works while there
     * is one copy of that rule. A shot that is not on the server yet returns
     * nothing and renders no figure, so the row shrinks rather than breaking.
     *
     * One per claim made above: assembled, fought, public. The trait card
     * that was here briefly was a picture of a part, not of the thing.
     */
    $pt_dhc = array(
        array('dhcassemble', 'The assembler, ten trait slots'),
        array('dhcgame',     'DHC Arena, Crew versus Crew'),
        array('dhcbrowse',   'The Collection, open to everyone'),
    );
    $pt_dhc_have = array();
    foreach ($pt_dhc as $d) {
        $sh = dhc_shot_src($d[0]);
        if ($sh) $pt_dhc_have[] = array($sh, $d[1]);
    }
    if ($pt_dhc_have): ?>
    <div class="pt-shots<?php echo count($pt_dhc_have) === 3 ? ' three' : ' two'; ?>">
      <?php foreach ($pt_dhc_have as $d): list($sh, $cap) = $d; ?>
        <figure><img src="<?php echo $pt_e($sh['src']); ?>" alt="<?php echo $pt_e($cap); ?>"
          <?php if ($sh['w']) printf('width="%d" height="%d"', $sh['w'], $sh['h']); ?>
          loading="lazy" decoding="async"><figcaption><?php echo $pt_e($cap); ?></figcaption></figure>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p style="margin-top:16px">The art is by <a href="https://x.com/MMAXI404" target="_blank"
      rel="noopener" style="color:var(--ochre)">Maxingo</a>, who shared it so this could be built.
      Nothing about DHC asks anything of you - it is simply there, and it is the reason a chunk
      of the platform's regulars log in daily.</p>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">Leaderboards</p>
    <h2>Somewhere for your community to show off</h2>
    <p>Most boards run two tracks: a monthly one that often pays out, and an all-time one for
      bragging rights. Your project gets its own standings the day it joins - and your holders
      appear on the platform-wide boards too, which is how other projects' collectors find out
      you exist.</p>
    <ul class="pt-build">
      <li><b>Points</b><span>Overall and per-project standings, so your holders compete with each
        other as well as with everyone else.</span></li>
      <li><b>Missions</b><span>All-time and monthly, the monthly track paying a CARBON pool.</span></li>
      <li><b>Raids and factions</b><span>Realms competition, by player and by faction.</span></li>
      <li><b>Every game</b><span>Monstrocity, Boss Battles, Skull Swap, Gauntlets, Crypt Crawl,
        Crypt Conquest and Skull Racer each keep their own.</span></li>
    </ul>
    <div class="pt-shots">
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/leaderboard.png" alt="Monthly and all-time, per project and platform-wide" loading="lazy" decoding="async"><figcaption>Monthly and all-time, per project and platform-wide</figcaption></figure>
    </div>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">The Store</p>
    <h2>Give your points somewhere to go</h2>
    <p>The Store is where points get spent. Your holders redeem <em>your</em> points for items you
      contribute - NFTs, whitelist spots, merch, whatever you want to put up - and DIAMOND, the
      platform's own points, can claim anything.</p>
    <p style="margin-top:14px">You do not need to build any claim infrastructure, and you do not
      need to hand over custody of anything you are not giving away.</p>
    <div class="pt-shots">
        <figure><img src="https://www.skulliance.io/staking/images/screenshots/store.png" alt="The Store, filterable by project" loading="lazy" decoding="async"><figcaption>The Store, filterable by project</figcaption></figure>
    </div>
    <div class="pt-spec">
      <h3>How a claim works</h3>
      <dl>
        <dt>You send</dt>
        <dd>The reward NFTs to <code>$diamondskulls</code>, so claims can be managed in one place.</dd>
        <dt>A holder claims</dt>
        <dd>They spend your points in the Store. We are notified.</dd>
        <dt>We send</dt>
        <dd>The NFT goes out manually to the claimant. Nothing for you to run, nothing to automate,
          no contract to deploy.</dd>
      </dl>
    </div>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">What it is for</p>
    <h2>Somewhere for your collectors to gather</h2>
    <p>Nothing here is meant to justify your work - people already bought it, and they bought it
      for what it is. What a collection does not usually come with is a room: a place where the
      people who own it turn up, see each other, and do something with the thing they bought.
      That is what this adds, and it adds it without you becoming a game studio.</p>
    <ul class="pt-build">
      <li><b>Your art, in front of people</b><span>Every staked piece is shown across the platform -
        the gallery, the panels, the boards - to collectors of thirty other projects who have
        never seen it.</span></li>
      <li><b>Something for collectors to do</b><span>They stake, play, compete and claim with your
        art rather than only storing it in a wallet.</span></li>
      <li><b>A community that meets</b><span>Your holders land among the communities already here,
        and theirs find you. That is how most projects on this page met each other.</span></li>
      <li><b>Nothing to maintain</b><span>No contracts, no servers, no mod work. If something
        breaks it is ours to fix.</span></li>
    </ul>
  </div>

  <div class="pt-sect">
    <p class="pt-kicker">Your side of it</p>
    <h2>Everything we need from you</h2>
    <p>Four things, and two more only if you want them.</p>
    <ul class="pt-ask">
      <?php /* THE NAME HAS TO BE FREE, AND THE PAGE NOW SAYS SO. It asked for
               "2 to 16 letters or digits" and stopped, so the first thing a
               real partner hit was being told their obvious choice was gone
               -- MOON, to Disco Solaris. The list is every ticker in use,
               read live, so they can check before asking rather than after. */ ?>
      <li><span><b>A name for your points</b> - 2 to 16 letters or digits, and it has to be
        one nobody is using yet.
        <?php if ($pt_curs): ksort($pt_curs); ?>
        <span class="pt-taken">Already taken: <?php
          echo $pt_e(implode(', ', array_keys($pt_curs))); ?>.</span>
        <?php endif; ?></span></li>
      <li><span><b>One icon for the points</b> - 150 &times; 150 PNG, white on transparent.</span></li>
      <li><span><b>Your logo</b> - whatever you already have, any size.</span></li>
      <?php /* THE ONE HE HAD TO EXPLAIN EVERY TIME. "We build the artwork" is
               true of the icons and the game art, and it reads as "send us
               nothing" -- but missions, realms and the rest are curated out
               of the partner's own world, and without their art and lore
               there is nothing to curate. */ ?>
      <li><span><b>Your collateral</b> - art, lore, promo material, anything you have. It is what
        missions, realms and the rest get built out of, so the more there is the more of your
        world ends up in the game.</span></li>
      <li><span><b>Store rewards</b>, if you want them - sent to <code>$diamondskulls</code> whenever you
        have something to give away.</span></li>
      <li><span><b>Someone to talk to</b> - one person on your side who can answer a question.</span></li>
    </ul>
    <p style="margin-top:20px;color:var(--dim);font-size:13px">That is the whole list. We find your
      collections on chain ourselves, we set the rates, and we draw every icon, card and game
      asset from what you send. No fee, no
      revenue share, no exclusivity, and no token - points are off chain and stay that way. You
      can leave whenever you like.</p>
  </div>

  <div class="pt-close">
    <h2><?php if ($pt_for !== ''): ?>Ready when you are, <?php echo $pt_e($pt_for); ?>.<?php
        else: ?>Ready when you are.<?php endif; ?></h2>
    <p class="pt-lede" style="margin:14px auto 0">Send a message and we will take it from there.
      Setup is on us.</p>
    <div class="pt-cta">
      <?php /* THE VANITY INVITE IS NOT REAL. This button pointed at the
         obvious guessable form of the invite, which Discord answers
         Unknown Invite for -- so the one call to action on the partner
         page has been sending every prospective partner to an error
         page. Verified against the Discord API; the code below is the
         same one info.php, site-header.php and the homepage use. */ ?>
      <a class="go" href="https://discord.gg/JqqBZBrph2" target="_blank" rel="noopener">Talk to us on Discord</a>
      <a href="https://x.com/skulliance" target="_blank" rel="noopener">@skulliance on X</a>
      <a href="gallery.php">See what is staked</a>
    </div>
    <p class="pt-more">Want the detail rather than the pitch? Everything on this page is
      documented in full in the <a href="skullpaper.php">Skull Paper</a> - every mechanic, every
      rate, every formula.</p>
  </div>

</div>

<!-- Footer -->
<div class="footer">
  <p>Skulliance<br>Copyright © <span id="year"></span>
</div>
</div>
</div>
</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>

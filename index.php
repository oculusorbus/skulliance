<?php
/*
 * index.php -- the staking root, and the page every invite lands on.
 *
 * IT WAS A BLANK PAGE WITH ONE BUTTON. Fourteen lines of static HTML on a
 * dark background: no art, no pitch, no mention of what Skulliance is. Every
 * share poster, every report-card post and every three traits paid out on a
 * referral was spent delivering a cold stranger to that. The public pitch
 * lives on the WordPress homepage at skulliance.io, which the invite link
 * never touches.
 *
 * THE CALL TO ACTION IS STILL THE DISCORD BUTTON, unchanged and in the same
 * place, surrounded by the reasons to press it. Nothing else here competes
 * with it: the two free games are named as evidence, not offered as a
 * second button.
 *
 * THE REFERRAL CODE IS CAPTURED HERE OR NOT AT ALL. An invite is
 * /staking/?r=<code>, and this file had no PHP in it whatsoever -- so
 * ref_capture() never ran, the cookie was never set, and by the time the
 * arrival came back from Discord's OAuth hop there was nothing left to say
 * who had invited them. The invite simply never paid, with no error anywhere
 * and the arrival signed in perfectly normally. db.php does the capture for
 * every other page on the platform, which is exactly why this was
 * invisible: the one page the link points at is the one page without it.
 *
 * BEFORE ANY OUTPUT, because ref_capture() sets a cookie and setcookie()
 * after the first byte of HTML does nothing (it checks headers_sent() and
 * returns quietly, which is the same silent failure in a different place).
 *
 * NO db.php, DELIBERATELY. This is the most-hit cold page on the platform
 * and a crawler's first stop. homepage-data.php exists precisely so a public
 * page can have the live figures without it: raw credentials, a five-minute
 * file cache, and a stale value served when the database is unreachable, so
 * a blip shows last week's number instead of a zero. site-header.php is
 * likewise self-contained, no session and no database.
 *
 * The session is resumed only when there is already a session cookie --
 * starting one unconditionally would write an orphan session file for every
 * cold visitor and every crawler that ever hits the login page, the same
 * reason skullswap.php guards its own restore that way.
 */
require_once __DIR__ . '/lib/referral-lib.php';
if (session_status() !== PHP_SESSION_ACTIVE && isset($_COOKIE[session_name()])) @session_start();
ref_capture();

require_once __DIR__ . '/homepage-data.php';

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

/* THE OAUTH URL IS UNCHANGED, byte for byte. It carries a client id, a
   redirect that must match what Discord has registered, and a scope list;
   none of it is ours to tidy. */
$ix_oauth = 'https://discord.com/api/oauth2/authorize?client_id=1007909434141462588&permissions=268437504&redirect_uri=https%3A%2F%2Fskulliance.io%2Fstaking%2Fprocess-oauth.php&response_type=code&scope=identify%20guilds%20guilds.members.read';

/* Already signed in? Then this page is not advertising to them. The sign-in
   link stays underneath regardless, because a SessionCookie can be stale and
   the only recovery is the same button. */
$ix_signed_in = !empty($_SESSION['userData']['user_id']);

/*
 * X SIGN-IN IS A SECOND DOOR TO AN EXISTING ACCOUNT, never a way to
 * make one. It finds the member who connected that X account and signs
 * them in; if nobody has, it sends them back here with ?x=nolink and
 * the line below explains what to do. Discord stays the way in.
 *
 * Why it is worth having at all: somebody arriving from a shared run is
 * on X already and one tap from being recognised, where Discord on a
 * phone is an app switch or a password at the exact moment their
 * interest is most fragile.
 */
$ix_xnote = isset($_GET['x']) ? (string)$_GET['x'] : '';
$ix_xsaid = array(
	'nolink'      => 'No Skulliance account is connected to that X account yet. '
	               . 'Sign in with Discord, then connect X from your Wallets page.',
	'cancelled'   => 'Cancelled at X. Nothing changed.',
	'expired'     => 'That took too long. Try again.',
	'failed'      => 'X did not complete the sign-in. Try again, or use Discord.',
	'nocode'      => 'X did not send us anything to work with. Try again.',
	'signin'      => 'Sign in with Discord first, then you can connect X.',
	'unavailable' => 'X sign-in is not configured yet.',
);

/*
 * An invited arrival gets told what is waiting, in the number the code
 * actually pays -- REF_ARRIVAL_DRAWS, not a 3 typed into the copy.
 *
 * THE COOKIE COUNTS, NOT ONLY ?r=. The invite posted on X points at
 * digest.php, and its button brings the arrival here -- one hop, after
 * which the query string is gone while the cookie ref_capture() set is
 * still there. Keying the offer on ?r= alone meant the person who came the
 * normal way was the one who never saw it mentioned, on the page that
 * exists to convert them. The cookie is what ref_credit() will actually
 * pay on, so it is the right thing to ask.
 */
$ix_ref = '';
if (!empty($_GET['r']) && ref_decode($_GET['r']) > 0)              $ix_ref = (string) $_GET['r'];
else if (!empty($_COOKIE[REF_COOKIE]) && ref_decode($_COOKIE[REF_COOKIE]) > 0)
	$ix_ref = (string) $_COOKIE[REF_COOKIE];
$ix_invited = $ix_ref !== '' && !$ix_signed_in;

/*
 * NAME THEM. The code decodes to exactly one account and it is the account
 * that will actually be paid, so "whoever sent you" was being coy about
 * something the page already knew. No ownership check is needed here, the
 * way digest.php needs one: there is no poster on this page to mismatch,
 * and the claim being made is simply who ref_credit() will pay.
 */
$ix_from = $ix_invited ? hp_username(ref_decode($ix_ref)) : '';

/*
 * The figures, live. A zero means the query failed and no stale value was
 * cached, so the tile is dropped rather than printed: "0 NFTs staked" on the
 * page that exists to say this is a going concern is worse than one fewer
 * number.
 */
$ix_stats = array();
$ix_push  = function ($n, $one, $many) use (&$ix_stats) {
	if ((int) $n > 0) $ix_stats[] = array(number_format((int) $n), (int) $n === 1 ? $one : $many);
};
$ix_staked = hp_stat_nfts_staked();
$ix_push($ix_staked,            'NFT staked',  'NFTs staked');
$ix_push(hp_stat_artists(),     'project',     'projects');
$ix_push(hp_stat_collections(), 'collection',  'collections');
$ix_push(hp_stat_chains(),      'chain',       'chains');

/*
 * ALL OR NOTHING. hp_stat_chains() falls back to 1 while every other figure
 * falls back to 0, so a database the page cannot reach renders exactly one
 * tile reading "1 chain" -- which looks less like a live platform than
 * showing no figures at all. The headline number has to be real or the
 * strip does not appear.
 */
if ($ix_staked <= 0 || count($ix_stats) < 2) $ix_stats = array();

require_once __DIR__ . '/lib/dhc-shots.php';
/* The same three shots partner.php uses, through the same resolver, so a
   -sm.jpg dropped on the server later is picked up here too. */
$ix_dhc = array(
	array('dhcgame',     'Earn them by playing',
	      'Every game pays a different part of a Fighter, so no one game can finish one. '
	    . 'Traits turn up as you play.'),
	array('dhcassemble', 'Assemble them',
	      'Ten slots: torso, head, background, effects, arms, weapon, headgear, a companion. '
	    . 'Build what you like out of what you have earned and save it.'),
	array('dhcbrowse',   'Then see where you stand',
	      'Your best Fighter\'s rarity score is your place on the board. The rarest Fighter wins.'),
);
$ix_projects = hp_stat_artists();
$ix_icons    = hp_project_icons();
$ix_founders = require __DIR__ . '/lib/founding-artists.php';

/* What staking itself gets you, each with the screenshot of the real page. */
$ix_platform = array(
	array('missions.png',    'Missions',     'Send your NFTs out on timed missions and they come back with points. Longer missions pay more, and the ones you have staked longest can reach the better tiers.'),
	array('realms.png',      'Realms and raids', 'Build a realm, garrison it with your NFTs, then raid somebody else or hold yours against them. Your NFTs carry a record of every raid they have fought.'),
	array('leaderboard.png', 'Leaderboards', 'Every game has a leaderboard, and so does most of the rest of the platform.'),
	array('store.png',       'The store',    'Spend what you earn. Projects put real NFTs and merch in there, and claims are handled by hand so nothing is minted into the void.'),
);

/* The arcade. The two marked free are genuinely playable with no account --
   skullswap.php and monstrocity.php both render for a logged-out visitor and
   enforce score saving server-side, so this claim is not marketing. */
$ix_games = array(
	array('screenshots/monstrocity.png',  'Monstrocity',     'Your NFT as a fighter in a turn-based brawler, against 28 opponents and the bosses behind them.', true),
	array('screenshots/skull-swap.png',   'Skull Swap',      'A match-3 built out of the project icons of everything on the platform.', true),
	array('screenshots/boss-battles.png', 'Boss Battles',    'The whole platform against one boss, together, for a window of a few days.', false),
	array('cryptcrawl.png',               'Crypt Crawl',     'A solo card game about getting out of a crypt with your skin on.', false),
	array('cryptconquest.png',            'Crypt Conquest',  'Its sibling, and the harder of the two. One deck, four royals, no luck to hide behind.', false),
	array('guardians.png',                'Realm Guardians', 'Tower defence played on your own realm, with your own NFTs holding the line.', false),
);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Skulliance | Stake your NFTs, then go and play with them</title>
  <meta name="description" content="A free staking platform and games arcade for <?php echo $e($ix_projects ? $ix_projects . ' NFT projects' : 'NFT projects'); ?>. Sign in with Discord.">
  <meta name="theme-color" content="#0e0f13">
<?php include __DIR__ . '/lib/favicon-head.php'; ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:title" content="Stake your NFTs, then go and play with them">
  <meta property="og:description" content="A free staking platform and games arcade for <?php echo $e($ix_projects ? $ix_projects . ' NFT projects' : 'NFT projects'); ?>. Sign in with Discord.">
  <meta property="og:image" content="https://www.skulliance.io/staking/images/og.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@skulliance">
  <meta name="twitter:image" content="https://www.skulliance.io/staking/images/og.jpg">
<style>
/*
 * Self-contained, under an `ix-` prefix. dist/output.css is a 12KB purged
 * Tailwind build from a year ago, so a class this page invents would simply
 * not exist in it -- the original page's bg-discord-gray worked because it
 * was in that build's content set and nothing new is.
 */
:root{
  --ix-bg:#0e0f13; --ix-panel:#171a21; --ix-panel2:#1d2129; --ix-line:#282e3a;
  --ix-text:#e9ecf3; --ix-dim:#9aa4b4; --ix-accent:#00c8a0; --ix-discord:#5865f2;
}
*{box-sizing:border-box}
body{margin:0;background:var(--ix-bg);color:var(--ix-text);
  font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased}
img{max-width:100%;display:block}
a{color:var(--ix-accent)}
.ix-wrap{max-width:1180px;margin:0 auto;padding:0 20px}
.ix-sec{padding-block:clamp(44px,7vw,86px)}
.ix-sec+.ix-sec{border-top:1px solid var(--ix-line)}
.ix-h2{font-size:clamp(25px,3.6vw,40px);line-height:1.15;font-weight:800;letter-spacing:-.02em;
  margin:0 0 12px;text-wrap:balance}
.ix-lead{color:var(--ix-dim);font-size:clamp(16px,1.6vw,18px);margin:0 0 30px}
.ix-eyebrow{font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--ix-accent);
  font-weight:700;margin:0 0 14px}
.ix-logo{width:260px;max-width:62%;height:auto;margin:0 0 20px;
  filter:drop-shadow(0 10px 30px rgba(0,0,0,.6))}

/* ---- hero ---- */
.ix-hero{padding-block:clamp(40px,6vw,76px) clamp(36px,5vw,60px);
  background:radial-gradient(900px 420px at 50% -10%,rgba(0,200,160,.14),transparent 70%)}
.ix-hero h1{font-size:clamp(33px,6vw,62px);line-height:1.04;font-weight:800;letter-spacing:-.03em;
  margin:0 0 18px;text-wrap:balance}
.ix-hero p{color:var(--ix-dim);font-size:clamp(17px,1.9vw,20px);margin:0 0 30px}
.ix-invited{display:flex;gap:12px;align-items:flex-start;margin:0 0 26px;padding:14px 18px;
  border:1px solid rgba(0,200,160,.4);border-radius:12px;background:rgba(0,200,160,.08)}
.ix-invited strong{color:var(--ix-accent)}
.ix-invited span{color:var(--ix-text);font-size:15px}
.ix-invited a{color:var(--ix-accent);text-decoration:underline;text-underline-offset:2px}
/* The button is inline-flex, so it sits in an anonymous line box whose
   leading eats into the space under it and the note below ends up tight
   against the button however much margin the note asks for. A block
   wrapper gives the gap somewhere to live. */
.ix-act{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 20px}
.ix-cta{display:inline-flex;align-items:center;gap:11px;background:var(--ix-discord);color:#fff;
  font-size:18px;font-weight:700;text-decoration:none;padding:16px 28px;border-radius:12px;
  border:0;transition:filter .15s ease,transform .15s ease}
.ix-cta:hover{filter:brightness(1.12);transform:translateY(-1px)}
.ix-cta svg{width:24px;height:24px;flex:0 0 auto;fill:currentColor}
.ix-cta-note{color:var(--ix-dim);font-size:14px;margin:0}
.ix-cta-note a{color:var(--ix-dim)}
.ix-second{display:block;font-size:14px;color:var(--ix-dim)}
/*
 * THE SECOND DOOR, AND IT LOOKS LIKE ONE. Discord is how you join; X
 * only works for somebody who has already connected one, so matching
 * the primary button would offer a stranger a choice that cannot work
 * for them. Outlined, smaller, same height so the pair still line up.
 */
.ix-second-cta{display:inline-flex;align-items:center;gap:7px;background:transparent;
  color:var(--ix-dim);font-size:15px;font-weight:600;text-decoration:none;
  padding:15px 22px;border-radius:12px;border:1px solid rgba(255,255,255,.18);
  transition:border-color .15s ease,color .15s ease}
.ix-second-cta:hover{border-color:rgba(255,255,255,.4);color:#fff}
/* Sized to the text it replaces, and nudged to sit on the same optical
   line -- a cap-height glyph next to lowercase letters reads high
   otherwise. */
.ix-second-cta svg{width:15px;height:15px;flex:0 0 auto;fill:currentColor;
  position:relative;top:1px}
/* Why the second button did nothing for them, when it did nothing. */
.ix-xnote{margin:0 0 18px;padding:11px 14px;border:1px solid rgba(214,158,46,.35);
  background:rgba(214,158,46,.07);color:#e0c07a;font-size:14px;border-radius:8px;max-width:620px}

/* ---- numbers ---- */
.ix-stats{display:flex;flex-wrap:wrap;gap:10px;margin:34px 0 0;padding:0;list-style:none}
.ix-stats li{flex:1 1 150px;background:var(--ix-panel);border:1px solid var(--ix-line);
  border-radius:12px;padding:16px 18px}
.ix-stats b{display:block;font-size:clamp(23px,2.8vw,31px);font-weight:800;letter-spacing:-.02em;
  font-variant-numeric:tabular-nums;line-height:1.1}
.ix-stats span{color:var(--ix-dim);font-size:13px}

/* ---- rows of cards ---- */
/* min() on the track floor, not a bare minmax: auto-fit still makes a track
   as wide as its minimum when the container is NARROWER than that, so
   minmax(380px,1fr) is a 380px column inside a 360px phone and the whole
   page scrolls sideways. min(…,100%) lets the track shrink to the column. */
.ix-grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(min(272px,100%),1fr))}
/* Four cards in a three-up grid leave one sitting alone on the second row.
   Two-up fills exactly, and these screenshots are dense enough to want it. */
.ix-grid.ix-two{grid-template-columns:repeat(auto-fit,minmax(min(380px,100%),1fr))}
.ix-card{background:var(--ix-panel);border:1px solid var(--ix-line);border-radius:14px;overflow:hidden;
  display:flex;flex-direction:column}
.ix-card-i{position:relative}
.ix-card img{aspect-ratio:16/10;object-fit:cover;width:100%;background:var(--ix-panel2)}
.ix-card-b{padding:16px 18px 20px}
.ix-card h3{margin:0 0 7px;font-size:18px;font-weight:700;letter-spacing:-.01em}
.ix-card p{margin:0;color:var(--ix-dim);font-size:14.5px;line-height:1.6}
/* ON the image, not above the heading: as a block above the title it moved
   the titles of the two free games out of line with the other four. */
.ix-free{position:absolute;left:10px;top:10px;font-size:11px;font-weight:700;letter-spacing:.09em;
  text-transform:uppercase;color:#04120e;background:var(--ix-accent);
  border-radius:999px;padding:4px 10px}

/* ---- the marquee ----
 * BOUNDED BY THE WRAPPER, never 100vw: 100vw includes the scrollbar, which
 * is how partner.php ended up with a horizontal scrollbar on a page that
 * had no content wider than the column. */
/* Full bleed by sitting OUTSIDE .ix-wrap rather than by 100vw, which
   includes the scrollbar and is how partner.php got a horizontal scrollbar
   on a page with nothing wider than its column. */
.ix-marq{overflow:hidden;margin:0 0 18px;mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent)}
.ix-track{display:flex;gap:18px;width:max-content;animation:ix-slide 70s linear infinite}
.ix-marq.ix-rev .ix-track{animation-duration:84s;animation-direction:reverse}
.ix-marq:hover .ix-track{animation-play-state:paused}
@keyframes ix-slide{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.ix-tile{flex:0 0 auto;width:112px;text-decoration:none;color:inherit}
.ix-tile img{width:112px;height:112px;object-fit:contain;padding:18px;border-radius:14px;
  border:1px solid var(--ix-line);background:var(--ix-panel)}
.ix-tile span{display:block;font-size:12px;color:var(--ix-dim);margin-top:8px;text-align:center;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media (prefers-reduced-motion:reduce){
  .ix-track{animation:none}
  .ix-marq{overflow-x:auto}
}

/* ---- points ---- */
.ix-points{background:var(--ix-panel);border:1px solid var(--ix-line);border-radius:14px;padding:clamp(20px,3vw,32px)}
.ix-points ul{margin:0;padding-left:22px;color:var(--ix-dim)}
.ix-points a{color:var(--ix-accent)}
.ix-points strong{color:var(--ix-text)}
.ix-points li{margin:0 0 7px}
.ix-points li:last-child{margin:0}

/* ---- closing ---- */
.ix-end{text-align:center;background:radial-gradient(760px 320px at 50% 110%,rgba(0,200,160,.13),transparent 70%)}
.ix-end .ix-h2{max-width:24ch;margin-inline:auto}
/* The wrapper that gives the button room below it is a flex row, and a
   flex row ignores the section's text-align, so the closing button went
   hard left in a centred section. */
.ix-end .ix-act{justify-content:center}
.ix-end .ix-lead{margin-inline:auto}
.ix-foot{border-top:1px solid var(--ix-line);padding:26px 0 36px;color:var(--ix-dim);font-size:13.5px;text-align:center}
.ix-foot a{color:var(--ix-dim);margin:0 9px}
</style>
</head>
<body>
<?php
/* Public nav, so an arrival is not at a dead end. Self-contained: no
   session, no database, root-relative links (the login cookie is host-only,
   so an absolute www link would log out anyone on the bare domain). */
$sh_links = array(
	array('Games',       '/#games'),
	array('Merch',       '/shop'),
	array('Skull Paper', '/staking/skullpaper.php'),
);
include __DIR__ . '/site-header.php';

$ix_note = 'Sign in with Discord. One click, and no wallet needed until you stake something.';

/*
 * THE EXPLAINER IS PLACED, NOT FIXED.
 *
 * An invited arrival is here for the traits -- that is the whole of their
 * motivation for clicking -- so being told what a DHC Fighter trait is
 * has to come straight after the button, not five sections down past the
 * staking pitch. Somebody who arrived cold has no such motivation yet:
 * for them "what is a DHC Fighter trait" before "what is Skulliance" is
 * an answer to a question they have not asked, so it stays in place.
 *
 * One copy, called from one of two positions, so the two orderings cannot
 * drift apart.
 */
$ix_traits = function () use ($ix_dhc, $e) { ?>
<section class="ix-sec" id="traits">
  <div class="ix-wrap">
    <h2 class="ix-h2">What is a DHC Fighter trait?</h2>
    <p class="ix-lead">DHC is <strong>Digital Hell Citizens</strong>, a collection by
    <a href="https://x.com/MMAXI404" target="_blank" rel="noopener noreferrer">Maxingo</a>. He let
    Skulliance use the artwork from <em>Digital Hell Citizens 2: Fighters</em> to build a game out
    of it. A trait is one piece of a fighter: a head, a weapon, a backdrop, a companion.</p>
    <div class="ix-grid">
<?php foreach ($ix_dhc as $d): $sh = dhc_shot_src($d[0]); ?>
      <article class="ix-card">
<?php if (!empty($sh['src'])): ?>
        <img src="<?php echo $e($sh['src']); ?>" alt="<?php echo $e($d[1]); ?>" loading="lazy" decoding="async">
<?php endif; ?>
        <div class="ix-card-b">
          <h3><?php echo $e($d[1]); ?></h3>
          <p><?php echo $e($d[2]); ?></p>
        </div>
      </article>
<?php endforeach; ?>
    </div>
    <div class="ix-points" style="margin-top:20px">
      <ul>
        <li><strong>A trait is not an NFT.</strong> It cannot be minted, and earning one gives you
        no ownership of the artwork. It is a game piece.</li>
        <li>The genuine, ownable Fighters are
        <a href="https://www.wayup.io/collection/b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5" target="_blank" rel="noopener noreferrer">the Digital Hell Citizens 2 collection on wayup</a>.
        If the art is what grabs you, that is where to go.</li>
        <li>Traits are free. You earn them by playing, and the three waiting for you are the same
        ones everybody else has to play for.</li>
      </ul>
    </div>
  </div>
</section>

<?php };

/* Written once and printed twice, top and bottom. */
$ix_button = function () use ($ix_oauth, $ix_signed_in, $e, $ix_xnote, $ix_xsaid) {
	/* PRINTED ONCE, at the top pair only. The same explanation twice on
	   one page reads as two different problems. */
	static $noted = false;
	if (!$noted && $ix_xnote !== '' && isset($ix_xsaid[$ix_xnote])) {
		echo '<p class="ix-xnote">' . $e($ix_xsaid[$ix_xnote]) . '</p>';
		$noted = true;
	}
	echo '<div class="ix-act">';
	if ($ix_signed_in) {
		echo '<a class="ix-cta" href="dashboard.php">Continue to your dashboard</a>';
		echo '<span class="ix-second">Not you, or signed in somewhere else? '
		   . '<a href="' . $e($ix_oauth) . '">Sign in with Discord again</a>.</span></div>';
		return;
	}
	echo '<a class="ix-cta" href="' . $e($ix_oauth) . '">'
	   . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.3.6a14.6 14.6 0 0 1 4.3 1.4 14.4 14.4 0 0 0-10.8 0A14.6 14.6 0 0 1 12.9 3.6L12.6 3A19.8 19.8 0 0 0 7.7 4.4C4.6 9 3.8 13.5 4.2 18a19.9 19.9 0 0 0 6 3l.8-1.2a13 13 0 0 1-2-1l.5-.4a14.2 14.2 0 0 0 9.1 0l.5.4a13 13 0 0 1-2 1l.8 1.2a19.9 19.9 0 0 0 6-3c.5-5.2-.8-9.7-3.6-13.6ZM9.7 15.3c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2Zm4.6 0c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2Z"/></svg>'
	   . 'Login with Discord</a>';
	/* SECOND, AND QUIETER. Discord is how you join; X is a shortcut
	   back in for people who have already connected one, so it must not
	   read as an equal choice to somebody who has never been here. */
	/* THE LOGO IS THE WORD. An X glyph beside the words "Sign in with X"
	   says X twice; the mark stands in for the name, the way the button
	   reads everywhere else on the web. aria-label carries the full
	   phrase, because a logo is not text to a screen reader. */
	echo '<a class="ix-second-cta" href="x-auth.php?mode=login" aria-label="Sign in with X">'
	   . 'Sign in with '
	   . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.3 22H3.2l7.3-8.3L2.4 2h6.4l4.4 5.9ZM17.8 20.1h1.7L7.9 3.8H6.1Z"/></svg>'
	   . '</a></div>';
};
?>

<section class="ix-hero">
  <div class="ix-wrap">
    <?php /* The wordmark, not the word. 700x300 on the server, drawn at
             260 wide, so width/height are stated to stop it shoving the
             headline down as it loads. */ ?>
    <img class="ix-logo" src="images/skulliancelogo.png" alt="Skulliance"
         width="700" height="300" fetchpriority="high" decoding="async">
    <h1>Stake your NFTs, then go and play with them.</h1>
<?php if ($ix_invited): ?>
    <div class="ix-invited">
      <span><strong><?php echo $ix_from !== ''
          ? $e($ix_from) . ' invited you.'
          : 'You were invited.'; ?></strong>
      <?php echo (int) REF_ARRIVAL_DRAWS; ?>
      <a href="#traits">DHC Fighter traits</a> are yours when you sign in, on better odds than an
      ordinary drop, and
      <?php echo $ix_from !== '' ? $e($ix_from) : 'whoever sent you'; ?> gets
      <?php echo (int) REF_INVITER_DRAWS; ?> for bringing you in.
      <a href="#traits">What is a DHC Fighter trait?</a></span>
    </div>
<?php endif; ?>
<?php
/*
 * AN INVITED ARRIVAL GETS THE BUTTON FIRST.
 *
 * They have already been sold -- somebody they know sent them, and the
 * ribbon above says what is waiting. Making them read the pitch before
 * they can see the button puts the one thing they came to do below the
 * fold on a phone. Anybody else gets the pitch first, because for them
 * the button is a question they have not been asked yet.
 */
if ($ix_invited) { $ix_button(); echo '<p class="ix-cta-note">' . $ix_note . '</p>'; }
?>
    <p class="ix-pitch">Free staking and about a dozen games<?php
      if ($ix_projects > 0) echo ', shared by ' . (int) $ix_projects . ' NFT projects'; ?>.
    Your collection earns points every night it is staked. Spend them on missions, realms, raids,
    the store, and games that use your NFTs as the pieces.</p>
<?php if (!$ix_invited) { $ix_button(); echo '<p class="ix-cta-note">' . $ix_note . '</p>'; } ?>
<?php if ($ix_stats): ?>
    <ul class="ix-stats">
<?php foreach ($ix_stats as $s): ?>
      <li><b><?php echo $e($s[0]); ?></b><span><?php echo $e($s[1]); ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </div>
</section>

<?php if ($ix_invited) $ix_traits(); ?>

<section class="ix-sec">
  <div class="ix-wrap">
    <h2 class="ix-h2">Staking is where it starts, not where it stops</h2>
    <p class="ix-lead">Most staking stops at a number going up. Here is what the points are for.</p>
    <div class="ix-grid ix-two">
<?php foreach ($ix_platform as $p): ?>
      <article class="ix-card">
        <img src="images/screenshots/<?php echo $e($p[0]); ?>" alt="<?php echo $e($p[1]); ?> on Skulliance" loading="lazy" decoding="async">
        <div class="ix-card-b">
          <h3><?php echo $e($p[1]); ?></h3>
          <p><?php echo $e($p[2]); ?></p>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="ix-sec" id="games">
  <div class="ix-wrap">
    <h2 class="ix-h2">And then there are the games</h2>
    <p class="ix-lead">All free, all built here, most of them played with the NFTs you already
    own. Skull Swap and Monstrocity need no account. Play them right now.</p>
    <div class="ix-grid">
<?php foreach ($ix_games as $g): ?>
      <article class="ix-card">
        <div class="ix-card-i">
          <img src="images/<?php echo $e($g[0]); ?>" alt="<?php echo $e($g[1]); ?>" loading="lazy" decoding="async">
<?php if ($g[3]): ?>
          <span class="ix-free">No account needed</span>
<?php endif; ?>
        </div>
        <div class="ix-card-b">
          <h3><?php echo $e($g[1]); ?></h3>
          <p><?php echo $e($g[2]); ?></p>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="ix-sec">
  <div class="ix-wrap">
    <h2 class="ix-h2"><?php echo $ix_projects > 0 ? (int) $ix_projects . ' projects' : 'The projects'; ?>, and the artists behind them</h2>
    <p class="ix-lead">Six artists started Skulliance. Everyone since was invited, and it is still
    artists who bring in the next one.</p>
  </div>
<?php
/*
 * TWO ROWS OF PROJECT ICONS, counter-scrolling.
 *
 * EAGER, NOT LAZY, and that is not an oversight. A marquee tile arrives by
 * transform rather than by layout, so loading="lazy" leaves most of the
 * track permanently blank -- homepage.php has the same note over its own
 * strip. Eager is only affordable because these are the 6-8KB currency
 * icons: the partner flyers are 1-3MB each and 42MB of them behind a
 * 112px tile is what this deliberately is not.
 */
$ix_rows = $ix_icons ? array_chunk($ix_icons, (int) ceil(count($ix_icons) / 2)) : array();
foreach ($ix_rows as $ix_i => $ix_row): if (!$ix_row) continue; ?>
  <div class="ix-marq<?php echo $ix_i % 2 ? ' ix-rev' : ''; ?>">
    <div class="ix-track">
<?php /* The list twice, for a seamless -50% loop. The second pass is
         decoration, so it is hidden from a screen reader. */
      for ($ix_p = 0; $ix_p < 2; $ix_p++):
        foreach ($ix_row as $ic): ?>
      <span class="ix-tile"<?php if ($ix_p) echo ' aria-hidden="true"'; ?>>
        <img src="icons/<?php echo $e($ic['currency']); ?>.png" width="112" height="112"
             alt="<?php echo $ix_p ? '' : $e($ic['name']); ?>" decoding="async">
        <span><?php echo $e($ic['name']); ?></span>
      </span>
<?php   endforeach;
      endfor; ?>
    </div>
  </div>
<?php endforeach; ?>
  <div class="ix-wrap">
    <p class="ix-lead" style="margin-top:22px">Founded by
<?php
$ix_names = array();
foreach ($ix_founders as $f) $ix_names[] = $f[1];
$ix_last = array_pop($ix_names);
$ix_line = implode(', ', $ix_names) . ' and ' . $ix_last;
/* H.Y.P.E. already ends in a stop, and "H.Y.P.E.." is the kind of thing
   that reads as a typo on the one line naming the founders. */
echo $e(substr($ix_line, -1) === '.' ? $ix_line : $ix_line . '.');
?></p>
  </div>
</section>

<?php if (!$ix_invited) $ix_traits(); ?>

<section class="ix-sec">
  <div class="ix-wrap">
    <h2 class="ix-h2">Points are not a token</h2>
    <div class="ix-points">
      <ul>
        <li>Points are kept off chain, in a database. There is no contract.</li>
        <li>They are not a coin, not a token and not tradeable. There is no pool, no listing and no price.</li>
        <li>They have no value anywhere except here, where they buy missions, upgrades, store items and entries.</li>
        <li>Nothing here costs money, and staking never moves your NFTs out of your wallet.</li>
      </ul>
    </div>
  </div>
</section>

<section class="ix-sec ix-end">
  <div class="ix-wrap">
    <h2 class="ix-h2"><?php echo $ix_invited ? 'Your traits are waiting' : 'One click, and your collection is working'; ?></h2>
    <p class="ix-lead">Sign in with Discord, pick the collections you hold, and the first night of
    staking starts tonight.</p>
<?php $ix_button(); ?>
  </div>
</section>

<div class="ix-foot">
  <a href="/staking/skullpaper.php">Skull Paper</a>
  <a href="/#games">Games</a>
  <a href="/shop">Merch</a>
  <a href="https://x.com/skulliance" target="_blank" rel="noopener noreferrer">@skulliance</a>
</div>
</body>
</html>

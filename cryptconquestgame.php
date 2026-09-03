<?php
// CRYPT CONQUEST MARKETING PAGE -- public, deliberately session-independent.
// Mirrors cryptcrawlgame.php's own structure exactly (see that file's own
// header comment for the full rationale): no session_start(), no login
// check, no game state of any kind -- the exact same content renders for
// every visitor, every time. The "Start Conquest" CTA is a real <form>
// POSTing straight to cryptconquest.php (not an AJAX/JS trick against
// anything on this page), landing the visitor in a fresh active run via
// the exact same start_run handling the game's own no-JS fallback uses.
include_once 'db.php';

$cq_canonical = 'https://www.skulliance.io/staking/cryptconquestgame.php';
$cq_og_image  = 'https://www.skulliance.io/staking/images/cryptconquest.png';
$cq_title     = 'Crypt Conquest - Free Solo Regicide-Style Card Game | Play in Your Browser';
$cq_desc      = 'Play Crypt Conquest free - a solo Regicide-style card game where you dethrone all 12 court cards of the Necropolis using nothing but suit powers, Jesters, and one Last Stand. Illustrated entirely in Crypties NFT art, with a monthly 100,000 CARBON leaderboard. Works on mobile, tablet, and desktop. No download, no signup.';
$cq_short     = 'A free browser card game where you dethrone 12 court cards using suit powers, Jesters, and one Last Stand - illustrated in Crypties NFT art, with a monthly CARBON leaderboard. Play on any device - no download.';

// Every court + Animal Companion art key actually used as card art in the
// game -- the exact same pools cryptconquest-render.php itself pulls from,
// so this marquee can never drift out of sync with what's really on the
// board. Auto-assigned from current holdings each render (not a
// hand-curated pool like Crypt Crawl's), so cards are labeled by their
// in-game identity (e.g. "King of Hearts"), not an NFT name.
$cq_art_pools = cryptconquestGetCardArtPools($conn);
$cq_land_cards = [];
foreach (cryptconquestCourtArtKeys() as $cq_key) {
	if (empty($cq_art_pools['enemy'][$cq_key])) continue;
	$cq_suit = substr($cq_key, 0, 1);
	$cq_rank = intval(substr($cq_key, 1));
	$cq_land_cards[] = ['url' => $cq_art_pools['enemy'][$cq_key], 'name' => cryptconquestCardLabel(['type' => 'court', 'suit' => $cq_suit, 'rank' => $cq_rank])];
}
foreach (cryptconquestCompanionArtKeys() as $cq_key) {
	if (empty($cq_art_pools['companion'][$cq_key])) continue;
	$cq_suit = substr($cq_key, 0, 1);
	$cq_land_cards[] = ['url' => $cq_art_pools['companion'][$cq_key], 'name' => cryptconquestCardLabel(['type' => 'companion', 'suit' => $cq_suit, 'rank' => null])];
}
// Repeat the unique card list enough times that a single marquee pass is
// comfortably wider than any real viewport. Conquest only has 16
// identities here (12 court + 4 companions -- the only cards with real
// NFT art), unlike Crypt Crawl's fixed, hand-curated 44-card pool --
// confirmed live that 16 cards' single-pass width (~1846px) can be
// narrower than a wide desktop viewport, which breaks the duplicate-and-
// translate(-50%) seamless-loop technique: once the animation scrolls
// past the real content, blank track shows until it loops. 116px/card
// matches .cq-strip-card's 96px flex-basis + 20px gap; the ~5100px
// target mirrors Crypt Crawl's own real single-pass width (44 * 116px)
// so both marquees read equally dense regardless of screen size.
if (!empty($cq_land_cards)) {
	$cq_card_px = 116;
	$cq_target_px = 5100;
	$cq_repeats = max(1, (int) ceil($cq_target_px / (count($cq_land_cards) * $cq_card_px)));
	$cq_land_cards = array_merge(...array_fill(0, $cq_repeats, $cq_land_cards));
}
?>
<!doctype html>
<html lang="en">
<head>
<title><?php echo htmlspecialchars($cq_title); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="<?php echo htmlspecialchars($cq_desc); ?>">
<meta name="keywords" content="free card game, Regicide card game, solo card game, browser card game, no download card game, roguelike card game, Cardano NFT game, Crypties NFT, court card game">
<meta name="theme-color" content="#07111d">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="<?php echo $cq_canonical; ?>">

<!-- OpenGraph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $cq_canonical; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($cq_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($cq_desc); ?>">
<meta property="og:image" content="<?php echo $cq_og_image; ?>">
<meta property="og:image:alt" content="Crypt Conquest board - a court card enemy facing the player's hand, illustrated in Crypties NFT art">
<meta property="og:locale" content="en_US">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($cq_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($cq_short); ?>">
<meta name="twitter:image" content="<?php echo $cq_og_image; ?>">
<meta name="twitter:image:alt" content="Crypt Conquest board - a court card enemy facing the player's hand, illustrated in Crypties NFT art">

<!-- Schema.org structured data: VideoGame + BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "VideoGame",
      "name": "Crypt Conquest",
      "alternateName": ["Crypt Conquest Card Game", "Crypt Conquest Regicide"],
      "url": "<?php echo $cq_canonical; ?>",
      "image": "<?php echo $cq_og_image; ?>",
      "screenshot": "<?php echo $cq_og_image; ?>",
      "description": "<?php echo $cq_short; ?>",
      "genre": ["Card Game", "Roguelike", "Strategy"],
      "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
      "playMode": "SinglePlayer",
      "applicationCategory": "Game",
      "operatingSystem": "Any",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
      "publisher": { "@type": "Organization", "name": "Skulliance", "url": "https://www.skulliance.io/" },
      "potentialAction": { "@type": "PlayAction", "target": "<?php echo $cq_canonical; ?>" }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
        { "@type": "ListItem", "position": 2, "name": "Crypt Conquest - Free Solo Regicide-Style Card Game", "item": "<?php echo $cq_canonical; ?>" }
      ]
    }
  ]
}
</script>
<style>
html { scroll-behavior: smooth; }
body { background: #07111d; margin: 0; color: #e8eaed; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.55; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
*, *::before, *::after { box-sizing: border-box; }
.cq-wrap { padding: 20px 16px 60px; }

/* Marketing CTAs -- pill-shaped, same technique as cryptcrawlgame.php's
   own .cc-cta/.cc-secondary and skullswap.php's .ss-cta before it, so all
   three marketing pages read as one product line. Deliberately NOT the
   small rectangular .cq-btn style used for actual in-game actions on
   cryptconquest.php itself -- a different button language for a
   different kind of page. Gold instead of Crypt Crawl's teal so the two
   game marketing pages are still visually distinct from each other at a
   glance, matching the gold CARBON/crown accent cryptconquest.php itself
   already uses for its own .cq-btn.gold. */
@keyframes cqBtnSheen { from { transform: translateX(-120%) skewX(-20deg); } to { transform: translateX(220%) skewX(-20deg); } }
.cq-cta {
	position: relative; overflow: hidden; display: inline-block; background: linear-gradient(135deg, #ffcc4d, #ff9900);
	color: #07111d !important; font-weight: 800; font-size: 1.08rem;
	padding: 14px 32px; border: none; border-radius: 999px; cursor: pointer;
	font-family: inherit; text-decoration: none !important;
	box-shadow: 0 6px 20px rgba(255,204,77,.35);
	transition: transform .15s ease, box-shadow .15s ease;
}
.cq-cta:hover, .cq-cta:focus { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(255,204,77,.5); text-decoration: none; color: #07111d !important; }
.cq-cta.cq-secondary { background: transparent; color: #00c8a0 !important; border: 1px solid rgba(0,200,160,.45); box-shadow: none; margin-left: 12px; }
.cq-cta.cq-secondary:hover { background: rgba(0,200,160,.08); }
/* The submit <button> (nested in its own inline-block <form>) and the
   plain <a> beside it default to different baselines -- inline-block's
   default vertical-align:baseline then lines them up on those mismatched
   baselines instead of visually centering them. Force both onto the same
   line-box middle instead. */
#cq-start-conquest-form, .cq-cta { vertical-align: middle; }
#cq-start-conquest-form { display: inline-block; margin: 0; }
@media (hover: hover) and (pointer: fine) {
	.cq-cta:not(.cq-secondary)::after {
		content: ''; position: absolute; top: 0; left: 0; width: 40%; height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent);
		transform: translateX(-120%) skewX(-20deg); pointer-events: none;
	}
	.cq-cta:not(.cq-secondary):hover::after { animation: cqBtnSheen .6s ease; }
}
@media (max-width: 480px) {
	.cq-cta { width: 100%; text-align: center; }
	.cq-cta.cq-secondary { margin-left: 0; margin-top: 10px; }
}

/* Marketing landing -- design language matches cryptcrawlgame.php/
   skullswap.php's own standalone landings: navy base, glow hero,
   accent-bar mechanics list, counter-scrolling icon marquee. Gold/purple
   accents in place of Crypt Crawl's teal so Conquest reads as its own
   "royal court" identity within the same platform look. */
.cq-landing { padding-bottom: 20px; }
.cq-land-wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; box-sizing: border-box; }
.cq-hero-land {
	text-align: center; padding: 48px 20px 44px; margin: 0 -16px 0;
	background: radial-gradient(circle at 50% 0%, rgba(255, 204, 77, 0.16), transparent 60%), linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
	border-bottom: 1px solid rgba(255,255,255,0.08);
}
.cq-title-land { text-transform: uppercase; letter-spacing: 0.04em; font-size: clamp(1.9rem, 4.5vw, 3.2rem); margin: 0 0 0.2em; }
.cq-title-land img { display: inline-block; height: 0.9em; width: auto; vertical-align: -0.12em; margin: 0 0.08em; filter: drop-shadow(0 2px 4px rgba(0,0,0,.45)); }
.cq-subtitle-land { display: block; font-size: clamp(1.05rem, 2.5vw, 1.6rem); font-weight: 600; color: #c7d0d9; }
.cq-lead { max-width: 640px; margin: 14px auto 22px; color: #c7d0d9; font-size: 1.02rem; }
h1 { margin: 0 0 0.5em; }
h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
p { margin: 0 0 1em; }
a { color: #ffcc4d; text-decoration: none; }
a:hover { color: #ffe08a; text-decoration: underline; }
.cq-shot-link { display: block; cursor: pointer; }
.cq-shot-land {
	display: block; width: 100%; max-width: 460px; height: auto; margin: 0 auto 26px;
	border-radius: 14px; border: 1px solid rgba(255,255,255,.15);
	box-shadow: 0 30px 80px rgba(0,0,0,.7), 0 0 0 1px rgba(255,204,77,.1) inset;
	transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.cq-shot-link:hover .cq-shot-land, .cq-shot-link:focus-visible .cq-shot-land {
	transform: translateY(-3px); border-color: rgba(255,204,77,.5);
	box-shadow: 0 36px 90px rgba(0,0,0,.75), 0 0 24px rgba(255,204,77,.25);
}
.cq-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 18px; }
.cq-badge { font-size: .8rem; font-weight: 600; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #c7d0d9; }
.cq-land-section { padding: 40px 0; }
.cq-land-section + .cq-land-section { border-top: 1px solid rgba(255,255,255,.06); }
.cq-land-section h2 { font-size: clamp(1.4rem, 3vw, 2rem); text-align: center; margin: 0 0 .6em; }
.cq-land-section p, .cq-land-section li { color: #c7d0d9; }
.cq-land-center { text-align: center; }
.cq-features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 20px; }
@media (max-width: 560px) { .cq-features { grid-template-columns: 1fr; } }
.cq-feat-card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 22px; }
.cq-feat-card h3 { margin: 0 0 8px; font-size: 1.1rem; color: #ffcc4d; }
.cq-feat-card p { margin: 0; font-size: .96rem; }
/* Two opposite-direction ("dueling") marquee rows of the court + Animal
   Companion art actually on the board -- same technique as
   cryptcrawlgame.php's own strip: each track holds its list twice for the
   seamless -50% translate loop (second pass aria-hidden), reversed
   direction on the second row. */
.cq-strip { width: 100%; overflow: hidden; padding: 14px 0; -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%); mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%); }
.cq-strip-track { display: flex; align-items: flex-start; gap: 20px; width: max-content; animation: cq-strip-scroll 55s linear infinite; will-change: transform; }
.cq-strip-track.cq-reverse { animation-direction: reverse; animation-duration: 62s; }
.cq-strip-track:hover { animation-play-state: paused; }
@keyframes cq-strip-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@media (prefers-reduced-motion: reduce) { .cq-strip-track { animation: none; } }
.cq-strip-card { flex: 0 0 96px; text-align: center; }
.cq-strip-card img { width: 88px; height: 123px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); box-shadow: 0 6px 14px rgba(0,0,0,.6); }
.cq-strip-card .cq-ticker { margin-top: 6px; font-size: .68rem; color: #8a96a3; font-weight: 600; letter-spacing: .03em; }
.cq-mechanics { list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
.cq-mechanics li { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; background: rgba(255,255,255,.03); border-left: 3px solid #ffcc4d; border-radius: 6px; font-size: .95rem; }
.cq-mechanics li strong { color: #ffe08a; }
.cq-mech-emoji { flex-shrink: 0; width: 32px; text-align: center; font-size: 1.5rem; line-height: 1.2; filter: drop-shadow(0 2px 4px rgba(0,0,0,.5)); }
.cq-tips { margin: 16px 0 0; padding-left: 22px; }
.cq-tips li { margin-bottom: 8px; }
.cq-faq details { border-bottom: 1px solid rgba(255,255,255,.08); padding: 14px 0; }
.cq-faq summary { cursor: pointer; font-weight: 700; color: #e8eaed; }
.cq-faq p { margin: 10px 0 0; }
.cq-final { text-align: center; background: linear-gradient(180deg, rgba(255,204,77,.08), transparent); border-radius: 18px; padding: 40px 24px; }
/* Footer -- matches cryptcrawlgame.php's own .cc-footer. No "Go Back"
   button here for the same reason: this is a front door, not a page
   someone drills into. */
.cq-footer { padding: 28px 20px; text-align: center; color: #8a96a3; font-size: .88rem; border-top: 1px solid rgba(255,255,255,.06); }
.cq-footer a { color: #8a96a3; }
</style>
</head>
<body>
<div class="cq-wrap">
<div class="cq-landing">
	<header class="cq-hero-land">
		<a class="cq-shot-link" href="#" onclick="document.getElementById('cq-start-conquest-form').requestSubmit(); return false;" aria-label="Play Crypt Conquest now">
			<img class="cq-shot-land" src="/staking/images/cryptconquest.png" alt="Crypt Conquest board - a court card enemy facing the player's hand, illustrated in Crypties NFT art" loading="eager" fetchpriority="high" decoding="async">
		</a>
		<h1><span class="cq-title-land"><img src="/staking/pwa/skulliance-logo-icon.png" alt="">Crypt Conquest<img src="/staking/pwa/skulliance-logo-icon.png" alt=""></span><span class="cq-subtitle-land">Free Solo Regicide-Style Card Game</span></h1>
		<p class="cq-lead">Dethrone all 12 court cards of the Necropolis alone - no HP bar, just a hand you have to make last. Clubs hit harder, Hearts heal, Diamonds draw, Spades shield, and one Last Stand waits for the run that would otherwise end. Every court card and Animal Companion is real Crypties NFT art. No download, no signup - just play.</p>
		<form method="post" action="cryptconquest.php" id="cq-start-conquest-form"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cq-cta">👑 Start Conquest</button>
		</form>
		<a class="cq-cta cq-secondary" href="#cq-how-it-works">How It Works</a>
		<div class="cq-badges" aria-label="Game highlights">
			<span class="cq-badge">100% Free</span>
			<span class="cq-badge">No Download</span>
			<span class="cq-badge">No Signup</span>
			<span class="cq-badge">Mobile &amp; Desktop</span>
		</div>
	</header>

	<section class="cq-land-section" id="cq-how-it-works">
		<div class="cq-land-wrap">
			<h2>A Regicide-Style Card Game With No HP Bar</h2>
			<div class="cq-features">
				<div class="cq-feat-card">
					<h3>🃏 Suit Powers, Not Stats</h3>
					<p>Clubs double your damage, Hearts heal cards back from the discard, Diamonds draw you fresh cards, and Spades shield you from the enemy's next hit. Read the board, not a stat sheet.</p>
				</div>
				<div class="cq-feat-card">
					<h3>🎭 Two Jesters</h3>
					<p>Twice a run, discard your whole hand and refill it on demand - a reset valve for the moment your hand stops working, whenever you decide to spend it.</p>
				</div>
				<div class="cq-feat-card">
					<h3>🛡️ One Last Stand</h3>
					<p>The first time your whole hand still can't cover the damage, you refuse to fall instead of losing - once per run, automatic, no button to remember.</p>
				</div>
				<div class="cq-feat-card">
					<h3>👑 CARBON &amp; a Monthly Leaderboard</h3>
					<p>Every completed run pays out CARBON on the spot, win or lose. Log in and your best runs climb the leaderboard for a share of a 100,000 CARBON pool every month.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="cq-land-section">
		<div class="cq-land-wrap cq-land-center">
			<h2>The Whole Court Is Real Crypties NFT Art</h2>
			<p>Every King, Queen, Jack, and Animal Companion on the board is a real Crypties NFT, not a generic icon - the exact same collectible dead things on Cardano that illustrate Crypt Crawl, showing a different slice of the collection here.</p>
		</div>
		<?php
		$cq_strip_rows = [
			['cards' => $cq_land_cards, 'class' => ''],
			['cards' => array_reverse($cq_land_cards), 'class' => ' cq-reverse'],
		];
		foreach ($cq_strip_rows as $cq_row): if (empty($cq_row['cards'])) continue; ?>
		<div class="cq-strip">
			<div class="cq-strip-track<?php echo $cq_row['class']; ?>">
				<?php for ($cq_pass = 0; $cq_pass < 2; $cq_pass++):
					foreach ($cq_row['cards'] as $cq_card): ?>
				<div class="cq-strip-card"<?php if ($cq_pass) echo ' aria-hidden="true"'; ?>>
					<img src="<?php echo htmlspecialchars($cq_card['url']); ?>"
						alt="<?php echo $cq_pass ? '' : htmlspecialchars($cq_card['name']); ?>"
						loading="lazy" decoding="async" onerror="this.closest('.cq-strip-card').style.display='none';">
					<div class="cq-ticker"><?php echo htmlspecialchars($cq_card['name']); ?></div>
				</div>
				<?php endforeach; endfor; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</section>

	<section class="cq-land-section">
		<div class="cq-land-wrap">
			<h2>How a Conquest Works</h2>
			<ul class="cq-mechanics">
				<li><span class="cq-mech-emoji" aria-hidden="true">🔄</span><span><strong>Your hand only shrinks</strong> - it never refills at the end of a turn. Cards you play AND cards you discard to cover damage are both gone for good, so your hand is effectively your health bar.</span></li>
				<li><span class="cq-mech-emoji" aria-hidden="true">♣️</span><span><strong>Clubs double your attack</strong> - play a Club into a hit and the whole total is doubled against the current court card.</span></li>
				<li><span class="cq-mech-emoji" aria-hidden="true">♥️</span><span><strong>Hearts heal your discard pile</strong> - random cards move from the discard to the <em>bottom</em> of the deck. Not into your hand: it stops the deck running dry rather than helping you this turn.</span></li>
				<li><span class="cq-mech-emoji" aria-hidden="true">♦️</span><span><strong>Diamonds draw you cards</strong> - straight into your hand, off the attack you just made. The only routine way to refill, which makes it a lifeline rather than a bonus. Draws stop at the 8-card hand limit, so a big Diamond played on a full hand is mostly wasted.</span></li>
				<li><span class="cq-mech-emoji" aria-hidden="true">♠️</span><span><strong>Spades shield you</strong> - blunt the court card's next attack before it ever reaches your hand.</span></li>
				<li><span class="cq-mech-emoji" aria-hidden="true">🛡️</span><span><strong>Perfect Guard</strong> - cover an incoming hit <em>exactly</em> and your two highest-value cards come straight back to your hand. A 2-card exact match costs you nothing at all; overpaying by a single point returns nothing.</span></li>
				<li><span class="cq-mech-emoji" aria-hidden="true">👑</span><span><strong>Defeat all 12 court cards</strong> - four Jacks, four Queens, four Kings - to conquer the Necropolis. Exact-damage kills recover the card face-down atop your deck instead of losing it to the discard.</span></li>
			</ul>
		</div>
	</section>

	<section class="cq-land-section">
		<div class="cq-land-wrap">
			<h2>Think Like a Claimant to the Throne</h2>
			<ol class="cq-tips">
				<li><strong>Covering damage only needs to reach the total, not match it exactly.</strong> Discard the fewest cards you can spare - a natural instinct is to hunt for a clean number using more cards, but every card left in your hand is worth more than a tidy total.</li>
				<li><strong>Hold big Diamonds until your hand is thin.</strong> Draws stop at 8 cards and the rest are forfeited, so a 9 of Diamonds played on a full hand does the work of a 1.</li>
				<li><strong>Cover the number exactly whenever you can.</strong> Overpaying by one point loses everything you spent; hitting it exactly hands your two best cards back.</li>
				<li><strong>Attack for the power you need, not just the biggest number.</strong> A small Diamond when your hand is thin is worth more than a big off-suit card that doesn't do anything useful right now - save your high cards for when a court card actually demands them.</li>
				<li><strong>Save a Jester for when your hand truly stalls.</strong> You only get two, and a full discard-and-refill is worth more mid-crisis than spent early out of habit.</li>
				<li><strong>Don't rely on Last Stand as a plan.</strong> It only fires once - treat it as the emergency it is, not a second life you can play around.</li>
				<li><strong>Spades before a hard court card.</strong> Shielding ahead of a King's attack is worth more than the same cards spent chasing extra damage.</li>
				<li><strong>Animal Companions are flexible, not free.</strong> They're always worth 1 and can pair with a single other card, but they can't join a bigger combo - spend them on the turn that actually needs the flexibility.</li>
			</ol>
		</div>
	</section>

	<section class="cq-land-section">
		<div class="cq-land-wrap cq-faq">
			<h2>Crypt Conquest FAQ</h2>
			<details>
				<summary>Is Crypt Conquest really free to play?</summary>
				<p>Yes - completely free. Open the page and play, no purchase or signup required.</p>
			</details>
			<details>
				<summary>Do I need an account to play?</summary>
				<p>No account is needed for casual play. Log in through Skulliance with Discord if you want your runs saved, counted toward the leaderboard, and paid out in CARBON - but it's never required to enjoy the game.</p>
			</details>
			<details>
				<summary>Does Crypt Conquest work on mobile?</summary>
				<p>Yes. The whole board is touch-friendly and resizes for phones and tablets - it plays just as well on mobile as on desktop.</p>
			</details>
			<details>
				<summary>What happens if I lose?</summary>
				<p>Failing to cover a court card's attack even with your whole hand - after Last Stand is spent - ends the run as a loss. Either way, whatever CARBON you'd earned that run still pays out, and you can start a fresh conquest immediately.</p>
			</details>
			<details>
				<summary>Where does the card art come from?</summary>
				<p>Every court card and Animal Companion is drawn from real Crypties NFTs - the same OG collectible dead things on Cardano that illustrate Crypt Crawl, just a different slice of the collection here.</p>
			</details>
		</div>
	</section>

	<section class="cq-land-section">
		<div class="cq-land-wrap">
			<div class="cq-final">
				<h2>Ready to Take the Throne?</h2>
				<p>The court is seated and waiting. No download. No signup. Just play.</p>
				<a href="#" class="cq-cta" onclick="document.getElementById('cq-start-conquest-form').requestSubmit(); return false;">👑 Start Conquest</a>
			</div>
		</div>
	</section>

	<footer class="cq-footer">
		<p>&copy; Skulliance &middot; Crypt Conquest is a free browser-based solo Regicide-style card game. <a href="https://www.skulliance.io/">Visit Skulliance</a></p>
	</footer>
</div>
</div>
</body>
</html>

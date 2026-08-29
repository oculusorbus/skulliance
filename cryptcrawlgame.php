<?php
// CRYPT CRAWL MARKETING PAGE -- public, deliberately session-independent.
//
// Split out from cryptcrawl.php (the actual game) specifically so this
// content can never be affected by a player's session/game state again --
// see MAINTENANCE.md for the investigation that led here (a report that
// visitors were landing straight in the game instead of this page, which
// turned out very likely to be a deploy-timing/caching artifact rather
// than an application bug, but the user asked for a structural fix that
// rules the whole class of issue out for good rather than continuing to
// chase it). This page does no session_start(), no login check, no game
// state of any kind -- the exact same content renders for every visitor,
// every time, full stop.
//
// The "Start Delve" CTA is a real <form> POSTing straight to
// cryptcrawl.php (not an AJAX/JS trick against anything on this page) --
// landing the visitor in a fresh active delve via the exact same
// start_run handling the game's own no-JS fallback already uses.
include_once 'db.php';

$cc_canonical = 'https://www.skulliance.io/staking/cryptcrawlgame.php';
$cc_og_image  = 'https://www.skulliance.io/staking/images/cryptcrawl.png';
$cc_title     = 'Crypt Crawl - Free Solo Dungeon Card Game | Play in Your Browser';
$cc_desc      = 'Play Crypt Crawl free - a solo dungeon-delve card game with a 44-card deck illustrated entirely in Crypties NFT art, a Last Stand save, and a weekly CARBON leaderboard. Works on mobile, tablet, and desktop. No download, no signup.';
$cc_short     = 'A free browser dungeon-delve card game illustrated in Crypties NFT art, with a Last Stand save and a weekly CARBON leaderboard. Play on any device - no download.';

// Every Crypties NFT actually used as card art in the game -- the exact
// same lookup cryptcrawl.php itself uses, so the marquee below can never
// drift out of sync with what's really in the deck.
$cc_land_art = cryptcrawlGetCardArt($conn);
?>
<!doctype html>
<html lang="en">
<head>
<title><?php echo htmlspecialchars($cc_title); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="<?php echo htmlspecialchars($cc_desc); ?>">
<meta name="keywords" content="free card game, solo dungeon crawler, roguelike card game, browser card game, no download card game, free rogue-like game, Cardano NFT game, Crypties NFT, dungeon delve card game">
<meta name="theme-color" content="#07111d">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="<?php echo $cc_canonical; ?>">

<!-- OpenGraph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $cc_canonical; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($cc_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($cc_desc); ?>">
<meta property="og:image" content="<?php echo $cc_og_image; ?>">
<meta property="og:image:alt" content="Crypt Crawl dungeon-delve card game board, illustrated in Crypties NFT art">
<meta property="og:locale" content="en_US">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($cc_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($cc_short); ?>">
<meta name="twitter:image" content="<?php echo $cc_og_image; ?>">
<meta name="twitter:image:alt" content="Crypt Crawl dungeon-delve card game board, illustrated in Crypties NFT art">

<!-- Schema.org structured data: VideoGame + BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "VideoGame",
      "name": "Crypt Crawl",
      "alternateName": ["Crypt Crawl Card Game", "Crypt Crawl Dungeon Delve"],
      "url": "<?php echo $cc_canonical; ?>",
      "image": "<?php echo $cc_og_image; ?>",
      "screenshot": "<?php echo $cc_og_image; ?>",
      "description": "<?php echo $cc_short; ?>",
      "genre": ["Card Game", "Roguelike", "Dungeon Crawler"],
      "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
      "playMode": "SinglePlayer",
      "applicationCategory": "Game",
      "operatingSystem": "Any",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
      "publisher": { "@type": "Organization", "name": "Skulliance", "url": "https://www.skulliance.io/" },
      "potentialAction": { "@type": "PlayAction", "target": "<?php echo $cc_canonical; ?>" }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
        { "@type": "ListItem", "position": 2, "name": "Crypt Crawl - Free Solo Dungeon Card Game", "item": "<?php echo $cc_canonical; ?>" }
      ]
    }
  ]
}
</script>
<style>
html { scroll-behavior: smooth; }
body { background: #07111d; margin: 0; color: #e8eaed; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.55; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
*, *::before, *::after { box-sizing: border-box; }
.cc-wrap { padding: 20px 16px 60px; }

/* Buttons -- same look as the actual game (cryptcrawl.php), so the CTA
   here and the ones you land on after clicking it feel like one product. */
@keyframes ccBtnSheen { from { transform: translateX(-120%) skewX(-20deg); } to { transform: translateX(220%) skewX(-20deg); } }
.cc-btn {
	position: relative; overflow: hidden; background: #00c8a0; color: #012; border: 1px solid transparent; border-radius: 6px;
	padding: 7px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; box-sizing: border-box;
	width: 100%; min-height: 48px; display: flex; align-items: center; justify-content: center; text-align: center;
	transition: transform .12s ease, filter .12s ease, box-shadow .2s ease;
}
.cc-btn:hover:not(:disabled) { filter: brightness(1.12); box-shadow: 0 6px 16px rgba(0,200,160,.35); }
.cc-btn:active:not(:disabled) { transform: scale(.95); }
.cc-btn.secondary { background: rgba(255,255,255,.08); color: #e8f2f8; border-color: rgba(255,255,255,.3); }
.cc-btn.secondary:hover:not(:disabled) { background: rgba(255,255,255,.15); box-shadow: 0 6px 16px rgba(255,255,255,.12); }
@media (hover: hover) and (pointer: fine) {
	.cc-btn:not(:disabled)::after {
		content: ''; position: absolute; top: 0; left: 0; width: 40%; height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
		transform: translateX(-120%) skewX(-20deg); pointer-events: none;
	}
	.cc-btn:not(:disabled):hover::after { animation: ccBtnSheen .6s ease; }
}

/* Marketing landing -- design language matches skullswap.php's own
   standalone landing: brand green-teal accents, navy base, glow hero,
   accent-bar mechanics list, counter-scrolling icon marquee. */
.cc-landing { padding-bottom: 20px; }
.cc-land-wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; box-sizing: border-box; }
.cc-hero-land {
	text-align: center; padding: 48px 20px 44px; margin: 0 -16px 0;
	background: radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%), linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
	border-bottom: 1px solid rgba(255,255,255,0.08);
}
.cc-title-land { text-transform: uppercase; letter-spacing: 0.04em; font-size: clamp(1.9rem, 4.5vw, 3.2rem); margin: 0 0 0.2em; }
.cc-title-land img { display: inline-block; height: 0.9em; width: auto; vertical-align: -0.12em; margin: 0 0.08em; filter: drop-shadow(0 2px 4px rgba(0,0,0,.45)); }
.cc-subtitle-land { display: block; font-size: clamp(1.05rem, 2.5vw, 1.6rem); font-weight: 600; color: #c7d0d9; }
.cc-lead { max-width: 640px; margin: 14px auto 22px; color: #c7d0d9; font-size: 1.02rem; }
h1 { margin: 0 0 0.5em; }
h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
p { margin: 0 0 1em; }
a { color: #00c8a0; text-decoration: none; }
a:hover { color: #34e3bb; text-decoration: underline; }
.cc-shot-link { display: block; cursor: pointer; }
.cc-shot-land {
	display: block; width: 100%; max-width: 460px; height: auto; margin: 0 auto 26px;
	border-radius: 14px; border: 1px solid rgba(255,255,255,.15);
	box-shadow: 0 30px 80px rgba(0,0,0,.7), 0 0 0 1px rgba(0,200,160,.1) inset;
	transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.cc-shot-link:hover .cc-shot-land, .cc-shot-link:focus-visible .cc-shot-land {
	transform: translateY(-3px); border-color: rgba(0,200,160,.5);
	box-shadow: 0 36px 90px rgba(0,0,0,.75), 0 0 24px rgba(0,200,160,.25);
}
.cc-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 18px; }
.cc-badge { font-size: .8rem; font-weight: 600; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #c7d0d9; }
.cc-land-section { padding: 40px 0; }
.cc-land-section + .cc-land-section { border-top: 1px solid rgba(255,255,255,.06); }
.cc-land-section h2 { font-size: clamp(1.4rem, 3vw, 2rem); text-align: center; margin: 0 0 .6em; }
.cc-land-section p, .cc-land-section li { color: #c7d0d9; }
.cc-land-center { text-align: center; }
.cc-features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 20px; }
@media (max-width: 560px) { .cc-features { grid-template-columns: 1fr; } }
.cc-feat-card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 22px; }
.cc-feat-card h3 { margin: 0 0 8px; font-size: 1.1rem; color: #00c8a0; }
.cc-feat-card p { margin: 0; font-size: .96rem; }
/* Two opposite-direction ("dueling") marquee rows of every Crypties NFT
   used as card art in the game. Same technique as skullswap.php's icon
   strip: duplicated track sliding -50%, reversed direction on the second
   row, pause on hover, edge fade masks. */
.cc-strip { width: 100%; overflow: hidden; padding: 14px 0; -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%); mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%); }
.cc-strip-track { display: flex; align-items: flex-start; gap: 20px; width: max-content; animation: cc-strip-scroll 55s linear infinite; will-change: transform; }
.cc-strip-track.cc-reverse { animation-direction: reverse; animation-duration: 62s; }
.cc-strip-track:hover { animation-play-state: paused; }
@keyframes cc-strip-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@media (prefers-reduced-motion: reduce) { .cc-strip-track { animation: none; } }
.cc-strip-card { flex: 0 0 96px; text-align: center; }
.cc-strip-card img { width: 88px; height: 123px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); box-shadow: 0 6px 14px rgba(0,0,0,.6); }
.cc-strip-card .cc-ticker { margin-top: 6px; font-size: .68rem; color: #8a96a3; font-weight: 600; letter-spacing: .03em; }
.cc-mechanics { list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
.cc-mechanics li { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; background: rgba(255,255,255,.03); border-left: 3px solid #00c8a0; border-radius: 6px; font-size: .95rem; }
.cc-mechanics li strong { color: #34e3bb; }
.cc-mech-emoji { flex-shrink: 0; width: 32px; text-align: center; font-size: 1.5rem; line-height: 1.2; filter: drop-shadow(0 2px 4px rgba(0,0,0,.5)); }
.cc-tips { margin: 16px 0 0; padding-left: 22px; }
.cc-tips li { margin-bottom: 8px; }
.cc-faq details { border-bottom: 1px solid rgba(255,255,255,.08); padding: 14px 0; }
.cc-faq summary { cursor: pointer; font-weight: 700; color: #e8eaed; }
.cc-faq p { margin: 10px 0 0; }
.cc-final { text-align: center; background: linear-gradient(180deg, rgba(0,200,160,.08), transparent); border-radius: 18px; padding: 40px 24px; }
.cc-go-back-row { margin-top: 10px; }
.cc-go-back-row .cc-btn { width: 100%; }
</style>
</head>
<body>
<div class="cc-wrap">
<div class="cc-landing">
	<header class="cc-hero-land">
		<a class="cc-shot-link" href="#" onclick="document.getElementById('cc-start-delve-form').requestSubmit(); return false;" aria-label="Play Crypt Crawl now">
			<img class="cc-shot-land" src="/staking/images/cryptcrawl.png" alt="Crypt Crawl gameplay - a dungeon room of four cards illustrated in Crypties NFT art" loading="eager" fetchpriority="high" decoding="async">
		</a>
		<h1><span class="cc-title-land"><img src="/staking/pwa/skulliance-logo-icon.png" alt="">Crypt Crawl<img src="/staking/pwa/skulliance-logo-icon.png" alt=""></span><span class="cc-subtitle-land">Free Solo Dungeon Card Game</span></h1>
		<p class="cc-lead">Delve a 44-card crypt deck alone - weapons that wear down, medkits that diminish, monsters that hit back, and one guaranteed Last Stand when it matters most. Every card is a real Crypties NFT. No download, no signup - just play.</p>
		<form method="post" action="cryptcrawl.php" id="cc-start-delve-form"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Start Delve</button>
		</form>
		<p><a href="#cc-how-it-works">How It Works</a></p>
		<div class="cc-badges" aria-label="Game highlights">
			<span class="cc-badge">100% Free</span>
			<span class="cc-badge">No Download</span>
			<span class="cc-badge">No Signup</span>
			<span class="cc-badge">Mobile &amp; Desktop</span>
		</div>
	</header>

	<section class="cc-land-section" id="cc-how-it-works">
		<div class="cc-land-wrap">
			<h2>A Card Game With Teeth</h2>
			<div class="cc-features">
				<div class="cc-feat-card">
					<h3>🛡️ Last Stand</h3>
					<p>The first hit that would take you to 0 HP in a delve doesn't - it leaves you standing at 1 instead. Once per delve, automatic, no button to remember. The one guaranteed save for a genuinely bad stretch.</p>
				</div>
				<div class="cc-feat-card">
					<h3>⚔️ Weapons That Wear Down</h3>
					<p>Equip a weapon and it degrades with every kill - it can only beat enemies at or below the rank of the one it just fought. Save it for the fight that actually needs it.</p>
				</div>
				<div class="cc-feat-card">
					<h3>🃏 44 Unique Crypties Cards</h3>
					<p>Every card in the deck is hand-assigned to a real Crypties NFT, not a shuffled art pool - the toughest enemies carry the rarest pieces in the collection.</p>
				</div>
				<div class="cc-feat-card">
					<h3>🏆 CARBON &amp; a Weekly Leaderboard</h3>
					<p>Every card you resolve earns CARBON that pays out the moment your delve ends, win or lose. Log in and your best runs climb the weekly leaderboard for a share of a 50,000 CARBON pool.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="cc-land-section">
		<div class="cc-land-wrap cc-land-center">
			<h2>Every Card Is a Real Crypties NFT</h2>
			<p>Crypties are the OG collectible dead things on Cardano - and the entire 44-card deck is illustrated in their art, not generic icons. The toughest monsters in the crypt carry the rarest pieces in the collection.</p>
		</div>
		<?php
		// Two opposite-direction ("dueling") marquee rows covering every
		// Crypties NFT actually used as card art in the game (see
		// CRYPTCRAWL_CARD_ART in db.php) -- same technique as
		// skullswap.php's own icon strip: each track holds its list twice
		// for the seamless -50% translate loop (second pass aria-hidden),
		// reversed direction on the second row.
		$cc_land_cards = [];
		foreach (CRYPTCRAWL_CARD_ART as $cc_key => $cc_nft_name) {
			if (!isset($cc_land_art[$cc_key])) continue;
			$cc_land_cards[] = ['url' => $cc_land_art[$cc_key], 'name' => $cc_nft_name];
		}
		$cc_strip_rows = [
			['cards' => $cc_land_cards, 'class' => ''],
			['cards' => array_reverse($cc_land_cards), 'class' => ' cc-reverse'],
		];
		foreach ($cc_strip_rows as $cc_row): if (empty($cc_row['cards'])) continue; ?>
		<div class="cc-strip">
			<div class="cc-strip-track<?php echo $cc_row['class']; ?>">
				<?php for ($cc_pass = 0; $cc_pass < 2; $cc_pass++):
					foreach ($cc_row['cards'] as $cc_card): ?>
				<div class="cc-strip-card"<?php if ($cc_pass) echo ' aria-hidden="true"'; ?>>
					<img src="<?php echo htmlspecialchars($cc_card['url']); ?>"
						alt="<?php echo $cc_pass ? '' : htmlspecialchars($cc_card['name']); ?>"
						loading="lazy" decoding="async" onerror="this.closest('.cc-strip-card').style.display='none';">
					<div class="cc-ticker"><?php echo htmlspecialchars($cc_card['name']); ?></div>
				</div>
				<?php endforeach; endfor; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</section>

	<section class="cc-land-section">
		<div class="cc-land-wrap">
			<h2>How a Crypt Works</h2>
			<ul class="cc-mechanics">
				<li><span class="cc-mech-emoji" aria-hidden="true">♦️</span><span><strong>Diamonds (2-10) are weapons</strong> - equip one and it stays until you use it, degrading so it can only beat weaker enemies after each kill.</span></li>
				<li><span class="cc-mech-emoji" aria-hidden="true">♥️</span><span><strong>Hearts (2-10) are medkits</strong> - the first one you use each crypt heals in full; any more in the same crypt still heal, just for half.</span></li>
				<li><span class="cc-mech-emoji" aria-hidden="true">♣️</span><span><strong>Clubs &amp; Spades (2-14) are enemies</strong> - fight bare-handed and take full damage, or spend your weapon and take only the difference.</span></li>
				<li><span class="cc-mech-emoji" aria-hidden="true">🏃</span><span><strong>Resolve 3 of 4 cards</strong> in a crypt and the last one carries into the next; or flee a fresh crypt once (not twice in a row) to reshuffle it back into the deck.</span></li>
			</ul>
		</div>
	</section>

	<section class="cc-land-section">
		<div class="cc-land-wrap">
			<h2>Think Like a Survivor</h2>
			<ol class="cc-tips">
				<li><strong>Save your weapon for the fight that needs it.</strong> It only degrades further once you use it - a fresh weapon on a weak enemy is a wasted edge later.</li>
				<li><strong>Don't burn every medkit in one crypt.</strong> Only the first heals in full; spacing them across crypts is worth more than hoarding them into one.</li>
				<li><strong>Flee when the room is genuinely bad.</strong> You only get it once between touches - use it on a crypt that would actually cost you Last Stand, not a mild inconvenience.</li>
				<li><strong>Watch the music.</strong> The ambient track shifts to Frantic, then Doom, when a crypt has become genuinely unsurvivable even played perfectly - your ears will know before the HP bar makes it obvious.</li>
			</ol>
		</div>
	</section>

	<section class="cc-land-section">
		<div class="cc-land-wrap cc-faq">
			<h2>Crypt Crawl FAQ</h2>
			<details>
				<summary>Is Crypt Crawl really free to play?</summary>
				<p>Yes - completely free. Open the page and play, no purchase or signup required.</p>
			</details>
			<details>
				<summary>Do I need an account to play?</summary>
				<p>No account is needed for casual play. Log in through Skulliance with Discord if you want your runs saved, counted toward the leaderboard, and paid out in CARBON - but it's never required to enjoy the game.</p>
			</details>
			<details>
				<summary>Does Crypt Crawl work on mobile?</summary>
				<p>Yes. The whole board is touch-friendly and resizes for phones and tablets - it plays just as well on mobile as on desktop.</p>
			</details>
			<details>
				<summary>What happens if I lose?</summary>
				<p>Running out of HP (after Last Stand is spent) ends the delve as a loss - the same as giving up mid-delve. Either way, whatever CARBON you'd earned that run still pays out, and you can start a fresh delve immediately.</p>
			</details>
			<details>
				<summary>Where does the card art come from?</summary>
				<p>Every card is hand-assigned to a specific Crypties NFT - the OG collectible dead things on Cardano - not a random or shuffled pool. The rarest pieces in the collection are reserved for the toughest enemies in the deck.</p>
			</details>
		</div>
	</section>

	<section class="cc-land-section">
		<div class="cc-land-wrap">
			<div class="cc-final">
				<h2>Ready to Delve?</h2>
				<p>The deck is shuffled and waiting. No download. No signup. Just play.</p>
				<a href="#" class="cc-btn" onclick="document.getElementById('cc-start-delve-form').requestSubmit(); return false;">💀 Start Delve</a>
			</div>
			<div class="cc-go-back-row"><a href="#" class="cc-btn secondary" data-go-back="1">↩️ Go Back</a></div>
		</div>
	</section>
</div>
</div>
<script>
(function() {
	// "Go Back" -- same hostname-based same-site check as cryptcrawl.php
	// (see that file for why: a plain origin string-prefix match breaks
	// for a referrer from the bare skulliance.io domain vs this site's
	// www.skulliance.io origin, even though they're the same site).
	function ccIsSameSite(url) {
		try {
			var refHost = new URL(url).hostname.replace(/^www\./, '');
			var curHost = window.location.hostname.replace(/^www\./, '');
			return refHost === curHost;
		} catch (e) {
			return false;
		}
	}
	document.addEventListener('click', function(e) {
		var btn = e.target.closest ? e.target.closest('[data-go-back]') : null;
		if (!btn) return;
		e.preventDefault();
		if (document.referrer && ccIsSameSite(document.referrer) && window.history.length > 1) {
			window.history.back();
		} else {
			window.location.href = 'https://www.skulliance.io/';
		}
	});
})();
</script>
</body>
</html>

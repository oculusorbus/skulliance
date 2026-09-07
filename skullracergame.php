<?php
// SKULL RACER MARKETING PAGE -- public, deliberately session-independent.
//
// Same split cryptcrawlgame.php/cryptconquestgame.php already use: the
// *game.php file is the public front door that nav points at, and the
// bare <game>.php beside it is the thing you actually play. Here that's
// skullracer.php, which wraps racing/index.html in the site header.
//
// Goes one step further than those two and includes NOTHING -- not even
// db.php. Crypt Crawl's landing needs db.php to look up its card art;
// this page has no dynamic content at all, so there is nothing here that
// can be affected by a player's session, login state, or game state, and
// nothing that can fail if the database is down. The exact same bytes
// render for every visitor, every time.
//
// The CTA is a plain relative link to skullracer.php. Relative on
// purpose: the login cookie on this platform is host-only (no domain
// param), so www.skulliance.io and skulliance.io do NOT share a session
// -- hardcoding either host here would silently log a visitor out of the
// game they just clicked into. See MAINTENANCE.md's own note on that.
$sr_canonical = 'https://www.skulliance.io/staking/skullracergame.php';
// Real in-game screenshot, not the box art -- it's the hero here AND the
// social/search preview, since what the game actually looks like in motion
// sells it better than packaging does. The box art still gets its own
// section further down, where it's the actual subject.
$sr_og_image  = 'https://www.skulliance.io/staking/racing/images/screenshot.png';
$sr_title     = 'Skull Racer - Free Retro Arcade Highway Racing Game | Play in Your Browser';
$sr_desc      = 'Play Skull Racer free - a pseudo-3D arcade highway racer with boost pads, jump ramps, ghost cars of the current record holders, gamepad support, and a weekly CARBON leaderboard. Works on mobile, tablet, and desktop. No download, no signup.';
$sr_short     = 'A free browser arcade highway racer with boost pads, jump ramps, ghost cars of the record holders and a weekly CARBON leaderboard. Play on any device - no download.';
?>
<!doctype html>
<html lang="en">
<head>
<title><?php echo htmlspecialchars($sr_title); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="<?php echo htmlspecialchars($sr_desc); ?>">
<meta name="keywords" content="free racing game, arcade racer, retro racing game, browser racing game, no download racing game, pseudo 3D racer, outrun style game, ghost car racing, mobile racing game, gamepad browser game">
<meta name="theme-color" content="#07111d">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="<?php echo $sr_canonical; ?>">

<!-- OpenGraph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $sr_canonical; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($sr_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($sr_desc); ?>">
<meta property="og:image" content="<?php echo $sr_og_image; ?>">
<meta property="og:image:alt" content="Skull Racer gameplay - racing a night desert highway under a full moon, traffic ahead and lap times on the HUD">
<meta property="og:locale" content="en_US">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($sr_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($sr_short); ?>">
<meta name="twitter:image" content="<?php echo $sr_og_image; ?>">
<meta name="twitter:image:alt" content="Skull Racer gameplay - racing a night desert highway under a full moon, traffic ahead and lap times on the HUD">

<!-- Schema.org structured data: VideoGame + BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "VideoGame",
      "name": "Skull Racer",
      "alternateName": ["Skull Racer Arcade Racing", "Skull Racer Highway Racer"],
      "url": "<?php echo $sr_canonical; ?>",
      "image": "<?php echo $sr_og_image; ?>",
      "screenshot": "<?php echo $sr_og_image; ?>",
      "description": "<?php echo $sr_short; ?>",
      "genre": ["Racing", "Arcade"],
      "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
      "playMode": "SinglePlayer",
      "applicationCategory": "Game",
      "operatingSystem": "Any",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
      "publisher": { "@type": "Organization", "name": "Skulliance", "url": "https://www.skulliance.io/" },
      "potentialAction": { "@type": "PlayAction", "target": "<?php echo $sr_canonical; ?>" }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
        { "@type": "ListItem", "position": 2, "name": "Skull Racer - Free Retro Arcade Highway Racing Game", "item": "<?php echo $sr_canonical; ?>" }
      ]
    }
  ]
}
</script>
<style>
html { scroll-behavior: smooth; }
body { background: #07111d; margin: 0; color: #e8eaed; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.55; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
*, *::before, *::after { box-sizing: border-box; }
.sr-wrap { padding: 20px 16px 60px; }

/* Marketing landing -- same design language as cryptcrawlgame.php and
   skullswap.php's own standalone landings, so the whole set reads as one
   product line: brand green-teal accents, navy base, glow hero,
   accent-bar mechanics list, marquee strip. */
.sr-landing { padding-bottom: 20px; }
.sr-land-wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; box-sizing: border-box; }
.sr-hero-land {
	text-align: center; padding: 48px 20px 44px; margin: 0 -16px 0;
	background: radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%), linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
	border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sr-title-land { text-transform: uppercase; letter-spacing: 0.04em; font-size: clamp(1.9rem, 4.5vw, 3.2rem); margin: 0 0 0.2em; }
.sr-title-land img { display: inline-block; height: 0.9em; width: auto; vertical-align: -0.12em; margin: 0 0.08em; filter: drop-shadow(0 2px 4px rgba(0,0,0,.45)); }
.sr-subtitle-land { display: block; font-size: clamp(1.05rem, 2.5vw, 1.6rem); font-weight: 600; color: #c7d0d9; }
.sr-lead { max-width: 640px; margin: 14px auto 22px; color: #c7d0d9; font-size: 1.02rem; }
h1 { margin: 0 0 0.5em; }
h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
p { margin: 0 0 1em; }
a { color: #00c8a0; text-decoration: none; }
a:hover { color: #34e3bb; text-decoration: underline; }
.sr-shot-link { display: block; cursor: pointer; }
/* Wider than the 460px the card games' landings use for their board
   shots: this one is a busy gameplay scene with a HUD, traffic and a
   minimap in it, and at 460px the detail that actually sells the game
   goes muddy. Same style otherwise. */
.sr-shot-land {
	display: block; width: 100%; max-width: 620px; height: auto; margin: 0 auto 26px;
	border-radius: 14px; border: 1px solid rgba(255,255,255,.15);
	box-shadow: 0 30px 80px rgba(0,0,0,.7), 0 0 0 1px rgba(0,200,160,.1) inset;
	transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.sr-shot-link:hover .sr-shot-land, .sr-shot-link:focus-visible .sr-shot-land {
	transform: translateY(-3px); border-color: rgba(0,200,160,.5);
	box-shadow: 0 36px 90px rgba(0,0,0,.75), 0 0 24px rgba(0,200,160,.25);
}
.sr-cta {
	display: inline-block; background: linear-gradient(135deg, #00c8a0, #0596c4);
	color: #07111d !important; font-weight: 800; font-size: 1.08rem;
	padding: 14px 32px; border: none; border-radius: 999px; cursor: pointer;
	font-family: inherit; text-decoration: none !important;
	box-shadow: 0 6px 20px rgba(0,200,160,.35);
	transition: transform .15s ease, box-shadow .15s ease;
	vertical-align: middle;
}
.sr-cta:hover, .sr-cta:focus { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,200,160,.5); }
.sr-cta.sr-secondary { background: transparent; color: #00c8a0 !important; border: 1px solid rgba(0,200,160,.45); box-shadow: none; margin-left: 12px; }
.sr-cta.sr-secondary:hover { background: rgba(0,200,160,.08); }
@media (max-width: 480px) {
	.sr-cta { width: 100%; text-align: center; }
	.sr-cta.sr-secondary { margin-left: 0; margin-top: 10px; }
}
.sr-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 18px; }
.sr-badge { font-size: .8rem; font-weight: 600; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #c7d0d9; }
.sr-land-section { padding: 40px 0; }
.sr-land-section + .sr-land-section { border-top: 1px solid rgba(255,255,255,.06); }
.sr-land-section h2 { font-size: clamp(1.4rem, 3vw, 2rem); text-align: center; margin: 0 0 .6em; }
.sr-land-section p, .sr-land-section li { color: #c7d0d9; }
.sr-land-center { text-align: center; }
.sr-features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 20px; }
@media (max-width: 560px) { .sr-features { grid-template-columns: 1fr; } }
.sr-feat-card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 22px; }
.sr-feat-card h3 { margin: 0 0 8px; font-size: 1.1rem; color: #00c8a0; }
.sr-feat-card p { margin: 0; font-size: .96rem; }

/* Box/cartridge art showcase -- the racer's own retro-console packaging,
   which is what it has instead of Crypt Crawl's NFT card marquee. Plain
   flex row rather than a scrolling track: there are four pieces, not
   forty, so they all fit without motion. */
.sr-art-row { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-end; gap: 20px; margin-top: 22px; }
.sr-art-item { flex: 0 1 220px; text-align: center; }
.sr-art-item img { width: 100%; height: auto; border-radius: 10px; border: 1px solid rgba(255,255,255,.15); box-shadow: 0 10px 26px rgba(0,0,0,.65); }
.sr-art-item .sr-art-label { margin-top: 8px; font-size: .72rem; color: #8a96a3; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }

.sr-mechanics { list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
.sr-mechanics li { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; background: rgba(255,255,255,.03); border-left: 3px solid #00c8a0; border-radius: 6px; font-size: .95rem; }
.sr-mechanics li strong { color: #34e3bb; }
.sr-mech-emoji { flex-shrink: 0; width: 32px; text-align: center; font-size: 1.5rem; line-height: 1.2; filter: drop-shadow(0 2px 4px rgba(0,0,0,.5)); }
.sr-tips { margin: 16px 0 0; padding-left: 22px; }
.sr-tips li { margin-bottom: 8px; }
.sr-faq details { border-bottom: 1px solid rgba(255,255,255,.08); padding: 14px 0; }
.sr-faq summary { cursor: pointer; font-weight: 700; color: #e8eaed; }
.sr-faq p { margin: 10px 0 0; }
.sr-final { text-align: center; background: linear-gradient(180deg, rgba(0,200,160,.08), transparent); border-radius: 18px; padding: 40px 24px; }
.sr-footer { padding: 28px 20px; text-align: center; color: #8a96a3; font-size: .88rem; border-top: 1px solid rgba(255,255,255,.06); }
.sr-footer a { color: #8a96a3; }
</style>
</head>
<body>
<div class="sr-wrap">
<div class="sr-landing">
	<header class="sr-hero-land">
		<a class="sr-shot-link" href="skullracer.php" aria-label="Play Skull Racer now">
			<img class="sr-shot-land" src="/staking/racing/images/screenshot.png" alt="Skull Racer gameplay - racing a night desert highway under a full moon, traffic ahead and lap times on the HUD" loading="eager" fetchpriority="high" decoding="async" width="1217" height="916">
		</a>
		<h1><span class="sr-title-land"><img src="/staking/pwa/skulliance-logo-icon.png" alt="">Skull Racer<img src="/staking/pwa/skulliance-logo-icon.png" alt=""></span><span class="sr-subtitle-land">Free Retro Arcade Highway Racer</span></h1>
		<p class="sr-lead">Three laps of a dark desert highway at 180 mph - boost pads, jump ramps, traffic to thread, and the ghost of whoever currently holds the record running the line right beside you. No download, no signup - just drive.</p>
		<a class="sr-cta" href="skullracer.php">🏁 Start Racing</a>
		<a class="sr-cta sr-secondary" href="#sr-how-it-works">How It Works</a>
		<div class="sr-badges" aria-label="Game highlights">
			<span class="sr-badge">100% Free</span>
			<span class="sr-badge">No Download</span>
			<span class="sr-badge">No Signup</span>
			<span class="sr-badge">Mobile &amp; Desktop</span>
			<span class="sr-badge">Gamepad Ready</span>
		</div>
	</header>

	<section class="sr-land-section" id="sr-how-it-works">
		<div class="sr-land-wrap">
			<h2>An Arcade Racer With a Clock On It</h2>
			<div class="sr-features">
				<div class="sr-feat-card">
					<h3>👻 Race the Record Holders</h3>
					<p>Up to three translucent ghost cars drive alongside you every lap - your own fastest lap, the current weekly leader's, and the all-time leader's. The leaders' cars carry their avatar on the back, so you can see exactly whose time you're chasing.</p>
				</div>
				<div class="sr-feat-card">
					<h3>⚡ Boost Pads &amp; Jump Ramps</h3>
					<p>Each of the four longest straightaways hides a boost strip that pins you at 180 mph for the rest of it - and a ramp further down that same straight, in the same lane, so grabbing the boost lines up the jump automatically.</p>
				</div>
				<div class="sr-feat-card">
					<h3>🎮 Keyboard, Touch or Controller</h3>
					<p>Arrow keys or WASD on desktop. On a phone it drives itself so you only steer and brake - or pair a PS4/PS5 controller over Bluetooth and turn the phone sideways for a full-width, handheld-console view.</p>
				</div>
				<div class="sr-feat-card">
					<h3>🏆 CARBON &amp; a Weekly Leaderboard</h3>
					<p>Every finished race pays 1,000 CARBON just for crossing the line, win or lose. The weekly leaderboard ranks on total time across all three laps and splits a 50,000 CARBON pool.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="sr-land-section">
		<div class="sr-land-wrap sr-land-center">
			<h2>Boxed Like It's 1988</h2>
			<p>Skull Racer ships with its own front and back cover art and a cartridge to match - a dark-wasteland world drawn for this game alone, from the cracked road surface to the skull grinning off the back of your own car.</p>
			<!-- Cartridge deliberately in the MIDDLE, flanked by the two
			     box covers -- reads as the boxed product laid out, front
			     and back, with the cart sat between them. -->
			<div class="sr-art-row">
				<div class="sr-art-item">
					<img src="/staking/racing/images/skullracer.jpg" alt="Skull Racer box art, front cover" loading="lazy" decoding="async">
					<div class="sr-art-label">Front Cover</div>
				</div>
				<div class="sr-art-item">
					<img src="/staking/racing/images/front-cartridge.png" alt="Skull Racer cartridge, front" loading="lazy" decoding="async">
					<div class="sr-art-label">Cartridge</div>
				</div>
				<div class="sr-art-item">
					<img src="/staking/racing/images/backcover.jpg" alt="Skull Racer box art, back cover" loading="lazy" decoding="async">
					<div class="sr-art-label">Back Cover</div>
				</div>
			</div>
		</div>
	</section>

	<section class="sr-land-section">
		<div class="sr-land-wrap">
			<h2>How a Race Works</h2>
			<ul class="sr-mechanics">
				<li><span class="sr-mech-emoji" aria-hidden="true">🏁</span><span><strong>Three laps, one clock</strong> - lap and fastest-lap times show as you drive, but the leaderboard ranks on total time across all three, so a blistering lap you pay for later counts for nothing.</span></li>
				<li><span class="sr-mech-emoji" aria-hidden="true">⚡</span><span><strong>Boost pads sit on the four longest straights</strong> - a different lane every race, so you have to actually spot them rather than memorise a line. Crash while boosting and the run ends early.</span></li>
				<li><span class="sr-mech-emoji" aria-hidden="true">🛹</span><span><strong>Ramps and crests launch you</strong> - and while you're airborne traffic can't touch you, so a well-timed jump clears a car that would otherwise have ended your boost.</span></li>
				<li><span class="sr-mech-emoji" aria-hidden="true">🚗</span><span><strong>Traffic and roadside wreckage are randomised every lap</strong> - the track never changes, but what's on it does, so no two races play out the same way.</span></li>
				<li><span class="sr-mech-emoji" aria-hidden="true">💨</span><span><strong>Sit in a car's slipstream</strong> and you'll draft past your normal top speed - hold the lane long enough and it pays, break away or crash and it's gone instantly.</span></li>
			</ul>
		</div>
	</section>

	<section class="sr-land-section">
		<div class="sr-land-wrap">
			<h2>Drive Like You Mean It</h2>
			<ol class="sr-tips">
				<li><strong>Chase the gold ghost, not your own.</strong> Your personal-best ghost is a pacer; the gold ones are the times that actually pay.</li>
				<li><strong>Take the boost lane early.</strong> The pad sits near the start of a straight - drifting across for it late wastes most of the straight it was meant to buy you.</li>
				<li><strong>Brake before the corner, not in it.</strong> Off-road grass scrubs speed hard, and the time you lose recovering costs more than the corner ever did.</li>
				<li><strong>Use the jump on traffic, not scenery.</strong> Being airborne saves you from cars in your lane; billboards and rocks off the road don't care.</li>
				<li><strong>Finish the race even when it's gone wrong.</strong> Every completed race pays out regardless of position - quitting mid-race is the only way to earn nothing.</li>
			</ol>
		</div>
	</section>

	<section class="sr-land-section">
		<div class="sr-land-wrap sr-faq">
			<h2>Skull Racer FAQ</h2>
			<details>
				<summary>Is Skull Racer really free to play?</summary>
				<p>Yes - completely free. Open the page and drive, no purchase or signup required.</p>
			</details>
			<details>
				<summary>Do I need an account to play?</summary>
				<p>No account is needed for casual play. Log in through Skulliance with Discord if you want your times saved, counted toward the leaderboard, and paid out in CARBON - but it's never required to enjoy the game.</p>
			</details>
			<details>
				<summary>Does Skull Racer work on mobile?</summary>
				<p>Yes, and it's built for it. Driving is automatic on a phone so you only steer and brake, and turning the phone sideways switches to a full-width view with the controls moved to the screen edges where your thumbs already are.</p>
			</details>
			<details>
				<summary>Can I use a game controller?</summary>
				<p>Yes. Pair a PS4 or PS5 controller to your phone or computer over Bluetooth, press any button once so the browser sees it, and it takes over - stick or D-pad to steer, square for gas, cross for brake.</p>
			</details>
			<details>
				<summary>What are the ghost cars?</summary>
				<p>Recordings of real laps, not AI drivers. One is your own fastest lap, and the two gold ones are the current weekly and all-time record holders, replayed at the pace their total time actually earned. They can't be crashed into - they're a reference, not traffic.</p>
			</details>
			<details>
				<summary>How is the leaderboard scored?</summary>
				<p>On total time across all three laps - lowest wins. Every finished race also pays a flat CARBON reward regardless of where you place.</p>
			</details>
		</div>
	</section>

	<section class="sr-land-section">
		<div class="sr-land-wrap">
			<div class="sr-final">
				<h2>Ready to Drive?</h2>
				<p>The engine's running and the ghosts are already on the grid. No download. No signup. Just drive.</p>
				<a href="skullracer.php" class="sr-cta">🏁 Start Racing</a>
			</div>
		</div>
	</section>

	<footer class="sr-footer">
		<p>&copy; Skulliance &middot; Skull Racer is a free browser-based retro arcade highway racing game. <a href="https://www.skulliance.io/">Visit Skulliance</a></p>
	</footer>
</div>
</div>
</body>
</html>

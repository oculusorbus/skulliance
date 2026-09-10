<?php
// REALM GUARDIANS MARKETING PAGE -- public, deliberately session-independent.
//
// Same split cryptcrawlgame.php / cryptconquestgame.php / skullracergame.php
// already use: the *game.php file is the public front door that nav and the
// homepage point at, and the bare <game>.php beside it is the thing you
// actually play. Here that is guardians.php.
//
// Includes NOTHING -- not even db.php -- like skullracergame.php. There is no
// dynamic content on this page, so nothing here can be affected by a visitor's
// session or login state, and nothing can fail if the database is down. The
// same bytes render for every visitor, every time.
//
// The CTA is a plain RELATIVE link to guardians.php. Relative on purpose: the
// login cookie on this platform is host-only (no domain param), so
// www.skulliance.io and skulliance.io do NOT share a session -- hardcoding
// either host here would silently log a visitor out of the game they just
// clicked into.
$rg_canonical = 'https://www.skulliance.io/staking/guardiansgame.php';
/*
 * The real screenshot, matching its siblings (images/cryptcrawl.png,
 * racing/images/screenshot.png). One variable, used by the hero, OpenGraph,
 * Twitter and the schema block, so all four can never disagree about what this
 * game looks like. Verified live before pointing at it: 200, 741x931 PNG.
 */
$rg_og_image  = 'https://www.skulliance.io/staking/images/guardians.png';
$rg_title     = 'Realm Guardians - Free Tower Defense Game Built on Your NFT Realm | Play in Your Browser';
$rg_desc      = 'Play Realm Guardians free - a browser tower defense where your own Cardano NFT realm is the battlefield. Your enlisted NFTs man the wall carrying the gear you gave them, the horde is made of other players, and a monthly CARBON leaderboard ranks how long you held. Works on mobile, tablet and desktop. No download, no signup.';
$rg_short     = 'A free browser tower defense built on the realm you already own. Your NFTs hold the wall, the horde is other players, and a monthly CARBON leaderboard ranks how long you lasted. Play on any device - no download.';
?>
<!doctype html>
<html lang="en">
<head>
<title><?php echo htmlspecialchars($rg_title); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="<?php echo htmlspecialchars($rg_desc); ?>">
<meta name="keywords" content="free tower defense game, browser tower defense, no download tower defense, Cardano NFT game, NFT tower defense, wave defense game, mobile tower defense, base defense game, idle defense game, Skulliance">
<meta name="theme-color" content="#07111d">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="<?php echo $rg_canonical; ?>">

<!-- OpenGraph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $rg_canonical; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($rg_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($rg_desc); ?>">
<meta property="og:image" content="<?php echo $rg_og_image; ?>">
<meta property="og:image:alt" content="Realm Guardians - defending a realm wall against an oncoming horde">
<meta property="og:locale" content="en_US">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($rg_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($rg_short); ?>">
<meta name="twitter:image" content="<?php echo $rg_og_image; ?>">
<meta name="twitter:image:alt" content="Realm Guardians - defending a realm wall against an oncoming horde">

<!-- Schema.org structured data: VideoGame + BreadcrumbList + FAQPage -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "VideoGame",
      "name": "Realm Guardians",
      "alternateName": ["Skulliance Realm Guardians", "Realm Guardians Tower Defense"],
      "url": "<?php echo $rg_canonical; ?>",
      "image": "<?php echo $rg_og_image; ?>",
      "screenshot": "<?php echo $rg_og_image; ?>",
      "description": "<?php echo $rg_short; ?>",
      "genre": ["Strategy", "Tower Defense"],
      "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
      "playMode": "SinglePlayer",
      "applicationCategory": "Game",
      "operatingSystem": "Any",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
      "publisher": { "@type": "Organization", "name": "Skulliance", "url": "https://www.skulliance.io/" },
      "potentialAction": { "@type": "PlayAction", "target": "<?php echo $rg_canonical; ?>" }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
        { "@type": "ListItem", "position": 2, "name": "Realm Guardians - Free Tower Defense Game", "item": "<?php echo $rg_canonical; ?>" }
      ]
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        { "@type": "Question", "name": "Is Realm Guardians free to play?",
          "acceptedAnswer": { "@type": "Answer", "text": "Yes. It runs in your browser with no download and no signup. You can play as a guest without an account, though runs are only saved and scored for logged-in members." } },
        { "@type": "Question", "name": "Do I need NFTs to play?",
          "acceptedAnswer": { "@type": "Answer", "text": "No. Without a realm you hold the wall with conscripts. If you do have a Skulliance realm, your enlisted NFTs become the guardians on your wall, carrying the exact weapons and armour you equipped them with." } },
        { "@type": "Question", "name": "Can playing damage my realm?",
          "acceptedAnswer": { "@type": "Answer", "text": "No. The game reads your realm and never writes to it. Guardians that fall in a siege are numbers in the browser - your soldiers, gear and location levels are untouched no matter how the run goes." } },
        { "@type": "Question", "name": "How does the leaderboard work?",
          "acceptedAnswer": { "@type": "Answer", "text": "A monthly board paying 100,000 CARBON, ranked by how many waves you held past your own starting wave rather than the wave number you reached - so a large realm is a head start, not a trophy." } }
      ]
    }
  ]
}
</script>
<style>
html { scroll-behavior: smooth; }
body { background: #07111d; margin: 0; color: #e8eaed; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.55; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
*, *::before, *::after { box-sizing: border-box; }
.rg-wrap { padding: 20px 16px 60px; }

/* Deliberately the SAME values as skullracergame.php and the two card-game
   landings rather than an approximation of them: pill CTAs on the brand
   gradient, hairline section rules, .03 card fills, a 999px badge row. The four
   pages are one product line and should not be four dialects of it. */
.rg-landing { padding-bottom: 20px; }
.rg-land-wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; box-sizing: border-box; }
.rg-hero-land {
	text-align: center; padding: 48px 20px 44px; margin: 0 -16px 0;
	background: radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%), linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
	border-bottom: 1px solid rgba(255,255,255,0.08);
}
.rg-title-land { text-transform: uppercase; letter-spacing: 0.04em; font-size: clamp(1.9rem, 4.5vw, 3.2rem); margin: 0 0 0.2em; }
.rg-title-land img { display: inline-block; height: 0.9em; width: auto; vertical-align: -0.12em; margin: 0 0.08em; filter: drop-shadow(0 2px 4px rgba(0,0,0,.45)); }
.rg-subtitle-land { display: block; font-size: clamp(1.05rem, 2.5vw, 1.6rem); font-weight: 600; color: #c7d0d9; }
.rg-lead { max-width: 640px; margin: 14px auto 22px; color: #c7d0d9; font-size: 1.02rem; }
h1 { margin: 0 0 0.5em; }
h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
p { margin: 0 0 1em; }
a { color: #00c8a0; text-decoration: none; }
a:hover { color: #34e3bb; text-decoration: underline; }

/* Board shot. 620px like the racer's, not the 460px the card games use: this
   is a busy scene with a HUD, a field of units and a wall, and at 460px the
   detail that sells it goes muddy. */
.rg-shot-link { display: block; cursor: pointer; }
.rg-shot-land {
	display: block; width: 100%; max-width: 620px; height: auto; margin: 0 auto 26px;
	border-radius: 14px; border: 1px solid rgba(255,255,255,.15);
	box-shadow: 0 30px 80px rgba(0,0,0,.7), 0 0 0 1px rgba(0,200,160,.1) inset;
	transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.rg-shot-link:hover .rg-shot-land, .rg-shot-link:focus-visible .rg-shot-land {
	transform: translateY(-3px); border-color: rgba(0,200,160,.5);
	box-shadow: 0 36px 90px rgba(0,0,0,.75), 0 0 24px rgba(0,200,160,.25);
}
.rg-cta {
	display: inline-block; background: linear-gradient(135deg, #00c8a0, #0596c4);
	color: #07111d !important; font-weight: 800; font-size: 1.08rem;
	padding: 14px 32px; border: none; border-radius: 999px; cursor: pointer;
	font-family: inherit; text-decoration: none !important;
	box-shadow: 0 6px 20px rgba(0,200,160,.35);
	transition: transform .15s ease, box-shadow .15s ease;
	vertical-align: middle;
}
.rg-cta:hover, .rg-cta:focus { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,200,160,.5); }
.rg-cta.rg-secondary { background: transparent; color: #00c8a0 !important; border: 1px solid rgba(0,200,160,.45); box-shadow: none; margin-left: 12px; }
.rg-cta.rg-secondary:hover { background: rgba(0,200,160,.08); }
@media (max-width: 480px) {
	.rg-cta { width: 100%; text-align: center; }
	.rg-cta.rg-secondary { margin-left: 0; margin-top: 10px; }
}
.rg-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 18px; }
.rg-badge { font-size: .8rem; font-weight: 600; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #c7d0d9; }
.rg-land-section { padding: 40px 0; }
.rg-land-section + .rg-land-section { border-top: 1px solid rgba(255,255,255,.06); }
.rg-land-section h2 { font-size: clamp(1.4rem, 3vw, 2rem); text-align: center; margin: 0 0 .6em; }
.rg-land-section p, .rg-land-section li { color: #c7d0d9; }
.rg-land-center { text-align: center; }

/* TWO BY TWO, and it is the house grid -- skullracergame.php's .sr-features is
   the same repeat(2, 1fr) with the same 560px stack. auto-fit sized columns off
   the available width, so four cards landed as three-and-an-orphan. */
.rg-features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 20px; }
@media (max-width: 560px) { .rg-features { grid-template-columns: 1fr; } }
.rg-feat-card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 22px; }
.rg-feat-card h3 { margin: 0 0 8px; font-size: 1.1rem; color: #00c8a0; }
.rg-feat-card p { margin: 0; font-size: .96rem; }

.rg-mechanics { list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
.rg-mechanics li { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; background: rgba(255,255,255,.03); border-left: 3px solid #00c8a0; border-radius: 6px; font-size: .95rem; }
.rg-mechanics li strong { color: #34e3bb; }
.rg-mech-emoji { flex-shrink: 0; width: 32px; text-align: center; font-size: 1.5rem; line-height: 1.2; filter: drop-shadow(0 2px 4px rgba(0,0,0,.5)); }
.rg-tips { margin: 16px 0 0; padding-left: 22px; }
.rg-tips li { margin-bottom: 8px; }
.rg-faq details { border-bottom: 1px solid rgba(255,255,255,.08); padding: 14px 0; }
.rg-faq summary { cursor: pointer; font-weight: 700; color: #e8eaed; }
.rg-faq p { margin: 10px 0 0; }
.rg-final { text-align: center; background: linear-gradient(180deg, rgba(0,200,160,.08), transparent); border-radius: 18px; padding: 40px 24px; }
.rg-footer { padding: 28px 20px; text-align: center; color: #8a96a3; font-size: .88rem; border-top: 1px solid rgba(255,255,255,.06); }
.rg-footer a { color: #8a96a3; }
</style>
</head>
<body>
<div class="rg-wrap">
<div class="rg-landing">

	<header class="rg-hero-land">
		<a class="rg-shot-link" href="guardians.php" aria-label="Play Realm Guardians now">
			<!-- width/height are the file's real 741x931. Not decoration: without
			     them the hero has no height until it decodes, and everything below
			     it jumps down the page when it arrives. -->
			<img class="rg-shot-land" src="/staking/images/guardians.png"
			     alt="Realm Guardians - guardians holding a wall as the horde crosses the field"
			     loading="eager" fetchpriority="high" decoding="async"
			     width="741" height="931">
		</a>
		<h1><span class="rg-title-land"><img src="/staking/pwa/skulliance-logo-icon.png" alt="">Realm Guardians<img src="/staking/pwa/skulliance-logo-icon.png" alt=""></span><span class="rg-subtitle-land">Free Browser Tower Defense</span></h1>
		<p class="rg-lead">Your realm has to hold. Waves cross the field toward your wall, and the guardians standing on it are your own NFTs &mdash; carrying the exact weapons and armour you gave them. Every realm falls eventually; the only question is how long you held.</p>
		<a class="rg-cta" href="guardians.php">🛡️ Defend Your Realm</a>
		<a class="rg-cta rg-secondary" href="#rg-how-it-works">How It Works</a>
		<div class="rg-badges" aria-label="Game highlights">
			<span class="rg-badge">100% Free</span>
			<span class="rg-badge">No Download</span>
			<span class="rg-badge">No Signup</span>
			<span class="rg-badge">Mobile &amp; Desktop</span>
			<span class="rg-badge">Monthly Leaderboard</span>
		</div>
	</header>

	<section class="rg-land-section" id="rg-how-it-works">
		<div class="rg-land-wrap">
			<h2>Your Realm Is the Battlefield</h2>
			<p class="rg-land-center">Most tower defense hands you the same empty map as everyone else. This one reads the realm you have already built: its power decides which wave you open on, its soldiers are the guardians on your wall, and the gear you equipped them with is the gear they fight with. Nothing is spent and nothing is risked &mdash; the game reads your realm and never writes to it.</p>
			<div class="rg-features">
				<div class="rg-feat-card">
					<h3>Deploy</h3>
					<p>Send guardians from the Barracks to the Tower, armed and armoured from your cache.</p>
				</div>
				<div class="rg-feat-card">
					<h3>Strike</h3>
					<p>Meet the horde in the open before it reaches the wall. Trades bodies for damage you never take.</p>
				</div>
				<div class="rg-feat-card">
					<h3>Raise</h3>
					<p>One rite empties the whole Crypt and every guardian in it comes back. Free &mdash; it only costs time.</p>
				</div>
				<div class="rg-feat-card">
					<h3>Spend Items</h3>
					<p>Shields that cancel a breach outright, volleys that make the Tower bite harder, rushes that finish every line at once.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="rg-land-section">
		<div class="rg-land-wrap">
			<h2>How a Siege Works</h2>
			<ul class="rg-mechanics">
				<li><span class="rg-mech-emoji" aria-hidden="true">🏰</span><span><strong>Your realm is your starting line</strong> - power decides which wave you open on, so an established realm starts you deep into the siege rather than at wave one. No realm and you hold the wall with conscripts, from the beginning.</span></li>
				<li><span class="rg-mech-emoji" aria-hidden="true">🗡️</span><span><strong>Your NFTs man the Tower</strong> - carrying the actual weapons and armour you equipped them with. Weapons decide how hard they hit; armour is what lets a guardian walk away from a breach. Soldiers away on raids start already out in the field.</span></li>
				<li><span class="rg-mech-emoji" aria-hidden="true">💀</span><span><strong>The horde is other players</strong> - every attacker walking at your wall wears another member's avatar, and when the wall finally comes down, the one who broke it gets named.</span></li>
				<li><span class="rg-mech-emoji" aria-hidden="true">⚙️</span><span><strong>Seven locations run themselves</strong> - the Barracks trains, the Armory forges, the Factory builds, the Mine pays and the Crypt prepares your dead to return. You never click to keep the wall manned; you decide where the effort goes.</span></li>
				<li><span class="rg-mech-emoji" aria-hidden="true">☢️</span><span><strong>One last stand</strong> - the first time your wall falls, a charge goes off and everything on the field goes with it. Once. Whatever is still beyond the edge keeps coming.</span></li>
			</ul>
		</div>
	</section>

	<section class="rg-land-section">
		<div class="rg-land-wrap">
			<h2>The Monthly Board</h2>
			<p class="rg-land-center">100,000 CARBON every month, ranked by <strong>waves held past your own starting wave</strong> &mdash; not the wave number you reached. A large realm hands you a head start, so it does not hand you the board: a brand new player competes on exactly the same number as a veteran.</p>
			<ul class="rg-tips">
				<li>Runs are saved as you play, so you can close the tab and pick the siege back up.</li>
				<li>Pause any time &mdash; and it pauses itself if you switch away.</li>
				<li>Play as a guest without an account. Nothing is saved or scored, but the whole game is there.</li>
			</ul>
		</div>
	</section>

	<section class="rg-land-section">
		<div class="rg-land-wrap">
			<h2>Realm Guardians FAQ</h2>
			<div class="rg-faq">
				<details>
					<summary>Is it really free?</summary>
					<p>Yes. It runs in your browser with no download and no signup. You can play as a guest without an account &mdash; runs are only saved and scored for logged-in members.</p>
				</details>
				<details>
					<summary>Do I need NFTs to play?</summary>
					<p>No. Without a realm you hold the wall with conscripts. With one, your enlisted NFTs become the guardians on your wall, and their real gear decides how hard they hit and how long they last.</p>
				</details>
				<details>
					<summary>Can playing damage my realm?</summary>
					<p>No. The game reads your realm and never writes to it. A guardian falling in a siege is a number in your browser &mdash; your soldiers, your gear and your location levels are exactly as you left them, however the run goes.</p>
				</details>
				<details>
					<summary>Can I leave and come back?</summary>
					<p>Yes, if you are logged in. The run is saved as you play and you can pause whenever you like. Guests can play a full siege, but nothing is kept.</p>
				</details>
				<details>
					<summary>Does it work on a phone?</summary>
					<p>Yes. The whole board is built to fit a phone screen, and sound effects start muted on mobile so it will not surprise you.</p>
				</details>
				<details>
					<summary>Can I win?</summary>
					<p>No. Every realm falls to the horde eventually &mdash; there is no win condition, only how long you held. That is the score.</p>
				</details>
			</div>
		</div>
	</section>

	<section class="rg-land-section">
		<div class="rg-land-wrap">
			<div class="rg-final">
				<h2>Ready to Hold?</h2>
				<p>Pick a wall and see how long it stands.</p>
				<a class="rg-cta" href="guardians.php">🛡️ Defend Your Realm</a>
			</div>
		</div>
	</section>

	<footer class="rg-footer">
		<p>Part of <a href="https://www.skulliance.io/">Skulliance</a> &mdash; also try
		<a href="cryptcrawlgame.php">Crypt Crawl</a>,
		<a href="cryptconquestgame.php">Crypt Conquest</a> and
		<a href="skullracergame.php">Skull Racer</a>.</p>
	</footer>

</div>
</div>
</body>
</html>

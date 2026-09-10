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
 * THE HERO IMAGE IS A PLACEHOLDER AND SHOULD BE SWAPPED.
 *
 * Its siblings point at a real screenshot (images/cryptcrawl.png,
 * racing/images/screenshot.png). There is no guardians screenshot on the server
 * yet -- images deploy by FTP, outside this repo -- and a 404 here would break
 * the social and search preview, not just the page. So this points at a live
 * realm theme, which is at least the right subject, until a screenshot exists
 * at images/guardians.png. One variable, used by the hero, OpenGraph, Twitter
 * and the schema block: change it here and every one of them follows.
 */
$rg_og_image  = 'https://www.skulliance.io/staking/images/themes/7.jpg';
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
/* Same design language as the other three landings, so the set reads as one
   product line: navy base, green-teal accents, glow hero, accent-bar list. */
.rg-land-wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; box-sizing: border-box; }
.rg-hero-land {
	text-align: center; padding: 48px 20px 44px; margin: 0 -16px 0;
	background: radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%), linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
	border-bottom: 1px solid rgba(255,255,255,0.08);
}
.rg-title-land { text-transform: uppercase; letter-spacing: 0.04em; font-size: clamp(1.9rem, 4.5vw, 3.2rem); margin: 0 0 0.2em; display: block; }
.rg-title-land img { display: inline-block; height: 0.9em; width: auto; vertical-align: -0.12em; margin: 0 0.08em; filter: drop-shadow(0 2px 4px rgba(0,0,0,.45)); }
.rg-subtitle-land { display: block; font-size: clamp(1.05rem, 2.5vw, 1.6rem); font-weight: 600; color: #c7d0d9; }
.rg-lead { max-width: 660px; margin: 14px auto 22px; color: #c7d0d9; font-size: 1.02rem; }
h1 { margin: 0 0 0.5em; }
h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
p { margin: 0 0 1em; }
a { color: #00c8a0; text-decoration: none; }
a:hover { color: #34e3bb; text-decoration: underline; }
.rg-shot-link { display: block; cursor: pointer; }
.rg-shot-land { display: block; width: 100%; max-width: 720px; margin: 0 auto 26px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.10); box-shadow: 0 18px 50px rgba(0,0,0,.55); height: auto; }
.rg-cta { display: inline-block; background: #00c8a0; color: #04121d; font-weight: 800; padding: 14px 30px; border-radius: 8px; font-size: 1.05rem; margin: 6px 6px 0; }
.rg-cta:hover { background: #34e3bb; color: #04121d; text-decoration: none; }
.rg-cta.rg-secondary { background: rgba(255,255,255,0.10); color: #e8eaed; }
.rg-cta.rg-secondary:hover { background: rgba(255,255,255,0.18); color: #fff; }
.rg-badges { margin-top: 22px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.rg-badge { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.10em; color: #9fb0bf; border: 1px solid rgba(255,255,255,0.16); border-radius: 20px; padding: 5px 12px; }
.rg-land-section { padding: 46px 0 8px; }
.rg-land-section h2 { text-align: center; font-size: clamp(1.4rem, 3vw, 2rem); margin-bottom: 0.9em; }
.rg-mechanics { list-style: none; padding: 0; margin: 0 auto; max-width: 760px; }
.rg-mechanics li { border-left: 3px solid #00c8a0; padding: 4px 0 4px 16px; margin: 0 0 18px; }
.rg-mechanics strong { display: block; color: #fff; margin-bottom: 2px; }
.rg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; max-width: 900px; margin: 0 auto; }
.rg-card { background: #0d1e30; border: 1px solid rgba(255,255,255,0.10); border-radius: 10px; padding: 18px; }
.rg-card h3 { font-size: 1rem; color: #00c8a0; }
.rg-card p { margin: 0; font-size: 0.92rem; color: #c7d0d9; }
.rg-faq { max-width: 760px; margin: 0 auto; }
.rg-faq dt { font-weight: 700; color: #fff; margin-top: 18px; }
.rg-faq dd { margin: 6px 0 0; color: #c7d0d9; }
.rg-foot { text-align: center; padding: 46px 20px 10px; color: #7d8b98; font-size: 0.88rem; }
.rg-foot a { color: #9fb0bf; }
</style>
</head>
<body>
<div class="rg-wrap">
<div class="rg-landing">

	<header class="rg-hero-land">
		<a class="rg-shot-link" href="guardians.php" aria-label="Play Realm Guardians now">
			<img class="rg-shot-land" src="/staking/images/guardians.png"
			     onerror="this.onerror=null;this.src='/staking/images/themes/7.jpg';"
			     alt="Realm Guardians - guardians holding a wall as the horde crosses the field"
			     loading="eager" fetchpriority="high" decoding="async">
		</a>
		<h1><span class="rg-title-land"><img src="/staking/pwa/skulliance-logo-icon.png" alt="">Realm Guardians<img src="/staking/pwa/skulliance-logo-icon.png" alt=""></span><span class="rg-subtitle-land">Free Browser Tower Defense</span></h1>
		<p class="rg-lead">Your realm has to hold. Waves of attackers cross the field toward your wall, and the guardians standing on it are your own NFTs &mdash; carrying the exact weapons and armour you gave them. No download, no signup. Every realm falls eventually; the only question is how long you held.</p>
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

	<div class="rg-land-wrap">

		<section class="rg-land-section" id="rg-how-it-works">
			<h2>How It Works</h2>
			<ul class="rg-mechanics">
				<li><strong>Your realm is your starting line.</strong>
				A realm's power decides which wave you open on, so an established realm starts you deep into the siege instead of at wave one. No realm? You hold the wall with conscripts and start at the beginning.</li>
				<li><strong>Your NFTs are the guardians.</strong>
				Enlisted soldiers man the Tower carrying the actual gear you equipped them with. Soldiers away on raids begin already out in the field. Better weapons hit harder; armour is what lets a guardian walk away from a breach.</li>
				<li><strong>The horde is other players.</strong>
				Every attacker walking at your wall wears another member's avatar. When the wall finally comes down, the one who broke it gets named.</li>
				<li><strong>Seven locations run themselves.</strong>
				The Barracks trains, the Armory forges, the Factory builds, the Mine pays, the Crypt prepares your dead to return. You never click to keep the wall manned &mdash; your job is deciding where the effort goes.</li>
				<li><strong>Nothing is spent and nothing is risked.</strong>
				The game reads your realm and never writes to it. Play it a hundred times and your soldiers, gear and levels are exactly as you left them.</li>
			</ul>
		</section>

		<section class="rg-land-section">
			<h2>What You Actually Decide</h2>
			<div class="rg-grid">
				<div class="rg-card">
					<h3>Deploy</h3>
					<p>Send guardians from the Barracks to the Tower, armed and armoured from your cache.</p>
				</div>
				<div class="rg-card">
					<h3>Strike</h3>
					<p>Meet the horde in the open before it reaches the wall. Trades bodies for damage you never take.</p>
				</div>
				<div class="rg-card">
					<h3>Raise</h3>
					<p>One rite empties the whole Crypt and every guardian in it comes back. Free &mdash; it only costs time.</p>
				</div>
				<div class="rg-card">
					<h3>Spend Items</h3>
					<p>Shields that cancel a breach outright, volleys that make the Tower bite harder, and rushes that finish every production line at once.</p>
				</div>
			</div>
		</section>

		<section class="rg-land-section">
			<h2>The Monthly Board</h2>
			<p style="max-width:760px;margin:0 auto 1em;text-align:center;color:#c7d0d9;">
			100,000 CARBON every month, ranked by <strong>waves held past your own starting wave</strong> &mdash; not the wave number you reached. A large realm hands you a head start, so it does not hand you the board. A brand new player competes on exactly the same number as a veteran.</p>
			<p style="text-align:center;"><a class="rg-cta" href="guardians.php">🛡️ Play Realm Guardians</a></p>
		</section>

		<section class="rg-land-section">
			<h2>Questions</h2>
			<dl class="rg-faq">
				<dt>Is it really free?</dt>
				<dd>Yes. It runs in your browser, with no download and no signup. You can play as a guest without an account &mdash; runs are only saved and scored for logged-in members.</dd>
				<dt>Do I need NFTs to play?</dt>
				<dd>No. Without a realm you hold the wall with conscripts. With one, your enlisted NFTs become the guardians on your wall and their real gear decides how hard they hit and how long they last.</dd>
				<dt>Can it damage my realm?</dt>
				<dd>No. It reads your realm and never writes to it. A guardian falling in a siege is a number in your browser &mdash; nothing touches your soldiers, your gear or your location levels.</dd>
				<dt>Can I leave and come back?</dt>
				<dd>Yes, if you are logged in. A run is saved as you play, and you can pause any time. Guests can play a full siege, but nothing is kept.</dd>
				<dt>Does it work on a phone?</dt>
				<dd>Yes. The whole board is built to fit a phone screen, and effects start muted on mobile so it will not surprise you.</dd>
			</dl>
		</section>

		<div class="rg-foot">
			<p>Part of <a href="https://www.skulliance.io/">Skulliance</a> &mdash; also try
			<a href="cryptcrawlgame.php">Crypt Crawl</a>,
			<a href="cryptconquestgame.php">Crypt Conquest</a> and
			<a href="skullracergame.php">Skull Racer</a>.</p>
		</div>

	</div>
</div>
</div>
</body>
</html>

<?php
/**
 * DROP SHIP MARKETING PAGE -- public, and the first one this game has had.
 *
 * Same split as cryptcrawlgame.php / guardiansgame.php / skullracergame.php:
 * the *game.php file is the public front door, and the thing you play sits
 * behind the login at dropship/dashboard.php.
 *
 * BUILT BECAUSE A SHARE NEEDS SOMEWHERE TO LAND. x.com/intent/post cannot
 * attach a file; the only picture a post carries is the twitter:image of the
 * URL it links, fetched by X's own crawler. Every Drop Ship URL redirects to
 * the login -- dropship/index.php included -- so the crawler got a redirect
 * to error.php and a shared run rendered as a bare blue link. There was no
 * page on this domain that could carry the card.
 *
 * ?theme=<slug> POINTS THE CARD AT THAT GAME. Five themes run on one engine
 * and they look nothing alike, so a share of a Dread City run must not show
 * Drop Ship's art. The slug only ever changes the META -- title, description
 * and og:image -- never the body, which is the same shape dhcarena.php uses
 * for ?battle=. The canonical stays the bare page so every shared run
 * consolidates onto one indexable URL instead of spawning thousands.
 *
 * AND NOTHING ABOUT THE RUN IS IN THE URL. The obvious design was
 * ?run=<result_id>, which would have let anyone walk the results table by
 * changing a number -- every player's score, in public, forever. The post
 * text carries the placement and the name, and the player writes that
 * themselves in their own composer. Nothing readable here is anyone's but
 * the theme's.
 *
 * ONE QUERY, FAILING QUIET. lib/dropship-public.php reads the active theme
 * list and nothing else -- not dropship/db.php, which IS the login gate, and
 * not the platform's db.php, which is the whole application. With the
 * database down this page still renders; it just lists no themes.
 */
require_once __DIR__ . '/lib/dropship-public.php';

$ds_themes = dropshipPublicThemes();
/*
 * REAL SOLDIERS, OFF THE LOCAL CACHE. The page had no picture above the
 * fold at all -- every sibling game page opens with one -- and the one
 * thing this game has more of than any other here is art: hundreds of
 * cards already registered as soldiers.
 *
 * DROP SHIP'S OWN ONLY, at the owner's direction for this pass. Five
 * collections on one page is five art styles fighting each other, and
 * this is a pitch rather than a gallery.
 * dropshipPublicArt() returns only files that exist on this server, so a
 * dead gateway cannot turn this into a wall of broken frames.
 */
$ds_art = dropshipPublicArt(36, 'drop-ship');
$ds_canonical = 'https://www.skulliance.io/staking/dropshipgame.php';

/* The shared theme, when there is one. Validated against the live list, so
   an invented slug falls back to the generic page rather than 404ing
   someone's post. */
$ds_one  = isset($_GET['theme']) ? dropshipPublicTheme($_GET['theme']) : null;
$ds_slug = $ds_one ? $ds_one['slug'] : '';
$ds_name = $ds_one ? $ds_one['name'] : '';

/*
 * WHAT THIS THEME MAY CLAIM. The page said "Free to play" and "every
 * player is given four free soldiers" for whichever theme it was showing,
 * and both are false somewhere: Oculus Lounge needs VIP, and the four
 * grunts are Drop Ship only (addDefaultSoldiers() is gated on project 1).
 * Same rule the share post uses, from the same function, because the post
 * is what sends people here and the two must not disagree.
 */
$ds_claims = dropshipThemeClaims($ds_one ? $ds_one['project_id'] : 0);
/* With no theme named this is the whole family, where "free to play" is
   true of four of the five and the Lounge is called out on its own tile. */
$ds_open  = $ds_one ? $ds_claims['open'] : true;
$ds_grunts = $ds_one ? $ds_claims['free_squad'] : false;

$ds_og_image = $ds_slug !== ''
	? 'https://www.skulliance.io/staking/dropship-card.php?theme=' . rawurlencode($ds_slug)
	: 'https://www.skulliance.io/staking/dropship-card.php?theme=drop-ship';

$ds_title = $ds_name !== ''
	? $ds_name . ($ds_open ? ' - Free' : ' -') . ' Weekly NFT Squad Battler | Play in Your Browser'
	: 'Drop Ship - Free Weekly NFT Squad Battler, Five Games on One Engine | Skulliance';
$ds_desc  = $ds_name !== ''
	? 'Play ' . $ds_name . ' on Skulliance' . ($ds_open ? ', free' : ' with VIP access') . '. Send a squad of '
	. 'four into a run that gets harder every round, see how far they get, and take your place on a leaderboard '
	. 'that resets every week. Your squads build themselves from what you hold, so there is nothing to manage '
	. 'before you play.'
	: 'Play Drop Ship free on Skulliance. Send a squad of four into a run that gets harder every round and '
	. 'climb a leaderboard that resets every week. Five games share one engine, each with its own board, its '
	. 'own squad and its own prizes. Your squads build themselves, so there is nothing to manage before you play.';
$ds_short = $ds_name !== ''
	? 'A squad of four, a run that gets harder every round, and a board that resets every week.'
	: 'Five games on one engine. A squad of four, a run that gets harder every round, and a weekly board.';
?>
<!doctype html>
<html lang="en">
<head>
<?php include __DIR__ . '/lib/favicon-head.php'; ?>
<title><?php echo htmlspecialchars($ds_title); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="<?php echo htmlspecialchars($ds_desc); ?>">
<meta name="theme-color" content="#07111d">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<?php /* CANONICAL IS THE BARE PAGE even on ?theme=, so a thousand shared runs
         consolidate into one indexable URL instead of a thousand near
         duplicates. Absolute here and only here: canonical and OG are not
         clickable navigation, so they cannot bounce a visitor between www and
         the bare host and drop a host-only session cookie. */ ?>
<link rel="canonical" href="<?php echo $ds_canonical; ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $ds_canonical; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($ds_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($ds_desc); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($ds_og_image); ?>">
<meta property="og:image:alt" content="<?php echo htmlspecialchars(($ds_name !== '' ? $ds_name : 'Drop Ship') . ' cover art'); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($ds_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($ds_short); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($ds_og_image); ?>">
<meta name="twitter:image:alt" content="<?php echo htmlspecialchars(($ds_name !== '' ? $ds_name : 'Drop Ship') . ' cover art'); ?>">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "VideoGame",
      "name": <?php echo json_encode($ds_name !== '' ? $ds_name : 'Drop Ship'); ?>,
      "alternateName": ["Skulliance Drop Ship", "Drop Ship NFT Battler"],
      "url": "<?php echo $ds_canonical; ?>",
      "image": "<?php echo htmlspecialchars($ds_og_image); ?>",
      "screenshot": "<?php echo htmlspecialchars($ds_og_image); ?>",
      "description": <?php echo json_encode($ds_short); ?>,
      "genre": ["Strategy", "Roguelike", "Collectible"],
      "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
      "playMode": "SinglePlayer",
      "applicationCategory": "Game",
      "operatingSystem": "Any",
      "inLanguage": "en",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
      "publisher": { "@type": "Organization", "name": "Skulliance", "url": "https://www.skulliance.io/" },
      "potentialAction": { "@type": "PlayAction", "target": "<?php echo $ds_canonical; ?>" }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
        { "@type": "ListItem", "position": 2, "name": "Drop Ship - Free Weekly NFT Squad Battler", "item": "<?php echo $ds_canonical; ?>" }
      ]
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        { "@type": "Question", "name": "Is Drop Ship free to play?",
          "acceptedAnswer": { "@type": "Answer", "text": "Yes. It runs in your browser with no download. Drop Ship gives every player four free soldiers, and those four make one complete squad, so you can take a real run before you own anything. Oculus Lounge is the one theme that is not open to everybody." } },
        { "@type": "Question", "name": "Do I need an NFT to play Drop Ship?",
          "acceptedAnswer": { "@type": "Answer", "text": "No. Four free soldiers are waiting in your barracks. NFTs you hold become extra soldiers, and in DHC2 Fighters a Fighter you assemble out of earned traits also fields as one." } },
        { "@type": "Question", "name": "How long does a run take?",
          "acceptedAnswer": { "@type": "Answer", "text": "A couple of minutes. You pick a squad, deploy, and watch the run play out round by round. The round you reach is your score for the week." } },
        { "@type": "Question", "name": "Do I have to manage squads?",
          "acceptedAnswer": { "@type": "Answer", "text": "No. Squads are generated from what you hold, arranged into the best combinations available, every time your collection changes. There is nothing to name, save or take apart." } },
        { "@type": "Question", "name": "What are the five games?",
          "acceptedAnswer": { "@type": "Answer", "text": "Drop Ship, Dread City, Filthy Mermaid, DHC2 Fighters and Oculus Lounge. They share one engine but keep their own board, their own squad, their own art and their own prizes." } }
      ]
    }
  ]
}
</script>
<style>
/*
 * THE PAGE PAINTS ITS OWN GROUND, which this shipped without and a
 * screenshot caught immediately: the body stayed white while every colour
 * here assumed the dark site, so the whole page was pale grey text on
 * white and only the cards were readable. These pages are standalone --
 * they include site-header.php and nothing else -- so nothing upstream
 * sets a background for them. Same line guardiansgame.php carries.
 */
body { background: #07111d; margin: 0; color: #e8eaed; line-height: 1.55;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu,
  Cantarell, sans-serif; overflow-x: hidden; -webkit-font-smoothing: antialiased; }

/* Everything else is scoped to .dsg-, so it cannot reach site-header.php. */
.dsg-wrap { max-width: 1100px; margin: 0 auto; padding: 28px 16px 60px; color: #e8eaed; }
.dsg-hero { text-align: center; }
.dsg-hero h1 { margin: 0 0 6px; font-size: clamp(1.9rem, 5vw, 3rem); text-wrap: balance; }
.dsg-sub { display: block; font-size: .8rem; letter-spacing: .12em; text-transform: uppercase;
  color: #00c8a0; margin-top: 8px; }
.dsg-lead { max-width: none; margin: 16px 0 22px; font-size: 1.02rem; line-height: 1.6; color: #c6d4dd; }
.dsg-cta { display: inline-block; margin: 0 6px 10px; padding: 12px 26px; background: #00c8a0;
  color: #06222b; font-weight: 700; text-decoration: none; border: 1px solid #00c8a0; }
.dsg-cta.alt { background: transparent; color: #00c8a0; }
.dsg-cta:hover { filter: brightness(1.1); }
.dsg-badges { margin: 14px 0 0; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.dsg-badge { font-size: .72rem; letter-spacing: .07em; text-transform: uppercase; color: #8fa8b8;
  border: 1px solid rgba(255,255,255,.14); padding: 5px 10px; }
.dsg-sec { margin-top: 46px; }
.dsg-sec h2 { font-size: 1.5rem; margin: 0 0 12px; }
.dsg-sec p { line-height: 1.65; color: #c6d4dd; }
.dsg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px;
  margin-top: 18px; }
.dsg-card { background: #07111d; border: 1px solid rgba(255,255,255,.07); padding: 16px; }
.dsg-card h3 { margin: 0 0 7px; font-size: 1rem; color: #00c8a0; }
.dsg-card p { margin: 0; font-size: .9rem; }
/* The theme wall: the same art the picker shows, whole and uncropped --
   object-fit:contain, because the five are nothing alike in shape. */
.dsg-themes { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px;
  margin-top: 18px; }
.dsg-theme { background: #07111d; border: 1px solid #163047; overflow: hidden; }
.dsg-theme .art { display: block; width: 100%; aspect-ratio: 1/1; background: #07111d; }
.dsg-theme .art img { display: block; width: 100%; height: 100%; object-fit: contain; max-width: 100%; }
.dsg-theme b { display: block; padding: 9px 11px 11px; font-size: .9rem; }
.dsg-theme.on { border-color: #00c8a0; }

/*
 * THE SOLDIER STRIP. A single row above the fold that scrolls sideways
 * on a phone and fans out on a desktop -- the fastest way to say "this
 * game is made of hundreds of pieces of art" without a hero screenshot
 * that would only ever show one of the five games.
 */
.dsg-strip { display: flex; gap: 8px; overflow-x: auto; padding: 2px 0 10px;
  margin: 0 0 18px; scrollbar-width: thin; }
.dsg-strip img { flex: 0 0 auto; width: 104px; height: 104px; object-fit: cover;
  border: 1px solid rgba(255,255,255,0.10); background: #07111d; }
@media (min-width: 760px) { .dsg-strip img { width: 128px; height: 128px; } }

/* The wall: everything, as a mosaic. */
.dsg-wall { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 6px; margin-top: 18px; }
.dsg-wall img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block;
  background: #07111d; border: 1px solid rgba(255,255,255,0.07); }

/* The kit strip: the game's own icons, labelled. */
.dsg-kit { margin: 16px 0 4px; display: flex; flex-direction: column; gap: 10px; }
.dsg-kit-row { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; }
.dsg-kit-label { font-size: 0.7rem; letter-spacing: 0.12em; text-transform: uppercase;
  color: #00c8a0; width: 52px; flex: 0 0 auto; }
.dsg-kit-item { display: flex; align-items: center; gap: 7px; }
.dsg-kit-item img { width: 40px; height: 40px; object-fit: contain; }
.dsg-kit-item em { font-style: normal; font-size: 0.82rem; color: #c6d4dd; }

.dsg-faq { margin-top: 18px; }
.dsg-faq details { border-top: 1px solid rgba(255,255,255,0.08); padding: 11px 0; }
.dsg-faq summary { cursor: pointer; font-weight: bold; font-size: 0.95rem; list-style: none; }
.dsg-faq summary::-webkit-details-marker { display: none; }
.dsg-faq summary::after { content: ' +'; color: #00c8a0; }
.dsg-faq details[open] summary::after { content: ' \2212'; }
.dsg-faq p { margin: 8px 0 0; font-size: 0.9rem; }
</style>
</head>
<body>
<?php include __DIR__ . '/site-header.php'; ?>

<div class="dsg-wrap">

	<?php if ($ds_art): ?>
	<?php /* ABOVE THE HEADLINE, because it is the argument. Every other
	         game page opens with a screenshot of one board; this game is
	         five collections of art, and showing it beats describing it. */ ?>
	<div class="dsg-strip" aria-label="Soldiers registered in the game">
		<?php foreach (array_slice($ds_art, 0, 14) as $a): ?>
		<img src="<?php echo htmlspecialchars($a['url'], ENT_QUOTES); ?>" alt=""
		     loading="eager" decoding="async" width="128" height="128">
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<header class="dsg-hero">
		<h1><?php echo htmlspecialchars($ds_name !== '' ? $ds_name : 'Drop Ship'); ?></h1>
		<span class="dsg-sub">Free weekly squad battler</span>
		<p class="dsg-lead">Send four into a run that gets harder every round and see how far they get.
			Nobody survives forever, so the only question is how deep you went and whether it was
			deeper than everyone else this week. The board wipes every week, which means the player
			who has been here for years and the player who started this morning are both chasing the
			same number.</p>
		<?php /* RELATIVE. The login cookie is host-only, so an absolute link
		         here can drop a visitor onto the host they are not signed in
		         on and bounce them to a login they already passed. */ ?>
		<a class="dsg-cta" href="dropship/dashboard.php<?php
			echo $ds_one ? '?project_id=' . (int)$ds_one['project_id'] : ''; ?>">Play now</a>
		<a class="dsg-cta alt" href="#dsg-how">How it works</a>
		<div class="dsg-badges">
			<?php /* NOT "Free to play" on a theme that is not. Oculus Lounge
			         wants the VIP role or a pass bought with DISCOIN. */ ?>
			<span class="dsg-badge"><?php echo $ds_open ? 'Free to play' : 'VIP access'; ?></span>
			<span class="dsg-badge">No download</span>
			<span class="dsg-badge">Weekly reset</span>
			<span class="dsg-badge">Squads build themselves</span>
			<span class="dsg-badge">Mobile &amp; desktop</span>
		</div>
	</header>

	<section class="dsg-sec" id="dsg-how">
		<h2>How a run works</h2>
		<p>You field a squad of four. Each round the run rolls against your squad's armor, and when it
			rolls badly somebody dies. Get far enough and the round number is your score for the week.</p>
		<?php
		/*
		 * THE GAME'S OWN ICONS, not a description of them. These are the
		 * exact files the barracks and the shop draw, so a stranger has
		 * already met the vocabulary before they open the game. Every one
		 * verified live before being pointed at -- images ship by FTP and
		 * are not in the repo, so a filename here is a claim about the
		 * server, not about this checkout.
		 */
		$ds_ico   = 'https://skulliance.io/staking/dropship/icons/';
		$ds_armor = array('heavy-armor' => 'Heavy', 'medium-armor' => 'Medium',
		                  'light-armor' => 'Light', 'base-armor' => 'Base');
		$ds_gear  = array('medkit' => 'Medkit', 'melee' => 'Melee', 'demolition' => 'Demolition');
		?>
		<div class="dsg-kit">
			<div class="dsg-kit-row"><span class="dsg-kit-label">Armor</span>
				<?php foreach ($ds_armor as $f => $n): ?>
				<span class="dsg-kit-item"><img src="<?php echo $ds_ico . $f; ?>.png" alt=""
					loading="lazy" width="40" height="40"><em><?php echo $n; ?></em></span>
				<?php endforeach; ?>
			</div>
			<div class="dsg-kit-row"><span class="dsg-kit-label">Gear</span>
				<?php foreach ($ds_gear as $f => $n): ?>
				<span class="dsg-kit-item"><img src="<?php echo $ds_ico . $f; ?>.png" alt=""
					loading="lazy" width="40" height="40"><em><?php echo $n; ?></em></span>
				<?php endforeach; ?>
				<span class="dsg-kit-item"><em>or none</em></span>
			</div>
		</div>
		<div class="dsg-grid">
			<div class="dsg-card">
				<h3>Armor sets the odds</h3>
				<p>The best armor in the squad sets the floor and every lower tier adds on top, so a mixed
					squad of four beats four of the same thing.</p>
			</div>
			<div class="dsg-card">
				<h3>One of each item fires</h3>
				<p>A Medkit is one extra life however many you send. A second is a duplicate and does
					nothing, which is why the fourth soldier is often better off carrying none.</p>
			</div>
			<div class="dsg-card">
				<h3>Your squads build themselves</h3>
				<p>Open the game and what you hold is already arranged into the best squads it can make.
					Nothing to name, nothing to save, nothing to take apart.</p>
			</div>
			<div class="dsg-card">
				<h3>The board resets weekly</h3>
				<p>Every week is a fresh ladder with its own prizes, so a bad week costs you nothing and a
					good one counts for everything.</p>
			</div>
		</div>
	</section>

	<?php if ($ds_themes): ?>
	<section class="dsg-sec">
		<h2><?php echo count($ds_themes) === 1 ? 'The game' : 'Five games, one engine'; ?></h2>
		<p>Each keeps its own board, its own squad and its own prizes, and reads its own collection for
			what your soldiers are wearing. Switching between them is one press inside the game.</p>
		<div class="dsg-themes">
			<?php foreach ($ds_themes as $t): ?>
			<div class="dsg-theme<?php echo ($t['slug'] === $ds_slug) ? ' on' : ''; ?>">
				<?php /* The card endpoint, reused as a thumbnail: it already frames
				         whatever shape the art is and it is already cached. */ ?>
				<span class="art"><img loading="lazy" alt=""
					src="/staking/dropship-card.php?theme=<?php echo rawurlencode($t['slug']); ?>"></span>
				<b><?php echo htmlspecialchars($t['name']); ?></b>
			</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if (count($ds_art) > 14): ?>
	<section class="dsg-sec">
		<h2>Every one of these is somebody's soldier</h2>
		<p>Drop Ship does not draw its own characters. It reads the collections people already hold and
			turns each card into a soldier, carrying the armor and gear its art says it has. These are
			real ones, pulled straight from the barracks.</p>
		<div class="dsg-wall">
			<?php foreach ($ds_art as $a): ?>
			<img src="<?php echo htmlspecialchars($a['url'], ENT_QUOTES); ?>"
			     alt="<?php echo htmlspecialchars($a['theme'] . ' soldier', ENT_QUOTES); ?>"
			     loading="lazy" decoding="async">
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<section class="dsg-sec">
		<h2>Questions</h2>
		<div class="dsg-faq">
			<details open><summary>Is it free?</summary>
				<p>Yes, and you do not need to own anything. Four soldiers are waiting in your Drop Ship
					barracks and the four of them make one complete squad. Oculus Lounge is the one theme
					that is not open to everybody.</p></details>
			<details><summary>How long is a run?</summary>
				<p>A couple of minutes. Pick a squad, deploy, and watch it play out round by round. The
					round you reach is your score for the week.</p></details>
			<details><summary>Do I have to manage squads?</summary>
				<p>No. They are generated from what you hold and rebuilt whenever your collection changes.
					Nothing to name, save or take apart.</p></details>
			<details><summary>What happens to a soldier that dies?</summary>
				<p>Nothing permanent. Death is a timeout: they can be resurrected after a short wait, and
					your NFT is never at risk. Weapons degrade over time anyway.</p></details>
			<details><summary>What are the five games?</summary>
				<p>Drop Ship, Dread City, Filthy Mermaid, DHC2 Fighters and Oculus Lounge. One engine, but
					each keeps its own board, squad, art and prizes.</p></details>
		</div>
	</section>

	<section class="dsg-sec">
		<h2>Start without owning anything</h2>
		<?php /* THE FREE SQUAD IS DROP SHIP ONLY. addDefaultSoldiers() is gated
		         on project 1, so claiming it on a Dread City or Oculus Lounge
		         page is a promise the game does not keep. */ ?>
		<?php if ($ds_grunts || $ds_one === null): ?>
		<p><?php echo $ds_grunts ? 'Every player is given' : 'In Drop Ship every player is given'; ?>
			four free soldiers, and they are not filler: Hagar, Delay, Blood Bag and Sektr carry Heavy,
			Medium, Light and Base armor with a Melee, a Demolition and a Medkit between them, which is a
			complete squad. You can take a real run before you own a single NFT.</p>
		<?php endif; ?>
		<p>In DHC2 Fighters you can go further. Assemble a Fighter out of traits earned anywhere on the
			platform and it drops straight into your barracks as a soldier, scored on its own traits
			alongside the minted collection. Assembling costs nothing, so that theme is open to anyone
			who builds.</p>
		<?php if (!$ds_open): ?>
		<p><?php echo htmlspecialchars($ds_name); ?> is the one that is not open to everybody: it wants the
			VIP role, or a temporary pass bought with DISCOIN.</p>
		<?php endif; ?>
		<p><a class="dsg-cta" href="dropship/dashboard.php<?php
			echo $ds_one ? '?project_id=' . (int)$ds_one['project_id'] : ''; ?>">Play now</a></p>
	</section>

</div>
</body>
</html>

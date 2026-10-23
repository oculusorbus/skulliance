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
$ds_art    = dropshipPublicArt(40);
$ds_crew   = dropshipPublicGrunts();
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
/* NAMED APART ON PURPOSE. These two were both $ds_grunts for a while:
   the four soldiers, and whether this theme hands them out. The claim
   overwrote the array, so the section foreach'd a boolean -- which with
   display_errors on prints a PHP warning into the page. */
$ds_free_squad = $ds_one ? $ds_claims['free_squad'] : false;

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
      "playMode": ["SinglePlayer", "MultiPlayer"],
      "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
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
        { "@type": "Question", "name": "Can I play against another player?",
          "acceptedAnswer": { "@type": "Answer", "text": "Yes. Drop Ship has PvP battles: put up a SCRIP wager and any player who can match it may accept. You both make a run, and whoever reaches the deeper round takes both stakes. A tie returns both wagers." } },
        { "@type": "Question", "name": "What are the five games?",
          "acceptedAnswer": { "@type": "Answer", "text": "Drop Ship, Dread City, Filthy Mermaid, DHC2 Fighters and Oculus Lounge. They share one engine but keep their own board, their own squad, their own art and their own prizes." } }
      ]
    }
  ]
}
</script>
<style>
/*
 * THE PAGE PAINTS ITS OWN GROUND. These pages include site-header.php
 * and nothing else, so nothing upstream sets a background -- without
 * this the whole thing renders as pale grey text on white.
 */
body { background: #07111d; margin: 0; color: #e8eaed; line-height: 1.55;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu,
  Cantarell, sans-serif; overflow-x: hidden; -webkit-font-smoothing: antialiased; }

/* Everything else is scoped to .dsg-, so it cannot reach site-header.php. */
.dsg-wrap { max-width: 1100px; margin: 0 auto; padding: 8px 16px 60px; color: #e8eaed; }
.dsg-hero { text-align: center; }
/* CONTAINED AND CAPPED. The art is portrait (745x850) and the other
   four themes are each a different shape again, so it is fitted rather
   than cropped and bounded by HEIGHT -- a width cap would let the
   landscape one tower over the headline it is supposed to introduce. */
.dsg-cover { display: block; margin: 0 auto 14px; width: auto; height: auto;
  max-width: min(100%, 340px); max-height: 300px; object-fit: contain; }
@media (min-width: 760px) { .dsg-cover { max-height: 360px; max-width: min(100%, 400px); } }
.dsg-hero h1 { margin: 0 0 6px; font-size: clamp(1.9rem, 5vw, 3rem); text-wrap: balance; }
.dsg-sub { display: block; font-size: .8rem; letter-spacing: .12em; text-transform: uppercase;
  color: #00c8a0; margin-top: 8px; }
.dsg-lead { margin: 16px 0 22px; font-size: 1.02rem; line-height: 1.6; color: #c6d4dd; }
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

/*
 * THE MARQUEE. Two counter-scrolling rows, each holding its list TWICE
 * so the -50% translate loops seamlessly -- the same technique
 * dhcgame.php and the homepage partner strip use, which is what the
 * owner meant by "double auto scroll like other pages". The first
 * version was a plain overflow-x box, so it sat still with a scrollbar
 * under it and looked like a bug.
 */
.dsg-strip { width: 100vw; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw);
  overflow: hidden; padding: 14px 0;
  -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
          mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%); }
.dsg-strip + .dsg-strip { padding-top: 0; }
/* The heading sits right on top of the rows now, so it keeps its space
   above and gives it all back below. */
.dsg-sec-tight { margin-bottom: 0; }
.dsg-sec-tight + .dsg-strip { padding-top: 4px; }
.dsg-track { display: flex; align-items: flex-start; gap: 14px; width: max-content;
  animation: dsg-scroll 70s linear infinite; will-change: transform; }
.dsg-track.rev { animation-direction: reverse; animation-duration: 84s; }
.dsg-track:hover { animation-play-state: paused; }
@keyframes dsg-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@media (prefers-reduced-motion: reduce) { .dsg-track { animation: none; } }
.dsg-track figure { flex: 0 0 auto; width: 124px; margin: 0; }
.dsg-track img { width: 124px; height: 124px; object-fit: cover; display: block;
  border: 1px solid rgba(255,255,255,.10); background: #07111d; }
/* ONE LINE, CLIPPED. A long card name must not widen its tile: the -50%
   loop depends on both halves of the track measuring the same, and a
   single wrapped caption would desynchronise the whole row. */
.dsg-track figcaption { margin-top: 5px; font-size: .68rem; line-height: 1.3; color: #7a9eb0;
  text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* The four Grunts, named. */
.dsg-crew { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px;
  margin-top: 18px; }
.dsg-crew figure { margin: 0; background: #07111d; border: 1px solid rgba(255,255,255,.08); }
.dsg-crew img { display: block; width: 100%; aspect-ratio: 1/1; object-fit: cover; background: #07111d; }
.dsg-crew figcaption { padding: 10px 12px 12px; }
.dsg-crew b { display: block; font-size: .96rem; }
.dsg-crew .kit { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: .74rem;
  color: #00c8a0; letter-spacing: .04em; text-transform: uppercase; }
.dsg-crew .kit img { width: 22px; height: 22px; object-fit: contain; border: 0; display: inline-block; }
.dsg-crew .note { margin: 6px 0 0; font-size: .78rem; color: #7a9eb0; }

/* The kit strip and the shelves: the game's own icons, labelled. */
.dsg-kit { margin: 16px 0 4px; display: flex; flex-direction: column; gap: 10px; }
.dsg-kit-row { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; }
.dsg-kit-label { font-size: .7rem; letter-spacing: .12em; text-transform: uppercase;
  color: #00c8a0; width: 52px; flex: 0 0 auto; }
.dsg-kit-item { display: flex; align-items: center; gap: 7px; }
.dsg-kit-item img { width: 40px; height: 40px; object-fit: contain; }
.dsg-kit-item em { font-style: normal; font-size: .82rem; color: #c6d4dd; }

/* A shelf heading wears its own tab icon. */
.dsg-shelf-head { display: flex; align-items: center; gap: 8px; margin: 24px 0 0;
  font-size: .78rem; letter-spacing: .12em; text-transform: uppercase; color: #00c8a0; }
.dsg-shelf-head img { width: 26px; height: 26px; object-fit: contain; }

.dsg-shelf { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px;
  margin-top: 14px; }
.dsg-shelf span { display: flex; flex-direction: column; align-items: center; gap: 6px;
  text-align: center; font-size: .72rem; color: #8fa8b8; }
.dsg-shelf img { width: 44px; height: 44px; object-fit: contain; }

/* The theme wall: whole art, uncropped -- the five are nothing alike in shape. */
.dsg-themes { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px;
  margin-top: 18px; }
.dsg-theme { background: #07111d; border: 1px solid #163047; overflow: hidden; }
.dsg-theme .art { display: block; width: 100%; aspect-ratio: 1/1; background: #07111d; }
.dsg-theme .art img { display: block; width: 100%; height: 100%; object-fit: contain; max-width: 100%; }
.dsg-theme b { display: block; padding: 9px 11px 11px; font-size: .9rem; }
.dsg-theme.on { border-color: #00c8a0; }

/* The footer. Every sibling game page carries one, and a page the
   homepage links to needs a way back out of it. */
.dsg-foot { margin-top: 46px; padding: 26px 0 0; border-top: 1px solid rgba(255,255,255,.07);
  text-align: center; color: #8a96a3; font-size: .86rem; }
.dsg-foot a { color: #8a96a3; }
.dsg-foot a:hover { color: #00c8a0; }
.dsg-foot .also { margin-top: 8px; font-size: .8rem; color: #5f7d90; }

/* The rank ladder: a name and the number of soldiers it wants. */
.dsg-ranks { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 6px;
  margin-top: 14px; }
.dsg-ranks span { display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
  padding: 7px 10px; background: #07111d; border: 1px solid rgba(255,255,255,.06); }
.dsg-ranks b { font-size: .84rem; font-weight: 600; }
.dsg-ranks em { font-style: normal; font-size: .76rem; color: #00c8a0;
  font-variant-numeric: tabular-nums; }

.dsg-faq { margin-top: 18px; }
.dsg-faq details { border-top: 1px solid rgba(255,255,255,.08); padding: 11px 0; }
.dsg-faq summary { cursor: pointer; font-weight: bold; font-size: .95rem; list-style: none; }
.dsg-faq summary::-webkit-details-marker { display: none; }
.dsg-faq summary::after { content: ' +'; color: #00c8a0; }
.dsg-faq details[open] summary::after { content: ' \2212'; }
.dsg-faq p { margin: 8px 0 0; font-size: .9rem; }
</style>
</head>
<body>
<?php include __DIR__ . '/site-header.php'; ?>

<div class="dsg-wrap">

	<header class="dsg-hero">
		<?php /* THE COVER, above the name. The theme's own where one is
		         named, Drop Ship's otherwise -- a Dread City share landing
		         under Drop Ship's art would be advertising the wrong game.
		         Dimensions are stated because the file is 745x850 and
		         without them the headline jumps down the page the moment it
		         decodes. */ ?>
		<img class="dsg-cover" src="<?php echo htmlspecialchars(dropshipPublicCover($ds_slug), ENT_QUOTES); ?>"
		     alt="<?php echo htmlspecialchars(($ds_name !== '' ? $ds_name : 'Drop Ship') . ' cover art', ENT_QUOTES); ?>"
		     width="745" height="850" loading="eager" fetchpriority="high" decoding="async"
		     onerror="this.remove()">
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

	<?php if ($ds_art): ?>
	<?php /* STRAIGHT UNDER THE CALL TO ACTION, at the owner's direction.
	         This was two things in two places: a silent strip above the
	         headline and a static mosaic most of a page further down. The
	         mosaic was the better pitch and the strip was in the better
	         spot, so they are one section now -- the claim, and then the
	         soldiers moving under it. */ ?>
	<section class="dsg-sec dsg-sec-tight">
		<h2>Every one of these is somebody's soldier</h2>
		<p>Drop Ship does not draw its own characters. It reads the cards people already hold and turns
			each one into a soldier, carrying the armor and gear its art says it has. These are real
			ones, pulled straight from the barracks.</p>
	</section>
	<?php
	/*
	 * TWO COUNTER-SCROLLING ROWS, each holding its half TWICE so the -50%
	 * translate loops with no seam. Split down the middle rather than both
	 * rows showing the same soldiers, and the second pass is aria-hidden so
	 * a screen reader meets each one once.
	 *
	 * THE REPEAT COUNT MUST BE EVEN or the two halves differ and the loop
	 * visibly jumps. At 124px plus a 14px gap, sixteen tiles is 2208px,
	 * which clears a wide display; fewer leaves a gap at the turn that
	 * reads as a broken page.
	 */
	$ds_rows = array_chunk($ds_art, max(1, (int)ceil(count($ds_art) / 2)));
	?>
	<?php foreach ($ds_rows as $ri => $ds_row): if (!$ds_row) continue;
	      $ds_passes = max(2, 2 * (int)ceil(16 / max(1, count($ds_row)))); ?>
	<div class="dsg-strip">
		<div class="dsg-track<?php echo $ri % 2 ? ' rev' : ''; ?>">
			<?php /* NAMED ON EVERY PASS, not just the first: the repeats are
			         what is on screen most of the time, and a strip of mostly
			         unlabelled tiles with the occasional name reads as a bug.
			         The duplicate FIGURE is aria-hidden, so a screen reader
			         still meets each soldier once. */ ?>
			<?php for ($pass = 0; $pass < $ds_passes; $pass++): foreach ($ds_row as $a): ?>
			<figure<?php if ($pass) echo ' aria-hidden="true"'; ?>>
				<img src="<?php echo htmlspecialchars($a['url'], ENT_QUOTES); ?>" alt=""
				     loading="eager" decoding="async" width="124" height="124"
				     onerror="this.closest('figure').remove()">
				<figcaption><?php echo htmlspecialchars($a['name'], ENT_QUOTES); ?></figcaption>
			</figure>
			<?php endforeach; endfor; ?>
		</div>
	</div>
	<?php endforeach; ?>
	<?php endif; ?>

	<section class="dsg-sec" id="dsg-how">
		<h2>How a run works</h2>
		<p>You field a squad of four. Each round the run rolls against your squad's armor, and when it
			rolls badly somebody dies. Get far enough and the round number is your score for the week.</p>
		<?php
		/*
		 * THE GAME'S OWN ICONS, not a description of them. These are the
		 * exact files the barracks and the shop draw, so a stranger has
		 * already met the vocabulary before they open the game. Every
		 * filename below was verified 200 on the server before being
		 * pointed at -- images ship by FTP, so a name here is a claim
		 * about the server and not about this checkout.
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

	<?php /* THE FREE SQUAD IS DROP SHIP ONLY. addDefaultSoldiers() is gated
	         on project 1, so a section headed "four soldiers, free, waiting
	         for you" on a Dread City or Oculus Lounge page is a promise the
	         game does not keep. Shown on Drop Ship and on the untargeted
	         page, which is about the family. */ ?>
	<?php if ($ds_crew && ($ds_free_squad || $ds_one === null)): ?>
	<section class="dsg-sec">
		<h2>Four soldiers, free, waiting for you</h2>
		<p>You do not need to own anything to take a real run.
			<?php echo $ds_free_squad ? 'Every player is handed these four' : 'Every Drop Ship player is handed these four'; ?>,
			and they are not filler: between them they carry all four armor tiers and three gear
			types, which is one complete squad. Sektr carrying nothing is deliberate, because there are
			three gear types and four soldiers.</p>
		<div class="dsg-crew">
			<?php foreach ($ds_crew as $g): ?>
			<figure>
				<img src="<?php echo htmlspecialchars(dropshipPublicArtUrl($g['asset']), ENT_QUOTES); ?>"
				     alt="<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>"
				     loading="lazy" decoding="async" onerror="this.remove()">
				<figcaption>
					<b><?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?></b>
					<span class="kit">
						<img src="<?php echo $ds_ico . strtolower($g['armor']); ?>-armor.png" alt=""
						     loading="lazy" width="22" height="22"><?php echo htmlspecialchars($g['armor']); ?>
						<?php if ($g['gear'] !== 'None'): ?>
						<img src="<?php echo $ds_ico . strtolower($g['gear']); ?>.png" alt=""
						     loading="lazy" width="22" height="22"><?php echo htmlspecialchars($g['gear']); ?>
						<?php else: ?>&middot; no gear<?php endif; ?>
					</span>
					<p class="note"><?php echo htmlspecialchars($g['note'], ENT_QUOTES); ?></p>
				</figcaption>
			</figure>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<section class="dsg-sec">
		<h2>The Armory</h2>
		<p>SCRIP earned from runs buys gear, and gear changes the odds the run rolls against. Weapons,
			armor and equipment are all bought the same way and all fire once per squad. What you own
			sits in your inventory until you send it out with a squad.</p>
		<?php
		/* The shop's real inventory icons. Three shelves, named exactly as
		   the Armory names them. All verified live. */
		/* HEADED BY THE ARMORY'S OWN TAB ICONS -- the same four the game
		   puts across the top of that panel, so the three shelves below
		   are recognisable before a player has ever opened it. */
		$ds_shelves = array(
			'Weapons'   => array('icon' => 'weapons', 'items' => array(
			                     'tactical-katana' => 'Tactical Katana', 'sniper-rifle' => 'Sniper Rifle',
			                     'machine-gun' => 'Machine Gun', 'rocket-launcher' => 'Rocket Launcher',
			                     'flamethrower' => 'Flamethrower', 'grenade' => 'Grenade')),
			'Armor'     => array('icon' => 'shield', 'items' => array(
			                     'ballistic-shield' => 'Ballistic Shield', 'exo-suit' => 'Exo Suit',
			                     'mech-suit' => 'Mech Suit', 'quantum-stealth' => 'Quantum Stealth')),
			'Equipment' => array('icon' => 'equipment', 'items' => array(
			                     'radar' => 'Radar', 'radio' => 'Radio', 'jet-pack' => 'Jet Pack',
			                     'night-vision-goggles' => 'Night Vision', 'smoke-bomb' => 'Smoke Bomb',
			                     'drop-box' => 'Drop Box')),
		);
		foreach ($ds_shelves as $label => $shelf): $items = $shelf['items']; ?>
		<h3 class="dsg-shelf-head">
			<img src="<?php echo $ds_ico . $shelf['icon']; ?>.png" alt="" loading="lazy"
			     width="26" height="26" onerror="this.remove()"><?php echo $label; ?></h3>
		<div class="dsg-shelf">
			<?php foreach ($items as $f => $n): ?>
			<span><img src="<?php echo $ds_ico . $f; ?>.png" alt="" loading="lazy"
				width="44" height="44" onerror="this.parentNode.remove()"><?php echo htmlspecialchars($n); ?></span>
			<?php endforeach; ?>
		</div>
		<?php endforeach; ?>
	</section>

	<section class="dsg-sec">
		<h2>Or wager it on somebody</h2>
		<p>The weekly board is not the only thing to play for. Put up a SCRIP wager, and anyone with the
			balance to match it can take the battle. You both make a run, and whoever got deeper takes
			both stakes. A tie gives everyone their SCRIP back.</p>
		<p>It is the same run either way, so a battle costs you nothing but the wager, and there is no
			queue to wait in: open battles sit on the board until somebody accepts one.</p>
		<div class="dsg-shelf">
			<?php foreach (array('dropship' => 'Create a battle', 'scrip' => 'Name your wager',
			                     'locked' => 'Wait for a taker', 'average' => 'Both make a run',
			                     'trophy' => 'Deeper run takes both') as $f => $n): ?>
			<span><img src="<?php echo $ds_ico . $f; ?>.png" alt="" loading="lazy"
				width="44" height="44" onerror="this.parentNode.remove()"><?php echo htmlspecialchars($n); ?></span>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="dsg-sec">
		<h2>What the game keeps on you</h2>
		<p>Every run feeds a record: how many you have played, what you average, your best, the SCRIP
			you have banked and the XP that moves you up a level.</p>
		<div class="dsg-shelf">
			<?php foreach (array('scoreboard' => 'Games played', 'average' => 'Average score',
			                     'trophy' => 'Top score', 'level' => 'Level', 'xp' => 'XP',
			                     'progress' => 'Progress', 'rank' => 'Rank',
			                     'scrip' => 'SCRIP') as $f => $n): ?>
			<span><img src="<?php echo $ds_ico . $f; ?>.png" alt="" loading="lazy"
				width="44" height="44" onerror="this.parentNode.remove()"><?php echo htmlspecialchars($n); ?></span>
			<?php endforeach; ?>
		</div>

		<?php /* THE LADDER IS COLLECTION SIZE, and saying so is better than
		         letting somebody assume it is skill. It is also the one
		         number on this page that rewards owning more, which is worth
		         being straight about rather than burying. */ ?>
		<h3 style="font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:#00c8a0;margin:26px 0 0;">
			Ranks, by soldiers held</h3>
		<div class="dsg-ranks">
			<?php foreach (dropshipPublicRanks() as $rank => $need): ?>
			<span><b><?php echo htmlspecialchars($rank); ?></b><em><?php echo (int)$need; ?></em></span>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="dsg-sec">
		<h2>How far did you get</h2>
		<p>The run has landmarks before it has a score. Reach them and they stay on your record, along
			with the rounds you survived and the SCRIP you earned.</p>
		<div class="dsg-shelf">
			<?php foreach (array('beach' => 'Stormed the Beach', 'hill' => 'Climbed the Hill',
			                     'bunker' => 'Entered the Bunker', '20' => 'Survived 20',
			                     '30' => 'Survived 30', '40' => 'Survived 40', '50' => 'Survived 50',
			                     'scrip' => 'SCRIP earned', 'trophy' => 'Won an NFT') as $f => $n): ?>
			<span><img src="<?php echo $ds_ico . $f; ?>.png" alt="" loading="lazy"
				width="44" height="44" onerror="this.parentNode.remove()"><?php echo htmlspecialchars($n); ?></span>
			<?php endforeach; ?>
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


	<section class="dsg-sec">
		<h2>Questions</h2>
		<div class="dsg-faq">
			<details open><summary>Is it free?</summary>
				<p>Yes, and you do not need to own anything. The four soldiers above are waiting in your
					Drop Ship barracks and they make one complete squad. Oculus Lounge is the one theme
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
			<details><summary>Can I play against someone?</summary>
				<p>Yes. Put up a SCRIP wager and anyone who can match it may accept. You both make a run
					and the deeper one takes both stakes; a tie returns them.</p></details>
			<details><summary>What are the five games?</summary>
				<p>Drop Ship, Dread City, Filthy Mermaid, DHC2 Fighters and Oculus Lounge. One engine, but
					each keeps its own board, squad, art and prizes.</p></details>
		</div>
	</section>

	<section class="dsg-sec">
		<h2>Bring your own</h2>
		<p>NFTs you hold become soldiers alongside the free four, carrying the armor and gear their art
			says they have. In DHC2 Fighters you can go further: assemble a Fighter out of traits earned
			anywhere on the platform and it drops straight into your barracks as a soldier, scored on its
			own traits alongside the minted collection. Assembling costs nothing, so that theme is open
			to anyone who builds.</p>
		<?php if (!$ds_open): ?>
		<p><?php echo htmlspecialchars($ds_name); ?> is the one that is not open to everybody: it wants the
			VIP role, or a temporary pass bought with DISCOIN.</p>
		<?php endif; ?>
		<p><a class="dsg-cta" href="dropship/dashboard.php<?php
			echo $ds_one ? '?project_id=' . (int)$ds_one['project_id'] : ''; ?>">Play now</a></p>
	</section>


	<footer class="dsg-foot">
		<p>Part of <a href="https://www.skulliance.io/">Skulliance</a>, an NFT platform on Cardano with
			a dozen games on it.</p>
		<p class="also">Also try
			<a href="dhcgame.php">DHC Fighters</a>,
			<a href="guardiansgame.php">Realm Guardians</a>,
			<a href="cryptcrawlgame.php">Crypt Crawl</a>,
			<a href="cryptconquestgame.php">Crypt Conquest</a> and
			<a href="skullracergame.php">Skull Racer</a>.</p>
		<?php /* RELATIVE, like every other link out of here: the login cookie
		         is host-only, so an absolute one can drop a visitor onto the
		         host they are not signed in on. The Skulliance link above is
		         the exception, and deliberately -- it is the marketing site,
		         not a page anybody is signed in to. */ ?>
		<p class="also"><a href="skullpaper.php?page=games-drop-ship">Read the rules in full</a></p>
	</footer>

</div>
</body>
</html>

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
$ds_canonical = 'https://www.skulliance.io/staking/dropshipgame.php';

/* The shared theme, when there is one. Validated against the live list, so
   an invented slug falls back to the generic page rather than 404ing
   someone's post. */
$ds_one  = isset($_GET['theme']) ? dropshipPublicTheme($_GET['theme']) : null;
$ds_slug = $ds_one ? $ds_one['slug'] : '';
$ds_name = $ds_one ? $ds_one['name'] : '';

$ds_og_image = $ds_slug !== ''
	? 'https://www.skulliance.io/staking/dropship-card.php?theme=' . rawurlencode($ds_slug)
	: 'https://www.skulliance.io/staking/dropship-card.php?theme=drop-ship';

$ds_title = $ds_name !== ''
	? $ds_name . ' - Free Weekly NFT Squad Battler | Play in Your Browser'
	: 'Drop Ship - Free Weekly NFT Squad Battler, Five Games on One Engine | Skulliance';
$ds_desc  = $ds_name !== ''
	? 'Play ' . $ds_name . ' free on Skulliance. Send a squad of four into a run that gets harder every round, '
	. 'see how far they get, and take your place on a leaderboard that resets every week. Your squads build '
	. 'themselves from what you hold, so there is nothing to manage before you play.'
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
</style>
</head>
<body>
<?php include __DIR__ . '/site-header.php'; ?>

<div class="dsg-wrap">

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
			<span class="dsg-badge">Free to play</span>
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

	<section class="dsg-sec">
		<h2>Start without owning anything</h2>
		<p>Every player is given four free soldiers, and they are not filler: Hagar, Delay, Blood Bag and
			Sektr carry Heavy, Medium, Light and Base armor with a Melee, a Demolition and a Medkit
			between them, which is a complete squad. You can take a real run before you own a single NFT.</p>
		<p>In DHC2 Fighters you can go further. Assemble a Fighter out of traits earned anywhere on the
			platform and it drops straight into your barracks as a soldier, scored on its own traits
			alongside the minted collection.</p>
		<p><a class="dsg-cta" href="dropship/dashboard.php<?php
			echo $ds_one ? '?project_id=' . (int)$ds_one['project_id'] : ''; ?>">Play now</a></p>
	</section>

</div>
</body>
</html>

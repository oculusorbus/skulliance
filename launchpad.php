<?php
/*
 * Launchpad -- what a player sees immediately after logging in.
 *
 * The old landing was profile.php, and before that people were expected to
 * find things through the nav: four dropdowns holding ~26 destinations. That
 * works if you already know what you're looking for and is close to useless
 * if you don't. This page puts the whole platform on one screen, grouped, so
 * a new member can see what there is to do without opening a single menu, and
 * a regular can get to any of it in one click.
 *
 * dashboard.php is NOT this page and never was -- despite the name it is a
 * view of your staked NFTs. It stays exactly as it is; this is the page the
 * name promised.
 *
 * Everything here is navigation plus three cheap personal numbers. No game
 * logic, no writes, nothing that can fail in a way that costs a player
 * anything -- deliberately, because this is now the first thing that loads
 * after login and it must never be the thing that breaks.
 */
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';

// 'name' is the key the session actually carries -- it is what header.php
// prints in the navbar and what skullpaper.php reads. 'username' exists in
// some rows but is not reliably in the session, so reading it alone gave an
// empty greeting for a logged-in player. Both, name first.
$lp_user     = $_SESSION['userData']['name'] ?? ($_SESSION['userData']['username'] ?? '');
$lp_user_id  = intval($_SESSION['userData']['user_id'] ?? 0);
$lp_discord  = $_SESSION['userData']['discord_id'] ?? '';
$lp_avatar   = $_SESSION['userData']['avatar'] ?? '';
$lp_avatar_url = ($lp_discord && $lp_avatar)
	? avatar_url($lp_discord, $lp_avatar)
	: "icons/skull.png";

// Three cheap signals, all from helpers that already exist. Each is wrapped
// so a failure degrades that one number rather than the page.
$lp_wallets = 0;
$lp_streak  = 0;
$lp_balance = 0;
if ($lp_user_id > 0) {
	$w = getWallets($conn);
	$lp_wallets = is_array($w) ? count($w) : 0;
	$s = getCurrentDailyRewardStreak($conn);
	$lp_streak  = intval(is_array($s) ? ($s['streak'] ?? 0) : $s);
	$lp_balance = intval(getCurrentBalance($conn, $lp_user_id, 0));
}

/*
 * The platform, as a player sees it. Mirrors the nav's own grouping (Play /
 * NFTs / Stats / Account) rather than inventing a second taxonomy, so someone
 * who learns this page can still find things in the menu afterwards.
 *
 * Emoji rather than image icons: images deploy by FTP outside this repo, so
 * 26 new icons would mean 26 uploads that have to land before this page stops
 * looking broken -- and this is the first page after login.
 */
$lp_sections = array(
	'Play' => array(
		'accent' => '#ffcc44',
		'blurb'  => 'Every game on the platform. Most pay CARBON just for finishing a run.',
		'items'  => array(
			array('missions.php',        '🗺️', 'Missions',        'Send NFTs on timed runs for points'),
			array('realms.php',          '🏰', 'Realms',          'Build a realm, raid your rivals'),
			array('guardians.php',       '🛡️', 'Realm Guardians', 'Hold your realm against the horde'),
			array('cryptcrawl.php',      '💀', 'Crypt Crawl',     'Solo dungeon card game'),
			array('cryptconquest.php',   '👑', 'Crypt Conquest',  'Regicide-style card game'),
			array('monstrocity.php',     '👾', 'Monstrocity',     'Match 3 RPG campaign'),
			array('monstrocity.php#boss', '🐉', 'Boss Battles',    'Co-op community boss fights'),
			array('skullswap.php',       '🔄', 'Skull Swap',      'Match 3 score chase'),
			array('skullracer.php',      '🏁', 'Skull Racer',     'Pseudo-3D arcade racer'),
			array('gauntlets.php',       '🥊', 'Gauntlets',       'NFT roguelike run'),			
			array('obscura.php',         '🔍', 'Obscura',         'Name the collection from a sliver'),
			// Listed with the games because it is the reason to play them: every
			// game on this list pays a different part of a Fighter.
			array('dhcfighters.php',     '🧬', 'DHC Fighters',    'Earn traits, assemble Fighters, top the board'),
			// Straight after DHC Fighters, because it is the only thing on this
			// list you cannot reach without one: entering costs a Crew of three.
			array('dhcarena.php',        '🏟️', 'DHC Arena',       'Send a Crew of three against a rival'),
			// dashboard.php, NOT the bare directory. `dropship/` serves index.php,
			// which is an unconditional redirect to the Skulliance login -- so a
			// player who was ALREADY logged in got bounced to a login page. The
			// nav link in header.php has always pointed at dashboard.php, which is
			// why that one worked and this one did not.
			// ?project_id= on BOTH, not just the Lounge. Drop Ship and Oculus
			// Lounge are one codebase switched by a session value, so without
			// it each tile opened whichever game the session was last left on
			// -- click Oculus Lounge, then Drop Ship, and you got the Lounge
			// again. dropship.php validates the id against getProjects().
			array('dropship/dashboard.php?project_id=1', '🪖', 'Drop Ship',    'NFT battler'),
			array('dropship/dashboard.php?project_id=4', '🪩', 'Oculus Lounge', 'NSFW Club Crawler'),
		),
	),
	'Collect' => array(
		'accent' => '#4fa3ff',
		'blurb'  => 'Your NFTs, and the places to get more of them.',
		'items'  => array(
			array('my-nfts.php',         '🎒', 'My NFTs',         'Everything you have staked'),
			array('store.php',           '🏪', 'Store',           'Free member claims'),
			array('auctions.php',        '🔨', 'Auctions',        'Bid on NFTs'),
			array('raffles.php',         '🎟️', 'Raffles',         'Ticketed draws'),
			array('gallery.php',         '🖼️', 'Gallery',         'Browse the collections'),
			array('collections.php',     '📚', 'Collections',     'Every supported project'),
			array('musicvideos.php',     '🎬', 'Music Videos',    'Made with the projects\' art'),
			array('diamond-skulls.php',  '💎', 'Diamond Skulls',  'Delegation rewards'),
			array('skulliverse.php',     '🌌', 'Skulliverse',     'The wider universe'),
		),
	),
	'Progress' => array(
		'accent' => '#00c8a0',
		'blurb'  => 'Where you stand, and what your points are worth.',
		'items'  => array(
			array('leaderboards.php',    '🏆', 'Leaderboards',    'Every board, with who leads it'),
			array('profile.php',         '🪪', 'Profile',         'Your public page'),
			array('points.php',          '⚡', 'Points',          'What you have earned'),
			array('crafting.php',        '⚗️', 'Crafting',        'Burn CARBON for DIAMOND'),
			array('transactions.php',    '🧾', 'Transactions',    'Your full history'),
			array('wallets.php',         '👛', 'Wallets',         'Connected addresses'),
			array('analytics.php',       '📊', 'Analytics',       'Platform-wide numbers'),
			array('skullpaper.php',      '📖', 'Skull Paper',     'How all of it works'),
		),
	),
);

/*
 * Start Here -- only rendered while something is genuinely incomplete, and it
 * disappears on its own once the account is set up. A permanent checklist a
 * veteran can never clear is nagging, not onboarding.
 */
/*
 * Greeting. "Welcome back" on someone's FIRST ever login is exactly the wrong
 * note to open on, and it would have hit every new member, since a
 * Discord-authenticated user always has a name.
 *
 * There is no join date on the users table (INSERT INTO users only writes
 * discord_id, avatar, username), so "first login" cannot be read directly.
 * These three signals answer the useful question instead: an account with no
 * wallet, no points and no streak has not started yet, whether that is their
 * first minute or their third visit. It also means the greeting and the Start
 * Here list below always agree -- the condition that shows all three tasks is
 * the same condition that drops the "back".
 */
$lp_started = ($lp_wallets > 0 || $lp_balance > 0 || $lp_streak > 0);
if ($lp_user === '') {
	$lp_greeting = 'Welcome to Skulliance';
} else if ($lp_started) {
	$lp_greeting = 'Welcome back, ' . $lp_user;
} else {
	$lp_greeting = 'Welcome to Skulliance, ' . $lp_user;
}

$lp_todo = array();
if ($lp_wallets === 0) {
	$lp_todo[] = array('wallets.php', 'Connect a wallet', 'Nothing can be staked or rewarded until Skulliance can see your NFTs.');
}
if ($lp_streak === 0) {
	$lp_todo[] = array('points.php', 'Claim your daily reward', 'Free points every day, and the streak is its own leaderboard.');
}
if ($lp_balance === 0) {
    $lp_todo[] = array('skullracer.php', 'Play a game', 'Skull Racer pays CARBON for finishing a race, win or lose.');
}

/*
 * ONE SUGGESTION A DAY, FOR PLAYERS RUNNING ONE LOOP.
 *
 * Start Here above is ONBOARDING -- no wallet, no streak, no points --
 * and it disappears the moment an account is set up. This is the opposite
 * end: somebody long past setup who has settled into Missions and the
 * daily claim and never found the other dozen things.
 *
 * NOT AIMED AT QUIET PLAYERS. Reading the Activity wall, the narrowest
 * accounts include some of the busiest on the platform -- ninth by
 * weighted score, three sources touched. Ranking by score would miss
 * every one of them and pester the people who have already tried
 * everything. The test is breadth, never volume. See lib/nudge-lib.php.
 *
 * NEVER BOTH AT ONCE. A new account has enough to read already, so this
 * waits until Start Here is done.
 */
$lp_nudge = null;
if (empty($lp_todo) && isset($_SESSION['userData']['user_id'])) {
	require_once __DIR__ . '/lib/nudge-lib.php';
	$lp_uid = (int)$_SESSION['userData']['user_id'];
	/* One indexed read, and on most loads it ends here. */
	if (!nudge_quiet($conn, $lp_uid)) {
		$lp_print = nudge_footprint($conn, $lp_uid);
		if (nudge_breadth($lp_print) <= NUDGE_NARROW_MAX) {
			$lp_facts = array(
				'has_realm'   => function_exists('checkRealm') ? (bool)checkRealm($conn) : true,
				'fighters'    => isset($lp_print['fighter']) ? (int)$lp_print['fighter'] : 0,
				'free_traits' => 0,
			);
			/* Unused traits, which is the strongest nudge we can make
			   because the number comes off their own account. */
			if (function_exists('dhcf_available')) {
				foreach (dhcf_available($conn, $lp_uid) as $cat => $traits) {
					foreach ($traits as $t) $lp_facts['free_traits'] += (int)$t['free'];
				}
			}
			$lp_nudge = nudge_pick(nudge_candidates($lp_print, $lp_facts),
			                       nudge_history($conn, $lp_uid));
			if ($lp_nudge) {
				$lp_nudge['head'] = nudge_headline($lp_print);
				/* Logged as shown BEFORE it renders, so a reload does not
				   count twice and does not offer a second one today. */
				nudge_log($conn, $lp_uid, $lp_nudge['key'], 'shown');
			}
		} else {
			/* Broad already. Record it so the fifteen queries do not run
			   again today just to reach the same answer. */
			nudge_log($conn, $lp_uid, 'broad', 'shown');
		}
	}
}
?>

<!-- col1of3 with the flex override is the site's full-width centred column --
     the same pattern leaderboards.php uses. There is no ".col1" class in
     flexbox.css; using one meant the div had no width rule at all, .row's
     flex shrank it to its content, and 26 tiles rendered in a 455px strip
     against 2800px of empty page. -->
<div class="row" id="row1">
  <div class="col1of3" style="max-width:1100px;margin:0 auto;flex:1 1 100%;">

	<div class="lp-hero">
		<img class="lp-hero-avatar" src="<?php echo htmlspecialchars($lp_avatar_url); ?>" alt=""
		     onerror="this.src='icons/skull.png';">
		<div class="lp-hero-text">
			<h2><?php echo htmlspecialchars($lp_greeting); ?></h2>
			<div class="lp-hero-stats">
				<span><strong><?php echo number_format($lp_balance); ?></strong> points</span>
				<span><strong><?php echo number_format($lp_streak); ?></strong> day streak</span>
				<span><strong><?php echo number_format($lp_wallets); ?></strong> wallet<?php echo $lp_wallets === 1 ? '' : 's'; ?></span>
			</div>
		</div>
	</div>

	<?php if (!empty($lp_todo)): ?>
	<div class="lp-todo">
		<h3>Start Here</h3>
		<?php foreach ($lp_todo as $t): ?>
			<a class="lp-todo-item" href="<?php echo htmlspecialchars($t[0]); ?>">
				<span class="lp-todo-title"><?php echo htmlspecialchars($t[1]); ?></span>
				<span class="lp-todo-why"><?php echo htmlspecialchars($t[2]); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ($lp_nudge): ?>
	<?php /* ITS OWN LOOK, not Start Here's teal. Start Here is a checklist
	         of things that are wrong; this is an invitation, and a player
	         who sees the same box for both learns to skim both. */ ?>
	<div class="lp-nudge" id="lp-nudge">
		<h3>Something else to try</h3>
		<p class="lp-nudge-head"><?php echo htmlspecialchars($lp_nudge['head']); ?></p>
		<a class="lp-nudge-item" href="<?php echo htmlspecialchars($lp_nudge['url']); ?>"
		   data-key="<?php echo htmlspecialchars($lp_nudge['key']); ?>">
			<span class="lp-nudge-title"><?php echo htmlspecialchars($lp_nudge['title']); ?></span>
			<span class="lp-nudge-why"><?php echo htmlspecialchars($lp_nudge['why']); ?></span>
			<span class="lp-nudge-cta"><?php echo htmlspecialchars($lp_nudge['cta']); ?> &rarr;</span>
		</a>
		<button type="button" class="lp-nudge-off" id="lp-nudge-off">Not now, and not for a week</button>
	</div>
	<script>
	(function () {
		var box = document.getElementById('lp-nudge');
		if (!box) return;
		var key = box.querySelector('.lp-nudge-item').getAttribute('data-key');
		function tell(action, then) {
			/* keepalive so the click-through is recorded even though the
			   browser is already navigating away from this page. */
			try {
				fetch('ajax/nudge.php', {
					method: 'POST', keepalive: true,
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: 'action=' + encodeURIComponent(action) + '&key=' + encodeURIComponent(key)
				}).then(then || function () {}, then || function () {});
			} catch (e) { if (then) then(); }
		}
		box.querySelector('.lp-nudge-item').addEventListener('click', function () { tell('clicked'); });
		document.getElementById('lp-nudge-off').addEventListener('click', function () {
			/* Hidden immediately. The write is the server's problem and a
			   failed one must not leave the box sitting there looking
			   like the button is broken. */
			box.hidden = true;
			tell('snoozed');
		});
	}());
	</script>
	<?php endif; ?>

	<?php foreach ($lp_sections as $lp_name => $lp_sec): ?>
	<div class="lp-section" style="--lp-accent:<?php echo $lp_sec['accent']; ?>">
		<h3 class="lp-section-head"><?php echo htmlspecialchars($lp_name); ?></h3>
		<div class="lp-section-blurb"><?php echo htmlspecialchars($lp_sec['blurb']); ?></div>
		<div class="lp-grid">
			<?php foreach ($lp_sec['items'] as $it): ?>
				<a class="lp-tile" href="<?php echo htmlspecialchars($it[0]); ?>">
					<span class="lp-tile-icon"><?php echo $it[1]; ?></span>
					<span class="lp-tile-name"><?php echo htmlspecialchars($it[2]); ?></span>
					<span class="lp-tile-desc"><?php echo htmlspecialchars($it[3]); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endforeach; ?>

  </div>
</div>

<style>
.lp-hero {
	display: flex; align-items: center; gap: 16px;
	padding: 18px 4px 22px;
}
.lp-hero-avatar { width: 64px; height: 64px; border-radius: 50%; border: 3px solid #00c8a0; object-fit: cover; flex-shrink: 0; }
.lp-hero-text h2 { margin: 0 0 6px; }
.lp-hero-stats { display: flex; flex-wrap: wrap; gap: 18px; font-size: 0.82rem; color: rgba(255,255,255,0.55); }
.lp-hero-stats strong { color: #00c8a0; font-size: 1rem; }

/* Start Here disappears once the account is set up -- see $lp_todo. */
.lp-todo {
	background: rgba(0,200,160,0.06);
	border: 1px solid rgba(0,200,160,0.3);
	border-radius: 8px;
	padding: 14px 16px;
	margin-bottom: 34px;
}
.lp-todo h3 { margin: 0 0 10px; font-size: 0.8rem; letter-spacing: 0.14em; text-transform: uppercase; color: #00c8a0; }
.lp-todo-item { display: block; padding: 9px 0; text-decoration: none; color: inherit; border-top: 1px solid rgba(255,255,255,0.07); }
.lp-todo-item:first-of-type { border-top: 0; }
.lp-todo-title { display: block; font-weight: bold; font-size: 0.92rem; }
.lp-todo-title::after { content: ' \2192'; color: #00c8a0; }
.lp-todo-why  { display: block; font-size: 0.76rem; color: rgba(255,255,255,0.5); margin-top: 2px; }

/* The nudge. Ochre rather than teal: Start Here is a checklist and this
   is an invitation, and two boxes that look identical get skimmed alike. */
.lp-nudge {
	background: rgba(214,158,46,0.07);
	border: 1px solid rgba(214,158,46,0.32);
	border-radius: 8px;
	padding: 14px 16px;
	margin-bottom: 34px;
}
.lp-nudge h3 { margin: 0 0 4px; font-size: 0.8rem; letter-spacing: 0.14em;
	text-transform: uppercase; color: #d69e2e; }
.lp-nudge-head { margin: 0 0 10px; font-size: 0.8rem; color: rgba(255,255,255,0.55); }
/* text-align RESTATED, not inherited. flexbox.css sets `a {text-align:center}`
   globally, so this anchor came out centred under a left-aligned headline
   and above a left-aligned button -- the card disagreeing with itself.
   Caught in a render, not in review. */
.lp-nudge-item { display: block; padding: 10px 0; text-decoration: none; color: inherit;
	text-align: left; border-top: 1px solid rgba(255,255,255,0.07); }
.lp-nudge-title { display: block; font-weight: bold; font-size: 0.98rem; }
.lp-nudge-why { display: block; font-size: 0.8rem; color: rgba(255,255,255,0.6); margin-top: 3px; }
.lp-nudge-cta { display: inline-block; margin-top: 8px; font-size: 0.78rem; font-weight: bold;
	letter-spacing: 0.04em; color: #d69e2e; }
/* A button, not a link: it performs an action on this page and goes
   nowhere, and flexbox.css paints every bare anchor teal. */
.lp-nudge-off { display: block; margin: 10px 0 0; padding: 0; background: none; border: 0;
	font: inherit; font-size: 0.74rem; color: rgba(255,255,255,0.38); cursor: pointer;
	text-align: left; }
.lp-nudge-off:hover { color: rgba(255,255,255,0.7); text-decoration: underline; }

.lp-section { margin-bottom: 38px; }
.lp-section-head {
	margin: 0 0 4px; font-size: 0.9rem; font-weight: bold;
	letter-spacing: 0.16em; text-transform: uppercase;
	color: rgba(255,255,255,0.85);
	border-bottom: 2px solid var(--lp-accent, #00c8a0); padding-bottom: 8px;
}
.lp-section-blurb { font-size: 0.78rem; color: rgba(255,255,255,0.45); margin: 8px 0 14px; }

/* auto-fit so the grid reflows from five across to one on a phone with no
   breakpoints to maintain -- same approach as the leaderboard hub. */
.lp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; align-items: stretch; }
.lp-tile {
	display: flex; flex-direction: column;
	background: #0d1e30;
	border: 1px solid rgba(255,255,255,0.06);
	border-top: 2px solid var(--lp-accent, #00c8a0);
	border-radius: 8px;
	padding: 14px;
	text-decoration: none; color: inherit;
	transition: background-color 0.15s ease, transform 0.15s ease;
}
.lp-tile:hover { background: #10263c; transform: translateY(-2px); }
.lp-tile-icon { font-size: 1.6rem; line-height: 1; margin-bottom: 8px; }
.lp-tile-name { font-weight: bold; font-size: 0.95rem; }
.lp-tile-desc { font-size: 0.73rem; color: rgba(255,255,255,0.45); margin-top: 4px; }
</style>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>

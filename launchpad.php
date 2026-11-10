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
/*
 * WHAT THIS ACCOUNT CAN ACTUALLY DO. Two indexed COUNTs, memoised, and a
 * failure reads as "unknown", which renders exactly like the page did
 * before any of this existed. See lib/player-state.php for why unknown
 * resolves to AVAILABLE rather than locked.
 */
require_once __DIR__ . '/lib/player-state.php';
$lp_state = player_state($conn);

require_once __DIR__ . '/lib/launchpad-stats.php';

$lp_wallets  = 0;
$lp_streak   = 0;
$lp_balance  = 0;
$lp_missions = 0;
$lp_raids    = 0;
if ($lp_user_id > 0) {
	$w = getWallets($conn);
	$lp_wallets = is_array($w) ? count($w) : 0;
	$s = getCurrentDailyRewardStreak($conn);
	$lp_streak  = intval(is_array($s) ? ($s['streak'] ?? 0) : $s);
	$lp_balance = intval(getCurrentBalance($conn, $lp_user_id, 0));

	/*
	 * WHAT THE ACCOUNT HAS DONE: two COUNTs on indexed columns, each
	 * wrapped so a failure is a zero rather than a dead first page.
	 *
	 * COUNTED THE WAY THE LEADERBOARDS COUNT, every row regardless of
	 * outcome -- checkMissionsLeaderboard's total is COUNT(missions.id)
	 * and checkRaidsLeaderboard's is COUNT(raids.id), neither filtered
	 * on status. A page that said 40 missions next to a board that said
	 * 52 would be a bug report, and the honest reading of "missions" is
	 * ones you sent, not ones that came back.
	 *
	 * Raids hang off the realm, not the user: raids.offense_id is a
	 * realms.id, so the user only reaches them through their own realm.
	 */
	$lp_q = @$conn->query("SELECT COUNT(id) AS c FROM missions WHERE user_id = '" . $lp_user_id . "'");
	if ($lp_q && ($lp_r = $lp_q->fetch_assoc())) $lp_missions = (int)$lp_r['c'];
	$lp_q = @$conn->query("SELECT COUNT(raids.id) AS c FROM raids
	                       INNER JOIN realms ON realms.id = raids.offense_id
	                       WHERE realms.user_id = '" . $lp_user_id . "'");
	if ($lp_q && ($lp_r = $lp_q->fetch_assoc())) $lp_raids = (int)$lp_r['c'];
}

/* THE CATALOGUE LIVES IN lib/launchpad-catalogue.php, because
   admin-outreach.php reads the same list to tell a partner community what
   is on the platform now. Two copies of a list are one list and one bug --
   the same reason admin_tabs() exists. */
require_once __DIR__ . '/lib/launchpad-catalogue.php';

/*
 * Start Here -- only rendered while something is genuinely incomplete, and it
 * disappears on its own once the account is set up. A permanent checklist a
 * veteran can never clear is nagging, not onboarding.
 */
/*
 * THE NAME, AND NOTHING WRAPPED AROUND IT.
 *
 * This used to pick between "Welcome back, <name>" and "Welcome to
 * Skulliance, <name>" off three signals, to avoid greeting a brand new
 * member as a returning one. The greeting itself turned out to be the
 * part worth cutting: it is the first thing on the page every single
 * visit, it says nothing the player does not know, and it pushed the
 * one piece of information up there -- whose account this is -- into
 * being a suffix. The name alone also stops the line running the width
 * of a 2000px band.
 *
 * The empty case stays. The page is login-gated, so a missing name is a
 * session that half-read, and a bare heading with nothing in it is a
 * worse failure than a generic one.
 */
$lp_greeting = $lp_user !== '' ? $lp_user : 'Skulliance';

$lp_todo = array();
if ($lp_wallets === 0) {
	$lp_todo[] = array('wallets.php', 'Connect a wallet', 'Nothing can be staked or rewarded until Skulliance can see your NFTs.');
}
if ($lp_streak === 0) {
	/* MISSIONS, NOT POINTS. points.php still carries the original
	   seven-row renderer and its own claim button; missions.php is where
	   the reward is a chest that opens, which is the version worth
	   sending somebody to on the day they first claim one. The deep link
	   lands on the daily strip rather than the top of the page. */
	/* THE CHEST COMES WITH IT. This row is the one thing on the page we
	   most want pressed, and it now has art of its own on the other end
	   of the link -- so it shows the closed chest here, which is the
	   state the account is actually in while this row exists at all. A
	   fourth element, optional, read only by the Start Here loop. */
	$lp_todo[] = array('missions.php#ms-sec-daily', 'Claim your daily reward',
	                   'Free points every day, and the streak is its own leaderboard.',
	                   'images/chest/closed.webp');
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
	$lp_state = nudge_state($conn, $lp_uid);
	if ($lp_state['snoozed'] || $lp_state['resting'] || $lp_state['recent']) {
		/* Nothing to do. */
	} elseif (nudge_fatigued($lp_state)) {
		/*
		 * TEN OFFERS, NOT ONE TAKEN. That is an answer, and asking an
		 * eleventh time is not a nudge. Recorded with its own expiry so
		 * it lifts by itself in a quarter rather than needing anybody to
		 * remember it exists.
		 */
		nudge_log($conn, $lp_uid, 'rest', 'rested',
		          date('Y-m-d H:i:s', strtotime('+' . NUDGE_REST_DAYS . ' days')));
	} else {
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
				   count twice and does not offer another inside the gap. */
				nudge_log($conn, $lp_uid, $lp_nudge['key'], 'shown');
			} else {
				/*
				 * NOTHING LEFT TO SUGGEST, AND THAT HAS TO BE RECORDED.
				 * Without this the gate never closes: no row is written,
				 * nudge_state() keeps answering "no recent nudge", and
				 * the fifteen activity queries plus the trait count run
				 * again on every single Launchpad load until a candidate
				 * frees up. 'none' is not 'shown', so it does not count
				 * against the fatigue rule -- we did not ask.
				 */
				nudge_log($conn, $lp_uid, 'none', 'none');
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
  <?php /*
   * FULL WIDTH, TO THE SAME EDGE AS THE MENU.
   *
   * max-width:none is the whole fix and it has to be inline. Removing
   * this page's own 1100px cap was not enough: flexbox.css:2592 puts a
   * SECOND cap of 900px on .col1of3, which is why the nav measured
   * 2000px and the tiles 900 -- the column was being centred inside a
   * container twice its width. That rule is platform-wide and other
   * pages want it, so it is overridden here rather than changed there.
   *
   * 2000px is the ceiling either way: .container caps at that and the
   * navbar lives inside it, so matching the menu IS matching 2000.
   */ ?>
  <div class="col1of3" style="margin:0 auto;flex:1 1 100%;max-width:none;">

	<?php /*
	 * THE BANNER. The greeting and the stats sit ON the art; Start Here
	 * sits on it too but keeps a panel of its own, because it is a list
	 * of links you act on rather than text you read.
	 *
	 * TEXT AT THE BOTTOM, ART AT THE TOP, and that is measured rather
	 * than chosen: the image runs from a mean luminance of 66 across its
	 * top eighth to 14 across its bottom eighth. White text needs almost
	 * no help down there and would need a scrim heavy enough to hide the
	 * citadel up top. The composition agrees -- the skull is in the upper
	 * middle and the foreground rock is nearly black.
	 */ ?>
	<?php /*
	 * THE STAGE. The banner is no longer a band with a picture in it: the
	 * picture is a backdrop the whole page sits on, and the band is just
	 * the part of it nothing covers.
	 *
	 * The art is a separate element rather than a background on the stage
	 * because it has to keep the image's own 3:2 shape as the window
	 * resizes, and aspect-ratio can only do that for a box. A second
	 * background layer could not: background-size percentages resolve
	 * against the positioning area, which here is thousands of pixels of
	 * page, so the fade would never line up with the art it is fading.
	 */ ?>
	<div class="lp-stage">
	<div class="lp-stage-art" aria-hidden="true"></div>

	<div class="lp-banner">
	<div class="lp-banner-in">

	<div class="lp-hero">
		<img class="lp-hero-avatar" src="<?php echo htmlspecialchars($lp_avatar_url); ?>" alt=""
		     onerror="this.src='icons/skull.png';">
		<div class="lp-hero-text">
			<h2><?php echo htmlspecialchars($lp_greeting); ?></h2>
			<div class="lp-hero-stats">
				<?php /* Which of these appear is lp_hero_stats()'s call, and the
				         rule is in lib/launchpad-stats.php: a zero is not a stat. */ ?>
				<?php foreach (lp_hero_stats($lp_balance, $lp_streak, $lp_missions, $lp_raids, $lp_wallets) as $lp_st): ?>
				<span><strong><?php echo number_format($lp_st[0]); ?></strong> <?php
					echo htmlspecialchars(lp_stat_label($lp_st)); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<?php if (!empty($lp_todo)): ?>
	<div class="lp-todo">
		<h3>Start Here</h3>
		<?php foreach ($lp_todo as $t): ?>
			<a class="lp-todo-item<?php echo isset($t[3]) ? ' lp-todo-art' : ''; ?>"
			   href="<?php echo htmlspecialchars($t[0]); ?>">
				<?php if (isset($t[3])): ?>
				<?php /* Decorative: the row already says what it is, so the
				         image is alt="" and a missing file just removes
				         itself rather than leaving a broken frame. */ ?>
				<img class="lp-todo-pic" src="<?php echo htmlspecialchars($t[3]); ?>" alt=""
					width="56" height="58" decoding="async"
					onerror="this.remove()">
				<?php endif; ?>
				<span class="lp-todo-text">
					<span class="lp-todo-title"><?php echo htmlspecialchars($t[1]); ?></span>
					<span class="lp-todo-why"><?php echo htmlspecialchars($t[2]); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	</div>
	</div>

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

	<?php
	/*
	 * THE REMEDIES, ONCE, AT THE TOP -- not a link inside every locked tile.
	 *
	 * Two reasons. A tile is already an <a>, and an <a> inside an <a> is the
	 * bug that hoisted the body out of every card on the Activity wall
	 * (recorded in MAINTENANCE.md); and five tiles each repeating "Connect a
	 * wallet" is noise where one line is an instruction.
	 *
	 * The remedy depends on the account -- somebody with no wallet and
	 * somebody with a wallet and nothing staked are told different things --
	 * which is the whole reason this is computed rather than written out.
	 */
	$lp_unmet = array();
	foreach ($lp_sections as $lp_sec_scan) {
		foreach ($lp_sec_scan['items'] as $it_scan) {
			$need = isset($it_scan[4]) ? $it_scan[4] : '';
			if ($need === '' || player_need_met($need, $lp_state)) continue;
			$lp_unmet[$need] = player_need_fix($need, $lp_state);
		}
	}
	?>
	<?php if ($lp_unmet): ?>
	<p class="lp-unmet">Some of these need something first.
		<?php foreach ($lp_unmet as $need => $fix): ?>
		<a href="<?php echo htmlspecialchars($fix[0], ENT_QUOTES); ?>"><?php
			echo htmlspecialchars($fix[1], ENT_QUOTES); ?> &rarr;</a>
		<?php endforeach; ?>
	</p>
	<?php endif; ?>

	<?php foreach ($lp_sections as $lp_name => $lp_sec): ?>
	<?php list($lp_ready, $lp_later) = lp_split_items($lp_sec['items'], $lp_state); ?>
	<div class="lp-section" style="--lp-accent:<?php echo $lp_sec['accent']; ?>">
		<h3 class="lp-section-head"><?php echo htmlspecialchars($lp_name); ?></h3>
		<div class="lp-section-blurb"><?php echo htmlspecialchars($lp_sec['blurb']); ?></div>
		<div class="lp-grid">
			<?php
			/* Ready first, then the rest in their original order. A locked
			   tile is STILL A LINK -- every one of these pages has an empty
			   state that explains itself better than a disabled tile could,
			   and refusing the click would be the brick wall this is meant
			   to remove. The chip is information, not a gate. */
			foreach (array_merge($lp_ready, $lp_later) as $it):
				$lp_need = isset($it[4]) ? $it[4] : '';
				$lp_ok   = player_need_met($lp_need, $lp_state); ?>
				<?php /*
				 * THE ART IS THE PANEL, and its accent came out of the art.
				 *
				 * An <img> rather than a CSS background, for one reason:
				 * onerror. Images deploy by FTP outside this repo, and a
				 * background-image that 404s is a silently empty tile. This
				 * way a missing upload puts the emoji back and the tile is
				 * what it always was, which is the whole reason the
				 * catalogue chose emoji to begin with. Not lazy: this is the
				 * top of the first page after login.
				 */
				$lp_ico = isset($lp_icons[$it[2]]) ? $lp_icons[$it[2]] : null; ?>
				<?php /* No accent of its own: a tile wears its section's, which
				         .lp-section already sets as --lp-accent. A colour per
				         tile made the grid a swatch book. */ ?>
				<a class="lp-tile<?php echo $lp_ok ? '' : ' lp-tile-later'; ?><?php
				      echo $lp_ico ? ' lp-tile-art-on' : ''; ?>"
				   href="<?php echo htmlspecialchars($it[0]); ?>">
					<span class="lp-tile-icon" data-emoji="<?php echo htmlspecialchars($it[1], ENT_QUOTES, 'UTF-8'); ?>"><?php
						if ($lp_ico): ?><img class="lp-tile-art" src="icons/<?php echo $lp_ico; ?>.webp"
							alt="" decoding="async"
							onerror="this.parentNode.textContent=this.parentNode.dataset.emoji;this.closest('.lp-tile').classList.remove('lp-tile-art-on')"><?php
						else: echo $it[1]; endif; ?></span>
					<span class="lp-tile-name"><?php echo htmlspecialchars($it[2]); ?></span>
					<span class="lp-tile-desc"><?php echo htmlspecialchars($it[3]); ?></span>
					<?php if (!$lp_ok): ?>
					<span class="lp-tile-need"><?php
						echo htmlspecialchars(player_need_label($lp_need), ENT_QUOTES); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endforeach; ?>

	</div><!-- .lp-stage -->

  </div>
</div>

<style>
/*
 * THE BANNER IS A REGION OF A BACKDROP, not a band with a picture in it.
 *
 * The art used to be a cover background on .lp-banner, cropped to a
 * 340px strip at 50% 27%. It is a 3:2 image and that strip is nearer
 * 6:1, so five sixths of it was never seen. It now hangs off .lp-stage
 * at its own shape and runs down behind the section headings and
 * through the gutters between tiles, quieting as it goes and dissolving
 * into the page where it ends -- and the banner is simply the part of
 * it that nothing covers.
 *
 * Images deploy by FTP, not with the code, so the no-upload case still
 * rules everything: neither the banner nor the backdrop paints a colour,
 * and a 404 leaves the band falling through to the page's own #07111d.
 * That is exactly the banner that was here before the art existed.
 */
/*
 * OUT TO THE MENU'S EDGE. .col1of3 carries padding: 20px and the navbar
 * sits straight inside .container with none, so the column's content is
 * inset exactly 20px from the menu at every width, 2000px ceiling
 * included. The stage spends that 20px back on both sides and takes it
 * out again as padding, so the art runs to the menu's edge while
 * everything on top of it stays where it was.
 */
.lp-stage {
	--lp-bleed: 20px;
	position: relative;
	/*
	 * UP TO THE MENU AS WELL AS OUT TO IT. The column's padding is the
	 * same 20px on all four sides, so the art stopped 20px short of the
	 * navbar and left a dark strip between them. The top bleed is the
	 * same number for the same reason, and it is NOT taken back as
	 * padding: everything in the stage moves up with the art, and
	 * .lp-banner-in's own 20px is what keeps the welcome off the menu.
	 */
	margin: calc(var(--lp-bleed) * -1) calc(var(--lp-bleed) * -1) 0;
	padding: 0 var(--lp-bleed);
}
/*
 * THE ART, AT ITS OWN SHAPE.
 *
 * 1728x1152, so aspect-ratio keeps the whole picture on screen instead
 * of cropping it to a band: at full width that is about 1330px of
 * image, of which the banner is the top quarter and the rest runs down
 * behind the section headings and through the gutters between tiles.
 * The citadel's spires finish inside the band and the skull carries on
 * below it, which is the point -- it is one picture the page sits on
 * rather than a strip at the top.
 *
 * MIN-HEIGHT IS THE PHONE. At 360px the natural height is 240px against
 * a 270px band, so aspect-ratio alone would leave a strip of bare page
 * under the art INSIDE the banner. The floor matches the band, cover
 * crops the sides to fill it, and a phone gets what it has always had.
 *
 * NO background-color. A missing upload leaves this element invisible
 * and the band falls through to the page's own #07111d -- which is
 * exactly the banner that was here before the art existed. The art
 * stays an improvement layered on, never a dependency.
 */
.lp-stage-art {
	position: absolute; top: 0; left: 0; right: 0;
	aspect-ratio: 1728 / 1152;
	min-height: 340px;
	background-image: url(images/launchpad-hero.jpg);
	background-size: cover;
	background-position: 50% 0;
	background-repeat: no-repeat;
	pointer-events: none;
	z-index: 0;
}
/*
 * TWO JOBS, ONE OVERLAY.
 *
 * Quieting: below the band the art is backdrop, not subject. Tiles are
 * opaque, so what shows down there is 12px gutters and the strip behind
 * each section heading, and at full strength that reads as clutter
 * rather than depth. The ramp starts where the band ends.
 *
 * Dissolving: the art has a hard bottom edge, and on a wide screen you
 * can see it. The last stretch goes to solid #07111d, which is the
 * page's own background, so the picture ends by becoming the page.
 *
 * Both are measured in px from an edge of the ART, which is why they
 * live on this element and not on the stage -- the stage is as tall as
 * the content and has nothing to do with where the picture stops.
 */
.lp-stage-art::after {
	content: ""; position: absolute; inset: 0;
	background:
		linear-gradient(180deg,
			rgba(7,17,29,0) 330px,
			rgba(7,17,29,0.66) 520px,
			rgba(7,17,29,0.66) 100%),
		linear-gradient(0deg,
			#07111d 0,
			rgba(7,17,29,0) 260px);
}
/* Everything the page actually says sits on top of the picture. */
.lp-banner, .lp-nudge, .lp-section { position: relative; z-index: 1; }
/*
 * THE BANNER IS NOW A HOLE IN THE PAGE, not a panel: no background of
 * its own, so the stage's art shows through it. Its scrim stays, since
 * that is about the words rather than the band.
 */
/*
 * THE BANNER BLEEDS TOO, and it is the scrim that makes it have to.
 * .lp-banner::before is inset:0 on this element, so a banner that
 * stopped at the column's edge would end its 0.93 wash 20px short of
 * the art and leave a bright seam down the left of the band. Bleeding
 * the box and taking the same distance back as padding puts the scrim
 * over the whole picture and leaves .lp-banner-in exactly where it was.
 */
.lp-banner {
	position: relative;
	margin: 0 calc(var(--lp-bleed) * -1) 34px;
	padding: 0 var(--lp-bleed);
}
/* THE SCRIM, graded to the art rather than flat. Measured per eighth,
   the image runs 66 luminance at the top to 14 at the bottom, so this is
   nearly clear over the citadel and does its work only where the text
   is. A flat overlay dark enough for the top would have buried it. */
.lp-banner::before {
	content: ""; position: absolute; inset: 0; pointer-events: none;
	/*
	 * LEFT-WEIGHTED, because the text moved to the top.
	 *
	 * The first scrim was bottom-weighted and correct for it: the art is
	 * darkest along its bottom edge, so that is where words were cheapest
	 * to read. With the welcome at the top of the band it now sits over
	 * the brightest part of the picture, and a bottom scrim does nothing
	 * for it. The left third of this art is dark rock and sky and the
	 * citadel is centre-right, so the weight goes left: solid behind the
	 * text, gone by the time it reaches the skull.
	 */
	background:
		linear-gradient(100deg,
			rgba(7,17,29,0.93) 0%,
			rgba(7,17,29,0.78) 22%,
			rgba(7,17,29,0.30) 52%,
			rgba(7,17,29,0.08) 100%),
		linear-gradient(180deg,
			rgba(7,17,29,0.35) 0%,
			rgba(7,17,29,0.00) 45%,
			rgba(7,17,29,0.45) 100%);
}
/*
 * MIN-HEIGHT, NOT PADDING. Start Here only renders while something is
 * incomplete, so sizing the band off its content would give an account
 * mid-setup a tall banner and a finished one a short one -- the art
 * would change size as a side effect of the checklist emptying. A floor
 * keeps the band the same shape either way, and the first pass was also
 * simply too short to fit the citadel in.
 */
.lp-banner-in {
	position: relative;              /* over the scrim */
	display: flex; flex-direction: column; justify-content: flex-start;
	min-height: 340px;
	padding: 20px 18px 18px;
}
.lp-hero {
	display: flex; align-items: center; gap: 16px;
	padding: 0 0 14px;
	/* RESTATED, not inherited: .main sets text-align:center, and the note
	   further down this file is about exactly this trap. */
	text-align: left;
}
.lp-hero-text h2 { text-shadow: 0 2px 14px rgba(0,0,0,0.8); }
.lp-hero-stats { text-shadow: 0 1px 10px rgba(0,0,0,0.85); }
/* Start Here is a list of LINKS, so it keeps a surface of its own. Text
   can sit on a photograph; a row you are meant to find and tap cannot. */
.lp-banner .lp-todo {
	background: rgba(7,17,29,0.74);
	border-color: rgba(0,200,160,0.45);
	margin-bottom: 0;
	/* IT IS A LIST, NOT A BANNER. Full width it was most of a 2000px
	   band of empty box to the right of two lines of text. It sizes to
	   its content now and the art fills the rest. */
	max-width: 560px;
	margin-right: auto;
	/* SQUARE, STATED HERE. The sweep squares panels it recognises by
	   colour, and this one is deliberately a colour it does not know --
	   it is a scrim panel, not a surface -- so it would have kept the
	   old 8px while everything around it lost it. */
	border-radius: 0;
}
@media (max-width: 700px) {
	/* No background-position of its own any more: it was 26% against a
	   base that is now 25%, and at 400px the band is 270px tall against
	   a 3:2 image, so cover barely crops it and the position has almost
	   no say. A rule that cannot change anything is just a thing to keep
	   in step. */
	.lp-banner-in { min-height: 270px; padding: 16px 12px 12px; }
	/* In step with the band above it, for the reason in .lp-stage-art. */
	.lp-stage-art { min-height: 270px; }
	/*
	 * AND THE BLEED IS NOT 20px DOWN HERE. flexbox.css:2140 sets
	 * `#row1 .col1of3 { padding: 4px }` under 700px, and at (1,1,0) it
	 * beats the `.col1of3 { padding: 8px }` further down the same file.
	 * Bleeding 20px against a 4px column would hang the art 16px off
	 * each side of the page and give a phone a horizontal scrollbar.
	 */
	.lp-stage { --lp-bleed: 4px; }

	/*
	 * TWO OR THREE ACROSS ON A PHONE, NOT ONE.
	 *
	 * minmax(190px) fits exactly one column in the ~350px a 390px phone
	 * leaves, so every tile became a 350px square and thirty four of
	 * them is a twelve thousand pixel page. 140px gives two across at
	 * phone width and three on a big one, and auto-fill still keeps them
	 * all the same size.
	 */
	.lp-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
	/*
	 * And the words have to fit the smaller square. aspect-ratio yields
	 * to content, so a description that wraps to four lines makes its
	 * own tile taller than the rest -- which is the uniformity problem
	 * coming back in through the text instead of the grid.
	 */
	.lp-tile-art-on .lp-tile-name { font-size: 0.8rem; padding: 0 8px; }
	.lp-tile-art-on .lp-tile-desc {
		font-size: 0.62rem; padding: 0 7px 8px; line-height: 1.3;
	}
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
/* A row with art becomes a two-column row; the rest are untouched. */
.lp-todo-art { display: flex; align-items: center; gap: 13px; text-align: left; }
.lp-todo-pic { width: 56px; height: 58px; object-fit: contain; flex: 0 0 56px;
	filter: drop-shadow(0 0 10px rgba(0,200,160,0.3)); }
.lp-todo-art .lp-todo-text { min-width: 0; }
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
/*
 * auto-FILL, NOT auto-fit, and the difference is visible on this page.
 *
 * auto-fit COLLAPSES the tracks an item does not occupy, so a section
 * with fewer items than columns stretches the ones it has: at 2000px
 * that is nine columns, and Progress's eight tiles came out 235px wide
 * against Play's 207. Three sections, three tile sizes. auto-fill keeps
 * the empty track, so every tile on the page is the same width -- which
 * matters more now they are square, because a wider tile is also a
 * taller one.
 */
.lp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; align-items: stretch; }
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
/*
 * THE EMOJI GETS A BOX, because emoji do not agree on width.
 *
 * Reported as "mobile icons are a mess, all varying sizes in width",
 * and they were: this is the fallback state every tile is in until the
 * art is uploaded, and an emoji is a glyph whose advance width is
 * whatever the platform font says. A stadium is wider than a skull. A
 * fixed square with the glyph centred makes the column line up whatever
 * each one happens to measure.
 */
.lp-tile-icon {
	display: inline-flex; align-items: center; justify-content: center;
	width: 44px; height: 44px; flex: 0 0 auto;
	font-size: 1.6rem; line-height: 1; margin-bottom: 8px;
}

/*
 * A TILE WITH ART IS SQUARE AND FULL BLEED.
 *
 * Square because the source is: these were cropped square out of a 5x4
 * sheet, and a wide short tile would cut the subject in half. The art
 * fills the panel and the text sits at the bottom over a scrim, the same
 * arrangement as the hero banner and for the same measured reason -- the
 * sheet is dark at the bottom of every cell and busiest through the
 * middle.
 */
.lp-tile.lp-tile-art-on {
	position: relative;
	aspect-ratio: 1;
	justify-content: flex-end;
	overflow: hidden;
	padding: 0;
	border-radius: 0;              /* the old 8px, which the sweep does not reach */
	background: #0d1e30;
}
/*
 * AND THE BOX COMES OFF AGAIN WHEN THERE IS ART.
 *
 * The 44px square above is the EMOJI's box. inset:0 does not override
 * an explicit width and height, so with both set the art container was
 * pinned 44x44 at the top left of the tile and every panel became a
 * tiny icon in the corner. Reset them, or the fallback's size wins over
 * the real thing.
 */
.lp-tile-art-on .lp-tile-icon {
	position: absolute; inset: 0; margin: 0;
	width: auto; height: auto; display: block;
}
.lp-tile-art { display: block; width: 100%; height: 100%; object-fit: cover; }
/* The scrim. Clear over the subject, solid under the words. */
.lp-tile-art-on::after {
	content: ""; position: absolute; inset: 0; pointer-events: none;
	background: linear-gradient(180deg,
		rgba(10,16,26,0.00) 38%,
		rgba(10,16,26,0.55) 62%,
		rgba(10,16,26,0.92) 100%);
}
.lp-tile-art-on .lp-tile-name,
.lp-tile-art-on .lp-tile-desc,
.lp-tile-art-on .lp-tile-need { position: relative; z-index: 1; }
.lp-tile-art-on .lp-tile-name { padding: 0 12px; text-shadow: 0 2px 10px rgba(0,0,0,0.9); }
/*
 * ONE LINE, AND NOTHING TO CUT.
 *
 * An art tile is bottom-anchored, so a description that wraps lifts its
 * own title above its neighbours' and the row of titles comes out
 * staggered. nowrap levels them. There is deliberately NO ellipsis: a
 * cut word is the same failure wearing a nicer hat, so the copy was
 * shortened instead (see lib/launchpad-catalogue.php) and the harness
 * holds every description to a width that fits. The phone never had
 * the problem because its font is smaller, which is why the desktop
 * size comes down to meet it.
 */
.lp-tile-art-on .lp-tile-desc {
	font-size: 0.68rem; padding: 0 10px 11px; line-height: 1.3;
	white-space: nowrap; overflow: hidden;
	text-shadow: 0 1px 8px rgba(0,0,0,0.95);
}
.lp-tile-art-on .lp-tile-need { padding: 0 12px 12px; }
/* The accent is the art's own colour now, so the top rule is worth more
   than 2px and the hover can use it too. */
.lp-tile-art-on { border-top-width: 3px; }
.lp-tile-art-on:hover { transform: translateY(-2px); }
.lp-tile-art-on:hover .lp-tile-art { transform: scale(1.04); }
.lp-tile-art { transition: transform 0.25s ease; }
@media (prefers-reduced-motion: reduce) {
	.lp-tile-art-on:hover { transform: none; }
	.lp-tile-art, .lp-tile-art-on:hover .lp-tile-art { transition: none; transform: none; }
}
.lp-tile-name { font-weight: bold; font-size: 0.95rem; }
.lp-tile-desc { font-size: 0.73rem; color: rgba(255,255,255,0.45); margin-top: 4px; }

/*
 * NOT YET, RATHER THAN NOT FOR YOU.
 *
 * Quieter, not disabled: the tile is still a link, still hovers, still goes
 * where it says. The only change is that it stops competing for attention
 * with the things this account can do today, which is the entire job -- a
 * new member reads thirty-six equal tiles as a wall and five as a start.
 *
 * The chip carries the sentence. Colour alone would say "different" without
 * saying what, and on a phone at a glance that reads as broken.
 */
.lp-tile-later { opacity: 0.55; border-top-color: rgba(255,255,255,0.18); }
.lp-tile-later:hover { opacity: 1; }
.lp-tile-need {
	margin-top: 8px; align-self: flex-start;
	font-size: 0.62rem; letter-spacing: 0.08em; text-transform: uppercase;
	color: #f5a623; border: 1px solid rgba(245,166,35,0.45);
	border-radius: 999px; padding: 2px 8px;
}
/* The remedies, once, above the sections. Amber to match the chips it
   explains, and it is the only place a fix is linked -- a link inside a
   tile would be an <a> inside an <a>, which is the bug that hoisted the
   body out of every card on the Activity wall. */
.lp-unmet {
	margin: 0 4px 18px; font-size: 0.82rem; color: rgba(255,255,255,0.6);
	display: flex; flex-wrap: wrap; gap: 14px; align-items: baseline;
}
.lp-unmet a { color: #f5a623; text-decoration: none; font-weight: 600; text-align: left; }
.lp-unmet a:hover { text-decoration: underline; }
</style>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>

<?php
/**
 * realms-onboarding.php — the page somebody sees before they have a realm.
 *
 * WHAT IT REPLACED, AND WHY. Realms used to greet a newcomer with the
 * narrow left column of the logged-in layout: a 400px-tall logo, two
 * unstyled fields, and a plain <ul> of seven currency codes, with the
 * entire right-hand two thirds of the page empty. It read as a form
 * somebody had been asked to add, not as an invitation, and nobody on
 * this side of it had ever looked at it -- everyone who could change it
 * has owned a realm for years. admin-lib.php's Onboarding tab exists so
 * that is no longer true.
 *
 * SO IT IS A PITCH, AND IT ARGUES IN THIS ORDER: what you get, what it
 * costs (nothing), what it is, and who is already playing. The form is
 * at the top because somebody who has already decided should not have
 * to read the pitch, and repeated at the bottom because somebody who
 * read it should not have to scroll back.
 *
 * THE PROOF IS THE PART THAT CANNOT BE WRITTEN. Everything above the
 * showcase is copy, and copy is free. The row of real players, with
 * their faces, their factions and their levels, is the only element on
 * the page that a reader can check -- so it is the one that earns the
 * signup, and it comes from realm_showcase() rather than from a
 * marketing list.
 *
 * NUMBERS COME FROM THE CODE THAT GRANTS THEM. The starter pack is
 * realm_starter_pack(), which reads the same core-project list and the
 * same REALM_STARTER_EACH that createRealm() credits. The old page typed
 * its own list of seven next to that loop.
 *
 * NO <section>, NO <article>, NO <header> IN HERE. dist/flexbox.css
 * carries `section { opacity: 0 }` platform-wide for its reveal-on-
 * scroll effect, so a semantic <section> renders a completely invisible
 * page -- which is exactly what the first draft of this file did, and
 * exactly what admin-lib.php already has a note about from the last
 * time somebody found out. Plain <div> with a class, everywhere.
 *
 * Included from realms.php when !$realm_status. Expects $conn.
 */
if (!isset($conn)) return;

require_once __DIR__ . '/realms-lib.php';

$ro_core    = getProjects($conn, 'core');
$ro_partner = getProjects($conn, 'partner');
$ro_starter = realm_starter_pack($conn);
$ro_players = realm_showcase($conn, 6, 30);

/* 7 is the house theme, not a faction anybody joins -- the same unset
   the original form did, kept here for the same reason. */
$ro_core_factions = $ro_core;
unset($ro_core_factions[7]);

/* Inline, not files: icons/ is deployed by FTP and is not in the repo,
   so a new icon would be a broken image until somebody remembered to
   upload it. These cost nothing and inherit currentColor. */
function ro_icon($name) {
	$p = array(
		'build' => '<path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/>',
		'raid'  => '<path d="M14.5 3.5 20.5 9.5M18 2l4 4-9.5 9.5-4-4L18 2zM8.5 11.5 3 17v4h4l5.5-5.5"/>',
		'flag'  => '<path d="M5 21V4m0 0 9 2.5L19 4v9l-5 2.5L5 13"/>',
		'crown' => '<path d="M3 7l4 4 5-7 5 7 4-4-2 12H5L3 7z"/>',
	);
	if (!isset($p[$name])) return '';
	return '<svg class="ro-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
	     . 'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
	     . $p[$name] . '</svg>';
}

/* The form, twice: once at the top for the decided and once at the foot
   for the convinced. One function so the two cannot drift -- the fields
   are also createRealm()'s $_POST names. */
function ro_form($core, $partner, $id) {
	$id = htmlspecialchars($id);
	?>
	<form class="ro-form" action="realms.php" method="post">
		<div class="ro-field">
			<label for="<?php echo $id; ?>-name">Name your realm</label>
			<input type="text" id="<?php echo $id; ?>-name" name="realm" maxlength="40"
			       placeholder="The Gilded Hollow" autocomplete="off" required>
		</div>
		<div class="ro-field">
			<label for="<?php echo $id; ?>-faction">Choose a faction</label>
			<!-- NOT class="dropdown": flexbox.css gives that width:300px !important
			     and height:30px against a 20px font, which both breaks the
			     full-width field and clips the option text in half. -->
			<select id="<?php echo $id; ?>-faction" name="faction" required>
				<optgroup label="Core Factions">
				<?php foreach ($core as $pid => $p) {
					printf('<option value="%d">%s</option>', (int) $pid, htmlspecialchars($p['name']));
				} ?>
				</optgroup>
				<optgroup label="Partner Factions">
				<?php foreach ($partner as $pid => $p) {
					printf('<option value="%d">%s</option>', (int) $pid, htmlspecialchars($p['name']));
				} ?>
				</optgroup>
			</select>
		</div>
		<button type="submit" class="ro-go">Establish my realm</button>
		<p class="ro-fine">Free to start. You can rename your realm and change its look later.</p>
	</form>
	<?php
}
?>
<div class="row">
<div class="main">
<div class="ro">

	<div class="ro-hero">
		<div class="ro-hero-copy">
			<?php /* The wordmark, not a text label. width/height are the
			         file's real 750x450 so the browser reserves the right box
			         before it loads and the headline does not jump; the CSS
			         caps what it actually renders at. The old page showed this
			         same file at full column width, which is how it came to be
			         400px of logo above a form. */ ?>
			<img class="ro-logo" src="images/realms-logo.png" width="750" height="450"
			     alt="Skulliance Realms">
			<h1>Claim a corner of the map.</h1>
			<p class="ro-lede">
				Build a realm, grow it location by location, and raid the players around you
				for their points. Your realm takes a place on the world map the moment you
				found it, and it keeps earning while you are away.
			</p>
			<?php if (!empty($ro_starter)) { ?>
			<p class="ro-kicker">
				<strong><?php echo count($ro_starter); ?> currencies</strong>
				land in your wallet the second you create it.
			</p>
			<?php } ?>
		</div>
		<div class="ro-hero-form">
			<?php ro_form($ro_core_factions, $ro_partner, 'top'); ?>
		</div>
	</div>

	<div class="ro-perks">
		<?php
		$ro_perks = array(
			array('build', 'Build it up',    'Seven locations, each upgradeable. Higher levels mean more points per day and a harder realm to crack.'),
			array('raid',  'Raid your rivals', 'Send a raid at another realm and take a cut of what they are holding. Defend yours by building the right locations.'),
			array('flag',  'Fly a faction',  'Pick the project you hold and fight under its banner. Factions share the map and compete for ground on it.'),
			array('crown', 'Climb the board', 'Every realm is ranked by level, offense and defense. The map shows who holds what, and it updates as you play.'),
		);
		foreach ($ro_perks as $p) { ?>
			<div class="ro-perk">
				<span class="ro-perk-ico"><?php echo ro_icon($p[0]); ?></span>
				<h3><?php echo htmlspecialchars($p[1]); ?></h3>
				<p><?php echo htmlspecialchars($p[2]); ?></p>
			</div>
		<?php } ?>
	</div>

	<?php if (!empty($ro_starter)) { ?>
	<div class="ro-starter">
		<div class="ro-head">
			<h2>Your founding grant</h2>
			<p>Credited automatically when your realm is created. No purchase, no staking requirement.</p>
		</div>
		<ul class="ro-coins">
			<?php foreach ($ro_starter as $s) {
				$code = strtolower((string) $s['currency']); ?>
				<li class="ro-coin" title="<?php echo htmlspecialchars($s['name']); ?>">
					<img src="icons/<?php echo htmlspecialchars($code); ?>.png" alt=""
					     loading="lazy" onerror="this.style.display='none'">
					<span class="ro-coin-amt"><?php echo number_format((int) $s['amount']); ?></span>
					<span class="ro-coin-cur"><?php echo htmlspecialchars((string) $s['currency']); ?></span>
				</li>
			<?php } ?>
		</ul>
	</div>
	<?php } ?>

	<?php if (!empty($ro_players)) { ?>
	<div class="ro-live">
		<div class="ro-head">
			<h2>Realms held this month</h2>
			<p>Players seen in the last 30 days, and what they have built.</p>
		</div>
		<ul class="ro-players">
			<?php foreach ($ro_players as $pl) {
				$cur = strtolower((string) $pl['currency']); ?>
				<li class="ro-player">
					<div class="ro-player-art"
					     style="background-image:url('images/themes/<?php echo (int) $pl['theme_id']; ?>.jpg')">
						<img class="ro-player-face" src="<?php echo htmlspecialchars($pl['avatar']); ?>"
						     alt="" loading="lazy">
					</div>
					<div class="ro-player-id">
						<strong><?php echo htmlspecialchars($pl['realm']); ?></strong>
						<span><?php echo htmlspecialchars($pl['username']); ?></span>
					</div>
					<div class="ro-player-faction">
						<img src="icons/<?php echo htmlspecialchars($cur); ?>.png" alt=""
						     loading="lazy" onerror="this.style.display='none'">
						<span><?php echo htmlspecialchars($pl['faction']); ?></span>
					</div>
					<div class="ro-player-stats">
						<span><em><?php echo number_format($pl['levels']); ?></em>levels</span>
						<span><em><?php echo number_format($pl['raids_won']); ?></em>raids won</span>
					</div>
				</li>
			<?php } ?>
		</ul>
	</div>
	<?php } ?>

	<div class="ro-close">
		<div class="ro-close-copy">
			<h2>Found your realm</h2>
			<p>
				It takes a name and a faction. Everything else you can change once you are on
				the map.
			</p>
			<p class="ro-more">
				<a href="skullpaper.php?page=realms">Read how Realms works in the Skull Paper</a>
			</p>
		</div>
		<div class="ro-close-form">
			<?php ro_form($ro_core_factions, $ro_partner, 'foot'); ?>
		</div>
	</div>

</div>
</div>

</div>

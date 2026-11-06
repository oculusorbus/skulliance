<?php
/**
 * realms-map.php — the world map, without a login.
 *
 * WHY IT IS ITS OWN PAGE. The map lives on realms.php, which is behind
 * verify.php and skulliance.php, so the one thing on this platform that
 * is worth showing a stranger was the one thing a stranger could not
 * reach. Every share of it was a screenshot.
 *
 * NO skulliance.php AND NO verify.php. Including either redirects to
 * Discord, which is exactly what this page exists not to do -- the same
 * rule skullpaper.php carries at the top of its own header, and there
 * is a harness assertion on it because it is the kind of line somebody
 * adds while fixing something else.
 *
 * NOTHING HERE IS NEW EXPOSURE. The map carries usernames, avatars,
 * realm names and levels; profile.php already serves any username and
 * avatar to anyone without a login, and a realm's levels are what the
 * raid screen shows an attacker before they commit. This publishes the
 * same facts on a nicer surface.
 *
 * header.php, NOT site-header.php. The map needs the platform's
 * stylesheets and its nav renders its member menu inside isset($name),
 * so it degrades to the public nav on its own. site-header.php is
 * self-contained navigation for pages that carry their own CSS, which
 * this one does not: it wants dist/map.css, and header.php is where
 * that link is gated.
 *
 * ROOT-RELATIVE LINKS ONLY. Session cookies here are host-only, so an
 * absolute https://www.skulliance.io link would silently log out anyone
 * browsing the bare domain.
 */
include_once 'db.php';

/*
 * The member nav renders inside isset($name), so a signed-in reader
 * gets their own menu here and a stranger gets the public one. Set only
 * when there is really a session: assigning "" would make isset() true
 * and draw a member navbar with nobody in it.
 */
if (isset($_SESSION['userData'])) {
	$name = $_SESSION['userData']['username'] ?? $_SESSION['userData']['name'] ?? null;
	if ($name === null) unset($name);
}

$page_title_override = 'Skulliance Realms world map';
$extra_head = '<meta name="description" content="The live world map of Skulliance Realms: faction territories, active raids and every realm on the board.">'
	. '<meta property="og:title" content="Skulliance Realms world map">'
	. '<meta property="og:description" content="Faction territories, active raids and every realm on the board, updated as the week turns.">';

include 'header.php';
?>
<div class="row" id="map">
	<div class="main">
		<?php
		/*
		 * NO HEADING AND NO EXPLAINER. There was a title and a
		 * paragraph here describing what the map is, how territory
		 * size works and what a click does. The owner cut it: "we
		 * don't need all this verbiage, the map speaks for itself".
		 * That is right, and it generalises -- a page whose whole point
		 * is a picture should open on the picture. The name of the
		 * page lives in the tab ($page_title_override) and the
		 * sentence that described it lives in the meta description,
		 * where it does its job for search without standing between
		 * the reader and the art.
		 */
		?>
		<div id="container-wrapper">
			<div id="container"></div>
		</div>
		<div class="popup-overlay" id="popup-overlay">
			<div class="popup-content">
				<button class="popup-close" id="popup-close">&#x2715;</button>
				<img class="popup-image" id="popup-image" src="" alt="Realm Image">
				<div class="popup-footer">
					<img class="popup-avatar" id="popup-avatar" src="" alt="Avatar">
					<div class="popup-info">
						<p class="popup-user" id="popup-user"></p>
						<p class="popup-realm" id="popup-realm"></p>
					</div>
				</div>
				<div class="popup-stats">
					<div class="popup-stat">
						<img class="popup-stat-icon" id="popup-faction-icon" src="" alt="">
						<span id="popup-faction-name"></span>
					</div>
					<div class="popup-stat">
						<span class="popup-stat-label">Avg Level</span>
						<span class="popup-stat-value" id="popup-avg-level"></span>
					</div>
					<div id="popup-raid-chips"></div>
				</div>
				<div class="popup-dossier" id="popup-dossier"></div>
			</div>
		</div>
		<?php if (!isset($name)) { ?>
		<div class="rm-join">
			<p>Free to start. You are on this map from the first week.</p>
			<a class="button" href="realms.php">Claim a realm</a>
		</div>
		<?php } ?>
	</div>
</div>
<?php
/* The same two emitters realms.php uses, so the public map is the live
   map and not a second query that can drift from it. */
getFactionsRealmsMapData($conn);
getActiveRaidsMapData($conn);
$conn->close();
?>
<?php /* map-kit.js FIRST: map.js uses its globals at parse time, and
         map-world.js before map.js because map.js calls mwWorld() to
         build the continent. Same order as realms.php, same reasons. */ ?>
<script type="text/javascript" src="map-kit.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="map-world.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="map.js?var=<?php echo rand(0,999); ?>"></script>
</html>

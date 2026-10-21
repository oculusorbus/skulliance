<!doctype html>
<html>
<head>
  <title>Drop Ship</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php
  /*
   * THE PLATFORM'S ICON, for cohesion -- Drop Ship is a Skulliance game and
   * the tab said so nowhere. It had no icon at all, so the tab, the bookmark
   * and the phone home screen showed the browser's blank page.
   *
   * THE SHARED INCLUDE, not a paste. lib/favicon-head.php exists because
   * these five tags had already been copied into three files and drifted,
   * and it was written for exactly this case: "every page that skips
   * header.php had no icon at all". Drop Ship has its own header.php, so it
   * was one of them.
   *
   * ROOT-RELATIVE paths, which is why this works across apps: /staking/pwa/...
   * resolves the same from /staking/dropship/ as from /staking/, and the
   * include deliberately avoids absolute hosts because the login cookie is
   * host-only. ../lib/ is a path Drop Ship already reaches into -- db.php
   * requires ../lib/koios.php.
   *
   * THEME-AGNOSTIC on purpose. Dread City and Filthy Mermaid are Skulliance
   * games too; a per-theme favicon would be five more files to deploy by FTP
   * and one more thing to be missing.
   */
  $ds_fav = __DIR__ . '/../lib/favicon-head.php';
  if (is_file($ds_fav)) include $ds_fav;
  ?>
  <!-- TEMPORARILY reverted to Google's CDN (2026-09-05): a self-hosted
       dist/jquery-3.6.3.min.js reference was live here, but that file was
       never actually uploaded to the server -- 404'd, breaking every
       $-dependent script on the page (dropship.js included). The intent
       (mirroring Skulliance's own header.php: Brave's Shields blocks
       ajax.googleapis.com for some stakers) is still worth doing, but
       needs the actual file deployed to dropship/dist/ first -- swap
       this back to <script src="dist/jquery-3.6.3.min.js"></script>
       once that file is confirmed live (curl it, don't assume). -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
  <!--<link href="dist/output.css" rel="stylesheet">-->
  <!-- filemtime()-based cache-bust, not rand(0,999) -- a random 0-999 value
       can (and, live-tested, DID) collide with one already sitting in a
       browser's cache from before a real CSS change shipped, silently
       serving stale styles. filemtime() only changes when the file
       actually changes, so a fresh deploy always gets a fresh URL. Same
       pattern the main site already uses for its PWA version banner
       (header.php's own app-version meta tag). -->
  <link href="dist/flexbox.css?var=<?php echo @filemtime(__DIR__ . '/dist/flexbox.css') ?: rand(0,999); ?>" rel="stylesheet">
  <script>
  // Toggle dropdown -- ported from Skulliance's own header.php verbatim.
  // Above 700px the CSS opens a dropdown on :hover on its own (see
  // dist/flexbox.css); this is what makes tapping the trigger work at any
  // width, including the narrow case where :hover isn't a real gesture.
  function toggleDropdown(el){
    var menu = el.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.nav-dropdown-menu.open').forEach(function(m){ m.classList.remove('open'); });
    if(!isOpen) menu.classList.add('open');
  }
  // Toggle burger menu -- ported from Skulliance's own header.php verbatim
  // (same icon swap / show-menu class dance), so the nav is hidden by
  // default on mobile instead of always rendered full-height like before.
  // Reuses Skulliance's own menu/close icons (no local copies exist in
  // dropship/images) -- same precedent as instructions.php's DREAD/MOON
  // icons pointing at /staking/icons/ directly.
  function toggleMenu(){
    if(document.getElementById('burger-icon').src.indexOf('close.png') === -1){
      document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/close.png";
      document.getElementById("navbar").classList.add('show-menu');
    }else{
      document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/menu.png";
      document.getElementById("navbar").classList.remove('show-menu');
    }
  }
  </script>
  <?php
  if($_SESSION["userData"]["dropship_project_id"] == 4){?>
	<?php if(str_contains($_SERVER['PHP_SELF'], "battles.php")){?>
	<style>
		.button{
			filter: hue-rotate(145deg);
		}
	</style>
	  <?php }else{ ?>
		<style>
			.button, .small-button{
				filter: hue-rotate(145deg);
			}
			.credit{
				filter: hue-rotate(45deg);
			}
			.battle-credit{
				filter: hue-rotate(145deg);
			}
		</style>
	  <?php } ?>
  <?php } ?>
</head>
<?php /* The theme's own art folder, for the one thing JavaScript has to
         build a path to: the theme-change loader. Every theme ships its
         own images/loading.gif. */ ?>
<body data-ds-prefix="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>">
<?php
/*
 * THE LOADER BELONGS ON THE PAGE BEING LOADED, NOT THE ONE BEING LEFT.
 *
 * dsShowLoader() builds an overlay in dropship.js and then submits the
 * form, so it lives on the OUTGOING document and dies the instant the
 * browser swaps in the new one -- which is well before that page's art has
 * arrived. Reported exactly that way: "it flashes for a second, then the
 * page displays without all the content rendered."
 *
 * The two halves are different documents, so this is the other half: the
 * arriving page paints its own overlay on the first frame and keeps it up
 * until the art behind it is actually there. dsShowLoader() still covers
 * the request itself, so there is no gap between them.
 *
 * ONLY ON A THEME CHANGE. $project_id_changed is set in dropship.php when
 * the requested project differs from the session's, which is the one
 * request that re-reads the player's holdings and routinely takes a
 * second. Every other navigation here is fast and an overlay on it was
 * what made the loader intolerable the first time round.
 *
 * window.load, NOT DOMContentLoaded, because "full content" means the
 * images: load fires once every subresource has finished, including the
 * ones that failed, so a 404 in a half-uploaded theme cannot strand it.
 *
 * NO JQUERY, AND A HARD CAP. jQuery comes from Google's CDN and Brave's
 * Shields block that host for some stakers -- see the note in the head --
 * so dropship.js's own $(window).on('load') handler is not something an
 * overlay covering the whole page may depend on. A loader that never
 * lifts is far worse than one that lifts early, hence the plain listener
 * and the timeout behind it.
 */
if (isset($project_id_changed) && $project_id_changed === "true"
    && function_exists('dropshipThemeArtFile')) {
	$ds_spin = dropshipThemeArtFile($slug, 'images/loading', 'gif');
	/* No spinner for this theme means no overlay, rather than a black
	   screen with a broken image on it. */
	if ($ds_spin !== '') { ?>
	<div id="loading"><img id="loading-image" alt="Loading..."
	     src="<?php echo htmlspecialchars($prefix . $ds_spin, ENT_QUOTES); ?>"></div>
	<script>
	(function () {
		var el = document.getElementById('loading');
		if (!el) return;
		var lifted = false;
		function lift() { if (lifted) return; lifted = true; el.style.display = 'none'; }
		if (document.readyState === 'complete') lift();
		else window.addEventListener('load', lift);
		/* Ten seconds is longer than this page has ever taken and shorter
		   than anybody will sit staring at a spinner wondering if the site
		   is broken. */
		setTimeout(lift, 10000);
	})();
	</script>
<?php }
} ?>
	<?php /* NO FULL-SCREEN LOADING OVERLAY ON ANY OTHER NAVIGATION. It covered the page on every
	         navigation and on every form post, which is most of what you do
	         here, and it was a full-bleed gif rather than progress -- it
	         never told anybody anything the browser's own loading state did
	         not. The element is simply absent; dropship.js and wallet.js
	         both check before touching it, so nothing throws.
	         The PLATFORM keeps its own #loading (skulliance.js uses it) and
	         the rule in dist/flexbox.css is shared, so neither is removed. */ ?>
	<div class="container">
		<div id="burger-menu">
			<img id="burger-icon" onclick="javascript:toggleMenu();" src="https://www.skulliance.io/staking/images/menu.png"/>
		</div>
		<!-- Navigation Bar -->
		<!-- Grouped into dropdowns mirroring Skulliance's own nav (Play/NFTs/
		     Stats/Account, same class names and toggleDropdown() mechanism --
		     see staking's own header.php + dist/flexbox.css) instead of one
		     long jammed-full row of top-level links. Hidden by default on
		     mobile now (id="navbar" + #burger-menu above), same as
		     Skulliance's own -- it used to render full-height and always
		     visible on narrow screens instead of collapsing behind a
		     hamburger icon. -->
		<div class="navbar" id="navbar">
	      <img class="rounded-full" src="<?php echo $avatar_url?>" />
		  <a href="https://discord.gg/DHbGU9ZDyG"><?php echo $name;?></a>

		  <!-- Play -->
		  <div class="nav-dropdown navbar-first">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Play</span>
		    <div class="nav-dropdown-menu">
		      <a href="dashboard.php">Dashboard</a>
		      <a href="dashboard.php#barracks"><?php echo evaluateText("Barracks");?></a>
		      <a href="dashboard.php#armory"><?php echo evaluateText("Armory");?></a>
		      <a href="battles.php"><?php echo evaluateText("Battles");?></a>
		      <?php if($_SESSION["userData"]["dropship_project_id"] == 4){?>
		      <a href="discoin.php">Buy Temp VIP</a>
		      <?php } ?>
		    </div>
		  </div>

		  <!-- NFTs -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">NFTs</span>
		    <div class="nav-dropdown-menu">
		      <a href="soldiers.php"><?php echo evaluateText("Soldiers");?></a>
		    </div>
		  </div>

		  <!-- Stats -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Stats</span>
		    <div class="nav-dropdown-menu">
		      <a href="leaderboards.php">Leaderboards</a>
		      <a href="achievements.php">Achievements</a>
		    </div>
		  </div>

		  <!-- Account -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Account</span>
		    <div class="nav-dropdown-menu">
		      <a href="transactions.php">Transactions</a>
		    </div>
		  </div>

		  <?php if($_SESSION["userData"]["dropship_project_id"] == 1 || $_SESSION["userData"]["dropship_project_id"] == 2 || $_SESSION["userData"]["dropship_project_id"] == 4){?>
		  <a href="instructions.php">How to Play</a>
		  <?php } ?>
		  <?php /* Staff only, and the check is the same one the page itself
		           enforces -- a link nobody else can see is convenience, not
		           security, so admin-themes.php gates again on arrival. */ ?>
		  <?php if(function_exists('dropshipThemeAdmin') && dropshipThemeAdmin()){ ?>
		  <!-- Admin -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Admin</span>
		    <div class="nav-dropdown-menu">
		      <a href="admin-themes.php">Themes</a>
		      <a href="admin-art.php">Theme Art</a>
		    </div>
		  </div>
		  <?php } ?>
		  <!-- The LAUNCHPAD, not the dashboard. Every other game on the platform
		       exits to launchpad.php (Monstrocity, Skull Swap, Match 3), because
		       someone leaving a game is usually going to another one, and the
		       launchpad is the page that lists them; the dashboard is a staking
		       page and a detour from there. Drop Ship was the last one still
		       pointing at the dashboard, from before it moved into this repo.
		       Relative, never absolute: the login cookie is host-only, so a link
		       hardcoded to www would drop a skulliance.io session (and vice
		       versa) and land the player on a logged-out launchpad. -->
		  <a href="../launchpad.php" aria-label="Back to the Skulliance Launchpad">&larr; Back to Skulliance</a>
		</div>
		<!-- Mobile bottom quick-links bar (icons/scoreboard.png etc. all
		     curl-verified live) -- same visual pattern as Missions/Realms'
		     own #quick-menu, but real links rather than in-page section
		     toggling, since these four are genuinely different pages/anchors
		     (Stats is a separate page, not a section of this one). Shown on
		     every Drop Ship page via header.php, mobile-only via CSS.
		     Which icon is "selected" has to be decided client-side, not in
		     PHP -- Barracks/Armory are hash anchors on this same
		     dashboard.php page, and a URL fragment is never sent to the
		     server at all, so $_SERVER['PHP_SELF'] alone can't tell
		     "#barracks" apart from plain dashboard.php. -->
		<?php
		// Oculus Lounge-specific icon swaps. These are nav furniture, not
		// in-game item names, so there is no wording entry for them and the
		// choice is made here. Curl-verified all three live before using them.
		$is_oculus_lounge = ($_SESSION['userData']['dropship_project_id'] == 4);
		$qm_game_icon = $is_oculus_lounge ? 'money-gun' : 'dropship';
		// ...with ONE exception. The barracks icon is literally the Medkit
		// picture, which was written here as 'nurse' -- a second copy of a
		// filename the wording owns, and it broke the moment that file was
		// renamed to follow the word. Asking for it is the same thing and
		// cannot drift.
		$qm_barracks_icon = $is_oculus_lounge ? evaluateText('medkit') : 'supersoldier';
		$qm_armory_icon = $is_oculus_lounge ? 'grab-bag' : 'shield';
		?>
		<div id="quick-menu">
			<a href="dashboard.php" title="Game"><img src="icons/<?php echo $qm_game_icon;?>.png" data-page="dashboard.php" data-hash=""/></a>
			<a href="dashboard.php#barracks" title="<?php echo evaluateText("Barracks");?>"><img src="icons/<?php echo $qm_barracks_icon;?>.png" data-page="dashboard.php" data-hash="barracks"/></a>
			<a href="dashboard.php#armory" title="<?php echo evaluateText("Armory");?>"><img src="icons/<?php echo $qm_armory_icon;?>.png" data-page="dashboard.php" data-hash="armory"/></a>
			<a href="leaderboards.php" title="Stats"><img src="icons/scoreboard.png" data-page="leaderboards.php" data-hash=""/></a>
		</div>
		<script>
		(function(){
			// A same-page hash link (e.g. clicking Barracks while already on
			// dashboard.php) never fires a real navigation/page load -- the
			// browser just updates location.hash in place -- so this can't
			// only run once at parse time, it has to re-run on 'hashchange'
			// too, or Game -> Barracks/Armory within the same page load
			// never updates which icon is highlighted.
			function updateSelected(){
				var page = location.pathname.split('/').pop();
				var hash = location.hash.replace('#', '');
				document.querySelectorAll('#quick-menu img').forEach(function(img){
					img.classList.toggle('selected', img.dataset.page === page && img.dataset.hash === hash);
				});
			}
			updateSelected();
			window.addEventListener('hashchange', updateSelected);
		})();
		</script>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
<?php
/*
 * THE THEME'S PAGE BACKGROUND, for every theme and on every page.
 *
 * This was `if project_id == 4` here plus a second copy in dashboard.php
 * for projects 3 and 4, and the Filthy Mermaid copy was missing the
 * /staking/dropship prefix, so it 404'd while the file sat on the server.
 * Three ways to get it wrong and it managed two of them. One lookup now,
 * in the header every page already includes, so a theme gets its
 * background everywhere rather than only on the dashboard.
 */
$ds_bg = function_exists('dropshipThemeBackground') && isset($slug)
       ? dropshipThemeBackground($slug) : '';
if ($ds_bg !== ''): ?>
	<script type='text/javascript'>document.body.style.backgroundImage = "url('<?php
		echo htmlspecialchars($ds_bg, ENT_QUOTES); ?>')";</script>
<?php endif; ?>
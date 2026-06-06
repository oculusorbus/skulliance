<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
require_once 'lib/Parsedown.php';
include 'header.php';

/*
 * Skull Paper — the Skulliance documentation, served natively from this repo.
 *
 * Content lives as plain Markdown in /skullpaper/*.md. To add or edit a page:
 *   1. Create/edit the .md file in /skullpaper/
 *   2. Add it to $skullpaper_nav below (slug = filename without .md)
 * The slug doubles as the ?page= URL param and the markdown filename.
 *
 * Replaces the old GitBook (skulliance.gitbook.io/skulliance) so docs update
 * in lockstep with the code in this repo.
 */

// Navigation tree: each section is a page in its own right; children nest under it.
$skullpaper_nav = [
	['slug' => 'overview', 'title' => 'Overview', 'children' => []],
	['slug' => 'staking', 'title' => 'Staking', 'children' => [
		['slug' => 'staking-membership',    'title' => 'Membership'],
		['slug' => 'staking-daily-rewards', 'title' => 'Daily Rewards'],
	]],
	['slug' => 'missions', 'title' => 'Missions', 'children' => [
		['slug' => 'missions-consumable-items', 'title' => 'Consumable Items'],
		['slug' => 'missions-monthly-rewards',  'title' => 'Monthly Rewards'],
	]],
	['slug' => 'realms', 'title' => 'Realms', 'children' => [
		['slug' => 'realms-locations', 'title' => 'Locations'],
		['slug' => 'realms-raids',     'title' => 'Raids'],
		['slug' => 'realms-factions',  'title' => 'Factions'],
	]],
	['slug' => 'diamond-skulls', 'title' => 'Diamond Skulls', 'children' => [
		['slug' => 'diamond-skulls-carbon-emissions', 'title' => 'Carbon Emissions'],
		['slug' => 'diamond-skulls-skulliverse',      'title' => 'Skulliverse'],
	]],
	['slug' => 'games', 'title' => 'Games', 'children' => [
		['slug' => 'games-drop-ship',    'title' => 'Drop Ship'],
		['slug' => 'games-oculus-lounge', 'title' => 'Oculus Lounge'],
	]],
];

// Flatten to an ordered list of [slug => title] for routing + prev/next.
$sp_order = [];
foreach ($skullpaper_nav as $sec) {
	$sp_order[$sec['slug']] = $sec['title'];
	foreach ($sec['children'] as $child) {
		$sp_order[$child['slug']] = $child['title'];
	}
}

// Resolve and validate the requested page (whitelist guards against traversal).
$page = isset($_GET['page']) ? (string)$_GET['page'] : 'overview';
if (!preg_match('/^[a-z0-9-]+$/', $page) || !isset($sp_order[$page])) {
	$page = 'overview';
}
$mdFile = __DIR__ . '/skullpaper/' . $page . '.md';

$bodyHtml = '<p>This page is coming soon.</p>';
if (is_file($mdFile)) {
	$Parsedown = new Parsedown();
	$Parsedown->setSafeMode(true); // content is trusted, but escape raw HTML for safety
	$bodyHtml = $Parsedown->text(file_get_contents($mdFile));
}

// Prev / next within the flat order.
$slugs = array_keys($sp_order);
$idx = array_search($page, $slugs, true);
$prevSlug = ($idx > 0) ? $slugs[$idx - 1] : null;
$nextSlug = ($idx < count($slugs) - 1) ? $slugs[$idx + 1] : null;

$pageTitle = $sp_order[$page];
?>
<style>
#skullpaper { color:#e8eaed; }
#skullpaper .sp-row { display:flex; flex-wrap:wrap; align-items:flex-start; gap:0; }
#skullpaper .sp-side {
	flex:0 0 260px; max-width:260px; padding:20px 16px;
	position:sticky; top:10px; align-self:flex-start;
}
#skullpaper .sp-main { flex:1 1 480px; min-width:0; padding:20px 28px; text-align:left; }

/* Sidebar */
#skullpaper .sp-brand {
	display:flex; align-items:center; gap:10px;
	font-weight:700; font-size:1.05rem; letter-spacing:.02em;
	color:#e8eaed; margin-bottom:16px; padding-bottom:14px;
	border-bottom:1px solid rgba(0,200,160,0.15);
}
#skullpaper .sp-brand img { width:28px; height:auto; }
#skullpaper .sp-nav { list-style:none; margin:0; padding:0; }
#skullpaper .sp-nav .sp-section { margin-bottom:6px; }
#skullpaper .sp-nav a {
	display:block; text-decoration:none; color:#7a9eb0;
	padding:7px 10px; border-radius:7px; font-size:.92rem; line-height:1.3;
	transition:background .15s, color .15s;
}
#skullpaper .sp-nav a:hover { color:#e8eaed; background:rgba(0,200,160,0.07); }
#skullpaper .sp-nav a.sp-top { color:#d6dde0; font-weight:600; }
#skullpaper .sp-nav a.active { color:#00c8a0; background:rgba(0,200,160,0.12); font-weight:600; }
#skullpaper .sp-children { list-style:none; margin:2px 0 8px; padding:0 0 0 12px;
	border-left:1px solid rgba(0,200,160,0.12); }
#skullpaper .sp-children a { font-size:.88rem; padding:5px 10px; }

/* Article */
#skullpaper .sp-article {
	background:#0a1929; border:1px solid rgba(0,200,160,0.18);
	border-radius:12px; padding:26px 30px;
}
#skullpaper .sp-article h1 {
	font-size:1.9rem; margin:0 0 18px; color:#fff; line-height:1.2;
	padding-bottom:14px; border-bottom:1px solid rgba(0,200,160,0.15);
}
#skullpaper .sp-article h2 {
	font-size:1.3rem; margin:30px 0 12px; color:#00c8a0; text-align:left;
}
#skullpaper .sp-article h3 { font-size:1.08rem; margin:22px 0 10px; color:#d6dde0; text-align:left; }
#skullpaper .sp-article p { line-height:1.7; margin:0 0 14px; color:#cdd8de; }
#skullpaper .sp-article ul, #skullpaper .sp-article ol { margin:0 0 16px; padding-left:22px; line-height:1.7; color:#cdd8de; }
#skullpaper .sp-article li { margin:4px 0; }
#skullpaper .sp-article a { color:#00c8a0; text-decoration:none; }
#skullpaper .sp-article a:hover { text-decoration:underline; }
#skullpaper .sp-article strong { color:#e8eaed; }
#skullpaper .sp-article img {
	max-width:100%; height:auto; border-radius:10px; margin:8px 0 20px;
	border:1px solid rgba(0,200,160,0.12);
}
#skullpaper .sp-article table { border-collapse:collapse; width:100%; margin:0 0 18px; }
#skullpaper .sp-article th, #skullpaper .sp-article td {
	border:1px solid rgba(0,200,160,0.15); padding:8px 12px; text-align:left;
}
#skullpaper .sp-article th { background:rgba(0,200,160,0.08); color:#e8eaed; }
#skullpaper .sp-article blockquote {
	margin:0 0 16px; padding:8px 16px; border-left:3px solid #00c8a0;
	background:rgba(0,200,160,0.06); color:#cdd8de; border-radius:0 8px 8px 0;
}
#skullpaper .sp-article code {
	background:#07111d; padding:2px 6px; border-radius:5px; font-size:.9em; color:#00ffc8;
}

/* Prev / next */
#skullpaper .sp-pager { display:flex; justify-content:space-between; gap:14px; margin-top:22px; }
#skullpaper .sp-pager a {
	flex:1; text-decoration:none; color:#cdd8de;
	background:#0d1e2e; border:1px solid rgba(0,200,160,0.18);
	border-radius:10px; padding:14px 18px; transition:border-color .15s, background .15s;
}
#skullpaper .sp-pager a:hover { border-color:#00c8a0; background:#0f2436; }
#skullpaper .sp-pager .sp-pg-label { display:block; font-size:.72rem; text-transform:uppercase;
	letter-spacing:.08em; color:#7a9eb0; margin-bottom:4px; }
#skullpaper .sp-pager .sp-pg-next { text-align:right; }

/* Mobile */
#skullpaper .sp-menu-toggle { display:none; }
@media (max-width:820px) {
	#skullpaper .sp-side {
		flex-basis:100%; max-width:100%; position:static;
		padding:14px 10px;
	}
	#skullpaper .sp-nav { display:none; }
	#skullpaper .sp-nav.open { display:block; }
	#skullpaper .sp-menu-toggle {
		display:flex; align-items:center; gap:8px; width:100%;
		background:#0d1e2e; color:#e8eaed; border:1px solid rgba(0,200,160,0.18);
		border-radius:8px; padding:11px 14px; font-size:.95rem; cursor:pointer; margin-bottom:8px;
	}
	#skullpaper .sp-main { padding:8px 12px 20px; }
	#skullpaper .sp-article { padding:18px 16px; }
}
</style>

<div id="skullpaper">
	<div class="sp-row">
		<!-- Sidebar -->
		<aside class="sp-side">
			<div class="sp-brand">
				<img src="/staking/pwa/skulliance-logo-icon.png" alt="Skulliance"> Skull Paper
			</div>
			<button class="sp-menu-toggle" onclick="document.getElementById('sp-nav').classList.toggle('open')">
				&#9776; <span>Contents</span>
			</button>
			<ul class="sp-nav" id="sp-nav">
				<?php foreach ($skullpaper_nav as $sec): ?>
				<li class="sp-section">
					<a class="sp-top<?php echo ($page === $sec['slug']) ? ' active' : ''; ?>"
					   href="skullpaper.php?page=<?php echo $sec['slug']; ?>"><?php echo htmlspecialchars($sec['title']); ?></a>
					<?php if (!empty($sec['children'])): ?>
					<ul class="sp-children">
						<?php foreach ($sec['children'] as $child): ?>
						<li>
							<a class="<?php echo ($page === $child['slug']) ? 'active' : ''; ?>"
							   href="skullpaper.php?page=<?php echo $child['slug']; ?>"><?php echo htmlspecialchars($child['title']); ?></a>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</aside>

		<!-- Content -->
		<div class="sp-main">
			<article class="sp-article">
				<?php echo $bodyHtml; ?>
			</article>

			<nav class="sp-pager">
				<?php if ($prevSlug): ?>
				<a href="skullpaper.php?page=<?php echo $prevSlug; ?>">
					<span class="sp-pg-label">&#8592; Previous</span><?php echo htmlspecialchars($sp_order[$prevSlug]); ?>
				</a>
				<?php else: ?><span style="flex:1"></span><?php endif; ?>
				<?php if ($nextSlug): ?>
				<a class="sp-pg-next" href="skullpaper.php?page=<?php echo $nextSlug; ?>">
					<span class="sp-pg-label">Next &#8594;</span><?php echo htmlspecialchars($sp_order[$nextSlug]); ?>
				</a>
				<?php else: ?><span style="flex:1"></span><?php endif; ?>
			</nav>
		</div>
	</div>
</div>

<!-- Footer -->
<div class="footer">
  <p>Skulliance<br>Copyright © <span id="year"></span>
</div>
</div>
</div>
</body>
<script type="text/javascript">document.title = "<?php echo htmlspecialchars($pageTitle, ENT_QUOTES); ?> — Skull Paper | Skulliance";</script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>

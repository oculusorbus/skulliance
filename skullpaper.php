<?php
include_once 'db.php';
require_once 'lib/Parsedown.php';

/*
 * Skull Paper is intentionally PUBLIC — it deliberately does NOT include
 * skulliance.php (which redirects anonymous visitors to error.php) or the
 * heavy verify.php. db.php gives us a session (only if a cookie exists) and
 * $conn. We still light up the personalized navbar for logged-in visitors by
 * restoring their session and populating $name / $avatar_url, but anonymous
 * visitors and crawlers see the page with the default (logged-out) header.
 */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = $cookie; }
}
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	$name = $_SESSION['userData']['name'] ?? null;
	$sp_did = $_SESSION['userData']['discord_id'] ?? null;
	$sp_avatar = $_SESSION['userData']['avatar'] ?? null;
	if ($name !== null && $sp_did && $sp_avatar) {
		$avatar_url = "https://cdn.discordapp.com/avatars/$sp_did/$sp_avatar.jpg";
	}
}

/*
 * Skull Paper — the Skulliance documentation, served natively from this repo.
 * (header.php is included further down, AFTER the page is resolved and its
 * markdown loaded, so per-page SEO meta can be injected via $extra_head.)
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
	['slug' => 'overview', 'title' => 'Overview', 'emoji' => '📖', 'children' => []],
	['slug' => 'staking', 'title' => 'Staking', 'emoji' => '💰', 'children' => [
		['slug' => 'staking-membership',         'title' => 'Membership',          'emoji' => '🎟️'],
		['slug' => 'staking-daily-rewards',      'title' => 'Daily Rewards',       'emoji' => '📅'],
		['slug' => 'staking-points',             'title' => 'Points',              'emoji' => '🪙'],
		['slug' => 'staking-crafting',           'title' => 'Crafting',            'emoji' => '⚒️'],
	]],
	['slug' => 'missions', 'title' => 'Missions', 'emoji' => '🎯', 'children' => [
		['slug' => 'missions-consumable-items', 'title' => 'Consumable Items', 'emoji' => '🧪'],
		['slug' => 'missions-monthly-rewards',  'title' => 'Monthly Rewards',  'emoji' => '🏆'],
	]],
	['slug' => 'realms', 'title' => 'Realms', 'emoji' => '🏰', 'children' => [
		['slug' => 'realms-locations', 'title' => 'Locations', 'emoji' => '📍'],
		['slug' => 'realms-soldiers',  'title' => 'Soldiers',  'emoji' => '⚔️'],
		['slug' => 'realms-raids',     'title' => 'Raids',     'emoji' => '🐉'],
		['slug' => 'realms-factions',  'title' => 'Factions',  'emoji' => '🚩'],
	]],
	['slug' => 'diamond-skulls', 'title' => 'Diamond Skulls', 'emoji' => '💎', 'children' => [
		['slug' => 'diamond-skulls-carbon-emissions', 'title' => 'Carbon Emissions', 'emoji' => '♻️'],
		['slug' => 'diamond-skulls-skulliverse',      'title' => 'Skulliverse',      'emoji' => '🌌'],
	]],
	['slug' => 'games', 'title' => 'Games', 'emoji' => '🎮', 'children' => [
		['slug' => 'games-monstrocity',   'title' => 'Monstrocity',   'emoji' => '👾'],
		['slug' => 'games-boss-battles',  'title' => 'Boss Battles',  'emoji' => '👹'],
		['slug' => 'games-skull-swap',    'title' => 'Skull Swap',    'emoji' => '🔄'],
		['slug' => 'games-gauntlets',     'title' => 'Gauntlets',     'emoji' => '🥊'],
		['slug' => 'games-drop-ship',     'title' => 'Drop Ship',     'emoji' => '🚀'],
		['slug' => 'games-oculus-lounge', 'title' => 'Oculus Lounge', 'emoji' => '🛋️'],
	]],
	['slug' => 'marketplace', 'title' => 'Marketplace', 'emoji' => '🛒', 'children' => [
		['slug' => 'marketplace-store',    'title' => 'Store',    'emoji' => '🏪'],
		['slug' => 'marketplace-auctions', 'title' => 'Auctions', 'emoji' => '🔨'],
		['slug' => 'marketplace-raffles',  'title' => 'Raffles',  'emoji' => '🎫'],
	]],
	['slug' => 'platform', 'title' => 'Platform', 'emoji' => '🛠️', 'children' => [
		['slug' => 'platform-dashboard',    'title' => 'Dashboard',    'emoji' => '📊'],
		['slug' => 'platform-gallery',      'title' => 'Gallery',      'emoji' => '🖼️'],
		['slug' => 'platform-collections',  'title' => 'Collections',  'emoji' => '📚'],
		['slug' => 'platform-leaderboards', 'title' => 'Leaderboards', 'emoji' => '🏅'],
		['slug' => 'platform-analytics',    'title' => 'Analytics',    'emoji' => '📈'],
		['slug' => 'platform-profile',      'title' => 'Profile',      'emoji' => '👤'],
		['slug' => 'platform-wallets',      'title' => 'Wallets',      'emoji' => '👛'],
		['slug' => 'platform-transactions', 'title' => 'Transactions', 'emoji' => '🧾'],
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
	$raw = file_get_contents($mdFile);
	// Expand {{projects:GROUP}} tokens into live Markdown pulled from the
	// projects table, so the project/points lists never drift from the database.
	//   GROUP    = founding (ids 1-6) | core (id <= 7) | partner (id > 7, !=15)
	//   {{projects:GROUP}}        -> table rows  "| Name | CURRENCY |"
	//   {{projects:GROUP:names}}  -> bullet list "* Name"            (overview)
	//   {{projects:GROUP:points}} -> bullet list "* Name - CURRENCY" (staking)
	// Only public fields (name, currency) are emitted — Skull Paper is a public
	// page (see header comment). The doc owns any table header / surrounding text.
	$raw = preg_replace_callback('/\{\{projects:(founding|core|partner)(?::(names|points))?\}\}/', function ($m) use ($conn) {
		$rows = getProjects($conn, $m[1]);
		if (!is_array($rows) || !$rows) { return '_None listed yet._'; }
		$fmt = $m[2] ?? '';
		$out = [];
		foreach ($rows as $p) {
			$name = trim((string)$p['name']);
			$cur  = trim((string)$p['currency']);
			if ($fmt === 'names') {
				$out[] = '* ' . $name;
			} elseif ($fmt === 'points') {
				$out[] = '* ' . $name . ' - ' . $cur;
			} else {
				// Escape pipes so a stray "|" in a value can't break the table.
				$out[] = '| ' . str_replace('|', '\\|', $name) . ' | ' . str_replace('|', '\\|', $cur) . ' |';
			}
		}
		return implode("\n", $out);
	}, $raw);
	// Resolve [[slug]] wiki-links to internal doc links using nav titles.
	// Unknown slugs render as plain text (their label) rather than a broken link.
	$raw = preg_replace_callback('/\[\[([a-z0-9-]+)\]\]/', function ($m) use ($sp_order) {
		$slug = $m[1];
		if (isset($sp_order[$slug])) {
			return '[' . $sp_order[$slug] . '](skullpaper.php?page=' . $slug . ')';
		}
		return $slug;
	}, $raw);
	$Parsedown = new Parsedown();
	$Parsedown->setSafeMode(true); // content is trusted, but escape raw HTML for safety
	$bodyHtml = $Parsedown->text($raw);
}

// Prev / next within the flat order.
$slugs = array_keys($sp_order);
$idx = array_search($page, $slugs, true);
$prevSlug = ($idx > 0) ? $slugs[$idx - 1] : null;
$nextSlug = ($idx < count($slugs) - 1) ? $slugs[$idx + 1] : null;

$pageTitle = $sp_order[$page];

/*
 * Per-page SEO, injected through header.php's $extra_head hook.
 * Title/description/canonical/OG/Twitter/JSON-LD are all server-rendered so
 * every doc page is uniquely indexable and shares cleanly (the old approach
 * set the title via JS only, which crawlers and scrapers never saw).
 */
$sp_site = 'https://www.skulliance.io/staking/skullpaper.php';
$sp_canonical = $sp_site . ($page === 'overview' ? '' : '?page=' . $page);
$page_title_override = $pageTitle . ' - Skull Paper | Skulliance';

// Meta description: first real prose paragraph of the markdown, stripped of
// markup. Falls back to a general blurb for token-only or missing pages.
$sp_desc = 'The Skull Paper is the living guide to the Skulliance platform - staking, missions, Realms, Diamond Skulls, games, and the marketplace.';
if (isset($raw)) {
	foreach (preg_split('/\n\s*\n/', $raw) as $sp_block) {
		$sp_block = trim($sp_block);
		if ($sp_block === '') { continue; }
		$c = $sp_block[0];
		if ($c === '#' || $c === '!' || $c === '|' || $c === '>' || $c === '*' || $c === '-' || preg_match('/^\d+\./', $sp_block)) { continue; }
		$sp_block = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $sp_block);   // images
		$sp_block = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $sp_block); // links -> text
		$sp_block = str_replace(['**', '`', '*'], '', $sp_block);
		$sp_block = trim(preg_replace('/\s+/', ' ', $sp_block));
		if ($sp_block !== '') {
			$sp_desc = mb_strlen($sp_block) > 158 ? mb_substr($sp_block, 0, 155) . '...' : $sp_block;
			break;
		}
	}
}

// Share image: first image embedded in the page, else the brand artwork.
$sp_img = 'https://www.skulliance.io/staking/images/skulliance-group.jpg';
if (isset($raw) && preg_match('/!\[[^\]]*\]\((https?:[^)\s]+)\)/', $raw, $sp_m)) {
	$sp_img = $sp_m[1];
}

// Parent section (for breadcrumbs) + real modified date from the md file.
$sp_parent = null;
foreach ($skullpaper_nav as $sp_sec) {
	if (in_array($page, array_column($sp_sec['children'], 'slug'), true)) { $sp_parent = $sp_sec; break; }
}
$sp_modified = is_file($mdFile) ? date('c', filemtime($mdFile)) : date('c');

// Breadcrumbs: Skulliance -> Skull Paper [-> Section] -> Page
$sp_crumbs = [['Skulliance', 'https://www.skulliance.io/'], ['Skull Paper', $sp_site]];
if ($sp_parent) { $sp_crumbs[] = [$sp_parent['title'], $sp_site . '?page=' . $sp_parent['slug']]; }
if ($page !== 'overview') { $sp_crumbs[] = [$pageTitle, $sp_canonical]; }
$sp_crumb_items = [];
foreach ($sp_crumbs as $sp_i => $sp_c) {
	$sp_crumb_items[] = ['@type' => 'ListItem', 'position' => $sp_i + 1, 'name' => $sp_c[0], 'item' => $sp_c[1]];
}

$sp_jsonld = json_encode([
	'@context' => 'https://schema.org',
	'@graph' => [
		[
			'@type' => 'TechArticle',
			'headline' => $pageTitle . ' - Skull Paper',
			'description' => $sp_desc,
			'url' => $sp_canonical,
			'mainEntityOfPage' => $sp_canonical,
			'image' => $sp_img,
			'dateModified' => $sp_modified,
			'inLanguage' => 'en',
			'isPartOf' => ['@id' => 'https://www.skulliance.io/#website'],
			'author' => ['@id' => 'https://www.skulliance.io/#organization'],
			'publisher' => ['@id' => 'https://www.skulliance.io/#organization'],
		],
		[
			'@type' => 'BreadcrumbList',
			'itemListElement' => $sp_crumb_items,
		],
	],
], JSON_UNESCAPED_SLASHES);

$sp_t = htmlspecialchars($page_title_override, ENT_QUOTES);
$sp_d = htmlspecialchars($sp_desc, ENT_QUOTES);
$sp_u = htmlspecialchars($sp_canonical, ENT_QUOTES);
$sp_i = htmlspecialchars($sp_img, ENT_QUOTES);
$extra_head = <<<HTML
  <meta name="description" content="{$sp_d}">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
  <link rel="canonical" href="{$sp_u}">
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="{$sp_u}">
  <meta property="og:title" content="{$sp_t}">
  <meta property="og:description" content="{$sp_d}">
  <meta property="og:image" content="{$sp_i}">
  <meta property="og:locale" content="en_US">
  <meta property="article:modified_time" content="{$sp_modified}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@skulliance">
  <meta name="twitter:title" content="{$sp_t}">
  <meta name="twitter:description" content="{$sp_d}">
  <meta name="twitter:image" content="{$sp_i}">
  <script type="application/ld+json">{$sp_jsonld}</script>
HTML;

include 'header.php';
?>
<style>
#skullpaper { color:#e8eaed; }
#skullpaper .sp-row { display:flex; flex-wrap:wrap; align-items:flex-start; gap:0; }
#skullpaper .sp-side {
	flex:0 0 260px; max-width:260px; padding:20px 16px;
	position:sticky; top:10px; align-self:flex-start; text-align:left;
}
#skullpaper .sp-main { flex:1 1 480px; min-width:0; padding:20px 28px; text-align:left; }

/* Sidebar */
#skullpaper .sp-brand {
	display:flex; align-items:center; gap:10px;
	font-weight:700; font-size:1.05rem; letter-spacing:.02em;
	color:#e8eaed; margin-bottom:16px; padding-bottom:14px;
	border-bottom:1px solid rgba(0,200,160,0.15);
}
#skullpaper .sp-brand a { display:inline-flex; }
#skullpaper .sp-brand img { width:28px; height:auto; }
#skullpaper .sp-nav { list-style:none; margin:0; padding:0; text-align:left; }
#skullpaper .sp-nav .sp-section { margin-bottom:6px; }
#skullpaper .sp-nav a {
	display:block; text-decoration:none; color:#7a9eb0; text-align:left;
	padding:7px 10px; border-radius:7px; font-size:.92rem; line-height:1.3;
	transition:background .15s, color .15s;
}
#skullpaper .sp-nav a:hover { color:#e8eaed; background:rgba(0,200,160,0.07); }
#skullpaper .sp-nav a.sp-top { color:#d6dde0; font-weight:600; }
#skullpaper .sp-nav a.active { color:#00c8a0; background:rgba(0,200,160,0.12); font-weight:600; }
#skullpaper .sp-emoji { display:inline-block; width:1.5em; margin-right:2px; text-align:center; }
#skullpaper .sp-children { list-style:none; margin:2px 0 8px; padding:0 0 0 12px;
	border-left:1px solid rgba(0,200,160,0.12); }
#skullpaper .sp-children a { font-size:.88rem; padding:5px 10px; }

/* Collapsible sections */
#skullpaper .sp-sec-row { display:flex; align-items:stretch; gap:4px; }
#skullpaper .sp-sec-row a.sp-top { flex:1 1 auto; min-width:0; }
#skullpaper .sp-caret {
	flex:0 0 auto; background:none; border:none; cursor:pointer; color:#7a9eb0;
	padding:0 8px; border-radius:7px; font-size:.8rem; line-height:1;
	transition:transform .15s, color .15s, background .15s;
}
#skullpaper .sp-caret:hover { color:#e8eaed; background:rgba(0,200,160,0.07); }
#skullpaper .sp-section.open > .sp-sec-row .sp-caret { transform:rotate(90deg); }
#skullpaper .sp-section > .sp-children { display:none; }
#skullpaper .sp-section.open > .sp-children { display:block; }

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
				<a href="https://www.skulliance.io/" aria-label="Back to Skulliance home"><img src="/staking/pwa/skulliance-logo-icon.png" alt="Skulliance"></a> Skull Paper
			</div>
			<button class="sp-menu-toggle" onclick="document.getElementById('sp-nav').classList.toggle('open')">
				&#9776; <span>Contents</span>
			</button>
			<ul class="sp-nav" id="sp-nav">
				<?php foreach ($skullpaper_nav as $sec):
					// A section is "open" when you're on its page or one of its children.
					$childSlugs = array_column($sec['children'], 'slug');
					$isActiveSec = ($page === $sec['slug']);
					$isOpen = $isActiveSec || in_array($page, $childSlugs, true);
				?>
				<li class="sp-section<?php echo $isOpen ? ' open' : ''; ?>">
					<div class="sp-sec-row">
						<a class="sp-top<?php echo $isActiveSec ? ' active' : ''; ?>"
						   href="skullpaper.php?page=<?php echo $sec['slug']; ?>"><span class="sp-emoji"><?php echo $sec['emoji']; ?></span><?php echo htmlspecialchars($sec['title']); ?></a>
						<?php if (!empty($sec['children'])): ?>
						<button type="button" class="sp-caret" aria-label="Toggle section"
						        onclick="this.closest('.sp-section').classList.toggle('open')">&#9656;</button>
						<?php endif; ?>
					</div>
					<?php if (!empty($sec['children'])): ?>
					<ul class="sp-children">
						<?php foreach ($sec['children'] as $child): ?>
						<li>
							<a class="<?php echo ($page === $child['slug']) ? 'active' : ''; ?>"
							   href="skullpaper.php?page=<?php echo $child['slug']; ?>"><span class="sp-emoji"><?php echo $child['emoji']; ?></span><?php echo htmlspecialchars($child['title']); ?></a>
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
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>

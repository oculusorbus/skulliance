<?php
/**
 * site-header.php — the public site's navigation, one copy.
 *
 * Included by homepage.php and by every public game landing page, so a
 * visitor who arrives on a game page can reach the rest of the site instead
 * of being at a dead end. It was previously markup and CSS living inside
 * homepage.php alone, which is why the game pages had no navigation at all.
 *
 * SELF-CONTAINED, because the pages that include it do not share a
 * stylesheet: the homepage, dhcgame.php, match3rpg.php and the rest each
 * carry their own inline CSS. So this brings its own, once, under an `sh-`
 * prefix that none of them use.
 *
 * NO SESSION, NO DATABASE, NO db.php. It is navigation. Every page that
 * includes it must stay includable by WordPress (the homepage is) and cheap
 * for a crawler.
 *
 * ROOT-RELATIVE LINKS, NEVER ABSOLUTE. Session cookies here are HOST-ONLY --
 * no domain= on SessionCookie or PHPSESSID -- so www.skulliance.io and
 * skulliance.io do not share a login. An absolute https://www.skulliance.io
 * link in shared navigation would silently log out everybody browsing the
 * bare domain, on every page this appears on. A leading slash keeps whatever
 * host the visitor is already on. (The homepage's own nav had absolute URLs;
 * that is fixed by this move rather than carried into it.)
 *
 * STICKY, NOT FIXED. A fixed bar is out of the layout, so every page that
 * included it would need its own top padding to avoid being overlapped --
 * a magic number per page, wrong the moment the bar wraps to two rows on a
 * phone. Sticky occupies its space, so no page has to know it is there.
 *
 * TWO KNOBS, both optional, both set BEFORE the include:
 *   $sh_on_home  true on the homepage, so its section links stay in-page
 *                anchors rather than reloading the page to jump.
 *   $sh_links    override the link list entirely. Default is the short set
 *                a game page wants; the homepage passes its full one.
 */

$sh_on_home = !empty($sh_on_home);

if (!isset($sh_links) || !is_array($sh_links)) {
	/* The short set: where someone who landed on a game page would want to
	   go next. The homepage's own section anchors are noise here. */
	$sh_links = array(
		array('Games',       '/#games'),
		array('Staking',     '/staking/'),
		array('Merch',       '/shop'),
		array('Skull Paper', '/staking/skullpaper.php'),
	);
}

/* On the homepage an anchor is an anchor; anywhere else it has to go home
   first. Written here so no caller has to remember it. */
$sh_href = function ($href) use ($sh_on_home) {
	if ($sh_on_home && strpos($href, '/#') === 0) return substr($href, 1);
	return $href;
};

$sh_img = '/staking/images/';
?>
<style>
.sh-nav {
  position: sticky; top: 0; z-index: 9990;
  display: flex; align-items: center; gap: 22px;
  padding: 10px 18px;
  background: rgba(7,17,29,.88);
  border-bottom: 1px solid rgba(255,255,255,.08);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}
.sh-nav .sh-mark { display: inline-flex; align-items: center; }
.sh-nav .sh-mark img { height: 30px; width: auto; display: block; }
.sh-nav a.sh-link {
  color: #c7d0d9; font-size: .92rem; font-weight: 600;
  letter-spacing: .02em; text-decoration: none;
}
.sh-nav a.sh-link:hover, .sh-nav a.sh-link:focus { color: #34e3bb; text-decoration: none; }
.sh-nav .sh-spacer { flex: 1; }
.sh-nav a.sh-social img { height: 18px; width: auto; display: block; }
.sh-nav a.sh-social:hover img {
  filter: invert(64%) sepia(67%) saturate(437%) hue-rotate(112deg) brightness(95%) contrast(92%);
}
#sh-burger { display: none; background: none; border: none; padding: 4px; cursor: pointer; margin-left: auto; }
#sh-burger img { height: 30px; width: auto; display: block; }
@media (max-width: 860px) {
  #sh-burger { display: block; }
  .sh-nav { flex-wrap: wrap; }
  .sh-nav .sh-links {
    display: none; flex-direction: column; align-items: flex-start;
    gap: 14px; width: 100%; padding: 14px 4px 8px;
  }
  .sh-nav .sh-links.open { display: flex; }
  .sh-nav a.sh-link { font-size: 1.05rem; }
}
@media (min-width: 861px) {
  .sh-nav .sh-links { display: flex; align-items: center; gap: 22px; flex: 1; }
}
</style>

<nav class="sh-nav" aria-label="Main">
  <a class="sh-mark" href="<?php echo $sh_on_home ? '#top' : '/'; ?>" aria-label="Skulliance home">
    <img src="<?php echo $sh_img; ?>skull.png" alt="Skulliance" width="30" height="30">
  </a>
  <button id="sh-burger" type="button" aria-label="Toggle menu" aria-expanded="false" onclick="shToggleMenu()">
    <img id="sh-burger-icon" src="<?php echo $sh_img; ?>menu.png" alt="" width="30" height="30">
  </button>
  <div class="sh-links" id="sh-links">
    <?php foreach ($sh_links as $sh_l): ?>
    <a class="sh-link" href="<?php echo htmlspecialchars($sh_href($sh_l[1])); ?>"><?php echo htmlspecialchars($sh_l[0]); ?></a>
    <?php endforeach; ?>
    <span class="sh-spacer"></span>
    <a class="sh-social" href="https://discord.gg/JqqBZBrph2" target="_blank" rel="noopener" aria-label="Skulliance Discord"><img src="<?php echo $sh_img; ?>discord.png" alt="Discord" width="18" height="18"></a>
    <a class="sh-social" href="https://www.x.com/skulliance" target="_blank" rel="noopener" aria-label="Skulliance on X"><img src="<?php echo $sh_img; ?>x.png" alt="X" width="18" height="18"></a>
  </div>
</nav>

<script>
/* aria-expanded has to move with the class or the button lies to a screen
   reader about what it just did. The icon swap and the close-on-tap below
   came from the homepage's own version and are kept: without the swap the
   burger gives no sign the menu is open, and without the close a visitor
   who taps a section anchor is left staring at the menu they just used. */
function shToggleMenu() {
  var l = document.getElementById('sh-links');
  var b = document.getElementById('sh-burger');
  var i = document.getElementById('sh-burger-icon');
  if (!l) return;
  var open = l.classList.toggle('open');
  if (i) i.src = '/staking/images/' + (open ? 'close.png' : 'menu.png');
  if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
}
(function () {
  var l = document.getElementById('sh-links');
  if (!l) return;
  l.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () {
      if (l.classList.contains('open')) shToggleMenu();
    });
  });
})();
</script>

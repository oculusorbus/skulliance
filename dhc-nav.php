<?php
/**
 * dhc-nav.php — the strip that ties the three DHC pages together.
 *
 * Build a Fighter, look at what everyone has built, fight with it. Those are
 * one activity, and they were three pages you had to find: the Collection was
 * not in the platform nav at all, and getting from the Arena back to the
 * assembler meant opening a menu or knowing a link was buried in a paragraph.
 *
 * ONE FILE, INCLUDED THREE TIMES, so the strip cannot drift into three
 * slightly different strips. Set $dhcnav_at before including:
 *
 *     $dhcnav_at = 'fighters' | 'collection' | 'arena';
 *     include 'dhc-nav.php';
 *
 * SIGNED OUT, THE OTHER TWO ARE A TRAP. dhcarena.php is public; dhcfighters.php
 * and dhcgallery.php both include skulliance.php, which redirects an anonymous
 * visitor to error.php. Linking a guest straight into that is the exact wall
 * the public Arena removed, so for a guest those two point at the sign-in page
 * and say why. Set $dhcnav_guest = true to get that (only the Arena ever needs
 * to -- the other two cannot be reached signed out).
 *
 * Relative hrefs, never absolute: the login cookie is host-only, so an
 * absolute www link can hop hosts and silently sign the player out.
 */
$dhcnav_at    = isset($dhcnav_at) ? $dhcnav_at : '';
$dhcnav_guest = !empty($dhcnav_guest);

$dhcnav_items = array(
	'fighters'   => array('dhcfighters.php', 'Fighters',   'build',  1),
	'collection' => array('dhcgallery.php',  'Collection', 'browse', 1),
	'arena'      => array('dhcarena.php',    'Arena',      'fight',  0),
);
?>
<style>
/* Scoped to its own class and written with fallbacks, because it lands on three
   pages that each declare their own --ochre. */
.dhcnav{display:flex;gap:6px;margin:0 0 14px;flex-wrap:nowrap;overflow-x:auto;
  -webkit-overflow-scrolling:touch;scrollbar-width:none}
.dhcnav::-webkit-scrollbar{display:none}
.dhcnav a{flex:0 0 auto;display:flex;flex-direction:column;gap:1px;
  border:1px solid var(--line,#1b2836);border-radius:3px;padding:6px 14px;
  text-decoration:none;color:var(--bone,#c9d6e2);background:var(--panel2,#0d1826);
  line-height:1.25;transition:border-color .15s,color .15s}
.dhcnav a b{font-size:12px;font-weight:600;letter-spacing:.02em;white-space:nowrap}
.dhcnav a span{font-size:9px;letter-spacing:.12em;text-transform:uppercase;opacity:.5;
  white-space:nowrap}
.dhcnav a:hover{border-color:var(--ochre,#00c8a0);color:var(--ochre,#00c8a0)}
/* The page you are on is stated, not merely highlighted -- on a strip this
   small a colour change alone reads as a hover that got stuck. */
.dhcnav a.on{border-color:var(--ochre,#00c8a0);color:var(--ochre,#00c8a0);
  background:rgba(0,200,160,.08);cursor:default}
.dhcnav a.on span{opacity:.75}
.dhcnav a.locked{opacity:.62}
.dhcnav a.locked span{color:var(--ochre,#00c8a0);opacity:.8}
@media (max-width:420px){
  .dhcnav a{padding:5px 11px}
  .dhcnav a b{font-size:11.5px}
}
</style>
<nav class="dhcnav" aria-label="DHC Fighters, Collection and Arena">
<?php foreach ($dhcnav_items as $key => $it):
	list($href, $label, $hint, $gated) = $it;
	$here   = ($key === $dhcnav_at);
	$locked = ($dhcnav_guest && $gated && !$here);
	$to     = $locked ? 'index.php' : $href;
	$cls    = $here ? 'on' : ($locked ? 'locked' : '');
	$title  = $locked
		? $label.' — sign in to '.$hint
		: ($here ? 'You are here' : $label.' — '.$hint);
?>
  <a href="<?php echo $here ? '#' : htmlspecialchars($to); ?>"<?php
	echo $cls ? ' class="'.$cls.'"' : '';
	echo $here ? ' aria-current="page"' : '';
	?> title="<?php echo htmlspecialchars($title); ?>"><b><?php echo $label; ?></b><span><?php
	echo $locked ? 'sign in' : $hint; ?></span></a>
<?php endforeach; ?>
</nav>

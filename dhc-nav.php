<?php
/**
 * dhc-nav.php — the strip that ties the three DHC pages together.
 *
 * Build a Fighter, look at what everyone has built, fight with it. Those are
 * one activity, and they were three pages you had to find: the Collection was
 * not in the platform nav at all, and getting from the Arena back to the
 * assembler meant opening a menu or knowing a link was buried in a paragraph.
 *
 * ONE FILE, INCLUDED ON EVERY DHC PAGE, so the strip cannot drift into four
 * slightly different strips. Set $dhcnav_at before including:
 *
 *     $dhcnav_at = 'fighters' | 'collection' | 'arena' | 'sandbox';
 *     include 'dhc-nav.php';
 *
 * SIGNED OUT, ONE OF THE THREE IS A TRAP. The Arena and the Collection are
 * public; dhcfighters.php still includes skulliance.php, which redirects an
 * anonymous visitor to error.php. Linking a guest straight into that is the
 * exact wall the public Arena removed, so for a guest that one points at the
 * sign-in page and says why. Set $dhcnav_guest = true on any page a guest can
 * reach -- the flag on each item below is what decides, not the caller, so
 * opening another page to the public is one 1 changed to a 0 here.
 *
 * Relative hrefs, never absolute: the login cookie is host-only, so an
 * absolute www link can hop hosts and silently sign the player out.
 */
$dhcnav_at    = isset($dhcnav_at) ? $dhcnav_at : '';
$dhcnav_guest = !empty($dhcnav_guest);

/*
 * [href, label, hint, gated, mobile_only]
 *
 * THE FIFTH FIELD IS WHY THIS IS NO LONGER FOUR ENTRIES. "Where traits drop"
 * is a column of dhcfighters.php, so on a desktop it is already on screen and
 * a link to it would be clutter. On a phone that page stacks and the list
 * sits below the assembler, the canvas, the draw order and twenty roster
 * cards -- and "what can I still pull today" is the single most-repeated
 * question a trait hunter has, several times a day. So the shortcut exists
 * only where the scrolling does.
 *
 * It is an ANCHOR into that page, not a page of its own: a second URL, a
 * second menu entry and a second copy of the panel's markup, all to show a
 * list that is already rendered, is a worse trade than a hash.
 *
 * Label is the noun and the hint is the verb, like every other entry here --
 * Fighters/build, Collection/browse, Arena/fight. Hence Games/review rather
 * than "Status", which is neither.
 */
$dhcnav_items = array(
	'fighters'   => array('dhcfighters.php', 'Fighters',   'build',  1, 0),
	'status'     => array('dhcfighters.php#drops', 'Games', 'review', 1, 1),
	'collection' => array('dhcgallery.php',  'Collection', 'browse', 0, 0),
	'arena'      => array('dhcarena.php',    'Arena',      'fight',  0, 0),
	'sandbox'    => array('dhcsandbox.php',  'Sandbox',    'experiment', 0, 0),
);
?>
<style>
/* Scoped to its own class and written with fallbacks, because it lands on three
   pages that each declare their own --ochre. */
.dhcnav{display:flex;gap:6px;margin:0 0 14px;flex-wrap:nowrap;overflow-x:auto;
  -webkit-overflow-scrolling:touch;scrollbar-width:none}
.dhcnav::-webkit-scrollbar{display:none}
/* Desktop never shows the Games shortcut: the list it jumps to is already a
   column of the page it jumps into. */
.dhcnav a.only-mobile{display:none}
@media (max-width:700px){
  /* TWO ROWS, NOT A SIDEWAYS SCROLLER. Five chips do not fit a phone, and a
     horizontal scroller hides whichever ones overflow behind a gesture
     nobody is told about -- the same trap the trait grid had. Wrapping costs
     about thirty pixels and shows all five. The strip sits below the navbar
     in normal flow, so nothing here can reach the burger. */
  .dhcnav{flex-wrap:wrap;overflow-x:visible}
  .dhcnav a{flex:1 1 auto;align-items:center}
  .dhcnav a.only-mobile{display:flex}
}
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
	/* Hidden with CSS rather than skipped in PHP: one menu, one markup, and
	   no server-side guess about what device is asking. */
	list($href, $label, $hint, $gated, $mobile) = $it;
	$here   = ($key === $dhcnav_at);
	$locked = ($dhcnav_guest && $gated && !$here);
	$to     = $locked ? 'index.php' : $href;

	/*
	 * AN ANCHOR INTO THE PAGE YOU ARE ALREADY ON MUST BE JUST THE HASH.
	 *
	 * "dhcfighters.php#drops" only jumps in place when everything before the
	 * "#" matches the current URL EXACTLY. It does not while editing
	 * (?edit=12), and any mismatch makes it a navigation instead -- which
	 * reloads the assembler, the trait index, the roster art and two
	 * leaderboards to reach a list already rendered further down the same
	 * document. In the installed PWA that reload was reported as never
	 * finishing at all.
	 *
	 * Derived rather than special-cased on 'status': any future item that
	 * points a fragment at its own page gets the same treatment, and nothing
	 * has to remember to add it.
	 */
	$hash = strpos($href, '#');
	if (!$locked && $hash !== false
	    && isset($dhcnav_items[$dhcnav_at])
	    && $dhcnav_items[$dhcnav_at][0] === substr($href, 0, $hash)) {
		$to = substr($href, $hash);
	}
	$cls    = trim(($here ? 'on' : ($locked ? 'locked' : '')) . ($mobile ? ' only-mobile' : ''));
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

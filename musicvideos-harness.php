<?php
/* musicvideos-harness.php - CLI only. No database, no network.
 *
 * Matching a music video to the project it was made for.
 *
 * WHY THIS IS NOT OBVIOUS. The videos arrive by FTP named after their
 * projects, so the match is a slug comparison -- and the failure is
 * silent in the worst way: an unmatched file still renders, just with a
 * filename where the artist's logo should be. Nobody gets an error, the
 * page looks finished, and one artist's film is the only one on the page
 * without their mark on it.
 *
 * The project list below is the owner's own export, so the slugs are the
 * real ones: "f.ART" slugs to "fart" and its video is projectvenus.mp4,
 * "Heist on Alpha" to "heistonalpha", "Dead Pop Hell" to "deadpophell".
 *
 * Driven against a fake mysqli and a real temporary directory, so this
 * runs the shipping mv_videos() rather than a copy of its logic.
 *
 * Usage: php musicvideos-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/music-videos.php';

/* The owner's projects table, trimmed to what mv_videos() reads. */
$PROJECTS = array(
	array(9, 'Maxingo', 'MAXI'), array(10, 'Danketsu', 'DANK'),
	array(12, 'Netanel Cohen', 'NAT'), array(14, 'Apprentices', 'USTRA'),
	array(17, 'Dead Pop Hell', 'FIRE'), array(20, 'Pendulum', 'KALA'),
	array(21, 'Disco Solaris', 'MOON'), array(24, 'Hellknights', 'SOUL'),
	array(27, 'Heist on Alpha', 'LOOT'), array(28, 'f.ART', 'VENUS'),
	array(29, 'Darkula', 'AXION'), array(35, 'Havoc Worlds', 'HAVOC'),
	array(1, 'Galactico', 'STAR'), array(7, 'Skulliance', 'DIAMOND'),
);
$VIDEOS = array('apprentices', 'danketsu', 'darkula', 'deadpophell', 'discosolaris',
                'havocworlds', 'heistonalpha', 'hellknights', 'maxingo', 'netanelcohen',
                'pendulum', 'projectvenus',
                /* NOT one of the owner's twelve. The lookup falls back to the
                   CURRENCY when the name does not match, which is how
                   projectvenus.mp4 would have resolved without its alias --
                   and with the real twelve all matching by name, nothing
                   exercised that path at all. 'star' is Galactico's. */
                'star');

/* ---- a mysqli stand-in that answers the two queries mv_videos() makes ---- */
class MvRes {
	private $rows; private $i = 0;
	public function __construct($rows) { $this->rows = $rows; }
	public function fetch_assoc() { return $this->i < count($this->rows) ? $this->rows[$this->i++] : null; }
}
class MvConn {
	public $projects; public $themes;
	public function __construct($p, $t) { $this->projects = $p; $this->themes = $t; }
	public function query($sql) {
		if (strpos($sql, 'FROM projects') !== false) {
			$out = array();
			foreach ($this->projects as $p) {
				$out[] = array('id' => $p[0], 'name' => $p[1], 'currency' => $p[2]);
			}
			return new MvRes($out);
		}
		if (strpos($sql, 'FROM monstrocity_themes') !== false) return new MvRes($this->themes);
		return false;
	}
}

/* A webroot with the videos, the theme logos and the currency icons in it. */
$root = sys_get_temp_dir() . '/mv-' . getmypid();
@mkdir($root . '/videos', 0777, true);
foreach ($VIDEOS as $v) file_put_contents($root . '/videos/' . $v . '.mp4', 'x');
@mkdir($root . '/icons', 0777, true);
foreach ($PROJECTS as $p) file_put_contents($root . '/icons/' . strtolower($p[2]) . '.png', 'x');
/* Every project here has a theme EXCEPT f.ART, which is the real state. */
$themes = array();
foreach ($PROJECTS as $p) {
	if ($p[1] === 'f.ART') continue;
	$slug = mv_slug($p[1]);
	$themes[] = array('project_id' => $p[0], 'slug' => $slug);
	@mkdir($root . '/images/monstrocity/' . $slug, 0777, true);
	file_put_contents($root . '/images/monstrocity/' . $slug . '/logo.png', 'x');
}
$conn = new MvConn($PROJECTS, $themes);
$got  = mv_videos($conn, $root);
$by   = array();
foreach ($got as $g) $by[basename($g['file'], '.mp4')] = $g;

echo "every video finds its project\n";
{
	ok(count($got) === count($VIDEOS),
	   'got ' . count($got) . ' videos from ' . count($VIDEOS) . ' files');
	$unmatched = array();
	foreach ($got as $g) if (!$g['project_id']) $unmatched[] = basename($g['file']);
	ok(!$unmatched,
	   'these fall back to a filename where the artist\'s mark should be: '
	 . implode(', ', $unmatched));

	/* The ones whose filename is not their name. */
	ok(isset($by['projectvenus']) && $by['projectvenus']['title'] === 'f.ART',
	   'projectvenus.mp4 is not matched to f.ART -- its file is named for its currency, '
	 . 'and the project name slugs to something else entirely');
	/* Matched on the currency, not the name. */
	ok(isset($by['star']) && $by['star']['title'] === 'Galactico',
	   'star.mp4 did not fall back to matching Galactico by its currency');
	/* And the ordinary ones, including the multi-word names. */
	foreach (array('heistonalpha' => 'Heist on Alpha', 'deadpophell' => 'Dead Pop Hell',
	               'netanelcohen' => 'Netanel Cohen', 'havocworlds' => 'Havoc Worlds',
	               'discosolaris' => 'Disco Solaris') as $f => $want) {
		ok(isset($by[$f]) && $by[$f]['title'] === $want,
		   $f . '.mp4 resolved to "' . (isset($by[$f]) ? $by[$f]['title'] : '?') . '"');
	}
}

echo "\nwhat is printed under each one\n";
{
	/* A theme logo where there is one. */
	$noLogo = array();
	foreach ($got as $g) if ($g['logo'] === '') $noLogo[] = $g['title'];
	ok($noLogo === array('f.ART'),
	   'expected only f.ART to have no theme logo, got: ' . implode(', ', $noLogo));
	ok(isset($by['discosolaris']) && strpos($by['discosolaris']['logo'], 'images/monstrocity/') === 0,
	   'a theme logo does not come from the theme directory');

	/* And the points icon behind it, which every project has. */
	ok(isset($by['projectvenus']) && $by['projectvenus']['icon'] === 'icons/venus.png',
	   'f.ART falls through to a bare name instead of its points icon');
	$noIcon = array();
	foreach ($got as $g) if ($g['icon'] === '') $noIcon[] = $g['title'];
	ok(!$noIcon, 'no points icon found for: ' . implode(', ', $noIcon));

	/* The page prefers logo, then icon, then name -- in that order. */
	$pg = harness_strip_comments(file_get_contents(__DIR__ . '/musicvideos.php'));
	$l = strpos($pg, "\$v['logo'] !== ''");
	$i = strpos($pg, "\$v['icon'] !== ''");
	ok($l !== false && $i !== false && $l < $i,
	   'the points icon is preferred over the project\'s own logo');
	ok(strpos($pg, "<span><?php echo \$e(\$v['title']); ?></span>") !== false,
	   'there is no name left for a video with neither a logo nor an icon');
}

echo "\na missing file never becomes a broken frame\n";
{
	/* An icon that is not on disk must fall through to the name. */
	@unlink($root . '/icons/venus.png');
	$g2 = mv_videos($conn, $root);
	$fart = null;
	foreach ($g2 as $g) if ($g['title'] === 'f.ART') $fart = $g;
	ok($fart && $fart['icon'] === '',
	   'a points icon that is not on the server is still printed, as a broken image');
	ok($fart && $fart['title'] === 'f.ART', 'the name is gone too, so nothing identifies it');
	file_put_contents($root . '/icons/venus.png', 'x');

	/* A logo directory with no logo.png in it, likewise. */
	@unlink($root . '/images/monstrocity/darkula/logo.png');
	$g3 = mv_videos($conn, $root);
	foreach ($g3 as $g) if ($g['title'] === 'Darkula') {
		ok($g['logo'] === '', 'a theme with no logo.png still prints one');
		ok($g['icon'] === 'icons/axion.png', 'and it does not fall back to the points icon');
	}
	file_put_contents($root . '/images/monstrocity/darkula/logo.png', 'x');

	/* An unknown video is SHOWN, not dropped. */
	file_put_contents($root . '/videos/somebodynew.mp4', 'x');
	$g4 = mv_videos($conn, $root);
	ok(count($g4) === count($VIDEOS) + 1, 'a video with no matching project was dropped');
	$new = null;
	foreach ($g4 as $g) if (strpos($g['file'], 'somebodynew') !== false) $new = $g;
	ok($new && $new['title'] === 'Somebodynew', 'an unmatched video has no readable title');
	@unlink($root . '/videos/somebodynew.mp4');

	/* No videos at all is a sentence, not an empty page. */
	foreach ($VIDEOS as $v) @unlink($root . '/videos/' . $v . '.mp4');
	ok(mv_videos($conn, $root) === array(), 'an empty folder does not return an empty list');
	ok(strpos($pg, 'No videos have been uploaded yet') !== false,
	   'an empty folder renders a blank page with no explanation');
	foreach ($VIDEOS as $v) file_put_contents($root . '/videos/' . $v . '.mp4', 'x');
}

echo "\nthe page is public and cheap to open\n";
{
	$pg = harness_strip_comments(file_get_contents(__DIR__ . '/musicvideos.php'));
	/* PUBLIC: these are the artists' films and the point is being seen. */
	ok(strpos($pg, "include 'skulliance.php'") === false
	   && strpos($pg, "verify.php") === false,
	   'the page gates on login, so the one place the art lives is the hardest to reach');
	ok(strpos($pg, 'site-header.php') !== false,
	   'a stranger lands with no navigation at all');
	/* TWELVE VIDEOS, NOT TWELVE DOWNLOADS. */
	/*
	 * ONE VIDEO ELEMENT ON THE PAGE, in the player. The grid used to be
	 * twelve of them, each asking for metadata and seeking to its own
	 * midpoint for a still: that opened more connections than a browser
	 * allows per host, and on the live page most tiles sat on a spinner
	 * showing nothing at all. The tiles are images now.
	 *
	 * Counted on the RAW file, because harness_strip_comments() leaves
	 * <?php /* ... *<?php ?>/ ?> blocks alone and the comments here
	 * discuss <video at length.
	 */
	$rawpg = file_get_contents(__DIR__ . '/musicvideos.php');
	/* A real TAG -- <video followed by an attribute. Counting the bare
	   string finds the two comments that talk about "<video> elements". */
	$nvid = preg_match_all('/<video\s+[a-z]+=/i', $rawpg);
	ok($nvid === 1,
	   $nvid . ' video elements on the page; twelve of them loading at once is what filled the '
	 . 'grid with spinners');
	ok(preg_match('/<video[^>]*id="mvp-video"/', $rawpg) === 1, 'the player has no video element');
	ok(preg_match('/<video[^>]*playsinline/', $rawpg) === 1,
	   'playing a video on an iPhone takes over the whole screen');
	ok(preg_match('/<video[^>]*controls/', $rawpg) === 1, 'the player has no controls');
	/* No src in the markup, or it downloads before anybody asks. */
	ok(preg_match('/<video[^>]*\ssrc=/', $rawpg) === 0,
	   'the player carries a src in the markup, so one film downloads on every page load');

	/* THE TILES ARE IMAGES, and the space they take is reserved. */
	ok(strpos($pg, 'aspect-ratio:4/3') !== false,
	   'the tile reserves no space, so the grid jumps about as the theme images arrive');
	ok(strpos($pg, 'aspect-ratio:1/1') !== false,
	   'the player is not square, and the films are');
	ok(preg_match('/<img[^>]*loading="lazy"/', $rawpg) === 1
	   || strpos($rawpg, "'eager' : 'lazy'") !== false,
	   'every theme image loads at once; they are up to 1.4MB each');
	/*
	 * THE CARD. This page is meant to be shared -- that is most of why it
	 * is public -- and a link with no card is a grey rectangle with a url
	 * in it. $extra_head is how every other page here does this, and it
	 * only works BEFORE header.php.
	 */
	$raw = file_get_contents(__DIR__ . '/musicvideos.php');
	$at  = strpos($raw, '$extra_head');
	$hd  = strpos($raw, "include 'header.php'");
	ok($at !== false, 'the page sets no share card at all');
	ok($at !== false && $hd !== false && $at < $hd,
	   'the card is set after header.php has already printed the head, so none of it lands');
	foreach (array('og:title', 'og:description', 'og:image', 'og:url',
	               'twitter:card', 'twitter:image') as $tag) {
		ok(strpos($raw, $tag) !== false, 'the card has no ' . $tag);
	}
	ok(strpos($raw, 'summary_large_image') !== false,
	   'the card is the small square kind, which buries the picture');
	/* ABSOLUTE. A crawler has no page to resolve a relative url against.
	   Checked on the VALUE, not the tag: the tag is built by concatenation,
	   so matching a literal url in the markup fails on correct code. */
	ok(preg_match('/\\$mv_og\\s*=\\s*.https:\\/\\//', $raw) === 1,
	   'the card image is a relative url, which a crawler cannot fetch');
	ok(preg_match('/\\$mv_canon\\s*=\\s*.https:\\/\\//', $raw) === 1,
	   'the canonical url is relative');
	ok(strpos($raw, 'rel="canonical"') !== false, 'there is no canonical url');
	/* The stated size has to be the file's real one, or the crop is wrong. */
	ok(strpos($raw, '<meta property="og:image:width" content="1416">') !== false
	   && strpos($raw, '<meta property="og:image:height" content="1113">') !== false,
	   'the card states a size images/og.jpg does not have');

	/*
	 * THE PLAYER. The whole reason the grid is images is that the film
	 * should not be fetched until somebody asks for it -- so the closing
	 * half matters as much as the opening one: a paused video that still
	 * has a src keeps its buffer, and on some browsers keeps filling it.
	 */
	$pl = strpos($rawpg, "var veil = document.getElementById('mvp-veil')");
	ok($pl !== false, 'the player script is gone');
	$player = $pl === false ? '' : substr($rawpg, $pl, 2200);
	ok(strpos($player, "vid.removeAttribute('src')") !== false,
	   'closing the player leaves the src in place, so the film keeps downloading');
	ok(strpos($player, 'vid.load()') !== false,
	   'the src is dropped but load() is not called, so the buffer survives');
	ok(strpos($player, "vid.pause()") !== false, 'closing the player does not stop playback');
	ok(strpos($player, "e.key === 'Escape'") !== false, 'Escape does not close the player');
	ok(strpos($player, 'e.target === veil') !== false,
	   'clicking the video itself closes the player');
	ok(strpos($player, 'opener.focus()') !== false,
	   'closing drops keyboard focus, so a keyboard user is dumped at the top of the page');
	/* A tile is a button, or it cannot be reached without a mouse. */
	ok(preg_match('/<button[^>]*class="mv-item"/', $rawpg) === 1,
	   'the tiles are not buttons, so none of this is reachable by keyboard');
	ok(strpos($rawpg, 'data-src=') !== false && strpos($player, "getAttribute('data-src')") !== false,
	   'the tile does not carry the film it opens');
	/* Autoplay is attempted but never assumed. */
	ok(strpos($player, 'p.catch') !== false,
	   'a refused autoplay throws an unhandled rejection into the console on every open');

	/* The menu has to point at it. */
	$hdr = harness_strip_comments(file_get_contents(__DIR__ . '/header.php'));
	ok(strpos($hdr, 'musicvideos.php') !== false, 'nothing in the NFTs menu links to the page');
}

/* tidy */
foreach (glob($root . '/videos/*') as $f) @unlink($f);
foreach (glob($root . '/icons/*') as $f) @unlink($f);
foreach (glob($root . '/images/monstrocity/*/logo.png') as $f) @unlink($f);
foreach (glob($root . '/images/monstrocity/*') as $d) @rmdir($d);
@rmdir($root . '/images/monstrocity'); @rmdir($root . '/images');
@rmdir($root . '/videos'); @rmdir($root . '/icons'); @rmdir($root);

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);

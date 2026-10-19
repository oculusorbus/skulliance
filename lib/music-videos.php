<?php
/**
 * lib/music-videos.php -- the music videos, matched to the projects they are for.
 *
 * THE DIRECTORY IS THE LIST. Videos arrive by FTP like every other piece of
 * media on this platform, so a hardcoded roster would mean a code change to
 * publish one and a page that is wrong the first time somebody uploads
 * without saying. Whatever is in the folder is what the page shows.
 *
 * MATCHED BY SLUG, WITH ROOM FOR THE ONES THAT DO NOT MATCH. Almost every
 * file is named after its project -- discosolaris.mp4, heistonalpha.mp4 --
 * so a project whose name slugs to the filename is the match, and a video
 * uploaded later just works. The exceptions are real though: f.ART's video
 * is projectvenus.mp4, after its currency rather than its name, and
 * slugging "f.ART" gives "fart". Those get a line in MV_ALIASES rather than
 * a special case in the lookup.
 *
 * An unmatched file is still SHOWN, titled from its own filename. A video
 * the owner uploaded is a video the owner wants seen; dropping it because
 * this file has not been told about the project would be the page quietly
 * disagreeing with the folder.
 */

/** Filenames whose project cannot be guessed from the name. */
$GLOBALS['MV_ALIASES'] = array(
	/* f.ART's video is named for its currency, VENUS, and its project name
	   slugs to something nobody wants printed under a video. */
	'projectvenus' => 'f.ART',
);

/**
 * How big an mp4 actually is, read out of its own header.
 *
 * WHY THE SERVER HAS TO KNOW. The player is sized to the film's real
 * resolution, and if that only arrives with loadedmetadata the modal
 * opens at a guess and then visibly jumps to the right size. Knowing it
 * before the page is printed means it opens correct and never moves.
 *
 * The width and height are the LAST EIGHT BYTES of the tkhd box, as two
 * 16.16 fixed-point numbers -- which is true for both versions of tkhd,
 * so this does not have to care which it is looking at. Walks the atom
 * tree rather than scanning for bytes, because 'moov' and 'trak' appear
 * inside compressed data often enough that a scan finds the wrong one.
 *
 * Returns array(w, h), or array(0, 0) for anything it cannot read -- the
 * caller falls back to sizing from the browser, which is where it was.
 */
function mv_video_dims($abs) {
	$fh = @fopen($abs, 'rb');
	if (!$fh) return array(0, 0);
	$res = mv_find_tkhd($fh, 0, filesize($abs));
	fclose($fh);
	return $res ? $res : array(0, 0);
}

/** Walk one level of atoms between $pos and $end, descending where useful. */
function mv_find_tkhd($fh, $pos, $end, $depth = 0) {
	if ($depth > 4) return null;
	while ($pos + 8 <= $end) {
		if (fseek($fh, $pos) !== 0) return null;
		$hdr = fread($fh, 8);
		if (strlen($hdr) < 8) return null;
		$u = unpack('Nsize/a4type', $hdr);
		$size = $u['size'];
		$head = 8;
		/* size 1 means a 64-bit length follows; size 0 means "to the end". */
		if ($size === 1) {
			$ext = fread($fh, 8);
			if (strlen($ext) < 8) return null;
			$p = unpack('Nhi/Nlo', $ext);
			$size = $p['hi'] * 4294967296 + $p['lo'];
			$head = 16;
		} elseif ($size === 0) {
			$size = $end - $pos;
		}
		if ($size < $head) return null;                 /* malformed */

		if ($u['type'] === 'tkhd') {
			/* The last eight bytes: width and height, 16.16 fixed. */
			if (fseek($fh, $pos + $size - 8) !== 0) return null;
			$wh = fread($fh, 8);
			if (strlen($wh) < 8) return null;
			$v = unpack('Nw/Nh', $wh);
			$w = (int) round($v['w'] / 65536);
			$h = (int) round($v['h'] / 65536);
			if ($w > 0 && $h > 0) return array($w, $h);
		} elseif ($u['type'] === 'moov' || $u['type'] === 'trak') {
			$in = mv_find_tkhd($fh, $pos + $head, $pos + $size, $depth + 1);
			if ($in) return $in;
		}
		$pos += $size;
	}
	return null;
}

/**
 * A small copy of a big image, made once and kept.
 *
 * THE REALM THEME ART IS 2000x1500 AND UP TO 1.4MB. Twelve of those behind
 * a 380px tile is ten megabytes to look at a grid: the page sat spinning,
 * and -- worse -- the video a visitor then clicked had to queue behind
 * them for one of the browser's six connections, so it looked like the
 * films were broken when they were only waiting.
 *
 * GENERATED FROM A FILE ALREADY ON THE SERVER, which is the only kind of
 * image this repo can make: the originals arrive by FTP and nothing here
 * can add one. Written once and reused; if the source is ever replaced,
 * a newer mtime rebuilds it.
 *
 * ANY FAILURE RETURNS THE ORIGINAL. An unwritable directory, no GD, a
 * corrupt jpeg -- none of those should cost the page its picture, they
 * should only cost it the saving.
 */
function mv_thumb($webroot, $rel, $maxw = 620) {
	$root = rtrim($webroot, '/');
	$src  = $root . '/' . $rel;
	if (!is_file($src) || !function_exists('imagecreatefromjpeg')) return $rel;

	$dir = $root . '/images/themes/thumbs';
	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return $rel;

	$out    = $dir . '/' . basename($rel);
	$relOut = 'images/themes/thumbs/' . basename($rel);
	/* Fresh enough? The source changing is the only thing that invalidates
	   it, and comparing mtimes is cheaper than any other answer. */
	if (is_file($out) && @filemtime($out) >= @filemtime($src)) return $relOut;

	$d = @getimagesize($src);
	if (!$d || $d[0] <= 0) return $rel;
	/* Already small enough: copying it would be work for nothing. */
	if ($d[0] <= $maxw) return $rel;

	$im = @imagecreatefromjpeg($src);
	if (!$im) return $rel;
	$w = $maxw;
	$h = (int) round($d[1] * ($maxw / $d[0]));
	$tn = @imagecreatetruecolor($w, $h);
	if (!$tn) return $rel;
	imagecopyresampled($tn, $im, 0, 0, 0, 0, $w, $h, $d[0], $d[1]);
	/* Written to a temp name and renamed, because two visitors can arrive
	   at once and a half-written jpeg is worse than a big one. */
	$tmp = $out . '.' . getmypid() . '.tmp';
	$okw = @imagejpeg($tn, $tmp, 82) && @rename($tmp, $out);
	if (!$okw) { @unlink($tmp); return $rel; }
	return $relOut;
}

/** Where the videos live. Relative: the page is the only thing that reads it. */
function mv_dir() { return 'videos'; }

/** A name reduced to the shape a filename takes: lowercase, letters and digits. */
function mv_slug($s) { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $s)); }

/**
 * Every video on disk, newest knowledge of the projects attached.
 *
 * Returns a list of array('file', 'w', 'h', 'title', 'project_id', 'logo',
 * 'icon', 'poster'), ordered by the title shown. 'w' and 'h' come from the
 * mp4's own header and are 0 when it could not be read.
 *
 * 'poster' is the project's REALM THEME image, images/themes/<project
 * id>.jpg -- realms.php picks a theme by project id, so the file is
 * already keyed exactly the way this needs it. It is the tile the grid
 * shows; the video itself is only fetched when somebody opens it.
 *
 * THREE WAYS TO LABEL A VIDEO, in descending order of how much it looks
 * like the project's own mark:
 *
 *   logo   the Monstrocity theme's logo, drawn for that project
 *   icon   the currency icon, white on transparent -- every project has
 *          one, so this is the backstop for a project with no theme
 *   title  the name, for anything neither of those covers
 *
 * Today exactly one video lands on the icon: f.ART, which has no theme.
 */
function mv_videos($conn, $webroot) {
	$dir = rtrim($webroot, '/') . '/' . mv_dir();
	$files = glob($dir . '/*.mp4');
	if (!$files) return array();

	/* projects, by slug and by currency, in one pass. */
	$byName = array(); $byCur = array(); $names = array(); $icons = array();
	$r = $conn ? @$conn->query('SELECT id, name, currency FROM projects') : null;
	if ($r) while ($p = $r->fetch_assoc()) {
		$id = (int) $p['id'];
		$names[$id] = (string) $p['name'];
		$byName[mv_slug($p['name'])] = $id;
		$cur = mv_slug($p['currency']);
		/* FIRST WINS. Several projects share a Discord and could share a
		   currency spelling; a later one must not steal an earlier match. */
		if ($cur !== '' && !isset($byCur[$cur])) $byCur[$cur] = $id;
		/* The currency icon, which is the same file the rest of the
		   platform draws for points. Checked on disk: a missing one must
		   fall through to the name rather than print a broken frame. */
		if ($cur !== '' && is_file(rtrim($webroot, '/') . '/icons/' . $cur . '.png')) {
			$icons[$id] = 'icons/' . $cur . '.png';
		}
	}

	/*
	 * A project's Monstrocity theme.
	 *
	 * STRAIGHT OFF monstrocity_themes.project_id, which is the column the
	 * admin writes. The first version went the long way round --
	 * monstrocity_theme_collections -> collections -> projects -- and on
	 * the live site exactly ONE of twelve videos found a logo that way.
	 * That link table is sparse: it is the newer per-collection mapping
	 * and most themes have no rows in it at all, so the join quietly
	 * returned nothing for eleven projects and they all fell through to
	 * their points icon. Nothing looked broken, which is the problem.
	 *
	 * Active themes first, so a live theme beats a draft for the same
	 * project; within that, the admin's own ordering.
	 */
	$logos = array();
	$r = $conn ? @$conn->query(
		'SELECT project_id, slug
		   FROM monstrocity_themes
		  WHERE project_id IS NOT NULL AND project_id > 0
		  ORDER BY active DESC, sort_order ASC, id ASC') : null;
	if ($r) while ($t = $r->fetch_assoc()) {
		$pid  = (int) $t['project_id'];
		$slug = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $t['slug']);
		if ($pid <= 0 || $slug === '' || isset($logos[$pid])) continue;
		/* CHECKED ON DISK. A theme row is not a promise that anybody drew
		   a logo for it; without this a themed project with no logo.png
		   prints a broken frame instead of falling back to its icon. */
		$path = 'images/monstrocity/' . $slug . '/logo.png';
		if (is_file(rtrim($webroot, '/') . '/' . $path)) $logos[$pid] = $path;
	}

	$out = array();
	foreach ($files as $f) {
		$base = basename($f, '.mp4');
		$slug = mv_slug($base);

		$pid = 0;
		if (isset($GLOBALS['MV_ALIASES'][$slug])) {
			$alias = mv_slug($GLOBALS['MV_ALIASES'][$slug]);
			if (isset($byName[$alias])) $pid = $byName[$alias];
		}
		if (!$pid && isset($byName[$slug])) $pid = $byName[$slug];
		if (!$pid && isset($byCur[$slug]))  $pid = $byCur[$slug];

		/* Read once per page from the file's own header, so the player can
		   open at the right size instead of jumping to it. */
		$dim = mv_video_dims($f);

		$out[] = array(
			'file'       => mv_dir() . '/' . basename($f),
			'w'          => $dim[0],
			'h'          => $dim[1],
			/* Unmatched: the filename, spaced out, rather than nothing. */
			'title'      => $pid ? $names[$pid] : ucwords(preg_replace('/[-_]+/', ' ', $base)),
			'project_id' => $pid,
			'logo'       => ($pid && isset($logos[$pid])) ? $logos[$pid] : '',
			'icon'       => ($pid && isset($icons[$pid])) ? $icons[$pid] : '',
			/* Checked on disk like the rest: a project with no realm
			   theme gets no tile image and the page draws a plain panel
			   rather than a broken frame. */
			/* The tile art, shrunk to something a tile actually needs. */
			'poster'     => ($pid && is_file(rtrim($webroot, '/') . '/images/themes/' . $pid . '.jpg'))
			                ? mv_thumb($webroot, 'images/themes/' . $pid . '.jpg') : '',
		);
	}

	usort($out, function ($a, $b) { return strcasecmp($a['title'], $b['title']); });
	return $out;
}

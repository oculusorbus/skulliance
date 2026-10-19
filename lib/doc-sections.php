<?php
/**
 * lib/doc-sections.php -- show part of a Skull Paper page somewhere else.
 *
 * WHY THIS EXISTS RATHER THAN A SECOND COPY OF THE RULES. An in-game guide
 * and the Skull Paper say the same things, and the moment they are two
 * pieces of text one of them is wrong -- it is always the one nobody
 * opened recently, and here that would be the one players actually read
 * while playing. Every other place this platform kept two copies of
 * something (the partner roster, the invite url, the iOS save) drifted
 * within weeks, so the guide is a VIEW of skullpaper/<slug>.md.
 *
 * It also means a rules change lands in both places by being written once,
 * which is the point of the Skull Paper in the first place.
 *
 * A REQUESTED HEADING THAT IS NOT THERE IS REPORTED, not silently skipped:
 * renaming a heading in the markdown would otherwise quietly empty a
 * section of the guide, and nothing on either page would look broken.
 */

require_once __DIR__ . '/Parsedown.php';

/**
 * What a Skull Paper page calls itself: its first level-one heading.
 *
 * Cached per request because one guide can link the same page twice.
 */
function doc_title($slug) {
	static $seen = array();
	if (isset($seen[$slug])) return $seen[$slug];
	$seen[$slug] = '';
	if (!preg_match('/^[a-z0-9-]+$/', (string) $slug)) return '';
	$f = __DIR__ . '/../skullpaper/' . $slug . '.md';
	if (!is_readable($f)) return '';
	$h = @fopen($f, 'r');
	if (!$h) return '';
	while (($line = fgets($h)) !== false) {
		if (preg_match('/^#\s+(.+?)\s*$/', $line, $m)) { $seen[$slug] = $m[1]; break; }
	}
	fclose($h);
	return $seen[$slug];
}

/**
 * Pull named sections out of a Skull Paper page and render them to HTML.
 *
 * A section runs from its heading to the next heading of the SAME OR
 * HIGHER level, so asking for "## The board" brings its "###" subsections
 * with it -- which is what a reader expects and what the author intended
 * by nesting them.
 *
 * Returns array('html' => string, 'missing' => array of headings not found).
 */
function doc_sections($slug, array $headings) {
	$out = array('html' => '', 'missing' => array());

	/* The slug is a FILENAME. Anything but a plain doc name is refused
	   rather than escaped -- nothing here needs a path. */
	if (!preg_match('/^[a-z0-9-]+$/', (string) $slug)) return $out;
	$file = __DIR__ . '/../skullpaper/' . $slug . '.md';
	if (!is_readable($file)) return $out;

	$lines = explode("\n", (string) file_get_contents($file));

	/* Index every heading once: level, text, line. */
	$heads = array();
	foreach ($lines as $i => $line) {
		if (preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $m)) {
			$heads[] = array('level' => strlen($m[1]), 'text' => $m[2], 'line' => $i);
		}
	}

	$chunks = array();
	foreach ($headings as $want) {
		$found = null;
		foreach ($heads as $k => $h) {
			if (strcasecmp($h['text'], $want) === 0) { $found = $k; break; }
		}
		if ($found === null) { $out['missing'][] = $want; continue; }

		$start = $heads[$found]['line'];
		$end   = count($lines);
		for ($k = $found + 1; $k < count($heads); $k++) {
			if ($heads[$k]['level'] <= $heads[$found]['level']) { $end = $heads[$k]['line']; break; }
		}
		$chunks[] = implode("\n", array_slice($lines, $start, $end - $start));
	}
	if (!$chunks) return $out;

	$md = implode("\n\n", $chunks);

	/*
	 * NO SCREENSHOTS. The guide opens over the thing it is describing, so
	 * a picture of that same thing is weight for nothing -- and the images
	 * reach this server by FTP, so one that has not been uploaded yet is a
	 * broken frame in the middle of the rules.
	 */
	$md = preg_replace('/^!\[[^\]]*\]\([^)]*\)\s*$/m', '', $md);
	$md = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $md);

	/*
	 * [[slug]] is the Skull Paper's own cross-link, resolved here so the
	 * guide can send a reader to the rest of it.
	 *
	 * THE LABEL IS THE TARGET PAGE'S OWN TITLE, read out of its first #
	 * heading. De-hyphenating the slug gave "games monstrocity" and
	 * "games skull swap", and skullpaper.php's nav titles are a second
	 * list that would need keeping in step -- the page already knows what
	 * it is called. An unknown slug falls back to the de-hyphenated slug
	 * rather than rendering a dead link.
	 */
	$md = preg_replace_callback('/\[\[([a-z0-9-]+)\]\]/', function ($m) {
		$t = doc_title($m[1]);
		return '[' . ($t !== '' ? $t : str_replace('-', ' ', $m[1]))
		     . '](skullpaper.php?page=' . $m[1] . ')';
	}, $md);

	$pd = new Parsedown();
	$pd->setSafeMode(true);
	$out['html'] = $pd->text($md);
	return $out;
}

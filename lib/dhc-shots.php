<?php
/**
 * lib/dhc-shots.php -- where a DHC screenshot lives, in ONE place.
 *
 * dhcgame.php defined this and partner.php needs the same pictures. The
 * -sm.jpg preference is the whole point of having a function at all: drop a
 * smaller derivative on the server and every page showing that shot picks it
 * up, which only works while there is one copy of the rule.
 *
 * Images reach this server by FTP and are never committed, so a shot that is
 * not there yet returns an empty array and the caller renders no figure
 * rather than a broken frame.
 */

/**
 * A screenshot under a callout card.
 *
 * PREFERS A -sm.jpg IF ONE HAS BEEN UPLOADED. The originals are ~2000px
 * PNGs totalling 5.3MB for three pictures that render about 340px wide --
 * roughly ten times the pixels the card can show. A smaller JPEG beside
 * each is used automatically the moment it exists, with no code change:
 * the same arrangement the hero reel uses for its mp4 and webm.
 *
 * Returns array() when neither file is there, and the caller renders no
 * figure at all rather than a broken frame.
 *
 * Real dimensions come from the file rather than being hardcoded, so
 * width/height stay correct when the -sm version lands and the browser can
 * still reserve the right box before the image arrives.
 */
function dhc_shot_src($base) {
	foreach (array($base . '-sm.jpg', $base . '.png') as $f) {
		/* dirname(__DIR__), NOT __DIR__. This function used to live in
		   dhcgame.php where __DIR__ was the site root; moving it into lib/
		   silently repointed it at staking/lib/images/, which does not
		   exist -- so every shot resolved to nothing and BOTH pages
		   stopped showing their screenshots. The returned src stays
		   relative ('images/...'), which is correct from any page in
		   /staking/. */
		$abs = dirname(__DIR__) . '/images/' . $f;
		if (is_file($abs)) {
			$d = @getimagesize($abs);
			return array('src' => 'images/' . $f,
			             'w' => $d ? $d[0] : 0, 'h' => $d ? $d[1] : 0);
		}
	}
	return array();
}

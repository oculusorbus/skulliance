<?php
/**
 * DHC FIGHTERS -- Discord notifications
 *
 * Two announcements: a trait dropping, and a Fighter being saved.
 *
 * Both are FIRE AND FORGET. A Discord outage, a missing webhook credential or
 * a failed render must never cost a player their drop or their save -- every
 * entry point here is wrapped so the worst case is silence.
 *
 * Requires webhooks.php (discordmsg) and dhcfighters-lib.php.
 *
 * NOTHING HERE MAY PRINT. db.php runs with display_errors on, and these are
 * called from inside the AJAX save/claim endpoints, so a single notice would
 * land in the middle of a JSON response and break the very action being
 * announced. Callers buffer around these for that reason, and imagedestroy()
 * -- a no-op since PHP 8.0, deprecated in 8.5 -- is deliberately not used.
 */

/* avatar_url() -- required here rather than relied on from a caller: these are
   libraries, and a page that includes one without db.php would fatal on an
   undefined function at render time. require_once, so the usual path where
   db.php already pulled it in costs nothing. */
require_once __DIR__ . '/avatar-lib.php';
/*
 * LOAD THE WEBHOOKS OURSELVES.
 *
 * This file guards on function_exists('discordmsg') so a missing credential
 * degrades to silence -- but that same guard silently swallowed every
 * notification when the caller simply had not included webhooks.php.
 * ajax/dhc-claim-drop.php did not, so a real Obscura drop posted nothing and
 * looked exactly like a misconfigured webhook.
 *
 * Depending on nine game pages and three endpoints to each remember an include
 * is a rule that will be broken again by the next caller. The notifier needs
 * discordmsg(), so the notifier fetches it. webhooks.php is function
 * definitions plus an include_once of its credentials -- no side effects, and
 * include_once here makes a double load harmless.
 */
if (!function_exists('discordmsg') && is_file(__DIR__ . '/webhooks.php')) {
	include_once __DIR__ . '/webhooks.php';
}

/* Embed accent per tier -- the same ramp the drop modal and the assembler use,
   as the integer Discord wants rather than a hex string. */
function dhcf_tier_color($tier) {
	$map = array(
		'common'    => '7A9EB0',
		'uncommon'  => '00C8A0',
		'epic'      => '8B7BD8',
		'legendary' => 'F5A623',
		'mythic'    => 'FF4F8B',
	);
	return isset($map[$tier]) ? $map[$tier] : '7A9EB0';
}

/**
 * Absolute base for trait art. Discord fetches these itself, so relative fails.
 *
 * The directory scan is dhcf_art_dir() in dhcfighters-config.php -- one answer
 * for both, rather than two copies of the same candidate list drifting apart.
 * The function_exists guard is real: dhc-compose.php @include_once's this file
 * on its own, with no config loaded.
 */
function dhcf_art_base() {
	static $b = null;
	if ($b === null) {
		if (function_exists('dhcf_art_dir')) {
			$b = dhcf_art_dir();
		} else {
			$b = '';
			foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
				if (is_dir(__DIR__ . '/' . $c . '/1000')) { $b = $c; break; }
			}
		}
	}
	return 'https://skulliance.io/staking/' . $b;
}


/**
 * Author block for an embed: display name plus their Discord avatar.
 *
 * Returns the mention string as a third value. A trait can arrive from a game
 * a player has already navigated away from, so the announcement is often the
 * first they hear of it -- and an embed nobody is tagged in scrolls past
 * unnoticed. The mention is their receipt.
 */
function dhcf_author_block($conn, $user_id) {
	$res = $conn->query(sprintf(
		"SELECT username, discord_id, avatar FROM users WHERE id = %d LIMIT 1", (int)$user_id));
	if (!$res || !$res->num_rows) return array(null, 'a player', '');
	$u = $res->fetch_assoc();
	$name = $u['username'] !== '' ? $u['username'] : 'a player';
	$author = array('name' => $name);
	if (!empty($u['discord_id']) && !empty($u['avatar'])) {
		$author['icon_url'] = avatar_url($u['discord_id'], $u['avatar']);
	}
	/*
	 * A mention only notifies from discordmsg()'s top-level $content field --
	 * one written into the embed renders as a link and pings nobody. See the
	 * note on that parameter in webhooks.php.
	 *
	 * Not every staker has linked Discord, so this is empty as often as not;
	 * every caller has to treat that as normal and post without it.
	 */
	$mention = !empty($u['discord_id']) ? '<@' . $u['discord_id'] . '>' : '';
	return array($author, $name, $mention);
}

/* ------------------------------------------------------------------ *
 * TRAIT DROP
 * ------------------------------------------------------------------ */

/**
 * Announce a drop. $drop is dhcf_award()'s return value.
 *
 * Leads with provenance rather than the tier word: "no minted Fighter wears
 * this" is a far better line than "mythic", and it is the thing that makes a
 * rare drop feel like it came from somewhere real.
 */
function dhcf_notify_drop($conn, $user_id, $drop, $game_key) {
	try {
		if (!function_exists('discordmsg') || empty($drop)) return;

		list($author, $name, $mention) = dhcf_author_block($conn, $user_id);
		$game  = dhcf_game($game_key);
		$label = $game ? $game['label'] : $game_key;

		$worn = (int)$drop['worn'] > 0
			? 'Worn by **' . (int)$drop['worn'] . '** of the 226 minted Fighters'
			: '**No minted Fighter wears this.**';

		$desc = '**' . strtoupper($drop['tier']) . '** · ' . $drop['category'] . "\n"
		      . $worn . "\n"
		      . $drop['rate'] . '% drop chance · ' . $drop['points'] . ' pts'
		      . (empty($drop['is_new']) ? "\n_Duplicate — lets them build a second._" : '');

		discordmsg(
			$name . ' found ' . $drop['name'],
			$desc,
			dhcf_art_base() . '/250/' . $drop['category'] . '/' . $drop['slug'] . '.png',
			'https://skulliance.io/staking/dhcfighters.php',
			'dhcfighters',
			'',                                  // thumbnail: leave the default
			dhcf_tier_color($drop['tier']),
			$author,
			array('text' => 'Dropped from ' . $label),
			$mention
		);
	} catch (Throwable $e) {
		error_log('dhcf_notify_drop: ' . $e->getMessage());
	}
}

/* ------------------------------------------------------------------ *
 * FIGHTER SAVED
 * ------------------------------------------------------------------ */

/**
 * Flatten a saved layout to a PNG so Discord has something to show.
 *
 * Composited server-side with GD from dhcf_layers() -- literally the same
 * stack the Collection renders, not a second implementation of it, so the
 * announcement matches what the player built rather than approximating it.
 *
 * Returns an absolute URL, or '' if anything at all went wrong: no GD on this
 * SAPI, a missing source file, an unwritable directory. Every caller treats ''
 * as "post without a picture", because a Fighter announced as text is far
 * better than a save that failed over a render.
 *
 * $size is the edge of the square canvas. 500 is the default and what Discord,
 * the Arena and the landing thumbnails ask for; dhc-download.php asks for 1000,
 * which is the size of the master art, so a player can post their Fighter
 * somewhere that is not this site. Nothing above 1000 is real detail.
 */
function dhcf_render_fighter($traits, $serial, $size = 500) {
	try {
		if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) return '';
		/* This file is normally reached THROUGH dhcfighters-lib.php, but a
		   couple of endpoints require it directly. Resolved at call time, so
		   there is no load-order loop with lib's own conditional require. */
		if (!function_exists('dhcf_layers')) require_once __DIR__ . '/dhcfighters-lib.php';
		if (!function_exists('dhcf_layers')) return '';
		$size = max(100, min(1000, (int)$size));   // 1000 is the master art; above it is just blur

		$base = '';
		foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
			if (is_dir(__DIR__ . '/' . $c . '/1000')) { $base = $c; break; }
		}
		if ($base === '') return '';

		$dir = __DIR__ . '/dhcrenders';
		if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return '';
		if (!is_writable($dir)) return '';

		/*
		 * ALREADY BUILT? The filename is the serial plus a hash of the exact
		 * traits, so an existing file IS this Fighter -- and an edited one
		 * hashes differently and composes fresh. Rendering anyway was
		 * affordable when the only caller was one Discord post per save; the
		 * public landing page asks for a wall of them at once, where
		 * recomposing 28 thousand-pixel stacks per page view is not.
		 */
		/*
		 * THE 500 IS UNSUFFIXED ON PURPOSE. Every render made before this
		 * function took a size is a 500 sitting in dhcrenders/ under the
		 * plain name; putting the size in every filename would orphan the
		 * whole cache and recompose a thousand-pixel stack per Fighter the
		 * next time Discord or the Arena asked for one.
		 */
		$name = 'f' . (int)$serial . '-' . substr(md5(json_encode($traits)), 0, 8)
		      . ($size === 500 ? '' : '@' . $size) . '.png';
		/* A ZERO-BYTE HIT IS NOT A HIT. Renders used to be written straight
		   to this name, so a write that died partway left a stub that
		   is_file() has been happily serving ever since -- see the note on
		   the write below. Any such leftovers re-compose now instead of
		   being handed to Discord forever. */
		if (is_file($dir . '/' . $name) && @filesize($dir . '/' . $name) > 0) {
			return 'https://skulliance.io/staking/dhcrenders/' . $name;
		}

		$out  = imagecreatetruecolor($size, $size);
		imagealphablending($out, false);
		imagesavealpha($out, true);
		imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
		imagealphablending($out, true);    // now composite onto it

		/*
		 * ONE STACK, BUILT ONCE. This loop used to re-derive the whole layer
		 * stack itself -- the armless torso swap, the single-arm restore, the
		 * nudge, the attached companion's claws and which head refuses them --
		 * alongside dhcf_layers() doing exactly the same thing for the
		 * Collection. Every rule therefore had to be written twice, and the
		 * Demon's five sandbox exceptions landed in one copy before the other,
		 * which is how the Arena lost its effects: twice.
		 *
		 * dhcf_layers() is now the single answer and this function only paints
		 * it. The only translation left is the unit: dhcf_layers() reports the
		 * nudge as a percentage of the canvas, because the still renderer
		 * positions with CSS, while GD wants pixels.
		 */
		$drew = 0;
		foreach (dhcf_layers($traits, __DIR__ . '/' . $base) as $L) {
			$file = __DIR__ . '/' . $base . '/1000/' . $L['cat'] . '/' . $L['slug'] . '.png';
			if (!is_file($file)) continue;
			$layer = @imagecreatefrompng($file);
			if (!$layer) continue;

			$nudge = (int)round($L['nudge'] * $size / 100);

			if ($L['clip'] === '') {
				imagecopyresampled($out, $layer, 0, $nudge, 0, 0, $size, $size,
				                   imagesx($layer), imagesy($layer));
			} else {
				/* The half of a normal torso a single-sided arm does not
				   cover, drawn back over the armless one. The source window
				   has to track the destination window, or the wrong half of
				   the torso lands in the kept side. */
				$cut = (int)round($size * DHCF_ONE_ARM_SPLIT / 100);
				$dx  = $L['clip'] === 'right' ? $cut : 0;
				$w   = $L['clip'] === 'right' ? $size - $cut : $cut;
				$sx  = (int)round(imagesx($layer) * $dx / $size);
				$sw  = (int)round(imagesx($layer) * $w  / $size);
				imagecopyresampled($out, $layer, $dx, $nudge, $sx, 0, $w, $size,
				                   $sw, imagesy($layer));
			}
			$drew++;
		}

		if (!$drew) return '';

		/*
		 * WRITE SOMEWHERE ELSE, THEN MOVE IT. This used to imagepng()
		 * straight onto $name, and the cache check above trusts is_file().
		 * imagepng creates the destination immediately and fills it
		 * progressively, so:
		 *
		 *   - any other request asking for the same Fighter mid-write --
		 *     the gallery wall, the Arena, a second save -- took the early
		 *     return, handed Discord the URL of a TRUNCATED png, and
		 *     Discord cached the failure;
		 *   - and a write that died partway (memory, disk) left a stub that
		 *     satisfies is_file() FOREVER, so that Fighter was broken in
		 *     every embed from then on.
		 *
		 * Which is the shape of the report: sometimes it renders, sometimes
		 * it does not, and it has been like that for a while.
		 *
		 * rename() is atomic within a filesystem, and the temp file is in
		 * the same directory, so a reader sees no file or the finished one
		 * -- never a partial. The pid and a random suffix keep two
		 * simultaneous composes of the same Fighter off each other's temp.
		 */
		$tmp = $dir . '/.' . $name . '.' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
		/* NO imagedestroy(). It has had no effect since PHP 8.0 and emits a
		   deprecation notice from 8.5, and this platform runs with
		   display_errors ON -- a notice from inside a render is printed
		   into whatever page or JSON called it. GD images are freed when
		   the function returns. */
		$ok  = @imagepng($out, $tmp, 6);
		if (!$ok || !@filesize($tmp)) { @unlink($tmp); return ''; }
		if (!@rename($tmp, $dir . '/' . $name)) { @unlink($tmp); return ''; }

		return 'https://skulliance.io/staking/dhcrenders/' . $name;
	} catch (Throwable $e) {
		error_log('dhcf_render_fighter: ' . $e->getMessage());
		return '';
	}
}

/** Announce a saved Fighter, with a flattened render when one can be made. */
function dhcf_notify_fighter($conn, $user_id, $row) {
	try {
		if (!function_exists('discordmsg') || empty($row)) return;

		list($author, $name, $mention) = dhcf_author_block($conn, $user_id);
		$traits = isset($row['traits']) ? $row['traits'] : array();

		// Rarest trait first: it is the reason the score is what it is.
		$parts = array();
		foreach ($traits as $slot => $slug) {
			$info = dhcf_trait_info(dhcf_slot_category($slot), $slug);
			if (!$info) continue;
			$parts[] = array(
				'rate'  => (float)$info[2],
				'line'  => '`' . str_pad($info[0], 9) . '` ' . dhcf_trait_name(dhcf_slot_category($slot), $slug),
			);
		}
		usort($parts, function ($a, $b) { return $a['rate'] <=> $b['rate']; });
		$lines = array();
		foreach (array_slice($parts, 0, 10) as $p) $lines[] = $p['line'];

		$desc = '**' . number_format((int)$row['score']) . ' pts** · '
		      . count($traits) . ' traits' . "\n" . implode("\n", $lines);

		/* discordmsg() uploads this rather than linking it -- the URL is on
		   our own server, so it resolves the file and attaches the bytes.
		   See the note there. */
		$render = dhcf_render_fighter($traits, $row['serial']);

		discordmsg(
			$name . ' assembled ' . $row['display'],
			$desc,
			$render,
			'https://skulliance.io/staking/dhcfighters.php',
			'dhcfighters',
			'',
			'00C8A0',
			$author,
			array('text' => 'DHC Fighters — not an NFT, cannot be minted'),
			$mention
		);
	} catch (Throwable $e) {
		error_log('dhcf_notify_fighter: ' . $e->getMessage());
	}
}

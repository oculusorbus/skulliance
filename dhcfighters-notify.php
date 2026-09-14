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

/** Absolute base for trait art. Discord fetches these itself, so relative fails. */
function dhcf_art_base() {
	static $b = null;
	if ($b === null) {
		$b = '';
		foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
			if (is_dir(__DIR__ . '/' . $c . '/1000')) { $b = $c; break; }
		}
	}
	return 'https://skulliance.io/staking/' . $b;
}

/** Author block for an embed: display name plus their Discord avatar. */
function dhcf_author_block($conn, $user_id) {
	$res = $conn->query(sprintf(
		"SELECT username, discord_id, avatar FROM users WHERE id = %d LIMIT 1", (int)$user_id));
	if (!$res || !$res->num_rows) return array(null, 'a player');
	$u = $res->fetch_assoc();
	$name = $u['username'] !== '' ? $u['username'] : 'a player';
	$author = array('name' => $name);
	if (!empty($u['discord_id']) && !empty($u['avatar'])) {
		$author['icon_url'] = 'https://cdn.discordapp.com/avatars/'
		                    . $u['discord_id'] . '/' . $u['avatar'] . '.jpg';
	}
	return array($author, $name);
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

		list($author, $name) = dhcf_author_block($conn, $user_id);
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
			array('text' => 'Dropped from ' . $label)
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
 * Composited server-side with GD, using dhcf_layer_order() -- the same rules
 * the assembler draws with, so the announcement matches what the player built
 * rather than approximating it.
 *
 * Returns an absolute URL, or '' if anything at all went wrong: no GD on this
 * SAPI, a missing source file, an unwritable directory. Every caller treats ''
 * as "post without a picture", because a Fighter announced as text is far
 * better than a save that failed over a render.
 */
function dhcf_render_fighter($traits, $serial) {
	try {
		if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) return '';

		$base = '';
		foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
			if (is_dir(__DIR__ . '/' . $c . '/1000')) { $base = $c; break; }
		}
		if ($base === '') return '';

		$dir = __DIR__ . '/dhcrenders';
		if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return '';
		if (!is_writable($dir)) return '';

		$size = 500;                       // plenty for a Discord embed, quick to build
		$out  = imagecreatetruecolor($size, $size);
		imagealphablending($out, false);
		imagesavealpha($out, true);
		imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
		imagealphablending($out, true);    // now composite onto it

		$drew = 0;
		foreach (dhcf_layer_order($traits) as $slot) {
			if (empty($traits[$slot])) continue;
			$cat  = dhcf_slot_category($slot);
			$slug = $traits[$slot];

			// Armless torso variant, exactly as the assembler chooses it
			$catDir = $cat;
			if ($slot === 'torso' && !empty($traits['arms'])
			    && is_file(__DIR__ . '/' . $base . '/1000/torso-noarms/' . $slug . '.png')) {
				$catDir = 'torso-noarms';
			}

			$file = __DIR__ . '/' . $base . '/1000/' . $catDir . '/' . $slug . '.png';
			if (!is_file($file)) continue;
			$layer = @imagecreatefrompng($file);
			if (!$layer) continue;

			// Assembly nudge, scaled from the 1000px master to this canvas
			$nudge = isset(DHCF_NUDGE[$slug]) ? (int)round(DHCF_NUDGE[$slug] * $size / 1000) : 0;

			imagecopyresampled($out, $layer, 0, $nudge, 0, 0, $size, $size,
			                   imagesx($layer), imagesy($layer));
			$drew++;
		}
		if (!$drew) return '';

		$name = 'f' . (int)$serial . '-' . substr(md5(json_encode($traits)), 0, 8) . '.png';
		$ok   = @imagepng($out, $dir . '/' . $name, 6);
		if (!$ok) return '';

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

		list($author, $name) = dhcf_author_block($conn, $user_id);
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

		discordmsg(
			$name . ' assembled ' . $row['display'],
			$desc,
			dhcf_render_fighter($traits, $row['serial']),
			'https://skulliance.io/staking/dhcfighters.php',
			'dhcfighters',
			'',
			'00C8A0',
			$author,
			array('text' => 'DHC Fighters — not an NFT, cannot be minted')
		);
	} catch (Throwable $e) {
		error_log('dhcf_notify_fighter: ' . $e->getMessage());
	}
}

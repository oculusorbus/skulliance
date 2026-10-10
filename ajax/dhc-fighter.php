<?php
/**
 * ajax/dhc-fighter.php?serial=N
 *
 * One Fighter, in exactly the shape dhc-fighter-modal.php wants -- the same
 * object dhcgallery.php puts in each card's data-f attribute.
 *
 * WHY IT EXISTS. The assembler opens that panel the moment you save, so the
 * Fighter you just built can be looked at, downloaded and shared without
 * going to find it. The save endpoint returns a name, a score and a serial;
 * the panel needs the layer stack, every trait's metadata, the Arena's own
 * numbers and the four ranks. Widening dhc-save-fighter.php's reply to carry
 * all of that would put the Collection's presentation inside a write
 * endpoint -- and a write endpoint that fails because a rank pool could not
 * be read is a Fighter that saved and said it did not.
 *
 * Public, like the Collection it mirrors. It returns nothing that page does
 * not already render for every visitor; `mine` is what decides whether
 * Download and Share are offered, and dhc-download.php re-checks ownership
 * in its own WHERE clause regardless.
 */
include '../db.php';
require_once __DIR__ . '/../dhcfighters-lib.php';
require_once __DIR__ . '/../dhc-ranks.php';
require_once __DIR__ . '/../dhcarena-engine.php';
require_once __DIR__ . '/../dhc-json.php';

$serial = isset($_GET['serial']) ? (int)$_GET['serial'] : 0;
if ($serial <= 0) dhc_json(array('ok' => false, 'message' => 'No serial.'));

/* $conn->query(), not a prepared statement: this server's mysqli has no
   mysqlnd, so get_result() is a fatal. $serial is cast above. */
/* Same LEFT JOIN as dhcgallery.php -- the deep-link endpoint and the page
   feed the SAME modal, so a field present in one and missing from the other
   is a panel that shows the record only when you scrolled to it. */
$res = $conn->query("SELECT f.*, u.username, u.discord_id, u.avatar,
                            COALESCE(af.wins, 0) AS arena_wins,
                            COALESCE(af.losses, 0) AS arena_losses
                     FROM dhc_fighters f
                     LEFT JOIN users u ON u.id = f.user_id
                     LEFT JOIN dhc_arena_fighters af ON af.fighter_id = f.id
                     WHERE f.serial = $serial AND f.disassembled_at IS NULL LIMIT 1");
$row = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
if (!$row) dhc_json(array('ok' => false, 'message' => 'No such Fighter.'));

$row['traits']  = json_decode($row['traits'], true) ?: array();
$row['display'] = dhcf_display_name($row);

/* Every trait's metadata, rarest first -- the same order and the same fields
   the Collection builds, because the panel reads them by name. */
$rarity = dhcf_rarity();
$parts  = array();
foreach ($row['traits'] as $slot => $slug) {
	$cat  = dhcf_slot_category($slot);
	$info = dhcf_trait_info($cat, $slug);
	if (!$info) continue;
	$parts[] = array(
		'slot' => $slot, 'cat' => $cat, 'slug' => $slug,
		'name' => dhcf_trait_name($cat, $slug),
		'tier' => $info[0], 'worn' => (int)$info[1], 'rate' => (float)$info[2],
		'pts'  => dhcf_trait_points($info[2]),
	);
}
usort($parts, function ($a, $b) { return $a['rate'] <=> $b['rate']; });

$built = dhca_build_fighter($row['traits'], '', 'a' . $row['id'], $rarity);
$rv    = dhcf_rank_values($row['traits'], (int)$row['rarity_score'], $built);
$pool  = dhcf_rank_pool($conn);
$rank  = array();
foreach (dhcf_rank_axes() as $k => $label) $rank[$k] = dhcf_rank_of($pool[$k], $rv[$k]);

$layers = array();
foreach (dhcf_layers($row['traits'], __DIR__ . '/../' . dhcf_art_dir()) as $L) {
	$layers[] = array('c' => $L['cat'], 's' => $L['slug'], 'n' => $L['nudge'], 'k' => $L['clip']);
}

/* Same figure the Collection shows: every saved Fighter this builder has,
   not whatever a filtered page happened to list. */
$ownerN = 0;
$cq = $conn->query("SELECT COUNT(*) AS n FROM dhc_fighters
                    WHERE user_id = " . (int)$row['user_id'] . " AND disassembled_at IS NULL");
if ($cq && ($cr = $cq->fetch_assoc())) $ownerN = (int)$cr['n'];

$avatar = (!empty($row['discord_id']) && !empty($row['avatar']))
        ? 'https://cdn.discordapp.com/avatars/' . $row['discord_id'] . '/' . $row['avatar'] . '.jpg'
        : 'icons/skulliance.png';

$out = array(
	'name'    => $row['display'],
	'serial'  => (int)$row['serial'],
	'owner'   => $row['username'],
	'ownerId' => (int)$row['user_id'],
	'avatar'  => $avatar,
	'ownerN'  => $ownerN,
	'score'   => (int)$row['rarity_score'],
	'hp'      => (int)$rv['tough'],
	'pow'     => (int)$rv['power'],
	'crit'    => (int)round($built['critC'] * 100),
	'resist'  => (int)round($built['resist'] * 100),
	'assist'  => (int)round($built['assist'] * 100),
	'charge'  => round($built['charge'], 2),
	'roles'   => isset($built['roles']) ? $built['roles'] : array(),
	'kit'     => $built['kit']['emoji'] . ' ' . $built['kit']['name'] . ' — ' . $built['kit']['note'],
	'created' => $row['created_at'],
	'aw'      => (int)$row['arena_wins'],
	'al'      => (int)$row['arena_losses'],
	'parts'   => $parts,
	'rank'    => $rank,
	'rankOf'  => (int)$pool['_n'],
	'layers'  => $layers,
);
$conn->close();
dhc_json(array('ok' => true, 'fighter' => $out));

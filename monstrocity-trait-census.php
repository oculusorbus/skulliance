<?php
/**
 * monstrocity-trait-census.php — what a collection's traits are, and how rare. CLI only.
 *
 *   php monstrocity-trait-census.php <policy> <out.json>
 *
 * WHY THE WHOLE COLLECTION AND NOT THE STAKED ROWS. Stats derived from
 * traits have to mean something for an NFT that has never touched the
 * platform -- the point of the exercise is that a collector who has not
 * staked yet can look up what their Ancestor would be. Counting only
 * nfts.metadata would measure whoever happens to be staked this week: 886
 * of 1,225 Ancestors today, so every rarity figure would be wrong, and
 * wrong by a different amount every night.
 *
 * So the census is taken from the chain, once, and committed. It changes
 * only if the collection does, which for a minted CIP-25 policy is never.
 *
 * READ THROUGH THE PLATFORM'S OWN PARSER. nft_metadata_from_koios() and
 * nft_metadata_traits() are the same pair the nightly crawl and every
 * reader use, so a trait named here is a trait the game will actually see
 * in nfts.metadata. Re-deriving the shape with a second walk is how the two
 * come to disagree about what an NFT has.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/lib/nft-metadata-lib.php';

$policy = isset($argv[1]) ? trim($argv[1]) : '';
$out    = isset($argv[2]) ? $argv[2] : '';
if (!preg_match('/^[0-9a-f]{56}$/', $policy) || $out === '') {
	fwrite(STDERR, "usage: php monstrocity-trait-census.php <56-hex policy> <out.json>\n");
	exit(2);
}

/* The token the Monstrocity endpoint already uses. Koios is read-only and
   the collection is public; nothing of the player's goes over this. */
$src = @file_get_contents(__DIR__ . '/ajax/get-monstrocity-assets.php');
if (!preg_match('/\$koios_bearer_token\s*=\s*\'([^\']+)\'/', (string)$src, $m)) {
	fwrite(STDERR, "could not find the Koios token in ajax/get-monstrocity-assets.php\n");
	exit(2);
}
$hdr = array('Content-Type: application/json', 'Authorization: Bearer ' . $m[1]);

function mtc_get($url, $hdr, $post = null) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
	}
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($code !== 200) { fwrite(STDERR, "\nHTTP $code\n"); return null; }
	return json_decode($body);
}

/* 1. every asset name under the policy. Paged: Koios caps a page at 1000
      and the Ancestors policy holds 1,226 including its reference token. */
$names = array(); $off = 0;
while (true) {
	$page = mtc_get("https://api.koios.rest/api/v1/policy_asset_list?_asset_policy=$policy"
	              . "&offset=$off&limit=1000", $hdr);
	if (!is_array($page) || !$page) break;
	/* The empty asset_name is the policy's own reference/royalty token, not
	   a piece of art, and it carries no traits. */
	foreach ($page as $a) if (isset($a->asset_name) && $a->asset_name !== '') $names[] = $a->asset_name;
	if (count($page) < 1000) break;
	$off += 1000;
}
if (!$names) { fwrite(STDERR, "no assets under that policy\n"); exit(1); }
fwrite(STDERR, count($names) . " assets\n");

/* 2. their metadata, 35 at a time -- the same batch size the Monstrocity
      endpoint uses against the same API, paced so a census never looks
      like an attack on a service the platform depends on. */
$slots = array(); $covered = array(); $seen = 0; $loads = array();
foreach (array_chunk($names, 35) as $chunk) {
	$list = array();
	foreach ($chunk as $n) $list[] = array($policy, $n);
	$rows = mtc_get('https://api.koios.rest/api/v1/asset_info', $hdr, array('_asset_list' => $list));
	if (!is_array($rows)) { fwrite(STDERR, "a batch failed; the census would be wrong -- stopping\n"); exit(1); }
	foreach ($rows as $r) {
		$traits = nft_metadata_traits(nft_metadata_from_koios($r));
		$seen++;
		$loads[] = count($traits);
		foreach ($traits as $k => $v) {
			$k = (string)$k; $v = (string)$v;
			if (!isset($slots[$k])) { $slots[$k] = array(); $covered[$k] = 0; }
			if (!isset($slots[$k][$v])) $slots[$k][$v] = 0;
			$slots[$k][$v]++;
			$covered[$k]++;
		}
	}
	fwrite(STDERR, "\r$seen/" . count($names));
	usleep(120000);
}
fwrite(STDERR, "\n");

foreach ($slots as $k => $vals) { arsort($vals); $slots[$k] = $vals; }
ksort($slots);

$doc = array(
	'policy'    => $policy,
	'assets'    => $seen,
	'taken'     => gmdate('Y-m-d'),
	'loadMin'   => $loads ? min($loads) : 0,
	'loadMax'   => $loads ? max($loads) : 0,
	'slots'     => array(),
);
foreach ($slots as $k => $vals)
	$doc['slots'][$k] = array('coverage' => $covered[$k], 'values' => $vals);

file_put_contents($out, json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
printf("%d assets, %d trait slots, %d distinct values -> %s\n",
	$seen, count($slots), array_sum(array_map('count', $slots)), $out);

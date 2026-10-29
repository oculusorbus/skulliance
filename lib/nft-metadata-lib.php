<?php
/**
 * lib/nft-metadata-lib.php — the on-chain document, kept instead of thrown away.
 *
 * WHAT THIS CHANGES. Every chain's verifier already fetches an asset's full
 * metadata and then keeps two fields out of it:
 *
 *   cardano CIP-25  verify.php, $nft_data        -> name, image
 *   cardano CIP-68  verify.php, $traits          -> name, image
 *   xrpl            xrpl_parse_metadata(), $meta -> name, image, collection
 *   solana          sol_parse_metadata(), $meta  -> name, image
 *   polygon         poly_parse_metadata(), $j    -> name, image
 *
 * Everything else -- every trait of every NFT on the platform -- was dropped
 * on the floor. So this adds no request to any API and no time to any crawl.
 * It deletes a discard.
 *
 * THE RAW DOCUMENT IS WHAT IS STORED. Four chains have four shapes and the
 * fifth will have a fifth, so a parsed-traits schema designed today is wrong
 * by the next integration. Readers use nft_metadata_traits() below.
 *
 * WRITTEN BY asset_id, NOT BY ROW ID, which is what makes RFTs work: one
 * asset can own several `nfts` rows (checkAvailableNFT() reuses an unowned
 * one first, and creates a second only when every existing row is held), and
 * one UPDATE keeps all of them saying the same thing. CIP-68 metadata is
 * mutable on chain, so "all of them" matters.
 */

if (!function_exists('nft_metadata_store')) {

/**
 * Keep an asset's document. Returns true when something was written.
 *
 * SILENT ON AN EMPTY DOCUMENT, and that is the point: a crawl that could not
 * read the metadata must leave metadata_at NULL so the backfill still owes
 * the row. Writing "{}" with a timestamp would mark it done forever.
 */
function nft_metadata_store($conn, $asset_id, $doc) {
	$asset_id = (string)$asset_id;
	if ($asset_id === '') return false;
	if (!is_array($doc) || !$doc) return false;

	$json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	if (!is_string($json) || $json === '' || $json === '{}' || $json === '[]') return false;
	/*
	 * A DOCUMENT NOBODY SANE MINTED IS REFUSED RATHER THAN TRUNCATED. MySQL
	 * silently cuts an over-long value in a non-strict mode, and a truncated
	 * JSON document is unparseable -- worse than no document, because
	 * metadata_at would say it had been read.
	 */
	if (strlen($json) > 65000) return false;

	/*
	 * A METADATA WRITE MUST NEVER COST A CRAWL ITS NIGHT.
	 *
	 * This is strictly additive: nothing downstream needs it, and a staker
	 * whose NFTs did not get verified because a trait could not be stored
	 * would be a bad trade for a column that exists to be convenient. So an
	 * unusable handle is refused rather than called -- a connection object
	 * that cannot escape is a fatal inside a nightly worker, which is how
	 * the Polygon harness found this.
	 */
	if (!is_object($conn)
	    || !method_exists($conn, 'query') || !method_exists($conn, 'real_escape_string'))
		return false;

	$ok = @$conn->query(sprintf(
		"UPDATE nfts SET metadata = '%s', metadata_at = NOW() WHERE asset_id = '%s'",
		$conn->real_escape_string($json), $conn->real_escape_string($asset_id)));
	return (bool)$ok;
}

/**
 * The CIP-25 / CIP-68 document for one asset out of a Koios asset_info row.
 *
 * TWO SHAPES, ONE FUNCTION, because the caller should not have to know which
 * standard an asset was minted under -- verify.php already branches on that
 * for the name and image and the branching is the part that rots.
 *
 * CIP-25 nests the object under policy id then asset name, which is why the
 * walk looks the way it does. CIP-68 arrives as an already-decoded key/value
 * map on the caller's side, so it is passed straight through.
 */
function nft_metadata_from_koios($row) {
	if (!is_object($row)) return array();

	/* CIP-25: minting_tx_metadata -> 721 -> policy -> asset name */
	if (isset($row->minting_tx_metadata) && isset($row->policy_id)) {
		$policy = $row->policy_id;
		$name   = isset($row->asset_name_ascii) ? $row->asset_name_ascii : null;
		foreach ((array)$row->minting_tx_metadata as $label) {
			if (!is_object($label) || !isset($label->$policy)) continue;
			$byName = $label->$policy;
			if ($name !== null && isset($byName->$name))
				return nft_metadata_clean((array)$byName->$name);
		}
	}

	/*
	 * CIP-68: the datum, as Koios hands it over -- a nested CBOR map that
	 * arrives as alternating key and value byte strings.
	 *
	 * LIFTED OUT OF verify.php RATHER THAN CALLED FROM IT. The walk was
	 * inline in the CIP-68 branch, built a complete $traits map and then
	 * read two keys out of it. Here it answers the same question the CIP-25
	 * branch above answers, so a caller does not need to know which standard
	 * an asset was minted under -- and the nightly crawl can refresh the
	 * document for EVERY asset rather than only the ones it is creating.
	 */
	if (isset($row->cip68_metadata)) {
		$traits = array();
		$alternate = 'key'; $key = ''; $value = '';
		foreach ((array)$row->cip68_metadata as $metadata) {
			foreach ((array)$metadata as $fields) {
				if (!is_array($fields)) continue;
				foreach ($fields as $maps) {
					foreach ((array)$maps as $map) {
						if (!is_array($map)) continue;
						foreach ($map as $pairings) {
							foreach ((array)$pairings as $pairing) {
								if (!isset($pairing->bytes)) continue;
								if ($alternate === 'key') { $key = nft_metadata_hex($pairing->bytes); $alternate = 'value'; }
								else                      { $value = nft_metadata_hex($pairing->bytes); $alternate = 'key'; }
							}
							if ($key !== '' && $value !== '') $traits[$key] = $value;
						}
					}
				}
			}
		}
		if ($traits) return nft_metadata_clean($traits);
	}
	return array();
}

/** Hex to bytes. Local so the library stands alone -- verify.php's hex2str()
    is defined in a file that includes db.php, and the harness loads neither. */
function nft_metadata_hex($hex) {
	$hex = (string)$hex;
	$out = '';
	for ($i = 0; $i + 1 < strlen($hex); $i += 2) $out .= chr(hexdec(substr($hex, $i, 2)));
	return $out;
}

/**
 * Trim a document to the part worth keeping.
 *
 * THE PICTURE IS ALREADY A COLUMN. `image`, `files` and `mediaType` are what
 * nfts.ipfs was derived from and re-storing them doubles the row for nothing
 * -- 551 bytes becomes 203 for a typical Ancestor. The project's own social
 * links are the same on every one of its assets and belong nowhere near a
 * per-asset record.
 *
 * Nothing else is dropped. A trait this platform has never seen is kept,
 * because the whole reason for the column is the question nobody has asked
 * yet.
 */
function nft_metadata_clean($doc) {
	$drop = array('image', 'files', 'mediaType', 'Discord', 'Twitter', 'twitter', 'discord',
	              'website', 'Website');
	$out = array();
	foreach ($doc as $k => $v) {
		if (in_array((string)$k, $drop, true)) continue;
		if (is_scalar($v) || $v === null) { $out[$k] = $v; continue; }
		if (!is_array($v) && !is_object($v)) continue;
		/*
		 * TWO LEVELS, NOT ONE, AND THAT IS NOT GENEROSITY.
		 *
		 * Metaplex and every EVM collection write traits as
		 * attributes[{trait_type, value}] -- a list of OBJECTS. A one-level
		 * rule flattens scalars and silently drops anything deeper, which
		 * threw away the entire trait set on two of the four chains while
		 * looking perfectly reasonable. The Solana harness caught it.
		 *
		 * Still bounded: the inner level keeps scalars only, so a
		 * provenance blob or a nested file manifest does not come through.
		 */
		$flat = array();
		foreach ((array)$v as $vk => $vv) {
			if (is_scalar($vv) || $vv === null) { $flat[$vk] = $vv; continue; }
			if (!is_array($vv) && !is_object($vv)) continue;
			$inner = array();
			foreach ((array)$vv as $ik => $iv) if (is_scalar($iv)) $inner[$ik] = $iv;
			if ($inner) $flat[$vk] = $inner;
		}
		if ($flat) $out[$k] = $flat;
	}
	return $out;
}

/**
 * The trait pairs a reader wants, out of whatever was stored.
 *
 * ONE HOME FOR THE QUESTION "what are this NFT's traits", because the answer
 * differs per chain and every surface that asks -- a Monstrocity stat map, a
 * collection view, a rarity count -- would otherwise re-derive it slightly
 * differently. Returns an ordered name => value map of scalars only.
 */
function nft_metadata_traits($json) {
	$doc = is_array($json) ? $json : json_decode((string)$json, true);
	if (!is_array($doc)) return array();

	/*
	 * FOUR SHAPES, BECAUSE FOUR SHAPES EXIST IN THE WILD.
	 *
	 *   attributes: [{trait_type, value}]   Metaplex, most EVM collections
	 *   attributes: {K: V}                  the same idea, as a map
	 *   traits:     [{K: V}, {K: V}]        a LIST OF SINGLE-KEY OBJECTS
	 *   properties: [{K: V}, {K: V}]        the same, under another name
	 *   flat keys                           CIP-25, XLS-24
	 *
	 * `properties` turned up the same way `traits` did -- Havoc Worlds, all
	 * 9,999 of them, censused as having no traits whatsoever. There is no
	 * standard here, only names people chose.
	 *
	 * The third one is why this function was rewritten. Wavy Ape Vibe
	 * Empire writes it, 4,444 assets of it, and this reader returned an
	 * empty array for every single one -- no error, no warning, just a
	 * collection that looked like it had no traits. The documents were
	 * stored correctly all along; only the reading was wrong, which is the
	 * worst way for it to be wrong because nothing anywhere says so.
	 */
	foreach (array('attributes', 'traits', 'properties') as $container) {
		if (!isset($doc[$container]) || !is_array($doc[$container])) continue;
		$out = array();
		foreach ($doc[$container] as $ck => $a) {
			/* attributes: {K: V} -- the container is itself the map. */
			if (is_scalar($a)) { if (!nft_metadata_is_uri($a)) $out[(string)$ck] = $a; continue; }
			if (!is_array($a)) continue;
			/* {trait_type, value} or {name, value}. */
			$k = isset($a['trait_type']) ? $a['trait_type']
			   : (isset($a['name']) ? $a['name'] : null);
			if ($k !== null && isset($a['value']) && is_scalar($a['value'])) {
				if (!nft_metadata_is_uri($a['value'])) $out[(string)$k] = $a['value'];
				continue;
			}
			/*
			 * A SINGLE-KEY OBJECT. Only taken when it does NOT look like a
			 * {trait_type, value} pair, so the branch above keeps priority
			 * and a malformed Metaplex row does not get read as a trait
			 * called "trait_type".
			 */
			foreach ($a as $ik => $iv)
				if (is_scalar($iv) && !nft_metadata_is_uri($iv)
				    && !in_array((string)$ik, array('trait_type', 'value', 'name'), true))
					$out[(string)$ik] = $iv;
		}
		if ($out) return $out;
	}

	$skip = array('name', 'description', 'project', 'collection', 'attributes', 'traits',
	              'properties', 'publisher', 'artist', 'creator');
	$out = array();
	foreach ($doc as $k => $v)
		if (is_scalar($v) && !in_array((string)$k, $skip, true) && !nft_metadata_is_uri($v))
			$out[(string)$k] = $v;
	return $out;
}

/**
 * Is this value a link rather than a trait?
 *
 * BY VALUE, NOT BY KEY, and that is the whole point. nft_metadata_clean()
 * drops `image`, `files` and `mediaType` by name, which catches the keys
 * this platform has seen -- and a collection that calls it `Image`, `art`,
 * `hi_res` or `animation` sails straight through and shows up in a trait
 * list as "Image: ipfs://bafy...". 96 stored documents carry a leaked
 * ipfs:// under some key or other. A URI is never a trait whatever it is
 * called.
 */
function nft_metadata_is_uri($v) {
	/* data: carries no slashes, which the one-pattern version missed. */
	return is_string($v) && preg_match('~^\s*(?:(?:ipfs|https?|ar)://|data:)~i', $v) === 1;
}

}

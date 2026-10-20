<?php
/**
 * KOIOS REQUEST HEADERS, in one place, with an expired token left out.
 *
 * AN EXPIRED TOKEN IS WORSE THAN NO TOKEN. Koios answers an expired
 * subscription with HTTP 403 and a plain-text body, where an
 * unauthenticated request to the same endpoint answers 200. The caller then
 * does json_decode() on that sentence, gets null, fails is_array(), and
 * skips the whole block -- no error, no log, just no NFTs.
 *
 * That is exactly what dropship/dashboard.php did for about twenty-two
 * months: it carried a token that expired 2024-12-20, kept from before the
 * migration, while the rest of the platform had already moved to a current
 * one. It is why no Drop Ship reskin on the Koios path ever loaded a
 * soldier. Skulliance's own calls were never affected -- db.php, verify.php
 * and the rarity reports all used the good token.
 *
 * Measured, not assumed:
 *   with the expired bearer -> 403 "Subscription expired, Please renew..."
 *   with no bearer          -> 200
 *
 * SO THE TOKEN IS ONLY SENT WHILE IT IS VALID. Renewing it at
 * https://koios.rest/Profile.html and pasting it below turns the paid tier
 * back on with no other change; leaving it expired falls back to the free
 * tier, which works, rather than to nothing, which is what happened.
 */

/** The current Koios bearer. Replace the whole string when renewing. */
function koios_token() {
	return 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg';
}

/**
 * Seconds left on the token: positive while valid, <= 0 once expired, and
 * null when the expiry cannot be read at all (which is treated as expired,
 * because sending a token we cannot vouch for is the exact failure above).
 */
function koios_token_expires_in($token = null) {
	$t = $token === null ? koios_token() : $token;
	$parts = explode('.', $t);
	if (count($parts) < 2) return null;
	$pad = strlen($parts[1]) % 4;
	$b64 = strtr($parts[1], '-_', '+/') . ($pad ? str_repeat('=', 4 - $pad) : '');
	$claims = json_decode((string)base64_decode($b64), true);
	if (!is_array($claims) || !isset($claims['exp'])) return null;
	return (int)$claims['exp'] - time();
}

/**
 * Headers for a Koios call. Pass any extras the endpoint wants.
 *
 * Always asks for JSON; adds the bearer only while it is good for it.
 */
function koios_headers($extra = array()) {
	$h = array('Content-type: application/json', 'accept: application/json');
	foreach ((array)$extra as $x) $h[] = $x;
	$left = koios_token_expires_in();
	if ($left !== null && $left > 0) $h[] = 'authorization: Bearer ' . koios_token();
	return $h;
}

/**
 * Is this a stake address Koios will accept?
 *
 * ONE BAD ADDRESS POISONS THE WHOLE BATCH. account_utxos takes every
 * connected wallet in a single call, and Koios answers the entire request
 * with HTTP 500 -- "Failed to decode Bech32 string" -- if any one entry is
 * malformed. So a player with ten wallets and one bad row gets nothing from
 * any of them, and the 500 body is not JSON, so the caller's is_array()
 * check drops it silently. Measured: ten valid addresses 200, nine valid
 * plus one malformed 500, nine valid plus one empty string 500.
 *
 * Before the wallets were batched into one call this cost that wallet only;
 * batching is what made it cost all of them.
 *
 * Checks the shape, not the checksum: prefix, Bech32's own alphabet (no
 * 1, b, i or o) and a plausible length. That is enough to keep a malformed
 * row out of the request, which is the whole job -- a well-formed address
 * that simply holds nothing is answered normally by Koios.
 */
function koios_valid_stake_address($addr) {
	$a = trim((string)$addr);
	if ($a === '') return false;
	return (bool)preg_match('/^stake(_test)?1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{50,70}$/', $a);
}

/** Keep only the addresses Koios can read. Values, re-indexed for json_encode. */
function koios_filter_stake_addresses($addresses) {
	$ok = array();
	foreach ((array)$addresses as $a) if (koios_valid_stake_address($a)) $ok[] = trim((string)$a);
	return array_values(array_unique($ok));
}

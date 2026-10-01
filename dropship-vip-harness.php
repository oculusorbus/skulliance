<?php
/* dropship-vip-harness.php — CLI only. No database, no Discord.
 *
 * The Oculus Lounge reskin (dropship_project_id 4) gates its Play button on
 * $vip, and the question that started this was whether that gate reads the
 * CHAIN or Discord. It is Discord, entirely: the Oculus Lounge policy id
 * appears in the codebase only inside two marketplace hyperlinks, and $vip
 * is set from one role id in $_SESSION['userData']['roles'].
 *
 * Which makes the source of that array the whole story, and it is a
 * SNAPSHOT: process-oauth.php fills it once at login from four sequential
 * Discord calls whose failures are silently ignored. A role granted after
 * you signed in does not exist to this page.
 *
 * That is the paid path, not an edge case -- buying a pass assigns the role
 * and sets $_SESSION['userData']['VIP'], and the gate used to read neither.
 *
 * Usage: php dropship-vip-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$ds   = file_get_contents(__DIR__ . '/dropship/dropship.php');
$dash = file_get_contents(__DIR__ . '/dropship/dashboard.php');
$role = file_get_contents(__DIR__ . '/dropship/role.php');
$oauth= file_get_contents(__DIR__ . '/process-oauth.php');

echo "the VIP gate is Discord, not the chain\n";
$policy = '3d250a78df7ad14e9472d9b63159ef2d099740c593c0ba53059f144a';
$codeHits = 0;
foreach (glob(__DIR__ . '/dropship/*.php') as $f) {
	foreach (file($f) as $line) {
		if (strpos($line, $policy) === false) continue;
		/* A marketplace hyperlink is not an ownership check. */
		if (stripos($line, 'wayup.io') !== false) continue;
		$codeHits++;
	}
}
printf("  the Oculus Lounge policy appears in %d place(s) that are not a marketplace link\n", $codeHits);
ok($codeHits === 0,
   'something now queries the Oculus Lounge policy; if an on-chain check has '
 . 'been added, this harness and the docs need to say so');
ok(preg_match('/case "966399108011163678":\s*\$vip = "true";/', $ds) === 1,
   'the VIP role id the gate keys on has changed');
ok(strpos($role, '$guildid = "966397496978964500"') !== false,
   'assignRole no longer targets the Oculus Lounge guild, so the role the '
 . 'gate wants and the role a purchase grants are in different servers');
ok(strpos($oauth, '966397496978964500') !== false,
   'the Oculus Lounge guild is no longer scanned at login, so its roles will '
 . 'never reach the session at all');

echo "\na pass you paid for works in the session you paid in\n";
ok(preg_match("/if \(\\\$vip !== 'true' && !empty\(\\\$_SESSION\['userData'\]\['VIP'\]\)\) \{\s*\\\$vip = 'true';/", $ds) === 1,
   'the gate ignores $_SESSION[userData][VIP] again -- buying a DISCOIN pass '
 . 'assigns the role and sets that flag, but the roles SNAPSHOT predates the '
 . 'purchase, so the player is refused until they log out and back in');
ok(strpos(file_get_contents(__DIR__ . '/dropship/ajax/transaction.php'),
          "\$_SESSION['userData']['VIP'] = 1;") !== false,
   'the purchase no longer sets the session flag the gate now reads');
/* It must only ever GRANT. */
ok(preg_match("/\\\$vip !== 'true' &&/", $ds) === 1,
   'the session flag is being consulted unconditionally rather than only as '
 . 'a fallback, so a false value could take access away from someone the '
 . 'role snapshot already approved');

/* And the gate itself still keys on $vip. */
ok(preg_match('/dropship_project_id\'\] == 4 && \$vip == "true"/', $dash) === 1,
   'the Play gate no longer reads $vip, so none of the above reaches it');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all dropship VIP checks passed\n";

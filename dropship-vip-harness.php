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
ok(strpos($role, "define('DROPSHIP_GUILD_ID', '966397496978964500')") !== false
   && strpos($role, '$guildid = DROPSHIP_GUILD_ID;') !== false,
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

echo "\nthe live recheck, driven for real\n";
/*
 * A function whose whole job is one HTTP call is otherwise only checkable
 * by reading it, and reading is exactly how a multipart bug got past me
 * earlier today. DROPSHIP_DISCORD_API exists so this can be pointed at a
 * local server; production never defines it.
 *
 * The cases that matter are the ones where Discord does NOT answer
 * cleanly, because the rule is that those may never cost a player access.
 */
$port = 8793;
$root = __DIR__ . '/tmp-viptest-' . getmypid();
@mkdir($root . '/guilds/966397496978964500/members', 0775, true);
$m = $root . '/guilds/966397496978964500/members';
file_put_contents($m . '/has.php',    '<?php header("Content-Type: application/json"); echo json_encode(array("roles" => array("111", "966399108011163678", "222")));');
file_put_contents($m . '/hasnt.php',  '<?php header("Content-Type: application/json"); echo json_encode(array("roles" => array("111", "222")));');
file_put_contents($m . '/gone.php',   '<?php http_response_code(404); echo json_encode(array("message" => "Unknown Member"));');
file_put_contents($m . '/limited.php','<?php http_response_code(429); echo json_encode(array("message" => "You are being rate limited."));');
file_put_contents($m . '/junk.php',   '<?php header("Content-Type: application/json"); echo "<html>not json</html>";');

$srv = proc_open('php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($root),
    array(0 => array('file', '/dev/null', 'r'),
          1 => array('file', '/dev/null', 'w'),
          2 => array('file', '/dev/null', 'w')), $pipes);
for ($i = 0; $i < 40; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}

define('DROPSHIP_DISCORD_API', 'http://127.0.0.1:' . $port);
define('DROPSHIP_GUILD_ID', '966397496978964500');
$bot_token = 'test-token';
/* the REAL function, lifted out of role.php */
$at = strpos($role, 'function dropshipMemberHasRole');
$i = strpos($role, '{', $at); $d = 0;
for ($j = $i; $j < strlen($role); $j++) {
    if ($role[$j] === '{') $d++; elseif ($role[$j] === '}') { $d--; if (!$d) break; }
}
eval(substr($role, $at, $j - $at + 1));

$VIPROLE = '966399108011163678';
ok(dropshipMemberHasRole('has.php', $VIPROLE) === true,
   'a member who HOLDS the role is not recognised');
ok(dropshipMemberHasRole('hasnt.php', $VIPROLE) === false,
   'a member who does not hold the role is not reported as false');
ok(dropshipMemberHasRole('gone.php', $VIPROLE) === false,
   'a 404 (not in the guild) should be a definite false, not an unknown');
ok(dropshipMemberHasRole('limited.php', $VIPROLE) === null,
   'a rate limit reads as a definite NO -- it must be null, or a Discord '
 . 'hiccup would start denying access');
ok(dropshipMemberHasRole('junk.php', $VIPROLE) === null,
   'an unparseable body reads as a definite answer instead of null');
$keep = $bot_token; $bot_token = '';
ok(dropshipMemberHasRole('has.php', $VIPROLE) === null,
   'with no bot token configured it claims an answer rather than null');
$bot_token = $keep;
printf("  true / false / 404 / 429 / junk / no-token all behave\n");

if (is_resource($srv)) { proc_terminate($srv); proc_close($srv); }
foreach (array($m, $root . '/guilds/966397496978964500', $root . '/guilds', $root) as $d2) {
    array_map('unlink', glob($d2 . '/*.php') ?: array());
    @rmdir($d2);
}

echo "\nthe recheck is wired, and only ever grants\n";
ok(strpos($dash, "require_once 'role.php'") !== false,
   'dashboard.php does not load role.php, so the recheck cannot run');
ok(strpos($dash, "include 'webhooks.php';") < strpos($dash, "require_once 'role.php'"),
   'role.php is loaded before webhooks.php, so $bot_token is not set yet');
/* Single-quoted: in a double-quoted PHP string "$vip" interpolates and
   "['userData']" turns into a broken character class. */
ok(preg_match('/if \\(\\$vip !== .true./', $dash) === 1,
   'the recheck runs even when the snapshot already says VIP, which spends '
 . 'a Discord call on every dashboard load for players who do not need it');
/* The COMPARISON, not just the variable name -- `if (true)` leaves the
   assignment in place and a name-only check passes a removed throttle. */
ok(preg_match('/\$ds_now - \$ds_last >= 300/', $dash) === 1,
   'the recheck is not throttled, so every dashboard load for a non-VIP '
 . 'hits the Discord API');
ok(preg_match('/=== true\\) \\{\\s*\\$_SESSION\\[.userData.\\]\\[.VIP.\\] = true;/', $dash) === 1,
   'the recheck acts on something other than an explicit true -- null means '
 . 'Discord could not be asked and must never deny anyone');
ok(strpos($dash, "unset(\$_SESSION['userData']['VIP'])") === false,
   'the recheck can now REVOKE access; an outage would lock out a player '
 . 'who was already in the game');


/* ------------------------------------------------------------------ */
/*
 * WHO MAY PLAY FILTHY MERMAID.
 *
 * It was nobody. The gate read
 *
 *     if (pid == 3 && $patron == "true") {          // the 5+ NFT role
 *         if (discord_id is one of two hardcoded ids) play;
 *         else "Cannot Play Until Fully Developed";
 *     }
 *     ...
 *     else if (pid == 3) "You Must Have 5+ NFTs to Play";
 *     ...
 *     else if (pid != 3) play;
 *
 * so the refusal everybody read named a number that would not have let
 * them in either -- no branch reached the play button without one of two
 * Discord ids -- and a Patron saw BOTH messages, because the first block
 * has no else before the chain that follows it. Per the owner: the Shorty
 * Verse NFTs are expensive, holding one is already the hard part, so one
 * qualifying NFT is the whole gate.
 */
echo "\nFilthy Mermaid gates on holding an NFT, like the other themes\n";

/* THE PLAY-GATE REGION ONLY. The dashboard tests project 3 all over the
   place for art, audio and captions, and every one of those is fine; the
   question is whether the GATE still does. */
$gate_from = strpos($dash, 'if(checkSquadCount($conn) >= 1) { ?>');
ok($gate_from !== false, 'the play gate could not be located, so nothing below is proving anything');
$gate = $gate_from === false ? '' : substr($dash, $gate_from, 4000);
$gate_end = strpos($gate, 'Assemble Squad of');
ok($gate_end !== false, 'the end of the play gate could not be located');
if ($gate_end !== false) $gate = substr($gate, 0, $gate_end);
/*
 * COMMENTS STRIPPED FIRST. The note explaining the change quotes the copy
 * that was removed -- "Cannot Play Until Fully Developed", "5+ NFTs" -- so
 * every check below was satisfied by the comment and would have passed
 * with the refusal still in place. This has caught the harness out before.
 */
$gate = preg_replace(array('#/\*.*?\*/#s', '#(^|\s)//[^\n]*#'), ' ', $gate);
ok(strpos($gate, '$play_button') !== false,
   'stripping the comments took the code with it');

ok(strpos($gate, 'Cannot Play Until Fully Developed') === false,
   'the play gate still refuses Filthy Mermaid as unfinished');
ok(strpos($gate, '5+ NFTs') === false,
   'the play gate still demands 5 NFTs, which no branch ever accepted anyway');
ok(strpos($gate, '772831523899965440') === false && strpos($gate, '897218513024974879') === false,
   'the play gate still lets two hardcoded Discord ids in and nobody else');
ok(strpos($gate, '== 3') === false && strpos($gate, '!= 3') === false,
   'the play gate still special-cases project 3');
/* AND THE BUTTON IS STILL REACHED. A gate that refuses nobody and offers
   nothing would pass every check above. */
ok(preg_match('/\}else\{\s*\n\s*echo \$play_button;/', $gate) === 1,
   'nothing falls through to the play button, so the themes that could play now cannot');
/* Oculus Lounge is untouched: it gates on the Discord role, not on
   holdings, and that is a different question. */
ok(strpos($gate, "dropship_project_id'] == 4 && \$vip == \"true\"") !== false,
   "Oculus Lounge's VIP gate was removed along with Filthy Mermaid's");
ok(strpos($gate, 'oculusVipLinks(') !== false,
   "Oculus Lounge's refusal no longer tells a player how to get in");

/*
 * WHERE TO BUY ONE, in the theme's own collection.
 *
 * instructions.php sent every project except Oculus Lounge to Drop Ship's
 * wayup.io collection, so a Dread City or Filthy Mermaid player following
 * "where to buy" was pointed at a different game's NFTs -- and that reader
 * is by definition the one who does not yet know what the qualifying NFT
 * is. Built from the policy id the projects table already holds.
 */
echo "\nthe collection link is the theme's own\n";
require_once __DIR__ . '/dropship/dropship-themes.php';
class VRes { public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return $this->i < count($this->r) ? $this->r[$this->i++] : null; } }
class VConn { public $policies;
	public function __construct($p) { $this->policies = $p; }
	public function query($sql) {
		if (strpos($sql, 'SELECT policy_id FROM projects WHERE id') !== false) {
			preg_match('/id = (\d+)/', $sql, $m); $pid = (int)$m[1];
			return new VRes(isset($this->policies[$pid])
				? array(array('policy_id' => $this->policies[$pid])) : array());
		}
		return new VRes(array());
	} }
$shorty = '0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c';
$vc = new VConn(array(
	3 => $shorty,
	5 => '',                                   /* no policy recorded */
	6 => substr($shorty, 0, 54),               /* hex, two characters short */
	7 => str_repeat('z', 56),                  /* right length, not hex */
	8 => strtoupper($shorty),                  /* same policy, shouted */
));
ok(dropshipCollectionUrl($vc, 3) === 'https://www.wayup.io/collection/' . $shorty,
   'the collection url is not the prefix plus the policy id');
ok(dropshipCollectionUrl($vc, 8) === 'https://www.wayup.io/collection/' . $shorty,
   'an uppercase policy id produces a different url for the same collection');
ok(dropshipCollectionUrl($vc, 5) === '',
   'a project with no policy still gets a collection url, which is a dead link');
ok(dropshipCollectionUrl($vc, 6) === '',
   'a policy id of the wrong length is handed to a player as a url');
ok(dropshipCollectionUrl($vc, 7) === '',
   'a policy id that is not hex is handed to a player as a url');
ok(dropshipCollectionUrl($vc, 0) === '' && dropshipCollectionUrl(null, 3) === '',
   'the url builder does not survive being asked with nothing');

$link = dropshipCollectionLink($vc, 3, '<strong>Intro.</strong>', 'Filthy Mermaid');
ok(strpos($link, 'https://www.wayup.io/collection/' . $shorty) !== false,
   'the rendered link does not carry the collection url');
ok(strpos($link, 'Filthy Mermaid NFTs') !== false,
   "the link is not named in the theme's own words");
ok(strpos($link, '<strong>Intro.</strong>') !== false,
   "the calling page's own copy is dropped");
ok(strpos($link, 'rel="noopener"') !== false,
   'an external link opens in a new tab with no noopener');
ok(dropshipCollectionLink($vc, 5) === '',
   'a project with no policy renders a link to nowhere rather than nothing');
/* The name is page copy but it reaches the markup, and a theme is renamed
   by hand in an admin field. */
ok(strpos(dropshipCollectionLink($vc, 3, '', 'A<b>B'), 'A<b>B') === false,
   "the theme's name is written into the markup unescaped");

$inst = file_get_contents(__DIR__ . '/dropship/instructions.php');
ok(strpos($inst, 'dropshipCollectionLink($conn') !== false,
   'the instructions page does not ask for the theme\'s own collection');
ok(preg_match('/4478c708183e95340d0582419a2d6bc93d57657895c19802546d396c/', $inst) === 1,
   "Drop Ship's own collection is no longer there as the fallback");
$dash_shop = substr($dash, strpos($dash, 'Assemble Squad of'), 900);
ok(strpos($dash_shop, 'dropshipCollectionLink($conn') !== false,
   'the Play refusal does not say where to get a qualifying NFT');
/* HOLDINGS, NOT DEPLOYMENT. This refusal also catches a player who owns a
   squad and has not deployed it, and sending them shopping is wrong. */
ok(strpos($dash_shop, 'dropshipSoldierCount($conn) < 1') !== false,
   'the shop link is shown to a player who already owns soldiers');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all dropship VIP checks passed\n";

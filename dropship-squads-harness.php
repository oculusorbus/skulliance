<?php
/*
 * dropship-squads-harness.php — CLI only. Saved squads, with no database.
 *
 * The rules live in two pure functions on purpose -- dropshipSquadMemberState()
 * and dropshipSquadReadiness() take arrays and return arrays -- so this drives
 * the REAL ones rather than describing them. Everything that writes is a thin
 * wrapper around these two, so what is checked here is what the page decides.
 *
 * Usage: php dropship-squads-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/dropship/dropship-squads.php';

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok   $what\n"; return true; }
	$fail++;  echo "  FAIL $what\n";  return false;
}
function soldier($o) {
	return array_merge(array('id' => 7, 'user_id' => 1, 'active' => 0, 'deceased' => 0), $o);
}

/* ---------- what a member is ----------------------------------------------- */

echo "what one member is\n";
ok(dropshipSquadMemberState(soldier(array()), 1) === 'ready', 'an idle soldier of yours is ready');
ok(dropshipSquadMemberState(soldier(array('active' => 1)), 1) === 'fielded',
   'a deployed soldier reads as something other than fielded');
ok(dropshipSquadMemberState(soldier(array('deceased' => 1)), 1) === 'fallen',
   'a dead soldier reads as something other than fallen');
ok(dropshipSquadMemberState(soldier(array('user_id' => 0)), 1) === 'sold',
   'a soldier you no longer own does not read as sold');
ok(dropshipSquadMemberState(soldier(array('user_id' => 99)), 1) === 'sold',
   'a soldier owned by somebody else does not read as sold');
ok(dropshipSquadMemberState(null, 1) === 'gone', 'a missing soldier row does not read as gone');
/*
 * OWNERSHIP OUTRANKS EVERYTHING. A sold soldier can be deceased and standing
 * on the buyer's field at the same time, and none of that is the seller's
 * business -- reporting "already fought this game" about somebody else's
 * soldier would send a player looking for a soldier they cannot see.
 */
ok(dropshipSquadMemberState(soldier(array('user_id' => 0, 'deceased' => 1)), 1) === 'sold',
   'a sold soldier that is also dead reports the death instead of the sale');
ok(dropshipSquadMemberState(soldier(array('user_id' => 0, 'active' => 1)), 1) === 'sold',
   'a sold soldier on the buyer\'s field reports as fielded, which is not yours to say');
/* The id is compared as a number: the session writes strval() of it. */
ok(dropshipSquadMemberState(soldier(array('user_id' => '1')), '1') === 'ready',
   'a string user id from a restored session reads as somebody else');
/* ORDER INSIDE THE FUNCTION, pinned because both flags can be set at once
   and the answer differs. killSquad() writes deceased=1 and active=0 in one
   statement, so the pair is not reachable today -- but this function takes
   whatever row it is handed, and "dead" beating "on the field" is the
   decision, not an accident of which if came first. */
ok(dropshipSquadMemberState(soldier(array('active' => 1, 'deceased' => 1)), 1) === 'fallen',
   'a soldier that is both dead and flagged active reports as fielded');

/* ---------- when a squad may be fielded ------------------------------------ */

echo "\nwhen a squad may be fielded\n";
$four = array('ready', 'ready', 'ready', 'ready');
$r = dropshipSquadReadiness($four, 4);
ok($r['can'] === true, 'four ready soldiers and room for four cannot deploy');
ok($r['why'] === '', 'a deployable squad still explains itself');

/* THE OWNER'S CALL: refuse a partial squad rather than field three of four.
   A Super Soldier Squad is worth 80 because all four armour tiers are there;
   fielding three would hand somebody 74 under a name that promised 80. */
$r = dropshipSquadReadiness(array('ready', 'ready', 'ready', 'fallen'), 4);
ok($r['can'] === false, 'a squad with a fallen member deploys three of four anyway');
ok(strpos($r['why'], 'already fought this game') !== false,
   'a fallen member is reported as missing or broken rather than as having fought: ' . $r['why']);
ok($r['fix'] === 'wait', 'a fallen member suggests something other than waiting');

/*
 * FALLEN IS NOT DAMAGE, IT IS TUESDAY, and this is the check that keeps the
 * wording honest. killSquad() kills the field after every run and nothing
 * revives anybody until an admin opens the next game, so a squad reads as
 * fallen for most of the week it was used in. Any wording that sounds like a
 * fault sends somebody hunting a bug that is not there.
 */
foreach (array('missing', 'no longer', 'error', 'problem', 'broken') as $alarming) {
	ok(stripos($r['why'], $alarming) === false,
	   "a squad resting between games says '$alarming' at the player");
}

$r = dropshipSquadReadiness(array('ready', 'ready', 'ready', 'sold'), 4);
ok($r['can'] === false, 'a squad with a sold member can still be deployed');
ok(strpos($r['why'], 'no longer yours') !== false, 'a sold member is not explained: ' . $r['why']);
ok($r['fix'] === 'edit', 'a sold member does not point at rebuilding');
/* The two causes must not be conflated: one waits, the other needs a decision. */
ok(dropshipSquadReadiness(array('ready', 'fallen'), 4)['why']
   !== dropshipSquadReadiness(array('ready', 'sold'), 4)['why'],
   'a soldier that fought and a soldier that was sold say the same thing');

/* ---------- the squad nobody can manage ------------------------------------ */

echo "\nthe squad you no longer own\n";
$r = dropshipSquadReadiness(array('sold', 'sold', 'sold', 'sold'), 4);
ok($r['can'] === false, 'a squad of soldiers you sold is deployable');
ok($r['fix'] === 'delete', 'a wholly sold squad offers something other than deleting it');
ok(strpos($r['why'], 'any of these') !== false, 'the all-sold case is not worded for all: ' . $r['why']);
/* ...and one lost soldier is not "any of these soldiers". */
ok(strpos(dropshipSquadReadiness(array('sold'), 4)['why'], 'this soldier') !== false,
   'a one-soldier squad is told about soldiers in the plural');
$r = dropshipSquadReadiness(array('gone', 'sold'), 4);
ok($r['fix'] === 'delete', 'a squad of missing and sold rows is not offered a delete');
$r = dropshipSquadReadiness(array(), 4);
ok($r['can'] === false && $r['fix'] === 'delete', 'an empty squad is deployable or cannot be removed');

/* ---------- the field's own ceiling ---------------------------------------- */

echo "\nthe four-soldier ceiling\n";
$r = dropshipSquadReadiness($four, 2);
ok($r['can'] === false, 'four ready soldiers deploy into two free slots');
ok($r['fix'] === 'withdraw', 'a full field does not say to withdraw somebody');
ok(strpos($r['why'], 'room for only 2') !== false, 'the refusal does not say how much room there is');

/*
 * NO ARITHMETIC SENTENCES, and zero is the one that gives it away. The first
 * version read "There is only room for 0 more on the field", which is the
 * shape of a string built by a loop rather than written by a person.
 */
ok(strpos(dropshipSquadReadiness($four, 0)['why'], 'for 0') === false,
   'the full-squad refusal still counts down to zero: '
   . dropshipSquadReadiness($four, 0)['why']);
ok(strpos(dropshipSquadReadiness($four, 0)['why'], 'already full') !== false,
   'a full squad does not simply say it is full');

/*
 * AND IT IS CALLED A SQUAD, because the game has never called it anything
 * else -- deploySoldier() says "Your squad is full." "The field" was a
 * second name for a thing that already had one.
 */
$sayings = array(
	dropshipSquadReadiness($four, 0)['why'],
	dropshipSquadReadiness($four, 2)['why'],
	dropshipSquadReadiness(array('ready', 'sold'), 4)['why'],
	dropshipSquadReadiness(array('ready', 'fallen'), 4)['why'],
	dropshipSquadReadiness(array('sold'), 4)['why'],
);
foreach ($sayings as $saying) {
	ok(stripos($saying, 'the field') === false, "a message still says \"the field\": $saying");
}
/*
 * EVERY player-facing string in the file, not just the ones a pure function
 * returns. The messages from dropshipSquadSave() and dropshipWithdrawAll()
 * need a database to produce, and a mutation putting "the field" back into
 * the save refusal survived a check that only read the readiness ones.
 * Scanned off the source with comments stripped, since the comments discuss
 * the phrase on purpose.
 */
/*
 * A PLAIN PHRASE SCAN, after one attempt at something cleverer. Pairing up
 * quotes to pull out string literals mis-pairs on the first apostrophe
 * inside a double-quoted string and then reads every quote after it as the
 * wrong half of a pair -- it found 307 literals and none of the offending
 * one. The phrase simply does not belong anywhere outside a comment.
 */
/* READ HERE. $src is not assigned until a later section, so this scanned an
   empty string and passed on anything -- the same shape of bug as the
   dhcarena harness that referenced $dbsrc6 before it existed. */
$noComments = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
                           (string)file_get_contents(__DIR__ . '/dropship/dropship-squads.php'));
preg_match_all('/.{0,40}the field.{0,40}/i', $noComments, $hits);
ok($hits[0] === array(),
   'something outside a comment says "the field" instead of squad: '
   . implode(' | ', array_map(function ($x) { return trim(preg_replace('/\s+/', ' ', $x)); },
                              $hits[0])));

echo "\nwithdraw all\n";
/*
 * Driven, not read: "0 soldiers withdrawn" on an empty squad is a success
 * message for something that did not happen, and only running it shows that.
 */
class WAConn {
	public $affected_rows = 0; public $rows = 0;
	public function __construct($rows) { $this->rows = $rows; }
	public function real_escape_string($s) { return $s; }
	public function query($sql) { $this->affected_rows = $this->rows; return true; }
}
$_SESSION['userData'] = array('dropship_user_id' => 1, 'dropship_project_id' => 1);
list($wok, $wmsg) = dropshipWithdrawAll(new WAConn(3));
ok($wok === true && strpos($wmsg, '3 soldiers') === 0, "withdrawing three said: $wmsg");
list($wok, $wmsg) = dropshipWithdrawAll(new WAConn(1));
ok(strpos($wmsg, '1 soldier ') === 0, "withdrawing one is pluralised: $wmsg");
list($wok, $wmsg) = dropshipWithdrawAll(new WAConn(0));
ok($wok === false, 'withdrawing from an empty squad reports success');
ok(strpos($wmsg, '0 ') === false, "an empty squad is told about 0 soldiers: $wmsg");
ok(dropshipSquadReadiness(array('ready', 'ready'), 2)['can'] === true,
   'two soldiers will not go into exactly two slots');
/* Already standing there is not a reason to refuse. */
ok(dropshipSquadReadiness(array('fielded', 'fielded', 'ready'), 1)['can'] === true,
   'a squad mostly on the field already cannot send its last member');
$r = dropshipSquadReadiness(array('fielded', 'fielded'), 0);
ok($r['can'] === false && strpos($r['why'], 'already deployed') !== false,
   'a squad that is wholly deployed is offered a Deploy: ' . $r['why']);

/* ---------- counts, which the screen renders ------------------------------- */

echo "\nthe counts\n";
$r = dropshipSquadReadiness(array('ready', 'fallen', 'fallen', 'sold'), 4);
ok($r['counts']['fallen'] === 2 && $r['counts']['sold'] === 1 && $r['counts']['ready'] === 1,
   'the per-state counts are wrong: ' . json_encode($r['counts']));
ok(dropshipSquadPlural(1, 'member') === '1 member', 'one member is pluralised');
ok(dropshipSquadPlural(3, 'member') === '3 members', 'three members are not');
/* Singular all the way through the sentence, noun AND verb: "1 members are"
   and "1 member are" are both the mark of a page nobody finished. */
$one = dropshipSquadReadiness(array('ready', 'sold'), 4)['why'];
ok(strpos($one, '1 member ') === 0, "a single lost member reads as plural: $one");
ok(strpos($one, ' is no longer yours') !== false, "the verb does not agree: $one");
$two = dropshipSquadReadiness(array('ready', 'sold', 'sold'), 4)['why'];
ok(strpos($two, '2 members ') === 0 && strpos($two, ' are no longer yours') !== false,
   "two lost members read as singular: $two");

/* ---------- the writes refuse a guessed id --------------------------------- */

echo "\nnothing takes a soldier id from the browser\n";
$src = file_get_contents(__DIR__ . '/dropship/dropship-squads.php');
$code = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '', $src);
/*
 * deploySoldier() carries its own comment admitting it never checks
 * ownership, so a brute-forced soldier_id deploys somebody else's soldier.
 * None of that may be copied in here: a saved squad reads its members FROM
 * THE FIELD, and every statement that writes is scoped to the player.
 */
ok(strpos($code, '$_POST') === false, 'the squad library reads $_POST directly');
ok(strpos($code, '$_GET') === false,  'the squad library reads $_GET directly');
/*
 * EVERY STATEMENT THAT WRITES, not merely every function that mentions $me.
 * The first version of this check looked for '$me' anywhere in the function
 * body and passed while the UPDATE had lost its scope entirely -- $me is all
 * over these functions for other reasons, so its presence proves nothing.
 * The statements are pulled out and each one is checked on its own.
 */
foreach (array('dropshipSquadDeploy', 'dropshipSquadDisperse', 'dropshipSquadDrop') as $fn) {
	$at = strpos($code, 'function ' . $fn);
	ok($at !== false, "$fn has gone");
	/*
	 * THE WHOLE FUNCTION, BY BRACE MATCHING. This took a fixed 1600-char
	 * window from the function start, which is fine until the function
	 * grows past it -- and then the UPDATE falls outside the window, no
	 * write statements are found, and the per-statement scope checks pass
	 * by having nothing to check. The "writes > 0" guard below caught it,
	 * which is the only reason it was not silent, but the window itself is
	 * the bug. Same shape as the exit() that killed a whole block of
	 * dropship-grunts-harness.php.
	 */
	$body = '';
	if ($at !== false) {
		$open = strpos($code, '{', $at);
		$depth = 0;
		for ($i = $open; $i !== false && $i < strlen($code); $i++) {
			if ($code[$i] === '{') $depth++;
			elseif ($code[$i] === '}') { $depth--; if ($depth === 0) { $i++; break; } }
		}
		$body = substr($code, $at, $i - $at);
	}
	preg_match_all('/\b(UPDATE|DELETE)\b.*?(?=\'\)|";|\')/s', $body, $stmts);
	$writes = 0;
	foreach ($stmts[0] as $st) {
		$st = preg_replace('/\s+/', ' ', $st);
		/* squad_members is reached THROUGH a squad whose ownership was just
		   established, and carries no user_id of its own to test. */
		if (strpos($st, 'squad_members') !== false) continue;
		$writes++;
		ok(strpos($st, 'user_id = $me') !== false,
		   "$fn writes without scoping to the player: " . substr($st, 0, 90));
	}
	ok($writes > 0, "$fn no longer writes anything, so the checks above are vacuous");
}
/* The delete carries its own ownership test rather than trusting a SELECT
   that ran a statement earlier. */
$at = strpos($code, 'function dropshipSquadDisperse');
ok(strpos(substr($code, $at, 900), 'DELETE FROM squads WHERE id = $sid AND user_id = $me') !== false,
   'dispersing can delete a squad that is not yours');
/* Saving never takes a list of soldiers from the caller. */
/* Takes the connection and NOTHING else: no name (the owner removed those)
   and, more importantly, no soldier ids. The members are whoever is standing
   on the field, read from the database at the moment of the save. */
ok(preg_match('/function dropshipSquadSave\(\$conn\)/', $code) === 1,
   'dropshipSquadSave() takes an argument it should not, probably soldier ids');
ok(strpos($code, 'active = 1 AND deceased = 0') !== false,
   'saving does not read the squad off the field');

/*
 * AND THE BUTTON NEXT DOOR. deploySoldier() is what the single Deploy posts
 * to, and it carried a comment admitting it never checked ownership -- a
 * bare soldier_id from a form, straight into the WHERE. Squad deploys would
 * have sat beside it doing the right thing while the older path stayed open,
 * so it is fixed and pinned here rather than left as a known hole with a
 * nicer neighbour.
 */
echo "\nand the single Deploy is scoped too\n";
$dbsrc = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
                      (string)file_get_contents(__DIR__ . '/dropship/db.php'));
$at = strpos($dbsrc, 'function deploySoldier');
ok($at !== false, 'deploySoldier() has gone');
$body = $at === false ? '' : substr($dbsrc, $at, 900);
ok(strpos($body, "user_id = '\".\$_SESSION['userData']['dropship_user_id'].\"'") !== false,
   'deploySoldier() updates by id alone again, so a guessed id moves somebody else\'s soldier');

/* ---------- what a squad IS, since it has no name -------------------------- */

echo "\na squad is identified by what it carries\n";
require_once __DIR__ . '/dropship/dropship-odds.php';
function mem($armor, $gear = 'None') {
	return array('row' => array('id' => 1, 'user_id' => 1, 'active' => 0, 'deceased' => 0,
	                            'armor' => $armor, 'gear' => $gear));
}
$superSquad = array(mem('Heavy', 'Melee'), mem('Medium', 'Demolition'),
                    mem('Light', 'Medkit'), mem('Base'));
$p = dropshipSquadPower($superSquad);
ok($p['super'] === true, 'four tiers and three gear types is not recognised as super');
ok(strpos($p['label'], 'Squad') !== false, "the super label lost its noun: {$p['label']}");
/*
 * THE SAME THRESHOLD THE RUN ROLLS AGAINST. 74 + 3 + 2 + 1 = 80, and the
 * point of deriving it rather than storing a number is that these cannot
 * drift -- a harness that hardcoded 80 here would pass after somebody
 * retuned the credits and the screen started lying.
 */
ok($p['pct'] === (int)dropshipSurvivalPct(dropshipThreshold(dropshipSquadFlags($superSquad))),
   'the quoted percentage is not the one dropshipThreshold() produces');
ok($p['pct'] === 80, "a complete squad should read 80, got {$p['pct']}");

/* All four tiers but not all the gear is not super -- the same test
   dropshipSquadAdvice() applies, not a second opinion. */
$allArmour = array(mem('Heavy'), mem('Medium'), mem('Light'), mem('Base'));
$p2 = dropshipSquadPower($allArmour);
ok($p2['super'] === false, 'armour alone is being called a Super Soldier Squad');
ok($p2['pct'] === 80, 'gear changed the odds, which it does not');
ok($p2['label'] !== $p['label'], 'a geared and an ungeared squad read identically');

$weak = array(mem('Base'));
ok(dropshipSquadPower($weak)['pct'] === 50, 'one Base soldier should be 50');
ok(dropshipSquadPower(array())['tiers'] === 0, 'an empty squad claims armour');
/* A member that is gone contributes nothing rather than exploding. */
ok(dropshipSquadPower(array(array('row' => null), mem('Heavy')))['pct'] === 74,
   'a missing member breaks the reckoning');

/*
 * SUITS ARE NOT THE SQUAD'S. They are purchased items on the session and
 * they OVERRIDE armour in the threshold chain, so folding one in reports the
 * same number for every squad a player owns -- precisely when ordering by
 * strength has to work.
 */
$f = dropshipSquadFlags($superSquad);
foreach (array('mech_suit', 'exo_suit', 'quantum_stealth', 'ballistic_shield') as $suit) {
	ok($f[$suit] === false, "a squad claims to be carrying a $suit");
}

echo "\nstrongest first\n";
$list = array(
	array('id' => 3, 'members' => $weak,       'power' => dropshipSquadPower($weak)),
	array('id' => 1, 'members' => $superSquad, 'power' => $p),
	array('id' => 2, 'members' => $allArmour,  'power' => $p2),
);
dropshipSquadSort($list);
ok($list[0]['power']['pct'] === 80 && $list[2]['power']['pct'] === 50,
   'the list is not in strength order: '
   . implode(',', array_map(function ($x) { return $x['power']['pct']; }, $list)));
/* Gear breaks the 80/80 tie, because two squads at the same odds are not
   equal if one of them carries a Medkit. */
ok($list[0]['power']['super'] === true, 'the geared squad does not win the tie at 80');
/* TOTAL, so a list does not reshuffle between page loads for no reason. */
$again = $list; dropshipSquadSort($again);
ok(array_column($again, 'id') === array_column($list, 'id'), 'the sort is not stable');

echo "\nheaviest armour first inside a squad\n";
/* The order dropshipThreshold() builds the squad's number in: the best tier
   worn is the floor, the lower tiers are credits on top. */
$mix = array(
	array('row' => array('armor' => 'Base',   'name' => 'D')),
	array('row' => array('armor' => 'Light',  'name' => 'C')),
	array('row' => array('armor' => 'Heavy',  'name' => 'A')),
	array('row' => array('armor' => 'Medium', 'name' => 'B')),
);
dropshipSquadOrderMembers($mix);
ok(array_column(array_column($mix, 'row'), 'name') === array('A', 'B', 'C', 'D'),
   'a squad is not ordered heaviest first: '
   . implode('', array_column(array_column($mix, 'row'), 'name')));
/* Armour the chain does not recognise, and a member whose row has gone, sort
   last rather than wherever -- not at the front where they would read as the
   tier carrying the squad. */
$odd = array(
	array('row' => null),
	array('row' => array('armor' => '',      'name' => 'X')),
	array('row' => array('armor' => 'Base',  'name' => 'D')),
	array('row' => array('armor' => 'Heavy', 'name' => 'A')),
);
dropshipSquadOrderMembers($odd);
ok(isset($odd[0]['row']['name']) && $odd[0]['row']['name'] === 'A',
   'an unarmoured or missing member sorts above the Heavy');
ok(isset($odd[1]['row']['name']) && $odd[1]['row']['name'] === 'D',
   'the Base soldier does not follow the Heavy');
/* The two rankless kinds -- no armour, and no row at all -- both sort after
   every armoured member. WHICH of the two comes first is not a decision
   anybody made: they tie and the tie is broken by insertion order, which is
   stable and is all this needs to promise. Asserting one above the other
   would pin an accident. */
$tail = array($odd[2], $odd[3]);
$names = array();
foreach ($tail as $x) $names[] = $x['row'] === null ? 'gone' : $x['row']['name'];
sort($names);
ok($names === array('X', 'gone'), 'the unarmoured and missing members do not sort last: '
   . implode(',', $names));
ok(dropshipSquadArmorRank('Heavy') < dropshipSquadArmorRank('Base'),
   'the armour ranking is upside down');
ok(dropshipSquadArmorRank('Nonsense') > dropshipSquadArmorRank('Base'),
   'an unknown armour outranks Base');
/* STABLE: two of the same tier must not swap between page loads. */
$pair = array(array('row' => array('armor' => 'Heavy', 'name' => 'first')),
              array('row' => array('armor' => 'Heavy', 'name' => 'second')));
dropshipSquadOrderMembers($pair);
ok($pair[0]['row']['name'] === 'first', 'two soldiers of the same tier reorder themselves');

echo "\na soldier belongs to one squad\n";
/*
 * THE OWNER'S RULE, after the walk harness found that tweaking a deployed
 * squad and saving quietly put three soldiers in two squads at once.
 * dropshipSquadShared() is the net under the rule, not the rule itself.
 */
ok(dropshipSquadShared(array(
		array('members' => array(array('soldier_id' => 1), array('soldier_id' => 2))),
		array('members' => array(array('soldier_id' => 2), array('soldier_id' => 3))),
	)) === array(2 => 2), 'a soldier in two squads is not reported as shared');
ok(dropshipSquadShared(array(
		array('members' => array(array('soldier_id' => 1))),
		array('members' => array(array('soldier_id' => 2))),
	)) === array(), 'squads that share nobody are reported as sharing somebody');
/* The refusal has to come AFTER the duplicate test, or saving the same four
   twice reports a clash instead of "you already saved this". */
$saveSrc = substr($code, strpos($code, 'function dropshipSquadSave'), 2600);
ok(strpos($saveSrc, 'already saved this squad') < strpos($saveSrc, 'already in a saved squad'),
   'the clash check runs before the duplicate check, so re-saving reports the wrong thing');
/* ...and it must fire whenever ANY member is spoken for. `count($clash) !==
   count($ids)` was the first attempt and the walk broke it in eight steps:
   save {D,E}, withdraw D, save, and {E} slips through as not-a-duplicate. */
ok(strpos($saveSrc, 'count($clash) !== count($ids)') === false,
   'the clash check stands down when every member is spoken for, which lets a subset through');
ok(preg_match('/if \(\$clash\) \{/', $saveSrc) === 1,
   'the clash check is conditional on something other than there being a clash');

echo "\nthe squad shows what you added before what a squad brought\n";
/*
 * THE OWNER'S RULE: soldiers you added one at a time keep the order you added
 * them, and anything that arrived through Deploy squad follows in armour
 * order. Adding one at a time is a sequence the player performed, and
 * watching it resort itself is disorienting; a squad arrived all at once and
 * has no order of its own, so it takes the one its own card uses.
 */
function fr($id, $armor) { return array('soldier_id' => $id, 'armor' => $armor); }
$rows   = array(fr(1,'Base'), fr(2,'Heavy'), fr(3,'Light'), fr(4,'Medium'));
/* 2 and 4 came from a saved squad; 1 and 3 the player added, 3 before 1. */
$when   = array(1 => '2026-10-21 10:05:00', 3 => '2026-10-21 10:01:00');
$insq   = array(2 => array(9), 4 => array(9));
$out    = dropshipFieldOrder($rows, $when, $insq);
ok(array_column($out, 'soldier_id') === array(3, 1, 2, 4),
   'the squad is not ordered yours-then-squad: ' . implode(',', array_column($out, 'soldier_id')));
/* All individual: purely the order they were added, NOT by armour. */
$out = dropshipFieldOrder($rows, array(1=>'10:04',2=>'10:01',3=>'10:03',4=>'10:02'), array());
ok(array_column($out, 'soldier_id') === array(2, 4, 3, 1),
   'individual adds are being resorted by something other than when they were added');
/* All from a squad: purely armour, Heavy first. */
$out = dropshipFieldOrder($rows, array(), array(1=>array(9),2=>array(9),3=>array(9),4=>array(9)));
ok(array_column($out, 'soldier_id') === array(2, 4, 3, 1),
   'a deployed squad is not shown heaviest first: ' . implode(',', array_column($out, 'soldier_id')));
/* A soldier deployed before active_at existed has no time at all. It was
   there first, so it sorts first rather than anywhere. */
$out = dropshipFieldOrder(array(fr(1,'Base'), fr(2,'Heavy')), array(2 => '10:00'), array());
ok(array_column($out, 'soldier_id') === array(1, 2),
   'a soldier with no deploy time is sorted into the middle of the row');
/* Stable when everything ties, so the row does not shuffle on every load. */
$t = dropshipFieldOrder($rows, array(), array());
ok(array_column($t, 'soldier_id') === array(1, 2, 3, 4), 'an all-tie row reorders itself');

/* ---------- the screen it is wired to -------------------------------------- */

echo "\npicking a different squad swaps\n";
/*
 * Deploying while something is fielded used to answer "Your squad is
 * already full. Withdraw somebody first." Nobody presses Deploy squad on a
 * second squad meaning "but only if the field is empty"; they mean field
 * this one instead.
 *
 * THE SAFETY PROPERTY IS THAT IT ONLY CLEARS FOR ROOM.
 * dropshipSquadReadiness() tags the full-field case 'fix' => 'withdraw',
 * and every other refusal -- members who fought this game, died, or were
 * sold -- must still stand rather than emptying the field for a deployment
 * that cannot happen anyway.
 */
$dep_at = strpos($code, 'function dropshipSquadDeploy');
$dep = substr($code, $dep_at, 2600);
ok(strpos($dep, 'dropshipWithdrawAll($conn)') !== false,
   'deploying a second squad no longer clears the field, so it refuses again');
ok(preg_match("/fix.*?===\s*'withdraw'/", $dep) === 1,
   'the auto-withdraw is not gated on room being the blocker, so it clears the field '
 . 'for squads that cannot deploy anyway');
/* AND IT RE-READS THE SQUAD. Readiness was computed against the room
   available when the squad was loaded; the stale copy still believes the
   field is full, so deploying would refuse immediately after clearing. */
ok(substr_count($dep, 'dropshipSquadsAll($conn, $me, $pid)') >= 2,
   'the squad is not re-read after the field is cleared, so its readiness is stale');
/* Clearing a field that is already THIS squad is not a swap. */
/*
 * AND THE BUTTON MUST STILL BE THERE TO PRESS. Hiding Deploy on a full
 * field is the one thing that makes the swap unreachable: every other
 * squad loses its control and the player is back to clearing by hand,
 * which is what the swap removed.
 */
$panel_src = $code;
ok(strpos($panel_src, '$swaps = !$can &&') !== false,
   'the panel no longer works out whether a squad can swap in');
ok(strpos($panel_src, 'if ($offer):') !== false,
   'the deploy button is gated on $can again, so a full field hides every other squad\'s button');
ok(strpos($panel_src, "'Swap in'") !== false,
   'the button still says Deploy squad when it will first clear the field');
ok(strpos($panel_src, 'Swapping in withdraws your current squad') !== false,
   'the reason text still tells the player to withdraw next to a button that withdraws for them');

ok(strpos($dep, '$holding') !== false,
   'it withdraws even when the field is already this squad, undoing what is done');

echo "\nthere is no barracks any more\n";
/*
 * EVERYTHING IS SQUAD BASED. The loose grid and its attribute filter were
 * how a player picked soldiers one at a time and hunted for the pieces of
 * a Super Soldier Squad by hand. dropshipSquadsAutoSync() arranges the
 * whole roster, so every soldier is already in a squad and there is
 * nothing loose left to list -- the grid would have rendered empty, and
 * the filter would have been filtering nothing.
 *
 * A single soldier is still reachable: the plan runs past the complete
 * squads into threes, twos and ones, so the dregs are a squad like any
 * other and deploy in one press.
 */
$dash = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash, 'filterTroops("dashboard")') === false,
   'the attribute filter is back on the dashboard, filtering a list that no longer exists');
ok(strpos($dash, 'getSoldiers($conn, 0, $filterby)') === false,
   'the loose barracks grid is back, and every soldier in it is already in a squad above');
/*
 * AND THE SCRIPT THAT DROVE IT IS GONE TOO. getElementById() on a removed
 * id returns null, and reading .value off null throws -- taking every
 * script after it on the page with it. Removing a control means removing
 * what talks to it.
 */
ok(strpos($dash, "getElementById('filterTroops')") === false,
   'a script still sets a value on the filter element, which no longer exists');

/*
 * getSoldiers() KEEPS NO SQUAD EXCLUSION. It had one for the barracks, to
 * stop a soldier appearing both in their squad and loose below it. With no
 * loose list there is nothing to exclude from, and $active 1 (the field)
 * and $active 2 (the public roll) always wanted every soldier anyway.
 */
$gs = strpos($dbsrc, 'function getSoldiers');
$gsb = $gs === false ? '' : substr($dbsrc, $gs, 3000);
ok(strpos($gsb, 'soldiers.id NOT IN (') === false,
   'the squad exclusion is back in getSoldiers, and nothing renders a list for it to filter');

echo "\nthe panel\n";
$panel = preg_replace(array('#/\*.*?\*/#s'), '', $src);
/*
 * THE MANUAL CONTROLS ARE GONE, AND THAT IS NOW THE THING TO PIN.
 *
 * Squads are generated by dropshipSquadsAutoSync() from what the player
 * owns. There is nothing to assemble, so nothing to save; nothing chosen,
 * so nothing to disperse; and a member who has been sold is noticed by the
 * engine before anybody could press Remove. Putting any of them back would
 * offer an action the next page load silently undoes.
 */
$dis = substr_count($panel, 'name="squad_disperse"');
ok($dis === 0, "Disperse is back, rendered $dis times, and the engine would undo it");
ok(substr_count($panel, 'name="squad_drop"') === 0,
   'Remove is back on squad members, which the engine would undo');
ok(strpos($panel, "if (\$can): ?>\n\t\t\t\t<form") !== false
   || preg_match('/\$can \?.*squad_deploy|if \(\$can\)/s', $panel) === 1,
   'Deploy is offered on a squad that cannot deploy');
ok(substr_count($panel, 'name="squad_deploy"') === 1,
   'Deploy has gone from the panel, which is the one squad action still the player\'s');

$dash = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash, 'name="squad_save"') === false,
   'the save-a-squad form is back, and there is no hand-built squad to save');
ok(strpos($dash, 'dropshipSquadSave($conn)') === false,
   'the dashboard still handles a squad_save post');
ok(strpos($dash, 'dropshipSquadDisperse($conn') === false,
   'the dashboard still handles a squad_disperse post');
/* CLEARING THE FIELD IS STILL THE PLAYER'S. It is how one squad comes off
   and another goes on, and it touches no squad. */
ok(strpos($dash, 'name="squad_clear"') !== false, 'Withdraw all has gone with the rest');
ok(strpos($dash, 'dropshipWithdrawAll($conn)') !== false, 'and nothing handles it');

/*
 * AND THE ENGINE RUNS, AFTER THE HOLDINGS SYNC.
 *
 * Before it, a freshly bought NFT would sit outside every squad until the
 * page after next -- which is exactly the managing this removes.
 */
$sync = strpos($dash, 'dropshipSquadsAutoSync($conn)');
ok($sync !== false, 'nothing builds the squads, so a new player has none');
$upd = strpos($dash, 'updateSoldiers($conn');
ok($upd === false || $upd < $sync,
   'the squad engine runs before the holdings sync, so it plans a stale roster');
$panelAt = strpos($dash, 'dropshipSquadsPanel($conn)');
ok($sync !== false && $panelAt !== false && $sync < $panelAt,
   'the squads are drawn before the engine has arranged them');
/* There is no longer a loose grid to render squads above; the panel is
   the whole of it. What still matters is that it renders at all. */
ok(strpos($dash, 'dropshipSquadsPanel($conn)') !== false,
   'nothing draws the squads, so the barracks is now an empty heading');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all squad checks passed\n");
exit($fail ? 1 : 0);

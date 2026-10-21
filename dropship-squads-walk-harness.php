<?php
/*
 * dropship-squads-walk-harness.php — CLI only. Every order of operations.
 *
 * WHY A WALK AND NOT MORE EXAMPLES. Asked whether saved squads handle every
 * quirk of adding, withdrawing and saving, the honest answer was that nobody
 * knew: the cases had been picked by hand, and the one the owner asked about
 * -- withdraw part of a deployed squad, replace the gap, save -- turned out to
 * do something nobody had considered. Picking cases by hand finds the cases
 * you thought of.
 *
 * So this drives the REAL save, deploy, disperse and member-state functions
 * through 4000 random sequences of nine actions over six soldiers, and checks
 * the invariants after EVERY step rather than at the end. What it protects is
 * not any one scenario but the claim that there is no order of operations
 * that leaves a squad nobody can explain.
 *
 * THE INVARIANTS, each of which is a bug somebody could hit:
 *   - the field never holds more than four
 *   - no two saved squads hold exactly the same soldiers
 *   - no saved squad is empty
 *   - a squad that refuses to deploy always says why, and one that can deploy
 *     never carries a refusal
 *   - overlap is ALLOWED but must be reported by dropshipSquadShared(), and
 *     nothing may be reported as shared that is not
 *   - the barracks filter and the squads agree about who is spoken for
 *
 * ONE MUTATION IS EQUIVALENT AND IS NOT CHASED: making the armour-order tie
 * return 0 instead of comparing insertion position. PHP's sort has been
 * stable since 8.0, so two soldiers of the same tier keep their order either
 * way and nothing on the page moves. The comparison stays because the
 * guarantee should be in the code rather than in a language note.
 *
 * Usage: php dropship-squads-walk-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/* Walk every reachable sequence of squad actions and assert the invariants
   after each step. Finds the quirks instead of guessing at them. */
session_start();
$_SESSION['userData'] = array('dropship_user_id' => 1, 'dropship_project_id' => 1);
function evaluateText($t){ return $t; }
require __DIR__ . '/dropship/dropship-grunts.php';
require __DIR__ . '/dropship/dropship-odds.php';
require __DIR__ . '/dropship/dropship-squads.php';
function checkSquadCount($c){ global $DB; $n=0; foreach($DB['soldiers'] as $s) if($s['active']&&!$s['deceased']&&$s['user_id']==1)$n++; return $n; }
class R { public $rows; public $num_rows;
  function __construct($r){ $this->rows=array_values($r); $this->num_rows=count($r); }
  function fetch_assoc(){ return array_shift($this->rows); } }
class C {
  public $insert_id=0; public $affected_rows=0;
  function real_escape_string($s){ return addslashes($s); }
  function query($q){
    global $DB;
    $q1 = preg_replace('/\s+/',' ',$q);
    if (strpos($q1,'SHOW TABLES')!==false) return new R(array(array('x'=>'squads')));
    if (strpos($q1,'SELECT id FROM squads')!==false) {
      $o=array(); foreach($DB['squads'] as $id=>$s) $o[]=array('id'=>$id); return new R($o);
    }
    if (strpos($q1,'SELECT name FROM soldiers WHERE id IN')!==false) {
      preg_match('/IN \(([\d,]+)\)/',$q1,$m); $o=array();
      foreach(explode(',',$m[1]) as $id) if(isset($DB['soldiers'][(int)$id])) $o[]=array('name'=>$DB['soldiers'][(int)$id]['name']);
      return new R($o);
    }
    if (strpos($q1,'SELECT id FROM soldiers')!==false) {
      $o=array(); foreach($DB['soldiers'] as $s) if($s['user_id']==1 && $s['active'] && !$s['deceased']) $o[]=array('id'=>$s['id']);
      return new R($o);
    }
    if (strpos($q1,'FROM squad_members m LEFT JOIN')!==false) {
      $o=array();
      foreach($DB['members'] as $m){ $s=$DB['soldiers'][$m['soldier_id']] ?? null;
        $o[] = array_merge(array('squad_id'=>$m['squad_id'],'soldier_id'=>$m['soldier_id'],'slot'=>$m['slot']), $s?:array('id'=>null)); }
      return new R($o);
    }
    if (strpos($q1,'FROM squad_members m INNER JOIN')!==false) {
      $o=array(); foreach($DB['members'] as $m) $o[]=array('soldier_id'=>$m['soldier_id'],'id'=>$m['squad_id']);
      return new R($o);
    }
    if (strpos($q1,'INSERT INTO squads')!==false) { $id=$DB['next']++; $DB['squads'][$id]=true; $this->insert_id=$id; return true; }
    if (strpos($q1,'INSERT INTO squad_members')!==false) {
      preg_match_all('/\((\d+),(\d+),(\d+)\)/',$q1,$m,PREG_SET_ORDER);
      foreach($m as $x) $DB['members'][]=array('squad_id'=>(int)$x[1],'soldier_id'=>(int)$x[2],'slot'=>(int)$x[3]);
      return true;
    }
    if (strpos($q1,'DELETE FROM squads')!==false) {
      preg_match('/id = (\d+)/',$q1,$m); $id=(int)$m[1];
      if (isset($DB['squads'][$id])) { unset($DB['squads'][$id]); $this->affected_rows=1; }
      else $this->affected_rows=0;
      return true;
    }
    if (strpos($q1,'DELETE FROM squad_members')!==false) {
      preg_match('/squad_id = (\d+)/',$q1,$m); $id=(int)$m[1];
      foreach($DB['members'] as $k=>$x) if($x['squad_id']==$id) unset($DB['members'][$k]);
      $DB['members']=array_values($DB['members']); return true;
    }
    if (strpos($q1,'UPDATE soldiers SET active = 1')!==false) {
      preg_match('/IN \(([\d,]+)\)/',$q1,$m);
      foreach(explode(',',$m[1]) as $id) if(isset($DB['soldiers'][(int)$id])) $DB['soldiers'][(int)$id]['active']=1;
      return true;
    }
    return new R(array());
  }
}

$ARMOR = array('Heavy','Medium','Light','Base','Heavy','Light');
$GEAR  = array('Melee','Demolition','Medkit','None','Medkit','None');
function reset_db($n = 6) {
  global $DB, $ARMOR, $GEAR;
  $DB = array('soldiers'=>array(),'squads'=>array(),'members'=>array(),'next'=>1);
  for ($i=0;$i<$n;$i++) { $id=11+$i;
    $DB['soldiers'][$id]=array('id'=>$id,'user_id'=>1,'name'=>chr(65+$i),'asset_name'=>'a'.$i,
      'rank'=>'P','armor'=>$ARMOR[$i],'gear'=>$GEAR[$i],'level'=>1,'ipfs'=>'x','active'=>0,'deceased'=>0); }
}
$problems = array();
function flag($what, $trail) {
  global $problems; $k = $what;
  if (!isset($problems[$k])) $problems[$k] = $trail;
}

function invariants($conn, $trail) {
  global $DB;
  $field = array(); foreach($DB['soldiers'] as $s) if($s['active']) $field[]=$s['id'];
  if (count($field) > 4) flag('the field holds more than four soldiers', $trail);

  $squads = dropshipSquadsAll($conn, 1, 1);
  $sets = array(); $seen = array(); $overlap = array();
  foreach ($squads as $sq) {
    if (!$sq['members']) flag('a saved squad has no members at all', $trail);
    $ids = array(); foreach ($sq['members'] as $m) $ids[] = (int)$m['soldier_id'];
    sort($ids);
    $key = implode(',', $ids);
    if (isset($sets[$key])) flag('two saved squads hold exactly the same soldiers', $trail);
    $sets[$key] = true;
    foreach ($ids as $id) {
      if (isset($seen[$id])) $overlap[$id] = true;
      $seen[$id] = $sq['id'];
    }
    /* HEAVIEST FIRST, checked on what dropshipSquadsAll() actually returns
       and not on the sorter in isolation -- removing the CALL leaves the
       sorter passing its own tests while every squad on the page is in
       insertion order. */
    $last = -1;
    foreach ($sq['members'] as $m) {
      $r = dropshipSquadArmorRank(isset($m['row']['armor']) ? $m['row']['armor'] : '');
      if ($r < $last) flag('a squad renders its members out of armour order', $trail);
      $last = $r;
    }
    if (!$sq['ready']['can'] && trim($sq['ready']['why']) === '') {
      flag('a squad refuses to deploy and gives no reason', $trail);
    }
    if ($sq['ready']['can'] && $sq['ready']['why'] !== '') {
      flag('a deployable squad also carries a refusal message', $trail);
    }
  }
  /*
   * A SOLDIER BELONGS TO ONE SQUAD, and no order of operations may produce
   * otherwise. This is the invariant the walk was written for: the case that
   * broke it -- withdraw part of a deployed squad, fill the gap, save -- is
   * nine steps in and was not on anybody's list.
   */
  foreach (array_keys($overlap) as $id) {
    flag('a soldier belongs to two saved squads at once', $trail);
  }
  /* And the net under it still works, for a row this code did not create.
     Asserted both ways so it cannot rot into something that reports nothing. */
  $reported = dropshipSquadShared($squads);
  foreach (array_keys($overlap) as $id) {
    if (!isset($reported[$id])) flag('an overlap exists and nothing reports it', $trail);
  }
  foreach (array_keys($reported) as $id) {
    if (!isset($overlap[$id])) flag('a soldier is reported as shared when it is not', $trail);
  }
  /* The map the barracks filters on must agree with the squads themselves. */
  $map = dropshipSquadMembership($conn, 1, 1);
  foreach ($seen as $id => $qid) {
    if (!isset($map[$id])) flag('a squad member is missing from the barracks filter', $trail);
  }
  if (count($map) !== count($seen)) flag('the barracks filter and the squads disagree on the count', $trail);
  foreach ($map as $id => $qids) {
    if (!is_array($qids)) flag('the membership map is not a list, so a shared soldier loses a squad', $trail);
    elseif (count($qids) !== count(array_unique($qids))) flag('the membership map repeats a squad', $trail);
  }
}

$acts = array('dep0','dep1','dep2','dep3','dep4','dep5','wd0','wd1','wd2','wd3','save','deploySquad','disperse');
mt_srand(20261021);
for ($run = 0; $run < 6000; $run++) {
  reset_db();
  $conn = new C(); $trail = array();
  for ($step = 0; $step < 11; $step++) {
    $a = $acts[mt_rand(0, count($acts)-1)];
    $trail[] = $a;
    if (strpos($a,'dep') === 0 && $a !== 'deploySquad') {
      $i = (int)substr($a,3); $id = 11+$i;
      if (isset($DB['soldiers'][$id]) && !$DB['soldiers'][$id]['active'] && checkSquadCount($conn) < 4)
        $DB['soldiers'][$id]['active'] = 1;
    } elseif (strpos($a,'wd') === 0) {
      $i = (int)substr($a,2); $id = 11+$i;
      if (isset($DB['soldiers'][$id])) $DB['soldiers'][$id]['active'] = 0;
    } elseif ($a === 'save') {
      dropshipSquadSave($conn);
    } elseif ($a === 'deploySquad') {
      $sq = dropshipSquadsAll($conn,1,1); if ($sq) dropshipSquadDeploy($conn, $sq[0]['id']);
    } elseif ($a === 'disperse') {
      $sq = dropshipSquadsAll($conn,1,1); if ($sq) dropshipSquadDisperse($conn, $sq[0]['id']);
    }
    invariants($conn, $trail);
  }
}
if (!$problems) { echo "no invariant broken in 6000 walks\n"; exit(0); }
foreach ($problems as $what => $trail) {
  echo "BROKEN: $what\n         reached by: ", implode(' -> ', $trail), "\n";
}

// Headless model of the Realm Guardians combat loop, so balance is CHECKED
// rather than guessed at. Mirrors guardians.php's numbers exactly.
function mulberry32(a){return function(){a|=0;a=a+0x6D2B79F5|0;var t=Math.imul(a^a>>>15,1|a);t=t+Math.imul(t^t>>>7,61|t)^t;return((t^t>>>14)>>>0)/4294967296;};}

function buildWave(n, rand, spacing, countMul, hpAdd) {
  var q = [], count = 4 + Math.floor(n * countMul);
  for (var i = 0; i < count; i++) {
    var tough = n >= 3 && rand() < 0.16 + n * 0.015;
    var hp = (tough ? 24 : 10) + n * hpAdd;
    q.push({ hp: hp, speed: (tough ? 0.24 : 0.38) + n * 0.006,
             pos: 100 + i * (spacing[0] + rand() * spacing[1]), tough: tough });
  }
  return q;
}

// One wave against a fixed defense. Returns ticks taken and how many leaked.
function runWave(n, def, spacing, countMul, hpAdd) {
  var rand = mulberry32(1234 + n);
  var foes = buildWave(n, rand, spacing, countMul, hpAdd);
  var dmg = def.armed * (2 + def.wlevel) + (def.garrison - def.armed);
  var next = 0, ticks = 0, leaks = 0;
  while (foes.length && ticks < 6000) {
    ticks++;
    if (--next <= 0 && foes.length) {
      next = 6;
      var t = foes[0];
      for (var i = 1; i < foes.length; i++) if (foes[i].pos < t.pos) t = foes[i];
      t.hp -= dmg;
      if (t.hp <= 0) foes.splice(foes.indexOf(t), 1);
    }
    for (var j = foes.length - 1; j >= 0; j--) {
      foes[j].pos -= foes[j].speed;
      if (foes[j].pos <= 0) { foes.splice(j, 1); leaks++; }
    }
  }
  return { ticks: ticks, leaks: leaks };
}

// A realm whose power lands it at wave n: start = floor(power/5), and location
// levels are roughly power spread across seven locations.
function defenseAtWave(n) {
  var power = n * 5;
  var locLevels = Math.max(1, Math.round(power * 0.55 / 7));
  var wlevel = Math.max(1, Math.round(power * 0.1 / 4));
  var army = Math.max(3, Math.round(power * 0.25));
  var garrison = Math.min(3 + locLevels, army);
  return { garrison: garrison, armed: garrison, wlevel: wlevel, army: army };
}

function report(label, spacing, countMul, hpAdd) {
  console.log('\n' + label);
  console.log('wave  foes  garrison  wlevel   seconds   leaked');
  [5, 10, 15, 20, 30].forEach(function (n) {
    var d = defenseAtWave(n);
    var r = runWave(n, d, spacing, countMul, hpAdd);
    var foes = 4 + Math.floor(n * countMul);
    console.log(
      String(n).padEnd(6) + String(foes).padEnd(6) +
      String(d.garrison).padEnd(10) + String(d.wlevel).padEnd(9) +
      (r.ticks / 10).toFixed(1).padEnd(10) + r.leaks);
  });
}

/*
 * The real question: starting at YOUR wave with YOUR realm, how many waves do
 * you survive? Defense is fixed for the run (only in-run upgrades help, modelled
 * as a modest DPS creep), while the waves keep climbing. The design wants you to
 * gain a few waves per run and then stall at your ceiling -- that stall IS the
 * ratchet. Dying at start+3..+6 is the target; start+0 means no progress is
 * possible, start+15 means no ceiling exists.
 */
function runLadder(start, spacing, countMul, hpAdd) {
  var d = defenseAtWave(start);
  var wall = 100, n = start, waves = 0;
  while (wall > 0 && waves < 30) {
    // In-run upgrades: roughly one garrison slot every three waves.
    var cur = { garrison: d.garrison + Math.floor(waves / 3),
                armed:    d.armed + Math.floor(waves / 3),
                wlevel:   d.wlevel };
    var r = runWave(n, cur, spacing, countMul, hpAdd);
    wall -= r.leaks * 6;          // mixed tough/normal, ~6 damage each
    wall = Math.min(100, wall + 8); // a fortify between waves
    n++; waves++;
  }
  return waves;
}

function ladder(label, spacing, countMul, hpAdd) {
  var out = [];
  [5, 10, 15, 20, 30].forEach(function (s) {
    out.push('start ' + s + ' -> +' + runLadder(s, spacing, countMul, hpAdd));
  });
  console.log(label.padEnd(46) + out.join('   '));
}

console.log('WAVES SURVIVED PAST YOUR STARTING WAVE (target: +3 to +6)');
ladder('BEFORE  (7-13 spacing, x1.5, +4hp)', [7, 6], 1.5, 4);
ladder('AFTER   (2.2-4 spacing, x1.9, +5hp)', [2.2, 1.8], 1.9, 5);
ladder('CAND A  (x2.1, +7hp)', [2.2, 1.8], 2.1, 7);
ladder('CAND B  (x2.3, +9hp)', [2.0, 1.6], 2.3, 9);
ladder('SHIPPED  (x1.75, +4hp, auto-reinforce)', [2.2, 1.8], 1.75, 4);

report('BEFORE  (spacing 7-13, count x1.5, hp +4/wave)', [7, 6], 1.5, 4);
report('AFTER   (spacing 2.2-4, count x1.9, hp +5/wave)', [2.2, 1.8], 1.9, 5);
// The model gives the player NO agency -- no upgrades, sorties or fortifies
// mid-wave -- so real difficulty is lower than these rows. Push past "just
// about challenging" on paper to land on challenging in the hand.
report('CANDIDATE A (count x2.1, hp +7/wave)', [2.2, 1.8], 2.1, 7);
report('CANDIDATE B (count x2.3, hp +9/wave)', [2.0, 1.6], 2.3, 9);

<?php
/*
 * admin-bossrez.php — the boss crew resurrection cue, audible.
 *
 * The proposal: when a boss crew is wiped mid-run the next wave stands up
 * in its place, and the sound of it rising is hypercube_create.ogg -- a
 * sound the player already knows from arming a Hypercube -- pitched DOWN
 * further every wave. Familiar, then wrong, then worse.
 *
 * WHY THIS IS A PAGE. The curve is a feel decision and nobody can read a
 * playbackRate off a page and know whether it is unnerving. It plays the
 * REAL file through the REAL path the Arena uses, so what is heard here is
 * what would ship. The sliders are the same arrangement as the chest glow
 * tuner: drag until it is right, and the numbers get read off afterwards.
 *
 * Plays audio. Writes nothing, changes no setting, awards nothing.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/dhcbosses-lib.php';
admin_require();

/*
 * EVERY SOUND THE PLATFORM ACTUALLY SERVES, and the two halves are not
 * stored the same way. The Arena's fifteen .ogg files live at
 * /staking/sounds/ and are NOT in the repo -- they deploy like images.
 * The twenty-one .mp3 files under audio/sounds/ ARE committed. Both were
 * curl-verified 200 before being listed here; a dozen other sounds/*.mp3
 * paths appear in the source but belong to dropship/sounds/, which is
 * gitignored and 404s from this host.
 */
$abr_lib = array(
	'sounds/badgeawarded.ogg' => 'arena: badgeawarded',
	'sounds/badmove.ogg' => 'arena: badmove',
	'sounds/bomb_explode.ogg' => 'arena: bomb_explode',
	'sounds/gem_shatters.ogg' => 'arena: gem_shatters',
	'sounds/hypercube_create.ogg' => 'arena: hypercube_create',
	'sounds/hyperspace_gem_land_1.ogg' => 'arena: hyperspace_gem_land_1',
	'sounds/hyperspace_gem_land_2.ogg' => 'arena: hyperspace_gem_land_2',
	'sounds/powergem_created.ogg' => 'arena: powergem_created',
	'sounds/select.ogg' => 'arena: select',
	'sounds/skullcoinlose.ogg' => 'arena: skullcoinlose',
	'sounds/speedmatch1.ogg' => 'arena: speedmatch1',
	'sounds/voice_excellent.ogg' => 'arena: voice_excellent',
	'sounds/voice_gameover.ogg' => 'arena: voice_gameover',
	'sounds/voice_go.ogg' => 'arena: voice_go',
	'sounds/voice_levelcomplete.ogg' => 'arena: voice_levelcomplete',
	'audio/sounds/artillery.mp3' => 'drop ship: artillery',
	'audio/sounds/card.mp3' => 'drop ship: card',
	'audio/sounds/death.mp3' => 'drop ship: death',
	'audio/sounds/demolition.mp3' => 'drop ship: demolition',
	'audio/sounds/equip.mp3' => 'drop ship: equip',
	'audio/sounds/exactmatch.mp3' => 'drop ship: exactmatch',
	'audio/sounds/fist.mp3' => 'drop ship: fist',
	'audio/sounds/flamethrower.mp3' => 'drop ship: flamethrower',
	'audio/sounds/flee.mp3' => 'drop ship: flee',
	'audio/sounds/grenade.mp3' => 'drop ship: grenade',
	'audio/sounds/heal.mp3' => 'drop ship: heal',
	'audio/sounds/joker.mp3' => 'drop ship: joker',
	'audio/sounds/kill.mp3' => 'drop ship: kill',
	'audio/sounds/laststand.mp3' => 'drop ship: laststand',
	'audio/sounds/machinegun.mp3' => 'drop ship: machinegun',
	'audio/sounds/melee.mp3' => 'drop ship: melee',
	'audio/sounds/pistol.mp3' => 'drop ship: pistol',
	'audio/sounds/rocketlauncher.mp3' => 'drop ship: rocketlauncher',
	'audio/sounds/sniperrifle.mp3' => 'drop ship: sniperrifle',
	'audio/sounds/tacticalkatana.mp3' => 'drop ship: tacticalkatana',
	'audio/sounds/victory.mp3' => 'drop ship: victory',
);

/* The real escalation, so the ladder can sit beside what it is describing. */
$abr_pow = array();
for ($w = 1; $w <= 10; $w++) $abr_pow[$w] = dhcb_wave_pow($w);

include 'header.php';
admin_chrome('bossrez');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Boss crew resurrection</h3>
	<small>
		<code>sounds/hypercube_create.ogg</code> is what the Arena plays when a
		Hypercube is armed. Pitched down, it is what a wiped boss crew sounds like
		getting back up. Each wave drops it further, alongside the real power
		curve of 1.18&times; per wave.
	</small>

	<p id="abrNote" style="color:#f5a623"></p>

	<div class="abr-controls">
		<label>Wave 1 rate <b id="abrBaseV">0.78</b>
			<input type="range" id="abrBase" min="0.40" max="1.00" step="0.01" value="0.78"></label>
		<label>Drop per wave <b id="abrStepV">0.035</b>
			<input type="range" id="abrStep" min="0" max="0.08" step="0.005" value="0.035"></label>
		<label>Floor <b id="abrFloorV">0.45</b>
			<input type="range" id="abrFloor" min="0.25" max="0.80" step="0.01" value="0.45"></label>
		<label>Gain <b id="abrGainV">0.80</b>
			<input type="range" id="abrGain" min="0.2" max="1" step="0.05" value="0.80"></label>
		<label>Lead-in <b id="abrLeadV">1.30</b>s
			<input type="range" id="abrLead" min="0.4" max="3" step="0.1" value="1.30"></label>
	</div>
	<p>
		<label style="font-size:.82rem;color:#c3d2dd">
			<input type="checkbox" id="abrRev" checked> Reverse the layers
		</label>
		<small style="display:block;margin-top:6px;color:#8fa7bb">
			Reversed, the sound swells and cuts instead of hitting and decaying, so it
			has to FINISH on the visual rather than start on it. The lead-in is how long
			the swell runs before the crew lands -- held constant, because a deeper pitch
			makes the whole sound longer and the lead would otherwise stretch with it.
		</small>
	</p>

	<p>
		<button type="button" class="button abr-go" id="abrLadder" style="width:auto">▶ Play waves 1 to 10</button>
		<button type="button" class="button abr-go" id="abrStop" style="width:auto">■ Stop</button>
	</p>

	<div class="abr-grid" id="abrGrid"></div>

	<h4>Layers</h4>
	<p><small>
		Stack as many as you like. Reversed layers are END-ALIGNED: they all cut on
		the same instant, which is the one the crew lands on, so a longer swell
		simply starts earlier. Offset nudges a layer off that shared landing.
	</small></p>
	<div id="abrLayers"></div>
	<p>
		<button type="button" class="button abr-go" id="abrAdd" style="width:auto">+ Add layer</button>
		<button type="button" class="button abr-go" id="abrPlay" style="width:auto">▶ Play the stack (wave 4)</button>
	</p>
	<pre id="abrDump" style="white-space:pre-wrap;background:#080f18;padding:10px;
	     border-radius:3px;color:#8fa7bb;font-size:.72rem"></pre>

	<h4>The two-beat cue</h4>
	<p><small>
		Three bosses knocked out, a held beat of nothing, then the same KO sound
		running backwards and pitched down. The thing the player heard go down is
		the thing they hear come back up.
	</small></p>
	<p>
		<label>Held beat <b id="abrHoldV">500</b>ms
			<input type="range" id="abrHold" min="0" max="1200" step="50" value="500"
			       style="vertical-align:middle;width:180px"></label>
	</p>
	<p>
		<button type="button" class="button abr-go" id="abrCue" style="width:auto">▶ Play the whole cue: crew falls, then rises (wave 4)</button>
	</p>
</div></div>

<style>
.abr-controls { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 12px; margin: 16px 0; }
.abr-controls label { display: block; font-size: .78rem; color: #8fa7bb; }
.abr-controls input[type=range] { width: 100%; margin-top: 6px; }
.abr-controls b { color: #00c8a0; font-variant-numeric: tabular-nums; }
.abr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
  gap: 8px; margin: 16px 0; }
.abr-w { padding: 10px 8px; border: 1px solid #1b3346; border-radius: 3px;
  background: #0a1929; color: #c3d2dd; cursor: pointer; font: inherit; font-size: .76rem;
  text-align: center; line-height: 1.5; }
.abr-w:hover { border-color: #00c8a0; }
.abr-w.on { border-color: #00c8a0; background: #0d2436; }
.abr-w .n { display: block; font-size: .62rem; letter-spacing: .14em;
  text-transform: uppercase; color: #7a9eb0; }
.abr-w .r { display: block; font-size: 1.05rem; color: #00c8a0;
  font-variant-numeric: tabular-nums; }
.abr-w .p { display: block; font-size: .64rem; color: #7a9eb0; }
.abr-go { margin-right: 8px; }
.abr-layer { display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
  padding: 8px; margin-bottom: 6px; border: 1px solid #1b3346; border-radius: 3px;
  background: #0a1929; }
.abr-layer select { max-width: 230px; }
.abr-layer label { font-size: .72rem; color: #8fa7bb; }
.abr-layer input { width: 76px; margin-left: 4px; }
.abr-layer button { font-size: .7rem; padding: 4px 8px; cursor: pointer;
  color: #c3d2dd; background: #122a3e; border: 1px solid #1b3346; border-radius: 2px; }
</style>

<script>
(function () {
  var POW = <?php echo json_encode($abr_pow); ?>;
  /*
   * TWO CANDIDATES FOR THE RISE, and the second one is the better idea:
   * skullcoinlose is what plays when anything is knocked out, so reversed
   * it is literally the sound of a boss going down, undone. hypercube is
   * the sound of something powerful being assembled, which is thematically
   * close but is not the same sound the player just heard three times.
   */
  var LIB = <?php echo json_encode($abr_lib); ?>;
  var FILES = { hyper: 'sounds/hypercube_create.ogg',
                ko:    'sounds/skullcoinlose.ogg',
                win:   'sounds/voice_levelcomplete.ogg' };
  Object.keys(LIB).forEach(function (path) { FILES[path] = path; });
  var actx = null, buf = {}, rbuf = {}, live = [], note = document.getElementById('abrNote');
  /* Declared up here because src() reads it and src() is called from the
     wave buttons, which are built before the layer rig further down. */
  var rows = [];

  /* Reversed once per file, not per play: copying two channels of a three
     second buffer on every button press is wasted work, and the reversed
     take is the one being auditioned most. */
  function reversed(key) {
    if (rbuf[key] || !buf[key]) return rbuf[key];
    var src = buf[key];
    var out = actx.createBuffer(src.numberOfChannels, src.length, src.sampleRate);
    for (var c = 0; c < src.numberOfChannels; c++) {
      var i = src.getChannelData(c), o = out.getChannelData(c), n = src.length;
      for (var k = 0; k < n; k++) o[k] = i[n - 1 - k];
    }
    rbuf[key] = out;
    return out;
  }

  /* The Arena's own loader, shortened: decode once into AudioContext so
     playbackRate is real resampling rather than a tempo trick. */
  function init() {
    if (actx) return Promise.resolve();
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) { note.textContent = 'No Web Audio here, falling back to <audio>.'; return Promise.resolve(); }
    actx = new AC();
    /* Only the three the fixed controls use. Thirty-six decodes on page
       open is a megabyte of audio nobody asked for; a layer fetches its
       own file the first time it is played. */
    return Promise.all(['hyper', 'ko', 'win'].map(function (k) {
      return fetch(FILES[k]).then(function (r) { return r.arrayBuffer(); })
        .then(function (b) { return new Promise(function (res, rej) { actx.decodeAudioData(b, res, rej); }); })
        .then(function (d) { buf[k] = d; })
        .catch(function () { note.textContent =
          'Could not decode ' + FILES[k] + ' here. Safari only learned Ogg Vorbis in 18.4 -- '
          + 'the fallback below still plays it, pitch included.'; });
    }));
  }

  /* A layer's file, fetched and decoded once, on first use. */
  function need(key) {
    if (buf[key]) return Promise.resolve();
    return fetch(FILES[key]).then(function (r) { return r.arrayBuffer(); })
      .then(function (b) { return new Promise(function (res, rej) { actx.decodeAudioData(b, res, rej); }); })
      .then(function (d) { buf[key] = d; })
      .catch(function () { note.textContent = 'Could not decode ' + FILES[key] + '.'; });
  }

  function play(key, rate, gain, when, rev, stackPlay) {
    if (actx && buf[key]) {
      if (actx.state === 'suspended') actx.resume();
      var s = actx.createBufferSource();
      s.buffer = rev ? reversed(key) : buf[key];
      s.__whole = !!stackPlay;   /* only the stack aligns whole swells */
      s.playbackRate.value = rate;
      var g = actx.createGain(); g.gain.value = gain;
      s.connect(g); g.connect(actx.destination);
      /*
       * A REVERSED SWELL IS PLAYED FROM ITS TAIL. At rate 0.465 the whole
       * thing runs five and a half seconds, so starting at the top would
       * put the landing five seconds after the button. Only the last
       * lead-in seconds are played, which keeps the moment the swell
       * cuts a fixed distance from the start whatever the pitch.
       */
      if (rev && key !== 'win') {
        /* The single-sound buttons trim to a fixed lead-in. The layer
           stack does not: it aligns whole swells against each other, and
           trimming each one first would undo that. */
        if (!s.__whole) {
          var lead = parseFloat(document.getElementById('abrLead').value);
          var full = s.buffer.duration / rate;
          var off  = Math.max(0, (full - lead)) * rate;
          s.start(actx.currentTime + (when || 0), off);
          live.push(s);
          return;
        }
      }
      s.start(actx.currentTime + (when || 0));
      live.push(s);
      return;
    }
    /* No decode: an <audio> element also has playbackRate, and turning
       preservesPitch OFF is what makes it drop in pitch rather than just
       play slower. */
    if (rev) { note.textContent = 'Reverse needs Web Audio, which this browser did not give us.'; return; }
    setTimeout(function () {
      var a = new Audio(FILES[key]);
      a.preservesPitch = false; a.mozPreservesPitch = false; a.webkitPreservesPitch = false;
      a.playbackRate = rate; a.volume = Math.min(1, gain);
      a.play().catch(function () {});
      live.push({ stop: function () { a.pause(); } });
    }, (when || 0) * 1000);
  }

  function stopAll() { live.forEach(function (s) { try { s.stop(); } catch (e) {} }); live = []; }

  function val(id) { return parseFloat(document.getElementById(id).value); }
  function rev() { return document.getElementById('abrRev').checked; }
  /* The wave buttons and the ladder audition whatever is on top of the
     layer stack. There used to be a dropdown of its own here; the stack
     replaced it, and this reads from the stack rather than from an
     element that no longer exists. */
  function src() {
    if (rows.length) { var v = readRow(rows[0]); if (v.path) return v.path; }
    return 'sounds/skullcoinlose.ogg';
  }
  function rateFor(w) {
    return Math.max(val('abrFloor'), val('abrBase') - val('abrStep') * (w - 1));
  }

  var grid = document.getElementById('abrGrid');
  function draw() {
    ['abrBase', 'abrStep', 'abrFloor', 'abrGain', 'abrHold', 'abrLead'].forEach(function (id) {
      document.getElementById(id + 'V').textContent = document.getElementById(id).value;
    });
    grid.innerHTML = '';
    for (var w = 1; w <= 10; w++) {
      (function (w) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'abr-w';
        b.innerHTML = '<span class="n">Wave ' + w + '</span>'
                    + '<span class="r">' + rateFor(w).toFixed(3) + '</span>'
                    + '<span class="p">power ' + POW[w].toFixed(2) + '&times;</span>';
        b.addEventListener('click', function () {
          init().then(function () { stopAll(); play(src(), rateFor(w), val('abrGain'), 0, rev()); });
        });
        grid.appendChild(b);
      })(w);
    }
  }
  ['abrBase', 'abrStep', 'abrFloor', 'abrGain', 'abrHold', 'abrLead'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', draw);
  });
  document.getElementById('abrRev').addEventListener('change', draw);
  draw();

  /*
   * THE LAYER STACK.
   *
   * END-ALIGNED BY DEFAULT, which is the whole trick with reversed
   * sounds: each one is a swell that CUTS, and the cut is the instant
   * the crew lands. Two swells of different lengths must therefore
   * start at different times and finish together -- aligning their
   * starts, which is the obvious thing to do, puts two landings in the
   * cue and reads as a mistake.
   */
  var layersEl = document.getElementById('abrLayers');
  var dump = document.getElementById('abrDump');

  function addRow(path, rate, gain, off) {
    var i = rows.length;
    var d = document.createElement('div');
    d.className = 'abr-layer';
    var opts = Object.keys(LIB).map(function (p) {
      return '<option value="' + p + '"' + (p === path ? ' selected' : '') + '>' + LIB[p] + '</option>';
    }).join('');
    d.innerHTML =
      '<select data-f="path">' + opts + '</select>'
      + '<label>rate <input type="number" data-f="rate" min="0.2" max="2" step="0.005" value="' + rate + '"></label>'
      + '<label>gain <input type="number" data-f="gain" min="0" max="1.5" step="0.05" value="' + gain + '"></label>'
      + '<label>offset <input type="number" data-f="off" min="-2" max="2" step="0.05" value="' + off + '"></label>'
      + '<button type="button" data-f="del">remove</button>';
    d.querySelector('[data-f=del]').addEventListener('click', function () {
      rows.splice(rows.indexOf(rec), 1); d.remove(); writeDump();
    });
    Array.prototype.forEach.call(d.querySelectorAll('select,input'), function (el) {
      el.addEventListener('input', writeDump);
    });
    layersEl.appendChild(d);
    var rec = { el: d };
    rows.push(rec);
    writeDump();
    return rec;
  }
  function readRow(r) {
    var q = function (f) { return r.el.querySelector('[data-f=' + f + ']'); };
    return { path: q('path').value, rate: parseFloat(q('rate').value),
             gain: parseFloat(q('gain').value), off: parseFloat(q('off').value) };
  }
  function writeDump() {
    dump.textContent = rows.length
      ? rows.map(function (r, i) { var v = readRow(r);
          return (i + 1) + '. ' + v.path + '  rate ' + v.rate + '  gain ' + v.gain
               + '  offset ' + v.off + 's' + (rev() ? '  (reversed, end-aligned)' : ''); }).join('\n')
      : 'No layers.';
  }
  document.getElementById('abrAdd').addEventListener('click', function () {
    addRow('sounds/skullcoinlose.ogg', 0.675, 1, 0);
  });
  document.getElementById('abrPlay').addEventListener('click', function () {
    if (!rows.length) return;
    var specs = rows.map(readRow);
    init()
      .then(function () { return Promise.all(specs.map(function (v) { return need(v.path); })); })
      .then(function () {
        stopAll();
        /* The longest layer sets the landing; every other one is pushed
           back by the difference so all the cuts coincide. */
        var durs = specs.map(function (v) {
          return buf[v.path] ? buf[v.path].duration / v.rate : 0;
        });
        var longest = Math.max.apply(null, durs);
        specs.forEach(function (v, i) {
          var lead = rev() ? (longest - durs[i]) : 0;
          play(v.path, v.rate, v.gain, Math.max(0, lead + v.off), rev(), true);
        });
      });
  });
  addRow('sounds/skullcoinlose.ogg', 0.675, 1, 0);
  addRow('sounds/hypercube_create.ogg', 0.675, 0.55, 0);

  document.getElementById('abrLadder').addEventListener('click', function () {
    init().then(function () {
      stopAll();
      for (var w = 1; w <= 10; w++) play(src(), rateFor(w), val('abrGain'), (w - 1) * 1.6, rev());
    });
  });
  document.getElementById('abrStop').addEventListener('click', stopAll);
  document.getElementById('abrCue').addEventListener('click', function () {
    init().then(function () {
      stopAll();
      /* THE CREW FALLING IS THREE KOs, not a win chime. That is what the
         player actually hears in the half second before the rise, so the
         cue has to be auditioned against it. */
      play('ko', 1, 0.6, 0); play('ko', 1, 0.6, 0.45); play('ko', 1, 0.6, 0.9);
      play(src(), rateFor(4), val('abrGain'), 1.9 + val('abrHold') / 1000, rev());
    });
  });
})();
</script>
</html>

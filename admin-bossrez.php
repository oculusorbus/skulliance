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
			<input type="checkbox" id="abrRev" checked> Reverse it
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

	<h4>The two-beat cue</h4>
	<p><small>
		Triumph, stillness, dread. The wipe keeps the sound it already has
		(<code>voice_levelcomplete</code>, today's win cue, which resurrection would
		otherwise orphan), then a held beat of nothing, then the rise.
	</small></p>
	<p>
		<label>Held beat <b id="abrHoldV">500</b>ms
			<input type="range" id="abrHold" min="0" max="1200" step="50" value="500"
			       style="vertical-align:middle;width:180px"></label>
	</p>
	<p>
		<button type="button" class="button abr-go" id="abrCue" style="width:auto">▶ Play the whole cue (wave 4)</button>
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
</style>

<script>
(function () {
  var POW = <?php echo json_encode($abr_pow); ?>;
  var FILES = { rise: 'sounds/hypercube_create.ogg', win: 'sounds/voice_levelcomplete.ogg' };
  var actx = null, buf = {}, rbuf = {}, live = [], note = document.getElementById('abrNote');

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
    return Promise.all(Object.keys(FILES).map(function (k) {
      return fetch(FILES[k]).then(function (r) { return r.arrayBuffer(); })
        .then(function (b) { return new Promise(function (res, rej) { actx.decodeAudioData(b, res, rej); }); })
        .then(function (d) { buf[k] = d; })
        .catch(function () { note.textContent =
          'Could not decode ' + FILES[k] + ' here. Safari only learned Ogg Vorbis in 18.4 -- '
          + 'the fallback below still plays it, pitch included.'; });
    }));
  }

  function play(key, rate, gain, when, rev) {
    if (actx && buf[key]) {
      if (actx.state === 'suspended') actx.resume();
      var s = actx.createBufferSource();
      s.buffer = rev ? reversed(key) : buf[key];
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
      if (rev) {
        var lead = parseFloat(document.getElementById('abrLead').value);
        var full = s.buffer.duration / rate;
        var off  = Math.max(0, (full - lead)) * rate;
        s.start(actx.currentTime + (when || 0), off);
        live.push(s);
        return;
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
          init().then(function () { stopAll(); play('rise', rateFor(w), val('abrGain'), 0, rev()); });
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

  document.getElementById('abrLadder').addEventListener('click', function () {
    init().then(function () {
      stopAll();
      for (var w = 1; w <= 10; w++) play('rise', rateFor(w), val('abrGain'), (w - 1) * 1.6, rev());
    });
  });
  document.getElementById('abrStop').addEventListener('click', stopAll);
  document.getElementById('abrCue').addEventListener('click', function () {
    init().then(function () {
      stopAll();
      play('win', 1, 0.75, 0);
      play('rise', rateFor(4), val('abrGain'), 1.1 + val('abrHold') / 1000, rev());
    });
  });
})();
</script>
</html>

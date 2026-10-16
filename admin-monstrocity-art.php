<?php
/*
 * The character art board for one theme.
 *
 * Fourteen rows, base and leader paired on each, laid out the way the owner
 * lays them out in a folder window when checking whether a set hangs
 * together. The battle-damaged column is generated, not uploaded.
 *
 * THREE THINGS THIS PAGE REFUSES TO DO:
 *
 *  1. BATCH ANYTHING. Every upload, flip and clear is its own request against
 *     its own slot. One failure costs that slot. There is no "save all", so
 *     there is no way to lose an hour to one bad file.
 *
 *  2. SEND THE ORIGINAL. A source NFT can be 40MB, which is larger than any
 *     sane post_max_size and is exactly how the last page lost a whole form.
 *     The browser resizes to the target width on a canvas first, so what
 *     leaves the machine is a couple of hundred KB and a timeout stops being
 *     a thing that can happen.
 *
 *  3. GUESS THE FLIP. Each slot prints the direction that character faces in
 *     the original game. The art is looked at and the button is pressed, or
 *     it is not. For a Random-facing collection there is nothing to guess
 *     from anyway.
 *
 * The theme's own `active` flag is the commit. Build it switched off, fill
 * the board, turn it on.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();
require_once __DIR__ . '/monstrocity-art-lib.php';

$theme_id = isset($_GET['theme']) ? (int)$_GET['theme'] : 0;
$res = $conn->query('SELECT * FROM monstrocity_themes WHERE id = ' . $theme_id);
$theme = $res ? $res->fetch_assoc() : null;

$chars = monstrocity_characters();

/* What is already on disk, and how well the generated damage reads. The score
   is computed here rather than on demand because fourteen of them is a
   fraction of a second and it is the one number worth seeing without asking. */
$state = array();
if ($theme) {
	$dir = __DIR__ . '/' . monstrocity_theme_dir($theme['slug']);
	foreach ($chars as $c) {
		$row = array('base' => '', 'leader' => '', 'damaged' => '', 'score' => -1);
		foreach (array('base' => 'base', 'leader' => 'leader', 'damaged' => 'battle-damaged') as $k => $f) {
			$p = $dir . '/' . $f . '/' . $c['slug'] . '.png';
			if (is_file($p)) $row[$k] = monstrocity_theme_dir($theme['slug'])
			                          . '/' . $f . '/' . $c['slug'] . '.png?v=' . filemtime($p);
		}
		if ($row['base'] && $row['damaged']) {
			$b = mart_load($dir . '/base/' . $c['slug'] . '.png');
			$d = mart_load($dir . '/battle-damaged/' . $c['slug'] . '.png');
			if ($b && $d) $row['score'] = round(mart_damage_score($b, $d), 1);
		}
		$state[$c['slug']] = $row;
	}
}

include 'header.php';
admin_chrome('monstrocity');
?>
<style>
.ma-head { display:flex; flex-wrap:wrap; gap:12px; align-items:baseline; margin:0 0 4px }
.ma-head h3 { margin:0; font-size:15px }
.ma-sub { font-size:11px; color:#5a7888; margin:0 0 14px; line-height:1.55; max-width:70ch }
.ma-back { font-size:12px; display:inline-block; margin:0 0 10px; text-align:left }
.ma-prog { font-family:ui-monospace,Menlo,monospace; font-size:11px; color:#7a9eb0 }
.ma-prog b { color:#00c8a0 }

.ma-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(440px,1fr)); gap:10px }
.ma-row { display:grid; grid-template-columns:92px 1fr 1fr 92px; gap:9px; align-items:start;
          background:#0a1929; border:1px solid rgba(0,200,160,.14); border-radius:5px; padding:9px 10px }
.ma-who { font-size:11px; color:#e8eaed; line-height:1.4; padding-top:2px }
.ma-who .faces { display:block; color:#7a9eb0; font-size:10px; margin-top:2px }
.ma-who .faces b { color:#f5a623 }
.ma-cell { position:relative }
.ma-cap { font-size:10px; color:#5a7888; text-align:left; margin:0 0 3px; letter-spacing:.03em; text-transform:uppercase }
.ma-drop { position:relative; aspect-ratio:1/1; border:1px dashed rgba(255,255,255,.18); border-radius:4px;
           background:#07111d; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer }
.ma-drop.over { border-color:#00c8a0; background:#09202c }
.ma-drop.busy { opacity:.45 }
.ma-drop img { width:100%; height:100%; object-fit:contain; display:block }
.ma-drop .ph { font-size:10px; color:#3d5566 }
.ma-drop input[type=file] { display:none }
.ma-tools { display:flex; gap:5px; margin-top:4px }
.ma-tools button { flex:1; font-size:10px; padding:3px 0; background:#10222f; color:#9fb4c2;
                   border:1px solid rgba(255,255,255,.14); border-radius:3px; cursor:pointer }
.ma-tools button:hover { border-color:#00c8a0; color:#00c8a0 }
.ma-dmg { aspect-ratio:1/1; border:1px solid rgba(255,255,255,.08); border-radius:4px; background:#07111d; overflow:hidden }
.ma-dmg img { width:100%; height:100%; object-fit:contain; display:block }
.ma-score { font-family:ui-monospace,Menlo,monospace; font-size:10px; text-align:center; margin-top:3px; color:#00c8a0 }
.ma-score.weak { color:#e0466b }
.ma-score.none { color:#3d5566 }
.ma-err { font-size:10px; color:#e0466b; margin-top:3px; min-height:12px; line-height:1.3 }
</style>

<?php if (!$theme): ?>
  <p class="adm-sub">No such theme. <a href="admin-monstrocity.php">Back to the list</a>.</p>
<?php else: ?>
  <a class="ma-back" href="admin-monstrocity.php">&larr; All themes</a>
  <div class="ma-head">
    <h3><?php echo htmlspecialchars($theme['project_name'] . ' &mdash; ' . $theme['title'], ENT_QUOTES); ?></h3>
    <span class="ma-prog" id="ma-prog"></span>
    <?php if (empty($theme['active'])): ?>
      <span class="mt-pill off" style="border:1px solid #f5a623;color:#f5a623;padding:1px 7px;border-radius:999px;font-size:10px">not live yet</span>
    <?php endif; ?>
  </div>
  <p class="ma-sub">
    Drag art from a folder window straight onto a square, one at a time (clicking opens a file dialog if you prefer). Each one saves on its own the moment it
    lands &mdash; nothing here is batched, so a file that fails costs that square and nothing else.
    Images are resized in the browser before they are sent, so the original never leaves your machine.
    <b>Battle&#8209;damaged is generated from the base</b> and regenerates whenever the base changes.
    <?php if (empty($theme['active'])): ?>
      This theme is switched off, so none of this is visible to players until you
      <a href="admin-monstrocity.php?edit=<?php echo (int)$theme['id']; ?>">mark it active</a>.
    <?php endif; ?>
  </p>

  <div class="ma-grid">
    <?php foreach ($chars as $c): $s = $state[$c['slug']]; ?>
      <div class="ma-row" data-char="<?php echo $c['slug']; ?>">
        <div class="ma-who">
          <?php echo htmlspecialchars($c['name']); ?>
          <span class="faces"><?php echo htmlspecialchars($c['size']); ?><br>faces <b><?php echo $c['faces']; ?></b></span>
        </div>
        <?php foreach (array('base' => 'Base', 'leader' => 'Leader') as $slot => $cap): ?>
          <div class="ma-cell">
            <p class="ma-cap"><?php echo $cap; ?></p>
            <label class="ma-drop" data-slot="<?php echo $slot; ?>">
              <input type="file" accept="image/*">
              <?php if ($s[$slot]): ?><img src="<?php echo htmlspecialchars($s[$slot]); ?>" alt="">
              <?php else: ?><span class="ph">drag art here</span><?php endif; ?>
            </label>
            <div class="ma-tools">
              <button type="button" data-act="flip" data-slot="<?php echo $slot; ?>">flip</button>
              <button type="button" data-act="clear" data-slot="<?php echo $slot; ?>">clear</button>
            </div>
            <p class="ma-err" data-slot="<?php echo $slot; ?>"></p>
          </div>
        <?php endforeach; ?>
        <div class="ma-cell">
          <p class="ma-cap">Damaged</p>
          <div class="ma-dmg">
            <?php if ($s['damaged']): ?><img src="<?php echo htmlspecialchars($s['damaged']); ?>" alt=""><?php endif; ?>
          </div>
          <?php
            $sc = $s['score'];
            $cls = $sc < 0 ? 'none' : ($sc < MONSTROCITY_DAMAGE_WEAK ? 'weak' : '');
          ?>
          <p class="ma-score <?php echo $cls; ?>" title="How visibly the damaged art differs from the base. Under <?php echo (int)MONSTROCITY_DAMAGE_WEAK; ?> reads as no change at all."><?php
            echo $sc < 0 ? '&mdash;' : ($sc < MONSTROCITY_DAMAGE_WEAK ? 'weak ' . $sc : $sc); ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<script>
(function () {
  var THEME = <?php echo (int)$theme['id']; ?>,
      WIDTH = <?php echo (int)MONSTROCITY_ART_WIDTH; ?>,
      WEAK  = <?php echo (float)MONSTROCITY_DAMAGE_WEAK; ?>;

  /*
   * RESIZE BEFORE SENDING. A source NFT runs to tens of megabytes, which is
   * larger than post_max_size and arrives as no POST at all -- the failure
   * that wiped the theme form. Downscaling here means a couple of hundred KB
   * goes over the wire and the request cannot time out.
   *
   * Halving in steps rather than one jump: a single large downscale samples
   * too few source pixels per output pixel and aliases on detailed art. The
   * cost is a few canvas draws on an image already in memory.
   */
  function shrink(file) {
    return new Promise(function (done, fail) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight;
        if (!w || !h) { fail('That file is not an image.'); return; }
        if (w <= WIDTH) { done(file); return; }          // nothing to do, send as-is
        var cw = w, ch = h, src = img;
        while (cw / 2 > WIDTH) {
          cw = Math.round(cw / 2); ch = Math.round(ch / 2);
          var step = document.createElement('canvas');
          step.width = cw; step.height = ch;
          step.getContext('2d').drawImage(src, 0, 0, cw, ch);
          src = step;
        }
        var out = document.createElement('canvas');
        out.width = WIDTH; out.height = Math.round(h * WIDTH / w);
        out.getContext('2d').drawImage(src, 0, 0, out.width, out.height);
        out.toBlob(function (b) { b ? done(b) : fail('The browser could not encode the resize.'); }, 'image/png');
      };
      img.onerror = function () { URL.revokeObjectURL(url); fail('That file is not an image.'); };
      img.src = url;
    });
  }

  function post(body) {
    body.append('theme_id', THEME);
    return fetch('ajax/monstrocity-art.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        try { return JSON.parse(t); }
        catch (e) { return { ok: false, why: 'The server answered with something that is not JSON.' }; }
      })
      .catch(function () { return { ok: false, why: 'The request did not reach the server.' }; });
  }

  function cellOf(row, slot) { return row.querySelector('.ma-drop[data-slot="' + slot + '"]'); }
  function errOf(row, slot)  { return row.querySelector('.ma-err[data-slot="' + slot + '"]'); }

  function paint(row, slot, url) {
    var cell = cellOf(row, slot);
    cell.innerHTML = '<input type="file" accept="image/*">'
      + (url ? '<img src="' + url + '" alt="">' : '<span class="ph">drag art here</span>');
    wireCell(row, cell, slot);
  }
  function paintDamaged(row, url) {
    var box = row.querySelector('.ma-dmg');
    box.innerHTML = url ? '<img src="' + url + '" alt="">' : '';
  }
  function setScore(row, score) {
    var el = row.querySelector('.ma-score');
    if (score === undefined || score === null || score < 0) { el.textContent = '—'; el.className = 'ma-score none'; return; }
    el.textContent = score < WEAK ? 'weak ' + score : score;
    el.className = 'ma-score' + (score < WEAK ? ' weak' : '');
    progress();
  }
  function refreshScore(row) {
    var b = new FormData();
    b.append('action', 'score'); b.append('slot', 'base'); b.append('char', row.dataset.char);
    post(b).then(function (r) { if (r.ok) setScore(row, r.score); });
  }

  function send(row, slot, file) {
    var cell = cellOf(row, slot), err = errOf(row, slot);
    err.textContent = ''; cell.classList.add('busy');
    shrink(file).then(function (blob) {
      var b = new FormData();
      b.append('action', 'upload'); b.append('slot', slot); b.append('char', row.dataset.char);
      b.append('image', blob, row.dataset.char + '.png');
      return post(b);
    }).then(function (r) {
      cell.classList.remove('busy');
      if (!r.ok) { err.textContent = r.why || 'Failed.'; return; }
      if (r.why) err.textContent = r.why;
      paint(row, slot, r.url);
      if (r.damaged) { paintDamaged(row, r.damaged); refreshScore(row); }
      progress();
    }, function (why) { cell.classList.remove('busy'); err.textContent = why; });
  }

  function wireCell(row, cell, slot) {
    var input = cell.querySelector('input[type=file]');
    input.addEventListener('change', function () { if (input.files[0]) send(row, slot, input.files[0]); });
    cell.addEventListener('dragover', function (e) { e.preventDefault(); cell.classList.add('over'); });
    cell.addEventListener('dragleave', function () { cell.classList.remove('over'); });
    cell.addEventListener('drop', function (e) {
      e.preventDefault(); cell.classList.remove('over');
      var f = e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) send(row, slot, f);
    });
  }

  function progress() {
    var rows = document.querySelectorAll('.ma-row'), b = 0, l = 0, weak = 0;
    rows.forEach(function (r) {
      if (r.querySelector('.ma-drop[data-slot=base] img')) b++;
      if (r.querySelector('.ma-drop[data-slot=leader] img')) l++;
      var s = r.querySelector('.ma-score');
      if (s && s.classList.contains('weak')) weak++;
    });
    var el = document.getElementById('ma-prog');
    el.innerHTML = 'base <b>' + b + '</b>/14 &middot; leader <b>' + l + '</b>/14'
                 + (weak ? ' &middot; <span style="color:#e0466b">' + weak + ' weak damage</span>' : '');
  }

  /*
   * A MISSED DROP MUST NOT TAKE THE PAGE WITH IT.
   *
   * The default action for an image dropped anywhere else in the window is to
   * navigate to it, which here means walking away from a half-filled board.
   * This workflow is dragging one file at a time out of a Finder window into
   * a small square, so missing is not a rare event.
   */
  window.addEventListener('dragover', function (e) { e.preventDefault(); });
  window.addEventListener('drop', function (e) {
    if (!e.target.closest('.ma-drop')) e.preventDefault();
  });

  document.querySelectorAll('.ma-row').forEach(function (row) {
    ['base', 'leader'].forEach(function (slot) { wireCell(row, cellOf(row, slot), slot); });
    row.querySelectorAll('button[data-act]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var slot = btn.dataset.slot, err = errOf(row, slot), cell = cellOf(row, slot);
        if (btn.dataset.act === 'clear' && !confirm('Clear the ' + slot + ' image for ' + row.dataset.char + '?')) return;
        err.textContent = ''; cell.classList.add('busy');
        var b = new FormData();
        b.append('action', btn.dataset.act); b.append('slot', slot); b.append('char', row.dataset.char);
        post(b).then(function (r) {
          cell.classList.remove('busy');
          if (!r.ok) { err.textContent = r.why || 'Failed.'; return; }
          paint(row, slot, r.url);
          if (slot === 'base') {
            paintDamaged(row, r.damaged || '');
            r.damaged ? refreshScore(row) : setScore(row, -1);
          }
          progress();
        });
      });
    });
  });
  progress();
})();
</script>
<?php endif; ?>
<?php include 'admin-css.php'; ?>

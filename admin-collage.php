<?php
/**
 * admin-collage.php — every face that has ever used Skulliance, in one image.
 *
 * A thank-you poster: one tile per account, drawn as a grid, downloaded as a
 * PNG and posted wherever. Nothing is stored and nothing is written.
 *
 * DRAWN IN THE BROWSER, for the same reasons admin-digest.php is and one of
 * its own: there is no TTF in this repo, so GD cannot write a username under
 * a tile; and 399 avatars fetched server-side is 399 sequential curls before
 * the first byte of the page, where the browser fetches them in parallel and
 * shows the grid filling in.
 *
 * THE CANVAS STAYS CLEAN BECAUSE DISCORD SENDS CORS. cdn.discordapp.com
 * answers with access-control-allow-origin: *, so an avatar drawn with
 * crossOrigin='anonymous' does not taint the canvas and toDataURL() still
 * works -- the same reason activity_digest_src() lets that one host through
 * where it drops every other remote URL. crossOrigin MUST be set BEFORE src
 * or the attribute is ignored and the export dies at the last step, which is
 * the worst possible place to find out.
 *
 * AVATARS ARE ALREADY PUBLIC. A username and avatar appear on the
 * leaderboards, the Collection and the realms map whatever a profile's
 * visibility is set to -- visibility gates HOLDINGS, not identity (see
 * dhcgallery.php's header and gallery.php:33). So the default is everyone.
 * The toggle exists anyway, because posting to an external platform is a
 * different context from showing something on a page somebody chose to open,
 * and that is the owner's call rather than mine.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';

/* ---- options, all in the query string so a layout can be linked ---- */
$cg_publiconly = !empty($_GET['publiconly']);
$cg_names      = !empty($_GET['names']);
$cg_sort       = (isset($_GET['sort']) && $_GET['sort'] === 'recent') ? 'recent' : 'oldest';

/*
 * FACES ONLY, NO SKULLS -- not an option, because an option here can only
 * make the poster worse. avatar_url() returns icons/skull.png for a row with
 * no hash, and that file is same-origin so it loads and draws perfectly: a
 * no-hash row is a skull on the wall, not a load failure the canvas can
 * detect. So it is excluded in SQL. The other skull source -- a hash that
 * exists but 404s -- is only discoverable in the browser, and is dropped
 * there. Between the two, every tile on the finished wall is a real face.
 */
$where = array("username <> ''", "avatar <> ''");
if ($cg_publiconly) $where[] = 'visibility = 2';

/* id ASC is join order -- the oldest accounts first, which is the story this
   poster is telling. last_login DESC answers a different question and is
   there because it is one line. */
$order = $cg_sort === 'recent' ? 'last_login DESC, id ASC' : 'id ASC';

$cg_users = array();
$res = $conn->query('SELECT id, username, discord_id, avatar FROM users WHERE '
                  . implode(' AND ', $where) . ' ORDER BY ' . $order);
if ($res) {
	while ($r = $res->fetch_assoc()) {
		$cg_users[] = array(
			'n' => $r['username'],
			/* Built here, not in JS: avatar_url() knows that an animated hash
			   is .gif and a still one .png. A row whose id or hash it refuses
			   comes back as the placeholder, which the draw step drops. */
			'a' => avatar_url($r['discord_id'], $r['avatar']),
			'd' => (string) $r['discord_id'],
		);
	}
}
$cg_total = count($cg_users);

include 'header.php';
admin_chrome('collage');
?>
<style>
.cg-opts { display:flex; flex-wrap:wrap; gap:14px; align-items:center; margin:0 0 14px }
.cg-opts label { display:flex; align-items:center; gap:6px; font-size:12px; cursor:pointer }
.cg-box { background:#050c14; border:1px solid rgba(0,200,160,.15); border-radius:4px; padding:10px }
#cg-canvas { width:100%; height:auto; display:block }
.cg-note { font-size:12px; opacity:.7; margin:10px 0 0 }
.cg-bad { color:#e0466b }
</style>

<p class="adm-sub">
  One tile per account, oldest first. <b><?php echo number_format($cg_total); ?></b>
  accounts have an avatar set; any whose picture no longer loads is left out of
  the wall rather than drawn as a skull. Nothing is saved — set the options,
  draw it, download it.
</p>

<form class="cg-opts" method="get">
  <label><input type="checkbox" name="publiconly" value="1" <?php echo $cg_publiconly ? 'checked' : ''; ?>>
         Public profiles only</label>
  <label><input type="checkbox" name="names" value="1" <?php echo $cg_names ? 'checked' : ''; ?>>
         Show usernames</label>
  <label>Order
    <select name="sort">
      <option value="oldest" <?php echo $cg_sort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
      <option value="recent" <?php echo $cg_sort === 'recent' ? 'selected' : ''; ?>>Recently active first</option>
    </select>
  </label>
  <button class="adm-btn" type="submit">Apply</button>
</form>

<p class="cg-opts">
  <button class="adm-btn" id="cg-draw" type="button">Draw</button>
  <a class="adm-btn" id="cg-dl" download="skulliance-avatar-wall.png" hidden>Download PNG</a>
  <span id="cg-status" class="cg-note"></span>
</p>

<div class="cg-box"><canvas id="cg-canvas" width="100" height="100"></canvas></div>
<p class="cg-note" id="cg-missing"></p>

<script>
(function () {
  var USERS   = <?php echo json_encode($cg_users, JSON_UNESCAPED_SLASHES); ?>;
  var SHOWNAM = <?php echo $cg_names ? 'true' : 'false'; ?>;
  var canvas  = document.getElementById('cg-canvas');
  var status  = document.getElementById('cg-status');
  var missing = document.getElementById('cg-missing');
  var dl      = document.getElementById('cg-dl');

  /* crossOrigin BEFORE src, every time. Set afterwards it is ignored, the
     image still draws, and the canvas is silently tainted -- which only
     surfaces at toDataURL(), after all 399 have loaded. */
  function load(src) {
    return new Promise(function (done) {
      var im = new Image();
      im.crossOrigin = 'anonymous';
      im.onload  = function () { done(im); };
      im.onerror = function () { done(null); };
      im.src = src;
    });
  }

  document.getElementById('cg-draw').addEventListener('click', function () {
    var n = USERS.length;
    if (!n) { status.textContent = 'No accounts match those options.'; return; }
    dl.hidden = true;
    status.textContent = 'Loading ' + n + ' avatars…';

    /*
     * LOAD FIRST, THEN SIZE THE GRID. A dead avatar hash is only discoverable
     * by trying it -- the URL is well formed and the row looks fine -- so the
     * count of tiles that will actually draw is not known until every image
     * has resolved. Laying out from USERS.length and substituting a skull
     * filled roughly a third of the wall with placeholders, which is not the
     * picture: this is a thank-you to people, not a report on data quality.
     */
    Promise.all(USERS.map(function (u) { return load(u.a); })).then(function (imgs) {
      var kept = [], failed = [];
      imgs.forEach(function (im, i) {
        if (im) kept.push({ im: im, u: USERS[i] });
        else    failed.push(USERS[i].n);
      });
      if (!kept.length) {
        status.textContent = 'None of the ' + n + ' avatars would load.';
        return;
      }

      /* A square-ish grid, so the poster works as a social card at any count. */
      var m = kept.length;
      var cols = Math.ceil(Math.sqrt(m));
      var rows = Math.ceil(m / cols);
      var TILE = 128, PAD = 6, NAME = SHOWNAM ? 18 : 0;
      var cellW = TILE + PAD, cellH = TILE + PAD + NAME;
      var W = cols * cellW + PAD, H = rows * cellH + PAD;

      canvas.width = W; canvas.height = H;
      var c = canvas.getContext('2d');
      c.fillStyle = '#050c14';
      c.fillRect(0, 0, W, H);

      kept.forEach(function (k, i) {
        var x = PAD + (i % cols) * cellW, y = PAD + Math.floor(i / cols) * cellH;
        c.save();
        c.beginPath();
        c.arc(x + TILE / 2, y + TILE / 2, TILE / 2, 0, Math.PI * 2);
        c.closePath(); c.clip();
        c.drawImage(k.im, x, y, TILE, TILE);
        c.restore();
        if (SHOWNAM) {
          c.fillStyle = '#7a9eb0';
          c.font = '11px system-ui, sans-serif';
          c.textAlign = 'center';
          var nm = k.u.n;
          if (nm.length > 16) nm = nm.slice(0, 15) + '…';
          c.fillText(nm, x + TILE / 2, y + TILE + 13);
        }
      });
      status.textContent = m + ' avatars drawn in a ' + cols + '\u00d7' + rows + ' grid.';
      missing.innerHTML = failed.length
        ? '<span class="cg-bad">' + failed.length + ' left out because their avatar would '
          + 'not load: ' + failed.slice(0, 12).join(', ')
          + (failed.length > 12 ? '…' : '') + '. Those hashes are stale; opening a page they '
          + 'appear on heals them, then draw again to include them.</span>'
        : '';
      /* The export is the moment a tainted canvas reveals itself. */
      try {
        dl.href = canvas.toDataURL('image/png');
        dl.hidden = false;
      } catch (e) {
        status.textContent = 'Drawn, but the canvas is tainted so it cannot be exported: '
                           + e.message;
      }
    });
  });
})();
</script>
<?php /* admin_chrome() opens the .adm wrapper but does not carry its styling,
         so without this the page renders as unstyled text against the left
         edge -- the same note admin-digest.php ends on. */ ?>
<?php include 'admin-css.php'; ?>

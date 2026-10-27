<?php
/**
 * admin-outreach.php — the post that goes back into a partner's Discord.
 *
 * WHY IT EXISTS. Most of these projects announced Skulliance when staking
 * was most of what it did, and communities turn over. The pitch now is not
 * "come and stake", it is "you already staked, and there is a great deal
 * more here than there was" -- which is only persuasive with the
 * community's OWN numbers in front of it. Pairs with admin-flyers.php: the
 * text here, the image there.
 *
 * READ ONLY, AND IT SENDS NOTHING. It builds text to copy; the owner reads
 * it, edits it and pastes it himself. There is no webhook and no POST, so
 * nothing can go out of here by accident into somebody else's server --
 * which is also why admin-harness.php's write-path checks do not apply.
 *
 * THE NUMBERS ARE THE RISK, NOT THE WORDING. This post carries another
 * project's name into their own chat, where the readers are the people
 * best placed to notice a wrong figure. lib/outreach-stats.php therefore
 * reuses the exact query shapes the platform already counts these things
 * with, returns null for a failed read rather than 0, and the post simply
 * leaves out anything that is nought or unknown -- a claim is made only
 * when it is both true and worth making.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/lib/outreach-stats.php';

$OUT_PROJECTS = adm_projects($conn);
$out_id = isset($_GET['project']) ? (int)$_GET['project'] : 0;
if ($out_id && !isset($OUT_PROJECTS[$out_id])) $out_id = 0;
$out_p = $out_id ? $OUT_PROJECTS[$out_id] : null;
$out_stats = $out_id ? outreach_stats($conn, $out_id) : array();

/*
 * WHAT IS HERE NOW, READ OFF THE LAUNCHPAD RATHER THAN TYPED.
 *
 * launchpad.php's Play section is the maintained answer to "what can you
 * do on this platform" -- it is the first page after login and it is kept
 * current because players use it. A second hand-written list here would be
 * wrong within a month, and wrong in the direction that matters: a post
 * that omits the game somebody would have come back for.
 *
 * THE ARRAY, NOT THE PAGE. The first cut included launchpad.php itself
 * with output buffered -- which would have re-run db.php, verify.php and
 * header.php inside an admin page that had already drawn its own. The list
 * now lives in lib/launchpad-catalogue.php, which holds nothing but the
 * array, and launchpad.php requires it too: one list, one home, the same
 * arrangement admin_tabs() has.
 */
require_once __DIR__ . '/lib/launchpad-catalogue.php';
$OUT_GAMES = array();
if (isset($lp_sections['Play']['items']))
	foreach ($lp_sections['Play']['items'] as $it)
		if (is_array($it) && count($it) >= 4)
			$OUT_GAMES[] = array('emoji' => $it[1], 'name' => $it[2], 'blurb' => $it[3]);

include 'header.php';   // GLOBAL scope: header.php reads $name and $avatar_url
admin_chrome('outreach');
?>
<?php include 'admin-css.php'; ?>
<style>
.ou-wrap { display:grid; grid-template-columns:minmax(0,340px) minmax(0,1fr); gap:16px; align-items:start; }
@media (max-width:900px){ .ou-wrap { grid-template-columns:1fr; } }
.ou-stats { list-style:none; margin:0; padding:0; }
.ou-stats li { display:flex; justify-content:space-between; gap:12px; padding:7px 0;
  border-top:1px solid rgba(255,255,255,.06); font-size:.84rem; }
.ou-stats li:first-child { border-top:0; }
.ou-stats .k { color:#8fa8b8; }
.ou-stats .v { font-variant-numeric:tabular-nums; color:#e8eaed; font-weight:600; }
.ou-stats .v.zero { color:#5f7d90; font-weight:normal; }
.ou-stats .v.bad { color:#d69e2e; font-weight:normal; }
.ou-pick { display:flex; flex-direction:column; gap:5px; margin:0; }
/* flex-direction:row is NOT redundant: .adm-form label is a COLUMN, which
   put every checkbox on its own line above its own label. Same reason
   .adm-inline exists; this one needs flex-start rather than centre because
   the label runs to two lines. */
.ou-pick label { display:flex; flex-direction:row; align-items:flex-start; gap:8px;
  font-size:.8rem; color:#8fa8b8; text-transform:none; letter-spacing:0; cursor:pointer; }
.ou-pick input { width:auto; margin-top:2px; flex:0 0 auto; }
.ou-pick b { color:#e8eaed; font-weight:600; }
.ou-pick small { display:block; color:#5f7d90; }
#ou-text { width:100%; min-height:460px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  font-size:.8rem; line-height:1.55; resize:vertical; }
.ou-bar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:10px; }
.ou-count { font-variant-numeric:tabular-nums; font-size:.8rem; color:#8fa8b8; }
.ou-count.over { color:#e05a5a; font-weight:600; }
.ou-count b { color:#00c8a0; }
.ou-count.over b { color:#e05a5a; }
</style>

<p class="adm-note">Builds the &ldquo;there is a lot more here now&rdquo; post for a partner&rsquo;s own Discord,
  with that community&rsquo;s real numbers in it. Nothing is sent &mdash; copy it, read it, paste it.
  Pair it with the flyer from the <a href="admin-flyers.php">Flyers</a> tab.</p>

<?php /*
 * A REAL GET FORM, NOT A SELECT WIRED UP BY SCRIPT.
 *
 * It was a bare <select> whose change handler lived in the page's one
 * <script> -- which sits inside the `else` below, so it only existed once
 * a project had ALREADY been chosen. Picking the first one did nothing at
 * all, which is the only state the control is ever used in. Reported
 * immediately, and fairly.
 *
 * A form navigates on its own, so the control cannot be left dead by where
 * a script happens to sit. The script below only removes the button,
 * because with it the select submits itself.
 */ ?>
<div class="adm-card">
  <form class="adm-form" method="get" action="admin-outreach.php" id="ou-form">
    <label>Project
      <select name="project" id="ou-project">
        <option value="0">&mdash; pick a project &mdash;</option>
        <?php foreach ($OUT_PROJECTS as $id => $p): ?>
        <option value="<?php echo (int)$id; ?>"<?php echo $id === $out_id ? ' selected' : ''; ?>><?php
          echo htmlspecialchars($p['name'] . ' (' . $p['currency'] . ')'); ?></option>
        <?php endforeach; ?>
      </select>
      <small>Reloads: the figures below are read from the database for this project.</small></label>
    <button type="submit" class="adm-btn" id="ou-go">Read the numbers</button>
  </form>
</div>
<script>
/* ALWAYS RENDERED, unlike the one at the foot of this page. */
(function () {
	var f = document.getElementById('ou-form'),
	    s = document.getElementById('ou-project'),
	    g = document.getElementById('ou-go');
	if (!f || !s) return;
	if (g) g.hidden = true;
	s.addEventListener('change', function () { f.submit(); });
}());
</script>

<?php if (!$out_p): ?>
  <p class="adm-note">Pick a project to read its numbers.</p>
<?php else: ?>

<div class="ou-wrap">
  <div>
    <div class="adm-card">
      <h3><?php echo htmlspecialchars($out_p['name']); ?></h3>
      <?php /* EVERY FIGURE, INCLUDING THE ONES THE POST WILL DROP. The post
               leaves out noughts because "0 raids won" argues against
               turning up -- but the operator still has to see that it is a
               nought and not a broken read, so this list shows all six and
               says which is which. */ ?>
      <ul class="ou-stats">
        <?php foreach ($out_stats as $k => $s): ?>
        <li>
          <span class="k"><?php echo htmlspecialchars($s['label']); ?></span>
          <?php if ($s['value'] === null): ?>
            <span class="v bad" title="The query failed. Nothing is claimed.">could not read</span>
          <?php elseif ($s['value'] <= 0): ?>
            <span class="v zero" title="Left out of the post.">0 &middot; left out</span>
          <?php else: ?>
            <span class="v"><?php echo number_format($s['value']); ?></span>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="adm-note" style="margin-top:10px">Ticker <b><?php
        echo htmlspecialchars($out_p['currency']); ?></b> &middot;
        <a href="admin-flyers.php?project=<?php echo (int)$out_id; ?>">build the flyer</a></p>
    </div>

    <div class="adm-card">
      <h3>Mention</h3>
      <div class="ou-pick" id="ou-games">
        <?php /* ALL OF THEM, BY DEFAULT. The whole Play list comes to about
                 1,430 characters against Discord's 2,000, so there is no
                 budget reason to start with a subset -- and taking one out
                 is a decision the operator can see the cost of, where
                 putting one back means first noticing it is missing.

                 data-n IS THE NAME, AND IT IS WHAT THE SAVED SELECTION IS
                 KEYED ON. data-g is a position in the catalogue, and the
                 catalogue gains and loses entries -- key the save on that
                 and adding one game silently shifts every stored choice
                 onto a different game. */ ?>
        <?php foreach ($OUT_GAMES as $i => $g): ?>
        <label><input type="checkbox" data-g="<?php echo (int)$i; ?>"
          data-n="<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>" checked>
          <span><b><?php echo htmlspecialchars($g['emoji'] . ' ' . $g['name']); ?></b>
          <small><?php echo htmlspecialchars($g['blurb']); ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <?php if (!$OUT_GAMES): ?>
        <p class="adm-note"><span class="warn">The launchpad catalogue could not be read,
          so there is nothing to list here.</span></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="adm-card">
    <h3>The post</h3>
    <div class="adm-form">
      <label>Opening
        <input type="text" id="ou-open" value="Your <?php
          echo htmlspecialchars($out_p['currency']); ?> has been earning this whole time, and the platform around it has grown a long way past staking."></label>
      <label>Closing
        <input type="text" id="ou-close" value="Nothing to set up again. Sign in with the same wallet and everything is where you left it."></label>
    </div>
    <?php /* THE CLAIMS ARE THE OWNER'S, THE FIGURES ARE THE DATABASE'S.
             The two sentences above are editable because they are the only
             parts of this post that assert anything the queries cannot
             check -- and this page must not put an unverifiable sentence
             into somebody else's chat under their project's name. */ ?>
    <textarea id="ou-text" spellcheck="false"></textarea>
    <div class="ou-bar">
      <button type="button" class="adm-btn" id="ou-copy">Copy</button>
      <span class="ou-count" id="ou-count"></span>
      <span class="adm-note" style="margin:0">Discord caps a non-Nitro message at 2,000.</span>
    </div>
  </div>
</div>

<script>
(function () {
	var P = <?php echo json_encode(array(
		'name' => $out_p['name'], 'currency' => $out_p['currency'],
		'lines' => outreach_lines($out_stats, $out_p['currency']),
		'games' => $OUT_GAMES,
	), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
	var LIMIT = 2000;
	var $ = function (id) { return document.getElementById(id); };

	function build() {
		var picked = [];
		$('ou-games').querySelectorAll('input:checked').forEach(function (c) {
			var g = P.games[+c.getAttribute('data-g')];
			if (g) picked.push(g.emoji + '  **' + g.name + '** — ' + g.blurb);
		});
		var t = '**' + P.name + ' is still live on Skulliance**\n\n' + $('ou-open').value + '\n';
		/* The community's own numbers first. They are the only part of this
		   a reader cannot dismiss, and the post is weaker without them. */
		if (P.lines.length) {
			t += '\n**What ' + P.name + ' has done here so far**\n';
			P.lines.forEach(function (l) { t += '• ' + l + '\n'; });
		}
		if (picked.length) {
			t += '\n**What is here now**\n' + picked.join('\n') + '\n';
		}
		t += '\n' + $('ou-close').value
		   + '\n\nhttps://www.skulliance.io/staking/';
		return t;
	}
	function paint(reset) {
		if (reset) $('ou-text').value = build();
		var n = $('ou-text').value.length, el = $('ou-count');
		el.className = 'ou-count' + (n > LIMIT ? ' over' : '');
		el.innerHTML = '<b>' + n + '</b> / ' + LIMIT
		             + (n > LIMIT ? ' — ' + (n - LIMIT) + ' over' : '');
	}

	/* The project control is a form and wires itself, above -- see the note
	   there. Doing it again here would navigate twice. */
	/* REBUILT ON A TOGGLE, RECOUNTED ON A TYPE. Rebuilding while somebody
	   is editing the box would throw their edit away mid-sentence. */
	/*
	 * THE SELECTION SURVIVES A PROJECT CHANGE, which is a full page load.
	 *
	 * WHAT IS STORED IS THE UNTICKED SET, not the ticked one. Everything
	 * is on by default, so the short list is the exceptions -- and a game
	 * added to the catalogue tomorrow is then ticked for everybody rather
	 * than silently missing from every post because it was absent from a
	 * saved list written before it existed. That is the failure that would
	 * never be noticed: the post simply omits the newest thing, which is
	 * the thing most worth mentioning.
	 *
	 * BY NAME, NOT POSITION, for the same reason.
	 *
	 * localStorage is per-browser and can throw outright -- private
	 * windows, blocked site data -- so every access is wrapped and the
	 * page works untouched when it fails. Nothing here is worth an error.
	 */
	var SKIP = 'skl-outreach-unticked';
	function readSkip() {
		try { return JSON.parse(localStorage.getItem(SKIP) || '[]') || []; }
		catch (e) { return []; }
	}
	function saveSkip() {
		var off = [];
		$('ou-games').querySelectorAll('input[type=checkbox]').forEach(function (c) {
			if (!c.checked) off.push(c.getAttribute('data-n'));
		});
		try { localStorage.setItem(SKIP, JSON.stringify(off)); } catch (e) {}
	}
	(function () {
		var off = readSkip();
		if (!off.length) return;
		$('ou-games').querySelectorAll('input[type=checkbox]').forEach(function (c) {
			if (off.indexOf(c.getAttribute('data-n')) !== -1) c.checked = false;
		});
	}());

	$('ou-games').addEventListener('change', function () { saveSkip(); paint(true); });
	$('ou-open').addEventListener('input', function () { paint(true); });
	$('ou-close').addEventListener('input', function () { paint(true); });
	$('ou-text').addEventListener('input', function () { paint(false); });
	$('ou-copy').addEventListener('click', function () {
		var el = $('ou-text');
		el.select(); el.setSelectionRange(0, el.value.length);
		var done = function () { $('ou-copy').textContent = 'Copied';
			setTimeout(function () { $('ou-copy').textContent = 'Copy'; }, 1400); };
		if (navigator.clipboard) navigator.clipboard.writeText(el.value).then(done, function () {});
		else { try { document.execCommand('copy'); done(); } catch (e) {} }
	});
	paint(true);
}());
</script>
<?php endif; ?>

<?php /* admin_chrome() opens two divs and every admin page closes them the
         same way; there is no closer function. */ ?>
</div></div>

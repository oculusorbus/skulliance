<?php
/*
 * infiltration.php -- reply to our posts on X, climb a rarity ladder.
 *
 * NOT PUBLIC. Behind the Discord login like every other member page, and
 * deliberately absent from the Skull Paper, from every marketing page and
 * from dhc-nav.php -- that strip is included on the PUBLIC Arena page, so a
 * link there would put this in front of anonymous visitors. See
 * infiltration-design.md.
 *
 * THE MEMBER NEVER SEES A NUMBER THEY CANNOT ACT ON. The meter is the whole
 * interface: ten rungs, where they stand, what the next reply is worth. The
 * odds table behind each rung is the platform's own, so the ladder means the
 * same thing here as it does everywhere else traits drop.
 */
include_once 'db.php';
include 'message.php';
include 'skulliance.php';
/*
 * WEBHOOKS, EXPLICITLY, AND NOTHING ELSE LOADS IT.
 *
 * db.php does not include it and neither does skulliance.php, so without
 * this line skl_discordmsg() simply does not exist -- and every announce
 * in lib/infiltration-lib.php is guarded by function_exists(), which is
 * correct (a missing Discord post must never break an award) and is also
 * why the failure is SILENT. Targets were added and infiltrations were
 * paid with nothing ever reaching the channel, and the admin Health card
 * reported "no webhook" because getInfiltrationsWebhook() lives in the
 * credentials file that webhooks.php pulls in.
 *
 * include_once, not include: the file declares its functions at top level
 * and a second include is a fatal redeclare.
 */
include_once 'webhooks.php';
require_once __DIR__ . '/dhcfighters-config.php';
require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/lib/infiltration-lib.php';

$inf_uid = (int) ($_SESSION['userData']['user_id'] ?? 0);

/* Tables first: everything below reads them, and the owner installs from
   the admin page. Nothing here creates anything. */
$inf_ready = $inf_uid > 0 ? (count(infil_tables_missing($conn)) === 0) : false;

/*
 * MISSES ARE SETTLED ON PAGE LOAD because nothing in this repo can schedule
 * anything (see rewards.php, which is fifteen crontab lines in a trench
 * coat). One indexed lookup that returns no rows on almost every call, and
 * idempotent -- `settled` is claimed with a conditional UPDATE so two
 * concurrent loads cannot dock the same member twice.
 */
if ($inf_ready) infil_settle_due($conn);

$inf_flash = '';
$inf_bad   = '';
$inf_drop  = null;

/* Redirect after POST, so a refresh cannot re-submit a comment. */
if ($inf_ready && $inf_uid > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {

	if (isset($_POST['link_x'])) {
		$r = infil_link($conn, $inf_uid, (string) $_POST['comment']);
		if ($r['state'] === INFIL_OK) {
			$_SESSION['inf_flash'] = 'Connected as @' . $r['handle'] . '.';
		} else {
			$_SESSION['inf_bad'] = $r['why'];
		}

	} else if (isset($_POST['new_code'])) {
		infil_code($conn, $inf_uid, true);
		$_SESSION['inf_flash'] = 'New code issued.';

	} else if (isset($_POST['infiltrate'])) {
		$res = infil_submit($conn, $inf_uid, (int) $_POST['target'], (string) $_POST['comment']);
		if ($res['state'] === INFIL_OK) {
			/* The announce is deferred inside skl_discordmsg(), so the member
			   is not waiting on Discord to see their own trait. */
			infil_announce($conn, $res);
			$_SESSION['inf_flash'] = 'Infiltration confirmed.'
			  . ($res['tiers'] > 0 ? ' Up ' . (int) $res['tiers'] . ' rung'
			                       . ($res['tiers'] === 1 ? '' : 's') . '.' : '');
			if (is_array($res['trait'])) $_SESSION['inf_drop'] = $res['trait'];
		} else if ($res['state'] === INFIL_PENDING) {
			/* NOT AN ERROR, and it must not read as one. */
			$_SESSION['inf_flash'] = $res['why'];
		} else {
			$_SESSION['inf_bad'] = $res['why'];
		}
	}
	header('Location: infiltration.php');
	exit;
}

/* Read once and clear, so a refresh does not repeat the message. */
if (isset($_SESSION['inf_flash'])) { $inf_flash = (string) $_SESSION['inf_flash']; unset($_SESSION['inf_flash']); }
if (isset($_SESSION['inf_bad']))   { $inf_bad   = (string) $_SESSION['inf_bad'];   unset($_SESSION['inf_bad']); }
if (isset($_SESSION['inf_drop']))  { $inf_drop  = $_SESSION['inf_drop'];           unset($_SESSION['inf_drop']); }

$inf_handle = $inf_ready ? infil_handle($conn, $inf_uid) : '';
$inf_linked = $inf_ready && infil_uid($conn, $inf_uid) !== '';
$inf_meter  = $inf_ready ? infil_meter($conn, $inf_uid) : array('tier' => 0, 'bank' => 0, 'month_tiers' => 0);
$inf_board  = ($inf_ready && $inf_linked) ? infil_board($conn, $inf_uid) : array();
$inf_top    = $inf_ready ? infil_leaderboard($conn, 15) : array();
$inf_code   = ($inf_ready && !$inf_linked) ? infil_code($conn, $inf_uid) : '';
$inf_labels = dhcf_table_labels();

include 'header.php';
?>
<style>
/* Scoped under .inf so nothing here can reach the rest of the platform. */
/* min-width:0 BECAUSE THE COLUMN IS A FLEX ITEM. A flex item defaults to
   min-width:auto and will not shrink below its content's min-content width,
   so one wide child drags the whole column past the viewport and the PAGE
   scrolls sideways. Measured at 601px against a 485px viewport. */
.inf { color:#c8d8e8; font:400 15px/1.6 Arial,Helvetica,sans-serif; text-align:left;
  min-width:0; max-width:100%; }
.inf h2,.inf h3,.inf h4 { text-align:left; margin:0; }

/* ---- the banner ----------------------------------------------------- */
/* ONE FILE, CROPPED BY CSS, the same way .lp-stage-art handles the
   launchpad hero: the art is 4:3 and every context wants a different slice
   of it, so the box sets the shape and background-position picks the crop.
   Re-cropping in an editor would mean a new upload every time a ratio
   changes. The colour under it is not decoration -- it is what shows if the
   file is not on the server yet, so the page never renders as a white gap. */
/* THE HEIGHT IS 1600/520 AND IT STAYS THERE. It was briefly 820, because
   the framing I had chosen at the time cut the structure in half and a
   taller band fixed it -- which was solving my own bad crop by moving
   something the owner had already set. 90% frames it properly at this
   height, so there is nothing left for the ratio to fix.

   90% IS THE OWNER'S OWN FIGURE, found against the real art in a browser,
   the same way the -180px on .lp-stage-art was.

   A PERCENTAGE, NOT A PIXEL OFFSET, so the crop holds at every width: the
   box is a ratio of the viewport, so its height tracks its width, and a
   fixed offset would walk down the picture as the window narrows. That is
   the mistake .lp-stage-art records having made. */
/*
 * NO min-height, AND THAT IS THE WHOLE MOBILE BUG.
 *
 * This carried min-height:190px. On a box with aspect-ratio 1600/520 the
 * ratio runs BOTH ways, so a 190px floor on the height is a 585px floor on
 * the WIDTH (190 x 1600/520) -- which the flex column above then refused to
 * shrink below, and the entire page scrolled sideways on every phone.
 * Measured, not guessed: .inf-hero demanded 585px of min-content.
 *
 * .lp-stage-art records making exactly this mistake in the other direction.
 * The ratio is the only thing that should size this box.
 */
.inf-hero { position:relative; width:100%; aspect-ratio: 1600/520;
  background:#0a0a14 url(images/infiltration/hero.webp) 50% 90% / cover no-repeat;
  border:1px solid rgba(110,74,168,.4); display:flex; align-items:flex-end;
  min-width:0; }
/* The scrim exists so the title reads over whatever is behind it. Starting
   at 62% rather than the usual 38% keeps it off the foreground skeletons,
   which at 90% are most of the band. If the title turns out to be hard to
   read against them, this percentage is the dial -- not the height. */
.inf-hero::after { content:''; position:absolute; inset:0;
  background:linear-gradient(180deg, rgba(8,6,16,0) 62%, rgba(8,6,16,.9) 100%); }
/* A 1600px file on a 400px screen is four times the pixels anyone sees.
   900px is the breakpoint because below it the band is never wider than
   that, so the 800px file is still over-sampled rather than soft. */
@media (max-width:900px){
  .inf-hero { background-image:url(images/infiltration/hero-800.webp); }
}
.inf-hero-in { position:relative; z-index:1; padding:20px 24px; width:100%; }
.inf-hero h2 { font-size:1.6rem; letter-spacing:.14em; text-transform:uppercase;
  color:#e8e2f8; text-shadow:0 2px 14px rgba(0,0,0,.9); }
.inf-hero p { margin:6px 0 0; color:#b9aed6; font-size:.92rem; max-width:none;
  text-shadow:0 1px 8px rgba(0,0,0,.9); }

/* ---- the meter ------------------------------------------------------ */
.inf-meter { background:#0a1929; border:1px solid rgba(110,74,168,.3);
  padding:18px 20px; margin:18px 0; }
/* Nine across, each one sized by the track rather than by itself, so the
   row stays even however different the badges' own proportions are. */
.inf-ladder { display:grid; grid-template-columns:repeat(9,1fr); gap:10px;
  margin:16px 0 4px; align-items:end; }
.inf-ladder.noart { display:none; }
.inf-rcell { text-align:center; min-width:0; }
.inf-rcell img { display:block; width:100%; height:auto; margin:0 auto;
  /* Unearned rungs keep their SHAPE and lose their colour. Seeing what is
     coming is the point of showing all nine.
     NOT DARKENED, which the first attempt did: these badges are dark metal
     on a dark card, so brightness(.4) at opacity(.45) erased them into the
     panel. Compared side by side, a slight LIFT with the saturation gone
     reads as locked while staying legible. */
  filter:grayscale(1) brightness(1.1); opacity:.55;
  transition:filter .25s ease, opacity .25s ease, transform .25s ease; }
.inf-rcell.got img { filter:none; opacity:1; }
.inf-rcell.now img { transform:translateY(-3px) scale(1.06);
  filter:drop-shadow(0 0 12px rgba(138,99,210,.85)); }
.inf-rnum { display:block; margin-top:5px; font-size:.64rem; letter-spacing:.12em;
  color:#5a7888; font-variant-numeric:tabular-nums; }
.inf-rcell.got .inf-rnum { color:#b9aed6; }
.inf-rcell.now .inf-rnum { color:#00e0b4; font-weight:bold; }
/* The phone's single badge. Hidden on desktop, where the row says it better. */
.inf-badge-now { display:none; width:64px; height:auto; flex:0 0 auto;
  filter:drop-shadow(0 0 10px rgba(138,99,210,.7)); }

.inf-rungs { display:flex; gap:4px; margin:12px 0 0; }
.inf-rung { flex:1 1 0; min-width:0; height:16px; background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.08); }
.inf-rung.on { background:linear-gradient(180deg,#8a63d2,#5b3a9e); border-color:#9d78e0; }
.inf-rung.top { background:linear-gradient(180deg,#00e0b4,#00a07e); border-color:#00e0b4; }
.inf-scale { display:flex; gap:4px; margin-top:5px; }
.inf-scale span { flex:1 1 0; min-width:0; text-align:center; font-size:.6rem;
  letter-spacing:.04em; color:#5a7888; font-variant-numeric:tabular-nums; }
.inf-pips { display:flex; flex-wrap:wrap; gap:4px; align-items:center; margin-top:12px; }
.inf-pip { width:11px; height:11px; border-radius:50%; background:#00c8a0;
  box-shadow:0 0 7px rgba(0,200,160,.55); }
.inf-standing { display:flex; flex-wrap:wrap; gap:18px; align-items:baseline; }
.inf-big { font-size:2.1rem; font-weight:bold; color:#e8e2f8; line-height:1;
  font-variant-numeric:tabular-nums; }
.inf-sub { font-size:.72rem; letter-spacing:.13em; text-transform:uppercase; color:#5a7888; }

/* ---- targets -------------------------------------------------------- */
.inf-target { background:#0a1929; border:1px solid rgba(110,74,168,.26);
  padding:16px 18px; margin:0 0 12px; }
.inf-target.done { border-color:rgba(0,200,160,.3); }
.inf-target.shut { opacity:.55; }
.inf-trow { display:flex; flex-wrap:wrap; gap:12px; align-items:baseline;
  justify-content:space-between; }
.inf-win { font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; }
.inf-win.early { color:#00e0b4; } .inf-win.main { color:#e8b24a; }
.inf-win.late  { color:#7a8ea0; }
.inf-form { display:flex; flex-wrap:wrap; gap:9px; margin-top:12px; }
.inf-form input[type=text] { flex:1 1 320px; min-width:0; background:#07111d;
  border:1px solid rgba(110,74,168,.4); color:#c8d8e8; padding:10px 11px;
  font:400 14px/1.4 Arial,sans-serif; }
.inf-form input[type=text]:focus { outline:2px solid #8a63d2; outline-offset:1px; }
.inf a.inf-go, .inf button { background:#6e4aa8; color:#fff; border:0; padding:10px 18px;
  font-weight:bold; font-size:.85rem; cursor:pointer; text-decoration:none;
  display:inline-block; }
.inf a.inf-go:hover, .inf button:hover { background:#8a63d2; }
.inf button.inf-quiet { background:transparent; border:1px solid rgba(110,74,168,.5);
  color:#b9aed6; font-weight:normal; }
.inf-said { font-size:.86rem; color:#8aa0b4; margin:8px 0 0;
  border-left:2px solid rgba(0,200,160,.4); padding-left:10px; }

/* The two steps we cannot verify. Quiet until pressed, so the one control
   that matters on this card is still the Infiltrate button. */
.inf-steps-gate { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:4px; }
.inf button.inf-step { background:transparent; border:1px solid rgba(110,74,168,.5);
  color:#b9aed6; font-weight:normal; font-size:.82rem; padding:8px 14px; }
.inf button.inf-step:hover { border-color:#8a63d2; color:#e8e2f8; }
.inf button.inf-step.on { background:rgba(0,200,160,.14); border-color:#00c8a0; color:#00e0b4; }
.inf button.inf-step.on::before { content:'\2713\00a0'; }
.inf-trust { font-size:.7rem; letter-spacing:.04em; color:#5a7888; }
/* Only ever applied by script. With JS off the form is untouched and
   submits normally -- a gate nobody can open is worse than no gate. */
.inf-form.locked { opacity:.45; }

/* ---- messages ------------------------------------------------------- */
.inf-msg { margin:14px 0; padding:11px 14px; border-left:3px solid; font-size:.9rem; }
.inf-msg.good { border-color:#00c8a0; background:rgba(0,200,160,.1); color:#00c8a0; }
.inf-msg.bad  { border-color:#e0705f; background:rgba(224,112,95,.1); color:#e0705f; }

/* ---- the code ------------------------------------------------------- */
/* THE CODE IS THE BUTTON. A separate "copy" control beside it is a second
   thing to find and aim at on a phone, where this flow actually happens --
   the member is holding the device they are about to open X on. */
.inf-copy { margin:4px 0 0; }
.inf button.inf-code { font:700 1.5rem/1 ui-monospace,Menlo,monospace; letter-spacing:.16em;
  color:#00e0b4; background:#07111d; border:1px dashed rgba(0,200,160,.45);
  padding:14px 18px; display:inline-flex; align-items:center; gap:14px;
  cursor:pointer; }
.inf button.inf-code:hover { background:#0a1929; border-color:#00e0b4; }
.inf button.inf-code.done { border-style:solid; border-color:#00e0b4; }
.inf-copy-hint { font:400 .62rem/1 Arial,sans-serif; letter-spacing:.14em;
  text-transform:uppercase; color:#5a7888; }
.inf button.inf-code.done .inf-copy-hint { color:#00e0b4; }
.inf-steps { margin:12px 0 0; padding-left:20px; font-size:.92rem; }
.inf-steps li { margin:0 0 7px; }

/* ---- the board ------------------------------------------------------ */
/* The scroller, and min-width:0 so IT scrolls rather than the page: the
   table inside declares 420px and without this that becomes the column's
   floor. */
.inf-tablewrap { overflow-x:auto; min-width:0; max-width:100%; }
.inf-table { border-collapse:collapse; width:100%; min-width:420px; font-size:.88rem; }
.inf-table th { text-align:left; padding:9px 10px; color:#5a7888; font-size:.66rem;
  letter-spacing:.12em; text-transform:uppercase; font-weight:normal;
  border-bottom:1px solid rgba(110,74,168,.24); }
.inf-table td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.05); }
.inf-table .n { font-variant-numeric:tabular-nums; text-align:right; }
.inf-table tr.me td { background:rgba(110,74,168,.16); }
.inf-table .narrow { display:none; }
@media (max-width:700px){
  /* A 3:1 band is 127px tall on a 390px phone, which is a stripe rather
     than a picture. Squarer here so the art still reads, and the ratio is
     still the only thing setting the height. */
  .inf-hero { aspect-ratio: 16/11; }
  .inf-hero-in { padding:14px 16px; }
  .inf-hero h2 { font-size:1.25rem; letter-spacing:.1em; }
  .inf-hero p  { font-size:.82rem; }
  .inf-scale span { font-size:.5rem; }
  .inf-standing { gap:12px; }
  /* margin-left:auto strands the handle on its own wrapped line. */
  .inf-standing > div[style] { margin-left:0 !important; }
  .inf-big { font-size:1.7rem; }
  .inf-ladder { display:none; }
  .inf-badge-now { display:block; width:58px; }
  .inf-meter, .inf-target { padding:14px 15px; }
  .inf-form input[type=text] { flex:1 1 100%; }
  .inf a.inf-go, .inf button { width:100%; text-align:center; }
  .inf button.inf-step { width:auto; flex:1 1 44%; }
  .inf-trow { gap:6px; }
  /*
   * THE TABLE'S OWN FLOOR HAS TO GO, not just the page's.
   *
   * min-width:420px inside an overflow-x:auto wrapper looked safe and was
   * not: the wrapper is an auto-width block inside an auto-width column, so
   * the 420 propagated all the way up and the PAGE scrolled instead of the
   * table. Measured by removing each section in turn -- dropping this one
   * took body.scrollWidth from 436 straight to the viewport.
   */
  .inf-table { min-width:0; font-size:.8rem; }
  .inf-table th, .inf-table td { padding:7px 6px; }
  .inf-table .wide   { display:none; }
  .inf-table .narrow { display:inline; }
}
</style>

<div class="container"><div class="row"><div class="col1of3" style="margin:0 auto;flex:1 1 100%;max-width:none;">
<div class="inf">

  <div class="inf-hero">
    <div class="inf-hero-in">
      <h2>Infiltration</h2>
      <p>Reply to our posts on X in your own words. Every verified infiltration pays a trait,
         and replying promptly climbs the ladder that decides how good it is.</p>
    </div>
  </div>

<?php if ($inf_uid <= 0): ?>
  <p class="inf-msg bad">Sign in to infiltrate.</p>

<?php elseif (!$inf_ready): ?>
  <p class="inf-msg bad">Infiltration is not switched on yet.</p>

<?php else: ?>

  <?php if ($inf_flash !== ''): ?><p class="inf-msg good"><?php echo htmlspecialchars($inf_flash); ?></p><?php endif; ?>
  <?php if ($inf_bad   !== ''): ?><p class="inf-msg bad"><?php echo htmlspecialchars($inf_bad); ?></p><?php endif; ?>

  <?php if (!$inf_linked): ?>
    <?php /* ---- connect an X account ------------------------------------ */ ?>
    <div class="inf-meter">
      <h3>Connect your X account</h3>
      <p>One time. We need to know which account is yours before a reply can count.</p>
      <div class="inf-copy">
        <button type="button" class="inf-code" id="infCode"
                data-code="<?php echo htmlspecialchars($inf_code, ENT_QUOTES); ?>"
                aria-label="Copy your verification code">
          <?php echo htmlspecialchars($inf_code); ?>
          <span class="inf-copy-hint" id="infCopyHint">tap to copy</span>
        </button>
      </div>
      <ol class="inf-steps">
        <li>Reply to <a class="inf-go" style="padding:2px 8px;font-size:.78rem"
            href="https://x.com/skulliance/status/<?php echo htmlspecialchars(INFIL_LINK_STATUS); ?>"
            target="_blank" rel="noopener">this post</a> with the code above in it.
            Reply to the post itself, not to somebody else's comment.</li>
        <li>Copy the link to your reply.</li>
        <li>Paste it here.</li>
      </ol>
      <form method="post" class="inf-form">
        <input type="text" name="comment" placeholder="https://x.com/you/status/..." autocomplete="off" required>
        <button type="submit" name="link_x" value="1">Connect</button>
      </form>
      <form method="post" style="margin-top:8px">
        <button type="submit" name="new_code" value="1" class="inf-quiet">Issue a new code</button>
      </form>
    </div>

  <?php else: ?>
    <?php /* ---- the meter --------------------------------------------- */
      $t = (int) $inf_meter['tier']; $b = (int) $inf_meter['bank'];
      $tbl = infil_table_name($t);
      $odds = infil_table($t);
    ?>
    <div class="inf-meter">
      <div class="inf-standing">
        <?php /* The phone's version of the ladder: just the one you are on.
                 Absent at rung 0, where there is no badge yet and the empty
                 socket is the point. */ ?>
        <?php if ($t > 0): ?>
        <img class="inf-badge-now" src="icons/infiltration/rung-<?php echo $t; ?>.webp"
             alt="" decoding="async" onerror="this.remove()">
        <?php endif; ?>
        <div><span class="inf-big"><?php echo $t; ?></span><span class="inf-sub"> / <?php echo INFIL_MAX_TIER; ?> rungs</span></div>
        <div><span class="inf-sub">Rolling on</span><br><b><?php
          echo htmlspecialchars(isset($inf_labels[$tbl]) ? $inf_labels[$tbl] : $tbl); ?></b>
          &middot; <?php echo (float) $odds['mythic']; ?>% mythic</div>
        <div><span class="inf-sub">This month</span><br><b><?php echo (int) $inf_meter['month_tiers']; ?></b> rungs climbed</div>
        <div style="margin-left:auto"><span class="inf-sub">as @<?php echo htmlspecialchars($inf_handle); ?></span></div>
      </div>

      <?php /*
       * THE LADDER, AS NINE BADGES.
       *
       * <img> rather than a CSS background, for the same reason
       * launchpad.php gives: images deploy by FTP outside this repo, and a
       * background-image that 404s is a silently empty row. One onerror
       * marks the whole row and CSS hides it, leaving the bar below -- which
       * is why the bar stays rather than being replaced.
       *
       * Hidden under 700px, where nine of anything is 40px of mud. The phone
       * gets the single current badge up in the standing row instead.
       */ ?>
      <div class="inf-ladder" aria-hidden="true">
        <?php for ($i = 1; $i <= INFIL_MAX_TIER; $i++): ?>
        <div class="inf-rcell<?php echo $i <= $t ? ' got' : ''; ?><?php
                              echo $i === $t ? ' now' : ''; ?>">
          <img src="icons/infiltration/rung-<?php echo $i; ?>.webp" alt=""
               decoding="async" loading="lazy"
               onerror="this.closest('.inf-ladder').classList.add('noart')">
          <span class="inf-rnum"><?php echo $i; ?></span>
        </div>
        <?php endfor; ?>
      </div>

      <div class="inf-rungs" aria-label="Your rung">
        <?php for ($i = 1; $i <= INFIL_MAX_TIER; $i++): ?>
          <i class="inf-rung<?php echo $i <= $t ? ($i === INFIL_MAX_TIER ? ' on top' : ' on') : ''; ?>"></i>
        <?php endfor; ?>
      </div>
      <div class="inf-scale">
        <?php for ($i = 1; $i <= INFIL_MAX_TIER; $i++): ?>
          <span><?php echo (float) DHCF_TIERS[infil_table_name($i)]['mythic']; ?></span>
        <?php endfor; ?>
      </div>
      <p class="inf-sub" style="margin-top:4px">mythic chance at each rung</p>

      <?php if ($b > 0): ?>
      <div class="inf-pips" aria-label="Banked">
        <span class="inf-sub">Banked</span>
        <?php for ($i = 0; $i < min($b, 24); $i++): ?><i class="inf-pip"></i><?php endfor; ?>
        <b style="color:#00c8a0"><?php echo $b; ?></b>
        <span class="inf-sub">&mdash; each one absorbs a missed target before your rung drops</span>
      </div>
      <?php elseif ($t >= INFIL_MAX_TIER): ?>
        <p class="inf-sub" style="margin-top:10px">At the top. Every prompt reply from here banks a pip.</p>
      <?php endif; ?>
    </div>

    <?php /* ---- the targets --------------------------------------------- */ ?>
    <h3 style="margin:22px 0 10px">Targets</h3>
    <?php if (!$inf_board): ?>
      <p class="inf-sub">No targets yet. They appear here as they are posted.</p>
    <?php endif; ?>

    <?php foreach ($inf_board as $tg):
      $done   = ($tg['sub_state'] === INFIL_OK);
      $wait   = ($tg['sub_state'] === INFIL_PENDING);
      $shut   = ((int) $tg['retired'] === 1);
      $url    = 'https://x.com/skulliance/status/' . $tg['status_id'];
      $worth  = infil_gain($tg['window']);
    ?>
    <div class="inf-target<?php echo $done ? ' done' : ''; ?><?php echo $shut ? ' shut' : ''; ?>">
      <div class="inf-trow">
        <div>
          <b><?php echo htmlspecialchars($tg['label'] !== '' ? $tg['label'] : 'Target'); ?></b>
          <span class="inf-sub">&middot; <?php echo (int) $tg['crowd']; ?> infiltrated</span>
        </div>
        <div class="inf-win <?php echo htmlspecialchars($tg['window']); ?>">
          <?php if ($shut): ?>closed
          <?php elseif ($tg['window'] === 'early'): ?>first window &middot; worth +2
          <?php elseif ($tg['window'] === 'main'): ?>open &middot; worth +1
          <?php else: ?>late &middot; pays a trait, no climb<?php endif; ?>
        </div>
      </div>

      <?php if ($done): ?>
        <p class="inf-said">Infiltrated
          <?php echo $tg['sub_speed'] === 'early' ? 'in the first window'
                   : ($tg['sub_speed'] === 'main' ? 'in the window' : 'late'); ?>.
          <?php echo (int) $tg['sub_tiers'] > 0
                ? '+' . (int) $tg['sub_tiers'] . ' rung' . ((int) $tg['sub_tiers'] === 1 ? '' : 's')
                : 'No climb.'; ?></p>
      <?php elseif ($wait): ?>
        <p class="inf-said">Waiting on X. This is saved and will be checked again -- nothing is
           wrong with your comment.</p>
      <?php elseif (!$shut): ?>
        <a class="inf-go" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">Open the post</a>

        <?php /*
         * THE RUN ORDER, AND IT IS A CHECKLIST RATHER THAN A CHECK.
         *
         * Like, repost, then comment. The first two cannot be verified from
         * any free surface -- the syndication endpoint reports a post's
         * TOTAL likes, never who gave them -- so these two are the member's
         * word and the copy says so.
         *
         * SAYING SO IS THE POINT, not a concession. The comment check is
         * real and rigorous, and the whole feature rests on members
         * believing that; a fake check here is one curious person away from
         * casting doubt on the one that matters. "We are trusting you" also
         * asks more of somebody than a box they think is watched, because
         * skipping it is a breach rather than a system beaten.
         *
         * The gate is the reminder. Both have to be pressed before the
         * comment box opens, which is what puts the two actions in front of
         * somebody at the moment they are about to do the third.
         */ ?>
        <div class="inf-steps-gate" data-gate>
          <p class="inf-sub" style="margin:10px 0 6px">Do these first</p>
          <button type="button" class="inf-step" data-step="like">I liked it</button>
          <button type="button" class="inf-step" data-step="repost">I reposted it</button>
          <span class="inf-trust">We cannot check these two. We are trusting you.</span>
        </div>

        <form method="post" class="inf-form" data-gated>
          <input type="hidden" name="target" value="<?php echo (int) $tg['id']; ?>">
          <input type="text" name="comment" placeholder="Paste the link to your reply" autocomplete="off" required>
          <button type="submit" name="infiltrate" value="1">Infiltrate<?php
            echo $worth > 0 ? ' (+' . $worth . ')' : ''; ?></button>
        </form>
        <p class="inf-sub" style="margin-top:7px">At least <?php echo INFIL_MIN_CHARS; ?> characters
          in your own words, and not something you have said before.</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>

  <?php /* ---- the board ------------------------------------------------- */ ?>
  <h3 style="margin:26px 0 10px">This month</h3>
  <?php if (!$inf_top): ?>
    <p class="inf-sub">Nobody has climbed a rung this month yet.</p>
  <?php else: ?>
  <div class="inf-tablewrap">
  <table class="inf-table">
    <?php /* Two labels per heading, one shown at a time. A five column table
             whose longest heading is "Rungs this month" cannot fit a 320px
             phone at any font size, and the alternative to shortening the
             words is hiding a column -- which loses the number rather than
             the sentence around it. */ ?>
    <tr><th>#</th><th>Member</th>
        <th class="n"><span class="wide">Rungs this month</span><span class="narrow">Rungs</span></th>
        <th class="n">Rung</th>
        <th class="n"><span class="wide">Banked</span><span class="narrow">Bank</span></th></tr>
    <?php foreach ($inf_top as $i => $row): ?>
    <tr<?php echo (int) $row['user_id'] === $inf_uid ? ' class="me"' : ''; ?>>
      <td class="n"><?php echo $i + 1; ?></td>
      <td><?php echo htmlspecialchars($row['username']); ?></td>
      <td class="n"><?php echo (int) $row['month_tiers']; ?></td>
      <td class="n"><?php echo (int) $row['tier']; ?></td>
      <td class="n"><?php echo (int) $row['bank']; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div>
</div></div></div>

<script>
/* One tap, with a fallback that is not a dead end.
   navigator.clipboard is unavailable on a plain-http origin and can be
   refused outright, and this is the one control the whole connect flow
   depends on -- so a failure selects the text instead, which leaves the
   member one long-press from copying it by hand rather than stuck. */
(function () {
  var b = document.getElementById('infCode');
  if (!b) return;
  var hint = document.getElementById('infCopyHint');
  var was  = hint ? hint.textContent : '';
  var t;
  function say(msg, good) {
    if (!hint) return;
    hint.textContent = msg;
    b.classList.toggle('done', !!good);
    clearTimeout(t);
    t = setTimeout(function () { hint.textContent = was; b.classList.remove('done'); }, 2200);
  }
  function selectIt() {
    try {
      var r = document.createRange();
      r.selectNodeContents(b.firstChild ? b : b);
      var sel = window.getSelection();
      sel.removeAllRanges(); sel.addRange(r);
    } catch (e) {}
  }
  b.addEventListener('click', function () {
    var code = b.getAttribute('data-code') || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(function () { say('copied', true); },
                                              function () { selectIt(); say('press and hold to copy'); });
    } else {
      selectIt();
      say('press and hold to copy');
    }
  });
}());
</script>

<script>
/*
 * LIKE, REPOST, THEN COMMENT -- enforced as an order, not as a check.
 *
 * The first two cannot be verified from anything free, so this is a
 * checklist. Its job is to put both actions in front of somebody at the
 * moment they are about to do the third, because a line of instructions
 * nobody has to acknowledge is a line nobody reads.
 *
 * LOCKED BY SCRIPT ONLY. The form ships enabled and JS disables it, so with
 * scripting off the page still works -- a gate that cannot be opened is
 * worse than no gate at all.
 *
 * Remembered per target for the session: a refused submission reloads the
 * page, and making somebody re-press both buttons to fix a typo would teach
 * them to resent the checklist.
 */
(function () {
  function load(id) {
    try { return JSON.parse(sessionStorage.getItem('inf-gate-' + id) || '{}'); }
    catch (e) { return {}; }
  }
  function save(id, v) {
    try { sessionStorage.setItem('inf-gate-' + id, JSON.stringify(v)); } catch (e) {}
  }
  var gates = document.querySelectorAll('[data-gate]');
  Array.prototype.forEach.call(gates, function (gate) {
    var form = gate.parentNode.querySelector('[data-gated]');
    if (!form) return;
    var hid  = form.querySelector('input[name="target"]');
    var id   = hid ? hid.value : '0';
    var go   = form.querySelector('button[type="submit"]');
    var done = load(id);

    function sync() {
      var all = true;
      Array.prototype.forEach.call(gate.querySelectorAll('[data-step]'), function (b) {
        var on = !!done[b.getAttribute('data-step')];
        b.classList.toggle('on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (!on) all = false;
      });
      form.classList.toggle('locked', !all);
      if (go) go.disabled = !all;
      return all;
    }

    Array.prototype.forEach.call(gate.querySelectorAll('[data-step]'), function (b) {
      b.addEventListener('click', function () {
        var k = b.getAttribute('data-step');
        done[k] = !done[k];
        save(id, done);
        sync();
      });
    });
    sync();
  });
}());
</script>

<?php if (is_array($inf_drop)): ?>
<script>
/* The platform's own reveal, included by header.php on every page. Same
   handoff missions-claim-modal.php uses, on a short delay so the modal
   opens onto a drawn page rather than over a half-painted one. */
(function () {
  var d = <?php echo json_encode($inf_drop); ?>;
  function go() { if (window.DHC_SHOW_DROP) window.DHC_SHOW_DROP(d); }
  if (document.readyState === 'complete') setTimeout(go, 300);
  else window.addEventListener('load', function () { setTimeout(go, 300); });
}());
</script>
<?php endif; ?>
<?php $conn->close(); ?>
</html>

<?php
/*
 * musicvideos.php -- music videos made using art from the projects and
 * artists in Skulliance.
 *
 * NOT "films the artists made for their own collections", which is what
 * this said first and is not true: the art is theirs, the videos are made
 * with it. A page about somebody's work is the wrong place to be loose
 * about whose work it is.
 *
 * PUBLIC, like the Skull Paper and partner.php. The point of a music
 * video is being watched; behind a login it would be the one thing on the
 * platform harder to reach than the staking.
 *
 * THE FOLDER IS THE LIST. See lib/music-videos.php: videos arrive by FTP
 * and a hardcoded roster would mean a deploy to publish one. Whatever is
 * in videos/ is what appears here, matched to a project by its filename.
 *
 * THE PROJECT'S OWN MARK WHERE IT HAS ONE. A Monstrocity theme carries a
 * logo drawn for that project, so it stands in for the name -- the name
 * is still there for a screen reader and for the projects without a theme.
 */
include_once 'db.php';
require_once __DIR__ . '/lib/music-videos.php';

/* Same session restore as partner.php and skullpaper.php: light up the
   member navbar if a cookie is already there, and leave a stranger with
   the public one. MERGED, never raw-assigned. */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = array_merge((array) $_SESSION, $cookie); }
}
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	$name   = $_SESSION['userData']['name'] ?? null;
	$mv_did = $_SESSION['userData']['discord_id'] ?? null;
	$mv_av  = $_SESSION['userData']['avatar'] ?? null;
	if ($name !== null && $mv_did && $mv_av) $avatar_url = avatar_url($mv_did, $mv_av);
}

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

$mv_list = mv_videos(isset($conn) ? $conn : null, __DIR__);

/*
 * THE CARD X AND DISCORD SHOW.
 *
 * $extra_head before header.php is how dhcarena.php and the rest do this.
 * The image is images/og.jpg, the same one the partner page uses -- NOT a
 * generated montage of the videos: there is no way to pull a frame out of
 * an mp4 on this server, and a card generator for this was already built
 * once on partner.php and thrown away when a direct url to the jpg was
 * the answer.
 *
 * ABSOLUTE URLS, because a crawler fetching the card has no page context
 * to resolve a relative one against -- and the DIMENSIONS are stated, so
 * a crawler can lay out the card before it has the bytes. They are the
 * file's real 1416x1113 rather than the 1200x630 that gets copied around;
 * X crops to about 1.91:1 regardless, and lying about the shape only
 * makes it crop the wrong part.
 */
$page_title_override = 'Music Videos | Skulliance';
$mv_canon = 'https://www.skulliance.io/staking/musicvideos.php';
$mv_og    = 'https://www.skulliance.io/staking/images/og.jpg';
$mv_desc  = count($mv_list)
	? count($mv_list) . ' music videos made using art from the projects and artists '
	  . 'in Skulliance.'
	: 'Music videos made using art from the projects and artists in Skulliance.';
$mv_alt   = 'Skulliance - NFT staking, games and the artists behind them';

$extra_head = '
<meta name="description" content="' . $e($mv_desc) . '">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="' . $e($mv_canon) . '">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="' . $e($mv_canon) . '">
<meta property="og:title" content="Music Videos">
<meta property="og:description" content="' . $e($mv_desc) . '">
<meta property="og:image" content="' . $e($mv_og) . '">
<meta property="og:image:alt" content="' . $e($mv_alt) . '">
<meta property="og:image:width" content="1416">
<meta property="og:image:height" content="1113">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@skulliance">
<meta name="twitter:title" content="Music Videos">
<meta name="twitter:description" content="' . $e($mv_desc) . '">
<meta name="twitter:image" content="' . $e($mv_og) . '">
<meta name="twitter:image:alt" content="' . $e($mv_alt) . '">
';

include 'header.php';
/* header.php's navbar lives inside if(isset($name)), so a stranger -- which
   is most of the audience for this page -- would otherwise land with no way
   anywhere. Same fix partner.php and the Skull Paper carry. */
if (!isset($name)) include __DIR__ . '/site-header.php';
?>
<style>
.mv-wrap{max-width:1180px;margin:0 auto;padding:20px}
.mv-wrap h1{font-family:"Archivo Black",Impact,sans-serif;font-weight:400;margin:0 0 6px;font-size:24px}
.mv-intro{color:#7a9eb0;font-size:13px;line-height:1.7;margin:0 0 22px}
.mv-grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))}

/* A TILE IS A BUTTON, not a video. Twelve <video> elements all asking for
   metadata at once opened more connections than the browser allows per
   host, and most of the page sat on a spinner showing nothing. The grid
   is images now and the film is fetched when somebody asks for it. */
.mv-item{background:#0a1929;border:1px solid #1b3346;border-radius:5px;overflow:hidden;
  display:flex;flex-direction:column;padding:0;cursor:pointer;text-align:left;
  font:inherit;color:inherit;width:100%}
.mv-item:hover,.mv-item:focus-visible{border-color:#00c8a0;outline:none}
.mv-shot{position:relative;display:block;aspect-ratio:4/3;background:#06101a}
.mv-shot img{display:block;width:100%;height:100%;object-fit:cover}
.mv-item:hover .mv-shot img{opacity:.82}
/* The play badge, drawn rather than an image file. */
.mv-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  pointer-events:none}
.mv-play i{display:block;width:62px;height:62px;border-radius:50%;background:rgba(6,16,26,.62);
  border:2px solid rgba(255,255,255,.85);position:relative;transition:background .15s ease}
.mv-play i::after{content:"";position:absolute;top:50%;left:54%;transform:translate(-50%,-50%);
  border-style:solid;border-width:11px 0 11px 18px;border-color:transparent transparent transparent #fff}
.mv-item:hover .mv-play i{background:rgba(0,200,160,.85)}
.mv-cap{padding:10px 14px;display:flex;align-items:center;gap:10px;min-height:66px}
.mv-cap img{display:block;max-height:46px;max-width:100%;width:auto;height:auto}
.mv-cap img.mv-ico{max-height:28px;flex:0 0 auto}
.mv-cap span{font-size:13.5px;color:#e8eaed;letter-spacing:.02em}
.mv-none{color:#7a9eb0;font-size:13px;border:1px dashed #1b3346;border-radius:5px;padding:26px;
  text-align:center}

/* ---- the player ---- */
#mvp-veil{position:fixed;inset:0;z-index:9996;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.92);
  padding:calc(env(safe-area-inset-top,0px) + 16px) 16px calc(env(safe-area-inset-bottom,0px) + 16px)}
#mvp-veil.on{display:flex}
/* A STARTING SIZE ONLY. Once the film reports its own dimensions the
   script sets an exact pixel width here, so it is shown at its real
   resolution and never blown up. This is what it looks like in the
   moment before that, and on a browser where metadata never arrives. */
#mvp-box{width:min(760px,calc(100vh - 170px),100%);display:flex;flex-direction:column;gap:10px}
#mvp-head{display:flex;align-items:center;gap:12px}
#mvp-title{flex:1 1 auto;font-size:14px;color:#e8eaed;letter-spacing:.03em}
#mvp-close{font:inherit;font-size:11px;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;
  padding:7px 14px;border-radius:3px;border:1px solid #1b3346;background:transparent;color:#e8eaed}
#mvp-close:hover{border-color:#00c8a0;color:#00c8a0}
/* 1/1 is the shape of the films there are today; the script replaces it
   with whatever the file actually reports, so a non-square one later is
   not stretched into a square. */
#mvp-video{display:block;width:100%;aspect-ratio:1/1;
  background:#06101a;border:1px solid #1b3346;border-radius:4px}
</style>

<div class="mv-wrap">
  <h1>Music Videos</h1>
  <p class="mv-intro">Music videos made using art from the projects and artists in
    Skulliance.<?php echo count($mv_list) ? ' ' . (int) count($mv_list) . ' of them so far.' : ''; ?></p>

<?php if (!$mv_list): ?>
  <p class="mv-none">No videos have been uploaded yet.</p>
<?php else: ?>
  <div class="mv-grid">
<?php foreach ($mv_list as $k => $v): ?>
    <?php /* A BUTTON, so it is reachable by keyboard and announced as
             something you can press. The whole tile is the target. */ ?>
    <button type="button" class="mv-item" data-src="<?php echo $e($v['file']); ?>"
            data-title="<?php echo $e($v['title']); ?>"
            data-w="<?php echo (int) $v['w']; ?>" data-h="<?php echo (int) $v['h']; ?>">
      <span class="mv-shot">
<?php if ($v['poster'] !== ''): ?>
        <img src="<?php echo $e($v['poster']); ?>" alt=""
             width="2000" height="1500"
             loading="<?php echo $k < 3 ? 'eager' : 'lazy'; ?>" decoding="async">
<?php endif; ?>
        <span class="mv-play"><i></i></span>
      </span>
      <span class="mv-cap">
<?php if ($v['logo'] !== ''): ?>
        <img src="<?php echo $e($v['logo']); ?>" alt="<?php echo $e($v['title']); ?>"
             loading="lazy" decoding="async">
<?php elseif ($v['icon'] !== ''): ?>
      <?php /* No Monstrocity theme, so the project's points icon stands in.
               It is a glyph rather than a wordmark, so the name stays beside
               it -- on its own next to eleven wordmarks it reads as an image
               that failed to load. */ ?>
        <img class="mv-ico" src="<?php echo $e($v['icon']); ?>" alt=""
             loading="lazy" decoding="async">
        <span><?php echo $e($v['title']); ?></span>
<?php else: ?>
        <span><?php echo $e($v['title']); ?></span>
<?php endif; ?>
      </span>
    </button>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<?php if ($mv_list): ?>
<div id="mvp-veil" role="dialog" aria-modal="true" aria-labelledby="mvp-title" hidden>
  <div id="mvp-box">
    <div id="mvp-head">
      <span id="mvp-title"></span>
      <button type="button" id="mvp-close">Close</button>
    </div>
    <?php /* ONE video element for the whole page, given a src when a tile
             is pressed and stripped of it again on close. Twelve elements
             that each know their own film is what filled the page with
             spinners; one that knows nothing until asked downloads
             nothing until asked. */ ?>
    <video id="mvp-video" controls playsinline preload="auto"></video>
  </div>
</div>
<?php endif; ?>

<script>
/*
 * ONE PLAYER, OPENED FROM A TILE.
 *
 * The grid used to be twelve <video> elements, each asking for metadata
 * and then seeking to its own midpoint for a still. On the real page that
 * opened more connections than the browser allows per host: most tiles
 * sat on a spinner, four got as far as the midpoint without painting a
 * frame, and one worked. The tiles are realm theme images now -- which
 * the platform already keys by project id -- and the film is fetched only
 * when somebody presses one.
 *
 * THE SRC IS REMOVED ON CLOSE, not just paused. A paused video with a src
 * keeps its buffer and, on some browsers, keeps filling it; clearing the
 * source and calling load() is what actually stops the download.
 */
(function () {
  var veil = document.getElementById('mvp-veil');
  var vid  = document.getElementById('mvp-video');
  var ttl  = document.getElementById('mvp-title');
  var box  = document.getElementById('mvp-box');
  var shut = document.getElementById('mvp-close');
  var tiles = document.querySelectorAll('.mv-item');
  if (!veil || !vid || !box || !tiles.length) return;

  var opener = null;

  /*
   * SHOWN AT ITS OWN SIZE, NEVER BLOWN UP.
   *
   * A 600px film stretched across a 760px box is a 600px film with soft
   * edges -- upscaling cannot add anything, it only makes the compression
   * visible. So the box is set to the video's INTRINSIC width, and scaled
   * DOWN only when the window is too small to hold it.
   *
   * Math.min(1, ...) is the whole rule: the scale never goes above 1.
   *
   * The aspect comes from the file too. Today every one of them is
   * square, and hardcoding that would quietly stretch the first one that
   * is not.
   */
  function fit(w, h) {
    w = w || vid.videoWidth;
    h = h || vid.videoHeight;
    if (!w || !h || veil.hidden) return;
    var head = document.getElementById('mvp-head');
    /* What the veil leaves once its padding and the title row are out. */
    var availW = veil.clientWidth - 32;
    var availH = veil.clientHeight - 32 - (head ? head.offsetHeight : 0) - 10;
    var scale  = Math.min(1, availW / w, availH / h);
    if (!isFinite(scale) || scale <= 0) scale = 1;
    box.style.width = Math.round(w * scale) + 'px';
    vid.style.aspectRatio = w + ' / ' + h;
  }
  /* Both of these call it with no arguments, so it falls back to the
     element's own dimensions -- which by then are known and authoritative
     if the header said something different. */
  vid.addEventListener('loadedmetadata', function () { fit(); });
  window.addEventListener('resize', function () { fit(); });

  function open(btn) {
    opener = btn;
    ttl.textContent = btn.getAttribute('data-title') || '';
    vid.src = btn.getAttribute('data-src');
    veil.hidden = false;
    veil.classList.add('on');
    /*
     * SIZED BEFORE IT IS SEEN. The dimensions come from the mp4's own
     * header, read server-side, so the box opens at the film's size
     * rather than at a CSS guess that visibly snaps to the right one the
     * moment loadedmetadata arrives. Without this the modal opened at
     * 760px and jumped to 560.
     */
    fit(parseInt(btn.getAttribute('data-w'), 10), parseInt(btn.getAttribute('data-h'), 10));
    if (shut) shut.focus();
    /* Autoplay with sound is refused by every browser, and rightly. The
       press on the tile is the gesture, so this usually succeeds -- and
       where it does not the controls are right there. */
    var p = vid.play();
    if (p && p.catch) p.catch(function () {});
  }

  function close() {
    veil.classList.remove('on');
    veil.hidden = true;
    try { vid.pause(); } catch (e) {}
    vid.removeAttribute('src');
    try { vid.load(); } catch (e) {}   /* drops the buffer and the request */
    ttl.textContent = '';
    /* Cleared, or the next film opens at the last one's width before its
       own metadata arrives. */
    box.style.width = '';
    vid.style.aspectRatio = '';
    if (opener) { opener.focus(); opener = null; }
  }

  for (var i = 0; i < tiles.length; i++) {
    tiles[i].addEventListener('click', function () { open(this); });
  }
  if (shut) shut.addEventListener('click', close);
  /* The backdrop closes it; the box itself must not. */
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !veil.hidden) close();
  });
})();
</script>
</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>

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
.mv-grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr))}
.mv-item{background:#0a1929;border:1px solid #1b3346;border-radius:5px;overflow:hidden;
  display:flex;flex-direction:column}
/* 16:9 reserved up front, so twelve of these do not shunt the page around
   as each one's metadata arrives. */
.mv-item video{display:block;width:100%;aspect-ratio:16/9;background:#06101a}
.mv-cap{padding:10px 14px;display:flex;align-items:center;gap:10px;min-height:66px}
/* A height band rather than a fixed height: these logos are every shape
   there is, and a stacked wordmark capped at a one-line height shrinks to
   something nobody can read. The width cap keeps a long horizontal one
   from pushing the row around. */
.mv-cap img{display:block;max-height:46px;max-width:100%;width:auto;height:auto}
.mv-cap img.mv-ico{max-height:28px;flex:0 0 auto}
.mv-cap span{font-size:13.5px;color:#e8eaed;letter-spacing:.02em}
.mv-none{color:#7a9eb0;font-size:13px;border:1px dashed #1b3346;border-radius:5px;padding:26px;
  text-align:center}
</style>

<div class="mv-wrap">
  <h1>Music Videos</h1>
  <p class="mv-intro">Music videos made using art from the projects and artists in
    Skulliance.<?php echo count($mv_list) ? ' ' . (int) count($mv_list) . ' of them so far.' : ''; ?></p>

<?php if (!$mv_list): ?>
  <p class="mv-none">No videos have been uploaded yet.</p>
<?php else: ?>
  <div class="mv-grid">
<?php foreach ($mv_list as $v): ?>
    <div class="mv-item">
      <?php /* preload="metadata" so twelve videos cost twelve headers rather
               than twelve films. #t=0.5 is the floor: with no JavaScript at
               all it still shows a frame rather than a black rectangle. The
               script below moves that to the MIDPOINT once the duration is
               known, because half a second in is usually a fade from black.
               playsinline keeps an iPhone from taking over the whole screen
               the moment it is tapped. */ ?>
      <video controls preload="metadata" playsinline
             src="<?php echo $e($v['file']); ?>#t=0.5"></video>
      <div class="mv-cap">
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
      </div>
    </div>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<script>
/*
 * THE STILL IS A FRAME FROM THE MIDDLE OF THE FILM.
 *
 * There are no poster images and nothing on this server can make one --
 * pulling a frame out of an mp4 needs ffmpeg, and these arrive by FTP as
 * finished files. So the browser makes the poster: seek the video to its
 * own midpoint and the frame it lands on is what is painted until somebody
 * presses play. Half a second in, which is all the #t= fragment can ask
 * for without knowing the duration, is usually still a fade from black.
 *
 * COSTS A RANGE REQUEST EACH, not a download: the server answers 206 for
 * these (checked), so seeking fetches the chunk around the midpoint and
 * nothing else.
 *
 * AND PLAY STILL STARTS AT THE BEGINNING. The seek leaves currentTime in
 * the middle, so without this the first press of play would drop the
 * viewer halfway through the video they just chose to watch. Rewound once,
 * and only if they have not seeked somewhere themselves in the meantime.
 */
(function () {
  var vids = document.querySelectorAll('.mv-item video');
  for (var i = 0; i < vids.length; i++) (function (v) {
    var mid = null;
    v.addEventListener('loadedmetadata', function () {
      if (mid !== null || !isFinite(v.duration) || v.duration <= 0) return;
      mid = Math.min(v.duration / 2, Math.max(0, v.duration - 0.1));
      /* Throws on a browser that will not seek before it has buffered;
         the #t= frame then stands, which is the whole point of keeping it. */
      try { v.currentTime = mid; } catch (e) { mid = null; }
    });
    v.addEventListener('play', function () {
      if (mid !== null && Math.abs(v.currentTime - mid) < 0.4) v.currentTime = 0;
      mid = null;                       /* once only: after this it is theirs */
    });
  })(vids[i]);
})();
</script>
</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>

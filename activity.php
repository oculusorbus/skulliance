<?php
/*
 * ACTIVITY -- the platform's own copy of what Skull Bot announces to Discord.
 *
 * Reads nothing but the `activity` table. See activity-schema.md for why this
 * is a log written at announce time rather than a query across the feature
 * tables (short version: a lot of what gets announced is assembled into a
 * sentence and stored nowhere else), and activity-lib.php for the writer.
 *
 * Until the table exists this renders an empty wall with an explanation
 * rather than an error -- the feature is inert, not broken.
 */
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include_once 'activity-lib.php';

/* ---- filters ------------------------------------------------------------- */

$me = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;

/* Whitelisted rather than passed through: $scope reaches a WHERE clause, and
   while activity_recent() ignores anything it doesn't recognise, the value is
   also echoed back into the links below. */
$ac_scope = isset($_GET['scope']) ? (string) $_GET['scope'] : 'all';
if (!in_array($ac_scope, array('all', 'mine', 'involving'), true)) $ac_scope = 'all';
if ($me <= 0) $ac_scope = 'all';

$ac_chans = activity_channels($conn);
$ac_chan  = isset($_GET['channel']) ? (string) $_GET['channel'] : '';
/* Only a channel that actually has rows. Anything else is treated as "all",
   so a stale bookmark shows the feed instead of an empty page. */
if ($ac_chan !== '' && !isset($ac_chans[$ac_chan])) $ac_chan = '';

$ac_before = isset($_GET['before']) ? (int) $_GET['before'] : 0;
if ($ac_before < 0) $ac_before = 0;

/* One extra row, used only to decide whether to draw "Load older". Asking for
   PAGE_SIZE and then checking count() == PAGE_SIZE gets this wrong exactly
   when the last page is full, and prints a dead button. */
$ac_rows = activity_recent($conn, ACTIVITY_PAGE_SIZE + 1, $ac_before, $ac_chan, $me, $ac_scope);
$ac_more = count($ac_rows) > ACTIVITY_PAGE_SIZE;
if ($ac_more) array_pop($ac_rows);

/* Resolve every <@id> on this page in ONE query -- after the extra row is
   popped, so the card that is not being drawn does not pull names in. */
$ac_names = activity_mention_names($conn, $ac_rows);

/* Rebuild the querystring for a link that changes one filter and keeps the
   rest. Paging always resets -- a `before` id from the unfiltered feed is
   meaningless once the filter changes. */
function ac_link($overrides = array()) {
	global $ac_scope, $ac_chan;
	$q = array('scope' => $ac_scope, 'channel' => $ac_chan);
	foreach ($overrides as $k => $v) $q[$k] = $v;
	foreach ($q as $k => $v) if ($v === '' || $v === null) unset($q[$k]);
	return 'activity.php' . ($q ? '?' . http_build_query($q) : '');
}

include 'header.php';
?>
<style>
/* Scoped to this page. The wall is the only thing here, so it gets the full
   width rather than sitting in the .col1of3 one-column grid the table pages
   use -- a card wall at a third of the viewport is a list with pictures. */
#ac-wrap { padding: 20px; width: 100%; }
#ac-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px 18px; margin-bottom: 4px; }
#ac-head h2 { margin: 0; }
#ac-head .ac-sub { color: #9fb4c4; font-size: .82rem; }

/* Filters. The scope is a segmented control and the channel is a select,
   because they are different kinds of choice: three fixed viewpoints on the
   same feed, versus one of ~20 sources. */
#ac-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 14px 0 20px; }
/* Square, like every other control on the platform -- flexbox.css squared the
   panels, buttons and cards page by page and has a bare `select {
   border-radius: 0 }` baseline to stop dropdowns being the one round thing
   left on a screen. A rounded segmented control next to a square select
   would reintroduce exactly that. */
.ac-seg { display: inline-flex; border: 1px solid rgba(0,200,160,0.25); overflow: hidden; }
/* 8px, not 7: it puts the segmented control's outer height at 33px, the same
   as the select beside it. At 7px the two sat 2px apart along the top edge. */
.ac-seg a { display: block; padding: 8px 14px; font-size: .8rem; color: #9fb4c4; text-decoration: none;
  background: #0a1929; transition: background .15s, color .15s; white-space: nowrap; }
.ac-seg a + a { border-left: 1px solid rgba(0,200,160,0.18); }
.ac-seg a:hover { color: #c8dce8; background: #0f2236; }
.ac-seg a.on { background: #00c8a0; color: #04222c; font-weight: 700; }
/*
 * THE CHANNEL FILTER WEARS THE PLATFORM'S DROPDOWN, NOT THE BROWSER'S.
 *
 * This set only padding and font-size at first, which leaves every colour to
 * the user agent -- so on a dark page the one white, rounded, system-styled
 * box on screen was this. The values are flexbox.css's own dropdown
 * convention (#filterLeaderboard / .dropdown), not new ones.
 *
 * The explicit rule on <option> is not redundant: a select's own background
 * does not inherit into its popup list on Windows or Android, and the
 * options would stay white-on-white there.
 */
#ac-filters select {
  padding: 6px 10px; font-size: .8rem; font-weight: 700;
  font-family: Arial, sans-serif;
  background-color: #0d1e2e; color: #D6DDDE;
  border: 1px solid rgba(0,200,160,0.18); border-radius: 0;
  height: 33px;
}
#ac-filters select option { background-color: #0d1e2e; color: #D6DDDE; font-weight: 400; }

/* The wall. auto-fill, not auto-fit: a single card should stay card-width
   instead of stretching across the viewport, which is what happens the moment
   a filter narrows the feed to one result. */
/* Items STRETCH to the row's tallest, which is the default and is kept
   deliberately. A text-only card next to one with square art therefore has
   some empty space below its text. The alternative, align-items: start,
   gives every card its natural height and in exchange leaves ragged holes
   under the short ones, because grid still starts the next row below the
   tallest card in this one -- it trades the flaw rather than fixing it.
   Real masonry would fix it and costs the reading order: CSS columns fill
   top-to-bottom per column, so on a feed sorted newest-first the seventh
   card lands at the top of column two. Not worth it for a chronology. */
#ac-wall { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; }

.ac-card { display: flex; flex-direction: column; background: #0d1e2e; border-radius: 10px;
  overflow: hidden; border: 1px solid rgba(0,200,160,0.10);
  /* The platform stylesheet centres body text, which is right for the panels
     it was written for and wrong for a feed: centred ragged paragraphs have
     no common left edge to scan down, so six cards read as six separate
     blocks instead of a list. Reset it here rather than page-wide. */
  text-align: left;
  /* The accent edge carries the channel's own embed colour -- see the inline
     style on each card. It is the only place the colour is used, so a post
     with none simply has a neutral edge. */
  border-left: 3px solid #1d4256; transition: border-color .15s, transform .15s; }
.ac-card:hover { transform: translateY(-2px); }

.ac-top { display: flex; align-items: center; gap: 9px; padding: 11px 13px 0; }
.ac-av { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex: 0 0 30px;
  background: #07111d; }
.ac-who { min-width: 0; flex: 1; }
.ac-who .ac-name { font-size: .78rem; color: #c8dce8; font-weight: 700;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ac-who .ac-when { font-size: .7rem; color: #7c93a6; }
.ac-chip { font-size: .62rem; letter-spacing: .04em; text-transform: uppercase; font-weight: 700;
  color: #00c8a0; background: rgba(0,200,160,0.10); border-radius: 20px; padding: 3px 9px;
  white-space: nowrap; flex: 0 0 auto; }

.ac-body { padding: 10px 13px 13px; }
.ac-title { font-size: .92rem; color: #e6f1f7; font-weight: 700; line-height: 1.35; margin: 0 0 6px; }
.ac-desc { font-size: .78rem; color: #9fb4c4; line-height: 1.55; margin: 0;
  /* Announcements run from one line to a dozen. Clamping keeps the wall a
     wall; the card links to the thing itself for the rest. */
  display: -webkit-box; -webkit-line-clamp: 7; -webkit-box-orient: vertical; overflow: hidden; }
.ac-desc a { color: #00c8a0; }
.ac-desc code { background: #07111d; padding: 1px 4px; border-radius: 3px; font-size: .92em; }
.ac-foot { font-size: .68rem; color: #6d8395; padding: 0 13px 12px; }

/*
 * THE IMAGE SLOT HOLDS TWO DIFFERENT KINDS OF THING.
 *
 * Most announcements carry ARTWORK -- mission art, a Fighter render, a realm
 * scene -- and essentially all of it is square. A 240px-tall cover crop was
 * therefore slicing the top and bottom off every one of them for no reason,
 * so the frame is square and the art arrives whole.
 *
 * The daily reward announce carries a CURRENCY MARK instead: icons/<cur>.png,
 * 120-128px, a flat white glyph meant to be seen at 24px. Rendered in the
 * same frame it became a 290px blob that dwarfed the card it belonged to and
 * said less than the "Reward: 20 MUSE" line already above it. It gets an
 * emblem treatment instead -- small, contained, on its own slim band -- which
 * also reads as what it is rather than pretending to be art.
 *
 * loading="lazy" still matters either way: some of this art is 5000x5000
 * (Danketsu), and it is what stops a full page decoding a few hundred
 * megapixels.
 */
/* cover, on a square frame. The art is mostly square already -- realm
   scenes, Fighter renders, Monstrocity bosses -- so cover crops essentially
   nothing off it, and it avoids the letterbox bars contain would put on
   every card to accommodate the minority that are not. The frame being
   square is what does the real work: the old 240px-tall crop was slicing the
   top and bottom off square art, which is the whole thing that was hiding
   the picture. Anything genuinely taller than square, like some mission art,
   is cropped to its middle, which is the accepted trade. */
.ac-shot img { display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: cover;
  background: #07111d; }
.ac-shot.is-mark { display: flex; align-items: center; justify-content: center;
  padding: 20px; background: rgba(255,255,255,0.02);
  border-top: 1px solid rgba(0,200,160,0.08); }
.ac-shot.is-mark img { width: 56px; height: 56px; aspect-ratio: auto;
  object-fit: contain; opacity: .85; }
.ac-link { display: flex; flex-direction: column; flex: 1; }

/*
 * THE WHOLE CARD IS CLICKABLE, AND THE CARD IS NOT AN <a>.
 *
 * It was, and that was a real bug: activity_format() turns a Discord
 * [text](url) into an anchor, and plenty of announcements carry one. An <a>
 * inside an <a> is invalid HTML, so the parser CLOSES the outer one early --
 * the card's own body was being hoisted out of its link entirely, which is
 * how it was caught (one card in the preview had a .ac-link with a single
 * child while every other had three).
 *
 * So the link is an empty overlay stretched across the card instead. The
 * description's own anchors sit above it on z-index and keep working, which
 * is also the better behaviour: a link in the text should go where it says,
 * not where the card goes.
 */
.ac-card { position: relative; }
.ac-stretch { position: absolute; inset: 0; z-index: 1; }
.ac-desc a { position: relative; z-index: 2; }
.ac-card:hover .ac-title { color: #00c8a0; }

#ac-more { margin: 24px 0 0; text-align: center; }
.ac-empty { background: #0a1929; border-radius: 10px; padding: 26px 22px; color: #9fb4c4;
  font-size: .84rem; line-height: 1.6; max-width: 620px; }
.ac-empty b { color: #c8dce8; }

@media (max-width: 600px) {
  #ac-wall { grid-template-columns: 1fr; }
  #ac-wrap { padding: 14px; }
}
</style>
		<div class="row" id="row1">
			<div id="ac-wrap">
				<div id="ac-head">
					<h2>Activity</h2>
					<span class="ac-sub">What Skull Bot has been announcing across the platform.</span>
				</div>

				<div id="ac-filters">
					<?php if ($me > 0) { ?>
					<div class="ac-seg">
						<a href="<?php echo htmlspecialchars(ac_link(array('scope' => 'all')), ENT_QUOTES); ?>"
						   class="<?php echo $ac_scope === 'all' ? 'on' : ''; ?>">All activity</a>
						<a href="<?php echo htmlspecialchars(ac_link(array('scope' => 'mine')), ENT_QUOTES); ?>"
						   class="<?php echo $ac_scope === 'mine' ? 'on' : ''; ?>">My activity</a>
						<?php /* Not the same as "mine": an Arena battle fought AGAINST you is
						         something you were involved in without making a request. */ ?>
						<a href="<?php echo htmlspecialchars(ac_link(array('scope' => 'involving')), ENT_QUOTES); ?>"
						   class="<?php echo $ac_scope === 'involving' ? 'on' : ''; ?>">Involving me</a>
					</div>
					<?php } ?>

					<?php if ($ac_chans) { ?>
					<select onchange="location.href=this.value;">
						<option value="<?php echo htmlspecialchars(ac_link(array('channel' => '')), ENT_QUOTES); ?>"
							<?php echo $ac_chan === '' ? 'selected' : ''; ?>>Everything</option>
						<?php foreach ($ac_chans as $c => $n) { ?>
						<option value="<?php echo htmlspecialchars(ac_link(array('channel' => $c)), ENT_QUOTES); ?>"
							<?php echo $ac_chan === $c ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars(activity_channel_label($c)) . ' (' . (int) $n . ')'; ?>
						</option>
						<?php } ?>
					</select>
					<?php } ?>
				</div>

				<?php if (!$ac_rows) { ?>
				<div class="ac-empty">
					<?php if ($ac_scope === 'involving' && $ac_chan === '') { ?>
					<?php /* This tab is strictly passive, so empty is a NORMAL state --
					         it means nobody has come at you yet, not that anything is
					         broken. Say what the tab is for rather than leaving a
					         player to guess from a blank panel. */ ?>
					<b>Nobody has come for you yet.</b><br>
					This is where things other people do <em>to</em> you show up - a Gauntlet
					run against one of your NFTs, an Arena battle fought while you were away,
					a challenge sent your way, someone winning something you listed.
					Anything you did yourself is under <a href="<?php echo htmlspecialchars(ac_link(array('scope' => 'mine')), ENT_QUOTES); ?>">My activity</a>.
					<?php } else if ($ac_scope !== 'all' || $ac_chan !== '') { ?>
					<b>Nothing here yet.</b><br>
					No activity matches this filter. Try <a href="activity.php">all activity</a>.
					<?php } else { ?>
					<b>The feed is still filling up.</b><br>
					Activity is recorded as it is announced, so this wall starts from the day
					the feature went live rather than from the beginning of the platform.
					Play something and you will be the first card on it.
					<?php } ?>
				</div>
				<?php } else { ?>

				<div id="ac-wall">
					<?php foreach ($ac_rows as $r) {
						$edge  = preg_match('/^[0-9A-Fa-f]{6}$/', (string) $r['color']) && $r['color'] !== '000000'
						       ? '#' . $r['color'] : '#1d4256';
						$href  = (string) $r['url'];
						/* Only http(s). The column is written from discordmsg()'s $url, which
						   is platform-controlled today, but a javascript: href here would be
						   a stored XSS on a page every member loads. */
						if (!preg_match('~^https?://~i', $href)) $href = '';
						$av    = (string) $r['author_icon'];
						if ($av === '') $av = (string) $r['thumbnail'];
						$who   = (string) $r['author_name'];
						$img   = (string) $r['image_url'];
					?>
					<div class="ac-card" style="border-left-color: <?php echo htmlspecialchars($edge, ENT_QUOTES); ?>;">
						<?php if ($href !== '') { ?>
						<?php /* Empty by design -- see .ac-stretch. aria-label because an
						         anchor with no text content is unreachable otherwise. */ ?>
						<a class="ac-stretch" href="<?php echo htmlspecialchars($href, ENT_QUOTES); ?>"
						   aria-label="<?php echo htmlspecialchars((string) $r['title'] !== ''
						       ? (string) $r['title'] : activity_channel_label($r['channel']), ENT_QUOTES); ?>"></a>
						<?php } ?>
						<div class="ac-link">
							<div class="ac-top">
								<?php if ($av !== '') { ?>
								<img class="ac-av" src="<?php echo htmlspecialchars($av, ENT_QUOTES); ?>"
								     alt="" loading="lazy" onerror="this.src='icons/skull.png'">
								<?php } ?>
								<div class="ac-who">
									<?php if ($who !== '') { ?>
									<div class="ac-name"><?php echo htmlspecialchars($who); ?></div>
									<?php } ?>
									<div class="ac-when" title="<?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES); ?>">
										<?php echo htmlspecialchars(activity_ago($r['created_at'])); ?>
									</div>
								</div>
								<span class="ac-chip"><?php echo htmlspecialchars(activity_channel_label($r['channel'])); ?></span>
							</div>
							<div class="ac-body">
								<?php if ((string) $r['title'] !== '') { ?>
								<p class="ac-title"><?php echo htmlspecialchars((string) $r['title']); ?></p>
								<?php } ?>
								<?php if ((string) $r['description'] !== '') { ?>
								<?php /* activity_format() escapes first and builds the only tags in
								         its own output -- it is the XSS boundary for this page. */ ?>
								<p class="ac-desc"><?php echo activity_format($r['description'], $ac_names); ?></p>
								<?php } ?>
							</div>
							<?php if ($img !== '') {
								/* /icons/ is the platform's ornament directory -- currency
								   marks, chain marks, the skull. Nothing in it is artwork,
								   so anything from it gets the emblem treatment with no
								   measuring and no layout shift. acShot() below is the
								   belt-and-braces for everything else: this feed absorbs
								   new announcements automatically, by design, so a future
								   one passing a small image should not have to wait for
								   someone to notice it looks wrong. */
								$is_mark = (bool) preg_match('~/icons/[^/]+$~i', $img);
							?>
							<div class="ac-shot<?php echo $is_mark ? ' is-mark' : ''; ?>">
								<img src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>"
								     alt="" loading="lazy" onload="acShot(this)"
								     onerror="this.parentNode.style.display='none'">
							</div>
							<?php } ?>
							<?php if ((string) $r['footer_text'] !== '') { ?>
							<div class="ac-foot"><?php echo htmlspecialchars((string) $r['footer_text']); ?></div>
							<?php } ?>
						</div>
					</div>
					<?php } ?>
				</div>

				<?php if ($ac_more) {
					$last = (int) $ac_rows[count($ac_rows) - 1]['id'];
				?>
				<div id="ac-more">
					<a class="button" href="<?php echo htmlspecialchars(ac_link(array('before' => $last)), ENT_QUOTES); ?>">Load older</a>
				</div>
				<?php } ?>

				<?php } ?>
			</div>
		</div>
		<!-- Footer -->
		<div class="footer">
		  <p>Skulliance<br>Copyright © <span id="year"></span>
		</div>
	</div>
  </div>
</body>
<?php $conn->close(); ?>
<script>
/* A small source image in the artwork frame is the daily-reward bug in its
   general form. The /icons/ path check server-side catches the one case that
   exists today with no flash; this catches anything new. 200px is the
   threshold because the frame is ~290px wide -- below that an image is being
   upscaled, which is the thing that looked wrong. */
function acShot(img) {
	if (img.naturalWidth && img.naturalWidth < 200) img.parentNode.classList.add('is-mark');
}
/* A cached image can finish before the handler is attached, so sweep once. */
document.addEventListener('DOMContentLoaded', function () {
	var imgs = document.querySelectorAll('.ac-shot img');
	for (var i = 0; i < imgs.length; i++) if (imgs[i].complete) acShot(imgs[i]);
});
</script>
<script type="text/javascript" src="skulliance.js"></script>
</html>

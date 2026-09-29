<?php
/**
 * homepage-preview.php — a PROPOSED first screen, for looking at.
 *
 * Nothing links here and nothing includes it. It exists so the new hero can
 * be judged against the live one before anything replaces it; delete it, or
 * fold its <header> into homepage.php, once a call has been made.
 *
 * WHAT IT IS TRYING TO FIX, measured against omenati.com:
 *
 *                       omenati.com     homepage.php
 *   visible text        1,345 chars     7,072 chars
 *   headings            ~4              40
 *   images              ~30             100
 *   hero asks you to    do one thing    choose between three
 *
 * The problem is not the depth -- that depth is why match3rpg.php ranks --
 * it is that the first screen does none of the work. Today's H1 is
 * "The premier skull NFT collective on Cardano - artists, staking rewards,
 * free browser games, and exclusive merch in one community": 128 characters
 * describing a CATEGORY. It says what Skulliance is and never what a
 * visitor gets or does. OMEN's is "Ten thousand faces. Find yours." --
 * thirty characters, a promise and an instruction.
 *
 * So: one promise, one sub-line carrying the old feature list, ONE primary
 * action, four live numbers, and the art on screen. Everything currently on
 * the homepage stays exactly where it is, underneath.
 */
require_once __DIR__ . '/homepage-data.php';

/*
 * THE HEADLINE. Two lines, like OMEN's: name the problem, then the escape.
 * It leads on the one thing that is actually different about Skulliance --
 * most NFTs do nothing, and these do -- rather than on the category.
 * Alternates kept here because choosing is the point of this page:
 *   'Skull art that does something.' / ''
 *   'Ten artists. Seven games.' / 'One collection that plays.'
 */
$hero_line1 = 'Most NFTs just sit there.';
$hero_line2 = "Yours don't have to.";

/* Live, from homepage-data.php -- each falls back to a sane number rather
   than a zero if the database is unreachable, because a homepage claiming
   nothing is staked is worse than one slightly out of date. */
$stat_staked   = hp_stat_nfts_staked();
$stat_artists  = hp_stat_artists();
$stat_chains   = hp_stat_chains();

/* The art. These are on the server already and used further down the live
   page, so nothing new has to be uploaded to try this. */
$hero_art = array('sinderskullz.png','kimosabe.png','crypties.png','galactico.png',
                  'ohhmeed.png','hype.png','maxingo.png','darkula.jpg','skowl.jpg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Homepage first screen - proposal</title>
<meta name="robots" content="noindex,nofollow">
<style>
  *,*::before,*::after { box-sizing: border-box; }
  body {
    margin: 0; background: #07111d; color: #e8eaed; line-height: 1.55;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    -webkit-font-smoothing: antialiased; overflow-x: hidden;
  }
  a { color: #00c8a0; text-decoration: none; }
  img { max-width: 100%; height: auto; display: block; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

  .nh {
    text-align: center; padding: 64px 20px 44px;
    background:
      radial-gradient(circle at 50% 0%, rgba(0,200,160,.20), transparent 62%),
      linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
    border-bottom: 1px solid rgba(255,255,255,.08);
  }
  /* The promise. Big, and the second line carries the turn -- the same
     two-beat shape OMEN uses, which is what makes a headline quotable. */
  .nh h1 {
    margin: 0 0 18px; font-weight: 800; line-height: 1.05;
    font-size: clamp(2.1rem, 6vw, 4.2rem); letter-spacing: -.02em;
  }
  .nh h1 .turn {
    display: block;
    background: linear-gradient(135deg, #00c8a0, #0596c4);
    -webkit-background-clip: text; background-clip: text; color: transparent;
  }
  /* Today's H1, demoted to where a feature list belongs. */
  .nh .sub {
    max-width: 680px; margin: 0 auto 26px;
    font-size: 1.05rem; color: #b9c7d4;
  }
  .nh .ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  .btn {
    display: inline-block; padding: 14px 30px; border-radius: 999px;
    font-weight: 700; font-size: 1rem;
    background: linear-gradient(135deg, #00c8a0, #0596c4); color: #04121b !important;
    box-shadow: 0 6px 24px rgba(0,200,160,.3);
  }
  .btn.ghost {
    background: transparent; color: #00c8a0 !important;
    border: 1px solid rgba(0,200,160,.45); box-shadow: none;
  }
  .nh .note { margin: 14px 0 0; font-size: .82rem; color: #5a7888; }

  /* FOUR NUMBERS. Concrete, live, and the cheapest credibility on the page
     -- OMEN runs the same idea and it is the thing that says "alive". */
  .stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
    max-width: 860px; margin: 34px auto 0;
  }
  @media (max-width: 620px) { .stats { grid-template-columns: repeat(2, 1fr); } }
  .stat {
    background: rgba(10,25,41,.7); border: 1px solid rgba(0,200,160,.14);
    border-radius: 12px; padding: 16px 10px;
  }
  .stat b { display: block; font-size: 1.6rem; color: #00c8a0; line-height: 1.1; }
  .stat span { font-size: .74rem; color: #7a9eb0; letter-spacing: .04em; text-transform: uppercase; }

  /* The art, on the first screen instead of thousands of pixels down. */
  .artstrip {
    width: 100vw; margin: 36px calc(50% - 50vw) 0; overflow: hidden;
    -webkit-mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent);
            mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent);
  }
  .artstrip-track {
    display: flex; gap: 16px; width: max-content;
    animation: artscroll 55s linear infinite;
  }
  .artstrip-track:hover { animation-play-state: paused; }
  @keyframes artscroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
  @media (prefers-reduced-motion: reduce) { .artstrip-track { animation: none; } }
  .artstrip img {
    width: 132px; height: 132px; object-fit: cover; border-radius: 12px;
    border: 1px solid rgba(0,200,160,.14); flex: 0 0 auto;
  }

  .after { padding: 46px 20px 70px; text-align: center; color: #7a9eb0; }
  .after h2 { color: #e8eaed; font-size: 1.15rem; margin: 0 0 8px; }
  .after p { max-width: 640px; margin: 0 auto 8px; font-size: .92rem; }
</style>
</head>
<body>

<?php include __DIR__ . '/site-header.php'; ?>

<header class="nh">
  <div class="wrap">
    <h1><?php echo htmlspecialchars($hero_line1); ?>
      <?php if ($hero_line2 !== ''): ?><span class="turn"><?php echo htmlspecialchars($hero_line2); ?></span><?php endif; ?>
    </h1>

    <?php /* The old H1, demoted. A feature list is a fine SUB-line -- it is
             only a bad headline. Two chains is new and is stated plainly
             rather than left to be discovered. */ ?>
    <p class="sub">Skulliance is a collective of skull NFT artists on <strong>Cardano and the XRP Ledger</strong>.
       Stake your art for daily rewards, send it on missions, or take it into seven free browser games.
       No signup to play.</p>

    <?php /* ONE primary action. Three CTAs is zero CTAs: a stranger cannot
             pick. Play is the lowest-friction door on the site and the one
             nothing else on Cardano has. */ ?>
    <div class="ctas">
      <a class="btn" href="/#games">Play a game, free</a>
      <a class="btn ghost" href="/staking/">Stake your NFTs</a>
    </div>
    <p class="note">No wallet needed to play. No download. Works on your phone.</p>

    <?php
    /*
     * A ZERO IS WORSE THAN A GAP. If the database is unreachable the stats
     * fall back to 0, and a homepage announcing "0 NFTs staked" actively
     * argues against itself -- it is the one number a sceptical visitor
     * will believe instantly. So a stat that cannot be proven is dropped,
     * and the row lays out whatever is left rather than showing a hole.
     *
     * The two static ones always show: seven games is a fact about the
     * site, and the chain count is read from the database but floors at 1
     * because Cardano is not in doubt.
     */
    $tiles = array();
    if ($stat_staked  > 0) $tiles[] = array(number_format($stat_staked),  'NFTs staked');
    if ($stat_artists > 0) $tiles[] = array(number_format($stat_artists), 'Artists');
    $tiles[] = array('7', 'Free games');
    $tiles[] = $stat_chains >= 2
             ? array('2', 'Cardano + XRPL')
             : array('1', 'Cardano');
    ?>
    <div class="stats" style="grid-template-columns: repeat(<?php echo min(4, count($tiles)); ?>, 1fr)">
      <?php foreach ($tiles as $t): ?>
      <div class="stat"><b><?php echo $t[0]; ?></b><span><?php echo htmlspecialchars($t[1]); ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="artstrip">
    <div class="artstrip-track">
      <?php
      /* Repeat until a HALF-track is wider than any viewport, and keep the
         count EVEN so the two halves match -- the -50% loop shows a gap at
         the turn otherwise. Nine tiles at 148px is 1332px a half, narrower
         than a desktop. Same trap as the Fighter marquee on dhcgame.php. */
      $passes = max(2, 2 * (int)ceil(20 / max(1, count($hero_art))));
      for ($pass = 0; $pass < $passes; $pass++): foreach ($hero_art as $f): ?>
      <img src="/staking/images/projects/<?php echo htmlspecialchars($f); ?>"
           alt="" loading="lazy" decoding="async">
      <?php endforeach; endfor; ?>
    </div>
  </div>
</header>

<div class="after">
  <h2>— everything currently on the homepage continues from here, unchanged —</h2>
  <p>This page is a proposal for the first screen only. The nine sections below the fold
     on the live homepage are not touched: the depth is why the game pages rank, and the
     argument is about what a stranger meets first, not about deleting anything.</p>
  <p><a href="/">See the live homepage</a></p>
</div>

</body>
</html>

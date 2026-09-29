<?php
/**
 * dhcgame.php — public landing page for the DHC ecosystem:
 * build a Fighter, browse the Collection, fight in the Arena.
 *
 * SESSION HANDLING IS COPIED FROM match3rpg.php ON PURPOSE. Bare
 * session_start(), then restore from the 6-month SessionCookie if PHPSESSID
 * has lapsed, merging rather than assigning. NO db.php: that include was
 * what kicked stakers out of the other landing pages, and this one does not
 * need it -- the Fighter art comes through homepage-data.php, which opens
 * its own connection and touches no session.
 */
session_start();

if (!isset($_SESSION['logged_in'])) {
    if (isset($_COOKIE['SessionCookie'])) {
        $cookieData = json_decode($_COOKIE['SessionCookie'], true);
        if (is_array($cookieData)) {
            // Merge, not replace -- see skulliance.php's own fix for why.
            $_SESSION = array_merge((array)$_SESSION, $cookieData);
        }
    }
}
$is_logged_in = !empty($_SESSION['logged_in']);

require_once __DIR__ . '/homepage-data.php';

/*
 * REAL FIGHTERS, NOT MOCKUPS. The page's whole claim is that players build
 * these, and invented combinations would be both a lie and worse art --
 * people make stranger Fighters than a generator does. If the query finds
 * nothing the strips are simply not rendered; a marketing page with empty
 * frames is worse than one section shorter.
 */
$dhc_fighters = hp_dhc_fighters(28);

/* Art root, resolved the way every other DHC page resolves it, so this page
   cannot disagree with the assembler about where the layers live. */
$ART = '';
foreach (array('dhc/web', 'web', 'dhc', 'traits') as $c) {
    if (is_dir(__DIR__ . '/' . $c . '/250')) { $ART = $c; break; }
}
/* Which directory a slot's art actually sits in. Same map the Arena uses:
   both effects slots draw from effects/, weaponBack from weapon/. */
$SLOT_CAT = array('effects1' => 'effects', 'effects2' => 'effects', 'weaponBack' => 'weapon');

require_once __DIR__ . '/dhc-compose.php';

/**
 * One Fighter's picture.
 *
 * Prefers a single small image derived from the CANONICAL render -- the
 * same dhcf_render_fighter() the Discord embed uses, so this page cannot
 * draw a Fighter differently from the assembler. Falls back to stacking the
 * layers in the browser if GD is not available.
 *
 * The flattened form is not an optimisation here, it is what makes the
 * marquee work at all: a lazy image loads from its LAYOUT position, and a
 * marquee card sits off-screen and only arrives by transform, so with eight
 * layers a card it has never "seen" slides in empty. Measured: 88 of 384
 * layer images never loaded.
 */
function dhc_fighter_card(array $f, $ART, array $SLOT_CAT) {
	$t = isset($f['traits']) ? $f['traits'] : array();
	$order = (!empty($f['layers'])) ? $f['layers'] : array_keys($t);
	$flat = dhc_fighter_thumb($t, isset($f['serial']) ? $f['serial'] : 0);
	if ($flat !== '') {
		/*
		 * NOT loading="lazy". A lazy image loads from its LAYOUT position,
		 * and every card past the first screen sits off to the right and
		 * only arrives by transform -- so lazy here means permanently
		 * blank, which is what the first build did.
		 *
		 * Eager is affordable precisely because the marquee repeats: all
		 * 48 cards point at ~14 distinct URLs, and a browser fetches each
		 * URL once. The repeats are cache hits.
		 */
		return '<img class="dhc-flat" decoding="async" alt="" src="'
		     . htmlspecialchars($flat) . '" onerror="this.remove()">';
	}
	return dhc_fighter_art($f, $ART, $SLOT_CAT);
}

/** One Fighter as stacked layer images, in the order the assembler draws. */
function dhc_fighter_art(array $f, $ART, array $SLOT_CAT) {
    $t = isset($f['traits']) ? $f['traits'] : array();
    $order = (!empty($f['layers'])) ? $f['layers'] : array_keys($t);
    $out = '';
    /* Background first and outside the order, exactly as the Arena does --
       it is the plate everything else sits on, never a stacked layer. */
    if (!empty($t['background'])) {
        $out .= '<img class="dhc-bg" loading="lazy" decoding="async" alt="" src="'
              . htmlspecialchars($ART . '/250/background/' . $t['background'] . '.png')
              . '" onerror="this.remove()">';
    }
    foreach ($order as $slot) {
        if ($slot === 'background' || empty($t[$slot])) continue;
        $dir = isset($SLOT_CAT[$slot]) ? $SLOT_CAT[$slot] : $slot;
        $out .= '<img loading="lazy" decoding="async" alt="" src="'
              . htmlspecialchars($ART . '/250/' . $dir . '/' . $t[$slot] . '.png')
              . '" onerror="this.remove()">';
    }
    return $out;
}

$canonical  = 'https://www.skulliance.io/staking/dhcgame.php';
/* The Arena mid-battle: both Crews, the board, the terrain name. A share
   card should show the thing, and this is the thing. 1996x1255 clears the
   1200x630 minimum X and Facebook want, so neither crops it to a strip. */
$og_image   = 'https://www.skulliance.io/staking/images/dhcgame.png';
$shot_url   = 'images/dhcgame.png';
$page_title = 'Digi-Hell Corps - Build NFT Characters and Battle Them Free';
$page_desc  = 'Build a Digi-Hell Corps Fighter from real NFT traits, browse the full collection, and take your squad into the Arena - a free match-3 battler. No download, no wallet needed to play.';
$short_desc = 'Build a Fighter from NFT traits, browse the collection, and battle in the Arena. Free in your browser.';

/* Relative hrefs throughout. Session cookies are host-only -- no domain= on
   SessionCookie or PHPSESSID -- so an absolute www. link logs out anybody
   sessioned on the bare domain. Absolute URLs appear ONLY in SEO markup. */
$href_arena     = 'dhcarena.php';
$href_fighters  = 'dhcfighters.php';
$href_gallery   = 'dhcgallery.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_desc); ?>">
  <meta name="keywords" content="digi-hell corps, dhc, nft character builder, build nft character, nft trait builder, free match 3 battler, nft battle game, browser battle game, nft character creator, cardano nft game, skull nft game, nft arena game, free browser rpg, pvp match 3, nft collection browser">
  <meta name="theme-color" content="#07111d">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
  <link rel="canonical" href="<?php echo $canonical; ?>">

  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="<?php echo $canonical; ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($page_desc); ?>">
  <meta property="og:image" content="<?php echo $og_image; ?>">
  <meta property="og:image:alt" content="A Digi-Hell Corps Arena battle: two Crews of three Fighters either side of a seven-by-seven gem board">
  <meta property="og:locale" content="en_US">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($short_desc); ?>">
  <meta name="twitter:image" content="<?php echo $og_image; ?>">
  <meta name="twitter:image:alt" content="A Digi-Hell Corps Arena battle in progress">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "VideoGame",
        "name": "Digi-Hell Corps Arena",
        "alternateName": ["DHC Arena", "Digi-Hell Corps"],
        "url": "<?php echo $canonical; ?>",
        "image": "<?php echo $og_image; ?>",
        "description": <?php echo json_encode($page_desc); ?>,
        "genre": ["Match 3", "Strategy", "Collectible Card Game", "Puzzle"],
        "gamePlatform": ["Web Browser", "Mobile Web", "Tablet", "Desktop"],
        "operatingSystem": ["Any (browser-based)", "Windows", "macOS", "Linux", "iOS", "Android"],
        "applicationCategory": "GameApplication",
        "playMode": ["SinglePlayer", "MultiPlayer"],
        "inLanguage": "en",
        "isAccessibleForFree": true,
        "offers": {
          "@type": "Offer", "price": "0", "priceCurrency": "USD",
          "availability": "https://schema.org/InStock",
          "url": "https://www.skulliance.io/staking/dhcarena.php"
        },
        "publisher": { "@type": "Organization", "name": "Skulliance", "url": "https://www.skulliance.io/" },
        "potentialAction": { "@type": "PlayAction", "target": "https://www.skulliance.io/staking/dhcarena.php" }
      },
      {
        "@type": "BreadcrumbList",
        "itemListElement": [
          { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
          { "@type": "ListItem", "position": 2, "name": "Digi-Hell Corps", "item": "<?php echo $canonical; ?>" }
        ]
      }
    ]
  }
  </script>

  <style>
    *,*::before,*::after { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      background: #07111d; color: #e8eaed; line-height: 1.55;
      -webkit-font-smoothing: antialiased; overflow-x: hidden;
    }
    a { color: #00c8a0; text-decoration: none; }
    a:hover, a:focus { color: #34e3bb; text-decoration: underline; }
    img { max-width: 100%; height: auto; display: block; }
    h1, h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
    h1 { font-size: clamp(1.9rem, 4.5vw, 3.2rem); }
    h2 { font-size: clamp(1.5rem, 3vw, 2.2rem); margin-top: 1.5em; }
    h3 { font-size: 1.15rem; color: #00c8a0; }
    p  { margin: 0 0 1em; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
    main { padding: 0 0 64px; }
    .center { text-align: center; }
    .lede { font-size: 1.05rem; color: #b9c7d4; max-width: 720px; margin: 0 auto 1.2em; }

    /* Exit for logged-in arrivals, same pattern as match3rpg.php */
    #dhc-exit {
      position: fixed; top: calc(env(safe-area-inset-top, 0px) + 8px);
      left: calc(env(safe-area-inset-left, 0px) + 8px); z-index: 99990;
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 12px; min-height: 36px;
      background: rgba(18,18,18,.85); color: #e8eaed;
      border: 1px solid rgba(0,200,160,.45); border-radius: 999px;
      font-size: .82rem; font-weight: 600;
      box-shadow: 0 2px 10px rgba(0,0,0,.4);
      backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    #dhc-exit:hover { background: rgba(0,200,160,.18); border-color: #00c8a0; text-decoration: none; color: #e8eaed; }
    #dhc-exit .mx-arrow { font-size: 1.05rem; line-height: 1; color: #00c8a0; }
    @media (max-width: 480px) { #dhc-exit .mx-label { display: none; } }

    .hero {
      text-align: center; padding: 56px 20px 40px;
      background:
        radial-gradient(circle at 50% 0%, rgba(0,200,160,.18), transparent 60%),
        linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .hero .kicker {
      display: inline-block; font-size: .75rem; letter-spacing: .18em;
      text-transform: uppercase; color: #7a9eb0; margin-bottom: 14px;
    }
    .cta-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
    .btn {
      display: inline-block; padding: 13px 26px; border-radius: 999px;
      font-weight: 700; font-size: .98rem;
      background: linear-gradient(135deg, #00c8a0, #0596c4); color: #04121b !important;
      box-shadow: 0 6px 22px rgba(0,200,160,.28);
    }
    .btn:hover { text-decoration: none; filter: brightness(1.08); }
    .btn.ghost {
      background: transparent; color: #00c8a0 !important;
      border: 1px solid rgba(0,200,160,.45); box-shadow: none;
    }
    .btn.ghost:hover { background: rgba(0,200,160,.12); }

    /* ---- the Fighter marquee -------------------------------------------
       Two counter-scrolling rows, each holding its list TWICE so the -50%
       translate loops seamlessly; the second pass is aria-hidden so screen
       readers and tab order see each Fighter once. Same technique as the
       homepage partner strip. */
    .dhc-strip {
      width: 100vw; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw);
      overflow: hidden; padding: 16px 0;
      -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
              mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
    }
    .dhc-strip + .dhc-strip { padding-top: 0; }
    .dhc-track {
      display: flex; align-items: flex-start; gap: 20px;
      width: max-content; animation: dhc-scroll 70s linear infinite; will-change: transform;
    }
    .dhc-track.rev { animation-direction: reverse; animation-duration: 78s; }
    .dhc-track:hover { animation-play-state: paused; }
    @keyframes dhc-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    @media (prefers-reduced-motion: reduce) { .dhc-track { animation: none; } }

    .dhc-card { flex: 0 0 auto; width: 150px; text-align: center; }
    /* The stack. Every layer is absolutely positioned over the same square,
       which is what makes ten PNGs read as one character. */
    .dhc-art {
      position: relative; width: 150px; height: 150px;
      border-radius: 12px; overflow: hidden;
      background: #0a1929; border: 1px solid rgba(0,200,160,.14);
    }
    .dhc-art img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
    .dhc-art img.dhc-bg { object-fit: cover; }
    /* The flattened form already contains the background, so it fills. */
    .dhc-art img.dhc-flat { object-fit: cover; }
    .dhc-name {
      margin-top: 8px; font-size: .74rem; font-weight: 600; color: #9fb4c4;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    section { padding-top: 8px; }
    .rule { border: 0; border-top: 1px solid rgba(255,255,255,.08); margin: 48px 0 0; }
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin: 28px 0 8px; }
    .card {
      background: #0a1929; border: 1px solid rgba(0,200,160,.14);
      border-radius: 14px; padding: 22px;
    }
    .card h3 { margin-top: 0; }
    .card p { color: #b9c7d4; font-size: .93rem; margin-bottom: .8em; }
    .card .more { font-size: .86rem; font-weight: 600; }
    .steps { counter-reset: step; list-style: none; padding: 0; margin: 24px 0 0; }
    .steps li {
      counter-increment: step; position: relative; padding-left: 46px; margin-bottom: 18px; color: #b9c7d4;
    }
    .steps li::before {
      content: counter(step); position: absolute; left: 0; top: -2px;
      width: 30px; height: 30px; border-radius: 50%;
      background: rgba(0,200,160,.14); border: 1px solid rgba(0,200,160,.4);
      color: #00c8a0; font-weight: 700; font-size: .85rem;
      display: flex; align-items: center; justify-content: center;
    }
    .steps li strong { color: #e8eaed; }
    .faq { margin-top: 24px; }
    .faq details {
      border-top: 1px solid rgba(255,255,255,.08); padding: 14px 0;
    }
    .faq summary { cursor: pointer; font-weight: 600; color: #e8eaed; list-style: none; }
    .faq summary::-webkit-details-marker { display: none; }
    .faq summary::before { content: '+ '; color: #00c8a0; font-weight: 700; }
    .faq details[open] summary::before { content: '– '; }
    .faq p { color: #b9c7d4; margin: 10px 0 0; font-size: .93rem; }
    /* The screenshot. aspect-ratio + width/height on the tag together stop
       the page jumping when a 1.2MB image finally lands. */
    .shot {
      display: block; margin: 24px auto 0; max-width: 980px;
      border-radius: 14px; overflow: hidden;
      border: 1px solid rgba(0,200,160,.18);
      box-shadow: 0 18px 50px rgba(0,0,0,.45);
    }
    .shot img { width: 100%; height: auto; aspect-ratio: 1996 / 1255; }
    .shot:hover { border-color: rgba(0,200,160,.5); }
    footer { border-top: 1px solid rgba(255,255,255,.08); padding: 28px 20px; text-align: center; color: #5a7888; font-size: .85rem; }
  </style>
</head>
<body>

<?php if ($is_logged_in): ?>
<a id="dhc-exit" href="dashboard.php"><span class="mx-arrow">&larr;</span><span class="mx-label">Dashboard</span></a>
<?php endif; ?>

<header class="hero">
  <div class="wrap">
    <span class="kicker">Digi-Hell Corps</span>
    <h1>Build a Fighter. Then find out if it was any good.</h1>
    <p class="lede">Digi-Hell Corps is a character system you assemble from real NFT traits, a collection you can browse in full, and an Arena where the squad you built has to actually hold up. Playable free in your browser.</p>
    <div class="cta-row">
      <a class="btn" href="<?php echo $href_arena; ?>">Fight in the Arena</a>
      <a class="btn ghost" href="<?php echo $href_gallery; ?>">Browse the Collection</a>
    </div>
  </div>
</header>

<main>

<?php
/* Rendered only when there are real Fighters to show. Split across two rows
   that scroll opposite ways, which is what makes the wall read as motion
   rather than as a single sliding band. */
if ($dhc_fighters && $ART !== ''):
    $rows = array_chunk($dhc_fighters, (int)ceil(count($dhc_fighters) / 2));
?>
<section aria-label="Fighters built by players">
  <?php foreach ($rows as $ri => $row): if (!$row) continue; ?>
  <div class="dhc-strip">
    <?php
    /* HOW MANY TIMES THE ROW REPEATS.
       The -50% loop needs the track to be at least twice the viewport, and
       it needs the first half to equal the second half exactly -- so the
       count must be EVEN. Two passes is enough at 14 cards a row; at three
       cards a row it leaves a visible gap that reads as a broken page, and
       the collection is young enough for that to happen.

       SIXTEEN, not twelve. The half-track has to be at least as wide as the
       viewport or the loop shows empty space at the turn; at 150px cards
       plus a 20px gap, twelve is 2040px, which is narrower than a 2560px
       display. Sixteen is 2720px. */
    $passes = max(2, 2 * (int)ceil(16 / max(1, count($row))));
    /* Composed once per Fighter, reused across every repeat of the row. */
    $dhc_card_html = array();
    ?>
    <div class="dhc-track<?php echo $ri % 2 ? ' rev' : ''; ?>">
      <?php for ($pass = 0; $pass < $passes; $pass++):
              foreach ($row as $f): ?>
      <div class="dhc-card"<?php if ($pass) echo ' aria-hidden="true"'; ?>>
        <div class="dhc-art"><?php echo $pass ? $dhc_card_html[$f['name']] : ($dhc_card_html[$f['name']] = dhc_fighter_card($f, $ART, $SLOT_CAT)); ?></div>
        <?php /* Named on every pass, not just the first: the repeats are what
                 is on screen most of the time, and a wall of mostly-unlabelled
                 cards with the occasional name reads as a bug. The duplicate
                 CARD is aria-hidden, so a screen reader still meets each
                 Fighter once. */ ?>
        <div class="dhc-name"><?php echo htmlspecialchars($f['name']); ?></div>
      </div>
      <?php endforeach; endfor; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="wrap center">
    <p class="lede" style="margin-top:10px;font-size:.9rem;color:#7a9eb0;">Every Fighter above was assembled by a player from traits they hold.</p>
  </div>
</section>
<?php endif; ?>

<section class="wrap">
  <h2 class="center">Three things, one character</h2>
  <div class="cards">
    <div class="card">
      <h3>Fighters — the assembler</h3>
      <p>Ten trait slots: torso, head, background, two effects, arms, weapons, weapons behind, headgear and a companion. Layers draw in a fixed order with real exceptions — a companion can sit under the arms, some arms sit behind the torso — so a build looks the same everywhere it appears.</p>
      <p>Traits are committed. A trait spent on one Fighter is not available to another until you disassemble it, which is what makes a build a decision rather than a preview.</p>
      <a class="more" href="<?php echo $href_fighters; ?>">Open the assembler &rarr;</a>
    </div>
    <div class="card">
      <h3>Collection — browse it all</h3>
      <p>The full Digi-Hell Corps collection, open to everyone. Filter by trait, see what is rare and what only looks rare, and find the pieces a build needs before you commit to it.</p>
      <p>No account required to look.</p>
      <a class="more" href="<?php echo $href_gallery; ?>">Browse the collection &rarr;</a>
    </div>
    <div class="card">
      <h3>Arena — the match-3 battler</h3>
      <p>Your squad of three fights on a match-3 board. Matching charges your Fighters; chains and explosions chunk health down as they land, not after the board settles. Rank decides who takes the hit, so the order you field them matters.</p>
      <p>Rarity does not decide a fight. A common build played well beats a rare one played badly.</p>
      <a class="more" href="<?php echo $href_arena; ?>">Play the Arena &rarr;</a>
    </div>
  </div>
</section>

<hr class="rule">

<?php /* THE SCREENSHOT IS THE PITCH. Everything above describes a battler;
         this is the only place a visitor sees one. Below the fold and
         lazy-loaded -- it is a 1.2MB PNG, so it must not be in the way of
         first paint -- with width/height so the page does not jump when it
         arrives. Clickable, because the next thing anyone wants after
         looking at it is to play it. */ ?>
<section class="wrap">
  <h2 class="center">What a battle looks like</h2>
  <p class="lede center">Your Crew on the left, theirs on the right, a seven-by-seven board between you. Match to charge your Fighters; rank decides who takes the hit. The arena itself is named after the back Fighter's background.</p>
  <a class="shot" href="<?php echo $href_arena; ?>">
    <img src="<?php echo htmlspecialchars($shot_url); ?>" width="1996" height="1255"
         alt="A Digi-Hell Corps Arena battle: three Fighters on each side of a seven-by-seven gem board, with health bars and kit chips"
         loading="lazy" decoding="async">
  </a>
  <p class="center" style="margin-top:14px;"><a class="btn" href="<?php echo $href_arena; ?>">Play a practice battle</a></p>
</section>

<hr class="rule">

<section class="wrap">
  <h2>Start without owning anything</h2>
  <ol class="steps">
    <li><strong>Play a practice match.</strong> The Arena opens straight into practice mode with a pre-built crew. No account, no wallet, no download — it just starts.</li>
    <li><strong>Browse the collection.</strong> See the traits and work out what you would build.</li>
    <li><strong>Sign in with Discord</strong> to save Fighters of your own and keep them between sessions.</li>
    <li><strong>Hold DHC traits</strong> to assemble Fighters from the real thing and take them into ranked matches.</li>
  </ol>
</section>

<hr class="rule">

<section class="wrap">
  <h2>Questions</h2>
  <div class="faq">
    <details><summary>Do I need an NFT to play?</summary>
      <p>No. The Arena defaults to practice mode for anyone, with a pre-built crew, and the collection is public. Holding traits is what lets you assemble and save your own Fighters.</p></details>
    <details><summary>Is it pay-to-win?</summary>
      <p>No, and the rules are built to keep it that way — rarity does not decide a fight. A rare trait changes how a Fighter looks and what it is worth, not whether it wins.</p></details>
    <details><summary>Can I play against other people?</summary>
      <p>Yes. Live matches are for the sport of it: no rewards, no rankings at stake, no way to farm them. Just a match against someone who also wanted one.</p></details>
    <details><summary>What happens to a trait I use?</summary>
      <p>It is committed to that Fighter and unavailable to another until you disassemble it. Disassembling frees the traits; the Fighter's record survives so an old build cannot be replayed as if it were new.</p></details>
    <details><summary>Does it work on a phone?</summary>
      <p>Yes — the board and the assembler are both built for touch, and the site installs as a home-screen app if you want it to.</p></details>
  </div>
</section>

</main>

<footer>
  <div class="wrap">
    <p>Digi-Hell Corps is part of <a href="https://www.skulliance.io/">Skulliance</a> — NFT staking, games and a marketplace.</p>
  </div>
</footer>

</body>
</html>

<?php
// Standalone marketing landing page for the Monstrocity Match 3 RPG.
// Public - no session, no DB, no login redirect. Designed to rank for
// "free match 3 rpg", "browser match 3 game", and adjacent terms, with
// OpenGraph/Twitter Cards/Schema.org structured data. FAQPage schema
// intentionally omitted (Google retired FAQ rich results on 2026-05-07);
// FAQ content kept inline for topical SEO depth and user value.

$canonical    = 'https://www.skulliance.io/staking/match3rpg.php';
$play_url     = 'https://www.skulliance.io/staking/monstrocity.php';
$logo_url     = 'https://www.skulliance.io/staking/images/monstrocity/logo.png';
$og_image     = $logo_url;
$page_title   = 'Free Match 3 RPG Game - Play Monstrocity in Your Browser';
$page_desc    = 'Play Monstrocity free - a Match 3 RPG with deep combat, 35+ visual themes, boss battles, and skill-based combos. Works on mobile, tablet, and desktop. No download, no ads, no pay-to-win.';
$short_desc   = 'A free browser Match 3 RPG with real combat depth, 35+ themes, and boss battles. Play on any device - no download.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_desc); ?>">
  <meta name="keywords" content="free match 3 rpg, match 3 rpg game, free puzzle rpg, browser match 3 game, online match 3 game, match 3 game free, mobile match 3 rpg, tablet match 3 game, puzzle rpg browser, free online rpg game, no download match 3 game, match three rpg, match-3 rpg, match 3 puzzle rpg, monstrocity">
  <meta name="theme-color" content="#002f44">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
  <link rel="canonical" href="<?php echo $canonical; ?>">

  <!-- OpenGraph -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Monstrocity">
  <meta property="og:url" content="<?php echo $canonical; ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($page_desc); ?>">
  <meta property="og:image" content="<?php echo $og_image; ?>">
  <meta property="og:image:alt" content="Monstrocity Match 3 RPG logo">
  <meta property="og:locale" content="en_US">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($short_desc); ?>">
  <meta name="twitter:image" content="<?php echo $og_image; ?>">
  <meta name="twitter:image:alt" content="Monstrocity Match 3 RPG logo">

  <!-- Preconnect for the play CTA target -->
  <link rel="preconnect" href="https://www.skulliance.io">

  <!-- Schema.org structured data: VideoGame + BreadcrumbList -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "VideoGame",
        "name": "Monstrocity",
        "alternateName": ["Monstrocity Match 3 RPG", "Monstrocity Match-3 RPG"],
        "url": "<?php echo $canonical; ?>",
        "image": "<?php echo $logo_url; ?>",
        "description": <?php echo json_encode($page_desc); ?>,
        "genre": ["Match 3", "Puzzle RPG", "Role-Playing Game", "Puzzle"],
        "gamePlatform": ["Web Browser", "Mobile Web", "Tablet", "Desktop"],
        "operatingSystem": ["Any (browser-based)", "Windows", "macOS", "Linux", "iOS", "Android"],
        "applicationCategory": "GameApplication",
        "playMode": ["SinglePlayer"],
        "inLanguage": "en",
        "isAccessibleForFree": true,
        "offers": {
          "@type": "Offer",
          "price": "0",
          "priceCurrency": "USD",
          "availability": "https://schema.org/InStock",
          "url": "<?php echo $play_url; ?>"
        },
        "publisher": {
          "@type": "Organization",
          "name": "Skulliance",
          "url": "https://www.skulliance.io/"
        },
        "potentialAction": {
          "@type": "PlayAction",
          "target": "<?php echo $play_url; ?>"
        }
      },
      {
        "@type": "BreadcrumbList",
        "itemListElement": [
          { "@type": "ListItem", "position": 1, "name": "Skulliance", "item": "https://www.skulliance.io/" },
          { "@type": "ListItem", "position": 2, "name": "Free Match 3 RPG", "item": "<?php echo $canonical; ?>" }
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
      background: #07111d;
      color: #e8eaed;
      line-height: 1.55;
      -webkit-font-smoothing: antialiased;
    }
    a { color: #00c8a0; text-decoration: none; }
    a:hover, a:focus { color: #34e3bb; text-decoration: underline; }
    img { max-width: 100%; height: auto; display: block; }
    h1, h2, h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
    h1 { font-size: clamp(1.9rem, 4.5vw, 3.2rem); }
    h2 { font-size: clamp(1.5rem, 3vw, 2.2rem); margin-top: 1.5em; }
    h3 { font-size: 1.15rem; color: #00c8a0; }
    p { margin: 0 0 1em; }

    .wrap { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
    main { padding: 24px 0 64px; }

    /* Hero */
    .hero {
      text-align: center;
      padding: 56px 20px 48px;
      background:
        radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%),
        linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .hero img.logo {
      max-width: 320px; width: 80%;
      margin: 0 auto 24px;
      filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.6));
    }
    .hero p.lead {
      font-size: clamp(1rem, 2vw, 1.18rem);
      max-width: 720px;
      margin: 0 auto 28px;
      color: #c7d0d9;
    }
    .cta {
      display: inline-block;
      background: linear-gradient(135deg, #00c8a0, #0596c4);
      color: #07111d !important;
      font-weight: 800;
      font-size: 1.08rem;
      padding: 14px 32px;
      border-radius: 999px;
      text-decoration: none;
      box-shadow: 0 6px 20px rgba(0, 200, 160, 0.35);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .cta:hover, .cta:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(0, 200, 160, 0.5);
      text-decoration: none;
      color: #07111d !important;
    }
    .cta.secondary {
      background: transparent;
      color: #00c8a0 !important;
      border: 1px solid rgba(0, 200, 160, 0.45);
      box-shadow: none;
      margin-left: 12px;
    }
    .cta.secondary:hover { background: rgba(0, 200, 160, 0.08); color: #34e3bb !important; }

    .badges {
      display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;
      margin-top: 22px;
    }
    .badge {
      font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase;
      padding: 6px 12px; border-radius: 999px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #c7d0d9;
    }

    /* Section */
    section { padding: 40px 0; }
    section + section { border-top: 1px solid rgba(255, 255, 255, 0.06); }

    /* Features grid */
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 18px;
      margin-top: 20px;
    }
    .card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
      padding: 22px;
    }
    .card h3 { margin-bottom: 8px; }
    .card p { margin: 0; color: #c7d0d9; font-size: 0.96rem; }

    /* Gameplay list */
    .mechanics {
      list-style: none; padding: 0; margin: 16px 0 0;
      display: flex; flex-direction: column; gap: 10px;
    }
    .mechanics.cols-2 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }
    .mechanics li {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 16px;
      background: rgba(255, 255, 255, 0.03);
      border-left: 3px solid #00c8a0;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .mechanics li strong { color: #34e3bb; }
    .mech-icon {
      flex-shrink: 0;
      display: inline-flex; align-items: center; gap: 4px;
    }
    .mech-icon img {
      width: 32px; height: 32px;
      object-fit: contain;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
    }
    .mech-heading {
      margin-top: 36px;
      font-size: 1.05rem;
      color: #00c8a0;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .mech-note {
      margin-top: 14px;
      font-size: 0.88rem;
      color: #8a96a3;
      font-style: italic;
    }

    /* Auto-scrolling character marquee - full viewport width */
    .character-strip-section {
      padding: 32px 0 12px;
    }
    .character-strip-section .intro {
      text-align: center;
      margin-bottom: 24px;
    }
    .character-strip-section h2 { margin-top: 0; }
    .character-strip-section .intro p {
      color: #c7d0d9;
      max-width: 600px;
      margin: 0 auto;
    }
    .character-strip {
      width: 100%;
      overflow: hidden;
      padding: 24px 0;
      background:
        linear-gradient(180deg, rgba(0, 200, 160, 0.06), transparent 60%),
        linear-gradient(180deg, #0b1a2b 0%, #07111d 100%);
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
              mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
    }
    .strip-track {
      display: flex;
      gap: 28px;
      width: max-content;
      animation: strip-scroll 50s linear infinite;
      will-change: transform;
    }
    .strip-track:hover { animation-play-state: paused; }
    @keyframes strip-scroll {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }
    @media (prefers-reduced-motion: reduce) {
      .strip-track { animation: none; }
    }
    .strip-card {
      flex: 0 0 200px;
      text-align: center;
    }
    .strip-card img {
      width: 200px;
      height: 200px;
      object-fit: contain;
      filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.65));
    }
    .strip-card .name {
      margin-top: 10px;
      font-size: 0.92rem;
      color: #c7d0d9;
      font-weight: 600;
      letter-spacing: 0.02em;
    }

    /* Themes - project logo grid */
    .logo-grid {
      list-style: none; padding: 0; margin: 20px 0 0;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(236px, 1fr));
      gap: 14px;
    }
    .logo-tile {
      display: flex; align-items: center; justify-content: center;
      padding: 16px 14px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    }
    .logo-tile:hover {
      transform: translateY(-2px);
      border-color: rgba(0, 200, 160, 0.45);
      background: rgba(0, 200, 160, 0.06);
    }
    .logo-tile img {
      width: 100%;
      max-width: 200px;
      height: 100px;
      object-fit: contain;
    }

    /* FAQ */
    .faq details {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 10px;
      padding: 16px 20px;
      margin: 10px 0;
    }
    .faq summary {
      cursor: pointer;
      font-weight: 600;
      color: #e8eaed;
      list-style: none;
      position: relative;
      padding-right: 28px;
    }
    .faq summary::-webkit-details-marker { display: none; }
    .faq summary::after {
      content: "+";
      position: absolute; right: 0; top: 50%; transform: translateY(-50%);
      color: #00c8a0; font-size: 1.4rem; font-weight: 400; line-height: 1;
    }
    .faq details[open] summary::after { content: "−"; }
    .faq summary:focus { outline: 2px solid #00c8a0; outline-offset: 4px; border-radius: 4px; }
    .faq details p { margin: 12px 0 0; color: #c7d0d9; }

    /* Final CTA */
    .final-cta {
      text-align: center;
      padding: 64px 20px;
      background: linear-gradient(135deg, rgba(0, 200, 160, 0.12), rgba(5, 150, 196, 0.08));
      border-radius: 16px;
      margin-top: 32px;
    }

    /* Footer */
    footer {
      padding: 28px 20px;
      text-align: center;
      color: #8a96a3;
      font-size: 0.88rem;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }
    footer a { color: #8a96a3; }

    @media (max-width: 480px) {
      .cta { width: 100%; text-align: center; }
      .cta.secondary { margin-left: 0; margin-top: 10px; }
    }
  </style>
</head>
<body>

  <main>

    <section class="hero">
      <div class="wrap">
        <img src="<?php echo $logo_url; ?>" alt="Monstrocity - free Match 3 RPG logo" class="logo" width="320" height="320" fetchpriority="high">
        <h1>Free Match 3 RPG - Play in Your Browser</h1>
        <p class="lead">Monstrocity is a free online Match 3 RPG with real combat depth - character stats, special attacks, power-ups, and boss battles wrapped around the match-3 mechanics you already love. Plays in any modern browser on phone, tablet, or desktop.</p>
        <a href="<?php echo $play_url; ?>" class="cta" aria-label="Play Monstrocity free now">Play Free Now</a>
        <a href="#how-it-works" class="cta secondary">How It Works</a>
        <div class="badges" aria-label="Game highlights">
          <span class="badge">100% Free</span>
          <span class="badge">No Download</span>
          <span class="badge">No Ads</span>
          <span class="badge">No Pay-to-Win</span>
          <span class="badge">Mobile · Tablet · Desktop</span>
        </div>
      </div>
    </section>

    <section class="character-strip-section">
      <div class="wrap intro">
        <h2>Meet the Monstrocity Cast</h2>
        <p>14 original characters anchor the base game, each with their own stats, size, and signature power-up.</p>
      </div>
      <?php
      // Base Monstrocity character roster (JSON order from monstrocity.php).
      // Path: /staking/images/monstrocity/monstrocity/base/{slug}.png
      // Slug = lowercase name with spaces replaced by dashes.
      $characters = [
          'Craig', 'Merdock', 'Goblin Ganger', 'Texby', 'Mandiblus',
          'Koipon', 'Slime Mind', 'Billandar and Ted', 'Dankle', 'Jarhead',
          'Spydrax', 'Katastrophy', 'Ouchie', 'Drake',
      ];
      $char_base = 'https://www.skulliance.io/staking/images/monstrocity/monstrocity/base/';
      ?>
      <div class="character-strip" aria-label="Monstrocity base characters">
        <div class="strip-track">
          <?php // Render twice for seamless infinite scroll loop.
          for ($pass = 0; $pass < 2; $pass++):
            foreach ($characters as $char):
              $slug = strtolower(str_replace(' ', '-', $char));
          ?>
            <div class="strip-card"<?php echo $pass ? ' aria-hidden="true"' : ''; ?>>
              <img src="<?php echo $char_base . $slug; ?>.png"
                   alt="<?php echo htmlspecialchars($char); ?> - Monstrocity base character"
                   loading="<?php echo $pass ? 'lazy' : 'eager'; ?>"
                   decoding="async"
                   width="200" height="200"
                   onerror="this.onerror=null;this.src='/staking/icons/skull.png';">
              <div class="name"><?php echo htmlspecialchars($char); ?></div>
            </div>
          <?php
            endforeach;
          endfor;
          ?>
        </div>
      </div>
    </section>

    <section>
      <div class="wrap">
        <h2>Why Players Love Monstrocity</h2>
        <p>Most match-3 games stop at "match three of a color." Monstrocity layers a full RPG combat system on top: every match attacks, defends, or powers up your character based on the tile type. Strategy matters, and skilled play actually wins fights.</p>
        <div class="grid">
          <article class="card">
            <h3>Free Forever</h3>
            <p>Completely free to play. No download, no install, no account required. Open the page and go.</p>
          </article>
          <article class="card">
            <h3>Real RPG Depth</h3>
            <p>Strength, Speed, Tactics, Size, and Type stats. Multiple attack types, power-ups, and last-stand mechanics that reward planning.</p>
          </article>
          <article class="card">
            <h3>Plays Anywhere</h3>
            <p>Works on iPhone, Android, iPad, and any desktop browser. No app store. Responsive layout with touch and mouse support.</p>
          </article>
          <article class="card">
            <h3>35+ Visual Themes</h3>
            <p>Swap between dozens of art styles drawn from independent artists and partner projects - from cosmic explorers to retro punks.</p>
          </article>
          <article class="card">
            <h3>Boss Battles</h3>
            <p>Take on themed bosses with unique health pools and traits. Pick your character, pick your power-ups, and outplay them.</p>
          </article>
          <article class="card">
            <h3>Skill-Based Combos</h3>
            <p>Match-4 and match-5 chains, multi-matches, and cascade combos all stack damage and score multipliers. Bigger combos hit harder.</p>
          </article>
        </div>
      </div>
    </section>

    <section id="how-it-works">
      <div class="wrap">
        <h2>Real RPG Combat in a Match 3 Puzzle</h2>
        <p>Every tile on the board does something different in combat. Instead of generic "score points," each match resolves as a combat action against the enemy character - and your opponent does the same to you.</p>

        <h3 class="mech-heading">Tile Types</h3>
        <ul class="mechanics">
          <li>
            <span class="mech-icon"><img src="/staking/icons/first-attack.png" alt="" loading="lazy" decoding="async" width="32" height="32"></span>
            <span><strong>First Attack (Slash)</strong> - Deals damage (Strength × 2/3/4 for 3/4/5 tiles).</span>
          </li>
          <li>
            <span class="mech-icon"><img src="/staking/icons/second-attack.png" alt="" loading="lazy" decoding="async" width="32" height="32"></span>
            <span><strong>Second Attack (Bite)</strong> - Deals damage (Strength × 2/3/4 for 3/4/5 tiles).</span>
          </li>
          <li>
            <span class="mech-icon"><img src="/staking/icons/special-attack.png" alt="" loading="lazy" decoding="async" width="32" height="32"></span>
            <span><strong>Special Attack (Shadow Strike)</strong> - Deals 1.2× damage (Strength × 2/3/4 for 3/4/5 tiles).</span>
          </li>
          <li>
            <span class="mech-icon"><img src="/staking/icons/power-up.png" alt="" loading="lazy" decoding="async" width="32" height="32"></span>
            <span><strong>Power-Up</strong> - Activates a random powerup (see below).</span>
          </li>
          <li>
            <span class="mech-icon"><img src="/staking/icons/last-stand.png" alt="" loading="lazy" decoding="async" width="32" height="32"></span>
            <span><strong>Last Stand</strong> - Deals damage and mitigates 5 damage on the next attack received.</span>
          </li>
        </ul>

        <h3 class="mech-heading">Power-Up Effects</h3>
        <ul class="mechanics cols-2">
          <li><span><strong>Heal (Bloody)</strong> - Restores 10 HP (reduced by enemy tactics).</span></li>
          <li><span><strong>Boost Attack (Cardano)</strong> - Adds +10 damage to the next attack (reduced by enemy tactics).</span></li>
          <li><span><strong>Regenerate (ADA)</strong> - Restores 7 HP (reduced by enemy tactics).</span></li>
          <li><span><strong>Minor Regen (None)</strong> - Restores 5 HP (reduced by enemy tactics).</span></li>
        </ul>
        <p class="mech-note">Power-up effects are boosted by 50% for a match-4 and 100% for a match-5+.</p>

        <h3 class="mech-heading">Combo Bonuses</h3>
        <ul class="mechanics cols-2">
          <li><span><strong>Match-4 Bonus</strong> - 50% bonus to damage and score for a single match of 4 tiles.</span></li>
          <li><span><strong>Match-5+ Bonus</strong> - 100% bonus to damage and score for a single match of 5 or more tiles.</span></li>
          <li><span><strong>Multi-Match (6–8 tiles)</strong> - 20% bonus to score for matching 6–8 tiles across multiple matches in a single move (does not apply to cascades).</span></li>
          <li><span><strong>Mega Multi-Match (9+ tiles)</strong> - 200% bonus to score for matching 9 or more tiles across multiple matches in a single move (does not apply to cascades).</span></li>
        </ul>

        <h3 class="mech-heading">Character Traits</h3>
        <ul class="mechanics">
          <li><span><strong>Strength</strong> - Determines base damage for attacks (Strength × 2/3/4 for 3/4/5+ tiles).</span></li>
          <li><span><strong>Speed</strong> - Determines turn order at the start of the level (higher Speed goes first; ties broken by Strength).</span></li>
          <li><span><strong>Tactics</strong> - Gives a (Tactics × 10)% chance to halve incoming damage and reduces enemy power-up effects by (Tactics × 5)%.</span></li>
          <li><span><strong>Size</strong> - Large: +20% health, -2 Tactics (if Tactics &gt; 1); Medium: No effect; Small: -20% health, +2 Tactics (max 7).</span></li>
          <li><span><strong>Type</strong> - Base: 85 health; Leader: 100 health; Battle Damaged: 70 health.</span></li>
        </ul>
      </div>
    </section>

    <section>
      <div class="wrap">
        <h2>Featured Artists &amp; Projects</h2>
        <p>Monstrocity bundles the original character set with dozens of visual themes contributed by independent artists. Each project below brings its own world - swap freely in-game, the mechanics stay the same and the art changes everything.</p>
        <?php
        // Display: project name. Path: /staking/images/monstrocity/{theme-slug}/logo.png
        // where theme-slug is the FIRST theme.value associated with this project
        // in the monstrocity.php JSON (since the logo lives inside a theme folder,
        // not a project-named folder). Slugified project names happen to match the
        // theme value for some projects (apprentices, blackflag, etc.) but for
        // many they don't (Heist on Alpha → proxy, Josh Howard → muses, Nemonium
        // → fauna, etc.) - explicit map avoids 404s.
        $img_base = 'https://www.skulliance.io/staking/images/monstrocity/';
        $projects = [
            'Monstrocity'      => 'monstrocity',
            'Apprentices'      => 'apprentices',
            'Black Flag'       => 'blackflag',
            'Danketsu'         => 'danketsu2',
            'Disco Solaris'    => 'moebiuspioneers',
            'Havoc Worlds'     => 'havocworlds',
            'Heist on Alpha'   => 'proxy',
            'Josh Howard'      => 'muses',
            'Nemonium'         => 'fauna',
            'Pendulum'         => 'pendulum',
            'Perps'            => 'perps',
            'Vampire Invasion' => 'vampireinvasion',
            'Bungking'         => 'bungking',
            'Cardano Camera'   => 'cardanocamera',
            'Crypties'         => 'crypties2',
            'Darkula'          => 'darkula',
            'Dead Pop Hell'    => 'deadpophell',
            'Galactico'        => 'galactico',
            'Nel'              => 'animeorigins',
            'Netanel Cohen'    => 'happypeople',
            'Kimosabe Art'     => 'machineheadz',
            'Maxingo'          => 'maxi',
            'Ohh Meed'         => 'shortyverse',
            'Ritual'           => 'beelzebub',
            'Sinder Skullz'    => 'sinderskullz',
            'Skowl'            => 'skowl',
            'Squashua'         => 'ug',
            'ADA Punks'        => 'adapunks',
        ];
        ?>
        <ul class="logo-grid">
          <?php foreach ($projects as $project => $theme_slug):
              $logo = $img_base . $theme_slug . '/logo.png';
          ?>
            <li class="logo-tile">
              <img src="<?php echo htmlspecialchars($logo); ?>"
                   alt="<?php echo htmlspecialchars($project); ?> project logo"
                   loading="lazy" decoding="async"
                   width="200" height="100"
                   onerror="this.onerror=null;this.src='/staking/icons/skull.png';">
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section>
      <div class="wrap">
        <h2>How to Start Playing in Under 10 Seconds</h2>
        <ol>
          <li>Open <a href="<?php echo $play_url; ?>">the game</a> in any browser - phone, tablet, or desktop.</li>
          <li>Pick a visual theme (or stick with the default Monstrocity art).</li>
          <li>Choose a character and step into your first battle.</li>
          <li>Match tiles to attack, defend, and trigger power-ups. Beat the opponent.</li>
        </ol>
        <p style="margin-top: 18px;"><a href="<?php echo $play_url; ?>" class="cta">Start Playing Now</a></p>
      </div>
    </section>

    <section class="faq">
      <div class="wrap">
        <h2>Frequently Asked Questions</h2>

        <details>
          <summary>Is Monstrocity really free to play?</summary>
          <p>Yes. The game is 100% free with no ads, no in-app purchases that affect gameplay, and no paywall. You don't need an account to play.</p>
        </details>

        <details>
          <summary>Do I need to download anything?</summary>
          <p>No download or install. Monstrocity runs entirely in your web browser. You can play on iPhone, Android, iPad, or any modern desktop browser (Chrome, Safari, Firefox, Edge).</p>
        </details>

        <details>
          <summary>Does it work on mobile and tablet?</summary>
          <p>Yes. The game is fully touch-enabled and the board layout adapts to your screen. It works great on phones in portrait mode and shines on tablets with the larger play area.</p>
        </details>

        <details>
          <summary>How is this different from other match 3 games?</summary>
          <p>Most match-3 games are pure puzzles or have light meta-game wrappers. Monstrocity is a true Match 3 RPG - every match resolves as a combat action with damage, defense, or power-up effects. Character stats (Strength, Speed, Tactics, Size, Type) actually change how fights play out, and bigger combos translate to bigger hits.</p>
        </details>

        <details>
          <summary>Is there a story or campaign?</summary>
          <p>Pick a character, pick a theme, and challenge bosses. The focus is on combat depth and replayability rather than a linear story. Themes from independent artists give the world a wildly different feel each time you play.</p>
        </details>

        <details>
          <summary>Are there ads?</summary>
          <p>No ads. The game is supported by the Skulliance community rather than ad revenue, so the play experience stays clean.</p>
        </details>

        <details>
          <summary>What is Skulliance and do I need it to play?</summary>
          <p>Skulliance is the platform that hosts and maintains Monstrocity. You do not need a Skulliance account, a wallet, or anything cryptocurrency-related to play. Optional features (leaderboards, game saves, rewards) exist for Skulliance community members, but they are entirely opt-in - the base game is free and complete on its own.</p>
        </details>

        <details>
          <summary>Can I save my progress?</summary>
          <p>Casual play runs in-browser without an account. If you want persistent game saves, leaderboards, or to compete for rewards, you can optionally connect via the Skulliance staking platform - but it's not required to enjoy the game.</p>
        </details>

        <details>
          <summary>What browsers and devices are supported?</summary>
          <p>Any modern browser from the last few years on Windows, macOS, Linux, iOS, or Android. Chrome, Safari, Firefox, and Edge are all tested and supported.</p>
        </details>

      </div>
    </section>

    <div class="wrap">
      <div class="final-cta">
        <h2 style="margin-top:0;">Ready to Play?</h2>
        <p>Open the game and start matching. No download. No signup. Just play.</p>
        <a href="<?php echo $play_url; ?>" class="cta" aria-label="Play Monstrocity free Match 3 RPG now">Play Monstrocity Free</a>
      </div>
    </div>

  </main>

  <footer>
    <p>© Skulliance · Monstrocity is a free browser-based Match 3 RPG. <a href="https://www.skulliance.io/">Visit Skulliance</a></p>
  </footer>

</body>
</html>

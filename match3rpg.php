<?php
// Session handling is the EXACT pattern used by monstrocity.php (which is
// confirmed to keep stakers logged in): bare session_start, then restore
// the staking session from the 6-month SessionCookie if PHPSESSID has
// lapsed. No db.php include - this page doesn't need DB access, and the
// previous include_once 'db.php' attempt was still kicking users out.
session_start();

if (!isset($_SESSION['logged_in'])) {
    if (isset($_COOKIE['SessionCookie'])) {
        $cookie = $_COOKIE['SessionCookie'];
        $cookieData = json_decode($cookie, true);
        if (is_array($cookieData)) {
            $_SESSION = $cookieData;
        }
    }
}

$is_logged_in = !empty($_SESSION['logged_in']);

// Marketing landing page for the Monstrocity Match 3 RPG. Reachable both
// from search (designed to rank for "free match 3 rpg" etc.) and from
// the staking nav for logged-in users. When a session is present a small
// floating exit button is rendered so PWA/mobile users have a way back
// to their dashboard. SEO meta, OG, and Schema.org markup target the
// cold-visitor case; logged-in users get the same page plus the exit.
// FAQPage schema intentionally omitted (Google retired FAQ rich results
// on 2026-05-07); FAQ content kept inline for topical SEO depth.

$canonical    = 'https://www.skulliance.io/staking/match3rpg.php';
// Absolute URL for SEO markup (schema.org/OG) only. Clickable CTAs must use
// $play_href instead: session cookies are host-only (no domain= on
// SessionCookie/PHPSESSID), so an absolute www. link logs out users who are
// sessioned on the bare domain — every in-app nav link is relative for the
// same reason.
$play_url     = 'https://www.skulliance.io/staking/monstrocity.php';
$play_href    = 'monstrocity.php';
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
      overflow-x: hidden;
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
    main { padding: 0 0 64px; }

    /* Floating exit button for logged-in users arriving from the staking
       nav. Mirrors monstrocity.php's #monstrocity-exit pattern so the
       interaction feels consistent across the two standalone pages. */
    #m3-exit {
      position: fixed;
      top: calc(env(safe-area-inset-top, 0px) + 8px);
      left: calc(env(safe-area-inset-left, 0px) + 8px);
      z-index: 99990;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      min-height: 36px;
      background: rgba(18, 18, 18, 0.85);
      color: #e8eaed;
      border: 1px solid rgba(0, 200, 160, 0.45);
      border-radius: 999px;
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 600;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      transition: background 0.15s, border-color 0.15s;
    }
    #m3-exit:hover, #m3-exit:active {
      background: rgba(0, 200, 160, 0.18);
      border-color: #00c8a0;
      text-decoration: none;
      color: #e8eaed;
    }
    #m3-exit .mx-arrow {
      font-size: 1.05rem;
      line-height: 1;
      color: #00c8a0;
    }
    @media (max-width: 480px) {
      #m3-exit .mx-label { display: none; }
      #m3-exit { padding: 8px 10px; }
    }

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
    /* Screenshot band - full-width with parallax theme background */
    .screenshot-band {
      position: relative;
      width: 100%;
      padding: 56px 20px;
      background-image: url('https://www.skulliance.io/staking/images/monstrocity/monstrocity/monstrocity.png');
      background-size: cover;
      background-position: center center;
      background-attachment: fixed;
      background-repeat: no-repeat;
      overflow: hidden;
    }
    .screenshot-band::before {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(180deg, rgba(7, 17, 29, 0.55), rgba(7, 17, 29, 0.3) 40%, rgba(7, 17, 29, 0.65));
      pointer-events: none;
    }
    .hero-screenshot {
      position: relative;
      display: block;
      width: 100%;
      max-width: 960px;
      height: auto;
      margin: 0 auto;
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, 0.15);
      box-shadow:
        0 30px 80px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(0, 200, 160, 0.1) inset;
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    /* The screenshot links to the game - hover lift as the affordance */
    .screenshot-band a:hover .hero-screenshot,
    .screenshot-band a:focus-visible .hero-screenshot {
      transform: translateY(-3px);
      border-color: rgba(0, 200, 160, 0.5);
      box-shadow:
        0 36px 90px rgba(0, 0, 0, 0.75),
        0 0 24px rgba(0, 200, 160, 0.25);
    }
    @media (max-width: 768px), (hover: none) {
      /* iOS Safari and most touch browsers ignore background-attachment: fixed
         (or break scrolling), so fall back to scroll on small/touch screens. */
      .screenshot-band { background-attachment: scroll; }
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
    .mech-emoji {
      flex-shrink: 0;
      width: 32px;
      font-size: 1.4rem;
      line-height: 1;
      text-align: center;
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
    .strip-track.reverse {
      animation-direction: reverse;
      animation-duration: 55s;
    }
    .strip-track:hover { animation-play-state: paused; }
    @keyframes strip-scroll {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }
    .character-strip + .character-strip {
      border-top: 0;
      margin-top: -1px;
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

    /* Hint above the project grid that the logos are interactive */
    .click-hint {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      margin-top: 6px;
      background: rgba(0, 200, 160, 0.08);
      border: 1px solid rgba(0, 200, 160, 0.3);
      border-radius: 999px;
      color: #c7d0d9;
      font-size: 0.92rem;
    }
    .click-hint strong { color: #34e3bb; }
    .click-hint .click-icon {
      font-size: 1.05rem;
      transform: rotate(120deg);
      display: inline-block;
      animation: click-bounce 1.8s ease-in-out infinite;
    }
    @keyframes click-bounce {
      0%, 100% { transform: rotate(120deg) translateY(0); }
      50%      { transform: rotate(120deg) translateY(-3px); }
    }
    @media (prefers-reduced-motion: reduce) {
      .click-hint .click-icon { animation: none; }
    }

    /* Themes - project logo grid (full viewport width) */
    .logo-grid {
      list-style: none;
      margin: 20px 0 0;
      padding: 0 24px;
      /* Break out of the .wrap container to fill the full viewport width
         so more logos fit per row and the section uses less vertical
         space. Heading/intro above the grid stay constrained. */
      width: 100vw;
      margin-left: calc(50% - 50vw);
      margin-right: calc(50% - 50vw);
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
    .logo-tile { cursor: pointer; }
    .logo-tile:focus-visible {
      outline: 2px solid #00c8a0;
      outline-offset: 2px;
    }
    .logo-tile.active {
      border-color: rgba(0, 200, 160, 0.6);
      background: rgba(0, 200, 160, 0.1);
    }
    .logo-tile-expansion {
      grid-column: 1 / -1;
      list-style: none;
      padding: 0;
      background: transparent;
      border: none;
      margin: 4px 0 8px;
    }
    .logo-tile-expansion .expansion-inner {
      background: rgba(0, 0, 0, 0.3);
      border-top: 1px solid rgba(0, 200, 160, 0.25);
      border-bottom: 1px solid rgba(0, 200, 160, 0.25);
      padding: 20px 0 24px;
      overflow: hidden;
      /* Break out of the wrap container to fill the full viewport width,
         matching the top-of-page character strips. The calc shifts the
         element left by (half viewport - half parent) so width: 100vw
         starts at viewport edge zero. body has overflow-x: hidden so
         this never produces a horizontal scrollbar. */
      width: 100vw;
      margin-left: calc(50% - 50vw);
      margin-right: calc(50% - 50vw);
    }
    .logo-tile-expansion h3 {
      text-align: center;
      margin: 0 0 16px;
      padding: 0 20px;
      font-size: 0.95rem;
      color: #00c8a0;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .logo-tile-expansion .character-strip {
      background: transparent;
      border: none;
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

    /* Cross-promotion card for Skull Swap */
    .cross-promo {
      display: flex; align-items: center; gap: 28px;
      margin-top: 20px; padding: 24px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
    }
    .cross-promo .cross-shot { flex: 0 0 200px; display: block; }
    .cross-promo .cross-shot img {
      width: 100%; height: auto; border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.15);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
      transition: transform 0.15s ease, border-color 0.15s ease;
    }
    .cross-promo .cross-shot:hover img,
    .cross-promo .cross-shot:focus-visible img {
      transform: translateY(-2px);
      border-color: rgba(0, 200, 160, 0.5);
    }
    .cross-promo h3 { margin-top: 0; }
    .cross-promo p { color: #c7d0d9; font-size: 0.96rem; }
    @media (max-width: 640px) {
      .cross-promo { flex-direction: column; text-align: center; }
      .cross-promo .cross-shot { flex-basis: auto; width: 70%; max-width: 220px; }
    }

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

  <?php if ($is_logged_in): ?>
  <a id="m3-exit" href="dashboard.php" aria-label="Back to Skulliance staking dashboard">
    <span class="mx-arrow">&larr;</span>
    <span class="mx-label">Back to Staking</span>
  </a>
  <?php endif; ?>

  <main>

    <div class="screenshot-band" role="presentation">
      <a href="<?php echo $play_href; ?>" aria-label="Play Monstrocity now">
        <img src="https://www.skulliance.io/staking/images/monstrocity/game.png"
             alt="Monstrocity Match 3 RPG gameplay screenshot"
             class="hero-screenshot"
             width="2040" height="1414"
             fetchpriority="high" decoding="async">
      </a>
    </div>

    <section class="hero">
      <div class="wrap">
        <h1>Free Match 3 RPG - Play in Your Browser</h1>
        <p class="lead">Monstrocity is a free online Match 3 RPG with real combat depth - character stats, special attacks, power-ups, and boss battles wrapped around the match-3 mechanics you already love. Plays in any modern browser on phone, tablet, or desktop.</p>
        <a href="<?php echo $play_href; ?>" class="cta" aria-label="Play Monstrocity free now">Play Free Now</a>
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
        <p>14 original characters anchor the base game in two flavors - Base and Leader - each with their own stats, size, and signature power-up.</p>
      </div>
      <?php
      // Base Monstrocity character roster (JSON order from monstrocity.php).
      // Path: /staking/images/monstrocity/monstrocity/{type}/{slug}.png where
      // type is "base" or "leader" and slug is the lowercase name with spaces
      // replaced by dashes. Same 14 names exist in both type folders.
      $characters = [
          'Craig', 'Merdock', 'Goblin Ganger', 'Texby', 'Mandiblus',
          'Koipon', 'Slime Mind', 'Billandar and Ted', 'Dankle', 'Jarhead',
          'Spydrax', 'Katastrophy', 'Ouchie', 'Drake',
      ];
      $char_root = 'https://www.skulliance.io/staking/images/monstrocity/monstrocity/';

      // Render a single strip. Each character appears twice so the CSS
      // 0% -> -50% translate loops seamlessly.
      $render_strip = function(array $chars, string $type_folder, string $type_label, bool $reverse) use ($char_root) {
          $track_class = 'strip-track' . ($reverse ? ' reverse' : '');
          $aria_label  = 'Monstrocity ' . $type_label . ' characters';
          echo '<div class="character-strip" aria-label="' . htmlspecialchars($aria_label) . '">';
          echo   '<div class="' . $track_class . '">';
          for ($pass = 0; $pass < 2; $pass++) {
              foreach ($chars as $char) {
                  $slug = strtolower(str_replace(' ', '-', $char));
                  $url  = $char_root . $type_folder . '/' . $slug . '.png';
                  $hide = $pass ? ' aria-hidden="true"' : '';
                  $load = $pass ? 'lazy' : 'eager';
                  echo '<div class="strip-card"' . $hide . '>';
                  echo   '<img src="' . htmlspecialchars($url) . '"';
                  echo        ' alt="' . htmlspecialchars($char . ' - Monstrocity ' . $type_label . ' character') . '"';
                  echo        ' loading="' . $load . '" decoding="async"';
                  echo        ' width="200" height="200"';
                  echo        ' onerror="this.onerror=null;this.src=\'/staking/icons/skull.png\';">';
                  echo   '<div class="name">' . htmlspecialchars($char) . '</div>';
                  echo '</div>';
              }
          }
          echo   '</div>';
          echo '</div>';
      };
      $render_strip($characters, 'base',   'Base',   false);
      $render_strip($characters, 'leader', 'Leader', true);
      ?>
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

    <section>
      <div class="wrap">
        <h2>Featured Artists &amp; Projects</h2>
        <p>Monstrocity bundles the original character set with dozens of visual themes contributed by independent artists. Each project below brings its own world - swap freely in-game, the mechanics stay the same and the art changes everything.</p>
        <p class="click-hint"><span class="click-icon" aria-hidden="true">&#x1F446;</span> <strong>Click any logo</strong> to preview that project's cast of characters.</p>
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
            'Nemonium'         => 'omen',
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
            'Ritual'           => 'ritual',
            'Sinder Skullz'    => 'sinderskullz',
            'Skowl'            => 'skowl',
            'Squashua'         => 'ug',
            'ADA Punks'        => 'adapunks',
            'Cardanians'       => 'cardanians',
            'Handies'          => 'handies',
        ];
        ?>
        <ul class="logo-grid">
          <?php foreach ($projects as $project => $theme_slug):
              $logo = $img_base . $theme_slug . '/logo.png';
          ?>
            <li class="logo-tile"
                role="button"
                tabindex="0"
                data-slug="<?php echo htmlspecialchars($theme_slug); ?>"
                data-project="<?php echo htmlspecialchars($project); ?>"
                aria-label="<?php echo htmlspecialchars($project); ?> - click to preview characters">
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
          <li><span class="mech-emoji" aria-hidden="true">🩸</span><span><strong>Heal (Bloody)</strong> - Restores 10 HP (reduced by enemy tactics).</span></li>
          <li><span class="mech-emoji" aria-hidden="true">💥</span><span><strong>Boost Attack (Cardano)</strong> - Adds +10 damage to the next attack (reduced by enemy tactics).</span></li>
          <li><span class="mech-emoji" aria-hidden="true">🔄</span><span><strong>Regenerate (ADA)</strong> - Restores 7 HP (reduced by enemy tactics).</span></li>
          <li><span class="mech-emoji" aria-hidden="true">🩹</span><span><strong>Minor Regen (None)</strong> - Restores 5 HP (reduced by enemy tactics).</span></li>
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
          <li><span class="mech-emoji" aria-hidden="true">💪</span><span><strong>Strength</strong> - Determines base damage for attacks (Strength × 2/3/4 for 3/4/5+ tiles).</span></li>
          <li><span class="mech-emoji" aria-hidden="true">⚡</span><span><strong>Speed</strong> - Determines turn order at the start of the level (higher Speed goes first; ties broken by Strength).</span></li>
          <li><span class="mech-emoji" aria-hidden="true">🧠</span><span><strong>Tactics</strong> - Gives a (Tactics × 10)% chance to halve incoming damage and reduces enemy power-up effects by (Tactics × 5)%.</span></li>
          <li><span class="mech-emoji" aria-hidden="true">📐</span><span><strong>Size</strong> - Large: +20% health, -2 Tactics (if Tactics &gt; 1); Medium: No effect; Small: -20% health, +2 Tactics (max 7).</span></li>
          <li><span class="mech-emoji" aria-hidden="true">🎭</span><span><strong>Type</strong> - Base: 85 health; Leader: 100 health; Battle Damaged: 70 health.</span></li>
        </ul>
      </div>
    </section>

    <section>
      <div class="wrap">
        <h2>How to Start Playing in Under 10 Seconds</h2>
        <ol>
          <li>Open <a href="<?php echo $play_href; ?>">the game</a> in any browser - phone, tablet, or desktop.</li>
          <li>Pick a visual theme (or stick with the default Monstrocity art).</li>
          <li>Choose a character and step into your first battle.</li>
          <li>Match tiles to attack, defend, and trigger power-ups. Beat the opponent.</li>
        </ol>
        <p style="margin-top: 18px;"><a href="<?php echo $play_href; ?>" class="cta">Start Playing Now</a></p>
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
        <a href="<?php echo $play_href; ?>" class="cta" aria-label="Play Monstrocity free Match 3 RPG now">Play Monstrocity Free</a>
      </div>
    </div>

    <section>
      <div class="wrap">
        <h2>More Free Games from Skulliance</h2>
        <div class="cross-promo">
          <a class="cross-shot" href="skullswap.php" aria-label="Play Skull Swap, a free match 3 puzzle game">
            <img src="https://www.skulliance.io/staking/images/skullswap.png"
                 alt="Skull Swap match 3 puzzle game board"
                 width="1207" height="1207" loading="lazy" decoding="async">
          </a>
          <div>
            <h3>Skull Swap - Match 3 Puzzle</h3>
            <p>Prefer a pure puzzle score chase? Skull Swap gives you exactly 25 matches to squeeze out every point - forge Carbon and Diamond bombs, chain detonations for huge combos, and cash your bombs in before the End Game Trap. Free in your browser, no download.</p>
            <a href="skullswap.php" class="cta" aria-label="Play Skull Swap free now">Play Skull Swap Free</a>
          </div>
        </div>
      </div>
    </section>

  </main>

  <footer>
    <p>© Skulliance · Monstrocity is a free browser-based Match 3 RPG. <a href="https://www.skulliance.io/">Visit Skulliance</a></p>
  </footer>

  <script>
  // Inline character-strip expansion for project logo tiles.
  // Clicking a project logo splits the grid at that row and inserts two
  // auto-scrolling rows (base + leader, opposite directions) for the
  // clicked project's theme. Clicking the same tile again collapses it;
  // clicking a different tile moves the expansion.
  (function () {
    var CHARS = ['Craig','Merdock','Goblin Ganger','Texby','Mandiblus','Koipon','Slime Mind','Billandar and Ted','Dankle','Jarhead','Spydrax','Katastrophy','Ouchie','Drake'];
    var IMG_ROOT = 'https://www.skulliance.io/staking/images/monstrocity/';
    var SKULL = '/staking/icons/skull.png';
    // Per-theme image extension for character images. Pulled from the
    // `extension:` field of each theme entry in monstrocity.php's JSON
    // (the comment in that file: "Applies only to character images").
    // Default is png; only themes that diverge need entries here.
    var EXT_BY_THEME = <?php echo json_encode([
        'galactico'    => 'gif',
        'machineheadz' => 'gif',
        'cardanians'   => 'gif',
    ]); ?>;

    function slugify(name) { return name.toLowerCase().replace(/ /g, '-'); }
    function extFor(themeSlug) { return EXT_BY_THEME[themeSlug] || 'png'; }

    function buildStrip(themeSlug, type, reverse) {
      var track = document.createElement('div');
      track.className = 'strip-track' + (reverse ? ' reverse' : '');
      var ext = extFor(themeSlug);
      // Two passes for seamless loop.
      for (var pass = 0; pass < 2; pass++) {
        CHARS.forEach(function (name) {
          var slug = slugify(name);
          var card = document.createElement('div');
          card.className = 'strip-card';
          if (pass) card.setAttribute('aria-hidden', 'true');

          var img = document.createElement('img');
          img.src = IMG_ROOT + themeSlug + '/' + type + '/' + slug + '.' + ext;
          img.alt = name + ' - ' + type + ' character';
          img.width = 200; img.height = 200;
          img.loading = 'lazy'; img.decoding = 'async';
          img.onerror = function () { this.onerror = null; this.src = SKULL; };

          var nameDiv = document.createElement('div');
          nameDiv.className = 'name';
          nameDiv.textContent = name;

          card.appendChild(img);
          card.appendChild(nameDiv);
          track.appendChild(card);
        });
      }
      var strip = document.createElement('div');
      strip.className = 'character-strip';
      strip.appendChild(track);
      return strip;
    }

    function buildExpansion(themeSlug, projectName) {
      var li = document.createElement('li');
      li.className = 'logo-tile-expansion';

      var inner = document.createElement('div');
      inner.className = 'expansion-inner';

      var h3 = document.createElement('h3');
      h3.textContent = projectName + ' Cast';
      inner.appendChild(h3);

      inner.appendChild(buildStrip(themeSlug, 'base', false));
      inner.appendChild(buildStrip(themeSlug, 'leader', true));

      li.appendChild(inner);
      return li;
    }

    // Visually identify the last tile in the same row as the clicked tile.
    // Grid layout uses auto-fit so the column count varies with viewport.
    // Group tiles by getBoundingClientRect().top so we work off actual
    // rendered rows rather than guessing column count.
    function findLastTileInRow(clickedTile) {
      var tiles = document.querySelectorAll('.logo-tile');
      var clickedTop = clickedTile.getBoundingClientRect().top;
      var last = clickedTile;
      for (var i = 0; i < tiles.length; i++) {
        var t = tiles[i];
        if (Math.abs(t.getBoundingClientRect().top - clickedTop) <= 2) {
          last = t;
        }
      }
      return last;
    }

    function activate(tile) {
      var slug = tile.dataset.slug;
      var project = tile.dataset.project;
      if (!slug) return;

      var alreadyActive = tile.classList.contains('active');

      // Wipe any existing expansion + active state. Doing this before the
      // row calculation so getBoundingClientRect sees the post-removal layout.
      var existing = document.querySelectorAll('.logo-tile-expansion');
      for (var i = 0; i < existing.length; i++) existing[i].remove();
      var prevActive = document.querySelectorAll('.logo-tile.active');
      for (var j = 0; j < prevActive.length; j++) prevActive[j].classList.remove('active');

      // Toggle off if user clicked the currently-open tile.
      if (alreadyActive) return;

      var expansion = buildExpansion(slug, project);
      var lastInRow = findLastTileInRow(tile);
      tile.parentNode.insertBefore(expansion, lastInRow.nextElementSibling);
      tile.classList.add('active');

      // Scroll the expansion into view so the user actually sees it.
      requestAnimationFrame(function () {
        expansion.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    }

    document.querySelectorAll('.logo-tile').forEach(function (tile) {
      tile.addEventListener('click', function () { activate(tile); });
      tile.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          activate(tile);
        }
      });
    });

    // Re-anchor expansion to the end of the active tile's row when the
    // viewport size changes (column count changes -> active tile may have
    // moved into a different row). Debounced so it doesn't fire constantly
    // during a drag-resize.
    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        var activeTile = document.querySelector('.logo-tile.active');
        var expansion = document.querySelector('.logo-tile-expansion');
        if (!activeTile || !expansion) return;
        var lastInRow = findLastTileInRow(activeTile);
        var desiredNext = lastInRow.nextElementSibling;
        if (expansion !== desiredNext && expansion !== lastInRow) {
          activeTile.parentNode.insertBefore(expansion, desiredNext);
        }
      }, 150);
    });
  })();
  </script>

</body>
</html>

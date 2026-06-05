<?php
// Skull Swap is public: playable logged-out, but scores only save for
// logged-in players (ajax/start-swap-game.php and ajax/save-swap-score.php
// both enforce this server-side). db.php is needed unconditionally for the
// tile-icon query below; it only resumes a session when cookies exist, so
// cookieless visitors don't generate orphan session files.
include_once 'db.php';

// Restore the staking session from the 6-month SessionCookie when PHPSESSID
// has lapsed (same pattern as monstrocity.php / match3rpg.php).
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        $_SESSION = $cookieData;
    }
}
$is_logged_in = isset($_SESSION['userData']['user_id']);

// Project currency icons - the game's tile set (this.allIcons) and, for
// public visitors, the landing page's scrolling icon marquee (which also
// wants the project name for its labels). Grouped by currency to keep the
// one-icon-per-currency dedupe the game relies on; MIN(name) picks a
// stable representative when projects share a currency.
$swap_projects = [];
$icon_res = $conn->query("SELECT LOWER(currency) AS currency, MIN(name) AS name FROM projects WHERE currency NOT IN ('DIAMOND','CARBON') AND currency != '' GROUP BY LOWER(currency) ORDER BY currency ASC");
while ($icon_row = $icon_res->fetch_assoc()) {
    $swap_projects[] = [
        'url'  => 'https://www.skulliance.io/staking/icons/' . $icon_row['currency'] . '.png',
        'name' => $icon_row['name'],
    ];
}
$swap_icons = array_column($swap_projects, 'url');

// Standalone page for EVERYONE - same treatment as match3rpg.php /
// monstrocity.php: no header.php nav and no staking platform CSS (which
// fights the landing's design). Logged-in users keep their session (db.php
// resumed it above) and get a floating back-to-staking button instead of
// the nav; score saving is unaffected because the ajax endpoints handle
// their own session/auth independently of this page's includes.
$ss_canonical = 'https://www.skulliance.io/staking/skullswap.php';
$ss_image     = 'https://www.skulliance.io/staking/images/skullswap.png';
$ss_title     = 'Skull Swap - Free Match 3 Puzzle Game | Play in Your Browser';
$ss_desc      = 'Play Skull Swap free - a match 3 puzzle game with carbon and diamond bombs, cascade combos, and a 25-match score chase. Works on mobile, tablet, and desktop. No download, no signup.';
$ss_short     = 'A free browser match 3 puzzle game with bombs, cascades, and a 25-match score chase. Play on any device - no download.';
?>
<!doctype html>
<html lang="en">
<head>
  <title><?php echo htmlspecialchars($ss_title); ?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="<?php echo htmlspecialchars($ss_desc); ?>">
  <meta name="keywords" content="free match 3 game, match 3 puzzle game, browser match 3 game, online match 3 game, no download match 3 game, free puzzle game, skull game, skull swap, match three game, mobile match 3 game">
  <meta name="theme-color" content="#161616">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
  <link rel="canonical" href="<?php echo $ss_canonical; ?>">

  <!-- OpenGraph -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="<?php echo $ss_canonical; ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($ss_title); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($ss_desc); ?>">
  <meta property="og:image" content="<?php echo $ss_image; ?>">
  <meta property="og:image:width" content="1207">
  <meta property="og:image:height" content="1207">
  <meta property="og:image:alt" content="Skull Swap match 3 puzzle game board">
  <meta property="og:locale" content="en_US">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($ss_title); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($ss_short); ?>">
  <meta name="twitter:image" content="<?php echo $ss_image; ?>">
  <meta name="twitter:image:alt" content="Skull Swap match 3 puzzle game board">

  <!-- Schema.org structured data: VideoGame + BreadcrumbList
       (FAQPage schema intentionally omitted - Google retired FAQ rich
       results on 2026-05-07; FAQ content kept inline for topical depth,
       same approach as match3rpg.php) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "VideoGame",
        "name": "Skull Swap",
        "alternateName": ["Skull Swap Match 3", "Skull Swap Puzzle Game"],
        "url": "<?php echo $ss_canonical; ?>",
        "image": "<?php echo $ss_image; ?>",
        "screenshot": "<?php echo $ss_image; ?>",
        "description": "<?php echo $ss_short; ?>",
        "genre": ["Match 3", "Puzzle"],
        "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
        "playMode": "SinglePlayer",
        "applicationCategory": "Game",
        "operatingSystem": "Any",
        "inLanguage": "en",
        "offers": {
          "@type": "Offer",
          "price": "0",
          "priceCurrency": "USD",
          "availability": "https://schema.org/InStock"
        },
        "publisher": {
          "@type": "Organization",
          "name": "Skulliance",
          "url": "https://www.skulliance.io/"
        },
        "potentialAction": {
          "@type": "PlayAction",
          "target": "<?php echo $ss_canonical; ?>"
        }
      },
      {
        "@type": "BreadcrumbList",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "name": "Skulliance",
            "item": "https://www.skulliance.io/"
          },
          {
            "@type": "ListItem",
            "position": 2,
            "name": "Skull Swap - Free Match 3 Puzzle Game",
            "item": "<?php echo $ss_canonical; ?>"
          }
        ]
      }
    ]
  }
  </script>

  <style>
    html { scroll-behavior: smooth; }
    body {
      background: #07111d; margin: 0; color: #e8eaed;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      line-height: 1.55; overflow-x: hidden; -webkit-font-smoothing: antialiased;
    }
  </style>
  <style>
    /* Marketing landing styles - all visitors (the page is standalone
       like match3rpg.php, so no platform CSS fights these). Scoped under
       #ss-landing. Design language matches match3rpg.php / the staking
       platform theme: brand green-teal accents (#00c8a0 -> #0596c4), navy
       base, glow hero, gradient pill CTAs, accent-bar mechanics, score
       table, gradient final CTA. */
    #ss-landing {
      background: #07111d; color: #e8eaed; width: 100%;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      line-height: 1.55; -webkit-font-smoothing: antialiased;
    }
    #ss-landing *, #ss-landing *::before, #ss-landing *::after { box-sizing: border-box; }
    #ss-landing h1, #ss-landing h2, #ss-landing h3 { line-height: 1.2; margin: 0 0 0.5em; font-weight: 700; }
    #ss-landing h1 { font-size: clamp(1.9rem, 4.5vw, 3.2rem); }
    #ss-landing h2 { font-size: clamp(1.4rem, 3vw, 2rem); }
    #ss-landing h3 { font-size: 1.12rem; color: #00c8a0; }
    #ss-landing p { margin: 0 0 1em; }
    #ss-landing a { color: #00c8a0; text-decoration: none; }
    #ss-landing a:hover { color: #34e3bb; text-decoration: underline; }
    .ss-wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; }

    .ss-hero {
      text-align: center; padding: 56px 20px 48px;
      background:
        radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%),
        linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
      border-top: 1px solid rgba(255,255,255,0.08);
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .ss-subtitle {
      display: block; margin-top: 8px;
      font-size: clamp(1.05rem, 2.5vw, 1.6rem);
      font-weight: 600; color: #c7d0d9;
    }
    /* ID-prefixed to outrank the #ss-landing p reset above */
    #ss-landing .ss-lead {
      text-align: center; font-size: clamp(1rem, 2vw, 1.18rem);
      max-width: 700px; margin: 0 auto 28px; color: #c7d0d9;
    }
    .ss-cta {
      display: inline-block;
      background: linear-gradient(135deg, #00c8a0, #0596c4);
      color: #07111d !important; font-weight: 800; font-size: 1.08rem;
      padding: 14px 32px; border: none; border-radius: 999px; cursor: pointer;
      font-family: inherit; text-decoration: none !important;
      box-shadow: 0 6px 20px rgba(0, 200, 160, 0.35);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .ss-cta:hover, .ss-cta:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(0, 200, 160, 0.5);
    }
    .ss-cta.ss-secondary {
      background: transparent; color: #00c8a0 !important;
      border: 1px solid rgba(0, 200, 160, 0.45); box-shadow: none; margin-left: 12px;
    }
    .ss-cta.ss-secondary:hover { background: rgba(0, 200, 160, 0.08); }
    .ss-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 22px; }
    .ss-badge {
      font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase;
      padding: 6px 12px; border-radius: 999px;
      background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #c7d0d9;
    }

    /* Framed game-board screenshot leading the hero - a plain image, not
       a background. This is the first thing visitors see; clicking it
       starts the game, so it gets a hover lift as an affordance. */
    .ss-shot-link { display: block; cursor: pointer; }
    .ss-shot {
      display: block; width: 100%; max-width: 480px; height: auto;
      margin: 0 auto 30px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.15);
      box-shadow: 0 30px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(0,200,160,0.1) inset;
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .ss-shot-link:hover .ss-shot, .ss-shot-link:focus-visible .ss-shot {
      transform: translateY(-3px);
      border-color: rgba(0, 200, 160, 0.5);
      box-shadow: 0 36px 90px rgba(0,0,0,0.75), 0 0 24px rgba(0,200,160,0.25);
    }

    .ss-section { padding: 44px 0; }
    .ss-section + .ss-section { border-top: 1px solid rgba(255,255,255,0.06); }
    .ss-section p, .ss-section li { color: #c7d0d9; }

    .ss-features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 20px; }
    @media (max-width: 560px) { .ss-features { grid-template-columns: 1fr; } }
    .ss-card {
      background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px; padding: 22px;
    }
    .ss-card h3 { margin-bottom: 8px; }
    .ss-card p { margin: 0; font-size: 0.96rem; }

    /* Auto-scrolling project icon marquee - same pattern as match3rpg's
       character strips: duplicated track sliding -50%, opposite directions
       per row, pause on hover, edge fade masks. */
    .ss-strip {
      width: 100%;
      overflow: hidden;
      padding: 18px 0;
      -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
              mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
    }
    .ss-strip + .ss-strip { padding-top: 0; }
    .ss-strip-track {
      display: flex;
      align-items: flex-start;
      gap: 34px;
      width: max-content;
      animation: ss-strip-scroll 45s linear infinite;
      will-change: transform;
    }
    .ss-strip-track.ss-reverse {
      animation-direction: reverse;
      animation-duration: 50s;
    }
    .ss-strip-track:hover { animation-play-state: paused; }
    @keyframes ss-strip-scroll {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }
    @media (prefers-reduced-motion: reduce) {
      .ss-strip-track { animation: none; }
    }
    .ss-strip-card {
      flex: 0 0 104px;
      text-align: center;
    }
    .ss-strip-card img {
      width: 64px; height: 64px;
      margin: 0 auto;
      object-fit: contain;
      filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.65));
    }
    .ss-strip-card .ss-ticker {
      margin-top: 8px;
      font-size: 0.72rem;
      line-height: 1.3;
      color: #8a96a3;
      font-weight: 600;
      letter-spacing: 0.04em;
    }

    /* Scoring mechanics: accent-bar list with bomb icons */
    .ss-mechanics { list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
    .ss-mechanics li {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 16px; background: rgba(255,255,255,0.03);
      border-left: 3px solid #00c8a0; border-radius: 6px; font-size: 0.95rem;
    }
    .ss-mechanics li strong { color: #34e3bb; }
    .ss-mech-icon { flex-shrink: 0; }
    .ss-mech-icon img { width: 32px; height: 32px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); }
    .ss-mech-emoji {
      flex-shrink: 0; width: 32px; text-align: center;
      font-size: 1.5rem; line-height: 1.2;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
    }

    .ss-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; margin-top: 18px; }
    .ss-table th {
      text-align: left; padding: 8px 12px; background: rgba(255,255,255,0.06);
      color: rgba(255,255,255,0.55); font-size: 0.76rem; letter-spacing: 0.07em; text-transform: uppercase;
    }
    .ss-table td { padding: 9px 12px; border-bottom: 1px solid rgba(255,255,255,0.06); color: #c7d0d9; }
    .ss-table tr:last-child td { border-bottom: none; }
    .ss-table td strong { color: #34e3bb; }
    .ss-table img { width: 16px; height: 16px; object-fit: contain; vertical-align: middle; margin: 0 2px; }

    /* Strategy tips: numbered pills */
    .ss-tips { list-style: none; counter-reset: tip; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 10px; }
    .ss-tips li {
      counter-increment: tip; position: relative;
      padding: 14px 16px 14px 56px; background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; font-size: 0.95rem;
    }
    .ss-tips li::before {
      content: counter(tip);
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      width: 28px; height: 28px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #00c8a0, #0596c4);
      color: #07111d; font-weight: 800; font-size: 0.9rem;
    }
    .ss-tips li strong { color: #34e3bb; }

    .ss-center { text-align: center; }
    /* Cross-promotion card for Monstrocity */
    .ss-cross {
      display: flex; align-items: center; gap: 28px;
      margin-top: 20px; padding: 24px;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
    }
    .ss-cross .ss-cross-shot { flex: 0 0 200px; display: block; }
    .ss-cross .ss-cross-shot img {
      width: 100%; height: auto;
      filter: drop-shadow(0 12px 32px rgba(0,0,0,0.5));
      transition: transform 0.15s ease;
    }
    .ss-cross .ss-cross-shot:hover img,
    .ss-cross .ss-cross-shot:focus-visible img {
      transform: translateY(-2px);
    }
    .ss-cross h3 { margin-top: 0; }
    .ss-cross p { font-size: 0.96rem; }
    @media (max-width: 640px) {
      .ss-cross { flex-direction: column; text-align: center; }
      .ss-cross .ss-cross-shot { flex-basis: auto; width: 70%; max-width: 220px; }
    }
    /* Centered quickstart section */
    .ss-quickstart { text-align: center; }
    .ss-quickstart ol { list-style-position: inside; padding: 0; margin: 16px 0 24px; }
    .ss-quickstart ol li { margin: 6px 0; }
    /* FAQ accordions, match3rpg pattern */
    .ss-faq details {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 10px;
      padding: 16px 20px;
      margin: 10px 0;
    }
    .ss-faq summary {
      cursor: pointer; font-weight: 600; color: #e8eaed;
      list-style: none; position: relative; padding-right: 28px;
    }
    .ss-faq summary::-webkit-details-marker { display: none; }
    .ss-faq summary::after {
      content: "+";
      position: absolute; right: 0; top: 50%; transform: translateY(-50%);
      color: #00c8a0; font-size: 1.4rem; font-weight: 400; line-height: 1;
    }
    .ss-faq details[open] summary::after { content: "\2212"; }
    .ss-faq summary:focus-visible { outline: 2px solid #00c8a0; outline-offset: 4px; border-radius: 4px; }
    .ss-faq details p { margin: 12px 0 0; color: #c7d0d9; }
    .ss-final {
      text-align: center; padding: 56px 20px;
      background: linear-gradient(135deg, rgba(0,200,160,0.12), rgba(5,150,196,0.08));
      border-radius: 16px; margin-top: 8px;
    }
    .ss-final h2 { margin-top: 0; }
    /* ID-prefixed to outrank the #ss-landing p reset; the top margin is
       the requested space above the login note */
    #ss-landing .ss-login-note { font-size: 0.9rem; color: #8a96a3; margin: 28px 0 0; }
    .ss-footer {
      padding: 28px 20px; text-align: center; color: #8a96a3; font-size: 0.88rem;
      border-top: 1px solid rgba(255,255,255,0.06);
    }
    .ss-footer a { color: #8a96a3; }
    @media (max-width: 480px) {
      .ss-cta { width: 100%; text-align: center; }
      .ss-cta.ss-secondary { margin-left: 0; margin-top: 10px; }
    }

    /* Floating back-to-staking pill for logged-in users on the LANDING
       only - mirrors match3rpg's #m3-exit. ssPlay() hides it when the
       game activates; the GO BACK button below the board takes over. */
    #ss-exit {
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
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      transition: background 0.15s, border-color 0.15s;
    }
    #ss-exit:hover, #ss-exit:active {
      background: rgba(0, 200, 160, 0.18);
      border-color: #00c8a0;
      color: #e8eaed;
    }
    #ss-exit .mx-arrow {
      font-size: 1.05rem;
      line-height: 1;
      color: #00c8a0;
    }
    @media (max-width: 480px) {
      #ss-exit .mx-label { display: none; }
      #ss-exit { padding: 8px 10px; }
    }
  </style>
 <style>
	 /*
         body {
             background: #0F0F0F;
             margin: 0;
             height: 100vh;
             display: flex;
             justify-content: center;
             align-items: center;
             overflow: hidden;
         }*/
         #game-container {
             display: flex;
             flex-direction: column;
             align-items: center;
             position: relative;
             margin-top: 12px;
         }
         #hud {
             width: 100%;
             display: flex;
             justify-content: space-between;
             padding-bottom: 10px;
         }
         #score {
             font-size: 24px;
             font-family: Arial;
             color: #fff;
             text-align: left;
             white-space: nowrap; /* high scores must never wrap to two lines */
         }
         #matches {
             font-size: 24px;
             font-family: Arial;
             color: #fff;
             text-align: right;
             white-space: nowrap;
         }
        @media (max-width: 768px) {
            /* HUD type drops a couple points so max scores fit one line
               on small phones. No top clearance needed: the floating
               back pill is landing-only, and gameplay's GO BACK lives in
               the button row below the board. The old 48px burger-menu
               gutter on #matches is gone with the header. */
            #score, #matches { font-size: 20px; }
        }
         #game-board {
             display: grid;
             gap: 0.5vh;
             background: #333;
             padding: 1vh;
             box-sizing: border-box;
             user-select: none;
             position: relative;
             touch-action: none;
         }
         .tile {
             width: 100%;
             height: 100%;
             display: flex;
             align-items: center;
             justify-content: center;
             font-size: 2vh;
             cursor: pointer;
             transition: transform 0.2s ease, filter 0.5s ease;
             position: relative;
             background: #444;
             box-sizing: border-box;
             padding: 0.25vh;
             z-index: 1;
			 box-shadow: 0px 1px 5px black;
         }
         .tile.game-over {
             filter: grayscale(100%);
         }
         .tile img {
             width: 80%;
             height: 80%;
             object-fit: contain;
             position: absolute;
             z-index: 1;
         }
         .tile::before {
             content: '';
             position: absolute;
             top: 0;
             left: 0;
             width: 100%;
             height: 100%;
             opacity: 0.6;
             z-index: 0;
         }
         .selected {
             transform: scale(1.05);
             border: 0.25vh solid white;
             border-radius: 0;
             z-index: 10;
             pointer-events: none;
             padding: 0;
         }
         .tile:hover {
             outline: 2px solid rgba(255,255,255,0.7);
             outline-offset: -2px;
         }
         .matched {
             animation: matchAnimation 0.3s ease forwards;
         }
         .falling {
             transition: transform 0.3s ease-out;
         }
         .falling-fast {
             transition: transform 0.1s ease-out;
         }
         .bomb-creation {
             animation: bombPopIn 0.5s ease forwards;
         }
         .carbon-clear {
             animation: carbonSweep 0.8s ease forwards;
         }
         .diamond-clear {
             animation: diamondShockwave 1s ease forwards;
         }
         #game-over-container {
             position: absolute;
             top: 50%;
             left: 50%;
             transform: translate(-50%, -50%);
             text-align: center;
             z-index: 30;
             display: none;
             background: rgba(0, 0, 0, 0.8);
             padding: 20px;
             border-radius: 10px;
         }
         #game-over-buttons {
             display: flex;
             flex-direction: column;
             align-items: center;
             width: 100%;
         }
         #game-over {
             font-size: 48px;
             font-family: Arial;
             color: #ffffff;
             text-shadow: 2px 2px 4px #000;
             margin: 0 0 20px 0;
             animation: gameOverPulse 1s ease-in-out infinite;
         }
         #try-again, #leaderboard {
             font-size: 24px;
             font-family: Arial;
			 font-weight: bold;
             color: #ffffff;
             background-color: #444;
             border: 2px solid #fff;
             padding: 10px 20px;
             margin: 10px 0;
             cursor: pointer;
             transition: background-color 0.3s ease, transform 0.2s ease;
             width: 250px;
             box-sizing: border-box;
             text-align: center;
         }
         #try-again:hover, #leaderboard:hover {
             background-color: #666;
             transform: scale(1.05);
         }
         @keyframes matchAnimation {
             0% { transform: scale(1); opacity: 1; }
             50% { transform: scale(1.2); opacity: 0.8; }
             100% { transform: scale(0); opacity: 0; }
         }
         @keyframes bombPopIn {
             0% { transform: scale(0); opacity: 0; }
             70% { transform: scale(1.2); opacity: 1; }
             100% { transform: scale(1); opacity: 1; }
         }
         @keyframes carbonSweep {
             0% { transform: scale(1); opacity: 1; background-color: #ff6600; box-shadow: 0 0 10px #ff3300; }
             50% { transform: scale(1.2); opacity: 0.8; background-color: #ff9900; box-shadow: 0 0 20px #ff6600; }
             100% { transform: scale(0); opacity: 0; background-color: #ffcc00; box-shadow: 0 0 30px #ff9900; }
         }
         @keyframes diamondShockwave {
             0% { transform: scale(1); opacity: 1; background-color: #ffffff; box-shadow: 0 0 0 #ff00ff; }
             50% { transform: scale(1.5); opacity: 0.8; background-color: #ff00ff; box-shadow: 0 0 20px #ff00ff; }
             75% { transform: scale(1); opacity: 0.5; background-color: #9900cc; box-shadow: 0 0 40px #ff00ff; }
             100% { transform: scale(0); opacity: 0; background-color: #000000; box-shadow: 0 0 60px #ff00ff; }
         }
         @keyframes gameOverPulse {
             0% { transform: scale(1); opacity: 0.8; }
             50% { transform: scale(1.1); opacity: 1; }
             100% { transform: scale(1); opacity: 0.8; }
         }
        /* Button row below the board: GO BACK (logged-in only) + HOW TO
           PLAY, sharing one quiet style so neither distracts from play. */
        #board-btns {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
        }
        .board-btn {
            display: inline-block;
            font-size: 13px;
            font-family: Arial;
            font-weight: bold;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.5);
            background: transparent;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 18px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s, border-color 0.2s;
        }
        .board-btn:hover { color: #fff; border-color: rgba(255,255,255,0.4); }
        #guide-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }
        #guide-modal {
            background: #0d1b2a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            max-width: 600px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            padding: 28px 28px 24px;
            box-sizing: border-box;
            position: relative;
            color: rgba(255,255,255,0.85);
            font-family: Arial, sans-serif;
        }
        #guide-modal h2 {
            font-size: 1.3rem;
            color: #fff;
            margin: 0 0 20px;
            text-align: center;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        #guide-close {
            position: absolute;
            top: 14px; right: 16px;
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.4);
            font-size: 1.2rem;
            cursor: pointer;
            line-height: 1;
        }
        #guide-close:hover { color: #fff; }
        .guide-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .guide-section:last-child { border-bottom: none; margin-bottom: 0; }
        .guide-section h3 {
            font-size: 0.82rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.45);
            margin: 0 0 10px;
        }
        .guide-section p, .guide-section li {
            font-size: 0.88rem;
            line-height: 1.6;
            color: rgba(255,255,255,0.78);
            margin: 0 0 7px;
        }
        .guide-section ul, .guide-section ol {
            margin: 6px 0 8px;
            padding-left: 20px;
        }
        .guide-bomb-card {
            border-radius: 7px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .carbon-card { background: rgba(255,102,0,0.1); border: 1px solid rgba(255,102,0,0.25); }
        .diamond-card { background: rgba(153,0,204,0.1); border: 1px solid rgba(180,0,255,0.25); }
        .guide-bomb-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            font-size: 0.95rem;
            color: #fff;
            margin-bottom: 8px;
        }
        .guide-bomb-header img { width: 22px; height: 22px; object-fit: contain; }
        .guide-bomb-sub { font-weight: normal; font-size: 0.78rem; color: rgba(255,255,255,0.45); }
        .guide-callout {
            background: rgba(255,255,255,0.04);
            border-left: 3px solid rgba(255,255,255,0.2);
            border-radius: 0 5px 5px 0;
            padding: 10px 14px !important;
            margin-top: 10px !important;
            font-style: italic;
        }
        .guide-warning { background: rgba(255,180,0,0.05); border-radius: 7px; padding: 12px 14px; border: 1px solid rgba(255,180,0,0.18); }
        .guide-warning h3 { color: rgba(255,200,0,0.7); }
        .guide-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            margin-top: 6px;
        }
        .guide-table th {
            text-align: left;
            padding: 6px 10px;
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }
        .guide-table td {
            padding: 7px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.78);
        }
        .guide-table tr:last-child td { border-bottom: none; }
        .guide-table img { width: 16px; height: 16px; object-fit: contain; vertical-align: middle; margin: 0 2px; }
     </style>
 </head>
 <body>
  <?php if ($is_logged_in): ?>
     <!-- Floating back button for logged-in users - the page has no nav.
          Collapses to a bare arrow once the game is activated. -->
     <a id="ss-exit" href="dashboard.php" aria-label="Back to Skulliance staking dashboard">
         <span class="mx-arrow">&larr;</span>
         <span class="mx-label">Back to Staking</span>
     </a>
  <?php endif; ?>

     <!-- Hidden for ALL visitors until they hit Play on the landing -->
     <div id="game-container" style="display:none">
         <div id="hud">
             <div id="score">Score: 0</div>
             <div id="matches">Matches: 0/25</div>
         </div>
         <div id="game-board"></div>
         <div id="game-over-container">
             <div id="game-over">GAME OVER</div>
             <div id="game-over-buttons">
                 <button id="try-again">TRY AGAIN</button>
                 <!-- Shown to public players too: leaderboards.php's login gate
                      lands them on error.php with a Back to Login link, which
                      doubles as a signup prompt. -->
                 <form id="leaderboard-form" action="leaderboards.php" method="POST">
                     <input type="hidden" name="filterby" value="weekly-swaps">
                     <button id="leaderboard" type="submit">LEADERBOARD</button>
                 </form>
             </div>
         </div>
         <div id="board-btns">
             <?php if ($is_logged_in): ?>
             <a id="back-btn" class="board-btn" href="dashboard.php" aria-label="Back to Skulliance staking dashboard">GO BACK</a>
             <?php endif; ?>
             <button id="guide-btn" class="board-btn" onclick="openGuide()">HOW TO PLAY</button>
         </div>
     </div>

     <!-- Skull Swap Guide Modal -->
     <div id="guide-overlay" onclick="if(event.target===this)closeGuide()">
         <div id="guide-modal">
             <button id="guide-close" onclick="closeGuide()">&#x2715;</button>
             <h2>&#x1F480; Skull Swap Guide &#x1F480;</h2>

             <div class="guide-section">
                 <h3>The Basics</h3>
                 <p>You get <strong>25 matches</strong>. The goal is to squeeze maximum points out of each one.</p>
                 <p>Every tile cleared = <strong>10 points</strong>. A plain match-3 gives you 30 points. That&#39;s the floor &#8211; not the ceiling.</p>
             </div>

             <div class="guide-section">
                 <h3>The Two Special Tiles</h3>
                 <div class="guide-bomb-card carbon-card">
                     <div class="guide-bomb-header">
                         <img src="icons/carbon.png" alt="Carbon"> CARBON <span class="guide-bomb-sub">(grey bomb)</span>
                     </div>
                     <ul>
                         <li>Created by matching <strong>exactly 4 tiles</strong> in a row or column</li>
                         <li>When detonated, wipes its entire <strong>row + entire column</strong></li>
                         <li>Scoring: 10 pts per tile cleared + <strong>25 point bonus</strong></li>
                     </ul>
                 </div>
                 <div class="guide-bomb-card diamond-card">
                     <div class="guide-bomb-header">
                         <img src="icons/diamond.png" alt="Diamond"> DIAMOND <span class="guide-bomb-sub">(black bomb)</span>
                     </div>
                     <ul>
                         <li>Created by matching <strong>5 or more tiles</strong></li>
                         <li>When detonated, wipes the <strong>entire board</strong></li>
                         <li>Scoring: 10 pts per tile cleared + <strong>50 point bonus</strong></li>
                     </ul>
                 </div>
                 <p class="guide-callout">On an 8&times;8 board a <img src="icons/diamond.png" style="width:14px;height:14px;vertical-align:middle;"> Diamond bomb clears ~63 tiles = <strong>680 points</strong> in one move. A plain match-3 gives 30. That&#39;s the gap you need to close.</p>
             </div>

             <div class="guide-section">
                 <h3>How to Detonate Bombs</h3>
                 <p>Bombs sit on the board like normal tiles. To set one off, slide it into a 3-match with tiles that share its icon. The explosion happens automatically after the match resolves.</p>
             </div>

             <div class="guide-section">
                 <h3>Chain Detonations Are Where Big Scores Come From</h3>
                 <p>If a bomb explosion hits another bomb, the second bomb chain-detonates for an extra:</p>
                 <ul>
                     <li><img src="icons/carbon.png" style="width:14px;height:14px;vertical-align:middle;"> Carbon chain = <strong>+50 bonus</strong></li>
                     <li><img src="icons/diamond.png" style="width:14px;height:14px;vertical-align:middle;"> Diamond chain = <strong>+100 bonus</strong></li>
                 </ul>
                 <p>Stack bombs near each other intentionally. One Diamond detonating into a Carbon = massive combo.</p>
             </div>

             <div class="guide-section guide-warning">
                 <h3>&#x26A0; The End Game Trap</h3>
                 <p>When your 25th match fires, all remaining bombs auto-detonate &#8211; but you <strong>lose the manual bonuses (+25/+50)</strong>. You still get 10 pts per tile, but nothing extra.</p>
                 <p><strong>Don&#39;t leave bombs on the board when you hit match 25.</strong> Detonate them manually in the moves before your last match to collect the full bonuses.</p>
             </div>

             <div class="guide-section">
                 <h3>Priority Order</h3>
                 <ol>
                     <li>Always take a <strong>5-match over a 3-match</strong> &#8211; <img src="icons/diamond.png" style="width:14px;height:14px;vertical-align:middle;"> Diamond bomb is worth hundreds of points</li>
                     <li>Take a <strong>4-match over a 3-match</strong> &#8211; <img src="icons/carbon.png" style="width:14px;height:14px;vertical-align:middle;"> Carbon bomb beats a plain clear every time</li>
                     <li><strong>Position your drag endpoint</strong> &#8211; where you release determines where the bomb spawns, so place it where it can chain later</li>
                     <li><strong>Detonate before match 25</strong> &#8211; never let the game auto-fire your bombs at discount rates</li>
                 </ol>
             </div>

             <div class="guide-section">
                 <h3>Quick Score Reference</h3>
                 <table class="guide-table">
                     <thead><tr><th>Move</th><th>Points</th></tr></thead>
                     <tbody>
                         <tr><td>Match 3</td><td>30</td></tr>
                         <tr><td>Match 4 (makes <img src="icons/carbon.png" alt=""> Carbon bomb)</td><td>40</td></tr>
                         <tr><td>Match 5 (makes <img src="icons/diamond.png" alt=""> Diamond bomb)</td><td>50</td></tr>
                         <tr><td>Manually detonate <img src="icons/carbon.png" alt=""> Carbon (13-tile cross)</td><td>~130 + 25 = <strong>155</strong></td></tr>
                         <tr><td>Manually detonate <img src="icons/diamond.png" alt=""> Diamond (full board)</td><td>~630 + 50 = <strong>680</strong></td></tr>
                         <tr><td>Chain: <img src="icons/carbon.png" alt=""> Carbon into <img src="icons/diamond.png" alt=""> Diamond</td><td><strong>+100 bonus</strong> on top</td></tr>
                     </tbody>
                 </table>
             </div>
         </div>
     </div>

     <script>
function openGuide() { document.getElementById('guide-overlay').style.display = 'flex'; }
function closeGuide() { document.getElementById('guide-overlay').style.display = 'none'; }
     </script>

     <!-- Marketing landing shown to ALL visitors by default; the game
          board image leads, the playable game stays hidden until Play is
          clicked (ssPlay hides this landing and reveals the board). The
          board image itself is clickable and starts the game too. All
          numbers mirror the in-game guide modal so the copy stays
          truthful to the mechanics - if scoring is ever rebalanced,
          update both. -->
     <div id="ss-landing">
         <header class="ss-hero">
             <a class="ss-shot-link" href="#" onclick="ssPlay(); return false;" aria-label="Play Skull Swap now">
                 <img class="ss-shot" src="https://www.skulliance.io/staking/images/skullswap.png" alt="Skull Swap match 3 puzzle game board with skull tiles and bombs" width="1207" height="1207" fetchpriority="high" decoding="async">
             </a>
             <h1>&#x1F480; Skull Swap &#x1F480;<span class="ss-subtitle">Free Match 3 Puzzle Game</span></h1>
             <p class="ss-lead">Swap, match, and detonate your way through 25 matches. Skull Swap is a free browser match 3 built around bombs, chain reactions, and squeezing every point out of a limited-move run. No download, no signup - just play.</p>
             <button class="ss-cta" type="button" onclick="ssPlay()">Play Free Now</button>
             <a class="ss-cta ss-secondary" href="#ss-scoring">How Scoring Works</a>
             <div class="ss-badges" aria-label="Game highlights">
                 <span class="ss-badge">100% Free</span>
                 <span class="ss-badge">No Download</span>
                 <span class="ss-badge">No Signup</span>
                 <span class="ss-badge">Mobile &amp; Desktop</span>
             </div>
         </header>

         <section class="ss-section">
             <div class="ss-wrap">
                 <h2>Not Your Average Match 3</h2>
                 <div class="ss-features">
                     <div class="ss-card">
                         <h3>&#x1F4A3; Carbon &amp; Diamond Bombs</h3>
                         <p>Match 4 to forge a Carbon bomb that wipes a full row and column. Match 5 for a Diamond bomb that clears the entire board - up to 680 points in a single move.</p>
                     </div>
                     <div class="ss-card">
                         <h3>&#x26D3; Chain Detonations</h3>
                         <p>Bomb blasts that hit other bombs set them off too, stacking +50 and +100 bonuses on top. Planting bombs near each other is where monster scores come from.</p>
                     </div>
                     <div class="ss-card">
                         <h3>&#x1F3AF; 25 Matches, Make Them Count</h3>
                         <p>Every run is exactly 25 matches. No timers, no lives - pure score-per-move strategy where a 5-match always beats a quick 3.</p>
                     </div>
                     <div class="ss-card">
                         <h3>&#x26A0; The End Game Trap</h3>
                         <p>Leftover bombs auto-detonate at discount rates when your last match fires. Cashing them in manually before match 25 separates good scores from great ones.</p>
                     </div>
                 </div>
             </div>
         </section>

         <section class="ss-section">
             <div class="ss-wrap ss-center">
                 <h2>Featured Artists &amp; Projects on Every Tile</h2>
                 <p>The tiles you match aren't generic gems - they're icons from artists and projects who have partnered with Skulliance. Every run deals a fresh hand of seven.</p>
             </div>
             <?php
             // Two opposite-direction marquee rows; each track holds its icon
             // list twice for the seamless -50% translate loop (second pass
             // is aria-hidden). Bottom row runs the reversed list so the
             // rows don't mirror each other.
             $strip_rows = [
                 ['projects' => $swap_projects,                'class' => ''],
                 ['projects' => array_reverse($swap_projects), 'class' => ' ss-reverse'],
             ];
             foreach ($strip_rows as $row): ?>
             <div class="ss-strip">
                 <div class="ss-strip-track<?php echo $row['class']; ?>">
                     <?php for ($pass = 0; $pass < 2; $pass++):
                         foreach ($row['projects'] as $proj): ?>
                     <div class="ss-strip-card"<?php if ($pass) echo ' aria-hidden="true"'; ?>>
                         <img src="<?php echo htmlspecialchars($proj['url']); ?>"
                              alt="<?php echo $pass ? '' : htmlspecialchars($proj['name']) . ' project icon'; ?>"
                              loading="lazy" decoding="async" width="64" height="64"
                              onerror="this.onerror=null;this.src='/staking/icons/skull.png';">
                         <div class="ss-ticker"><?php echo htmlspecialchars($proj['name']); ?></div>
                     </div>
                     <?php endforeach; endfor; ?>
                 </div>
             </div>
             <?php endforeach; ?>
         </section>

         <section class="ss-section" id="ss-scoring">
             <div class="ss-wrap">
                 <h2>How Scoring Works</h2>
                 <p>Every tile cleared is worth 10 points, so a plain match-3 pays 30. That's the floor, not the ceiling - the real economy is in the bombs:</p>
                 <ul class="ss-mechanics">
                     <li>
                         <span class="ss-mech-icon"><img src="icons/carbon.png" alt="Carbon bomb" loading="lazy" decoding="async" width="32" height="32"></span>
                         <span><strong>Carbon Bomb (match 4)</strong> - detonates its entire row and column, a 13-tile cross worth ~130 points plus a +25 manual-detonation bonus.</span>
                     </li>
                     <li>
                         <span class="ss-mech-icon"><img src="icons/diamond.png" alt="Diamond bomb" loading="lazy" decoding="async" width="32" height="32"></span>
                         <span><strong>Diamond Bomb (match 5+)</strong> - wipes the whole board: ~630 points plus a +50 bonus. One move, twenty times a plain match.</span>
                     </li>
                     <li>
                         <span class="ss-mech-emoji" aria-hidden="true">&#x26D3;</span>
                         <span><strong>Chains</strong> - a blast that hits another bomb sets it off too: +50 per chained Carbon, +100 per chained Diamond. Stack bombs near each other on purpose.</span>
                     </li>
                 </ul>
                 <table class="ss-table">
                     <thead><tr><th>Move</th><th>Points</th></tr></thead>
                     <tbody>
                         <tr><td>Match 3</td><td>30</td></tr>
                         <tr><td>Match 4 (makes <img src="icons/carbon.png" alt="Carbon"> Carbon bomb)</td><td>40</td></tr>
                         <tr><td>Match 5 (makes <img src="icons/diamond.png" alt="Diamond"> Diamond bomb)</td><td>50</td></tr>
                         <tr><td>Manually detonate <img src="icons/carbon.png" alt="Carbon"> Carbon (13-tile cross)</td><td>~130 + 25 = <strong>155</strong></td></tr>
                         <tr><td>Manually detonate <img src="icons/diamond.png" alt="Diamond"> Diamond (full board)</td><td>~630 + 50 = <strong>680</strong></td></tr>
                         <tr><td>Chain: <img src="icons/carbon.png" alt="Carbon"> Carbon into <img src="icons/diamond.png" alt="Diamond"> Diamond</td><td><strong>+100 bonus</strong> on top</td></tr>
                     </tbody>
                 </table>
             </div>
         </section>

         <section class="ss-section">
             <div class="ss-wrap">
                 <h2>Think Like a High Scorer</h2>
                 <ol class="ss-tips">
                     <li><strong>Always take a 5-match over a 3-match.</strong> A Diamond bomb is worth hundreds of points; a quick clear is worth 30.</li>
                     <li><strong>Take a 4-match over a 3-match.</strong> A Carbon bomb beats a plain clear every time.</li>
                     <li><strong>Position your drag endpoint.</strong> The bomb spawns where you release, so place it where it can chain into another bomb later.</li>
                     <li><strong>Detonate before match 25.</strong> When your last match fires, leftover bombs auto-detonate at discount rates - you keep the tile points but lose every +25/+50 bonus.</li>
                 </ol>
             </div>
         </section>

         <section class="ss-section ss-quickstart">
             <div class="ss-wrap">
                 <h2>How to Start Playing in Under 10 Seconds</h2>
                 <ol>
                     <li>Hit Play - the board loads instantly in your browser on phone, tablet, or desktop.</li>
                     <li>Drag a tile to swap it with a neighbor and line up 3 or more matching icons.</li>
                     <li>Chase 4- and 5-matches to forge bombs, then detonate them for the big points.</li>
                 </ol>
                 <p class="ss-center"><button class="ss-cta" type="button" onclick="ssPlay()">Start Playing Now</button></p>
             </div>
         </section>

         <section class="ss-section">
             <div class="ss-wrap ss-faq">
                 <h2>Skull Swap FAQ</h2>
                 <details>
                     <summary>Is Skull Swap really free to play?</summary>
                     <p>Yes - completely free. There's nothing to buy, no pay-to-win mechanics, and no signup required. Open the page and play.</p>
                 </details>
                 <details>
                     <summary>Do I need to download anything to play?</summary>
                     <p>No. Skull Swap runs entirely in your web browser - no app store, no installer, no plugins. If you can read this page, you can play the game.</p>
                 </details>
                 <details>
                     <summary>Does Skull Swap work on mobile?</summary>
                     <p>Yes. The board is touch-friendly - drag a tile with your finger to swap it. It plays just as well on phones and tablets as on desktop.</p>
                 </details>
                 <details>
                     <summary>How do I get a high score in Skull Swap?</summary>
                     <p>Prioritize 4- and 5-matches to forge Carbon and Diamond bombs, place bombs near each other so detonations chain for +50/+100 bonuses, and always detonate manually before your 25th match - the End Game Trap auto-fires leftover bombs without the bonuses.</p>
                 </details>
                 <details>
                     <summary>Do I need an account to play?</summary>
                     <p>No account is needed for casual play. If you want your scores saved and a spot on the weekly leaderboard, you can optionally log in through Skulliance with Discord - but it's never required to enjoy the game.</p>
                 </details>
                 <details>
                     <summary>How long does a game of Skull Swap take?</summary>
                     <p>A typical run is a few minutes - every game is exactly 25 matches, so it's perfect for a quick break. Chasing a personal best is what keeps it going.</p>
                 </details>
             </div>
         </section>

         <section class="ss-section">
             <div class="ss-wrap">
                 <div class="ss-final">
                     <h2>Ready to Swap?</h2>
                     <p>The board is waiting at the top of this page. No download. No signup. Just play.</p>
                     <button class="ss-cta" type="button" onclick="ssPlay()">Play Skull Swap Free</button>
                     <?php if ($is_logged_in): ?>
                     <p class="ss-login-note">You're logged in - every run you finish is saved and counts toward the weekly leaderboard.</p>
                     <?php else: ?>
                     <p class="ss-login-note">Want your scores saved and a shot at the weekly leaderboard? <a href="index.php">Log in through Skulliance</a> with Discord and every run you finish counts.</p>
                     <?php endif; ?>
                 </div>
             </div>
         </section>

         <section class="ss-section">
             <div class="ss-wrap">
                 <h2>More Free Games from Skulliance</h2>
                 <div class="ss-cross">
                     <a class="ss-cross-shot" href="match3rpg.php" aria-label="Play Monstrocity, a free match 3 RPG">
                         <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png"
                              alt="Monstrocity Match 3 RPG logo"
                              loading="lazy" decoding="async">
                     </a>
                     <div>
                         <h3>Monstrocity - Match 3 RPG</h3>
                         <p>Want more than a score chase? Monstrocity wraps real RPG combat around the match 3 board - character stats, special attacks, power-ups, boss battles, and 35+ visual themes from featured artists. Free in your browser, no download.</p>
                         <a class="ss-cta" href="match3rpg.php">Play Monstrocity Free</a>
                     </div>
                 </div>
             </div>
         </section>

         <footer class="ss-footer">
             <p>&copy; Skulliance &middot; Skull Swap is a free browser-based match 3 puzzle game. <a href="https://www.skulliance.io/">Visit Skulliance</a></p>
         </footer>
     </div>
     <script>
         // Hide the landing and reveal the (already initialized) game.
         function ssPlay() {
             document.getElementById('ss-landing').style.display = 'none';
             document.getElementById('game-container').style.display = 'flex';
             // The floating pill is landing-only; during gameplay the
             // GO BACK button in the row below the board takes over.
             var ssExit = document.getElementById('ss-exit');
             if (ssExit) ssExit.style.display = 'none';
             window.scrollTo(0, 0);
         }
     </script>

     <script>
 // Public players play without a server game session; token fetch and
 // score saves are skipped client-side (and rejected server-side anyway).
 const IS_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

 class Match3Game {
     constructor() {
         this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
         this.isMobile = (this.isTouchDevice && window.innerWidth <= 768) || window.innerWidth <= 768;

         this.width = this.isMobile ? 6 : 8;
         this.height = this.isMobile ? 10 : 8;

         this.board = [];
         this.selectedTile = null;
         this.score = 0;
         this.matchCount = 0;
         this.matchLimit = 25;
         this.gameOver = false;
         this.isDetonating = false;
         this.isGrandFinale = false;
         this.gameToken = null;
         this.fetchGameToken();

         this.allIcons = <?php echo json_encode($swap_icons); ?>; // query hoisted to top of file (shared with landing marquee)
         this.specialIcons = {
             carbon: 'https://www.skulliance.io/staking/icons/carbon.png',
             diamond: 'https://www.skulliance.io/staking/icons/diamond.png'
         };
         this.colorPalette = [
             '#800000', '#008080', '#408000', '#4B0082', '#666633', '#804000', '#004080'
         ];
         this.icons = this.selectRandomIcons(7);
         this.iconColorMap = this.createIconColorMap();
         this.specialTypes = { bomb4: 'carbon', bomb5: 'diamond' };
         this.isDragging = false;
         this.matchCheckCount = 0;
         this.targetTile = null;
         this.dragDirection = null;
         this.offsetX = 0;
         this.offsetY = 0;

         this.bonusScores = {
             carbonDetonation: 50,
             diamondDetonation: 100,
             carbonCleared: 25,
             diamondCleared: 50
         };

         this.sounds = {
             match: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
             carbonBombAppear: new Audio('https://www.skulliance.io/staking/sounds/hyperspace_gem_land_2.ogg'),
             diamondBombAppear: new Audio('https://www.skulliance.io/staking/sounds/hyperspace_gem_land_1.ogg'),
             carbonExplode: new Audio('https://www.skulliance.io/staking/sounds/bomb_explode.ogg'),
             diamondExplode: new Audio('https://www.skulliance.io/staking/sounds/gem_shatters.ogg'),
             cascade: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
             badMove: new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),
             gameOver: new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),
             reset: new Audio('https://www.skulliance.io/staking/sounds/voice_welcomeback.ogg'),
			 highScore: new Audio('https://www.skulliance.io/staking/sounds/voice_challengecomplete.ogg'),
			 lowScore: new Audio('https://www.skulliance.io/staking/sounds/voice_good.ogg'),
			 newScore: new Audio('https://www.skulliance.io/staking/sounds/voice_excellent.ogg')
         };
         Object.values(this.sounds).forEach(sound => sound.preload = 'auto');

         const boardElement = document.getElementById('game-board');
         const maxWidth = Math.min(window.innerWidth * 0.9, window.innerHeight * 0.9 * (this.width / this.height));
         const maxHeight = Math.min(window.innerHeight * 0.9, window.innerWidth * 0.9 * (this.height / this.width));
         boardElement.style.width = `${maxWidth}px`;
         boardElement.style.height = `${maxHeight}px`;
         boardElement.style.gridTemplateColumns = `repeat(${this.width}, 1fr)`;

         const hudElement = document.getElementById('hud');
         hudElement.style.width = `${maxWidth}px`;

         this.tileSizeWithGap = (maxWidth - (0.5 * (this.width - 1))) / this.width;

         this.initBoard();
         this.renderBoard();
         this.addEventListeners();
         boardElement.style.pointerEvents = 'auto';

         this.tryAgainButton = document.getElementById('try-again');
         this.tryAgainButton.addEventListener('click', () => this.resetGame());

         // Leaderboard button only renders for logged-in players
         this.leaderboardButton = document.getElementById('leaderboard');
         this.leaderboardForm = document.getElementById('leaderboard-form');
         if (this.leaderboardButton && this.leaderboardForm) {
             this.leaderboardButton.addEventListener('click', () => this.leaderboardForm.submit());
         }
     }

     playSound(soundName) {
         const sound = this.sounds[soundName];
         if (sound) {
             sound.currentTime = 0;
             sound.play().catch(error => console.log('Sound error:', error));
         }
     }

     fetchGameToken() {
         if (!IS_LOGGED_IN) return;
         var self = this;
         var xhr = new XMLHttpRequest();
         xhr.open('GET', 'ajax/start-swap-game.php', true);
         xhr.onreadystatechange = function() {
             if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
                 try {
                     var data = JSON.parse(xhr.responseText);
                     if (data.success) self.gameToken = data.token;
                 } catch(e) {}
             }
         };
         xhr.send();
     }

     resetGame() {
         console.log('Resetting game...');
         this.gameToken = null;
         this.fetchGameToken();
         this.score = 0;
         this.matchCount = 0;
         this.gameOver = false;
         this.isGrandFinale = false;
         document.getElementById('score').textContent = `Score: ${this.score}`;
         document.getElementById('matches').textContent = `Matches: ${this.matchCount}/${this.matchLimit}`;
        
         const board = document.getElementById('game-board');
         const tiles = board.querySelectorAll('.tile');
         tiles.forEach(tile => tile.classList.remove('game-over'));
         board.style.pointerEvents = 'auto';
        
         const gameOverContainer = document.getElementById('game-over-container');
         gameOverContainer.style.display = 'none';
        
         this.playSound('reset');
         this.initBoard();
     }

     selectRandomIcons(count) {
         const shuffled = [...this.allIcons].sort(() => 0.5 - Math.random());
         return shuffled.slice(0, count);
     }

     createIconColorMap() {
         const map = {};
         this.icons.forEach((icon, index) => {
             map[icon] = this.colorPalette[index % this.colorPalette.length];
         });
         map[this.specialIcons.carbon] = '#333300';
         map[this.specialIcons.diamond] = '#000000';
         return map;
     }

     initBoard() {
         this.board = [];
         for (let y = 0; y < this.height; y++) {
             this.board[y] = [];
             for (let x = 0; x < this.width; x++) {
                 let tile;
                 do {
                     tile = this.createRandomTile();
                 } while (
                     (x >= 2 && this.board[y][x-1].icon === tile.icon && this.board[y][x-2].icon === tile.icon) ||
                     (y >= 2 && this.board[y-1][x].icon === tile.icon && this.board[y-2][x].icon === tile.icon)
                 );
                 this.board[y][x] = tile;
             }
         }
         this.renderBoard();
         this.resolveMatches();
     }

     createRandomTile() {
         return {
             icon: this.icons[Math.floor(Math.random() * this.icons.length)],
             special: null,
             element: null
         };
     }

     renderBoard() {
         const boardElement = document.getElementById('game-board');
         boardElement.innerHTML = '';

         for (let y = 0; y < this.height; y++) {
             for (let x = 0; x < this.width; x++) {
                 const tile = this.board[y][x];
                 const tileElement = document.createElement('div');
                 tileElement.className = 'tile';
                 if (this.gameOver) tileElement.classList.add('game-over');
                
                 if (tile.special) {
                     const img = document.createElement('img');
                     img.src = this.specialIcons[tile.special];
                     tileElement.appendChild(img);
                     tileElement.style.backgroundColor = this.iconColorMap[this.specialIcons[tile.special]];
                 } else if (tile.icon) {
                     const img = document.createElement('img');
                     img.src = tile.icon;
                     tileElement.appendChild(img);
                     tileElement.style.backgroundColor = this.iconColorMap[tile.icon];
                 }

                 tileElement.dataset.x = x;
                 tileElement.dataset.y = y;
                 boardElement.appendChild(tileElement);
                 tile.element = tileElement;

                 if (!this.isDragging || (this.selectedTile && (this.selectedTile.x !== x || this.selectedTile.y !== y))) {
                     tileElement.style.transform = 'translate(0, 0)';
                 }
             }
         }
        
         document.getElementById('game-over-container').style.display = this.gameOver ? 'block' : 'none';
     }

     addEventListeners() {
         const board = document.getElementById('game-board');
        
         if (this.isTouchDevice && window.innerWidth <= 768) {
             board.addEventListener('touchstart', (e) => this.handleTouchStart(e));
             board.addEventListener('touchmove', (e) => this.handleTouchMove(e));
             board.addEventListener('touchend', (e) => this.handleTouchEnd(e));
         } else {
             board.addEventListener('mousedown', (e) => this.handleMouseDown(e));
             board.addEventListener('mousemove', (e) => this.handleMouseMove(e));
             board.addEventListener('mouseup', (e) => this.handleMouseUp(e));
         }
     }

     handleMouseDown(e) {
         if (this.gameOver || this.isGrandFinale) return;
         e.preventDefault();
         const tile = this.getTileFromEvent(e);
         if (!tile || !tile.element) return;

         this.isDragging = true;
         this.selectedTile = { x: tile.x, y: tile.y };
         tile.element.classList.add('selected');

         const boardRect = document.getElementById('game-board').getBoundingClientRect();
         this.offsetX = e.clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
         this.offsetY = e.clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
     }

     handleMouseMove(e) {
         if (!this.isDragging || !this.selectedTile || this.gameOver || this.isGrandFinale) return;
         e.preventDefault();

         const boardRect = document.getElementById('game-board').getBoundingClientRect();
         const mouseX = e.clientX - boardRect.left - this.offsetX;
         const mouseY = e.clientY - boardRect.top - this.offsetY;

         const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;
         selectedTileElement.style.transition = '';

         if (!this.dragDirection) {
             const dx = Math.abs(mouseX - (this.selectedTile.x * this.tileSizeWithGap));
             const dy = Math.abs(mouseY - (this.selectedTile.y * this.tileSizeWithGap));
             if (dx > dy && dx > 5) this.dragDirection = 'row';
             else if (dy > dx && dy > 5) this.dragDirection = 'column';
         }

         if (!this.dragDirection) return;

         if (this.dragDirection === 'row') {
             const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, mouseX));
             selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
             this.targetTile = {
                 x: Math.round(constrainedX / this.tileSizeWithGap),
                 y: this.selectedTile.y
             };
         } else if (this.dragDirection === 'column') {
             const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, mouseY));
             selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
             this.targetTile = {
                 x: this.selectedTile.x,
                 y: Math.round(constrainedY / this.tileSizeWithGap)
             };
         }
     }

     handleMouseUp(e) {
         if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver || this.isGrandFinale) {
             if (this.selectedTile) {
                 const tile = this.board[this.selectedTile.y][this.selectedTile.x];
                 if (tile.element) tile.element.classList.remove('selected');
             }
             this.isDragging = false;
             this.selectedTile = null;
             this.targetTile = null;
             this.dragDirection = null;
             this.renderBoard();
             this.playSound('badMove');
             return;
         }

         const tile = this.board[this.selectedTile.y][this.selectedTile.x];
         if (tile.element) tile.element.classList.remove('selected');

         this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

         this.isDragging = false;
         this.selectedTile = null;
         this.targetTile = null;
         this.dragDirection = null;
     }

     handleTouchStart(e) {
         if (this.gameOver || this.isGrandFinale) return;
         e.preventDefault();
         const tile = this.getTileFromEvent(e.touches[0]);
         if (!tile || !tile.element) return;

         this.isDragging = true;
         this.selectedTile = { x: tile.x, y: tile.y };
         tile.element.classList.add('selected');

         const boardRect = document.getElementById('game-board').getBoundingClientRect();
         this.offsetX = e.touches[0].clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
         this.offsetY = e.touches[0].clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
     }

     handleTouchMove(e) {
         if (!this.isDragging || !this.selectedTile || this.gameOver || this.isGrandFinale) return;
         e.preventDefault();

         const boardRect = document.getElementById('game-board').getBoundingClientRect();
         const touchX = e.touches[0].clientX - boardRect.left - this.offsetX;
         const touchY = e.touches[0].clientY - boardRect.top - this.offsetY;

         const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;

         requestAnimationFrame(() => {
             if (!this.dragDirection) {
                 const dx = Math.abs(touchX - (this.selectedTile.x * this.tileSizeWithGap));
                 const dy = Math.abs(touchY - (this.selectedTile.y * this.tileSizeWithGap));
                 if (dx > dy && dx > 7) this.dragDirection = 'row';
                 else if (dy > dx && dy > 7) this.dragDirection = 'column';
             }

             selectedTileElement.style.transition = '';

             if (this.dragDirection === 'row') {
                 const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, touchX));
                 selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
                 this.targetTile = {
                     x: Math.round(constrainedX / this.tileSizeWithGap),
                     y: this.selectedTile.y
                 };
             } else if (this.dragDirection === 'column') {
                 const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, touchY));
                 selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
                 this.targetTile = {
                     x: this.selectedTile.x,
                     y: Math.round(constrainedY / this.tileSizeWithGap)
                 };
             }
         });
     }

     handleTouchEnd(e) {
         if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver || this.isGrandFinale) {
             if (this.selectedTile) {
                 const tile = this.board[this.selectedTile.y][this.selectedTile.x];
                 if (tile.element) tile.element.classList.remove('selected');
             }
             this.isDragging = false;
             this.selectedTile = null;
             this.targetTile = null;
             this.dragDirection = null;
             this.renderBoard();
             this.playSound('badMove');
             return;
         }

         const tile = this.board[this.selectedTile.y][this.selectedTile.x];
         if (tile.element) tile.element.classList.remove('selected');

         this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

         this.isDragging = false;
         this.selectedTile = null;
         this.targetTile = null;
         this.dragDirection = null;
     }

     getTileFromEvent(e) {
         const boardRect = document.getElementById('game-board').getBoundingClientRect();
         const x = Math.floor((e.clientX - boardRect.left) / this.tileSizeWithGap);
         const y = Math.floor((e.clientY - boardRect.top) / this.tileSizeWithGap);
         if (x >= 0 && x < this.width && y >= 0 && y < this.height) {
             return { x, y, element: this.board[y][x].element };
         }
         return null;
     }

     slideTiles(startX, startY, endX, endY) {
         const tileSizeWithGap = this.tileSizeWithGap;
         let direction;

         const originalTiles = [];
         const tileElements = [];
         if (startY === endY) {
             direction = startX < endX ? 1 : -1;
             const minX = Math.min(startX, endX);
             const maxX = Math.max(startX, endX);
             for (let x = minX; x <= maxX; x++) {
                 originalTiles.push({ ...this.board[startY][x] });
                 tileElements.push(this.board[startY][x].element);
             }
         } else if (startX === endX) {
             direction = startY < endY ? 1 : -1;
             const minY = Math.min(startY, endY);
             const maxY = Math.max(startY, endY);
             for (let y = minY; y <= maxY; y++) {
                 originalTiles.push({ ...this.board[y][startX] });
                 tileElements.push(this.board[y][startX].element);
             }
         }

         const selectedElement = this.board[startY][startX].element;
         const dx = (endX - startX) * tileSizeWithGap;
         const dy = (endY - startY) * tileSizeWithGap;

         selectedElement.style.transition = 'transform 0.2s ease';
         selectedElement.style.transform = `translate(${dx}px, ${dy}px)`;

         let i = 0;
         if (startY === endY) {
             for (let x = Math.min(startX, endX); x <= Math.max(startX, endX); x++) {
                 if (x === startX) continue;
                 const offsetX = direction * -tileSizeWithGap * (x - startX) / Math.abs(endX - startX);
                 tileElements[i].style.transition = 'transform 0.2s ease';
                 tileElements[i].style.transform = `translate(${offsetX}px, 0)`;
                 i++;
             }
         } else {
             for (let y = Math.min(startY, endY); y <= Math.max(startY, endY); y++) {
                 if (y === startY) continue;
                 const offsetY = direction * -tileSizeWithGap * (y - startY) / Math.abs(endY - startY);
                 tileElements[i].style.transition = 'transform 0.2s ease';
                 tileElements[i].style.transform = `translate(0, ${offsetY}px)`;
                 i++;
             }
         }

         setTimeout(() => {
             if (startY === endY) {
                 const row = this.board[startY];
                 const tempRow = [...row];
                 if (startX < endX) {
                     for (let x = startX; x < endX; x++) row[x] = tempRow[x + 1];
                 } else {
                     for (let x = startX; x > endX; x--) row[x] = tempRow[x - 1];
                 }
                 row[endX] = tempRow[startX];
             } else {
                 const tempCol = [];
                 for (let y = 0; y < this.height; y++) tempCol[y] = { ...this.board[y][startX] };
                 if (startY < endY) {
                     for (let y = startY; y < endY; y++) this.board[y][startX] = tempCol[y + 1];
                 } else {
                     for (let y = startY; y > endY; y--) this.board[y][startX] = tempCol[y - 1];
                 }
                 this.board[endY][endX] = tempCol[startY];
             }

             this.renderBoard();
             const hasMatches = this.resolveMatches(endX, endY);

             if (hasMatches) {
                 this.matchCount++;
                 document.getElementById('matches').textContent = `Matches: ${this.matchCount}/${this.matchLimit}`;
                
                 if (this.matchCount >= this.matchLimit) this.endGame();
             } else {
                 console.log(`No match, reverting tiles from (${startX}, ${startY}) to (${endX}, ${endY})`);
                 selectedElement.style.transition = 'transform 0.2s ease';
                 selectedElement.style.transform = 'translate(0, 0)';
                 tileElements.forEach(element => {
                     element.style.transition = 'transform 0.2s ease';
                     element.style.transform = 'translate(0, 0)';
                 });

                 setTimeout(() => {
                     if (startY === endY) {
                         const minX = Math.min(startX, endX);
                         for (let i = 0; i < originalTiles.length; i++) {
                             this.board[startY][minX + i] = { ...originalTiles[i], element: tileElements[i] };
                         }
                     } else {
                         const minY = Math.min(startY, endY);
                         for (let i = 0; i < originalTiles.length; i++) {
                             this.board[minY + i][startX] = { ...originalTiles[i], element: tileElements[i] };
                         }
                     }
                     this.renderBoard();
                 }, 200);
             }
         }, 200);
     }

     async endGame() {
         console.log('Starting endgame sequence...');
         this.isGrandFinale = true;
         const board = document.getElementById('game-board');
         board.style.pointerEvents = 'none';
         const gameOverContainer = document.getElementById('game-over-container');

         let bombPositions = this.getAllBombPositions();
         console.log(`Found ${bombPositions.length} bombs for grand finale`);

         this.isDetonating = true;
         while (bombPositions.length > 0) {
             const bomb = bombPositions.shift();
             if (bomb.type === 'carbon') {
                 await this.clearRowAndColumn(bomb.x, bomb.y, true);
             } else if (bomb.type === 'diamond') {
                 await this.clearBoard(bomb.x, bomb.y, true);
             }
             this.showerTiles();
             this.cascadeTilesWithoutRender();
             this.renderBoard();
             await new Promise(resolve => setTimeout(resolve, 250));

             const newBombs = this.getAllBombPositions();
             bombPositions = [...bombPositions, ...newBombs.filter(nb => 
                 !bombPositions.some(b => b.x === nb.x && b.y === nb.y))];
             console.log(`Detonated at (${bomb.x}, ${bomb.y}), ${bombPositions.length} bombs remain`);
         }
         this.isDetonating = false;

         let moved = true;
         let iterations = 0;
         const maxIterations = 20;
         while (moved && iterations < maxIterations) {
             moved = this.cascadeTilesWithoutRender();
             this.showerTiles();
             this.renderBoard();
             await new Promise(resolve => setTimeout(resolve, 300));
             iterations++;

             let hasMatches = false;
             for (let y = 0; y < this.height && !hasMatches; y++) {
                 for (let x = 0; x < this.width && !hasMatches; x++) {
                     const matchResult = this.checkMatches(x, y);
                     if (matchResult.hasMatches && matchResult.matches.size >= 3) {
                         hasMatches = true;
                         console.log(`Grand finale match found at (${x}, ${y}) with size ${matchResult.matches.size}`);
                         await this.handleMatches(matchResult.matches, null, matchResult.bombX, matchResult.bombY);
                     }
                 }
             }
             if (!hasMatches) break;
         }

         console.log('Board is calm, showing game over...');
         const tiles = board.querySelectorAll('.tile');
         tiles.forEach(tile => tile.classList.add('game-over'));
         gameOverContainer.style.display = 'block';
         this.gameOver = true;
         this.playSound('gameOver');
         console.log('Game Over - Grand finale completed!');
         this.saveSwapScore(this.score);
     }

	 saveSwapScore(score) {
	     if (!IS_LOGGED_IN) return;
	     var xhttp = new XMLHttpRequest();
	     xhttp.open('GET', 'ajax/save-swap-score.php?score=' + score + '&token=' + encodeURIComponent(this.gameToken || ''), true);
	     xhttp.send();
	     xhttp.onreadystatechange = function() {
	         if (xhttp.readyState == XMLHttpRequest.DONE) {
	             if (xhttp.status == 200) {
	                 var data = xhttp.responseText;
	                 setTimeout(() => { // Delay the additional sounds
	                     if (data == "new") {
	                         this.playSound('newScore');
	                     } else if (data == "high") {
	                         this.playSound('highScore');
	                     } else if (data == "low") {
	                         this.playSound('lowScore');
	                     }
	                 }, 2000); // 2000ms (2 seconds) delay; adjust as needed
	                 console.log(data);
	             }
	         }
	     }.bind(this); // Bind the Match3Game instance to the function
	 }

     getAllBombPositions() {
         const bombPositions = [];
         for (let y = 0; y < this.height; y++) {
             for (let x = 0; x < this.width; x++) {
                 const tile = this.board[y][x];
                 if (tile.special) {
                     bombPositions.push({ x, y, type: tile.special });
                 }
             }
         }
         return bombPositions;
     }

     async clearRowAndColumn(x, y, isEndGame = false) {
         console.log(`Clearing row ${y} and column ${x} (Carbon Bomb)`);
         this.isDetonating = true;
         this.playSound('carbonExplode');
         const affectedTiles = new Set();
         const newBombs = [];

         if (this.board[y][x].element) {
             this.board[y][x].element.classList.add('carbon-clear');
         }

         for (let i = 0; i < this.width; i++) {
             if (i !== x && this.board[y][i].element) {
                 affectedTiles.add(`${i},${y}`);
             }
         }
         for (let j = 0; j < this.height; j++) {
             if (j !== y && this.board[j][x].element) {
                 affectedTiles.add(`${x},${j}`);
             }
         }

         affectedTiles.forEach(pos => {
             const [tx, ty] = pos.split(',').map(Number);
             if (this.board[ty][tx].element) {
                 this.board[ty][tx].element.classList.add('carbon-clear');
             }
         });

         await new Promise(resolve => setTimeout(resolve, isEndGame ? 250 : 800));

         affectedTiles.forEach(pos => {
             const [tx, ty] = pos.split(',').map(Number);
             if (this.board[ty][tx].special && !isEndGame) {
                 newBombs.push({ x: tx, y: ty, type: this.board[ty][tx].special });
             }
         });

         affectedTiles.forEach(pos => {
             const [tx, ty] = pos.split(',').map(Number);
             this.board[ty][tx].icon = null;
             this.board[ty][tx].special = null;
             this.board[ty][tx].element = null;
         });
         this.board[y][x].icon = null;
         this.board[y][x].special = null;
         this.board[y][x].element = null;

         this.score += (affectedTiles.size + 1) * 10;
         if (!isEndGame) this.score += this.bonusScores.carbonCleared;
         document.getElementById('score').textContent = `Score: ${this.score}`;
         console.log(`Carbon bomb cleared ${affectedTiles.size + 1} tiles, added ${(affectedTiles.size + 1) * 10} points`);

         if (!isEndGame) {
             this.showerTiles();
             this.cascadeTilesWithoutRender();
             this.renderBoard();
             for (const bomb of newBombs) {
                 await this.handleBombDetonation(bomb.x, bomb.y, bomb.type);
             }
         }
         this.isDetonating = false;
         return isEndGame ? [] : newBombs;
     }

     async clearBoard(x = null, y = null, isEndGame = false) {
         console.log(`Clearing entire board (Diamond Bomb)${x !== null && y !== null ? ` at (${x}, ${y})` : ''}`);
         this.isDetonating = true;
         this.playSound('diamondExplode');
         const affectedTiles = new Set();
         const newBombs = [];

         if (x !== null && y !== null && this.board[y][x].element) {
             this.board[y][x].element.classList.add('diamond-clear');
         }

         for (let ty = 0; ty < this.height; ty++) {
             for (let tx = 0; tx < this.width; tx++) {
                 if ((tx !== x || ty !== y) && this.board[ty][tx].element) {
                     affectedTiles.add(`${tx},${ty}`);
                     this.board[ty][tx].element.classList.add('diamond-clear');
                 }
             }
         }

         await new Promise(resolve => setTimeout(resolve, isEndGame ? 250 : 1000));

         affectedTiles.forEach(pos => {
             const [tx, ty] = pos.split(',').map(Number);
             if (this.board[ty][tx].special && !isEndGame) {
                 newBombs.push({ x: tx, y: ty, type: this.board[ty][tx].special });
             }
         });

         affectedTiles.forEach(pos => {
             const [tx, ty] = pos.split(',').map(Number);
             this.board[ty][tx].icon = null;
             this.board[ty][tx].special = null;
             this.board[ty][tx].element = null;
         });
         if (x !== null && y !== null) {
             this.board[y][x].icon = null;
             this.board[y][x].special = null;
             this.board[y][x].element = null;
         }

         this.score += affectedTiles.size * 10 + (x !== null ? 10 : 0);
         if (!isEndGame) this.score += this.bonusScores.diamondCleared;
         document.getElementById('score').textContent = `Score: ${this.score}`;
         console.log(`Diamond bomb cleared ${affectedTiles.size + (x !== null ? 1 : 0)} tiles, added ${(affectedTiles.size + (x !== null ? 1 : 0)) * 10} points`);

         if (!isEndGame) {
             this.showerTiles();
             this.cascadeTilesWithoutRender();
             this.renderBoard();
             for (const bomb of newBombs) {
                 await this.handleBombDetonation(bomb.x, bomb.y, bomb.type);
             }
         }
         this.isDetonating = false;
         return isEndGame ? [] : newBombs;
     }

     async handleBombDetonation(x, y, bombType) {
         console.log(`Detonating ${bombType} bomb at (${x}, ${y}) triggered by another bomb`);
         this.isDetonating = true;
         if (bombType === 'carbon') {
             this.score += this.bonusScores.carbonDetonation;
             console.log(`Carbon bomb chain-detonated at (${x}, ${y}), +${this.bonusScores.carbonDetonation} bonus`);
             await this.clearRowAndColumn(x, y);
         } else if (bombType === 'diamond') {
             this.score += this.bonusScores.diamondDetonation;
             console.log(`Diamond bomb chain-detonated at (${x}, ${y}), +${this.bonusScores.diamondDetonation} bonus`);
             await this.clearBoard(x, y);
         }
         this.showerTiles();
         this.cascadeTilesWithoutRender();
         this.renderBoard();
         await new Promise(resolve => setTimeout(resolve, 200));
         document.getElementById('score').textContent = `Score: ${this.score}`;
         this.isDetonating = false;
     }

     showerTiles() {
         console.log('Showering tiles...');
         for (let x = 0; x < this.width; x++) {
             let topEmpty = -1;
             for (let y = 0; y < this.height; y++) {
                 if (!this.board[y][x].icon && !this.board[y][x].special) {
                     topEmpty = y;
                     break;
                 }
             }
             if (topEmpty >= 0) {
                 this.board[topEmpty][x] = this.createRandomTile();
             }
         }
     }

     cascadeTilesWithoutRender() {
         let moved = false;
         for (let x = 0; x < this.width; x++) {
             let emptySpaces = 0;
             for (let y = this.height - 1; y >= 0; y--) {
                 if (!this.board[y][x].icon && !this.board[y][x].special) {
                     emptySpaces++;
                 } else if (emptySpaces > 0) {
                     this.board[y + emptySpaces][x] = this.board[y][x];
                     this.board[y][x] = { icon: null, special: null, element: null };
                     moved = true;
                 }
             }
             for (let i = 0; i < emptySpaces; i++) {
                 this.board[i][x] = this.createRandomTile();
                 moved = true;
             }
         }
         return moved;
     }

     cascadeTiles() {
         console.log('Cascading tiles...');
         const moved = this.cascadeTilesWithoutRender();
         const fallTime = this.isDetonating ? 100 : 300;
         const fallClass = this.isDetonating ? 'falling-fast' : 'falling';

         for (let x = 0; x < this.width; x++) {
             for (let y = 0; y < this.height; y++) {
                 const tile = this.board[y][x];
                 if (tile.element && tile.element.style.transform === 'translate(0px, 0px)') {
                     const emptyBelow = this.countEmptyBelow(x, y);
                     if (emptyBelow > 0) {
                         tile.element.classList.add(fallClass);
                         tile.element.style.transform = `translate(0, ${emptyBelow * this.tileSizeWithGap}px)`;
                     }
                 }
             }
         }

         this.renderBoard();
         this.playSound('cascade');

         if (moved || this.matchCheckCount < 2) {
             this.matchCheckCount++;
             setTimeout(() => {
                 const hasMatches = this.resolveMatches();
                 if (!hasMatches) this.matchCheckCount = 0;
                 const tiles = document.querySelectorAll(`.${fallClass}`);
                 tiles.forEach(tile => {
                     tile.classList.remove(fallClass);
                     tile.style.transform = 'translate(0, 0)';
                 });
                 if (this.isDetonating) {
                     this.showerTiles();
                     this.cascadeTilesWithoutRender();
                     this.renderBoard();
                 }
             }, fallTime);
         } else {
             this.matchCheckCount = 0;
         }
     }

     countEmptyBelow(x, y) {
         let count = 0;
         for (let i = y + 1; i < this.height; i++) {
             if (!this.board[i][x].icon && !this.board[i][x].special) {
                 count++;
             } else {
                 break;
             }
         }
         return count;
     }

     resolveMatches(selectedX = null, selectedY = null) {
         if (this.isGrandFinale) return false;
         const matchResult = this.checkMatches(selectedX, selectedY);
         if (matchResult.hasMatches) {
             const { matches, bombType, bombX, bombY } = matchResult;
             if (matches.size >= 3) {
                 // Scan all matched tiles for an existing bomb — prefer diamond over carbon
                 let bombTile = null, bombTileX, bombTileY;
                 for (const pos of matches) {
                     const [px, py] = pos.split(',').map(Number);
                     const tile = this.board[py][px];
                     if (tile && tile.special) {
                         if (!bombTile || tile.special === 'diamond') {
                             bombTile = tile; bombTileX = px; bombTileY = py;
                         }
                     }
                 }
                 if (bombTile) {
                     this.handleBombMatches(matches, bombTile.special, bombTileX, bombTileY);
                 } else {
                     this.handleMatches(matches, bombType, bombX, bombY);
                 }
             }
             return true;
         }
         return false;
     }

     checkMatches(selectedX = null, selectedY = null) {
         let hasMatches = false;
         const allMatches = new Set();
         let bombType = null;
         let bombX, bombY;

         for (let y = 0; y < this.height; y++) {
             let matchStart = 0;
             let currentIcon = null;
             for (let x = 0; x <= this.width; x++) {
                 const tile = x < this.width ? this.board[y][x] : null;
                 const icon = tile ? (tile.special ? this.specialIcons[tile.special] : tile.icon) : null;

                 if (icon !== currentIcon || x === this.width) {
                     const matchLength = x - matchStart;
                     if (matchLength >= 3) {
                         for (let i = matchStart; i < x; i++) {
                             allMatches.add(`${i},${y}`);
                         }
                         console.log(`Horizontal match of ${matchLength} at row ${y}, start ${matchStart}, end ${x - 1}`);
                         hasMatches = true;
                     }
                     matchStart = x;
                     currentIcon = icon;
                 }
             }
         }

         for (let x = 0; x < this.width; x++) {
             let matchStart = 0;
             let currentIcon = null;
             for (let y = 0; y <= this.height; y++) {
                 const tile = y < this.height ? this.board[y][x] : null;
                 const icon = tile ? (tile.special ? this.specialIcons[tile.special] : tile.icon) : null;

                 if (icon !== currentIcon || y === this.height) {
                     const matchLength = y - matchStart;
                     if (matchLength >= 3) {
                         for (let i = matchStart; i < y; i++) {
                             allMatches.add(`${x},${i}`);
                         }
                         console.log(`Vertical match of ${matchLength} at col ${x}, start ${matchStart}, end ${y - 1}`);
                         hasMatches = true;
                     }
                     matchStart = y;
                     currentIcon = icon;
                 }
             }
         }

         if (hasMatches) {
             const matchSize = allMatches.size;
             console.log(`Total unique matched tiles: ${matchSize}, selected position: (${selectedX}, ${selectedY})`);
            
             if (selectedX !== null && selectedY !== null && allMatches.has(`${selectedX},${selectedY}`)) {
                 bombX = selectedX;
                 bombY = selectedY;
             } else {
                 const lastMatch = Array.from(allMatches).pop();
                 [bombX, bombY] = lastMatch.split(',').map(Number);
             }

             bombType = matchSize === 4 ? 'bomb4' : matchSize >= 5 ? 'bomb5' : null;
         }

         return { hasMatches, matches: allMatches, bombType, bombX, bombY };
     }

     handleMatches(matches, bombType, bombX, bombY) {
         this.playSound('match');
         matches.forEach(match => {
             const [x, y] = match.split(',').map(Number);
             if (this.board[y][x].element) {
                 this.board[y][x].element.classList.add('matched');
             }
         });

         setTimeout(() => {
             matches.forEach(match => {
                 const [x, y] = match.split(',').map(Number);
                 this.board[y][x].icon = null;
                 this.board[y][x].special = null;
                 this.board[y][x].element = null;
             });

             if (bombType && !this.isGrandFinale) {
                 this.createSpecialTile(bombX, bombY, bombType);
                 this.board[bombY][bombX].element.classList.add('bomb-creation');
                 this.playSound(bombType === 'bomb4' ? 'carbonBombAppear' : 'diamondBombAppear');
                 console.log(`Bomb placed at (${bombX}, ${bombY})`);
             }

             this.score += matches.size * 10;
             document.getElementById('score').textContent = `Score: ${this.score}`;
            
             this.cascadeTiles();
         }, 300);
     }

     async handleBombMatches(matches, bombType, bombX, bombY) {
         this.playSound('match');
         matches.forEach(match => {
             const [x, y] = match.split(',').map(Number);
             if (this.board[y][x].element) {
                 this.board[y][x].element.classList.add('matched');
             }
         });

         await new Promise(resolve => setTimeout(resolve, 300));

         matches.forEach(match => {
             const [x, y] = match.split(',').map(Number);
             this.board[y][x].icon = null;
             this.board[y][x].special = null;
             this.board[y][x].element = null;
         });

         this.score += matches.size * 10;

         if (bombType === 'carbon') {
             this.score += this.bonusScores.carbonDetonation;
             console.log(`Carbon bomb detonated at (${bombX}, ${bombY}), +${this.bonusScores.carbonDetonation} bonus`);
             await this.clearRowAndColumn(bombX, bombY);
         } else if (bombType === 'diamond') {
             this.score += this.bonusScores.diamondDetonation;
             console.log(`Diamond bomb detonated at (${bombX}, ${bombY}), +${this.bonusScores.diamondDetonation} bonus`);
             await this.clearBoard(bombX, bombY);
         }

         document.getElementById('score').textContent = `Score: ${this.score}`;
        
         this.cascadeTiles();
     }

     createSpecialTile(x, y, type) {
         this.board[y][x] = {
             icon: null,
             special: this.specialTypes[type],
             element: null
         };
         console.log(`Created ${this.specialTypes[type]} bomb at (${x}, ${y})`);
         this.renderBoard();
     }
 }

 const game = new Match3Game();
     </script>
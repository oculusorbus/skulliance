<?php
/**
 * Customises the stock Storefront homepage template to include the sidebar and the boutique_before_homepage_content hook.
 *
 * Template name: Homepage
 *
 * @package storefront
 */

// This template intentionally bypasses the WordPress chrome (get_header /
// get_footer stay disabled) and emits a fully self-contained document:
// all CSS is inline, all asset URLs are absolute, and there are zero
// dependencies on the staking platform's stylesheets. That keeps the
// repo copy and the WordPress copy byte-identical - migrating is a pure
// copy-paste - and nothing under WP or /staking can drift the design.
//
// Design language matches the match3rpg.php / skullswap.php landing pages:
// navy #07111d base, brand teal #00c8a0 -> #0596c4 gradient CTAs, glow
// hero, hairline-separated sections, card grids.
//
// Copy is proudly Cardano/NFT-forward: Skulliance's home is the Cardano
// blockchain and the homepage courts Cardano users directly. (The neutral,
// chain-free wording is reserved for the public game landing pages, which
// target general gaming search traffic.)

//get_header(); ?>
<!doctype html>
<html lang="en">
<head>
  <title>Skulliance - Premier Skull NFT Artists on Cardano | Staking, Games &amp; Merch</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Skulliance connects art collectors with the premier skull NFT artists on Cardano - NFT staking with nightly rewards, free browser games, missions, leaderboards, and exclusive merch.">
  <meta name="theme-color" content="#07111d">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
  <link rel="canonical" href="https://www.skulliance.io/">

  <!-- OpenGraph -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="https://www.skulliance.io/">
  <meta property="og:title" content="Skulliance - Premier Skull NFT Artists on Cardano">
  <meta property="og:description" content="Skull NFT staking with nightly rewards, free browser games, missions, leaderboards, and exclusive merch - built on Cardano.">
  <meta property="og:image" content="https://www.skulliance.io/staking/images/skulliancelogo.png">
  <meta property="og:image:alt" content="Skulliance logo">
  <meta property="og:locale" content="en_US">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Skulliance - Premier Skull NFT Artists on Cardano">
  <meta name="twitter:description" content="Skull NFT staking with nightly rewards, free browser games, and exclusive merch - built on Cardano.">
  <meta name="twitter:image" content="https://www.skulliance.io/staking/images/skulliancelogo.png">
  <meta name="twitter:image:alt" content="Skulliance logo">

  <!-- Schema.org: Organization -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Skulliance",
    "url": "https://www.skulliance.io/",
    "logo": "https://www.skulliance.io/staking/images/skulliancelogo.png",
    "description": "Skulliance connects art collectors with the premier skull NFT artists on the Cardano blockchain - NFT staking with nightly rewards, free browser games, and exclusive merch.",
    "sameAs": [
      "https://www.x.com/skulliance",
      "https://discord.gg/JqqBZBrph2"
    ]
  }
  </script>

  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
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
    h2 { font-size: clamp(1.5rem, 3vw, 2.2rem); }
    h3 { font-size: 1.12rem; color: #00c8a0; }
    p { margin: 0 0 1em; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

    /* ---------- Navigation ---------- */
    .hp-nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 9990;
      display: flex; align-items: center; gap: 22px;
      padding: 10px 18px;
      background: rgba(7, 17, 29, 0.88);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }
    .hp-nav .hp-mark { display: inline-flex; align-items: center; }
    .hp-nav .hp-mark img { height: 30px; width: auto; }
    .hp-nav a.hp-link {
      color: #c7d0d9; font-size: 0.92rem; font-weight: 600;
      letter-spacing: 0.02em;
    }
    .hp-nav a.hp-link:hover { color: #34e3bb; text-decoration: none; }
    .hp-nav .hp-spacer { flex: 1; }
    .hp-nav a.hp-social img { height: 18px; width: auto; }
    .hp-nav a.hp-social:hover img { filter: invert(64%) sepia(67%) saturate(437%) hue-rotate(112deg) brightness(95%) contrast(92%); }
    #hp-burger { display: none; background: none; border: none; padding: 4px; cursor: pointer; margin-left: auto; }
    #hp-burger img { height: 30px; width: auto; }
    @media (max-width: 860px) {
      #hp-burger { display: block; }
      .hp-nav { flex-wrap: wrap; }
      .hp-nav .hp-links {
        display: none;
        flex-direction: column; align-items: flex-start; gap: 14px;
        width: 100%; padding: 14px 4px 8px;
      }
      .hp-nav .hp-links.open { display: flex; }
      .hp-nav a.hp-link { font-size: 1.05rem; }
    }
    @media (min-width: 861px) {
      .hp-nav .hp-links { display: flex; align-items: center; gap: 22px; flex: 1; }
    }

    /* ---------- Hero ---------- */
    .hp-hero {
      text-align: center;
      padding: 120px 20px 56px;
      background:
        radial-gradient(circle at 50% 0%, rgba(0, 200, 160, 0.18), transparent 60%),
        url('https://www.skulliance.io/staking/images/skulliancebackground.png') center/cover no-repeat,
        linear-gradient(180deg, #07111d 0%, #0b1a2b 100%);
      background-blend-mode: normal, multiply, normal;
      background-color: #36393F;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .hp-hero .hp-logo {
      max-width: 420px; width: 86%;
      margin: 0 auto 8px;
      filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.7));
    }
    .hp-hero h1 {
      /* Visually quiet but real for SEO/screen readers - the logo carries
         the brand; the h1 carries the keywords. */
      font-size: clamp(1.05rem, 2.2vw, 1.4rem);
      font-weight: 600; color: #c7d0d9;
      max-width: 720px; margin: 0 auto 26px;
    }
    .hp-cta {
      display: inline-block;
      background: linear-gradient(135deg, #00c8a0, #0596c4);
      color: #07111d !important; font-weight: 800; font-size: 1.05rem;
      padding: 13px 30px; border-radius: 999px;
      text-decoration: none !important;
      box-shadow: 0 6px 20px rgba(0, 200, 160, 0.35);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .hp-cta:hover, .hp-cta:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(0, 200, 160, 0.5);
    }
    .hp-cta.hp-secondary {
      background: transparent; color: #00c8a0 !important;
      border: 1px solid rgba(0, 200, 160, 0.45); box-shadow: none;
    }
    .hp-cta.hp-secondary:hover { background: rgba(0, 200, 160, 0.08); }
    .hp-hero .hp-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .hp-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 24px; }
    .hp-badge {
      font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase;
      padding: 6px 12px; border-radius: 999px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #c7d0d9;
    }

    /* ---------- Sections ---------- */
    section { padding: 48px 0; }
    section + section { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .hp-center { text-align: center; }
    .hp-intro { max-width: 760px; margin: 0 auto 8px; color: #c7d0d9; }
    section h2 { text-align: center; }

    /* Card grids */
    .hp-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 18px; margin-top: 24px;
    }
    .hp-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 22px;
    }
    .hp-card h3 { margin-bottom: 8px; }
    .hp-card p { margin: 0 0 14px; color: #c7d0d9; font-size: 0.96rem; }

    /* Games */
    .hp-games { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 18px; margin-top: 24px; }
    .hp-game {
      display: flex; flex-direction: column; align-items: center; text-align: center;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 26px 22px;
    }
    .hp-game .hp-game-art { display: block; margin-bottom: 18px; }
    .hp-game .hp-game-art img {
      max-width: 260px; max-height: 200px; width: auto; margin: 0 auto;
      border-radius: 10px;
      filter: drop-shadow(0 12px 32px rgba(0, 0, 0, 0.55));
      transition: transform 0.15s ease;
    }
    .hp-game .hp-game-art:hover img { transform: translateY(-3px); }
    .hp-game p { color: #c7d0d9; font-size: 0.96rem; }
    .hp-game .hp-cta { margin-top: auto; }

    /* Artist / partner logo grid - fixed 3 per row, 2-up on phones */
    .hp-logos {
      list-style: none; padding: 0; margin: 24px 0 0;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }
    .hp-logos li {
      display: flex; align-items: center; justify-content: center;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px; padding: 12px;
      transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    }
    .hp-logos li:hover {
      transform: translateY(-2px);
      border-color: rgba(0, 200, 160, 0.45);
      background: rgba(0, 200, 160, 0.06);
    }
    .hp-logos a { display: block; width: 100%; }
    .hp-logos img { width: 100%; height: 150px; object-fit: contain; }
    @media (max-width: 640px) {
      .hp-logos { grid-template-columns: repeat(2, 1fr); }
      .hp-logos img { height: 120px; }
    }

    /* Partner flyer marquees - match3rpg's character-strip pattern:
       full-viewport-width rows (calc shift breaks out of the wrap; body
       overflow-x:hidden prevents a scrollbar), duplicated track sliding
       -50% for a seamless loop, opposite directions per row, pause on
       hover, edge fade masks, reduced-motion fallback. */
    .hp-strip {
      width: 100vw;
      margin-left: calc(50% - 50vw);
      margin-right: calc(50% - 50vw);
      overflow: hidden;
      padding: 20px 0;
      -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
              mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
    }
    .hp-strip + .hp-strip { padding-top: 0; }
    .hp-strip-track {
      display: flex;
      align-items: flex-start;
      gap: 26px;
      width: max-content;
      animation: hp-strip-scroll 60s linear infinite;
      will-change: transform;
    }
    .hp-strip-track.hp-reverse {
      animation-direction: reverse;
      animation-duration: 66s;
    }
    .hp-strip-track:hover { animation-play-state: paused; }
    @keyframes hp-strip-scroll {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }
    @media (prefers-reduced-motion: reduce) {
      .hp-strip-track { animation: none; }
    }
    .hp-strip-card {
      flex: 0 0 auto;
      text-align: center;
      text-decoration: none !important;
    }
    .hp-strip-card img {
      height: 170px; width: auto; max-width: 280px;
      object-fit: contain;
      border-radius: 10px;
      filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.65));
      transition: transform 0.15s ease;
    }
    .hp-strip-card:hover img { transform: translateY(-3px); }
    .hp-strip-name {
      display: block; margin-top: 8px;
      font-size: 0.85rem; font-weight: 600;
      color: #c7d0d9; letter-spacing: 0.02em;
    }
    .hp-strip-card:hover .hp-strip-name { color: #34e3bb; }
    @media (max-width: 640px) {
      .hp-strip-card img { height: 130px; max-width: 220px; }
    }

    /* Platform screenshots */
    .hp-shots { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
    .hp-shot-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 16px; text-align: center;
    }
    .hp-shot-card h3 { font-size: 1rem; margin-bottom: 10px; }
    .hp-shot-card img {
      border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.12);
      transition: transform 0.15s ease;
    }
    .hp-shot-card a:hover img { transform: translateY(-2px); }

    /* Membership tiers */
    .hp-tiers { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-top: 24px; }
    .hp-tier {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 24px;
    }
    .hp-tier .hp-tier-rank {
      font-size: 0.74rem; letter-spacing: 0.1em; text-transform: uppercase;
      color: #8a96a3; margin-bottom: 4px;
    }
    .hp-tier h3 { margin-bottom: 10px; }
    .hp-tier p { margin: 0; color: #c7d0d9; font-size: 0.94rem; }
    .hp-tier.hp-tier-top { border-color: rgba(0, 200, 160, 0.35); background: rgba(0, 200, 160, 0.05); }
    .hp-member-art { max-width: 420px; margin: 28px auto 0; }
    .hp-member-art img { border-radius: 14px; }

    /* Team */
    .hp-team { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-top: 24px; }
    .hp-member {
      text-align: center;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px; padding: 20px;
    }
    .hp-member img {
      border-radius: 12px; margin: 0 auto 12px;
      transition: transform 0.15s ease;
    }
    .hp-member a:hover img { transform: translateY(-2px); }
    .hp-member .hp-role { font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; color: #8a96a3; margin: 0 0 2px; }
    .hp-member .hp-name { font-weight: 700; color: #e8eaed; margin: 0; }

    /* Final CTA */
    .hp-final {
      text-align: center; padding: 56px 20px;
      background: linear-gradient(135deg, rgba(0, 200, 160, 0.12), rgba(5, 150, 196, 0.08));
      border-radius: 16px;
    }
    .hp-final h2 { margin-top: 0; }
    .hp-final p { max-width: 640px; margin: 0 auto 24px; color: #c7d0d9; }
    .hp-final .hp-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

    /* Footer */
    footer {
      padding: 30px 20px; text-align: center;
      color: #8a96a3; font-size: 0.88rem;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }
    footer a { color: #8a96a3; }
    footer .hp-foot-links { margin-bottom: 10px; display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }

    @media (max-width: 480px) {
      .hp-hero { padding-top: 96px; }
      .hp-hero .hp-ctas .hp-cta { width: 100%; text-align: center; }
      .hp-final .hp-ctas .hp-cta { width: 100%; text-align: center; }
    }
  </style>
</head>
<body>

  <!-- Navigation -->
  <nav class="hp-nav" aria-label="Main">
    <a class="hp-mark" href="#top" aria-label="Skulliance home">
      <img src="https://www.skulliance.io/staking/images/skull.png" alt="Skulliance skull mark" width="30" height="30">
    </a>
    <button id="hp-burger" type="button" aria-label="Toggle menu" aria-expanded="false" onclick="hpToggleMenu()">
      <img id="hp-burger-icon" src="https://www.skulliance.io/staking/images/menu.png" alt="" width="30" height="30">
    </button>
    <div class="hp-links" id="hp-links">
      <a class="hp-link" href="#mission">Mission</a>
      <a class="hp-link" href="#games">Games</a>
      <a class="hp-link" href="#artists">Artists</a>
      <a class="hp-link" href="#partners">Partners</a>
      <a class="hp-link" href="#platform">Platform</a>
      <a class="hp-link" href="#team">Team</a>
      <a class="hp-link" href="https://www.skulliance.io/staking">Staking</a>
      <a class="hp-link" href="https://www.skulliance.io/shop">Merch</a>
      <a class="hp-link" href="https://skulliance.gitbook.io/skulliance" target="_blank" rel="noopener">Skull Paper</a>
      <span class="hp-spacer"></span>
      <a class="hp-social" href="https://discord.gg/JqqBZBrph2" aria-label="Skulliance Discord"><img src="https://www.skulliance.io/staking/images/discord.png" alt="Discord" width="18" height="18"></a>
      <a class="hp-social" href="https://www.x.com/skulliance" aria-label="Skulliance on X"><img src="https://www.skulliance.io/staking/images/x.png" alt="X" width="18" height="18"></a>
    </div>
  </nav>

  <!-- Hero -->
  <header class="hp-hero" id="top">
    <img class="hp-logo" src="https://www.skulliance.io/staking/images/skulliancelogo.png" alt="Skulliance logo" fetchpriority="high" decoding="async">
    <h1>The premier skull NFT collective on Cardano - artists, staking rewards, free browser games, and exclusive merch in one community.</h1>
    <div class="hp-ctas">
      <a class="hp-cta" href="#games">Play Free Games</a>
      <a class="hp-cta hp-secondary" href="https://www.skulliance.io/shop">Shop Merch</a>
      <a class="hp-cta hp-secondary" href="https://discord.gg/JqqBZBrph2">Join the Discord</a>
    </div>
    <div class="hp-badges" aria-label="Highlights">
      <span class="hp-badge">Built on Cardano</span>
      <span class="hp-badge">25+ Featured Artists</span>
      <span class="hp-badge">NFT Staking Rewards</span>
      <span class="hp-badge">Free Browser Games</span>
      <span class="hp-badge">Exclusive Merch</span>
    </div>
  </header>

  <main>

    <!-- Mission -->
    <section id="mission">
      <div class="wrap">
        <h2>Mission</h2>
        <p class="hp-intro hp-center">The mission of Skulliance is to connect skull art collectors with the premier skull NFT artists on the Cardano blockchain and elevate the collective art form and community within the space. Cardano is our home - led by Oculus Orbus, an avid skull NFT collector and developer, Skulliance gives artists a stage and collectors a reason to keep coming back every day.</p>
        <img src="https://www.skulliance.io/staking/images/skulliance-group.jpg" alt="Skulliance founding artists group artwork" loading="lazy" decoding="async" style="border-radius:14px; margin-top:18px;">
      </div>
    </section>

    <!-- Games -->
    <section id="games">
      <div class="wrap">
        <h2>Free Browser Games</h2>
        <p class="hp-intro hp-center">No download, no signup, no paywall - both games run in any browser on phone, tablet, or desktop. Collectors can log in to save scores, climb weekly leaderboards, and battle with characters from their own NFT collections.</p>
        <div class="hp-games">
          <div class="hp-game">
            <a class="hp-game-art" href="https://www.skulliance.io/staking/match3rpg.php" aria-label="Play Monstrocity, the free match 3 RPG">
              <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Match 3 RPG logo" loading="lazy" decoding="async">
            </a>
            <h3>Monstrocity - Match 3 RPG</h3>
            <p>Real RPG combat wrapped around a match 3 board - character stats, special attacks, power-ups, boss battles, and 35+ visual themes from featured artists.</p>
            <a class="hp-cta" href="https://www.skulliance.io/staking/match3rpg.php">Play Monstrocity</a>
          </div>
          <div class="hp-game">
            <a class="hp-game-art" href="https://www.skulliance.io/staking/skullswap.php" aria-label="Play Skull Swap, the free match 3 puzzle game">
              <img src="https://www.skulliance.io/staking/images/skullswap.png" alt="Skull Swap match 3 puzzle game board" loading="lazy" decoding="async">
            </a>
            <h3>Skull Swap - Match 3 Puzzle</h3>
            <p>A pure score chase: exactly 25 matches to forge Carbon and Diamond bombs, chain detonations for huge combos, and squeeze out every last point.</p>
            <a class="hp-cta" href="https://www.skulliance.io/staking/skullswap.php">Play Skull Swap</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Founding Artists -->
    <section id="artists">
      <div class="wrap">
        <h2>Founding Artists</h2>
        <p class="hp-intro hp-center">These artists specialize in skull art on Cardano and came together to form Skulliance. Holders of their NFTs can stake them on the Skulliance platform and earn nightly off-chain points redeemable for exclusive incentives.</p>
        <ul class="hp-logos hp-founding">
          <li><a href="https://x.com/SinderSkullz" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/sinderskullz.png" alt="Sinder Skullz" loading="lazy" decoding="async"></a></li>
          <li><a href="https://x.com/Nft4R" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/kimosabe.png" alt="Kimosabe Art" loading="lazy" decoding="async"></a></li>
          <li><a href="https://x.com/cryptiesnft" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/crypties.png" alt="Crypties" loading="lazy" decoding="async"></a></li>
          <li><a href="https://x.com/GalacticoNFT" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/galactico.png" alt="Galactico" loading="lazy" decoding="async"></a></li>
          <li><a href="https://x.com/ohh_meed" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/ohhmeed.png" alt="Ohh Meed" loading="lazy" decoding="async"></a></li>
          <li><a href="https://x.com/haveyouseenhype" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/hype.png" alt="H.Y.P.E." loading="lazy" decoding="async"></a></li>
        </ul>
      </div>
    </section>

    <!-- Staking Partners -->
    <section id="partners">
      <div class="wrap">
        <h2>Partner Artists &amp; Projects</h2>
        <p class="hp-intro hp-center">With the success of the platform, Skulliance invited other high-quality artists and projects on Cardano to participate in partner staking - their holders earn points, redeem incentives, and climb the leaderboards too.</p>
        <?php
        // Partner flyers, split half/half across two counter-scrolling
        // marquee rows so the majority of artists register at a glance.
        // Each track holds its list twice for the seamless -50% translate
        // loop (second pass aria-hidden). Image filenames live under
        // /staking/images/projects/.
        $hp_partners = [
            ['nemonium.jpg',     'Nemonium',                'https://x.com/_nemonium'],
            ['discosolaris.png', 'Disco Solaris',           'https://x.com/discosolaris'],
            ['danketsu.png',     'Danketsu',                'https://x.com/DanketsuNFT'],
            ['squashua.jpg',     'Squashua',                'https://x.com/Joshua_Squashua'],
            ['netanelcohen.png', 'Netanel Cohen',           'https://x.com/netanelchn'],
            ['maxingo.png',      'Maxingo',                 'https://x.com/madmaxi__'],
            ['pendulum.jpg',     'Pendulum',                'https://x.com/Pendulum_NFT'],
            ['aeoniumsky.jpg',   'Aeoniumsky',              'https://x.com/aeoniumsky'],
            ['havocworlds.jpg',  'Havoc Worlds',            'https://x.com/havocworlds'],
            ['goattribe.jpg',    'Goat Tribe',              'https://x.com/adaGOATS'],
            ['threefoldbold.png','Threefold Bold',          'https://x.com/Threefoldbold'],
            ['bungking.jpg',     'Bungking',                'https://x.com/Fiqhi_Alfani'],
            ['darkula.jpg',      'Darkula',                 'https://x.com/darkula__'],
            ['heistonalpha.jpg', 'Heist on Alpha',          'https://x.com/heistonalpha'],
            ['apprentices.png',  'Apprentices',             'https://x.com/ApprenticesCNFT'],
            ['deadpophell.png',  'Dead Pop Hell',           'https://x.com/deadpophell'],
            ['fart.jpg',         'f.ART',                   'https://x.com/cnftfart'],
            ['muses.jpg',        'Muses of the Multiverse', 'https://x.com/joshuahoward'],
            ['cardanocamera.jpg','Cardano Camera',          'https://x.com/cardanocamera'],
            ['oldmoney.jpg',     'Old Money',               'https://x.com/OldMoneyNFT'],
            ['jordi.png',        'Jordi',                   'https://x.com/JordiLeitao'],
            ['ascenderone.jpg',  'Ascender One',            'https://x.com/AscenderOne'],
            ['ritual.png',       'Ritual',                  'https://x.com/thecgritual'],
            ['mipatoys.jpg',     'Mipa Toys',               'https://x.com/MipaToys'],
            ['stagwolf.jpg',     'Stagwolf',                'https://x.com/stagwolf'],
            ['grey.png',         'Grey',                    'https://x.com/diexgrey'],
            ['skowl.jpg',        'Skowl',                   'https://x.com/skowllwoks'],
        ];
        $hp_img_base = 'https://www.skulliance.io/staking/images/projects/';
        $hp_rows = array_chunk($hp_partners, (int)ceil(count($hp_partners) / 2));
        foreach ($hp_rows as $hp_row_index => $hp_row): ?>
        <div class="hp-strip">
            <div class="hp-strip-track<?php echo $hp_row_index % 2 ? ' hp-reverse' : ''; ?>">
                <?php for ($hp_pass = 0; $hp_pass < 2; $hp_pass++):
                    foreach ($hp_row as $hp_partner):
                        list($hp_file, $hp_name, $hp_url) = $hp_partner; ?>
                <a class="hp-strip-card" href="<?php echo htmlspecialchars($hp_url); ?>" target="_blank" rel="noopener"<?php if ($hp_pass) echo ' aria-hidden="true" tabindex="-1"'; ?>>
                    <img src="<?php echo $hp_img_base . $hp_file; ?>"
                         alt="<?php echo $hp_pass ? '' : htmlspecialchars($hp_name); ?>"
                         loading="lazy" decoding="async">
                    <span class="hp-strip-name"><?php echo htmlspecialchars($hp_name); ?></span>
                </a>
                <?php endforeach; endfor; ?>
            </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Staking Platform -->
    <section id="platform">
      <div class="wrap">
        <h2>The Staking Platform</h2>
        <p class="hp-intro hp-center">Log in with Discord, connect your Cardano wallets, and your qualifying NFTs start earning nightly off-chain points - redeemable for exclusive incentives in the staking store. Claim daily rewards, send your NFTs on idle missions, build out your Realm, run the Gauntlet, delegate core project NFTs to Diamond Skulls to earn CARBON and craft DIAMOND, explore the Skulliverse, and climb the leaderboards.</p>
        <?php
        // Platform screenshot cards. Images are served from /staking/screenshots/
        // (temporary home - if the folder moves, update $hp_shot_base only).
        $hp_shot_base = 'https://www.skulliance.io/staking/screenshots/';
        $hp_shots = [
            ['profile.png',       'Profile',                  'https://www.skulliance.io/staking/profile.php'],
            ['dashboard.png',     'Dashboard',                'https://www.skulliance.io/staking/dashboard.php'],
            ['store.png',         'Staking Store',            'https://www.skulliance.io/staking/store.php'],
            ['missions.png',      'Missions',                 'https://www.skulliance.io/staking/missions.php'],
            ['gauntlet.png',      'Gauntlets',                'https://www.skulliance.io/staking/gauntlets.php'],
            ['realms.png',        'Realms',                   'https://www.skulliance.io/staking/realms.php'],
            ['diamond-skulls.png','Diamond Skulls',           'https://www.skulliance.io/staking/diamond-skulls.php'],
            ['delegation.png',    'Delegations',              'https://www.skulliance.io/staking/diamond-skulls.php#delegation'],
            ['skulliverse.png',   'Skulliverse',              'https://www.skulliance.io/staking/skulliverse.php'],
            ['monstrocity.png',   'Monstrocity - Match 3 RPG','https://www.skulliance.io/staking/match3rpg.php'],
            ['skull-swap.png',    'Skull Swap',               'https://www.skulliance.io/staking/skullswap.php'],
            ['boss-battles.png',  'Boss Battles',             'https://www.skulliance.io/staking/monstrocity.php#boss'],
            ['daily-rewards.png', 'Daily Rewards & Crafting', 'https://www.skulliance.io/staking/dashboard.php'],
            ['leaderboard.png',   'Leaderboards',             'https://www.skulliance.io/staking/leaderboards.php'],
            ['analytics.png',     'Analytics',                'https://www.skulliance.io/staking/analytics.php'],
        ];
        ?>
        <div class="hp-grid hp-shots">
          <?php foreach ($hp_shots as $hp_shot): list($hp_shot_file, $hp_shot_name, $hp_shot_url) = $hp_shot; ?>
          <div class="hp-shot-card">
            <h3><?php echo htmlspecialchars($hp_shot_name); ?></h3>
            <a href="<?php echo $hp_shot_url; ?>"><img src="<?php echo $hp_shot_base . $hp_shot_file; ?>" alt="<?php echo htmlspecialchars($hp_shot_name); ?> screenshot" loading="lazy" decoding="async"></a>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="hp-center" style="margin-top: 28px;"><a class="hp-cta" href="https://www.skulliance.io/staking">Start Staking</a></p>
      </div>
    </section>

    <!-- Membership -->
    <section id="membership">
      <div class="wrap">
        <h2>Membership Tiers</h2>
        <p class="hp-intro hp-center">Staking is open to everyone - membership unlocks more of the store and deeper rewards as your collection grows.</p>
        <div class="hp-tiers">
          <div class="hp-tier">
            <p class="hp-tier-rank">Tier 1</p>
            <h3>Base Member</h3>
            <p>Hold 1 NFT from Sinder Skullz, Kimosabe Art, and Crypties. Base membership isn't required to stake, but it unlocks claiming exclusive incentives from the staking store with the points you accumulate.</p>
          </div>
          <div class="hp-tier">
            <p class="hp-tier-rank">Tier 2</p>
            <h3>Elite Member</h3>
            <p>Hold at least 1 NFT from every founding artist. Elite members can convert equal parts of founding-artist points into DIAMOND - the premium currency that can purchase any store reward at a discount or premium.</p>
          </div>
          <div class="hp-tier hp-tier-top">
            <p class="hp-tier-rank">Tier 3</p>
            <h3>Inner Circle</h3>
            <p>Elite members who also hold a Diamond Skull NFT. The Inner Circle earns CARBON delegation rewards plus DIAMOND from nightly emissions and crafting - the deepest reward loop on the platform.</p>
          </div>
        </div>
        <div class="hp-member-art">
          <img src="https://www.skulliance.io/staking/images/skulliance.jpg" alt="Skulliance membership artwork" loading="lazy" decoding="async">
        </div>
      </div>
    </section>

    <!-- Team -->
    <section id="team">
      <div class="wrap">
        <h2>Team</h2>
        <p class="hp-intro hp-center">Dedicated to elevating skull artists on Cardano and bringing real value and utility to the Skulliance family of artists and loyal collectors.</p>
        <div class="hp-team">
          <div class="hp-member">
            <a href="https://www.x.com/oculusorbus" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/team/oculusorbus.jpg" alt="Oculus Orbus" loading="lazy" decoding="async"></a>
            <p class="hp-role">Founder &amp; Developer</p>
            <p class="hp-name">Oculus Orbus</p>
          </div>
          <div class="hp-member">
            <a href="https://www.x.com/TheKryptman" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/team/kryptman.jpg" alt="Kryptman" loading="lazy" decoding="async"></a>
            <p class="hp-role">Co-Founder</p>
            <p class="hp-name">Kryptman</p>
          </div>
          <div class="hp-member">
            <a href="https://www.x.com/diexgrey" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/team/diexgrey.jpg" alt="Diex Grey" loading="lazy" decoding="async"></a>
            <p class="hp-role">Artist / Visual Creative</p>
            <p class="hp-name">Diex Grey (Galactico)</p>
          </div>
          <div class="hp-member">
            <a href="https://www.x.com/SinderSkullz" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/team/sinderskullz.jpg" alt="Sinder Skullz" loading="lazy" decoding="async"></a>
            <p class="hp-role">Diamond Skulls Artist</p>
            <p class="hp-name">Sinder Skullz</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section>
      <div class="wrap">
        <div class="hp-final">
          <h2>Join Skulliance</h2>
          <p>Play the games, meet the artists, grab some merch, and start earning nightly rewards for the skull NFTs you already love collecting. Cardano is our home - come make it yours.</p>
          <div class="hp-ctas">
            <a class="hp-cta" href="https://discord.gg/JqqBZBrph2">Join the Discord</a>
            <a class="hp-cta hp-secondary" href="#games">Play Free Games</a>
            <a class="hp-cta hp-secondary" href="https://www.skulliance.io/shop">Shop Merch</a>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Footer -->
  <footer>
    <div class="hp-foot-links">
      <a href="https://www.skulliance.io/staking">Staking</a>
      <a href="https://www.skulliance.io/shop">Merch</a>
      <a href="https://www.skulliance.io/staking/match3rpg.php">Monstrocity</a>
      <a href="https://www.skulliance.io/staking/skullswap.php">Skull Swap</a>
      <a href="https://skulliance.gitbook.io/skulliance" target="_blank" rel="noopener">Skull Paper</a>
      <a href="https://discord.gg/JqqBZBrph2">Discord</a>
      <a href="https://www.x.com/skulliance">X</a>
    </div>
    <p>Skulliance &middot; Copyright &copy; <span id="hp-year"></span></p>
  </footer>

  <script>
    document.getElementById('hp-year').textContent = new Date().getFullYear();

    function hpToggleMenu() {
      var links = document.getElementById('hp-links');
      var icon = document.getElementById('hp-burger-icon');
      var burger = document.getElementById('hp-burger');
      var open = links.classList.toggle('open');
      icon.src = open
        ? 'https://www.skulliance.io/staking/images/close.png'
        : 'https://www.skulliance.io/staking/images/menu.png';
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Close the mobile menu after tapping an anchor link
    document.querySelectorAll('#hp-links a').forEach(function (a) {
      a.addEventListener('click', function () {
        var links = document.getElementById('hp-links');
        if (links.classList.contains('open')) hpToggleMenu();
      });
    });
  </script>
</body>
</html>

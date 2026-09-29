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
// dependencies on the staking platform's stylesheets. Nothing under WP or
// /staking can drift the design.
//
// DEPLOYED BY INCLUDE, NOT BY COPY-PASTE. The WordPress theme file is a
// stub that includes THIS file from the staking directory, so a push and a
// pull deploys the homepage -- no copying, and no chance of the two copies
// disagreeing. The stub is wp-homepage-template.php in this repo; copy that
// into the theme. It keeps the "Template name:" header, because that is
// what makes the template selectable in WordPress.
//
// IT RESOLVES THE PATH WITH ABSPATH, which WordPress defines as its own
// install directory with a trailing slash. A hardcoded absolute path is
// what produced a WHITE SCREEN on the first attempt: a template whose only
// statement is an include that fails outputs nothing at all, and with
// display_errors off in production that is a blank page carrying no clue.
// The stub therefore also renders a real fallback page rather than
// nothing, and names the path it tried -- to an administrator only.
//
// THIS FILE MUST STAY SAFE TO INCLUDE FROM ANOTHER APPLICATION. It has no
// includes, reads no $_SESSION, and depends on no current working
// directory -- so it behaves the same under WordPress as under /staking.
// Keep it that way: for database figures use homepage-data.php, which is
// built for this, and never include db.php here. db.php loads its
// credentials by a CWD-relative path (which breaks under WP), turns
// display_errors ON, and starts a session -- see that file's header.
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
  <title>Skulliance | Skull NFT Artists on Cardano - Staking, Games &amp; Merch</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Skulliance connects collectors with premier skull NFT artists on Cardano. Stake NFTs for nightly rewards, play free browser games, and shop exclusive merch.">
  <meta name="keywords" content="skulliance, skull nft, skull nfts, cardano nft, cardano nft staking, cnft staking, nft staking rewards, skull art nft, cardano nft projects, cnft projects, skull artists cardano, nft staking platform, cardano nft community, diamond skulls, nft idle missions, nft games, free nft games, skull art, cardano cnft, nft rewards platform">
  <meta name="theme-color" content="#07111d">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
  <link rel="canonical" href="https://www.skulliance.io/">

  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="32x32" href="https://www.skulliance.io/staking/pwa/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="https://www.skulliance.io/staking/pwa/favicon-16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="https://www.skulliance.io/staking/pwa/apple-touch-icon.png">

  <!-- OpenGraph -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="https://www.skulliance.io/">
  <meta property="og:title" content="Skulliance - Premier Skull NFT Artists on Cardano">
  <meta property="og:description" content="Stake skull NFTs for nightly rewards, play free browser games, run missions, climb leaderboards, and shop exclusive merch - built on Cardano.">
  <meta property="og:image" content="https://www.skulliance.io/staking/images/skulliance-group.jpg">
  <meta property="og:image:width" content="1500">
  <meta property="og:image:height" content="765">
  <meta property="og:image:alt" content="Skulliance founding skull artists group artwork">
  <meta property="og:locale" content="en_US">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@skulliance">
  <meta name="twitter:title" content="Skulliance - Premier Skull NFT Artists on Cardano">
  <meta name="twitter:description" content="Stake skull NFTs for nightly rewards, play free browser games, and shop exclusive skull art merch - built on Cardano.">
  <meta name="twitter:image" content="https://www.skulliance.io/staking/images/skulliance-group.jpg">
  <meta name="twitter:image:alt" content="Skulliance founding skull artists group artwork">

  <!-- Schema.org: Organization + WebSite + WebPage + games ItemList,
       cross-linked via @id so crawlers see one connected entity graph -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "https://www.skulliance.io/#organization",
        "name": "Skulliance",
        "url": "https://www.skulliance.io/",
        "logo": {
          "@type": "ImageObject",
          "url": "https://www.skulliance.io/staking/images/skulliancelogo.png",
          "width": 700,
          "height": 300
        },
        "image": "https://www.skulliance.io/staking/images/skulliance-group.jpg",
        "description": "Skulliance connects art collectors with the premier skull NFT artists on the Cardano blockchain - NFT staking with nightly rewards, free browser games, idle missions, leaderboards, and exclusive merch.",
        "founder": {
          "@type": "Person",
          "name": "Oculus Orbus",
          "url": "https://www.x.com/oculusorbus"
        },
        "knowsAbout": ["Skull Art", "NFT Art", "Cardano", "NFT Staking", "Browser Games", "Digital Collectibles"],
        "sameAs": [
          "https://www.x.com/skulliance",
          "https://discord.gg/JqqBZBrph2",
          "https://www.skulliance.io/staking/skullpaper.php"
        ]
      },
      {
        "@type": "WebSite",
        "@id": "https://www.skulliance.io/#website",
        "url": "https://www.skulliance.io/",
        "name": "Skulliance",
        "publisher": { "@id": "https://www.skulliance.io/#organization" },
        "inLanguage": "en"
      },
      {
        "@type": "WebPage",
        "@id": "https://www.skulliance.io/#webpage",
        "url": "https://www.skulliance.io/",
        "name": "Skulliance - Premier Skull NFT Artists on Cardano | NFT Staking, Games & Merch",
        "isPartOf": { "@id": "https://www.skulliance.io/#website" },
        "about": { "@id": "https://www.skulliance.io/#organization" },
        "primaryImageOfPage": "https://www.skulliance.io/staking/images/skulliance-group.jpg",
        "description": "Skulliance connects collectors with the premier skull NFT artists on Cardano - NFT staking with nightly rewards, free browser games, missions, leaderboards, and exclusive merch.",
        "inLanguage": "en"
      },
      {
        "@type": "ItemList",
        "@id": "https://www.skulliance.io/#games",
        "name": "Free Browser Games by Skulliance",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "item": {
              "@type": "VideoGame",
              "name": "Monstrocity",
              "url": "https://www.skulliance.io/staking/match3rpg.php",
              "image": "https://www.skulliance.io/staking/images/monstrocity/logo.png",
              "genre": ["Match 3", "Puzzle RPG"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          },
          {
            "@type": "ListItem",
            "position": 2,
            "item": {
              "@type": "VideoGame",
              "name": "Skull Swap",
              "url": "https://www.skulliance.io/staking/skullswap.php",
              "image": "https://www.skulliance.io/staking/images/skullswap.png",
              "genre": ["Match 3", "Puzzle"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          },
          {
            "@type": "ListItem",
            "position": 3,
            "item": {
              "@type": "VideoGame",
              "name": "Crypt Crawl",
              "url": "https://www.skulliance.io/staking/cryptcrawlgame.php",
              "image": "https://www.skulliance.io/staking/images/cryptcrawl.png",
              "genre": ["Card Game", "Roguelike", "Dungeon Crawler"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          },
          {
            "@type": "ListItem",
            "position": 4,
            "item": {
              "@type": "VideoGame",
              "name": "Crypt Conquest",
              "url": "https://www.skulliance.io/staking/cryptconquestgame.php",
              "image": "https://www.skulliance.io/staking/images/cryptconquest.png",
              "genre": ["Card Game", "Roguelike", "Strategy"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          },
          {
            "@type": "ListItem",
            "position": 5,
            "item": {
              "@type": "VideoGame",
              "name": "Skull Racer",
              "url": "https://www.skulliance.io/staking/skullracergame.php",
              "image": "https://www.skulliance.io/staking/racing/images/screenshot.png",
              "genre": ["Racing", "Arcade"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          },
          {
            "@type": "ListItem",
            "position": 6,
            "item": {
              "@type": "VideoGame",
              "name": "Realm Guardians",
              "url": "https://www.skulliance.io/staking/guardiansgame.php",
              "image": "https://www.skulliance.io/staking/images/guardians.png",
              "genre": ["Strategy", "Tower Defense"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          },
          {
            "@type": "ListItem",
            "position": 7,
            "item": {
              "@type": "VideoGame",
              "name": "DHC Fighters",
              "url": "https://www.skulliance.io/staking/dhcgame.php",
              "image": "https://www.skulliance.io/staking/images/dhcgame.png",
              "genre": ["Match 3", "Strategy", "Collectible Card Game"],
              "gamePlatform": ["Web Browser", "Mobile", "Tablet", "Desktop"],
              "isAccessibleForFree": true,
              "publisher": { "@id": "https://www.skulliance.io/#organization" }
            }
          }
        ]
      }
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
    /* ---------- Hero ---------- */
    .hp-hero {
      text-align: center;
      /* 56px, not 120px. The nav was FIXED -- out of the layout -- so the
         hero had to reserve its height by hand. site-header.php is STICKY
         and occupies its own space, so that reservation is now a gap. */
      padding: 56px 20px 56px;
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
    /* The h1 is now the HEADLINE, not a quiet keyword line. The keywords
       moved to .hp-lede below it, which is where a feature list belongs --
       so nothing was lost for search, it just stopped being the first
       thing a human reads. */
    .hp-hero h1 {
      font-size: clamp(1.9rem, 5vw, 3.6rem);
      font-weight: 800; line-height: 1.06; letter-spacing: -.02em;
      color: #e8eaed; max-width: 900px; margin: 6px auto 16px;
    }
    .hp-hero h1 .hp-turn {
      display: block;
      background: linear-gradient(135deg, #00c8a0, #0596c4);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .hp-lede {
      max-width: 700px; margin: 0 auto 24px;
      font-size: 1.02rem; color: #b9c7d4;
    }
    .hp-nosignup { margin: 14px 0 0; font-size: .82rem; color: #5a7888; }

    /* Four live numbers. A badge is a claim; a number is evidence. */
    /* Tiles keep a sane width whatever the count. With plain 1fr columns a
       single surviving tile stretches the whole 860px and looks like a
       mistake rather than a deliberate row -- and one surviving tile is
       exactly what a database blip produces. */
    .hp-stats {
      display: grid; gap: 14px; max-width: 860px; margin: 30px auto 0;
      justify-content: center;
    }
    @media (max-width: 620px) { .hp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } }
    .hp-stat {
      background: rgba(10,25,41,.7); border: 1px solid rgba(0,200,160,.14);
      border-radius: 12px; padding: 16px 10px;
    }
    .hp-stat b { display: block; font-size: 1.6rem; color: #00c8a0; line-height: 1.1; }
    .hp-stat span { font-size: .72rem; color: #7a9eb0; letter-spacing: .04em; text-transform: uppercase; }

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

    /* ---------- Sections ---------- */
    section { padding: 48px 0; }
    section + section { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .hp-center { text-align: center; }
    .hp-intro { max-width: 760px; margin: 0 auto 8px; color: #c7d0d9; }
    section h2 { text-align: center; }

    /*
     * ================= THE LAYER CAKE ==================================
     * Every section used to be the same shape -- 48px of padding, a
     * hairline rule, a centred h2, a centred 760px paragraph, then a grid.
     * Three of those in a row is why Mission, Games and Platform read as
     * one long undifferentiated page: nothing tells you a new idea has
     * started, so nothing invites you to stop and read it.
     *
     * A layer is now a FULL-BLEED BAND with its own ground. Alternating
     * grounds do the work the hairline was failing to do, and each layer
     * gets its own rhythm -- the mission is left-aligned over art, the
     * games layer leads with one big one, the platform layer states what
     * you DO before showing twenty screenshots of it.
     */
    .hp-layer {
      width: 100vw; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw);
      padding: 84px 0;
    }
    .hp-layer + .hp-layer { border-top: 0; }
    .hp-layer.alt { background: #0a1724; }
    .hp-layer .wrap { max-width: 1120px; margin: 0 auto; padding: 0 22px; }
    /* A layer heading is BIG and can sit left. A centred 1.5rem h2 over a
       centred paragraph is the shape of a 2009 brochure. */
    .hp-layer h2 {
      font-size: clamp(1.8rem, 4vw, 3rem); line-height: 1.08;
      letter-spacing: -.02em; text-align: left; margin: 0 0 14px;
    }
    .hp-layer .hp-kick {
      display: block; font-size: .74rem; letter-spacing: .18em;
      text-transform: uppercase; color: #00c8a0; margin-bottom: 12px;
      text-align: left;
    }
    .hp-layer .hp-say { max-width: 620px; color: #b9c7d4; font-size: 1.02rem; }

    /* ---- Mission: a claim over the art, not a paragraph under it ---- */
    .hp-mission {
      position: relative; overflow: hidden;
      background:
        linear-gradient(90deg, rgba(7,17,29,.96) 0%, rgba(7,17,29,.82) 46%, rgba(7,17,29,.30) 100%),
        url('https://www.skulliance.io/staking/images/skulliance-group.jpg') center/cover no-repeat;
    }
    .hp-mission .wrap { padding-top: 26px; padding-bottom: 26px; }
    .hp-mission .hp-say { max-width: 560px; }
    @media (max-width: 720px) {
      .hp-mission {
        background:
          linear-gradient(180deg, rgba(7,17,29,.90) 0%, rgba(7,17,29,.96) 70%),
          url('https://www.skulliance.io/staking/images/skulliance-group.jpg') center/cover no-repeat;
      }
    }

    /* ---- Games: one featured, the rest compact ---- */
    .hp-feature {
      display: grid; grid-template-columns: 1.05fr .95fr; gap: 34px;
      align-items: center; margin-top: 26px;
    }
    @media (max-width: 860px) { .hp-feature { grid-template-columns: 1fr; gap: 20px; } }
    .hp-feature img { border-radius: 14px; border: 1px solid rgba(0,200,160,.16); box-shadow: 0 18px 46px rgba(0,0,0,.5); }
    .hp-feature .hp-tag {
      display: inline-block; font-size: .7rem; letter-spacing: .14em;
      text-transform: uppercase; color: #04121b; font-weight: 800;
      background: linear-gradient(135deg,#00c8a0,#0596c4);
      padding: 4px 10px; border-radius: 999px; margin-bottom: 10px;
    }
    .hp-feature h3 { font-size: 1.5rem; color: #e8eaed; margin: 0 0 10px; }
    .hp-more {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 40px;
    }
    @media (max-width: 860px) { .hp-more { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px) { .hp-more { grid-template-columns: 1fr; } }
    .hp-mini {
      display: block; background: #0a1929; border: 1px solid rgba(0,200,160,.12);
      border-radius: 12px; padding: 14px; text-decoration: none !important;
      transition: border-color .15s, transform .15s;
    }
    .hp-mini:hover { border-color: rgba(0,200,160,.45); transform: translateY(-2px); }
    .hp-mini img { border-radius: 8px; aspect-ratio: 16/10; object-fit: cover; width: 100%; }
    .hp-mini b { display: block; margin-top: 10px; color: #e8eaed; font-size: .94rem; }
    .hp-mini span { display: block; color: #7a9eb0; font-size: .82rem; margin-top: 2px; }

    /* A thin layer, for a pointer rather than a pitch. Giving the Skull
       Paper or the team the same height as Games would claim they matter
       as much. */
    .hp-layer.hp-thin { padding: 54px 0; }
    .hp-split { display: grid; grid-template-columns: 1.6fr auto; gap: 30px; align-items: center; }
    @media (max-width: 760px) { .hp-split { grid-template-columns: 1fr; } }
    .hp-split-act { text-align: left; }

    /* The close. Centred on purpose -- it is the one layer that should feel
       like an ending rather than another band of content. */
    .hp-close { text-align: center; padding: 96px 0; }
    .hp-close h2.hp-close-h {
      text-align: center; font-size: clamp(2rem, 5vw, 3.4rem);
      background: linear-gradient(135deg,#00c8a0,#0596c4);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .hp-close .hp-close-p { margin: 0 auto 26px; text-align: center; }
    .hp-close .hp-final-art { max-width: 240px; margin: 0 auto 18px; }

    /* ---- Platform: say what you DO, then show the wall ---- */
    .hp-does { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-top: 30px; }
    @media (max-width: 860px) { .hp-does { grid-template-columns: 1fr; } }
    .hp-do img { border-radius: 12px; border: 1px solid rgba(0,200,160,.14); aspect-ratio: 16/10; object-fit: cover; width: 100%; }
    .hp-do h3 { font-size: 1.1rem; color: #00c8a0; margin: 14px 0 6px; }
    .hp-do p { color: #b9c7d4; font-size: .92rem; margin: 0; }
    /* The twenty screenshots are not a menu, they are EVIDENCE. Labelled as
       such and shrunk, they stop asking to be read one by one. */
    .hp-wall-label {
      margin: 52px 0 14px; font-size: .74rem; letter-spacing: .16em;
      text-transform: uppercase; color: #5a7888; text-align: left;
    }
    .hp-wall { display: grid; grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); gap: 10px; }
    .hp-wall a { display: block; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,.07); }
    .hp-wall a:hover { border-color: rgba(0,200,160,.45); }
    .hp-wall img { width: 100%; aspect-ratio: 16/10; object-fit: cover; display: block; }

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

    /* Games -- fixed 2-column grid, which auto-fit would break: at this
       section's ~1060px content width it lays out 3 then a lone straggler.
       Two columns divides evenly for any EVEN number of games, which is
       why the count matters: 6 was 3 clean rows, and DHC Fighters makes 7,
       so the last card now sits alone. Adding an eighth closes it, or go
       to a 3rd column at 9. Count is Monstrocity, Skull Swap, Crypt Crawl,
       Crypt Conquest, Skull Racer, Realm Guardians, DHC Fighters. */
    .hp-games { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 24px; }
    @media (max-width: 640px) {
      .hp-games { grid-template-columns: 1fr; }
    }
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
    .hp-logos a { display: block; width: 100%; text-decoration: none !important; }
    .hp-logos img { width: 100%; height: 150px; object-fit: contain; }
    .hp-logo-name {
      display: block; margin-top: 10px; text-align: center;
      font-size: 0.88rem; font-weight: 600;
      color: #c7d0d9; letter-spacing: 0.02em;
    }
    .hp-logos li:hover .hp-logo-name { color: #34e3bb; }
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
    /* 15 $hp_shots cards + the four standalone cards below the loop (Crypt
       Crawl, Crypt Conquest, Skull Racer, Realm Guardians) = 19, which is six
       full 3-column rows and ONE left over -- so the straggler rule this
       comment predicted is needed again, and Realm Guardians is the card
       sitting alone.

       Centres it in the middle column once the grid genuinely lands on 3
       columns. .wrap's 1100px max-width (minus 40px padding = 1060px content)
       never reaches a 4th column -- 4 x 250 + 3 x 18 = 1054... which now DOES
       fit -- which is why the .hp-shots rule ABOVE pins this grid's minmax to
       260px instead of inheriting .hp-grid's 250px. 3 columns first fit at roughly an 856px
       viewport, so 900px is comfortably "wide enough for 3, never enough for
       4". If the count stops leaving a single card in the last row, drop this
       rule with it. */
    @media (min-width: 900px) {
      .hp-shots .hp-shot-card:last-child { grid-column: 2; }
    }
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

    /* Skull Paper callout - centered like the final Join Skulliance panel,
       skull icon above the title */
    .hp-paper {
      text-align: center; padding: 56px 20px;
      background: linear-gradient(135deg, rgba(0, 200, 160, 0.10), rgba(5, 150, 196, 0.07));
      border: 1px solid rgba(0, 200, 160, 0.30);
      border-radius: 16px;
    }
    .hp-paper-icon {
      display: block; width: 84px; height: auto; margin: 0 auto 18px;
      filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.5));
    }
    .hp-paper h2 { margin: 0 0 12px; }
    .hp-paper p { color: #c7d0d9; max-width: 680px; margin: 0 auto 24px; }

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
    /* Membership artwork above the section title, emblem-style like the
       Skull Paper and Join Skulliance panels */
    .hp-member-art {
      display: block;
      width: 100%; max-width: 240px; height: auto;
      margin: 0 auto 20px;
      border-radius: 14px;
      filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.5));
    }

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

    /* Skulliance x Cardano artwork above the Join Skulliance title,
       mirroring the Skull Paper callout's icon-above-title treatment */
    .hp-final-art {
      display: block;
      width: 100%; max-width: 240px; height: auto;
      margin: 0 auto 20px;
      filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.5));
    }

    /* Footer */
    footer {
      padding: 30px 20px; text-align: center;
      color: #8a96a3; font-size: 0.88rem;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }
    footer a { color: #8a96a3; }
    footer .hp-foot-links { margin-bottom: 10px; display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }

    @media (max-width: 480px) {
      .hp-hero { padding-top: 40px; }   /* was 96: see the note above */
      .hp-hero .hp-ctas .hp-cta { width: 100%; text-align: center; }
      .hp-final .hp-ctas .hp-cta { width: 100%; text-align: center; }
    }
  </style>
</head>
<body>

  <?php
  /* Navigation now lives in site-header.php, shared with every public game
     landing page -- a visitor who arrives on one of those was previously at
     a dead end. This page keeps its full section list; the include's
     default is the shorter set a game page wants.

     __DIR__ matters: this file is included by the WordPress theme, where
     the working directory is the WP root and a relative include would miss.
     $sh_on_home keeps these as in-page anchors instead of reloading. */
  $sh_on_home = true;
  $sh_links = array(
      array('Mission',     '/#mission'),
      array('Games',       '/#games'),
      array('Artists',     '/#artists'),
      array('Partners',    '/#partners'),
      array('Platform',    '/#platform'),
      array('Team',        '/#team'),
      array('Staking',     '/staking/'),
      array('Merch',       '/shop'),
      array('Skull Paper', '/staking/skullpaper.php'),
  );
  include __DIR__ . '/site-header.php';
  ?>

  <!-- Hero -->
  <?php
  /*
   * THE FIRST SCREEN. Rewritten after comparing the page with omenati.com:
   *
   *                     omenati.com     this page, before
   *   visible text      1,345 chars     7,072 chars
   *   headings          ~4              40
   *   hero asked you to do one thing    choose between three
   *
   * The depth below is not the problem -- it is why the game pages rank.
   * The problem was that the first screen did none of the work. The old H1
   * was 128 characters describing a CATEGORY: what Skulliance IS, never
   * what a visitor gets or does. It is now the sub-line, which is where a
   * feature list belongs.
   *
   * WHAT WENT, and it can come back in one block from git history:
   *   - the three CTAs, down to one primary and one secondary. Three CTAs
   *     is zero CTAs; a stranger cannot pick. Play is the lowest-friction
   *     door and the one nothing else on Cardano has.
   *   - the five badges, replaced by four LIVE numbers. "Free Browser
   *     Games" as a badge is a claim; "7" next to "12,000 NFTs staked" is
   *     evidence, and it costs one query each, cached five minutes.
   * The logo stays.
   *
   * A SCROLLING WALL OF ARTIST FLYERS WAS HERE AND WAS REMOVED. It looked
   * right and cost 18MB: those nine files are 1.2-2.8MB each at 1500px,
   * rendered into 132px tiles. The partner strip further down gets away
   * with the same images only because it is loading="lazy" and below the
   * fold -- and lazy is exactly what a marquee CANNOT use, because a tile
   * arrives by transform rather than by layout and so never loads at all.
   * Eager meant 18MB in front of the first screen.
   *
   * The Mission layer immediately below is now full-bleed artwork at
   * 0.3MB, which does the "lead with the art" job better and avoids two
   * art walls back to back. Bring the strip back only with resized files:
   * a 132px tile wants about 300px of source, not 1500.
   */
  require_once __DIR__ . '/homepage-data.php';
  $stat_staked  = hp_stat_nfts_staked();
  $stat_stakers = hp_stat_stakers();
  $stat_artists = hp_stat_artists();

  /* A ZERO IS WORSE THAN A GAP. These fall back to 0 when the database is
     unreachable, and a homepage announcing "0 NFTs staked" argues against
     itself -- it is the one number a sceptic believes instantly. A stat
     that cannot be proven is dropped and the row relays out. */
  $hp_tiles = array();
  if ($stat_staked  > 0) $hp_tiles[] = array(number_format($stat_staked),  'NFTs staked');
  if ($stat_stakers > 0) $hp_tiles[] = array(number_format($stat_stakers), 'Stakers');
  if ($stat_artists > 0) $hp_tiles[] = array(number_format($stat_artists), 'Artists & projects');
  $hp_tiles[] = array('7', 'Free games');

  ?>
  <header class="hp-hero" id="top">
    <img class="hp-logo" src="https://www.skulliance.io/staking/images/skulliancelogo.png" alt="Skulliance logo" fetchpriority="high" decoding="async">

    <?php /* Two beats: name the problem, then the turn. That shape is what
             makes a line repeatable, and it leads on the one thing that is
             actually different here rather than on the category. */ ?>
    <h1>Most NFTs just sit there. <span class="hp-turn">Yours don't have to.</span></h1>

    <p class="hp-lede">Skulliance is a collective of NFT artists/projects on
       <strong>Cardano and the XRP Ledger</strong>. Stake your NFTs for daily rewards,
       send them on missions, or take them into seven free browser games. No signup to play.</p>

    <div class="hp-ctas">
      <a class="hp-cta" href="#games">Play a game, free</a>
      <a class="hp-cta hp-secondary" href="https://www.skulliance.io/staking">Stake your NFTs</a>
    </div>
    <p class="hp-nosignup">No wallet needed to play. No download. Works on your phone.</p>

    <div class="hp-stats" style="grid-template-columns: repeat(<?php echo min(4, count($hp_tiles)); ?>, minmax(140px, 200px))">
      <?php foreach ($hp_tiles as $t): ?>
      <div class="hp-stat"><b><?php echo $t[0]; ?></b><span><?php echo htmlspecialchars($t[1]); ?></span></div>
      <?php endforeach; ?>
    </div>

  </header>

  <main>

    <?php /* MISSION. It was a centred paragraph under a picture, with no
             link in it -- 371 characters and nothing to do. "Mission" is
             also an inward-facing word: nobody arrives wanting to read
             one. The art is now the ground rather than an illustration
             underneath, the claim is a sentence instead of a statement of
             intent, and there is finally somewhere to go from here. */ ?>
    <section id="mission" class="hp-layer hp-mission">
      <div class="wrap">
        <span class="hp-kick">Why this exists</span>
        <h2>Artists get a stage.<br>Collectors get a reason to come back.</h2>
        <p class="hp-say">Skulliance connects collectors with the artists and projects behind the
           art, and then gives the art something to do - staking, missions, games, a marketplace.
           Led by Oculus Orbus, a collector and developer, and built on Cardano with the XRP
           Ledger alongside it.</p>
        <p style="margin-top:22px;"><a class="hp-cta hp-secondary" href="#artists">Meet the artists</a></p>
      </div>
    </section>

    <?php /* GAMES. Seven cards of identical weight meant a visitor had to
             evaluate all seven to find one, which is the same as evaluating
             none. One is featured at full width with its screenshot, and
             the other six sit under it as compact tiles -- a glance now
             yields a decision instead of a reading task.

             DHC Fighters leads because it is the newest, the only one that
             is a build-and-battle rather than a score chase, and the one
             with a page of its own to land on. */ ?>
    <section id="games" class="hp-layer alt">
      <div class="wrap">
        <span class="hp-kick">Free browser games</span>
        <h2>Seven games. No download, no signup.</h2>
        <p class="hp-say">Every one runs in any browser on a phone, tablet or desktop. Log in and
           they play with characters from your own collection, and your scores climb the
           leaderboards.</p>

        <div class="hp-feature">
          <a href="https://www.skulliance.io/staking/dhcgame.php" aria-label="Play DHC Fighters">
            <img src="https://www.skulliance.io/staking/images/dhcgame.png"
                 alt="DHC Fighters - two Crews of three facing each other across a gem board"
                 loading="lazy" decoding="async">
          </a>
          <div>
            <span class="hp-tag">Newest</span>
            <h3>DHC Fighters - build and battle</h3>
            <p class="hp-say">Assemble a Fighter from ten trait slots, field a Crew of three, and
               take it into the Arena - a match 3 battler where rank decides who takes the hit
               and rarity does not decide the fight. Browse the whole collection while you plan.</p>
            <p style="margin-top:18px;"><a class="hp-cta" href="https://www.skulliance.io/staking/dhcgame.php">Play DHC Fighters</a></p>
          </div>
        </div>

        <div class="hp-more">
          <a class="hp-mini" href="https://www.skulliance.io/staking/match3rpg.php">
            <img src="https://www.skulliance.io/staking/images/screenshots/monstrocity.png" alt="Monstrocity" loading="lazy" decoding="async">
            <b>Monstrocity</b><span>Match 3 RPG - stats, bosses, 35+ themes</span>
          </a>
          <a class="hp-mini" href="https://www.skulliance.io/staking/skullswap.php">
            <img src="https://www.skulliance.io/staking/images/skullswap.png" alt="Skull Swap" loading="lazy" decoding="async">
            <b>Skull Swap</b><span>Match 3 score chase - 25 moves, chain the bombs</span>
          </a>
          <a class="hp-mini" href="https://www.skulliance.io/staking/cryptcrawlgame.php">
            <img src="https://www.skulliance.io/staking/images/cryptcrawl.png" alt="Crypt Crawl" loading="lazy" decoding="async">
            <b>Crypt Crawl</b><span>Scoundrel-style card crawl in Crypties art</span>
          </a>
          <a class="hp-mini" href="https://www.skulliance.io/staking/cryptconquestgame.php">
            <img src="https://www.skulliance.io/staking/images/cryptconquest.png" alt="Crypt Conquest" loading="lazy" decoding="async">
            <b>Crypt Conquest</b><span>Regicide-style solo - dethrone all 12 courts</span>
          </a>
          <a class="hp-mini" href="https://www.skulliance.io/staking/skullracergame.php">
            <img src="https://www.skulliance.io/staking/racing/images/screenshot.png" alt="Skull Racer" loading="lazy" decoding="async">
            <b>Skull Racer</b><span>Retro arcade racer - three laps, ghost cars</span>
          </a>
          <a class="hp-mini" href="https://www.skulliance.io/staking/guardiansgame.php">
            <img src="https://www.skulliance.io/staking/images/guardians.png" alt="Realm Guardians" loading="lazy" decoding="async">
            <b>Realm Guardians</b><span>Tower defense on your own realm</span>
          </a>
        </div>
      </div>
    </section>

    <!-- Founding Artists -->
    <?php /* ARTISTS. "Founding Artists" is a label; a visitor who does not
             already know these names learns nothing from it. The heading
             now states the fact that makes them worth looking at -- six
             people started this, and holding any of their work pays. */ ?>
    <section id="artists" class="hp-layer">
      <div class="wrap">
        <span class="hp-kick">Founding artists</span>
        <h2>Six artists started this.</h2>
        <p class="hp-say">They specialise in skull art on Cardano and came together to build somewhere
           their collectors would want to stay. Hold any of their work and it earns from the first
           night, whether or not you ever play anything.</p>
        <ul class="hp-logos hp-founding">
          <li><a href="https://x.com/SinderSkullz" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/sinderskullz.png" alt="Sinder Skullz" loading="lazy" decoding="async"><span class="hp-logo-name">Sinder Skullz</span></a></li>
          <li><a href="https://x.com/Nft4R" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/kimosabe.png" alt="Kimosabe Art" loading="lazy" decoding="async"><span class="hp-logo-name">Kimosabe Art</span></a></li>
          <li><a href="https://x.com/cryptiesnft" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/crypties.png" alt="Crypties" loading="lazy" decoding="async"><span class="hp-logo-name">Crypties</span></a></li>
          <li><a href="https://x.com/GalacticoNFT" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/galactico.png" alt="Galactico" loading="lazy" decoding="async"><span class="hp-logo-name">Galactico</span></a></li>
          <li><a href="https://x.com/ohh_meed" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/ohhmeed.png" alt="Ohh Meed" loading="lazy" decoding="async"><span class="hp-logo-name">Ohh Meed</span></a></li>
          <li><a href="https://x.com/haveyouseenhype" target="_blank" rel="noopener"><img src="https://www.skulliance.io/staking/images/projects/hype.png" alt="H.Y.P.E." loading="lazy" decoding="async"><span class="hp-logo-name">H.Y.P.E.</span></a></li>
        </ul>
      </div>
    </section>

    <!-- Staking Partners -->
    <?php
    /*
     * PARTNERS. The count IS the argument and it was buried in a sentence,
     * so it is the heading now -- but it is READ FROM THE DATABASE, never
     * written here.
     *
     * A hardcoded 27 was wrong the moment it was typed: that is the length
     * of $hp_partners, which is the list of projects that have a FLYER
     * IMAGE, not the list of projects that stake. Not every partner has
     * artwork on file, so the marquee has always been a subset and any
     * number taken from it understates the platform.
     *
     * hp_stat_artists() counts DISTINCT project_id in collections, which
     * is the real answer and stays right as artists join. It falls back to
     * a wording with no number rather than to a wrong one -- "0 projects
     * stake here" on the page arguing that projects stake here would be
     * the worst sentence on the site.
     */
    $partner_n = isset($stat_artists) ? (int)$stat_artists : hp_stat_artists();
    ?>
    <section id="partners" class="hp-layer alt">
      <div class="wrap">
        <span class="hp-kick">Partner projects</span>
        <h2><?php echo $partner_n > 6
              ? number_format($partner_n) . ' artists and projects stake here.'
              : 'Artists keep joining.'; ?></h2>
        <p class="hp-say">Six founded it; the rest were invited. Skulliance opened partner staking to
           other Cardano artists and projects, and now to the XRP Ledger - their holders earn points,
           redeem the same incentives and climb the same leaderboards. A few below, not all of them.</p>
        <?php
        // Partner flyers, split half/half across two counter-scrolling
        // marquee rows so the majority of artists register at a glance.
        // Each track holds its list twice for the seamless -50% translate
        // loop (second pass aria-hidden). Image filenames live under
        // /staking/images/projects/.
        $hp_partners = [
            ['nemonium.jpg',     'Nemonium',                'https://x.com/Omen4Omen'],
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

    <?php /* PLATFORM. This was 698 characters of prose followed by TWENTY
             equally-weighted screenshots -- a contact sheet, not a section.
             Twenty thumbnails asking to be read one by one is the same as
             none being read, and the one paragraph explaining them was a
             single sentence with eight clauses in it.

             So: say what you actually DO here, in three, with the three
             best pictures. The remaining screenshots stay, but as a WALL
             that is labelled as evidence of depth rather than as a menu.
             Nothing was deleted; the twenty stopped competing with the
             three that matter. */ ?>
    <section id="platform" class="hp-layer">
      <div class="wrap">
        <span class="hp-kick">The staking platform</span>
        <h2>Your NFTs earn while you do nothing.<br>Then you spend it.</h2>
        <p class="hp-say">Log in with Discord, connect a Cardano or XRPL wallet, and qualifying
           NFTs start earning nightly. No gas, no transactions, nothing leaves your wallet.</p>

        <div class="hp-does">
          <div class="hp-do">
            <a href="https://www.skulliance.io/staking/"><img src="https://www.skulliance.io/staking/images/screenshots/dashboard.png" alt="" loading="lazy" decoding="async"></a>
            <h3>Earn nightly</h3>
            <p>Every qualifying NFT pays points each night at its collection's rate. Daily rewards
               and streaks stack on top.</p>
          </div>
          <div class="hp-do">
            <a href="https://www.skulliance.io/staking/missions.php"><img src="https://www.skulliance.io/staking/images/screenshots/missions.png" alt="" loading="lazy" decoding="async"></a>
            <h3>Send them out</h3>
            <p>Idle missions, Realms to build, Gauntlets to run, Diamond Skull delegation that pays
               CARBON and crafts DIAMOND.</p>
          </div>
          <div class="hp-do">
            <a href="https://www.skulliance.io/staking/store.php"><img src="https://www.skulliance.io/staking/images/screenshots/store.png" alt="" loading="lazy" decoding="async"></a>
            <h3>Spend the points</h3>
            <p>The staking store carries incentives you cannot get anywhere else, and the
               leaderboards keep score.</p>
          </div>
        </div>

        <p class="hp-wall-label">And the rest of it</p>
        <?php
        // Platform screenshot cards. Images live on the server at
        // /staking/images/screenshots/ (uploaded via FTP, not in the repo).
        $hp_shot_base = 'https://www.skulliance.io/staking/images/screenshots/';
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
        <div class="hp-wall">
          <?php /* A tile, not a card: the h3 is gone and the name lives in
                   title/alt. Twenty headings in one section is what made
                   this read as twenty things to evaluate. */ ?>
          <?php foreach ($hp_shots as $hp_shot): list($hp_shot_file, $hp_shot_name, $hp_shot_url) = $hp_shot; ?>
          <a href="<?php echo $hp_shot_url; ?>" title="<?php echo htmlspecialchars($hp_shot_name); ?>">
            <img src="<?php echo $hp_shot_base . $hp_shot_file; ?>" alt="<?php echo htmlspecialchars($hp_shot_name); ?>" loading="lazy" decoding="async">
          </a>
          <?php endforeach; ?>
          <!-- Lives directly at /staking/images/ (not the shared screenshots/
               base above the loop uses), so it's its own card rather than
               another $hp_shots row. -->
          <a href="https://www.skulliance.io/staking/cryptcrawlgame.php" title="Crypt Crawl"><img src="https://www.skulliance.io/staking/images/cryptcrawl.png" alt="Crypt Crawl" loading="lazy" decoding="async"></a>
          <a href="https://www.skulliance.io/staking/cryptconquestgame.php" title="Crypt Conquest"><img src="https://www.skulliance.io/staking/images/cryptconquest.png" alt="Crypt Conquest" loading="lazy" decoding="async"></a>
          <!-- Lives under /staking/racing/images/ with the rest of the
               racer's own assets, so like the two above it's its own card
               rather than another $hp_shots row off the shared base. -->
          <a href="https://www.skulliance.io/staking/skullracergame.php" title="Skull Racer"><img src="https://www.skulliance.io/staking/racing/images/screenshot.png" alt="Skull Racer" loading="lazy" decoding="async"></a>
          <a href="https://www.skulliance.io/staking/guardiansgame.php" title="Realm Guardians"><img src="https://www.skulliance.io/staking/images/guardians.png" alt="Realm Guardians" loading="lazy" decoding="async"></a>
          <a href="https://www.skulliance.io/staking/dhcgame.php" title="DHC Fighters"><img src="https://www.skulliance.io/staking/images/dhcgame.png" alt="DHC Fighters" loading="lazy" decoding="async"></a>
        </div>
        <p class="hp-center" style="margin-top: 28px;"><a class="hp-cta" href="https://www.skulliance.io/staking">Start Staking</a></p>
      </div>
    </section>

    <!-- Skull Paper callout -->
    <?php /* SKULL PAPER. A thin band on purpose -- it is a pointer, not a
             pitch, and giving it the same weight as Games would say it
             matters as much. The claim worth making is the unusual one:
             the numbers are real and they are kept honest. */ ?>
    <section id="skull-paper" class="hp-layer alt hp-thin">
      <div class="wrap hp-split">
        <div>
          <span class="hp-kick">The Skull Paper</span>
          <h2>Every rate, written down.</h2>
          <p class="hp-say">Mechanics, rates, points and formulas for all of it - staking, missions,
             Realms, Diamond Skulls, every game, the marketplace. A living guide kept in sync with
             the platform rather than a whitepaper written once.</p>
        </div>
        <div class="hp-split-act">
          <a class="hp-cta" href="https://www.skulliance.io/staking/skullpaper.php">Read the Skull Paper</a>
        </div>
      </div>
    </section>

    <?php /* MEMBERSHIP. Three tiers, 806 characters, and NOT ONE LINK --
             the most conversion-shaped section on the page had nowhere to
             go from it. The heading also led with "Tiers", which is a
             structure, not a reason. It now leads with the thing that
             stops a newcomer bouncing: none of this is required to
             start. */ ?>
    <section id="membership" class="hp-layer">
      <div class="wrap">
        <span class="hp-kick">Membership</span>
        <h2>Staking needs nothing.<br>Membership gets you further.</h2>
        <p class="hp-say">Anyone can stake from day one. The tiers are what unlock the store, the
           premium currency and the deepest reward loop as your collection grows - not a gate on
           getting started.</p>
        <div class="hp-tiers">
          <div class="hp-tier">
            <p class="hp-tier-rank">Tier 1</p>
            <h3>&#x1F6E1;&#xFE0F; Base Member</h3>
            <p>Hold 1 NFT from Sinder Skullz, Kimosabe Art, and Crypties. Base membership isn't required to stake, but it unlocks claiming exclusive incentives from the staking store with the points you accumulate.</p>
          </div>
          <div class="hp-tier">
            <p class="hp-tier-rank">Tier 2</p>
            <h3>&#x2694;&#xFE0F; Elite Member</h3>
            <p>Hold at least 1 NFT from every founding artist. Elite members can convert equal parts of founding-artist points into DIAMOND - the premium currency that can purchase any store reward at a discount or premium.</p>
          </div>
          <div class="hp-tier hp-tier-top">
            <p class="hp-tier-rank">Tier 3</p>
            <h3>&#x1F48E; Inner Circle</h3>
            <p>Elite members who also hold a Diamond Skull NFT. The Inner Circle earns CARBON delegation rewards plus DIAMOND from nightly emissions and crafting - the deepest reward loop on the platform.</p>
          </div>
        </div>
        <p style="margin-top:26px;"><a class="hp-cta hp-secondary" href="https://www.skulliance.io/staking/">Start at tier zero</a></p>
      </div>
    </section>

    <?php /* TEAM. Thin, like the Skull Paper -- four faces do not need a
             full band, and giving them one would say the team matters
             more than the games. */ ?>
    <section id="team" class="hp-layer alt hp-thin">
      <div class="wrap">
        <span class="hp-kick">Who builds it</span>
        <h2>A small team, in public.</h2>
        <p class="hp-say">Dedicated to elevating skull artists and bringing real utility to the
           collectors who back them. Everyone here is reachable.</p>
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
    <?php /* THE CLOSE. It asked for three things at once -- Discord, games,
             merch -- which is the same mistake the hero made and the same
             answer: a page that ends by offering a choice ends without an
             action. It closes on the one thing that costs a visitor
             nothing, and repeats the headline's promise so the page ends
             where it began. */ ?>
    <section class="hp-layer hp-close">
      <div class="wrap hp-center">
        <img class="hp-final-art" src="https://www.skulliance.io/staking/images/skulliance-cardano-logo.png" alt="" width="1500" height="1674" loading="lazy" decoding="async">
        <h2 class="hp-close-h">Still just sitting there?</h2>
        <p class="hp-say hp-close-p">Play something first - it costs nothing and needs no wallet. The
           staking, the artists and the rest of it will still be here when you want them.</p>
        <div class="hp-ctas" style="justify-content:center;">
          <a class="hp-cta" href="#games">Play a game, free</a>
          <a class="hp-cta hp-secondary" href="https://discord.gg/JqqBZBrph2">Join the Discord</a>
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
      <a href="https://www.skulliance.io/staking/cryptcrawlgame.php">Crypt Crawl</a>
      <a href="https://www.skulliance.io/staking/skullpaper.php">Skull Paper</a>
      <a href="https://discord.gg/JqqBZBrph2">Discord</a>
      <a href="https://www.x.com/skulliance">X</a>
    </div>
    <p>Skulliance &middot; Copyright &copy; <span id="hp-year"></span></p>
  </footer>

  <script>
    document.getElementById('hp-year').textContent = new Date().getFullYear();

  </script>
</body>
</html>

# Monstrocity

Monstrocity is Skulliance's **Match 3 RPG** - a single-player campaign that pairs match-3 puzzle mechanics with turn-based combat. Match tiles to attack; reduce your opponent to zero health before they do the same to you.

## Playing

* Choose a **character theme** from one of 35+ skins drawn from NFT collections across every chain Skulliance supports (core artists, partners, and bonus themes). Themes are cosmetic - no specific NFT is required to play.
* Progress through a **28-level campaign** of escalating difficulty.
* Each level is a match-3 battle: you and an AI opponent alternate turns on a shared board, and the board state persists between turns.
* Win a level by draining the opponent's health before yours runs out.

## Character Traits

Each character carries combat stats: **health, strength, speed, tactics, size, and a power-up**.

* **Speed** decides turn order (ties broken by strength).
* **Size** is a trade-off: larger characters gain health but lose tactics; smaller characters lose health but gain tactics.
* **Type** sets base health: Leader 100, Base 85, Battle Damaged 70. Size then scales it.

### Playing your own NFTs

If you own NFTs from the collections behind a theme, they show up as characters you can field alongside the default roster, on **Cardano, Solana, XRPL and Polygon** alike.

Stats for those characters are worked out from the NFT's own on-chain identifier, so every NFT always has the same stats and no two holders can argue about it. The original **Monstrocity** collection is the exception: its stats were written into the NFTs when they were minted, and the game reads those directly.

### Collections whose traits decide the card

A theme can be any collection on the platform, and most of them have nothing a
fighting game could read — so for those, the identifier is all there is. Some
collections are different, and those are **mapped**: the game reads their actual
traits, and rarity is measured against the whole collection on-chain rather than
against whoever happens to be staked.

**The Ancestors** is the first. 1,225 of them, ten trait slots, 432 distinct
values, and the card follows what the Ancestor is actually wearing:

| Stat | What decides it |
|---|---|
| **Size** | Species. Dwarf is Small, Human and Elf Medium, Orc and the Ancients Large |
| **Type** | What is on its head. A crown, a king's or prince's or chief's helm makes a **Leader**; a cursed, bitten or bone one makes it **Battle Damaged** |
| **Strength** | How rare its armament is — Weapon, Additional Weapon and Back Gear together. A Sacred Golden Sword counts for a great deal more than a chicken leg |
| **Tactics** | How rare its Head and Face are. A Shaman Cowl is a sharper mind than Sleepy Blonde |
| **Speed** | Species, *less what it is carrying*. Armour slows you more than a beard does |
| **Power-up** | The earring. Leaf Of Life heals, gold regenerates, iron and bone boost the attack. An Ancestor with no ear falls back to its weapon |

**Speed is the one that is not rarity.** Everything else rewards a rare trait, but
speed rewards travelling light — and rarer Ancestors tend to carry *fewer* traits,
not more. So a fully kitted-out legendary does not top every bar, and a lean Elf
with one good blade is a genuinely different card rather than a worse one.

Rarity is taken from the chain once and committed, so it never shifts under you:
the same Ancestor plays the same card in everybody's roster, forever, whether or
not it has ever been staked.

## Progress & Scoring

* Your campaign **progress is saved** and can be resumed; you can also start fresh.
* Score accumulates from your matches and how far you advance.
* The **monthly leaderboard** ranks players by **max level reached, then score**.

## Rewards

The monthly Monstrocity leaderboard pays out **30,000 CLAW + 30,000 CARBON**, divided down the leaderboard by rank (rank 1 gets the full share, rank 2 half, and so on). CLAW is Monstrocity's own points; CARBON can be burned for DIAMOND. See [[staking-crafting]].

> Monstrocity is also referred to as "Match 3 RPG." **Boss Battles** are a related but separate mode built on the same combat engine - see [[games-boss-battles]].

![Monstrocity match 3 RPG battle](https://www.skulliance.io/staking/images/screenshots/monstrocity.png)

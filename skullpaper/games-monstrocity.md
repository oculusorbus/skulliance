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

### Your NFT's traits decide its card

**28 collections, 101,219 NFTs**, play by their own traits rather than by a hash
of their identifier. Rarity is measured against the **whole collection on chain**,
not against whoever happens to be staked, so your card is fixed from the day the
collection minted and the same NFT plays the same way in everybody's roster.

Most of them, 24, get the **general reading**, which makes no claim about what a
trait *means* — only about how unusual it is:

| Stat | What decides it |
|---|---|
| **Strength** | Your rarest single trait. One standout piece |
| **Tactics** | Your median trait. How good your typical piece is |
| **Speed** | The inverse of your total rarity, less what you are carrying |
| **Size** and **Type** | Overall rarity. The rarest wear the crown |
| **Power-up** | Keyed on your rarest trait, so two NFTs with the same standout piece share a charm |

Speed deliberately pulls the other way. Rare traits hit harder and think faster;
common traits move quicker. There is no single best card — a plain NFT is a fast
one, not a worthless one.

Slots that are **identifiers rather than traits** are ignored: a serial number, or
a background nobody else has, says nothing about what an NFT *is*, and counting it
would make almost every holder's rarest trait a one-of-one.

**A collection whose traits cannot tell holders apart keeps the hash**, which is
the one case where the hash is the better answer. Some collections hand out the
same handful of trait combinations to everybody — five evenly-split values here,
four there, and the whole collection comes down to twenty possible cards. Reading
those traits would give a holder *less* to distinguish their NFT by than its
identifier already does, so those collections carry on as they were. 23 of the
51 collections analysed sit in that group for one reason or another.

**Four collections are authored** instead, where the traits say something a
fighting game can use directly — The Ancestors, Hellknights, Disco Solaris and
Moebius Pioneers.

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

#### Hellknights

The second authored collection, and its traits already read like a character
sheet. The **chain** is a rank ladder, so a Lord or an Ancient fields as a
**Leader**; **wings** decide speed, and the same elaborate **armour** that raises
strength takes speed back; **horns** set the frame, with Minotaur, Carnage, Maul
and Underworld the big ones; **weapon** and **armour** rarity make strength,
**eyes** and **helmet** make tactics, and the seven **insignia** houses — evenly
spread, so they carry no rarity at all — decide the charm.

Torn wings and mortal wounds make a knight **Battle Damaged**. A bloodied weapon
does not: it prefixes most of the armoury and says something about the blade
rather than the one holding it.

#### Disco Solaris

A nightlife collection, and its vocabulary was built for this. **The moon phase
is your frame** — a New Moon is Small, a Full Moon is Large. **A uniform is a
rank**: Police Officers, Sergeants, Lieutenants and the Space Suits field as
**Leaders**, while the 607 holding Fans do not. **A bandage on the cheek** makes
you Battle Damaged, which is the only collection so far where the artwork
literally shows the injury.

Strength is your outerwear and body, tactics your eyes and glasses. Speed is your
headphones, less what you are wearing — on means you are in the groove, and a top
hat and a heavy coat are not what you dance in. Your power-up is the mark on your
cheek: a Heart heals, a Bandage regenerates, a Kiss Mark boosts the attack. Half
of them have no cheek mark, so the jewellery answers for the rest.

A bandaged officer is still an officer. Rank beats damage.

#### Moebius Pioneers

The same universe, and the one that took the most work to read. Its traits carry
an edition number — `Black Coat - New #16`, `Choker 2 #31` — so the same coat
appeared under hundreds of names and every Pioneer looked like it was wearing a
one-of-one. Strip the edition and the real collection appears: the body slot goes
from 718 values to **14**, the ear jewellery from 155 to **9**.

**Your backpack is your speed**, matched on the family rather than the colour: a
**Flying Board Premium** is the fastest thing in the collection, a standard board
next, a Pinwheel beats walking, and a Sweeper's kit is heavy. **What you are
wearing is your silhouette** — a Corpo Suit or Sweeper Armor is Large, nothing at
all is Small. **OG gear** and the Moonlight Radio Pendant are the originals and
field as Leaders. Smudges, neck stitches, grease, patches and the Damaged Bionic
Hand make you Battle Damaged: it is a working crew and it shows on them.

Your power-up is your face tattoo, and where there is none, your lips.

#### Collections that still hash

Eighteen are left on the old identifier hash, and for a reason rather than an
oversight. Eight carry no readable traits at all — only a name, a description and
an image. Seven have exactly one usable trait slot, where strength, tactics and
speed would all be the same number wearing three hats, which is worse than a hash
rather than better. Three have no **rarity** to read: their traits are spread
evenly enough that every NFT scores the same, so banding them would put the whole
collection on one line. **Drop Ship** is the clearest of those — `Character` is
twenty values at about sixteen each, and Rank and Level are perfectly even. It
wants an authored map rather than a rarity one, and will get one.

## Progress & Scoring

* Your campaign **progress is saved** and can be resumed; you can also start fresh.
* Score accumulates from your matches and how far you advance.
* The **monthly leaderboard** ranks players by **max level reached, then score**.

## Rewards

The monthly Monstrocity leaderboard pays out **30,000 CLAW + 30,000 CARBON**, divided down the leaderboard by rank (rank 1 gets the full share, rank 2 half, and so on). CLAW is Monstrocity's own points; CARBON can be burned for DIAMOND. See [[staking-crafting]].

> Monstrocity is also referred to as "Match 3 RPG." **Boss Battles** are a related but separate mode built on the same combat engine - see [[games-boss-battles]].

![Monstrocity match 3 RPG battle](https://www.skulliance.io/staking/images/screenshots/monstrocity.png)

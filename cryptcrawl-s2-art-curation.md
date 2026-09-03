# Crypt Crawl — Season 2 Card Art Curation

Reference data + the resulting rank/suit assignment for all 44 card
identities in `CRYPTCRAWL_CARD_ART` (db.php), resolved by
`cryptcrawlGetCardArt()`. Curated 2026-09-03 from real on-chain data, the
same idea as Crypt Conquest's own [[cryptconquest-s1-art-curation]] -- rarer
pieces on higher ranks -- but built specifically around Crypt Crawl's own
shape: only Clubs and Spades ever render Crypties art in-game (Diamonds/
Hearts show a plain black card with a generic weapon/medkit icon, see
`cryptcrawl.php`'s `.cc-card-icon-face`), and unlike Conquest's S1 pool this
one is also aligned by animal species, not rarity alone.

## Where the data came from

Pulled straight from real on-chain CIP-25 metadata via Koios's `asset_info`
endpoint -- see `crypties-s2-rarity-report.php` (one-off script, still in the
repo, safe to re-run if the wallet's holdings change). Single wallet
(`CRYPTCRAWL_ART_USER_ID`), matching `cryptcrawlGetCardArt()`'s own existing
single-wallet scope -- unlike Conquest's S1 curation, there's no second
"backup" wallet here. The script also scans the wallet's full on-chain
holdings via Koios `account_assets`, not just what's synced into the
platform's own `nfts` table; this run found 0 pieces held but unsynced, so
the DB set was already complete.

**108 NFTs total.** 100 use the standard trait schema (`aura`, `background`,
`carcass`, `mantle`, `rarity`, `trinket`); 8 are special collab/chimera
1-of-1s using a different schema (`project`, `rarity`, `subset`, `variant` --
no `carcass`, i.e. no species signal at all). Those 8 are the same pieces
already referenced by name in the *previous* version of this array's own
comments (curated by eye before this data-driven pass existed).

## Rarity tiers (real, on-chain, `attributes.rarity`)

Six tiers exist here, unlike S1's three -- confirmed on-chain, not assumed:

| Tier | Count |
|---|---|
| wtf | 8 |
| mythic | 3 |
| legendary | 21 |
| epic | 17 |
| uncommon | 27 |
| common | 32 |

## Species: the `carcass` trait

No trait is literally named "species" or "head" anywhere in the metadata --
Crypt Crawl's Cryptie heads all sit on a skull, and the trait that actually
determines *which animal's skull* turned out to be `carcass`, despite its
non-obvious flavor-word values (`bloodied`, `cobalt`, `wild`, etc. -- nothing
in the string itself hints at species). Found by process of elimination
(`aura`'s creature-adjacent-sounding values like `vamp`/`snaggletooth`/`devil`
were the first guess, disproven by visual inspection -- same `aura` value did
NOT share a consistent skull shape) and confirmed two ways: sample images
across every `carcass` value inspected directly, and cross-checked against
the community tool `cnft.tools/cryptiess2` (the owner logged in with their
own wallet, filtering to the same 108-piece holdings) -- filtering by a single
`carcass` value there shows visually identical skulls every time.

| Species | `carcass` values | Count (100 standard-schema pieces) |
|---|---|---|
| Monkey | cobalt, shroud, spores, neta | 33 |
| Tiger | tangerine, frost, wild, plated, adored | 34 |
| Ram | bloodied, nether, neon, shiny | 23 |
| Bear | tattered, conduct | 10 |

## Suit assignment: Clubs = monkey, Spades = ram

Crypt Crawl's monster suits aren't the same shape as Conquest's number
cards: Clubs and Spades each need 13 identities (ranks 2-14), and only
those two suits ever show Crypties art at all (Diamonds/Hearts render a
generic icon, never NFT art -- see above). That reduces "align suits with
animals" to picking exactly 2 of the 4 species for exactly those 2 suits.
Bear (10 owned) can't cover a 13-rank suit outright, so it was ruled out;
monkey and ram were picked over tiger despite tiger having the largest pool
(34) specifically because tiger's only real standouts -- its 2 Mythic pieces
-- were more valuable as a *guest* card seeded into each of the other two
suits (see below) than as a same-species suit of their own.

## Rank assignment: WTF on the face cards, tiger Mythics as the guest slot

None of the 8 WTF pieces carry `carcass` -- they're the special collab/
chimera schema, with no species signal at all. Sorted onto a suit by visual
fit instead, reviewing each piece's actual art:

* **Ram-skulled** (clear curled horns): `#10316`, `#10873`, `#11120`,
  `#11903` -> **Spades**.
* **No animal at all** (human/doll or tech heads -- neutral, no anti-fit
  either way): `#10208`, `#10330`, `#10340`, `#11731` -> **Clubs**.

All 8 landed on Ace/King/Queen/Jack across both suits, per the owner's own
exact card-by-card assignment. Rank 10 on both suits is a deliberate species
exception: the collection's only two Mythic-tier pieces are both **tiger**
(`#10566` "wild", `#10753` "frost") -- neither monkey nor ram has a native
Mythic of its own that could fill this slot on both suits, so one was seeded
into each suit as a guest card, again by the owner's own pick (Spades:
`#10753`; Clubs: `#10566`). Spades additionally has its own native Mythic
ram (`#11216`) at rank 9, since ram has one and monkey doesn't. Everything
below that is each suit's own Legendary tier (then Epic to round out
Spades, since ram's Legendary pool is smaller), pulled straight from that
suit's own species pool, ties broken by Cryptie #.

| Suit | WTF (Ace-Jack) | Mythic | Legendary | Epic | Pool used / owned |
|---|---|---|---|---|---|
| Clubs (monkey) | 4 (guest species, none native) | 1 (guest tiger) | 8 of 9 | — | 13 / 33 |
| Spades (ram) | 4 (native species) | 2 (1 native + 1 guest tiger) | 6 of 6 | 1 of 3 | 13 / 23 |

Diamonds (weapons) and Hearts (potions) never render Crypties art in-game at
all, so their 18 identities carry no rarity or species curation -- just
leftover monkey/ram pieces not already used above (mostly monkey's spare
Epic/Uncommon tier), chosen only to keep every one of the 44 slots pointing
at a distinct NFT.

## Card names

The card label shown in-game (`.cc-card-label`, normally the generic "enemy"
every other type keeps) is a flavor name instead for monster cards --
`cryptcrawlMonsterName()` in db.php, backed by `CRYPTCRAWL_MONSTER_NAMES`.
Default is the suit's species -- **Cursed Ape** (Clubs) / **Cursed Ram**
(Spades) -- overridden only for the 8 WTF cards and both guest-tiger 10s,
since those specific pieces don't actually match their suit's species:

| Card(s) | Name | Why |
|---|---|---|
| `C14` | Mardi Gras | Cryptie #10340's own `variant`, "mardi gras clementine" |
| `C13` | Boombox | Cryptie #10208's own `variant` |
| `C12` | Horny | Cryptie #11731's own `variant` |
| `C11` | Static | Cryptie #10330's own `variant`, "static twist" (withspaces collab) |
| `C10`, `S10` | Cursed Tiger | both guest Mythic pieces are tiger, not the suit's own species |
| `S14`, `S13`, `S12`, `S11` | Chimera | all 4 are the real `subset: chimera` pieces |

Everything else -- both suits' 9/legendary/epic ranks, and `S9`'s native
Mythic ram -- keeps the per-suit default (Cursed Ape / Cursed Ram), since
those cards genuinely are that species.

## The 44 picks

| Key | Suit | Rank | Name | NFT | Rarity | Species/Note |
|---|---|---|---|---|---|---|
| `C14` | Clubs | Ace | Mardi Gras | Cryptie #10340 | wtf | special (Never Engine collab, "mardi gras clementine") |
| `C13` | Clubs | King | Boombox | Cryptie #10208 | wtf | special (Ada Dolls collab, "boombox") |
| `C12` | Clubs | Queen | Horny | Cryptie #11731 | wtf | special (Ada Dolls collab, "horny") |
| `C11` | Clubs | Jack | Static | Cryptie #10330 | wtf | special (withspaces collab, "static twist") |
| `C10` | Clubs | 10 | Cursed Tiger | Cryptie #10566 | mythic | tiger (guest -- no monkey-native mythic) |
| `C9` | Clubs | 9 | Cursed Ape | Cryptie #10203 | legendary | monkey |
| `C8` | Clubs | 8 | Cursed Ape | Cryptie #10351 | legendary | monkey |
| `C7` | Clubs | 7 | Cursed Ape | Cryptie #10823 | legendary | monkey |
| `C6` | Clubs | 6 | Cursed Ape | Cryptie #11097 | legendary | monkey |
| `C5` | Clubs | 5 | Cursed Ape | Cryptie #11470 | legendary | monkey |
| `C4` | Clubs | 4 | Cursed Ape | Cryptie #11552 | legendary | monkey |
| `C3` | Clubs | 3 | Cursed Ape | Cryptie #11592 | legendary | monkey |
| `C2` | Clubs | 2 | Cursed Ape | Cryptie #11753 | legendary | monkey |
| `S14` | Spades | Ace | Chimera | Cryptie #11120 | wtf | special (Chimera, "the one") |
| `S13` | Spades | King | Chimera | Cryptie #10873 | wtf | special (Chimera, "summon morado") |
| `S12` | Spades | Queen | Chimera | Cryptie #11903 | wtf | special (Chimera, "tribe saffron") |
| `S11` | Spades | Jack | Chimera | Cryptie #10316 | wtf | special (Chimera, "sketch platinum") |
| `S10` | Spades | 10 | Cursed Tiger | Cryptie #10753 | mythic | tiger (guest) |
| `S9` | Spades | 9 | Cursed Ram | Cryptie #11216 | mythic | ram (native) |
| `S8` | Spades | 8 | Cursed Ram | Cryptie #10552 | legendary | ram |
| `S7` | Spades | 7 | Cursed Ram | Cryptie #10760 | legendary | ram |
| `S6` | Spades | 6 | Cursed Ram | Cryptie #11279 | legendary | ram |
| `S5` | Spades | 5 | Cursed Ram | Cryptie #11356 | legendary | ram |
| `S4` | Spades | 4 | Cursed Ram | Cryptie #11543 | legendary | ram |
| `S3` | Spades | 3 | Cursed Ram | Cryptie #11566 | legendary | ram |
| `S2` | Spades | 2 | Cursed Ram | Cryptie #11221 | epic | ram |
| `D10` | Diamonds | 10 | *(weapon, generic label)* | Cryptie #11862 | legendary | monkey (leftover; art never shown) |
| `D9` | Diamonds | 9 | *(weapon, generic label)* | Cryptie #10020 | epic | monkey (leftover) |
| `D8` | Diamonds | 8 | *(weapon, generic label)* | Cryptie #10431 | epic | monkey (leftover) |
| `D7` | Diamonds | 7 | *(weapon, generic label)* | Cryptie #11009 | epic | monkey (leftover) |
| `D6` | Diamonds | 6 | *(weapon, generic label)* | Cryptie #11225 | epic | monkey (leftover) |
| `D5` | Diamonds | 5 | *(weapon, generic label)* | Cryptie #11984 | epic | monkey (leftover) |
| `D4` | Diamonds | 4 | *(weapon, generic label)* | Cryptie #11385 | epic | ram (leftover) |
| `D3` | Diamonds | 3 | *(weapon, generic label)* | Cryptie #11854 | epic | ram (leftover) |
| `D2` | Diamonds | 2 | *(weapon, generic label)* | Cryptie #10218 | uncommon | monkey (leftover) |
| `H10` | Hearts | 10 | *(medkit, generic label)* | Cryptie #10444 | uncommon | monkey (leftover) |
| `H9` | Hearts | 9 | *(medkit, generic label)* | Cryptie #10462 | uncommon | monkey (leftover) |
| `H8` | Hearts | 8 | *(medkit, generic label)* | Cryptie #10896 | uncommon | monkey (leftover) |
| `H7` | Hearts | 7 | *(medkit, generic label)* | Cryptie #10953 | uncommon | monkey (leftover) |
| `H6` | Hearts | 6 | *(medkit, generic label)* | Cryptie #11376 | uncommon | monkey (leftover) |
| `H5` | Hearts | 5 | *(medkit, generic label)* | Cryptie #11462 | uncommon | monkey (leftover) |
| `H4` | Hearts | 4 | *(medkit, generic label)* | Cryptie #11656 | uncommon | monkey (leftover) |
| `H3` | Hearts | 3 | *(medkit, generic label)* | Cryptie #11771 | uncommon | monkey (leftover) |
| `H2` | Hearts | 2 | *(medkit, generic label)* | Cryptie #11961 | uncommon | monkey (leftover) |

## Full 108-piece dataset

Every S2 NFT in the wallet, with its full trait read -- kept here so this
page is a complete standalone reference; re-running
`crypties-s2-rarity-report.php` only needed if the wallet's holdings change.
Special (no-`carcass`) pieces show their `subset`/`variant`/`project` in the
species column instead.

| NFT | Rarity | Species | Carcass | Aura | Mantle | Trinket | Background |
|---|---|---|---|---|---|---|---|
| Cryptie #10208 | wtf | special | subset=collab variant=boombox project=ada dolls | | | | |
| Cryptie #10316 | wtf | special | subset=chimera variant=sketch platinum project=None | | | | |
| Cryptie #10330 | wtf | special | subset=collab variant=static twist project=withspaces | | | | |
| Cryptie #10340 | wtf | special | subset=collab variant=mardi gras clementine project=never engine | | | | |
| Cryptie #10873 | wtf | special | subset=chimera variant=summon morado project=None | | | | |
| Cryptie #11120 | wtf | special | subset=chimera variant=the one project=None | | | | |
| Cryptie #11731 | wtf | special | subset=collab variant=horny project=ada dolls | | | | |
| Cryptie #11903 | wtf | special | subset=chimera variant=tribe saffron project=None | | | | |
| Cryptie #10566 | mythic | tiger | wild | vine | ruin | idol | growth |
| Cryptie #10753 | mythic | tiger | frost | wired | blizzard | mini-tiger | ice |
| Cryptie #11216 | mythic | ram | shiny | decorative | prized | dagger | golden |
| Cryptie #10203 | legendary | monkey | shroud | vamp | shock | mini-ape | riddle |
| Cryptie #10351 | legendary | monkey | shroud | vamp | shock | mini-ape | sea |
| Cryptie #10464 | legendary | tiger | frost | wired | blizzard | mini-tiger | paprika |
| Cryptie #10552 | legendary | ram | nether | mayhem | demi | chalice | grunge |
| Cryptie #10760 | legendary | ram | shiny | decorative | demi | dagger | golden |
| Cryptie #10823 | legendary | monkey | neta | tag | hermano | banana | feast |
| Cryptie #11097 | legendary | monkey | shroud | vamp | shock | mini-ape | feast |
| Cryptie #11141 | legendary | tiger | wild | scorpion | pimp | moon | quasar |
| Cryptie #11149 | legendary | tiger | plated | snaggletooth | god | ace | overkill |
| Cryptie #11279 | legendary | ram | shiny | decorative | prized | dagger | crimson |
| Cryptie #11332 | legendary | tiger | adored | petal | champ | rose | slay |
| Cryptie #11356 | legendary | ram | shiny | decorative | sacrifice | dagger | golden |
| Cryptie #11380 | legendary | bear | tattered | snaggletooth | god | ace | foam |
| Cryptie #11470 | legendary | monkey | cobalt | king | seeker | ada | sea |
| Cryptie #11472 | legendary | tiger | frost | wired | blizzard | mini-tiger | growth |
| Cryptie #11543 | legendary | ram | neon | gamer | prized | onigiri | grunge |
| Cryptie #11552 | legendary | monkey | neta | vamp | hermano | banana | riddle |
| Cryptie #11566 | legendary | ram | nether | mayhem | cyber | chalice | cursed |
| Cryptie #11592 | legendary | monkey | cobalt | king | tribal | mini-ape | sea |
| Cryptie #11753 | legendary | monkey | neta | tag | tribal | banana | riddle |
| Cryptie #11862 | legendary | monkey | cobalt | vamp | shock | mini-ape | haunt |
| Cryptie #10020 | epic | monkey | cobalt | king | shock | banana | sea |
| Cryptie #10178 | epic | tiger | frost | mantra | ruin | flame | paprika |
| Cryptie #10252 | epic | tiger | adored | snaggletooth | streamer | ace | slay |
| Cryptie #10360 | epic | tiger | tangerine | mantra | torched | mini-tiger | quasar |
| Cryptie #10365 | epic | tiger | adored | combatant | streamer | ace | slay |
| Cryptie #10431 | epic | monkey | cobalt | king | seeker | banana | sea |
| Cryptie #10473 | epic | tiger | tangerine | vine | blizzard | idol | growth |
| Cryptie #10571 | epic | bear | tattered | snaggletooth | god | rose | slay |
| Cryptie #10593 | epic | tiger | frost | mantra | blizzard | mini-tiger | quasar |
| Cryptie #11009 | epic | monkey | cobalt | tag | tribal | ada | riddle |
| Cryptie #11203 | epic | tiger | wild | vine | torched | flame | growth |
| Cryptie #11221 | epic | ram | shiny | decorative | demi | chalice | cursed |
| Cryptie #11225 | epic | monkey | cobalt | tag | tribal | ada | feast |
| Cryptie #11385 | epic | ram | shiny | decorative | cyber | onigiri | grunge |
| Cryptie #11535 | epic | bear | tattered | snaggletooth | smart | rose | overkill |
| Cryptie #11854 | epic | ram | bloodied | devil | sacrifice | chalice | grunge |
| Cryptie #11984 | epic | monkey | shroud | vamp | shock | ada | feast |
| Cryptie #10168 | uncommon | ram | bloodied | gamer | sacrifice | chalice | cursed |
| Cryptie #10218 | uncommon | monkey | spores | tag | seeker | mini-ape | haunt |
| Cryptie #10305 | uncommon | ram | shiny | devil | demi | dagger | crimson |
| Cryptie #10428 | uncommon | tiger | wild | scorpion | ruin | moon | paprika |
| Cryptie #10435 | uncommon | bear | tattered | magnet | smart | protect | toasted |
| Cryptie #10444 | uncommon | monkey | neta | vamp | tribal | mini-ape | sea |
| Cryptie #10462 | uncommon | monkey | neta | king | tribal | mini-ape | haunt |
| Cryptie #10516 | uncommon | ram | bloodied | gamer | demi | hex | cursed |
| Cryptie #10720 | uncommon | tiger | wild | wired | ruin | moon | ice |
| Cryptie #10896 | uncommon | monkey | neta | vamp | shock | ada | sea |
| Cryptie #10953 | uncommon | monkey | spores | king | seeker | ada | riddle |
| Cryptie #10997 | uncommon | bear | conduct | magnet | champ | protect | overkill |
| Cryptie #11074 | uncommon | tiger | frost | vine | torched | mini-tiger | growth |
| Cryptie #11096 | uncommon | bear | conduct | petal | streamer | ace | overkill |
| Cryptie #11206 | uncommon | tiger | wild | mantra | blizzard | mini-tiger | growth |
| Cryptie #11229 | uncommon | ram | nether | gamer | cyber | chalice | crimson |
| Cryptie #11242 | uncommon | tiger | wild | wired | pimp | idol | ice |
| Cryptie #11376 | uncommon | monkey | cobalt | king | shock | fungi | feast |
| Cryptie #11422 | uncommon | tiger | wild | wired | torched | mini-tiger | paprika |
| Cryptie #11462 | uncommon | monkey | spores | tag | seeker | mini-ape | riddle |
| Cryptie #11549 | uncommon | tiger | adored | magnet | god | rose | overkill |
| Cryptie #11656 | uncommon | monkey | cobalt | vamp | tribal | fungi | feast |
| Cryptie #11714 | uncommon | tiger | plated | petal | god | ace | toasted |
| Cryptie #11771 | uncommon | monkey | cobalt | necro | tribal | mini-ape | feast |
| Cryptie #11886 | uncommon | tiger | frost | vine | blizzard | moon | growth |
| Cryptie #11890 | uncommon | ram | shiny | devil | sacrifice | dagger | cursed |
| Cryptie #11961 | uncommon | monkey | cobalt | vamp | hermano | ada | haunt |
| Cryptie #10031 | common | tiger | tangerine | vine | pimp | flame | ice |
| Cryptie #10207 | common | monkey | cobalt | tag | shock | fungi | haunt |
| Cryptie #10588 | common | monkey | cobalt | necro | shock | fungi | riddle |
| Cryptie #10597 | common | tiger | tangerine | scorpion | ruin | mini-tiger | ice |
| Cryptie #10731 | common | tiger | frost | scorpion | torched | moon | growth |
| Cryptie #10777 | common | tiger | wild | scorpion | blizzard | mini-tiger | paprika |
| Cryptie #10857 | common | tiger | adored | petal | god | mini-bear | toasted |
| Cryptie #11071 | common | monkey | cobalt | vamp | seeker | fungi | riddle |
| Cryptie #11226 | common | tiger | tangerine | vine | pimp | idol | ice |
| Cryptie #11258 | common | bear | tattered | combatant | smart | protect | slay |
| Cryptie #11336 | common | ram | neon | mayhem | sacrifice | dagger | grunge |
| Cryptie #11399 | common | ram | nether | devil | cyber | dagger | grunge |
| Cryptie #11407 | common | bear | tattered | petal | god | mini-bear | toasted |
| Cryptie #11416 | common | tiger | frost | mantra | ruin | flame | quasar |
| Cryptie #11429 | common | ram | nether | decorative | sacrifice | onigiri | grunge |
| Cryptie #11439 | common | monkey | neta | necro | shock | ada | feast |
| Cryptie #11448 | common | ram | shiny | mayhem | sacrifice | onigiri | crimson |
| Cryptie #11449 | common | monkey | shroud | king | hermano | ada | feast |
| Cryptie #11487 | common | tiger | wild | scorpion | blizzard | moon | paprika |
| Cryptie #11528 | common | monkey | shroud | necro | seeker | ada | riddle |
| Cryptie #11604 | common | bear | tattered | snaggletooth | smart | protect | slay |
| Cryptie #11675 | common | monkey | cobalt | king | seeker | mini-ape | riddle |
| Cryptie #11678 | common | ram | bloodied | decorative | demi | onigiri | grunge |
| Cryptie #11680 | common | ram | bloodied | decorative | cyber | chalice | golden |
| Cryptie #11691 | common | ram | bloodied | gamer | demi | dagger | grunge |
| Cryptie #11706 | common | monkey | shroud | king | tribal | banana | feast |
| Cryptie #11707 | common | ram | nether | devil | prized | onigiri | grunge |
| Cryptie #11732 | common | tiger | plated | snaggletooth | smart | protect | slay |
| Cryptie #11757 | common | tiger | tangerine | scorpion | blizzard | idol | paprika |
| Cryptie #11812 | common | monkey | spores | king | shock | fungi | riddle |
| Cryptie #11866 | common | bear | conduct | snaggletooth | champ | rose | overkill |
| Cryptie #11907 | common | tiger | tangerine | vine | pimp | mini-tiger | paprika |

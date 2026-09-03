# Crypt Conquest — Season 1 Number-Card Art Curation

Reference data + the resulting rank/suit assignment for the 36 number-card
identities (2-10 x 4 suits) in Crypt Conquest's PLAYER-hand art pool --
`cryptconquestGetCardArtPools()`'s `'player'` key in db.php. Replaces that
pool's auto-assignment (name-sorted, no rarity awareness) with a curated set,
the same idea as Crypt Crawl's own `CRYPTCRAWL_CARD_ART` -- rarer pieces on
higher ranks -- but built from real on-chain data instead of eyeballing.

**Explicitly out of scope, untouched:** the 12 court-card identities (Jack/
Queen/King x 4 suits). Those stay on the platform-wide enemy pool (any public
staker's S1 holdings, see `cryptconquestFetchCourtCandidates()`) -- this
curation only ever touches the two wallets `cryptconquestGetS1ArtPool()`
already draws the PLAYER pool from: `CRYPTCRAWL_ART_USER_ID` ("my
collection") and `CRYPTCONQUEST_S1_EXTRA_USER_ID` ("Dean's collection", the
backup).

## Where the data came from

Nothing in this platform's own DB stores Crypties trait/rarity data. Pulled
straight from real on-chain CIP-25 metadata via Koios's `asset_info`
endpoint -- see `crypties-s1-rarity-report.php` (one-off script, still in the
repo, safe to re-run if the two wallets' holdings change). That script also
scans each wallet's FULL on-chain holdings (not just what's synced into the
platform's own `nfts` table) via Koios `account_assets`, so this reflects the
real complete picture, not just what happened to be staked/verified.

**92 usable S1 NFTs** across both wallets (59 on-chain in the primary wallet,
38 in Dean's, minus overlap/dupes). **5 more excluded on purpose:**
`UltimateCryptie006/047/226/253/282` -- a distinct special sub-type (the ADA
symbol rendered in stacked skulls, animated GIF), not the standard portrait
art card faces need, and known to have rendering problems on several
marketplaces/platforms. Their metadata also didn't resolve cleanly through
the same CIP-25 lookup path the other 92 use (different naming convention --
worth knowing if anyone revisits this later, not worth chasing for this).

## Rarity tiers (real, on-chain, `attributes.rarity`)

Only 3 tiers exist -- **not** Crypt Crawl's WTF/Mythic/Legendary scheme:

| Tier | Count |
|---|---|
| legendary | 7 |
| epic | 36 |
| common | 49 |

## Suit assignment: by `attributes.aura`

Legendary pieces are scarce and concentrated -- only 5 of the 10 aura values
carry any legendary piece at all (geometric=2, teleporting=1, battle=1,
starving=1, crypto=2), so those five had to anchor the four suit groups (with
crypto folded into geometric's group) or a suit would end up with zero
legendary for its top rank. `geometric` -> Diamonds was specifically
requested (tech/digital reads as wealth/modernity, and it's thematically apt
for an NFT game specifically); the other three groupings follow the same
constraint plus loose theme (dark/hunger -> Spades, ethereal/light -> Hearts,
war/smoke -> Clubs).

| Suit | Aura values | Legendary | Epic | Common | Pool size |
|---|---|---|---|---|---|
| Diamonds (D) | geometric + crypto | 4 | 9 | 15 | 28 |
| Hearts (H) | teleporting + shimmering + glowing + summoning | 1 | 14 | 16 | 31 |
| Clubs (C) | battle + smoking | 1 | 7 | 10 | 18 |
| Spades (S) | starving + haunting | 1 | 6 | 8 | 15 |

Every group clears its own 9-slot requirement (ranks 2-10) with real margin
and at least one legendary piece. Grand total checks out exactly against the
real data: 7 legendary + 36 epic + 49 common = 92.

## Rank assignment

Rarest to the top, same logic Crypt Crawl's own curation used: within each
suit's own pool, legendary fills the highest ranks first, then epic, then
common only once the rarer tiers run out -- independently per suit (not one
global pass), since each suit's own supply mix differs. Across all 36 slots
this used **all 7 legendary and 26 of 36 epic pieces**, common only needed
for Clubs-2 and Spades-3/Spades-2 (3 slots) where that suit's epic ran out.
Ties within a tier broken by NFT name, ascending -- arbitrary but stable, no
gameplay or visual reason to prefer one legendary piece's card slot over
another's.

## The 36 picks

| Key | Suit | Rank | NFT | Rarity | Owner |
|---|---|---|---|---|---|
| `D10` | Diamonds | 10 | Cryptie #00891 | legendary | primary (my collection) |
| `D9` | Diamonds | 9 | Cryptie #01896 | legendary | primary (my collection) |
| `D8` | Diamonds | 8 | Cryptie #01994 | legendary | primary (my collection) |
| `D7` | Diamonds | 7 | Cryptie #08507 | legendary | backup (Dean's collection) |
| `D6` | Diamonds | 6 | Cryptie #01614 | epic | primary (my collection) |
| `D5` | Diamonds | 5 | Cryptie #01725 | epic | primary (my collection) |
| `D4` | Diamonds | 4 | Cryptie #04439 | epic | primary (my collection) |
| `D3` | Diamonds | 3 | Cryptie #04665 | epic | primary (my collection) |
| `D2` | Diamonds | 2 | Cryptie #05515 | epic | primary (my collection) |
| `H10` | Hearts | 10 | Cryptie #08218 | legendary | primary (my collection) |
| `H9` | Hearts | 9 | Cryptie #00317 | epic | primary (my collection) |
| `H8` | Hearts | 8 | Cryptie #00606 | epic | primary (my collection) |
| `H7` | Hearts | 7 | Cryptie #01577 | epic | primary (my collection) |
| `H6` | Hearts | 6 | Cryptie #02040 | epic | primary (my collection) |
| `H5` | Hearts | 5 | Cryptie #02869 | epic | backup (Dean's collection) |
| `H4` | Hearts | 4 | Cryptie #03543 | epic | primary (my collection) |
| `H3` | Hearts | 3 | Cryptie #03587 | epic | primary (my collection) |
| `H2` | Hearts | 2 | Cryptie #04647 | epic | backup (Dean's collection) |
| `C10` | Clubs | 10 | Cryptie #03600 | legendary | backup (Dean's collection) |
| `C9` | Clubs | 9 | Cryptie #00901 | epic | primary (my collection) |
| `C8` | Clubs | 8 | Cryptie #01916 | epic | primary (my collection) |
| `C7` | Clubs | 7 | Cryptie #04113 | epic | primary (my collection) |
| `C6` | Clubs | 6 | Cryptie #05212 | epic | backup (Dean's collection) |
| `C5` | Clubs | 5 | Cryptie #07564 | epic | primary (my collection) |
| `C4` | Clubs | 4 | Cryptie #08359 | epic | backup (Dean's collection) |
| `C3` | Clubs | 3 | Cryptie #08454 | epic | primary (my collection) |
| `C2` | Clubs | 2 | Cryptie #00351 | common | backup (Dean's collection) |
| `S10` | Spades | 10 | Cryptie #01478 | legendary | primary (my collection) |
| `S9` | Spades | 9 | Cryptie #02818 | epic | backup (Dean's collection) |
| `S8` | Spades | 8 | Cryptie #04941 | epic | primary (my collection) |
| `S7` | Spades | 7 | Cryptie #05609 | epic | primary (my collection) |
| `S6` | Spades | 6 | Cryptie #06885 | epic | primary (my collection) |
| `S5` | Spades | 5 | Cryptie #07892 | epic | primary (my collection) |
| `S4` | Spades | 4 | Cryptie #08620 | epic | primary (my collection) |
| `S3` | Spades | 3 | Cryptie #00202 | common | backup (Dean's collection) |
| `S2` | Spades | 2 | Cryptie #02230 | common | backup (Dean's collection) |

## Full 92-piece dataset (usable pool)

Every S1 NFT considered, with its full trait read -- kept here so this page
is a complete standalone reference; re-running `crypties-s1-rarity-report.php`
only needed if the two wallets' holdings actually change.

| NFT | Owner | Rarity | Aura | Background | Body | Head |
|---|---|---|---|---|---|---|
| Cryptie #00202 | backup (Dean's collection) | common | starving | spiced | gambler | x-ray |
| Cryptie #00276 | primary (my collection) | common | geometric | decayed | king | robot |
| Cryptie #00317 | primary (my collection) | epic | teleporting | tortured | god | x-ray |
| Cryptie #00351 | backup (Dean's collection) | common | battle | tortured | hombre | robot |
| Cryptie #00606 | primary (my collection) | epic | glowing | blackened | hunter | demon |
| Cryptie #00669 | backup (Dean's collection) | common | geometric | possessed | hunter | zombie |
| Cryptie #00704 | backup (Dean's collection) | common | battle | spiced | hunter | tech |
| Cryptie #00780 | backup (Dean's collection) | common | smoking | tortured | fiend | x-ray |
| Cryptie #00829 | backup (Dean's collection) | common | shimmering | charred | cultist | zombie |
| Cryptie #00891 | primary (my collection) | legendary | crypto | coded | god | tech |
| Cryptie #00901 | primary (my collection) | epic | battle | tortured | god | tech |
| Cryptie #01014 | backup (Dean's collection) | common | shimmering | tortured | shadow | warrior |
| Cryptie #01058 | backup (Dean's collection) | common | smoking | blackened | hombre | tech |
| Cryptie #01091 | backup (Dean's collection) | common | shimmering | decayed | viking | robot |
| Cryptie #01478 | primary (my collection) | legendary | starving | coded | fiend | zombie |
| Cryptie #01577 | primary (my collection) | epic | teleporting | blackened | god | collector |
| Cryptie #01614 | primary (my collection) | epic | geometric | treasured | god | tech |
| Cryptie #01725 | primary (my collection) | epic | geometric | spiced | hombre | collector |
| Cryptie #01896 | primary (my collection) | legendary | geometric | coded | god | tech |
| Cryptie #01916 | primary (my collection) | epic | smoking | charred | shadow | warrior |
| Cryptie #01928 | primary (my collection) | common | geometric | charred | cultist | emerald |
| Cryptie #01994 | primary (my collection) | legendary | geometric | blackened | god | x-ray |
| Cryptie #02040 | primary (my collection) | epic | teleporting | charred | hunter | warlock |
| Cryptie #02230 | backup (Dean's collection) | common | starving | possessed | hombre | demon |
| Cryptie #02639 | backup (Dean's collection) | common | haunting | blessed | cultist | tech |
| Cryptie #02818 | backup (Dean's collection) | epic | haunting | treasured | viking | collector |
| Cryptie #02869 | backup (Dean's collection) | epic | teleporting | blessed | priest | tech |
| Cryptie #03085 | backup (Dean's collection) | common | geometric | charred | fiend | collector |
| Cryptie #03253 | backup (Dean's collection) | common | geometric | spiced | viking | collector |
| Cryptie #03543 | primary (my collection) | epic | teleporting | blackened | fiend | tech |
| Cryptie #03555 | primary (my collection) | common | starving | charred | king | warrior |
| Cryptie #03587 | primary (my collection) | epic | teleporting | tortured | god | zombie |
| Cryptie #03600 | backup (Dean's collection) | legendary | battle | possessed | cultist | warlock |
| Cryptie #03780 | primary (my collection) | common | geometric | possessed | hunter | tech |
| Cryptie #03938 | backup (Dean's collection) | common | battle | decayed | hombre | tech |
| Cryptie #04053 | backup (Dean's collection) | common | summoning | blackened | god | zombie |
| Cryptie #04113 | primary (my collection) | epic | battle | blackened | hombre | x-ray |
| Cryptie #04179 | primary (my collection) | common | teleporting | decayed | cultist | demon |
| Cryptie #04202 | backup (Dean's collection) | common | starving | coded | gambler | warlock |
| Cryptie #04439 | primary (my collection) | epic | geometric | possessed | hombre | wise |
| Cryptie #04498 | primary (my collection) | common | geometric | charred | priest | warrior |
| Cryptie #04578 | backup (Dean's collection) | common | crypto | tortured | fiend | robot |
| Cryptie #04647 | backup (Dean's collection) | epic | summoning | chaotic | god | warrior |
| Cryptie #04665 | primary (my collection) | epic | crypto | charred | god | robot |
| Cryptie #04727 | primary (my collection) | common | geometric | possessed | god | robot |
| Cryptie #04941 | primary (my collection) | epic | starving | possessed | god | tech |
| Cryptie #05212 | backup (Dean's collection) | epic | battle | spiced | king | wise |
| Cryptie #05214 | primary (my collection) | epic | teleporting | coded | shadow | collector |
| Cryptie #05281 | primary (my collection) | common | geometric | coded | fiend | demon |
| Cryptie #05423 | primary (my collection) | common | battle | possessed | fiend | tech |
| Cryptie #05515 | primary (my collection) | epic | geometric | possessed | viking | warrior |
| Cryptie #05593 | primary (my collection) | common | battle | treasured | gambler | wise |
| Cryptie #05609 | primary (my collection) | epic | starving | decayed | god | collector |
| Cryptie #05999 | primary (my collection) | common | geometric | charred | viking | wise |
| Cryptie #06034 | primary (my collection) | epic | glowing | tortured | viking | x-ray |
| Cryptie #06156 | primary (my collection) | common | summoning | blackened | god | robot |
| Cryptie #06605 | primary (my collection) | common | teleporting | tortured | hombre | x-ray |
| Cryptie #06623 | primary (my collection) | epic | geometric | charred | cultist | x-ray |
| Cryptie #06885 | primary (my collection) | epic | starving | decayed | god | x-ray |
| Cryptie #06920 | primary (my collection) | epic | crypto | coded | hombre | collector |
| Cryptie #06967 | primary (my collection) | epic | teleporting | charred | fiend | robot |
| Cryptie #07015 | backup (Dean's collection) | common | glowing | decayed | gambler | tech |
| Cryptie #07168 | backup (Dean's collection) | common | battle | charred | hombre | demon |
| Cryptie #07237 | backup (Dean's collection) | common | shimmering | charred | cultist | warrior |
| Cryptie #07239 | primary (my collection) | common | teleporting | possessed | hunter | warrior |
| Cryptie #07246 | primary (my collection) | epic | geometric | tortured | shadow | demon |
| Cryptie #07299 | primary (my collection) | epic | glowing | coded | king | tech |
| Cryptie #07323 | backup (Dean's collection) | common | glowing | spiced | cultist | robot |
| Cryptie #07402 | primary (my collection) | common | geometric | charred | god | emerald |
| Cryptie #07435 | primary (my collection) | epic | geometric | possessed | shadow | warrior |
| Cryptie #07478 | backup (Dean's collection) | common | shimmering | tortured | shadow | warlock |
| Cryptie #07564 | primary (my collection) | epic | smoking | blackened | king | x-ray |
| Cryptie #07679 | primary (my collection) | common | geometric | tortured | hunter | tech |
| Cryptie #07892 | primary (my collection) | epic | starving | charred | god | tech |
| Cryptie #08059 | backup (Dean's collection) | common | haunting | blackened | gambler | robot |
| Cryptie #08146 | primary (my collection) | epic | glowing | tortured | god | collector |
| Cryptie #08182 | primary (my collection) | common | starving | charred | cultist | tech |
| Cryptie #08218 | primary (my collection) | legendary | teleporting | blackened | god | tech |
| Cryptie #08255 | backup (Dean's collection) | common | geometric | blessed | god | robot |
| Cryptie #08321 | primary (my collection) | common | starving | possessed | god | robot |
| Cryptie #08359 | backup (Dean's collection) | epic | battle | tortured | gambler | emerald |
| Cryptie #08412 | primary (my collection) | common | teleporting | spiced | gambler | warrior |
| Cryptie #08419 | backup (Dean's collection) | common | battle | tortured | cultist | robot |
| Cryptie #08454 | primary (my collection) | epic | smoking | charred | fiend | zombie |
| Cryptie #08507 | backup (Dean's collection) | legendary | crypto | possessed | king | collector |
| Cryptie #08620 | primary (my collection) | epic | starving | decayed | cultist | warlock |
| Cryptie #08794 | primary (my collection) | common | geometric | blessed | hunter | warrior |
| Cryptie #09046 | primary (my collection) | epic | teleporting | possessed | god | warlock |
| Cryptie #09158 | primary (my collection) | common | teleporting | possessed | priest | collector |
| Cryptie #09603 | primary (my collection) | common | smoking | spiced | gambler | collector |
| Cryptie #09746 | backup (Dean's collection) | common | shimmering | tortured | hunter | warrior |
| Cryptie #09885 | backup (Dean's collection) | common | shimmering | coded | shadow | demon |

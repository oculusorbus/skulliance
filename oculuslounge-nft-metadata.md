# Oculus Lounge — Disco Solaris Metadata Research

Internal design/research doc, not player-facing (see `skullpaper/MAINTENANCE.md` — this
is not a Skull Paper page). Written up so the deep-research pass behind the eventual
"real armor/gear instead of random rolls" feature isn't lost between sessions.

## The problem

Oculus Lounge (`dropship_project_id == 4`) plays on the **Disco Solaris** NFT
collection (policy `d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779`), a
different collection entirely from Drop Ship's own. `dropshipSyncOculusLounge()` in
`dropship/db.php` currently assigns each new soldier's `armor`/`gear` with a flat
random roll:

```php
$armor = array("Heavy", "Medium", "Light", "Base");
$gear  = array("None", "Melee", "Demolition", "Medkit");
$armor_final = $armor[rand(0, 3)];
$gear_final = $gear[rand(0, 3)];
```

— a snapshot taken once at first sync, unrelated to what the player's actual NFT looks
like. The goal: derive `armor`/`gear` from the NFT's own real on-chain traits instead,
so a holder's soldier is a genuine reflection of their art, not a coin flip.

## Data source & method

Disco Solaris publishes standard CIP-25 metadata, readable via Koios's already-public,
unauthenticated `asset_info` endpoint (`api.koios.rest/api/v1/asset_info`, same one
Drop Ship's own pre-migration Koios pipeline used) — no new integration needed, no
scraping a third-party rarity site required.

**Pitfall hit and worked around:** Koios's `policy_asset_list` listing endpoint (used
to enumerate every asset in the collection) paginates via a `Range` header, but the
underlying query has no stable `ORDER BY` — successive pages return inconsistent,
overlapping windows. A first pull came back with only 4,555 of the real 5,777 assets
(1,222 duplicated across far-apart pages, others silently skipped). Fixed by not
trusting that endpoint at all: Disco Solaris uses a clean sequential naming scheme
(`DiscoSolaris0001`–`DiscoSolaris5777`), so every asset name was generated directly and
pulled individually via `asset_info`, batched ~40 at a time. Final pull: **5,777 / 5,777
assets, zero gaps**, matching cnft.tools' own reported collection size exactly.

Raw per-asset attribute data (asset name → CIP-25 `attributes` object) is cached at
[`dropship/data/discosolaris-attributes.json`](dropship/data/discosolaris-attributes.json)
(~1.7MB) so a real implementation pass doesn't need to re-pull the whole collection from
Koios again — only new mints going forward would need fresh calls.

## Attribute schema (confirmed on-chain, all 5,777 assets)

| Field | Assets with it | % |
|---|---|---|
| Eyes / Skin / Mouth / Phase / `ZaF-b51ab4` | 5,767 | 99.8% |
| Body | 5,627 | 97.4% |
| Hair | 5,557 | 96.2% |
| Outerwear | 5,291 | 91.6% |
| Earrings | 5,136 | 88.9% |
| Necklace | 2,938 | 50.9% |
| Cheek | 2,897 | 50.1% |
| Glasses | 2,842 | 49.2% |
| Headphones | 1,585 | 27.4% |
| Beard | 916 | 15.9% |
| Moustache | 889 | 15.4% |
| Special | 754 | 13.1% |
| Hat | 745 | 12.9% |

`ZaF-b51ab4` is not a display trait — it's an internal generation/batch code (values
like `"000101"`), confirmed noise, excluded from any mapping. Body/Eyes/Hair/Skin/
Beard/Cheek/Mouth/Moustache/Phase are cosmetic (face/body), not worn/carried items, so
they're out of scope for armor/gear mapping — `Outerwear` (armor) and the six
accessory-type fields (gear) are the only fields that represent "worn or carried
objects" in a Drop Ship item-slot sense.

**Edge cases found, real metadata not gaps:**
- **12 tokens have `Outerwear: "Custom"`** — a founder/genesis batch (0001, 0002,
  0003, 0007, 0641, ...) with unique 1-of-1 art across most fields, not drawn from the
  generative trait pool.
- **10 "poster" tokens** (0009–0014, 0552, 3994, 4235, 4687) are non-humanoid 1-of-1s —
  no Body/Eyes/Outerwear at all, just `{"Special": "Amber VHS Poster"}` or
  `"Moebius-9 Poster"`.
- **486 tokens (8.4%) genuinely have no `Outerwear` trait** — real absence, not a pull
  failure (481 of those still have some accessory field; only 5 are fully bare).
- **90 tokens (1.6%) have none of the six accessory fields at all** — the only tokens
  that should legitimately resolve to gear `None`.
- **4,717 tokens (81.6%) have 2+ accessory fields simultaneously** — since a soldier
  only has one `gear` slot, a precedence rule (below) is required to pick a winner.

## Confirmed mapping design

Reasoned through Oculus Lounge's own reskinned vocabulary (`evaluateText()` in
`dropship/db.php`), not the abstract Drop Ship internal names — the actual words a
player sees are Boxer Briefs/Basketball Shorts/Sweat Pants/Smoking Jacket (armor) and
Vibrator/Paddle/Sexy Nurse (gear/items).

### Armor — `Outerwear` trait → tier

| Stored value | Oculus Lounge display | Outerwear values covered | Count | % |
|---|---|---|---|---|
| `Base` | Boxer Briefs | *no Outerwear on-chain*, Sleeveless Jacket, Tracksuit, Sweatshirt | 1,864 | 32.3% |
| `Light` | Basketball Shorts | Denim Jacket, Bomber Jacket, Badlands Jacket, Fancy Shirt, Soho Kids Jacket | 1,522 | 26.3% |
| `Medium` | Sweat Pants | Moss Jacket (+ With Flower/Mushrooms/Insect), Leather Jacket With Cables, Thick Pink Jacket, Night Dress, Lab Coat, Black Coat, Green Coat | 1,691 | 29.3% |
| `Heavy` | Smoking Jacket | Formal Jacket (+ With Phone/Card/Pack/Floppy/Green Console/Grey Console), Solar Jacket, Custom | 700 | 12.1% |

Bucketed by garment formality/coverage — plain "Formal Jacket" variants and the
founder "Custom" tokens landing on the actual "Smoking Jacket" item is a clean,
literal match, not a stretch. No Outerwear at all (486 tokens) legitimately earns
`Base` — real absence, not a fallback default.

### Gear — accessory fields → item (rarity-ranked precedence)

Six on-chain fields (Hat, Necklace, Earrings, Glasses, Headphones, Special) feed one
`gear` slot. No field has an obvious visual match to Vibrator/Paddle (those are
generic reskinned "weapon" names, not costume pieces) except `Special`, whose values
are literal costume/uniform items (Police Officer Uniform, Space Suit) — a genuine
costume-to-costume match against Sexy Nurse. Everything else is ranked by field
rarity: rarer accessory trait = better item.

Precedence (first match wins):

```
Special or Hat present   -> Sexy Nurse   (Medkit)
Headphones present       -> Paddle       (Demolition)
Glasses/Necklace/Earrings-> Vibrator     (Melee)
none of the above        -> None
```

| Stored value | Oculus Lounge display | Count | % |
|---|---|---|---|
| `Melee` | Vibrator | 3,050 | 52.8% |
| `Medkit` | Sexy Nurse | 1,429 | 24.7% |
| `Demolition` | Paddle | 1,208 | 20.9% |
| `None` | None | 90 | 1.6% |

Confirmed reasonable: Medkit (the single strongest bonus — an extra life) lands at a
meaningful-but-not-common rate; Melee (weakest, delay 1 turn) is correctly the
majority default; `None` stays genuinely rare and earned (only the 90 tokens with zero
accessory traits of any kind), not a catch-all gap.

## Where this integrates (not yet built)

1. `dropshipSyncOculusLounge()` (`dropship/db.php`) would batch a Koios `asset_info`
   call for a player's *own* staked Disco Solaris NFTs only (small subset, not the
   whole collection) and run the mapping above instead of `rand(0,3)`.
2. A new `soldiers.metadata_verified` flag (or similar) so a soldier is only derived
   once, not re-rolled every sync.
3. A one-time CLI backfill/verify script — same shape as `cache-oculuslounge-art.php`
   — to re-derive `armor`/`gear` for existing Oculus Lounge soldiers that currently
   hold pre-this-feature random rolls, and mark them verified.
4. The mapping tables above (`Outerwear` → tier, accessory-field precedence → gear)
   become one pure function both paths call.

Not started. This doc + the cached JSON are the research/design foundation for
whenever that build happens.

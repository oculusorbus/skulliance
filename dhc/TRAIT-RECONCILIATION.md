# DHC2: Fighters — trait art reconciliation

Comparing the trait files provided against the **226 fighters actually minted**
under policy `b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5`,
read from chain via Koios. Counts in brackets are how many minted fighters use
that trait.

Context: the collection was hand-assembled, so some of these may be deliberate
omissions — traits that don't decompose into clean layers. That's exactly what
we'd like confirmed.

---

## 1. On-chain traits with no matching file (32)

These appear in minted fighters but we have no art for them. If any were left
out deliberately, no action needed — we'll simply exclude them.

**Background** (3)

- ~~Red Planet Decoy~~ *[4]* — **RESOLVED 2026-10-14.** `red-planet-decoy`, epic.
- ~~XLONs Room Entrance~~ *[4]* — **RESOLVED 2026-10-30.** `xlons-room-entrance`, epic.
  A **Graffiti** variant came with it that is not in the collection at all:
  `xlons-room-entrance-graffiti`, mythic.
- XLONs Complex Interior  *[1 fighter]*

**Torso** (5)

- UE Punk  *[6 fighters]*
- ~~Damaged DH Cop #1~~ *[2]* — **RESOLVED 2026-10-30.** `damaged-dh-cop-1`, legendary.
  Borrows DH Cop's armless plate: the damage is 100% inside the arms region
  and 0px on the body, so a second plate would have been identical.
- Virtual Smoke  *[1 fighter]*
- MK3K  *[1 fighter]*
- Battled Out Red Cyborg  *[1 fighter]*

**Arms** (2)

- Turret Blue MK200 Limbs  *[8 fighters]*
- Propellant Limbs  *[1 fighter]*

**Head** (9)

- Red Cyborg  *[10 fighters]*
- V12 Raider  *[9 fighters]*
- XLON Repeater  *[4 fighters]*
- Happy Robot Decoy  *[3 fighters]*
- Virtual Raider  *[2 fighters]*
- UE Mutant Pirate  *[2 fighters]*
- Virtual Smoke  *[1 fighter]*
- Nightmare Robot  *[1 fighter]*
- Battled Out Red Cyborg  *[1 fighter]*

**Headgear** (~~3~~ 2)

- ~~Skull Collector~~ *[5 fighters]* — **RESOLVED 2026-09-14.** Maxingo sent the
  art as a straggler after the original hand-off. Incorporated as
  `skull-collector-helmet` (epic, 5/226); the file is named
  `Skull_Collector_Helmet.png`, so `gen-rarity.py` aliases the name back to the
  on-chain `Skull Collector`.
- Post Apocalyptic Exo Mask  *[4 fighters]*
- Code Viking Helmet  *[1 fighter]*

**Weapon** (1)

- ~~Bone Blaster 3X~~ *[3, but 2 render it]* — **RESOLVED 2026-10-30.** Split into
  `bone-blaster-3x` (back) + `bone-blaster-3x-strap` (front), tethered. Scored
  on 2, not 3: one Fighter lists it in metadata with no weapon in the art.
  Legendary.

**Companion** (1)

- ~~Demon Companion~~ *[2]* — **RESOLVED 2026-10-14.** Two-part: body behind the
  torso, claws over the head.

**Effects1** (5)

- ~~Earth Recovery System Interface~~ *[9]* — **RESOLVED 2026-10-14.** Uncommon.
- Short Circuit  *[5 fighters]*
- Love Bomb  *[3 fighters]*
- Dissolving Bubbles #1  *[1 fighter]*
- Digi Goo Attack  *[1 fighter]*

**Effects2** (3)

- Bionic Eye  *[2 fighters]*
- Short Circuit  *[1 fighter]*
- Love Bomb  *[1 fighter]*

**Extra** — this slot has no folder at all. Values used: Killer Phantom Mask [1], Annihilation Belt [1],  [4], Digital Bubbles #1 [1]

They look like second instances of traits from other slots. Is `Extra` a
wildcard duplicate slot, or its own art?

---

## 2. Names that matched the same file — please confirm (12)

Each row is two or more on-chain trait names resolving to one file. Most look
like casing/spelling variants in the mint metadata, but some may be genuinely
distinct traits where we're missing one.

| slot | file we have | on-chain names |
|---|---|---|
| Background | `XLONs_s_Complex.jpg` | XLONs Complex *[8]*<br>XLONs Complex Trap *[4]* |
| Background | `DH_Aracnyd_Web.jpg` | DH Aracnyd Web *[8]*<br>Dh Aracnyd Web *[1]* |
| Background | `XLON_s_2YK_Attack_#02.jpg` | XLONs 2YK Attack *[2]*<br>XLONs 2YK Attack #2 *[3]* |
| Background | `Digital_Trap_#03.jpg` | DIgital Trap #3 *[1]*<br>Digital Trap #3 *[2]* |
| Torso | `ZX350_Centinel.png` | ZX350 Centinel *[4]*<br>ZX350 Sentinel *[4]*<br>ZX50 Centinel *[1]*<br>ZX50 Sentinel *[1]* |
| Torso | `Inferno_Wasp_Guardian.png` | Inferno Wasp Guardian *[3]*<br>inferno Wasp Guardian *[1]* |
| Arms | `MK100_Blaster_Arms.png` | Damaged MK100 Blaster Arms *[2]*<br>MK100 Blaster Arms *[1]* |
| Arms | `Cyber_Inferno_Limbs.png` | Cyber Inferno Limbs *[1]*<br>Cyber inferno Limbs *[1]* |
| Head | `XBot_.png` | XBot *[1]*<br>Xbot *[11]* |
| Head | `Dh_Cop_#03.png` | DH Cop #3 *[11]*<br>Dh Cop #3 *[1]* |
| Effects1 | ~~`DHC2_Comic_Cover_#3.png`~~ | DHC2 Comic Cover #3 *[8]* — **#4 art received 2026-10-14**, scored separately (epic). A **#5** arrived 2026-10-30 with no mint precedent, pinned legendary. |
| Effects1 | `XLON_Digi_Fire_Attack.png` | XLON Digi Fire Attack *[1]*<br>XLONs Digi Fire Attack *[6]* |

Ones we'd particularly like checked:

- **ZX350 / ZX50 Centinel / Sentinel** — four names, one file. Are ZX50 and ZX350 different torsos?
- **Damaged MK100 Blaster Arms** vs **MK100 Blaster Arms** — we also have an unused
  `Deteriorated_MK100_Blaster_Arms.png`; is that the 'Damaged' one?
- **XLONs 2YK Attack** vs **XLONs 2YK Attack #2**, **XLONs Complex** vs **XLONs Complex Trap**,
  **DHC2 Comic Cover #3** vs **#4** — variants where we have only one file.

---

## 3. Files provided but never used in the minted set (59)

The opposite direction — art we have that no minted fighter uses. Are these
free to use in the platform assembler, or should we stay within what was minted?

**Augmented Arms** (2): `Deteriorated_MK100_Blaster_Arms.png`, `symbiō-flexx_.png`

**Backgrounds** (22): `Black.jpg`, `Bright_Red.jpg`, `DH_Nebula_#02.jpg`, `Digital_Trap_#01.jpg`, `Digital_Trap_#03.jpg`, `Digital_Trap_#04.jpg`, `Eon_Raider_Spacecraft_Window.jpg`, `Exoplanet_Landscape.jpg`, `Light_Blue.jpg`, `Light_Green.jpg`, `Light_Purple.jpg`, `Light_Yellow.jpg`, `Red.jpg`, `White.jpg`, `XLON_Ddos_Attack.jpg`, `XLON_s_2YK_Attack_#01.jpg`, `XLON_s_2YK_Attack_#02.jpg`, `XLONs_Building_Interior.jpg`, `XLON_s_Building_Trap.jpg`, `XLON_s_Disintegrating_Bubbles.jpg`, `XLON_s_Optical_Attack.jpg`, `XLONs_s_Complex.jpg`

**Effects** (3): `2YK_Flames.png`, `Electricity.png`, `Love_Demonstration.png`

**Headpieces** (8): `Cyber_Voodoo.png`, `Cyber_Voodoo 1.png`, `E_Pharaoh_Head.png`, `Golden_Cyber_Plague_Detecting_Mask.png`, `Merged.png`, `MK100_Codesea_Explorer_Helmet.png`, `MK200_Cyber_Plague_VR_Mask.png`, `Steel_Viking_Helmet.png`

**Heads** (16): `Alien_Zombie.png`, `Alien_Zombie_#02.png`, `Broken_XLON_Repeater 1.png`, `Cyborg_Stryker.png`, `Cyborg_Stryker_#02.png`, `Cyborg_Stryker_#03.png`, `DH_Cop_#01.png`, `Dh_Cop_#03.png`, `Dh_Cop_#03 1.png`, `Happy_Cyborg_Decoy.png`, `Mk100_Torso.png`, `Mutant_Goblin_.png`, `Planet_8TZ_Specter 1.png`, `U.E._Mutant_#01.png`, `U.E._Mutant_#02.png`, `V12_Punk_Raider.png`

**Pets** (3): `Arachnid_Laser_Cannon.png`, `U._Vigilance_Device_#1.png`, `U._Vigilance_Device_#2.png`

**Torsos** (2): `DH_Cop.png`, `Planet_P-386_Punk.png`

**Weapons** (3): `DH_Spike_Blaster 1.png`, `Lil_Fren 1.png`, `Mega_Taser_Cannon 1.png`

---

## 4. Metadata typos, for information only

Noticed while matching; these are immutable on chain and need no action —
flagging only so nobody thinks they're new traits:

`Dh Aracnyd Web` / `DIgital Trap #3` / `inferno Wasp Guardian` / `Dh Cop #3` /
`Xbot` / `Cyber inferno Limbs` — casing or spelling variants of an existing trait.

---

## 5. One thing we can't derive: layer order

We need the canonical z-order to composite correctly. Complicated by two things
we found in the metadata:

- There are **two effects slots** (`Effects1`, `Effects2`) — do they have a fixed order?
- Some weapons appear to have a component that sits **behind** the torso. We'd like
  to split those into back/front pieces, which means editing your source art —
  we won't touch anything without your go-ahead, and layered originals would be
  far better than us cutting flattened PNGs.



---

## Resize pipeline (verified)

The web derivatives are a straight full-canvas downscale of the 2500x2500
source — never a crop or a trim, because every trait sharing one canvas is
exactly what makes the layers stack without nudging:

```python
im = Image.open(src).convert('RGBA').resize((size, size), Image.LANCZOS)
im.quantize(colors=256, method=Image.FASTOCTREE).save(out, optimize=True)
```

Confirmed **byte-identical** to the existing `dhc/web/1000` files by
regenerating `alien-skull.png` and `666-demon-headgear.png` from their sources
and comparing pixels. BICUBIC does not reproduce them; LANCZOS is the one that
matches. Sizes are 1000 and 250.

After adding art: update `dhc/web/trait-index.json` (slug, name, source), add an
ALIAS in `gen-rarity.py` if the filename differs from the on-chain trait name,
rerun it to rewrite `dhcrarity.php`, then FTP the two PNGs and the trait index.


---

## 2026-10-30 delivery

Five new traits and nine missing ones. The new art has no mint precedent, so a
count of 0 says nothing about how rare it should be — those are pinned by hand.

| trait | slot | tier | why |
|---|---|---|---|
| DHC2 Comic Cover #5 | Effects 2 | legendary | pinned; Effects 2 only, like every cover |
| Failed Immortality Body | Torso | legendary | pinned |
| Failed Immortality Head | Head | legendary | pinned |
| Neural Crown | Headgear | legendary | pinned |
| Skulliance Helmet | Headgear | mythic | count-0 default; the house's own badge |
| Bio Weapon Strap | Weapon (front) | legendary | tethered to the Bio Weapon, inherits its 2 |
| Bone Blaster 3X + Strap | Weapon | legendary | 2 render it, not the 3 in metadata |
| Damaged DH Cop #1 | Torso | legendary | 2 on chain |
| XLONs Room Entrance | Background | epic | 4 on chain |
| XLONs Room Entrance Graffiti | Background | mythic | not in the collection |
| Dark DH Sorceror | Headgear | legendary | **this** is the chain's `DH Sorceror`, 1 |
| Green Code Sea Predator | Companion | legendary | **this** is the chain's `Code Sea Predator`, 2 |
| Electric Morning Star (the mace) | Weapon (back) | epic | 3 on chain |

### Three names we had on the wrong art

The delivery settled what the collection actually contains, and in three cases
the piece we held under a name was not the piece the chain means by it. The
slug has to follow the chain — otherwise rarity describes the wrong art, and
the minted Fighters in `dhc-mint-traits.json` render pieces those NFTs do not
have. So the art kept the slug and the displaced piece took a new one:

| slug | was | is now | the displaced piece |
|---|---|---|---|
| `electric-morning-star` | the skull and chains | the mace | `skull-and-chains`, uncommon |
| `code-sea-predator` | a piece absent from the collection | the green one | `steel-code-sea-predator`, mythic |
| `dh-sorceror` | the blue one | the dark one | `blue-dh-sorceror`, mythic |

`dhc-trait-migrate.php` renames what players already hold, so nobody's
assembled Fighter changes appearance. All three displaced pieces moved to a
**rarer** tier than they had, never a commoner one.

**The skull and chains is not a tethered half.** It hangs from the Axe *and*
from the mace, so both couple to it one-way: picking either brings the chains,
picking the chains commits you to neither. Its rarity is a derived count of 8
(axe 5 + morning star 3, disjoint because Weapon is one slot on chain) rather
than the 0 its own absent name would give it.

**The Bio Weapon's strap is backfilled.** The weapon shipped without it and has
been drawn unsupported since; now that the two are tethered, a holder with no
strap would have their Fighter refused at save for a trait they were never
given. The same script grants one strap per weapon held. The Bone Blaster needs
no equivalent — both halves are new here.

### Still outstanding

Everything under §1 that is not struck through, and the `Extra` slot question.

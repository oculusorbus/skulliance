# Mission economy — cost, reward, art and the spread

**What this is.** The maths behind every row in `quests`, recovered from the
live table rather than from memory; how mission art is named and paired; and
the rules an admin tool has to enforce so that onboarding a project or
extending one cannot quietly break either.

Operator material, so it lives at the repo root beside `multichain.md`
rather than in `skullpaper/` — a player never reads this.

| | |
|---|---|
| `missions-tiers.py` | the generator: prints any scheme's ladder for any mission count |
| `quests` table | `cost`, `reward`, `duration`, `level`, `extension`, `project_id` |
| `missions-lib.php` | reads them; computes `net_per_day`; builds every art path |

Analysed against a full dump of **455 missions across 39 projects**, taken
2026-10-04.

---

## 1. The rule

Three lines, and **453 of the 455 live rows obey them exactly**:

```
level 1      cost 0, reward 10, duration 1        -- the free intro
level 2+     duration = cost / 100                -- days
             reward   = cost * (1 + level/10)
```

`level` is the mission's position in its project's ladder, 1 upward, and
every project opens with the free one.

### 1a. The invariant that actually matters

Those three lines collapse into one:

```
net per day = 10 * level
```

Profit is `cost * level/10` and duration is `cost/100`, so the cost cancels.
**The cost ladder does not affect the rate.** It decides how much CARBON a
mission locks up and for how long, and nothing else.

Not an incidental property: `missions-lib.php` computes
`net_per_day = (reward - cost) / duration` and puts it in front of the
player, so it is the number they compare missions on.

The practical consequence for anyone editing a mission: **cost and duration
move together or the rate breaks.** Change a cost without the duration and
that mission silently becomes the best or worst deal on the platform.

### 1b. Why the numbers come out round

`cost` is a multiple of 100 and the multiplier moves in tenths, so

```
reward = 100k * (10 + L)/10 = 10k * (10 + L)
```

is always a multiple of ten. That is the whole trick behind the clean
pairings. **A multiplier on a twentieth breaks it:** `500 * 1.25 = 625`. If
a finer gradient is ever wanted, the way to get it is more levels, not finer
multipliers.

---

## 2. The two rows that disagreed — fixed

Both were data entry slips, not intent, and nothing else in 455 rows
deviated. Corrected on the live table 2026-10-04.

| id | mission | project | was | should be | symptom |
|---|---|---|---|---|---|
| 197 | Trash Collection | 10, level 3 | reward `600` | `650` | 20/day where every other level 3 pays 30 |
| 183 | The Worm | 12, level 6 | duration `7` | `10` | 85.7/day where every other level 6 pays 60 |

Worth noting *how* they surfaced: not by reading rows, but by asserting the
invariant across the whole table and printing what failed. Two rows in 455
is not something anyone finds by eye, and a tool that validates on save
makes a third impossible.

---

## 3. The cost ladders actually in use

Two families, and the split is historical rather than designed.

**Wide spread** — 25 projects, the older ones. All draw from one master
sequence, shorter projects taking a window of it:

```
200  300  500  700  1000  1500  2000  2500  3000
```

| missions | ladder, level 2 upward |
|---|---|
| 4 | 300 500 700 |
| 5 | 300 500 700 1000 |
| 6 | 300 500 700 1000 1500 |
| 8 | 300 500 700 1000 1500 2000 3000 |
| 10 | 200 300 500 700 1000 1500 2000 2500 3000 |

**Flat 100s** — 14 projects, the newer ones. `cost = 100 * level`, so the
duration equals the level: 200, 300, 400, 500 …

The instinct behind the split was right. A four-mission project needs big
jumps to span cheap to expensive at all; a forty-six-mission project doing
the same would be absurd. What was missing was a rule for choosing.

---

## 4. The problem to decide on

Because the multiplier keys off the **absolute level**, a project's earning
rate is set by how many missions it happens to have.

| missions | top multiplier | top net/day | top reward |
|---|---|---|---|
| 6 | 1.6x | 60 | 2,400 |
| 10 | 2.0x | 100 | 6,000 |
| 24 | 3.4x | 240 | 8,160 |
| 46 | **5.6x** | **460** | **25,760** |

A player holding a 46-mission project's NFTs earns nearly eight times the
daily rate of one holding a 6-mission project's, for no reason either can
see or influence.

This is the one real design fault in the scheme. Everything else about it is
sound.

---

## 5. Three schemes, and when each applies

`missions-tiers.py` implements all three. For an admin tool the last column
is the one that matters.

| scheme | multiplier | top net/day | safe to append to? |
|---|---|---|---|
| `live()` | `1 + level/10`, uncapped | `10 * level`, unbounded | yes |
| `tiered()` | spans 1.2–2.0 across the ladder | always 100 | **no** |
| `append_safe()` | `1 + min(level,10)/10` | 100, capped | **yes** |

### 5a. Why the obvious fix is the wrong one here

`tiered()` sets the multiplier from a mission's **position** in the ladder,
so a short project and a long one span the same economic range and the
imbalance in §4 disappears. Cleanest answer on paper.

It is also the worst possible fit for adding missions to an existing
project. Position-based means one new mission renumbers the economics of
every mission already there — measured: appending rewrote **every** existing
row, in 26 of 26 cases. That is exactly the rework this is meant to prevent.

### 5b. What to use instead

`append_safe()` — every field a function of the level alone:

```
cost     = 100 * level
duration = level                           -- days
reward   = cost * (1 + min(level, 10)/10)
net/day  = 10 * min(level, 10)             -- flat 100 past level 10
```

Nothing depends on the mission count, so **appending is a pure INSERT**.
Measured: appending seven missions rewrote existing rows in **0 of 36**
cases. Level 7 pays 1.7x whether the project has eight missions or eighty.

It also fixes §4: the rate tops out at 100/day for everyone, and a project
with many missions offers a longer climb rather than a better rate.

A first attempt capped the multiplier but still rewrote rows on append,
because the *cost* ladder switched from the wide spread to flat 100s once a
project passed twelve missions — the schedule was a function of `n` even
though the multiplier was not. Both have to be level-only, or neither is.

### 5c. What adopting it would cost

| | |
|---|---|
| rows already matching | 167 of 455 |
| rows that would change | 288 |
| projects already conformant | 4 of 39 |
| rows above the cap losing headline reward | 123, totalling ~353,000 CARBON |

**Not a decision to take by accident**, and not one this document makes.
Adopting it for *new* projects only costs nothing and stops the spread
widening further; retrofitting is a separate call about the live economy.

---

## 6. Mission art

### 6a. The filename is the title

```php
function mission_art_slug($title) {
    return strtolower(str_replace("'", "", str_replace(" ", "-", $title)));
}
```

Art lives in `images/missions/` and the filename is the mission's **title**:
apostrophes removed, spaces to hyphens, lowercased. `quests.extension`
records the format.

Two consequences, both of which the tool has to own:

- **Two missions with the same title overwrite each other's art.** There is
  no uniqueness constraint on `quests.title` and the collision is silent:
  the second upload simply replaces the first, across projects.
- **Only apostrophes and spaces are stripped.** A title with a colon, comma,
  question mark, ampersand, slash or an accented character puts that
  character straight into the filename.

Audited across all 455: **zero collisions and zero slugs outside
`[a-z0-9-]`.** That is discipline, not enforcement — nothing in the code
prevents either.

### 6b. The format pairings

| `extension` | files needed | still shown | video |
|---|---|---|---|
| `png` | `slug.png` | `slug.png` | none |
| `jpg` | `slug.jpg` | `slug.jpg` | none |
| `gif` | `slug.gif` | `slug.gif` | none |
| `mp4` | **`slug.mp4` and `slug.gif`** | `slug.gif` | `slug.mp4` |

**An mp4 mission needs two files.** Three of the five art paths in
`missions-lib.php` do `($extension === 'mp4') ? 'gif' : $extension`, so the
still is always a gif and the video rides alongside. That pairing is why a
video mission means manually producing an animated gif as well.

Live spread: **png 189, gif 173, mp4 82, jpg 11.**

**`mov` is not supported anywhere in the code.** There is no branch for it,
so a `mov` mission would set `image` to a `.mov` (not an image) and leave
`video` empty. That is why mov has to be converted before upload — the
conversion is not optional, and nothing would tell you if it were skipped.

### 6c. Two bugs found while documenting this

Both fixed in the same change as this document.

1. **`mission_active()` ignored the extension and hardcoded `.png`.** It
   `SELECT`s `q.extension` and then builds `images/missions/{slug}.png`
   regardless — so every gif, mp4 and jpg mission showed a **broken image in
   the field list**, which is the view a player looks at while a mission is
   out. That is **266 of 455 missions**, 58% of the table.
2. **The Discord/X share card used the raw extension.** For an mp4 mission
   it published `slug.mp4` as the embed image, which no embed renders.

Both now go through one helper, `mission_art_ext()`, so the pairing rule
lives in a single place instead of being restated at five call sites — four
of which got it right and two of which did not.

### 6d. Resizing and optimisation

Nothing resizes or optimises mission art today; files are whatever was
uploaded. The platform **already has the tooling**, though:
`lib/image-cache-lib.php` runs Imagick with a 256MB limit, coalesces
animated gifs, and resizes with `FILTER_LANCZOS` to 1000px wide. An uploader
can reuse that path rather than inventing one.

**What it cannot do on this host:** there is no `ffmpeg` and no `exec()`
anywhere in the codebase, and Imagick will not read mp4 without video
delegates. So **mp4 → gif cannot be automated server-side** without new
infrastructure. The realistic version is that the tool *detects* the
problem: an mp4 upload without its paired gif is refused with a message
saying so, rather than saving a row that renders a broken tile.

---

## 7. What an admin tool has to enforce

The point of writing all of the above down.

**Economics — validate on save, always:**

1. `duration == cost / 100`, and `cost` a multiple of 100. These are one
   field, not two; derive the duration and do not offer it.
2. `reward == cost * (1 + min(level, cap)/10)`. Derived, never typed.
3. `level` unique within the project, contiguous from 1, no gaps.
4. Exactly one level-1 mission per project, with `cost 0 / reward 10 /
   duration 1`.
5. `reward % 10 == 0`. A failure here means a non-tenth multiplier got in.

**Art — validate before the row is written:**

6. The slug must match `[a-z0-9-]+` after transformation, and must be unique
   **across every project**, not just this one.
7. An `mp4` mission needs both files present. Refuse the save otherwise.
8. Resize and optimise on upload through the existing Imagick path.
9. Write the file and set `extension` in the same operation — a row whose
   art never arrived is the failure mode FTP makes easy.

**Make the safe path the easy one:**

- Adding a mission defaults to `level = max(level) + 1` with cost, duration
  and reward pre-filled from the schedule. The operator writes the title,
  the description and the art; the economics are not their problem.
- Editing a cost moves the duration and reward with it, and shows the
  net/day before and after.
- Show `net_per_day` in the editor. It is what the player sees, and it
  reveals a mistake instantly.
- Refuse to save a ladder with a gap or a duplicate level rather than
  writing it and hoping.

**Descriptions are the other half of the work.** Configuring a project has
never been bottlenecked on the numbers — it is 10 to 46 pieces of original
prose per project, each tied to a specific piece of art. Worth designing
for: a drafting step that takes the project's theme and the mission's art
and proposes a description for the operator to edit, rather than a blank
textarea 46 times. The existing descriptions are a strong house style to
work from — second person, a hook, and a closing "Or, will you …?" reversal
in most of them.

---

## 8. Running the generator

```
python3 missions-tiers.py 6 10 24          # any list of mission counts
```

Prints the live ladder and the proposed tiers side by side for each count:
costs, rewards, multipliers, durations, net/day and totals. `live()` uses
the ladders actually on the table where one exists for that count, so the
comparison is against the real thing rather than a reconstruction.

Verified for every mission count from 2 to 60: every reward a multiple of
ten, costs and rewards strictly increasing, and no net/day above the cap in
the capped schemes.

---

## 9. Open decisions

- **Retrofit, or new projects only?** §5c has the numbers. Doing nothing is
  a valid choice; the imbalance is longstanding and nobody has complained.
- **Where does the cap sit?** 10 is proposed because it makes the top rate
  100/day and matches the sweet spot the original ladders were tuned around.
  A higher cap keeps more of the current spread.
- **Is 100/day the right ceiling at all?** It is inherited from the existing
  level-10 rate, not derived from anything about the reward pool.
- **Should `quests.title` get a unique index?** It is the art filename in
  all but name, and the database does not know that.

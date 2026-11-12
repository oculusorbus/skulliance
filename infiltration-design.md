# Infiltration -- design record

**NOT FOR THE SKULL PAPER, and not to be broadcast.** The owner's
constraint, stated plainly: Infiltration cannot go in `skullpaper/`, cannot
appear in `MAINTENANCE.md`, and must not be announced publicly. The X posts
it runs on stay normal posts -- no "comment to earn" copy, no hint that
replies are being paid for. A promotion scheme that advertises itself as a
promotion scheme is worth nothing to the algorithm it is trying to please,
and reads as farming to everyone else.

This file is served as a 403: `.htaccess` denies `\.(log|txt|md)$` at the
document root. Keep it that way.

The feature itself lives behind the Discord login like everything else. The
leaderboard being member-visible is fine -- no leaderboard on this platform
has ever been public.

---

## 1. What it is

The owner posts a **target**: a link to a Skulliance post on X. Members
reply to it in their own words, submit the link to their reply, and the
platform verifies the reply and pays a DHC Fighters trait.

Replying promptly climbs a rarity ladder. Staying current holds it. The
ladder is the whole mechanic; the trait is the payout.

Likes and reposts cannot be verified and are not tried. Members are trusted
to do those, and nothing depends on it.

---

## 2. Verification, and what X gives us for nothing

Four facts are needed about a reply: the author's handle, their immutable
numeric id, the reply text, and proof it replies to the post we named.

**Measured 2026-11-12, from the live server, via `admin-xprobe.php`:**

| Surface | Handle | User id | Text | Parent |
|---|---|---|---|---|
| `publish.twitter.com/oembed` (official) | yes | no | yes | **no** |
| `cdn.syndication.twimg.com/tweet-result` | yes | yes | yes | **yes** |
| `x.com` page fetched server side | og only | no | og only | **no** |
| `api.fxtwitter.com` (third party) | yes | yes | yes | **no** |
| `api.vxtwitter.com` (third party) | 403 | 403 | 403 | 403 |

**Exactly one surface proves the parent.** That is the single point of
failure in the whole feature, and the reason for section 7.

```
GET https://cdn.syndication.twimg.com/tweet-result?id=<status>&lang=en&token=<anything>
```

Returns, for a reply:

- `user.screen_name` -- the handle, display text only, can be renamed
- `user.id_str` -- the immutable id. **This is the identity.**
- `text` -- the reply, with the `@mention` prefix included
- `display_text_range` -- `[12, 49]`: where the member's OWN words start
  and end. X hands us the mention-stripped slice; we do not parse it.
- `in_reply_to_status_id_str` -- the target post
- `in_reply_to_user_id_str` -- **the target ACCOUNT.** `1494210394541412352`
  is @skulliance. Stronger than the post id alone: it cannot be defeated by
  a lookalike handle and survives a rename.

The `token` parameter is **required but not validated**: `token=a` returns
the full payload, an absent token returns `{}`. `xp_token()` reproduces what
a browser sends anyway, on the theory that the day they start checking is
the day this stops being free.

Timing from the live server: 150-200ms. Fast enough to verify synchronously
on submit, which is the right feel -- paste, verified, trait.

### Linking an X account

Not OAuth. `lib/x-oauth.php` is a complete, tested PKCE flow left
deliberately disabled, because the one call that identifies a signed-in user
(`GET /2/users/me`) is a v2 data endpoint and 403s without pay-per-use
enrolment. The owner's position is firm: nothing that costs money.

So: the platform shows the member a code unique to their Discord account.
They post it as a reply to any Skulliance post and submit the link. The same
syndication call returns `user.id_str`, which is written to `users.x_id`.

The columns already exist -- `x_id`, `x_username`, `x_linked_at`, with a
UNIQUE index on `x_id` -- because `process-x-oauth.php` was built to fill
them. Nothing new is needed.

Stealing somebody's code is a griefing move that costs the thief their own
X link and gains them nothing: the traits go to the Skulliance account, not
the poster. Mitigated by regenerating codes on demand and letting a member
disconnect, which `wallets.php` already does.

---

## 3. A target

Submitted by the owner whenever he likes. **No cadence.** There may be three
in a day and none for a fortnight.

| Field | Note |
|---|---|
| status id | parsed from the pasted URL |
| posted at | when the window starts |
| early window | default 3h, **set per target** |
| main window | default 24h, **set per target** |
| retired | the owner's hand only |

**Nothing expires.** A target stays open forever and can always be
infiltrated late. Targets close only when the owner retires one, for a post
he deleted or no longer wants farmed. A deadline would not make anybody
faster -- the prompt player was driven by a notification, not a rule -- and
would punish the one who was travelling.

The windows are per-target because window length is the only parameter that
can quietly create a caste. A short window rewards timezone and
notification settings rather than effort; 24h rewards the habit of checking
in, which is what we actually want and what survives having a job. A short
one is sometimes right, and should be a knowing choice.

---

## 4. The ladder

One meter, ten notches, four tables. The tables are the platform's existing
`DHCF_TIERS` (`dhcfighters-config.php:1501`) and the band mechanism is the
one the daily streak already uses, so this is config rather than new code.

```
notch   0   1   2   3   4   5   6   7   8   9      bank
table  [--- run ---][ place_10 ][ place_3 ][p_1]   [][][]
                    ^3          ^6         ^9
```

```php
'infiltration' => array(
    'unlisted' => true,
    'base'     => 'run',
    'bands'    => array(3 => 'placement_10', 6 => 'placement_3', 9 => 'placement_1'),
),
```

Per target, four outcomes:

| Outcome | Notches | Why |
|---|---|---|
| Replied inside the early window | **+2** | speed is the only thing that climbs fast |
| Replied inside the main window | **+1** | the ordinary good outcome |
| Replied late | **0** | pays a trait, cancels the miss, does not climb |
| Never replied | **-1** | lands when the main window closes |

Five fast infiltrations to the top table, or nine merely-prompt ones. Both
are a real climb.

**Why notches and not tiers.** There are only four tables. A tier per
infiltration would take a new member from the bottom to the top in two
posts, and the consistency the ladder exists to reward would never be
tested. Three notches to a tier means being fast genuinely moves you up
twice as quickly, which is the owner's instinct, without ending the ladder
in a week.

**Why late is zero and not half.** Earlier draft gave late infiltrations
half credit so that nobody in the wrong timezone could be locked out of the
top. The owner's call is that promptness is what progresses, and the 24h
main window is what keeps that fair -- anybody who opens the site once a day
qualifies. Late still pays a trait and still cancels the penalty, so the
backlog is damage control and a returning member has something to do.

### The overshoot bank

Notches fill the meter first; **overflow goes to the bank**, capped at **3**.
A penalty burns a banked notch before it touches the meter.

So a member pinned at the ceiling who keeps being prompt is not wasting the
effort -- they are building insurance. One missed target burns a pip. Only
once the bank is empty do they start descending.

Capped at 3 deliberately, which is the width of one band: it reads as
exactly one tier of insurance, and covers roughly a two week absence at the
owner's posting rate.

The cap is NOT about trait volume. A bank pays nothing on its own -- nobody
rolls anything without infiltrating a target -- so overshoot buys gaps
without consequence, not a stream of rare traits. The owner's framing, and
it is the reason the bank is worth having: the diligent player's surplus
effort turns into forgiveness instead of being discarded.

The cap is about what the meter MEANS. Uncapped, somebody prompt for six
months could infiltrate once a quarter and still be at the ceiling, and the
meter would stop reading "is current" and start reading "was once diligent".
Three pips covers an absence. It does not replace staying current.

Draws as three pips above a full bar, burning off one at a time before the
bar moves.

### Carryover

The meter and the bank **both carry across months.** Only the leaderboard
rankings reset. The leaderboard is monthly, ranked on notches held.

---

## 5. Comment quality

The reply has to be the member's own words. Three gates, all on the slice
`display_text_range` hands us, so the `@skulliance` prefix never counts:

1. **Minimum length.** Characters of own words, not total.
2. **Not a duplicate of anything ever submitted.** Normalise -- lowercase,
   collapse whitespace, strip punctuation and emoji -- hash, and compare
   against every prior submission platform-wide, not just this target's.
3. **Not a near-duplicate.** Similarity against prior submissions by the
   same member, to stop one person pasting a variant eight times.

X cannot and does not police this. We do, and only because every comment we
pay for passes through here.

---

## 6. Anti-abuse

- **Submitting somebody else's comment fails for free.** `user.id_str` must
  equal the submitter's stored `x_id`. No extra work.
- **The parent must match the target**, by `in_reply_to_status_id_str`, and
  the account must match ours by `in_reply_to_user_id_str`. A mention is not
  a reply and does not count.
- **One submission per member per target**, enforced in the schema.
- **Cache by status id.** One fetch per submission. Rate limits on the
  syndication endpoint are unmeasured; volume here is dozens per target, not
  thousands, but there is no reason to re-ask.

---

## 7. The rule that matters most

**A failed fetch must never read as a failed infiltration.**

A 403, a 404, an empty body or a timeout parks the submission as *pending*
and retries. It does not tell the member their comment is invalid. There is
exactly one surface on earth that proves the parent id, it is undocumented,
and the day it changes every honest player would otherwise be told they are
lying.

Pair it with an admin alert when the pending rate crosses anything at all,
so that day is found out from the panel rather than from Discord.

---

## 8. The faucet

Top table is 8% mythic, 22% legendary. Eight targets a month with fifteen
members pinned at the ceiling is roughly **ten mythics a month**, which is
about what the daily streak already mints (`placement_1` once per 7-day
cycle per sustained player, `db.php:862`). Not a new order of magnitude.

The bank does **not** add to this. It creates no rolls -- a trait comes
only from infiltrating a target -- so its entire effect is that the single
roll following a gap happens at the member's held tier instead of one tier
down. One roll, per gap, one tier. Negligible against the above.

Awards go through `dhcf_award()` with `$announce = false`. The trait reveal
is the member's own modal; nothing posts to Discord naming the mechanic.

---

## 9. Still open

- Minimum own-words length, and the near-duplicate threshold.
- Whether the early window default of 3h is right, or shorter.
- Whether the leaderboard shows the meter publicly or only a rank.

## 10. Schema sketch

`users` needs nothing. New:

- `infil_targets` -- status id, url, label, posted_at, early_mins,
  main_mins, retired
- `infil_submissions` -- user_id, target_id, comment status id, raw text,
  normalised text, hash, speed (`early`|`main`|`late`), notches awarded,
  drop id, state (`verified`|`pending`|`rejected`), verified_at
- `infil_meter` -- user_id, notches, bank, updated_at. Stored rather than
  derived, with `infil_submissions` as the audit trail.
- `x_link_codes` -- user_id, code, issued_at. One live code per member.

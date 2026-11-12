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
They post it as a reply to **one designated post** and submit the link. The
same syndication call returns `user.id_str`, which is written to
`users.x_id`.

```php
/* The pinned post every account link is verified against. */
define('INFIL_LINK_STATUS',  '2120916830747602945');
/* @skulliance. Matched on the id, never the handle -- a handle can be
   renamed and re-registered by somebody else. */
define('INFIL_SKULLIANCE_UID', '1494210394541412352');
```

Verified against the live endpoint 2026-11-12: authored by `@skulliance`
(`user.id_str` = the constant above), **top level and not itself a reply**,
text `💀🆔 https://t.co/lWPZJnbe14`. The cryptic copy is deliberate -- the
instructions live on the platform behind the login, not on X, which is the
same reason a target post carries no "comment to earn" wording.

A link submission must satisfy BOTH:

- `in_reply_to_status_id_str === INFIL_LINK_STATUS`
- `in_reply_to_user_id_str === INFIL_SKULLIANCE_UID`

**THAT POST MUST NEVER BE DELETED OR REPLACED.** Links already made survive,
because `x_id` is written once and kept, but every future link breaks the
moment the id stops resolving. Pinned and left alone.

**A NESTED REPLY WILL FAIL, AND THE ERROR HAS TO SAY WHY.** Replying to
somebody else's comment under the post carries THAT comment as the parent,
not the post, so the check correctly refuses it. Worded as a mismatch it
reads as a broken feature; it needs to say "that is a reply to another
comment, not to the post".

Codes are single use, invalidated the moment a link succeeds, and
regenerable on demand. They are publicly visible under the post, which
costs nothing: a stolen code links the THIEF's x_id to the victim's
Skulliance account, so it hands over their own X identity and collects
nothing -- the traits go to the Skulliance user. Griefing at worst, and
undone by the disconnect `wallets.php` already has.

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
| early window | default **1h**, **set per target** |
| main window | default **24h**, **set per target** |
| retired | the owner's hand only |

**Nothing expires.** A target stays open forever and can always be
infiltrated late. Targets close only when the owner retires one, for a post
he deleted or no longer wants farmed. A deadline would not make anybody
faster -- the prompt player was driven by a notification, not a rule -- and
would punish the one who was travelling.

**THE DEFAULTS ARE RESEARCHED, NOT GUESSED.** See section 10. One hour is
where a reply actually buys reach: Earlybird decays engagement counts with a
half-life around 360 minutes, so a reply at 6h lands on a post at half its
peak visibility and at 24h a sixteenth of it. Twenty-four hours is where a
reply still counts as showing up.

That maps onto the +2 / +1 split exactly: double for the thing that helps,
single for participation.

Per-target because window length is the only parameter that can quietly
create a caste, and an earlier draft of this file defaulted the early window
to 3h on nothing at all. The 1h window does NOT lock anybody out, because
the 24h window still pays +1 and reaches rung 9 in nine targets -- the
sprinters just get there in five.

---

## 4. The ladder

One rung per tier, ten tiers. **This expands `DHCF_TIERS`**
(`dhcfighters-config.php:1501`) from four tables to ten: the existing four
keep their exact values and nothing else on the platform changes behaviour.

```
rung   0    1    2    3     4    5    6    7    8    9     bank
      run  i1   i2  p_10   i4  p_3   i6   i7   i8  p_1   [][][][]...
     0.5%  1%  1.5%  2%  2.5%  3%  4.2% 5.5% 6.8%  8%     uncapped
                          (mythic chance)
```

| # | table | common | uncommon | epic | legendary | mythic |
|---|---|---|---|---|---|---|
| 0 | `run` | 55 | 28 | 13 | 3.5 | 0.5 |
| 1 | `infil_1` | 49.3 | 28.7 | 15.7 | 5.3 | 1 |
| 2 | `infil_2` | 43.7 | 29.3 | 18.3 | 7.2 | 1.5 |
| 3 | `placement_10` | 38 | 30 | 21 | 9 | 2 |
| 4 | `infil_4` | 31.5 | 30 | 24 | 12 | 2.5 |
| 5 | `placement_3` | 25 | 30 | 27 | 15 | 3 |
| 6 | `infil_6` | 22.4 | 28.8 | 27.8 | 16.8 | 4.2 |
| 7 | `infil_7` | 20 | 27.5 | 28.5 | 18.5 | 5.5 |
| 8 | `infil_8` | 17.6 | 26.2 | 29.2 | 20.2 | 6.8 |
| 9 | `placement_1` | 15 | 25 | 30 | 22 | 8 |

Interpolated between the four anchors rather than hand-written; every row
sums to 100. `common` falls, `epic`, `legendary` and `mythic` rise.
`uncommon` HUMPS in the middle and is meant to -- at the top table the
weight moves into epic and legendary, and the existing four already do this
(28, 30, 30, 25). A monotonicity check on that column is the wrong check.

```php
'infiltration' => array(
    'unlisted' => true,
    'base'     => 'run',
    'bands'    => array(
        1 => 'infil_1', 2 => 'infil_2', 3 => 'placement_10',
        4 => 'infil_4', 5 => 'placement_3', 6 => 'infil_6',
        7 => 'infil_7', 8 => 'infil_8', 9 => 'placement_1',
    ),
),
```

One band per rung, so `dhcf_table_for('infiltration', $tier)` resolves the
table from the meter with no new lookup. Same mechanism the daily streak
uses.

Per target, four outcomes:

| Outcome | Tiers | Why |
|---|---|---|
| Replied inside the early window | **+2** | speed is the only thing that climbs fast |
| Replied inside the main window | **+1** | the ordinary good outcome |
| Replied late | **0** | pays a trait, cancels the miss, does not climb |
| Never replied | **-1** | lands when the main window closes |

Five fast infiltrations to the top table, or nine merely-prompt ones.

**Why ten tiers and not four.** An earlier draft of this file put ten
NOTCHES against the existing four tables, banded 3/6/9. It worked, but two
out of every three prompt infiltrations moved a bar and changed no odds at
all -- dead feedback on the one dial the member is staring at. With a tier
per rung, every infiltration changes the odds, and speed buys tiers
directly, which was the owner's instinct before the four-table limit forced
the indirection.

**Rung 9 is `placement_1`, NOT a tier above it.** The owner's call, and the
codebase had already written down why: the guard at
`dhcbosses-harness.php:573` exists because "a tier above placement_1 would
break a ceiling eight games share". A new top table would silently demote
every existing top prize -- winning the Arena, downing a boss crew, filling
the Portal board -- without touching a line of their code. Those are what
the platform is for. Matching the best table is already a strong reward for
commenting on a post.

**Why late is zero and not half.** An earlier draft gave late infiltrations
half credit so nobody in the wrong timezone could be locked out of the top.
The owner's call is that promptness is what progresses, and the 24h main
window is what keeps that fair -- anybody who opens the site once a day
qualifies. Late still pays a trait and still cancels the penalty, so the
backlog is damage control and a returning member has something to do.

### What the expansion touches

Checked, not assumed. Every consumer of `DHCF_TIERS` names its tables
explicitly, so adding rows changes no existing behaviour:

- `dhcp_tables()` (`lib/dhcportal-lib.php:55`) returns a hardcoded four and
  its own comment already anticipates this. The Portal is unaffected.
- `dhcb_tier_table()` (`dhcbosses-lib.php:1714`) returns names by threshold.
- `dropship-traitdrop.php:299`, `dhcfighters-lib.php:391`,
  `admin-dhcbosses.php:359` all index by name.

Two edits are required:

1. **`dhcf_table_rank()`** (`dhcfighters-config.php:1509`) is a hardcoded
   order map that returns 0 for an unknown name. Without the six new names
   in it they would rank as the WORST table and lose the "whichever is
   better" comparisons at lines 1538-1560.
2. **`dhcbosses-harness.php:583`** asserts `dhcf_table_rank('placement_1')
   === 3`. The literal breaks when placement_1 becomes rank 9; the INTENT
   ("placement_1 is the platform ceiling") still holds. Change it to assert
   it is the maximum rank across `DHCF_TIERS`, which is what
   `daily-reward-harness.php:239` already does and is the stronger claim.

`daily-reward-harness.php:239-240` needs no change and is worth knowing
about: it already walks every key of `DHCF_TIERS` and asserts `placement_1`
outranks all of them. That assertion IS the enforcement of the decision
above, and it stays green.

**Why late is zero and not half.** Earlier draft gave late infiltrations
half credit so that nobody in the wrong timezone could be locked out of the
top. The owner's call is that promptness is what progresses, and the 24h
main window is what keeps that fair -- anybody who opens the site once a day
qualifies. Late still pays a trait and still cancels the penalty, so the
backlog is damage control and a returning member has something to do.

### The overshoot bank

Tiers fill the meter first; **overflow goes to the bank**, and the bank is
**uncapped**. A penalty burns a banked pip before it touches the meter, so a
member at rung 9 with pips in hand does not descend.

So a member pinned at the ceiling who keeps being prompt is not wasting the
effort -- they are building insurance. One missed target burns a pip. Only
once the bank is empty do they start descending.

**UNCAPPED, AND AN EARLIER DRAFT OF THIS FILE WAS WRONG ABOUT WHY IT SHOULD
NOT BE.** The owner's call: if somebody is that consistent, let them run it
up and enjoy watching the number climb.

A bank pays nothing on its own -- nobody rolls anything without infiltrating
a target -- so overshoot buys gaps without consequence rather than a stream
of rare traits, and a dormant member with sixty pips still rolls exactly one
trait when they come back. There is no exploit to cap.

The one thing a cap protected was the meter reading "is current" rather than
"was once diligent", and that signal belongs on the leaderboard instead: it
ranks **tiers earned in the month**, not tier held. So the bank runs up
forever as a flex stat and a shield, and the board still shows who is
actually turning up.

Draws as pips above a full bar, burning off one at a time before the bar
moves. Uncapped means the row has to wrap or count once it is long, which is
a UI problem rather than a rule: past some number it reads better as a
figure than as pips.

### Carryover

The meter and the bank **both carry across months.** Only the leaderboard
resets, and it ranks **tiers earned that month** rather than tier held --
which is what keeps a large bank from parking somebody at the top of the
board while dormant.

The board shows **the rank and the meter together** -- the owner's call, and
the meter is the better half of it: a row of ten rungs with a bank trailing
off the end says more about somebody than their position does.

---

## 5. Comment quality

The reply has to be the member's own words. Three gates, all on the slice
`display_text_range` hands us, so the `@skulliance` prefix never counts:

1. **Minimum length: 25 characters AND 4 words** of own words, not total.
   Characters are the primary gate because word counts are trivially gamed
   with single letters; the 4-word floor stops a wall of one repeated
   character, which the duplicate check does not catch on a first offence.

   **CALIBRATED AGAINST A REAL COMMENT.** The reply verified through
   `admin-xprobe.php` was "I did and got a legendary background!" -- 37
   characters, 7 words, and completely genuine. Any minimum that rejects
   that comment is wrong, which rules out the double-digit word counts that
   look reasonable in the abstract. 25 characters passes it comfortably and
   still rejects "nice", "great post" and a lone emoji.

   There is NO reply-length feature in X's ranker (section 10), so this
   threshold is not an algorithmic gate. Its only jobs are to stop one-word
   farming and to leave something worth replying to.
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

- The near-duplicate similarity threshold.
- Names for the six new tables (below).
`infil_*` is honest while only Infiltration uses them; if other games ever
want the finer bands they want neutral names. Cosmetic, and cheap to change
before anything ships.

Settled, for the record: no cadence, no expiry, windows of 1h/24h set per
target, ten tiers topping out AT `placement_1`, +2/+1/0/-1, an uncapped
bank, a monthly leaderboard showing **both rank and the meter**, a 25
character / 4 word floor on own words, and verification through the
syndication endpoint with a pending state rather than a rejection.

## 10. What X actually rewards

Researched 2026-11-12. The weights below are from the ranking code X
published in March 2023 (`github.com/twitter/the-algorithm`), which is the
only published ranking model of any major platform. Everything in this
section that is NOT from that code is marked as such.

### The weights

| Action | Weight |
|---|---|
| Like | 0.5 |
| Repost | 1.0 |
| Reply | **13.5** |
| **Reply the author engages back with** | **75.0** |

Two things follow.

**Replies are the right thing to pay for.** A reply is 27x a like. The
decision to verify comments and merely trust likes and reposts was already
made for other reasons; the weights happen to agree with it.

**THE BIGGEST LEVER IS THE OWNER REPLYING TO THE COMMENTS.** An author reply
turns a 13.5 into a 75 -- 5.5x, and about 150x a like. That is larger than
any effect the ladder itself can have, and it costs one sentence per
comment.

So it should be a screen, not a habit to remember: **the admin tool lists
infiltration comments the owner has not answered yet.** Working through that
list is the highest-value thing in the entire feature.

### Making that cheap for the owner

We cannot reply from our side at any price we are willing to pay: writes
need `tweet.write` on the v2 API. So the job is getting him to the right
place fast, not automating it.

**THE PING IS A DM, AND THE CHANNEL IS FOR THE COMMUNITY.** The owner's
call, and it is the better split: he gets the worklist wherever he is, and
the channel stays free to show members what each other are doing, which is
the point of gamifying it at all.

`sendDM($discord_id, $message, $image_url)` (`message.php:83`) already does
it: it opens a DM channel against `/users/@me/channels` and posts as the
bot. No new webhook, no new credential, no channel to configure.

Two drafts of this file got the destination wrong before landing here. The
first said to use `alertAdmin()` (`webhooks.php:615`) because it "pings him
in a channel members cannot read" -- **it does not.** `alertAdmin()` passes
`$channel = ""`, which falls through to `getWebhook()` (`webhooks.php:491`),
the default members-visible channel; its `ops` flag only suppresses the
mirror into the on-site Activity feed. Followed as written, the thing built
to save the owner from watching X would have announced the mechanic to the
whole server. The second draft fixed that with a private webhook, which
works but was more machinery than a DM needs.

Two things to get right:

1. **IT MUST NOT BLOCK THE SUBMISSION.** `sendDM()` goes through
   `MakeRequest()`, which is a separate path from `skl_discordmsg()` and so
   sits OUTSIDE the deferral queue -- two round trips to Discord with the
   player waiting on both, which is the exact problem the deferral work
   existed to remove. It has to fire after the response is released.
2. **A failed DM must never fail the infiltration.** Privacy settings can
   refuse a bot DM, and by then the trait is already paid.

The DM carries the member's own words inline plus a link to the comment and
a composer aimed at it, which beats X's notification tab because it is only
infiltrations and the text is already there.

### The showcase channel

Members-visible, in Discord, which is consistent with the owner's position
on the leaderboard: in-platform and behind a Discord login is not "public".

**It should LINK each infiltration rather than quote it.** The comment is
already public on X, so a link leaks nothing new -- and every member who
clicks lands on the target thread, inside its decay window, from exactly the
people most likely to add a reply of their own. That makes the showcase a
second reach engine instead of a trophy case. Quoting inline gets the social
proof without the click, which is the weaker half.

The composer link is `https://x.com/intent/post?in_reply_to=<status_id>` --
the same free Web Intent family `lib/share-x.php:66` already uses. No API,
no auth.

**Sort the worklist by the parent's decay, not by arrival.** This is the
part that cannot be done by eye in X, whose notification tab is strictly
chronological. A comment on a 40 minute old post is worth far more than one
on a three day old post, because the parent is still inside its 6h
half-life. Order by parent age ascending and show the remaining window.

**Like everything, reply selectively.** The published definition of the
75-weight label is "the probability the user replies to the Tweet and this
reply is ENGAGED by the Tweet author" -- engaged, not replied-to. Twitter's
engagement taxonomy normally includes likes, so a like probably captures it,
but the README does not spell that out and this file does not claim it does.
A like is one tap and is the floor; a reply is strictly better regardless,
because it also builds the reply depth the ranker rewards separately and
gives the member a reason to come back.

**Do NOT pre-fill the reply text.** Identical or templated replies from
@skulliance under every comment is the pattern that reads as automation, and
it undercuts the one thing members are being asked to do.

### Timing

Earlybird feeds the ranker time-decayed engagement counts
(`timelines.earlybird.decayed_favorite_count` and friends) and indexes a
roughly 7-day realtime window. The decay half-life is around **360 minutes**
-- six hours. A reply at 6h lands on a post at half its peak visibility, at
24h a sixteenth.

Hence 1h and 24h in section 3.

**Discounted as folklore:** the oddly specific thresholds the growth guides
repeat -- "10 replies in the first 15 minutes triggers a viral cascade",
"under 3 engagements and the tweet dies". Those numbers appear in no
published source. The defensible version of the claim is only that early
velocity drives out-of-network distribution.

### Length

**There is no reply-length feature in the ranker.** Nothing is penalised for
being short. What it rewards is reply DEPTH, meaning multi-turn
conversation, which is the author-reply finding again rather than a separate
one.

This is why section 5's floor is 25 characters rather than a word count that
sounds more serious. The floor exists to stop farming and to leave the owner
something to reply to. It is not an algorithmic threshold, because no such
threshold exists.

### Sources

- `github.com/twitter/the-algorithm` -- the published ranking code
- `github.com/twitter/the-algorithm-ml/blob/main/projects/home/recap/README.md`
  -- the heavy ranker's documented weights
- Secondary write-ups were used only to locate constants in the above, and
  are not relied on for any number in this file.

---

## 11. Schema sketch

`users` needs nothing. New:

- `infil_targets` -- status id, url, label, posted_at, early_mins,
  main_mins, retired
- `infil_submissions` -- user_id, target_id, comment status id, raw text,
  normalised text, hash, speed (`early`|`main`|`late`), tiers awarded,
  drop id, state (`verified`|`pending`|`rejected`), verified_at,
  **answered_at**. The last one drives the unanswered-comments screen in
  section 10, which is the highest-value surface in the feature: an author
  reply is worth 75 against the comment's own 13.5. Set by the owner marking
  it, not inferred -- nothing free from X lists the replies to a reply.
- `infil_meter` -- user_id, notches, bank, updated_at. Stored rather than
  derived, with `infil_submissions` as the audit trail.
- `x_link_codes` -- user_id, code, issued_at. One live code per member.

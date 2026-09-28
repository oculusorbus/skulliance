# Multi-chain staking — XRPL

**Status: built, not yet live.** Phase one is wired end to end:

| | |
|---|---|
| `multichain-schema.md` | the migration, run by hand — **not yet run** |
| `verify-xrpl.php` | the XRPL verifier, feeding the existing `processNFT()` |
| `verify-xrpl-harness.php` | its tests: no network, no database |
| `verify-xrpl-probe.php` | finds a collection's issuer and taxon from a wallet |
| `xaman.php` + `ajax/xaman-{start,done}.php` | sign-in |
| `header.php` | the Connect button, QR, and the websocket the browser holds |
| `verify.php` | `verify=xrpl`, a second cron pass |
| `db.php` | `removeUsers()`, `getAllAddresses()`, `getNFTAssetIDs()`, `getCollectionIDs()` chain-scoped; `createNFT()` and `createAddress()` record a chain; `getChainSetting()` |

**To go live, in order:**

**0.** `git pull` on the server. Nothing below works until the code is there.

**1. Run the migration** — `multichain-schema.md`. Safe mid-day: every column
is `DEFAULT 1`, so existing rows become correct the instant it lands and every
existing query keeps returning what it returned before.
**Run the duplicate-`asset_id` check before the unique key** — RFTs share an
`asset_id` by design, so it can legitimately fail. Do not force it.

**2. Add the credentials** to `credentials/db_credentials.php`, beside
`$blockfrost_project_id`:
```php
$xaman_api_key    = "...";   // from https://apps.xaman.dev — free
$xaman_api_secret = "...";
```

**3. Find the collection.** Holding one of its NFTs:
```
php verify-xrpl-probe.php <your r-address>
```
It prints the issuer, taxon, a sample name and image, and the `INSERT`. Run
that INSERT, using the **artist's existing `project_id`** — an XRPL collection
belongs to the same project as their Cardano ones (§3d).

**4. Dry run, before linking anything:**
```
php verify.php verify=xrpl dry=1 addr=<your r-address>
```
Writes nothing. Prints what it would stake next to anything on the ledger that
is **not** registered — which is the check that matters, because an
issuer:taxon off by a digit matches nothing, raises nothing, and looks exactly
like a correct run against an empty wallet.

**5. Link a wallet** through the Connect modal. It verifies **immediately** —
the same way a Cardano connect does — so the confirmation says how many NFTs
are now staking. Nothing to run afterwards.

Then check `wallets` for a row with `blockchain_id = 2` and `nfts` for rows
with `blockchain_id = 2` and your `user_id`.

**That is all.** There is no new cron: the XRPL phase runs inside the existing
nightly job, before the payouts (§5c). Your crontab does not change.

Steps 3 and 4 need no migration, no credentials and no linked wallet, so the
whole ledger-read path can be proven before anything else is touched.

---

## 1. Why

Several Skulliance artists have moved to XRPL to find collectors, and their
collectors have not followed them here because there is nothing here for them.
Maxingo alone has around fifty on XRP — a number that is not a rounding error
against the platform's current active population. It is plausibly a multiple of
it.

The goal is **engagement and players**, not chain coverage for its own sake.
That framing decides the phasing in §8: the cheapest version that could bring
those people in is identity and the free games, and staking can wait until we
know they showed up.

The usual blocker — query cost — does not apply. XRPL's public clusters are
free and unauthenticated, so unlike Blockfrost there is no per-call budget to
design around.

---

## 2. What is already chain-agnostic

Measured rather than assumed, because the answer was better than expected.

Only **14 PHP files** mention `policy_id`, `blockfrost`, `koios` or `cardano`
at all, and several of those are one-off rarity reports and Drop Ship.

The `nfts` table is agnostic in everything that matters. Across `db.php`:

| Column | References |
|---|---|
| `nfts.collection_id` | 66 |
| `nfts.user_id` | 47 |
| `nfts.name` | 25 |
| `nfts.ipfs` | 9 |
| `nfts.asset_id` | 4 |

Staking, points, missions, realms, raids and the marketplace all key on
`collection_id`. **They will work on XRPL rows the day those rows exist**, with
no changes at all. Images already go through `ipfs`, and an XRPL NFToken's URI
is usually an IPFS link — an easier path than the pool.pm one, not a harder
one.

There are exactly **two chain-shaped identifiers** in the schema:
`wallets.stake_address` and `collections.policy`.

---

## 3. Schema

### 3a. The `blockchains` table

```sql
CREATE TABLE IF NOT EXISTS blockchains (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	slug          VARCHAR(32)  NOT NULL UNIQUE,   -- 'cardano', 'xrpl'
	name          VARCHAR(64)  NOT NULL,          -- 'Cardano', 'XRP Ledger'
	api_base      VARCHAR(255) DEFAULT NULL,      -- the node/indexer this chain verifies against
	explorer_nft  VARCHAR(255) DEFAULT NULL,      -- printf template for one NFT's page
	address_re    VARCHAR(255) DEFAULT NULL,      -- validation pattern for an address
	active        TINYINT(1)   NOT NULL DEFAULT 1,
	INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO blockchains (id, slug, name) VALUES
	(1, 'cardano', 'Cardano'),
	(2, 'xrpl',    'XRP Ledger');
```

**What the table is for, and what it is not for.** It holds a chain's
*configuration* — endpoints, URL templates, validation patterns. It does not
hold behaviour. The per-chain verifier stays code (§5). A table that tries to
express "how to read metadata on this chain" will be worse than a switch
statement and much harder to debug.

### 3b. The foreign keys

```sql
ALTER TABLE collections ADD COLUMN blockchain_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE wallets     ADD COLUMN blockchain_id INT NOT NULL DEFAULT 1 AFTER user_id;
ALTER TABLE nfts        ADD COLUMN blockchain_id INT NOT NULL DEFAULT 1 AFTER collection_id;

ALTER TABLE nfts ADD UNIQUE KEY uniq_chain_asset (blockchain_id, asset_id);
ALTER TABLE wallets ADD INDEX idx_chain (blockchain_id, stake_address);
ALTER TABLE collections ADD INDEX idx_chain (blockchain_id);
```

`DEFAULT 1` is what makes this non-breaking: **every existing row is correct
the moment the column lands**, every existing query keeps returning the same
results, and the chain filter gets added call site by call site rather than in
one frightening sweep.

### 3c. Why `nfts` carries it too, even though it is derivable

`nfts.collection_id` already implies the chain, so the column is strictly
redundant. Add it anyway, for one specific reason.

There are **4 bare `WHERE asset_id = '...'` lookups in `db.php`** that are not
scoped by collection. A Cardano `asset_id` is a 56-character policy plus a hex
asset name, so a four-byte name produces exactly 64 characters — the same
length as an XRPL NFTokenID. An actual value collision is astronomically
unlikely, but it is not impossible *by construction*, and the failure mode is
silent: the wrong NFT is returned and nothing errors.

`UNIQUE(blockchain_id, asset_id)` makes it impossible by construction instead
of improbable, and it needs the column. It also spares a join to `collections`
on a hot path purely to learn the chain.

### 3d. Where chain must NOT go

**Not on `projects`.** This is the load-bearing placement decision.

`collections.project_id` already exists, so an artist owns collections and a
collection belongs to a chain. Maxingo's XRPL collectors and his Cardano
collectors then land under the same project, and everything project-level —
Discord roles, `projects.currency`, leaderboards — keeps working across both
with no changes.

Put chain on the project and you split the artist in two, and every
project-level feature grows a special case for ever after.

**Not on `users`.** A player may hold Cardano and XRPL assets at once. Chain is
a property of a collection and of an address, never of a person.

**Never in game code.** Nothing in Monstrocity, Boss Battles, the Arena or
Realms should ever contain `if (chain === ...)`. If a game needs to know, the
abstraction has failed somewhere upstream.

---

## 4. Wallet connection — Xaman

The largest single piece of work, and a **different shape** rather than a
different library. Scoped from the current docs rather than from memory;
sources at the end of this section.

### 4a. What Xaman actually is

Cardano's CIP-30 is a browser extension injecting a provider, and `wallet.js`
reads the stake address straight out of it. Xaman is a phone app with a
**backend API**: the server creates a sign request, the user approves it in the
app, and the server reads the result. The browser only ever shows a QR code or
a deeplink.

So this adds a server-side component the Cardano path does not have. It also
adds a **third-party dependency on the login path** — CIP-30 is local, Xaman is
a service, and if it is down those users cannot connect. Worth knowing before
it happens rather than during.

### 4b. The SignIn pseudo-transaction

`SignIn` is a Xaman-specific pseudo transaction type. It is **signature-only
and is never submitted to the ledger** — it exists purely to prove control of
an account. That is exactly what we need and nothing more: no fee, no ledger
write, no XRP required in the account.

### 4c. The flow, concretely

```
1. POST https://xumm.app/api/v1/platform/payload
     headers: x-api-key, x-api-secret, Content-Type: application/json
     body:    {"txjson": {"TransactionType": "SignIn"}}

   ->  uuid
       next.always            the URL to send the user to
       refs.qr_png            a QR image for desktop
       refs.websocket_status  wss://xumm.app/sign/<uuid>
       pushed                 whether a push reached an existing user

2. Desktop: show refs.qr_png.   Mobile: deeplink to next.always.

3. Wait for resolution — webhook, websocket, or poll the GET.

4. Fetch the payload result. It carries the signed blob and the ACCOUNT
   ADDRESS, which is the thing we actually want.

5. Bind that address to the user and write it to wallets with blockchain_id=2.
```

**Credentials** are an API key and secret from `apps.xumm.dev`, sent as
`x-api-key` and `x-api-secret` headers. The secret is **backend only** — anyone
holding it can create sign requests impersonating Skulliance. It belongs
wherever `$blockfrost_project_id` already lives, never in `wallet.js`.

### 4d. How it fits this codebase

**Use raw curl, not an SDK.** The official SDKs are TypeScript/JS, .NET and
Python — there is no PHP one. That is fine: `verify.php` already talks to Koios
with plain curl, so this is the same pattern with different headers and no new
dependency.

**The BROWSER holds the websocket; PHP never does.** This is the one place the
obvious answer is wrong, and the rate limit is why.

Polling looks natural here — it is what the Arena's live match does — but the
arithmetic kills it. A QR sits on screen for around a minute, so polling every
two seconds is ~30 Xaman calls **per sign-in**. Against a limit of 60–200 a
minute (§4e), that is two to six people signing in at once before the whole
integration starts getting throttled. The day this launches is precisely the
day fifty people try it at once.

`refs.websocket_status` is `wss://xumm.app/sign/<uuid>` and needs **no API
secret** — it is addressed by the payload uuid alone, so the browser can hold
it directly. PHP never opens a socket:

```
  1. browser asks ajax/xaman-start.php   -> server POSTs the payload  (1 call)
  2. browser opens wss://xumm.app/sign/<uuid> itself                  (0 calls)
  3. socket says signed -> browser calls ajax/xaman-done.php
                        -> server GETs the result, binds the wallet  (1 call)
```

**Two Xaman calls per sign-in instead of thirty**, and the rate limit stops
being a launch-day risk at all. Keep a slow poll (every 5s) purely as a
fallback for a blocked websocket; at that rate even the fallback is cheap.

**Bind the uuid to the session when it is created.** Store the payload uuid in
`$_SESSION` at step 1 and refuse any status call for a uuid that session did
not start. Without it, knowing a uuid is enough to claim somebody else's
sign-in.

**Timeouts.** The websocket keepalive reports `expires_in_seconds` and
eventually `{"expired": true}`, and the docs are explicit that this is a **scan
deadline, not a resolution deadline** — a payload the user has already opened
does not die when the timer runs out. The UI should say "QR expired, get
another" rather than "sign-in failed", and it must not delete the pending row
the moment the timer hits zero.

### 4e. Cost — settled, it is free

**The Xaman platform API costs nothing**, and this was checked properly because
the whole plan depended on it.

- **60–200 calls per minute**, free, no account tier and no card. The docs are
  explicit that the figure is an average the platform adjusts on the fly based
  on call behaviour. Higher limits are available by asking support with a
  reason, not by paying.
- **The one paid thing is not this.** XRPL Labs partnered with Dhali to
  monetize **XRPL node/ledger RPC access** — not payloads, not SignIn. Their
  announcement states existing users see "no immediate change" and keep "the
  same robust infrastructure limits"; the paid key only buys unlimited RPC for
  people who exceed the free tier.
- **And we do not need that either.** The rate-limits page itself says to fetch
  ledger data over a native XRPL connection rather than through their platform.
  Verification talks to a public XRPL cluster for `account_nfts` (§5) and never
  touches Xaman, so the Dhali path is irrelevant to this design.

Net: Xaman is touched **twice per wallet link** and never again. Nothing in the
nightly cron goes near it. There is no plausible volume at which this platform
pays Xaman anything.

Sources: [Rate limits](https://docs.xaman.dev/concepts/limitations/rate-limits) ·
[Dhali announcement](https://xaman.app/blog/xaman-partners-with-dhali-to-monetize-xrpl-infrastucture-offering-unlimited-paid-api-access)

Sources: [SignIn](https://docs.xaman.dev/concepts/special-transaction-types/signin) ·
[Authorization](https://docs.xaman.dev/concepts/authorization) ·
[Lifecycle](https://docs.xaman.dev/concepts/payloads-sign-requests/lifecycle) ·
[POST /payload](https://xumm.readme.io/reference/post-payload)

---

## 5. Where the verifier slots in

`verify.php` is a pipeline with a clean seam already in it.

```
  cron ?verify=1
    → getAllAddresses()      db.php   — active users, Diamond Skull owners, delegators
    → getPolicies()          db.php   — SELECT policy FROM collections
    → removeUsers()          db.php   — zero nfts.user_id for everyone about to be re-verified
    → verifyNFTs()           verify.php — POST Koios account_utxos, per address
        → processNFTMetadata()
            → processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint, $address, ...)
```

**`processNFT()` is the boundary, and its signature is already chain-agnostic.**
It takes a policy, an asset name, a display name, an image, a fingerprint and
an address — all normalised, none of them Cardano-shaped by necessity. It is
the function that writes the row.

So the XRPL work is a sibling of `verifyNFTs()`, not a rewrite of the pipeline:

```php
function verifyNFTsXRPL($conn, $addresses, $issuers, $asset_ids, ...) {
    // JSON-RPC account_nfts against the chain's api_base, per address.
    // Paginates by `marker`; limit is 20-400, default 100 — a collector with
    // more than that WILL be truncated if the marker loop is skipped, and the
    // failure is silent (they just appear to own fewer NFTs).
    //
    // Each NFToken returns:
    //   NFTokenID      64 hex, unique across the whole ledger
    //   Issuer         the minting account
    //   NFTokenTaxon   the collection number within that issuer
    //   URI            hex-encoded, usually decodes to ipfs://...
    //   nft_serial     position within the taxon
    //
    // Mapping onto what processNFT() already expects:
    //   policy_id  <- Issuer + ':' + NFTokenTaxon   (an XRPL "collection")
    //   asset_name <- NFTokenID
    //   image/name <- hex-decode URI, then resolve the JSON behind it
    //   → processNFT(...) with blockchain_id = 2
}
```

A collection on XRPL is **issuer + taxon**, not one identifier, so
`collections.policy` holds the pair joined rather than a single hash. Storing
them as one column keeps `getPolicies()` and the collection lookup unchanged;
splitting them into two columns would buy nothing and touch more code.

A happy accident makes this work: Cardano's stake address means one query
returns everything a user holds, and XRPL's `account_nfts` has exactly the same
shape — one call per account, all their NFTokens. The loop structure carries
over unchanged.

### 5a. The cron hazard — `removeUsers()` must become chain-scoped

**This is the one that will cause a silent data loss if it is missed.**

`removeUsers()` sets `nfts.user_id = 0` for every row belonging to a user about
to be re-verified, and `verifyNFTs()` then re-assigns them. It is safe today
because exactly one verifier runs and it restores everything it cleared.

Add a second chain and the invariant breaks. A Cardano run would zero the XRPL
rows and never restore them — every XRPL holder silently loses their staking
the first night it runs, and nothing errors.

```sql
UPDATE nfts SET user_id = 0 WHERE blockchain_id = ? AND ...
```

The same reasoning applies to `getNFTAssetIDs()` and
`cleanupOrphanedProtectedNFTs()`: anything that reasons about "NFTs we expected
to see and did not" must be scoped to the chain that was actually queried.

### 5c. One job, two phases — not two crons

The first design scheduled XRPL as its own cron running before the Cardano
one. **That was wrong, and wrong in money.**

The payout steps at the end of the Cardano block are platform-wide: they read
whatever is staked, on either chain, and pay once. The XRPL pass clears
ownership before rebuilding it. Two separate jobs put a race between those: a
payout landing mid-pass reads a half-rebuilt table — or an empty one, if it
catches the moment after the clear — and underpays. Silently.

A generous gap between crons makes that unlikely, not impossible, and
"unlikely" is the wrong guarantee for a payout.

So the XRPL phase runs **inside** the nightly job, first, before anything that
pays. The ordering is then structural rather than scheduled.

**Isolation is not the same thing as a separate job**, which is what the
earlier design confused. `xrpl_nightly()` never throws and is bounded by a
wall-clock budget, so an XRPL problem costs XRPL holders a night and costs
everybody else nothing — which is all the isolation was ever for.

`verify=xrpl` still exists, for running it by hand and for dry runs. It is not
scheduled.

### 5e. Connecting a wallet verifies it there and then

A Cardano connect links and re-verifies in one request
(`skulliance.php` → `checkAddress()` → `removeUser()` → `verifyNFTs()`), so
XRPL does the same through `xrpl_verify_user()`. Somebody who has just proved
they own something should not be told to come back tomorrow to see it.

Bounded to 45 seconds and caught: if the ledger is slow the wallet stays
linked and the nightly pass picks it up, which is a better answer than a
request that hangs.

**`removeUser()` had the same hazard as `removeUsers()`, and worse.** It
cleared one user's NFTs across *every* chain, and it fires interactively. So
connecting a **Cardano** wallet would zero that user's XRPL rows, and the
Cardano verification that follows would never restore them — they would look
sold until the next night. It is chain-scoped now, and all four call sites
pass 1 explicitly.

### 5d. Read everything, then write — or write nothing

The Cardano pattern clears ownership up front and trusts the verifier to put
it back. That is safe only when nothing can interrupt it.

Here it can be: a slow node, one unreadable address in fifty, a budget
running out. A cleared-then-partially-rebuilt table is not a smaller truth, it
is a **wrong** one, and the payout step reads it.

So the pass reads the whole ledger before touching a row. If **any** address
fails, nothing is cleared and nothing is written — yesterday's rows stand, and
they are correct for everybody who has not traded since. A missed night is
recoverable. A night where half the holders read as having sold everything is
paid out and gone.

`removeUsers()` is injected into the pass rather than called before it, so the
function owns the ordering instead of a comment asking the caller to get it
right. The harness asserts it: a failed read never clears, a clean pass clears
exactly once, and it clears **before** writing rather than after.

### 5b. Failure isolation### 5b. Failure isolation

The chains must fail independently. If XRPL's cluster is unreachable, the
Cardano run must still complete and vice versa — one chain being down must
never cost holders on the other chain their staking for a night. Each verifier
gets its own try/catch and its own "did this run succeed" flag, and
`removeUsers()` for a chain only runs if that chain's verifier is about to run.

---

## 6. Metadata — not a phase-one problem

This was going to be the awkward part, and it turns out not to apply.

`ajax/get-monstrocity-assets.php` parses CIP-25 metadata out of a minting
transaction (the `721` label), and XRPL has no equivalent — an NFToken carries
a hex URI pointing at JSON. Same idea, different fetch, per game.

**But Monstrocity themes are collection-specific, so XRPL collections are not
in Monstrocity.** Nothing else reads trait metadata off a staked NFT: staking
points come from `collections.rate` keyed on `collection_id`, and missions,
realms and raids all key the same way. A stakeable collection needs a name, an
image and a rate — no metadata pipeline at all.

So for phase one this section is empty. The `nfts.ipfs` column still wants
filling, and that is one hex-decode of the NFToken URI at verification time
(§5), not a per-game parser.

It comes back only if an XRPL collection is ever given a Monstrocity theme, or
if some future game reads traits off staked assets. Worth keeping written down
for that day, and worth not building for today.

---

## 6b. Images — already handled, by accident of good design

The local image cache needs **no changes at all**, which is worth writing down
because it is the part that looked like it would need the most.

`lib/image-cache-lib.php` keys everything on
`images/nfts/{project_id}/{collection_id}/{md5(ipfs)}.{ext}` and its query
joins `nfts → collections` for `n.ipfs, n.collection_id, c.project_id`. There
is no chain, no policy and no Cardano concept anywhere in it. It already races
six IPFS gateways, which is exactly what an XRPL image needs, because XRPL
images are IPFS too.

**The one contract that makes it work: `nfts.ipfs` holds a BARE CID.**
`_fetchRace()` builds its URL as `gateway . value`, and `processNFT()` produces
that value by chopping exactly seven characters off the front of the image —
right for `ipfs://`, wrong for everything else. `https://host/a.png` would be
stored as `/host/a.png` and then fetched as
`https://ipfs.io/ipfs//host/a.png`.

So `xrpl_storable_image()` enforces ipfs-or-nothing on the way in:

- `ipfs://<cid>` — stored
- `https://any-gateway/ipfs/<cid>` — folded back to `ipfs://<cid>`
- a bare CID — given the scheme
- anything else (a plain web host, Arweave) — **dropped**, with a log line
- shorter than 46 characters — dropped, because the cache would skip it later
  and further from the cause

Dropping is deliberate. The NFT still stakes and simply has no cached picture,
which is recoverable. A poisoned `nfts.ipfs` is not: it looks like data, it
fails inside a nightly worker, and nothing points at why.

The alternative was loosening `processNFT()`'s `substr(7)` for both chains.
Not worth it for a case Maxingo's collections will not hit — and if a future
collection does host off IPFS, `xrpl_storable_image()` is the single function
to change.

---

## 7. Rarity and traits

Rarity tables are per-collection and derived from on-chain frequency (see
`dhc/gen-rarity.py` → `dhcrarity.php`). A new chain needs its own generation
run per collection, which is the existing process pointed at a different
source. No design change.

Worth deciding early, though: **can an XRPL-derived Fighter fight a
Cardano-derived one in the Arena?** The answer should be yes, and it falls out
for free provided §3d is respected — the Arena reads Fighters, not chains. It
only becomes a question if chain leaks into game code.

---

## 8. Phasing

### Phase one — identity only

No staking, no points, no rewards.

- `blockchains` table and the three FK columns (§3)
- Xaman sign-in (§4, §11.1)
- `verifyNFTsXRPL()` writing rows through `processNFT()` (§5), Maxingo's
  collections only (§11.2)
- `removeUsers()` and friends scoped by chain (§5a)
- The existing **staker role** granted off XRPL holdings (§11.3)
- Access to what is already public and free: the Arena's practice mode, the
  Collection, the Sandbox

Smaller than it first looked, because §6 turned out not to apply: no metadata
pipeline, no per-game parser, no rarity run. A stakeable collection needs a
name, an image and a rate.

It answers the question that decides everything else: **do those fifty people
actually turn up?** If they do, phase
two is obviously worth it. If they do not, the platform has learned that
cheaply and nothing downstream was disturbed.

### Phase two — staking and points

- `collections.rate` per XRPL collection, which already exists and needs nothing
- Points accrual, which keys on `collection_id` and therefore already works
- Missions, realms, raids — all `collection_id`-keyed, so they come along
- Per-game metadata resolution (§6) — only if an XRPL collection is ever given
  a Monstrocity theme, which is not planned

The ordering is deliberate: phase one is the part that is hard to reverse
(schema, identity), phase two is the part that is merely laborious.

---

## 9. Migration surface

Countable, which is the encouraging part:

| Thing | Count | Where |
|---|---|---|
| `stake_address` references | 33 | `db.php` |
| `stake_address` references | 1 | `verify.php` |
| Bare `asset_id` lookups | 4 | `db.php` |
| Files mentioning a chain at all | 14 | repo-wide |

**Keep the column named `stake_address`.** It is a Cardano word holding an XRPL
account address, which is ugly — but renaming it churns 33 call sites to fix a
noun, and `blockchain_id` sitting beside it removes any real ambiguity. Not
worth the diff.

---

## 10. Risks

**The split is the risk, not the code.** Two chains means two verification
crons, two rarity pipelines, two sets of outage behaviour and two ways for a
night's run to go wrong. The mitigation is §3d: chain is a property of a
collection, never of a player and never of a game.

**Xaman is a third-party dependency** on the login path in a way CIP-30 is not.
CIP-30 is a local extension; Xaman is a service. If it is down, those users
cannot connect. Worth knowing before it happens rather than during — it is the
remaining risk in §4 now that cost is settled (§4e), and it is a real one
because it has no mitigation short of also shipping an extension connector.

**Phase one has no revenue and no staking**, so it must not be judged on
points. Its only success metric is whether XRPL holders connect and play.

---

## 11. Decisions taken

1. **Xaman first.** It reaches most XRPL collectors, and reaching Maxingo's
   fifty is the entire point — building the cheap extension connector first
   would serve the minority and prove little. GemWallet/Crossmark remain a
   later addition, not a prerequisite.
2. **Maxingo's collections only at launch.** His collectors are the reason for
   the exercise, and one collection means one rarity run, one metadata shape
   and one thing to debug on the first nightly cron.
3. **XRPL stakers get the existing staker role.** Not a new parallel role and
   not membership — the same role a Cardano staker gets, so nothing about the
   Discord hierarchy changes and there is no second concept to maintain.
4. **Diamond Skull delegation does not apply.** It is a Cardano feature for the
   original six projects and has no XRPL dimension — not deferred, just not
   related.

## 12. Resolved since

- **Points use `collections.rate` exactly as every other collection does.** No
  XRPL-specific mechanics, no separate tuning path — a rate is assigned to the
  collection for its project and accrual is the existing code. This is the
  whole payoff of §2: the points system never learns a second chain exists.
- **Two cron passes, not one.** Cardano and XRPL verify as separate scheduled
  jobs so a failure on one cannot disrupt the other. This makes §5a's scoping
  mandatory rather than merely advisable: each pass zeroes and restores only
  its own chain's rows, and a chain whose verifier did not run must not have
  had its ownership cleared.

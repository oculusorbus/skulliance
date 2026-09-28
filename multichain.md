# Multi-chain staking — XRPL

**Status: a plan, not a description.** Nothing here is built. It records the
shape the work should take and the decisions already made, the way
`dhcarena.md` did before the Arena existed.

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

**Poll, do not websocket.** Xaman offers webhooks, a websocket
(`refs.websocket_status`) and polling. A websocket is awkward to hold open from
PHP on shared hosting, and a webhook means an unauthenticated public endpoint
plus the problem of matching a callback to a browser session. Polling is the
boring option and it is the one the platform already uses everywhere — the
Arena's live match polls an ajax endpoint every 1.5s on exactly this shape:

```
  browser  --2s-->  ajax/xaman-status.php  --> GET the payload  --> resolved?
```

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

### 4e. Unknowns to close before building

- **Cost.** Pricing and free-tier terms are not in the public docs. Historically
  the developer API has been usable without charge, but that is not something
  to assume on the strength of memory — confirm in the dashboard at
  `apps.xumm.dev` before this is scheduled. This is the one open item that
  could change the plan rather than the implementation.
- **Rate limit.** The docs mention roughly **30 payload POSTs per minute** but
  do not document limits fully. Thirty sign-ins a minute is far beyond anything
  this platform will see, so it is a note rather than a constraint.

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

### 5b. Failure isolation

The chains must fail independently. If XRPL's cluster is unreachable, the
Cardano run must still complete and vice versa — one chain being down must
never cost holders on the other chain their staking for a night. Each verifier
gets its own try/catch and its own "did this run succeed" flag, and
`removeUsers()` for a chain only runs if that chain's verifier is about to run.

---

## 6. Metadata — the nuisance

`ajax/get-monstrocity-assets.php` parses CIP-25 metadata out of a minting
transaction (the `721` label). XRPL has no equivalent concept: an NFToken
carries a hex-encoded URI, usually pointing at IPFS-hosted JSON.

Same idea, different fetch, and it is per-game rather than central. This is
where the "many other nuances" live. It is bounded work but it is the part most
likely to be underestimated, because each game that reads traits off-chain
needs its own equivalent.

Mitigation: resolve the URI **once, at verification time**, and store the
result in `nfts.ipfs` and the rarity tables the way Cardano metadata already
is. Then the games read the database, not the chain, and most of them need no
change at all.

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

This is a fraction of the total and it answers the question that decides
everything else: **do those fifty people actually turn up?** If they do, phase
two is obviously worth it. If they do not, the platform has learned that
cheaply and nothing downstream was disturbed.

### Phase two — staking and points

- `collections.rate` per XRPL collection, which already exists and needs nothing
- Points accrual, which keys on `collection_id` and therefore already works
- Missions, realms, raids — all `collection_id`-keyed, so they come along
- Per-game metadata resolution (§6) for anything that reads traits

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
cannot connect. Worth knowing before it happens rather than during.

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

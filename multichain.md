# Multi-chain staking — XRPL

**Status: built, not yet live.** Phase one is wired end to end:

| | |
|---|---|
| `multichain-schema.md` | the migration, run by hand — **not yet run** |
| `verify-xrpl.php` | the XRPL verifier, feeding the existing `processNFT()` |
| `verify-xrpl-harness.php` | its tests: no network, no database |
| `verify-xrpl-probe.php` | finds a collection's issuer and taxon from a wallet |
| `xaman.php` + `ajax/xaman-{start,done}.php` | sign-in |
| `xaman-probe.php` | CLI: are the Xaman credentials good? |
| `verify-xrpl-doctor.php` | CLI: why did this holder's NFTs not show up? |
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
Both are UUIDs, and the console at apps.xaman.dev is signed into **with the
Xaman phone app**, so install it first. Step-by-step in §4a2. Confirm them
with `php xaman-probe.php` before touching the UI.

**3. Find the collection.** An artist will send a marketplace link, so the
slug from it is the whole ask:
```
php verify-xrpl-probe.php bootlegs
```
It also takes an NFTokenID (offline) or an r-address (lists a wallet's
collections, marking which are self-issued). Any of the three prints the
`INSERT`. Run it with the **artist's existing `project_id`** — an XRPL
collection belongs to the same project as their Cardano ones (§3d).

§6d covers why the artist never needs to know what a taxon is.

**4. Dry run, before linking anything:**
```
php verify.php verify=xrpl dry=1 addr=<your r-address>
```
Writes nothing. Prints what it would stake next to anything on the ledger that
is **not** registered — which is the check that matters, because an
issuer:taxon off by a digit matches nothing, raises nothing, and looks exactly
like a correct run against an empty wallet.

**5. Link a wallet** through the Connect modal: **Xaman** (QR, phone),
**Crossmark** / **GemWallet** (shown only if installed), or **Ledger** (shown
where WebHID works — Chrome, Edge, Opera). For a hardware-held account use the
Ledger button; no extension supports hardware wallets (§4g). Any of them
verifies **immediately** —
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
redundant. It is added anyway to spare a join to `collections` on a hot path
purely to learn which chain a row belongs to, and so a verifier pass can be
scoped with a plain `WHERE`.

**An earlier draft justified it differently and was wrong.** It claimed a
collision was possible between a Cardano `asset_id` and an XRPL NFTokenID,
because a policy plus a short hex name is 64 characters and so is an
NFTokenID. But `nfts.asset_id` does not hold policy+name — it holds a **CIP-14
asset fingerprint**, bech32, `asset1...`, around 44 characters
(`processNFT()` takes `$fingerprint`). Against 64 hex characters that cannot
collide by construction, so the four bare `WHERE asset_id = '...'` lookups in
`db.php` are safe unscoped and **no unique key is wanted** — see
`multichain-schema.md`, where live data also shows legitimate duplicates.

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

### 4a2. Getting the API credentials

Free, and the whole thing takes about five minutes. The one thing worth
knowing up front: **the developer console is itself signed into with Xaman**,
so the app has to be on your phone before you can get a key for it.

1. Install **Xaman** (iOS or Android) and create or import an account in it.
2. Go to **https://apps.xaman.dev** and sign in — it shows a QR you scan with
   that app. There is no password login.
3. Create an application. Name and icon are what a user sees on their phone
   when they approve the sign-in, so use Skulliance's.
4. Open it and copy the **API Key** and **API Secret**. Both are UUIDs.

Leave the webhook field empty. Webhooks are for being *told* a payload
resolved; this design reads the result on demand instead, because the browser
is already holding the websocket (§4d) and a webhook would be a second public
endpoint to secure for no gain.

Then, in `credentials/db_credentials.php` beside `$blockfrost_project_id`:

```php
$xaman_api_key    = "1a2b3c4d-5e6f-7081-92a3-b4c5d6e7f809";
$xaman_api_secret = "9f8e7d6c-5b4a-4392-8170-6f5e4d3c2b1a";
```

Plain globals, not constants, matching `$blockfrost_project_id` — `xaman.php`
picks them up with `global`. **The secret never reaches the browser**: anyone
holding it can create sign requests that look like they came from Skulliance.

Check them without touching the UI:

```
php xaman-probe.php
```

It creates one real SignIn payload and prints the QR. Scan it to test the
round trip; ignore it and it expires by itself.

**Why a probe rather than reading the modal's error.** The Connect modal can
only ever say "Could not reach Xaman" — a stranger clicking Connect must not
be told whether the platform's API secret is missing, malformed or revoked.
That is right for the modal and useless during setup, so the specific answer
lives in a CLI tool only the key's owner runs. It names which of the two is
missing, rejects a non-UUID before spending a call, and catches the likeliest
paste error of all: the same value in both slots.

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

### 4d2. Xaman cannot sign for a hardware wallet — a real gap

Found while testing, and it qualifies §11.1 rather than overturning it.

Xaman is **mobile only** — iOS and Android, no browser extension and no
desktop app, whatever the impersonation sites that dominate a search for
"Xaman extension" claim. The flow is built for that and is unaffected.

What is affected: **an XRPL account held on a hardware wallet can only be
imported into Xaman read-only.** Read-only cannot sign, so such a holder
cannot complete a SignIn. The documented workaround is to set a **regular
key** — create a second account, configure it as a signer for the hardware
account, and let Xaman manage that one. It works, and it is far more than a
collector should have to do to link a wallet.

The same applies to anyone whose account lives in Crossmark, GemWallet or
Sologenic rather than Xaman.

**This is why the extension connectors were built** (§4f) rather than left as
a later option. §11.1 chose Xaman first because it reaches the most people,
which was right — but "the most" is not "all", and the ones it misses skew
towards serious collectors. Crossmark and GemWallet are additive, not a
replacement.

A hardware holder therefore adds their Ledger **inside** one of those
extensions. That still means creating a software wallet first, which is a
genuine annoyance and the reason a direct path was attempted — see §4g for why
it was removed again.

**Testing is not blocked by this.** The probe and the dry run take an address
on the command line and need no signature at all, so the whole read and verify
path can be proven against a hardware-wallet address today. Only the link step
needs an account Xaman can sign for.

### 4e2. The Connect modal is two steps

One chain meant one flat list. Two chains in one list would mean a Cardano
holder scrolling past Xaman, Crossmark, GemWallet and Ledger to reach Eternl.
So the modal asks **which chain** first, then shows that chain's wallets as
the same logo tiles the Cardano grid already uses.

**Both chains are always offered — never gated on what is installed.** The
Cardano grid detects extensions because that is all Cardano has. XRPL's main
wallet is **Xaman, a phone app**, so detecting at the chain level would hide
XRPL from every desktop visitor without an extension, which is nearly all of
them. Detection belongs on step two, where it is a statement about wallets
rather than about a chain.

**Signed-in only.** For a logged-out visitor this modal *is* the login, and
login is Cardano — `ajax/xrpl-link.php` needs a `user_id` to link to. Those
pages get the flat Cardano grid exactly as before, with no chain step and no
back button, because adding a click to every sign-in to reach a chain they
cannot use is a cost paid by everyone for nobody.

| step | element | shown |
|---|---|---|
| chain | `#wallet-chain` | signed in |
| Cardano | `#wallet-grid` | always (default when logged out) |
| XRPL | `#wallet-xrpl-list` | signed in |

`walletStep()` in `header.php` owns all three and the header's title and back
button; `wallet.js` calls it rather than touching `#wallet-grid` directly, so
a connect in flight hides every step instead of leaving the chain picker
stacked above the spinner. **"Try Again" returns to the step you were on**,
not to the top.

Two traps worth keeping written down:

- **`hidden` does not hide a `.wallet-panel`.** The attribute is a
  UA-stylesheet `display:none`, which the class's `display:flex` outranks, so
  every undetected tile would show. The tiles use inline `display:none`, and
  detection clears it rather than assigning `flex`.
- **The logos live on the server, not in the repo** — images ship by FTP.
  All six are uploaded and verified 200, but a host that has not had them
  uploaded would show four broken-image glyphs where the wallet logos go, so
  each `<img>` keeps an `onerror` that swaps in a lettermark in the same 52px
  square. They are also `loading="lazy"`: ~100KB of logos should not ride on
  every page view when most visitors never open the modal.

  | `icons/` | source | prep |
  |---|---|---|
  | `cardano.png` | pre-existing | — |
  | `xrp.png` | xrpl.org symbol SVG | recoloured white; it ships `#141414` |
  | `xaman.png` | xaman.app | as-is |
  | `crossmark.png` | crossmark.io (1600²) | downscaled |
  | `gemwallet.png` | gemwallet.app SVG | rasterised |
  | `ledger.png` | ledger.com | **inverted** — black-on-white became white-on-transparent |

  All 256×256 PNG. Square app icons (Xaman, Crossmark) are full-bleed since
  they carry their own rounded corners; bare marks (XRP, GemWallet, Ledger)
  got 10–14% padding so they do not touch the tile edge. **Two would have
  been invisible or wrong if dropped in unmodified** — worth knowing before
  adding a seventh.

### 4f. Browser extensions — Crossmark and GemWallet

Built because §4d2 is not a theoretical gap: the first person to try linking a
real XRPL NFT held it on a Ledger, and Xaman could not sign for it. **GemWallet
is also what xrp.cafe offers**, which makes it what a good share of these NFTs
were bought with — so it is at least as likely to be what a collector already
has as Crossmark is.

| | detect | get the address |
|---|---|---|
| Crossmark | `window.xrpl.isCrossmark` | `sdk.methods.signInAndWait()` |
| GemWallet | `window.gemWallet` | `getAddress()` |

**Detection is a flag, not a probe**, and it runs on a short timer rather than
once at load — an extension's content script can land after the page script
does, and a one-shot check tells somebody who has it installed that they do
not. A button only appears for a wallet actually present.

**The SDKs are vendored and load on click.** Served from our own origin, not a
CDN: these run in a page that can reach a wallet, and `import()` cannot carry
Subresource Integrity, so a pinned CDN version protects against a bad release
but not against a compromised CDN.

**The two are not detected the same way, and assuming they were was a bug.**
Crossmark sets `window.xrpl.isCrossmark` as soon as its content script runs,
so a synchronous check finds it. **GemWallet injects nothing.** Reading its
own SDK: `window.gemWallet` is assigned *by* `isInstalled()`, inside the
page, only after a `postMessage` handshake with the content script comes
back. A `typeof window.gemWallet !== 'undefined'` check can therefore never
be true, and the GemWallet tile never appeared for anybody who had it.

Loading the SDK just to ask would be 63KB on every page view for a question
most visitors never ask, so `gemwalletProbe()` does the handshake directly:

```
-> {source:'GEM_WALLET_MSG_REQUEST', messageId, app:'gem-wallet',
    type:'REQUEST_IS_INSTALLED/V3'}
<- {source:'GEM_WALLET_MSG_RESPONSE', messagedId, isInstalled:true}
```

`messagedId` in the reply is GemWallet's spelling, not a typo here — match
on `messageId` and the promise never resolves. Silence is the normal
negative (nothing answers when the extension is absent), so it times out at
1s and is retried up to three times, because a content script can land after
the page script does.

It also repairs the **connect** path: the SDK rejects every request other
than the install check while `window.gemWallet` is falsy, so setting that
flag is what lets `getAddress()` through at all.

Measured against a real installed GemWallet: reply in **1ms**, the old check
returning **false** on the same page, and a swallowed request resolving
false at 1.3s with no flag set.

**UMD rather than ESM, and the numbers decided it.** Crossmark's ESM graph is
**713 files and 2.8MB** — it pulls the whole of `xrpl.js`, and 49 of those
fetches fail outright. Its UMD bundle is one self-contained 57KB file.
GemWallet's is one 64KB file. Loading on click still keeps both off every page
view for the many people who use neither.

The trade is one ugly global: Crossmark's UMD spreads its exports onto
`window`, so the SDK arrives as `window.default`. That is the price of a
single file over a 713-file graph, and it is written down rather than left to
surprise somebody.

Crossmark signs the same `SignIn` pseudo-transaction Xaman uses:
signature-only, never submitted, no fee, works on an empty account. GemWallet
simply returns the address, and answers a decline with `type: "reject"` rather
than an error — a decline is an answer, not a failure.

**One endpoint, not one per wallet.** `ajax/xrpl-link.php` takes a whitelisted
`via`, because the two differ only in which button was pressed and a file each
would be three copies of the same twenty lines drifting apart. `via` is
whitelisted rather than trusted: it lands in `wallets.link_method`, so a client
must not be able to label itself `xaman` and look server-verified.

#### What it does NOT do, and why that is written down

Xaman's result is fetched **by the server** from Xaman's API. Crossmark's is
reported **by the browser**. We take the browser's word for the address.

That is the bar the Cardano path has always used — `skulliance.php` passes
`$_POST['stakeaddress']` straight to `checkAddress()` — so Crossmark adds no
new *class* of risk. It is still the weaker of the two XRPL paths.

Verifying properly would mean checking an XRPL signature in PHP. Keys are
secp256k1 or ed25519, **this server has no `ext/sodium` on any of its PHP
builds**, and a CLI probe cannot answer what the web SAPI has. So it is left
undone deliberately rather than half-done, and `wallets.link_method` records
which path proved each address so the distinction survives:

| `link_method` | proof |
|---|---|
| `xaman` | server-verified |
| `crossmark` | browser-asserted |
| `gemwallet` | browser-asserted |
| `cip30` | browser-asserted |

If that ever needs tightening, the column means it is a query rather than
asking every holder to link again.

**Worth noting separately:** the Cardano endpoint accepting a POSTed stake
address is a pre-existing platform-wide weakness, not something this work
introduced. It is out of scope here, but it is now written down.

### 4g. Ledger — via xrpl-connect, verified

**No XRPL browser extension supports a hardware wallet.** Not Crossmark, not
GemWallet — checked both, and neither advertises it or has a single issue
mentioning it. A hardware holder cannot add a Ledger "inside" an extension,
which is what I wrongly suggested and what cost an evening of setup that led
nowhere.

What does support Ledger: **XRP Toolkit** and **Sologenic** — web apps that
drive the device over WebUSB/WebHID. They can manage the account but cannot
link it here, because there is no provider for a third-party page to call.

And **`xrpl-connect`**, which is the answer.

#### Why this one and not the raw libraries

A direct WebHID path was built first against `@ledgerhq/hw-app-xrp` and
`hw-transport-webhid`, and removed — those ship no UMD build, so the only
browser-ready form came from esm.sh, which transpiles and rewrites rather than
serving npm's files. That copy could never be checked against npm's published
hashes, and *"I'm not comfortable connecting my ledger or having others
connect their ledger to unverified code"* is the right call.

`xrpl-connect` (XRPL Commons) solves exactly that:

- **Zero dependencies.** Nothing transitive to chase.
- **Self-contained UMD** in the npm tarball — no CDN transformation involved.
- **Tarball verified** against the registry integrity hash by
  `vendor/verify.sh`, same as the other two.
- Bundles `LedgerAdapter` alongside Xaman, Crossmark, GemWallet,
  WalletConnect, Otsu and Xyra.

So the chain of custody is the same one the extension SDKs already have.

#### Two things to know

**It is ~1MB**, against 57KB and 64KB for the extension SDKs. It therefore
loads **only when the Ledger button is clicked**, and the lean paths keep
their lean bundles rather than being consolidated onto it. Consolidation is
available later if maintaining three integrations stops being worth it.

**`window.xrpl` is a name collision, and a nasty one.** xrpl-connect's UMD
wrapper reads `window.xrpl` expecting the **xrpl.js library**; Crossmark
injects `window.xrpl` as its own marker object. Loading the bundle on a
machine with Crossmark installed would hand it Crossmark's marker and treat it
as a library. The loader stashes `window.xrpl`, loads, and puts it back.

*Still needs a real device to confirm.* The package is verified and the
adapter is present in the bundle, but nothing here can prove a Ledger answers
until one is plugged in — and the adapter's address accessor differs across
versions, so the code takes whichever of `address` / `account` /
`getAddress()` is actually populated rather than guessing one and failing
quietly.


### 4h. Vendoring alone does not prove authenticity

Serving our own copy removes the CDN from the **runtime** trust path. It does
not prove that what was fetched **at vendoring time** was genuine — a
compromised CDN at that moment is simply baked in permanently, which is
arguably worse, because it then never changes again.

The chain has to reach something the CDN does not control. npm publishes a
SHA-512 integrity hash per package version, so `vendor/verify.sh` walks:

```
registry integrity  ->  tarball  ->  the file we serve
```

All three vendored bundles pass: byte-identical to the contents of tarballs
whose hashes match what the registry published. jsDelivr is not in that chain
at all — those files could have come from anywhere.

**Every path is either verified or has no client library at all**, which is
the property worth keeping:

| path | client library | verified against npm |
|---|---|---|
| Xaman | none | n/a |
| Crossmark | `vendor/xrpl/crossmark-sdk-0.4.0.umd.js` | yes |
| GemWallet | `vendor/xrpl/gemwallet-api-3.8.0.umd.js` | yes |
| Ledger | `vendor/xrpl/xrpl-connect-0.8.2.umd.js` | yes |

The raw `@ledgerhq` libraries could not meet that bar, which is why the Ledger
path goes through xrpl-connect instead (§4g).


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

## 6c. Fungible tokens — invisible, not broken

The platform stakes fungible tokens as well as NFTs: `processNFT()` detects
several holders of one `asset_id` and creates a row each, which is where the
legitimate duplicates in `nfts` come from. XRPL has fungible tokens too, so
the question is whether that logic misfires.

**It cannot, because the verifier never sees them.**

XRPL keeps the two in completely separate places:

| | XRPL concept | how you read it |
|---|---|---|
| NFT | NFToken (XLS-20) | `account_nfts` |
| fungible | issued currency on a trustline | `account_lines` |

`verifyNFTsXRPL()` calls **only `account_nfts`**, so a trustline balance is
never returned, never reaches `processNFT()`, and cannot create a row. XRPL
fungible tokens are therefore **unsupported, not mishandled** — which is the
safe failure of the two.

**Editioned art is fine and is the common case.** An edition of 100 on XRPL is
100 NFTokens, each with its own globally unique NFTokenID and exactly one
holder. Each gets its own row with one owner, and the RFT branch never fires —
which is correct, because they are not fungible in the ledger's eyes even
though the picture is the same.

So the duplicate-row path stays a Cardano behaviour. That also makes the
unscoped `checkAvailableNFT()` and `updateNFT()` safe: they key on `asset_id`
alone, and a bech32 `asset1...` fingerprint cannot collide with a 64-hex
NFTokenID (§3c).

### What XRPL fungible support would need

Not planned, and worth knowing the shape before anybody assumes it is a small
addition:

- A second read per address, `account_lines`, returning currency + issuer +
  balance
- A collection identity that is **issuer + currency code**, not issuer + taxon
- **A quantity concept the schema does not have.** A trustline holds a
  *balance*, not a set of tokens. Cardano's model works because one row is one
  unit held by one person; a balance of 1,500.25 has no natural representation
  in that shape, and inventing one would touch every staking query rather
  than just the verifier.

That last point is the real work. It is a schema question, not a chain
question.

## 6d. Onboarding a collection — what to ask an artist for

**An NFTokenID. That is the whole ask.**

Artists mint through xrp.cafe and think in **collections**. They do not know
what a taxon is, have never set one, and should not have to. Asking them for
"your issuer address and taxon" is asking them to learn the ledger's internals
to list their own art.

They do not need to, because **the minting tool assigns a taxon per
collection** and it can be read back off any NFT. The grouping they see on
xrp.cafe **is** the taxon; they simply never see the number.

### Two traps, both found on real artist accounts

**`account_nfts` returns what an account HOLDS, not what it CREATED.** For an
active artist most of that is other people's work they bought. Maxi holds 18
NFTs of which only **3** are his own; Darkula holds 62 of which **28** are.
Filter on `Issuer == the artist's address` or you will register somebody
else's collection under their project. xrp.cafe makes the same distinction —
its profile URLs carry `?sort=my_creations`.

**A sold-out collection is invisible.** Maxi minted **261** and holds 3;
Darkula minted **3,569** and holds 62. The NFT Darkula tried to send was from
taxon 0, which does not appear in his held self-issued list at all, because
that collection is fully distributed.

There is **no way to enumerate an issuer's collections from core rippled** —
that needs an indexer. Which is why the NFTokenID form of the probe is the
onboarding path and the address form is only a convenience: one NFT works
whether the collection sold out or not.

So the workflow is:

1. Artist sends a link to **any one NFT** in the collection
2. `php verify-xrpl-probe.php <NFTokenID>`
3. Run the `INSERT` it prints

No wallet, no transfer, nothing delivered, no technical vocabulary. An
NFTokenID is 64 hex characters packing flags, transfer fee, issuer, taxon and
sequence — `xrpl_decode_nftoken_id()` unpacks it offline.

**The taxon is scrambled inside the id.** XLS-20 mixes it with the sequence so
sequential mints do not produce adjacent ids; `account_nfts` returns it
already unscrambled, which is why the arithmetic lives in exactly one
function. Get it wrong and you get a plausible-looking wrong number rather
than an error — so the decoder is checked against ids whose taxons were
confirmed independently through `account_nfts`.

### The taxon-0 caveat, in proportion

An earlier draft warned that an artist using taxon 0 for everything would make
`issuer:0` mean their entire output. Possible in principle, and not what
either artist does — both spread across many taxons.

The probe prints the taxon before anything is registered, so the check is: if
an artist's collections all come back `:0`, ask. Otherwise proceed.

### The two Skulliance artists on XRPL

| | address | minted | own collections seen |
|---|---|---|---|
| Maxi | `rhnNSggjJfXXM4AE5g87uLGoUH72GAsnVw` | 261 | `:2000` |
| Darkula | `rBbUQ5vutUQEDj911fQxGNWzxwzah8urLJ` | 3,569 | `:2` `:4` `:10` `:11` `:12` (and `:0`, sold out) |

"Own collections seen" is only what each still holds — both have minted far
more than they hold, so treat it as a floor rather than a list. Ask each artist
which collections they want listed and get one NFT id per collection.

## 6d2. First collection: Maxi's Bootlegs

The one to start with, and the numbers are the reason:

| | |
|---|---|
| name | DIGI-HELL CORPS! (Bootlegs) |
| issuer | `rhnNSggjJfXXM4AE5g87uLGoUH72GAsnVw` |
| taxon | `3000` |
| key | `rhnNSggjJfXXM4AE5g87uLGoUH72GAsnVw:3000` |
| size | 125 NFTs across **36 holders** |
| xrp.cafe | verified, slug `bootlegs` |

**36 holders is the whole point of the exercise** — potentially 36 people who
have never had an account here. One collection also keeps the first run
small: one rarity shape, one metadata format, one thing to debug on the first
nightly pass.

```sql
INSERT INTO collections (blockchain_id, project_id, name, policy, rate) VALUES
  (2, <maxi's project_id>, 'DIGI-HELL CORPS! (Bootlegs)',
   'rhnNSggjJfXXM4AE5g87uLGoUH72GAsnVw:3000', <rate>);
```

`project_id` is Maxi's **existing** Cardano project (§3d). Find it with:

```sql
SELECT id, name FROM projects WHERE name LIKE '%axi%';
```

## 6d3. When a holder sees nothing

```
php verify-xrpl-doctor.php                       # who has linked one
php verify-xrpl-doctor.php <r-address | user_id> # why theirs is empty
```

Run it with no argument first. You almost never have the address when you
need this — the report arrives as "I connected and nothing showed up", and
asking a tester to go find their r-address is another round trip while they
are still willing to help. Whoever just linked is the last row.

**Six things can break here and five of them fail silently** — a collection
registered on the wrong chain, a taxon off by one, a wallet row written with
`blockchain_id` 1, a holder looking at a different address than the one they
linked, an NFT still sitting in an unaccepted sell offer, a node that would
not answer. Every one produces the identical symptom: zero NFTs, no error,
nothing in a log. Checking them one query at a time is how an evening goes.

The doctor walks all four layers in order — registered collections, the
wallet row, the live ledger, the stored rows — and prints a verdict with the
`UPDATE` to run. It writes nothing, so it is safe while somebody is waiting
on the other end of a chat.

**The first suspect is almost always `blockchain_id`.** Both
`collections.blockchain_id` and `wallets.blockchain_id` DEFAULT to 1, which
is what makes the migration safe to run mid-day (§2 of the schema) — and
also means any row added without naming the column looks perfect and is
invisible to the XRPL pass. The doctor flags an `issuer:taxon` policy or an
r-address filed under chain 1 specifically.

## 6d4. The chain id has to reach the INSERT

`createNFT()` takes `$blockchain_id` last and defaults it to 1, which is what
keeps every Cardano caller unchanged. `processNFT()` sat in between and did
not pass it, so **every XRPL NFT was written as Cardano** — a row that looks
perfect and errors nowhere, wrong in three compounding ways:

1. `getNFTAssetIDs($conn, 2)` never sees it, so the next XRPL pass thinks the
   NFT is new and creates **another** row. Every run.
2. `removeUser($conn, $user, 2)` never clears it, so ownership never resets
   when the holder sells.
3. `removeUsers($conn, 1)` **does** zero it. The Cardano pass clears it and
   cannot put it back, because Koios has never heard of it. The holder's NFTs
   disappear on the first nightly run after linking.

Fixed by threading `$blockchain_id` through `processNFT()`, same shape as
`createNFT()`: last parameter, default 1.

**The harness passed the whole time**, and that is the lesson worth keeping.
PHP silently discards extra arguments to a user-defined function, so the
ten-parameter stub swallowed an eleventh without a word. The stub now takes
`$blockchain_id`, records it, and asserts every row carries 2 — verified by
removing the argument again and watching it fail.

Rows written before the fix are repaired from their collection, which is the
authority:

```sql
UPDATE nfts n JOIN collections c ON c.id = n.collection_id
   SET n.blockchain_id = c.blockchain_id
 WHERE c.blockchain_id = 2 AND n.blockchain_id <> 2;
```

`verify-xrpl-doctor.php` §1b finds them and prints this.

## 6d5. Metadata must be fetched in parallel

The first real holder linked, matched 20 Bootlegs, and got nothing. Measured
against their actual wallet:

| | serial | parallel |
|---|---|---|
| read 498 NFTs from the ledger | 1.5s | 1.5s |
| resolve 20 metadata documents | **111s** | **14s** |
| whole request | **113s** | **16s** |

PHP's default `max_execution_time` is **30s**. The request was killed partway
— *after* `removeUser()` and *before* the writes — so the holder saw nothing,
the row count stayed zero, and nothing was logged. Every individual piece was
correct: right collection, right key, right wallet row, 20 matches on the
ledger. It was only ever the wall clock.

**IPFS gateways are slow individually and perfectly happy in parallel**, so
the sum was never the number that mattered. `xrpl_resolve_many()` asks the
preferred gateway for the whole batch, then asks the next gateway only for
what the first could not answer — one round trip per gateway for the batch
instead of one per NFT. `XRPL_FETCH_CONCURRENCY` caps it at 12 in flight so a
large holder does not get the server rate-limited by every gateway at once,
which is the problem this is meant to avoid rather than cause.

**The harness keeps its serial path.** A custom `$fetch` is looped one URL at
a time — same answers, same order, no network — and only the real fetcher
takes the `curl_multi` route. The tests did not change; production stopped
being serial.

Both link endpoints also `@set_time_limit(90)`. The real limit is
`xrpl_verify_user()`'s 45s budget, which now covers the metadata rounds too
and degrades to synthesised names rather than dying; the PHP ceiling just
must not pull the rug out from under it first.

## 6d6. `animation` is where an animated piece lives

XLS-24 metadata has separate slots — `image`, `animation`, `video`, `audio`,
`3d_model` — and an artist minting an animated GIF fills `animation` and
leaves **`image` an empty string**, not absent. Reading only `image`
therefore returned nothing for exactly the pieces somebody put the most work
into, with no error anywhere: the NFT staked, the tile was blank. Three of
the first holder's twenty Bootlegs are built that way. With `animation`
read, it is 20 of 20.

Only `animation` was added. `video`, `audio` and `3d_model` are deliberately
**not** read — the image cache's mime whitelist is jpeg/png/gif/svg/webp and
would skip them, so storing one would be a CID that can only ever fail,
which is worse than no CID because it looks like data.

Animated GIFs need nothing further: `lib/image-cache-lib.php` already
coalesces, resizes every frame and rebuilds the animation, under a 256MB
Imagick limit with a catch. Checked against the real files — 8MB and 12MB
GIFs at 2500×2500.

## 6d7. Two things that were Cardano-shaped in the UI

The NFTs loaded and the showcase was still wrong in two ways, both of them
invisible on Cardano because Cardano has been the only chain for years.

**Every image was broken.** `getIPFS()` falls back to a public gateway when
there is no cached file, and that fallback was hardcoded to `ipfs.io` —
which answers **429** for this server's IP, as does `dweb.link` (same
operator). So the fallback was a *guaranteed* broken image. It never showed
because Cardano's images were all cached years ago; a new XRPL holder hits
nothing but uncached images. Measured against the first holder's twenty:

| gateway | result |
|---|---|
| ipfs.io | 429 |
| dweb.link | 429 |
| cloudflare-ipfs.com | dead |
| nftstorage.link | 302 |
| gateway.pinata.cloud | **200, every one** |

Now `IPFS_FALLBACK_GATEWAY`, defaulting to Pinata and overridable in
`credentials/`. It is still only a stopgap — `image-cache.php` storing the
file locally is what the browser should be hitting, and **it must be run
after a new holder verifies** or they wait until the nightly pass.

**Every NFT linked to pool.pm**, a Cardano explorer that has never heard of
an NFTokenID. `nftExplorerUrl()` reads `blockchains.explorer_nft` — the
template column that existed for exactly this and was not yet used — once
per request and caches it, because this is called inside a render loop and a
query per tile is how a gallery page gets slow. `accountExplorerUrl()` does
the same for the wallets page, in code rather than a column, since there is
no `explorer_account` and adding one is not a migration to run mid-incident.

To point XRPL at the marketplace rather than Bithomp:

```sql
UPDATE blockchains SET explorer_nft = 'https://xrp.cafe/nft/%s' WHERE id = 2;
```

The code defaults to that anyway if the column is empty, so it never returns
a broken href.

`ajax/get-nft-assets.php` needed nothing: it already rejects an `asset_id`
that does not start with `asset`, so the Cardano-only gallery pipeline
excludes XRPL by construction.

## 6d8. An r-address filed as Cardano

Hours after the XRPL pass ran, the Cardano cron started alerting:

```
Failed to decode Bech32 string: Parse(Char(InvalidChar('i')))
There was no response after 4 attempts for stake address:
https://pool.pm/rLRRH3TvxPRtjuPsU3RfxcgfkXpiTfYNiY
```

Ripple's base58 alphabet contains `i`; bech32 does not. That is an XRPL
account sitting in `wallets` with `blockchain_id = 1`.

**`getAllAddresses()` was not at fault** — it filters on `blockchain_id`
correctly. The row itself is mislabelled, and the way in is
`checkAddress()`: it calls `createAddress()` without a chain, which defaults
to 1, and it validated nothing. Anything POSTed to the Cardano connect
endpoint became a Cardano wallet.

Two fixes, because one of them is not enough:

1. **`checkAddress()` now returns `'invalid'`** for anything that is not
   bech32 `stake1`/`addr1` (with the `_test1` forms allowed, so a testnet
   wallet is not rejected confusingly). `wallet-ajax.php` turns that into
   *"That is not a Cardano address. If it is an XRPL account, use the XRPL
   option"* — which names the fix, since reaching that path with an XRPL
   account in hand is the likeliest way to get there.
2. **The Cardano pass skips non-Cardano-shaped addresses** before calling
   Koios, and says so. Rows written before fix 1 still exist, and a
   mislabelled row must not cost the job that pays everybody four retries
   and the operator a false alarm. Logged rather than dropped silently: a
   skipped address is somebody not being paid.

Repair the row itself:

```sql
UPDATE wallets SET blockchain_id = 2
 WHERE stake_address LIKE 'r%' AND blockchain_id = 1;
```

`verify-xrpl-doctor.php` with no argument lists r-addresses and flags any on
the wrong chain.

## 6e. IPFS gateways — one is not enough

A sample of Maxi's art first resolved with no name and no image, which looked
like an artist whose metadata was missing. It was not. The metadata is rich:

```json
"name": "DH_Landfill_1 #5",
"image": "ipfs://QmR9gMnXUm93e7D2n2257WfpJwAUwrs9esEcagGzpEWMZt",
"collection": { "name": "404s" },
"attributes": [{ "trait_type": "Artist", "value": "MadMaxi (404)" }]
```

**`ipfs.io` returns 429 Too Many Requests** under any real load, and
`dweb.link` — same operator — returns it in the same breath. The resolver was
pointed at `ipfs.io` alone, so it failed in bursts, and the failure presented
as *"this artist has no metadata"* rather than *"the gateway is busy"*.

That is not cosmetic. `processNFT()` skips an NFT with no name, and an NFT
with no picture is one nobody wants to stake — seeing the art is the entire
point.

`xrpl_resolve_metadata()` now falls back across the **same six gateways
`lib/image-cache-lib.php` already races**, proven against this platform's
traffic for longer than the XRPL code has existed. Pinata answered in 4.4s
while ipfs.io and dweb.link both 429'd; the others redirect to subdomain form,
which is fine because `CURLOPT_FOLLOWLOCATION` is set.

### The metadata names the collection

XRPL art metadata carries `collection.name` — "404s" for that sample. The
probe reads it and pre-fills the `INSERT`:

```
INSERT INTO collections (blockchain_id, project_id, name, policy, rate)
VALUES (2, <project_id>, '404s', 'rhnNSggjJfXXM4AE5g87uLGoUH72GAsnVw:2000', <rate>);
```

So the collection is listed under **the name the artist gave it**, rather than
whatever somebody types at the point of running the query.

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

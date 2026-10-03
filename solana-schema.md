# Solana — the migration, run by hand

Design and reasoning: `multichain.md`. This is the whole database change for
adding Solana as chain 3, and it is one `INSERT` plus one `INSERT`, because
the XRPL work already did the structural part.

**Nothing here is destructive and nothing has a down-time window.** Run it
mid-day if you like.

---

## 1. The chain row

Everything else keys off this. Without it `getChainSetting()` returns its
defaults, which happen to be correct, so the pass would *work* — and then
`verify-solana-doctor.php` would have nothing to show and the Collections
page would have no explorer link. Add the row.

```sql
INSERT INTO blockchains (id, slug, name, api_base, explorer_nft, ipfs_gateway, active)
VALUES (3, 'solana', 'Solana',
        'https://api.mainnet-beta.solana.com',
        'https://solscan.io/token/%s',
        'https://ipfs.io/ipfs/',
        1);
```

> The columns are `api_base`, `explorer_nft`, `ipfs_gateway`, `active` —
> **taken from `multichain-schema.md`, the migration that was actually run**,
> not from `multichain.md` §3a, whose sketch carried an `address_re` that was
> never created. Getting that wrong is a `#1054 Unknown column` and it
> happened on the first attempt. `getChainSetting()` whitelists exactly those
> three settings, which is the other place to check.

`api_base` is the **public cluster**, which answers unauthenticated and is
what every measurement in `multichain.md` was taken against. It is also rate
limited and Solana's own documentation says not to lean on it in production.
If Omen's holders arrive in numbers, swap this one column for a provider
endpoint (Helius has a free tier that serves `getProgramAccounts`) — no code
changes, because nothing hard-codes it.

**There is no address-pattern column, by design.** Validation is code:
`sol_valid_address()` decodes the address and requires exactly 32 bytes,
because a 44-character string of legal base58 characters can still decode to
33 and a regex cannot see that.

`ipfs_gateway` is **unused by OMEN**, which serves its art and metadata over
plain https. It is set anyway because it is what chains 1 and 2 carry, and
because a later Solana collection that does use IPFS gets this gateway tried
FIRST, ahead of the built-in list — which is the whole point of the column
being a column.

---

## 2. The columns — already there

`blockchain_id` was added to `collections`, `wallets` and `nfts` by the XRPL
migration (`multichain-schema.md` §2), all `DEFAULT 1`. Solana needs no new
column at all.

Confirm, if you want to be sure before the first pass:

```sql
SHOW COLUMNS FROM nfts LIKE 'blockchain_id';
SHOW COLUMNS FROM nfts LIKE 'asset_id';
```

`asset_id` must hold **44** characters. A Solana Core asset id is a base58
pubkey, up to 44; the XRPL work widened this column to 64 for NFTokenIDs, so
it already passes. `sol_check_schema()` checks it on every run anyway and
**refuses to write** rather than let MySQL truncate silently — a truncated id
never matches on the next pass, so every run would re-insert the whole
collection.

---

## 3. The collection

Do not write this by hand. The probe reads it off the chain and prints it:

```
php verify-solana-probe.php Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2
```

Any address from the collection works — the collection account, one asset, or
a holder's wallet — because nobody should have to know what an MPL Core
collection account is to answer "which collection is this?".

For OMEN it prints:

```sql
INSERT INTO collections (blockchain_id, project_id, name, policy, rate)
VALUES (3, <project_id>, 'OMEN', 'Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2', <rate>);
```

**`project_id` is Omen's EXISTING project**, the one its Cardano collection
already belongs to — not a new one. That is the placement decision in
`multichain.md` §3d: an artist owns collections, a collection belongs to a
chain, and splitting the artist by chain grows a special case in every
project-level feature for ever after.

**Leave `marketplace_slug` NULL.** It exists for XRPL, where a collection is
`issuer:taxon` and xrp.cafe's slug is an artist-chosen vanity string with no
derivation in either direction — twenty-one of those had to be hunted by hand
after the fact. A Solana collection *is* an address, and that address is what
marketplaces route by, so `collectionMarketUrl()` builds the link from the
policy exactly as it does for Cardano. Setting a slug is an override, not a
requirement: Tensor's `/trade/` accepts either form.

**`rate` is a judgement, and it is the only number here that can cost
money.** The OMEN collection on Solana is **7,209 assets**. Every one of them
earns this rate every day from the night it is registered, and a good share
of them belong to people who have never staked here. Pick it against the
reward pool, not against what a comparable Cardano collection pays.

---

## 4. Checks, in order, before and after

Each of these needs only the step above it.

```
php verify-solana-probe.php <address>            # no database, no wallet
php verify.php verify=solana dry=1 addr=<addr>   # reads, writes nothing
php verify.php verify=solana                     # the real pass, by hand
php verify-solana-doctor.php                     # who is linked, and why not
```

The dry run is the one that matters, and specifically the part of its output
headed **"held, but NOT registered"**. A collection address off by one
character matches nothing, raises nothing, and looks exactly like a correct
run against a wallet that holds none of it. Listing what the chain says the
wallet holds that we did not recognise is the only way to tell those apart.

---

## 5. Rolling it back

Delete the collections row. Holdings stop being matched that night, nothing
else changes, and the `nfts` rows can be cleared at leisure:

```sql
DELETE FROM collections WHERE blockchain_id = 3;
DELETE FROM nfts        WHERE blockchain_id = 3;
UPDATE blockchains SET active = 0 WHERE id = 3;
```

Leaving the `blockchains` row in place with `active = 0` is deliberate: the
id is referenced by any `wallets` rows people already linked, and renumbering
chains later is far worse than an inactive row.

# Multi-chain — database schema

Run once against the live database, by hand. Nothing in the app creates or
alters tables, same as `dhcarena-schema.md` and `dhcfighters-schema.md`.

Design and reasoning: `multichain.md`.

**Confirm the existing column definitions before running this.** The ALTERs
below were written from reading queries rather than from a schema dump, so
check that `collections`, `wallets` and `nfts` look the way this expects.

---

## 1. The chain table

```sql
CREATE TABLE IF NOT EXISTS blockchains (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	slug          VARCHAR(32)  NOT NULL UNIQUE,   -- 'cardano', 'xrpl'
	name          VARCHAR(64)  NOT NULL,
	api_base      VARCHAR(255) DEFAULT NULL,      -- what the verifier talks to
	explorer_nft  VARCHAR(255) DEFAULT NULL,      -- printf template, %s = asset id
	ipfs_gateway  VARCHAR(255) DEFAULT NULL,      -- how ipfs:// is resolved for this chain
	active        TINYINT(1)   NOT NULL DEFAULT 1,
	INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO blockchains (id, slug, name, api_base, explorer_nft, ipfs_gateway) VALUES
	(1, 'cardano', 'Cardano', 'https://api.koios.rest/api/v1',
	    'https://pool.pm/%s', 'https://ipfs.io/ipfs/'),
	(2, 'xrpl', 'XRP Ledger', 'https://xrplcluster.com',
	    'https://bithomp.com/en/nft/%s', 'https://ipfs.io/ipfs/')
ON DUPLICATE KEY UPDATE name = VALUES(name);
```

`api_base` for XRPL is a public JSON-RPC cluster. It is a column rather than a
constant so a node can be swapped without a deploy — if `xrplcluster.com` is
slow one night, point it at `s1.ripple.com` and the next cron pass uses it.

## 2. The foreign keys

```sql
ALTER TABLE collections ADD COLUMN blockchain_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE wallets     ADD COLUMN blockchain_id INT NOT NULL DEFAULT 1 AFTER user_id;
ALTER TABLE nfts        ADD COLUMN blockchain_id INT NOT NULL DEFAULT 1 AFTER collection_id;

ALTER TABLE collections ADD INDEX idx_chain (blockchain_id);
ALTER TABLE wallets     ADD INDEX idx_chain (blockchain_id, stake_address);
ALTER TABLE nfts        ADD INDEX idx_chain (blockchain_id);
```

**`DEFAULT 1` is what makes this safe to run on live in the middle of the day.**
Every existing row is Cardano and becomes correct the instant the column lands.
Every existing query keeps returning exactly what it returned before. The chain
filters get added call site by call site afterwards, not in one sweep.

### The unique key, and why it comes later

```sql
-- Run this only AFTER the columns are populated and you have checked for
-- duplicates. It will fail loudly if two rows already share an asset_id.
ALTER TABLE nfts ADD UNIQUE KEY uniq_chain_asset (blockchain_id, asset_id);
```

There are four bare `WHERE asset_id = '...'` lookups in `db.php` that are not
scoped by collection. A Cardano `asset_id` is a 56-character policy plus a hex
name, so a four-byte name is exactly 64 characters — the same length as an XRPL
NFTokenID. A real collision is vanishingly unlikely but not impossible by
construction, and the failure is silent: the wrong NFT comes back.

This key makes it impossible instead of improbable. It is separated from the
block above because **it can fail on existing data** — RFTs deliberately create
multiple rows sharing an `asset_id` (see `processNFT()`), so check first:

```sql
SELECT asset_id, COUNT(*) c FROM nfts GROUP BY asset_id HAVING c > 1 LIMIT 20;
```

If that returns rows, the unique key is wrong for this schema and the four
lookups should be scoped by `blockchain_id` in code instead. **Check before
running it; do not force it.**

## 2b. How a wallet was proved

```sql
ALTER TABLE wallets ADD COLUMN link_method VARCHAR(16) DEFAULT NULL AFTER blockchain_id;
```

Nullable, because every existing row predates the question.

**The three link paths do not prove the same thing, and the column records
which one was used.**

| value | path | proof |
|---|---|---|
| `xaman` | Xaman SignIn | the SERVER reads the result from Xaman's API |
| `crossmark` | Crossmark extension | the BROWSER reports an address |
| `gemwallet` | GemWallet extension | the BROWSER reports an address |
| `cip30` | Cardano extension | the BROWSER reports an address |

Only the first is cryptographically verified server-side. The other two are
assertions: the endpoint receives an address in a POST and takes it, exactly
as `checkAddress()` has always done for Cardano.

That is a pre-existing platform-wide property, not something Crossmark
introduces — `skulliance.php` passes `$_POST['stakeaddress']` straight to
`checkAddress()` today. But it is worth being able to TELL THE PATHS APART
after the fact, and impossible to reconstruct later if it is not written down
at the time. One column now means a future policy ("only server-verified
addresses count for X") is a query rather than asking everybody to re-link.

## 3. XRPL collections

A collection on XRPL is **issuer + taxon**, stored joined so `getPolicies()`
and `getCollectionIDs()` keep working unchanged — they treat `policy` as an
opaque string and never parse it.

```sql
-- One row per XRPL collection. Values come from the artist.
INSERT INTO collections (blockchain_id, project_id, name, policy, rate) VALUES
	(2, <project_id>, '<collection name>', '<issuer>:<taxon>', <rate>);
```

`project_id` is the artist's **existing** project — an XRPL collection sits
under the same project as their Cardano ones, which is what keeps one artist
from becoming two. See `multichain.md` §3d.

## 4. What still needs a code change

The schema alone is not enough; `createNFT()` inserts without `blockchain_id`,
so an XRPL row would take the `DEFAULT 1` and claim to be Cardano. It takes an
optional final parameter now, defaulting to 1 so every existing caller is
unchanged.

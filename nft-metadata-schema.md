# NFT metadata — database schema

Run once against the live database. Nothing in the app creates columns, same
as every other feature here.

**Two columns on `nfts`, no new table.** Metadata is a property of the asset
and `nfts` is really an ownership row, so a side table keyed on `asset_id`
was the first instinct. It was wrong, and the numbers say so: there is no
`SELECT *` anywhere against `nfts` (checked, including `nfts.*` and joined
selects), InnoDB keeps a large JSON value off-page so narrow reads never
touch it, and a full CIP-25 object for one asset is **551 bytes**. The only
duplication is for genuinely multi-holder assets — `checkAvailableNFT()`
reuses an unowned row before creating a second one, so a 1-of-1 collection
has exactly one row per asset forever. A side table would have bought
nothing and added a join, an orphan case, and a second thing to keep in
sync, which is this platform's most repeated bug.

```sql
ALTER TABLE nfts
  ADD COLUMN metadata JSON NULL AFTER ipfs,
  ADD COLUMN metadata_at DATETIME NULL AFTER metadata,
  ADD INDEX idx_nfts_metadata_at (metadata_at);
```

If the server is on MariaDB, or MySQL older than 5.7, use `LONGTEXT NULL`
instead of `JSON`. Nothing in the code calls a JSON function — the column is
read with `json_decode()` in PHP — so the type is storage only and either
works.

## Why `metadata_at` is not optional

It is the difference between **never fetched** and **fetched, and this asset
genuinely has no traits**. Without it the backfill cannot tell what it still
owes, cannot be stopped and resumed, and a collection whose metadata is
simply sparse is indistinguishable from one the crawl has never reached. The
same distinction the boss ladder and the outreach stats both needed: a null
that means *unknown* has to be separable from a value that means *nothing
there*.

The index is what makes `WHERE metadata_at IS NULL LIMIT n` cheap, which is
the backfill's only query and the thing it runs thousands of times.

## What goes in it

The **raw document**, not parsed traits. Four chains have four shapes —
CIP-25's `721` envelope, CIP-68's datum map, Metaplex's `properties.files`,
XRPL's loose XLS-24 keys — and any schema designed now is wrong for the
fifth chain. Readers pick what they need; `nft_metadata_traits()` in
`lib/nft-metadata-lib.php` is the shared way to do that.

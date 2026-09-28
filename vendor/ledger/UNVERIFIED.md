# This tree is NOT verified against npm

**Read this before enabling the direct-Ledger connect path.**

`vendor/xrpl/` can be checked byte-for-byte against npm's published SHA-512
integrity hashes (`sh vendor/verify.sh`). This tree cannot.

## Why

These 17 files came from **esm.sh**, which does not serve npm's files — it
**transpiles** them to `es2022` and **rewrites their imports**, and shims
node globals like `Buffer` that the browser does not have. The output is a
transformation, so it cannot match an npm tarball by construction.

So the chain of custody stops at esm.sh. Vendoring moved the risk from "every
page load, forever" to "one moment at fetch time" — and made the code fixed
and auditable — but it did not prove that moment was clean. A compromised CDN
then would be baked in permanently.

That matters more here than anywhere else in this repo, because this code gets
**WebHID access to a hardware wallet**. It cannot extract a key — that is what
the device is for — but it could ask the device to sign something, and the
only thing standing in the way is somebody reading the Ledger's screen
carefully while in the middle of "connecting my wallet".

## What would actually fix it

Both packages ship real ESM in `lib-es/` inside their npm tarballs, so a
verified vendoring is possible:

1. Fetch each `@ledgerhq/*` tarball, verify against the registry integrity hash
2. Extract `lib-es/`
3. Rewrite the bare imports (`@ledgerhq/hw-transport` etc.) to local paths, or
   ship an import map
4. Supply a `Buffer` polyfill — the genuinely awkward part, and one that must
   itself come from a verified source rather than be hand-written

That is a few hours and it closes the loop completely.

## Until then

Treat the direct-Ledger path as **unverified third-party code**. The two
extension paths (`vendor/xrpl/`) and the Xaman path (no client library at all)
do not have this problem.

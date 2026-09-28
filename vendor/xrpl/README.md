# Vendored XRPL wallet SDKs

Served from our own origin rather than a CDN. These run in a page that can
reach a user's wallet, and `import()` cannot carry Subresource Integrity — so
pinning a version protects against a bad release but not against a compromised
CDN. Copies here remove the CDN from the runtime trust path entirely.

| file | package | global it defines |
|---|---|---|
| `crossmark-sdk-0.4.0.umd.js` | `@crossmarkio/sdk@0.4.0` | `window.default` (plus `vanilla`, `modules`, `typings`) |
| `gemwallet-api-3.8.0.umd.js` | `@gemwallet/api@3.8.0` | `window.GemWalletApi` |

**UMD, not ESM, and deliberately.** Crossmark's ESM graph is 713 files and
2.8MB — it pulls the whole of `xrpl.js`, and 49 of those fetches fail. Its UMD
bundle is one self-contained 57KB file. GemWallet's is one 64KB file.

The Ledger libraries are a separate tree in `vendor/ledger/` because they have
no UMD build; see `vendor/ledger/refresh.py`.

## Refreshing

```
curl -sS "https://cdn.jsdelivr.net/npm/@crossmarkio/sdk@<v>/pack/umd/index.js" \
     -o vendor/xrpl/crossmark-sdk-<v>.umd.js
curl -sS "https://cdn.jsdelivr.net/npm/@gemwallet/api@<v>/umd/gemwallet-api.js" \
     -o vendor/xrpl/gemwallet-api-<v>.umd.js
```

Then update the filenames in `header.php`. Versioned filenames on purpose: a
cached old copy under a shared name is a bug nobody can see.

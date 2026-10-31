# sodium_compat (vendored)

Pure-PHP implementation of the parts of libsodium we need. Vendored, not
installed, because this repo has no Composer and the server has no `ext/sodium`.

- **Package:** [paragonie/sodium_compat](https://github.com/paragonie/sodium_compat)
- **Version:** v2.5.2 (released 2026-08-18)
- **License:** ISC (see `LICENSE`)

## Why it's here

`discord-interactions.php` has to verify the Ed25519 signature Discord puts on
every interaction request. Normally that's `sodium_crypto_sign_verify_detached()`
from `ext/sodium`, bundled with PHP since 7.2.

This server didn't have it, and the survey that established that was wrong about
why. Every PHP build on the box was checked by RUNNING it — all 18 CloudLinux
`alt-php` versions and both EasyApache ones — and none of them had the function.
That measures what is **enabled in each build's ini**, not what is available to
enable, and it was read as the second. The CloudLinux PHP Selector's Extensions
tab did have a `sodium` checkbox all along; it was ticked on **2026-10-31** and
the extension is now present (confirmed over HTTP on Admin → Front Door).

**This library stays, and is not dead code.** Selector extension choices are per
PHP version and reset on a version switch — the same trap that emptied
`date.timezone` and broke the rules gate for an afternoon. Every caller is
written `function_exists('sodium_…') ? native : ParagonIE_Sodium_Compat::…`, so
native wins while it is there and nothing breaks the day it is not. The two
implementations were checked against each other on the one call this platform
makes outside the Discord gate (`Bech32::hashNativeAsset`, BLAKE2b-160): byte
identical.

Verification is not optional: an interactions endpoint that skips it will accept
forged requests, and Discord won't even let you save the endpoint URL unless it
proves the check works.

## How it's wired in

`discord-interactions.php` prefers the real extension and only requires this
when it's absent:

```php
if (!function_exists('sodium_crypto_sign_verify_detached')) {
    require_once __DIR__ . '/lib/sodium_compat/autoload.php';
}
```

If the host ever gains `ext/sodium`, the native one is used automatically and
this becomes dead weight — nothing to change, and nothing breaks. `autoload.php`
also defines the `sodium_*` functions as global polyfills when the extension is
missing, so calling code doesn't branch.

Cost is about 25ms per verification against Discord's 3-second interaction
budget.

## What was omitted

Upstream's `namespaced/` directory (PHP namespace aliases) is not included —
`autoload.php` never loads it, and nothing in `src/` or `lib/` references it
outside a comment. `composer.json`, `README.md` and `CONTRIBUTING.md` are also
dropped. `src/`, `lib/`, `autoload.php` and `LICENSE` are byte-for-byte upstream.

## Updating

Replace `src/`, `lib/` and `autoload.php` from a fresh release tarball and update
the version above. Don't hand-edit anything in here — it's cryptographic code,
and local modifications would be invisible to anyone reading it later.

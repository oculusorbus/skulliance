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

This server doesn't have it. Not "it's off for the selected version" — every PHP
build on the box was checked, all 18 CloudLinux `alt-php` versions and both
EasyApache ones, and none of them ship the extension. So there was no PHP
selector checkbox to tick.

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

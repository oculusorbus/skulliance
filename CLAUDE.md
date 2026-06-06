# Skulliance — Project Instructions

PHP web app: an NFT-based game dashboard on Cardano. Skulls/NFTs are used for
staking, missions, raids, realms, games, and a marketplace. No ORM — all DB
access goes through `db.php`. Pages live in the repo root and are served under
`/staking/`; AJAX endpoints live in `ajax/`.

## Skull Paper — keep the docs in sync (IMPORTANT)

The **Skull Paper** is the platform's living documentation, served from
`skullpaper.php` with content in `skullpaper/*.md` (Markdown rendered by
`lib/Parsedown.php`). It replaced the old GitBook so docs live next to the code.

**Whenever you make a significant change to a feature, update its Skull Paper
page in the same change.** "Significant" = anything a player would notice:
new features, changed rewards/rates/costs/formulas, new currencies, renamed or
removed mechanics, new games or marketplace/platform tools.

- `skullpaper/MAINTENANCE.md` maps every feature → its doc page → the code that
  defines it, and records the verified constants. **Consult and update it.**
- Cross-link pages with `[[slug]]` (slug = the `.md` filename without extension);
  the renderer resolves these to internal links via the nav in `skullpaper.php`.
- When adding a page: create `skullpaper/<slug>.md`, add it to `$skullpaper_nav`
  in `skullpaper.php`, and add a row to `skullpaper/MAINTENANCE.md`.
- Keep numbers accurate — cite them from code. If you can't verify a value, write
  the mechanic qualitatively rather than guessing.

A `pre-commit` hook (`skullpaper/hooks/pre-commit`) prints a reminder when you
stage feature code without touching `skullpaper/`. It is a non-blocking nudge,
not a gate. Install it with: `cp skullpaper/hooks/pre-commit .git/hooks/ && chmod +x .git/hooks/pre-commit`

## Conventions

- Commit code changes after completing them.
- The Skull Paper is **public** — `skullpaper.php` must not include `skulliance.php`
  (login gate) or `verify.php`. See its header comment.

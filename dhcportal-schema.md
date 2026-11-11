# DHC Portal — schema

One table. Everything else the Portal does happens in `dhc_trait_drops`,
which already exists.

```sql
CREATE TABLE IF NOT EXISTS dhc_portal_board (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id  INT UNSIGNED NOT NULL,
  drop_id  INT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_drop (drop_id),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Why `drop_id` and not `category` + `slug`

A player can own three copies of the same trait. Staging two of them has to
be unambiguous, and the delete at jettison has to remove *exactly the rows
that were staged* rather than "two of these". Pointing at a specific
`dhc_trait_drops.id` makes every piece on the board a particular copy that
either still exists and is still free, or does not.

## Why `UNIQUE` on `drop_id`

It is the first half of the double-spend defence, and it is enforced by the
database rather than by code remembering to check. The same copy cannot be
staged twice — not by a second tab, not by a replayed request, not by two
players if a row ever changed hands mid-flight.

The second half is in `ajax/dhcportal-jettison.php`: the delete names the
owner (`WHERE user_id = ... AND id IN (...)`) and the affected-row count must
equal the board size, or the whole transaction rolls back. Two people firing
at once cannot take the same row, and a row that moved between the read and
the write simply is not deleted — which the count catches before anything is
handed out.

## Staged is not committed

A trait on the board stays available to the assembler. That is deliberate: a
draft that silently locks your traits is worse than one that goes stale. The
cost is that the board can go out of date, so `dhcp_board()` **self-heals** —
it checks staged copies against `dhcf_available()` and drops any that have
since gone onto a Fighter, reporting how many went.

The jettison endpoint then refuses outright if anything was dropped, rather
than firing a smaller board than the player assembled. They charged it to a
tier and the tier may have just fallen.

## No row for the hopper's contents after firing

The fed traits are **deleted** from `dhc_trait_drops` and re-created on their
recipients by `dhcf_commit_drop()` with `source = 'portalgift'`. Moving the
row with an `UPDATE` would have been one statement, but it bypasses the
announce — and the Discord ping in real time is the entire woo-back. A new
award event for the recipient is also the truthful record: it *is* a new
award, to them, today.

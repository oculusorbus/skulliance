# Testimonials — database schema

Run once against the live database. Nothing in the app creates tables, same as
`activity-schema.md` and `dhcarena-schema.md`.

Until the tables exist the feature is **inert, not broken**: every read returns
empty, the modal never offers, the admin tab says the tables are missing, and
the public pages render exactly as they do today.

```sql
CREATE TABLE testimonials (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  prompt_key     VARCHAR(32)  NOT NULL DEFAULT '',
  body           TEXT         NOT NULL,
  visibility     ENUM('named','anonymous','private') NOT NULL DEFAULT 'named',
  status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  is_current     TINYINT(1)   NOT NULL DEFAULT 1,
  created_date   DATETIME     NOT NULL,
  reviewed_date  DATETIME     NULL,
  reviewed_by    INT          NULL,
  review_note    VARCHAR(255) NOT NULL DEFAULT '',
  discord_posted TINYINT(1)   NOT NULL DEFAULT 0,
  KEY user_current (user_id, is_current),
  KEY review_queue (status, is_current, created_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE testimonial_nudges (
  user_id     INT NOT NULL PRIMARY KEY,
  shown_count INT        NOT NULL DEFAULT 0,
  last_shown  DATETIME   NULL,
  muted       TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Why every submission is kept, not one row per player

The obvious build is one row per user, updated in place. It cannot be that, for
a reason that only shows up after the first bad one:

**An edit has to reset approval, or approval means nothing.** Someone writes
something generous, it is approved and posted, and then they edit it to
whatever they like and it is live with the owner's name on it. So an edit is a
new row at `pending`, and the previous row keeps its own status and stays.

That also gives the archive the feature was asked for at no extra cost: every
version anybody ever wrote is still there, in order, with the review decision
that was made on it.

`is_current` flags the one row per user that counts. Exactly one per user, or
none.

## Why a second table for the nudges

Dismissals are not testimonials. A player who has been asked twice and said not
now has no row in `testimonials` at all, and giving them an empty one to hold a
counter would put blank entries in the archive and in the review queue.

`muted` is set when somebody uses the last reminder, or asks never to be asked.
Nothing clears it but the player.

## What is NOT stored

**No share state.** The X button is a link to an intent URL; whether somebody
posts it happens on X, and a column claiming to know would be wrong as often as
right.

**No Discord message id.** `discord_posted` is a flag, not a handle. A post
cannot be unposted through a webhook, so there is nothing a handle would let us
do, and storing one implies otherwise. The submit screen says so plainly.

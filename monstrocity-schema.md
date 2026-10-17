# Monstrocity theme schema

Themes used to live as a JS array literal inside `monstrocity.php`. This table
replaces it. **Nothing in the app creates this table** - run the SQL below by
hand, the same way `dhcarena-schema.md` works.

## Why these columns look the way they do

- **`project_id` is NULLABLE and mostly NULL.** Plenty of themes are for
  projects that are not on Skulliance at all, so there is nothing to point at.
  When a theme *does* map to one, linking it lets the theme inherit the
  project's points icon and canonical name. When it does not, `project_name`
  and `title` stand on their own. Do not make it required.
- **COLLECTIONS ARE MANY, SO THEY ARE A JOIN TABLE, NOT A COLUMN.** A theme can
  cover several collections - that is exactly why some themes carry a list of
  policy ids rather than one. `monstrocity_theme_collections` holds those links.
- **`policy_ids` is the LEGACY path and still works.** When a theme has no
  collections attached, the static string is used exactly as before. When it
  does, the policy ids are read from those collections instead, so there is one
  place to change a policy and it is the collection that owns it.
- **`req_innercircle` and `req_dev` are two booleans, not one tier column.**
  In the original the `$dev` block was nested *inside* the `$innercircle`
  block, so the one dev theme (`j2`) needs BOTH. A single enum would have
  shown `j2` to a dev who is not Inner Circle, which the old code never did.
- **`policy_ids` and `ipfs_prefixes` stay comma-separated strings**, because
  that is exactly what the client parses today. Splitting them into their own
  table is a later decision, not this migration's business.
- **`sort_order` is global, not per group.** The original array is grouped
  contiguously, so ordering every row by `sort_order` and starting a new group
  whenever `theme_group` changes reproduces the original output exactly.
- **`active`** lets a theme be retired without deleting its row and its history.

## Table

```sql
CREATE TABLE IF NOT EXISTS monstrocity_themes (
	id              INT AUTO_INCREMENT PRIMARY KEY,
	slug            VARCHAR(64)  NOT NULL,            -- folder under images/monstrocity/
	project_id      INT          DEFAULT NULL,        -- optional -> projects.id
	project_name    VARCHAR(128) NOT NULL,            -- shown when project_id is NULL
	title           VARCHAR(128) NOT NULL,
	policy_ids      TEXT         NOT NULL,            -- CSV, as the client expects
	orientations    VARCHAR(32)  NOT NULL DEFAULT '', -- '' | Left | Right | Random
	ipfs_prefixes   TEXT         NOT NULL,            -- DEAD, see below. Safe to drop.
	background      TINYINT(1)   NOT NULL DEFAULT 1,
	extension       VARCHAR(8)   NOT NULL DEFAULT 'png',
	theme_group     VARCHAR(64)  NOT NULL,
	group_order     INT          NOT NULL DEFAULT 0,
	req_innercircle TINYINT(1)   NOT NULL DEFAULT 0,
	req_dev         TINYINT(1)   NOT NULL DEFAULT 0,
	sort_order      INT          NOT NULL DEFAULT 0,
	active          TINYINT(1)   NOT NULL DEFAULT 1,
	created_at      DATETIME     NOT NULL,
	updated_at      DATETIME     DEFAULT NULL,
	UNIQUE KEY uniq_slug (slug),
	INDEX idx_render (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## `ipfs_prefixes` is dead and can be dropped

Nothing selects it, nothing writes it, and the generated theme array no
longer carries it.

Storing a gateway per theme was the wrong shape twice over. It pinned all 49
themes to `https://ipfs.io/ipfs/`, which **answers 429 to this server**, so
every theme NFT was fetched from a host that refuses us. And it made a
gateway going bad a data migration instead of a one-line change in
`lib/ipfs-gateways.php`.

The game resolves the way My NFTs does now: the locally cached file first,
then each measured gateway in turn, then the placeholder.

```sql
ALTER TABLE monstrocity_themes DROP COLUMN ipfs_prefixes;
```

Optional. An unused column costs nothing, and keeping it preserves what the
old themes named. Run it only if you want the table tidy.

## Collections attached to a theme

A theme covers zero or more collections. Zero means "use the legacy
`policy_ids` string"; one or more means "ignore it and read the policies from
these collections". Nothing is attached at migration time, so every theme keeps
the exact policy string it had.

> **Run the CREATE below before using the checkboxes.** Nothing in the codebase
> creates this table. Until it exists, mysqli returns `false` for every query
> against it without throwing, so the admin's `DELETE` and `INSERT` both fail,
> the page still reports *Saved.*, and every tick is discarded. Reopen the form
> and the boxes are empty. `monstrocity_theme_policies()` returns nothing, so
> each theme falls back to its `policy_ids` string -- and a theme whose string
> was cleared, in the belief the collections had taken over, then matches no
> NFTs at all. The admin now says so on the form and on the save, and
> `php monstrocity-theme-check.php` leads with it.

```sql
CREATE TABLE IF NOT EXISTS monstrocity_theme_collections (
	theme_id      INT NOT NULL,
	collection_id INT NOT NULL,
	sort_order    INT NOT NULL DEFAULT 0,
	PRIMARY KEY (theme_id, collection_id),
	INDEX idx_theme (theme_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### The override cuts both ways

Because ticking anything replaces the string **wholesale**, attaching
collections is the one edit in this form that can make a theme stop matching
NFTs it matched yesterday. Two ways:

- **A ticked collection with an empty `collections.policy`** contributes
  nothing. `monstrocity_theme_policies()` filters it out (`WHERE c.policy <> ''`)
  and `ajax/get-nft-assets.php` matches on `collections.policy IN (...)`, which
  can never match `''`. Tick only collections like that and the derived set is
  empty, the legacy string is still ignored, and the theme offers the default
  characters only.
- **A collection that was never ticked.** The theme's `project_id` narrows the
  checkbox list by default, so a collection filed under a *different* project is
  hidden until "Show every project's collections" is checked. If the hardcoded
  policy belonged to one of those, it is simply not in the derived set.

The admin page now warns on both after saving, and the checkbox labels a
policy-less collection as such instead of rendering a bare ellipsis. To see
what a theme is actually asking for, and what a given player owns under it:

```
php monstrocity-theme-check.php                             # every theme, one line each
php monstrocity-theme-check.php <slug>                      # attached collections, derived vs legacy
php monstrocity-theme-check.php <slug> <username>           # and what that player would be handed
```

`orientations` is also **positional** against the policy list. One value
applies to every policy (the client special-cases a single entry), but two or
more were written for the order and length of the legacy string, so a derived
list of a different shape silently reassigns which way each collection faces.
The check script reports that mismatch too.

### Which themes could be linked today

This lists every theme whose hardcoded policy ids already exist as collections,
so you can see what is linkable before touching anything. It only reports; it
changes nothing.

```sql
SELECT t.slug, t.title, c.id AS collection_id, c.name AS collection_name, c.policy
  FROM monstrocity_themes t
  JOIN collections c
    ON FIND_IN_SET(c.policy, t.policy_ids)
 ORDER BY t.sort_order, c.name;
```

## Seed: the 49 themes exactly as they were in the code

```sql
INSERT INTO monstrocity_themes
 (slug, project_id, project_name, title, policy_ids, orientations,
  ipfs_prefixes, background, extension, theme_group, group_order,
  req_innercircle, req_dev, sort_order, active, created_at)
VALUES
('monstrocity', NULL, 'Monstrocity', 'Default Game', '', '', '', 1, 'png', 'Partner Project Themes', 0, 0, 0, 0, 1, NOW()),
('apprentices', NULL, 'Apprentices', 'Apprentices', '93ff51e7dfdf32314fd2f99ff222aa9c92f486b7d2cc0d46b64a9785', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 1, 1, NOW()),
('blackflag', NULL, 'Black Flag', 'Black Flag', 'b3dab69f7e6100849434fb1781e34bd12a916557f6231b8d2629b6f6', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 2, 1, NOW()),
('danketsu2', NULL, 'Danketsu', 'The Fourth', 'a4b7f3bbb16b028739efc983967f1e631883f63a2671d508023b5dfb', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 3, 1, NOW()),
('moebiuspioneers', NULL, 'Disco Solaris', 'Legends', '9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 4, 1, NOW()),
('discosolaris', NULL, 'Disco Solaris', 'Moebius Pioneers', '9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 5, 1, NOW()),
('oculuslounge', NULL, 'Disco Solaris', 'Oculus Lounge', 'd0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 6, 1, NOW()),
('havocworlds', NULL, 'Havoc Worlds', 'Season 1', '1088b361c41f49906645cedeeb7a9ef0e0b793b1a2d24f623ea74876', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 7, 1, NOW()),
('proxy', NULL, 'Heist on Alpha', 'Proxy', 'f85be1a54efa2607de78a0f8855f9d73c9dd79bfa116f3767c015098', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 8, 1, NOW()),
('muses', NULL, 'Josh Howard', 'Muses of the Multiverse', '7f95b5948e3efed1171523757b472f24aecfab8303612cfa1b6fec55', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 9, 1, NOW()),
('fauna', NULL, 'Omen', 'Fauna x Omen', '7cd357f96d7a7325ff3038e78e004840706887790d8b513913b45c27', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 10, 1, NOW()),
('omen', NULL, 'Omen', 'Omen Legends', 'da286f15e0de865e3d50fec6fa0484d7e2309671dc4ba8ce6bdd122b', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 11, 1, NOW()),
('sh4pes', NULL, 'Omen', 'Sh4pes x Omen', '2d868badf3dc317234fe253859621fedf661bf9eba275faea80a8bfe', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 12, 1, NOW()),
('pendulum', NULL, 'Pendulum', 'Pendulum', 'a616aab3b18eb855b4292246bd58f9e131d7c8c25d1d1d7c88b666c4', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 13, 1, NOW()),
('perps', NULL, 'Perps', 'Perps', 'e6ba9c0ff27be029442c32533c6efd956a60d15ecb976acbb64c4de0', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 14, 1, NOW()),
('vampireinvasion', NULL, 'Vampire Invasion', 'Vampire Invasion', '590048dda6a624c887c0466b178c16fff3dedc2aac5651a9e0fc41b6', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Project Themes', 0, 0, 0, 15, 1, NOW()),
('bungking', NULL, 'Bungking', 'Yume', 'f5a4009f12b9ee53b15edf338d1b7001641630be8308409b1477753b', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 16, 1, NOW()),
('cardanocamera', NULL, 'Cardano Camera', 'Galaxy of Sons', '647535c1befd741bfa1ace4a5508e93fe03ff7590c26d372c8a812cb', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 17, 1, NOW()),
('crypties2', NULL, 'Crypties', 'Crypties S2', 'e77fe5101469bdbd2d596f69abbd8ea6311008f5687dec3d950bb17a', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 18, 1, NOW()),
('darkula', NULL, 'Darkula', 'Island of the Uncanny Neighbors', 'b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 19, 1, NOW()),
('darkula2', NULL, 'Darkula', 'Island of the Violent Neighbors', 'b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 20, 1, NOW()),
('deadpophell', NULL, 'Dead Pop Hell', 'NSFW', '6710d32c862a616ba81ef00294e60fe56969949e0225452c48b5f0ed', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 21, 1, NOW()),
('galactico', NULL, 'Galactico', 'Galactico', 'bedb9383c1ad91247ec2a81fc3841934bbb298530eb99a55e77a0fb8,89c01dc68d57169d9c02c3f35562d219884bbafa9d9f868c2f39ce67', 'Random', 'https://ipfs.io/ipfs/', 1, 'gif', 'Partner Artist Themes', 1, 0, 0, 22, 1, NOW()),
('animeorigins', NULL, 'Nel', 'Anime Origins', 'c7ad13b8f11d1d1d2abe519b4394b4ca62c1dd2ce737e382ab573947', 'left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 23, 1, NOW()),
('happypeople', NULL, 'Netanel Cohen', 'Happy People', 'ac9067e22a857ee2e4ea20f77c6c047f78a11511f285d30d81857196', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 24, 1, NOW()),
('machineheadz', NULL, 'Kimosabe Art', 'Machine Headz Carnage', '8f63e08467bfbbc260a382a80f3297f9a569fca0293f296620d72d17,f4ae899bda2d478baec88620a06108619b231dfc6197be2a3b74a0ea,243f0ca18ded6b45ba87655ce3917abc8e04830b23ba1f498de67a8a,dcd1fa7b85bf5854fbe9f94238e9079fd502bf10ab2a3c26cf3d5465', 'Left', 'https://ipfs.io/ipfs/', 1, 'gif', 'Partner Artist Themes', 1, 0, 0, 25, 1, NOW()),
('maxi', NULL, 'Maxingo', 'Digital Hell Citizens 2: Fighters', 'b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 26, 1, NOW()),
('shortyverse', NULL, 'Ohh Meed', 'Shorty Verse', '0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 27, 1, NOW()),
('shortyverse2', NULL, 'Ohh Meed', 'Shorty Verse Engaged', '0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 28, 1, NOW()),
('dropship', NULL, 'Ohh Meed', 'Drop Ship', '4478c708183e95340d0582419a2d6bc93d57657895c19802546d396c', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 29, 1, NOW()),
('beelzebub', NULL, 'Ritual', 'Beelzebub', 'cd3a338f248a8ccb484032327b499e90b0303c499beea47e37039bf2', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 30, 1, NOW()),
('bogeyman', NULL, 'Ritual', 'Bogeyman', 'bca7c472792b859fb18920477f917c94b76c9c9705e039bf08af0b63', 'Random', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 31, 1, NOW()),
('ritual', NULL, 'Ritual', 'John Doe', '16b10d60f428b03fa5bafa631c848b2243f31cbf93cce1a65779e5f5', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 32, 1, NOW()),
('skada', NULL, 'Ritual', 'Skada', '2eacad9ddcb9edd7721af49f682bd356e8e28194bafa4bbc2f559bb7', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 33, 1, NOW()),
('sinderskullz', NULL, 'Sinder Skullz', 'Sinder Skullz', '83732ff37818e7e520592fcd3e5257e429307d40a9f5437240e926de', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 34, 1, NOW()),
('skowl', NULL, 'Skowl', 'Derivative Heroes', 'd38910b4b5bd3e634138dc027b507b52406acf687889e3719aa4f7cf', 'Left', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 35, 1, NOW()),
('ug', NULL, 'Squashua', 'Ug Vs Donuts', '8972aab912aed2cf44b65916e206324c6bdcb6fbd3dc4eb634fdbd28', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 36, 1, NOW()),
('wave', NULL, 'Squashua', 'Wavy Ape Vibe Empire', 'd458857ae7d121bfb5af64d78c0454a519133248e030a0225734452b', 'Right', 'https://ipfs.io/ipfs/', 1, 'png', 'Partner Artist Themes', 1, 0, 0, 37, 1, NOW()),
('adapunks', NULL, 'ADA Punks', 'ADA Punks', '', '', '', 1, 'png', 'Bonus Themes', 2, 0, 0, 38, 1, NOW()),
('cardanians', NULL, 'Cardanians', 'Cardanian Snow Globes (GIF)', '', '', '', 1, 'gif', 'Bonus Themes', 2, 0, 0, 39, 1, NOW()),
('cardanians2', NULL, 'Cardanians', 'Cardanian Snow Globes (MOV)', '', '', '', 1, 'mov', 'Bonus Themes', 2, 0, 0, 40, 1, NOW()),
('handies', NULL, 'Handies', 'Handies', '', '', '', 1, 'png', 'Bonus Themes', 2, 0, 0, 41, 1, NOW()),
('occultarchives', NULL, 'Billy Martin', 'Occult Archives', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 0, 42, 1, NOW()),
('rubberrebels', NULL, 'Classic Cardtoons', 'Rubber Rebels', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 0, 43, 1, NOW()),
('danketsu', NULL, 'Danketsu', 'Legends', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 0, 44, 1, NOW()),
('j2', NULL, 'J2', 'J2', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 1, 45, 1, NOW()),
('jetchicken', NULL, 'Jet Chicken', 'Jet Chicken', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 0, 46, 1, NOW()),
('karranka', NULL, 'Karranka', 'Badass Heroes', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 0, 47, 1, NOW()),
('karranka2', NULL, 'Karranka', 'Japanese Ghosts: Legendary Warriors', '', '', '', 1, 'png', 'Inner Circle Top Secret Themes', 3, 1, 0, 48, 1, NOW());
```

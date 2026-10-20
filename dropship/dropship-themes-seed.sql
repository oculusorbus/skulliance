-- ===========================================================================
-- DESTRUCTIVE. This DROPS both theme tables and rebuilds them from scratch.
-- Running it discards every theme and every word anybody has saved in the
-- admin. That is the point -- it is the one file to run for a clean slate,
-- so the schema and the data can never be half a version apart -- but it is
-- not a file to run casually on a theme somebody has been editing.
-- ===========================================================================
--
-- GENERATED from the if/else chain in dropship/db.php by running it,
-- not by reading it. Regenerate rather than hand-edit.
--
-- NO ICON ROWS. An icon filename is the theme's own word made into a
-- filename and dropshipThemeText() derives it, so storing it would be the
-- same fact in two places -- which is exactly how 13 of the 64 live icon
-- names drifted away from the word beside them.
--
-- NO LABEL COLUMNS. currency, squad_label and character_label held the words
-- SCRIP, Super Soldier and Troops, which the wording owns; they are rows
-- here now.
--
-- trait_source STAYS. It was briefly removed on the reasoning that it was
-- derivable from the slug -- which was true of a DIFFERENT thing of the same
-- name, the key naming which game a Discord trait drop came from. This one
-- picks between two real NFT-loading pipelines and nothing derives it.
--
-- REQUIRES dropship_theme_text.find TO BE utf8mb4_bin. The seed carries
-- case-only pairs on purpose -- 'Medkit' is the word a player reads and
-- 'medkit' is the icon filename -- and MySQL's default collation treats
-- them as one value, so a case-insensitive column rejects the second with
-- "#1062 Duplicate entry ... for key 'uniq_pair'". See the schema doc.
-- Project 1 (Drop Ship) is the base and has no overrides, so it has no rows.
-- The 'gif' pair is deliberately absent: a theme's animation extension is one
-- value on dropship_themes.extension, and duplicating it into the dictionary
-- would leave two places to change it. (It used to be a per-player 3D toggle,
-- now retired.)

DROP TABLE IF EXISTS dropship_theme_text;
DROP TABLE IF EXISTS dropship_themes;

CREATE TABLE dropship_themes (
	id               INT AUTO_INCREMENT PRIMARY KEY,
	project_id       INT          NOT NULL,            -- projects.id
	slug             VARCHAR(64)  NOT NULL,            -- image folder under dropship/
	active           TINYINT(1)   NOT NULL DEFAULT 0,  -- shows in the project picker
	-- MEDIA extension: 'gif' (animates itself) or a still like 'png',
	-- which gets the Ken Burns pan instead.
	extension        VARCHAR(8)   NOT NULL DEFAULT 'gif',
	-- Where this theme's NFT holdings come from: 'koios' reads the
	-- player's connected wallets live, 'skulliance' reads the already
	-- synced nfts table. A real choice; nothing derives it.
	trait_source     VARCHAR(16)  NOT NULL DEFAULT 'koios',
	-- Stage art in order ('linear') or shuffled ('random', the original
	-- behaviour). Shuffling suits frames that are interchangeable takes
	-- on one scene; it reads as the story jumping about when they are a
	-- sequence.
	stage_order      VARCHAR(8)   NOT NULL DEFAULT 'random',
	created_at       DATETIME     NOT NULL,
	updated_at       DATETIME     DEFAULT NULL,
	UNIQUE KEY uniq_project (project_id),
	UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dropship_theme_text (
	id          INT AUTO_INCREMENT PRIMARY KEY,
	theme_id    INT          NOT NULL,
	match_type  ENUM('exact','description') NOT NULL DEFAULT 'exact',
	-- utf8mb4_bin: `find` is CASE SENSITIVE. The default collation makes
	-- 'Medkit' and 'medkit' one row and they are not the same thing.
	find        VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
	replacement VARCHAR(255) NOT NULL,
	sort_order  INT          NOT NULL DEFAULT 0,
	UNIQUE KEY uniq_pair (theme_id, match_type, find),
	INDEX idx_theme (theme_id, match_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- project 1: drop-ship, the base game
-- No text rows: this IS the wording every other theme overrides. The row
-- exists so the base game can be reskinned like the rest, and so the art
-- admin can manage its files -- dropshipArtRoot() maps the 'drop-ship' slug
-- to the FLAT folder, which is where Drop Ship's own art actually lives.
INSERT INTO dropship_themes
  (project_id, slug, active, extension, trait_source, stage_order, created_at)
VALUES
  (1, 'drop-ship', 1, 'gif', 'koios', 'random', NOW());

-- ---------- project 2: dread-city (31 exact, 3 description)
INSERT INTO dropship_themes
  (project_id, slug, active, extension, trait_source, stage_order, created_at)
VALUES
  (2, 'dread-city', 0, 'gif', 'koios', 'random', NOW());
SET @t := (SELECT id FROM dropship_themes WHERE project_id = 2);
INSERT INTO dropship_theme_text (theme_id, match_type, find, replacement, sort_order) VALUES
  (@t, 'exact', 'SCRIP', 'DREAD', 1),
  (@t, 'exact', 'Barracks', 'Hideout', 2),
  (@t, 'exact', 'Base', 'Leather Jacket', 3),
  (@t, 'exact', 'Light', 'Bulletproof Vest', 4),
  (@t, 'exact', 'Medium', 'Steel Armor', 5),
  (@t, 'exact', 'Heavy', 'Titanium Armor', 6),
  (@t, 'exact', 'Base Armor', 'Leather Jacket', 7),
  (@t, 'exact', 'Light Armor', 'Bulletproof Vest', 8),
  (@t, 'exact', 'Medium Armor', 'Steel Armor', 9),
  (@t, 'exact', 'Heavy Armor', 'Titanium Armor', 10),
  (@t, 'exact', 'Tactical Katana', 'Machete', 11),
  (@t, 'exact', 'Quantum Stealth', 'Mask & Hoodie', 12),
  (@t, 'exact', 'Exo Suit', 'SUV', 13),
  (@t, 'exact', 'Mech Suit', 'Hummer', 14),
  (@t, 'exact', 'Night Vision Goggles', 'Flashlight', 15),
  (@t, 'exact', 'Radar', 'GPS Tracker', 16),
  (@t, 'exact', 'Radio', 'Walkie Talkie', 17),
  (@t, 'exact', 'Jet Pack', 'Helicopter', 18),
  (@t, 'exact', 'Drop Box', 'Loot Crate', 19),
  (@t, 'description', 'beach', 'downtown', 20),
  (@t, 'description', 'hill', 'complex', 21),
  (@t, 'description', 'bunker', 'hideout', 22),
  (@t, 'exact', 'Super Soldier', 'Elite', 23),
  (@t, 'exact', 'Troops', 'Henchmen', 24);

-- ---------- project 3: filthy-mermaid (78 exact, 29 description)
INSERT INTO dropship_themes
  (project_id, slug, active, extension, trait_source, stage_order, created_at)
VALUES
  (3, 'filthy-mermaid', 0, 'gif', 'koios', 'random', NOW());
SET @t := (SELECT id FROM dropship_themes WHERE project_id = 3);
INSERT INTO dropship_theme_text (theme_id, match_type, find, replacement, sort_order) VALUES
  (@t, 'exact', 'Drop Ship', 'Filthy Mermaid', 1),
  (@t, 'exact', 'Drop Ship Initiating', 'Filthy Mermaid Entrance', 2),
  (@t, 'exact', 'Drop Ship Landed', 'Filthy Mermaid Doorway', 3),
  (@t, 'exact', 'SCRIP', 'TIDDIES', 4),
  (@t, 'exact', 'Super Soldier', 'VIP', 5),
  (@t, 'exact', 'Grunt', 'Sex Slave', 6),
  (@t, 'exact', 'Barracks', 'Hideout', 7),
  (@t, 'exact', 'Armory', 'Sex Shop', 8),
  (@t, 'exact', 'Pull Grenade Pin', 'Insert Anal Beads', 9),
  (@t, 'exact', 'Pull Smoke Bomb Pin', 'Pull Out Whip', 10),
  (@t, 'exact', 'Explosion', 'Spank', 11),
  (@t, 'exact', 'Base', 'Boxer Briefs', 12),
  (@t, 'exact', 'Light', 'Basketball Shorts', 13),
  (@t, 'exact', 'Medium', 'Sweat Pants', 14),
  (@t, 'exact', 'Heavy', 'Smoking Jacket', 15),
  (@t, 'exact', 'Base Armor', 'Boxer Briefs', 16),
  (@t, 'exact', 'Light Armor', 'Basketball Shorts', 17),
  (@t, 'exact', 'Medium Armor', 'Sweat Pants', 18),
  (@t, 'exact', 'Heavy Armor', 'Smoking Jacket', 19),
  (@t, 'exact', 'Ballistic Shield', 'Fur Coat', 20),
  (@t, 'exact', 'Quantum Stealth', 'Pimp Suit', 21),
  (@t, 'exact', 'Exo Suit', 'Trench Coat', 22),
  (@t, 'exact', 'Mech Suit', 'Birthday Suit', 23),
  (@t, 'exact', 'Tactical Katana', 'Dildo', 24),
  (@t, 'exact', 'Sniper Rifle', 'Butt Plug', 25),
  (@t, 'exact', 'Melee', 'Vibrator', 26),
  (@t, 'exact', 'Grenade', 'Anal Beads', 27),
  (@t, 'exact', 'Demolition', 'Paddle', 28),
  (@t, 'exact', 'Smoke Bomb', 'Whip', 29),
  (@t, 'exact', 'Machine Gun', 'Money Gun', 30),
  (@t, 'exact', 'Flamethrower', 'Candle', 31),
  (@t, 'exact', 'Rocket Launcher', 'Double Dildo', 32),
  (@t, 'exact', 'Throw Grenade', 'Remove Anal Beads', 33),
  (@t, 'exact', 'Load Machine Gun', 'Load Money Gun', 34),
  (@t, 'exact', 'Flamethrower Ignition', 'Approach Mistress', 35),
  (@t, 'exact', 'Flamethrower Spray', 'Address Mistress', 36),
  (@t, 'exact', 'Flamethrower Flame', 'Drip Candle Wax', 37),
  (@t, 'exact', 'Flamethrower Fire', 'Pour Candle Wax', 38),
  (@t, 'exact', 'Load Rocket Launcher', 'Double Dildo', 39),
  (@t, 'exact', 'Rocket Launcher Gunfire', 'Double Dildo', 40),
  (@t, 'exact', 'Rocket Launcher Explosion', 'Double Dildo Finale', 41),
  (@t, 'exact', 'Night Vision Goggles', 'Fuzzy Handcuffs', 42),
  (@t, 'exact', 'Radar', 'Ball Gag', 43),
  (@t, 'exact', 'Radio', 'ATM', 44),
  (@t, 'exact', 'Jet Pack', 'Drugs', 45),
  (@t, 'exact', 'Drop Box', 'Grab Bag', 46),
  (@t, 'exact', 'Medkit', 'Sexy Nurse', 47),
  (@t, 'exact', 'Reinforcements', 'Make It Rain', 48),
  (@t, 'exact', 'Pilot', 'Money', 49),
  (@t, 'exact', 'Airstrike', 'Money', 50),
  (@t, 'exact', 'Jet Pack Takeoff', 'Drugs', 51),
  (@t, 'exact', 'Jet Pack Fly', 'Trippin', 52),
  (@t, 'description', 'Stormed the Beach', 'Entered the Lobby', 53),
  (@t, 'description', 'Climbed the Hill', 'Approached the Stage', 54),
  (@t, 'description', 'Entered the Bunker', 'Accessed VIP', 55),
  (@t, 'description', 'beach', 'lobby', 56),
  (@t, 'description', 'hill', 'stage', 57),
  (@t, 'description', 'bunker', 'vip', 58),
  (@t, 'description', 'Base Armor', 'Boxer Briefs', 59),
  (@t, 'description', 'Light Armor', 'Basketball Shorts', 60),
  (@t, 'description', 'Medium Armor', 'Sweat Pants', 61),
  (@t, 'description', 'Heavy Armor', 'Smoking Jacket', 62),
  (@t, 'description', 'Ballistic Shield', 'Fur Coat', 63),
  (@t, 'description', 'Quantum Stealth', 'Pimp Suit', 64),
  (@t, 'description', 'Exo Suit', 'Trench Coat', 65),
  (@t, 'description', 'Mech Suit', 'Birthday Suit', 66),
  (@t, 'description', 'Tactical Katana', 'Dildo', 67),
  (@t, 'description', 'Sniper Rifle', 'Butt Plug', 68),
  (@t, 'description', 'Melee', 'Vibrator', 69),
  (@t, 'description', 'Grenade', 'Anal Beads', 70),
  (@t, 'description', 'Demolition', 'Paddle', 71),
  (@t, 'description', 'Smoke Bomb', 'Whip', 72),
  (@t, 'description', 'Machine Gun', 'Money Gun', 73),
  (@t, 'description', 'Flamethrower', 'Candle', 74),
  (@t, 'description', 'Rocket Launcher', 'Double Dildo', 75),
  (@t, 'description', 'Night Vision Goggles', 'Fuzzy Handcuffs', 76),
  (@t, 'description', 'Radar', 'Ball Gag', 77),
  (@t, 'description', 'Radio', 'ATM', 78),
  (@t, 'description', 'Jet Pack', 'Drugs', 79),
  (@t, 'description', 'Drop Box', 'Grab Bag', 80),
  (@t, 'description', 'Medkit', 'Sexy Nurse', 81),
  (@t, 'exact', 'Troops', 'Perverts', 82);

-- ---------- project 4: oculus-lounge (82 exact, 29 description)
INSERT INTO dropship_themes
  (project_id, slug, active, extension, trait_source, stage_order, created_at)
VALUES
  (4, 'oculus-lounge', 1, 'png', 'skulliance', 'random', NOW());
SET @t := (SELECT id FROM dropship_themes WHERE project_id = 4);
INSERT INTO dropship_theme_text (theme_id, match_type, find, replacement, sort_order) VALUES
  (@t, 'exact', 'Drop Ship', 'Oculus Lounge', 1),
  (@t, 'exact', 'Drop Ship Initiating', 'Oculus Lounge Entrance', 2),
  (@t, 'exact', 'Drop Ship Landed', 'Oculus Lounge Hallway', 3),
  (@t, 'exact', 'Soldiers', 'Citizens', 4),
  (@t, 'exact', 'Registered Soldiers', 'Oculus Lounge VIPs', 5),
  (@t, 'exact', 'Beach Secured', 'Approach Stage', 6),
  (@t, 'exact', 'Hill Secured', 'Enter VIP', 7),
  (@t, 'exact', 'SCRIP', 'TIDDIES', 8),
  (@t, 'exact', 'Super Soldier', 'VIP', 9),
  (@t, 'exact', 'Grunt', 'Sex Slave', 10),
  (@t, 'exact', 'Barracks', 'Moebius-9 Dome', 11),
  (@t, 'exact', 'Armory', 'Sex Shop', 12),
  (@t, 'exact', 'Pull Grenade Pin', 'Insert Anal Beads', 13),
  (@t, 'exact', 'Pull Smoke Bomb Pin', 'Pull Out Whip', 14),
  (@t, 'exact', 'Explosion', 'Spank', 15),
  (@t, 'exact', 'Base', 'Boxer Briefs', 16),
  (@t, 'exact', 'Light', 'Basketball Shorts', 17),
  (@t, 'exact', 'Medium', 'Sweat Pants', 18),
  (@t, 'exact', 'Heavy', 'Smoking Jacket', 19),
  (@t, 'exact', 'Base Armor', 'Boxer Briefs', 20),
  (@t, 'exact', 'Light Armor', 'Basketball Shorts', 21),
  (@t, 'exact', 'Medium Armor', 'Sweat Pants', 22),
  (@t, 'exact', 'Heavy Armor', 'Smoking Jacket', 23),
  (@t, 'exact', 'Ballistic Shield', 'Fur Coat', 24),
  (@t, 'exact', 'Quantum Stealth', 'Pimp Suit', 25),
  (@t, 'exact', 'Exo Suit', 'Trench Coat', 26),
  (@t, 'exact', 'Mech Suit', 'Birthday Suit', 27),
  (@t, 'exact', 'Tactical Katana', 'Dildo', 28),
  (@t, 'exact', 'Sniper Rifle', 'Butt Plug', 29),
  (@t, 'exact', 'Melee', 'Vibrator', 30),
  (@t, 'exact', 'Grenade', 'Anal Beads', 31),
  (@t, 'exact', 'Demolition', 'Paddle', 32),
  (@t, 'exact', 'Smoke Bomb', 'Whip', 33),
  (@t, 'exact', 'Machine Gun', 'Money Gun', 34),
  (@t, 'exact', 'Flamethrower', 'Candle', 35),
  (@t, 'exact', 'Rocket Launcher', 'Double Dildo', 36),
  (@t, 'exact', 'Throw Grenade', 'Remove Anal Beads', 37),
  (@t, 'exact', 'Load Machine Gun', 'Load Money Gun', 38),
  (@t, 'exact', 'Flamethrower Ignition', 'Approach Mistress', 39),
  (@t, 'exact', 'Flamethrower Spray', 'Address Mistress', 40),
  (@t, 'exact', 'Flamethrower Flame', 'Drip Candle Wax', 41),
  (@t, 'exact', 'Flamethrower Fire', 'Pour Candle Wax', 42),
  (@t, 'exact', 'Load Rocket Launcher', 'Double Dildo', 43),
  (@t, 'exact', 'Rocket Launcher Gunfire', 'Double Dildo', 44),
  (@t, 'exact', 'Rocket Launcher Explosion', 'Double Dildo Finale', 45),
  (@t, 'exact', 'Night Vision Goggles', 'Fuzzy Handcuffs', 46),
  (@t, 'exact', 'Radar', 'Ball Gag', 47),
  (@t, 'exact', 'Radio', 'ATM', 48),
  (@t, 'exact', 'Jet Pack', 'Drugs', 49),
  (@t, 'exact', 'Drop Box', 'Grab Bag', 50),
  (@t, 'exact', 'Medkit', 'Sexy Nurse', 51),
  (@t, 'exact', 'Reinforcements', 'Make It Rain', 52),
  (@t, 'exact', 'Pilot', 'Money', 53),
  (@t, 'exact', 'Airstrike', 'Money', 54),
  (@t, 'exact', 'Jet Pack Takeoff', 'Drugs', 55),
  (@t, 'exact', 'Jet Pack Fly', 'Trippin', 56),
  (@t, 'description', 'Stormed the Beach', 'Entered the Lobby', 57),
  (@t, 'description', 'Climbed the Hill', 'Approached the Stage', 58),
  (@t, 'description', 'Entered the Bunker', 'Accessed VIP', 59),
  (@t, 'description', 'beach', 'lobby', 60),
  (@t, 'description', 'hill', 'stage', 61),
  (@t, 'description', 'bunker', 'vip', 62),
  (@t, 'description', 'Base Armor', 'Boxer Briefs', 63),
  (@t, 'description', 'Light Armor', 'Basketball Shorts', 64),
  (@t, 'description', 'Medium Armor', 'Sweat Pants', 65),
  (@t, 'description', 'Heavy Armor', 'Smoking Jacket', 66),
  (@t, 'description', 'Ballistic Shield', 'Fur Coat', 67),
  (@t, 'description', 'Quantum Stealth', 'Pimp Suit', 68),
  (@t, 'description', 'Exo Suit', 'Trench Coat', 69),
  (@t, 'description', 'Mech Suit', 'Birthday Suit', 70),
  (@t, 'description', 'Tactical Katana', 'Dildo', 71),
  (@t, 'description', 'Sniper Rifle', 'Butt Plug', 72),
  (@t, 'description', 'Melee', 'Vibrator', 73),
  (@t, 'description', 'Grenade', 'Anal Beads', 74),
  (@t, 'description', 'Demolition', 'Paddle', 75),
  (@t, 'description', 'Smoke Bomb', 'Whip', 76),
  (@t, 'description', 'Machine Gun', 'Money Gun', 77),
  (@t, 'description', 'Flamethrower', 'Candle', 78),
  (@t, 'description', 'Rocket Launcher', 'Double Dildo', 79),
  (@t, 'description', 'Night Vision Goggles', 'Fuzzy Handcuffs', 80),
  (@t, 'description', 'Radar', 'Ball Gag', 81),
  (@t, 'description', 'Radio', 'ATM', 82),
  (@t, 'description', 'Jet Pack', 'Drugs', 83),
  (@t, 'description', 'Drop Box', 'Grab Bag', 84),
  (@t, 'description', 'Medkit', 'Sexy Nurse', 85),
  (@t, 'exact', 'Troops', 'Neo Miami Citizens', 86);


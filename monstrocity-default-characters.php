<?php
/**
 * Single source of truth for the default Monstrocity character roster.
 *
 * Shown to logged-out visitors (and logged-in players with no Monstrocity
 * NFTs), and used as the client-side offline fallback. Consumed by:
 *   - ajax/get-monstrocity-assets.php  -> returned as JSON to the game
 *   - monstrocity.php                  -> echoed into JS (window.MONSTROCITY_
 *                                         DEFAULT_CHARACTERS) for the live load
 *                                         and the fetch-failure fallback
 *
 * Edit stats HERE ONLY - both consumers read this file, so they can never
 * drift. The roster is stat-balanced so no single pick dominates; the in-game
 * 'theme' field is added by the consumers (it's always 'monstrocity' here).
 *
 * Keep the qualitative description in the Skull Paper (games-monstrocity.md)
 * roughly in step, though it lists no per-character numbers.
 */
return array(
	array('name' => 'Craig',             'strength' => 6, 'speed' => 5, 'tactics' => 5, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Merdock',           'strength' => 6, 'speed' => 4, 'tactics' => 5, 'size' => 'Large',  'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Goblin Ganger',     'strength' => 4, 'speed' => 6, 'tactics' => 5, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Texby',             'strength' => 4, 'speed' => 5, 'tactics' => 6, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Mandiblus',         'strength' => 6, 'speed' => 4, 'tactics' => 4, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Koipon',            'strength' => 4, 'speed' => 4, 'tactics' => 6, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Slime Mind',        'strength' => 4, 'speed' => 5, 'tactics' => 5, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Billandar and Ted', 'strength' => 5, 'speed' => 5, 'tactics' => 4, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Dankle',            'strength' => 6, 'speed' => 4, 'tactics' => 3, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Boost Attack'),
	array('name' => 'Jarhead',           'strength' => 5, 'speed' => 5, 'tactics' => 3, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Boost Attack'),
	array('name' => 'Spydrax',           'strength' => 4, 'speed' => 6, 'tactics' => 3, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Heal'),
	array('name' => 'Katastrophy',       'strength' => 5, 'speed' => 3, 'tactics' => 5, 'size' => 'Large',  'type' => 'Base', 'powerup' => 'Heal'),
	array('name' => 'Ouchie',            'strength' => 4, 'speed' => 5, 'tactics' => 4, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Heal'),
	array('name' => 'Drake',             'strength' => 6, 'speed' => 4, 'tactics' => 3, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Heal'),
);

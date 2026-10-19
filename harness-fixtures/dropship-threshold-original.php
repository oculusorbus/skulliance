<?php
/* GOLDEN REFERENCE -- the armour/suit threshold chain EXACTLY as it stood in
 * dropship/dropship.php before dropshipThreshold() was extracted from it.
 *
 * DO NOT TIDY, REFORMAT OR "FIX" THIS FILE. Its only job is to disagree with
 * the extraction if the extraction is wrong, and it can only do that while it
 * is a verbatim copy of what actually shipped. It is dead code everywhere
 * except dropship-odds-harness.php. */
function dropship_threshold_original($mech_suit, $exo_suit, $quantum_stealth, $ballistic_shield,
                                     $heavy, $medium, $light, $base, $session) {
	$threshold = 79;
	$_SESSION = $session;
			$heavy_threshold = 74;
			$medium_threshold = 72;
			$light_threshold = 65.66;
			$base_thresold = 50;
			$medium_credit = 3;
			$light_credit = 2;
			$base_credit = 1;
			
			if($mech_suit == 'true') {
				$threshold = 88.9;
			} else if($exo_suit == 'true') {
				$threshold = 87.5;
			} else if($quantum_stealth == 'true') {
				$threshold = 85.7;
			} else if($ballistic_shield == 'true') {
				$threshold = 83.3;
			}else if($heavy == 'true' || $_SESSION['userData']['heavy_armor'] == "true") {
				if($medium == 'true' || $_SESSION['userData']['medium_armor'] == "true") {
					if($light == "true" || $_SESSION['userData']['light_armor'] == "true"){
						if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
							$threshold = $heavy_threshold+$medium_credit+$light_credit+$base_credit;
						}else{
							$threshold = $heavy_threshold+$medium_credit+$light_credit;
						}
					}else if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
						$threshold = $heavy_threshold+$medium_credit+$base_credit;
					}else{
						$threshold = $heavy_threshold+$medium_credit;
					}
				}else if($light == "true" || $_SESSION['userData']['light_armor'] == "true"){
					if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
						$threshold = $heavy_threshold+$light_credit+$base_credit;
					}else{
						$threshold = $heavy_threshold+$light_credit;
					}
				}else if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
					$threshold = $heavy_threshold+$base_credit;
				}else{
					$threshold = $heavy_threshold;
				}
			} else if($medium == 'true' || $_SESSION['userData']['medium_armor'] == "true") {
				if($light == "true" || $_SESSION['userData']['light_armor'] == "true"){
					if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
						$threshold = $medium_threshold+$light_credit+$base_credit;
					}else{
						$threshold = $medium_threshold+$light_credit;
					}
				}else if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
					$threshold = $medium_threshold+$base_credit;
				}else{
					$threshold = $medium_threshold;
				}
			} else if($light == 'true' || $_SESSION['userData']['light_armor'] == "true") {
				if($base == "true" || $_SESSION['userData']['base_armor'] == "true"){
					$threshold = $light_threshold+$base_credit;
				}else{
					$threshold = $light_threshold;
				}
			} else if($base == 'true' || $_SESSION['userData']['base_armor'] == "true") {
				$threshold = $base_thresold;
			} 
			// Disabling all gear for grunts
			// Enabling all gear for grunts
			else {
				// Skulliance promo overrides
				$extralife = 'true';
				$demolition = 'true';
				$melee = 'true';
				// End Skulliance promo overrides
			}
			
	return $threshold;
}

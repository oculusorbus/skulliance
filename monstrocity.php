<?php
if(isset($_COOKIE['SessionCookie'])){
	$cookie = $_COOKIE['SessionCookie'];
	$cookie = json_decode($cookie, true);
	$_SESSION = $cookie;
	extract($_SESSION['userData']);
	// Initiate variables
	$member = false;
	$elite = false;
	$innercircle = false;
	$roles = $_SESSION['userData']['roles'];
	if(!empty($roles)){
		foreach ($roles as $key => $roleData) {
			switch ($roleData) {
			  case "949930195584954378":
				$member = true;
				break;
	  		  case "949930360681140274":
	  			$elite = true;
	  			break;
			  case "949930529841635348";
			    $innercircle = true;
				break;
			  default:
				break;
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monstrocity Match-3</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #121212;
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
	  /*
      background-image: url(https://www.skulliance.io/staking/images/monstrocity/monstrocity.png);*/
      background-size: cover;
      background-position: center;
    }
	
	#theme-select {
	  padding: 5px;
	  margin: 10px 0;
	  background-color: #165777;
	  color: #fff;
	  border: 1px solid black;
	  border-radius: 5px;
	  font-size: 16px;
	  max-width: 265px;
	}

    h2 {
      margin-top: 0px;
      margin-bottom: 10px;
      font-weight: bold;
    }
	
	a {
		color: white;
		font-weight: bold;
		text-decoration: none;
	}

	.game-container {
	  margin-top: 20px;
	  margin-bottom: 20px;
	  text-align: center;
	  padding: 20px;
	  background-color: #002f44;
	  border-radius: 10px;
	  box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
	  width: 100%;
	  min-width: 910px;
	  max-width: 1024px;
	  box-sizing: border-box;
	  max-height: 1880px;
	  border: 3px solid black;
	  display: none; /* Initially hidden */
	}

    .game-logo {
      max-width: 300px;
      height: auto;
      margin: 0 auto 10px;
      display: block;
    }

    .turn-indicator {
      font-size: 1.2em;
      margin-bottom: 20px;
      color: #fff;
      position: relative;
      top: 10px;
      font-weight: bold;
    }

    .battlefield {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      margin-bottom: 20px;
    }

    .character {
      width: 265px;
      padding: 15px;
      background-color: #165777;
      border-radius: 5px;
      text-align: center;
      flex-shrink: 0;
      position: relative;
      top: -225px;
      min-height: 552px;
      border: 1px solid black;
    }

    .character img {
      width: 100%;
      height: auto;
      margin-bottom: 10px;
      border-radius: 5px;
      transition: transform 0.1s linear, filter 0.5s ease;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
      filter: drop-shadow(2px 5px 10px #000);
    }
    
    .character p{
      font-weight: bold;
    }
    
    .character table {
      width: 100%;
      text-align: left;
	  margin-top: 10px;
    }
    
    .character td{
      padding-top: 5px;
      width: 58%;
      text-align: right;
    }
    
    .character .attribute-label {
      font-weight: bold;
      width: 42%;
      text-align: left;
    }
    
    .character .attribute {
      font-weight: normal;
    }
	
	#player1 img, #player2 img {
	  display: none;
	}
	
	#p1-image:hover {
	  cursor: pointer;
	}
    
    .health-bar {
      width: 100%;
      height: 20px;
      background-color: #525F65;
      border-radius: 5px;
      overflow: hidden;
      margin: 5px 0;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
      filter: drop-shadow(2px 5px 10px #000);
	  border: 1px solid black;
    }

    .health {
      height: 100%;
      background-color: #4CAF50;
      transition: width 0.3s ease;
    }

    #game-board {
      box-sizing: border-box;		
      display: grid;
      gap: 0.5vh;
      background: #165777;
      padding: 1vh;
      box-sizing: border-box;
      user-select: none;
      position: relative;
      touch-action: none;
      width: 342px;
      height: 342px;
      grid-template-columns: repeat(5, 1fr);
      flex-shrink: 0;
      margin-top: 17px;
      border-radius: 5px;
      border: 1px solid black;
    }

    .tile {
      box-sizing: border-box;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5vh;
      cursor: pointer;
      transition: transform 0.2s ease;
      position: relative;
      background: #444;
      box-sizing: border-box;
      z-index: 1;
      border: 1px solid black;
      box-shadow: 0px 2px 5px black;
    }
	
	.tile:hover {
		border: 1px solid white;
	}
    
    .tile img {
      width: 80%;
      height: 80%;
      object-fit: contain;
    }
    
    .legend-tile img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .tile.game-over {
      filter: grayscale(100%);
    }

    .tile.first-attack { background-color: #4CAF50; }
    .tile.second-attack { background-color: #2196F3; }
    .tile.special-attack { background-color: #FFC107; }
    .tile.power-up { background-color: #9C27B0; }
    .tile.last-stand { background-color: #F44336; }

    .selected {
      transform: scale(1.05);
      outline: 0.25vh solid white;
      outline-offset: -0.25vh;
      z-index: 10;
      pointer-events: none;
    }

    .matched {
      animation: matchAnimation 0.3s ease forwards;
    }

    .falling {
      transition: transform 0.3s ease-out;
    }

    #game-over-container {
      position: relative;
      top: -1650px;
      left: 50%;
      width: 260px;
      max-width: 260px;
      transform: translate(-50%, -50%);
      text-align: center;
      z-index: 30;
      display: none;
      background: rgba(0, 0, 0, 0.8);
      padding: 20px;
      border-radius: 10px;
    }

    #game-over {
      font-size: 48px;
      font-family: Arial;
      color: #ffffff;
      text-shadow: 2px 2px 4px #000;
      margin: 0 0 20px 0;
      animation: gameOverPulse 1s ease-in-out infinite;
    }

    #try-again {
      font-size: 24px;
      font-family: Arial;
      font-weight: bold;
      color: #ffffff;
      background-color: #444;
      border: 2px solid #fff;
      padding: 10px 20px;
      margin: 10px 0;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      width: 250px;
      box-sizing: border-box;
      text-align: center;
    }

    #try-again:hover {
      background-color: #666;
      transform: scale(1.05);
    }
	
    #leaderboard {
      font-size: 24px;
      font-family: Arial;
      font-weight: bold;
      color: #ffffff;
      background-color: #444;
      border: 2px solid #fff;
      padding: 10px 20px;
      margin: 10px 0;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      width: 250px;
      box-sizing: border-box;
      text-align: center;
    }

    #leaderboard:hover {
      background-color: #666;
      transform: scale(1.05);
    }

    @keyframes matchAnimation {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.8; }
      100% { transform: scale(0); opacity: 0; }
    }

    @keyframes gameOverPulse {
      0% { transform: scale(1); opacity: 0.8; }
      50% { transform: scale(1.1); opacity: 1; }
      100% { transform: scale(1); opacity: 0.8; }
    }

    .log {
      margin-top: 20px;
      text-align: left;
      background-color: #165777;
      padding: 10px;
      border-radius: 5px;
      max-height: 150px;
      min-height: 150px;
      overflow-y: auto;
      position: relative;
      top: -225px;
      border: 1px solid black;
    }
    
    .log h3 {
      margin: 0 0 10px;
    }

    #battle-log { list-style: none; padding: 0; }
    #battle-log li { margin: 5px 0; opacity: 0; animation: fadeIn 0.5s forwards; }
    @keyframes fadeIn { to { opacity: 1; } }

    button {
	    padding: 10px 20px;
	    background-color: #49BBE3;
	    border: none;
	    border-radius: 5px;
	    cursor: pointer;
	    font-weight: bold;
	    margin-bottom: 20px;
	    min-width: 137px;
	    font-size: 13px;
    }

    button:hover { background-color: #54d4ff; }

	.legend {
	  margin-top: 20px;
	  text-align: left;
	  background-color: #165777;
	  padding: 10px;
	  border-radius: 5px;
	  position: relative;
	  top: -225px;
	  border: 1px solid black;
	}

	.legend h3 {
	  margin: 0 0 10px;
	  color: #fff;
	}

	.legend ul {
	  list-style: none;
	  padding: 0;
	  margin: 0;
	}

	.legend li {
	  display: flex; /* Main flex container for the list item */
	  align-items: flex-start; /* Align items at the top */
	  margin: 5px 0;
	  font-size: 0.9em;
	  line-height: 1.4; /* Improve readability with better line spacing */
	}

	.legend-tile {
	  width: 20px;
	  height: 20px;
	  flex-shrink: 0; /* Prevent the tile from shrinking */
	  margin-right: 10px; /* Space between tile and text */
	  display: inline-block;
	  border: 1px solid black;
	  padding: 3px;
	  margin-top: 3px;
	  box-shadow: 0px 2px 5px black;
	}

	/* Nested flex container for the text content */
	.legend li .text-content {
	  display: flex;
	  flex-direction: column; /* Stack the strong and span vertically */
	  flex: 1; /* Take up the remaining space */
	}

	/* Ensure the description text wraps naturally */
	.legend li .text-content span {
	  display: inline; /* Allow natural text wrapping */
	}

    .legend-tile.first-attack { background-color: #4CAF50; }
    .legend-tile.second-attack { background-color: #2196F3; }
    .legend-tile.special-attack { background-color: #FFC107; }
    .legend-tile.power-up { background-color: #9C27B0; }
    .legend-tile.last-stand { background-color: #F44336; }

    .glow-first-attack { box-shadow: 0 0 10px 5px #4CAF50; }
    .glow-second-attack { box-shadow: 0 0 10px 5px #2196F3; }
    .glow-special-attack { box-shadow: 0 0 10px 5px #FFC107; }
    .glow-last-stand { box-shadow: 0 0 10px 5px #F44336; }
    .glow-power-up { box-shadow: 0 0 10px 5px #9C27B0; }
    .glow-recoil { box-shadow: 0 0 10px 5px #FF0000; }
    .winner { animation: bounce 1.5s infinite, pulseGlow 1.5s infinite; }
    .loser { animation: pulseRedGlow 1.5s infinite; }
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    @keyframes pulseGlow {
      0% { box-shadow: 0 0 10px 5px #FFD700; }
      50% { box-shadow: 0 0 20px 10px #FFD700; }
      100% { box-shadow: 0 0 10px 5px #FFD700; }
    }
    @keyframes pulseRedGlow {
      0% { box-shadow: 0 0 10px 5px #FF0000; }
      50% { box-shadow: 0 0 20px 10px #FF0000; }
      100% { box-shadow: 0 0 10px 5px #FF0000; }
    }

	#character-select-container {
	  position: fixed;
	  top: 50%;
	  left: 50%;
	  transform: translate(-50%, -50%);
	  background: #002f44;
	  padding: 20px;
	  z-index: 100;
	  width: 100%;
	  height: 100%;
	  max-width: 980px;
	  overflow-y: auto;
	  display: block; /* Initially visible */
	  border: 3px solid black;
	  text-align: center;
	}

    #character-select-container h2 {
      text-align: center;
      margin-bottom: 20px;
	  margin-top: 20px;
    }

    .character-option {
      display: inline-block;
      width: 200px;
      margin: 10px;
      padding: 10px;
      background: #165777;
      border-radius: 5px;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
	  border: 1px solid black;
    }

    .character-option:hover {
      transform: scale(1.05);
      background: #2080ad;
    }

    .character-option img {
      width: 100%;
      height: auto;
      border-radius: 5px;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
      filter: drop-shadow(2px 5px 10px #000);
    }

    .character-option p {
      margin: 5px 0;
      font-size: 0.9em;
    }
	
	/* Use more specific class names to avoid conflicts */
	.progress-modal {
	  position: fixed;
	  top: 0;
	  left: 0;
	  width: 100%;
	  height: 100%;
	  background-color: rgba(0, 0, 0, 0.5);
	  display: flex;
	  justify-content: center;
	  align-items: center;
	  z-index: 1000;
	  margin: 0;
	  padding: 0;
	  box-sizing: border-box;
	}

	.progress-modal-content {
	  background-color: #1a1a1a;
	  padding: 20px;
	  border-radius: 10px;
	  text-align: center;
	  color: #fff;
	  font-family: Arial, sans-serif;
	  box-shadow: 0 0 10px rgba(0, 0, 0, 0.8);
	  max-width: 400px;
	  width: 90%;
	  margin: 0;
	  box-sizing: border-box;
	}

	.progress-modal-content p {
	  margin: 0 0 20px 0;
	  font-size: 18px;
	}

	.progress-modal-buttons {
	  display: flex;
	  justify-content: center;
	  gap: 10px;
	}

	.progress-modal-buttons button {
	  padding: 10px 20px;
	  font-size: 16px;
	  cursor: pointer;
	  border: none;
	  border-radius: 5px;
	  transition: background-color 0.3s;
	}

	#progress-resume {
	  background-color: #4CAF50;
	  color: white;
	}

	#progress-resume:hover {
	  background-color: #45a049;
	}

	#progress-start-fresh {
	  background-color: #f44336;
	  color: white;
	}

	#progress-start-fresh:hover {
	  background-color: #da190b;
	}

    @media (max-width: 1025px) {
      .game-container {
        width: 100%;
        min-width: 320px;
        max-width: 100%;
        padding: 10px;
        max-height: none;
        margin-top: 0px;
      }
      
      .character {
        min-height: 100px;
        text-align: right;
        width: 100%;
        max-width: 330px;
        padding: 5px;
        padding-left: 10px;
        padding-right: 10px;
      }
      
      .character h2 {
        text-align: center;
        display: none;
      }
      
      .character img {
		  width: 85px;
          float: left;
          position: absolute;
          left: 15px;
          top: 55px;
      }
      
      .character table{
        width: 70%;
        float: right;
		font-size: 13px;
		width: 65%;
      }
      
      .character td {
        padding-top: 0px;
      }
      
      .game-logo {
        opacity: 0;
      }
      
      #restart, #change-character {
        opacity: 0;
      }

      .battlefield {
        flex-direction: column;
        align-items: center;
        gap: 0px;
      }
      
      .turn-indicator {
        opacity: 0;
      }

      #game-board {
        width: 250px;
        height: 250px;
        position: relative;
        top: -225px;
        margin-top: 0px;
        background: none;
        border: none;
      }

	  .legend {
	    font-size: 0.8em;
	  }

	  .legend-tile {
	    width: 15px;
	    height: 15px;
	  }

	  .legend li {
	    line-height: 1.3; /* Adjust line spacing for smaller screens */
	  }
      
      #game-over-container {
        top: -1753px;
      }

      #character-select-container {
        width: 90%;
        padding: 10px;
      }

      .character-option {
        width: 140px;
        margin: 5px;
      }
    }
  </style>
</head>
<body>
  <div class="game-container">
    <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Logo" class="game-logo">
    <button id="restart">Restart Level</button>
    <button id="change-character" style="display: none;">Switch Character</button>
    <div class="turn-indicator" id="turn-indicator">Player 1's Turn</div>

    <div class="battlefield">
      <div class="character" id="player1">
        <h2><span id="p1-name"></span></h2>
        <img id="p1-image" src="" alt="Player 1 Image">
        <div class="health-bar"><div class="health" id="p1-health"></div></div>
        <table>
          <tr><td class="attribute-label">Health:</td><td class="attribute"><span id="p1-hp"></span></td></tr>
          <tr><td class="attribute-label">Strength:</td><td class="attribute"><span id="p1-strength"></span></td></tr>
          <tr><td class="attribute-label">Speed:</td><td class="attribute"><span id="p1-speed"></span></td></tr>
          <tr><td class="attribute-label">Tactics:</td><td class="attribute"><span id="p1-tactics"></span></td></tr>
          <tr><td class="attribute-label">Size:</td><td class="attribute"><span id="p1-size"></span></td></tr>
          <tr><td class="attribute-label">Power-Up:</td><td class="attribute"><span id="p1-powerup"></span></td></tr>
          <tr><td class="attribute-label">Type:</td><td class="attribute"><span id="p1-type"></span></td></tr>
        </table>
      </div>
      <div id="game-board"></div>
      <div class="character" id="player2">
        <h2><span id="p2-name"></span></h2>
        <img id="p2-image" src="" alt="Player 2 Image">
        <div class="health-bar"><div class="health" id="p2-health"></div></div>
        <table>
          <tr><td class="attribute-label">Health:</td><td class="attribute"><span id="p2-hp"></span></td></tr>
          <tr><td class="attribute-label">Strength:</td><td class="attribute"><span id="p2-strength"></span></td></tr>
          <tr><td class="attribute-label">Speed:</td><td class="attribute"><span id="p2-speed"></span></td></tr>
          <tr><td class="attribute-label">Tactics:</td><td class="attribute"><span id="p2-tactics"></span></td></tr>
          <tr><td class="attribute-label">Size:</td><td class="attribute"><span id="p2-size"></span></td></tr>
          <tr><td class="attribute-label">Power-Up:</td><td class="attribute"><span id="p2-powerup"></span></td></tr>
          <tr><td class="attribute-label">Type:</td><td class="attribute"><span id="p2-type"></span></td></tr>
        </table>
      </div>
    </div>
    <div class="log">
      <h3>Battle Log</h3>
      <ul id="battle-log"></ul>
    </div>
	<div class="legend">
	  <h3>Legend</h3>
	  <ul>
	    <li>
	      <span class="legend-tile first-attack"><img src="https://www.skulliance.io/staking/icons/first-attack.png" alt="First Attack"></span>
	      <div class="text-content">
	        <strong>First Attack (Slash): </strong>
	        <span>Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile second-attack"><img src="https://www.skulliance.io/staking/icons/second-attack.png" alt="Second Attack"></span>
	      <div class="text-content">
	        <strong>Second Attack (Bite): </strong>
	        <span>Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile special-attack"><img src="https://www.skulliance.io/staking/icons/special-attack.png" alt="Special Attack"></span>
	      <div class="text-content">
	        <strong>Special Attack (Shadow Strike): </strong>
	        <span>Deals 1.2× damage (Strength × 2/3/4 for 3/4/5 tiles)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile power-up"><img src="https://www.skulliance.io/staking/icons/power-up.png" alt="Power Up"></span>
	      <div class="text-content">
	        <strong>Power-Up: </strong>
	        <span>Activates a random powerup (see below)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile last-stand"><img src="https://www.skulliance.io/staking/icons/last-stand.png" alt="Last Stand"></span>
	      <div class="text-content">
	        <strong>Last Stand: </strong>
	        <span>Deals damage and mitigates 5 damage on the next attack received</span>
	      </div>
	    </li>
	  </ul>
	  <br>
	  <h3>Power-Up Effects</h3>
	  <ul>
	    <li>
	      <div class="text-content">
	        <strong>Heal (Bloody): </strong>
	        <span>Restores 10 HP (reduced by enemy tactics)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Boost Attack (Cardano): </strong>
	        <span>Adds +10 damage to the next attack (reduced by enemy tactics)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Regenerate (ADA): </strong>
	        <span>Restores 7 HP (reduced by enemy tactics)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Minor Regen (None): </strong>
	        <span>Restores 5 HP (reduced by enemy tactics)</span>
	      </div>
	    </li>
	  </ul>
	  <span>Note: Power-up effects are boosted by 50% for a match-4 and 100% for a match-5+.</span>
	  <br>
	  <br>
	  <h3>Combo Bonuses</h3>
	  <ul>
	    <li>
	      <div class="text-content">
	        <strong>Match-4 Bonus: </strong>
	        <span>50% bonus to damage and score for a single match of 4 tiles</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Match-5+ Bonus: </strong>
	        <span>100% bonus to damage and score for a single match of 5 or more tiles</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Multi-Match (6–8 tiles): </strong>
	        <span>20% bonus to score for matching 6–8 tiles across multiple matches in a single move (does not apply to cascades)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Mega Multi-Match (9+ tiles): </strong>
	        <span>200% bonus to score for matching 9 or more tiles across multiple matches in a single move (does not apply to cascades)</span>
	      </div>
	    </li>
	  </ul>
	  <br>
	  <h3>Character Traits</h3>
	  <ul>
	    <li>
	      <div class="text-content">
	        <strong>Strength: </strong>
	        <span>Determines base damage for attacks (Strength × 2/3/4 for 3/4/5+ tiles)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Speed: </strong>
	        <span>Determines turn order at the start of the level (higher Speed goes first; ties broken by Strength)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Tactics: </strong>
	        <span>Gives a (Tactics × 10)% chance to halve incoming damage and reduces enemy power-up effects by (Tactics × 5)%</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Size: </strong>
	        <span>Large: +20% health, -2 Tactics (if Tactics > 1); Medium: No effect; Small: -20% health, +2 Tactics (max 7)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Type: </strong>
	        <span>Base: 85 health; Leader: 100 health; Battle Damaged: 70 health</span>
	      </div>
	    </li>
	  </ul>
	</div>
    <div id="game-over-container">
      <div id="game-over"></div>
      <div id="game-over-buttons">
        <button id="try-again"></button>
		<form action="leaderboards.php" method="post"><input type="hidden" name="filterbystreak" id="filterbystreak" value="monthly-monstrocity"><input id="leaderboard" type="submit" value="LEADERBOARD"></form>
      </div>
    </div>

  </div>
    <div id="character-select-container">
      <h2>Select Your Character</h2>
	  <div>
	    <label for="theme-select">Theme: </label>
	    <select id="theme-select">
		  <optgroup label="Default Game Theme">
	      <option value="monstrocity">Monstrocity - Season 1</option>
	  	  </optgroup>
		  <optgroup label="Independent Artist Themes">
			  <option value="bungking">Bungking - Yume</option>
			  <option value="darkula">Darkula - Island of the Uncanny Neighbors</option>
			  <option value="darkula2">Darkula - Island of the Violent Neighbors</option>
			  <option value="muses">Josh Howard - Muses of the Multiverse</option>
			  <option value="maxi">Maxingo - Digital Hell Citizens 2: Fighters</option>
		  </optgroup>
  		  <optgroup label="Partner Project Themes">
	   		  <option value="discosolaris">Disco Solaris - Moebius Pioneers</option>
			  <option value="oculuslounge">Disco Solaris - Oculus Lounge</option>
		  </optgroup>
		  <optgroup label="Rugged Projects">
			  <option value="adapunks">ADA Punks</option>
		  </optgroup>
		  <?php
		  if($innercircle){?>
		  <optgroup label="Inner Circle Top Secret Themes">
			  <option value="occultarchives">Billy Martin - Occult Archives</option>
			  <option value="rubberrebels">Classic Cardtoons - Rubber Rebels</option>
			  <option value="danketsu">Danketsu - Legends</option>
			  <option value="deadpophell">Dead Pop Hell - NSFW</option>
			  <option value="havocworlds">Havoc Worlds - Season 1</option>
			  <option value="karranka">Karranka - Badass Heroes</option>
			  <option value="karranka2">Karranka - Japanese Ghosts: Legendary Warriors</option>
			  <option value="omen">Nemonium - Omen Legends</option>
	      </optgroup>
		  <?php }
		  ?>
	    </select>
	  </div>
	  <p><a href="https://www.jpg.store/collection/monstrocity" target="_blank">Purchase Monstrocity NFTs</a> to Add More Characters</p>
	  <p><a href="https://www.skulliance.io/staking" target="_blank">Visit Skulliance Staking</a> to Connect Wallet(s)</p>
	  <p>Rewards, Leaderboards, and Game Saves Available to Skulliance Stakers</p>
      <div id="character-options"></div>
    </div>
  <script>  eval(function(p,a,c,k,e,d){e=function(c){return(c<a?'':e(parseInt(c/a)))+((c=c%a)>35?String.fromCharCode(c+29):c.toString(36))};if(!''.replace(/^/,String)){while(c--){d[e(c)]=k[c]||e(c)}k=[function(e){return d[e]}];e=function(){return'\\w+'};c=1};while(c--){if(k[c]){p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c])}}return p;}('c 3w=[{l:"4E",E:1,D:1,q:1,o:"18",j:"1b",H:"2o 27"},{l:"6Z",E:1,D:1,q:1,o:"3j",j:"1b",H:"2o 27"},{l:"77 76",E:2,D:2,q:2,o:"3m",j:"1b",H:"2o 27"},{l:"6U",E:2,D:2,q:2,o:"18",j:"1b",H:"2o 27"},{l:"71",E:3,D:3,q:3,o:"18",j:"1b",H:"2i"},{l:"72",E:3,D:3,q:3,o:"18",j:"1b",H:"2i"},{l:"6X 6W",E:4,D:4,q:4,o:"3m",j:"1b",H:"2i"},{l:"7b 3E 7a",E:4,D:4,q:4,o:"18",j:"1b",H:"2i"},{l:"79",E:5,D:5,q:5,o:"18",j:"1b",H:"39 44"},{l:"74",E:5,D:5,q:5,o:"18",j:"1b",H:"39 44"},{l:"6V",E:6,D:6,q:6,o:"3m",j:"1b",H:"2p"},{l:"73",E:7,D:7,q:7,o:"3j",j:"1b",H:"2p"},{l:"6Y",E:7,D:7,q:7,o:"18",j:"1b",H:"2p"},{l:"78",E:8,D:7,q:7,o:"18",j:"1b",H:"2p"},{l:"4E",E:1,D:1,q:1,o:"18",j:"1n",H:"2o 27"},{l:"6Z",E:1,D:1,q:1,o:"3j",j:"1n",H:"2o 27"},{l:"77 76",E:2,D:2,q:2,o:"3m",j:"1n",H:"2o 27"},{l:"6U",E:2,D:2,q:2,o:"18",j:"1n",H:"2o 27"},{l:"71",E:3,D:3,q:3,o:"18",j:"1n",H:"2i"},{l:"72",E:3,D:3,q:3,o:"18",j:"1n",H:"2i"},{l:"6X 6W",E:4,D:4,q:4,o:"3m",j:"1n",H:"2i"},{l:"7b 3E 7a",E:4,D:4,q:4,o:"18",j:"1n",H:"2i"},{l:"79",E:5,D:5,q:5,o:"18",j:"1n",H:"39 44"},{l:"74",E:5,D:5,q:5,o:"18",j:"1n",H:"39 44"},{l:"6V",E:6,D:6,q:6,o:"3m",j:"1n",H:"2p"},{l:"73",E:7,D:7,q:7,o:"3j",j:"1n",H:"2p"},{l:"6Y",E:7,D:7,q:7,o:"18",j:"1n",H:"2p"},{l:"78",E:8,D:7,q:7,o:"18",j:"1n",H:"2p"}];c 6J={"7b 3E 7a":"23","4E":"23","79":"23","78":"3O","77 76":"23","74":"3O","73":"3O","72":"23","71":"23","6Z":"23","6Y":"23","6X 6W":"3O","6V":"3O","6U":"23"};cc 7c{cb(2J){b.8C=\'ca\'59 c9||9d.c8>0||9d.c7>0;b.1k=5;b.1s=5;b.m=[];b.z=N;b.I=W;b.13=N;b.k=N;b.u=N;b.1e="5F";b.1T=W;b.1w=N;b.1u=N;b.3f=0;b.3e=0;b.F=1;b.2J=2J;b.4s=[];b.3u=W;b.8F=["5a-1H","6t-1H","6u-1H","5N-5M","6r-6q"];b.1z=[];b.1y=0;b.3i=98.c6(\'97\')||\'2h\';b.2R=`1A:b.6T();b.14={Q:11 2g(\'1A://2f.2e.2d/2c/14/4w.2b\'),6g:11 2g(\'1A://2f.2e.2d/2c/14/4w.2b\'),8q:11 2g(\'1A://2f.2e.2d/2c/14/c5.2b\'),I:11 2g(\'1A://2f.2e.2d/2c/14/c4.2b\'),62:11 2g(\'1A://2f.2e.2d/2c/14/c3.2b\'),65:11 2g(\'1A://2f.2e.2d/2c/14/c2.2b\'),5Y:11 2g(\'1A://2f.2e.2d/2c/14/c1.2b\'),7P:11 2g(\'1A://2f.2e.2d/2c/14/c0.2b\'),8b:11 2g(\'1A://2f.2e.2d/2c/14/bZ.2b\'),8a:11 2g(\'1A://2f.2e.2d/2c/14/bY.2b\'),6A:11 2g(\'1A://2f.2e.2d/2c/14/bX.2b\')};b.6H();b.5s()}2y 3Q(){h.f("3Q: 6K 2y 9a");b.4s=b.2J.5k(1f=>b.5v(1f));1j b.32(1q);c 9c=1j b.95();c{2a,29,4A}=9c;d(4A){h.f(`3Q:bW 2L 2a=${2a},29=${29}`);c 9b=1j b.4t(2a,29);d(9b){b.F=2a;b.1y=29;f(`bV at 1M ${b.F},2A ${b.1y}`)}G{b.F=1;b.1y=0;1j b.63();f(`6K 8R at 1M 1`)}}G{b.F=1;b.1y=0;f(`5j 5Z 16 4b,bU at 1M 1`)}h.f("3Q: bT 9a 21")}6T(){n.3s.C.bS=`bR(${b.2R}2h.3v)`}8Y(99){b.3i=99;b.2R=`1A:98.bQ(\'97\',b.3i);b.6T();b.4s=b.2J.5k(1f=>b.5v(1f));d(b.k){b.k.3h=b.6R(b.k);b.5u()}d(b.u){b.u.3h=b.6R(b.u);b.6I()}n.6L(\'.17-5w\').35=`${b.2R}5w.3v`;c 26=n.A("15-4w-26");d(26.C.1F==="2D"){b.32(b.k===N)}}2y 61(){c 1X={F:b.F,1y:b.1y};h.f("bP 61 bO 2L 1X:",1X);1W{c 19=1j 4W(\'3p/5S-2h-16.3o\',{4V:\'5R\',4U:{\'4T-3V\':\'4S/34\'},3s:3R.7J(1X)});h.f("93 1p:",19.1p);c 4C=1j 19.96();h.f("bN 19 96:",4C);d(!19.4R){4Q 11 1C(`4P P!4O:${19.1p}`)}c V=3R.5H(4C);h.f("92 19:",V);d(V.1p===\'3U\'){f(\'60 5Z: 1M \'+b.F)}G{h.P(\'7e 12 5S 16:\',V.2k)}}2w(P){h.P(\'1C 5Q 16:\',P)}}2y 95(){1W{h.f("bM 16 3C 3p/94-2h-16.3o");c 19=1j 4W(\'3p/94-2h-16.3o\',{4V:\'7h\',4U:{\'4T-3V\':\'4S/34\'}});h.f("93 1p:",19.1p);d(!19.4R){4Q 11 1C(`4P P!4O:${19.1p}`)}c V=1j 19.34();h.f("92 19:",V);d(V.1p===\'3U\'&&V.16){c 16=V.16;B{2a:16.F||1,29:16.1y||0,4A:1q}}G{h.f("5j 16 4b bL 1p 86 3U:",V);B{2a:1,29:0,4A:W}}}2w(P){h.P(\'1C bK 16:\',P);B{2a:1,29:0,4A:W}}}2y 63(){1W{c 19=1j 4W(\'3p/8k-2h-16.3o\',{4V:\'5R\',4U:{\'4T-3V\':\'4S/34\'}});d(!19.4R){4Q 11 1C(`4P P!4O:${19.1p}`)}c V=1j 19.34();d(V.1p===\'3U\'){b.F=1;b.1y=0;f(\'60 bJ\')}}2w(P){h.P(\'1C 8i 16:\',P)}}6H(){c 4p=n.A("17-m");c 91=4p.bI||6f;b.R=(91-(0.5*(b.1k-1)))/b.1k}5v(1f){r 1U;5E(1f.j){1V"1b":1U="5D";1Q;1V"1n":1U="8Z";1Q;1V"6Q 6P":1U="3S-5X";1Q;6O:1U="5D"}c 3h=`${b.2R}${1U}/${1f.l.5W().5V(//g,\'-\')}.3v`;r 4z;5E(1f.j){1V"1n":4z=2S;1Q;1V"6Q 6P":4z=70;1Q;1V"1b":6O:4z=85}r 4y=1;r 4x=0;5E(1f.o){1V"3j":4y=1.2;4x=1f.q>1?-2:0;1Q;1V"3m":4y=0.8;4x=1f.q<6?2:7-1f.q;1Q;1V"18":4y=1;4x=0;1Q}c 6S=t.24(4z*4y);c 90=t.1R(1,t.1d(7,1f.q+4x));B{l:1f.l,j:1f.j,E:1f.E,D:1f.D,q:90,o:1f.o,H:1f.H,J:6S,1h:6S,43:W,55:0,5c:W,3h}}6R(15){r 1U;5E(15.j){1V"1b":1U="5D";1Q;1V"1n":1U="8Z";1Q;1V"6Q 6P":1U="3S-5X";1Q;6O:1U="5D"}B`${b.2R}${1U}/${15.l.5W().5V(//g,\'-\')}.3v`}2y 32(5B=W){h.f(`32:bH 2L 5B=${5B}`);c 26=n.A("15-4w-26");c 6N=n.A("15-bG");c 5C=n.A("3i-4w");6N.5t="";26.C.1F="2D";5C.8X=b.3i;5C.bF=()=>{b.8Y(5C.8X)};b.4s.2F((15,5g)=>{c 3P=n.2l("4r");3P.4q="15-3P";3P.5t=`<3N 35="${15.3h}"8E="${15.l}"><p><8W>${15.l}</8W></p><p>3V:${15.j}</p><p>7Q:${15.1h}</p><p>bE:${15.E}</p><p>bD:${15.D}</p><p>88:${15.q}</p><p>bC:${15.o}</p><p>7Z-bB:${15.H}</p>`;3P.1J("28",()=>{h.f(`32:bA 3g:${15.l}`);26.C.1F="3M";d(5B){b.k={...15};h.f(`32:b.k bz:${b.k.l}`);b.4o()}G{b.8V(15)}});6N.2I(3P)})}8V(8U){c 8T=b.k.J;c 8S=b.k.1h;c 2v={...8U};c 2C=t.1d(1,8T/8S);2v.J=t.24(2v.1h*2C);2v.J=t.1R(0,t.1d(2v.1h,2v.J));2v.43=W;2v.55=0;2v.5c=W;b.k=2v;b.5u();b.3z(b.k);f(`${b.k.l}bx bw 6n bv 2L ${b.k.J}/${b.k.1h}41!`);b.13=b.k.D>b.u.D?b.k:b.u.D>b.k.D?b.u:b.k.E>=b.u.E?b.k:b.u;3q.T=`1M ${b.F}-${b.13===b.k?"2x":"3Y"}\'s 52`;d(b.13===b.u&&b.1e!=="I"){1Z(()=>b.2X(),4L)}}4t(2a,29){h.f(`4t:bu bt K 2B=${2a},1L=${29}`);B 11 7i((3n)=>{c 1B=n.2l("4r");1B.5A="16-1B";1B.4q="16-1B";c 4u=n.2l("4r");4u.4q="16-1B-bs";c 2k=n.2l("p");2k.5A="16-2k";2k.T=`6M 3C 1M ${2a}2L 2A 6w ${29}?`;4u.2I(2k);c 4v=n.2l("4r");4v.4q="16-1B-br";c 3l=n.2l("6G");3l.5A="16-bq";3l.T="6M";4v.2I(3l);c 3k=n.2l("6G");3k.5A="16-bp-8R";3k.T="8O";4v.2I(3k);4u.2I(4v);1B.2I(4u);n.3s.2I(1B);1B.C.1F="bo";c 5y=()=>{h.f("4t: 8Q 8P 6M");1B.C.1F="3M";n.3s.5I(1B);3l.5z("28",5y);3k.5z("28",5x);3n(1q)};c 5x=()=>{h.f("4t: 8Q 8P 8O");1B.C.1F="3M";n.3s.5I(1B);3l.5z("28",5y);3k.5z("28",5x);3n(W)};3l.1J("28",5y);3k.1J("28",5x)})}4o(){h.f(`4o:bn 2L b.F=${b.F}`);c 8N=n.6L(".17-26");c 8M=n.A("17-m");8N.C.1F="2D";8M.C.bm="bl";n.6L(\'.17-5w\').35=`${b.2R}5w.3v`;b.14.62.22();f(`6K 1M ${b.F}...`);b.u=b.5v(3w[b.F-1]);h.f(`bk bj K 2B ${b.F}:${b.u.l}(3w[${b.F-1}])`);b.k.J=b.k.1h;b.13=b.k.D>b.u.D?b.k:b.u.D>b.k.D?b.u:b.k.E>=b.u.E?b.k:b.u;b.1e="5F";b.I=W;b.1z=[];1D.1r.2O(\'5T\',\'5U\');1K.1r.2O(\'5T\',\'5U\');b.5u();b.6I();d(6J[b.k.l]==="23"){1D.C.X="4M(-1)"}G{1D.C.X="3M"}d(6J[b.u.l]==="3O"){1K.C.X="4M(-1)"}G{1K.C.X="3M"}b.3z(b.k);b.3z(b.u);2z.5t="";I.T="";d(b.k.o!=="18"){f(`${b.k.l}\'s ${b.k.o}o ${b.k.o==="3j"?"8L J 12 "+b.k.1h+" 48 8K q 12 "+b.k.q:"8J J 12 "+b.k.1h+" 48 8I q 12 "+b.k.q}!`)}d(b.u.o!=="18"){f(`${b.u.l}\'s ${b.u.o}o ${b.u.o==="3j"?"8L J 12 "+b.u.1h+" 48 8K q 12 "+b.u.q:"8J J 12 "+b.u.1h+" 48 8I q 12 "+b.u.q}!`)}f(`${b.k.l}bi at bh E 2L ${b.k.J}/${b.k.1h}41!`);f(`${b.13.l}bg 5a!`);b.8G();b.1e=b.13===b.k?"2n":"2X";3q.T=`1M ${b.F}-${b.13===b.k?"2x":"3Y"}\'s 52`;d(b.4s.1c>1){n.A("8B-15").C.1F="bf-2D"}d(b.13===b.u){1Z(()=>b.2X(),4L)}}5u(){7B.T=b.3i===\'2h\'?b.k.l:"2x 1";7t.T=b.k.j;7y.T=b.k.E;7x.T=b.k.D;7w.T=b.k.q;7v.T=b.k.o;7u.T=b.k.H;1D.35=b.k.3h;1D.8H=()=>1D.C.1F="2D"}6I(){7s.T=b.3i===\'2h\'?b.u.l:"be 3Y";7j.T=b.u.j;7o.T=b.u.E;7n.T=b.u.D;7m.T=b.u.q;7l.T=b.u.o;7k.T=b.u.H;1K.35=b.u.3h;1K.8H=()=>1K.C.1F="2D"}8G(){b.m=[];K(r y=0;y<b.1s;y++){b.m[y]=[];K(r x=0;x<b.1k;x++){r w;bd{w=b.6d()}bc((x>=2&&b.m[y][x-1]?.j===w.j&&b.m[y][x-2]?.j===w.j)||(y>=2&&b.m[y-1]?.[x]?.j===w.j&&b.m[y-2]?.[x]?.j===w.j));b.m[y][x]=w}}b.2Q()}6d(){B{j:7E(b.8F),L:N}}2Q(){b.6H();c 4p=n.A("17-m");4p.5t="";K(r y=0;y<b.1s;y++){K(r x=0;x<b.1k;x++){c w=b.m[y][x];d(w.j===N)5f;c 2H=n.2l("4r");2H.4q=`w ${w.j}`;d(b.I)2H.1r.1x("17-1g");c 3N=n.2l(\'3N\');3N.35=`1A:3N.8E=w.j;2H.2I(3N);2H.8D.x=x;2H.8D.y=y;4p.2I(2H);w.L=2H;d(!b.1T||(b.z&&(b.z.x!==x||b.z.y!==y))){2H.C.X="1I(0, 0)"}}}n.A("17-1g-26").C.1F=b.I?"2D":"3M"}5s(){c m=n.A("17-m");d(b.8C){m.1J("bb",(e)=>b.8v(e));m.1J("ba",(e)=>b.8u(e));m.1J("b9",(e)=>b.8t(e))}G{m.1J("b8",(e)=>b.8y(e));m.1J("b7",(e)=>b.8x(e));m.1J("b6",(e)=>b.8w(e))}n.A("1W-7V").1J("28",()=>b.5r());n.A("b5").1J("28",()=>{b.4o()});c 8A=n.A("8B-15");c 1D=n.A("1Y-4F");8A.1J("28",()=>{h.f("5s: b4 b3 6G 8z");b.32(W)});1D.1J("28",()=>{h.f("5s: 2x 1 4F 8z");b.32(W)})}5r(){h.f(`5r 3W:F=${b.F},u.J=${b.u.J}`);d(b.u.J<=0&&b.F>3w.1c){b.F=1;h.f(`b2 12 1M 1:F=${b.F}`)}b.4o();h.f(`5r 21:F=${b.F}`)}8y(e){d(b.I||b.1e!=="2n"||b.13!==b.k)B;e.5q();c w=b.6B(e);d(!w||!w.L)B;b.1T=1q;b.z={x:w.x,y:w.y};w.L.1r.1x("3g");c 1v=n.A("17-m").4m();b.3f=e.4l-(1v.4k+b.z.x*b.R);b.3e=e.4j-(1v.4i+b.z.y*b.R)}8x(e){d(!b.1T||!b.z||b.I||b.1e!=="2n")B;e.5q();c 1v=n.A("17-m").4m();c 6F=e.4l-1v.4k-b.3f;c 6E=e.4j-1v.4i-b.3e;c 31=b.m[b.z.y][b.z.x].L;31.C.2P="";d(!b.1u){c 2u=t.3J(6F-(b.z.x*b.R));c 2t=t.3J(6E-(b.z.y*b.R));d(2u>2t&&2u>5)b.1u="2q";G d(2t>2u&&2t>5)b.1u="5p"}d(!b.1u)B;d(b.1u==="2q"){c 3L=t.1R(0,t.1d((b.1k-1)*b.R,6F));31.C.X=`1I(${3L-b.z.x*b.R}2m,0)5o(1.45)`;b.1w={x:t.24(3L/b.R),y:b.z.y}}G d(b.1u==="5p"){c 3K=t.1R(0,t.1d((b.1s-1)*b.R,6E));31.C.X=`1I(0,${3K-b.z.y*b.R}2m)5o(1.45)`;b.1w={x:b.z.x,y:t.24(3K/b.R)}}}8w(e){d(!b.1T||!b.z||!b.1w||b.I||b.1e!=="2n"){d(b.z){c w=b.m[b.z.y][b.z.x];d(w.L)w.L.1r.2O("3g")}b.1T=W;b.z=N;b.1w=N;b.1u=N;b.2Q();B}c w=b.m[b.z.y][b.z.x];d(w.L)w.L.1r.2O("3g");b.51(b.z.x,b.z.y,b.1w.x,b.1w.y);b.1T=W;b.z=N;b.1w=N;b.1u=N}8v(e){d(b.I||b.1e!=="2n"||b.13!==b.k)B;e.5q();c w=b.6B(e.4n[0]);d(!w||!w.L)B;b.1T=1q;b.z={x:w.x,y:w.y};w.L.1r.1x("3g");c 1v=n.A("17-m").4m();b.3f=e.4n[0].4l-(1v.4k+b.z.x*b.R);b.3e=e.4n[0].4j-(1v.4i+b.z.y*b.R)}8u(e){d(!b.1T||!b.z||b.I||b.1e!=="2n")B;e.5q();c 1v=n.A("17-m").4m();c 6D=e.4n[0].4l-1v.4k-b.3f;c 6C=e.4n[0].4j-1v.4i-b.3e;c 31=b.m[b.z.y][b.z.x].L;b1(()=>{d(!b.1u){c 2u=t.3J(6D-(b.z.x*b.R));c 2t=t.3J(6C-(b.z.y*b.R));d(2u>2t&&2u>7)b.1u="2q";G d(2t>2u&&2t>7)b.1u="5p"}31.C.2P="";d(b.1u==="2q"){c 3L=t.1R(0,t.1d((b.1k-1)*b.R,6D));31.C.X=`1I(${3L-b.z.x*b.R}2m,0)5o(1.45)`;b.1w={x:t.24(3L/b.R),y:b.z.y}}G d(b.1u==="5p"){c 3K=t.1R(0,t.1d((b.1s-1)*b.R,6C));31.C.X=`1I(0,${3K-b.z.y*b.R}2m)5o(1.45)`;b.1w={x:b.z.x,y:t.24(3K/b.R)}}})}8t(e){d(!b.1T||!b.z||!b.1w||b.I||b.1e!=="2n"){d(b.z){c w=b.m[b.z.y][b.z.x];d(w.L)w.L.1r.2O("3g")}b.1T=W;b.z=N;b.1w=N;b.1u=N;b.2Q();B}c w=b.m[b.z.y][b.z.x];d(w.L)w.L.1r.2O("3g");b.51(b.z.x,b.z.y,b.1w.x,b.1w.y);b.1T=W;b.z=N;b.1w=N;b.1u=N}6B(e){c 1v=n.A("17-m").4m();c x=t.2N((e.4l-1v.4k)/b.R);c y=t.2N((e.4j-1v.4i)/b.R);d(x>=0&&x<b.1k&&y>=0&&y<b.1s){B{x,y,L:b.m[y][x].L}}B N}51(S,O,1t,1i){c R=b.R;r 4h;c 3d=[];c 2r=[];d(O===1i){4h=S<1t?1:-1;c 5m=t.1d(S,1t);c 8s=t.1R(S,1t);K(r x=5m;x<=8s;x++){3d.2G({...b.m[O][x]});2r.2G(b.m[O][x].L)}}G d(S===1t){4h=O<1i?1:-1;c 5l=t.1d(O,1i);c 8r=t.1R(O,1i);K(r y=5l;y<=8r;y++){3d.2G({...b.m[y][S]});2r.2G(b.m[y][S].L)}}c 4f=b.m[O][S].L;c 2u=(1t-S)*R;c 2t=(1i-O)*R;4f.C.2P="X 0.2s 4e";4f.C.X=`1I(${2u}2m,${2t}2m)`;r i=0;d(O===1i){K(r x=t.1d(S,1t);x<=t.1R(S,1t);x++){d(x===S)5f;c 3f=4h*-R*(x-S)/t.3J(1t-S);2r[i].C.2P="X 0.2s 4e";2r[i].C.X=`1I(${3f}2m,0)`;i++}}G{K(r y=t.1d(O,1i);y<=t.1R(O,1i);y++){d(y===O)5f;c 3e=4h*-R*(y-O)/t.3J(1i-O);2r[i].C.2P="X 0.2s 4e";2r[i].C.X=`1I(0,${3e}2m)`;i++}}1Z(()=>{d(O===1i){c 2q=b.m[O];c 5n=[...2q];d(S<1t){K(r x=S;x<1t;x++)2q[x]=5n[x+1]}G{K(r x=S;x>1t;x--)2q[x]=5n[x-1]}2q[1t]=5n[S]}G{c 4g=[];K(r y=0;y<b.1s;y++)4g[y]={...b.m[y][S]};d(O<1i){K(r y=O;y<1i;y++)b.m[y][S]=4g[y+1]}G{K(r y=O;y>1i;y--)b.m[y][S]=4g[y-1]}b.m[1i][1t]=4g[O]}b.2Q();c 57=b.3a(1t,1i);d(57){b.1e="69"}G{f("5j Q, b0 1G...");b.14.8q.22();4f.C.2P="X 0.2s 4e";4f.C.X="1I(0, 0)";2r.2F(L=>{L.C.2P="X 0.2s 4e";L.C.X="1I(0, 0)"});1Z(()=>{d(O===1i){c 5m=t.1d(S,1t);K(r i=0;i<3d.1c;i++){b.m[O][5m+i]={...3d[i],L:2r[i]}}}G{c 5l=t.1d(O,1i);K(r i=0;i<3d.1c;i++){b.m[5l+i][S]={...3d[i],L:2r[i]}}}b.2Q();b.1e="2n"},2M)}},2M)}3a(8p=N,8o=N){h.f("3a 3W, I:",b.I);d(b.I){h.f("1o 1g, 8l 3a");B W}c 2Y=8p!==N&&8o!==N;h.f(`aZ aY 1O:${2Y}`);c Z=b.3x();h.f(`aX ${Z.1c}Z:`,Z);r 4d=1;r 4c="";d(2Y&&Z.1c>1){c 3c=Z.aW((8n,Q)=>8n+Q.6x,0);h.f(`4X 1G 3I 3C U 1O:${3c}`);d(3c>=6&&3c<=8){4d=1.2;4c=`8m-6v!${3c}1G 3I K a 20%3A!`;b.14.6A.22()}G d(3c>=9){4d=3.0;4c=`aV 8m-6v!${3c}1G 3I K a 2M%3A!`;b.14.6A.22()}}d(Z.1c>0){c 30=11 4a();r 33=0;c 1a=b.13;c M=b.13===b.k?b.u:b.k;1W{Z.2F(Q=>{h.f("aU Q:",Q);Q.1S.2F(3G=>30.1x(3G));c v=b.47(Q,2Y);h.f(`aT 3C Q:${v}`);d(b.I){h.f("1o 1g aS aR Q 6z, aQ aP 6z");B}d(v>0)33+=v});d(b.I){h.f("1o 1g 6k 6z Z, 8l 3a");B 1q}h.f(`4X v 84:${33},1G 12 8k:`,[...30]);d(33>0&&!b.I){1Z(()=>{d(b.I){h.f("1o 1g, 36 5K 8j");B}h.f("6j 5K K M:",M.l);b.7F(M,33)},2S)}1Z(()=>{d(b.I){h.f("1o 1g, 36 Q 8j 3E 8h");B}h.f("6j 3I 1G, 30:",[...30]);30.2F(w=>{c[x,y]=w.8g(",").5k(8f);d(b.m[y][x]?.L){b.m[y][x].L.1r.1x("3I")}G{h.aO(`aN at(${x},${y})5e aM L 12 aL`)}});1Z(()=>{d(b.I){h.f("1o 1g, 36 w 8i 3E 8h");B}h.f("aK 3I 1G:",[...30]);30.2F(w=>{c[x,y]=w.8g(",").5k(8f);d(b.m[y][x]){b.m[y][x].j=N;b.m[y][x].L=N}});b.14.Q.22();h.f("aJ 1G");d(4d>1&&b.1z.1c>0){c Y=b.1z[b.1z.1c-1];c 8e=Y.1N;Y.1N=t.24(Y.1N*4d);d(4c){f(4c);f(`4Z 1N aI 3C ${8e}12 ${Y.1N}6k aH-Q 3A!`)}}b.6i(()=>{d(b.I){h.f("1o 1g, 36 3X");B}h.f("87 aG, aF 7C");b.3X()})},6f)},2M);B 1q}2w(P){h.P("1C 59 3a:",P);b.1e=b.13===b.k?"2n":"2X";B W}}h.f("5j Z 4b, 8c W");B W}3x(){h.f("3x 3W");c Z=[];1W{c 3H=[];K(r y=0;y<b.1s;y++){r S=0;K(r x=0;x<=b.1k;x++){c 5i=x<b.1k?b.m[y][x]?.j:N;d(5i!==b.m[y][S]?.j||x===b.1k){c 5h=x-S;d(5h>=3){c 2Z=11 4a();K(r i=S;i<x;i++){2Z.1x(`${i},${y}`)}3H.2G({j:b.m[y][S].j,1S:2Z});h.f(`aE Q 4b at 2q ${y},aD ${S}-${x-1}:`,[...2Z])}S=x}}}K(r x=0;x<b.1k;x++){r O=0;K(r y=0;y<=b.1s;y++){c 5i=y<b.1s?b.m[y][x]?.j:N;d(5i!==b.m[O][x]?.j||y===b.1s){c 5h=y-O;d(5h>=3){c 2Z=11 4a();K(r i=O;i<y;i++){2Z.1x(`${x},${i}`)}3H.2G({j:b.m[O][x].j,1S:2Z});h.f(`aC Q 4b at aB ${x},aA ${O}-${y-1}:`,[...2Z])}O=y}}}c 6y=[];c 49=11 4a();3H.2F((Q,5g)=>{d(49.5e(5g))B;c 3b={j:Q.j,1S:11 4a(Q.1S)};49.1x(5g);K(r i=0;i<3H.1c;i++){d(49.5e(i))5f;c 5d=3H[i];d(5d.j===3b.j){c 8d=[...5d.1S].az(3G=>3b.1S.5e(3G));d(8d){5d.1S.2F(3G=>3b.1S.1x(3G));49.1x(i)}}}6y.2G({j:3b.j,1S:3b.1S,6x:3b.1S.o})});Z.2G(...6y);h.f("3x 21, 8c Z:",Z);B Z}2w(P){h.P("1C 59 3x:",P);B[]}}47(Q,2Y=1q){h.f("47 3W, Q:",Q,"2Y:",2Y);c 1a=b.13;c M=b.13===b.k?b.u:b.k;c j=Q.j;c o=Q.6x;r v=0;r 6s=0;h.f(`${M.l}J ay Q:${M.J}`);d(o==4){b.14.8b.22();f(`${1a.l}5G a Q 6w ${o}1G!`)}d(o>=5){b.14.8a.22();f(`${1a.l}5G a Q 6w ${o}1G!`)}d(j==="5a-1H"||j==="6t-1H"||j==="6u-1H"||j==="6r-6q"){v=t.24(1a.E*(o===3?2:o===4?3:4));r 1P=1;d(o===4){1P=1.5}G d(o>=5){1P=2.0}v=t.24(v*1P);h.f(`1b v:${1a.E*(o===3?2:o===4?3:4)},6v 3A:${1P},4X v:${v}`);d(j==="6u-1H"){v=t.24(v*1.2);h.f(`ax 1H aw 5b,v:${v}`)}d(1a.43){v+=1a.55||10;1a.43=W;f(`${1a.l}\'s 39 av.`);h.f(`39 5b,v:${v}`)}6s=v;c 89=M.q*10;d(t.7D()*2S<89){v=t.2N(v/2);f(`${M.l}\'s q au 6n as,ar aq ${v}v!`);h.f(`88 5b,v 40 12:${v}`)}r 3F=0;d(M.5c){3F=t.1d(v,5);v=t.1R(0,v-3F);M.5c=W;h.f(`6p 6o 5b,ap ${3F},v:${v}`)}c 6m=j==="5a-1H"?"ao":j==="6t-1H"?"an":"am al";r 3D;d(3F>0){3D=`${1a.l}38 ${6m}6l ${M.l}K ${6s}v,48 ${M.l}\'s 6p 6o ak ${3F}v,aj 59 ${v}v!`}G d(j==="6r-6q"){3D=`${1a.l}38 6p 6o,ai ${v}v 12 ${M.l}3E ah 12 ag 5 v 6l 6n 7Y 1H!`}G{3D=`${1a.l}38 ${6m}6l ${M.l}K ${v}v!`}d(2Y){f(3D)}G{f(`87:${3D}`)}M.J=t.1R(0,M.J-v);h.f(`${M.l}J 6k v:${M.J}`);b.3z(M);h.f("af 3t 3C 47");b.3t();d(!b.I){h.f("1o 86 1g, 69 1H");b.7H(1a,v,j)}}G d(j==="5N-5M"){b.80(1a,M,o);d(!b.I){h.f("6j H");b.7G(1a)}}d(!b.1z[b.1z.1c-1]||b.1z[b.1z.1c-1].21){b.1z.2G({1N:0,Z:0,2C:0,21:W})}c Y=b.1z[b.1z.1c-1];Y.1N+=v;Y.Z+=1;h.f(`47 21,v 84:${v}`);B v}6i(6e){d(b.I){h.f("1o 1g, 36 6i");B}c 3B=b.82();c 58="ae";K(r x=0;x<b.1k;x++){K(r y=0;y<b.1s;y++){c w=b.m[y][x];d(w.L&&w.L.C.X==="1I(83, 83)"){c 6h=b.81(x,y);d(6h>0){w.L.1r.1x(58);w.L.C.X=`1I(0,${6h*b.R}2m)`}}}}b.2Q();d(3B){1Z(()=>{d(b.I){h.f("1o 1g, 36 6g ad");B}b.14.6g.22();c 57=b.3a();c 1G=n.ac(`.${58}`);1G.2F(w=>{w.1r.2O(58);w.C.X="1I(0, 0)"});d(!57){6e()}},6f)}G{6e()}}82(){r 3B=W;K(r x=0;x<b.1k;x++){r 46=0;K(r y=b.1s-1;y>=0;y--){d(!b.m[y][x].j){46++}G d(46>0){b.m[y+46][x]=b.m[y][x];b.m[y][x]={j:N,L:N};3B=1q}}K(r i=0;i<46;i++){b.m[i][x]=b.6d();3B=1q}}B 3B}81(x,y){r 6c=0;K(r i=y+1;i<b.1s;i++){d(!b.m[i][x].j){6c++}G{1Q}}B 6c}80(U,M,o){c 42=1-(M.q*0.45);r 1m;r 1l;r 2E;r 1P=1;r 37="";d(o===4){1P=1.5;37=" (50% 3A K Q-4)"}G d(o>=5){1P=2.0;37=" (2S% 3A K Q-5+)"}d(U.H==="2p"){1l=10*1P;1m=t.2N(1l*42);2E=1l-1m;U.J=t.1d(U.1h,U.J+1m);f(`${U.l}38 2p,6b ${1m}41${37}${M.q>0?`(54 ${1l},40 by ${2E}53 12 ${M.l}\'s q)`:""}!`)}G d(U.H==="39 44"){1l=10*1P;1m=t.2N(1l*42);2E=1l-1m;U.43=1q;U.55=1m;f(`${U.l}38 7Z ab,7Y 1H+${1m}v${37}${M.q>0?`(54 ${1l},40 by ${2E}53 12 ${M.l}\'s q)`:""}!`)}G d(U.H==="2i"){1l=7*1P;1m=t.2N(1l*42);2E=1l-1m;U.J=t.1d(U.1h,U.J+1m);f(`${U.l}38 27,6b ${1m}41${37}${M.q>0?`(54 ${1l},40 by ${2E}53 12 ${M.l}\'s q)`:""}!`)}G d(U.H==="2o 27"){1l=5*1P;1m=t.2N(1l*42);2E=1l-1m;U.J=t.1d(U.1h,U.J+1m);f(`${U.l}38 2o 27,6b ${1m}41${37}${M.q>0?`(54 ${1l},40 by ${2E}53 12 ${M.l}\'s q)`:""}!`)}b.3z(U)}3z(U){c 6a=U===b.k?7A:7r;c 7X=U===b.k?7z:7q;c 3Z=(U.J/U.1h)*2S;6a.C.1k=`${3Z}%`;r 3y;d(3Z>75){3y=\'#aa\'}G d(3Z>50){3y=\'#a9\'}G d(3Z>25){3y=\'#a8\'}G{3y=\'#a7\'}6a.C.a6=3y;7X.T=`${U.J}/${U.1h}`}3X(){d(b.1e==="I"||b.I){h.f("1o 1g, 36 3X");B}b.13=b.13===b.k?b.u:b.k;b.1e=b.13===b.k?"2n":"2X";3q.T=`1M ${b.F}-${b.13===b.k?"2x":"3Y"}\'s 52`;f(`52 a5 12 ${b.13===b.k?"2x":"3Y"}`);d(b.13===b.u){1Z(()=>b.2X(),4L)}}2X(){d(b.1e!=="2X"||b.13!==b.u)B;b.1e="69";c 1O=b.7W();d(1O){f(`${b.u.l}a4 1G at(${1O.2V},${1O.2W})12(${1O.2T},${1O.2U})`);b.51(1O.2V,1O.2W,1O.2T,1O.2U)}G{f(`${b.u.l}a3...`);b.3X()}}7W(){K(r y=0;y<b.1s;y++){K(r x=0;x<b.1k;x++){d(x<b.1k-1&&b.68(x,y,x+1,y))B{2V:x,2W:y,2T:x+1,2U:y};d(y<b.1s-1&&b.68(x,y,x,y+1))B{2V:x,2W:y,2T:x,2U:y+1}}}B N}68(2V,2W,2T,2U){c 67={...b.m[2W][2V]};c 66={...b.m[2U][2T]};b.m[2W][2V]=66;b.m[2U][2T]=67;c Z=b.3x().1c>0;b.m[2W][2V]=67;b.m[2U][2T]=66;B Z}2y 3t(){d(b.I||b.3u){h.f(`3t a2:I=${b.I},3u=${b.3u},F=${b.F}`);B}b.3u=1q;h.f(`3t 3W:F=${b.F},k.J=${b.k.J},u.J=${b.u.J}`);c 64=n.A("1W-7V");d(b.k.J<=0){h.f("2x 1 J <= 0, 7T 17 1g (65)");b.I=1q;b.1e="I";I.T="7S a1!";3q.T="1o 7R";f(`${b.u.l}a0 ${b.k.l}!`);64.T="9Z 9Y";n.A("17-1g-26").C.1F="2D";1W{b.14.65.22()}2w(7U){h.P("1C 9X 9W 9V:",7U)}}G d(b.u.J<=0){h.f("2x 2 J <= 0, 7T 17 1g (5Y)");b.I=1q;b.1e="I";I.T="7S 9U!";3q.T="1o 7R";64.T=b.F===3w.1c?"9T 9S":"9R 9Q";n.A("17-1g-26").C.1F="2D";d(b.13===b.k){c Y=b.1z[b.1z.1c-1];d(Y&&!Y.21){Y.2C=(b.k.J/b.k.1h)*2S;Y.21=1q;c 4Y=Y.Z>0?(((Y.1N/Y.Z)/ 2S) * (Y.2C + 20)) * (1 + b.F /56):0;f(`9P 24 1L:1N=${Y.1N},Z=${Y.Z},2C=${Y.2C.4N(2)},2B=${b.F}`);f(`4Z 2A 9O:(((${Y.1N}/ ${Y.Z}) /2S)*(${Y.2C}+20))*(1+${b.F}/56)=${4Y}`);b.1y+=4Y;f(`4Z 9N!9M:${Y.1N},9L:${Y.Z},7Q 23:${Y.2C.4N(2)}%`);f(`4Z 2A:${4Y},7N 4X 2A:${b.1y}`)}}1j b.7L(b.F);d(b.F===3w.1c){b.14.7P.22();f(`7O 2B 21!7O 1L:${b.1y}`);b.1y=0;1j b.63();f("1o 21! 7N 9K 1L 62.")}G{b.F+=1;1j b.61();h.f(`60 5Z:F=${b.F}`);b.14.5Y.22()}c 7M=`${b.2R}3S-5X/${b.u.l.5W().5V(/ /g,\'-\')}.3v`;1K.35=7M;1K.1r.1x(\'5U\');1D.1r.1x(\'5T\');b.2Q()}b.3u=W;h.f(`3t 21:F=${b.F},I=${b.I}`)}2y 7L(7K){c 1X={2B:7K,1L:b.1y};h.f(`9J 1L:2B=${1X.2B},1L=${1X.1L}`);1W{c 19=1j 4W(\'3p/5S-2h-1L.3o\',{4V:\'5R\',4U:{\'4T-3V\':\'4S/34\'},3s:3R.7J(1X)});d(!19.4R){4Q 11 1C(`4P P!4O:${19.1p}`)}c V=1j 19.34();h.f(\'9I 19:\',V);f(`1M ${V.2B}2A:${V.1L.4N(2)}`);d(V.1p===\'3U\'){f(`2A 7I:1M ${V.2B},2A ${V.1L.4N(2)},9H:${V.9G}`)}G{f(`2A 9F 7I:${V.2k}`)}}2w(P){h.P(\'1C 5Q 12 9E:\',P);f(`1C 5Q 1L:${P.2k}`)}}4H(1E,3r,3T,4K){c 5P=1E.C.X||\'\';c 5O=5P.9D(\'4M\')?5P.Q(/4M\\([^)]+\\)/)[0]:\'\';1E.C.2P=`X ${4K/2/4L}s 9C`;1E.C.X=`9B(${3r}2m)${5O}`;1E.1r.1x(3T);1Z(()=>{1E.C.X=5O;1Z(()=>{1E.1r.2O(3T)},4K/ 2)}, 4K /2)}7H(1a,v,j){c 1E=1a===b.k?1D:1K;c 4J=1a===b.k?1:-1;c 4I=t.1d(10,2+v*0.4);c 3r=4J*4I;c 3T=`5L-${j}`;b.4H(1E,3r,3T,2M)}7G(1a){c 1E=1a===b.k?1D:1K;b.4H(1E,0,\'5L-5N-5M\',2M)}7F(M,33){c 1E=M===b.k?1D:1K;c 4J=M===b.k?-1:1;c 4I=t.1d(10,2+33*0.4);c 3r=4J*4I;b.4H(1E,3r,\'5L-5K\',2M)}}4D 7E(5J){B 5J[t.2N(t.7D()*5J.1c)]}4D f(2k){c 2z=n.A("3S-f");c 4G=n.2l("4G");4G.T=2k;2z.9A(4G,2z.9z);d(2z.9y.1c>50){2z.5I(2z.9x)}2z.9w=0}c 3q=n.A("7C-9v");c 7B=n.A("1Y-l");c 1D=n.A("1Y-4F");c 7A=n.A("1Y-J");c 7z=n.A("1Y-7p");c 7y=n.A("1Y-E");c 7x=n.A("1Y-D");c 7w=n.A("1Y-q");c 7v=n.A("1Y-o");c 7u=n.A("1Y-H");c 7t=n.A("1Y-j");c 7s=n.A("2j-l");c 1K=n.A("2j-4F");c 7r=n.A("2j-J");c 7q=n.A("2j-7p");c 7o=n.A("2j-E");c 7n=n.A("2j-D");c 7m=n.A("2j-q");c 7l=n.A("2j-o");c 7k=n.A("2j-H");c 7j=n.A("2j-j");c 2z=n.A("3S-f");c I=n.A("17-1g");4D 7d(){B 11 7i((3n,9u)=>{r V=[{l:"4E",E:4,D:4,q:4,o:"18",j:"1b",H:"2i"}];7f 2K=11 7g();2K.9t(\'7h\',\'3p/9s-2h-9r.3o\',1q);2K.9q();2K.9p=4D(){d(2K.9o==7g.9n){d(2K.1p==2M){7f 1X=2K.4C;d(1X!==\'W\'){1W{V=3R.5H(1X);d(!9m.9l(V)){V=[V]}}2w(e){h.P(\'7e 12 5H 3R:\',e)}}3n(V)}G{h.P(\'9k 9j 2L 1p:\',2K.1p);3n(V)}}}})}(2y()=>{1W{c 2J=1j 7d();h.f("4B: 2x 9i 9h:",2J);c 17=11 7c(2J);h.f("4B: 1o 9g 5G");1j 17.3Q();h.f("4B: 1o 9f 9e")}2w(P){h.P("4B: 1C 5F 17:",P)}})();',62,757,'|||||||||||this|const|if||log||console||type|player1|name|board|document|size||tactics|let||Math|player2|damage|tile|||selectedTile|getElementById|return|style|speed|strength|currentLevel|else|powerup|gameOver|health|for|element|defender|null|startY|error|match|tileSizeWithGap|startX|textContent|player|result|false|transform|currentRound|matches||new|to|currentTurn|sounds|character|progress|game|Medium|response|attacker|Base|length|min|gameState|config|over|maxHealth|endY|await|width|originalValue|effectValue|Leader|Game|status|true|classList|height|endX|dragDirection|boardRect|targetTile|add|grandTotalScore|roundStats|https|modal|Error|p1Image|imageElement|display|tiles|attack|translate|addEventListener|p2Image|score|Level|points|move|matchBonus|break|max|coordinates|isDragging|typeFolder|case|try|data|p1|setTimeout||completed|play|Left|round||container|Regen|click|loadedScore|loadedLevel|ogg|staking|io|skulliance|www|Audio|monstrocity|Regenerate|p2|message|createElement|px|playerTurn|Minor|Heal|row|tileElements||dy|dx|newInstance|catch|Player|async|battleLog|Score|level|healthPercentage|block|reducedBy|forEach|push|tileElement|appendChild|playerCharactersConfig|xhttp|with|200|floor|remove|transition|renderBoard|baseImagePath|100|x2|y2|x1|y1|aiTurn|isInitialMove|matchCoordinates|allMatchedTiles|selectedTileElement|showCharacterSelect|totalDamage|json|src|skipping|bonusMessage|uses|Boost|resolveMatches|currentGroup|totalTilesMatched|originalTiles|offsetY|offsetX|selected|imageUrl|theme|Large|startFreshButton|resumeButton|Small|resolve|php|ajax|turnIndicator|shiftX|body|checkGameOver|isCheckingGameOver|png|opponentsConfig|checkMatches|color|updateHealth|bonus|moved|from|attackMessage|and|mitigatedAmount|coord|straightMatches|matched|abs|constrainedY|constrainedX|none|img|Right|option|init|JSON|battle|glowClass|success|Type|started|endTurn|Opponent|percentage|reduced|HP|reductionFactor|boostActive|Attack|05|emptySpaces|handleMatch|but|processedMatches|Set|found|comboMessage|comboBonus|ease|selectedElement|tempCol|direction|top|clientY|left|clientX|getBoundingClientRect|touches|initGame|boardElement|className|div|playerCharacters|showProgressPopup|modalContent|modalButtons|select|tacticsAdjust|healthModifier|baseHealth|hasProgress|Main|responseText|function|Craig|image|li|applyAnimation|shiftDistance|shiftDirection|duration|1000|scaleX|toFixed|Status|HTTP|throw|ok|application|Content|headers|method|fetch|Total|roundScore|Round||slideTiles|Turn|due|originally|boostValue||hasMatches|fallClass|in|first|applied|lastStandActive|otherMatch|has|continue|index|matchLength|currentType|No|map|minY|minX|tempRow|scale|column|preventDefault|handleGameOverButton|addEventListeners|innerHTML|updatePlayerDisplay|createCharacter|logo|onStartFresh|onResume|removeEventListener|id|isInitial|themeSelect|base|switch|initializing|created|parse|removeChild|arr|recoil|glow|up|power|scalePart|originalTransform|saving|POST|save|winner|loser|replace|toLowerCase|damaged|win|saved|Progress|saveProgress|reset|clearProgress|tryAgainButton|loss|temp2|temp1|canMakeMatch|animating|healthBar|restoring|count|createRandomTile|callback|300|cascade|emptyBelow|cascadeTiles|Animating|after|on|attackType|the|Stand|Last|stand|last|originalDamage|second|special|Match|of|totalTiles|groupedMatches|processing|multiMatch|getTileFromEvent|touchY|touchX|mouseY|mouseX|button|updateTileSizeWithGap|updateOpponentDisplay|characterDirections|Starting|querySelector|Resume|optionsDiv|default|Damaged|Battle|getCharacterImageUrl|adjustedHealth|setBackground|Texby|Spydrax|Mind|Slime|Ouchie|Merdock||Mandiblus|Koipon|Katastrophy|Jarhead||Ganger|Goblin|Drake|Dankle|Ted|Billandar|MonstrocityMatch3|getAssets|Failed|var|XMLHttpRequest|GET|Promise|p2Type|p2Powerup|p2Size|p2Tactics|p2Speed|p2Strength|hp|p2Hp|p2Health|p2Name|p1Type|p1Powerup|p1Size|p1Tactics|p1Speed|p1Strength|p1Hp|p1Health|p1Name|turn|random|randomChoice|animateRecoil|animatePowerup|animateAttack|Saved|stringify|completedLevel|saveScoreToDatabase|damagedUrl|Grand|Final|finalWin|Health|Over|You|triggering|err|again|findAIMove|hpText|next|Power|usePowerup|countEmptyBelow|cascadeTilesWithoutRender|0px|dealt||not|Cascade|Tactics|tacticsChance|hyperCube|powerGem|returning|overlaps|originalPoints|Number|split|cascading|clearing|animation|clear|exiting|Multi|sum|selectedY|selectedX|badMove|maxY|maxX|handleTouchEnd|handleTouchMove|handleTouchStart|handleMouseUp|handleMouseMove|handleMouseDown|clicked|changeCharacterButton|change|isTouchDevice|dataset|alt|tileTypes|initBoard|onload|sharpens|drops|dulls|boosts|gameBoard|gameContainer|Restart|chose|User|fresh|oldMaxHealth|oldHealth|newCharacter|swapPlayerCharacter|strong|value|updateTheme|leader|adjustedTactics|boardWidth|Parsed|Response|load|loadProgress|text|gameTheme|localStorage|newTheme|initialization|userChoice|progressData|navigator|successfully|initialized|instance|loaded|characters|failed|Request|isArray|Array|DONE|readyState|onreadystatechange|send|assets|get|open|reject|indicator|scrollTop|lastChild|children|firstChild|insertBefore|translateX|linear|includes|database|Not|attempts|Completions|Save|Saving|total|Matches|Points|Won|Formula|Calculating|LEVEL|NEXT|OVER|START|Win|sound|lose|playing|AGAIN|TRY|defeats|Lose|skipped|passes|swaps|switched|backgroundColor|F44336|FFA500|FFC105|4CAF50|Surge|querySelectorAll|resolution|falling|Calling|mitigate|preparing|dealing|resulting|mitigates|Strike|Shadow|Bite|Slash|mitigated|only|taking|blow||halve|fades|multiplier|Special|before|some|rows|col|Vertical|cols|Horizontal|ending|complete|multi|increased|Cascading|Clearing|animate|no|Tile|warn|further|stopping|during|detected|Damage|Processing|Mega|reduce|Found|initial|Is|reverting|requestAnimationFrame|Reset|Monster|Switch|restart|mouseup|mousemove|mousedown|touchend|touchmove|touchstart|while|do|AI|inline|goes|full|starts|opponent|Loaded|visible|visibility|Started|flex|start|resume|buttons|content|popup|Displaying|fray|into|steps||set|Character|Up|Size|Speed|Strength|onchange|options|Called|offsetWidth|cleared|loading|or|Fetching|Raw|request|Sending|setItem|url|backgroundImage|Async|starting|Resumed|Prompting|speedmatch1|hypercube_create|powergem_created|badgeawarded|voice_levelcomplete|skullcoinlose|voice_go|voice_gameover|badmove|getItem|msMaxTouchPoints|maxTouchPoints|window|ontouchstart|constructor|class'.split('|'),0,{}));
  </script>
</body>
</html>
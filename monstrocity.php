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
  <script>
    const _0x2a1a53=_0x507e;(function(_0x6e6ff0,_0xc6f838){const _0x2ede0a=_0x507e,_0x501468=_0x6e6ff0();while(!![]){try{const _0x276079=parseInt(_0x2ede0a(0x244))/0x1*(parseInt(_0x2ede0a(0x132))/0x2)+-parseInt(_0x2ede0a(0x151))/0x3*(-parseInt(_0x2ede0a(0x201))/0x4)+-parseInt(_0x2ede0a(0x218))/0x5+parseInt(_0x2ede0a(0xe1))/0x6+-parseInt(_0x2ede0a(0xae))/0x7+-parseInt(_0x2ede0a(0x1d9))/0x8*(-parseInt(_0x2ede0a(0x1d3))/0x9)+parseInt(_0x2ede0a(0x1a5))/0xa*(-parseInt(_0x2ede0a(0x1d1))/0xb);if(_0x276079===_0xc6f838)break;else _0x501468['push'](_0x501468['shift']());}catch(_0x45ec34){_0x501468['push'](_0x501468['shift']());}}}(_0x404e,0xa09c7));const opponentsConfig=[{'name':'Craig','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x144),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x2a1a53(0x1bc),'type':'Base','powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x1f6),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x2a1a53(0x10a),'type':'Base','powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x13e),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x2a1a53(0x1ea),'type':'Base','powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x1dd),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x23a)},{'name':_0x2a1a53(0x21f),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x23a)},{'name':_0x2a1a53(0x12b),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x2a1a53(0x10a),'type':_0x2a1a53(0x21e),'powerup':'Regenerate'},{'name':_0x2a1a53(0x141),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x23a)},{'name':'Dankle','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x21e),'powerup':'Boost\x20Attack'},{'name':_0x2a1a53(0xb1),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x199)},{'name':_0x2a1a53(0xa7),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x12e)},{'name':_0x2a1a53(0xa6),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x2a1a53(0x1bc),'type':_0x2a1a53(0x21e),'powerup':'Heal'},{'name':_0x2a1a53(0x24a),'strength':0x7,'speed':0x7,'tactics':0x7,'size':'Medium','type':'Base','powerup':_0x2a1a53(0x12e)},{'name':_0x2a1a53(0x1c6),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x21e),'powerup':_0x2a1a53(0x12e)},{'name':_0x2a1a53(0x195),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x144),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Large','type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x1f6),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x2a1a53(0x10a),'type':_0x2a1a53(0x213),'powerup':'Minor\x20Regen'},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x1c7)},{'name':_0x2a1a53(0x1dd),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x23a)},{'name':_0x2a1a53(0x21f),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x23a)},{'name':_0x2a1a53(0x12b),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x2a1a53(0x10a),'type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x23a)},{'name':_0x2a1a53(0x141),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x23a)},{'name':_0x2a1a53(0x229),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x199)},{'name':_0x2a1a53(0xb1),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x199)},{'name':_0x2a1a53(0xa7),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x2a1a53(0x10a),'type':'Leader','powerup':_0x2a1a53(0x12e)},{'name':_0x2a1a53(0xa6),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x2a1a53(0x1bc),'type':'Leader','powerup':_0x2a1a53(0x12e)},{'name':_0x2a1a53(0x24a),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x2a1a53(0x1ea),'type':_0x2a1a53(0x213),'powerup':_0x2a1a53(0x12e)},{'name':_0x2a1a53(0x1c6),'strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':'Leader','powerup':_0x2a1a53(0x12e)}],characterDirections={'Billandar\x20and\x20Ted':'Left','Craig':'Left','Dankle':_0x2a1a53(0x253),'Drake':'Right','Goblin\x20Ganger':_0x2a1a53(0x253),'Jarhead':_0x2a1a53(0x176),'Katastrophy':_0x2a1a53(0x176),'Koipon':_0x2a1a53(0x253),'Mandiblus':_0x2a1a53(0x253),'Merdock':'Left','Ouchie':_0x2a1a53(0x253),'Slime\x20Mind':_0x2a1a53(0x176),'Spydrax':_0x2a1a53(0x176),'Texby':_0x2a1a53(0x253)};class MonstrocityMatch3{constructor(_0x287fa2){const _0x296eca=_0x2a1a53;this['isTouchDevice']=_0x296eca(0x215)in window||navigator['maxTouchPoints']>0x0||navigator[_0x296eca(0x131)]>0x0,this['width']=0x5,this[_0x296eca(0x16f)]=0x5,this[_0x296eca(0xba)]=[],this['selectedTile']=null,this[_0x296eca(0x15a)]=![],this[_0x296eca(0x18d)]=null,this[_0x296eca(0x1d6)]=null,this[_0x296eca(0x258)]=null,this[_0x296eca(0x17a)]=_0x296eca(0x1b7),this[_0x296eca(0x16e)]=![],this[_0x296eca(0xd4)]=null,this[_0x296eca(0x20a)]=null,this['offsetX']=0x0,this['offsetY']=0x0,this['currentLevel']=0x1,this['playerCharactersConfig']=_0x287fa2,this['playerCharacters']=[],this[_0x296eca(0x10e)]=![],this[_0x296eca(0xff)]=['first-attack',_0x296eca(0x15e),_0x296eca(0xc2),_0x296eca(0x205),_0x296eca(0x185)],this[_0x296eca(0x1b5)]=[],this['grandTotalScore']=0x0,this[_0x296eca(0xde)]=localStorage['getItem']('gameTheme')||_0x296eca(0xc3),this[_0x296eca(0x1c0)]=_0x296eca(0xbe)+this[_0x296eca(0xde)]+'/',this[_0x296eca(0x197)](),this['sounds']={'match':new Audio(_0x296eca(0xc0)),'cascade':new Audio(_0x296eca(0xc0)),'badMove':new Audio(_0x296eca(0x1aa)),'gameOver':new Audio(_0x296eca(0x17f)),'reset':new Audio(_0x296eca(0x17d)),'loss':new Audio(_0x296eca(0xb8)),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio(_0x296eca(0x248)),'powerGem':new Audio(_0x296eca(0xa0)),'hyperCube':new Audio(_0x296eca(0x236)),'multiMatch':new Audio('https://www.skulliance.io/staking/sounds/speedmatch1.ogg')},this[_0x296eca(0x1ce)](),this[_0x296eca(0xf9)]();}async['init'](){const _0x57d997=_0x2a1a53;console[_0x57d997(0x22d)](_0x57d997(0x110)),this['playerCharacters']=this[_0x57d997(0x1ec)]['map'](_0x2e79da=>this[_0x57d997(0x111)](_0x2e79da)),await this[_0x57d997(0x24b)](!![]);const _0x4db3e7=await this['loadProgress'](),{loadedLevel:_0x107a0b,loadedScore:_0x4599f9,hasProgress:_0x2a8551}=_0x4db3e7;if(_0x2a8551){console[_0x57d997(0x22d)](_0x57d997(0x1eb)+_0x107a0b+_0x57d997(0x1c3)+_0x4599f9);const _0x802eca=await this[_0x57d997(0x13a)](_0x107a0b,_0x4599f9);_0x802eca?(this[_0x57d997(0x18b)]=_0x107a0b,this[_0x57d997(0x219)]=_0x4599f9,log('Resumed\x20at\x20Level\x20'+this[_0x57d997(0x18b)]+',\x20Score\x20'+this[_0x57d997(0x219)])):(this['currentLevel']=0x1,this[_0x57d997(0x219)]=0x0,await this[_0x57d997(0x106)](),log(_0x57d997(0x1cd)));}else this[_0x57d997(0x18b)]=0x1,this[_0x57d997(0x219)]=0x0,log(_0x57d997(0xf0));console[_0x57d997(0x22d)](_0x57d997(0x109));}[_0x2a1a53(0x197)](){const _0x38810f=_0x2a1a53;document[_0x38810f(0x181)]['style']['backgroundImage']=_0x38810f(0xf8)+this[_0x38810f(0x1c0)]+_0x38810f(0x16c);}[_0x2a1a53(0x1e3)](_0x15c85a){const _0x4bda74=_0x2a1a53;this[_0x4bda74(0xde)]=_0x15c85a,this[_0x4bda74(0x1c0)]=_0x4bda74(0xbe)+this[_0x4bda74(0xde)]+'/',localStorage[_0x4bda74(0xa1)](_0x4bda74(0xa4),this['theme']),this['setBackground'](),this[_0x4bda74(0xc8)]=this[_0x4bda74(0x1ec)][_0x4bda74(0x9b)](_0x40c985=>this['createCharacter'](_0x40c985));this[_0x4bda74(0x1d6)]&&(this[_0x4bda74(0x1d6)][_0x4bda74(0x1a6)]=this['getCharacterImageUrl'](this[_0x4bda74(0x1d6)]),this[_0x4bda74(0x11c)]());this[_0x4bda74(0x258)]&&(this[_0x4bda74(0x258)][_0x4bda74(0x1a6)]=this['getCharacterImageUrl'](this[_0x4bda74(0x258)]),this[_0x4bda74(0x18a)]());document[_0x4bda74(0xcd)](_0x4bda74(0xdf))[_0x4bda74(0x1ba)]=this['baseImagePath']+_0x4bda74(0x1d4);const _0x3920cd=document[_0x4bda74(0x87)]('character-select-container');_0x3920cd['style'][_0x4bda74(0x190)]==='block'&&this[_0x4bda74(0x24b)](this[_0x4bda74(0x1d6)]===null);}async[_0x2a1a53(0x9a)](){const _0x5c6bf6=_0x2a1a53,_0x3941a2={'currentLevel':this[_0x5c6bf6(0x18b)],'grandTotalScore':this[_0x5c6bf6(0x219)]};console[_0x5c6bf6(0x22d)](_0x5c6bf6(0x1a9),_0x3941a2);try{const _0x5933e6=await fetch('ajax/save-monstrocity-progress.php',{'method':_0x5c6bf6(0x247),'headers':{'Content-Type':_0x5c6bf6(0x19c)},'body':JSON[_0x5c6bf6(0x16b)](_0x3941a2)});console[_0x5c6bf6(0x22d)](_0x5c6bf6(0x18e),_0x5933e6['status']);const _0xd29eeb=await _0x5933e6[_0x5c6bf6(0x1ee)]();console[_0x5c6bf6(0x22d)](_0x5c6bf6(0x1d5),_0xd29eeb);if(!_0x5933e6['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x5933e6[_0x5c6bf6(0xea)]);const _0x3b7e9e=JSON[_0x5c6bf6(0x102)](_0xd29eeb);console['log'](_0x5c6bf6(0x20d),_0x3b7e9e),_0x3b7e9e[_0x5c6bf6(0xea)]==='success'?log(_0x5c6bf6(0x147)+this[_0x5c6bf6(0x18b)]):console[_0x5c6bf6(0x11d)](_0x5c6bf6(0x1dc),_0x3b7e9e[_0x5c6bf6(0x1f7)]);}catch(_0x34a4ae){console[_0x5c6bf6(0x11d)](_0x5c6bf6(0x256),_0x34a4ae);}}async[_0x2a1a53(0x12f)](){const _0x43a3e4=_0x2a1a53;try{console[_0x43a3e4(0x22d)](_0x43a3e4(0x1a1));const _0x52cbb7=await fetch(_0x43a3e4(0x1de),{'method':_0x43a3e4(0x16d),'headers':{'Content-Type':_0x43a3e4(0x19c)}});console[_0x43a3e4(0x22d)]('Response\x20status:',_0x52cbb7[_0x43a3e4(0xea)]);if(!_0x52cbb7['ok'])throw new Error(_0x43a3e4(0x113)+_0x52cbb7['status']);const _0x523d00=await _0x52cbb7[_0x43a3e4(0x233)]();console[_0x43a3e4(0x22d)](_0x43a3e4(0x20d),_0x523d00);if(_0x523d00[_0x43a3e4(0xea)]===_0x43a3e4(0xd6)&&_0x523d00['progress']){const _0x5a0d3e=_0x523d00['progress'];return{'loadedLevel':_0x5a0d3e[_0x43a3e4(0x18b)]||0x1,'loadedScore':_0x5a0d3e['grandTotalScore']||0x0,'hasProgress':!![]};}else return console[_0x43a3e4(0x22d)](_0x43a3e4(0x14b),_0x523d00),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x39e4b1){return console[_0x43a3e4(0x11d)](_0x43a3e4(0x23c),_0x39e4b1),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x2a1a53(0x106)](){const _0x3c0bf1=_0x2a1a53;try{const _0x47e963=await fetch(_0x3c0bf1(0xcb),{'method':_0x3c0bf1(0x247),'headers':{'Content-Type':_0x3c0bf1(0x19c)}});if(!_0x47e963['ok'])throw new Error(_0x3c0bf1(0x113)+_0x47e963[_0x3c0bf1(0xea)]);const _0x570be0=await _0x47e963[_0x3c0bf1(0x233)]();_0x570be0[_0x3c0bf1(0xea)]===_0x3c0bf1(0xd6)&&(this[_0x3c0bf1(0x18b)]=0x1,this[_0x3c0bf1(0x219)]=0x0,log('Progress\x20cleared'));}catch(_0x30650c){console['error']('Error\x20clearing\x20progress:',_0x30650c);}}[_0x2a1a53(0x1ce)](){const _0x2723a5=_0x2a1a53,_0x1a1a7e=document[_0x2723a5(0x87)]('game-board'),_0x229b8b=_0x1a1a7e[_0x2723a5(0x9c)]||0x12c;this['tileSizeWithGap']=(_0x229b8b-0.5*(this[_0x2723a5(0x129)]-0x1))/this[_0x2723a5(0x129)];}[_0x2a1a53(0x111)](_0x45ba47){const _0x5130da=_0x2a1a53;let _0x2d479c;switch(_0x45ba47['type']){case _0x5130da(0x21e):_0x2d479c=_0x5130da(0x8f);break;case'Leader':_0x2d479c='leader';break;case _0x5130da(0x118):_0x2d479c=_0x5130da(0x246);break;default:_0x2d479c=_0x5130da(0x8f);}const _0x378694=''+this[_0x5130da(0x1c0)]+_0x2d479c+'/'+_0x45ba47['name']['toLowerCase']()[_0x5130da(0xfc)](/ /g,'-')+_0x5130da(0x13f);let _0x4b7e6b;switch(_0x45ba47[_0x5130da(0x24f)]){case _0x5130da(0x213):_0x4b7e6b=0x64;break;case'Battle\x20Damaged':_0x4b7e6b=0x46;break;case _0x5130da(0x21e):default:_0x4b7e6b=0x55;}let _0x4a3959=0x1,_0x2ef2ea=0x0;switch(_0x45ba47[_0x5130da(0x1e8)]){case _0x5130da(0x1bc):_0x4a3959=1.2,_0x2ef2ea=_0x45ba47[_0x5130da(0x1d0)]>0x1?-0x2:0x0;break;case _0x5130da(0x10a):_0x4a3959=0.8,_0x2ef2ea=_0x45ba47[_0x5130da(0x1d0)]<0x6?0x2:0x7-_0x45ba47[_0x5130da(0x1d0)];break;case _0x5130da(0x1ea):_0x4a3959=0x1,_0x2ef2ea=0x0;break;}const _0x528dd9=Math['round'](_0x4b7e6b*_0x4a3959),_0x5cdfad=Math[_0x5130da(0x96)](0x1,Math[_0x5130da(0x178)](0x7,_0x45ba47[_0x5130da(0x1d0)]+_0x2ef2ea));return{'name':_0x45ba47[_0x5130da(0x171)],'type':_0x45ba47['type'],'strength':_0x45ba47[_0x5130da(0x1b4)],'speed':_0x45ba47[_0x5130da(0xd8)],'tactics':_0x5cdfad,'size':_0x45ba47['size'],'powerup':_0x45ba47[_0x5130da(0x1ff)],'health':_0x528dd9,'maxHealth':_0x528dd9,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x378694};}[_0x2a1a53(0x1b3)](_0x4d91f8){const _0x395302=_0x2a1a53;let _0x504e7d;switch(_0x4d91f8[_0x395302(0x24f)]){case _0x395302(0x21e):_0x504e7d='base';break;case _0x395302(0x213):_0x504e7d=_0x395302(0x99);break;case _0x395302(0x118):_0x504e7d=_0x395302(0x246);break;default:_0x504e7d=_0x395302(0x8f);}return''+this['baseImagePath']+_0x504e7d+'/'+_0x4d91f8[_0x395302(0x171)][_0x395302(0x19b)]()[_0x395302(0xfc)](/ /g,'-')+'.png';}async['showCharacterSelect'](_0x1bd646=![]){const _0x59726f=_0x2a1a53;console['log'](_0x59726f(0xab)+_0x1bd646);const _0x3684fb=document['getElementById'](_0x59726f(0x18f)),_0x4f8477=document[_0x59726f(0x87)](_0x59726f(0x13d)),_0x2d2880=document[_0x59726f(0x87)](_0x59726f(0x108));_0x4f8477[_0x59726f(0x1e4)]='',_0x3684fb['style'][_0x59726f(0x190)]='block',_0x2d2880['value']=this[_0x59726f(0xde)],_0x2d2880[_0x59726f(0x153)]=()=>{const _0x582526=_0x59726f;this[_0x582526(0x1e3)](_0x2d2880[_0x582526(0xad)]);},this['playerCharacters']['forEach']((_0x3912d0,_0x58cab1)=>{const _0x214bd5=_0x59726f,_0xe41a0=document[_0x214bd5(0x214)](_0x214bd5(0x122));_0xe41a0[_0x214bd5(0x196)]=_0x214bd5(0x173),_0xe41a0[_0x214bd5(0x1e4)]='\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22'+_0x3912d0[_0x214bd5(0x1a6)]+_0x214bd5(0x18c)+_0x3912d0[_0x214bd5(0x171)]+_0x214bd5(0xe9)+_0x3912d0[_0x214bd5(0x171)]+_0x214bd5(0x15d)+_0x3912d0[_0x214bd5(0x24f)]+'</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Health:\x20'+_0x3912d0[_0x214bd5(0x145)]+'</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Strength:\x20'+_0x3912d0[_0x214bd5(0x1b4)]+_0x214bd5(0x1af)+_0x3912d0['speed']+_0x214bd5(0xd5)+_0x3912d0['tactics']+'</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Size:\x20'+_0x3912d0[_0x214bd5(0x1e8)]+'</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Power-Up:\x20'+_0x3912d0[_0x214bd5(0x1ff)]+_0x214bd5(0x81),_0xe41a0['addEventListener']('click',()=>{const _0x4d9389=_0x214bd5;console['log'](_0x4d9389(0x1cc)+_0x3912d0[_0x4d9389(0x171)]),_0x3684fb[_0x4d9389(0x237)][_0x4d9389(0x190)]='none',_0x1bd646?(this['player1']={..._0x3912d0},console[_0x4d9389(0x22d)](_0x4d9389(0x107)+this[_0x4d9389(0x1d6)]['name']),this['initGame']()):this[_0x4d9389(0x164)](_0x3912d0);}),_0x4f8477[_0x214bd5(0x101)](_0xe41a0);});}[_0x2a1a53(0x164)](_0x57b706){const _0x5abbb2=_0x2a1a53,_0x42564f=this[_0x5abbb2(0x1d6)][_0x5abbb2(0x252)],_0x529c3d=this[_0x5abbb2(0x1d6)]['maxHealth'],_0x51640b={..._0x57b706},_0x5258fd=Math[_0x5abbb2(0x178)](0x1,_0x42564f/_0x529c3d);_0x51640b['health']=Math[_0x5abbb2(0xdd)](_0x51640b[_0x5abbb2(0x145)]*_0x5258fd),_0x51640b[_0x5abbb2(0x252)]=Math[_0x5abbb2(0x96)](0x0,Math[_0x5abbb2(0x178)](_0x51640b[_0x5abbb2(0x145)],_0x51640b[_0x5abbb2(0x252)])),_0x51640b[_0x5abbb2(0xe2)]=![],_0x51640b[_0x5abbb2(0x22e)]=0x0,_0x51640b[_0x5abbb2(0x1a8)]=![],this['player1']=_0x51640b,this[_0x5abbb2(0x11c)](),this['updateHealth'](this[_0x5abbb2(0x1d6)]),log(this[_0x5abbb2(0x1d6)]['name']+_0x5abbb2(0xf7)+this[_0x5abbb2(0x1d6)][_0x5abbb2(0x252)]+'/'+this[_0x5abbb2(0x1d6)]['maxHealth']+_0x5abbb2(0x1fa)),this[_0x5abbb2(0x18d)]=this[_0x5abbb2(0x1d6)][_0x5abbb2(0xd8)]>this['player2'][_0x5abbb2(0xd8)]?this['player1']:this[_0x5abbb2(0x258)][_0x5abbb2(0xd8)]>this[_0x5abbb2(0x1d6)][_0x5abbb2(0xd8)]?this[_0x5abbb2(0x258)]:this[_0x5abbb2(0x1d6)]['strength']>=this['player2'][_0x5abbb2(0x1b4)]?this['player1']:this[_0x5abbb2(0x258)],turnIndicator[_0x5abbb2(0xbc)]=_0x5abbb2(0xeb)+this[_0x5abbb2(0x18b)]+'\x20-\x20'+(this['currentTurn']===this[_0x5abbb2(0x1d6)]?_0x5abbb2(0x1ab):_0x5abbb2(0x1f5))+_0x5abbb2(0x161),this[_0x5abbb2(0x18d)]===this[_0x5abbb2(0x258)]&&this[_0x5abbb2(0x17a)]!=='gameOver'&&setTimeout(()=>this[_0x5abbb2(0xf5)](),0x3e8);}[_0x2a1a53(0x13a)](_0x416ec2,_0x2b0055){const _0x572b2b=_0x2a1a53;return console[_0x572b2b(0x22d)](_0x572b2b(0x242)+_0x416ec2+_0x572b2b(0x19e)+_0x2b0055),new Promise(_0x56a985=>{const _0x1a7bb5=_0x572b2b,_0x58a5cb=document[_0x1a7bb5(0x214)](_0x1a7bb5(0x122));_0x58a5cb['id']=_0x1a7bb5(0x22b),_0x58a5cb[_0x1a7bb5(0x196)]=_0x1a7bb5(0x22b);const _0x15b51f=document[_0x1a7bb5(0x214)](_0x1a7bb5(0x122));_0x15b51f['className']=_0x1a7bb5(0xd3);const _0x1947b7=document[_0x1a7bb5(0x214)]('p');_0x1947b7['id']=_0x1a7bb5(0x136),_0x1947b7[_0x1a7bb5(0xbc)]=_0x1a7bb5(0xbb)+_0x416ec2+_0x1a7bb5(0x217)+_0x2b0055+'?',_0x15b51f[_0x1a7bb5(0x101)](_0x1947b7);const _0x55daba=document[_0x1a7bb5(0x214)](_0x1a7bb5(0x122));_0x55daba[_0x1a7bb5(0x196)]=_0x1a7bb5(0x22f);const _0x27f7a0=document[_0x1a7bb5(0x214)]('button');_0x27f7a0['id']='progress-resume',_0x27f7a0['textContent']=_0x1a7bb5(0x221),_0x55daba['appendChild'](_0x27f7a0);const _0x2b6071=document[_0x1a7bb5(0x214)](_0x1a7bb5(0x142));_0x2b6071['id']=_0x1a7bb5(0x93),_0x2b6071['textContent']=_0x1a7bb5(0xb2),_0x55daba[_0x1a7bb5(0x101)](_0x2b6071),_0x15b51f[_0x1a7bb5(0x101)](_0x55daba),_0x58a5cb[_0x1a7bb5(0x101)](_0x15b51f),document[_0x1a7bb5(0x181)][_0x1a7bb5(0x101)](_0x58a5cb),_0x58a5cb['style'][_0x1a7bb5(0x190)]=_0x1a7bb5(0xbd);const _0x36584d=()=>{const _0x1293ce=_0x1a7bb5;console[_0x1293ce(0x22d)](_0x1293ce(0x19a)),_0x58a5cb[_0x1293ce(0x237)][_0x1293ce(0x190)]=_0x1293ce(0xe7),document[_0x1293ce(0x181)][_0x1293ce(0x104)](_0x58a5cb),_0x27f7a0['removeEventListener'](_0x1293ce(0x1d2),_0x36584d),_0x2b6071[_0x1293ce(0x1cf)](_0x1293ce(0x1d2),_0x4d99e0),_0x56a985(!![]);},_0x4d99e0=()=>{const _0x1da23b=_0x1a7bb5;console[_0x1da23b(0x22d)](_0x1da23b(0x198)),_0x58a5cb[_0x1da23b(0x237)][_0x1da23b(0x190)]=_0x1da23b(0xe7),document[_0x1da23b(0x181)]['removeChild'](_0x58a5cb),_0x27f7a0[_0x1da23b(0x1cf)](_0x1da23b(0x1d2),_0x36584d),_0x2b6071['removeEventListener'](_0x1da23b(0x1d2),_0x4d99e0),_0x56a985(![]);};_0x27f7a0['addEventListener'](_0x1a7bb5(0x1d2),_0x36584d),_0x2b6071[_0x1a7bb5(0x1bf)](_0x1a7bb5(0x1d2),_0x4d99e0);});}[_0x2a1a53(0x21d)](){const _0x3ec66b=_0x2a1a53;console[_0x3ec66b(0x22d)](_0x3ec66b(0x17e)+this[_0x3ec66b(0x18b)]);const _0x307ea7=document[_0x3ec66b(0xcd)](_0x3ec66b(0x19d)),_0x432a39=document['getElementById'](_0x3ec66b(0x1a4));_0x307ea7[_0x3ec66b(0x237)][_0x3ec66b(0x190)]=_0x3ec66b(0xca),_0x432a39[_0x3ec66b(0x237)][_0x3ec66b(0x206)]='visible',document[_0x3ec66b(0xcd)]('.game-logo')[_0x3ec66b(0x1ba)]=this[_0x3ec66b(0x1c0)]+'logo.png',this[_0x3ec66b(0x200)]['reset'][_0x3ec66b(0xe0)](),log(_0x3ec66b(0x19f)+this[_0x3ec66b(0x18b)]+_0x3ec66b(0x1e5)),this[_0x3ec66b(0x258)]=this[_0x3ec66b(0x111)](opponentsConfig[this['currentLevel']-0x1]),console[_0x3ec66b(0x22d)](_0x3ec66b(0x1be)+this[_0x3ec66b(0x18b)]+':\x20'+this[_0x3ec66b(0x258)][_0x3ec66b(0x171)]+'\x20(opponentsConfig['+(this[_0x3ec66b(0x18b)]-0x1)+'])'),this['player1'][_0x3ec66b(0x252)]=this[_0x3ec66b(0x1d6)]['maxHealth'],this[_0x3ec66b(0x18d)]=this[_0x3ec66b(0x1d6)][_0x3ec66b(0xd8)]>this[_0x3ec66b(0x258)][_0x3ec66b(0xd8)]?this['player1']:this[_0x3ec66b(0x258)][_0x3ec66b(0xd8)]>this[_0x3ec66b(0x1d6)][_0x3ec66b(0xd8)]?this[_0x3ec66b(0x258)]:this['player1'][_0x3ec66b(0x1b4)]>=this[_0x3ec66b(0x258)][_0x3ec66b(0x1b4)]?this[_0x3ec66b(0x1d6)]:this[_0x3ec66b(0x258)],this[_0x3ec66b(0x17a)]=_0x3ec66b(0x1b7),this[_0x3ec66b(0x15a)]=![],this[_0x3ec66b(0x1b5)]=[],p1Image[_0x3ec66b(0x227)]['remove']('winner','loser'),p2Image[_0x3ec66b(0x227)][_0x3ec66b(0x7f)]('winner','loser'),this['updatePlayerDisplay'](),this[_0x3ec66b(0x18a)](),characterDirections[this[_0x3ec66b(0x1d6)][_0x3ec66b(0x171)]]===_0x3ec66b(0x253)?p1Image[_0x3ec66b(0x237)]['transform']=_0x3ec66b(0x85):p1Image[_0x3ec66b(0x237)][_0x3ec66b(0xe8)]=_0x3ec66b(0xe7),characterDirections[this['player2']['name']]===_0x3ec66b(0x176)?p2Image[_0x3ec66b(0x237)]['transform']='scaleX(-1)':p2Image[_0x3ec66b(0x237)]['transform']=_0x3ec66b(0xe7),this[_0x3ec66b(0x179)](this[_0x3ec66b(0x1d6)]),this[_0x3ec66b(0x179)](this[_0x3ec66b(0x258)]),battleLog['innerHTML']='',gameOver[_0x3ec66b(0xbc)]='',this[_0x3ec66b(0x1d6)][_0x3ec66b(0x1e8)]!==_0x3ec66b(0x1ea)&&log(this[_0x3ec66b(0x1d6)][_0x3ec66b(0x171)]+_0x3ec66b(0x1f1)+this[_0x3ec66b(0x1d6)][_0x3ec66b(0x1e8)]+_0x3ec66b(0x22a)+(this[_0x3ec66b(0x1d6)][_0x3ec66b(0x1e8)]===_0x3ec66b(0x1bc)?_0x3ec66b(0x238)+this['player1']['maxHealth']+_0x3ec66b(0x14a)+this[_0x3ec66b(0x1d6)][_0x3ec66b(0x1d0)]:_0x3ec66b(0x243)+this[_0x3ec66b(0x1d6)][_0x3ec66b(0x145)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x3ec66b(0x1d6)][_0x3ec66b(0x1d0)])+'!'),this[_0x3ec66b(0x258)][_0x3ec66b(0x1e8)]!==_0x3ec66b(0x1ea)&&log(this[_0x3ec66b(0x258)]['name']+_0x3ec66b(0x1f1)+this['player2'][_0x3ec66b(0x1e8)]+_0x3ec66b(0x22a)+(this[_0x3ec66b(0x258)]['size']==='Large'?_0x3ec66b(0x238)+this[_0x3ec66b(0x258)][_0x3ec66b(0x145)]+_0x3ec66b(0x14a)+this[_0x3ec66b(0x258)][_0x3ec66b(0x1d0)]:_0x3ec66b(0x243)+this[_0x3ec66b(0x258)][_0x3ec66b(0x145)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x3ec66b(0x258)][_0x3ec66b(0x1d0)])+'!'),log(this[_0x3ec66b(0x1d6)][_0x3ec66b(0x171)]+'\x20starts\x20at\x20full\x20strength\x20with\x20'+this['player1'][_0x3ec66b(0x252)]+'/'+this['player1'][_0x3ec66b(0x145)]+_0x3ec66b(0x1fa)),log(this[_0x3ec66b(0x18d)][_0x3ec66b(0x171)]+_0x3ec66b(0xac)),this['initBoard'](),this[_0x3ec66b(0x17a)]=this['currentTurn']===this[_0x3ec66b(0x1d6)]?_0x3ec66b(0x1cb):_0x3ec66b(0xf5),turnIndicator[_0x3ec66b(0xbc)]=_0x3ec66b(0xeb)+this[_0x3ec66b(0x18b)]+_0x3ec66b(0xef)+(this[_0x3ec66b(0x18d)]===this['player1']?_0x3ec66b(0x1ab):'Opponent')+'\x27s\x20Turn',this['playerCharacters'][_0x3ec66b(0x20f)]>0x1&&(document[_0x3ec66b(0x87)](_0x3ec66b(0x235))[_0x3ec66b(0x237)][_0x3ec66b(0x190)]=_0x3ec66b(0x105)),this[_0x3ec66b(0x18d)]===this['player2']&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x2a1a53(0x11c)](){const _0x1c2015=_0x2a1a53;p1Name['textContent']=this['theme']===_0x1c2015(0xc3)?this['player1']['name']:_0x1c2015(0x8c),p1Type[_0x1c2015(0xbc)]=this[_0x1c2015(0x1d6)][_0x1c2015(0x24f)],p1Strength[_0x1c2015(0xbc)]=this[_0x1c2015(0x1d6)][_0x1c2015(0x1b4)],p1Speed['textContent']=this['player1'][_0x1c2015(0xd8)],p1Tactics[_0x1c2015(0xbc)]=this[_0x1c2015(0x1d6)]['tactics'],p1Size[_0x1c2015(0xbc)]=this['player1'][_0x1c2015(0x1e8)],p1Powerup[_0x1c2015(0xbc)]=this[_0x1c2015(0x1d6)]['powerup'],p1Image[_0x1c2015(0x1ba)]=this[_0x1c2015(0x1d6)][_0x1c2015(0x1a6)],p1Image[_0x1c2015(0x88)]=()=>p1Image['style'][_0x1c2015(0x190)]=_0x1c2015(0xca);}['updateOpponentDisplay'](){const _0x586a5f=_0x2a1a53;p2Name[_0x586a5f(0xbc)]=this[_0x586a5f(0xde)]==='monstrocity'?this[_0x586a5f(0x258)][_0x586a5f(0x171)]:_0x586a5f(0xd1),p2Type['textContent']=this[_0x586a5f(0x258)]['type'],p2Strength[_0x586a5f(0xbc)]=this[_0x586a5f(0x258)][_0x586a5f(0x1b4)],p2Speed[_0x586a5f(0xbc)]=this['player2'][_0x586a5f(0xd8)],p2Tactics[_0x586a5f(0xbc)]=this['player2'][_0x586a5f(0x1d0)],p2Size[_0x586a5f(0xbc)]=this[_0x586a5f(0x258)][_0x586a5f(0x1e8)],p2Powerup[_0x586a5f(0xbc)]=this[_0x586a5f(0x258)]['powerup'],p2Image['src']=this[_0x586a5f(0x258)]['imageUrl'],p2Image[_0x586a5f(0x88)]=()=>p2Image[_0x586a5f(0x237)][_0x586a5f(0x190)]=_0x586a5f(0xca);}['initBoard'](){const _0x2bac2a=_0x2a1a53;this[_0x2bac2a(0xba)]=[];for(let _0x47bb30=0x0;_0x47bb30<this[_0x2bac2a(0x16f)];_0x47bb30++){this['board'][_0x47bb30]=[];for(let _0x55df30=0x0;_0x55df30<this['width'];_0x55df30++){let _0x42c75c;do{_0x42c75c=this[_0x2bac2a(0x1b2)]();}while(_0x55df30>=0x2&&this[_0x2bac2a(0xba)][_0x47bb30][_0x55df30-0x1]?.[_0x2bac2a(0x24f)]===_0x42c75c[_0x2bac2a(0x24f)]&&this[_0x2bac2a(0xba)][_0x47bb30][_0x55df30-0x2]?.[_0x2bac2a(0x24f)]===_0x42c75c['type']||_0x47bb30>=0x2&&this[_0x2bac2a(0xba)][_0x47bb30-0x1]?.[_0x55df30]?.['type']===_0x42c75c['type']&&this[_0x2bac2a(0xba)][_0x47bb30-0x2]?.[_0x55df30]?.[_0x2bac2a(0x24f)]===_0x42c75c['type']);this[_0x2bac2a(0xba)][_0x47bb30][_0x55df30]=_0x42c75c;}}this['renderBoard']();}[_0x2a1a53(0x1b2)](){const _0x31e836=_0x2a1a53;return{'type':randomChoice(this[_0x31e836(0xff)]),'element':null};}[_0x2a1a53(0xbf)](){const _0x55b931=_0x2a1a53;this[_0x55b931(0x1ce)]();const _0x311fd3=document[_0x55b931(0x87)]('game-board');_0x311fd3[_0x55b931(0x1e4)]='';for(let _0x56b7c7=0x0;_0x56b7c7<this[_0x55b931(0x16f)];_0x56b7c7++){for(let _0x11355d=0x0;_0x11355d<this[_0x55b931(0x129)];_0x11355d++){const _0x158e7d=this[_0x55b931(0xba)][_0x56b7c7][_0x11355d];if(_0x158e7d[_0x55b931(0x24f)]===null)continue;const _0x17b84c=document[_0x55b931(0x214)](_0x55b931(0x122));_0x17b84c['className']=_0x55b931(0x124)+_0x158e7d[_0x55b931(0x24f)];if(this[_0x55b931(0x15a)])_0x17b84c[_0x55b931(0x227)][_0x55b931(0x193)](_0x55b931(0x20b));const _0x3778fc=document[_0x55b931(0x214)]('img');_0x3778fc[_0x55b931(0x1ba)]=_0x55b931(0x1fd)+_0x158e7d[_0x55b931(0x24f)]+_0x55b931(0x13f),_0x3778fc[_0x55b931(0x1d7)]=_0x158e7d[_0x55b931(0x24f)],_0x17b84c['appendChild'](_0x3778fc),_0x17b84c['dataset']['x']=_0x11355d,_0x17b84c[_0x55b931(0x225)]['y']=_0x56b7c7,_0x311fd3[_0x55b931(0x101)](_0x17b84c),_0x158e7d[_0x55b931(0x82)]=_0x17b84c,(!this[_0x55b931(0x16e)]||this[_0x55b931(0x1fc)]&&(this[_0x55b931(0x1fc)]['x']!==_0x11355d||this['selectedTile']['y']!==_0x56b7c7))&&(_0x17b84c['style'][_0x55b931(0xe8)]='translate(0,\x200)');}}document[_0x55b931(0x87)]('game-over-container')[_0x55b931(0x237)][_0x55b931(0x190)]=this[_0x55b931(0x15a)]?_0x55b931(0xca):_0x55b931(0xe7);}[_0x2a1a53(0xf9)](){const _0x11fa83=_0x2a1a53,_0x1d5949=document['getElementById'](_0x11fa83(0x1a4));this['isTouchDevice']?(_0x1d5949['addEventListener'](_0x11fa83(0x1e9),_0x57ecce=>this['handleTouchStart'](_0x57ecce)),_0x1d5949['addEventListener'](_0x11fa83(0xc1),_0x98ef4c=>this[_0x11fa83(0x239)](_0x98ef4c)),_0x1d5949['addEventListener'](_0x11fa83(0x1c4),_0x32cdbf=>this['handleTouchEnd'](_0x32cdbf))):(_0x1d5949[_0x11fa83(0x1bf)](_0x11fa83(0x10c),_0x343c62=>this['handleMouseDown'](_0x343c62)),_0x1d5949[_0x11fa83(0x1bf)]('mousemove',_0x4d3434=>this[_0x11fa83(0xdb)](_0x4d3434)),_0x1d5949['addEventListener'](_0x11fa83(0x250),_0x4b11a5=>this[_0x11fa83(0x83)](_0x4b11a5)));document[_0x11fa83(0x87)](_0x11fa83(0x119))[_0x11fa83(0x1bf)](_0x11fa83(0x1d2),()=>this['handleGameOverButton']()),document['getElementById']('restart')[_0x11fa83(0x1bf)](_0x11fa83(0x1d2),()=>{const _0x3e7aac=_0x11fa83;this[_0x3e7aac(0x21d)]();});const _0x1cb72e=document['getElementById']('change-character'),_0x3a0e7f=document['getElementById'](_0x11fa83(0x1e0));_0x1cb72e[_0x11fa83(0x1bf)](_0x11fa83(0x1d2),()=>{const _0x30ce89=_0x11fa83;console[_0x30ce89(0x22d)](_0x30ce89(0x1ac)),this[_0x30ce89(0x24b)](![]);}),_0x3a0e7f[_0x11fa83(0x1bf)](_0x11fa83(0x1d2),()=>{const _0x504aec=_0x11fa83;console[_0x504aec(0x22d)]('addEventListeners:\x20Player\x201\x20image\x20clicked'),this['showCharacterSelect'](![]);});}[_0x2a1a53(0x192)](){const _0x47a83d=_0x2a1a53;console[_0x47a83d(0x22d)](_0x47a83d(0x146)+this[_0x47a83d(0x18b)]+_0x47a83d(0x194)+this[_0x47a83d(0x258)][_0x47a83d(0x252)]),this[_0x47a83d(0x258)]['health']<=0x0&&this[_0x47a83d(0x18b)]>opponentsConfig[_0x47a83d(0x20f)]&&(this[_0x47a83d(0x18b)]=0x1,console[_0x47a83d(0x22d)]('Reset\x20to\x20Level\x201:\x20currentLevel='+this[_0x47a83d(0x18b)])),this[_0x47a83d(0x21d)](),console[_0x47a83d(0x22d)](_0x47a83d(0x186)+this[_0x47a83d(0x18b)]);}[_0x2a1a53(0x255)](_0x3fc2d2){const _0x4b41b8=_0x2a1a53;if(this[_0x4b41b8(0x15a)]||this[_0x4b41b8(0x17a)]!==_0x4b41b8(0x1cb)||this[_0x4b41b8(0x18d)]!==this['player1'])return;_0x3fc2d2['preventDefault']();const _0x373985=this[_0x4b41b8(0x224)](_0x3fc2d2);if(!_0x373985||!_0x373985[_0x4b41b8(0x82)])return;this['isDragging']=!![],this['selectedTile']={'x':_0x373985['x'],'y':_0x373985['y']},_0x373985['element'][_0x4b41b8(0x227)][_0x4b41b8(0x193)](_0x4b41b8(0xd9));const _0xe094b6=document['getElementById'](_0x4b41b8(0x1a4))['getBoundingClientRect']();this[_0x4b41b8(0x1f4)]=_0x3fc2d2['clientX']-(_0xe094b6[_0x4b41b8(0xb3)]+this[_0x4b41b8(0x1fc)]['x']*this[_0x4b41b8(0x22c)]),this[_0x4b41b8(0x1e2)]=_0x3fc2d2[_0x4b41b8(0x1a0)]-(_0xe094b6[_0x4b41b8(0x1ef)]+this[_0x4b41b8(0x1fc)]['y']*this[_0x4b41b8(0x22c)]);}['handleMouseMove'](_0x2efbdc){const _0x3610d1=_0x2a1a53;if(!this[_0x3610d1(0x16e)]||!this[_0x3610d1(0x1fc)]||this[_0x3610d1(0x15a)]||this['gameState']!==_0x3610d1(0x1cb))return;_0x2efbdc[_0x3610d1(0x156)]();const _0x3f9375=document['getElementById'](_0x3610d1(0x1a4))['getBoundingClientRect'](),_0x1e53d1=_0x2efbdc['clientX']-_0x3f9375['left']-this['offsetX'],_0x47ca82=_0x2efbdc['clientY']-_0x3f9375[_0x3610d1(0x1ef)]-this[_0x3610d1(0x1e2)],_0xa7e0d2=this[_0x3610d1(0xba)][this['selectedTile']['y']][this['selectedTile']['x']][_0x3610d1(0x82)];_0xa7e0d2[_0x3610d1(0x237)][_0x3610d1(0x8d)]='';if(!this[_0x3610d1(0x20a)]){const _0x2e0298=Math[_0x3610d1(0x89)](_0x1e53d1-this[_0x3610d1(0x1fc)]['x']*this[_0x3610d1(0x22c)]),_0x4a5bb4=Math[_0x3610d1(0x89)](_0x47ca82-this[_0x3610d1(0x1fc)]['y']*this[_0x3610d1(0x22c)]);if(_0x2e0298>_0x4a5bb4&&_0x2e0298>0x5)this[_0x3610d1(0x20a)]=_0x3610d1(0xee);else{if(_0x4a5bb4>_0x2e0298&&_0x4a5bb4>0x5)this['dragDirection']=_0x3610d1(0x17b);}}if(!this[_0x3610d1(0x20a)])return;if(this[_0x3610d1(0x20a)]===_0x3610d1(0xee)){const _0x38a2c2=Math[_0x3610d1(0x96)](0x0,Math[_0x3610d1(0x178)]((this[_0x3610d1(0x129)]-0x1)*this[_0x3610d1(0x22c)],_0x1e53d1));_0xa7e0d2[_0x3610d1(0x237)]['transform']=_0x3610d1(0xb0)+(_0x38a2c2-this['selectedTile']['x']*this[_0x3610d1(0x22c)])+_0x3610d1(0x23e),this[_0x3610d1(0xd4)]={'x':Math[_0x3610d1(0xdd)](_0x38a2c2/this['tileSizeWithGap']),'y':this[_0x3610d1(0x1fc)]['y']};}else{if(this[_0x3610d1(0x20a)]===_0x3610d1(0x17b)){const _0x4d7463=Math['max'](0x0,Math[_0x3610d1(0x178)]((this[_0x3610d1(0x16f)]-0x1)*this[_0x3610d1(0x22c)],_0x47ca82));_0xa7e0d2[_0x3610d1(0x237)][_0x3610d1(0xe8)]=_0x3610d1(0xb5)+(_0x4d7463-this[_0x3610d1(0x1fc)]['y']*this[_0x3610d1(0x22c)])+_0x3610d1(0x115),this['targetTile']={'x':this[_0x3610d1(0x1fc)]['x'],'y':Math[_0x3610d1(0xdd)](_0x4d7463/this[_0x3610d1(0x22c)])};}}}[_0x2a1a53(0x83)](_0x3fe670){const _0x1d5f6e=_0x2a1a53;if(!this[_0x1d5f6e(0x16e)]||!this['selectedTile']||!this['targetTile']||this[_0x1d5f6e(0x15a)]||this['gameState']!==_0x1d5f6e(0x1cb)){if(this[_0x1d5f6e(0x1fc)]){const _0x311d4b=this[_0x1d5f6e(0xba)][this[_0x1d5f6e(0x1fc)]['y']][this[_0x1d5f6e(0x1fc)]['x']];if(_0x311d4b[_0x1d5f6e(0x82)])_0x311d4b[_0x1d5f6e(0x82)][_0x1d5f6e(0x227)][_0x1d5f6e(0x7f)]('selected');}this[_0x1d5f6e(0x16e)]=![],this['selectedTile']=null,this[_0x1d5f6e(0xd4)]=null,this[_0x1d5f6e(0x20a)]=null,this[_0x1d5f6e(0xbf)]();return;}const _0x50446e=this[_0x1d5f6e(0xba)][this[_0x1d5f6e(0x1fc)]['y']][this[_0x1d5f6e(0x1fc)]['x']];if(_0x50446e[_0x1d5f6e(0x82)])_0x50446e[_0x1d5f6e(0x82)][_0x1d5f6e(0x227)][_0x1d5f6e(0x7f)]('selected');this[_0x1d5f6e(0x14d)](this[_0x1d5f6e(0x1fc)]['x'],this[_0x1d5f6e(0x1fc)]['y'],this[_0x1d5f6e(0xd4)]['x'],this[_0x1d5f6e(0xd4)]['y']),this[_0x1d5f6e(0x16e)]=![],this['selectedTile']=null,this[_0x1d5f6e(0xd4)]=null,this['dragDirection']=null;}['handleTouchStart'](_0x5d253e){const _0x523f80=_0x2a1a53;if(this[_0x523f80(0x15a)]||this[_0x523f80(0x17a)]!==_0x523f80(0x1cb)||this[_0x523f80(0x18d)]!==this[_0x523f80(0x1d6)])return;_0x5d253e[_0x523f80(0x156)]();const _0xf1fb5e=this[_0x523f80(0x224)](_0x5d253e[_0x523f80(0x121)][0x0]);if(!_0xf1fb5e||!_0xf1fb5e[_0x523f80(0x82)])return;this[_0x523f80(0x16e)]=!![],this[_0x523f80(0x1fc)]={'x':_0xf1fb5e['x'],'y':_0xf1fb5e['y']},_0xf1fb5e[_0x523f80(0x82)][_0x523f80(0x227)][_0x523f80(0x193)](_0x523f80(0xd9));const _0x572e72=document[_0x523f80(0x87)](_0x523f80(0x1a4))['getBoundingClientRect']();this['offsetX']=_0x5d253e['touches'][0x0]['clientX']-(_0x572e72[_0x523f80(0xb3)]+this[_0x523f80(0x1fc)]['x']*this[_0x523f80(0x22c)]),this[_0x523f80(0x1e2)]=_0x5d253e[_0x523f80(0x121)][0x0][_0x523f80(0x1a0)]-(_0x572e72[_0x523f80(0x1ef)]+this[_0x523f80(0x1fc)]['y']*this[_0x523f80(0x22c)]);}[_0x2a1a53(0x239)](_0x162eb4){const _0x5b1503=_0x2a1a53;if(!this[_0x5b1503(0x16e)]||!this[_0x5b1503(0x1fc)]||this[_0x5b1503(0x15a)]||this[_0x5b1503(0x17a)]!==_0x5b1503(0x1cb))return;_0x162eb4[_0x5b1503(0x156)]();const _0x2ab602=document[_0x5b1503(0x87)](_0x5b1503(0x1a4))[_0x5b1503(0xdc)](),_0x1d0b60=_0x162eb4[_0x5b1503(0x121)][0x0][_0x5b1503(0x15c)]-_0x2ab602['left']-this[_0x5b1503(0x1f4)],_0x2e3615=_0x162eb4[_0x5b1503(0x121)][0x0][_0x5b1503(0x1a0)]-_0x2ab602[_0x5b1503(0x1ef)]-this[_0x5b1503(0x1e2)],_0x5ccc4c=this[_0x5b1503(0xba)][this[_0x5b1503(0x1fc)]['y']][this[_0x5b1503(0x1fc)]['x']]['element'];requestAnimationFrame(()=>{const _0x2c55de=_0x5b1503;if(!this['dragDirection']){const _0x1172f1=Math[_0x2c55de(0x89)](_0x1d0b60-this[_0x2c55de(0x1fc)]['x']*this[_0x2c55de(0x22c)]),_0x13aeee=Math[_0x2c55de(0x89)](_0x2e3615-this['selectedTile']['y']*this[_0x2c55de(0x22c)]);if(_0x1172f1>_0x13aeee&&_0x1172f1>0x7)this[_0x2c55de(0x20a)]=_0x2c55de(0xee);else{if(_0x13aeee>_0x1172f1&&_0x13aeee>0x7)this[_0x2c55de(0x20a)]=_0x2c55de(0x17b);}}_0x5ccc4c[_0x2c55de(0x237)][_0x2c55de(0x8d)]='';if(this[_0x2c55de(0x20a)]===_0x2c55de(0xee)){const _0x1b2ba5=Math[_0x2c55de(0x96)](0x0,Math[_0x2c55de(0x178)]((this[_0x2c55de(0x129)]-0x1)*this[_0x2c55de(0x22c)],_0x1d0b60));_0x5ccc4c[_0x2c55de(0x237)]['transform']=_0x2c55de(0xb0)+(_0x1b2ba5-this[_0x2c55de(0x1fc)]['x']*this[_0x2c55de(0x22c)])+_0x2c55de(0x23e),this[_0x2c55de(0xd4)]={'x':Math[_0x2c55de(0xdd)](_0x1b2ba5/this[_0x2c55de(0x22c)]),'y':this[_0x2c55de(0x1fc)]['y']};}else{if(this['dragDirection']==='column'){const _0x401209=Math[_0x2c55de(0x96)](0x0,Math['min']((this[_0x2c55de(0x16f)]-0x1)*this['tileSizeWithGap'],_0x2e3615));_0x5ccc4c[_0x2c55de(0x237)]['transform']=_0x2c55de(0xb5)+(_0x401209-this[_0x2c55de(0x1fc)]['y']*this['tileSizeWithGap'])+_0x2c55de(0x115),this[_0x2c55de(0xd4)]={'x':this[_0x2c55de(0x1fc)]['x'],'y':Math[_0x2c55de(0xdd)](_0x401209/this[_0x2c55de(0x22c)])};}}});}[_0x2a1a53(0x177)](_0xe7daaf){const _0x8a3201=_0x2a1a53;if(!this[_0x8a3201(0x16e)]||!this[_0x8a3201(0x1fc)]||!this[_0x8a3201(0xd4)]||this[_0x8a3201(0x15a)]||this[_0x8a3201(0x17a)]!==_0x8a3201(0x1cb)){if(this[_0x8a3201(0x1fc)]){const _0xa1bc0d=this[_0x8a3201(0xba)][this[_0x8a3201(0x1fc)]['y']][this['selectedTile']['x']];if(_0xa1bc0d[_0x8a3201(0x82)])_0xa1bc0d[_0x8a3201(0x82)][_0x8a3201(0x227)][_0x8a3201(0x7f)](_0x8a3201(0xd9));}this[_0x8a3201(0x16e)]=![],this[_0x8a3201(0x1fc)]=null,this['targetTile']=null,this[_0x8a3201(0x20a)]=null,this[_0x8a3201(0xbf)]();return;}const _0x5911ce=this[_0x8a3201(0xba)][this[_0x8a3201(0x1fc)]['y']][this[_0x8a3201(0x1fc)]['x']];if(_0x5911ce[_0x8a3201(0x82)])_0x5911ce['element'][_0x8a3201(0x227)]['remove'](_0x8a3201(0xd9));this[_0x8a3201(0x14d)](this[_0x8a3201(0x1fc)]['x'],this[_0x8a3201(0x1fc)]['y'],this[_0x8a3201(0xd4)]['x'],this[_0x8a3201(0xd4)]['y']),this[_0x8a3201(0x16e)]=![],this[_0x8a3201(0x1fc)]=null,this[_0x8a3201(0xd4)]=null,this[_0x8a3201(0x20a)]=null;}[_0x2a1a53(0x224)](_0x3ab3b0){const _0x98c5e2=_0x2a1a53,_0x4d1520=document[_0x98c5e2(0x87)]('game-board')['getBoundingClientRect'](),_0x21872e=Math[_0x98c5e2(0x1e6)]((_0x3ab3b0[_0x98c5e2(0x15c)]-_0x4d1520[_0x98c5e2(0xb3)])/this['tileSizeWithGap']),_0x1e3a44=Math[_0x98c5e2(0x1e6)]((_0x3ab3b0['clientY']-_0x4d1520[_0x98c5e2(0x1ef)])/this[_0x98c5e2(0x22c)]);if(_0x21872e>=0x0&&_0x21872e<this[_0x98c5e2(0x129)]&&_0x1e3a44>=0x0&&_0x1e3a44<this['height'])return{'x':_0x21872e,'y':_0x1e3a44,'element':this[_0x98c5e2(0xba)][_0x1e3a44][_0x21872e][_0x98c5e2(0x82)]};return null;}[_0x2a1a53(0x14d)](_0x197615,_0x41ed00,_0x4a5402,_0x147d09){const _0x1a50ca=_0x2a1a53,_0x113156=this[_0x1a50ca(0x22c)];let _0xdabf06;const _0x194ba8=[],_0x4793a=[];if(_0x41ed00===_0x147d09){_0xdabf06=_0x197615<_0x4a5402?0x1:-0x1;const _0x54ed0c=Math[_0x1a50ca(0x178)](_0x197615,_0x4a5402),_0x3b39b9=Math[_0x1a50ca(0x96)](_0x197615,_0x4a5402);for(let _0x423dfc=_0x54ed0c;_0x423dfc<=_0x3b39b9;_0x423dfc++){_0x194ba8['push']({...this['board'][_0x41ed00][_0x423dfc]}),_0x4793a[_0x1a50ca(0x187)](this[_0x1a50ca(0xba)][_0x41ed00][_0x423dfc]['element']);}}else{if(_0x197615===_0x4a5402){_0xdabf06=_0x41ed00<_0x147d09?0x1:-0x1;const _0x1bc716=Math[_0x1a50ca(0x178)](_0x41ed00,_0x147d09),_0x32f036=Math[_0x1a50ca(0x96)](_0x41ed00,_0x147d09);for(let _0x1c54a2=_0x1bc716;_0x1c54a2<=_0x32f036;_0x1c54a2++){_0x194ba8['push']({...this[_0x1a50ca(0xba)][_0x1c54a2][_0x197615]}),_0x4793a[_0x1a50ca(0x187)](this[_0x1a50ca(0xba)][_0x1c54a2][_0x197615][_0x1a50ca(0x82)]);}}}const _0x59874b=this[_0x1a50ca(0xba)][_0x41ed00][_0x197615][_0x1a50ca(0x82)],_0x37fdcd=(_0x4a5402-_0x197615)*_0x113156,_0x459812=(_0x147d09-_0x41ed00)*_0x113156;_0x59874b[_0x1a50ca(0x237)][_0x1a50ca(0x8d)]=_0x1a50ca(0x167),_0x59874b[_0x1a50ca(0x237)]['transform']=_0x1a50ca(0xb0)+_0x37fdcd+_0x1a50ca(0x230)+_0x459812+_0x1a50ca(0x212);let _0x4d9dd4=0x0;if(_0x41ed00===_0x147d09)for(let _0x2945c9=Math['min'](_0x197615,_0x4a5402);_0x2945c9<=Math['max'](_0x197615,_0x4a5402);_0x2945c9++){if(_0x2945c9===_0x197615)continue;const _0x5323c7=_0xdabf06*-_0x113156*(_0x2945c9-_0x197615)/Math[_0x1a50ca(0x89)](_0x4a5402-_0x197615);_0x4793a[_0x4d9dd4]['style'][_0x1a50ca(0x8d)]=_0x1a50ca(0x167),_0x4793a[_0x4d9dd4][_0x1a50ca(0x237)][_0x1a50ca(0xe8)]=_0x1a50ca(0xb0)+_0x5323c7+_0x1a50ca(0xf4),_0x4d9dd4++;}else for(let _0x283f16=Math[_0x1a50ca(0x178)](_0x41ed00,_0x147d09);_0x283f16<=Math[_0x1a50ca(0x96)](_0x41ed00,_0x147d09);_0x283f16++){if(_0x283f16===_0x41ed00)continue;const _0x127f9c=_0xdabf06*-_0x113156*(_0x283f16-_0x41ed00)/Math['abs'](_0x147d09-_0x41ed00);_0x4793a[_0x4d9dd4][_0x1a50ca(0x237)]['transition']=_0x1a50ca(0x167),_0x4793a[_0x4d9dd4][_0x1a50ca(0x237)][_0x1a50ca(0xe8)]='translate(0,\x20'+_0x127f9c+_0x1a50ca(0x212),_0x4d9dd4++;}setTimeout(()=>{const _0x5beb3d=_0x1a50ca;if(_0x41ed00===_0x147d09){const _0x5c9ca2=this[_0x5beb3d(0xba)][_0x41ed00],_0xfe1c82=[..._0x5c9ca2];if(_0x197615<_0x4a5402){for(let _0x5d41d5=_0x197615;_0x5d41d5<_0x4a5402;_0x5d41d5++)_0x5c9ca2[_0x5d41d5]=_0xfe1c82[_0x5d41d5+0x1];}else{for(let _0x270649=_0x197615;_0x270649>_0x4a5402;_0x270649--)_0x5c9ca2[_0x270649]=_0xfe1c82[_0x270649-0x1];}_0x5c9ca2[_0x4a5402]=_0xfe1c82[_0x197615];}else{const _0x404d37=[];for(let _0xabe960=0x0;_0xabe960<this[_0x5beb3d(0x16f)];_0xabe960++)_0x404d37[_0xabe960]={...this[_0x5beb3d(0xba)][_0xabe960][_0x197615]};if(_0x41ed00<_0x147d09){for(let _0x5444c3=_0x41ed00;_0x5444c3<_0x147d09;_0x5444c3++)this[_0x5beb3d(0xba)][_0x5444c3][_0x197615]=_0x404d37[_0x5444c3+0x1];}else{for(let _0x5026ae=_0x41ed00;_0x5026ae>_0x147d09;_0x5026ae--)this[_0x5beb3d(0xba)][_0x5026ae][_0x197615]=_0x404d37[_0x5026ae-0x1];}this[_0x5beb3d(0xba)][_0x147d09][_0x4a5402]=_0x404d37[_0x41ed00];}this[_0x5beb3d(0xbf)]();const _0x3d1529=this[_0x5beb3d(0x103)](_0x4a5402,_0x147d09);_0x3d1529?this[_0x5beb3d(0x17a)]='animating':(log(_0x5beb3d(0x130)),this['sounds'][_0x5beb3d(0x1ae)][_0x5beb3d(0xe0)](),_0x59874b[_0x5beb3d(0x237)][_0x5beb3d(0x8d)]=_0x5beb3d(0x167),_0x59874b['style'][_0x5beb3d(0xe8)]=_0x5beb3d(0xc9),_0x4793a[_0x5beb3d(0x251)](_0x271cd4=>{const _0x95ea8a=_0x5beb3d;_0x271cd4['style'][_0x95ea8a(0x8d)]=_0x95ea8a(0x167),_0x271cd4['style']['transform']='translate(0,\x200)';}),setTimeout(()=>{const _0x336bc2=_0x5beb3d;if(_0x41ed00===_0x147d09){const _0x22c4b5=Math['min'](_0x197615,_0x4a5402);for(let _0x11b740=0x0;_0x11b740<_0x194ba8[_0x336bc2(0x20f)];_0x11b740++){this[_0x336bc2(0xba)][_0x41ed00][_0x22c4b5+_0x11b740]={..._0x194ba8[_0x11b740],'element':_0x4793a[_0x11b740]};}}else{const _0xf8b7ac=Math[_0x336bc2(0x178)](_0x41ed00,_0x147d09);for(let _0x37f686=0x0;_0x37f686<_0x194ba8[_0x336bc2(0x20f)];_0x37f686++){this[_0x336bc2(0xba)][_0xf8b7ac+_0x37f686][_0x197615]={..._0x194ba8[_0x37f686],'element':_0x4793a[_0x37f686]};}}this[_0x336bc2(0xbf)](),this['gameState']='playerTurn';},0xc8));},0xc8);}['resolveMatches'](_0x2a3a7e=null,_0x32754b=null){const _0x3f0222=_0x2a1a53;console[_0x3f0222(0x22d)]('resolveMatches\x20started,\x20gameOver:',this[_0x3f0222(0x15a)]);if(this[_0x3f0222(0x15a)])return console[_0x3f0222(0x22d)](_0x3f0222(0xe4)),![];const _0x4f54d6=_0x2a3a7e!==null&&_0x32754b!==null;console[_0x3f0222(0x22d)](_0x3f0222(0x1ca)+_0x4f54d6);const _0x146148=this[_0x3f0222(0x94)]();console[_0x3f0222(0x22d)](_0x3f0222(0x80)+_0x146148[_0x3f0222(0x20f)]+_0x3f0222(0xb6),_0x146148);let _0x190a3a=0x1,_0x4ba950='';if(_0x4f54d6&&_0x146148[_0x3f0222(0x20f)]>0x1){const _0xea905d=_0x146148['reduce']((_0x37fd9d,_0x2626c3)=>_0x37fd9d+_0x2626c3[_0x3f0222(0x220)],0x0);console[_0x3f0222(0x22d)](_0x3f0222(0x117)+_0xea905d);if(_0xea905d>=0x6&&_0xea905d<=0x8)_0x190a3a=1.2,_0x4ba950=_0x3f0222(0x11f)+_0xea905d+_0x3f0222(0x180),this[_0x3f0222(0x200)]['multiMatch'][_0x3f0222(0xe0)]();else _0xea905d>=0x9&&(_0x190a3a=0x3,_0x4ba950=_0x3f0222(0x1a2)+_0xea905d+_0x3f0222(0x226),this[_0x3f0222(0x200)]['multiMatch'][_0x3f0222(0xe0)]());}if(_0x146148['length']>0x0){const _0x5abed6=new Set();let _0x200431=0x0;const _0x3698c8=this[_0x3f0222(0x18d)],_0x345c43=this[_0x3f0222(0x18d)]===this[_0x3f0222(0x1d6)]?this[_0x3f0222(0x258)]:this[_0x3f0222(0x1d6)];try{_0x146148[_0x3f0222(0x251)](_0x44e9c6=>{const _0x27e8b6=_0x3f0222;console[_0x27e8b6(0x22d)](_0x27e8b6(0x140),_0x44e9c6),_0x44e9c6[_0x27e8b6(0x155)][_0x27e8b6(0x251)](_0x56dba1=>_0x5abed6[_0x27e8b6(0x193)](_0x56dba1));const _0x1abb12=this['handleMatch'](_0x44e9c6,_0x4f54d6);console['log'](_0x27e8b6(0xc7)+_0x1abb12);if(this['gameOver']){console['log']('Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing');return;}if(_0x1abb12>0x0)_0x200431+=_0x1abb12;});if(this[_0x3f0222(0x15a)])return console['log'](_0x3f0222(0x211)),!![];return console[_0x3f0222(0x22d)](_0x3f0222(0x249)+_0x200431+_0x3f0222(0x14e),[..._0x5abed6]),_0x200431>0x0&&!this[_0x3f0222(0x15a)]&&setTimeout(()=>{const _0x5373b4=_0x3f0222;if(this[_0x5373b4(0x15a)]){console[_0x5373b4(0x22d)]('Game\x20over,\x20skipping\x20recoil\x20animation');return;}console[_0x5373b4(0x22d)](_0x5373b4(0xfa),_0x345c43[_0x5373b4(0x171)]),this['animateRecoil'](_0x345c43,_0x200431);},0x64),setTimeout(()=>{const _0x2c7608=_0x3f0222;if(this[_0x2c7608(0x15a)]){console['log'](_0x2c7608(0x157));return;}console['log']('Animating\x20matched\x20tiles,\x20allMatchedTiles:',[..._0x5abed6]),_0x5abed6[_0x2c7608(0x251)](_0x1c22d6=>{const _0x20305c=_0x2c7608,[_0x5b7b5d,_0x5992e7]=_0x1c22d6[_0x20305c(0x1a7)](',')[_0x20305c(0x9b)](Number);this[_0x20305c(0xba)][_0x5992e7][_0x5b7b5d]?.[_0x20305c(0x82)]?this[_0x20305c(0xba)][_0x5992e7][_0x5b7b5d]['element'][_0x20305c(0x227)][_0x20305c(0x193)](_0x20305c(0x175)):console['warn'](_0x20305c(0x216)+_0x5b7b5d+','+_0x5992e7+_0x20305c(0x1b9));}),setTimeout(()=>{const _0x5b32e5=_0x2c7608;if(this[_0x5b32e5(0x15a)]){console[_0x5b32e5(0x22d)](_0x5b32e5(0x183));return;}console['log'](_0x5b32e5(0x10f),[..._0x5abed6]),_0x5abed6[_0x5b32e5(0x251)](_0x150554=>{const _0x20a820=_0x5b32e5,[_0x1af8ec,_0x2ba8cd]=_0x150554['split'](',')[_0x20a820(0x9b)](Number);this[_0x20a820(0xba)][_0x2ba8cd][_0x1af8ec]&&(this['board'][_0x2ba8cd][_0x1af8ec][_0x20a820(0x24f)]=null,this[_0x20a820(0xba)][_0x2ba8cd][_0x1af8ec][_0x20a820(0x82)]=null);}),this[_0x5b32e5(0x200)]['match'][_0x5b32e5(0xe0)](),console[_0x5b32e5(0x22d)]('Cascading\x20tiles');if(_0x190a3a>0x1&&this[_0x5b32e5(0x1b5)][_0x5b32e5(0x20f)]>0x0){const _0x5b9566=this[_0x5b32e5(0x1b5)][this[_0x5b32e5(0x1b5)][_0x5b32e5(0x20f)]-0x1],_0x138d8c=_0x5b9566['points'];_0x5b9566[_0x5b32e5(0xe5)]=Math[_0x5b32e5(0xdd)](_0x5b9566[_0x5b32e5(0xe5)]*_0x190a3a),_0x4ba950&&(log(_0x4ba950),log('Round\x20points\x20increased\x20from\x20'+_0x138d8c+_0x5b32e5(0x1a3)+_0x5b9566['points']+_0x5b32e5(0x95)));}this[_0x5b32e5(0x189)](()=>{const _0x594e99=_0x5b32e5;if(this[_0x594e99(0x15a)]){console[_0x594e99(0x22d)](_0x594e99(0xe3));return;}console[_0x594e99(0x22d)](_0x594e99(0x160)),this[_0x594e99(0x9e)]();});},0x12c);},0xc8),!![];}catch(_0x2f7d1d){return console[_0x3f0222(0x11d)](_0x3f0222(0x12d),_0x2f7d1d),this[_0x3f0222(0x17a)]=this[_0x3f0222(0x18d)]===this['player1']?_0x3f0222(0x1cb):_0x3f0222(0xf5),![];}}return console[_0x3f0222(0x22d)]('No\x20matches\x20found,\x20returning\x20false'),![];}[_0x2a1a53(0x94)](){const _0x4c8037=_0x2a1a53;console[_0x4c8037(0x22d)](_0x4c8037(0x203));const _0x5cad51=[];try{const _0x760127=[];for(let _0x15ecd9=0x0;_0x15ecd9<this[_0x4c8037(0x16f)];_0x15ecd9++){let _0x2d1ac3=0x0;for(let _0x44b02a=0x0;_0x44b02a<=this['width'];_0x44b02a++){const _0x54bd4e=_0x44b02a<this[_0x4c8037(0x129)]?this['board'][_0x15ecd9][_0x44b02a]?.[_0x4c8037(0x24f)]:null;if(_0x54bd4e!==this[_0x4c8037(0xba)][_0x15ecd9][_0x2d1ac3]?.[_0x4c8037(0x24f)]||_0x44b02a===this[_0x4c8037(0x129)]){const _0x3efce3=_0x44b02a-_0x2d1ac3;if(_0x3efce3>=0x3){const _0x3e95d1=new Set();for(let _0x48e962=_0x2d1ac3;_0x48e962<_0x44b02a;_0x48e962++){_0x3e95d1[_0x4c8037(0x193)](_0x48e962+','+_0x15ecd9);}_0x760127[_0x4c8037(0x187)]({'type':this[_0x4c8037(0xba)][_0x15ecd9][_0x2d1ac3][_0x4c8037(0x24f)],'coordinates':_0x3e95d1}),console[_0x4c8037(0x22d)]('Horizontal\x20match\x20found\x20at\x20row\x20'+_0x15ecd9+_0x4c8037(0xa3)+_0x2d1ac3+'-'+(_0x44b02a-0x1)+':',[..._0x3e95d1]);}_0x2d1ac3=_0x44b02a;}}}for(let _0x118586=0x0;_0x118586<this['width'];_0x118586++){let _0x4b818c=0x0;for(let _0x2005c8=0x0;_0x2005c8<=this['height'];_0x2005c8++){const _0x184828=_0x2005c8<this[_0x4c8037(0x16f)]?this[_0x4c8037(0xba)][_0x2005c8][_0x118586]?.[_0x4c8037(0x24f)]:null;if(_0x184828!==this[_0x4c8037(0xba)][_0x4b818c][_0x118586]?.['type']||_0x2005c8===this[_0x4c8037(0x16f)]){const _0x44bec5=_0x2005c8-_0x4b818c;if(_0x44bec5>=0x3){const _0x3d5aef=new Set();for(let _0x30e727=_0x4b818c;_0x30e727<_0x2005c8;_0x30e727++){_0x3d5aef[_0x4c8037(0x193)](_0x118586+','+_0x30e727);}_0x760127[_0x4c8037(0x187)]({'type':this['board'][_0x4b818c][_0x118586][_0x4c8037(0x24f)],'coordinates':_0x3d5aef}),console[_0x4c8037(0x22d)]('Vertical\x20match\x20found\x20at\x20col\x20'+_0x118586+_0x4c8037(0x21a)+_0x4b818c+'-'+(_0x2005c8-0x1)+':',[..._0x3d5aef]);}_0x4b818c=_0x2005c8;}}}const _0x5d0526=[],_0x105c47=new Set();return _0x760127[_0x4c8037(0x251)]((_0x4cdde4,_0xde3e8d)=>{const _0x3ade22=_0x4c8037;if(_0x105c47['has'](_0xde3e8d))return;const _0x195152={'type':_0x4cdde4[_0x3ade22(0x24f)],'coordinates':new Set(_0x4cdde4[_0x3ade22(0x155)])};_0x105c47[_0x3ade22(0x193)](_0xde3e8d);for(let _0x4259cd=0x0;_0x4259cd<_0x760127['length'];_0x4259cd++){if(_0x105c47[_0x3ade22(0xec)](_0x4259cd))continue;const _0x5290d5=_0x760127[_0x4259cd];if(_0x5290d5[_0x3ade22(0x24f)]===_0x195152['type']){const _0x788acc=[..._0x5290d5[_0x3ade22(0x155)]][_0x3ade22(0x154)](_0x2e3364=>_0x195152['coordinates'][_0x3ade22(0xec)](_0x2e3364));_0x788acc&&(_0x5290d5[_0x3ade22(0x155)][_0x3ade22(0x251)](_0x2b2f60=>_0x195152[_0x3ade22(0x155)][_0x3ade22(0x193)](_0x2b2f60)),_0x105c47[_0x3ade22(0x193)](_0x4259cd));}}_0x5d0526[_0x3ade22(0x187)]({'type':_0x195152[_0x3ade22(0x24f)],'coordinates':_0x195152[_0x3ade22(0x155)],'totalTiles':_0x195152[_0x3ade22(0x155)]['size']});}),_0x5cad51[_0x4c8037(0x187)](..._0x5d0526),console[_0x4c8037(0x22d)]('checkMatches\x20completed,\x20returning\x20matches:',_0x5cad51),_0x5cad51;}catch(_0x3dc73c){return console[_0x4c8037(0x11d)](_0x4c8037(0x191),_0x3dc73c),[];}}[_0x2a1a53(0x11a)](_0xddbe10,_0x45ad8c=!![]){const _0x24a5b0=_0x2a1a53;console[_0x24a5b0(0x22d)](_0x24a5b0(0x90),_0xddbe10,'isInitialMove:',_0x45ad8c);const _0x23f2e1=this[_0x24a5b0(0x18d)],_0x5d0ac3=this[_0x24a5b0(0x18d)]===this[_0x24a5b0(0x1d6)]?this[_0x24a5b0(0x258)]:this[_0x24a5b0(0x1d6)],_0x1ef955=_0xddbe10[_0x24a5b0(0x24f)],_0x40704f=_0xddbe10['totalTiles'];let _0x21b237=0x0,_0x61325b=0x0;console[_0x24a5b0(0x22d)](_0x5d0ac3['name']+'\x20health\x20before\x20match:\x20'+_0x5d0ac3[_0x24a5b0(0x252)]);_0x40704f==0x4&&(this[_0x24a5b0(0x200)][_0x24a5b0(0xcc)][_0x24a5b0(0xe0)](),log(_0x23f2e1[_0x24a5b0(0x171)]+'\x20created\x20a\x20match\x20of\x20'+_0x40704f+_0x24a5b0(0x204)));_0x40704f>=0x5&&(this['sounds']['hyperCube'][_0x24a5b0(0xe0)](),log(_0x23f2e1['name']+_0x24a5b0(0xc5)+_0x40704f+_0x24a5b0(0x204)));if(_0x1ef955===_0x24a5b0(0xd0)||_0x1ef955===_0x24a5b0(0x15e)||_0x1ef955===_0x24a5b0(0xc2)||_0x1ef955===_0x24a5b0(0x185)){_0x21b237=Math[_0x24a5b0(0xdd)](_0x23f2e1[_0x24a5b0(0x1b4)]*(_0x40704f===0x3?0x2:_0x40704f===0x4?0x3:0x4));let _0x201187=0x1;if(_0x40704f===0x4)_0x201187=1.5;else _0x40704f>=0x5&&(_0x201187=0x2);_0x21b237=Math[_0x24a5b0(0xdd)](_0x21b237*_0x201187),console[_0x24a5b0(0x22d)]('Base\x20damage:\x20'+_0x23f2e1[_0x24a5b0(0x1b4)]*(_0x40704f===0x3?0x2:_0x40704f===0x4?0x3:0x4)+_0x24a5b0(0x182)+_0x201187+',\x20Total\x20damage:\x20'+_0x21b237);_0x1ef955===_0x24a5b0(0xc2)&&(_0x21b237=Math['round'](_0x21b237*1.2),console['log']('Special\x20attack\x20multiplier\x20applied,\x20damage:\x20'+_0x21b237));_0x23f2e1['boostActive']&&(_0x21b237+=_0x23f2e1['boostValue']||0xa,_0x23f2e1[_0x24a5b0(0xe2)]=![],log(_0x23f2e1[_0x24a5b0(0x171)]+_0x24a5b0(0x20e)),console['log'](_0x24a5b0(0x135)+_0x21b237));_0x61325b=_0x21b237;const _0x37c6ca=_0x5d0ac3[_0x24a5b0(0x1d0)]*0xa;Math[_0x24a5b0(0x9d)]()*0x64<_0x37c6ca&&(_0x21b237=Math['floor'](_0x21b237/0x2),log(_0x5d0ac3[_0x24a5b0(0x171)]+_0x24a5b0(0x202)+_0x21b237+_0x24a5b0(0x15f)),console[_0x24a5b0(0x22d)]('Tactics\x20applied,\x20damage\x20reduced\x20to:\x20'+_0x21b237));let _0x1e6098=0x0;_0x5d0ac3['lastStandActive']&&(_0x1e6098=Math[_0x24a5b0(0x178)](_0x21b237,0x5),_0x21b237=Math[_0x24a5b0(0x96)](0x0,_0x21b237-_0x1e6098),_0x5d0ac3['lastStandActive']=![],console[_0x24a5b0(0x22d)](_0x24a5b0(0xaf)+_0x1e6098+_0x24a5b0(0xa9)+_0x21b237));const _0x37e82a=_0x1ef955===_0x24a5b0(0xd0)?_0x24a5b0(0x24d):_0x1ef955===_0x24a5b0(0x15e)?_0x24a5b0(0x1b6):_0x24a5b0(0x10d);let _0x3349a0;if(_0x1e6098>0x0)_0x3349a0=_0x23f2e1[_0x24a5b0(0x171)]+'\x20uses\x20'+_0x37e82a+_0x24a5b0(0x1e7)+_0x5d0ac3[_0x24a5b0(0x171)]+'\x20for\x20'+_0x61325b+_0x24a5b0(0x166)+_0x5d0ac3[_0x24a5b0(0x171)]+_0x24a5b0(0x168)+_0x1e6098+'\x20damage,\x20resulting\x20in\x20'+_0x21b237+_0x24a5b0(0x15f);else _0x1ef955===_0x24a5b0(0x185)?_0x3349a0=_0x23f2e1[_0x24a5b0(0x171)]+_0x24a5b0(0xe6)+_0x21b237+_0x24a5b0(0x222)+_0x5d0ac3[_0x24a5b0(0x171)]+_0x24a5b0(0x21b):_0x3349a0=_0x23f2e1[_0x24a5b0(0x171)]+_0x24a5b0(0x84)+_0x37e82a+_0x24a5b0(0x1e7)+_0x5d0ac3[_0x24a5b0(0x171)]+'\x20for\x20'+_0x21b237+_0x24a5b0(0x15f);_0x45ad8c?log(_0x3349a0):log(_0x24a5b0(0x8b)+_0x3349a0),_0x5d0ac3[_0x24a5b0(0x252)]=Math['max'](0x0,_0x5d0ac3[_0x24a5b0(0x252)]-_0x21b237),console['log'](_0x5d0ac3[_0x24a5b0(0x171)]+'\x20health\x20after\x20damage:\x20'+_0x5d0ac3[_0x24a5b0(0x252)]),this['updateHealth'](_0x5d0ac3),console[_0x24a5b0(0x22d)]('Calling\x20checkGameOver\x20from\x20handleMatch'),this[_0x24a5b0(0x20c)](),!this[_0x24a5b0(0x15a)]&&(console['log'](_0x24a5b0(0x184)),this['animateAttack'](_0x23f2e1,_0x21b237,_0x1ef955));}else _0x1ef955===_0x24a5b0(0x205)&&(this['usePowerup'](_0x23f2e1,_0x5d0ac3,_0x40704f),!this['gameOver']&&(console['log'](_0x24a5b0(0x11e)),this['animatePowerup'](_0x23f2e1)));(!this[_0x24a5b0(0x1b5)][this[_0x24a5b0(0x1b5)][_0x24a5b0(0x20f)]-0x1]||this['roundStats'][this[_0x24a5b0(0x1b5)]['length']-0x1][_0x24a5b0(0x123)])&&this[_0x24a5b0(0x1b5)][_0x24a5b0(0x187)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x135267=this[_0x24a5b0(0x1b5)][this[_0x24a5b0(0x1b5)][_0x24a5b0(0x20f)]-0x1];return _0x135267[_0x24a5b0(0xe5)]+=_0x21b237,_0x135267[_0x24a5b0(0x97)]+=0x1,console[_0x24a5b0(0x22d)]('handleMatch\x20completed,\x20damage\x20dealt:\x20'+_0x21b237),_0x21b237;}[_0x2a1a53(0x189)](_0x4a7ee8){const _0x45f4cc=_0x2a1a53;if(this['gameOver']){console[_0x45f4cc(0x22d)]('Game\x20over,\x20skipping\x20cascadeTiles');return;}const _0x4680a0=this['cascadeTilesWithoutRender'](),_0x2377e3='falling';for(let _0x4d08c1=0x0;_0x4d08c1<this[_0x45f4cc(0x129)];_0x4d08c1++){for(let _0x168c58=0x0;_0x168c58<this[_0x45f4cc(0x16f)];_0x168c58++){const _0x4274c4=this[_0x45f4cc(0xba)][_0x168c58][_0x4d08c1];if(_0x4274c4[_0x45f4cc(0x82)]&&_0x4274c4['element'][_0x45f4cc(0x237)][_0x45f4cc(0xe8)]==='translate(0px,\x200px)'){const _0x45c808=this[_0x45f4cc(0x137)](_0x4d08c1,_0x168c58);_0x45c808>0x0&&(_0x4274c4[_0x45f4cc(0x82)][_0x45f4cc(0x227)][_0x45f4cc(0x193)](_0x2377e3),_0x4274c4[_0x45f4cc(0x82)][_0x45f4cc(0x237)][_0x45f4cc(0xe8)]=_0x45f4cc(0xb5)+_0x45c808*this['tileSizeWithGap']+'px)');}}}this[_0x45f4cc(0xbf)](),_0x4680a0?setTimeout(()=>{const _0x1d5c7a=_0x45f4cc;if(this[_0x1d5c7a(0x15a)]){console[_0x1d5c7a(0x22d)](_0x1d5c7a(0x1b8));return;}this[_0x1d5c7a(0x200)][_0x1d5c7a(0xfb)]['play']();const _0x12c229=this[_0x1d5c7a(0x103)](),_0xfe8899=document[_0x1d5c7a(0x1e1)]('.'+_0x2377e3);_0xfe8899[_0x1d5c7a(0x251)](_0x20b681=>{const _0x2f5174=_0x1d5c7a;_0x20b681[_0x2f5174(0x227)]['remove'](_0x2377e3),_0x20b681['style'][_0x2f5174(0xe8)]='translate(0,\x200)';}),!_0x12c229&&_0x4a7ee8();},0x12c):_0x4a7ee8();}[_0x2a1a53(0x240)](){const _0x46e63b=_0x2a1a53;let _0x4702ca=![];for(let _0x44d03e=0x0;_0x44d03e<this[_0x46e63b(0x129)];_0x44d03e++){let _0x3d76ab=0x0;for(let _0x3651b8=this['height']-0x1;_0x3651b8>=0x0;_0x3651b8--){if(!this['board'][_0x3651b8][_0x44d03e][_0x46e63b(0x24f)])_0x3d76ab++;else _0x3d76ab>0x0&&(this['board'][_0x3651b8+_0x3d76ab][_0x44d03e]=this[_0x46e63b(0xba)][_0x3651b8][_0x44d03e],this[_0x46e63b(0xba)][_0x3651b8][_0x44d03e]={'type':null,'element':null},_0x4702ca=!![]);}for(let _0x1a06a6=0x0;_0x1a06a6<_0x3d76ab;_0x1a06a6++){this[_0x46e63b(0xba)][_0x1a06a6][_0x44d03e]=this['createRandomTile'](),_0x4702ca=!![];}}return _0x4702ca;}[_0x2a1a53(0x137)](_0x2099a2,_0x459d7c){const _0x54d998=_0x2a1a53;let _0xb03f9f=0x0;for(let _0x40ac57=_0x459d7c+0x1;_0x40ac57<this[_0x54d998(0x16f)];_0x40ac57++){if(!this[_0x54d998(0xba)][_0x40ac57][_0x2099a2][_0x54d998(0x24f)])_0xb03f9f++;else break;}return _0xb03f9f;}[_0x2a1a53(0xcf)](_0x1b133a,_0xb4ded7,_0x469f23){const _0xaebbc9=_0x2a1a53,_0x399efd=0x1-_0xb4ded7[_0xaebbc9(0x1d0)]*0.05;let _0x5364d8,_0x1552a2,_0x31ea21,_0x4fc932=0x1,_0x5200e8='';if(_0x469f23===0x4)_0x4fc932=1.5,_0x5200e8=_0xaebbc9(0xb9);else _0x469f23>=0x5&&(_0x4fc932=0x2,_0x5200e8='\x20(100%\x20bonus\x20for\x20match-5+)');if(_0x1b133a[_0xaebbc9(0x1ff)]==='Heal')_0x1552a2=0xa*_0x4fc932,_0x5364d8=Math[_0xaebbc9(0x1e6)](_0x1552a2*_0x399efd),_0x31ea21=_0x1552a2-_0x5364d8,_0x1b133a['health']=Math[_0xaebbc9(0x178)](_0x1b133a[_0xaebbc9(0x145)],_0x1b133a[_0xaebbc9(0x252)]+_0x5364d8),log(_0x1b133a['name']+_0xaebbc9(0x148)+_0x5364d8+'\x20HP'+_0x5200e8+(_0xb4ded7[_0xaebbc9(0x1d0)]>0x0?_0xaebbc9(0xaa)+_0x1552a2+_0xaebbc9(0x188)+_0x31ea21+'\x20due\x20to\x20'+_0xb4ded7[_0xaebbc9(0x171)]+_0xaebbc9(0x152):'')+'!');else{if(_0x1b133a[_0xaebbc9(0x1ff)]===_0xaebbc9(0x199))_0x1552a2=0xa*_0x4fc932,_0x5364d8=Math[_0xaebbc9(0x1e6)](_0x1552a2*_0x399efd),_0x31ea21=_0x1552a2-_0x5364d8,_0x1b133a['boostActive']=!![],_0x1b133a['boostValue']=_0x5364d8,log(_0x1b133a[_0xaebbc9(0x171)]+_0xaebbc9(0xa8)+_0x5364d8+_0xaebbc9(0x1c2)+_0x5200e8+(_0xb4ded7[_0xaebbc9(0x1d0)]>0x0?_0xaebbc9(0xaa)+_0x1552a2+',\x20reduced\x20by\x20'+_0x31ea21+_0xaebbc9(0x234)+_0xb4ded7[_0xaebbc9(0x171)]+_0xaebbc9(0x152):'')+'!');else{if(_0x1b133a[_0xaebbc9(0x1ff)]==='Regenerate')_0x1552a2=0x7*_0x4fc932,_0x5364d8=Math[_0xaebbc9(0x1e6)](_0x1552a2*_0x399efd),_0x31ea21=_0x1552a2-_0x5364d8,_0x1b133a[_0xaebbc9(0x252)]=Math[_0xaebbc9(0x178)](_0x1b133a['maxHealth'],_0x1b133a['health']+_0x5364d8),log(_0x1b133a['name']+'\x20uses\x20Regen,\x20restoring\x20'+_0x5364d8+_0xaebbc9(0x86)+_0x5200e8+(_0xb4ded7[_0xaebbc9(0x1d0)]>0x0?'\x20(originally\x20'+_0x1552a2+_0xaebbc9(0x188)+_0x31ea21+_0xaebbc9(0x234)+_0xb4ded7[_0xaebbc9(0x171)]+_0xaebbc9(0x152):'')+'!');else _0x1b133a[_0xaebbc9(0x1ff)]===_0xaebbc9(0x1c7)&&(_0x1552a2=0x5*_0x4fc932,_0x5364d8=Math[_0xaebbc9(0x1e6)](_0x1552a2*_0x399efd),_0x31ea21=_0x1552a2-_0x5364d8,_0x1b133a[_0xaebbc9(0x252)]=Math[_0xaebbc9(0x178)](_0x1b133a['maxHealth'],_0x1b133a['health']+_0x5364d8),log(_0x1b133a['name']+_0xaebbc9(0x14c)+_0x5364d8+'\x20HP'+_0x5200e8+(_0xb4ded7[_0xaebbc9(0x1d0)]>0x0?_0xaebbc9(0xaa)+_0x1552a2+_0xaebbc9(0x188)+_0x31ea21+'\x20due\x20to\x20'+_0xb4ded7[_0xaebbc9(0x171)]+'\x27s\x20tactics)':'')+'!'));}}this[_0xaebbc9(0x179)](_0x1b133a);}[_0x2a1a53(0x179)](_0x4d9386){const _0x4648b0=_0x2a1a53,_0x6e57ad=_0x4d9386===this[_0x4648b0(0x1d6)]?p1Health:p2Health,_0x4966ca=_0x4d9386===this[_0x4648b0(0x1d6)]?p1Hp:p2Hp,_0x396f72=_0x4d9386[_0x4648b0(0x252)]/_0x4d9386[_0x4648b0(0x145)]*0x64;_0x6e57ad[_0x4648b0(0x237)]['width']=_0x396f72+'%';let _0x4e9b96;if(_0x396f72>0x4b)_0x4e9b96=_0x4648b0(0x114);else{if(_0x396f72>0x32)_0x4e9b96=_0x4648b0(0x98);else _0x396f72>0x19?_0x4e9b96='#FFA500':_0x4e9b96=_0x4648b0(0x232);}_0x6e57ad[_0x4648b0(0x237)]['backgroundColor']=_0x4e9b96,_0x4966ca[_0x4648b0(0xbc)]=_0x4d9386[_0x4648b0(0x252)]+'/'+_0x4d9386[_0x4648b0(0x145)];}[_0x2a1a53(0x9e)](){const _0xa8be05=_0x2a1a53;if(this[_0xa8be05(0x17a)]===_0xa8be05(0x15a)||this[_0xa8be05(0x15a)]){console[_0xa8be05(0x22d)](_0xa8be05(0xe3));return;}this['currentTurn']=this['currentTurn']===this[_0xa8be05(0x1d6)]?this[_0xa8be05(0x258)]:this[_0xa8be05(0x1d6)],this[_0xa8be05(0x17a)]=this['currentTurn']===this['player1']?_0xa8be05(0x1cb):_0xa8be05(0xf5),turnIndicator[_0xa8be05(0xbc)]=_0xa8be05(0xeb)+this[_0xa8be05(0x18b)]+_0xa8be05(0xef)+(this['currentTurn']===this[_0xa8be05(0x1d6)]?'Player':_0xa8be05(0x1f5))+_0xa8be05(0x161),log(_0xa8be05(0xf1)+(this[_0xa8be05(0x18d)]===this['player1']?_0xa8be05(0x1ab):_0xa8be05(0x1f5))),this[_0xa8be05(0x18d)]===this['player2']&&setTimeout(()=>this[_0xa8be05(0xf5)](),0x3e8);}[_0x2a1a53(0xf5)](){const _0x178a06=_0x2a1a53;if(this['gameState']!=='aiTurn'||this['currentTurn']!==this[_0x178a06(0x258)])return;this[_0x178a06(0x17a)]=_0x178a06(0x1f8);const _0x476ab8=this[_0x178a06(0x9f)]();_0x476ab8?(log(this[_0x178a06(0x258)][_0x178a06(0x171)]+_0x178a06(0x1db)+_0x476ab8['x1']+',\x20'+_0x476ab8['y1']+')\x20to\x20('+_0x476ab8['x2']+',\x20'+_0x476ab8['y2']+')'),this[_0x178a06(0x14d)](_0x476ab8['x1'],_0x476ab8['y1'],_0x476ab8['x2'],_0x476ab8['y2'])):(log(this[_0x178a06(0x258)][_0x178a06(0x171)]+_0x178a06(0x223)),this['endTurn']());}['findAIMove'](){const _0x136912=_0x2a1a53;for(let _0x47ebf1=0x0;_0x47ebf1<this[_0x136912(0x16f)];_0x47ebf1++){for(let _0x592a5c=0x0;_0x592a5c<this[_0x136912(0x129)];_0x592a5c++){if(_0x592a5c<this[_0x136912(0x129)]-0x1&&this[_0x136912(0x172)](_0x592a5c,_0x47ebf1,_0x592a5c+0x1,_0x47ebf1))return{'x1':_0x592a5c,'y1':_0x47ebf1,'x2':_0x592a5c+0x1,'y2':_0x47ebf1};if(_0x47ebf1<this[_0x136912(0x16f)]-0x1&&this['canMakeMatch'](_0x592a5c,_0x47ebf1,_0x592a5c,_0x47ebf1+0x1))return{'x1':_0x592a5c,'y1':_0x47ebf1,'x2':_0x592a5c,'y2':_0x47ebf1+0x1};}}return null;}[_0x2a1a53(0x172)](_0x13162a,_0x1d69e7,_0x149b6b,_0x23b664){const _0x26b471=_0x2a1a53,_0x437fb3={...this[_0x26b471(0xba)][_0x1d69e7][_0x13162a]},_0x2440ea={...this[_0x26b471(0xba)][_0x23b664][_0x149b6b]};this[_0x26b471(0xba)][_0x1d69e7][_0x13162a]=_0x2440ea,this[_0x26b471(0xba)][_0x23b664][_0x149b6b]=_0x437fb3;const _0x473fcb=this[_0x26b471(0x94)]()[_0x26b471(0x20f)]>0x0;return this['board'][_0x1d69e7][_0x13162a]=_0x437fb3,this[_0x26b471(0xba)][_0x23b664][_0x149b6b]=_0x2440ea,_0x473fcb;}async['checkGameOver'](){const _0x1e247e=_0x2a1a53;if(this['gameOver']||this['isCheckingGameOver']){console['log'](_0x1e247e(0x14f)+this[_0x1e247e(0x15a)]+',\x20isCheckingGameOver='+this[_0x1e247e(0x10e)]+_0x1e247e(0x100)+this[_0x1e247e(0x18b)]);return;}this[_0x1e247e(0x10e)]=!![],console[_0x1e247e(0x22d)]('checkGameOver\x20started:\x20currentLevel='+this[_0x1e247e(0x18b)]+',\x20player1.health='+this[_0x1e247e(0x1d6)][_0x1e247e(0x252)]+',\x20player2.health='+this['player2'][_0x1e247e(0x252)]);const _0xa713fd=document[_0x1e247e(0x87)](_0x1e247e(0x119));if(this[_0x1e247e(0x1d6)][_0x1e247e(0x252)]<=0x0){console[_0x1e247e(0x22d)](_0x1e247e(0x1f3)),this[_0x1e247e(0x15a)]=!![],this[_0x1e247e(0x17a)]=_0x1e247e(0x15a),gameOver['textContent']='You\x20Lose!',turnIndicator[_0x1e247e(0xbc)]='Game\x20Over',log(this[_0x1e247e(0x258)][_0x1e247e(0x171)]+'\x20defeats\x20'+this[_0x1e247e(0x1d6)][_0x1e247e(0x171)]+'!'),_0xa713fd[_0x1e247e(0xbc)]='TRY\x20AGAIN',document[_0x1e247e(0x87)]('game-over-container')[_0x1e247e(0x237)][_0x1e247e(0x190)]=_0x1e247e(0xca);try{this[_0x1e247e(0x200)][_0x1e247e(0x8a)][_0x1e247e(0xe0)]();}catch(_0x76467d){console[_0x1e247e(0x11d)](_0x1e247e(0x15b),_0x76467d);}}else{if(this['player2'][_0x1e247e(0x252)]<=0x0){console[_0x1e247e(0x22d)]('Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)'),this[_0x1e247e(0x15a)]=!![],this[_0x1e247e(0x17a)]=_0x1e247e(0x15a),gameOver[_0x1e247e(0xbc)]=_0x1e247e(0x149),turnIndicator['textContent']=_0x1e247e(0xed),_0xa713fd[_0x1e247e(0xbc)]=this['currentLevel']===opponentsConfig[_0x1e247e(0x20f)]?_0x1e247e(0x208):_0x1e247e(0xf2),document[_0x1e247e(0x87)](_0x1e247e(0x13c))[_0x1e247e(0x237)][_0x1e247e(0x190)]=_0x1e247e(0xca);if(this[_0x1e247e(0x18d)]===this[_0x1e247e(0x1d6)]){const _0x439612=this['roundStats'][this[_0x1e247e(0x1b5)][_0x1e247e(0x20f)]-0x1];if(_0x439612&&!_0x439612[_0x1e247e(0x123)]){_0x439612[_0x1e247e(0x12c)]=this[_0x1e247e(0x1d6)][_0x1e247e(0x252)]/this[_0x1e247e(0x1d6)][_0x1e247e(0x145)]*0x64,_0x439612[_0x1e247e(0x123)]=!![];const _0x58eb55=_0x439612[_0x1e247e(0x97)]>0x0?_0x439612['points']/_0x439612[_0x1e247e(0x97)]/0x64*(_0x439612[_0x1e247e(0x12c)]+0x14)*(0x1+this['currentLevel']/0x38):0x0;log(_0x1e247e(0xd7)+_0x439612['points']+_0x1e247e(0x133)+_0x439612[_0x1e247e(0x97)]+_0x1e247e(0x112)+_0x439612[_0x1e247e(0x12c)]['toFixed'](0x2)+_0x1e247e(0x120)+this[_0x1e247e(0x18b)]),log(_0x1e247e(0x1df)+_0x439612['points']+_0x1e247e(0x209)+_0x439612['matches']+_0x1e247e(0x13b)+_0x439612[_0x1e247e(0x12c)]+_0x1e247e(0x143)+this[_0x1e247e(0x18b)]+_0x1e247e(0x1c8)+_0x58eb55),this[_0x1e247e(0x219)]+=_0x58eb55,log(_0x1e247e(0x1fb)+_0x439612[_0x1e247e(0xe5)]+_0x1e247e(0xda)+_0x439612['matches']+_0x1e247e(0x228)+_0x439612['healthPercentage']['toFixed'](0x2)+'%'),log(_0x1e247e(0x254)+_0x58eb55+_0x1e247e(0x257)+this[_0x1e247e(0x219)]);}}await this[_0x1e247e(0x12a)](this[_0x1e247e(0x18b)]);this[_0x1e247e(0x18b)]===opponentsConfig[_0x1e247e(0x20f)]?(this[_0x1e247e(0x200)]['finalWin'][_0x1e247e(0xe0)](),log(_0x1e247e(0x1f2)+this['grandTotalScore']),this[_0x1e247e(0x219)]=0x0,await this[_0x1e247e(0x106)](),log(_0x1e247e(0xb4))):(this[_0x1e247e(0x18b)]+=0x1,await this[_0x1e247e(0x9a)](),console[_0x1e247e(0x22d)](_0x1e247e(0x139)+this[_0x1e247e(0x18b)]),this[_0x1e247e(0x200)][_0x1e247e(0xa5)][_0x1e247e(0xe0)]());const _0x3a1175=this[_0x1e247e(0x1c0)]+_0x1e247e(0x1b1)+this[_0x1e247e(0x258)][_0x1e247e(0x171)][_0x1e247e(0x19b)]()['replace'](/ /g,'-')+'.png';p2Image[_0x1e247e(0x1ba)]=_0x3a1175,p2Image[_0x1e247e(0x227)]['add'](_0x1e247e(0x174)),p1Image[_0x1e247e(0x227)][_0x1e247e(0x193)]('winner'),this[_0x1e247e(0xbf)]();}}this[_0x1e247e(0x10e)]=![],console[_0x1e247e(0x22d)](_0x1e247e(0x92)+this[_0x1e247e(0x18b)]+_0x1e247e(0x1ad)+this[_0x1e247e(0x15a)]);}async[_0x2a1a53(0x12a)](_0x4ff8ae){const _0x395589=_0x2a1a53,_0x4c1ab8={'level':_0x4ff8ae,'score':this['grandTotalScore']};console[_0x395589(0x22d)](_0x395589(0x17c)+_0x4c1ab8[_0x395589(0x1bd)]+',\x20score='+_0x4c1ab8[_0x395589(0xf6)]);try{const _0x570ebc=await fetch(_0x395589(0x1f9),{'method':'POST','headers':{'Content-Type':_0x395589(0x19c)},'body':JSON['stringify'](_0x4c1ab8)});if(!_0x570ebc['ok'])throw new Error(_0x395589(0x113)+_0x570ebc[_0x395589(0xea)]);const _0x20949f=await _0x570ebc['json']();console['log'](_0x395589(0x23d),_0x20949f),log(_0x395589(0xeb)+_0x20949f[_0x395589(0x1bd)]+_0x395589(0x1da)+_0x20949f['score'][_0x395589(0x245)](0x2)),_0x20949f[_0x395589(0xea)]==='success'?log(_0x395589(0x158)+_0x20949f[_0x395589(0x1bd)]+_0x395589(0x169)+_0x20949f[_0x395589(0xf6)][_0x395589(0x245)](0x2)+_0x395589(0x21c)+_0x20949f[_0x395589(0xb7)]):log(_0x395589(0x1f0)+_0x20949f[_0x395589(0x1f7)]);}catch(_0x432631){console[_0x395589(0x11d)](_0x395589(0x91),_0x432631),log(_0x395589(0x1c1)+_0x432631[_0x395589(0x1f7)]);}}[_0x2a1a53(0x127)](_0x28d1ec,_0x1fb8c6,_0x12363c,_0x136a6e){const _0x375cf1=_0x2a1a53,_0x1fb720=_0x28d1ec[_0x375cf1(0x237)][_0x375cf1(0xe8)]||'',_0x41a649=_0x1fb720[_0x375cf1(0x8e)](_0x375cf1(0x231))?_0x1fb720[_0x375cf1(0x163)](/scaleX\([^)]+\)/)[0x0]:'';_0x28d1ec['style']['transition']='transform\x20'+_0x136a6e/0x2/0x3e8+_0x375cf1(0xf3),_0x28d1ec[_0x375cf1(0x237)][_0x375cf1(0xe8)]='translateX('+_0x1fb8c6+_0x375cf1(0x125)+_0x41a649,_0x28d1ec[_0x375cf1(0x227)][_0x375cf1(0x193)](_0x12363c),setTimeout(()=>{const _0x2a55a0=_0x375cf1;_0x28d1ec[_0x2a55a0(0x237)][_0x2a55a0(0xe8)]=_0x41a649,setTimeout(()=>{const _0x3ec8b5=_0x2a55a0;_0x28d1ec[_0x3ec8b5(0x227)][_0x3ec8b5(0x7f)](_0x12363c);},_0x136a6e/0x2);},_0x136a6e/0x2);}[_0x2a1a53(0xa2)](_0x3c819c,_0x5c5560,_0x38843a){const _0x338050=_0x2a1a53,_0x2af95d=_0x3c819c===this['player1']?p1Image:p2Image,_0xd58cec=_0x3c819c===this[_0x338050(0x1d6)]?0x1:-0x1,_0x1b4a07=Math[_0x338050(0x178)](0xa,0x2+_0x5c5560*0.4),_0x31d7a0=_0xd58cec*_0x1b4a07,_0x575fdb=_0x338050(0x23b)+_0x38843a;this[_0x338050(0x127)](_0x2af95d,_0x31d7a0,_0x575fdb,0xc8);}[_0x2a1a53(0x138)](_0x24ceb6){const _0x1c4e12=_0x2a1a53,_0x240964=_0x24ceb6===this[_0x1c4e12(0x1d6)]?p1Image:p2Image;this[_0x1c4e12(0x127)](_0x240964,0x0,_0x1c4e12(0x10b),0xc8);}[_0x2a1a53(0xce)](_0x2f32cc,_0x49ee61){const _0x33d3ac=_0x2a1a53,_0x26a9f7=_0x2f32cc===this['player1']?p1Image:p2Image,_0x21cc08=_0x2f32cc===this[_0x33d3ac(0x1d6)]?-0x1:0x1,_0x1b6e55=Math[_0x33d3ac(0x178)](0xa,0x2+_0x49ee61*0.4),_0x1d076c=_0x21cc08*_0x1b6e55;this[_0x33d3ac(0x127)](_0x26a9f7,_0x1d076c,'glow-recoil',0xc8);}}function randomChoice(_0x43b38c){const _0x144526=_0x2a1a53;return _0x43b38c[Math[_0x144526(0x1e6)](Math['random']()*_0x43b38c['length'])];}function log(_0x623ead){const _0x855a15=_0x2a1a53,_0x1549a8=document['getElementById']('battle-log'),_0x56c92a=document['createElement']('li');_0x56c92a[_0x855a15(0xbc)]=_0x623ead,_0x1549a8['insertBefore'](_0x56c92a,_0x1549a8['firstChild']),_0x1549a8['children'][_0x855a15(0x20f)]>0x32&&_0x1549a8[_0x855a15(0x104)](_0x1549a8[_0x855a15(0x23f)]),_0x1549a8[_0x855a15(0x1bb)]=0x0;}function _0x507e(_0x122add,_0x42c46c){const _0x404eca=_0x404e();return _0x507e=function(_0x507e1a,_0x2ef71e){_0x507e1a=_0x507e1a-0x7f;let _0x512810=_0x404eca[_0x507e1a];return _0x512810;},_0x507e(_0x122add,_0x42c46c);}const turnIndicator=document[_0x2a1a53(0x87)](_0x2a1a53(0xc6)),p1Name=document[_0x2a1a53(0x87)](_0x2a1a53(0x11b)),p1Image=document[_0x2a1a53(0x87)](_0x2a1a53(0x1e0)),p1Health=document[_0x2a1a53(0x87)](_0x2a1a53(0x1b0)),p1Hp=document['getElementById'](_0x2a1a53(0x24e)),p1Strength=document[_0x2a1a53(0x87)](_0x2a1a53(0xd2)),p1Speed=document[_0x2a1a53(0x87)](_0x2a1a53(0x1fe)),p1Tactics=document[_0x2a1a53(0x87)]('p1-tactics'),p1Size=document[_0x2a1a53(0x87)](_0x2a1a53(0x16a)),p1Powerup=document[_0x2a1a53(0x87)](_0x2a1a53(0x150)),p1Type=document[_0x2a1a53(0x87)](_0x2a1a53(0x1ed)),p2Name=document[_0x2a1a53(0x87)](_0x2a1a53(0xfe)),p2Image=document[_0x2a1a53(0x87)]('p2-image'),p2Health=document[_0x2a1a53(0x87)](_0x2a1a53(0x116)),p2Hp=document['getElementById'](_0x2a1a53(0x128)),p2Strength=document[_0x2a1a53(0x87)](_0x2a1a53(0x165)),p2Speed=document[_0x2a1a53(0x87)](_0x2a1a53(0x1c5)),p2Tactics=document[_0x2a1a53(0x87)](_0x2a1a53(0x126)),p2Size=document[_0x2a1a53(0x87)](_0x2a1a53(0xfd)),p2Powerup=document['getElementById'](_0x2a1a53(0x159)),p2Type=document['getElementById']('p2-type'),battleLog=document[_0x2a1a53(0x87)](_0x2a1a53(0x170)),gameOver=document[_0x2a1a53(0x87)](_0x2a1a53(0x20b));function getAssets(){return new Promise((_0x821cd9,_0x447b6f)=>{const _0x1a7086=_0x507e;let _0x4f7be7=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1a7086(0x1ea),'type':_0x1a7086(0x21e),'powerup':_0x1a7086(0x23a)}];var _0x5431e4=new XMLHttpRequest();_0x5431e4[_0x1a7086(0x210)](_0x1a7086(0x16d),_0x1a7086(0xc4),!![]),_0x5431e4['send'](),_0x5431e4[_0x1a7086(0x24c)]=function(){const _0x28aeb1=_0x1a7086;if(_0x5431e4[_0x28aeb1(0x1d8)]==XMLHttpRequest['DONE']){if(_0x5431e4[_0x28aeb1(0xea)]==0xc8){var _0x8497bb=_0x5431e4[_0x28aeb1(0x134)];if(_0x8497bb!==_0x28aeb1(0x162))try{_0x4f7be7=JSON[_0x28aeb1(0x102)](_0x8497bb),!Array['isArray'](_0x4f7be7)&&(_0x4f7be7=[_0x4f7be7]);}catch(_0x266d97){console['error'](_0x28aeb1(0x241),_0x266d97);}_0x821cd9(_0x4f7be7);}else console[_0x28aeb1(0x11d)]('Request\x20failed\x20with\x20status:',_0x5431e4['status']),_0x821cd9(_0x4f7be7);}};});}((async()=>{const _0x33d977=_0x2a1a53;try{const _0xcc7527=await getAssets();console[_0x33d977(0x22d)](_0x33d977(0x1c9),_0xcc7527);const _0x14a5dc=new MonstrocityMatch3(_0xcc7527);console[_0x33d977(0x22d)]('Main:\x20Game\x20instance\x20created'),await _0x14a5dc['init'](),console[_0x33d977(0x22d)](_0x33d977(0x207));}catch(_0x38d0ee){console[_0x33d977(0x11d)]('Main:\x20Error\x20initializing\x20game:',_0x38d0ee);}})());function _0x404e(){const _0x50de34=['Battle\x20Damaged','try-again','handleMatch','p1-name','updatePlayerDisplay','error','Animating\x20powerup','Multi-Match!\x20',',\x20level=','touches','div','completed','tile\x20','px)\x20','p2-tactics','applyAnimation','p2-hp','width','saveScoreToDatabase','Slime\x20Mind','healthPercentage','Error\x20in\x20resolveMatches:','Heal','loadProgress','No\x20match,\x20reverting\x20tiles...','msMaxTouchPoints','4lybXDT',',\x20matches=','responseText','Boost\x20applied,\x20damage:\x20','progress-message','countEmptyBelow','animatePowerup','Progress\x20saved:\x20currentLevel=','showProgressPopup',')\x20/\x20100)\x20*\x20(','game-over-container','character-options','Texby','.png','Processing\x20match:','Billandar\x20and\x20Ted','button','\x20+\x2020))\x20*\x20(1\x20+\x20','Merdock','maxHealth','handleGameOverButton\x20started:\x20currentLevel=','Progress\x20saved:\x20Level\x20','\x20uses\x20Heal,\x20restoring\x20','You\x20Win!','\x20but\x20dulls\x20tactics\x20to\x20','No\x20progress\x20found\x20or\x20status\x20not\x20success:','\x20uses\x20Minor\x20Regen,\x20restoring\x20','slideTiles',',\x20tiles\x20to\x20clear:','checkGameOver\x20skipped:\x20gameOver=','p1-powerup','1782VJWiHm','\x27s\x20tactics)','onchange','some','coordinates','preventDefault','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','Score\x20Saved:\x20Level\x20','p2-powerup','gameOver','Error\x20playing\x20lose\x20sound:','clientX','</strong></p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Type:\x20','second-attack','\x20damage!','Cascade\x20complete,\x20ending\x20turn','\x27s\x20Turn','false','match','swapPlayerCharacter','p2-strength','\x20damage,\x20but\x20','transform\x200.2s\x20ease','\x27s\x20Last\x20Stand\x20mitigates\x20',',\x20Score\x20','p1-size','stringify','monstrocity.png)','GET','isDragging','height','battle-log','name','canMakeMatch','character-option','loser','matched','Right','handleTouchEnd','min','updateHealth','gameState','column','Saving\x20score:\x20level=','https://www.skulliance.io/staking/sounds/voice_go.ogg','initGame:\x20Started\x20with\x20this.currentLevel=','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','body',',\x20Match\x20bonus:\x20','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','Game\x20not\x20over,\x20animating\x20attack','last-stand','handleGameOverButton\x20completed:\x20currentLevel=','push',',\x20reduced\x20by\x20','cascadeTiles','updateOpponentDisplay','currentLevel','\x22\x20alt=\x22','currentTurn','Response\x20status:','character-select-container','display','Error\x20in\x20checkMatches:','handleGameOverButton','add',',\x20player2.health=','Craig','className','setBackground','showProgressPopup:\x20User\x20chose\x20Restart','Boost\x20Attack','showProgressPopup:\x20User\x20chose\x20Resume','toLowerCase','application/json','.game-container',',\x20score=','Starting\x20Level\x20','clientY','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','Mega\x20Multi-Match!\x20','\x20to\x20','game-board','19710430iNCjjR','imageUrl','split','lastStandActive','Sending\x20saveProgress\x20request\x20with\x20data:','https://www.skulliance.io/staking/sounds/badmove.ogg','Player','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked',',\x20gameOver=','badMove','</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Speed:\x20','p1-health','battle-damaged/','createRandomTile','getCharacterImageUrl','strength','roundStats','Bite','initializing','Game\x20over,\x20skipping\x20cascade\x20resolution',')\x20has\x20no\x20element\x20to\x20animate','src','scrollTop','Large','level','Loaded\x20opponent\x20for\x20level\x20','addEventListener','baseImagePath','Error\x20saving\x20score:\x20','\x20damage',',\x20loadedScore=','touchend','p2-speed','Drake','Minor\x20Regen','\x20/\x2056)\x20=\x20','Main:\x20Player\x20characters\x20loaded:','Is\x20initial\x20move:\x20','playerTurn','showCharacterSelect:\x20Character\x20selected:\x20','Starting\x20fresh\x20at\x20Level\x201','updateTileSizeWithGap','removeEventListener','tactics','11UyuXGV','click','3411zBSgJh','logo.png','Raw\x20response\x20text:','player1','alt','readyState','22304xtiLTa','\x20Score:\x20','\x20swaps\x20tiles\x20at\x20(','Failed\x20to\x20save\x20progress:','Mandiblus','ajax/load-monstrocity-progress.php','Round\x20Score\x20Formula:\x20(((','p1-image','querySelectorAll','offsetY','updateTheme','innerHTML','...','floor','\x20on\x20','size','touchstart','Medium','init:\x20Prompting\x20with\x20loadedLevel=','playerCharactersConfig','p1-type','text','top','Score\x20Not\x20Saved:\x20','\x27s\x20','Final\x20level\x20completed!\x20Final\x20score:\x20','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)','offsetX','Opponent','Goblin\x20Ganger','message','animating','ajax/save-monstrocity-score.php','\x20HP!','Round\x20Won!\x20Points:\x20','selectedTile','https://www.skulliance.io/staking/icons/','p1-speed','powerup','sounds','796kYWsGk','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20','checkMatches\x20started','\x20tiles!','power-up','visibility','Main:\x20Game\x20initialized\x20successfully','START\x20OVER','\x20/\x20','dragDirection','game-over','checkGameOver','Parsed\x20response:','\x27s\x20Boost\x20fades.','length','open','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','px)','Leader','createElement','ontouchstart','Tile\x20at\x20(','\x20with\x20Score\x20of\x20','2589545zcIbhc','grandTotalScore',',\x20rows\x20','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!',',\x20Completions:\x20','initGame','Base','Koipon','totalTiles','Resume','\x20damage\x20to\x20','\x20passes...','getTileFromEvent','dataset','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','classList',',\x20Health\x20Left:\x20','Dankle','\x20size\x20','progress-modal','tileSizeWithGap','log','boostValue','progress-modal-buttons','px,\x20','scaleX','#F44336','json','\x20due\x20to\x20','change-character','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','style','boosts\x20health\x20to\x20','handleTouchMove','Regenerate','glow-','Error\x20loading\x20progress:','Save\x20response:','px,\x200)\x20scale(1.05)','lastChild','cascadeTilesWithoutRender','Failed\x20to\x20parse\x20JSON:','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','drops\x20health\x20to\x20','574412pHOFcU','toFixed','battle-damaged','POST','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','Total\x20damage\x20dealt:\x20','Ouchie','showCharacterSelect','onreadystatechange','Slash','p1-hp','type','mouseup','forEach','health','Left','Round\x20Score:\x20','handleMouseDown','Error\x20saving\x20progress:',',\x20Grand\x20Total\x20Score:\x20','player2','remove','Found\x20','</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20','element','handleMouseUp','\x20uses\x20','scaleX(-1)','\x20HP','getElementById','onload','abs','loss','Cascade:\x20','Player\x201','transition','includes','base','handleMatch\x20started,\x20match:','Error\x20saving\x20to\x20database:','checkGameOver\x20completed:\x20currentLevel=','progress-start-fresh','checkMatches','\x20after\x20multi-match\x20bonus!','max','matches','#FFC105','leader','saveProgress','map','offsetWidth','random','endTurn','findAIMove','https://www.skulliance.io/staking/sounds/powergem_created.ogg','setItem','animateAttack',',\x20cols\x20','gameTheme','win','Katastrophy','Spydrax','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+',',\x20damage:\x20','\x20(originally\x20','showCharacterSelect:\x20Called\x20with\x20isInitial=','\x20goes\x20first!','value','2999899vJFkcV','Last\x20Stand\x20applied,\x20mitigated\x20','translate(','Jarhead','Restart','left','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','translate(0,\x20','\x20matches:','attempts','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','\x20(50%\x20bonus\x20for\x20match-4)','board','Resume\x20from\x20Level\x20','textContent','flex','https://www.skulliance.io/staking/images/monstrocity/','renderBoard','https://www.skulliance.io/staking/sounds/select.ogg','touchmove','special-attack','monstrocity','ajax/get-monstrocity-assets.php','\x20created\x20a\x20match\x20of\x20','turn-indicator','Damage\x20from\x20match:\x20','playerCharacters','translate(0,\x200)','block','ajax/clear-monstrocity-progress.php','powerGem','querySelector','animateRecoil','usePowerup','first-attack','AI\x20Opponent','p1-strength','progress-modal-content','targetTile','</p>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>Tactics:\x20','success','Calculating\x20round\x20score:\x20points=','speed','selected',',\x20Matches:\x20','handleMouseMove','getBoundingClientRect','round','theme','.game-logo','play','7510140EXcdCa','boostActive','Game\x20over,\x20skipping\x20endTurn','Game\x20over,\x20exiting\x20resolveMatches','points','\x20uses\x20Last\x20Stand,\x20dealing\x20','none','transform','\x22>\x0a\x09\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p><strong>','status','Level\x20','has','Game\x20Over','row','\x20-\x20','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','Turn\x20switched\x20to\x20','NEXT\x20LEVEL','s\x20linear','px,\x200)','aiTurn','score','\x20steps\x20into\x20the\x20fray\x20with\x20','url(','addEventListeners','Animating\x20recoil\x20for\x20defender:','cascade','replace','p2-size','p2-name','tileTypes',',\x20currentLevel=','appendChild','parse','resolveMatches','removeChild','inline-block','clearProgress','showCharacterSelect:\x20this.player1\x20set:\x20','theme-select','init:\x20Async\x20initialization\x20completed','Small','glow-power-up','mousedown','Shadow\x20Strike','isCheckingGameOver','Clearing\x20matched\x20tiles:','init:\x20Starting\x20async\x20initialization','createCharacter',',\x20healthPercentage=','HTTP\x20error!\x20Status:\x20','#4CAF50','px)\x20scale(1.05)','p2-health','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20'];_0x404e=function(){return _0x50de34;};return _0x404e();}
  </script>
</body>
</html>
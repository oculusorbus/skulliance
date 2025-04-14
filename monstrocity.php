<?php
session_start();
if(!isset($_SESSION['logged_in'])){
	if(isset($_COOKIE['SessionCookie'])){
		$cookie = $_COOKIE['SessionCookie'];
		$cookie = json_decode($cookie, true);
		$_SESSION = $cookie;
	}
}
if(isset($_SESSION)){
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
	  white-space: nowrap;
    }
	
	a {
		color: white;
		font-weight: bold;
		text-decoration: none;
	}

	.game-container {
	  text-align: center;
	  padding: 20px;
	  background-color: #002f44;
	  box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
	  width: 100%;
	  height: 100%;
	  min-width: 910px;
	  max-width: 1024px;
	  box-sizing: border-box;
	  border-left: 3px solid black;
	  border-right: 3px solid black;
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
	    position: absolute;
	    top: 430px;
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
	
    #flip-p1, #flip-p2 {
		margin-top: 10px;
		margin-bottom: 0px;
		-webkit-filter: drop-shadow(2px 5px 10px #000);
		filter: drop-shadow(2px 5px 10px #000);
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
        top: 317px;
      }

      #character-select-container {
        width: 90%;
        padding: 10px;
      }

      .character-option {
        width: 140px;
        margin: 5px;
      }
	  
	  #flip-p1, #flip-p2 {
		  display: none;
	  }
    }
  </style>
</head>
<body>
  <div class="game-container">
    <div id="game-over-container">
      <div id="game-over"></div>
      <div id="game-over-buttons">
        <button id="try-again"></button>
		<form action="leaderboards.php" method="post"><input type="hidden" name="filterbystreak" id="filterbystreak" value="monthly-monstrocity"><input id="leaderboard" type="submit" value="LEADERBOARD"></form>
      </div>
    </div>
    <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Logo" class="game-logo">
    <button id="restart">Restart Level</button>
    <button id="change-character" style="display: none;">Switch Character</button>
    <div class="turn-indicator" id="turn-indicator">Player 1's Turn</div>

    <div class="battlefield">
	  <div class="character" id="player1">
	      <h2><span id="p1-name"></span></h2>
	      <img id="p1-image" src="" alt="Player 1 Image">
	      <div class="health-bar"><div class="health" id="p1-health"></div></div>
  	      <button id="flip-p1">Flip Player 1 Image</button>
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
  	      <button id="flip-p2">Flip Opponent Image</button>
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

  </div>
    <div id="character-select-container">
      <h2>Select Your Character</h2>
	  <div>
	    <label for="theme-select">Theme: </label>
	    <select id="theme-select">
		  <optgroup label="Default Game Theme">
	      <option value="monstrocity" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Monstrocity - Season 1</option>
	  	  </optgroup>
		  <optgroup label="Independent Artist Themes">
			  <option value="bungking" 
			  	data-policy-ids="f5a4009f12b9ee53b15edf338d1b7001641630be8308409b1477753b" 
				data-orientations="Right" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Bungking - Yume</option>
				
			  <option value="darkula" 
			  	data-policy-ids="b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0" 
				data-orientations="Random" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Darkula - Island of the Uncanny Neighbors</option>
				
			  <option value="darkula2" 
				  data-policy-ids="b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0" 
				  data-orientations="Random" 
				  data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				  Darkula - Island of the Violent Neighbors</option>
				  
			  <option value="muses" 
			  	data-policy-ids="7f95b5948e3efed1171523757b472f24aecfab8303612cfa1b6fec55" 
				data-orientations="Random" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Josh Howard - Muses of the Multiverse</option>
				
			  <option value="maxi" 
			  	data-policy-ids="b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5" 
				data-orientations="Right" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Maxingo - Digital Hell Citizens 2: Fighters</option>
				
  			  <option value="shortyverse" 
			  	data-policy-ids="0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c" 
				data-orientations="Random" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Ohh Meed - Shorty Verse</option>
				
			  <option value="shortyverse2" 
			  	data-policy-ids="0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c" 
				data-orientations="Random" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Ohh Meed - Shorty Verse Engaged</option>
				
			  <option value="ritual" 
			  	data-policy-ids="16b10d60f428b03fa5bafa631c848b2243f31cbf93cce1a65779e5f5" 
				data-orientations="Right" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Ritual - John Doe</option>
			  
			  <option value="skowl" 
			  	data-policy-ids="d38910b4b5bd3e634138dc027b507b52406acf687889e3719aa4f7cf" 
				data-orientations="Left" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Skowl - Derivative Heroes</option>
		  </optgroup>
  		  <optgroup label="Partner Project Themes">
	   		  <option value="discosolaris" 
			  	data-policy-ids="9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99" 
				data-orientations="Right" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Disco Solaris - Moebius Pioneers</option>
				
			  <option value="oculuslounge" 
			  	data-policy-ids="d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779" 
				data-orientations="Left" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Disco Solaris - Oculus Lounge</option>
		  </optgroup>
		  <optgroup label="Rugged Project Themes">
			  <option value="adapunks" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">ADA Punks</option>
		  </optgroup>
		  <?php
		  if($innercircle){?>
		  <optgroup label="Inner Circle Top Secret Themes">
			  <option value="occultarchives" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Billy Martin - Occult Archives</option>
			  <option value="rubberrebels" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Classic Cardtoons - Rubber Rebels</option>
			  <option value="danketsu" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Danketsu - Legends</option>
			  
			  <option value="deadpophell" 
			  	data-policy-ids="6710d32c862a616ba81ef00294e60fe56969949e0225452c48b5f0ed" 
				data-orientations="Right" 
				data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				Dead Pop Hell - NSFW</option>
				
			  <option value="havocworlds" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Havoc Worlds - Season 1</option>
			  <option value="karranka" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Karranka - Badass Heroes</option>
			  <option value="karranka2" data-policy-ids="" data-orientations="" data-ipfs-prefixes="">Karranka - Japanese Ghosts: Legendary Warriors</option>
			  
			  <option value="omen" 
				  data-policy-ids="da286f15e0de865e3d50fec6fa0484d7e2309671dc4ba8ce6bdd122b" 
				  data-orientations="Right" 
				  data-ipfs-prefixes="https://ipfs5.jpgstoreapis.com/ipfs/">
				  Nemonium - Omen Legends</option>
	      </optgroup>
		  <?php }
		  ?>
	    </select>
	  </div>
	  <p><a href="https://www.jpg.store/collection/monstrocity" target="_blank">Purchase Monstrocity NFTs</a> to Add More Characters</p>
	  <p><a href="https://www.skulliance.io/staking" target="_blank">Visit Skulliance Staking</a> to Connect Wallet(s) and Load in Qualifying NFTs</p>
	  <p>Rewards, Leaderboards, and Game Saves Available to Skulliance Stakers</p>
      <div id="character-options"></div>
    </div>
  <script>
const _0x59433c=_0x4623;(function(_0x39c380,_0x16d195){const _0x7b356e=_0x4623,_0x10d7dd=_0x39c380();while(!![]){try{const _0x4164ea=parseInt(_0x7b356e(0x1c8))/0x1*(parseInt(_0x7b356e(0x1a8))/0x2)+-parseInt(_0x7b356e(0x26c))/0x3*(parseInt(_0x7b356e(0x227))/0x4)+-parseInt(_0x7b356e(0x16b))/0x5+parseInt(_0x7b356e(0x6f))/0x6+parseInt(_0x7b356e(0x1fe))/0x7+parseInt(_0x7b356e(0x1cd))/0x8+parseInt(_0x7b356e(0x258))/0x9*(parseInt(_0x7b356e(0x10c))/0xa);if(_0x4164ea===_0x16d195)break;else _0x10d7dd['push'](_0x10d7dd['shift']());}catch(_0x4048db){_0x10d7dd['push'](_0x10d7dd['shift']());}}}(_0x4fe1,0x87a0b));const opponentsConfig=[{'name':_0x59433c(0x1d6),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x59433c(0x1ff),'type':_0x59433c(0x188),'powerup':'Minor\x20Regen','theme':_0x59433c(0x21f)},{'name':_0x59433c(0xa5),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Large','type':'Base','powerup':'Minor\x20Regen','theme':_0x59433c(0x21f)},{'name':_0x59433c(0x17d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x59433c(0xce),'type':_0x59433c(0x188),'powerup':_0x59433c(0x1c4),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x1bd),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x59433c(0x1ff),'type':'Base','powerup':'Minor\x20Regen','theme':'monstrocity'},{'name':_0x59433c(0x12a),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x59433c(0x1ff),'type':_0x59433c(0x188),'powerup':_0x59433c(0xb1),'theme':'monstrocity'},{'name':_0x59433c(0x8f),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x59433c(0x1ff),'type':_0x59433c(0x188),'powerup':_0x59433c(0xb1),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x23c),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Small','type':'Base','powerup':'Regenerate','theme':_0x59433c(0x21f)},{'name':_0x59433c(0x135),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x59433c(0x1ff),'type':_0x59433c(0x188),'powerup':_0x59433c(0xb1),'theme':'monstrocity'},{'name':_0x59433c(0x20a),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x59433c(0x1ff),'type':'Base','powerup':_0x59433c(0x140),'theme':'monstrocity'},{'name':'Jarhead','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x59433c(0x1ff),'type':_0x59433c(0x188),'powerup':_0x59433c(0x140),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x19c),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x59433c(0xce),'type':_0x59433c(0x188),'powerup':_0x59433c(0x21a),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x26a),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x59433c(0x1b8),'type':'Base','powerup':_0x59433c(0x21a),'theme':_0x59433c(0x21f)},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x59433c(0x1ff),'type':_0x59433c(0x188),'powerup':_0x59433c(0x21a),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x1b4),'strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x59433c(0x188),'powerup':_0x59433c(0x21a),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x1d6),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':_0x59433c(0x148),'powerup':_0x59433c(0x1c4),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0xa5),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Large','type':_0x59433c(0x148),'powerup':_0x59433c(0x1c4),'theme':'monstrocity'},{'name':_0x59433c(0x17d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x59433c(0xce),'type':_0x59433c(0x148),'powerup':_0x59433c(0x1c4),'theme':_0x59433c(0x21f)},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x59433c(0x1ff),'type':_0x59433c(0x148),'powerup':_0x59433c(0x6e),'theme':'monstrocity'},{'name':_0x59433c(0x12a),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x59433c(0x148),'powerup':'Regenerate','theme':_0x59433c(0x21f)},{'name':_0x59433c(0x8f),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x59433c(0x148),'powerup':'Regenerate','theme':_0x59433c(0x21f)},{'name':_0x59433c(0x23c),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Small','type':_0x59433c(0x148),'powerup':'Regenerate','theme':_0x59433c(0x21f)},{'name':_0x59433c(0x135),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0x59433c(0x148),'powerup':_0x59433c(0xb1),'theme':_0x59433c(0x21f)},{'name':_0x59433c(0x20a),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x59433c(0x1ff),'type':'Leader','powerup':'Boost\x20Attack','theme':_0x59433c(0x21f)},{'name':'Jarhead','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x59433c(0x1ff),'type':_0x59433c(0x148),'powerup':_0x59433c(0x140),'theme':_0x59433c(0x21f)},{'name':'Spydrax','strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x59433c(0x148),'powerup':'Heal','theme':'monstrocity'},{'name':_0x59433c(0x26a),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x59433c(0x1b8),'type':_0x59433c(0x148),'powerup':'Heal','theme':_0x59433c(0x21f)},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x59433c(0x1ff),'type':'Leader','powerup':_0x59433c(0x21a),'theme':'monstrocity'},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x59433c(0x148),'powerup':_0x59433c(0x21a),'theme':_0x59433c(0x21f)}],characterDirections={'Billandar\x20and\x20Ted':'Left','Craig':_0x59433c(0x167),'Dankle':'Left','Drake':_0x59433c(0x268),'Goblin\x20Ganger':_0x59433c(0x167),'Jarhead':_0x59433c(0x268),'Katastrophy':_0x59433c(0x268),'Koipon':_0x59433c(0x167),'Mandiblus':_0x59433c(0x167),'Merdock':_0x59433c(0x167),'Ouchie':_0x59433c(0x167),'Slime\x20Mind':_0x59433c(0x268),'Spydrax':_0x59433c(0x268),'Texby':_0x59433c(0x167)};class MonstrocityMatch3{constructor(_0x4a57ad,_0x181a94){const _0x3614a2=_0x59433c;this[_0x3614a2(0x1e2)]=_0x3614a2(0x8e)in window||navigator[_0x3614a2(0x115)]>0x0||navigator[_0x3614a2(0xc3)]>0x0,this[_0x3614a2(0xf8)]=0x5,this[_0x3614a2(0x71)]=0x5,this['board']=[],this[_0x3614a2(0x147)]=null,this[_0x3614a2(0xad)]=![],this[_0x3614a2(0x104)]=null,this['player1']=null,this[_0x3614a2(0x13d)]=null,this[_0x3614a2(0xd1)]=_0x3614a2(0x1b6),this['isDragging']=![],this[_0x3614a2(0x15f)]=null,this['dragDirection']=null,this[_0x3614a2(0x11c)]=0x0,this[_0x3614a2(0x16a)]=0x0,this[_0x3614a2(0x19d)]=0x1,this['playerCharactersConfig']=_0x4a57ad,this[_0x3614a2(0x111)]=[],this['isCheckingGameOver']=![],this['tileTypes']=[_0x3614a2(0x1e3),_0x3614a2(0x256),_0x3614a2(0x153),_0x3614a2(0x14a),_0x3614a2(0x1eb)],this[_0x3614a2(0x263)]=[],this['grandTotalScore']=0x0,this[_0x3614a2(0x205)]=localStorage[_0x3614a2(0x9c)](_0x3614a2(0x9f))||_0x181a94||_0x3614a2(0x21f),this[_0x3614a2(0x1a0)]=_0x3614a2(0xc6)+this[_0x3614a2(0x205)]+'/',this[_0x3614a2(0x138)]={'match':new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),'cascade':new Audio(_0x3614a2(0x146)),'badMove':new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),'gameOver':new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),'reset':new Audio(_0x3614a2(0x97)),'loss':new Audio('https://www.skulliance.io/staking/sounds/skullcoinlose.ogg'),'win':new Audio(_0x3614a2(0x1f7)),'finalWin':new Audio(_0x3614a2(0xe0)),'powerGem':new Audio('https://www.skulliance.io/staking/sounds/powergem_created.ogg'),'hyperCube':new Audio(_0x3614a2(0xf4)),'multiMatch':new Audio('https://www.skulliance.io/staking/sounds/speedmatch1.ogg')},this[_0x3614a2(0x141)](),this[_0x3614a2(0x1ad)](),this[_0x3614a2(0xb5)]();}async[_0x59433c(0x131)](){const _0x266b4e=_0x59433c;console[_0x266b4e(0x105)](_0x266b4e(0x1a5)),this[_0x266b4e(0x111)]=this[_0x266b4e(0x218)][_0x266b4e(0xe6)](_0x1e77f5=>this[_0x266b4e(0x133)](_0x1e77f5)),await this[_0x266b4e(0x216)](!![]);const _0x23ebf6=await this[_0x266b4e(0x119)](),{loadedLevel:_0x3b6f36,loadedScore:_0x35b326,hasProgress:_0x582211}=_0x23ebf6;if(_0x582211){console[_0x266b4e(0x105)](_0x266b4e(0x165)+_0x3b6f36+_0x266b4e(0x1cb)+_0x35b326);const _0x426219=await this['showProgressPopup'](_0x3b6f36,_0x35b326);_0x426219?(this[_0x266b4e(0x19d)]=_0x3b6f36,this[_0x266b4e(0x16c)]=_0x35b326,log('Resumed\x20at\x20Level\x20'+this['currentLevel']+_0x266b4e(0x240)+this[_0x266b4e(0x16c)])):(this[_0x266b4e(0x19d)]=0x1,this[_0x266b4e(0x16c)]=0x0,await this[_0x266b4e(0x156)](),log('Starting\x20fresh\x20at\x20Level\x201'));}else this[_0x266b4e(0x19d)]=0x1,this['grandTotalScore']=0x0,log(_0x266b4e(0x191));console[_0x266b4e(0x105)](_0x266b4e(0x169));}['setBackground'](){const _0x5d9d13=_0x59433c;document[_0x5d9d13(0x183)][_0x5d9d13(0x13b)][_0x5d9d13(0x9e)]='url('+this[_0x5d9d13(0x1a0)]+'monstrocity.png)';}[_0x59433c(0x1dc)](_0x3b18cf){const _0x296efc=_0x59433c;var _0x23b277=this;this[_0x296efc(0x205)]=_0x3b18cf,this['baseImagePath']=_0x296efc(0xc6)+this[_0x296efc(0x205)]+'/',localStorage['setItem'](_0x296efc(0x9f),this['theme']),this[_0x296efc(0xb5)](),getAssets(this[_0x296efc(0x205)])[_0x296efc(0x109)](function(_0x12b3ce){const _0x306220=_0x296efc;_0x23b277[_0x306220(0x218)]=_0x12b3ce,_0x23b277['playerCharacters']=_0x23b277['playerCharactersConfig'][_0x306220(0xe6)](function(_0x1afdfa){const _0x4f80f4=_0x306220;return _0x23b277[_0x4f80f4(0x133)](_0x1afdfa);});if(_0x23b277[_0x306220(0xf0)]){var _0x581fa6=_0x23b277['playerCharactersConfig'][_0x306220(0x1f0)](function(_0xfeaa94){const _0x15a627=_0x306220;return _0xfeaa94[_0x15a627(0x1ec)]===_0x23b277[_0x15a627(0xf0)][_0x15a627(0x1ec)];})||_0x23b277['playerCharactersConfig'][0x0];_0x23b277[_0x306220(0xf0)]=_0x23b277['createCharacter'](_0x581fa6),_0x23b277[_0x306220(0x142)]();}_0x23b277[_0x306220(0x13d)]&&(_0x23b277[_0x306220(0x13d)]=_0x23b277['createCharacter'](opponentsConfig[_0x23b277[_0x306220(0x19d)]-0x1]),_0x23b277['updateOpponentDisplay']());document[_0x306220(0xff)]('.game-logo')[_0x306220(0x10b)]=_0x23b277[_0x306220(0x1a0)]+_0x306220(0x1ba);var _0x53e501=document['getElementById'](_0x306220(0x235));_0x53e501['style'][_0x306220(0x13c)]===_0x306220(0x221)&&_0x23b277[_0x306220(0x216)](_0x23b277['player1']===null);})[_0x296efc(0xa4)](function(_0x50e0f4){const _0x2adc57=_0x296efc;console[_0x2adc57(0x6a)](_0x2adc57(0x252),_0x50e0f4);});}async[_0x59433c(0xfb)](){const _0x57c8a1=_0x59433c,_0x276828={'currentLevel':this[_0x57c8a1(0x19d)],'grandTotalScore':this[_0x57c8a1(0x16c)]};console[_0x57c8a1(0x105)](_0x57c8a1(0x1dd),_0x276828);try{const _0x4ea60c=await fetch('ajax/save-monstrocity-progress.php',{'method':'POST','headers':{'Content-Type':_0x57c8a1(0x1f6)},'body':JSON[_0x57c8a1(0x7d)](_0x276828)});console[_0x57c8a1(0x105)](_0x57c8a1(0xaf),_0x4ea60c['status']);const _0x239f55=await _0x4ea60c['text']();console[_0x57c8a1(0x105)](_0x57c8a1(0x254),_0x239f55);if(!_0x4ea60c['ok'])throw new Error(_0x57c8a1(0x23d)+_0x4ea60c[_0x57c8a1(0x1ab)]);const _0x2f9303=JSON[_0x57c8a1(0x16f)](_0x239f55);console[_0x57c8a1(0x105)](_0x57c8a1(0x178),_0x2f9303),_0x2f9303['status']==='success'?log(_0x57c8a1(0x1e7)+this[_0x57c8a1(0x19d)]):console[_0x57c8a1(0x6a)](_0x57c8a1(0x1c7),_0x2f9303[_0x57c8a1(0x122)]);}catch(_0x6d3e2b){console[_0x57c8a1(0x6a)](_0x57c8a1(0x1a3),_0x6d3e2b);}}async[_0x59433c(0x119)](){const _0x2fbb91=_0x59433c;try{console[_0x2fbb91(0x105)]('Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php');const _0x53aa7f=await fetch('ajax/load-monstrocity-progress.php',{'method':'GET','headers':{'Content-Type':_0x2fbb91(0x1f6)}});console['log'](_0x2fbb91(0xaf),_0x53aa7f[_0x2fbb91(0x1ab)]);if(!_0x53aa7f['ok'])throw new Error(_0x2fbb91(0x23d)+_0x53aa7f[_0x2fbb91(0x1ab)]);const _0x58f05b=await _0x53aa7f[_0x2fbb91(0x24e)]();console[_0x2fbb91(0x105)](_0x2fbb91(0x178),_0x58f05b);if(_0x58f05b[_0x2fbb91(0x1ab)]===_0x2fbb91(0x22a)&&_0x58f05b[_0x2fbb91(0x116)]){const _0x29e886=_0x58f05b['progress'];return{'loadedLevel':_0x29e886[_0x2fbb91(0x19d)]||0x1,'loadedScore':_0x29e886[_0x2fbb91(0x16c)]||0x0,'hasProgress':!![]};}else return console[_0x2fbb91(0x105)]('No\x20progress\x20found\x20or\x20status\x20not\x20success:',_0x58f05b),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0xabe375){return console[_0x2fbb91(0x6a)](_0x2fbb91(0x8c),_0xabe375),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x59433c(0x156)](){const _0x3751f8=_0x59433c;try{const _0x57e10c=await fetch(_0x3751f8(0x16d),{'method':_0x3751f8(0x174),'headers':{'Content-Type':_0x3751f8(0x1f6)}});if(!_0x57e10c['ok'])throw new Error(_0x3751f8(0x23d)+_0x57e10c[_0x3751f8(0x1ab)]);const _0x4d9606=await _0x57e10c['json']();_0x4d9606[_0x3751f8(0x1ab)]==='success'&&(this['currentLevel']=0x1,this['grandTotalScore']=0x0,log(_0x3751f8(0x206)));}catch(_0x52ad53){console[_0x3751f8(0x6a)](_0x3751f8(0x1e9),_0x52ad53);}}['updateTileSizeWithGap'](){const _0x298a9e=_0x59433c,_0x9ba159=document[_0x298a9e(0x21b)](_0x298a9e(0x184)),_0xc0913d=_0x9ba159['offsetWidth']||0x12c;this[_0x298a9e(0x209)]=(_0xc0913d-0.5*(this[_0x298a9e(0xf8)]-0x1))/this[_0x298a9e(0xf8)];}[_0x59433c(0x133)](_0x522835){const _0x50f691=_0x59433c;console[_0x50f691(0x105)]('createCharacter:\x20config=',_0x522835);var _0x5590cf,_0x10567a,_0x523074='Left',_0x23b832=![];if(_0x522835[_0x50f691(0x266)]&&_0x522835[_0x50f691(0x25f)]){_0x23b832=!![];var _0x39e7da=document[_0x50f691(0xff)](_0x50f691(0x181)+_0x522835[_0x50f691(0x205)]+'\x22]'),_0x3ce566={'orientation':_0x50f691(0x268),'ipfsPrefix':_0x50f691(0x86)};if(_0x39e7da){var _0x3a588b=_0x39e7da['dataset'][_0x50f691(0x85)]?_0x39e7da[_0x50f691(0x1d1)][_0x50f691(0x85)][_0x50f691(0x6c)](',')[_0x50f691(0x1c3)](function(_0x664858){return _0x664858['trim']();}):[],_0x4a95c2=_0x39e7da[_0x50f691(0x1d1)]['orientations']?_0x39e7da['dataset'][_0x50f691(0x1a2)][_0x50f691(0x6c)](',')[_0x50f691(0x1c3)](function(_0x13b321){return _0x13b321['trim']();}):[],_0x4b7a93=_0x39e7da['dataset'][_0x50f691(0xcd)]?_0x39e7da[_0x50f691(0x1d1)][_0x50f691(0xcd)][_0x50f691(0x6c)](',')['filter'](function(_0x3befd8){const _0x57b120=_0x50f691;return _0x3befd8[_0x57b120(0x248)]();}):[],_0x3c8014=_0x3a588b[_0x50f691(0x22d)](_0x522835[_0x50f691(0x25f)]);_0x3c8014!==-0x1&&(_0x3ce566={'orientation':_0x4a95c2[_0x50f691(0x1c0)]===0x1?_0x4a95c2[0x0]:_0x4a95c2[_0x3c8014]||_0x50f691(0x268),'ipfsPrefix':_0x4b7a93['length']===0x1?_0x4b7a93[0x0]:_0x4b7a93[_0x3c8014]||_0x50f691(0x86)});}_0x3ce566[_0x50f691(0x89)]===_0x50f691(0xdb)?_0x523074=Math[_0x50f691(0x21d)]()<0.5?_0x50f691(0x167):_0x50f691(0x268):_0x523074=_0x3ce566[_0x50f691(0x89)],_0x10567a=_0x3ce566[_0x50f691(0x257)]+_0x522835[_0x50f691(0x266)];}else{switch(_0x522835[_0x50f691(0x201)]){case _0x50f691(0x188):_0x5590cf=_0x50f691(0xb4);break;case _0x50f691(0x148):_0x5590cf=_0x50f691(0xb9);break;case _0x50f691(0x211):_0x5590cf=_0x50f691(0x230);break;default:_0x5590cf='base';}_0x10567a=this[_0x50f691(0x1a0)]+_0x5590cf+'/'+_0x522835[_0x50f691(0x1ec)][_0x50f691(0x17a)]()[_0x50f691(0x118)](/ /g,'-')+_0x50f691(0x1ce),_0x523074=characterDirections[_0x522835[_0x50f691(0x1ec)]]||'Left';}var _0x4d9c6a;switch(_0x522835['type']){case _0x50f691(0x148):_0x4d9c6a=0x64;break;case'Battle\x20Damaged':_0x4d9c6a=0x46;break;case _0x50f691(0x188):default:_0x4d9c6a=0x55;}var _0x4bb3c5=0x1,_0x56a0bd=0x0;switch(_0x522835['size']){case _0x50f691(0x1b8):_0x4bb3c5=1.2,_0x56a0bd=_0x522835[_0x50f691(0xd3)]>0x1?-0x2:0x0;break;case'Small':_0x4bb3c5=0.8,_0x56a0bd=_0x522835[_0x50f691(0xd3)]<0x6?0x2:0x7-_0x522835[_0x50f691(0xd3)];break;case _0x50f691(0x1ff):_0x4bb3c5=0x1,_0x56a0bd=0x0;break;}var _0x16bb87=Math[_0x50f691(0x1ea)](_0x4d9c6a*_0x4bb3c5),_0x373b2c=Math[_0x50f691(0x90)](0x1,Math[_0x50f691(0x14d)](0x7,_0x522835['tactics']+_0x56a0bd));return{'name':_0x522835[_0x50f691(0x1ec)],'type':_0x522835[_0x50f691(0x201)],'strength':_0x522835[_0x50f691(0xd7)],'speed':_0x522835[_0x50f691(0x81)],'tactics':_0x373b2c,'size':_0x522835[_0x50f691(0x234)],'powerup':_0x522835[_0x50f691(0x20f)],'health':_0x16bb87,'maxHealth':_0x16bb87,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x10567a,'orientation':_0x523074,'isNFT':_0x23b832};}[_0x59433c(0x139)](_0x565e05,_0x54a22b,_0x4497d5=![]){const _0x14b2a5=_0x59433c;_0x565e05[_0x14b2a5(0x89)]===_0x14b2a5(0x167)?(_0x565e05[_0x14b2a5(0x89)]=_0x14b2a5(0x268),_0x54a22b[_0x14b2a5(0x13b)]['transform']=_0x4497d5?_0x14b2a5(0xa1):'none'):(_0x565e05['orientation']='Left',_0x54a22b[_0x14b2a5(0x13b)][_0x14b2a5(0x18e)]=_0x4497d5?'none':'scaleX(-1)'),log(_0x565e05[_0x14b2a5(0x1ec)]+_0x14b2a5(0x203)+_0x565e05['orientation']+'!');}['showCharacterSelect'](_0x5fc22e){const _0x25f600=_0x59433c;var _0x39995d=this;console['log'](_0x25f600(0x267)+_0x5fc22e);var _0x18e318=document[_0x25f600(0x21b)](_0x25f600(0x235)),_0x253945=document[_0x25f600(0x21b)](_0x25f600(0x159)),_0x262f7c=document['getElementById'](_0x25f600(0x1e5));_0x253945[_0x25f600(0x1d8)]='',_0x18e318[_0x25f600(0x13b)][_0x25f600(0x13c)]=_0x25f600(0x221),_0x262f7c['value']=this[_0x25f600(0x205)],_0x262f7c['onchange']=function(){const _0x21eaac=_0x25f600;_0x39995d[_0x21eaac(0x1dc)](_0x262f7c[_0x21eaac(0x168)]);},this[_0x25f600(0x111)][_0x25f600(0x161)](function(_0x1424d1,_0x22b1ca){const _0xf106f1=_0x25f600;var _0x528ce6=document[_0xf106f1(0x223)](_0xf106f1(0x1a9));_0x528ce6[_0xf106f1(0x25c)]=_0xf106f1(0x15a),_0x528ce6[_0xf106f1(0x1d8)]=_0xf106f1(0x113)+_0x1424d1[_0xf106f1(0x1ef)]+'\x22\x20alt=\x22'+_0x1424d1[_0xf106f1(0x1ec)]+'\x22>'+'<p><strong>'+_0x1424d1[_0xf106f1(0x1ec)]+_0xf106f1(0xf1)+_0xf106f1(0x23a)+_0x1424d1[_0xf106f1(0x201)]+_0xf106f1(0x207)+'<p>Health:\x20'+_0x1424d1[_0xf106f1(0x163)]+_0xf106f1(0x207)+_0xf106f1(0x1f8)+_0x1424d1['strength']+_0xf106f1(0x207)+_0xf106f1(0x132)+_0x1424d1[_0xf106f1(0x81)]+'</p>'+_0xf106f1(0x26e)+_0x1424d1[_0xf106f1(0xd3)]+_0xf106f1(0x207)+_0xf106f1(0xd6)+_0x1424d1[_0xf106f1(0x234)]+'</p>'+_0xf106f1(0x96)+_0x1424d1['powerup']+_0xf106f1(0x207),_0x528ce6[_0xf106f1(0x175)](_0xf106f1(0x126),function(){const _0x5734d5=_0xf106f1;console[_0x5734d5(0x105)]('showCharacterSelect:\x20Character\x20selected:\x20'+_0x1424d1[_0x5734d5(0x1ec)]),_0x18e318[_0x5734d5(0x13b)][_0x5734d5(0x13c)]=_0x5734d5(0x172),_0x5fc22e?(_0x39995d[_0x5734d5(0xf0)]={'name':_0x1424d1[_0x5734d5(0x1ec)],'type':_0x1424d1[_0x5734d5(0x201)],'strength':_0x1424d1[_0x5734d5(0xd7)],'speed':_0x1424d1[_0x5734d5(0x81)],'tactics':_0x1424d1[_0x5734d5(0xd3)],'size':_0x1424d1[_0x5734d5(0x234)],'powerup':_0x1424d1[_0x5734d5(0x20f)],'health':_0x1424d1['health'],'maxHealth':_0x1424d1['maxHealth'],'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x1424d1['imageUrl'],'orientation':_0x1424d1[_0x5734d5(0x89)],'isNFT':_0x1424d1['isNFT']},console[_0x5734d5(0x105)](_0x5734d5(0xab)+_0x39995d['player1']['name']),_0x39995d[_0x5734d5(0xed)]()):_0x39995d['swapPlayerCharacter'](_0x1424d1);}),_0x253945['appendChild'](_0x528ce6);});}[_0x59433c(0x10d)](_0x275bbc){const _0x4ac3b9=_0x59433c,_0x4188f3=this[_0x4ac3b9(0xf0)][_0x4ac3b9(0x100)],_0x28d7c8=this[_0x4ac3b9(0xf0)]['maxHealth'],_0x54d2e5={..._0x275bbc},_0x5a5930=Math[_0x4ac3b9(0x14d)](0x1,_0x4188f3/_0x28d7c8);_0x54d2e5[_0x4ac3b9(0x100)]=Math['round'](_0x54d2e5['maxHealth']*_0x5a5930),_0x54d2e5[_0x4ac3b9(0x100)]=Math['max'](0x0,Math[_0x4ac3b9(0x14d)](_0x54d2e5['maxHealth'],_0x54d2e5['health'])),_0x54d2e5[_0x4ac3b9(0x239)]=![],_0x54d2e5[_0x4ac3b9(0x15c)]=0x0,_0x54d2e5[_0x4ac3b9(0x1d3)]=![],this[_0x4ac3b9(0xf0)]=_0x54d2e5,this[_0x4ac3b9(0x142)](),this['updateHealth'](this[_0x4ac3b9(0xf0)]),log(this[_0x4ac3b9(0xf0)][_0x4ac3b9(0x1ec)]+_0x4ac3b9(0xea)+this[_0x4ac3b9(0xf0)][_0x4ac3b9(0x100)]+'/'+this[_0x4ac3b9(0xf0)][_0x4ac3b9(0x163)]+_0x4ac3b9(0x192)),this[_0x4ac3b9(0x104)]=this[_0x4ac3b9(0xf0)]['speed']>this[_0x4ac3b9(0x13d)][_0x4ac3b9(0x81)]?this['player1']:this[_0x4ac3b9(0x13d)][_0x4ac3b9(0x81)]>this['player1'][_0x4ac3b9(0x81)]?this[_0x4ac3b9(0x13d)]:this[_0x4ac3b9(0xf0)][_0x4ac3b9(0xd7)]>=this[_0x4ac3b9(0x13d)][_0x4ac3b9(0xd7)]?this[_0x4ac3b9(0xf0)]:this[_0x4ac3b9(0x13d)],turnIndicator[_0x4ac3b9(0xc7)]=_0x4ac3b9(0x1f2)+this[_0x4ac3b9(0x19d)]+_0x4ac3b9(0x10a)+(this['currentTurn']===this['player1']?'Player':'Opponent')+_0x4ac3b9(0x1b5),this[_0x4ac3b9(0x104)]===this['player2']&&this[_0x4ac3b9(0xd1)]!==_0x4ac3b9(0xad)&&setTimeout(()=>this[_0x4ac3b9(0x1fd)](),0x3e8);}[_0x59433c(0x120)](_0x3748c7,_0x10f1d7){const _0x8c6fe5=_0x59433c;return console[_0x8c6fe5(0x105)](_0x8c6fe5(0x236)+_0x3748c7+_0x8c6fe5(0x12f)+_0x10f1d7),new Promise(_0x59af35=>{const _0x4245da=_0x8c6fe5,_0x2f1901=document[_0x4245da(0x223)](_0x4245da(0x1a9));_0x2f1901['id']=_0x4245da(0x11e),_0x2f1901[_0x4245da(0x25c)]=_0x4245da(0x11e);const _0x37104e=document[_0x4245da(0x223)](_0x4245da(0x1a9));_0x37104e[_0x4245da(0x25c)]=_0x4245da(0xa0);const _0x9e8d9d=document[_0x4245da(0x223)]('p');_0x9e8d9d['id']='progress-message',_0x9e8d9d[_0x4245da(0xc7)]=_0x4245da(0x231)+_0x3748c7+_0x4245da(0x212)+_0x10f1d7+'?',_0x37104e[_0x4245da(0x12d)](_0x9e8d9d);const _0x5aedc2=document[_0x4245da(0x223)](_0x4245da(0x1a9));_0x5aedc2[_0x4245da(0x25c)]=_0x4245da(0x18c);const _0x5e67d6=document[_0x4245da(0x223)](_0x4245da(0xb0));_0x5e67d6['id']='progress-resume',_0x5e67d6['textContent']='Resume',_0x5aedc2[_0x4245da(0x12d)](_0x5e67d6);const _0x11c154=document[_0x4245da(0x223)]('button');_0x11c154['id']='progress-start-fresh',_0x11c154[_0x4245da(0xc7)]=_0x4245da(0x82),_0x5aedc2[_0x4245da(0x12d)](_0x11c154),_0x37104e[_0x4245da(0x12d)](_0x5aedc2),_0x2f1901[_0x4245da(0x12d)](_0x37104e),document[_0x4245da(0x183)][_0x4245da(0x12d)](_0x2f1901),_0x2f1901[_0x4245da(0x13b)][_0x4245da(0x13c)]=_0x4245da(0xf2);const _0x3e5c4a=()=>{const _0x12be22=_0x4245da;console[_0x12be22(0x105)](_0x12be22(0x99)),_0x2f1901[_0x12be22(0x13b)][_0x12be22(0x13c)]=_0x12be22(0x172),document['body'][_0x12be22(0x1f1)](_0x2f1901),_0x5e67d6[_0x12be22(0x1de)](_0x12be22(0x126),_0x3e5c4a),_0x11c154[_0x12be22(0x1de)](_0x12be22(0x126),_0x3c2d54),_0x59af35(!![]);},_0x3c2d54=()=>{const _0x1cc1c0=_0x4245da;console[_0x1cc1c0(0x105)](_0x1cc1c0(0x1db)),_0x2f1901['style'][_0x1cc1c0(0x13c)]=_0x1cc1c0(0x172),document['body']['removeChild'](_0x2f1901),_0x5e67d6[_0x1cc1c0(0x1de)](_0x1cc1c0(0x126),_0x3e5c4a),_0x11c154[_0x1cc1c0(0x1de)](_0x1cc1c0(0x126),_0x3c2d54),_0x59af35(![]);};_0x5e67d6[_0x4245da(0x175)](_0x4245da(0x126),_0x3e5c4a),_0x11c154['addEventListener'](_0x4245da(0x126),_0x3c2d54);});}[_0x59433c(0xed)](){const _0x10358d=_0x59433c;var _0xed3ee1=this;console['log']('initGame:\x20Started\x20with\x20this.currentLevel='+this[_0x10358d(0x19d)]);var _0x3fbe8a=document[_0x10358d(0xff)](_0x10358d(0x66)),_0x639c05=document[_0x10358d(0x21b)](_0x10358d(0x184));_0x3fbe8a['style'][_0x10358d(0x13c)]='block',_0x639c05['style'][_0x10358d(0x171)]=_0x10358d(0xaa),this[_0x10358d(0x138)][_0x10358d(0x11a)][_0x10358d(0xdf)](),log(_0x10358d(0x101)+this[_0x10358d(0x19d)]+_0x10358d(0x106)),this[_0x10358d(0x13d)]=this[_0x10358d(0x133)](opponentsConfig[this[_0x10358d(0x19d)]-0x1]),console[_0x10358d(0x105)](_0x10358d(0x19f)+this[_0x10358d(0x19d)]+':\x20'+this[_0x10358d(0x13d)][_0x10358d(0x1ec)]+_0x10358d(0x17b)+(this[_0x10358d(0x19d)]-0x1)+'])'),this[_0x10358d(0xf0)][_0x10358d(0x100)]=this[_0x10358d(0xf0)][_0x10358d(0x163)],this['currentTurn']=this[_0x10358d(0xf0)][_0x10358d(0x81)]>this[_0x10358d(0x13d)][_0x10358d(0x81)]?this[_0x10358d(0xf0)]:this[_0x10358d(0x13d)]['speed']>this[_0x10358d(0xf0)]['speed']?this[_0x10358d(0x13d)]:this['player1'][_0x10358d(0xd7)]>=this[_0x10358d(0x13d)][_0x10358d(0xd7)]?this[_0x10358d(0xf0)]:this[_0x10358d(0x13d)],this[_0x10358d(0xd1)]=_0x10358d(0x1b6),this[_0x10358d(0xad)]=![],this[_0x10358d(0x263)]=[],p1Image['classList'][_0x10358d(0x108)](_0x10358d(0x17e),_0x10358d(0x233)),p2Image[_0x10358d(0x79)][_0x10358d(0x108)]('winner','loser'),this['updatePlayerDisplay'](),this['updateOpponentDisplay'](),p1Image[_0x10358d(0x13b)][_0x10358d(0x18e)]=this[_0x10358d(0xf0)]['orientation']==='Left'?_0x10358d(0xa1):'none',p2Image[_0x10358d(0x13b)][_0x10358d(0x18e)]=this[_0x10358d(0x13d)][_0x10358d(0x89)]==='Right'?'scaleX(-1)':_0x10358d(0x172),this[_0x10358d(0x73)](this[_0x10358d(0xf0)]),this[_0x10358d(0x73)](this[_0x10358d(0x13d)]),battleLog['innerHTML']='',gameOver[_0x10358d(0xc7)]='',this[_0x10358d(0xf0)][_0x10358d(0x234)]!=='Medium'&&log(this['player1']['name']+_0x10358d(0x269)+this[_0x10358d(0xf0)][_0x10358d(0x234)]+_0x10358d(0xbb)+(this[_0x10358d(0xf0)]['size']===_0x10358d(0x1b8)?_0x10358d(0x18a)+this[_0x10358d(0xf0)][_0x10358d(0x163)]+_0x10358d(0x186)+this['player1']['tactics']:_0x10358d(0xfd)+this[_0x10358d(0xf0)][_0x10358d(0x163)]+_0x10358d(0x199)+this[_0x10358d(0xf0)]['tactics'])+'!'),this[_0x10358d(0x13d)][_0x10358d(0x234)]!==_0x10358d(0x1ff)&&log(this[_0x10358d(0x13d)][_0x10358d(0x1ec)]+_0x10358d(0x269)+this['player2']['size']+_0x10358d(0xbb)+(this[_0x10358d(0x13d)][_0x10358d(0x234)]==='Large'?'boosts\x20health\x20to\x20'+this[_0x10358d(0x13d)][_0x10358d(0x163)]+_0x10358d(0x186)+this[_0x10358d(0x13d)]['tactics']:_0x10358d(0xfd)+this[_0x10358d(0x13d)]['maxHealth']+_0x10358d(0x199)+this[_0x10358d(0x13d)][_0x10358d(0xd3)])+'!'),log(this['player1'][_0x10358d(0x1ec)]+_0x10358d(0x214)+this[_0x10358d(0xf0)][_0x10358d(0x100)]+'/'+this[_0x10358d(0xf0)][_0x10358d(0x163)]+_0x10358d(0x192)),log(this[_0x10358d(0x104)][_0x10358d(0x1ec)]+_0x10358d(0x7f)),this[_0x10358d(0x210)](),this['gameState']=this[_0x10358d(0x104)]===this[_0x10358d(0xf0)]?_0x10358d(0x124):'aiTurn',turnIndicator[_0x10358d(0xc7)]='Level\x20'+this['currentLevel']+_0x10358d(0x10a)+(this['currentTurn']===this[_0x10358d(0xf0)]?_0x10358d(0x6d):'Opponent')+'\x27s\x20Turn',this[_0x10358d(0x111)]['length']>0x1&&(document['getElementById'](_0x10358d(0xee))[_0x10358d(0x13b)]['display']=_0x10358d(0x1ac)),this[_0x10358d(0x104)]===this[_0x10358d(0x13d)]&&setTimeout(function(){const _0xa64fd2=_0x10358d;_0xed3ee1[_0xa64fd2(0x1fd)]();},0x3e8);}[_0x59433c(0x142)](){const _0x214ea4=_0x59433c;p1Name[_0x214ea4(0xc7)]=this[_0x214ea4(0xf0)][_0x214ea4(0xd8)]||this['theme']==='monstrocity'?this['player1'][_0x214ea4(0x1ec)]:'Player\x201',p1Type[_0x214ea4(0xc7)]=this[_0x214ea4(0xf0)][_0x214ea4(0x201)],p1Strength[_0x214ea4(0xc7)]=this[_0x214ea4(0xf0)][_0x214ea4(0xd7)],p1Speed['textContent']=this[_0x214ea4(0xf0)][_0x214ea4(0x81)],p1Tactics['textContent']=this[_0x214ea4(0xf0)][_0x214ea4(0xd3)],p1Size['textContent']=this['player1'][_0x214ea4(0x234)],p1Powerup['textContent']=this[_0x214ea4(0xf0)][_0x214ea4(0x20f)],p1Image[_0x214ea4(0x10b)]=this[_0x214ea4(0xf0)][_0x214ea4(0x1ef)],p1Image[_0x214ea4(0x13b)][_0x214ea4(0x18e)]=this[_0x214ea4(0xf0)][_0x214ea4(0x89)]===_0x214ea4(0x167)?_0x214ea4(0xa1):_0x214ea4(0x172),p1Image[_0x214ea4(0x164)]=function(){p1Image['style']['display']='block';},p1Hp[_0x214ea4(0xc7)]=this['player1']['health']+'/'+this[_0x214ea4(0xf0)][_0x214ea4(0x163)];}[_0x59433c(0x1e1)](){const _0x44172b=_0x59433c;p2Name[_0x44172b(0xc7)]=this[_0x44172b(0x205)]==='monstrocity'?this[_0x44172b(0x13d)]['name']:_0x44172b(0xa8),p2Type[_0x44172b(0xc7)]=this[_0x44172b(0x13d)]['type'],p2Strength[_0x44172b(0xc7)]=this[_0x44172b(0x13d)][_0x44172b(0xd7)],p2Speed[_0x44172b(0xc7)]=this['player2'][_0x44172b(0x81)],p2Tactics[_0x44172b(0xc7)]=this[_0x44172b(0x13d)][_0x44172b(0xd3)],p2Size[_0x44172b(0xc7)]=this['player2'][_0x44172b(0x234)],p2Powerup[_0x44172b(0xc7)]=this['player2'][_0x44172b(0x20f)],p2Image['src']=this['player2'][_0x44172b(0x1ef)],p2Image['style']['transform']=this[_0x44172b(0x13d)][_0x44172b(0x89)]===_0x44172b(0x268)?_0x44172b(0xa1):'none',p2Image['onload']=function(){const _0x8396f4=_0x44172b;p2Image[_0x8396f4(0x13b)]['display']='block';},p2Hp[_0x44172b(0xc7)]=this[_0x44172b(0x13d)][_0x44172b(0x100)]+'/'+this[_0x44172b(0x13d)][_0x44172b(0x163)];}['initBoard'](){const _0x440955=_0x59433c;this[_0x440955(0x241)]=[];for(let _0x3c6a39=0x0;_0x3c6a39<this[_0x440955(0x71)];_0x3c6a39++){this[_0x440955(0x241)][_0x3c6a39]=[];for(let _0x467fec=0x0;_0x467fec<this[_0x440955(0xf8)];_0x467fec++){let _0x5b0491;do{_0x5b0491=this['createRandomTile']();}while(_0x467fec>=0x2&&this[_0x440955(0x241)][_0x3c6a39][_0x467fec-0x1]?.[_0x440955(0x201)]===_0x5b0491[_0x440955(0x201)]&&this[_0x440955(0x241)][_0x3c6a39][_0x467fec-0x2]?.[_0x440955(0x201)]===_0x5b0491[_0x440955(0x201)]||_0x3c6a39>=0x2&&this[_0x440955(0x241)][_0x3c6a39-0x1]?.[_0x467fec]?.['type']===_0x5b0491[_0x440955(0x201)]&&this[_0x440955(0x241)][_0x3c6a39-0x2]?.[_0x467fec]?.[_0x440955(0x201)]===_0x5b0491['type']);this['board'][_0x3c6a39][_0x467fec]=_0x5b0491;}}this[_0x440955(0x1bc)]();}['createRandomTile'](){const _0x5578c6=_0x59433c;return{'type':randomChoice(this[_0x5578c6(0x121)]),'element':null};}[_0x59433c(0x1bc)](){const _0x10f534=_0x59433c;this[_0x10f534(0x141)]();const _0xce0200=document[_0x10f534(0x21b)](_0x10f534(0x184));_0xce0200['innerHTML']='';for(let _0x1698c6=0x0;_0x1698c6<this[_0x10f534(0x71)];_0x1698c6++){for(let _0x25ac81=0x0;_0x25ac81<this[_0x10f534(0xf8)];_0x25ac81++){const _0x2d05e1=this['board'][_0x1698c6][_0x25ac81];if(_0x2d05e1['type']===null)continue;const _0x7b61d4=document[_0x10f534(0x223)](_0x10f534(0x1a9));_0x7b61d4[_0x10f534(0x25c)]=_0x10f534(0xe2)+_0x2d05e1[_0x10f534(0x201)];if(this[_0x10f534(0xad)])_0x7b61d4[_0x10f534(0x79)]['add']('game-over');const _0x26a930=document[_0x10f534(0x223)](_0x10f534(0x255));_0x26a930['src']=_0x10f534(0xf7)+_0x2d05e1[_0x10f534(0x201)]+_0x10f534(0x1ce),_0x26a930[_0x10f534(0x24a)]=_0x2d05e1['type'],_0x7b61d4['appendChild'](_0x26a930),_0x7b61d4[_0x10f534(0x1d1)]['x']=_0x25ac81,_0x7b61d4[_0x10f534(0x1d1)]['y']=_0x1698c6,_0xce0200[_0x10f534(0x12d)](_0x7b61d4),_0x2d05e1[_0x10f534(0x1aa)]=_0x7b61d4,(!this['isDragging']||this[_0x10f534(0x147)]&&(this['selectedTile']['x']!==_0x25ac81||this[_0x10f534(0x147)]['y']!==_0x1698c6))&&(_0x7b61d4[_0x10f534(0x13b)]['transform']=_0x10f534(0x8d));}}document['getElementById'](_0x10f534(0x264))['style'][_0x10f534(0x13c)]=this[_0x10f534(0xad)]?_0x10f534(0x221):_0x10f534(0x172);}['addEventListeners'](){const _0x35392a=_0x59433c,_0x143eb8=document[_0x35392a(0x21b)](_0x35392a(0x184));this[_0x35392a(0x1e2)]?(_0x143eb8['addEventListener'](_0x35392a(0x22b),_0x404972=>this[_0x35392a(0x144)](_0x404972)),_0x143eb8[_0x35392a(0x175)]('touchmove',_0x56b479=>this[_0x35392a(0x1d7)](_0x56b479)),_0x143eb8[_0x35392a(0x175)]('touchend',_0x2b4866=>this[_0x35392a(0x134)](_0x2b4866))):(_0x143eb8['addEventListener'](_0x35392a(0xa6),_0x17a815=>this[_0x35392a(0x11f)](_0x17a815)),_0x143eb8[_0x35392a(0x175)](_0x35392a(0x93),_0x230909=>this[_0x35392a(0x15d)](_0x230909)),_0x143eb8['addEventListener'](_0x35392a(0x220),_0xb9baa3=>this['handleMouseUp'](_0xb9baa3)));document[_0x35392a(0x21b)](_0x35392a(0x23b))[_0x35392a(0x175)](_0x35392a(0x126),()=>this['handleGameOverButton']()),document[_0x35392a(0x21b)](_0x35392a(0x219))[_0x35392a(0x175)](_0x35392a(0x126),()=>{const _0x2267e5=_0x35392a;this[_0x2267e5(0xed)]();});const _0x224f8a=document[_0x35392a(0x21b)](_0x35392a(0xee)),_0x36faef=document[_0x35392a(0x21b)](_0x35392a(0xa7));_0x224f8a[_0x35392a(0x175)]('click',()=>{const _0x45a124=_0x35392a;console['log'](_0x45a124(0x1e4)),this[_0x45a124(0x216)](![]);}),_0x36faef[_0x35392a(0x175)](_0x35392a(0x126),()=>{const _0x43034e=_0x35392a;console[_0x43034e(0x105)](_0x43034e(0x151)),this[_0x43034e(0x216)](![]);}),document[_0x35392a(0x21b)](_0x35392a(0x1a6))[_0x35392a(0x175)]('click',()=>this[_0x35392a(0x139)](this[_0x35392a(0xf0)],_0x36faef,![])),document[_0x35392a(0x21b)](_0x35392a(0xc2))[_0x35392a(0x175)](_0x35392a(0x126),()=>this['flipCharacter'](this[_0x35392a(0x13d)],p2Image,!![]));}[_0x59433c(0x1fc)](){const _0x5199e8=_0x59433c;console[_0x5199e8(0x105)](_0x5199e8(0x16e)+this[_0x5199e8(0x19d)]+_0x5199e8(0x25a)+this[_0x5199e8(0x13d)]['health']),this[_0x5199e8(0x13d)][_0x5199e8(0x100)]<=0x0&&this[_0x5199e8(0x19d)]>opponentsConfig[_0x5199e8(0x1c0)]&&(this[_0x5199e8(0x19d)]=0x1,console[_0x5199e8(0x105)](_0x5199e8(0x1bb)+this['currentLevel'])),this[_0x5199e8(0xed)](),console[_0x5199e8(0x105)](_0x5199e8(0xcc)+this[_0x5199e8(0x19d)]);}['handleMouseDown'](_0x566a21){const _0x22e7f2=_0x59433c;if(this['gameOver']||this[_0x22e7f2(0xd1)]!==_0x22e7f2(0x124)||this[_0x22e7f2(0x104)]!==this[_0x22e7f2(0xf0)])return;_0x566a21[_0x22e7f2(0x7a)]();const _0x5f389e=this[_0x22e7f2(0x13a)](_0x566a21);if(!_0x5f389e||!_0x5f389e[_0x22e7f2(0x1aa)])return;this[_0x22e7f2(0xec)]=!![],this['selectedTile']={'x':_0x5f389e['x'],'y':_0x5f389e['y']},_0x5f389e[_0x22e7f2(0x1aa)][_0x22e7f2(0x79)][_0x22e7f2(0xfe)](_0x22e7f2(0x92));const _0x256157=document['getElementById']('game-board')['getBoundingClientRect']();this[_0x22e7f2(0x11c)]=_0x566a21['clientX']-(_0x256157['left']+this[_0x22e7f2(0x147)]['x']*this['tileSizeWithGap']),this[_0x22e7f2(0x16a)]=_0x566a21[_0x22e7f2(0x232)]-(_0x256157[_0x22e7f2(0x24b)]+this[_0x22e7f2(0x147)]['y']*this[_0x22e7f2(0x209)]);}[_0x59433c(0x15d)](_0x1f3eab){const _0x5d225e=_0x59433c;if(!this['isDragging']||!this[_0x5d225e(0x147)]||this[_0x5d225e(0xad)]||this[_0x5d225e(0xd1)]!==_0x5d225e(0x124))return;_0x1f3eab[_0x5d225e(0x7a)]();const _0x31c7d5=document[_0x5d225e(0x21b)]('game-board')[_0x5d225e(0x224)](),_0x4b033c=_0x1f3eab[_0x5d225e(0x1f5)]-_0x31c7d5['left']-this[_0x5d225e(0x11c)],_0x3e708c=_0x1f3eab['clientY']-_0x31c7d5[_0x5d225e(0x24b)]-this['offsetY'],_0x2d0d4e=this[_0x5d225e(0x241)][this['selectedTile']['y']][this['selectedTile']['x']][_0x5d225e(0x1aa)];_0x2d0d4e['style'][_0x5d225e(0xcf)]='';if(!this['dragDirection']){const _0x453ea8=Math[_0x5d225e(0x187)](_0x4b033c-this['selectedTile']['x']*this[_0x5d225e(0x209)]),_0x5864f6=Math[_0x5d225e(0x187)](_0x3e708c-this[_0x5d225e(0x147)]['y']*this[_0x5d225e(0x209)]);if(_0x453ea8>_0x5864f6&&_0x453ea8>0x5)this[_0x5d225e(0xba)]='row';else{if(_0x5864f6>_0x453ea8&&_0x5864f6>0x5)this[_0x5d225e(0xba)]=_0x5d225e(0xe4);}}if(!this['dragDirection'])return;if(this['dragDirection']===_0x5d225e(0x22e)){const _0x5151e8=Math[_0x5d225e(0x90)](0x0,Math[_0x5d225e(0x14d)]((this['width']-0x1)*this[_0x5d225e(0x209)],_0x4b033c));_0x2d0d4e[_0x5d225e(0x13b)]['transform']='translate('+(_0x5151e8-this[_0x5d225e(0x147)]['x']*this[_0x5d225e(0x209)])+'px,\x200)\x20scale(1.05)',this[_0x5d225e(0x15f)]={'x':Math['round'](_0x5151e8/this[_0x5d225e(0x209)]),'y':this[_0x5d225e(0x147)]['y']};}else{if(this['dragDirection']===_0x5d225e(0xe4)){const _0x269528=Math[_0x5d225e(0x90)](0x0,Math[_0x5d225e(0x14d)]((this[_0x5d225e(0x71)]-0x1)*this[_0x5d225e(0x209)],_0x3e708c));_0x2d0d4e[_0x5d225e(0x13b)][_0x5d225e(0x18e)]='translate(0,\x20'+(_0x269528-this['selectedTile']['y']*this['tileSizeWithGap'])+_0x5d225e(0x87),this['targetTile']={'x':this[_0x5d225e(0x147)]['x'],'y':Math['round'](_0x269528/this[_0x5d225e(0x209)])};}}}['handleMouseUp'](_0x392a62){const _0x57e345=_0x59433c;if(!this[_0x57e345(0xec)]||!this[_0x57e345(0x147)]||!this[_0x57e345(0x15f)]||this[_0x57e345(0xad)]||this['gameState']!==_0x57e345(0x124)){if(this[_0x57e345(0x147)]){const _0xdc58bc=this[_0x57e345(0x241)][this[_0x57e345(0x147)]['y']][this[_0x57e345(0x147)]['x']];if(_0xdc58bc[_0x57e345(0x1aa)])_0xdc58bc[_0x57e345(0x1aa)]['classList'][_0x57e345(0x108)]('selected');}this[_0x57e345(0xec)]=![],this[_0x57e345(0x147)]=null,this[_0x57e345(0x15f)]=null,this['dragDirection']=null,this[_0x57e345(0x1bc)]();return;}const _0x56df50=this[_0x57e345(0x241)][this[_0x57e345(0x147)]['y']][this[_0x57e345(0x147)]['x']];if(_0x56df50[_0x57e345(0x1aa)])_0x56df50['element'][_0x57e345(0x79)][_0x57e345(0x108)](_0x57e345(0x92));this[_0x57e345(0x195)](this[_0x57e345(0x147)]['x'],this[_0x57e345(0x147)]['y'],this['targetTile']['x'],this[_0x57e345(0x15f)]['y']),this[_0x57e345(0xec)]=![],this[_0x57e345(0x147)]=null,this[_0x57e345(0x15f)]=null,this[_0x57e345(0xba)]=null;}[_0x59433c(0x144)](_0x5107e2){const _0x3b3a34=_0x59433c;if(this['gameOver']||this[_0x3b3a34(0xd1)]!==_0x3b3a34(0x124)||this['currentTurn']!==this[_0x3b3a34(0xf0)])return;_0x5107e2[_0x3b3a34(0x7a)]();const _0x5848fd=this[_0x3b3a34(0x13a)](_0x5107e2[_0x3b3a34(0xa3)][0x0]);if(!_0x5848fd||!_0x5848fd[_0x3b3a34(0x1aa)])return;this[_0x3b3a34(0xec)]=!![],this[_0x3b3a34(0x147)]={'x':_0x5848fd['x'],'y':_0x5848fd['y']},_0x5848fd['element'][_0x3b3a34(0x79)]['add'](_0x3b3a34(0x92));const _0x4ed33f=document[_0x3b3a34(0x21b)](_0x3b3a34(0x184))['getBoundingClientRect']();this['offsetX']=_0x5107e2[_0x3b3a34(0xa3)][0x0][_0x3b3a34(0x1f5)]-(_0x4ed33f['left']+this['selectedTile']['x']*this['tileSizeWithGap']),this['offsetY']=_0x5107e2[_0x3b3a34(0xa3)][0x0][_0x3b3a34(0x232)]-(_0x4ed33f[_0x3b3a34(0x24b)]+this['selectedTile']['y']*this['tileSizeWithGap']);}['handleTouchMove'](_0x75489e){const _0x555373=_0x59433c;if(!this[_0x555373(0xec)]||!this['selectedTile']||this[_0x555373(0xad)]||this[_0x555373(0xd1)]!==_0x555373(0x124))return;_0x75489e['preventDefault']();const _0x2ac2f3=document[_0x555373(0x21b)](_0x555373(0x184))[_0x555373(0x224)](),_0x493d28=_0x75489e['touches'][0x0]['clientX']-_0x2ac2f3[_0x555373(0x12e)]-this[_0x555373(0x11c)],_0x509ee2=_0x75489e[_0x555373(0xa3)][0x0][_0x555373(0x232)]-_0x2ac2f3[_0x555373(0x24b)]-this['offsetY'],_0x55b255=this[_0x555373(0x241)][this['selectedTile']['y']][this[_0x555373(0x147)]['x']][_0x555373(0x1aa)];requestAnimationFrame(()=>{const _0x3e8cf8=_0x555373;if(!this[_0x3e8cf8(0xba)]){const _0x171f7a=Math[_0x3e8cf8(0x187)](_0x493d28-this[_0x3e8cf8(0x147)]['x']*this['tileSizeWithGap']),_0x1e2a7f=Math[_0x3e8cf8(0x187)](_0x509ee2-this[_0x3e8cf8(0x147)]['y']*this[_0x3e8cf8(0x209)]);if(_0x171f7a>_0x1e2a7f&&_0x171f7a>0x7)this[_0x3e8cf8(0xba)]='row';else{if(_0x1e2a7f>_0x171f7a&&_0x1e2a7f>0x7)this['dragDirection']=_0x3e8cf8(0xe4);}}_0x55b255[_0x3e8cf8(0x13b)]['transition']='';if(this[_0x3e8cf8(0xba)]===_0x3e8cf8(0x22e)){const _0x9338b8=Math[_0x3e8cf8(0x90)](0x0,Math[_0x3e8cf8(0x14d)]((this['width']-0x1)*this['tileSizeWithGap'],_0x493d28));_0x55b255[_0x3e8cf8(0x13b)][_0x3e8cf8(0x18e)]=_0x3e8cf8(0x259)+(_0x9338b8-this['selectedTile']['x']*this['tileSizeWithGap'])+_0x3e8cf8(0x22c),this['targetTile']={'x':Math[_0x3e8cf8(0x1ea)](_0x9338b8/this['tileSizeWithGap']),'y':this[_0x3e8cf8(0x147)]['y']};}else{if(this[_0x3e8cf8(0xba)]==='column'){const _0xc036a9=Math['max'](0x0,Math['min']((this[_0x3e8cf8(0x71)]-0x1)*this[_0x3e8cf8(0x209)],_0x509ee2));_0x55b255[_0x3e8cf8(0x13b)][_0x3e8cf8(0x18e)]='translate(0,\x20'+(_0xc036a9-this['selectedTile']['y']*this[_0x3e8cf8(0x209)])+_0x3e8cf8(0x87),this[_0x3e8cf8(0x15f)]={'x':this[_0x3e8cf8(0x147)]['x'],'y':Math[_0x3e8cf8(0x1ea)](_0xc036a9/this['tileSizeWithGap'])};}}});}[_0x59433c(0x134)](_0x16743d){const _0x1329b9=_0x59433c;if(!this[_0x1329b9(0xec)]||!this[_0x1329b9(0x147)]||!this[_0x1329b9(0x15f)]||this['gameOver']||this[_0x1329b9(0xd1)]!=='playerTurn'){if(this[_0x1329b9(0x147)]){const _0x5bb043=this[_0x1329b9(0x241)][this[_0x1329b9(0x147)]['y']][this[_0x1329b9(0x147)]['x']];if(_0x5bb043[_0x1329b9(0x1aa)])_0x5bb043['element'][_0x1329b9(0x79)][_0x1329b9(0x108)]('selected');}this['isDragging']=![],this['selectedTile']=null,this['targetTile']=null,this['dragDirection']=null,this[_0x1329b9(0x1bc)]();return;}const _0x3a33d8=this[_0x1329b9(0x241)][this[_0x1329b9(0x147)]['y']][this[_0x1329b9(0x147)]['x']];if(_0x3a33d8[_0x1329b9(0x1aa)])_0x3a33d8[_0x1329b9(0x1aa)][_0x1329b9(0x79)][_0x1329b9(0x108)](_0x1329b9(0x92));this[_0x1329b9(0x195)](this['selectedTile']['x'],this[_0x1329b9(0x147)]['y'],this[_0x1329b9(0x15f)]['x'],this[_0x1329b9(0x15f)]['y']),this['isDragging']=![],this[_0x1329b9(0x147)]=null,this[_0x1329b9(0x15f)]=null,this['dragDirection']=null;}[_0x59433c(0x13a)](_0x18e558){const _0x1287c0=_0x59433c,_0x597586=document[_0x1287c0(0x21b)]('game-board')[_0x1287c0(0x224)](),_0x1c231e=Math[_0x1287c0(0x78)]((_0x18e558['clientX']-_0x597586[_0x1287c0(0x12e)])/this[_0x1287c0(0x209)]),_0x18e92d=Math[_0x1287c0(0x78)]((_0x18e558['clientY']-_0x597586['top'])/this['tileSizeWithGap']);if(_0x1c231e>=0x0&&_0x1c231e<this['width']&&_0x18e92d>=0x0&&_0x18e92d<this[_0x1287c0(0x71)])return{'x':_0x1c231e,'y':_0x18e92d,'element':this[_0x1287c0(0x241)][_0x18e92d][_0x1c231e][_0x1287c0(0x1aa)]};return null;}[_0x59433c(0x195)](_0x33f31f,_0x243170,_0x3d0fb8,_0x34b304){const _0x301aad=_0x59433c,_0x3b42d4=this[_0x301aad(0x209)];let _0x16dcd6;const _0x449711=[],_0x102cef=[];if(_0x243170===_0x34b304){_0x16dcd6=_0x33f31f<_0x3d0fb8?0x1:-0x1;const _0xb65dd=Math[_0x301aad(0x14d)](_0x33f31f,_0x3d0fb8),_0x2484be=Math['max'](_0x33f31f,_0x3d0fb8);for(let _0x373488=_0xb65dd;_0x373488<=_0x2484be;_0x373488++){_0x449711[_0x301aad(0xfc)]({...this[_0x301aad(0x241)][_0x243170][_0x373488]}),_0x102cef[_0x301aad(0xfc)](this[_0x301aad(0x241)][_0x243170][_0x373488][_0x301aad(0x1aa)]);}}else{if(_0x33f31f===_0x3d0fb8){_0x16dcd6=_0x243170<_0x34b304?0x1:-0x1;const _0x5b17d1=Math['min'](_0x243170,_0x34b304),_0x3919dc=Math[_0x301aad(0x90)](_0x243170,_0x34b304);for(let _0xcc38a=_0x5b17d1;_0xcc38a<=_0x3919dc;_0xcc38a++){_0x449711[_0x301aad(0xfc)]({...this['board'][_0xcc38a][_0x33f31f]}),_0x102cef[_0x301aad(0xfc)](this[_0x301aad(0x241)][_0xcc38a][_0x33f31f][_0x301aad(0x1aa)]);}}}const _0x5edd03=this[_0x301aad(0x241)][_0x243170][_0x33f31f][_0x301aad(0x1aa)],_0x237767=(_0x3d0fb8-_0x33f31f)*_0x3b42d4,_0x28a8d1=(_0x34b304-_0x243170)*_0x3b42d4;_0x5edd03['style']['transition']=_0x301aad(0x177),_0x5edd03[_0x301aad(0x13b)][_0x301aad(0x18e)]=_0x301aad(0x259)+_0x237767+_0x301aad(0x76)+_0x28a8d1+'px)';let _0xd949c3=0x0;if(_0x243170===_0x34b304)for(let _0x5ee12a=Math['min'](_0x33f31f,_0x3d0fb8);_0x5ee12a<=Math['max'](_0x33f31f,_0x3d0fb8);_0x5ee12a++){if(_0x5ee12a===_0x33f31f)continue;const _0x4b0e10=_0x16dcd6*-_0x3b42d4*(_0x5ee12a-_0x33f31f)/Math[_0x301aad(0x187)](_0x3d0fb8-_0x33f31f);_0x102cef[_0xd949c3]['style'][_0x301aad(0xcf)]=_0x301aad(0x177),_0x102cef[_0xd949c3][_0x301aad(0x13b)]['transform']=_0x301aad(0x259)+_0x4b0e10+_0x301aad(0x67),_0xd949c3++;}else for(let _0x3aa9ca=Math['min'](_0x243170,_0x34b304);_0x3aa9ca<=Math[_0x301aad(0x90)](_0x243170,_0x34b304);_0x3aa9ca++){if(_0x3aa9ca===_0x243170)continue;const _0x2314d1=_0x16dcd6*-_0x3b42d4*(_0x3aa9ca-_0x243170)/Math['abs'](_0x34b304-_0x243170);_0x102cef[_0xd949c3]['style'][_0x301aad(0xcf)]='transform\x200.2s\x20ease',_0x102cef[_0xd949c3][_0x301aad(0x13b)][_0x301aad(0x18e)]=_0x301aad(0x17f)+_0x2314d1+'px)',_0xd949c3++;}setTimeout(()=>{const _0x19169d=_0x301aad;if(_0x243170===_0x34b304){const _0x1e1d13=this['board'][_0x243170],_0x3887a3=[..._0x1e1d13];if(_0x33f31f<_0x3d0fb8){for(let _0x57dddc=_0x33f31f;_0x57dddc<_0x3d0fb8;_0x57dddc++)_0x1e1d13[_0x57dddc]=_0x3887a3[_0x57dddc+0x1];}else{for(let _0x5ec94a=_0x33f31f;_0x5ec94a>_0x3d0fb8;_0x5ec94a--)_0x1e1d13[_0x5ec94a]=_0x3887a3[_0x5ec94a-0x1];}_0x1e1d13[_0x3d0fb8]=_0x3887a3[_0x33f31f];}else{const _0x1b88ab=[];for(let _0x2c64aa=0x0;_0x2c64aa<this[_0x19169d(0x71)];_0x2c64aa++)_0x1b88ab[_0x2c64aa]={...this[_0x19169d(0x241)][_0x2c64aa][_0x33f31f]};if(_0x243170<_0x34b304){for(let _0x3f2032=_0x243170;_0x3f2032<_0x34b304;_0x3f2032++)this[_0x19169d(0x241)][_0x3f2032][_0x33f31f]=_0x1b88ab[_0x3f2032+0x1];}else{for(let _0x4d9794=_0x243170;_0x4d9794>_0x34b304;_0x4d9794--)this[_0x19169d(0x241)][_0x4d9794][_0x33f31f]=_0x1b88ab[_0x4d9794-0x1];}this[_0x19169d(0x241)][_0x34b304][_0x3d0fb8]=_0x1b88ab[_0x243170];}this[_0x19169d(0x1bc)]();const _0x8133fe=this[_0x19169d(0x10e)](_0x3d0fb8,_0x34b304);_0x8133fe?this[_0x19169d(0xd1)]=_0x19169d(0x24d):(log(_0x19169d(0x222)),this[_0x19169d(0x138)][_0x19169d(0x250)][_0x19169d(0xdf)](),_0x5edd03[_0x19169d(0x13b)][_0x19169d(0xcf)]=_0x19169d(0x177),_0x5edd03[_0x19169d(0x13b)]['transform']='translate(0,\x200)',_0x102cef[_0x19169d(0x161)](_0x575fda=>{const _0x4cdbbc=_0x19169d;_0x575fda[_0x4cdbbc(0x13b)][_0x4cdbbc(0xcf)]=_0x4cdbbc(0x177),_0x575fda[_0x4cdbbc(0x13b)][_0x4cdbbc(0x18e)]='translate(0,\x200)';}),setTimeout(()=>{const _0x2cc84c=_0x19169d;if(_0x243170===_0x34b304){const _0x2c30fd=Math[_0x2cc84c(0x14d)](_0x33f31f,_0x3d0fb8);for(let _0x413733=0x0;_0x413733<_0x449711[_0x2cc84c(0x1c0)];_0x413733++){this[_0x2cc84c(0x241)][_0x243170][_0x2c30fd+_0x413733]={..._0x449711[_0x413733],'element':_0x102cef[_0x413733]};}}else{const _0x5a1d6e=Math[_0x2cc84c(0x14d)](_0x243170,_0x34b304);for(let _0x551695=0x0;_0x551695<_0x449711[_0x2cc84c(0x1c0)];_0x551695++){this['board'][_0x5a1d6e+_0x551695][_0x33f31f]={..._0x449711[_0x551695],'element':_0x102cef[_0x551695]};}}this['renderBoard'](),this[_0x2cc84c(0xd1)]=_0x2cc84c(0x124);},0xc8));},0xc8);}['resolveMatches'](_0x1017e5=null,_0x26e3b9=null){const _0x18d0f6=_0x59433c;console['log'](_0x18d0f6(0x91),this[_0x18d0f6(0xad)]);if(this[_0x18d0f6(0xad)])return console[_0x18d0f6(0x105)]('Game\x20over,\x20exiting\x20resolveMatches'),![];const _0x2dca50=_0x1017e5!==null&&_0x26e3b9!==null;console[_0x18d0f6(0x105)]('Is\x20initial\x20move:\x20'+_0x2dca50);const _0x51eed9=this[_0x18d0f6(0x75)]();console[_0x18d0f6(0x105)](_0x18d0f6(0xef)+_0x51eed9[_0x18d0f6(0x1c0)]+_0x18d0f6(0x18d),_0x51eed9);let _0x286e63=0x1,_0x379593='';if(_0x2dca50&&_0x51eed9[_0x18d0f6(0x1c0)]>0x1){const _0x169923=_0x51eed9['reduce']((_0x5967bb,_0x541aa2)=>_0x5967bb+_0x541aa2[_0x18d0f6(0x1b3)],0x0);console[_0x18d0f6(0x105)](_0x18d0f6(0x98)+_0x169923);if(_0x169923>=0x6&&_0x169923<=0x8)_0x286e63=1.2,_0x379593=_0x18d0f6(0xe8)+_0x169923+_0x18d0f6(0x208),this[_0x18d0f6(0x138)][_0x18d0f6(0xde)][_0x18d0f6(0xdf)]();else _0x169923>=0x9&&(_0x286e63=0x3,_0x379593='Mega\x20Multi-Match!\x20'+_0x169923+_0x18d0f6(0xc9),this['sounds'][_0x18d0f6(0xde)][_0x18d0f6(0xdf)]());}if(_0x51eed9[_0x18d0f6(0x1c0)]>0x0){const _0x2486f1=new Set();let _0x2d98b2=0x0;const _0x5580db=this['currentTurn'],_0x2eae87=this[_0x18d0f6(0x104)]===this[_0x18d0f6(0xf0)]?this[_0x18d0f6(0x13d)]:this[_0x18d0f6(0xf0)];try{_0x51eed9[_0x18d0f6(0x161)](_0x236509=>{const _0x34bcfa=_0x18d0f6;console[_0x34bcfa(0x105)](_0x34bcfa(0x84),_0x236509),_0x236509[_0x34bcfa(0x152)][_0x34bcfa(0x161)](_0x1c24ce=>_0x2486f1[_0x34bcfa(0xfe)](_0x1c24ce));const _0x17c906=this[_0x34bcfa(0x26b)](_0x236509,_0x2dca50);console[_0x34bcfa(0x105)](_0x34bcfa(0x1b9)+_0x17c906);if(this[_0x34bcfa(0xad)]){console[_0x34bcfa(0x105)](_0x34bcfa(0x1cc));return;}if(_0x17c906>0x0)_0x2d98b2+=_0x17c906;});if(this['gameOver'])return console[_0x18d0f6(0x105)](_0x18d0f6(0x74)),!![];return console[_0x18d0f6(0x105)](_0x18d0f6(0x114)+_0x2d98b2+_0x18d0f6(0x1be),[..._0x2486f1]),_0x2d98b2>0x0&&!this['gameOver']&&setTimeout(()=>{const _0x45399b=_0x18d0f6;if(this[_0x45399b(0xad)]){console[_0x45399b(0x105)](_0x45399b(0x22f));return;}console[_0x45399b(0x105)](_0x45399b(0x137),_0x2eae87[_0x45399b(0x1ec)]),this[_0x45399b(0xae)](_0x2eae87,_0x2d98b2);},0x64),setTimeout(()=>{const _0x41a6d6=_0x18d0f6;if(this[_0x41a6d6(0xad)]){console[_0x41a6d6(0x105)](_0x41a6d6(0x193));return;}console['log'](_0x41a6d6(0x13e),[..._0x2486f1]),_0x2486f1[_0x41a6d6(0x161)](_0x2870bd=>{const _0x4b808f=_0x41a6d6,[_0x417e1c,_0x49d426]=_0x2870bd[_0x4b808f(0x6c)](',')[_0x4b808f(0xe6)](Number);this['board'][_0x49d426][_0x417e1c]?.[_0x4b808f(0x1aa)]?this[_0x4b808f(0x241)][_0x49d426][_0x417e1c][_0x4b808f(0x1aa)][_0x4b808f(0x79)][_0x4b808f(0xfe)]('matched'):console[_0x4b808f(0xf3)]('Tile\x20at\x20('+_0x417e1c+','+_0x49d426+_0x4b808f(0x8a));}),setTimeout(()=>{const _0x5a4049=_0x41a6d6;if(this[_0x5a4049(0xad)]){console[_0x5a4049(0x105)](_0x5a4049(0xe1));return;}console['log'](_0x5a4049(0xdc),[..._0x2486f1]),_0x2486f1[_0x5a4049(0x161)](_0x44d777=>{const _0x20c9a1=_0x5a4049,[_0x573508,_0x3ac2e4]=_0x44d777[_0x20c9a1(0x6c)](',')[_0x20c9a1(0xe6)](Number);this['board'][_0x3ac2e4][_0x573508]&&(this[_0x20c9a1(0x241)][_0x3ac2e4][_0x573508][_0x20c9a1(0x201)]=null,this[_0x20c9a1(0x241)][_0x3ac2e4][_0x573508]['element']=null);}),this['sounds'][_0x5a4049(0x245)][_0x5a4049(0xdf)](),console[_0x5a4049(0x105)]('Cascading\x20tiles');if(_0x286e63>0x1&&this[_0x5a4049(0x263)][_0x5a4049(0x1c0)]>0x0){const _0x335c80=this[_0x5a4049(0x263)][this[_0x5a4049(0x263)][_0x5a4049(0x1c0)]-0x1],_0x92b20c=_0x335c80['points'];_0x335c80[_0x5a4049(0x20b)]=Math[_0x5a4049(0x1ea)](_0x335c80[_0x5a4049(0x20b)]*_0x286e63),_0x379593&&(log(_0x379593),log(_0x5a4049(0x14f)+_0x92b20c+_0x5a4049(0x25d)+_0x335c80['points']+'\x20after\x20multi-match\x20bonus!'));}this['cascadeTiles'](()=>{const _0x319a45=_0x5a4049;if(this['gameOver']){console[_0x319a45(0x105)](_0x319a45(0x15e));return;}console[_0x319a45(0x105)](_0x319a45(0x200)),this['endTurn']();});},0x12c);},0xc8),!![];}catch(_0x38a572){return console[_0x18d0f6(0x6a)](_0x18d0f6(0xc0),_0x38a572),this[_0x18d0f6(0xd1)]=this[_0x18d0f6(0x104)]===this[_0x18d0f6(0xf0)]?_0x18d0f6(0x124):_0x18d0f6(0x1fd),![];}}return console[_0x18d0f6(0x105)](_0x18d0f6(0x9b)),![];}[_0x59433c(0x75)](){const _0x396a84=_0x59433c;console[_0x396a84(0x105)](_0x396a84(0x179));const _0x4b60e5=[];try{const _0x44573f=[];for(let _0x2fe4eb=0x0;_0x2fe4eb<this[_0x396a84(0x71)];_0x2fe4eb++){let _0x523779=0x0;for(let _0xa1d782=0x0;_0xa1d782<=this[_0x396a84(0xf8)];_0xa1d782++){const _0x2af708=_0xa1d782<this[_0x396a84(0xf8)]?this['board'][_0x2fe4eb][_0xa1d782]?.[_0x396a84(0x201)]:null;if(_0x2af708!==this[_0x396a84(0x241)][_0x2fe4eb][_0x523779]?.['type']||_0xa1d782===this[_0x396a84(0xf8)]){const _0x34540e=_0xa1d782-_0x523779;if(_0x34540e>=0x3){const _0x3885a2=new Set();for(let _0x56b485=_0x523779;_0x56b485<_0xa1d782;_0x56b485++){_0x3885a2['add'](_0x56b485+','+_0x2fe4eb);}_0x44573f[_0x396a84(0xfc)]({'type':this[_0x396a84(0x241)][_0x2fe4eb][_0x523779][_0x396a84(0x201)],'coordinates':_0x3885a2}),console[_0x396a84(0x105)](_0x396a84(0x247)+_0x2fe4eb+_0x396a84(0x69)+_0x523779+'-'+(_0xa1d782-0x1)+':',[..._0x3885a2]);}_0x523779=_0xa1d782;}}}for(let _0xf66e83=0x0;_0xf66e83<this[_0x396a84(0xf8)];_0xf66e83++){let _0x573cd1=0x0;for(let _0x4d105c=0x0;_0x4d105c<=this[_0x396a84(0x71)];_0x4d105c++){const _0x115b69=_0x4d105c<this['height']?this[_0x396a84(0x241)][_0x4d105c][_0xf66e83]?.[_0x396a84(0x201)]:null;if(_0x115b69!==this[_0x396a84(0x241)][_0x573cd1][_0xf66e83]?.[_0x396a84(0x201)]||_0x4d105c===this['height']){const _0x4e30f3=_0x4d105c-_0x573cd1;if(_0x4e30f3>=0x3){const _0x304560=new Set();for(let _0x119dbf=_0x573cd1;_0x119dbf<_0x4d105c;_0x119dbf++){_0x304560['add'](_0xf66e83+','+_0x119dbf);}_0x44573f[_0x396a84(0xfc)]({'type':this[_0x396a84(0x241)][_0x573cd1][_0xf66e83]['type'],'coordinates':_0x304560}),console[_0x396a84(0x105)](_0x396a84(0xb8)+_0xf66e83+_0x396a84(0x1fb)+_0x573cd1+'-'+(_0x4d105c-0x1)+':',[..._0x304560]);}_0x573cd1=_0x4d105c;}}}const _0x3fdd13=[],_0x1ef268=new Set();return _0x44573f[_0x396a84(0x161)]((_0x483c93,_0x71d36a)=>{const _0x191043=_0x396a84;if(_0x1ef268[_0x191043(0x225)](_0x71d36a))return;const _0x187688={'type':_0x483c93[_0x191043(0x201)],'coordinates':new Set(_0x483c93[_0x191043(0x152)])};_0x1ef268[_0x191043(0xfe)](_0x71d36a);for(let _0x568e3c=0x0;_0x568e3c<_0x44573f['length'];_0x568e3c++){if(_0x1ef268['has'](_0x568e3c))continue;const _0x3df94f=_0x44573f[_0x568e3c];if(_0x3df94f[_0x191043(0x201)]===_0x187688[_0x191043(0x201)]){const _0x546719=[..._0x3df94f[_0x191043(0x152)]][_0x191043(0xd9)](_0xa732a2=>_0x187688[_0x191043(0x152)][_0x191043(0x225)](_0xa732a2));_0x546719&&(_0x3df94f[_0x191043(0x152)][_0x191043(0x161)](_0x974a4=>_0x187688['coordinates']['add'](_0x974a4)),_0x1ef268[_0x191043(0xfe)](_0x568e3c));}}_0x3fdd13[_0x191043(0xfc)]({'type':_0x187688[_0x191043(0x201)],'coordinates':_0x187688[_0x191043(0x152)],'totalTiles':_0x187688[_0x191043(0x152)][_0x191043(0x234)]});}),_0x4b60e5[_0x396a84(0xfc)](..._0x3fdd13),console['log']('checkMatches\x20completed,\x20returning\x20matches:',_0x4b60e5),_0x4b60e5;}catch(_0xca1a18){return console[_0x396a84(0x6a)]('Error\x20in\x20checkMatches:',_0xca1a18),[];}}[_0x59433c(0x26b)](_0x4b2b48,_0x13a7d=!![]){const _0x439d9b=_0x59433c;console[_0x439d9b(0x105)]('handleMatch\x20started,\x20match:',_0x4b2b48,_0x439d9b(0x12c),_0x13a7d);const _0x2baaa7=this['currentTurn'],_0x37a8c2=this['currentTurn']===this[_0x439d9b(0xf0)]?this[_0x439d9b(0x13d)]:this[_0x439d9b(0xf0)],_0x1bf258=_0x4b2b48['type'],_0x579005=_0x4b2b48[_0x439d9b(0x1b3)];let _0x53ca60=0x0,_0x446c6b=0x0;console[_0x439d9b(0x105)](_0x37a8c2[_0x439d9b(0x1ec)]+_0x439d9b(0x25b)+_0x37a8c2['health']);_0x579005==0x4&&(this[_0x439d9b(0x138)]['powerGem'][_0x439d9b(0xdf)](),log(_0x2baaa7[_0x439d9b(0x1ec)]+_0x439d9b(0x13f)+_0x579005+'\x20tiles!'));_0x579005>=0x5&&(this[_0x439d9b(0x138)][_0x439d9b(0x6b)][_0x439d9b(0xdf)](),log(_0x2baaa7[_0x439d9b(0x1ec)]+'\x20created\x20a\x20match\x20of\x20'+_0x579005+_0x439d9b(0x194)));if(_0x1bf258==='first-attack'||_0x1bf258===_0x439d9b(0x256)||_0x1bf258===_0x439d9b(0x153)||_0x1bf258==='last-stand'){_0x53ca60=Math[_0x439d9b(0x1ea)](_0x2baaa7[_0x439d9b(0xd7)]*(_0x579005===0x3?0x2:_0x579005===0x4?0x3:0x4));let _0xddd420=0x1;if(_0x579005===0x4)_0xddd420=1.5;else _0x579005>=0x5&&(_0xddd420=0x2);_0x53ca60=Math['round'](_0x53ca60*_0xddd420),console[_0x439d9b(0x105)](_0x439d9b(0x1df)+_0x2baaa7[_0x439d9b(0xd7)]*(_0x579005===0x3?0x2:_0x579005===0x4?0x3:0x4)+_0x439d9b(0x1ca)+_0xddd420+_0x439d9b(0x19b)+_0x53ca60);_0x1bf258===_0x439d9b(0x153)&&(_0x53ca60=Math[_0x439d9b(0x1ea)](_0x53ca60*1.2),console[_0x439d9b(0x105)](_0x439d9b(0xa2)+_0x53ca60));_0x2baaa7[_0x439d9b(0x239)]&&(_0x53ca60+=_0x2baaa7[_0x439d9b(0x15c)]||0xa,_0x2baaa7[_0x439d9b(0x239)]=![],log(_0x2baaa7['name']+_0x439d9b(0x262)),console['log'](_0x439d9b(0x83)+_0x53ca60));_0x446c6b=_0x53ca60;const _0x45132f=_0x37a8c2[_0x439d9b(0xd3)]*0xa;Math['random']()*0x64<_0x45132f&&(_0x53ca60=Math[_0x439d9b(0x78)](_0x53ca60/0x2),log(_0x37a8c2[_0x439d9b(0x1ec)]+'\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20'+_0x53ca60+_0x439d9b(0xfa)),console['log'](_0x439d9b(0x145)+_0x53ca60));let _0x2c7474=0x0;_0x37a8c2[_0x439d9b(0x1d3)]&&(_0x2c7474=Math[_0x439d9b(0x14d)](_0x53ca60,0x5),_0x53ca60=Math[_0x439d9b(0x90)](0x0,_0x53ca60-_0x2c7474),_0x37a8c2['lastStandActive']=![],console[_0x439d9b(0x105)](_0x439d9b(0x117)+_0x2c7474+_0x439d9b(0xa9)+_0x53ca60));const _0x579078=_0x1bf258===_0x439d9b(0x1e3)?_0x439d9b(0xf9):_0x1bf258==='second-attack'?'Bite':_0x439d9b(0xbf);let _0x34f43f;if(_0x2c7474>0x0)_0x34f43f=_0x2baaa7['name']+_0x439d9b(0x1c2)+_0x579078+_0x439d9b(0x149)+_0x37a8c2[_0x439d9b(0x1ec)]+_0x439d9b(0x204)+_0x446c6b+_0x439d9b(0x1d9)+_0x37a8c2[_0x439d9b(0x1ec)]+_0x439d9b(0x17c)+_0x2c7474+'\x20damage,\x20resulting\x20in\x20'+_0x53ca60+_0x439d9b(0xfa);else _0x1bf258===_0x439d9b(0x1eb)?_0x34f43f=_0x2baaa7['name']+_0x439d9b(0x197)+_0x53ca60+_0x439d9b(0x7c)+_0x37a8c2[_0x439d9b(0x1ec)]+_0x439d9b(0x26d):_0x34f43f=_0x2baaa7[_0x439d9b(0x1ec)]+_0x439d9b(0x1c2)+_0x579078+'\x20on\x20'+_0x37a8c2[_0x439d9b(0x1ec)]+'\x20for\x20'+_0x53ca60+_0x439d9b(0xfa);_0x13a7d?log(_0x34f43f):log(_0x439d9b(0x1d0)+_0x34f43f),_0x37a8c2[_0x439d9b(0x100)]=Math['max'](0x0,_0x37a8c2[_0x439d9b(0x100)]-_0x53ca60),console['log'](_0x37a8c2[_0x439d9b(0x1ec)]+_0x439d9b(0x10f)+_0x37a8c2[_0x439d9b(0x100)]),this[_0x439d9b(0x73)](_0x37a8c2),console[_0x439d9b(0x105)](_0x439d9b(0x127)),this[_0x439d9b(0x1a7)](),!this[_0x439d9b(0xad)]&&(console[_0x439d9b(0x105)]('Game\x20not\x20over,\x20animating\x20attack'),this['animateAttack'](_0x2baaa7,_0x53ca60,_0x1bf258));}else _0x1bf258===_0x439d9b(0x14a)&&(this[_0x439d9b(0x125)](_0x2baaa7,_0x37a8c2,_0x579005),!this[_0x439d9b(0xad)]&&(console[_0x439d9b(0x105)](_0x439d9b(0xd4)),this['animatePowerup'](_0x2baaa7)));(!this['roundStats'][this[_0x439d9b(0x263)][_0x439d9b(0x1c0)]-0x1]||this[_0x439d9b(0x263)][this['roundStats'][_0x439d9b(0x1c0)]-0x1][_0x439d9b(0x20d)])&&this[_0x439d9b(0x263)][_0x439d9b(0xfc)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x40b4c6=this['roundStats'][this[_0x439d9b(0x263)][_0x439d9b(0x1c0)]-0x1];return _0x40b4c6[_0x439d9b(0x20b)]+=_0x53ca60,_0x40b4c6[_0x439d9b(0x1ed)]+=0x1,console[_0x439d9b(0x105)](_0x439d9b(0x68)+_0x53ca60),_0x53ca60;}[_0x59433c(0x20c)](_0xbe7cd7){const _0x45066a=_0x59433c;if(this[_0x45066a(0xad)]){console[_0x45066a(0x105)](_0x45066a(0x198));return;}const _0x187b2a=this['cascadeTilesWithoutRender'](),_0x347104=_0x45066a(0x25e);for(let _0x3ae575=0x0;_0x3ae575<this['width'];_0x3ae575++){for(let _0x4397dc=0x0;_0x4397dc<this[_0x45066a(0x71)];_0x4397dc++){const _0x37c481=this[_0x45066a(0x241)][_0x4397dc][_0x3ae575];if(_0x37c481[_0x45066a(0x1aa)]&&_0x37c481[_0x45066a(0x1aa)][_0x45066a(0x13b)][_0x45066a(0x18e)]===_0x45066a(0x176)){const _0x599a06=this[_0x45066a(0x180)](_0x3ae575,_0x4397dc);_0x599a06>0x0&&(_0x37c481[_0x45066a(0x1aa)][_0x45066a(0x79)]['add'](_0x347104),_0x37c481[_0x45066a(0x1aa)]['style']['transform']=_0x45066a(0x17f)+_0x599a06*this['tileSizeWithGap']+_0x45066a(0x14b));}}}this[_0x45066a(0x1bc)](),_0x187b2a?setTimeout(()=>{const _0x18766d=_0x45066a;if(this[_0x18766d(0xad)]){console[_0x18766d(0x105)](_0x18766d(0x1e0));return;}this[_0x18766d(0x138)][_0x18766d(0x72)]['play']();const _0x21b014=this[_0x18766d(0x10e)](),_0x2e7136=document[_0x18766d(0x1f3)]('.'+_0x347104);_0x2e7136[_0x18766d(0x161)](_0x1353d8=>{const _0x360d4=_0x18766d;_0x1353d8[_0x360d4(0x79)][_0x360d4(0x108)](_0x347104),_0x1353d8[_0x360d4(0x13b)]['transform']='translate(0,\x200)';}),!_0x21b014&&_0xbe7cd7();},0x12c):_0xbe7cd7();}[_0x59433c(0x1d5)](){const _0xa2b75e=_0x59433c;let _0x2249cc=![];for(let _0x34c7fa=0x0;_0x34c7fa<this[_0xa2b75e(0xf8)];_0x34c7fa++){let _0x2e4c5f=0x0;for(let _0x227dd2=this[_0xa2b75e(0x71)]-0x1;_0x227dd2>=0x0;_0x227dd2--){if(!this['board'][_0x227dd2][_0x34c7fa]['type'])_0x2e4c5f++;else _0x2e4c5f>0x0&&(this[_0xa2b75e(0x241)][_0x227dd2+_0x2e4c5f][_0x34c7fa]=this['board'][_0x227dd2][_0x34c7fa],this[_0xa2b75e(0x241)][_0x227dd2][_0x34c7fa]={'type':null,'element':null},_0x2249cc=!![]);}for(let _0x13b1af=0x0;_0x13b1af<_0x2e4c5f;_0x13b1af++){this[_0xa2b75e(0x241)][_0x13b1af][_0x34c7fa]=this[_0xa2b75e(0x123)](),_0x2249cc=!![];}}return _0x2249cc;}['countEmptyBelow'](_0x4c53f6,_0x21565a){const _0x207c9b=_0x59433c;let _0x1c10ab=0x0;for(let _0x467ffd=_0x21565a+0x1;_0x467ffd<this['height'];_0x467ffd++){if(!this[_0x207c9b(0x241)][_0x467ffd][_0x4c53f6]['type'])_0x1c10ab++;else break;}return _0x1c10ab;}[_0x59433c(0x125)](_0x5ec976,_0x19f14f,_0x4a428c){const _0xf32bea=_0x59433c,_0x2c7e37=0x1-_0x19f14f[_0xf32bea(0xd3)]*0.05;let _0x3ec73d,_0x4055ae,_0x15d413,_0x1f4355=0x1,_0x2496a1='';if(_0x4a428c===0x4)_0x1f4355=1.5,_0x2496a1=_0xf32bea(0x213);else _0x4a428c>=0x5&&(_0x1f4355=0x2,_0x2496a1=_0xf32bea(0x238));if(_0x5ec976['powerup']===_0xf32bea(0x21a))_0x4055ae=0xa*_0x1f4355,_0x3ec73d=Math[_0xf32bea(0x78)](_0x4055ae*_0x2c7e37),_0x15d413=_0x4055ae-_0x3ec73d,_0x5ec976[_0xf32bea(0x100)]=Math[_0xf32bea(0x14d)](_0x5ec976[_0xf32bea(0x163)],_0x5ec976[_0xf32bea(0x100)]+_0x3ec73d),log(_0x5ec976[_0xf32bea(0x1ec)]+_0xf32bea(0xbd)+_0x3ec73d+_0xf32bea(0x166)+_0x2496a1+(_0x19f14f[_0xf32bea(0xd3)]>0x0?_0xf32bea(0x228)+_0x4055ae+',\x20reduced\x20by\x20'+_0x15d413+_0xf32bea(0x1a1)+_0x19f14f[_0xf32bea(0x1ec)]+_0xf32bea(0xca):'')+'!');else{if(_0x5ec976[_0xf32bea(0x20f)]==='Boost\x20Attack')_0x4055ae=0xa*_0x1f4355,_0x3ec73d=Math[_0xf32bea(0x78)](_0x4055ae*_0x2c7e37),_0x15d413=_0x4055ae-_0x3ec73d,_0x5ec976['boostActive']=!![],_0x5ec976[_0xf32bea(0x15c)]=_0x3ec73d,log(_0x5ec976[_0xf32bea(0x1ec)]+_0xf32bea(0x1b2)+_0x3ec73d+_0xf32bea(0x1d2)+_0x2496a1+(_0x19f14f[_0xf32bea(0xd3)]>0x0?_0xf32bea(0x228)+_0x4055ae+_0xf32bea(0x196)+_0x15d413+_0xf32bea(0x1a1)+_0x19f14f[_0xf32bea(0x1ec)]+_0xf32bea(0xca):'')+'!');else{if(_0x5ec976['powerup']===_0xf32bea(0xb1))_0x4055ae=0x7*_0x1f4355,_0x3ec73d=Math[_0xf32bea(0x78)](_0x4055ae*_0x2c7e37),_0x15d413=_0x4055ae-_0x3ec73d,_0x5ec976[_0xf32bea(0x100)]=Math[_0xf32bea(0x14d)](_0x5ec976[_0xf32bea(0x163)],_0x5ec976[_0xf32bea(0x100)]+_0x3ec73d),log(_0x5ec976['name']+_0xf32bea(0x189)+_0x3ec73d+'\x20HP'+_0x2496a1+(_0x19f14f['tactics']>0x0?'\x20(originally\x20'+_0x4055ae+_0xf32bea(0x196)+_0x15d413+_0xf32bea(0x1a1)+_0x19f14f['name']+_0xf32bea(0xca):'')+'!');else _0x5ec976[_0xf32bea(0x20f)]===_0xf32bea(0x1c4)&&(_0x4055ae=0x5*_0x1f4355,_0x3ec73d=Math[_0xf32bea(0x78)](_0x4055ae*_0x2c7e37),_0x15d413=_0x4055ae-_0x3ec73d,_0x5ec976['health']=Math[_0xf32bea(0x14d)](_0x5ec976[_0xf32bea(0x163)],_0x5ec976[_0xf32bea(0x100)]+_0x3ec73d),log(_0x5ec976['name']+_0xf32bea(0x1af)+_0x3ec73d+'\x20HP'+_0x2496a1+(_0x19f14f[_0xf32bea(0xd3)]>0x0?'\x20(originally\x20'+_0x4055ae+',\x20reduced\x20by\x20'+_0x15d413+_0xf32bea(0x1a1)+_0x19f14f[_0xf32bea(0x1ec)]+_0xf32bea(0xca):'')+'!'));}}this[_0xf32bea(0x73)](_0x5ec976);}[_0x59433c(0x73)](_0xc9c2e9){const _0x27740f=_0x59433c,_0x5ce060=_0xc9c2e9===this[_0x27740f(0xf0)]?p1Health:p2Health,_0x1f29c5=_0xc9c2e9===this[_0x27740f(0xf0)]?p1Hp:p2Hp,_0x432ff2=_0xc9c2e9[_0x27740f(0x100)]/_0xc9c2e9[_0x27740f(0x163)]*0x64;_0x5ce060[_0x27740f(0x13b)][_0x27740f(0xf8)]=_0x432ff2+'%';let _0xd7aabe;if(_0x432ff2>0x4b)_0xd7aabe=_0x27740f(0x88);else{if(_0x432ff2>0x32)_0xd7aabe=_0x27740f(0x19a);else _0x432ff2>0x19?_0xd7aabe=_0x27740f(0x173):_0xd7aabe=_0x27740f(0xc4);}_0x5ce060[_0x27740f(0x13b)]['backgroundColor']=_0xd7aabe,_0x1f29c5[_0x27740f(0xc7)]=_0xc9c2e9['health']+'/'+_0xc9c2e9['maxHealth'];}[_0x59433c(0x1da)](){const _0x142344=_0x59433c;if(this[_0x142344(0xd1)]===_0x142344(0xad)||this[_0x142344(0xad)]){console[_0x142344(0x105)](_0x142344(0x15e));return;}this[_0x142344(0x104)]=this['currentTurn']===this[_0x142344(0xf0)]?this[_0x142344(0x13d)]:this[_0x142344(0xf0)],this[_0x142344(0xd1)]=this['currentTurn']===this[_0x142344(0xf0)]?_0x142344(0x124):_0x142344(0x1fd),turnIndicator[_0x142344(0xc7)]=_0x142344(0x1f2)+this[_0x142344(0x19d)]+_0x142344(0x10a)+(this['currentTurn']===this[_0x142344(0xf0)]?_0x142344(0x6d):_0x142344(0x18f))+_0x142344(0x1b5),log(_0x142344(0x77)+(this[_0x142344(0x104)]===this[_0x142344(0xf0)]?'Player':_0x142344(0x18f))),this['currentTurn']===this['player2']&&setTimeout(()=>this[_0x142344(0x1fd)](),0x3e8);}[_0x59433c(0x1fd)](){const _0x382271=_0x59433c;if(this[_0x382271(0xd1)]!==_0x382271(0x1fd)||this[_0x382271(0x104)]!==this[_0x382271(0x13d)])return;this[_0x382271(0xd1)]=_0x382271(0x24d);const _0x353fcf=this[_0x382271(0x103)]();_0x353fcf?(log(this['player2'][_0x382271(0x1ec)]+_0x382271(0x1bf)+_0x353fcf['x1']+',\x20'+_0x353fcf['y1']+_0x382271(0x7b)+_0x353fcf['x2']+',\x20'+_0x353fcf['y2']+')'),this[_0x382271(0x195)](_0x353fcf['x1'],_0x353fcf['y1'],_0x353fcf['x2'],_0x353fcf['y2'])):(log(this[_0x382271(0x13d)][_0x382271(0x1ec)]+_0x382271(0x226)),this[_0x382271(0x1da)]());}[_0x59433c(0x103)](){const _0xe7119a=_0x59433c;for(let _0x12a7b9=0x0;_0x12a7b9<this[_0xe7119a(0x71)];_0x12a7b9++){for(let _0x363be0=0x0;_0x363be0<this[_0xe7119a(0xf8)];_0x363be0++){if(_0x363be0<this['width']-0x1&&this[_0xe7119a(0xeb)](_0x363be0,_0x12a7b9,_0x363be0+0x1,_0x12a7b9))return{'x1':_0x363be0,'y1':_0x12a7b9,'x2':_0x363be0+0x1,'y2':_0x12a7b9};if(_0x12a7b9<this[_0xe7119a(0x71)]-0x1&&this[_0xe7119a(0xeb)](_0x363be0,_0x12a7b9,_0x363be0,_0x12a7b9+0x1))return{'x1':_0x363be0,'y1':_0x12a7b9,'x2':_0x363be0,'y2':_0x12a7b9+0x1};}}return null;}['canMakeMatch'](_0x396316,_0x18772f,_0x44660f,_0xb94018){const _0x435d9c=_0x59433c,_0x3ab1be={...this[_0x435d9c(0x241)][_0x18772f][_0x396316]},_0x5b09af={...this['board'][_0xb94018][_0x44660f]};this['board'][_0x18772f][_0x396316]=_0x5b09af,this[_0x435d9c(0x241)][_0xb94018][_0x44660f]=_0x3ab1be;const _0xb00a8c=this[_0x435d9c(0x75)]()['length']>0x0;return this[_0x435d9c(0x241)][_0x18772f][_0x396316]=_0x3ab1be,this[_0x435d9c(0x241)][_0xb94018][_0x44660f]=_0x5b09af,_0xb00a8c;}async[_0x59433c(0x1a7)](){const _0x18d77f=_0x59433c;if(this[_0x18d77f(0xad)]||this[_0x18d77f(0x260)]){console['log'](_0x18d77f(0x110)+this[_0x18d77f(0xad)]+_0x18d77f(0x244)+this[_0x18d77f(0x260)]+',\x20currentLevel='+this['currentLevel']);return;}this[_0x18d77f(0x260)]=!![],console[_0x18d77f(0x105)](_0x18d77f(0x8b)+this[_0x18d77f(0x19d)]+_0x18d77f(0x136)+this[_0x18d77f(0xf0)][_0x18d77f(0x100)]+_0x18d77f(0x25a)+this['player2'][_0x18d77f(0x100)]);const _0x5be4a0=document[_0x18d77f(0x21b)](_0x18d77f(0x23b));if(this['player1'][_0x18d77f(0x100)]<=0x0){console[_0x18d77f(0x105)]('Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)'),this[_0x18d77f(0xad)]=!![],this[_0x18d77f(0xd1)]=_0x18d77f(0xad),gameOver[_0x18d77f(0xc7)]='You\x20Lose!',turnIndicator[_0x18d77f(0xc7)]=_0x18d77f(0x143),log(this[_0x18d77f(0x13d)]['name']+_0x18d77f(0x7e)+this[_0x18d77f(0xf0)]['name']+'!'),_0x5be4a0[_0x18d77f(0xc7)]=_0x18d77f(0x158),document[_0x18d77f(0x21b)](_0x18d77f(0x264))[_0x18d77f(0x13b)][_0x18d77f(0x13c)]=_0x18d77f(0x221);try{this[_0x18d77f(0x138)][_0x18d77f(0x1c5)][_0x18d77f(0xdf)]();}catch(_0x871295){console[_0x18d77f(0x6a)]('Error\x20playing\x20lose\x20sound:',_0x871295);}}else{if(this[_0x18d77f(0x13d)][_0x18d77f(0x100)]<=0x0){console[_0x18d77f(0x105)]('Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)'),this[_0x18d77f(0xad)]=!![],this[_0x18d77f(0xd1)]='gameOver',gameOver[_0x18d77f(0xc7)]='You\x20Win!',turnIndicator[_0x18d77f(0xc7)]=_0x18d77f(0x143),_0x5be4a0[_0x18d77f(0xc7)]=this['currentLevel']===opponentsConfig[_0x18d77f(0x1c0)]?_0x18d77f(0x9a):'NEXT\x20LEVEL',document[_0x18d77f(0x21b)]('game-over-container')[_0x18d77f(0x13b)][_0x18d77f(0x13c)]=_0x18d77f(0x221);if(this['currentTurn']===this[_0x18d77f(0xf0)]){const _0x4eb633=this['roundStats'][this['roundStats']['length']-0x1];if(_0x4eb633&&!_0x4eb633['completed']){_0x4eb633['healthPercentage']=this[_0x18d77f(0xf0)]['health']/this[_0x18d77f(0xf0)]['maxHealth']*0x64,_0x4eb633[_0x18d77f(0x20d)]=!![];const _0x3958a9=_0x4eb633[_0x18d77f(0x1ed)]>0x0?_0x4eb633['points']/_0x4eb633[_0x18d77f(0x1ed)]/0x64*(_0x4eb633[_0x18d77f(0x243)]+0x14)*(0x1+this['currentLevel']/0x38):0x0;log(_0x18d77f(0xe9)+_0x4eb633[_0x18d77f(0x20b)]+_0x18d77f(0x24c)+_0x4eb633[_0x18d77f(0x1ed)]+',\x20healthPercentage='+_0x4eb633[_0x18d77f(0x243)][_0x18d77f(0x229)](0x2)+_0x18d77f(0x1b7)+this[_0x18d77f(0x19d)]),log(_0x18d77f(0x9d)+_0x4eb633['points']+_0x18d77f(0xdd)+_0x4eb633[_0x18d77f(0x1ed)]+_0x18d77f(0x23f)+_0x4eb633['healthPercentage']+_0x18d77f(0x1f9)+this[_0x18d77f(0x19d)]+'\x20/\x2056)\x20=\x20'+_0x3958a9),this[_0x18d77f(0x16c)]+=_0x3958a9,log(_0x18d77f(0x249)+_0x4eb633[_0x18d77f(0x20b)]+_0x18d77f(0xf5)+_0x4eb633[_0x18d77f(0x1ed)]+',\x20Health\x20Left:\x20'+_0x4eb633[_0x18d77f(0x243)]['toFixed'](0x2)+'%'),log('Round\x20Score:\x20'+_0x3958a9+_0x18d77f(0x1c9)+this[_0x18d77f(0x16c)]);}}await this['saveScoreToDatabase'](this['currentLevel']);this[_0x18d77f(0x19d)]===opponentsConfig['length']?(this[_0x18d77f(0x138)]['finalWin'][_0x18d77f(0xdf)](),log(_0x18d77f(0x23e)+this[_0x18d77f(0x16c)]),this[_0x18d77f(0x16c)]=0x0,await this[_0x18d77f(0x156)](),log(_0x18d77f(0x170))):(this['currentLevel']+=0x1,await this[_0x18d77f(0xfb)](),console['log']('Progress\x20saved:\x20currentLevel='+this[_0x18d77f(0x19d)]),this[_0x18d77f(0x138)][_0x18d77f(0x80)][_0x18d77f(0xdf)]());const _0x2421b1=this[_0x18d77f(0x1a0)]+_0x18d77f(0x1ae)+this['player2'][_0x18d77f(0x1ec)]['toLowerCase']()['replace'](/ /g,'-')+_0x18d77f(0x1ce);p2Image[_0x18d77f(0x10b)]=_0x2421b1,p2Image[_0x18d77f(0x79)][_0x18d77f(0xfe)](_0x18d77f(0x233)),p1Image[_0x18d77f(0x79)][_0x18d77f(0xfe)]('winner'),this['renderBoard']();}}this[_0x18d77f(0x260)]=![],console['log'](_0x18d77f(0x261)+this[_0x18d77f(0x19d)]+',\x20gameOver='+this[_0x18d77f(0xad)]);}async[_0x59433c(0x14e)](_0x459a23){const _0x13da7e=_0x59433c,_0xc5684f={'level':_0x459a23,'score':this[_0x13da7e(0x16c)]};console[_0x13da7e(0x105)](_0x13da7e(0x1fa)+_0xc5684f[_0x13da7e(0xc5)]+_0x13da7e(0x12f)+_0xc5684f[_0x13da7e(0x160)]);try{const _0x557dc1=await fetch(_0x13da7e(0x202),{'method':_0x13da7e(0x174),'headers':{'Content-Type':_0x13da7e(0x1f6)},'body':JSON[_0x13da7e(0x7d)](_0xc5684f)});if(!_0x557dc1['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x557dc1[_0x13da7e(0x1ab)]);const _0x57b695=await _0x557dc1[_0x13da7e(0x24e)]();console['log']('Save\x20response:',_0x57b695),log(_0x13da7e(0x1f2)+_0x57b695[_0x13da7e(0xc5)]+'\x20Score:\x20'+_0x57b695[_0x13da7e(0x160)]['toFixed'](0x2)),_0x57b695[_0x13da7e(0x1ab)]===_0x13da7e(0x22a)?log(_0x13da7e(0x130)+_0x57b695[_0x13da7e(0xc5)]+_0x13da7e(0x240)+_0x57b695[_0x13da7e(0x160)][_0x13da7e(0x229)](0x2)+_0x13da7e(0x107)+_0x57b695[_0x13da7e(0xac)]):log(_0x13da7e(0x217)+_0x57b695[_0x13da7e(0x122)]);}catch(_0x2c89db){console[_0x13da7e(0x6a)](_0x13da7e(0x112),_0x2c89db),log('Error\x20saving\x20score:\x20'+_0x2c89db['message']);}}['applyAnimation'](_0x79f10b,_0x313ec9,_0x1c36e8,_0xeab1c){const _0x3ba12a=_0x59433c,_0x5ca3b8=_0x79f10b[_0x3ba12a(0x13b)]['transform']||'',_0x66f061=_0x5ca3b8[_0x3ba12a(0xd0)](_0x3ba12a(0x1d4))?_0x5ca3b8[_0x3ba12a(0x245)](/scaleX\([^)]+\)/)[0x0]:'';_0x79f10b[_0x3ba12a(0x13b)][_0x3ba12a(0xcf)]=_0x3ba12a(0x15b)+_0xeab1c/0x2/0x3e8+'s\x20linear',_0x79f10b[_0x3ba12a(0x13b)][_0x3ba12a(0x18e)]='translateX('+_0x313ec9+_0x3ba12a(0xc8)+_0x66f061,_0x79f10b['classList'][_0x3ba12a(0xfe)](_0x1c36e8),setTimeout(()=>{const _0x3eeff0=_0x3ba12a;_0x79f10b[_0x3eeff0(0x13b)]['transform']=_0x66f061,setTimeout(()=>{const _0x502d4b=_0x3eeff0;_0x79f10b[_0x502d4b(0x79)][_0x502d4b(0x108)](_0x1c36e8);},_0xeab1c/0x2);},_0xeab1c/0x2);}['animateAttack'](_0x26a873,_0x276de4,_0x5ead45){const _0x52b9f0=_0x59433c,_0x1a2ab5=_0x26a873===this[_0x52b9f0(0xf0)]?p1Image:p2Image,_0x125732=_0x26a873===this['player1']?0x1:-0x1,_0x33a9c6=Math['min'](0xa,0x2+_0x276de4*0.4),_0x2ca168=_0x125732*_0x33a9c6,_0x534a27=_0x52b9f0(0x215)+_0x5ead45;this[_0x52b9f0(0x253)](_0x1a2ab5,_0x2ca168,_0x534a27,0xc8);}[_0x59433c(0x185)](_0x34775b){const _0x533062=_0x59433c,_0x3c0f5a=_0x34775b===this[_0x533062(0xf0)]?p1Image:p2Image;this['applyAnimation'](_0x3c0f5a,0x0,'glow-power-up',0xc8);}['animateRecoil'](_0x53096d,_0xc628ab){const _0xba3e51=_0x59433c,_0x3bf53d=_0x53096d===this[_0xba3e51(0xf0)]?p1Image:p2Image,_0x2be7c7=_0x53096d===this[_0xba3e51(0xf0)]?-0x1:0x1,_0x246a3d=Math[_0xba3e51(0x14d)](0xa,0x2+_0xc628ab*0.4),_0x22b5d9=_0x2be7c7*_0x246a3d;this[_0xba3e51(0x253)](_0x3bf53d,_0x22b5d9,_0xba3e51(0x94),0xc8);}}function randomChoice(_0x10fdc9){const _0x4800df=_0x59433c;return _0x10fdc9[Math[_0x4800df(0x78)](Math[_0x4800df(0x21d)]()*_0x10fdc9['length'])];}function log(_0x19afbc){const _0x5471c5=_0x59433c,_0x491bf6=document['getElementById'](_0x5471c5(0x251)),_0x25f5d3=document['createElement']('li');_0x25f5d3[_0x5471c5(0xc7)]=_0x19afbc,_0x491bf6[_0x5471c5(0x14c)](_0x25f5d3,_0x491bf6[_0x5471c5(0x1e6)]),_0x491bf6[_0x5471c5(0x1b1)][_0x5471c5(0x1c0)]>0x32&&_0x491bf6['removeChild'](_0x491bf6[_0x5471c5(0x237)]),_0x491bf6[_0x5471c5(0x265)]=0x0;}const turnIndicator=document[_0x59433c(0x21b)](_0x59433c(0x1cf)),p1Name=document['getElementById'](_0x59433c(0xda)),p1Image=document[_0x59433c(0x21b)]('p1-image'),p1Health=document['getElementById']('p1-health'),p1Hp=document[_0x59433c(0x21b)](_0x59433c(0x242)),p1Strength=document['getElementById']('p1-strength'),p1Speed=document[_0x59433c(0x21b)](_0x59433c(0x157)),p1Tactics=document[_0x59433c(0x21b)](_0x59433c(0x21c)),p1Size=document[_0x59433c(0x21b)](_0x59433c(0x128)),p1Powerup=document[_0x59433c(0x21b)](_0x59433c(0x246)),p1Type=document[_0x59433c(0x21b)](_0x59433c(0x1f4)),p2Name=document['getElementById'](_0x59433c(0xe3)),p2Image=document[_0x59433c(0x21b)](_0x59433c(0x24f)),p2Health=document[_0x59433c(0x21b)](_0x59433c(0x1c6)),p2Hp=document[_0x59433c(0x21b)](_0x59433c(0x150)),p2Strength=document[_0x59433c(0x21b)]('p2-strength'),p2Speed=document[_0x59433c(0x21b)]('p2-speed'),p2Tactics=document[_0x59433c(0x21b)](_0x59433c(0x95)),p2Size=document['getElementById'](_0x59433c(0xb2)),p2Powerup=document[_0x59433c(0x21b)](_0x59433c(0x70)),p2Type=document['getElementById'](_0x59433c(0xf6)),battleLog=document[_0x59433c(0x21b)](_0x59433c(0x251)),gameOver=document[_0x59433c(0x21b)](_0x59433c(0xd5));async function getAssets(_0x164510){const _0x5f2990=_0x59433c;let _0x26c9fb=[];try{console['log'](_0x5f2990(0x20e));const _0x192641=await fetch('ajax/get-monstrocity-assets.php',{'method':_0x5f2990(0x174),'headers':{'Content-Type':_0x5f2990(0x1f6)},'body':JSON[_0x5f2990(0x7d)]({'theme':_0x5f2990(0x21f)})});console[_0x5f2990(0x105)](_0x5f2990(0x1c1),_0x192641[_0x5f2990(0x1ab)]);if(!_0x192641['ok'])throw new Error(_0x5f2990(0xb6)+_0x192641['status']);_0x26c9fb=await _0x192641[_0x5f2990(0x24e)](),console['log'](_0x5f2990(0x162),_0x26c9fb),!Array[_0x5f2990(0x11b)](_0x26c9fb)&&(_0x26c9fb=[_0x26c9fb]),_0x26c9fb=_0x26c9fb[_0x5f2990(0xe6)]((_0x341a3b,_0x1d5839)=>{const _0x275ee3=_0x5f2990,_0x261080={..._0x341a3b,'theme':_0x275ee3(0x21f),'name':_0x341a3b['name']||_0x275ee3(0xbe)+_0x1d5839,'strength':_0x341a3b[_0x275ee3(0xd7)]||0x4,'speed':_0x341a3b[_0x275ee3(0x81)]||0x4,'tactics':_0x341a3b[_0x275ee3(0xd3)]||0x4,'size':_0x341a3b[_0x275ee3(0x234)]||_0x275ee3(0x1ff),'type':_0x341a3b['type']||_0x275ee3(0x188),'powerup':_0x341a3b['powerup']||_0x275ee3(0xb1)};return console['log'](_0x275ee3(0x182)+_0x1d5839+'=',_0x261080),_0x261080;});}catch(_0x211ac6){console[_0x5f2990(0x6a)]('getAssets:\x20Monstrocity\x20fetch\x20error:',_0x211ac6),_0x26c9fb=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x5f2990(0x1ff),'type':'Base','powerup':_0x5f2990(0xb1),'theme':_0x5f2990(0x21f)},{'name':_0x5f2990(0x20a),'strength':0x3,'speed':0x5,'tactics':0x3,'size':'Small','type':_0x5f2990(0x188),'powerup':_0x5f2990(0x21a),'theme':_0x5f2990(0x21f)}],console[_0x5f2990(0x105)](_0x5f2990(0xb7),_0x26c9fb);}console[_0x5f2990(0x105)](_0x5f2990(0x21e),_0x26c9fb);if(_0x164510==='monstrocity')return console[_0x5f2990(0x105)](_0x5f2990(0x18b),_0x26c9fb),_0x26c9fb;console[_0x5f2990(0x105)]('getAssets:\x20Processing\x20NFT\x20theme=',_0x164510);const _0x2dfd72=document[_0x5f2990(0xff)]('#theme-select\x20option[value=\x22'+_0x164510+'\x22]');if(!_0x2dfd72)return console['warn']('getAssets:\x20Theme\x20option\x20not\x20found:\x20'+_0x164510),_0x26c9fb;const _0x14751c=_0x2dfd72[_0x5f2990(0x1d1)][_0x5f2990(0x85)]?_0x2dfd72[_0x5f2990(0x1d1)][_0x5f2990(0x85)][_0x5f2990(0x6c)](',')[_0x5f2990(0x1c3)](_0x1d0953=>_0x1d0953[_0x5f2990(0x248)]()):[];console[_0x5f2990(0x105)]('getAssets:\x20Policy\x20IDs=',_0x14751c);if(!_0x14751c[_0x5f2990(0x1c0)])return console['log'](_0x5f2990(0xc1)+_0x164510+_0x5f2990(0x1e8)),_0x26c9fb;const _0x525908=_0x2dfd72['dataset'][_0x5f2990(0x1a2)]?_0x2dfd72[_0x5f2990(0x1d1)][_0x5f2990(0x1a2)][_0x5f2990(0x6c)](',')[_0x5f2990(0x1c3)](_0x1cb9a9=>_0x1cb9a9[_0x5f2990(0x248)]()):[],_0x480ecb=_0x2dfd72['dataset'][_0x5f2990(0xcd)]?_0x2dfd72['dataset']['ipfsPrefixes'][_0x5f2990(0x6c)](',')['filter'](_0x244551=>_0x244551[_0x5f2990(0x248)]()):[],_0x37d0c0=_0x14751c['map']((_0x9c4f22,_0x487039)=>({'policyId':_0x9c4f22,'orientation':_0x525908['length']===0x1?_0x525908[0x0]:_0x525908[_0x487039]||_0x5f2990(0x268),'ipfsPrefix':_0x480ecb[_0x5f2990(0x1c0)]===0x1?_0x480ecb[0x0]:_0x480ecb[_0x487039]||_0x5f2990(0x86)}));console[_0x5f2990(0x105)](_0x5f2990(0x1a4),_0x37d0c0);let _0x173d87=[];try{const _0x38a158=JSON[_0x5f2990(0x7d)]({'policyIds':_0x37d0c0[_0x5f2990(0xe6)](_0x2c5cbb=>_0x2c5cbb['policyId']),'theme':_0x164510});console[_0x5f2990(0x105)](_0x5f2990(0x12b),_0x38a158);const _0x4584b1=await fetch(_0x5f2990(0xcb),{'method':_0x5f2990(0x174),'headers':{'Content-Type':'application/json'},'body':_0x38a158});console[_0x5f2990(0x105)](_0x5f2990(0xb3),_0x4584b1['status'],'ok=',_0x4584b1['ok']);const _0x22edf3=await _0x4584b1[_0x5f2990(0x190)]();console[_0x5f2990(0x105)](_0x5f2990(0xbc),_0x22edf3);if(!_0x4584b1['ok'])throw new Error(_0x5f2990(0xd2)+_0x4584b1[_0x5f2990(0x1ab)]);let _0x52f466;try{_0x52f466=JSON[_0x5f2990(0x16f)](_0x22edf3);}catch(_0x51dc65){console[_0x5f2990(0x6a)]('getAssets:\x20NFT\x20parse\x20error:',_0x51dc65,_0x5f2990(0x1ee),_0x22edf3);throw _0x51dc65;}console['log'](_0x5f2990(0xe7),_0x52f466),_0x52f466===![]||_0x52f466===_0x5f2990(0x129)?(console[_0x5f2990(0x105)](_0x5f2990(0xe5)),_0x173d87=[]):(_0x173d87=Array[_0x5f2990(0x11b)](_0x52f466)?_0x52f466:[_0x52f466],console[_0x5f2990(0x105)](_0x5f2990(0x11d),_0x173d87)),_0x173d87=_0x173d87[_0x5f2990(0xe6)]((_0x49babe,_0x343aa3)=>{const _0x5e83ec=_0x5f2990,_0x371127={..._0x49babe,'theme':_0x164510,'name':_0x49babe[_0x5e83ec(0x1ec)]||_0x5e83ec(0x155)+_0x343aa3,'strength':_0x49babe['strength']||0x4,'speed':_0x49babe[_0x5e83ec(0x81)]||0x4,'tactics':_0x49babe[_0x5e83ec(0xd3)]||0x4,'size':_0x49babe[_0x5e83ec(0x234)]||'Medium','type':_0x49babe[_0x5e83ec(0x201)]||_0x5e83ec(0x188),'powerup':_0x49babe['powerup']||_0x5e83ec(0xb1),'policyId':_0x49babe['policyId']||_0x37d0c0[0x0][_0x5e83ec(0x25f)],'ipfs':_0x49babe['ipfs']||''};return console['log']('getAssets:\x20Mapped\x20NFT\x20asset\x20'+_0x343aa3+'=',_0x371127),_0x371127;});}catch(_0x29ab0d){console[_0x5f2990(0x6a)](_0x5f2990(0x1b0)+_0x164510+':',_0x29ab0d),_0x173d87=[];}console[_0x5f2990(0x105)](_0x5f2990(0x102),_0x173d87);const _0xe9e1ce=[..._0x26c9fb,..._0x173d87];return console[_0x5f2990(0x105)](_0x5f2990(0x154),_0xe9e1ce),_0xe9e1ce;}function _0x4fe1(){const _0x21e1c9=['Game\x20over,\x20skipping\x20endTurn','targetTile','score','forEach','getAssets:\x20Monstrocity\x20data=','maxHealth','onload','init:\x20Prompting\x20with\x20loadedLevel=','\x20HP','Left','value','init:\x20Async\x20initialization\x20completed','offsetY','4711455UtvQXG','grandTotalScore','ajax/clear-monstrocity-progress.php','handleGameOverButton\x20started:\x20currentLevel=','parse','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','visibility','none','#FFA500','POST','addEventListener','translate(0px,\x200px)','transform\x200.2s\x20ease','Parsed\x20response:','checkMatches\x20started','toLowerCase','\x20(opponentsConfig[','\x27s\x20Last\x20Stand\x20mitigates\x20','Goblin\x20Ganger','winner','translate(0,\x20','countEmptyBelow','#theme-select\x20option[value=\x22','getAssets:\x20Mapped\x20Monstrocity\x20asset\x20','body','game-board','animatePowerup','\x20but\x20dulls\x20tactics\x20to\x20','abs','Base','\x20uses\x20Regen,\x20restoring\x20','boosts\x20health\x20to\x20','getAssets:\x20Returning\x20only\x20Monstrocity\x20assets=','progress-modal-buttons','\x20matches:','transform','Opponent','text','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','\x20HP!','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','\x20tiles!','slideTiles',',\x20reduced\x20by\x20','\x20uses\x20Last\x20Stand,\x20dealing\x20','Game\x20over,\x20skipping\x20cascadeTiles','\x20but\x20sharpens\x20tactics\x20to\x20','#FFC105',',\x20Total\x20damage:\x20','Spydrax','currentLevel','Main:\x20Game\x20instance\x20created','Loaded\x20opponent\x20for\x20level\x20','baseImagePath','\x20due\x20to\x20','orientations','Error\x20saving\x20progress:','getAssets:\x20Policies=','init:\x20Starting\x20async\x20initialization','flip-p1','checkGameOver','1904258WGyvog','div','element','status','inline-block','addEventListeners','battle-damaged/','\x20uses\x20Minor\x20Regen,\x20restoring\x20','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20','children','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','totalTiles','Drake','\x27s\x20Turn','initializing',',\x20level=','Large','Damage\x20from\x20match:\x20','logo.png','Reset\x20to\x20Level\x201:\x20currentLevel=','renderBoard','Texby',',\x20tiles\x20to\x20clear:','\x20swaps\x20tiles\x20at\x20(','length','getAssets:\x20Monstrocity\x20status=','\x20uses\x20','filter','Minor\x20Regen','loss','p2-health','Failed\x20to\x20save\x20progress:','1XYjHJL',',\x20Grand\x20Total\x20Score:\x20',',\x20Match\x20bonus:\x20',',\x20loadedScore=','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','1517640CejwsJ','.png','turn-indicator','Cascade:\x20','dataset','\x20damage','lastStandActive','scaleX','cascadeTilesWithoutRender','Craig','handleTouchMove','innerHTML','\x20damage,\x20but\x20','endTurn','showProgressPopup:\x20User\x20chose\x20Restart','updateTheme','Sending\x20saveProgress\x20request\x20with\x20data:','removeEventListener','Base\x20damage:\x20','Game\x20over,\x20skipping\x20cascade\x20resolution','updateOpponentDisplay','isTouchDevice','first-attack','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','theme-select','firstChild','Progress\x20saved:\x20Level\x20',',\x20returning\x20Monstrocity\x20assets','Error\x20clearing\x20progress:','round','last-stand','name','matches','raw=','imageUrl','find','removeChild','Level\x20','querySelectorAll','p1-type','clientX','application/json','https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg','<p>Strength:\x20','\x20+\x2020))\x20*\x20(1\x20+\x20','Saving\x20score:\x20level=',',\x20rows\x20','handleGameOverButton','aiTurn','681240iuwoxe','Medium','Cascade\x20complete,\x20ending\x20turn','type','ajax/save-monstrocity-score.php','\x27s\x20orientation\x20flipped\x20to\x20','\x20for\x20','theme','Progress\x20cleared','</p>','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','tileSizeWithGap','Dankle','points','cascadeTiles','completed','getAssets:\x20Fetching\x20Monstrocity\x20assets','powerup','initBoard','Battle\x20Damaged','\x20with\x20Score\x20of\x20','\x20(50%\x20bonus\x20for\x20match-4)','\x20starts\x20at\x20full\x20strength\x20with\x20','glow-','showCharacterSelect','Score\x20Not\x20Saved:\x20','playerCharactersConfig','restart','Heal','getElementById','p1-tactics','random','getAssets:\x20Monstrocity\x20assets\x20final=','monstrocity','mouseup','block','No\x20match,\x20reverting\x20tiles...','createElement','getBoundingClientRect','has','\x20passes...','42256kyuJCz','\x20(originally\x20','toFixed','success','touchstart','px,\x200)\x20scale(1.05)','indexOf','row','Game\x20over,\x20skipping\x20recoil\x20animation','battle-damaged','Resume\x20from\x20Level\x20','clientY','loser','size','character-select-container','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','lastChild','\x20(100%\x20bonus\x20for\x20match-5+)','boostActive','<p>Type:\x20','try-again','Slime\x20Mind','HTTP\x20error!\x20Status:\x20','Final\x20level\x20completed!\x20Final\x20score:\x20',')\x20/\x20100)\x20*\x20(',',\x20Score\x20','board','p1-hp','healthPercentage',',\x20isCheckingGameOver=','match','p1-powerup','Horizontal\x20match\x20found\x20at\x20row\x20','trim','Round\x20Won!\x20Points:\x20','alt','top',',\x20matches=','animating','json','p2-image','badMove','battle-log','Error\x20updating\x20theme\x20assets:','applyAnimation','Raw\x20response\x20text:','img','second-attack','ipfsPrefix','837TjAZgC','translate(',',\x20player2.health=','\x20health\x20before\x20match:\x20','className','\x20to\x20','falling','policyId','isCheckingGameOver','checkGameOver\x20completed:\x20currentLevel=','\x27s\x20Boost\x20fades.','roundStats','game-over-container','scrollTop','ipfs','showCharacterSelect:\x20Called\x20with\x20isInitial=','Right','\x27s\x20','Katastrophy','handleMatch','243bLVCVW','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','<p>Tactics:\x20','.game-container','px,\x200)','handleMatch\x20completed,\x20damage\x20dealt:\x20',',\x20cols\x20','error','hyperCube','split','Player','Minor\x20Régén','529698VqQANm','p2-powerup','height','cascade','updateHealth','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','checkMatches','px,\x20','Turn\x20switched\x20to\x20','floor','classList','preventDefault',')\x20to\x20(','\x20damage\x20to\x20','stringify','\x20defeats\x20','\x20goes\x20first!','win','speed','Restart','Boost\x20applied,\x20damage:\x20','Processing\x20match:','policyIds','https://ipfs.io/ipfs/','px)\x20scale(1.05)','#4CAF50','orientation',')\x20has\x20no\x20element\x20to\x20animate','checkGameOver\x20started:\x20currentLevel=','Error\x20loading\x20progress:','translate(0,\x200)','ontouchstart','Koipon','max','resolveMatches\x20started,\x20gameOver:','selected','mousemove','glow-recoil','p2-tactics','<p>Power-Up:\x20','https://www.skulliance.io/staking/sounds/voice_go.ogg','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','showProgressPopup:\x20User\x20chose\x20Resume','START\x20OVER','No\x20matches\x20found,\x20returning\x20false','getItem','Round\x20Score\x20Formula:\x20(((','backgroundImage','gameTheme','progress-modal-content','scaleX(-1)','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','touches','catch','Merdock','mousedown','p1-image','AI\x20Opponent',',\x20damage:\x20','visible','showCharacterSelect:\x20this.player1\x20set:\x20','attempts','gameOver','animateRecoil','Response\x20status:','button','Regenerate','p2-size','getAssets:\x20NFT\x20status=','base','setBackground','Monstrocity\x20HTTP\x20error!\x20Status:\x20','getAssets:\x20Using\x20default\x20Monstrocity\x20assets=','Vertical\x20match\x20found\x20at\x20col\x20','leader','dragDirection','\x20size\x20','getAssets:\x20NFT\x20raw\x20response=','\x20uses\x20Heal,\x20restoring\x20','Monstrocity_Unknown_','Shadow\x20Strike','Error\x20in\x20resolveMatches:','getAssets:\x20No\x20policy\x20IDs\x20for\x20theme\x20','flip-p2','msMaxTouchPoints','#F44336','level','https://www.skulliance.io/staking/images/monstrocity/','textContent','px)\x20','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','\x27s\x20tactics)','ajax/get-nft-assets.php','handleGameOverButton\x20completed:\x20currentLevel=','ipfsPrefixes','Small','transition','includes','gameState','NFT\x20HTTP\x20error!\x20Status:\x20','tactics','Animating\x20powerup','game-over','<p>Size:\x20','strength','isNFT','some','p1-name','Random','Clearing\x20matched\x20tiles:','\x20/\x20','multiMatch','play','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','tile\x20','p2-name','column','getAssets:\x20NFT\x20data\x20is\x20false,\x20skipping','map','getAssets:\x20NFT\x20parsed\x20data=','Multi-Match!\x20','Calculating\x20round\x20score:\x20points=','\x20steps\x20into\x20the\x20fray\x20with\x20','canMakeMatch','isDragging','initGame','change-character','Found\x20','player1','</strong></p>','flex','warn','https://www.skulliance.io/staking/sounds/hypercube_create.ogg',',\x20Matches:\x20','p2-type','https://www.skulliance.io/staking/icons/','width','Slash','\x20damage!','saveProgress','push','drops\x20health\x20to\x20','add','querySelector','health','Starting\x20Level\x20','getAssets:\x20NFT\x20assets\x20final=','findAIMove','currentTurn','log','...',',\x20Completions:\x20','remove','then','\x20-\x20','src','110330NguhPc','swapPlayerCharacter','resolveMatches','\x20health\x20after\x20damage:\x20','checkGameOver\x20skipped:\x20gameOver=','playerCharacters','Error\x20saving\x20to\x20database:','<img\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22\x20src=\x22','Total\x20damage\x20dealt:\x20','maxTouchPoints','progress','Last\x20Stand\x20applied,\x20mitigated\x20','replace','loadProgress','reset','isArray','offsetX','getAssets:\x20NFT\x20normalized=','progress-modal','handleMouseDown','showProgressPopup','tileTypes','message','createRandomTile','playerTurn','usePowerup','click','Calling\x20checkGameOver\x20from\x20handleMatch','p1-size','false','Mandiblus','getAssets:\x20Sending\x20NFT\x20POST=','isInitialMove:','appendChild','left',',\x20score=','Score\x20Saved:\x20Level\x20','init','<p>Speed:\x20','createCharacter','handleTouchEnd','Billandar\x20and\x20Ted',',\x20player1.health=','Animating\x20recoil\x20for\x20defender:','sounds','flipCharacter','getTileFromEvent','style','display','player2','Animating\x20matched\x20tiles,\x20allMatchedTiles:','\x20created\x20a\x20match\x20of\x20','Boost\x20Attack','updateTileSizeWithGap','updatePlayerDisplay','Game\x20Over','handleTouchStart','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','https://www.skulliance.io/staking/sounds/select.ogg','selectedTile','Leader','\x20on\x20','power-up','px)','insertBefore','min','saveScoreToDatabase','Round\x20points\x20increased\x20from\x20','p2-hp','addEventListeners:\x20Player\x201\x20image\x20clicked','coordinates','special-attack','getAssets:\x20Returning\x20merged\x20assets=','NFT_Unknown_','clearProgress','p1-speed','TRY\x20AGAIN','character-options','character-option','transform\x20','boostValue','handleMouseMove'];_0x4fe1=function(){return _0x21e1c9;};return _0x4fe1();}function _0x4623(_0x108313,_0x5655f8){const _0x4fe117=_0x4fe1();return _0x4623=function(_0x4623d6,_0x39686d){_0x4623d6=_0x4623d6-0x66;let _0x24b5ac=_0x4fe117[_0x4623d6];return _0x24b5ac;},_0x4623(_0x108313,_0x5655f8);}(function(){var _0x46b595=function(){const _0xc1a6dd=_0x4623;var _0xb27f94=localStorage[_0xc1a6dd(0x9c)](_0xc1a6dd(0x9f))||_0xc1a6dd(0x21f);getAssets(_0xb27f94)[_0xc1a6dd(0x109)](function(_0x504060){const _0x3480f9=_0xc1a6dd;console[_0x3480f9(0x105)]('Main:\x20Player\x20characters\x20loaded:',_0x504060);var _0x2dd356=new MonstrocityMatch3(_0x504060,_0xb27f94);console[_0x3480f9(0x105)](_0x3480f9(0x19e)),_0x2dd356[_0x3480f9(0x131)]()[_0x3480f9(0x109)](function(){const _0x5bc7bc=_0x3480f9;console[_0x5bc7bc(0x105)]('Main:\x20Game\x20initialized\x20successfully');});})[_0xc1a6dd(0xa4)](function(_0x3352ee){const _0xc1448a=_0xc1a6dd;console[_0xc1448a(0x6a)]('Main:\x20Error\x20initializing\x20game:',_0x3352ee);});};_0x46b595();}());
  </script>
</body>
</html>
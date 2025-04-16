<?php
session_start();

// Initialize default variables
$member = false;
$elite = false;
$innercircle = false;

// Restore session from cookie if logged out
if (!isset($_SESSION['logged_in'])) {
    if (isset($_COOKIE['SessionCookie'])) {
        $cookie = $_COOKIE['SessionCookie'];
        $cookieData = json_decode($cookie, true);
        if (is_array($cookieData)) {
            $_SESSION = $cookieData;
        }
    }
}

// Process user data only if valid session exists
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
    extract($_SESSION['userData']);
    if (isset($roles) && is_array($roles) && !empty($roles)) {
        foreach ($roles as $key => $roleData) {
            switch ($roleData) {
                case "949930195584954378":
                    $member = true;
                    break;
                case "949930360681140274":
                    $elite = true;
                    break;
                case "949930529841635348":
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
	    min-width: 150px;
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
	  margin-top: 30px;
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
	
	#theme-select-button {
	  padding: 10px 20px;
	  background-color: #49BBE3;
	  border: none;
	  border-radius: 5px;
	  cursor: pointer;
	  font-weight: bold;
	  font-size: 16px;
	  margin: 10px 0;
	  min-width: 150px;
	  color: black;
	}

	#theme-select-button:hover {
	  background-color: #54d4ff;
	}

	#theme-select-container {
	  position: fixed;
	  top: 50%;
	  left: 50%;
	  transform: translate(-50%, -50%);
	  background: #002f44;
	  padding: 20px;
	  z-index: 101; /* Above character-select-container */
	  width: 100%;
	  height: 100%;
	  overflow-y: auto;
	  border: 3px solid black;
	  text-align: center;
	  display: none;
	}
	
	#theme-select-container h2{
		margin-top: 30px;
	}

	#theme-close-button {
	  position: absolute;
	  top: 20px;
	  right: 20px;
	  padding: 10px 20px;
	  background-color: #f44336;
	  border: none;
	  border-radius: 5px;
	  cursor: pointer;
	  font-weight: bold;
	  font-size: 16px;
	  color: #fff;
	}

	#theme-close-button:hover {
	  background-color: #da190b;
	}

	.theme-group {
	  margin: 20px 0;
	  display: flex;
	  flex-wrap: wrap;
	  justify-content: center; /* Center themes horizontally */
	  text-align: center;
	}

	.theme-group h3 {
	  color: #fff;
	  font-size: 1.5em;
	  margin: 10px 0;
	  width: 100%; /* Ensure title spans container */
	  background-color: #165777;
	  padding: 10px;
	  border-top: 1px solid black;
	  border-bottom: 1px solid black;
	  margin-bottom: 30px;
	}

	.theme-option {
	  display: inline-block;
	  width: 350px;
	  height: 275px;
	  margin: 10px;
	  padding: 10px;
	  background-color: #121212; /* Fallback color */
	  background-size: cover;
	  background-position: center;
	  border-radius: 5px;
	  cursor: pointer;
	  transition: transform 0.2s ease, background 0.2s ease;
	  border: 1px solid black;
	  position: relative;
	  overflow: hidden;
	  text-align: center;
	}

	.theme-option:hover {
	  transform: scale(1.05);
	  background-color: rgba(32, 128, 173, 0.2); /* Blend with fallback on hover */
	}

	.theme-option img {
	  max-width: 80%;
	  max-height: 120px;
	  margin: 20px auto;
	  display: block;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
	  filter: drop-shadow(2px 5px 10px #000);
	}

	.theme-option img:hover::after {
	  content: attr(data-project);
	  position: absolute;
	  background: rgba(0, 0, 0, 0.8);
	  color: #fff;
	  padding: 5px 10px;
	  border-radius: 5px;
	  font-size: 0.8em;
	  bottom: 140px; /* Above title bar */
	  left: 50%;
	  transform: translateX(-50%);
	  z-index: 10;
	}

	.theme-option p {
	  margin: 0;
	  font-size: 0.9em;
	  color: #fff;
	  background: rgba(0, 0, 0, 0.7);
	  padding: 10px;
	  position: absolute;
	  bottom: 0;
	  left: 0;
	  right: 0;
	  text-align: center;
	  box-sizing: border-box;
	}

	@media (max-width: 1025px) {
	  .theme-option {
	    width: 250px;
	    height: 200px;
	  }

	  .theme-option img {
	    max-height: 80px;
	    margin: 10px auto;
	  }

	  .theme-option p {
	    font-size: 0.8em;
	    padding: 8px;
	  }
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
	  <h2>Staking Info</h2>
  	  <!--<p><a href="https://www.jpg.store/collection/monstrocity" target="_blank">Purchase Monstrocity NFTs</a> to Add More Characters</p>-->
  	  <p><a href="https://www.skulliance.io/staking" target="_blank">Visit Skulliance Staking</a> to Connect Wallet(s) and Load in Qualifying NFTs</p>
  	  <p>Leaderboards, Game Saves, and Rewards are Available to Skulliance Stakers</p>
      <button id="theme-select-button">Select Theme</button>
	  <h2>Select Character</h2>
      <div id="character-options"></div>
    </div>
	<!-- New Theme Select Modal -->
	<!-- Theme Select Template (initially empty, built by JS) -->
	<div id="theme-select-container" style="display: none;"></div>
  <script>
	  let updatePending = false;
	  // Theme data extracted from original <select>
	  const themes = [
	    {
	      group: "Partner Project Themes",
	      items: [
	        {
	          value: "monstrocity",
	          project: "Monstrocity",
	          title: "Default Game",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true
	        },
	        {
	          value: "discosolaris",
	          project: "Disco Solaris",
	          title: "Moebius Pioneers",
	          policyIds: "9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "oculuslounge",
	          project: "Disco Solaris",
	          title: "Oculus Lounge",
	          policyIds: "d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "havocworlds",
	          project: "Havoc Worlds",
	          title: "Season 1",
	          policyIds: "1088b361c41f49906645cedeeb7a9ef0e0b793b1a2d24f623ea74876",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "muses",
	          project: "Josh Howard",
	          title: "Muses of the Multiverse",
	          policyIds: "7f95b5948e3efed1171523757b472f24aecfab8303612cfa1b6fec55",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        }
	      ]
	    },
	    {
	      group: "Partner Artist Themes",
	      items: [
	        {
	          value: "bungking",
	          project: "Bungking",
	          title: "Yume",
	          policyIds: "f5a4009f12b9ee53b15edf338d1b7001641630be8308409b1477753b",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "cardanocamera",
	          project: "Cardano Camera",
	          title: "Galaxy of Sons",
	          policyIds: "647535c1befd741bfa1ace4a5508e93fe03ff7590c26d372c8a812cb",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "darkula",
	          project: "Darkula",
	          title: "Island of the Uncanny Neighbors",
	          policyIds: "b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "darkula2",
	          project: "Darkula",
	          title: "Island of the Violent Neighbors",
	          policyIds: "b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "maxi",
	          project: "Maxingo",
	          title: "Digital Hell Citizens 2: Fighters",
	          policyIds: "b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "shortyverse",
	          project: "Ohh Meed",
	          title: "Shorty Verse",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "shortyverse2",
	          project: "Ohh Meed",
	          title: "Shorty Verse Engaged",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "bogeyman",
	          project: "Ritual",
	          title: "Bogeyman",
	          policyIds: "bca7c472792b859fb18920477f917c94b76c9c9705e039bf08af0b63",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "ritual",
	          project: "Ritual",
	          title: "John Doe",
	          policyIds: "16b10d60f428b03fa5bafa631c848b2243f31cbf93cce1a65779e5f5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "sinderskullz",
	          project: "Sinder Skullz",
	          title: "Sinder Skullz",
	          policyIds: "83732ff37818e7e520592fcd3e5257e429307d40a9f5437240e926de",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "skowl",
	          project: "Skowl",
	          title: "Derivative Heroes",
	          policyIds: "d38910b4b5bd3e634138dc027b507b52406acf687889e3719aa4f7cf",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        }
	      ]
	    },
	    {
	      group: "Bonus Themes",
	      items: [
	        {
	          value: "adapunks",
	          project: "ADA Punks",
	          title: "ADA Punks",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true
	        }
	      ]
	    }
	    <?php if($innercircle) { ?>
	    ,
	    {
	      group: "Inner Circle Top Secret Themes",
	      items: [
	        {
	          value: "occultarchives",
	          project: "Billy Martin",
	          title: "Occult Archives",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true
	        },
	        {
	          value: "rubberrebels",
	          project: "Classic Cardtoons",
	          title: "Rubber Rebels",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true	
	        },
	        {
	          value: "danketsu",
	          project: "Danketsu",
	          title: "Legends",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true
	        },
	        {
	          value: "danketsu2",
	          project: "Danketsu",
	          title: "The Fourth",
	          policyIds: "a4b7f3bbb16b028739efc983967f1e631883f63a2671d508023b5dfb",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "deadpophell",
	          project: "Dead Pop Hell",
	          title: "NSFW",
	          policyIds: "6710d32c862a616ba81ef00294e60fe56969949e0225452c48b5f0ed",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "moebiuspioneers",
	          project: "Disco Solaris",
	          title: "Legends",
	          policyIds: "9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        },
	        {
	          value: "karranka",
	          project: "Karranka",
	          title: "Badass Heroes",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true
	        },
	        {
	          value: "karranka2",
	          project: "Karranka",
	          title: "Japanese Ghosts: Legendary Warriors",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true
	        },
	        {
	          value: "omen",
	          project: "Nemonium",
	          title: "Omen Legends",
	          policyIds: "da286f15e0de865e3d50fec6fa0484d7e2309671dc4ba8ce6bdd122b",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true
	        }
	      ]
	    }
	    <?php } ?>
	  ];
	  
	  const _0x250edb=_0x5db5;(function(_0x181529,_0x59f1ac){const _0xcbe329=_0x5db5,_0xfeb5bd=_0x181529();while(!![]){try{const _0x3d27da=parseInt(_0xcbe329(0x34f))/0x1+parseInt(_0xcbe329(0x1a4))/0x2*(parseInt(_0xcbe329(0x323))/0x3)+-parseInt(_0xcbe329(0x325))/0x4*(parseInt(_0xcbe329(0x2f6))/0x5)+parseInt(_0xcbe329(0x14a))/0x6*(-parseInt(_0xcbe329(0x19d))/0x7)+parseInt(_0xcbe329(0x186))/0x8*(parseInt(_0xcbe329(0x257))/0x9)+-parseInt(_0xcbe329(0x188))/0xa+-parseInt(_0xcbe329(0x1e3))/0xb*(parseInt(_0xcbe329(0x2cf))/0xc);if(_0x3d27da===_0x59f1ac)break;else _0xfeb5bd['push'](_0xfeb5bd['shift']());}catch(_0x547a77){_0xfeb5bd['push'](_0xfeb5bd['shift']());}}}(_0x563e,0x649d9));function showThemeSelect(_0x718fa){const _0x189fe3=_0x5db5;console[_0x189fe3(0x332)](_0x189fe3(0x26a));let _0x1283cb=document['getElementById'](_0x189fe3(0x196));const _0x2aa682=document['getElementById'](_0x189fe3(0x215));_0x1283cb['innerHTML']=_0x189fe3(0x230);const _0x379658=document[_0x189fe3(0x2fa)]('theme-options');_0x1283cb['style'][_0x189fe3(0x223)]=_0x189fe3(0x210),_0x2aa682[_0x189fe3(0x206)][_0x189fe3(0x223)]=_0x189fe3(0x302),themes[_0x189fe3(0x131)](_0x357b8d=>{const _0x5473f2=_0x189fe3,_0x2230d3=document['createElement']('div');_0x2230d3[_0x5473f2(0x34e)]=_0x5473f2(0x13e);const _0xb0c20f=document['createElement']('h3');_0xb0c20f[_0x5473f2(0x2b2)]=_0x357b8d[_0x5473f2(0x2dd)],_0x2230d3[_0x5473f2(0x342)](_0xb0c20f),_0x357b8d['items'][_0x5473f2(0x131)](_0x208a23=>{const _0x149d44=_0x5473f2,_0xabb47=document[_0x149d44(0x13a)]('div');_0xabb47[_0x149d44(0x34e)]=_0x149d44(0x298);if(_0x208a23[_0x149d44(0x339)]){const _0xebf221='https://www.skulliance.io/staking/images/monstrocity/'+_0x208a23[_0x149d44(0x311)]+_0x149d44(0x154);_0xabb47[_0x149d44(0x206)][_0x149d44(0x2c7)]=_0x149d44(0x1b6)+_0xebf221+')';}const _0x4a3f3c='https://www.skulliance.io/staking/images/monstrocity/'+_0x208a23[_0x149d44(0x311)]+'/logo.png';_0xabb47['innerHTML']=_0x149d44(0x1fa)+_0x4a3f3c+_0x149d44(0x239)+_0x208a23[_0x149d44(0x138)]+'\x22\x20data-project=\x22'+_0x208a23[_0x149d44(0x232)]+_0x149d44(0x24f)+_0x208a23[_0x149d44(0x138)]+_0x149d44(0x193),_0xabb47[_0x149d44(0x1f8)](_0x149d44(0x313),()=>{const _0x29697f=_0x149d44,_0x5d0284=document['getElementById'](_0x29697f(0x31e));_0x5d0284&&(_0x5d0284['innerHTML']=_0x29697f(0x1f6)),_0x1283cb[_0x29697f(0x2c2)]='',_0x1283cb[_0x29697f(0x206)][_0x29697f(0x223)]=_0x29697f(0x302),_0x2aa682[_0x29697f(0x206)][_0x29697f(0x223)]=_0x29697f(0x210),_0x718fa[_0x29697f(0x32c)](_0x208a23[_0x29697f(0x311)]);}),_0x2230d3[_0x149d44(0x342)](_0xabb47);}),_0x379658['appendChild'](_0x2230d3);}),console[_0x189fe3(0x335)](_0x189fe3(0x26a));}function _0x5db5(_0xb3e477,_0x61f793){const _0x563e29=_0x563e();return _0x5db5=function(_0x5db57e,_0xfbb47c){_0x5db57e=_0x5db57e-0x124;let _0xb2e0b2=_0x563e29[_0x5db57e];return _0xb2e0b2;},_0x5db5(_0xb3e477,_0x61f793);}const opponentsConfig=[{'name':_0x250edb(0x2bb),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x250edb(0x24c),'type':'Base','powerup':_0x250edb(0x2f0),'theme':_0x250edb(0x2ae)},{'name':'Merdock','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x250edb(0x168),'type':_0x250edb(0x330),'powerup':'Minor\x20Regen','theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x18d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x250edb(0x1d7),'type':_0x250edb(0x330),'powerup':_0x250edb(0x2f0),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x195),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x250edb(0x24c),'type':'Base','powerup':_0x250edb(0x2f0),'theme':'monstrocity'},{'name':_0x250edb(0x130),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x250edb(0x24c),'type':_0x250edb(0x330),'powerup':_0x250edb(0x147),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x312),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x250edb(0x330),'powerup':'Regenerate','theme':_0x250edb(0x2ae)},{'name':'Slime\x20Mind','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x250edb(0x1d7),'type':_0x250edb(0x330),'powerup':'Regenerate','theme':'monstrocity'},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x250edb(0x24c),'type':'Base','powerup':_0x250edb(0x147),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x1d0),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x250edb(0x24c),'type':_0x250edb(0x330),'powerup':_0x250edb(0x1c2),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x2fd),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x250edb(0x24c),'type':_0x250edb(0x330),'powerup':'Boost\x20Attack','theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x179),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':'Base','powerup':_0x250edb(0x20f),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x1cd),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x250edb(0x168),'type':_0x250edb(0x330),'powerup':'Heal','theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x15d),'strength':0x7,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x250edb(0x330),'powerup':_0x250edb(0x20f),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x228),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x250edb(0x24c),'type':'Base','powerup':_0x250edb(0x20f),'theme':'monstrocity'},{'name':'Craig','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x2f0),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x307),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x250edb(0x168),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x2f0),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x18d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Small','type':_0x250edb(0x1b5),'powerup':'Minor\x20Regen','theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x195),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x1b3),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x130),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x147),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x312),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x147),'theme':'monstrocity'},{'name':_0x250edb(0x213),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x250edb(0x1d7),'type':_0x250edb(0x1b5),'powerup':'Regenerate','theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x32d),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x147),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x1d0),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x1c2),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x2fd),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':'Leader','powerup':_0x250edb(0x1c2),'theme':_0x250edb(0x2ae)},{'name':'Spydrax','strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x250edb(0x1d7),'type':_0x250edb(0x1b5),'powerup':_0x250edb(0x20f),'theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x1cd),'strength':0x7,'speed':0x7,'tactics':0x7,'size':'Large','type':_0x250edb(0x1b5),'powerup':'Heal','theme':_0x250edb(0x2ae)},{'name':_0x250edb(0x15d),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x250edb(0x24c),'type':'Leader','powerup':_0x250edb(0x20f),'theme':'monstrocity'},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x250edb(0x24c),'type':_0x250edb(0x1b5),'powerup':'Heal','theme':'monstrocity'}],characterDirections={'Billandar\x20and\x20Ted':_0x250edb(0x17e),'Craig':_0x250edb(0x17e),'Dankle':'Left','Drake':_0x250edb(0x334),'Goblin\x20Ganger':_0x250edb(0x17e),'Jarhead':_0x250edb(0x334),'Katastrophy':_0x250edb(0x334),'Koipon':_0x250edb(0x17e),'Mandiblus':_0x250edb(0x17e),'Merdock':_0x250edb(0x17e),'Ouchie':_0x250edb(0x17e),'Slime\x20Mind':_0x250edb(0x334),'Spydrax':'Right','Texby':'Left'};class MonstrocityMatch3{constructor(_0x5f0546,_0x40c8dc){const _0xa96753=_0x250edb;this[_0xa96753(0x319)]=_0xa96753(0x21f)in window||navigator[_0xa96753(0x29b)]>0x0||navigator[_0xa96753(0x305)]>0x0,this[_0xa96753(0x19a)]=0x5,this[_0xa96753(0x1d2)]=0x5,this[_0xa96753(0x34c)]=[],this[_0xa96753(0x1aa)]=null,this[_0xa96753(0x22c)]=![],this[_0xa96753(0x12d)]=null,this[_0xa96753(0x321)]=null,this['player2']=null,this['gameState']=_0xa96753(0x260),this['isDragging']=![],this[_0xa96753(0x209)]=null,this['dragDirection']=null,this['offsetX']=0x0,this['offsetY']=0x0,this['currentLevel']=0x1,this[_0xa96753(0x292)]=_0x5f0546,this['playerCharacters']=[],this['isCheckingGameOver']=![],this[_0xa96753(0x157)]=[_0xa96753(0x32a),_0xa96753(0x33f),_0xa96753(0x265),_0xa96753(0x250),'last-stand'],this['roundStats']=[],this[_0xa96753(0x1b4)]=0x0;const _0x5ebe15=themes['flatMap'](_0x231875=>_0x231875[_0xa96753(0x166)])[_0xa96753(0x233)](_0x311b4b=>_0x311b4b[_0xa96753(0x311)]),_0x369eca=localStorage[_0xa96753(0x1c3)](_0xa96753(0x316));this['theme']=_0x369eca&&_0x5ebe15['includes'](_0x369eca)?_0x369eca:_0x40c8dc&&_0x5ebe15[_0xa96753(0x21c)](_0x40c8dc)?_0x40c8dc:_0xa96753(0x2ae),console[_0xa96753(0x2c4)]('constructor:\x20initialTheme='+_0x40c8dc+_0xa96753(0x266)+_0x369eca+_0xa96753(0x261)+this[_0xa96753(0x237)]),this[_0xa96753(0x227)]='https://www.skulliance.io/staking/images/monstrocity/'+this[_0xa96753(0x237)]+'/',this['sounds']={'match':new Audio(_0xa96753(0x208)),'cascade':new Audio(_0xa96753(0x208)),'badMove':new Audio(_0xa96753(0x2c8)),'gameOver':new Audio(_0xa96753(0x340)),'reset':new Audio(_0xa96753(0x182)),'loss':new Audio(_0xa96753(0x2e3)),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio('https://www.skulliance.io/staking/sounds/badgeawarded.ogg'),'powerGem':new Audio(_0xa96753(0x350)),'hyperCube':new Audio(_0xa96753(0x1fd)),'multiMatch':new Audio(_0xa96753(0x1e8))},this[_0xa96753(0x1ce)](),this[_0xa96753(0x30b)]();}async[_0x250edb(0x17d)](){const _0x3a2058=_0x250edb;console[_0x3a2058(0x2c4)]('init:\x20Starting\x20async\x20initialization'),this[_0x3a2058(0x189)]=this[_0x3a2058(0x292)][_0x3a2058(0x233)](_0x39e30b=>this[_0x3a2058(0x2c6)](_0x39e30b)),await this['showCharacterSelect'](!![]);const _0x21513e=await this['loadProgress'](),{loadedLevel:_0x321a5e,loadedScore:_0x3ac7e0,hasProgress:_0x5c1293}=_0x21513e;if(_0x5c1293){console[_0x3a2058(0x2c4)](_0x3a2058(0x1a5)+_0x321a5e+_0x3a2058(0x2de)+_0x3ac7e0);const _0x48cbf4=await this[_0x3a2058(0x2da)](_0x321a5e,_0x3ac7e0);_0x48cbf4?(this[_0x3a2058(0x13d)]=_0x321a5e,this['grandTotalScore']=_0x3ac7e0,log(_0x3a2058(0x247)+this[_0x3a2058(0x13d)]+_0x3a2058(0x308)+this['grandTotalScore'])):(this[_0x3a2058(0x13d)]=0x1,this[_0x3a2058(0x1b4)]=0x0,await this[_0x3a2058(0x178)](),log(_0x3a2058(0x16f)));}else this[_0x3a2058(0x13d)]=0x1,this['grandTotalScore']=0x0,log(_0x3a2058(0x1b9));console[_0x3a2058(0x2c4)](_0x3a2058(0x30d));}[_0x250edb(0x277)](){const _0x301900=_0x250edb;console[_0x301900(0x2c4)](_0x301900(0x336)+this[_0x301900(0x237)]);const _0x3adf44=themes[_0x301900(0x2f8)](_0x3d5596=>_0x3d5596['items'])[_0x301900(0x262)](_0x25dbf7=>_0x25dbf7[_0x301900(0x311)]===this[_0x301900(0x237)]);console[_0x301900(0x2c4)](_0x301900(0x278),_0x3adf44);const _0x507601=_0x301900(0x2a1)+this[_0x301900(0x237)]+_0x301900(0x154);console[_0x301900(0x2c4)](_0x301900(0x322)+_0x507601),_0x3adf44&&_0x3adf44[_0x301900(0x339)]?(document[_0x301900(0x20a)][_0x301900(0x206)][_0x301900(0x2c7)]=_0x301900(0x1b6)+_0x507601+')',document[_0x301900(0x20a)][_0x301900(0x206)]['backgroundSize']=_0x301900(0x25f),document[_0x301900(0x20a)][_0x301900(0x206)]['backgroundPosition']=_0x301900(0x1bb)):document['body'][_0x301900(0x206)][_0x301900(0x2c7)]='none';}[_0x250edb(0x32c)](_0x30dfa4){const _0x2c177f=_0x250edb;if(updatePending){console[_0x2c177f(0x2c4)](_0x2c177f(0x317));return;}updatePending=!![],console[_0x2c177f(0x332)](_0x2c177f(0x1dc)+_0x30dfa4);var _0x316a40=this;this[_0x2c177f(0x237)]=_0x30dfa4,this[_0x2c177f(0x227)]=_0x2c177f(0x2a1)+this[_0x2c177f(0x237)]+'/',localStorage[_0x2c177f(0x125)]('gameTheme',this[_0x2c177f(0x237)]),this[_0x2c177f(0x277)](),getAssets(this[_0x2c177f(0x237)])[_0x2c177f(0x33a)](function(_0xd09cc0){const _0x24bebc=_0x2c177f;console[_0x24bebc(0x332)]('updateCharacters_'+_0x30dfa4),_0x316a40[_0x24bebc(0x292)]=_0xd09cc0,_0x316a40[_0x24bebc(0x189)]=[],_0xd09cc0[_0x24bebc(0x131)](_0x2bc4d3=>{const _0x1f36f1=_0x24bebc,_0x328717=_0x316a40[_0x1f36f1(0x2c6)](_0x2bc4d3),_0x7241ce=new Image();_0x7241ce[_0x1f36f1(0x2bf)]=_0x328717[_0x1f36f1(0x218)],_0x7241ce[_0x1f36f1(0x2ce)]=()=>console[_0x1f36f1(0x2c4)]('Preloaded:\x20'+_0x328717[_0x1f36f1(0x218)]),_0x7241ce[_0x1f36f1(0x288)]=()=>console[_0x1f36f1(0x2c4)]('Failed\x20to\x20preload:\x20'+_0x328717[_0x1f36f1(0x218)]),_0x316a40[_0x1f36f1(0x189)][_0x1f36f1(0x2af)](_0x328717);});if(_0x316a40[_0x24bebc(0x321)]){var _0x42e04b=_0x316a40['playerCharactersConfig'][_0x24bebc(0x262)](function(_0xf7827a){const _0x57d1aa=_0x24bebc;return _0xf7827a[_0x57d1aa(0x2e1)]===_0x316a40[_0x57d1aa(0x321)][_0x57d1aa(0x2e1)];})||_0x316a40['playerCharactersConfig'][0x0];_0x316a40[_0x24bebc(0x321)]=_0x316a40[_0x24bebc(0x2c6)](_0x42e04b),_0x316a40[_0x24bebc(0x2d9)]();}_0x316a40['player2']&&(_0x316a40[_0x24bebc(0x285)]=_0x316a40[_0x24bebc(0x2c6)](opponentsConfig[_0x316a40[_0x24bebc(0x13d)]-0x1]),_0x316a40[_0x24bebc(0x346)]());document[_0x24bebc(0x1d3)](_0x24bebc(0x14c))['src']=_0x316a40['baseImagePath']+_0x24bebc(0x343);var _0x27ff3c=document[_0x24bebc(0x2fa)](_0x24bebc(0x215));_0x27ff3c[_0x24bebc(0x206)][_0x24bebc(0x223)]===_0x24bebc(0x210)&&_0x316a40[_0x24bebc(0x301)](_0x316a40[_0x24bebc(0x321)]===null),console[_0x24bebc(0x335)](_0x24bebc(0x2fb)+_0x30dfa4),console['timeEnd'](_0x24bebc(0x1dc)+_0x30dfa4),updatePending=![];})[_0x2c177f(0x1fb)](function(_0x340a18){const _0x1e8e57=_0x2c177f;console[_0x1e8e57(0x2e5)](_0x1e8e57(0x2b6),_0x340a18),console[_0x1e8e57(0x335)](_0x1e8e57(0x1dc)+_0x30dfa4),updatePending=![];});}async[_0x250edb(0x2ea)](){const _0x270032=_0x250edb,_0x227222={'currentLevel':this[_0x270032(0x13d)],'grandTotalScore':this['grandTotalScore']};console[_0x270032(0x2c4)](_0x270032(0x205),_0x227222);try{const _0x41f9d6=await fetch(_0x270032(0x14f),{'method':_0x270032(0x2ee),'headers':{'Content-Type':'application/json'},'body':JSON['stringify'](_0x227222)});console[_0x270032(0x2c4)]('Response\x20status:',_0x41f9d6[_0x270032(0x19c)]);const _0x1bfaed=await _0x41f9d6[_0x270032(0x220)]();console[_0x270032(0x2c4)](_0x270032(0x300),_0x1bfaed);if(!_0x41f9d6['ok'])throw new Error(_0x270032(0x33b)+_0x41f9d6[_0x270032(0x19c)]);const _0x4bf8c5=JSON['parse'](_0x1bfaed);console[_0x270032(0x2c4)](_0x270032(0x284),_0x4bf8c5),_0x4bf8c5[_0x270032(0x19c)]===_0x270032(0x29c)?log(_0x270032(0x2ab)+this['currentLevel']):console[_0x270032(0x2e5)](_0x270032(0x202),_0x4bf8c5[_0x270032(0x2e2)]);}catch(_0x37bf88){console[_0x270032(0x2e5)](_0x270032(0x187),_0x37bf88);}}async[_0x250edb(0x31a)](){const _0x4db514=_0x250edb;try{console[_0x4db514(0x2c4)]('Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php');const _0x2c5223=await fetch('ajax/load-monstrocity-progress.php',{'method':'GET','headers':{'Content-Type':_0x4db514(0x240)}});console[_0x4db514(0x2c4)](_0x4db514(0x28e),_0x2c5223[_0x4db514(0x19c)]);if(!_0x2c5223['ok'])throw new Error(_0x4db514(0x33b)+_0x2c5223[_0x4db514(0x19c)]);const _0x8a7405=await _0x2c5223[_0x4db514(0x13b)]();console[_0x4db514(0x2c4)](_0x4db514(0x284),_0x8a7405);if(_0x8a7405[_0x4db514(0x19c)]===_0x4db514(0x29c)&&_0x8a7405[_0x4db514(0x25d)]){const _0x39226c=_0x8a7405[_0x4db514(0x25d)];return{'loadedLevel':_0x39226c['currentLevel']||0x1,'loadedScore':_0x39226c[_0x4db514(0x1b4)]||0x0,'hasProgress':!![]};}else return console[_0x4db514(0x2c4)](_0x4db514(0x1b2),_0x8a7405),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x58e949){return console[_0x4db514(0x2e5)](_0x4db514(0x1bf),_0x58e949),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x250edb(0x178)](){const _0xc6d96a=_0x250edb;try{const _0x8f5ed3=await fetch('ajax/clear-monstrocity-progress.php',{'method':_0xc6d96a(0x2ee),'headers':{'Content-Type':_0xc6d96a(0x240)}});if(!_0x8f5ed3['ok'])throw new Error(_0xc6d96a(0x33b)+_0x8f5ed3[_0xc6d96a(0x19c)]);const _0x33f215=await _0x8f5ed3[_0xc6d96a(0x13b)]();_0x33f215[_0xc6d96a(0x19c)]==='success'&&(this[_0xc6d96a(0x13d)]=0x1,this[_0xc6d96a(0x1b4)]=0x0,log(_0xc6d96a(0x2bc)));}catch(_0x3efaa5){console[_0xc6d96a(0x2e5)](_0xc6d96a(0x2b0),_0x3efaa5);}}['updateTileSizeWithGap'](){const _0x4a66a9=_0x250edb,_0x548fe8=document['getElementById']('game-board'),_0x17d650=_0x548fe8[_0x4a66a9(0x2b3)]||0x12c;this[_0x4a66a9(0x326)]=(_0x17d650-0.5*(this[_0x4a66a9(0x19a)]-0x1))/this['width'];}['createCharacter'](_0x42643b){const _0x1371e7=_0x250edb;console['log'](_0x1371e7(0x2e0),_0x42643b);var _0x57a24c,_0x1c52e9,_0x30aae8=_0x1371e7(0x17e),_0x244051=![];if(_0x42643b[_0x1371e7(0x291)]&&_0x42643b['policyId']){_0x244051=!![];var _0x45a9e8=document[_0x1371e7(0x1d3)](_0x1371e7(0x2ad)+_0x42643b[_0x1371e7(0x237)]+'\x22]'),_0x369dd2={'orientation':'Right','ipfsPrefix':'https://ipfs.io/ipfs/'};if(_0x45a9e8){var _0x31a464=_0x45a9e8[_0x1371e7(0x207)][_0x1371e7(0x1fe)]?_0x45a9e8[_0x1371e7(0x207)][_0x1371e7(0x1fe)][_0x1371e7(0x24e)](',')[_0x1371e7(0x263)](function(_0x417c88){const _0x4c4991=_0x1371e7;return _0x417c88[_0x4c4991(0x352)]();}):[],_0x5049e6=_0x45a9e8['dataset'][_0x1371e7(0x192)]?_0x45a9e8['dataset']['orientations'][_0x1371e7(0x24e)](',')[_0x1371e7(0x263)](function(_0x1c4942){const _0x215dd9=_0x1371e7;return _0x1c4942[_0x215dd9(0x352)]();}):[],_0x2db3e3=_0x45a9e8[_0x1371e7(0x207)][_0x1371e7(0x159)]?_0x45a9e8[_0x1371e7(0x207)][_0x1371e7(0x159)][_0x1371e7(0x24e)](',')[_0x1371e7(0x263)](function(_0x4a2620){return _0x4a2620['trim']();}):[],_0x2712d4=_0x31a464[_0x1371e7(0x2df)](_0x42643b['policyId']);_0x2712d4!==-0x1&&(_0x369dd2={'orientation':_0x5049e6[_0x1371e7(0x254)]===0x1?_0x5049e6[0x0]:_0x5049e6[_0x2712d4]||_0x1371e7(0x334),'ipfsPrefix':_0x2db3e3[_0x1371e7(0x254)]===0x1?_0x2db3e3[0x0]:_0x2db3e3[_0x2712d4]||_0x1371e7(0x136)});}_0x369dd2[_0x1371e7(0x32b)]===_0x1371e7(0x1e4)?_0x30aae8=Math[_0x1371e7(0x1ac)]()<0.5?_0x1371e7(0x17e):'Right':_0x30aae8=_0x369dd2[_0x1371e7(0x32b)],_0x1c52e9=_0x369dd2[_0x1371e7(0x127)]+_0x42643b[_0x1371e7(0x291)];}else{switch(_0x42643b[_0x1371e7(0x15c)]){case _0x1371e7(0x330):_0x57a24c=_0x1371e7(0x17a);break;case _0x1371e7(0x1b5):_0x57a24c=_0x1371e7(0x1d8);break;case _0x1371e7(0x297):_0x57a24c=_0x1371e7(0x177);break;default:_0x57a24c=_0x1371e7(0x17a);}_0x1c52e9=this[_0x1371e7(0x227)]+_0x57a24c+'/'+_0x42643b[_0x1371e7(0x2e1)][_0x1371e7(0x268)]()[_0x1371e7(0x26c)](/ /g,'-')+_0x1371e7(0x214),_0x30aae8=characterDirections[_0x42643b[_0x1371e7(0x2e1)]]||_0x1371e7(0x17e);}var _0x40f3e4;switch(_0x42643b[_0x1371e7(0x15c)]){case _0x1371e7(0x1b5):_0x40f3e4=0x64;break;case _0x1371e7(0x297):_0x40f3e4=0x46;break;case'Base':default:_0x40f3e4=0x55;}var _0x37f124=0x1,_0x4eef04=0x0;switch(_0x42643b['size']){case _0x1371e7(0x168):_0x37f124=1.2,_0x4eef04=_0x42643b[_0x1371e7(0x2a5)]>0x1?-0x2:0x0;break;case'Small':_0x37f124=0.8,_0x4eef04=_0x42643b[_0x1371e7(0x2a5)]<0x6?0x2:0x7-_0x42643b[_0x1371e7(0x2a5)];break;case _0x1371e7(0x24c):_0x37f124=0x1,_0x4eef04=0x0;break;}var _0x4ada4b=Math['round'](_0x40f3e4*_0x37f124),_0x1dbcde=Math[_0x1371e7(0x22f)](0x1,Math[_0x1371e7(0x27b)](0x7,_0x42643b[_0x1371e7(0x2a5)]+_0x4eef04));return{'name':_0x42643b[_0x1371e7(0x2e1)],'type':_0x42643b[_0x1371e7(0x15c)],'strength':_0x42643b[_0x1371e7(0x2be)],'speed':_0x42643b[_0x1371e7(0x1a8)],'tactics':_0x1dbcde,'size':_0x42643b['size'],'powerup':_0x42643b[_0x1371e7(0x2c0)],'health':_0x4ada4b,'maxHealth':_0x4ada4b,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x1c52e9,'orientation':_0x30aae8,'isNFT':_0x244051};}[_0x250edb(0x22b)](_0x543bcc,_0x901692,_0x49d0f2=![]){const _0x474da6=_0x250edb;_0x543bcc[_0x474da6(0x32b)]===_0x474da6(0x17e)?(_0x543bcc[_0x474da6(0x32b)]=_0x474da6(0x334),_0x901692[_0x474da6(0x206)][_0x474da6(0x2ba)]=_0x49d0f2?'scaleX(-1)':_0x474da6(0x302)):(_0x543bcc[_0x474da6(0x32b)]=_0x474da6(0x17e),_0x901692[_0x474da6(0x206)][_0x474da6(0x2ba)]=_0x49d0f2?_0x474da6(0x302):_0x474da6(0x1e2)),log(_0x543bcc['name']+_0x474da6(0x1e7)+_0x543bcc[_0x474da6(0x32b)]+'!');}['showCharacterSelect'](_0x5eef4c){const _0x3237ac=_0x250edb;var _0x51a07a=this;console[_0x3237ac(0x332)]('showCharacterSelect');var _0xd64aff=document[_0x3237ac(0x2fa)](_0x3237ac(0x215)),_0x3d2b7a=document[_0x3237ac(0x2fa)](_0x3237ac(0x31e));_0x3d2b7a[_0x3237ac(0x2c2)]='',_0xd64aff[_0x3237ac(0x206)][_0x3237ac(0x223)]=_0x3237ac(0x210),document[_0x3237ac(0x2fa)](_0x3237ac(0x162))['onclick']=()=>{showThemeSelect(_0x51a07a);};const _0x70e5bb=document[_0x3237ac(0x331)]();this['playerCharacters'][_0x3237ac(0x131)](function(_0x3edb40){const _0x1a1e99=_0x3237ac;var _0x5412f6=document[_0x1a1e99(0x13a)](_0x1a1e99(0x128));_0x5412f6['className']=_0x1a1e99(0x16b),_0x5412f6['innerHTML']=_0x1a1e99(0x15e)+_0x3edb40[_0x1a1e99(0x218)]+_0x1a1e99(0x239)+_0x3edb40[_0x1a1e99(0x2e1)]+'\x22>'+_0x1a1e99(0x23e)+_0x3edb40['name']+_0x1a1e99(0x2c5)+'<p>Type:\x20'+_0x3edb40[_0x1a1e99(0x15c)]+_0x1a1e99(0x22e)+'<p>Health:\x20'+_0x3edb40[_0x1a1e99(0x222)]+_0x1a1e99(0x22e)+_0x1a1e99(0x1cc)+_0x3edb40[_0x1a1e99(0x2be)]+'</p>'+_0x1a1e99(0x289)+_0x3edb40[_0x1a1e99(0x1a8)]+_0x1a1e99(0x22e)+_0x1a1e99(0x235)+_0x3edb40[_0x1a1e99(0x2a5)]+_0x1a1e99(0x22e)+_0x1a1e99(0x29e)+_0x3edb40[_0x1a1e99(0x349)]+_0x1a1e99(0x22e)+_0x1a1e99(0x2a0)+_0x3edb40[_0x1a1e99(0x2c0)]+_0x1a1e99(0x22e),_0x5412f6[_0x1a1e99(0x1f8)]('click',function(){const _0x23ea7f=_0x1a1e99;console[_0x23ea7f(0x2c4)](_0x23ea7f(0x1c5)+_0x3edb40[_0x23ea7f(0x2e1)]),_0xd64aff[_0x23ea7f(0x206)][_0x23ea7f(0x223)]=_0x23ea7f(0x302),_0x5eef4c?(_0x51a07a[_0x23ea7f(0x321)]={'name':_0x3edb40['name'],'type':_0x3edb40['type'],'strength':_0x3edb40['strength'],'speed':_0x3edb40['speed'],'tactics':_0x3edb40['tactics'],'size':_0x3edb40[_0x23ea7f(0x349)],'powerup':_0x3edb40[_0x23ea7f(0x2c0)],'health':_0x3edb40[_0x23ea7f(0x338)],'maxHealth':_0x3edb40['maxHealth'],'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x3edb40[_0x23ea7f(0x218)],'orientation':_0x3edb40[_0x23ea7f(0x32b)],'isNFT':_0x3edb40['isNFT']},console[_0x23ea7f(0x2c4)]('showCharacterSelect:\x20this.player1\x20set:\x20'+_0x51a07a['player1'][_0x23ea7f(0x2e1)]),_0x51a07a[_0x23ea7f(0x28b)]()):_0x51a07a[_0x23ea7f(0x1c0)](_0x3edb40);}),_0x70e5bb[_0x1a1e99(0x342)](_0x5412f6);}),_0x3d2b7a['appendChild'](_0x70e5bb),console[_0x3237ac(0x335)]('showCharacterSelect');}[_0x250edb(0x1c0)](_0x3eaeea){const _0x4f7008=_0x250edb,_0x1748f5=this[_0x4f7008(0x321)][_0x4f7008(0x338)],_0x306654=this[_0x4f7008(0x321)][_0x4f7008(0x222)],_0x1d11c6={..._0x3eaeea},_0x5ef408=Math[_0x4f7008(0x27b)](0x1,_0x1748f5/_0x306654);_0x1d11c6['health']=Math[_0x4f7008(0x16c)](_0x1d11c6[_0x4f7008(0x222)]*_0x5ef408),_0x1d11c6[_0x4f7008(0x338)]=Math[_0x4f7008(0x22f)](0x0,Math['min'](_0x1d11c6['maxHealth'],_0x1d11c6['health'])),_0x1d11c6[_0x4f7008(0x139)]=![],_0x1d11c6[_0x4f7008(0x2ca)]=0x0,_0x1d11c6['lastStandActive']=![],this['player1']=_0x1d11c6,this[_0x4f7008(0x2d9)](),this[_0x4f7008(0x17b)](this[_0x4f7008(0x321)]),log(this['player1'][_0x4f7008(0x2e1)]+'\x20steps\x20into\x20the\x20fray\x20with\x20'+this['player1'][_0x4f7008(0x338)]+'/'+this[_0x4f7008(0x321)][_0x4f7008(0x222)]+_0x4f7008(0x253)),this[_0x4f7008(0x12d)]=this[_0x4f7008(0x321)]['speed']>this['player2'][_0x4f7008(0x1a8)]?this[_0x4f7008(0x321)]:this[_0x4f7008(0x285)]['speed']>this[_0x4f7008(0x321)]['speed']?this[_0x4f7008(0x285)]:this[_0x4f7008(0x321)][_0x4f7008(0x2be)]>=this[_0x4f7008(0x285)][_0x4f7008(0x2be)]?this['player1']:this[_0x4f7008(0x285)],turnIndicator[_0x4f7008(0x2b2)]=_0x4f7008(0x286)+this[_0x4f7008(0x13d)]+_0x4f7008(0x25c)+(this[_0x4f7008(0x12d)]===this[_0x4f7008(0x321)]?_0x4f7008(0x274):_0x4f7008(0x185))+'\x27s\x20Turn',this['currentTurn']===this[_0x4f7008(0x285)]&&this[_0x4f7008(0x1b7)]!=='gameOver'&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x250edb(0x2da)](_0x28d508,_0x52ade6){const _0x54b682=_0x250edb;return console['log'](_0x54b682(0x1a3)+_0x28d508+',\x20score='+_0x52ade6),new Promise(_0x4688b9=>{const _0x52e957=_0x54b682,_0x2a7eb5=document[_0x52e957(0x13a)](_0x52e957(0x128));_0x2a7eb5['id']=_0x52e957(0x2b1),_0x2a7eb5[_0x52e957(0x34e)]=_0x52e957(0x2b1);const _0x1425d2=document[_0x52e957(0x13a)](_0x52e957(0x128));_0x1425d2['className']=_0x52e957(0x241);const _0x1c687a=document['createElement']('p');_0x1c687a['id']=_0x52e957(0x26b),_0x1c687a[_0x52e957(0x2b2)]=_0x52e957(0x280)+_0x28d508+_0x52e957(0x34a)+_0x52ade6+'?',_0x1425d2[_0x52e957(0x342)](_0x1c687a);const _0x4d9557=document[_0x52e957(0x13a)](_0x52e957(0x128));_0x4d9557[_0x52e957(0x34e)]=_0x52e957(0x20e);const _0x1f8e3f=document[_0x52e957(0x13a)](_0x52e957(0x27d));_0x1f8e3f['id']=_0x52e957(0x25e),_0x1f8e3f[_0x52e957(0x2b2)]=_0x52e957(0x2f1),_0x4d9557['appendChild'](_0x1f8e3f);const _0x33af1c=document[_0x52e957(0x13a)](_0x52e957(0x27d));_0x33af1c['id']=_0x52e957(0x216),_0x33af1c[_0x52e957(0x2b2)]=_0x52e957(0x1b0),_0x4d9557[_0x52e957(0x342)](_0x33af1c),_0x1425d2['appendChild'](_0x4d9557),_0x2a7eb5[_0x52e957(0x342)](_0x1425d2),document['body']['appendChild'](_0x2a7eb5),_0x2a7eb5[_0x52e957(0x206)][_0x52e957(0x223)]=_0x52e957(0x2b4);const _0x3be4b7=()=>{const _0x1af16c=_0x52e957;console['log'](_0x1af16c(0x1a6)),_0x2a7eb5['style'][_0x1af16c(0x223)]='none',document[_0x1af16c(0x20a)][_0x1af16c(0x2d2)](_0x2a7eb5),_0x1f8e3f['removeEventListener'](_0x1af16c(0x313),_0x3be4b7),_0x33af1c[_0x1af16c(0x1ed)](_0x1af16c(0x313),_0x27dbaf),_0x4688b9(!![]);},_0x27dbaf=()=>{const _0x5a8ae9=_0x52e957;console[_0x5a8ae9(0x2c4)]('showProgressPopup:\x20User\x20chose\x20Restart'),_0x2a7eb5[_0x5a8ae9(0x206)][_0x5a8ae9(0x223)]=_0x5a8ae9(0x302),document['body'][_0x5a8ae9(0x2d2)](_0x2a7eb5),_0x1f8e3f[_0x5a8ae9(0x1ed)](_0x5a8ae9(0x313),_0x3be4b7),_0x33af1c[_0x5a8ae9(0x1ed)](_0x5a8ae9(0x313),_0x27dbaf),_0x4688b9(![]);};_0x1f8e3f[_0x52e957(0x1f8)](_0x52e957(0x313),_0x3be4b7),_0x33af1c[_0x52e957(0x1f8)](_0x52e957(0x313),_0x27dbaf);});}[_0x250edb(0x28b)](){const _0x302638=_0x250edb;var _0x1d49e0=this;console['log'](_0x302638(0x191)+this['currentLevel']);var _0x3da408=document['querySelector'](_0x302638(0x26e)),_0x17f565=document['getElementById'](_0x302638(0x13c));_0x3da408[_0x302638(0x206)][_0x302638(0x223)]='block',_0x17f565[_0x302638(0x206)]['visibility']=_0x302638(0x30e),this[_0x302638(0x277)](),this[_0x302638(0x28a)]['reset'][_0x302638(0x2d5)](),log(_0x302638(0x169)+this[_0x302638(0x13d)]+_0x302638(0x1ad)),this[_0x302638(0x285)]=this[_0x302638(0x2c6)](opponentsConfig[this['currentLevel']-0x1]),console[_0x302638(0x2c4)](_0x302638(0x2ed)+this[_0x302638(0x13d)]+':\x20'+this['player2']['name']+_0x302638(0x152)+(this['currentLevel']-0x1)+'])'),this['player1'][_0x302638(0x338)]=this[_0x302638(0x321)]['maxHealth'],this[_0x302638(0x12d)]=this['player1']['speed']>this[_0x302638(0x285)][_0x302638(0x1a8)]?this['player1']:this['player2']['speed']>this[_0x302638(0x321)][_0x302638(0x1a8)]?this[_0x302638(0x285)]:this[_0x302638(0x321)][_0x302638(0x2be)]>=this['player2'][_0x302638(0x2be)]?this[_0x302638(0x321)]:this[_0x302638(0x285)],this[_0x302638(0x1b7)]='initializing',this['gameOver']=![],this[_0x302638(0x33e)]=[],p1Image['classList']['remove'](_0x302638(0x306),_0x302638(0x2ff)),p2Image[_0x302638(0x12b)][_0x302638(0x25b)](_0x302638(0x306),_0x302638(0x2ff)),this[_0x302638(0x2d9)](),this['updateOpponentDisplay'](),p1Image['style']['transform']=this[_0x302638(0x321)][_0x302638(0x32b)]===_0x302638(0x17e)?_0x302638(0x1e2):_0x302638(0x302),p2Image[_0x302638(0x206)][_0x302638(0x2ba)]=this['player2'][_0x302638(0x32b)]===_0x302638(0x334)?_0x302638(0x1e2):_0x302638(0x302),this[_0x302638(0x17b)](this['player1']),this[_0x302638(0x17b)](this[_0x302638(0x285)]),battleLog['innerHTML']='',gameOver[_0x302638(0x2b2)]='',this[_0x302638(0x321)][_0x302638(0x349)]!==_0x302638(0x24c)&&log(this[_0x302638(0x321)]['name']+'\x27s\x20'+this['player1'][_0x302638(0x349)]+'\x20size\x20'+(this[_0x302638(0x321)][_0x302638(0x349)]===_0x302638(0x168)?_0x302638(0x204)+this[_0x302638(0x321)]['maxHealth']+_0x302638(0x1f2)+this[_0x302638(0x321)][_0x302638(0x2a5)]:_0x302638(0x2f7)+this[_0x302638(0x321)]['maxHealth']+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x302638(0x321)][_0x302638(0x2a5)])+'!'),this[_0x302638(0x285)][_0x302638(0x349)]!==_0x302638(0x24c)&&log(this[_0x302638(0x285)]['name']+'\x27s\x20'+this[_0x302638(0x285)][_0x302638(0x349)]+'\x20size\x20'+(this[_0x302638(0x285)][_0x302638(0x349)]==='Large'?_0x302638(0x204)+this[_0x302638(0x285)][_0x302638(0x222)]+_0x302638(0x1f2)+this['player2']['tactics']:'drops\x20health\x20to\x20'+this[_0x302638(0x285)][_0x302638(0x222)]+_0x302638(0x1cb)+this[_0x302638(0x285)][_0x302638(0x2a5)])+'!'),log(this['player1'][_0x302638(0x2e1)]+_0x302638(0x236)+this[_0x302638(0x321)][_0x302638(0x338)]+'/'+this[_0x302638(0x321)]['maxHealth']+_0x302638(0x253)),log(this['currentTurn'][_0x302638(0x2e1)]+_0x302638(0x1af)),this[_0x302638(0x1be)](),this[_0x302638(0x1b7)]=this['currentTurn']===this[_0x302638(0x321)]?'playerTurn':_0x302638(0x2cb),turnIndicator[_0x302638(0x2b2)]='Level\x20'+this[_0x302638(0x13d)]+_0x302638(0x25c)+(this[_0x302638(0x12d)]===this[_0x302638(0x321)]?_0x302638(0x274):_0x302638(0x185))+_0x302638(0x2f5),this['playerCharacters'][_0x302638(0x254)]>0x1&&(document['getElementById'](_0x302638(0x244))[_0x302638(0x206)][_0x302638(0x223)]=_0x302638(0x271)),this[_0x302638(0x12d)]===this[_0x302638(0x285)]&&setTimeout(function(){const _0x5983ba=_0x302638;_0x1d49e0[_0x5983ba(0x2cb)]();},0x3e8);}['updatePlayerDisplay'](){const _0x3e53e0=_0x250edb;p1Name[_0x3e53e0(0x2b2)]=this[_0x3e53e0(0x321)][_0x3e53e0(0x124)]||this['theme']===_0x3e53e0(0x2ae)?this[_0x3e53e0(0x321)][_0x3e53e0(0x2e1)]:_0x3e53e0(0x2ac),p1Type[_0x3e53e0(0x2b2)]=this[_0x3e53e0(0x321)]['type'],p1Strength['textContent']=this[_0x3e53e0(0x321)][_0x3e53e0(0x2be)],p1Speed[_0x3e53e0(0x2b2)]=this[_0x3e53e0(0x321)][_0x3e53e0(0x1a8)],p1Tactics[_0x3e53e0(0x2b2)]=this['player1'][_0x3e53e0(0x2a5)],p1Size['textContent']=this[_0x3e53e0(0x321)]['size'],p1Powerup[_0x3e53e0(0x2b2)]=this[_0x3e53e0(0x321)]['powerup'],p1Image[_0x3e53e0(0x2bf)]=this[_0x3e53e0(0x321)][_0x3e53e0(0x218)],p1Image[_0x3e53e0(0x206)][_0x3e53e0(0x2ba)]=this['player1']['orientation']===_0x3e53e0(0x17e)?'scaleX(-1)':_0x3e53e0(0x302),p1Image[_0x3e53e0(0x2ce)]=function(){const _0x540c13=_0x3e53e0;p1Image[_0x540c13(0x206)][_0x540c13(0x223)]=_0x540c13(0x210);},p1Hp[_0x3e53e0(0x2b2)]=this[_0x3e53e0(0x321)]['health']+'/'+this[_0x3e53e0(0x321)]['maxHealth'];}[_0x250edb(0x346)](){const _0x4eb835=_0x250edb;p2Name[_0x4eb835(0x2b2)]=this[_0x4eb835(0x237)]===_0x4eb835(0x2ae)?this['player2'][_0x4eb835(0x2e1)]:_0x4eb835(0x224),p2Type[_0x4eb835(0x2b2)]=this['player2'][_0x4eb835(0x15c)],p2Strength[_0x4eb835(0x2b2)]=this[_0x4eb835(0x285)][_0x4eb835(0x2be)],p2Speed['textContent']=this[_0x4eb835(0x285)]['speed'],p2Tactics[_0x4eb835(0x2b2)]=this[_0x4eb835(0x285)][_0x4eb835(0x2a5)],p2Size[_0x4eb835(0x2b2)]=this[_0x4eb835(0x285)][_0x4eb835(0x349)],p2Powerup[_0x4eb835(0x2b2)]=this['player2']['powerup'],p2Image['src']=this[_0x4eb835(0x285)][_0x4eb835(0x218)],p2Image[_0x4eb835(0x206)][_0x4eb835(0x2ba)]=this[_0x4eb835(0x285)][_0x4eb835(0x32b)]===_0x4eb835(0x334)?'scaleX(-1)':'none',p2Image[_0x4eb835(0x2ce)]=function(){const _0x5bcf1a=_0x4eb835;p2Image[_0x5bcf1a(0x206)][_0x5bcf1a(0x223)]=_0x5bcf1a(0x210);},p2Hp[_0x4eb835(0x2b2)]=this[_0x4eb835(0x285)][_0x4eb835(0x338)]+'/'+this[_0x4eb835(0x285)][_0x4eb835(0x222)];}[_0x250edb(0x1be)](){const _0x297a2b=_0x250edb;this[_0x297a2b(0x34c)]=[];for(let _0x183a91=0x0;_0x183a91<this['height'];_0x183a91++){this[_0x297a2b(0x34c)][_0x183a91]=[];for(let _0x240f85=0x0;_0x240f85<this[_0x297a2b(0x19a)];_0x240f85++){let _0x2615ea;do{_0x2615ea=this['createRandomTile']();}while(_0x240f85>=0x2&&this[_0x297a2b(0x34c)][_0x183a91][_0x240f85-0x1]?.[_0x297a2b(0x15c)]===_0x2615ea[_0x297a2b(0x15c)]&&this[_0x297a2b(0x34c)][_0x183a91][_0x240f85-0x2]?.[_0x297a2b(0x15c)]===_0x2615ea['type']||_0x183a91>=0x2&&this[_0x297a2b(0x34c)][_0x183a91-0x1]?.[_0x240f85]?.['type']===_0x2615ea[_0x297a2b(0x15c)]&&this[_0x297a2b(0x34c)][_0x183a91-0x2]?.[_0x240f85]?.[_0x297a2b(0x15c)]===_0x2615ea[_0x297a2b(0x15c)]);this[_0x297a2b(0x34c)][_0x183a91][_0x240f85]=_0x2615ea;}}this[_0x297a2b(0x1d4)]();}[_0x250edb(0x347)](){const _0x36bc0e=_0x250edb;return{'type':randomChoice(this[_0x36bc0e(0x157)]),'element':null};}[_0x250edb(0x1d4)](){const _0x48feb0=_0x250edb;this['updateTileSizeWithGap']();const _0x2c8324=document[_0x48feb0(0x2fa)](_0x48feb0(0x13c));_0x2c8324['innerHTML']='';for(let _0x441915=0x0;_0x441915<this['height'];_0x441915++){for(let _0x162b4d=0x0;_0x162b4d<this[_0x48feb0(0x19a)];_0x162b4d++){const _0x4e691a=this[_0x48feb0(0x34c)][_0x441915][_0x162b4d];if(_0x4e691a[_0x48feb0(0x15c)]===null)continue;const _0x3201ec=document[_0x48feb0(0x13a)](_0x48feb0(0x128));_0x3201ec[_0x48feb0(0x34e)]='tile\x20'+_0x4e691a[_0x48feb0(0x15c)];if(this['gameOver'])_0x3201ec[_0x48feb0(0x12b)]['add'](_0x48feb0(0x279));const _0x5eb9cc=document[_0x48feb0(0x13a)](_0x48feb0(0x243));_0x5eb9cc['src']=_0x48feb0(0x135)+_0x4e691a['type']+_0x48feb0(0x214),_0x5eb9cc[_0x48feb0(0x155)]=_0x4e691a[_0x48feb0(0x15c)],_0x3201ec[_0x48feb0(0x342)](_0x5eb9cc),_0x3201ec[_0x48feb0(0x207)]['x']=_0x162b4d,_0x3201ec[_0x48feb0(0x207)]['y']=_0x441915,_0x2c8324[_0x48feb0(0x342)](_0x3201ec),_0x4e691a[_0x48feb0(0x23a)]=_0x3201ec,(!this[_0x48feb0(0x29d)]||this[_0x48feb0(0x1aa)]&&(this[_0x48feb0(0x1aa)]['x']!==_0x162b4d||this[_0x48feb0(0x1aa)]['y']!==_0x441915))&&(_0x3201ec[_0x48feb0(0x206)][_0x48feb0(0x2ba)]=_0x48feb0(0x2f2));}}document[_0x48feb0(0x2fa)]('game-over-container')[_0x48feb0(0x206)][_0x48feb0(0x223)]=this[_0x48feb0(0x22c)]?_0x48feb0(0x210):_0x48feb0(0x302);}['addEventListeners'](){const _0x41a8ed=_0x250edb,_0x2ad372=document['getElementById'](_0x41a8ed(0x13c));this['isTouchDevice']?(_0x2ad372[_0x41a8ed(0x1f8)](_0x41a8ed(0x29a),_0x5da168=>this['handleTouchStart'](_0x5da168)),_0x2ad372[_0x41a8ed(0x1f8)](_0x41a8ed(0x27e),_0x13c84b=>this[_0x41a8ed(0x310)](_0x13c84b)),_0x2ad372[_0x41a8ed(0x1f8)](_0x41a8ed(0x19e),_0x4fc00d=>this[_0x41a8ed(0x1fc)](_0x4fc00d))):(_0x2ad372[_0x41a8ed(0x1f8)](_0x41a8ed(0x1d9),_0x289792=>this[_0x41a8ed(0x151)](_0x289792)),_0x2ad372[_0x41a8ed(0x1f8)](_0x41a8ed(0x1df),_0x174828=>this[_0x41a8ed(0x1db)](_0x174828)),_0x2ad372[_0x41a8ed(0x1f8)]('mouseup',_0x5ecbaa=>this[_0x41a8ed(0x12f)](_0x5ecbaa)));document['getElementById'](_0x41a8ed(0x180))['addEventListener'](_0x41a8ed(0x313),()=>this[_0x41a8ed(0x231)]()),document[_0x41a8ed(0x2fa)]('restart')[_0x41a8ed(0x1f8)](_0x41a8ed(0x313),()=>{const _0x3a5c85=_0x41a8ed;this[_0x3a5c85(0x28b)]();});const _0xe20790=document[_0x41a8ed(0x2fa)](_0x41a8ed(0x244)),_0x2865b5=document[_0x41a8ed(0x2fa)](_0x41a8ed(0x1ea));_0xe20790[_0x41a8ed(0x1f8)](_0x41a8ed(0x313),()=>{const _0x7fcb35=_0x41a8ed;console[_0x7fcb35(0x2c4)](_0x7fcb35(0x1bc)),this[_0x7fcb35(0x301)](![]);}),_0x2865b5[_0x41a8ed(0x1f8)](_0x41a8ed(0x313),()=>{console['log']('addEventListeners:\x20Player\x201\x20image\x20clicked'),this['showCharacterSelect'](![]);}),document[_0x41a8ed(0x2fa)](_0x41a8ed(0x199))[_0x41a8ed(0x1f8)]('click',()=>this['flipCharacter'](this[_0x41a8ed(0x321)],_0x2865b5,![])),document[_0x41a8ed(0x2fa)](_0x41a8ed(0x16a))[_0x41a8ed(0x1f8)](_0x41a8ed(0x313),()=>this['flipCharacter'](this[_0x41a8ed(0x285)],p2Image,!![]));}[_0x250edb(0x231)](){const _0x128a20=_0x250edb;console[_0x128a20(0x2c4)](_0x128a20(0x1bd)+this['currentLevel']+_0x128a20(0x28f)+this['player2'][_0x128a20(0x338)]),this[_0x128a20(0x285)][_0x128a20(0x338)]<=0x0&&this[_0x128a20(0x13d)]>opponentsConfig['length']&&(this[_0x128a20(0x13d)]=0x1,console[_0x128a20(0x2c4)](_0x128a20(0x226)+this[_0x128a20(0x13d)])),this[_0x128a20(0x28b)](),console[_0x128a20(0x2c4)](_0x128a20(0x1dd)+this['currentLevel']);}['handleMouseDown'](_0x5d8167){const _0x54ed1c=_0x250edb;if(this['gameOver']||this['gameState']!=='playerTurn'||this[_0x54ed1c(0x12d)]!==this[_0x54ed1c(0x321)])return;_0x5d8167[_0x54ed1c(0x161)]();const _0x46e0e7=this[_0x54ed1c(0x1a1)](_0x5d8167);if(!_0x46e0e7||!_0x46e0e7[_0x54ed1c(0x23a)])return;this['isDragging']=!![],this[_0x54ed1c(0x1aa)]={'x':_0x46e0e7['x'],'y':_0x46e0e7['y']},_0x46e0e7[_0x54ed1c(0x23a)][_0x54ed1c(0x12b)][_0x54ed1c(0x1eb)]('selected');const _0xd899cc=document[_0x54ed1c(0x2fa)](_0x54ed1c(0x13c))[_0x54ed1c(0x272)]();this['offsetX']=_0x5d8167['clientX']-(_0xd899cc[_0x54ed1c(0x242)]+this[_0x54ed1c(0x1aa)]['x']*this[_0x54ed1c(0x326)]),this[_0x54ed1c(0x2e6)]=_0x5d8167[_0x54ed1c(0x27a)]-(_0xd899cc[_0x54ed1c(0x249)]+this[_0x54ed1c(0x1aa)]['y']*this[_0x54ed1c(0x326)]);}[_0x250edb(0x1db)](_0x7eac64){const _0x34cf2b=_0x250edb;if(!this[_0x34cf2b(0x29d)]||!this[_0x34cf2b(0x1aa)]||this[_0x34cf2b(0x22c)]||this[_0x34cf2b(0x1b7)]!==_0x34cf2b(0x281))return;_0x7eac64[_0x34cf2b(0x161)]();const _0x3c240a=document[_0x34cf2b(0x2fa)](_0x34cf2b(0x13c))['getBoundingClientRect'](),_0x56aee4=_0x7eac64['clientX']-_0x3c240a['left']-this[_0x34cf2b(0x172)],_0x50373b=_0x7eac64['clientY']-_0x3c240a[_0x34cf2b(0x249)]-this[_0x34cf2b(0x2e6)],_0x44736c=this[_0x34cf2b(0x34c)][this['selectedTile']['y']][this[_0x34cf2b(0x1aa)]['x']][_0x34cf2b(0x23a)];_0x44736c[_0x34cf2b(0x206)][_0x34cf2b(0x234)]='';if(!this[_0x34cf2b(0x1c8)]){const _0x4242b7=Math[_0x34cf2b(0x32e)](_0x56aee4-this[_0x34cf2b(0x1aa)]['x']*this[_0x34cf2b(0x326)]),_0x406c83=Math[_0x34cf2b(0x32e)](_0x50373b-this[_0x34cf2b(0x1aa)]['y']*this[_0x34cf2b(0x326)]);if(_0x4242b7>_0x406c83&&_0x4242b7>0x5)this['dragDirection']='row';else{if(_0x406c83>_0x4242b7&&_0x406c83>0x5)this['dragDirection']=_0x34cf2b(0x1ba);}}if(!this[_0x34cf2b(0x1c8)])return;if(this['dragDirection']===_0x34cf2b(0x1c7)){const _0xa10719=Math[_0x34cf2b(0x22f)](0x0,Math[_0x34cf2b(0x27b)]((this['width']-0x1)*this[_0x34cf2b(0x326)],_0x56aee4));_0x44736c[_0x34cf2b(0x206)][_0x34cf2b(0x2ba)]=_0x34cf2b(0x1ef)+(_0xa10719-this[_0x34cf2b(0x1aa)]['x']*this[_0x34cf2b(0x326)])+'px,\x200)\x20scale(1.05)',this[_0x34cf2b(0x209)]={'x':Math['round'](_0xa10719/this[_0x34cf2b(0x326)]),'y':this[_0x34cf2b(0x1aa)]['y']};}else{if(this[_0x34cf2b(0x1c8)]===_0x34cf2b(0x1ba)){const _0x2e48e5=Math[_0x34cf2b(0x22f)](0x0,Math[_0x34cf2b(0x27b)]((this['height']-0x1)*this[_0x34cf2b(0x326)],_0x50373b));_0x44736c[_0x34cf2b(0x206)]['transform']=_0x34cf2b(0x1d6)+(_0x2e48e5-this[_0x34cf2b(0x1aa)]['y']*this['tileSizeWithGap'])+_0x34cf2b(0x133),this[_0x34cf2b(0x209)]={'x':this[_0x34cf2b(0x1aa)]['x'],'y':Math[_0x34cf2b(0x16c)](_0x2e48e5/this[_0x34cf2b(0x326)])};}}}[_0x250edb(0x12f)](_0x36c5ff){const _0x3f9df5=_0x250edb;if(!this['isDragging']||!this[_0x3f9df5(0x1aa)]||!this[_0x3f9df5(0x209)]||this[_0x3f9df5(0x22c)]||this[_0x3f9df5(0x1b7)]!==_0x3f9df5(0x281)){if(this[_0x3f9df5(0x1aa)]){const _0x51a5b6=this[_0x3f9df5(0x34c)][this[_0x3f9df5(0x1aa)]['y']][this[_0x3f9df5(0x1aa)]['x']];if(_0x51a5b6[_0x3f9df5(0x23a)])_0x51a5b6[_0x3f9df5(0x23a)][_0x3f9df5(0x12b)][_0x3f9df5(0x25b)](_0x3f9df5(0x142));}this[_0x3f9df5(0x29d)]=![],this['selectedTile']=null,this[_0x3f9df5(0x209)]=null,this[_0x3f9df5(0x1c8)]=null,this[_0x3f9df5(0x1d4)]();return;}const _0x294514=this[_0x3f9df5(0x34c)][this[_0x3f9df5(0x1aa)]['y']][this[_0x3f9df5(0x1aa)]['x']];if(_0x294514[_0x3f9df5(0x23a)])_0x294514[_0x3f9df5(0x23a)][_0x3f9df5(0x12b)]['remove']('selected');this[_0x3f9df5(0x329)](this[_0x3f9df5(0x1aa)]['x'],this[_0x3f9df5(0x1aa)]['y'],this['targetTile']['x'],this[_0x3f9df5(0x209)]['y']),this[_0x3f9df5(0x29d)]=![],this[_0x3f9df5(0x1aa)]=null,this[_0x3f9df5(0x209)]=null,this[_0x3f9df5(0x1c8)]=null;}['handleTouchStart'](_0x304112){const _0x381287=_0x250edb;if(this[_0x381287(0x22c)]||this[_0x381287(0x1b7)]!=='playerTurn'||this['currentTurn']!==this['player1'])return;_0x304112['preventDefault']();const _0x171d85=this[_0x381287(0x1a1)](_0x304112[_0x381287(0x14e)][0x0]);if(!_0x171d85||!_0x171d85[_0x381287(0x23a)])return;this[_0x381287(0x29d)]=!![],this['selectedTile']={'x':_0x171d85['x'],'y':_0x171d85['y']},_0x171d85[_0x381287(0x23a)][_0x381287(0x12b)][_0x381287(0x1eb)](_0x381287(0x142));const _0x4e949a=document[_0x381287(0x2fa)]('game-board')['getBoundingClientRect']();this[_0x381287(0x172)]=_0x304112['touches'][0x0]['clientX']-(_0x4e949a[_0x381287(0x242)]+this[_0x381287(0x1aa)]['x']*this[_0x381287(0x326)]),this[_0x381287(0x2e6)]=_0x304112['touches'][0x0][_0x381287(0x27a)]-(_0x4e949a['top']+this[_0x381287(0x1aa)]['y']*this[_0x381287(0x326)]);}[_0x250edb(0x310)](_0x15079d){const _0x2191f3=_0x250edb;if(!this[_0x2191f3(0x29d)]||!this['selectedTile']||this['gameOver']||this[_0x2191f3(0x1b7)]!==_0x2191f3(0x281))return;_0x15079d['preventDefault']();const _0x455c69=document[_0x2191f3(0x2fa)](_0x2191f3(0x13c))[_0x2191f3(0x272)](),_0x4c8070=_0x15079d[_0x2191f3(0x14e)][0x0][_0x2191f3(0x149)]-_0x455c69[_0x2191f3(0x242)]-this[_0x2191f3(0x172)],_0x1204f0=_0x15079d['touches'][0x0][_0x2191f3(0x27a)]-_0x455c69['top']-this[_0x2191f3(0x2e6)],_0x5af2fe=this[_0x2191f3(0x34c)][this[_0x2191f3(0x1aa)]['y']][this[_0x2191f3(0x1aa)]['x']]['element'];requestAnimationFrame(()=>{const _0x542035=_0x2191f3;if(!this['dragDirection']){const _0x57f046=Math[_0x542035(0x32e)](_0x4c8070-this[_0x542035(0x1aa)]['x']*this[_0x542035(0x326)]),_0x4e7b6b=Math['abs'](_0x1204f0-this[_0x542035(0x1aa)]['y']*this[_0x542035(0x326)]);if(_0x57f046>_0x4e7b6b&&_0x57f046>0x7)this[_0x542035(0x1c8)]='row';else{if(_0x4e7b6b>_0x57f046&&_0x4e7b6b>0x7)this[_0x542035(0x1c8)]='column';}}_0x5af2fe[_0x542035(0x206)][_0x542035(0x234)]='';if(this['dragDirection']===_0x542035(0x1c7)){const _0x3c3019=Math[_0x542035(0x22f)](0x0,Math[_0x542035(0x27b)]((this[_0x542035(0x19a)]-0x1)*this[_0x542035(0x326)],_0x4c8070));_0x5af2fe[_0x542035(0x206)][_0x542035(0x2ba)]='translate('+(_0x3c3019-this[_0x542035(0x1aa)]['x']*this[_0x542035(0x326)])+_0x542035(0x18c),this[_0x542035(0x209)]={'x':Math[_0x542035(0x16c)](_0x3c3019/this[_0x542035(0x326)]),'y':this['selectedTile']['y']};}else{if(this[_0x542035(0x1c8)]==='column'){const _0x3cfebb=Math['max'](0x0,Math[_0x542035(0x27b)]((this[_0x542035(0x1d2)]-0x1)*this['tileSizeWithGap'],_0x1204f0));_0x5af2fe[_0x542035(0x206)]['transform']='translate(0,\x20'+(_0x3cfebb-this[_0x542035(0x1aa)]['y']*this[_0x542035(0x326)])+_0x542035(0x133),this[_0x542035(0x209)]={'x':this[_0x542035(0x1aa)]['x'],'y':Math[_0x542035(0x16c)](_0x3cfebb/this[_0x542035(0x326)])};}}});}[_0x250edb(0x1fc)](_0xc27bed){const _0x2f456f=_0x250edb;if(!this[_0x2f456f(0x29d)]||!this[_0x2f456f(0x1aa)]||!this[_0x2f456f(0x209)]||this[_0x2f456f(0x22c)]||this[_0x2f456f(0x1b7)]!==_0x2f456f(0x281)){if(this[_0x2f456f(0x1aa)]){const _0x446535=this['board'][this[_0x2f456f(0x1aa)]['y']][this[_0x2f456f(0x1aa)]['x']];if(_0x446535['element'])_0x446535['element'][_0x2f456f(0x12b)][_0x2f456f(0x25b)](_0x2f456f(0x142));}this[_0x2f456f(0x29d)]=![],this['selectedTile']=null,this[_0x2f456f(0x209)]=null,this[_0x2f456f(0x1c8)]=null,this['renderBoard']();return;}const _0x57164a=this['board'][this[_0x2f456f(0x1aa)]['y']][this[_0x2f456f(0x1aa)]['x']];if(_0x57164a[_0x2f456f(0x23a)])_0x57164a[_0x2f456f(0x23a)][_0x2f456f(0x12b)][_0x2f456f(0x25b)]('selected');this[_0x2f456f(0x329)](this[_0x2f456f(0x1aa)]['x'],this[_0x2f456f(0x1aa)]['y'],this['targetTile']['x'],this[_0x2f456f(0x209)]['y']),this[_0x2f456f(0x29d)]=![],this[_0x2f456f(0x1aa)]=null,this[_0x2f456f(0x209)]=null,this[_0x2f456f(0x1c8)]=null;}[_0x250edb(0x1a1)](_0x56a7b7){const _0xc74515=_0x250edb,_0x11fcfd=document[_0xc74515(0x2fa)](_0xc74515(0x13c))['getBoundingClientRect'](),_0x348161=Math[_0xc74515(0x34d)]((_0x56a7b7['clientX']-_0x11fcfd[_0xc74515(0x242)])/this[_0xc74515(0x326)]),_0x33d6c3=Math[_0xc74515(0x34d)]((_0x56a7b7[_0xc74515(0x27a)]-_0x11fcfd[_0xc74515(0x249)])/this[_0xc74515(0x326)]);if(_0x348161>=0x0&&_0x348161<this[_0xc74515(0x19a)]&&_0x33d6c3>=0x0&&_0x33d6c3<this[_0xc74515(0x1d2)])return{'x':_0x348161,'y':_0x33d6c3,'element':this['board'][_0x33d6c3][_0x348161][_0xc74515(0x23a)]};return null;}['slideTiles'](_0x13ed76,_0x51b48d,_0xd3b98c,_0xb0962b){const _0x7f62e7=_0x250edb,_0x3013ae=this[_0x7f62e7(0x326)];let _0x5749f6;const _0x405bf7=[],_0x2d9afb=[];if(_0x51b48d===_0xb0962b){_0x5749f6=_0x13ed76<_0xd3b98c?0x1:-0x1;const _0x23fc05=Math[_0x7f62e7(0x27b)](_0x13ed76,_0xd3b98c),_0x5211f5=Math[_0x7f62e7(0x22f)](_0x13ed76,_0xd3b98c);for(let _0x447180=_0x23fc05;_0x447180<=_0x5211f5;_0x447180++){_0x405bf7[_0x7f62e7(0x2af)]({...this['board'][_0x51b48d][_0x447180]}),_0x2d9afb[_0x7f62e7(0x2af)](this['board'][_0x51b48d][_0x447180][_0x7f62e7(0x23a)]);}}else{if(_0x13ed76===_0xd3b98c){_0x5749f6=_0x51b48d<_0xb0962b?0x1:-0x1;const _0x4bf753=Math[_0x7f62e7(0x27b)](_0x51b48d,_0xb0962b),_0x2f87ef=Math[_0x7f62e7(0x22f)](_0x51b48d,_0xb0962b);for(let _0x35c6ec=_0x4bf753;_0x35c6ec<=_0x2f87ef;_0x35c6ec++){_0x405bf7['push']({...this['board'][_0x35c6ec][_0x13ed76]}),_0x2d9afb[_0x7f62e7(0x2af)](this['board'][_0x35c6ec][_0x13ed76][_0x7f62e7(0x23a)]);}}}const _0x5bb681=this[_0x7f62e7(0x34c)][_0x51b48d][_0x13ed76][_0x7f62e7(0x23a)],_0x1c50ff=(_0xd3b98c-_0x13ed76)*_0x3013ae,_0xd02e9d=(_0xb0962b-_0x51b48d)*_0x3013ae;_0x5bb681[_0x7f62e7(0x206)][_0x7f62e7(0x234)]='transform\x200.2s\x20ease',_0x5bb681['style'][_0x7f62e7(0x2ba)]=_0x7f62e7(0x1ef)+_0x1c50ff+_0x7f62e7(0x2d3)+_0xd02e9d+'px)';let _0x139e63=0x0;if(_0x51b48d===_0xb0962b)for(let _0xfc7c62=Math[_0x7f62e7(0x27b)](_0x13ed76,_0xd3b98c);_0xfc7c62<=Math['max'](_0x13ed76,_0xd3b98c);_0xfc7c62++){if(_0xfc7c62===_0x13ed76)continue;const _0x337fc8=_0x5749f6*-_0x3013ae*(_0xfc7c62-_0x13ed76)/Math[_0x7f62e7(0x32e)](_0xd3b98c-_0x13ed76);_0x2d9afb[_0x139e63]['style']['transition']='transform\x200.2s\x20ease',_0x2d9afb[_0x139e63][_0x7f62e7(0x206)]['transform']=_0x7f62e7(0x1ef)+_0x337fc8+_0x7f62e7(0x344),_0x139e63++;}else for(let _0x926adb=Math[_0x7f62e7(0x27b)](_0x51b48d,_0xb0962b);_0x926adb<=Math['max'](_0x51b48d,_0xb0962b);_0x926adb++){if(_0x926adb===_0x51b48d)continue;const _0x3bbb80=_0x5749f6*-_0x3013ae*(_0x926adb-_0x51b48d)/Math[_0x7f62e7(0x32e)](_0xb0962b-_0x51b48d);_0x2d9afb[_0x139e63][_0x7f62e7(0x206)][_0x7f62e7(0x234)]=_0x7f62e7(0x2db),_0x2d9afb[_0x139e63][_0x7f62e7(0x206)]['transform']=_0x7f62e7(0x1d6)+_0x3bbb80+_0x7f62e7(0x200),_0x139e63++;}setTimeout(()=>{const _0x2e4f1d=_0x7f62e7;if(_0x51b48d===_0xb0962b){const _0x452ce4=this[_0x2e4f1d(0x34c)][_0x51b48d],_0x2022d7=[..._0x452ce4];if(_0x13ed76<_0xd3b98c){for(let _0x459151=_0x13ed76;_0x459151<_0xd3b98c;_0x459151++)_0x452ce4[_0x459151]=_0x2022d7[_0x459151+0x1];}else{for(let _0x76d6d8=_0x13ed76;_0x76d6d8>_0xd3b98c;_0x76d6d8--)_0x452ce4[_0x76d6d8]=_0x2022d7[_0x76d6d8-0x1];}_0x452ce4[_0xd3b98c]=_0x2022d7[_0x13ed76];}else{const _0xfcd48=[];for(let _0x31e062=0x0;_0x31e062<this[_0x2e4f1d(0x1d2)];_0x31e062++)_0xfcd48[_0x31e062]={...this[_0x2e4f1d(0x34c)][_0x31e062][_0x13ed76]};if(_0x51b48d<_0xb0962b){for(let _0x494d1b=_0x51b48d;_0x494d1b<_0xb0962b;_0x494d1b++)this['board'][_0x494d1b][_0x13ed76]=_0xfcd48[_0x494d1b+0x1];}else{for(let _0xcdc6af=_0x51b48d;_0xcdc6af>_0xb0962b;_0xcdc6af--)this['board'][_0xcdc6af][_0x13ed76]=_0xfcd48[_0xcdc6af-0x1];}this['board'][_0xb0962b][_0xd3b98c]=_0xfcd48[_0x51b48d];}this['renderBoard']();const _0x8fa2ca=this[_0x2e4f1d(0x173)](_0xd3b98c,_0xb0962b);_0x8fa2ca?this[_0x2e4f1d(0x1b7)]=_0x2e4f1d(0x14d):(log('No\x20match,\x20reverting\x20tiles...'),this['sounds'][_0x2e4f1d(0x1a2)][_0x2e4f1d(0x2d5)](),_0x5bb681['style'][_0x2e4f1d(0x234)]=_0x2e4f1d(0x2db),_0x5bb681[_0x2e4f1d(0x206)][_0x2e4f1d(0x2ba)]=_0x2e4f1d(0x2f2),_0x2d9afb[_0x2e4f1d(0x131)](_0x3bf65a=>{const _0x412455=_0x2e4f1d;_0x3bf65a[_0x412455(0x206)][_0x412455(0x234)]='transform\x200.2s\x20ease',_0x3bf65a[_0x412455(0x206)]['transform']='translate(0,\x200)';}),setTimeout(()=>{const _0x3d7cf6=_0x2e4f1d;if(_0x51b48d===_0xb0962b){const _0x41ed71=Math['min'](_0x13ed76,_0xd3b98c);for(let _0x2459fd=0x0;_0x2459fd<_0x405bf7[_0x3d7cf6(0x254)];_0x2459fd++){this['board'][_0x51b48d][_0x41ed71+_0x2459fd]={..._0x405bf7[_0x2459fd],'element':_0x2d9afb[_0x2459fd]};}}else{const _0x4464a7=Math[_0x3d7cf6(0x27b)](_0x51b48d,_0xb0962b);for(let _0x5c2d1d=0x0;_0x5c2d1d<_0x405bf7['length'];_0x5c2d1d++){this[_0x3d7cf6(0x34c)][_0x4464a7+_0x5c2d1d][_0x13ed76]={..._0x405bf7[_0x5c2d1d],'element':_0x2d9afb[_0x5c2d1d]};}}this[_0x3d7cf6(0x1d4)](),this[_0x3d7cf6(0x1b7)]=_0x3d7cf6(0x281);},0xc8));},0xc8);}[_0x250edb(0x173)](_0x4424b8=null,_0xd910a6=null){const _0x4b0d68=_0x250edb;console[_0x4b0d68(0x2c4)](_0x4b0d68(0x2bd),this['gameOver']);if(this[_0x4b0d68(0x22c)])return console[_0x4b0d68(0x2c4)](_0x4b0d68(0x290)),![];const _0xfc6c7e=_0x4424b8!==null&&_0xd910a6!==null;console['log']('Is\x20initial\x20move:\x20'+_0xfc6c7e);const _0x5b6d96=this[_0x4b0d68(0x1e1)]();console[_0x4b0d68(0x2c4)](_0x4b0d68(0x217)+_0x5b6d96[_0x4b0d68(0x254)]+'\x20matches:',_0x5b6d96);let _0x3a097d=0x1,_0x52a2e4='';if(_0xfc6c7e&&_0x5b6d96[_0x4b0d68(0x254)]>0x1){const _0x4f6ff6=_0x5b6d96[_0x4b0d68(0x23c)]((_0x434b86,_0x219154)=>_0x434b86+_0x219154[_0x4b0d68(0x23b)],0x0);console[_0x4b0d68(0x2c4)](_0x4b0d68(0x318)+_0x4f6ff6);if(_0x4f6ff6>=0x6&&_0x4f6ff6<=0x8)_0x3a097d=1.2,_0x52a2e4=_0x4b0d68(0x1ec)+_0x4f6ff6+'\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!',this[_0x4b0d68(0x28a)][_0x4b0d68(0x145)][_0x4b0d68(0x2d5)]();else _0x4f6ff6>=0x9&&(_0x3a097d=0x3,_0x52a2e4='Mega\x20Multi-Match!\x20'+_0x4f6ff6+_0x4b0d68(0x2a8),this['sounds'][_0x4b0d68(0x145)]['play']());}if(_0x5b6d96[_0x4b0d68(0x254)]>0x0){const _0x46b5e0=new Set();let _0x5bf69b=0x0;const _0x358503=this[_0x4b0d68(0x12d)],_0x469a3e=this[_0x4b0d68(0x12d)]===this['player1']?this[_0x4b0d68(0x285)]:this[_0x4b0d68(0x321)];try{_0x5b6d96[_0x4b0d68(0x131)](_0x4760a8=>{const _0x3cb397=_0x4b0d68;console['log'](_0x3cb397(0x229),_0x4760a8),_0x4760a8[_0x3cb397(0x34b)][_0x3cb397(0x131)](_0x50abc3=>_0x46b5e0[_0x3cb397(0x1eb)](_0x50abc3));const _0x5f088d=this[_0x3cb397(0x273)](_0x4760a8,_0xfc6c7e);console[_0x3cb397(0x2c4)](_0x3cb397(0x22d)+_0x5f088d);if(this['gameOver']){console['log'](_0x3cb397(0x2e8));return;}if(_0x5f088d>0x0)_0x5bf69b+=_0x5f088d;});if(this[_0x4b0d68(0x22c)])return console[_0x4b0d68(0x2c4)](_0x4b0d68(0x315)),!![];return console['log']('Total\x20damage\x20dealt:\x20'+_0x5bf69b+_0x4b0d68(0x27f),[..._0x46b5e0]),_0x5bf69b>0x0&&!this['gameOver']&&setTimeout(()=>{const _0x2afe15=_0x4b0d68;if(this[_0x2afe15(0x22c)]){console[_0x2afe15(0x2c4)](_0x2afe15(0x19b));return;}console['log'](_0x2afe15(0x2b5),_0x469a3e['name']),this[_0x2afe15(0x165)](_0x469a3e,_0x5bf69b);},0x64),setTimeout(()=>{const _0x54e90d=_0x4b0d68;if(this[_0x54e90d(0x22c)]){console[_0x54e90d(0x2c4)]('Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading');return;}console['log'](_0x54e90d(0x221),[..._0x46b5e0]),_0x46b5e0[_0x54e90d(0x131)](_0x218ad6=>{const _0x331415=_0x54e90d,[_0x489fde,_0x135431]=_0x218ad6[_0x331415(0x24e)](',')['map'](Number);this[_0x331415(0x34c)][_0x135431][_0x489fde]?.[_0x331415(0x23a)]?this[_0x331415(0x34c)][_0x135431][_0x489fde]['element']['classList'][_0x331415(0x1eb)](_0x331415(0x283)):console[_0x331415(0x2d7)](_0x331415(0x2d1)+_0x489fde+','+_0x135431+_0x331415(0x31b));}),setTimeout(()=>{const _0x22d02a=_0x54e90d;if(this[_0x22d02a(0x22c)]){console[_0x22d02a(0x2c4)](_0x22d02a(0x21a));return;}console[_0x22d02a(0x2c4)]('Clearing\x20matched\x20tiles:',[..._0x46b5e0]),_0x46b5e0[_0x22d02a(0x131)](_0x1a5f95=>{const _0x1f4b24=_0x22d02a,[_0x4aef2a,_0x409b01]=_0x1a5f95[_0x1f4b24(0x24e)](',')[_0x1f4b24(0x233)](Number);this[_0x1f4b24(0x34c)][_0x409b01][_0x4aef2a]&&(this[_0x1f4b24(0x34c)][_0x409b01][_0x4aef2a][_0x1f4b24(0x15c)]=null,this[_0x1f4b24(0x34c)][_0x409b01][_0x4aef2a][_0x1f4b24(0x23a)]=null);}),this[_0x22d02a(0x28a)][_0x22d02a(0x1ca)][_0x22d02a(0x2d5)](),console['log'](_0x22d02a(0x2f4));if(_0x3a097d>0x1&&this[_0x22d02a(0x33e)][_0x22d02a(0x254)]>0x0){const _0x23b66a=this[_0x22d02a(0x33e)][this['roundStats'][_0x22d02a(0x254)]-0x1],_0x5f3084=_0x23b66a[_0x22d02a(0x21b)];_0x23b66a[_0x22d02a(0x21b)]=Math[_0x22d02a(0x16c)](_0x23b66a[_0x22d02a(0x21b)]*_0x3a097d),_0x52a2e4&&(log(_0x52a2e4),log(_0x22d02a(0x183)+_0x5f3084+_0x22d02a(0x327)+_0x23b66a[_0x22d02a(0x21b)]+'\x20after\x20multi-match\x20bonus!'));}this[_0x22d02a(0x148)](()=>{const _0x24fd05=_0x22d02a;if(this[_0x24fd05(0x22c)]){console[_0x24fd05(0x2c4)](_0x24fd05(0x1a9));return;}console[_0x24fd05(0x2c4)](_0x24fd05(0x1ae)),this[_0x24fd05(0x170)]();});},0x12c);},0xc8),!![];}catch(_0x170640){return console[_0x4b0d68(0x2e5)]('Error\x20in\x20resolveMatches:',_0x170640),this['gameState']=this[_0x4b0d68(0x12d)]===this[_0x4b0d68(0x321)]?_0x4b0d68(0x281):_0x4b0d68(0x2cb),![];}}return console[_0x4b0d68(0x2c4)](_0x4b0d68(0x167)),![];}[_0x250edb(0x1e1)](){const _0x43aee1=_0x250edb;console[_0x43aee1(0x2c4)](_0x43aee1(0x2fe));const _0x374843=[];try{const _0x24b7ce=[];for(let _0x639336=0x0;_0x639336<this['height'];_0x639336++){let _0x1f4ccf=0x0;for(let _0x3d05f1=0x0;_0x3d05f1<=this[_0x43aee1(0x19a)];_0x3d05f1++){const _0x3c8afa=_0x3d05f1<this[_0x43aee1(0x19a)]?this[_0x43aee1(0x34c)][_0x639336][_0x3d05f1]?.[_0x43aee1(0x15c)]:null;if(_0x3c8afa!==this[_0x43aee1(0x34c)][_0x639336][_0x1f4ccf]?.[_0x43aee1(0x15c)]||_0x3d05f1===this['width']){const _0x343c78=_0x3d05f1-_0x1f4ccf;if(_0x343c78>=0x3){const _0xd33b8=new Set();for(let _0x281405=_0x1f4ccf;_0x281405<_0x3d05f1;_0x281405++){_0xd33b8['add'](_0x281405+','+_0x639336);}_0x24b7ce[_0x43aee1(0x2af)]({'type':this['board'][_0x639336][_0x1f4ccf][_0x43aee1(0x15c)],'coordinates':_0xd33b8}),console[_0x43aee1(0x2c4)](_0x43aee1(0x2b7)+_0x639336+_0x43aee1(0x158)+_0x1f4ccf+'-'+(_0x3d05f1-0x1)+':',[..._0xd33b8]);}_0x1f4ccf=_0x3d05f1;}}}for(let _0x406edd=0x0;_0x406edd<this[_0x43aee1(0x19a)];_0x406edd++){let _0x239aa7=0x0;for(let _0x1019ea=0x0;_0x1019ea<=this[_0x43aee1(0x1d2)];_0x1019ea++){const _0x586d23=_0x1019ea<this[_0x43aee1(0x1d2)]?this[_0x43aee1(0x34c)][_0x1019ea][_0x406edd]?.['type']:null;if(_0x586d23!==this[_0x43aee1(0x34c)][_0x239aa7][_0x406edd]?.['type']||_0x1019ea===this[_0x43aee1(0x1d2)]){const _0x2b20f3=_0x1019ea-_0x239aa7;if(_0x2b20f3>=0x3){const _0x22eb54=new Set();for(let _0xf50b97=_0x239aa7;_0xf50b97<_0x1019ea;_0xf50b97++){_0x22eb54[_0x43aee1(0x1eb)](_0x406edd+','+_0xf50b97);}_0x24b7ce[_0x43aee1(0x2af)]({'type':this[_0x43aee1(0x34c)][_0x239aa7][_0x406edd][_0x43aee1(0x15c)],'coordinates':_0x22eb54}),console['log'](_0x43aee1(0x176)+_0x406edd+',\x20rows\x20'+_0x239aa7+'-'+(_0x1019ea-0x1)+':',[..._0x22eb54]);}_0x239aa7=_0x1019ea;}}}const _0x3ceba6=[],_0xcd1b17=new Set();return _0x24b7ce[_0x43aee1(0x131)]((_0x4db4f2,_0x5f7a09)=>{const _0x246bde=_0x43aee1;if(_0xcd1b17[_0x246bde(0x12e)](_0x5f7a09))return;const _0x168eb2={'type':_0x4db4f2['type'],'coordinates':new Set(_0x4db4f2[_0x246bde(0x34b)])};_0xcd1b17['add'](_0x5f7a09);for(let _0x5e12d0=0x0;_0x5e12d0<_0x24b7ce['length'];_0x5e12d0++){if(_0xcd1b17['has'](_0x5e12d0))continue;const _0x40c0a6=_0x24b7ce[_0x5e12d0];if(_0x40c0a6[_0x246bde(0x15c)]===_0x168eb2['type']){const _0x3e9d32=[..._0x40c0a6[_0x246bde(0x34b)]][_0x246bde(0x156)](_0x428755=>_0x168eb2[_0x246bde(0x34b)][_0x246bde(0x12e)](_0x428755));_0x3e9d32&&(_0x40c0a6[_0x246bde(0x34b)]['forEach'](_0x1ac02c=>_0x168eb2['coordinates'][_0x246bde(0x1eb)](_0x1ac02c)),_0xcd1b17['add'](_0x5e12d0));}}_0x3ceba6[_0x246bde(0x2af)]({'type':_0x168eb2[_0x246bde(0x15c)],'coordinates':_0x168eb2[_0x246bde(0x34b)],'totalTiles':_0x168eb2[_0x246bde(0x34b)]['size']});}),_0x374843[_0x43aee1(0x2af)](..._0x3ceba6),console['log']('checkMatches\x20completed,\x20returning\x20matches:',_0x374843),_0x374843;}catch(_0x879b9a){return console[_0x43aee1(0x2e5)](_0x43aee1(0x328),_0x879b9a),[];}}[_0x250edb(0x273)](_0x3f55da,_0x56498c=!![]){const _0x35d23b=_0x250edb;console[_0x35d23b(0x2c4)](_0x35d23b(0x337),_0x3f55da,_0x35d23b(0x15b),_0x56498c);const _0x34a069=this[_0x35d23b(0x12d)],_0x648af=this['currentTurn']===this[_0x35d23b(0x321)]?this[_0x35d23b(0x285)]:this[_0x35d23b(0x321)],_0x57c81a=_0x3f55da[_0x35d23b(0x15c)],_0x53a481=_0x3f55da[_0x35d23b(0x23b)];let _0x839d35=0x0,_0x1b7c06=0x0;console[_0x35d23b(0x2c4)](_0x648af[_0x35d23b(0x2e1)]+_0x35d23b(0x2a7)+_0x648af[_0x35d23b(0x338)]);_0x53a481==0x4&&(this[_0x35d23b(0x28a)]['powerGem'][_0x35d23b(0x2d5)](),log(_0x34a069[_0x35d23b(0x2e1)]+'\x20created\x20a\x20match\x20of\x20'+_0x53a481+'\x20tiles!'));_0x53a481>=0x5&&(this[_0x35d23b(0x28a)][_0x35d23b(0x22a)]['play'](),log(_0x34a069[_0x35d23b(0x2e1)]+'\x20created\x20a\x20match\x20of\x20'+_0x53a481+'\x20tiles!'));if(_0x57c81a===_0x35d23b(0x32a)||_0x57c81a===_0x35d23b(0x33f)||_0x57c81a===_0x35d23b(0x265)||_0x57c81a===_0x35d23b(0x1f7)){_0x839d35=Math[_0x35d23b(0x16c)](_0x34a069['strength']*(_0x53a481===0x3?0x2:_0x53a481===0x4?0x3:0x4));let _0x13c10f=0x1;if(_0x53a481===0x4)_0x13c10f=1.5;else _0x53a481>=0x5&&(_0x13c10f=0x2);_0x839d35=Math['round'](_0x839d35*_0x13c10f),console[_0x35d23b(0x2c4)](_0x35d23b(0x20d)+_0x34a069[_0x35d23b(0x2be)]*(_0x53a481===0x3?0x2:_0x53a481===0x4?0x3:0x4)+',\x20Match\x20bonus:\x20'+_0x13c10f+',\x20Total\x20damage:\x20'+_0x839d35);_0x57c81a===_0x35d23b(0x265)&&(_0x839d35=Math[_0x35d23b(0x16c)](_0x839d35*1.2),console['log'](_0x35d23b(0x140)+_0x839d35));_0x34a069[_0x35d23b(0x139)]&&(_0x839d35+=_0x34a069['boostValue']||0xa,_0x34a069['boostActive']=![],log(_0x34a069[_0x35d23b(0x2e1)]+_0x35d23b(0x1e0)),console[_0x35d23b(0x2c4)](_0x35d23b(0x2ec)+_0x839d35));_0x1b7c06=_0x839d35;const _0x326838=_0x648af[_0x35d23b(0x2a5)]*0xa;Math[_0x35d23b(0x1ac)]()*0x64<_0x326838&&(_0x839d35=Math[_0x35d23b(0x34d)](_0x839d35/0x2),log(_0x648af['name']+_0x35d23b(0x26f)+_0x839d35+_0x35d23b(0x255)),console[_0x35d23b(0x2c4)](_0x35d23b(0x15f)+_0x839d35));let _0x5b3a6d=0x0;_0x648af[_0x35d23b(0x295)]&&(_0x5b3a6d=Math[_0x35d23b(0x27b)](_0x839d35,0x5),_0x839d35=Math[_0x35d23b(0x22f)](0x0,_0x839d35-_0x5b3a6d),_0x648af['lastStandActive']=![],console[_0x35d23b(0x2c4)](_0x35d23b(0x2cc)+_0x5b3a6d+_0x35d23b(0x201)+_0x839d35));const _0x4ea6f3=_0x57c81a===_0x35d23b(0x32a)?_0x35d23b(0x20c):_0x57c81a==='second-attack'?_0x35d23b(0x24b):_0x35d23b(0x287);let _0x393f33;if(_0x5b3a6d>0x0)_0x393f33=_0x34a069[_0x35d23b(0x2e1)]+_0x35d23b(0x126)+_0x4ea6f3+'\x20on\x20'+_0x648af[_0x35d23b(0x2e1)]+_0x35d23b(0x348)+_0x1b7c06+_0x35d23b(0x1f0)+_0x648af[_0x35d23b(0x2e1)]+_0x35d23b(0x2dc)+_0x5b3a6d+'\x20damage,\x20resulting\x20in\x20'+_0x839d35+_0x35d23b(0x255);else _0x57c81a===_0x35d23b(0x1f7)?_0x393f33=_0x34a069[_0x35d23b(0x2e1)]+_0x35d23b(0x238)+_0x839d35+_0x35d23b(0x29f)+_0x648af[_0x35d23b(0x2e1)]+_0x35d23b(0x211):_0x393f33=_0x34a069[_0x35d23b(0x2e1)]+_0x35d23b(0x126)+_0x4ea6f3+_0x35d23b(0x1ab)+_0x648af[_0x35d23b(0x2e1)]+'\x20for\x20'+_0x839d35+_0x35d23b(0x255);_0x56498c?log(_0x393f33):log(_0x35d23b(0x351)+_0x393f33),_0x648af[_0x35d23b(0x338)]=Math[_0x35d23b(0x22f)](0x0,_0x648af['health']-_0x839d35),console[_0x35d23b(0x2c4)](_0x648af[_0x35d23b(0x2e1)]+_0x35d23b(0x256)+_0x648af[_0x35d23b(0x338)]),this[_0x35d23b(0x17b)](_0x648af),console[_0x35d23b(0x2c4)](_0x35d23b(0x171)),this[_0x35d23b(0x2d6)](),!this['gameOver']&&(console[_0x35d23b(0x2c4)](_0x35d23b(0x1de)),this['animateAttack'](_0x34a069,_0x839d35,_0x57c81a));}else _0x57c81a==='power-up'&&(this[_0x35d23b(0x132)](_0x34a069,_0x648af,_0x53a481),!this[_0x35d23b(0x22c)]&&(console[_0x35d23b(0x2c4)](_0x35d23b(0x17c)),this['animatePowerup'](_0x34a069)));(!this[_0x35d23b(0x33e)][this[_0x35d23b(0x33e)][_0x35d23b(0x254)]-0x1]||this[_0x35d23b(0x33e)][this[_0x35d23b(0x33e)][_0x35d23b(0x254)]-0x1][_0x35d23b(0x2c1)])&&this['roundStats'][_0x35d23b(0x2af)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x154eea=this[_0x35d23b(0x33e)][this[_0x35d23b(0x33e)][_0x35d23b(0x254)]-0x1];return _0x154eea[_0x35d23b(0x21b)]+=_0x839d35,_0x154eea[_0x35d23b(0x31f)]+=0x1,console[_0x35d23b(0x2c4)](_0x35d23b(0x258)+_0x839d35),_0x839d35;}[_0x250edb(0x148)](_0x3823f5){const _0x270748=_0x250edb;if(this[_0x270748(0x22c)]){console[_0x270748(0x2c4)](_0x270748(0x17f));return;}const _0x3d5100=this[_0x270748(0x16d)](),_0x1b38bb=_0x270748(0x150);for(let _0x4fc2fd=0x0;_0x4fc2fd<this[_0x270748(0x19a)];_0x4fc2fd++){for(let _0x280103=0x0;_0x280103<this[_0x270748(0x1d2)];_0x280103++){const _0x5464e7=this[_0x270748(0x34c)][_0x280103][_0x4fc2fd];if(_0x5464e7['element']&&_0x5464e7[_0x270748(0x23a)][_0x270748(0x206)][_0x270748(0x2ba)]===_0x270748(0x212)){const _0x4f470a=this[_0x270748(0x1a7)](_0x4fc2fd,_0x280103);_0x4f470a>0x0&&(_0x5464e7[_0x270748(0x23a)]['classList'][_0x270748(0x1eb)](_0x1b38bb),_0x5464e7[_0x270748(0x23a)]['style'][_0x270748(0x2ba)]='translate(0,\x20'+_0x4f470a*this[_0x270748(0x326)]+'px)');}}}this['renderBoard'](),_0x3d5100?setTimeout(()=>{const _0x255363=_0x270748;if(this[_0x255363(0x22c)]){console[_0x255363(0x2c4)](_0x255363(0x2d8));return;}this[_0x255363(0x28a)][_0x255363(0x1f5)][_0x255363(0x2d5)]();const _0x2f185f=this[_0x255363(0x173)](),_0x4ff37f=document[_0x255363(0x333)]('.'+_0x1b38bb);_0x4ff37f[_0x255363(0x131)](_0x2820ce=>{const _0x1c0ea1=_0x255363;_0x2820ce[_0x1c0ea1(0x12b)][_0x1c0ea1(0x25b)](_0x1b38bb),_0x2820ce[_0x1c0ea1(0x206)][_0x1c0ea1(0x2ba)]=_0x1c0ea1(0x2f2);}),!_0x2f185f&&_0x3823f5();},0x12c):_0x3823f5();}[_0x250edb(0x16d)](){const _0x45181a=_0x250edb;let _0x1e1c18=![];for(let _0x4446bb=0x0;_0x4446bb<this[_0x45181a(0x19a)];_0x4446bb++){let _0x27e4fb=0x0;for(let _0x12e674=this[_0x45181a(0x1d2)]-0x1;_0x12e674>=0x0;_0x12e674--){if(!this[_0x45181a(0x34c)][_0x12e674][_0x4446bb][_0x45181a(0x15c)])_0x27e4fb++;else _0x27e4fb>0x0&&(this[_0x45181a(0x34c)][_0x12e674+_0x27e4fb][_0x4446bb]=this[_0x45181a(0x34c)][_0x12e674][_0x4446bb],this[_0x45181a(0x34c)][_0x12e674][_0x4446bb]={'type':null,'element':null},_0x1e1c18=!![]);}for(let _0x3097a7=0x0;_0x3097a7<_0x27e4fb;_0x3097a7++){this['board'][_0x3097a7][_0x4446bb]=this[_0x45181a(0x347)](),_0x1e1c18=!![];}}return _0x1e1c18;}[_0x250edb(0x1a7)](_0x3755eb,_0x5203ef){const _0x557766=_0x250edb;let _0x4f80ae=0x0;for(let _0x36fa60=_0x5203ef+0x1;_0x36fa60<this[_0x557766(0x1d2)];_0x36fa60++){if(!this[_0x557766(0x34c)][_0x36fa60][_0x3755eb][_0x557766(0x15c)])_0x4f80ae++;else break;}return _0x4f80ae;}[_0x250edb(0x132)](_0x531fcd,_0x27f11f,_0x563253){const _0x6c39c0=_0x250edb,_0x3065df=0x1-_0x27f11f['tactics']*0.05;let _0x280721,_0x1b0890,_0x14e735,_0x1c9249=0x1,_0x345f5b='';if(_0x563253===0x4)_0x1c9249=1.5,_0x345f5b=_0x6c39c0(0x1f3);else _0x563253>=0x5&&(_0x1c9249=0x2,_0x345f5b=_0x6c39c0(0x12c));if(_0x531fcd['powerup']===_0x6c39c0(0x20f))_0x1b0890=0xa*_0x1c9249,_0x280721=Math['floor'](_0x1b0890*_0x3065df),_0x14e735=_0x1b0890-_0x280721,_0x531fcd[_0x6c39c0(0x338)]=Math[_0x6c39c0(0x27b)](_0x531fcd['maxHealth'],_0x531fcd['health']+_0x280721),log(_0x531fcd[_0x6c39c0(0x2e1)]+_0x6c39c0(0x1b8)+_0x280721+_0x6c39c0(0x303)+_0x345f5b+(_0x27f11f['tactics']>0x0?_0x6c39c0(0x2a4)+_0x1b0890+_0x6c39c0(0x203)+_0x14e735+_0x6c39c0(0x2fc)+_0x27f11f[_0x6c39c0(0x2e1)]+_0x6c39c0(0x1c1):'')+'!');else{if(_0x531fcd[_0x6c39c0(0x2c0)]===_0x6c39c0(0x1c2))_0x1b0890=0xa*_0x1c9249,_0x280721=Math[_0x6c39c0(0x34d)](_0x1b0890*_0x3065df),_0x14e735=_0x1b0890-_0x280721,_0x531fcd[_0x6c39c0(0x139)]=!![],_0x531fcd[_0x6c39c0(0x2ca)]=_0x280721,log(_0x531fcd[_0x6c39c0(0x2e1)]+_0x6c39c0(0x248)+_0x280721+_0x6c39c0(0x141)+_0x345f5b+(_0x27f11f[_0x6c39c0(0x2a5)]>0x0?_0x6c39c0(0x2a4)+_0x1b0890+',\x20reduced\x20by\x20'+_0x14e735+_0x6c39c0(0x2fc)+_0x27f11f[_0x6c39c0(0x2e1)]+_0x6c39c0(0x1c1):'')+'!');else{if(_0x531fcd[_0x6c39c0(0x2c0)]===_0x6c39c0(0x147))_0x1b0890=0x7*_0x1c9249,_0x280721=Math['floor'](_0x1b0890*_0x3065df),_0x14e735=_0x1b0890-_0x280721,_0x531fcd[_0x6c39c0(0x338)]=Math[_0x6c39c0(0x27b)](_0x531fcd[_0x6c39c0(0x222)],_0x531fcd[_0x6c39c0(0x338)]+_0x280721),log(_0x531fcd['name']+_0x6c39c0(0x2eb)+_0x280721+_0x6c39c0(0x303)+_0x345f5b+(_0x27f11f[_0x6c39c0(0x2a5)]>0x0?_0x6c39c0(0x2a4)+_0x1b0890+',\x20reduced\x20by\x20'+_0x14e735+_0x6c39c0(0x2fc)+_0x27f11f['name']+_0x6c39c0(0x1c1):'')+'!');else _0x531fcd[_0x6c39c0(0x2c0)]===_0x6c39c0(0x2f0)&&(_0x1b0890=0x5*_0x1c9249,_0x280721=Math['floor'](_0x1b0890*_0x3065df),_0x14e735=_0x1b0890-_0x280721,_0x531fcd['health']=Math[_0x6c39c0(0x27b)](_0x531fcd[_0x6c39c0(0x222)],_0x531fcd[_0x6c39c0(0x338)]+_0x280721),log(_0x531fcd[_0x6c39c0(0x2e1)]+_0x6c39c0(0x27c)+_0x280721+'\x20HP'+_0x345f5b+(_0x27f11f[_0x6c39c0(0x2a5)]>0x0?_0x6c39c0(0x2a4)+_0x1b0890+_0x6c39c0(0x203)+_0x14e735+_0x6c39c0(0x2fc)+_0x27f11f['name']+'\x27s\x20tactics)':'')+'!'));}}this[_0x6c39c0(0x17b)](_0x531fcd);}[_0x250edb(0x17b)](_0x3804fc){const _0x493594=_0x250edb,_0x38bcc3=_0x3804fc===this['player1']?p1Health:p2Health,_0x220ff6=_0x3804fc===this['player1']?p1Hp:p2Hp,_0x54803c=_0x3804fc['health']/_0x3804fc[_0x493594(0x222)]*0x64;_0x38bcc3['style'][_0x493594(0x19a)]=_0x54803c+'%';let _0x2b80a9;if(_0x54803c>0x4b)_0x2b80a9=_0x493594(0x267);else{if(_0x54803c>0x32)_0x2b80a9='#FFC105';else _0x54803c>0x19?_0x2b80a9=_0x493594(0x26d):_0x2b80a9=_0x493594(0x282);}_0x38bcc3[_0x493594(0x206)]['backgroundColor']=_0x2b80a9,_0x220ff6['textContent']=_0x3804fc[_0x493594(0x338)]+'/'+_0x3804fc[_0x493594(0x222)];}['endTurn'](){const _0x578190=_0x250edb;if(this[_0x578190(0x1b7)]===_0x578190(0x22c)||this[_0x578190(0x22c)]){console[_0x578190(0x2c4)](_0x578190(0x1a9));return;}this[_0x578190(0x12d)]=this[_0x578190(0x12d)]===this[_0x578190(0x321)]?this['player2']:this[_0x578190(0x321)],this[_0x578190(0x1b7)]=this[_0x578190(0x12d)]===this[_0x578190(0x321)]?'playerTurn':'aiTurn',turnIndicator[_0x578190(0x2b2)]=_0x578190(0x286)+this['currentLevel']+_0x578190(0x25c)+(this['currentTurn']===this[_0x578190(0x321)]?_0x578190(0x274):_0x578190(0x185))+_0x578190(0x2f5),log('Turn\x20switched\x20to\x20'+(this['currentTurn']===this[_0x578190(0x321)]?_0x578190(0x274):_0x578190(0x185))),this[_0x578190(0x12d)]===this['player2']&&setTimeout(()=>this['aiTurn'](),0x3e8);}['aiTurn'](){const _0x3dd7d0=_0x250edb;if(this['gameState']!==_0x3dd7d0(0x2cb)||this[_0x3dd7d0(0x12d)]!==this[_0x3dd7d0(0x285)])return;this['gameState']='animating';const _0x56c06d=this[_0x3dd7d0(0x18f)]();_0x56c06d?(log(this['player2'][_0x3dd7d0(0x2e1)]+_0x3dd7d0(0x160)+_0x56c06d['x1']+',\x20'+_0x56c06d['y1']+_0x3dd7d0(0x324)+_0x56c06d['x2']+',\x20'+_0x56c06d['y2']+')'),this[_0x3dd7d0(0x329)](_0x56c06d['x1'],_0x56c06d['y1'],_0x56c06d['x2'],_0x56c06d['y2'])):(log(this[_0x3dd7d0(0x285)][_0x3dd7d0(0x2e1)]+_0x3dd7d0(0x294)),this['endTurn']());}[_0x250edb(0x18f)](){const _0xc7e46a=_0x250edb;for(let _0x4d9477=0x0;_0x4d9477<this[_0xc7e46a(0x1d2)];_0x4d9477++){for(let _0x19a33b=0x0;_0x19a33b<this[_0xc7e46a(0x19a)];_0x19a33b++){if(_0x19a33b<this[_0xc7e46a(0x19a)]-0x1&&this[_0xc7e46a(0x18a)](_0x19a33b,_0x4d9477,_0x19a33b+0x1,_0x4d9477))return{'x1':_0x19a33b,'y1':_0x4d9477,'x2':_0x19a33b+0x1,'y2':_0x4d9477};if(_0x4d9477<this['height']-0x1&&this['canMakeMatch'](_0x19a33b,_0x4d9477,_0x19a33b,_0x4d9477+0x1))return{'x1':_0x19a33b,'y1':_0x4d9477,'x2':_0x19a33b,'y2':_0x4d9477+0x1};}}return null;}[_0x250edb(0x18a)](_0x5937df,_0x18cad2,_0x1f5356,_0x447d46){const _0xc5d47e=_0x250edb,_0x5b014b={...this[_0xc5d47e(0x34c)][_0x18cad2][_0x5937df]},_0x298daf={...this[_0xc5d47e(0x34c)][_0x447d46][_0x1f5356]};this[_0xc5d47e(0x34c)][_0x18cad2][_0x5937df]=_0x298daf,this[_0xc5d47e(0x34c)][_0x447d46][_0x1f5356]=_0x5b014b;const _0x4b4160=this[_0xc5d47e(0x1e1)]()[_0xc5d47e(0x254)]>0x0;return this[_0xc5d47e(0x34c)][_0x18cad2][_0x5937df]=_0x5b014b,this[_0xc5d47e(0x34c)][_0x447d46][_0x1f5356]=_0x298daf,_0x4b4160;}async['checkGameOver'](){const _0x222799=_0x250edb;if(this[_0x222799(0x22c)]||this['isCheckingGameOver']){console[_0x222799(0x2c4)]('checkGameOver\x20skipped:\x20gameOver='+this[_0x222799(0x22c)]+_0x222799(0x1c6)+this[_0x222799(0x14b)]+_0x222799(0x33d)+this['currentLevel']);return;}this['isCheckingGameOver']=!![],console[_0x222799(0x2c4)](_0x222799(0x2e9)+this[_0x222799(0x13d)]+_0x222799(0x197)+this[_0x222799(0x321)][_0x222799(0x338)]+_0x222799(0x28f)+this[_0x222799(0x285)][_0x222799(0x338)]);const _0x233a07=document[_0x222799(0x2fa)](_0x222799(0x180));if(this[_0x222799(0x321)][_0x222799(0x338)]<=0x0){console[_0x222799(0x2c4)](_0x222799(0x134)),this[_0x222799(0x22c)]=!![],this[_0x222799(0x1b7)]=_0x222799(0x22c),gameOver['textContent']=_0x222799(0x2a2),turnIndicator[_0x222799(0x2b2)]=_0x222799(0x1c9),log(this['player2'][_0x222799(0x2e1)]+_0x222799(0x264)+this[_0x222799(0x321)][_0x222799(0x2e1)]+'!'),_0x233a07[_0x222799(0x2b2)]=_0x222799(0x1cf),document[_0x222799(0x2fa)](_0x222799(0x19f))[_0x222799(0x206)][_0x222799(0x223)]=_0x222799(0x210);try{this[_0x222799(0x28a)][_0x222799(0x1ee)][_0x222799(0x2d5)]();}catch(_0x5e2194){console['error']('Error\x20playing\x20lose\x20sound:',_0x5e2194);}}else{if(this['player2'][_0x222799(0x338)]<=0x0){console[_0x222799(0x2c4)](_0x222799(0x2f9)),this[_0x222799(0x22c)]=!![],this[_0x222799(0x1b7)]=_0x222799(0x22c),gameOver['textContent']=_0x222799(0x30c),turnIndicator[_0x222799(0x2b2)]=_0x222799(0x1c9),_0x233a07[_0x222799(0x2b2)]=this[_0x222799(0x13d)]===opponentsConfig[_0x222799(0x254)]?'START\x20OVER':_0x222799(0x314),document[_0x222799(0x2fa)]('game-over-container')[_0x222799(0x206)][_0x222799(0x223)]=_0x222799(0x210);if(this[_0x222799(0x12d)]===this[_0x222799(0x321)]){const _0x4627f3=this[_0x222799(0x33e)][this[_0x222799(0x33e)][_0x222799(0x254)]-0x1];if(_0x4627f3&&!_0x4627f3[_0x222799(0x2c1)]){_0x4627f3[_0x222799(0x2e7)]=this['player1'][_0x222799(0x338)]/this['player1'][_0x222799(0x222)]*0x64,_0x4627f3['completed']=!![];const _0x1f1bcd=_0x4627f3[_0x222799(0x31f)]>0x0?_0x4627f3[_0x222799(0x21b)]/_0x4627f3[_0x222799(0x31f)]/0x64*(_0x4627f3[_0x222799(0x2e7)]+0x14)*(0x1+this[_0x222799(0x13d)]/0x38):0x0;log('Calculating\x20round\x20score:\x20points='+_0x4627f3[_0x222799(0x21b)]+_0x222799(0x299)+_0x4627f3[_0x222799(0x31f)]+_0x222799(0x245)+_0x4627f3[_0x222799(0x2e7)][_0x222799(0x28d)](0x2)+_0x222799(0x1e9)+this[_0x222799(0x13d)]),log(_0x222799(0x296)+_0x4627f3[_0x222799(0x21b)]+_0x222799(0x219)+_0x4627f3['matches']+')\x20/\x20100)\x20*\x20('+_0x4627f3['healthPercentage']+'\x20+\x2020))\x20*\x20(1\x20+\x20'+this[_0x222799(0x13d)]+_0x222799(0x23d)+_0x1f1bcd),this[_0x222799(0x1b4)]+=_0x1f1bcd,log(_0x222799(0x23f)+_0x4627f3[_0x222799(0x21b)]+',\x20Matches:\x20'+_0x4627f3[_0x222799(0x31f)]+',\x20Health\x20Left:\x20'+_0x4627f3['healthPercentage']['toFixed'](0x2)+'%'),log(_0x222799(0x20b)+_0x1f1bcd+_0x222799(0x252)+this[_0x222799(0x1b4)]);}}await this[_0x222799(0x2f3)](this[_0x222799(0x13d)]);this['currentLevel']===opponentsConfig[_0x222799(0x254)]?(this[_0x222799(0x28a)][_0x222799(0x1d5)][_0x222799(0x2d5)](),log('Final\x20level\x20completed!\x20Final\x20score:\x20'+this[_0x222799(0x1b4)]),this['grandTotalScore']=0x0,await this[_0x222799(0x178)](),log(_0x222799(0x2a9))):(this[_0x222799(0x13d)]+=0x1,await this['saveProgress'](),console['log'](_0x222799(0x1a0)+this[_0x222799(0x13d)]),this[_0x222799(0x28a)][_0x222799(0x21d)][_0x222799(0x2d5)]());const _0x53e191=this[_0x222799(0x227)]+_0x222799(0x2e4)+this['player2'][_0x222799(0x2e1)]['toLowerCase']()[_0x222799(0x26c)](/ /g,'-')+_0x222799(0x214);p2Image[_0x222799(0x2bf)]=_0x53e191,p2Image[_0x222799(0x12b)][_0x222799(0x1eb)]('loser'),p1Image['classList']['add'](_0x222799(0x306)),this[_0x222799(0x1d4)]();}}this[_0x222799(0x14b)]=![],console[_0x222799(0x2c4)](_0x222799(0x24d)+this['currentLevel']+_0x222799(0x32f)+this[_0x222799(0x22c)]);}async[_0x250edb(0x2f3)](_0x2e6fff){const _0x5df33f=_0x250edb,_0x44efb1={'level':_0x2e6fff,'score':this[_0x5df33f(0x1b4)]};console[_0x5df33f(0x2c4)](_0x5df33f(0x225)+_0x44efb1['level']+_0x5df33f(0x16e)+_0x44efb1[_0x5df33f(0x198)]);try{const _0x57bd11=await fetch(_0x5df33f(0x13f),{'method':_0x5df33f(0x2ee),'headers':{'Content-Type':_0x5df33f(0x240)},'body':JSON[_0x5df33f(0x1f1)](_0x44efb1)});if(!_0x57bd11['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x57bd11['status']);const _0x43ba2d=await _0x57bd11[_0x5df33f(0x13b)]();console[_0x5df33f(0x2c4)]('Save\x20response:',_0x43ba2d),log('Level\x20'+_0x43ba2d[_0x5df33f(0x30f)]+_0x5df33f(0x18e)+_0x43ba2d[_0x5df33f(0x198)][_0x5df33f(0x28d)](0x2)),_0x43ba2d[_0x5df33f(0x19c)]===_0x5df33f(0x29c)?log(_0x5df33f(0x190)+_0x43ba2d[_0x5df33f(0x30f)]+_0x5df33f(0x308)+_0x43ba2d[_0x5df33f(0x198)][_0x5df33f(0x28d)](0x2)+_0x5df33f(0x304)+_0x43ba2d[_0x5df33f(0x181)]):log('Score\x20Not\x20Saved:\x20'+_0x43ba2d['message']);}catch(_0x1f2854){console[_0x5df33f(0x2e5)](_0x5df33f(0x163),_0x1f2854),log(_0x5df33f(0x28c)+_0x1f2854[_0x5df33f(0x2e2)]);}}[_0x250edb(0x33c)](_0x4598b4,_0x1ef935,_0x3c566c,_0x5208e3){const _0x5c0088=_0x250edb,_0x763e10=_0x4598b4[_0x5c0088(0x206)]['transform']||'',_0x275897=_0x763e10[_0x5c0088(0x21c)](_0x5c0088(0x1d1))?_0x763e10[_0x5c0088(0x1ca)](/scaleX\([^)]+\)/)[0x0]:'';_0x4598b4[_0x5c0088(0x206)]['transition']=_0x5c0088(0x194)+_0x5208e3/0x2/0x3e8+'s\x20linear',_0x4598b4[_0x5c0088(0x206)]['transform']=_0x5c0088(0x251)+_0x1ef935+_0x5c0088(0x1e6)+_0x275897,_0x4598b4[_0x5c0088(0x12b)][_0x5c0088(0x1eb)](_0x3c566c),setTimeout(()=>{const _0x11dbf5=_0x5c0088;_0x4598b4[_0x11dbf5(0x206)][_0x11dbf5(0x2ba)]=_0x275897,setTimeout(()=>{const _0xde91c7=_0x11dbf5;_0x4598b4[_0xde91c7(0x12b)][_0xde91c7(0x25b)](_0x3c566c);},_0x5208e3/0x2);},_0x5208e3/0x2);}[_0x250edb(0x309)](_0x20b419,_0x481b6d,_0x44f9dd){const _0xb05cb=_0x250edb,_0x56c6b6=_0x20b419===this[_0xb05cb(0x321)]?p1Image:p2Image,_0x5bfd82=_0x20b419===this[_0xb05cb(0x321)]?0x1:-0x1,_0x5bf0e3=Math['min'](0xa,0x2+_0x481b6d*0.4),_0x554d99=_0x5bfd82*_0x5bf0e3,_0x558b4f=_0xb05cb(0x15a)+_0x44f9dd;this['applyAnimation'](_0x56c6b6,_0x554d99,_0x558b4f,0xc8);}[_0x250edb(0x2d4)](_0x2779d5){const _0xee6fd0=_0x250edb,_0x5c4701=_0x2779d5===this['player1']?p1Image:p2Image;this[_0xee6fd0(0x33c)](_0x5c4701,0x0,_0xee6fd0(0x30a),0xc8);}[_0x250edb(0x165)](_0xbf2983,_0x5bb5fd){const _0x2289e2=_0x250edb,_0x2ec8f5=_0xbf2983===this['player1']?p1Image:p2Image,_0x481f13=_0xbf2983===this[_0x2289e2(0x321)]?-0x1:0x1,_0x467b41=Math['min'](0xa,0x2+_0x5bb5fd*0.4),_0x58f23e=_0x481f13*_0x467b41;this[_0x2289e2(0x33c)](_0x2ec8f5,_0x58f23e,_0x2289e2(0x275),0xc8);}}function randomChoice(_0x2f6fdd){const _0x4d666d=_0x250edb;return _0x2f6fdd[Math[_0x4d666d(0x34d)](Math[_0x4d666d(0x1ac)]()*_0x2f6fdd[_0x4d666d(0x254)])];}function _0x563e(){const _0x2ef7e0=[',\x20loadedScore=','indexOf','createCharacter:\x20config=','name','message','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','battle-damaged/','error','offsetY','healthPercentage','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','checkGameOver\x20started:\x20currentLevel=','saveProgress','\x20uses\x20Regen,\x20restoring\x20','Boost\x20applied,\x20damage:\x20','Loaded\x20opponent\x20for\x20level\x20','POST','getAssets:\x20Monstrocity\x20data=','Minor\x20Regen','Resume','translate(0,\x200)','saveScoreToDatabase','Cascading\x20tiles','\x27s\x20Turn','335aOHdTn','drops\x20health\x20to\x20','flatMap','Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)','getElementById','updateCharacters_','\x20due\x20to\x20','Jarhead','checkMatches\x20started','loser','Raw\x20response\x20text:','showCharacterSelect','none','\x20HP',',\x20Completions:\x20','msMaxTouchPoints','winner','Merdock',',\x20Score\x20','animateAttack','glow-power-up','addEventListeners','You\x20Win!','init:\x20Async\x20initialization\x20completed','visible','level','handleTouchMove','value','Koipon','click','NEXT\x20LEVEL','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','gameTheme','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','isTouchDevice','loadProgress',')\x20has\x20no\x20element\x20to\x20animate','getAssets:\x20No\x20policy\x20IDs\x20for\x20theme\x20','DOMContentLoaded','character-options','matches','p2-tactics','player1','setBackground:\x20Setting\x20background\x20to\x20','25587tRJFqQ',')\x20to\x20(','15376gqOHhO','tileSizeWithGap','\x20to\x20','Error\x20in\x20checkMatches:','slideTiles','first-attack','orientation','updateTheme','Billandar\x20and\x20Ted','abs',',\x20gameOver=','Base','createDocumentFragment','time','querySelectorAll','Right','timeEnd','setBackground:\x20Attempting\x20for\x20theme=','handleMatch\x20started,\x20match:','health','background','then','HTTP\x20error!\x20Status:\x20','applyAnimation',',\x20currentLevel=','roundStats','second-attack','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','Main:\x20Error\x20initializing\x20game:','appendChild','logo.png','px,\x200)','p2-image','updateOpponentDisplay','createRandomTile','\x20for\x20','size','\x20with\x20Score\x20of\x20','coordinates','board','floor','className','196038sJpNKu','https://www.skulliance.io/staking/sounds/powergem_created.ogg','Cascade:\x20','trim','getAssets:\x20NFT\x20data\x20is\x20false','isNFT','setItem','\x20uses\x20','ipfsPrefix','div','p2-hp','NFT\x20HTTP\x20error!\x20Status:\x20','classList','\x20(100%\x20bonus\x20for\x20match-5+)','currentTurn','has','handleMouseUp','Mandiblus','forEach','usePowerup','px)\x20scale(1.05)','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)','https://www.skulliance.io/staking/icons/','https://ipfs.io/ipfs/','isArray','title','boostActive','createElement','json','game-board','currentLevel','theme-group','ajax/save-monstrocity-score.php','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','\x20damage','selected','insertBefore','Monstrocity\x20timeout','multiMatch','getAssets:\x20Returning\x20Monstrocity\x20assets','Regenerate','cascadeTiles','clientX','29418MHEPhR','isCheckingGameOver','.game-logo','animating','touches','ajax/save-monstrocity-progress.php','falling','handleMouseDown','\x20(opponentsConfig[','getAssets:\x20Sending\x20NFT\x20POST','/monstrocity.png','alt','some','tileTypes',',\x20cols\x20','ipfsPrefixes','glow-','isInitialMove:','type','Ouchie','<img\x20loading=\x22eager\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22\x20src=\x22','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','\x20swaps\x20tiles\x20at\x20(','preventDefault','theme-select-button','Error\x20saving\x20to\x20database:','firstChild','animateRecoil','items','No\x20matches\x20found,\x20returning\x20false','Large','Starting\x20Level\x20','flip-p2','character-option','round','cascadeTilesWithoutRender',',\x20score=','Starting\x20fresh\x20at\x20Level\x201','endTurn','Calling\x20checkGameOver\x20from\x20handleMatch','offsetX','resolveMatches','scrollTop','Monstrocity_Unknown_','Vertical\x20match\x20found\x20at\x20col\x20','battle-damaged','clearProgress','Spydrax','base','updateHealth','Animating\x20powerup','init','Left','Game\x20over,\x20skipping\x20cascadeTiles','try-again','attempts','https://www.skulliance.io/staking/sounds/voice_go.ogg','Round\x20points\x20increased\x20from\x20','p2-size','Opponent','6062232bHqgCq','Error\x20saving\x20progress:','2989400QxEDIc','playerCharacters','canMakeMatch','getAssets_','px,\x200)\x20scale(1.05)','Goblin\x20Ganger','\x20Score:\x20','findAIMove','Score\x20Saved:\x20Level\x20','initGame:\x20Started\x20with\x20this.currentLevel=','orientations','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','transform\x20','Texby','theme-select-container',',\x20player1.health=','score','flip-p1','width','Game\x20over,\x20skipping\x20recoil\x20animation','status','266IGkToi','touchend','game-over-container','Progress\x20saved:\x20currentLevel=','getTileFromEvent','badMove','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','172fYbrrH','init:\x20Prompting\x20with\x20loadedLevel=','showProgressPopup:\x20User\x20chose\x20Resume','countEmptyBelow','speed','Game\x20over,\x20skipping\x20endTurn','selectedTile','\x20on\x20','random','...','Cascade\x20complete,\x20ending\x20turn','\x20goes\x20first!','Restart','NFT\x20timeout','No\x20progress\x20found\x20or\x20status\x20not\x20success:','Minor\x20Régén','grandTotalScore','Leader','url(','gameState','\x20uses\x20Heal,\x20restoring\x20','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','column','center','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','handleGameOverButton\x20started:\x20currentLevel=','initBoard','Error\x20loading\x20progress:','swapPlayerCharacter','\x27s\x20tactics)','Boost\x20Attack','getItem','p1-powerup','showCharacterSelect:\x20Character\x20selected:\x20',',\x20isCheckingGameOver=','row','dragDirection','Game\x20Over','match','\x20but\x20sharpens\x20tactics\x20to\x20','<p>Strength:\x20','Katastrophy','updateTileSizeWithGap','TRY\x20AGAIN','Dankle','scaleX','height','querySelector','renderBoard','finalWin','translate(0,\x20','Small','leader','mousedown','Main:\x20Game\x20instance\x20created','handleMouseMove','updateTheme_','handleGameOverButton\x20completed:\x20currentLevel=','Game\x20not\x20over,\x20animating\x20attack','mousemove','\x27s\x20Boost\x20fades.','checkMatches','scaleX(-1)','44wrVRVm','Random','lastChild','px)\x20','\x27s\x20orientation\x20flipped\x20to\x20','https://www.skulliance.io/staking/sounds/speedmatch1.ogg',',\x20level=','p1-image','add','Multi-Match!\x20','removeEventListener','loss','translate(','\x20damage,\x20but\x20','stringify','\x20but\x20dulls\x20tactics\x20to\x20','\x20(50%\x20bonus\x20for\x20match-4)','p1-strength','cascade','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','last-stand','addEventListener','policyId','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','catch','handleTouchEnd','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','policyIds','p1-name','px)',',\x20damage:\x20','Failed\x20to\x20save\x20progress:',',\x20reduced\x20by\x20','boosts\x20health\x20to\x20','Sending\x20saveProgress\x20request\x20with\x20data:','style','dataset','https://www.skulliance.io/staking/sounds/select.ogg','targetTile','body','Round\x20Score:\x20','Slash','Base\x20damage:\x20','progress-modal-buttons','Heal','block','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','translate(0px,\x200px)','Slime\x20Mind','.png','character-select-container','progress-start-fresh','Found\x20','imageUrl','\x20/\x20','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','points','includes','win','ajax/get-monstrocity-assets.php','ontouchstart','text','Animating\x20matched\x20tiles,\x20allMatchedTiles:','maxHealth','display','AI\x20Opponent','Saving\x20score:\x20level=','Reset\x20to\x20Level\x201:\x20currentLevel=','baseImagePath','Drake','Processing\x20match:','hyperCube','flipCharacter','gameOver','Damage\x20from\x20match:\x20','</p>','max','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','handleGameOverButton','project','map','transition','<p>Tactics:\x20','\x20starts\x20at\x20full\x20strength\x20with\x20','theme','\x20uses\x20Last\x20Stand,\x20dealing\x20','\x22\x20alt=\x22','element','totalTiles','reduce','\x20/\x2056)\x20=\x20','<p><strong>','Round\x20Won!\x20Points:\x20','application/json','progress-modal-content','left','img','change-character',',\x20healthPercentage=','Main:\x20Player\x20characters\x20loaded:','Resumed\x20at\x20Level\x20','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','top','p1-health','Bite','Medium','checkGameOver\x20completed:\x20currentLevel=','split','\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>','power-up','translateX(',',\x20Grand\x20Total\x20Score:\x20','\x20HP!','length','\x20damage!','\x20health\x20after\x20damage:\x20','9jlflrv','handleMatch\x20completed,\x20damage\x20dealt:\x20','race','children','remove','\x20-\x20','progress','progress-resume','cover','initializing',',\x20selected\x20theme=','find','filter','\x20defeats\x20','special-attack',',\x20storedTheme=','#4CAF50','toLowerCase','getAssets:\x20Monstrocity\x20status=','showThemeSelect','progress-message','replace','#FFA500','.game-container','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20','getAssets:\x20Cache\x20hit\x20for\x20','inline-block','getBoundingClientRect','handleMatch','Player','glow-recoil','p2-speed','setBackground','setBackground:\x20themeData=','game-over','clientY','min','\x20uses\x20Minor\x20Regen,\x20restoring\x20','button','touchmove',',\x20tiles\x20to\x20clear:','Resume\x20from\x20Level\x20','playerTurn','#F44336','matched','Parsed\x20response:','player2','Level\x20','Shadow\x20Strike','onerror','<p>Speed:\x20','sounds','initGame','Error\x20saving\x20score:\x20','toFixed','Response\x20status:',',\x20player2.health=','Game\x20over,\x20exiting\x20resolveMatches','ipfs','playerCharactersConfig','ajax/get-nft-assets.php','\x20passes...','lastStandActive','Round\x20Score\x20Formula:\x20(((','Battle\x20Damaged','theme-option',',\x20matches=','touchstart','maxTouchPoints','success','isDragging','<p>Size:\x20','\x20damage\x20to\x20','<p>Power-Up:\x20','https://www.skulliance.io/staking/images/monstrocity/','You\x20Lose!','getAssets:\x20Theme\x20not\x20found:\x20','\x20(originally\x20','tactics','NFT_Unknown_','\x20health\x20before\x20match:\x20','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','p1-hp','Progress\x20saved:\x20Level\x20','Player\x201','#theme-select\x20option[value=\x22','monstrocity','push','Error\x20clearing\x20progress:','progress-modal','textContent','offsetWidth','flex','Animating\x20recoil\x20for\x20defender:','Error\x20updating\x20theme\x20assets:','Horizontal\x20match\x20found\x20at\x20row\x20','false','getAssets:\x20Fetching\x20Monstrocity\x20assets','transform','Craig','Progress\x20cleared','resolveMatches\x20started,\x20gameOver:','strength','src','powerup','completed','innerHTML','getAssets:\x20Returning\x20merged\x20assets,\x20count=','log','</strong></p>','createCharacter','backgroundImage','https://www.skulliance.io/staking/sounds/badmove.ogg','Main:\x20Game\x20initialized\x20successfully','boostValue','aiTurn','Last\x20Stand\x20applied,\x20mitigated\x20','p2-name','onload','1597164JRaBHl','getAssets:\x20Monstrocity\x20fetch\x20error:','Tile\x20at\x20(','removeChild','px,\x20','animatePowerup','play','checkGameOver','warn','Game\x20over,\x20skipping\x20cascade\x20resolution','updatePlayerDisplay','showProgressPopup','transform\x200.2s\x20ease','\x27s\x20Last\x20Stand\x20mitigates\x20','group'];_0x563e=function(){return _0x2ef7e0;};return _0x563e();}function log(_0x3e68f2){const _0x11c53b=_0x250edb,_0x218466=document[_0x11c53b(0x2fa)]('battle-log'),_0x4a342e=document[_0x11c53b(0x13a)]('li');_0x4a342e[_0x11c53b(0x2b2)]=_0x3e68f2,_0x218466[_0x11c53b(0x143)](_0x4a342e,_0x218466[_0x11c53b(0x164)]),_0x218466[_0x11c53b(0x25a)][_0x11c53b(0x254)]>0x32&&_0x218466['removeChild'](_0x218466[_0x11c53b(0x1e5)]),_0x218466[_0x11c53b(0x174)]=0x0;}const turnIndicator=document['getElementById']('turn-indicator'),p1Name=document[_0x250edb(0x2fa)](_0x250edb(0x1ff)),p1Image=document[_0x250edb(0x2fa)](_0x250edb(0x1ea)),p1Health=document[_0x250edb(0x2fa)](_0x250edb(0x24a)),p1Hp=document['getElementById'](_0x250edb(0x2aa)),p1Strength=document[_0x250edb(0x2fa)](_0x250edb(0x1f4)),p1Speed=document[_0x250edb(0x2fa)]('p1-speed'),p1Tactics=document[_0x250edb(0x2fa)]('p1-tactics'),p1Size=document['getElementById']('p1-size'),p1Powerup=document[_0x250edb(0x2fa)](_0x250edb(0x1c4)),p1Type=document[_0x250edb(0x2fa)]('p1-type'),p2Name=document[_0x250edb(0x2fa)](_0x250edb(0x2cd)),p2Image=document[_0x250edb(0x2fa)](_0x250edb(0x345)),p2Health=document[_0x250edb(0x2fa)]('p2-health'),p2Hp=document[_0x250edb(0x2fa)](_0x250edb(0x129)),p2Strength=document[_0x250edb(0x2fa)]('p2-strength'),p2Speed=document[_0x250edb(0x2fa)](_0x250edb(0x276)),p2Tactics=document['getElementById'](_0x250edb(0x320)),p2Size=document[_0x250edb(0x2fa)](_0x250edb(0x184)),p2Powerup=document['getElementById']('p2-powerup'),p2Type=document['getElementById']('p2-type'),battleLog=document[_0x250edb(0x2fa)]('battle-log'),gameOver=document['getElementById'](_0x250edb(0x279)),assetCache={};async function getAssets(_0x244bb8){const _0x1caae5=_0x250edb;if(assetCache[_0x244bb8])return console[_0x1caae5(0x2c4)](_0x1caae5(0x270)+_0x244bb8),assetCache[_0x244bb8];console[_0x1caae5(0x332)](_0x1caae5(0x18b)+_0x244bb8);let _0x590647=[];try{console[_0x1caae5(0x2c4)](_0x1caae5(0x2b9));const _0x731a94=await Promise[_0x1caae5(0x259)]([fetch(_0x1caae5(0x21e),{'method':_0x1caae5(0x2ee),'headers':{'Content-Type':'application/json'},'body':JSON[_0x1caae5(0x1f1)]({'theme':'monstrocity'})}),new Promise((_0x555bc9,_0x3f095c)=>setTimeout(()=>_0x3f095c(new Error(_0x1caae5(0x144))),0x3e8))]);console[_0x1caae5(0x2c4)](_0x1caae5(0x269),_0x731a94[_0x1caae5(0x19c)]);if(!_0x731a94['ok'])throw new Error('Monstrocity\x20HTTP\x20error!\x20Status:\x20'+_0x731a94[_0x1caae5(0x19c)]);_0x590647=await _0x731a94[_0x1caae5(0x13b)](),console[_0x1caae5(0x2c4)](_0x1caae5(0x2ef),_0x590647),!Array[_0x1caae5(0x137)](_0x590647)&&(_0x590647=[_0x590647]),_0x590647=_0x590647['map']((_0x27db03,_0x4ecc3d)=>{const _0x26af79=_0x1caae5,_0x3301ac={..._0x27db03,'theme':'monstrocity','name':_0x27db03[_0x26af79(0x2e1)]||_0x26af79(0x175)+_0x4ecc3d,'strength':_0x27db03['strength']||0x4,'speed':_0x27db03[_0x26af79(0x1a8)]||0x4,'tactics':_0x27db03[_0x26af79(0x2a5)]||0x4,'size':_0x27db03[_0x26af79(0x349)]||_0x26af79(0x24c),'type':_0x27db03[_0x26af79(0x15c)]||_0x26af79(0x330),'powerup':_0x27db03['powerup']||_0x26af79(0x147)};return _0x3301ac;});}catch(_0x516e83){console[_0x1caae5(0x2e5)](_0x1caae5(0x2d0),_0x516e83),_0x590647=[{'name':_0x1caae5(0x2bb),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1caae5(0x24c),'type':_0x1caae5(0x330),'powerup':'Regenerate','theme':'monstrocity'},{'name':_0x1caae5(0x1d0),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x1caae5(0x1d7),'type':_0x1caae5(0x330),'powerup':'Heal','theme':_0x1caae5(0x2ae)}],console[_0x1caae5(0x2c4)]('getAssets:\x20Using\x20default\x20Monstrocity\x20assets');}if(_0x244bb8===_0x1caae5(0x2ae))return console['log'](_0x1caae5(0x146)),assetCache[_0x244bb8]=_0x590647,console['timeEnd'](_0x1caae5(0x18b)+_0x244bb8),_0x590647;let _0x44dd1d=null;for(const _0x36ef8d of themes){_0x44dd1d=_0x36ef8d[_0x1caae5(0x166)]['find'](_0x4fc2c0=>_0x4fc2c0[_0x1caae5(0x311)]===_0x244bb8);if(_0x44dd1d)break;}if(!_0x44dd1d)return console[_0x1caae5(0x2d7)](_0x1caae5(0x2a3)+_0x244bb8),assetCache[_0x244bb8]=_0x590647,console[_0x1caae5(0x335)](_0x1caae5(0x18b)+_0x244bb8),_0x590647;const _0x1443f8=_0x44dd1d[_0x1caae5(0x1fe)]?_0x44dd1d['policyIds'][_0x1caae5(0x24e)](',')[_0x1caae5(0x263)](_0x5216f8=>_0x5216f8[_0x1caae5(0x352)]()):[];if(!_0x1443f8['length'])return console['log'](_0x1caae5(0x31c)+_0x244bb8),assetCache[_0x244bb8]=_0x590647,console[_0x1caae5(0x335)]('getAssets_'+_0x244bb8),_0x590647;const _0x524a11=_0x44dd1d['orientations']?_0x44dd1d[_0x1caae5(0x192)]['split'](',')[_0x1caae5(0x263)](_0x2df64f=>_0x2df64f[_0x1caae5(0x352)]()):[],_0x2b5928=_0x44dd1d[_0x1caae5(0x159)]?_0x44dd1d['ipfsPrefixes'][_0x1caae5(0x24e)](',')['filter'](_0x5694cd=>_0x5694cd[_0x1caae5(0x352)]()):[],_0x5a3491=_0x1443f8[_0x1caae5(0x233)]((_0x28b4e4,_0x300f30)=>({'policyId':_0x28b4e4,'orientation':_0x524a11[_0x1caae5(0x254)]===0x1?_0x524a11[0x0]:_0x524a11[_0x300f30]||_0x1caae5(0x334),'ipfsPrefix':_0x2b5928[_0x1caae5(0x254)]===0x1?_0x2b5928[0x0]:_0x2b5928[_0x300f30]||'https://ipfs.io/ipfs/'}));let _0x3fd265=[];try{const _0x4c42c8=JSON[_0x1caae5(0x1f1)]({'policyIds':_0x5a3491[_0x1caae5(0x233)](_0x1c9f73=>_0x1c9f73[_0x1caae5(0x1f9)]),'theme':_0x244bb8});console[_0x1caae5(0x2c4)](_0x1caae5(0x153));const _0x4e4c38=await Promise[_0x1caae5(0x259)]([fetch(_0x1caae5(0x293),{'method':_0x1caae5(0x2ee),'headers':{'Content-Type':_0x1caae5(0x240)},'body':_0x4c42c8}),new Promise((_0x29a0a0,_0x16fedf)=>setTimeout(()=>_0x16fedf(new Error(_0x1caae5(0x1b1))),0x2710))]);if(!_0x4e4c38['ok'])throw new Error(_0x1caae5(0x12a)+_0x4e4c38[_0x1caae5(0x19c)]);const _0x17a9ca=await _0x4e4c38[_0x1caae5(0x220)]();let _0xeb29e;try{_0xeb29e=JSON['parse'](_0x17a9ca);}catch(_0x2d4d76){console[_0x1caae5(0x2e5)]('getAssets:\x20NFT\x20parse\x20error:',_0x2d4d76);throw _0x2d4d76;}_0xeb29e===![]||_0xeb29e===_0x1caae5(0x2b8)?(console[_0x1caae5(0x2c4)](_0x1caae5(0x353)),_0x3fd265=[]):_0x3fd265=Array[_0x1caae5(0x137)](_0xeb29e)?_0xeb29e:[_0xeb29e],_0x3fd265=_0x3fd265[_0x1caae5(0x233)]((_0x5b2149,_0x3d7273)=>{const _0x206114=_0x1caae5,_0x3866be={..._0x5b2149,'theme':_0x244bb8,'name':_0x5b2149[_0x206114(0x2e1)]||_0x206114(0x2a6)+_0x3d7273,'strength':_0x5b2149[_0x206114(0x2be)]||0x4,'speed':_0x5b2149[_0x206114(0x1a8)]||0x4,'tactics':_0x5b2149[_0x206114(0x2a5)]||0x4,'size':_0x5b2149[_0x206114(0x349)]||_0x206114(0x24c),'type':_0x5b2149[_0x206114(0x15c)]||_0x206114(0x330),'powerup':_0x5b2149[_0x206114(0x2c0)]||_0x206114(0x147),'policyId':_0x5b2149['policyId']||_0x5a3491[0x0][_0x206114(0x1f9)],'ipfs':_0x5b2149[_0x206114(0x291)]||''};return _0x3866be;});}catch(_0x5c59d4){console[_0x1caae5(0x2e5)]('getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20'+_0x244bb8+':',_0x5c59d4),_0x3fd265=[];}const _0x3a56f3=[..._0x590647,..._0x3fd265];return console['log'](_0x1caae5(0x2c3)+_0x3a56f3['length']),assetCache[_0x244bb8]=_0x3a56f3,console[_0x1caae5(0x335)]('getAssets_'+_0x244bb8),_0x3a56f3;}document[_0x250edb(0x1f8)](_0x250edb(0x31d),function(){var _0x44082d=function(){const _0x35dd5e=_0x5db5;var _0x147604=localStorage['getItem']('gameTheme')||_0x35dd5e(0x2ae);getAssets(_0x147604)[_0x35dd5e(0x33a)](function(_0x2a196c){const _0x38de50=_0x35dd5e;console[_0x38de50(0x2c4)](_0x38de50(0x246),_0x2a196c);var _0x5051b9=new MonstrocityMatch3(_0x2a196c,_0x147604);console[_0x38de50(0x2c4)](_0x38de50(0x1da)),_0x5051b9[_0x38de50(0x17d)]()['then'](function(){const _0x548e2e=_0x38de50;console[_0x548e2e(0x2c4)](_0x548e2e(0x2c9));});})[_0x35dd5e(0x1fb)](function(_0x357310){const _0x147418=_0x35dd5e;console[_0x147418(0x2e5)](_0x147418(0x341),_0x357310);});};_0x44082d();});
  </script>
</body>
</html>
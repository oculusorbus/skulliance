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
		margin-top: 60px;
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
	      group: "Rugged Project Themes",
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
	  
const _0x50a5e2=_0x28cb;(function(_0x33fc0b,_0x11bffd){const _0x12af29=_0x28cb,_0x3ebfd3=_0x33fc0b();while(!![]){try{const _0x29f088=-parseInt(_0x12af29(0x2d6))/0x1+-parseInt(_0x12af29(0x1ce))/0x2+-parseInt(_0x12af29(0x23d))/0x3*(parseInt(_0x12af29(0x3a1))/0x4)+-parseInt(_0x12af29(0x396))/0x5*(-parseInt(_0x12af29(0x1e2))/0x6)+-parseInt(_0x12af29(0x34a))/0x7*(-parseInt(_0x12af29(0x2d9))/0x8)+-parseInt(_0x12af29(0x252))/0x9*(parseInt(_0x12af29(0x248))/0xa)+parseInt(_0x12af29(0x2be))/0xb*(parseInt(_0x12af29(0x394))/0xc);if(_0x29f088===_0x11bffd)break;else _0x3ebfd3['push'](_0x3ebfd3['shift']());}catch(_0xded3ee){_0x3ebfd3['push'](_0x3ebfd3['shift']());}}}(_0x17cc,0x6b017));function showThemeSelect(_0x31867e){const _0x47f958=_0x28cb;console[_0x47f958(0x205)]('showThemeSelect');let _0x357176=document[_0x47f958(0x1b8)](_0x47f958(0x31c));const _0x476223=document['getElementById'](_0x47f958(0x2ec));_0x357176[_0x47f958(0x196)]=_0x47f958(0x1bd);const _0x97fcb9=document[_0x47f958(0x1b8)]('theme-options');_0x357176[_0x47f958(0x1e5)][_0x47f958(0x2bd)]='block',_0x476223[_0x47f958(0x1e5)][_0x47f958(0x2bd)]='none',themes[_0x47f958(0x277)](_0x1a9c20=>{const _0x19b45e=_0x47f958,_0x4a0dc6=document[_0x19b45e(0x36a)]('div');_0x4a0dc6['className']=_0x19b45e(0x31b);const _0x50a917=document[_0x19b45e(0x36a)]('h3');_0x50a917[_0x19b45e(0x226)]=_0x1a9c20[_0x19b45e(0x314)],_0x4a0dc6[_0x19b45e(0x1de)](_0x50a917),_0x1a9c20[_0x19b45e(0x32d)][_0x19b45e(0x277)](_0x5816ef=>{const _0x217ab7=_0x19b45e,_0x2743a6=document[_0x217ab7(0x36a)](_0x217ab7(0x36f));_0x2743a6[_0x217ab7(0x28d)]='theme-option';if(_0x5816ef['background']){const _0x17a63b='https://www.skulliance.io/staking/images/monstrocity/'+_0x5816ef[_0x217ab7(0x282)]+_0x217ab7(0x22b);_0x2743a6[_0x217ab7(0x1e5)]['backgroundImage']='url('+_0x17a63b+')';}const _0x1a506d='https://www.skulliance.io/staking/images/monstrocity/'+_0x5816ef[_0x217ab7(0x282)]+_0x217ab7(0x2dc);_0x2743a6[_0x217ab7(0x196)]='\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22'+_0x1a506d+_0x217ab7(0x30c)+_0x5816ef['title']+_0x217ab7(0x2eb)+_0x5816ef[_0x217ab7(0x24a)]+_0x217ab7(0x306)+_0x5816ef['title']+_0x217ab7(0x2cf),_0x2743a6[_0x217ab7(0x1cb)](_0x217ab7(0x338),()=>{const _0x2469f4=_0x217ab7,_0x1d3515=document[_0x2469f4(0x1b8)]('character-options');_0x1d3515&&(_0x1d3515[_0x2469f4(0x196)]=_0x2469f4(0x26a)),_0x357176[_0x2469f4(0x196)]='',_0x357176[_0x2469f4(0x1e5)][_0x2469f4(0x2bd)]=_0x2469f4(0x2b3),_0x476223['style']['display']=_0x2469f4(0x240),_0x31867e[_0x2469f4(0x19f)](_0x5816ef['value']);}),_0x4a0dc6[_0x217ab7(0x1de)](_0x2743a6);}),_0x97fcb9[_0x19b45e(0x1de)](_0x4a0dc6);}),document['getElementById']('theme-close-button')[_0x47f958(0x290)]=()=>{const _0x3c1d6d=_0x47f958,_0x41a57d=document['getElementById'](_0x3c1d6d(0x1ab));_0x41a57d&&(_0x41a57d[_0x3c1d6d(0x196)]=''),_0x357176['innerHTML']='',_0x357176[_0x3c1d6d(0x1e5)]['display']=_0x3c1d6d(0x2b3),_0x476223[_0x3c1d6d(0x1e5)][_0x3c1d6d(0x2bd)]='block';},console[_0x47f958(0x203)](_0x47f958(0x23c));}const opponentsConfig=[{'name':_0x50a5e2(0x3a6),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':_0x50a5e2(0x21e),'powerup':'Minor\x20Regen','theme':'monstrocity'},{'name':_0x50a5e2(0x310),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x50a5e2(0x3a3),'type':_0x50a5e2(0x21e),'powerup':'Minor\x20Regen','theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x335),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Small','type':_0x50a5e2(0x21e),'powerup':'Minor\x20Regen','theme':_0x50a5e2(0x217)},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x21e),'powerup':_0x50a5e2(0x2c4),'theme':_0x50a5e2(0x217)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x50a5e2(0x21e),'powerup':_0x50a5e2(0x238),'theme':'monstrocity'},{'name':_0x50a5e2(0x2db),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':'Base','powerup':_0x50a5e2(0x238),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x24d),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x50a5e2(0x365),'type':_0x50a5e2(0x21e),'powerup':_0x50a5e2(0x238),'theme':_0x50a5e2(0x217)},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x21e),'powerup':_0x50a5e2(0x238),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x29f),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x50a5e2(0x316),'type':'Base','powerup':'Boost\x20Attack','theme':_0x50a5e2(0x217)},{'name':'Jarhead','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x50a5e2(0x316),'type':'Base','powerup':'Boost\x20Attack','theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x333),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x50a5e2(0x365),'type':_0x50a5e2(0x21e),'powerup':_0x50a5e2(0x39d),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x285),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x50a5e2(0x3a3),'type':'Base','powerup':'Heal','theme':_0x50a5e2(0x217)},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x50a5e2(0x316),'type':'Base','powerup':_0x50a5e2(0x39d),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x1e8),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x50a5e2(0x316),'type':'Base','powerup':_0x50a5e2(0x39d),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x3a6),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x2c4),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x310),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x50a5e2(0x3a3),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x2c4),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x335),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x50a5e2(0x365),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x2c4),'theme':_0x50a5e2(0x217)},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x50a5e2(0x316),'type':'Leader','powerup':_0x50a5e2(0x385),'theme':_0x50a5e2(0x217)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x238),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x2db),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x356),'powerup':'Regenerate','theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x24d),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x50a5e2(0x365),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x238),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x361),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x238),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x29f),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x24f),'theme':'monstrocity'},{'name':_0x50a5e2(0x1ca),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x24f),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x333),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x39d),'theme':'monstrocity'},{'name':_0x50a5e2(0x285),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x50a5e2(0x3a3),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x39d),'theme':_0x50a5e2(0x217)},{'name':_0x50a5e2(0x1f7),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x50a5e2(0x316),'type':'Leader','powerup':'Heal','theme':'monstrocity'},{'name':_0x50a5e2(0x1e8),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x50a5e2(0x316),'type':_0x50a5e2(0x356),'powerup':_0x50a5e2(0x39d),'theme':_0x50a5e2(0x217)}],characterDirections={'Billandar\x20and\x20Ted':_0x50a5e2(0x36e),'Craig':_0x50a5e2(0x36e),'Dankle':'Left','Drake':'Right','Goblin\x20Ganger':_0x50a5e2(0x36e),'Jarhead':_0x50a5e2(0x398),'Katastrophy':'Right','Koipon':_0x50a5e2(0x36e),'Mandiblus':_0x50a5e2(0x36e),'Merdock':_0x50a5e2(0x36e),'Ouchie':'Left','Slime\x20Mind':_0x50a5e2(0x398),'Spydrax':_0x50a5e2(0x398),'Texby':_0x50a5e2(0x36e)};class MonstrocityMatch3{constructor(_0x3e51da,_0x2af9af){const _0x4f2fc9=_0x50a5e2;this[_0x4f2fc9(0x211)]=_0x4f2fc9(0x298)in window||navigator[_0x4f2fc9(0x340)]>0x0||navigator[_0x4f2fc9(0x220)]>0x0,this[_0x4f2fc9(0x288)]=0x5,this[_0x4f2fc9(0x36b)]=0x5,this[_0x4f2fc9(0x39c)]=[],this[_0x4f2fc9(0x236)]=null,this[_0x4f2fc9(0x2e3)]=![],this[_0x4f2fc9(0x2f2)]=null,this['player1']=null,this[_0x4f2fc9(0x202)]=null,this[_0x4f2fc9(0x300)]=_0x4f2fc9(0x214),this[_0x4f2fc9(0x199)]=![],this[_0x4f2fc9(0x2cb)]=null,this[_0x4f2fc9(0x377)]=null,this['offsetX']=0x0,this[_0x4f2fc9(0x364)]=0x0,this[_0x4f2fc9(0x242)]=0x1,this['playerCharactersConfig']=_0x3e51da,this[_0x4f2fc9(0x271)]=[],this[_0x4f2fc9(0x268)]=![],this[_0x4f2fc9(0x32a)]=['first-attack',_0x4f2fc9(0x25b),_0x4f2fc9(0x35e),_0x4f2fc9(0x29e),'last-stand'],this[_0x4f2fc9(0x1d9)]=[],this[_0x4f2fc9(0x1ee)]=0x0;const _0x18078e=themes[_0x4f2fc9(0x1d4)](_0x42945c=>_0x42945c[_0x4f2fc9(0x32d)])[_0x4f2fc9(0x1bf)](_0x341780=>_0x341780['value']),_0x4e3098=localStorage[_0x4f2fc9(0x23e)](_0x4f2fc9(0x1b2));this[_0x4f2fc9(0x247)]=_0x4e3098&&_0x18078e['includes'](_0x4e3098)?_0x4e3098:_0x2af9af&&_0x18078e['includes'](_0x2af9af)?_0x2af9af:_0x4f2fc9(0x217),console[_0x4f2fc9(0x1fc)]('constructor:\x20initialTheme='+_0x2af9af+',\x20storedTheme='+_0x4e3098+_0x4f2fc9(0x39e)+this['theme']),this['baseImagePath']=_0x4f2fc9(0x225)+this['theme']+'/',this[_0x4f2fc9(0x389)]={'match':new Audio(_0x4f2fc9(0x26b)),'cascade':new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),'badMove':new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),'gameOver':new Audio(_0x4f2fc9(0x24e)),'reset':new Audio(_0x4f2fc9(0x1af)),'loss':new Audio(_0x4f2fc9(0x26c)),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio(_0x4f2fc9(0x2d8)),'powerGem':new Audio(_0x4f2fc9(0x2ef)),'hyperCube':new Audio(_0x4f2fc9(0x303)),'multiMatch':new Audio(_0x4f2fc9(0x31f))},this[_0x4f2fc9(0x273)](),this[_0x4f2fc9(0x2e8)]();}async[_0x50a5e2(0x39f)](){const _0x1d69a1=_0x50a5e2;console[_0x1d69a1(0x1fc)](_0x1d69a1(0x33b)),this['playerCharacters']=this[_0x1d69a1(0x370)][_0x1d69a1(0x1bf)](_0x32bf2c=>this['createCharacter'](_0x32bf2c)),await this[_0x1d69a1(0x339)](!![]);const _0xe33bad=await this['loadProgress'](),{loadedLevel:_0x23114d,loadedScore:_0x283ded,hasProgress:_0x59c3e9}=_0xe33bad;if(_0x59c3e9){console['log'](_0x1d69a1(0x2f4)+_0x23114d+_0x1d69a1(0x1c9)+_0x283ded);const _0x8ad438=await this[_0x1d69a1(0x27b)](_0x23114d,_0x283ded);_0x8ad438?(this['currentLevel']=_0x23114d,this['grandTotalScore']=_0x283ded,log(_0x1d69a1(0x24b)+this['currentLevel']+_0x1d69a1(0x1ad)+this['grandTotalScore'])):(this[_0x1d69a1(0x242)]=0x1,this[_0x1d69a1(0x1ee)]=0x0,await this[_0x1d69a1(0x392)](),log(_0x1d69a1(0x1b3)));}else this[_0x1d69a1(0x242)]=0x1,this['grandTotalScore']=0x0,log(_0x1d69a1(0x201));console[_0x1d69a1(0x1fc)](_0x1d69a1(0x354));}[_0x50a5e2(0x280)](){const _0x2c0163=_0x50a5e2;console[_0x2c0163(0x1fc)](_0x2c0163(0x22d)+this['theme']);const _0x206082=themes[_0x2c0163(0x1d4)](_0x1c5e36=>_0x1c5e36[_0x2c0163(0x32d)])[_0x2c0163(0x369)](_0x4c26d5=>_0x4c26d5[_0x2c0163(0x282)]===this[_0x2c0163(0x247)]);console[_0x2c0163(0x1fc)](_0x2c0163(0x38a),_0x206082);const _0x40c356='https://www.skulliance.io/staking/images/monstrocity/'+this[_0x2c0163(0x247)]+_0x2c0163(0x22b);console[_0x2c0163(0x1fc)](_0x2c0163(0x33d)+_0x40c356),_0x206082&&_0x206082[_0x2c0163(0x28f)]?(document['body']['style'][_0x2c0163(0x1bc)]=_0x2c0163(0x35f)+_0x40c356+')',document[_0x2c0163(0x346)]['style'][_0x2c0163(0x2d1)]=_0x2c0163(0x359),document[_0x2c0163(0x346)][_0x2c0163(0x1e5)][_0x2c0163(0x250)]=_0x2c0163(0x281)):document[_0x2c0163(0x346)][_0x2c0163(0x1e5)]['backgroundImage']=_0x2c0163(0x2b3);}[_0x50a5e2(0x19f)](_0x2c0caa){const _0x551125=_0x50a5e2;if(updatePending){console[_0x551125(0x1fc)](_0x551125(0x1a7));return;}updatePending=!![],console[_0x551125(0x205)]('updateTheme_'+_0x2c0caa);var _0x338492=this;this[_0x551125(0x247)]=_0x2c0caa,this['baseImagePath']=_0x551125(0x225)+this[_0x551125(0x247)]+'/',localStorage[_0x551125(0x34d)](_0x551125(0x1b2),this[_0x551125(0x247)]),this[_0x551125(0x280)](),getAssets(this[_0x551125(0x247)])[_0x551125(0x309)](function(_0x55f884){const _0x35fb2b=_0x551125;console['time'](_0x35fb2b(0x2a4)+_0x2c0caa),_0x338492[_0x35fb2b(0x370)]=_0x55f884,_0x338492[_0x35fb2b(0x271)]=[],_0x55f884[_0x35fb2b(0x277)](_0x564df7=>{const _0x33aacd=_0x35fb2b,_0x50be2c=_0x338492[_0x33aacd(0x1b7)](_0x564df7),_0x1d11f0=new Image();_0x1d11f0[_0x33aacd(0x2fc)]=_0x50be2c['imageUrl'],_0x1d11f0[_0x33aacd(0x35d)]=()=>console['log']('Preloaded:\x20'+_0x50be2c[_0x33aacd(0x197)]),_0x1d11f0[_0x33aacd(0x305)]=()=>console['log'](_0x33aacd(0x38e)+_0x50be2c[_0x33aacd(0x197)]),_0x338492['playerCharacters'][_0x33aacd(0x21d)](_0x50be2c);});if(_0x338492[_0x35fb2b(0x1a5)]){var _0xbe7b17=_0x338492[_0x35fb2b(0x370)][_0x35fb2b(0x369)](function(_0x2123ef){const _0x19b0f4=_0x35fb2b;return _0x2123ef['name']===_0x338492['player1'][_0x19b0f4(0x2e2)];})||_0x338492['playerCharactersConfig'][0x0];_0x338492['player1']=_0x338492[_0x35fb2b(0x1b7)](_0xbe7b17),_0x338492[_0x35fb2b(0x229)]();}_0x338492[_0x35fb2b(0x202)]&&(_0x338492[_0x35fb2b(0x202)]=_0x338492[_0x35fb2b(0x1b7)](opponentsConfig[_0x338492[_0x35fb2b(0x242)]-0x1]),_0x338492[_0x35fb2b(0x1d7)]());document[_0x35fb2b(0x38d)](_0x35fb2b(0x289))['src']=_0x338492['baseImagePath']+_0x35fb2b(0x38b);var _0x13ed69=document[_0x35fb2b(0x1b8)]('character-select-container');_0x13ed69[_0x35fb2b(0x1e5)]['display']===_0x35fb2b(0x240)&&_0x338492[_0x35fb2b(0x339)](_0x338492[_0x35fb2b(0x1a5)]===null),console[_0x35fb2b(0x203)](_0x35fb2b(0x2a4)+_0x2c0caa),console[_0x35fb2b(0x203)](_0x35fb2b(0x258)+_0x2c0caa),updatePending=![];})['catch'](function(_0x443aac){const _0x11a23a=_0x551125;console[_0x11a23a(0x241)](_0x11a23a(0x348),_0x443aac),console[_0x11a23a(0x203)](_0x11a23a(0x258)+_0x2c0caa),updatePending=![];});}async[_0x50a5e2(0x2d0)](){const _0x43c70e=_0x50a5e2,_0x172609={'currentLevel':this['currentLevel'],'grandTotalScore':this[_0x43c70e(0x1ee)]};console['log']('Sending\x20saveProgress\x20request\x20with\x20data:',_0x172609);try{const _0x5112b8=await fetch(_0x43c70e(0x2c7),{'method':'POST','headers':{'Content-Type':_0x43c70e(0x1cc)},'body':JSON[_0x43c70e(0x37a)](_0x172609)});console['log']('Response\x20status:',_0x5112b8[_0x43c70e(0x325)]);const _0x52bff0=await _0x5112b8[_0x43c70e(0x387)]();console['log'](_0x43c70e(0x243),_0x52bff0);if(!_0x5112b8['ok'])throw new Error(_0x43c70e(0x262)+_0x5112b8[_0x43c70e(0x325)]);const _0x29b3f0=JSON[_0x43c70e(0x1b6)](_0x52bff0);console['log'](_0x43c70e(0x2f1),_0x29b3f0),_0x29b3f0[_0x43c70e(0x325)]===_0x43c70e(0x2d5)?log(_0x43c70e(0x3a0)+this[_0x43c70e(0x242)]):console[_0x43c70e(0x241)]('Failed\x20to\x20save\x20progress:',_0x29b3f0[_0x43c70e(0x2a2)]);}catch(_0x3a2427){console[_0x43c70e(0x241)](_0x43c70e(0x2ed),_0x3a2427);}}async[_0x50a5e2(0x358)](){const _0x74d7cc=_0x50a5e2;try{console[_0x74d7cc(0x1fc)](_0x74d7cc(0x2c3));const _0x2b388b=await fetch(_0x74d7cc(0x221),{'method':_0x74d7cc(0x3a4),'headers':{'Content-Type':'application/json'}});console['log']('Response\x20status:',_0x2b388b['status']);if(!_0x2b388b['ok'])throw new Error(_0x74d7cc(0x262)+_0x2b388b[_0x74d7cc(0x325)]);const _0x32d979=await _0x2b388b[_0x74d7cc(0x1df)]();console[_0x74d7cc(0x1fc)](_0x74d7cc(0x2f1),_0x32d979);if(_0x32d979[_0x74d7cc(0x325)]===_0x74d7cc(0x2d5)&&_0x32d979[_0x74d7cc(0x287)]){const _0x251f33=_0x32d979[_0x74d7cc(0x287)];return{'loadedLevel':_0x251f33[_0x74d7cc(0x242)]||0x1,'loadedScore':_0x251f33['grandTotalScore']||0x0,'hasProgress':!![]};}else return console['log']('No\x20progress\x20found\x20or\x20status\x20not\x20success:',_0x32d979),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x1edb87){return console[_0x74d7cc(0x241)](_0x74d7cc(0x264),_0x1edb87),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x50a5e2(0x392)](){const _0x457657=_0x50a5e2;try{const _0x28b14c=await fetch(_0x457657(0x265),{'method':_0x457657(0x2e5),'headers':{'Content-Type':'application/json'}});if(!_0x28b14c['ok'])throw new Error(_0x457657(0x262)+_0x28b14c[_0x457657(0x325)]);const _0x307259=await _0x28b14c['json']();_0x307259[_0x457657(0x325)]===_0x457657(0x2d5)&&(this[_0x457657(0x242)]=0x1,this[_0x457657(0x1ee)]=0x0,log(_0x457657(0x30e)));}catch(_0x559ab3){console[_0x457657(0x241)](_0x457657(0x24c),_0x559ab3);}}['updateTileSizeWithGap'](){const _0x516792=_0x50a5e2,_0x14732a=document[_0x516792(0x1b8)](_0x516792(0x292)),_0xcc814=_0x14732a['offsetWidth']||0x12c;this[_0x516792(0x19c)]=(_0xcc814-0.5*(this[_0x516792(0x288)]-0x1))/this[_0x516792(0x288)];}['createCharacter'](_0x5cb644){const _0x57df44=_0x50a5e2;console[_0x57df44(0x1fc)](_0x57df44(0x2b6),_0x5cb644);var _0x3bd66a,_0x3e90a8,_0xde8981=_0x57df44(0x36e),_0x3301f3=![];if(_0x5cb644[_0x57df44(0x1ef)]&&_0x5cb644[_0x57df44(0x237)]){_0x3301f3=!![];var _0x4a6ec1=document['querySelector']('#theme-select\x20option[value=\x22'+_0x5cb644['theme']+'\x22]'),_0x1f9987={'orientation':_0x57df44(0x398),'ipfsPrefix':'https://ipfs.io/ipfs/'};if(_0x4a6ec1){var _0x2a5792=_0x4a6ec1[_0x57df44(0x261)]['policyIds']?_0x4a6ec1[_0x57df44(0x261)][_0x57df44(0x190)]['split'](',')['filter'](function(_0x2a72f2){return _0x2a72f2['trim']();}):[],_0x374d35=_0x4a6ec1[_0x57df44(0x261)][_0x57df44(0x209)]?_0x4a6ec1[_0x57df44(0x261)][_0x57df44(0x209)][_0x57df44(0x279)](',')[_0x57df44(0x334)](function(_0x1e31a3){const _0x25b9f3=_0x57df44;return _0x1e31a3[_0x25b9f3(0x21b)]();}):[],_0x9a16fb=_0x4a6ec1[_0x57df44(0x261)]['ipfsPrefixes']?_0x4a6ec1[_0x57df44(0x261)]['ipfsPrefixes'][_0x57df44(0x279)](',')['filter'](function(_0x350566){const _0x120a1b=_0x57df44;return _0x350566[_0x120a1b(0x21b)]();}):[],_0x4359b0=_0x2a5792[_0x57df44(0x360)](_0x5cb644['policyId']);_0x4359b0!==-0x1&&(_0x1f9987={'orientation':_0x374d35['length']===0x1?_0x374d35[0x0]:_0x374d35[_0x4359b0]||_0x57df44(0x398),'ipfsPrefix':_0x9a16fb[_0x57df44(0x33f)]===0x1?_0x9a16fb[0x0]:_0x9a16fb[_0x4359b0]||_0x57df44(0x1ed)});}_0x1f9987[_0x57df44(0x341)]===_0x57df44(0x1c4)?_0xde8981=Math[_0x57df44(0x330)]()<0.5?_0x57df44(0x36e):_0x57df44(0x398):_0xde8981=_0x1f9987[_0x57df44(0x341)],_0x3e90a8=_0x1f9987[_0x57df44(0x263)]+_0x5cb644[_0x57df44(0x1ef)];}else{switch(_0x5cb644[_0x57df44(0x25e)]){case _0x57df44(0x21e):_0x3bd66a='base';break;case'Leader':_0x3bd66a=_0x57df44(0x2e1);break;case'Battle\x20Damaged':_0x3bd66a=_0x57df44(0x357);break;default:_0x3bd66a=_0x57df44(0x323);}_0x3e90a8=this[_0x57df44(0x218)]+_0x3bd66a+'/'+_0x5cb644[_0x57df44(0x2e2)][_0x57df44(0x2ab)]()[_0x57df44(0x2ad)](/ /g,'-')+_0x57df44(0x308),_0xde8981=characterDirections[_0x5cb644[_0x57df44(0x2e2)]]||_0x57df44(0x36e);}var _0x349d2c;switch(_0x5cb644['type']){case'Leader':_0x349d2c=0x64;break;case _0x57df44(0x2f8):_0x349d2c=0x46;break;case _0x57df44(0x21e):default:_0x349d2c=0x55;}var _0x52cbef=0x1,_0x469442=0x0;switch(_0x5cb644['size']){case'Large':_0x52cbef=1.2,_0x469442=_0x5cb644[_0x57df44(0x2ba)]>0x1?-0x2:0x0;break;case _0x57df44(0x365):_0x52cbef=0.8,_0x469442=_0x5cb644[_0x57df44(0x2ba)]<0x6?0x2:0x7-_0x5cb644[_0x57df44(0x2ba)];break;case _0x57df44(0x316):_0x52cbef=0x1,_0x469442=0x0;break;}var _0x3b2c0f=Math['round'](_0x349d2c*_0x52cbef),_0x4a1ce6=Math['max'](0x1,Math[_0x57df44(0x376)](0x7,_0x5cb644['tactics']+_0x469442));return{'name':_0x5cb644['name'],'type':_0x5cb644[_0x57df44(0x25e)],'strength':_0x5cb644[_0x57df44(0x296)],'speed':_0x5cb644['speed'],'tactics':_0x4a1ce6,'size':_0x5cb644[_0x57df44(0x29c)],'powerup':_0x5cb644[_0x57df44(0x1d6)],'health':_0x3b2c0f,'maxHealth':_0x3b2c0f,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x3e90a8,'orientation':_0xde8981,'isNFT':_0x3301f3};}[_0x50a5e2(0x29a)](_0x15bbec,_0x51b0e7,_0x283e44=![]){const _0x20cf3b=_0x50a5e2;_0x15bbec['orientation']===_0x20cf3b(0x36e)?(_0x15bbec['orientation']=_0x20cf3b(0x398),_0x51b0e7[_0x20cf3b(0x1e5)]['transform']=_0x283e44?_0x20cf3b(0x228):_0x20cf3b(0x2b3)):(_0x15bbec['orientation']='Left',_0x51b0e7['style'][_0x20cf3b(0x20a)]=_0x283e44?_0x20cf3b(0x2b3):_0x20cf3b(0x228)),log(_0x15bbec[_0x20cf3b(0x2e2)]+_0x20cf3b(0x320)+_0x15bbec['orientation']+'!');}[_0x50a5e2(0x339)](_0x47a357){const _0x4c52a6=_0x50a5e2;var _0x10ef06=this;console['time'](_0x4c52a6(0x339));var _0x179e8f=document[_0x4c52a6(0x1b8)](_0x4c52a6(0x2ec)),_0x12e391=document[_0x4c52a6(0x1b8)]('character-options');_0x12e391[_0x4c52a6(0x196)]='',_0x179e8f[_0x4c52a6(0x1e5)]['display']='block',document[_0x4c52a6(0x1b8)]('theme-select-button')[_0x4c52a6(0x290)]=()=>{showThemeSelect(_0x10ef06);};const _0x59d229=document[_0x4c52a6(0x22f)]();this[_0x4c52a6(0x271)][_0x4c52a6(0x277)](function(_0x506389){const _0x5ad96b=_0x4c52a6;var _0xfc73f5=document[_0x5ad96b(0x36a)](_0x5ad96b(0x36f));_0xfc73f5['className']='character-option',_0xfc73f5[_0x5ad96b(0x196)]=_0x5ad96b(0x198)+_0x506389[_0x5ad96b(0x197)]+'\x22\x20alt=\x22'+_0x506389[_0x5ad96b(0x2e2)]+'\x22>'+_0x5ad96b(0x301)+_0x506389['name']+_0x5ad96b(0x32b)+_0x5ad96b(0x2e4)+_0x506389[_0x5ad96b(0x25e)]+_0x5ad96b(0x235)+_0x5ad96b(0x206)+_0x506389[_0x5ad96b(0x350)]+_0x5ad96b(0x235)+_0x5ad96b(0x378)+_0x506389[_0x5ad96b(0x296)]+'</p>'+_0x5ad96b(0x1a6)+_0x506389[_0x5ad96b(0x37d)]+_0x5ad96b(0x235)+_0x5ad96b(0x353)+_0x506389[_0x5ad96b(0x2ba)]+_0x5ad96b(0x235)+_0x5ad96b(0x39b)+_0x506389[_0x5ad96b(0x29c)]+_0x5ad96b(0x235)+_0x5ad96b(0x246)+_0x506389[_0x5ad96b(0x1d6)]+'</p>',_0xfc73f5['addEventListener'](_0x5ad96b(0x338),function(){const _0x24caa9=_0x5ad96b;console[_0x24caa9(0x1fc)](_0x24caa9(0x390)+_0x506389[_0x24caa9(0x2e2)]),_0x179e8f['style'][_0x24caa9(0x2bd)]='none',_0x47a357?(_0x10ef06['player1']={'name':_0x506389[_0x24caa9(0x2e2)],'type':_0x506389['type'],'strength':_0x506389[_0x24caa9(0x296)],'speed':_0x506389[_0x24caa9(0x37d)],'tactics':_0x506389[_0x24caa9(0x2ba)],'size':_0x506389[_0x24caa9(0x29c)],'powerup':_0x506389[_0x24caa9(0x1d6)],'health':_0x506389[_0x24caa9(0x2c9)],'maxHealth':_0x506389['maxHealth'],'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x506389[_0x24caa9(0x197)],'orientation':_0x506389[_0x24caa9(0x341)],'isNFT':_0x506389[_0x24caa9(0x222)]},console[_0x24caa9(0x1fc)](_0x24caa9(0x30a)+_0x10ef06[_0x24caa9(0x1a5)][_0x24caa9(0x2e2)]),_0x10ef06[_0x24caa9(0x2c0)]()):_0x10ef06[_0x24caa9(0x1a2)](_0x506389);}),_0x59d229['appendChild'](_0xfc73f5);}),_0x12e391[_0x4c52a6(0x1de)](_0x59d229),console[_0x4c52a6(0x203)](_0x4c52a6(0x339));}[_0x50a5e2(0x1a2)](_0x3940d2){const _0xcdeb2c=_0x50a5e2,_0x5a573e=this[_0xcdeb2c(0x1a5)][_0xcdeb2c(0x2c9)],_0x21bae7=this[_0xcdeb2c(0x1a5)]['maxHealth'],_0x4f1608={..._0x3940d2},_0x476d30=Math[_0xcdeb2c(0x376)](0x1,_0x5a573e/_0x21bae7);_0x4f1608['health']=Math['round'](_0x4f1608[_0xcdeb2c(0x350)]*_0x476d30),_0x4f1608[_0xcdeb2c(0x2c9)]=Math[_0xcdeb2c(0x294)](0x0,Math[_0xcdeb2c(0x376)](_0x4f1608[_0xcdeb2c(0x350)],_0x4f1608[_0xcdeb2c(0x2c9)])),_0x4f1608[_0xcdeb2c(0x2d4)]=![],_0x4f1608[_0xcdeb2c(0x1eb)]=0x0,_0x4f1608['lastStandActive']=![],this['player1']=_0x4f1608,this[_0xcdeb2c(0x229)](),this[_0xcdeb2c(0x2fd)](this[_0xcdeb2c(0x1a5)]),log(this[_0xcdeb2c(0x1a5)][_0xcdeb2c(0x2e2)]+_0xcdeb2c(0x38c)+this['player1'][_0xcdeb2c(0x2c9)]+'/'+this[_0xcdeb2c(0x1a5)][_0xcdeb2c(0x350)]+_0xcdeb2c(0x1ff)),this[_0xcdeb2c(0x2f2)]=this[_0xcdeb2c(0x1a5)][_0xcdeb2c(0x37d)]>this[_0xcdeb2c(0x202)][_0xcdeb2c(0x37d)]?this[_0xcdeb2c(0x1a5)]:this['player2'][_0xcdeb2c(0x37d)]>this[_0xcdeb2c(0x1a5)][_0xcdeb2c(0x37d)]?this['player2']:this[_0xcdeb2c(0x1a5)][_0xcdeb2c(0x296)]>=this[_0xcdeb2c(0x202)][_0xcdeb2c(0x296)]?this['player1']:this[_0xcdeb2c(0x202)],turnIndicator[_0xcdeb2c(0x226)]='Level\x20'+this[_0xcdeb2c(0x242)]+'\x20-\x20'+(this[_0xcdeb2c(0x2f2)]===this['player1']?_0xcdeb2c(0x304):'Opponent')+_0xcdeb2c(0x257),this['currentTurn']===this[_0xcdeb2c(0x202)]&&this[_0xcdeb2c(0x300)]!==_0xcdeb2c(0x2e3)&&setTimeout(()=>this[_0xcdeb2c(0x234)](),0x3e8);}[_0x50a5e2(0x27b)](_0x2e1790,_0x33c774){const _0x2743c9=_0x50a5e2;return console[_0x2743c9(0x1fc)](_0x2743c9(0x1a8)+_0x2e1790+_0x2743c9(0x208)+_0x33c774),new Promise(_0x1c9313=>{const _0x5d6843=_0x2743c9,_0x7108ad=document[_0x5d6843(0x36a)](_0x5d6843(0x36f));_0x7108ad['id']='progress-modal',_0x7108ad[_0x5d6843(0x28d)]='progress-modal';const _0x3b79a0=document[_0x5d6843(0x36a)](_0x5d6843(0x36f));_0x3b79a0['className']='progress-modal-content';const _0x3e8824=document['createElement']('p');_0x3e8824['id']='progress-message',_0x3e8824['textContent']=_0x5d6843(0x384)+_0x2e1790+_0x5d6843(0x2e9)+_0x33c774+'?',_0x3b79a0[_0x5d6843(0x1de)](_0x3e8824);const _0x5270f0=document[_0x5d6843(0x36a)](_0x5d6843(0x36f));_0x5270f0[_0x5d6843(0x28d)]='progress-modal-buttons';const _0x3e976c=document[_0x5d6843(0x36a)](_0x5d6843(0x2a5));_0x3e976c['id']=_0x5d6843(0x23a),_0x3e976c[_0x5d6843(0x226)]=_0x5d6843(0x2c2),_0x5270f0[_0x5d6843(0x1de)](_0x3e976c);const _0x576acc=document[_0x5d6843(0x36a)](_0x5d6843(0x2a5));_0x576acc['id']=_0x5d6843(0x1f5),_0x576acc[_0x5d6843(0x226)]=_0x5d6843(0x2bb),_0x5270f0[_0x5d6843(0x1de)](_0x576acc),_0x3b79a0[_0x5d6843(0x1de)](_0x5270f0),_0x7108ad[_0x5d6843(0x1de)](_0x3b79a0),document[_0x5d6843(0x346)][_0x5d6843(0x1de)](_0x7108ad),_0x7108ad['style'][_0x5d6843(0x2bd)]=_0x5d6843(0x379);const _0x47ebda=()=>{const _0x4ce599=_0x5d6843;console[_0x4ce599(0x1fc)](_0x4ce599(0x375)),_0x7108ad[_0x4ce599(0x1e5)][_0x4ce599(0x2bd)]=_0x4ce599(0x2b3),document[_0x4ce599(0x346)][_0x4ce599(0x194)](_0x7108ad),_0x3e976c['removeEventListener'](_0x4ce599(0x338),_0x47ebda),_0x576acc[_0x4ce599(0x2c8)](_0x4ce599(0x338),_0x39f20b),_0x1c9313(!![]);},_0x39f20b=()=>{const _0x5df79b=_0x5d6843;console[_0x5df79b(0x1fc)]('showProgressPopup:\x20User\x20chose\x20Restart'),_0x7108ad[_0x5df79b(0x1e5)][_0x5df79b(0x2bd)]='none',document[_0x5df79b(0x346)][_0x5df79b(0x194)](_0x7108ad),_0x3e976c[_0x5df79b(0x2c8)](_0x5df79b(0x338),_0x47ebda),_0x576acc['removeEventListener'](_0x5df79b(0x338),_0x39f20b),_0x1c9313(![]);};_0x3e976c[_0x5d6843(0x1cb)](_0x5d6843(0x338),_0x47ebda),_0x576acc[_0x5d6843(0x1cb)]('click',_0x39f20b);});}[_0x50a5e2(0x2c0)](){const _0x277b61=_0x50a5e2;var _0x2086c6=this;console[_0x277b61(0x1fc)](_0x277b61(0x28a)+this['currentLevel']);var _0x39a2bc=document[_0x277b61(0x38d)](_0x277b61(0x311)),_0x1212c1=document[_0x277b61(0x1b8)](_0x277b61(0x292));_0x39a2bc[_0x277b61(0x1e5)]['display']='block',_0x1212c1['style'][_0x277b61(0x1f2)]=_0x277b61(0x267),this['setBackground'](),this[_0x277b61(0x389)][_0x277b61(0x367)]['play'](),log(_0x277b61(0x2fb)+this[_0x277b61(0x242)]+_0x277b61(0x30f)),this['player2']=this[_0x277b61(0x1b7)](opponentsConfig[this[_0x277b61(0x242)]-0x1]),console[_0x277b61(0x1fc)](_0x277b61(0x2fa)+this[_0x277b61(0x242)]+':\x20'+this[_0x277b61(0x202)][_0x277b61(0x2e2)]+_0x277b61(0x332)+(this['currentLevel']-0x1)+'])'),this[_0x277b61(0x1a5)][_0x277b61(0x2c9)]=this[_0x277b61(0x1a5)][_0x277b61(0x350)],this[_0x277b61(0x2f2)]=this[_0x277b61(0x1a5)][_0x277b61(0x37d)]>this[_0x277b61(0x202)]['speed']?this[_0x277b61(0x1a5)]:this[_0x277b61(0x202)][_0x277b61(0x37d)]>this[_0x277b61(0x1a5)][_0x277b61(0x37d)]?this['player2']:this['player1'][_0x277b61(0x296)]>=this[_0x277b61(0x202)][_0x277b61(0x296)]?this['player1']:this['player2'],this[_0x277b61(0x300)]=_0x277b61(0x214),this['gameOver']=![],this['roundStats']=[],p1Image['classList'][_0x277b61(0x362)]('winner',_0x277b61(0x366)),p2Image[_0x277b61(0x34c)][_0x277b61(0x362)](_0x277b61(0x233),'loser'),this[_0x277b61(0x229)](),this[_0x277b61(0x1d7)](),p1Image[_0x277b61(0x1e5)]['transform']=this[_0x277b61(0x1a5)][_0x277b61(0x341)]==='Left'?_0x277b61(0x228):'none',p2Image[_0x277b61(0x1e5)][_0x277b61(0x20a)]=this['player2'][_0x277b61(0x341)]===_0x277b61(0x398)?_0x277b61(0x228):_0x277b61(0x2b3),this[_0x277b61(0x2fd)](this['player1']),this[_0x277b61(0x2fd)](this[_0x277b61(0x202)]),battleLog[_0x277b61(0x196)]='',gameOver['textContent']='',this[_0x277b61(0x1a5)]['size']!==_0x277b61(0x316)&&log(this[_0x277b61(0x1a5)][_0x277b61(0x2e2)]+'\x27s\x20'+this[_0x277b61(0x1a5)][_0x277b61(0x29c)]+_0x277b61(0x1d3)+(this[_0x277b61(0x1a5)]['size']===_0x277b61(0x3a3)?'boosts\x20health\x20to\x20'+this[_0x277b61(0x1a5)][_0x277b61(0x350)]+'\x20but\x20dulls\x20tactics\x20to\x20'+this[_0x277b61(0x1a5)][_0x277b61(0x2ba)]:_0x277b61(0x315)+this[_0x277b61(0x1a5)]['maxHealth']+_0x277b61(0x2d7)+this[_0x277b61(0x1a5)]['tactics'])+'!'),this[_0x277b61(0x202)][_0x277b61(0x29c)]!==_0x277b61(0x316)&&log(this[_0x277b61(0x202)]['name']+'\x27s\x20'+this['player2'][_0x277b61(0x29c)]+_0x277b61(0x1d3)+(this['player2'][_0x277b61(0x29c)]==='Large'?_0x277b61(0x35b)+this[_0x277b61(0x202)][_0x277b61(0x350)]+_0x277b61(0x33c)+this[_0x277b61(0x202)][_0x277b61(0x2ba)]:_0x277b61(0x315)+this[_0x277b61(0x202)][_0x277b61(0x350)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this['player2']['tactics'])+'!'),log(this[_0x277b61(0x1a5)]['name']+_0x277b61(0x232)+this[_0x277b61(0x1a5)]['health']+'/'+this[_0x277b61(0x1a5)][_0x277b61(0x350)]+_0x277b61(0x1ff)),log(this[_0x277b61(0x2f2)][_0x277b61(0x2e2)]+_0x277b61(0x2b9)),this[_0x277b61(0x22a)](),this[_0x277b61(0x300)]=this[_0x277b61(0x2f2)]===this[_0x277b61(0x1a5)]?_0x277b61(0x1c6):'aiTurn',turnIndicator[_0x277b61(0x226)]='Level\x20'+this['currentLevel']+_0x277b61(0x1aa)+(this[_0x277b61(0x2f2)]===this[_0x277b61(0x1a5)]?_0x277b61(0x304):'Opponent')+'\x27s\x20Turn',this['playerCharacters'][_0x277b61(0x33f)]>0x1&&(document[_0x277b61(0x1b8)]('change-character')[_0x277b61(0x1e5)]['display']='inline-block'),this['currentTurn']===this['player2']&&setTimeout(function(){const _0x4ffec6=_0x277b61;_0x2086c6[_0x4ffec6(0x234)]();},0x3e8);}[_0x50a5e2(0x229)](){const _0x1e4839=_0x50a5e2;p1Name['textContent']=this[_0x1e4839(0x1a5)]['isNFT']||this[_0x1e4839(0x247)]===_0x1e4839(0x217)?this[_0x1e4839(0x1a5)][_0x1e4839(0x2e2)]:_0x1e4839(0x23b),p1Type[_0x1e4839(0x226)]=this[_0x1e4839(0x1a5)][_0x1e4839(0x25e)],p1Strength['textContent']=this[_0x1e4839(0x1a5)][_0x1e4839(0x296)],p1Speed[_0x1e4839(0x226)]=this[_0x1e4839(0x1a5)][_0x1e4839(0x37d)],p1Tactics[_0x1e4839(0x226)]=this[_0x1e4839(0x1a5)][_0x1e4839(0x2ba)],p1Size[_0x1e4839(0x226)]=this[_0x1e4839(0x1a5)]['size'],p1Powerup[_0x1e4839(0x226)]=this['player1'][_0x1e4839(0x1d6)],p1Image[_0x1e4839(0x2fc)]=this[_0x1e4839(0x1a5)][_0x1e4839(0x197)],p1Image[_0x1e4839(0x1e5)]['transform']=this[_0x1e4839(0x1a5)][_0x1e4839(0x341)]===_0x1e4839(0x36e)?_0x1e4839(0x228):'none',p1Image['onload']=function(){const _0x52ec6c=_0x1e4839;p1Image[_0x52ec6c(0x1e5)]['display']=_0x52ec6c(0x240);},p1Hp[_0x1e4839(0x226)]=this[_0x1e4839(0x1a5)][_0x1e4839(0x2c9)]+'/'+this['player1'][_0x1e4839(0x350)];}['updateOpponentDisplay'](){const _0x20866e=_0x50a5e2;p2Name['textContent']=this[_0x20866e(0x247)]===_0x20866e(0x217)?this[_0x20866e(0x202)]['name']:'AI\x20Opponent',p2Type[_0x20866e(0x226)]=this['player2'][_0x20866e(0x25e)],p2Strength['textContent']=this[_0x20866e(0x202)][_0x20866e(0x296)],p2Speed[_0x20866e(0x226)]=this[_0x20866e(0x202)][_0x20866e(0x37d)],p2Tactics['textContent']=this[_0x20866e(0x202)][_0x20866e(0x2ba)],p2Size[_0x20866e(0x226)]=this[_0x20866e(0x202)][_0x20866e(0x29c)],p2Powerup[_0x20866e(0x226)]=this['player2'][_0x20866e(0x1d6)],p2Image[_0x20866e(0x2fc)]=this[_0x20866e(0x202)][_0x20866e(0x197)],p2Image[_0x20866e(0x1e5)][_0x20866e(0x20a)]=this[_0x20866e(0x202)][_0x20866e(0x341)]===_0x20866e(0x398)?'scaleX(-1)':_0x20866e(0x2b3),p2Image[_0x20866e(0x35d)]=function(){const _0x1698b2=_0x20866e;p2Image['style']['display']=_0x1698b2(0x240);},p2Hp['textContent']=this['player2']['health']+'/'+this[_0x20866e(0x202)][_0x20866e(0x350)];}[_0x50a5e2(0x22a)](){const _0x397515=_0x50a5e2;this[_0x397515(0x39c)]=[];for(let _0x4e2b77=0x0;_0x4e2b77<this[_0x397515(0x36b)];_0x4e2b77++){this['board'][_0x4e2b77]=[];for(let _0x5aa723=0x0;_0x5aa723<this[_0x397515(0x288)];_0x5aa723++){let _0x4a4011;do{_0x4a4011=this[_0x397515(0x249)]();}while(_0x5aa723>=0x2&&this['board'][_0x4e2b77][_0x5aa723-0x1]?.[_0x397515(0x25e)]===_0x4a4011[_0x397515(0x25e)]&&this[_0x397515(0x39c)][_0x4e2b77][_0x5aa723-0x2]?.[_0x397515(0x25e)]===_0x4a4011['type']||_0x4e2b77>=0x2&&this[_0x397515(0x39c)][_0x4e2b77-0x1]?.[_0x5aa723]?.[_0x397515(0x25e)]===_0x4a4011[_0x397515(0x25e)]&&this['board'][_0x4e2b77-0x2]?.[_0x5aa723]?.[_0x397515(0x25e)]===_0x4a4011[_0x397515(0x25e)]);this['board'][_0x4e2b77][_0x5aa723]=_0x4a4011;}}this[_0x397515(0x31d)]();}[_0x50a5e2(0x249)](){const _0x251636=_0x50a5e2;return{'type':randomChoice(this[_0x251636(0x32a)]),'element':null};}['renderBoard'](){const _0x21c23c=_0x50a5e2;this[_0x21c23c(0x273)]();const _0x479bd3=document[_0x21c23c(0x1b8)]('game-board');_0x479bd3[_0x21c23c(0x196)]='';for(let _0x7498ec=0x0;_0x7498ec<this['height'];_0x7498ec++){for(let _0x3b392f=0x0;_0x3b392f<this['width'];_0x3b392f++){const _0x414a49=this['board'][_0x7498ec][_0x3b392f];if(_0x414a49['type']===null)continue;const _0x3b1188=document[_0x21c23c(0x36a)](_0x21c23c(0x36f));_0x3b1188[_0x21c23c(0x28d)]=_0x21c23c(0x2ce)+_0x414a49['type'];if(this[_0x21c23c(0x2e3)])_0x3b1188[_0x21c23c(0x34c)][_0x21c23c(0x22e)](_0x21c23c(0x331));const _0x2b3eb6=document[_0x21c23c(0x36a)](_0x21c23c(0x1d5));_0x2b3eb6['src']=_0x21c23c(0x1fb)+_0x414a49[_0x21c23c(0x25e)]+_0x21c23c(0x308),_0x2b3eb6[_0x21c23c(0x2ae)]=_0x414a49['type'],_0x3b1188['appendChild'](_0x2b3eb6),_0x3b1188[_0x21c23c(0x261)]['x']=_0x3b392f,_0x3b1188[_0x21c23c(0x261)]['y']=_0x7498ec,_0x479bd3[_0x21c23c(0x1de)](_0x3b1188),_0x414a49[_0x21c23c(0x2df)]=_0x3b1188,(!this[_0x21c23c(0x199)]||this[_0x21c23c(0x236)]&&(this[_0x21c23c(0x236)]['x']!==_0x3b392f||this[_0x21c23c(0x236)]['y']!==_0x7498ec))&&(_0x3b1188[_0x21c23c(0x1e5)][_0x21c23c(0x20a)]=_0x21c23c(0x1e7));}}document['getElementById']('game-over-container')[_0x21c23c(0x1e5)][_0x21c23c(0x2bd)]=this[_0x21c23c(0x2e3)]?'block':_0x21c23c(0x2b3);}[_0x50a5e2(0x2e8)](){const _0x555efb=_0x50a5e2,_0x18b4eb=document[_0x555efb(0x1b8)](_0x555efb(0x292));this['isTouchDevice']?(_0x18b4eb[_0x555efb(0x1cb)](_0x555efb(0x1cf),_0x63212d=>this[_0x555efb(0x200)](_0x63212d)),_0x18b4eb[_0x555efb(0x1cb)](_0x555efb(0x297),_0x1c3239=>this[_0x555efb(0x1d1)](_0x1c3239)),_0x18b4eb['addEventListener'](_0x555efb(0x1be),_0x54ea86=>this[_0x555efb(0x1a4)](_0x54ea86))):(_0x18b4eb[_0x555efb(0x1cb)]('mousedown',_0x39dfd2=>this[_0x555efb(0x318)](_0x39dfd2)),_0x18b4eb[_0x555efb(0x1cb)]('mousemove',_0x1d1812=>this[_0x555efb(0x21f)](_0x1d1812)),_0x18b4eb[_0x555efb(0x1cb)]('mouseup',_0x198f7f=>this[_0x555efb(0x18f)](_0x198f7f)));document[_0x555efb(0x1b8)](_0x555efb(0x351))[_0x555efb(0x1cb)](_0x555efb(0x338),()=>this['handleGameOverButton']()),document[_0x555efb(0x1b8)](_0x555efb(0x312))['addEventListener'](_0x555efb(0x338),()=>{const _0xda55ee=_0x555efb;this[_0xda55ee(0x2c0)]();});const _0x37173c=document['getElementById'](_0x555efb(0x39a)),_0x2b1f4b=document[_0x555efb(0x1b8)](_0x555efb(0x343));_0x37173c[_0x555efb(0x1cb)](_0x555efb(0x338),()=>{const _0x8ebe28=_0x555efb;console[_0x8ebe28(0x1fc)](_0x8ebe28(0x302)),this['showCharacterSelect'](![]);}),_0x2b1f4b[_0x555efb(0x1cb)](_0x555efb(0x338),()=>{const _0x164ef3=_0x555efb;console[_0x164ef3(0x1fc)](_0x164ef3(0x1f4)),this['showCharacterSelect'](![]);}),document[_0x555efb(0x1b8)](_0x555efb(0x34e))[_0x555efb(0x1cb)](_0x555efb(0x338),()=>this[_0x555efb(0x29a)](this['player1'],_0x2b1f4b,![])),document[_0x555efb(0x1b8)](_0x555efb(0x371))['addEventListener'](_0x555efb(0x338),()=>this[_0x555efb(0x29a)](this['player2'],p2Image,!![]));}[_0x50a5e2(0x27c)](){const _0x1ee46f=_0x50a5e2;console['log']('handleGameOverButton\x20started:\x20currentLevel='+this[_0x1ee46f(0x242)]+_0x1ee46f(0x23f)+this[_0x1ee46f(0x202)][_0x1ee46f(0x2c9)]),this['player2'][_0x1ee46f(0x2c9)]<=0x0&&this[_0x1ee46f(0x242)]>opponentsConfig[_0x1ee46f(0x33f)]&&(this['currentLevel']=0x1,console[_0x1ee46f(0x1fc)](_0x1ee46f(0x2a3)+this[_0x1ee46f(0x242)])),this[_0x1ee46f(0x2c0)](),console[_0x1ee46f(0x1fc)]('handleGameOverButton\x20completed:\x20currentLevel='+this['currentLevel']);}[_0x50a5e2(0x318)](_0x1f9705){const _0x12610f=_0x50a5e2;if(this[_0x12610f(0x2e3)]||this[_0x12610f(0x300)]!==_0x12610f(0x1c6)||this[_0x12610f(0x2f2)]!==this[_0x12610f(0x1a5)])return;_0x1f9705['preventDefault']();const _0x1506ee=this['getTileFromEvent'](_0x1f9705);if(!_0x1506ee||!_0x1506ee['element'])return;this[_0x12610f(0x199)]=!![],this[_0x12610f(0x236)]={'x':_0x1506ee['x'],'y':_0x1506ee['y']},_0x1506ee[_0x12610f(0x2df)][_0x12610f(0x34c)][_0x12610f(0x22e)](_0x12610f(0x34f));const _0x127522=document['getElementById'](_0x12610f(0x292))[_0x12610f(0x1fa)]();this[_0x12610f(0x327)]=_0x1f9705['clientX']-(_0x127522[_0x12610f(0x1c7)]+this[_0x12610f(0x236)]['x']*this[_0x12610f(0x19c)]),this[_0x12610f(0x364)]=_0x1f9705[_0x12610f(0x25f)]-(_0x127522[_0x12610f(0x195)]+this[_0x12610f(0x236)]['y']*this[_0x12610f(0x19c)]);}[_0x50a5e2(0x21f)](_0x35619c){const _0x372ebb=_0x50a5e2;if(!this['isDragging']||!this[_0x372ebb(0x236)]||this[_0x372ebb(0x2e3)]||this[_0x372ebb(0x300)]!=='playerTurn')return;_0x35619c[_0x372ebb(0x372)]();const _0xa68d27=document['getElementById'](_0x372ebb(0x292))[_0x372ebb(0x1fa)](),_0x5fec99=_0x35619c['clientX']-_0xa68d27['left']-this['offsetX'],_0x23d129=_0x35619c['clientY']-_0xa68d27[_0x372ebb(0x195)]-this[_0x372ebb(0x364)],_0x7ef6a5=this[_0x372ebb(0x39c)][this[_0x372ebb(0x236)]['y']][this[_0x372ebb(0x236)]['x']][_0x372ebb(0x2df)];_0x7ef6a5['style'][_0x372ebb(0x22c)]='';if(!this['dragDirection']){const _0x525d3e=Math[_0x372ebb(0x382)](_0x5fec99-this[_0x372ebb(0x236)]['x']*this[_0x372ebb(0x19c)]),_0x3e21b6=Math['abs'](_0x23d129-this[_0x372ebb(0x236)]['y']*this['tileSizeWithGap']);if(_0x525d3e>_0x3e21b6&&_0x525d3e>0x5)this[_0x372ebb(0x377)]=_0x372ebb(0x253);else{if(_0x3e21b6>_0x525d3e&&_0x3e21b6>0x5)this['dragDirection']='column';}}if(!this['dragDirection'])return;if(this[_0x372ebb(0x377)]===_0x372ebb(0x253)){const _0x2472da=Math[_0x372ebb(0x294)](0x0,Math[_0x372ebb(0x376)]((this[_0x372ebb(0x288)]-0x1)*this[_0x372ebb(0x19c)],_0x5fec99));_0x7ef6a5['style'][_0x372ebb(0x20a)]=_0x372ebb(0x1f3)+(_0x2472da-this[_0x372ebb(0x236)]['x']*this[_0x372ebb(0x19c)])+_0x372ebb(0x37c),this['targetTile']={'x':Math[_0x372ebb(0x2bf)](_0x2472da/this[_0x372ebb(0x19c)]),'y':this['selectedTile']['y']};}else{if(this['dragDirection']===_0x372ebb(0x32f)){const _0x180ad1=Math['max'](0x0,Math[_0x372ebb(0x376)]((this[_0x372ebb(0x36b)]-0x1)*this[_0x372ebb(0x19c)],_0x23d129));_0x7ef6a5[_0x372ebb(0x1e5)][_0x372ebb(0x20a)]=_0x372ebb(0x20e)+(_0x180ad1-this[_0x372ebb(0x236)]['y']*this[_0x372ebb(0x19c)])+_0x372ebb(0x1ea),this[_0x372ebb(0x2cb)]={'x':this['selectedTile']['x'],'y':Math[_0x372ebb(0x2bf)](_0x180ad1/this[_0x372ebb(0x19c)])};}}}['handleMouseUp'](_0x57fd11){const _0xfe59fb=_0x50a5e2;if(!this[_0xfe59fb(0x199)]||!this['selectedTile']||!this[_0xfe59fb(0x2cb)]||this['gameOver']||this['gameState']!==_0xfe59fb(0x1c6)){if(this[_0xfe59fb(0x236)]){const _0x2494b6=this[_0xfe59fb(0x39c)][this[_0xfe59fb(0x236)]['y']][this[_0xfe59fb(0x236)]['x']];if(_0x2494b6[_0xfe59fb(0x2df)])_0x2494b6[_0xfe59fb(0x2df)][_0xfe59fb(0x34c)][_0xfe59fb(0x362)](_0xfe59fb(0x34f));}this['isDragging']=![],this[_0xfe59fb(0x236)]=null,this[_0xfe59fb(0x2cb)]=null,this['dragDirection']=null,this[_0xfe59fb(0x31d)]();return;}const _0x39e20d=this[_0xfe59fb(0x39c)][this['selectedTile']['y']][this[_0xfe59fb(0x236)]['x']];if(_0x39e20d[_0xfe59fb(0x2df)])_0x39e20d['element'][_0xfe59fb(0x34c)][_0xfe59fb(0x362)](_0xfe59fb(0x34f));this['slideTiles'](this[_0xfe59fb(0x236)]['x'],this[_0xfe59fb(0x236)]['y'],this['targetTile']['x'],this['targetTile']['y']),this[_0xfe59fb(0x199)]=![],this[_0xfe59fb(0x236)]=null,this['targetTile']=null,this[_0xfe59fb(0x377)]=null;}[_0x50a5e2(0x200)](_0x50cb44){const _0x55be87=_0x50a5e2;if(this[_0x55be87(0x2e3)]||this[_0x55be87(0x300)]!==_0x55be87(0x1c6)||this[_0x55be87(0x2f2)]!==this[_0x55be87(0x1a5)])return;_0x50cb44[_0x55be87(0x372)]();const _0x2d2e8a=this[_0x55be87(0x224)](_0x50cb44[_0x55be87(0x20f)][0x0]);if(!_0x2d2e8a||!_0x2d2e8a[_0x55be87(0x2df)])return;this['isDragging']=!![],this[_0x55be87(0x236)]={'x':_0x2d2e8a['x'],'y':_0x2d2e8a['y']},_0x2d2e8a['element'][_0x55be87(0x34c)][_0x55be87(0x22e)](_0x55be87(0x34f));const _0x3fce21=document[_0x55be87(0x1b8)](_0x55be87(0x292))['getBoundingClientRect']();this[_0x55be87(0x327)]=_0x50cb44[_0x55be87(0x20f)][0x0][_0x55be87(0x307)]-(_0x3fce21[_0x55be87(0x1c7)]+this[_0x55be87(0x236)]['x']*this[_0x55be87(0x19c)]),this[_0x55be87(0x364)]=_0x50cb44[_0x55be87(0x20f)][0x0][_0x55be87(0x25f)]-(_0x3fce21[_0x55be87(0x195)]+this[_0x55be87(0x236)]['y']*this['tileSizeWithGap']);}['handleTouchMove'](_0x216f72){const _0x4ece0f=_0x50a5e2;if(!this[_0x4ece0f(0x199)]||!this[_0x4ece0f(0x236)]||this[_0x4ece0f(0x2e3)]||this[_0x4ece0f(0x300)]!==_0x4ece0f(0x1c6))return;_0x216f72[_0x4ece0f(0x372)]();const _0x3783ed=document[_0x4ece0f(0x1b8)](_0x4ece0f(0x292))[_0x4ece0f(0x1fa)](),_0x40a2cf=_0x216f72['touches'][0x0]['clientX']-_0x3783ed[_0x4ece0f(0x1c7)]-this[_0x4ece0f(0x327)],_0x10bd31=_0x216f72['touches'][0x0]['clientY']-_0x3783ed['top']-this['offsetY'],_0x37219c=this[_0x4ece0f(0x39c)][this[_0x4ece0f(0x236)]['y']][this[_0x4ece0f(0x236)]['x']][_0x4ece0f(0x2df)];requestAnimationFrame(()=>{const _0x819ffb=_0x4ece0f;if(!this[_0x819ffb(0x377)]){const _0x409f98=Math[_0x819ffb(0x382)](_0x40a2cf-this[_0x819ffb(0x236)]['x']*this[_0x819ffb(0x19c)]),_0x4a2a34=Math['abs'](_0x10bd31-this['selectedTile']['y']*this['tileSizeWithGap']);if(_0x409f98>_0x4a2a34&&_0x409f98>0x7)this[_0x819ffb(0x377)]='row';else{if(_0x4a2a34>_0x409f98&&_0x4a2a34>0x7)this[_0x819ffb(0x377)]=_0x819ffb(0x32f);}}_0x37219c[_0x819ffb(0x1e5)][_0x819ffb(0x22c)]='';if(this[_0x819ffb(0x377)]===_0x819ffb(0x253)){const _0x1b78e9=Math[_0x819ffb(0x294)](0x0,Math[_0x819ffb(0x376)]((this['width']-0x1)*this[_0x819ffb(0x19c)],_0x40a2cf));_0x37219c[_0x819ffb(0x1e5)][_0x819ffb(0x20a)]=_0x819ffb(0x1f3)+(_0x1b78e9-this[_0x819ffb(0x236)]['x']*this[_0x819ffb(0x19c)])+'px,\x200)\x20scale(1.05)',this[_0x819ffb(0x2cb)]={'x':Math[_0x819ffb(0x2bf)](_0x1b78e9/this['tileSizeWithGap']),'y':this[_0x819ffb(0x236)]['y']};}else{if(this[_0x819ffb(0x377)]==='column'){const _0x1ee521=Math['max'](0x0,Math[_0x819ffb(0x376)]((this['height']-0x1)*this[_0x819ffb(0x19c)],_0x10bd31));_0x37219c[_0x819ffb(0x1e5)][_0x819ffb(0x20a)]=_0x819ffb(0x20e)+(_0x1ee521-this['selectedTile']['y']*this['tileSizeWithGap'])+_0x819ffb(0x1ea),this[_0x819ffb(0x2cb)]={'x':this[_0x819ffb(0x236)]['x'],'y':Math[_0x819ffb(0x2bf)](_0x1ee521/this['tileSizeWithGap'])};}}});}[_0x50a5e2(0x1a4)](_0x1a74e3){const _0x567930=_0x50a5e2;if(!this[_0x567930(0x199)]||!this[_0x567930(0x236)]||!this[_0x567930(0x2cb)]||this[_0x567930(0x2e3)]||this['gameState']!=='playerTurn'){if(this[_0x567930(0x236)]){const _0x475a53=this[_0x567930(0x39c)][this['selectedTile']['y']][this['selectedTile']['x']];if(_0x475a53[_0x567930(0x2df)])_0x475a53['element'][_0x567930(0x34c)]['remove'](_0x567930(0x34f));}this[_0x567930(0x199)]=![],this[_0x567930(0x236)]=null,this['targetTile']=null,this[_0x567930(0x377)]=null,this[_0x567930(0x31d)]();return;}const _0x3d0b00=this[_0x567930(0x39c)][this[_0x567930(0x236)]['y']][this[_0x567930(0x236)]['x']];if(_0x3d0b00[_0x567930(0x2df)])_0x3d0b00['element'][_0x567930(0x34c)][_0x567930(0x362)](_0x567930(0x34f));this[_0x567930(0x380)](this[_0x567930(0x236)]['x'],this[_0x567930(0x236)]['y'],this[_0x567930(0x2cb)]['x'],this[_0x567930(0x2cb)]['y']),this[_0x567930(0x199)]=![],this[_0x567930(0x236)]=null,this[_0x567930(0x2cb)]=null,this['dragDirection']=null;}[_0x50a5e2(0x224)](_0x568b15){const _0x40521b=_0x50a5e2,_0x52aa1a=document[_0x40521b(0x1b8)]('game-board')[_0x40521b(0x1fa)](),_0xbca2fc=Math[_0x40521b(0x20d)]((_0x568b15['clientX']-_0x52aa1a[_0x40521b(0x1c7)])/this[_0x40521b(0x19c)]),_0x109980=Math[_0x40521b(0x20d)]((_0x568b15[_0x40521b(0x25f)]-_0x52aa1a[_0x40521b(0x195)])/this[_0x40521b(0x19c)]);if(_0xbca2fc>=0x0&&_0xbca2fc<this[_0x40521b(0x288)]&&_0x109980>=0x0&&_0x109980<this[_0x40521b(0x36b)])return{'x':_0xbca2fc,'y':_0x109980,'element':this[_0x40521b(0x39c)][_0x109980][_0xbca2fc][_0x40521b(0x2df)]};return null;}[_0x50a5e2(0x380)](_0x55339e,_0x50c153,_0x24f745,_0xec8c9){const _0x122698=_0x50a5e2,_0x14aef0=this['tileSizeWithGap'];let _0x111d4b;const _0x29c606=[],_0x1bbfc0=[];if(_0x50c153===_0xec8c9){_0x111d4b=_0x55339e<_0x24f745?0x1:-0x1;const _0x12a8ab=Math['min'](_0x55339e,_0x24f745),_0x2cfe76=Math['max'](_0x55339e,_0x24f745);for(let _0x160153=_0x12a8ab;_0x160153<=_0x2cfe76;_0x160153++){_0x29c606[_0x122698(0x21d)]({...this[_0x122698(0x39c)][_0x50c153][_0x160153]}),_0x1bbfc0[_0x122698(0x21d)](this[_0x122698(0x39c)][_0x50c153][_0x160153][_0x122698(0x2df)]);}}else{if(_0x55339e===_0x24f745){_0x111d4b=_0x50c153<_0xec8c9?0x1:-0x1;const _0x5556a7=Math[_0x122698(0x376)](_0x50c153,_0xec8c9),_0x1631f4=Math[_0x122698(0x294)](_0x50c153,_0xec8c9);for(let _0x22109c=_0x5556a7;_0x22109c<=_0x1631f4;_0x22109c++){_0x29c606[_0x122698(0x21d)]({...this['board'][_0x22109c][_0x55339e]}),_0x1bbfc0[_0x122698(0x21d)](this['board'][_0x22109c][_0x55339e][_0x122698(0x2df)]);}}}const _0x34ffb4=this['board'][_0x50c153][_0x55339e][_0x122698(0x2df)],_0x267d4e=(_0x24f745-_0x55339e)*_0x14aef0,_0x4e8626=(_0xec8c9-_0x50c153)*_0x14aef0;_0x34ffb4[_0x122698(0x1e5)][_0x122698(0x22c)]=_0x122698(0x19e),_0x34ffb4['style']['transform']=_0x122698(0x1f3)+_0x267d4e+_0x122698(0x1e4)+_0x4e8626+_0x122698(0x1f8);let _0x333f46=0x0;if(_0x50c153===_0xec8c9)for(let _0x37df69=Math[_0x122698(0x376)](_0x55339e,_0x24f745);_0x37df69<=Math['max'](_0x55339e,_0x24f745);_0x37df69++){if(_0x37df69===_0x55339e)continue;const _0x1f70c5=_0x111d4b*-_0x14aef0*(_0x37df69-_0x55339e)/Math[_0x122698(0x382)](_0x24f745-_0x55339e);_0x1bbfc0[_0x333f46][_0x122698(0x1e5)][_0x122698(0x22c)]='transform\x200.2s\x20ease',_0x1bbfc0[_0x333f46][_0x122698(0x1e5)][_0x122698(0x20a)]=_0x122698(0x1f3)+_0x1f70c5+'px,\x200)',_0x333f46++;}else for(let _0x15580b=Math[_0x122698(0x376)](_0x50c153,_0xec8c9);_0x15580b<=Math['max'](_0x50c153,_0xec8c9);_0x15580b++){if(_0x15580b===_0x50c153)continue;const _0x25b8c8=_0x111d4b*-_0x14aef0*(_0x15580b-_0x50c153)/Math[_0x122698(0x382)](_0xec8c9-_0x50c153);_0x1bbfc0[_0x333f46]['style'][_0x122698(0x22c)]=_0x122698(0x19e),_0x1bbfc0[_0x333f46][_0x122698(0x1e5)][_0x122698(0x20a)]=_0x122698(0x20e)+_0x25b8c8+_0x122698(0x1f8),_0x333f46++;}setTimeout(()=>{const _0x3e4e85=_0x122698;if(_0x50c153===_0xec8c9){const _0x3434bc=this[_0x3e4e85(0x39c)][_0x50c153],_0xbef44b=[..._0x3434bc];if(_0x55339e<_0x24f745){for(let _0x1283fe=_0x55339e;_0x1283fe<_0x24f745;_0x1283fe++)_0x3434bc[_0x1283fe]=_0xbef44b[_0x1283fe+0x1];}else{for(let _0x3bc07c=_0x55339e;_0x3bc07c>_0x24f745;_0x3bc07c--)_0x3434bc[_0x3bc07c]=_0xbef44b[_0x3bc07c-0x1];}_0x3434bc[_0x24f745]=_0xbef44b[_0x55339e];}else{const _0x221212=[];for(let _0x48ccb0=0x0;_0x48ccb0<this['height'];_0x48ccb0++)_0x221212[_0x48ccb0]={...this['board'][_0x48ccb0][_0x55339e]};if(_0x50c153<_0xec8c9){for(let _0x5a827b=_0x50c153;_0x5a827b<_0xec8c9;_0x5a827b++)this[_0x3e4e85(0x39c)][_0x5a827b][_0x55339e]=_0x221212[_0x5a827b+0x1];}else{for(let _0x3f4ee7=_0x50c153;_0x3f4ee7>_0xec8c9;_0x3f4ee7--)this[_0x3e4e85(0x39c)][_0x3f4ee7][_0x55339e]=_0x221212[_0x3f4ee7-0x1];}this['board'][_0xec8c9][_0x24f745]=_0x221212[_0x50c153];}this[_0x3e4e85(0x31d)]();const _0x1d788b=this['resolveMatches'](_0x24f745,_0xec8c9);_0x1d788b?this[_0x3e4e85(0x300)]=_0x3e4e85(0x239):(log(_0x3e4e85(0x2f3)),this['sounds']['badMove']['play'](),_0x34ffb4[_0x3e4e85(0x1e5)]['transition']=_0x3e4e85(0x19e),_0x34ffb4[_0x3e4e85(0x1e5)][_0x3e4e85(0x20a)]=_0x3e4e85(0x1e7),_0x1bbfc0[_0x3e4e85(0x277)](_0x3fb5ee=>{const _0x132013=_0x3e4e85;_0x3fb5ee['style']['transition']=_0x132013(0x19e),_0x3fb5ee[_0x132013(0x1e5)][_0x132013(0x20a)]=_0x132013(0x1e7);}),setTimeout(()=>{const _0xeb1a46=_0x3e4e85;if(_0x50c153===_0xec8c9){const _0x4e56e3=Math[_0xeb1a46(0x376)](_0x55339e,_0x24f745);for(let _0x2b4345=0x0;_0x2b4345<_0x29c606[_0xeb1a46(0x33f)];_0x2b4345++){this[_0xeb1a46(0x39c)][_0x50c153][_0x4e56e3+_0x2b4345]={..._0x29c606[_0x2b4345],'element':_0x1bbfc0[_0x2b4345]};}}else{const _0x455c91=Math[_0xeb1a46(0x376)](_0x50c153,_0xec8c9);for(let _0x4dfdad=0x0;_0x4dfdad<_0x29c606[_0xeb1a46(0x33f)];_0x4dfdad++){this[_0xeb1a46(0x39c)][_0x455c91+_0x4dfdad][_0x55339e]={..._0x29c606[_0x4dfdad],'element':_0x1bbfc0[_0x4dfdad]};}}this[_0xeb1a46(0x31d)](),this[_0xeb1a46(0x300)]=_0xeb1a46(0x1c6);},0xc8));},0xc8);}[_0x50a5e2(0x219)](_0x502bea=null,_0x2330e2=null){const _0x15b5a8=_0x50a5e2;console[_0x15b5a8(0x1fc)](_0x15b5a8(0x1dd),this[_0x15b5a8(0x2e3)]);if(this[_0x15b5a8(0x2e3)])return console[_0x15b5a8(0x1fc)](_0x15b5a8(0x1a3)),![];const _0x38f159=_0x502bea!==null&&_0x2330e2!==null;console[_0x15b5a8(0x1fc)]('Is\x20initial\x20move:\x20'+_0x38f159);const _0x400252=this['checkMatches']();console['log'](_0x15b5a8(0x1ba)+_0x400252['length']+_0x15b5a8(0x1b1),_0x400252);let _0xdeaf7f=0x1,_0x59c4c3='';if(_0x38f159&&_0x400252[_0x15b5a8(0x33f)]>0x1){const _0x5ce75c=_0x400252[_0x15b5a8(0x2ff)]((_0x2d5003,_0x39cf87)=>_0x2d5003+_0x39cf87[_0x15b5a8(0x2e6)],0x0);console['log'](_0x15b5a8(0x36d)+_0x5ce75c);if(_0x5ce75c>=0x6&&_0x5ce75c<=0x8)_0xdeaf7f=1.2,_0x59c4c3=_0x15b5a8(0x230)+_0x5ce75c+_0x15b5a8(0x2e7),this['sounds'][_0x15b5a8(0x2b0)][_0x15b5a8(0x2dd)]();else _0x5ce75c>=0x9&&(_0xdeaf7f=0x3,_0x59c4c3='Mega\x20Multi-Match!\x20'+_0x5ce75c+_0x15b5a8(0x2b7),this[_0x15b5a8(0x389)][_0x15b5a8(0x2b0)][_0x15b5a8(0x2dd)]());}if(_0x400252[_0x15b5a8(0x33f)]>0x0){const _0x39fe21=new Set();let _0x1f0df8=0x0;const _0x379528=this['currentTurn'],_0x570ac7=this[_0x15b5a8(0x2f2)]===this['player1']?this[_0x15b5a8(0x202)]:this['player1'];try{_0x400252['forEach'](_0x3b982b=>{const _0x2db7de=_0x15b5a8;console['log'](_0x2db7de(0x293),_0x3b982b),_0x3b982b[_0x2db7de(0x283)][_0x2db7de(0x277)](_0x16208f=>_0x39fe21[_0x2db7de(0x22e)](_0x16208f));const _0x25cc0a=this[_0x2db7de(0x2fe)](_0x3b982b,_0x38f159);console[_0x2db7de(0x1fc)]('Damage\x20from\x20match:\x20'+_0x25cc0a);if(this[_0x2db7de(0x2e3)]){console[_0x2db7de(0x1fc)](_0x2db7de(0x2a9));return;}if(_0x25cc0a>0x0)_0x1f0df8+=_0x25cc0a;});if(this[_0x15b5a8(0x2e3)])return console[_0x15b5a8(0x1fc)](_0x15b5a8(0x2b1)),!![];return console[_0x15b5a8(0x1fc)](_0x15b5a8(0x1e1)+_0x1f0df8+',\x20tiles\x20to\x20clear:',[..._0x39fe21]),_0x1f0df8>0x0&&!this[_0x15b5a8(0x2e3)]&&setTimeout(()=>{const _0x430734=_0x15b5a8;if(this[_0x430734(0x2e3)]){console[_0x430734(0x1fc)]('Game\x20over,\x20skipping\x20recoil\x20animation');return;}console[_0x430734(0x1fc)](_0x430734(0x19b),_0x570ac7[_0x430734(0x2e2)]),this[_0x430734(0x1a9)](_0x570ac7,_0x1f0df8);},0x64),setTimeout(()=>{const _0xb72a9f=_0x15b5a8;if(this['gameOver']){console['log'](_0xb72a9f(0x26f));return;}console[_0xb72a9f(0x1fc)](_0xb72a9f(0x192),[..._0x39fe21]),_0x39fe21['forEach'](_0x4c934a=>{const _0x196dc8=_0xb72a9f,[_0x39c5d4,_0x20695b]=_0x4c934a['split'](',')[_0x196dc8(0x1bf)](Number);this[_0x196dc8(0x39c)][_0x20695b][_0x39c5d4]?.[_0x196dc8(0x2df)]?this[_0x196dc8(0x39c)][_0x20695b][_0x39c5d4][_0x196dc8(0x2df)]['classList'][_0x196dc8(0x22e)](_0x196dc8(0x2af)):console['warn'](_0x196dc8(0x33e)+_0x39c5d4+','+_0x20695b+_0x196dc8(0x368));}),setTimeout(()=>{const _0x472ba1=_0xb72a9f;if(this[_0x472ba1(0x2e3)]){console[_0x472ba1(0x1fc)](_0x472ba1(0x337));return;}console[_0x472ba1(0x1fc)]('Clearing\x20matched\x20tiles:',[..._0x39fe21]),_0x39fe21[_0x472ba1(0x277)](_0x3c64c8=>{const _0x28830d=_0x472ba1,[_0x5a6f11,_0x36e877]=_0x3c64c8[_0x28830d(0x279)](',')['map'](Number);this[_0x28830d(0x39c)][_0x36e877][_0x5a6f11]&&(this['board'][_0x36e877][_0x5a6f11][_0x28830d(0x25e)]=null,this['board'][_0x36e877][_0x5a6f11][_0x28830d(0x2df)]=null);}),this['sounds'][_0x472ba1(0x1b5)][_0x472ba1(0x2dd)](),console[_0x472ba1(0x1fc)]('Cascading\x20tiles');if(_0xdeaf7f>0x1&&this[_0x472ba1(0x1d9)][_0x472ba1(0x33f)]>0x0){const _0x2b7058=this['roundStats'][this[_0x472ba1(0x1d9)][_0x472ba1(0x33f)]-0x1],_0x86ffce=_0x2b7058[_0x472ba1(0x1f9)];_0x2b7058[_0x472ba1(0x1f9)]=Math[_0x472ba1(0x2bf)](_0x2b7058[_0x472ba1(0x1f9)]*_0xdeaf7f),_0x59c4c3&&(log(_0x59c4c3),log(_0x472ba1(0x2d2)+_0x86ffce+_0x472ba1(0x2ac)+_0x2b7058['points']+'\x20after\x20multi-match\x20bonus!'));}this['cascadeTiles'](()=>{const _0x5ac8b6=_0x472ba1;if(this[_0x5ac8b6(0x2e3)]){console[_0x5ac8b6(0x1fc)](_0x5ac8b6(0x317));return;}console['log'](_0x5ac8b6(0x1c0)),this[_0x5ac8b6(0x2d3)]();});},0x12c);},0xc8),!![];}catch(_0x4f1e0c){return console[_0x15b5a8(0x241)](_0x15b5a8(0x2b5),_0x4f1e0c),this[_0x15b5a8(0x300)]=this['currentTurn']===this[_0x15b5a8(0x1a5)]?_0x15b5a8(0x1c6):'aiTurn',![];}}return console[_0x15b5a8(0x1fc)](_0x15b5a8(0x1c3)),![];}['checkMatches'](){const _0xea6a3e=_0x50a5e2;console[_0xea6a3e(0x1fc)]('checkMatches\x20started');const _0x13e3e0=[];try{const _0x6d8b50=[];for(let _0x49f174=0x0;_0x49f174<this[_0xea6a3e(0x36b)];_0x49f174++){let _0xa0a1d3=0x0;for(let _0xab7d71=0x0;_0xab7d71<=this[_0xea6a3e(0x288)];_0xab7d71++){const _0x12b09b=_0xab7d71<this[_0xea6a3e(0x288)]?this['board'][_0x49f174][_0xab7d71]?.[_0xea6a3e(0x25e)]:null;if(_0x12b09b!==this[_0xea6a3e(0x39c)][_0x49f174][_0xa0a1d3]?.[_0xea6a3e(0x25e)]||_0xab7d71===this[_0xea6a3e(0x288)]){const _0x366485=_0xab7d71-_0xa0a1d3;if(_0x366485>=0x3){const _0x133883=new Set();for(let _0x22dd15=_0xa0a1d3;_0x22dd15<_0xab7d71;_0x22dd15++){_0x133883[_0xea6a3e(0x22e)](_0x22dd15+','+_0x49f174);}_0x6d8b50[_0xea6a3e(0x21d)]({'type':this[_0xea6a3e(0x39c)][_0x49f174][_0xa0a1d3][_0xea6a3e(0x25e)],'coordinates':_0x133883}),console[_0xea6a3e(0x1fc)]('Horizontal\x20match\x20found\x20at\x20row\x20'+_0x49f174+_0xea6a3e(0x2ea)+_0xa0a1d3+'-'+(_0xab7d71-0x1)+':',[..._0x133883]);}_0xa0a1d3=_0xab7d71;}}}for(let _0x117b05=0x0;_0x117b05<this['width'];_0x117b05++){let _0x55f0c3=0x0;for(let _0x15df83=0x0;_0x15df83<=this[_0xea6a3e(0x36b)];_0x15df83++){const _0x21e471=_0x15df83<this['height']?this[_0xea6a3e(0x39c)][_0x15df83][_0x117b05]?.[_0xea6a3e(0x25e)]:null;if(_0x21e471!==this[_0xea6a3e(0x39c)][_0x55f0c3][_0x117b05]?.[_0xea6a3e(0x25e)]||_0x15df83===this[_0xea6a3e(0x36b)]){const _0x2ac7da=_0x15df83-_0x55f0c3;if(_0x2ac7da>=0x3){const _0x48a0eb=new Set();for(let _0x20f63e=_0x55f0c3;_0x20f63e<_0x15df83;_0x20f63e++){_0x48a0eb['add'](_0x117b05+','+_0x20f63e);}_0x6d8b50[_0xea6a3e(0x21d)]({'type':this[_0xea6a3e(0x39c)][_0x55f0c3][_0x117b05][_0xea6a3e(0x25e)],'coordinates':_0x48a0eb}),console[_0xea6a3e(0x1fc)]('Vertical\x20match\x20found\x20at\x20col\x20'+_0x117b05+_0xea6a3e(0x2f7)+_0x55f0c3+'-'+(_0x15df83-0x1)+':',[..._0x48a0eb]);}_0x55f0c3=_0x15df83;}}}const _0x5310fd=[],_0x55429e=new Set();return _0x6d8b50[_0xea6a3e(0x277)]((_0x389dda,_0x438530)=>{const _0x3961c4=_0xea6a3e;if(_0x55429e['has'](_0x438530))return;const _0x22d5fb={'type':_0x389dda[_0x3961c4(0x25e)],'coordinates':new Set(_0x389dda[_0x3961c4(0x283)])};_0x55429e['add'](_0x438530);for(let _0x4ea4cd=0x0;_0x4ea4cd<_0x6d8b50[_0x3961c4(0x33f)];_0x4ea4cd++){if(_0x55429e['has'](_0x4ea4cd))continue;const _0x36bcc4=_0x6d8b50[_0x4ea4cd];if(_0x36bcc4[_0x3961c4(0x25e)]===_0x22d5fb[_0x3961c4(0x25e)]){const _0x58f645=[..._0x36bcc4[_0x3961c4(0x283)]]['some'](_0x44297f=>_0x22d5fb[_0x3961c4(0x283)]['has'](_0x44297f));_0x58f645&&(_0x36bcc4['coordinates'][_0x3961c4(0x277)](_0x30e642=>_0x22d5fb[_0x3961c4(0x283)][_0x3961c4(0x22e)](_0x30e642)),_0x55429e[_0x3961c4(0x22e)](_0x4ea4cd));}}_0x5310fd[_0x3961c4(0x21d)]({'type':_0x22d5fb[_0x3961c4(0x25e)],'coordinates':_0x22d5fb[_0x3961c4(0x283)],'totalTiles':_0x22d5fb[_0x3961c4(0x283)][_0x3961c4(0x29c)]});}),_0x13e3e0['push'](..._0x5310fd),console[_0xea6a3e(0x1fc)](_0xea6a3e(0x345),_0x13e3e0),_0x13e3e0;}catch(_0x24d5bc){return console[_0xea6a3e(0x241)](_0xea6a3e(0x36c),_0x24d5bc),[];}}[_0x50a5e2(0x2fe)](_0x11aaa5,_0x1a77ce=!![]){const _0x315545=_0x50a5e2;console[_0x315545(0x1fc)](_0x315545(0x319),_0x11aaa5,_0x315545(0x269),_0x1a77ce);const _0x160667=this[_0x315545(0x2f2)],_0x10b2f8=this['currentTurn']===this['player1']?this['player2']:this[_0x315545(0x1a5)],_0x74782e=_0x11aaa5[_0x315545(0x25e)],_0x4d3b30=_0x11aaa5[_0x315545(0x2e6)];let _0x2a9834=0x0,_0x408590=0x0;console[_0x315545(0x1fc)](_0x10b2f8[_0x315545(0x2e2)]+_0x315545(0x2da)+_0x10b2f8[_0x315545(0x2c9)]);_0x4d3b30==0x4&&(this[_0x315545(0x389)][_0x315545(0x2c6)]['play'](),log(_0x160667['name']+'\x20created\x20a\x20match\x20of\x20'+_0x4d3b30+_0x315545(0x1db)));_0x4d3b30>=0x5&&(this[_0x315545(0x389)]['hyperCube'][_0x315545(0x2dd)](),log(_0x160667[_0x315545(0x2e2)]+_0x315545(0x386)+_0x4d3b30+_0x315545(0x1db)));if(_0x74782e==='first-attack'||_0x74782e===_0x315545(0x25b)||_0x74782e===_0x315545(0x35e)||_0x74782e==='last-stand'){_0x2a9834=Math[_0x315545(0x2bf)](_0x160667[_0x315545(0x296)]*(_0x4d3b30===0x3?0x2:_0x4d3b30===0x4?0x3:0x4));let _0x3142b=0x1;if(_0x4d3b30===0x4)_0x3142b=1.5;else _0x4d3b30>=0x5&&(_0x3142b=0x2);_0x2a9834=Math['round'](_0x2a9834*_0x3142b),console[_0x315545(0x1fc)](_0x315545(0x284)+_0x160667[_0x315545(0x296)]*(_0x4d3b30===0x3?0x2:_0x4d3b30===0x4?0x3:0x4)+_0x315545(0x37f)+_0x3142b+_0x315545(0x2de)+_0x2a9834);_0x74782e===_0x315545(0x35e)&&(_0x2a9834=Math[_0x315545(0x2bf)](_0x2a9834*1.2),console['log'](_0x315545(0x204)+_0x2a9834));_0x160667[_0x315545(0x2d4)]&&(_0x2a9834+=_0x160667[_0x315545(0x1eb)]||0xa,_0x160667[_0x315545(0x2d4)]=![],log(_0x160667[_0x315545(0x2e2)]+_0x315545(0x326)),console['log'](_0x315545(0x31a)+_0x2a9834));_0x408590=_0x2a9834;const _0x594011=_0x10b2f8['tactics']*0xa;Math[_0x315545(0x330)]()*0x64<_0x594011&&(_0x2a9834=Math[_0x315545(0x20d)](_0x2a9834/0x2),log(_0x10b2f8['name']+'\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20'+_0x2a9834+_0x315545(0x19d)),console[_0x315545(0x1fc)](_0x315545(0x291)+_0x2a9834));let _0x576603=0x0;_0x10b2f8['lastStandActive']&&(_0x576603=Math['min'](_0x2a9834,0x5),_0x2a9834=Math[_0x315545(0x294)](0x0,_0x2a9834-_0x576603),_0x10b2f8['lastStandActive']=![],console[_0x315545(0x1fc)](_0x315545(0x1d0)+_0x576603+_0x315545(0x344)+_0x2a9834));const _0x33e35b=_0x74782e===_0x315545(0x255)?_0x315545(0x21a):_0x74782e===_0x315545(0x25b)?'Bite':'Shadow\x20Strike';let _0x55a7df;if(_0x576603>0x0)_0x55a7df=_0x160667[_0x315545(0x2e2)]+_0x315545(0x1c5)+_0x33e35b+'\x20on\x20'+_0x10b2f8[_0x315545(0x2e2)]+'\x20for\x20'+_0x408590+_0x315545(0x2c1)+_0x10b2f8[_0x315545(0x2e2)]+_0x315545(0x381)+_0x576603+'\x20damage,\x20resulting\x20in\x20'+_0x2a9834+_0x315545(0x19d);else _0x74782e==='last-stand'?_0x55a7df=_0x160667['name']+'\x20uses\x20Last\x20Stand,\x20dealing\x20'+_0x2a9834+_0x315545(0x32e)+_0x10b2f8['name']+'\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!':_0x55a7df=_0x160667[_0x315545(0x2e2)]+_0x315545(0x1c5)+_0x33e35b+_0x315545(0x278)+_0x10b2f8[_0x315545(0x2e2)]+_0x315545(0x1d2)+_0x2a9834+_0x315545(0x19d);_0x1a77ce?log(_0x55a7df):log(_0x315545(0x397)+_0x55a7df),_0x10b2f8['health']=Math[_0x315545(0x294)](0x0,_0x10b2f8[_0x315545(0x2c9)]-_0x2a9834),console[_0x315545(0x1fc)](_0x10b2f8['name']+_0x315545(0x26e)+_0x10b2f8[_0x315545(0x2c9)]),this['updateHealth'](_0x10b2f8),console[_0x315545(0x1fc)](_0x315545(0x266)),this[_0x315545(0x191)](),!this['gameOver']&&(console[_0x315545(0x1fc)](_0x315545(0x1da)),this[_0x315545(0x29b)](_0x160667,_0x2a9834,_0x74782e));}else _0x74782e===_0x315545(0x29e)&&(this[_0x315545(0x193)](_0x160667,_0x10b2f8,_0x4d3b30),!this['gameOver']&&(console[_0x315545(0x1fc)](_0x315545(0x1b4)),this[_0x315545(0x27a)](_0x160667)));(!this[_0x315545(0x1d9)][this[_0x315545(0x1d9)][_0x315545(0x33f)]-0x1]||this[_0x315545(0x1d9)][this[_0x315545(0x1d9)][_0x315545(0x33f)]-0x1][_0x315545(0x2f9)])&&this[_0x315545(0x1d9)]['push']({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x57bae3=this[_0x315545(0x1d9)][this['roundStats'][_0x315545(0x33f)]-0x1];return _0x57bae3[_0x315545(0x1f9)]+=_0x2a9834,_0x57bae3[_0x315545(0x2aa)]+=0x1,console['log'](_0x315545(0x1ec)+_0x2a9834),_0x2a9834;}['cascadeTiles'](_0x38c8c9){const _0x2d1f08=_0x50a5e2;if(this['gameOver']){console[_0x2d1f08(0x1fc)](_0x2d1f08(0x1e0));return;}const _0x4ea53c=this[_0x2d1f08(0x210)](),_0x24d3f9='falling';for(let _0x584599=0x0;_0x584599<this['width'];_0x584599++){for(let _0x312a39=0x0;_0x312a39<this[_0x2d1f08(0x36b)];_0x312a39++){const _0x4d7ae2=this[_0x2d1f08(0x39c)][_0x312a39][_0x584599];if(_0x4d7ae2[_0x2d1f08(0x2df)]&&_0x4d7ae2['element'][_0x2d1f08(0x1e5)][_0x2d1f08(0x20a)]===_0x2d1f08(0x393)){const _0x511ce7=this['countEmptyBelow'](_0x584599,_0x312a39);_0x511ce7>0x0&&(_0x4d7ae2['element'][_0x2d1f08(0x34c)][_0x2d1f08(0x22e)](_0x24d3f9),_0x4d7ae2[_0x2d1f08(0x2df)]['style'][_0x2d1f08(0x20a)]=_0x2d1f08(0x20e)+_0x511ce7*this['tileSizeWithGap']+_0x2d1f08(0x1f8));}}}this[_0x2d1f08(0x31d)](),_0x4ea53c?setTimeout(()=>{const _0x2e361e=_0x2d1f08;if(this[_0x2e361e(0x2e3)]){console['log']('Game\x20over,\x20skipping\x20cascade\x20resolution');return;}this['sounds'][_0x2e361e(0x2ee)][_0x2e361e(0x2dd)]();const _0x426cc9=this[_0x2e361e(0x219)](),_0x50d90f=document['querySelectorAll']('.'+_0x24d3f9);_0x50d90f['forEach'](_0x229260=>{const _0x4d53aa=_0x2e361e;_0x229260[_0x4d53aa(0x34c)]['remove'](_0x24d3f9),_0x229260[_0x4d53aa(0x1e5)][_0x4d53aa(0x20a)]=_0x4d53aa(0x1e7);}),!_0x426cc9&&_0x38c8c9();},0x12c):_0x38c8c9();}[_0x50a5e2(0x210)](){const _0xcb483b=_0x50a5e2;let _0x85342d=![];for(let _0x3fce1c=0x0;_0x3fce1c<this[_0xcb483b(0x288)];_0x3fce1c++){let _0x24b970=0x0;for(let _0x2e8f2c=this['height']-0x1;_0x2e8f2c>=0x0;_0x2e8f2c--){if(!this['board'][_0x2e8f2c][_0x3fce1c][_0xcb483b(0x25e)])_0x24b970++;else _0x24b970>0x0&&(this[_0xcb483b(0x39c)][_0x2e8f2c+_0x24b970][_0x3fce1c]=this[_0xcb483b(0x39c)][_0x2e8f2c][_0x3fce1c],this[_0xcb483b(0x39c)][_0x2e8f2c][_0x3fce1c]={'type':null,'element':null},_0x85342d=!![]);}for(let _0x411c72=0x0;_0x411c72<_0x24b970;_0x411c72++){this[_0xcb483b(0x39c)][_0x411c72][_0x3fce1c]=this[_0xcb483b(0x249)](),_0x85342d=!![];}}return _0x85342d;}[_0x50a5e2(0x272)](_0x3deb1f,_0x686e56){const _0xbab24b=_0x50a5e2;let _0x404d2=0x0;for(let _0x57f0f0=_0x686e56+0x1;_0x57f0f0<this[_0xbab24b(0x36b)];_0x57f0f0++){if(!this['board'][_0x57f0f0][_0x3deb1f][_0xbab24b(0x25e)])_0x404d2++;else break;}return _0x404d2;}[_0x50a5e2(0x193)](_0x420822,_0x422140,_0x3e1ed6){const _0x407083=_0x50a5e2,_0x3e609a=0x1-_0x422140['tactics']*0.05;let _0x39bc05,_0x41d921,_0x452219,_0x51285f=0x1,_0x50a229='';if(_0x3e1ed6===0x4)_0x51285f=1.5,_0x50a229='\x20(50%\x20bonus\x20for\x20match-4)';else _0x3e1ed6>=0x5&&(_0x51285f=0x2,_0x50a229='\x20(100%\x20bonus\x20for\x20match-5+)');if(_0x420822[_0x407083(0x1d6)]===_0x407083(0x39d))_0x41d921=0xa*_0x51285f,_0x39bc05=Math['floor'](_0x41d921*_0x3e609a),_0x452219=_0x41d921-_0x39bc05,_0x420822[_0x407083(0x2c9)]=Math['min'](_0x420822['maxHealth'],_0x420822[_0x407083(0x2c9)]+_0x39bc05),log(_0x420822['name']+'\x20uses\x20Heal,\x20restoring\x20'+_0x39bc05+'\x20HP'+_0x50a229+(_0x422140[_0x407083(0x2ba)]>0x0?_0x407083(0x2b4)+_0x41d921+',\x20reduced\x20by\x20'+_0x452219+_0x407083(0x342)+_0x422140['name']+'\x27s\x20tactics)':'')+'!');else{if(_0x420822[_0x407083(0x1d6)]===_0x407083(0x24f))_0x41d921=0xa*_0x51285f,_0x39bc05=Math[_0x407083(0x20d)](_0x41d921*_0x3e609a),_0x452219=_0x41d921-_0x39bc05,_0x420822[_0x407083(0x2d4)]=!![],_0x420822[_0x407083(0x1eb)]=_0x39bc05,log(_0x420822[_0x407083(0x2e2)]+_0x407083(0x38f)+_0x39bc05+_0x407083(0x216)+_0x50a229+(_0x422140[_0x407083(0x2ba)]>0x0?_0x407083(0x2b4)+_0x41d921+_0x407083(0x352)+_0x452219+_0x407083(0x342)+_0x422140[_0x407083(0x2e2)]+'\x27s\x20tactics)':'')+'!');else{if(_0x420822[_0x407083(0x1d6)]==='Regenerate')_0x41d921=0x7*_0x51285f,_0x39bc05=Math[_0x407083(0x20d)](_0x41d921*_0x3e609a),_0x452219=_0x41d921-_0x39bc05,_0x420822[_0x407083(0x2c9)]=Math[_0x407083(0x376)](_0x420822[_0x407083(0x350)],_0x420822[_0x407083(0x2c9)]+_0x39bc05),log(_0x420822[_0x407083(0x2e2)]+_0x407083(0x286)+_0x39bc05+'\x20HP'+_0x50a229+(_0x422140[_0x407083(0x2ba)]>0x0?_0x407083(0x2b4)+_0x41d921+',\x20reduced\x20by\x20'+_0x452219+_0x407083(0x342)+_0x422140['name']+_0x407083(0x25a):'')+'!');else _0x420822['powerup']===_0x407083(0x2c4)&&(_0x41d921=0x5*_0x51285f,_0x39bc05=Math[_0x407083(0x20d)](_0x41d921*_0x3e609a),_0x452219=_0x41d921-_0x39bc05,_0x420822['health']=Math[_0x407083(0x376)](_0x420822[_0x407083(0x350)],_0x420822[_0x407083(0x2c9)]+_0x39bc05),log(_0x420822[_0x407083(0x2e2)]+_0x407083(0x355)+_0x39bc05+_0x407083(0x328)+_0x50a229+(_0x422140['tactics']>0x0?'\x20(originally\x20'+_0x41d921+_0x407083(0x352)+_0x452219+'\x20due\x20to\x20'+_0x422140['name']+_0x407083(0x25a):'')+'!'));}}this[_0x407083(0x2fd)](_0x420822);}[_0x50a5e2(0x2fd)](_0x495c01){const _0x2897d4=_0x50a5e2,_0x4242ff=_0x495c01===this['player1']?p1Health:p2Health,_0x530e43=_0x495c01===this['player1']?p1Hp:p2Hp,_0x1a875b=_0x495c01[_0x2897d4(0x2c9)]/_0x495c01['maxHealth']*0x64;_0x4242ff['style'][_0x2897d4(0x288)]=_0x1a875b+'%';let _0x5786f6;if(_0x1a875b>0x4b)_0x5786f6=_0x2897d4(0x299);else{if(_0x1a875b>0x32)_0x5786f6='#FFC105';else _0x1a875b>0x19?_0x5786f6=_0x2897d4(0x207):_0x5786f6=_0x2897d4(0x1fe);}_0x4242ff[_0x2897d4(0x1e5)][_0x2897d4(0x28e)]=_0x5786f6,_0x530e43['textContent']=_0x495c01[_0x2897d4(0x2c9)]+'/'+_0x495c01['maxHealth'];}[_0x50a5e2(0x2d3)](){const _0x47aa5a=_0x50a5e2;if(this[_0x47aa5a(0x300)]===_0x47aa5a(0x2e3)||this[_0x47aa5a(0x2e3)]){console[_0x47aa5a(0x1fc)]('Game\x20over,\x20skipping\x20endTurn');return;}this[_0x47aa5a(0x2f2)]=this[_0x47aa5a(0x2f2)]===this[_0x47aa5a(0x1a5)]?this[_0x47aa5a(0x202)]:this[_0x47aa5a(0x1a5)],this['gameState']=this[_0x47aa5a(0x2f2)]===this[_0x47aa5a(0x1a5)]?_0x47aa5a(0x1c6):_0x47aa5a(0x234),turnIndicator[_0x47aa5a(0x226)]=_0x47aa5a(0x2bc)+this[_0x47aa5a(0x242)]+'\x20-\x20'+(this['currentTurn']===this['player1']?_0x47aa5a(0x304):_0x47aa5a(0x245))+_0x47aa5a(0x257),log(_0x47aa5a(0x25c)+(this[_0x47aa5a(0x2f2)]===this[_0x47aa5a(0x1a5)]?'Player':'Opponent')),this[_0x47aa5a(0x2f2)]===this[_0x47aa5a(0x202)]&&setTimeout(()=>this[_0x47aa5a(0x234)](),0x3e8);}[_0x50a5e2(0x234)](){const _0x58afdb=_0x50a5e2;if(this['gameState']!==_0x58afdb(0x234)||this[_0x58afdb(0x2f2)]!==this['player2'])return;this[_0x58afdb(0x300)]=_0x58afdb(0x239);const _0x121c15=this['findAIMove']();_0x121c15?(log(this[_0x58afdb(0x202)]['name']+'\x20swaps\x20tiles\x20at\x20('+_0x121c15['x1']+',\x20'+_0x121c15['y1']+_0x58afdb(0x347)+_0x121c15['x2']+',\x20'+_0x121c15['y2']+')'),this[_0x58afdb(0x380)](_0x121c15['x1'],_0x121c15['y1'],_0x121c15['x2'],_0x121c15['y2'])):(log(this['player2'][_0x58afdb(0x2e2)]+'\x20passes...'),this[_0x58afdb(0x2d3)]());}['findAIMove'](){const _0x124b15=_0x50a5e2;for(let _0x3728fc=0x0;_0x3728fc<this[_0x124b15(0x36b)];_0x3728fc++){for(let _0x2dd090=0x0;_0x2dd090<this[_0x124b15(0x288)];_0x2dd090++){if(_0x2dd090<this[_0x124b15(0x288)]-0x1&&this[_0x124b15(0x2a1)](_0x2dd090,_0x3728fc,_0x2dd090+0x1,_0x3728fc))return{'x1':_0x2dd090,'y1':_0x3728fc,'x2':_0x2dd090+0x1,'y2':_0x3728fc};if(_0x3728fc<this[_0x124b15(0x36b)]-0x1&&this[_0x124b15(0x2a1)](_0x2dd090,_0x3728fc,_0x2dd090,_0x3728fc+0x1))return{'x1':_0x2dd090,'y1':_0x3728fc,'x2':_0x2dd090,'y2':_0x3728fc+0x1};}}return null;}[_0x50a5e2(0x2a1)](_0x3f85dc,_0x3c8369,_0x36271f,_0x352d80){const _0x32e133=_0x50a5e2,_0x566468={...this['board'][_0x3c8369][_0x3f85dc]},_0xc47543={...this[_0x32e133(0x39c)][_0x352d80][_0x36271f]};this['board'][_0x3c8369][_0x3f85dc]=_0xc47543,this[_0x32e133(0x39c)][_0x352d80][_0x36271f]=_0x566468;const _0x267af9=this[_0x32e133(0x1dc)]()[_0x32e133(0x33f)]>0x0;return this[_0x32e133(0x39c)][_0x3c8369][_0x3f85dc]=_0x566468,this[_0x32e133(0x39c)][_0x352d80][_0x36271f]=_0xc47543,_0x267af9;}async[_0x50a5e2(0x191)](){const _0x3d846a=_0x50a5e2;if(this[_0x3d846a(0x2e3)]||this[_0x3d846a(0x268)]){console[_0x3d846a(0x1fc)](_0x3d846a(0x2b2)+this[_0x3d846a(0x2e3)]+_0x3d846a(0x349)+this[_0x3d846a(0x268)]+',\x20currentLevel='+this[_0x3d846a(0x242)]);return;}this[_0x3d846a(0x268)]=!![],console[_0x3d846a(0x1fc)](_0x3d846a(0x324)+this[_0x3d846a(0x242)]+_0x3d846a(0x1b0)+this['player1'][_0x3d846a(0x2c9)]+_0x3d846a(0x23f)+this['player2'][_0x3d846a(0x2c9)]);const _0x4de992=document[_0x3d846a(0x1b8)](_0x3d846a(0x351));if(this[_0x3d846a(0x1a5)][_0x3d846a(0x2c9)]<=0x0){console['log'](_0x3d846a(0x3a2)),this['gameOver']=!![],this[_0x3d846a(0x300)]=_0x3d846a(0x2e3),gameOver[_0x3d846a(0x226)]=_0x3d846a(0x2a0),turnIndicator[_0x3d846a(0x226)]=_0x3d846a(0x20c),log(this[_0x3d846a(0x202)]['name']+'\x20defeats\x20'+this[_0x3d846a(0x1a5)][_0x3d846a(0x2e2)]+'!'),_0x4de992[_0x3d846a(0x226)]='TRY\x20AGAIN',document[_0x3d846a(0x1b8)](_0x3d846a(0x1e6))['style'][_0x3d846a(0x2bd)]=_0x3d846a(0x240);try{this['sounds']['loss'][_0x3d846a(0x2dd)]();}catch(_0x5b1be1){console[_0x3d846a(0x241)](_0x3d846a(0x37e),_0x5b1be1);}}else{if(this[_0x3d846a(0x202)][_0x3d846a(0x2c9)]<=0x0){console[_0x3d846a(0x1fc)]('Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)'),this['gameOver']=!![],this[_0x3d846a(0x300)]=_0x3d846a(0x2e3),gameOver[_0x3d846a(0x226)]='You\x20Win!',turnIndicator[_0x3d846a(0x226)]=_0x3d846a(0x20c),_0x4de992[_0x3d846a(0x226)]=this[_0x3d846a(0x242)]===opponentsConfig[_0x3d846a(0x33f)]?_0x3d846a(0x256):'NEXT\x20LEVEL',document['getElementById'](_0x3d846a(0x1e6))[_0x3d846a(0x1e5)][_0x3d846a(0x2bd)]=_0x3d846a(0x240);if(this[_0x3d846a(0x2f2)]===this[_0x3d846a(0x1a5)]){const _0x18afc8=this[_0x3d846a(0x1d9)][this[_0x3d846a(0x1d9)][_0x3d846a(0x33f)]-0x1];if(_0x18afc8&&!_0x18afc8[_0x3d846a(0x2f9)]){_0x18afc8[_0x3d846a(0x213)]=this['player1'][_0x3d846a(0x2c9)]/this[_0x3d846a(0x1a5)][_0x3d846a(0x350)]*0x64,_0x18afc8[_0x3d846a(0x2f9)]=!![];const _0x585345=_0x18afc8[_0x3d846a(0x2aa)]>0x0?_0x18afc8[_0x3d846a(0x1f9)]/_0x18afc8[_0x3d846a(0x2aa)]/0x64*(_0x18afc8['healthPercentage']+0x14)*(0x1+this[_0x3d846a(0x242)]/0x38):0x0;log('Calculating\x20round\x20score:\x20points='+_0x18afc8[_0x3d846a(0x1f9)]+',\x20matches='+_0x18afc8['matches']+',\x20healthPercentage='+_0x18afc8['healthPercentage'][_0x3d846a(0x35a)](0x2)+_0x3d846a(0x34b)+this[_0x3d846a(0x242)]),log('Round\x20Score\x20Formula:\x20((('+_0x18afc8[_0x3d846a(0x1f9)]+_0x3d846a(0x2a6)+_0x18afc8[_0x3d846a(0x2aa)]+')\x20/\x20100)\x20*\x20('+_0x18afc8[_0x3d846a(0x213)]+_0x3d846a(0x1cd)+this['currentLevel']+_0x3d846a(0x2c5)+_0x585345),this[_0x3d846a(0x1ee)]+=_0x585345,log(_0x3d846a(0x27d)+_0x18afc8[_0x3d846a(0x1f9)]+_0x3d846a(0x3a5)+_0x18afc8[_0x3d846a(0x2aa)]+',\x20Health\x20Left:\x20'+_0x18afc8[_0x3d846a(0x213)]['toFixed'](0x2)+'%'),log(_0x3d846a(0x1ac)+_0x585345+',\x20Grand\x20Total\x20Score:\x20'+this[_0x3d846a(0x1ee)]);}}await this[_0x3d846a(0x270)](this[_0x3d846a(0x242)]);this[_0x3d846a(0x242)]===opponentsConfig[_0x3d846a(0x33f)]?(this['sounds'][_0x3d846a(0x212)][_0x3d846a(0x2dd)](),log('Final\x20level\x20completed!\x20Final\x20score:\x20'+this[_0x3d846a(0x1ee)]),this['grandTotalScore']=0x0,await this[_0x3d846a(0x392)](),log('Game\x20completed!\x20Grand\x20total\x20score\x20reset.')):(this[_0x3d846a(0x242)]+=0x1,await this[_0x3d846a(0x2d0)](),console[_0x3d846a(0x1fc)](_0x3d846a(0x1e9)+this['currentLevel']),this[_0x3d846a(0x389)]['win'][_0x3d846a(0x2dd)]());const _0x32f7cd=this['baseImagePath']+_0x3d846a(0x274)+this['player2']['name'][_0x3d846a(0x2ab)]()[_0x3d846a(0x2ad)](/ /g,'-')+_0x3d846a(0x308);p2Image['src']=_0x32f7cd,p2Image['classList'][_0x3d846a(0x22e)]('loser'),p1Image['classList'][_0x3d846a(0x22e)](_0x3d846a(0x233)),this[_0x3d846a(0x31d)]();}}this[_0x3d846a(0x268)]=![],console[_0x3d846a(0x1fc)]('checkGameOver\x20completed:\x20currentLevel='+this[_0x3d846a(0x242)]+',\x20gameOver='+this[_0x3d846a(0x2e3)]);}async['saveScoreToDatabase'](_0x112942){const _0x39aa4c=_0x50a5e2,_0x8426fc={'level':_0x112942,'score':this[_0x39aa4c(0x1ee)]};console[_0x39aa4c(0x1fc)](_0x39aa4c(0x399)+_0x8426fc[_0x39aa4c(0x1fd)]+_0x39aa4c(0x208)+_0x8426fc[_0x39aa4c(0x26d)]);try{const _0x46d462=await fetch('ajax/save-monstrocity-score.php',{'method':'POST','headers':{'Content-Type':'application/json'},'body':JSON[_0x39aa4c(0x37a)](_0x8426fc)});if(!_0x46d462['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x46d462[_0x39aa4c(0x325)]);const _0x375c12=await _0x46d462[_0x39aa4c(0x1df)]();console['log'](_0x39aa4c(0x275),_0x375c12),log(_0x39aa4c(0x2bc)+_0x375c12['level']+_0x39aa4c(0x20b)+_0x375c12['score'][_0x39aa4c(0x35a)](0x2)),_0x375c12[_0x39aa4c(0x325)]===_0x39aa4c(0x2d5)?log(_0x39aa4c(0x373)+_0x375c12[_0x39aa4c(0x1fd)]+_0x39aa4c(0x1ad)+_0x375c12[_0x39aa4c(0x26d)][_0x39aa4c(0x35a)](0x2)+_0x39aa4c(0x254)+_0x375c12[_0x39aa4c(0x313)]):log(_0x39aa4c(0x2f0)+_0x375c12[_0x39aa4c(0x2a2)]);}catch(_0x49aadd){console['error']('Error\x20saving\x20to\x20database:',_0x49aadd),log('Error\x20saving\x20score:\x20'+_0x49aadd[_0x39aa4c(0x2a2)]);}}[_0x50a5e2(0x1f6)](_0x1ac61b,_0x1294c9,_0x2e7aa4,_0x39402c){const _0x34f3e3=_0x50a5e2,_0x35cfc5=_0x1ac61b[_0x34f3e3(0x1e5)][_0x34f3e3(0x20a)]||'',_0x234962=_0x35cfc5['includes'](_0x34f3e3(0x260))?_0x35cfc5['match'](/scaleX\([^)]+\)/)[0x0]:'';_0x1ac61b['style'][_0x34f3e3(0x22c)]=_0x34f3e3(0x2f5)+_0x39402c/0x2/0x3e8+_0x34f3e3(0x244),_0x1ac61b[_0x34f3e3(0x1e5)][_0x34f3e3(0x20a)]=_0x34f3e3(0x391)+_0x1294c9+_0x34f3e3(0x1c1)+_0x234962,_0x1ac61b[_0x34f3e3(0x34c)][_0x34f3e3(0x22e)](_0x2e7aa4),setTimeout(()=>{const _0x2ae28c=_0x34f3e3;_0x1ac61b[_0x2ae28c(0x1e5)][_0x2ae28c(0x20a)]=_0x234962,setTimeout(()=>{const _0x13ff0b=_0x2ae28c;_0x1ac61b[_0x13ff0b(0x34c)][_0x13ff0b(0x362)](_0x2e7aa4);},_0x39402c/0x2);},_0x39402c/0x2);}[_0x50a5e2(0x29b)](_0x53f7d1,_0x4ab282,_0x1f643f){const _0x2864a5=_0x50a5e2,_0x5b1598=_0x53f7d1===this['player1']?p1Image:p2Image,_0x286fce=_0x53f7d1===this[_0x2864a5(0x1a5)]?0x1:-0x1,_0x15d756=Math[_0x2864a5(0x376)](0xa,0x2+_0x4ab282*0.4),_0x458e0b=_0x286fce*_0x15d756,_0x4b0669=_0x2864a5(0x259)+_0x1f643f;this[_0x2864a5(0x1f6)](_0x5b1598,_0x458e0b,_0x4b0669,0xc8);}[_0x50a5e2(0x27a)](_0x3d4a6f){const _0x504d08=_0x50a5e2,_0x274f50=_0x3d4a6f===this['player1']?p1Image:p2Image;this[_0x504d08(0x1f6)](_0x274f50,0x0,_0x504d08(0x19a),0xc8);}[_0x50a5e2(0x1a9)](_0x21ce28,_0x24d45e){const _0x45f6da=_0x50a5e2,_0x3bfcf1=_0x21ce28===this['player1']?p1Image:p2Image,_0xa5295e=_0x21ce28===this[_0x45f6da(0x1a5)]?-0x1:0x1,_0xe2c0bb=Math['min'](0xa,0x2+_0x24d45e*0.4),_0x164838=_0xa5295e*_0xe2c0bb;this['applyAnimation'](_0x3bfcf1,_0x164838,_0x45f6da(0x28c),0xc8);}}function randomChoice(_0x66228a){const _0x35e670=_0x50a5e2;return _0x66228a[Math['floor'](Math[_0x35e670(0x330)]()*_0x66228a['length'])];}function log(_0x43c838){const _0x276ac1=_0x50a5e2,_0x4c8380=document['getElementById'](_0x276ac1(0x25d)),_0xc338d9=document[_0x276ac1(0x36a)]('li');_0xc338d9[_0x276ac1(0x226)]=_0x43c838,_0x4c8380[_0x276ac1(0x30b)](_0xc338d9,_0x4c8380['firstChild']),_0x4c8380[_0x276ac1(0x21c)]['length']>0x32&&_0x4c8380[_0x276ac1(0x194)](_0x4c8380[_0x276ac1(0x35c)]),_0x4c8380[_0x276ac1(0x27f)]=0x0;}function _0x28cb(_0x18fb16,_0x10be62){const _0x17cc15=_0x17cc();return _0x28cb=function(_0x28cb1d,_0x105630){_0x28cb1d=_0x28cb1d-0x18f;let _0x5aa398=_0x17cc15[_0x28cb1d];return _0x5aa398;},_0x28cb(_0x18fb16,_0x10be62);}const turnIndicator=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x2a8)),p1Name=document['getElementById'](_0x50a5e2(0x1a0)),p1Image=document[_0x50a5e2(0x1b8)]('p1-image'),p1Health=document[_0x50a5e2(0x1b8)]('p1-health'),p1Hp=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x1c8)),p1Strength=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x30d)),p1Speed=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x2ca)),p1Tactics=document['getElementById'](_0x50a5e2(0x223)),p1Size=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x2cc)),p1Powerup=document['getElementById'](_0x50a5e2(0x1bb)),p1Type=document['getElementById'](_0x50a5e2(0x1ae)),p2Name=document['getElementById'](_0x50a5e2(0x31e)),p2Image=document['getElementById'](_0x50a5e2(0x2b8)),p2Health=document['getElementById']('p2-health'),p2Hp=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x295)),p2Strength=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x1a1)),p2Speed=document['getElementById'](_0x50a5e2(0x1e3)),p2Tactics=document[_0x50a5e2(0x1b8)]('p2-tactics'),p2Size=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x27e)),p2Powerup=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x363)),p2Type=document[_0x50a5e2(0x1b8)](_0x50a5e2(0x2e0)),battleLog=document['getElementById'](_0x50a5e2(0x25d)),gameOver=document['getElementById'](_0x50a5e2(0x331)),assetCache={};async function getAssets(_0x4ae59b){const _0x1752f0=_0x50a5e2;if(assetCache[_0x4ae59b])return console[_0x1752f0(0x1fc)](_0x1752f0(0x251)+_0x4ae59b),assetCache[_0x4ae59b];console['time'](_0x1752f0(0x29d)+_0x4ae59b);let _0x89469c=[];try{console[_0x1752f0(0x1fc)](_0x1752f0(0x215));const _0x59fb2e=await Promise[_0x1752f0(0x374)]([fetch(_0x1752f0(0x2cd),{'method':_0x1752f0(0x2e5),'headers':{'Content-Type':_0x1752f0(0x1cc)},'body':JSON['stringify']({'theme':_0x1752f0(0x217)})}),new Promise((_0x2ce322,_0x142462)=>setTimeout(()=>_0x142462(new Error('Monstrocity\x20timeout')),0x3e8))]);console['log']('getAssets:\x20Monstrocity\x20status=',_0x59fb2e[_0x1752f0(0x325)]);if(!_0x59fb2e['ok'])throw new Error('Monstrocity\x20HTTP\x20error!\x20Status:\x20'+_0x59fb2e[_0x1752f0(0x325)]);_0x89469c=await _0x59fb2e[_0x1752f0(0x1df)](),console['log'](_0x1752f0(0x1b9),_0x89469c),!Array[_0x1752f0(0x33a)](_0x89469c)&&(_0x89469c=[_0x89469c]),_0x89469c=_0x89469c[_0x1752f0(0x1bf)]((_0x3e2d87,_0x2e534f)=>{const _0x49ed0e=_0x1752f0,_0x27c795={..._0x3e2d87,'theme':_0x49ed0e(0x217),'name':_0x3e2d87[_0x49ed0e(0x2e2)]||_0x49ed0e(0x1d8)+_0x2e534f,'strength':_0x3e2d87[_0x49ed0e(0x296)]||0x4,'speed':_0x3e2d87[_0x49ed0e(0x37d)]||0x4,'tactics':_0x3e2d87[_0x49ed0e(0x2ba)]||0x4,'size':_0x3e2d87[_0x49ed0e(0x29c)]||_0x49ed0e(0x316),'type':_0x3e2d87[_0x49ed0e(0x25e)]||_0x49ed0e(0x21e),'powerup':_0x3e2d87[_0x49ed0e(0x1d6)]||'Regenerate'};return _0x27c795;});}catch(_0x114b7d){console[_0x1752f0(0x241)]('getAssets:\x20Monstrocity\x20fetch\x20error:',_0x114b7d),_0x89469c=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1752f0(0x316),'type':_0x1752f0(0x21e),'powerup':_0x1752f0(0x238),'theme':_0x1752f0(0x217)},{'name':_0x1752f0(0x29f),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x1752f0(0x365),'type':'Base','powerup':_0x1752f0(0x39d),'theme':'monstrocity'}],console['log'](_0x1752f0(0x388));}if(_0x4ae59b===_0x1752f0(0x217))return console[_0x1752f0(0x1fc)]('getAssets:\x20Returning\x20Monstrocity\x20assets'),assetCache[_0x4ae59b]=_0x89469c,console[_0x1752f0(0x203)](_0x1752f0(0x29d)+_0x4ae59b),_0x89469c;let _0x576bd5=null;for(const _0x382864 of themes){_0x576bd5=_0x382864[_0x1752f0(0x32d)][_0x1752f0(0x369)](_0x59f2d5=>_0x59f2d5['value']===_0x4ae59b);if(_0x576bd5)break;}if(!_0x576bd5)return console[_0x1752f0(0x1f0)]('getAssets:\x20Theme\x20not\x20found:\x20'+_0x4ae59b),assetCache[_0x4ae59b]=_0x89469c,console[_0x1752f0(0x203)]('getAssets_'+_0x4ae59b),_0x89469c;const _0x5b2106=_0x576bd5[_0x1752f0(0x190)]?_0x576bd5[_0x1752f0(0x190)][_0x1752f0(0x279)](',')[_0x1752f0(0x334)](_0x18e82c=>_0x18e82c['trim']()):[];if(!_0x5b2106[_0x1752f0(0x33f)])return console[_0x1752f0(0x1fc)](_0x1752f0(0x1f1)+_0x4ae59b),assetCache[_0x4ae59b]=_0x89469c,console['timeEnd'](_0x1752f0(0x29d)+_0x4ae59b),_0x89469c;const _0x2068a3=_0x576bd5[_0x1752f0(0x209)]?_0x576bd5[_0x1752f0(0x209)][_0x1752f0(0x279)](',')[_0x1752f0(0x334)](_0x35fd9e=>_0x35fd9e[_0x1752f0(0x21b)]()):[],_0x173538=_0x576bd5[_0x1752f0(0x336)]?_0x576bd5['ipfsPrefixes'][_0x1752f0(0x279)](',')[_0x1752f0(0x334)](_0x2cef58=>_0x2cef58[_0x1752f0(0x21b)]()):[],_0x31ecd2=_0x5b2106[_0x1752f0(0x1bf)]((_0x5a48d5,_0x52e5ff)=>({'policyId':_0x5a48d5,'orientation':_0x2068a3[_0x1752f0(0x33f)]===0x1?_0x2068a3[0x0]:_0x2068a3[_0x52e5ff]||_0x1752f0(0x398),'ipfsPrefix':_0x173538['length']===0x1?_0x173538[0x0]:_0x173538[_0x52e5ff]||'https://ipfs.io/ipfs/'}));let _0x2f2440=[];try{const _0x4eade2=JSON[_0x1752f0(0x37a)]({'policyIds':_0x31ecd2[_0x1752f0(0x1bf)](_0xbd6790=>_0xbd6790[_0x1752f0(0x237)]),'theme':_0x4ae59b});console[_0x1752f0(0x1fc)](_0x1752f0(0x321));const _0x464cdc=await Promise[_0x1752f0(0x374)]([fetch(_0x1752f0(0x395),{'method':_0x1752f0(0x2e5),'headers':{'Content-Type':'application/json'},'body':_0x4eade2}),new Promise((_0x5bd6ca,_0x40b9f1)=>setTimeout(()=>_0x40b9f1(new Error(_0x1752f0(0x1c2))),0x2710))]);if(!_0x464cdc['ok'])throw new Error(_0x1752f0(0x37b)+_0x464cdc['status']);const _0x334e56=await _0x464cdc['text']();let _0x477fc2;try{_0x477fc2=JSON['parse'](_0x334e56);}catch(_0x30a755){console[_0x1752f0(0x241)](_0x1752f0(0x231),_0x30a755);throw _0x30a755;}_0x477fc2===![]||_0x477fc2===_0x1752f0(0x322)?(console[_0x1752f0(0x1fc)](_0x1752f0(0x329)),_0x2f2440=[]):_0x2f2440=Array[_0x1752f0(0x33a)](_0x477fc2)?_0x477fc2:[_0x477fc2],_0x2f2440=_0x2f2440[_0x1752f0(0x1bf)]((_0x30eb3a,_0x3fb738)=>{const _0xd4d13e=_0x1752f0,_0xe0ec73={..._0x30eb3a,'theme':_0x4ae59b,'name':_0x30eb3a['name']||_0xd4d13e(0x28b)+_0x3fb738,'strength':_0x30eb3a[_0xd4d13e(0x296)]||0x4,'speed':_0x30eb3a[_0xd4d13e(0x37d)]||0x4,'tactics':_0x30eb3a[_0xd4d13e(0x2ba)]||0x4,'size':_0x30eb3a[_0xd4d13e(0x29c)]||_0xd4d13e(0x316),'type':_0x30eb3a['type']||_0xd4d13e(0x21e),'powerup':_0x30eb3a[_0xd4d13e(0x1d6)]||_0xd4d13e(0x238),'policyId':_0x30eb3a[_0xd4d13e(0x237)]||_0x31ecd2[0x0][_0xd4d13e(0x237)],'ipfs':_0x30eb3a[_0xd4d13e(0x1ef)]||''};return _0xe0ec73;});}catch(_0x343d17){console['error'](_0x1752f0(0x227)+_0x4ae59b+':',_0x343d17),_0x2f2440=[];}const _0x4d56a3=[..._0x89469c,..._0x2f2440];return console[_0x1752f0(0x1fc)]('getAssets:\x20Returning\x20merged\x20assets,\x20count='+_0x4d56a3[_0x1752f0(0x33f)]),assetCache[_0x4ae59b]=_0x4d56a3,console[_0x1752f0(0x203)](_0x1752f0(0x29d)+_0x4ae59b),_0x4d56a3;}document[_0x50a5e2(0x1cb)](_0x50a5e2(0x2f6),function(){var _0x322d51=function(){const _0x561e94=_0x28cb;var _0x382f20=localStorage['getItem'](_0x561e94(0x1b2))||_0x561e94(0x217);getAssets(_0x382f20)[_0x561e94(0x309)](function(_0x3ea5c5){const _0x4dc3c8=_0x561e94;console[_0x4dc3c8(0x1fc)](_0x4dc3c8(0x383),_0x3ea5c5);var _0x566ed6=new MonstrocityMatch3(_0x3ea5c5,_0x382f20);console[_0x4dc3c8(0x1fc)](_0x4dc3c8(0x2a7)),_0x566ed6[_0x4dc3c8(0x39f)]()[_0x4dc3c8(0x309)](function(){const _0x58b655=_0x4dc3c8;console['log'](_0x58b655(0x32c));});})['catch'](function(_0x50256c){const _0x120046=_0x561e94;console['error'](_0x120046(0x276),_0x50256c);});};_0x322d51();});function _0x17cc(){const _0x20d760=['getAssets:\x20Monstrocity\x20data=','Found\x20','p1-powerup','backgroundImage','\x0a\x09\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20<button\x20id=\x22theme-close-button\x22>Close</button>\x0a\x09\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20','touchend','map','Cascade\x20complete,\x20ending\x20turn','px)\x20','NFT\x20timeout','No\x20matches\x20found,\x20returning\x20false','Random','\x20uses\x20','playerTurn','left','p1-hp',',\x20loadedScore=','Jarhead','addEventListener','application/json','\x20+\x2020))\x20*\x20(1\x20+\x20','428722vbpnVA','touchstart','Last\x20Stand\x20applied,\x20mitigated\x20','handleTouchMove','\x20for\x20','\x20size\x20','flatMap','img','powerup','updateOpponentDisplay','Monstrocity_Unknown_','roundStats','Game\x20not\x20over,\x20animating\x20attack','\x20tiles!','checkMatches','resolveMatches\x20started,\x20gameOver:','appendChild','json','Game\x20over,\x20skipping\x20cascadeTiles','Total\x20damage\x20dealt:\x20','16944uIUWEN','p2-speed','px,\x20','style','game-over-container','translate(0,\x200)','Drake','Progress\x20saved:\x20currentLevel=','px)\x20scale(1.05)','boostValue','handleMatch\x20completed,\x20damage\x20dealt:\x20','https://ipfs.io/ipfs/','grandTotalScore','ipfs','warn','getAssets:\x20No\x20policy\x20IDs\x20for\x20theme\x20','visibility','translate(','addEventListeners:\x20Player\x201\x20image\x20clicked','progress-start-fresh','applyAnimation','Ouchie','px)','points','getBoundingClientRect','https://www.skulliance.io/staking/icons/','log','level','#F44336','\x20HP!','handleTouchStart','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','player2','timeEnd','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','time','<p>Health:\x20','#FFA500',',\x20score=','orientations','transform','\x20Score:\x20','Game\x20Over','floor','translate(0,\x20','touches','cascadeTilesWithoutRender','isTouchDevice','finalWin','healthPercentage','initializing','getAssets:\x20Fetching\x20Monstrocity\x20assets','\x20damage','monstrocity','baseImagePath','resolveMatches','Slash','trim','children','push','Base','handleMouseMove','msMaxTouchPoints','ajax/load-monstrocity-progress.php','isNFT','p1-tactics','getTileFromEvent','https://www.skulliance.io/staking/images/monstrocity/','textContent','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20','scaleX(-1)','updatePlayerDisplay','initBoard','/monstrocity.png','transition','setBackground:\x20Attempting\x20for\x20theme=','add','createDocumentFragment','Multi-Match!\x20','getAssets:\x20NFT\x20parse\x20error:','\x20starts\x20at\x20full\x20strength\x20with\x20','winner','aiTurn','</p>','selectedTile','policyId','Regenerate','animating','progress-resume','Player\x201','showThemeSelect','46407xqKYRn','getItem',',\x20player2.health=','block','error','currentLevel','Raw\x20response\x20text:','s\x20linear','Opponent','<p>Power-Up:\x20','theme','70110YeSnIn','createRandomTile','project','Resumed\x20at\x20Level\x20','Error\x20clearing\x20progress:','Slime\x20Mind','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','Boost\x20Attack','backgroundPosition','getAssets:\x20Cache\x20hit\x20for\x20','54URpPez','row',',\x20Completions:\x20','first-attack','START\x20OVER','\x27s\x20Turn','updateTheme_','glow-','\x27s\x20tactics)','second-attack','Turn\x20switched\x20to\x20','battle-log','type','clientY','scaleX','dataset','HTTP\x20error!\x20Status:\x20','ipfsPrefix','Error\x20loading\x20progress:','ajax/clear-monstrocity-progress.php','Calling\x20checkGameOver\x20from\x20handleMatch','visible','isCheckingGameOver','isInitialMove:','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','https://www.skulliance.io/staking/sounds/select.ogg','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','score','\x20health\x20after\x20damage:\x20','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','saveScoreToDatabase','playerCharacters','countEmptyBelow','updateTileSizeWithGap','battle-damaged/','Save\x20response:','Main:\x20Error\x20initializing\x20game:','forEach','\x20on\x20','split','animatePowerup','showProgressPopup','handleGameOverButton','Round\x20Won!\x20Points:\x20','p2-size','scrollTop','setBackground','center','value','coordinates','Base\x20damage:\x20','Katastrophy','\x20uses\x20Regen,\x20restoring\x20','progress','width','.game-logo','initGame:\x20Started\x20with\x20this.currentLevel=','NFT_Unknown_','glow-recoil','className','backgroundColor','background','onclick','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','game-board','Processing\x20match:','max','p2-hp','strength','touchmove','ontouchstart','#4CAF50','flipCharacter','animateAttack','size','getAssets_','power-up','Dankle','You\x20Lose!','canMakeMatch','message','Reset\x20to\x20Level\x201:\x20currentLevel=','updateCharacters_','button','\x20/\x20','Main:\x20Game\x20instance\x20created','turn-indicator','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','matches','toLowerCase','\x20to\x20','replace','alt','matched','multiMatch','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','checkGameOver\x20skipped:\x20gameOver=','none','\x20(originally\x20','Error\x20in\x20resolveMatches:','createCharacter:\x20config=','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','p2-image','\x20goes\x20first!','tactics','Restart','Level\x20','display','139832jiQQsp','round','initGame','\x20damage,\x20but\x20','Resume','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','Minor\x20Regen','\x20/\x2056)\x20=\x20','powerGem','ajax/save-monstrocity-progress.php','removeEventListener','health','p1-speed','targetTile','p1-size','ajax/get-monstrocity-assets.php','tile\x20','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20','saveProgress','backgroundSize','Round\x20points\x20increased\x20from\x20','endTurn','boostActive','success','182137tXFuoY','\x20but\x20sharpens\x20tactics\x20to\x20','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','8XUOqxd','\x20health\x20before\x20match:\x20','Koipon','/logo.png','play',',\x20Total\x20damage:\x20','element','p2-type','leader','name','gameOver','<p>Type:\x20','POST','totalTiles','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','addEventListeners','\x20with\x20Score\x20of\x20',',\x20cols\x20','\x22\x20data-project=\x22','character-select-container','Error\x20saving\x20progress:','cascade','https://www.skulliance.io/staking/sounds/powergem_created.ogg','Score\x20Not\x20Saved:\x20','Parsed\x20response:','currentTurn','No\x20match,\x20reverting\x20tiles...','init:\x20Prompting\x20with\x20loadedLevel=','transform\x20','DOMContentLoaded',',\x20rows\x20','Battle\x20Damaged','completed','Loaded\x20opponent\x20for\x20level\x20','Starting\x20Level\x20','src','updateHealth','handleMatch','reduce','gameState','<p><strong>','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','Player','onerror','\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>','clientX','.png','then','showCharacterSelect:\x20this.player1\x20set:\x20','insertBefore','\x22\x20alt=\x22','p1-strength','Progress\x20cleared','...','Merdock','.game-container','restart','attempts','group','drops\x20health\x20to\x20','Medium','Game\x20over,\x20skipping\x20endTurn','handleMouseDown','handleMatch\x20started,\x20match:','Boost\x20applied,\x20damage:\x20','theme-group','theme-select-container','renderBoard','p2-name','https://www.skulliance.io/staking/sounds/speedmatch1.ogg','\x27s\x20orientation\x20flipped\x20to\x20','getAssets:\x20Sending\x20NFT\x20POST','false','base','checkGameOver\x20started:\x20currentLevel=','status','\x27s\x20Boost\x20fades.','offsetX','\x20HP','getAssets:\x20NFT\x20data\x20is\x20false','tileTypes','</strong></p>','Main:\x20Game\x20initialized\x20successfully','items','\x20damage\x20to\x20','column','random','game-over','\x20(opponentsConfig[','Spydrax','filter','Goblin\x20Ganger','ipfsPrefixes','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','click','showCharacterSelect','isArray','init:\x20Starting\x20async\x20initialization','\x20but\x20dulls\x20tactics\x20to\x20','setBackground:\x20Setting\x20background\x20to\x20','Tile\x20at\x20(','length','maxTouchPoints','orientation','\x20due\x20to\x20','p1-image',',\x20damage:\x20','checkMatches\x20completed,\x20returning\x20matches:','body',')\x20to\x20(','Error\x20updating\x20theme\x20assets:',',\x20isCheckingGameOver=','3847746cyubii',',\x20level=','classList','setItem','flip-p1','selected','maxHealth','try-again',',\x20reduced\x20by\x20','<p>Tactics:\x20','init:\x20Async\x20initialization\x20completed','\x20uses\x20Minor\x20Regen,\x20restoring\x20','Leader','battle-damaged','loadProgress','cover','toFixed','boosts\x20health\x20to\x20','lastChild','onload','special-attack','url(','indexOf','Billandar\x20and\x20Ted','remove','p2-powerup','offsetY','Small','loser','reset',')\x20has\x20no\x20element\x20to\x20animate','find','createElement','height','Error\x20in\x20checkMatches:','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','Left','div','playerCharactersConfig','flip-p2','preventDefault','Score\x20Saved:\x20Level\x20','race','showProgressPopup:\x20User\x20chose\x20Resume','min','dragDirection','<p>Strength:\x20','flex','stringify','NFT\x20HTTP\x20error!\x20Status:\x20','px,\x200)\x20scale(1.05)','speed','Error\x20playing\x20lose\x20sound:',',\x20Match\x20bonus:\x20','slideTiles','\x27s\x20Last\x20Stand\x20mitigates\x20','abs','Main:\x20Player\x20characters\x20loaded:','Resume\x20from\x20Level\x20','Minor\x20Régén','\x20created\x20a\x20match\x20of\x20','text','getAssets:\x20Using\x20default\x20Monstrocity\x20assets','sounds','setBackground:\x20themeData=','logo.png','\x20steps\x20into\x20the\x20fray\x20with\x20','querySelector','Failed\x20to\x20preload:\x20','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','showCharacterSelect:\x20Character\x20selected:\x20','translateX(','clearProgress','translate(0px,\x200px)','1104KvcqDV','ajax/get-nft-assets.php','15baLVLU','Cascade:\x20','Right','Saving\x20score:\x20level=','change-character','<p>Size:\x20','board','Heal',',\x20selected\x20theme=','init','Progress\x20saved:\x20Level\x20','220xBEaMy','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)','Large','GET',',\x20Matches:\x20','Craig','handleMouseUp','policyIds','checkGameOver','Animating\x20matched\x20tiles,\x20allMatchedTiles:','usePowerup','removeChild','top','innerHTML','imageUrl','<img\x20loading=\x22eager\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22\x20src=\x22','isDragging','glow-power-up','Animating\x20recoil\x20for\x20defender:','tileSizeWithGap','\x20damage!','transform\x200.2s\x20ease','updateTheme','p1-name','p2-strength','swapPlayerCharacter','Game\x20over,\x20exiting\x20resolveMatches','handleTouchEnd','player1','<p>Speed:\x20','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','animateRecoil','\x20-\x20','character-options','Round\x20Score:\x20',',\x20Score\x20','p1-type','https://www.skulliance.io/staking/sounds/voice_go.ogg',',\x20player1.health=','\x20matches:','gameTheme','Starting\x20fresh\x20at\x20Level\x201','Animating\x20powerup','match','parse','createCharacter','getElementById'];_0x17cc=function(){return _0x20d760;};return _0x17cc();}  </script>
</body>
</html>
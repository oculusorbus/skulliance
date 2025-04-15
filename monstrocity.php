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
	  background-color: #165777; /* Fallback color */
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
	          value: "muses",
	          project: "Josh Howard",
	          title: "Muses of the Multiverse",
	          policyIds: "7f95b5948e3efed1171523757b472f24aecfab8303612cfa1b6fec55",
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
			  background: false
	        },
	        {
	          value: "shortyverse2",
	          project: "Ohh Meed",
	          title: "Shorty Verse Engaged",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: false
	        },
	        {
	          value: "bogeyman",
	          project: "Ritual",
	          title: "Bogeyman",
	          policyIds: "bca7c472792b859fb18920477f917c94b76c9c9705e039bf08af0b63",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: false
	        },
	        {
	          value: "ritual",
	          project: "Ritual",
	          title: "John Doe",
	          policyIds: "16b10d60f428b03fa5bafa631c848b2243f31cbf93cce1a65779e5f5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: false
	        },
	        {
	          value: "skowl",
	          project: "Skowl",
	          title: "Derivative Heroes",
	          policyIds: "d38910b4b5bd3e634138dc027b507b52406acf687889e3719aa4f7cf",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: false
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
			  background: false	
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
	  
const _0x5ca7c6=_0x1cfe;function _0x2afa(){const _0x8e3e1a=['special-attack','checkGameOver\x20skipped:\x20gameOver=','p2-health','<p><strong>','clearProgress','Medium','ipfsPrefixes','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','speed','HTTP\x20error!\x20Status:\x20','strength','Main:\x20Game\x20initialized\x20successfully','addEventListeners','Player\x201','theme-select-button','theme-close-button','character-options','Minor\x20Regen',',\x20gameOver=','msMaxTouchPoints','\x20goes\x20first!','p2-hp','Large','p1-image','19719uszAje','battle-damaged','animatePowerup','44kxfcHG','scaleX',',\x20Grand\x20Total\x20Score:\x20','/logo.png','setBackground:\x20Attempting\x20for\x20theme=','updateTheme','slideTiles','background','filter',',\x20level=','Jarhead','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','theme-options','isArray','\x20damage!','isTouchDevice','cascadeTilesWithoutRender','clientX','offsetX','animateAttack','getAssets_','has','forEach','timeEnd','NEXT\x20LEVEL','initializing','style','matched','some','progress-modal-content','left','title','ajax/get-monstrocity-assets.php','sounds','currentTurn','Error\x20in\x20checkMatches:','progress-modal-buttons','\x20after\x20multi-match\x20bonus!','https://ipfs.io/ipfs/','message','ajax/get-nft-assets.php','\x27s\x20Last\x20Stand\x20mitigates\x20','AI\x20Opponent','Round\x20Won!\x20Points:\x20','flip-p1','updateTileSizeWithGap','checkGameOver\x20started:\x20currentLevel=','Game\x20Over','createCharacter:\x20config=','scrollTop','element','floor','div','https://www.skulliance.io/staking/sounds/speedmatch1.ogg','Slash',',\x20Health\x20Left:\x20','drops\x20health\x20to\x20','textContent','progress-start-fresh','getAssets:\x20Monstrocity\x20status=','\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>','showProgressPopup:\x20User\x20chose\x20Resume','No\x20matches\x20found,\x20returning\x20false','type','countEmptyBelow','length','group','Sending\x20saveProgress\x20request\x20with\x20data:','monstrocity','\x20steps\x20into\x20the\x20fray\x20with\x20','checkMatches\x20started','resolveMatches\x20started,\x20gameOver:','https://www.skulliance.io/staking/images/monstrocity/','#FFA500','GET','top','Koipon','touches','playerCharacters','value','usePowerup','warn','findAIMove','progress','1938QcomDa','Level\x20','inline-block',',\x20currentLevel=','change-character','Score\x20Saved:\x20Level\x20','Animating\x20recoil\x20for\x20defender:','Main:\x20Error\x20initializing\x20game:','getAssets:\x20NFT\x20data\x20is\x20false','multiMatch','getAssets:\x20Sending\x20NFT\x20POST','boosts\x20health\x20to\x20','\x20health\x20after\x20damage:\x20',')\x20/\x20100)\x20*\x20(','flipCharacter','\x20uses\x20Regen,\x20restoring\x20','trim','game-over','isDragging','\x20created\x20a\x20match\x20of\x20',',\x20loadedScore=','try-again','5XgJDro','character-select-container','error','No\x20match,\x20reverting\x20tiles...','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20','updateTheme_','player1','<p>Strength:\x20','p2-type','showProgressPopup','DOMContentLoaded','POST','Slime\x20Mind','\x20due\x20to\x20','handleMouseUp','health','falling','points','character-option','initBoard','visibility','grandTotalScore','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','removeChild','className','healthPercentage','\x20(originally\x20','Clearing\x20matched\x20tiles:','then','backgroundImage','<p>Power-Up:\x20','</p>','cascadeTiles',')\x20to\x20(','flip-p2','attempts','winner','catch',',\x20isCheckingGameOver=','\x20for\x20','\x27s\x20tactics)','loadProgress','247692vVrvUS','applyAnimation','Vertical\x20match\x20found\x20at\x20col\x20','10536ZkGifb','size','Game\x20not\x20over,\x20animating\x20attack','\x20damage','saveProgress','handleMouseDown','15418wHPjCm','mouseup','baseImagePath',',\x20rows\x20','abs','ajax/save-monstrocity-progress.php','selected','createElement','<img\x20loading=\x22eager\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22\x20src=\x22','toFixed','Random','Heal','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','6194572CFiSua','reset','log','offsetWidth','getElementById','playerTurn','\x27s\x20orientation\x20flipped\x20to\x20','\x20HP!','name','#4CAF50','orientation','updateCharacters_','src','updateOpponentDisplay','Tile\x20at\x20(','score','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','dragDirection','getBoundingClientRect','column','Multi-Match!\x20','showThemeSelect','showCharacterSelect','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','tactics','transition','Mega\x20Multi-Match!\x20','replace','getAssets:\x20Returning\x20merged\x20assets,\x20count=','getAssets:\x20No\x20policy\x20IDs\x20for\x20theme\x20','Monstrocity\x20timeout','Round\x20Score:\x20','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','level','none','\x20passes...','play','Restart','url(','Boost\x20Attack','p2-powerup','stringify','.game-container','\x20/\x2056)\x20=\x20','canMakeMatch','isCheckingGameOver','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','Round\x20points\x20increased\x20from\x20','\x20damage,\x20resulting\x20in\x20','START\x20OVER','appendChild','children','addEventListeners:\x20Player\x201\x20image\x20clicked','handleGameOverButton\x20started:\x20currentLevel=','Shadow\x20Strike','handleGameOverButton','lastStandActive','battle-damaged/','30eKnFyZ','\x20uses\x20Heal,\x20restoring\x20','offsetY','boostActive','targetTile','\x20uses\x20','init:\x20Async\x20initialization\x20completed','roundStats','handleTouchEnd','onclick','success','cover','p1-type','battle-log','Mandiblus','orientations','restart','base','Dankle','You\x20Win!','getAssets:\x20Using\x20default\x20Monstrocity\x20assets','hyperCube','Last\x20Stand\x20applied,\x20mitigated\x20','Round\x20Score\x20Formula:\x20(((','px,\x200)\x20scale(1.05)','#FFC105','maxTouchPoints','saveScoreToDatabase','Error\x20saving\x20to\x20database:','\x20damage\x20to\x20','checkMatches','Main:\x20Game\x20instance\x20created','round','Main:\x20Player\x20characters\x20loaded:','player2','loser','createCharacter','\x20but\x20dulls\x20tactics\x20to\x20','Small','backgroundSize','px)\x20','last-stand','<p>Type:\x20','Error\x20saving\x20score:\x20','handleTouchStart','Response\x20status:','Right','Cascading\x20tiles','\x20with\x20Score\x20of\x20','p1-hp','animateRecoil','#F44336','body','mousedown','Progress\x20cleared','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','remove','max','map','getAssets:\x20Monstrocity\x20fetch\x20error:','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','px)\x20scale(1.05)','backgroundColor',',\x20score=','firstChild','Calculating\x20round\x20score:\x20points=','time','width','Spydrax','Texby','Game\x20over,\x20skipping\x20cascade\x20resolution','p1-name','NFT\x20HTTP\x20error!\x20Status:\x20','p1-speed','gameOver','powerup','Minor\x20Régén','transform\x20',',\x20Score\x20','visible','Katastrophy','classList','Reset\x20to\x20Level\x201:\x20currentLevel=','</strong></p>','getAssets:\x20NFT\x20parse\x20error:','updateHealth','initGame','ipfsPrefix','init','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','Saving\x20score:\x20level=','Game\x20over,\x20skipping\x20recoil\x20animation','getTileFromEvent','touchstart','Battle\x20Damaged',',\x20Total\x20damage:\x20','\x20Score:\x20','progress-modal','checkMatches\x20completed,\x20returning\x20matches:','Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)','p2-name','Regenerate','\x20damage,\x20but\x20','\x20+\x2020))\x20*\x20(1\x20+\x20','getAssets:\x20Cache\x20hit\x20for\x20','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','policyId','init:\x20Starting\x20async\x20initialization','18024CTFBgc','button',',\x20matches=','Horizontal\x20match\x20found\x20at\x20row\x20','min','power-up','\x20HP','Cascade:\x20','game-board','currentLevel','win','selectedTile','\x22\x20data-project=\x22','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)','application/json','\x20(100%\x20bonus\x20for\x20match-5+)','transform','flex','You\x20Lose!','gameState','handleMatch\x20completed,\x20damage\x20dealt:\x20','\x20uses\x20Minor\x20Regen,\x20restoring\x20','NFT_Unknown_','clientY','handleMouseMove','badMove','.png','addEventListener',')\x20has\x20no\x20element\x20to\x20animate','updatePlayerDisplay','Opponent','Error\x20updating\x20theme\x20assets:','Bite','translate(','\x20uses\x20Last\x20Stand,\x20dealing\x20','Parsed\x20response:','createDocumentFragment','Starting\x20Level\x20',',\x20cols\x20','ajax/save-monstrocity-score.php','Raw\x20response\x20text:','NFT\x20timeout',',\x20Match\x20bonus:\x20','Ouchie','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','p1-strength','\x27s\x20Turn','Leader','false','Error\x20playing\x20lose\x20sound:','384010qlfNtP','tileTypes','<p>Health:\x20','status','tileSizeWithGap','includes','Calling\x20checkGameOver\x20from\x20handleMatch','https://www.skulliance.io/staking/sounds/voice_go.ogg','click','Base\x20damage:\x20','\x27s\x20','Starting\x20fresh\x20at\x20Level\x201','dataset','transform\x200.2s\x20ease','querySelector','onload','Craig','\x20-\x20','translate(0px,\x200px)','items','Player','Resume\x20from\x20Level\x20','px)','preventDefault',',\x20healthPercentage=','createRandomTile','\x20defeats\x20','Error\x20loading\x20progress:','\x20but\x20sharpens\x20tactics\x20to\x20','Progress\x20saved:\x20Level\x20','\x20size\x20','https://www.skulliance.io/staking/sounds/select.ogg','\x20on\x20','center','resolveMatches',',\x20Matches:\x20','Error\x20clearing\x20progress:','mousemove','ipfs','animating','<p>Speed:\x20','progress-message','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20',',\x20Completions:\x20','Failed\x20to\x20preload:\x20','maxHealth','<p>Tactics:\x20','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','Failed\x20to\x20save\x20progress:','leader','toLowerCase','p2-image','playerCharactersConfig','flatMap','\x22\x20alt=\x22','isInitialMove:','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','game-over-container','setBackground','Left','\x20/\x20','Merdock','getItem','px,\x20','split','translate(0,\x200)','lastChild','Progress\x20saved:\x20currentLevel=','Boost\x20applied,\x20damage:\x20','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','height','Game\x20over,\x20skipping\x20endTurn','boostValue','swapPlayerCharacter','matches','5418ohwAoe','add','Score\x20Not\x20Saved:\x20','handleTouchMove','backgroundPosition','json','showProgressPopup:\x20User\x20chose\x20Restart','Final\x20level\x20completed!\x20Final\x20score:\x20','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20','find','Base','\x20(opponentsConfig[','totalTiles','display','Game\x20over,\x20skipping\x20cascadeTiles','...','Resume','push','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','isNFT','ajax/load-monstrocity-progress.php','random','race','scaleX(-1)','renderBoard','imageUrl','removeEventListener','completed','handleMatch','theme','first-attack','second-attack','board','s\x20linear','checkGameOver','No\x20progress\x20found\x20or\x20status\x20not\x20success:','alt','endTurn',',\x20reduced\x20by\x20','Is\x20initial\x20move:\x20','onerror',',\x20player2.health=','Monstrocity\x20HTTP\x20error!\x20Status:\x20','handleMatch\x20started,\x20match:','Error\x20saving\x20progress:','innerHTML','text','Drake','coordinates','getAssets:\x20Theme\x20not\x20found:\x20','match','158390ALiflf','img','aiTurn',',\x20player1.health=','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','policyIds','querySelectorAll','getAssets:\x20Monstrocity\x20data=','block','handleGameOverButton\x20completed:\x20currentLevel=','gameTheme','row','loss','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>'];_0x2afa=function(){return _0x8e3e1a;};return _0x2afa();}(function(_0x4835c4,_0x7b46e4){const _0x352bdd=_0x1cfe,_0x54a97a=_0x4835c4();while(!![]){try{const _0x4addc2=-parseInt(_0x352bdd(0x174))/0x1+-parseInt(_0x352bdd(0x281))/0x2*(-parseInt(_0x352bdd(0x231))/0x3)+-parseInt(_0x352bdd(0x247))/0x4*(-parseInt(_0x352bdd(0x207))/0x5)+-parseInt(_0x352bdd(0x1f1))/0x6*(parseInt(_0x352bdd(0x19a))/0x7)+parseInt(_0x352bdd(0x234))/0x8*(parseInt(_0x352bdd(0x141))/0x9)+-parseInt(_0x352bdd(0xf6))/0xa*(-parseInt(_0x352bdd(0x19d))/0xb)+-parseInt(_0x352bdd(0xc4))/0xc*(parseInt(_0x352bdd(0x23a))/0xd);if(_0x4addc2===_0x7b46e4)break;else _0x54a97a['push'](_0x54a97a['shift']());}catch(_0x766928){_0x54a97a['push'](_0x54a97a['shift']());}}}(_0x2afa,0xd7cb0));function showThemeSelect(_0x1003d7){const _0x44a56d=_0x1cfe;console[_0x44a56d(0x9a)](_0x44a56d(0x25c));let _0x2c6fca=document['getElementById']('theme-select-container');const _0x5d1370=document[_0x44a56d(0x24b)](_0x44a56d(0x208));_0x2c6fca['innerHTML']='\x0a\x09\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20<button\x20id=\x22theme-close-button\x22>Close</button>\x0a\x09\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20';const _0x99a7bf=document[_0x44a56d(0x24b)](_0x44a56d(0x1a9));_0x2c6fca[_0x44a56d(0x1b7)][_0x44a56d(0x14e)]=_0x44a56d(0x17c),_0x5d1370[_0x44a56d(0x1b7)]['display']=_0x44a56d(0x269),themes['forEach'](_0x48f9f8=>{const _0x4b11ec=_0x44a56d,_0x578f49=document[_0x4b11ec(0x241)]('div');_0x578f49['className']='theme-group';const _0x498134=document['createElement']('h3');_0x498134['textContent']=_0x48f9f8[_0x4b11ec(0x1df)],_0x578f49['appendChild'](_0x498134),_0x48f9f8[_0x4b11ec(0x109)][_0x4b11ec(0x1b3)](_0xc5a6fb=>{const _0x5a67e4=_0x4b11ec,_0x3a0c6e=document[_0x5a67e4(0x241)](_0x5a67e4(0x1d1));_0x3a0c6e['className']='theme-option';if(_0xc5a6fb[_0x5a67e4(0x1a4)]){const _0x596150=_0x5a67e4(0x1e5)+_0xc5a6fb[_0x5a67e4(0x1ec)]+'/monstrocity.png';_0x3a0c6e[_0x5a67e4(0x1b7)][_0x5a67e4(0x224)]=_0x5a67e4(0x26d)+_0x596150+')';}const _0x3b756d='https://www.skulliance.io/staking/images/monstrocity/'+_0xc5a6fb['value']+_0x5a67e4(0x1a0);_0x3a0c6e[_0x5a67e4(0x16e)]=_0x5a67e4(0xb1)+_0x3b756d+_0x5a67e4(0x12c)+_0xc5a6fb[_0x5a67e4(0x1bc)]+_0x5a67e4(0xd0)+_0xc5a6fb['project']+_0x5a67e4(0x1d9)+_0xc5a6fb[_0x5a67e4(0x1bc)]+_0x5a67e4(0x149),_0x3a0c6e[_0x5a67e4(0xdf)](_0x5a67e4(0xfe),()=>{const _0x48e79d=_0x5a67e4,_0x122a4c=document['getElementById'](_0x48e79d(0x192));_0x122a4c&&(_0x122a4c[_0x48e79d(0x16e)]=_0x48e79d(0x181)),_0x2c6fca[_0x48e79d(0x16e)]='',_0x2c6fca[_0x48e79d(0x1b7)]['display']=_0x48e79d(0x269),_0x5d1370[_0x48e79d(0x1b7)][_0x48e79d(0x14e)]=_0x48e79d(0x17c),_0x1003d7[_0x48e79d(0x1a2)](_0xc5a6fb['value']);}),_0x578f49[_0x5a67e4(0x279)](_0x3a0c6e);}),_0x99a7bf['appendChild'](_0x578f49);}),document[_0x44a56d(0x24b)](_0x44a56d(0x191))[_0x44a56d(0x28a)]=()=>{const _0x2f3cab=_0x44a56d,_0x13dc24=document[_0x2f3cab(0x24b)](_0x2f3cab(0x192));_0x13dc24&&(_0x13dc24[_0x2f3cab(0x16e)]=''),_0x2c6fca['innerHTML']='',_0x2c6fca['style'][_0x2f3cab(0x14e)]=_0x2f3cab(0x269),_0x5d1370[_0x2f3cab(0x1b7)][_0x2f3cab(0x14e)]=_0x2f3cab(0x17c);},console[_0x44a56d(0x1b4)](_0x44a56d(0x25c));}const opponentsConfig=[{'name':_0x5ca7c6(0x106),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x193),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x133),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x5ca7c6(0x198),'type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x193),'theme':'monstrocity'},{'name':'Goblin\x20Ganger','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x5ca7c6(0x2a7),'type':_0x5ca7c6(0x14b),'powerup':'Minor\x20Regen','theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x9d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x193),'theme':_0x5ca7c6(0x1e1)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x5ca7c6(0x187),'type':'Base','powerup':_0x5ca7c6(0xbd),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x1e9),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0xbd),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x213),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x5ca7c6(0x2a7),'type':'Base','powerup':_0x5ca7c6(0xbd),'theme':'monstrocity'},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0xbd),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x293),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x5ca7c6(0x187),'type':'Base','powerup':'Boost\x20Attack','theme':'monstrocity'},{'name':_0x5ca7c6(0x1a7),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x5ca7c6(0x187),'type':'Base','powerup':_0x5ca7c6(0x26e),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x9c),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x245),'theme':'monstrocity'},{'name':'Katastrophy','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x5ca7c6(0x198),'type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x245),'theme':_0x5ca7c6(0x1e1)},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x245),'theme':_0x5ca7c6(0x1e1)},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x5ca7c6(0x14b),'powerup':_0x5ca7c6(0x245),'theme':'monstrocity'},{'name':'Craig','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x5ca7c6(0x187),'type':'Leader','powerup':'Minor\x20Regen','theme':'monstrocity'},{'name':_0x5ca7c6(0x133),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x5ca7c6(0x198),'type':'Leader','powerup':_0x5ca7c6(0x193),'theme':'monstrocity'},{'name':'Goblin\x20Ganger','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x5ca7c6(0x2a7),'type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0x193),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x9d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Medium','type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0xa4),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x28f),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0xbd),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x1e9),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0xbd),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x213),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x5ca7c6(0x2a7),'type':_0x5ca7c6(0xf3),'powerup':'Regenerate','theme':_0x5ca7c6(0x1e1)},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0x5ca7c6(0xf3),'powerup':'Regenerate','theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x293),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0xf3),'powerup':'Boost\x20Attack','theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x1a7),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0x26e),'theme':_0x5ca7c6(0x1e1)},{'name':'Spydrax','strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x5ca7c6(0x2a7),'type':_0x5ca7c6(0xf3),'powerup':'Heal','theme':'monstrocity'},{'name':_0x5ca7c6(0xa8),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x5ca7c6(0x198),'type':_0x5ca7c6(0xf3),'powerup':'Heal','theme':'monstrocity'},{'name':_0x5ca7c6(0xef),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x5ca7c6(0x187),'type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0x245),'theme':_0x5ca7c6(0x1e1)},{'name':_0x5ca7c6(0x170),'strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x5ca7c6(0xf3),'powerup':_0x5ca7c6(0x245),'theme':'monstrocity'}],characterDirections={'Billandar\x20and\x20Ted':_0x5ca7c6(0x131),'Craig':_0x5ca7c6(0x131),'Dankle':_0x5ca7c6(0x131),'Drake':_0x5ca7c6(0x86),'Goblin\x20Ganger':_0x5ca7c6(0x131),'Jarhead':_0x5ca7c6(0x86),'Katastrophy':_0x5ca7c6(0x86),'Koipon':'Left','Mandiblus':_0x5ca7c6(0x131),'Merdock':_0x5ca7c6(0x131),'Ouchie':'Left','Slime\x20Mind':_0x5ca7c6(0x86),'Spydrax':_0x5ca7c6(0x86),'Texby':_0x5ca7c6(0x131)};class MonstrocityMatch3{constructor(_0x46d658,_0x51221f){const _0x2a64f8=_0x5ca7c6;this['isTouchDevice']='ontouchstart'in window||navigator[_0x2a64f8(0x29b)]>0x0||navigator[_0x2a64f8(0x195)]>0x0,this['width']=0x5,this['height']=0x5,this[_0x2a64f8(0x161)]=[],this[_0x2a64f8(0xcf)]=null,this[_0x2a64f8(0xa2)]=![],this[_0x2a64f8(0x1bf)]=null,this[_0x2a64f8(0x20d)]=null,this[_0x2a64f8(0x2a3)]=null,this[_0x2a64f8(0xd7)]='initializing',this[_0x2a64f8(0x203)]=![],this[_0x2a64f8(0x285)]=null,this['dragDirection']=null,this[_0x2a64f8(0x1af)]=0x0,this[_0x2a64f8(0x283)]=0x0,this['currentLevel']=0x1,this[_0x2a64f8(0x12a)]=_0x46d658,this['playerCharacters']=[],this[_0x2a64f8(0x274)]=![],this['tileTypes']=[_0x2a64f8(0x15f),_0x2a64f8(0x160),_0x2a64f8(0x182),_0x2a64f8(0xc9),_0x2a64f8(0x2aa)],this[_0x2a64f8(0x288)]=[],this[_0x2a64f8(0x21c)]=0x0;const _0xd43d0a=themes[_0x2a64f8(0x12b)](_0x13e77d=>_0x13e77d[_0x2a64f8(0x109)])[_0x2a64f8(0x92)](_0x271415=>_0x271415[_0x2a64f8(0x1ec)]),_0x3d852b=localStorage['getItem']('gameTheme');this[_0x2a64f8(0x15e)]=_0x3d852b&&_0xd43d0a[_0x2a64f8(0xfb)](_0x3d852b)?_0x3d852b:_0x51221f&&_0xd43d0a[_0x2a64f8(0xfb)](_0x51221f)?_0x51221f:_0x2a64f8(0x1e1),console[_0x2a64f8(0x249)]('constructor:\x20initialTheme='+_0x51221f+',\x20storedTheme='+_0x3d852b+',\x20selected\x20theme='+this[_0x2a64f8(0x15e)]),this[_0x2a64f8(0x23c)]=_0x2a64f8(0x1e5)+this[_0x2a64f8(0x15e)]+'/',this['sounds']={'match':new Audio(_0x2a64f8(0x115)),'cascade':new Audio(_0x2a64f8(0x115)),'badMove':new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),'gameOver':new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),'reset':new Audio(_0x2a64f8(0xfd)),'loss':new Audio(_0x2a64f8(0x125)),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio(_0x2a64f8(0x1a8)),'powerGem':new Audio('https://www.skulliance.io/staking/sounds/powergem_created.ogg'),'hyperCube':new Audio('https://www.skulliance.io/staking/sounds/hypercube_create.ogg'),'multiMatch':new Audio(_0x2a64f8(0x1d2))},this['updateTileSizeWithGap'](),this['addEventListeners']();}async[_0x5ca7c6(0xb0)](){const _0x183042=_0x5ca7c6;console[_0x183042(0x249)](_0x183042(0xc3)),this['playerCharacters']=this[_0x183042(0x12a)][_0x183042(0x92)](_0x4a9043=>this[_0x183042(0x2a5)](_0x4a9043)),await this[_0x183042(0x25d)](!![]);const _0x1d2e8c=await this[_0x183042(0x230)](),{loadedLevel:_0x499796,loadedScore:_0x5b85fa,hasProgress:_0x21adff}=_0x1d2e8c;if(_0x21adff){console[_0x183042(0x249)]('init:\x20Prompting\x20with\x20loadedLevel='+_0x499796+_0x183042(0x205)+_0x5b85fa);const _0x257c88=await this[_0x183042(0x210)](_0x499796,_0x5b85fa);_0x257c88?(this[_0x183042(0xcd)]=_0x499796,this[_0x183042(0x21c)]=_0x5b85fa,log('Resumed\x20at\x20Level\x20'+this[_0x183042(0xcd)]+_0x183042(0xa6)+this[_0x183042(0x21c)])):(this[_0x183042(0xcd)]=0x1,this[_0x183042(0x21c)]=0x0,await this['clearProgress'](),log(_0x183042(0x101)));}else this['currentLevel']=0x1,this[_0x183042(0x21c)]=0x0,log(_0x183042(0x21d));console[_0x183042(0x249)](_0x183042(0x287));}[_0x5ca7c6(0x130)](){const _0x580dd3=_0x5ca7c6;console[_0x580dd3(0x249)](_0x580dd3(0x1a1)+this[_0x580dd3(0x15e)]);const _0x58f8ad=themes[_0x580dd3(0x12b)](_0x3e5ab0=>_0x3e5ab0[_0x580dd3(0x109)])[_0x580dd3(0x14a)](_0x4be28e=>_0x4be28e[_0x580dd3(0x1ec)]===this[_0x580dd3(0x15e)]);console[_0x580dd3(0x249)]('setBackground:\x20themeData=',_0x58f8ad);const _0x47ad2a=_0x580dd3(0x1e5)+this[_0x580dd3(0x15e)]+'/monstrocity.png';console[_0x580dd3(0x249)]('setBackground:\x20Setting\x20background\x20to\x20'+_0x47ad2a),_0x58f8ad&&_0x58f8ad[_0x580dd3(0x1a4)]?(document[_0x580dd3(0x8c)][_0x580dd3(0x1b7)][_0x580dd3(0x224)]=_0x580dd3(0x26d)+_0x47ad2a+')',document['body']['style'][_0x580dd3(0x2a8)]=_0x580dd3(0x28c),document[_0x580dd3(0x8c)][_0x580dd3(0x1b7)][_0x580dd3(0x145)]=_0x580dd3(0x117)):document[_0x580dd3(0x8c)][_0x580dd3(0x1b7)][_0x580dd3(0x224)]='none';}[_0x5ca7c6(0x1a2)](_0x1dafb1){const _0x7fcb54=_0x5ca7c6;if(updatePending){console[_0x7fcb54(0x249)](_0x7fcb54(0x25e));return;}updatePending=!![],console['time'](_0x7fcb54(0x20c)+_0x1dafb1);var _0x7001ac=this;this[_0x7fcb54(0x15e)]=_0x1dafb1,this[_0x7fcb54(0x23c)]=_0x7fcb54(0x1e5)+this[_0x7fcb54(0x15e)]+'/',localStorage['setItem'](_0x7fcb54(0x17e),this[_0x7fcb54(0x15e)]),this[_0x7fcb54(0x130)](),getAssets(this[_0x7fcb54(0x15e)])[_0x7fcb54(0x223)](function(_0x504dfe){const _0x2353de=_0x7fcb54;console[_0x2353de(0x9a)](_0x2353de(0x252)+_0x1dafb1),_0x7001ac[_0x2353de(0x12a)]=_0x504dfe,_0x7001ac[_0x2353de(0x1eb)]=[],_0x504dfe[_0x2353de(0x1b3)](_0x33c5dd=>{const _0x38c8f1=_0x2353de,_0x20db48=_0x7001ac[_0x38c8f1(0x2a5)](_0x33c5dd),_0x25a6de=new Image();_0x25a6de[_0x38c8f1(0x253)]=_0x20db48[_0x38c8f1(0x15a)],_0x25a6de[_0x38c8f1(0x105)]=()=>console[_0x38c8f1(0x249)]('Preloaded:\x20'+_0x20db48[_0x38c8f1(0x15a)]),_0x25a6de[_0x38c8f1(0x169)]=()=>console['log'](_0x38c8f1(0x122)+_0x20db48['imageUrl']),_0x7001ac['playerCharacters'][_0x38c8f1(0x152)](_0x20db48);});if(_0x7001ac[_0x2353de(0x20d)]){var _0x345f4e=_0x7001ac['playerCharactersConfig']['find'](function(_0x14b971){const _0x17db6f=_0x2353de;return _0x14b971[_0x17db6f(0x24f)]===_0x7001ac[_0x17db6f(0x20d)][_0x17db6f(0x24f)];})||_0x7001ac[_0x2353de(0x12a)][0x0];_0x7001ac['player1']=_0x7001ac['createCharacter'](_0x345f4e),_0x7001ac[_0x2353de(0xe1)]();}_0x7001ac['player2']&&(_0x7001ac[_0x2353de(0x2a3)]=_0x7001ac[_0x2353de(0x2a5)](opponentsConfig[_0x7001ac[_0x2353de(0xcd)]-0x1]),_0x7001ac[_0x2353de(0x254)]());document[_0x2353de(0x104)]('.game-logo')['src']=_0x7001ac[_0x2353de(0x23c)]+'logo.png';var _0x58f57d=document['getElementById']('character-select-container');_0x58f57d[_0x2353de(0x1b7)][_0x2353de(0x14e)]===_0x2353de(0x17c)&&_0x7001ac[_0x2353de(0x25d)](_0x7001ac[_0x2353de(0x20d)]===null),console[_0x2353de(0x1b4)]('updateCharacters_'+_0x1dafb1),console[_0x2353de(0x1b4)](_0x2353de(0x20c)+_0x1dafb1),updatePending=![];})[_0x7fcb54(0x22c)](function(_0x5e2943){const _0x339595=_0x7fcb54;console[_0x339595(0x209)](_0x339595(0xe3),_0x5e2943),console['timeEnd'](_0x339595(0x20c)+_0x1dafb1),updatePending=![];});}async[_0x5ca7c6(0x238)](){const _0x5eefb5=_0x5ca7c6,_0x5ab257={'currentLevel':this[_0x5eefb5(0xcd)],'grandTotalScore':this[_0x5eefb5(0x21c)]};console[_0x5eefb5(0x249)](_0x5eefb5(0x1e0),_0x5ab257);try{const _0x1a7a7c=await fetch(_0x5eefb5(0x23f),{'method':_0x5eefb5(0x212),'headers':{'Content-Type':_0x5eefb5(0xd2)},'body':JSON[_0x5eefb5(0x270)](_0x5ab257)});console[_0x5eefb5(0x249)](_0x5eefb5(0x2ae),_0x1a7a7c[_0x5eefb5(0xf9)]);const _0x27f5f7=await _0x1a7a7c[_0x5eefb5(0x16f)]();console[_0x5eefb5(0x249)](_0x5eefb5(0xec),_0x27f5f7);if(!_0x1a7a7c['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x1a7a7c[_0x5eefb5(0xf9)]);const _0x1be155=JSON['parse'](_0x27f5f7);console[_0x5eefb5(0x249)]('Parsed\x20response:',_0x1be155),_0x1be155[_0x5eefb5(0xf9)]==='success'?log(_0x5eefb5(0x113)+this[_0x5eefb5(0xcd)]):console['error'](_0x5eefb5(0x126),_0x1be155[_0x5eefb5(0x1c4)]);}catch(_0x3a4140){console[_0x5eefb5(0x209)](_0x5eefb5(0x16d),_0x3a4140);}}async['loadProgress'](){const _0x1dd9d9=_0x5ca7c6;try{console[_0x1dd9d9(0x249)](_0x1dd9d9(0x94));const _0x97fc23=await fetch(_0x1dd9d9(0x155),{'method':_0x1dd9d9(0x1e7),'headers':{'Content-Type':_0x1dd9d9(0xd2)}});console[_0x1dd9d9(0x249)](_0x1dd9d9(0x2ae),_0x97fc23['status']);if(!_0x97fc23['ok'])throw new Error(_0x1dd9d9(0x18b)+_0x97fc23[_0x1dd9d9(0xf9)]);const _0x24f4db=await _0x97fc23['json']();console[_0x1dd9d9(0x249)](_0x1dd9d9(0xe7),_0x24f4db);if(_0x24f4db['status']===_0x1dd9d9(0x28b)&&_0x24f4db['progress']){const _0x4dddd9=_0x24f4db[_0x1dd9d9(0x1f0)];return{'loadedLevel':_0x4dddd9[_0x1dd9d9(0xcd)]||0x1,'loadedScore':_0x4dddd9[_0x1dd9d9(0x21c)]||0x0,'hasProgress':!![]};}else return console[_0x1dd9d9(0x249)](_0x1dd9d9(0x164),_0x24f4db),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x1a5ba3){return console[_0x1dd9d9(0x209)](_0x1dd9d9(0x111),_0x1a5ba3),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x5ca7c6(0x186)](){const _0x15b762=_0x5ca7c6;try{const _0x448fbc=await fetch('ajax/clear-monstrocity-progress.php',{'method':'POST','headers':{'Content-Type':_0x15b762(0xd2)}});if(!_0x448fbc['ok'])throw new Error(_0x15b762(0x18b)+_0x448fbc[_0x15b762(0xf9)]);const _0x933855=await _0x448fbc['json']();_0x933855[_0x15b762(0xf9)]===_0x15b762(0x28b)&&(this[_0x15b762(0xcd)]=0x1,this[_0x15b762(0x21c)]=0x0,log(_0x15b762(0x8e)));}catch(_0x4e2421){console[_0x15b762(0x209)](_0x15b762(0x11a),_0x4e2421);}}[_0x5ca7c6(0x1ca)](){const _0x53ba40=_0x5ca7c6,_0x85b3ce=document[_0x53ba40(0x24b)](_0x53ba40(0xcc)),_0xf035d7=_0x85b3ce[_0x53ba40(0x24a)]||0x12c;this[_0x53ba40(0xfa)]=(_0xf035d7-0.5*(this[_0x53ba40(0x9b)]-0x1))/this['width'];}[_0x5ca7c6(0x2a5)](_0x54a3be){const _0x4d98ad=_0x5ca7c6;console[_0x4d98ad(0x249)](_0x4d98ad(0x1cd),_0x54a3be);var _0x4c8667,_0x69a3cc,_0x4714f5='Left',_0x471def=![];if(_0x54a3be[_0x4d98ad(0x11c)]&&_0x54a3be[_0x4d98ad(0xc2)]){_0x471def=!![];var _0x1790dc=document[_0x4d98ad(0x104)]('#theme-select\x20option[value=\x22'+_0x54a3be[_0x4d98ad(0x15e)]+'\x22]'),_0x5a3e7c={'orientation':'Right','ipfsPrefix':_0x4d98ad(0x1c3)};if(_0x1790dc){var _0x331596=_0x1790dc[_0x4d98ad(0x102)][_0x4d98ad(0x179)]?_0x1790dc[_0x4d98ad(0x102)]['policyIds'][_0x4d98ad(0x136)](',')[_0x4d98ad(0x1a5)](function(_0x3209d9){const _0x573b7b=_0x4d98ad;return _0x3209d9[_0x573b7b(0x201)]();}):[],_0x400488=_0x1790dc['dataset'][_0x4d98ad(0x290)]?_0x1790dc[_0x4d98ad(0x102)][_0x4d98ad(0x290)][_0x4d98ad(0x136)](',')[_0x4d98ad(0x1a5)](function(_0x260842){const _0x35d135=_0x4d98ad;return _0x260842[_0x35d135(0x201)]();}):[],_0x532ac4=_0x1790dc[_0x4d98ad(0x102)][_0x4d98ad(0x188)]?_0x1790dc[_0x4d98ad(0x102)][_0x4d98ad(0x188)][_0x4d98ad(0x136)](',')[_0x4d98ad(0x1a5)](function(_0x13d51d){const _0x26fd62=_0x4d98ad;return _0x13d51d[_0x26fd62(0x201)]();}):[],_0x5add26=_0x331596['indexOf'](_0x54a3be[_0x4d98ad(0xc2)]);_0x5add26!==-0x1&&(_0x5a3e7c={'orientation':_0x400488[_0x4d98ad(0x1de)]===0x1?_0x400488[0x0]:_0x400488[_0x5add26]||_0x4d98ad(0x86),'ipfsPrefix':_0x532ac4[_0x4d98ad(0x1de)]===0x1?_0x532ac4[0x0]:_0x532ac4[_0x5add26]||_0x4d98ad(0x1c3)});}_0x5a3e7c[_0x4d98ad(0x251)]===_0x4d98ad(0x244)?_0x4714f5=Math['random']()<0.5?_0x4d98ad(0x131):_0x4d98ad(0x86):_0x4714f5=_0x5a3e7c[_0x4d98ad(0x251)],_0x69a3cc=_0x5a3e7c[_0x4d98ad(0xaf)]+_0x54a3be[_0x4d98ad(0x11c)];}else{switch(_0x54a3be[_0x4d98ad(0x1dc)]){case _0x4d98ad(0x14b):_0x4c8667='base';break;case _0x4d98ad(0xf3):_0x4c8667=_0x4d98ad(0x127);break;case'Battle\x20Damaged':_0x4c8667=_0x4d98ad(0x19b);break;default:_0x4c8667=_0x4d98ad(0x292);}_0x69a3cc=this[_0x4d98ad(0x23c)]+_0x4c8667+'/'+_0x54a3be[_0x4d98ad(0x24f)][_0x4d98ad(0x128)]()[_0x4d98ad(0x262)](/ /g,'-')+_0x4d98ad(0xde),_0x4714f5=characterDirections[_0x54a3be[_0x4d98ad(0x24f)]]||_0x4d98ad(0x131);}var _0x52c4db;switch(_0x54a3be[_0x4d98ad(0x1dc)]){case _0x4d98ad(0xf3):_0x52c4db=0x64;break;case _0x4d98ad(0xb6):_0x52c4db=0x46;break;case _0x4d98ad(0x14b):default:_0x52c4db=0x55;}var _0x5a7c60=0x1,_0x4aca9c=0x0;switch(_0x54a3be['size']){case _0x4d98ad(0x198):_0x5a7c60=1.2,_0x4aca9c=_0x54a3be[_0x4d98ad(0x25f)]>0x1?-0x2:0x0;break;case _0x4d98ad(0x2a7):_0x5a7c60=0.8,_0x4aca9c=_0x54a3be[_0x4d98ad(0x25f)]<0x6?0x2:0x7-_0x54a3be['tactics'];break;case _0x4d98ad(0x187):_0x5a7c60=0x1,_0x4aca9c=0x0;break;}var _0x27e5fa=Math[_0x4d98ad(0x2a1)](_0x52c4db*_0x5a7c60),_0x2c8f91=Math[_0x4d98ad(0x91)](0x1,Math[_0x4d98ad(0xc8)](0x7,_0x54a3be['tactics']+_0x4aca9c));return{'name':_0x54a3be[_0x4d98ad(0x24f)],'type':_0x54a3be['type'],'strength':_0x54a3be[_0x4d98ad(0x18c)],'speed':_0x54a3be['speed'],'tactics':_0x2c8f91,'size':_0x54a3be[_0x4d98ad(0x235)],'powerup':_0x54a3be[_0x4d98ad(0xa3)],'health':_0x27e5fa,'maxHealth':_0x27e5fa,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x69a3cc,'orientation':_0x4714f5,'isNFT':_0x471def};}[_0x5ca7c6(0x1ff)](_0xf8a8ea,_0xd8fa6e,_0x16219c=![]){const _0x471b09=_0x5ca7c6;_0xf8a8ea['orientation']===_0x471b09(0x131)?(_0xf8a8ea[_0x471b09(0x251)]=_0x471b09(0x86),_0xd8fa6e[_0x471b09(0x1b7)][_0x471b09(0xd4)]=_0x16219c?_0x471b09(0x158):_0x471b09(0x269)):(_0xf8a8ea[_0x471b09(0x251)]=_0x471b09(0x131),_0xd8fa6e[_0x471b09(0x1b7)]['transform']=_0x16219c?_0x471b09(0x269):_0x471b09(0x158)),log(_0xf8a8ea[_0x471b09(0x24f)]+_0x471b09(0x24d)+_0xf8a8ea[_0x471b09(0x251)]+'!');}['showCharacterSelect'](_0x4c8f3d){const _0x514e25=_0x5ca7c6;var _0xbacc8e=this;console[_0x514e25(0x9a)](_0x514e25(0x25d));var _0x2041cc=document[_0x514e25(0x24b)](_0x514e25(0x208)),_0x1901ac=document['getElementById'](_0x514e25(0x192));_0x1901ac[_0x514e25(0x16e)]='',_0x2041cc[_0x514e25(0x1b7)]['display']=_0x514e25(0x17c),document[_0x514e25(0x24b)](_0x514e25(0x190))[_0x514e25(0x28a)]=()=>{showThemeSelect(_0xbacc8e);};const _0x55203a=document[_0x514e25(0xe8)]();this[_0x514e25(0x1eb)][_0x514e25(0x1b3)](function(_0x943958){const _0xe33a10=_0x514e25;var _0x5f1e2c=document[_0xe33a10(0x241)]('div');_0x5f1e2c[_0xe33a10(0x21f)]=_0xe33a10(0x219),_0x5f1e2c[_0xe33a10(0x16e)]=_0xe33a10(0x242)+_0x943958[_0xe33a10(0x15a)]+_0xe33a10(0x12c)+_0x943958['name']+'\x22>'+_0xe33a10(0x185)+_0x943958[_0xe33a10(0x24f)]+_0xe33a10(0xab)+_0xe33a10(0x2ab)+_0x943958[_0xe33a10(0x1dc)]+_0xe33a10(0x226)+_0xe33a10(0xf8)+_0x943958['maxHealth']+_0xe33a10(0x226)+_0xe33a10(0x20e)+_0x943958[_0xe33a10(0x18c)]+_0xe33a10(0x226)+_0xe33a10(0x11e)+_0x943958[_0xe33a10(0x18a)]+_0xe33a10(0x226)+_0xe33a10(0x124)+_0x943958[_0xe33a10(0x25f)]+_0xe33a10(0x226)+'<p>Size:\x20'+_0x943958['size']+_0xe33a10(0x226)+_0xe33a10(0x225)+_0x943958['powerup']+_0xe33a10(0x226),_0x5f1e2c['addEventListener'](_0xe33a10(0xfe),function(){const _0x39467a=_0xe33a10;console[_0x39467a(0x249)]('showCharacterSelect:\x20Character\x20selected:\x20'+_0x943958[_0x39467a(0x24f)]),_0x2041cc[_0x39467a(0x1b7)][_0x39467a(0x14e)]=_0x39467a(0x269),_0x4c8f3d?(_0xbacc8e[_0x39467a(0x20d)]={'name':_0x943958[_0x39467a(0x24f)],'type':_0x943958[_0x39467a(0x1dc)],'strength':_0x943958[_0x39467a(0x18c)],'speed':_0x943958['speed'],'tactics':_0x943958[_0x39467a(0x25f)],'size':_0x943958[_0x39467a(0x235)],'powerup':_0x943958[_0x39467a(0xa3)],'health':_0x943958[_0x39467a(0x216)],'maxHealth':_0x943958['maxHealth'],'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x943958[_0x39467a(0x15a)],'orientation':_0x943958['orientation'],'isNFT':_0x943958[_0x39467a(0x154)]},console[_0x39467a(0x249)]('showCharacterSelect:\x20this.player1\x20set:\x20'+_0xbacc8e['player1'][_0x39467a(0x24f)]),_0xbacc8e['initGame']()):_0xbacc8e[_0x39467a(0x13f)](_0x943958);}),_0x55203a[_0xe33a10(0x279)](_0x5f1e2c);}),_0x1901ac[_0x514e25(0x279)](_0x55203a),console[_0x514e25(0x1b4)](_0x514e25(0x25d));}['swapPlayerCharacter'](_0x15b2f7){const _0xf86f24=_0x5ca7c6,_0x3bb495=this['player1']['health'],_0xfb573=this[_0xf86f24(0x20d)][_0xf86f24(0x123)],_0x2a5642={..._0x15b2f7},_0x23c118=Math['min'](0x1,_0x3bb495/_0xfb573);_0x2a5642[_0xf86f24(0x216)]=Math[_0xf86f24(0x2a1)](_0x2a5642['maxHealth']*_0x23c118),_0x2a5642['health']=Math['max'](0x0,Math[_0xf86f24(0xc8)](_0x2a5642['maxHealth'],_0x2a5642[_0xf86f24(0x216)])),_0x2a5642['boostActive']=![],_0x2a5642[_0xf86f24(0x13e)]=0x0,_0x2a5642[_0xf86f24(0x27f)]=![],this[_0xf86f24(0x20d)]=_0x2a5642,this[_0xf86f24(0xe1)](),this[_0xf86f24(0xad)](this[_0xf86f24(0x20d)]),log(this[_0xf86f24(0x20d)]['name']+_0xf86f24(0x1e2)+this[_0xf86f24(0x20d)][_0xf86f24(0x216)]+'/'+this[_0xf86f24(0x20d)][_0xf86f24(0x123)]+'\x20HP!'),this[_0xf86f24(0x1bf)]=this['player1'][_0xf86f24(0x18a)]>this[_0xf86f24(0x2a3)][_0xf86f24(0x18a)]?this[_0xf86f24(0x20d)]:this[_0xf86f24(0x2a3)]['speed']>this['player1'][_0xf86f24(0x18a)]?this[_0xf86f24(0x2a3)]:this['player1'][_0xf86f24(0x18c)]>=this[_0xf86f24(0x2a3)]['strength']?this[_0xf86f24(0x20d)]:this[_0xf86f24(0x2a3)],turnIndicator['textContent']=_0xf86f24(0x1f2)+this[_0xf86f24(0xcd)]+_0xf86f24(0x107)+(this[_0xf86f24(0x1bf)]===this[_0xf86f24(0x20d)]?'Player':_0xf86f24(0xe2))+'\x27s\x20Turn',this[_0xf86f24(0x1bf)]===this['player2']&&this[_0xf86f24(0xd7)]!==_0xf86f24(0xa2)&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x5ca7c6(0x210)](_0x3a99d1,_0x5002ff){const _0x3dbf56=_0x5ca7c6;return console[_0x3dbf56(0x249)](_0x3dbf56(0x275)+_0x3a99d1+_0x3dbf56(0x97)+_0x5002ff),new Promise(_0x43135e=>{const _0x43a1ff=_0x3dbf56,_0x2fc2c7=document[_0x43a1ff(0x241)]('div');_0x2fc2c7['id']='progress-modal',_0x2fc2c7[_0x43a1ff(0x21f)]=_0x43a1ff(0xb9);const _0x5b9ad4=document['createElement'](_0x43a1ff(0x1d1));_0x5b9ad4['className']=_0x43a1ff(0x1ba);const _0x221d6f=document[_0x43a1ff(0x241)]('p');_0x221d6f['id']=_0x43a1ff(0x11f),_0x221d6f[_0x43a1ff(0x1d6)]=_0x43a1ff(0x10b)+_0x3a99d1+_0x43a1ff(0x88)+_0x5002ff+'?',_0x5b9ad4['appendChild'](_0x221d6f);const _0x4f6690=document['createElement'](_0x43a1ff(0x1d1));_0x4f6690[_0x43a1ff(0x21f)]=_0x43a1ff(0x1c1);const _0x5ab564=document[_0x43a1ff(0x241)]('button');_0x5ab564['id']='progress-resume',_0x5ab564['textContent']=_0x43a1ff(0x151),_0x4f6690['appendChild'](_0x5ab564);const _0x7dcd6=document[_0x43a1ff(0x241)](_0x43a1ff(0xc5));_0x7dcd6['id']=_0x43a1ff(0x1d7),_0x7dcd6['textContent']=_0x43a1ff(0x26c),_0x4f6690[_0x43a1ff(0x279)](_0x7dcd6),_0x5b9ad4[_0x43a1ff(0x279)](_0x4f6690),_0x2fc2c7[_0x43a1ff(0x279)](_0x5b9ad4),document[_0x43a1ff(0x8c)][_0x43a1ff(0x279)](_0x2fc2c7),_0x2fc2c7[_0x43a1ff(0x1b7)]['display']=_0x43a1ff(0xd5);const _0x5ca93b=()=>{const _0xb43f60=_0x43a1ff;console[_0xb43f60(0x249)](_0xb43f60(0x1da)),_0x2fc2c7[_0xb43f60(0x1b7)][_0xb43f60(0x14e)]=_0xb43f60(0x269),document['body']['removeChild'](_0x2fc2c7),_0x5ab564['removeEventListener'](_0xb43f60(0xfe),_0x5ca93b),_0x7dcd6[_0xb43f60(0x15b)](_0xb43f60(0xfe),_0x2265b5),_0x43135e(!![]);},_0x2265b5=()=>{const _0x3873fa=_0x43a1ff;console[_0x3873fa(0x249)](_0x3873fa(0x147)),_0x2fc2c7[_0x3873fa(0x1b7)][_0x3873fa(0x14e)]='none',document['body'][_0x3873fa(0x21e)](_0x2fc2c7),_0x5ab564[_0x3873fa(0x15b)]('click',_0x5ca93b),_0x7dcd6['removeEventListener'](_0x3873fa(0xfe),_0x2265b5),_0x43135e(![]);};_0x5ab564[_0x43a1ff(0xdf)](_0x43a1ff(0xfe),_0x5ca93b),_0x7dcd6[_0x43a1ff(0xdf)](_0x43a1ff(0xfe),_0x2265b5);});}[_0x5ca7c6(0xae)](){const _0x43658c=_0x5ca7c6;var _0x50e7b8=this;console[_0x43658c(0x249)]('initGame:\x20Started\x20with\x20this.currentLevel='+this[_0x43658c(0xcd)]);var _0x24dcfe=document[_0x43658c(0x104)](_0x43658c(0x271)),_0x1d3a57=document[_0x43658c(0x24b)](_0x43658c(0xcc));_0x24dcfe[_0x43658c(0x1b7)][_0x43658c(0x14e)]=_0x43658c(0x17c),_0x1d3a57['style'][_0x43658c(0x21b)]=_0x43658c(0xa7),this[_0x43658c(0x130)](),this[_0x43658c(0x1be)][_0x43658c(0x248)][_0x43658c(0x26b)](),log(_0x43658c(0xe9)+this[_0x43658c(0xcd)]+_0x43658c(0x150)),this['player2']=this['createCharacter'](opponentsConfig[this['currentLevel']-0x1]),console['log']('Loaded\x20opponent\x20for\x20level\x20'+this[_0x43658c(0xcd)]+':\x20'+this[_0x43658c(0x2a3)][_0x43658c(0x24f)]+_0x43658c(0x14c)+(this[_0x43658c(0xcd)]-0x1)+'])'),this[_0x43658c(0x20d)][_0x43658c(0x216)]=this[_0x43658c(0x20d)][_0x43658c(0x123)],this[_0x43658c(0x1bf)]=this[_0x43658c(0x20d)][_0x43658c(0x18a)]>this[_0x43658c(0x2a3)][_0x43658c(0x18a)]?this[_0x43658c(0x20d)]:this[_0x43658c(0x2a3)][_0x43658c(0x18a)]>this[_0x43658c(0x20d)][_0x43658c(0x18a)]?this[_0x43658c(0x2a3)]:this[_0x43658c(0x20d)]['strength']>=this[_0x43658c(0x2a3)][_0x43658c(0x18c)]?this[_0x43658c(0x20d)]:this['player2'],this[_0x43658c(0xd7)]=_0x43658c(0x1b6),this[_0x43658c(0xa2)]=![],this['roundStats']=[],p1Image[_0x43658c(0xa9)][_0x43658c(0x90)](_0x43658c(0x22b),_0x43658c(0x2a4)),p2Image[_0x43658c(0xa9)][_0x43658c(0x90)](_0x43658c(0x22b),'loser'),this[_0x43658c(0xe1)](),this[_0x43658c(0x254)](),p1Image[_0x43658c(0x1b7)][_0x43658c(0xd4)]=this[_0x43658c(0x20d)][_0x43658c(0x251)]===_0x43658c(0x131)?_0x43658c(0x158):_0x43658c(0x269),p2Image['style'][_0x43658c(0xd4)]=this[_0x43658c(0x2a3)][_0x43658c(0x251)]==='Right'?_0x43658c(0x158):_0x43658c(0x269),this['updateHealth'](this[_0x43658c(0x20d)]),this[_0x43658c(0xad)](this[_0x43658c(0x2a3)]),battleLog[_0x43658c(0x16e)]='',gameOver[_0x43658c(0x1d6)]='',this[_0x43658c(0x20d)][_0x43658c(0x235)]!==_0x43658c(0x187)&&log(this[_0x43658c(0x20d)][_0x43658c(0x24f)]+_0x43658c(0x100)+this[_0x43658c(0x20d)][_0x43658c(0x235)]+_0x43658c(0x114)+(this['player1'][_0x43658c(0x235)]===_0x43658c(0x198)?_0x43658c(0x1fc)+this[_0x43658c(0x20d)][_0x43658c(0x123)]+_0x43658c(0x2a6)+this['player1'][_0x43658c(0x25f)]:_0x43658c(0x1d5)+this[_0x43658c(0x20d)][_0x43658c(0x123)]+_0x43658c(0x112)+this[_0x43658c(0x20d)]['tactics'])+'!'),this[_0x43658c(0x2a3)]['size']!==_0x43658c(0x187)&&log(this[_0x43658c(0x2a3)][_0x43658c(0x24f)]+_0x43658c(0x100)+this[_0x43658c(0x2a3)][_0x43658c(0x235)]+'\x20size\x20'+(this['player2'][_0x43658c(0x235)]===_0x43658c(0x198)?'boosts\x20health\x20to\x20'+this[_0x43658c(0x2a3)][_0x43658c(0x123)]+'\x20but\x20dulls\x20tactics\x20to\x20'+this[_0x43658c(0x2a3)][_0x43658c(0x25f)]:_0x43658c(0x1d5)+this[_0x43658c(0x2a3)][_0x43658c(0x123)]+_0x43658c(0x112)+this['player2'][_0x43658c(0x25f)])+'!'),log(this[_0x43658c(0x20d)][_0x43658c(0x24f)]+'\x20starts\x20at\x20full\x20strength\x20with\x20'+this[_0x43658c(0x20d)][_0x43658c(0x216)]+'/'+this['player1'][_0x43658c(0x123)]+_0x43658c(0x24e)),log(this[_0x43658c(0x1bf)]['name']+_0x43658c(0x196)),this['initBoard'](),this[_0x43658c(0xd7)]=this[_0x43658c(0x1bf)]===this[_0x43658c(0x20d)]?_0x43658c(0x24c):_0x43658c(0x176),turnIndicator[_0x43658c(0x1d6)]=_0x43658c(0x1f2)+this[_0x43658c(0xcd)]+'\x20-\x20'+(this['currentTurn']===this[_0x43658c(0x20d)]?_0x43658c(0x10a):_0x43658c(0xe2))+_0x43658c(0xf2),this[_0x43658c(0x1eb)][_0x43658c(0x1de)]>0x1&&(document[_0x43658c(0x24b)](_0x43658c(0x1f5))[_0x43658c(0x1b7)]['display']=_0x43658c(0x1f3)),this[_0x43658c(0x1bf)]===this[_0x43658c(0x2a3)]&&setTimeout(function(){const _0x43cbf5=_0x43658c;_0x50e7b8[_0x43cbf5(0x176)]();},0x3e8);}['updatePlayerDisplay'](){const _0x1677ea=_0x5ca7c6;p1Name[_0x1677ea(0x1d6)]=this[_0x1677ea(0x20d)][_0x1677ea(0x154)]||this[_0x1677ea(0x15e)]==='monstrocity'?this[_0x1677ea(0x20d)][_0x1677ea(0x24f)]:_0x1677ea(0x18f),p1Type[_0x1677ea(0x1d6)]=this[_0x1677ea(0x20d)]['type'],p1Strength[_0x1677ea(0x1d6)]=this[_0x1677ea(0x20d)][_0x1677ea(0x18c)],p1Speed[_0x1677ea(0x1d6)]=this[_0x1677ea(0x20d)]['speed'],p1Tactics['textContent']=this[_0x1677ea(0x20d)][_0x1677ea(0x25f)],p1Size[_0x1677ea(0x1d6)]=this[_0x1677ea(0x20d)][_0x1677ea(0x235)],p1Powerup[_0x1677ea(0x1d6)]=this[_0x1677ea(0x20d)]['powerup'],p1Image[_0x1677ea(0x253)]=this[_0x1677ea(0x20d)][_0x1677ea(0x15a)],p1Image[_0x1677ea(0x1b7)]['transform']=this[_0x1677ea(0x20d)][_0x1677ea(0x251)]==='Left'?_0x1677ea(0x158):'none',p1Image[_0x1677ea(0x105)]=function(){const _0x5f00af=_0x1677ea;p1Image[_0x5f00af(0x1b7)][_0x5f00af(0x14e)]=_0x5f00af(0x17c);},p1Hp['textContent']=this[_0x1677ea(0x20d)]['health']+'/'+this[_0x1677ea(0x20d)][_0x1677ea(0x123)];}[_0x5ca7c6(0x254)](){const _0x38c6a6=_0x5ca7c6;p2Name[_0x38c6a6(0x1d6)]=this[_0x38c6a6(0x15e)]===_0x38c6a6(0x1e1)?this[_0x38c6a6(0x2a3)][_0x38c6a6(0x24f)]:_0x38c6a6(0x1c7),p2Type[_0x38c6a6(0x1d6)]=this['player2'][_0x38c6a6(0x1dc)],p2Strength[_0x38c6a6(0x1d6)]=this[_0x38c6a6(0x2a3)][_0x38c6a6(0x18c)],p2Speed[_0x38c6a6(0x1d6)]=this[_0x38c6a6(0x2a3)]['speed'],p2Tactics[_0x38c6a6(0x1d6)]=this[_0x38c6a6(0x2a3)]['tactics'],p2Size[_0x38c6a6(0x1d6)]=this[_0x38c6a6(0x2a3)]['size'],p2Powerup['textContent']=this['player2'][_0x38c6a6(0xa3)],p2Image[_0x38c6a6(0x253)]=this[_0x38c6a6(0x2a3)]['imageUrl'],p2Image[_0x38c6a6(0x1b7)]['transform']=this['player2'][_0x38c6a6(0x251)]===_0x38c6a6(0x86)?_0x38c6a6(0x158):_0x38c6a6(0x269),p2Image['onload']=function(){const _0x4cbd67=_0x38c6a6;p2Image[_0x4cbd67(0x1b7)][_0x4cbd67(0x14e)]='block';},p2Hp[_0x38c6a6(0x1d6)]=this[_0x38c6a6(0x2a3)][_0x38c6a6(0x216)]+'/'+this['player2'][_0x38c6a6(0x123)];}[_0x5ca7c6(0x21a)](){const _0x20ee3d=_0x5ca7c6;this[_0x20ee3d(0x161)]=[];for(let _0x3782b6=0x0;_0x3782b6<this['height'];_0x3782b6++){this[_0x20ee3d(0x161)][_0x3782b6]=[];for(let _0xb98778=0x0;_0xb98778<this[_0x20ee3d(0x9b)];_0xb98778++){let _0x2968c2;do{_0x2968c2=this[_0x20ee3d(0x10f)]();}while(_0xb98778>=0x2&&this['board'][_0x3782b6][_0xb98778-0x1]?.[_0x20ee3d(0x1dc)]===_0x2968c2['type']&&this['board'][_0x3782b6][_0xb98778-0x2]?.[_0x20ee3d(0x1dc)]===_0x2968c2[_0x20ee3d(0x1dc)]||_0x3782b6>=0x2&&this['board'][_0x3782b6-0x1]?.[_0xb98778]?.[_0x20ee3d(0x1dc)]===_0x2968c2[_0x20ee3d(0x1dc)]&&this[_0x20ee3d(0x161)][_0x3782b6-0x2]?.[_0xb98778]?.[_0x20ee3d(0x1dc)]===_0x2968c2[_0x20ee3d(0x1dc)]);this[_0x20ee3d(0x161)][_0x3782b6][_0xb98778]=_0x2968c2;}}this[_0x20ee3d(0x159)]();}[_0x5ca7c6(0x10f)](){const _0x56af5c=_0x5ca7c6;return{'type':randomChoice(this[_0x56af5c(0xf7)]),'element':null};}[_0x5ca7c6(0x159)](){const _0x13a3ae=_0x5ca7c6;this[_0x13a3ae(0x1ca)]();const _0xe7127c=document[_0x13a3ae(0x24b)]('game-board');_0xe7127c[_0x13a3ae(0x16e)]='';for(let _0x45b69a=0x0;_0x45b69a<this[_0x13a3ae(0x13c)];_0x45b69a++){for(let _0x3fbd51=0x0;_0x3fbd51<this[_0x13a3ae(0x9b)];_0x3fbd51++){const _0x5c6f09=this[_0x13a3ae(0x161)][_0x45b69a][_0x3fbd51];if(_0x5c6f09[_0x13a3ae(0x1dc)]===null)continue;const _0x37360e=document[_0x13a3ae(0x241)](_0x13a3ae(0x1d1));_0x37360e[_0x13a3ae(0x21f)]='tile\x20'+_0x5c6f09['type'];if(this[_0x13a3ae(0xa2)])_0x37360e[_0x13a3ae(0xa9)][_0x13a3ae(0x142)](_0x13a3ae(0x202));const _0x838aa6=document[_0x13a3ae(0x241)](_0x13a3ae(0x175));_0x838aa6[_0x13a3ae(0x253)]='https://www.skulliance.io/staking/icons/'+_0x5c6f09[_0x13a3ae(0x1dc)]+_0x13a3ae(0xde),_0x838aa6[_0x13a3ae(0x165)]=_0x5c6f09[_0x13a3ae(0x1dc)],_0x37360e[_0x13a3ae(0x279)](_0x838aa6),_0x37360e[_0x13a3ae(0x102)]['x']=_0x3fbd51,_0x37360e['dataset']['y']=_0x45b69a,_0xe7127c[_0x13a3ae(0x279)](_0x37360e),_0x5c6f09['element']=_0x37360e,(!this[_0x13a3ae(0x203)]||this[_0x13a3ae(0xcf)]&&(this['selectedTile']['x']!==_0x3fbd51||this[_0x13a3ae(0xcf)]['y']!==_0x45b69a))&&(_0x37360e[_0x13a3ae(0x1b7)][_0x13a3ae(0xd4)]=_0x13a3ae(0x137));}}document[_0x13a3ae(0x24b)]('game-over-container')[_0x13a3ae(0x1b7)][_0x13a3ae(0x14e)]=this[_0x13a3ae(0xa2)]?_0x13a3ae(0x17c):_0x13a3ae(0x269);}[_0x5ca7c6(0x18e)](){const _0x45785c=_0x5ca7c6,_0x292355=document[_0x45785c(0x24b)](_0x45785c(0xcc));this[_0x45785c(0x1ac)]?(_0x292355[_0x45785c(0xdf)](_0x45785c(0xb5),_0x497928=>this[_0x45785c(0x2ad)](_0x497928)),_0x292355[_0x45785c(0xdf)]('touchmove',_0x370708=>this[_0x45785c(0x144)](_0x370708)),_0x292355['addEventListener']('touchend',_0x1eea9f=>this['handleTouchEnd'](_0x1eea9f))):(_0x292355[_0x45785c(0xdf)](_0x45785c(0x8d),_0xc5371=>this[_0x45785c(0x239)](_0xc5371)),_0x292355['addEventListener'](_0x45785c(0x11b),_0x5e216e=>this['handleMouseMove'](_0x5e216e)),_0x292355[_0x45785c(0xdf)](_0x45785c(0x23b),_0x3038a6=>this[_0x45785c(0x215)](_0x3038a6)));document[_0x45785c(0x24b)](_0x45785c(0x206))[_0x45785c(0xdf)](_0x45785c(0xfe),()=>this[_0x45785c(0x27e)]()),document[_0x45785c(0x24b)](_0x45785c(0x291))[_0x45785c(0xdf)](_0x45785c(0xfe),()=>{const _0x37c9c8=_0x45785c;this[_0x37c9c8(0xae)]();});const _0x4a40b6=document[_0x45785c(0x24b)](_0x45785c(0x1f5)),_0x1eeceb=document[_0x45785c(0x24b)]('p1-image');_0x4a40b6[_0x45785c(0xdf)](_0x45785c(0xfe),()=>{const _0x4b9ae0=_0x45785c;console[_0x4b9ae0(0x249)](_0x4b9ae0(0x267)),this[_0x4b9ae0(0x25d)](![]);}),_0x1eeceb[_0x45785c(0xdf)]('click',()=>{const _0x5d022f=_0x45785c;console['log'](_0x5d022f(0x27b)),this[_0x5d022f(0x25d)](![]);}),document[_0x45785c(0x24b)](_0x45785c(0x1c9))[_0x45785c(0xdf)](_0x45785c(0xfe),()=>this[_0x45785c(0x1ff)](this[_0x45785c(0x20d)],_0x1eeceb,![])),document[_0x45785c(0x24b)](_0x45785c(0x229))['addEventListener'](_0x45785c(0xfe),()=>this['flipCharacter'](this[_0x45785c(0x2a3)],p2Image,!![]));}['handleGameOverButton'](){const _0x38a808=_0x5ca7c6;console[_0x38a808(0x249)](_0x38a808(0x27c)+this[_0x38a808(0xcd)]+_0x38a808(0x16a)+this[_0x38a808(0x2a3)]['health']),this[_0x38a808(0x2a3)][_0x38a808(0x216)]<=0x0&&this[_0x38a808(0xcd)]>opponentsConfig[_0x38a808(0x1de)]&&(this[_0x38a808(0xcd)]=0x1,console[_0x38a808(0x249)](_0x38a808(0xaa)+this['currentLevel'])),this[_0x38a808(0xae)](),console[_0x38a808(0x249)](_0x38a808(0x17d)+this['currentLevel']);}[_0x5ca7c6(0x239)](_0x459012){const _0xee300f=_0x5ca7c6;if(this[_0xee300f(0xa2)]||this[_0xee300f(0xd7)]!==_0xee300f(0x24c)||this[_0xee300f(0x1bf)]!==this['player1'])return;_0x459012[_0xee300f(0x10d)]();const _0x173ac6=this[_0xee300f(0xb4)](_0x459012);if(!_0x173ac6||!_0x173ac6[_0xee300f(0x1cf)])return;this[_0xee300f(0x203)]=!![],this[_0xee300f(0xcf)]={'x':_0x173ac6['x'],'y':_0x173ac6['y']},_0x173ac6['element'][_0xee300f(0xa9)][_0xee300f(0x142)](_0xee300f(0x240));const _0x3538db=document[_0xee300f(0x24b)](_0xee300f(0xcc))[_0xee300f(0x259)]();this['offsetX']=_0x459012[_0xee300f(0x1ae)]-(_0x3538db[_0xee300f(0x1bb)]+this[_0xee300f(0xcf)]['x']*this[_0xee300f(0xfa)]),this[_0xee300f(0x283)]=_0x459012[_0xee300f(0xdb)]-(_0x3538db['top']+this[_0xee300f(0xcf)]['y']*this['tileSizeWithGap']);}[_0x5ca7c6(0xdc)](_0x11d12f){const _0x2ea40b=_0x5ca7c6;if(!this[_0x2ea40b(0x203)]||!this[_0x2ea40b(0xcf)]||this[_0x2ea40b(0xa2)]||this['gameState']!==_0x2ea40b(0x24c))return;_0x11d12f['preventDefault']();const _0x3d4e51=document['getElementById']('game-board')[_0x2ea40b(0x259)](),_0x59eda5=_0x11d12f[_0x2ea40b(0x1ae)]-_0x3d4e51['left']-this[_0x2ea40b(0x1af)],_0x2a663c=_0x11d12f['clientY']-_0x3d4e51[_0x2ea40b(0x1e8)]-this[_0x2ea40b(0x283)],_0x272f12=this[_0x2ea40b(0x161)][this['selectedTile']['y']][this[_0x2ea40b(0xcf)]['x']][_0x2ea40b(0x1cf)];_0x272f12[_0x2ea40b(0x1b7)][_0x2ea40b(0x260)]='';if(!this[_0x2ea40b(0x258)]){const _0x3a837a=Math[_0x2ea40b(0x23e)](_0x59eda5-this[_0x2ea40b(0xcf)]['x']*this['tileSizeWithGap']),_0x1b591a=Math[_0x2ea40b(0x23e)](_0x2a663c-this['selectedTile']['y']*this[_0x2ea40b(0xfa)]);if(_0x3a837a>_0x1b591a&&_0x3a837a>0x5)this[_0x2ea40b(0x258)]='row';else{if(_0x1b591a>_0x3a837a&&_0x1b591a>0x5)this[_0x2ea40b(0x258)]=_0x2ea40b(0x25a);}}if(!this[_0x2ea40b(0x258)])return;if(this[_0x2ea40b(0x258)]==='row'){const _0x1ae33c=Math['max'](0x0,Math[_0x2ea40b(0xc8)]((this['width']-0x1)*this[_0x2ea40b(0xfa)],_0x59eda5));_0x272f12[_0x2ea40b(0x1b7)][_0x2ea40b(0xd4)]=_0x2ea40b(0xe5)+(_0x1ae33c-this[_0x2ea40b(0xcf)]['x']*this[_0x2ea40b(0xfa)])+_0x2ea40b(0x299),this[_0x2ea40b(0x285)]={'x':Math[_0x2ea40b(0x2a1)](_0x1ae33c/this[_0x2ea40b(0xfa)]),'y':this[_0x2ea40b(0xcf)]['y']};}else{if(this['dragDirection']==='column'){const _0x451af1=Math['max'](0x0,Math['min']((this[_0x2ea40b(0x13c)]-0x1)*this['tileSizeWithGap'],_0x2a663c));_0x272f12[_0x2ea40b(0x1b7)][_0x2ea40b(0xd4)]='translate(0,\x20'+(_0x451af1-this[_0x2ea40b(0xcf)]['y']*this[_0x2ea40b(0xfa)])+_0x2ea40b(0x95),this['targetTile']={'x':this['selectedTile']['x'],'y':Math[_0x2ea40b(0x2a1)](_0x451af1/this[_0x2ea40b(0xfa)])};}}}[_0x5ca7c6(0x215)](_0x13bbd2){const _0x25c601=_0x5ca7c6;if(!this[_0x25c601(0x203)]||!this[_0x25c601(0xcf)]||!this[_0x25c601(0x285)]||this[_0x25c601(0xa2)]||this[_0x25c601(0xd7)]!==_0x25c601(0x24c)){if(this['selectedTile']){const _0x5d459a=this[_0x25c601(0x161)][this[_0x25c601(0xcf)]['y']][this[_0x25c601(0xcf)]['x']];if(_0x5d459a['element'])_0x5d459a['element']['classList'][_0x25c601(0x90)](_0x25c601(0x240));}this['isDragging']=![],this['selectedTile']=null,this[_0x25c601(0x285)]=null,this[_0x25c601(0x258)]=null,this[_0x25c601(0x159)]();return;}const _0x74b0e6=this['board'][this[_0x25c601(0xcf)]['y']][this[_0x25c601(0xcf)]['x']];if(_0x74b0e6[_0x25c601(0x1cf)])_0x74b0e6[_0x25c601(0x1cf)][_0x25c601(0xa9)][_0x25c601(0x90)](_0x25c601(0x240));this[_0x25c601(0x1a3)](this[_0x25c601(0xcf)]['x'],this[_0x25c601(0xcf)]['y'],this['targetTile']['x'],this[_0x25c601(0x285)]['y']),this[_0x25c601(0x203)]=![],this['selectedTile']=null,this[_0x25c601(0x285)]=null,this['dragDirection']=null;}['handleTouchStart'](_0x3aa3dc){const _0x2e9d7d=_0x5ca7c6;if(this['gameOver']||this['gameState']!==_0x2e9d7d(0x24c)||this[_0x2e9d7d(0x1bf)]!==this[_0x2e9d7d(0x20d)])return;_0x3aa3dc[_0x2e9d7d(0x10d)]();const _0x49be8a=this[_0x2e9d7d(0xb4)](_0x3aa3dc['touches'][0x0]);if(!_0x49be8a||!_0x49be8a[_0x2e9d7d(0x1cf)])return;this[_0x2e9d7d(0x203)]=!![],this[_0x2e9d7d(0xcf)]={'x':_0x49be8a['x'],'y':_0x49be8a['y']},_0x49be8a[_0x2e9d7d(0x1cf)][_0x2e9d7d(0xa9)][_0x2e9d7d(0x142)](_0x2e9d7d(0x240));const _0x1000c9=document[_0x2e9d7d(0x24b)]('game-board')[_0x2e9d7d(0x259)]();this[_0x2e9d7d(0x1af)]=_0x3aa3dc['touches'][0x0]['clientX']-(_0x1000c9[_0x2e9d7d(0x1bb)]+this[_0x2e9d7d(0xcf)]['x']*this[_0x2e9d7d(0xfa)]),this[_0x2e9d7d(0x283)]=_0x3aa3dc[_0x2e9d7d(0x1ea)][0x0][_0x2e9d7d(0xdb)]-(_0x1000c9['top']+this[_0x2e9d7d(0xcf)]['y']*this['tileSizeWithGap']);}[_0x5ca7c6(0x144)](_0x318923){const _0x2bdcf0=_0x5ca7c6;if(!this[_0x2bdcf0(0x203)]||!this[_0x2bdcf0(0xcf)]||this['gameOver']||this[_0x2bdcf0(0xd7)]!==_0x2bdcf0(0x24c))return;_0x318923[_0x2bdcf0(0x10d)]();const _0x583d56=document[_0x2bdcf0(0x24b)](_0x2bdcf0(0xcc))[_0x2bdcf0(0x259)](),_0x29039d=_0x318923[_0x2bdcf0(0x1ea)][0x0][_0x2bdcf0(0x1ae)]-_0x583d56[_0x2bdcf0(0x1bb)]-this['offsetX'],_0xe598bf=_0x318923['touches'][0x0][_0x2bdcf0(0xdb)]-_0x583d56['top']-this[_0x2bdcf0(0x283)],_0x4a6c92=this[_0x2bdcf0(0x161)][this[_0x2bdcf0(0xcf)]['y']][this[_0x2bdcf0(0xcf)]['x']]['element'];requestAnimationFrame(()=>{const _0x197357=_0x2bdcf0;if(!this['dragDirection']){const _0x1f9b64=Math['abs'](_0x29039d-this[_0x197357(0xcf)]['x']*this['tileSizeWithGap']),_0x52e474=Math[_0x197357(0x23e)](_0xe598bf-this[_0x197357(0xcf)]['y']*this[_0x197357(0xfa)]);if(_0x1f9b64>_0x52e474&&_0x1f9b64>0x7)this[_0x197357(0x258)]=_0x197357(0x17f);else{if(_0x52e474>_0x1f9b64&&_0x52e474>0x7)this[_0x197357(0x258)]=_0x197357(0x25a);}}_0x4a6c92[_0x197357(0x1b7)]['transition']='';if(this[_0x197357(0x258)]===_0x197357(0x17f)){const _0x309886=Math[_0x197357(0x91)](0x0,Math['min']((this[_0x197357(0x9b)]-0x1)*this[_0x197357(0xfa)],_0x29039d));_0x4a6c92[_0x197357(0x1b7)]['transform']=_0x197357(0xe5)+(_0x309886-this[_0x197357(0xcf)]['x']*this['tileSizeWithGap'])+_0x197357(0x299),this[_0x197357(0x285)]={'x':Math['round'](_0x309886/this[_0x197357(0xfa)]),'y':this[_0x197357(0xcf)]['y']};}else{if(this['dragDirection']==='column'){const _0x18eddc=Math['max'](0x0,Math['min']((this['height']-0x1)*this['tileSizeWithGap'],_0xe598bf));_0x4a6c92[_0x197357(0x1b7)][_0x197357(0xd4)]='translate(0,\x20'+(_0x18eddc-this[_0x197357(0xcf)]['y']*this[_0x197357(0xfa)])+_0x197357(0x95),this[_0x197357(0x285)]={'x':this[_0x197357(0xcf)]['x'],'y':Math[_0x197357(0x2a1)](_0x18eddc/this['tileSizeWithGap'])};}}});}[_0x5ca7c6(0x289)](_0x1a5426){const _0x56afd4=_0x5ca7c6;if(!this['isDragging']||!this[_0x56afd4(0xcf)]||!this[_0x56afd4(0x285)]||this[_0x56afd4(0xa2)]||this[_0x56afd4(0xd7)]!==_0x56afd4(0x24c)){if(this[_0x56afd4(0xcf)]){const _0x4d418c=this[_0x56afd4(0x161)][this[_0x56afd4(0xcf)]['y']][this[_0x56afd4(0xcf)]['x']];if(_0x4d418c[_0x56afd4(0x1cf)])_0x4d418c[_0x56afd4(0x1cf)][_0x56afd4(0xa9)][_0x56afd4(0x90)](_0x56afd4(0x240));}this[_0x56afd4(0x203)]=![],this[_0x56afd4(0xcf)]=null,this[_0x56afd4(0x285)]=null,this['dragDirection']=null,this[_0x56afd4(0x159)]();return;}const _0x503e97=this[_0x56afd4(0x161)][this[_0x56afd4(0xcf)]['y']][this['selectedTile']['x']];if(_0x503e97[_0x56afd4(0x1cf)])_0x503e97[_0x56afd4(0x1cf)][_0x56afd4(0xa9)][_0x56afd4(0x90)]('selected');this[_0x56afd4(0x1a3)](this[_0x56afd4(0xcf)]['x'],this[_0x56afd4(0xcf)]['y'],this[_0x56afd4(0x285)]['x'],this['targetTile']['y']),this[_0x56afd4(0x203)]=![],this[_0x56afd4(0xcf)]=null,this[_0x56afd4(0x285)]=null,this['dragDirection']=null;}['getTileFromEvent'](_0x42ce4b){const _0x597d27=_0x5ca7c6,_0x298098=document[_0x597d27(0x24b)](_0x597d27(0xcc))[_0x597d27(0x259)](),_0x182352=Math['floor']((_0x42ce4b[_0x597d27(0x1ae)]-_0x298098[_0x597d27(0x1bb)])/this[_0x597d27(0xfa)]),_0x51b3be=Math[_0x597d27(0x1d0)]((_0x42ce4b[_0x597d27(0xdb)]-_0x298098[_0x597d27(0x1e8)])/this[_0x597d27(0xfa)]);if(_0x182352>=0x0&&_0x182352<this[_0x597d27(0x9b)]&&_0x51b3be>=0x0&&_0x51b3be<this[_0x597d27(0x13c)])return{'x':_0x182352,'y':_0x51b3be,'element':this[_0x597d27(0x161)][_0x51b3be][_0x182352]['element']};return null;}[_0x5ca7c6(0x1a3)](_0x747956,_0x18decf,_0x33ff0b,_0x2fd58a){const _0x4f8fca=_0x5ca7c6,_0x54e2b0=this[_0x4f8fca(0xfa)];let _0x2330e4;const _0x2ef71b=[],_0x34cd47=[];if(_0x18decf===_0x2fd58a){_0x2330e4=_0x747956<_0x33ff0b?0x1:-0x1;const _0x5d63f0=Math['min'](_0x747956,_0x33ff0b),_0x4350f2=Math['max'](_0x747956,_0x33ff0b);for(let _0x20aa97=_0x5d63f0;_0x20aa97<=_0x4350f2;_0x20aa97++){_0x2ef71b[_0x4f8fca(0x152)]({...this[_0x4f8fca(0x161)][_0x18decf][_0x20aa97]}),_0x34cd47[_0x4f8fca(0x152)](this['board'][_0x18decf][_0x20aa97][_0x4f8fca(0x1cf)]);}}else{if(_0x747956===_0x33ff0b){_0x2330e4=_0x18decf<_0x2fd58a?0x1:-0x1;const _0x3c1e5b=Math[_0x4f8fca(0xc8)](_0x18decf,_0x2fd58a),_0x23526f=Math['max'](_0x18decf,_0x2fd58a);for(let _0x5a7cf0=_0x3c1e5b;_0x5a7cf0<=_0x23526f;_0x5a7cf0++){_0x2ef71b[_0x4f8fca(0x152)]({...this[_0x4f8fca(0x161)][_0x5a7cf0][_0x747956]}),_0x34cd47[_0x4f8fca(0x152)](this[_0x4f8fca(0x161)][_0x5a7cf0][_0x747956]['element']);}}}const _0x45639f=this['board'][_0x18decf][_0x747956][_0x4f8fca(0x1cf)],_0x37f9a8=(_0x33ff0b-_0x747956)*_0x54e2b0,_0x52c201=(_0x2fd58a-_0x18decf)*_0x54e2b0;_0x45639f[_0x4f8fca(0x1b7)][_0x4f8fca(0x260)]=_0x4f8fca(0x103),_0x45639f[_0x4f8fca(0x1b7)][_0x4f8fca(0xd4)]='translate('+_0x37f9a8+_0x4f8fca(0x135)+_0x52c201+_0x4f8fca(0x10c);let _0xa2ec0a=0x0;if(_0x18decf===_0x2fd58a)for(let _0x243180=Math[_0x4f8fca(0xc8)](_0x747956,_0x33ff0b);_0x243180<=Math[_0x4f8fca(0x91)](_0x747956,_0x33ff0b);_0x243180++){if(_0x243180===_0x747956)continue;const _0x4833be=_0x2330e4*-_0x54e2b0*(_0x243180-_0x747956)/Math[_0x4f8fca(0x23e)](_0x33ff0b-_0x747956);_0x34cd47[_0xa2ec0a]['style']['transition']='transform\x200.2s\x20ease',_0x34cd47[_0xa2ec0a]['style'][_0x4f8fca(0xd4)]='translate('+_0x4833be+'px,\x200)',_0xa2ec0a++;}else for(let _0x56f80e=Math[_0x4f8fca(0xc8)](_0x18decf,_0x2fd58a);_0x56f80e<=Math[_0x4f8fca(0x91)](_0x18decf,_0x2fd58a);_0x56f80e++){if(_0x56f80e===_0x18decf)continue;const _0x221dcc=_0x2330e4*-_0x54e2b0*(_0x56f80e-_0x18decf)/Math[_0x4f8fca(0x23e)](_0x2fd58a-_0x18decf);_0x34cd47[_0xa2ec0a][_0x4f8fca(0x1b7)]['transition']=_0x4f8fca(0x103),_0x34cd47[_0xa2ec0a]['style'][_0x4f8fca(0xd4)]='translate(0,\x20'+_0x221dcc+_0x4f8fca(0x10c),_0xa2ec0a++;}setTimeout(()=>{const _0x2313d7=_0x4f8fca;if(_0x18decf===_0x2fd58a){const _0x13431c=this[_0x2313d7(0x161)][_0x18decf],_0x3521f3=[..._0x13431c];if(_0x747956<_0x33ff0b){for(let _0xd77ba6=_0x747956;_0xd77ba6<_0x33ff0b;_0xd77ba6++)_0x13431c[_0xd77ba6]=_0x3521f3[_0xd77ba6+0x1];}else{for(let _0x5393db=_0x747956;_0x5393db>_0x33ff0b;_0x5393db--)_0x13431c[_0x5393db]=_0x3521f3[_0x5393db-0x1];}_0x13431c[_0x33ff0b]=_0x3521f3[_0x747956];}else{const _0x40ad24=[];for(let _0x25143d=0x0;_0x25143d<this[_0x2313d7(0x13c)];_0x25143d++)_0x40ad24[_0x25143d]={...this['board'][_0x25143d][_0x747956]};if(_0x18decf<_0x2fd58a){for(let _0x201936=_0x18decf;_0x201936<_0x2fd58a;_0x201936++)this[_0x2313d7(0x161)][_0x201936][_0x747956]=_0x40ad24[_0x201936+0x1];}else{for(let _0x77bea2=_0x18decf;_0x77bea2>_0x2fd58a;_0x77bea2--)this[_0x2313d7(0x161)][_0x77bea2][_0x747956]=_0x40ad24[_0x77bea2-0x1];}this[_0x2313d7(0x161)][_0x2fd58a][_0x33ff0b]=_0x40ad24[_0x18decf];}this[_0x2313d7(0x159)]();const _0x1e9e74=this[_0x2313d7(0x118)](_0x33ff0b,_0x2fd58a);_0x1e9e74?this['gameState']=_0x2313d7(0x11d):(log(_0x2313d7(0x20a)),this[_0x2313d7(0x1be)][_0x2313d7(0xdd)][_0x2313d7(0x26b)](),_0x45639f['style']['transition']=_0x2313d7(0x103),_0x45639f[_0x2313d7(0x1b7)][_0x2313d7(0xd4)]=_0x2313d7(0x137),_0x34cd47[_0x2313d7(0x1b3)](_0xc562e9=>{const _0x15ff13=_0x2313d7;_0xc562e9[_0x15ff13(0x1b7)][_0x15ff13(0x260)]='transform\x200.2s\x20ease',_0xc562e9[_0x15ff13(0x1b7)]['transform']=_0x15ff13(0x137);}),setTimeout(()=>{const _0x1b24f6=_0x2313d7;if(_0x18decf===_0x2fd58a){const _0x215361=Math[_0x1b24f6(0xc8)](_0x747956,_0x33ff0b);for(let _0xfaf167=0x0;_0xfaf167<_0x2ef71b[_0x1b24f6(0x1de)];_0xfaf167++){this[_0x1b24f6(0x161)][_0x18decf][_0x215361+_0xfaf167]={..._0x2ef71b[_0xfaf167],'element':_0x34cd47[_0xfaf167]};}}else{const _0x209f4e=Math['min'](_0x18decf,_0x2fd58a);for(let _0x525c51=0x0;_0x525c51<_0x2ef71b[_0x1b24f6(0x1de)];_0x525c51++){this[_0x1b24f6(0x161)][_0x209f4e+_0x525c51][_0x747956]={..._0x2ef71b[_0x525c51],'element':_0x34cd47[_0x525c51]};}}this['renderBoard'](),this[_0x1b24f6(0xd7)]=_0x1b24f6(0x24c);},0xc8));},0xc8);}[_0x5ca7c6(0x118)](_0x1b643d=null,_0x4e5075=null){const _0x3329b4=_0x5ca7c6;console['log'](_0x3329b4(0x1e4),this[_0x3329b4(0xa2)]);if(this[_0x3329b4(0xa2)])return console[_0x3329b4(0x249)]('Game\x20over,\x20exiting\x20resolveMatches'),![];const _0x3d25a7=_0x1b643d!==null&&_0x4e5075!==null;console[_0x3329b4(0x249)](_0x3329b4(0x168)+_0x3d25a7);const _0x50fb4a=this[_0x3329b4(0x29f)]();console[_0x3329b4(0x249)]('Found\x20'+_0x50fb4a[_0x3329b4(0x1de)]+'\x20matches:',_0x50fb4a);let _0x3c8eec=0x1,_0x594378='';if(_0x3d25a7&&_0x50fb4a[_0x3329b4(0x1de)]>0x1){const _0x165fd3=_0x50fb4a['reduce']((_0x241844,_0x27aba6)=>_0x241844+_0x27aba6[_0x3329b4(0x14d)],0x0);console['log']('Total\x20tiles\x20matched\x20from\x20player\x20move:\x20'+_0x165fd3);if(_0x165fd3>=0x6&&_0x165fd3<=0x8)_0x3c8eec=1.2,_0x594378=_0x3329b4(0x25b)+_0x165fd3+_0x3329b4(0x13b),this[_0x3329b4(0x1be)][_0x3329b4(0x1fa)][_0x3329b4(0x26b)]();else _0x165fd3>=0x9&&(_0x3c8eec=0x3,_0x594378=_0x3329b4(0x261)+_0x165fd3+_0x3329b4(0x153),this['sounds'][_0x3329b4(0x1fa)]['play']());}if(_0x50fb4a[_0x3329b4(0x1de)]>0x0){const _0x478a21=new Set();let _0x1d8aa3=0x0;const _0x59cc6a=this[_0x3329b4(0x1bf)],_0x2f1c09=this[_0x3329b4(0x1bf)]===this[_0x3329b4(0x20d)]?this[_0x3329b4(0x2a3)]:this[_0x3329b4(0x20d)];try{_0x50fb4a[_0x3329b4(0x1b3)](_0x382972=>{const _0x5a5198=_0x3329b4;console[_0x5a5198(0x249)]('Processing\x20match:',_0x382972),_0x382972[_0x5a5198(0x171)][_0x5a5198(0x1b3)](_0x2d76af=>_0x478a21[_0x5a5198(0x142)](_0x2d76af));const _0x6c6f55=this[_0x5a5198(0x15d)](_0x382972,_0x3d25a7);console[_0x5a5198(0x249)]('Damage\x20from\x20match:\x20'+_0x6c6f55);if(this['gameOver']){console[_0x5a5198(0x249)](_0x5a5198(0x12e));return;}if(_0x6c6f55>0x0)_0x1d8aa3+=_0x6c6f55;});if(this['gameOver'])return console[_0x3329b4(0x249)]('Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches'),!![];return console[_0x3329b4(0x249)]('Total\x20damage\x20dealt:\x20'+_0x1d8aa3+',\x20tiles\x20to\x20clear:',[..._0x478a21]),_0x1d8aa3>0x0&&!this[_0x3329b4(0xa2)]&&setTimeout(()=>{const _0x4a58f8=_0x3329b4;if(this[_0x4a58f8(0xa2)]){console[_0x4a58f8(0x249)](_0x4a58f8(0xb3));return;}console['log'](_0x4a58f8(0x1f7),_0x2f1c09[_0x4a58f8(0x24f)]),this[_0x4a58f8(0x8a)](_0x2f1c09,_0x1d8aa3);},0x64),setTimeout(()=>{const _0x4792c8=_0x3329b4;if(this[_0x4792c8(0xa2)]){console[_0x4792c8(0x249)](_0x4792c8(0xf0));return;}console[_0x4792c8(0x249)]('Animating\x20matched\x20tiles,\x20allMatchedTiles:',[..._0x478a21]),_0x478a21[_0x4792c8(0x1b3)](_0x1657f2=>{const _0x28dfad=_0x4792c8,[_0x1f7250,_0x1549cc]=_0x1657f2['split'](',')[_0x28dfad(0x92)](Number);this['board'][_0x1549cc][_0x1f7250]?.[_0x28dfad(0x1cf)]?this[_0x28dfad(0x161)][_0x1549cc][_0x1f7250][_0x28dfad(0x1cf)][_0x28dfad(0xa9)]['add'](_0x28dfad(0x1b8)):console[_0x28dfad(0x1ee)](_0x28dfad(0x255)+_0x1f7250+','+_0x1549cc+_0x28dfad(0xe0));}),setTimeout(()=>{const _0x5b26a1=_0x4792c8;if(this[_0x5b26a1(0xa2)]){console['log'](_0x5b26a1(0xc1));return;}console['log'](_0x5b26a1(0x222),[..._0x478a21]),_0x478a21[_0x5b26a1(0x1b3)](_0x18abde=>{const _0x371ba2=_0x5b26a1,[_0x116dcd,_0x1da30e]=_0x18abde['split'](',')[_0x371ba2(0x92)](Number);this['board'][_0x1da30e][_0x116dcd]&&(this[_0x371ba2(0x161)][_0x1da30e][_0x116dcd][_0x371ba2(0x1dc)]=null,this[_0x371ba2(0x161)][_0x1da30e][_0x116dcd][_0x371ba2(0x1cf)]=null);}),this[_0x5b26a1(0x1be)]['match']['play'](),console[_0x5b26a1(0x249)](_0x5b26a1(0x87));if(_0x3c8eec>0x1&&this['roundStats']['length']>0x0){const _0x22205f=this[_0x5b26a1(0x288)][this[_0x5b26a1(0x288)]['length']-0x1],_0x5f2079=_0x22205f[_0x5b26a1(0x218)];_0x22205f['points']=Math[_0x5b26a1(0x2a1)](_0x22205f[_0x5b26a1(0x218)]*_0x3c8eec),_0x594378&&(log(_0x594378),log(_0x5b26a1(0x276)+_0x5f2079+'\x20to\x20'+_0x22205f[_0x5b26a1(0x218)]+_0x5b26a1(0x1c2)));}this[_0x5b26a1(0x227)](()=>{const _0x3d641e=_0x5b26a1;if(this[_0x3d641e(0xa2)]){console[_0x3d641e(0x249)]('Game\x20over,\x20skipping\x20endTurn');return;}console['log']('Cascade\x20complete,\x20ending\x20turn'),this[_0x3d641e(0x166)]();});},0x12c);},0xc8),!![];}catch(_0x98531a){return console[_0x3329b4(0x209)]('Error\x20in\x20resolveMatches:',_0x98531a),this[_0x3329b4(0xd7)]=this['currentTurn']===this['player1']?_0x3329b4(0x24c):_0x3329b4(0x176),![];}}return console['log'](_0x3329b4(0x1db)),![];}[_0x5ca7c6(0x29f)](){const _0x3ff571=_0x5ca7c6;console['log'](_0x3ff571(0x1e3));const _0xd8fc8d=[];try{const _0x24c01d=[];for(let _0x9242c5=0x0;_0x9242c5<this['height'];_0x9242c5++){let _0x589a3f=0x0;for(let _0x3e7116=0x0;_0x3e7116<=this['width'];_0x3e7116++){const _0x16c703=_0x3e7116<this['width']?this[_0x3ff571(0x161)][_0x9242c5][_0x3e7116]?.[_0x3ff571(0x1dc)]:null;if(_0x16c703!==this[_0x3ff571(0x161)][_0x9242c5][_0x589a3f]?.[_0x3ff571(0x1dc)]||_0x3e7116===this[_0x3ff571(0x9b)]){const _0x4dbcca=_0x3e7116-_0x589a3f;if(_0x4dbcca>=0x3){const _0x5b299f=new Set();for(let _0xfd5226=_0x589a3f;_0xfd5226<_0x3e7116;_0xfd5226++){_0x5b299f[_0x3ff571(0x142)](_0xfd5226+','+_0x9242c5);}_0x24c01d[_0x3ff571(0x152)]({'type':this['board'][_0x9242c5][_0x589a3f][_0x3ff571(0x1dc)],'coordinates':_0x5b299f}),console[_0x3ff571(0x249)](_0x3ff571(0xc7)+_0x9242c5+_0x3ff571(0xea)+_0x589a3f+'-'+(_0x3e7116-0x1)+':',[..._0x5b299f]);}_0x589a3f=_0x3e7116;}}}for(let _0x195de5=0x0;_0x195de5<this[_0x3ff571(0x9b)];_0x195de5++){let _0x4b8a4c=0x0;for(let _0x1f2b20=0x0;_0x1f2b20<=this[_0x3ff571(0x13c)];_0x1f2b20++){const _0x7e14f1=_0x1f2b20<this[_0x3ff571(0x13c)]?this[_0x3ff571(0x161)][_0x1f2b20][_0x195de5]?.[_0x3ff571(0x1dc)]:null;if(_0x7e14f1!==this['board'][_0x4b8a4c][_0x195de5]?.[_0x3ff571(0x1dc)]||_0x1f2b20===this[_0x3ff571(0x13c)]){const _0x36a6c9=_0x1f2b20-_0x4b8a4c;if(_0x36a6c9>=0x3){const _0x3df20a=new Set();for(let _0x2fab06=_0x4b8a4c;_0x2fab06<_0x1f2b20;_0x2fab06++){_0x3df20a[_0x3ff571(0x142)](_0x195de5+','+_0x2fab06);}_0x24c01d[_0x3ff571(0x152)]({'type':this[_0x3ff571(0x161)][_0x4b8a4c][_0x195de5][_0x3ff571(0x1dc)],'coordinates':_0x3df20a}),console[_0x3ff571(0x249)](_0x3ff571(0x233)+_0x195de5+_0x3ff571(0x23d)+_0x4b8a4c+'-'+(_0x1f2b20-0x1)+':',[..._0x3df20a]);}_0x4b8a4c=_0x1f2b20;}}}const _0x128e82=[],_0x3ae50b=new Set();return _0x24c01d[_0x3ff571(0x1b3)]((_0x10de03,_0x57d4a9)=>{const _0x148058=_0x3ff571;if(_0x3ae50b[_0x148058(0x1b2)](_0x57d4a9))return;const _0x316ee9={'type':_0x10de03[_0x148058(0x1dc)],'coordinates':new Set(_0x10de03[_0x148058(0x171)])};_0x3ae50b[_0x148058(0x142)](_0x57d4a9);for(let _0xb1d4b5=0x0;_0xb1d4b5<_0x24c01d[_0x148058(0x1de)];_0xb1d4b5++){if(_0x3ae50b[_0x148058(0x1b2)](_0xb1d4b5))continue;const _0x45b4e4=_0x24c01d[_0xb1d4b5];if(_0x45b4e4[_0x148058(0x1dc)]===_0x316ee9[_0x148058(0x1dc)]){const _0xaf808d=[..._0x45b4e4[_0x148058(0x171)]][_0x148058(0x1b9)](_0x79bf73=>_0x316ee9[_0x148058(0x171)][_0x148058(0x1b2)](_0x79bf73));_0xaf808d&&(_0x45b4e4[_0x148058(0x171)]['forEach'](_0x537bd6=>_0x316ee9[_0x148058(0x171)][_0x148058(0x142)](_0x537bd6)),_0x3ae50b[_0x148058(0x142)](_0xb1d4b5));}}_0x128e82[_0x148058(0x152)]({'type':_0x316ee9[_0x148058(0x1dc)],'coordinates':_0x316ee9['coordinates'],'totalTiles':_0x316ee9[_0x148058(0x171)][_0x148058(0x235)]});}),_0xd8fc8d[_0x3ff571(0x152)](..._0x128e82),console[_0x3ff571(0x249)](_0x3ff571(0xba),_0xd8fc8d),_0xd8fc8d;}catch(_0x31f3bf){return console[_0x3ff571(0x209)](_0x3ff571(0x1c0),_0x31f3bf),[];}}[_0x5ca7c6(0x15d)](_0x3f8528,_0x1eb69f=!![]){const _0x570e8c=_0x5ca7c6;console[_0x570e8c(0x249)](_0x570e8c(0x16c),_0x3f8528,_0x570e8c(0x12d),_0x1eb69f);const _0x2be779=this[_0x570e8c(0x1bf)],_0x44ec58=this[_0x570e8c(0x1bf)]===this['player1']?this[_0x570e8c(0x2a3)]:this['player1'],_0x3ec5d9=_0x3f8528[_0x570e8c(0x1dc)],_0x440da3=_0x3f8528[_0x570e8c(0x14d)];let _0x4cb608=0x0,_0x25e5c0=0x0;console[_0x570e8c(0x249)](_0x44ec58[_0x570e8c(0x24f)]+'\x20health\x20before\x20match:\x20'+_0x44ec58['health']);_0x440da3==0x4&&(this[_0x570e8c(0x1be)]['powerGem'][_0x570e8c(0x26b)](),log(_0x2be779[_0x570e8c(0x24f)]+'\x20created\x20a\x20match\x20of\x20'+_0x440da3+'\x20tiles!'));_0x440da3>=0x5&&(this[_0x570e8c(0x1be)][_0x570e8c(0x296)][_0x570e8c(0x26b)](),log(_0x2be779[_0x570e8c(0x24f)]+_0x570e8c(0x204)+_0x440da3+'\x20tiles!'));if(_0x3ec5d9===_0x570e8c(0x15f)||_0x3ec5d9===_0x570e8c(0x160)||_0x3ec5d9===_0x570e8c(0x182)||_0x3ec5d9==='last-stand'){_0x4cb608=Math[_0x570e8c(0x2a1)](_0x2be779[_0x570e8c(0x18c)]*(_0x440da3===0x3?0x2:_0x440da3===0x4?0x3:0x4));let _0x4c1924=0x1;if(_0x440da3===0x4)_0x4c1924=1.5;else _0x440da3>=0x5&&(_0x4c1924=0x2);_0x4cb608=Math['round'](_0x4cb608*_0x4c1924),console[_0x570e8c(0x249)](_0x570e8c(0xff)+_0x2be779[_0x570e8c(0x18c)]*(_0x440da3===0x3?0x2:_0x440da3===0x4?0x3:0x4)+_0x570e8c(0xee)+_0x4c1924+_0x570e8c(0xb7)+_0x4cb608);_0x3ec5d9===_0x570e8c(0x182)&&(_0x4cb608=Math['round'](_0x4cb608*1.2),console[_0x570e8c(0x249)](_0x570e8c(0x189)+_0x4cb608));_0x2be779['boostActive']&&(_0x4cb608+=_0x2be779[_0x570e8c(0x13e)]||0xa,_0x2be779[_0x570e8c(0x284)]=![],log(_0x2be779[_0x570e8c(0x24f)]+'\x27s\x20Boost\x20fades.'),console['log'](_0x570e8c(0x13a)+_0x4cb608));_0x25e5c0=_0x4cb608;const _0x46bacc=_0x44ec58[_0x570e8c(0x25f)]*0xa;Math['random']()*0x64<_0x46bacc&&(_0x4cb608=Math[_0x570e8c(0x1d0)](_0x4cb608/0x2),log(_0x44ec58[_0x570e8c(0x24f)]+_0x570e8c(0x20b)+_0x4cb608+_0x570e8c(0x1ab)),console[_0x570e8c(0x249)](_0x570e8c(0x8f)+_0x4cb608));let _0x366c06=0x0;_0x44ec58['lastStandActive']&&(_0x366c06=Math[_0x570e8c(0xc8)](_0x4cb608,0x5),_0x4cb608=Math[_0x570e8c(0x91)](0x0,_0x4cb608-_0x366c06),_0x44ec58[_0x570e8c(0x27f)]=![],console[_0x570e8c(0x249)](_0x570e8c(0x297)+_0x366c06+',\x20damage:\x20'+_0x4cb608));const _0x538898=_0x3ec5d9==='first-attack'?_0x570e8c(0x1d3):_0x3ec5d9===_0x570e8c(0x160)?_0x570e8c(0xe4):_0x570e8c(0x27d);let _0x4c4c47;if(_0x366c06>0x0)_0x4c4c47=_0x2be779[_0x570e8c(0x24f)]+_0x570e8c(0x286)+_0x538898+_0x570e8c(0x116)+_0x44ec58['name']+_0x570e8c(0x22e)+_0x25e5c0+_0x570e8c(0xbe)+_0x44ec58[_0x570e8c(0x24f)]+_0x570e8c(0x1c6)+_0x366c06+_0x570e8c(0x277)+_0x4cb608+_0x570e8c(0x1ab);else _0x3ec5d9===_0x570e8c(0x2aa)?_0x4c4c47=_0x2be779[_0x570e8c(0x24f)]+_0x570e8c(0xe6)+_0x4cb608+_0x570e8c(0x29e)+_0x44ec58['name']+_0x570e8c(0x257):_0x4c4c47=_0x2be779[_0x570e8c(0x24f)]+_0x570e8c(0x286)+_0x538898+_0x570e8c(0x116)+_0x44ec58[_0x570e8c(0x24f)]+'\x20for\x20'+_0x4cb608+_0x570e8c(0x1ab);_0x1eb69f?log(_0x4c4c47):log(_0x570e8c(0xcb)+_0x4c4c47),_0x44ec58[_0x570e8c(0x216)]=Math[_0x570e8c(0x91)](0x0,_0x44ec58[_0x570e8c(0x216)]-_0x4cb608),console[_0x570e8c(0x249)](_0x44ec58[_0x570e8c(0x24f)]+_0x570e8c(0x1fd)+_0x44ec58[_0x570e8c(0x216)]),this[_0x570e8c(0xad)](_0x44ec58),console[_0x570e8c(0x249)](_0x570e8c(0xfc)),this[_0x570e8c(0x163)](),!this[_0x570e8c(0xa2)]&&(console[_0x570e8c(0x249)](_0x570e8c(0x236)),this[_0x570e8c(0x1b0)](_0x2be779,_0x4cb608,_0x3ec5d9));}else _0x3ec5d9==='power-up'&&(this[_0x570e8c(0x1ed)](_0x2be779,_0x44ec58,_0x440da3),!this['gameOver']&&(console[_0x570e8c(0x249)]('Animating\x20powerup'),this[_0x570e8c(0x19c)](_0x2be779)));(!this[_0x570e8c(0x288)][this[_0x570e8c(0x288)][_0x570e8c(0x1de)]-0x1]||this[_0x570e8c(0x288)][this[_0x570e8c(0x288)][_0x570e8c(0x1de)]-0x1][_0x570e8c(0x15c)])&&this[_0x570e8c(0x288)][_0x570e8c(0x152)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x243226=this[_0x570e8c(0x288)][this['roundStats']['length']-0x1];return _0x243226[_0x570e8c(0x218)]+=_0x4cb608,_0x243226[_0x570e8c(0x140)]+=0x1,console[_0x570e8c(0x249)](_0x570e8c(0xd8)+_0x4cb608),_0x4cb608;}[_0x5ca7c6(0x227)](_0xb52393){const _0x35b18d=_0x5ca7c6;if(this[_0x35b18d(0xa2)]){console['log'](_0x35b18d(0x14f));return;}const _0x24e20=this[_0x35b18d(0x1ad)](),_0x18a18b=_0x35b18d(0x217);for(let _0x3f79b8=0x0;_0x3f79b8<this[_0x35b18d(0x9b)];_0x3f79b8++){for(let _0x4e9854=0x0;_0x4e9854<this[_0x35b18d(0x13c)];_0x4e9854++){const _0x5e37ce=this[_0x35b18d(0x161)][_0x4e9854][_0x3f79b8];if(_0x5e37ce['element']&&_0x5e37ce[_0x35b18d(0x1cf)][_0x35b18d(0x1b7)]['transform']===_0x35b18d(0x108)){const _0x1f7cb0=this[_0x35b18d(0x1dd)](_0x3f79b8,_0x4e9854);_0x1f7cb0>0x0&&(_0x5e37ce['element']['classList'][_0x35b18d(0x142)](_0x18a18b),_0x5e37ce[_0x35b18d(0x1cf)]['style'][_0x35b18d(0xd4)]='translate(0,\x20'+_0x1f7cb0*this[_0x35b18d(0xfa)]+'px)');}}}this[_0x35b18d(0x159)](),_0x24e20?setTimeout(()=>{const _0x1be644=_0x35b18d;if(this[_0x1be644(0xa2)]){console[_0x1be644(0x249)](_0x1be644(0x9e));return;}this[_0x1be644(0x1be)]['cascade'][_0x1be644(0x26b)]();const _0x4d3c91=this[_0x1be644(0x118)](),_0x168914=document[_0x1be644(0x17a)]('.'+_0x18a18b);_0x168914['forEach'](_0x4121dc=>{const _0x583929=_0x1be644;_0x4121dc['classList'][_0x583929(0x90)](_0x18a18b),_0x4121dc[_0x583929(0x1b7)][_0x583929(0xd4)]=_0x583929(0x137);}),!_0x4d3c91&&_0xb52393();},0x12c):_0xb52393();}[_0x5ca7c6(0x1ad)](){const _0x12ea4a=_0x5ca7c6;let _0x972ea1=![];for(let _0x23b396=0x0;_0x23b396<this[_0x12ea4a(0x9b)];_0x23b396++){let _0x3ce7c0=0x0;for(let _0x33610a=this['height']-0x1;_0x33610a>=0x0;_0x33610a--){if(!this[_0x12ea4a(0x161)][_0x33610a][_0x23b396][_0x12ea4a(0x1dc)])_0x3ce7c0++;else _0x3ce7c0>0x0&&(this[_0x12ea4a(0x161)][_0x33610a+_0x3ce7c0][_0x23b396]=this[_0x12ea4a(0x161)][_0x33610a][_0x23b396],this[_0x12ea4a(0x161)][_0x33610a][_0x23b396]={'type':null,'element':null},_0x972ea1=!![]);}for(let _0x19686a=0x0;_0x19686a<_0x3ce7c0;_0x19686a++){this[_0x12ea4a(0x161)][_0x19686a][_0x23b396]=this['createRandomTile'](),_0x972ea1=!![];}}return _0x972ea1;}[_0x5ca7c6(0x1dd)](_0x586db4,_0x383cf8){const _0x403efb=_0x5ca7c6;let _0x3ee62a=0x0;for(let _0x1975c6=_0x383cf8+0x1;_0x1975c6<this[_0x403efb(0x13c)];_0x1975c6++){if(!this[_0x403efb(0x161)][_0x1975c6][_0x586db4][_0x403efb(0x1dc)])_0x3ee62a++;else break;}return _0x3ee62a;}[_0x5ca7c6(0x1ed)](_0x55f6d3,_0x20dd09,_0x3b3f08){const _0x50f7ac=_0x5ca7c6,_0x1ff81b=0x1-_0x20dd09[_0x50f7ac(0x25f)]*0.05;let _0xf5e4af,_0x40fd03,_0x3e8316,_0x3caacc=0x1,_0x3d56f8='';if(_0x3b3f08===0x4)_0x3caacc=1.5,_0x3d56f8='\x20(50%\x20bonus\x20for\x20match-4)';else _0x3b3f08>=0x5&&(_0x3caacc=0x2,_0x3d56f8=_0x50f7ac(0xd3));if(_0x55f6d3['powerup']===_0x50f7ac(0x245))_0x40fd03=0xa*_0x3caacc,_0xf5e4af=Math[_0x50f7ac(0x1d0)](_0x40fd03*_0x1ff81b),_0x3e8316=_0x40fd03-_0xf5e4af,_0x55f6d3[_0x50f7ac(0x216)]=Math[_0x50f7ac(0xc8)](_0x55f6d3[_0x50f7ac(0x123)],_0x55f6d3[_0x50f7ac(0x216)]+_0xf5e4af),log(_0x55f6d3['name']+_0x50f7ac(0x282)+_0xf5e4af+'\x20HP'+_0x3d56f8+(_0x20dd09[_0x50f7ac(0x25f)]>0x0?_0x50f7ac(0x221)+_0x40fd03+',\x20reduced\x20by\x20'+_0x3e8316+_0x50f7ac(0x214)+_0x20dd09['name']+_0x50f7ac(0x22f):'')+'!');else{if(_0x55f6d3[_0x50f7ac(0xa3)]===_0x50f7ac(0x26e))_0x40fd03=0xa*_0x3caacc,_0xf5e4af=Math['floor'](_0x40fd03*_0x1ff81b),_0x3e8316=_0x40fd03-_0xf5e4af,_0x55f6d3[_0x50f7ac(0x284)]=!![],_0x55f6d3[_0x50f7ac(0x13e)]=_0xf5e4af,log(_0x55f6d3[_0x50f7ac(0x24f)]+_0x50f7ac(0x178)+_0xf5e4af+_0x50f7ac(0x237)+_0x3d56f8+(_0x20dd09[_0x50f7ac(0x25f)]>0x0?'\x20(originally\x20'+_0x40fd03+',\x20reduced\x20by\x20'+_0x3e8316+_0x50f7ac(0x214)+_0x20dd09[_0x50f7ac(0x24f)]+_0x50f7ac(0x22f):'')+'!');else{if(_0x55f6d3[_0x50f7ac(0xa3)]==='Regenerate')_0x40fd03=0x7*_0x3caacc,_0xf5e4af=Math[_0x50f7ac(0x1d0)](_0x40fd03*_0x1ff81b),_0x3e8316=_0x40fd03-_0xf5e4af,_0x55f6d3[_0x50f7ac(0x216)]=Math[_0x50f7ac(0xc8)](_0x55f6d3[_0x50f7ac(0x123)],_0x55f6d3['health']+_0xf5e4af),log(_0x55f6d3[_0x50f7ac(0x24f)]+_0x50f7ac(0x200)+_0xf5e4af+'\x20HP'+_0x3d56f8+(_0x20dd09[_0x50f7ac(0x25f)]>0x0?_0x50f7ac(0x221)+_0x40fd03+_0x50f7ac(0x167)+_0x3e8316+'\x20due\x20to\x20'+_0x20dd09[_0x50f7ac(0x24f)]+'\x27s\x20tactics)':'')+'!');else _0x55f6d3[_0x50f7ac(0xa3)]===_0x50f7ac(0x193)&&(_0x40fd03=0x5*_0x3caacc,_0xf5e4af=Math[_0x50f7ac(0x1d0)](_0x40fd03*_0x1ff81b),_0x3e8316=_0x40fd03-_0xf5e4af,_0x55f6d3[_0x50f7ac(0x216)]=Math[_0x50f7ac(0xc8)](_0x55f6d3[_0x50f7ac(0x123)],_0x55f6d3[_0x50f7ac(0x216)]+_0xf5e4af),log(_0x55f6d3['name']+_0x50f7ac(0xd9)+_0xf5e4af+_0x50f7ac(0xca)+_0x3d56f8+(_0x20dd09[_0x50f7ac(0x25f)]>0x0?_0x50f7ac(0x221)+_0x40fd03+_0x50f7ac(0x167)+_0x3e8316+_0x50f7ac(0x214)+_0x20dd09['name']+_0x50f7ac(0x22f):'')+'!'));}}this[_0x50f7ac(0xad)](_0x55f6d3);}[_0x5ca7c6(0xad)](_0x436405){const _0x514ac4=_0x5ca7c6,_0x4b8b4a=_0x436405===this[_0x514ac4(0x20d)]?p1Health:p2Health,_0x40d180=_0x436405===this[_0x514ac4(0x20d)]?p1Hp:p2Hp,_0x3ef443=_0x436405[_0x514ac4(0x216)]/_0x436405['maxHealth']*0x64;_0x4b8b4a['style'][_0x514ac4(0x9b)]=_0x3ef443+'%';let _0x3b2ce8;if(_0x3ef443>0x4b)_0x3b2ce8=_0x514ac4(0x250);else{if(_0x3ef443>0x32)_0x3b2ce8=_0x514ac4(0x29a);else _0x3ef443>0x19?_0x3b2ce8=_0x514ac4(0x1e6):_0x3b2ce8=_0x514ac4(0x8b);}_0x4b8b4a[_0x514ac4(0x1b7)][_0x514ac4(0x96)]=_0x3b2ce8,_0x40d180[_0x514ac4(0x1d6)]=_0x436405[_0x514ac4(0x216)]+'/'+_0x436405[_0x514ac4(0x123)];}[_0x5ca7c6(0x166)](){const _0x1ebd4d=_0x5ca7c6;if(this[_0x1ebd4d(0xd7)]===_0x1ebd4d(0xa2)||this[_0x1ebd4d(0xa2)]){console[_0x1ebd4d(0x249)](_0x1ebd4d(0x13d));return;}this[_0x1ebd4d(0x1bf)]=this['currentTurn']===this['player1']?this[_0x1ebd4d(0x2a3)]:this[_0x1ebd4d(0x20d)],this['gameState']=this['currentTurn']===this[_0x1ebd4d(0x20d)]?_0x1ebd4d(0x24c):'aiTurn',turnIndicator[_0x1ebd4d(0x1d6)]='Level\x20'+this[_0x1ebd4d(0xcd)]+_0x1ebd4d(0x107)+(this['currentTurn']===this[_0x1ebd4d(0x20d)]?_0x1ebd4d(0x10a):_0x1ebd4d(0xe2))+_0x1ebd4d(0xf2),log('Turn\x20switched\x20to\x20'+(this[_0x1ebd4d(0x1bf)]===this[_0x1ebd4d(0x20d)]?_0x1ebd4d(0x10a):_0x1ebd4d(0xe2))),this[_0x1ebd4d(0x1bf)]===this['player2']&&setTimeout(()=>this[_0x1ebd4d(0x176)](),0x3e8);}[_0x5ca7c6(0x176)](){const _0xc6c518=_0x5ca7c6;if(this[_0xc6c518(0xd7)]!==_0xc6c518(0x176)||this[_0xc6c518(0x1bf)]!==this[_0xc6c518(0x2a3)])return;this[_0xc6c518(0xd7)]=_0xc6c518(0x11d);const _0xdfbce9=this['findAIMove']();_0xdfbce9?(log(this[_0xc6c518(0x2a3)][_0xc6c518(0x24f)]+'\x20swaps\x20tiles\x20at\x20('+_0xdfbce9['x1']+',\x20'+_0xdfbce9['y1']+_0xc6c518(0x228)+_0xdfbce9['x2']+',\x20'+_0xdfbce9['y2']+')'),this[_0xc6c518(0x1a3)](_0xdfbce9['x1'],_0xdfbce9['y1'],_0xdfbce9['x2'],_0xdfbce9['y2'])):(log(this[_0xc6c518(0x2a3)][_0xc6c518(0x24f)]+_0xc6c518(0x26a)),this[_0xc6c518(0x166)]());}[_0x5ca7c6(0x1ef)](){const _0x4b5d6f=_0x5ca7c6;for(let _0x30762f=0x0;_0x30762f<this[_0x4b5d6f(0x13c)];_0x30762f++){for(let _0x37688c=0x0;_0x37688c<this[_0x4b5d6f(0x9b)];_0x37688c++){if(_0x37688c<this['width']-0x1&&this[_0x4b5d6f(0x273)](_0x37688c,_0x30762f,_0x37688c+0x1,_0x30762f))return{'x1':_0x37688c,'y1':_0x30762f,'x2':_0x37688c+0x1,'y2':_0x30762f};if(_0x30762f<this[_0x4b5d6f(0x13c)]-0x1&&this['canMakeMatch'](_0x37688c,_0x30762f,_0x37688c,_0x30762f+0x1))return{'x1':_0x37688c,'y1':_0x30762f,'x2':_0x37688c,'y2':_0x30762f+0x1};}}return null;}[_0x5ca7c6(0x273)](_0x8c287b,_0xd5f72f,_0x17d4a0,_0x2d32c6){const _0x3e51fd=_0x5ca7c6,_0x10f798={...this[_0x3e51fd(0x161)][_0xd5f72f][_0x8c287b]},_0x335147={...this[_0x3e51fd(0x161)][_0x2d32c6][_0x17d4a0]};this['board'][_0xd5f72f][_0x8c287b]=_0x335147,this['board'][_0x2d32c6][_0x17d4a0]=_0x10f798;const _0x4b432b=this[_0x3e51fd(0x29f)]()[_0x3e51fd(0x1de)]>0x0;return this[_0x3e51fd(0x161)][_0xd5f72f][_0x8c287b]=_0x10f798,this['board'][_0x2d32c6][_0x17d4a0]=_0x335147,_0x4b432b;}async[_0x5ca7c6(0x163)](){const _0x28e28c=_0x5ca7c6;if(this['gameOver']||this[_0x28e28c(0x274)]){console[_0x28e28c(0x249)](_0x28e28c(0x183)+this[_0x28e28c(0xa2)]+_0x28e28c(0x22d)+this[_0x28e28c(0x274)]+_0x28e28c(0x1f4)+this['currentLevel']);return;}this['isCheckingGameOver']=!![],console[_0x28e28c(0x249)](_0x28e28c(0x1cb)+this[_0x28e28c(0xcd)]+_0x28e28c(0x177)+this['player1'][_0x28e28c(0x216)]+_0x28e28c(0x16a)+this[_0x28e28c(0x2a3)][_0x28e28c(0x216)]);const _0x430015=document[_0x28e28c(0x24b)]('try-again');if(this[_0x28e28c(0x20d)]['health']<=0x0){console[_0x28e28c(0x249)](_0x28e28c(0xd1)),this[_0x28e28c(0xa2)]=!![],this[_0x28e28c(0xd7)]=_0x28e28c(0xa2),gameOver[_0x28e28c(0x1d6)]=_0x28e28c(0xd6),turnIndicator[_0x28e28c(0x1d6)]=_0x28e28c(0x1cc),log(this['player2'][_0x28e28c(0x24f)]+_0x28e28c(0x110)+this[_0x28e28c(0x20d)][_0x28e28c(0x24f)]+'!'),_0x430015[_0x28e28c(0x1d6)]='TRY\x20AGAIN',document[_0x28e28c(0x24b)](_0x28e28c(0x12f))['style'][_0x28e28c(0x14e)]='block';try{this['sounds'][_0x28e28c(0x180)][_0x28e28c(0x26b)]();}catch(_0x5632a0){console[_0x28e28c(0x209)](_0x28e28c(0xf5),_0x5632a0);}}else{if(this[_0x28e28c(0x2a3)]['health']<=0x0){console['log'](_0x28e28c(0xbb)),this['gameOver']=!![],this[_0x28e28c(0xd7)]=_0x28e28c(0xa2),gameOver['textContent']=_0x28e28c(0x294),turnIndicator[_0x28e28c(0x1d6)]=_0x28e28c(0x1cc),_0x430015['textContent']=this[_0x28e28c(0xcd)]===opponentsConfig[_0x28e28c(0x1de)]?_0x28e28c(0x278):_0x28e28c(0x1b5),document[_0x28e28c(0x24b)](_0x28e28c(0x12f))[_0x28e28c(0x1b7)][_0x28e28c(0x14e)]=_0x28e28c(0x17c);if(this[_0x28e28c(0x1bf)]===this[_0x28e28c(0x20d)]){const _0x530158=this[_0x28e28c(0x288)][this[_0x28e28c(0x288)][_0x28e28c(0x1de)]-0x1];if(_0x530158&&!_0x530158[_0x28e28c(0x15c)]){_0x530158[_0x28e28c(0x220)]=this[_0x28e28c(0x20d)][_0x28e28c(0x216)]/this['player1'][_0x28e28c(0x123)]*0x64,_0x530158[_0x28e28c(0x15c)]=!![];const _0x5b566e=_0x530158['matches']>0x0?_0x530158['points']/_0x530158['matches']/0x64*(_0x530158[_0x28e28c(0x220)]+0x14)*(0x1+this['currentLevel']/0x38):0x0;log(_0x28e28c(0x99)+_0x530158[_0x28e28c(0x218)]+_0x28e28c(0xc6)+_0x530158[_0x28e28c(0x140)]+_0x28e28c(0x10e)+_0x530158['healthPercentage']['toFixed'](0x2)+_0x28e28c(0x1a6)+this['currentLevel']),log(_0x28e28c(0x298)+_0x530158['points']+_0x28e28c(0x132)+_0x530158['matches']+_0x28e28c(0x1fe)+_0x530158[_0x28e28c(0x220)]+_0x28e28c(0xbf)+this[_0x28e28c(0xcd)]+_0x28e28c(0x272)+_0x5b566e),this['grandTotalScore']+=_0x5b566e,log(_0x28e28c(0x1c8)+_0x530158[_0x28e28c(0x218)]+_0x28e28c(0x119)+_0x530158[_0x28e28c(0x140)]+_0x28e28c(0x1d4)+_0x530158['healthPercentage'][_0x28e28c(0x243)](0x2)+'%'),log(_0x28e28c(0x266)+_0x5b566e+_0x28e28c(0x19f)+this[_0x28e28c(0x21c)]);}}await this[_0x28e28c(0x29c)](this[_0x28e28c(0xcd)]);this[_0x28e28c(0xcd)]===opponentsConfig['length']?(this[_0x28e28c(0x1be)]['finalWin']['play'](),log(_0x28e28c(0x148)+this[_0x28e28c(0x21c)]),this['grandTotalScore']=0x0,await this[_0x28e28c(0x186)](),log(_0x28e28c(0x246))):(this[_0x28e28c(0xcd)]+=0x1,await this['saveProgress'](),console[_0x28e28c(0x249)](_0x28e28c(0x139)+this[_0x28e28c(0xcd)]),this[_0x28e28c(0x1be)][_0x28e28c(0xce)][_0x28e28c(0x26b)]());const _0x4ab8a5=this[_0x28e28c(0x23c)]+_0x28e28c(0x280)+this[_0x28e28c(0x2a3)][_0x28e28c(0x24f)][_0x28e28c(0x128)]()[_0x28e28c(0x262)](/ /g,'-')+_0x28e28c(0xde);p2Image[_0x28e28c(0x253)]=_0x4ab8a5,p2Image[_0x28e28c(0xa9)]['add'](_0x28e28c(0x2a4)),p1Image[_0x28e28c(0xa9)]['add'](_0x28e28c(0x22b)),this[_0x28e28c(0x159)]();}}this[_0x28e28c(0x274)]=![],console[_0x28e28c(0x249)]('checkGameOver\x20completed:\x20currentLevel='+this[_0x28e28c(0xcd)]+_0x28e28c(0x194)+this['gameOver']);}async[_0x5ca7c6(0x29c)](_0x37fd73){const _0x5f4c49=_0x5ca7c6,_0x5f0c76={'level':_0x37fd73,'score':this[_0x5f4c49(0x21c)]};console[_0x5f4c49(0x249)](_0x5f4c49(0xb2)+_0x5f0c76[_0x5f4c49(0x268)]+',\x20score='+_0x5f0c76[_0x5f4c49(0x256)]);try{const _0x4a4ede=await fetch(_0x5f4c49(0xeb),{'method':_0x5f4c49(0x212),'headers':{'Content-Type':_0x5f4c49(0xd2)},'body':JSON[_0x5f4c49(0x270)](_0x5f0c76)});if(!_0x4a4ede['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x4a4ede[_0x5f4c49(0xf9)]);const _0x181751=await _0x4a4ede[_0x5f4c49(0x146)]();console[_0x5f4c49(0x249)]('Save\x20response:',_0x181751),log(_0x5f4c49(0x1f2)+_0x181751['level']+_0x5f4c49(0xb8)+_0x181751[_0x5f4c49(0x256)][_0x5f4c49(0x243)](0x2)),_0x181751[_0x5f4c49(0xf9)]==='success'?log(_0x5f4c49(0x1f6)+_0x181751[_0x5f4c49(0x268)]+_0x5f4c49(0xa6)+_0x181751['score'][_0x5f4c49(0x243)](0x2)+_0x5f4c49(0x121)+_0x181751[_0x5f4c49(0x22a)]):log(_0x5f4c49(0x143)+_0x181751[_0x5f4c49(0x1c4)]);}catch(_0x5eda03){console[_0x5f4c49(0x209)](_0x5f4c49(0x29d),_0x5eda03),log(_0x5f4c49(0x2ac)+_0x5eda03[_0x5f4c49(0x1c4)]);}}[_0x5ca7c6(0x232)](_0x2ba9ba,_0x34bdae,_0x591689,_0x3baddb){const _0x182e07=_0x5ca7c6,_0x507544=_0x2ba9ba['style'][_0x182e07(0xd4)]||'',_0x57bb83=_0x507544['includes'](_0x182e07(0x19e))?_0x507544[_0x182e07(0x173)](/scaleX\([^)]+\)/)[0x0]:'';_0x2ba9ba[_0x182e07(0x1b7)][_0x182e07(0x260)]=_0x182e07(0xa5)+_0x3baddb/0x2/0x3e8+_0x182e07(0x162),_0x2ba9ba[_0x182e07(0x1b7)]['transform']='translateX('+_0x34bdae+_0x182e07(0x2a9)+_0x57bb83,_0x2ba9ba[_0x182e07(0xa9)][_0x182e07(0x142)](_0x591689),setTimeout(()=>{const _0x42a017=_0x182e07;_0x2ba9ba[_0x42a017(0x1b7)][_0x42a017(0xd4)]=_0x57bb83,setTimeout(()=>{const _0x1d3161=_0x42a017;_0x2ba9ba[_0x1d3161(0xa9)][_0x1d3161(0x90)](_0x591689);},_0x3baddb/0x2);},_0x3baddb/0x2);}['animateAttack'](_0x90598d,_0xb0fa83,_0x1dac8a){const _0x3dad56=_0x5ca7c6,_0x4dac66=_0x90598d===this['player1']?p1Image:p2Image,_0x37cf9d=_0x90598d===this[_0x3dad56(0x20d)]?0x1:-0x1,_0x43ae69=Math['min'](0xa,0x2+_0xb0fa83*0.4),_0x506848=_0x37cf9d*_0x43ae69,_0x245a66='glow-'+_0x1dac8a;this[_0x3dad56(0x232)](_0x4dac66,_0x506848,_0x245a66,0xc8);}[_0x5ca7c6(0x19c)](_0x2f9a24){const _0xc75eb5=_0x5ca7c6,_0x1684d1=_0x2f9a24===this[_0xc75eb5(0x20d)]?p1Image:p2Image;this[_0xc75eb5(0x232)](_0x1684d1,0x0,'glow-power-up',0xc8);}[_0x5ca7c6(0x8a)](_0x90cf06,_0x9e058b){const _0x50ae7a=_0x5ca7c6,_0x5ab963=_0x90cf06===this[_0x50ae7a(0x20d)]?p1Image:p2Image,_0x403168=_0x90cf06===this['player1']?-0x1:0x1,_0x522195=Math['min'](0xa,0x2+_0x9e058b*0.4),_0x5760c8=_0x403168*_0x522195;this[_0x50ae7a(0x232)](_0x5ab963,_0x5760c8,'glow-recoil',0xc8);}}function randomChoice(_0x3e63ba){const _0x193ce2=_0x5ca7c6;return _0x3e63ba[Math[_0x193ce2(0x1d0)](Math[_0x193ce2(0x156)]()*_0x3e63ba[_0x193ce2(0x1de)])];}function log(_0x2c5079){const _0x1717b9=_0x5ca7c6,_0x1849cb=document[_0x1717b9(0x24b)](_0x1717b9(0x28e)),_0x3cadfb=document[_0x1717b9(0x241)]('li');_0x3cadfb[_0x1717b9(0x1d6)]=_0x2c5079,_0x1849cb['insertBefore'](_0x3cadfb,_0x1849cb[_0x1717b9(0x98)]),_0x1849cb[_0x1717b9(0x27a)]['length']>0x32&&_0x1849cb[_0x1717b9(0x21e)](_0x1849cb[_0x1717b9(0x138)]),_0x1849cb[_0x1717b9(0x1ce)]=0x0;}function _0x1cfe(_0x193704,_0x2a0596){const _0x2afa31=_0x2afa();return _0x1cfe=function(_0x1cfe42,_0x594746){_0x1cfe42=_0x1cfe42-0x86;let _0x35dc80=_0x2afa31[_0x1cfe42];return _0x35dc80;},_0x1cfe(_0x193704,_0x2a0596);}const turnIndicator=document[_0x5ca7c6(0x24b)]('turn-indicator'),p1Name=document['getElementById'](_0x5ca7c6(0x9f)),p1Image=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x199)),p1Health=document[_0x5ca7c6(0x24b)]('p1-health'),p1Hp=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x89)),p1Strength=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0xf1)),p1Speed=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0xa1)),p1Tactics=document['getElementById']('p1-tactics'),p1Size=document['getElementById']('p1-size'),p1Powerup=document[_0x5ca7c6(0x24b)]('p1-powerup'),p1Type=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x28d)),p2Name=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0xbc)),p2Image=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x129)),p2Health=document['getElementById'](_0x5ca7c6(0x184)),p2Hp=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x197)),p2Strength=document[_0x5ca7c6(0x24b)]('p2-strength'),p2Speed=document[_0x5ca7c6(0x24b)]('p2-speed'),p2Tactics=document[_0x5ca7c6(0x24b)]('p2-tactics'),p2Size=document[_0x5ca7c6(0x24b)]('p2-size'),p2Powerup=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x26f)),p2Type=document[_0x5ca7c6(0x24b)](_0x5ca7c6(0x20f)),battleLog=document[_0x5ca7c6(0x24b)]('battle-log'),gameOver=document[_0x5ca7c6(0x24b)]('game-over'),assetCache={};async function getAssets(_0x40c849){const _0x18df37=_0x5ca7c6;if(assetCache[_0x40c849])return console[_0x18df37(0x249)](_0x18df37(0xc0)+_0x40c849),assetCache[_0x40c849];console[_0x18df37(0x9a)](_0x18df37(0x1b1)+_0x40c849);let _0x2779f8=[];try{console[_0x18df37(0x249)]('getAssets:\x20Fetching\x20Monstrocity\x20assets');const _0x47efac=await Promise[_0x18df37(0x157)]([fetch(_0x18df37(0x1bd),{'method':_0x18df37(0x212),'headers':{'Content-Type':_0x18df37(0xd2)},'body':JSON[_0x18df37(0x270)]({'theme':_0x18df37(0x1e1)})}),new Promise((_0x3b476b,_0x3b01e8)=>setTimeout(()=>_0x3b01e8(new Error(_0x18df37(0x265))),0x3e8))]);console['log'](_0x18df37(0x1d8),_0x47efac['status']);if(!_0x47efac['ok'])throw new Error(_0x18df37(0x16b)+_0x47efac[_0x18df37(0xf9)]);_0x2779f8=await _0x47efac['json'](),console['log'](_0x18df37(0x17b),_0x2779f8),!Array[_0x18df37(0x1aa)](_0x2779f8)&&(_0x2779f8=[_0x2779f8]),_0x2779f8=_0x2779f8[_0x18df37(0x92)]((_0x52e072,_0x27fba4)=>{const _0x56bac1=_0x18df37,_0x330cd8={..._0x52e072,'theme':_0x56bac1(0x1e1),'name':_0x52e072[_0x56bac1(0x24f)]||'Monstrocity_Unknown_'+_0x27fba4,'strength':_0x52e072['strength']||0x4,'speed':_0x52e072['speed']||0x4,'tactics':_0x52e072['tactics']||0x4,'size':_0x52e072[_0x56bac1(0x235)]||'Medium','type':_0x52e072[_0x56bac1(0x1dc)]||_0x56bac1(0x14b),'powerup':_0x52e072['powerup']||_0x56bac1(0xbd)};return _0x330cd8;});}catch(_0x58b536){console['error'](_0x18df37(0x93),_0x58b536),_0x2779f8=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x18df37(0x187),'type':_0x18df37(0x14b),'powerup':_0x18df37(0xbd),'theme':_0x18df37(0x1e1)},{'name':_0x18df37(0x293),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x18df37(0x2a7),'type':_0x18df37(0x14b),'powerup':_0x18df37(0x245),'theme':_0x18df37(0x1e1)}],console[_0x18df37(0x249)](_0x18df37(0x295));}if(_0x40c849===_0x18df37(0x1e1))return console[_0x18df37(0x249)]('getAssets:\x20Returning\x20Monstrocity\x20assets'),assetCache[_0x40c849]=_0x2779f8,console[_0x18df37(0x1b4)](_0x18df37(0x1b1)+_0x40c849),_0x2779f8;let _0x5bd6fe=null;for(const _0x24176a of themes){_0x5bd6fe=_0x24176a[_0x18df37(0x109)][_0x18df37(0x14a)](_0x3513c5=>_0x3513c5[_0x18df37(0x1ec)]===_0x40c849);if(_0x5bd6fe)break;}if(!_0x5bd6fe)return console[_0x18df37(0x1ee)](_0x18df37(0x172)+_0x40c849),assetCache[_0x40c849]=_0x2779f8,console[_0x18df37(0x1b4)](_0x18df37(0x1b1)+_0x40c849),_0x2779f8;const _0x24ceec=_0x5bd6fe['policyIds']?_0x5bd6fe['policyIds']['split'](',')[_0x18df37(0x1a5)](_0x43ba3d=>_0x43ba3d[_0x18df37(0x201)]()):[];if(!_0x24ceec[_0x18df37(0x1de)])return console[_0x18df37(0x249)](_0x18df37(0x264)+_0x40c849),assetCache[_0x40c849]=_0x2779f8,console['timeEnd'](_0x18df37(0x1b1)+_0x40c849),_0x2779f8;const _0x31b20f=_0x5bd6fe[_0x18df37(0x290)]?_0x5bd6fe['orientations'][_0x18df37(0x136)](',')[_0x18df37(0x1a5)](_0x27bd2e=>_0x27bd2e[_0x18df37(0x201)]()):[],_0x5019df=_0x5bd6fe[_0x18df37(0x188)]?_0x5bd6fe['ipfsPrefixes'][_0x18df37(0x136)](',')[_0x18df37(0x1a5)](_0x1cda2=>_0x1cda2[_0x18df37(0x201)]()):[],_0x3d3d54=_0x24ceec[_0x18df37(0x92)]((_0x1a50c1,_0x266f5d)=>({'policyId':_0x1a50c1,'orientation':_0x31b20f[_0x18df37(0x1de)]===0x1?_0x31b20f[0x0]:_0x31b20f[_0x266f5d]||_0x18df37(0x86),'ipfsPrefix':_0x5019df[_0x18df37(0x1de)]===0x1?_0x5019df[0x0]:_0x5019df[_0x266f5d]||'https://ipfs.io/ipfs/'}));let _0x784799=[];try{const _0x291275=JSON['stringify']({'policyIds':_0x3d3d54['map'](_0x4f65b5=>_0x4f65b5[_0x18df37(0xc2)]),'theme':_0x40c849});console[_0x18df37(0x249)](_0x18df37(0x1fb));const _0x4f53b2=await Promise[_0x18df37(0x157)]([fetch(_0x18df37(0x1c5),{'method':_0x18df37(0x212),'headers':{'Content-Type':_0x18df37(0xd2)},'body':_0x291275}),new Promise((_0x2823b2,_0x17cfb4)=>setTimeout(()=>_0x17cfb4(new Error(_0x18df37(0xed))),0x3e8))]);if(!_0x4f53b2['ok'])throw new Error(_0x18df37(0xa0)+_0x4f53b2[_0x18df37(0xf9)]);const _0x311d3a=await _0x4f53b2['text']();let _0x5372e2;try{_0x5372e2=JSON['parse'](_0x311d3a);}catch(_0xeccff){console['error'](_0x18df37(0xac),_0xeccff);throw _0xeccff;}_0x5372e2===![]||_0x5372e2===_0x18df37(0xf4)?(console[_0x18df37(0x249)](_0x18df37(0x1f9)),_0x784799=[]):_0x784799=Array[_0x18df37(0x1aa)](_0x5372e2)?_0x5372e2:[_0x5372e2],_0x784799=_0x784799[_0x18df37(0x92)]((_0x15e8e8,_0x2158fe)=>{const _0x3ed5ff=_0x18df37,_0x4d8975={..._0x15e8e8,'theme':_0x40c849,'name':_0x15e8e8[_0x3ed5ff(0x24f)]||_0x3ed5ff(0xda)+_0x2158fe,'strength':_0x15e8e8[_0x3ed5ff(0x18c)]||0x4,'speed':_0x15e8e8[_0x3ed5ff(0x18a)]||0x4,'tactics':_0x15e8e8[_0x3ed5ff(0x25f)]||0x4,'size':_0x15e8e8['size']||_0x3ed5ff(0x187),'type':_0x15e8e8[_0x3ed5ff(0x1dc)]||'Base','powerup':_0x15e8e8[_0x3ed5ff(0xa3)]||_0x3ed5ff(0xbd),'policyId':_0x15e8e8['policyId']||_0x3d3d54[0x0][_0x3ed5ff(0xc2)],'ipfs':_0x15e8e8[_0x3ed5ff(0x11c)]||''};return _0x4d8975;});}catch(_0x1b4483){console[_0x18df37(0x209)](_0x18df37(0x120)+_0x40c849+':',_0x1b4483),_0x784799=[];}const _0x54d08b=[..._0x2779f8,..._0x784799];return console[_0x18df37(0x249)](_0x18df37(0x263)+_0x54d08b[_0x18df37(0x1de)]),assetCache[_0x40c849]=_0x54d08b,console[_0x18df37(0x1b4)](_0x18df37(0x1b1)+_0x40c849),_0x54d08b;}document[_0x5ca7c6(0xdf)](_0x5ca7c6(0x211),function(){var _0x39ebf8=function(){const _0x5b330a=_0x1cfe;var _0x29e9be=localStorage[_0x5b330a(0x134)](_0x5b330a(0x17e))||_0x5b330a(0x1e1);getAssets(_0x29e9be)['then'](function(_0x43eca3){const _0x571f21=_0x5b330a;console[_0x571f21(0x249)](_0x571f21(0x2a2),_0x43eca3);var _0x571ea0=new MonstrocityMatch3(_0x43eca3,_0x29e9be);console[_0x571f21(0x249)](_0x571f21(0x2a0)),_0x571ea0[_0x571f21(0xb0)]()[_0x571f21(0x223)](function(){const _0x46da2c=_0x571f21;console['log'](_0x46da2c(0x18d));});})[_0x5b330a(0x22c)](function(_0x24f446){const _0x48f60c=_0x5b330a;console['error'](_0x48f60c(0x1f8),_0x24f446);});};_0x39ebf8();});
  </script>
</body>
</html>
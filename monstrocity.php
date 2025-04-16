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

	.character img, .character video {
		width: 100%;
	    height: auto;
	    margin-bottom: 10px;
	    border-radius: 5px;
	    transition: transform 0.1slinear, filter 0.5sease;
	    -webkit-filter: drop-shadow(2px 5px 10px #000);
	    filter: drop-shadow(2px 5px 10px #000);
	    min-height: 265px;
	    background-color: #003044;
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

	.character-option img, .character-option video {
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
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "discosolaris",
	          project: "Disco Solaris",
	          title: "Moebius Pioneers",
	          policyIds: "9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "oculuslounge",
	          project: "Disco Solaris",
	          title: "Oculus Lounge",
	          policyIds: "d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "havocworlds",
	          project: "Havoc Worlds",
	          title: "Season 1",
	          policyIds: "1088b361c41f49906645cedeeb7a9ef0e0b793b1a2d24f623ea74876",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "muses",
	          project: "Josh Howard",
	          title: "Muses of the Multiverse",
	          policyIds: "7f95b5948e3efed1171523757b472f24aecfab8303612cfa1b6fec55",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
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
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "cardanocamera",
	          project: "Cardano Camera",
	          title: "Galaxy of Sons",
	          policyIds: "647535c1befd741bfa1ace4a5508e93fe03ff7590c26d372c8a812cb",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "darkula",
	          project: "Darkula",
	          title: "Island of the Uncanny Neighbors",
	          policyIds: "b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "darkula2",
	          project: "Darkula",
	          title: "Island of the Violent Neighbors",
	          policyIds: "b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "maxi",
	          project: "Maxingo",
	          title: "Digital Hell Citizens 2: Fighters",
	          policyIds: "b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "shortyverse",
	          project: "Ohh Meed",
	          title: "Shorty Verse",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "shortyverse2",
	          project: "Ohh Meed",
	          title: "Shorty Verse Engaged",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "bogeyman",
	          project: "Ritual",
	          title: "Bogeyman",
	          policyIds: "bca7c472792b859fb18920477f917c94b76c9c9705e039bf08af0b63",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "ritual",
	          project: "Ritual",
	          title: "John Doe",
	          policyIds: "16b10d60f428b03fa5bafa631c848b2243f31cbf93cce1a65779e5f5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "sinderskullz",
	          project: "Sinder Skullz",
	          title: "Sinder Skullz",
	          policyIds: "83732ff37818e7e520592fcd3e5257e429307d40a9f5437240e926de",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "skowl",
	          project: "Skowl",
	          title: "Derivative Heroes",
	          policyIds: "d38910b4b5bd3e634138dc027b507b52406acf687889e3719aa4f7cf",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
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
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "cardanians",
	          project: "Cardanians",
	          title: "Cardanian Snow Globes (gif)",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "gif" // Applies only to character images
	        },
	        {
	          value: "cardanians2",
	          project: "Cardanians",
	          title: "Cardanian Snow Globes (mov)",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "mov" // Applies only to character images
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
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "rubberrebels",
	          project: "Classic Cardtoons",
	          title: "Rubber Rebels",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "danketsu",
	          project: "Danketsu",
	          title: "Legends",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "danketsu2",
	          project: "Danketsu",
	          title: "The Fourth",
	          policyIds: "a4b7f3bbb16b028739efc983967f1e631883f63a2671d508023b5dfb",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "deadpophell",
	          project: "Dead Pop Hell",
	          title: "NSFW",
	          policyIds: "6710d32c862a616ba81ef00294e60fe56969949e0225452c48b5f0ed",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "moebiuspioneers",
	          project: "Disco Solaris",
	          title: "Legends",
	          policyIds: "9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "karranka",
	          project: "Karranka",
	          title: "Badass Heroes",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "karranka2",
	          project: "Karranka",
	          title: "Japanese Ghosts: Legendary Warriors",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "omen",
	          project: "Nemonium",
	          title: "Omen Legends",
	          policyIds: "da286f15e0de865e3d50fec6fa0484d7e2309671dc4ba8ce6bdd122b",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        }
	      ]
	    }
	    <?php } ?>
	  ];
	  
	  const _0x22bcae=_0x2bd9;(function(_0x1ebc77,_0x212d7f){const _0x56aec3=_0x2bd9,_0x3fb4ce=_0x1ebc77();while(!![]){try{const _0x3bce49=parseInt(_0x56aec3(0x2ab))/0x1*(-parseInt(_0x56aec3(0x161))/0x2)+parseInt(_0x56aec3(0x1ef))/0x3*(-parseInt(_0x56aec3(0x174))/0x4)+parseInt(_0x56aec3(0x33b))/0x5*(parseInt(_0x56aec3(0x1c5))/0x6)+-parseInt(_0x56aec3(0x246))/0x7*(-parseInt(_0x56aec3(0x183))/0x8)+-parseInt(_0x56aec3(0x231))/0x9*(-parseInt(_0x56aec3(0x307))/0xa)+-parseInt(_0x56aec3(0x2ee))/0xb*(-parseInt(_0x56aec3(0x2de))/0xc)+parseInt(_0x56aec3(0x1f0))/0xd;if(_0x3bce49===_0x212d7f)break;else _0x3fb4ce['push'](_0x3fb4ce['shift']());}catch(_0x8dfa55){_0x3fb4ce['push'](_0x3fb4ce['shift']());}}}(_0x30a2,0x31341));function showThemeSelect(_0x128e46){const _0x3c439c=_0x2bd9;console[_0x3c439c(0x2bb)](_0x3c439c(0x323));let _0x17dcdb=document['getElementById'](_0x3c439c(0x2d9));const _0x3634a7=document[_0x3c439c(0x28c)]('character-select-container');_0x17dcdb[_0x3c439c(0x1a8)]=_0x3c439c(0x320);const _0x1ce644=document[_0x3c439c(0x28c)]('theme-options');_0x17dcdb[_0x3c439c(0x2b3)]['display']=_0x3c439c(0x131),_0x3634a7[_0x3c439c(0x2b3)][_0x3c439c(0x1ac)]=_0x3c439c(0x2a3),themes[_0x3c439c(0x25c)](_0x4188a9=>{const _0x4e33af=_0x3c439c,_0x211bfb=document['createElement'](_0x4e33af(0x302));_0x211bfb[_0x4e33af(0x1ea)]='theme-group';const _0x388d30=document[_0x4e33af(0x2c8)]('h3');_0x388d30[_0x4e33af(0x179)]=_0x4188a9[_0x4e33af(0x16c)],_0x211bfb['appendChild'](_0x388d30),_0x4188a9['items'][_0x4e33af(0x25c)](_0x25af49=>{const _0x583c9f=_0x4e33af,_0x4e2e91=document[_0x583c9f(0x2c8)](_0x583c9f(0x302));_0x4e2e91[_0x583c9f(0x1ea)]=_0x583c9f(0x12d);if(_0x25af49['background']){const _0xa7db81=_0x583c9f(0x300)+_0x25af49[_0x583c9f(0x19b)]+_0x583c9f(0x329);_0x4e2e91[_0x583c9f(0x2b3)]['backgroundImage']=_0x583c9f(0x1d4)+_0xa7db81+')';}const _0x328484=_0x583c9f(0x300)+_0x25af49[_0x583c9f(0x19b)]+'/logo.png';_0x4e2e91[_0x583c9f(0x1a8)]=_0x583c9f(0x2e2)+_0x328484+'\x22\x20alt=\x22'+_0x25af49[_0x583c9f(0x327)]+'\x22\x20data-project=\x22'+_0x25af49[_0x583c9f(0x1aa)]+_0x583c9f(0x321)+_0x25af49[_0x583c9f(0x327)]+_0x583c9f(0x158),_0x4e2e91[_0x583c9f(0x1d1)](_0x583c9f(0x1b3),()=>{const _0x5c4e27=_0x583c9f,_0x368375=document[_0x5c4e27(0x28c)](_0x5c4e27(0x19d));_0x368375&&(_0x368375[_0x5c4e27(0x1a8)]=_0x5c4e27(0x1c3)),_0x17dcdb['innerHTML']='',_0x17dcdb[_0x5c4e27(0x2b3)][_0x5c4e27(0x1ac)]=_0x5c4e27(0x2a3),_0x3634a7[_0x5c4e27(0x2b3)]['display']=_0x5c4e27(0x131),_0x128e46[_0x5c4e27(0x243)](_0x25af49['value']);}),_0x211bfb['appendChild'](_0x4e2e91);}),_0x1ce644[_0x4e33af(0x14e)](_0x211bfb);}),console[_0x3c439c(0x227)](_0x3c439c(0x323));}function _0x30a2(){const _0x32624e=['ajax/get-monstrocity-assets.php','ajax/clear-monstrocity-progress.php','px)\x20','init:\x20Async\x20initialization\x20completed','\x27s\x20',',\x20cols\x20','orientation','Game\x20over,\x20skipping\x20cascade\x20resolution','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','p2-name','gameTheme','className','parse','progress-modal-buttons','\x20but\x20dulls\x20tactics\x20to\x20','addEventListeners:\x20Player\x201\x20media\x20clicked','743427CvFQvx','1066338tRdAda','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','querySelector','animatePowerup','dataset','ajax/get-nft-assets.php','getAssets:\x20Returning\x20merged\x20assets,\x20count=','p2-powerup','p1-hp','button','No\x20match,\x20reverting\x20tiles...','isDragging','updateTileSizeWithGap','checkMatches\x20started','\x20/\x20','progress-modal','checkGameOver\x20completed:\x20currentLevel=','background','filter','px,\x20','Battle\x20Damaged','Game\x20state\x20reset\x20to:\x20','Animating\x20matched\x20tiles,\x20allMatchedTiles:','<img\x20loading=\x22eager\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22\x20src=\x22','handleGameOverButton\x20completed:\x20currentLevel=','win','backgroundSize','https://www.skulliance.io/staking/sounds/badmove.ogg','\x20due\x20to\x20','backgroundColor','battle-damaged','maxHealth',',\x20matches=','push','checkGameOver\x20skipped:\x20gameOver=','progress-modal-content','handleMatch\x20started,\x20match:','Dankle','monstrocity','\x20on\x20','createCharacter:\x20config=','mousedown','visibility','firstChild','p1-strength','speed','#theme-select\x20option[value=\x22','Game\x20over,\x20skipping\x20endTurn','getAssets:\x20Monstrocity\x20status=','width','offsetX','playerCharacters','type','init:\x20Starting\x20async\x20initialization','selected','timeEnd','.game-logo','isCheckingGameOver','then','<p>Strength:\x20','powerGem','\x20Score:\x20','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','createRandomTile','extension','360117rZBHPT','swapPlayerCharacter','column','flip-p2','false','coordinates','multiMatch','applyAnimation','split','VIDEO','progress','points','\x20uses\x20Heal,\x20restoring\x20','game-board','Small','p1-speed','inline-block','maxTouchPoints','updateTheme','getTileFromEvent',',\x20Grand\x20Total\x20Score:\x20','7EsQmEj','last-stand','HTTP\x20error!\x20Status:\x20','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','Final\x20level\x20completed!\x20Final\x20score:\x20','translate(0,\x200)','Parsed\x20response:','setBackground:\x20Setting\x20background\x20to\x20','Is\x20initial\x20move:\x20','\x20uses\x20Regen,\x20restoring\x20','cascadeTilesWithoutRender','Monstrocity\x20timeout','https://www.skulliance.io/staking/sounds/voice_go.ogg',',\x20Total\x20damage:\x20','character-select-container','tactics','level','has','Round\x20points\x20increased\x20from\x20','winner','https://www.skulliance.io/staking/sounds/powergem_created.ogg','\x20health\x20before\x20match:\x20','forEach','transform','transform\x20','Heal','\x20-\x20','Merdock','currentLevel','</p>','\x20passes...','cover','Failed\x20to\x20preload:\x20','items','Katastrophy','score','backgroundPosition','touchstart','\x27s\x20orientation\x20flipped\x20to\x20','Found\x20','https://ipfs.io/ipfs/','Error\x20saving\x20to\x20database:','board','Large','sounds','top','<p><strong>','Preloaded:\x20','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','Score\x20Saved:\x20Level\x20',')\x20/\x20100)\x20*\x20(','handleGameOverButton','isTouchDevice','#FFA500','lastStandActive','Error\x20saving\x20progress:','Random','setBackground:\x20themeData=','clientX','insertBefore','Regenerate','px)','Goblin\x20Ganger','trim','NFT\x20timeout','\x20/\x2056)\x20=\x20','Round\x20Score\x20Formula:\x20(((','renderBoard','findAIMove','scaleX(-1)','getElementById','\x20health\x20after\x20damage:\x20','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','Tile\x20at\x20(','showCharacterSelect:\x20Character\x20selected:\x20','createCharacter','updateOpponentDisplay','getAssets:\x20No\x20policy\x20IDs\x20for\x20theme\x20','Resume','Spydrax','onload','Main:\x20Player\x20characters\x20loaded:','NFT\x20HTTP\x20error!\x20Status:\x20','glow-recoil','Craig','Drake','init','badMove','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','abs','\x27s\x20Last\x20Stand\x20mitigates\x20','matched','replace','none','Game\x20over,\x20skipping\x20cascadeTiles','classList','warn','\x20(originally\x20','\x20size\x20','Raw\x20response\x20text:','Last\x20Stand\x20applied,\x20mitigated\x20','1jJoRVv','translate(0,\x20','flipCharacter','showCharacterSelect:\x20this.player1\x20set:\x20','toLowerCase','updatePlayerDisplay','Game\x20over,\x20skipping\x20recoil\x20animation','Animating\x20powerup','style','gameState','flex','ajax/save-monstrocity-score.php','Loaded\x20opponent\x20for\x20level\x20','Response\x20status:','endTurn','body','time','saveProgress','https://www.skulliance.io/staking/icons/',',\x20score=','checkMatches','dragDirection','\x27s\x20Boost\x20fades.','game-over-container','orientations','getAssets:\x20NFT\x20parse\x20error:','Koipon','Main:\x20Error\x20initializing\x20game:','Error\x20updating\x20theme\x20assets:','createElement','p2-speed','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php',',\x20player2.health=','START\x20OVER','s\x20linear','text','initializing','IMG','isArray','attempts','isNFT','mov','success','Sending\x20saveProgress\x20request\x20with\x20data:','\x20goes\x20first!','Slash','theme-select-container','Jarhead','message','mediaType','mp4','79284RqzkyL','Opponent','transition','animating','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','Leader','You\x20Win!','log','init:\x20Prompting\x20with\x20loadedLevel=','p1-image','flatMap','ajax/load-monstrocity-progress.php','Base\x20damage:\x20','removeEventListener','\x20uses\x20','No\x20progress\x20found\x20or\x20status\x20not\x20success:','110WmLacE','showProgressPopup:\x20User\x20chose\x20Resume','playerCharactersConfig','element','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','video','add','alt','handleMouseUp','\x20uses\x20Last\x20Stand,\x20dealing\x20','remove','Progress\x20saved:\x20Level\x20','Minor\x20Regen','aiTurn','drops\x20health\x20to\x20','<p>Speed:\x20','find','Calling\x20checkGameOver\x20from\x20handleMatch','https://www.skulliance.io/staking/images/monstrocity/','canMakeMatch','div','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','ipfsPrefixes','p2-image','</strong></p>','10tHIxld','p1-type','Texby','handleTouchMove','loss','getAssets:\x20Returning\x20Monstrocity\x20assets','handleTouchStart',',\x20isCheckingGameOver=','theme-select-button','handleMatch\x20completed,\x20damage\x20dealt:\x20','battle-damaged/','png','Base','\x20for\x20','Level\x20','targetTile','map','player1','round','currentTurn','baseImagePath','Vertical\x20match\x20found\x20at\x20col\x20','loadProgress','application/json','checkGameOver','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>','Failed\x20to\x20save\x20progress:','showThemeSelect','random','Player','Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)','title','Cascade\x20complete,\x20ending\x20turn','/monstrocity.png','height',',\x20selected\x20theme=','powerup','getAssets:\x20Monstrocity\x20data=','strength','#F44336','\x20HP','<p>Power-Up:\x20','handleMouseMove','handleMatch','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)','glow-power-up','https://www.skulliance.io/staking/sounds/speedmatch1.ogg','toFixed','imageUrl','\x27s\x20tactics)','mousemove','22105Caxpcf','updateHealth','totalTiles','<video\x20src=\x22','offsetY','autoplay','Billandar\x20and\x20Ted','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','name','Error\x20clearing\x20progress:','Boost\x20Attack','resolveMatches','<p>Health:\x20','translate(','stringify','msMaxTouchPoints','setBackground','getItem','tileTypes','p1-size','Round\x20Score:\x20','left','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','Score\x20Not\x20Saved:\x20','Resume\x20from\x20Level\x20','cascadeTiles','replaceChild','Save\x20response:','GET','character-option','Error\x20playing\x20lose\x20sound:','boosts\x20health\x20to\x20','\x20swaps\x20tiles\x20at\x20(','min','getAssets:\x20Sending\x20NFT\x20POST','Round\x20Won!\x20Points:\x20','\x20damage!',',\x20currentLevel=','showCharacterSelect',',\x20Completions:\x20','loop','theme-option',',\x20healthPercentage=','getAssets:\x20Using\x20default\x20Monstrocity\x20assets','json','block','length','Game\x20Over','touches',',\x20Match\x20bonus:\x20','tagName','player2','handleTouchEnd','Mandiblus','animateAttack','querySelectorAll','getBoundingClientRect',',\x20Matches:\x20','px)\x20scale(1.05)','p2-hp','Bite','p2-type','getAssets:\x20Monstrocity\x20fetch\x20error:','p1-name','initGame','Minor\x20Régén',',\x20gameOver=','playerTurn','\x20created\x20a\x20match\x20of\x20','touchend','setBackground:\x20Attempting\x20for\x20theme=','\x20damage','Restart','p2-size','appendChild','onclick','theme','grandTotalScore','\x20+\x2020))\x20*\x20(1\x20+\x20','onerror','addEventListeners','Right','visible','\x20matches:','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','selectedTile','policyIds','hyperCube','Starting\x20fresh\x20at\x20Level\x201','\x22></video>','\x20defeats\x20','p2-strength','special-attack','319346wTwPww','progress-resume','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20','img','TRY\x20AGAIN','pop','Mega\x20Multi-Match!\x20','DOMContentLoaded','battle-log','gameOver','src','group','Shadow\x20Strike','matches','backgroundImage','policyId','<p>Size:\x20','parentNode','checkGameOver\x20started:\x20currentLevel=','4NCtYFz','roundStats','tileSizeWithGap','clearProgress','constructor:\x20initialTheme=','textContent','Error\x20saving\x20score:\x20','\x20after\x20multi-match\x20bonus!','Damage\x20from\x20match:\x20',',\x20rows\x20','updateTheme_','first-attack','#FFC105','Saving\x20score:\x20level=','showProgressPopup','219528kSENZa','error',',\x20Score\x20','\x20starts\x20at\x20full\x20strength\x20with\x20','size','Monstrocity_Unknown_','Medium','\x20HP!','play','change-character','preventDefault','Progress\x20cleared','initBoard','getAssets:\x20Fetching\x20Monstrocity\x20assets','completed','center','loser','max','second-attack','restart','Animating\x20recoil\x20for\x20defender:','Slime\x20Mind','ajax/save-monstrocity-progress.php','muted','value','Turn\x20switched\x20to\x20','character-options','Main:\x20Game\x20instance\x20created','POST','boostActive','updateCharacters_','includes','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','indexOf','Ouchie','handleMouseDown','\x20(100%\x20bonus\x20for\x20match-5+)','innerHTML','Cascade:\x20','project','floor','display','catch','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','p1-powerup','NEXT\x20LEVEL',',\x20player1.health=','Left','click','\x22\x20autoplay\x20loop\x20muted\x20alt=\x22','p2-tactics','getAssets_','match','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','resolveMatches\x20started,\x20gameOver:','<p>Type:\x20','\x27s\x20Turn','game-over','status','Multi-Match!\x20','healthPercentage','clientY','.png','row','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','Horizontal\x20match\x20found\x20at\x20row\x20','534HikSpt','transform\x200.2s\x20ease','flip-p1','p2-health','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','.tile','boostValue','power-up','ipfs',',\x20reduced\x20by\x20','usePowerup','try-again','addEventListener','https://www.skulliance.io/staking/sounds/select.ogg','lastChild','url(','race','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','slideTiles','base','health','Cascading\x20tiles','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','image','Game\x20completed!\x20Grand\x20total\x20score\x20reset.'];_0x30a2=function(){return _0x32624e;};return _0x30a2();}const opponentsConfig=[{'name':_0x22bcae(0x29a),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':'Minor\x20Regen','theme':'monstrocity'},{'name':_0x22bcae(0x261),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x22bcae(0x271),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x2fa),'theme':_0x22bcae(0x216)},{'name':'Goblin\x20Ganger','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x22bcae(0x23f),'type':_0x22bcae(0x313),'powerup':'Minor\x20Regen','theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x309),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x2fa),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x139),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x282),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x2c5),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x282),'theme':_0x22bcae(0x216)},{'name':'Slime\x20Mind','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x22bcae(0x23f),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x282),'theme':'monstrocity'},{'name':_0x22bcae(0x341),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':'Regenerate','theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x215),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0x22bcae(0x313),'powerup':_0x22bcae(0x345),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x2da),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0x22bcae(0x313),'powerup':_0x22bcae(0x345),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x295),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x22bcae(0x313),'powerup':_0x22bcae(0x25f),'theme':'monstrocity'},{'name':_0x22bcae(0x268),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x22bcae(0x271),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x25f),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x1a5),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x25f),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x29b),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x22bcae(0x189),'type':_0x22bcae(0x313),'powerup':_0x22bcae(0x25f),'theme':'monstrocity'},{'name':_0x22bcae(0x29a),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x22bcae(0x189),'type':_0x22bcae(0x2e3),'powerup':'Minor\x20Regen','theme':'monstrocity'},{'name':'Merdock','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x22bcae(0x271),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x2fa),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x284),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x22bcae(0x23f),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x2fa),'theme':_0x22bcae(0x216)},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x22bcae(0x189),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x145),'theme':_0x22bcae(0x216)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':'Leader','powerup':_0x22bcae(0x282),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x2c5),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x22bcae(0x189),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x282),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x198),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x22bcae(0x23f),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x282),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x341),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x22bcae(0x189),'type':'Leader','powerup':_0x22bcae(0x282),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x215),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x22bcae(0x189),'type':_0x22bcae(0x2e3),'powerup':'Boost\x20Attack','theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x2da),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x22bcae(0x189),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x345),'theme':'monstrocity'},{'name':_0x22bcae(0x295),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x25f),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x268),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x22bcae(0x271),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x25f),'theme':_0x22bcae(0x216)},{'name':_0x22bcae(0x1a5),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x22bcae(0x189),'type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x25f),'theme':_0x22bcae(0x216)},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x22bcae(0x2e3),'powerup':_0x22bcae(0x25f),'theme':_0x22bcae(0x216)}],characterDirections={'Billandar\x20and\x20Ted':_0x22bcae(0x1b2),'Craig':_0x22bcae(0x1b2),'Dankle':'Left','Drake':_0x22bcae(0x155),'Goblin\x20Ganger':_0x22bcae(0x1b2),'Jarhead':_0x22bcae(0x155),'Katastrophy':'Right','Koipon':_0x22bcae(0x1b2),'Mandiblus':'Left','Merdock':_0x22bcae(0x1b2),'Ouchie':_0x22bcae(0x1b2),'Slime\x20Mind':_0x22bcae(0x155),'Spydrax':_0x22bcae(0x155),'Texby':'Left'};class MonstrocityMatch3{constructor(_0x294c9e,_0x556798){const _0x2270f1=_0x22bcae;this[_0x2270f1(0x27a)]='ontouchstart'in window||navigator[_0x2270f1(0x242)]>0x0||navigator[_0x2270f1(0x34a)]>0x0,this['width']=0x5,this['height']=0x5,this[_0x2270f1(0x270)]=[],this['selectedTile']=null,this['gameOver']=![],this[_0x2270f1(0x31a)]=null,this[_0x2270f1(0x318)]=null,this[_0x2270f1(0x137)]=null,this[_0x2270f1(0x2b4)]=_0x2270f1(0x2cf),this[_0x2270f1(0x1fb)]=![],this[_0x2270f1(0x316)]=null,this[_0x2270f1(0x2c0)]=null,this['offsetX']=0x0,this[_0x2270f1(0x33f)]=0x0,this['currentLevel']=0x1,this['playerCharactersConfig']=_0x294c9e,this['playerCharacters']=[],this[_0x2270f1(0x229)]=![],this['tileTypes']=[_0x2270f1(0x17f),_0x2270f1(0x195),_0x2270f1(0x160),_0x2270f1(0x1cc),_0x2270f1(0x247)],this[_0x2270f1(0x175)]=[],this[_0x2270f1(0x151)]=0x0;const _0x1aeb5a=themes[_0x2270f1(0x2e8)](_0xc41cbd=>_0xc41cbd['items'])[_0x2270f1(0x317)](_0x2c3d08=>_0x2c3d08[_0x2270f1(0x19b)]),_0x15b095=localStorage[_0x2270f1(0x34c)](_0x2270f1(0x1e9));this['theme']=_0x15b095&&_0x1aeb5a[_0x2270f1(0x1a2)](_0x15b095)?_0x15b095:_0x556798&&_0x1aeb5a['includes'](_0x556798)?_0x556798:_0x2270f1(0x216),console[_0x2270f1(0x2e5)](_0x2270f1(0x178)+_0x556798+',\x20storedTheme='+_0x15b095+_0x2270f1(0x32b)+this[_0x2270f1(0x150)]),this['baseImagePath']=_0x2270f1(0x300)+this['theme']+'/',this[_0x2270f1(0x272)]={'match':new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),'cascade':new Audio(_0x2270f1(0x1d2)),'badMove':new Audio(_0x2270f1(0x20b)),'gameOver':new Audio(_0x2270f1(0x1d6)),'reset':new Audio(_0x2270f1(0x252)),'loss':new Audio(_0x2270f1(0x249)),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio(_0x2270f1(0x1db)),'powerGem':new Audio(_0x2270f1(0x25a)),'hyperCube':new Audio(_0x2270f1(0x1ae)),'multiMatch':new Audio(_0x2270f1(0x336))},this[_0x2270f1(0x1fc)](),this[_0x2270f1(0x154)]();}async[_0x22bcae(0x29c)](){const _0x2e6b52=_0x22bcae;console[_0x2e6b52(0x2e5)](_0x2e6b52(0x225)),this[_0x2e6b52(0x223)]=this[_0x2e6b52(0x2f0)][_0x2e6b52(0x317)](_0x185f63=>this[_0x2e6b52(0x291)](_0x185f63)),await this['showCharacterSelect'](!![]);const _0x28957f=await this[_0x2e6b52(0x31d)](),{loadedLevel:_0x20ceb9,loadedScore:_0x13690c,hasProgress:_0x3cd998}=_0x28957f;if(_0x3cd998){console[_0x2e6b52(0x2e5)](_0x2e6b52(0x2e6)+_0x20ceb9+',\x20loadedScore='+_0x13690c);const _0x2d50cd=await this[_0x2e6b52(0x182)](_0x20ceb9,_0x13690c);_0x2d50cd?(this['currentLevel']=_0x20ceb9,this[_0x2e6b52(0x151)]=_0x13690c,log('Resumed\x20at\x20Level\x20'+this[_0x2e6b52(0x262)]+_0x2e6b52(0x185)+this[_0x2e6b52(0x151)])):(this[_0x2e6b52(0x262)]=0x1,this['grandTotalScore']=0x0,await this[_0x2e6b52(0x177)](),log(_0x2e6b52(0x15c)));}else this[_0x2e6b52(0x262)]=0x1,this[_0x2e6b52(0x151)]=0x0,log('No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201');console[_0x2e6b52(0x2e5)](_0x2e6b52(0x1e2));}[_0x22bcae(0x34b)](){const _0x3b27ef=_0x22bcae;console[_0x3b27ef(0x2e5)](_0x3b27ef(0x14a)+this[_0x3b27ef(0x150)]);const _0xd23a8f=themes[_0x3b27ef(0x2e8)](_0x2c056f=>_0x2c056f[_0x3b27ef(0x267)])['find'](_0x1d1bd9=>_0x1d1bd9[_0x3b27ef(0x19b)]===this[_0x3b27ef(0x150)]);console['log'](_0x3b27ef(0x27f),_0xd23a8f);const _0x5f1847='https://www.skulliance.io/staking/images/monstrocity/'+this['theme']+_0x3b27ef(0x329);console[_0x3b27ef(0x2e5)](_0x3b27ef(0x24d)+_0x5f1847),_0xd23a8f&&_0xd23a8f[_0x3b27ef(0x201)]?(document[_0x3b27ef(0x2ba)][_0x3b27ef(0x2b3)][_0x3b27ef(0x16f)]=_0x3b27ef(0x1d4)+_0x5f1847+')',document[_0x3b27ef(0x2ba)][_0x3b27ef(0x2b3)][_0x3b27ef(0x20a)]=_0x3b27ef(0x265),document[_0x3b27ef(0x2ba)]['style'][_0x3b27ef(0x26a)]=_0x3b27ef(0x192)):document[_0x3b27ef(0x2ba)]['style'][_0x3b27ef(0x16f)]=_0x3b27ef(0x2a3);}[_0x22bcae(0x243)](_0x4a342f){const _0x51bd96=_0x22bcae;if(updatePending){console[_0x51bd96(0x2e5)](_0x51bd96(0x28e));return;}updatePending=!![],console['time'](_0x51bd96(0x17e)+_0x4a342f);var _0x830f08=this;this['theme']=_0x4a342f,this[_0x51bd96(0x31b)]=_0x51bd96(0x300)+this[_0x51bd96(0x150)]+'/',localStorage['setItem'](_0x51bd96(0x1e9),this[_0x51bd96(0x150)]),this['setBackground'](),document[_0x51bd96(0x1f2)](_0x51bd96(0x228))['src']=this[_0x51bd96(0x31b)]+'logo.png',getAssets(this[_0x51bd96(0x150)])[_0x51bd96(0x22a)](function(_0x2b94b4){const _0x321931=_0x51bd96;console[_0x321931(0x2bb)](_0x321931(0x1a1)+_0x4a342f),_0x830f08[_0x321931(0x2f0)]=_0x2b94b4,_0x830f08[_0x321931(0x223)]=[],_0x2b94b4['forEach'](_0x56a296=>{const _0x348052=_0x321931,_0x2dc046=_0x830f08['createCharacter'](_0x56a296),_0x1cbf61=new Image();_0x1cbf61['src']=_0x2dc046[_0x348052(0x338)],_0x1cbf61[_0x348052(0x296)]=()=>console['log'](_0x348052(0x275)+_0x2dc046[_0x348052(0x338)]),_0x1cbf61[_0x348052(0x153)]=()=>console[_0x348052(0x2e5)](_0x348052(0x266)+_0x2dc046[_0x348052(0x338)]),_0x830f08[_0x348052(0x223)][_0x348052(0x211)](_0x2dc046);});if(_0x830f08['player1']){var _0x277eff=_0x830f08[_0x321931(0x2f0)][_0x321931(0x2fe)](function(_0x2dd4ba){const _0x531ffe=_0x321931;return _0x2dd4ba[_0x531ffe(0x343)]===_0x830f08[_0x531ffe(0x318)][_0x531ffe(0x343)];})||_0x830f08['playerCharactersConfig'][0x0];_0x830f08[_0x321931(0x318)]=_0x830f08[_0x321931(0x291)](_0x277eff),_0x830f08[_0x321931(0x2b0)]();}_0x830f08['player2']&&(_0x830f08[_0x321931(0x137)]=_0x830f08[_0x321931(0x291)](opponentsConfig[_0x830f08['currentLevel']-0x1]),_0x830f08[_0x321931(0x292)]());const _0x5883cd=document[_0x321931(0x13b)](_0x321931(0x1ca));_0x5883cd[_0x321931(0x25c)](_0x477536=>{const _0x3bd17c=_0x321931;_0x477536[_0x3bd17c(0x2eb)](_0x3bd17c(0x219),_0x830f08[_0x3bd17c(0x1a6)]),_0x477536[_0x3bd17c(0x2eb)](_0x3bd17c(0x26b),_0x830f08[_0x3bd17c(0x30d)]);}),_0x830f08[_0x321931(0x289)](),_0x830f08['isDragging']=![],_0x830f08['selectedTile']=null,_0x830f08[_0x321931(0x316)]=null,_0x830f08[_0x321931(0x2b4)]=_0x830f08[_0x321931(0x31a)]===_0x830f08[_0x321931(0x318)]?_0x321931(0x147):_0x321931(0x2fb),console[_0x321931(0x2e5)](_0x321931(0x205)+_0x830f08[_0x321931(0x2b4)]);var _0x2452a0=document[_0x321931(0x28c)](_0x321931(0x254));_0x2452a0[_0x321931(0x2b3)][_0x321931(0x1ac)]===_0x321931(0x131)&&_0x830f08[_0x321931(0x12a)](_0x830f08[_0x321931(0x318)]===null),console[_0x321931(0x227)]('updateCharacters_'+_0x4a342f),console[_0x321931(0x227)](_0x321931(0x17e)+_0x4a342f),updatePending=![];})[_0x51bd96(0x1ad)](function(_0x38e3c5){const _0x12cad7=_0x51bd96;console[_0x12cad7(0x184)](_0x12cad7(0x2c7),_0x38e3c5),console[_0x12cad7(0x227)]('updateTheme_'+_0x4a342f),updatePending=![];});}async[_0x22bcae(0x2bc)](){const _0x58b1f9=_0x22bcae,_0x1fb95e={'currentLevel':this[_0x58b1f9(0x262)],'grandTotalScore':this[_0x58b1f9(0x151)]};console['log'](_0x58b1f9(0x2d6),_0x1fb95e);try{const _0x5ac555=await fetch(_0x58b1f9(0x199),{'method':_0x58b1f9(0x19f),'headers':{'Content-Type':_0x58b1f9(0x31e)},'body':JSON[_0x58b1f9(0x349)](_0x1fb95e)});console[_0x58b1f9(0x2e5)](_0x58b1f9(0x2b8),_0x5ac555[_0x58b1f9(0x1bd)]);const _0x429484=await _0x5ac555[_0x58b1f9(0x2ce)]();console[_0x58b1f9(0x2e5)](_0x58b1f9(0x2a9),_0x429484);if(!_0x5ac555['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x5ac555[_0x58b1f9(0x1bd)]);const _0x299e89=JSON[_0x58b1f9(0x1eb)](_0x429484);console[_0x58b1f9(0x2e5)](_0x58b1f9(0x24c),_0x299e89),_0x299e89[_0x58b1f9(0x1bd)]==='success'?log(_0x58b1f9(0x2f9)+this[_0x58b1f9(0x262)]):console[_0x58b1f9(0x184)](_0x58b1f9(0x322),_0x299e89[_0x58b1f9(0x2db)]);}catch(_0x24b353){console[_0x58b1f9(0x184)](_0x58b1f9(0x27d),_0x24b353);}}async[_0x22bcae(0x31d)](){const _0xfd3c90=_0x22bcae;try{console['log'](_0xfd3c90(0x2ca));const _0x224700=await fetch(_0xfd3c90(0x2e9),{'method':_0xfd3c90(0x357),'headers':{'Content-Type':_0xfd3c90(0x31e)}});console[_0xfd3c90(0x2e5)](_0xfd3c90(0x2b8),_0x224700[_0xfd3c90(0x1bd)]);if(!_0x224700['ok'])throw new Error(_0xfd3c90(0x248)+_0x224700[_0xfd3c90(0x1bd)]);const _0x12ffa9=await _0x224700[_0xfd3c90(0x130)]();console[_0xfd3c90(0x2e5)](_0xfd3c90(0x24c),_0x12ffa9);if(_0x12ffa9[_0xfd3c90(0x1bd)]===_0xfd3c90(0x2d5)&&_0x12ffa9[_0xfd3c90(0x23b)]){const _0x3bbc42=_0x12ffa9[_0xfd3c90(0x23b)];return{'loadedLevel':_0x3bbc42[_0xfd3c90(0x262)]||0x1,'loadedScore':_0x3bbc42['grandTotalScore']||0x0,'hasProgress':!![]};}else return console[_0xfd3c90(0x2e5)](_0xfd3c90(0x2ed),_0x12ffa9),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x144b1f){return console['error']('Error\x20loading\x20progress:',_0x144b1f),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x22bcae(0x177)](){const _0x244c98=_0x22bcae;try{const _0x51b1e5=await fetch(_0x244c98(0x1e0),{'method':_0x244c98(0x19f),'headers':{'Content-Type':_0x244c98(0x31e)}});if(!_0x51b1e5['ok'])throw new Error(_0x244c98(0x248)+_0x51b1e5[_0x244c98(0x1bd)]);const _0x6eef81=await _0x51b1e5[_0x244c98(0x130)]();_0x6eef81[_0x244c98(0x1bd)]==='success'&&(this[_0x244c98(0x262)]=0x1,this[_0x244c98(0x151)]=0x0,log(_0x244c98(0x18e)));}catch(_0x154efa){console[_0x244c98(0x184)](_0x244c98(0x344),_0x154efa);}}[_0x22bcae(0x1fc)](){const _0x1b2040=_0x22bcae,_0x156056=document[_0x1b2040(0x28c)](_0x1b2040(0x23e)),_0x58550b=_0x156056['offsetWidth']||0x12c;this[_0x1b2040(0x176)]=(_0x58550b-0.5*(this['width']-0x1))/this[_0x1b2040(0x221)];}[_0x22bcae(0x291)](_0x63a22b){const _0x5ceccc=_0x22bcae;console[_0x5ceccc(0x2e5)](_0x5ceccc(0x218),_0x63a22b);var _0x396c57,_0x448b75,_0x376922='Left',_0x9c2b16=![],_0x20b1df=_0x5ceccc(0x1dd);const _0x1f16f7=themes[_0x5ceccc(0x2e8)](_0x1118a0=>_0x1118a0[_0x5ceccc(0x267)])[_0x5ceccc(0x2fe)](_0x1e2247=>_0x1e2247[_0x5ceccc(0x19b)]===this[_0x5ceccc(0x150)]),_0x5767f8=_0x1f16f7?.[_0x5ceccc(0x230)]||_0x5ceccc(0x312),_0x619086=[_0x5ceccc(0x2d4),_0x5ceccc(0x2dd)];if(_0x63a22b[_0x5ceccc(0x1cd)]&&_0x63a22b[_0x5ceccc(0x170)]){_0x9c2b16=!![];var _0x5bbcc0=document[_0x5ceccc(0x1f2)](_0x5ceccc(0x21e)+_0x63a22b[_0x5ceccc(0x150)]+'\x22]'),_0x2e8926={'orientation':_0x5ceccc(0x155),'ipfsPrefix':_0x5ceccc(0x26e)};if(_0x5bbcc0){var _0x41e024=_0x5bbcc0[_0x5ceccc(0x1f4)]['policyIds']?_0x5bbcc0[_0x5ceccc(0x1f4)]['policyIds'][_0x5ceccc(0x239)](',')[_0x5ceccc(0x202)](function(_0x42da90){const _0x207361=_0x5ceccc;return _0x42da90[_0x207361(0x285)]();}):[],_0x4bd16d=_0x5bbcc0['dataset'][_0x5ceccc(0x2c3)]?_0x5bbcc0[_0x5ceccc(0x1f4)][_0x5ceccc(0x2c3)]['split'](',')['filter'](function(_0x3f4a71){const _0x25f360=_0x5ceccc;return _0x3f4a71[_0x25f360(0x285)]();}):[],_0x16f84c=_0x5bbcc0['dataset']['ipfsPrefixes']?_0x5bbcc0[_0x5ceccc(0x1f4)][_0x5ceccc(0x304)][_0x5ceccc(0x239)](',')[_0x5ceccc(0x202)](function(_0x50a5ec){const _0x46a848=_0x5ceccc;return _0x50a5ec[_0x46a848(0x285)]();}):[],_0x5ac8d0=_0x41e024[_0x5ceccc(0x1a4)](_0x63a22b[_0x5ceccc(0x170)]);_0x5ac8d0!==-0x1&&(_0x2e8926={'orientation':_0x4bd16d[_0x5ceccc(0x132)]===0x1?_0x4bd16d[0x0]:_0x4bd16d[_0x5ac8d0]||_0x5ceccc(0x155),'ipfsPrefix':_0x16f84c[_0x5ceccc(0x132)]===0x1?_0x16f84c[0x0]:_0x16f84c[_0x5ac8d0]||'https://ipfs.io/ipfs/'});}_0x2e8926[_0x5ceccc(0x1e5)]===_0x5ceccc(0x27e)?_0x376922=Math['random']()<0.5?_0x5ceccc(0x1b2):_0x5ceccc(0x155):_0x376922=_0x2e8926[_0x5ceccc(0x1e5)];_0x448b75=_0x2e8926['ipfsPrefix']+_0x63a22b[_0x5ceccc(0x1cd)];const _0x42cb2e=_0x448b75[_0x5ceccc(0x239)]('.')[_0x5ceccc(0x166)]()[_0x5ceccc(0x2af)]();_0x619086[_0x5ceccc(0x1a2)](_0x42cb2e)&&(_0x20b1df=_0x5ceccc(0x2f3));}else{switch(_0x63a22b[_0x5ceccc(0x224)]){case _0x5ceccc(0x313):_0x396c57=_0x5ceccc(0x1d8);break;case _0x5ceccc(0x2e3):_0x396c57='leader';break;case _0x5ceccc(0x204):_0x396c57=_0x5ceccc(0x20e);break;default:_0x396c57=_0x5ceccc(0x1d8);}_0x448b75=this[_0x5ceccc(0x31b)]+_0x396c57+'/'+_0x63a22b['name'][_0x5ceccc(0x2af)]()[_0x5ceccc(0x2a2)](/ /g,'-')+'.'+_0x5767f8,_0x376922=characterDirections[_0x63a22b['name']]||_0x5ceccc(0x1b2),_0x619086[_0x5ceccc(0x1a2)](_0x5767f8[_0x5ceccc(0x2af)]())&&(_0x20b1df='video');}var _0xf47324;switch(_0x63a22b[_0x5ceccc(0x224)]){case _0x5ceccc(0x2e3):_0xf47324=0x64;break;case _0x5ceccc(0x204):_0xf47324=0x46;break;case _0x5ceccc(0x313):default:_0xf47324=0x55;}var _0x5090df=0x1,_0x428bb4=0x0;switch(_0x63a22b[_0x5ceccc(0x187)]){case _0x5ceccc(0x271):_0x5090df=1.2,_0x428bb4=_0x63a22b[_0x5ceccc(0x255)]>0x1?-0x2:0x0;break;case'Small':_0x5090df=0.8,_0x428bb4=_0x63a22b['tactics']<0x6?0x2:0x7-_0x63a22b[_0x5ceccc(0x255)];break;case _0x5ceccc(0x189):_0x5090df=0x1,_0x428bb4=0x0;break;}var _0x5f08ef=Math[_0x5ceccc(0x319)](_0xf47324*_0x5090df),_0x4c6bac=Math['max'](0x1,Math['min'](0x7,_0x63a22b[_0x5ceccc(0x255)]+_0x428bb4));return{'name':_0x63a22b[_0x5ceccc(0x343)],'type':_0x63a22b[_0x5ceccc(0x224)],'strength':_0x63a22b[_0x5ceccc(0x32e)],'speed':_0x63a22b[_0x5ceccc(0x21d)],'tactics':_0x4c6bac,'size':_0x63a22b[_0x5ceccc(0x187)],'powerup':_0x63a22b[_0x5ceccc(0x32c)],'health':_0x5f08ef,'maxHealth':_0x5f08ef,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x448b75,'orientation':_0x376922,'isNFT':_0x9c2b16,'mediaType':_0x20b1df};}[_0x22bcae(0x2ad)](_0x5c8e84,_0x1e07a8,_0x1f51dc=![]){const _0x16aa2a=_0x22bcae;_0x5c8e84[_0x16aa2a(0x1e5)]===_0x16aa2a(0x1b2)?(_0x5c8e84[_0x16aa2a(0x1e5)]=_0x16aa2a(0x155),_0x1e07a8['style'][_0x16aa2a(0x25d)]=_0x1f51dc?_0x16aa2a(0x28b):_0x16aa2a(0x2a3)):(_0x5c8e84['orientation']=_0x16aa2a(0x1b2),_0x1e07a8[_0x16aa2a(0x2b3)][_0x16aa2a(0x25d)]=_0x1f51dc?'none':_0x16aa2a(0x28b)),log(_0x5c8e84['name']+_0x16aa2a(0x26c)+_0x5c8e84['orientation']+'!');}[_0x22bcae(0x12a)](_0x5af25e){const _0x216f28=_0x22bcae;var _0x3f581a=this;console[_0x216f28(0x2bb)](_0x216f28(0x12a));var _0x5e206e=document[_0x216f28(0x28c)]('character-select-container'),_0xaec00=document[_0x216f28(0x28c)](_0x216f28(0x19d));_0xaec00[_0x216f28(0x1a8)]='',_0x5e206e[_0x216f28(0x2b3)][_0x216f28(0x1ac)]=_0x216f28(0x131),document[_0x216f28(0x28c)](_0x216f28(0x30f))['onclick']=()=>{showThemeSelect(_0x3f581a);};const _0x109b8c=document['createDocumentFragment']();this[_0x216f28(0x223)][_0x216f28(0x25c)](function(_0x56534b){const _0x22e50d=_0x216f28;var _0x125aad=document['createElement'](_0x22e50d(0x302));_0x125aad['className']=_0x22e50d(0x358),_0x56534b[_0x22e50d(0x2dc)]===_0x22e50d(0x2f3)?_0x125aad[_0x22e50d(0x1a8)]=_0x22e50d(0x33e)+_0x56534b[_0x22e50d(0x338)]+_0x22e50d(0x1b4)+_0x56534b[_0x22e50d(0x343)]+_0x22e50d(0x15d)+'<p><strong>'+_0x56534b[_0x22e50d(0x343)]+_0x22e50d(0x306)+'<p>Type:\x20'+_0x56534b[_0x22e50d(0x224)]+_0x22e50d(0x263)+_0x22e50d(0x347)+_0x56534b['maxHealth']+_0x22e50d(0x263)+_0x22e50d(0x22b)+_0x56534b[_0x22e50d(0x32e)]+_0x22e50d(0x263)+_0x22e50d(0x2fd)+_0x56534b[_0x22e50d(0x21d)]+_0x22e50d(0x263)+'<p>Tactics:\x20'+_0x56534b[_0x22e50d(0x255)]+'</p>'+_0x22e50d(0x171)+_0x56534b[_0x22e50d(0x187)]+'</p>'+_0x22e50d(0x331)+_0x56534b[_0x22e50d(0x32c)]+_0x22e50d(0x263):_0x125aad['innerHTML']=_0x22e50d(0x207)+_0x56534b[_0x22e50d(0x338)]+'\x22\x20alt=\x22'+_0x56534b[_0x22e50d(0x343)]+'\x22>'+_0x22e50d(0x274)+_0x56534b['name']+_0x22e50d(0x306)+_0x22e50d(0x1ba)+_0x56534b[_0x22e50d(0x224)]+_0x22e50d(0x263)+_0x22e50d(0x347)+_0x56534b[_0x22e50d(0x20f)]+_0x22e50d(0x263)+_0x22e50d(0x22b)+_0x56534b[_0x22e50d(0x32e)]+_0x22e50d(0x263)+_0x22e50d(0x2fd)+_0x56534b[_0x22e50d(0x21d)]+_0x22e50d(0x263)+'<p>Tactics:\x20'+_0x56534b['tactics']+_0x22e50d(0x263)+_0x22e50d(0x171)+_0x56534b['size']+_0x22e50d(0x263)+_0x22e50d(0x331)+_0x56534b['powerup']+_0x22e50d(0x263),_0x125aad[_0x22e50d(0x1d1)]('click',function(){const _0x45686f=_0x22e50d;console[_0x45686f(0x2e5)](_0x45686f(0x290)+_0x56534b[_0x45686f(0x343)]),_0x5e206e[_0x45686f(0x2b3)][_0x45686f(0x1ac)]=_0x45686f(0x2a3),_0x5af25e?(_0x3f581a['player1']={..._0x56534b},console[_0x45686f(0x2e5)](_0x45686f(0x2ae)+_0x3f581a[_0x45686f(0x318)][_0x45686f(0x343)]),_0x3f581a[_0x45686f(0x144)]()):_0x3f581a[_0x45686f(0x232)](_0x56534b);}),_0x109b8c[_0x22e50d(0x14e)](_0x125aad);}),_0xaec00[_0x216f28(0x14e)](_0x109b8c),console['timeEnd'](_0x216f28(0x12a));}[_0x22bcae(0x232)](_0x5b5d3e){const _0xd598f7=_0x22bcae,_0x2cb7cd=this['player1'][_0xd598f7(0x1d9)],_0x105d0c=this['player1'][_0xd598f7(0x20f)],_0x535a13={..._0x5b5d3e},_0x14a5de=Math[_0xd598f7(0x35c)](0x1,_0x2cb7cd/_0x105d0c);_0x535a13['health']=Math[_0xd598f7(0x319)](_0x535a13[_0xd598f7(0x20f)]*_0x14a5de),_0x535a13[_0xd598f7(0x1d9)]=Math['max'](0x0,Math[_0xd598f7(0x35c)](_0x535a13[_0xd598f7(0x20f)],_0x535a13[_0xd598f7(0x1d9)])),_0x535a13[_0xd598f7(0x1a0)]=![],_0x535a13['boostValue']=0x0,_0x535a13[_0xd598f7(0x27c)]=![],this['player1']=_0x535a13,this[_0xd598f7(0x2b0)](),this[_0xd598f7(0x33c)](this[_0xd598f7(0x318)]),log(this[_0xd598f7(0x318)]['name']+'\x20steps\x20into\x20the\x20fray\x20with\x20'+this[_0xd598f7(0x318)][_0xd598f7(0x1d9)]+'/'+this[_0xd598f7(0x318)][_0xd598f7(0x20f)]+_0xd598f7(0x18a)),this[_0xd598f7(0x31a)]=this[_0xd598f7(0x318)][_0xd598f7(0x21d)]>this['player2'][_0xd598f7(0x21d)]?this[_0xd598f7(0x318)]:this[_0xd598f7(0x137)][_0xd598f7(0x21d)]>this[_0xd598f7(0x318)]['speed']?this['player2']:this[_0xd598f7(0x318)][_0xd598f7(0x32e)]>=this[_0xd598f7(0x137)][_0xd598f7(0x32e)]?this[_0xd598f7(0x318)]:this[_0xd598f7(0x137)],turnIndicator[_0xd598f7(0x179)]='Level\x20'+this[_0xd598f7(0x262)]+_0xd598f7(0x260)+(this[_0xd598f7(0x31a)]===this[_0xd598f7(0x318)]?_0xd598f7(0x325):'Opponent')+_0xd598f7(0x1bb),this[_0xd598f7(0x31a)]===this[_0xd598f7(0x137)]&&this[_0xd598f7(0x2b4)]!==_0xd598f7(0x16a)&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x22bcae(0x182)](_0x8dc39a,_0x562f61){const _0x375e0b=_0x22bcae;return console[_0x375e0b(0x2e5)](_0x375e0b(0x1b8)+_0x8dc39a+_0x375e0b(0x2be)+_0x562f61),new Promise(_0x3eca52=>{const _0x2ec3fd=_0x375e0b,_0x38c958=document['createElement'](_0x2ec3fd(0x302));_0x38c958['id']=_0x2ec3fd(0x1ff),_0x38c958[_0x2ec3fd(0x1ea)]=_0x2ec3fd(0x1ff);const _0x54d348=document[_0x2ec3fd(0x2c8)](_0x2ec3fd(0x302));_0x54d348[_0x2ec3fd(0x1ea)]=_0x2ec3fd(0x213);const _0x5e6404=document[_0x2ec3fd(0x2c8)]('p');_0x5e6404['id']='progress-message',_0x5e6404[_0x2ec3fd(0x179)]=_0x2ec3fd(0x353)+_0x8dc39a+'\x20with\x20Score\x20of\x20'+_0x562f61+'?',_0x54d348[_0x2ec3fd(0x14e)](_0x5e6404);const _0x1a2892=document['createElement'](_0x2ec3fd(0x302));_0x1a2892['className']=_0x2ec3fd(0x1ec);const _0x145b3d=document[_0x2ec3fd(0x2c8)]('button');_0x145b3d['id']=_0x2ec3fd(0x162),_0x145b3d[_0x2ec3fd(0x179)]=_0x2ec3fd(0x294),_0x1a2892[_0x2ec3fd(0x14e)](_0x145b3d);const _0x1a8e4b=document[_0x2ec3fd(0x2c8)](_0x2ec3fd(0x1f9));_0x1a8e4b['id']='progress-start-fresh',_0x1a8e4b[_0x2ec3fd(0x179)]=_0x2ec3fd(0x14c),_0x1a2892[_0x2ec3fd(0x14e)](_0x1a8e4b),_0x54d348[_0x2ec3fd(0x14e)](_0x1a2892),_0x38c958[_0x2ec3fd(0x14e)](_0x54d348),document[_0x2ec3fd(0x2ba)][_0x2ec3fd(0x14e)](_0x38c958),_0x38c958[_0x2ec3fd(0x2b3)][_0x2ec3fd(0x1ac)]=_0x2ec3fd(0x2b5);const _0x5003a1=()=>{const _0x4ae61f=_0x2ec3fd;console['log'](_0x4ae61f(0x2ef)),_0x38c958[_0x4ae61f(0x2b3)][_0x4ae61f(0x1ac)]=_0x4ae61f(0x2a3),document[_0x4ae61f(0x2ba)]['removeChild'](_0x38c958),_0x145b3d['removeEventListener']('click',_0x5003a1),_0x1a8e4b[_0x4ae61f(0x2eb)](_0x4ae61f(0x1b3),_0x3d4c53),_0x3eca52(!![]);},_0x3d4c53=()=>{const _0x264378=_0x2ec3fd;console[_0x264378(0x2e5)]('showProgressPopup:\x20User\x20chose\x20Restart'),_0x38c958[_0x264378(0x2b3)][_0x264378(0x1ac)]=_0x264378(0x2a3),document[_0x264378(0x2ba)]['removeChild'](_0x38c958),_0x145b3d[_0x264378(0x2eb)](_0x264378(0x1b3),_0x5003a1),_0x1a8e4b[_0x264378(0x2eb)](_0x264378(0x1b3),_0x3d4c53),_0x3eca52(![]);};_0x145b3d['addEventListener'](_0x2ec3fd(0x1b3),_0x5003a1),_0x1a8e4b[_0x2ec3fd(0x1d1)](_0x2ec3fd(0x1b3),_0x3d4c53);});}[_0x22bcae(0x144)](){const _0x2879b2=_0x22bcae;var _0x1f3068=this;console['log']('initGame:\x20Started\x20with\x20this.currentLevel='+this[_0x2879b2(0x262)]);var _0x3768aa=document[_0x2879b2(0x1f2)]('.game-container'),_0x6011c5=document[_0x2879b2(0x28c)](_0x2879b2(0x23e));_0x3768aa[_0x2879b2(0x2b3)][_0x2879b2(0x1ac)]=_0x2879b2(0x131),_0x6011c5[_0x2879b2(0x2b3)][_0x2879b2(0x21a)]=_0x2879b2(0x156),this[_0x2879b2(0x34b)](),this['sounds']['reset'][_0x2879b2(0x18b)](),log('Starting\x20Level\x20'+this[_0x2879b2(0x262)]+'...'),this[_0x2879b2(0x137)]=this[_0x2879b2(0x291)](opponentsConfig[this['currentLevel']-0x1]),console[_0x2879b2(0x2e5)](_0x2879b2(0x2b7)+this[_0x2879b2(0x262)]+':\x20'+this[_0x2879b2(0x137)][_0x2879b2(0x343)]+'\x20(opponentsConfig['+(this[_0x2879b2(0x262)]-0x1)+'])'),this['player1'][_0x2879b2(0x1d9)]=this[_0x2879b2(0x318)][_0x2879b2(0x20f)],this[_0x2879b2(0x31a)]=this[_0x2879b2(0x318)][_0x2879b2(0x21d)]>this[_0x2879b2(0x137)][_0x2879b2(0x21d)]?this[_0x2879b2(0x318)]:this[_0x2879b2(0x137)][_0x2879b2(0x21d)]>this['player1']['speed']?this[_0x2879b2(0x137)]:this[_0x2879b2(0x318)][_0x2879b2(0x32e)]>=this[_0x2879b2(0x137)]['strength']?this[_0x2879b2(0x318)]:this[_0x2879b2(0x137)],this[_0x2879b2(0x2b4)]=_0x2879b2(0x2cf),this[_0x2879b2(0x16a)]=![],this[_0x2879b2(0x175)]=[];const _0x113c83=document[_0x2879b2(0x28c)]('p1-image'),_0x432b2b=document[_0x2879b2(0x28c)](_0x2879b2(0x305));if(_0x113c83)_0x113c83[_0x2879b2(0x2a5)][_0x2879b2(0x2f8)](_0x2879b2(0x259),'loser');if(_0x432b2b)_0x432b2b[_0x2879b2(0x2a5)][_0x2879b2(0x2f8)](_0x2879b2(0x259),_0x2879b2(0x193));this[_0x2879b2(0x2b0)](),this['updateOpponentDisplay']();if(_0x113c83)_0x113c83[_0x2879b2(0x2b3)][_0x2879b2(0x25d)]=this[_0x2879b2(0x318)][_0x2879b2(0x1e5)]===_0x2879b2(0x1b2)?_0x2879b2(0x28b):_0x2879b2(0x2a3);if(_0x432b2b)_0x432b2b[_0x2879b2(0x2b3)][_0x2879b2(0x25d)]=this[_0x2879b2(0x137)]['orientation']===_0x2879b2(0x155)?_0x2879b2(0x28b):'none';this['updateHealth'](this['player1']),this[_0x2879b2(0x33c)](this[_0x2879b2(0x137)]),battleLog['innerHTML']='',gameOver[_0x2879b2(0x179)]='',this[_0x2879b2(0x318)][_0x2879b2(0x187)]!=='Medium'&&log(this['player1'][_0x2879b2(0x343)]+'\x27s\x20'+this['player1'][_0x2879b2(0x187)]+_0x2879b2(0x2a8)+(this[_0x2879b2(0x318)]['size']===_0x2879b2(0x271)?_0x2879b2(0x35a)+this['player1'][_0x2879b2(0x20f)]+_0x2879b2(0x1ed)+this[_0x2879b2(0x318)]['tactics']:_0x2879b2(0x2fc)+this[_0x2879b2(0x318)][_0x2879b2(0x20f)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x2879b2(0x318)][_0x2879b2(0x255)])+'!'),this[_0x2879b2(0x137)][_0x2879b2(0x187)]!=='Medium'&&log(this[_0x2879b2(0x137)]['name']+_0x2879b2(0x1e3)+this['player2'][_0x2879b2(0x187)]+_0x2879b2(0x2a8)+(this[_0x2879b2(0x137)][_0x2879b2(0x187)]===_0x2879b2(0x271)?'boosts\x20health\x20to\x20'+this[_0x2879b2(0x137)][_0x2879b2(0x20f)]+'\x20but\x20dulls\x20tactics\x20to\x20'+this[_0x2879b2(0x137)][_0x2879b2(0x255)]:_0x2879b2(0x2fc)+this[_0x2879b2(0x137)][_0x2879b2(0x20f)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x2879b2(0x137)][_0x2879b2(0x255)])+'!'),log(this[_0x2879b2(0x318)][_0x2879b2(0x343)]+_0x2879b2(0x186)+this[_0x2879b2(0x318)][_0x2879b2(0x1d9)]+'/'+this[_0x2879b2(0x318)]['maxHealth']+_0x2879b2(0x18a)),log(this[_0x2879b2(0x31a)][_0x2879b2(0x343)]+_0x2879b2(0x2d7)),this[_0x2879b2(0x18f)](),this['gameState']=this[_0x2879b2(0x31a)]===this['player1']?_0x2879b2(0x147):_0x2879b2(0x2fb),turnIndicator[_0x2879b2(0x179)]='Level\x20'+this['currentLevel']+_0x2879b2(0x260)+(this[_0x2879b2(0x31a)]===this['player1']?_0x2879b2(0x325):_0x2879b2(0x2df))+'\x27s\x20Turn',this['playerCharacters'][_0x2879b2(0x132)]>0x1&&(document[_0x2879b2(0x28c)](_0x2879b2(0x18c))['style'][_0x2879b2(0x1ac)]=_0x2879b2(0x241)),this[_0x2879b2(0x31a)]===this['player2']&&setTimeout(function(){_0x1f3068['aiTurn']();},0x3e8);}['updatePlayerDisplay'](){const _0x5e71e2=_0x22bcae;p1Name[_0x5e71e2(0x179)]=this[_0x5e71e2(0x318)][_0x5e71e2(0x2d3)]||this['theme']===_0x5e71e2(0x216)?this[_0x5e71e2(0x318)][_0x5e71e2(0x343)]:'Player\x201',p1Type[_0x5e71e2(0x179)]=this['player1']['type'],p1Strength['textContent']=this[_0x5e71e2(0x318)][_0x5e71e2(0x32e)],p1Speed[_0x5e71e2(0x179)]=this[_0x5e71e2(0x318)][_0x5e71e2(0x21d)],p1Tactics['textContent']=this[_0x5e71e2(0x318)]['tactics'],p1Size['textContent']=this[_0x5e71e2(0x318)][_0x5e71e2(0x187)],p1Powerup['textContent']=this[_0x5e71e2(0x318)][_0x5e71e2(0x32c)];const _0x3a0d59=document[_0x5e71e2(0x28c)](_0x5e71e2(0x2e7)),_0x494f48=_0x3a0d59[_0x5e71e2(0x172)];if(this[_0x5e71e2(0x318)]['mediaType']==='video'){if(_0x3a0d59[_0x5e71e2(0x136)]!==_0x5e71e2(0x23a)){const _0x498788=document[_0x5e71e2(0x2c8)](_0x5e71e2(0x2f3));_0x498788['id']='p1-image',_0x498788['src']=this[_0x5e71e2(0x318)]['imageUrl'],_0x498788[_0x5e71e2(0x340)]=!![],_0x498788[_0x5e71e2(0x12c)]=!![],_0x498788[_0x5e71e2(0x19a)]=!![],_0x498788[_0x5e71e2(0x2f5)]=this['player1'][_0x5e71e2(0x343)],_0x494f48['replaceChild'](_0x498788,_0x3a0d59);}else _0x3a0d59['src']=this[_0x5e71e2(0x318)][_0x5e71e2(0x338)];}else{if(_0x3a0d59[_0x5e71e2(0x136)]!==_0x5e71e2(0x2d0)){const _0x54324e=document[_0x5e71e2(0x2c8)](_0x5e71e2(0x164));_0x54324e['id']=_0x5e71e2(0x2e7),_0x54324e[_0x5e71e2(0x16b)]=this[_0x5e71e2(0x318)][_0x5e71e2(0x338)],_0x54324e['alt']=this['player1'][_0x5e71e2(0x343)],_0x494f48[_0x5e71e2(0x355)](_0x54324e,_0x3a0d59);}else _0x3a0d59[_0x5e71e2(0x16b)]=this['player1'][_0x5e71e2(0x338)];}const _0x4d14e5=document[_0x5e71e2(0x28c)](_0x5e71e2(0x2e7));_0x4d14e5[_0x5e71e2(0x2b3)][_0x5e71e2(0x25d)]=this['player1'][_0x5e71e2(0x1e5)]===_0x5e71e2(0x1b2)?_0x5e71e2(0x28b):_0x5e71e2(0x2a3),_0x4d14e5[_0x5e71e2(0x136)]===_0x5e71e2(0x2d0)?_0x4d14e5[_0x5e71e2(0x296)]=function(){const _0x1d335a=_0x5e71e2;_0x4d14e5[_0x1d335a(0x2b3)][_0x1d335a(0x1ac)]=_0x1d335a(0x131);}:_0x4d14e5['style']['display']=_0x5e71e2(0x131),p1Hp[_0x5e71e2(0x179)]=this[_0x5e71e2(0x318)][_0x5e71e2(0x1d9)]+'/'+this[_0x5e71e2(0x318)]['maxHealth'],_0x4d14e5[_0x5e71e2(0x14f)]=()=>{console['log']('Player\x201\x20media\x20clicked'),this['showCharacterSelect'](![]);};}[_0x22bcae(0x292)](){const _0x5c4bb3=_0x22bcae;p2Name[_0x5c4bb3(0x179)]=this[_0x5c4bb3(0x150)]===_0x5c4bb3(0x216)?this[_0x5c4bb3(0x137)]['name']:'AI\x20Opponent',p2Type[_0x5c4bb3(0x179)]=this[_0x5c4bb3(0x137)]['type'],p2Strength[_0x5c4bb3(0x179)]=this[_0x5c4bb3(0x137)]['strength'],p2Speed['textContent']=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x21d)],p2Tactics[_0x5c4bb3(0x179)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x255)],p2Size['textContent']=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x187)],p2Powerup[_0x5c4bb3(0x179)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x32c)];const _0x287347=document[_0x5c4bb3(0x28c)](_0x5c4bb3(0x305)),_0x4c61ab=_0x287347['parentNode'];if(this[_0x5c4bb3(0x137)][_0x5c4bb3(0x2dc)]===_0x5c4bb3(0x2f3)){if(_0x287347['tagName']!==_0x5c4bb3(0x23a)){const _0x46335a=document[_0x5c4bb3(0x2c8)]('video');_0x46335a['id']='p2-image',_0x46335a[_0x5c4bb3(0x16b)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x338)],_0x46335a['autoplay']=!![],_0x46335a[_0x5c4bb3(0x12c)]=!![],_0x46335a[_0x5c4bb3(0x19a)]=!![],_0x46335a['alt']=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x343)],_0x4c61ab[_0x5c4bb3(0x355)](_0x46335a,_0x287347);}else _0x287347[_0x5c4bb3(0x16b)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x338)];}else{if(_0x287347['tagName']!=='IMG'){const _0x553807=document['createElement'](_0x5c4bb3(0x164));_0x553807['id']=_0x5c4bb3(0x305),_0x553807[_0x5c4bb3(0x16b)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x338)],_0x553807[_0x5c4bb3(0x2f5)]=this[_0x5c4bb3(0x137)]['name'],_0x4c61ab[_0x5c4bb3(0x355)](_0x553807,_0x287347);}else _0x287347[_0x5c4bb3(0x16b)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x338)];}const _0x1d8040=document[_0x5c4bb3(0x28c)]('p2-image');_0x1d8040[_0x5c4bb3(0x2b3)][_0x5c4bb3(0x25d)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x1e5)]===_0x5c4bb3(0x155)?'scaleX(-1)':_0x5c4bb3(0x2a3),_0x1d8040[_0x5c4bb3(0x136)]==='IMG'?_0x1d8040[_0x5c4bb3(0x296)]=function(){const _0x5d62b4=_0x5c4bb3;_0x1d8040['style'][_0x5d62b4(0x1ac)]=_0x5d62b4(0x131);}:_0x1d8040['style'][_0x5c4bb3(0x1ac)]=_0x5c4bb3(0x131),p2Hp[_0x5c4bb3(0x179)]=this[_0x5c4bb3(0x137)][_0x5c4bb3(0x1d9)]+'/'+this[_0x5c4bb3(0x137)]['maxHealth'],_0x1d8040[_0x5c4bb3(0x2a5)][_0x5c4bb3(0x2f8)](_0x5c4bb3(0x259),'loser');}[_0x22bcae(0x18f)](){const _0x580693=_0x22bcae;this[_0x580693(0x270)]=[];for(let _0x5ec8d7=0x0;_0x5ec8d7<this[_0x580693(0x32a)];_0x5ec8d7++){this[_0x580693(0x270)][_0x5ec8d7]=[];for(let _0x3c0167=0x0;_0x3c0167<this[_0x580693(0x221)];_0x3c0167++){let _0x32644c;do{_0x32644c=this[_0x580693(0x22f)]();}while(_0x3c0167>=0x2&&this[_0x580693(0x270)][_0x5ec8d7][_0x3c0167-0x1]?.['type']===_0x32644c['type']&&this[_0x580693(0x270)][_0x5ec8d7][_0x3c0167-0x2]?.[_0x580693(0x224)]===_0x32644c[_0x580693(0x224)]||_0x5ec8d7>=0x2&&this[_0x580693(0x270)][_0x5ec8d7-0x1]?.[_0x3c0167]?.[_0x580693(0x224)]===_0x32644c[_0x580693(0x224)]&&this[_0x580693(0x270)][_0x5ec8d7-0x2]?.[_0x3c0167]?.['type']===_0x32644c[_0x580693(0x224)]);this[_0x580693(0x270)][_0x5ec8d7][_0x3c0167]=_0x32644c;}}this[_0x580693(0x289)]();}[_0x22bcae(0x22f)](){const _0x427a88=_0x22bcae;return{'type':randomChoice(this[_0x427a88(0x34d)]),'element':null};}[_0x22bcae(0x289)](){const _0x14c80c=_0x22bcae;this[_0x14c80c(0x1fc)]();const _0x2a23a5=document[_0x14c80c(0x28c)](_0x14c80c(0x23e));_0x2a23a5['innerHTML']='';for(let _0x20682b=0x0;_0x20682b<this['height'];_0x20682b++){for(let _0xe70279=0x0;_0xe70279<this['width'];_0xe70279++){const _0x3feeaa=this[_0x14c80c(0x270)][_0x20682b][_0xe70279];if(_0x3feeaa[_0x14c80c(0x224)]===null)continue;const _0x11f362=document[_0x14c80c(0x2c8)](_0x14c80c(0x302));_0x11f362['className']='tile\x20'+_0x3feeaa['type'];if(this[_0x14c80c(0x16a)])_0x11f362['classList'][_0x14c80c(0x2f4)](_0x14c80c(0x1bc));const _0x5802b6=document['createElement'](_0x14c80c(0x164));_0x5802b6[_0x14c80c(0x16b)]=_0x14c80c(0x2bd)+_0x3feeaa[_0x14c80c(0x224)]+_0x14c80c(0x1c1),_0x5802b6[_0x14c80c(0x2f5)]=_0x3feeaa['type'],_0x11f362[_0x14c80c(0x14e)](_0x5802b6),_0x11f362[_0x14c80c(0x1f4)]['x']=_0xe70279,_0x11f362[_0x14c80c(0x1f4)]['y']=_0x20682b,_0x2a23a5[_0x14c80c(0x14e)](_0x11f362),_0x3feeaa[_0x14c80c(0x2f1)]=_0x11f362,(!this[_0x14c80c(0x1fb)]||this[_0x14c80c(0x159)]&&(this[_0x14c80c(0x159)]['x']!==_0xe70279||this[_0x14c80c(0x159)]['y']!==_0x20682b))&&(_0x11f362[_0x14c80c(0x2b3)][_0x14c80c(0x25d)]=_0x14c80c(0x24b)),this[_0x14c80c(0x27a)]?_0x11f362['addEventListener']('touchstart',_0x7ff183=>this['handleTouchStart'](_0x7ff183)):_0x11f362[_0x14c80c(0x1d1)](_0x14c80c(0x219),_0xb932b1=>this[_0x14c80c(0x1a6)](_0xb932b1));}}document[_0x14c80c(0x28c)](_0x14c80c(0x2c2))['style']['display']=this[_0x14c80c(0x16a)]?'block':_0x14c80c(0x2a3);}[_0x22bcae(0x154)](){const _0xcdbc5c=_0x22bcae,_0x38cf8f=document['getElementById'](_0xcdbc5c(0x23e));this['isTouchDevice']?(_0x38cf8f[_0xcdbc5c(0x1d1)]('touchstart',_0x37ddba=>this[_0xcdbc5c(0x30d)](_0x37ddba)),_0x38cf8f[_0xcdbc5c(0x1d1)]('touchmove',_0x3800a0=>this[_0xcdbc5c(0x30a)](_0x3800a0)),_0x38cf8f[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x149),_0x5bb95b=>this['handleTouchEnd'](_0x5bb95b))):(_0x38cf8f[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x219),_0x58a8ee=>this[_0xcdbc5c(0x1a6)](_0x58a8ee)),_0x38cf8f[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x33a),_0x40235a=>this[_0xcdbc5c(0x332)](_0x40235a)),_0x38cf8f[_0xcdbc5c(0x1d1)]('mouseup',_0x5b6183=>this[_0xcdbc5c(0x2f6)](_0x5b6183)));document[_0xcdbc5c(0x28c)](_0xcdbc5c(0x1d0))[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x1b3),()=>this[_0xcdbc5c(0x279)]()),document[_0xcdbc5c(0x28c)](_0xcdbc5c(0x196))[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x1b3),()=>{const _0x4961f5=_0xcdbc5c;this[_0x4961f5(0x144)]();});const _0x12082b=document['getElementById'](_0xcdbc5c(0x18c)),_0x168ae7=document['getElementById'](_0xcdbc5c(0x2e7)),_0x2b60df=document[_0xcdbc5c(0x28c)](_0xcdbc5c(0x305));_0x12082b[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x1b3),()=>{const _0x2b9766=_0xcdbc5c;console['log'](_0x2b9766(0x303)),this[_0x2b9766(0x12a)](![]);}),_0x168ae7[_0xcdbc5c(0x1d1)]('click',()=>{const _0x54c6a0=_0xcdbc5c;console[_0x54c6a0(0x2e5)](_0x54c6a0(0x1ee)),this['showCharacterSelect'](![]);}),document['getElementById'](_0xcdbc5c(0x1c7))[_0xcdbc5c(0x1d1)](_0xcdbc5c(0x1b3),()=>this[_0xcdbc5c(0x2ad)](this[_0xcdbc5c(0x318)],document[_0xcdbc5c(0x28c)](_0xcdbc5c(0x2e7)),![])),document[_0xcdbc5c(0x28c)](_0xcdbc5c(0x234))[_0xcdbc5c(0x1d1)]('click',()=>this[_0xcdbc5c(0x2ad)](this[_0xcdbc5c(0x137)],document[_0xcdbc5c(0x28c)](_0xcdbc5c(0x305)),!![]));}[_0x22bcae(0x279)](){const _0x1ee728=_0x22bcae;console[_0x1ee728(0x2e5)]('handleGameOverButton\x20started:\x20currentLevel='+this[_0x1ee728(0x262)]+_0x1ee728(0x2cb)+this['player2'][_0x1ee728(0x1d9)]),this[_0x1ee728(0x137)][_0x1ee728(0x1d9)]<=0x0&&this['currentLevel']>opponentsConfig[_0x1ee728(0x132)]&&(this[_0x1ee728(0x262)]=0x1,console[_0x1ee728(0x2e5)]('Reset\x20to\x20Level\x201:\x20currentLevel='+this['currentLevel'])),this[_0x1ee728(0x144)](),console[_0x1ee728(0x2e5)](_0x1ee728(0x208)+this[_0x1ee728(0x262)]);}[_0x22bcae(0x1a6)](_0x9df06c){const _0x5a46e1=_0x22bcae;if(this[_0x5a46e1(0x16a)]||this['gameState']!==_0x5a46e1(0x147)||this[_0x5a46e1(0x31a)]!==this[_0x5a46e1(0x318)])return;_0x9df06c[_0x5a46e1(0x18d)]();const _0x54b9e7=this['getTileFromEvent'](_0x9df06c);if(!_0x54b9e7||!_0x54b9e7[_0x5a46e1(0x2f1)])return;this[_0x5a46e1(0x1fb)]=!![],this[_0x5a46e1(0x159)]={'x':_0x54b9e7['x'],'y':_0x54b9e7['y']},_0x54b9e7[_0x5a46e1(0x2f1)][_0x5a46e1(0x2a5)][_0x5a46e1(0x2f4)]('selected');const _0x187ec0=document[_0x5a46e1(0x28c)]('game-board')['getBoundingClientRect']();this['offsetX']=_0x9df06c[_0x5a46e1(0x280)]-(_0x187ec0[_0x5a46e1(0x350)]+this[_0x5a46e1(0x159)]['x']*this['tileSizeWithGap']),this['offsetY']=_0x9df06c[_0x5a46e1(0x1c0)]-(_0x187ec0[_0x5a46e1(0x273)]+this[_0x5a46e1(0x159)]['y']*this['tileSizeWithGap']);}[_0x22bcae(0x332)](_0x27f17a){const _0x4c407d=_0x22bcae;if(!this[_0x4c407d(0x1fb)]||!this[_0x4c407d(0x159)]||this[_0x4c407d(0x16a)]||this[_0x4c407d(0x2b4)]!==_0x4c407d(0x147))return;_0x27f17a[_0x4c407d(0x18d)]();const _0x5e6c27=document[_0x4c407d(0x28c)](_0x4c407d(0x23e))['getBoundingClientRect'](),_0x1c4486=_0x27f17a[_0x4c407d(0x280)]-_0x5e6c27[_0x4c407d(0x350)]-this[_0x4c407d(0x222)],_0x142789=_0x27f17a[_0x4c407d(0x1c0)]-_0x5e6c27[_0x4c407d(0x273)]-this[_0x4c407d(0x33f)],_0x513145=this[_0x4c407d(0x270)][this[_0x4c407d(0x159)]['y']][this[_0x4c407d(0x159)]['x']][_0x4c407d(0x2f1)];_0x513145['style']['transition']='';if(!this[_0x4c407d(0x2c0)]){const _0xe64ae6=Math[_0x4c407d(0x29f)](_0x1c4486-this[_0x4c407d(0x159)]['x']*this[_0x4c407d(0x176)]),_0x56fb49=Math[_0x4c407d(0x29f)](_0x142789-this['selectedTile']['y']*this['tileSizeWithGap']);if(_0xe64ae6>_0x56fb49&&_0xe64ae6>0x5)this[_0x4c407d(0x2c0)]=_0x4c407d(0x1c2);else{if(_0x56fb49>_0xe64ae6&&_0x56fb49>0x5)this[_0x4c407d(0x2c0)]=_0x4c407d(0x233);}}if(!this['dragDirection'])return;if(this[_0x4c407d(0x2c0)]===_0x4c407d(0x1c2)){const _0x50fcb2=Math[_0x4c407d(0x194)](0x0,Math[_0x4c407d(0x35c)]((this[_0x4c407d(0x221)]-0x1)*this[_0x4c407d(0x176)],_0x1c4486));_0x513145[_0x4c407d(0x2b3)][_0x4c407d(0x25d)]=_0x4c407d(0x348)+(_0x50fcb2-this[_0x4c407d(0x159)]['x']*this[_0x4c407d(0x176)])+'px,\x200)\x20scale(1.05)',this[_0x4c407d(0x316)]={'x':Math['round'](_0x50fcb2/this['tileSizeWithGap']),'y':this[_0x4c407d(0x159)]['y']};}else{if(this[_0x4c407d(0x2c0)]===_0x4c407d(0x233)){const _0x35c68c=Math[_0x4c407d(0x194)](0x0,Math[_0x4c407d(0x35c)]((this[_0x4c407d(0x32a)]-0x1)*this[_0x4c407d(0x176)],_0x142789));_0x513145['style']['transform']='translate(0,\x20'+(_0x35c68c-this[_0x4c407d(0x159)]['y']*this[_0x4c407d(0x176)])+'px)\x20scale(1.05)',this[_0x4c407d(0x316)]={'x':this[_0x4c407d(0x159)]['x'],'y':Math[_0x4c407d(0x319)](_0x35c68c/this[_0x4c407d(0x176)])};}}}[_0x22bcae(0x2f6)](_0x278c62){const _0x315a40=_0x22bcae;if(!this[_0x315a40(0x1fb)]||!this[_0x315a40(0x159)]||!this[_0x315a40(0x316)]||this[_0x315a40(0x16a)]||this[_0x315a40(0x2b4)]!==_0x315a40(0x147)){if(this[_0x315a40(0x159)]){const _0x1558be=this[_0x315a40(0x270)][this['selectedTile']['y']][this['selectedTile']['x']];if(_0x1558be['element'])_0x1558be[_0x315a40(0x2f1)][_0x315a40(0x2a5)][_0x315a40(0x2f8)]('selected');}this[_0x315a40(0x1fb)]=![],this[_0x315a40(0x159)]=null,this['targetTile']=null,this[_0x315a40(0x2c0)]=null,this[_0x315a40(0x289)]();return;}const _0x17eec5=this[_0x315a40(0x270)][this['selectedTile']['y']][this[_0x315a40(0x159)]['x']];if(_0x17eec5[_0x315a40(0x2f1)])_0x17eec5['element']['classList'][_0x315a40(0x2f8)]('selected');this[_0x315a40(0x1d7)](this['selectedTile']['x'],this[_0x315a40(0x159)]['y'],this[_0x315a40(0x316)]['x'],this['targetTile']['y']),this['isDragging']=![],this[_0x315a40(0x159)]=null,this['targetTile']=null,this[_0x315a40(0x2c0)]=null;}['handleTouchStart'](_0x1558fa){const _0x531d11=_0x22bcae;if(this[_0x531d11(0x16a)]||this[_0x531d11(0x2b4)]!==_0x531d11(0x147)||this[_0x531d11(0x31a)]!==this[_0x531d11(0x318)])return;_0x1558fa[_0x531d11(0x18d)]();const _0x44da7f=this['getTileFromEvent'](_0x1558fa[_0x531d11(0x134)][0x0]);if(!_0x44da7f||!_0x44da7f['element'])return;this[_0x531d11(0x1fb)]=!![],this[_0x531d11(0x159)]={'x':_0x44da7f['x'],'y':_0x44da7f['y']},_0x44da7f[_0x531d11(0x2f1)][_0x531d11(0x2a5)][_0x531d11(0x2f4)](_0x531d11(0x226));const _0x4370c5=document[_0x531d11(0x28c)](_0x531d11(0x23e))[_0x531d11(0x13c)]();this[_0x531d11(0x222)]=_0x1558fa[_0x531d11(0x134)][0x0][_0x531d11(0x280)]-(_0x4370c5[_0x531d11(0x350)]+this[_0x531d11(0x159)]['x']*this[_0x531d11(0x176)]),this['offsetY']=_0x1558fa[_0x531d11(0x134)][0x0][_0x531d11(0x1c0)]-(_0x4370c5[_0x531d11(0x273)]+this['selectedTile']['y']*this[_0x531d11(0x176)]);}[_0x22bcae(0x30a)](_0x86eb00){const _0x513917=_0x22bcae;if(!this[_0x513917(0x1fb)]||!this['selectedTile']||this[_0x513917(0x16a)]||this[_0x513917(0x2b4)]!==_0x513917(0x147))return;_0x86eb00['preventDefault']();const _0x32d5ab=document['getElementById'](_0x513917(0x23e))[_0x513917(0x13c)](),_0x1633bf=_0x86eb00[_0x513917(0x134)][0x0][_0x513917(0x280)]-_0x32d5ab[_0x513917(0x350)]-this['offsetX'],_0x2d9d0c=_0x86eb00[_0x513917(0x134)][0x0][_0x513917(0x1c0)]-_0x32d5ab[_0x513917(0x273)]-this[_0x513917(0x33f)],_0x15b71e=this['board'][this['selectedTile']['y']][this[_0x513917(0x159)]['x']][_0x513917(0x2f1)];requestAnimationFrame(()=>{const _0x3a2332=_0x513917;if(!this[_0x3a2332(0x2c0)]){const _0x171361=Math[_0x3a2332(0x29f)](_0x1633bf-this['selectedTile']['x']*this[_0x3a2332(0x176)]),_0x12ec22=Math[_0x3a2332(0x29f)](_0x2d9d0c-this[_0x3a2332(0x159)]['y']*this['tileSizeWithGap']);if(_0x171361>_0x12ec22&&_0x171361>0x7)this[_0x3a2332(0x2c0)]=_0x3a2332(0x1c2);else{if(_0x12ec22>_0x171361&&_0x12ec22>0x7)this[_0x3a2332(0x2c0)]=_0x3a2332(0x233);}}_0x15b71e[_0x3a2332(0x2b3)][_0x3a2332(0x2e0)]='';if(this[_0x3a2332(0x2c0)]===_0x3a2332(0x1c2)){const _0x5dd26d=Math['max'](0x0,Math[_0x3a2332(0x35c)]((this['width']-0x1)*this[_0x3a2332(0x176)],_0x1633bf));_0x15b71e['style'][_0x3a2332(0x25d)]='translate('+(_0x5dd26d-this[_0x3a2332(0x159)]['x']*this[_0x3a2332(0x176)])+'px,\x200)\x20scale(1.05)',this[_0x3a2332(0x316)]={'x':Math['round'](_0x5dd26d/this['tileSizeWithGap']),'y':this['selectedTile']['y']};}else{if(this[_0x3a2332(0x2c0)]==='column'){const _0x235829=Math[_0x3a2332(0x194)](0x0,Math[_0x3a2332(0x35c)]((this[_0x3a2332(0x32a)]-0x1)*this[_0x3a2332(0x176)],_0x2d9d0c));_0x15b71e[_0x3a2332(0x2b3)]['transform']='translate(0,\x20'+(_0x235829-this[_0x3a2332(0x159)]['y']*this['tileSizeWithGap'])+_0x3a2332(0x13e),this[_0x3a2332(0x316)]={'x':this[_0x3a2332(0x159)]['x'],'y':Math[_0x3a2332(0x319)](_0x235829/this[_0x3a2332(0x176)])};}}});}[_0x22bcae(0x138)](_0x26dca7){const _0x5cb9e2=_0x22bcae;if(!this[_0x5cb9e2(0x1fb)]||!this['selectedTile']||!this[_0x5cb9e2(0x316)]||this['gameOver']||this[_0x5cb9e2(0x2b4)]!==_0x5cb9e2(0x147)){if(this['selectedTile']){const _0x364eb9=this[_0x5cb9e2(0x270)][this[_0x5cb9e2(0x159)]['y']][this['selectedTile']['x']];if(_0x364eb9['element'])_0x364eb9['element'][_0x5cb9e2(0x2a5)][_0x5cb9e2(0x2f8)](_0x5cb9e2(0x226));}this[_0x5cb9e2(0x1fb)]=![],this[_0x5cb9e2(0x159)]=null,this['targetTile']=null,this[_0x5cb9e2(0x2c0)]=null,this['renderBoard']();return;}const _0x24c4ad=this['board'][this[_0x5cb9e2(0x159)]['y']][this['selectedTile']['x']];if(_0x24c4ad[_0x5cb9e2(0x2f1)])_0x24c4ad[_0x5cb9e2(0x2f1)][_0x5cb9e2(0x2a5)]['remove'](_0x5cb9e2(0x226));this[_0x5cb9e2(0x1d7)](this[_0x5cb9e2(0x159)]['x'],this['selectedTile']['y'],this[_0x5cb9e2(0x316)]['x'],this['targetTile']['y']),this['isDragging']=![],this[_0x5cb9e2(0x159)]=null,this[_0x5cb9e2(0x316)]=null,this[_0x5cb9e2(0x2c0)]=null;}[_0x22bcae(0x244)](_0x41b977){const _0x558436=_0x22bcae,_0xa6b26b=document[_0x558436(0x28c)](_0x558436(0x23e))['getBoundingClientRect'](),_0x36ed51=Math[_0x558436(0x1ab)]((_0x41b977['clientX']-_0xa6b26b[_0x558436(0x350)])/this[_0x558436(0x176)]),_0x52b8d6=Math['floor']((_0x41b977[_0x558436(0x1c0)]-_0xa6b26b['top'])/this['tileSizeWithGap']);if(_0x36ed51>=0x0&&_0x36ed51<this[_0x558436(0x221)]&&_0x52b8d6>=0x0&&_0x52b8d6<this[_0x558436(0x32a)])return{'x':_0x36ed51,'y':_0x52b8d6,'element':this[_0x558436(0x270)][_0x52b8d6][_0x36ed51][_0x558436(0x2f1)]};return null;}[_0x22bcae(0x1d7)](_0x57fba1,_0x9abc2f,_0x3c2de3,_0x243876){const _0x54d266=_0x22bcae,_0x53cc94=this['tileSizeWithGap'];let _0x3cd9d5;const _0x119495=[],_0x403adc=[];if(_0x9abc2f===_0x243876){_0x3cd9d5=_0x57fba1<_0x3c2de3?0x1:-0x1;const _0x313797=Math['min'](_0x57fba1,_0x3c2de3),_0x1996f9=Math[_0x54d266(0x194)](_0x57fba1,_0x3c2de3);for(let _0x743e26=_0x313797;_0x743e26<=_0x1996f9;_0x743e26++){_0x119495['push']({...this[_0x54d266(0x270)][_0x9abc2f][_0x743e26]}),_0x403adc[_0x54d266(0x211)](this[_0x54d266(0x270)][_0x9abc2f][_0x743e26][_0x54d266(0x2f1)]);}}else{if(_0x57fba1===_0x3c2de3){_0x3cd9d5=_0x9abc2f<_0x243876?0x1:-0x1;const _0x28601b=Math[_0x54d266(0x35c)](_0x9abc2f,_0x243876),_0x4d388c=Math[_0x54d266(0x194)](_0x9abc2f,_0x243876);for(let _0x211581=_0x28601b;_0x211581<=_0x4d388c;_0x211581++){_0x119495['push']({...this['board'][_0x211581][_0x57fba1]}),_0x403adc['push'](this[_0x54d266(0x270)][_0x211581][_0x57fba1]['element']);}}}const _0x160880=this['board'][_0x9abc2f][_0x57fba1][_0x54d266(0x2f1)],_0x42c93f=(_0x3c2de3-_0x57fba1)*_0x53cc94,_0x2afecb=(_0x243876-_0x9abc2f)*_0x53cc94;_0x160880[_0x54d266(0x2b3)][_0x54d266(0x2e0)]='transform\x200.2s\x20ease',_0x160880[_0x54d266(0x2b3)][_0x54d266(0x25d)]=_0x54d266(0x348)+_0x42c93f+_0x54d266(0x203)+_0x2afecb+'px)';let _0x25c4c6=0x0;if(_0x9abc2f===_0x243876)for(let _0x3f064c=Math[_0x54d266(0x35c)](_0x57fba1,_0x3c2de3);_0x3f064c<=Math['max'](_0x57fba1,_0x3c2de3);_0x3f064c++){if(_0x3f064c===_0x57fba1)continue;const _0x6800fd=_0x3cd9d5*-_0x53cc94*(_0x3f064c-_0x57fba1)/Math['abs'](_0x3c2de3-_0x57fba1);_0x403adc[_0x25c4c6][_0x54d266(0x2b3)][_0x54d266(0x2e0)]=_0x54d266(0x1c6),_0x403adc[_0x25c4c6][_0x54d266(0x2b3)][_0x54d266(0x25d)]=_0x54d266(0x348)+_0x6800fd+'px,\x200)',_0x25c4c6++;}else for(let _0xb5cb1a=Math[_0x54d266(0x35c)](_0x9abc2f,_0x243876);_0xb5cb1a<=Math[_0x54d266(0x194)](_0x9abc2f,_0x243876);_0xb5cb1a++){if(_0xb5cb1a===_0x9abc2f)continue;const _0x1c4be8=_0x3cd9d5*-_0x53cc94*(_0xb5cb1a-_0x9abc2f)/Math[_0x54d266(0x29f)](_0x243876-_0x9abc2f);_0x403adc[_0x25c4c6][_0x54d266(0x2b3)][_0x54d266(0x2e0)]=_0x54d266(0x1c6),_0x403adc[_0x25c4c6][_0x54d266(0x2b3)][_0x54d266(0x25d)]=_0x54d266(0x2ac)+_0x1c4be8+'px)',_0x25c4c6++;}setTimeout(()=>{const _0x29b0a4=_0x54d266;if(_0x9abc2f===_0x243876){const _0x20285c=this[_0x29b0a4(0x270)][_0x9abc2f],_0x8bbfcd=[..._0x20285c];if(_0x57fba1<_0x3c2de3){for(let _0x57a6be=_0x57fba1;_0x57a6be<_0x3c2de3;_0x57a6be++)_0x20285c[_0x57a6be]=_0x8bbfcd[_0x57a6be+0x1];}else{for(let _0xb45448=_0x57fba1;_0xb45448>_0x3c2de3;_0xb45448--)_0x20285c[_0xb45448]=_0x8bbfcd[_0xb45448-0x1];}_0x20285c[_0x3c2de3]=_0x8bbfcd[_0x57fba1];}else{const _0x1ae096=[];for(let _0x5a8c5c=0x0;_0x5a8c5c<this['height'];_0x5a8c5c++)_0x1ae096[_0x5a8c5c]={...this[_0x29b0a4(0x270)][_0x5a8c5c][_0x57fba1]};if(_0x9abc2f<_0x243876){for(let _0x5d3fb9=_0x9abc2f;_0x5d3fb9<_0x243876;_0x5d3fb9++)this[_0x29b0a4(0x270)][_0x5d3fb9][_0x57fba1]=_0x1ae096[_0x5d3fb9+0x1];}else{for(let _0x653007=_0x9abc2f;_0x653007>_0x243876;_0x653007--)this[_0x29b0a4(0x270)][_0x653007][_0x57fba1]=_0x1ae096[_0x653007-0x1];}this[_0x29b0a4(0x270)][_0x243876][_0x3c2de3]=_0x1ae096[_0x9abc2f];}this[_0x29b0a4(0x289)]();const _0x2c7eae=this[_0x29b0a4(0x346)](_0x3c2de3,_0x243876);_0x2c7eae?this[_0x29b0a4(0x2b4)]=_0x29b0a4(0x2e1):(log(_0x29b0a4(0x1fa)),this[_0x29b0a4(0x272)][_0x29b0a4(0x29d)]['play'](),_0x160880['style'][_0x29b0a4(0x2e0)]=_0x29b0a4(0x1c6),_0x160880[_0x29b0a4(0x2b3)]['transform']=_0x29b0a4(0x24b),_0x403adc[_0x29b0a4(0x25c)](_0xfacb70=>{const _0x22dc03=_0x29b0a4;_0xfacb70[_0x22dc03(0x2b3)][_0x22dc03(0x2e0)]=_0x22dc03(0x1c6),_0xfacb70[_0x22dc03(0x2b3)]['transform']=_0x22dc03(0x24b);}),setTimeout(()=>{const _0xfd7110=_0x29b0a4;if(_0x9abc2f===_0x243876){const _0x3d51d6=Math[_0xfd7110(0x35c)](_0x57fba1,_0x3c2de3);for(let _0x4fc768=0x0;_0x4fc768<_0x119495[_0xfd7110(0x132)];_0x4fc768++){this[_0xfd7110(0x270)][_0x9abc2f][_0x3d51d6+_0x4fc768]={..._0x119495[_0x4fc768],'element':_0x403adc[_0x4fc768]};}}else{const _0x5aafc1=Math[_0xfd7110(0x35c)](_0x9abc2f,_0x243876);for(let _0x165edc=0x0;_0x165edc<_0x119495[_0xfd7110(0x132)];_0x165edc++){this['board'][_0x5aafc1+_0x165edc][_0x57fba1]={..._0x119495[_0x165edc],'element':_0x403adc[_0x165edc]};}}this[_0xfd7110(0x289)](),this[_0xfd7110(0x2b4)]='playerTurn';},0xc8));},0xc8);}[_0x22bcae(0x346)](_0x376b93=null,_0x29eadf=null){const _0x2a9dcc=_0x22bcae;console[_0x2a9dcc(0x2e5)](_0x2a9dcc(0x1b9),this['gameOver']);if(this[_0x2a9dcc(0x16a)])return console[_0x2a9dcc(0x2e5)]('Game\x20over,\x20exiting\x20resolveMatches'),![];const _0x20b7aa=_0x376b93!==null&&_0x29eadf!==null;console[_0x2a9dcc(0x2e5)](_0x2a9dcc(0x24e)+_0x20b7aa);const _0x39a7fc=this[_0x2a9dcc(0x2bf)]();console[_0x2a9dcc(0x2e5)](_0x2a9dcc(0x26d)+_0x39a7fc[_0x2a9dcc(0x132)]+_0x2a9dcc(0x157),_0x39a7fc);let _0x72b789=0x1,_0x46a68b='';if(_0x20b7aa&&_0x39a7fc[_0x2a9dcc(0x132)]>0x1){const _0x1b0569=_0x39a7fc['reduce']((_0x328aeb,_0x2d59b8)=>_0x328aeb+_0x2d59b8[_0x2a9dcc(0x33d)],0x0);console[_0x2a9dcc(0x2e5)](_0x2a9dcc(0x1f1)+_0x1b0569);if(_0x1b0569>=0x6&&_0x1b0569<=0x8)_0x72b789=1.2,_0x46a68b=_0x2a9dcc(0x1be)+_0x1b0569+_0x2a9dcc(0x1c9),this[_0x2a9dcc(0x272)][_0x2a9dcc(0x237)][_0x2a9dcc(0x18b)]();else _0x1b0569>=0x9&&(_0x72b789=0x3,_0x46a68b=_0x2a9dcc(0x167)+_0x1b0569+_0x2a9dcc(0x342),this['sounds'][_0x2a9dcc(0x237)][_0x2a9dcc(0x18b)]());}if(_0x39a7fc[_0x2a9dcc(0x132)]>0x0){const _0x3bd7b0=new Set();let _0x5d453d=0x0;const _0x1da618=this['currentTurn'],_0x2b88f0=this[_0x2a9dcc(0x31a)]===this[_0x2a9dcc(0x318)]?this['player2']:this[_0x2a9dcc(0x318)];try{_0x39a7fc['forEach'](_0x50dc96=>{const _0x3d555c=_0x2a9dcc;console['log']('Processing\x20match:',_0x50dc96),_0x50dc96[_0x3d555c(0x236)][_0x3d555c(0x25c)](_0x5a6c40=>_0x3bd7b0[_0x3d555c(0x2f4)](_0x5a6c40));const _0x290c58=this[_0x3d555c(0x333)](_0x50dc96,_0x20b7aa);console['log'](_0x3d555c(0x17c)+_0x290c58);if(this[_0x3d555c(0x16a)]){console[_0x3d555c(0x2e5)](_0x3d555c(0x1a3));return;}if(_0x290c58>0x0)_0x5d453d+=_0x290c58;});if(this[_0x2a9dcc(0x16a)])return console[_0x2a9dcc(0x2e5)](_0x2a9dcc(0x22e)),!![];return console[_0x2a9dcc(0x2e5)]('Total\x20damage\x20dealt:\x20'+_0x5d453d+',\x20tiles\x20to\x20clear:',[..._0x3bd7b0]),_0x5d453d>0x0&&!this[_0x2a9dcc(0x16a)]&&setTimeout(()=>{const _0x5c68e6=_0x2a9dcc;if(this['gameOver']){console[_0x5c68e6(0x2e5)](_0x5c68e6(0x2b1));return;}console['log'](_0x5c68e6(0x197),_0x2b88f0[_0x5c68e6(0x343)]),this['animateRecoil'](_0x2b88f0,_0x5d453d);},0x64),setTimeout(()=>{const _0x49cf06=_0x2a9dcc;if(this['gameOver']){console['log'](_0x49cf06(0x1dc));return;}console[_0x49cf06(0x2e5)](_0x49cf06(0x206),[..._0x3bd7b0]),_0x3bd7b0[_0x49cf06(0x25c)](_0x3cc2e0=>{const _0x1a09de=_0x49cf06,[_0x31e198,_0x462b9f]=_0x3cc2e0['split'](',')[_0x1a09de(0x317)](Number);this[_0x1a09de(0x270)][_0x462b9f][_0x31e198]?.[_0x1a09de(0x2f1)]?this[_0x1a09de(0x270)][_0x462b9f][_0x31e198][_0x1a09de(0x2f1)]['classList']['add'](_0x1a09de(0x2a1)):console[_0x1a09de(0x2a6)](_0x1a09de(0x28f)+_0x31e198+','+_0x462b9f+')\x20has\x20no\x20element\x20to\x20animate');}),setTimeout(()=>{const _0x114c3b=_0x49cf06;if(this[_0x114c3b(0x16a)]){console[_0x114c3b(0x2e5)](_0x114c3b(0x2f2));return;}console[_0x114c3b(0x2e5)]('Clearing\x20matched\x20tiles:',[..._0x3bd7b0]),_0x3bd7b0['forEach'](_0x3802dc=>{const _0x396e5d=_0x114c3b,[_0x536ca3,_0x36fa89]=_0x3802dc[_0x396e5d(0x239)](',')[_0x396e5d(0x317)](Number);this[_0x396e5d(0x270)][_0x36fa89][_0x536ca3]&&(this[_0x396e5d(0x270)][_0x36fa89][_0x536ca3][_0x396e5d(0x224)]=null,this[_0x396e5d(0x270)][_0x36fa89][_0x536ca3][_0x396e5d(0x2f1)]=null);}),this[_0x114c3b(0x272)][_0x114c3b(0x1b7)][_0x114c3b(0x18b)](),console[_0x114c3b(0x2e5)](_0x114c3b(0x1da));if(_0x72b789>0x1&&this['roundStats'][_0x114c3b(0x132)]>0x0){const _0xfc400=this[_0x114c3b(0x175)][this['roundStats'][_0x114c3b(0x132)]-0x1],_0x540615=_0xfc400['points'];_0xfc400['points']=Math['round'](_0xfc400[_0x114c3b(0x23c)]*_0x72b789),_0x46a68b&&(log(_0x46a68b),log(_0x114c3b(0x258)+_0x540615+'\x20to\x20'+_0xfc400[_0x114c3b(0x23c)]+_0x114c3b(0x17b)));}this[_0x114c3b(0x354)](()=>{const _0x1eda24=_0x114c3b;if(this['gameOver']){console[_0x1eda24(0x2e5)](_0x1eda24(0x21f));return;}console[_0x1eda24(0x2e5)](_0x1eda24(0x328)),this['endTurn']();});},0x12c);},0xc8),!![];}catch(_0x5e3b30){return console[_0x2a9dcc(0x184)]('Error\x20in\x20resolveMatches:',_0x5e3b30),this[_0x2a9dcc(0x2b4)]=this[_0x2a9dcc(0x31a)]===this['player1']?'playerTurn':_0x2a9dcc(0x2fb),![];}}return console[_0x2a9dcc(0x2e5)]('No\x20matches\x20found,\x20returning\x20false'),![];}[_0x22bcae(0x2bf)](){const _0x347519=_0x22bcae;console[_0x347519(0x2e5)](_0x347519(0x1fd));const _0x4fa9f3=[];try{const _0x2152e1=[];for(let _0x11c815=0x0;_0x11c815<this[_0x347519(0x32a)];_0x11c815++){let _0x47f1c0=0x0;for(let _0x27f0c8=0x0;_0x27f0c8<=this[_0x347519(0x221)];_0x27f0c8++){const _0x3d0376=_0x27f0c8<this['width']?this[_0x347519(0x270)][_0x11c815][_0x27f0c8]?.[_0x347519(0x224)]:null;if(_0x3d0376!==this['board'][_0x11c815][_0x47f1c0]?.['type']||_0x27f0c8===this['width']){const _0xe39151=_0x27f0c8-_0x47f1c0;if(_0xe39151>=0x3){const _0x5de508=new Set();for(let _0x1d85f7=_0x47f1c0;_0x1d85f7<_0x27f0c8;_0x1d85f7++){_0x5de508[_0x347519(0x2f4)](_0x1d85f7+','+_0x11c815);}_0x2152e1[_0x347519(0x211)]({'type':this['board'][_0x11c815][_0x47f1c0][_0x347519(0x224)],'coordinates':_0x5de508}),console['log'](_0x347519(0x1c4)+_0x11c815+_0x347519(0x1e4)+_0x47f1c0+'-'+(_0x27f0c8-0x1)+':',[..._0x5de508]);}_0x47f1c0=_0x27f0c8;}}}for(let _0x56d2a3=0x0;_0x56d2a3<this[_0x347519(0x221)];_0x56d2a3++){let _0x13a85a=0x0;for(let _0x1a3bc1=0x0;_0x1a3bc1<=this[_0x347519(0x32a)];_0x1a3bc1++){const _0xba17c6=_0x1a3bc1<this['height']?this[_0x347519(0x270)][_0x1a3bc1][_0x56d2a3]?.[_0x347519(0x224)]:null;if(_0xba17c6!==this[_0x347519(0x270)][_0x13a85a][_0x56d2a3]?.['type']||_0x1a3bc1===this[_0x347519(0x32a)]){const _0x5b5445=_0x1a3bc1-_0x13a85a;if(_0x5b5445>=0x3){const _0x3313ab=new Set();for(let _0xeda568=_0x13a85a;_0xeda568<_0x1a3bc1;_0xeda568++){_0x3313ab['add'](_0x56d2a3+','+_0xeda568);}_0x2152e1[_0x347519(0x211)]({'type':this[_0x347519(0x270)][_0x13a85a][_0x56d2a3]['type'],'coordinates':_0x3313ab}),console[_0x347519(0x2e5)](_0x347519(0x31c)+_0x56d2a3+_0x347519(0x17d)+_0x13a85a+'-'+(_0x1a3bc1-0x1)+':',[..._0x3313ab]);}_0x13a85a=_0x1a3bc1;}}}const _0x26bfdd=[],_0x5a4658=new Set();return _0x2152e1['forEach']((_0x30572b,_0x2e9ae7)=>{const _0x310462=_0x347519;if(_0x5a4658[_0x310462(0x257)](_0x2e9ae7))return;const _0x160edb={'type':_0x30572b['type'],'coordinates':new Set(_0x30572b['coordinates'])};_0x5a4658[_0x310462(0x2f4)](_0x2e9ae7);for(let _0x2887a9=0x0;_0x2887a9<_0x2152e1[_0x310462(0x132)];_0x2887a9++){if(_0x5a4658[_0x310462(0x257)](_0x2887a9))continue;const _0x20ef1d=_0x2152e1[_0x2887a9];if(_0x20ef1d[_0x310462(0x224)]===_0x160edb[_0x310462(0x224)]){const _0x3316f3=[..._0x20ef1d['coordinates']]['some'](_0x541c67=>_0x160edb[_0x310462(0x236)][_0x310462(0x257)](_0x541c67));_0x3316f3&&(_0x20ef1d[_0x310462(0x236)]['forEach'](_0x56d0cb=>_0x160edb[_0x310462(0x236)][_0x310462(0x2f4)](_0x56d0cb)),_0x5a4658[_0x310462(0x2f4)](_0x2887a9));}}_0x26bfdd[_0x310462(0x211)]({'type':_0x160edb['type'],'coordinates':_0x160edb[_0x310462(0x236)],'totalTiles':_0x160edb['coordinates'][_0x310462(0x187)]});}),_0x4fa9f3[_0x347519(0x211)](..._0x26bfdd),console[_0x347519(0x2e5)]('checkMatches\x20completed,\x20returning\x20matches:',_0x4fa9f3),_0x4fa9f3;}catch(_0x5d39f4){return console[_0x347519(0x184)]('Error\x20in\x20checkMatches:',_0x5d39f4),[];}}[_0x22bcae(0x333)](_0x32d530,_0x2a9052=!![]){const _0x16ab09=_0x22bcae;console[_0x16ab09(0x2e5)](_0x16ab09(0x214),_0x32d530,'isInitialMove:',_0x2a9052);const _0x48e28f=this[_0x16ab09(0x31a)],_0x19c393=this[_0x16ab09(0x31a)]===this[_0x16ab09(0x318)]?this[_0x16ab09(0x137)]:this[_0x16ab09(0x318)],_0x1b8d75=_0x32d530[_0x16ab09(0x224)],_0x43c8a4=_0x32d530[_0x16ab09(0x33d)];let _0x200392=0x0,_0x2e51c2=0x0;console[_0x16ab09(0x2e5)](_0x19c393['name']+_0x16ab09(0x25b)+_0x19c393['health']);_0x43c8a4==0x4&&(this[_0x16ab09(0x272)][_0x16ab09(0x22c)][_0x16ab09(0x18b)](),log(_0x48e28f['name']+_0x16ab09(0x148)+_0x43c8a4+'\x20tiles!'));_0x43c8a4>=0x5&&(this[_0x16ab09(0x272)][_0x16ab09(0x15b)][_0x16ab09(0x18b)](),log(_0x48e28f[_0x16ab09(0x343)]+'\x20created\x20a\x20match\x20of\x20'+_0x43c8a4+'\x20tiles!'));if(_0x1b8d75===_0x16ab09(0x17f)||_0x1b8d75===_0x16ab09(0x195)||_0x1b8d75===_0x16ab09(0x160)||_0x1b8d75===_0x16ab09(0x247)){_0x200392=Math[_0x16ab09(0x319)](_0x48e28f[_0x16ab09(0x32e)]*(_0x43c8a4===0x3?0x2:_0x43c8a4===0x4?0x3:0x4));let _0xae11f3=0x1;if(_0x43c8a4===0x4)_0xae11f3=1.5;else _0x43c8a4>=0x5&&(_0xae11f3=0x2);_0x200392=Math['round'](_0x200392*_0xae11f3),console[_0x16ab09(0x2e5)](_0x16ab09(0x2ea)+_0x48e28f[_0x16ab09(0x32e)]*(_0x43c8a4===0x3?0x2:_0x43c8a4===0x4?0x3:0x4)+_0x16ab09(0x135)+_0xae11f3+_0x16ab09(0x253)+_0x200392);_0x1b8d75===_0x16ab09(0x160)&&(_0x200392=Math[_0x16ab09(0x319)](_0x200392*1.2),console[_0x16ab09(0x2e5)](_0x16ab09(0x276)+_0x200392));_0x48e28f[_0x16ab09(0x1a0)]&&(_0x200392+=_0x48e28f[_0x16ab09(0x1cb)]||0xa,_0x48e28f[_0x16ab09(0x1a0)]=![],log(_0x48e28f[_0x16ab09(0x343)]+_0x16ab09(0x2c1)),console[_0x16ab09(0x2e5)]('Boost\x20applied,\x20damage:\x20'+_0x200392));_0x2e51c2=_0x200392;const _0x42dad9=_0x19c393[_0x16ab09(0x255)]*0xa;Math['random']()*0x64<_0x42dad9&&(_0x200392=Math[_0x16ab09(0x1ab)](_0x200392/0x2),log(_0x19c393[_0x16ab09(0x343)]+'\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20'+_0x200392+_0x16ab09(0x128)),console[_0x16ab09(0x2e5)](_0x16ab09(0x1e7)+_0x200392));let _0x526c7d=0x0;_0x19c393[_0x16ab09(0x27c)]&&(_0x526c7d=Math[_0x16ab09(0x35c)](_0x200392,0x5),_0x200392=Math[_0x16ab09(0x194)](0x0,_0x200392-_0x526c7d),_0x19c393['lastStandActive']=![],console[_0x16ab09(0x2e5)](_0x16ab09(0x2aa)+_0x526c7d+',\x20damage:\x20'+_0x200392));const _0x2f79ac=_0x1b8d75===_0x16ab09(0x17f)?_0x16ab09(0x2d8):_0x1b8d75===_0x16ab09(0x195)?_0x16ab09(0x140):_0x16ab09(0x16d);let _0x1cf67d;if(_0x526c7d>0x0)_0x1cf67d=_0x48e28f[_0x16ab09(0x343)]+_0x16ab09(0x2ec)+_0x2f79ac+_0x16ab09(0x217)+_0x19c393['name']+'\x20for\x20'+_0x2e51c2+'\x20damage,\x20but\x20'+_0x19c393['name']+_0x16ab09(0x2a0)+_0x526c7d+'\x20damage,\x20resulting\x20in\x20'+_0x200392+_0x16ab09(0x128);else _0x1b8d75===_0x16ab09(0x247)?_0x1cf67d=_0x48e28f[_0x16ab09(0x343)]+_0x16ab09(0x2f7)+_0x200392+'\x20damage\x20to\x20'+_0x19c393[_0x16ab09(0x343)]+_0x16ab09(0x351):_0x1cf67d=_0x48e28f[_0x16ab09(0x343)]+'\x20uses\x20'+_0x2f79ac+_0x16ab09(0x217)+_0x19c393[_0x16ab09(0x343)]+_0x16ab09(0x314)+_0x200392+_0x16ab09(0x128);_0x2a9052?log(_0x1cf67d):log(_0x16ab09(0x1a9)+_0x1cf67d),_0x19c393[_0x16ab09(0x1d9)]=Math['max'](0x0,_0x19c393[_0x16ab09(0x1d9)]-_0x200392),console[_0x16ab09(0x2e5)](_0x19c393[_0x16ab09(0x343)]+_0x16ab09(0x28d)+_0x19c393[_0x16ab09(0x1d9)]),this[_0x16ab09(0x33c)](_0x19c393),console[_0x16ab09(0x2e5)](_0x16ab09(0x2ff)),this[_0x16ab09(0x31f)](),!this['gameOver']&&(console['log']('Game\x20not\x20over,\x20animating\x20attack'),this[_0x16ab09(0x13a)](_0x48e28f,_0x200392,_0x1b8d75));}else _0x1b8d75===_0x16ab09(0x1cc)&&(this[_0x16ab09(0x1cf)](_0x48e28f,_0x19c393,_0x43c8a4),!this[_0x16ab09(0x16a)]&&(console[_0x16ab09(0x2e5)](_0x16ab09(0x2b2)),this['animatePowerup'](_0x48e28f)));(!this[_0x16ab09(0x175)][this[_0x16ab09(0x175)]['length']-0x1]||this['roundStats'][this['roundStats'][_0x16ab09(0x132)]-0x1][_0x16ab09(0x191)])&&this[_0x16ab09(0x175)][_0x16ab09(0x211)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x32e1f9=this[_0x16ab09(0x175)][this[_0x16ab09(0x175)]['length']-0x1];return _0x32e1f9['points']+=_0x200392,_0x32e1f9['matches']+=0x1,console[_0x16ab09(0x2e5)](_0x16ab09(0x310)+_0x200392),_0x200392;}[_0x22bcae(0x354)](_0x1be179){const _0x42900d=_0x22bcae;if(this[_0x42900d(0x16a)]){console[_0x42900d(0x2e5)](_0x42900d(0x2a4));return;}const _0x25230c=this[_0x42900d(0x250)](),_0x42fe68='falling';for(let _0x2f6844=0x0;_0x2f6844<this[_0x42900d(0x221)];_0x2f6844++){for(let _0xeffad2=0x0;_0xeffad2<this[_0x42900d(0x32a)];_0xeffad2++){const _0x42d5c2=this[_0x42900d(0x270)][_0xeffad2][_0x2f6844];if(_0x42d5c2[_0x42900d(0x2f1)]&&_0x42d5c2[_0x42900d(0x2f1)][_0x42900d(0x2b3)]['transform']==='translate(0px,\x200px)'){const _0xdd26f0=this['countEmptyBelow'](_0x2f6844,_0xeffad2);_0xdd26f0>0x0&&(_0x42d5c2['element'][_0x42900d(0x2a5)][_0x42900d(0x2f4)](_0x42fe68),_0x42d5c2[_0x42900d(0x2f1)]['style']['transform']='translate(0,\x20'+_0xdd26f0*this['tileSizeWithGap']+_0x42900d(0x283));}}}this['renderBoard'](),_0x25230c?setTimeout(()=>{const _0x4de40c=_0x42900d;if(this[_0x4de40c(0x16a)]){console[_0x4de40c(0x2e5)](_0x4de40c(0x1e6));return;}this['sounds']['cascade']['play']();const _0x557930=this[_0x4de40c(0x346)](),_0x3689f4=document[_0x4de40c(0x13b)]('.'+_0x42fe68);_0x3689f4[_0x4de40c(0x25c)](_0x53e623=>{const _0x37d670=_0x4de40c;_0x53e623[_0x37d670(0x2a5)][_0x37d670(0x2f8)](_0x42fe68),_0x53e623[_0x37d670(0x2b3)]['transform']=_0x37d670(0x24b);}),!_0x557930&&_0x1be179();},0x12c):_0x1be179();}['cascadeTilesWithoutRender'](){const _0x34be71=_0x22bcae;let _0x301479=![];for(let _0x407749=0x0;_0x407749<this[_0x34be71(0x221)];_0x407749++){let _0x4461d7=0x0;for(let _0x4d9ab7=this[_0x34be71(0x32a)]-0x1;_0x4d9ab7>=0x0;_0x4d9ab7--){if(!this[_0x34be71(0x270)][_0x4d9ab7][_0x407749][_0x34be71(0x224)])_0x4461d7++;else _0x4461d7>0x0&&(this[_0x34be71(0x270)][_0x4d9ab7+_0x4461d7][_0x407749]=this['board'][_0x4d9ab7][_0x407749],this[_0x34be71(0x270)][_0x4d9ab7][_0x407749]={'type':null,'element':null},_0x301479=!![]);}for(let _0x1b3b23=0x0;_0x1b3b23<_0x4461d7;_0x1b3b23++){this[_0x34be71(0x270)][_0x1b3b23][_0x407749]=this['createRandomTile'](),_0x301479=!![];}}return _0x301479;}['countEmptyBelow'](_0x2615a7,_0x4d96ae){const _0x494dac=_0x22bcae;let _0x343bb0=0x0;for(let _0x238369=_0x4d96ae+0x1;_0x238369<this[_0x494dac(0x32a)];_0x238369++){if(!this[_0x494dac(0x270)][_0x238369][_0x2615a7][_0x494dac(0x224)])_0x343bb0++;else break;}return _0x343bb0;}[_0x22bcae(0x1cf)](_0x10b3dd,_0x5a80c3,_0x42ce49){const _0x289147=_0x22bcae,_0x225a5c=0x1-_0x5a80c3['tactics']*0.05;let _0x1d5c66,_0xf452e4,_0x50a3a2,_0x54107c=0x1,_0x5cb648='';if(_0x42ce49===0x4)_0x54107c=1.5,_0x5cb648='\x20(50%\x20bonus\x20for\x20match-4)';else _0x42ce49>=0x5&&(_0x54107c=0x2,_0x5cb648=_0x289147(0x1a7));if(_0x10b3dd[_0x289147(0x32c)]===_0x289147(0x25f))_0xf452e4=0xa*_0x54107c,_0x1d5c66=Math[_0x289147(0x1ab)](_0xf452e4*_0x225a5c),_0x50a3a2=_0xf452e4-_0x1d5c66,_0x10b3dd['health']=Math[_0x289147(0x35c)](_0x10b3dd[_0x289147(0x20f)],_0x10b3dd[_0x289147(0x1d9)]+_0x1d5c66),log(_0x10b3dd[_0x289147(0x343)]+_0x289147(0x23d)+_0x1d5c66+_0x289147(0x330)+_0x5cb648+(_0x5a80c3[_0x289147(0x255)]>0x0?'\x20(originally\x20'+_0xf452e4+_0x289147(0x1ce)+_0x50a3a2+'\x20due\x20to\x20'+_0x5a80c3[_0x289147(0x343)]+_0x289147(0x339):'')+'!');else{if(_0x10b3dd[_0x289147(0x32c)]===_0x289147(0x345))_0xf452e4=0xa*_0x54107c,_0x1d5c66=Math[_0x289147(0x1ab)](_0xf452e4*_0x225a5c),_0x50a3a2=_0xf452e4-_0x1d5c66,_0x10b3dd['boostActive']=!![],_0x10b3dd['boostValue']=_0x1d5c66,log(_0x10b3dd[_0x289147(0x343)]+_0x289147(0x29e)+_0x1d5c66+_0x289147(0x14b)+_0x5cb648+(_0x5a80c3[_0x289147(0x255)]>0x0?'\x20(originally\x20'+_0xf452e4+',\x20reduced\x20by\x20'+_0x50a3a2+_0x289147(0x20c)+_0x5a80c3[_0x289147(0x343)]+_0x289147(0x339):'')+'!');else{if(_0x10b3dd[_0x289147(0x32c)]==='Regenerate')_0xf452e4=0x7*_0x54107c,_0x1d5c66=Math[_0x289147(0x1ab)](_0xf452e4*_0x225a5c),_0x50a3a2=_0xf452e4-_0x1d5c66,_0x10b3dd[_0x289147(0x1d9)]=Math[_0x289147(0x35c)](_0x10b3dd[_0x289147(0x20f)],_0x10b3dd['health']+_0x1d5c66),log(_0x10b3dd['name']+_0x289147(0x24f)+_0x1d5c66+'\x20HP'+_0x5cb648+(_0x5a80c3['tactics']>0x0?'\x20(originally\x20'+_0xf452e4+_0x289147(0x1ce)+_0x50a3a2+_0x289147(0x20c)+_0x5a80c3[_0x289147(0x343)]+_0x289147(0x339):'')+'!');else _0x10b3dd[_0x289147(0x32c)]===_0x289147(0x2fa)&&(_0xf452e4=0x5*_0x54107c,_0x1d5c66=Math['floor'](_0xf452e4*_0x225a5c),_0x50a3a2=_0xf452e4-_0x1d5c66,_0x10b3dd['health']=Math[_0x289147(0x35c)](_0x10b3dd['maxHealth'],_0x10b3dd[_0x289147(0x1d9)]+_0x1d5c66),log(_0x10b3dd[_0x289147(0x343)]+'\x20uses\x20Minor\x20Regen,\x20restoring\x20'+_0x1d5c66+'\x20HP'+_0x5cb648+(_0x5a80c3[_0x289147(0x255)]>0x0?_0x289147(0x2a7)+_0xf452e4+_0x289147(0x1ce)+_0x50a3a2+_0x289147(0x20c)+_0x5a80c3[_0x289147(0x343)]+_0x289147(0x339):'')+'!'));}}this['updateHealth'](_0x10b3dd);}['updateHealth'](_0x2de052){const _0x189988=_0x22bcae,_0x476d49=_0x2de052===this['player1']?p1Health:p2Health,_0x2ecb0b=_0x2de052===this['player1']?p1Hp:p2Hp,_0x447a22=_0x2de052[_0x189988(0x1d9)]/_0x2de052[_0x189988(0x20f)]*0x64;_0x476d49['style']['width']=_0x447a22+'%';let _0x566292;if(_0x447a22>0x4b)_0x566292='#4CAF50';else{if(_0x447a22>0x32)_0x566292=_0x189988(0x180);else _0x447a22>0x19?_0x566292=_0x189988(0x27b):_0x566292=_0x189988(0x32f);}_0x476d49[_0x189988(0x2b3)][_0x189988(0x20d)]=_0x566292,_0x2ecb0b[_0x189988(0x179)]=_0x2de052['health']+'/'+_0x2de052['maxHealth'];}['endTurn'](){const _0x5b9c2c=_0x22bcae;if(this[_0x5b9c2c(0x2b4)]===_0x5b9c2c(0x16a)||this[_0x5b9c2c(0x16a)]){console['log'](_0x5b9c2c(0x21f));return;}this[_0x5b9c2c(0x31a)]=this[_0x5b9c2c(0x31a)]===this['player1']?this[_0x5b9c2c(0x137)]:this[_0x5b9c2c(0x318)],this[_0x5b9c2c(0x2b4)]=this['currentTurn']===this[_0x5b9c2c(0x318)]?_0x5b9c2c(0x147):_0x5b9c2c(0x2fb),turnIndicator['textContent']=_0x5b9c2c(0x315)+this[_0x5b9c2c(0x262)]+'\x20-\x20'+(this[_0x5b9c2c(0x31a)]===this['player1']?_0x5b9c2c(0x325):'Opponent')+'\x27s\x20Turn',log(_0x5b9c2c(0x19c)+(this['currentTurn']===this['player1']?_0x5b9c2c(0x325):_0x5b9c2c(0x2df))),this['currentTurn']===this[_0x5b9c2c(0x137)]&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x22bcae(0x2fb)](){const _0x51bc98=_0x22bcae;if(this[_0x51bc98(0x2b4)]!==_0x51bc98(0x2fb)||this[_0x51bc98(0x31a)]!==this[_0x51bc98(0x137)])return;this[_0x51bc98(0x2b4)]=_0x51bc98(0x2e1);const _0x1d5c20=this[_0x51bc98(0x28a)]();_0x1d5c20?(log(this[_0x51bc98(0x137)]['name']+_0x51bc98(0x35b)+_0x1d5c20['x1']+',\x20'+_0x1d5c20['y1']+')\x20to\x20('+_0x1d5c20['x2']+',\x20'+_0x1d5c20['y2']+')'),this[_0x51bc98(0x1d7)](_0x1d5c20['x1'],_0x1d5c20['y1'],_0x1d5c20['x2'],_0x1d5c20['y2'])):(log(this[_0x51bc98(0x137)][_0x51bc98(0x343)]+_0x51bc98(0x264)),this[_0x51bc98(0x2b9)]());}[_0x22bcae(0x28a)](){const _0x2e138c=_0x22bcae;for(let _0x395392=0x0;_0x395392<this[_0x2e138c(0x32a)];_0x395392++){for(let _0x354877=0x0;_0x354877<this['width'];_0x354877++){if(_0x354877<this[_0x2e138c(0x221)]-0x1&&this[_0x2e138c(0x301)](_0x354877,_0x395392,_0x354877+0x1,_0x395392))return{'x1':_0x354877,'y1':_0x395392,'x2':_0x354877+0x1,'y2':_0x395392};if(_0x395392<this[_0x2e138c(0x32a)]-0x1&&this[_0x2e138c(0x301)](_0x354877,_0x395392,_0x354877,_0x395392+0x1))return{'x1':_0x354877,'y1':_0x395392,'x2':_0x354877,'y2':_0x395392+0x1};}}return null;}['canMakeMatch'](_0xf01239,_0x311b55,_0x5e846b,_0x5bb9bb){const _0x4dd569=_0x22bcae,_0xb02948={...this[_0x4dd569(0x270)][_0x311b55][_0xf01239]},_0x3ea3c1={...this[_0x4dd569(0x270)][_0x5bb9bb][_0x5e846b]};this[_0x4dd569(0x270)][_0x311b55][_0xf01239]=_0x3ea3c1,this[_0x4dd569(0x270)][_0x5bb9bb][_0x5e846b]=_0xb02948;const _0x26bc49=this[_0x4dd569(0x2bf)]()[_0x4dd569(0x132)]>0x0;return this['board'][_0x311b55][_0xf01239]=_0xb02948,this['board'][_0x5bb9bb][_0x5e846b]=_0x3ea3c1,_0x26bc49;}async[_0x22bcae(0x31f)](){const _0x5e2d48=_0x22bcae;if(this[_0x5e2d48(0x16a)]||this[_0x5e2d48(0x229)]){console[_0x5e2d48(0x2e5)](_0x5e2d48(0x212)+this[_0x5e2d48(0x16a)]+_0x5e2d48(0x30e)+this['isCheckingGameOver']+_0x5e2d48(0x129)+this[_0x5e2d48(0x262)]);return;}this[_0x5e2d48(0x229)]=!![],console['log'](_0x5e2d48(0x173)+this[_0x5e2d48(0x262)]+_0x5e2d48(0x1b1)+this[_0x5e2d48(0x318)][_0x5e2d48(0x1d9)]+',\x20player2.health='+this['player2'][_0x5e2d48(0x1d9)]);const _0x590334=document[_0x5e2d48(0x28c)](_0x5e2d48(0x1d0));if(this['player1'][_0x5e2d48(0x1d9)]<=0x0){console[_0x5e2d48(0x2e5)](_0x5e2d48(0x334)),this[_0x5e2d48(0x16a)]=!![],this['gameState']=_0x5e2d48(0x16a),gameOver[_0x5e2d48(0x179)]='You\x20Lose!',turnIndicator['textContent']=_0x5e2d48(0x133),log(this['player2'][_0x5e2d48(0x343)]+_0x5e2d48(0x15e)+this[_0x5e2d48(0x318)]['name']+'!'),_0x590334['textContent']=_0x5e2d48(0x165),document[_0x5e2d48(0x28c)]('game-over-container')[_0x5e2d48(0x2b3)][_0x5e2d48(0x1ac)]='block';try{this[_0x5e2d48(0x272)][_0x5e2d48(0x30b)][_0x5e2d48(0x18b)]();}catch(_0x5e1747){console[_0x5e2d48(0x184)](_0x5e2d48(0x359),_0x5e1747);}}else{if(this[_0x5e2d48(0x137)]['health']<=0x0){console[_0x5e2d48(0x2e5)](_0x5e2d48(0x326)),this[_0x5e2d48(0x16a)]=!![],this[_0x5e2d48(0x2b4)]=_0x5e2d48(0x16a),gameOver[_0x5e2d48(0x179)]=_0x5e2d48(0x2e4),turnIndicator['textContent']=_0x5e2d48(0x133),_0x590334[_0x5e2d48(0x179)]=this[_0x5e2d48(0x262)]===opponentsConfig[_0x5e2d48(0x132)]?_0x5e2d48(0x2cc):_0x5e2d48(0x1b0),document[_0x5e2d48(0x28c)](_0x5e2d48(0x2c2))['style'][_0x5e2d48(0x1ac)]='block';if(this[_0x5e2d48(0x31a)]===this[_0x5e2d48(0x318)]){const _0x4cbed4=this['roundStats'][this[_0x5e2d48(0x175)]['length']-0x1];if(_0x4cbed4&&!_0x4cbed4[_0x5e2d48(0x191)]){_0x4cbed4['healthPercentage']=this[_0x5e2d48(0x318)][_0x5e2d48(0x1d9)]/this['player1']['maxHealth']*0x64,_0x4cbed4[_0x5e2d48(0x191)]=!![];const _0x59a543=_0x4cbed4['matches']>0x0?_0x4cbed4['points']/_0x4cbed4[_0x5e2d48(0x16e)]/0x64*(_0x4cbed4[_0x5e2d48(0x1bf)]+0x14)*(0x1+this['currentLevel']/0x38):0x0;log('Calculating\x20round\x20score:\x20points='+_0x4cbed4[_0x5e2d48(0x23c)]+_0x5e2d48(0x210)+_0x4cbed4[_0x5e2d48(0x16e)]+_0x5e2d48(0x12e)+_0x4cbed4[_0x5e2d48(0x1bf)][_0x5e2d48(0x337)](0x2)+',\x20level='+this['currentLevel']),log(_0x5e2d48(0x288)+_0x4cbed4['points']+_0x5e2d48(0x1fe)+_0x4cbed4[_0x5e2d48(0x16e)]+_0x5e2d48(0x278)+_0x4cbed4[_0x5e2d48(0x1bf)]+_0x5e2d48(0x152)+this[_0x5e2d48(0x262)]+_0x5e2d48(0x287)+_0x59a543),this[_0x5e2d48(0x151)]+=_0x59a543,log(_0x5e2d48(0x35e)+_0x4cbed4[_0x5e2d48(0x23c)]+_0x5e2d48(0x13d)+_0x4cbed4['matches']+',\x20Health\x20Left:\x20'+_0x4cbed4['healthPercentage']['toFixed'](0x2)+'%'),log(_0x5e2d48(0x34f)+_0x59a543+_0x5e2d48(0x245)+this[_0x5e2d48(0x151)]);}}await this['saveScoreToDatabase'](this[_0x5e2d48(0x262)]);this['currentLevel']===opponentsConfig[_0x5e2d48(0x132)]?(this[_0x5e2d48(0x272)]['finalWin']['play'](),log(_0x5e2d48(0x24a)+this[_0x5e2d48(0x151)]),this[_0x5e2d48(0x151)]=0x0,await this[_0x5e2d48(0x177)](),log(_0x5e2d48(0x1de))):(this[_0x5e2d48(0x262)]+=0x1,await this[_0x5e2d48(0x2bc)](),console['log']('Progress\x20saved:\x20currentLevel='+this[_0x5e2d48(0x262)]),this[_0x5e2d48(0x272)][_0x5e2d48(0x209)]['play']());const _0x282946=themes[_0x5e2d48(0x2e8)](_0x2bd409=>_0x2bd409['items'])['find'](_0x2bcce0=>_0x2bcce0[_0x5e2d48(0x19b)]===this[_0x5e2d48(0x150)]),_0x8f081e=_0x282946?.[_0x5e2d48(0x230)]||_0x5e2d48(0x312),_0x38c785=this[_0x5e2d48(0x31b)]+_0x5e2d48(0x311)+this[_0x5e2d48(0x137)][_0x5e2d48(0x343)]['toLowerCase']()[_0x5e2d48(0x2a2)](/ /g,'-')+'.'+_0x8f081e,_0xb093b1=document['getElementById']('p2-image'),_0x46382f=_0xb093b1[_0x5e2d48(0x172)];if(this['player2'][_0x5e2d48(0x2dc)]==='video'){if(_0xb093b1[_0x5e2d48(0x136)]!==_0x5e2d48(0x23a)){const _0x151041=document['createElement'](_0x5e2d48(0x2f3));_0x151041['id']=_0x5e2d48(0x305),_0x151041['src']=_0x38c785,_0x151041['autoplay']=!![],_0x151041['loop']=!![],_0x151041[_0x5e2d48(0x19a)]=!![],_0x151041[_0x5e2d48(0x2f5)]=this[_0x5e2d48(0x137)][_0x5e2d48(0x343)],_0x46382f[_0x5e2d48(0x355)](_0x151041,_0xb093b1);}else _0xb093b1['src']=_0x38c785;}else{if(_0xb093b1[_0x5e2d48(0x136)]!==_0x5e2d48(0x2d0)){const _0x37a278=document[_0x5e2d48(0x2c8)](_0x5e2d48(0x164));_0x37a278['id']=_0x5e2d48(0x305),_0x37a278['src']=_0x38c785,_0x37a278['alt']=this[_0x5e2d48(0x137)]['name'],_0x46382f[_0x5e2d48(0x355)](_0x37a278,_0xb093b1);}else _0xb093b1[_0x5e2d48(0x16b)]=_0x38c785;}const _0x3de007=document['getElementById'](_0x5e2d48(0x305));_0x3de007[_0x5e2d48(0x2b3)][_0x5e2d48(0x1ac)]='block',_0x3de007['classList']['add'](_0x5e2d48(0x193)),p1Image[_0x5e2d48(0x2a5)][_0x5e2d48(0x2f4)](_0x5e2d48(0x259)),this[_0x5e2d48(0x289)]();}}this[_0x5e2d48(0x229)]=![],console[_0x5e2d48(0x2e5)](_0x5e2d48(0x200)+this['currentLevel']+_0x5e2d48(0x146)+this[_0x5e2d48(0x16a)]);}async['saveScoreToDatabase'](_0x392326){const _0x49dfb1=_0x22bcae,_0xfe28c8={'level':_0x392326,'score':this[_0x49dfb1(0x151)]};console[_0x49dfb1(0x2e5)](_0x49dfb1(0x181)+_0xfe28c8[_0x49dfb1(0x256)]+_0x49dfb1(0x2be)+_0xfe28c8[_0x49dfb1(0x269)]);try{const _0x7154f5=await fetch(_0x49dfb1(0x2b6),{'method':_0x49dfb1(0x19f),'headers':{'Content-Type':_0x49dfb1(0x31e)},'body':JSON[_0x49dfb1(0x349)](_0xfe28c8)});if(!_0x7154f5['ok'])throw new Error(_0x49dfb1(0x248)+_0x7154f5[_0x49dfb1(0x1bd)]);const _0x521aaf=await _0x7154f5[_0x49dfb1(0x130)]();console[_0x49dfb1(0x2e5)](_0x49dfb1(0x356),_0x521aaf),log(_0x49dfb1(0x315)+_0x521aaf[_0x49dfb1(0x256)]+_0x49dfb1(0x22d)+_0x521aaf[_0x49dfb1(0x269)][_0x49dfb1(0x337)](0x2)),_0x521aaf[_0x49dfb1(0x1bd)]==='success'?log(_0x49dfb1(0x277)+_0x521aaf[_0x49dfb1(0x256)]+_0x49dfb1(0x185)+_0x521aaf[_0x49dfb1(0x269)][_0x49dfb1(0x337)](0x2)+_0x49dfb1(0x12b)+_0x521aaf[_0x49dfb1(0x2d2)]):log(_0x49dfb1(0x352)+_0x521aaf[_0x49dfb1(0x2db)]);}catch(_0x3b3ffd){console['error'](_0x49dfb1(0x26f),_0x3b3ffd),log(_0x49dfb1(0x17a)+_0x3b3ffd[_0x49dfb1(0x2db)]);}}[_0x22bcae(0x238)](_0x580ddf,_0x261070,_0x3cde92,_0x493ec4){const _0xc0f2b8=_0x22bcae,_0x4cccac=_0x580ddf['style'][_0xc0f2b8(0x25d)]||'',_0x3362b9=_0x4cccac[_0xc0f2b8(0x1a2)]('scaleX')?_0x4cccac[_0xc0f2b8(0x1b7)](/scaleX\([^)]+\)/)[0x0]:'';_0x580ddf[_0xc0f2b8(0x2b3)][_0xc0f2b8(0x2e0)]=_0xc0f2b8(0x25e)+_0x493ec4/0x2/0x3e8+_0xc0f2b8(0x2cd),_0x580ddf[_0xc0f2b8(0x2b3)][_0xc0f2b8(0x25d)]='translateX('+_0x261070+_0xc0f2b8(0x1e1)+_0x3362b9,_0x580ddf[_0xc0f2b8(0x2a5)][_0xc0f2b8(0x2f4)](_0x3cde92),setTimeout(()=>{const _0x12de03=_0xc0f2b8;_0x580ddf[_0x12de03(0x2b3)][_0x12de03(0x25d)]=_0x3362b9,setTimeout(()=>{const _0x555bba=_0x12de03;_0x580ddf[_0x555bba(0x2a5)][_0x555bba(0x2f8)](_0x3cde92);},_0x493ec4/0x2);},_0x493ec4/0x2);}['animateAttack'](_0x2a8bd6,_0x38b598,_0x3e93ec){const _0x3f88b8=_0x22bcae,_0x13d5fe=_0x2a8bd6===this[_0x3f88b8(0x318)]?p1Image:p2Image,_0x521a6d=_0x2a8bd6===this['player1']?0x1:-0x1,_0x2c5ea6=Math[_0x3f88b8(0x35c)](0xa,0x2+_0x38b598*0.4),_0x4d308f=_0x521a6d*_0x2c5ea6,_0x2eb9d5='glow-'+_0x3e93ec;this[_0x3f88b8(0x238)](_0x13d5fe,_0x4d308f,_0x2eb9d5,0xc8);}[_0x22bcae(0x1f3)](_0x327ab0){const _0xfd9857=_0x22bcae,_0x34388c=_0x327ab0===this['player1']?p1Image:p2Image;this['applyAnimation'](_0x34388c,0x0,_0xfd9857(0x335),0xc8);}['animateRecoil'](_0x48c6fc,_0x65b0f1){const _0x312c28=_0x22bcae,_0x397762=_0x48c6fc===this[_0x312c28(0x318)]?p1Image:p2Image,_0x1b810a=_0x48c6fc===this['player1']?-0x1:0x1,_0x54b819=Math[_0x312c28(0x35c)](0xa,0x2+_0x65b0f1*0.4),_0x2544d0=_0x1b810a*_0x54b819;this['applyAnimation'](_0x397762,_0x2544d0,_0x312c28(0x299),0xc8);}}function randomChoice(_0x5e3335){const _0x508aaa=_0x22bcae;return _0x5e3335[Math[_0x508aaa(0x1ab)](Math[_0x508aaa(0x324)]()*_0x5e3335['length'])];}function _0x2bd9(_0x29d47b,_0x24d401){const _0x30a2a2=_0x30a2();return _0x2bd9=function(_0x2bd947,_0x254c52){_0x2bd947=_0x2bd947-0x128;let _0x33c776=_0x30a2a2[_0x2bd947];return _0x33c776;},_0x2bd9(_0x29d47b,_0x24d401);}function log(_0x5b233e){const _0x38e19d=_0x22bcae,_0x559103=document[_0x38e19d(0x28c)](_0x38e19d(0x169)),_0x302b3a=document[_0x38e19d(0x2c8)]('li');_0x302b3a['textContent']=_0x5b233e,_0x559103[_0x38e19d(0x281)](_0x302b3a,_0x559103[_0x38e19d(0x21b)]),_0x559103['children'][_0x38e19d(0x132)]>0x32&&_0x559103['removeChild'](_0x559103[_0x38e19d(0x1d3)]),_0x559103['scrollTop']=0x0;}const turnIndicator=document[_0x22bcae(0x28c)]('turn-indicator'),p1Name=document[_0x22bcae(0x28c)](_0x22bcae(0x143)),p1Image=document[_0x22bcae(0x28c)](_0x22bcae(0x2e7)),p1Health=document[_0x22bcae(0x28c)]('p1-health'),p1Hp=document[_0x22bcae(0x28c)](_0x22bcae(0x1f8)),p1Strength=document[_0x22bcae(0x28c)](_0x22bcae(0x21c)),p1Speed=document[_0x22bcae(0x28c)](_0x22bcae(0x240)),p1Tactics=document[_0x22bcae(0x28c)]('p1-tactics'),p1Size=document[_0x22bcae(0x28c)](_0x22bcae(0x34e)),p1Powerup=document[_0x22bcae(0x28c)](_0x22bcae(0x1af)),p1Type=document[_0x22bcae(0x28c)](_0x22bcae(0x308)),p2Name=document[_0x22bcae(0x28c)](_0x22bcae(0x1e8)),p2Image=document['getElementById'](_0x22bcae(0x305)),p2Health=document[_0x22bcae(0x28c)](_0x22bcae(0x1c8)),p2Hp=document[_0x22bcae(0x28c)](_0x22bcae(0x13f)),p2Strength=document[_0x22bcae(0x28c)](_0x22bcae(0x15f)),p2Speed=document['getElementById'](_0x22bcae(0x2c9)),p2Tactics=document[_0x22bcae(0x28c)](_0x22bcae(0x1b5)),p2Size=document[_0x22bcae(0x28c)](_0x22bcae(0x14d)),p2Powerup=document[_0x22bcae(0x28c)](_0x22bcae(0x1f7)),p2Type=document['getElementById'](_0x22bcae(0x141)),battleLog=document['getElementById']('battle-log'),gameOver=document[_0x22bcae(0x28c)](_0x22bcae(0x1bc)),assetCache={};async function getAssets(_0x3e6fbd){const _0xcb8c64=_0x22bcae;if(assetCache[_0x3e6fbd])return console['log']('getAssets:\x20Cache\x20hit\x20for\x20'+_0x3e6fbd),assetCache[_0x3e6fbd];console[_0xcb8c64(0x2bb)](_0xcb8c64(0x1b6)+_0x3e6fbd);let _0x4128ac=[];try{console[_0xcb8c64(0x2e5)](_0xcb8c64(0x190));const _0x5f0fbc=await Promise[_0xcb8c64(0x1d5)]([fetch(_0xcb8c64(0x1df),{'method':_0xcb8c64(0x19f),'headers':{'Content-Type':_0xcb8c64(0x31e)},'body':JSON['stringify']({'theme':_0xcb8c64(0x216)})}),new Promise((_0x2d79b3,_0x17aea9)=>setTimeout(()=>_0x17aea9(new Error(_0xcb8c64(0x251))),0x3e8))]);console[_0xcb8c64(0x2e5)](_0xcb8c64(0x220),_0x5f0fbc['status']);if(!_0x5f0fbc['ok'])throw new Error('Monstrocity\x20HTTP\x20error!\x20Status:\x20'+_0x5f0fbc[_0xcb8c64(0x1bd)]);_0x4128ac=await _0x5f0fbc[_0xcb8c64(0x130)](),console[_0xcb8c64(0x2e5)](_0xcb8c64(0x32d),_0x4128ac),!Array['isArray'](_0x4128ac)&&(_0x4128ac=[_0x4128ac]),_0x4128ac=_0x4128ac['map']((_0x40ae50,_0x1214d1)=>{const _0x36345c=_0xcb8c64,_0x33f0fe={..._0x40ae50,'theme':_0x36345c(0x216),'name':_0x40ae50[_0x36345c(0x343)]||_0x36345c(0x188)+_0x1214d1,'strength':_0x40ae50[_0x36345c(0x32e)]||0x4,'speed':_0x40ae50[_0x36345c(0x21d)]||0x4,'tactics':_0x40ae50[_0x36345c(0x255)]||0x4,'size':_0x40ae50['size']||_0x36345c(0x189),'type':_0x40ae50[_0x36345c(0x224)]||_0x36345c(0x313),'powerup':_0x40ae50[_0x36345c(0x32c)]||_0x36345c(0x282)};return _0x33f0fe;});}catch(_0x3e5688){console[_0xcb8c64(0x184)](_0xcb8c64(0x142),_0x3e5688),_0x4128ac=[{'name':_0xcb8c64(0x29a),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0xcb8c64(0x189),'type':_0xcb8c64(0x313),'powerup':_0xcb8c64(0x282),'theme':'monstrocity'},{'name':_0xcb8c64(0x215),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0xcb8c64(0x23f),'type':'Base','powerup':_0xcb8c64(0x25f),'theme':_0xcb8c64(0x216)}],console['log'](_0xcb8c64(0x12f));}if(_0x3e6fbd===_0xcb8c64(0x216))return console[_0xcb8c64(0x2e5)](_0xcb8c64(0x30c)),assetCache[_0x3e6fbd]=_0x4128ac,console[_0xcb8c64(0x227)](_0xcb8c64(0x1b6)+_0x3e6fbd),_0x4128ac;let _0x443f19=null;for(const _0x118ff6 of themes){_0x443f19=_0x118ff6[_0xcb8c64(0x267)][_0xcb8c64(0x2fe)](_0xbfa7ca=>_0xbfa7ca[_0xcb8c64(0x19b)]===_0x3e6fbd);if(_0x443f19)break;}if(!_0x443f19)return console[_0xcb8c64(0x2a6)]('getAssets:\x20Theme\x20not\x20found:\x20'+_0x3e6fbd),assetCache[_0x3e6fbd]=_0x4128ac,console[_0xcb8c64(0x227)](_0xcb8c64(0x1b6)+_0x3e6fbd),_0x4128ac;const _0x56d563=_0x443f19['policyIds']?_0x443f19[_0xcb8c64(0x15a)][_0xcb8c64(0x239)](',')[_0xcb8c64(0x202)](_0x4dd981=>_0x4dd981[_0xcb8c64(0x285)]()):[];if(!_0x56d563['length'])return console[_0xcb8c64(0x2e5)](_0xcb8c64(0x293)+_0x3e6fbd),assetCache[_0x3e6fbd]=_0x4128ac,console[_0xcb8c64(0x227)](_0xcb8c64(0x1b6)+_0x3e6fbd),_0x4128ac;const _0x693389=_0x443f19['orientations']?_0x443f19[_0xcb8c64(0x2c3)][_0xcb8c64(0x239)](',')[_0xcb8c64(0x202)](_0x2876f1=>_0x2876f1[_0xcb8c64(0x285)]()):[],_0x2fd610=_0x443f19[_0xcb8c64(0x304)]?_0x443f19['ipfsPrefixes']['split'](',')[_0xcb8c64(0x202)](_0x526fae=>_0x526fae[_0xcb8c64(0x285)]()):[],_0x46868e=_0x56d563[_0xcb8c64(0x317)]((_0x287834,_0x3a40ed)=>({'policyId':_0x287834,'orientation':_0x693389[_0xcb8c64(0x132)]===0x1?_0x693389[0x0]:_0x693389[_0x3a40ed]||_0xcb8c64(0x155),'ipfsPrefix':_0x2fd610['length']===0x1?_0x2fd610[0x0]:_0x2fd610[_0x3a40ed]||_0xcb8c64(0x26e)}));let _0xf3f205=[];try{const _0x424318=JSON[_0xcb8c64(0x349)]({'policyIds':_0x46868e['map'](_0x313a3e=>_0x313a3e[_0xcb8c64(0x170)]),'theme':_0x3e6fbd});console[_0xcb8c64(0x2e5)](_0xcb8c64(0x35d));const _0x45eca7=await Promise['race']([fetch(_0xcb8c64(0x1f5),{'method':_0xcb8c64(0x19f),'headers':{'Content-Type':_0xcb8c64(0x31e)},'body':_0x424318}),new Promise((_0x426dff,_0x58b339)=>setTimeout(()=>_0x58b339(new Error(_0xcb8c64(0x286))),0x2710))]);if(!_0x45eca7['ok'])throw new Error(_0xcb8c64(0x298)+_0x45eca7['status']);const _0x2f1f18=await _0x45eca7['text']();let _0x525a9b;try{_0x525a9b=JSON[_0xcb8c64(0x1eb)](_0x2f1f18);}catch(_0x4f1e52){console[_0xcb8c64(0x184)](_0xcb8c64(0x2c4),_0x4f1e52);throw _0x4f1e52;}_0x525a9b===![]||_0x525a9b===_0xcb8c64(0x235)?(console[_0xcb8c64(0x2e5)]('getAssets:\x20NFT\x20data\x20is\x20false'),_0xf3f205=[]):_0xf3f205=Array[_0xcb8c64(0x2d1)](_0x525a9b)?_0x525a9b:[_0x525a9b],_0xf3f205=_0xf3f205[_0xcb8c64(0x317)]((_0x5075ca,_0x5e4050)=>{const _0x1766c9=_0xcb8c64,_0x3e25b8={..._0x5075ca,'theme':_0x3e6fbd,'name':_0x5075ca['name']||'NFT_Unknown_'+_0x5e4050,'strength':_0x5075ca[_0x1766c9(0x32e)]||0x4,'speed':_0x5075ca[_0x1766c9(0x21d)]||0x4,'tactics':_0x5075ca['tactics']||0x4,'size':_0x5075ca[_0x1766c9(0x187)]||_0x1766c9(0x189),'type':_0x5075ca[_0x1766c9(0x224)]||_0x1766c9(0x313),'powerup':_0x5075ca['powerup']||_0x1766c9(0x282),'policyId':_0x5075ca[_0x1766c9(0x170)]||_0x46868e[0x0][_0x1766c9(0x170)],'ipfs':_0x5075ca[_0x1766c9(0x1cd)]||''};return _0x3e25b8;});}catch(_0x306663){console[_0xcb8c64(0x184)](_0xcb8c64(0x163)+_0x3e6fbd+':',_0x306663),_0xf3f205=[];}const _0x58e963=[..._0x4128ac,..._0xf3f205];return console[_0xcb8c64(0x2e5)](_0xcb8c64(0x1f6)+_0x58e963[_0xcb8c64(0x132)]),assetCache[_0x3e6fbd]=_0x58e963,console[_0xcb8c64(0x227)](_0xcb8c64(0x1b6)+_0x3e6fbd),_0x58e963;}document[_0x22bcae(0x1d1)](_0x22bcae(0x168),function(){var _0x5e3b45=function(){const _0x2a1098=_0x2bd9;var _0xdfe672=localStorage[_0x2a1098(0x34c)](_0x2a1098(0x1e9))||_0x2a1098(0x216);getAssets(_0xdfe672)[_0x2a1098(0x22a)](function(_0x24705a){const _0x476ee9=_0x2a1098;console[_0x476ee9(0x2e5)](_0x476ee9(0x297),_0x24705a);var _0x3da676=new MonstrocityMatch3(_0x24705a,_0xdfe672);console[_0x476ee9(0x2e5)](_0x476ee9(0x19e)),_0x3da676[_0x476ee9(0x29c)]()[_0x476ee9(0x22a)](function(){const _0x31b885=_0x476ee9;console[_0x31b885(0x2e5)]('Main:\x20Game\x20initialized\x20successfully'),document[_0x31b885(0x1f2)](_0x31b885(0x228))['src']=_0x3da676[_0x31b885(0x31b)]+'logo.png';});})[_0x2a1098(0x1ad)](function(_0x43478a){const _0x17a721=_0x2a1098;console[_0x17a721(0x184)](_0x17a721(0x2c6),_0x43478a);});};_0x5e3b45();});
  </script>
</body>
</html>
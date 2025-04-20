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
	  height: 100%;
	  max-height: 2050px;
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
      
      .character img, .character video {
		  width: 85px;
		  height: auto;
          float: left;
          position: absolute;
          left: 15px;
          top: 55px;
		  min-height: 40px;
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
	        },
	        {
	          value: "fauna",
	          project: "Nemonium",
	          title: "Fauna x Nemonium",
	          policyIds: "7cd357f96d7a7325ff3038e78e004840706887790d8b513913b45c27",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "sh4pes",
	          project: "Nemonium",
	          title: "Sh4pes x Nemonium",
	          policyIds: "2d868badf3dc317234fe253859621fedf661bf9eba275faea80a8bfe",
	          orientations: "Left",
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
	          value: "skada",
	          project: "Ritual",
	          title: "Skada",
	          policyIds: "2eacad9ddcb9edd7721af49f682bd356e8e28194bafa4bbc2f559bb7",
	          orientations: "Left",
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
	          title: "Cardanian Snow Globes (GIF)",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "gif" // Applies only to character images
	        },
	        {
	          value: "cardanians2",
	          project: "Cardanians",
	          title: "Cardanian Snow Globes (MOV)",
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
	  
const _0x56fcfe=_0x3067;(function(_0x4ff749,_0x1a64e7){const _0x4a8f43=_0x3067,_0x42e5d3=_0x4ff749();while(!![]){try{const _0x5dfbbe=-parseInt(_0x4a8f43(0x172))/0x1*(parseInt(_0x4a8f43(0xcf))/0x2)+parseInt(_0x4a8f43(0x18a))/0x3*(parseInt(_0x4a8f43(0x2d3))/0x4)+parseInt(_0x4a8f43(0xa6))/0x5*(-parseInt(_0x4a8f43(0x1cf))/0x6)+-parseInt(_0x4a8f43(0x201))/0x7*(-parseInt(_0x4a8f43(0x1fb))/0x8)+-parseInt(_0x4a8f43(0x236))/0x9+-parseInt(_0x4a8f43(0x27f))/0xa+parseInt(_0x4a8f43(0x182))/0xb;if(_0x5dfbbe===_0x1a64e7)break;else _0x42e5d3['push'](_0x42e5d3['shift']());}catch(_0x3c9bc8){_0x42e5d3['push'](_0x42e5d3['shift']());}}}(_0xbdcb,0x509f1));function showThemeSelect(_0x362bd0){const _0x4da090=_0x3067;console['time'](_0x4da090(0x212));let _0x498af7=document[_0x4da090(0x150)]('theme-select-container');const _0x38b72c=document[_0x4da090(0x150)]('character-select-container');_0x498af7['innerHTML']=_0x4da090(0x1a9);const _0x31b933=document[_0x4da090(0x150)](_0x4da090(0x227));_0x498af7[_0x4da090(0x27b)][_0x4da090(0x19a)]='block',_0x38b72c['style'][_0x4da090(0x19a)]=_0x4da090(0x208),themes[_0x4da090(0x220)](_0x19b43e=>{const _0x12bc86=_0x4da090,_0x3fbff1=document[_0x12bc86(0x16a)](_0x12bc86(0x1fd));_0x3fbff1['className']='theme-group';const _0x71e55=document[_0x12bc86(0x16a)]('h3');_0x71e55[_0x12bc86(0xd4)]=_0x19b43e['group'],_0x3fbff1[_0x12bc86(0x1b3)](_0x71e55),_0x19b43e['items']['forEach'](_0x4d4395=>{const _0x176823=_0x12bc86,_0x4c9581=document[_0x176823(0x16a)](_0x176823(0x1fd));_0x4c9581[_0x176823(0xd5)]=_0x176823(0x2c6);if(_0x4d4395['background']){const _0x5c04d2=_0x176823(0x278)+_0x4d4395[_0x176823(0x1d3)]+_0x176823(0x2cf);_0x4c9581['style']['backgroundImage']=_0x176823(0x28b)+_0x5c04d2+')';}const _0x5659c5=_0x176823(0x278)+_0x4d4395[_0x176823(0x1d3)]+'/logo.png';_0x4c9581[_0x176823(0x256)]=_0x176823(0x210)+_0x5659c5+_0x176823(0x2a2)+_0x4d4395['title']+_0x176823(0x1b7)+_0x4d4395[_0x176823(0x147)]+'\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>'+_0x4d4395['title']+_0x176823(0x22e),_0x4c9581[_0x176823(0xb6)]('click',()=>{const _0xafb8fe=_0x176823,_0x3b1092=document[_0xafb8fe(0x150)]('character-options');_0x3b1092&&(_0x3b1092[_0xafb8fe(0x256)]=_0xafb8fe(0x193)),_0x498af7[_0xafb8fe(0x256)]='',_0x498af7[_0xafb8fe(0x27b)][_0xafb8fe(0x19a)]=_0xafb8fe(0x208),_0x38b72c['style'][_0xafb8fe(0x19a)]='block',_0x362bd0[_0xafb8fe(0x260)](_0x4d4395[_0xafb8fe(0x1d3)]);}),_0x3fbff1[_0x176823(0x1b3)](_0x4c9581);}),_0x31b933[_0x12bc86(0x1b3)](_0x3fbff1);}),console[_0x4da090(0x2aa)](_0x4da090(0x212));}const opponentsConfig=[{'name':_0x56fcfe(0xcc),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0x28a),'powerup':'Minor\x20Regen','theme':_0x56fcfe(0x127)},{'name':'Merdock','strength':0x1,'speed':0x1,'tactics':0x1,'size':'Large','type':'Base','powerup':_0x56fcfe(0x252),'theme':'monstrocity'},{'name':_0x56fcfe(0xfa),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x56fcfe(0x2c9),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x252),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x2c7),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x252),'theme':_0x56fcfe(0x127)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':'Base','powerup':_0x56fcfe(0x1f2),'theme':'monstrocity'},{'name':_0x56fcfe(0x14c),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x1f2),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x20b),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x56fcfe(0x2c9),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x1f2),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x11a),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x56fcfe(0x1d2),'type':'Base','powerup':'Regenerate','theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x224),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x134),'theme':'monstrocity'},{'name':_0x56fcfe(0x25f),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0x56fcfe(0x28a),'powerup':'Boost\x20Attack','theme':'monstrocity'},{'name':_0x56fcfe(0x1a4),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x56fcfe(0x2c9),'type':'Base','powerup':_0x56fcfe(0x24a),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x280),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x56fcfe(0x117),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x24a),'theme':'monstrocity'},{'name':_0x56fcfe(0xc5),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0x28a),'powerup':_0x56fcfe(0x24a),'theme':'monstrocity'},{'name':_0x56fcfe(0x2d4),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0x28a),'powerup':'Heal','theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0xcc),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0xb4),'powerup':'Minor\x20Regen','theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x1ff),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x56fcfe(0x117),'type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x252),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0xfa),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x56fcfe(0x2c9),'type':'Leader','powerup':_0x56fcfe(0x252),'theme':_0x56fcfe(0x127)},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x56fcfe(0x1d2),'type':'Leader','powerup':_0x56fcfe(0x1af),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x13d),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x1f2),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x14c),'strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x1f2),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x20b),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Small','type':'Leader','powerup':'Regenerate','theme':'monstrocity'},{'name':_0x56fcfe(0x11a),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x56fcfe(0x1d2),'type':'Leader','powerup':'Regenerate','theme':'monstrocity'},{'name':_0x56fcfe(0x224),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x134),'theme':_0x56fcfe(0x127)},{'name':'Jarhead','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0xb4),'powerup':'Boost\x20Attack','theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x1a4),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x56fcfe(0x2c9),'type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x24a),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0x280),'strength':0x7,'speed':0x7,'tactics':0x7,'size':'Large','type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x24a),'theme':_0x56fcfe(0x127)},{'name':_0x56fcfe(0xc5),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x24a),'theme':_0x56fcfe(0x127)},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x56fcfe(0x1d2),'type':_0x56fcfe(0xb4),'powerup':_0x56fcfe(0x24a),'theme':_0x56fcfe(0x127)}],characterDirections={'Billandar\x20and\x20Ted':_0x56fcfe(0x288),'Craig':'Left','Dankle':_0x56fcfe(0x288),'Drake':_0x56fcfe(0xec),'Goblin\x20Ganger':_0x56fcfe(0x288),'Jarhead':_0x56fcfe(0xec),'Katastrophy':'Right','Koipon':'Left','Mandiblus':'Left','Merdock':'Left','Ouchie':'Left','Slime\x20Mind':'Right','Spydrax':'Right','Texby':_0x56fcfe(0x288)};class MonstrocityMatch3{constructor(_0x508590,_0x20df7c){const _0x2d6e59=_0x56fcfe;this['isTouchDevice']=_0x2d6e59(0x1aa)in window||navigator[_0x2d6e59(0xaf)]>0x0||navigator[_0x2d6e59(0x23e)]>0x0,this[_0x2d6e59(0x19b)]=0x5,this[_0x2d6e59(0x1c8)]=0x5,this['board']=[],this[_0x2d6e59(0x24f)]=null,this[_0x2d6e59(0x101)]=![],this['currentTurn']=null,this[_0x2d6e59(0xd9)]=null,this[_0x2d6e59(0xf8)]=null,this[_0x2d6e59(0x27e)]=_0x2d6e59(0x21a),this['isDragging']=![],this[_0x2d6e59(0x165)]=null,this[_0x2d6e59(0x9f)]=null,this[_0x2d6e59(0x2b5)]=0x0,this[_0x2d6e59(0x121)]=0x0,this[_0x2d6e59(0x211)]=0x1,this[_0x2d6e59(0x132)]=_0x508590,this[_0x2d6e59(0x270)]=[],this[_0x2d6e59(0x163)]=![],this['tileTypes']=[_0x2d6e59(0x29b),_0x2d6e59(0x296),'special-attack',_0x2d6e59(0x196),_0x2d6e59(0x189)],this[_0x2d6e59(0xbb)]=[],this[_0x2d6e59(0x151)]=0x0;const _0x10a83f=themes[_0x2d6e59(0x20a)](_0x172168=>_0x172168[_0x2d6e59(0xb3)])['map'](_0x1f6ec9=>_0x1f6ec9['value']),_0x4918de=localStorage[_0x2d6e59(0x2ae)]('gameTheme');this['theme']=_0x4918de&&_0x10a83f[_0x2d6e59(0x185)](_0x4918de)?_0x4918de:_0x20df7c&&_0x10a83f[_0x2d6e59(0x185)](_0x20df7c)?_0x20df7c:_0x2d6e59(0x127),console['log'](_0x2d6e59(0x225)+_0x20df7c+',\x20storedTheme='+_0x4918de+_0x2d6e59(0x169)+this[_0x2d6e59(0xa0)]),this['baseImagePath']=_0x2d6e59(0x278)+this[_0x2d6e59(0xa0)]+'/',this['sounds']={'match':new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),'cascade':new Audio(_0x2d6e59(0x1f0)),'badMove':new Audio(_0x2d6e59(0x2bc)),'gameOver':new Audio(_0x2d6e59(0x179)),'reset':new Audio('https://www.skulliance.io/staking/sounds/voice_go.ogg'),'loss':new Audio(_0x2d6e59(0x114)),'win':new Audio(_0x2d6e59(0x1ae)),'finalWin':new Audio(_0x2d6e59(0xe3)),'powerGem':new Audio(_0x2d6e59(0x10c)),'hyperCube':new Audio(_0x2d6e59(0x1cb)),'multiMatch':new Audio('https://www.skulliance.io/staking/sounds/speedmatch1.ogg')},this[_0x2d6e59(0x177)](),this[_0x2d6e59(0x1b4)]();}[_0x56fcfe(0x12b)](_0x4fc72c){const _0x1b003c=_0x56fcfe;let _0x41ac44=0x0;const _0xd73735=JSON[_0x1b003c(0x1d5)](_0x4fc72c[_0x1b003c(0xac)](_0x3e5014=>_0x3e5014[_0x1b003c(0xac)](_0x3f7957=>_0x3f7957[_0x1b003c(0x2e0)])));for(let _0x441565=0x0;_0x441565<_0xd73735[_0x1b003c(0x23c)];_0x441565++){_0x41ac44=(_0x41ac44<<0x5)-_0x41ac44+_0xd73735['charCodeAt'](_0x441565),_0x41ac44|=0x0;}return _0x41ac44['toString']();}[_0x56fcfe(0xb0)](){const _0xbc0b3d=_0x56fcfe,_0x20866b={'level':this['currentLevel'],'board':this[_0xbc0b3d(0x1ac)]['map'](_0x3cfc5e=>_0x3cfc5e['map'](_0x4148cd=>({'type':_0x4148cd[_0xbc0b3d(0x2e0)]}))),'hash':this[_0xbc0b3d(0x12b)](this[_0xbc0b3d(0x1ac)])};localStorage['setItem']('board_level_'+this[_0xbc0b3d(0x211)],JSON[_0xbc0b3d(0x1d5)](_0x20866b));}[_0x56fcfe(0x1cd)](){const _0x45ac84=_0x56fcfe,_0x4a783d=_0x45ac84(0x140)+this[_0x45ac84(0x211)],_0x56c7e9=JSON['parse'](localStorage[_0x45ac84(0x2ae)](_0x4a783d));if(_0x56c7e9&&_0x56c7e9[_0x45ac84(0x1e3)]===this[_0x45ac84(0x211)]){const _0x69b162=this[_0x45ac84(0x12b)](_0x56c7e9[_0x45ac84(0x1ac)]);if(_0x56c7e9[_0x45ac84(0x119)]===_0x69b162)return _0x56c7e9[_0x45ac84(0x1ac)][_0x45ac84(0xac)](_0xb2b09f=>_0xb2b09f['map'](_0x56e26f=>({'type':_0x56e26f[_0x45ac84(0x2e0)],'element':null})));else console[_0x45ac84(0xad)](_0x45ac84(0x1d4)+this[_0x45ac84(0x211)]+',\x20generating\x20new\x20board');}return null;}[_0x56fcfe(0x24c)](){const _0x133dbb=_0x56fcfe;localStorage['removeItem'](_0x133dbb(0x140)+this['currentLevel']);}async[_0x56fcfe(0x16b)](){const _0x17ad49=_0x56fcfe;console[_0x17ad49(0x199)](_0x17ad49(0x10d)),this[_0x17ad49(0x270)]=this['playerCharactersConfig']['map'](_0xd10b5b=>this['createCharacter'](_0xd10b5b)),await this[_0x17ad49(0x266)](!![]);const _0x58acb6=await this[_0x17ad49(0xae)](),{loadedLevel:_0x559fcf,loadedScore:_0x2f4594,hasProgress:_0x36e193}=_0x58acb6;if(_0x36e193){console[_0x17ad49(0x199)]('init:\x20Prompting\x20with\x20loadedLevel='+_0x559fcf+_0x17ad49(0x2d0)+_0x2f4594);const _0x270bf1=await this[_0x17ad49(0xd1)](_0x559fcf,_0x2f4594);_0x270bf1?(this[_0x17ad49(0x211)]=_0x559fcf,this[_0x17ad49(0x151)]=_0x2f4594,log(_0x17ad49(0x17b)+this[_0x17ad49(0x211)]+_0x17ad49(0x118)+this[_0x17ad49(0x151)])):(this['currentLevel']=0x1,this['grandTotalScore']=0x0,await this['clearProgress'](),log('Starting\x20fresh\x20at\x20Level\x201'));}else this[_0x17ad49(0x211)]=0x1,this[_0x17ad49(0x151)]=0x0,log('No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201');console[_0x17ad49(0x199)](_0x17ad49(0x122));}[_0x56fcfe(0x102)](){const _0x47867c=_0x56fcfe;console[_0x47867c(0x199)](_0x47867c(0x1c0)+this[_0x47867c(0xa0)]);const _0x200281=themes[_0x47867c(0x20a)](_0x53fee6=>_0x53fee6['items'])[_0x47867c(0x160)](_0x27cd06=>_0x27cd06['value']===this['theme']);console[_0x47867c(0x199)](_0x47867c(0x21b),_0x200281);const _0x1336c7=_0x47867c(0x278)+this['theme']+_0x47867c(0x2cf);console[_0x47867c(0x199)](_0x47867c(0x2d8)+_0x1336c7),_0x200281&&_0x200281[_0x47867c(0x294)]?(document[_0x47867c(0xab)]['style'][_0x47867c(0x218)]='url('+_0x1336c7+')',document['body']['style'][_0x47867c(0x17e)]=_0x47867c(0x2a1),document[_0x47867c(0xab)]['style'][_0x47867c(0xe4)]='center'):document[_0x47867c(0xab)][_0x47867c(0x27b)][_0x47867c(0x218)]=_0x47867c(0x208);}[_0x56fcfe(0x260)](_0x38b14d){const _0x54b62a=_0x56fcfe;if(updatePending){console[_0x54b62a(0x199)](_0x54b62a(0x2dc));return;}updatePending=!![],console[_0x54b62a(0x11f)](_0x54b62a(0x16d)+_0x38b14d);const _0x4bbf74=this;this[_0x54b62a(0xa0)]=_0x38b14d,this[_0x54b62a(0x10b)]=_0x54b62a(0x278)+this['theme']+'/',localStorage['setItem'](_0x54b62a(0x17a),this[_0x54b62a(0xa0)]),this[_0x54b62a(0x102)](),document[_0x54b62a(0x1e8)](_0x54b62a(0x215))[_0x54b62a(0x1bc)]=this['baseImagePath']+_0x54b62a(0x29e);const _0x314b0a=document[_0x54b62a(0x150)](_0x54b62a(0x2c3));_0x314b0a&&(_0x314b0a[_0x54b62a(0x256)]=_0x54b62a(0x193)),getAssets(this[_0x54b62a(0xa0)])[_0x54b62a(0x2a8)](function(_0x5e38c9){const _0x23a358=_0x54b62a;console[_0x23a358(0x11f)](_0x23a358(0xee)+_0x38b14d),_0x4bbf74[_0x23a358(0x132)]=_0x5e38c9,_0x4bbf74[_0x23a358(0x270)]=[],_0x5e38c9[_0x23a358(0x220)](_0x39846a=>{const _0x345d9e=_0x23a358,_0x2257ab=_0x4bbf74['createCharacter'](_0x39846a);if(_0x2257ab[_0x345d9e(0xa8)]===_0x345d9e(0xf0)){const _0xd30b6a=new Image();_0xd30b6a['src']=_0x2257ab[_0x345d9e(0x2da)],_0xd30b6a['onload']=()=>console[_0x345d9e(0x199)](_0x345d9e(0x1a8)+_0x2257ab[_0x345d9e(0x2da)]),_0xd30b6a['onerror']=()=>console[_0x345d9e(0x199)]('Failed\x20to\x20preload:\x20'+_0x2257ab[_0x345d9e(0x2da)]);}_0x4bbf74['playerCharacters'][_0x345d9e(0xb7)](_0x2257ab);});if(_0x4bbf74[_0x23a358(0xd9)]){const _0x47e921=_0x4bbf74['playerCharactersConfig'][_0x23a358(0x160)](_0x4175af=>_0x4175af['name']===_0x4bbf74[_0x23a358(0xd9)]['name'])||_0x4bbf74[_0x23a358(0x132)][0x0];_0x4bbf74['player1']=_0x4bbf74['createCharacter'](_0x47e921),_0x4bbf74[_0x23a358(0x1b2)]();}_0x4bbf74[_0x23a358(0xf8)]&&(_0x4bbf74[_0x23a358(0xf8)]=_0x4bbf74['createCharacter'](opponentsConfig[_0x4bbf74[_0x23a358(0x211)]-0x1]),_0x4bbf74[_0x23a358(0x129)]());if(_0x4bbf74[_0x23a358(0xd9)]&&_0x4bbf74[_0x23a358(0x27e)]!=='initializing'){const _0x3553f5=document['querySelectorAll'](_0x23a358(0x175));_0x3553f5[_0x23a358(0x220)](_0x2d3388=>{const _0x1b0267=_0x23a358;_0x2d3388[_0x1b0267(0x202)](_0x1b0267(0x286),_0x4bbf74[_0x1b0267(0x97)]),_0x2d3388['removeEventListener']('touchstart',_0x4bbf74[_0x1b0267(0x2c5)]);}),_0x4bbf74[_0x23a358(0x159)](),console[_0x23a358(0x199)](_0x23a358(0xce));}else console[_0x23a358(0x199)](_0x23a358(0x289));_0x4bbf74[_0x23a358(0xd9)]&&(_0x4bbf74[_0x23a358(0xdd)]=![],_0x4bbf74[_0x23a358(0x24f)]=null,_0x4bbf74['targetTile']=null,_0x4bbf74[_0x23a358(0x27e)]=_0x4bbf74['currentTurn']===_0x4bbf74[_0x23a358(0xd9)]?'playerTurn':_0x23a358(0xdb));const _0x30764c=document['getElementById'](_0x23a358(0x234));_0x30764c[_0x23a358(0x27b)][_0x23a358(0x19a)]=_0x23a358(0xfb),_0x4bbf74[_0x23a358(0x266)](_0x4bbf74[_0x23a358(0xd9)]===null),console[_0x23a358(0x2aa)](_0x23a358(0xee)+_0x38b14d),console['timeEnd'](_0x23a358(0x16d)+_0x38b14d),updatePending=![];})[_0x54b62a(0xed)](function(_0x3a53a6){const _0xe0c255=_0x54b62a;console[_0xe0c255(0x2b8)](_0xe0c255(0x1ad),_0x3a53a6),_0x4bbf74['playerCharactersConfig']=[{'name':_0xe0c255(0xcc),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0xe0c255(0x28a),'powerup':'Regenerate','theme':_0xe0c255(0x127)},{'name':'Dankle','strength':0x3,'speed':0x5,'tactics':0x3,'size':_0xe0c255(0x2c9),'type':_0xe0c255(0x28a),'powerup':_0xe0c255(0x24a),'theme':'monstrocity'}],_0x4bbf74['playerCharacters']=_0x4bbf74[_0xe0c255(0x132)][_0xe0c255(0xac)](_0x51b66c=>_0x4bbf74[_0xe0c255(0x145)](_0x51b66c));const _0x1e3a37=document[_0xe0c255(0x150)](_0xe0c255(0x234));_0x1e3a37[_0xe0c255(0x27b)][_0xe0c255(0x19a)]=_0xe0c255(0xfb),_0x4bbf74['showCharacterSelect'](_0x4bbf74[_0xe0c255(0xd9)]===null),console[_0xe0c255(0x2aa)]('updateTheme_'+_0x38b14d),updatePending=![];});}async[_0x56fcfe(0x233)](){const _0x2a0a07=_0x56fcfe,_0x2008ef={'currentLevel':this[_0x2a0a07(0x211)],'grandTotalScore':this['grandTotalScore']};console[_0x2a0a07(0x199)]('Sending\x20saveProgress\x20request\x20with\x20data:',_0x2008ef);try{const _0x4f3863=await fetch(_0x2a0a07(0x13b),{'method':'POST','headers':{'Content-Type':'application/json'},'body':JSON['stringify'](_0x2008ef)});console['log'](_0x2a0a07(0x284),_0x4f3863[_0x2a0a07(0x1d9)]);const _0x1e721b=await _0x4f3863[_0x2a0a07(0x14a)]();console[_0x2a0a07(0x199)]('Raw\x20response\x20text:',_0x1e721b);if(!_0x4f3863['ok'])throw new Error(_0x2a0a07(0x1fe)+_0x4f3863[_0x2a0a07(0x1d9)]);const _0x10dba4=JSON[_0x2a0a07(0x1d6)](_0x1e721b);console[_0x2a0a07(0x199)]('Parsed\x20response:',_0x10dba4),_0x10dba4[_0x2a0a07(0x1d9)]===_0x2a0a07(0x115)?log(_0x2a0a07(0xb1)+this['currentLevel']):console['error'](_0x2a0a07(0x1ea),_0x10dba4[_0x2a0a07(0xbe)]);}catch(_0x4da803){console['error'](_0x2a0a07(0x22c),_0x4da803);}}async[_0x56fcfe(0xae)](){const _0x240f2b=_0x56fcfe;try{console[_0x240f2b(0x199)](_0x240f2b(0x12e));const _0x57c7a4=await fetch(_0x240f2b(0x259),{'method':_0x240f2b(0x178),'headers':{'Content-Type':'application/json'}});console[_0x240f2b(0x199)](_0x240f2b(0x284),_0x57c7a4[_0x240f2b(0x1d9)]);if(!_0x57c7a4['ok'])throw new Error(_0x240f2b(0x1fe)+_0x57c7a4['status']);const _0x19ce9f=await _0x57c7a4[_0x240f2b(0x200)]();console[_0x240f2b(0x199)](_0x240f2b(0x1a1),_0x19ce9f);if(_0x19ce9f[_0x240f2b(0x1d9)]===_0x240f2b(0x115)&&_0x19ce9f[_0x240f2b(0x11b)]){const _0x44fe60=_0x19ce9f['progress'];return{'loadedLevel':_0x44fe60[_0x240f2b(0x211)]||0x1,'loadedScore':_0x44fe60[_0x240f2b(0x151)]||0x0,'hasProgress':!![]};}else return console[_0x240f2b(0x199)](_0x240f2b(0x17f),_0x19ce9f),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x438b67){return console[_0x240f2b(0x2b8)](_0x240f2b(0x1be),_0x438b67),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x56fcfe(0x99)](){const _0x48e784=_0x56fcfe;try{const _0x1c7723=await fetch(_0x48e784(0x2c8),{'method':_0x48e784(0x2a0),'headers':{'Content-Type':'application/json'}});if(!_0x1c7723['ok'])throw new Error(_0x48e784(0x1fe)+_0x1c7723[_0x48e784(0x1d9)]);const _0x3b0257=await _0x1c7723[_0x48e784(0x200)]();_0x3b0257[_0x48e784(0x1d9)]===_0x48e784(0x115)&&(this['currentLevel']=0x1,this[_0x48e784(0x151)]=0x0,this['clearBoard'](),log('Progress\x20cleared'));}catch(_0x2ad5c6){console[_0x48e784(0x2b8)](_0x48e784(0xda),_0x2ad5c6);}}[_0x56fcfe(0x177)](){const _0x164598=_0x56fcfe,_0xfd0a9c=document[_0x164598(0x150)](_0x164598(0x2df)),_0x4e4750=_0xfd0a9c[_0x164598(0xff)]||0x12c;this[_0x164598(0x2be)]=(_0x4e4750-0.5*(this[_0x164598(0x19b)]-0x1))/this[_0x164598(0x19b)];}['createCharacter'](_0x57244a){const _0x44e52d=_0x56fcfe;console[_0x44e52d(0x199)](_0x44e52d(0x261),_0x57244a);var _0x392108,_0x17e6c9,_0x34749e,_0x385ecb=_0x44e52d(0x288),_0x2fa342=![],_0x208e91=_0x44e52d(0xf0);const _0xcb64ab=themes['flatMap'](_0x31e2b3=>_0x31e2b3[_0x44e52d(0xb3)])['find'](_0x3573aa=>_0x3573aa[_0x44e52d(0x1d3)]===this[_0x44e52d(0xa0)]),_0x5d58ad=_0xcb64ab?.[_0x44e52d(0x21d)]||_0x44e52d(0x26c),_0x2374b0=[_0x44e52d(0xfc),_0x44e52d(0x104)],_0x2ec67f=_0x44e52d(0x1d1);if(_0x57244a[_0x44e52d(0x10a)]&&_0x57244a[_0x44e52d(0xa5)]){_0x2fa342=!![];var _0x18a59b={'orientation':'Right','ipfsPrefix':_0xcb64ab?.[_0x44e52d(0x174)]||_0x2ec67f};if(_0xcb64ab&&_0xcb64ab[_0x44e52d(0x105)]){var _0x426432=_0xcb64ab[_0x44e52d(0x105)][_0x44e52d(0x28d)](',')[_0x44e52d(0x2bb)](_0x169f90=>_0x169f90[_0x44e52d(0x12a)]()),_0x3d8f4b=_0xcb64ab[_0x44e52d(0x9a)]?_0xcb64ab[_0x44e52d(0x9a)]['split'](',')[_0x44e52d(0x2bb)](_0x338207=>_0x338207[_0x44e52d(0x12a)]()):[],_0x4909e7=_0xcb64ab[_0x44e52d(0x174)]?_0xcb64ab[_0x44e52d(0x174)]['split'](',')[_0x44e52d(0x2bb)](_0x2044da=>_0x2044da[_0x44e52d(0x12a)]()):[_0x2ec67f],_0x28a6c1=_0x426432[_0x44e52d(0x152)](_0x57244a['policyId']);_0x28a6c1!==-0x1&&(_0x18a59b={'orientation':_0x3d8f4b['length']===0x1?_0x3d8f4b[0x0]:_0x3d8f4b[_0x28a6c1]||'Right','ipfsPrefix':_0x4909e7[_0x44e52d(0x23c)]===0x1?_0x4909e7[0x0]:_0x4909e7[_0x28a6c1]||_0x2ec67f});}_0x18a59b[_0x44e52d(0x130)]===_0x44e52d(0xc8)?_0x385ecb=Math[_0x44e52d(0x2d7)]()<0.5?_0x44e52d(0x288):_0x44e52d(0xec):_0x385ecb=_0x18a59b[_0x44e52d(0x130)];_0x17e6c9=_0x18a59b[_0x44e52d(0x231)]+_0x57244a['ipfs'],_0x34749e=_0x2ec67f+_0x57244a['ipfs'];const _0x57c51b=_0x17e6c9['split']('.')[_0x44e52d(0xc3)]()['toLowerCase']();_0x2374b0['includes'](_0x57c51b)&&(_0x208e91=_0x44e52d(0x113));}else{switch(_0x57244a[_0x44e52d(0x2e0)]){case _0x44e52d(0x28a):_0x392108=_0x44e52d(0x1f5);break;case _0x44e52d(0xb4):_0x392108=_0x44e52d(0x1fc);break;case _0x44e52d(0x1e2):_0x392108='battle-damaged';break;default:_0x392108=_0x44e52d(0x1f5);}_0x17e6c9=this[_0x44e52d(0x10b)]+_0x392108+'/'+_0x57244a[_0x44e52d(0x258)][_0x44e52d(0x262)]()[_0x44e52d(0x1e5)](/ /g,'-')+'.'+_0x5d58ad,_0x34749e=_0x44e52d(0x18d),_0x385ecb=characterDirections[_0x57244a['name']]||_0x44e52d(0x288),_0x2374b0[_0x44e52d(0x185)](_0x5d58ad['toLowerCase']())&&(_0x208e91=_0x44e52d(0x113));}var _0x47ba22;switch(_0x57244a[_0x44e52d(0x2e0)]){case _0x44e52d(0xb4):_0x47ba22=0x64;break;case _0x44e52d(0x1e2):_0x47ba22=0x46;break;case _0x44e52d(0x28a):default:_0x47ba22=0x55;}var _0x399ce0=0x1,_0x31269=0x0;switch(_0x57244a[_0x44e52d(0x226)]){case'Large':_0x399ce0=1.2,_0x31269=_0x57244a[_0x44e52d(0x1ca)]>0x1?-0x2:0x0;break;case _0x44e52d(0x2c9):_0x399ce0=0.8,_0x31269=_0x57244a['tactics']<0x6?0x2:0x7-_0x57244a[_0x44e52d(0x1ca)];break;case _0x44e52d(0x1d2):_0x399ce0=0x1,_0x31269=0x0;break;}var _0xb3bc01=Math['round'](_0x47ba22*_0x399ce0),_0x1f22a7=Math[_0x44e52d(0x23a)](0x1,Math[_0x44e52d(0x1f6)](0x7,_0x57244a['tactics']+_0x31269));return{'name':_0x57244a['name'],'type':_0x57244a[_0x44e52d(0x2e0)],'strength':_0x57244a[_0x44e52d(0x254)],'speed':_0x57244a[_0x44e52d(0x24b)],'tactics':_0x1f22a7,'size':_0x57244a['size'],'powerup':_0x57244a['powerup'],'health':_0xb3bc01,'maxHealth':_0xb3bc01,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x17e6c9,'fallbackUrl':_0x34749e,'orientation':_0x385ecb,'isNFT':_0x2fa342,'mediaType':_0x208e91};}[_0x56fcfe(0x1e1)](_0x1f617f,_0x1271ea,_0x34c9b9=![]){const _0x841676=_0x56fcfe;_0x1f617f[_0x841676(0x130)]===_0x841676(0x288)?(_0x1f617f[_0x841676(0x130)]='Right',_0x1271ea[_0x841676(0x27b)][_0x841676(0x285)]=_0x34c9b9?_0x841676(0x2b3):_0x841676(0x208)):(_0x1f617f[_0x841676(0x130)]=_0x841676(0x288),_0x1271ea[_0x841676(0x27b)][_0x841676(0x285)]=_0x34c9b9?'none':'scaleX(-1)'),log(_0x1f617f['name']+_0x841676(0xef)+_0x1f617f[_0x841676(0x130)]+'!');}['showCharacterSelect'](_0x4f8d6d){const _0x31cff3=_0x56fcfe;console[_0x31cff3(0x11f)]('showCharacterSelect');const _0x2674f1=document[_0x31cff3(0x150)](_0x31cff3(0x234)),_0x4c16db=document[_0x31cff3(0x150)](_0x31cff3(0x2c3));_0x4c16db[_0x31cff3(0x256)]='',_0x2674f1['style']['display']=_0x31cff3(0xfb);if(!this[_0x31cff3(0x270)]||this['playerCharacters'][_0x31cff3(0x23c)]===0x0){console['warn'](_0x31cff3(0x223)),_0x4c16db[_0x31cff3(0x256)]=_0x31cff3(0x204),console[_0x31cff3(0x2aa)](_0x31cff3(0x266));return;}document[_0x31cff3(0x150)](_0x31cff3(0xb2))['onclick']=()=>{showThemeSelect(this);};const _0x4a86bb=document[_0x31cff3(0x18c)]();this['playerCharacters']['forEach'](_0x18a6e8=>{const _0x11f1dc=_0x31cff3,_0x57a49e=document[_0x11f1dc(0x16a)](_0x11f1dc(0x1fd));_0x57a49e['className']=_0x11f1dc(0x2d2),_0x57a49e[_0x11f1dc(0x256)]=_0x18a6e8[_0x11f1dc(0xa8)]===_0x11f1dc(0x113)?'<video\x20src=\x22'+_0x18a6e8[_0x11f1dc(0x2da)]+'\x22\x20autoplay\x20loop\x20muted\x20alt=\x22'+_0x18a6e8[_0x11f1dc(0x258)]+_0x11f1dc(0x2de)+_0x18a6e8[_0x11f1dc(0x257)]+_0x11f1dc(0x1e7)+(_0x11f1dc(0x12c)+_0x18a6e8[_0x11f1dc(0x258)]+_0x11f1dc(0x2a7))+('<p>Type:\x20'+_0x18a6e8[_0x11f1dc(0x2e0)]+_0x11f1dc(0xde))+(_0x11f1dc(0x21e)+_0x18a6e8['maxHealth']+_0x11f1dc(0xde))+(_0x11f1dc(0x153)+_0x18a6e8[_0x11f1dc(0x254)]+_0x11f1dc(0xde))+(_0x11f1dc(0xa2)+_0x18a6e8[_0x11f1dc(0x24b)]+'</p>')+(_0x11f1dc(0xc9)+_0x18a6e8['tactics']+_0x11f1dc(0xde))+(_0x11f1dc(0x2c1)+_0x18a6e8['size']+_0x11f1dc(0xde))+(_0x11f1dc(0x10f)+_0x18a6e8[_0x11f1dc(0x209)]+_0x11f1dc(0xde)):_0x11f1dc(0x2b9)+_0x18a6e8[_0x11f1dc(0x2da)]+'\x22\x20alt=\x22'+_0x18a6e8['name']+_0x11f1dc(0x2de)+_0x18a6e8['fallbackUrl']+_0x11f1dc(0x1bf)+('<p><strong>'+_0x18a6e8[_0x11f1dc(0x258)]+_0x11f1dc(0x2a7))+('<p>Type:\x20'+_0x18a6e8['type']+_0x11f1dc(0xde))+('<p>Health:\x20'+_0x18a6e8[_0x11f1dc(0x1ce)]+_0x11f1dc(0xde))+(_0x11f1dc(0x153)+_0x18a6e8['strength']+'</p>')+('<p>Speed:\x20'+_0x18a6e8['speed']+_0x11f1dc(0xde))+(_0x11f1dc(0xc9)+_0x18a6e8[_0x11f1dc(0x1ca)]+'</p>')+(_0x11f1dc(0x2c1)+_0x18a6e8['size']+_0x11f1dc(0xde))+('<p>Power-Up:\x20'+_0x18a6e8['powerup']+_0x11f1dc(0xde)),_0x57a49e[_0x11f1dc(0xb6)](_0x11f1dc(0x9d),()=>{const _0x331da9=_0x11f1dc;console[_0x331da9(0x199)](_0x331da9(0x297)+_0x18a6e8[_0x331da9(0x258)]),_0x2674f1[_0x331da9(0x27b)][_0x331da9(0x19a)]=_0x331da9(0x208),_0x4f8d6d?(this[_0x331da9(0xd9)]={..._0x18a6e8},console[_0x331da9(0x199)](_0x331da9(0x1ab)+this[_0x331da9(0xd9)][_0x331da9(0x258)]),this[_0x331da9(0x271)]()):this[_0x331da9(0x1a6)](_0x18a6e8);}),_0x4a86bb['appendChild'](_0x57a49e);}),_0x4c16db[_0x31cff3(0x1b3)](_0x4a86bb),console[_0x31cff3(0x199)](_0x31cff3(0xa9)+this[_0x31cff3(0x270)][_0x31cff3(0x23c)]+_0x31cff3(0x1eb)),console[_0x31cff3(0x2aa)](_0x31cff3(0x266));}[_0x56fcfe(0x1a6)](_0x35e3e3){const _0x310cc6=_0x56fcfe,_0x53989e=this[_0x310cc6(0xd9)][_0x310cc6(0x2b4)],_0xf2e330=this[_0x310cc6(0xd9)][_0x310cc6(0x1ce)],_0x399482={..._0x35e3e3},_0x5c3aff=Math['min'](0x1,_0x53989e/_0xf2e330);_0x399482[_0x310cc6(0x2b4)]=Math[_0x310cc6(0x167)](_0x399482[_0x310cc6(0x1ce)]*_0x5c3aff),_0x399482[_0x310cc6(0x2b4)]=Math['max'](0x0,Math[_0x310cc6(0x1f6)](_0x399482[_0x310cc6(0x1ce)],_0x399482[_0x310cc6(0x2b4)])),_0x399482[_0x310cc6(0x191)]=![],_0x399482['boostValue']=0x0,_0x399482[_0x310cc6(0x1ed)]=![],this[_0x310cc6(0xd9)]=_0x399482,this[_0x310cc6(0x1b2)](),this[_0x310cc6(0x135)](this[_0x310cc6(0xd9)]),log(this['player1']['name']+_0x310cc6(0x273)+this[_0x310cc6(0xd9)][_0x310cc6(0x2b4)]+'/'+this[_0x310cc6(0xd9)]['maxHealth']+_0x310cc6(0x22a)),this[_0x310cc6(0x283)]=this[_0x310cc6(0xd9)][_0x310cc6(0x24b)]>this[_0x310cc6(0xf8)]['speed']?this['player1']:this[_0x310cc6(0xf8)][_0x310cc6(0x24b)]>this[_0x310cc6(0xd9)]['speed']?this[_0x310cc6(0xf8)]:this[_0x310cc6(0xd9)]['strength']>=this['player2'][_0x310cc6(0x254)]?this[_0x310cc6(0xd9)]:this[_0x310cc6(0xf8)],turnIndicator[_0x310cc6(0xd4)]=_0x310cc6(0x1c3)+this[_0x310cc6(0x211)]+_0x310cc6(0x14d)+(this[_0x310cc6(0x283)]===this[_0x310cc6(0xd9)]?'Player':'Opponent')+_0x310cc6(0x1ba),this[_0x310cc6(0x283)]===this['player2']&&this['gameState']!==_0x310cc6(0x101)&&setTimeout(()=>this[_0x310cc6(0xdb)](),0x3e8);}[_0x56fcfe(0xd1)](_0x4fa9f8,_0x1af51c){const _0x2c98ee=_0x56fcfe;return console[_0x2c98ee(0x199)]('showProgressPopup:\x20Displaying\x20popup\x20for\x20level='+_0x4fa9f8+_0x2c98ee(0xf2)+_0x1af51c),new Promise(_0x118a0a=>{const _0x36577d=_0x2c98ee,_0x5a9073=document[_0x36577d(0x16a)](_0x36577d(0x1fd));_0x5a9073['id']='progress-modal',_0x5a9073['className']=_0x36577d(0xc7);const _0x5f2f7b=document[_0x36577d(0x16a)](_0x36577d(0x1fd));_0x5f2f7b[_0x36577d(0xd5)]=_0x36577d(0x131);const _0x3bd300=document['createElement']('p');_0x3bd300['id']=_0x36577d(0x25e),_0x3bd300[_0x36577d(0xd4)]=_0x36577d(0x26d)+_0x4fa9f8+_0x36577d(0xf1)+_0x1af51c+'?',_0x5f2f7b[_0x36577d(0x1b3)](_0x3bd300);const _0x183ff3=document[_0x36577d(0x16a)](_0x36577d(0x1fd));_0x183ff3[_0x36577d(0xd5)]=_0x36577d(0x232);const _0x43ed94=document[_0x36577d(0x16a)](_0x36577d(0x149));_0x43ed94['id']='progress-resume',_0x43ed94['textContent']='Resume',_0x183ff3[_0x36577d(0x1b3)](_0x43ed94);const _0x23b1a9=document[_0x36577d(0x16a)](_0x36577d(0x149));_0x23b1a9['id']=_0x36577d(0x28e),_0x23b1a9[_0x36577d(0xd4)]=_0x36577d(0x195),_0x183ff3[_0x36577d(0x1b3)](_0x23b1a9),_0x5f2f7b[_0x36577d(0x1b3)](_0x183ff3),_0x5a9073[_0x36577d(0x1b3)](_0x5f2f7b),document[_0x36577d(0xab)]['appendChild'](_0x5a9073),_0x5a9073[_0x36577d(0x27b)][_0x36577d(0x19a)]='flex';const _0x4cb4d7=()=>{const _0x446fe3=_0x36577d;console[_0x446fe3(0x199)](_0x446fe3(0x156)),_0x5a9073[_0x446fe3(0x27b)][_0x446fe3(0x19a)]=_0x446fe3(0x208),document[_0x446fe3(0xab)][_0x446fe3(0x2b6)](_0x5a9073),_0x43ed94[_0x446fe3(0x202)](_0x446fe3(0x9d),_0x4cb4d7),_0x23b1a9[_0x446fe3(0x202)](_0x446fe3(0x9d),_0xea2dc2),_0x118a0a(!![]);},_0xea2dc2=()=>{const _0x58606e=_0x36577d;console[_0x58606e(0x199)](_0x58606e(0x1f1)),_0x5a9073['style'][_0x58606e(0x19a)]=_0x58606e(0x208),document['body'][_0x58606e(0x2b6)](_0x5a9073),_0x43ed94[_0x58606e(0x202)](_0x58606e(0x9d),_0x4cb4d7),_0x23b1a9['removeEventListener'](_0x58606e(0x9d),_0xea2dc2),_0x118a0a(![]);};_0x43ed94[_0x36577d(0xb6)](_0x36577d(0x9d),_0x4cb4d7),_0x23b1a9['addEventListener']('click',_0xea2dc2);});}[_0x56fcfe(0x271)](){const _0x274cd8=_0x56fcfe;var _0x54ae98=this;console[_0x274cd8(0x199)](_0x274cd8(0x20f)+this[_0x274cd8(0x211)]);var _0x272847=document[_0x274cd8(0x1e8)]('.game-container'),_0xd20233=document[_0x274cd8(0x150)]('game-board');_0x272847['style'][_0x274cd8(0x19a)]=_0x274cd8(0xfb),_0xd20233[_0x274cd8(0x27b)]['visibility']=_0x274cd8(0x26b),this['setBackground'](),this[_0x274cd8(0x106)][_0x274cd8(0x248)][_0x274cd8(0xa4)](),log(_0x274cd8(0x19e)+this[_0x274cd8(0x211)]+_0x274cd8(0xdf)),this[_0x274cd8(0xf8)]=this[_0x274cd8(0x145)](opponentsConfig[this['currentLevel']-0x1]),console['log'](_0x274cd8(0x1b6)+this['currentLevel']+':\x20'+this[_0x274cd8(0xf8)][_0x274cd8(0x258)]+_0x274cd8(0x138)+(this[_0x274cd8(0x211)]-0x1)+'])'),this['player1'][_0x274cd8(0x2b4)]=this[_0x274cd8(0xd9)][_0x274cd8(0x1ce)],this[_0x274cd8(0x283)]=this[_0x274cd8(0xd9)][_0x274cd8(0x24b)]>this[_0x274cd8(0xf8)][_0x274cd8(0x24b)]?this[_0x274cd8(0xd9)]:this[_0x274cd8(0xf8)][_0x274cd8(0x24b)]>this[_0x274cd8(0xd9)][_0x274cd8(0x24b)]?this[_0x274cd8(0xf8)]:this['player1'][_0x274cd8(0x254)]>=this[_0x274cd8(0xf8)]['strength']?this['player1']:this['player2'],this[_0x274cd8(0x27e)]=_0x274cd8(0x21a),this[_0x274cd8(0x101)]=![],this[_0x274cd8(0xbb)]=[];const _0x492249=document[_0x274cd8(0x150)](_0x274cd8(0xa7)),_0x4180b0=document[_0x274cd8(0x150)](_0x274cd8(0xba));if(_0x492249)_0x492249[_0x274cd8(0x20d)][_0x274cd8(0x27d)](_0x274cd8(0x27a),_0x274cd8(0xbf));if(_0x4180b0)_0x4180b0[_0x274cd8(0x20d)][_0x274cd8(0x27d)](_0x274cd8(0x27a),_0x274cd8(0xbf));this[_0x274cd8(0x1b2)](),this[_0x274cd8(0x129)]();if(_0x492249)_0x492249[_0x274cd8(0x27b)][_0x274cd8(0x285)]=this['player1'][_0x274cd8(0x130)]===_0x274cd8(0x288)?_0x274cd8(0x2b3):_0x274cd8(0x208);if(_0x4180b0)_0x4180b0[_0x274cd8(0x27b)]['transform']=this[_0x274cd8(0xf8)]['orientation']===_0x274cd8(0xec)?_0x274cd8(0x2b3):'none';this[_0x274cd8(0x135)](this[_0x274cd8(0xd9)]),this['updateHealth'](this[_0x274cd8(0xf8)]),battleLog[_0x274cd8(0x256)]='',gameOver['textContent']='',this[_0x274cd8(0xd9)]['size']!=='Medium'&&log(this[_0x274cd8(0xd9)][_0x274cd8(0x258)]+_0x274cd8(0x186)+this[_0x274cd8(0xd9)]['size']+'\x20size\x20'+(this['player1'][_0x274cd8(0x226)]===_0x274cd8(0x117)?_0x274cd8(0x295)+this[_0x274cd8(0xd9)][_0x274cd8(0x1ce)]+_0x274cd8(0x15d)+this[_0x274cd8(0xd9)][_0x274cd8(0x1ca)]:_0x274cd8(0x1f8)+this['player1']['maxHealth']+'\x20but\x20sharpens\x20tactics\x20to\x20'+this['player1'][_0x274cd8(0x1ca)])+'!'),this['player2'][_0x274cd8(0x226)]!==_0x274cd8(0x1d2)&&log(this['player2'][_0x274cd8(0x258)]+_0x274cd8(0x186)+this[_0x274cd8(0xf8)]['size']+_0x274cd8(0x245)+(this[_0x274cd8(0xf8)][_0x274cd8(0x226)]===_0x274cd8(0x117)?'boosts\x20health\x20to\x20'+this[_0x274cd8(0xf8)]['maxHealth']+_0x274cd8(0x15d)+this[_0x274cd8(0xf8)][_0x274cd8(0x1ca)]:_0x274cd8(0x1f8)+this['player2']['maxHealth']+_0x274cd8(0x29d)+this[_0x274cd8(0xf8)][_0x274cd8(0x1ca)])+'!'),log(this[_0x274cd8(0xd9)][_0x274cd8(0x258)]+_0x274cd8(0x26f)+this[_0x274cd8(0xd9)]['health']+'/'+this[_0x274cd8(0xd9)][_0x274cd8(0x1ce)]+_0x274cd8(0x22a)),log(this[_0x274cd8(0x283)][_0x274cd8(0x258)]+_0x274cd8(0x269)),this[_0x274cd8(0xf4)](),this[_0x274cd8(0x27e)]=this[_0x274cd8(0x283)]===this[_0x274cd8(0xd9)]?'playerTurn':'aiTurn',turnIndicator[_0x274cd8(0xd4)]='Level\x20'+this[_0x274cd8(0x211)]+_0x274cd8(0x14d)+(this[_0x274cd8(0x283)]===this[_0x274cd8(0xd9)]?'Player':_0x274cd8(0x2a5))+'\x27s\x20Turn',this['playerCharacters'][_0x274cd8(0x23c)]>0x1&&(document[_0x274cd8(0x150)](_0x274cd8(0x11d))[_0x274cd8(0x27b)][_0x274cd8(0x19a)]=_0x274cd8(0xc0)),this[_0x274cd8(0x283)]===this[_0x274cd8(0xf8)]&&setTimeout(function(){const _0x1bae04=_0x274cd8;_0x54ae98[_0x1bae04(0xdb)]();},0x3e8);}[_0x56fcfe(0x1b2)](){const _0x22fa9d=_0x56fcfe;p1Name[_0x22fa9d(0xd4)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x213)]||this[_0x22fa9d(0xa0)]===_0x22fa9d(0x127)?this[_0x22fa9d(0xd9)][_0x22fa9d(0x258)]:_0x22fa9d(0x255),p1Type[_0x22fa9d(0xd4)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x2e0)],p1Strength['textContent']=this[_0x22fa9d(0xd9)][_0x22fa9d(0x254)],p1Speed[_0x22fa9d(0xd4)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x24b)],p1Tactics['textContent']=this[_0x22fa9d(0xd9)]['tactics'],p1Size[_0x22fa9d(0xd4)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x226)],p1Powerup['textContent']=this[_0x22fa9d(0xd9)][_0x22fa9d(0x209)];const _0x27d311=document['getElementById'](_0x22fa9d(0xa7)),_0x5815c1=_0x27d311['parentNode'];if(this[_0x22fa9d(0xd9)][_0x22fa9d(0xa8)]==='video'){if(_0x27d311[_0x22fa9d(0x14e)]!==_0x22fa9d(0x164)){const _0x21ff05=document[_0x22fa9d(0x16a)](_0x22fa9d(0x113));_0x21ff05['id']=_0x22fa9d(0xa7),_0x21ff05[_0x22fa9d(0x1bc)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x2da)],_0x21ff05[_0x22fa9d(0x188)]=!![],_0x21ff05['loop']=!![],_0x21ff05[_0x22fa9d(0x2cd)]=!![],_0x21ff05['alt']=this[_0x22fa9d(0xd9)][_0x22fa9d(0x258)],_0x21ff05[_0x22fa9d(0x20c)]=()=>{const _0x4ab718=_0x22fa9d;_0x21ff05['src']=this[_0x4ab718(0xd9)][_0x4ab718(0x257)];},_0x5815c1[_0x22fa9d(0x1a2)](_0x21ff05,_0x27d311);}else _0x27d311[_0x22fa9d(0x1bc)]=this[_0x22fa9d(0xd9)]['imageUrl'],_0x27d311['onerror']=()=>{const _0x10d05b=_0x22fa9d;_0x27d311[_0x10d05b(0x1bc)]=this[_0x10d05b(0xd9)]['fallbackUrl'];};}else{if(_0x27d311[_0x22fa9d(0x14e)]!==_0x22fa9d(0x142)){const _0x383657=document[_0x22fa9d(0x16a)](_0x22fa9d(0x2db));_0x383657['id']=_0x22fa9d(0xa7),_0x383657[_0x22fa9d(0x1bc)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x2da)],_0x383657['alt']=this['player1'][_0x22fa9d(0x258)],_0x383657['onerror']=()=>{const _0x3a243f=_0x22fa9d;_0x383657[_0x3a243f(0x1bc)]=this[_0x3a243f(0xd9)][_0x3a243f(0x257)];},_0x5815c1[_0x22fa9d(0x1a2)](_0x383657,_0x27d311);}else _0x27d311[_0x22fa9d(0x1bc)]=this[_0x22fa9d(0xd9)][_0x22fa9d(0x2da)],_0x27d311[_0x22fa9d(0x20c)]=()=>{const _0x42bbb9=_0x22fa9d;_0x27d311['src']=this[_0x42bbb9(0xd9)][_0x42bbb9(0x257)];};}const _0x5305f6=document[_0x22fa9d(0x150)](_0x22fa9d(0xa7));_0x5305f6[_0x22fa9d(0x27b)]['transform']=this[_0x22fa9d(0xd9)][_0x22fa9d(0x130)]===_0x22fa9d(0x288)?_0x22fa9d(0x2b3):_0x22fa9d(0x208),_0x5305f6[_0x22fa9d(0x14e)]==='IMG'?_0x5305f6[_0x22fa9d(0x183)]=function(){const _0x3bae1d=_0x22fa9d;_0x5305f6[_0x3bae1d(0x27b)][_0x3bae1d(0x19a)]=_0x3bae1d(0xfb);}:_0x5305f6[_0x22fa9d(0x27b)][_0x22fa9d(0x19a)]=_0x22fa9d(0xfb),p1Hp[_0x22fa9d(0xd4)]=this['player1']['health']+'/'+this['player1'][_0x22fa9d(0x1ce)],_0x5305f6['onclick']=()=>{const _0x1d0862=_0x22fa9d;console[_0x1d0862(0x199)](_0x1d0862(0xe7)),this[_0x1d0862(0x266)](![]);};}[_0x56fcfe(0x129)](){const _0x112925=_0x56fcfe;p2Name[_0x112925(0xd4)]=this[_0x112925(0xa0)]==='monstrocity'?this[_0x112925(0xf8)][_0x112925(0x258)]:_0x112925(0xa3),p2Type['textContent']=this[_0x112925(0xf8)]['type'],p2Strength[_0x112925(0xd4)]=this[_0x112925(0xf8)][_0x112925(0x254)],p2Speed[_0x112925(0xd4)]=this[_0x112925(0xf8)][_0x112925(0x24b)],p2Tactics[_0x112925(0xd4)]=this['player2'][_0x112925(0x1ca)],p2Size[_0x112925(0xd4)]=this[_0x112925(0xf8)][_0x112925(0x226)],p2Powerup[_0x112925(0xd4)]=this[_0x112925(0xf8)]['powerup'];const _0x3df281=document[_0x112925(0x150)](_0x112925(0xba)),_0x43ac49=_0x3df281['parentNode'];if(this[_0x112925(0xf8)][_0x112925(0xa8)]==='video'){if(_0x3df281[_0x112925(0x14e)]!==_0x112925(0x164)){const _0x327624=document['createElement'](_0x112925(0x113));_0x327624['id']=_0x112925(0xba),_0x327624[_0x112925(0x1bc)]=this[_0x112925(0xf8)][_0x112925(0x2da)],_0x327624['autoplay']=!![],_0x327624['loop']=!![],_0x327624[_0x112925(0x2cd)]=!![],_0x327624[_0x112925(0x277)]=this[_0x112925(0xf8)][_0x112925(0x258)],_0x43ac49['replaceChild'](_0x327624,_0x3df281);}else _0x3df281[_0x112925(0x1bc)]=this[_0x112925(0xf8)][_0x112925(0x2da)];}else{if(_0x3df281[_0x112925(0x14e)]!==_0x112925(0x142)){const _0xe36833=document[_0x112925(0x16a)](_0x112925(0x2db));_0xe36833['id']=_0x112925(0xba),_0xe36833[_0x112925(0x1bc)]=this[_0x112925(0xf8)][_0x112925(0x2da)],_0xe36833[_0x112925(0x277)]=this[_0x112925(0xf8)][_0x112925(0x258)],_0x43ac49[_0x112925(0x1a2)](_0xe36833,_0x3df281);}else _0x3df281[_0x112925(0x1bc)]=this[_0x112925(0xf8)]['imageUrl'];}const _0x48ba41=document[_0x112925(0x150)](_0x112925(0xba));_0x48ba41[_0x112925(0x27b)]['transform']=this[_0x112925(0xf8)][_0x112925(0x130)]===_0x112925(0xec)?_0x112925(0x2b3):_0x112925(0x208),_0x48ba41['tagName']===_0x112925(0x142)?_0x48ba41[_0x112925(0x183)]=function(){const _0x8745b8=_0x112925;_0x48ba41[_0x8745b8(0x27b)][_0x8745b8(0x19a)]='block';}:_0x48ba41[_0x112925(0x27b)][_0x112925(0x19a)]=_0x112925(0xfb),p2Hp[_0x112925(0xd4)]=this[_0x112925(0xf8)]['health']+'/'+this[_0x112925(0xf8)][_0x112925(0x1ce)],_0x48ba41[_0x112925(0x20d)]['remove'](_0x112925(0x27a),_0x112925(0xbf));}[_0x56fcfe(0xf4)](){const _0x2d20db=_0x56fcfe,_0x36e182=this[_0x2d20db(0x1cd)]();if(_0x36e182)this[_0x2d20db(0x1ac)]=_0x36e182,console[_0x2d20db(0x199)]('initBoard:\x20Loaded\x20board\x20from\x20localStorage\x20for\x20level',this[_0x2d20db(0x211)]);else{this[_0x2d20db(0x1ac)]=[];for(let _0x5e6104=0x0;_0x5e6104<this['height'];_0x5e6104++){this[_0x2d20db(0x1ac)][_0x5e6104]=[];for(let _0x236529=0x0;_0x236529<this['width'];_0x236529++){let _0x36c598;do{_0x36c598=this[_0x2d20db(0x25d)]();}while(_0x236529>=0x2&&this['board'][_0x5e6104][_0x236529-0x1]?.['type']===_0x36c598[_0x2d20db(0x2e0)]&&this['board'][_0x5e6104][_0x236529-0x2]?.['type']===_0x36c598['type']||_0x5e6104>=0x2&&this[_0x2d20db(0x1ac)][_0x5e6104-0x1]?.[_0x236529]?.[_0x2d20db(0x2e0)]===_0x36c598[_0x2d20db(0x2e0)]&&this[_0x2d20db(0x1ac)][_0x5e6104-0x2]?.[_0x236529]?.['type']===_0x36c598['type']);this[_0x2d20db(0x1ac)][_0x5e6104][_0x236529]=_0x36c598;}}this['saveBoard'](),console[_0x2d20db(0x199)](_0x2d20db(0x272),this[_0x2d20db(0x211)]);}this[_0x2d20db(0x159)]();}[_0x56fcfe(0x25d)](){const _0xc808db=_0x56fcfe;return{'type':randomChoice(this[_0xc808db(0x192)]),'element':null};}[_0x56fcfe(0x159)](){const _0x1acd0f=_0x56fcfe;this['updateTileSizeWithGap']();const _0x2b8c25=document[_0x1acd0f(0x150)](_0x1acd0f(0x2df));_0x2b8c25['innerHTML']='';if(!this[_0x1acd0f(0x1ac)]||!Array['isArray'](this[_0x1acd0f(0x1ac)])||this[_0x1acd0f(0x1ac)][_0x1acd0f(0x23c)]!==this[_0x1acd0f(0x1c8)]){console[_0x1acd0f(0xad)](_0x1acd0f(0x171));return;}for(let _0x1e4524=0x0;_0x1e4524<this[_0x1acd0f(0x1c8)];_0x1e4524++){if(!Array[_0x1acd0f(0x144)](this['board'][_0x1e4524])){console[_0x1acd0f(0xad)](_0x1acd0f(0x1b0)+_0x1e4524+_0x1acd0f(0x124));continue;}for(let _0x3a76c2=0x0;_0x3a76c2<this['width'];_0x3a76c2++){const _0x59e412=this[_0x1acd0f(0x1ac)][_0x1e4524][_0x3a76c2];if(!_0x59e412||_0x59e412[_0x1acd0f(0x2e0)]===null)continue;const _0xe4a6ae=document['createElement'](_0x1acd0f(0x1fd));_0xe4a6ae[_0x1acd0f(0xd5)]=_0x1acd0f(0x161)+_0x59e412[_0x1acd0f(0x2e0)];if(this[_0x1acd0f(0x101)])_0xe4a6ae[_0x1acd0f(0x20d)][_0x1acd0f(0x279)](_0x1acd0f(0x2c4));const _0x550585=document['createElement']('img');_0x550585[_0x1acd0f(0x1bc)]=_0x1acd0f(0x1e6)+_0x59e412['type']+_0x1acd0f(0x243),_0x550585[_0x1acd0f(0x277)]=_0x59e412[_0x1acd0f(0x2e0)],_0xe4a6ae[_0x1acd0f(0x1b3)](_0x550585),_0xe4a6ae[_0x1acd0f(0x235)]['x']=_0x3a76c2,_0xe4a6ae[_0x1acd0f(0x235)]['y']=_0x1e4524,_0x2b8c25['appendChild'](_0xe4a6ae),_0x59e412[_0x1acd0f(0x1c1)]=_0xe4a6ae,(!this[_0x1acd0f(0xdd)]||this[_0x1acd0f(0x24f)]&&(this[_0x1acd0f(0x24f)]['x']!==_0x3a76c2||this[_0x1acd0f(0x24f)]['y']!==_0x1e4524))&&(_0xe4a6ae[_0x1acd0f(0x27b)]['transform']=_0x1acd0f(0x112)),this[_0x1acd0f(0x22b)]?_0xe4a6ae[_0x1acd0f(0xb6)](_0x1acd0f(0x2ca),_0x5ba0f0=>this[_0x1acd0f(0x2c5)](_0x5ba0f0)):_0xe4a6ae[_0x1acd0f(0xb6)](_0x1acd0f(0x286),_0x4b1447=>this[_0x1acd0f(0x97)](_0x4b1447));}}document[_0x1acd0f(0x150)](_0x1acd0f(0x158))[_0x1acd0f(0x27b)][_0x1acd0f(0x19a)]=this[_0x1acd0f(0x101)]?_0x1acd0f(0xfb):_0x1acd0f(0x208),console['log']('renderBoard:\x20Board\x20rendered\x20successfully');}[_0x56fcfe(0x1b4)](){const _0x46366f=_0x56fcfe,_0x46bcae=document[_0x46366f(0x150)](_0x46366f(0x2df));this['isTouchDevice']?(_0x46bcae[_0x46366f(0xb6)](_0x46366f(0x2ca),_0x5daafd=>this['handleTouchStart'](_0x5daafd)),_0x46bcae[_0x46366f(0xb6)](_0x46366f(0x154),_0x47fe82=>this[_0x46366f(0x29f)](_0x47fe82)),_0x46bcae[_0x46366f(0xb6)](_0x46366f(0x180),_0xdd92e3=>this[_0x46366f(0x21f)](_0xdd92e3))):(_0x46bcae[_0x46366f(0xb6)](_0x46366f(0x286),_0x80c20a=>this[_0x46366f(0x97)](_0x80c20a)),_0x46bcae[_0x46366f(0xb6)](_0x46366f(0x11e),_0x2cb270=>this['handleMouseMove'](_0x2cb270)),_0x46bcae['addEventListener'](_0x46366f(0xf6),_0x1b6db7=>this['handleMouseUp'](_0x1b6db7)));document[_0x46366f(0x150)](_0x46366f(0xa1))[_0x46366f(0xb6)](_0x46366f(0x9d),()=>this['handleGameOverButton']()),document[_0x46366f(0x150)]('restart')[_0x46366f(0xb6)]('click',()=>{const _0x570cdf=_0x46366f;this[_0x570cdf(0x271)]();});const _0x2ccda7=document[_0x46366f(0x150)]('change-character'),_0x318bbf=document[_0x46366f(0x150)](_0x46366f(0xa7)),_0x294276=document[_0x46366f(0x150)](_0x46366f(0xba));_0x2ccda7[_0x46366f(0xb6)](_0x46366f(0x9d),()=>{const _0xb85a95=_0x46366f;console[_0xb85a95(0x199)]('addEventListeners:\x20Switch\x20Monster\x20button\x20clicked'),this['showCharacterSelect'](![]);}),_0x318bbf['addEventListener'](_0x46366f(0x9d),()=>{const _0x1b9463=_0x46366f;console['log'](_0x1b9463(0x1c7)),this[_0x1b9463(0x266)](![]);}),document[_0x46366f(0x150)](_0x46366f(0x12f))[_0x46366f(0xb6)](_0x46366f(0x9d),()=>this[_0x46366f(0x1e1)](this[_0x46366f(0xd9)],document[_0x46366f(0x150)](_0x46366f(0xa7)),![])),document[_0x46366f(0x150)]('flip-p2')[_0x46366f(0xb6)](_0x46366f(0x9d),()=>this[_0x46366f(0x1e1)](this[_0x46366f(0xf8)],document[_0x46366f(0x150)](_0x46366f(0xba)),!![]));}['handleGameOverButton'](){const _0x1ab41a=_0x56fcfe;console[_0x1ab41a(0x199)](_0x1ab41a(0x103)+this['currentLevel']+_0x1ab41a(0xd2)+this[_0x1ab41a(0xf8)][_0x1ab41a(0x2b4)]),this[_0x1ab41a(0xf8)][_0x1ab41a(0x2b4)]<=0x0&&this[_0x1ab41a(0x211)]>opponentsConfig[_0x1ab41a(0x23c)]&&(this[_0x1ab41a(0x211)]=0x1,console[_0x1ab41a(0x199)](_0x1ab41a(0x136)+this[_0x1ab41a(0x211)])),this['initGame'](),console[_0x1ab41a(0x199)]('handleGameOverButton\x20completed:\x20currentLevel='+this[_0x1ab41a(0x211)]);}[_0x56fcfe(0x97)](_0x4a89e5){const _0xfcc3ba=_0x56fcfe;if(this['gameOver']||this['gameState']!=='playerTurn'||this[_0xfcc3ba(0x283)]!==this[_0xfcc3ba(0xd9)])return;_0x4a89e5[_0xfcc3ba(0x230)]();const _0x4b9830=this[_0xfcc3ba(0x155)](_0x4a89e5);if(!_0x4b9830||!_0x4b9830[_0xfcc3ba(0x1c1)])return;this[_0xfcc3ba(0xdd)]=!![],this[_0xfcc3ba(0x24f)]={'x':_0x4b9830['x'],'y':_0x4b9830['y']},_0x4b9830[_0xfcc3ba(0x1c1)]['classList'][_0xfcc3ba(0x279)]('selected');const _0x38ac58=document[_0xfcc3ba(0x150)](_0xfcc3ba(0x2df))[_0xfcc3ba(0x275)]();this[_0xfcc3ba(0x2b5)]=_0x4a89e5[_0xfcc3ba(0x14b)]-(_0x38ac58[_0xfcc3ba(0x1ee)]+this[_0xfcc3ba(0x24f)]['x']*this[_0xfcc3ba(0x2be)]),this[_0xfcc3ba(0x121)]=_0x4a89e5[_0xfcc3ba(0x143)]-(_0x38ac58[_0xfcc3ba(0x1f7)]+this[_0xfcc3ba(0x24f)]['y']*this[_0xfcc3ba(0x2be)]);}[_0x56fcfe(0x28c)](_0x2c86f4){const _0x141f27=_0x56fcfe;if(!this[_0x141f27(0xdd)]||!this[_0x141f27(0x24f)]||this[_0x141f27(0x101)]||this['gameState']!==_0x141f27(0x13f))return;_0x2c86f4[_0x141f27(0x230)]();const _0x44ef94=document[_0x141f27(0x150)](_0x141f27(0x2df))[_0x141f27(0x275)](),_0x5b21dc=_0x2c86f4['clientX']-_0x44ef94[_0x141f27(0x1ee)]-this[_0x141f27(0x2b5)],_0x5e7238=_0x2c86f4[_0x141f27(0x143)]-_0x44ef94['top']-this['offsetY'],_0x45d37d=this['board'][this[_0x141f27(0x24f)]['y']][this['selectedTile']['x']][_0x141f27(0x1c1)];_0x45d37d[_0x141f27(0x27b)][_0x141f27(0x16e)]='';if(!this[_0x141f27(0x9f)]){const _0x455642=Math[_0x141f27(0x290)](_0x5b21dc-this[_0x141f27(0x24f)]['x']*this['tileSizeWithGap']),_0x51a1bd=Math[_0x141f27(0x290)](_0x5e7238-this[_0x141f27(0x24f)]['y']*this[_0x141f27(0x2be)]);if(_0x455642>_0x51a1bd&&_0x455642>0x5)this[_0x141f27(0x9f)]='row';else{if(_0x51a1bd>_0x455642&&_0x51a1bd>0x5)this[_0x141f27(0x9f)]=_0x141f27(0x19d);}}if(!this[_0x141f27(0x9f)])return;if(this[_0x141f27(0x9f)]===_0x141f27(0x1db)){const _0x22d222=Math[_0x141f27(0x23a)](0x0,Math[_0x141f27(0x1f6)]((this['width']-0x1)*this[_0x141f27(0x2be)],_0x5b21dc));_0x45d37d[_0x141f27(0x27b)][_0x141f27(0x285)]=_0x141f27(0x13e)+(_0x22d222-this[_0x141f27(0x24f)]['x']*this[_0x141f27(0x2be)])+_0x141f27(0xeb),this[_0x141f27(0x165)]={'x':Math[_0x141f27(0x167)](_0x22d222/this['tileSizeWithGap']),'y':this[_0x141f27(0x24f)]['y']};}else{if(this[_0x141f27(0x9f)]===_0x141f27(0x19d)){const _0x520ef3=Math[_0x141f27(0x23a)](0x0,Math[_0x141f27(0x1f6)]((this[_0x141f27(0x1c8)]-0x1)*this[_0x141f27(0x2be)],_0x5e7238));_0x45d37d[_0x141f27(0x27b)][_0x141f27(0x285)]=_0x141f27(0x1b8)+(_0x520ef3-this[_0x141f27(0x24f)]['y']*this[_0x141f27(0x2be)])+'px)\x20scale(1.05)',this[_0x141f27(0x165)]={'x':this[_0x141f27(0x24f)]['x'],'y':Math[_0x141f27(0x167)](_0x520ef3/this[_0x141f27(0x2be)])};}}}[_0x56fcfe(0x18e)](_0x40160d){const _0x12396b=_0x56fcfe;if(!this[_0x12396b(0xdd)]||!this[_0x12396b(0x24f)]||!this[_0x12396b(0x165)]||this[_0x12396b(0x101)]||this[_0x12396b(0x27e)]!==_0x12396b(0x13f)){if(this[_0x12396b(0x24f)]){const _0x7f91a8=this[_0x12396b(0x1ac)][this[_0x12396b(0x24f)]['y']][this[_0x12396b(0x24f)]['x']];if(_0x7f91a8[_0x12396b(0x1c1)])_0x7f91a8[_0x12396b(0x1c1)]['classList'][_0x12396b(0x27d)](_0x12396b(0x2d5));}this[_0x12396b(0xdd)]=![],this[_0x12396b(0x24f)]=null,this['targetTile']=null,this['dragDirection']=null,this[_0x12396b(0x159)]();return;}const _0x3df55b=this[_0x12396b(0x1ac)][this[_0x12396b(0x24f)]['y']][this[_0x12396b(0x24f)]['x']];if(_0x3df55b['element'])_0x3df55b[_0x12396b(0x1c1)]['classList'][_0x12396b(0x27d)]('selected');this[_0x12396b(0x11c)](this[_0x12396b(0x24f)]['x'],this[_0x12396b(0x24f)]['y'],this[_0x12396b(0x165)]['x'],this[_0x12396b(0x165)]['y']),this[_0x12396b(0xdd)]=![],this[_0x12396b(0x24f)]=null,this[_0x12396b(0x165)]=null,this['dragDirection']=null;}[_0x56fcfe(0x2c5)](_0x34fb9f){const _0x50ac72=_0x56fcfe;if(this['gameOver']||this['gameState']!==_0x50ac72(0x13f)||this['currentTurn']!==this[_0x50ac72(0xd9)])return;_0x34fb9f['preventDefault']();const _0x22cfc3=this['getTileFromEvent'](_0x34fb9f[_0x50ac72(0x20e)][0x0]);if(!_0x22cfc3||!_0x22cfc3[_0x50ac72(0x1c1)])return;this[_0x50ac72(0xdd)]=!![],this[_0x50ac72(0x24f)]={'x':_0x22cfc3['x'],'y':_0x22cfc3['y']},_0x22cfc3[_0x50ac72(0x1c1)][_0x50ac72(0x20d)][_0x50ac72(0x279)](_0x50ac72(0x2d5));const _0xaa7ecc=document[_0x50ac72(0x150)](_0x50ac72(0x2df))[_0x50ac72(0x275)]();this[_0x50ac72(0x2b5)]=_0x34fb9f[_0x50ac72(0x20e)][0x0][_0x50ac72(0x14b)]-(_0xaa7ecc[_0x50ac72(0x1ee)]+this[_0x50ac72(0x24f)]['x']*this['tileSizeWithGap']),this['offsetY']=_0x34fb9f[_0x50ac72(0x20e)][0x0][_0x50ac72(0x143)]-(_0xaa7ecc[_0x50ac72(0x1f7)]+this[_0x50ac72(0x24f)]['y']*this[_0x50ac72(0x2be)]);}[_0x56fcfe(0x29f)](_0x22da39){const _0x41c635=_0x56fcfe;if(!this['isDragging']||!this['selectedTile']||this['gameOver']||this[_0x41c635(0x27e)]!=='playerTurn')return;_0x22da39[_0x41c635(0x230)]();const _0x16646f=document[_0x41c635(0x150)]('game-board')[_0x41c635(0x275)](),_0x4bfda0=_0x22da39[_0x41c635(0x20e)][0x0]['clientX']-_0x16646f[_0x41c635(0x1ee)]-this[_0x41c635(0x2b5)],_0x4aef9d=_0x22da39[_0x41c635(0x20e)][0x0][_0x41c635(0x143)]-_0x16646f[_0x41c635(0x1f7)]-this[_0x41c635(0x121)],_0x41518f=this['board'][this[_0x41c635(0x24f)]['y']][this['selectedTile']['x']][_0x41c635(0x1c1)];requestAnimationFrame(()=>{const _0x5cf691=_0x41c635;if(!this['dragDirection']){const _0x813914=Math[_0x5cf691(0x290)](_0x4bfda0-this[_0x5cf691(0x24f)]['x']*this[_0x5cf691(0x2be)]),_0x341e77=Math[_0x5cf691(0x290)](_0x4aef9d-this[_0x5cf691(0x24f)]['y']*this[_0x5cf691(0x2be)]);if(_0x813914>_0x341e77&&_0x813914>0x7)this[_0x5cf691(0x9f)]=_0x5cf691(0x1db);else{if(_0x341e77>_0x813914&&_0x341e77>0x7)this[_0x5cf691(0x9f)]=_0x5cf691(0x19d);}}_0x41518f['style'][_0x5cf691(0x16e)]='';if(this[_0x5cf691(0x9f)]===_0x5cf691(0x1db)){const _0x186af4=Math['max'](0x0,Math[_0x5cf691(0x1f6)]((this[_0x5cf691(0x19b)]-0x1)*this[_0x5cf691(0x2be)],_0x4bfda0));_0x41518f[_0x5cf691(0x27b)]['transform']='translate('+(_0x186af4-this['selectedTile']['x']*this[_0x5cf691(0x2be)])+_0x5cf691(0xeb),this[_0x5cf691(0x165)]={'x':Math[_0x5cf691(0x167)](_0x186af4/this[_0x5cf691(0x2be)]),'y':this[_0x5cf691(0x24f)]['y']};}else{if(this['dragDirection']===_0x5cf691(0x19d)){const _0x46fbaa=Math[_0x5cf691(0x23a)](0x0,Math[_0x5cf691(0x1f6)]((this[_0x5cf691(0x1c8)]-0x1)*this[_0x5cf691(0x2be)],_0x4aef9d));_0x41518f[_0x5cf691(0x27b)]['transform']=_0x5cf691(0x1b8)+(_0x46fbaa-this['selectedTile']['y']*this[_0x5cf691(0x2be)])+_0x5cf691(0x1c2),this[_0x5cf691(0x165)]={'x':this[_0x5cf691(0x24f)]['x'],'y':Math[_0x5cf691(0x167)](_0x46fbaa/this[_0x5cf691(0x2be)])};}}});}[_0x56fcfe(0x21f)](_0x30526d){const _0x1d464c=_0x56fcfe;if(!this['isDragging']||!this[_0x1d464c(0x24f)]||!this['targetTile']||this['gameOver']||this[_0x1d464c(0x27e)]!==_0x1d464c(0x13f)){if(this[_0x1d464c(0x24f)]){const _0x4441e4=this[_0x1d464c(0x1ac)][this['selectedTile']['y']][this[_0x1d464c(0x24f)]['x']];if(_0x4441e4[_0x1d464c(0x1c1)])_0x4441e4[_0x1d464c(0x1c1)][_0x1d464c(0x20d)][_0x1d464c(0x27d)](_0x1d464c(0x2d5));}this[_0x1d464c(0xdd)]=![],this[_0x1d464c(0x24f)]=null,this['targetTile']=null,this[_0x1d464c(0x9f)]=null,this[_0x1d464c(0x159)]();return;}const _0x16a834=this[_0x1d464c(0x1ac)][this[_0x1d464c(0x24f)]['y']][this['selectedTile']['x']];if(_0x16a834[_0x1d464c(0x1c1)])_0x16a834[_0x1d464c(0x1c1)][_0x1d464c(0x20d)][_0x1d464c(0x27d)](_0x1d464c(0x2d5));this[_0x1d464c(0x11c)](this[_0x1d464c(0x24f)]['x'],this[_0x1d464c(0x24f)]['y'],this[_0x1d464c(0x165)]['x'],this[_0x1d464c(0x165)]['y']),this[_0x1d464c(0xdd)]=![],this['selectedTile']=null,this['targetTile']=null,this[_0x1d464c(0x9f)]=null;}[_0x56fcfe(0x155)](_0xd33f8f){const _0x21fd24=_0x56fcfe,_0x19f336=document[_0x21fd24(0x150)]('game-board')[_0x21fd24(0x275)](),_0x268aa3=Math[_0x21fd24(0x1c4)]((_0xd33f8f[_0x21fd24(0x14b)]-_0x19f336[_0x21fd24(0x1ee)])/this[_0x21fd24(0x2be)]),_0x4b35e2=Math[_0x21fd24(0x1c4)]((_0xd33f8f[_0x21fd24(0x143)]-_0x19f336['top'])/this[_0x21fd24(0x2be)]);if(_0x268aa3>=0x0&&_0x268aa3<this[_0x21fd24(0x19b)]&&_0x4b35e2>=0x0&&_0x4b35e2<this['height'])return{'x':_0x268aa3,'y':_0x4b35e2,'element':this[_0x21fd24(0x1ac)][_0x4b35e2][_0x268aa3]['element']};return null;}[_0x56fcfe(0x11c)](_0x15252f,_0x4b714c,_0x5bcf0d,_0x583ea1){const _0x204c57=_0x56fcfe,_0x3237ed=this[_0x204c57(0x2be)];let _0xbeed84;const _0xde7b45=[],_0x9328a7=[];if(_0x4b714c===_0x583ea1){_0xbeed84=_0x15252f<_0x5bcf0d?0x1:-0x1;const _0x381322=Math['min'](_0x15252f,_0x5bcf0d),_0x2923f8=Math[_0x204c57(0x23a)](_0x15252f,_0x5bcf0d);for(let _0xb1d029=_0x381322;_0xb1d029<=_0x2923f8;_0xb1d029++){_0xde7b45['push']({...this[_0x204c57(0x1ac)][_0x4b714c][_0xb1d029]}),_0x9328a7[_0x204c57(0xb7)](this[_0x204c57(0x1ac)][_0x4b714c][_0xb1d029][_0x204c57(0x1c1)]);}}else{if(_0x15252f===_0x5bcf0d){_0xbeed84=_0x4b714c<_0x583ea1?0x1:-0x1;const _0x3492c5=Math[_0x204c57(0x1f6)](_0x4b714c,_0x583ea1),_0x550c8f=Math[_0x204c57(0x23a)](_0x4b714c,_0x583ea1);for(let _0x35d3d7=_0x3492c5;_0x35d3d7<=_0x550c8f;_0x35d3d7++){_0xde7b45[_0x204c57(0xb7)]({...this[_0x204c57(0x1ac)][_0x35d3d7][_0x15252f]}),_0x9328a7[_0x204c57(0xb7)](this[_0x204c57(0x1ac)][_0x35d3d7][_0x15252f][_0x204c57(0x1c1)]);}}}const _0x8080ed=this['board'][_0x4b714c][_0x15252f]['element'],_0x47096d=(_0x5bcf0d-_0x15252f)*_0x3237ed,_0x40cbde=(_0x583ea1-_0x4b714c)*_0x3237ed;_0x8080ed[_0x204c57(0x27b)][_0x204c57(0x16e)]=_0x204c57(0xd6),_0x8080ed['style'][_0x204c57(0x285)]=_0x204c57(0x13e)+_0x47096d+_0x204c57(0x29a)+_0x40cbde+'px)';let _0x55c1b7=0x0;if(_0x4b714c===_0x583ea1)for(let _0x2ef2f5=Math[_0x204c57(0x1f6)](_0x15252f,_0x5bcf0d);_0x2ef2f5<=Math[_0x204c57(0x23a)](_0x15252f,_0x5bcf0d);_0x2ef2f5++){if(_0x2ef2f5===_0x15252f)continue;const _0x2cf7e5=_0xbeed84*-_0x3237ed*(_0x2ef2f5-_0x15252f)/Math[_0x204c57(0x290)](_0x5bcf0d-_0x15252f);_0x9328a7[_0x55c1b7][_0x204c57(0x27b)][_0x204c57(0x16e)]=_0x204c57(0xd6),_0x9328a7[_0x55c1b7][_0x204c57(0x27b)][_0x204c57(0x285)]='translate('+_0x2cf7e5+_0x204c57(0x139),_0x55c1b7++;}else for(let _0x2f9b99=Math[_0x204c57(0x1f6)](_0x4b714c,_0x583ea1);_0x2f9b99<=Math[_0x204c57(0x23a)](_0x4b714c,_0x583ea1);_0x2f9b99++){if(_0x2f9b99===_0x4b714c)continue;const _0x1ce49f=_0xbeed84*-_0x3237ed*(_0x2f9b99-_0x4b714c)/Math[_0x204c57(0x290)](_0x583ea1-_0x4b714c);_0x9328a7[_0x55c1b7][_0x204c57(0x27b)][_0x204c57(0x16e)]=_0x204c57(0xd6),_0x9328a7[_0x55c1b7][_0x204c57(0x27b)][_0x204c57(0x285)]=_0x204c57(0x1b8)+_0x1ce49f+'px)',_0x55c1b7++;}setTimeout(()=>{const _0x13757f=_0x204c57;if(_0x4b714c===_0x583ea1){const _0x4c39d4=this[_0x13757f(0x1ac)][_0x4b714c],_0x405377=[..._0x4c39d4];if(_0x15252f<_0x5bcf0d){for(let _0x40d5f9=_0x15252f;_0x40d5f9<_0x5bcf0d;_0x40d5f9++)_0x4c39d4[_0x40d5f9]=_0x405377[_0x40d5f9+0x1];}else{for(let _0x173067=_0x15252f;_0x173067>_0x5bcf0d;_0x173067--)_0x4c39d4[_0x173067]=_0x405377[_0x173067-0x1];}_0x4c39d4[_0x5bcf0d]=_0x405377[_0x15252f];}else{const _0x3b7906=[];for(let _0x152588=0x0;_0x152588<this[_0x13757f(0x1c8)];_0x152588++)_0x3b7906[_0x152588]={...this[_0x13757f(0x1ac)][_0x152588][_0x15252f]};if(_0x4b714c<_0x583ea1){for(let _0x237793=_0x4b714c;_0x237793<_0x583ea1;_0x237793++)this[_0x13757f(0x1ac)][_0x237793][_0x15252f]=_0x3b7906[_0x237793+0x1];}else{for(let _0x398ec7=_0x4b714c;_0x398ec7>_0x583ea1;_0x398ec7--)this['board'][_0x398ec7][_0x15252f]=_0x3b7906[_0x398ec7-0x1];}this[_0x13757f(0x1ac)][_0x583ea1][_0x5bcf0d]=_0x3b7906[_0x4b714c];}this[_0x13757f(0x159)]();const _0x3283bd=this[_0x13757f(0x17d)](_0x5bcf0d,_0x583ea1);_0x3283bd?this['gameState']='animating':(log(_0x13757f(0x263)),this['sounds']['badMove'][_0x13757f(0xa4)](),_0x8080ed[_0x13757f(0x27b)][_0x13757f(0x16e)]='transform\x200.2s\x20ease',_0x8080ed[_0x13757f(0x27b)]['transform']=_0x13757f(0x112),_0x9328a7['forEach'](_0x17621a=>{const _0x1f0d54=_0x13757f;_0x17621a[_0x1f0d54(0x27b)][_0x1f0d54(0x16e)]=_0x1f0d54(0xd6),_0x17621a['style'][_0x1f0d54(0x285)]=_0x1f0d54(0x112);}),setTimeout(()=>{const _0x4e012d=_0x13757f;if(_0x4b714c===_0x583ea1){const _0x47cafc=Math[_0x4e012d(0x1f6)](_0x15252f,_0x5bcf0d);for(let _0x4df267=0x0;_0x4df267<_0xde7b45[_0x4e012d(0x23c)];_0x4df267++){this[_0x4e012d(0x1ac)][_0x4b714c][_0x47cafc+_0x4df267]={..._0xde7b45[_0x4df267],'element':_0x9328a7[_0x4df267]};}}else{const _0x500381=Math[_0x4e012d(0x1f6)](_0x4b714c,_0x583ea1);for(let _0x14d678=0x0;_0x14d678<_0xde7b45[_0x4e012d(0x23c)];_0x14d678++){this[_0x4e012d(0x1ac)][_0x500381+_0x14d678][_0x15252f]={..._0xde7b45[_0x14d678],'element':_0x9328a7[_0x14d678]};}}this['renderBoard'](),this[_0x4e012d(0x27e)]='playerTurn';},0xc8));},0xc8);}[_0x56fcfe(0x17d)](_0x291d5d=null,_0x4a1bcf=null){const _0x317b63=_0x56fcfe;console[_0x317b63(0x199)](_0x317b63(0xd0),this[_0x317b63(0x101)]);if(this[_0x317b63(0x101)])return console[_0x317b63(0x199)]('Game\x20over,\x20exiting\x20resolveMatches'),![];const _0x1482ce=_0x291d5d!==null&&_0x4a1bcf!==null;console[_0x317b63(0x199)](_0x317b63(0x2af)+_0x1482ce);const _0x2d9e82=this[_0x317b63(0x107)]();console[_0x317b63(0x199)](_0x317b63(0x228)+_0x2d9e82['length']+_0x317b63(0xcd),_0x2d9e82);let _0x4d38f6=0x1,_0x46ffd3='';if(_0x1482ce&&_0x2d9e82[_0x317b63(0x23c)]>0x1){const _0x3fdaa1=_0x2d9e82['reduce']((_0x485dd9,_0x2b6cd8)=>_0x485dd9+_0x2b6cd8[_0x317b63(0x237)],0x0);console['log'](_0x317b63(0x222)+_0x3fdaa1);if(_0x3fdaa1>=0x6&&_0x3fdaa1<=0x8)_0x4d38f6=1.2,_0x46ffd3='Multi-Match!\x20'+_0x3fdaa1+_0x317b63(0x25a),this[_0x317b63(0x106)][_0x317b63(0x166)][_0x317b63(0xa4)]();else _0x3fdaa1>=0x9&&(_0x4d38f6=0x3,_0x46ffd3=_0x317b63(0x24d)+_0x3fdaa1+_0x317b63(0x2ba),this['sounds']['multiMatch'][_0x317b63(0xa4)]());}if(_0x2d9e82[_0x317b63(0x23c)]>0x0){const _0x11dfec=new Set();let _0x3c910a=0x0;const _0x2c7060=this[_0x317b63(0x283)],_0x4b96bf=this[_0x317b63(0x283)]===this['player1']?this[_0x317b63(0xf8)]:this[_0x317b63(0xd9)];try{_0x2d9e82[_0x317b63(0x220)](_0x473550=>{const _0x247f04=_0x317b63;console['log'](_0x247f04(0xdc),_0x473550),_0x473550[_0x247f04(0x111)][_0x247f04(0x220)](_0x4ce8f5=>_0x11dfec[_0x247f04(0x279)](_0x4ce8f5));const _0xde471a=this['handleMatch'](_0x473550,_0x1482ce);console[_0x247f04(0x199)](_0x247f04(0x293)+_0xde471a);if(this[_0x247f04(0x101)]){console[_0x247f04(0x199)](_0x247f04(0x1c9));return;}if(_0xde471a>0x0)_0x3c910a+=_0xde471a;});if(this[_0x317b63(0x101)])return console['log'](_0x317b63(0x1f9)),!![];return console['log'](_0x317b63(0x15f)+_0x3c910a+_0x317b63(0x276),[..._0x11dfec]),_0x3c910a>0x0&&!this[_0x317b63(0x101)]&&setTimeout(()=>{const _0x720370=_0x317b63;if(this[_0x720370(0x101)]){console[_0x720370(0x199)](_0x720370(0x12d));return;}console[_0x720370(0x199)](_0x720370(0x26e),_0x4b96bf['name']),this[_0x720370(0x238)](_0x4b96bf,_0x3c910a);},0x64),setTimeout(()=>{const _0x180711=_0x317b63;if(this[_0x180711(0x101)]){console['log']('Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading');return;}console[_0x180711(0x199)]('Animating\x20matched\x20tiles,\x20allMatchedTiles:',[..._0x11dfec]),_0x11dfec[_0x180711(0x220)](_0x172e38=>{const _0x6f06d2=_0x180711,[_0x29b557,_0x2dbe1b]=_0x172e38[_0x6f06d2(0x28d)](',')['map'](Number);this['board'][_0x2dbe1b][_0x29b557]?.['element']?this[_0x6f06d2(0x1ac)][_0x2dbe1b][_0x29b557][_0x6f06d2(0x1c1)][_0x6f06d2(0x20d)][_0x6f06d2(0x279)](_0x6f06d2(0x23f)):console[_0x6f06d2(0xad)](_0x6f06d2(0x24e)+_0x29b557+','+_0x2dbe1b+')\x20has\x20no\x20element\x20to\x20animate');}),setTimeout(()=>{const _0x5c4a39=_0x180711;if(this[_0x5c4a39(0x101)]){console['log'](_0x5c4a39(0x15a));return;}console[_0x5c4a39(0x199)](_0x5c4a39(0x2cc),[..._0x11dfec]),_0x11dfec['forEach'](_0x24c049=>{const _0x10f4ef=_0x5c4a39,[_0x58e8e2,_0x6f28c7]=_0x24c049['split'](',')['map'](Number);this['board'][_0x6f28c7][_0x58e8e2]&&(this[_0x10f4ef(0x1ac)][_0x6f28c7][_0x58e8e2][_0x10f4ef(0x2e0)]=null,this['board'][_0x6f28c7][_0x58e8e2][_0x10f4ef(0x1c1)]=null);}),this[_0x5c4a39(0x106)]['match'][_0x5c4a39(0xa4)](),console[_0x5c4a39(0x199)]('Cascading\x20tiles');if(_0x4d38f6>0x1&&this[_0x5c4a39(0xbb)][_0x5c4a39(0x23c)]>0x0){const _0x252a91=this[_0x5c4a39(0xbb)][this['roundStats'][_0x5c4a39(0x23c)]-0x1],_0x21f9e9=_0x252a91[_0x5c4a39(0x253)];_0x252a91[_0x5c4a39(0x253)]=Math['round'](_0x252a91['points']*_0x4d38f6),_0x46ffd3&&(log(_0x46ffd3),log('Round\x20points\x20increased\x20from\x20'+_0x21f9e9+'\x20to\x20'+_0x252a91[_0x5c4a39(0x253)]+'\x20after\x20multi-match\x20bonus!'));}this[_0x5c4a39(0x268)](()=>{const _0x2c7c9d=_0x5c4a39;if(this[_0x2c7c9d(0x101)]){console[_0x2c7c9d(0x199)](_0x2c7c9d(0x176));return;}console[_0x2c7c9d(0x199)](_0x2c7c9d(0x264)),this['endTurn']();});},0x12c);},0xc8),!![];}catch(_0x1f5e69){return console[_0x317b63(0x2b8)](_0x317b63(0x2bd),_0x1f5e69),this[_0x317b63(0x27e)]=this[_0x317b63(0x283)]===this['player1']?_0x317b63(0x13f):_0x317b63(0xdb),![];}}return console[_0x317b63(0x199)](_0x317b63(0xea)),![];}[_0x56fcfe(0x107)](){const _0xec0b29=_0x56fcfe;console['log']('checkMatches\x20started');const _0x3cf810=[];try{const _0x435615=[];for(let _0x4992e7=0x0;_0x4992e7<this[_0xec0b29(0x1c8)];_0x4992e7++){let _0x2c984d=0x0;for(let _0x54747b=0x0;_0x54747b<=this['width'];_0x54747b++){const _0x5c4837=_0x54747b<this[_0xec0b29(0x19b)]?this[_0xec0b29(0x1ac)][_0x4992e7][_0x54747b]?.[_0xec0b29(0x2e0)]:null;if(_0x5c4837!==this['board'][_0x4992e7][_0x2c984d]?.[_0xec0b29(0x2e0)]||_0x54747b===this['width']){const _0x2ccd4f=_0x54747b-_0x2c984d;if(_0x2ccd4f>=0x3){const _0x50cc32=new Set();for(let _0x1bb68b=_0x2c984d;_0x1bb68b<_0x54747b;_0x1bb68b++){_0x50cc32[_0xec0b29(0x279)](_0x1bb68b+','+_0x4992e7);}_0x435615[_0xec0b29(0xb7)]({'type':this[_0xec0b29(0x1ac)][_0x4992e7][_0x2c984d][_0xec0b29(0x2e0)],'coordinates':_0x50cc32}),console['log'](_0xec0b29(0xb8)+_0x4992e7+_0xec0b29(0xe9)+_0x2c984d+'-'+(_0x54747b-0x1)+':',[..._0x50cc32]);}_0x2c984d=_0x54747b;}}}for(let _0x458e85=0x0;_0x458e85<this[_0xec0b29(0x19b)];_0x458e85++){let _0x48bbef=0x0;for(let _0x44eee5=0x0;_0x44eee5<=this[_0xec0b29(0x1c8)];_0x44eee5++){const _0x1e8af0=_0x44eee5<this[_0xec0b29(0x1c8)]?this['board'][_0x44eee5][_0x458e85]?.[_0xec0b29(0x2e0)]:null;if(_0x1e8af0!==this[_0xec0b29(0x1ac)][_0x48bbef][_0x458e85]?.['type']||_0x44eee5===this[_0xec0b29(0x1c8)]){const _0xc8621d=_0x44eee5-_0x48bbef;if(_0xc8621d>=0x3){const _0x5958f3=new Set();for(let _0xa53c23=_0x48bbef;_0xa53c23<_0x44eee5;_0xa53c23++){_0x5958f3[_0xec0b29(0x279)](_0x458e85+','+_0xa53c23);}_0x435615[_0xec0b29(0xb7)]({'type':this[_0xec0b29(0x1ac)][_0x48bbef][_0x458e85]['type'],'coordinates':_0x5958f3}),console[_0xec0b29(0x199)](_0xec0b29(0x162)+_0x458e85+_0xec0b29(0x28f)+_0x48bbef+'-'+(_0x44eee5-0x1)+':',[..._0x5958f3]);}_0x48bbef=_0x44eee5;}}}const _0x52aa4f=[],_0x1c6c78=new Set();return _0x435615['forEach']((_0x499ad6,_0xf4734b)=>{const _0x396d5c=_0xec0b29;if(_0x1c6c78[_0x396d5c(0xf7)](_0xf4734b))return;const _0x2ac303={'type':_0x499ad6['type'],'coordinates':new Set(_0x499ad6[_0x396d5c(0x111)])};_0x1c6c78[_0x396d5c(0x279)](_0xf4734b);for(let _0x2f4df0=0x0;_0x2f4df0<_0x435615[_0x396d5c(0x23c)];_0x2f4df0++){if(_0x1c6c78[_0x396d5c(0xf7)](_0x2f4df0))continue;const _0x19afab=_0x435615[_0x2f4df0];if(_0x19afab[_0x396d5c(0x2e0)]===_0x2ac303['type']){const _0x4df51b=[..._0x19afab['coordinates']][_0x396d5c(0x2cb)](_0x5bc662=>_0x2ac303[_0x396d5c(0x111)][_0x396d5c(0xf7)](_0x5bc662));_0x4df51b&&(_0x19afab[_0x396d5c(0x111)][_0x396d5c(0x220)](_0x3de9a5=>_0x2ac303[_0x396d5c(0x111)][_0x396d5c(0x279)](_0x3de9a5)),_0x1c6c78['add'](_0x2f4df0));}}_0x52aa4f[_0x396d5c(0xb7)]({'type':_0x2ac303[_0x396d5c(0x2e0)],'coordinates':_0x2ac303['coordinates'],'totalTiles':_0x2ac303['coordinates'][_0x396d5c(0x226)]});}),_0x3cf810[_0xec0b29(0xb7)](..._0x52aa4f),console[_0xec0b29(0x199)]('checkMatches\x20completed,\x20returning\x20matches:',_0x3cf810),_0x3cf810;}catch(_0x5e2616){return console[_0xec0b29(0x2b8)]('Error\x20in\x20checkMatches:',_0x5e2616),[];}}[_0x56fcfe(0x281)](_0x55ac9a,_0x106c0f=!![]){const _0x33e16b=_0x56fcfe;console[_0x33e16b(0x199)]('handleMatch\x20started,\x20match:',_0x55ac9a,_0x33e16b(0x265),_0x106c0f);const _0x7bc0e9=this[_0x33e16b(0x283)],_0x2e3788=this[_0x33e16b(0x283)]===this[_0x33e16b(0xd9)]?this['player2']:this[_0x33e16b(0xd9)],_0x43d4da=_0x55ac9a[_0x33e16b(0x2e0)],_0x5560fe=_0x55ac9a[_0x33e16b(0x237)];let _0x5c3053=0x0,_0x54afd8=0x0;console[_0x33e16b(0x199)](_0x2e3788['name']+_0x33e16b(0xf9)+_0x2e3788[_0x33e16b(0x2b4)]);_0x5560fe==0x4&&(this[_0x33e16b(0x106)][_0x33e16b(0x1f4)]['play'](),log(_0x7bc0e9[_0x33e16b(0x258)]+_0x33e16b(0xb5)+_0x5560fe+_0x33e16b(0x29c)));_0x5560fe>=0x5&&(this[_0x33e16b(0x106)][_0x33e16b(0x1d8)][_0x33e16b(0xa4)](),log(_0x7bc0e9[_0x33e16b(0x258)]+_0x33e16b(0xb5)+_0x5560fe+'\x20tiles!'));if(_0x43d4da===_0x33e16b(0x29b)||_0x43d4da===_0x33e16b(0x296)||_0x43d4da===_0x33e16b(0x25c)||_0x43d4da==='last-stand'){_0x5c3053=Math['round'](_0x7bc0e9[_0x33e16b(0x254)]*(_0x5560fe===0x3?0x2:_0x5560fe===0x4?0x3:0x4));let _0x56a70a=0x1;if(_0x5560fe===0x4)_0x56a70a=1.5;else _0x5560fe>=0x5&&(_0x56a70a=0x2);_0x5c3053=Math['round'](_0x5c3053*_0x56a70a),console[_0x33e16b(0x199)](_0x33e16b(0x173)+_0x7bc0e9[_0x33e16b(0x254)]*(_0x5560fe===0x3?0x2:_0x5560fe===0x4?0x3:0x4)+_0x33e16b(0xfd)+_0x56a70a+_0x33e16b(0x229)+_0x5c3053);_0x43d4da===_0x33e16b(0x25c)&&(_0x5c3053=Math[_0x33e16b(0x167)](_0x5c3053*1.2),console[_0x33e16b(0x199)]('Special\x20attack\x20multiplier\x20applied,\x20damage:\x20'+_0x5c3053));_0x7bc0e9[_0x33e16b(0x191)]&&(_0x5c3053+=_0x7bc0e9['boostValue']||0xa,_0x7bc0e9[_0x33e16b(0x191)]=![],log(_0x7bc0e9[_0x33e16b(0x258)]+_0x33e16b(0x2ad)),console[_0x33e16b(0x199)](_0x33e16b(0x1dc)+_0x5c3053));_0x54afd8=_0x5c3053;const _0x54d2fa=_0x2e3788['tactics']*0xa;Math[_0x33e16b(0x2d7)]()*0x64<_0x54d2fa&&(_0x5c3053=Math[_0x33e16b(0x1c4)](_0x5c3053/0x2),log(_0x2e3788[_0x33e16b(0x258)]+'\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20'+_0x5c3053+_0x33e16b(0x109)),console['log'](_0x33e16b(0x1a7)+_0x5c3053));let _0x7f3260=0x0;_0x2e3788['lastStandActive']&&(_0x7f3260=Math[_0x33e16b(0x1f6)](_0x5c3053,0x5),_0x5c3053=Math[_0x33e16b(0x23a)](0x0,_0x5c3053-_0x7f3260),_0x2e3788[_0x33e16b(0x1ed)]=![],console[_0x33e16b(0x199)](_0x33e16b(0xc1)+_0x7f3260+_0x33e16b(0x2a6)+_0x5c3053));const _0x5d1d04=_0x43d4da===_0x33e16b(0x29b)?_0x33e16b(0x246):_0x43d4da===_0x33e16b(0x296)?'Bite':'Shadow\x20Strike';let _0x46aad4;if(_0x7f3260>0x0)_0x46aad4=_0x7bc0e9[_0x33e16b(0x258)]+_0x33e16b(0x2a9)+_0x5d1d04+_0x33e16b(0x1a3)+_0x2e3788['name']+_0x33e16b(0xe6)+_0x54afd8+_0x33e16b(0x18b)+_0x2e3788['name']+_0x33e16b(0x216)+_0x7f3260+_0x33e16b(0x9c)+_0x5c3053+_0x33e16b(0x109);else _0x43d4da==='last-stand'?_0x46aad4=_0x7bc0e9[_0x33e16b(0x258)]+_0x33e16b(0x2d1)+_0x5c3053+_0x33e16b(0x190)+_0x2e3788[_0x33e16b(0x258)]+_0x33e16b(0x1cc):_0x46aad4=_0x7bc0e9[_0x33e16b(0x258)]+_0x33e16b(0x2a9)+_0x5d1d04+'\x20on\x20'+_0x2e3788[_0x33e16b(0x258)]+_0x33e16b(0xe6)+_0x5c3053+_0x33e16b(0x109);_0x106c0f?log(_0x46aad4):log('Cascade:\x20'+_0x46aad4),_0x2e3788[_0x33e16b(0x2b4)]=Math['max'](0x0,_0x2e3788[_0x33e16b(0x2b4)]-_0x5c3053),console[_0x33e16b(0x199)](_0x2e3788[_0x33e16b(0x258)]+_0x33e16b(0x100)+_0x2e3788[_0x33e16b(0x2b4)]),this[_0x33e16b(0x135)](_0x2e3788),console[_0x33e16b(0x199)](_0x33e16b(0x287)),this[_0x33e16b(0x1bd)](),!this[_0x33e16b(0x101)]&&(console[_0x33e16b(0x199)](_0x33e16b(0x298)),this[_0x33e16b(0x16c)](_0x7bc0e9,_0x5c3053,_0x43d4da));}else _0x43d4da===_0x33e16b(0x196)&&(this[_0x33e16b(0x247)](_0x7bc0e9,_0x2e3788,_0x5560fe),!this[_0x33e16b(0x101)]&&(console[_0x33e16b(0x199)](_0x33e16b(0x13c)),this['animatePowerup'](_0x7bc0e9)));(!this[_0x33e16b(0xbb)][this[_0x33e16b(0xbb)][_0x33e16b(0x23c)]-0x1]||this['roundStats'][this[_0x33e16b(0xbb)][_0x33e16b(0x23c)]-0x1][_0x33e16b(0x110)])&&this[_0x33e16b(0xbb)][_0x33e16b(0xb7)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x247182=this[_0x33e16b(0xbb)][this[_0x33e16b(0xbb)][_0x33e16b(0x23c)]-0x1];return _0x247182[_0x33e16b(0x253)]+=_0x5c3053,_0x247182[_0x33e16b(0x168)]+=0x1,console[_0x33e16b(0x199)](_0x33e16b(0x15b)+_0x5c3053),_0x5c3053;}[_0x56fcfe(0x268)](_0x3abb59){const _0x1013fb=_0x56fcfe;if(this[_0x1013fb(0x101)]){console['log'](_0x1013fb(0xe0));return;}const _0x994118=this['cascadeTilesWithoutRender'](),_0x265dae=_0x1013fb(0xe1);for(let _0x4cb31a=0x0;_0x4cb31a<this[_0x1013fb(0x19b)];_0x4cb31a++){for(let _0x5175be=0x0;_0x5175be<this[_0x1013fb(0x1c8)];_0x5175be++){const _0x114c87=this[_0x1013fb(0x1ac)][_0x5175be][_0x4cb31a];if(_0x114c87['element']&&_0x114c87[_0x1013fb(0x1c1)][_0x1013fb(0x27b)][_0x1013fb(0x285)]===_0x1013fb(0x21c)){const _0x3ec03c=this[_0x1013fb(0x1b5)](_0x4cb31a,_0x5175be);_0x3ec03c>0x0&&(_0x114c87[_0x1013fb(0x1c1)][_0x1013fb(0x20d)]['add'](_0x265dae),_0x114c87[_0x1013fb(0x1c1)][_0x1013fb(0x27b)]['transform']=_0x1013fb(0x1b8)+_0x3ec03c*this[_0x1013fb(0x2be)]+'px)');}}}this['renderBoard'](),_0x994118?setTimeout(()=>{const _0x5a8ebc=_0x1013fb;if(this[_0x5a8ebc(0x101)]){console[_0x5a8ebc(0x199)](_0x5a8ebc(0x250));return;}this['sounds'][_0x5a8ebc(0x2ac)][_0x5a8ebc(0xa4)]();const _0x32e731=this[_0x5a8ebc(0x17d)](),_0x5f363c=document['querySelectorAll']('.'+_0x265dae);_0x5f363c[_0x5a8ebc(0x220)](_0x3476d7=>{const _0x33d87a=_0x5a8ebc;_0x3476d7[_0x33d87a(0x20d)][_0x33d87a(0x27d)](_0x265dae),_0x3476d7['style'][_0x33d87a(0x285)]=_0x33d87a(0x112);}),!_0x32e731&&_0x3abb59();},0x12c):_0x3abb59();}[_0x56fcfe(0x1fa)](){const _0x10b104=_0x56fcfe;let _0x2f0626=![];for(let _0x41d3f3=0x0;_0x41d3f3<this[_0x10b104(0x19b)];_0x41d3f3++){let _0x379f55=0x0;for(let _0x5ad28d=this[_0x10b104(0x1c8)]-0x1;_0x5ad28d>=0x0;_0x5ad28d--){if(!this[_0x10b104(0x1ac)][_0x5ad28d][_0x41d3f3]['type'])_0x379f55++;else _0x379f55>0x0&&(this['board'][_0x5ad28d+_0x379f55][_0x41d3f3]=this[_0x10b104(0x1ac)][_0x5ad28d][_0x41d3f3],this['board'][_0x5ad28d][_0x41d3f3]={'type':null,'element':null},_0x2f0626=!![]);}for(let _0x4432e5=0x0;_0x4432e5<_0x379f55;_0x4432e5++){this[_0x10b104(0x1ac)][_0x4432e5][_0x41d3f3]=this['createRandomTile'](),_0x2f0626=!![];}}return _0x2f0626;}['countEmptyBelow'](_0x130cfe,_0x682996){const _0x1c3dc0=_0x56fcfe;let _0x42cfa6=0x0;for(let _0x835e90=_0x682996+0x1;_0x835e90<this[_0x1c3dc0(0x1c8)];_0x835e90++){if(!this['board'][_0x835e90][_0x130cfe][_0x1c3dc0(0x2e0)])_0x42cfa6++;else break;}return _0x42cfa6;}[_0x56fcfe(0x247)](_0x3db509,_0x24841a,_0x4686b7){const _0x571f0a=_0x56fcfe,_0x11bd52=0x1-_0x24841a[_0x571f0a(0x1ca)]*0.05;let _0x2d8197,_0x4184a6,_0x397267,_0x23ea7b=0x1,_0x4eef1f='';if(_0x4686b7===0x4)_0x23ea7b=1.5,_0x4eef1f='\x20(50%\x20bonus\x20for\x20match-4)';else _0x4686b7>=0x5&&(_0x23ea7b=0x2,_0x4eef1f=_0x571f0a(0x19c));if(_0x3db509[_0x571f0a(0x209)]===_0x571f0a(0x24a))_0x4184a6=0xa*_0x23ea7b,_0x2d8197=Math['floor'](_0x4184a6*_0x11bd52),_0x397267=_0x4184a6-_0x2d8197,_0x3db509[_0x571f0a(0x2b4)]=Math[_0x571f0a(0x1f6)](_0x3db509[_0x571f0a(0x1ce)],_0x3db509[_0x571f0a(0x2b4)]+_0x2d8197),log(_0x3db509[_0x571f0a(0x258)]+_0x571f0a(0xd8)+_0x2d8197+_0x571f0a(0x10e)+_0x4eef1f+(_0x24841a[_0x571f0a(0x1ca)]>0x0?_0x571f0a(0xaa)+_0x4184a6+_0x571f0a(0xc4)+_0x397267+'\x20due\x20to\x20'+_0x24841a[_0x571f0a(0x258)]+_0x571f0a(0x170):'')+'!');else{if(_0x3db509[_0x571f0a(0x209)]===_0x571f0a(0x134))_0x4184a6=0xa*_0x23ea7b,_0x2d8197=Math['floor'](_0x4184a6*_0x11bd52),_0x397267=_0x4184a6-_0x2d8197,_0x3db509[_0x571f0a(0x191)]=!![],_0x3db509[_0x571f0a(0xd7)]=_0x2d8197,log(_0x3db509[_0x571f0a(0x258)]+_0x571f0a(0x23d)+_0x2d8197+_0x571f0a(0x157)+_0x4eef1f+(_0x24841a[_0x571f0a(0x1ca)]>0x0?_0x571f0a(0xaa)+_0x4184a6+_0x571f0a(0xc4)+_0x397267+_0x571f0a(0xbd)+_0x24841a[_0x571f0a(0x258)]+_0x571f0a(0x170):'')+'!');else{if(_0x3db509[_0x571f0a(0x209)]===_0x571f0a(0x1f2))_0x4184a6=0x7*_0x23ea7b,_0x2d8197=Math['floor'](_0x4184a6*_0x11bd52),_0x397267=_0x4184a6-_0x2d8197,_0x3db509[_0x571f0a(0x2b4)]=Math[_0x571f0a(0x1f6)](_0x3db509[_0x571f0a(0x1ce)],_0x3db509[_0x571f0a(0x2b4)]+_0x2d8197),log(_0x3db509[_0x571f0a(0x258)]+'\x20uses\x20Regen,\x20restoring\x20'+_0x2d8197+_0x571f0a(0x10e)+_0x4eef1f+(_0x24841a['tactics']>0x0?_0x571f0a(0xaa)+_0x4184a6+_0x571f0a(0xc4)+_0x397267+_0x571f0a(0xbd)+_0x24841a[_0x571f0a(0x258)]+'\x27s\x20tactics)':'')+'!');else _0x3db509[_0x571f0a(0x209)]===_0x571f0a(0x252)&&(_0x4184a6=0x5*_0x23ea7b,_0x2d8197=Math[_0x571f0a(0x1c4)](_0x4184a6*_0x11bd52),_0x397267=_0x4184a6-_0x2d8197,_0x3db509[_0x571f0a(0x2b4)]=Math[_0x571f0a(0x1f6)](_0x3db509[_0x571f0a(0x1ce)],_0x3db509[_0x571f0a(0x2b4)]+_0x2d8197),log(_0x3db509[_0x571f0a(0x258)]+_0x571f0a(0x1de)+_0x2d8197+_0x571f0a(0x10e)+_0x4eef1f+(_0x24841a['tactics']>0x0?_0x571f0a(0xaa)+_0x4184a6+_0x571f0a(0xc4)+_0x397267+_0x571f0a(0xbd)+_0x24841a[_0x571f0a(0x258)]+_0x571f0a(0x170):'')+'!'));}}this[_0x571f0a(0x135)](_0x3db509);}['updateHealth'](_0x5357bb){const _0x2e9edb=_0x56fcfe,_0x288783=_0x5357bb===this[_0x2e9edb(0xd9)]?p1Health:p2Health,_0x3f8cb8=_0x5357bb===this[_0x2e9edb(0xd9)]?p1Hp:p2Hp,_0x5a4803=_0x5357bb[_0x2e9edb(0x2b4)]/_0x5357bb[_0x2e9edb(0x1ce)]*0x64;_0x288783[_0x2e9edb(0x27b)][_0x2e9edb(0x19b)]=_0x5a4803+'%';let _0x3f9243;if(_0x5a4803>0x4b)_0x3f9243=_0x2e9edb(0xc6);else{if(_0x5a4803>0x32)_0x3f9243=_0x2e9edb(0x25b);else _0x5a4803>0x19?_0x3f9243=_0x2e9edb(0x15e):_0x3f9243=_0x2e9edb(0x1e9);}_0x288783[_0x2e9edb(0x27b)]['backgroundColor']=_0x3f9243,_0x3f8cb8[_0x2e9edb(0xd4)]=_0x5357bb['health']+'/'+_0x5357bb[_0x2e9edb(0x1ce)];}[_0x56fcfe(0x249)](){const _0x3c0ff9=_0x56fcfe;if(this[_0x3c0ff9(0x27e)]===_0x3c0ff9(0x101)||this[_0x3c0ff9(0x101)]){console[_0x3c0ff9(0x199)](_0x3c0ff9(0x176));return;}this[_0x3c0ff9(0x283)]=this[_0x3c0ff9(0x283)]===this[_0x3c0ff9(0xd9)]?this[_0x3c0ff9(0xf8)]:this[_0x3c0ff9(0xd9)],this[_0x3c0ff9(0x27e)]=this[_0x3c0ff9(0x283)]===this['player1']?'playerTurn':_0x3c0ff9(0xdb),turnIndicator['textContent']=_0x3c0ff9(0x1c3)+this['currentLevel']+_0x3c0ff9(0x14d)+(this[_0x3c0ff9(0x283)]===this[_0x3c0ff9(0xd9)]?_0x3c0ff9(0x1e0):_0x3c0ff9(0x2a5))+_0x3c0ff9(0x1ba),log(_0x3c0ff9(0x207)+(this[_0x3c0ff9(0x283)]===this[_0x3c0ff9(0xd9)]?_0x3c0ff9(0x1e0):_0x3c0ff9(0x2a5))),this[_0x3c0ff9(0x283)]===this[_0x3c0ff9(0xf8)]&&setTimeout(()=>this['aiTurn'](),0x3e8);}['aiTurn'](){const _0x160695=_0x56fcfe;if(this[_0x160695(0x27e)]!==_0x160695(0xdb)||this[_0x160695(0x283)]!==this[_0x160695(0xf8)])return;this[_0x160695(0x27e)]=_0x160695(0x2c2);const _0x11076a=this[_0x160695(0x108)]();_0x11076a?(log(this[_0x160695(0xf8)]['name']+_0x160695(0x1df)+_0x11076a['x1']+',\x20'+_0x11076a['y1']+_0x160695(0x146)+_0x11076a['x2']+',\x20'+_0x11076a['y2']+')'),this[_0x160695(0x11c)](_0x11076a['x1'],_0x11076a['y1'],_0x11076a['x2'],_0x11076a['y2'])):(log(this[_0x160695(0xf8)][_0x160695(0x258)]+_0x160695(0xbc)),this['endTurn']());}['findAIMove'](){const _0x1f3f75=_0x56fcfe;for(let _0x29cb2d=0x0;_0x29cb2d<this[_0x1f3f75(0x1c8)];_0x29cb2d++){for(let _0x1f755b=0x0;_0x1f755b<this['width'];_0x1f755b++){if(_0x1f755b<this[_0x1f3f75(0x19b)]-0x1&&this[_0x1f3f75(0x2c0)](_0x1f755b,_0x29cb2d,_0x1f755b+0x1,_0x29cb2d))return{'x1':_0x1f755b,'y1':_0x29cb2d,'x2':_0x1f755b+0x1,'y2':_0x29cb2d};if(_0x29cb2d<this[_0x1f3f75(0x1c8)]-0x1&&this[_0x1f3f75(0x2c0)](_0x1f755b,_0x29cb2d,_0x1f755b,_0x29cb2d+0x1))return{'x1':_0x1f755b,'y1':_0x29cb2d,'x2':_0x1f755b,'y2':_0x29cb2d+0x1};}}return null;}['canMakeMatch'](_0x26d25b,_0x8fe801,_0x355ed5,_0x490687){const _0x3caf32=_0x56fcfe,_0x522a51={...this['board'][_0x8fe801][_0x26d25b]},_0x4d5ade={...this[_0x3caf32(0x1ac)][_0x490687][_0x355ed5]};this[_0x3caf32(0x1ac)][_0x8fe801][_0x26d25b]=_0x4d5ade,this['board'][_0x490687][_0x355ed5]=_0x522a51;const _0x2c7dcc=this['checkMatches']()[_0x3caf32(0x23c)]>0x0;return this[_0x3caf32(0x1ac)][_0x8fe801][_0x26d25b]=_0x522a51,this[_0x3caf32(0x1ac)][_0x490687][_0x355ed5]=_0x4d5ade,_0x2c7dcc;}async[_0x56fcfe(0x1bd)](){const _0x186b58=_0x56fcfe;if(this[_0x186b58(0x101)]||this[_0x186b58(0x163)]){console[_0x186b58(0x199)](_0x186b58(0x291)+this['gameOver']+_0x186b58(0x1c6)+this[_0x186b58(0x163)]+_0x186b58(0x23b)+this[_0x186b58(0x211)]);return;}this[_0x186b58(0x163)]=!![],console[_0x186b58(0x199)](_0x186b58(0x2d9)+this[_0x186b58(0x211)]+_0x186b58(0x1b1)+this[_0x186b58(0xd9)][_0x186b58(0x2b4)]+',\x20player2.health='+this[_0x186b58(0xf8)][_0x186b58(0x2b4)]);const _0x5720d9=document[_0x186b58(0x150)](_0x186b58(0xa1));if(this[_0x186b58(0xd9)][_0x186b58(0x2b4)]<=0x0){console['log'](_0x186b58(0x19f)),this['gameOver']=!![],this[_0x186b58(0x27e)]=_0x186b58(0x101),gameOver['textContent']='You\x20Lose!',turnIndicator[_0x186b58(0xd4)]=_0x186b58(0x205),log(this[_0x186b58(0xf8)]['name']+_0x186b58(0x244)+this['player1'][_0x186b58(0x258)]+'!'),_0x5720d9['textContent']=_0x186b58(0x116),document[_0x186b58(0x150)]('game-over-container')[_0x186b58(0x27b)][_0x186b58(0x19a)]=_0x186b58(0xfb);try{this[_0x186b58(0x106)]['loss'][_0x186b58(0xa4)]();}catch(_0x5702af){console[_0x186b58(0x2b8)](_0x186b58(0x1a0),_0x5702af);}}else{if(this[_0x186b58(0xf8)][_0x186b58(0x2b4)]<=0x0){console['log'](_0x186b58(0xf3)),this[_0x186b58(0x101)]=!![],this['gameState']='gameOver',gameOver[_0x186b58(0xd4)]=_0x186b58(0x194),turnIndicator['textContent']=_0x186b58(0x205),_0x5720d9[_0x186b58(0xd4)]=this[_0x186b58(0x211)]===opponentsConfig['length']?'START\x20OVER':_0x186b58(0x16f),document[_0x186b58(0x150)]('game-over-container')[_0x186b58(0x27b)][_0x186b58(0x19a)]=_0x186b58(0xfb);if(this[_0x186b58(0x283)]===this[_0x186b58(0xd9)]){const _0x28a994=this[_0x186b58(0xbb)][this['roundStats']['length']-0x1];if(_0x28a994&&!_0x28a994[_0x186b58(0x110)]){_0x28a994[_0x186b58(0x274)]=this[_0x186b58(0xd9)]['health']/this['player1'][_0x186b58(0x1ce)]*0x64,_0x28a994[_0x186b58(0x110)]=!![];const _0x167f7f=_0x28a994[_0x186b58(0x168)]>0x0?_0x28a994['points']/_0x28a994[_0x186b58(0x168)]/0x64*(_0x28a994[_0x186b58(0x274)]+0x14)*(0x1+this[_0x186b58(0x211)]/0x38):0x0;log('Calculating\x20round\x20score:\x20points='+_0x28a994[_0x186b58(0x253)]+',\x20matches='+_0x28a994['matches']+_0x186b58(0xca)+_0x28a994[_0x186b58(0x274)][_0x186b58(0x22f)](0x2)+_0x186b58(0x184)+this[_0x186b58(0x211)]),log(_0x186b58(0x2a3)+_0x28a994['points']+_0x186b58(0x1b9)+_0x28a994[_0x186b58(0x168)]+_0x186b58(0x2b7)+_0x28a994['healthPercentage']+_0x186b58(0x242)+this[_0x186b58(0x211)]+_0x186b58(0xe2)+_0x167f7f),this[_0x186b58(0x151)]+=_0x167f7f,log(_0x186b58(0xd3)+_0x28a994[_0x186b58(0x253)]+_0x186b58(0x241)+_0x28a994['matches']+_0x186b58(0x181)+_0x28a994[_0x186b58(0x274)]['toFixed'](0x2)+'%'),log(_0x186b58(0x17c)+_0x167f7f+_0x186b58(0x2d6)+this[_0x186b58(0x151)]);}}await this[_0x186b58(0x1d0)](this[_0x186b58(0x211)]);this[_0x186b58(0x211)]===opponentsConfig['length']?(this[_0x186b58(0x106)]['finalWin'][_0x186b58(0xa4)](),log(_0x186b58(0x219)+this['grandTotalScore']),this[_0x186b58(0x151)]=0x0,await this['clearProgress'](),log(_0x186b58(0x18f))):(this[_0x186b58(0x24c)](),this['currentLevel']+=0x1,await this['saveProgress'](),console[_0x186b58(0x199)](_0x186b58(0x251)+this[_0x186b58(0x211)]),this[_0x186b58(0x106)][_0x186b58(0xe5)][_0x186b58(0xa4)]());const _0x1fdf7e=themes[_0x186b58(0x20a)](_0xe1d7ef=>_0xe1d7ef[_0x186b58(0xb3)])[_0x186b58(0x160)](_0x26bd9f=>_0x26bd9f['value']===this[_0x186b58(0xa0)]),_0x54ca22=_0x1fdf7e?.['extension']||'png',_0x38f99b=this[_0x186b58(0x10b)]+'battle-damaged/'+this[_0x186b58(0xf8)][_0x186b58(0x258)][_0x186b58(0x262)]()[_0x186b58(0x1e5)](/ /g,'-')+'.'+_0x54ca22,_0x1c3987=document[_0x186b58(0x150)]('p2-image'),_0x21c4a5=_0x1c3987[_0x186b58(0x2b1)];if(this[_0x186b58(0xf8)][_0x186b58(0xa8)]===_0x186b58(0x113)){if(_0x1c3987[_0x186b58(0x14e)]!==_0x186b58(0x164)){const _0x273dea=document[_0x186b58(0x16a)](_0x186b58(0x113));_0x273dea['id']='p2-image',_0x273dea[_0x186b58(0x1bc)]=_0x38f99b,_0x273dea[_0x186b58(0x188)]=!![],_0x273dea[_0x186b58(0x206)]=!![],_0x273dea[_0x186b58(0x2cd)]=!![],_0x273dea['alt']=this[_0x186b58(0xf8)][_0x186b58(0x258)],_0x21c4a5['replaceChild'](_0x273dea,_0x1c3987);}else _0x1c3987['src']=_0x38f99b;}else{if(_0x1c3987[_0x186b58(0x14e)]!==_0x186b58(0x142)){const _0x4364bf=document[_0x186b58(0x16a)](_0x186b58(0x2db));_0x4364bf['id']=_0x186b58(0xba),_0x4364bf[_0x186b58(0x1bc)]=_0x38f99b,_0x4364bf['alt']=this[_0x186b58(0xf8)]['name'],_0x21c4a5[_0x186b58(0x1a2)](_0x4364bf,_0x1c3987);}else _0x1c3987[_0x186b58(0x1bc)]=_0x38f99b;}const _0x569e6d=document[_0x186b58(0x150)](_0x186b58(0xba));_0x569e6d[_0x186b58(0x27b)][_0x186b58(0x19a)]=_0x186b58(0xfb),_0x569e6d[_0x186b58(0x20d)][_0x186b58(0x279)](_0x186b58(0xbf)),p1Image[_0x186b58(0x20d)][_0x186b58(0x279)](_0x186b58(0x27a)),this[_0x186b58(0x159)]();}}this[_0x186b58(0x163)]=![],console[_0x186b58(0x199)](_0x186b58(0x13a)+this[_0x186b58(0x211)]+_0x186b58(0x1ef)+this[_0x186b58(0x101)]);}async[_0x56fcfe(0x1d0)](_0x295982){const _0x1ddb9d=_0x56fcfe,_0x41d289={'level':_0x295982,'score':this['grandTotalScore']};console[_0x1ddb9d(0x199)](_0x1ddb9d(0xe8)+_0x41d289[_0x1ddb9d(0x1e3)]+_0x1ddb9d(0xf2)+_0x41d289['score']);try{const _0x416ef7=await fetch(_0x1ddb9d(0x2bf),{'method':_0x1ddb9d(0x2a0),'headers':{'Content-Type':'application/json'},'body':JSON['stringify'](_0x41d289)});if(!_0x416ef7['ok'])throw new Error(_0x1ddb9d(0x1fe)+_0x416ef7[_0x1ddb9d(0x1d9)]);const _0x1ac541=await _0x416ef7[_0x1ddb9d(0x200)]();console['log']('Save\x20response:',_0x1ac541),log(_0x1ddb9d(0x1c3)+_0x1ac541['level']+_0x1ddb9d(0x1f3)+_0x1ac541[_0x1ddb9d(0x267)][_0x1ddb9d(0x22f)](0x2)),_0x1ac541[_0x1ddb9d(0x1d9)]===_0x1ddb9d(0x115)?log(_0x1ddb9d(0x1da)+_0x1ac541[_0x1ddb9d(0x1e3)]+',\x20Score\x20'+_0x1ac541['score'][_0x1ddb9d(0x22f)](0x2)+_0x1ddb9d(0x299)+_0x1ac541[_0x1ddb9d(0x27c)]):log(_0x1ddb9d(0x128)+_0x1ac541['message']);}catch(_0x4d8716){console[_0x1ddb9d(0x2b8)](_0x1ddb9d(0x14f),_0x4d8716),log(_0x1ddb9d(0x9b)+_0x4d8716[_0x1ddb9d(0xbe)]);}}[_0x56fcfe(0x2dd)](_0x2d0fce,_0x99fe7c,_0x3e3ce9,_0x56ec52){const _0x14ee37=_0x56fcfe,_0x1eaceb=_0x2d0fce['style'][_0x14ee37(0x285)]||'',_0x4a180d=_0x1eaceb[_0x14ee37(0x185)](_0x14ee37(0x1c5))?_0x1eaceb[_0x14ee37(0x133)](/scaleX\([^)]+\)/)[0x0]:'';_0x2d0fce[_0x14ee37(0x27b)][_0x14ee37(0x16e)]=_0x14ee37(0x1dd)+_0x56ec52/0x2/0x3e8+'s\x20linear',_0x2d0fce[_0x14ee37(0x27b)]['transform']=_0x14ee37(0x2ce)+_0x99fe7c+'px)\x20'+_0x4a180d,_0x2d0fce['classList'][_0x14ee37(0x279)](_0x3e3ce9),setTimeout(()=>{const _0x1ffa04=_0x14ee37;_0x2d0fce[_0x1ffa04(0x27b)][_0x1ffa04(0x285)]=_0x4a180d,setTimeout(()=>{const _0x49279e=_0x1ffa04;_0x2d0fce[_0x49279e(0x20d)][_0x49279e(0x27d)](_0x3e3ce9);},_0x56ec52/0x2);},_0x56ec52/0x2);}[_0x56fcfe(0x16c)](_0x116c5d,_0x599274,_0x17a8ef){const _0x5ef58b=_0x56fcfe,_0x571464=_0x116c5d===this['player1']?p1Image:p2Image,_0x175907=_0x116c5d===this[_0x5ef58b(0xd9)]?0x1:-0x1,_0x34bdc8=Math[_0x5ef58b(0x1f6)](0xa,0x2+_0x599274*0.4),_0x4b4ab6=_0x175907*_0x34bdc8,_0x3588e3=_0x5ef58b(0x2b0)+_0x17a8ef;this[_0x5ef58b(0x2dd)](_0x571464,_0x4b4ab6,_0x3588e3,0xc8);}[_0x56fcfe(0x1ec)](_0x50a942){const _0x4baefc=_0x56fcfe,_0x5a62c8=_0x50a942===this[_0x4baefc(0xd9)]?p1Image:p2Image;this[_0x4baefc(0x2dd)](_0x5a62c8,0x0,_0x4baefc(0x137),0xc8);}[_0x56fcfe(0x238)](_0x3bc293,_0x5256bd){const _0xdcd479=_0x56fcfe,_0x48216c=_0x3bc293===this[_0xdcd479(0xd9)]?p1Image:p2Image,_0x3cb034=_0x3bc293===this['player1']?-0x1:0x1,_0x1ce26d=Math[_0xdcd479(0x1f6)](0xa,0x2+_0x5256bd*0.4),_0x35ed65=_0x3cb034*_0x1ce26d;this['applyAnimation'](_0x48216c,_0x35ed65,_0xdcd479(0x282),0xc8);}}function randomChoice(_0x2ee21c){const _0x438586=_0x56fcfe;return _0x2ee21c[Math[_0x438586(0x1c4)](Math['random']()*_0x2ee21c[_0x438586(0x23c)])];}function _0x3067(_0x5a926f,_0x11762b){const _0xbdcbd3=_0xbdcb();return _0x3067=function(_0x3067cc,_0x51f468){_0x3067cc=_0x3067cc-0x97;let _0x5f4499=_0xbdcbd3[_0x3067cc];return _0x5f4499;},_0x3067(_0x5a926f,_0x11762b);}function _0xbdcb(){const _0x555c25=['hyperCube','status','Score\x20Saved:\x20Level\x20','row','Boost\x20applied,\x20damage:\x20','transform\x20','\x20uses\x20Minor\x20Regen,\x20restoring\x20','\x20swaps\x20tiles\x20at\x20(','Player','flipCharacter','Battle\x20Damaged','level','p1-hp','replace','https://www.skulliance.io/staking/icons/','\x27\x22></video>','querySelector','#F44336','Failed\x20to\x20save\x20progress:','\x20characters','animatePowerup','lastStandActive','left',',\x20gameOver=','https://www.skulliance.io/staking/sounds/select.ogg','showProgressPopup:\x20User\x20chose\x20Restart','Regenerate','\x20Score:\x20','powerGem','base','min','top','drops\x20health\x20to\x20','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','cascadeTilesWithoutRender','139032HhIqao','leader','div','HTTP\x20error!\x20Status:\x20','Merdock','json','63rBLucG','removeEventListener','getAssets:\x20Monstrocity\x20fetch\x20error:','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20characters\x20available.\x20Please\x20try\x20another\x20theme.</p>','Game\x20Over','loop','Turn\x20switched\x20to\x20','none','powerup','flatMap','Slime\x20Mind','onerror','classList','touches','initGame:\x20Started\x20with\x20this.currentLevel=','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','currentLevel','showThemeSelect','isNFT','p1-name','.game-logo','\x27s\x20Last\x20Stand\x20mitigates\x20','Monstrocity\x20timeout','backgroundImage','Final\x20level\x20completed!\x20Final\x20score:\x20','initializing','setBackground:\x20themeData=','translate(0px,\x200px)','extension','<p>Health:\x20','handleTouchEnd','forEach','p2-type','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','showCharacterSelect:\x20No\x20characters\x20available,\x20using\x20fallback','Dankle','constructor:\x20initialTheme=','size','theme-options','Found\x20',',\x20Total\x20damage:\x20','\x20HP!','isTouchDevice','Error\x20saving\x20progress:','p1-speed','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','toFixed','preventDefault','ipfsPrefix','progress-modal-buttons','saveProgress','character-select-container','dataset','1848690mRAQpc','totalTiles','animateRecoil','lastChild','max',',\x20currentLevel=','length','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','msMaxTouchPoints','matched','p1-health',',\x20Matches:\x20','\x20+\x2020))\x20*\x20(1\x20+\x20','.png','\x20defeats\x20','\x20size\x20','Slash','usePowerup','reset','endTurn','Heal','speed','clearBoard','Mega\x20Multi-Match!\x20','Tile\x20at\x20(','selectedTile','Game\x20over,\x20skipping\x20cascade\x20resolution','Progress\x20saved:\x20currentLevel=','Minor\x20Regen','points','strength','Player\x201','innerHTML','fallbackUrl','name','ajax/load-monstrocity-progress.php','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','#FFC105','special-attack','createRandomTile','progress-message','Jarhead','updateTheme','createCharacter:\x20config=','toLowerCase','No\x20match,\x20reverting\x20tiles...','Cascade\x20complete,\x20ending\x20turn','isInitialMove:','showCharacterSelect','score','cascadeTiles','\x20goes\x20first!','p2-hp','visible','png','Resume\x20from\x20Level\x20','Animating\x20recoil\x20for\x20defender:','\x20starts\x20at\x20full\x20strength\x20with\x20','playerCharacters','initGame','initBoard:\x20Generated\x20and\x20saved\x20new\x20board\x20for\x20level','\x20steps\x20into\x20the\x20fray\x20with\x20','healthPercentage','getBoundingClientRect',',\x20tiles\x20to\x20clear:','alt','https://www.skulliance.io/staking/images/monstrocity/','add','winner','style','attempts','remove','gameState','4319130hexHdX','Katastrophy','handleMatch','glow-recoil','currentTurn','Response\x20status:','transform','mousedown','Calling\x20checkGameOver\x20from\x20handleMatch','Left','updateTheme:\x20Skipping\x20board\x20render,\x20no\x20active\x20game','Base','url(','handleMouseMove','split','progress-start-fresh',',\x20rows\x20','abs','checkGameOver\x20skipped:\x20gameOver=','getAssets_','Damage\x20from\x20match:\x20','background','boosts\x20health\x20to\x20','second-attack','showCharacterSelect:\x20Character\x20selected:\x20','Game\x20not\x20over,\x20animating\x20attack',',\x20Completions:\x20','px,\x20','first-attack','\x20tiles!','\x20but\x20sharpens\x20tactics\x20to\x20','logo.png','handleTouchMove','POST','cover','\x22\x20alt=\x22','Round\x20Score\x20Formula:\x20(((','race','Opponent',',\x20damage:\x20','</strong></p>','then','\x20uses\x20','timeEnd','Main:\x20Error\x20initializing\x20game:','cascade','\x27s\x20Boost\x20fades.','getItem','Is\x20initial\x20move:\x20','glow-','parentNode','NFT\x20HTTP\x20error!\x20Status:\x20','scaleX(-1)','health','offsetX','removeChild',')\x20/\x20100)\x20*\x20(','error','<img\x20loading=\x22eager\x22\x20src=\x22','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','filter','https://www.skulliance.io/staking/sounds/badmove.ogg','Error\x20in\x20resolveMatches:','tileSizeWithGap','ajax/save-monstrocity-score.php','canMakeMatch','<p>Size:\x20','animating','character-options','game-over','handleTouchStart','theme-option','Texby','ajax/clear-monstrocity-progress.php','Small','touchstart','some','Clearing\x20matched\x20tiles:','muted','translateX(','/monstrocity.png',',\x20loadedScore=','\x20uses\x20Last\x20Stand,\x20dealing\x20','character-option','1191592DHJqTe','Drake','selected',',\x20Grand\x20Total\x20Score:\x20','random','setBackground:\x20Setting\x20background\x20to\x20','checkGameOver\x20started:\x20currentLevel=','imageUrl','img','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','applyAnimation','\x22\x20onerror=\x22this.src=\x27','game-board','type','handleMouseDown','children','clearProgress','orientations','Error\x20saving\x20score:\x20','\x20damage,\x20resulting\x20in\x20','click','p1-tactics','dragDirection','theme','try-again','<p>Speed:\x20','AI\x20Opponent','play','policyId','635SxGZYa','p1-image','mediaType','showCharacterSelect:\x20Rendered\x20','\x20(originally\x20','body','map','warn','loadProgress','maxTouchPoints','saveBoard','Progress\x20saved:\x20Level\x20','theme-select-button','items','Leader','\x20created\x20a\x20match\x20of\x20','addEventListener','push','Horizontal\x20match\x20found\x20at\x20row\x20','p1-powerup','p2-image','roundStats','\x20passes...','\x20due\x20to\x20','message','loser','inline-block','Last\x20Stand\x20applied,\x20mitigated\x20','DOMContentLoaded','pop',',\x20reduced\x20by\x20','Ouchie','#4CAF50','progress-modal','Random','<p>Tactics:\x20',',\x20healthPercentage=','firstChild','Craig','\x20matches:','updateTheme:\x20Board\x20rendered\x20for\x20active\x20game','128904TJDNMu','resolveMatches\x20started,\x20gameOver:','showProgressPopup',',\x20player2.health=','Round\x20Won!\x20Points:\x20','textContent','className','transform\x200.2s\x20ease','boostValue','\x20uses\x20Heal,\x20restoring\x20','player1','Error\x20clearing\x20progress:','aiTurn','Processing\x20match:','isDragging','</p>','...','Game\x20over,\x20skipping\x20cascadeTiles','falling','\x20/\x2056)\x20=\x20','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','backgroundPosition','win','\x20for\x20','Player\x201\x20media\x20clicked','Saving\x20score:\x20level=',',\x20cols\x20','No\x20matches\x20found,\x20returning\x20false','px,\x200)\x20scale(1.05)','Right','catch','updateCharacters_','\x27s\x20orientation\x20flipped\x20to\x20','image','\x20with\x20Score\x20of\x20',',\x20score=','Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)','initBoard','NFT_Unknown_','mouseup','has','player2','\x20health\x20before\x20match:\x20','Goblin\x20Ganger','block','mov',',\x20Match\x20bonus:\x20','p1-size','offsetWidth','\x20health\x20after\x20damage:\x20','gameOver','setBackground','handleGameOverButton\x20started:\x20currentLevel=','mp4','policyIds','sounds','checkMatches','findAIMove','\x20damage!','ipfs','baseImagePath','https://www.skulliance.io/staking/sounds/powergem_created.ogg','init:\x20Starting\x20async\x20initialization','\x20HP','<p>Power-Up:\x20','completed','coordinates','translate(0,\x200)','video','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','success','TRY\x20AGAIN','Large',',\x20Score\x20','hash','Billandar\x20and\x20Ted','progress','slideTiles','change-character','mousemove','time','scrollTop','offsetY','init:\x20Async\x20initialization\x20completed','insertBefore','\x20is\x20not\x20an\x20array,\x20skipping','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20','getAssets:\x20Fetching\x20Monstrocity\x20assets','monstrocity','Score\x20Not\x20Saved:\x20','updateOpponentDisplay','trim','createBoardHash','<p><strong>','Game\x20over,\x20skipping\x20recoil\x20animation','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','flip-p1','orientation','progress-modal-content','playerCharactersConfig','match','Boost\x20Attack','updateHealth','Reset\x20to\x20Level\x201:\x20currentLevel=','glow-power-up','\x20(opponentsConfig[','px,\x200)','checkGameOver\x20completed:\x20currentLevel=','ajax/save-monstrocity-progress.php','Animating\x20powerup','Mandiblus','translate(','playerTurn','board_level_','Monstrocity\x20HTTP\x20error!\x20Status:\x20','IMG','clientY','isArray','createCharacter',')\x20to\x20(','project','battle-log','button','text','clientX','Koipon','\x20-\x20','tagName','Error\x20saving\x20to\x20database:','getElementById','grandTotalScore','indexOf','<p>Strength:\x20','touchmove','getTileFromEvent','showProgressPopup:\x20User\x20chose\x20Resume','\x20damage','game-over-container','renderBoard','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','handleMatch\x20completed,\x20damage\x20dealt:\x20','NFT\x20timeout','\x20but\x20dulls\x20tactics\x20to\x20','#FFA500','Total\x20damage\x20dealt:\x20','find','tile\x20','Vertical\x20match\x20found\x20at\x20col\x20','isCheckingGameOver','VIDEO','targetTile','multiMatch','round','matches',',\x20selected\x20theme=','createElement','init','animateAttack','updateTheme_','transition','NEXT\x20LEVEL','\x27s\x20tactics)','renderBoard:\x20Board\x20not\x20initialized,\x20skipping\x20render','3KOmnuN','Base\x20damage:\x20','ipfsPrefixes','.tile','Game\x20over,\x20skipping\x20endTurn','updateTileSizeWithGap','GET','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','gameTheme','Resumed\x20at\x20Level\x20','Round\x20Score:\x20','resolveMatches','backgroundSize','No\x20progress\x20found\x20or\x20status\x20not\x20success:','touchend',',\x20Health\x20Left:\x20','6634474ofEyWa','onload',',\x20level=','includes','\x27s\x20','application/json','autoplay','last-stand','6mwazLV','\x20damage,\x20but\x20','createDocumentFragment','/staking/icons/skull.png','handleMouseUp','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','\x20damage\x20to\x20','boostActive','tileTypes','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','You\x20Win!','Restart','power-up','ajax/get-monstrocity-assets.php','p2-powerup','log','display','width','\x20(100%\x20bonus\x20for\x20match-5+)','column','Starting\x20Level\x20','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)','Error\x20playing\x20lose\x20sound:','Parsed\x20response:','replaceChild','\x20on\x20','Spydrax','p2-strength','swapPlayerCharacter','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','Preloaded:\x20','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','ontouchstart','showCharacterSelect:\x20this.player1\x20set:\x20','board','Error\x20updating\x20theme\x20assets:','https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg','Minor\x20Régén','renderBoard:\x20Row\x20',',\x20player1.health=','updatePlayerDisplay','appendChild','addEventListeners','countEmptyBelow','Loaded\x20opponent\x20for\x20level\x20','\x22\x20data-project=\x22','translate(0,\x20','\x20/\x20','\x27s\x20Turn','p2-size','src','checkGameOver','Error\x20loading\x20progress:','\x27\x22>','setBackground:\x20Attempting\x20for\x20theme=','element','px)\x20scale(1.05)','Level\x20','floor','scaleX',',\x20isCheckingGameOver=','addEventListeners:\x20Player\x201\x20media\x20clicked','height','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','tactics','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','loadBoard','maxHealth','9186XtXuOv','saveScoreToDatabase','https://ipfs.io/ipfs/','Medium','value','loadBoard:\x20Invalid\x20hash\x20for\x20level\x20','stringify','parse','Main:\x20Game\x20initialized\x20successfully'];_0xbdcb=function(){return _0x555c25;};return _0xbdcb();}function log(_0x292ede){const _0x3ad28e=_0x56fcfe,_0x2acbcb=document['getElementById'](_0x3ad28e(0x148)),_0x3a0118=document[_0x3ad28e(0x16a)]('li');_0x3a0118[_0x3ad28e(0xd4)]=_0x292ede,_0x2acbcb[_0x3ad28e(0x123)](_0x3a0118,_0x2acbcb[_0x3ad28e(0xcb)]),_0x2acbcb[_0x3ad28e(0x98)]['length']>0x32&&_0x2acbcb[_0x3ad28e(0x2b6)](_0x2acbcb[_0x3ad28e(0x239)]),_0x2acbcb[_0x3ad28e(0x120)]=0x0;}const turnIndicator=document[_0x56fcfe(0x150)]('turn-indicator'),p1Name=document[_0x56fcfe(0x150)](_0x56fcfe(0x214)),p1Image=document[_0x56fcfe(0x150)](_0x56fcfe(0xa7)),p1Health=document[_0x56fcfe(0x150)](_0x56fcfe(0x240)),p1Hp=document[_0x56fcfe(0x150)](_0x56fcfe(0x1e4)),p1Strength=document[_0x56fcfe(0x150)]('p1-strength'),p1Speed=document['getElementById'](_0x56fcfe(0x22d)),p1Tactics=document[_0x56fcfe(0x150)](_0x56fcfe(0x9e)),p1Size=document[_0x56fcfe(0x150)](_0x56fcfe(0xfe)),p1Powerup=document['getElementById'](_0x56fcfe(0xb9)),p1Type=document[_0x56fcfe(0x150)]('p1-type'),p2Name=document[_0x56fcfe(0x150)]('p2-name'),p2Image=document[_0x56fcfe(0x150)]('p2-image'),p2Health=document[_0x56fcfe(0x150)]('p2-health'),p2Hp=document[_0x56fcfe(0x150)](_0x56fcfe(0x26a)),p2Strength=document[_0x56fcfe(0x150)](_0x56fcfe(0x1a5)),p2Speed=document[_0x56fcfe(0x150)]('p2-speed'),p2Tactics=document[_0x56fcfe(0x150)]('p2-tactics'),p2Size=document[_0x56fcfe(0x150)](_0x56fcfe(0x1bb)),p2Powerup=document['getElementById'](_0x56fcfe(0x198)),p2Type=document[_0x56fcfe(0x150)](_0x56fcfe(0x221)),battleLog=document[_0x56fcfe(0x150)](_0x56fcfe(0x148)),gameOver=document[_0x56fcfe(0x150)](_0x56fcfe(0x2c4)),assetCache={};async function getAssets(_0x22f9dd){const _0x41bee3=_0x56fcfe;if(assetCache[_0x22f9dd])return console[_0x41bee3(0x199)]('getAssets:\x20Cache\x20hit\x20for\x20'+_0x22f9dd),assetCache[_0x22f9dd];console[_0x41bee3(0x11f)](_0x41bee3(0x292)+_0x22f9dd);let _0x183258=[];try{console['log'](_0x41bee3(0x126));const _0x1197e4=await Promise[_0x41bee3(0x2a4)]([fetch(_0x41bee3(0x197),{'method':_0x41bee3(0x2a0),'headers':{'Content-Type':_0x41bee3(0x187)},'body':JSON[_0x41bee3(0x1d5)]({'theme':'monstrocity'})}),new Promise((_0x3f3be4,_0x34a033)=>setTimeout(()=>_0x34a033(new Error(_0x41bee3(0x217))),0x1388))]);if(!_0x1197e4['ok'])throw new Error(_0x41bee3(0x141)+_0x1197e4['status']);_0x183258=await _0x1197e4[_0x41bee3(0x200)](),!Array[_0x41bee3(0x144)](_0x183258)&&(_0x183258=[_0x183258]),_0x183258=_0x183258['map']((_0x1cc5a7,_0x45b4e3)=>({..._0x1cc5a7,'theme':_0x41bee3(0x127),'name':_0x1cc5a7[_0x41bee3(0x258)]||'Monstrocity_Unknown_'+_0x45b4e3,'strength':_0x1cc5a7['strength']||0x4,'speed':_0x1cc5a7[_0x41bee3(0x24b)]||0x4,'tactics':_0x1cc5a7['tactics']||0x4,'size':_0x1cc5a7[_0x41bee3(0x226)]||_0x41bee3(0x1d2),'type':_0x1cc5a7[_0x41bee3(0x2e0)]||_0x41bee3(0x28a),'powerup':_0x1cc5a7[_0x41bee3(0x209)]||_0x41bee3(0x1f2)}));}catch(_0x3fe034){console[_0x41bee3(0x2b8)](_0x41bee3(0x203),_0x3fe034),_0x183258=[{'name':_0x41bee3(0xcc),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x41bee3(0x1d2),'type':_0x41bee3(0x28a),'powerup':_0x41bee3(0x1f2),'theme':'monstrocity'},{'name':_0x41bee3(0x224),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x41bee3(0x2c9),'type':_0x41bee3(0x28a),'powerup':_0x41bee3(0x24a),'theme':_0x41bee3(0x127)}];}if(_0x22f9dd===_0x41bee3(0x127))return assetCache[_0x22f9dd]=_0x183258,console[_0x41bee3(0x2aa)](_0x41bee3(0x292)+_0x22f9dd),_0x183258;const _0x88d490=themes[_0x41bee3(0x20a)](_0x51d437=>_0x51d437[_0x41bee3(0xb3)])[_0x41bee3(0x160)](_0x17169a=>_0x17169a['value']===_0x22f9dd);if(!_0x88d490)return console['warn']('getAssets:\x20Theme\x20not\x20found:\x20'+_0x22f9dd),assetCache[_0x22f9dd]=_0x183258,console[_0x41bee3(0x2aa)]('getAssets_'+_0x22f9dd),_0x183258;const _0x371926=_0x88d490['policyIds']?_0x88d490['policyIds']['split'](',')[_0x41bee3(0x2bb)](_0x2787c0=>_0x2787c0[_0x41bee3(0x12a)]()):[];if(!_0x371926['length'])return assetCache[_0x22f9dd]=_0x183258,console[_0x41bee3(0x2aa)](_0x41bee3(0x292)+_0x22f9dd),_0x183258;let _0x596938=[];try{const _0x3c5739=_0x371926[_0x41bee3(0xac)]((_0x1d3b08,_0x5527cd)=>({'policyId':_0x1d3b08,'orientation':_0x88d490['orientations']?.[_0x41bee3(0x28d)](',')[_0x5527cd]||'Right','ipfsPrefix':_0x88d490[_0x41bee3(0x174)]?.[_0x41bee3(0x28d)](',')[_0x5527cd]||_0x41bee3(0x1d1)})),_0x216db8=await Promise['race']([fetch('ajax/get-nft-assets.php',{'method':_0x41bee3(0x2a0),'headers':{'Content-Type':_0x41bee3(0x187)},'body':JSON[_0x41bee3(0x1d5)]({'policyIds':_0x3c5739[_0x41bee3(0xac)](_0x5ec3ae=>_0x5ec3ae[_0x41bee3(0xa5)]),'theme':_0x22f9dd})}),new Promise((_0xecea70,_0x314d3a)=>setTimeout(()=>_0x314d3a(new Error(_0x41bee3(0x15c))),0x2710))]);if(!_0x216db8['ok'])throw new Error(_0x41bee3(0x2b2)+_0x216db8['status']);const _0x3e5616=await _0x216db8[_0x41bee3(0x200)]();_0x596938=Array[_0x41bee3(0x144)](_0x3e5616)?_0x3e5616:[_0x3e5616],_0x596938=_0x596938['filter'](_0x40c857=>_0x40c857&&_0x40c857[_0x41bee3(0x258)]&&_0x40c857['ipfs'])[_0x41bee3(0xac)]((_0x3fa9eb,_0x2d6b1f)=>({..._0x3fa9eb,'theme':_0x22f9dd,'name':_0x3fa9eb[_0x41bee3(0x258)]||_0x41bee3(0xf5)+_0x2d6b1f,'strength':_0x3fa9eb[_0x41bee3(0x254)]||0x4,'speed':_0x3fa9eb[_0x41bee3(0x24b)]||0x4,'tactics':_0x3fa9eb[_0x41bee3(0x1ca)]||0x4,'size':_0x3fa9eb[_0x41bee3(0x226)]||'Medium','type':_0x3fa9eb[_0x41bee3(0x2e0)]||_0x41bee3(0x28a),'powerup':_0x3fa9eb[_0x41bee3(0x209)]||_0x41bee3(0x1f2),'policyId':_0x3fa9eb[_0x41bee3(0xa5)]||_0x3c5739[0x0][_0x41bee3(0xa5)],'ipfs':_0x3fa9eb[_0x41bee3(0x10a)]||''}));}catch(_0x14f16e){console[_0x41bee3(0x2b8)](_0x41bee3(0x125)+_0x22f9dd+':',_0x14f16e);}const _0x1f4690=[..._0x183258,..._0x596938];return assetCache[_0x22f9dd]=_0x1f4690,console[_0x41bee3(0x2aa)](_0x41bee3(0x292)+_0x22f9dd),_0x1f4690;}document[_0x56fcfe(0xb6)](_0x56fcfe(0xc2),function(){var _0x4fa742=function(){const _0x3a175d=_0x3067;var _0x244822=localStorage[_0x3a175d(0x2ae)](_0x3a175d(0x17a))||_0x3a175d(0x127);getAssets(_0x244822)[_0x3a175d(0x2a8)](function(_0x20a4df){const _0x1975bb=_0x3a175d;console[_0x1975bb(0x199)]('Main:\x20Player\x20characters\x20loaded:',_0x20a4df);var _0x575464=new MonstrocityMatch3(_0x20a4df,_0x244822);console['log']('Main:\x20Game\x20instance\x20created'),_0x575464[_0x1975bb(0x16b)]()[_0x1975bb(0x2a8)](function(){const _0x21279a=_0x1975bb;console[_0x21279a(0x199)](_0x21279a(0x1d7)),document[_0x21279a(0x1e8)](_0x21279a(0x215))[_0x21279a(0x1bc)]=_0x575464[_0x21279a(0x10b)]+_0x21279a(0x29e);});})[_0x3a175d(0xed)](function(_0x428142){const _0xbfb2a2=_0x3a175d;console[_0xbfb2a2(0x2b8)](_0xbfb2a2(0x2ab),_0x428142);});};_0x4fa742();});
  </script>
</body>
</html>
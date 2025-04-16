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
	  
	  const _0x272063=_0x3e75;(function(_0x46b76d,_0x19cf06){const _0x4bdedb=_0x3e75,_0xaabdfe=_0x46b76d();while(!![]){try{const _0x50de2a=-parseInt(_0x4bdedb(0x17e))/0x1*(parseInt(_0x4bdedb(0x31c))/0x2)+-parseInt(_0x4bdedb(0x28b))/0x3+parseInt(_0x4bdedb(0x190))/0x4*(-parseInt(_0x4bdedb(0x32d))/0x5)+-parseInt(_0x4bdedb(0x262))/0x6+-parseInt(_0x4bdedb(0x28c))/0x7*(parseInt(_0x4bdedb(0x2dd))/0x8)+-parseInt(_0x4bdedb(0x14e))/0x9*(-parseInt(_0x4bdedb(0x2ad))/0xa)+-parseInt(_0x4bdedb(0x243))/0xb*(-parseInt(_0x4bdedb(0x1f5))/0xc);if(_0x50de2a===_0x19cf06)break;else _0xaabdfe['push'](_0xaabdfe['shift']());}catch(_0x20be4d){_0xaabdfe['push'](_0xaabdfe['shift']());}}}(_0x441b,0x3ad86));function showThemeSelect(_0x510aa0){const _0x3e484b=_0x3e75;console[_0x3e484b(0x2ea)]('showThemeSelect');let _0x28170b=document['getElementById'](_0x3e484b(0x17c));const _0x2cb3da=document[_0x3e484b(0x36d)]('character-select-container');_0x28170b[_0x3e484b(0x322)]='\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20';const _0x3ad8eb=document[_0x3e484b(0x36d)](_0x3e484b(0x16d));_0x28170b[_0x3e484b(0x1c3)][_0x3e484b(0x16c)]=_0x3e484b(0x1d4),_0x2cb3da[_0x3e484b(0x1c3)][_0x3e484b(0x16c)]=_0x3e484b(0x356),themes['forEach'](_0x1e65d1=>{const _0x5dbc77=_0x3e484b,_0x237963=document[_0x5dbc77(0x269)]('div');_0x237963['className']=_0x5dbc77(0x250);const _0x286679=document[_0x5dbc77(0x269)]('h3');_0x286679[_0x5dbc77(0x30c)]=_0x1e65d1[_0x5dbc77(0x189)],_0x237963['appendChild'](_0x286679),_0x1e65d1[_0x5dbc77(0x204)][_0x5dbc77(0x2b2)](_0x24f563=>{const _0x549c4a=_0x5dbc77,_0x17efb4=document[_0x549c4a(0x269)](_0x549c4a(0x15d));_0x17efb4[_0x549c4a(0x27e)]='theme-option';if(_0x24f563[_0x549c4a(0x2e9)]){const _0x528bc0=_0x549c4a(0x1f4)+_0x24f563[_0x549c4a(0x277)]+'/monstrocity.png';_0x17efb4[_0x549c4a(0x1c3)][_0x549c4a(0x1a4)]=_0x549c4a(0x345)+_0x528bc0+')';}const _0x47a498='https://www.skulliance.io/staking/images/monstrocity/'+_0x24f563['value']+_0x549c4a(0x25a);_0x17efb4[_0x549c4a(0x322)]=_0x549c4a(0x232)+_0x47a498+_0x549c4a(0x226)+_0x24f563['title']+'\x22\x20data-project=\x22'+_0x24f563['project']+'\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>'+_0x24f563[_0x549c4a(0x13b)]+_0x549c4a(0x287),_0x17efb4[_0x549c4a(0x242)](_0x549c4a(0x177),()=>{const _0x1efced=_0x549c4a,_0x57535=document['getElementById'](_0x1efced(0x179));_0x57535&&(_0x57535['innerHTML']=_0x1efced(0x24b)),_0x28170b['innerHTML']='',_0x28170b[_0x1efced(0x1c3)][_0x1efced(0x16c)]='none',_0x2cb3da[_0x1efced(0x1c3)][_0x1efced(0x16c)]=_0x1efced(0x1d4),_0x510aa0[_0x1efced(0x359)](_0x24f563['value']);}),_0x237963[_0x549c4a(0x2a7)](_0x17efb4);}),_0x3ad8eb[_0x5dbc77(0x2a7)](_0x237963);}),console[_0x3e484b(0x2e8)]('showThemeSelect');}function _0x3e75(_0x2b4b09,_0x4b6dea){const _0x441b44=_0x441b();return _0x3e75=function(_0x3e7589,_0x22131a){_0x3e7589=_0x3e7589-0x136;let _0x4fbcf0=_0x441b44[_0x3e7589];return _0x4fbcf0;},_0x3e75(_0x2b4b09,_0x4b6dea);}const opponentsConfig=[{'name':_0x272063(0x19e),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':_0x272063(0x1c1),'powerup':_0x272063(0x1ac),'theme':_0x272063(0x24a)},{'name':_0x272063(0x1ed),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Large','type':_0x272063(0x1c1),'powerup':'Minor\x20Regen','theme':_0x272063(0x24a)},{'name':_0x272063(0x35f),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x272063(0x317),'type':'Base','powerup':_0x272063(0x1ac),'theme':_0x272063(0x24a)},{'name':_0x272063(0x25e),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Medium','type':_0x272063(0x1c1),'powerup':_0x272063(0x1ac),'theme':_0x272063(0x24a)},{'name':_0x272063(0x36a),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x272063(0x18a),'type':_0x272063(0x1c1),'powerup':_0x272063(0x140),'theme':'monstrocity'},{'name':_0x272063(0x20c),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x272063(0x18a),'type':_0x272063(0x1c1),'powerup':'Regenerate','theme':_0x272063(0x24a)},{'name':_0x272063(0x174),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x272063(0x317),'type':_0x272063(0x1c1),'powerup':_0x272063(0x140),'theme':_0x272063(0x24a)},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':'Base','powerup':_0x272063(0x140),'theme':_0x272063(0x24a)},{'name':_0x272063(0x1b0),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x272063(0x18a),'type':_0x272063(0x1c1),'powerup':'Boost\x20Attack','theme':'monstrocity'},{'name':'Jarhead','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x272063(0x18a),'type':_0x272063(0x1c1),'powerup':_0x272063(0x339),'theme':_0x272063(0x24a)},{'name':'Spydrax','strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x272063(0x317),'type':_0x272063(0x1c1),'powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':'Katastrophy','strength':0x7,'speed':0x7,'tactics':0x7,'size':'Large','type':_0x272063(0x1c1),'powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x272063(0x18a),'type':_0x272063(0x1c1),'powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':'Base','powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':'Craig','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x272063(0x18a),'type':_0x272063(0x1f7),'powerup':_0x272063(0x1ac),'theme':_0x272063(0x24a)},{'name':'Merdock','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x272063(0x27b),'type':_0x272063(0x1f7),'powerup':'Minor\x20Regen','theme':_0x272063(0x24a)},{'name':'Goblin\x20Ganger','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x272063(0x317),'type':_0x272063(0x1f7),'powerup':_0x272063(0x1ac),'theme':'monstrocity'},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':'Medium','type':_0x272063(0x1f7),'powerup':_0x272063(0x22d),'theme':_0x272063(0x24a)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':'Leader','powerup':_0x272063(0x140),'theme':'monstrocity'},{'name':'Koipon','strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':'Leader','powerup':'Regenerate','theme':_0x272063(0x24a)},{'name':'Slime\x20Mind','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x272063(0x317),'type':_0x272063(0x1f7),'powerup':_0x272063(0x140),'theme':_0x272063(0x24a)},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x272063(0x18a),'type':'Leader','powerup':_0x272063(0x140),'theme':_0x272063(0x24a)},{'name':_0x272063(0x1b0),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x272063(0x18a),'type':_0x272063(0x1f7),'powerup':_0x272063(0x339),'theme':_0x272063(0x24a)},{'name':_0x272063(0x235),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x272063(0x18a),'type':'Leader','powerup':'Boost\x20Attack','theme':_0x272063(0x24a)},{'name':'Spydrax','strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x272063(0x1f7),'powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':'Katastrophy','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x272063(0x27b),'type':_0x272063(0x1f7),'powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':_0x272063(0x346),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x272063(0x18a),'type':'Leader','powerup':_0x272063(0x155),'theme':_0x272063(0x24a)},{'name':_0x272063(0x320),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x272063(0x18a),'type':_0x272063(0x1f7),'powerup':_0x272063(0x155),'theme':'monstrocity'}],characterDirections={'Billandar\x20and\x20Ted':_0x272063(0x22a),'Craig':_0x272063(0x22a),'Dankle':_0x272063(0x22a),'Drake':_0x272063(0x343),'Goblin\x20Ganger':_0x272063(0x22a),'Jarhead':_0x272063(0x343),'Katastrophy':'Right','Koipon':'Left','Mandiblus':_0x272063(0x22a),'Merdock':_0x272063(0x22a),'Ouchie':_0x272063(0x22a),'Slime\x20Mind':_0x272063(0x343),'Spydrax':_0x272063(0x343),'Texby':_0x272063(0x22a)};class MonstrocityMatch3{constructor(_0x281d20,_0x11d059){const _0x13dc30=_0x272063;this['isTouchDevice']='ontouchstart'in window||navigator[_0x13dc30(0x2f7)]>0x0||navigator[_0x13dc30(0x1ab)]>0x0,this['width']=0x5,this[_0x13dc30(0x1b3)]=0x5,this[_0x13dc30(0x2af)]=[],this[_0x13dc30(0x1bd)]=null,this[_0x13dc30(0x234)]=![],this['currentTurn']=null,this['player1']=null,this['player2']=null,this[_0x13dc30(0x2e2)]='initializing',this[_0x13dc30(0x34a)]=![],this[_0x13dc30(0x335)]=null,this[_0x13dc30(0x210)]=null,this[_0x13dc30(0x370)]=0x0,this[_0x13dc30(0x24c)]=0x0,this[_0x13dc30(0x1b4)]=0x1,this[_0x13dc30(0x197)]=_0x281d20,this[_0x13dc30(0x21e)]=[],this['isCheckingGameOver']=![],this[_0x13dc30(0x2f8)]=['first-attack',_0x13dc30(0x2da),_0x13dc30(0x1e6),_0x13dc30(0x1da),_0x13dc30(0x354)],this['roundStats']=[],this[_0x13dc30(0x19a)]=0x0;const _0x5592a4=themes[_0x13dc30(0x192)](_0x190e4b=>_0x190e4b[_0x13dc30(0x204)])[_0x13dc30(0x166)](_0x1913b6=>_0x1913b6['value']),_0x32be2c=localStorage[_0x13dc30(0x16a)](_0x13dc30(0x1d3));this[_0x13dc30(0x18b)]=_0x32be2c&&_0x5592a4[_0x13dc30(0x2ed)](_0x32be2c)?_0x32be2c:_0x11d059&&_0x5592a4[_0x13dc30(0x2ed)](_0x11d059)?_0x11d059:_0x13dc30(0x24a),console[_0x13dc30(0x34d)](_0x13dc30(0x313)+_0x11d059+_0x13dc30(0x1d8)+_0x32be2c+',\x20selected\x20theme='+this[_0x13dc30(0x18b)]),this['baseImagePath']=_0x13dc30(0x1f4)+this[_0x13dc30(0x18b)]+'/',this[_0x13dc30(0x162)]={'match':new Audio(_0x13dc30(0x1bf)),'cascade':new Audio(_0x13dc30(0x1bf)),'badMove':new Audio(_0x13dc30(0x2f4)),'gameOver':new Audio(_0x13dc30(0x14a)),'reset':new Audio(_0x13dc30(0x2b7)),'loss':new Audio('https://www.skulliance.io/staking/sounds/skullcoinlose.ogg'),'win':new Audio(_0x13dc30(0x36e)),'finalWin':new Audio(_0x13dc30(0x1ec)),'powerGem':new Audio('https://www.skulliance.io/staking/sounds/powergem_created.ogg'),'hyperCube':new Audio(_0x13dc30(0x1e3)),'multiMatch':new Audio('https://www.skulliance.io/staking/sounds/speedmatch1.ogg')},this[_0x13dc30(0x352)](),this['addEventListeners']();}async[_0x272063(0x1ea)](){const _0x445076=_0x272063;console[_0x445076(0x34d)](_0x445076(0x212)),this[_0x445076(0x21e)]=this[_0x445076(0x197)][_0x445076(0x166)](_0x18be0d=>this[_0x445076(0x2b4)](_0x18be0d)),await this['showCharacterSelect'](!![]);const _0x13f7ef=await this['loadProgress'](),{loadedLevel:_0x179fc4,loadedScore:_0x181ee9,hasProgress:_0xde328e}=_0x13f7ef;if(_0xde328e){console[_0x445076(0x34d)]('init:\x20Prompting\x20with\x20loadedLevel='+_0x179fc4+',\x20loadedScore='+_0x181ee9);const _0x2daf1e=await this[_0x445076(0x278)](_0x179fc4,_0x181ee9);_0x2daf1e?(this[_0x445076(0x1b4)]=_0x179fc4,this[_0x445076(0x19a)]=_0x181ee9,log(_0x445076(0x326)+this[_0x445076(0x1b4)]+_0x445076(0x296)+this[_0x445076(0x19a)])):(this[_0x445076(0x1b4)]=0x1,this[_0x445076(0x19a)]=0x0,await this['clearProgress'](),log(_0x445076(0x218)));}else this['currentLevel']=0x1,this['grandTotalScore']=0x0,log(_0x445076(0x33d));console[_0x445076(0x34d)](_0x445076(0x301));}[_0x272063(0x2e3)](){const _0x402133=_0x272063;console[_0x402133(0x34d)]('setBackground:\x20Attempting\x20for\x20theme='+this[_0x402133(0x18b)]);const _0x8ac3a4=themes[_0x402133(0x192)](_0x419fe6=>_0x419fe6['items'])[_0x402133(0x199)](_0x172c16=>_0x172c16['value']===this['theme']);console['log']('setBackground:\x20themeData=',_0x8ac3a4);const _0x14aae5='https://www.skulliance.io/staking/images/monstrocity/'+this[_0x402133(0x18b)]+_0x402133(0x203);console[_0x402133(0x34d)](_0x402133(0x1ee)+_0x14aae5),_0x8ac3a4&&_0x8ac3a4['background']?(document['body'][_0x402133(0x1c3)][_0x402133(0x1a4)]=_0x402133(0x345)+_0x14aae5+')',document[_0x402133(0x1c8)][_0x402133(0x1c3)][_0x402133(0x2d3)]=_0x402133(0x327),document[_0x402133(0x1c8)]['style'][_0x402133(0x13a)]='center'):document['body'][_0x402133(0x1c3)][_0x402133(0x1a4)]=_0x402133(0x356);}[_0x272063(0x359)](_0x194391){const _0x50642f=_0x272063;if(updatePending){console[_0x50642f(0x34d)](_0x50642f(0x141));return;}updatePending=!![],console[_0x50642f(0x2ea)](_0x50642f(0x21f)+_0x194391);const _0xab15a=this;this['theme']=_0x194391,this[_0x50642f(0x1f2)]=_0x50642f(0x1f4)+this[_0x50642f(0x18b)]+'/',localStorage[_0x50642f(0x376)](_0x50642f(0x1d3),this[_0x50642f(0x18b)]),this[_0x50642f(0x2e3)](),document[_0x50642f(0x2ca)](_0x50642f(0x282))[_0x50642f(0x161)]=this[_0x50642f(0x1f2)]+_0x50642f(0x281);const _0x37407e=document['getElementById'](_0x50642f(0x179));_0x37407e&&(_0x37407e[_0x50642f(0x322)]=_0x50642f(0x24b)),getAssets(this[_0x50642f(0x18b)])[_0x50642f(0x35a)](function(_0x4f276b){const _0x2fcce6=_0x50642f;console[_0x2fcce6(0x2ea)](_0x2fcce6(0x29b)+_0x194391),_0xab15a[_0x2fcce6(0x197)]=_0x4f276b,_0xab15a[_0x2fcce6(0x21e)]=[],_0x4f276b[_0x2fcce6(0x2b2)](_0x5a31a4=>{const _0x19f3fc=_0x2fcce6,_0x38fc10=_0xab15a[_0x19f3fc(0x2b4)](_0x5a31a4);if(_0x38fc10[_0x19f3fc(0x357)]===_0x19f3fc(0x1ef)){const _0x42e888=new Image();_0x42e888[_0x19f3fc(0x161)]=_0x38fc10[_0x19f3fc(0x1f8)],_0x42e888[_0x19f3fc(0x260)]=()=>console[_0x19f3fc(0x34d)]('Preloaded:\x20'+_0x38fc10[_0x19f3fc(0x1f8)]),_0x42e888['onerror']=()=>console[_0x19f3fc(0x34d)](_0x19f3fc(0x148)+_0x38fc10[_0x19f3fc(0x1f8)]);}_0xab15a['playerCharacters']['push'](_0x38fc10);});if(_0xab15a[_0x2fcce6(0x15e)]){const _0xd67905=_0xab15a[_0x2fcce6(0x197)][_0x2fcce6(0x199)](_0x2bd019=>_0x2bd019[_0x2fcce6(0x2df)]===_0xab15a[_0x2fcce6(0x15e)]['name'])||_0xab15a[_0x2fcce6(0x197)][0x0];_0xab15a[_0x2fcce6(0x15e)]=_0xab15a[_0x2fcce6(0x2b4)](_0xd67905),_0xab15a[_0x2fcce6(0x2bc)]();}_0xab15a['player2']&&(_0xab15a['player2']=_0xab15a['createCharacter'](opponentsConfig[_0xab15a[_0x2fcce6(0x1b4)]-0x1]),_0xab15a[_0x2fcce6(0x1f9)]());if(_0xab15a[_0x2fcce6(0x15e)]&&_0xab15a[_0x2fcce6(0x2e2)]!=='initializing'){const _0x11541b=document[_0x2fcce6(0x25c)](_0x2fcce6(0x295));_0x11541b[_0x2fcce6(0x2b2)](_0x24f94c=>{const _0x16bcd9=_0x2fcce6;_0x24f94c[_0x16bcd9(0x2d2)]('mousedown',_0xab15a[_0x16bcd9(0x1b5)]),_0x24f94c[_0x16bcd9(0x2d2)](_0x16bcd9(0x158),_0xab15a[_0x16bcd9(0x2a6)]);}),_0xab15a[_0x2fcce6(0x1dd)](),console[_0x2fcce6(0x34d)](_0x2fcce6(0x324));}else console[_0x2fcce6(0x34d)]('updateTheme:\x20Skipping\x20board\x20render,\x20no\x20active\x20game');_0xab15a['player1']&&(_0xab15a[_0x2fcce6(0x34a)]=![],_0xab15a[_0x2fcce6(0x1bd)]=null,_0xab15a[_0x2fcce6(0x335)]=null,_0xab15a[_0x2fcce6(0x2e2)]=_0xab15a[_0x2fcce6(0x15b)]===_0xab15a[_0x2fcce6(0x15e)]?'playerTurn':_0x2fcce6(0x1ba));const _0x2c04b4=document[_0x2fcce6(0x36d)]('character-select-container');_0x2c04b4[_0x2fcce6(0x1c3)]['display']=_0x2fcce6(0x1d4),_0xab15a['showCharacterSelect'](_0xab15a['player1']===null),console[_0x2fcce6(0x2e8)](_0x2fcce6(0x29b)+_0x194391),console[_0x2fcce6(0x2e8)](_0x2fcce6(0x21f)+_0x194391),updatePending=![];})[_0x50642f(0x1e0)](function(_0x578212){const _0x791681=_0x50642f;console['error']('Error\x20updating\x20theme\x20assets:',_0x578212),_0xab15a['playerCharactersConfig']=[{'name':_0x791681(0x19e),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x791681(0x18a),'type':_0x791681(0x1c1),'powerup':_0x791681(0x140),'theme':_0x791681(0x24a)},{'name':_0x791681(0x1b0),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x791681(0x317),'type':'Base','powerup':_0x791681(0x155),'theme':'monstrocity'}],_0xab15a[_0x791681(0x21e)]=_0xab15a['playerCharactersConfig'][_0x791681(0x166)](_0x29505a=>_0xab15a[_0x791681(0x2b4)](_0x29505a));const _0x3712ca=document[_0x791681(0x36d)](_0x791681(0x209));_0x3712ca[_0x791681(0x1c3)]['display']=_0x791681(0x1d4),_0xab15a[_0x791681(0x323)](_0xab15a[_0x791681(0x15e)]===null),console['timeEnd'](_0x791681(0x21f)+_0x194391),updatePending=![];});}async[_0x272063(0x1e9)](){const _0x351d0c=_0x272063,_0x402a8e={'currentLevel':this['currentLevel'],'grandTotalScore':this[_0x351d0c(0x19a)]};console['log'](_0x351d0c(0x1c5),_0x402a8e);try{const _0x3884fa=await fetch(_0x351d0c(0x29f),{'method':_0x351d0c(0x2fe),'headers':{'Content-Type':_0x351d0c(0x2cd)},'body':JSON[_0x351d0c(0x1a0)](_0x402a8e)});console[_0x351d0c(0x34d)](_0x351d0c(0x30f),_0x3884fa['status']);const _0x4c0954=await _0x3884fa[_0x351d0c(0x240)]();console[_0x351d0c(0x34d)](_0x351d0c(0x2c0),_0x4c0954);if(!_0x3884fa['ok'])throw new Error(_0x351d0c(0x251)+_0x3884fa[_0x351d0c(0x138)]);const _0x79f819=JSON[_0x351d0c(0x305)](_0x4c0954);console['log'](_0x351d0c(0x2e1),_0x79f819),_0x79f819[_0x351d0c(0x138)]===_0x351d0c(0x1c0)?log(_0x351d0c(0x341)+this[_0x351d0c(0x1b4)]):console[_0x351d0c(0x1a3)](_0x351d0c(0x2a1),_0x79f819['message']);}catch(_0x46d700){console[_0x351d0c(0x1a3)](_0x351d0c(0x178),_0x46d700);}}async['loadProgress'](){const _0x3459db=_0x272063;try{console[_0x3459db(0x34d)](_0x3459db(0x290));const _0x5a194c=await fetch(_0x3459db(0x1c4),{'method':_0x3459db(0x2a0),'headers':{'Content-Type':_0x3459db(0x2cd)}});console[_0x3459db(0x34d)](_0x3459db(0x30f),_0x5a194c[_0x3459db(0x138)]);if(!_0x5a194c['ok'])throw new Error(_0x3459db(0x251)+_0x5a194c['status']);const _0x43e41e=await _0x5a194c[_0x3459db(0x14d)]();console[_0x3459db(0x34d)]('Parsed\x20response:',_0x43e41e);if(_0x43e41e[_0x3459db(0x138)]===_0x3459db(0x1c0)&&_0x43e41e['progress']){const _0x57dc43=_0x43e41e[_0x3459db(0x274)];return{'loadedLevel':_0x57dc43[_0x3459db(0x1b4)]||0x1,'loadedScore':_0x57dc43[_0x3459db(0x19a)]||0x0,'hasProgress':!![]};}else return console[_0x3459db(0x34d)]('No\x20progress\x20found\x20or\x20status\x20not\x20success:',_0x43e41e),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x138201){return console[_0x3459db(0x1a3)](_0x3459db(0x163),_0x138201),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async['clearProgress'](){const _0x479dee=_0x272063;try{const _0x3579c7=await fetch(_0x479dee(0x2c1),{'method':_0x479dee(0x2fe),'headers':{'Content-Type':'application/json'}});if(!_0x3579c7['ok'])throw new Error(_0x479dee(0x251)+_0x3579c7[_0x479dee(0x138)]);const _0x124419=await _0x3579c7['json']();_0x124419[_0x479dee(0x138)]==='success'&&(this[_0x479dee(0x1b4)]=0x1,this['grandTotalScore']=0x0,log(_0x479dee(0x21c)));}catch(_0x351d0b){console[_0x479dee(0x1a3)](_0x479dee(0x21a),_0x351d0b);}}[_0x272063(0x352)](){const _0x2d30cd=_0x272063,_0x2ddce1=document[_0x2d30cd(0x36d)](_0x2d30cd(0x1af)),_0x14aa9f=_0x2ddce1['offsetWidth']||0x12c;this[_0x2d30cd(0x236)]=(_0x14aa9f-0.5*(this[_0x2d30cd(0x29c)]-0x1))/this[_0x2d30cd(0x29c)];}['createCharacter'](_0x19558c){const _0x101494=_0x272063;console[_0x101494(0x34d)](_0x101494(0x1fa),_0x19558c);var _0x2dea33,_0x27c20e,_0x4eb727=_0x101494(0x22a),_0x1a51ed=![],_0x2ff504=_0x101494(0x1ef);const _0x571a85=themes[_0x101494(0x192)](_0xfb29a7=>_0xfb29a7['items'])['find'](_0x47dd13=>_0x47dd13[_0x101494(0x277)]===this['theme']),_0xdef0a6=_0x571a85?.['extension']||_0x101494(0x1a8),_0x21499f=[_0x101494(0x14b),_0x101494(0x2b3)];if(_0x19558c[_0x101494(0x20e)]&&_0x19558c[_0x101494(0x24f)]){_0x1a51ed=!![];var _0x44d35f=document['querySelector'](_0x101494(0x2e5)+_0x19558c[_0x101494(0x18b)]+'\x22]'),_0x4d9e4d={'orientation':'Right','ipfsPrefix':_0x101494(0x1a7)};if(_0x44d35f){var _0x2713f2=_0x44d35f[_0x101494(0x1fb)][_0x101494(0x146)]?_0x44d35f[_0x101494(0x1fb)][_0x101494(0x146)][_0x101494(0x2d0)](',')['filter'](function(_0x4c2a5b){const _0x913044=_0x101494;return _0x4c2a5b[_0x913044(0x336)]();}):[],_0x1405a1=_0x44d35f[_0x101494(0x1fb)][_0x101494(0x1aa)]?_0x44d35f[_0x101494(0x1fb)][_0x101494(0x1aa)]['split'](',')[_0x101494(0x288)](function(_0xc617f1){return _0xc617f1['trim']();}):[],_0x2b5a91=_0x44d35f[_0x101494(0x1fb)]['ipfsPrefixes']?_0x44d35f[_0x101494(0x1fb)]['ipfsPrefixes'][_0x101494(0x2d0)](',')[_0x101494(0x288)](function(_0x117559){const _0x21fff3=_0x101494;return _0x117559[_0x21fff3(0x336)]();}):[],_0x49f975=_0x2713f2[_0x101494(0x263)](_0x19558c['policyId']);_0x49f975!==-0x1&&(_0x4d9e4d={'orientation':_0x1405a1[_0x101494(0x196)]===0x1?_0x1405a1[0x0]:_0x1405a1[_0x49f975]||_0x101494(0x343),'ipfsPrefix':_0x2b5a91[_0x101494(0x196)]===0x1?_0x2b5a91[0x0]:_0x2b5a91[_0x49f975]||'https://ipfs.io/ipfs/'});}_0x4d9e4d[_0x101494(0x147)]==='Random'?_0x4eb727=Math['random']()<0.5?_0x101494(0x22a):_0x101494(0x343):_0x4eb727=_0x4d9e4d[_0x101494(0x147)];_0x27c20e=_0x4d9e4d[_0x101494(0x237)]+_0x19558c['ipfs'];const _0x5cd0d6=_0x27c20e[_0x101494(0x2d0)]('.')[_0x101494(0x28d)]()['toLowerCase']();_0x21499f['includes'](_0x5cd0d6)&&(_0x2ff504=_0x101494(0x268));}else{switch(_0x19558c[_0x101494(0x23d)]){case _0x101494(0x1c1):_0x2dea33='base';break;case _0x101494(0x1f7):_0x2dea33=_0x101494(0x1ff);break;case _0x101494(0x350):_0x2dea33=_0x101494(0x18e);break;default:_0x2dea33=_0x101494(0x286);}_0x27c20e=this['baseImagePath']+_0x2dea33+'/'+_0x19558c[_0x101494(0x2df)]['toLowerCase']()[_0x101494(0x1d7)](/ /g,'-')+'.'+_0xdef0a6,_0x4eb727=characterDirections[_0x19558c[_0x101494(0x2df)]]||_0x101494(0x22a),_0x21499f[_0x101494(0x2ed)](_0xdef0a6[_0x101494(0x18f)]())&&(_0x2ff504=_0x101494(0x268));}var _0x581355;switch(_0x19558c[_0x101494(0x23d)]){case _0x101494(0x1f7):_0x581355=0x64;break;case _0x101494(0x350):_0x581355=0x46;break;case _0x101494(0x1c1):default:_0x581355=0x55;}var _0x5f4c8c=0x1,_0x2b360d=0x0;switch(_0x19558c[_0x101494(0x19f)]){case _0x101494(0x27b):_0x5f4c8c=1.2,_0x2b360d=_0x19558c['tactics']>0x1?-0x2:0x0;break;case _0x101494(0x317):_0x5f4c8c=0.8,_0x2b360d=_0x19558c[_0x101494(0x156)]<0x6?0x2:0x7-_0x19558c[_0x101494(0x156)];break;case _0x101494(0x18a):_0x5f4c8c=0x1,_0x2b360d=0x0;break;}var _0x680a30=Math[_0x101494(0x22e)](_0x581355*_0x5f4c8c),_0xf928a6=Math[_0x101494(0x17b)](0x1,Math[_0x101494(0x2ec)](0x7,_0x19558c['tactics']+_0x2b360d));return{'name':_0x19558c[_0x101494(0x2df)],'type':_0x19558c[_0x101494(0x23d)],'strength':_0x19558c[_0x101494(0x206)],'speed':_0x19558c[_0x101494(0x362)],'tactics':_0xf928a6,'size':_0x19558c[_0x101494(0x19f)],'powerup':_0x19558c[_0x101494(0x1e5)],'health':_0x680a30,'maxHealth':_0x680a30,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x27c20e,'orientation':_0x4eb727,'isNFT':_0x1a51ed,'mediaType':_0x2ff504};}[_0x272063(0x17d)](_0x15fcda,_0xeca7e3,_0x4dcb0b=![]){const _0x13fd3c=_0x272063;_0x15fcda[_0x13fd3c(0x147)]===_0x13fd3c(0x22a)?(_0x15fcda[_0x13fd3c(0x147)]=_0x13fd3c(0x343),_0xeca7e3[_0x13fd3c(0x1c3)]['transform']=_0x4dcb0b?_0x13fd3c(0x2f6):_0x13fd3c(0x356)):(_0x15fcda[_0x13fd3c(0x147)]=_0x13fd3c(0x22a),_0xeca7e3[_0x13fd3c(0x1c3)]['transform']=_0x4dcb0b?'none':'scaleX(-1)'),log(_0x15fcda['name']+_0x13fd3c(0x201)+_0x15fcda[_0x13fd3c(0x147)]+'!');}['showCharacterSelect'](_0x535cd4){const _0x10bbec=_0x272063;console[_0x10bbec(0x2ea)]('showCharacterSelect');const _0x59db8d=document[_0x10bbec(0x36d)](_0x10bbec(0x209)),_0x2fae8d=document[_0x10bbec(0x36d)](_0x10bbec(0x179));_0x2fae8d[_0x10bbec(0x322)]='',_0x59db8d[_0x10bbec(0x1c3)]['display']=_0x10bbec(0x1d4);if(!this[_0x10bbec(0x21e)]||this['playerCharacters'][_0x10bbec(0x196)]===0x0){console['warn']('showCharacterSelect:\x20No\x20characters\x20available,\x20using\x20fallback'),_0x2fae8d['innerHTML']=_0x10bbec(0x15c),console[_0x10bbec(0x2e8)](_0x10bbec(0x323));return;}document[_0x10bbec(0x36d)](_0x10bbec(0x302))['onclick']=()=>{showThemeSelect(this);};const _0x4d8d99=document[_0x10bbec(0x36b)]();this[_0x10bbec(0x21e)][_0x10bbec(0x2b2)](_0x53cbf5=>{const _0x585e21=_0x10bbec,_0x15cd03=document[_0x585e21(0x269)]('div');_0x15cd03[_0x585e21(0x27e)]=_0x585e21(0x35e),_0x15cd03[_0x585e21(0x322)]=_0x53cbf5[_0x585e21(0x357)]===_0x585e21(0x268)?_0x585e21(0x2ba)+_0x53cbf5['imageUrl']+_0x585e21(0x32e)+_0x53cbf5[_0x585e21(0x2df)]+_0x585e21(0x267)+(_0x585e21(0x2d4)+_0x53cbf5[_0x585e21(0x2df)]+_0x585e21(0x1ad))+(_0x585e21(0x151)+_0x53cbf5[_0x585e21(0x23d)]+_0x585e21(0x28f))+(_0x585e21(0x143)+_0x53cbf5[_0x585e21(0x1fd)]+_0x585e21(0x28f))+(_0x585e21(0x254)+_0x53cbf5['strength']+'</p>')+(_0x585e21(0x1ce)+_0x53cbf5[_0x585e21(0x362)]+'</p>')+('<p>Tactics:\x20'+_0x53cbf5[_0x585e21(0x156)]+_0x585e21(0x28f))+(_0x585e21(0x321)+_0x53cbf5['size']+_0x585e21(0x28f))+(_0x585e21(0x176)+_0x53cbf5[_0x585e21(0x1e5)]+'</p>'):_0x585e21(0x340)+_0x53cbf5[_0x585e21(0x1f8)]+_0x585e21(0x226)+_0x53cbf5[_0x585e21(0x2df)]+_0x585e21(0x23f)+('<p><strong>'+_0x53cbf5['name']+_0x585e21(0x1ad))+(_0x585e21(0x151)+_0x53cbf5[_0x585e21(0x23d)]+_0x585e21(0x28f))+('<p>Health:\x20'+_0x53cbf5[_0x585e21(0x1fd)]+_0x585e21(0x28f))+(_0x585e21(0x254)+_0x53cbf5[_0x585e21(0x206)]+_0x585e21(0x28f))+(_0x585e21(0x1ce)+_0x53cbf5[_0x585e21(0x362)]+_0x585e21(0x28f))+('<p>Tactics:\x20'+_0x53cbf5[_0x585e21(0x156)]+'</p>')+(_0x585e21(0x321)+_0x53cbf5['size']+_0x585e21(0x28f))+(_0x585e21(0x176)+_0x53cbf5[_0x585e21(0x1e5)]+_0x585e21(0x28f)),_0x15cd03[_0x585e21(0x242)](_0x585e21(0x177),()=>{const _0x2913af=_0x585e21;console['log'](_0x2913af(0x2f0)+_0x53cbf5[_0x2913af(0x2df)]),_0x59db8d['style']['display']=_0x2913af(0x356),_0x535cd4?(this[_0x2913af(0x15e)]={..._0x53cbf5},console[_0x2913af(0x34d)](_0x2913af(0x2ff)+this[_0x2913af(0x15e)][_0x2913af(0x2df)]),this['initGame']()):this[_0x2913af(0x152)](_0x53cbf5);}),_0x4d8d99['appendChild'](_0x15cd03);}),_0x2fae8d['appendChild'](_0x4d8d99),console[_0x10bbec(0x34d)](_0x10bbec(0x231)+this['playerCharacters']['length']+'\x20characters'),console[_0x10bbec(0x2e8)]('showCharacterSelect');}['swapPlayerCharacter'](_0x1df18a){const _0x42bb1f=_0x272063,_0xb7627c=this['player1'][_0x42bb1f(0x16b)],_0x423a1b=this[_0x42bb1f(0x15e)]['maxHealth'],_0x40f5a3={..._0x1df18a},_0x52170d=Math[_0x42bb1f(0x2ec)](0x1,_0xb7627c/_0x423a1b);_0x40f5a3['health']=Math[_0x42bb1f(0x22e)](_0x40f5a3[_0x42bb1f(0x1fd)]*_0x52170d),_0x40f5a3[_0x42bb1f(0x16b)]=Math[_0x42bb1f(0x17b)](0x0,Math[_0x42bb1f(0x2ec)](_0x40f5a3['maxHealth'],_0x40f5a3[_0x42bb1f(0x16b)])),_0x40f5a3[_0x42bb1f(0x164)]=![],_0x40f5a3['boostValue']=0x0,_0x40f5a3[_0x42bb1f(0x291)]=![],this[_0x42bb1f(0x15e)]=_0x40f5a3,this[_0x42bb1f(0x2bc)](),this['updateHealth'](this[_0x42bb1f(0x15e)]),log(this[_0x42bb1f(0x15e)]['name']+_0x42bb1f(0x2b5)+this[_0x42bb1f(0x15e)]['health']+'/'+this[_0x42bb1f(0x15e)]['maxHealth']+_0x42bb1f(0x32c)),this[_0x42bb1f(0x15b)]=this[_0x42bb1f(0x15e)][_0x42bb1f(0x362)]>this['player2'][_0x42bb1f(0x362)]?this['player1']:this[_0x42bb1f(0x31b)]['speed']>this['player1'][_0x42bb1f(0x362)]?this[_0x42bb1f(0x31b)]:this[_0x42bb1f(0x15e)][_0x42bb1f(0x206)]>=this[_0x42bb1f(0x31b)][_0x42bb1f(0x206)]?this[_0x42bb1f(0x15e)]:this[_0x42bb1f(0x31b)],turnIndicator[_0x42bb1f(0x30c)]=_0x42bb1f(0x1cd)+this[_0x42bb1f(0x1b4)]+_0x42bb1f(0x276)+(this[_0x42bb1f(0x15b)]===this[_0x42bb1f(0x15e)]?_0x42bb1f(0x329):_0x42bb1f(0x367))+_0x42bb1f(0x252),this[_0x42bb1f(0x15b)]===this['player2']&&this[_0x42bb1f(0x2e2)]!==_0x42bb1f(0x234)&&setTimeout(()=>this[_0x42bb1f(0x1ba)](),0x3e8);}['showProgressPopup'](_0x5aa969,_0x44bd09){const _0x34b0a0=_0x272063;return console[_0x34b0a0(0x34d)](_0x34b0a0(0x1f0)+_0x5aa969+_0x34b0a0(0x303)+_0x44bd09),new Promise(_0x453ef5=>{const _0x1f3861=_0x34b0a0,_0x1bdf9c=document[_0x1f3861(0x269)](_0x1f3861(0x15d));_0x1bdf9c['id']=_0x1f3861(0x15f),_0x1bdf9c[_0x1f3861(0x27e)]=_0x1f3861(0x15f);const _0x1c6414=document['createElement'](_0x1f3861(0x15d));_0x1c6414['className']=_0x1f3861(0x2bb);const _0x115c7d=document[_0x1f3861(0x269)]('p');_0x115c7d['id']=_0x1f3861(0x23b),_0x115c7d['textContent']=_0x1f3861(0x224)+_0x5aa969+_0x1f3861(0x17f)+_0x44bd09+'?',_0x1c6414[_0x1f3861(0x2a7)](_0x115c7d);const _0x310e16=document[_0x1f3861(0x269)](_0x1f3861(0x15d));_0x310e16['className']=_0x1f3861(0x1d6);const _0x3404b5=document[_0x1f3861(0x269)]('button');_0x3404b5['id']=_0x1f3861(0x23a),_0x3404b5[_0x1f3861(0x30c)]=_0x1f3861(0x364),_0x310e16[_0x1f3861(0x2a7)](_0x3404b5);const _0x39d16e=document['createElement'](_0x1f3861(0x30d));_0x39d16e['id']=_0x1f3861(0x219),_0x39d16e[_0x1f3861(0x30c)]=_0x1f3861(0x280),_0x310e16[_0x1f3861(0x2a7)](_0x39d16e),_0x1c6414['appendChild'](_0x310e16),_0x1bdf9c[_0x1f3861(0x2a7)](_0x1c6414),document[_0x1f3861(0x1c8)][_0x1f3861(0x2a7)](_0x1bdf9c),_0x1bdf9c[_0x1f3861(0x1c3)][_0x1f3861(0x16c)]=_0x1f3861(0x372);const _0x370ec7=()=>{const _0x2fcfce=_0x1f3861;console[_0x2fcfce(0x34d)](_0x2fcfce(0x2a2)),_0x1bdf9c['style']['display']=_0x2fcfce(0x356),document['body'][_0x2fcfce(0x2ae)](_0x1bdf9c),_0x3404b5['removeEventListener'](_0x2fcfce(0x177),_0x370ec7),_0x39d16e[_0x2fcfce(0x2d2)](_0x2fcfce(0x177),_0x49c140),_0x453ef5(!![]);},_0x49c140=()=>{const _0xd0ec69=_0x1f3861;console[_0xd0ec69(0x34d)](_0xd0ec69(0x247)),_0x1bdf9c[_0xd0ec69(0x1c3)][_0xd0ec69(0x16c)]='none',document[_0xd0ec69(0x1c8)][_0xd0ec69(0x2ae)](_0x1bdf9c),_0x3404b5['removeEventListener']('click',_0x370ec7),_0x39d16e[_0xd0ec69(0x2d2)](_0xd0ec69(0x177),_0x49c140),_0x453ef5(![]);};_0x3404b5[_0x1f3861(0x242)]('click',_0x370ec7),_0x39d16e['addEventListener']('click',_0x49c140);});}['initGame'](){const _0x933709=_0x272063;var _0x1936b5=this;console[_0x933709(0x34d)]('initGame:\x20Started\x20with\x20this.currentLevel='+this[_0x933709(0x1b4)]);var _0x4ee70a=document[_0x933709(0x2ca)](_0x933709(0x2c4)),_0x3fd2d1=document[_0x933709(0x36d)](_0x933709(0x1af));_0x4ee70a[_0x933709(0x1c3)][_0x933709(0x16c)]=_0x933709(0x1d4),_0x3fd2d1[_0x933709(0x1c3)]['visibility']=_0x933709(0x2d6),this['setBackground'](),this[_0x933709(0x162)]['reset']['play'](),log(_0x933709(0x172)+this[_0x933709(0x1b4)]+_0x933709(0x1e7)),this[_0x933709(0x31b)]=this[_0x933709(0x2b4)](opponentsConfig[this['currentLevel']-0x1]),console[_0x933709(0x34d)](_0x933709(0x373)+this[_0x933709(0x1b4)]+':\x20'+this['player2'][_0x933709(0x2df)]+_0x933709(0x332)+(this[_0x933709(0x1b4)]-0x1)+'])'),this[_0x933709(0x15e)][_0x933709(0x16b)]=this[_0x933709(0x15e)][_0x933709(0x1fd)],this[_0x933709(0x15b)]=this[_0x933709(0x15e)][_0x933709(0x362)]>this[_0x933709(0x31b)][_0x933709(0x362)]?this[_0x933709(0x15e)]:this[_0x933709(0x31b)][_0x933709(0x362)]>this[_0x933709(0x15e)][_0x933709(0x362)]?this[_0x933709(0x31b)]:this[_0x933709(0x15e)][_0x933709(0x206)]>=this[_0x933709(0x31b)]['strength']?this[_0x933709(0x15e)]:this[_0x933709(0x31b)],this[_0x933709(0x2e2)]=_0x933709(0x153),this[_0x933709(0x234)]=![],this[_0x933709(0x2f1)]=[];const _0x52c7f8=document[_0x933709(0x36d)]('p1-image'),_0x548a26=document[_0x933709(0x36d)]('p2-image');if(_0x52c7f8)_0x52c7f8['classList'][_0x933709(0x34f)](_0x933709(0x2db),'loser');if(_0x548a26)_0x548a26[_0x933709(0x14c)][_0x933709(0x34f)](_0x933709(0x2db),'loser');this[_0x933709(0x2bc)](),this[_0x933709(0x1f9)]();if(_0x52c7f8)_0x52c7f8['style']['transform']=this['player1']['orientation']==='Left'?'scaleX(-1)':'none';if(_0x548a26)_0x548a26[_0x933709(0x1c3)]['transform']=this[_0x933709(0x31b)][_0x933709(0x147)]===_0x933709(0x343)?_0x933709(0x2f6):'none';this[_0x933709(0x223)](this[_0x933709(0x15e)]),this[_0x933709(0x223)](this[_0x933709(0x31b)]),battleLog[_0x933709(0x322)]='',gameOver[_0x933709(0x30c)]='',this[_0x933709(0x15e)][_0x933709(0x19f)]!=='Medium'&&log(this[_0x933709(0x15e)][_0x933709(0x2df)]+_0x933709(0x271)+this['player1'][_0x933709(0x19f)]+_0x933709(0x309)+(this[_0x933709(0x15e)]['size']==='Large'?_0x933709(0x18c)+this[_0x933709(0x15e)]['maxHealth']+_0x933709(0x1f6)+this['player1'][_0x933709(0x156)]:'drops\x20health\x20to\x20'+this[_0x933709(0x15e)][_0x933709(0x1fd)]+_0x933709(0x375)+this['player1'][_0x933709(0x156)])+'!'),this['player2'][_0x933709(0x19f)]!==_0x933709(0x18a)&&log(this[_0x933709(0x31b)][_0x933709(0x2df)]+_0x933709(0x271)+this['player2']['size']+_0x933709(0x309)+(this[_0x933709(0x31b)]['size']===_0x933709(0x27b)?_0x933709(0x18c)+this[_0x933709(0x31b)][_0x933709(0x1fd)]+'\x20but\x20dulls\x20tactics\x20to\x20'+this['player2'][_0x933709(0x156)]:'drops\x20health\x20to\x20'+this[_0x933709(0x31b)][_0x933709(0x1fd)]+_0x933709(0x375)+this[_0x933709(0x31b)][_0x933709(0x156)])+'!'),log(this[_0x933709(0x15e)][_0x933709(0x2df)]+_0x933709(0x245)+this[_0x933709(0x15e)][_0x933709(0x16b)]+'/'+this[_0x933709(0x15e)][_0x933709(0x1fd)]+_0x933709(0x32c)),log(this[_0x933709(0x15b)][_0x933709(0x2df)]+_0x933709(0x2ef)),this[_0x933709(0x246)](),this[_0x933709(0x2e2)]=this['currentTurn']===this['player1']?_0x933709(0x257):'aiTurn',turnIndicator[_0x933709(0x30c)]=_0x933709(0x1cd)+this[_0x933709(0x1b4)]+_0x933709(0x276)+(this[_0x933709(0x15b)]===this[_0x933709(0x15e)]?_0x933709(0x329):_0x933709(0x367))+_0x933709(0x252),this['playerCharacters'][_0x933709(0x196)]>0x1&&(document[_0x933709(0x36d)](_0x933709(0x33f))[_0x933709(0x1c3)]['display']=_0x933709(0x1f3)),this[_0x933709(0x15b)]===this[_0x933709(0x31b)]&&setTimeout(function(){const _0x3bb842=_0x933709;_0x1936b5[_0x3bb842(0x1ba)]();},0x3e8);}[_0x272063(0x2bc)](){const _0x2695a4=_0x272063;p1Name[_0x2695a4(0x30c)]=this[_0x2695a4(0x15e)][_0x2695a4(0x2a8)]||this['theme']===_0x2695a4(0x24a)?this[_0x2695a4(0x15e)][_0x2695a4(0x2df)]:'Player\x201',p1Type[_0x2695a4(0x30c)]=this[_0x2695a4(0x15e)][_0x2695a4(0x23d)],p1Strength[_0x2695a4(0x30c)]=this[_0x2695a4(0x15e)][_0x2695a4(0x206)],p1Speed[_0x2695a4(0x30c)]=this[_0x2695a4(0x15e)][_0x2695a4(0x362)],p1Tactics[_0x2695a4(0x30c)]=this['player1']['tactics'],p1Size[_0x2695a4(0x30c)]=this[_0x2695a4(0x15e)][_0x2695a4(0x19f)],p1Powerup['textContent']=this[_0x2695a4(0x15e)][_0x2695a4(0x1e5)];const _0x551577=document[_0x2695a4(0x36d)](_0x2695a4(0x27a)),_0x4557a9=_0x551577[_0x2695a4(0x244)];if(this[_0x2695a4(0x15e)]['mediaType']===_0x2695a4(0x268)){if(_0x551577[_0x2695a4(0x1c7)]!=='VIDEO'){const _0xb61816=document[_0x2695a4(0x269)](_0x2695a4(0x268));_0xb61816['id']=_0x2695a4(0x27a),_0xb61816['src']=this['player1'][_0x2695a4(0x1f8)],_0xb61816[_0x2695a4(0x315)]=!![],_0xb61816['loop']=!![],_0xb61816[_0x2695a4(0x256)]=!![],_0xb61816[_0x2695a4(0x2fa)]=this[_0x2695a4(0x15e)][_0x2695a4(0x2df)],_0x4557a9[_0x2695a4(0x23e)](_0xb61816,_0x551577);}else _0x551577[_0x2695a4(0x161)]=this[_0x2695a4(0x15e)][_0x2695a4(0x1f8)];}else{if(_0x551577['tagName']!==_0x2695a4(0x365)){const _0x65c9d5=document[_0x2695a4(0x269)](_0x2695a4(0x369));_0x65c9d5['id']=_0x2695a4(0x27a),_0x65c9d5[_0x2695a4(0x161)]=this[_0x2695a4(0x15e)][_0x2695a4(0x1f8)],_0x65c9d5['alt']=this['player1'][_0x2695a4(0x2df)],_0x4557a9['replaceChild'](_0x65c9d5,_0x551577);}else _0x551577[_0x2695a4(0x161)]=this[_0x2695a4(0x15e)][_0x2695a4(0x1f8)];}const _0x42c22a=document[_0x2695a4(0x36d)]('p1-image');_0x42c22a[_0x2695a4(0x1c3)]['transform']=this[_0x2695a4(0x15e)][_0x2695a4(0x147)]===_0x2695a4(0x22a)?_0x2695a4(0x2f6):_0x2695a4(0x356),_0x42c22a[_0x2695a4(0x1c7)]===_0x2695a4(0x365)?_0x42c22a[_0x2695a4(0x260)]=function(){_0x42c22a['style']['display']='block';}:_0x42c22a[_0x2695a4(0x1c3)][_0x2695a4(0x16c)]=_0x2695a4(0x1d4),p1Hp[_0x2695a4(0x30c)]=this[_0x2695a4(0x15e)][_0x2695a4(0x16b)]+'/'+this[_0x2695a4(0x15e)][_0x2695a4(0x1fd)],_0x42c22a[_0x2695a4(0x307)]=()=>{const _0x1f66c4=_0x2695a4;console['log']('Player\x201\x20media\x20clicked'),this[_0x1f66c4(0x323)](![]);};}['updateOpponentDisplay'](){const _0x33238e=_0x272063;p2Name[_0x33238e(0x30c)]=this[_0x33238e(0x18b)]===_0x33238e(0x24a)?this[_0x33238e(0x31b)][_0x33238e(0x2df)]:_0x33238e(0x173),p2Type[_0x33238e(0x30c)]=this[_0x33238e(0x31b)]['type'],p2Strength[_0x33238e(0x30c)]=this[_0x33238e(0x31b)][_0x33238e(0x206)],p2Speed['textContent']=this[_0x33238e(0x31b)][_0x33238e(0x362)],p2Tactics[_0x33238e(0x30c)]=this[_0x33238e(0x31b)][_0x33238e(0x156)],p2Size[_0x33238e(0x30c)]=this[_0x33238e(0x31b)][_0x33238e(0x19f)],p2Powerup[_0x33238e(0x30c)]=this['player2'][_0x33238e(0x1e5)];const _0x123e65=document[_0x33238e(0x36d)]('p2-image'),_0x36e5a2=_0x123e65['parentNode'];if(this[_0x33238e(0x31b)][_0x33238e(0x357)]===_0x33238e(0x268)){if(_0x123e65[_0x33238e(0x1c7)]!==_0x33238e(0x217)){const _0x225726=document['createElement'](_0x33238e(0x268));_0x225726['id']=_0x33238e(0x1a9),_0x225726[_0x33238e(0x161)]=this[_0x33238e(0x31b)]['imageUrl'],_0x225726[_0x33238e(0x315)]=!![],_0x225726[_0x33238e(0x1c2)]=!![],_0x225726['muted']=!![],_0x225726[_0x33238e(0x2fa)]=this[_0x33238e(0x31b)][_0x33238e(0x2df)],_0x36e5a2[_0x33238e(0x23e)](_0x225726,_0x123e65);}else _0x123e65['src']=this[_0x33238e(0x31b)][_0x33238e(0x1f8)];}else{if(_0x123e65[_0x33238e(0x1c7)]!=='IMG'){const _0x1a1e63=document[_0x33238e(0x269)](_0x33238e(0x369));_0x1a1e63['id']=_0x33238e(0x1a9),_0x1a1e63[_0x33238e(0x161)]=this[_0x33238e(0x31b)][_0x33238e(0x1f8)],_0x1a1e63['alt']=this[_0x33238e(0x31b)][_0x33238e(0x2df)],_0x36e5a2[_0x33238e(0x23e)](_0x1a1e63,_0x123e65);}else _0x123e65[_0x33238e(0x161)]=this[_0x33238e(0x31b)][_0x33238e(0x1f8)];}const _0x4ad4a4=document['getElementById'](_0x33238e(0x1a9));_0x4ad4a4[_0x33238e(0x1c3)]['transform']=this[_0x33238e(0x31b)][_0x33238e(0x147)]==='Right'?'scaleX(-1)':_0x33238e(0x356),_0x4ad4a4[_0x33238e(0x1c7)]===_0x33238e(0x365)?_0x4ad4a4['onload']=function(){const _0x4fad16=_0x33238e;_0x4ad4a4[_0x4fad16(0x1c3)][_0x4fad16(0x16c)]=_0x4fad16(0x1d4);}:_0x4ad4a4['style'][_0x33238e(0x16c)]=_0x33238e(0x1d4),p2Hp[_0x33238e(0x30c)]=this[_0x33238e(0x31b)]['health']+'/'+this['player2']['maxHealth'],_0x4ad4a4[_0x33238e(0x14c)]['remove'](_0x33238e(0x2db),'loser');}[_0x272063(0x246)](){const _0x596a20=_0x272063;this[_0x596a20(0x2af)]=[];for(let _0x58d876=0x0;_0x58d876<this[_0x596a20(0x1b3)];_0x58d876++){this[_0x596a20(0x2af)][_0x58d876]=[];for(let _0x4d0f6a=0x0;_0x4d0f6a<this[_0x596a20(0x29c)];_0x4d0f6a++){let _0x1e5b23;do{_0x1e5b23=this['createRandomTile']();}while(_0x4d0f6a>=0x2&&this[_0x596a20(0x2af)][_0x58d876][_0x4d0f6a-0x1]?.[_0x596a20(0x23d)]===_0x1e5b23['type']&&this[_0x596a20(0x2af)][_0x58d876][_0x4d0f6a-0x2]?.[_0x596a20(0x23d)]===_0x1e5b23[_0x596a20(0x23d)]||_0x58d876>=0x2&&this[_0x596a20(0x2af)][_0x58d876-0x1]?.[_0x4d0f6a]?.[_0x596a20(0x23d)]===_0x1e5b23['type']&&this[_0x596a20(0x2af)][_0x58d876-0x2]?.[_0x4d0f6a]?.[_0x596a20(0x23d)]===_0x1e5b23[_0x596a20(0x23d)]);this[_0x596a20(0x2af)][_0x58d876][_0x4d0f6a]=_0x1e5b23;}}console['log'](_0x596a20(0x193),this[_0x596a20(0x29c)],'x',this['height']),this[_0x596a20(0x1dd)]();}['createRandomTile'](){const _0x1588ef=_0x272063;return{'type':randomChoice(this[_0x1588ef(0x2f8)]),'element':null};}['renderBoard'](){const _0xacd836=_0x272063;this[_0xacd836(0x352)]();const _0x13d5d2=document[_0xacd836(0x36d)](_0xacd836(0x1af));_0x13d5d2[_0xacd836(0x322)]='';if(!this[_0xacd836(0x2af)]||!Array['isArray'](this[_0xacd836(0x2af)])||this[_0xacd836(0x2af)][_0xacd836(0x196)]!==this[_0xacd836(0x1b3)]){console['warn'](_0xacd836(0x30e));return;}for(let _0xbb64e1=0x0;_0xbb64e1<this[_0xacd836(0x1b3)];_0xbb64e1++){if(!Array[_0xacd836(0x25d)](this[_0xacd836(0x2af)][_0xbb64e1])){console[_0xacd836(0x265)](_0xacd836(0x2ce)+_0xbb64e1+_0xacd836(0x259));continue;}for(let _0x4dd380=0x0;_0x4dd380<this[_0xacd836(0x29c)];_0x4dd380++){const _0x26dee9=this[_0xacd836(0x2af)][_0xbb64e1][_0x4dd380];if(!_0x26dee9||_0x26dee9[_0xacd836(0x23d)]===null)continue;const _0x3e2aed=document[_0xacd836(0x269)](_0xacd836(0x15d));_0x3e2aed[_0xacd836(0x27e)]=_0xacd836(0x355)+_0x26dee9[_0xacd836(0x23d)];if(this['gameOver'])_0x3e2aed[_0xacd836(0x14c)][_0xacd836(0x22f)]('game-over');const _0x4a63c4=document[_0xacd836(0x269)](_0xacd836(0x369));_0x4a63c4['src']=_0xacd836(0x361)+_0x26dee9[_0xacd836(0x23d)]+_0xacd836(0x363),_0x4a63c4[_0xacd836(0x2fa)]=_0x26dee9[_0xacd836(0x23d)],_0x3e2aed[_0xacd836(0x2a7)](_0x4a63c4),_0x3e2aed[_0xacd836(0x1fb)]['x']=_0x4dd380,_0x3e2aed[_0xacd836(0x1fb)]['y']=_0xbb64e1,_0x13d5d2['appendChild'](_0x3e2aed),_0x26dee9['element']=_0x3e2aed,(!this[_0xacd836(0x34a)]||this['selectedTile']&&(this[_0xacd836(0x1bd)]['x']!==_0x4dd380||this[_0xacd836(0x1bd)]['y']!==_0xbb64e1))&&(_0x3e2aed['style'][_0xacd836(0x33a)]=_0xacd836(0x374)),this[_0xacd836(0x14f)]?_0x3e2aed[_0xacd836(0x242)](_0xacd836(0x158),_0x4fe04d=>this[_0xacd836(0x2a6)](_0x4fe04d)):_0x3e2aed[_0xacd836(0x242)](_0xacd836(0x2d1),_0x31e757=>this[_0xacd836(0x1b5)](_0x31e757));}}document[_0xacd836(0x36d)]('game-over-container')[_0xacd836(0x1c3)][_0xacd836(0x16c)]=this[_0xacd836(0x234)]?_0xacd836(0x1d4):_0xacd836(0x356),console['log'](_0xacd836(0x1db));}[_0x272063(0x348)](){const _0x4bee28=_0x272063,_0x492383=document[_0x4bee28(0x36d)]('game-board');this['isTouchDevice']?(_0x492383[_0x4bee28(0x242)](_0x4bee28(0x158),_0x52bdcc=>this[_0x4bee28(0x2a6)](_0x52bdcc)),_0x492383[_0x4bee28(0x242)](_0x4bee28(0x18d),_0x568dc1=>this[_0x4bee28(0x331)](_0x568dc1)),_0x492383[_0x4bee28(0x242)](_0x4bee28(0x273),_0x349143=>this[_0x4bee28(0x225)](_0x349143))):(_0x492383['addEventListener'](_0x4bee28(0x2d1),_0x30002b=>this[_0x4bee28(0x1b5)](_0x30002b)),_0x492383['addEventListener'](_0x4bee28(0x255),_0x15a5e7=>this[_0x4bee28(0x136)](_0x15a5e7)),_0x492383['addEventListener'](_0x4bee28(0x293),_0x276ed8=>this['handleMouseUp'](_0x276ed8)));document[_0x4bee28(0x36d)](_0x4bee28(0x31f))['addEventListener'](_0x4bee28(0x177),()=>this['handleGameOverButton']()),document['getElementById'](_0x4bee28(0x312))[_0x4bee28(0x242)](_0x4bee28(0x177),()=>{const _0x15281e=_0x4bee28;this[_0x15281e(0x358)]();});const _0x5114e5=document['getElementById'](_0x4bee28(0x33f)),_0x1d6d9a=document[_0x4bee28(0x36d)](_0x4bee28(0x27a)),_0x4f481c=document[_0x4bee28(0x36d)](_0x4bee28(0x1a9));_0x5114e5[_0x4bee28(0x242)](_0x4bee28(0x177),()=>{const _0x1a6e59=_0x4bee28;console[_0x1a6e59(0x34d)](_0x1a6e59(0x27d)),this[_0x1a6e59(0x323)](![]);}),_0x1d6d9a[_0x4bee28(0x242)](_0x4bee28(0x177),()=>{const _0x204281=_0x4bee28;console[_0x204281(0x34d)](_0x204281(0x353)),this[_0x204281(0x323)](![]);}),document[_0x4bee28(0x36d)](_0x4bee28(0x22c))['addEventListener']('click',()=>this['flipCharacter'](this['player1'],document[_0x4bee28(0x36d)](_0x4bee28(0x27a)),![])),document[_0x4bee28(0x36d)](_0x4bee28(0x139))['addEventListener']('click',()=>this[_0x4bee28(0x17d)](this['player2'],document[_0x4bee28(0x36d)]('p2-image'),!![]));}['handleGameOverButton'](){const _0x4fef04=_0x272063;console[_0x4fef04(0x34d)]('handleGameOverButton\x20started:\x20currentLevel='+this[_0x4fef04(0x1b4)]+_0x4fef04(0x1a6)+this[_0x4fef04(0x31b)][_0x4fef04(0x16b)]),this[_0x4fef04(0x31b)][_0x4fef04(0x16b)]<=0x0&&this['currentLevel']>opponentsConfig[_0x4fef04(0x196)]&&(this[_0x4fef04(0x1b4)]=0x1,console['log'](_0x4fef04(0x1b2)+this[_0x4fef04(0x1b4)])),this[_0x4fef04(0x358)](),console[_0x4fef04(0x34d)](_0x4fef04(0x2b0)+this[_0x4fef04(0x1b4)]);}['handleMouseDown'](_0x5c4222){const _0x8c795=_0x272063;if(this[_0x8c795(0x234)]||this[_0x8c795(0x2e2)]!==_0x8c795(0x257)||this[_0x8c795(0x15b)]!==this[_0x8c795(0x15e)])return;_0x5c4222['preventDefault']();const _0x3e6fec=this[_0x8c795(0x298)](_0x5c4222);if(!_0x3e6fec||!_0x3e6fec[_0x8c795(0x2fd)])return;this[_0x8c795(0x34a)]=!![],this[_0x8c795(0x1bd)]={'x':_0x3e6fec['x'],'y':_0x3e6fec['y']},_0x3e6fec[_0x8c795(0x2fd)]['classList'][_0x8c795(0x22f)](_0x8c795(0x25b));const _0x4dc529=document[_0x8c795(0x36d)]('game-board')[_0x8c795(0x20f)]();this[_0x8c795(0x370)]=_0x5c4222[_0x8c795(0x17a)]-(_0x4dc529[_0x8c795(0x264)]+this[_0x8c795(0x1bd)]['x']*this['tileSizeWithGap']),this[_0x8c795(0x24c)]=_0x5c4222[_0x8c795(0x2ab)]-(_0x4dc529[_0x8c795(0x32f)]+this['selectedTile']['y']*this[_0x8c795(0x236)]);}[_0x272063(0x136)](_0x320dd9){const _0x52b7ad=_0x272063;if(!this[_0x52b7ad(0x34a)]||!this['selectedTile']||this[_0x52b7ad(0x234)]||this[_0x52b7ad(0x2e2)]!==_0x52b7ad(0x257))return;_0x320dd9[_0x52b7ad(0x175)]();const _0x378da9=document['getElementById'](_0x52b7ad(0x1af))[_0x52b7ad(0x20f)](),_0x35ed94=_0x320dd9[_0x52b7ad(0x17a)]-_0x378da9['left']-this['offsetX'],_0x3e3e80=_0x320dd9[_0x52b7ad(0x2ab)]-_0x378da9[_0x52b7ad(0x32f)]-this[_0x52b7ad(0x24c)],_0x484276=this[_0x52b7ad(0x2af)][this['selectedTile']['y']][this['selectedTile']['x']]['element'];_0x484276['style'][_0x52b7ad(0x253)]='';if(!this['dragDirection']){const _0x27fed8=Math['abs'](_0x35ed94-this['selectedTile']['x']*this[_0x52b7ad(0x236)]),_0x58468d=Math['abs'](_0x3e3e80-this['selectedTile']['y']*this['tileSizeWithGap']);if(_0x27fed8>_0x58468d&&_0x27fed8>0x5)this['dragDirection']='row';else{if(_0x58468d>_0x27fed8&&_0x58468d>0x5)this[_0x52b7ad(0x210)]='column';}}if(!this[_0x52b7ad(0x210)])return;if(this[_0x52b7ad(0x210)]===_0x52b7ad(0x220)){const _0x96b254=Math[_0x52b7ad(0x17b)](0x0,Math[_0x52b7ad(0x2ec)]((this[_0x52b7ad(0x29c)]-0x1)*this['tileSizeWithGap'],_0x35ed94));_0x484276[_0x52b7ad(0x1c3)][_0x52b7ad(0x33a)]=_0x52b7ad(0x333)+(_0x96b254-this[_0x52b7ad(0x1bd)]['x']*this[_0x52b7ad(0x236)])+'px,\x200)\x20scale(1.05)',this[_0x52b7ad(0x335)]={'x':Math[_0x52b7ad(0x22e)](_0x96b254/this[_0x52b7ad(0x236)]),'y':this[_0x52b7ad(0x1bd)]['y']};}else{if(this[_0x52b7ad(0x210)]===_0x52b7ad(0x1e1)){const _0x560a89=Math['max'](0x0,Math['min']((this[_0x52b7ad(0x1b3)]-0x1)*this[_0x52b7ad(0x236)],_0x3e3e80));_0x484276[_0x52b7ad(0x1c3)][_0x52b7ad(0x33a)]='translate(0,\x20'+(_0x560a89-this[_0x52b7ad(0x1bd)]['y']*this[_0x52b7ad(0x236)])+_0x52b7ad(0x19d),this[_0x52b7ad(0x335)]={'x':this[_0x52b7ad(0x1bd)]['x'],'y':Math[_0x52b7ad(0x22e)](_0x560a89/this['tileSizeWithGap'])};}}}['handleMouseUp'](_0x2dbf6f){const _0x35ae53=_0x272063;if(!this[_0x35ae53(0x34a)]||!this['selectedTile']||!this['targetTile']||this[_0x35ae53(0x234)]||this[_0x35ae53(0x2e2)]!=='playerTurn'){if(this[_0x35ae53(0x1bd)]){const _0x3af856=this[_0x35ae53(0x2af)][this[_0x35ae53(0x1bd)]['y']][this[_0x35ae53(0x1bd)]['x']];if(_0x3af856['element'])_0x3af856[_0x35ae53(0x2fd)]['classList'][_0x35ae53(0x34f)]('selected');}this[_0x35ae53(0x34a)]=![],this[_0x35ae53(0x1bd)]=null,this[_0x35ae53(0x335)]=null,this['dragDirection']=null,this['renderBoard']();return;}const _0x349f3b=this[_0x35ae53(0x2af)][this['selectedTile']['y']][this[_0x35ae53(0x1bd)]['x']];if(_0x349f3b[_0x35ae53(0x2fd)])_0x349f3b[_0x35ae53(0x2fd)][_0x35ae53(0x14c)][_0x35ae53(0x34f)](_0x35ae53(0x25b));this[_0x35ae53(0x314)](this[_0x35ae53(0x1bd)]['x'],this[_0x35ae53(0x1bd)]['y'],this[_0x35ae53(0x335)]['x'],this[_0x35ae53(0x335)]['y']),this[_0x35ae53(0x34a)]=![],this['selectedTile']=null,this[_0x35ae53(0x335)]=null,this[_0x35ae53(0x210)]=null;}[_0x272063(0x2a6)](_0x4dcd4b){const _0x5687ba=_0x272063;if(this[_0x5687ba(0x234)]||this[_0x5687ba(0x2e2)]!==_0x5687ba(0x257)||this[_0x5687ba(0x15b)]!==this[_0x5687ba(0x15e)])return;_0x4dcd4b[_0x5687ba(0x175)]();const _0x31fbba=this[_0x5687ba(0x298)](_0x4dcd4b[_0x5687ba(0x21b)][0x0]);if(!_0x31fbba||!_0x31fbba[_0x5687ba(0x2fd)])return;this['isDragging']=!![],this[_0x5687ba(0x1bd)]={'x':_0x31fbba['x'],'y':_0x31fbba['y']},_0x31fbba['element'][_0x5687ba(0x14c)][_0x5687ba(0x22f)]('selected');const _0x480ddf=document['getElementById'](_0x5687ba(0x1af))[_0x5687ba(0x20f)]();this[_0x5687ba(0x370)]=_0x4dcd4b['touches'][0x0]['clientX']-(_0x480ddf[_0x5687ba(0x264)]+this[_0x5687ba(0x1bd)]['x']*this[_0x5687ba(0x236)]),this[_0x5687ba(0x24c)]=_0x4dcd4b[_0x5687ba(0x21b)][0x0][_0x5687ba(0x2ab)]-(_0x480ddf[_0x5687ba(0x32f)]+this[_0x5687ba(0x1bd)]['y']*this[_0x5687ba(0x236)]);}[_0x272063(0x331)](_0x312bc2){const _0x1a6951=_0x272063;if(!this[_0x1a6951(0x34a)]||!this[_0x1a6951(0x1bd)]||this[_0x1a6951(0x234)]||this[_0x1a6951(0x2e2)]!==_0x1a6951(0x257))return;_0x312bc2[_0x1a6951(0x175)]();const _0x3c97a9=document['getElementById'](_0x1a6951(0x1af))[_0x1a6951(0x20f)](),_0x192274=_0x312bc2[_0x1a6951(0x21b)][0x0][_0x1a6951(0x17a)]-_0x3c97a9[_0x1a6951(0x264)]-this[_0x1a6951(0x370)],_0x3d6639=_0x312bc2[_0x1a6951(0x21b)][0x0]['clientY']-_0x3c97a9[_0x1a6951(0x32f)]-this[_0x1a6951(0x24c)],_0x48222a=this[_0x1a6951(0x2af)][this['selectedTile']['y']][this[_0x1a6951(0x1bd)]['x']][_0x1a6951(0x2fd)];requestAnimationFrame(()=>{const _0xe616d1=_0x1a6951;if(!this['dragDirection']){const _0x1d75d6=Math[_0xe616d1(0x26c)](_0x192274-this[_0xe616d1(0x1bd)]['x']*this[_0xe616d1(0x236)]),_0x41aef8=Math[_0xe616d1(0x26c)](_0x3d6639-this[_0xe616d1(0x1bd)]['y']*this[_0xe616d1(0x236)]);if(_0x1d75d6>_0x41aef8&&_0x1d75d6>0x7)this[_0xe616d1(0x210)]=_0xe616d1(0x220);else{if(_0x41aef8>_0x1d75d6&&_0x41aef8>0x7)this[_0xe616d1(0x210)]=_0xe616d1(0x1e1);}}_0x48222a[_0xe616d1(0x1c3)][_0xe616d1(0x253)]='';if(this[_0xe616d1(0x210)]===_0xe616d1(0x220)){const _0x9d85f4=Math[_0xe616d1(0x17b)](0x0,Math['min']((this[_0xe616d1(0x29c)]-0x1)*this[_0xe616d1(0x236)],_0x192274));_0x48222a['style']['transform']='translate('+(_0x9d85f4-this[_0xe616d1(0x1bd)]['x']*this[_0xe616d1(0x236)])+_0xe616d1(0x19b),this['targetTile']={'x':Math[_0xe616d1(0x22e)](_0x9d85f4/this[_0xe616d1(0x236)]),'y':this[_0xe616d1(0x1bd)]['y']};}else{if(this['dragDirection']==='column'){const _0x560015=Math['max'](0x0,Math['min']((this[_0xe616d1(0x1b3)]-0x1)*this[_0xe616d1(0x236)],_0x3d6639));_0x48222a['style'][_0xe616d1(0x33a)]=_0xe616d1(0x360)+(_0x560015-this['selectedTile']['y']*this[_0xe616d1(0x236)])+_0xe616d1(0x19d),this[_0xe616d1(0x335)]={'x':this['selectedTile']['x'],'y':Math[_0xe616d1(0x22e)](_0x560015/this['tileSizeWithGap'])};}}});}[_0x272063(0x225)](_0x4aaf0f){const _0x1e1acf=_0x272063;if(!this['isDragging']||!this[_0x1e1acf(0x1bd)]||!this[_0x1e1acf(0x335)]||this['gameOver']||this[_0x1e1acf(0x2e2)]!==_0x1e1acf(0x257)){if(this['selectedTile']){const _0x20c5f4=this[_0x1e1acf(0x2af)][this[_0x1e1acf(0x1bd)]['y']][this[_0x1e1acf(0x1bd)]['x']];if(_0x20c5f4[_0x1e1acf(0x2fd)])_0x20c5f4['element'][_0x1e1acf(0x14c)][_0x1e1acf(0x34f)](_0x1e1acf(0x25b));}this[_0x1e1acf(0x34a)]=![],this[_0x1e1acf(0x1bd)]=null,this['targetTile']=null,this[_0x1e1acf(0x210)]=null,this[_0x1e1acf(0x1dd)]();return;}const _0x4b973b=this[_0x1e1acf(0x2af)][this[_0x1e1acf(0x1bd)]['y']][this[_0x1e1acf(0x1bd)]['x']];if(_0x4b973b[_0x1e1acf(0x2fd)])_0x4b973b[_0x1e1acf(0x2fd)][_0x1e1acf(0x14c)][_0x1e1acf(0x34f)]('selected');this['slideTiles'](this[_0x1e1acf(0x1bd)]['x'],this[_0x1e1acf(0x1bd)]['y'],this[_0x1e1acf(0x335)]['x'],this[_0x1e1acf(0x335)]['y']),this['isDragging']=![],this['selectedTile']=null,this[_0x1e1acf(0x335)]=null,this[_0x1e1acf(0x210)]=null;}['getTileFromEvent'](_0xc6c1e9){const _0x1219e9=_0x272063,_0x5c3d9a=document[_0x1219e9(0x36d)]('game-board')[_0x1219e9(0x20f)](),_0x48100b=Math[_0x1219e9(0x2c8)]((_0xc6c1e9[_0x1219e9(0x17a)]-_0x5c3d9a[_0x1219e9(0x264)])/this[_0x1219e9(0x236)]),_0x16c2dd=Math[_0x1219e9(0x2c8)]((_0xc6c1e9['clientY']-_0x5c3d9a['top'])/this[_0x1219e9(0x236)]);if(_0x48100b>=0x0&&_0x48100b<this[_0x1219e9(0x29c)]&&_0x16c2dd>=0x0&&_0x16c2dd<this[_0x1219e9(0x1b3)])return{'x':_0x48100b,'y':_0x16c2dd,'element':this[_0x1219e9(0x2af)][_0x16c2dd][_0x48100b]['element']};return null;}['slideTiles'](_0x56bfdd,_0x3edbf1,_0xa488ae,_0x2c5146){const _0x3c24b9=_0x272063,_0x460727=this['tileSizeWithGap'];let _0x1dc947;const _0x1abe56=[],_0x43b768=[];if(_0x3edbf1===_0x2c5146){_0x1dc947=_0x56bfdd<_0xa488ae?0x1:-0x1;const _0x46d35f=Math['min'](_0x56bfdd,_0xa488ae),_0x1c3c6c=Math[_0x3c24b9(0x17b)](_0x56bfdd,_0xa488ae);for(let _0x4fe589=_0x46d35f;_0x4fe589<=_0x1c3c6c;_0x4fe589++){_0x1abe56[_0x3c24b9(0x272)]({...this[_0x3c24b9(0x2af)][_0x3edbf1][_0x4fe589]}),_0x43b768[_0x3c24b9(0x272)](this[_0x3c24b9(0x2af)][_0x3edbf1][_0x4fe589]['element']);}}else{if(_0x56bfdd===_0xa488ae){_0x1dc947=_0x3edbf1<_0x2c5146?0x1:-0x1;const _0x9a6ce1=Math[_0x3c24b9(0x2ec)](_0x3edbf1,_0x2c5146),_0x557fdc=Math[_0x3c24b9(0x17b)](_0x3edbf1,_0x2c5146);for(let _0x325f8b=_0x9a6ce1;_0x325f8b<=_0x557fdc;_0x325f8b++){_0x1abe56[_0x3c24b9(0x272)]({...this[_0x3c24b9(0x2af)][_0x325f8b][_0x56bfdd]}),_0x43b768[_0x3c24b9(0x272)](this[_0x3c24b9(0x2af)][_0x325f8b][_0x56bfdd][_0x3c24b9(0x2fd)]);}}}const _0x1951a4=this[_0x3c24b9(0x2af)][_0x3edbf1][_0x56bfdd][_0x3c24b9(0x2fd)],_0xbd7397=(_0xa488ae-_0x56bfdd)*_0x460727,_0x16ae77=(_0x2c5146-_0x3edbf1)*_0x460727;_0x1951a4['style'][_0x3c24b9(0x253)]='transform\x200.2s\x20ease',_0x1951a4['style'][_0x3c24b9(0x33a)]='translate('+_0xbd7397+_0x3c24b9(0x181)+_0x16ae77+'px)';let _0x3d5405=0x0;if(_0x3edbf1===_0x2c5146)for(let _0x3271aa=Math[_0x3c24b9(0x2ec)](_0x56bfdd,_0xa488ae);_0x3271aa<=Math['max'](_0x56bfdd,_0xa488ae);_0x3271aa++){if(_0x3271aa===_0x56bfdd)continue;const _0x232319=_0x1dc947*-_0x460727*(_0x3271aa-_0x56bfdd)/Math[_0x3c24b9(0x26c)](_0xa488ae-_0x56bfdd);_0x43b768[_0x3d5405][_0x3c24b9(0x1c3)][_0x3c24b9(0x253)]=_0x3c24b9(0x33e),_0x43b768[_0x3d5405][_0x3c24b9(0x1c3)][_0x3c24b9(0x33a)]=_0x3c24b9(0x333)+_0x232319+_0x3c24b9(0x26d),_0x3d5405++;}else for(let _0xef0a82=Math[_0x3c24b9(0x2ec)](_0x3edbf1,_0x2c5146);_0xef0a82<=Math['max'](_0x3edbf1,_0x2c5146);_0xef0a82++){if(_0xef0a82===_0x3edbf1)continue;const _0x440695=_0x1dc947*-_0x460727*(_0xef0a82-_0x3edbf1)/Math[_0x3c24b9(0x26c)](_0x2c5146-_0x3edbf1);_0x43b768[_0x3d5405][_0x3c24b9(0x1c3)]['transition']=_0x3c24b9(0x33e),_0x43b768[_0x3d5405][_0x3c24b9(0x1c3)][_0x3c24b9(0x33a)]=_0x3c24b9(0x360)+_0x440695+_0x3c24b9(0x337),_0x3d5405++;}setTimeout(()=>{const _0x2eef33=_0x3c24b9;if(_0x3edbf1===_0x2c5146){const _0x4b1686=this['board'][_0x3edbf1],_0x46c273=[..._0x4b1686];if(_0x56bfdd<_0xa488ae){for(let _0x4a714b=_0x56bfdd;_0x4a714b<_0xa488ae;_0x4a714b++)_0x4b1686[_0x4a714b]=_0x46c273[_0x4a714b+0x1];}else{for(let _0x26e754=_0x56bfdd;_0x26e754>_0xa488ae;_0x26e754--)_0x4b1686[_0x26e754]=_0x46c273[_0x26e754-0x1];}_0x4b1686[_0xa488ae]=_0x46c273[_0x56bfdd];}else{const _0xcbba3a=[];for(let _0x42a785=0x0;_0x42a785<this[_0x2eef33(0x1b3)];_0x42a785++)_0xcbba3a[_0x42a785]={...this['board'][_0x42a785][_0x56bfdd]};if(_0x3edbf1<_0x2c5146){for(let _0x569ae8=_0x3edbf1;_0x569ae8<_0x2c5146;_0x569ae8++)this[_0x2eef33(0x2af)][_0x569ae8][_0x56bfdd]=_0xcbba3a[_0x569ae8+0x1];}else{for(let _0x4653f5=_0x3edbf1;_0x4653f5>_0x2c5146;_0x4653f5--)this[_0x2eef33(0x2af)][_0x4653f5][_0x56bfdd]=_0xcbba3a[_0x4653f5-0x1];}this['board'][_0x2c5146][_0xa488ae]=_0xcbba3a[_0x3edbf1];}this['renderBoard']();const _0x1f20c9=this[_0x2eef33(0x21d)](_0xa488ae,_0x2c5146);_0x1f20c9?this[_0x2eef33(0x2e2)]='animating':(log(_0x2eef33(0x2de)),this[_0x2eef33(0x162)][_0x2eef33(0x349)][_0x2eef33(0x2c2)](),_0x1951a4[_0x2eef33(0x1c3)][_0x2eef33(0x253)]=_0x2eef33(0x33e),_0x1951a4[_0x2eef33(0x1c3)][_0x2eef33(0x33a)]=_0x2eef33(0x374),_0x43b768[_0x2eef33(0x2b2)](_0x591f77=>{const _0x12049e=_0x2eef33;_0x591f77[_0x12049e(0x1c3)][_0x12049e(0x253)]=_0x12049e(0x33e),_0x591f77[_0x12049e(0x1c3)][_0x12049e(0x33a)]=_0x12049e(0x374);}),setTimeout(()=>{const _0x25bea2=_0x2eef33;if(_0x3edbf1===_0x2c5146){const _0x33d0fe=Math[_0x25bea2(0x2ec)](_0x56bfdd,_0xa488ae);for(let _0x55889a=0x0;_0x55889a<_0x1abe56[_0x25bea2(0x196)];_0x55889a++){this[_0x25bea2(0x2af)][_0x3edbf1][_0x33d0fe+_0x55889a]={..._0x1abe56[_0x55889a],'element':_0x43b768[_0x55889a]};}}else{const _0x554436=Math['min'](_0x3edbf1,_0x2c5146);for(let _0x4c5acc=0x0;_0x4c5acc<_0x1abe56[_0x25bea2(0x196)];_0x4c5acc++){this[_0x25bea2(0x2af)][_0x554436+_0x4c5acc][_0x56bfdd]={..._0x1abe56[_0x4c5acc],'element':_0x43b768[_0x4c5acc]};}}this[_0x25bea2(0x1dd)](),this['gameState']=_0x25bea2(0x257);},0xc8));},0xc8);}[_0x272063(0x21d)](_0x4e1f99=null,_0x1f6283=null){const _0x3d5297=_0x272063;console[_0x3d5297(0x34d)](_0x3d5297(0x2a3),this['gameOver']);if(this[_0x3d5297(0x234)])return console[_0x3d5297(0x34d)]('Game\x20over,\x20exiting\x20resolveMatches'),![];const _0x446dc5=_0x4e1f99!==null&&_0x1f6283!==null;console['log']('Is\x20initial\x20move:\x20'+_0x446dc5);const _0x2404b0=this[_0x3d5297(0x344)]();console[_0x3d5297(0x34d)](_0x3d5297(0x351)+_0x2404b0[_0x3d5297(0x196)]+_0x3d5297(0x30a),_0x2404b0);let _0x17985d=0x1,_0x5f1f3a='';if(_0x446dc5&&_0x2404b0[_0x3d5297(0x196)]>0x1){const _0x2d00f5=_0x2404b0[_0x3d5297(0x13e)]((_0x30c934,_0x3a983b)=>_0x30c934+_0x3a983b['totalTiles'],0x0);console[_0x3d5297(0x34d)](_0x3d5297(0x2cb)+_0x2d00f5);if(_0x2d00f5>=0x6&&_0x2d00f5<=0x8)_0x17985d=1.2,_0x5f1f3a=_0x3d5297(0x188)+_0x2d00f5+'\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!',this['sounds'][_0x3d5297(0x325)][_0x3d5297(0x2c2)]();else _0x2d00f5>=0x9&&(_0x17985d=0x3,_0x5f1f3a=_0x3d5297(0x168)+_0x2d00f5+_0x3d5297(0x308),this[_0x3d5297(0x162)][_0x3d5297(0x325)]['play']());}if(_0x2404b0[_0x3d5297(0x196)]>0x0){const _0x322333=new Set();let _0x335760=0x0;const _0x5a006b=this[_0x3d5297(0x15b)],_0x152f6f=this['currentTurn']===this[_0x3d5297(0x15e)]?this['player2']:this[_0x3d5297(0x15e)];try{_0x2404b0[_0x3d5297(0x2b2)](_0x184ce4=>{const _0x26c441=_0x3d5297;console[_0x26c441(0x34d)](_0x26c441(0x330),_0x184ce4),_0x184ce4[_0x26c441(0x371)][_0x26c441(0x2b2)](_0x145662=>_0x322333[_0x26c441(0x22f)](_0x145662));const _0x23396d=this[_0x26c441(0x26e)](_0x184ce4,_0x446dc5);console[_0x26c441(0x34d)](_0x26c441(0x194)+_0x23396d);if(this[_0x26c441(0x234)]){console[_0x26c441(0x34d)]('Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing');return;}if(_0x23396d>0x0)_0x335760+=_0x23396d;});if(this['gameOver'])return console[_0x3d5297(0x34d)](_0x3d5297(0x241)),!![];return console[_0x3d5297(0x34d)](_0x3d5297(0x334)+_0x335760+_0x3d5297(0x294),[..._0x322333]),_0x335760>0x0&&!this[_0x3d5297(0x234)]&&setTimeout(()=>{const _0x2c6541=_0x3d5297;if(this[_0x2c6541(0x234)]){console[_0x2c6541(0x34d)]('Game\x20over,\x20skipping\x20recoil\x20animation');return;}console['log'](_0x2c6541(0x186),_0x152f6f[_0x2c6541(0x2df)]),this['animateRecoil'](_0x152f6f,_0x335760);},0x64),setTimeout(()=>{const _0x7c361e=_0x3d5297;if(this[_0x7c361e(0x234)]){console[_0x7c361e(0x34d)]('Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading');return;}console[_0x7c361e(0x34d)](_0x7c361e(0x13d),[..._0x322333]),_0x322333[_0x7c361e(0x2b2)](_0x26f412=>{const _0x260512=_0x7c361e,[_0x33eb0f,_0x536246]=_0x26f412['split'](',')[_0x260512(0x166)](Number);this[_0x260512(0x2af)][_0x536246][_0x33eb0f]?.[_0x260512(0x2fd)]?this[_0x260512(0x2af)][_0x536246][_0x33eb0f]['element'][_0x260512(0x14c)][_0x260512(0x22f)](_0x260512(0x27c)):console['warn'](_0x260512(0x1bc)+_0x33eb0f+','+_0x536246+_0x260512(0x31e));}),setTimeout(()=>{const _0x3b189b=_0x7c361e;if(this['gameOver']){console[_0x3b189b(0x34d)](_0x3b189b(0x1c9));return;}console['log'](_0x3b189b(0x25f),[..._0x322333]),_0x322333[_0x3b189b(0x2b2)](_0x446d10=>{const _0x30e7ef=_0x3b189b,[_0x4b0c5b,_0x2b947f]=_0x446d10[_0x30e7ef(0x2d0)](',')[_0x30e7ef(0x166)](Number);this[_0x30e7ef(0x2af)][_0x2b947f][_0x4b0c5b]&&(this[_0x30e7ef(0x2af)][_0x2b947f][_0x4b0c5b][_0x30e7ef(0x23d)]=null,this['board'][_0x2b947f][_0x4b0c5b]['element']=null);}),this[_0x3b189b(0x162)]['match'][_0x3b189b(0x2c2)](),console[_0x3b189b(0x34d)](_0x3b189b(0x35b));if(_0x17985d>0x1&&this[_0x3b189b(0x2f1)]['length']>0x0){const _0x3fdd29=this[_0x3b189b(0x2f1)][this[_0x3b189b(0x2f1)][_0x3b189b(0x196)]-0x1],_0x1d250c=_0x3fdd29[_0x3b189b(0x222)];_0x3fdd29[_0x3b189b(0x222)]=Math[_0x3b189b(0x22e)](_0x3fdd29[_0x3b189b(0x222)]*_0x17985d),_0x5f1f3a&&(log(_0x5f1f3a),log(_0x3b189b(0x26b)+_0x1d250c+_0x3b189b(0x13c)+_0x3fdd29[_0x3b189b(0x222)]+_0x3b189b(0x2be)));}this['cascadeTiles'](()=>{const _0x330439=_0x3b189b;if(this[_0x330439(0x234)]){console[_0x330439(0x34d)](_0x330439(0x1fc));return;}console[_0x330439(0x34d)](_0x330439(0x2a5)),this[_0x330439(0x200)]();});},0x12c);},0xc8),!![];}catch(_0x531983){return console[_0x3d5297(0x1a3)](_0x3d5297(0x145),_0x531983),this[_0x3d5297(0x2e2)]=this[_0x3d5297(0x15b)]===this[_0x3d5297(0x15e)]?'playerTurn':_0x3d5297(0x1ba),![];}}return console['log'](_0x3d5297(0x187)),![];}[_0x272063(0x344)](){const _0x2a544d=_0x272063;console[_0x2a544d(0x34d)]('checkMatches\x20started');const _0x2f3138=[];try{const _0x4c732e=[];for(let _0x5b7e86=0x0;_0x5b7e86<this['height'];_0x5b7e86++){let _0x2b6865=0x0;for(let _0x2df935=0x0;_0x2df935<=this[_0x2a544d(0x29c)];_0x2df935++){const _0x46acd5=_0x2df935<this[_0x2a544d(0x29c)]?this[_0x2a544d(0x2af)][_0x5b7e86][_0x2df935]?.[_0x2a544d(0x23d)]:null;if(_0x46acd5!==this[_0x2a544d(0x2af)][_0x5b7e86][_0x2b6865]?.[_0x2a544d(0x23d)]||_0x2df935===this[_0x2a544d(0x29c)]){const _0x220900=_0x2df935-_0x2b6865;if(_0x220900>=0x3){const _0x2cd4bd=new Set();for(let _0x28fbf5=_0x2b6865;_0x28fbf5<_0x2df935;_0x28fbf5++){_0x2cd4bd[_0x2a544d(0x22f)](_0x28fbf5+','+_0x5b7e86);}_0x4c732e['push']({'type':this[_0x2a544d(0x2af)][_0x5b7e86][_0x2b6865][_0x2a544d(0x23d)],'coordinates':_0x2cd4bd}),console['log'](_0x2a544d(0x26a)+_0x5b7e86+_0x2a544d(0x20d)+_0x2b6865+'-'+(_0x2df935-0x1)+':',[..._0x2cd4bd]);}_0x2b6865=_0x2df935;}}}for(let _0xd9e5f0=0x0;_0xd9e5f0<this[_0x2a544d(0x29c)];_0xd9e5f0++){let _0x36cced=0x0;for(let _0x4931d6=0x0;_0x4931d6<=this[_0x2a544d(0x1b3)];_0x4931d6++){const _0x3a280f=_0x4931d6<this[_0x2a544d(0x1b3)]?this[_0x2a544d(0x2af)][_0x4931d6][_0xd9e5f0]?.['type']:null;if(_0x3a280f!==this[_0x2a544d(0x2af)][_0x36cced][_0xd9e5f0]?.[_0x2a544d(0x23d)]||_0x4931d6===this[_0x2a544d(0x1b3)]){const _0x25d445=_0x4931d6-_0x36cced;if(_0x25d445>=0x3){const _0x2709cf=new Set();for(let _0x588f39=_0x36cced;_0x588f39<_0x4931d6;_0x588f39++){_0x2709cf[_0x2a544d(0x22f)](_0xd9e5f0+','+_0x588f39);}_0x4c732e[_0x2a544d(0x272)]({'type':this[_0x2a544d(0x2af)][_0x36cced][_0xd9e5f0][_0x2a544d(0x23d)],'coordinates':_0x2709cf}),console[_0x2a544d(0x34d)](_0x2a544d(0x20a)+_0xd9e5f0+_0x2a544d(0x154)+_0x36cced+'-'+(_0x4931d6-0x1)+':',[..._0x2709cf]);}_0x36cced=_0x4931d6;}}}const _0xfc95c=[],_0x14b82b=new Set();return _0x4c732e[_0x2a544d(0x2b2)]((_0x3f8ac9,_0x224efc)=>{const _0x173ce3=_0x2a544d;if(_0x14b82b[_0x173ce3(0x28a)](_0x224efc))return;const _0x5282b1={'type':_0x3f8ac9[_0x173ce3(0x23d)],'coordinates':new Set(_0x3f8ac9[_0x173ce3(0x371)])};_0x14b82b[_0x173ce3(0x22f)](_0x224efc);for(let _0x31ab7c=0x0;_0x31ab7c<_0x4c732e['length'];_0x31ab7c++){if(_0x14b82b[_0x173ce3(0x28a)](_0x31ab7c))continue;const _0x21e77d=_0x4c732e[_0x31ab7c];if(_0x21e77d['type']===_0x5282b1[_0x173ce3(0x23d)]){const _0x340b41=[..._0x21e77d[_0x173ce3(0x371)]][_0x173ce3(0x142)](_0x2f3b25=>_0x5282b1['coordinates']['has'](_0x2f3b25));_0x340b41&&(_0x21e77d['coordinates'][_0x173ce3(0x2b2)](_0x29f4f5=>_0x5282b1[_0x173ce3(0x371)][_0x173ce3(0x22f)](_0x29f4f5)),_0x14b82b[_0x173ce3(0x22f)](_0x31ab7c));}}_0xfc95c[_0x173ce3(0x272)]({'type':_0x5282b1[_0x173ce3(0x23d)],'coordinates':_0x5282b1[_0x173ce3(0x371)],'totalTiles':_0x5282b1[_0x173ce3(0x371)][_0x173ce3(0x19f)]});}),_0x2f3138[_0x2a544d(0x272)](..._0xfc95c),console['log'](_0x2a544d(0x306),_0x2f3138),_0x2f3138;}catch(_0x54ac3e){return console[_0x2a544d(0x1a3)](_0x2a544d(0x20b),_0x54ac3e),[];}}[_0x272063(0x26e)](_0x3be052,_0x28cb90=!![]){const _0x24d766=_0x272063;console['log'](_0x24d766(0x16e),_0x3be052,_0x24d766(0x23c),_0x28cb90);const _0x18bc10=this[_0x24d766(0x15b)],_0x2b6710=this['currentTurn']===this[_0x24d766(0x15e)]?this['player2']:this[_0x24d766(0x15e)],_0x3916c6=_0x3be052['type'],_0x3866be=_0x3be052[_0x24d766(0x2e6)];let _0x5dd13f=0x0,_0x3eb578=0x0;console[_0x24d766(0x34d)](_0x2b6710[_0x24d766(0x2df)]+_0x24d766(0x284)+_0x2b6710['health']);_0x3866be==0x4&&(this[_0x24d766(0x162)][_0x24d766(0x32b)][_0x24d766(0x2c2)](),log(_0x18bc10[_0x24d766(0x2df)]+_0x24d766(0x198)+_0x3866be+_0x24d766(0x34c)));_0x3866be>=0x5&&(this[_0x24d766(0x162)][_0x24d766(0x229)][_0x24d766(0x2c2)](),log(_0x18bc10[_0x24d766(0x2df)]+_0x24d766(0x198)+_0x3866be+_0x24d766(0x34c)));if(_0x3916c6===_0x24d766(0x230)||_0x3916c6===_0x24d766(0x2da)||_0x3916c6==='special-attack'||_0x3916c6===_0x24d766(0x354)){_0x5dd13f=Math[_0x24d766(0x22e)](_0x18bc10[_0x24d766(0x206)]*(_0x3866be===0x3?0x2:_0x3866be===0x4?0x3:0x4));let _0x3c51b7=0x1;if(_0x3866be===0x4)_0x3c51b7=1.5;else _0x3866be>=0x5&&(_0x3c51b7=0x2);_0x5dd13f=Math[_0x24d766(0x22e)](_0x5dd13f*_0x3c51b7),console[_0x24d766(0x34d)]('Base\x20damage:\x20'+_0x18bc10[_0x24d766(0x206)]*(_0x3866be===0x3?0x2:_0x3866be===0x4?0x3:0x4)+_0x24d766(0x300)+_0x3c51b7+_0x24d766(0x2aa)+_0x5dd13f);_0x3916c6===_0x24d766(0x1e6)&&(_0x5dd13f=Math[_0x24d766(0x22e)](_0x5dd13f*1.2),console[_0x24d766(0x34d)](_0x24d766(0x328)+_0x5dd13f));_0x18bc10[_0x24d766(0x164)]&&(_0x5dd13f+=_0x18bc10[_0x24d766(0x27f)]||0xa,_0x18bc10[_0x24d766(0x164)]=![],log(_0x18bc10[_0x24d766(0x2df)]+_0x24d766(0x2bf)),console[_0x24d766(0x34d)](_0x24d766(0x368)+_0x5dd13f));_0x3eb578=_0x5dd13f;const _0x517b6d=_0x2b6710['tactics']*0xa;Math[_0x24d766(0x304)]()*0x64<_0x517b6d&&(_0x5dd13f=Math[_0x24d766(0x2c8)](_0x5dd13f/0x2),log(_0x2b6710['name']+_0x24d766(0x214)+_0x5dd13f+_0x24d766(0x1b1)),console[_0x24d766(0x34d)](_0x24d766(0x292)+_0x5dd13f));let _0x45d657=0x0;_0x2b6710['lastStandActive']&&(_0x45d657=Math['min'](_0x5dd13f,0x5),_0x5dd13f=Math[_0x24d766(0x17b)](0x0,_0x5dd13f-_0x45d657),_0x2b6710['lastStandActive']=![],console[_0x24d766(0x34d)](_0x24d766(0x36f)+_0x45d657+_0x24d766(0x24d)+_0x5dd13f));const _0x35d654=_0x3916c6===_0x24d766(0x230)?'Slash':_0x3916c6===_0x24d766(0x2da)?_0x24d766(0x29d):_0x24d766(0x2c3);let _0x43ba89;if(_0x45d657>0x0)_0x43ba89=_0x18bc10[_0x24d766(0x2df)]+'\x20uses\x20'+_0x35d654+_0x24d766(0x2e4)+_0x2b6710['name']+_0x24d766(0x2f5)+_0x3eb578+_0x24d766(0x1b8)+_0x2b6710[_0x24d766(0x2df)]+_0x24d766(0x366)+_0x45d657+'\x20damage,\x20resulting\x20in\x20'+_0x5dd13f+_0x24d766(0x1b1);else _0x3916c6===_0x24d766(0x354)?_0x43ba89=_0x18bc10[_0x24d766(0x2df)]+_0x24d766(0x205)+_0x5dd13f+_0x24d766(0x2fb)+_0x2b6710['name']+_0x24d766(0x191):_0x43ba89=_0x18bc10[_0x24d766(0x2df)]+'\x20uses\x20'+_0x35d654+_0x24d766(0x2e4)+_0x2b6710['name']+'\x20for\x20'+_0x5dd13f+_0x24d766(0x1b1);_0x28cb90?log(_0x43ba89):log(_0x24d766(0x2eb)+_0x43ba89),_0x2b6710[_0x24d766(0x16b)]=Math['max'](0x0,_0x2b6710[_0x24d766(0x16b)]-_0x5dd13f),console[_0x24d766(0x34d)](_0x2b6710[_0x24d766(0x2df)]+_0x24d766(0x2b8)+_0x2b6710['health']),this[_0x24d766(0x223)](_0x2b6710),console[_0x24d766(0x34d)](_0x24d766(0x2d5)),this[_0x24d766(0x159)](),!this['gameOver']&&(console['log'](_0x24d766(0x15a)),this[_0x24d766(0x144)](_0x18bc10,_0x5dd13f,_0x3916c6));}else _0x3916c6===_0x24d766(0x1da)&&(this[_0x24d766(0x28e)](_0x18bc10,_0x2b6710,_0x3866be),!this[_0x24d766(0x234)]&&(console['log'](_0x24d766(0x182)),this['animatePowerup'](_0x18bc10)));(!this[_0x24d766(0x2f1)][this[_0x24d766(0x2f1)][_0x24d766(0x196)]-0x1]||this['roundStats'][this[_0x24d766(0x2f1)][_0x24d766(0x196)]-0x1]['completed'])&&this[_0x24d766(0x2f1)][_0x24d766(0x272)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x2a2c79=this['roundStats'][this[_0x24d766(0x2f1)][_0x24d766(0x196)]-0x1];return _0x2a2c79[_0x24d766(0x222)]+=_0x5dd13f,_0x2a2c79[_0x24d766(0x2c7)]+=0x1,console[_0x24d766(0x34d)]('handleMatch\x20completed,\x20damage\x20dealt:\x20'+_0x5dd13f),_0x5dd13f;}['cascadeTiles'](_0x50a219){const _0x2e0392=_0x272063;if(this['gameOver']){console['log'](_0x2e0392(0x2a4));return;}const _0x4e98bc=this[_0x2e0392(0x1b7)](),_0x295efe=_0x2e0392(0x2d9);for(let _0x89dbc2=0x0;_0x89dbc2<this[_0x2e0392(0x29c)];_0x89dbc2++){for(let _0x5f1f51=0x0;_0x5f1f51<this[_0x2e0392(0x1b3)];_0x5f1f51++){const _0x492979=this['board'][_0x5f1f51][_0x89dbc2];if(_0x492979[_0x2e0392(0x2fd)]&&_0x492979[_0x2e0392(0x2fd)]['style'][_0x2e0392(0x33a)]===_0x2e0392(0x24e)){const _0x260466=this[_0x2e0392(0x227)](_0x89dbc2,_0x5f1f51);_0x260466>0x0&&(_0x492979[_0x2e0392(0x2fd)][_0x2e0392(0x14c)]['add'](_0x295efe),_0x492979[_0x2e0392(0x2fd)][_0x2e0392(0x1c3)][_0x2e0392(0x33a)]=_0x2e0392(0x360)+_0x260466*this['tileSizeWithGap']+_0x2e0392(0x337));}}}this['renderBoard'](),_0x4e98bc?setTimeout(()=>{const _0x3ab710=_0x2e0392;if(this[_0x3ab710(0x234)]){console[_0x3ab710(0x34d)]('Game\x20over,\x20skipping\x20cascade\x20resolution');return;}this['sounds'][_0x3ab710(0x285)]['play']();const _0x101d6f=this[_0x3ab710(0x21d)](),_0x4134e0=document[_0x3ab710(0x25c)]('.'+_0x295efe);_0x4134e0[_0x3ab710(0x2b2)](_0x237015=>{const _0x170927=_0x3ab710;_0x237015[_0x170927(0x14c)][_0x170927(0x34f)](_0x295efe),_0x237015['style'][_0x170927(0x33a)]=_0x170927(0x374);}),!_0x101d6f&&_0x50a219();},0x12c):_0x50a219();}[_0x272063(0x1b7)](){const _0x2fa87a=_0x272063;let _0x26a892=![];for(let _0x58a8b5=0x0;_0x58a8b5<this['width'];_0x58a8b5++){let _0x3b6d5d=0x0;for(let _0x2c6490=this[_0x2fa87a(0x1b3)]-0x1;_0x2c6490>=0x0;_0x2c6490--){if(!this['board'][_0x2c6490][_0x58a8b5]['type'])_0x3b6d5d++;else _0x3b6d5d>0x0&&(this[_0x2fa87a(0x2af)][_0x2c6490+_0x3b6d5d][_0x58a8b5]=this[_0x2fa87a(0x2af)][_0x2c6490][_0x58a8b5],this[_0x2fa87a(0x2af)][_0x2c6490][_0x58a8b5]={'type':null,'element':null},_0x26a892=!![]);}for(let _0x32755c=0x0;_0x32755c<_0x3b6d5d;_0x32755c++){this[_0x2fa87a(0x2af)][_0x32755c][_0x58a8b5]=this[_0x2fa87a(0x29a)](),_0x26a892=!![];}}return _0x26a892;}[_0x272063(0x227)](_0x8420d6,_0x537f9f){let _0x2917f0=0x0;for(let _0x2e0219=_0x537f9f+0x1;_0x2e0219<this['height'];_0x2e0219++){if(!this['board'][_0x2e0219][_0x8420d6]['type'])_0x2917f0++;else break;}return _0x2917f0;}[_0x272063(0x28e)](_0x4e1057,_0x15a1f0,_0x38c8f8){const _0x49231f=_0x272063,_0x3310db=0x1-_0x15a1f0[_0x49231f(0x156)]*0.05;let _0x2fb459,_0xee0d23,_0x36d77e,_0x4ba587=0x1,_0x404b90='';if(_0x38c8f8===0x4)_0x4ba587=1.5,_0x404b90='\x20(50%\x20bonus\x20for\x20match-4)';else _0x38c8f8>=0x5&&(_0x4ba587=0x2,_0x404b90=_0x49231f(0x33b));if(_0x4e1057[_0x49231f(0x1e5)]===_0x49231f(0x155))_0xee0d23=0xa*_0x4ba587,_0x2fb459=Math[_0x49231f(0x2c8)](_0xee0d23*_0x3310db),_0x36d77e=_0xee0d23-_0x2fb459,_0x4e1057[_0x49231f(0x16b)]=Math[_0x49231f(0x2ec)](_0x4e1057[_0x49231f(0x1fd)],_0x4e1057[_0x49231f(0x16b)]+_0x2fb459),log(_0x4e1057[_0x49231f(0x2df)]+_0x49231f(0x1b9)+_0x2fb459+_0x49231f(0x310)+_0x404b90+(_0x15a1f0[_0x49231f(0x156)]>0x0?'\x20(originally\x20'+_0xee0d23+',\x20reduced\x20by\x20'+_0x36d77e+_0x49231f(0x2c5)+_0x15a1f0[_0x49231f(0x2df)]+_0x49231f(0x207):'')+'!');else{if(_0x4e1057[_0x49231f(0x1e5)]==='Boost\x20Attack')_0xee0d23=0xa*_0x4ba587,_0x2fb459=Math[_0x49231f(0x2c8)](_0xee0d23*_0x3310db),_0x36d77e=_0xee0d23-_0x2fb459,_0x4e1057[_0x49231f(0x164)]=!![],_0x4e1057['boostValue']=_0x2fb459,log(_0x4e1057['name']+_0x49231f(0x1cc)+_0x2fb459+'\x20damage'+_0x404b90+(_0x15a1f0[_0x49231f(0x156)]>0x0?_0x49231f(0x202)+_0xee0d23+_0x49231f(0x213)+_0x36d77e+'\x20due\x20to\x20'+_0x15a1f0[_0x49231f(0x2df)]+_0x49231f(0x207):'')+'!');else{if(_0x4e1057[_0x49231f(0x1e5)]===_0x49231f(0x140))_0xee0d23=0x7*_0x4ba587,_0x2fb459=Math[_0x49231f(0x2c8)](_0xee0d23*_0x3310db),_0x36d77e=_0xee0d23-_0x2fb459,_0x4e1057[_0x49231f(0x16b)]=Math[_0x49231f(0x2ec)](_0x4e1057[_0x49231f(0x1fd)],_0x4e1057['health']+_0x2fb459),log(_0x4e1057['name']+_0x49231f(0x1fe)+_0x2fb459+_0x49231f(0x310)+_0x404b90+(_0x15a1f0[_0x49231f(0x156)]>0x0?_0x49231f(0x202)+_0xee0d23+_0x49231f(0x213)+_0x36d77e+_0x49231f(0x2c5)+_0x15a1f0['name']+'\x27s\x20tactics)':'')+'!');else _0x4e1057[_0x49231f(0x1e5)]===_0x49231f(0x1ac)&&(_0xee0d23=0x5*_0x4ba587,_0x2fb459=Math[_0x49231f(0x2c8)](_0xee0d23*_0x3310db),_0x36d77e=_0xee0d23-_0x2fb459,_0x4e1057[_0x49231f(0x16b)]=Math[_0x49231f(0x2ec)](_0x4e1057[_0x49231f(0x1fd)],_0x4e1057[_0x49231f(0x16b)]+_0x2fb459),log(_0x4e1057[_0x49231f(0x2df)]+_0x49231f(0x239)+_0x2fb459+_0x49231f(0x310)+_0x404b90+(_0x15a1f0[_0x49231f(0x156)]>0x0?'\x20(originally\x20'+_0xee0d23+',\x20reduced\x20by\x20'+_0x36d77e+'\x20due\x20to\x20'+_0x15a1f0['name']+_0x49231f(0x207):'')+'!'));}}this[_0x49231f(0x223)](_0x4e1057);}[_0x272063(0x223)](_0x39ecf1){const _0x580ffe=_0x272063,_0x28f129=_0x39ecf1===this[_0x580ffe(0x15e)]?p1Health:p2Health,_0x3f7616=_0x39ecf1===this['player1']?p1Hp:p2Hp,_0x5e4b08=_0x39ecf1[_0x580ffe(0x16b)]/_0x39ecf1['maxHealth']*0x64;_0x28f129['style'][_0x580ffe(0x29c)]=_0x5e4b08+'%';let _0x25437e;if(_0x5e4b08>0x4b)_0x25437e=_0x580ffe(0x221);else{if(_0x5e4b08>0x32)_0x25437e=_0x580ffe(0x238);else _0x5e4b08>0x19?_0x25437e=_0x580ffe(0x171):_0x25437e=_0x580ffe(0x2ee);}_0x28f129[_0x580ffe(0x1c3)][_0x580ffe(0x208)]=_0x25437e,_0x3f7616[_0x580ffe(0x30c)]=_0x39ecf1[_0x580ffe(0x16b)]+'/'+_0x39ecf1[_0x580ffe(0x1fd)];}[_0x272063(0x200)](){const _0x7bd4a3=_0x272063;if(this[_0x7bd4a3(0x2e2)]===_0x7bd4a3(0x234)||this[_0x7bd4a3(0x234)]){console['log'](_0x7bd4a3(0x1fc));return;}this[_0x7bd4a3(0x15b)]=this[_0x7bd4a3(0x15b)]===this[_0x7bd4a3(0x15e)]?this[_0x7bd4a3(0x31b)]:this[_0x7bd4a3(0x15e)],this[_0x7bd4a3(0x2e2)]=this['currentTurn']===this[_0x7bd4a3(0x15e)]?_0x7bd4a3(0x257):_0x7bd4a3(0x1ba),turnIndicator['textContent']='Level\x20'+this['currentLevel']+_0x7bd4a3(0x276)+(this[_0x7bd4a3(0x15b)]===this['player1']?_0x7bd4a3(0x329):'Opponent')+_0x7bd4a3(0x252),log(_0x7bd4a3(0x2dc)+(this[_0x7bd4a3(0x15b)]===this[_0x7bd4a3(0x15e)]?_0x7bd4a3(0x329):'Opponent')),this['currentTurn']===this[_0x7bd4a3(0x31b)]&&setTimeout(()=>this[_0x7bd4a3(0x1ba)](),0x3e8);}[_0x272063(0x1ba)](){const _0x3d6340=_0x272063;if(this['gameState']!==_0x3d6340(0x1ba)||this['currentTurn']!==this[_0x3d6340(0x31b)])return;this[_0x3d6340(0x2e2)]=_0x3d6340(0x2fc);const _0x300e10=this[_0x3d6340(0x22b)]();_0x300e10?(log(this[_0x3d6340(0x31b)][_0x3d6340(0x2df)]+'\x20swaps\x20tiles\x20at\x20('+_0x300e10['x1']+',\x20'+_0x300e10['y1']+_0x3d6340(0x2cc)+_0x300e10['x2']+',\x20'+_0x300e10['y2']+')'),this[_0x3d6340(0x314)](_0x300e10['x1'],_0x300e10['y1'],_0x300e10['x2'],_0x300e10['y2'])):(log(this[_0x3d6340(0x31b)][_0x3d6340(0x2df)]+'\x20passes...'),this[_0x3d6340(0x200)]());}['findAIMove'](){const _0x2c5756=_0x272063;for(let _0x5ab2ba=0x0;_0x5ab2ba<this[_0x2c5756(0x1b3)];_0x5ab2ba++){for(let _0x137712=0x0;_0x137712<this[_0x2c5756(0x29c)];_0x137712++){if(_0x137712<this[_0x2c5756(0x29c)]-0x1&&this[_0x2c5756(0x165)](_0x137712,_0x5ab2ba,_0x137712+0x1,_0x5ab2ba))return{'x1':_0x137712,'y1':_0x5ab2ba,'x2':_0x137712+0x1,'y2':_0x5ab2ba};if(_0x5ab2ba<this[_0x2c5756(0x1b3)]-0x1&&this[_0x2c5756(0x165)](_0x137712,_0x5ab2ba,_0x137712,_0x5ab2ba+0x1))return{'x1':_0x137712,'y1':_0x5ab2ba,'x2':_0x137712,'y2':_0x5ab2ba+0x1};}}return null;}[_0x272063(0x165)](_0x2e905c,_0x53c030,_0x21fd41,_0x1b1474){const _0x1e9745=_0x272063,_0x44cd73={...this[_0x1e9745(0x2af)][_0x53c030][_0x2e905c]},_0x4c564d={...this['board'][_0x1b1474][_0x21fd41]};this[_0x1e9745(0x2af)][_0x53c030][_0x2e905c]=_0x4c564d,this[_0x1e9745(0x2af)][_0x1b1474][_0x21fd41]=_0x44cd73;const _0x36dd13=this[_0x1e9745(0x344)]()[_0x1e9745(0x196)]>0x0;return this[_0x1e9745(0x2af)][_0x53c030][_0x2e905c]=_0x44cd73,this[_0x1e9745(0x2af)][_0x1b1474][_0x21fd41]=_0x4c564d,_0x36dd13;}async['checkGameOver'](){const _0xd208ac=_0x272063;if(this[_0xd208ac(0x234)]||this[_0xd208ac(0x1e4)]){console['log'](_0xd208ac(0x347)+this[_0xd208ac(0x234)]+_0xd208ac(0x1f1)+this['isCheckingGameOver']+_0xd208ac(0x1a2)+this[_0xd208ac(0x1b4)]);return;}this[_0xd208ac(0x1e4)]=!![],console[_0xd208ac(0x34d)](_0xd208ac(0x16f)+this['currentLevel']+_0xd208ac(0x2f9)+this[_0xd208ac(0x15e)][_0xd208ac(0x16b)]+',\x20player2.health='+this[_0xd208ac(0x31b)][_0xd208ac(0x16b)]);const _0x14a10f=document['getElementById']('try-again');if(this[_0xd208ac(0x15e)][_0xd208ac(0x16b)]<=0x0){console[_0xd208ac(0x34d)]('Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)'),this[_0xd208ac(0x234)]=!![],this['gameState']=_0xd208ac(0x234),gameOver[_0xd208ac(0x30c)]=_0xd208ac(0x150),turnIndicator[_0xd208ac(0x30c)]=_0xd208ac(0x228),log(this[_0xd208ac(0x31b)][_0xd208ac(0x2df)]+_0xd208ac(0x1be)+this[_0xd208ac(0x15e)][_0xd208ac(0x2df)]+'!'),_0x14a10f[_0xd208ac(0x30c)]='TRY\x20AGAIN',document[_0xd208ac(0x36d)](_0xd208ac(0x1a1))[_0xd208ac(0x1c3)][_0xd208ac(0x16c)]=_0xd208ac(0x1d4);try{this[_0xd208ac(0x162)]['loss'][_0xd208ac(0x2c2)]();}catch(_0x3dc275){console['error'](_0xd208ac(0x1e2),_0x3dc275);}}else{if(this[_0xd208ac(0x31b)][_0xd208ac(0x16b)]<=0x0){console[_0xd208ac(0x34d)](_0xd208ac(0x299)),this[_0xd208ac(0x234)]=!![],this['gameState']=_0xd208ac(0x234),gameOver[_0xd208ac(0x30c)]=_0xd208ac(0x167),turnIndicator[_0xd208ac(0x30c)]=_0xd208ac(0x228),_0x14a10f[_0xd208ac(0x30c)]=this[_0xd208ac(0x1b4)]===opponentsConfig[_0xd208ac(0x196)]?'START\x20OVER':_0xd208ac(0x31a),document[_0xd208ac(0x36d)](_0xd208ac(0x1a1))['style'][_0xd208ac(0x16c)]=_0xd208ac(0x1d4);if(this[_0xd208ac(0x15b)]===this[_0xd208ac(0x15e)]){const _0x7c05e8=this[_0xd208ac(0x2f1)][this[_0xd208ac(0x2f1)]['length']-0x1];if(_0x7c05e8&&!_0x7c05e8[_0xd208ac(0x33c)]){_0x7c05e8['healthPercentage']=this['player1'][_0xd208ac(0x16b)]/this[_0xd208ac(0x15e)]['maxHealth']*0x64,_0x7c05e8['completed']=!![];const _0x511190=_0x7c05e8['matches']>0x0?_0x7c05e8['points']/_0x7c05e8[_0xd208ac(0x2c7)]/0x64*(_0x7c05e8['healthPercentage']+0x14)*(0x1+this[_0xd208ac(0x1b4)]/0x38):0x0;log('Calculating\x20round\x20score:\x20points='+_0x7c05e8[_0xd208ac(0x222)]+_0xd208ac(0x157)+_0x7c05e8[_0xd208ac(0x2c7)]+_0xd208ac(0x149)+_0x7c05e8[_0xd208ac(0x1bb)][_0xd208ac(0x1d0)](0x2)+_0xd208ac(0x169)+this[_0xd208ac(0x1b4)]),log(_0xd208ac(0x36c)+_0x7c05e8[_0xd208ac(0x222)]+_0xd208ac(0x34b)+_0x7c05e8[_0xd208ac(0x2c7)]+')\x20/\x20100)\x20*\x20('+_0x7c05e8[_0xd208ac(0x1bb)]+'\x20+\x2020))\x20*\x20(1\x20+\x20'+this[_0xd208ac(0x1b4)]+_0xd208ac(0x275)+_0x511190),this['grandTotalScore']+=_0x511190,log('Round\x20Won!\x20Points:\x20'+_0x7c05e8[_0xd208ac(0x222)]+_0xd208ac(0x185)+_0x7c05e8[_0xd208ac(0x2c7)]+_0xd208ac(0x279)+_0x7c05e8[_0xd208ac(0x1bb)]['toFixed'](0x2)+'%'),log(_0xd208ac(0x261)+_0x511190+',\x20Grand\x20Total\x20Score:\x20'+this['grandTotalScore']);}}await this[_0xd208ac(0x2c9)](this[_0xd208ac(0x1b4)]);this[_0xd208ac(0x1b4)]===opponentsConfig[_0xd208ac(0x196)]?(this[_0xd208ac(0x162)][_0xd208ac(0x283)][_0xd208ac(0x2c2)](),log(_0xd208ac(0x1d5)+this[_0xd208ac(0x19a)]),this[_0xd208ac(0x19a)]=0x0,await this[_0xd208ac(0x1b6)](),log(_0xd208ac(0x2c6))):(this['currentLevel']+=0x1,await this[_0xd208ac(0x1e9)](),console[_0xd208ac(0x34d)]('Progress\x20saved:\x20currentLevel='+this['currentLevel']),this[_0xd208ac(0x162)]['win']['play']());const _0x1231d6=themes[_0xd208ac(0x192)](_0x12836f=>_0x12836f[_0xd208ac(0x204)])[_0xd208ac(0x199)](_0xa75c2d=>_0xa75c2d[_0xd208ac(0x277)]===this[_0xd208ac(0x18b)]),_0x4244c9=_0x1231d6?.[_0xd208ac(0x170)]||'png',_0x38c196=this[_0xd208ac(0x1f2)]+_0xd208ac(0x160)+this[_0xd208ac(0x31b)][_0xd208ac(0x2df)][_0xd208ac(0x18f)]()[_0xd208ac(0x1d7)](/ /g,'-')+'.'+_0x4244c9,_0x968e63=document[_0xd208ac(0x36d)]('p2-image'),_0x3615a0=_0x968e63[_0xd208ac(0x244)];if(this[_0xd208ac(0x31b)][_0xd208ac(0x357)]===_0xd208ac(0x268)){if(_0x968e63[_0xd208ac(0x1c7)]!==_0xd208ac(0x217)){const _0x3c42ee=document[_0xd208ac(0x269)](_0xd208ac(0x268));_0x3c42ee['id']=_0xd208ac(0x1a9),_0x3c42ee[_0xd208ac(0x161)]=_0x38c196,_0x3c42ee[_0xd208ac(0x315)]=!![],_0x3c42ee[_0xd208ac(0x1c2)]=!![],_0x3c42ee['muted']=!![],_0x3c42ee[_0xd208ac(0x2fa)]=this['player2'][_0xd208ac(0x2df)],_0x3615a0[_0xd208ac(0x23e)](_0x3c42ee,_0x968e63);}else _0x968e63[_0xd208ac(0x161)]=_0x38c196;}else{if(_0x968e63[_0xd208ac(0x1c7)]!=='IMG'){const _0x356cde=document[_0xd208ac(0x269)](_0xd208ac(0x369));_0x356cde['id']='p2-image',_0x356cde[_0xd208ac(0x161)]=_0x38c196,_0x356cde[_0xd208ac(0x2fa)]=this[_0xd208ac(0x31b)][_0xd208ac(0x2df)],_0x3615a0['replaceChild'](_0x356cde,_0x968e63);}else _0x968e63['src']=_0x38c196;}const _0x494031=document['getElementById'](_0xd208ac(0x1a9));_0x494031[_0xd208ac(0x1c3)]['display']=_0xd208ac(0x1d4),_0x494031[_0xd208ac(0x14c)][_0xd208ac(0x22f)](_0xd208ac(0x2f2)),p1Image['classList'][_0xd208ac(0x22f)](_0xd208ac(0x2db)),this[_0xd208ac(0x1dd)]();}}this[_0xd208ac(0x1e4)]=![],console[_0xd208ac(0x34d)]('checkGameOver\x20completed:\x20currentLevel='+this[_0xd208ac(0x1b4)]+',\x20gameOver='+this['gameOver']);}async[_0x272063(0x2c9)](_0x912a1d){const _0xd62539=_0x272063,_0x5bb746={'level':_0x912a1d,'score':this[_0xd62539(0x19a)]};console[_0xd62539(0x34d)](_0xd62539(0x258)+_0x5bb746[_0xd62539(0x183)]+_0xd62539(0x303)+_0x5bb746[_0xd62539(0x318)]);try{const _0x5b3ad9=await fetch(_0xd62539(0x1cf),{'method':_0xd62539(0x2fe),'headers':{'Content-Type':_0xd62539(0x2cd)},'body':JSON[_0xd62539(0x1a0)](_0x5bb746)});if(!_0x5b3ad9['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x5b3ad9[_0xd62539(0x138)]);const _0xfccf75=await _0x5b3ad9[_0xd62539(0x14d)]();console['log'](_0xd62539(0x1eb),_0xfccf75),log('Level\x20'+_0xfccf75[_0xd62539(0x183)]+_0xd62539(0x1d9)+_0xfccf75[_0xd62539(0x318)]['toFixed'](0x2)),_0xfccf75[_0xd62539(0x138)]===_0xd62539(0x1c0)?log(_0xd62539(0x1d2)+_0xfccf75[_0xd62539(0x183)]+',\x20Score\x20'+_0xfccf75['score'][_0xd62539(0x1d0)](0x2)+',\x20Completions:\x20'+_0xfccf75[_0xd62539(0x13f)]):log(_0xd62539(0x2b9)+_0xfccf75[_0xd62539(0x29e)]);}catch(_0xd12792){console[_0xd62539(0x1a3)](_0xd62539(0x26f),_0xd12792),log(_0xd62539(0x211)+_0xd12792[_0xd62539(0x29e)]);}}[_0x272063(0x19c)](_0x10e625,_0x4680ea,_0x43791d,_0x5b8649){const _0x3c815b=_0x272063,_0x235fb0=_0x10e625[_0x3c815b(0x1c3)][_0x3c815b(0x33a)]||'',_0x5617fe=_0x235fb0[_0x3c815b(0x2ed)](_0x3c815b(0x2d7))?_0x235fb0[_0x3c815b(0x2cf)](/scaleX\([^)]+\)/)[0x0]:'';_0x10e625[_0x3c815b(0x1c3)][_0x3c815b(0x253)]=_0x3c815b(0x31d)+_0x5b8649/0x2/0x3e8+_0x3c815b(0x2ac),_0x10e625[_0x3c815b(0x1c3)]['transform']=_0x3c815b(0x215)+_0x4680ea+_0x3c815b(0x2f3)+_0x5617fe,_0x10e625['classList'][_0x3c815b(0x22f)](_0x43791d),setTimeout(()=>{const _0x52814b=_0x3c815b;_0x10e625[_0x52814b(0x1c3)][_0x52814b(0x33a)]=_0x5617fe,setTimeout(()=>{const _0x4adde2=_0x52814b;_0x10e625[_0x4adde2(0x14c)]['remove'](_0x43791d);},_0x5b8649/0x2);},_0x5b8649/0x2);}[_0x272063(0x144)](_0x1e4421,_0x2257a9,_0x4de396){const _0xa2dc56=_0x272063,_0x1cc24b=_0x1e4421===this[_0xa2dc56(0x15e)]?p1Image:p2Image,_0x43a85c=_0x1e4421===this['player1']?0x1:-0x1,_0x45d712=Math[_0xa2dc56(0x2ec)](0xa,0x2+_0x2257a9*0.4),_0x784d36=_0x43a85c*_0x45d712,_0x18c25c=_0xa2dc56(0x289)+_0x4de396;this[_0xa2dc56(0x19c)](_0x1cc24b,_0x784d36,_0x18c25c,0xc8);}['animatePowerup'](_0x1c829b){const _0x44c2db=_0x272063,_0x488111=_0x1c829b===this['player1']?p1Image:p2Image;this[_0x44c2db(0x19c)](_0x488111,0x0,_0x44c2db(0x2e7),0xc8);}[_0x272063(0x34e)](_0x278b34,_0x371a7f){const _0x318ba8=_0x272063,_0x338d76=_0x278b34===this[_0x318ba8(0x15e)]?p1Image:p2Image,_0x531178=_0x278b34===this[_0x318ba8(0x15e)]?-0x1:0x1,_0x1058d6=Math[_0x318ba8(0x2ec)](0xa,0x2+_0x371a7f*0.4),_0x578c3a=_0x531178*_0x1058d6;this['applyAnimation'](_0x338d76,_0x578c3a,_0x318ba8(0x1c6),0xc8);}}function randomChoice(_0x284b88){const _0x5eb9f2=_0x272063;return _0x284b88[Math[_0x5eb9f2(0x2c8)](Math[_0x5eb9f2(0x304)]()*_0x284b88['length'])];}function log(_0x5aec9e){const _0x1b7354=_0x272063,_0x33236c=document[_0x1b7354(0x36d)](_0x1b7354(0x2b6)),_0x2a9af9=document['createElement']('li');_0x2a9af9[_0x1b7354(0x30c)]=_0x5aec9e,_0x33236c['insertBefore'](_0x2a9af9,_0x33236c['firstChild']),_0x33236c[_0x1b7354(0x311)][_0x1b7354(0x196)]>0x32&&_0x33236c[_0x1b7354(0x2ae)](_0x33236c[_0x1b7354(0x137)]),_0x33236c[_0x1b7354(0x216)]=0x0;}function _0x441b(){const _0x6600e0=['VIDEO','Starting\x20fresh\x20at\x20Level\x201','progress-start-fresh','Error\x20clearing\x20progress:','touches','Progress\x20cleared','resolveMatches','playerCharacters','updateTheme_','row','#4CAF50','points','updateHealth','Resume\x20from\x20Level\x20','handleTouchEnd','\x22\x20alt=\x22','countEmptyBelow','Game\x20Over','hyperCube','Left','findAIMove','flip-p1','Minor\x20Régén','round','add','first-attack','showCharacterSelect:\x20Rendered\x20','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','p1-type','gameOver','Jarhead','tileSizeWithGap','ipfsPrefix','#FFC105','\x20uses\x20Minor\x20Regen,\x20restoring\x20','progress-resume','progress-message','isInitialMove:','type','replaceChild','\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>','text','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','addEventListener','8659277BmevdD','parentNode','\x20starts\x20at\x20full\x20strength\x20with\x20','initBoard','showProgressPopup:\x20User\x20chose\x20Restart','getAssets:\x20Theme\x20not\x20found:\x20','NFT_Unknown_','monstrocity','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','offsetY',',\x20damage:\x20','translate(0px,\x200px)','policyId','theme-group','HTTP\x20error!\x20Status:\x20','\x27s\x20Turn','transition','<p>Strength:\x20','mousemove','muted','playerTurn','Saving\x20score:\x20level=','\x20is\x20not\x20an\x20array,\x20skipping','/logo.png','selected','querySelectorAll','isArray','Texby','Clearing\x20matched\x20tiles:','onload','Round\x20Score:\x20','63474wtWNbQ','indexOf','left','warn','p1-tactics','\x22></video>','video','createElement','Horizontal\x20match\x20found\x20at\x20row\x20','Round\x20points\x20increased\x20from\x20','abs','px,\x200)','handleMatch','Error\x20saving\x20to\x20database:','Monstrocity_Unknown_','\x27s\x20','push','touchend','progress','\x20/\x2056)\x20=\x20','\x20-\x20','value','showProgressPopup',',\x20Health\x20Left:\x20','p1-image','Large','matched','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','className','boostValue','Restart','logo.png','.game-logo','finalWin','\x20health\x20before\x20match:\x20','cascade','base','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','filter','glow-','has','519255DcYMCK','468440JEqbPb','pop','usePowerup','</p>','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','lastStandActive','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','mouseup',',\x20tiles\x20to\x20clear:','.tile',',\x20Score\x20','race','getTileFromEvent','Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)','createRandomTile','updateCharacters_','width','Bite','message','ajax/save-monstrocity-progress.php','GET','Failed\x20to\x20save\x20progress:','showProgressPopup:\x20User\x20chose\x20Resume','resolveMatches\x20started,\x20gameOver:','Game\x20over,\x20skipping\x20cascadeTiles','Cascade\x20complete,\x20ending\x20turn','handleTouchStart','appendChild','isNFT','Main:\x20Game\x20initialized\x20successfully',',\x20Total\x20damage:\x20','clientY','s\x20linear','350khHTTl','removeChild','board','handleGameOverButton\x20completed:\x20currentLevel=','ipfsPrefixes','forEach','mp4','createCharacter','\x20steps\x20into\x20the\x20fray\x20with\x20','battle-log','https://www.skulliance.io/staking/sounds/voice_go.ogg','\x20health\x20after\x20damage:\x20','Score\x20Not\x20Saved:\x20','<video\x20src=\x22','progress-modal-content','updatePlayerDisplay','NFT\x20timeout','\x20after\x20multi-match\x20bonus!','\x27s\x20Boost\x20fades.','Raw\x20response\x20text:','ajax/clear-monstrocity-progress.php','play','Shadow\x20Strike','.game-container','\x20due\x20to\x20','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','matches','floor','saveScoreToDatabase','querySelector','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20',')\x20to\x20(','application/json','renderBoard:\x20Row\x20','match','split','mousedown','removeEventListener','backgroundSize','<p><strong>','Calling\x20checkGameOver\x20from\x20handleMatch','visible','scaleX','p1-size','falling','second-attack','winner','Turn\x20switched\x20to\x20','56bTnpFc','No\x20match,\x20reverting\x20tiles...','name','p2-tactics','Parsed\x20response:','gameState','setBackground','\x20on\x20','#theme-select\x20option[value=\x22','totalTiles','glow-power-up','timeEnd','background','time','Cascade:\x20','min','includes','#F44336','\x20goes\x20first!','showCharacterSelect:\x20Character\x20selected:\x20','roundStats','loser','px)\x20','https://www.skulliance.io/staking/sounds/badmove.ogg','\x20for\x20','scaleX(-1)','maxTouchPoints','tileTypes',',\x20player1.health=','alt','\x20damage\x20to\x20','animating','element','POST','showCharacterSelect:\x20this.player1\x20set:\x20',',\x20Match\x20bonus:\x20','init:\x20Async\x20initialization\x20completed','theme-select-button',',\x20score=','random','parse','checkMatches\x20completed,\x20returning\x20matches:','onclick','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','\x20size\x20','\x20matches:','NFT\x20HTTP\x20error!\x20Status:\x20','textContent','button','renderBoard:\x20Board\x20not\x20initialized,\x20skipping\x20render','Response\x20status:','\x20HP','children','restart','constructor:\x20initialTheme=','slideTiles','autoplay','Main:\x20Game\x20instance\x20created','Small','score','p2-strength','NEXT\x20LEVEL','player2','303514vuieal','transform\x20',')\x20has\x20no\x20element\x20to\x20animate','try-again','Drake','<p>Size:\x20','innerHTML','showCharacterSelect','updateTheme:\x20Board\x20rendered\x20for\x20active\x20game','multiMatch','Resumed\x20at\x20Level\x20','cover','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','Player','getAssets_','powerGem','\x20HP!','665bYpwiO','\x22\x20autoplay\x20loop\x20muted\x20alt=\x22','top','Processing\x20match:','handleTouchMove','\x20(opponentsConfig[','translate(','Total\x20damage\x20dealt:\x20','targetTile','trim','px)','p1-hp','Boost\x20Attack','transform','\x20(100%\x20bonus\x20for\x20match-5+)','completed','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','transform\x200.2s\x20ease','change-character','<img\x20loading=\x22eager\x22\x20src=\x22','Progress\x20saved:\x20Level\x20','p1-speed','Right','checkMatches','url(','Ouchie','checkGameOver\x20skipped:\x20gameOver=','addEventListeners','badMove','isDragging','\x20/\x20','\x20tiles!','log','animateRecoil','remove','Battle\x20Damaged','Found\x20','updateTileSizeWithGap','addEventListeners:\x20Player\x201\x20media\x20clicked','last-stand','tile\x20','none','mediaType','initGame','updateTheme','then','Cascading\x20tiles','turn-indicator','game-over','character-option','Goblin\x20Ganger','translate(0,\x20','https://www.skulliance.io/staking/icons/','speed','.png','Resume','IMG','\x27s\x20Last\x20Stand\x20mitigates\x20','Opponent','Boost\x20applied,\x20damage:\x20','img','Mandiblus','createDocumentFragment','Round\x20Score\x20Formula:\x20(((','getElementById','https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg','Last\x20Stand\x20applied,\x20mitigated\x20','offsetX','coordinates','flex','Loaded\x20opponent\x20for\x20level\x20','translate(0,\x200)','\x20but\x20sharpens\x20tactics\x20to\x20','setItem','handleMouseMove','lastChild','status','flip-p2','backgroundPosition','title','\x20to\x20','Animating\x20matched\x20tiles,\x20allMatchedTiles:','reduce','attempts','Regenerate','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','some','<p>Health:\x20','animateAttack','Error\x20in\x20resolveMatches:','policyIds','orientation','Failed\x20to\x20preload:\x20',',\x20healthPercentage=','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','mov','classList','json','66843nGXnAt','isTouchDevice','You\x20Lose!','<p>Type:\x20','swapPlayerCharacter','initializing',',\x20rows\x20','Heal','tactics',',\x20matches=','touchstart','checkGameOver','Game\x20not\x20over,\x20animating\x20attack','currentTurn','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20characters\x20available.\x20Please\x20try\x20another\x20theme.</p>','div','player1','progress-modal','battle-damaged/','src','sounds','Error\x20loading\x20progress:','boostActive','canMakeMatch','map','You\x20Win!','Mega\x20Multi-Match!\x20',',\x20level=','getItem','health','display','theme-options','handleMatch\x20started,\x20match:','checkGameOver\x20started:\x20currentLevel=','extension','#FFA500','Starting\x20Level\x20','AI\x20Opponent','Slime\x20Mind','preventDefault','<p>Power-Up:\x20','click','Error\x20saving\x20progress:','character-options','clientX','max','theme-select-container','flipCharacter','1fIMxqP','\x20with\x20Score\x20of\x20','p1-powerup','px,\x20','Animating\x20powerup','level','p2-health',',\x20Matches:\x20','Animating\x20recoil\x20for\x20defender:','No\x20matches\x20found,\x20returning\x20false','Multi-Match!\x20','group','Medium','theme','boosts\x20health\x20to\x20','touchmove','battle-damaged','toLowerCase','68ZJYZGw','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','flatMap','initBoard:\x20Board\x20initialized\x20with\x20dimensions','Damage\x20from\x20match:\x20','p2-size','length','playerCharactersConfig','\x20created\x20a\x20match\x20of\x20','find','grandTotalScore','px,\x200)\x20scale(1.05)','applyAnimation','px)\x20scale(1.05)','Craig','size','stringify','game-over-container',',\x20currentLevel=','error','backgroundImage','ajax/get-nft-assets.php',',\x20player2.health=','https://ipfs.io/ipfs/','png','p2-image','orientations','msMaxTouchPoints','Minor\x20Regen','</strong></p>','Main:\x20Error\x20initializing\x20game:','game-board','Dankle','\x20damage!','Reset\x20to\x20Level\x201:\x20currentLevel=','height','currentLevel','handleMouseDown','clearProgress','cascadeTilesWithoutRender','\x20damage,\x20but\x20','\x20uses\x20Heal,\x20restoring\x20','aiTurn','healthPercentage','Tile\x20at\x20(','selectedTile','\x20defeats\x20','https://www.skulliance.io/staking/sounds/select.ogg','success','Base','loop','style','ajax/load-monstrocity-progress.php','Sending\x20saveProgress\x20request\x20with\x20data:','glow-recoil','tagName','body','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','p2-hp','ajax/get-monstrocity-assets.php','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','Level\x20','<p>Speed:\x20','ajax/save-monstrocity-score.php','toFixed','p2-speed','Score\x20Saved:\x20Level\x20','gameTheme','block','Final\x20level\x20completed!\x20Final\x20score:\x20','progress-modal-buttons','replace',',\x20storedTheme=','\x20Score:\x20','power-up','renderBoard:\x20Board\x20rendered\x20successfully','Monstrocity\x20HTTP\x20error!\x20Status:\x20','renderBoard','p1-strength','getAssets:\x20Fetching\x20Monstrocity\x20assets','catch','column','Error\x20playing\x20lose\x20sound:','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','isCheckingGameOver','powerup','special-attack','...','p2-name','saveProgress','init','Save\x20response:','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','Merdock','setBackground:\x20Setting\x20background\x20to\x20','image','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=',',\x20isCheckingGameOver=','baseImagePath','inline-block','https://www.skulliance.io/staking/images/monstrocity/','12lHmiUa','\x20but\x20dulls\x20tactics\x20to\x20','Leader','imageUrl','updateOpponentDisplay','createCharacter:\x20config=','dataset','Game\x20over,\x20skipping\x20endTurn','maxHealth','\x20uses\x20Regen,\x20restoring\x20','leader','endTurn','\x27s\x20orientation\x20flipped\x20to\x20','\x20(originally\x20','/monstrocity.png','items','\x20uses\x20Last\x20Stand,\x20dealing\x20','strength','\x27s\x20tactics)','backgroundColor','character-select-container','Vertical\x20match\x20found\x20at\x20col\x20','Error\x20in\x20checkMatches:','Koipon',',\x20cols\x20','ipfs','getBoundingClientRect','dragDirection','Error\x20saving\x20score:\x20','init:\x20Starting\x20async\x20initialization',',\x20reduced\x20by\x20','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20','translateX(','scrollTop'];_0x441b=function(){return _0x6600e0;};return _0x441b();}const turnIndicator=document[_0x272063(0x36d)](_0x272063(0x35c)),p1Name=document[_0x272063(0x36d)]('p1-name'),p1Image=document[_0x272063(0x36d)](_0x272063(0x27a)),p1Health=document[_0x272063(0x36d)]('p1-health'),p1Hp=document['getElementById'](_0x272063(0x338)),p1Strength=document[_0x272063(0x36d)](_0x272063(0x1de)),p1Speed=document['getElementById'](_0x272063(0x342)),p1Tactics=document[_0x272063(0x36d)](_0x272063(0x266)),p1Size=document[_0x272063(0x36d)](_0x272063(0x2d8)),p1Powerup=document[_0x272063(0x36d)](_0x272063(0x180)),p1Type=document[_0x272063(0x36d)](_0x272063(0x233)),p2Name=document['getElementById'](_0x272063(0x1e8)),p2Image=document[_0x272063(0x36d)](_0x272063(0x1a9)),p2Health=document[_0x272063(0x36d)](_0x272063(0x184)),p2Hp=document['getElementById'](_0x272063(0x1ca)),p2Strength=document[_0x272063(0x36d)](_0x272063(0x319)),p2Speed=document[_0x272063(0x36d)](_0x272063(0x1d1)),p2Tactics=document[_0x272063(0x36d)](_0x272063(0x2e0)),p2Size=document['getElementById'](_0x272063(0x195)),p2Powerup=document[_0x272063(0x36d)]('p2-powerup'),p2Type=document[_0x272063(0x36d)]('p2-type'),battleLog=document[_0x272063(0x36d)](_0x272063(0x2b6)),gameOver=document[_0x272063(0x36d)](_0x272063(0x35d)),assetCache={};async function getAssets(_0x38c26a){const _0x3199a4=_0x272063;if(assetCache[_0x38c26a])return console['log']('getAssets:\x20Cache\x20hit\x20for\x20'+_0x38c26a),assetCache[_0x38c26a];console[_0x3199a4(0x2ea)](_0x3199a4(0x32a)+_0x38c26a);let _0x456e72=[];try{console[_0x3199a4(0x34d)](_0x3199a4(0x1df));const _0x853bab=await Promise[_0x3199a4(0x297)]([fetch(_0x3199a4(0x1cb),{'method':_0x3199a4(0x2fe),'headers':{'Content-Type':_0x3199a4(0x2cd)},'body':JSON['stringify']({'theme':'monstrocity'})}),new Promise((_0x216080,_0x51853d)=>setTimeout(()=>_0x51853d(new Error('Monstrocity\x20timeout')),0x1388))]);if(!_0x853bab['ok'])throw new Error(_0x3199a4(0x1dc)+_0x853bab[_0x3199a4(0x138)]);_0x456e72=await _0x853bab[_0x3199a4(0x14d)](),!Array[_0x3199a4(0x25d)](_0x456e72)&&(_0x456e72=[_0x456e72]),_0x456e72=_0x456e72['map']((_0xdc042c,_0x24682b)=>({..._0xdc042c,'theme':_0x3199a4(0x24a),'name':_0xdc042c[_0x3199a4(0x2df)]||_0x3199a4(0x270)+_0x24682b,'strength':_0xdc042c['strength']||0x4,'speed':_0xdc042c[_0x3199a4(0x362)]||0x4,'tactics':_0xdc042c[_0x3199a4(0x156)]||0x4,'size':_0xdc042c[_0x3199a4(0x19f)]||'Medium','type':_0xdc042c[_0x3199a4(0x23d)]||_0x3199a4(0x1c1),'powerup':_0xdc042c[_0x3199a4(0x1e5)]||_0x3199a4(0x140)}));}catch(_0x13ab47){console[_0x3199a4(0x1a3)]('getAssets:\x20Monstrocity\x20fetch\x20error:',_0x13ab47),_0x456e72=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':'Base','powerup':_0x3199a4(0x140),'theme':_0x3199a4(0x24a)},{'name':_0x3199a4(0x1b0),'strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x3199a4(0x317),'type':_0x3199a4(0x1c1),'powerup':_0x3199a4(0x155),'theme':'monstrocity'}];}if(_0x38c26a===_0x3199a4(0x24a))return assetCache[_0x38c26a]=_0x456e72,console['timeEnd']('getAssets_'+_0x38c26a),_0x456e72;const _0x359363=themes[_0x3199a4(0x192)](_0x196781=>_0x196781[_0x3199a4(0x204)])['find'](_0x1c3b26=>_0x1c3b26[_0x3199a4(0x277)]===_0x38c26a);if(!_0x359363)return console[_0x3199a4(0x265)](_0x3199a4(0x248)+_0x38c26a),assetCache[_0x38c26a]=_0x456e72,console['timeEnd']('getAssets_'+_0x38c26a),_0x456e72;const _0x34adfe=_0x359363[_0x3199a4(0x146)]?_0x359363[_0x3199a4(0x146)]['split'](',')[_0x3199a4(0x288)](_0x189900=>_0x189900[_0x3199a4(0x336)]()):[];if(!_0x34adfe[_0x3199a4(0x196)])return assetCache[_0x38c26a]=_0x456e72,console['timeEnd'](_0x3199a4(0x32a)+_0x38c26a),_0x456e72;let _0x301c32=[];try{const _0x532361=_0x34adfe['map']((_0x4d0044,_0x41c2ab)=>({'policyId':_0x4d0044,'orientation':_0x359363[_0x3199a4(0x1aa)]?.['split'](',')[_0x41c2ab]||_0x3199a4(0x343),'ipfsPrefix':_0x359363[_0x3199a4(0x2b1)]?.[_0x3199a4(0x2d0)](',')[_0x41c2ab]||_0x3199a4(0x1a7)})),_0x56de96=await Promise[_0x3199a4(0x297)]([fetch(_0x3199a4(0x1a5),{'method':_0x3199a4(0x2fe),'headers':{'Content-Type':'application/json'},'body':JSON[_0x3199a4(0x1a0)]({'policyIds':_0x532361['map'](_0xfcd7c3=>_0xfcd7c3[_0x3199a4(0x24f)]),'theme':_0x38c26a})}),new Promise((_0x2cb059,_0x5d13b0)=>setTimeout(()=>_0x5d13b0(new Error(_0x3199a4(0x2bd))),0x2710))]);if(!_0x56de96['ok'])throw new Error(_0x3199a4(0x30b)+_0x56de96['status']);const _0x2a11e1=await _0x56de96[_0x3199a4(0x14d)]();_0x301c32=Array[_0x3199a4(0x25d)](_0x2a11e1)?_0x2a11e1:[_0x2a11e1],_0x301c32=_0x301c32[_0x3199a4(0x288)](_0xe65274=>_0xe65274&&_0xe65274[_0x3199a4(0x2df)]&&_0xe65274[_0x3199a4(0x20e)])[_0x3199a4(0x166)]((_0x41bc3a,_0x5137ee)=>({..._0x41bc3a,'theme':_0x38c26a,'name':_0x41bc3a[_0x3199a4(0x2df)]||_0x3199a4(0x249)+_0x5137ee,'strength':_0x41bc3a['strength']||0x4,'speed':_0x41bc3a[_0x3199a4(0x362)]||0x4,'tactics':_0x41bc3a[_0x3199a4(0x156)]||0x4,'size':_0x41bc3a[_0x3199a4(0x19f)]||'Medium','type':_0x41bc3a[_0x3199a4(0x23d)]||'Base','powerup':_0x41bc3a[_0x3199a4(0x1e5)]||_0x3199a4(0x140),'policyId':_0x41bc3a[_0x3199a4(0x24f)]||_0x532361[0x0][_0x3199a4(0x24f)],'ipfs':_0x41bc3a['ipfs']||''}));}catch(_0x1db54b){console[_0x3199a4(0x1a3)]('getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20'+_0x38c26a+':',_0x1db54b);}const _0x308421=[..._0x456e72,..._0x301c32];return assetCache[_0x38c26a]=_0x308421,console['timeEnd']('getAssets_'+_0x38c26a),_0x308421;}document[_0x272063(0x242)]('DOMContentLoaded',function(){var _0x26865c=function(){const _0xc31f4e=_0x3e75;var _0x59fdba=localStorage[_0xc31f4e(0x16a)]('gameTheme')||_0xc31f4e(0x24a);getAssets(_0x59fdba)[_0xc31f4e(0x35a)](function(_0x377127){const _0x11c511=_0xc31f4e;console[_0x11c511(0x34d)]('Main:\x20Player\x20characters\x20loaded:',_0x377127);var _0xfdc9a=new MonstrocityMatch3(_0x377127,_0x59fdba);console['log'](_0x11c511(0x316)),_0xfdc9a[_0x11c511(0x1ea)]()[_0x11c511(0x35a)](function(){const _0x2bca85=_0x11c511;console[_0x2bca85(0x34d)](_0x2bca85(0x2a9)),document[_0x2bca85(0x2ca)](_0x2bca85(0x282))['src']=_0xfdc9a[_0x2bca85(0x1f2)]+'logo.png';});})[_0xc31f4e(0x1e0)](function(_0x3274f1){const _0x30ad39=_0xc31f4e;console['error'](_0x30ad39(0x1ae),_0x3274f1);});};_0x26865c();});
  </script>
</body>
</html>
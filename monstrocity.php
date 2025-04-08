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
      background-image: url(https://www.skulliance.io/staking/images/monstrocity/monstrocity.png);
      background-size: cover;
      background-position: center;
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
	  max-height: 1160px;
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
      width: 230px;
      padding: 15px;
      background-color: #165777;
      border-radius: 5px;
      text-align: center;
      flex-shrink: 0;
      position: relative;
      top: -225px;
      min-height: 510px;
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
      top: -945px;
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
      display: flex;
      align-items: center;
      margin: 5px 0;
      font-size: 0.9em;
    }

    .legend-tile {
      width: 20px;
      height: 20px;
      margin-right: 10px;
      display: inline-block;
      border: 1px solid #555;
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
	  border-radius: 10px;
	  z-index: 100;
	  width: 100%;
	  height: 100%;
	  max-width: 931px;
	  max-height: 1083px;
	  overflow-y: auto;
	  display: block; /* Initially visible */
	  border: 3px solid black;
	  text-align: center;
	}

    #character-select-container h2 {
      text-align: center;
      margin-bottom: 20px;
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

    @media (max-width: 950px) {
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
      
      #game-over-container {
        top: -1150px;
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
    <button id="change-character" style="display: none;">Switch Monster</button>
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
        <li><span class="legend-tile first-attack"><img src="https://www.skulliance.io/staking/icons/first-attack.png" alt="First Attack"></span><strong>First Attack (Slash): </strong> Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile second-attack"><img src="https://www.skulliance.io/staking/icons/second-attack.png" alt="Second Attack"></span><strong>Second Attack (Bite): </strong> Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile special-attack"><img src="https://www.skulliance.io/staking/icons/special-attack.png" alt="Special Attack"></span><strong>Special Attack (Shadow Strike): </strong> Deals 1.2× damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile power-up"><img src="https://www.skulliance.io/staking/icons/power-up.png" alt="Power Up"></span><strong>Power-Up: </strong> Activates a random powerup (see below)</li>
        <li><span class="legend-tile last-stand"><img src="https://www.skulliance.io/staking/icons/last-stand.png" alt="Last Stand"></span><strong>Last Stand: </strong> Deals damage and mitigates 5 damage on the next attack received</li>
      </ul>
      <br>
      <h3>Power-Up Effects</h3>
      <ul>
        <li><strong>Heal (Bloody): </strong> Restores 10 HP (reduced by enemy tactics)</li>
        <li><strong>Boost Attack (Cardano): </strong> Adds +10 damage to the next attack (reduced by enemy tactics)</li>
        <li><strong>Regenerate (ADA): </strong> Restores 7 HP (reduced by enemy tactics)</li>
        <li><strong>Minor Regen (None): </strong> Restores 5 HP (reduced by enemy tactics)</li>
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
	  <p><a href="https://www.jpg.store/collection/monstrocity" target="_blank">Purchase Monstrocity NFTs</a> to Add More Characters</p>
	  <p><a href="https://www.skulliance.io/staking" target="_blank">Visit Skulliance Staking</a> to Connect Wallet(s)</p>
	  <p>Rewards, Leaderboards, and Game Saves Available to Skulliance Stakers</p>
      <div id="character-options"></div>
    </div>
  <script>
    const opponentsConfig = [
      { name: "Craig", strength: 1, speed: 1, tactics: 1, size: "Medium", type: "Base", powerup: "Minor Regen" },
      { name: "Merdock", strength: 1, speed: 1, tactics: 1, size: "Large", type: "Base", powerup: "Minor Regen" },
      { name: "Goblin Ganger", strength: 2, speed: 2, tactics: 2, size: "Small", type: "Base", powerup: "Minor Regen" },
      { name: "Texby", strength: 2, speed: 2, tactics: 2, size: "Medium", type: "Base", powerup: "Minor Regen" },
      { name: "Mandiblus", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Base", powerup: "Regenerate" },
      { name: "Koipon", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Base", powerup: "Regenerate" },
      { name: "Slime Mind", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
      { name: "Billandar and Ted", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
      { name: "Dankle", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Base", powerup: "Boost Attack" },
      { name: "Jarhead", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Base", powerup: "Boost Attack" },
      { name: "Spydrax", strength: 6, speed: 6, tactics: 6, size: "Small", type: "Base", powerup: "Heal" },
      { name: "Katastrophy", strength: 7, speed: 7, tactics: 7, size: "Large", type: "Base", powerup: "Heal" },
      { name: "Ouchie", strength: 7, speed: 7, tactics: 7, size: "Medium", type: "Base", powerup: "Heal" },
      { name: "Drake", strength: 8, speed: 7, tactics: 7, size: "Medium", type: "Base", powerup: "Heal" },
      { name: "Craig", strength: 1, speed: 1, tactics: 1, size: "Medium", type: "Leader", powerup: "Minor Regen" },
      { name: "Merdock", strength: 1, speed: 1, tactics: 1, size: "Large", type: "Leader", powerup: "Minor Regen" },
      { name: "Goblin Ganger", strength: 2, speed: 2, tactics: 2, size: "Small", type: "Leader", powerup: "Minor Regen" },
      { name: "Texby", strength: 2, speed: 2, tactics: 2, size: "Medium", type: "Leader", powerup: "Minor Regen" },
      { name: "Mandiblus", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
      { name: "Koipon", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
      { name: "Slime Mind", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Leader", powerup: "Regenerate" },
      { name: "Billandar and Ted", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Leader", powerup: "Regenerate" },
      { name: "Dankle", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Leader", powerup: "Boost Attack" },
      { name: "Jarhead", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Leader", powerup: "Boost Attack" },
      { name: "Spydrax", strength: 6, speed: 6, tactics: 6, size: "Small", type: "Leader", powerup: "Heal" },
      { name: "Katastrophy", strength: 7, speed: 7, tactics: 7, size: "Large", type: "Leader", powerup: "Heal" },
      { name: "Ouchie", strength: 7, speed: 7, tactics: 7, size: "Medium", type: "Leader", powerup: "Heal" },
      { name: "Drake", strength: 8, speed: 7, tactics: 7, size: "Medium", type: "Leader", powerup: "Heal" }
    ];
	
	 /*
    const playerCharactersConfig = [
        { name: "Craig", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Merdock", strength: 4, speed: 4, tactics: 4, size: "Large", type: "Base", powerup: "Regenerate" },
        { name: "Goblin Ganger", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
        { name: "Texby", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Mandiblus", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Koipon", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Slime Mind", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
        { name: "Billandar and Ted", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Dankle", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Jarhead", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Spydrax", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
        { name: "Katastrophy", strength: 4, speed: 4, tactics: 4, size: "Large", type: "Base", powerup: "Regenerate" },
        { name: "Ouchie", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Drake", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Craig", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Merdock", strength: 3, speed: 3, tactics: 3, size: "Large", type: "Leader", powerup: "Regenerate" },
        { name: "Goblin Ganger", strength: 3, speed: 3, tactics: 3, size: "Small", type: "Leader", powerup: "Regenerate" },
        { name: "Texby", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Mandiblus", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Koipon", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Slime Mind", strength: 3, speed: 3, tactics: 3, size: "Small", type: "Leader", powerup: "Regenerate" },
        { name: "Billandar and Ted", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Dankle", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Jarhead", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Spydrax", strength: 3, speed: 3, tactics: 3, size: "Small", type: "Leader", powerup: "Regenerate" },
        { name: "Katastrophy", strength: 3, speed: 3, tactics: 3, size: "Large", type: "Leader", powerup: "Regenerate" },
        { name: "Ouchie", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Drake", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Craig", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Merdock", strength: 5, speed: 5, tactics: 5, size: "Large", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Goblin Ganger", strength: 5, speed: 5, tactics: 5, size: "Small", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Texby", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Mandiblus", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Koipon", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Slime Mind", strength: 5, speed: 5, tactics: 5, size: "Small", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Billandar and Ted", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Dankle", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Jarhead", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Spydrax", strength: 5, speed: 5, tactics: 5, size: "Small", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Katastrophy", strength: 5, speed: 5, tactics: 5, size: "Large", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Ouchie", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Drake", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" }
    ];*/

    const characterDirections = {
      "Billandar and Ted": "Left",
      "Craig": "Left",
      "Dankle": "Left",
      "Drake": "Right",
      "Goblin Ganger": "Left",
      "Jarhead": "Right",
      "Katastrophy": "Right",
      "Koipon": "Left",
      "Mandiblus": "Left",
      "Merdock": "Left",
      "Ouchie": "Left",
      "Slime Mind": "Right",
      "Spydrax": "Right",
      "Texby": "Left"
    };

    class MonstrocityMatch3 {
		constructor(playerCharactersConfig) {
		    this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
		    this.width = 5;
		    this.height = 5;
		    this.board = [];
		    this.selectedTile = null;
		    this.gameOver = false;
		    this.currentTurn = null;
		    this.player1 = null;
		    this.player2 = null;
		    this.gameState = "initializing";
		    this.isDragging = false;
		    this.targetTile = null;
		    this.dragDirection = null;
		    this.offsetX = 0;
		    this.offsetY = 0;
		    this.currentLevel = 1;
		    this.playerCharacters = playerCharactersConfig.map(config => this.createCharacter(config));
		    this.isCheckingGameOver = false;

		    this.tileTypes = ["first-attack", "second-attack", "special-attack", "power-up", "last-stand"];
		    this.roundStats = [];
		    this.grandTotalScore = 0;

		    this.sounds = {
		      match: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
		      cascade: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
		      badMove: new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),
		      gameOver: new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),
		      reset: new Audio('https://www.skulliance.io/staking/sounds/voice_go.ogg'),
		      loss: new Audio('https://www.skulliance.io/staking/sounds/skullcoinlose.ogg'),
		      win: new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),
		      finalWin: new Audio('https://www.skulliance.io/staking/sounds/badgeawarded.ogg'),
		      powerGem: new Audio('https://www.skulliance.io/staking/sounds/powergem_created.ogg'),
		      hyperCube: new Audio('https://www.skulliance.io/staking/sounds/hypercube_create.ogg'),
		      multiMatch: new Audio('https://www.skulliance.io/staking/sounds/speedmatch1.ogg')
		    };

		    // Removed the initial game-board visibility hiding since the entire container is hidden
		    this.updateTileSizeWithGap();
		    this.addEventListeners();
		  }
		  
		  async init() {
		      console.log("init: Starting async initialization");
		      await this.showCharacterSelect(true);

		      // Progress check should only run here, once
		      const progressData = await this.loadProgress();
		      const { loadedLevel, loadedScore, hasProgress } = progressData;

		      if (hasProgress) {
		        console.log(`init: Prompting with loadedLevel=${loadedLevel}, loadedScore=${loadedScore}`);
		        const userChoice = await this.showProgressPopup(loadedLevel, loadedScore);
		        if (userChoice) {
		          this.currentLevel = loadedLevel;
		          this.grandTotalScore = loadedScore;
		          log(`Resumed at Level ${this.currentLevel}, Score ${this.grandTotalScore}`);
		        } else {
		          this.currentLevel = 1;
		          this.grandTotalScore = 0;
		          await this.clearProgress();
		          log(`Starting fresh at Level 1`);
		        }
		      } else {
		        this.currentLevel = 1;
		        this.grandTotalScore = 0;
		        log(`No saved progress found, starting at Level 1`);
		      }

		      console.log("init: Async initialization completed");
		    }
	  
		async saveProgress() {
		  const data = {
		    currentLevel: this.currentLevel, // Now 1-28
		    grandTotalScore: this.grandTotalScore
		  };

		  console.log("Sending saveProgress request with data:", data);

		  try {
		    const response = await fetch('ajax/save-monstrocity-progress.php', {
		      method: 'POST',
		      headers: { 'Content-Type': 'application/json' },
		      body: JSON.stringify(data)
		    });

		    console.log("Response status:", response.status);

		    const responseText = await response.text();
		    console.log("Raw response text:", responseText);

		    if (!response.ok) {
		      throw new Error(`HTTP error! Status: ${response.status}`);
		    }

		    const result = JSON.parse(responseText);
		    console.log("Parsed response:", result);

		    if (result.status === 'success') {
		      log('Progress saved: Level ' + this.currentLevel); // No +1 needed
		    } else {
		      console.error('Failed to save progress:', result.message);
		    }
		  } catch (error) {
		    console.error('Error saving progress:', error);
		  }
		}

		async loadProgress() {
		    try {
		      console.log("Fetching progress from ajax/load-monstrocity-progress.php");
		      const response = await fetch('ajax/load-monstrocity-progress.php', {
		        method: 'GET',
		        headers: { 'Content-Type': 'application/json' }
		      });

		      console.log("Response status:", response.status);
		      if (!response.ok) {
		        throw new Error(`HTTP error! Status: ${response.status}`);
		      }

		      const result = await response.json();
		      console.log("Parsed response:", result);
		      if (result.status === 'success' && result.progress) {
		        const progress = result.progress;
		        return {
		          loadedLevel: progress.currentLevel || 1,
		          loadedScore: progress.grandTotalScore || 0,
		          hasProgress: true
		        };
		      } else {
		        console.log("No progress found or status not success:", result);
		        return { loadedLevel: 1, loadedScore: 0, hasProgress: false };
		      }
		    } catch (error) {
		      console.error('Error loading progress:', error);
		      return { loadedLevel: 1, loadedScore: 0, hasProgress: false };
		    }
		  }

	  async clearProgress() {
	    try {
	      const response = await fetch('ajax/clear-monstrocity-progress.php', {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' }
	      });

	      if (!response.ok) {
	        throw new Error(`HTTP error! Status: ${response.status}`);
	      }

	      const result = await response.json();
	      if (result.status === 'success') {
	        this.currentLevel = 1; // Reset to level 1 (was 0)
	        this.grandTotalScore = 0;
	        log('Progress cleared');
	      }
	    } catch (error) {
	      console.error('Error clearing progress:', error);
	    }
	  }

      updateTileSizeWithGap() {
        const boardElement = document.getElementById("game-board");
        const boardWidth = boardElement.offsetWidth || 300;
        this.tileSizeWithGap = (boardWidth - (0.5 * (this.width - 1))) / this.width;
      }

      createCharacter(config) {
        let typeFolder;
        switch (config.type) {
          case "Base":
            typeFolder = "base";
            break;
          case "Leader":
            typeFolder = "leader";
            break;
          case "Battle Damaged":
            typeFolder = "battle-damaged";
            break;
          default:
            typeFolder = "base";
        }
        const imageUrl = `https://www.skulliance.io/staking/images/monstrocity/${typeFolder}/${config.name.toLowerCase().replace(/ /g, '-')}.png`;
        
        let baseHealth;
        switch (config.type) {
          case "Leader":
            baseHealth = 100;
            break;
          case "Battle Damaged":
            baseHealth = 70;
            break;
          case "Base":
          default:
            baseHealth = 85;
        }
        
        let healthModifier = 1;
        let tacticsAdjust = 0;
        switch (config.size) {
          case "Large":
            healthModifier = 1.2;
            tacticsAdjust = config.tactics > 1 ? -2 : 0;
            break;
          case "Small":
            healthModifier = 0.8;
            tacticsAdjust = config.tactics < 6 ? 2 : 7 - config.tactics;
            break;
          case "Medium":
            healthModifier = 1;
            tacticsAdjust = 0;
            break;
        }
        
        const adjustedHealth = Math.round(baseHealth * healthModifier);
        const adjustedTactics = Math.max(1, Math.min(7, config.tactics + tacticsAdjust));

        return {
          name: config.name,
          type: config.type,
          strength: config.strength,
          speed: config.speed,
          tactics: adjustedTactics,
          size: config.size,
          powerup: config.powerup,
          health: adjustedHealth,
          maxHealth: adjustedHealth,
          boostActive: false,
          boostValue: 0,
          lastStandActive: false,
          imageUrl
        };
      }

	  async showCharacterSelect(isInitial = false) {
	      console.log(`showCharacterSelect: Called with isInitial=${isInitial}`);
	      const container = document.getElementById("character-select-container");
	      const optionsDiv = document.getElementById("character-options");
	      optionsDiv.innerHTML = "";
	      container.style.display = "block";

	      this.playerCharacters.forEach((character, index) => {
	        const option = document.createElement("div");
	        option.className = "character-option";
	        option.innerHTML = `
	          <img src="${character.imageUrl}" alt="${character.name}">
	          <p><strong>${character.name}</strong></p>
	          <p>Type: ${character.type}</p>
	          <p>Health: ${character.maxHealth}</p>
	          <p>Strength: ${character.strength}</p>
	          <p>Speed: ${character.speed}</p>
	          <p>Tactics: ${character.tactics}</p>
	          <p>Size: ${character.size}</p>
	          <p>Power-Up: ${character.powerup}</p>
	        `;
	        option.addEventListener("click", () => {
	          console.log(`showCharacterSelect: Character selected: ${character.name}`);
	          container.style.display = "none";
	          if (isInitial) {
	            this.player1 = { ...character };
	            console.log(`showCharacterSelect: this.player1 set: ${this.player1.name}`);
	            this.initGame();
	          } else {
	            this.swapPlayerCharacter(character);
	          }
	        });
	        optionsDiv.appendChild(option);
	      });
	      // No progress check here
	    }

	  swapPlayerCharacter(newCharacter) {
	    const oldHealth = this.player1.health;
	    const oldMaxHealth = this.player1.maxHealth;
	    const newInstance = { ...newCharacter };
  
	    const healthPercentage = Math.min(1, oldHealth / oldMaxHealth);
	    newInstance.health = Math.round(newInstance.maxHealth * healthPercentage);
	    newInstance.health = Math.max(0, Math.min(newInstance.maxHealth, newInstance.health));
  
	    newInstance.boostActive = false;
	    newInstance.boostValue = 0;
	    newInstance.lastStandActive = false;
  
	    this.player1 = newInstance;
	    this.updatePlayerDisplay();
	    this.updateHealth(this.player1);
  
	    log(`${this.player1.name} steps into the fray with ${this.player1.health}/${this.player1.maxHealth} HP!`);
  
	    this.currentTurn = this.player1.speed > this.player2.speed 
	      ? this.player1 
	      : this.player2.speed > this.player1.speed 
	        ? this.player2 
	        : this.player1.strength >= this.player2.strength 
	          ? this.player1 
	          : this.player2;
	    turnIndicator.textContent = `Level ${this.currentLevel} - ${this.currentTurn === this.player1 ? "Player" : "Opponent"}'s Turn`;
  
	    if (this.currentTurn === this.player2 && this.gameState !== "gameOver") {
	      setTimeout(() => this.aiTurn(), 1000);
	    }
	  }
	  
	  showProgressPopup(loadedLevel, loadedScore) {
	      console.log(`showProgressPopup: Displaying popup for level=${loadedLevel}, score=${loadedScore}`);
	      return new Promise((resolve) => {
	        const modal = document.createElement("div");
	        modal.id = "progress-modal";
	        modal.className = "progress-modal";

	        const modalContent = document.createElement("div");
	        modalContent.className = "progress-modal-content";

	        const message = document.createElement("p");
	        message.id = "progress-message";
	        message.textContent = `Resume from Level ${loadedLevel} with Score of ${loadedScore}?`;
	        modalContent.appendChild(message);

	        const modalButtons = document.createElement("div");
	        modalButtons.className = "progress-modal-buttons";

	        const resumeButton = document.createElement("button");
	        resumeButton.id = "progress-resume";
	        resumeButton.textContent = "Resume";
	        modalButtons.appendChild(resumeButton);

	        const startFreshButton = document.createElement("button");
	        startFreshButton.id = "progress-start-fresh";
	        startFreshButton.textContent = "Restart";
	        modalButtons.appendChild(startFreshButton);

	        modalContent.appendChild(modalButtons);
	        modal.appendChild(modalContent);
	        document.body.appendChild(modal);

	        modal.style.display = "flex";

	        const onResume = () => {
	          console.log("showProgressPopup: User chose Resume");
	          modal.style.display = "none";
	          document.body.removeChild(modal);
	          resumeButton.removeEventListener("click", onResume);
	          startFreshButton.removeEventListener("click", onStartFresh);
	          resolve(true);
	        };

	        const onStartFresh = () => {
	          console.log("showProgressPopup: User chose Restart");
	          modal.style.display = "none";
	          document.body.removeChild(modal);
	          resumeButton.removeEventListener("click", onResume);
	          startFreshButton.removeEventListener("click", onStartFresh);
	          resolve(false);
	        };

	        resumeButton.addEventListener("click", onResume);
	        startFreshButton.addEventListener("click", onStartFresh);
	      });
	    }

		initGame() {
			console.log(`initGame: Started with this.currentLevel=${this.currentLevel}`);
		    const gameContainer = document.querySelector(".game-container");
		    const gameBoard = document.getElementById("game-board");
		    gameContainer.style.display = "block";
		    gameBoard.style.visibility = "visible";

		    this.sounds.reset.play();
		    log(`Starting Level ${this.currentLevel}...`);

		    this.player2 = this.createCharacter(opponentsConfig[this.currentLevel - 1]);
		    console.log(`Loaded opponent for level ${this.currentLevel}: ${this.player2.name} (opponentsConfig[${this.currentLevel - 1}])`);

		    this.player1.health = this.player1.maxHealth;

		    this.currentTurn = this.player1.speed > this.player2.speed 
		      ? this.player1 
		      : this.player2.speed > this.player1.speed 
		        ? this.player2 
		        : this.player1.strength >= this.player2.strength 
		          ? this.player1 
		          : this.player2;
		    this.gameState = "initializing";
		    this.gameOver = false;

		    this.roundStats = [];

		    p1Image.classList.remove('winner', 'loser');
		    p2Image.classList.remove('winner', 'loser');
		    this.updatePlayerDisplay();
		    this.updateOpponentDisplay();

		    if (characterDirections[this.player1.name] === "Left") {
		      p1Image.style.transform = "scaleX(-1)";
		    } else {
		      p1Image.style.transform = "none";
		    }

		    if (characterDirections[this.player2.name] === "Right") {
		      p2Image.style.transform = "scaleX(-1)";
		    } else {
		      p2Image.style.transform = "none";
		    }

		    this.updateHealth(this.player1);
		    this.updateHealth(this.player2);

		    battleLog.innerHTML = "";
		    gameOver.textContent = "";

		    if (this.player1.size !== "Medium") {
		      log(`${this.player1.name}'s ${this.player1.size} size ${this.player1.size === "Large" ? "boosts health to " + this.player1.maxHealth + " but dulls tactics to " + this.player1.tactics : "drops health to " + this.player1.maxHealth + " but sharpens tactics to " + this.player1.tactics}!`);
		    }
		    if (this.player2.size !== "Medium") {
		      log(`${this.player2.name}'s ${this.player2.size} size ${this.player2.size === "Large" ? "boosts health to " + this.player2.maxHealth + " but dulls tactics to " + this.player2.tactics : "drops health to " + this.player2.maxHealth + " but sharpens tactics to " + this.player2.tactics}!`);
		    }

		    log(`${this.player1.name} starts at full strength with ${this.player1.health}/${this.player1.maxHealth} HP!`);
		    log(`${this.currentTurn.name} goes first!`);

		    this.initBoard();
		    this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
		    turnIndicator.textContent = `Level ${this.currentLevel} - ${this.currentTurn === this.player1 ? "Player" : "Opponent"}'s Turn`;

		    if (this.playerCharacters.length > 1) {
		      document.getElementById("change-character").style.display = "inline-block";
		    }

		    if (this.currentTurn === this.player2) {
		      setTimeout(() => this.aiTurn(), 1000);
		    }
		  }

	  updatePlayerDisplay() {
        p1Name.textContent = this.player1.name;
        p1Type.textContent = this.player1.type;
        p1Strength.textContent = this.player1.strength;
        p1Speed.textContent = this.player1.speed;
        p1Tactics.textContent = this.player1.tactics;
        p1Size.textContent = this.player1.size;
        p1Powerup.textContent = this.player1.powerup;
        p1Image.src = this.player1.imageUrl;
        p1Image.onload = () => p1Image.style.display = "block"; // Show when loaded
      }

      updateOpponentDisplay() {
        p2Name.textContent = this.player2.name;
        p2Type.textContent = this.player2.type;
        p2Strength.textContent = this.player2.strength;
        p2Speed.textContent = this.player2.speed;
        p2Tactics.textContent = this.player2.tactics;
        p2Size.textContent = this.player2.size;
        p2Powerup.textContent = this.player2.powerup;
        p2Image.src = this.player2.imageUrl;
        p2Image.onload = () => p2Image.style.display = "block"; // Show when loaded
      }

      initBoard() {
        this.board = [];
        for (let y = 0; y < this.height; y++) {
          this.board[y] = [];
          for (let x = 0; x < this.width; x++) {
            let tile;
            do {
              tile = this.createRandomTile();
            } while (
              (x >= 2 && this.board[y][x-1]?.type === tile.type && this.board[y][x-2]?.type === tile.type) ||
              (y >= 2 && this.board[y-1]?.[x]?.type === tile.type && this.board[y-2]?.[x]?.type === tile.type)
            );
            this.board[y][x] = tile;
          }
        }
        this.renderBoard();
      }

      createRandomTile() {
        return {
          type: randomChoice(this.tileTypes),
          element: null
        };
      }

      renderBoard() {
        this.updateTileSizeWithGap();
        const boardElement = document.getElementById("game-board");
        boardElement.innerHTML = "";

        for (let y = 0; y < this.height; y++) {
          for (let x = 0; x < this.width; x++) {
            const tile = this.board[y][x];
            if (tile.type === null) continue;
            const tileElement = document.createElement("div");
            tileElement.className = `tile ${tile.type}`;
            if (this.gameOver) tileElement.classList.add("game-over");
            const img = document.createElement('img');
            img.src = `https://www.skulliance.io/staking/icons/${tile.type}.png`;
            img.alt = tile.type;
            tileElement.appendChild(img);
            tileElement.dataset.x = x;
            tileElement.dataset.y = y;
            boardElement.appendChild(tileElement);
            tile.element = tileElement;

            if (!this.isDragging || (this.selectedTile && (this.selectedTile.x !== x || this.selectedTile.y !== y))) {
              tileElement.style.transform = "translate(0, 0)";
            }
          }
        }

        document.getElementById("game-over-container").style.display = this.gameOver ? "block" : "none";
      }

	  addEventListeners() {
	      const board = document.getElementById("game-board");
	      if (this.isTouchDevice) {
	        board.addEventListener("touchstart", (e) => this.handleTouchStart(e));
	        board.addEventListener("touchmove", (e) => this.handleTouchMove(e));
	        board.addEventListener("touchend", (e) => this.handleTouchEnd(e));
	      } else {
	        board.addEventListener("mousedown", (e) => this.handleMouseDown(e));
	        board.addEventListener("mousemove", (e) => this.handleMouseMove(e));
	        board.addEventListener("mouseup", (e) => this.handleMouseUp(e));
	      }

	      document.getElementById("try-again").addEventListener("click", () => this.handleGameOverButton());
	      document.getElementById("restart").addEventListener("click", () => {
	        this.initGame();
	      });
	      const changeCharacterButton = document.getElementById("change-character");
	      const p1Image = document.getElementById("p1-image");
    
	      changeCharacterButton.addEventListener("click", () => {
	        console.log("addEventListeners: Switch Monster button clicked");
	        this.showCharacterSelect(false);
	      });
	      p1Image.addEventListener("click", () => {
	        console.log("addEventListeners: Player 1 image clicked");
	        this.showCharacterSelect(false);
	      });
	    }

		handleGameOverButton() {
		  console.log(`handleGameOverButton started: currentLevel=${this.currentLevel}, player2.health=${this.player2.health}`);
		  if (this.player2.health <= 0 && this.currentLevel > opponentsConfig.length) {
		    this.currentLevel = 1; // Reset to Level 1 only after final level win
		    console.log(`Reset to Level 1: currentLevel=${this.currentLevel}`);
		  }
		  // No level reset on loss; keep currentLevel intact
		  this.initGame();
		  console.log(`handleGameOverButton completed: currentLevel=${this.currentLevel}`);
		}

      handleMouseDown(e) {
        if (this.gameOver || this.gameState !== "playerTurn" || this.currentTurn !== this.player1) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add("selected");

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        this.offsetX = e.clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
      }

      handleMouseMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver || this.gameState !== "playerTurn") return;
        e.preventDefault();

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        const mouseX = e.clientX - boardRect.left - this.offsetX;
        const mouseY = e.clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;
        selectedTileElement.style.transition = "";

        if (!this.dragDirection) {
          const dx = Math.abs(mouseX - (this.selectedTile.x * this.tileSizeWithGap));
          const dy = Math.abs(mouseY - (this.selectedTile.y * this.tileSizeWithGap));
          if (dx > dy && dx > 5) this.dragDirection = "row";
          else if (dy > dx && dy > 5) this.dragDirection = "column";
        }

        if (!this.dragDirection) return;

        if (this.dragDirection === "row") {
          const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, mouseX));
          selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
          this.targetTile = {
            x: Math.round(constrainedX / this.tileSizeWithGap),
            y: this.selectedTile.y
          };
        } else if (this.dragDirection === "column") {
          const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, mouseY));
          selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
          this.targetTile = {
            x: this.selectedTile.x,
            y: Math.round(constrainedY / this.tileSizeWithGap)
          };
        }
      }

      handleMouseUp(e) {
        if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver || this.gameState !== "playerTurn") {
          if (this.selectedTile) {
            const tile = this.board[this.selectedTile.y][this.selectedTile.x];
            if (tile.element) tile.element.classList.remove("selected");
          }
          this.isDragging = false;
          this.selectedTile = null;
          this.targetTile = null;
          this.dragDirection = null;
          this.renderBoard();
          return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        if (tile.element) tile.element.classList.remove("selected");

        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
      }

      handleTouchStart(e) {
        if (this.gameOver || this.gameState !== "playerTurn" || this.currentTurn !== this.player1) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e.touches[0]);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add("selected");

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        this.offsetX = e.touches[0].clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.touches[0].clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
      }

      handleTouchMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver || this.gameState !== "playerTurn") return;
        e.preventDefault();

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        const touchX = e.touches[0].clientX - boardRect.left - this.offsetX;
        const touchY = e.touches[0].clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;

        requestAnimationFrame(() => {
          if (!this.dragDirection) {
            const dx = Math.abs(touchX - (this.selectedTile.x * this.tileSizeWithGap));
            const dy = Math.abs(touchY - (this.selectedTile.y * this.tileSizeWithGap));
            if (dx > dy && dx > 7) this.dragDirection = "row";
            else if (dy > dx && dy > 7) this.dragDirection = "column";
          }

          selectedTileElement.style.transition = "";

          if (this.dragDirection === "row") {
            const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, touchX));
            selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
            this.targetTile = {
              x: Math.round(constrainedX / this.tileSizeWithGap),
              y: this.selectedTile.y
            };
          } else if (this.dragDirection === "column") {
            const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, touchY));
            selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
            this.targetTile = {
              x: this.selectedTile.x,
              y: Math.round(constrainedY / this.tileSizeWithGap)
            };
          }
        });
      }

      handleTouchEnd(e) {
        if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver || this.gameState !== "playerTurn") {
          if (this.selectedTile) {
            const tile = this.board[this.selectedTile.y][this.selectedTile.x];
            if (tile.element) tile.element.classList.remove("selected");
          }
          this.isDragging = false;
          this.selectedTile = null;
          this.targetTile = null;
          this.dragDirection = null;
          this.renderBoard();
          return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        if (tile.element) tile.element.classList.remove("selected");

        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
      }

      getTileFromEvent(e) {
        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        const x = Math.floor((e.clientX - boardRect.left) / this.tileSizeWithGap);
        const y = Math.floor((e.clientY - boardRect.top) / this.tileSizeWithGap);
        if (x >= 0 && x < this.width && y >= 0 && y < this.height) {
          return { x, y, element: this.board[y][x].element };
        }
        return null;
      }

      slideTiles(startX, startY, endX, endY) {
        const tileSizeWithGap = this.tileSizeWithGap;
        let direction;

        const originalTiles = [];
        const tileElements = [];
        if (startY === endY) {
          direction = startX < endX ? 1 : -1;
          const minX = Math.min(startX, endX);
          const maxX = Math.max(startX, endX);
          for (let x = minX; x <= maxX; x++) {
            originalTiles.push({ ...this.board[startY][x] });
            tileElements.push(this.board[startY][x].element);
          }
        } else if (startX === endX) {
          direction = startY < endY ? 1 : -1;
          const minY = Math.min(startY, endY);
          const maxY = Math.max(startY, endY);
          for (let y = minY; y <= maxY; y++) {
            originalTiles.push({ ...this.board[y][startX] });
            tileElements.push(this.board[y][startX].element);
          }
        }

        const selectedElement = this.board[startY][startX].element;
        const dx = (endX - startX) * tileSizeWithGap;
        const dy = (endY - startY) * tileSizeWithGap;

        selectedElement.style.transition = "transform 0.2s ease";
        selectedElement.style.transform = `translate(${dx}px, ${dy}px)`;

        let i = 0;
        if (startY === endY) {
          for (let x = Math.min(startX, endX); x <= Math.max(startX, endX); x++) {
            if (x === startX) continue;
            const offsetX = direction * -tileSizeWithGap * (x - startX) / Math.abs(endX - startX);
            tileElements[i].style.transition = "transform 0.2s ease";
            tileElements[i].style.transform = `translate(${offsetX}px, 0)`;
            i++;
          }
        } else {
          for (let y = Math.min(startY, endY); y <= Math.max(startY, endY); y++) {
            if (y === startY) continue;
            const offsetY = direction * -tileSizeWithGap * (y - startY) / Math.abs(endY - startY);
            tileElements[i].style.transition = "transform 0.2s ease";
            tileElements[i].style.transform = `translate(0, ${offsetY}px)`;
            i++;
          }
        }

        setTimeout(() => {
          if (startY === endY) {
            const row = this.board[startY];
            const tempRow = [...row];
            if (startX < endX) {
              for (let x = startX; x < endX; x++) row[x] = tempRow[x + 1];
            } else {
              for (let x = startX; x > endX; x--) row[x] = tempRow[x - 1];
            }
            row[endX] = tempRow[startX];
          } else {
            const tempCol = [];
            for (let y = 0; y < this.height; y++) tempCol[y] = { ...this.board[y][startX] };
            if (startY < endY) {
              for (let y = startY; y < endY; y++) this.board[y][startX] = tempCol[y + 1];
            } else {
              for (let y = startY; y > endY; y--) this.board[y][startX] = tempCol[y - 1];
            }
            this.board[endY][endX] = tempCol[startY];
          }

          this.renderBoard();
          const hasMatches = this.resolveMatches(endX, endY);

          if (hasMatches) {
            this.gameState = "animating";
          } else {
            log("No match, reverting tiles...");
            this.sounds.badMove.play();
            selectedElement.style.transition = "transform 0.2s ease";
            selectedElement.style.transform = "translate(0, 0)";
            tileElements.forEach(element => {
              element.style.transition = "transform 0.2s ease";
              element.style.transform = "translate(0, 0)";
            });

            setTimeout(() => {
              if (startY === endY) {
                const minX = Math.min(startX, endX);
                for (let i = 0; i < originalTiles.length; i++) {
                  this.board[startY][minX + i] = { ...originalTiles[i], element: tileElements[i] };
                }
              } else {
                const minY = Math.min(startY, endY);
                for (let i = 0; i < originalTiles.length; i++) {
                  this.board[minY + i][startX] = { ...originalTiles[i], element: tileElements[i] };
                }
              }
              this.renderBoard();
              this.gameState = "playerTurn";
            }, 200);
          }
        }, 200);
      }

	  resolveMatches(selectedX = null, selectedY = null) {
	    console.log("resolveMatches started, gameOver:", this.gameOver);
	    if (this.gameOver) {
	      console.log("Game over, exiting resolveMatches");
	      return false;
	    }

	    const matches = this.checkMatches();
	    console.log(`Found ${matches.length} matches:`, matches);

	    // Calculate total tiles matched, but only play multiMatch sound for player's initial move
	    const isPlayerMove = selectedX !== null && selectedY !== null;
	    if (isPlayerMove && matches.length > 1) { // Only check if multiple matches and from player's move
	      const totalTilesMatched = matches.reduce((sum, match) => sum + match.totalTiles, 0);
	      console.log(`Total tiles matched from player move: ${totalTilesMatched}`);
	      if (totalTilesMatched === 6 || totalTilesMatched === 9) {
	        console.log(`Playing multiMatch sound for ${totalTilesMatched} tiles matched`);
	        this.sounds.multiMatch.play();
	      }
	    }

	    if (matches.length > 0) {
	      const allMatchedTiles = new Set();
	      let totalDamage = 0;
	      const attacker = this.currentTurn;
	      const defender = this.currentTurn === this.player1 ? this.player2 : this.player1;

	      try {
	        matches.forEach(match => {
	          console.log("Processing match:", match);
	          match.coordinates.forEach(coord => allMatchedTiles.add(coord));
	          const damage = this.handleMatch(match);
	          console.log(`Damage from match: ${damage}`);
	          if (this.gameOver) {
	            console.log("Game over detected during match processing, stopping further processing");
	            return;
	          }
	          if (damage > 0) totalDamage += damage;
	        });

	        if (this.gameOver) {
	          console.log("Game over after processing matches, exiting resolveMatches");
	          return true;
	        }

	        console.log(`Total damage dealt: ${totalDamage}, tiles to clear:`, [...allMatchedTiles]);
	        if (totalDamage > 0 && !this.gameOver) {
	          setTimeout(() => {
	            if (this.gameOver) {
	              console.log("Game over, skipping recoil animation");
	              return;
	            }
	            console.log("Animating recoil for defender:", defender.name);
	            this.animateRecoil(defender, totalDamage);
	          }, 100);
	        }

	        setTimeout(() => {
	          if (this.gameOver) {
	            console.log("Game over, skipping match animation and cascading");
	            return;
	          }
	          console.log("Animating matched tiles, allMatchedTiles:", [...allMatchedTiles]);
	          allMatchedTiles.forEach(tile => {
	            const [x, y] = tile.split(",").map(Number);
	            if (this.board[y][x]?.element) {
	              this.board[y][x].element.classList.add("matched");
	            } else {
	              console.warn(`Tile at (${x},${y}) has no element to animate`);
	            }
	          });

	          setTimeout(() => {
	            if (this.gameOver) {
	              console.log("Game over, skipping tile clearing and cascading");
	              return;
	            }
	            console.log("Clearing matched tiles:", [...allMatchedTiles]);
	            allMatchedTiles.forEach(tile => {
	              const [x, y] = tile.split(",").map(Number);
	              if (this.board[y][x]) {
	                this.board[y][x].type = null;
	                this.board[y][x].element = null;
	              }
	            });
	            this.sounds.match.play();
	            console.log("Cascading tiles");
	            this.cascadeTiles(() => {
	              if (this.gameOver) {
	                console.log("Game over, skipping endTurn");
	                return;
	              }
	              console.log("Cascade complete, ending turn");
	              this.endTurn();
	            });
	          }, 300);
	        }, 200);

	        return true;
	      } catch (error) {
	        console.error("Error in resolveMatches:", error);
	        this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
	        return false;
	      }
	    }
	    console.log("No matches found, returning false");
	    return false;
	  }

	  checkMatches() {
	    console.log("checkMatches started");
	    const matches = [];

	    try {
	      // Step 1: Detect straight-line matches
	      const straightMatches = [];

	      // Horizontal matches
	      for (let y = 0; y < this.height; y++) {
	        let startX = 0;
	        for (let x = 0; x <= this.width; x++) {
	          const currentType = x < this.width ? this.board[y][x]?.type : null;
	          if (currentType !== this.board[y][startX]?.type || x === this.width) {
	            const matchLength = x - startX;
	            if (matchLength >= 3) {
	              const matchCoordinates = new Set();
	              for (let i = startX; i < x; i++) {
	                matchCoordinates.add(`${i},${y}`);
	              }
	              straightMatches.push({ type: this.board[y][startX].type, coordinates: matchCoordinates });
	              console.log(`Horizontal match found at row ${y}, cols ${startX}-${x-1}:`, [...matchCoordinates]);
	            }
	            startX = x;
	          }
	        }
	      }

	      // Vertical matches
	      for (let x = 0; x < this.width; x++) {
	        let startY = 0;
	        for (let y = 0; y <= this.height; y++) {
	          const currentType = y < this.height ? this.board[y][x]?.type : null;
	          if (currentType !== this.board[startY][x]?.type || y === this.height) {
	            const matchLength = y - startY;
	            if (matchLength >= 3) {
	              const matchCoordinates = new Set();
	              for (let i = startY; i < y; i++) {
	                matchCoordinates.add(`${x},${i}`);
	              }
	              straightMatches.push({ type: this.board[startY][x].type, coordinates: matchCoordinates });
	              console.log(`Vertical match found at col ${x}, rows ${startY}-${y-1}:`, [...matchCoordinates]);
	            }
	            startY = y;
	          }
	        }
	      }

	      // Step 2: Group matches by tile type and calculate total unique tiles
	      const matchesByType = {};
	      straightMatches.forEach(match => {
	        if (!matchesByType[match.type]) {
	          matchesByType[match.type] = [];
	        }
	        matchesByType[match.type].push(match.coordinates);
	      });

	      // Step 3: For each tile type, calculate the total unique tiles matched
	      for (const tileType in matchesByType) {
	        const matchSets = matchesByType[tileType];
	        const allTiles = new Set();
	        matchSets.forEach(matchCoordinates => {
	          matchCoordinates.forEach(coord => allTiles.add(coord));
	        });
	        console.log(`Total unique tiles matched for type ${tileType}:`, [...allTiles], `count: ${allTiles.size}`);
	        matches.push({ type: tileType, coordinates: allTiles, totalTiles: allTiles.size });
	      }

	      console.log("checkMatches completed, returning matches:", matches);
	      return matches;
	    } catch (error) {
	      console.error("Error in checkMatches:", error);
	      return [];
	    }
	  }
	  
	  handleMatch(match) {
	    console.log("handleMatch started, match:", match);
	    const attacker = this.currentTurn;
	    const defender = this.currentTurn === this.player1 ? this.player2 : this.player1;
	    const type = match.type;
	    const size = match.totalTiles; // Use totalTiles instead of coordinates.size
	    let damage = 0;

	    console.log(`${defender.name} health before match: ${defender.health}`);

	    if (size == 4) {
	      this.sounds.powerGem.play();
	      log(`${attacker.name} created a match of ${size} tiles!`);
	    }

	    if (size >= 5) {
	      this.sounds.hyperCube.play();
	      log(`${attacker.name} created a match of ${size} tiles!`);
	    }

	    if (type === "first-attack" || type === "second-attack" || type === "special-attack" || type === "last-stand") {
	      damage = Math.round(attacker.strength * (size === 3 ? 2 : size === 4 ? 3 : 4));
	      console.log(`Base damage calculated: ${damage}`);
	      if (type === "special-attack") {
	        damage = Math.round(damage * 1.2);
	        console.log(`Special attack multiplier applied, damage: ${damage}`);
	      }
	      if (attacker.boostActive) {
	        damage += attacker.boostValue || 10;
	        attacker.boostActive = false;
	        log(`${attacker.name}'s Boost fades.`);
	        console.log(`Boost applied, damage: ${damage}`);
	      }

	      const tacticsChance = defender.tactics * 10;
	      if (Math.random() * 100 < tacticsChance) {
	        damage = Math.floor(damage / 2);
	        log(`${defender.name}'s tactics halve the blow, taking only ${damage} damage!`);
	        console.log(`Tactics applied, damage reduced to: ${damage}`);
	      }

	      if (defender.lastStandActive) {
	        damage = Math.max(0, damage - 5);
	        defender.lastStandActive = false;
	        log(`${defender.name}'s Last Stand mitigates 5 damage!`);
	        console.log(`Last Stand applied, damage: ${damage}`);
	      }

	      if (type === "last-stand") {
	        attacker.lastStandActive = true;
	        log(`${attacker.name} uses Last Stand, dealing ${damage} damage to ${defender.name} and preparing to mitigate 5 damage on the next attack!`);
	      } else {
	        log(`${attacker.name} uses ${type === "first-attack" ? "Slash" : type === "second-attack" ? "Bite" : "Shadow Strike"} on ${defender.name} for ${damage} damage!`);
	      }

	      defender.health = Math.max(0, defender.health - damage);
	      console.log(`${defender.name} health after damage: ${defender.health}`);
	      this.updateHealth(defender);
	      console.log("Calling checkGameOver from handleMatch");
	      this.checkGameOver();
	      if (!this.gameOver) {
	        console.log("Game not over, animating attack");
	        this.animateAttack(attacker, damage, type);
	      }
	    } else if (type === "power-up") {
	      this.usePowerup(attacker, defender);
	      if (!this.gameOver) {
	        console.log("Animating powerup");
	        this.animatePowerup(attacker);
	      }
	    }

	    if (!this.roundStats[this.roundStats.length - 1] || this.roundStats[this.roundStats.length - 1].completed) {
	      this.roundStats.push({
	        points: 0,
	        matches: 0,
	        healthPercentage: 0,
	        completed: false
	      });
	    }
	    const currentRound = this.roundStats[this.roundStats.length - 1];
	    currentRound.points += damage;
	    currentRound.matches += 1;

	    console.log(`handleMatch completed, damage dealt: ${damage}`);
	    return damage;
	  }

	  cascadeTiles(callback) {
	    if (this.gameOver) {
	      console.log("Game over, skipping cascadeTiles");
	      return;
	    }

	    const moved = this.cascadeTilesWithoutRender();
	    const fallClass = "falling";

	    for (let x = 0; x < this.width; x++) {
	      for (let y = 0; y < this.height; y++) {
	        const tile = this.board[y][x];
	        if (tile.element && tile.element.style.transform === "translate(0px, 0px)") {
	          const emptyBelow = this.countEmptyBelow(x, y);
	          if (emptyBelow > 0) {
	            tile.element.classList.add(fallClass);
	            tile.element.style.transform = `translate(0, ${emptyBelow * this.tileSizeWithGap}px)`;
	          }
	        }
	      }
	    }

	    this.renderBoard();

	    if (moved) {
	      setTimeout(() => {
	        if (this.gameOver) {
	          console.log("Game over, skipping cascade resolution");
	          return;
	        }
	        this.sounds.cascade.play();
	        const hasMatches = this.resolveMatches();
	        const tiles = document.querySelectorAll(`.${fallClass}`);
	        tiles.forEach(tile => {
	          tile.classList.remove(fallClass);
	          tile.style.transform = "translate(0, 0)";
	        });
	        if (!hasMatches) {
	          callback();
	        }
	      }, 300);
	    } else {
	      callback();
	    }
	  }

      cascadeTilesWithoutRender() {
        let moved = false;
        for (let x = 0; x < this.width; x++) {
          let emptySpaces = 0;
          for (let y = this.height - 1; y >= 0; y--) {
            if (!this.board[y][x].type) {
              emptySpaces++;
            } else if (emptySpaces > 0) {
              this.board[y + emptySpaces][x] = this.board[y][x];
              this.board[y][x] = { type: null, element: null };
              moved = true;
            }
          }
          for (let i = 0; i < emptySpaces; i++) {
            this.board[i][x] = this.createRandomTile();
            moved = true;
          }
        }
        return moved;
      }

      countEmptyBelow(x, y) {
        let count = 0;
        for (let i = y + 1; i < this.height; i++) {
          if (!this.board[i][x].type) {
            count++;
          } else {
            break;
          }
        }
        return count;
      }

      usePowerup(player, defender) {
        const reductionFactor = 1 - (defender.tactics * 0.05);
        let effectValue;

        if (player.powerup === "Heal") {
          effectValue = Math.floor(10 * reductionFactor);
          player.health = Math.min(player.maxHealth, player.health + effectValue);
          log(`${player.name} uses Heal, restoring ${effectValue} HP${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        } else if (player.powerup === "Boost Attack") {
          effectValue = Math.floor(10 * reductionFactor);
          player.boostActive = true;
          player.boostValue = effectValue;
          log(`${player.name} uses Power Surge, next attack +${effectValue} damage${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        } else if (player.powerup === "Regenerate") {
          effectValue = Math.floor(7 * reductionFactor);
          player.health = Math.min(player.maxHealth, player.health + effectValue);
          log(`${player.name} uses Regen, restoring ${effectValue} HP${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        } else if (player.powerup === "Minor Regen") {
          effectValue = Math.floor(5 * reductionFactor);
          player.health = Math.min(player.maxHealth, player.health + effectValue);
          log(`${player.name} uses Minor Regen, restoring ${effectValue} HP${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        }
        this.updateHealth(player);
      }

      updateHealth(player) {
        const healthBar = player === this.player1 ? p1Health : p2Health;
        const hpText = player === this.player1 ? p1Hp : p2Hp;

        const percentage = (player.health / player.maxHealth) * 100;
        healthBar.style.width = `${percentage}%`;

        let color;
        if (percentage > 75) {
          color = '#4CAF50'; // Green for >75%
        } else if (percentage > 50) {
          color = '#FFC105'; // Yellow for >50%
        } else if (percentage > 25) {
          color = '#FFA500'; // Orange for >25%
        } else {
          color = '#F44336'; // Red for ≤25%
        }

        healthBar.style.backgroundColor = color;
        hpText.textContent = `${player.health}/${player.maxHealth}`;
      }

	  endTurn() {
	    if (this.gameState === "gameOver" || this.gameOver) {
	      console.log("Game over, skipping endTurn");
	      return;
	    }
	    this.currentTurn = this.currentTurn === this.player1 ? this.player2 : this.player1;
	    this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
	    turnIndicator.textContent = `Level ${this.currentLevel} - ${this.currentTurn === this.player1 ? "Player" : "Opponent"}'s Turn`;
	    log(`Turn switched to ${this.currentTurn === this.player1 ? "Player" : "Opponent"}`);

	    if (this.currentTurn === this.player2) {
	      setTimeout(() => this.aiTurn(), 1000);
	    }
	  }

      aiTurn() {
        if (this.gameState !== "aiTurn" || this.currentTurn !== this.player2) return;
        this.gameState = "animating";
        const move = this.findAIMove();
        if (move) {
          log(`${this.player2.name} swaps tiles at (${move.x1}, ${move.y1}) to (${move.x2}, ${move.y2})`);
          this.slideTiles(move.x1, move.y1, move.x2, move.y2);
        } else {
          log(`${this.player2.name} passes...`);
          this.endTurn();
        }
      }

      findAIMove() {
        for (let y = 0; y < this.height; y++) {
          for (let x = 0; x < this.width; x++) {
            if (x < this.width - 1 && this.canMakeMatch(x, y, x + 1, y)) return { x1: x, y1: y, x2: x + 1, y2: y };
            if (y < this.height - 1 && this.canMakeMatch(x, y, x, y + 1)) return { x1: x, y1: y, x2: x, y2: y + 1 };
          }
        }
        return null;
      }

      canMakeMatch(x1, y1, x2, y2) {
        const temp1 = { ...this.board[y1][x1] };
        const temp2 = { ...this.board[y2][x2] };
        this.board[y1][x1] = temp2;
        this.board[y2][x2] = temp1;
        const matches = this.checkMatches().length > 0;
        this.board[y1][x1] = temp1;
        this.board[y2][x2] = temp2;
        return matches;
      }

	  async checkGameOver() {
	    if (this.gameOver || this.isCheckingGameOver) {
	      console.log(`checkGameOver skipped: gameOver=${this.gameOver}, isCheckingGameOver=${this.isCheckingGameOver}, currentLevel=${this.currentLevel}`);
	      return;
	    }

	    this.isCheckingGameOver = true;
	    console.log(`checkGameOver started: currentLevel=${this.currentLevel}, player1.health=${this.player1.health}, player2.health=${this.player2.health}`);

	    const tryAgainButton = document.getElementById("try-again");
	    if (this.player1.health <= 0) {
	      console.log("Player 1 health <= 0, triggering game over (loss)");
	      this.gameOver = true;
	      this.gameState = "gameOver";
	      gameOver.textContent = "You Lose!";
	      turnIndicator.textContent = "Game Over";
	      log(`${this.player2.name} defeats ${this.player1.name}!`);
	      tryAgainButton.textContent = "TRY AGAIN"; // Keep "Try Again" to restart same level
	      document.getElementById("game-over-container").style.display = "block";
	      try {
	        this.sounds.loss.play();
	      } catch (err) {
	        console.error("Error playing lose sound:", err);
	      }
	      // Removed await this.clearProgress(); - No progress reset on loss
	    } else if (this.player2.health <= 0) {
	      console.log("Player 2 health <= 0, triggering game over (win)");
	      this.gameOver = true;
	      this.gameState = "gameOver";
	      gameOver.textContent = "You Win!";
	      turnIndicator.textContent = "Game Over";
	      tryAgainButton.textContent = this.currentLevel === opponentsConfig.length ? "START OVER" : "NEXT LEVEL";
	      document.getElementById("game-over-container").style.display = "block";

	      if (this.currentTurn === this.player1) {
	        const currentRound = this.roundStats[this.roundStats.length - 1];
	        if (currentRound && !currentRound.completed) {
	          currentRound.healthPercentage = (this.player1.health / this.player1.maxHealth) * 100;
	          currentRound.completed = true;

	          const roundScore = currentRound.matches > 0 
	            ? (((currentRound.points / currentRound.matches) / 100) * (currentRound.healthPercentage + 20)) * (1 + this.currentLevel / 56)
	            : 0;

	          log(`Calculating round score: points=${currentRound.points}, matches=${currentRound.matches}, healthPercentage=${currentRound.healthPercentage.toFixed(2)}, level=${this.currentLevel}`);
	          log(`Round Score Formula: (((${currentRound.points} / ${currentRound.matches}) / 100) * (${currentRound.healthPercentage} + 20)) * (1 + ${this.currentLevel} / 56) = ${roundScore}`);

	          this.grandTotalScore += roundScore; // Only update grand total on win

	          log(`Round Won! Points: ${currentRound.points}, Matches: ${currentRound.matches}, Health Left: ${currentRound.healthPercentage.toFixed(2)}%`);
	          log(`Round Score: ${roundScore}, Grand Total Score: ${this.grandTotalScore}`);
	        }
	      }

	      await this.saveScoreToDatabase(this.currentLevel);

	      if (this.currentLevel === opponentsConfig.length) {
	        this.sounds.finalWin.play();
	        log(`Final level completed! Final score: ${this.grandTotalScore}`);
	        this.grandTotalScore = 0; // Reset only after final level win
	        await this.clearProgress();
	        log("Game completed! Grand total score reset.");
	      } else {
	        this.currentLevel += 1; // Advance level on win
	        await this.saveProgress();
	        console.log(`Progress saved: currentLevel=${this.currentLevel}`);
	        this.sounds.win.play();
	      }

	      const damagedUrl = `https://skulliance.io/staking/images/monstrocity/battle-damaged/${this.player2.name.toLowerCase().replace(/ /g, '-')}.png`;
	      p2Image.src = damagedUrl;
	      p2Image.classList.add('loser');
	      p1Image.classList.add('winner');
	      this.renderBoard();
	    }

	    this.isCheckingGameOver = false;
	    console.log(`checkGameOver completed: currentLevel=${this.currentLevel}, gameOver=${this.gameOver}`);
	  }
	  
	  async saveScoreToDatabase(completedLevel) {
	    const data = {
	      level: completedLevel,
	      score: this.grandTotalScore
	    };

	    console.log(`Saving grand total score to database: level=${data.level}, score=${data.score}`);

	    try {
	      const response = await fetch('ajax/save-monstrocity-score.php', {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' },
	        body: JSON.stringify(data)
	      });

	      if (!response.ok) {
	        throw new Error(`HTTP error! Status: ${response.status}`);
	      }

	      const result = await response.json();
	      console.log('Save response:', result);
	      if (result.status === 'success') {
	        log(`Saved: Level ${data.level}, Score ${data.score}`); // Remove toFixed(2)
	      } else {
	        log(`Score not saved: ${result.message}`);
	      }
	    } catch (error) {
	      console.error('Error saving to database:', error);
	    }
	  }

      applyAnimation(imageElement, shiftX, glowClass, duration) {
        const originalTransform = imageElement.style.transform || '';
        const scalePart = originalTransform.includes('scaleX') ? originalTransform.match(/scaleX\([^)]+\)/)[0] : '';
        imageElement.style.transition = `transform ${duration / 2 / 1000}s linear`;
        imageElement.style.transform = `translateX(${shiftX}px) ${scalePart}`;
        imageElement.classList.add(glowClass);
        setTimeout(() => {
          imageElement.style.transform = scalePart;
          setTimeout(() => {
            imageElement.classList.remove(glowClass);
          }, duration / 2);
        }, duration / 2);
      }

      animateAttack(attacker, damage, type) {
        const imageElement = attacker === this.player1 ? p1Image : p2Image;
        const shiftDirection = attacker === this.player1 ? 1 : -1;
        const shiftDistance = Math.min(10, 2 + damage * 0.4);
        const shiftX = shiftDirection * shiftDistance;
        const glowClass = `glow-${type}`;
        this.applyAnimation(imageElement, shiftX, glowClass, 200);
      }

      animatePowerup(attacker) {
        const imageElement = attacker === this.player1 ? p1Image : p2Image;
        this.applyAnimation(imageElement, 0, 'glow-power-up', 200);
      }

      animateRecoil(defender, totalDamage) {
        const imageElement = defender === this.player1 ? p1Image : p2Image;
        const shiftDirection = defender === this.player1 ? -1 : 1;
        const shiftDistance = Math.min(10, 2 + totalDamage * 0.4);
        const shiftX = shiftDirection * shiftDistance;
        this.applyAnimation(imageElement, shiftX, 'glow-recoil', 200);
      }
    }

    function randomChoice(arr) {
      return arr[Math.floor(Math.random() * arr.length)];
    }

    function log(message) {
      const li = document.createElement("li");
      li.textContent = message;
      battleLog.insertBefore(li, battleLog.firstChild);
      if (battleLog.children.length > 10) battleLog.removeChild(battleLog.lastChild);
    }

    const turnIndicator = document.getElementById("turn-indicator");
    const p1Name = document.getElementById("p1-name");
    const p1Image = document.getElementById("p1-image");
    const p1Health = document.getElementById("p1-health");
    const p1Hp = document.getElementById("p1-hp");
    const p1Strength = document.getElementById("p1-strength");
    const p1Speed = document.getElementById("p1-speed");
    const p1Tactics = document.getElementById("p1-tactics");
    const p1Size = document.getElementById("p1-size");
    const p1Powerup = document.getElementById("p1-powerup");
    const p1Type = document.getElementById("p1-type");
    const p2Name = document.getElementById("p2-name");
    const p2Image = document.getElementById("p2-image");
    const p2Health = document.getElementById("p2-health");
    const p2Hp = document.getElementById("p2-hp");
    const p2Strength = document.getElementById("p2-strength");
    const p2Speed = document.getElementById("p2-speed");
    const p2Tactics = document.getElementById("p2-tactics");
    const p2Size = document.getElementById("p2-size");
    const p2Powerup = document.getElementById("p2-powerup");
    const p2Type = document.getElementById("p2-type");
    const battleLog = document.getElementById("battle-log");
    const gameOver = document.getElementById("game-over");
	
	function getAssets() {
	  return new Promise((resolve, reject) => {
	    let result = [{
	      name: "Craig",
	      strength: 4,
	      speed: 4,
	      tactics: 4,
	      size: "Medium",
	      type: "Base",
	      powerup: "Regenerate"
	    }];

	    var xhttp = new XMLHttpRequest();
	    xhttp.open('GET', 'ajax/get-monstrocity-assets.php', true);
	    xhttp.send();
	    xhttp.onreadystatechange = function() {
	      if (xhttp.readyState == XMLHttpRequest.DONE) {
	        if (xhttp.status == 200) {
	          var data = xhttp.responseText;
	          if (data !== 'false') {
	            try {
	              result = JSON.parse(data);
	              if (!Array.isArray(result)) {
	                result = [result];
	              }
	            } catch (e) {
	              console.error('Failed to parse JSON:', e);
	            }
	          }
	          resolve(result);
	        } else {
	          console.error('Request failed with status:', xhttp.status);
	          resolve(result);
	        }
	      }
	    };
	  });
	}

	// Instantiation
	(async () => {
	  try {
	    const playerCharactersConfig = await getAssets();
	    console.log("Main: Player characters loaded:", playerCharactersConfig);
	    const game = new MonstrocityMatch3(playerCharactersConfig);
	    console.log("Main: Game instance created");
	    await game.init();
	    console.log("Main: Game initialized successfully");
	  } catch (error) {
	    console.error("Main: Error initializing game:", error);
	  }
	})();
  </script>
</body>
</html>
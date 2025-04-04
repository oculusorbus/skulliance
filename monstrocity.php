<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monstrocity Match-3</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #121212; /* Darker gray */
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      background-image: url(https://www.skulliance.io/staking/images/monstrocity/biolab.png);
      background-size: cover;
	  background-position: center;
    }
    
    h2 {
      margin-top: 0px;
    }

    .game-container {
      margin-top: 20px;
	  margin-bottom: 20px;
      text-align: center;
      padding: 20px;
      background-color: #1e1e1e; /* Slightly lighter gray */
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
      width: 900px;
      min-width: 900px;
      max-width: 900px;
      box-sizing: border-box;
	  max-height: 1070px;
    }

    .game-logo {
      max-width: 300px;
      height: auto;
      margin: 0 auto 10px; /* Reduced margin to tighten design */
      display: block;
    }

    .turn-indicator {
      font-size: 1.2em;
      margin-bottom: 20px;
      color: #ffcc00;
      position: relative;
      top: 10px;
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
      background-color: #2a2a2a; /* Lighter gray for characters */
      border-radius: 5px;
      text-align: center;
      flex-shrink: 0;
      position: relative;
      top: -225px;
      min-height: 510px;
    }

    .character img {
      width: 100%;
      height: auto;
      margin-bottom: 10px;
      border-radius: 5px;
      transition: filter 0.5s ease; /* Smooth transition for filter changes */
    }
    
    .grayscale {
      filter: grayscale(100%); /* Fully grays out the image */
    }

    .health-bar {
      width: 100%;
      height: 20px;
      background-color: #555;
      border-radius: 5px;
      overflow: hidden;
      margin: 5px 0;
    }

    .health {
      height: 100%;
      background-color: #4CAF50;
      transition: width 0.3s ease;
    }

    #game-board {
      display: grid;
      gap: 0.5vh;
      background: #333;
      padding: 1vh;
      box-sizing: border-box;
      user-select: none;
      position: relative;
      touch-action: none;
      width: 300px;
      height: 300px;
      grid-template-columns: repeat(5, 1fr);
      flex-shrink: 0;
      margin-top: 15px;
    }

    .tile {
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

    .tile.first-attack { background-color: #4CAF50; } /* Green */
    .tile.second-attack { background-color: #2196F3; } /* Blue */
    .tile.special-attack { background-color: #FFC107; } /* Yellow */
    .tile.power-up { background-color: #9C27B0; } /* Purple */
    .tile.last-stand { background-color: #F44336; } /* Red */

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
      top: 50%;
      left: 50%;
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
      background-color: #2a2a2a; /* Match character background */
      padding: 10px;
      border-radius: 5px;
      max-height: 150px;
      min-height: 150px;
      overflow-y: auto;
      position: relative;
      top: -225px;
    }

    #battle-log { list-style: none; padding: 0; }
    #battle-log li { margin: 5px 0; opacity: 0; animation: fadeIn 0.5s forwards; }
    @keyframes fadeIn { to { opacity: 1; } }

    button {
      padding: 10px 20px;
      background-color: #ffcc00;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      margin-bottom: 20px;
    }

    button:hover { background-color: #e6b800; }

    /* Legend Styles */
    .legend {
      margin-top: 20px;
      text-align: left;
      background-color: #2a2a2a; /* Match character background */
      padding: 10px;
      border-radius: 5px;
      position: relative;
      top: -225px;
    }

    .legend h3 {
      margin: 0 0 10px;
      color: #ffcc00;
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

    /* Responsive adjustments for smaller screens */
    @media (max-width: 950px) {
      .game-container {
        width: 100%;
        min-width: 320px;
        max-width: 100%;
        padding: 10px;
		max-height: none;
      }
	  
	  .character {
   	    min-height: 100px;
		text-align: right;
	  }
	  
	  .character h2{
		  text-align: center;
	  }
	  
	  .character img {
		width: 80px;
		float: left;
		position: absolute;
		left: 10px;
		top: 100px;
	  }

      .battlefield {
        flex-direction: column;
        align-items: center;
      }

      .character {
        width: 100%;
        max-width: 300px;
      }

      #game-board {
        width: 250px;
        height: 250px;
		position: relative;
        top: -230px;
      }

      .legend {
        font-size: 0.8em;
      }

      .legend-tile {
        width: 15px;
        height: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="game-container">
    <!-- Logo Image -->
    <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Logo" class="game-logo">
            <button id="restart">Restart Game</button>
    <div class="turn-indicator" id="turn-indicator">Player 1's Turn</div>

    <div class="battlefield">
      <div class="character" id="player1">
                <h2><span id="p1-name"></span></h2>

        <img id="p1-image" src="" alt="Player 1 Image">

        <div class="health-bar"><div class="health" id="p1-health"></div></div>
        <p>Health: <span id="p1-hp"></span></p>
        <p>Power-Up: <span id="p1-powerup"></span></p>
                <p>Type: <span id="p1-type"></span></p>
      </div>
      <div id="game-board"></div>
      <div class="character" id="player2">
                <h2><span id="p2-name"></span></h2>
        
        <img id="p2-image" src="" alt="Player 2 Image">

        <div class="health-bar"><div class="health" id="p2-health"></div></div>
        <p>Health: <span id="p2-hp"></span></p>
        <p>Power-Up: <span id="p2-powerup"></span></p>
        <p>Type: <span id="p2-type"></span></p>
      </div>
    </div>
    <div class="log">
      <h3>Battle Log</h3>
      <ul id="battle-log"></ul>
    </div>
    <!-- Legend Section -->
    <div class="legend">
      <h3>Legend</h3>
      <ul>
        <li><span class="legend-tile first-attack"><img src="https://www.skulliance.io/staking/icons/first-attack.png" alt="First Attack"></span>First Attack (Slash): Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile second-attack"><img src="https://www.skulliance.io/staking/icons/second-attack.png" alt="Second Attack"></span>Second Attack (Bite): Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile special-attack"><img src="https://www.skulliance.io/staking/icons/special-attack.png" alt="Special Attack"></span>Special Attack (Shadow Strike): Deals 1.2× damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile power-up"><img src="https://www.skulliance.io/staking/icons/power-up.png" alt="Power Up"></span>Power-Up: Activates a random powerup (see below)</li>
        <li><span class="legend-tile last-stand"><img src="https://www.skulliance.io/staking/icons/last-stand.png" alt="Last Stand"></span>Last Stand: Deals damage and mitigates 5 damage on the next attack received</li>
      </ul>
      <h3>Powerup Effects</h3>
      <ul>
        <li>Heal: Restores 10 HP</li>
        <li>Boost: Adds +10 damage to the next attack</li>
        <li>Minor Regen: Restores 5 HP</li>
      </ul>
    </div>
    <div id="game-over-container">
      <div id="game-over"></div>
      <div id="game-over-buttons">
        <button id="try-again">TRY AGAIN</button>
      </div>
    </div>
  </div>


  <script>
    class MonstrocityMatch3 {
      constructor() {
        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
        this.width = 5;
        this.height = 5;
        this.board = [];
        this.selectedTile = null;
        this.gameOver = false;
        this.currentTurn = null;
        this.player1 = null;
        this.player2 = null;
        this.gameState = "initializing"; // "initializing", "playerTurn", "aiTurn", "animating", "gameOver"
        this.isDragging = false;
        this.targetTile = null;
        this.dragDirection = null;
        this.offsetX = 0;
        this.offsetY = 0;

        this.tileTypes = ["first-attack", "second-attack", "special-attack", "power-up", "last-stand"];
        this.updateTileSizeWithGap(); // Calculate initially

        // Sound effects
        this.sounds = {
          match: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
          cascade: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
          badMove: new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),
          gameOver: new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),
          reset: new Audio('https://www.skulliance.io/staking/sounds/voice_go.ogg'),
		  win: new Audio('https://www.skulliance.io/staking/sounds/skullcoinwin.ogg'),
		  loss: new Audio('https://www.skulliance.io/staking/sounds/skullcoinlose.ogg'),
        };

        this.initGame();
        this.addEventListeners();
      }

      updateTileSizeWithGap() {
        const boardElement = document.getElementById("game-board");
        const boardWidth = boardElement.offsetWidth || 300; // Fallback to 300px if not rendered yet
        this.tileSizeWithGap = (boardWidth - (0.5 * (this.width - 1))) / this.width;
      }

      initGame() {
        this.sounds.reset.play(); // Play reset sound
        log("Starting game initialization...");

        // Initialize players with new characters
        this.player1 = this.generateCharacter();
        this.player2 = this.generateCharacter();
        this.currentTurn = this.player1.strength >= this.player2.strength ? this.player1 : this.player2;
        this.gameState = "initializing";
        this.gameOver = false;

        // Remove grayscale from both images
        p1Image.classList.remove('grayscale');
        p2Image.classList.remove('grayscale');

        // Update UI with new character details
        p1Name.textContent = this.player1.name;
        p1Type.textContent = this.player1.type;
        p1Powerup.textContent = this.player1.powerup;
        p1Image.src = this.player1.imageUrl; // Set new Player 1 image

        p2Name.textContent = this.player2.name;
        p2Type.textContent = this.player2.type;
        p2Powerup.textContent = this.player2.powerup;
        p2Image.src = this.player2.imageUrl; // Set new Player 2 image

        // Update health bars
        this.updateHealth(this.player1);
        this.updateHealth(this.player2);

        battleLog.innerHTML = "";
        gameOver.textContent = "";
        log(`${this.currentTurn.name} goes first!`);

        this.initBoard();
        this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
        turnIndicator.textContent = `${this.currentTurn === this.player1 ? "Player 1" : "Player 2"}'s Turn`;

        if (this.currentTurn === this.player2) {
          setTimeout(() => this.aiTurn(), 1000);
        }
      }

      generateCharacter() {
        const type = randomChoice(["Fighter", "Trickster", "Brute"]);
        const strength = Math.floor(Math.random() * 3) + 3; // 3-5
        const name = randomChoice(["Koipon", "Jarhead", "Slime Mind", "Mandiblus", "Texby", "Spydrax", "Goblin Ganger", "Billander and Ted", "Craig", "Dankle", "Drake", "Katastrophy", "Ouchie", "Merdock"]);
        const imageUrl = `https://www.skulliance.io/staking/images/monstrocity/base/${name.toLowerCase().replace(/ /g, '-')}.png`;
        return {
          name,
          type,
          strength,
          powerup: randomChoice(["Heal", "Boost (Next Attack)", "Minor Regen"]),
          health: type === "Brute" ? 100 : 75,
          maxHealth: type === "Brute" ? 100 : 75,
          boostActive: false,
          lastStandActive: false,
          imageUrl
        };
      }

      initBoard() {
        console.log("Initializing board...");
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
        console.log("Board initialized:", this.board);
        this.renderBoard();
      }

      createRandomTile() {
        return {
          type: randomChoice(this.tileTypes),
          element: null
        };
      }

      renderBoard() {
        console.log("Rendering board...");
        this.updateTileSizeWithGap(); // Recalculate tile size based on current board width
        const boardElement = document.getElementById("game-board");
        boardElement.innerHTML = "";

        for (let y = 0; y < this.height; y++) {
          for (let x = 0; x < this.width; x++) {
            const tile = this.board[y][x];
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

        document.getElementById("try-again").addEventListener("click", () => this.initGame());
        document.getElementById("restart").addEventListener("click", () => this.initGame());
      }

      handleMouseDown(e) {
        if (this.gameOver || this.gameState !== "playerTurn" || this.currentTurn !== this.player1) {
          log("Cannot drag: Not Player 1's turn or game over");
          return;
        }
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
        if (this.gameOver || this.gameState !== "playerTurn" || this.currentTurn !== this.player1) {
          log("Cannot drag: Not Player 1's turn or game over");
          return;
        }
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
            this.sounds.badMove.play(); // Play bad move sound
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
        if (this.gameOver) return false;
        const matchResult = this.checkMatches(selectedX, selectedY);
        if (matchResult.hasMatches) {
          const { matches, type } = matchResult;
          if (matches.size >= 3) {
            this.handleMatches(matches, type);
          }
          return true;
        }
        return false;
      }

      checkMatches(selectedX = null, selectedY = null) {
        let hasMatches = false;
        const allMatches = new Set();
        let type = null;

        // Horizontal matches
        for (let y = 0; y < this.height; y++) {
          let matchStart = 0;
          let currentType = null;
          for (let x = 0; x <= this.width; x++) {
            const tile = x < this.width ? this.board[y][x] : null;
            const tileType = tile ? tile.type : null;

            if (tileType !== currentType || x === this.width) {
              const matchLength = x - matchStart;
              if (matchLength >= 3) {
                for (let i = matchStart; i < x; i++) {
                  allMatches.add(`${i},${y}`);
                }
                hasMatches = true;
                type = currentType;
              }
              matchStart = x;
              currentType = tileType;
            }
          }
        }

        // Vertical matches
        for (let x = 0; x < this.width; x++) {
          let matchStart = 0;
          let currentType = null;
          for (let y = 0; y <= this.height; y++) {
            const tile = y < this.height ? this.board[y][x] : null;
            const tileType = tile ? tile.type : null;

            if (tileType !== currentType || y === this.height) {
              const matchLength = y - matchStart;
              if (matchLength >= 3) {
                for (let i = matchStart; i < y; i++) {
                  allMatches.add(`${x},${i}`);
                }
                hasMatches = true;
                type = currentType;
              }
              matchStart = y;
              currentType = tileType;
            }
          }
        }

        return { hasMatches, matches: allMatches, type };
      }

      handleMatches(matches, type) {
        const attacker = this.currentTurn;
        const defender = this.currentTurn === this.player1 ? this.player2 : this.player1;

        matches.forEach(match => {
          const [x, y] = match.split(",").map(Number);
          if (this.board[y][x].element) {
            this.board[y][x].element.classList.add("matched");
          }
        });

        setTimeout(() => {
          matches.forEach(match => {
            const [x, y] = match.split(",").map(Number);
            this.board[y][x].type = null;
            this.board[y][x].element = null;
          });

          if (type === "first-attack") this.attack(attacker, defender, "first-attack", matches.size);
          else if (type === "second-attack") this.attack(attacker, defender, "second-attack", matches.size);
          else if (type === "special-attack") this.attack(attacker, defender, "special-attack", matches.size);
          else if (type === "power-up") this.usePowerup(attacker);
          else if (type === "last-stand") this.lastStand(attacker, defender, matches.size);

          this.sounds.match.play(); // Play match sound
          this.cascadeTiles(() => {
            this.endTurn();
          });
        }, 300);
      }

      cascadeTiles(callback) {
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
            this.sounds.cascade.play(); // Play cascade sound
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

      attack(attacker, defender, type, tileCount) {
        let damage = Math.round(attacker.strength * (tileCount === 3 ? 2 : tileCount === 4 ? 3 : 4));
        if (type === "special-attack") damage = Math.round(damage * 1.2);
        if (attacker.boostActive) {
          damage += 10;
          attacker.boostActive = false;
          log(`${attacker.name}'s Boost fades.`);
        }
        if (defender.lastStandActive) {
          damage = Math.max(0, damage - 5);
          defender.lastStandActive = false;
          log(`${defender.name}'s Last Stand mitigates 5 damage!`);
        }
        defender.health = Math.max(0, defender.health - damage);
        log(`${attacker.name} uses ${type === "first-attack" ? "Slash" : type === "second-attack" ? "Bite" : "Shadow Strike"} on ${defender.name} for ${damage} damage!`);
        this.updateHealth(defender);
        this.checkGameOver();
      }

      usePowerup(player) {
        if (player.powerup === "Heal") {
          player.health = Math.min(player.maxHealth, player.health + 10);
          log(`${player.name} uses Heal, restoring 10 HP!`);
        } else if (player.powerup === "Boost (Next Attack +10)") {
          player.boostActive = true;
          log(`${player.name} uses Power Surge, next attack +10 damage!`);
        } else if (player.powerup === "Minor Regen") {
          player.health = Math.min(player.maxHealth, player.health + 5);
          log(`${player.name} uses Minor Regen, restoring 5 HP!`);
        }
        this.updateHealth(player);
      }

      lastStand(attacker, defender, tileCount) {
        let damage = Math.round(attacker.strength * (tileCount === 3 ? 2 : tileCount === 4 ? 3 : 4));
        if (attacker.boostActive) {
          damage += 10;
          attacker.boostActive = false;
          log(`${attacker.name}'s Boost fades.`);
        }
        if (defender.lastStandActive) {
          damage = Math.max(0, damage - 5);
          defender.lastStandActive = false;
          log(`${defender.name}'s Last Stand mitigates 5 damage!`);
        }
        defender.health = Math.max(0, defender.health - damage);
        attacker.lastStandActive = true;
        log(`${attacker.name} uses Last Stand, dealing ${damage} damage to ${defender.name} and preparing to mitigate 5 damage on the next attack!`);
        this.updateHealth(defender);
        this.checkGameOver();
      }

      updateHealth(player) {
        const healthBar = player === this.player1 ? p1Health : p2Health;
        const hpText = player === this.player1 ? p1Hp : p2Hp;

        // Calculate health percentage
        const percentage = (player.health / player.maxHealth) * 100;

        // Set the width of the health bar
        healthBar.style.width = `${percentage}%`;

        // Determine the color based on health percentage
        let color;
        if (percentage > 75) {
          color = '#4CAF50'; // Green for 75-100%
        } else if (percentage > 50) {
          color = '#FFC105'; // Yellow for 50-75%
        } else if (percentage > 25) {
          color = '#FFA500'; // Orange for 25-50%
        } else {
          color = '#F44336'; // Red for 0-25%
        }

        // Apply the color to the health bar
        healthBar.style.backgroundColor = color;

        // Update the health text
        hpText.textContent = `${player.health}/${player.maxHealth}`;
      }

      endTurn() {
        if (this.gameState === "gameOver") return;
        this.currentTurn = this.currentTurn === this.player1 ? this.player2 : this.player1;
        this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
        turnIndicator.textContent = `${this.currentTurn === this.player1 ? "Player 1" : "Player 2"}'s Turn`;
        log(`Turn switched to ${this.currentTurn === this.player1 ? "Player 1" : "Player 2"}`);

        if (this.currentTurn === this.player2) {
          setTimeout(() => this.aiTurn(), 1000);
        }
      }

      aiTurn() {
        if (this.gameState !== "aiTurn" || this.currentTurn !== this.player2) {
          log("AI turn skipped: Wrong state or turn");
          return;
        }
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
        const matches = this.checkMatches().hasMatches;
        this.board[y1][x1] = temp1;
        this.board[y2][x2] = temp2;
        return matches;
      }

      checkGameOver() {
        if (this.player1.health <= 0) {
          this.gameState = "gameOver";
          gameOver.textContent = `${this.player2.name} Wins!`;
          log(`${this.player2.name} defeats ${this.player1.name}!`);
          document.getElementById("game-over-container").style.display = "block";
          this.sounds.gameOver.play();
		  setTimeout(this.sounds.loss.play(), 1000);
          p1Image.classList.add('grayscale'); // Gray out Player 1 (loser)
        } else if (this.player2.health <= 0) {
          this.gameState = "gameOver";
          gameOver.textContent = `${this.player1.name} Wins!`;
          log(`${this.player1.name} defeats ${this.player2.name}!`);
          document.getElementById("game-over-container").style.display = "block";
          this.sounds.gameOver.play();
		  setTimeout(this.sounds.win.play(), 1000);
          p2Image.classList.add('grayscale'); // Gray out Player 2 (loser)
        }
      }
    }

    // Utility Functions
    function randomChoice(arr) {
      return arr[Math.floor(Math.random() * arr.length)];
    }

    function log(message) {
      const li = document.createElement("li");
      li.textContent = message;
      battleLog.insertBefore(li, battleLog.firstChild);
      if (battleLog.children.length > 10) battleLog.removeChild(battleLog.lastChild);
    }

    // DOM Elements
    const turnIndicator = document.getElementById("turn-indicator");
    const p1Name = document.getElementById("p1-name");
    const p1Type = document.getElementById("p1-type");
    const p1Hp = document.getElementById("p1-hp");
    const p1Health = document.getElementById("p1-health");
    const p1Powerup = document.getElementById("p1-powerup");
    const p1Image = document.getElementById("p1-image");
    const p2Name = document.getElementById("p2-name");
    const p2Type = document.getElementById("p2-type");
    const p2Hp = document.getElementById("p2-hp");
    const p2Health = document.getElementById("p2-health");
    const p2Powerup = document.getElementById("p2-powerup");
    const p2Image = document.getElementById("p2-image");
    const battleLog = document.getElementById("battle-log");
    const gameOver = document.getElementById("game-over");

    // Start the game
    document.addEventListener("DOMContentLoaded", () => {
      const game = new MonstrocityMatch3();
    });
  </script>
</body>
</html>
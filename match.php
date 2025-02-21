<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>
<!DOCTYPE html>

    <style>
        body {
            background: #0F0F0F;
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        #score, #matches {
            font-size: 24px;
            margin: 10px 0;
            font-family: Arial;
            color: #fff;
            text-align: center;
        }
        #game-board {
            display: grid;
            gap: 0.5vh;
            background: #333;
            padding: 1vh;
            width: min(90vh, 90vw);
            height: min(90vh, 90vw);
            grid-template-columns: repeat(8, 1fr);
            box-sizing: border-box;
            user-select: none;
            position: relative;
            touch-action: none;
        }
        .tile {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2vh;
            cursor: pointer;
            transition: transform 0.2s ease, filter 0.5s ease;
            position: relative;
            background: #444;
            box-sizing: border-box;
            padding: 0.25vh;
            z-index: 1;
        }
        .tile.game-over {
            filter: grayscale(100%);
        }
        .tile img {
            width: 80%;
            height: 80%;
            object-fit: contain;
            position: absolute;
            z-index: 1;
        }
        .tile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.6;
            z-index: 0;
        }
        .selected {
            transform: scale(1.05);
            border: 0.25vh solid white;
            z-index: 10;
            pointer-events: none;
            padding: 0;
        }
        .matched {
            animation: matchAnimation 0.3s ease forwards;
        }
        .falling {
            transition: transform 0.3s ease-out;
        }
        .bomb-creation {
            animation: bombPopIn 0.5s ease forwards;
        }
        .carbon-clear {
            animation: carbonSweep 0.8s ease forwards;
        }
        .diamond-clear {
            animation: diamondShockwave 1s ease forwards;
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
            margin: 0;
            animation: gameOverPulse 1s ease-in-out infinite;
        }
        #try-again {
            font-size: 24px;
            font-family: Arial;
            color: #ffffff;
            background-color: #444;
            border: 2px solid #fff;
            padding: 10px 20px;
            margin-top: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
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
        @keyframes bombPopIn {
            0% { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes carbonSweep {
            0% { transform: scale(1); opacity: 1; background-color: #ff6600; box-shadow: 0 0 10px #ff3300; }
            50% { transform: scale(1.2); opacity: 0.8; background-color: #ff9900; box-shadow: 0 0 20px #ff6600; }
            100% { transform: scale(0); opacity: 0; background-color: #ffcc00; box-shadow: 0 0 30px #ff9900; }
        }
        @keyframes diamondShockwave {
            0% { transform: scale(1); opacity: 1; background-color: #ffffff; box-shadow: 0 0 0 #ff00ff; }
            50% { transform: scale(1.5); opacity: 0.8; background-color: #ff00ff; box-shadow: 0 0 20px #ff00ff; }
            75% { transform: scale(1); opacity: 0.5; background-color: #9900cc; box-shadow: 0 0 40px #ff00ff; }
            100% { transform: scale(0); opacity: 0; background-color: #000000; box-shadow: 0 0 60px #ff00ff; }
        }
        @keyframes gameOverPulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 0.8; }
        }
    </style>

    <div id="matches">Matches: 0</div>
    <div id="score">Score: 0</div>
    <div id="game-board"></div>
    <div id="game-over-container">
        <div id="game-over">GAME OVER</div>
        <button id="try-again">TRY AGAIN</button>
    </div>

    <script>
class Match3Game {
    constructor(width, height) {
        this.width = width;
        this.height = height;
        this.board = [];
        this.selectedTile = null;
        this.score = 0;
        this.matchCount = 0;
        this.matchLimit = 25; // Changed from 20 to 25
        this.gameOver = false;
        this.allIcons = [
            'https://www.skulliance.io/staking/icons/dark.png',
            'https://www.skulliance.io/staking/icons/maxi.png',
            'https://www.skulliance.io/staking/icons/dank.png',
            'https://www.skulliance.io/staking/icons/mipa.png',
            'https://www.skulliance.io/staking/icons/ustra.png',
            'https://www.skulliance.io/staking/icons/nat.png',
            'https://www.skulliance.io/staking/icons/fire.png',
            'https://www.skulliance.io/staking/icons/eye.png',
            'https://www.skulliance.io/staking/icons/sharon.png',
            'https://www.skulliance.io/staking/icons/lens.png',
            'https://www.skulliance.io/staking/icons/kala.png',
            'https://www.skulliance.io/staking/icons/ass.png',
            'https://www.skulliance.io/staking/icons/moon.png',
            'https://www.skulliance.io/staking/icons/ritual.png',
            'https://www.skulliance.io/staking/icons/wave.png',
            'https://www.skulliance.io/staking/icons/soul.png',
            'https://www.skulliance.io/staking/icons/stag.png',
            'https://www.skulliance.io/staking/icons/skowl.png',
            'https://www.skulliance.io/staking/icons/loot.png',
            'https://www.skulliance.io/staking/icons/venus.png',
            'https://www.skulliance.io/staking/icons/axion.png',
            'https://www.skulliance.io/staking/icons/void.png',
            'https://www.skulliance.io/staking/icons/muse.png',
            'https://www.skulliance.io/staking/icons/dn.png',
            'https://www.skulliance.io/staking/icons/tribe.png',
            'https://www.skulliance.io/staking/icons/star.png',
            'https://www.skulliance.io/staking/icons/dread.png',
            'https://www.skulliance.io/staking/icons/hype.png',
            'https://www.skulliance.io/staking/icons/sinder.png',
            'https://www.skulliance.io/staking/icons/cyber.png',
            'https://www.skulliance.io/staking/icons/crypt.png'
        ];
        this.specialIcons = {
            carbon: 'https://www.skulliance.io/staking/icons/carbon.png',
            diamond: 'https://www.skulliance.io/staking/icons/diamond.png'
        };
        this.colorPalette = [
            '#800000',
            '#008080',
            '#408000',
            '#4B0082',
            '#666633',
            '#804000',
            '#004080'
        ];
        this.icons = this.selectRandomIcons(7);
        this.iconColorMap = this.createIconColorMap();
        this.specialTypes = { bomb4: 'carbon', bomb5: 'diamond' };
        this.isDragging = false;
        this.matchCheckCount = 0;
        this.targetTile = null;
        this.dragDirection = null;
        this.offsetX = 0;
        this.offsetY = 0;

        this.bonusScores = {
            carbonDetonation: 50,
            diamondDetonation: 100,
            carbonCleared: 25,
            diamondCleared: 50
        };

        // Sound effects from https://www.skulliance.io/staking/sounds/
        this.sounds = {
            match: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'), // Single sound for all matches
            bombAppear: new Audio('https://www.skulliance.io/staking/sounds/hyperspace_gem_land_1.ogg'), // Carbon/Diamond creation
            carbonExplode: new Audio('https://www.skulliance.io/staking/sounds/bomb_explode.ogg'), // Carbon bomb clear
            diamondExplode: new Audio('https://www.skulliance.io/staking/sounds/gem_shatters.ogg'), // Diamond bomb clear
            cascade: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'), // Tiles falling
            badMove: new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'), // Invalid swap
            gameOver: new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'), // Game over sound
            reset: new Audio('https://www.skulliance.io/staking/sounds/voice_welcomeback.ogg') // Reset game sound
        };
        Object.values(this.sounds).forEach(sound => {
            sound.preload = 'auto'; // Preload sounds to minimize delays
        });

        const boardSize = Math.min(window.innerHeight * 0.9, window.innerWidth * 0.9);
        this.tileSizeWithGap = (boardSize - (0.5 * (this.height - 1))) / this.height;

        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;

        this.initBoard();
        this.renderBoard();
        this.addEventListeners();
        
        const board = document.getElementById('game-board');
        board.style.pointerEvents = 'auto';

        this.tryAgainButton = document.getElementById('try-again');
        this.tryAgainButton.addEventListener('click', () => {
            console.log('Try Again button clicked!');
            this.resetGame();
        });
    }

    // Helper method to play sounds
    playSound(soundName) {
        const sound = this.sounds[soundName];
        if (sound) {
            sound.currentTime = 0; // Reset to start
            sound.play().catch(error => console.log('Sound error:', error));
        }
    }

    resetGame() {
        console.log('Resetting game...');
        this.score = 0;
        this.matchCount = 0;
        this.gameOver = false;
        document.getElementById('score').textContent = `Score: ${this.score}`;
        document.getElementById('matches').textContent = `Matches: ${this.matchCount}`;
        
        const board = document.getElementById('game-board');
        const tiles = board.querySelectorAll('.tile');
        tiles.forEach(tile => tile.classList.remove('game-over'));
        
        const gameOverContainer = document.getElementById('game-over-container');
        gameOverContainer.style.display = 'none';
        
        this.playSound('reset'); // Play reset sound
        this.initBoard();
    }

    selectRandomIcons(count) {
        const shuffled = [...this.allIcons].sort(() => 0.5 - Math.random());
        return shuffled.slice(0, count);
    }

    createIconColorMap() {
        const map = {};
        this.icons.forEach((icon, index) => {
            map[icon] = this.colorPalette[index % this.colorPalette.length];
        });
        map[this.specialIcons.carbon] = '#333300';
        map[this.specialIcons.diamond] = '#000000';
        return map;
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
                    (x >= 2 && this.board[y][x-1].icon === tile.icon && this.board[y][x-2].icon === tile.icon) ||
                    (y >= 2 && this.board[y-1][x].icon === tile.icon && this.board[y-2][x].icon === tile.icon)
                );
                this.board[y][x] = tile;
            }
        }
        this.renderBoard();
        this.resolveMatches();
    }

    createRandomTile() {
        return {
            icon: this.icons[Math.floor(Math.random() * this.icons.length)],
            special: null,
            element: null
        };
    }

    renderBoard() {
        const boardElement = document.getElementById('game-board');
        boardElement.innerHTML = ''; // Clear the board

        for (let y = 0; y < this.height; y++) {
            for (let x = 0; x < this.width; x++) {
                const tile = this.board[y][x];
                const tileElement = document.createElement('div');
                tileElement.className = 'tile';
                if (this.gameOver) {
                    tileElement.classList.add('game-over');
                }
                
                if (tile.special) {
                    const img = document.createElement('img');
                    img.src = this.specialIcons[tile.special];
                    tileElement.appendChild(img);
                    tileElement.style.backgroundColor = this.iconColorMap[this.specialIcons[tile.special]];
                } else if (tile.icon) {
                    const img = document.createElement('img');
                    img.src = tile.icon;
                    tileElement.appendChild(img);
                    tileElement.style.backgroundColor = this.iconColorMap[tile.icon];
                }

                tileElement.dataset.x = x;
                tileElement.dataset.y = y;
                boardElement.appendChild(tileElement);
                tile.element = tileElement;

                if (!this.isDragging || (this.selectedTile && (this.selectedTile.x !== x || this.selectedTile.y !== y))) {
                    tileElement.style.transform = 'translate(0, 0)';
                }
            }
        }
        
        const gameOverContainer = document.getElementById('game-over-container');
        gameOverContainer.style.display = this.gameOver ? 'block' : 'none';
    }

    addEventListeners() {
        const board = document.getElementById('game-board');
        
        if (this.isTouchDevice) {
            board.addEventListener('touchstart', (e) => this.handleTouchStart(e));
            board.addEventListener('touchmove', (e) => this.handleTouchMove(e));
            board.addEventListener('touchend', (e) => this.handleTouchEnd(e));
        } else {
            board.addEventListener('mousedown', (e) => this.handleMouseDown(e));
            board.addEventListener('mousemove', (e) => this.handleMouseMove(e));
            board.addEventListener('mouseup', (e) => this.handleMouseUp(e));
        }
    }

    handleMouseDown(e) {
        if (this.gameOver) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add('selected');

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        this.offsetX = e.clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
    }

    handleMouseMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver) return;
        e.preventDefault();

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        const mouseX = e.clientX - boardRect.left - this.offsetX;
        const mouseY = e.clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;
        selectedTileElement.style.transition = '';

        if (!this.dragDirection) {
            const dx = Math.abs(mouseX - (this.selectedTile.x * this.tileSizeWithGap));
            const dy = Math.abs(mouseY - (this.selectedTile.y * this.tileSizeWithGap));
            if (dx > dy && dx > 5) this.dragDirection = 'row';
            else if (dy > dx && dy > 5) this.dragDirection = 'column';
        }

        if (!this.dragDirection) return;

        if (this.dragDirection === 'row') {
            const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, mouseX));
            selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
            this.targetTile = {
                x: Math.round(constrainedX / this.tileSizeWithGap),
                y: this.selectedTile.y
            };
        } else if (this.dragDirection === 'column') {
            const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, mouseY));
            selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
            this.targetTile = {
                x: this.selectedTile.x,
                y: Math.round(constrainedY / this.tileSizeWithGap)
            };
        }
    }

    handleMouseUp(e) {
        if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver) {
            if (this.selectedTile) {
                const tile = this.board[this.selectedTile.y][this.selectedTile.x];
                if (tile.element) tile.element.classList.remove('selected');
            }
            this.isDragging = false;
            this.selectedTile = null;
            this.targetTile = null;
            this.dragDirection = null;
            this.renderBoard();
            this.playSound('badMove'); // Invalid move sound
            return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        if (tile.element) tile.element.classList.remove('selected');

        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
    }

    handleTouchStart(e) {
        if (this.gameOver) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e.touches[0]);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add('selected');

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        this.offsetX = e.touches[0].clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.touches[0].clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
    }

    handleTouchMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver) return;
        e.preventDefault();

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        const touchX = e.touches[0].clientX - boardRect.left - this.offsetX;
        const touchY = e.touches[0].clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;

        requestAnimationFrame(() => {
            if (!this.dragDirection) {
                const dx = Math.abs(touchX - (this.selectedTile.x * this.tileSizeWithGap));
                const dy = Math.abs(touchY - (this.selectedTile.y * this.tileSizeWithGap));
                if (dx > dy && dx > 7) this.dragDirection = 'row';
                else if (dy > dx && dy > 7) this.dragDirection = 'column';
            }

            selectedTileElement.style.transition = '';

            if (this.dragDirection === 'row') {
                const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, touchX));
                selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
                this.targetTile = {
                    x: Math.round(constrainedX / this.tileSizeWithGap),
                    y: this.selectedTile.y
                };
            } else if (this.dragDirection === 'column') {
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
        if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver) {
            if (this.selectedTile) {
                const tile = this.board[this.selectedTile.y][this.selectedTile.x];
                if (tile.element) tile.element.classList.remove('selected');
            }
            this.isDragging = false;
            this.selectedTile = null;
            this.targetTile = null;
            this.dragDirection = null;
            this.renderBoard();
            this.playSound('badMove'); // Invalid move sound
            return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        if (tile.element) tile.element.classList.remove('selected');

        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
    }

    getTileFromEvent(e) {
        const boardRect = document.getElementById('game-board').getBoundingClientRect();
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

        selectedElement.style.transition = 'transform 0.2s ease';
        selectedElement.style.transform = `translate(${dx}px, ${dy}px)`;

        let i = 0;
        if (startY === endY) {
            for (let x = Math.min(startX, endX); x <= Math.max(startX, endX); x++) {
                if (x === startX) continue;
                const offsetX = direction * -tileSizeWithGap * (x - startX) / Math.abs(endX - startX);
                tileElements[i].style.transition = 'transform 0.2s ease';
                tileElements[i].style.transform = `translate(${offsetX}px, 0)`;
                i++;
            }
        } else {
            for (let y = Math.min(startY, endY); y <= Math.max(startY, endY); y++) {
                if (y === startY) continue;
                const offsetY = direction * -tileSizeWithGap * (y - startY) / Math.abs(endY - startY);
                tileElements[i].style.transition = 'transform 0.2s ease';
                tileElements[i].style.transform = `translate(0, ${offsetY}px)`;
                i++;
            }
        }

        setTimeout(() => {
            if (startY === endY) {
                const row = this.board[startY];
                const tempRow = [...row];
                if (startX < endX) {
                    for (let x = startX; x < endX; x++) {
                        row[x] = tempRow[x + 1];
                    }
                } else {
                    for (let x = startX; x > endX; x--) {
                        row[x] = tempRow[x - 1];
                    }
                }
                row[endX] = tempRow[startX];
            } else {
                const tempCol = [];
                for (let y = 0; y < this.height; y++) {
                    tempCol[y] = { ...this.board[y][startX] };
                }
                if (startY < endY) {
                    for (let y = startY; y < endY; y++) {
                        this.board[y][startX] = tempCol[y + 1];
                    }
                } else {
                    for (let y = startY; y > endY; y--) {
                        this.board[y][startX] = tempCol[y - 1];
                    }
                }
                this.board[endY][endX] = tempCol[startY];
            }

            this.renderBoard();
            const hasMatches = this.resolveMatches(endX, endY);

            if (hasMatches) {
                this.matchCount++;
                document.getElementById('matches').textContent = `Matches: ${this.matchCount}`;
                
                if (this.matchCount >= this.matchLimit) { // Changed from 20 to 25
                    this.endGame();
                }
            } else {
                console.log(`No match, reverting tiles from (${startX}, ${startY}) to (${endX}, ${endY})`);
                selectedElement.style.transition = 'transform 0.2s ease';
                selectedElement.style.transform = 'translate(0, 0)';
                tileElements.forEach(element => {
                    element.style.transition = 'transform 0.2s ease';
                    element.style.transform = 'translate(0, 0)';
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
                }, 200);
            }
        }, 200);
    }

    async endGame() {
        console.log('Starting endgame sequence...');
        const board = document.getElementById('game-board');
        const gameOverContainer = document.getElementById('game-over-container');

        let bombPositions = this.getAllBombPositions();
        console.log(`Found ${bombPositions.length} bombs for grand finale`);

        while (bombPositions.length > 0) {
            const bomb = bombPositions.shift();
            if (bomb.type === 'carbon') {
                await this.clearRowAndColumn(bomb.x, bomb.y, true);
            } else if (bomb.type === 'diamond') {
                await this.clearBoard(bomb.x, bomb.y, true);
            }
            this.showerTiles();
            this.cascadeTilesWithoutRender();
            this.renderBoard();
            await new Promise(resolve => setTimeout(resolve, 250));

            const newBombs = this.getAllBombPositions();
            bombPositions = [...bombPositions, ...newBombs.filter(nb => 
                !bombPositions.some(b => b.x === nb.x && b.y === nb.y))];
            console.log(`Detonated at (${bomb.x}, ${bomb.y}), ${bombPositions.length} bombs remain`);
        }

        await new Promise(resolve => setTimeout(resolve, 1000));
        let moved = true;
        let iterations = 0;
        const maxIterations = 20;
        while (moved && iterations < maxIterations) {
            moved = this.cascadeTilesWithoutRender();
            this.showerTiles();
            this.renderBoard();
            await new Promise(resolve => setTimeout(resolve, 300));
            iterations++;

            let hasMatches = false;
            for (let y = 0; y < this.height && !hasMatches; y++) {
                for (let x = 0; x < this.width && !hasMatches; x++) {
                    if (this.checkMatches(x, y).hasMatches) {
                        hasMatches = true;
                    }
                }
            }
            if (!hasMatches) break;
        }

        console.log('Board is calm, showing game over...');
        const tiles = board.querySelectorAll('.tile');
        tiles.forEach(tile => tile.classList.add('game-over'));
        gameOverContainer.style.display = 'block';
        this.gameOver = true;
        this.playSound('gameOver'); // Play game over sound
        console.log('Game Over - Grand finale completed!');
    }

    getAllBombPositions() {
        const bombPositions = [];
        for (let y = 0; y < this.height; y++) {
            for (let x = 0; x < this.width; x++) {
                const tile = this.board[y][x];
                if (tile.special) {
                    bombPositions.push({ x, y, type: tile.special });
                }
            }
        }
        return bombPositions;
    }

    async clearRowAndColumn(x, y, isEndGame = false) {
        console.log(`Clearing row ${y} and column ${x} (Carbon Bomb)`);
        this.playSound('carbonExplode'); // Carbon bomb explosion sound
        const affectedTiles = new Set();
        const newBombs = [];

        if (this.board[y][x].element) {
            this.board[y][x].element.classList.add('carbon-clear');
        }

        for (let i = 0; i < this.width; i++) {
            if (i !== x && this.board[y][i].element) {
                affectedTiles.add(`${i},${y}`);
            }
        }
        for (let j = 0; j < this.height; j++) {
            if (j !== y && this.board[j][x].element) {
                affectedTiles.add(`${x},${j}`);
            }
        }

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            if (this.board[ty][tx].element) {
                this.board[ty][tx].element.classList.add('carbon-clear');
            }
        });

        await new Promise(resolve => setTimeout(resolve, isEndGame ? 250 : 800));

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            if (this.board[ty][tx].special && !isEndGame) {
                newBombs.push({ x: tx, y: ty, type: this.board[ty][tx].special });
            }
        });

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            this.board[ty][tx].icon = null;
            this.board[ty][tx].special = null;
            this.board[ty][tx].element = null;
        });
        this.board[y][x].icon = null;
        this.board[y][x].special = null;
        this.board[y][x].element = null;

        this.score += (affectedTiles.size + 1) * 10;
        if (!isEndGame) {
            this.score += this.bonusScores.carbonCleared;
        }
        document.getElementById('score').textContent = `Score: ${this.score}`;
        console.log(`Carbon bomb cleared ${affectedTiles.size + 1} tiles, added ${(affectedTiles.size + 1) * 10} points`);

        if (!isEndGame) {
            for (const bomb of newBombs) {
                await this.handleBombDetonation(bomb.x, bomb.y, bomb.type);
            }
        }

        return isEndGame ? [] : newBombs;
    }

    async clearBoard(x = null, y = null, isEndGame = false) {
        console.log(`Clearing entire board (Diamond Bomb)${x !== null && y !== null ? ` at (${x}, ${y})` : ''}`);
        this.playSound('diamondExplode'); // Diamond bomb explosion sound
        const affectedTiles = new Set();
        const newBombs = [];

        if (x !== null && y !== null && this.board[y][x].element) {
            this.board[y][x].element.classList.add('diamond-clear');
        }

        for (let ty = 0; ty < this.height; ty++) {
            for (let tx = 0; tx < this.width; tx++) {
                if ((tx !== x || ty !== y) && this.board[ty][tx].element) {
                    affectedTiles.add(`${tx},${ty}`);
                    this.board[ty][tx].element.classList.add('diamond-clear');
                }
            }
        }

        await new Promise(resolve => setTimeout(resolve, isEndGame ? 250 : 1000));

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            if (this.board[ty][tx].special && !isEndGame) {
                newBombs.push({ x: tx, y: ty, type: this.board[ty][tx].special });
            }
        });

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            this.board[ty][tx].icon = null;
            this.board[ty][tx].special = null;
            this.board[ty][tx].element = null;
        });
        if (x !== null && y !== null) {
            this.board[y][x].icon = null;
            this.board[y][x].special = null;
            this.board[y][x].element = null;
        }

        this.score += affectedTiles.size * 10 + (x !== null ? 10 : 0);
        if (!isEndGame) {
            this.score += this.bonusScores.diamondCleared;
        }
        document.getElementById('score').textContent = `Score: ${this.score}`;
        console.log(`Diamond bomb cleared ${affectedTiles.size + (x !== null ? 1 : 0)} tiles, added ${(affectedTiles.size + (x !== null ? 1 : 0)) * 10} points`);

        if (!isEndGame) {
            for (const bomb of newBombs) {
                await this.handleBombDetonation(bomb.x, bomb.y, bomb.type);
            }
        }

        return isEndGame ? [] : newBombs;
    }

    async handleBombDetonation(x, y, bombType) {
        console.log(`Detonating ${bombType} bomb at (${x}, ${y}) triggered by another bomb`);
        if (bombType === 'carbon') {
            this.score += this.bonusScores.carbonDetonation;
            console.log(`Carbon bomb chain-detonated at (${x}, ${y}), +${this.bonusScores.carbonDetonation} bonus`);
            await this.clearRowAndColumn(x, y);
        } else if (bombType === 'diamond') {
            this.score += this.bonusScores.diamondDetonation;
            console.log(`Diamond bomb chain-detonated at (${x}, ${y}), +${this.bonusScores.diamondDetonation} bonus`);
            await this.clearBoard(x, y);
        }
        this.showerTiles();
        this.renderBoard();
        await new Promise(resolve => setTimeout(resolve, 200));
        document.getElementById('score').textContent = `Score: ${this.score}`;
    }

    showerTiles() {
        console.log('Showering tiles...');
        for (let x = 0; x < this.width; x++) {
            let topEmpty = -1;
            for (let y = 0; y < this.height; y++) {
                if (!this.board[y][x].icon && !this.board[y][x].special) {
                    topEmpty = y;
                    break;
                }
            }
            if (topEmpty >= 0) {
                this.board[topEmpty][x] = this.createRandomTile();
            }
        }
    }

    cascadeTilesWithoutRender() {
        let moved = false;
        for (let x = 0; x < this.width; x++) {
            let emptySpaces = 0;
            for (let y = this.height - 1; y >= 0; y--) {
                if (!this.board[y][x].icon && !this.board[y][x].special) {
                    emptySpaces++;
                } else if (emptySpaces > 0) {
                    this.board[y + emptySpaces][x] = this.board[y][x];
                    this.board[y][x] = { icon: null, special: null, element: null };
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

    cascadeTiles() {
        console.log('Cascading tiles...');
        let moved = this.cascadeTilesWithoutRender();
        this.renderBoard();
        this.playSound('cascade'); // Tile cascade sound
        if (moved || this.matchCheckCount < 2) {
            this.matchCheckCount++;
            setTimeout(() => {
                const hasMatches = this.resolveMatches();
                if (!hasMatches) this.matchCheckCount = 0;
            }, 300);
        } else {
            this.matchCheckCount = 0;
        }
    }

    resolveMatches(selectedX = null, selectedY = null) {
        const matchResult = this.checkMatches(selectedX, selectedY);
        if (matchResult.hasMatches) {
            const { matches, bombType, bombX, bombY } = matchResult;
            if (matches.size >= 3) {
                const firstTile = this.board[bombY][bombX];
                if (firstTile && firstTile.special) {
                    this.handleBombMatches(matches, firstTile.special, bombX, bombY);
                } else {
                    this.handleMatches(matches, bombType, bombX, bombY);
                }
            }
            return true;
        }
        return false;
    }

    checkMatches(selectedX = null, selectedY = null) {
        let hasMatches = false;
        const allMatches = new Set();
        let bombType = null;
        let bombX, bombY;

        for (let y = 0; y < this.height; y++) {
            let matchStart = 0;
            let currentIcon = null;
            for (let x = 0; x <= this.width; x++) {
                const tile = x < this.width ? this.board[y][x] : null;
                const icon = tile ? (tile.special ? this.specialIcons[tile.special] : tile.icon) : null;

                if (icon !== currentIcon || x === this.width) {
                    const matchLength = x - matchStart;
                    if (matchLength >= 3) {
                        for (let i = matchStart; i < x; i++) {
                            allMatches.add(`${i},${y}`);
                        }
                        console.log(`Horizontal match of ${matchLength} at row ${y}, start ${matchStart}, end ${x - 1}`);
                        hasMatches = true;
                    }
                    matchStart = x;
                    currentIcon = icon;
                }
            }
        }

        for (let x = 0; x < this.width; x++) {
            let matchStart = 0;
            let currentIcon = null;
            for (let y = 0; y <= this.height; y++) {
                const tile = y < this.height ? this.board[y][x] : null;
                const icon = tile ? (tile.special ? this.specialIcons[tile.special] : tile.icon) : null;

                if (icon !== currentIcon || y === this.height) {
                    const matchLength = y - matchStart;
                    if (matchLength >= 3) {
                        for (let i = matchStart; i < y; i++) {
                            allMatches.add(`${x},${i}`);
                        }
                        console.log(`Vertical match of ${matchLength} at col ${x}, start ${matchStart}, end ${y - 1}`);
                        hasMatches = true;
                    }
                    matchStart = y;
                    currentIcon = icon;
                }
            }
        }

        if (hasMatches) {
            const matchSize = allMatches.size;
            console.log(`Total unique matched tiles: ${matchSize}, selected position: (${selectedX}, ${selectedY})`);
            
            if (selectedX !== null && selectedY !== null && allMatches.has(`${selectedX},${selectedY}`)) {
                bombX = selectedX;
                bombY = selectedY;
            } else {
                const lastMatch = Array.from(allMatches).pop();
                [bombX, bombY] = lastMatch.split(',').map(Number);
            }

            bombType = matchSize === 4 ? 'bomb4' : matchSize >= 5 ? 'bomb5' : null;
        }

        return { hasMatches, matches: allMatches, bombType, bombX, bombY };
    }

    handleMatches(matches, bombType, bombX, bombY) {
        this.playSound('match'); // Single match sound (no escalation)
        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            if (this.board[y][x].element) {
                this.board[y][x].element.classList.add('matched');
            }
        });

        setTimeout(() => {
            matches.forEach(match => {
                const [x, y] = match.split(',').map(Number);
                this.board[y][x].icon = null;
                this.board[y][x].special = null;
                this.board[y][x].element = null;
            });

            if (bombType) {
                this.createSpecialTile(bombX, bombY, bombType);
                this.board[bombY][bombX].element.classList.add('bomb-creation');
                this.playSound('bombAppear'); // Bomb creation sound
                console.log(`Bomb placed at (${bombX}, ${bombY})`);
            }

            this.score += matches.size * 10;
            document.getElementById('score').textContent = `Score: ${this.score}`;
            
            this.cascadeTiles();
        }, 300);
    }

    async handleBombMatches(matches, bombType, bombX, bombY) {
        this.playSound('match'); // Single match sound for bomb trigger (no escalation)
        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            if (this.board[y][x].element) {
                this.board[y][x].element.classList.add('matched');
            }
        });

        await new Promise(resolve => setTimeout(resolve, 300));

        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            this.board[y][x].icon = null;
            this.board[y][x].special = null;
            this.board[y][x].element = null;
        });

        this.score += matches.size * 10;

        if (bombType === 'carbon') {
            this.score += this.bonusScores.carbonDetonation;
            console.log(`Carbon bomb detonated at (${bombX}, ${bombY}), +${this.bonusScores.carbonDetonation} bonus`);
            await this.clearRowAndColumn(bombX, bombY);
        } else if (bombType === 'diamond') {
            this.score += this.bonusScores.diamondDetonation;
            console.log(`Diamond bomb detonated at (${bombX}, ${bombY}), +${this.bonusScores.diamondDetonation} bonus`);
            await this.clearBoard(bombX, bombY);
        }

        document.getElementById('score').textContent = `Score: ${this.score}`;
        
        this.cascadeTiles();
    }

    createSpecialTile(x, y, type) {
        this.board[y][x] = {
            icon: null,
            special: this.specialTypes[type],
            element: null
        };
        console.log(`Created ${this.specialTypes[type]} bomb at (${x}, ${y})`);
        this.renderBoard();
    }
}

const game = new Match3Game(8, 8);
    </script>
</body>
</html>
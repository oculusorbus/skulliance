class MusicPlayer {
    constructor(containerId, playlistData, autoPlay = false) {
        this.container = document.getElementById(containerId);
        this.playlist = playlistData;
        this.currentArtist = Object.keys(playlistData)[0];
        this.currentTrackIndex = 0;
        this.audio = new Audio();
        this.isPlaying = false;
        this.autoPlay = autoPlay;
        this.init();
    }

    init() {
        this.renderPlayer();
        this.setupEventListeners();
        this.loadTrack(this.currentTrackIndex);
        if (this.autoPlay) this.play();
    }

    renderPlayer() {
        this.container.innerHTML = `
            <div class="artist-selector">
                <select id="artistSelect">${Object.keys(this.playlist).map(a => 
                    `<option value="${a}" ${a === this.currentArtist ? 'selected' : ''}>${a}</option>`
                ).join('')}</select>
            </div>
            <div class="current-track" id="currentTrack">Select a track</div>
            <div class="controls">
                <button id="prevBtn">◄</button>
                <button id="playPauseBtn">▶</button>
                <button id="nextBtn">►</button>
            </div>
            <div class="volume-control">
                <button id="muteBtn">🔇</button>
                <input type="range" id="volumeSlider" min="0" max="1" step="0.1" value="1">
            </div>
            <div class="track-list" id="trackList"></div>
        `;
        this.renderTrackList();
    }

    renderTrackList() {
        document.getElementById('trackList').innerHTML = 
            this.playlist[this.currentArtist].map((t, i) => 
                `<div class="track-item" data-index="${i}">${t.title}</div>`
            ).join('');
    }

    setupEventListeners() {
        const els = {
            playPause: document.getElementById('playPauseBtn'),
            prev: document.getElementById('prevBtn'),
            next: document.getElementById('nextBtn'),
            mute: document.getElementById('muteBtn'),
            volume: document.getElementById('volumeSlider'),
            tracks: document.getElementById('trackList'),
            artist: document.getElementById('artistSelect')
        };

        els.playPause.addEventListener('click', () => this.togglePlayPause());
        els.prev.addEventListener('click', () => this.previousTrack());
        els.next.addEventListener('click', () => this.nextTrack());
        els.mute.addEventListener('click', () => this.toggleMute());
        els.volume.addEventListener('input', e => this.setVolume(e.target.value));
        
        els.tracks.addEventListener('click', e => {
            const item = e.target.closest('.track-item');
            if (item) {
                this.currentTrackIndex = parseInt(item.dataset.index);
                this.loadTrack(this.currentTrackIndex);
                this.play();
            }
        });

        els.artist.addEventListener('change', e => {
            this.currentArtist = e.target.value;
            this.currentTrackIndex = 0;
            this.renderTrackList();
            this.loadTrack(this.currentTrackIndex);
            if (this.isPlaying) this.play();
        });

        this.audio.addEventListener('ended', () => this.nextTrack());
    }

    loadTrack(index) {
        if (index >= 0 && index < this.playlist[this.currentArtist].length) {
            this.currentTrackIndex = index;
            const track = this.playlist[this.currentArtist][index];
            this.audio.src = track.url;
            document.getElementById('currentTrack').textContent = 
                `${this.currentArtist} - ${track.title}`;
        }
    }

    play() {
        this.audio.play();
        this.isPlaying = true;
        document.getElementById('playPauseBtn').textContent = '❚❚';
    }

    pause() {
        this.audio.pause();
        this.isPlaying = false;
        document.getElementById('playPauseBtn').textContent = '▶';
    }

    togglePlayPause() {
        this.isPlaying ? this.pause() : this.play();
    }

    nextTrack() {
        let next = this.currentTrackIndex + 1;
        if (next >= this.playlist[this.currentArtist].length) next = 0;
        this.loadTrack(next);
        if (this.isPlaying) this.play();
    }

    previousTrack() {
        let prev = this.currentTrackIndex - 1;
        if (prev < 0) prev = this.playlist[this.currentArtist].length - 1;
        this.loadTrack(prev);
        if (this.isPlaying) this.play();
    }

    setVolume(volume) {
        this.audio.volume = volume;
        document.getElementById('muteBtn').textContent = volume === '0' ? '🔈' : '🔇';
    }

    toggleMute() {
        this.audio.volume === 0 ? this.setVolume(1) : this.setVolume(0);
    }
}

const playlistData = {
    "Artist1": [
        { title: "Song 1", url: "path/to/artist1-song1.mp3" },
        { title: "Song 2", url: "path/to/artist1-song2.mp3" }
    ],
    "Artist2": [
        { title: "Track 1", url: "path/to/artist2-track1.mp3" },
        { title: "Track 2", url: "path/to/artist2-track2.mp3" }
    ]
};

const player = new MusicPlayer('musicPlayer', playlistData, false);
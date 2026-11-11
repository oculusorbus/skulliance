<?php
/*
 * dhcportal-ui.php — the board, the tray and the reveal.
 *
 * SPLIT OUT OF dhcportal.php because the page is markup and this is
 * behaviour, and the page was already long enough that the two were
 * getting read as one thing.
 *
 * THE PIECES ARE ROW-ALIGNED, which is what makes the board drawable
 * with no packing logic at all: a common is a quarter row, an uncommon
 * half, an epic a whole row, a legendary two, a mythic the board. They
 * stack. There is no Tetris here and there must never be.
 */
?>
<style>
.dhcp { --dhcp-ink:#e8eaed; --dhcp-dim:#7a9eb0; --dhcp-line:#1b3346;
  --dhcp-common:#5a7888; --dhcp-uncommon:#00c8a0; --dhcp-epic:#e6a449;
  --dhcp-legendary:#e6498a; --dhcp-mythic:#8a49e6;
  color: var(--dhcp-ink); }
.dhcp-head h2 { margin: 0 0 6px; text-align: left; }
.dhcp-head p { margin: 0 0 18px; max-width: 70ch; color: var(--dhcp-dim);
  font-size: .9rem; line-height: 1.5; text-align: left; }
.dhcp-note { color: var(--dhcp-dim); font-size: .88rem; text-align: left; }
.dhcp-warn { margin: 0 0 14px; padding: 9px 11px; font-size: .84rem; text-align: left;
  color: #ffd4d4; background-color: rgba(224,122,122,.1);
  background-image: var(--msp-lift-soft); border: 1px solid rgba(224,122,122,.35); }

/* ---- the rig: Portal, board, spares ---------------------------------- */
/*
 * THREE COLUMNS, and the areas are named so the phone can restack them
 * without the markup caring. On a phone it goes board, Portal, spares:
 * the board is what you came to look at, and the spares list is long
 * enough to bury everything under it if it went first.
 */
.dhcp-rig { display: grid; gap: 18px; align-items: start;
  grid-template-columns: 300px minmax(0,1fr) 320px;
  grid-template-areas: "portal board pick"; }
.dhcp-chute { grid-area: portal; }
.dhcp-boardwrap { grid-area: board; }
.dhcp-pick { grid-area: pick; }

/*
 * THE BOARD, ON THE OPERATOR.
 *
 * background-color FIRST and always: art deploys by FTP outside this
 * repo, so until operator.webp is on the server this is exactly the
 * panel that was here before -- dark ground, no broken frame, nothing
 * moved. The art is an improvement layered on, never a dependency.
 *
 * The scrim is not decoration either. Sixteen trait tiles have to read
 * against it, and the art underneath is busy at full strength.
 */
.dhcp-boardwrap { position: relative; border-radius: 4px; overflow: hidden;
  background-color: #071320;
  background-image: url(images/portal/operator.webp);
  background-size: cover; background-position: 50% 18%;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.05); }
.dhcp-boardwrap::before { content: ""; position: absolute; inset: 0;
  pointer-events: none;
  background: linear-gradient(180deg, rgba(7,19,32,.62) 0, rgba(7,19,32,.86) 100%); }
.dhcp-board { position: relative; z-index: 1; display: flex;
  flex-direction: column-reverse; gap: 6px; padding: 16px 16px 14px; }
/* column-reverse: row one is the BOTTOM row, because a board fills up
   rather than down and the tier climbs with it. */
.dhcp-row { position: relative; display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 6px; }
.dhcp-rowtag { position: absolute; left: 2px; top: -9px; z-index: 2;
  font-size: .52rem; letter-spacing: .12em; text-transform: uppercase;
  color: var(--dhcp-dim); opacity: .5; transition: opacity .2s, color .2s; }
.dhcp-row.lit .dhcp-rowtag { opacity: 1; color: var(--dhcp-uncommon); }
.dhcp-cell { display: block; aspect-ratio: 1; border-radius: 3px;
  background: rgba(4,12,22,.5); box-shadow: inset 0 0 0 1px rgba(255,255,255,.07); }

/* The pieces float over the cells, sized in cell-widths. */
.dhcp-pieces { position: absolute; inset: 16px 16px 14px; z-index: 2; pointer-events: none; }
.dhcp-piece { position: absolute; pointer-events: auto; cursor: pointer;
  border-radius: 3px; overflow: hidden; padding: 0; border: 0;
  background: #0d1e30; box-shadow: inset 0 0 0 1px rgba(255,255,255,.1);
  transition: transform .26s cubic-bezier(.2,.85,.3,1), opacity .2s linear; }
.dhcp-piece img { width: 100%; height: 100%; object-fit: cover; display: block; }
.dhcp-piece::after { content: ""; position: absolute; inset: 0; pointer-events: none;
  box-shadow: inset 0 0 0 2px var(--c, var(--dhcp-common)); border-radius: 3px; }
.dhcp-piece:hover { transform: scale(1.04); z-index: 3; }

.dhcp-readout { margin-top: 10px; font-size: .82rem; color: var(--dhcp-dim);
  text-align: left; min-height: 2.4em; line-height: 1.45; }
.dhcp-readout b { color: var(--dhcp-ink); font-weight: normal; }
.dhcp-readout .odds { color: var(--dhcp-uncommon); }

/* ---- the Portal ----------------------------------------------------- */
.dhcp-chute { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.dhcp-sign { width: 96px; padding: 7px 0; border-radius: 2px; background: #2ff07a;
  box-shadow: 0 0 22px rgba(47,240,122,.3); }
.dhcp-sign svg { display: block; width: 44px; margin: 0 auto; }
.dhcp-sign path { fill: #06110a; }
/*
 * THE MOUTH. Same FTP rule: the colour is the fallback and the art sits
 * on top of it, so a missing upload is a dark well rather than a hole.
 * The canvas draws over both.
 */
.dhcp-vortex { position: relative; width: 100%; aspect-ratio: 1; border-radius: 4px;
  overflow: hidden; background-color: #0a0410;
  background-image: url(images/portal/vortex.webp);
  background-size: cover; background-position: 50% 50%;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.06); }
.dhcp-vortex canvas { position: absolute; inset: 0; width: 100%; height: 100%; }
#dhcpGo { width: 100%; padding: 12px 14px; font: inherit; font-size: .95rem;
  letter-spacing: .08em; text-transform: uppercase; color: #04121c;
  background-color: var(--dhcp-uncommon); background-image: var(--msp-fill);
  border: 0; border-radius: 3px; cursor: pointer; box-shadow: 0 2px 0 rgba(0,0,0,.3); }
#dhcpGo:hover:not(:disabled) { background-color: #2ae0bd; }
#dhcpGo:active:not(:disabled) { background-image: var(--msp-fill-in);
  transform: translateY(2px); box-shadow: 0 0 0 rgba(0,0,0,.3); }
#dhcpGo:disabled { opacity: .4; cursor: default; box-shadow: none; }
#dhcpGo:focus-visible { outline: 2px solid #04121c; outline-offset: -4px; }
.dhcp-say { margin: 0; font-size: .78rem; color: var(--dhcp-dim); text-align: center;
  min-height: 1.2em; }

/* ---- your spares ---------------------------------------------------- */
.dhcp-pick { background-color: #0a1929; background-image: var(--msp-lift);
  border: 1px solid var(--dhcp-line); border-radius: 4px; padding: 12px; }
.dhcp-pick h3 { margin: 0 0 10px; font-size: .74rem; letter-spacing: .14em;
  text-transform: uppercase; color: var(--dhcp-dim); text-align: left; }
/*
 * CATEGORY TABS, the way the assembler browses traits. The first build
 * dumped all eight categories into one grid, which is unusable past a
 * few dozen pieces -- and inventing a third way to browse the same
 * traits would be worse than either.
 */
.dhcp-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px; }
.dhcp-tabs button { padding: 5px 8px; font: inherit; font-size: .64rem;
  letter-spacing: .06em; text-transform: uppercase; cursor: pointer;
  color: var(--dhcp-dim); background: rgba(255,255,255,.04);
  border: 1px solid transparent; border-radius: 2px; }
.dhcp-tabs button[aria-selected="true"] { color: #04121c;
  background: var(--dhcp-uncommon); border-color: var(--dhcp-uncommon); }
.dhcp-tabs button .n { opacity: .6; margin-left: 4px; }
/* The rarity filter matters more here than anywhere else: "show me my
   commons" is the whole clear-out. */
.dhcp-rar { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
.dhcp-rar button { width: 22px; height: 22px; padding: 0; cursor: pointer;
  border: 2px solid var(--c, var(--dhcp-common)); border-radius: 50%;
  background: transparent; }
.dhcp-rar button[aria-pressed="true"] { background: var(--c, var(--dhcp-common)); }
.dhcp-tray { display: grid; grid-template-columns: repeat(auto-fill, minmax(54px, 1fr));
  gap: 6px; max-height: 420px; overflow-y: auto; padding-right: 4px; }
.dhcp-empty { margin: 8px 0 0; font-size: .78rem; color: var(--dhcp-dim); text-align: left; }
.dhcp-chip { position: relative; aspect-ratio: 1; cursor: pointer; padding: 0; border: 0;
  border-radius: 3px; overflow: hidden; background: #0d1e30;
  transition: transform .14s ease, opacity .2s linear; }
.dhcp-chip img { width: 100%; height: 100%; object-fit: cover; display: block; }
.dhcp-chip::after { content: ""; position: absolute; inset: 0;
  box-shadow: inset 0 0 0 2px var(--c, var(--dhcp-common)); border-radius: 3px; }
.dhcp-chip:hover { transform: translateY(-2px); }
/* The size a piece will take, so the trade is visible before it is made. */
.dhcp-chip .sz { position: absolute; left: 3px; bottom: 3px; z-index: 2;
  font-size: .5rem; letter-spacing: .06em; padding: 1px 4px; border-radius: 2px;
  background: rgba(4,12,22,.85); color: var(--c, var(--dhcp-common)); }

@media (max-width: 1080px) {
  /* Two columns before one: the Portal and the board belong together and
     the spares list is the thing that wants the full width to browse. */
  .dhcp-rig { grid-template-columns: 260px minmax(0,1fr);
    grid-template-areas: "portal board" "pick pick"; }
}
@media (max-width: 720px) {
  /* BOARD FIRST. It is what the page is for, and the spares list is long
     enough to push everything else off the screen if it leads. */
  .dhcp-rig { grid-template-columns: minmax(0,1fr);
    grid-template-areas: "board" "portal" "pick"; }
  .dhcp-chute { max-width: 300px; margin: 0 auto; }
  .dhcp-tray { max-height: none; }
}
@media (prefers-reduced-motion: reduce) {
  .dhcp-piece, .dhcp-chip { transition: none; }
}
</style>
<script>
(function () {
	var D = window.DHCP;
	if (!D || !document.getElementById('dhcpBoard')) return;

	var boardEl = document.getElementById('dhcpBoard');
	var piecesEl = document.getElementById('dhcpPieces');
	var trayEl  = document.getElementById('dhcpTray');
	var readEl  = document.getElementById('dhcpReadout');
	var sayEl   = document.getElementById('dhcpSay');
	var goEl    = document.getElementById('dhcpGo');
	var busy = false;

	var LABEL = { run:'standard odds', placement_10:'improved odds',
	              placement_3:'strong odds', placement_1:'best odds' };
	var TIERNAME = { common:'Common', uncommon:'Uncommon', epic:'Epic',
	                 legendary:'Legendary', mythic:'Mythic' };

	function art(p) { return D.art + '/250/' + p.category + '/' + p.slug + '.png'; }
	function size(p) { return D.sizes[p.tier] || 1; }

	/*
	 * WHERE A PIECE SITS, derived rather than stored.
	 *
	 * Pieces are laid in order and every one is row-aligned, so a running
	 * slot count is the only state needed: a piece starting at slot n
	 * occupies column n%4 of row floor(n/4) and is as wide as it needs to
	 * be. Nothing can straddle awkwardly because nothing is allowed a
	 * size that does not divide a row.
	 */
	function layout(pieces) {
		var out = [], at = 0;
		pieces.forEach(function (p) {
			var s = size(p);
			out.push({ piece: p, at: at, w: Math.min(s, D.cols), h: Math.ceil(s / D.cols) });
			at += s;
		});
		return out;
	}

	function drawBoard(pieces) {
		var cell = 'calc((100% - ' + (D.cols - 1) * 6 + 'px) / ' + D.cols + ')';
		piecesEl.innerHTML = '';
		layout(pieces).forEach(function (L) {
			var p = L.piece;
			var col = L.at % D.cols, row = Math.floor(L.at / D.cols);
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'dhcp-piece';
			b.style.setProperty('--c', 'var(--dhcp-' + p.tier + ')');
			b.style.width  = 'calc(' + cell + ' * ' + L.w + ' + ' + (L.w - 1) * 6 + 'px)';
			b.style.height = 'calc(' + cell + ' * ' + L.h + ' + ' + (L.h - 1) * 6 + 'px)';
			b.style.left   = 'calc((' + cell + ' + 6px) * ' + col + ')';
			/* bottom, not top: the board fills upward. */
			b.style.bottom = 'calc((' + cell + ' + 6px) * ' + row + ')';
			b.title = TIERNAME[p.tier] + ' — click to take it off';
			b.innerHTML = '<img src="' + art(p) + '" alt="" loading="lazy" '
			            + 'onerror="this.style.display=\'none\'">';
			b.addEventListener('click', function () { send('remove', p.drop_id); });
			piecesEl.appendChild(b);
		});

		var slots = pieces.reduce(function (n, p) { return n + size(p); }, 0);
		var rows = Math.min(D.rows, Math.floor(slots / D.cols));
		Array.prototype.forEach.call(boardEl.querySelectorAll('.dhcp-row'), function (r, i) {
			r.classList.toggle('lit', i < rows);
		});

		var table = rows > 0 ? D.tables[rows - 1] : null;
		goEl.disabled = rows < 1 || busy;
		if (rows < 1) {
			readEl.innerHTML = '<b>' + slots + ' of ' + D.slots + ' slots.</b> '
			                 + 'A row is four slots. Fill one and the Portal will take it.';
			sayEl.textContent = 'Fill at least one row.';
		} else {
			var left = (rows < D.rows) ? ((rows + 1) * D.cols - slots) : 0;
			readEl.innerHTML = '<b>' + rows + ' row' + (rows === 1 ? '' : 's') + ' filled</b> — '
			  + 'rolling on <span class="odds">' + LABEL[table] + '</span>.'
			  + (left > 0 ? ' ' + left + ' more slot' + (left === 1 ? '' : 's') + ' for the next row.'
			              : ' This is the best the Portal has.');
			sayEl.textContent = pieces.length + ' piece' + (pieces.length === 1 ? '' : 's') + ' in.';
		}
	}

	/*
	 * THE PICKER. Category tabs and a rarity filter, which is how the
	 * assembler browses the same traits -- the first build dumped all
	 * eight categories into one grid and it is unusable past a few dozen
	 * pieces. Inventing a third way to browse would be worse than either.
	 */
	var tabsEl = document.getElementById('dhcpTabs');
	var rarEl  = document.getElementById('dhcpRar');
	var emptyEl = document.getElementById('dhcpEmpty');
	/*
	 * 'all' IS THE DEFAULT AND THAT IS THE POINT. The Portal opens on the
	 * whole pile laid out, because seeing the scale of your own junk is
	 * half of what makes a clear-out worth starting -- the tabs narrow
	 * it if you want, they are not a wall to click through first.
	 */
	var cat = 'all', rar = null;
	var TIERS = ['common', 'uncommon', 'epic', 'legendary', 'mythic'];
	var CATNAME = { arms:'Arms', background:'Background', companion:'Companion',
	                effects:'Effects', head:'Head', headgear:'Headgear',
	                torso:'Torso', weapon:'Weapon' };

	/* What is left in the tray, i.e. not already on the board. */
	function spare() {
		var on = {};
		D.board.forEach(function (p) { on[p.drop_id] = true; });
		return D.tray.filter(function (p) { return !on[p.drop_id]; });
	}

	function drawTabs(list) {
		var counts = { all: list.length };
		list.forEach(function (p) { counts[p.category] = (counts[p.category] || 0) + 1; });
		/* Categories the player actually holds, in a stable order. A tab
		   for a category they own none of is a dead end. */
		var cats = Object.keys(CATNAME).filter(function (c) { return counts[c]; });
		if (cats.indexOf(cat) < 0 && cat !== 'all') cat = 'all';

		tabsEl.innerHTML = '';
		[['all', 'All']].concat(cats.map(function (c) { return [c, CATNAME[c]]; }))
			.forEach(function (pair) {
				var b = document.createElement('button');
				b.type = 'button'; b.setAttribute('role', 'tab');
				b.setAttribute('aria-selected', pair[0] === cat ? 'true' : 'false');
				b.innerHTML = pair[1] + '<span class="n">' + (counts[pair[0]] || 0) + '</span>';
				b.addEventListener('click', function () { cat = pair[0]; render(); });
				tabsEl.appendChild(b);
			});

		rarEl.innerHTML = '';
		TIERS.forEach(function (t) {
			if (!list.some(function (p) { return p.tier === t; })) return;
			var b = document.createElement('button');
			b.type = 'button';
			b.style.setProperty('--c', 'var(--dhcp-' + t + ')');
			b.title = TIERNAME[t];
			b.setAttribute('aria-label', TIERNAME[t]);
			b.setAttribute('aria-pressed', rar === t ? 'true' : 'false');
			b.addEventListener('click', function () { rar = (rar === t) ? null : t; render(); });
			rarEl.appendChild(b);
		});
	}

	function drawTray() {
		var list = spare();
		drawTabs(list);
		var shown = list.filter(function (p) {
			return (cat === 'all' || p.category === cat) && (!rar || p.tier === rar);
		});
		trayEl.innerHTML = '';
		emptyEl.hidden = shown.length > 0;
		if (!list.length) emptyEl.textContent = 'Nothing spare. Everything you hold is on a Fighter.';
		else if (!shown.length) emptyEl.textContent = 'Nothing spare in that filter.';
		shown.forEach(function (p) {
			var sz = size(p);
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'dhcp-chip';
			b.style.setProperty('--c', 'var(--dhcp-' + p.tier + ')');
			b.title = TIERNAME[p.tier] + ' ' + (CATNAME[p.category] || p.category)
			        + ' — takes ' + sz + ' slot' + (sz === 1 ? '' : 's');
			b.innerHTML = '<img src="' + art(p) + '" alt="" loading="lazy" '
			            + 'onerror="this.style.display=\'none\'">'
			            + '<span class="sz">' + sz + '</span>';
			b.addEventListener('click', function () { send('add', p.drop_id); });
			trayEl.appendChild(b);
		});
	}

	function render() { drawBoard(D.board); drawTray(); }

	function send(action, dropId) {
		if (busy) return;
		busy = true; goEl.disabled = true;
		fetch('ajax/dhcportal-stage.php', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=' + encodeURIComponent(action) + '&drop_id=' + encodeURIComponent(dropId || 0)
		}).then(function (r) { return r.json(); }).then(function (d) {
			busy = false;
			if (d.board) D.board = d.board;
			if (!d.ok && d.why) sayEl.textContent = d.why;
			/*
			 * THE EJECT RULE, SAID OUT LOUD. The board silently shrinking
			 * is the version of this that reads as a bug, so the pieces
			 * the Portal handed back are named.
			 */
			if (d.returned && d.returned.length) {
				sayEl.textContent = d.returned.length + ' piece'
				  + (d.returned.length === 1 ? '' : 's') + ' came back: you did not need '
				  + (d.returned.length === 1 ? 'it' : 'them') + '.';
			}
			if (d.dropped) {
				sayEl.textContent = d.dropped + ' piece' + (d.dropped === 1 ? '' : 's')
				  + ' came off: you built with ' + (d.dropped === 1 ? 'it' : 'them') + '.';
			}
			render();
		}, function () { busy = false; sayEl.textContent = 'That did not reach the Portal.'; render(); });
	}

	goEl.addEventListener('click', function () {
		if (busy || !D.board.length) return;
		busy = true; goEl.disabled = true;
		sayEl.textContent = 'Feeding the Portal...';
		var fed = D.board.slice();
		fetch('ajax/dhcportal-jettison.php', {
			method: 'POST', credentials: 'same-origin'
		}).then(function (r) { return r.json(); }).then(function (d) {
			busy = false;
			if (!d.ok) {
				sayEl.textContent = d.why || 'The Portal refused.';
				if (d.reload) { setTimeout(function () { location.reload(); }, 1400); }
				else { goEl.disabled = false; }
				return;
			}
			D.board = [];
			/* The tray loses what went in; the page reloads after the
			   reveal rather than trying to reconcile it here. */
			window.DHCP_REVEAL(fed, d);
		}, function () {
			busy = false; goEl.disabled = false;
			sayEl.textContent = 'That did not reach the Portal. Nothing was taken.';
		});
	});

	render();
})();
</script>
<?php include __DIR__ . '/dhcportal-reveal.php'; ?>

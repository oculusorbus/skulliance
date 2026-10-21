$(window).on('load', function () {
  $('#loading').hide();
});

var currentRound = 0;
var lastRound = 0;
// Cycle through each Drop Ship round result and hide them. Document the last death round.
[].forEach.call(document.querySelectorAll('.round'), function (el) {
  el.style.visibility = 'hidden';
  lastRound++;
});

/*
 * A ROUND, FOUND BY POSITION RATHER THAN BY BARE NUMERIC ID.
 *
 * Every round div carries id='0', '1', '2' ... and displayRound() used to
 * reach them with document.getElementById(n). Those ids are not unique on
 * this page: the Armory, the soldier cards and the results tables all
 * render elements whose id is a small database integer, and every one of
 * them appears BEFORE #hidden-results in the document -- so getElementById
 * returns whichever came first. Land on one of those and it has no <h3>
 * and no <img>, the lookup throws, and the handler dies partway through:
 * the caption and the picture stop updating while whatever already started
 * playing carries on. Which ids collide depends on how many items and
 * soldiers a theme renders, which is why it looked different per theme.
 *
 * The rounds are emitted in order inside #hidden-results, so position IS
 * the round number, and scoping the lookup there cannot collide with
 * anything outside it.
 */
function roundEl(n) {
  var box = document.getElementById('hidden-results');
  if (!box) return null;
  return box.querySelectorAll('.round')[n] || null;
}

/*
 * ...AND THE READS DEGRADE RATHER THAN THROW. Scoping the lookup removes
 * the collision, but a round that is missing for any other reason would
 * then be a null dereference in the same place -- which is the failure
 * being fixed, wearing a different hat. A round that cannot be read now
 * leaves the display alone and lets the rest of the handler run, so the
 * button stays usable instead of dying mid-step.
 */
function roundCaption(n) {
  var el = roundEl(n);
  var h3 = el ? el.getElementsByTagName('h3')[0] : null;
  return h3 ? h3.innerHTML : '';
}
function roundImage(n) {
  var el = roundEl(n);
  var img = el ? el.getElementsByTagName('img')[0] : null;
  return img ? img.src : '';
}

// Toggle Audio On & Off
/*
 * Animation on/off for a still theme that also ships gifs.
 *
 * RELOADS, where toggleAudio() does not. Audio is a client-side property
 * of elements already on the page; this decides which FILES the server
 * renders into the markup -- the round art, and whether the Ken Burns pan
 * is laid over it -- so nothing changes until the page is drawn again.
 * Waiting for the request to land first, or the reload races the session
 * write and the page comes back with the old setting.
 */
function toggleAnimation(status){
	var want = status ? "true" : "false";
	var done = function(){ window.location.reload(); };
	if (window.fetch) {
		fetch("ajax/toggle-animation.php?status=" + want, { credentials: "same-origin" })
			.then(done, done);
	} else {
		var x = new XMLHttpRequest();
		x.open("GET", "ajax/toggle-animation.php?status=" + want, true);
		x.onloadend = done;
		x.send();
	}
}

function toggleAudio(status){
	audio1 = document.getElementById("audio1");
	audio2 = document.getElementById("audio2");
	video1 = document.getElementById("dropshipPromoVideo");
	audioIcon = document.getElementById("audio-icon");
	if(status){
		audioIcon.src = "icons/audio-on.png";
		status = "true";
	}else{
		audioIcon.src = "icons/audio-off.png";
		status = "false";
	}
	if(audio1 != null){
		if(status == "true"){
			audio1.muted = false;
			audio2.muted = false;
		}else{
			audio1.muted = true;
			audio2.muted = true;
		}
	}
	if(video1 != null){
		if(status == "true"){
			video1.muted = false;
		}else{
			video1.muted = true;
		}	
	}
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/toggle-audio.php?status='+status, true);
	xhttp.send();
}

function replaceAll(string, search, replace) {
  return string.split(search).join(replace);
}

/* toggle3D() retired along with the feature. It rewrote "png" to "gif"
   across the whole of #hidden-results with a blanket replace, so it also
   caught the word anywhere else in that markup. The extension is a property
   of the theme now (dropship_themes.extension). */

// Check DISCOIN transaction
function checkTransaction(){
	document.getElementById("loading-transaction").style.display = "block";
	var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        document.getElementById("loading-transaction").style.display = "none";
		alert(this.responseText);
      }
    };
	xhttp.open('GET', 'ajax/transaction.php', true);
	xhttp.send();
}

/* evaluateAudio() and evaluateRoundAudio() lived here. They existed only
   to drive the caption-to-sound chain in displayRound(), which is a table
   from the server now -- see playRoundAudio() and
   dropship/dropship-audio.php. Nothing else ever called them. */

// Configure audio, supporting optional time delays
function configureAudio(source, sound, milliseconds) {
	if (typeof milliseconds !== 'undefined') {
		setTimeout(function() {
			loadAudio(String(source), sound);
		}, milliseconds);
	}else{
		loadAudio(String(source), sound);
	}
}

/*
 * WHICH SOUND A ROUND PLAYS, from a table the server resolved.
 *
 * KEYED ON THE FRAME, NOT THE CAPTION. This was thirty-four if-branches
 * matching caption text, then briefly a list of caption substrings --
 * and neither can tell machine-gun2 from machine-gun3, which share the
 * caption "Machine Gun", nor rocket-launcher2 from 3. The frame a round
 * is showing is a fact about its IMAGE, so that is the key: an exact
 * lookup, with no order to get wrong and nothing able to shadow
 * anything else.
 *
 * See dropship/dropship-audio.php for the table and the naming: the file
 * is the theme's word for the thing plus the frame number, the same
 * convention the art itself uses (demo1/demo2 -> demolition1.mp3 and
 * demolition2.mp3, or paddle1.mp3 and paddle2.mp3 under a theme that
 * calls it a Paddle).
 */
function roundFrame(round) {
	var src = roundImage(round);
	if (!src) return '';
	/* Everything after the theme's images/ folder, without the query or
	   the extension: ".../oculus-lounge/images/weapons/demo2.png?v=1"
	   becomes "weapons/demo2". */
	var i = src.indexOf('/images/');
	if (i === -1) return '';
	var rel = src.slice(i + 8).split('?')[0].split('#')[0];
	var dot = rel.lastIndexOf('.');
	return dot === -1 ? rel : rel.slice(0, dot);
}

function playRoundAudio(round) {
	if (!window.dsAudio) return;
	var frame = roundFrame(round);
	if (frame === '') return;
	var row = dsAudio.frames ? dsAudio.frames[frame] : null;
	if (!row) {
		/* The catch-alls: a surviving round draws one of seven beach
		   frames and there is no moment to name, so they match on the
		   START of the path instead. */
		var any = dsAudio.any || [];
		for (var i = 0; i < any.length; i++) {
			if (any[i].p && frame.indexOf(any[i].p) === 0) { row = any[i]; break; }
		}
	}
	if (!row || !row.f) return;
	if (row.d) { (function (f) { setTimeout(function () { loadFile(2, f); }, row.d); })(row.f); }
	else       { loadFile(2, row.f); }
}

/*
 * Play a file by its path, rather than by a bare stem glued to "sounds/".
 * The path is resolved server-side now -- a theme's own file, or Drop
 * Ship's when it has none -- so the browser is handed the answer.
 */
function loadFile(source, path){
	var el = document.getElementById('audioSource'+source);
	var au = document.getElementById('audio'+source);
	if (!el || !au) return;
	/* filemtime, not rand(0,999): the server sends the buster with the
	   path. A random one collides against a week-long max-age, which is
	   what made dropship.js itself serve stale copies. */
	el.src = path;
	au.load();
	au.play();
}

// Load audio file and autoplay
function loadAudio(source, sound){
	document.getElementById('audioSource'+source).src = "sounds/"+sound+".mp3?var="+randomInt(0,999);
	document.getElementById('audio'+source).load();
	document.getElementById('audio'+source).play();
}

//This JavaScript function always returns a random number between min and max (both included):
function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1) ) + min;
}


/* One painter for both branches: they had the same two lines twice. */
function showRound(n) {
  var cap = roundCaption(n);
  var src = roundImage(n);
  var box = document.getElementById("resultsText");
  if (box && cap !== '') box.innerHTML = "<h3>" + cap + "</h3>";
  var img = document.getElementById("results-image");
  if (img && src !== '') img.style.backgroundImage = "url('" + src + "')";
}

// Cycle through each round and plug the inner HTML into the display box upon button press.
function displayRound(project_id) {
	if(currentRound == 0){
		document.getElementById('audio1').load();
		document.getElementById('audio1').play();
		// Extra function call to try and force music to play
		loadAudio(1, "8bit"+randomInt(1, 5));
		/* ROUND 0 IS A SLIDE LIKE ANY OTHER. Its sound used to be a
		   project_id ladder here (1 alarm, 3 crowd, 4 flyingcar), then
		   briefly a separate "open" key -- but round 0 shows reset.gif
		   and has a caption, so it goes through the same lookup every
		   other round does. */
		playRoundAudio(currentRound);
		//roundEl(currentRound).style.visibility = "visible";
//		document.getElementById("results").innerHTML = roundEl(currentRound).innerHTML;
		showRound(currentRound);
	}else{
		if(currentRound != lastRound){
			var prev = roundEl(currentRound-1);
			if (prev) prev.style.visibility = "hidden";
			//roundEl(currentRound).style.visibility = "visible";
//			document.getElementById("results").innerHTML = roundEl(currentRound).innerHTML;
			showRound(currentRound);
			
			playRoundAudio(currentRound);
		}else{
			if(document.getElementById('disableMessage').innerHTML == "true"){
				window.location.href = 'dashboard.php';
			}else{
				location.reload();
			}
		}
		if(currentRound == lastRound-1){
			//configureAudio(2, "deathcontra");
			configureAudio(1, "gameover");
			/* ALWAYS "Refresh". This button used to read "Send Results to
			   Discord", because its reload was what actually reached the
			   announcement in dashboard.php -- so a player who closed the tab
			   before the last round never posted their run. The announcement
			   fires when the score is logged now, so the button does nothing
			   but return to the results and should not claim otherwise. */
			document.getElementById("viewResults").innerHTML = "Refresh";
		}
	}
	currentRound++;
}

function filterTroops(criteria){
	document.getElementById('filterby').value = criteria;
	document.getElementById("filterTroopsForm").submit();
}

/*
 * THE LOADER, FOR THEME CHANGES AND NOTHING ELSE.
 *
 * It used to be a div on every page that any code could show, and it
 * covered the screen on every navigation and every form post -- which is
 * most of what Drop Ship does -- for work that was usually instant. It is
 * built HERE instead, at the moment of the switch, so it cannot appear
 * anywhere else: no element exists to show.
 *
 * A theme change earns one. It is the request that re-reads the player's
 * holdings, and the only one that routinely takes a second or more.
 *
 * The art is the theme's own -- every theme ships images/loading.gif --
 * and the path comes from the prefix the page already knows, because the
 * reskins nest and Drop Ship's own art is flat.
 */
function dsShowLoader(){
	if (document.getElementById('loading')) return;
	var pre = (document.body && document.body.getAttribute('data-ds-prefix')) || '';
	var wrap = document.createElement('div');
	wrap.id = 'loading';
	var img = document.createElement('img');
	img.id = 'loading-image';
	img.alt = 'Loading...';
	img.src = pre + 'images/loading.gif';
	wrap.appendChild(img);
	document.body.appendChild(wrap);
}

function selectProject(criteria){
	if(criteria == "none"){
		alert("Please select a project from the dropdown.");
		return;
	}
	dsShowLoader();
	document.getElementById('project_id').value = criteria;
	document.getElementById("projectForm").submit();
}

function toggleArmory(pane, tab){
	document.getElementById('inventory').style.display='none';
	document.getElementById('inventory-icon').style.opacity = "50%";
	document.getElementById('inventory-icon').style.margin = "1px";
	document.getElementById('weapons').style.display='none';
	document.getElementById('weapon-icon').style.opacity = "50%";
	document.getElementById('weapon-icon').style.margin = "1px";
	document.getElementById('armor').style.display='none';
	document.getElementById('armor-icon').style.opacity = "50%";
	document.getElementById('armor-icon').style.margin = "1px";
	document.getElementById('equipment').style.display='none';
	document.getElementById('equipment-icon').style.opacity = "50%";
	document.getElementById('equipment-icon').style.margin = "1px";
	pane.style.display = "block";
	tab.style.margin = '0px';
	tab.style.opacity = "100%";
	tab.style.height = "76px";
}

// Get the button
let mybutton = document.getElementById("back-to-top-button");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}

document.getElementById("year").innerHTML = new Date().getFullYear();
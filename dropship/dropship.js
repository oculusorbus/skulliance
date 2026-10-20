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

// Evaluate whether term is in results text
function evaluateAudio(currentRound, terms) {
	/* Reads the SAME caption the display does, so it has to survive the
	   same way: an unreadable round matches nothing rather than throwing
	   and taking the rest of the step with it. */
	return roundCaption(currentRound).includes(terms);
}

// Evaluate current round enumeration
function evaluateRoundAudio(currentRound, round, operator){
	if (typeof operator !== 'undefined') {
		if(currentRound != round){
			return true;
		}else{
			return false;
		}
	}else{
		if(currentRound == round){
			return true;
		}else{
			return false;
		}
	}
}

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
		if(project_id == 1){
			loadAudio(2, "alarm");
		}else if(project_id == 3){
			loadAudio(2, "crowd");
		}else if(project_id == 4){
			loadAudio(2, "flyingcar");
		}
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
			
			if(evaluateAudio(currentRound, "Melee")){
				configureAudio(2, "melee");
			}else if(evaluateAudio(currentRound, "Vibrator")){
				configureAudio(2, "vibrator");
			}else if(evaluateAudio(currentRound, "Tactical Katana")){
				configureAudio(2, "melee", 1000);
			}else if(evaluateAudio(currentRound, "Dildo")){
				configureAudio(2, "melee", 1000);
			}else if(evaluateAudio(currentRound, "Extra Life")){
				configureAudio(2, "extralife");
			}else if(evaluateAudio(currentRound, "Pull Smoke Bomb Pin")){
				configureAudio(2, "grenadepin", 600);
			}else if(evaluateAudio(currentRound, "Pull Out Whip")){
				configureAudio(2, "spankme");
			}else if(evaluateAudio(currentRound, "Smoke Bomb")){
				configureAudio(2, "grenadecontra");
			}else if(evaluateAudio(currentRound, "Whip")){
				configureAudio(2, "spank");
			}else if(evaluateAudio(currentRound, "Pull Grenade")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "grenadepin", 600);
				}
			}else if(evaluateAudio(currentRound, "Throw Grenade")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "grenadecontra");
				}
			}else if(evaluateAudio(currentRound, "Insert Anal Beads")){
				configureAudio(2, "analbeadsinsertion");
			}else if(evaluateAudio(currentRound, "Remove Anal Beads")){
				configureAudio(2, "analbeadsremoval", 1200);
			}else if(evaluateAudio(currentRound, "Ball Gag")){
				configureAudio(2, "ballgag");
			}else if(evaluateAudio(currentRound, "Butt Plug")){
				configureAudio(2, "ballgag");
			}else if(evaluateAudio(currentRound, "Load Machine Gun")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "loading");
				}
			}else if(evaluateAudio(currentRound, "Machine Gun")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "gunfirecontra");
				}
			}else if(evaluateAudio(currentRound, "Flamethrower Ignition")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "flamethrower1");
				}
			}else if(evaluateAudio(currentRound, "Flamethrower Spray")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "flamethrower2");
				}
			}else if(evaluateAudio(currentRound, "Flamethrower Flames")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "flamethrower3");
				}
			}else if(evaluateAudio(currentRound, "Flamethrower Fire")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "flamethrower4");
				}
			}else if(evaluateAudio(currentRound, "Load Rocket Launcher")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "helicopter");
				}
			}else if(evaluateAudio(currentRound, "Rocket Launcher Gunfire")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "gunfirecontra");
				}
			}else if(evaluateAudio(currentRound, "Rocket Launcher")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "rocketlaunchercontra");
				}
			}else if(evaluateAudio(currentRound, "Rocket Launcher Explosion")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "massexplosioncontra");
				}
			}else if(evaluateAudio(currentRound, "Demolition")){
				configureAudio(2, "demo");
			}else if(evaluateAudio(currentRound, "Paddle")){
				configureAudio(2, "spankme");
			}else if(evaluateAudio(currentRound, "Explosion")){
				configureAudio(2, "explosioncontra");
			}else if(evaluateAudio(currentRound, "Spank")){
				configureAudio(2, "spank", 1500);
			}else if(evaluateAudio(currentRound, "Sniper")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "sniper", 1000);
				}
			}else if(evaluateAudio(currentRound, "Pilot")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "loading");
				}
			}else if(evaluateAudio(currentRound, "Air Strike")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "massexplosioncontra", 2000);
				}
			}else if(evaluateAudio(currentRound, "Exo")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "exo", 300);
				}
			}else if(evaluateAudio(currentRound, "Secured")){
				configureAudio(2, "success");
			}else if(evaluateRoundAudio(currentRound, 1)){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "dropshipcontra");
				}else if(project_id == 3){
					configureAudio(2, "door");
				}
			}else if(evaluateRoundAudio(currentRound, 2)){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "gunfirecontra");
				}else if(project_id == 3){
					configureAudio(2, "stairs");
				}
			}else if(evaluateRoundAudio(currentRound, lastRound-1, "!=")){
				if(project_id != 3 && project_id != 4){
					configureAudio(2, "enemyguncontra");
				}
			}
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
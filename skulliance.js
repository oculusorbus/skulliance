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

// Toggle Audio On & Off
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

// Toggle 3D On & Off
function toggle3D(status){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/toggle-3d.php?status='+status, true);
	xhttp.send();
	if(status){
		document.getElementById("results-image").style.backgroundImage = document.getElementById("results-image").style.backgroundImage.replace("png", "gif");
		document.getElementById("hidden-results").innerHTML = replaceAll(document.getElementById("hidden-results").innerHTML, "png", "gif");
	}else{
		document.getElementById("results-image").style.backgroundImage = document.getElementById("results-image").style.backgroundImage.replace("gif", "png");
		document.getElementById("hidden-results").innerHTML = replaceAll(document.getElementById("hidden-results").innerHTML, "gif", "png");
	}
}

// Evaluate whether term is in results text
function evaluateAudio(currentRound, terms) {
	if(document.getElementById(currentRound).getElementsByTagName('h3')[0].innerHTML.includes(terms)){
		return true;
	}else{
		return false;
	}
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
		//document.getElementById(currentRound).style.visibility = "visible";
//		document.getElementById("results").innerHTML = document.getElementById(currentRound).innerHTML;
		document.getElementById("resultsText").innerHTML = "<h3>"+document.getElementById(currentRound).getElementsByTagName('h3')[0].innerHTML+"</h3>";
		document.getElementById("results-image").style.backgroundImage = "url('"+document.getElementById(currentRound).getElementsByTagName('img')[0].src+"')";
	}else{
		if(currentRound != lastRound){
			document.getElementById(currentRound-1).style.visibility = "hidden";
			//document.getElementById(currentRound).style.visibility = "visible";
//			document.getElementById("results").innerHTML = document.getElementById(currentRound).innerHTML;
			document.getElementById("resultsText").innerHTML = "<h3>"+document.getElementById(currentRound).getElementsByTagName('h3')[0].innerHTML+"</h3>";
			document.getElementById("results-image").style.backgroundImage = "url('"+document.getElementById(currentRound).getElementsByTagName('img')[0].src+"')";
			
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
			if(document.getElementById('disableMessage').innerHTML == "true"){
				document.getElementById("viewResults").innerHTML = "Refresh";
			}else{
				document.getElementById("viewResults").innerHTML = "Send Results to Discord";
			}
		}
	}
	currentRound++;
}

function filterNFTs(criteria){
	document.getElementById('filterby').value = criteria;
	document.getElementById("filterNFTsForm").submit();
}

function filterRealms(criteria){
	//document.getElementById('filterByRealms').value = criteria;
	//document.getElementById("filterRealmsForm").submit();
	var xhttp = new XMLHttpRequest();
	group = criteria.parentElement.label;
	criteria = criteria.value;
	
	xhttp.open('GET', 'ajax/select-realms-filter.php?criteria='+criteria+'&group='+group, true);
	
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
			document.getElementById('realms-list').innerHTML = data;
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function filterDiamondSkulls(criteria){
	document.getElementById('filterbydiamond').value = criteria;
	document.getElementById("filterDiamondSkullsForm").submit();
}

function filterPolicies(criteria){
	document.getElementById('filterby').value = criteria;
	document.getElementById("filterPoliciesForm").submit();
}

function filterLeaderboard(criteria){
	document.getElementById('filterby').value = criteria;
	if (criteria === 'activity-ath' || criteria === 'activity-monthly' || criteria === 'activity-weekly') {
		var el = document.getElementById('filtered-content');
		if (el) el.innerHTML = '<p style="text-align:center;padding:40px 0;opacity:.7;">Calculating activity scores…</p>';
	}
	document.getElementById("filterLeaderboardForm").submit();
}

function filterItems(criteria){
	document.getElementById('filterby').value = criteria;
	document.getElementById("filterItemsForm").submit();
}

function selectProject(criteria){
	if(criteria == "none"){
		alert("Please select a project from the dropdown.");
	}
	document.getElementById('loading').style.display = "block";
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

function selectProjectFilter(project_id){
	var xhttp = new XMLHttpRequest();
	var visibility = "";
	
	xhttp.open('GET', 'ajax/select-project-filter.php?project_id='+project_id, true);
	
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function loadTotalMissions(){
	if(window.totalMissionsLoaded) return;
	var container = document.getElementById('total-missions-container');
	if(!container) return;
	window.totalMissionsLoaded = true;
	container.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:40px 20px;">'
		+ '<div style="font-size:2.2rem;animation:lp 1.2s ease-in-out infinite;">&#x1F480;</div>'
		+ '<div style="width:180px;height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;">'
		+   '<div style="height:100%;background:#00c8a0;width:0%;animation:lb 8s ease-out forwards;"></div>'
		+ '</div>'
		+ '<div style="font-size:.75rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase;">Loading</div>'
		+ '</div>';
	$.get('ajax/get-total-missions.php', function(html){
		container.innerHTML = html;
	}).fail(function(){
		container.innerHTML = '<p style="padding:20px;opacity:0.5;">Failed to load stats.</p>';
		window.totalMissionsLoaded = false;
	});
}

function toggleTotalMissions(arrow){
	var xhttp = new XMLHttpRequest();
	var visibility = "";

	if(arrow.id == 'down'){
		arrow.id = 'up';
		arrow.src = 'icons/up.png';
		visibility = 'hide';
		document.getElementById('total-missions-container').style.display = 'none';
	}else{
		arrow.id = 'down';
		arrow.src = 'icons/down.png';
		visibility = 'show';
		document.getElementById('total-missions-container').style.display = 'block';
		loadTotalMissions();
	}

	xhttp.open('GET', 'ajax/toggle-total-missions.php?visibility='+visibility, true);
	xhttp.send();
}

function loadCurrentMissions(){
	if(window.currentMissionsLoaded) return;
	var container = document.getElementById('current-missions-container');
	if(!container) return;
	window.currentMissionsLoaded = true;
	container.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:40px 20px;">'
		+ '<div style="font-size:2.2rem;animation:lp 1.2s ease-in-out infinite;">&#x1F480;</div>'
		+ '<div style="width:180px;height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;">'
		+   '<div style="height:100%;background:#00c8a0;width:0%;animation:lb 8s ease-out forwards;"></div>'
		+ '</div>'
		+ '<div style="font-size:.75rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase;">Loading</div>'
		+ '</div>';
	$.get('ajax/get-current-missions.php', function(html){
		$(container).html(html);
	}).fail(function(){
		container.innerHTML = '<p style="padding:20px;opacity:0.5;">Failed to load missions.</p>';
		window.currentMissionsLoaded = false;
	});
}

function toggleCurrentMissions(arrow){
	var xhttp = new XMLHttpRequest();
	var visibility = "";

	if(arrow.id == 'current-down'){
		arrow.id = 'current-up';
		arrow.src = 'icons/up.png';
		visibility = 'hide';
		document.getElementById('current-missions-container').style.display = 'none';
		window.currentMissionsLoaded = false;
	}else{
		arrow.id = 'current-down';
		arrow.src = 'icons/down.png';
		visibility = 'show';
		document.getElementById('current-missions-container').style.display = 'block';
		loadCurrentMissions();
	}

	xhttp.open('GET', 'ajax/toggle-current-missions.php?visibility='+visibility, true);
	xhttp.send();
}

function toggleRaids(arrow, category, results){
	var xhttp = new XMLHttpRequest();
	var visibility = "";
	
	if(arrow.id == 'down'){
		arrow.id = 'up';
		arrow.src = 'icons/up.png';
		visibility = 'hide';
		document.getElementById(category+'-'+results+'-raids-container').style.display = 'none';
	}else{
		arrow.id = 'down';
		arrow.src = 'icons/down.png';
		visibility = 'show';
		document.getElementById(category+'-'+results+'-raids-container').style.display = 'block';
	}
	
	xhttp.open('GET', 'ajax/toggle-raids.php?visibility='+visibility+'&type='+category+'&status='+results, true);
	
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function toggleTotalRaids(arrow){
	var xhttp = new XMLHttpRequest();
	var visibility = "";
	
	if(arrow.id == 'down'){
		arrow.id = 'up';
		arrow.src = 'icons/up.png';
		visibility = 'hide';
		document.getElementById('total-missions-container').style.display = 'none';
	}else{
		arrow.id = 'down';
		arrow.src = 'icons/down.png';
		visibility = 'show';
		document.getElementById('total-missions-container').style.display = 'block';
	}
	
	xhttp.open('GET', 'ajax/toggle-total-raids.php?visibility='+visibility, true);
	
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function toggleTotalFactions(arrow){
	var xhttp = new XMLHttpRequest();
	var visibility = "";
	
	if(arrow.id == 'down'){
		arrow.id = 'up';
		arrow.src = 'icons/up.png';
		visibility = 'hide';
		document.getElementById('total-factions-container').style.display = 'none';
	}else{
		arrow.id = 'down';
		arrow.src = 'icons/down.png';
		visibility = 'show';
		document.getElementById('total-factions-container').style.display = 'block';
	}
	
	xhttp.open('GET', 'ajax/toggle-total-factions.php?visibility='+visibility, true);
	
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function setSuccessRate(rate) {
	if(document.getElementById('success-rate') !== null){
		document.getElementById('success-rate').innerHTML = rate;
	}
}

function processConsumable(action, consumable_id){
	// Clear NFTs and Success Rate items to accommodate 100% item
	if(consumable_id == 1 && action == 'Select'){
		clearSuccessRate();
	}
	current_rate = parseInt(document.getElementById('success-rate').innerHTML);
	calculated_rate = -1;
	var consumables = [];
	consumables[1] = 100;
	consumables[2] = 75;
	consumables[3] = 50;
	consumables[4] = 25;
	if(consumable_id == 1 || consumable_id == 2 || consumable_id == 3 || consumable_id == 4){
		if(action == 'Select'){
			calculated_rate = current_rate+consumables[consumable_id];
		}else if(action == 'Remove'){
			calculated_rate = current_rate-consumables[consumable_id];
		}
	}
	if(calculated_rate <= 100){
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/process-mission-consumable.php?action='+action+'&consumable_id='+consumable_id, true);
		xhttp.send();
	
		xhttp.onreadystatechange = function() {
		  if (xhttp.readyState == XMLHttpRequest.DONE) {
		    // Check the status of the response
		    if (xhttp.status == 200) {	
				if(action == 'Select'){
					document.getElementById('consumable-'+consumable_id).value = 'Remove';
					document.getElementById('amount-'+consumable_id).innerHTML = document.getElementById('amount-'+consumable_id).innerHTML-1;
					document.getElementById('consumable-'+consumable_id).classList.add("activated");
					if(calculated_rate != -1){
						setSuccessRate(calculated_rate);
					}
				}else if(action == 'Remove'){
					document.getElementById('consumable-'+consumable_id).value = 'Select';
					document.getElementById('amount-'+consumable_id).innerHTML = parseFloat(document.getElementById('amount-'+consumable_id).innerHTML)+1;
					document.getElementById('consumable-'+consumable_id).classList.remove("activated");
					if(calculated_rate != -1){
						setSuccessRate(calculated_rate);
					}
				}
				// Access the data returned by the server
				var data = xhttp.responseText;
				/*
				const obj = JSON.parse(data);
				if(obj == null){
  
				}else{
  
				}*/
				console.log(data);
				// Do something with the data
		    } else {
		      // Handle error
				alert("AJAX Error");
		    }
		  }
		};
	}else{
		alert("Success Rate cannot go above 100%.\r\n\r\nRemove NFTs from your inventory to free up room to use success rate consumables.");
	}
}

function processMissionNFT(action, nft_id, rate){
	current_rate = parseInt(document.getElementById('success-rate').innerHTML);
	if(action == 'Select'){
		calculated_rate = current_rate+rate;
	}else if(action == 'Remove'){
		calculated_rate = current_rate-rate;
	}
	if(calculated_rate <= 100){
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/process-mission-nft.php?action='+action+'&nft_id='+nft_id+'&rate='+rate, true);
		xhttp.send();
	
		xhttp.onreadystatechange = function() {
		  if (xhttp.readyState == XMLHttpRequest.DONE) {
		    // Check the status of the response
		    if (xhttp.status == 200) {	
				if(action == 'Select'){
					document.getElementById('button-'+nft_id).value = 'Remove';
					document.getElementById('button-'+nft_id).classList.add("activated");
					setSuccessRate(calculated_rate);
				}else if(action == 'Remove'){
					document.getElementById('button-'+nft_id).value = 'Select';
					document.getElementById('button-'+nft_id).classList.remove("activated");
					setSuccessRate(calculated_rate);
				}
				// Access the data returned by the server
				var data = xhttp.responseText;
				/*
				const obj = JSON.parse(data);
				if(obj == null){
  
				}else{
  
				}*/
				console.log(data);
				// Do something with the data
		    } else {
		      // Handle error
				alert("AJAX Error");
		    }
		  }
		};
	}else{
		alert("Success Rate cannot go above 100%.\r\n\r\nRemove Success Rate Items or NFTs to free up space for your NFT selection.");
	}
}

function startMission() {
	var success_rate = document.getElementById('success-rate').innerHTML;
	if(success_rate != 0){
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/start-mission.php', true);
		xhttp.send();

		xhttp.onreadystatechange = function() {
		  if (xhttp.readyState == XMLHttpRequest.DONE) {
		    // Check the status of the response
		    if (xhttp.status == 200) {
				// Access the data returned by the server
				var data = xhttp.responseText;
				/*
				const obj = JSON.parse(data);
				if(obj == null){

				}else{

				}*/
				if(data != ""){
					alert(data);
				}else{
					window.location.href = "missions.php";
				}
				console.log(data);
				// Do something with the data
		    } else {
		      // Handle error
				alert("AJAX Error");
		    }
		  }
		};
	}else{
		alert('Success Rate cannot be at 0%.\r\n\r\nRefresh the webpage to reload default NFT selections.');
	}
}

function completeMission(mission_id, quest_id) {
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/complete-mission.php?mission_id='+mission_id+"&quest_id="+quest_id, true);
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
			/*
			const obj = JSON.parse(data);
			if(obj == null){

			}else{

			}*/
			//document.getElementById('claim-button-'+mission_id).style.display = "none";
			alert(data);
			window.location.href = "missions.php";
			console.log(data);
			// Do something with the data
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function completeMissions(mission_ids, quest_ids) {	
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/complete-missions.php?mission_id='+mission_ids+"&quest_id="+quest_ids, true);
	xhttp.send();

	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
			// Access the data returned by the server
			var data = xhttp.responseText;
			/*
			const obj = JSON.parse(data);
			if(obj == null){

			}else{

			}*/
			// Since missions are being claimed, automatically display start missions buttons.
			var freeForm = document.getElementById('startFreeMissionsForm');
			var autoForm = document.getElementById('startAutoMissionsForm');
			if (freeForm) freeForm.style.display = "block";
			if (autoForm) autoForm.style.display = "block";
			const obj = JSON.parse(data);
			//document.getElementById('consumable-header').style.display = 'block';
			for(var i in obj){
			  document.getElementById('mission-result-'+i).innerHTML = "<strong>"+obj[i].status+"</strong>";
			  document.getElementById('currency-'+i).innerHTML = obj[i].currency;
			  //document.getElementById('consumable-'+i).style.display = 'block';
			  if(obj[i].status == "FAILURE"){
				var contents = document.getElementById('mission-reward-'+i).innerHTML;
				var withNoDigits = contents.replace(/[0-9]/g, '');
			  	document.getElementById('mission-reward-'+i).innerHTML = "0"+withNoDigits;
				document.getElementById('mission-row-'+i).classList.add("failure");
				document.getElementById('consumable-'+i).innerHTML = "<img class='icon consumable' src='icons/nothing.png'/>";
			  }else{
			  	document.getElementById('mission-row-'+i).classList.add("success");
				document.getElementById('consumable-'+i).innerHTML = "<img class='icon consumable' src='icons/"+obj[i].consumable+".png'/>";
			  }
			}
			
			/*
			var missionIDs = new Array();
			missionIDs = mission_ids.split(",");
			var arrayLength = missionIDs.length;
			for (var i = 0; i < arrayLength; i++) {
			    document.getElementById('mission-row-'+missionIDs[i]).style.display = "none";
			}
			document.getElementById('mission-results').innerHTML = data;*/
			
			
			//window.location.href = "missions.php";
			//alert(data);
			console.log(data);
			// Do something with the data
	    } else {
	      // Handle error
			alert("AJAX Error");
	    }
	  }
	};
}

function retreat(mission_id, quest_id){
	openConfirm("Are you sure you want to retreat?\r\n\r\nThe cost of your mission will be refunded.\r\n\r\nAll items used will be restored.", function() {
		// Immediately hide button upon confirmation to prevent double retreats
		document.getElementById("retreat-button-"+mission_id).style.display = "none";
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/retreat-mission.php?mission_id='+mission_id+"&quest_id="+quest_id, true);
		xhttp.send();

		xhttp.onreadystatechange = function() {
		  if (xhttp.readyState == XMLHttpRequest.DONE) {
		    if (xhttp.status == 200) {
				openNotify(xhttp.responseText);
				window.currentMissionsLoaded = false;
				loadCurrentMissions();
		    } else {
				openNotify("AJAX Error");
		    }
		  }
		};
	});
}

function getQuests(projectID){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/get-quests.php?project_id='+projectID, true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
	      var data = xhttp.responseText;
		  if(data != ""){
		  	document.getElementById('quests').outerHTML = data;
		  }
		  console.log(data);
	      // Do something with the data
	    } else {
	      // Handle error
	    }
	  }
	};
}


function dailyReward(){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/daily-reward.php?status=true', true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
		  const consumables = [];
		  consumables[1] = "random-reward";
		  consumables[2] = "25-success";
		  consumables[3] = "fast-forward";
		  consumables[4] = "50-success";
		  consumables[5] = "75-success";
		  consumables[6] = "double-rewards";
		  consumables[7] = "100-success";
	      var data = xhttp.responseText;
		  const obj = JSON.parse(data);
		  if(obj == null){
		  	document.getElementById('reward').innerHTML = "Daily Reward Already Claimed";
		  }else{
		    document.getElementById('reward').style.opacity = 1;
		  	document.getElementById('reward').innerHTML = "<span style='display:flex;align-items:center;gap:8px;flex-wrap:nowrap;white-space:nowrap;font-size:0.9rem;'><strong>Day "+obj.day+":</strong><img class='icon' style='margin-right:0;' src='icons/"+consumables[obj.day]+".png'/><span>+</span><img class='icon' style='margin-right:0;' src='icons/"+obj.currency.toLowerCase()+".png'/><span>+"+obj.amount+"&nbsp;"+obj.currency.replace(/[0-9]+/g,'')+"</span></span>";
			document.getElementById('claimed').style.display = "flex";
			document.getElementById('progress_bar').style.display = "flex";
			document.getElementById('progress_bar').innerHTML = obj.progress_bar;
			document.getElementById('remaining').style.display = "flex";
			document.getElementById('remaining').innerHTML = obj.remaining;
	  	  }
		  document.getElementById('claimRewardButton').style.display = "none";
		  console.log(data);
	      // Do something with the data
	    } else {
	      // Handle error
	    }
	  }
	};
}

function checkDiscordStatus(){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/check-discord-status.php', true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
	      var data = xhttp.responseText;
		  if(data == "true"){
			  document.getElementById("checkDiscordSection").style.display = 'none';
			  document.getElementById("claimRewardButton").style.display = 'block';
		  }else{
			  buttonValue = document.getElementById("checkDiscordStatusButton").value;
			  if(buttonValue == 'Check Status'){
			  	document.getElementById("checkDiscordStatusButton").value = 'Check Again';
		  	  }
			  if(buttonValue == 'Check Again'){
			  	document.getElementById("checkDiscordStatusButton").value = 'Check Status';
		  	  }
		  }
		  console.log(data);
	      // Do something with the data
	    } else {
	      // Handle error
	    }
	  }
	};
}

function upgradeRealmLocation(upgradeButton, realmID, locationID, duration, cost, projectID){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/upgrade-realm-location.php?realm_id='+realmID+'&location_id='+locationID+'&duration='+duration+'&cost='+cost+'&project_id='+projectID, true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
	      var data = xhttp.responseText;
		  if(data != ""){
		  	upgradeButton.outerHTML = data;
			// Destroy partner points option section upon successful DB update
			document.getElementById("points-section-"+locationID).outerHTML = "";
			togglePointsButtons('enable');
		  }
		  console.log(data);
	      // Do something with the data
	    } else {
	      // Handle error
	    }
	  }
	};
}

function upgradeRealmLocationPoints(upgradePointsButton, realmID, locationID, duration, cost){
	projectID = document.getElementById('points-'+locationID).value;
	
	// Check if button is present, if not retrieve message
	upgradeButton = document.getElementById('upgrade-button-'+locationID);
	if (typeof(upgradeButton) != 'undefined' && upgradeButton != null){
		element = upgradeButton;
	}else{
		element = document.getElementById('upgrade-message-'+locationID);
	}
	
	if(projectID != 0){
		upgradeRealmLocation(element, realmID, locationID, duration, cost, projectID);
	}else{
		alert('Please select a points balance to deduct from.');
	}
}

function pointsOption(pointsOptionButton, realmID, locationID, duration, cost){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/points-option.php?realm_id='+realmID+'&location_id='+locationID+'&duration='+duration+'&cost='+cost, true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
	      var data = xhttp.responseText;
		  if(data != ""){
		  	pointsOptionButton.outerHTML = data;
		  }
		  console.log(data);
	      // Do something with the data
	    } else {
	      // Handle error
	    }
	  }
	};
}

function togglePointsButtons(status){
	for (let i = 1; i <= 7; i++) {
		pointsButton = document.getElementById('points-button-'+i);
		if (typeof(pointsButton) != 'undefined' && pointsButton != null){
			if(status == "disable"){
				pointsButton.disabled = true;
				pointsButton.style.display = "none";
			}else if(status == "enable"){
				pointsButton.removeAttribute("disabled");
				pointsButton.style.display = "block";
			}
		}
	}
}

// Global state for raid consumables modal
var _raidModalDefenseId = null;
var _raidModalDuration  = null;

function _getModalSelectedCids(){
	var cids = [];
	document.querySelectorAll('#raid-con-modal-items .raid-con-check:checked').forEach(function(ch){
		cids.push(parseInt(ch.getAttribute('data-id')));
	});
	return cids;
}

function _getRaidSelectedCids(realmId){
	var allEl = document.getElementById('raid-all-items-'+realmId);
	if(!allEl || !allEl.checked) return _getModalSelectedCids();
	if(allEl.dataset.mode === 'saved'){
		// Saved Items: use saved config filtered by current inventory
		var savedIds = allEl.dataset.savedIds ? allEl.dataset.savedIds.split(',').map(Number).filter(Boolean) : [];
		return savedIds.filter(function(cid){
			var qtyEl = document.getElementById('inv-qty-'+cid);
			return qtyEl && parseInt(qtyEl.textContent) > 0;
		});
	}
	// All Items: use everything with qty > 0
	var cids = [];
	for(var cid = 1; cid <= 7; cid++){
		var qtyEl = document.getElementById('inv-qty-'+cid);
		if(qtyEl && parseInt(qtyEl.textContent) > 0) cids.push(cid);
	}
	return cids;
}

function _updateRaidStats(realmId, selectedCids){
	var sEl = document.getElementById('raid-success-'+realmId);
	var dEl = document.getElementById('raid-duration-'+realmId);
	if(!sEl || !dEl) return;
	var offensePct   = parseFloat(sEl.dataset.offensePct);
	var defBoost     = parseFloat(sEl.dataset.defenderBoost);
	var attLocBoost  = parseFloat(sEl.dataset.attackerLocBoost);
	var baseDuration = parseInt(dEl.dataset.baseDuration);
	var boostMap = {1:4, 2:3, 3:2, 4:1};
	var raidBoost = 0, hasFF = false;
	selectedCids.forEach(function(cid){
		raidBoost += boostMap[cid] || 0;
		if(cid == 5) hasFF = true;
	});
	raidBoost = Math.min(10, raidBoost);
	var adjWin = offensePct - defBoost + attLocBoost + raidBoost;
	adjWin = Math.max(1, Math.min(99, adjWin));
	console.log('[raidStats] realm='+realmId+' cids='+JSON.stringify(selectedCids)+' offensePct='+offensePct+' defBoost='+defBoost+' attLocBoost='+attLocBoost+' raidBoost='+raidBoost+' adjWin='+adjWin);
	sEl.textContent = Math.round(adjWin) + '%';
	var dur = hasFF ? Math.ceil(baseDuration / 2) : baseDuration;
	dEl.textContent = dur + ' ' + (dur === 1 ? 'day' : 'days');
}

function startRaid(raidButton, defenseID, duration){
	var allAvailableEl = document.getElementById('raid-all-items-'+defenseID);
	var consumablesParam = '';
	// All Items mode (checked, no saved config) — use all, never saves session
	if(allAvailableEl && allAvailableEl.checked && allAvailableEl.dataset.mode === 'all'){
		consumablesParam = '&consumables=all';
	} else {
		// Saved Items or unchecked — use specific cids, never saves session (modal-only saves)
		var selectedCids = _getRaidSelectedCids(defenseID);
		consumablesParam = selectedCids.map(function(cid){ return '&consumables[]=' + cid; }).join('');
	}
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/start_raid.php?defense_id='+defenseID+'&duration='+duration+consumablesParam, true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    if (xhttp.status == 200) {
	      var data = xhttp.responseText;
		  var conRow = document.getElementById('raid-con-row-'+defenseID);
		  if(conRow) conRow.style.display = 'none';
		  if(data != ""){
		  	raidButton.outerHTML = data;
		  }
		  console.log(data);
	    }
	  }
	};
}

var _defaultConIds = [2, 3, 4, 5, 7]; // all except 100% Success (1) and Double Rewards (6)

function updateRaidAllLabel(checkbox, realmId){
	var checks = document.querySelectorAll('#raid-con-modal-items .raid-con-check:not(:disabled)');
	if(!checkbox.checked){
		checks.forEach(function(ch){ ch.checked = false; });
	} else if(checkbox.dataset.mode === 'saved'){
		var savedIds = checkbox.dataset.savedIds ? checkbox.dataset.savedIds.split(',').map(Number).filter(Boolean) : [];
		checks.forEach(function(ch){ ch.checked = savedIds.indexOf(parseInt(ch.getAttribute('data-id'))) !== -1; });
	} else {
		checks.forEach(function(ch){ ch.checked = _defaultConIds.indexOf(parseInt(ch.getAttribute('data-id'))) !== -1; });
	}
	updateRaidModalSummary();
	_updateRaidStats(realmId, _getRaidSelectedCids(realmId));
}

function openRaidConsumablesModal(realmId, duration){
	_raidModalDefenseId = realmId;
	_raidModalDuration  = duration;
	var modal    = document.getElementById('raid-consumables-modal');
	var itemsEl  = document.getElementById('raid-con-modal-items');
	var sumEl    = document.getElementById('raid-con-modal-summary');
	itemsEl.innerHTML = '<p style="opacity:0.5">Loading...</p>';
	sumEl.innerHTML   = '';
	modal.style.display = 'block';
	document.getElementById('raid-consumables-overlay').style.display = 'block';
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/get-raid-consumables.php', true);
	xhttp.send();
	xhttp.onreadystatechange = function(){
		if(xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200){
			try{ _renderRaidConsumablesModal(JSON.parse(xhttp.responseText).consumables); }
			catch(e){ itemsEl.innerHTML = '<p style="color:#f55">Error loading consumables.</p>'; }
		}
	};
}

function _renderRaidConsumablesModal(consumables){
	var itemsEl = document.getElementById('raid-con-modal-items');
	var html = '';
	consumables.forEach(function(c){
		var has = c.qty > 0;
		html += '<div class="raid-con-item'+(has?'':' unavailable')+'">';
		html += '<label>';
		var allEl = document.getElementById('raid-all-items-'+_raidModalDefenseId);
		var allMode = allEl ? (allEl.dataset.mode || 'default') : 'default';
		var savedIds = (allEl && allEl.dataset.savedIds) ? allEl.dataset.savedIds.split(',').map(Number).filter(Boolean) : [];
		var itemChecked = has && (allMode === 'saved' ? savedIds.indexOf(c.id) !== -1 : _defaultConIds.indexOf(c.id) !== -1);
		html += '<input type="checkbox" class="raid-con-check" data-id="'+c.id+'" data-boost="'+_consumableBoost(c.id)+'"'+(has?(itemChecked?' checked':''):' disabled')+'>';
		html += '<img class="icon consumable" src="icons/'+c.icon+'" onerror="this.src=\'icons/skull.png\'" title="'+c.name+'"/>';
		html += '<span class="raid-con-name">'+c.name+'</span>';
		html += '</label>';
		html += '<span class="raid-con-qty">'+(has?'x'+c.qty:'None')+'</span>';
		html += '<p class="raid-con-desc">'+c.desc+'</p>';
		html += '</div>';
	});
	itemsEl.innerHTML = html;
	itemsEl.querySelectorAll('.raid-con-check').forEach(function(ch){
		ch.addEventListener('change', updateRaidModalSummary);
	});
	updateRaidModalSummary();
}

function _consumableBoost(cid){
	return {1:4, 2:3, 3:2, 4:1}[cid] || 0;
}

function updateRaidModalSummary(){
	var sumEl = document.getElementById('raid-con-modal-summary');
	var checks = document.querySelectorAll('#raid-con-modal-items .raid-con-check:checked');
	var totalBoost = 0, hasFF = false, hasDR = false, hasRR = false;
	checks.forEach(function(ch){
		var cid = parseInt(ch.getAttribute('data-id'));
		totalBoost += parseInt(ch.getAttribute('data-boost') || 0);
		if(cid == 5) hasFF = true;
		if(cid == 6) hasDR = true;
		if(cid == 7) hasRR = true;
	});
	var parts = [];
	var boostCapped = Math.min(10, totalBoost);
	if(boostCapped > 0) parts.push('+'+boostCapped+'% Success Rate');
	if(hasFF) parts.push('Duration Halved');
	if(hasDR) parts.push('Loot Cap 1000');
	if(hasRR) parts.push('+1 Random Project Loot');
	sumEl.innerHTML = parts.length > 0 ? '<strong>Bonuses:</strong> '+parts.join(' &bull; ') : 'No consumables selected.';
	if(_raidModalDefenseId) _updateRaidStats(_raidModalDefenseId, _getModalSelectedCids());
}

function closeRaidConsumablesModal(){
	document.getElementById('raid-consumables-modal').style.display = 'none';
	document.getElementById('raid-consumables-overlay').style.display = 'none';
	_raidModalDefenseId = null;
	_raidModalDuration  = null;
}

function _updateAllRaidConfigCheckboxes(savedIds){
	var savedIdsStr = savedIds.join(',');
	var hasSaved = savedIds.length > 0;
	var label = hasSaved ? 'Saved Config' : 'Default Config';
	document.querySelectorAll('[id^="raid-all-items-"]').forEach(function(el){
		el.dataset.mode     = hasSaved ? 'saved' : 'default';
		el.dataset.savedIds = savedIdsStr;
		el.textContent      = label;
	});
}

function startRaidFromModal(){
	var defenseID = _raidModalDefenseId;
	var duration  = _raidModalDuration;
	if(!defenseID) return;
	var checks = document.querySelectorAll('#raid-con-modal-items .raid-con-check:checked');
	var consumablesParam = '';
	var savedCids = [];
	checks.forEach(function(ch){
		consumablesParam += '&consumables[]='+ch.getAttribute('data-id');
		savedCids.push(parseInt(ch.getAttribute('data-id')));
	});
	var saveEl = document.getElementById('raid-con-save-config');
	var saveParam = (!saveEl || saveEl.checked) ? '&save_config=1' : '';
	if(saveParam) _updateAllRaidConfigCheckboxes(savedCids);
	closeRaidConsumablesModal();
	var raidButton = document.getElementById('raid-btn-'+defenseID);
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/start_raid.php?defense_id='+defenseID+'&duration='+duration+consumablesParam+saveParam, true);
	xhttp.send();
	xhttp.onreadystatechange = function(){
		if(xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200){
			var data = xhttp.responseText;
			var conRow = document.getElementById('raid-con-row-'+defenseID);
			if(conRow) conRow.style.display = 'none';
			if(data != '' && raidButton) raidButton.outerHTML = data;
			console.log(data);
		}
	};
}

function openGuideModal(){
	var m = document.getElementById('guide-modal');
	if(m) m.style.display = 'block';
	var o = document.getElementById('guide-modal-overlay');
	if(o) o.style.display = 'block';
}

function closeGuideModal(){
	var m = document.getElementById('guide-modal');
	if(m) m.style.display = 'none';
	var o = document.getElementById('guide-modal-overlay');
	if(o) o.style.display = 'none';
}

function openInventoryInfoModal(){
	var m = document.getElementById('inventory-info-modal');
	if(m) m.style.display = 'block';
	var o = document.getElementById('inventory-info-overlay');
	if(o) o.style.display = 'block';
}

function closeInventoryInfoModal(){
	var m = document.getElementById('inventory-info-modal');
	if(m) m.style.display = 'none';
	var o = document.getElementById('inventory-info-overlay');
	if(o) o.style.display = 'none';
}

function applyLocationConsumable(locationId, consumableId){
	_locConAjax('ajax/apply-location-consumable.php?location_id='+locationId+'&consumable_id='+consumableId);
}

function removeLocationConsumable(locationId, consumableId){
	_locConAjax('ajax/remove-location-consumable.php?location_id='+locationId+'&consumable_id='+consumableId);
}

function stockLocation(locationId){
	_locConAjax('ajax/apply-location-consumable.php?location_id='+locationId+'&consumable_id=all');
}

function stockAllLocations(){
	_locConAjax('ajax/apply-location-consumable.php?location_id=all&consumable_id=all');
}

function unstockLocation(locationId){
	_locConAjax('ajax/remove-location-consumable.php?location_id='+locationId+'&consumable_id=all');
}

function unstockAllLocations(){
	_locConAjax('ajax/remove-location-consumable.php?location_id=all&consumable_id=all');
}

function _checkStockButtonStates(){
	var globalAnyAvailable = false;
	var globalAnyEquipped  = false;
	for(var lid = 1; lid <= 7; lid++){
		var hasAvailable = false, hasEquipped = false;
		for(var cid = 1; cid <= 7; cid++){
			var slot = document.getElementById('loc-con-'+lid+'-'+cid);
			if(!slot) continue;
			if(slot.classList.contains('available')) hasAvailable = true;
			if(slot.classList.contains('equipped'))  hasEquipped  = true;
		}
		if(hasAvailable) globalAnyAvailable = true;
		if(hasEquipped)  globalAnyEquipped  = true;
		var btn = document.getElementById('stock-btn-'+lid);
		if(!btn) continue;
		if(!hasAvailable && hasEquipped){
			btn.textContent = 'Unstock Location';
			btn.onclick = (function(l){ return function(){ unstockLocation(l); }; })(lid);
		} else {
			btn.textContent = 'Stock Location';
			btn.onclick = (function(l){ return function(){ stockLocation(l); }; })(lid);
		}
	}
	var allBtn = document.getElementById('stock-all-btn');
	if(!allBtn) return;
	if(!globalAnyAvailable && globalAnyEquipped){
		allBtn.textContent = 'Unstock All Locations';
		allBtn.onclick = unstockAllLocations;
	} else {
		allBtn.textContent = 'Stock All Locations';
		allBtn.onclick = stockAllLocations;
	}
}

function _locConAjax(url){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', url, true);
	xhttp.send();
	xhttp.onreadystatechange = function(){
		if(xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200){
			try{
				var resp = JSON.parse(xhttp.responseText);
				if(resp.error){ console.log(resp.error); return; }
				updateInventoryStrip(resp.inventory);
				if(resp.equipped) _syncLocConsumableSlots(resp.equipped);
				if(resp.upgrades) _updateUpgradeDisplays(resp.upgrades);
			} catch(e){ console.log('Consumable AJAX error', e); }
		}
	};
}

function updateInventoryStrip(inventory){
	for(var cid in inventory){
		var qty = parseInt(inventory[cid]);
		var qtyEl = document.getElementById('inv-qty-'+cid);
		if(qtyEl) qtyEl.textContent = qty;
		var slotEl = document.getElementById('inv-slot-'+cid);
		if(slotEl){
			slotEl.classList.remove('available','unavailable');
			slotEl.classList.add(qty > 0 ? 'available' : 'unavailable');
		}
	}
}

function _syncLocConsumableSlots(equippedMap){
	for(var lid = 1; lid <= 7; lid++){
		for(var cid = 1; cid <= 7; cid++){
			var slotEl = document.getElementById('loc-con-'+lid+'-'+cid);
			if(!slotEl) continue;
			var isEquipped = equippedMap[lid] && equippedMap[lid][cid];
			var invQtyEl = document.getElementById('inv-qty-'+cid);
			var qty = invQtyEl ? parseInt(invQtyEl.textContent) : 0;
			slotEl.classList.remove('equipped','available','unavailable');
			var badge = slotEl.querySelector('.loc-con-badge');
			if(isEquipped){
				slotEl.classList.add('equipped');
				slotEl.setAttribute('onclick','removeLocationConsumable('+lid+','+cid+')');
				if(badge){ badge.classList.add('equipped'); badge.textContent = '\u2713'; }
				else{ var nb=document.createElement('span'); nb.className='loc-con-badge equipped'; nb.textContent='\u2713'; slotEl.appendChild(nb); }
			} else if(qty > 0){
				slotEl.classList.add('available');
				slotEl.setAttribute('onclick','applyLocationConsumable('+lid+','+cid+')');
				if(badge){ badge.classList.remove('equipped'); badge.textContent = qty; }
				else{ var nb2=document.createElement('span'); nb2.className='loc-con-badge'; nb2.textContent=qty; slotEl.appendChild(nb2); }
			} else {
				slotEl.classList.add('unavailable');
				slotEl.setAttribute('onclick','');
				if(badge) badge.remove();
			}
		}
		_updateLocationStatusLabels(lid, equippedMap[lid]);
	}
	_checkStockButtonStates();
}

function _updateLocationStatusLabels(lid, equippedRow){
	var el = document.getElementById('loc-status-'+lid);
	if(!el) return;
	equippedRow = equippedRow || {};
	var boostMap = {1:4, 2:3, 3:2, 4:1};
	var boost = 0;
	for(var cid in boostMap){ if(equippedRow[cid]) boost += boostMap[cid]; }
	boost = Math.min(10, boost);
	var tags = [];
	if(boost > 0)      tags.push('+'+boost+'% Success');
	if(equippedRow[5]) tags.push('Fast Forward');
	if(equippedRow[6]) tags.push('Shield');
	if(equippedRow[7]) tags.push('Random Reward');
	el.innerHTML = tags.map(function(t){ return '<span class="loc-status-tag">'+t+'</span>'; }).join('');
}

function _updateUpgradeDisplays(upgrades){
	for(var lid in upgrades){
		var el = document.getElementById('loc-upgrade-'+lid);
		if(!el) continue;
		var upg = upgrades[lid];
		var dur = upg.duration;
		var rem = upg.remaining_seconds;
		if(rem <= 0){ el.innerHTML = ''; continue; }
		var d = Math.floor(rem / 86400);
		var h = Math.floor((rem % 86400) / 3600);
		var m = Math.floor((rem % 3600) / 60);
		el.innerHTML = "<div class='location-meta' style='font-weight:normal;text-align:right;'>Lv"+dur+" upgrade &bull; "+dur+" "+(dur===1?"day":"days")+"<br>"+d+"d "+h+"h "+m+"m left</div>";
	}
}

function editRealmName(editIcon){
	editIcon.style.display = "none";
	realmName = document.getElementById('realmName').innerHTML;
	document.getElementById('realmName').innerHTML = "<form id='updateRealmName' action='realms.php#realm-name' method='post' style='max-height:0px'><input type='text' id='realmText' name='realmText' size='30' required>&nbsp;<input class='small-button' type='submit' value='Update'></form>";
	document.getElementById('realmText').value = realmName;
	document.getElementById('realmText').focus();
}

function retreatRaid(raidId){
	openConfirm("Are you sure you want to retreat this raid?\r\n\r\nAll consumables used will be returned to your inventory.", function(){
		var btn = document.querySelector('#raid-row-'+raidId+' input[type=button]');
		if(btn) btn.disabled = true;
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/retreat-raid.php?raid_id='+raidId, true);
		xhttp.send();
		xhttp.onreadystatechange = function(){
			if(xhttp.readyState == XMLHttpRequest.DONE){
				try{
					var resp = JSON.parse(xhttp.responseText);
					if(resp.error){ alert(resp.error); if(btn) btn.disabled = false; return; }
					var row = document.getElementById('raid-row-'+raidId);
					var progress = document.getElementById('raid-progress-'+raidId);
					if(row) row.remove();
					if(progress) progress.remove();
				} catch(e){ alert('Error retreating raid'); if(btn) btn.disabled = false; }
			}
		};
	});
}

function deactivateRealm(realmID){
	openConfirm("Are you sure you want to deactivate your realm?\r\n\r\nYou will not be able to reactivate it until after 30 days have passed.\r\n\r\nDeactivating your realm prevents other realms from raiding you, damaging your locations, and looting your points.", function() {
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/toggle-realm-state.php?type=deactivate&realm_id='+realmID, true);
		xhttp.send();
		xhttp.onreadystatechange = function() {
		  if (xhttp.readyState == XMLHttpRequest.DONE) {
		    if (xhttp.status == 200) {
		      var data = xhttp.responseText;
			  alert(data);
			  window.location.href = "realms.php";
		    }
		  }
		};
	});
}

function reactivateRealm(realmID){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/toggle-realm-state.php?type=reactivate&realm_id='+realmID, true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
	      var data = xhttp.responseText;
	      // Do something with the data
		  window.location.href = "realms.php";
	    } else {
	      // Handle error
	    }
	  }
	};
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

var revealObserver = new IntersectionObserver(function(entries) {
	entries.forEach(function(entry) {
		if (entry.isIntersecting) {
			entry.target.classList.add("active");
			revealObserver.unobserve(entry.target);
		}
	});
}, { rootMargin: "0px 0px -150px 0px" });

document.querySelectorAll(".reveal").forEach(function(el) {
	revealObserver.observe(el);
});


// Modal
// Get the modal
var modal = document.getElementById("myModal");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks on <span> (x), close the modal
if (typeof span !== 'undefined') {
	span.onclick = function() {
	  if (modal) modal.style.display = "none";
	}
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (modal && event.target == modal) {
    modal.style.display = "none";
  }
  var raidModal = document.getElementById('raid-consumables-modal');
  if (raidModal && event.target == raidModal) {
    closeRaidConsumablesModal();
  }
  var infoModal = document.getElementById('inventory-info-modal');
  if (infoModal && event.target == infoModal) {
    closeInventoryInfoModal();
  }
};

// Daily reward countdown — no days, just Xh Xm Xs
(function() {
	function tickDaily() {
		var now = Math.floor(Date.now() / 1000);
		document.querySelectorAll('.daily-reward-countdown[data-deadline]').forEach(function(el) {
			var rem = Math.max(0, parseInt(el.getAttribute('data-deadline'), 10) - now);
			var h = Math.floor(rem / 3600);
			var m = Math.floor((rem % 3600) / 60);
			var s = rem % 60;
			el.textContent = h + 'h ' + m + 'm ' + s + 's';
		});
	}
	setInterval(tickDaily, 1000);
	tickDaily();
}());

// Real-time countdown ticker — updates all .countdown[data-deadline] spans every second
(function() {
  function tick() {
    var now = Math.floor(Date.now() / 1000);
    document.querySelectorAll('.countdown[data-deadline]').forEach(function(el) {
      var deadline = parseInt(el.getAttribute('data-deadline'), 10);
      var rem = deadline - now;
      if (rem <= 0) {
        el.textContent = '0d 0h 0m 0s';
        el.removeAttribute('data-deadline');
        return;
      }
      var d = Math.floor(rem / 86400);
      var h = Math.floor((rem % 86400) / 3600);
      var m = Math.floor((rem % 3600) / 60);
      var s = rem % 60;
      el.textContent = d + 'd ' + h + 'h ' + m + 'm ' + s + 's';
    });
  }
  setInterval(tick, 1000);
  tick();
}());

// Pin user's own rank row to the top of each leaderboard list (only if not in top 3)
;(function() {
  document.querySelectorAll('.lb-list').forEach(function(list) {
    var highlighted = list.querySelector('.lb-row.lb-highlight');
    if (!highlighted) return;
    // Top-3 rows have a trophy icon — already visible, don't pin
    if (highlighted.querySelector('.lb-trophy')) return;
    var pinned = highlighted.cloneNode(true);
    pinned.style.cssText = 'background:rgba(0,200,160,0.09);outline:1px solid rgba(0,200,160,0.35);';
    var rankNum = pinned.querySelector('.lb-rank-num');
    if (rankNum) {
      rankNum.insertAdjacentHTML('beforebegin', '<span style="font-size:9px;opacity:0.55;display:block;letter-spacing:0.5px;text-align:center">YOU</span>');
    }
    list.insertBefore(pinned, list.firstChild);
  });
})();

// Shared loading state for intensive submit buttons (Start All Free, Start All Auto)
function startFreeMissionsAjax(btn) {
	btn.innerHTML = '<span class="btn-spinner"></span> Working&hellip;';
	btn.disabled = true;
	var autoBtn = document.querySelector('#startAutoMissionsForm button');
	if (autoBtn) autoBtn.disabled = true;
	$.get('ajax/start-free-missions.php', function() {
		window.currentMissionsLoaded = false;
		loadCurrentMissions();
	});
}

function startAutoMissionsAjax(btn) {
	btn.innerHTML = '<span class="btn-spinner"></span> Working&hellip;';
	btn.disabled = true;
	var freeBtn = document.querySelector('#startFreeMissionsForm button');
	if (freeBtn) freeBtn.disabled = true;
	$.get('ajax/start-auto-missions.php', function() {
		window.currentMissionsLoaded = false;
		loadCurrentMissions();
	});
}

function skullSubmitBtn(btn) {
  btn.innerHTML = '<span class="btn-spinner"></span> Working&hellip;';
  setTimeout(function() {
    ['startFreeMissionsForm', 'startAutoMissionsForm'].forEach(function(id) {
      var form = document.getElementById(id);
      if (form) {
        var b = form.querySelector('button[type="submit"]');
        if (b) b.disabled = true;
      }
    });
  }, 0);
}
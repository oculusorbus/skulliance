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
	}
	
	xhttp.open('GET', 'ajax/toggle-total-missions.php?visibility='+visibility, true);
	
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

function toggleCurrentMissions(arrow){
	var xhttp = new XMLHttpRequest();
	var visibility = "";
	
	if(arrow.id == 'down'){
		arrow.id = 'up';
		arrow.src = 'icons/up.png';
		visibility = 'hide';
		document.getElementById('current-missions-container').style.display = 'none';
	}else{
		arrow.id = 'down';
		arrow.src = 'icons/down.png';
		visibility = 'show';
		document.getElementById('current-missions-container').style.display = 'block';
	}
	
	xhttp.open('GET', 'ajax/toggle-current-missions.php?visibility='+visibility, true);
	
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
	document.getElementById('success-rate').innerHTML = rate;
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
		alert("Success Rate cannot go above 100%.");
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
				//alert(data);
				window.location.href = "missions.php";
				console.log(data);
				// Do something with the data
		    } else {
		      // Handle error
				alert("AJAX Error");
		    }
		  }
		};
	}else{
		alert('Success Rate cannot be at 0%.');
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
			const obj = JSON.parse(data);
			document.getElementById('consumable-header').style.display = 'block';
			for(var i in obj){
			  document.getElementById('mission-result-'+i).innerHTML = "<strong>"+obj[i].status+"</strong>";
			  document.getElementById('currency-'+i).innerHTML = obj[i].currency;
			  document.getElementById('consumable-'+i).style.display = 'block';
			  if(obj[i].status == "FAILURE"){
				var contents = document.getElementById('mission-reward-'+i).innerHTML;
				var withNoDigits = contents.replace(/[0-9]/g, '');
			  	document.getElementById('mission-reward-'+i).innerHTML = "0"+withNoDigits;
				document.getElementById('mission-row-'+i).className = "failure";
				document.getElementById('consumable-'+i).innerHTML += "<img class='icon consumable' src='icons/nothing.png'/>";
			  }else{
			  	document.getElementById('mission-row-'+i).className = "success";
				document.getElementById('consumable-'+i).innerHTML += "<img class='icon consumable' src='icons/"+obj[i].consumable+".png'/>";
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


function dailyReward(){
	var xhttp = new XMLHttpRequest();
	xhttp.open('GET', 'ajax/daily-reward.php?status=true', true);
	xhttp.send();
	xhttp.onreadystatechange = function() {
	  if (xhttp.readyState == XMLHttpRequest.DONE) {
	    // Check the status of the response
	    if (xhttp.status == 200) {
	      // Access the data returned by the server
	      var data = xhttp.responseText;
		  const obj = JSON.parse(data);
		  if(obj == null){
		  	document.getElementById('reward').innerHTML = "Daily Reward Already Claimed";
		  }else{
		    document.getElementById('reward').style.opacity = 1;
		  	document.getElementById('reward').innerHTML = "<strong>Day "+obj.day+":</strong> &nbsp;&nbsp;"+"<img class='icon' src='icons/"+obj.currency.toLowerCase()+".png'/> +"+obj.amount+" "+obj.currency;
			document.getElementById('claimed').style.display = "flex";
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

function scrollReveal() {
	var revealPoint = 150;
	var revealElement = document.querySelectorAll(".reveal");
	for (var i = 0; i < revealElement.length; i++) {
		var windowHeight = window.innerHeight;
		var revealTop = revealElement[i].getBoundingClientRect().top;
		if (revealTop < windowHeight - revealPoint) {
			revealElement[i].classList.add("active");
		} else {
			revealElement[i].classList.remove("active");
		}
	}
}

window.addEventListener("scroll", scrollReveal);


// Modal
// Get the modal
var modal = document.getElementById("myModal");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks on <span> (x), close the modal
if (typeof span !== 'undefined') {
	span.onclick = function() {
	  modal.style.display = "none";
	}
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (event.target == modal) {
    modal.style.display = "none";
  }
}
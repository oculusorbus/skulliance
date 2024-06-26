<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['mission_id']) && isset($_GET['quest_id'])){
	$mission_ids = array();
	$quest_ids = array();
	$mission_ids = explode(',', $_GET['mission_id']);
	$quest_ids = explode(',', $_GET['quest_id']);
	$missions_quests = array();
	$missions_quests = array_combine($mission_ids, $quest_ids);
	$mission_results = array();
	foreach($missions_quests AS $mission_id => $quest_id){
		$mission_results[$mission_id] = completeMission($conn, $mission_id, $quest_id);
	}
}else{
	echo "No Session";
}

echo json_encode($mission_results);

// Close DB Connection
$conn->close();
?>
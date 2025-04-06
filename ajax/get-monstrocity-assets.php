<?php
include '../db.php';
include '../skulliance.php';

if(isset($_SESSION['userData']['user_id'])){
	$asset_list = getMonstrocityAssets($conn);
		if(is_array($asset_list)){
		// Batch asset list into arrays of 35 items or less to allow for successful queries, had to reduce from 50 to 35 to remain under the free Koios plan limits.
		$batch_asset_lists = array();
		$final_asset_lists = array();
		$batch_index = 0;
		if(count($asset_list["_asset_list"]) < 35){
			$final_asset_lists[$batch_index] = array();
			$final_asset_lists[$batch_index]["_asset_list"] = $asset_list["_asset_list"];
		}else{
			$batch_asset_lists = array_chunk($asset_list["_asset_list"], 35);
			foreach($batch_asset_lists AS $index => $batch_asset_list){
				$final_asset_lists[$index] = array();
				$final_asset_lists[$index]["_asset_list"] = $batch_asset_list;
			}
		}
		foreach($final_asset_lists AS $final_asset_index => $final_asset_list){
			$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
			curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
			curl_setopt( $tokench, CURLOPT_POST, 1);
			curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
			curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $tokench, CURLOPT_HEADER, 0);
			curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);

			//$tokench = curl_init("https://api.koios.rest/api/v0/asset_info?_asset_policy=".$token->policy_id."&_asset_name=".$token->asset_name);
			//curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
			$tokenresponse = curl_exec( $tokench );
			// Check for errors and echo them
			if ($tokenresponse === false) {
			    $message .= "cURL Error: " . curl_error($tokench) . "\n";
			    $message .= "cURL Error Number: " . curl_errno($tokench) . "\n";
			} else {
			    // Optionally check HTTP status code
			    $http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
			    if ($http_code >= 400) {
			        $message .= "HTTP Error: Status code " . $http_code . "\n";
			        $message .= "Response: " . $tokenresponse . "\n";
			    }
			}
			$tokenresponse = json_decode($tokenresponse);
			curl_close( $tokench );

			if(is_array($tokenresponse)){
				foreach($tokenresponse AS $index => $tokenresponsedata){
					print_r($tokenresponsedata);
					exit;
				} // End foreach
			}else{
				$message .= "Bulk asset info could not be retrieved for stake address: https://pool.pm/".$address." \r\n";
				$failed_addresses[] = $address;
				echo $message;
				print_r($tokenresponse);
				sendDM("772831523899965440", $message);
				exit();
			}
		} // End foreach
	}else{
		echo "You do not have any Monstrocity NFTs";
	}
}else{
	echo "You are not logged in to discord.";
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>
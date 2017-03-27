<?php
/**
 * Example server side script to connect to MongoDB and populate it 
 * with gully sensor data from Smart Streets Hub using the Hypercat API 
 * and MongoDB PHP Driver. 
 *
 * The Hypercat API can be found on Github, please visit: 
 * https://github.com/SenseTecnic/hypercat-php
 *
 * For MongoDB PHP Driver Documentations, please visit:
 * http://php.net/mongo/
 * 
 * Schedule a CRON job to execute this script to update data frequently.
 * For more information about setting up CRON jobs, please see the README file.
 **/
include "../library/GullySensor.php"; // Gully Sensor Class
include "../config.php"; // Config file

set_error_handler('errHandle');

//MongoDB Config Variables 
$dbhost= $config["mongo"]["dbhost"];
$dbname = $config["mongo"]["dbname"];
$collection = $config["mongo"]["collection"];

// Initialise MongoDB connection
try {
	$m = new MongoClient("mongodb://$dbhost");
} catch (MongoConnectionException $e) {
	die('Error connecting to MongoDB server.');
}
$db = $m->$dbname;
$collection = $db->$collection;









$offset = 0; // Set paging offset
$limit = 10; // Set paging limit

$finish=false;
do{
	$results = $client->searchCatalogue($param, $offset, $limit); 
	foreach($results['items'] as $item) {
		$sensor_id=null;
		$lastupdate=null;
		$data_href=null;
		foreach ($item["i-object-metadata"] as $metadata){
			if ($metadata["rel"]=="urn:X-smartstreets:rels:hasId"){
				$sensor_id= $metadata["val"];
			}
			if ($metadata["rel"]=="urn:X-smartstreets:rels:lastUpdate"){
				$lastupdate= $metadata["val"];
			}
			if ($metadata["rel"]=="urn:X-smartstreets:rels:data"){
				$data_href= $metadata["val"];
			}
		}
		// Query Mongo to see if item with sensor ID already exists
		$query = array("sid"=> (int)$sensor_id);
		$cursor = $collection->find($query);
		
		if ($cursor->count()==0){
			print_r ("Created new Sensor item! New sensor Id: ".$sensor_id."\n");
			$response=curl_with_authentication($data_href, $api_key);
			// Insert new item to MongoDB
			var_dump($data_href);
			var_dump($response);
			$gully = new GullySensor($response, $lastupdate);
			$gullyArray =$gully->create_db_object();
			if ($gullyArray["la"]!==null)
				$collection->insert($gullyArray);
		}else{
			foreach ($cursor as $doc) {
				if ($doc["lastupdate"]==$lastupdate)
					print_r ("Sensor has not been updated since last check. \n");
				else{
					print_r ("Updated Sensor!\n");
					// Update existing item in MongoDB
					$response=curl_with_authentication($data_href, $api_key);
					$gully = new GullySensor($response, $lastupdate);
					$gullyArray =$gully->create_db_object();
					if ($gullyArray["la"]!==null)
						$collection->update($query, array('$set'=>$gullyArray));
				}
			}
		}
	}
	$offset+=$limit; // Increment paging offset
	// Finish loop when reaching end of all sensor results
	if (count($results['items'])==0){
		$finish=true;
		$collection->ensureIndex(array("geo" => "2dsphere"));
	}
}while(!$finish);

function curl_with_authentication ($url, $key){
	// Initialise cURL
    $ch = curl_init();
    //set url
    curl_setopt($ch, CURLOPT_URL, $url);
    //Allows results to be saved in variable and not printed out
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if (base64_decode($key,true))
    	$headerRel="Aurthorization";
    else
        $headerRel="x-api-key";
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        	$headerRel.': '.$key,
      	));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function errHandle($errNo, $errStr, $errFile, $errLine) {
    $msg = "$errStr in $errFile on line $errLine";
    if ($errNo == E_NOTICE || $errNo == E_WARNING) {
        throw new ErrorException($msg, $errNo);
    } else {
        echo $msg;
    }
}
?>
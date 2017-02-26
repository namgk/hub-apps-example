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
$collection = "gully";

// Initialise MongoDB connection
try {
	$m = new MongoClient("mongodb://$dbhost");
} catch (MongoConnectionException $e) {
	die('Error connecting to MongoDB server.');
}
$db = $m->$dbname;
$collection = $db->$collection;

/*** Retrieve gully data via Hypercat API PHP client ***/
// Set Simple Search parameters

$results = null;

// Parse CSV file 
$file="";
$row=2;
if (isset($argv[1]))
   $file = (string)$argv[1]; // use the command line argument for file name
else
  die("Error: Please provide file name as command argument.\n");
if (isset($argv[2])){
  if (is_numeric($argv[2]))
    $row = (int)$argv[2]; // use the command line argument for file name
  else
    die("Error: Please provide an integer starting row number.\n");
}

$fp = fopen($file,'r') or die("Can not open file! \n");

$count=0;
while($csvline = fgetcsv($fp,1024)) {
  $count++;
  if ($count < 74000){ // max: 75900
    continue;
  }
  $lastupdate = '2017-02-24';
  $gullySensorInput=csv_to_gully($csvline);
  $gully = new GullySensor($gullySensorInput, $lastupdate);
  var_dump($gully);

  $gullyArray =$gully->create_db_object();
  if ($gullyArray["la"]!==null)
    var_dump($gullyArray);
    $collection->insert($gullyArray);

  //TODO: gracefully detect end of file
}

fclose($fp) or die("can not close file");

$collection->ensureIndex(array("geo" => "2dsphere"));

function csv_to_gully ($csvline){
  $desc = $csvline[0];
  $id = $csvline[1];
  $timestamp = $csvline[2];
  $data = json_decode($csvline[3], true);
  $data['id'] = $id;
  $data['sensor_id'] = $id;
  $data['timestamp'] = $timestamp;
  $data['sensor_name'] = 'noname';

  $gully = array(
    'data' => array(
      0 => $data
    )
  );
  
  var_dump($gully);
	return $gully;
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

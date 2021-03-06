<?php
ini_set("memory_limit","-1");

$dbhost = 'localhost';  
$dbname = 'demo';

// Get AJAX parameters
$collection = $_POST['collection'];
$query = json_decode($_POST['query'],true);
$lat = $_POST['lat'];
$lng = $_POST['lng'];
$radius = $_POST['radius'];

// MongoDB Initialization
try {
	$m = new MongoClient("mongodb://$dbhost"); // connect to mongo
} catch (MongoConnectionException $e) {
	die('Error connecting to MongoDB server');
}
$db = $m->$dbname;
$dbCollection = $db->$collection;

$geojson=array(
    "type"=>"Point",
    "coordinates"=>array((float)$lng, (float)$lat)
);

$geoRangeArray = array(
    '$geometry' => $geojson,
    '$maxDistance'=>(float)$radius
);
$queryArray = array(
    'geo'=> Array('$near' => $geoRangeArray)
);

$cursor = $db->$collection->find($queryArray);
$results= json_encode(iterator_to_array($cursor));
echo $results;
?>
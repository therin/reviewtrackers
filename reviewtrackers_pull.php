<?php

// Instantiate new mongodb connection
$mongo = new MongoClient("mongodb://login:password@localhost:27017");
// select a database
$db = $mongo->reviewtrackers;
// select collection
$collection = $db->reviews;

$yesterday = date('d.m.Y',strtotime("-4 days"));

// Request ReviewTrackers Auth token

$curl = curl_init();

$username = "apiusername";
$password = "apipassword";
$agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.reviewtrackers.com/auth",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_USERAGENT => $agent,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "",
  CURLOPT_HTTPHEADER => array(
   "accept: application/vnd.rtx.authorization.v2.hal+json;charset=utf-8",
   "authorization: Basic " . base64_encode($username.":".$password), 
   "content-type: application/vnd.rtx.auth.v2.hal+json;charset=utf-8"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);


if ($err) {
  echo "cURL Error #:" . $err;
} else {
// echo $response;
 $result = json_decode($response);
 $auth_token = $result->token;
 $account_id = $result->account_id;
// echo $result->token;

}

// Get a collection of Group Resources to map location (store) to group (country/region)


curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.reviewtrackers.com/groups",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
  CURLOPT_USERAGENT => $agent,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_USERPWD => "$username:$auth_token",
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "accept: application/json",
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);


if ($err) {
  echo "cURL Error #:" . $err;
} else {
  $groups = json_decode($response,true);
  $grouped_stores = array();
  foreach($groups['_embedded']['groups'] as $group){
    $grouped_stores[$group['name']] = $group['active_location_ids'];
   
}
}


curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.reviewtrackers.com/reviews?account_id=" . $account_id . "&per_page=500",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
  CURLOPT_USERAGENT => $agent,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_USERPWD => "$username:$auth_token",
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "accept: application/json",
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  //echo $response;
}

// Convert JSON to PHP array and push to mongodb
$reviews = json_decode($response,true);

// Map locations (stores) to corresponding groups (countries) and append to subarray
// Loop and create separate documents for each review in reviews collection
// Use review ID as unique index to get rid of duplicates
foreach($reviews['_embedded']['reviews'] as $review){
  foreach ($grouped_stores as $key => $group) {
    if (in_array($review['location_id'], $group)){
      array_push($review,$key);
      $collection->update(array('_id' => $review['id']),($review),array("upsert" => true));
    }

  }
}


?>
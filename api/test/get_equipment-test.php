<?php
require_once("lib-test.php");
print 'TEST 1. JSON given data with POST method for retrieving equipment with limit and offset: {"request":"get_equipment", "params":{"limit":2,"offset":1}}<br/>';
$content = array(
	"request" => "get_equipment",
	"params"=> array(
		"limit" => "2",
		"offset" => "1"
	)
);
test_curl_request("v1/equipment", $content, "application/json");
print "<br/>";

print 'TEST 2. JSON given data for retrieving equipment with equipment_id=6: {"request":"get_equipment", "params":{"equipment_id":6}}<br/>';
$content = array(
	"request" => "get_equipment",
	"params"=> array ("equipment_id" => 6)
);
test_curl_request("v1/equipment", $content, "application/json");
print "<br/>";

print 'TEST 3. JSON given data for retrieving equipment with name=YateDRA: {"request":"get_equipment", "params":{"name":"YateDRA"}}<br/>';
$content = array(
	"request" => "get_equipment",
	"params"=> array ("equipment" => "YateDRA")
);
test_curl_request("v1/equipment", $content, "application/json");
print "<br/>";

print 'TEST 4. JSON given data for retrieving equipment with specified params: {"request":"get_equipment", "params":{"type":"hss","ip_management":"192.168.168.197"}}<br/>';
$content = array(
	"request" => "get_equipment",
	"params"=> array ("type" => "hss", "ip_management"=>"192.168.168.197")
);
test_curl_request("v1/equipment",$content, "application/json");
print "<br/>";
 
print "TEST 5. GET mmi/api/v1/equipment<br/>";
test_curl_request("v1/equipment", null, null, 'get');
print "<br/>";


print "TEST 6. GET mmi/api/v1/equipment/{equipment_id} with equipment_id=6<br/>";
test_curl_request("v1/equipment/6", null, null, 'get');
print "<br/>";

print "TEST 7. GET mmi/api/v1/equipment?equipment=name with name=YateDRA<br/>";
test_curl_request("v1/equipment?equipment=YateDRA", null, null, 'get');
print "<br/>";

print "TEST 8. GET mmi/api/v1/equipment?type=hss&ip_management=192.168.168.197<br/>";
test_curl_request("v1/equipment?type=hss&ip_management=192.168.168.197", null, null, 'get');
print "<br/>";

print "TEST 9. GET mmi/api/v1/equipment?ip_management=192.168.168.197. Error must be returned.<br/>";
test_curl_request("v1/equipment?ip_management=192.168.168.197", null, null, 'get');
print "<br/>";



<?php
/**
 * http://172.31.0.108/mmi/api/test/put_equipment-test.php
 *
 * edit basic info about equipment
 * Requests format:
 * -----------------
 * PUT mmi/api/v1/equipment/{equipment_id}  with JSON content 
 * {"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"/..}
 * or
 * ?equipment=name&ip_management=ip&type=hss
 *  {"request":"put_equipment", "params":{"equipment_id":..., "equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"..}}  
 */ 
require_once("lib-test.php");

print 'TEST 1. JSON given data with PUT method<br/>';
$content = array(
	"request" => "put_equipment",
	"params"=> array(
		"equipment_id"	=> 9,
		"equipment" 	=> "TestHSS1",
		"ip_management" => "192.168.168.122"
	)
);
test_curl_request("v1/equipment", $content, "application/json", 'put');
print "<br/>";

print 'TEST 2. PUT mmi/api/v1/equipment/8  with JSON content <br/> 
	 {"equipment":"yTESTHSS", "ip_management":"192.168.168.185"}
	<br/>';
$content = array(
		"equipment"		 => "yTESTHSS",
		"ip_management"  => "192.168.168.178"
);
test_curl_request("v1/equipment/8", $content, "application/json", 'put');
print "<br/>";

print "TEST 3. PUT mmi/api/v1/equipment/10?equipment=thss2&ip_management=192.168.168.177&type=ucn<br/>";
$content = array();
test_curl_request("v1/equipment/10?equipment=thss&ip_management=192.168.168.177&type=ucn", $content, null, 'put');

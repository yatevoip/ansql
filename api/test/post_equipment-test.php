<?php
/**
 * To test this just set in browser: 
 * http://172.31.0.108/mmi/api/test/post_equipment-test.php
 *
 *  Add not configured equipment
 *  Requests format:
 *  ----------------
 *
 *  RESTful request:
 *  POST mmi/api/v1/equipment with JSON content 
 *  {"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"..}
 *  or
 *  ?equipment=name&ip_management=ip&type=hss
 *  
 *  POST JSON request: 
 *  {"request":"post_equipment", "params":{"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"..}} -
 *
 */ 
require_once("lib-test.php");
print 'TEST 1. POST with JSON content: {"request":"post_equipment", "params":{"equipment":"thss1", "ip_management":"192.168.168.185","type":"hss"}}<br/>';
$content = array(
	"request" => "post_equipment",
	"params" => array(
		"equipment" => "thss1",
		"ip_management" => "192.168.168.185",
		"type" => "hss"
	)
);

test_curl_request("v1/equipment", $content, "application/json");
print "<br/>";

print "TEST 2. POST mmi/api/v1/equipment?equipment=thss2&ip_management=192.168.168.184&type=hss<br/>";
test_curl_request("v1/equipment?equipment=thss4&ip_management=192.168.168.182&type=hss", null, null, 'post');
print "<br/>";

print 'TEST 3. POST mmi/api/v1/equipment with JSON: {"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"..}<br/>';
$content = array(
	"equipment"=>"thss3",
	"ip_management"=>"192.168.168.183",
	"type"=>"hss"
);
test_curl_request("v1/equipment", $content, "application/json", 'post');
print "<br/>";

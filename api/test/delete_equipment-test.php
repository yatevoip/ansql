<?php
/**
 * http://172.31.0.108/mmi/api/test/delete_equipment-test.php
 *
 * Requests format:
 * ---------------
 * DELETE mmi/api/v1/equipment/{equipment_id} - 
 * {"request":"delete_equipment", "params": { "equipment_id":...} } 
 */ 

require_once("lib-test.php");

print 'TEST 1. JSON given data with POST method for delete equipment<br/>';
$content = array(
	"request" => "delete_equipment",
	"params"=> array(
		"equipment_id" => "8",
	)
);
test_curl_request("v1/equipment", $content, "application/json");
print "<br/>";

print "TEST 2. DELETE mmi/api/v1/equipment/10<br/>";
test_curl_request("v1/equipment/10", null, null, 'delete');
print "<br/>";


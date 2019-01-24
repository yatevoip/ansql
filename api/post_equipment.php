<?php
/**
 * Copyright (C) 2019 Null Team
 *
 * This software is distributed under multiple licenses;
 * see the COPYING file in the main directory for licensing
 * information for this specific distribution.
 *
 * This use of this software may be subject to additional restrictions.
 * See the LEGAL file in the main directory for details.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Add not configured equipment
 * Requests format:
 * -----------------
 *  POST mmi/api/v1/equipment with JSON content 
	{"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"..}
		or
	?equipment=name&ip_management=ip&type=hss
 	
	POST with JSON content: {"request":"post_equipment", "params":{"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"..}} 

 * Request specified action for equipment 
 * Requests format:
 * ----------------
 *  POST mmi/api/v1/equipment/{equipment_id}?action=request_configuration
 *  POST with JSON content: {"request":"post_equipment", "params":{"equipment_id":..., "action":...}} 
 
 *  Each response will have the format:
 *  -----------------------------------
 *  	in case of error: { "code": !0, "message": ... }
 *  	in case of success: { "code": 0, "equipment_id": equipment_id }
 */
function post_equipment($request)
{
	$action = get_param($request, "action");
	if ($action) {
		// TBI: identify the actions for equipment
		return array(false, 200, "This request is not implemented.");
	} 

	// add equipment as not configured

	$params = array();
	$required_params = array("equipment", "ip_management", "type");
	foreach ($required_params as $name) {
		$val = get_param($request,$name);
		if (!$val)
			return array(false, 402, "Missing '$name' parameter!");
		if ($name == 'type')
			$val = Equipment::translateAPIEquipmentType($val);
		$params[$name] = $val;
	}

	$type = $params["type"];
	$params["ss7_support"]      = get_param($request,"ss7_support");
	$params["diameter_support"] = get_param($request,"diameter_support");
	$params["status"]	        = "not configured";
	$params["serial_number"]    = "12345";
	
	$eq_types = array("YateDRA","YateHSS/HLR","YateSMSC","YateSTP","YateUCN","YateUSGW");
	// for custom equipments set custom_form to true
	if (!in_array($type, $eq_types)) 
		$params["custom_form"] = "t";

	if (!isset($params["ss7_support"]) && $type!="YateSTP")
		$params["ss7_support"] = "disabled";

	if (!isset($params["diameter_support"]) && $type!="YateDRA")
		$params["diameter_support"] = "disabled";

	if ($type=="YateUSGW" || $type=="YateSTP") {
		$params["ss7_support"] = "active";
		$params["diameter_support"] = "disabled";
	}
	
	$equipment = new Equipment();
	$equipment->disableTemp();
	$res = $equipment->add($params, true);
	if (!$res[0]) {
		return translate_error_to_code($res);
	}

	return array(true, array("equipment_id"=>$equipment->equipment_id));
}

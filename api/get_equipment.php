<?php
/**
 * get_equipment.php
 * This file is part of the MMI API 
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
 * - retrieve equipment list from MMI
 *    Request format:
 *    ---------------
 *  	GET mmi/api/v1/equipment  
 *  	POST {"request":"get_equipment", "params": {"limit":x, "offset":y} }
 *  - retrieve equipment with equipment_id to check if it already exists
 *    Request format:
 *    ---------------
 * 		 GET mmi/api/v1/equipment/{equipment_id} 
 *  	 POST {"request":"get_equipment","params":{"equipment_id":id}}
 *  - retrieve equipment with name to check if it already exists
 *    Request format:
 *    ---------------
 * 		 GET mmi/api/v1/equipment?equipment=name
 *  	 POST {"request":"get_equipment","params":{"equipment":"name"}}
 *  - retrieve specific equipment type with specified management_ip
 *    Requests format:
 *    ----------------
 *  	GET mmi/api/v1/equipment?type=hss&ip_management=ip   
 *  	POST {"request":"get_equipment","params":{"type":hss/ucn/.., "ip_management":"..." }
 
 *  Each response will have the format:
 *  -----------------------------------
 *  	in case of error: { "code": !0, "message": ... }
 *  	in case of success: { "code": 0, "equipment": [ {}, {} ] }
 */

function get_equipment($request)
{
	global $default_limit, $default_offset;

	$limit = (get_param($request,"limit")) ? get_param($request,"limit") : $default_limit;
	$offset = (get_param($request,"offset")) ? get_param($request,"offset") : $default_offset;
	$order = "equipment";
	$cond = array();
	$names = array("equipment_id", "equipment", "ip_management");
	$type = get_param($request, "type"); 
	foreach ($names as $name) {
		$param = get_param($request, $name);
		if ($name == "ip_management") {
			if ($type && !$param)
				return array(false, 402, "Missing 'ip_management' parameter!");
			if (!$type && $param)
				return array(false, 402, "Missing 'type' parameter!");
			if ($type)
				$cond["type"] = Equipment::translateAPIEquipmentType($type);
		}

		if ($param) {
			$cond[$name] = $param;
		}
	}
	return retrieve_equipment($cond, $order, $limit, $offset);
}

function retrieve_equipment($cond, $order, $limit, $offset)
{
	$eqs = TemporaryModel::selection("equipment", $cond, $order, $limit, $offset);
	$elem_to_retrieve =  array("equipment_id", "equipment", "type", "ip_management", "status");
	$equipment = array();

	for ($i=0; $i<count($eqs); $i++) {
		foreach ($elem_to_retrieve as $k=>$elem)
			$equipment[$i][$elem] = $eqs[$i]->$elem;
	}

	return array(true, array("equipment"=>$equipment));
}

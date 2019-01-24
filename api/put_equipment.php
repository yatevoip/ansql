<?php
/*
 *
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
 * put_equipment.php
 * edit basic info about equipment
 *  Requests format:
 *  ----------------
 *  PUT mmi/api/v1/equipment/{equipment_id}  with JSON content 
	{"equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"/..}
		or
	?equipment=name&ip_management=ip&type=hss
   
 * {"request":"put_equipment", "params":{"equipment_id":..., "equipment":"name", "ip_management":"...","type":"hss"/"ucn"/"stp"/"usgw"/..}}  
 *
 *
 *  Response format:
 *   ---------------
 * in case of error: { "code": !0, "message": ... }
 * in case of success: { "code": 0, "equipment_id": xx }
*/

function put_equipment($request)
{
	$eq_id = get_param($request, "equipment_id"); 
	if (!$eq_id)
		return array(false, 402, "Missing 'equipment_id' parameter!");

	$equip = TemporaryModel::selection("equipment", array("equipment_id"=>$eq_id));
	if (!count($equip))
		return array(false, 402, "This equipment_id ".$eq_id." doesn't exist!");

	$eq = new Equipment;
	$eq->equipment_id = $eq_id;
	$eq->select();

	if ($eq->status=='not configured' || $eq->status=='disabled') {
		$eq->disableTemp();
	}
	
	$params = array("equipment","ip_management","type");
	foreach ($params as $param) {
		$val = get_param($request,$param);
		if ($param == 'type')
			$val = Equipment::translateAPIEquipmentType($val);
		if ($val) {
			$eq->{$param} = $val;
			$update[] = $param;
			$val = null;
		}
	}

	if (!count($update))
		return array(false, 402, "No parameters provided for editing equipment: ". $eq_id);

	$res = $eq->fieldUpdate(array("equipment_id"=>$eq_id),$update);
	if (!$res[0]) {
		return translate_error_to_code($res);
	}
	return array(true, array("equipment_id"=>$eq_id));
}

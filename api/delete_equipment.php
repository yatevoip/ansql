<?
/*
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
 * delete an equipment 
 * Requests format:
 * ----------------
 * DELETE mmi/api/v1/equipment/{equipment_id} - 
 * {"request":"delete_equipment", "params": { "equipment_id":...} } 
 *
 * Response format:
 * ---------------
 * in case of error: { "code": !0, "message": ... }
 * in case of success: { "code": 0, "equipment_id": id }
 */
function delete_equipment($request)
{
	$eq_id = get_param($request, "equipment_id"); 
	if (!$eq_id)
		return array(false, 402, "Missing 'equipment_id' parameter!");

	$equipment = new Equipment;
	$equipment->equipment_id = $eq_id;
	$equipment->select();

	if (!$equipment->equipment)
		return array(false, 402, "This equipment_id ".$eq_id." doesn't exist!");

	if ($equipment->status=='not configured' || $equipment->status=='disabled') {
		$equipment->disableTemp();
	}

	$res = $equipment->objDelete();
	if (!$res[0])
		return translate_error_to_code($res);

	return array(true, array("equipment_id"=>$eq_id));
}

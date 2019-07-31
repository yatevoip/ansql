<?php 
/**
 * check_validity_fields_enb.php
 *
 * This file is part of the Yate-LMI Project http://www.yatebts.com
 *
 * Copyright (C) 2016 Null Team
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
 */

/* validate CellIdentity from [basic] section */
function validate_cell_identity($field_name, $field_value)
{
	if (strlen($field_value)!=7 || !ctype_xdigit($field_value))
		return array(false, "Field '" . $field_name . "' is not valid: " . $field_value .". Must be 7 hex digits.");
	
	return array(true);
}

/* validate EARFCN */
// $field_value corresponds to the inserted earfcn
function validate_earfcn_band($field_name, $field_value, $name_band, $name_bandwidth, $sel_band=null,$sel_bandw=null)
{
	$bands = get_available_bands();
	if (!$bands)
		return array(false, $_SESSION["error_get_bands"]." Please fix error before trying to save new configuration.");
	
	$bandwidth_mhz = array(
	    6 => 1.4,
	    15 => 3,
	    25 => 5,
	    50 => 10,
	    75 => 15,
	    100 => 20
	);
	
	$selected_band = ($sel_band)? $sel_band : getparam($name_band);
	$selected_bandwidth = ($sel_bandw)? $sel_bandw : getparam($name_bandwidth);
	
	if (!isset($bandwidth_mhz[$selected_bandwidth]))
		return array(false, "Invalid selected 'Bandwidth': $selected_bandwidth");
	
	$bandwidth = $bandwidth_mhz[$selected_bandwidth];
	foreach ($bands as $optional_band) {
		if ($optional_band["band"]==$selected_band)
			$band = $optional_band;
	}
	
	if (!isset($band))
		return array(false, "Selected 'Band':$selected_band is not in permitted values.");
	
	$min_earfcn = $band["min_earfcn_dl"];
	$max_earfcn = $band["max_earfcn_dl"];
	
	if ( ! ( ($field_value - $min_earfcn) * 0.1 > ($bandwidth/2)  &&
	         ($max_earfcn - $field_value) * 0.1 > ($bandwidth/2) )  )
		return array(false, "Invalid combination of 'Band':$selected_band, 'Bandwidth':$selected_bandwidth and 'Earfcn':$field_value.");

	return array(true);
}

/**
 * Validate Broadcast format IP:port, IP:port, ....
 */
function check_broadcast($field_name, $field_value)
{
	$expl = explode(",", $field_value);
	if (!count($expl)) {
		return array(false, "Invalid format for '" . $field_name . "'. The IPs must be splitted by comma: ','.");
	}
	foreach ($expl as $k=>$bc) {
		$val = explode(":", $bc);
		$ip = trim($val[0]);
		$port = trim($val[1]);
		$valid_ip = check_valid_ipaddress($field_name,$ip);
		if (!$valid_ip)
			return $valid_ip;

		if (!$port) {
			return array(false, "Field '" . $field_name . "' doesn't contain a port.");
		}

		if (ctype_digit(strval($port)) || strlen($port)>5) {
			return array(false, "Field '" . $field_name . "' doesn't contain a valid port:  '". $port);
		}
	}

	return array(true);
}

/* validate AuxRoutingTable field */
function check_auxrouting_table($field_name, $field_value)
{
	if (!ctype_digit(strval($field_value))) { 
		$valid = check_field_validity($field_name, $field_value, null, null, "^[a-zA-Z0-9_-]+)$");
		if (!$valid[0])
			return $valid;
	} else {
		$valid = check_field_validity($field_name, $field_value, 0, 255);
		if (!$valid[0])
			return $valid;
	}
	return array(true);
} 

/* UplinkRbs + UplinkRbStartIndex must be less than the total number of RBs specified in the system bandwidth configuration */
function check_uplink_rbs($field_name, $field_value, $ulrbsstartind, $rbs)
{
	$rbs_value = getparam($rbs);
	$ulrbsstartind_value = getparam($ulrbsstartind);
	if ($field_value + $ulrbsstartind_value > $rbs_value) {
		return array(false, "UplinkRbs + UplinkRbStartIndex must be less than the total number of RBs specified by 'Bandwidth' parameter in 'Radio' section.");
	}
	return array(true);
}

function check_n1PucchAn($field_name, $field_value, $bandwidth)
{
	if (!$bandwidth)
		 return array(false, "$field_name can't be tested! No Bandwidth provided!");
	$valid = check_valid_number($field_name, $field_value);
	if (!$valid[0])
		return $valid;	
	$bandwidth_value = (int) $bandwidth;
	$max_n1PucchAn =  floor (2047 * $bandwidth_value / 110);
	if ($field_value<0 || $field_value>$max_n1PucchAn)
		return array(false, "$field_name should be between 0 and 2047 * Bandwidth / 110 ($max_n1PucchAn)");
	return array(true);
}

function check_valid_enodebid($field_name, $field_value)
{
	if ((strlen($field_value)!=5 && strlen($field_value)!=7) || !ctype_xdigit($field_value)) 
		return array(false, "Field '" . $field_name . "' is not valid: " . $field_value .". Must be 5 or 7 hex digits.");

	return array(true);
}

function validate_gtp_params()
{
	$addr4 = getparam("addr4");
	$addr6 = getparam("addr6");

	if (!valid_param($addr4) && !valid_param($addr6)) {
		return array(false,"Please set 'addr4' or 'addr6' when setting 'GTP'.", array("addr4", "addr6"));
	}

	if (valid_param($addr4)) {
		$res = check_valid_ipaddress("Addr4", $addr4);
		$res[2] = array("addr4");
		return $res;
	}

	if (valid_param($addr6)) {
		$res = check_valid_ipaddress("Addr6", $addr6);
		$res[2] = array("addr6");
		return $res;
	}

	return array(true);
}

function validate_mme_params()
{
	$mme_validations = array(
		"address" => array("callback"=>"check_valid_ipaddress", "type"=>"required"),
		"local"   => array("callback"=>"check_valid_ipaddress", "type"=>"required"),
		"streams" => array("callback"=>"check_valid_integer"),
		"dscp"	  => array("valid_values"=> array("expedited")));

	// see if we have any defined MMEs set
	$index = 0;
	while ($index<=5) {
		$index++;
		if ($index==1)
			$mme_index = "";
		else
			$mme_index = "_" . $index;

		$mme_address = "mme_address" . $mme_index;
		$mme_local = "local" . $mme_index;
		if (!(getparam($mme_address) || getparam($mme_local)))
			break;
		$mme_addresses[] = getparam($mme_address);
		foreach ($mme_validations as $param=>$param_validation) {
			$suffix = "";
			if ($param == "address")
				$suffix = "mme_";
			$name = $suffix. $param . $mme_index;
			$field_name  = ucfirst($param);
			$field_value = getparam($name);

			if (isset($param_validation["type"]) && !valid_param($field_value))
				return array(false, "The '".$name."' can't be empty in 'mme".$index."'.", array($name));

			if (isset($param_validation["callback"]) && $field_value) {
				$res = call_user_func($param_validation["callback"], $field_name, $field_value);
			} elseif (isset($param_validation["valid_values"]) && $field_value) {
				$args = array($field_name, $field_value, $param_validation["valid_values"]);
				$res = call_user_func_array("check_valid_values_for_select_field", $args);
			}

			if (!$res[0]) {
				$res[2] = array($name);
				return $res;
			}
		}
	}

	if (count(array_unique($mme_addresses))<count($mme_addresses)) { 
		$err = "Found duplicated MME Addresses. ";
		$dup = array_count_values($mme_addresses);
		foreach ($dup as $ip=>$how_many) 
			$err .= "The Address: ".$ip. " is duplicated. ";

		return array(false, $err, array("mme_address"));
	}

	return array(true);
}

function check_output_level_validity($field_name, $field_value, $restricted_value)
{
	$valid = check_field_validity($field_name, $field_value, 0, 43);
	if (!$valid[0])
		return $valid;

	if ($field_value > $restricted_value)
		return array(false, "The '".$field_name. "' should not exceed the 'MaximumOutput' parameter from 'RadioHardware' section.");

	return array(true);
}

function check_SpecialRntiCodingEfficiency($field_name, $field_value)
{
	if ($field_value < 0.0625 || $field_value > 4)
		return array(false, "The '".$field_name. "' should be in the range 0.0625 - 4.0");

	return array(true);
}

function check_valid_tac($field_name, $field_value)
{
	if (!ctype_xdigit($field_value))
		return array(false, "Invalid '$field_name' value $field_value. Values should be hex format. Ex: 1A0C");
	if (strlen($field_value)!=4)
		return array(false, "Invalid '$field_name' value $field_value. Value should be 4 digits long. Ex: 1A0C");
	
	return array(true);
}
?>

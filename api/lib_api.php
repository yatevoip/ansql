<?php
/**
 * lib_api.php
 *
 * Copyright (C) 2016-2023 Null Team
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 */

?>
<?php

include "../use_json_requests.php";

function translate_error_to_code($res)
{
	if (!is_array($res) || count($res)<2 || !isset($res[0]))
		return array(false, "500", "Internal Server Error: wrong result format from called function.");
	if ($res[0])
		return array(true, "0", $res[1]); // success
	
	if (isset($res[1])) {
		if (strpos($res[1], " is required")!==false || strpos($res[1]," not set.")!==false)
			return array(false, "402", "Missing or empty parameters. ". $res[1]);
		elseif (strpos($res[1], "is already defined")!==false || strpos($res[1], "associated to another")!==false)
			return array(false, "403", "Duplicate entity. ". $res[1]);
		elseif (strpos($res[1], "invalid")!==false)
			return array(false, "401", "Invalid parameter(s) value. ".$res[1]);
		else
			return array(false, "400", "Generic error. ".$res[1] );
	}
}

function build_success($data, $params, $extra=null)
{
	if (isset($data["download"]))
		return array(
			"code"      => 200,
			"message"   => "OK",
			"params"    => $params,
			"download"  => $data["download"],
			"data"		=> $data["archive"]
		);
	$res = array(
		"code"		=> 200,
		"message"	=> "OK",
		"params"	=> $params,
		"data"		=> array("code" => 0, "answer_list"=>$data)
	);
	if ($extra)
		$res["data"]["extra"] = $extra;
	return $res;
}

function build_error($code, $message, $params=array(), $extra=null)
{
	$res = array(
		"code"		=> $code, 
		"message"	=> $message,
		"params"	=> $params,
		"data" 		=> array(
			"code"      => $code,
			"message"   => $message
		)
	);
	if ($extra)
		$res["data"]["extra"] = $extra; // keeps more details of the broadcast aggregate data
	return $res;
	
}

function log_request($inp, $out = null, $write_api_logs = false, $start_time = 0, $custom_content = "")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	global $logs_file;
	global $logs_dir;

	if ($write_api_logs) {
		// the path where all products will write the API logs
		$logs_dir = "/var/log/json_api";
		$logs_file = (is_writeable($logs_dir)) ? "$logs_dir/requests_log.txt" : null;
	}

	if (!$logs_file) {
		if (false !== $logs_file)
			Debug::xdebug("api", "Not writeable $logs_dir");
		return;
	}
	
	$addr = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : "unknown";
	$day = date("Y-m-d");
	$file = str_replace(".txt","$day.txt",$logs_file);
	$fh = fopen($file, "a");
	if (!$fh) {
		Debug::xdebug("api", "Can't write to $file");
		return;
	}

	if ($out === null)
		$out = "";
	elseif (json_encode($out))
		$out = "JsonOut: ".json_encode($out)."\n";
	else
		$out = "DataOut: ". $out."\n";

	$content = "------ " . date("Y-m-d H:i:s") . ", ip=$addr\nURI: ".$_SERVER['REQUEST_URI'];
	
	// If the user id of the request owner is present, add it to the logs file.
	if ($custom_content)
		$content .= $custom_content;
	
	$content .= "\nJson: " . json_encode($inp)."\n$out";
	fwrite($fh, $content);
	if ($start_time)
		fwrite($fh, sprintf("Handled: %0.3f\n",microtime(true) - $start_time)."\n");
	fclose($fh);
}

function decode_post_put($ctype, $method, $uri)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	if ($ctype == "application/json" || $ctype == "text/x-json") {
		$input = json_decode(file_get_contents('php://input'), true);
		if ($input === null)
			return array(null, null, null);
		if (isset($input["request"])) {
			if (isset($input["params"]))
				return array($input["request"], $input["params"],@$input["node"]);
			else
				return array($input["request"], array(),@$input["node"]);
		}
	} else {
		if ($method == "PUT") {
			$input = array();
			parse_str(file_get_contents("php://input"), $input);
			foreach ($input as $key => $value) {
				unset($input[$key]);
				$input[str_replace('amp;', '', $key)] = $value;
			}
		} else
			$input = $_POST;
		$input = array_merge($input, $uri["params"]);
	}

	return array(strtolower($method) . "_" . $uri["object"], $input,@$input["node"]);
}

/**
 * Return the actual format of the request that needs to be sent to equipment 
 * @param $handling String: broadcast, failover, proxy etc
 * @param $type String: npdb, subscriber etc
 */ 
function format_api_request($handling, $type, $input)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	global $requests_with_set;
	
	$ctype = $input["ctype"];
	$method = $input["method"];
	$output = array(); 

	if ($method == "POST" || $method == "PUT") {
		list($output["request"], $output["params"], $output["node"]) =
			decode_post_put($ctype, $method, $input);
		if ($output["params"] === null)
			return array("code"=>415, "message"=>"Unparsable JSON content");
	} elseif ($method == "GET") {
		$output["request"] = "get_" . $input["object"];
		$output["params"] = $_GET;
	} elseif ($method == "DELETE") {
		$output["request"] = "del_" . $input["object"];
		$output["params"] = $input["params"];
	}
	
	if (in_array($type, $requests_with_set)) {
		//$output["node"] = "npdb";
		$req = explode("_", $output["request"]); 
		$method = strtoupper($req[0]);

		if ($method == "PUT" || $method == "POST")
			$output["request"] = "set_".$input["object"];
	}
	
	// for npdb there are no ids, but will be used for other cases in the future
	if (isset($input["id"]))
		$output[$input["object"] . "_id"] = $input["id"];

	Debug::xdebug("api", "Builded params with the format of the request: \n".print_r($output,true));
	return $output;
}
/**
 * Depending on set $handling make request to $equipment and aggregate response (if needed)
 * @param $handling String: broadcast, failover, proxy etc
 * @param $node_type
 * @param $equipment - list of IPs->name equipment returned from get_api_nodes
 * @param $request_params - returned from format_api_request
 * @param $methods - array that customizes handling for this node_type -- from $predefined_requests
 */ 
function run_api_requests($handling, $node_type, $equipment, $request_params, $methods=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	$equipment_ips = array_keys($equipment);

	$extra = "";
	$extra_err = $extra_succ = "";
	$message = "";
	$aggregate_response = array();
	$have_errors = false;
	
	$answer = array();
	if ($handling == "broadcast") {
		foreach ($equipment_ips as $management_link) {
			$equipment_name = $equipment[$management_link];
			Debug::xdebug("api", "Type of request: 'broadcast' send to equipment '$management_link' with request_params: \n".print_r($request_params,true));
			$res = send_api_request($request_params,$management_link);
			
			// request fails, aggregate the messages and put extra info about the fail/success 
			if ($res["code"] != 0) {
				$have_errors = true;
				$code = $res["code"];
				if (strlen($extra_err))
					$extra_err .= ", ";
				$extra_err .= $equipment_name;
				/* // Not needed. It used a aggregate message 
				 if (strlen($message))
					$message .= " | ";
			  	$message .= $equipment_name.": [".$code."]: " .$res["message"];*/
			} else {
				// Not needed. Used to aggregate the successful requests
				/*foreach ($res as $k=>$response) {
					if ($k == "code")
						continue;
					//$aggregate_response[$k][][$equipment_name] = $response;
					$aggregate_response[$k][$equipment_name] = $response;
				}*/
				if (strlen($extra_succ))
					$extra_succ .= ", ";
				$extra_succ .= $equipment_name; 	
			} 
			
			$answer[] = array_merge(array("equipment_name"=>$equipment_name), $res);

			// GET requests will not be broadcast to all NPDB nodes, unless $out["params"]["broadcast"] = true
			// it is assumed that all the NPDB nodes are kept in synchronization
			$cb = (isset($methods["cb_exception_to_broadcast"])) ? $methods["cb_exception_to_broadcast"] : "exception_to_broadcast";
			if (is_callable($cb) && call_user_func_array($cb, array($request_params,$handling,$node_type)))
				break;	
		}
		
		if ($have_errors) {
			$extra = "Request ".$request_params["request"]." failed for equipment:  ". $extra_err. "\n";
			if (strlen($extra_succ))
				$extra .= "Request ".$request_params["request"]." succeeded for equipment: ".$extra_succ;
			/*return build_error($code, $message, $request_params, $extra);*/
			
		} else {

			$extra .= "Request ".$request_params["request"]." was successful for equipment: ".$extra_succ;
			/*return build_success($aggregate_response, $request_params, $extra);*/
		}
		
		return build_success($answer, $request_params, $extra);
		
	} elseif ($handling == "failover") {
		
		foreach ($equipment_ips as $management_link) {
			$equipment_name = $equipment[$management_link];
			Debug::xdebug("api", "Type of request: 'failover' send to equipment: '$management_link' with request_params: \n".print_r($request_params,true));
			$res = send_api_request($request_params,$management_link);
			
			// simulate positive response for query_data because actual data registration in UCN is needed to get this answer
			//if ($equipment_name=="ucn136")
			//	$res = array("code"=>0, "data_session"=>array("imsi"=>"aaa", "msisdn"=>"aaa", "apn"=>"internet"));
			
			// return response from first server that returns a positive response
			if ($res["code"] === 0) {
				$answer[] = array_merge(array("equipment_name"=>$equipment_name), $res);
				return build_success($answer, $request_params, "Request ".$request_params["request"]." was successful from equipment $equipment_name");
			}
		}
		
		// return the last received error inside a positive MMI response
		$answer[] = array_merge(array("equipment_name"=>$equipment_name), $res);
		return build_success($answer, $request_params, "Request ".$request_params["request"]." failed: ".$res["message"]);
		
		/*return build_error($res["code"], $res["message"], $request_params);*/
	}
}

function send_api_request($request_params,$management_link)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	$out = array(
		"node"    => $request_params["node"],
		"request" => $request_params["request"],
		"params"  => isset($request_params["params"]) ? $request_params["params"] : array()
	);
	$url = "http://$management_link/api.php";
	return make_request($out,$url);	
}

function exception_to_broadcast($request_params,$handling,$node_type)
{
	// GET requests will not be broadcast to all NPDB nodes, unless $out["params"]["broadcast"] = true
	// it is assumed that all the NPDB nodes are kept in synchronization
	if (substr($request_params["request"],0,3) == "get" && (!isset($request_params["params"]["broadcast"]) || !$request_params["params"]["broadcast"]))
		return true;

	return false;
}

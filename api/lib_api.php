<?php

include "../use_json_requests.php";

function translate_error_to_code($res)
{
	if (!is_array($res) || count($res)<2 || !isset($res[0]))
		return array(false, "500", "Internal Server Error: wrong result format from called function.");
	if ($res[0])
		return array(true, "0", $res[1]); // success
	
	if (isset($res[1])) {
		if (substr($res[1], -12)==" is required" || substr($res[1], -9)==" not set.")
			return array(false, "402", "Missing or empty parameters ". $res[1]);
		else
			return array(false, "500", "Internal Server Error: database generated: ".$res[1] );
	}
}

function build_success($data, $params, $extra="")
{
	return array(
		"code"		=> 200,
		"message"	=> "OK",
		"params"	=> $params,
		"data"		=> array_merge(array("code" => 0), $data),
		"extra"     => $extra
	);
}

function build_error($code, $message, $params = array(), $extra = "")
{
	return array(
		"code"		=> $code, 
		"message"	=> $message,
		"params"	=> $params,
		"data" 		=> array(
			"code"      => $code,
			"message"   => $message,
			"extra"     => $extra // keeps more details of the broadcast aggregate data
		)
	);
}

function log_request($inp, $out = null, $extra = "")
{
	global $logs_file;
	global $logs_dir;

	$addr = "unknown";
	if (isset($_SERVER['REMOTE_ADDR']))
		$addr = $_SERVER['REMOTE_ADDR'];

	if (!$logs_file) {
		if (false !== $logs_file)
			print "\n// Not writeable $logs_dir";
		return;
	}
	$day = date("Y-m-d");
	$file = str_replace(".txt","$day.txt",$logs_file);
	$fh = fopen($file, "a");
	if ($fh) {
		if ($out === null)
			$out = "";
		else 
			$out = "Response: ".json_encode($out)."\n";

		fwrite($fh, "------ " . date("Y-m-d H:i:s") . ", ip=$addr\nURI: ".$_SERVER['REQUEST_URI']."\nRequest: ".json_encode($inp)."\n$out\n");
		if (strlen($extra))
			fwrite($fh, "------ " ."Extra: ".$extra);
		fclose($fh);
	}
	else
		print "\n// Can't write to $file";
}

function decode_post_put($ctype, $method, $uri)
{
	if ($ctype == "application/json" || $ctype == "text/x-json") {
		$input = json_decode(file_get_contents('php://input'), true);
		if ($input === null)
			return array(null, null);
		if (isset($input["request"])) {
			if (isset($input["params"]))
				return array($input["request"], $input["params"]);
			else
				return array($input["request"], array());
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

	return array(strtolower($method) . "_" . $uri["object"], $input);
}

/**
 * Return the actual format of the request that needs to be sent to equipment 
 * @param $handling String: broadcast, failover, proxy etc
 * @param $type String: np, subscriber etc
 */ 
function format_api_request($handling, $type, $input)
{
	$ctype = $input["ctype"];
	$method = $input["method"];
	$output = array(); 

	if ($method == "POST" || $method == "PUT") {
		list($output["request"], $output["params"]) =
			decode_post_put($ctype, $method, $input);
		if ($output["params"] === null)
			return array("code"=>415, "message"=>"Unparsable JSON content");
	} elseif ($method == "GET") {
		$output["request"] = "get_" . $input["object"];
		$output["params"] = $_GET;
	} elseif ($method == "DELETE") {
		$output["request"] = "delete_" . $input["object"];
		$output["params"] = $input["params"];
	}

	if ($type == "np") {
		$output["node"] = "npdb";
		$req = explode("_", $output["request"]); 
		$method = strtoupper($req[0]);

		if ($method == "PUT" || $method == "POST")
			$output["request"] = "set_".$input["object"];
		if ($method == "DELETE")
			$output["request"] = "del_".$input["object"];
	}

	// for npdb there are no ids, but will be used for other cases in the future
	if (isset($input["id"]))
		$output[$input["object"] . "_id"] = $input["id"];
	return $output;
}
/**
 * Depending on set $handling make request to $equipment and aggregate response (if needed)
 * @param $handling String: broadcast, failover, proxy etc
 * $equipment - list of IPs->name equipment returned from get_api_nodes
 * $request_params - returned from format_api_request
 */ 
function run_api_requests($handling, $type, $equipment, $request_params)
{
	$equipment_ips = array_keys($equipment);

	$extra = "";
	$extra_err = $extra_succ = "";
	$message = "";
	$aggregate_response = array();
	$have_errors = false;
	if ($handling == "broadcast") {
		foreach ($equipment_ips as $management_link) {
			$equipment_name = $equipment[$management_link];

			$out = array(
				"node"    => $request_params["node"],
				"request" => $request_params["request"],
				"params"  => isset($request_params["params"]) ? $request_params["params"] : array()
			);
			$url = "http://$management_link/api.php";
			$res = make_request($out,$url);
			// request fails, aggregate the messages and put extra info about the fail/success 
			if ($res["code"] != 0) {
				$have_errors = true;
				$code = $res["code"];
				$extra_err .= $equipment_name.", ";
			  	$message .= $equipment_name.": [".$code."]: " .$res["message"]." | ";
			} else {
				// aggregate the successful requests
				foreach ($res as $k=>$response) {
					if ($k == "code")
						continue;
					$aggregate_response[$k][][$equipment_name] = $response;
				}
				$extra_succ .= $equipment_name.", "; 	
			}
		}
	}

	if ($have_errors) {
		$extra = "Request ".$request_params["request"]." failed for equipment:  ". $extra_err;
		if (strlen($extra_succ))
			$extra .= " succeeded for equipment: ".$extra_succ;
		return build_error($code, $message, $request_params, $extra);
	}

	$extra .= "Request ".$request_params["request"]." was successfull for equipment: ".$extra;
	return build_success($aggregate_response, $request_params, $extra);
}

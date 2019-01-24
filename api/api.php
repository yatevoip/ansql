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
 */ 

set_include_path(get_include_path().":".__DIR__."/../../");
include_once("classes/equipment.php");
include_once("ansql/framework.php");
include_once("ansql/lib.php");
include_once("config.php");
include_once("lib_api.php");
include_once("api_config.php");

$response = do_request();
if ($log_status) {
	log_request($response["params"], $response["data"]);
}

header("HTTP/1.1 " . $response["code"] . " " . $response["message"]);
if (isset($response["data"])) {
	header("Content-Type: application/json");
	echo json_encode($response["data"]);
}

function process_request($req, $params)
{
	if (!is_file($req . ".php"))
		return build_error(401, "Request '$req' not implemented!");

	require_once $req . ".php";
	$res = call_user_func($req, $params);
	$log_params = array("request"=>$req,"params"=>$params);
	if ($res[0])
		return build_success($res[1], $log_params);

	return build_error($res[1], $res[2], $log_params);
}

function do_request($method = "POST")
{
	global $cors_origin, $log_status, $api_secret;

	if (("OPTIONS" == $_SERVER["REQUEST_METHOD"]) 
		&& isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"])
		&& ($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"] == $method)) {
			header("Access-Control-Allow-Origin: $cors_origin");
			header("Access-Control-Allow-Methods: $method");
			header("Access-Control-Allow-Headers: Content-Type");
			header("Content-Type: text/plain");
			exit;
	}
	$errcode = 0;
	$errtext = "";
	$orig = isset($_SERVER["HTTP_ORIGIN"]) ? $_SERVER["HTTP_ORIGIN"] : "*";
	if (("*" != $cors_origin) && (0 !== strpos($orig,$cors_origin))) 
		return build_error(403, "Access Forbidden");
	
	if (("" != $api_secret) && !isset($_SERVER["HTTP_X_AUTHENTICATION"])) 
		return build_error(401, "API Authentication Required");

	if (("" != $api_secret) && ($_SERVER["HTTP_X_AUTHENTICATION"] != $api_secret))
		return build_error(403, "API Authentication Rejected");
	
	$ctype = isset($_SERVER["CONTENT_TYPE"]) ? strtolower($_SERVER["CONTENT_TYPE"]) : "";
	$ctype = preg_replace('/[[:space:]]*;.*$/',"",$ctype);

	if (!valid_ctype($ctype))
		return build_error(415, "Unsupported Media Type");

	/* URI parsing example for '/v1/equipment/12'
	 * 		[0]=> string(20) "/v1/equipment/12"
	 * 		[1]=> string(2) "v1"
	 * 		[2]=> string(9) "equipment"
	 * 		[3]=> string(3) "/12"
	 * 		[4]=> string(2) "12"
	 *
	 * Match indices are always the same, even if some of the subpatterns
	 * are not present.
	 */
	if (!preg_match('/^\/([a-z][[:alnum:]_]*)\/([[:alnum:]_-]+)(\/(.*))?$/', $_SERVER["PATH_INFO"], $match))
		return build_error(400, "Invalid URI");

	if ($match[1] != "v1")
		return build_error(501, "Unsupported API version");

	$uri = array(
		"object"	=> $match[2],
		"id"		=> @$match[4],
		"params"	=> array()
	);
	parse_str($_SERVER["QUERY_STRING"], $uri["params"]);

	if ("POST" == $_SERVER["REQUEST_METHOD"] || "PUT" == $_SERVER["REQUEST_METHOD"])
		return do_post_put($ctype, strtolower($_SERVER["REQUEST_METHOD"]), $uri);

	if ("GET" == $_SERVER["REQUEST_METHOD"])
		return do_get($ctype, $uri);

	if ("DELETE" == $_SERVER["REQUEST_METHOD"])
		return do_delete($ctype, $uri);

	return build_error(405, "Method Not Allowed");
}

function valid_ctype($ctype)
{
	$valid_ctypes = array("application/json", "text/x-json", "application/x-www-form-urlencoded", "");
	return in_array($ctype, $valid_ctypes);
}

function do_post_put($ctype, $method, $uri)
{
	if ($ctype == "application/json" || $ctype == "text/x-json") {
		$input = json_decode(file_get_contents('php://input'), true);
		if ($input === null)
			return array("code"=>415, "message"=>"Unparsable JSON content");
		if (isset($input["request"]))
			return process_request($input["request"], $input["params"]);
	} else {
		if ($method == "put") {
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

	if ($uri["id"])
		$input[$uri["object"] . "_id"] = $uri["id"];
	return process_request($method . "_" . $uri["object"], $input);
}

function do_get($ctype, $uri)
{
	$input = $_GET;
	if ($uri["id"])
		$input[$uri["object"] . "_id"] = $uri["id"];
	return process_request("get_" . $uri["object"], $input);
}

function do_delete($ctype, $uri)
{
	$input = array();
	if ($uri["id"])
		$input[$uri["object"] . "_id"] = $uri["id"];
	return process_request("delete_" . $uri["object"], $input);
}

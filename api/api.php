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
include_once "../../api/api_includes.php";

// enable dump_xdebug in session if debug is set
if (isset($debug))
	$_SESSION["dump_xdebug"] = "on";

$response = do_request();
if ($log_status) {
	$extra = (isset($response["extra"])) ? $response["extra"] : "";
	log_request($response["params"], $response["data"], $extra);
}

if (isset($debug)) {
	Debug::xdebug("api", "The response of the request when debug is activated from api_config.php: \n".print_r($response["data"],true));
	// we don't want to register other debug after request is registered in file
	unset($_SESSION["dump_xdebug"],$_SESSION["xdebug"]);
}

$mess = str_replace("\n","", $response["message"]);
header("HTTP/1.1 " . $response["code"] . " " . $mess);
if (isset($response["data"])) {
	header("Content-Type: application/json");
	echo json_encode($response["data"]);
}

function process_request($req, $params)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	// in case POST/PUT type of HTTP requests was used, rename them to mare readable add_ / edit_ ..
	// GET and DELETE are clear enough
	$renamed_req = str_replace(array("post_", "put_", "delete_"), array("add_", "edit_", "del_"), $req);
	$filename = "api/requests/" . $renamed_req . ".php";
	if (stream_resolve_include_path($filename)) {
		$req = $renamed_req;
	} else {
		$filename = "api/requests/" . $req . ".php";
		if (!stream_resolve_include_path($filename)) {
			Debug::xdebug("api", "Filename $filename doesn't exist.");
			return build_error(405, "Method not allowed");
		}
	}
	
	require_once $filename;
	
	// in some cases function with same name as request, exits in one of the included libs
	// before calling function with same name as request, see if api_$req exists 
	$func_name = (is_callable("api_".$req)) ? "api_".$req : $req;
	
	$res = call_user_func($func_name, $params);
	$log_params = array("request"=>$req,"params"=>$params);
	if ($res[0])
		return build_success($res[1], $log_params);

	return build_error($res[1], $res[2], $log_params);
}

function do_request($method = "POST")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");

	global $cors_origin, $log_status, $api_secret, $predefined_requests;

//	if (!$api_secret)
//		$api_secret = "mmi_secret";

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

	/* URI parsing example for '/v1/equipment/12'  | '/v1/np/series' (with predefined requests)
	 * 		[0]=> string(20) "/v1/equipment/12"    | [0]=> string(20) "/v1/np/series"
	 * 		[1]=> string(2) "v1"                   | [1]=> string(2) "v1"
	 * 		[2]=> string(9) "equipment"            | [2]=> string(2) "np"
	 * 		[3]=> string(3) "/12"                  | [3]=> string(3) "/series"
	 * 		[4]=> string(2) "12"                   | [4]=> string(2) "series"
	 *
	 * Match indices are always the same, even if some of the subpatterns
	 * are not present.
	 *
	 * ^\/([a-z][[:alnum:]_]*)\/([[:alnum:]_-]+)(\/([^\/]*))?(\/([^\/]*))?$
	 *  starts with literal '/'
	 *	1 lowercase letter                                      [a-z]
	 *	any number of numbers, letters or literal '_'           [[:alnum:]_]
	 *	literal char                                            '/'
	 *	at least one number, letter or literal '_' or '-'       [:alnum:]_-]
	 *  0-2 patterns consisting of a literal '/' followed by any number of
	 *  any characters except '/'                               (\/([^\/]*))?(\/([^\/]*))?
	 *  end of pattern                                          $  
	 * 
	 * Example of parsing when uri is /v1 -- mmi/api/v1
	 * array(2) {
	 *  [0]=>
	 *  string(3) "/v1"
	 *  [1]=>
	 *  string(2) "v1"
	 * }
	 *                                         $ 					
	 */
	
	// see the type of URI that was used: if long uri (mmi/api/v1/equipment/12 or mmi/api/v1/no  ) or short uri ( mmi/api/v1 - common for all requests )
	if (!preg_match('/^\/([a-z][[:alnum:]_]*)\/([[:alnum:]_-]+)(\/([^\/]*))?(\/([^\/]*))?$/', $_SERVER["PATH_INFO"], $match)) {
		
		if (!preg_match('/^\/([a-z][[:alnum:]_]*)(\/([^\/]*))?$/', $_SERVER["PATH_INFO"], $match)) {
			return build_error(400, "Invalid URI");
		}
	}

	if ($match[1] != "v1")
		return build_error(501, "Unsupported API version");

	Debug::xdebug("api", "The relevant part from the URI that was taken from 'PATH_INFO' (".$_SERVER["PATH_INFO"]."): \n".print_r($match,true));
	
	/*
	 * Example: npdb
	 * $predefined_requests = array(
	 *     "broadcast" => array(
	 *          "npdb" => array(
	 *             "cb_get_api_nodes" => "custom_get_npdb_nodes", // if missing a default  get_api_nodes() will be called 
	 *             "cb_format_params" => "custom_format_params",  // if missing a default function format_api_request() will be called
	 *             "cb_run_request" => "custom_apply_request",    // if missing a default function run_api_requests() will be called
	 *          )
	 *      )
	 *  );
	 *
	 */ 
	if (isset($predefined_requests)) {
		$uri = array(
			"method"    => $_SERVER["REQUEST_METHOD"],
			"ctype"     => $ctype,
			"object"    => @$match[4],
			"id"	    => @$match[6],
			"params"    => array()
		);
		parse_str($_SERVER["QUERY_STRING"], $uri["params"]);

		Debug::xdebug("api", "The built data from the URI that will be used with the predefined requests: \n".print_r($uri,true));

		$methods_allowed = array("GET","POST","PUT","DELETE");
		if (!in_array($_SERVER["REQUEST_METHOD"], $methods_allowed)) {
			Debug::xdebug("api", "The REQUEST_METHOD received is not allowed: ".$_SERVER["REQUEST_METHOD"]);
			return build_error(405, "Method Not Allowed");
		}

		foreach ($predefined_requests as $handling=>$types) {
			
			foreach ($types as $type=>$methods) {
				
				// in case type of api was not specified in the path, try to guess to type 
				// WE SHOULD NOT have same request name in multiple types
				if (isset($match[2]) && $type!=@$match[2])
					continue;

				$cb = "get_api_nodes";
				if (isset($methods["cb_get_api_nodes"]))
					$cb = str_replace ("custom_","",$methods["cb_get_api_nodes"]);

				// get the ips of the nodes where the requests will be sent to
				$ips = call_user_func_array($cb, array($handling,$type));

				// build the format for the new requests to be send to API using a custom function or the default function
				if (isset($methods["cb_format_params"]))
					$params_request = call_user_func_array(str_replace("custom_","",$methods["cb_format_params"]), array($handling,$type,$uri));
				else
					$params_request = format_api_request($handling,$type,$uri);
	
				// if node_type was not specified in uri, make sure it was added in JSON, otherwise all requests will be prisoners 					
				$node = null;
				if (isset($match[2]))
					$node = $match[2];
				elseif (array_key_exists("node", $params_request))
					$node = $params_request["node"];
				
				if (!$node || $node!=$type)
					continue;
				
				$params_request["node"] = $node;

				Debug::xdebug("api", "The parameters of the request: \n".print_r($params_request,true));

				if (isset($params_request["code"]))
					return build_error($params_request["code"],$params_request["message"]);
				
				// return the response from API after running the custom function to process the API requests
				if (isset($methods["cb_run_request"]))
					return call_user_func_array(str_replace("custom_","",$methods["cb_run_request"]), array($handling,$type,$ips,$params_request));

				// if not all requests are forwarded for this node, verify that this request is set in accepted requests list
				if (!isset($methods["requests"]) || (!is_array($methods["requests"]) && $methods["requests"]!="*"))
					return build_error(500,"Internal server error");
			
				if (is_array($methods["requests"]) && !in_array($params_request["request"], $methods["requests"]) ) {
					Debug::xdebug("api", "Request ".$params_request["request"]." is not set in the predefined requests: \n".print_r($methods["requests"],true));
					return build_error(405, "Method not allowed");
				}
				
				// return the response from API after running the default function to process the API requests
				return run_api_requests($handling,$type,$ips,$params_request,$methods);
			}
		}
	}

	$uri = array(
		"object"	=> @$match[2],
		"id"		=> @$match[4],
		"params"	=> array()
	);
	parse_str($_SERVER["QUERY_STRING"], $uri["params"]);

	if ("POST" == $_SERVER["REQUEST_METHOD"] || "PUT" == $_SERVER["REQUEST_METHOD"])
		return do_post_put($ctype, $_SERVER["REQUEST_METHOD"], $uri);

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
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	list($method, $input, $node_type) = decode_post_put($ctype, $method, $uri);

	if ($input === null)
		return array("code"=>415, "message"=>"Unparsable JSON content");

	if (@$uri["id"])
		$input[$uri["object"] . "_id"] = $uri["id"];

	return process_request($method, $input);
}

function do_get($ctype, $uri)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	$input = $_GET;
	if ($uri["id"])
		$input[$uri["object"] . "_id"] = $uri["id"];
	return process_request("get_" . $uri["object"], $input);
}

function do_delete($ctype, $uri)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"api");
	$input = array();
	if ($uri["id"])
		$input[$uri["object"] . "_id"] = $uri["id"];
	return process_request("delete_" . $uri["object"], $input);
}

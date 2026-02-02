<?php
/**
 * Copyright (C) 2012-2023 Null Team
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

?>
<?php

require_once("debug.php");

function make_request($out, $request=null, $response_is_array=true, $recursive=true, $wrap_printed_error = true, $extra_headers = array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $send_request_function;
	global $path_api;

	$func = (isset($send_request_function) && $send_request_function && function_exists($send_request_function)) ? $send_request_function : "make_direct_request";

	if (isset($send_request_function) && $send_request_function && function_exists($send_request_function))
		$func = $send_request_function;
	elseif (isset($path_api))
		$func = "make_direct_request";
	else
		$func = "make_curl_request";

	if (isset($out["params"]) && is_callable("transform_request_params"))
		transform_request_params($out["params"]);

	if ($func == "make_curl_request")
		return $func($out,$request,$response_is_array,$recursive,$wrap_printed_error,false,true,$extra_headers);
	return $func($out,$request,$response_is_array,$recursive,$wrap_printed_error,$extra_headers);
}

function make_direct_request($out, $request=null, $response_is_array=true, $recursive=true, $wrap_printed_error = true, $extra_headers = array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $path_api;

	$func = $request;
	if (substr($func,-4)!=".php") {
		$page = "$func.php";
	} else {
		$page = $func;
		$func = substr($func,0,strlen($func)-4);
	}

	require_once($page);
	// handle_direct_request must be implemented separately
	// and it's application specific
	$inp = handle_direct_request($func,$out);

	if ($func == "get_captcha")
		return;

	check_errors($inp);
	if (($inp["code"]=="215" || $inp["code"]=="226") && $recursive) {
		if (is_array($extra_headers) && count($extra_headers))
			$res = make_curl_request(array(),"get_user",true,false,true,false,true,$extra_headers);
		else
			$res = make_curl_request(array(),"get_user",true,false);
		if ($res["code"]=="0" && isset($res["user"])) 
			$_SESSION["site_user"] = $res["user"];
		else
			Debug::trigger_report("critical","Could not handle direct request nor curl request for $request");
	}
	return $inp;
}

function build_request_url(&$out,&$request)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $server_name,$request_protocol;

	if (!isset($request_protocol))
		$request_protocol = "http";
	$url = "$request_protocol://$server_name/json_api.php";
	$request_full = $request; //(substr($request,-4)!=".php") ? $request.".php" : $request;
	$out = array("req_func"=>$request_full,"req_params"=>$out);
	return $url;
}

function make_curl_request($out, $request=null, $response_is_array=true, $recursive=true, $wrap_printed_error = true, $token_alarm_center = false, $trigger_report = true, $extra_headers = array(), $http_method="post")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $method;
	global $conn_error;
	global $parse_errors;
	global $json_api_secret, $cb_update_json_api_secret;
	global $func_build_request_url;
	global $request_timeout;
	global $displayed_errors;
	global $func_handle_headers;
	global $request_http2;

	if (substr($request,0,7)!="http://" && substr($request,0,8)!="https://") {
		if (!isset($func_build_request_url) || !$func_build_request_url)
			$func_build_request_url = "build_request_url";

		$url = $func_build_request_url($out,$request);
		if (!$url || (is_array($url) && !$url[0])) {
			$resp = array("code"=>"-104", "message"=>_("Please specify url where to send request."));
			write_error($request, $out, "", "", $url, $resp);
			return $resp;
		}
	} else
		$url = $request;

	if (is_callable($cb_update_json_api_secret))
		call_user_func($cb_update_json_api_secret, $url);
	
	$curl = curl_init($url);
	if ($curl === false) {
		$resp = array("code"=>"-103", "message"=>_("Could not initialize curl request."));
		write_error($request, $out, "", "", $url, $resp, $trigger_report);
		return $resp;
	}

	if (isset($_SESSION["cookie"])) {
		$cookie = $_SESSION["cookie"];
	}

	$timeout = 20;
	if (isset($out["request"])) {
		$key = $out["request"];
		if (isset($request_timeout[$key]))
			$timeout = $request_timeout[$key];
		//  Note: this is used to modify timeout for 'set_node' request but just for a specific node
		if (isset($out["node"]))
			$key .= ".".$out["node"];
		if (isset($request_timeout[$key]))
			$timeout = $request_timeout[$key];
	}

	// by default don't use HTTP/2
	if (!isset($request_http2))
		$request_http2 = false;

	if ($request_http2) {
		curl_setopt($curl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_2_0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
	} else {
		curl_setopt($curl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
	}
	$api_secret = false;
	if ($token_alarm_center)
		$api_secret = $token_alarm_center;
	elseif ($json_api_secret && $trigger_report)
		$api_secret = $json_api_secret;
	//Debug::debug_message(__FUNCTION__,"Before json encode: ".return_var_dump($out));
	$json = json_encode($out);
	Debug::debug_message(__FUNCTION__,"Encoded json: ".$json);
	if ($http_method=="post") {
		curl_setopt($curl,CURLOPT_POST,true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,$json);
	}
	curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0); # Equivalent to -k or --insecure 
	/*Set request headers.*/
	$headers = array("Content-Type: application/json", "Accept: application/json,text/x-json,application/x-httpd-php", "Accept-Encoding: gzip, deflate");
	if ($api_secret)
		$headers = array_merge(array("X-Authentication: ".$api_secret), $headers);
	
	if (is_array($extra_headers) && count($extra_headers))
		$headers = array_merge($headers, $extra_headers);
	
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);
	// handle_api_headers will be called for each header line and must return number of handled bytes
	if (is_callable($func_handle_headers))
		curl_setopt($curl,CURLOPT_HEADERFUNCTION, $func_handle_headers);

	curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
	curl_setopt($curl,CURLOPT_TIMEOUT,$timeout);
	curl_setopt($curl,CURLOPT_HEADER, true);

	if (isset($cookie)) 
		curl_setopt($curl,CURLOPT_COOKIE,$cookie);

	$http_code = "-";
	$ret = curl_exec($curl);
	if ($ret === false) {
		$error = curl_error($curl);
		// if no response from api / request times out this will be received
		$resp = array("code"=>"-100", "message"=>_("Could not send request. Please try again later.\n Error: $error."));
		write_error($request, $out, $ret, "CURL exec error: $error", $url, $resp, $trigger_report);
		curl_close($curl);
		return $resp;
	} else {	
		$http_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		
		handle_headers_response($curl, $ret);

		$code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		$type = curl_getinfo($curl,CURLINFO_CONTENT_TYPE);
		if (substr($type,0,16) == "application/json") {
			// if there are comments added to the end of a valid JSON
			// remove the comment so that the JSON is parsed correctly
			// and write a warning to the user 
			// Ex.: '{"code":0,"status":{"operational":false,"level":"MILD","state":"Disconnected"},"version":"unknown"} // Not writeable /var/log/json_api'
			//	or
			//	'{"code":0,"locked":[]} // Not writeable /var/log/json_api'
			if (($trail = strrchr($ret, "}")) !== false && strlen($trail)>1) {
				$trail = substr($trail,1);
				$ret = substr($ret,0,-strlen($trail));
				$trail = trim($trail);
				if (strlen($trail)) {
					$pref = ($wrap_printed_error) ? "<div class=\"notice error\">" : "";
					$suf  = ($wrap_printed_error) ? "</div>" : "";
					
					$err  = $pref . "The JSON received from $url was invalid. Please fix the error: ".$trail . $suf;
					if (!is_array($displayed_errors))
						$displayed_errors = array();
					if (!in_array($err, $displayed_errors)) {
						print $err;
						$displayed_errors[] = $err;
					}
				}
			}
			$inp = json_decode($ret,true);
			if (!$inp || $inp==$ret) {
				$resp = array("code"=>"-101", "message"=>_("Could not parse JSON response."), "http_code"=>$http_code);
				write_error($request, $out, $ret, $http_code, $url, $resp, $trigger_report);
				curl_close($curl);
				return $resp;
			}
			check_errors($inp);
			curl_close($curl);
			/*if (($inp["code"]=="215" || $inp["code"]=="226") && $recursive) {
				$res = make_curl_request(array(),"get_user",$response_is_array,false);
				if ($res["code"]=="0" && isset($res["user"])) 
					$_SESSION["site_user"] = $res["user"];
				// else
				//       bad luck, maybe submit bug report
			}*/
			return array_merge($inp, array("http_code"=>$http_code));
		} elseif ($type == "image/jpeg") {
			if ($response_is_array) {
				$resp = array("code"=>"-102", "message"=>_("Invalid content type image/jpeg for response."), "http_code"=>$http_code);
				write_error($request, $out, $ret, $http_code, $url, $resp, $trigger_report);
				return $resp;
			
			}
			curl_close($curl);
			print $ret;
		} elseif ($type == "application/octet-stream" || substr($type,0,10) == "text/plain" ||
		  substr($type,0,25) == "text/tab-separated-values" || $type == "application/x-pcapng") {
			curl_close($curl);
			return $ret;
		} else {
			//print $ret;
			$resp = array("code"=>"-101", "message"=>_("Could not parse response from API. Got unknown type $type."), "http_code"=>$http_code);
			write_error($request, $out, $ret, $http_code, $url, $resp, $trigger_report);
			curl_close($curl);
			return $resp;
			//return $ret;
		}
	}
}

function make_basic_curl_request($url,$out,$auth_header=false)
{
	global $func_handle_headers;
	global $request_http2;
	global $json_api_secret, $cb_update_json_api_secret;;
	
	if (is_callable($cb_update_json_api_secret))
		call_user_func($cb_update_json_api_secret, $url);
	
	$curl = curl_init($url);
	if ($curl === false) {
		return array(false, "Could not initialize curl request.");
	}

	if (isset($_SESSION["cookie"])) {
		$cookie = $_SESSION["cookie"];
	}

	$timeout = 20;

	$headers = array(
	    "Accept-Encoding: gzip, deflate"
	);
	if ($auth_header)
		$headers[] = "X-Authentication: ".$json_api_secret;

	// by default don't use HTTP/2
	if (!isset($request_http2))
		$request_http2 = false;

	if ($request_http2) {
		curl_setopt($curl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_2_0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
	}
	curl_setopt($curl,CURLOPT_POST,true);
	curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0); # Equivalent to -k or --insecure 
	curl_setopt($curl,CURLOPT_POSTFIELDS,$out);
	curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);

	curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);
	// handle_api_headers will be called for each header line and must return number of handled bytes
	if (is_callable($func_handle_headers))
		curl_setopt($curl,CURLOPT_HEADERFUNCTION, $func_handle_headers);

	curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
	curl_setopt($curl,CURLOPT_TIMEOUT,$timeout);
	curl_setopt($curl,CURLOPT_HEADER, true);

	if (isset($cookie)) 
		curl_setopt($curl,CURLOPT_COOKIE,$cookie);

	$ret = curl_exec($curl);

	if ($ret === false) {
		$error = curl_error($curl);
		curl_close($curl);
		return array(false, "Could not send request. Please try again later.\n Error: $error.");
	} else {
		handle_headers_response($curl, $ret);
		return $ret;
	}
}

function handle_headers_response($curl, &$ret)
{
	$info = curl_getinfo($curl);	
	$raw_headers = explode("\n", substr($ret, 0, $info['header_size']) );

	$gzip = false;
	if (!isset($_SESSION["all_cookies"]))
		$_SESSION["all_cookies"] = array();
	foreach ($raw_headers as $header) {
		if (preg_match('/^(.*?)\\:\\s+(.*?)$/m', $header, $header_parts)) {
			$headers[$header_parts[1]] = $header_parts[2];

			// HTTP headers are case insensitive and the header can be all lower case
			if (!strcasecmp($header_parts[1], "Set-Cookie")) {
				$cookie = $header_parts[2];
				$cookie = explode("=", $cookie);
				if (count($cookie)<2)
					continue;
				$value = explode(";",$cookie[1]);
				$value = $value[0];
				$_SESSION["all_cookies"][$cookie[0]] = $value;
			}
			if (!strcasecmp($header_parts[1],"Content-Encoding") && substr(trim($header_parts[2]),0,4)=="gzip")
				$gzip = true;
		}
	}

	$_SESSION["cookie"] = "";
	foreach ($_SESSION["all_cookies"] as $name=>$value) {
		if ($_SESSION["cookie"]!="")
			$_SESSION["cookie"] .= "; ";
		$_SESSION["cookie"] .= "$name=$value";
	}
	// test to check that server doesn't return gziped captcha
	//if ($gzip && $request!="get_captcha" ) {
	if ($gzip) {
		$zipped_ret = substr($ret,$info['header_size']);
		$ret = gzinflate(substr($zipped_ret,10));
	} else
		$ret = substr($ret,$info['header_size']);
}

function write_error($request, $out, $ret, $http_code, $url, $displayed_response=null, $trigger_report=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $parse_errors, $display_parse_error, $error_type_func, $db_log, $log_db_conn;

	$user_id = (isset($_SESSION["user_id"])) ? $_SESSION["user_id"] : "-";
	$text = "------".date("Y-m-d H:i:s").",request=$request,url=$url,user_id=$user_id\n"
		."Sent: ".json_encode($out)."\n"
		."Received HTTP CODE=$http_code : ".$ret."\n";
	if ($displayed_response)
		$text .= "Displayed: ".json_encode($displayed_response)."\n";

	// write_error() used from make_curl_request() is also used from Debug::trigger_report()
	// we don't want to trigger report in case of integromat request failure
	if ($trigger_report) {
		// keep writing errors separately but also write them to common logs file
		if ($displayed_response["code"]) {
			$code = $displayed_response["code"];
			if (is_callable("get_type_error") && !isset($error_type_func)) {
				if (get_type_error($code) == "fatal")
					Debug::trigger_report('ansql_json', $text);
			} elseif (isset($error_type_func) && is_callable($error_type_func)) {
				if (call_user_func_array($error_type_func,array($code)))
					Debug::trigger_report('ansql_json', $text);
			} else {
				Debug::trigger_report('ansql_json', '$error_type_func not set. The received code must be translated into an error type.');
			}
		}
	}

	if (isset($db_log) && $db_log == true) {
		$log_type = array("parse_errors");
		add_db_log($text,$log_type);
	} elseif (isset($parse_errors) && strlen($parse_errors)) {
		$fh = fopen($parse_errors, "a");
		if ($fh) {
			fwrite($fh,$text);
			fclose($fh);
		}
	}
	//if ((isset($display_parse_error) && $display_parse_error) || (isset($_SESSION["debug_all"]) && $_SESSION["debug_all"]=="on")) {
	if (isset($display_parse_error) && $display_parse_error) {
		$text = str_replace("\n", "<br/>", $text);
		print $text;
	}
}

/** 
 * DEPRECATED. Use Debug::output() or Debug::xdebug('progress',$message) instead
 */
function write_text_error($message)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	Debug::output('critical',$message);

	global $parse_errors;
	if (isset($parse_errors) && strlen($parse_errors)) {
		$fh = fopen($parse_errors, "a");
		if ($fh) {
			$user_id = (isset($_SESSION["user_id"])) ? $_SESSION["user_id"] : "-";
			fwrite($fh,"------".date("Y-m-d H:i:s").",user_id=$user_id\n");
			fwrite($fh,"Text: $message"."\n");
			fclose($fh);
		}
	}
}

function not_auth($res)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (isset($res["code"]) && $res["code"]=="43")
		return true;
	return false;
}

function check_errors(&$res)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $error_codes;

	if ($res!=null && (!isset($res["message"]) || (!strlen($res["message"]))) && @array_key_exists('code',$res)) {
		if (isset($error_codes[$res["code"]]))
			$res["message"] = $error_codes[$res["code"]];
		foreach ($res as $name => $val) {
			if (is_array($val) && !isset($val["message"]) && isset($val['error_code'])) {
				$res[$name]["message"] = $error_codes[$val["error_code"]];
			}
		}
	}

	// later on we might choose to hide error code < 200
	//if ($res["code"]<200)
	//	$res["message"] = "Problem with your request. Try again later.";
	
	if (not_auth($res)) {
		session_unset();
		if ($_SERVER["PHP_SELF"]=="/main.php") {
			on_automatic_signout($res);
			exit();
		}
	}
}

function response($response)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	print json_encode($response,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	return;
}

function check_session_on_request()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (!isset($_SESSION["user_id"])) {
		response(array("code"=>"43", "message"=>"Not authenticated."));
		return false;
	}
	return true;
}

function logout_from_api()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	make_curl_request(array(),"logout");
}

// REQUEST working mode from equipment with SDR: bts/enb 
// Additionally it initializes available nodes in $_SESSION["node_types"]
function get_working_mode()
{
	if (!isset($_SESSION["available_sdr_modes"])) {
		// nipc,roaming,dataroam,enb
		$installed_modes = request_api(array(), "get_available_modes", "modes");
		$modes = clean_sdr_modes($installed_modes);
		$_SESSION["available_sdr_modes"] = $modes;
	}
	
	if (!isset($_SESSION["sdr_mode"])) {
		// bts, enb
		$installed_nodes = request_api(array(), "get_node_type", "node_type");
		$node_types = clean_node_types($installed_nodes);

		if (count($node_types) && isset($node_types[0]["sdr_mode"])) {
			$_SESSION["node_types"] = $node_types;
			$sdr_mode = $node_types[0]["sdr_mode"];
			if ($sdr_mode=="nib")
				$sdr_mode = "nipc";
			
			if (!in_array($sdr_mode, $_SESSION["available_sdr_modes"])) {
				$sdr_mode = "";
			}
			
			$_SESSION["sdr_mode"] = $sdr_mode;
			return $sdr_mode;
		}
		return "";
	}
	
	return $_SESSION["sdr_mode"];
}

function clean_sdr_modes($installed_modes)
{
	if (!is_array($installed_modes))
		return array();

	$modes = array();
	$accepted_modes = array("nipc","roaming","dataroam","enb");

	foreach ($installed_modes as $mode) {
		if (in_array($mode, $accepted_modes))
			$modes[] = $mode;
	}
	return $modes;
}

function clean_node_types($installed_nodes)
{
	// Ex: [{"type":"smsc","version":"unknown"},{"type":"eir","version":"unknown"},{"type":"ucn","version":"unknown"},{"type":"bts","version":"0.1-1_r74_r289.mga5","sdr_mode":"enb"},
	// {"type":"enb","version":"0.1-1_r74_r289.mga5","sdr_mode":"enb"},{"type":"stp","version":"unknown"},{"type":"dra","version":"unknown"},{"type":"pcrf","version":"unknown"},{"type":"hss","version":"unknown"}]

	$nodes = array();
	$accepted_nodes = array("enb", "bts");
	foreach ($installed_nodes as $node) {
		if (!isset($node["type"]) || !in_array($node["type"], $accepted_nodes))
			continue;
		$nodes[] = $node;
	}
	return $nodes;
}

function valid_status_response($res)
{
	if (!isset($res["status"]) || !is_array($res["status"]))
		return false;
	if (!isset($res["status"]["state"]) || !isset($res["status"]["operational"]))
		return false;
	return true;
}

/**
 * Uses get_node_status API request to get the node status. 
 * Returns array("state":text,"color":red/green/yellow,"details":true/false)
 */ 
function node_status($out=array(), $url="get_node_status", $extra=array())
{
	if (!$out)
		$out = array();
	if (!$url)
		$url = "get_node_status";
	if (!$extra)
		$extra = array();
	
	$res = make_request($out,$url);
	
	$ret = array();
	foreach ($extra as $extra_field) {
		if (isset($res[$extra_field]))
			$ret[$extra_field] = $res[$extra_field];
	}

	if (!isset($res["code"])) {
		$mess = (isset($res["message"])) ? $res["message"] : $res;
		return array_merge($ret, array(
			"state"=>html_entity_decode(nl2br($mess)), 
			"color"=>"red",
			"details"=>false,
			"version"=>(isset($res["version"])) ? $res["version"] : null
		));
	}
	
	if ($res["code"] != 0)
		return array_merge($ret, array(
			"state"=>html_entity_decode(nl2br($res["message"])), 
			"color"=>"red",
			"details"=>false,
			"version"=>(isset($res["version"])) ? $res["version"] : null
		));

	if (!valid_status_response($res))
		return array_merge($ret,  array(
			"state"=>"Invalid status response", 
			"color"=>"gray",
			"details"=>false,
			"version"=>(isset($res["version"])) ? $res["version"] : null
		));


	$node_status = array(
		"details"=>true,
		"version"=>(isset($res["version"])) ? $res["version"] : null,
		"state"=> html_entity_decode(nl2br($res["status"]["state"])),
		"color" => "gray"
	);

	if (isset($res["status"]["level"])) {
		$all_colors = array(
			"green"  => array("NOTE"), 
			"red"    => array("WARN","FAIL","CRIT"),
			"yellow" => array("MILD"),
			"blue"   => array("CALL","INFO")
		);
		foreach ($all_colors as $color=>$levels) {
			if (!in_array($res["status"]["level"],$levels))
				continue;
			$node_status["color"] = $color;
		}

	}
	elseif ($res["status"]["operational"])
		$node_status["color"] = "green";
	else
		$node_status["color"] = "red";

	return array_merge($ret, $node_status);
}
?>

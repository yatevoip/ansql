<?php
/**
 * debug_all.php
 * This file is part of the FreeSentral Project http://freesentral.com
 *
 * FreeSentral - is a Web Graphical User Interface for easy configuration of the Yate PBX software
 * Copyright (C) 2008-2014 Null Team
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

require_once "set_debug.php";
require_once "lib.php";

if (is_file("../defaults.php"))
	require_once "../defaults.php";
if (is_file("../config.php"))
	require_once "../config.php";

global $allow_code_comment,$use_comments_docs;

$allow_code_comment = true;
$use_comments_docs = false;

?>
<html>
<head>
	<title> Debug Page </title>
	<link type="text/css" rel="stylesheet" href="../css/main.css" />
	<script language="javascript" type="text/javascript" src="javascript.js"></script>
</head>
<body>
<?php

$array = array(
	1 => "E_ERROR",
	2 => "E_WARNING",
	4 => "E_PARSE",
	8 => "E_NOTICE",
	16 => "E_CORE_ERROR",
	32 => "E_CORE_WARNING",
	64 => "E_COMPILE_ERROR",
	128 =>	"E_COMPILE_WARNING",
	256 =>  "E_USER_ERROR",
	512 =>	"E_USER_WARNING",
	1024 =>	"E_USER_NOTICE",
	2048 =>	"E_STRICT",
	4096 =>	"E_RECOVERABLE_ERROR",
	8191 => "E_ALL",
	6183 => "E_ALL ^ E_NOTICE",
	10239 => "E_ALL | E_STRICT",
	0 => "disable"
);

$action = getparam("action");
if($action) 
	$call = "debug_".$action;
else
	$call = "debug";
$call();


function debug()
{
	global $array, $logs_in, $debug_modules, $debug_filters;

	if (!is_array($debug_modules))
		$debug_modules = array();
	if (!is_array($debug_filters))
		$debug_filters = array();
	
	$error = ini_get("error_reporting");
	$errors = $array;
	if(isset($array[$error]))
		$errors["selected"] = $array[$error];
	else{
		$errors[$error] = $error;
		$errors["selected"] = $errors[$error];
	}

	$display_errors = (ini_get("display_errors")) ? 't' : 'f';

	$dump_xdebug = (isset($_SESSION["dump_xdebug"])) ? "t" : "f";
	$debug_queries = (isset($_SESSION["debug_all"])) ? "t" : "f";
	$display_logs_on_web = (isset($_SESSION["display_logs_on_web"])) ? $_SESSION["display_logs_on_web"] : false;
	$enable_debug_buttons = (isset($_SESSION["enable_debug_buttons"])) ? $_SESSION["enable_debug_buttons"] : false;
	$debug_modules = (isset($_SESSION["debug_modules"])) ? $_SESSION["debug_modules"] : implode(",", $debug_modules);
	$impl_filters = (isset($_SESSION["debug_filters"])) ? $_SESSION["debug_filters"]  : implode(",", $debug_filters);
	
	$log_errors = (ini_get("log_errors")) ? "t" : "f";
	$error_log = ($a = ini_get("error_log")) ? $a : '';

	$arr = array(
		"pass_debug"=>array("compulsory"=>true, "column_name"=>"Password", "comment"=>"Password to be allowed to modify the debugging options (set in config.php usually)"),
		// Setting debug levels
		"objtitle1"=>array("display"=> "objtitle" , "value"=> "Setting/Activating debug levels"),
  		"enable_debug_buttons"=>array("value"=>$enable_debug_buttons, "display"=>"checkbox"),
		"dump_xdebug"=>array("value"=>$dump_xdebug, "display"=>"checkbox", "comment"=>"Dump xdebug log in log file - not the php xdebug, but application and library specific xdebug."),
		"debug_queries"=>array("value"=>$debug_queries, "display"=>"checkbox", "comment"=>"Dump query log in log file."),
		"display_logs_on_web"=>array("column_name"=>"Display debug in web page","value"=>$display_logs_on_web, "display"=>"checkbox", "comment"=>"If checked the logs will be displayed on screen. If 'dump_xdebug'/'debug_queries' is not checked, just module logs will be displayed. By default logs are added in " . implode(",", $logs_in)),
	  	"debug_modules"=>array("value"=>$debug_modules,  "comment"=>"Comma delimited list of tags for which the values must be displayed. Overwrites critical_tags, debug_tags value with provided value and debug_all variable becomes false."),
		"debug_filters"=>array("value"=>$impl_filters, "comment"=>"Comma separated list of filters: tags or pieces of messages"),
	  	// Setting php ini 
		"objtitle2"=>array("display"=> "objtitle" , "value"=> "Settings overwriting php ini"),
		"error_reporting"=>array($errors, "display"=>"select", "comment"=>"Controls php error_reporting. If set it will be kept in session and it will modify error_reporting until logging out or modifying it again from this form."), 
		"display_errors"=>array("value"=>$display_errors, "display"=>"checkbox", "comment"=>"Controls php display_errors. If set it will be kept in session and it will modify error_reporting until logging out or modifying it again from this form."),
		"log_errors"=>array("value"=>$log_errors, "display"=>"checkbox", "comment"=>"Controls php log_errors. If set it will be kept in session and it will modify error_reporting until logging out or modifying it again from this form."),
		"error_log"=>array("value"=>$error_log/*, "display"=>"checkbox"*/),
		"examples"=>array(
		  "display"=>"message", 
		  "value"=>"Usage examples:
			<ul>
				<li> visualize logs in \$_Session['xdebug']</li>
					<ul>
						<li> by default logs are displayed in \$_Session['xdebug']</li>
						<li> use Dump \$_SESSION button to visualize them </li>	
					</ul>
				<li> send all xdebug logs in log file </li>
					<ul> 
						<li> check dump_xdebug </li>
					</ul>
				<li> display all xdebug logs on screen</li>
					<ul>
						<li> check dump_xdebug field</li>
						<li> check 'Display logs on web' field</li>
					</ul>
				<li> display just xdebug logs from specific tags on screen</li>
					<ul>
						<li> check dump_xdebug</li>
						<li> check 'Display logs on web' field</li>
						<li> add the tags in debug_modules field </li>
					</ul>			
				<li> display Debug::debug_message() function output</li>
					<ul>
						<li> check 'Display debug in web page'</li>
					</ul>
				<li> enable/disable debug buttons</li>
					<ul>
						<li> check/uncheck 'Enable debug buttons' option</li>
					</ul>
			</ul>",
		  "no_escape"=>true)
	);

	if (is_auth("debug"))
		unset($arr["pass_debug"]);

	$submit_button = "<input class=\"edit\" name=\"Save\" value=\"Save\" type=\"submit\">";
	$reset_button = "<input class=\"edit\" value=\"Reset\" type=\"reset\">";
	
	$cancel_link =  previous_page_link();
	$cancel_button = "<input class=\"edit\" value=\"Cancel\" onclick=\"location.replace('$cancel_link');\" type=\"button\">";

	$arr["buttons"] = array("display"=>"custom_submit", "value"=>$submit_button . " " . $reset_button . " " . $cancel_button);
	
?>	<br/><br/>
	<form action="debug_all.php" method="post"><?php
	addHidden("database");
	editObject(NULL,$arr, "Debug Settings", "no");
?></form><?php
}


function debug_database()
{
	global $array, $pass_debug_page;

	if (!check_auth("debug")) {
		print "Error! Please insert debug password to see this page.";
		debug();
		return;
	}

	if (getparam("display_logs_on_web")) {
		$_SESSION["display_logs_on_web"] = true;
	} else 
		unset($_SESSION["display_logs_on_web"]);
	
	if (getparam("debug_modules")) {
		$debug_modules = getparam("debug_modules");
		$_SESSION["debug_modules"] = $debug_modules;
	} else 
		unset($_SESSION["debug_modules"]);
	
	if (getparam("debug_filters")) {
		$debug_filters = getparam("debug_filters");
		$_SESSION["debug_filters"] = $debug_filters;
	} else 
		unset($_SESSION["debug_filters"]);
	
	if (getparam("enable_debug_buttons") == "on")
		$_SESSION["enable_debug_buttons"] = "on";
	else
		unset($_SESSION["enable_debug_buttons"]);

	if (getparam("debug_queries") == "on")
		$_SESSION["debug_all"] = "on";
	else
		unset($_SESSION["debug_all"]);

	if (getparam("dump_xdebug") == "on")
		$_SESSION["dump_xdebug"] = "on";
	else
		unset($_SESSION["dump_xdebug"]);

	$err = getparam("error_reporting");
	$error_reporting = NULL;
	foreach($array as $key=>$value) {
	//	print $key. ' '. $value.'<br/>';
		if($value == $err) {
			$error_reporting = $key;
			break;
		}
	}
	if(!$error_reporting)
		$error_reporting = $err;
	ini_set("error_reporting",$error_reporting);
	$_SESSION["error_reporting"] = $error_reporting;
//	ini_set('error_reporting', E_ALL);

	$display_errors = (getparam("display_errors") == "on") ? true : false;

	ini_set("display_errors",$display_errors);
	$_SESSION["display_errors"] = $display_errors;

	$log_errors = (getparam("log_errors") == "on") ? true : false;
	ini_set("log_errors",$log_errors);
	$_SESSION["log_errors"] = $log_errors;

	$error_log = getparam("error_log");
	ini_set("error_log", $error_log);
	$_SESSION["error_log"] = $error_log;

	
	$previous_link =  previous_page_link() ;
	
	message("Settings were saved!<a href='$previous_link'>Back to previous page.</a>", "no", "", false);
	
	debug();
}

function previous_page_link() 
{
	if (isset($_SESSION["previous_link"]))
		$previous_link =  $_SESSION["previous_link"];
	else
		$previous_link = str_replace("/ansql/debug_all.php", "", $_SERVER["PHP_SELF"]) . "/main.php";
	
	$request_protocol = get_request_protocol();

	return   $request_protocol . "://" . $_SERVER["HTTP_HOST"] . $previous_link;
}
?>
</body>
</html>

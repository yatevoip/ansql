<?php
/**
 * debug.php
 *
 * Copyright (C) 2008-2023 Null Team
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

// true/false
// if true then all debug messages are remembered unless they are set in $debug_tags to be excluded
// if false then only the tags listed in $debug_tags are included
if (!isset($debug_all))
	$debug_all = true;

// array of tags that should be included or excluded from debug
// a tag can't contain white spaces
if (!isset($debug_tags))
	$debug_tags = array("paranoid","in_framework","in_ansql","ansql","framework");

// list of tags that should be excluded when xdebug() is used and $debug_all = true
if (!isset($exclude_xdebug_tags))
	$exclude_xdebug_tags = array("paranoid","in_framework","in_ansql","ansql","framework");

// list of tags that should be logged when $debug_all = false
if (!isset($allowed_xdebug_tags))
	$allowed_xdebug_tags = array();

// default tag if tag is not specified
if (!isset($default_tag))
	$default_tag = "logic";

// tags that should never be truncated
if (!isset($critical_tags))
	$critical_tags = array("critical","query","query failed");

// maximum xdebug message length
// set to false or 0 to disable truncking of messages
if (!isset($max_xdebug_mess))
	$max_xdebug_mess = 50;

// filters that match the message: Example: entered editObject
if (!isset($debug_filters))
	$debug_filters = array();

if (!isset($debug_tags_js))
	$debug_tags_js = array();

/*
// options to notify in case a report was triggered
$debug_notify = array(
    "mail" => array("email@domain.com", "email2@domain.com"),
    "web"  => array("notify", "dump")
);
*/
if (!isset($debug_notify))
	$debug_notify = array("web"=>array("notify"));

// if $enable_debug_buttons is true, then several Debug buttons are displayed at the top of the page
// Ex: Dump $_SESSION, See $_REQUEST
// you can also add additional buttons if you define the callbacks for them
if (!isset($debug_buttons))
	$debug_buttons = array(
		"dump_session"=>'Dump&nbsp;$_SESSION', 
		"dump_request"=>'Dump&nbsp;$_REQUEST',
		"debug_options" => 'Debug&nbsp;options',
	  	"clean_xdebug_session" =>'Clean&nbsp;XDebug',	  
		// 
		// "custom_debug_opt1" => callback
	);

if(is_file("defaults.php"))
	include_once("defaults.php");
if(is_file("config.php"))
	include_once("config.php");

/*Include bug_report.php file.*/
if (is_file("ansql/default_classes/bug_report.php")) {
	include_once("ansql/default_classes/bug_report.php");
	include_once("ansql/use_json_requests.php");
} else if (is_file("../ansql/default_classes/bug_report.php")){
	set_include_path(get_include_path().":../");
	include_once("ansql/default_classes/bug_report.php");
	include_once("ansql/use_json_requests.php");
}

if (!isset($log_db_conn))
	$log_db_conn = connect_database_log();

if (!isset($run_id))
	$run_id = generate_run_id();

function return_var_dump()
{
	ob_start();
	call_user_func_array('var_dump', func_get_args());
	return ob_get_clean();
}


class Debug
{
	// xdebug_log when running in cli mode
	// this is only used when $_SESSION is not available
	protected static $_xdebug_log;

	/**
	 * Used when entering a function
	 * Usage: Debug::func_start(__FUNCTION__,func_get_args()); -- in functions
	 * Usage: Debug::func_start(__METHOD__,func_get_args(),"framework");  -- in methods
	 * Output: Entered function_name(0 => array (0 => 'val1',1 => 'val2'),1 => NULL,2 => 'param3')
	 * @param $func Function name
	 * @param $args Array of arguments
	 * @param $tag String Optional. If not set $default_tag is used
	 */
	public static function func_start($func,$args,$tag=null,$obj_class=null,$obj_id=null)
	{
		global $default_tag;
		if (!$tag)
			$tag = $default_tag;

		if ($obj_class)
			$mess = "Entered ".$func." for '$obj_class' id='$obj_id' (".Debug::format_args($args).")";
		else
			$mess = "Entered ".$func."(".Debug::format_args($args).")";

		Debug::xdebug($tag,$mess);
	}


	/**
	 * Calling trigger_report() will register this shutdown_function
	 * This will then be called just when program 
	 */
	public static function shutdown_trigger_report($manually_triggered=false)
	{
		global $count_triggered;
		global $log_triggered;

		if ($count_triggered==1)
			$mess = "There was ".$count_triggered." report";
		else
			$mess = "There were ".$count_triggered." reports";
		$mess .= ": $log_triggered";
		return self::trigger_report("shutdown","$mess",true,$manually_triggered);
	}

	/**
	 * Function triggers the sending of a bug report
	 * Depending on param $shutdown it agregates the received reports and registers shutdown function shutdown_trigger_report()
	 * Current supported methods: mail, web (dump or notify), api
	 * Ex:
	 * $debug_notify = array(
	 * 	"mail" => array("email@domain.com", "email2@domain.com", 
	 *			"manually_triggered"=>array("email3@domain.com", "email4@domain.com")),
	 * 	"web"  => array("notify", "dump"),
	 * 	"api"  => array("url_api")
	 * );
	 * 'mail' - send emails with the log file as attachment or the xdebug log directly if logging to file was not configured.
	 *	  - contains default email address to which bug report will be sent		
	 * 'manually_triggered' - email addresses to which reports triggered by user will be sent. 
	 *			- if 'manually_triggered' key not defined or is an empty array then bug report will be sent to default addresses
         * 'dump' dumps the xdebug log on the web page
	 * 'notify' Sets 'triggered_error' in $_SESSION. Calling method button_trigger_report() will print a notice on the page
	 *
	 * The report can be triggered by the developper directly when detecting an abnormal situation
	 * or by the user that uses the 'Send bug report' button
	 *
	 * Custom bug report email:   (customize subject and add custom lines in bug report email)
	 * Inside your project define function "customize_bug_report" to 
	 * return sequential array with two elements: 
	 *		0: string, the new email subject.
	 *		1: string, the line(s) to be inserted after first line from bug report (under username:)
	 * 
	 * @param $tag String associated Used only when triggered from code
	 * @param $message String Used only when triggered from code
	 * If single parameter is provided and string contains " "(spaces) then it's assumed 
	 * the default tag is used. Default tag is 'logic'
	 * @param $shutdown Bool. Default false. If false, it will just register a shutdown function that sends report at the end. 
	 * @param $manually_triggered Bool. Default false. Changes subject or 
	 * If true, we assume we are at shutdown and trigger_report is actually performed
	 */
	public static function trigger_report($tag, $message=null, $shutdown=false, $manually_triggered=false)
	{
		global $debug_notify;
		global $server_email_address;
		global $logs_in;
		global $proj_title;
		global $module;
		global $count_triggered;
		global $log_triggered;
		global $software_version;
		global $dev_debug_mail;

		if (!$message) {
			$message = $tag;
			$tag = "critical";
		}
		
		if ($tag) 
			self::xdebug($tag,$message);

		if (!$shutdown) {
			// find software_version here, not when called from registered shutdown function, because it seems exec() can't be called from there
			if (!isset($software_version) && function_exists("get_version"))
				$software_version = get_version();

			if (!isset($count_triggered)) {
				$count_triggered = 1;
				$log_triggered = ($message) ? $message : $tag;
				self::xdebug("first_trigger","----------------------");
				register_shutdown_function(array('Debug','shutdown_trigger_report'),$manually_triggered);
			} else {
				$count_triggered++;
				$log_triggered .= ($message) ? "; " . $message : "; ".$tag;
				self::xdebug("trigger","----------------------");
			}
			return;
		}
		// save xdebug
		$xdebug = self::get_xdebug();
		self::dump_xdebug();

		foreach($debug_notify as $notification_type=>$notification_options) {
			if (!is_array($notification_options) || !count($notification_options)) {
				continue;
			}

			switch ($notification_type) {
			case "mail":
				if (!isset($server_email_address))
					$server_email_address = "bugreport@localhost.lan";

				$user = self::get_username();
				
				$subject = ($manually_triggered) ? "Manually triggered bug report for '".$proj_title."' by $user" : "Auto triggered bug report for '".$proj_title."'";

				$body = "Username&#58; ". htmlentities($user)."\n";
				
				// if "customize_bug_report" function is defined 
				// replace subject for bug report with first elem from result
				// concatenate body lines with second element from result
				if (is_callable("customize_bug_report")) {
					$res = customize_bug_report($manually_triggered, $user);
					if ($res[0])
						$subject = $res[0];
					if ($res[1])
						$body .= $res[1];
				}
				
				$body .= "Application: '$proj_title'"."\n";
				if (isset($software_version))
					$body .= "Version: $software_version"."\n";
				
				if (isset($_SERVER["HOSTNAME"]))
					$body .= "Hostname: ".$_SERVER["HOSTNAME"]."\n";
				
				$body .= "\nApplication is running on:";
				exec("/sbin/ip addr ls", $info);

				$interfaces = self::get_interfaces();
				foreach ($interfaces as $interface) 
					$body .= $interface;

				$description = getparam("bug_description");
				$description = htmlentities($description);
				if ($description)
					$body .= "\n\n User description: ".$description;

				if ($message) {
					$body .= "\n\n Error that triggered report: ";
					$body.= ($manually_triggered) ? "See User description\n" : $message;
				}

				$attachment = false;
				$res = self::get_log($xdebug,true);
				if (!$res[0]) {
					$attachment = $res[1];
					$attach_file = $res[2];
				} else
					$body .= $res[1];
				
				$body = str_replace("\n","<br/>",$body);
							
				// set where to send triggered reports
				$to_emails = $notification_options;
				if (isset($to_emails["manually_triggered"])) {
					if ($manually_triggered && is_array($to_emails["manually_triggered"]) && count($to_emails["manually_triggered"]))
						$to_emails = $to_emails["manually_triggered"];
					else
						unset($to_emails["manually_triggered"]);
				}

				foreach ($to_emails as $to) {
					send_mail($to, $server_email_address, $subject, $body, $attachment,null,false);
				}

				if ($attachment)
					unlink($attach_file);

				break;
			case "api":
				$out = array(
					"username" => self::get_username(),
					"application" => is_callable("get_network_name") ? get_network_name($proj_title) : $proj_title,
					"version" => (isset($software_version)) ? $software_version : "",
					"interfaces" => self::get_interfaces(),
					"log" => base64_encode(self::get_log($xdebug)),
					"report_name" => self::get_report_name($message),
					"host_name" => function_exists("gethostname") ? gethostname() : ""
				);	
				if ($dev_debug_mail)
					$out["email"] = $dev_debug_mail;
				
				if (class_exists("Bug_report") && Bug_report::aggregate_report($out))
					$res = make_curl_request($out, $debug_notify["api"][0], true,true,true,false,false);
				else {
					$declared_classes = get_declared_classes();
					self::output("Critical", "Class 'Bug_report' not found. Declared classes are :".implode(", ",$declared_classes));
				}
				// integromat hook response 200 ok with json content ["code":0]
				// do nothing with result from curl 
				break;
			case "web":
				for ($i=0; $i<count($notification_options); $i++) {
					switch ($notification_options[$i]) {
					case "notify":
						$_SESSION["triggered_report"] = true;
						break;
					case "dump":
						print "<pre>";
						if (!in_array("web",$logs_in))
							// of "web" is not already in $logs_in
							// then print directly because otherwise it won't appear on screen
							if ($message)
								print "Error that triggered report: ".$message."\n";
						print $xdebug;
						print "</pre>\n";
						if (isset($_SESSION["main"]))
							print "<a class='llink' href='".$_SESSION["main"]."?module=$module&method=clear_triggered_error'>Clear</a></div>";
						break;
					}
				}
				break;
			}
		}
	}

	private static function get_username()
	{
		if (isset($_SESSION["username"])) {
			$user = $_SESSION["username"];
			$reporter = getparam("name");
			if ($reporter)
				$user .= "($reporter)";
		} else {
			exec('echo "$USER"',$user);
			$user = implode("\n",$user);
		}

		return $user;
	}

	private static function get_log($xdebug,$attachment=false)
	{		
		$logs_file = self::get_log_file();

		if (is_file($logs_file)) {
			$dir_arr = explode("/",$logs_file);
			$path = "";
			for ($i=0; $i<count($dir_arr)-1; $i++)
				$path .= $dir_arr[$i]."/";

			$new_file = $path ."log.".date("YmdHis");
			$attach_file = $path ."attach_log.".date("YmdHis");

			exec("tail -n 500 $logs_file > $attach_file");
			rename($logs_file, $new_file);
			if (!$attachment) {
				$lines = file_get_contents($attach_file);
				unlink($attach_file);
				return $lines;
			}
			return array(false, array(array("file"=>$attach_file,"content_type"=>"text/plain")), $attach_file);
		} 

		// logs are not kept in file, take the log from xdebug 
		$lines = explode("\n", $xdebug);
		$lines = array_slice($lines, -500);
		if ($attachment)
			return array(true, "\n\n". implode("\n", $lines));

		return implode("\n", $lines);
	}

	// This is the error that triggered the bug report
	private static function get_report_name($message)
	{
		$report_name = "Error that triggered report: ";
		$description = htmlentities(getparam("bug_description"));
		if ($description)
			return $report_name. "User description: ".$description;

		if ($message) 
			return  $report_name .  $message;

		return "";

	}

	private static function get_interfaces()
	{
		global $skip_interfaces;

		if (!isset($skip_interfaces))
			$skip_interfaces = 'LOOPBACK|NO-CARRIER';
	
		exec("/sbin/ip addr ls", $info);

		$skip = true;
		$interfaces = array();
		foreach ($info as $data) {
			if (preg_match('/^[1-9]/', $data)) {
				if (preg_match('/'.$skip_interfaces.'/', $data)) {
					$skip = true;
					continue;
				}
				$line = explode(':',$data);
				$interfaces[] = 'Interface: ' . trim($line[1]);
				$skip = false;
				continue;
			}
			if ($skip)
				continue;
			if (strpos($data, "inet6") !== false) {
				$line = explode(' ', trim($data));
				$interfaces[] = " IPv6: " . $line[1];
				continue;
			}
			if (strpos($data, "inet") !== false) {
				$line = explode(' ', trim($data));
				$interfaces[] = " IPv4: " . $line[1];
				continue;
			}
		}
		return $interfaces;
	}
	/**
	 * Uses xdebug to dump the content of a variable/ object/ array/ array of objects
	 *
	 * @param string $tag. Tag to identify/group debug messages easier.
	 * @param mixed $var. Can be object/array/string/int etc.. which value will be dumped
	 * @param string $obj_level.  Just for objects, ignored otherwise. Depending on provided level all properties will be displayed or just the ones defined in variables() method
	 * @param string $sep. Row separator. Default is "\n". Example: "\n", "</br>" or other..
	 * @param string $identation. Initial indentation. Default is empty string.
	 * @param string $increase_ident. How to increase identation. Default is 4 spaces.
	 */
	public static function debug_message($tag, $var, $obj_level = "basic", $sep = "\n", $identation = "", $increase_ident = "    ")
	{
		global $debug_all;
		global $exclude_xdebug_tags;
		global $allowed_xdebug_tags;
		global $logs_in;
	
		if ( ($debug_all==true && !in_array($tag,$exclude_xdebug_tags))    ||    ($debug_all==false && in_array($tag,$allowed_xdebug_tags)) ) {
			$date = gmdate("[D M d H:i:s Y]");

			$msg =  Debug::format_value($var,$obj_level, $sep, $identation, $increase_ident) ;
	
			if (in_array("web", $logs_in)) {
				$msg = $date . strtoupper($tag) . ": " . $msg;
				
				//verify message  is not already displayed by dumping xdebug so it's not displayed twice
				if (!isset($_SESSION["dump_xdebug"]) || !bool_value($_SESSION["dump_xdebug"])) {
					print "\n<p class='debugmess'>". htmlentities($msg) . "</p>" . "\n";
				}
			}
			
			if  (in_array("console", $logs_in)) {
				$msg = $date . strtoupper($tag) . ": " . $msg;
				if (is_cli())
					print $msg. "\n";
			}

			Debug::xdebug($tag, $msg);
		}
	}

	/**
	 * Transforms and formats variables content into human readable string ready to be displayed 
	 * @param any_type $var Variable to be transformed in string. Can have any type.
	 * @param string $obj_level Used just if provided variable has object type. Value can be "basic" or "esential".  
	 * @param string $sep Row separator. Default is "\n".
	 * @param string $initial_ident. Initial indentation. Default is empty string.
	 * @param string $increase_ident. How to increase identation. Default is 4 spaces.
	 * @return string. Returns $var content as human readable formated string.
	 */
	public static function format_value($var, $obj_level = "basic", $sep = "\n", $initial_ident = "", $increase_ident = "    ")
	{
		$identation   = $initial_ident . $increase_ident;
		$inside_ident = $identation . $increase_ident;
		
		$content = "";
		if (is_array($var) && count($var)) {
			foreach ($var as $key=>$v) {
				$content .= $sep . $identation . "'$key' => " . Debug::format_value($v,$obj_level, $sep, $inside_ident);
			}
			
			$msg =	$sep . $initial_ident . "array(" . count($var) . ") {" .
				$content . $sep . 
				$initial_ident . "}";		
		} else if (is_object($var)) {
			$content = Debug::dumpObj($var, $obj_level, $sep, false, $identation);
			
			$msg =	$sep . $initial_ident . get_class($var) . " Object" . $sep . 
				$initial_ident . "(" . $sep .
				$content. $sep. 
				$initial_ident . ")";
		} else {
			if (gettype($var) == "string")
				$content =  "(" . strlen($var) . ")" . "\"". $var . "\"";
			else 
				$content =  "(" . print_r($var,true) . ")";
			
			$msg = gettype($var) . $content;
		
		}
		
		return $msg;
	
	}
	
	/**
	 * Contacts $message to $_SESSION["xdebug"].
	 * The writting of this log can be triggered or not.
	 * Unless users report a bug or code reaches a developer triggered report,
	 * this log is lost when user session is closed.
	 * @param $tag String associated Used only when triggered from code
	 * @param $message String Used only when triggered from code
	 * If single parameter is provided and string contains " "(spaces) then it's assumed 
	 * the default tag is used. Default tag is 'logic'
	 */
	public static function xdebug($tag, $message=null)
	{
		global $default_tag;
		global $debug_all;
		global $exclude_xdebug_tags;
		global $allowed_xdebug_tags;
		global $max_xdebug_mess;
		global $critical_tags;
		global $debug_filters;

		if ($message==null && strpos($tag," ")) {
			$message = $tag;
			$tag = $default_tag;
		}
		if (!$message) {
			self::output("critical","Error in Debug::debug() tag=$tag, empty message in .",false);
			return;
		}
		$orig = $message;
		if (!in_array($tag, $critical_tags) &&  $max_xdebug_mess && strlen($message)>$max_xdebug_mess)
			$message = substr($message,0,$max_xdebug_mess)." - (truncated)";
		$outputted = false;
		if ( ($debug_all==true && !in_array($tag,$exclude_xdebug_tags)) || 
		     ($debug_all==false && in_array($tag,$allowed_xdebug_tags)) ) { 
			$date = gmdate("[D M d H:i:s Y]");
			if (!isset($_SESSION["dump_xdebug"]) || !bool_value($_SESSION["dump_xdebug"]))
				self::concat_xdebug("\n$date".strtoupper($tag).": ".$message);
			else {
				$outputted = true;
				$message = $orig;
				self::output($tag, $message, false);
			}
		} 
		if (!$outputted) {
			foreach ($debug_filters as $filter) {
				if (false!==stripos($message,$filter)) {
					$message = $orig;
					self::output($tag, $message, false);
					break;
				}
			}
		}
	}

	/**
	 * Logs/Prints a nice formated output of PHP debug_print_backtrace()
	 */
	public static function trace()
	{
		$trace = self::debug_string_backtrace(__METHOD__);
		$trace = print_r($trace,true);
		self::output("------- Trace\n".$trace);
	}

	/**
	 * Logs/Prints a message
	 * output is controlled by $logs_in setting
	 * Ex: $logs_in = array("web", "/var/log/applog.txt", "php://stdout");
	 * 'web' prints messages on web page
	 * If $logs_in is not configured default is $logs_in = array("web")
	 * @param $tag String Tag for the message
	 * @param $msg String Message to pe logged/printed
	 * @param $write_to_xdebug Bool. Default true. Write to xdebug log on not.
	 * Set to false when called from inside xdebug to avoid loop
	 */
	public static function output($tag,$msg=NULL,$write_to_xdebug=true)
	{
		global $logs_in, $db_log, $log_db_conn;

		// log output in xdebug as well
		// if xdebug is written then this log will be duplicated
		// but it will help debugging to have it inserted in appropriate place in xdebug log

		// still, skip writting to xdebug if xdebug is currently dumped constantly
		if ($write_to_xdebug && (!isset($_SESSION["dump_xdebug"]) || !bool_value($_SESSION["dump_xdebug"])))
			self::xdebug($tag,$msg);


		if ($msg==null && strpos($tag," ")) {
			$msg = $tag;
			$tag = "output";
		}
		if (!$msg) {
			self::output("error", "Error in Debug::debug() tag=$tag, empty message in .");
			return;
		}

		// In case we didn't configure a log file just throw this in the apache logs
		// The majority of admins/people installing will know to look in apache logs for issues
		if (!isset($logs_in)) {
			trigger_error("$tag: $msg", E_USER_NOTICE);
			return;
		}

		$arr = $logs_in;
		if(!is_array($arr))
			$arr = array($arr);

		for ($i=0; $i<count($arr); $i++) {
			if ($arr[$i] == "web") {
				print "\n<p class='debugmess'>$msg</p>\n";
			} elseif (isset($db_log) && $db_log == true) {
				add_db_log($msg,array(),$tag);
			} else {
				$date = gmdate("[D M d H:i:s Y]");
				// check that file is writtable or if output would be stdout (force_update in cli mode)
				if (!is_file($arr[$i]) || $arr[$i]=="php://stdout") {
					if ($arr[$i]!="php://stdout") {					
						//explode path by / so we can remove filename from it
						$dir_parts = explode("/", $arr[$i]);
						// remove last element from array (the file name in this case)
						array_pop($dir_parts);
						// reconstruct path - this is the path to logs directory
						$dir = implode("/", $dir_parts);
						
						if (!is_dir($dir) ) {
							trigger_error("Error in Debug::output() - The logs directory $dir does not exist.", E_USER_NOTICE);
							return;
						}
						if (!is_writable($dir) ) {
							trigger_error("Error in Debug::output() - The logs directory $dir is not writable.", E_USER_NOTICE);
							return;
						}
					}
					$fh = fopen($arr[$i], "w");
					fwrite($fh, $date.strtoupper($tag).": ".$msg."\n");
					fclose($fh);
				} else if (is_file($arr[$i])) {
					if (!is_writable($arr[$i]) ) {
						trigger_error("Error in Debug::output() - The logs file $arr[$i] is not writable.", E_USER_NOTICE);
						return;
					}
					$fh = fopen($arr[$i], "a");
					fwrite($fh, $date.strtoupper($tag).": ".$msg."\n");
					fclose($fh);
				} 
			}
		}
	}

	/**
	 * Outputs xdebug log in file of web page depending on $logs_in
	 */
	public static function dump_xdebug()
	{
		$xdebug = "------- XDebug:".self::get_xdebug();
		Debug::output("xdebug",$xdebug,false);
		// reset debug to make sure we don't dump same information more than once
		self::reset_xdebug();
	}

	/**
	 * Contacts array of arguments and formats then returning a string
	 * @param $args Array of function arguments
	 * @return String with nicely formated arguments
	 */
	public static function format_args($args)
	{
		$res = str_replace("\n","",var_export($args,true));
		$res = str_replace("  "," ",$res);
		$res = str_replace(",)",")",$res);
		// exclude 'array ('
		$res = substr($res,7);
		// exclude last ', )'
		$res = substr($res,0,strlen($res)-1);
		return $res;
	}

	/**
	 * Looks in $logs_in and returns a log file if set. It ignores 'web' and entry containing 'stdout'
	 * @return String Log File 
	 */
	public static function get_log_file()
	{
		global $logs_in;

		if (!is_array($logs_in))
			return false;
		$count_logs = count($logs_in);
		for ($i=0; $i<$count_logs; $i++) {
			if ($logs_in[$i]=="web" || strpos($logs_in[$i],"stdout"))
				continue;
			return $logs_in[$i];
		}
		return false;
	}

	/**
	 * Clears "triggered_report" variable from session
	 */
	public static function clear_triggered_error()
	{
		unset($_SESSION["triggered_report"]);
	}

	/**
	 * Returns output of PHP debug_print_backtrace after stripping out name and name provided in @exclude
	 * @param String Function name to exclude from trace
	 * @return String Trace
	 */
	private static function debug_string_backtrace($exclude=null) 
	{
		ob_start();
		debug_print_backtrace();
		$trace = ob_get_contents();
		ob_end_clean();

		$exclude = ($exclude) ? array(__METHOD__,$exclude) : array(__METHOD__);
		for ($i=0; $i<count($exclude); $i++) {
			// Remove first item from backtrace as it's this function which
			// is redundant.
			$trace = preg_replace ('/^#0\s+' . $exclude[$i] . "[^\n]*\n/", '', $trace, 1);
			// Renumber backtrace items.
			$trace = preg_replace ('/^#(\d+)/me', '\'#\' . ($1 - 1)', $trace);
		}

		return $trace;
	}

	// METHODS THAT COME AFTER THIS USE FUNCTIONS FROM lib.php THAT WAS NOT REQUIRED IN THIS FILE
	// IN CASE YOU WANT TO USE THE Debug CLASS SEPARATELY YOU NEED TO REIMPLEMENT THEM

	/**
	 * Displays buttons to trigger bug report, dump_request, dump_session + custom callbacks
	 * This must be called from outside ansql. Ex: get_content() located in menu.php
	 * -- to use this function, remember to:
	 *	define array $general_exceptions_to_save and  add in it form_bug_report
	 *      don't call save_page_info() if  method is in $general_exceptions_to_save
	 */
	public static function button_trigger_report()
	{
		global $debug_notify;
		global $module;
		global $debug_buttons;
		global $dump_request_params;

		print "<div class='trigger_report'>";
		if (isset($debug_notify["mail"]) && is_array($debug_notify["mail"]) && count($debug_notify["mail"])	|| 
			(isset($debug_notify["api"]) && is_array($debug_notify["api"]) && count($debug_notify["api"])))
			print "<a class='llink' href='main.php?module=".$module."&method=form_bug_report'>Send&nbsp;bug&nbsp;report</a>";
		if (isset($_SESSION["triggered_report"]) && $_SESSION["triggered_report"]==true && isset($debug_notify["web"]) && in_array("notify",$debug_notify["web"]))
			print "<div class='triggered_error'>!ERROR <a class='llink' href='".$_SESSION["main"]."?module=$module&method=clear_triggered_error'>Clear</a></div>";

		//buttons to Dump $_SESSION, See $_REQUEST + custom callback
		if (isset($debug_buttons) && is_array($debug_buttons)) {
			foreach ($debug_buttons as $method=>$button) {
				switch ($method) {
					case "dump_session":
						if (is_file("pages.php"))
							$page = "pages.php";
						else
							$page = (isset($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
						print " <a class='llink' href='$page?method=$method' target='_blank'>$button</a>";
						break;
					case "dump_request":
						$dump_request_params = true;
						print " <a class='llink' onclick='show_hide(\"dumped_request\");'>$button</a>";
						break;
					case "clean_xdebug_session":
						print " <a class='llink' onclick='make_request(\"pages.php?method=$method\");show(\"cleaned_xdebug_session\");'>$button</a>
							<span id='cleaned_xdebug_session' style='display:none;color: green;'>Xdebug Session was cleaned! </span>";
						break;
					case "debug_options":
						print " <a class='llink' href='ansql/debug_all.php'>$button</a>";
						$_SESSION["previous_link"] = $_SERVER["REQUEST_URI"];
						break;

					default:
						call_user_func($button);
				}
			}
		}

		print "</div>";
	}

	/**
	 * Builds and displays form so user can send a bugreport
	 * This must be called from outside ansql from function 
	 * that could be located in menu.php
	 */
	public static function form_bug_report()
	{
		$fields = array(
			"bug_description"=>array("display"=>"textarea", "compulsory"=>true, "comment"=>"Description of the issue. Add any information you might consider relevant or things that seem weird.<br/><br/>This report will be sent by email message."),

			"name"=>array()
		);

		start_form();
		addHidden(null,array("method"=>"send_bug_report"));
		editObject(null,$fields,"Send bug report","Send");
		end_form();
	}

	/**
	 * Triggers a bug report containing the info submitted by the user.
	 * Report was sent from \ref form_bug_report.
	 * This must be called from outside ansql from function 
	 * that could be located in menu.php
	 */ 
	public static function send_bug_report()
	{
		$report = getparam("bug_description");
		$report = htmlentities($report);
		$from = "From: ".getparam("name");
		$report = "$from\n\n$report";
		self::trigger_report("REPORT",$report,false,true);
	}

	public static function reset_xdebug()
	{
		if (self::$_xdebug_log!==false)
			self::$_xdebug_log = '';
		elseif (isset($_SESSION["xdebug"]))
			$_SESSION["xdebug"] = '';
		// was not initialized. Nothing to reset
	}

	public static function get_xdebug()
	{
		if (self::$_xdebug_log!==false)
			return self::$_xdebug_log;
		elseif (isset($_SESSION["xdebug"]))
			return $_SESSION["xdebug"];
		// was not initialized. Nothing to return
		return '';
	}

	private static function concat_xdebug($mess)
	{
		if (!isset($_SESSION["xdebug"]))
			$_SESSION["xdebug"] = "";
		
		if (self::$_xdebug_log===NULL) {
			if (session_id()!="") {
				self::$_xdebug_log = false;
			} else {
				self::$_xdebug_log = '';
			}
		}

		if (self::$_xdebug_log!==false)
			self::$_xdebug_log .= $mess;
		else
			$_SESSION["xdebug"] .= $mess;
	}
	/**
	 * Extracts esential/basic information from object and returns it as string
	 * @param object $obj
	 * @param string $level Default "esential". Can be "basic"/"esential"
	 * @param string $sep Default is "auto" or can be set a custom one ex: "</br>";
	 * @param boolean $print_on_scrren If true extracted information will be also displayed on screen.
	 * @param string $identation. 
	 * @return string. Dumped object.
	 */
	public static function dumpObj($obj, $level="esential", $sep="auto", $print_on_scrren = true, $identation = "")
	{
		$vars = get_object_vars($obj);
		
		$basic = array();
		$internal = array();
		$full = array("_model");
		foreach ($vars as $var_name=>$var) {
			if ($print_on_scrren)
				print "$var_name=".print_r($var,true)."<br/>";
			if (in_array($var_name, $full)) {
				continue;
			}
			if (substr($var_name,0,1) == "_") {
				$internal[] = $identation . "$var_name=".print_r($var,true);
				continue;
			}
			$basic[] = $identation . "$var_name=".print_r($var,true);
		}
		
		$basic = implode("__sep", $basic);
		$internal = implode("__sep", $internal);
		
		switch ($level) {
			case "basic":
				$res = $basic;
				// the actual values for the specific object variables
				break;
			case "all":
				$res = $basic ."__sep". $internal . "__sep" . "full: TBI";
				// add _model as well
				break;
			case "esential":
			default:
				// the actual values for the specific object variables + internal variables prefixed with _
				$res = $basic ."__sep". $internal;
		}
		
		if ($sep=="auto") 
			$sep = "<br/>\n";
		
		return str_replace("__sep",$sep,$res);
	}
}


/**
 * Function called when dump_session is set in $debug_buttons. 
 * Dumps $_SESSION or calls $cb_dump_session if set and is callable
 */
function dump_session()
{
	global $cb_dump_session;

	if (isset($cb_dump_session) && is_callable($cb_dump_session))
		call_user_func($db_dump_session);
	else {
		//detect if is $_SESSION[xdebug] too large and drop it if so
		if (function_exists("memory_get_usage")) {
			$mem_alloc = memory_get_usage();
			$max_allowed_memory = get_max_allowed_memory();
			
			if ($mem_alloc*3 >= $max_allowed_memory) {
				clean_xdebug_session();
				print "<div style=\"font-size: 13px;position: relative;color: #085098;margin-bottom: 10px;font-weight: normal;border: 1px solid #ccc;background-color: #f2ffe5;background-image: url('images/notice.png');background-position: left center;background-repeat: no-repeat;padding: 10px;    padding-left: 10px;padding-left: 40px;\">" .
				"Data stored in xDebug was dropped as it's size was too large." .
				"</div>";
			}
		}
		
		$sess = $_SESSION;

		if (isset($sess["xdebug"])) {
			$xdebug = $sess["xdebug"];
			unset($sess["xdebug"]);
		}
		if (!isset($xdebug))
			$xdebug = "";
		
		//old implementation assumed to htmlentities with array recursive

		ob_start();
		
		//first display what we have in SESSION without xDebug data
		var_dump($sess);
		
		//display xDebug data
		print "xdebug: ";
		print var_dump($xdebug);
		
		$buffer = ob_get_clean();
		echo "<pre>".htmlentities($buffer)."</pre>";
	}
}
	
function clean_xdebug_session()
{
	if (isset($_SESSION["xdebug"])) 
		$_SESSION["xdebug"] = "";
}

function get_max_allowed_memory()
{
	global $xdebug_max_memory;
	
	if (!isset($xdebug_max_memory) || !strlen($xdebug_max_memory))
		return 52428800; //50 MB
	
	return $xdebug_max_memory;
}

function auto_clean_xdebug_session()
{	
	$xdebug_max_memory = get_max_allowed_memory();
	
	if (!function_exists("memory_get_usage"))
		return;
	
	$mem_alloc = memory_get_usage();
	if (isset($_SESSION["xdebug"]) && $mem_alloc > $xdebug_max_memory) {
		clean_xdebug_session ();
		$mem_alloc_mb = round($mem_alloc/1024/1024, 3);
		error_log("PHP Notice: Triggered xdebug cleaner ({$mem_alloc_mb} MB)");
	}
}


// this is/was used when using debug_all.php from web project and activating various debug options
// TBI! Update function after debug_all.php changes
function config_globals_from_session()
{
	global $logs_in, $enable_debug_buttons, $debug_buttons, $debug_all, $debug_tags, $critical_tags, $debug_filters, $debug_tags_js;
	
	if (isset($_SESSION["enable_debug_buttons"]))
		$enable_debug_buttons = true;
	if (!isset($enable_debug_buttons) || $enable_debug_buttons!=true)
		$debug_buttons = false;
	
	if (isset($_SESSION["display_logs_on_web"])) {
		if (!is_array($logs_in))
			$logs_in = array();
		if (!in_array("web",$logs_in))
			$logs_in[] = "web";
	}
	
	if (isset($_SESSION["debug_filters"])) {
		$debug_filters = explode(",", $_SESSION["debug_filters"]);
		$debug_filters = array_map("trim",$debug_filters);
	}
	//verify if debug modules has values and if so make the necessary  configurations	
	if (isset($_SESSION["debug_modules"])) {
		$debug_modules = explode(",",  $_SESSION["debug_modules"]);
		$debug_modules = array_map("trim",$debug_modules);
	}
	if(isset($_SESSION["debug_tags_js"])) {
		$debug_tags_js = explode(",", strtolower($_SESSION["debug_tags_js"]));
		$debug_tags_js = array_map("trim", $debug_tags_js);
	}
	if (empty($debug_modules))
		return;
	$debug_all = false;
	$debug_tags = $debug_modules;
	$critical_tags = $debug_modules;	
}

function connect_database_log()
{
	global $db_log,$log_db_host,$log_db_user,$log_db_database,$log_db_passwd,$log_db_port;

	if (!isset($db_log) || $db_log == false) {
		return;
	}

	if (!function_exists("mysqli_connect")) {
		error_log("Missing mysqli package for php installed on ". $log_db_host);
		Debug::trigger_report('critical', "You don't have mysqli package for php installed on ". $log_db_host);
                return false;
	}

	$conn = mysqli_connect($log_db_host, $log_db_user, $log_db_passwd, $log_db_database, $log_db_port);
	if (!$conn) {
		error_log("Connection failed to the log database: ". mysqli_connect_error());
		Debug::trigger_report('critical', "Connection failed to the log database: ". mysqli_connect_error());
                return false;
	}

	return $conn;
}

function create_database_log()
{
	global $log_db_conn, $db_log_specifics,$log_db_database;

	if (!$log_db_conn) {
		error_log("Connection failed to the log database: ".$log_db_database);
		Debug::trigger_report('critical', "Couldn't connect to the log database.");
		return;
	}

	$query = "SHOW TABLES FROM ".$log_db_database." LIKE 'logs'";
	$result = mysqli_query($log_db_conn, $query);

	if (!$result || $result->num_rows == 0) {
		$query = "CREATE TABLE logs (log_id bigint unsigned not null auto_increment, primary key (log_id), date timestamp, log_tag varchar(100), log_type varchar(100), log_from varchar(100), log longtext, performer_id varchar(100), performer varchar(100))";
		$result = mysqli_query($log_db_conn, $query);
		if (!$result) {
			error_log("Could not create logs table: ". mysqli_error());
			Debug::trigger_report('critical', "Could not create logs table: ". mysqli_error());
		}
	}

	$table_columns = array(
		"date" => "timestamp",
		"log_tag" => "varchar(100)",
		"log_type" => "varchar(100)",
		"log_from" => "varchar(100)",
		"log" => "longtext",
		"performer_id" => "varchar(100)",
		"performer" => "varchar(100)",
		"run_id" => "varchar(100)",
		"session_id" => "varchar(100)"
	);

	$query = 'SHOW COLUMNS FROM logs';
	$result = mysqli_query($log_db_conn, $query);
	$column_names = array();
	while($row = mysqli_fetch_array($result)){
		$column_names[] = $row['Field'];
	}

	foreach($table_columns as $column_name=>$type) {
		if (!in_array($column_name,$column_names)) {
			$res = mysqli_query($log_db_conn, "ALTER TABLE logs ADD ".$column_name." ".$type.";");
			if (!$res) {
				error_log("Could not add column '".$column_name."' to the logs table: ". mysqli_error());
				Debug::trigger_report('critical', "Could not add column '".$column_name."' to the logs table: ". mysqli_error());
			}
		}
	}
}

function generate_run_id()
{
	$run_id = time()."_".rand();

	return $run_id;
}

function get_log_from()
{
	global $argv;

	if (php_sapi_name() == "cli") {
		$log_from = (isset($argv[0])) ? $argv[0] : null;
	} else {
		$log_from = $_SERVER["SCRIPT_NAME"];
	}

	return $log_from;
}

function get_log_type($log_from=null)
{
	if (strpos($log_from, "index.php") || strpos($log_from, "main.php"))
		$log_type = "interface_ansql";
	elseif (strpos($log_from, "api.php"))
		$log_type = "api_ansql";
	else {
		$log_from = explode("/",$log_from);
		$log_type = end($log_from);
		$log_type = str_replace(".php", "", $log_type);
	}

	return $log_type;
}

/**
 * Function used to add logs to the database.
 * @global type $log_db_conn
 * @global type $log_performer_info
 * @param string $msg The log to be added to the database.
 * @param mixed(array|string) $additional_log_type The log type.
 *		Empty array - default type will be associated, it will be calculated using log from: if origin is index or main, log type is 'interface_ansql'; if log from is api.php, log type is 'api_ansql'; otrherwisw log type will be equal to log from.
 *		Array with elements - additionally to the default types, the one from the array will be added separated by comma
 *		String - only the type specified will be associated, default type will be overwritten
 * @param string $tag The log type if exists: query, output, cron...etc. This usually is taken from the debug message tag.
 */
function add_db_log($msg, $additional_log_type = array(), $tag = '')
{
	global $log_db_conn,$run_id,$log_performer_info;

	if (!isset($log_db_conn))
              $log_db_conn = connect_database_log();

	if ($log_db_conn == false) {
		return;
	}

	if (!isset($run_id)) {
		error_log("Missing run_id value.");
		Debug::trigger_report('critical', "Missing run_id value.");
	}

	$msg = str_replace("\n", "<br>", $msg);
	$string = mysqli_real_escape_string($log_db_conn, $msg);
	$log_from = get_log_from();
	if (!is_array($additional_log_type))
		$log_type = $additional_log_type;
	else {
		$orig_log_type = get_log_type($log_from);
		$additional_log_type[] = $orig_log_type;
		$log_type = implode(",", $additional_log_type);
	}
	$performer_id = (isset($_SESSION["user_id"])) ? $_SESSION["user_id"] : "";
	$performer_param = (isset($log_performer_info["performer"])) ? $log_performer_info["performer"] : "username";
	$performer = (isset($_SESSION[$performer_param])) ? $_SESSION[$performer_param] : "";
	$session_id = session_id();
	$query = "INSERT INTO logs (date, log_tag, log_type, log_from, log, performer_id, performer, run_id, session_id) VALUES (now(), '$tag', '$log_type', '$log_from', '$string', '$performer_id', '$performer', '$run_id', '$session_id')";
	$result = mysqli_query($log_db_conn, $query);
	if (!$result) {
		error_log("Couldn't insert log to the database: " . mysqli_error($log_db_conn));
		Debug::trigger_report('critical', "Couldn't insert log to the database: " . mysqli_error($log_db_conn));
	}
}
?>

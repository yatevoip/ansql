<?php
/**
 * lib.php
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

require_once("debug.php");

config_globals_from_session();

global $module, $method, $action, $vm_base, $limit, $db_true, $db_false, $limit, $page, $system_standard_timezone;

/**
 *  Include the classes for database objects.
 *  @param $path String. Path to the files to be included. 
 */ 
function include_classes($path='')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	$classes_dirs = array("classes/", "ansql/default_classes");

	for ($i=0; $i<count($classes_dirs); $i++) {
		if (!is_dir($path.$classes_dirs[$i]))
			continue;
		$handle = opendir($path.$classes_dirs[$i]);

		while (false !== ($file = readdir($handle))) {
			if (substr($file,-4) != '.php')
				continue;
			else {
				if ($classes_dirs[$i] == "ansql/default_classes") {
					$file_name = substr($file,0,strlen($file)-4);
					global ${"custom_$file_name"};
					if (isset(${"custom_$file_name"}) && ${"custom_$file_name"})
						continue;
				}
				require_once($path.$classes_dirs[$i]."/$file");
			}
		}
	}
}

/**
 * Implementation of stripos function if it does not exist.
 * Find the position of the first occurrence of a case-insensitive substring in a string.
 */ 
if (!function_exists("stripos")) {
	// PHP 4 does not define stripos
	function stripos($haystack,$needle,$offset=0)
	{
		return strpos(strtolower($haystack),strtolower($needle),$offset);
	}
}

/**
 * Implementation of array_column function if it does not exist.
 * Return the values from a single column in the input array
 */ 
if (!function_exists("array_column")) {
	// PHP <5.5 does not define array_column
	function array_column($input, $column_key, $index_key=null) {
		Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

		$err_mess="";
		if ($index_key!==null || $column_key===null)
			$err_mess = "This functionality was not yet implemented for your php version. The function does not support reindexing arrays or returning complete objects or arrays yet.";
		else if (!is_array($input))
			$err_mess = "Parameter input must be an array.";
		else if (!ctype_digit(strval($column_key)) && !is_string($column_key))
			$err_mess = "Parameter column_key must be either a string or an integer. ";

		if ($err_mess!="") {
			errormess("array_column(): Please contact an administrator." . $err_mess,"no");
			Debug::trigger_report ("critical", $err_mess);
			return array();
		}
		
		$result = array();
		foreach ($input as $elem) {
			if (is_object($elem)) {
				$err_mess = "This functionality was not yet implemented for your php version. The parameter input array must not contain elements of type object.";
				errormess("array_column(): Please contact an administrator." . $err_mess, "no");
				Debug::trigger_report ("critical", $err_mess);
				return array();
			}
			if (!isset($elem[$column_key]) || !is_array($elem))
				continue;
			$result[] = $elem[$column_key];
		}
		return $result;
	}
}

escape_page_params();

if (!isset($system_standard_timezone))
	$system_standard_timezone = "GMT".substr(date("O"),0,3);

/**
 * Establish the name of the default function to be called using some predefind criteria.
 * @param $module String. The Module defined for each project. 
 * @param $method String. The Method associated to the Module.
 * @param $action String. The action associated with a method.
 * @param $call String. The name of the default function to be called.
 * @return string of type $call
 */ 
function get_default_function()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $module, $method, $action;

	Debug::debug_message(__FUNCTION__, "module:".$module." | method:".$method." | action:".$action);
	
	if (!$method)
		$method = $module;
	if (substr($method,0,4) == "add_")
		$method = str_replace("add_","edit_",$method);
	if ($action)
		$call = $method.'_'.$action;
	else
		$call = $method;
	return $call;
}

/**
 * Test a given Path.
 * @param $path String. 
 * @return 403 Forbidden page only if $path matches a regular expression
 */ 
function testpath($path)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (preg_match("/[^A-Za-z0-9_]/",$path, $matches)) {
 		// Client tried to hack around the path naming rules - ALERT!
		Debug::trigger_report('operational', "Preg_match function match path: ".print_r($matches)." session: ".print_r($_SESSION,true));
 		forbidden();
 	}
}

/**
 * Send a raw HTTP header with 403 Forbidden. Clears the session data. 
 * Displays a page with Forbidden message.
 * Terminates the current script.
 */ 
function forbidden()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	header("403 Forbidden");
	session_unset();
	print '<html><body style="color:red">Forbidden</body></html>';
	exit();
}

/**
 * Builds the HTML <form> tag with all possible attributes:
 * @param $action String. The action of the FORM
 * @param $method String. Allowed values: post|get. Defaults to 'post'.
 * @param $allow_upload Bool. If true allow the upload of files. Defaults to false.
 * @param $form_name String. Fill the attribute name of the FORM. 
 * Defaults to global variable $module or 'current_form' if $module is not set or null
 * @param $class String. Fill the attribute class. No default value set.
 */ 
function start_form($action = NULL, $method = "post", $allow_upload = false, $form_name = NULL, $class = NULL, $javascript = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	global $module;

	if (!$method)
		$method = "post";
	$form = (!$module) ? "current_form" : $module;
	if (!$form_name)
		$form_name = $form;
	if (!$action) {
		if (isset($_SESSION["main"]))
			$action = $_SESSION["main"];
		else
			$action = "index.php";
	}
	
	?><form action="<?php print $action;?>" name="<?php print $form_name;?>" id="<?php print $form_name;?>" <?php if ($class) print "class=\"$class\"";?> method="<?php print $method;?>" <?php if($allow_upload) print 'enctype="multipart/form-data"';?><?php if ($javascript)	print $javascript;?>><?php
}

/**
 * Ends a HTML FORM tag.
 */ 
function end_form()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	?></form><?php
}

/**
 * Displays a given text as a note.
 * @param $note String Contains the note text.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function note($note, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	
	$note = ($encode) ? htmlentities($note) : $note;
	print 'Note! '.$note.'<br/>';
}

/**
 * Displays an error note with a predefined css
 * @param $text String The message to be displayed.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function errornote($text, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	
	$text = ($encode) ? htmlentities($text) : $text;
	print "<br/><font color=\"red\" style=\"font-weight:bold;\" > Error!</font> <font style=\"font-weight:bold;\">".$text."</font><br/>";
}

/**
 * Displays a given text.
 * @param $text String The text to be displayed
 * @param $path String The path to use in link 
 * @param $return_text the link to return to requested Path
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function message($text, $path=NULL, $return_text="Go back to application", $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method;

	$text = ($encode) ? htmlentities($text) : $text;
	
	print '<div class="notice">'."\n";
	print $text."\n";

	if ($path == 'no') {
		print '</div>';
		return;
	}

	link_to_main_page($path, $return_text);

	print '</div>';
}


/**
 * Displays a given text.
 * @param $text String The text to be displayed
 * @param $path String The path to use in link. No link provided if value is "no"
 * @param $return_text The link to return to requested Path
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
  * @param $details String Extra details to be displayed after click on "More details" link
  * @param $more_details_id String If provided this will be the id of the html container holding the More Details text
  * @param $extra_css String. Css class. If provided this css class will be aplied on the entire warning box
  * @param $print_text Bool. If true prints the warning directly. Otherwise returns the html code for the warning message.
  * @return string if $print_text is false
 */
function warning_mess($text, $path=NULL, $return_text="Go back to application", $encode=true, $details="", $more_details_id="", $extra_css="", $print_text = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method, $more_details_counter;

	$text = ($encode) ? htmlentities($text) : $text;
	
	$message =  '<div class="warning '. $extra_css.'">'."\n";
	$message .=  '<div class="hold_err_mess">';
	$message .=  $text."\n";
	if ($details) {
		if (!$more_details_id) {
			$more_details_counter = ($more_details_counter===null) ? 0 : $more_details_counter+1;
			$more_details_id = "error_details".$more_details_counter;
		}
		$message .=  ' <a class="llink" onclick="show_hide(\''.$more_details_id.'\');">More&nbsp;details<br/></a>';
		$message .=  '<div id="'.$more_details_id.'" class="error_details" style="display: none;">';
		$message .=  $details;
		$message .=  '</div>';
	}
	$message .=  '</div>';
	if ($path == 'no') {
		$message .=  '</div>';
		if (!$print_text)
			return $message;
			
		print $message;
		return;
	}

	$message .=  " " . link_to_main_page($path, $return_text, false);

	$message .=  '</div>';
	
	if (!$print_text)
		return $message;
	
	print $message;	
}

/**
 * Wrapper function for warning_mess()
 * @param $text The text to be displayed
 * @param $details String Extra details to be displayed after click on "More details" link
 * @param $extra_css String. Css class. If provided this css class will be aplied on the entire warning box
 * @param $print_text Bool. If true prints the warning directly. Otherwise returns the html code for the warning message.
 * @param $path String The path to use in link.  No link provided if value is "no" 
 * @param $return_text the link to return to requested Path
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 * @param $more_details_id String If provided this will be the id of the html container holding the More Details text
 * @return string if $print_text is false
 */
function warning_mess_with_details($text, $details, $extra_css="", $print_text = true, $path="no", $return_text="Go back to application", $encode=true, $more_details_id="")
{
	return warning_mess($text, $path, $return_text, $encode, $details, $more_details_id, $extra_css, $print_text);
}

/**
 * Displays a given text with a specific css for errors
 * @param $text String The text to be displayed
 * @param $path String The path to use in link.  No link provided if value is "no"
 * @param $return_text the link to return to requested Path
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 * @param $details String Extra details to be displayed after click on "More details" link
 * @param $more_details_id String If provided this will be the id of the html container holding the More Details text
 * @param $extra_css String. Css class. If provided this css class will be aplied on the entire error box
 * @param $print_text Bool. If true prints the error directly. Otherwise returns the html code for the error message.
 * @return string if $print_text is false
 */
function errormess($text, $path=NULL, $return_text="Go back to application", $encode=true, $details="", $more_details_id="", $extra_css="", $print_text = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $more_details_counter;

	$text = ($encode) ? htmlentities($text) : $text;

	$message =  '<div class="notice error '. $extra_css.'">'."\n";
	$message .=  '<div class="hold_err_mess">';
	$message .=  "<font class=\"error\"> Error!</font>"."\n";
	$message .=  "<font style=\"font-weight:bold;\">". $text ."</font>"."\n";
	if ($details) {
		if (!$more_details_id) {
			$more_details_counter = ($more_details_counter===null) ? 0 : $more_details_counter+1;
			$more_details_id = "error_details".$more_details_counter;
		}
		$message .=  ' <a class="llink" onclick="show_hide(\''.$more_details_id.'\');">More&nbsp;details<br/></a>';
		$message .=  '<div id="'.$more_details_id.'" class="error_details" style="display: none;">';
		$message .=  $details;
		$message .=  '</div>';
	}
	$message .=  '</div>';
	if ($path == 'no') {
		$message .=  '</div>';
		if (!$print_text)
			return $message;
			
		print $message;
		return;
	}

	$message .= " " . link_to_main_page($path, $return_text, false);
	
	$message .= '</div>';
	
	if (!$print_text)
		return $message;
	
	print $message;		
}

/**
 * Wrapper function for errormess()
 * @param type $text String The text to be displayed
 * @param type $details String Extra details to be displayed after click on "More details" link
 * @param type $extra_css String. Css class. If provided this css class will be aplied on the entire error box
 * @param type $print_text Bool. If true prints the error directly. Otherwise returns the html code for the error message.
 * @param type $path String The path to use in link.  No link provided if value is "no"
 * @param type $return_text the link to return to requested Path
 * @param type $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 * @param $details String Extra details to be displayed after click on "More details" link
 * @param $more_details_id String If provided this will be the id of the html container holding the More Details text
 * @param $extra_css String. Css class. If provided this css class will be aplied on the entire error box
 * @param $print_text Bool. If true prints the error directly. Otherwise returns the html code for the error message.
 * @return String if $print_text is false
 */
function error_with_details($text, $details, $extra_css="", $print_text = true, $path="no", $return_text="Go back to application", $encode=true, $more_details_id="")
{
	return errormess($text, $path, $return_text, $encode, $details, $more_details_id, $extra_css, $print_text);
}

/**
 * Prints a message as a notice or an error type message and calls a function 
 * @param $message String The message to be displayed.
 * @param $next_cb Callable/String. Setting it to 'no' stops the performing of the callback.  
 * @param $no_error Boolean If is true a message id displayed else an error type message is displayed. Defaults to true.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 * @param $details String Extra details to be displayed after click on "More details" link
 * @param $more_details_id String If provided this will be the id of the html container holding the More Details text
 * @param $extra_css String. Css class. If provided this css class will be aplied on the entire notice box
 * @param $print_text Bool. If true prints the notice directly. Otherwise returns the html with the message.
 * @return string if $print_text is false
 */
function notice($message, $next_cb=NULL, $no_error=true, $encode=true, $details="", $more_details_id="", $extra_css="", $print_text=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $more_details_counter;
	
	if (!$next_cb)
		$next_cb = $module;
	
	$message = ($encode) ? htmlentities($message) : $message;

	$notice = "";
	if ($no_error)
		$notice = '<div class="notice '.$extra_css.'">'.$message;
	else
		$notice = '<div class="notice error '.$extra_css.'"><font class="error">Error! </font>'.$message;

	if ($details) {
		if (!$more_details_id) {
			$more_details_counter = ($more_details_counter===null) ? 0 : $more_details_counter+1;
			$more_details_id = "error_details".$more_details_counter;
		}
		$notice .= ' <a class="llink" onclick="show_hide(\''.$more_details_id.'\');">More&nbsp;details<br/></a>';
		$notice .= '<div id="'.$more_details_id.'" class="error_details" style="display: none;">';
		$notice .= $details;
		$notice .= '</div>';
	}
	
	$notice .= "</div>";

	if (!$print_text)
		return $notice;

	print $notice;
	
	if ($next_cb != "no" && is_callable($next_cb))
		call_user_func($next_cb);
}

/**
 * Displays a text with a bold font style set.
 * @param $text String The message to be displayed.
 * @param $encode Bool True to use htmlentities on message before displaying, false for not using htmlentities.
 */ 
function plainmessage($text, $encode=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$text = ($encode) ? htmlentities($text) : $text;
	print "<br/><font style=\"font-weight:bold;\">".$text."</font><br/><br/>";
}

/**
 * Displays a message or an error message depending on the data given in array
 * @param $res Array Contains on key 0:  true/false  
 * and on key 1: the message to be displayed 
 */ 
function notify($res)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $path;

	if ($res[0])
		message($res[1],$path);
	else
		errormess($res[1],$path);
}


/**
 * Displays a specific build link for application
 */ 
function link_to_main_page($path, $return_text, $print_text = true)
{
	global $module;

	if (isset($_SESSION["main"]))
		$link = $_SESSION["main"];
	else
		$link = "main.php";
	$link .=  "?module=".$module;

	if ($path)
		$link .= "&method=".$path;

	if ($print_text)
		print '<a class="information" href="'.$link.'">'.$return_text.'</a>';
	else
		return '<a class="information" href="'.$link.'">'.$return_text.'</a>';
}

/**
 * Escape the HTTP Request variables
 */ 
function escape_page_params()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	foreach ($_POST as $param=>$value) {
		if (is_array($value)) {
			foreach ($value as $name=>$val) {
				// not necesary because implementation of html_quotes_escape was changed
				//$val = htmlspecialchars_decode($val, ENT_COMPAT);
				$_POST[$param][$name] = escape_page_param($val);
			}
		} else {
			// not necesary because implementation of html_quotes_escape was changed
			//$value = htmlspecialchars_decode($value, ENT_COMPAT);
			$_POST[$param] = escape_page_param($value);
		}
	}
	
	foreach ($_GET as $param=>$value) {
		$_GET[$param] = escape_page_param($value);
	}
	foreach ($_REQUEST as $param=>$value) {
		$_REQUEST[$param] = escape_page_param($value);
	}
}

/**
 * Convert all applicable characters to HTML entities for a value or an array of values
 * @param $value String / Array
 * @return the modified $value
 */ 
function escape_page_param($value)
{
	global $htmlentities_onall;
	global $use_urldecode;
	
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");
	Debug::xdebug("paranoid","Global in ".__FUNCTION__.": htmlentities_onall=$htmlentities_onall,use_urldecode=$use_urldecode");
	
	if (!isset($htmlentities_onall))
		$htmlentities_onall = true;
	if (!isset($use_urldecode))
		$use_urldecode = false;
	
	if ($htmlentities_onall) {
		if (!is_array($value))
			$value = htmlentities($value);
		else {
			foreach ($value as $index=>$val)
				$value[$index] = htmlentities($val);
		}
	}
	
	if ($use_urldecode) {
		if (!is_array($value))
			$value = urldecode($value);
		else {
			foreach ($value as $index=>$val)
				$value[$index] = urldecode($val);
		}
	}
	
	return $value;
}

/**
 * Return the $_GET OR $_POST value of a given parameter
 * @param  $param String 
 * @return the value of the $param set in $_GET or $_POST
 * or NULL if is not set or if is a specific sql abreviation used in queries
 */ 
function getparam($param,$escape = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");
	global $no_trim;

	if (!isset($no_trim))
		$no_trim = false;

	$ret = NULL;
	if (isset($_POST[$param]))
		$ret = $_POST[$param];
	elseif (isset($_GET[$param]))
		$ret = $_GET[$param];
	else 
		return NULL;
	if (is_array($ret)) {
		foreach($ret as $index => $value) {
			if ($no_trim)
				$ret[$index] = escape_sql_param($ret[$index]);
			else
				$ret[$index] = escape_sql_param(trim($ret[$index]));
		}
		return $ret;
	}
	if ($no_trim)
		$ret = escape_sql_param($ret);
	else
		$ret = escape_sql_param(trim($ret));
	return $ret;
}

/**
 * Return NULL if the value of a given param is specific to SQL abreviations
 * or the value of the param
 */ 
function escape_sql_param($ret)
{
	if (substr($ret,0,6) == "__sql_")
		$ret = NULL; 
	if ($ret == "__empty")
		$ret = NULL;
	if ($ret == "__non_empty" || $ret == "__not_empty")
		$ret = NULL;
	if (substr($ret,0,6) == "__LIKE")
		$ret = NULL;
	if (substr($ret,0,10) == "__NOT LIKE")
		$ret = NULL;
	return $ret;
}

/**
 * Returns the new string with "_" where were spaces.
 * @param $value String
 */ 
function killspaces($value)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return str_replace(' ','_',$value);
}

/**
 * Verifies if a string is numeric.
 * @param $num String the number to be checked
 * @param $very_big Bool if true verifies if every digit is numeric 
 * @return NULL if given string is not numeric.
 */ 
function Numerify($num, $very_big = false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if ($num == '0') 
		$num = '0';
	if ($very_big) {
		for($i=0; $i<strlen($num); $i++) {
			if(!is_numeric($num[$i]))
				return "NULL";
		}
	} else {
		if (!is_numeric($num) && strlen($num)) 
			$num = "NULL";
	}
	return $num;
}

/**
 * Build a full date string from parts
 * @return false on failure, true on empty
 */ 
function dateCheck($year,$month,$day,$hour,$end)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if ("$year$month$day" == "") {
		if ($hour == "")
			return true;
		if (($hour<0) || ($hour>23))
			return false;
		$hour = sprintf(" %02u:%02u:%02u",$hour,$end,$end);
		return gmdate("Y-m-d") . $hour;
	}
	if (!($year && $month && $day))
		return false;
	if ($hour == "")
		$hour = $end ? 23 : 0;
	if (!(is_numeric($year) && is_numeric($month) && is_numeric($day) && is_numeric($hour)))
		return false;
	if (($year<2000) || ($month<1) || ($month>12) || ($day<1) || ($day>31) || ($hour<0) || ($hour>23))
		return false;
	return sprintf("%04u-%02u-%02u %02u:%02u:%02u",$year,$month,$day,$hour,$end,$end);
}

/**
 * Builds link from the parameters from the current REQUEST
 * @param $exclude_params Array. Parameters to be excluded from built link
 * @param $additional_url_elements Array. Parameters required into the built link
 * @return String. The link from the current $_REQUEST
 */
function build_link_request($exclude_params=array(), $additional_url_elements=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method, $action;

	$link = (isset($_SESSION["main"]) && strlen($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
	$link .= "?";
	foreach ($_REQUEST as $param=>$value) {

		if ($param == "page" || 
			$param == "PHPSESSID" ||
			($param == "action" && $action) ||
			($param == "method" && $method) || 
			($param == "module" && $module) ||
			in_array($param,$exclude_params) || 
			(!is_array($value) && !strlen($value))
		)
		continue;

		if (substr($link,-1) != "?")
			$link .= "&";

		if (is_array($additional_url_elements) && count($additional_url_elements)) {
			foreach ($additional_url_elements as $k=>$element_name)
				if ($element_name == $param)
					$link .= "$param=".urlencode($value);
		} else {
			if (!is_array($value)) 
				$link .= "$param=".urlencode($value);
			else 
				foreach ($value as $arr_val) 
					$link .= "$param"."[]=".$arr_val;
		}
	}

	if (substr($link,-1) != "?")
		$link .= "&";
	if ($module)
		$link .= "module=$module";
	if ($method)
		$link .= "&method=$method";
	if ($action) {
		$call = get_default_function();
		if (function_exists($call))
			$link .= "&action=$action";
	}
	return $link;
}

/**
 * Displays number on page that are links which will
 * make a reload of page with a new limit request
 * @param $total Integer contains the number of items
 * @param $nrs Array contains the number of items to be displayed
 * This function was adapted to work also for the old version when $total didn't exist
 * Old function argument: items_on_page($nrs = array(20,50,100), $additional_url_elements=null)
 */ 
function items_on_page($total = null, $nrs = array(20,50,100), $additional_url_elements = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $limit;
	
	//Detect if old argument is used
	//Below are the cases where the old argument is used
	//1. First parameter is an array -> Ex: items_on_page(array(60,120,240));
	//2. First parameter is null and second parameter is an array and first element from array is string -> Ex: items_on_page(null, array("performer","operation"));
	if (is_array($total) || (is_null($total) && is_array($nrs) && count($nrs) && is_string($nrs[0]))) {
		$additional_url_elements = $nrs;
		$nrs = $total;
		$total = null;
	}

	if (!$nrs)
		$nrs = array(20,50,100);
	
	//Force total to integer
	$total = (is_numeric($total)) ? intval($total) : null;
	
	//Display limit links only if $total is bigger than the first limit
	if (is_int($total) && $total<=$nrs[0])
		return;

	if ($additional_url_elements)
		$additional_url_elements = array_merge($additional_url_elements, array("total"));
	$link = build_link_request(array("limit"), $additional_url_elements);
	
	print "<div class=\"items_on_page\">";
	for($i=0; $i<count($nrs); $i++)
	{
		$option = $link."&limit=".$nrs[$i];
		if ($i>0)
			print '|';
		print '&nbsp;<a class="pagelink';
		if ($nrs[$i]==$limit)
			print " selected_pagelink";
		print '" href="'.$option.'">'.$nrs[$i].'</a>&nbsp;';
	}
	print "</div>";
}

/**
 * Builds and prints pagination links. Ex: 1 2 3 >| or |< 1 2 3 4 5. Takes into account the total number of objects and limit of items to display on page
 * @global string $go_to_prev_page_link. Holds previous page link if being on exception case** / Empty if correct page is displayed
 *					(**exception case: if the only object from last page is deleted $page will be equal with the total number of objects therefore that page will still be displayed)
 *					Used in table() / tableOfObjects() to load content from previous page if it was detected that table would be displayed without content (not applied if being on first page and deleting the only object from table)
 * @param $total Integer. Total number of entities
 * @param $additional_url_elements Array. Additional elements to add in links beside default ones(module, method, page, total)
 * Ex: array("status")
 * @param $jump_to Boolean. Default false. If true, 'jump to' input is displayed.
 * @param $div_css String. If value is given, <center> tag won't be used, and specified css class will be used instead of the default one 'pages'.
 */ 
function pages($total = NULL, $additional_url_elements=array(), $jump_to=false, $div_css = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $limit, $page, $module, $method, $action, $go_to_prev_page_link;
	
	if (!$limit)
		$limit = 20;

	$link = isset($_SESSION["main"]) ? $_SESSION["main"] : "main.php";
	$link .= "?";
	$slink = $link;
	$page = 0;
	if (isset($_REQUEST["page"]))
		$page = $_REQUEST["page"];
	if (isset($_REQUEST["total"]))
		$total = $_REQUEST["total"];

	if (!is_array($additional_url_elements) || !count($additional_url_elements))
		$additional_url_elements = array("total");
	elseif (!in_array("total", $additional_url_elements))
		$additional_url_elements[] = "total";

	$link = build_link_request(array("limit"), $additional_url_elements);

	if (!$total)
		$total = 0;

	// fix page value (if the only object from last page is deleted $page would be equal with the total number of objects therfore that page would still be displayed)
	if ($page && $page == $total) {
		// fix page value so previous(correct) page and correct content will be displayed 
		$page = $page - $limit;
		//used in table() / tableOfObjects() functions to load the previous page if no content for page
		$go_to_prev_page_link = $link."&page=".$page;
	}

	if ($total <= $limit)  
		return; 

	$pages = floor($total/$limit);
	if ($div_css) {
		print '<div class="'.$div_css.'">';
	} else {
		print '<center>';
		print '<div class="pages">';
	}
	if ($page != 0) {
		/* jump to first page */
		print '<a class="pagelink" href="'.$link.'&page=0">|<</a>&nbsp;&nbsp;';

		/* jump back 5 pages */
		$prev5 = $page - 5*$limit;
		if ($prev5>0)
			print '<a class="pagelink" href="'.$link.'&page='.$prev5.'"><<</a>&nbsp;&nbsp;';

		/* jump to previous page */
		/*$prev_page = $page - $limit;
		print '<a class="pagelink" href="'.$link.'&page='.$prev_page.'"><</a>&nbsp;&nbsp;';*/

		$diff = floor(($total - ($page + $limit * 2))/$limit) * $limit;
		$sp = $page - $limit * 2;
		if ($diff < 0)
			$sp = $sp - abs($diff);
		while($sp<0)
			$sp += $limit;
		while($sp<$page) {
			$pg_nr = floor($sp/$limit) + 1;
			print '<a class="pagelink" href="'.$link.'&page='.$sp.'">'.$pg_nr.'</a>&nbsp;&nbsp;';
			$sp += $limit;
		}
	}
	$pg_nr = floor($page/$limit)+1;
	print '<font class="pagelink selected_pagelink" href="#">'.$pg_nr.'</font>&nbsp;&nbsp;';
	if (($page+$limit) < $total) {
		if($pg_nr >= 3)
			$stop_at = $pg_nr + 2;
		else
			$stop_at = $pg_nr + 5 - (floor($page/$limit)+1);

		$next_page = $page + $limit;
		while($next_page < $total && $pg_nr < $stop_at)	{
			$pg_nr++;
			print '<a class="pagelink" href="'.$link.'&page='.$next_page.'">'.$pg_nr.'</a>&nbsp;&nbsp;';
			$next_page += $limit;
		}

		/* jump to next page */
		/*$next_page = $page + $limit;
		if($next_page<$total)
			print '<a class="pagelink" href="'.$link.'&page='.$next_page.'">></a>&nbsp;&nbsp;';*/

		$next5 = $page + $limit*5;
		$last_page = floor($total/$limit) * $limit;
		if ($limit==1)
			$last_page = $total - 1;
		elseif (floor(($total/$limit))==$total/$limit)
			$last_page = floor(($total-1)/$limit) * $limit;

		/* jump 5 pages */
		if ($next5 < $last_page)
			print '<a class="pagelink" href="'.$link.'&page='.$next5.'">>></a>&nbsp;&nbsp;';

		/* jump to last page */
		print '<a class="pagelink" href="'.$link.'&page='.$last_page.'">>|</a>&nbsp;&nbsp;';
	}
	if ($jump_to) {
		$no_pages = ceil($total/$limit);
		print '('.$no_pages.')&nbsp;&nbsp;<font class="jump_to_text">Jump to</font> &nbsp;<input class="jump_to_inp" type="text" name="jump_to" id="jump_to" onkeyup="if (event.keyCode==13) '."jump_to('".$link."', $limit, $no_pages);".' "/>&nbsp;'. "<input type=\"button\" name=\"go\" value=\"Go\" onclick=\"jump_to('".$link."', $limit, $no_pages);\" />";
	}
	print '</div>';
	if (!$div_css)
		print '</center>';
}

/**
 * Create links used for navigation between pages (previous / next)
 */ 
function navbuttons($params=array(),$class = "llink")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method, $page;

	$step = '';
	$link="main.php?module=$module&method=$method&";
	foreach($params as $key => $value) {
		if ($key=="page" || $key=="tot")
			continue;
		$link="$link$key=$value&";
		if ($key == "step")
			$step = $value;
	}
	$total = $params["tot"];
	
	if (!$step || $step == '')
		$step = 10;
?>
	<center>
	<table border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td class="navbuttons">
<?php 
				$vl = $page-$step;
				if ($vl >= 0) { ?>
					<font size="-1"><a class="<?php print $class;?>" href="<?php print ("$link"."page"."=$vl");?>">Previous</a>&nbsp;&nbsp;</font>
<?php 
				} 
?>
			</td>
			<td class="navbuttons">
				<font size="-3">
				<?php
				$r = $page/$step+1;
 				print ("$r");
				?>
				</font>
			</td>
			<td class="navbuttons">
			    <?php
			    $vl = $page+$step;
			    if ($vl < $total) { ?>
				&nbsp;&nbsp;<font size="-1"><a class="<?php print $class;?>" href="<?php print ("$link"."page"."=$vl");?>">Next</a> </font><?php
				} ?>
			</td>
		</tr>
	</table>
	</center>
<?php
}

/**
 * Validates an email address
 * @param $mail String contains the email address string
 * $return true if valid email and false if string is not in email pattern 
 */ 
function check_valid_mail($mail)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	$pattern = '/^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i';
	return preg_match($pattern,$mail);
}

/**
 * Prints hidden type inputs used in page: module, method, action and additional parameters if set
 * @param $action String contains the name of action in the page
 * @param $additional Array contains the parameters and their values to be set as input hidden 
 * @param $empty_page_param Bool. If true it will set 'method' and 'module' hidden fields to existing value if they don't appear in $additional. Defaults to false
 */ 
function addHidden($action=NULL, $additional = array(), $empty_page_params=false, $skip_params=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method,$module;

	if (($method || $empty_page_params) && !isset($additional["method"]))
		print "<input type=\"hidden\" name=\"method\" id=\"method\" value=".html_quotes_escape($method)." />\n";

	if (is_array($module) && !isset($additional["module"]))
		print "<input type=\"hidden\" name=\"module\" id=\"module\" value=\"$module[0]\" />\n";
	elseif (($module || $empty_page_params) && !isset($additional["module"]))
		print "<input type=\"hidden\" name=\"module\" id=\"module\" value=\"$module\" />\n";

	print "<input type=\"hidden\" name=\"action\" id=\"action\" value=\"$action\" />\n";

	if (is_array($additional) && count($additional))
		foreach($additional as $key=>$value) 
			print '<input type="hidden" id="' . $key . '" name="' . $key . '" value=' . html_quotes_escape($value) . '>';
		
	if (isset($_SESSION["previous_page"])) {
		foreach ($_SESSION["previous_page"] as $param=>$value)
			if (!isset($additional[$param]) && $param!="module" && $param!="method" && $param!="action" && !in_array($param,$skip_params))
				print '<input type="hidden" id="'.$param.'" name="' . $param . '" value=' . html_quotes_escape ($value) . '>';
	}
}

/**
 * Creates a form for editing an object
 * @param $object Object that will be edited or NULL if fields don't belong to an object
 * @param $fields Array of type field_name=>field_formats
 * Ex: $fields =  array(
		"username"=>array("display"=>"fixed", "compulsory"=>true), 
		// if index 0 in the array is not set then this field will correspond to variable username of @ref $object
		// the field will be marked with a *(compulsory)
		"description"=>array("display"=>"textarea", "comment"=>"short description"), 
		// "comment" is used for inserting a comment under the html element 
		"password"=>array("display"=>"password", "compulsory"=>"yes"), 
		"birthday"=>array("date", "display"=>"include_date"), 
		// will call function include_date
		"category"=>array($categories, "display"=>"select") 
		// $categories is an array like 
		// $categories = array(array("category_id"=>"4", "category"=>"Nature"), array("category_id"=>"5", "category"=>"Movies")); when select category 'Nature' $_POST["category"] will be 4
		// or $categories = array("Nature", "Movies");
		"sex"=>array($sex, "display"=>"radio") 
		// $sex = array("male","female","don't want to answer");
		"name" => array ("display"=>"text","error_icon"=>true)
		//adds an error icon to that field, default is set to "display:none"
	); 
 * instead of "compulsory", "requited" can be also used
 * possible values for "display" are "textarea", "password", "fileselect", "text", "select", "radio", "radios", "checkbox", "fixed"
 * If not specified display is "text"
 * If the field corresponds to a bool field in the object given display is ignored and display is set to "checkbox"
 * @param $title Text representing the title of the form
 * @param $submit Text representing the value of the submit button or Array of values that will appear as more submit buttons
 * @param $compulsory_notice Bool true for using default notice, Text representing a notice that will be printed under the form if other notice is desired or NULL or false for no notice
 * @param $no_reset When set to true the reset button won't be displayed, Default value is false
 * @param $css Name of the css to be used when generating the elements. Default value is 'edit'
 * @param $form_identifier Text. Used to make the current fields unique(Used when this function is called more than once inside the same form with fields that can have the same name when being displayed)
 * @param $td_width Array or by default NULL. If Array("left"=>$value_left, "right"=>$value_right), force the widths to the ones provided. $value_left could be 20px or 20%.
 * @param $hide_advanced Bool default false. When true advanced fields will be always hidden when displaying form
 * @param $scroll_top_advanced Bool. Default false. Scrool to top after advanced fields are displayed
 * @param $label String. Default NULL. The identifier for the fields. Used in warning message to user.
 * @param $field_note Array. Default empty array. The values to identify the note of each field. Used in display_pair() to view/edit notes.
 * Ex: $field_name = array(
 * 			"object_name" => "ps_profile",
 * 			"object_id" => 1,
 * 			"note-".$field_name => "This is the comment for this field."
 */
function editObject($object, $fields, $title, $submit="Submit", $compulsory_notice=NULL, $no_reset=false, $css=NULL, $form_identifier='', $td_width=NULL, $hide_advanced=false, $scroll_top_advanced=false, $label=NULL, $field_note=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");	

	
	global $level, $method, $only_cancel_button, $exceptions_only_cancel_button;

	if (!$css)
		$css = "edit";

	if ($level === "auditor") {
		$title = str_replace("Edit ", "View ", $title);
	}

	
	print '<table class="'.$css.'" cellspacing="0" cellpadding="0">';
	if ($title) {
		print '<tr class="'.$css.'">';
		print '<th class="'.$css.'" colspan="2">'.$title.'</th>';
		print '</tr>';
	}

	$show_advanced = false;
	$have_advanced = false;

	$custom_submit = array();

	//find if there are any fields marked as advanced that have a value(if so then all advanced fields should be displayed)
	foreach($fields as $field_name=>$field_format)
	{
		if(!isset($field_format["advanced"]))
			continue;
		if($field_format["advanced"] != true)
			continue;
		$have_advanced = true;
		if($object)
			$value = (!is_array($field_name) && isset($object->{$field_name})) ? $object->{$field_name} : NULL;
		else
			$value = NULL;
		if(isset($field_format["value"]))
			$value = $field_format["value"];
		if (!$object || !is_object($object))
			break;
		$variable = $object->variable($field_name);
		if((!$variable && $value && !$hide_advanced))
		{
			$show_advanced = true;
			break;
		}
		if(!$variable)
			continue;
		if (($value && $variable->_type!="bool" && !$hide_advanced) || ($variable->_type=="bool" && bool_value($value) && !$hide_advanced))
		{
			$show_advanced = true;
			break;
		}
	}

	//if found errors in advanced fields, display the fields
	foreach($fields as $field_name=>$field_format) {
		if(!isset($field_format["advanced"]))
			continue;
		if (isset($field_format["error"]) && $field_format["error"]===true) {
			$show_advanced = true;
			break;
		}
	}

	foreach($fields as $field_name=>$field_format) {
		if (!isset($field_format["display"]) || $field_format["display"]!="custom_submit")
			display_pair($field_name, $field_format, $object, $form_identifier, $css, $show_advanced, $td_width, null, $label, "off", format_field_note($field_note,$field_name));
		else
			$custom_submit[$field_name] = $field_format;
	}
	
	
	if (is_file("images/advanced.png"))
		$img_extension = "png";
	else
		$img_extension = "jpg";
	
	$scroll_top_advanced = ($scroll_top_advanced) ? "true" : "false";
	if($have_advanced && !$compulsory_notice)
	{
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' left_td advanced">&nbsp;</th>';
		print '<td class="'.$css.' left_right advanced"><img class="form_advanced" id="'.$form_identifier.'xadvanced"';
		if(!$show_advanced)
			print " src=\"images/advanced.$img_extension\" title=\"Show advanced fields\"";
		else
			print " src=\"images/basic.$img_extension\" title=\"Hide advanced fields\"";
		print ' onClick="advanced(\''.$form_identifier.'\','.$scroll_top_advanced.');"/></td></tr>';
	}
	if($compulsory_notice && $compulsory_notice !== true)
	{
		if($have_advanced) {
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' left_td" colspan="2">';
		print '<img class="advanced" id="'.$form_identifier.'advanced" ';
		if(!$show_advanced)
			print "src=\"images/advanced.$img_extension\" title=\"Show advanced fields\"";
		else
			print "src=\"images/basic.$img_extension\" title=\"Hide advanced fields\"";
		print ' onClick="advanced(\''.$form_identifier.'\','.$scroll_top_advanced.');"/>'.$compulsory_notice.'</td>';
		print '</tr>';
		}
	}elseif($compulsory_notice === true){
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' left_td" colspan="2">';
		if($have_advanced) {
		print '<img id="'.$form_identifier.'xadvanced"';
		if(!$show_advanced)
			print " class=\"advanced\" src=\"images/advanced.$img_extension\" title=\"Show advanced fields\"";
		else
			print " class=\"advanced\" src=\"images/basic.$img_extension\" title=\"Hide advanced fields\"";
		print ' onClick="advanced(\''.$form_identifier.'\','.$scroll_top_advanced.');"/>';
		}
		print 'Fields marked with <font class="compulsory">*</font> are required.</td>';
		print '</tr>';
	}

	if(is_array($custom_submit) && count($custom_submit))
		foreach($custom_submit as $field_name=>$field_format)
			display_pair($field_name, $field_format, $object, $form_identifier, $css, $show_advanced, $td_width, null, $label, "off", format_field_note($field_note,$field_name));

	// don't display submit buttons if $submit value is "no" or "no_submit"
	if (in_array($submit, array("no", "no_submit"))) {
		print '</table>';
		return;
	}
	
	// $submit value must be "only_cancel" if 
	// - current user level is allowed to see just cancel button
	// - and if used method is not an exception method for which this user level can see submit button
	if (
	  isset($only_cancel_button) && in_array($level, $only_cancel_button) 
	  && 
	  (!isset($exceptions_only_cancel_button) || isset($exceptions_only_cancel_button) && !in_array($method, $exceptions_only_cancel_button))
	  )
		$submit = "only_cancel";
	
	// display only cancel button if $submit value is "only_cancel"
	if ($submit == 'only_cancel')
	{
		print '<tr class="'.$css.'">';
		print '<td class="'.$css.' trailer" colspan="2">';
		$cancel_but = cancel_button($css);
		if ($cancel_but)
			print "&nbsp;&nbsp;$cancel_but";
		print '</td>';
		print '</tr>';
		print '</table>';
		return;
	}
	
	print '<tr class="'.$css.'">';
	print '<td class="'.$css.' trailer" colspan="2">';
	if(is_array($submit)) {
		for($i=0; $i<count($submit); $i++)
		{
			print '&nbsp;&nbsp;';
			print '<input class="'.$css.'" type="submit" name="'.$submit[$i].'" value='.html_quotes_escape($submit[$i]).'/>';
		}
	} else
		print '<input class="'.$css.'" type="submit" name="'.$submit.'" value='.html_quotes_escape($submit).'/>';
	if (!$no_reset) {
		print '&nbsp;&nbsp;<input class="'.$css.'" type="reset" value="Reset"/>';
		$cancel_but = cancel_button($css);
		if ($cancel_but)
			print "&nbsp;&nbsp;$cancel_but";
	}
	print '</td>';
	print '</tr>';
	print '</table>';
}

function format_field_note($field_note, $field_name)
{
	$fn = array();
	if (count($field_note)) {
		$fn = array("object_name"=>$field_note["object_name"], "object_id"=>$field_note["object_id"]);
		if (isset($field_note["note-".$field_name]))
			$fn["note"]	= $field_note["note-".$field_name];
	}
	return $fn;
}

function build_restart_warning($label,$restart_fields,$form_identifier=null, $agregate_notice=false)
{
	global $equipment_restart_list;

	// $restart_fields is the string with the field names that changed
	// $equipment_restart_list is the list with only the params that trigger the restart
	if (!count($equipment_restart_list))
		return;

	$restart = $glue = "";
	$restart_fields = explode(", ",$restart_fields);
	foreach ($equipment_restart_list as $fields_labeled=>$fieldname) {
		$fields_labeled = (!is_string($fields_labeled)) ? $fieldname : $fields_labeled;
		$identifier = explode("-", $fields_labeled);
		if ($identifier[0] != $label)
			continue;
		$field = str_replace($label."-","",$fields_labeled);
		if (in_array($field,$restart_fields)) {
			if ($form_identifier)
				$field = str_replace($form_identifier,"",$field);
			$field = (strlen($fieldname)) ? $fieldname : $field;
			$restart .= $glue . $field;
			$glue = ", ";
		}
	}
	/*Return the params name that require a equipment restart when they are changed, but not display the notice.*/
	if ($agregate_notice && strlen($restart))
		return $restart;
	
	if (strlen($restart))
		warning_mess("The equipment must be restarted because these parameters were changed: " . $restart, "no");
}

/** 
 * Creates an input cancel button with build onclick link
 * to return to the previous page
 */ 
function cancel_button($css="", $name="Cancel")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = null;
	if (isset($_SESSION["previous_page"])) {
		$link = $_SESSION["main"]."?";
		foreach ($_SESSION["previous_page"] as $param=>$value) {
			if (is_array($value) || is_object ($value))
				continue;
			$link.= "$param=".urlencode($value)."&";
		}
		$res = '<input class="'.$css.'" type="button" value="'.$name.'" onClick="document.location.href=\''.$link.'\'"/>';
	}
	return $res;
}

/**
 * Returns a string with the link build from previous page data session variable
 */ 
function cancel_params()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$link = "";
	foreach ($_SESSION["previous_page"] as $param=>$value)
		$link.= "$param=".urlencode($value)."&";
	return $link;
}

function html_quotes_escape($str)
{
	return '"'.str_replace('"',"&quot;",$str).'"';
	// htmlspecialchars is will run on $value display_pair and display_field
	//return '"'.htmlspecialchars($str, ENT_COMPAT).'"';
}

/**
 * Builds the HTML data for FORM
 */ 
function display_pair($field_name, $field_format, $object, $form_identifier, $css, $show_advanced, $td_width, $category_id=null, $label=null, $hidden_field_input = "off", $field_note=array())
{
	global $allow_code_comment, $use_comments_docs, $method, $add_selected_to_dropdown_if_missing;
	global $show_notes;
	
	// Set this variable to true in defaults.php to add error icon for each field
	global $use_error_icon;
	global $htmlentities_onall;
	
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	// if htmlentities wasn't run on all fields retrieved from database
	// by default we should run it here before printing value
	$htmlentities_onvalue = (!$htmlentities_onall) ? true : false;
		
	if (!isset($allow_code_comment))
		$allow_code_comment = true;
	if (!isset($use_comments_docs))
		$use_comments_docs = false;
	if (!isset($use_error_icon))
		$use_error_icon = false;

	$q_mark = false;
	if (isset($field_format["advanced"]))
		$have_advanced = true;

	$needs_trigger = false;
	if (isset($field_format["triggered_by"]) && strlen($field_format["triggered_by"]) !== 0)
		$needs_trigger = true;

	$display = (isset($field_format["display"])) ? $field_format["display"] : "text";

	if ($display=="raw") {
		display_custom_field($form_identifier.$field_name,$field_format["raw"]);
		return;
	}
	$form_display_elements = array("text","textarea","textarea-nonedit","tri_bool","select","mul_select","select_without_non_selected","radios","radio","checkbox","checkbox-readonly","password","file","text-nonedit","checkbox-group" );

	$def_val = (isset($field_format["value"])) ? $field_format["value"] : null;
	if (isset($field_format["form_val"])) {
		$value = $field_format["form_val"];
	} elseif ($object) {
		if (is_array($object)) {			
			if (!is_array($field_name) && count($object) && in_array($display, $form_display_elements))
				$value = (array_key_exists($field_name,$object)) ? $object[$field_name] : $def_val; 
			else
				// some of the fields contain fixed content set in value: objtitle, subtitle, fixed 
				$value = $def_val;
		} elseif (is_object($object)) {
			//$value = (!is_array($field_name) && isset($object->{$field_name})) ? $object->{$field_name} : NULL;
			$value = (!is_array($field_name) && property_exists($object,$field_name) && $object->isPopulated()) ? $object->{$field_name} : $def_val;
		} else
			$value = $def_val;
	} else {
		// this is an add type of form, there is no object/array with fields being edited
		$value = $def_val;
	}

	if (!is_array($value) && !strlen($value) && isset($field_format["cb_for_value"]) && isset($field_format["cb_for_value"]["name"]) && is_callable($field_format["cb_for_value"]["name"])) {
		if (is_array($field_format["cb_for_value"]) && count($field_format["cb_for_value"])==2) 
			$value = call_user_func_array($field_format["cb_for_value"]["name"],$field_format["cb_for_value"]["params"]);
		else
			$value = call_user_func($field_format["cb_for_value"]["name"]);
		// if $value is resulted by callign a function then that function is responsible for running htmlentities 
		$htmlentities_onvalue = false;
	}
	// For alaways visible notes
	if (!count($field_note))
		$field_note = (isset($field_format["field_note"])) ? $field_format["field_note"] : array();
	$column_name = (!isset($field_format["column_name"])) ? ucfirst(str_replace("_","&nbsp;",$field_name)) : $field_format["column_name"];
	if (isset($show_notes) && $show_notes && count($field_note)) {
		$note = isset($field_note["note"]) ? str_replace("\n","<br>",$field_note["note"]) : "";
		$hidden = "";
		if (!isset($field_note["note"]))
			$hidden = 'style="display:none;"';
		$note_css = 'class="'.$css.'"';
		if (isset($field_format["advanced"]))
			$note_css = 'class="'.$css.' advancedrow" style="display:none;" advanced="true"';
		if (!strlen($note_css))
			$note_css = $hidden;
		$td_css = (isset($field_format["custom_css_left"])) ? $field_format["custom_css_left"] : "";
		if (substr($td_css,0,15) == "indentation_css")
			$td_css = str_replace("indentation_css", "comment_indentation_css",$td_css);

		print '<tr id="tr_'.$form_identifier.'comment-'.$field_name.'" '.$note_css.'><td class="comment_field '.$td_css.'" id="'.$form_identifier.'comment-'.$field_name.'" colspan="2">'.$note;
		if (is_addon("font-awesome") && $note)
			print '&nbsp;&nbsp;<i class="fa fa-pencil pointer pencil_icon" aria-hidden="true" onClick="show_note(\''.$field_name.'\', \''.$field_note["object_name"].'\', \''.$field_note["object_id"].'\', \''.$column_name.'\',\''.$form_identifier.'\');"></i>';
		print '</td></tr>';
	}
	if (isset($field_note["note"])) {
		$field_format["javascript"] = (isset($field_format["javascript"])) ? $field_format["javascript"] : "";
		$field_format["javascript"] .= " onChange=note_reminder();";
	}

	print '<tr id="tr_'.$form_identifier.$field_name.'"';
//		if($needs_trigger == true)	
//			print 'name="'.$form_identifier.$field_name.'triggered'.$field_format["triggered_by"].'"';

	if (isset($field_format["error"]) && $field_format["error"]===true)
		$css .= " error_field";
	print ' class="'.$css;
	if (isset($field_format["tr_extra_css"]))
		print " ".$field_format["tr_extra_css"];
	if (isset($field_format["advanced"]))
	{
		print " advancedrow\"";
		if (!$show_advanced) {
			print ' style="display:none;" advanced="true" ';
			if (isset($field_format["triggered_by"]))
				print " trigger=\"true\" ";

		} elseif (isset($field_format["triggered_by"])){
			if ($needs_trigger)
				print ' style="display:none;" trigger=\"true\" ';
			else
				print ' style="display:table-row;" trigger=\"true\" ';
		} else
			print ' style="display:table-row;"';
	} elseif (isset($field_format["triggered_by"])) {
		print "\"";
		if ($needs_trigger)
			print ' style="display:none;" trigger=\"true\" ';
		else
			print ' style="display:table-row;" trigger=\"true\" ';
	} else
		print "\"";
	print '>';
	// if $var_name is an array we won't use it
	$var_name = (isset($field_format[0])) ? $field_format[0] : $field_name;

	if ($object && !is_array($object)) {
		$variable = (!is_array($var_name)) ? $object->variable($var_name) : NULL;
		if ($variable) {
			if ($variable->_type == "bool" && $display!="text" && $display!="tri_bool")
				$display = "checkbox";
		}
	}

	if ($display=="message" || $display=="objtitle" || $display=="custom_submit" || $display=="custom_field") {
		$custom_field_css = ($display=="custom_field") ? "custom_field" : 'double_column';

		$extra_css = (isset($field_format["extra_css"])) ? $field_format["extra_css"] : "";
		
		print '<td class="'.$css.' '.$custom_field_css. ' ' . $extra_css .'" colspan="2">';
		if ($display=="objtitle")
			print "<div class='objtitle'>$value</div>";
		else
			print $value;
		print '</td>';
		print '</tr>';
		return;
	}
	// this is used for fields that must always remian hidden (ex: module, method ..)
	// use trigger_by and not display:hidden - if you need to make a field hidden and then to use show/hide() JS functions to display/hide it
	if ($display=="hidden") {
		print '<input class="'.$css.'" type="'.$display.'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';		
		print " value=".html_quotes_escape($value);
		print " />";
		return;
	}
	
	print '<td class="'.$css.' left_td ';
	$ident = false;
	if (isset($field_format["custom_css_left"])) {
		print $field_format["custom_css_left"];
		if (substr($field_format["custom_css_left"],0,15)=="indentation_css")
			$ident = true;
	}
	print '"';
	if (isset($td_width["left"]))
		print ' style="width:'.$td_width["left"].'"';
	print '>';
	$close_div = false;
	if ($ident) {
		print "<div class=\"indentation_css\">";	
	}
	if (!isset($field_format["column_name"])) {
		$column_name = ucfirst(str_replace("_","&nbsp;",$field_name));
		print $column_name;
	} else {
		$parts = explode('<', $field_format["column_name"], 2);
		print ucfirst(str_replace(" ","&nbsp;",$parts[0]));
		$column_name = ucfirst(str_replace(" ","&nbsp;",$parts[0]));
		if (isset($parts[1])) {
			print "<" . $parts[1];
			$column_name .= "<" . $parts[1];
		}
	}
	if (isset($field_format["required"]))
		$field_format["compulsory"] = $field_format["required"];
	if (isset($field_format["object_required"]))
		$field_format["compulsory"] = $field_format["object_required"];
	if (isset($field_format["compulsory"]))
		if($field_format["compulsory"]===true || $field_format["compulsory"]=="yes" || bool_value($field_format["compulsory"]) || $field_format["compulsory"]=="true")
			print '<font class="compulsory">*</font>';

	if ($ident)
		print "</div>";
	// check if the error icon is set in fields
	if (isset($field_format["error_icon"])) {
		if ($field_format["error_icon"]===true || $field_format["error_icon"]=="yes" || bool_value($field_format["error_icon"]) || $field_format["error_icon"]=="true")
			print "<img src='images/error.png' class='field_error' id='err_$field_name' style='display:none;'>";
	} else {
		if ($use_error_icon)
			print "<img src='images/error.png' class='field_error' id='err_$field_name' style='display:none;'>";
	}
	print '&nbsp;</td>';
	print '<td class="'.$css.' right_td ';
	if (isset($field_format["custom_css_right"]))
		print $field_format["custom_css_right"];
	print '"';
	if (isset($td_width["right"]))
		print ' style="width:'.$td_width["right"].'"';
	print '>';

	$field_comment = (empty($field_format["no_comment"])) ? comment_field($field_format, $form_identifier, $field_name, $category_id, $display) : "";

	if ($htmlentities_onvalue && !isset($field_format["no_escape"]))
		$value = (!is_array($value)) ? htmlentities($value) : array_map("htmlentities",$value);

	if ($label)
		build_javascript_format($field_name, $field_format, $display, $label, $form_identifier);
	
	//add custom html before field input. Ex: $conf[$fieldname]["pre_extra_html"] = "html_desired";
	if (isset($field_format["pre_extra_html"]))
		print $field_format["pre_extra_html"];

	switch($display) {
		case "textarea":
		case "textarea-nonedit":
			$textarea_cols = 20;
			$textarea_rows = 5;
			if (isset($field_format["textarea_cols"]))
				$textarea_cols = $field_format["textarea_cols"];
			if (isset($field_format["textarea_rows"]))
				$textarea_rows = $field_format["textarea_rows"];
			if (isset($field_format["container_div"])) {
				$prefix = (isset($field_format["container_class_prefix"])) ? $field_format["container_class_prefix"] : "";
				print "<div class='".$prefix."container_textarea' id='container_".$form_identifier.$field_name."'>";
				print "<div class='".$prefix."backdrop' id='backdrop_".$form_identifier.$field_name."'>";
				print "<div class='".$prefix."highlights' id='highlights_".$form_identifier.$field_name."'></div>";
				print "</div>";
			}
			if (isset($field_format["extra_css"]))
				$css = $css." ".$field_format["extra_css"];
			print '<textarea class="'.$css.'" id="'.$form_identifier.$field_name.'" name="'.$form_identifier.$field_name.'" cols="'.$textarea_cols.'" rows="'.$textarea_rows.'" ';
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if ($display == "textarea-nonedit")
				print " readonly=''";
			print '>';
			print $value;
			print '</textarea>';
			if (isset($field_format["container_div"]))
				print "</div>";
			print $field_comment;
			break;
		
		case "tri_bool":
			Debug::debug_message("tri_bool", $field_format);
			$selected = tri_bool_value($value);
			Debug::debug_message("tri_bool", "display_pair for $field_name with received value=$value, type=".gettype($value)." , selected=$selected");
			
			$options = array( ""=>"Default", "on"=>"True", "off"=>"False");			
			$labels  = (isset($field_format["labels"]) && is_array($field_format["labels"])) ? $field_format["labels"] : array();
			
			print '<select class="'.$css.'" id="'.$form_identifier.$field_name.'" ';
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			print ' name="'.$form_identifier.$field_name.'"';
			print '>';
			
			foreach ($options as $val=>$label) {
				if (isset($labels[$val]))
					$label = $labels[$val];
				
				print "<option value=\"$val\"";
				if ($selected===$val)
					print " SELECTED";
				print ">$label</option>";
			}
			
			print '</select>'.$field_comment;
			break;
			
		case "select":
		case "mul_select":
		case "select_without_non_selected":
			
			if ($add_selected_to_dropdown_if_missing) {
				$is_selected = false;
				$arr_is_selected = array();
			}

			print '<select class="'.$css.'" id="'.$form_identifier.$field_name.'" ';
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if ($display == "mul_select")
				print ' multiple="multiple" size="5" name="'.$form_identifier.$field_name.'[]"';
			else
				print ' name="'.$form_identifier.$field_name.'"';
			
			//Added option to mark a dropdown as disabled. 
			if (isset($field_format["disabled_select"]) &&  ($field_format["disabled_select"]== true))
				print ' disabled';
			
			print '>';
			//if ($display != "mul_select" && $display != "select_without_non_selected")
			if ($display == "select")
				print '<option value="">Not selected</option>';

			// PREVIOUS implementation when only 0 key could be used for dropdown options
			// $options = (is_array($var_name)) ? $var_name : array();
			
			if ($display != "mul_select") {
				// try gettting it from value
				if ($value && is_array($value))
					$options = $value;
				elseif (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			} else {
				// try gettting it from value
				// !! DEFINE "selected" even if selected is not defined
				if ($value && is_array($value) && array_key_exists("selected",$value))
					$options = $value;
				elseif (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			}
			
			$selected = selected_option($display, $field_name, $field_format, $options, $object);
			
			foreach ($options as $var=>$opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				$css = (is_array($opt) && isset($opt["css"])) ? 'class="'.$opt["css"].'"' : "";

				// $opt = array("field"=>..., "field_id"=>...) 
				if (is_array($opt) && isset($opt[$field_name.'_id'])) {
					$optval = $field_name.'_id';
					$name = $field_name;

					// for extreme debug purposes  you might want to print val/type of each value and the selected value and data type
					//$printed = trim($opt[$name]).",".$selected.",". gettype($selected).",".$opt[$optval].",".gettype($opt[$optval]);
					$printed = trim($opt[$name]);
					if (substr($printed,0,4) == "<img") {
						$expl = explode(">",$printed);
						$printed = $expl[1];
						$jquery_title = " title=\"".str_replace("<img","",$expl[0])."\"";
					} else
						$jquery_title = '';
					
					//Added possibility to set the 'title' attribute to the dropdown options.
					if (isset($opt["title"]))
						$jquery_title = ' title="'.$opt["title"].'"';

					if (is_string($selected) && (string)$opt[$optval] === "$selected" || (is_array($selected) && in_array($opt[$optval],$selected))) {
						print '<option';
						print " value=".html_quotes_escape($opt[$optval]);
						print ' '.$css.' SELECTED ';
						if($opt[$optval] === "__disabled")
							print ' disabled="disabled"';
						print $jquery_title;
						print '>' . $printed . '</option>';
						if ($add_selected_to_dropdown_if_missing) {
							if (is_string($selected)) 
								$is_selected = true;
							elseif (is_array($selected)) 
								$arr_is_selected[] = $opt[$optval];
						}
					} else {
						print '<option';
						print " value=".html_quotes_escape($opt[$optval]);
						print ' '.$css;
						if($opt[$optval] === "__disabled")
							print ' disabled="disabled"';
						print $jquery_title;
						print '>' . $printed . '</option>';
					}
				} else {
					if (($opt == $selected && strlen($opt)==strlen($selected)) || (is_array($selected) && in_array($opt,$selected))) {
						print '<option '.$css.' SELECTED >' . $opt . '</option>';
						if ($add_selected_to_dropdown_if_missing) {
							if (is_string($selected))
								$is_selected = true;
							elseif (is_array($selected))
								$arr_is_selected[] = $opt;
						}
					} else {
						print '<option '.$css.'>' . $opt . '</option>';
					}
				}
			}

			if ($add_selected_to_dropdown_if_missing && ( (is_string($selected) && strlen($selected)) || (is_array($selected) && count($selected)) ) ) {
				if (is_string($selected) && !$is_selected) {
					print '<option '.$css.' SELECTED >' . $selected . '</option>';
				} elseif (is_array($selected)) {
					foreach ($selected as $k=>$selected_val) {
						if (!in_array($selected_val,$arr_is_selected) && strlen($selected_val))
							print '<option '.$css.' SELECTED >' . $selected_val . '</option>';
					}
				}
			}

			print '</select>'.$field_comment;

			if(isset($field_format["add_custom"]))
				print $field_format["add_custom"];
			
			if (isset($selected) && ($selected=="Custom" || $selected==array("Custom")) && isset($field_format["javascript"])) {
				autocall_js($field_name, $field_format, $form_identifier);
			}

			break;			
		case "radios":
		case "radio":
		case "radios-readonly":
			$options = (is_array($var_name)) ? $var_name : array();
			$selected = selected_option($display, $field_name, $field_format, $options, $object);
			if (isset($field_format["extra_css"])) {
				print '<span class="'.$field_format["extra_css"].'" id="container_'.$form_identifier.$field_name.'">';
			}
			foreach ($options as $var=>$opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				if (is_array($opt) && count($opt) == 2) {
					$optval = $field_name.'_id';
					$name = $field_name;
					$value = $opt[$optval];
					$name = $opt[$name];
				} else {
					$value = $opt;
					$name = $opt;
				}
				print '<input class="'.$css.'" type="radio" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'_'.strtolower(str_replace(" ", "_",$value)).'"';
				print " value=".html_quotes_escape($value);
				if ($value == $selected)
					print ' CHECKED ';
				if (isset($field_format["javascript"]))
					print $field_format["javascript"];
				if ($display=="radios-readonly")
					print " disabled=''";
				print '>' . $name . '&nbsp;&nbsp;';
			}
			if (isset($field_format["extra_css"]))
				print "</span>";
			print $field_comment;
			break;
		case "checkbox":
		case "checkbox-readonly":
			//Added possibility to not set "hidden_field" to a checkbox from framework. On this case, you have to set input type=hidden manually.
			if (isset($field_format["no_hidden"]))
				$hidden_field_input = "off";
			
			if ($hidden_field_input == "on") {
				print '<!-- Input type="hidden" has been set to all checkboxes. -->';
				print '<input class="'.$css.'" type="hidden" name="'.$form_identifier.$field_name.'" value="off">';
			}
			print '<input class="'.$css.'" type="checkbox" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if (isset($field_format["title"])){
				print(' title="'.$field_format["title"].'"');
			}
			
			if (bool_value($value) || $value=="on")
				print " CHECKED ";
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			else {
				$result_alert_message = display_alert_message($form_identifier.$field_name);
				if ($result_alert_message[0])
					print($result_alert_message[1]);
			}
			if ($display=="checkbox-readonly")
				print " disabled=''";
			print '/>'.$field_comment;
			break;
		case "checkbox-group":
			$options = (is_array($var_name)) ? $var_name : array();
			$selected = selected_option($display, $field_name, $field_format, $options, $object);	
			if (!is_array($selected))
				$selected = (array)$selected;
			
			$max = 3;
			if (isset($field_format["max_rows"]))
				$max = $field_format["max_rows"];
			else if (isset($field_format["max_cols"]))
				$max = $field_format["max_cols"];
			
			$col = 1;			
			$row = 1;
			$checkboxes = array();

			foreach ($options as $var => $opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				
				$checkboxes[$row][$col]["name"] = $form_identifier.$field_name.'[]';
				$checkboxes[$row][$col]["label"] = (is_array($opt)) ? $opt["label"] : $opt;
				$checkboxes[$row][$col]["value"] = (is_array($opt)) ? $opt["value"] : $opt;
				$checkboxes[$row][$col]["id"] = $form_identifier.$field_name.'_'.strtolower(str_replace(" ", "_",$checkboxes[$row][$col]["value"])).'_ck';
				$checkboxes[$row][$col]["selected"] = (in_array($checkboxes[$row][$col]["value"], $selected)) ? true : false;
				$checkboxes[$row][$col]["javascript"] = (isset($field_format["javascript"])) ? " ".$field_format["javascript"] : "";
				if (is_array($opt) && isset($opt["javascript"]))
					$checkboxes[$row][$col]["javascript"] = " ".$opt["javascript"];
				
				$checkboxes[$row][$col]["disabled"] = (is_array($opt) && isset($opt["disabled"]) && $opt["disabled"]) ? true : false;
				//if disabled_checkbox all checkboxes should be disabled
				if (isset($field_format["disabled_checkbox"]) &&  ($field_format["disabled_checkbox"]== true))
					$checkboxes[$row][$col]["disabled"] = true;
				$checkboxes[$row][$col]["css"] = (is_array($opt) && isset($opt["css"]) && $opt["css"]) ? " ".$opt["css"] : "";	
				
				if (isset($field_format["max_cols"])) {
					$col++;
					if ($col > $max) {
						$col=1;
						$row++;
					}
				} else {
					$row++;
					if ($row > $max) {
						$row=1;
						$col++;
					}
				}
			}
			$autocall = array();
			print '<table class="checkbox-group" id="'.$form_identifier.$field_name.'">';
			foreach ($checkboxes as $row => $content) {
				print '<tr class="checkbox-group">';
				foreach ($content as $col => $opt) {
					print '<td class="checkbox-group">';
					print '<input class="checkbox-group'. $opt["css"].'" type="checkbox" name="'.$opt["name"].'" id="'.$opt["id"].'"'.$opt["javascript"];
					print " value=".html_quotes_escape($opt["value"]);
					if ($opt["selected"])
						print " CHECKED ";
					if ($opt["disabled"])
						print " disabled=''";
					print  '/>';
					print '<label for="'.$opt["id"] . '"';
					if ($opt["css"])
						print ' class="' . $opt["css"] . '"' ;
					print '>'.$opt["label"].'</label>';
					print '</td>';
				}
				print '</tr>';
				if (is_array($selected) && in_array("Custom", $selected) && $opt["value"]=="Custom" && isset($opt["javascript"])) {
					$opt["js_autocall"] = true;
					$autocall = array($field_name, $opt, $form_identifier);
				}
			}
			print '</table>';
			print '<span class="checkbox-group">'.$field_comment.'</span>';
			
			if (!empty($autocall)) {
				call_user_func_array("autocall_js", $autocall);
			}
			break;
		case "text":
		case "password":
		case "file":
		case "text-nonedit":
		case "text-disabled":
			if (isset($field_format["extra_css"]))
				$css = $css." ".$field_format["extra_css"];
			print '<input  class="'.$css.'" type="'.($display=="text-nonedit"||$display=="text-disabled"?"text":$display).'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if ($display != "file" && $display != "password") {
				if (!is_array($value)) {
					print " value=".html_quotes_escape($value);
				}
				else {
					if (isset($field_format["selected"]))
						$selected = $field_format["selected"];
					elseif (isset($value["selected"]))
						$selected = $value["selected"];
					elseif (isset($value["SELECTED"]))
						$selected = $value["SELECTED"];
					else
						$selected = '';				
					print " value=".html_quotes_escape($selected);
				}
			}
			if (isset($field_format["placeholder"])) 
				print " placeholder='".$field_format["placeholder"]."'";
			if (isset($field_format["size"])) 
				print " size='".$field_format["size"]."'";
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if (isset($field_format["maxlength"]))
				print " maxlength='".$field_format["maxlength"]."'";
			if ($display == "text-nonedit")
				print " readonly=''";
			if ($display == "text-disabled")
				print " disabled=''";
			if (isset($field_format["autocomplete"]))
				print " autocomplete=\"".$field_format["autocomplete"]."\"";
			if (isset($field_format["title"]))
				print(' title="'.$field_format["title"].'"');

			print '>'.$field_comment;

			if ($display == 'file' && isset($field_format["file_example"]) && $field_format["file_example"] != "__no_example") {
				if (!is_array($field_format["file_example"]))
					print '<br/><br/>Example: <a class="'.$css.'" href="example/'.$field_format["file_example"].'">'.$field_format["file_example"].'</a><br/><br/>';
				else {
					print '<br/><br/>Examples (click to download):';
					print '<ul class="'.$css.'" id="examples_'.$form_identifier.$field_name.'">';
					
					if (isset($field_format["display_examples"]) && is_numeric($field_format["display_examples"])) 
						$max_show = $field_format["display_examples"];
					else 
						$max_show = count($field_format["file_example"]);
					$not_displayed_elem_ids = array();
					$i=1; 
					foreach ($field_format["file_example"] as $filename => $description) {
						if (is_numeric($filename)) {
							$filename = $description;
							$description = "";
						}
						$elem_id =  "examples_li_".$form_identifier.$field_name.$i;
						$display_type = "block"; 
						if ($max_show < $i) {
							$display_type = "none";
							$not_displayed_elem_ids[] = $elem_id;
						}				
						if (isset($field_format["set_download"]))
							print '<li style="display:'.$display_type.'" id="'.$elem_id.'"><a class="'.$css.'" href="'.$field_format["set_download"].'">'.$filename . '</a> '.$description.' </li>';
						else
							print '<li style="display:'.$display_type.'" id="'.$elem_id.'"><a class="'.$css.'" href="example/'.$filename.'">'.$filename . '</a> '.$description.' </li>';
						$i++;
					}
					if (count($not_displayed_elem_ids)) 
						print '<a class="'.$css.'" id="show_hide_file_examples" onclick="show_hide_file_examples([\''. implode("','", $not_displayed_elem_ids).'\'], \'show_hide_file_examples\')" href="#">Display more examples...</a>';
					print '</ul>';
				}
			}
			if (isset($field_format["extra_info"])) {
				if (!is_array($field_format["extra_info"]) && strlen($field_format["extra_info"])>0) {
					if ($display == 'file')
						print '<div class="extra_info_import_form">'.'Note! ' . $field_format["extra_info"].'</div>';
					else
						print '<div class="extra_info">'. $field_format["extra_info"].'</div>';
				} else if (is_array($field_format["extra_info"]) && count($field_format["extra_info"])>0){
					print '<div class="extra_info_import_form">'.'Note! <ul>';
					foreach ($field_format["extra_info"] as $extra_info)
						print '<li>' . $extra_info . '</li>';
					print '</ul>'.'</div>';
				}
			}
			if ($display == 'file' && !isset($field_format["file_example"])) 
				Debug::trigger_report('critical', "For input type file a file example must be given as parameter.");
			break;
		case "password-toggle":
		case "password-toggle-fa":
			print '<input  class="'.$css.'" type="password" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if (is_string($value))
				print " value=".html_quotes_escape($value);
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			if (isset($field_format["maxlength"]))
				print " maxlength='".$field_format["maxlength"]."'";
			if (isset($field_format["autocomplete"]))
				print " autocomplete=\"".$field_format["autocomplete"]."\"";
			print '>'.$field_comment;
			
			//To use toggle-password-fa make sure that your project has font-awsome
			if ($display==="password-toggle-fa" && is_addon("font-awesome")) {
				$toggle_style = "cursor:pointer; position:relative; z-index:2; margin-left:-30px; color:blue; font-size:15px;";
				print '<span id="'.$form_identifier.$field_name.'_toggle" style="'.$toggle_style.'" class="fa fa-eye-slash password-toggle-fa" onClick="display_password(this);"></span>';
			} else {
				print '<br><input id="'.$form_identifier.$field_name.'_toggle" class="password-toggle" type="checkbox" onclick="display_password(this);">';
				print '<label for="'.$form_identifier.$field_name.'_toggle">Show Password</label>';
			}
			break;
		case "button":
			if (isset($field_format["container_elem"]))
				print "<span class='".$css."' id='".$field_name."_span'>";
			print "<button class='".$css."' type='button' name='".$form_identifier.$field_name."' id='".$form_identifier.$field_name."'";
			if (isset($field_format["javascript"]))
				print $field_format["javascript"];
			print ">";
			if (isset($field_format["button_name"]))
				print $field_format["button_name"];
			else
				print "View/Change";
			print "</button>";
			if (isset($field_format["container_elem"]))
				print "</span>";
			print $field_comment;
			break;
		case "date_time":
//			value format:
//			$field_format["value"] = array(
//				"date"=>1979-12-31,
//				"time"=>23:59:59.999,
//				"timezone"=>Europe/Bucharest
//			);
			
			//~~~DATE INPUT~~~
			// date value ex: 1979-12-31
			
			print "<input type=\"date\" id=\"{$field_name}_date\" name=\"{$field_name}_date\"";
			
			if (isset($value["date"]))
				print " value=\"{$value["date"]}\"";
			
			if (isset($field_format["date_min"]))
				print " min=\"".$field_format["date_min"]."\"";
			
			if (isset($field_format["date_max"]))
				print " max=\"".$field_format["date_max"]."\"";
			
			if (isset($field_format["javascript"]["date"]))
				print " ".$field_format["javascript"]["date"];
			
			if (isset($field_format["javascript"]) && is_string($field_format["javascript"]))
				print " ".$field_format["javascript"];
			elseif (isset($field_format["javascript"]["all"]))
				print " ".$field_format["javascript"]["all"];
			
			
			print " >&nbsp;";
			
			//stop here display just date field if $field_format["no_time"]=true
			if (isset($field_format["no_time"]) && $field_format["no_time"]===true) 
				break;
			
			//~~~TIME INPUT~~~
			// time value format ex: 23:59:59.999
			
			print "<input type=\"time\" id=\"{$field_name}_time\" name=\"{$field_name}_time\" ";
			
			if (isset($value["date"]))
				print " value=\"{$value["time"]}\"";
				
			if (isset($field_format["time_format"])) {
				if ($field_format["time_format"]=="H:i:s")
					print " step=\"1\"";
				elseif ($field_format["time_format"]=="H:i:s.v")
					print " step=\"0.1\"";
			} elseif (isset($field_format["time_step"]))
					print " step=\"{$field_format["time_step"]}\"";
			
			if (isset($field_format["time_min"]))
				print " min=\"".$field_format["time_min"]."\"";
			
			if (isset($field_format["time_max"]))
				print " max=\"".$field_format["time_max"]."\"";
			
			if (isset($field_format["javascript"]["time"]))
				print " ".$field_format["javascript"]["time"];
			
			if (isset($field_format["javascript"]) && is_string($field_format["javascript"]))
				print " ".$field_format["javascript"];
			elseif (isset($field_format["javascript"]["all"]))
				print " ".$field_format["javascript"]["all"];
			
			print " >&nbsp;";
			
			//stop here display just date and time fields if $field_format["no_date"]=true
			if (isset($field_format["no_timezone"]) && $field_format["no_timezone"]===true) 
				break;
			
			//~~~TIMEZONE INPUT~~~

			if (isset($field_format["timezones_cb"]))
				$timezones = call_user_func ($field_format["timezones_cb"]);
			else
				$timezones = pretty_timezone_list();

			$timezones = format_for_dropdown_assoc_opt($timezones,"{$field_name}_timezone");

			$js = "";
			if (isset($field_format["javascript"]["timezone"]))
				$js = " ".$field_format["javascript"]["timezone"];
			if (isset($field_format["javascript"]) && is_string($field_format["javascript"]))
				$js = " ".$field_format["javascript"];
			elseif (isset($field_format["javascript"]["all"]))
				$js = " ".$field_format["javascript"]["all"];

			if (isset($value["timezone"]))
				$timezones["selected"]=$value["timezone"];

			print build_dropdown($timezones, "{$field_name}_timezone", false, false, $css, $js, false, false, null, false);

			//Extra Button: Ex: Use UTC button to auto select UTC timezone
			if (isset($field_format["button"]["js"]) && isset($field_format["button"]["name"]))
				print "<button type=\"button\" " . $field_format["button"]["js"] . " >" . $field_format["button"]["name"] . "</button> ";

			print $field_comment;
			break;
		case "fixed":
			if (strlen($value))
				print $value;
			else
				print "&nbsp;";
			print $field_comment;
			break;
		case "fixed-encoded":
			$value = html_entity_decode($value);
			if (strlen($value))
				print $value;
			else
				print "&nbsp;";
			print $field_comment;
			break;

		default:
			// make sure the function that displays the advanced field is included
			if (isset($field_format["advanced"]))
				print '<input type="hidden" name="'.$form_identifier.$field_name.'">';

			if (!is_callable($display)) {
				Debug::trigger_report('critical', "Callable ".print_r($display,true)." is not implemented.");
				errornote("Callable '".print_r($display,true)."' is not implemented.");
			} else {
				// callback here
				$value = call_user_func_array($display, array($value,$form_identifier.$field_name));
				if ($value)
					print $value;
				print $field_comment;
			}
	}

	if (isset($show_notes) && is_addon("font-awesome") && count($field_note)) {
		$css_icon = "fa fa-comment-o pointer note_icon";
		$title_icon = "";
		if (!$show_notes && isset($field_note["note"])) {
			$title_icon = ' title="'.$field_note["note"].'" ';
			$css_icon = "fa fa-comment pointer note_icon";
		}
		print '&nbsp;&nbsp;<i class="'.$css_icon.'" aria-hidden="true" id="'.$form_identifier.'icon-'.$field_name.'" '.$title_icon;
		if (isset($field_note["object_name"]) && isset($field_note["object_id"]))
			print ' onClick="show_note(\''.$field_name.'\', \''.$field_note["object_name"].'\', \''.$field_note["object_id"].'\', \''.$column_name.'\', \''.$form_identifier.'\');"';
		print '></i>';
	}
	//add custom html for the input field after the documentation question mark. Ex: $conf[$fieldname]["post_extra_html"] = array( "html" => "html_desired");
	if (isset($field_format["post_extra_html"])) {
		$html_content = $field_format["post_extra_html"]["html"];
		print "{$html_content}";
	}

	print '</td>';
	print '</tr>';
}

/**
 * Display a custom field in the FORM
 * @param string $field_id. The css id value.
 * @param string $raw. The HTMLto be displayed in the custom field.
 */
function display_custom_field($field_id, $raw)
{
	print '<tr><td colspan="2" id="'.$field_id.'">';
	print $raw;
	print '</td></tr>';
}


/**
 * Builds the javascript format to use javascript function 
 * build_restart_changes(label+filed_name) that will register the 
 * fields that have changes from form
 * Used in display_pair()
 */
function build_javascript_format($field_name, &$field_format, $display, $label, $form_identifier)
{
	$op = ($display == "checkbox") ? "onClick" : "onChange";
	$js = "build_restart_changes('".$label."-".$form_identifier.$field_name."')";
	if (!isset($field_format["javascript"])) {
		$field_format["javascript"] = " " . $op . "=\"" . $js . "\"";
		return;
	}

	$parts = preg_split("/".$op."=/i", $field_format["javascript"],2);
	if (count($parts)>1) {
		if ($parts[1][0] == "'")
			$js = str_replace("'", "\"", $js);
		$field_format["javascript"] = $parts[0] . $op . "=" . $parts[1][0] . $js . "; ". substr($parts[1],1);
	} else
		$field_format["javascript"] .= " " . $op . "=\"" . $js . "\"";
}

/**
 * Used in display_pair()
 * Get the selected option for a field type dropdown/checkbox-group/radio
 * @param string $display
 * @param string $field_name
 * @param array $field_format
 * @param array $options
 * @param array/object/null $object
 * @return string
 */
function selected_option($display, $field_name, $field_format, $options, $object)
{		
	Debug::debug_message("field_format",$field_format);
	Debug::debug_message("field_format",$object);
	
	//default value
	if (isset($field_format["selected"]))
		$selected = $field_format["selected"];
	elseif (isset($options["selected"]))
		$selected = $options["selected"];
	elseif (isset($options["SELECTED"]))
		$selected = $options["SELECTED"];
	else
		$selected = '';
	
	//form value
	if (isset($field_format["form_val"]))
		return $field_format["form_val"];
	
	//value from object
	if ($object) {
		if (!is_array($field_name)) {
			if (is_array($object) && count($object) && array_key_exists($field_name,$object)) {
				$selected = $object[$field_name];
				if ($display == "mul_select"  ||  $display == "checkbox-group")
					$selected = explode(",",$selected);
			} elseif (is_object($object) && property_exists($object,$field_name) && $object->isPopulated()) {
				$selected = $object->{$field_name};
				if ($display == "mul_select"  ||  $display == "checkbox-group")
					$selected = explode(",",$selected);
			}
		}
		//else default will be returned
		
		// value from object returned
		return $selected;
	}
	
	// default returned
	return $selected;
}
	
/**
 * Verify if form was submitted
 * Recommended: set $keep_form_values in defaults.php so by default we try   
 *		detecting if form was submitted by verifying if "action" field 
 *		is equal to "database" value
 * @param string $apply_conditions Conditions that must be accomplished in order to consider that form was submitted. Ex: $apply_conditions = array("method"=>"edit_apn_db");
 * @return boolean. False if not submitted, true otherwise.
 */
function is_form_submited($apply_conditions=array())
{
	global $keep_form_values;

	// if keep_form_values activated but no condition given
	// try detecting if form was submited by verifying if "action" field is equal to "database" value
	if ($keep_form_values && (!is_array($apply_conditions) || !count($apply_conditions)))
		$apply_conditions = array("action"=>"database");
	
	$form_submited = true;
	foreach ($apply_conditions as $field=>$value) {
		if (isset($_REQUEST[$field]) && $_REQUEST[$field]==$value)
			continue;
		
		$form_submited = false;
	}
	
	return $form_submited;
}

// Call js associated to a field
// Decission to call it is made before calling this function
// Used when in dropdown 'Custom' was selected and we need to replicate js functiolity
// Instead of adding tr_custom_$field_name, call js function that will build the input field with the custom value already set
function autocall_js($field_name, $field_format, $form_identifier) 
{	
	global $in_wizard;
	if (!isset($in_wizard))
		$in_wizard = false;
	
	$js = $field_format["javascript"]; 
	$autocall = (isset($field_format["js_autocall"])) ? $field_format["js_autocall"] : null;
				
	$change_val_dropdown = (strpos($js, "custom_value_dropdown")) ? true : false;
				
	if ($autocall===true || ($change_val_dropdown && $autocall!==false && !$in_wizard)) {
	
		// when Custom value is selected in dropdown js custom_value_dropdown(custom_value,dropdown_id) is called on onchange
		// call js from here so field appears under <select>
		// For this to work in conjuction with wizard's pages.php methods, callback_request was modified to retrieve js pieces from response and eval them

		// get js piece
		$js = explode("=\"", $js); 
		if (count($js)==1)
			$js = explode("='", $js[0]); 

		if (count($js)>1) {
			// strip last ' or "
			$js = $js[1];
			$js = substr($js,0,strlen($js)-1); 
			// modify calling of function to include value for custom fields instead of '' or ""
			if (getparam("custom_".$form_identifier.$field_name)) {
				if (false!==strpos($js,"''"))
					$js = str_replace("''", "'".getparam("custom_".$form_identifier.$field_name)."'",$js);
				elseif (false!==strpos($js,'""'))
					$js = str_replace('""', '"'.getparam("custom_".$form_identifier.$field_name).'"',$js);
			} elseif (isset($field_format["custom_field_value"]) && strlen($field_format["custom_field_value"])) {
				if (false!==strpos($js,"''"))
					$js = str_replace("''", "'".$field_format["custom_field_value"]."'",$js);
				elseif (false!==strpos($js,'""'))
					$js = str_replace('""', '"'.$field_format["custom_field_value"].'"',$js);
			}
			
			if (isset($field_format['js_execution']) &&  $field_format['js_execution'] === "page_loaded") {
				//Execute JS after the entire page has been loaded
				print "<script> window.addEventListener('load', function(){ $js });</script>";
			} elseif (isset($field_format['js_execution']) && $field_format['js_execution'] === "dom_loaded") {
				//Execute JS after DOM is loaded and parsed without waiting for stylesheets, images, and subframes to finish loading
				print "<script> document.addEventListener('DOMContentLoaded', function(){ $js });</script>";
			} else {
				//Execute JS now
				print "<script>$js</script>";
			}
		}
	}
}

function comment_field($field_format, $form_identifier, $field_name, $category_id, $display)
{
	global $use_comments_docs, $allow_code_comment, $method;

	$res = "";
	if ($display != "hidden") {
		if (isset($field_format["comment"])) {
			if ($allow_code_comment) {

				$comment = $field_format["comment"];

				if (is_addon("font-awesome"))
					$res .= '&nbsp;&nbsp;<i class="fa fa-question pointer help_icon" aria-hidden="true" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"></i>';
				else if (is_file("images/question.jpg"))
					$res .= '&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"/>';
				else
					$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"> ? </font>';

				$res .= '<font class="comment" style="display:none;" id="comment_'.$form_identifier.$field_name.'">'.$comment.'</font>';
			}
		}

		if ($use_comments_docs) {
			if (!$category_id)
				$category_id = str_replace(array("add_", "edit_"), "", $method);

			$comment_id = build_comment_id($field_format, $form_identifier, $field_name, $category_id);

			if (is_addon("font-awesome"))
					$res .= '&nbsp;&nbsp;<i class="fa fa-question pointer help_icon" aria-hidden="true" onClick="show_docs(\''.$category_id.'\', \''.$comment_id.'\');"></i>';
			else if (is_file("images/question.jpg"))
				$res .= '&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_docs(\''.$category_id.'\', \''.$comment_id.'\');"/>';
			else
				$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_docs(\''.$category_id.'\',\''.$comment_id.'\');"> ? </font>';
		}
	}
	return $res;
}

function build_comment_id($field_format, $form_identifier, $field_name, $category_id)
{
	if (isset($field_format["comment_id"]))
		return $field_format["comment_id"];

	if (isset($field_format["ref_comment_id"]))
		$field_name = $field_format["ref_comment_id"];

	$pieces = explode("_", $field_name);

	if (ctype_digit(strval($pieces[count($pieces)-1]))) {
		$sign = $fld = "";
		for ($i = 0; $i<count($pieces)-1; $i++) {
			$fld .= $sign.$pieces[$i];
			$sign = "_";
		}
		return $category_id."_".$form_identifier.$fld;
	}
	$last = substr($field_name, -1);
	$penultimate = substr($field_name, -2, 1);
	$fld = $field_name;
	if ($penultimate=="_" && ctype_digit(strval($last)))
		$fld = substr($field_name,0,strlen($field_name)-2);
	elseif ((ctype_digit(strval($penultimate)) && ctype_digit(strval($last))) ||
			$last == "_")
		$fld = substr($field_name,0,strlen($field_name)-1);	

	return $category_id."_".$form_identifier.$fld;
}

/**
 * Find a field value from the results of a PGSQL query
 * Function used in query_to_array()
 */ 
function find_field_value($res, $line, $field)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	for($j=0; $j<pg_num_fields($res); $j++) {
		if (pg_field_name($res,$j) == $field)
			return pg_fetch_result($res,$line,$j);
	}

	return NULL;
}

function tree($array, $func="copacClick", $class="copac", $title=NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$path;

	if (!is_array($array))
		$array = array();

	$i = 0;
	if (count($array) && !isset($array[$i]))
		while(!isset($array[$i]))
			$i++;
	if (count($array)) {
		$num = count($array[$i]);
		$verify = array();
		for ($j=0;$j<$num;$j++)
			$verify[$j]="";
	}
	$level = 0;
	if (!$title)
		$title = $module;

	print '<div class="'.$class.'">';
	print '<div class="titlu">' . ucfirst($title). '</div>';
	print '<ul class="copac">';
	for ($i=0;$i<count($array);$i++) {
		$j = 0;
		foreach ($array[$i] as $fld => $val) {
	    		if ($val == "") {
				$j++;
				break;
			}
			if ($val == $verify[$j]) {
				$j++;
				continue;
			}
	    		for (;$level>$j;$level--)
				print "</ul>";
	    		if ($j > $level) {
				print "\n<ul class=\"copac_{$level}\" id=\"copac_ul_{$i}_{$level}\" style=\"display:none\">";
				$level++;
			}
			$tip = ($j+1 < $num) ? "disc" : "square";
			print "\n<li class=\"copac_{$j} \" id=\"copac_li_{$i}_{$j}\" type=\"{$tip}\"><a class=\"copac\" href=\"#\" onClick=\"{$func}({$i},{$j},'{$fld}'); return false\">$val</a></li>";
			$verify[$j] = $val;
			for ($k=$j+1; $k<$num; $k++)
				$verify[$k] = "";
			$j++;
		}
	}
	for (;$level>0;$level--)
		print "</ul><br/>";
    print "\n</ul></div>";
}

/**
 * Finds a field in the Keys of an array
 * Returns array with the searched field 
 */ 
function in_formats($field, $formats)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	foreach($formats as $key=>$value) {
		if (substr_count($key, $field))
			return array("key"=>$key, "value"=>$value);
	}

	return false;
}

/**
 *  Builds an array from a postgresql query results
 */ 
function query_to_array($res, $formats=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$begginings = array('1_', '2_' ,'3_' , '4_', '5_', '6_', '7_', '8_', '9_', '0_');

	$array = array();
	for($i=0; $i<pg_num_rows($res);$i++) {
		$array[$i] = array();
		for($j=0; $j<pg_num_fields($res); $j++) {
			$nm = pg_field_name($res,$j);
			$value = pg_fetch_result($res,$i,$j);
			if (isset($formats[$nm]) || in_formats($nm, $formats)) {
				$arr = in_formats($nm, $formats);

				$val = $arr["value"];
				$nm = $arr["key"];
				if (in_array(substr($nm,0,2), $begginings))
					$nm = substr($nm,2,strlen($nm));
				$save_nm = $nm;

				if (substr($val,0,9) == "function_") {
					$name = substr($val,9,strlen($val));
					$arr = explode(':',$name);

					if (count($arr)>1) {
						$nm = $arr[1];
						$name = $arr[0];
					}

					if (str_replace(',','',$save_nm) == $save_nm) {
						$value = call_user_func($name,find_field_value($res,$i,$save_nm));
					} else {
						$save_nm = explode(',',$save_nm);
						$params = array();
						for($x=0; $x<count($save_nm); $x++)
							$params[trim($save_nm[$x])] = find_field_value($res, $i, trim($save_nm[$x]));
						$value = call_user_func_array($name,$params);
						$save_nm = implode(":",$save_nm);
					}
				} elseif ($val)
					$nm = $val;
			}
			$array[$i][$nm] = $value;
		}
	}
	return $array;
}

function tableOfObjects_objectnames($objects, $formats, $object_name, $object_actions=array(), $general_actions=array(),$object_actions_names=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	tableOfObjects($objects, $formats, $object_name, $object_actions, $general_actions, NULL, false, "content", array(), $object_actions_names);
}

function tableOfObjects_ord($objects, $formats, $object_name, $object_actions=array(), $general_actions=array(), $order_by_columns=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	tableOfObjects($objects, $formats, $object_name, $object_actions, $general_actions, NULL, false, "content", array(),array(),false, $order_by_columns);
}

/**
 * Removes forbidden actions from $object_actions/ $general_actions array from table/tableOfObjects($forbidden_actions are set in structure.php)
 */
function clean_actions_array($actions)
{
	global $level, $forbidden_actions;

	if (!is_array($actions))
		return array();
	
	if (!isset($forbidden_actions[$level]))
		return $actions;
	
	foreach ($actions as $key=>$value) {
		$verif = (is_int($key)) ? $value : $key;

		if (in_array($verif, array("left", "right", "remove"))) {
			$actions[$key] = clean_actions_array($value);
			continue;
		}
		
		if ($verif === "cbs") {
			foreach ($value as $cb_key => $cb) {
				$test_val = (isset($cb["name"])) ? $cb["name"] : $cb; 
				if (stripos_array_values($test_val, $forbidden_actions[$level]))
					unset($actions[$verif][$cb_key]);
			}
			continue;
		}
	
		if ($verif === "cb")
			$test_val = (is_array($value)) ? $value["name"] : $value;
		else
			$test_val = $verif;
	
		if (stripos_array_values($test_val, $forbidden_actions[$level]))
			unset($actions[$key]);
	}
	
	return $actions;
}

function stripos_array_values($str, $array) 
{
	foreach ($array as $val)
		if (stripos($str,$val)!==FALSE)
			return true;
	
	return false;
}

/**
 * Creates table of objects
 * @param $objects Array with the objects to be displayed
 * @param $formats Array with columns to be displayed in the table
 * Ex: array("username", "function_truncdate:registered_on"=>"date", "function_unifynames:name"=>"first_name,last_name")
 * will display a table having the column names : Username | Registered on | Name
 * "function_truncdate:registered_on"=>"date" means that on each variable named date 
 * for all objects function truncdate will be called and the result will be printed in the table under column Registered on
 * "function_unifynames:name"=>"first_name,last_name" means that under the Name column, for all objects 
 * function unifynames will be called with 2 parameters" the content of variables first_name and last_name from each object 
 * @param $object_name Name of the object to be displayed. It's important only when the number of objects to be printer is 0
 * @param $object_actions Array of $method=>$method_name, $method will be added in the link and $method_name will be printed
 * Ex: array("&method=edit_user"=>"Edit")
 * @param $general_actions Array of $method=>$method_name that will be printed at the end of the table
 * If key '__top' is present in the $general_actions then the position will be top otherwize bottom of the table
 * Ex: array("&method=add_user"=>"Add user") or with callback:
 * array("cb"=>"fun_name") or:
 * array("cb"=>array("name"=>"fun_name", "params"=> array(param1, param2,...)) or:
 * array("left"=>array("cb"=> "func_name"), "right"=>array("cb"=>"func_name")) or: 
 * array("left"=>array("cb"=>array("name"=>"func_name", "params"=>array(param1, param2,...)), "right"=>array("cb"=>"func_name"));
 * Ex: array("right"=>array("&method=add_user"=>"Add user"), "left"=>array(...))
 * @param $base Text representing the name of the page the links from @ref $object_name and @ref $general_actions will be sent
 * Ex: $base = "main.php"
 * If not sent, i will try to see if $_SESSION["main"] was set and create the link. If $_SESSION["main"] was not set then  
 * "main.php" is the default value 
 * @param $insert_checkboxes Bool value. If true then in front of each row a checkbox will be created. The name attribute
 * for it will be "check_".value of the id of the object printed at that row
 * Note!! This parameter is taken into account only if the objects have an id defined
 * @param $css Name of the css to use for this table. Default value is 'content'
 * @param $conditional_css Array ("css_name"=>$conditions) $css is the to be applied on certain rows in the table if the object corresponding to that row complies to the array of $conditions
 * @param $object_actions_names Array
 * @param  $select_all Bool
 * @param $order_by_columns Bool/Array Adds buttons to adds links on the fields from the table header. 
 * Defaults to false
 * @param $add_empty_row Bool
 */
function tableOfObjects($objects, $formats, $object_name, $object_actions=array(), $general_actions=array(), $base = NULL, $insert_checkboxes = false, $css = "content", $conditional_css = array(), $object_actions_names=array(), $select_all = false, $order_by_columns=false, $add_empty_row=false, $label=null, $form_identifier=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");

	global $db_true, $db_false, $module, $method, $do_not_apply_htmlentities, $level, $go_to_prev_page_link, $display_table_headers;

	if (!is_array($objects))
		$objects = array();

	$object_actions = clean_actions_array($object_actions);
	$general_actions = clean_actions_array($general_actions);
	$formats = clean_actions_array($formats);

	// Start output buffering if level is auditor, so all output is stored in the internal buffer and it is not sent from the script.
	if ($level == "auditor")
		ob_start();
	
	if(!$db_true)
		$db_true = "yes";
	if(!$db_false)
		$db_false = "no";

	if(!count($objects) && (!isset($display_table_headers) || $display_table_headers == false)) {
		// display previous page contained in $go_to_prev_page_link (if pages() function detected that the wrong page is displayed then $go_to_prev_page_link will contain the previous page link with the correct page param variable)
		if ($go_to_prev_page_link) {
			print "<meta http-equiv=\"REFRESH\" content=\"3;url=".$go_to_prev_page_link."\">";
			plainmessage("<table class=\"$css\"><tr><td>Page will be refreshed in a few seconds.</td></tr>", false);
			return;
		}
		$plural = get_plural_form($object_name);
		print "<table class=\"$css\"><tr><td style=\"text-align:right;\">";
		plainmessage("There aren't any $plural in the database.");
		print "</td></tr>";
		if (!count($general_actions)) {
			print '</table>';
			return;
		}
	}

	$restart_fields = getparam("restart_fields");
	if ($restart_fields)
		build_restart_warning($label,$restart_fields, $form_identifier);
	
	if (!$base) {
		$main = (isset($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
		$base = "$main?module=$module";
	}
	
	$do_not_apply_htmlentities = (!$do_not_apply_htmlentities) ? array() : $do_not_apply_htmlentities;

	$ths = "";
	$id = str_replace(" ", "_", $css);
	print '<table class="'.$css.'" id="'.$id.'_id" cellspacing="0" cellpadding="0">';

	$order_dir = (!getparam("order_dir") || getparam("order_dir")=="asc") ? "desc" : "asc";
	if(count($objects)) {
		$ths .= '<tr class="'.$css.'">';
		$no_columns = 0;

		if($insert_checkboxes) {
			$ths .= '<th class="'.$css.' first_th checkbox">';
			if ($select_all)
				$ths .= '<input type="checkbox" name="select_all" id="select_all" onclick="toggle_column(this);">';
			else
				$ths .= '&nbsp;';
			$ths .= '</th>';
			$no_columns++;
		}

		$request_link = build_link_request(array("order_by","order_dir"));

		// print the name of the columns + add column for each action on object
		foreach($formats as $column_name => $var_name) {
			$name = extract_column_name($column_name, $var_name);
			$css_col = extract_column_css($conditional_css, $name);
			
			$ucss = ($no_columns == 0) ? "$css first_th" : $css;
			if ($no_columns == count($formats)-1)
				$ucss = $css . " last_th";
			$ths .= '<th class="'.$ucss.$css_col.'">';
			//$ths .= str_replace("_","&nbsp;",ucfirst($name));
			$column_link = false;
			if ($order_by_columns) {
				if (((is_numeric($column_name) || count(explode(",",$var_name))==1) && ($order_by_columns===true || !isset($order_by_columns[$name]))) || (isset($order_by_columns[$name]) && $order_by_columns[$name]==true)) {

					$ths .= "<a class='thlink' href='".$request_link."&order_by=$name&order_dir=$order_dir'>".str_replace("_","&nbsp;",ucfirst($name))."</a>";
					$column_link = true;
				}
			}
			if (!$column_link)
				$ths .= str_replace("_","&nbsp;",ucfirst($name));
			$ths .= '</th>';
			$no_columns++;
		}
		for($i=0; $i<count($object_actions); $i++) {
			$ths .= '<th class="'.$css.'">';
			if  (!isset($object_actions_names) || !isset($object_actions_names[$i]))
				$ths .= '&nbsp;';
			else
				$ths .= $object_actions_names[$i];
			$ths .= '</th>';
			$no_columns++;
		}
		$ths .= '</tr>';

		$vars = $objects[0]->extendedVariables();
		$class = get_class($objects[0]);
		$id_name = $objects[0]->getIdName();
		print $ths;
	} else {
		if (!isset($display_table_headers) || $display_table_headers == false) {
			$no_columns = 2;
		} else {
			// display previous page contained in $go_to_prev_page_link (if pages() function detected that the wrong page is displayed then $go_to_prev_page_link will contain the previous page link with the correct page param variable)
			if ($go_to_prev_page_link) {
				print "<meta http-equiv=\"REFRESH\" content=\"3;url=".$go_to_prev_page_link."\">";
				plainmessage("<table class=\"$css\"><tr><td>Page will be refreshed in a few seconds.</td></tr>", false);
				return;
			}
			$plural = get_plural_form($object_name);

			$ths .= '<tr>';
			$no_columns = 0; 
			foreach($formats as $column_name => $var_name) {
				$name = extract_column_name($column_name, $var_name);
				$css_col = extract_column_css($conditional_css, $name);
				$headers_name = str_replace("_","&nbsp;",ucfirst($name));

				$ucss = ($no_columns == 0) ? "$css first_th" : $css;
				if ($no_columns == count($formats)-1)
					$ucss = $css . " last_th";
				$ths .= '<th class="'.$ucss.$css_col.'">' . $headers_name . '</th>';
				$no_columns++;

			}

			$ths .= '</tr><tr><td class="'.$css.' last_row left_align_monitoring"  colspan="'.count($formats).'" >';
			print($ths);
			plainmessage("There aren't any $plural in the database.");
			print('</td></tr>');

			if (!count($general_actions)) {
				print '<tr><td class="last_row_empty" colspan="'.count($formats).'"></td></tr>';
				print '</table>';
				return;
			}
		}
	}
	if (count($general_actions) && in_array("__top",$general_actions))
		links_general_actions($general_actions, $no_columns, $css, $base, true);

	for($i=0; $i<count($objects); $i++) {
		$cond_css = '';
		foreach($conditional_css as $css_name=>$conditions) {
			if (substr($css_name, 0,10) == "__col_css_")
				continue;
			$add_css = true;
			foreach($conditions as $column=>$cond_column) {
				if($objects[$i]->{$column} != $cond_column) {
					$add_css = false;
					break;
				}
			}
			if($add_css)
				$cond_css .= " $css_name ";
		}
		if($i==(count($objects)-1))
			$cond_css.= " pre_end"; 
		print '<tr class="'.$css.'">';
		if($insert_checkboxes && $id_name) {
			print '<td class="'.$css.$cond_css;
			if($i%2 == 0)
				print " evenrow";
			print '">';
			print '<input type="checkbox" name="check_'.$objects[$i]->{$id_name}.'"/>';
			print '</td>';
		}
		$j=0;
		foreach($formats as $column_name => $var_name) {
			$name = extract_column_name($column_name, $var_name);
			$css_col = extract_column_css($conditional_css, $name);
			print '<td class="'.$css.$cond_css .$css_col;
			if($i%2 == 0)
				print " evenrow";
			if($j == count($formats)-1 && !count($object_actions))
				print " end_cell";
			print '">';
			$use_vars = explode(",", $var_name);
			array_walk($use_vars, 'trim_value');
			$exploded_col = explode(":", $column_name);
			$column_value = '';
			$apply_htmlentities = false;
			if(substr($exploded_col[0],0,9) == "function_") {
				$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
				if (in_array($function_name, $do_not_apply_htmlentities)) { 
					$apply_htmlentities = true;
				}
				if(count($use_vars)) {
					$params = array();
					for($var_nr=0; $var_nr<count($use_vars); $var_nr++)
						if(array_key_exists($use_vars[$var_nr], $vars))
							array_push($params, $objects[$i]->{$use_vars[$var_nr]});
						else
							array_push($params,null);
					$column_value = call_user_func_array($function_name,$params);
				}
			} elseif(isset($objects[$i]->{$var_name})){
				$column_value = $objects[$i]->{$var_name};
				$var = $objects[$i]->variable($use_vars[0]);
				if($var->_type == "bool") {
					if (bool_value($column_value))
						$column_value = $db_true;
					else
						$column_value = $db_false;
				}
			}
			if($column_value !== NULL) {
				$column_value = ($apply_htmlentities) ? $column_value : htmlentities($column_value);
				print $column_value;
			} else 
				print "&nbsp;";
			print '</td>';
			$j++;
		}
		$link = '';
		foreach($vars as $var_name => $var)
			if ($var_name!="password" && strlen($objects[$i]->{$var_name}) < 50)
				$link .= "&$var_name=".htmlentities(urlencode($objects[$i]->{$var_name}));
		$link_no = 0;
		foreach($object_actions as $methd => $methd_name) {
			print '<td class="'.$css.$cond_css;
			if($i%2 == 0)
				print ' evenrow object_action';
			else
				print ' object_action';
			if ($link_no == count($object_actions)-1)
				print ' end_cell';
			print '">';
		//	if($link_no)
		//		print '&nbsp;&nbsp;';
			print '<a class="'.$css.'" href="'.$base.$methd.$link.'">'.$methd_name.'</a>';
			print '</td>';
			$link_no++;
		}
		print '</tr>';
	}
	if ($add_empty_row) {
		$colspan = count($formats) + count($object_actions);
		if ($insert_checkboxes)
			$colspan += 1;
		if (!count($general_actions))
			print '<tr><td class="last_row_empty" colspan="'.$colspan.'"></td></tr>';
		else
			print '<tr><td class="last_row" colspan="'.$colspan.'"></td></tr>';
	}
	if(count($general_actions) && !in_array("__top",$general_actions))
		links_general_actions($general_actions, $no_columns, $css, $base);

	print "</table>";
	
	// catch table before it is displayed and replace Edit with View
	if ($level == "auditor") {
		$table = ob_get_contents();
		ob_end_clean();
		
		echo str_replace(array(">Edit</a>",">Edit </a>", "> Edit</a>", "> Edit </a>"), ">View</a>", $table);
	}
}

function extract_column_name($column_name, $var_name) 
{
	$exploded = explode(":",$column_name);
	if(count($exploded)>1)
		$name = $exploded[1];
	else {
		$name = $column_name;
		if(substr($column_name, 0, 9) == "function_")
			$name = substr($column_name,9);
		if(is_numeric($column_name))
			$name = $var_name;
	}
	return $name;	
}

function extract_column_css($conditional_css, $name)
{
	$css_col = "";
	foreach ($conditional_css as $css_class_name => $conditions) {
		if (substr($css_class_name, 0,10) != "__col_css_")
			continue;
		if (!in_array($name, $conditions))
			continue;
		$css_col .= " " . substr($css_class_name,10);
	}
	
	return $css_col;
}
/**
 * Get column and direction to order table elements by
 * @param $fields Array. This is the same parameter as $fields from \ref tableOfObjects
 * @param $default String. Default order column
 * @return String. Column and direction to use in SELECT query or NULL if $default was not set
 * Ex: "pieces ASC", "username DESC"
 */
function column_order($fields, $default, $custom_order=array())
{
	$order_by = getparam("order_by");
	$dir = (in_array(getparam("order_dir"),array("asc","desc"))) ? strtoupper(getparam("order_dir")) : "ASC";

	$col = null;
	if (isset($fields[$order_by])) {
		$col = $fields[$order_by];
	} elseif (in_array($order_by,$fields))
		$col = $order_by;
	
	if ($col) {
		if (isset($custom_order[$col])) {
			if ($custom_order[$col]===true)
				return "$col $dir";
			else
				return $custom_order[$col]." ".$dir;
		}

		if (count(explode(",",$col))==1)
			return "$col $dir";
		elseif ($default) {
			if (strtolower(substr($default,-4))!=" asc" && strtolower(substr($default,-4))!="desc")
				return "$default $dir";
			else
				return $default;
		} else
			return null;
	}

	foreach ($fields as $key=>$value) {
		if (substr($key,0,9)=="function_") {
			$key = explode(":",$key);
			$key = $key[1];
		}
		if ($key === $order_by) {
			if (isset($custom_order[$key])) {
				if ($custom_order[$key]===true)
					return "$key $dir";
				else
					return $custom_order[$key]." ".$dir;
			} elseif (count(explode(",",$value))==1)
				return "$value $dir";
			elseif ($default) {
				if (strtolower(substr($default,-4))!=" asc" && strtolower(substr($default,-4))!="desc")
					return "$default $dir";
				else
					return $default;
			} 
			return null;
		}
	}

	if ($default) {
		if (strtolower(substr($default,-4))!=" asc" && strtolower(substr($default,-4))!="desc")
			return "$default $dir";
		else
			return $default;
	}

	return null;
}

/**
 * Builds links or makes callbacks that will contain the general actions in the table of objects
 * @param $general_actions Array 
 * Ex: array("&method=add_user"=>"Add user") or array("left"=> array("&method=add_user"=>"Add user"), "right"=> array())
 * or with callback:
 * array("cb"=>"func_name") or array("cb"=>array("name"=>"func_name", "params"=> array(param1, param2,...)) or:
 * array("left"=>array("cb"=> "func_name"), "right"=>array("cb"=>"func_name")) or: 
 * array("left"=>array("cb"=>array("name"=>"func_name", "params"=>array(param1, param2,...)), "right"=>array("cb"=>"func_name"));
 * @param $no_columns Int the number of the columns. Used to build the colspan of the cell.
 * @param $css Text the css class name
 * @param $base Text. Name of the page the links from @ref $object_name and @ref $general_actions will be sent
 * Ex: $base = "main.php"
 * @param $on_top Bool if true add css class to cell 'starttable', otherwize 'endtable'  
 */ 
function links_general_actions($general_actions, $no_columns, $css, $base, $on_top=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (!is_array($general_actions) || !count($general_actions))
		return;

	$pos_css = ($on_top) ? "starttable" : "endtable";

		print '<tr class="'.$css.' endtable">';
		if (isset($general_actions["left"])) {
			$left_actions = $general_actions["left"];
			$columns_left = floor($no_columns/2);
			$no_columns -= $columns_left;
			print '<td class="'.$css.' allleft '.$pos_css.'" colspan="'.$columns_left.'">';
			$link_no = 0;
			if (is_array($left_actions)) {
				foreach($left_actions as $methd => $methd_name)	{
					if ($link_no)
						print '&nbsp;&nbsp;';
					if ($methd === "cb")
						set_cb($methd_name);
					elseif ($methd === "cbs") {
						
						foreach ($methd_name as $meth) {
							set_cb($meth);
							print '&nbsp;&nbsp;';
						}
					} else {
						print '<a class="'.$css.'" href="'.$base.$methd.'">'.$methd_name.'</a>';
					}
					$link_no++;
				} 
			} else
				print $left_actions;
			print '</td>';
			if (isset($general_actions["right"]))
				$general_actions = $general_actions["right"];
			else
				$general_actions = array();
		}
		
		print '<td class="'.$css.' allright '.$pos_css.'" colspan="'.$no_columns.'">';
		$link_no = 0;
		if (!count($general_actions))
			print "&nbsp;";
		foreach($general_actions as $methd=>$methd_name) {
			if ($methd_name == "__top")
				continue;
			if ($link_no)
				print '&nbsp;&nbsp;';
			if ($methd === "cb") {
				set_cb($methd_name);
			} elseif ($methd === "cbs") {
				foreach ($methd_name as $meth) {
					set_cb($meth);
					print '&nbsp;&nbsp;';
				}
			} else {
				print '<a class="'.$css.'" href="'.$base.$methd.'">'.$methd_name.'</a>';
			}
			$link_no++;
		}
		print '</td>';
		print '</tr>';
}

/** 
 * Makes the callback for different methods
 * used in  links_general_actions() when a callback is set in $general_actions
 */ 
function set_cb($methd_name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (is_callable($methd_name))
		call_user_func($methd_name);

	elseif (is_array($methd_name) && isset($methd_name["name"]) && isset($methd_name["params"])) {
		if (is_callable($methd_name["name"]))
			call_user_func_array($methd_name["name"], $methd_name["params"]);
		else
			Debug::trigger_report("critical", "The function '". $methd_name["name"] . "' is not implemented.");
	}
	else 
		Debug::trigger_report("critical", "Incorrect 'cb' - callback set in general actions.");
}

/**
 * Returns the plural of an object name
 */ 
function get_plural_form($object_name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	if (substr($object_name,0,7)=="__keep_") {
		return substr($object_name,7);
	} elseif (class_exists($object_name)) {
		$obj = new $object_name;
		$plural = $obj->getTableName();
	} else {
		if (substr($object_name,-1) == "s")
			$plural = $object_name;
		elseif (substr($object_name, -1) == "y")
			$plural = substr($object_name,0,strlen($object_name)-1)."ies";
		else
			$plural = $object_name."s";
	}
	return $plural;
}

/**
 * Strip whitespace (or other characters) from the beginning and end of a string
 */ 
function trim_value(&$value) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$value = trim($value); 
}

/**
  * Creates table of elements
  * @param $array Array with the values of the elements to be displayed.
  * @param $formats Array with columns to be displayed in the table.
  * @param $element_name String. Name of the element to be displayed in the table. 
  * @param $element_actions Array of $method=>$method_name, $method will be added in the link and $method_name will be printed
  * Ex: array("&method=edit_user"=>"Edit")
  * @param $general_actions Array of $method=>$method_name that will be printed at the end of the table
  * Ex: array("&method=add_user"=>"Add user")
  * @param $base Text representing the name of the page the links from @ref $element_name and @ref $general_actions will be sent
  * Ex: $base = "main.php"
  * If not sent, i will try to see if $_SESSION["main"] was set and create the link. If $_SESSION["main"] was not set then  
  * "main.php" is the default value 
  * @param $insert_checkboxes Bool. If true then in front of each row a checkbox will be created. The name attribute
  * for it will be "check_".value of the id of the object printed at that row
  * Note!! This parameter is taken into account only if the objects have an id defined
  * @param $css Name of the css to use for this table. Default value is 'content'
  * @param $conditional_css Array ("css_name"=>$conditions) $css is the to be applied on certain rows in the table if the object corresponding to that row complies to the array of $conditions
  * @param $object_actions_names Array containg the action names for the $element_actions that are displayed in the table header
  * @param $table_id Text the id of table
  * @param $select_all Bool. If true select all checkboxes made when $insert_checkboxes=true. Defaults to false.
  * @param $order_by_columns Bool/Array Adds buttons to add links on the fields from the table header. Defaults to false.
  * @param $build_link_elements Array Contains the name of the elements used to build each row $element_actions.
  * @param $optional_message Text If is set, replaces the generic message in case of first parameter ($array) is empty.
  */
function table($array, $formats, $element_name, $id_name, $element_actions = array(), $general_actions = array(), $base = NULL, $insert_checkboxes = false, $css = "content", $conditional_css = array(), $object_actions_names = array(), $table_id = null, $select_all = false, $order_by_columns = false, $build_link_elements = array(), $add_empty_row =false, $optional_message = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $do_not_apply_htmlentities, $level, $go_to_prev_page_link, $display_table_headers;

	if (!is_array($array))
		$array = array();
	$element_actions = clean_actions_array($element_actions);
	$general_actions = clean_actions_array($general_actions);
	$formats = clean_actions_array($formats);

	// Start output buffering if level is auditor, so all output is stored in the internal buffer and it is not sent from the script.
	if ($level == "auditor")
		ob_start();
	
	if (!$css)
		$css = "content";
	if (!count($array) && (!isset($display_table_headers) || $display_table_headers == false)) {
		// display previous page contained in $go_to_prev_page_link (if pages() function detected that the wrong page is displayed then $go_to_prev_page_link will contain the previous page link with the correct page param variable)
		if ($go_to_prev_page_link) {
			print "<meta http-equiv=\"REFRESH\" content=\"3;url=".$go_to_prev_page_link."\">";
			plainmessage("<table class=\"$css\"><tr><td>Page will be refreshed in a few seconds.</td></tr>", false);
			return;
		}

		$plural = get_plural_form($element_name);
		$message = (isset($optional_message)) ? $optional_message : "There aren't any defined $plural.";
		plainmessage("<table class=\"$css\"><tr><td>". $message. "</td></tr>", false);
		if (!count($general_actions)) {
			print '</table>';
			return;
		}
	}

	if (!$base) {
		$main = (isset($_SESSION["main"])) ? $_SESSION["main"] : "main.php";
		$base = "$main?module=$module";
	}

	$do_not_apply_htmlentities = (!$do_not_apply_htmlentities) ? array() : $do_not_apply_htmlentities;
	
	$lines = count($array);
	if ($element_actions)
		$act = count($element_actions);
	else
		$act = NULL;

	print '<table class="'.$css.'" cellspacing="0" cellpadding="0"';
	if ($table_id)
		print " id=\"$table_id\"";
	print '>';

	$order_dir = (!getparam("order_dir") || getparam("order_dir")=="asc") ? "desc" : "asc";
	if ($lines) {
		print '<tr class="'.$css.'">';
		$no_columns = 0;

		$ths ="";
		if($insert_checkboxes) {
			$ths .= '<th class="'.$css.' first_th checkbox">';
			if ($select_all)
				$ths .= '<input type="checkbox" name="select_all" id="select_all" onclick="toggle_column(this);">';
			else
				$ths .= '&nbsp;';
			$ths .= '</th>';
			print $ths;
			$no_columns++;
		}
		// print the name of the columns + add column for each action on object

		$request_link = build_link_request(array("order_by","order_dir"));
		foreach ($formats as $column_name => $var_name) {
			$name = extract_column_name($column_name, $var_name);
			$css_col = extract_column_css($conditional_css, $name);
			
			$ucss = ($no_columns == 0) ? "$css first_th" : $css;
			print '<th class="'.$ucss." ".$css_col.'">';
			$column_link = false;
			if ($order_by_columns) {
				if ((is_numeric($column_name) || count(explode(",",$var_name))==1) && ($order_by_columns===true || !isset($order_by_columns[$name]) || $order_by_columns[$name]==true)) {
					print "<a class='thlink' href='".$request_link."&order_by=$name&order_dir=$order_dir'>".str_replace("_","&nbsp;",ucfirst($name))."</a>";
					$column_link = true;
				}
			}
			if (!$column_link)
				print str_replace("_","&nbsp;",ucfirst($name));
			print '</th>';
			$no_columns++;
		}
		for ($i=0; $i<count($element_actions); $i++) {
/*			print '<th class="'.$css.'">&nbsp;</th>';
			$no_columns++;*/
			print '<th class="'.$css.'">';
			if (!isset($object_actions_names) || !isset($object_actions_names[$i]))
				print '&nbsp;';
			else
				print $object_actions_names[$i];
			print '</th>';
			$no_columns++;
		}
		print '</tr>';
	} else {
		if (!isset($display_table_headers) || $display_table_headers == false) {
			$no_columns = 2;
		} else {
			// display previous page contained in $go_to_prev_page_link (if pages() function detected that the wrong page is displayed then $go_to_prev_page_link will contain the previous page link with the correct page param variable)
			if ($go_to_prev_page_link) {
				print "<meta http-equiv=\"REFRESH\" content=\"3;url=".$go_to_prev_page_link."\">";
				plainmessage("<table class=\"$css\"><tr><td>Page will be refreshed in a few seconds.</td></tr>", false);
				return;
			}
			$plural = get_plural_form($element_name);
			print '<tr class="'.$css.'">';
			$no_columns = 0;
			$ths ="<tr>";
			//$ths .= '<tr>';
			$no_columns = 0; 
			foreach($formats as $column_name => $var_name) {
				$name = extract_column_name($column_name, $var_name);
				$css_col = extract_column_css($conditional_css, $name);
				$headers_name = str_replace("_","&nbsp;",ucfirst($name));

				$ucss = ($no_columns == 0) ? "$css first_th" : $css;
				if ($no_columns == count($formats)-1)
					$ucss = $css . " last_th";
				$ths .= '<th class="'.$ucss.$css_col.'">' . $headers_name . '</th>';
				$no_columns++;

			}

			$ths .= '</tr><tr><td class="'.$css.' last_row left_align_monitoring"  colspan="'.count($formats).'" >';
			print($ths);
			$message = (isset($optional_message)) ? $optional_message : "There aren't any defined $plural.";
			plainmessage($message);
			print('</td></tr>');

			if (!count($general_actions)) {
				print '<tr><td class="last_row" colspan="'.count($formats).'"></td></tr>';
				print '</table>';
				return;
			}
		}
	}

	for ($i=0; $i<count($array); $i++) {
		$cond_css = '';
		foreach ($conditional_css as $css_name => $conditions) {
			if (substr($css_name, 0,10) == "__col_css_")
				continue;
			$add_css = true;
			foreach ($conditions as $column => $cond_column) {
				
				if ($array[$i][$column] != $cond_column) {
					$add_css = false;
					break;
				}
			}
			if ($add_css)
				$cond_css .= " $css_name ";
		}
		if ($i == (count($array)-1))
			$cond_css.= " pre_end";
		print '<tr class="'.$css.'">';
		if ($insert_checkboxes && $id_name) {
			print '<td class="'.$css. "$cond_css";
			if ($i%2 == 0)
				print " evenrow";
			print '">';
			print '<input type="checkbox" name="check_'.$array[$i][$id_name].'"/>';
			print '</td>';
		}
		$j=0;
		foreach ($formats as $column_name => $names_in_array) {
			$name = extract_column_name($column_name, $var_name);
			$css_col = extract_column_css($conditional_css, $name);
			
			print '<td class="'.$css. "$cond_css" . " " . $css_col;
			if ($i%2 == 0)
				print " evenrow";
			if ($j == count($formats)-1)
				print " end_cell";
			print '">';
			$use_vars = explode(",", $names_in_array);
			$exploded_col = explode(":", $column_name);
			$column_value = '';
			$apply_htmlentities = false;
			if (substr($exploded_col[0],0,9) == "function_") {
				$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
				if (in_array($function_name, $do_not_apply_htmlentities)) { 
					$apply_htmlentities = true;
				}
				if (count($use_vars)) {
					$params = array();
					for ($var_nr=0; $var_nr<count($use_vars); $var_nr++)
						if (isset($array[$i][$use_vars[$var_nr]]))
							array_push($params, $array[$i][$use_vars[$var_nr]]);
						else
							array_push($params, null);
					$column_value = call_user_func_array($function_name,$params);
				}
			} elseif (isset($array[$i][$names_in_array])) {
				$column_value = $array[$i][$names_in_array];
			}
			if (strlen($column_value)) {
				$column_value = ($apply_htmlentities) ? $column_value : htmlentities($column_value);
				print $column_value;
			} else
				print "&nbsp;";
			print '</td>';
			$j++;
		}
		$link = '';
		foreach ($array[$i] as $col_name => $col_value) {
			if ($col_name=="password")
				continue;
			if (is_array($col_value))
				continue;
			if (count($build_link_elements)) {
				foreach ($build_link_elements as $k=>$link_element_name)
					if ($col_name == $link_element_name)
						$link .= "&$link_element_name=".urlencode($col_value);
			} else {
				if (strlen($col_value)<55)
					$link .= "&$col_name=".urlencode($col_value);
			}
		}
		$link_no = 0;
		foreach ($element_actions as $methd => $methd_name) {
			print '<td class="'.$css. "$cond_css";
			if ($i%2 == 0)
				print ' evenrow object_action';
			if ($link_no == count($element_actions)-1)
				print ' end_cell';
			print '">';
			if ($link_no)
				print '&nbsp;&nbsp;';
			
			if (substr($methd,0,8) != "__clean_")
				$current_link = $base.$methd.$link;
			else 
				$current_link = substr($methd,8)."?".trim($link,"&");

			if (substr($methd_name,0,11) != "__inactive_")
				print '<a class="'.$css. "$cond_css".'" href="'.htmlentities($current_link).'">'.$methd_name.'</a>';
			else {
				$methd_name = substr($methd_name,11);
				if (substr($methd_name,0,4)=="<img") 
					$methd_name = str_replace("<img", "<img style=\"opacity:0.4\"", $methd_name);
			
				print $methd_name;
			}
			print '</td>';
			$link_no++;
		}
		print '</tr>';
	}

	if ($add_empty_row) {
		$colspan = count($formats) + count($element_actions);
		if ($insert_checkboxes)
			$colspan += 1; 
		print '<tr><td class="last_row" colspan="'.$colspan.'"></td></tr>';
	}
	links_general_actions($general_actions, $no_columns, $css, $base);
	print "</table>";
	
	// catch table before it is displayed and replace Edit with View
	if ($level == "auditor") {
		$table = ob_get_contents();
		ob_end_clean();
		
		echo str_replace(array(">Edit</a>",">Edit </a>", "> Edit</a>", "> Edit </a>"), ">View</a>", $table);
	}
}

/**
 * Used to make confirmation link before an object will be deleted
 * @param $object String the object to be deleted
 * @param $value the value of the param
 * @param $message Text the message to be appended to the question of deletation
 * @param $object_id String the id of the object added in the link
 * @param $value_id String the value id of the object 
 * @param $additional String added to the value id
 * @param $next String use it to change the method param 
 * @param $action_obj String. Action to acknowledge. Default: "delete" 
 * @param $div_class String. Css class of ack message div. Default: "notice"
 */ 
function ack_delete($object, $value = NULL, $message = NULL, $object_id = NULL, $value_id = NULL, $additional = NULL, $next = NULL, $action_obj="delete", $div_class="notice")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method;

	if (!$object_id)
		$object_id = $object.'_id';
	if (!$value_id)
		$value_id = getparam($object_id);

	print "<div class=\"ack_delete ".$div_class."\">Are you sure you want to $action_obj ".str_replace("_","&nbsp;",$object)." $value?";
	if ($message) {
		if (substr($message,0,2) != "__") {
			if(substr($message,0,1) == ",")
				$message = substr($message, 1, strlen($message));
			print " If you delete it you will also delete or set to NULL it's associated objects from $message.";
		} else
			print substr($message,2);
	}

	print "<br/><br/>";

	$link = $_SESSION["main"] .'?';
	if (isset($_SESSION["previous_page"]))
		foreach ($_SESSION["previous_page"] as $param => $value)
			$link .= "$param=$value&";
	$link .= '&module=' . $module . '&method=' . $method . '&action=database&' . $object_id . '=' . $value_id . $additional;
	
	Debug::debug_message(__FUNCTION__, "'Link: ".$link);
	
	print '<a class="llink" href="'.htmlentities($link).'">Yes</a>';

	print '&nbsp;&nbsp;&nbsp;&nbsp;'; 

	if (!$next && isset($_SESSION["previous_page"])) {
		$link = $_SESSION["main"].'?';
		foreach ($_SESSION["previous_page"] as $param => $value)
			$link .= "$param=$value&";
	} else {
		$link = $_SESSION["main"].'?module='.$module.'&method='.$next;
	}
	print '<a class="llink" href="'.htmlentities($link).'">No</a>';
}

/**
 * Sets month and year in dropdown elements
 */ 
function month_year($value = null, $key = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($value);
	return set_month($date["month"],$key).set_year($date["year"],$key);
}

/**
 * Returns month_day_year_hour with the desired format
 */ 
function month_day_year_hour_end($val, $key = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return month_day_year_hour($val,$key,true,true,false);
}

/**
 * Returns day_month_year_hour with the desired format
 */ 
function day_month_year_hour_end($val, $key = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return day_month_year_hour($val,$key,true,true,false);
}

/**
 * Sets month, day, year format in dropdown elements
 */
function month_day_year($val, $key = null, $etiquettes = false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($val);
	$res = set_month($date["month"], $key, $etiquettes);
	$res.= set_day($date["day"], $key, $etiquettes);
	$res.= set_year($date["year"], $key, $etiquettes);
	return $res;	
}

/**
 * Sets month, day, year, hour format in dropdown elements
 */

function month_day_year_hour($date, $key = '', $default_today = true, $etiquettes = true, $begin_hour = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($date, $default_today, $begin_hour);
	$res = set_month($date["month"], $key, $etiquettes);
	$res.= set_day($date["day"], $key, $etiquettes);
	$res.= set_year($date["year"], $key, $etiquettes);
	$res.= set_hour($date["hour"], $key, $etiquettes);
	return $res;
}

/**
 * Sets day, month, year, hour in dropdown elements
 */ 
function day_month_year_hour($date, $key = '', $default_today = true, $etiquettes = true, $begin_hour = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = params_date($date, $default_today, $begin_hour);
	$res = set_day($date["day"], $key, $etiquettes);
	$res.= set_month($date["month"], $key, $etiquettes);
	$res.= set_year($date["year"], $key, $etiquettes);
	$res.= set_hour($date["hour"], $key, $etiquettes);
	return $res;
}

/**
 * Returns an array with day, month, year, hour
 */ 
function params_date($date, $default_today = false, $begin_hour = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$month = $day = $year = $hour = "";
	if ((!$date || $date == '') && $default_today) {
		/*$today = explode("-",date('F-j-Y-H-i',time()));
		$month = $today[0]; 
		$day = $today[1]; 
		$year = $today[2];*/
		$today = date("Y-m-d H:i:s");
		$today = check_timezone($today);
		$expl = explode(" ",$today);
		$date = explode("-",$expl[0]);
		$time = explode(":",$expl[1]);
		$month = $date[1];
		$day = $date[2];
		$year = $date[0];
		if ($begin_hour === true)
			$hour = "0";
		elseif ($begin_hour === false)
			$hour = "23";
		else
			$hour = $today[3];
	} elseif ($date && $date != "") {
		$today = explode(" ",$date);
		$hour = explode(":",$today[1]);
		$hour = $hour[0];
		$today = explode("-",$today[0]); 
		$year = $today[0];
		$month = $today[1]; 
		$day = $today[2]; 
	}
	return array("day"=>$day, "month"=>$month, "year"=>$year, "hour"=>$hour);
}

/**
 * Sets hour in dropdown
 */ 
function set_hour($hour, $key = '', $etiquettes = false, $js = '')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = "";
	if ($etiquettes)
		$res .= "&nbsp;Hour:&nbsp;";
	$res.= '<select name="'.$key.'hour" '.$js.'>'."\n";
	for ($i=0; $i<24; $i++) {
		$selected = ($hour == $i) ? "SELECTED" : "";
		$res.= "<option $selected>".$i."</option>"."\n";
	}
	$res.= "</select>";
	return $res;
}

/**
 * Sets Year in dropdown
 */
function set_year($year, $key = '', $etiquettes = false, $js = '')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = "";
	if ($etiquettes)
		$res .= "&nbsp;Year:&nbsp;";
	$res.= '<input type="text" name="'.$key.'year" value="'.$year.'" size="4" '.$js.'/>'."\n";
	return $res;
}

/**
 * Sets month in dropdown
 */
function set_month($month, $key='', $etiquettes=false, $default_today=false, $js='')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$months = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");;
	$res = "";
	if ($etiquettes)
		$res .= "Month:&nbsp;";
	$res .= '<select name="'.$key.'month" '.$js.'>'."\n";
	if (!$default_today)
		$res.= "<option value=\"\">-</option>";
	for ($i=0; $i<count($months); $i++) {
		$index = $i+1;
		$d_index = (strlen($index)==1) ? "0".$index : $index;
		$selected = ($month == $months[$i] || $month == $index || $month == $d_index) ? "SELECTED" : "";
		$res .= "<option $selected>".$months[$i]."</option>"."\n";
	}
	$res.= "</select>"."\n";
	return $res;
}

/**
 * Sets day in dropdown
 */ 
function set_day($day, $key='', $etiquettes=false, $default_today=false, $js="")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$res = "";
	if ($etiquettes)
		$res .= "Day:&nbsp;";
	$res.= '<select name="'.$key.'day" '.$js.'>'."\n";
	if (!$default_today)
		$res.= "<option value=\"\">-</option>";
	for ($i=1; $i<32; $i++) {
		$selected = ($day == $i || $day == "0".$i) ? "SELECTED" : "";
		$res .= "<option $selected>".$i."</option>"."\n";
	}
	$res.= "</select>"."\n";
	return $res;
}

/**
 * Truncate seconds from time
 */ 
function seconds_trunc($time, $floor=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $date_format;

	$date = explode(" ",$time);
	if (count($date)>1) {
		// not just "time" but "date and time"
		if ($date_format == "d-m-y") {
			$new_date = european_date($date[0]);
			$time = $new_date." ".$date[1];
		}
	}

	$date = explode('.',$time);
	$date = $date[0];
	$date = explode(":",$date);
	$sec = count($date) - 1;

	if (!$floor)
		$date[$sec] ++;

	if (strlen($date[$sec]) == 1)
		$date[$sec] = '0'.$date[$sec];
	if ($date[$sec] == 60) {
		$date[$sec-1]++;
		$date[$sec] = 0;
	}
	$date = implode(":",$date);
	return $date;
}
/**
 * Truncate seconds with rounding up
 */ 
function seconds_trunc_floor($time)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return seconds_trunc($time,true);
}

/**
 * Returns european date format if global is set and has the specific format
 * or takes the date from timestamp 
 */
function select_date($timestamp)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $date_format;

	$timestamp = check_timezone($timestamp);
	$timestamp = explode(" ",$timestamp);
	$date = $timestamp[0];
	if (isset($date_format) && $date_format == "d-m-y")
		$date = european_date($date);
	return $date;
}
/**
 * Returns european date format
 */ 
function european_date($date)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$date = explode("-",$date);
	return $date[2]."-".$date[1]."-".$date[0];
}

/**
 * Select time from a timestamp
 */ 
function select_time($timestamp)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$timestamp = check_timezone($timestamp);
	$date = seconds_trunc($timestamp);
	$date = explode(' ',$date);
	return $date[1];
}

/**
 * Returns timestamp without miliseconds
 */ 
function trunc_date($timestamp)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $date_format;

	$timestamp = check_timezone($timestamp);
	$date = explode(" ",$timestamp);
	if (count($date)>1) {
		// not just "time" but "date and time"
		if ($date_format == "d-m-y") {
			$new_date = european_date($date[0]);
			$timestamp = $new_date." ".$date[1];
		}
	}
	$timestamp = explode('.',$timestamp);
	return $timestamp[0];
}

/**
 * Format for $timestamp must be Y-m-d H:i:s.miliseconds
 */
function check_timezone($timestamp, $reverse_apply=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $system_standard_timezone;

	// check if $_SESSION["timezone"] is set and if is different from system timezone
	if (!isset($_SESSION["timezone"]))
		return $timestamp;

	$timezone = str_replace("GMT","",$_SESSION["timezone"]);
	if ($timezone == "")
		$timezone = 0;
/*	$system_tz = str_replace("0","",date("P"));
	$system_tz = str_replace(":","",$system_tz);*/
	$system_tz = str_replace("GMT","",$system_standard_timezone);
	if ($system_tz == "")
		$system_tz = 0;

	$diff = $timezone - $system_tz;
	if ($reverse_apply)
		$diff = -$diff;

	$arr = explode(".",$timestamp);
	$milisecs = (isset($arr[1])) ? $arr[1] : null;
	$date = $arr[0];
	$date = explode(" ",$date);
	$time = explode(":",$date[1]);
	$date = explode("-",$date[0]);
	$hours = $time[0] + $diff;	
	$new_timestamp = gmdate("Y-m-d H:i:s", gmmktime($hours, $time[1], $time[2], $date[1], $date[2], $date[0]));
	return $new_timestamp;
}

/**
 * Get the difference between session set timezone and system timezone
 */ 
function get_timezone_diff($reverse_apply=false)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $system_standard_timezone;

	// check if $_SESSION["timezone"] is set and if is different from system timezone
	if (!isset($_SESSION["timezone"]))
		return "0";

	$timezone = str_replace("GMT","",$_SESSION["timezone"]);
	if ($timezone == "")
		$timezone = 0;
/*	$system_tz = str_replace("0","",date("P"));
	$system_tz = str_replace(":","",$system_tz);*/
	$system_tz = str_replace("GMT","",$system_standard_timezone);
	if ($system_tz == "")
		$system_tz = 0;

	$diff = $timezone - $system_tz;
	if ($reverse_apply)
		$diff = -$diff;

	return $diff;
}

/**
 * Returns an interval from a timestamp
 */ 
function add_interval($timestamp, $unit, $nr)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$timestamp = explode(" " ,$timestamp);
	$date = explode("-",$timestamp[0]);
	$time = explode(".",$timestamp[1]);
	$time = explode(":",$time[0]);
	$year = $date[0];
	$month = $date[1];
	$day = $date[2];
	$hours = $time[0];
	$minutes = $time[1];
	$seconds = $time[2];

	${$unit} += $nr;
	$date = date('Y-m-d H:i:s',gmmktime($hours,$minutes,$seconds,$month,$day,$year));

	return $date;
}

/**
 * Get Unix timestamp for a GMT date
 */ 
function get_time($key='',$hour2='00',$min='00',$sec="00")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$day = getparam($key."day");
	if (!$day)
		return null;
	$month = getparam($key."month");
	$month = getmonthnumber($month);
	if (strlen($month))
		$month = '0'.$month;
	$year = getparam($key."year");
	$hour = getparam($key."hour");
	if (!$hour)
		$hour = $hour2;

	if (!checkdate($month,$day,$year)) {
		errormess("This date does not exit : day=".$day.' month='.$month.' year='.$year,'no');
		return;
	}
	$date = gmmktime($hour,$min,0,$month,$day,$year);
	return $date;
}

/**
 * Returns the DATE in a specific format from $_REQUEST
 * The format is a string: "year-month-day H:M:S"
 */ 
function get_date($key='',$_hour='00',$min='00',$sec='00')
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$day = getparam($key."day");
	$month = getparam($key."month");
	$year = getparam($key."year");
	if (!$day || !$month || !$year)
		return null;
	$month = getmonthnumber($month);
	$hour = getparam($key."hour");
	if (strlen(!$hour))
		$hour = $_hour;
	if (strlen($hour)==1)
		$hour = "0".$hour;

	if (!checkdate($month,$day,$year)) {
		errormess("This date does not exit : day=".$day.' month='.$month.' year='.$year,'no');
		return;
	}
	if (strlen($month) == 1)
		$month = '0'.$month;
	if (strlen($day) == 1)
		$day = '0'.$day;
	if (strlen($_hour))
		$date = "$year-$month-$day $hour:$min:$sec";
	else
		$date = "$year-$month-$day";
	return $date;
}

/**
 * Display letters in alphabetic order, each is a link except the one selected
 * @param $link String the base of the link on which will be added params:
 * module, method and letter
 * @param $force_method String the new method to be added in link, default NULL
 * @return $letter the selected letter 
 */   
function insert_letters($link = "main.php", $force_method = NULL, $letter = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method;

	if (is_array($module))
		$module = $module[0];
	if ($force_method)
		$method = $force_method;

	if (!$letter)
		$letter = getparam("letter") ? getparam("letter") : 'A';

	$letters = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "W", "Z");

	for ($i=0; $i<count($letters); $i++) {
		print '&nbsp;';
		if ($letter == $letters[$i])
			print $letter;
		else {
			print '<a class="llink" href="'.$link;
			if (!strpbrk($link,"?"))
				print '?';
			else
				print "&";
			print 'module='.$module.'&method='.$method.'&letter='.$letters[$i].'">'.$letters[$i].'</a>';
		}
	}
	print '<br/><hr>';
	return $letter;
}

/**
 * Display 10 numbers each as a link, the selected one is displayed without link.
 * Returns the selected number.
 * @param $link String contains the base of the link on which will be added parameters:
 * module, method and the number
 * @return the selected number
 */ 
function insert_numbers($link = "main.php")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module,$method;

	$nr = getparam("nr");
	if (!$nr)
		$nr = 0;

	if (is_array($module))
		$module = $module[0];

	for ($i=0; $i<10; $i++) {
		print '&nbsp;';
		if ($nr == $i)
			print $nr;
		else
			print '<a class="llink" href="'.$link.'?module='.$module.'&method='.$method.'&nr='.$i.'">'.$i.'</a>';
	}
	print '<br/><hr>';
	return $nr;
}

/** 
 * Returns minutes from interval with format HOURS:MINUTES:SECONDS
 * The result is a rounded float value to 2 decimals
 */ 
function interval_to_minutes($interval)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$interval)
		return NULL;
	$interval2 = explode(':',$interval);

	return round($interval2[0]*60+$interval2[1]+$interval2[2]/60,2);
}

/** 
 * Returns string with format "HOURS:MINUTES:00" as interval
 * from a given integer as minutes
 * @param $minutes Integer the minutes that will be transformed
 * in interval
 */ 
function minutes_to_interval($minutes = NULL)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	//minutes should be integer: ignoring seconds
	$minutes = floor($minutes);

	if (!$minutes)
		return '00:00:00';

	$hours = floor($minutes / 60);
	$mins = $minutes - $hours*60;

	//don't care if $hours > 24
	if (strlen($hours) == 1)
		$hours = '0'.$hours;
	if (strlen($mins) == 1)
		$mins = '0'.$mins;
	return "$hours:$mins:00";
}

/**
 * Generate a random chars of a default length of 8
 * @param $lim Integer the length of the random generated 
 * characters
 */ 
function gen_random($lim = 8)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$nr = '';
	for ($digit = 0; $digit < $lim; $digit++) {
		$r = rand(0,1);
		$c = ($r==0) ? rand(65,90) : rand(97,122);
		$nr .= chr($c);
	}
	return $nr;
}

/**
 * Returns the corresponding number from given month 
 * @param $month String the name of the month written in english or 
 * romanian language
 */ 
function getmonthnumber($month)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	switch($month){
		case "January":
		case "Ianuarie":
			return '1';
		case "February":
		case "Februarie":
			return '2';
		case "March":
		case "Martie":
			return '3';
		case "April":
		case "Aprilie":
			return '4';
		case "May":
		case "Mai":
			return '5';
		case "June":
		case "Iunie":
			return '6';
		case "July":
		case "Iulie":
			return '7';
		case "August":
			return '8';
		case "September":
		case "Septembrie":
			return '9';
		case "October":
		case "Octombrie":
			return '10';
		case "November":
		case "Noiembrie":
			return '11';
		case "December":
		case "Decembrie":
			return '12';
	}
	return false;
}

/**
 * Returns month name from given number
 * @param $nr String contains the number of the month 
 */ 
function get_month($nr)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	switch($nr) {
		case "13":
		case "1":
		case "01":
			return "January";
		case "2":
		case "02":
		case "14":
			return "February";
		case "3":
		case "03":
		case "15":
			return "March";
		case "4":
		case "04":
			return "April";
		case "5":
		case "05":
			return "May";
		case "6":
		case "06":
			return "June";
		case "7":
		case "07":
			return "July";
		case "8":
		case "08":
			return "August";
		case "9":
		case "09":
			return "September";
		case "10":
			return "October";
		case "11":
			return "November";
		case "12":
			return "December";
		default:
			return "Invalid month: $nr";
	}
}

/**
 * Remove special chars from value so that it will contain only digits
 */ 
function make_number($value)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$value = str_replace(array("$", ' ', '%', '&'), "", $value);
	return $value;
}

/**
 * Returns the name for a .jpg without spaces
 */ 
function make_picture($name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$name = strtolower($name);
	$name = str_replace(" ","_",$name);
	$name .= '.jpg';
	return $name;
}

/**
 * Builds the link for next page
 */ 
function next_page($max)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $method, $limit;
	$offset = getparam("offset");
	if (!$offset)
		$offset = 0;

	$minus = $offset - $limit;
	$plus = $offset + $limit;
	$page = number_format($offset / $limit) + 1;
	print '<br/><center>';
	if ($minus >= 0)
		print '<a class="llink" href="main.php?module='.$module.'&method='.$method.'&offset='.$minus.'&max='.$max.'"><<</a>&nbsp;&nbsp;&nbsp;';
	print $page;
	if ($plus < $max)
		print '&nbsp;&nbsp;&nbsp<a class="llink" href="main.php?module='.$module.'&method='.$method.'&offset='.$plus.'&max='.$max.'">>></a>&nbsp;&nbsp;&nbsp;';
	print '</center>';
}

/**
 * Removes parameters from $_REQUEST
 */ 
function unsetparam($param)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (isset($_POST[$param]))
		unset($_POST[$param]);
	if (isset($_GET[$param]))
		unset($_GET[$param]);
	if (isset($_REQUEST[$param]))
		unset($_REQUEST[$param]);
}

/**
 * Returns a string from bytes
 */ 
function bytestostring($size, $precision = 0) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'kB', 'B');
	$total = count($sizes);

	while($total-- && $size > 1024) 
		$size /= 1024;

	return round($size, $precision).$sizes[$total];
}

/**
 * Returns an array with the parameters from $_REQUEST 
 */ 
function form_params($fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");

	$params = array();
	for ($i=0; $i<count($fields); $i++)
		$params[$fields[$i]] = getparam($fields[$i]);
	return $params;
}

/**
 * Returns a field value if exists in an array or NULL otherwize
 */ 
function field_value($field, $array)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (isset($array[$field]))
		return $array[$field];
	return NULL;
}

/**
 * Displays a table in a div element.
 * The table has 2 rows:
 * first row contains a logo image and a title
 * the second row contains the method / default explanation
 * The style of the container of the table can be changed
 */
function explanations($logo, $title, $explanations, $style="explanation")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method;

	if (is_array($explanations))
		if (isset($explanations[$method]))
			$text = $explanations[$method];
		else
			$text = $explanations["default"];
	else
		$text = $explanations;
?>
<div class="<?php print $style; ?>">
    <table class="fillall" cellspacing="0" cellpadding="0">
	<tr>
	    <td class="logo_wizard" style="padding:5px;">
<?php	if ($logo && $logo != "") {?>
		<img src="<?php $logo; ?>" />
<?php	}
?>
	    </td>
	    <td class="title_wizard" style="padding:5px;">
		<div class="title_wizard"><?php print $title; ?></div>
	    </td>';	
	</tr>
	<tr>
	    <td class="step_description" style="font-size:13px;padding:5px;" colspan="2">
<?php		print $text; ?>
	    </td>
	</tr>
    </table>
</div>
<?php
}

/**
 * Builds the HTML dropdown
 */ 
function build_dropdown($arr, $name, $show_not_selected = true, $disabled = "", $css = "", $javascript = "", $just_options = false, $hidden = false, $set_not_selected = null, $use_default_keys = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$just_options) {
		$hidden = ($hidden) ? " style=\"display:none;\"" : "";
		$res = '<select class="dropdown '.$css.'" name="'.$name.'" id="'.$name.'" '.$disabled.$hidden.' ';
		if (substr($name,-2) == "[]")
			$res .= " multiple=\"multiple\" size=\"3\"";
		$res .= $javascript.'>'."\n";
	} else
		$res = '';
	if ($show_not_selected) {
		$val = "";
		if ($set_not_selected)
			$val = $set_not_selected;
		$res .= '<option value="'.$val.'"> - </option>'."\n";
	}
	$selected = (isset($arr["selected"]))? $arr["selected"] : "";
	unset($arr["selected"]);
	for ($i=0; $i<count($arr); $i++) {
		if (is_array($arr[$i])) {
			if ($use_default_keys) {
				$value = $arr[$i]["field_id"];
				$value_name = $arr[$i]["field_name"];
			} else {
				$value = $arr[$i]["{$name}_id"];
				$value_name = $arr[$i]["{$name}"];
			}
			$css = (isset($arr[$i]["css"])) ? 'class="'.$arr[$i]["css"].'"' : "";
			$res .= "<option value=\"$value\" $css";
			if (is_array($selected)) {
				if (in_array($value,$selected))
					$res .= " SELECTED";
			} else {
				if ($selected == $value)
					$res .= " SELECTED";
			}
			if ($value === "__disabled")
				$res .= " disabled=\"disabled\"";
			$res .= ">$value_name</option>\n";
		}
	}
	if (!$just_options)
		$res .= "</select>\n";
	return $res;
}

/**
 * Builds the format for the dropdown 
 */ 
function format_for_dropdown($vals)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$arr = array();
	for ($i=0; $i<count($vals); $i++)
		array_push($arr, array("field_id"=>$vals[$i], "field_name"=>$vals[$i]));
	return $arr;
}

/**
 * Builds the format for the dropdown with the possibility
 * of the option to be different from value displayed
 * @param $vals Array contains the ids and the values of elements
 * @param $field_name String the name of the field
 */ 
function format_for_dropdown_assoc_opt($vals,$field_name)
{
	$arr = array();
	foreach ($vals as $key => $val) {
		array_push($arr, array("{$field_name}_id"=>$key, "{$field_name}"=>$val));
	}
	return $arr;
}

/**
 * Builds the format for the dropdown with the possibility
 * of the option to be different from value displayed 
 *
 * @param $vals Array contains the ids and the values of elements
 * @param $field String the name of the field
 * @param $set_id Bool if true the option selected is the id of the element
 * otherwise the option selected is the same as the value
 * @return $arr Array with the specific format for the dropdown
 */ 
function format_opt_for_dropdown($vals, $field="field", $set_id=false, $use_default_keys=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$arr = array();
	foreach ($vals as $id=>$value) {
		$field_id = $value;
		if ($set_id)
			$field_id = $id;
		$field_name = (!$use_default_keys) ? $field : $field."_name";
		array_push($arr, array($field."_id"=>$field_id, $field_name=>$value));
	}
	return $arr;
}
/**
 * Build a table with the rows of a html form data
 * Uses display_pair() to display the rows
 * @param $rows Array the fields displayed in each row of the table
 * @param $th Array display each element in <th>. If width is present in array, add it in the 'style' of the element
 * @param $title String the title for this <form> displayed in <table>
 * @param $submit String the submit element displayed in <table>
 * @param $width String The custom with of the table
 * @param $id String The custom id of the table 
 * @param $css_first_column String the name of the first td element in the table
 * @param $color_indexes Array contains the ids of the rows that have custom css class 'custom_border'
 * @param $dif_css String custom class of the css for all <td> elements
 * @param $th_css String custom css for the <th> rows in <table>
 */ 
function formTable($rows, $th = null, $title = null, $submit = null, $width = null, $id = null, $css_first_column = '', $color_indexes = array(), $dif_css = "", $th_css = "")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!is_array($rows))
		$rows = array();

	if (is_array($th))
		$cols = count($th);
	elseif (isset($rows[0]))
		$cols = count($rows[0]);
	else
		$cols = count($rows);
	if ($width && substr($width,-2) != "px" && substr($width,-1) != "%")
		$width .= "px";
	$width = ($width) ? "style=\"width:".$width.";\"" : "";
	$id = ($id) ? " id=\"$id\"" : "";
	print '<table class="formtable '.$dif_css.'" cellspacing="0" cellpadding="0" '.$width.' '.$id.'>'."\n";
	if ($title) {
		print "<tr>\n";
		print '<th class="title_formtable '.$dif_css.'" colspan="'.$cols.'">'.$title.'</th>'."\n";
		print "</tr>\n";
	}
	if (is_array($th)) {
		print "<tr>\n";
		for ($i=0; $i<count($th); $i++) {
			if (is_array($th[$i])) {
				$style = "style=\"width:".$th[$i]["width"].";\"";
				$info = $th[$i][0];
			} else {
				$style = "";
				$info = $th[$i];
			}
			print "<th class=\"formtable ".$th_css."\" $style>".$info."</th>\n";
		}
		print "</tr>\n";
	}
	if (isset($rows[0])) {
		for ($i=0; $i<count($rows); $i++) {
			$row = $rows[$i];
			$custom_css = (in_array($i,$color_indexes)) ? "custom_border" : "";
			print "<tr>\n";
			if (is_array($row)) {
				for ($j=0; $j<count($row); $j++) {
					$css = ($i%2 == 0) ? "formtable evenrow_ftable $dif_css" : "formtable $dif_css";
					if (!$th && $i==0 && $j==0)
						$css .= "firsttd";
					if ($css_first_column && $j == 0)
						$css .= " $css_first_column"."";
					print "<td class=\"$css $custom_css\">". $row[$j] ."</td>\n";
				}
			} else {
				print '<td class="white_row" colspan="'.$cols.'">'.$row.'</td>';
			}
			print "</tr>\n";
		}
	} else {
		$i = 0;
		foreach ($rows as $key => $format) {
			print "<tr>\n";
			$css = ($i%2 === 0) ? "formtable evenrow $dif_css" : "formtable oddrow $dif_css";
			display_pair($key, $format, null, null, $css, null, null);
			print "</tr>\n";
			$i++;
		}
	}
	if ($submit) {
		print "<tr>\n";
		print "<td class=\"submit_formtable\" colspan=$cols>";
		print $submit;
		print "</td>";
		print "</tr>\n";
	}
	print "</table>\n";
}

/**
 * Builds a HTML <form> with the formTable() builded table
 */ 
function set_default_object($fields, $selected, $method, $message)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	start_form();
	if ($method)
		addHidden("database", array("method"=>$method));
	if (!$selected) {
		subtitle($message);
		print '<table class="error" cellspacing="0" cellpadding="0"><tr><td>'."\n";
	}
	formTable($fields, null, null, null, null, null, '', array(), "blue_letters");
	if (!$selected)
		print '</td></tr></table>'."\n";
	else
		hr();
	end_form();
	br();
}

/**
 * Sets the fields value by using getparam() to take the data set in FORM
 * @param type $fields
 * @param array $error_fields
 * @param type $field_prefix
 * @param type $use_urldecode
 * @param type $apply_conditions. Array used to detect if form was submitted.
 *				  Examples:
 *					- array("method"=>{name_of_the_method_used_for_validate_form}) - if method used for validate is not {$method."_".$action}
 *					- array("action"=>{name_of_the_action}) - when action is not "database" and method used for validate is {$method."_".$action}
 */
function set_form_fields(&$fields, $error_fields, $field_prefix='', $use_urldecode=false, $apply_conditions=array())
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$error_fields)
		$error_fields = array();

	foreach ($fields as $name=>$def) {
		if (!isset($def["display"]))
			$def["display"] = "text";
		if ($def["display"] == "hidden" || $def["display"]=="message" || $def["display"]=="fixed" || $def["display"]=="objtitle" || $def["display"]=="custom_field" || $def["display"]=="custom_submit")
			continue;
		if (in_array(strtolower($name), $error_fields))
			$fields[$name]["error"] = true;
		if (substr($name,-2) == "[]" && $def["display"] == "mul_select")
			$val = (isset($_REQUEST[$field_prefix.substr($name,0,strlen($name)-2)])) ? $_REQUEST[$field_prefix.substr($name,0,strlen($name)-2)] : null;
		elseif ($def["display"] == "date_time") {
			$val = array(
				"date"=>null,
				"time"=>null,
				"timezone"=>null
			);
			if (isset($_REQUEST["{$field_prefix}{$name}_date"]))
				$val["date"] = $_REQUEST["{$field_prefix}{$name}_date"];
			if (isset($_REQUEST["{$field_prefix}{$name}_time"]))
				$val["time"] = $_REQUEST["{$field_prefix}{$name}_time"];
			if (isset($_REQUEST["{$field_prefix}{$name}_timezone"]))
				$val["timezone"] = $_REQUEST["{$field_prefix}{$name}_timezone"];

			if ($val["date"]===null && $val["time"]===null && $val["timezone"]===null )
				$val = null;
		} else
			$val = (isset($_REQUEST[$field_prefix.$name])) ? $_REQUEST[$field_prefix.$name] : null;

		if ($use_urldecode)
			$val = urldecode($val);
		
		//if form_val already set don't override it
		if (isset($fields[$name]["form_val"]))
			continue;
			
		if ($val!==NULL) {
			if ($def["display"] == "checkbox")
				$fields[$name]["form_val"] = ($val == "on") ? "t" : "f";
			else
				$fields[$name]["form_val"] = $val;
		} else {
			// keep checkbox unchecked and mul_select or checkbox-group unselected
			// will return false also if {$apply_conditions is empty && $keep_form_values not defined in defaults.php}
			if (is_form_submited($apply_conditions)) {
				if ($def["display"]=="checkbox")
					$fields[$name]["form_val"] = "f";
				
				if ($def["display"]=="mul_select" || $def["display"]=="checkbox-group")
					$fields[$name]["form_val"] = "";
			}
		}
	}
}

/**
 * Check the error message and add specified error fields in error_fields array
 * @param $error String representing the error message. In order for fields to be added they must be marked with 'error_on_column'
 * Ex: Please set 'username' and 'email'. 
 * @param &$error_fields Array containing fields where errors occured. Fields parsed from $error will be added to it.
 */ 
function set_error_fields($error, &$error_fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	// fields between '' are considered names
	$field = '';
	$start = false;
	$error = strtolower($error);
	for ($i=0; $i<strlen($error); $i++) {
		if ($error[$i] != "'") {
			if ($start)
				$field .= $error[$i];
		} else {
			if ($start) {
				if (!in_array($field, $error_fields))
					$error_fields[] = $field;
				$field = '';
				$start = false;
			} else
				$start = true;
		}
	}
}

/**
 * Handle the errors by displaying the error using errormess();
 * sets the errors in array fields and then sets the data in form fields
 * @param string $error. True if encountered errors. False otherwise.
 * @param array $fields. Array containing the form fields (with the default values in "value" key)
 * @param array $error_fields. Array containing the fields with errors.
 * @param string $field_prefix. Defaults to empty string.
 * @param bool $use_urldecode. Defaults to false.
 * @param bool $encode_error_text. Defaults to true.
 * @param array $apply_conditions. Used in set_form_fields().
 *				  Conditions to detect form submit. Defaults to empty array.
 *				  Examples:
 *					- array("method"=>{name_of_the_method_used_for_validate_form}) - if method used for validate is not {$method."_".$action}
 *					- array("action"=>{name_of_the_action}) - when action is not "database" and method used for validate is {$method."_".$action}
 * @param string $extra_css Name(s) of css class(es) to be added for error messages. Separate classes by space.
 */
function error_handle($error, &$fields, &$error_fields, $field_prefix='', $use_urldecode=false, $encode_error_text=true, $apply_conditions=array(), $extra_css="")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if ($error) {
		errormess($error,"no","Go back to application", $encode_error_text, "", "", $extra_css);
		set_error_fields($error, $error_fields);
		set_form_fields($fields, $error_fields, $field_prefix, $use_urldecode, $apply_conditions);
	}
	if ($error===false) {
		set_error_fields($error, $error_fields);
		set_form_fields($fields, $error_fields, $field_prefix, $use_urldecode, $apply_conditions);
	}
}

/**
 * Display return button with the built link from previous page data or with a custom return link specified on 'onclick' event.
 * 
 * @param string $method Default is null. The method specified in the return link. If not set, return link is built from information stored in "previous_page" from session. If not found in session return link will contain only the value of the global $module.
 * @param string $_module Default is null. If set, return link will be built with specified module instead of the one stored in the global $module.
 * @param string $align Default is "right". Is the alignment of the container div, if container div is set.
 * @param string $name Default is "Return". Is the name of the return button.
 * @param bool $div_container Default is true. When true, button is displayed inside a container div.
 * @param string $css Default is "llink". Is the css class used for the button.
 * @param string $onclick Default is null. When set button uses onclick event instead of href, with the value of the $onclick parameter.
 */ 
function return_button($method=null, $_module=null, $align="right", $name="Return", $div_container=true, $css="llink", $onclick=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	$fa_icon = "";
	if (is_addon("font-awesome")) {
		$fa_icon = "<i class='fa fa-arrow-left $css' aria-hidden='true'>&nbsp;</i>";
	}
	
	if ($onclick) {
		if ($div_container) {
			print "<div style='float:".$align."'><a class='{$css}' onclick=\"$onclick\">".$fa_icon.$name."</a></div>";
			br(2);
			return;
		} else {
			print "<a class='{$css}' onclick=\"$onclick\">".$fa_icon.$name."</a>";
			return;
		}
	}
	
	if ($method) {
		if (!$_module)
			$_module = $module;
		$link = $_SESSION["main"].'?module='.$_module.'&method='.$method;
	} elseif (isset($_SESSION["previous_page"])) {
		$link = $_SESSION["main"]."?";
		foreach ($_SESSION["previous_page"] as $param=>$value) {
			if (is_array($value) || is_object ($value))
				continue;
			$link.= "$param=".urlencode($value)."&";
		}
	} else
		$link = $_SESSION["main"]. "?"."module=".$module;
	
	if ($div_container) {
		print '<div style="float:'.$align.'"><a class="'.$css.'" href="'.$link.'">'.$fa_icon.$name.'</a></div>';
		br(2);
	} else
		print '<a class="'.$css.'" href="'.$link.'">'.$fa_icon.$name.'</a>';
}

/**
 * Display a HTML <hr> element with css class 'bluehr'
 */ 
function hr()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	print "<hr class=\"bluehr\" />";
}

/**
 * Return true if module was found in the array $exceptions_to_save
 */ 
function exception_to_save()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method, $module, $exceptions_to_save;

	if (isset($exceptions_to_save) && isset($exceptions_to_save[$_SESSION["level"]]) && isset($exceptions_to_save[$_SESSION["level"]]["$module"]) && (in_array($method, $exceptions_to_save[$_SESSION["level"]]["$module"]) || (in_array("__all", $exceptions_to_save[$_SESSION["level"]]["$module"]))))
		return true;

	if (!isset($_SESSION["level"]) && isset($exceptions_to_save["__default"][$method]))
		return true;

	return false;
}

/**
 * Saves page info, puts the parameters from $_REQUEST in array $_SESSION["previous_page"]
 * If exceptions_to_save() is true and method begins with one of: edit,add_,delete, import, export, view_
 * then page info is not saved.
 * 
 * !! It is really important to test the whole project again if this is added later on, not at the start of the project.
 * Information added in this SESSION field is automatically added in forms/links built in ansql, and it might override the visual fields from forms/start of links
 */ 
function save_page_info()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method, $module, $exceptions_to_save;

	// don't save info for edit/add/delete pages
	if (substr($method,0,4) == "edit" || substr($method,0,4) == "add_" || substr($method,0,5) == "view_" || substr($method,0,6) == "delete" || exception_to_save() || substr($method,0,6)=="import" || substr($method,0,6)=="export") {
		return;
	}

	$_SESSION["previous_page"] = array();

	$param_exceptions = array("PHPSESSID", "old_submit");
	foreach ($_REQUEST as $param => $value) {
		if (in_array($param, $param_exceptions) || substr($param,0,5)=="__utm")
			continue;
		if ($param == "module")
			$value = $module;
		elseif ($param == "method")
			$value = $method;
		$_SESSION["previous_page"][$param] = $value;
	}
}

/**
 * Creates a HTML form structure to make a routing test
 */ 
function make_routing_test($fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module;

	if ($_SESSION["level"] != "admin")
		return;

	if (!$fields) 
		$fields = array("called"=>"", "caller"=>"");

	$fields["th_custom"] = array("display"=>"message", "value"=>"<div style=\"width:100px; float:left; text-align:center;\">Param</div>   <div style=\"width:100px; float:left; position:relative; text-align:center;\">Value</div>", "triggered_by"=>"1");
	$fields["custom_1"] = array("display"=>"message", "value"=>link_for_custom_field(1));
	for ($i=1; $i<=20; $i++)
		$fields["custom$i"] = array("display"=>"message", "triggered_by"=>"$i", "value"=>routing_test_custom_field($i));

	start_form();
	addHidden("yate");
	editObject(null, $fields, "Make routing test", "Send");
	end_form();
}

/**
 * Creates link for a custom field with a predefined index value
 */ 
function link_for_custom_field($index)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$ret = "<a class=\"llink\" id=\"custom_field_index$index\" onClick=\"";
	$ret.= ($index == 1) ? "show_hide('tr_th_custom');" : "";
	$ret.= "show_hide('tr_custom$index'); show_hide('custom_field_index$index');";
	if ($index == 1)
		$ret.= "show_hide('tr_custom_$index');";
	$ret.= "\" > Add parameter</a>";
	return $ret;
}

/**
 * Creates the custom input fields for routing test
 */ 
function routing_test_custom_field($index)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$param_name = getparam("param_name$index");
	$param_value = getparam("param_value$index");
	$ret = "<input type=\"text\" name=\"param_name$index\" value=\"$param_name\" style=\"width:100px;\"/> = \n";
	$ret.= "<input type=\"text\" name=\"param_value$index\" value=\"$param_value\" style=\"width:100px;\" />";
	$index++;
	$ret.= link_for_custom_field($index);
	return $ret;
}

/**
 * Returns the fields containing the parameters 
 * and their values from the routing test yate form
 */ 
function make_routing_test_yate($fields=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$fields) {
		$fields = array();
		$fields["called"] = getparam("called");
		$fields["caller"] = getparam("caller");
	}

	for ($i=1; $i<=20; $i++) {
		if (!getparam("param_name$i"))
			continue;
		$fields[getparam("param_name$i")] = getparam("param_value$i");
	}
	return $fields;
}

/**
 * Sends routing test command with fields parameters and values 
 * through the socket connection and return an array with 
 * true set on first key and the response of the given command
 * on second key  if no errors where encounted
 * If errors are found prints the error and returns
 * an array with false as the value of the first key
 */ 
function send_routing_test_yate($fields)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$command = "routing_test ";
	foreach ($fields as $name=>$value) {
		if ($command != "routing_test ")
			$command .= ";";
		$name = str_replace(";","",$name);
		$value = str_replace(";","",$value);
		$command .= "$name=$value";
	}

	$socket = new SocketConn;
	if ($socket->error == "") {
		return array(true,$socket->command($command/*"uptime"*/));
	} else {
		errormess("Can't make test: ".print_r($socket->error,true), "no");
		return array(false);
	}
}

/**
 * Insert at least 1 line break 
 * @param $count Integer the number of the line breaks to be printed. 
 */ 
function br($count = null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$count)
		$count = 1;

	for ($i=0; $i<$count; $i++) 
		print "<br/>"."\n";
}

/**
 * Print or return at least 1 non-breaking space 
 * @param $count Integer how many non-breaking space to be printed or returned
 * @param $print Bool set to true to print the non-breaking space, false to return 
 * the string with the non-breaking spaces. Defaults is set to true. 
 */ 
function nbsp($count = null, $print=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (!$count)
		$count = 1;

	$res = '';
	for ($i=0; $i<$count; $i++) 
		$res .= "&nbsp;";
	
	if ($print)
		print $res;
	else
		return $res;
}

/**
 * Sends an email 
 * @param $emailaddress String the email address where to send tha mail
 * @param $fromaddress String from whom address
 * @param $emailsubject String the subject of the email
 * @param $body String the content of the email
 * @param $attachments Bool 
 *
 */ 
function send_mail($emailaddress, $fromaddress, $emailsubject, $body, $attachments = false, $names = null, $notice = true, $link_with_notice = false, $content_type="text/html", $transfer_encoding="QUOTED-PRINTABLE")
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $path;
	$now = time();
	# Is the OS Windows or Mac or Linux
	if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
	    $eol="\r\n";
	} elseif (strtoupper(substr(PHP_OS,0,3)=='MAC')) {
	    $eol="\r";
	} else {
	    $eol="\n";
	}
	$mime_boundary=md5(time());

	if(!$names)
		$names = '';//$fromaddress;

	# Common Headers
	$headers = 'From: '.$names.'<'.$fromaddress.'>'.$eol;
	$headers .= 'Reply-To: <'.$fromaddress.'>'.$eol;
	$headers .= 'Return-Path: <'.$fromaddress.'>'.$eol;    // these two to set reply address
	$server = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : "localhost.lan";
	$headers .= "Message-ID: <".$now." TheSystem@".$server.">".$eol;
	$headers .= "X-Mailer: PHP v".phpversion().$eol;          // These two to help avoiker spam-filters
	# Boundry for marking the split & Multitype Headers
	$headers .= 'MIME-Version: 1.0'.$eol;
	$headers .= "Content-Type: multipart/mixed; boundary=\"".$mime_boundary."\"".$eol;
	$msg = "";
 
	#this is the body of the message
	$msg .= "--".$mime_boundary.$eol;
	$msg .= "Content-Type: $content_type; charset=ISO-8859-1".$eol;
	$msg .= "Content-Transfer-Encoding: $transfer_encoding".$eol.$eol;
	//$msg .= strip_tags(str_replace("<br>", "\n", $body)).$eol.$eol;
	//$msg .= strip_tags(str_replace("\n", "<br/>", $body)).$eol.$eol;
	$msg .= $body.$eol.$eol;

	if($attachments !== false  && is_array($attachments) && count($attachments) > 0) {
	# Boundry for marking the split & Multitype Headers
		$headers .= 'MIME-Version: 1.0'.$eol;
		$headers .= "Content-Type: multipart/mixed; boundary=\"".$mime_boundary."\"".$eol;

		$msg = "";
	
		#this is the body of the message
		$msg .= "--".$mime_boundary.$eol;
		$msg .= "Content-Type: $content_type; charset=ISO-8859-1".$eol;

		//$msg .= strip_tags(str_replace("<br>", "\n", $body)).$eol.$eol;
		//$msg .= strip_tags(str_replace("\n", "<br/>", $body)).$eol.$eol;
		$msg .= $body.$eol.$eol;
	
		#support for multiple attachments (i won't be using this for now)
		if ($attachments !== false  && count($attachments) > 0)
		{
	
			for($i=0; $i < count($attachments); $i++)
			{
				if (is_file($attachments[$i]["file"]))
				{  
				# File for Attachment
					$file_name = substr($attachments[$i]["file"], (strrpos($attachments[$i]["file"], "/")+1));
		
					$handle=fopen($attachments[$i]["file"], 'rb');
					$f_contents=fread($handle, filesize($attachments[$i]["file"]));
					$f_contents=chunk_split(base64_encode($f_contents));    //Encode The Data For Transition using base64_encode();
					fclose($handle);
		
					# Attachment
					$msg .= "--".$mime_boundary.$eol;
					$msg .= "Content-Type: ".$attachments[$i]["content_type"]."; name=\"".$file_name."\"".$eol;
					$msg .= "Content-Transfer-Encoding: base64".$eol;
					$msg .= "Content-Disposition: attachment; filename=\"".$file_name."\"".$eol.$eol; // !! This line needs TWO end of lines !! IMPORTANT !!
					$msg .= $f_contents.$eol.$eol;
				}
			}
		}
	}
	$msg .= "--".$mime_boundary.'--'.$eol.$eol;
	
	# SEND THE EMAIL
	ini_set('sendmail_from',$fromaddress);  // the INI lines are to force the From Address to be used !
	if (mail($emailaddress, $emailsubject, $msg, $headers)){
		if(php_sapi_name() == 'cli')
			print "E-mail message was sent succesfully to ".$emailaddress;
		elseif($notice && !$link_with_notice)
			message("E-mail message was sent succesfully to ".$emailaddress,'no');
		elseif($notice && $link_with_notice)
			message("E-mail message was sent succesfully to ".$emailaddress);
		return true;
	}else{
		if(php_sapi_name() == 'cli')
			print "Could not send e-mail message to ".$emailaddress;
		elseif($notice && !$link_with_notice)
			errormess("Could not send e-mail message to ".$emailaddress,'no');
		elseif($notice && $link_with_notice)
			errormess("Could not send e-mail message to ".$emailaddress);
		return false;
	}

	ini_restore('sendmail_from');
}

// function taken from user comments from php.net site
function get_mp3_len($file) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$rate1 = array(0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448, "bad");
	$rate2 = array(0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384, "bad");
	$rate3 = array(0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, "bad");
	$rate4 = array(0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256, "bad");
	$rate5 = array(0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, "bad");

	$bitrate = array(
			'1'  => $rate5,
			'2'  => $rate5,
			'3'  => $rate4,
			'9'  => $rate5,
			'10' => $rate5,
			'11' => $rate4,
			'13' => $rate3,
			'14' => $rate2,
			'15' => $rate1
		);

	$sample = array(
			'0'  => 11025,
			'1'  => 12000,
			'2'  => 8000,
			'8'  => 22050,
			'9'  => 24000,
			'10' => 16000,
			'12' => 44100,
			'13' => 48000,
			'14' => 32000
		);

	$fd = fopen($file, 'rb');
	$header = fgets($fd, 5);
	fclose($fd);

	$bits = "";
	while (strlen($header) > 0) {
		//var_dump($header);
		$bits .= str_pad(base_convert(ord($header[0]), 10, 2), 8, '0', STR_PAD_LEFT);
		$header = substr($header, 1);
	}

	$bits = substr($bits, 11); // lets strip the frame sync bits first.

	$version = substr($bits, 0, 2); // this gives us the version
	$layer = base_convert(substr($bits, 2, 2), 2, 10); // this gives us the layer
	$verlay = base_convert(substr($bits, 0, 4), 2, 10); // this gives us both

	$rateidx = base_convert(substr($bits, 5, 4), 2, 10); // this gives us the bitrate index
	$sampidx = base_convert($version.substr($bits, 9, 2), 2, 10); // this gives the sample index
	$padding = substr($bits, 11, 1); // are we padding frames?

	$rate = $bitrate[$verlay][$rateidx];
	$samp = $sample[$sampidx];

	$framelen = 0;
	$framesize = 384; // Size of the frame in samples
	if ($layer == 3) { // layer 1?
		$framelen = (12 * ($rate * 1000) / $samp + $padding) * 4;
	} else { // Layer 2 and 3
		$framelen = 144 * ($rate * 1000) / $samp + $padding;
		$framesize = 1152;
	}

	$headerlen = 4 + ($bits[4] == 0 ? '2' : '0');

	return (filesize($file) - $headerlen) / $framelen / ($samp / $framesize);
}

function arr_to_csv($file_name, $arr, $formats=null, $func="write_in_file", $title_text=null, $display_as_notice_mess = false)
{
	global $download_option;

	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $upload_path;

	$format = ".csv";

	$date = date("YMd_H_i_s");
	$file = $upload_path."/$file_name$format";
	if (is_file($file)) {
		unlink($file);
	}
	$fh = fopen($file, "w") or die("Can't open file for writting: ".$file);

	if ($title_text)
		fwrite($fh,$title_text."\r\n");
	if (!$func)
		$func = "write_in_file";

	$sep = ",";
	$func($fh, $formats, $arr, $sep);
	fclose($fh);
	chmod($file, 0777);

	if (isset($download_option) && $download_option=="direct_file")
		$message = "Content was exported. <a href=\"$upload_path/$file_name$format\">Download</a>";
	else
		$message = "Content was exported. <a href=\"download.php?file=$file_name$format\">Download</a>";
	
	$message.= "<br/><br/> ** When opening this file, double quote should be selected as 'text delimiter' so text having line breaks will not be displayed on separate lines.";
	if ($display_as_notice_mess)
		notice($message, "no", true, false);
	else 		
		print $message;
}

/**
 * Add the specific $col_names to the given $arr and write the results into
 * the given $file_name.
 * Display 'Download' notice or just print it depending on the settings.
 * @param $file_name String. The file name.
 * @param $arr Array. The array containing the data to be written in file.
 * @param $col_names Array. The names of the columns from the $arr data.
 * @param $display_as_notice_mess Bool. Default true so that the message
 * is displayed using notice() function. 
 */ 
function arr_to_json($file_name, $arr, $col_names, $display_as_notice_mess = true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $upload_path, $download_option;
	
	$fields = array();
	foreach ($arr as $k => $columns) {
		foreach ($columns as $name => $value) {
			if (!in_array($name,$col_names))
				continue;
			$fields[$k][$name] = $value;
		}
	}

	$file = $upload_path . "/" . $file_name;
	$fh = fopen($file, "w") or die("Can't open file for writting: ".$file);
	if (phpversion() && phpversion() >= 5.4)
		fwrite($fh, json_encode($fields,JSON_PRETTY_PRINT));
	else
		fwrite($fh, json_encode($fields));

	fclose($fh);

	if (isset($download_option) && $download_option=="direct_file")
		$message = "Content was exported. <a href=\"$file\">Download</a>";
	else
		$message = "Content was exported. <a href=\"download.php?file=$file_name\">Download</a>";
	
	if ($display_as_notice_mess)
		notice($message, "no", true, false);
	else 		
		print $message;
}

function mysql_in_file($fh, $formats, $array, $sep)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$col_nr = 0;
	
	if (!$formats && mysql_num_rows($array))
		for($i=0; $i<mysql_num_fields($array); $i++)
			$formats[mysql_field_name($array,$i)] = mysql_field_name($array,$i);
	if ($formats!="no") {
		foreach($formats as $column_name => $var_name)
		{
			$exploded = explode(":",$column_name);
			if(count($exploded)>1)
				$name = $exploded[1];
			else{
				$name = $column_name;
				if(substr($column_name, 0, 9) == "function_")
					$name = substr($column_name,9);
				if(is_numeric($column_name))
					$name = $var_name;
			}
			$val = str_replace("_"," ",ucfirst($name));
			$val = str_replace("&nbsp;"," ",$val);
			$val = ($col_nr) ? $sep."\"$val\"" : "\"$val\"";
			fwrite($fh, $val);
			$col_nr++;
		} 
		fwrite($fh,"\n");
	}
	//for($i=0; $i<mysql_num_rows($array); $i++) 
	while($row = mysql_fetch_assoc($array))
	{
		$col_nr = 0;

		foreach($formats as $column_name=>$names_in_array)
		{
			$use_vars = explode(",", $names_in_array);
			$exploded_col = explode(":", $column_name);
			$column_value = '';

			if(substr($exploded_col[0],0,9) == "function_") 
			{
				$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
				if(count($use_vars)) 
				{
					$params = array();
					for($var_nr=0; $var_nr<count($use_vars); $var_nr++)
						array_push($params, $row[$use_vars[$var_nr]]);
					$column_value = call_user_func_array($function_name,$params);
				}
			}elseif(isset($row[$names_in_array])){
				$column_value = $row[$names_in_array];
			}
			if(!strlen($column_value))
				$column_value = "";
			$column_value = "\"=\"\"".$column_value."\"\"\"";
			if ($col_nr)
				$column_value =  $sep.$column_value;
			fwrite($fh,$column_value);
			$col_nr++;
		}

		fwrite($fh,"\n");
	}
}
/**
 * Writes the data given in $array in a specific format given in $formats into a given file resource $fh
 * @param $fh Resource. The file handle opened in write mode
 * @param $formats Array or String. The format of the columns that will be written in file
 * Ex: array(":name_column"=>"name_key_from_array"); or "no". If set to "no" $formats will be
 * build from $array, the name of the columns are the same as the ones that will be written in file
 * @param $array Array. The data values specific for each column.
 * @param $sep String. The separator used between the columns in file.
 * @param $key_val_arr Bool. True if the columns are set directly in $array. 
 * False to get directly the values from arrays of arrays. 
 * @param $col_header Bool. True to set header parameters from $formats 
 * @param $keep_bools Bool. False by default to not keep boolean values.
 * @param $escape Bool. True by default to escape the values of the columns set in file.
 */ 
function write_in_file($fh, $formats, $array, $sep, $key_val_arr=true, $col_header=true, $keep_bools=false, $escape=true)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$col_nr = 0;

	if (!is_array($array))
		$array = array();
	
	if (!$formats && count($array))
		foreach ($array[0] as $name=>$val)
			$formats[$name] = $name;
	if ($col_header && $formats!="no") {
		foreach ($formats as $column_name => $var_name)
		{
			$exploded = explode(":",$column_name);
			if (count($exploded)>1)
				$name = $exploded[1];
			else {
				$name = $column_name;
				if (substr($column_name, 0, 9) == "function_")
					$name = substr($column_name,9);
				if (is_numeric($column_name))
					$name = $var_name;
			}
			$val = str_replace("_"," ",ucfirst($name));
			$val = str_replace("&nbsp;"," ",$val);
			if ($escape)
				$val = ($col_nr) ? $sep."\"$val\"" : "\"$val\"";
			else
				$val = ($col_nr) ? $sep.$val : $val;
			fwrite($fh, $val);
			$col_nr++;
		} 
		fwrite($fh,"\n");
	}

	for ($i=0; $i<count($array); $i++) 
	{
		$col_nr = 0;
		if ($key_val_arr) {
			foreach ($formats as $column_name=>$names_in_array)
			{
				$use_vars = explode(",", $names_in_array);
				$exploded_col = explode(":", $column_name);
				$column_value = '';

				if (substr($exploded_col[0],0,9) == "function_") 
				{
					$function_name = substr($exploded_col[0],9,strlen($exploded_col[0]));
					if (count($use_vars)) 
					{
						$params = array();
						for ($var_nr=0; $var_nr<count($use_vars); $var_nr++)
							array_push($params, $array[$i][$use_vars[$var_nr]]);
						$column_value = call_user_func_array($function_name,$params);
					}
				} elseif (isset($array[$i][$names_in_array])) {
					$column_value = $array[$i][$names_in_array];
				} elseif (substr($names_in_array, 0, 15) == "retrieve_column") {
					$col = explode(":", $names_in_array);
					$function_name = $col[0];
					$params = array(array($col[1] => array()));
					if (isset($array[$i][$col[1]]))
						$params = array($array[$i][$col[1]]);
					for ($no=2; $no < count($col); $no++) 
						array_push($params,$col[$no]);
					$column_value = call_user_func_array($function_name,$params);
				}

				if (!$keep_bools) {
					if (!strlen($column_value))
						$column_value = "";
					
					$column_value = ($escape) ? "\"=\"\"".$column_value."\"\"\"" : $column_value;
				} else {
					if (!is_bool($column_value) && $column_value!==null)
						$column_value = ($escape) ? "\"=\"\"".$column_value."\"\"\"" : $column_value;
					
				}
				if ($col_nr)
					$column_value =  $sep.$column_value;
				fwrite($fh,$column_value);
				$col_nr++;
			}
		} else {
			for ($j=0; $j<count($array[$i]); $j++) {
				$column_value = ($escape) ? "\"".$array[$i][$j]."\"" : $array[$i][$j];
				if ($col_nr)
					$column_value =  $sep.$column_value;
				fwrite($fh, $column_value);
				$col_nr++;
			}
		}
		fwrite($fh,"\n");
	}
}

/**
 * function used to check on_page authentication
 * used in debug_all.php
 */ 
function is_auth($identifier)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global ${"pass_".$identifier."_page"};

	if (isset($_SESSION["pass_$identifier"]) || !isset(${"pass_".$identifier."_page"}) || !strlen(${"pass_".$identifier."_page"}))
		return true;
	return false;
}

/**
 *  function used to check on_page authentication/authenticate
 *  used in debug_all.php
 */ 
function check_auth($identifier)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global ${"pass_".$identifier."_page"};

	if (strlen(${"pass_".$identifier."_page"}) && !isset($_SESSION["pass_$identifier"])) {
		$pass = (isset($_REQUEST["pass_$identifier"])) ? $_REQUEST["pass_$identifier"] : '';
		if ($pass != ${"pass_".$identifier."_page"}) 
			return false;
		$_SESSION["pass_$identifier"] = true;
	}
	return true;
}

/**
 * Generates a numeric token with a specified length
 *
 * @param $length the length of the token
 * @return String containing the random token
 */ 
function generateNumericToken($length)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$str = "";
	for ($i=0; $i<$length; $i++)
	{
		$c = mt_rand(0,9);
		$str .= $c;
	}
	return $str;
}

function display_bit_field($val)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	$bits = array("on","true","enabled","enable","yes","1","t");
	if (in_array($val,$bits)) {
		return "yes";
	}

	return "no";
}

function display_field($field_name,$field_format,$form_identifier='',$css=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	
	global $htmlentities_onall;
	
	// if htmlentities wasn't run on all fields retrieved from database
	// by default we should run it here before printing value
	$htmlentities_onvalue = (!$htmlentities_onall) ? true : false;
	
	$q_mark = false;

	$value = NULL;
	if(isset($field_format["value"]))
		$value = $field_format["value"];

	if (!strlen($value) && isset($field_format["cb_for_value"]) && is_callable($field_format["cb_for_value"]["name"])) {
		if (count($field_format["cb_for_value"])==2)
			$value = call_user_func_array($field_format["cb_for_value"]["name"],$field_format["cb_for_value"]["params"]);
		else
			$value = call_user_func($field_format["cb_for_value"]["name"]);
		// if $value is resulted by callign a function then that function is responsible for running htmlentities 
		$htmlentities_onvalue = false;
	}

	$display = (isset($field_format["display"])) ? $field_format["display"] : "text";
	$var_name = (isset($field_format[0])) ? $field_format[0] : $field_name;

	if ($htmlentities_onvalue && !isset($field_format["no_escape"]))
		$value = htmlentities($value);
	
	$res = "";
	switch($display)
	{
		case "select":
		case "mul_select":
		case "select_without_non_selected":
			$res .= '<select class="'.$css.'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'" ';
			if(isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if($display == "mul_select")
				$res .= ' multiple="multiple" size="5"';
			$res .=  '>';
			if($display != "mul_select" && $display != "select_without_non_selected")
				$res .=  '<option value="">Not selected</option>';

			// PREVIOUS implementation when only 0 key could be used for dropdown options
			// $options = (is_array($var_name)) ? $var_name : array();

			if ($display != "mul_select") {
				// try gettting it from value
				if ($value && is_array($value))
					$options = $value;
				elseif (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			} else {
				if (is_array($var_name))
					$options = $var_name;
				else
					$options = array();
			}

			if (isset($field_format["selected"]))
				$selected = $field_format["selected"];
			elseif(isset($options["selected"]))
				$selected = $options["selected"];
			elseif(isset($options["SELECTED"]))
				$selected = $options["SELECTED"];
			else
				$selected = '';
			foreach ($options as $var=>$opt) {
				if ($var === "selected" || $var === "SELECTED")
					continue;
				$css = (is_array($opt) && isset($opt["css"])) ? 'class="'.$opt["css"].'"' : "";
				if(is_array($opt) && isset($opt[$field_name.'_id'])) {
					$optval = $field_name.'_id';
					$name = $field_name;

					$printed = trim($opt[$name]);
					if (substr($printed,0,4) == "<img") {
						$expl = explode(">",$printed);
						$printed = $expl[1];
						$jquery_title = " title=\"".str_replace("<img","",$expl[0])."\"";
					} else
						$jquery_title = '';

					if ( (string)$opt[$optval] === "$selected" || (is_array($selected) && in_array($opt[$optval],$selected))) {
						$res .= '<option value=\''.$opt[$optval].'\' '.$css.' SELECTED ';
						if($opt[$optval] == "__disabled")
							print ' disabled="disabled"';
						$res .= $jquery_title;
						$res .= '>' . $printed . '</option>';
					} else {
						$res .= '<option value=\''.$opt[$optval].'\' '.$css;
						if($opt[$optval] == "__disabled")
							print ' disabled="disabled"';
						$res .= $jquery_title;
						$res .= '>' . $printed . '</option>';
					}
				}else{
					if ($opt == $selected ||  (is_array($selected) && in_array($opt[$optval],$selected)))
						$res .= '<option '.$css.' SELECTED >' . $opt . '</option>';
					else
						$res .= '<option '.$css.'>' . $opt . '</option>';
				}
			}
			$res .= '</select>';
			break;
		case "checkbox":
		case "checkbox-readonly":
			$res .= '<input class="'.$css.'" type="checkbox" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if (bool_value($value) || $value=="on")
				$res .= " CHECKED ";
			if (isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if ($display=="checkbox-readonly")
				$res .= " readonly=''";
			$res .= '/>';
			break;
		case "text":
		case "password":
		case "file":
		case "hidden":
		case "text-nonedit":
			$res .= '<input class="'.$css.'" type="'.$display.'" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if ($display != "file" && $display != "password") {
				if (!is_array($value))
					$res .= ' value='.html_quotes_escape($value);
				
				else {
					
					if (isset($field_format["selected"]))
						$selected = $field_format["selected"];
					elseif (isset($value["selected"]))
						$selected = $value["selected"];
					elseif (isset($value["SELECTED"]))
						$selected = $value["SELECTED"];
					else
						$selected = '';
					$res .= ' value='.html_quotes_escape($selected);
				}
			}
			if(isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if($display == "text-nonedit")
				$res .= " readonly=''";
			if(isset($field_format["autocomplete"]))
				$res .= " autocomplete=\"".$field_format["autocomplete"]."\"";
			if($display != "hidden" && isset($field_format["comment"])) {
				$q_mark = true;
				if (is_addon("font-awesome"))
					$res .= '>&nbsp;&nbsp;<i class="fa fa-question pointer help_icon" aria-hidden="true" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"></i>';
				else if (is_file("images/question.jpg"))
					$res .= '>&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"/>';
				else
					$res .= '>&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"> ? </font>';
			} else
				$res .= '>';
			break;
		case "password-toggle":
		case "password-toggle-fa":
			$res .= '<input  class="'.$css.'" type="password" style="padding-right:20px;" name="'.$form_identifier.$field_name.'" id="'.$form_identifier.$field_name.'"';
			if (is_string($value))
				$res .= " value=".html_quotes_escape($value);
			if (isset($field_format["javascript"]))
				$res .= $field_format["javascript"];
			if (isset($field_format["maxlength"]))
				$res .= " maxlength='".$field_format["maxlength"]."'";
			if (isset($field_format["autocomplete"]))
				$res .= " autocomplete=\"".$field_format["autocomplete"]."\"";
			$res .= '>';
			
			//To use toggle-password-fa make sure that your project has font-awsome
			if ($display==="password-toggle-fa" && is_addon("font-awesome")) {
				$toggle_style = "cursor:pointer; position:relative; z-index:2; margin-left:-15px; color:blue; font-size:15px;";
				$res .= '<span id="'.$form_identifier.$field_name.'_toggle" style="'.$toggle_style.'" class="fa fa-eye-slash password-toggle-slash" onClick="display_password(this);"></span>';
			} else {
				$res .= '<br><input id="'.$form_identifier.$field_name.'_toggle" class="password-toggle" type="checkbox" onclick="display_password(this);">';
				$res .= '<label for="'.$form_identifier.$field_name.'_toggle">Show Password</label>';
			}
			break;
		case "date_time":
			
//			value format:
//			$field_format["value"] = array(
//				"date"=>1979-12-31,
//				"time"=>23:59:59.999,
//				"timezone"=>Europe/Bucharest
//			);
			
			//~~~DATE INPUT~~~
			// date value ex: 1979-12-31
			
			$res .=  "<input type=\"date\" id=\"{$field_name}_date\" name=\"{$field_name}_date\"";
			
			if (isset($value["date"]))
				$res .=  " value=\"{$value["date"]}\"";
			
			if (isset($field_format["date_min"]))
				$res .=  " min=\"".$field_format["date_min"]."\"";
			
			if (isset($field_format["date_max"]))
				$res .=  " max=\"".$field_format["date_max"]."\"";
			
			if (isset($field_format["javascript"]["date"]))
				$res .=  " ".$field_format["javascript"]["date"];
			
			if (isset($field_format["javascript"]) && is_string($field_format["javascript"]))
				$res .=  " ".$field_format["javascript"];
			elseif (isset($field_format["javascript"]["all"]))
				$res .=  " ".$field_format["javascript"]["all"];
			
			
			$res .=  " >&nbsp;";
			
			//stop here display just date field if $field_format["no_time"]=true
			if (isset($field_format["no_time"]) && $field_format["no_time"]===true) 
				break;
			
			//~~~TIME INPUT~~~
			// time value format ex: 23:59:59.999
			
			$res .=  "<input type=\"time\" id=\"{$field_name}_time\" name=\"{$field_name}_time\" ";
			
			if (isset($value["date"]))
				$res .=  " value=\"{$value["time"]}\"";
				
			if (isset($field_format["time_format"])) {
				if ($field_format["time_format"]=="H:i:s")
					$res .=  " step=\"1\"";
				elseif ($field_format["time_format"]=="H:i:s.v")
					$res .=  " step=\"0.1\"";
			} elseif (isset($field_format["time_step"]))
					$res .=  " step=\"{$field_format["time_step"]}\"";
			
			if (isset($field_format["time_min"]))
				$res .=  " min=\"".$field_format["time_min"]."\"";
			
			if (isset($field_format["time_max"]))
				$res .=  " max=\"".$field_format["time_max"]."\"";
			
			if (isset($field_format["javascript"]["time"]))
				$res .=  " ".$field_format["javascript"]["time"];
			
			if (isset($field_format["javascript"]) && is_string($field_format["javascript"]))
				$res .=  " ".$field_format["javascript"];
			elseif (isset($field_format["javascript"]["all"]))
				$res .=  " ".$field_format["javascript"]["all"];
			
			$res .=  " >&nbsp;";
			
			//stop here display just date and time fields if $field_format["no_date"]=true
			if (isset($field_format["no_timezone"]) && $field_format["no_timezone"]===true) 
				break;
			
			//~~~TIMEZONE INPUT~~~

			if (isset($field_format["timezones_cb"]))
				$timezones = call_user_func ($field_format["timezones_cb"]);
			else
				$timezones = pretty_timezone_list();

			$timezones = format_for_dropdown_assoc_opt($timezones,"{$field_name}_timezone");

			$js = "";
			if (isset($field_format["javascript"]["timezone"]))
				$js = " ".$field_format["javascript"]["timezone"];
			if (isset($field_format["javascript"]) && is_string($field_format["javascript"]))
				$js = " ".$field_format["javascript"];
			elseif (isset($field_format["javascript"]["all"]))
				$js = $field_format["javascript"]["all"];

			if (isset($value["timezone"]))
				$timezones["selected"]=$value["timezone"];

			$res .= build_dropdown($timezones, "{$field_name}_timezone", false, false, $css, $js, false, false, null, false);

			break;
		case "fixed":
			if(strlen($value))
				$res .= $value;
			else
				$res .= "&nbsp;";
			break;
	}
	if(isset($field_format["comment"]))
	{
		$comment = $field_format["comment"];
		if(!$q_mark) {
			if (is_addon("font-awesome"))
					$res .= '&nbsp;&nbsp;<i class="fa fa-question pointer help_icon" aria-hidden="true" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"></i>';
			else if (is_file("images/question.jpg"))
				$res .= '&nbsp;&nbsp;<img class="pointer" src="images/question.jpg" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"/>';
			else
				$res .= '&nbsp;&nbsp;<font style="cursor:pointer;" onClick="show_hide_comment(\''.$form_identifier.$field_name.'\');"> ? </font>';
		}
		$res .= '<font class="comment" style="display:none;" id="comment_'.$form_identifier.$field_name.'">'.$comment.'</font>';
	}
	return $res;
}

function stdClassToArray($d) 
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (is_object($d)) {
		// Gets the properties of the given object
		// with get_object_vars function
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		/*
		* Return array converted to object
		* Using __FUNCTION__ (Magic constant)
		* for recursive call
		*/
		return array_map(__FUNCTION__, $d);
	}
	else {
		// Return array
		return $d;
	}
}

function get_param($array,$name)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return isset($array[$name]) ? $array[$name] : null;
}

function missing_param($param)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	return ($param === null) || ($param == "");
}

function generic_tabbed_settings($options,$config,$section_to_open=array(),$show_advanced=false, $form_name=null,$form_submit=true,$specif_ind="",$custom_css=null)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $module, $tabs_cb;

	if (!$form_name)
		$form_name = $module;
	if (!is_array($options))
		$options = array();

	print '<table class="sections '.$custom_css.'" cellspacing="0" cellpadding="0">'."\n";
	print '<tr>'."\n";
	$total_options = count($options);
	$disp_show = "";
	$disp_hide = ' style="display:none;"';

	$initial_show_advanced = $show_advanced;
	if (!isset($section_to_open["-"]))
		$show_advanced = true;

	$first_open = NULL;
	foreach($section_to_open as $sect_name=>$def) {
		$first_open = $sect_name;
		$first_subsec = (!strlen($def)) ? "-" : $def;
		break;
	}

	for ($i=0; $i<$total_options; $i++) {
		$name = $options[$i];
		$js_name = $specif_ind.$i;

		$css = ($first_open!=$name && !(!$i && $first_open=="-")) ? "section" : "section_selected";
		if (!$i)
			$css .= " basic";
		$css .= " $custom_css";
		if ($first_open==$name || ($first_open=="-" && !$i))
			$css .= "_selected";
		$td_css = (!$i) ? "section basic $custom_css" : "$custom_css";

		print '<td class="'.$td_css.'" id="td_'.$js_name.'">';
		if ($i) {
			print '<div class="fill_section" id="fill_'.$js_name.'" ';
			if ($show_advanced)
				print $disp_hide;
			print '>&nbsp;</div>';
		}

		print '<div class="'.$css.'" onClick="show_section(\''.$i.'\','.$total_options.',\''.$specif_ind.'\',\''.$custom_css.'\');" id="tab_'.$js_name.'" ';
		if ($i && !$show_advanced)
			print $disp_hide;
		print '>'.ucwords($name);

		if (!$i && $initial_show_advanced!==NULL) {
			$img = ($show_advanced) ? "sm_hide.png" : "sm_show.png";
			print "&nbsp;&nbsp;<img src=\"images/$img\" class=\"pointer\" id=\"img_show_tabs$specif_ind\" onclick=\"show_all_tabs($total_options,'$specif_ind');\" width=\"11px\" height=\"11px\"/>";
		}

		print '</div></td>'."\n";
		print '<td class="space_section">&nbsp;</td>'."\n";
	}
	print '<td class="fillspace_section">&nbsp;</td>'."\n";
	print '</tr>'."\n";
	print '<tr>'."\n";
	$colspan = $total_options*2+1;
	print '<td class="section_content" colspan="'.$colspan.'">'."\n";

	for ($i=0; $i<$total_options; $i++) {
		$name = $options[$i];
		$js_name = $specif_ind.$i;
		$current_fields = $config[$options[$i]];
		$disp = ($first_open!=$options[$i] && !(!$i && $first_open=="-")) ? " style='display:none;'" : '';

		print "<div class='section_fields' id='sect_$js_name'$disp>";
		if (isset($current_fields["subsections"])) {
			$sub_sections = $current_fields["subsections"];
			$names = array();
			foreach ($sub_sections as $name=>$fields)
				$names[] = $name;
			generic_tabbed_settings($names, $sub_sections, array("$first_subsec"=>""), false, "", false, $js_name."_", "subsec");
		} else {
			if (isset($tabs_cb) && is_callable($tabs_cb))
				$tabs_cb($name, $current_fields);
			editObject(NULL,$current_fields,NULL,"no");
		}
		print "</div>";
	}

	if ($form_submit===true) {
		print '<div class="section_submit">';
		//print '<input type="button" value="Save" onClick="submit_form(\''.$form_name.'\')" />&nbsp;';
		//print '<input type="reset" value="Reset" onClick="reset_form(\''.$form_name.'\')"/>&nbsp;';
		print '<input type="submit" value="Save" />&nbsp;';
		print '<input type="reset" value="Reset" />&nbsp;';
		print cancel_button('','Cancel');
		print '&nbsp;</div>';
	} elseif (is_array($form_submit)) {
		print '<div class="section_submit">';
		foreach($form_submit as $name=>$func)
			if ($func!="no_cancel")
				print '<input type="button" value="'.$name.'" onClick="'.$func.'(\''.$form_name.'\')" />&nbsp;';
		if (!in_array("no_cancel",$form_submit))
			print cancel_button('','Cancel');
		print '&nbsp;</div>';
	}
	print '</td>';
	print '</tr>';
	print '</table>';
}

function load_page($page, $timeout=0)
{
	$page = build_link($page);

	if (!is_numeric($timeout))
		$timeout = 0;
	
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	if (headers_sent() || $timeout)
		print "<meta http-equiv=\"REFRESH\" content=\"$timeout;url=$page\">";
	else
		header("Location: $page");
}

/**
 * Builds link substituting last current link position (after "/") with given page path; 
 * example: build_link('main.php?module=home&method=home')  =>  'http(s)://ip:port/{$_SERVER["PHP_SELF"] without last elem}/main.php?module=home&method=home'
 * @param string $page The page path for which the link is built.
 * @param bool $loopback_ip Default is false. If true link address will be built with 172.0.0.1 as server name.
 * @return string The builded link.
 */
function build_link($page, $loopback_ip = false)
{
	if (substr($page,0,7)=="http://" || substr($page,0,8)=="https://")
		return $page;

	$current = $_SERVER["PHP_SELF"];
	$current = explode("/",$current);
	$current[count($current)-1] = $page;
	$current = implode("/",$current);
	
	if ($loopback_ip)
		$page = "127.0.0.1".$current;
	elseif (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"]!=80)
		$page = $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$current;
	else
		$page = $_SERVER["SERVER_NAME"].$current;
	$page = get_request_protocol()."://".$page;

	return $page;
}

function include_javascript($module=null, $set_version=false)
{
	if (is_file("ansql/javascript.js")) {
		// include the ansql javascript functions
		if (!$set_version) {
			print '<script language="javascript" type="text/javascript" src="ansql/javascript.js"></script>';
		} else { 
			$src = set_file_version("ansql/javascript.js");
			print '<script language="javascript" type="text/javascript" src="'.$src.'"></script>';
		}
	}

	if (is_file("javascript/generic_js.php"))
		include("javascript/generic_js.php");

	if ($module && is_file("javascript/$module.php"))
		include("javascript/$module.php");
}

/**
 * Builds global javascript array map variable: required_fields 
 * Ex on generated object: required_fields={"username":"username", "contact_info":"Contact information"}
 * @param $form_fields Array. Fields
 * @param $exclude_fields Array. Array with key step names that should be excluded
 * Ex: array("step_name", "step_image", "step_description") -- usage when used from Wizard class
 */
function requiredFieldsJs($form_fields, $excluded_fields)
{
	?><script>
	// save the name of the required fields: field_name:column_name
	// column_name is the field the user sees, field_name is the key in the current step array
	required_fields = {};
	<?php

	foreach ($form_fields as $field_name=>$field_def) {
		if (isset($excluded_fields[$field_name]))
			continue;
		if (isset($field_def["display"])) {
			if ($field_def["display"] == "message" || $field_def["display"] == "fixed")
				continue;
		}

		if ( (isset($field_def["required"]) && ($field_def["required"]===true || $field_def["required"]=="true")) ||
		    (isset($field_def["compulsory"]) && ($field_def["compulsory"]===true || $field_def["compulsory"]=="true"))) {
				$real_name = isset($field_def["column_name"]) ? $field_def["column_name"] : str_replace("_", " ", ucfirst($field_name));
				echo "required_fields[\"".$field_name."\"]=\"".$real_name."\";";
		}
	}
	?>
	</script><?php
}

/**
 * Build generic search box: Dropdown to select option to search by and input for searched value
 * @param $search_options Array. Options to search by
 * @param $custom_fields Array. Custom search fields that will be added before the $search_options.
 * @param $value_readonly Bool. If true set 'Value' field as readonly, when 'Search by' is not selected.
 * Ex: array("title"=>array("Search1", "Search2"),array("<input type.../>", "<select>..</select>"))
 * @param $custom_order Bool. Default false. If true custom search fields will be added after the $search_options.
 * @param $default_selected String. The default dropdown option to be displayed as selected.
 * @param $not_selected_option Bool. Default true. If false, option '-' will not be added to the dropdown.
 */
function generic_search($search_options, $custom_fields=array(), $value_readonly=false, $custom_order=false, $default_selected=NULL, $not_selected_option=true)
{
	$search_options = format_for_dropdown($search_options);
	
	if (getparam("col"))
		$search_options["selected"] = getparam("col");
	else if ($default_selected)
		$search_options["selected"] = $default_selected;
	
	$title = array("Search by", "Value", "&nbsp;");
	$ro = (!$value_readonly) ? "undefined" : "true";
	$fields = array(array(
			build_dropdown($search_options, "col", $not_selected_option, "", "", 'onchange="clean_cols_search('.$ro.')"'),
			"<input type=\"text\" value=". html_quotes_escape(getparam("col_value"))." name=\"col_value\" id=\"col_value\" size=\"10\"/>",
			"<input type=\"submit\" value=\"Search\" />"
		));

	if ($value_readonly && !getparam("col_value"))
		$fields[0][1] = "<input type=\"text\" value=". html_quotes_escape(getparam("col_value"))." name=\"col_value\" id=\"col_value\" size=\"10\" readonly/>";

	if (isset($custom_fields["title"]) && isset($custom_fields["fields"])) {
		if (!$custom_order) {
			$title = array_merge($custom_fields["title"],$title);
			$fields = array(array_merge($custom_fields["fields"],$fields[0]));
		} else {
			$title = array_merge($title, $custom_fields["title"]);
			$fields = array(array_merge($fields[0], $custom_fields["fields"]));
		}
	}

	start_form();
	if ($value_readonly)
		addHidden(null,array(),false,array("col_value", "col"));
	else
		addHidden(null,array(),false,array("col_value"));
	formTable($fields,$title);
	end_form();
}

/**
 * Get conditions from search box built with \ref generic_search
 * @param $custom_cols Array (key=>value). If column is key in this array it will be renamed to $value in returned arr
 * @return Array
 * Ex: array("username"=>"LIKE%mary%") / array("__sql_customer"=>"LIKE%entils%")
 */
function get_search_conditions($custom_cols=array())
{
	$col = getparam("col");
	$col_value = getparam("col_value");

	if (!strlen($col) || !strlen($col_value))
		return array();

	if (isset($custom_cols[$col]))
		return array($custom_cols[$col]=>"__LIKE%$col_value%");

	return array($col=>"__LIKE%$col_value%");
}

/**
 * Reads a file in reverse order and returns the last N lines 
 * as an array (N given as parameter).
 * @param $path String the path with the filename.
 * @param $line_count Integer the lines number that will be read. 
 * @param $block_size Integer the bytes that will be read.
 * @param $offset Integer the offset in bytes
 * @param $leftover String the remaining data read.
 * @param $lines Array the remaining lines from previous data read.
 * @returns Array with:
 *      - the lines readed from the end of the file, 
 *      - the value of the offset
 *      - the leftover string remained from previous reading data
 *      - the remaining lines already read
 */ 
function get_file_last_lines($path, $line_count, $block_size = 512, $offset = null, $leftover = '', $lines = array())
{
	if (!is_array($lines))
		$lines = array();
	if (count($lines) >= $line_count)
		return array(array_slice($lines, 0, $line_count), $offset, $leftover, array_slice($lines, $line_count));

	$fh = fopen($path, 'r');
	
	if ($offset === null)
		// go to the end of the file
		fseek($fh, 0, SEEK_END);
	else
		// go to the last offset in the file
		fseek($fh, $offset, SEEK_SET);


	do {
		// need to know whether we can actually go back
		// $block_size bytes
		$can_read = $block_size;
		if (ftell($fh) < $block_size)
			$can_read = ftell($fh);

		// go back as many bytes as we can
		// read them to $data and then move the file pointer
		// back to where we were.
		fseek($fh, -$can_read, SEEK_CUR);
		if ($can_read == 0)
			$data = '';
		else
			$data = fread($fh, $can_read);
		$data .= $leftover;
		fseek($fh, -$can_read, SEEK_CUR);
		
		// remove the last element from data
		if (substr($data, strlen($data)-1, strlen($data)) == "\n")
			$data = substr($data, 0, strlen($data)-1);

		// split lines by \n. Then reverse them,
		// now the last line is most likely not a complete
		// line which is why we do not directly add it, but
		// append it to the data read the next time.
		$split_data = array_reverse(explode("\n", $data));
		$new_lines = array_slice($split_data, 0, -1);
		$lines = array_merge($lines, $new_lines);
		$leftover = $split_data[count($split_data) - 1];
	} while(count($lines) < $line_count && ftell($fh) != 0);


	if (ftell($fh) == 0) {
		$lines[] = $leftover;
		$leftover = "";
	}
	
	$offset = ftell($fh);
	fclose($fh);
	
	// Usually, we will read too many lines, correct that here.
	return array(array_slice($lines, 0, $line_count), $offset, $leftover, array_slice($lines, $line_count));
}

// Replace \n with <br/> and " " with &nbsp;
function format_comment($text)
{
	$text = str_replace("\n","<br/>",$text);
	$text = str_replace(" ","&nbsp;",$text);
	return $text;
}

/**
 * Build net addresses dropdown from received data
 * The types of dropdown are 'select_without_non_selected' || 'select'
 * @param $net_interfaces Array The interfaces with their associated IPs
 * @param $select Bool The type of dropdown, if true 'select' otherwize 'select_without_non_selected'
 * @param $type_ip String The type of IP: 'ipv4', 'ipv6', 'both' to be displayed in dropdown
 * @param $dropdown_key String The name of the dropdown from FORM
 * @return $interfaces_ips Array The format used to display the dropdown
 */
function build_net_addresses_dropdown($net_interfaces, $select=false, $type_ip = "both", $dropdown_key = "local")
{
	$interfaces_ips = array();

	foreach ($net_interfaces["net_address"] as $key=>$interface) {
		if (!$select)
			$interfaces_ips[] = array($dropdown_key."_id"=>"__disabled", $dropdown_key=>"------".$interface["interface"]."------");

		if ($type_ip == "both" || $type_ip == "ipv4") {
			if (!isset($interface["ipv4"]))
				continue;
			foreach ($interface["ipv4"] as $k=>$ip) {
				if (!$select)
					$interfaces_ips[] = array($dropdown_key."_id"=>$ip["address"], $dropdown_key=>$ip["address"]);
				else
					$interfaces_ips[] = $ip["address"];
			}
		}
		if ($type_ip == "both" || $type_ip == "ipv6") {
			if (!isset($interface["ipv6"]))
				continue;
			foreach ($interface["ipv6"] as $k=>$ip) {
				if (!$select)
					$interfaces_ips[] = array($dropdown_key."_id"=>$ip["address"], $dropdown_key=>$ip["address"]);
				else
					$interfaces_ips[] = $ip["address"];
			}
		}
	}
	return $interfaces_ips;
}

function build_net_addr_labels($net_interfaces, $dropdown_key = "sctp_ip")
{
	$interfaces_labels = array();
	foreach ($net_interfaces["net_address"] as $interface) 
		$interfaces_labels[] = array($dropdown_key."_id"=>$interface["interface"], $dropdown_key=>$interface["interface"]);
	return $interfaces_labels;
}

/**
 * Change the old key with a new key in an specific array used to build 
 * a dropdown that is used to display:'select_without_non_selected'
 */   
function change_keys_dropdown($array, $old_key, $new_key)
{
	$old_keys = array($old_key."_id", $old_key);
	$new_keys = array($new_key."_id", $new_key);


	if (!is_array($array))
		return array();

	for ($i=0; $i<count($array); $i++) {
		for ($j=0; $j<2; $j++) {
			if (isset($array[$i][$old_keys[$j]])) {
				$array[$i][$new_keys[$j]] = $array[$i][$old_keys[$j]];
				if (is_array($array[$i]) && isset($array[$i][$old_keys[$j]]))
					unset($array[$i][$old_keys[$j]]);
			}
		}
	}

	return $array;
}

/**
 * Allow flushing buffers on request
 */
function flush_buffers()
{
    @ob_end_flush();
    @ob_flush();
    
    // after testing this seemed enough
    flush();

    ob_start(); 
}

/**
 * Displays node statistics. 
 * @param $query_stats Array The statistics of a product(HSS, UCN, SDR, etc)
 * @param $product_name Text The name of the product
 */ 
function display_query_stats($query_stats, $product_name)
{
/*
 * {"code":0,
 *  "stats":{
 *          "engine":{"version":"5.5.1","revision":6202,"nodename":"ybtsUNCONFIG","runid":1491988359,"plugins":23,"inuse":1,"handlers":222,"hooks":4,"messages":0,
 *                     "maxqueue":2,"messagerate":2,"maxmsgrate":14,"enqueued":745014,"dequeued":745014,"dispatched":745061,"supervised":true,"runattempt":9,"lastsignal":0,
 *                     "threads":39,"workers":10,"mutexes":197,"semaphores":18,"acceptcalls":"accept","congestion":0},
 *          "uptime":{"wall":620866,"user":84312,"kernel":44640},
 *          "bladerf":{"ifaces":1},
 *          "yrtp":{"chans":0,"mirrors":0},
 *          "sip":{"routed":0,"routing":0,"total":0,"chans":0,"transactions":0},
 *          "mbts":{"state":"RadioUp"},
 *          "ybts":{"count":0}
 *          }
 * }
 *
 * {"code":0,
 *  "stats":{
 *            "engine":{"version":"5.5.1","revision":1815,"nodename":"hostedcore_test","plugins":27,"inuse":0,"handlers":354,"hooks":5,"messages":0,"supervised":true,
 *                      "runattempt":1,"lastsignal":0,"threads":24,"workers":5,"mutexes":297,"semaphores":0,"acceptcalls":"accept","congestion":0},
 *            "ucn_gmsc":{"inbound":0,"routed":0,"diverted":0,"failed":0},
 *            "ucn_pgw":{"apns":0,"sessions":0,"bearers":0,"reservations":0,"redirecting":0},
 *            "ucn_ati":{"sent":0,"recv":0,"errs":0,"fail":0},
 *            "ucn_gtt":{"local":0,"stp":0,"back":0,"fail":0}
 *          }
 * }
 *
 * {"code":0,
 *  "stats":{
 *           "engine": {"version":"5.5.1","revision":1815,"nodename":"hostedcore_test","plugins":14,"inuse":0,"handlers":180,"hooks":4,"messages":0,"supervised":true,
 *           			"runattempt":1,"lastsignal":0,"threads":22,"workers":5,"mutexes":229,"semaphores":1,"acceptcalls":"accept","congestion":0},
 *           "hss_cluster":{"nodename":"hostedcore_test","remote":0,"state":"standalone"},
 *           "hss_repair":{"clean":true,"cycles":11087,"checks":2510095,"errors":0,"fixed":0},
 *           "auc_map":{"auth2g":0,"auth3g":0,"auth4g":0,"resyncs":0,"unknowns":0,"illegals":0,"inactives":0,"reports":0},
 *           "hss_gtt":{"local":0,"imsi":0,"msisdn":0,"stp":0,"back":0,"fail":0},
 *           "auc_diam":{"auth2g":0,"auth3g":0,"auth4g":0,"resyncs":0,"unknowns":0,"illegals":0,"inactives":0}
 *           }
 * }
 *
 */ 
	if (!isset($query_stats["stats"]))
		return;

	if ($product_name == 'hss') {
		display_hlr($query_stats["stats"]);
		return;
	}

	$stats = $query_stats["stats"];
	if ($product_name == 'Yate-SDR') {
		$stats_order = array("engine","uptime","bladerf","mbts","sip","yrtp","ybts");

		foreach ($stats_order as $k=>$stat_nm) {
			if (isset($stats[$stat_nm])) {
				$reordered_stats[$stat_nm] = $stats[$stat_nm];
				unset($stats[$stat_nm]);
			}
		}

		$new_stats = array_merge($reordered_stats, $stats);
		$stats = $new_stats;
	}

	$max = 0;
	foreach (array_keys($stats) as $i => $k) {
		if (!$i)
			continue;
		
		$wide_line = have_wide_line($stats[$k]);
		
		// count of rows under property $k
		$count = count($stats[$k]);
		// if field has more than 8 properties, display it split in 2
		if ($count>8 && !$wide_line)
			$count = $count/2;
		$max = max($max, $count);
		if (!($i % 3)) {
			$length[(int)($i / 3) - 1] = $max;
			$max = 0;
		}
	}
	if ($max)
		$length[(int)floor($i / 3)] = $max;

	$display_splitted_count = 8;
	$table_css = 'query_stats';
	$td_css = 'container_qs';
	$i = 0;
	$j = 0;
	
	print "<table class='container_query_stats' cellspacing='0' cellpadding='0'>";
	print "<tr>";
	foreach ($stats as $stat_name=>$stat_props) {
		if ($i==0) {
			$rowspan = (int)floor(count($stats) / 3) + 1;
			print "<td class='$td_css' rowspan='".$rowspan."'>";
		} else
			print "<td class='$td_css'>";
		display_stats_format(count($stat_props),$display_splitted_count,$stat_props,$stat_name,$table_css,$length[(int)floor($j / 3)]);
		print "</td>";
		$i++;
		if ($i==(4+$j)) {
			print "</tr><tr>";
	//		print "</tr><tr><td></td>";
			$td_css = 'container_right_modif';
			$j=$j+3;
		}
	}
	print "</tr>";
	print "</table>";
}

function have_wide_line($stats)
{
	foreach ($stats as $key=>$val) {
		$line_width = strlen($key) + strlen($val);
		if ($line_width > 40) {
			return true;
		}
	}
	return false;
}

function display_splitted_props($stats,$stat_name, $display)
{
	$i=1;
	print "<table class='$display $stat_name' cellspacing='0' cellpadding='0'>";
	print "<tr> <td class='prop_lev1' colspan='4'>".$stat_name." </td></tr>";
	
	$wide_line = have_wide_line($stats);	
	foreach ($stats as $prop=>$val) {
		$val = ($val===false) ? "false" : $val;
		$val = ($val===true) ? "true" : $val;
		if ($i%2==1 || $wide_line)
			print "<tr>";
		print "<td class='cat_prop2'>".$prop."</td>";
		print "<td class='cat_prop2_desc'>".$val."</td>";
		if ($i%2==0 || $wide_line)
			print "</tr>";
		$i++;
	}
	if (count($stats)%2!=0)
		print "<td class='cat_prop2'>&nbsp;</td><td class='cat_prop2_desc'>&nbsp;</td></tr>";
	print "</table>";
}

/**
 * Split HSS query stats into lines using matchin rules
 */ 
function split_lines($stats, $match, $default)
{
	$ret = array();
	foreach ($stats as $prop=>$details) {
		$line = $default;
		foreach ($match as $preg=>$ls)
			if (preg_match($preg,$prop)) {
				$line = $ls;
				break;
			}
		if ($line < 0)
			continue;
		if (!isset($ret[$line]))
			$ret[$line] = array();
		$ret[$line][$prop] = $details;
	}
	ksort($ret);
	return array_values($ret);
}

/**
 * Display hss node stats
 * The order logic of each line displayed in table:
 * LEFT:
 * 		line1: engine stats,
 * 		line2: uptime stats
 * RIGHT:
 *    line1: match /hss/: hss_cluster, hss_repair, hss_gtt
 *    line2: match /map/: auc_map, hss_map 
 *    line3: match /diam/: diameter, auc_diam, hss_diam
 *    line4: match /sig/: sig_routers, sig_links, sig_sccp
 *    line5: the rest that don't match any of the above
 *
 * Compact the lines if the current line and the next one has less 
 * than 3 blocks
 */ 
function display_hlr($stats)
{
	$default = 4;
	$split_stats = array(
		"left"	=> split_lines($stats, array(
				"/engine/"	=> 0,
				"/uptime/"	=> 1,
			), -1),
		"right" => split_lines($stats, array(
				"/engine/"	=> -1,
				"/uptime/"	=> -1,
				"/map/"		=> 1,
				"/diam/"	=> 2,
				"/sig/"		=> 3,
				"/hss/"		=> 0,
			), $default)
		);

	// compact the lines 
	$comp = array();
	if (!isset($split_stats['right'][$default]))
		$default--;
	for ($i = 0; $i < $default; $i++) {
		$curr = $split_stats['right'][$i];
		$next = $split_stats['right'][$i+1];
		if (count($curr) + count($next) <= 3) {
			$split_stats['right'][$i+1] = array_merge($curr, $next);
			continue;
		}
		$comp[] = $curr;
	}
	$comp[] = $split_stats['right'][$default];
	$split_stats['right'] = $comp;

	$length = array();
	foreach ($split_stats['right'] as $i => $line) {
		$max = 0;
		foreach ($line as $prop) {
			$count = count($prop);
			if ($count>8)
				$count = $count/2;
			$max = max($max, $count);
		}
		$length[$i] = $max;
	}

	$display_splitted_count = 8;
    $table_css = 'query_stats';
    $td_css = 'container_qs';
	print "<table class='holdcontainer_query_stats' cellspacing='0' cellpadding='0'>";
	print "<tr>";
	foreach ($split_stats as $side=>$side_stats) {
		print "<td class='hold_stats'>";
		print "<table class='container_query_stats' cellspacing='0' cellpadding='0'>";
		print "<tr>";
		foreach ($side_stats as $line=>$stats) {
				print "<tr>";
				foreach ($stats as $stat_name=>$stat_props) {
					print "<td class='$td_css'>";
					display_stats_format(count($stat_props),$display_splitted_count,$stat_props,$stat_name,$table_css,$length[$line]);
					print "</td>";
				}
				print "</tr>";
		}
		print "</tr>";
		print "</table>";
		print "</td>";
	}
		print "</tr>";
	print "</table>";
}

function display_stats_format($stats_length, $max_columns, $cat_props, $prop, $table_css,$max_length)
{
	if ($stats_length > $max_columns) //if a category has more than 8 proprieties splitted into 2 columns
		display_splitted_props($cat_props, $prop, $table_css);
	else
		display_cat_prop($cat_props, $prop, $table_css,$max_length);
}

function display_cat_prop($stat_props,$stat_name,$display,$max_length)
{
	print "<table class='$display' cellspacing='0' cellpadding='0'>";
	print "<tr> <td class='prop_lev1'>".$stat_name." </td></tr>";

	$categ_name = $stat_name;
	foreach ($stat_props as $stat_name=>$stat_val) {
		print "<tr>";
		print "<td class='cat_lev2'>";
		print $stat_name;
		print "</td>";
		print "<td class='prop_lev2'>";
		if ($categ_name=="uptime" && $stat_name=="wall") {
			$orig = $stat_val;
			$stat_val = time_elapsed($orig);
		} elseif ($stat_val === true)
			$stat_val = "true";
		elseif ($stat_val === false)
			$stat_val = "false";
		print $stat_val;
		print "</td>";
		print "</tr>";
	}
	if (count($stat_props)<$max_length)
		fill_td($max_length-count($stat_props));
	print "</table>";
}

function fill_td($total)
{
	for ($i=0; $i<$total; $i++)
		print "<tr><td class='cat_lev2'>&nbsp;</td></tr>";
}

function bool_value($value) 
{
	if ($value==="t" || $value==="1" || $value===1 || $value=="on" || $value===true || $value=== "true" )
		return true;
	return false;
}

function tri_bool_value($value)
{
	if ($value==="t" || $value==="1" || $value===1 || $value=="on" || $value===true || $value=== "true" )
		$res = "on";
	elseif ($value==="f" || $value==="0" || $value===0 || $value=="off" || $value===false)
		$res = "off";
	else
		$res = "";
	Debug::debug_message("tri_bool_value", "Mapping '$value' with type=".gettype($value)." to '$res'");
	return $res;
}

function suffix_index($index)
{
	switch (substr($index,-1)) {
		case 1:
			$suffix = "st";
			break;
		case 2:
			$suffix = "nd";
			break;
		case 3:
			$suffix = "rd";
			break;
		default:
			$suffix = "th";
	}
	return $suffix;
}

function time_elapsed($secs)
{
	$bit = array(
		//'y' => $secs / 31556926 % 12,
		//'w' => $secs / 604800 % 52,
		'd' => floor( $secs / 86400) ,
		'h' => $secs / 3600 % 24,
		'm' => $secs / 60 % 60,
		's' => $secs % 60
        );
	
	foreach ($bit as $k=>$v) {
		if ($k=="d")
			continue;
		if (strlen($v)==1)
			$bit["$k"] = "0".$v;
	}
	
	$res = ($bit["d"]) ? $bit["d"]."&nbsp;" : "";
	$res .= $bit["h"].":".$bit["m"].":".$bit["s"]."&nbsp;($secs)";

	return $res;
}

/**
* Format a flat JSON string to make it more human-readable
* Thank you: https://github.com/GerHobbelt/nicejson-php
*
* @param string $json The original JSON string to process
*        When the input is not a string it is assumed the input is RAW
*        and should be converted to JSON first of all.
* @return string Indented version of the original JSON string 
*/
function json_format($json) 
{
	if (!is_string($json)) {
		if (phpversion() && phpversion() >= 5.4) {
			return json_encode($json, JSON_PRETTY_PRINT);
		}
		$json = json_encode($json);
	}
	
	$result      = '';
	$pos         = 0;               // indentation level
	$strLen      = strlen($json);
	$indentStr   = "\t";
	$newLine     = "\n";
	$prevChar    = '';
	$outOfQuotes = true;
	for ($i = 0; $i < $strLen; $i++) {
		// Speedup: copy blocks of input which don't matter re string detection and formatting.
		$copyLen = strcspn($json, $outOfQuotes ? " \t\r\n\",:[{}]" : "\\\"", $i);
		if ($copyLen >= 1) {
			$copyStr = substr($json, $i, $copyLen);
			// Also reset the tracker for escapes: we won't be hitting any right now
			// and the next round is the first time an 'escape' character can be seen again at the input.
			$prevChar = '';
			$result .= $copyStr;
			$i += $copyLen - 1;      // correct for the for(;;) loop
			continue;
		}

		// Grab the next character in the string
		$char = substr($json, $i, 1);

		// Are we inside a quoted string encountering an escape sequence?
		if (!$outOfQuotes && $prevChar === '\\') {
			// Add the escaped character to the result string and ignore it for the string enter/exit detection:
			$result .= $char;
			$prevChar = '';
			continue;
		}
		// Are we entering/exiting a quoted string?
		if ($char === '"' && $prevChar !== '\\') {
			$outOfQuotes = !$outOfQuotes;
		}
		// If this character is the end of an element,
		// output a new line and indent the next line
		else if ($outOfQuotes && ($char === '}' || $char === ']')) {
			$result .= $newLine;
			$pos--;
			for ($j = 0; $j < $pos; $j++) {
			  $result .= $indentStr;
			}
		}
		// eat all non-essential whitespace in the input as we do our own here and it would only mess up our process
		else if ($outOfQuotes && false !== strpos(" \t\r\n", $char)) {
			continue;
		}
		// Add the character to the result string
		$result .= $char;
		// always add a space after a field colon:
		if ($outOfQuotes && $char === ':') {
			$result .= ' ';
		}
		// If the last character was the beginning of an element,
		// output a new line and indent the next line
		else if ($outOfQuotes && ($char === ',' || $char === '{' || $char === '[')) {
			$result .= $newLine;
			if ($char === '{' || $char === '[') {
				$pos++;
			}
			for ($j = 0; $j < $pos; $j++) {
				$result .= $indentStr;
			}
		}
		$prevChar = $char;
	}
	return $result;
}
/* vi: set ts=8 sw=4 sts=4 noet: */

// Creates the structure for the popup (container + overlay)
function create_popup_container()
{
	print "<div class='lightbox_overlay' style='display:none;' id='maximize_overlay'></div>";
	print "<div class='maximized_container' style='display:none;' id='maxedcontainer'></div>";
}

// Retrieve HTTP code received when trying to call specific link
function get_http_code($link)
{
	$ch = curl_init($link);
	if ($ch===false)
		return false;
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$res = curl_exec($ch);
	if ($res===false)
		return false;
	
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE );
	
	return $http_code;
}

/**
 * 
 * @param string $input_name. The name of input type file 
 * @return false/string. Return false if no error while uploading. Return error message if error occurred while uploading.
 */
function file_upload_has_err($input_name) {
	
	$err_no = $_FILES[$input_name]['error'];

	$err_codes = array(
		//0 => 'File uploaded with success',
		1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
		2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
		3 => 'The uploaded file was only partially uploaded.',
		4 => 'No file was uploaded.',
		6 => 'Missing a temporary folder.',
		7 => 'Failed to write file to disk.',
		8 => 'A PHP extension stopped the file upload.',
	);
	
	if (array_key_exists($err_no, $err_codes))
		return "Could not upload file. Error code: ". $err_no ." - ". $err_codes[$err_no];

	return false;
}

/**
 * Verifies if error occurred after json encode/decode and 
 * returns corresponding error message or false if no error occurred
 */
function translate_json_err_codes() 
{
        switch (json_last_error()) {
                case JSON_ERROR_NONE:
                        $msg = ' - No errors';
                        break;
                case JSON_ERROR_DEPTH:
                        $msg = ' - Maximum stack depth exceeded';
                        break;
                case JSON_ERROR_STATE_MISMATCH:
                        $msg = ' - Underflow or the modes mismatch';
                        break;
                case JSON_ERROR_CTRL_CHAR:
                        $msg = ' - Unexpected control character found';
                        break;
                case JSON_ERROR_SYNTAX:
                        $msg = ' - Syntax error, malformed JSON';
                        break;
                case JSON_ERROR_UTF8:
                        $msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                        break;
                case JSON_ERROR_RECURSION:
                        $msg = ' - One or more recursive references in the value to be encoded';
                        break;
                case JSON_ERROR_INF_OR_NAN:
                        $msg = ' - One or more NAN or INF values in the value to be encoded';
                        break;
                case JSON_ERROR_UNSUPPORTED_TYPE:
                        $msg = ' - A value of a type that cannot be encoded was given';
                        break;
		case JSON_ERROR_INVALID_PROPERTY_NAME:
                        $msg = ' - A property name that cannot be encoded was given';
                        break;
                case JSON_ERROR_UTF16:
                        $msg = ' - Malformed UTF-16 characters, possibly incorrectly encoded';
                        break;
                default:
                        $msg = ' - Unknown error';
        }
        
	if ($msg == ' - No errors')
		return false;
	
        return $msg;
}

/**
 * Build array for $str by breaking after provided $delimiter. Additionally call $extra on each array item
 * @param String $str
 * @param String/Char $delimiter. Default ","
 * @param Callable $extra. Default "trim"
 * @return Array
 */
function str_to_arr($str, $delimiter=",", $extra="trim")
{
	if (!is_array($str))
		$arr = (strlen($str)) ? explode($delimiter, $str) : array();
	else
		$arr = $str;
	if ($extra && is_callable($extra))
		$arr = array_map($extra, $arr);
	
	return $arr;
}

/* 
 * Check if a parameter is set and its length is bigger than 0. 
 * @param String $param
 * @return Boolean.
 */
function is_set($param)
{
	if (!isset($param))
		return false;
	if (!strlen(strval($param)))
		return false;
	return true;
}

/**
 * Prints iframe having content(src) from 'pages.php?method=". getparam("form_method")
 * 
 * @param array $additional_params Array containing the names of the additional params to be added in src
 */
function import_file_iframe($additional_params = array())
{
	$form_method = getparam("form_method");
	if (!is_callable($form_method))
		Debug::trigger_report('critical', "import_file_iframe: Callable ".print_r($form_method,true)." provided in param 'form_method' is not implemented.");
        
	$src = "pages.php?method=".$form_method;
	
	if (is_array($additional_params) && count($additional_params)) {
		foreach ($additional_params as $additional_param)
			$src .= "&{$additional_param}=".getparam($additional_param);
	} else {
		foreach ($_REQUEST as $param => $value){
			if ($param==="form_method" || $param==="method")
				continue;
			$src .= "&{$param}={$value}";
		}
	}
	print "<iframe id='import_iframe' class='import_iframe' src='$src'>";
    print "</iframe>";
}

function open_html_iframe() 
{
	global $debug_tags_js;

	print "<html>";
	print "<head>";
	print "<meta http-equiv='content-type' content='text/html; charset=UTF-8'/>";
	if ($addon_path=is_addon("font-awesome")) {
		print "<link type=\"text/css\" rel=\"stylesheet\" href=\"".$addon_path."/css/font-awesome.min.css\"/>";
	}
	print "<link type=\"text/css\" rel=\"stylesheet\" href=\"" . set_file_version("css/main.css") . "\" />";
	print "<link type=\"text/css\" rel=\"stylesheet\" href=\"" . set_file_version("css/wizard.css") . "\" />";
	print "<script type=\"text/javascript\" src=\"" . set_file_version("javascript/lib_wizard.js") . "\"></script>";
	print "<script type=\"text/javascript\" src=\"" . set_file_version("ansql/javascript.js") . "\"></script>";
	print "<script> var js_tags_array = ".json_encode($debug_tags_js) . ";</script>";
	print "</head>";
	print "<body class=\"mainbody\">";
	print "<div id=\"import_form\">";
}

function close_html_iframe() 
{
	print "</div>";
	print "</body>";
	print "</html>";
}

/**
 * General function for displaying import form
 * @param string $class. Name of the objects class.
 * @param array $examples. Keeps the name, path and other info for example files.  Same array used in display_pair for display=file.
 * @param string $error. Error message 
 * @param array $error_fields. Array containing fields having incorrect values.
 * @param string $cb_cancel. Name of the function which will be called after the execution of this function is
 * @param array $cb_cancel_parameters. Id of the equipment if objects to be imported are not global objects
 * @param type $form_action_params Parameters to be added to action URL. 
 *			  Ex: 
 *			   For $form_action_params = array("equipment_id" => 15, "language_id" => 12);  
 *			   Results <form action="pages.php?method=import_languages_database&equipment_id=15&language_id=12" ...
 * @param bool $iframe. If true will indicate that the form is inside an iframe, therefore is needed to add the html structure for a 
 *			html document and to link JS and CSS docs so we can use css and javascript inside iframe
 * @param string $form_action_method. Name of the function used for validation. Will be added to action URL. 
 *			  Ex: 
 *			   For $form_action_method = array("method" => "import_languages_database")
 *			   Results <form action="pages.php?method=import_languages_database"
 * @param array $fields. Array of type field_name=>field_formats
 */
function import_form_obj($class, $examples, $error="", $error_fields = array(), $cb_cancel = null,$cb_cancel_parameters=array(),$form_action_params=array(),$iframe=true, $form_action_method=null, $fields=array(), $form_action = "pages.php")
{	
	if (!$cb_cancel)
		$cb_cancel = "display_" . get_plural_form($class);
	
	if ($iframe) {
		open_html_iframe();
		// when using iframe we need to use a custom cancel button which will:
		//  -  replace the iframe content with the content displayed from calling cb and 
		//  -  will remove iframe by replacing iframe with it's own content
		//
		$cb_parameters = (is_array($cb_cancel_parameters) && count($cb_cancel_parameters)) ? json_encode($cb_cancel_parameters) : "false";
		$fields["buttons"] = array(	
			"display" => "custom_submit", 
			"value"   => "<input class=\"edit\" name=\"Upload\" value=\"Upload\" type=\"submit\">
				     <input class=\"edit\" value=\"Reset\" type=\"reset\">
				     <input type=\"button\" onClick='import_iframe_cancel_button(\"$cb_cancel\",$cb_parameters);' value=\"Cancel\" />"
		);
	}
			
	$fields["insert_file_location"] = $examples;
	$fields["insert_file_location"]["display"] = "file";
	
	$button_name = (!isset($fields["buttons"])) ? "Upload" : "no";
	
	// Important! We add values in form action as request parameters, 
	// instead of adding hidden fields with this values, 
	// so, we don't lose this params, if files uploaded bigger than post_max_size
	if (!$form_action_method)
		$form_action_method = "import_" . get_plural_form($class) . "_database";
	$form_action = $form_action . "?method=" . $form_action_method;
	if (is_array($form_action_params) && count($form_action_params)) {
		foreach ($form_action_params as $name => $value)
			$form_action .=  "&".$name."=".$value;
	}
	
	error_handle($error, $fields, $error_fields);
	start_form($form_action, "POST", true, $cb_cancel);	
	editObject(NULL,$fields,"Import " . str_replace("_", " ", get_plural_form($class)) . " from file", $button_name);
	end_form();
	
	if ($iframe)
		close_html_iframe();
}

/**
 * General function to process csv file imported via import form
 * @param $class String. The name of the object's class.
 * @param $additional_params Array. The additional parameters to be imported in the file. 
 * @param $mandatory_cols Array. The names of the required cols.
 * @param $unique_cols Array. The names of the table columns which need to contain unique values.
 * @param $cb String. The name of the function which will be called if import file is valid
 * @param $other_validation_cb String. The name of the callback for validating import file.
 * When using other callback for validation the callback needs to return array(true/false, "message", array(err_fields).
 * @param $iframe Bool. If true will indicate that the form is inside a iframe, therefore is needed to add the html structure
 * for a html document and to link JS and CSS docs so we can use css and javascript inside iframe.
 * @param $container_id String. The id if the container holding the iframe (if iframe param is true).
 * @param $cb_custom_params String. The name of the callback to validate custom params added in custom fields in import form. 
 * @param $type_file String. The type of the file imported.
 * @return array. Returns array(true) if validations passed, else array(false,"error message") otherwise.
 */
function process_import_file($class, $additional_params=array(), $mandatory_cols = array(), $unique_cols = array(), $cb = null, $other_validation_cb = null, $iframe=true, $container_id = null, $cb_custom_params=null, $type_file = "csv")
{
	if (!class_exists($class))
		return array(false, "Provided class '$class' is not defined!", array());
	
	$res = validate_import_file($class, $mandatory_cols, $type_file);
	if (!$res[0])
		return $res;
	
	$data = $res[1];
	
	if ($other_validation_cb) {
		if (!is_callable($other_validation_cb))
			Debug::trigger_report('critical', "process_import_file: Callable ".print_r($other_validation_cb,true)." which should have been used for extra validation is not implemented.");

		$res = call_user_func($other_validation_cb, $data);
		if (!$res)
			return array(false, "Can not upload file: ".$res, array());
	}
	
	if ($cb_custom_params && !is_callable($cb_custom_params))
		Debug::trigger_report('critical', "process_import_file: Callable ".print_r($cb_custom_params,true)." which should add custom params is not implemented.");

	if (!$cb)
		$cb = "display_" . get_plural_form($class);
		
	if (!is_callable($cb))
		Debug::trigger_report('critical', "process_import_file: Callable ".print_r($cb,true)." is not implemented.");
	
	if ($iframe)
		open_html_iframe();
	
	$mes = import_file_data($data, $additional_params, $class, $mandatory_cols, $unique_cols, $cb_custom_params);
	notice($mes,$cb);
	
	if ($iframe) {
		close_html_iframe();
		if (!$container_id)
			$container_id = "custom_step_0";
		// remove iframe by replacing it with it's content so we can keep 
		// the success mesage displayed inside it and 
		// the content received from calling the cb 
		echo "<script> import_iframe_response('$container_id');</script>";
	}
	
	return array(true);
}

/**
 * General function to validate csv file imported via import form
 * @param $class String. Name of the object's class.
 * @param $mandatory_cols Array. The names of the required table columns.
 * @param $type_file String. The type of the file imported.
 * @return array. Returns array(true,array_with_csv_lines) if validations passed, and array(false,"error message") otherwise
 */
function validate_import_file($class, $mandatory_cols = array(), $type_file = "csv")
{
	global $upload_path;

	if (!class_exists($class))
		Debug::trigger_report('critical', "validate_import_file: Provided class: ".print_r($class,true)." is not implemented.");
	
	$res = check_file_upload(".".$type_file);
	if (!$res[0])
		return $res;
	$file = $res[1];

	if ($type_file == "csv") {
		$handle = fopen($file,'r');	
		$res    =  csv_to_arr($handle);
	} elseif ($type_file == "json") {
		$res    =  json_to_arr($file);
	} else {
		return array(false, "The validation for .".$type_file." file is not implemented.", array());
	}
	
	if (!$res["col_names"] || !is_array($res["data"]) || !count($res["data"])) {
		return array(false, "Can not upload file. File seems to be empty.", array());	
	}
	$data         = $res["data"];// $data = array( array(col1=>val1,col2=>val2, ...), array(col1=>val1,col2=>val2, ...), ...)
	$file_columns = $res["col_names"];

	$columns = $class::variables();	
	$cols    = array();
	foreach ($columns as $col_name => $value) {
		$cols[] = $col_name;
		if ($value->_required && !in_array($col_name, $mandatory_cols))
			$mandatory_cols[] = $col_name;
	}
	
	$missing_col = "";
	foreach ($mandatory_cols as $mandatory_col) {
		if (!in_array($mandatory_col, $file_columns)) {			
			$missing_col = $mandatory_col;
			break;
		}
	}

	if ($missing_col) {
		$msg =  (is_array($mandatory_cols) && count($mandatory_cols)) ? "Columns '" .implode("', '",$mandatory_cols)."' are mandatory!" : "Column '" .implode("', '",$mandatory_cols)."' is mandatory!";
		return array(false, "Can not upload file. Column '" . $missing_col ."' was not found in file. " . $msg, array());	
	}
	
	return array(true, $data);
}

/**
 * Generic function to import data from a file.
 * ! does not verify file structure! 
 * ! Use validate_import_file() to validate file structure
 * @param $data Array. The information to be imported. Format: // $data = array( array(col1=>val1,col2=>val2),array(col1=>val1,col2=>val2) ...)
 * @param $additional_params Array. The additional params to be inserted into database.
 * @param $class String. Name of the class. The table were the columns from $data will be inserted.
 * @param $mandatory_cols Array. The names of the required columns.
 * @param $unique_cols Array. The names of the columns which need to contain unique values.
 * @param $cb_custom_params Array. The name of the callback to validate custom params added in custom fields in import form. 
 */
function import_file_data($data, $additional_params, $class, $mandatory_cols = array(), $unique_cols=array(), $cb_custom_params=null)
{
	if (!class_exists($class))
		Debug::trigger_report('critical', "import_file_data: Provided class: ".print_r($class,true)." is not implemented.");

	$cols = array();
	$vars = $class::variables();	
	foreach ($vars as $col_name=>$value)
		$cols[] = $col_name;

	$lines_imported = 0;	
	foreach ($data as $line_no=>$line) {
		$params = $additional_params;
		$obj = new $class;
		$line_no = $line_no + 1;
		
		foreach ($line as $col_name=>$col_val) {
			$params[$col_name] = $col_val;

			if (in_array($col_name, $mandatory_cols) && $col_val===null) {
				warning_mess("Skipped line " . $line_no . ": Column '$col_name' must not be empty!","no");
				continue 2;
			}
			if (in_array($col_name, $unique_cols)) {
				
				$cond = array($col_name => $col_val);
				
				foreach ($additional_params as $prop=>$value)
					$cond[$prop] = ($value===null) ? "__empty" : $value;
				
				$count = $obj->fieldSelect("count(*)", $cond);
				if ($count) {
					warning_mess("Skipped line " . $line_no . ": Column '$col_name' must have unique values! Value '$col_val' already exists in ".str_replace("_", " ", get_plural_form($class)).".", "no");
					continue 2;
				}
			}
		}
		if ($cb_custom_params) {
			if (!is_callable($cb_custom_params))
				Debug::trigger_report('critical', "import_file_data: Callable ".print_r($cb_custom_params,true)." is not implemented.");

			$params = call_user_func ($cb_custom_params, $params);
		}
		$res = $obj->add($params);
		if (!$res[0])
			errormess("Could not import line " . $line_no . ": " . $res[1], "no");
		else
			$lines_imported++;
	}
	$plural = ucfirst(str_replace("_"," ",get_plural_form($class)));
	$mes    = ($lines_imported == 0) ? "There were no lines imported in " . $plural . "." : "Succesfully imported " . $lines_imported . " " . $plural . ".";
	
	return $mes;
}

/**
 * General function used to export into .csv / .json all objects or just the objects checked from the provided class
 * The function excludes columns containing object id's.
 * @param $class String. Class name from which we will export objects
 * @param $cond Array. If provided, export data fulfilling the condition
 * @param $cb String. Name of the function which will be called after the execution of this function 
 * @param $exclude_cols Array. Columns which will not be included in exported file
 * @param $export_all Bool. If true will export all objects from provided class
 * @param $order_by_cb String. Callback used to order array of objects returned by selection() (because oreder_by param does not work in Temporary Model)
 * @param $export_type String. The type of the file exported
 */
function export_objects($class, $cond=array(), $cb = null, $exclude_cols = array(), $export_all = false, $order_by_cb = null, $export_type = "csv")
{
	$date     = date('mdYhis', time());
	$filename = $class."_".$date;

	if (!$cb)
		$cb = "display_".get_plural_form($class);
	
	if (!is_callable($cb))
		Debug::trigger_report('critical', "export_objects: Callable ".print_r($cb,true)." is not implemented.");

	if (!class_exists($class))
		Debug::trigger_report('critical', "export_objects: Provided class: ".print_r($class,true)." is not implemented.");

	// get columns for export and exclude columns containing object ids
	// and also exclude columns from exclude_columns array
	$columns   = $class::variables();
	$col_names = array();
	foreach ($columns as $col_name => $val) {
		if (substr($col_name,-3) === "_id")
			continue;
		if (in_array($col_name, $exclude_cols))
			continue;
		$col_names[] = $col_name;
	}
	
	if (class_exists("TemporaryModel")) 
		$objs = TemporaryModel::selection($class,$cond);
	else
		$objs = Model::selection($class,$cond);

	if ($order_by_cb)
		$objs = call_user_func($order_by_cb, $objs);

	// if we have just one object it does not make sense to "check_" it
	if (!$export_all && count($objs)>1) {
		$selected_obj_ids = get_selected_chk();
		if(!count($selected_obj_ids)) {
			errormess("No " . str_replace("_", " ",$class) . " selected.", "no");
			return call_user_func($cb);
		}

		// remove objects which were not checked for export
		$obj_id = $class."_id";
		foreach ($objs as $key => $obj) {
			if (in_array($obj->$obj_id, $selected_obj_ids))
				continue;
			unset($objs[$key]);
		}
	}
	
	$arr = Model::objectsToArray(array_values($objs), "all");
	
	if ($export_type == "csv") {
		//replace double quotes with single quotes
		foreach ($arr as $key => $line) {
			foreach ($line as $name => $value) {
				$arr[$key][$name] = str_replace ('"', "'", $arr[$key][$name]);
			}
		}
		arr_to_csv($filename, $arr, $col_names,"write_in_file",null,true);
	} elseif ($export_type == "json") {
		arr_to_json($filename.".json", $arr, $col_names);
	} else {
		Debug::trigger_report('critical', "export_objects: The export objects for type '" .  $export_type . "' is not implemented.");
	}

	call_user_func($cb);
}

function get_selected_chk() 
{
	$selected_obj_ids = array();
	foreach ($_REQUEST as $field_name => $val) {
		if (substr($field_name, 0, 6) !== "check_")
			continue;

		$checkbox_value = getparam($field_name);
		if (bool_value($checkbox_value) || $checkbox_value=="on") {
			$selected_obj_ids[] = (int)substr($field_name, 6);
		} else
			continue;
	}
	
	return $selected_obj_ids;
}

/**
 * Return corresponding error type as stated here:
 * https://www.php.net/manual/en/errorfunc.constants.php
 * @param int $type_id
 * @return Corresponding error type
 */
function translate_error_type($type_id)
{
	switch ($type_id) {
		case 1:
			return "E_ERROR";
		case 2:
			return "E_WARNING";
		case 4:
			return "E_PARSE";
		case 8:
			return "E_NOTICE";
		case 16:
			return "E_CORE_ERROR";
		case 32:
			return "E_CORE_WARNING";
		case 64:
			return "E_COMPILE_ERROR";
		case 128:
			return "E_COMPILE_WARNING";
		case 256:
			return "E_USER_ERROR";
		case 512:
			return "E_USER_WARNING";
		case 1024:
			return "E_USER_NOTICE";
		case 2048:
			return "E_STRICT";
		case 4096:
			return "E_RECOVERABLE_ERROR";
		case 8192:
			return "E_DEPRECATED";
		case 16384:
			return "E_USER_DEPRECATED";
		case 32767:
			return "E_ALL";
	}
}

/**
 * Function used for pre-validatating file. 
 * Verifies if a file with specified extension was provided in $_FILES and uploads it in $upload_path/time().$ext
 * @global type $upload_path
 * @param type $ext
 * @param $name String. The name of the field where the file is uploaded.
 * @return array. If success returns array(true, " path to file") otherwise returns array(false, msg, array())
 */
function check_file_upload($ext, $name = "insert_file_location")
{
	global $upload_path;
	
	$last_error = error_get_last();
	
	if ($last_error!==NULL) {
		$err_type = translate_error_type($last_error["type"]);
		
		return array(false, "Encountered PHP error: Type: '".$err_type . "' , Message: '" . $last_error["message"] ."'", array());
	}
	if (!is_array($_FILES) || !count($_FILES))
		return array(false, "No file uploaded!", array());
	
	if ($err = file_upload_has_err($name))
		return array(false, $err, array());

	$filename = basename($_FILES[$name]["name"]);
	
	$len = -1 * strlen($ext);
	$file_ext = strtolower(substr($filename,$len));
	if ($file_ext!==$ext)
		return array(false,"File type must be ".$ext."!", array());

	if (!is_dir($upload_path))
		mkdir($upload_path);

	$real_name = time().$ext;
	$sep       = (substr($upload_path,-1)=="/") ? "" : "/";
	$file      = $upload_path . $sep . $real_name;
	
	if (!move_uploaded_file($_FILES[$name]['tmp_name'],$file))
		return array(false, "Could not upload file.", array());
	
	return array(true, $file);
}

/**
 * Reads all lines from csv and transforms them into an array having next format:
 * array( 
 *    array("col_11"=>"val_11", "col_12"=>"val_12"),
 *	  array("col_21"=>"val_21", "col_22"=>"val_22"),
 *	  ..... 
 * );
 * return array("data"=> array_containing_all_csv_lines, "col_names"=>array_containing_col_names);
 * @param resource $handle. File pointer resource.
 */
function csv_to_arr($handle) 
{
	$fields    = array(); //  col values
	$col_names = array(); //  col names
	$data      = array(); //  all lines from file

	while (($line = fgetcsv($handle)) !== FALSE) {
		if (!count($col_names)) {
			// this is the first line containing the names
			foreach ($line as $col_name) {
				$col_name  = remove_csv_escape_chars($col_name) ;
				$col_name  = str_replace(" ", "_", strtolower($col_name));
				
				$col_names[] = $col_name;
			}
			continue;
		}
		
		for ($j=0; $j<count($col_names); $j++) {
			$line[$j]  = remove_csv_escape_chars($line[$j]) ;
				
			$fields[$col_names[$j]] = (isset($line[$j]) && strlen(trim($line[$j]))) ? $line[$j] : null;
		}
		$data[] = $fields;
 	}
	return array("data"=>$data, "col_names"=>$col_names);
}

/**
 * Get file content and decodes json into array
 * return array("data"=>array_containing_all_json_data, "col_names"=>array_containing_col_names);
 * @param $file String. The file name with path.
 */
function json_to_arr($file)
{
	$json = $col_names = array();
	$string = file_get_contents($file);
	$json = json_decode($string, true);

	if (isset($json[0]))
		$col_names = array_keys($json[0]);

	return array("data"=>$json, "col_names"=>$col_names);
}

/**
 * Removes escape characters (single quotes, double quotes and equal sign)
 * from the beginning and end of the provided string
 */
function remove_csv_escape_chars($value) 
{
	$value  = str_replace(array('"="', "'='"), '', $value);
	
	if (substr($value,0,2)=='="')
		$value = substr($value,2);
	if (substr($value,0,1)=='"')
		$value = substr($value,1);
	if (substr($value,-1)=='"')
		$value = substr($value,0,-1);
	
	return $value;
}

/**
* Sniff the user agent of the browser and return a nice array with all the information 
* Thank you: https://gist.github.com/james2doyle/5774516
*
* @param boolean $concat_name_version Must be true if needed to return browser name and version in same element
* @return array
*/
function getBrowser($return_all_in_name = true)
{ 
	if (!isset($_SERVER['HTTP_USER_AGENT']))
		return array(
			'userAgent' => "",
			'name'      => "",
			'version'   => "",	
			'platform'  => "",
			'pattern'   => ""
			);
	
	$u_agent = $_SERVER['HTTP_USER_AGENT'];
	$bname = 'Unknown';
	$platform = 'Unknown';
	$version= "";

	//First get the platform?
	if (preg_match('/linux/i', $u_agent)) {
		$platform = 'linux';
	} elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
		$platform = 'mac';
	} elseif (preg_match('/windows|win32/i', $u_agent)) {
		$platform = 'windows';
	}
	$ub = "";
	// Next get the name of the useragent yes seperately and for good reason
	if (preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)){
		$bname = 'Internet Explorer';
		$ub = "MSIE";
	} elseif(preg_match('/Firefox/i',$u_agent)){
		$bname = 'Mozilla Firefox';
		$ub = "Firefox";
	} elseif(preg_match('/OPR/i',$u_agent)){
		$bname = 'Opera';
		$ub = "Opera";
	} elseif(preg_match('/Chrome/i',$u_agent) && !preg_match('/Edge/i',$u_agent)){
		$bname = 'Google Chrome';
		$ub = "Chrome";
	} elseif(preg_match('/Safari/i',$u_agent) && !preg_match('/Edge/i',$u_agent)){
		$bname = 'Apple Safari';
		$ub = "Safari";
	} elseif(preg_match('/Netscape/i',$u_agent)){
		$bname = 'Netscape';
		$ub = "Netscape";
	} elseif(preg_match('/Edge/i',$u_agent)){
		$bname = 'Edge';
		$ub = "Edge";
	} elseif(preg_match('/Trident/i',$u_agent)){
		$bname = 'Internet Explorer';
		$ub = "MSIE";
	}

	// finally get the correct version number
	$known = array('Version', $ub, 'other');
	$pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	if (!preg_match_all($pattern, $u_agent, $matches)) {
	  // we have no matching number just continue
	}
	// see how many we have
	$i = count($matches['browser']);
	if ($i > 1) {
		//we will have two since we are not using 'other' argument yet
		//see if version is before or after the name
		if (strripos($u_agent,"Version") < strripos($u_agent,$ub)) {
			 $version= $matches['version'][0];
		} else {
			$version= $matches['version'][1];
		}
	} else if ($i==1){
		$version= $matches['version'][0];
	}

	// check if we have a number
	if ($version==null || $version=="") {
		$version="?";
	}
	
	if ($return_all_in_name)
		$name = $bname . " v." . $version;
	else 
		$name = $bname;
		
	$res = array(
		'userAgent' => $u_agent,
		'name'      => $name,
		'version'   => $version,	
		'platform'  => $platform,
		'pattern'   => $pattern
	);
	return $res;
} 

/**
 * Function used to return the protocol per equipment or from $_SERVER scheme
 * @param string $eq_id 
 * @return string "http" or "https"
 */
function get_request_protocol($eq_id=null)
{
	//Select the fields from the equipment with that id
	if(isset($eq_id))
	{
		$equipment = new Equipment;
		$equipment->equipment_id = $eq_id;
		$equipment->select();
		$protocol = $equipment->management_request_protocol;
	}
	if(isset($protocol) && $protocol!="automatically")
		return $protocol;

	if ( (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') || 
	  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || 
	  (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') )
	       return 'https';
	
	return 'http';
}

/**
* Get dropdown options 
* @param array $options Options of the dropdown
* Ex: Simple options: $options = array("test1", "test2")
*      Assoc options: $options = array(array("opt_id"=>"test1","opt"=>"Test Nr. 1"), array("opt_id"=>"test2","opt"=>"Test Nr. 2"))
* @param string/null $option_id The option ID / dropdown name used in assoc options 
* Ex: dropdown name: $option_id = "opt" // the function will add suffix _id and look for "opt_id" options
*         option id: $option_id = "opt_id" // function will look for "opt_id" options
* @return array of options ex: array("test1", "test2")
*/
function get_dropdown_options($options, $option_id = null)
{
	$dropdown_options = array();
	if ($option_id && substr($option_id,-3) !== "_id")
		$option_id .= "_id";
	
	foreach($options as $key => $option) {
		if ($key === "selected")
			continue;
		if ($option_id && isset($option[$option_id])) {
			//assoc options
			$dropdown_options[] = $option[$option_id];
		} else {
			//simple options
			$dropdown_options[] = $option;
		}
	}
	
	return $dropdown_options;
}

/** SCROLL TO functions **/

/**
 * Add anchor anywhere in project so when scroll_anchor() function is called it will scroll directly to this anchor.
 * Note: Function was created to be used in combination with scroll_anchor()
 * 
 * @param string $id The anchor id
 */
function add_scroll_anchor($id = "bottom")
{
	$inline_scroll_css = "visibility:hidden; font-size:1px;padding:0px;margin:0px;height:1px;width:1px;";
	$inline_container_css = "height:1px;clear:left;padding:0px;margin:0px;";
	
	echo "<div class=\"scroll_anchor\" style=\"{$inline_container_css}\" >"
		. "<a id=\"{$id}\" href=\"#{$id}\" style=\"{$inline_scroll_css}\" class=\"scroll_anchor\">&nbsp;</a>"
	. "</div>";
}

/**
 * Use this function to scroll to specified anchor.
 * Note: You must use this function in combination with add_scroll_anchor().
 * 
 * @param string $id  The anchor id
 */
function scroll_anchor($id = "bottom")
{
	echo "<script>window.addEventListener('load', function(){  window.location = \"#{$id}\"; });</script>";
}

/**End SCROLL TO functions **/

/**
 * Verifies if error occurred after json encode/decode and 
 * returns corresponding error message or false if no error occurred
 */
function translate_preg_match_err_codes() 
{
        switch (preg_last_error()) {
                case PREG_NO_ERROR:
                        $msg = 'No errors';
                        break;
                case PREG_INTERNAL_ERROR:
                        $msg = 'There was an internal PCRE error';
                        break;
                case PREG_BACKTRACK_LIMIT_ERROR:
                        $msg = 'Backtrack limit was exhausted';
                        break;
                case PREG_RECURSION_LIMIT_ERROR:
                        $msg = 'Recursion limit was exhausted';
                        break;
                case PREG_BAD_UTF8_ERROR:
                        $msg = 'The offset didn\'t correspond to the begin of a valid UTF-8 code point';
                        break;
                case PREG_BAD_UTF8_OFFSET_ERROR:
                        $msg = 'Malformed UTF-8 data';
                        break;
                default:
                        $msg = 'Unknown error';
        }

	if ($msg == 'No errors')
		return false;
	
        return $msg;
}

/**
 * Return true if module/method was found in the $exceptions_return_button array or method is the main tab (module = method)
 */
function exception_return_button()
{
	Debug::func_start(__FUNCTION__,func_get_args(),"ansql");
	global $method, $module, $exceptions_return_button, $custom_return_button;

	if (isset($exceptions_return_button) && isset($exceptions_return_button[$_SESSION["level"]]) && isset($exceptions_return_button[$_SESSION["level"]]["$module"]) && (in_array($method, $exceptions_return_button[$_SESSION["level"]]["$module"]) || (in_array("__all", $exceptions_return_button[$_SESSION["level"]]["$module"]))))
		return true;
	
	if (isset($exceptions_return_button) && isset($exceptions_return_button[$_SESSION["level"]]) && isset($exceptions_return_button[$_SESSION["level"]]["methods"]) && (in_array($method, $exceptions_return_button[$_SESSION["level"]]["methods"])))
		return true;
	
	if (!isset($_SESSION["level"]) && isset($exceptions_return_button["default"][$method]))
		return true;

	//do not display return button in the first page of a tab (module is the same with method)
	//!! button is displayed if method is mentioned in the $custom_return_button array
	if (($module == $method) && (!isset($custom_return_button[$method])))
		return true;
	
	return false;
}

/**
 * This is a wrapper function for return_button().<br>
 * Use this function to automatically display return button for multiple methods.<br>
 * Exceptions from applying return button are discussed in exceptions_return_button() function.<br>
 * In order to customize the return button value, global $custom_return_button array should be set.
 * 
 * @param string $button_name Default is "Return". Is the name set on the return button.
 * @param bool $container_div Default true. If true return button is contained inside a div element.
 * @param string $alignment Default is "right". Is the alignment of the return button inside div_container element. If $container_div is false, the alignment is not used.
 * @param string $additional_css Default is "return" css class. If specified, is the second css class given to the return button element, besides "llink" css class.
 * 
 * @return - By default return button is a link to previous page. <br>
 * If return button is applied for methods from pages.php, the "onclick" event is used with the following value: <br>
 * 	    &nbsp;&nbsp; onclick = make_request('pages.php?method=$method&extra_param=$extra_param', {callback_request, param:'$cb_param'});<br>
 * Custom return button is always set on 'onclick' event and can have the following structures:<br>
 *	1. onclick = "location.href='main.php?module=...";
 *	2. onclick = function_name();
 *	3. onclick = function_name(param_1, param_2, param_3....param_n);
 *	4. onclick = function_name('function_name2', {'cb_param_name1':'cb_param_value1', 'cb_param_name2':'cb_param_value2'....});
 *	5. onclick = function_name('request_url'/'request_url?url_param_name=url_param_value&url_param_name=url_param_value&...', {'cb_param_name1':'cb_param_value1', 'cb_param_name2':'cb_param_value2'....});
 *	!If not specified, default function on onclick is 'make_request'. 
 */ 
function auto_return_button($button_name = "Return", $container_div = true, $alignment = "right", $additional_css = "return") 
{
	global $module, $method, $custom_return_button;

	$method = getparam("method") ? getparam("method") : $method;

	//do not display return button for modules/methods specified in the exceptions array or if methods is a main tabs
	if (exception_return_button()) {
		return;
	}
	
	//set custom return button for methods specified in the global $custom_return_button array -->
	
	if (isset($custom_return_button[$method])) {
		if (isset($custom_return_button[$method]["skip_return_button"]))
			return;
		//default function on onclick is 'make_request'
		$onclick = (isset($custom_return_button[$method]["onclick_func"])) ? $custom_return_button[$method]["onclick_func"] : "make_request";
		
		//build arguments for onclick function (cases: multiple arguments, function with callback or request link)
		if (isset($custom_return_button[$method]["func_params"])) {
			$onclick .= "(";
			if (isset($custom_return_button[$method]["cb_params"]) || isset($custom_return_button[$method]["cb_get_params"]))
				$onclick .= "'";
			$onclick .= implode(",", $custom_return_button[$method]["func_params"]);
		} else if (isset($custom_return_button[$method]["request_url"])) {
			$onclick .= "('".$custom_return_button[$method]["request_url"];
			//"request_url_params" is set for parameters for which the value is obtained with getparam()
			if (isset($custom_return_button[$method]["request_url_params"])) {
				$onclick .= (strpos($custom_return_button[$method]["request_url"], "?") == false) ? "?" : "&";
				foreach ($custom_return_button[$method]["request_url_params"] as $key=>$param) {
					$onclick .= $param."=".getparam($param)."&";
				}
			}
		} else if ((isset($custom_return_button[$method]["href_params"])) || (isset($custom_return_button[$method]["href_custom_params"])))  {
			if (isset($custom_return_button[$method]["href_params"])) {
				$onclick .= (strpos($custom_return_button[$method]["onclick_func"], "?") == false) ? "?" : "&";
				foreach ($custom_return_button[$method]["href_params"] as $key=>$param) {
					if(is_numeric($key))
						$onclick .= $param."=".getparam($param)."&";
					else
						$onclick .= $key."=".$param."&";
				}
			}

			$onclick .= "'";
		}
		
		//build cb argument as json
		if (isset($custom_return_button[$method]["cb_params"])) {
			$onclick .= "', {";
			$temp = array();
			foreach ($custom_return_button[$method]["cb_params"] as $key=>$value) {
				if(is_numeric($key))
					$temp[] = "'".$value."':'".getparam($value)."'";
				else
					$temp[] = $key.":".$value;
			}
			$onclick .= implode(",", $temp)."}";
		}
		
		//close onclick function if arguments were passed to the function
		if (isset($custom_return_button[$method]["request_url"]) || isset($custom_return_button[$method]["func_params"]))
			$onclick .= ")";

		//overwrite default/general parameters if specified in custom array
		$custom_css = (isset($custom_return_button[$method]["css"])) ? "llink ".$custom_return_button[$method]["css"] : "llink ".$additional_css;
		$custom_button_name = (isset($custom_return_button[$method]["button_name"])) ? ($custom_return_button[$method]["button_name"]) : $button_name;
		$custom_alignment = (isset($custom_return_button[$method]["alignment"])) ? ($custom_return_button[$method]["alignment"]) : $alignment;
		$custom_div_container = (isset($custom_return_button[$method]["div_container"])) ? ($custom_return_button[$method]["div_container"]) : $container_div;
		
		return_button(null, null, $custom_alignment, $custom_button_name, $custom_div_container, $custom_css, $onclick);
		return;
	} 
	
	//set return button for normal modules loaded from main.php -->
	
	if ((isset($_SERVER['REQUEST_URI'])) && (strpos($_SERVER['REQUEST_URI'], "pages.php") == false )) {
		$css = "llink ".$additional_css;
		
		return_button(null, null, $alignment, $button_name, $container_div, $css, null);
		return;
	} 
	
	//set default return button for methods used in wizard steps -->
	
	//recreate the name of the function to be called when return button is accessed
	$ajax_method = null;
	if ((strpos($method, "form_edit") !== false ) && (strpos($method, "_db") !== false)) {
		$display_method = str_replace ("form_edit", "display", $method);
		$ajax_method = get_plural_form(str_replace ("_db", "", $display_method));
	} elseif ((strpos($method, "form_") !== false ) && (strpos($method, "_db") !== false)) {
		$display_method = str_replace ("form", "display", $method);
		$ajax_method = get_plural_form(str_replace ("_db", "", $display_method));
	} elseif (strpos($method, "form_edit") !== false ) {
		$ajax_method = get_plural_form(str_replace ("form_edit", "display", $method));
	} elseif (strpos($method, "form_import") !== false ) {
		$ajax_method = get_plural_form(str_replace ("form_import", "display", $method));
	} elseif (strpos($method, "form") !== false ) {
		$ajax_method = get_plural_form(str_replace ("form", "display", $method));
	} elseif (strpos($method, "_db") !== false) {
		$ajax_method = "display_".get_plural_form(str_replace ("_db", "", $method));
	}
	
	if (empty($ajax_method)) {
		return;
	}
		
	if(!is_callable($ajax_method)) {
		return;
	}
	
	//get the default extra params, set in the global $custom_return_button array
	$extra_params = "";
	if (isset($custom_return_button["url_default_params"])) {
		foreach ($custom_return_button["url_default_params"] as $request_url_param) {
			$extra_params .= "&".$request_url_param."=".getparam($request_url_param);
		}
	}
	//build request link
	$request_link = "pages.php?method=".$ajax_method.$extra_params;
	//get cb param if different from default ("custom_step_0)
	$cb_param = (getparam("fieldname")) ? getparam("fieldname") : "custom_step_0";
	$onclick = "make_request('".$request_link."', {name:callback_request, param:'".$cb_param."'})";
	$css = "llink ".$additional_css;
	
	return_button(null, null, $alignment, $button_name, $container_div, $css, $onclick);
}

/*
 * Returns the properties of a directory/file (permissions, owner, group)
 * @param $path String. Path to the desired directory/file
 * @return array of file properties if path is valid, otherwise return null.
 */
function get_file_properties($path)
{
	if (!file_exists($path))
		return null;
	
	$file_params = array(
		"permissions" => array(
			"bash_output_index" => 0
		),
		"owner" => array(
			"posix_func" => "posix_getpwuid",
			"posix_func_param" => fileowner($path),
			"bash_output_index" => 2
		), 
		"group" => array(
			"posix_func" => "posix_getgrgid",
			"posix_func_param" => filegroup($path),
			"bash_output_index" => 3
		)
	);
	
	//Get the last file/directory from path
	$split_index = strrpos($path, "/");
	$file_path = substr($path, 0, $split_index);
	$file_name = substr($path, intval($split_index) + 1);
	
	//Extract file option from ls -l command
	$command = "cd {$file_path}; ls -l | grep {$file_name}";
	$output = array();
	exec($command,$output);
	$bash_line = null;
	if (isset($output[0]))
		$bash_line = preg_replace('/\s+/', ' ', $output[0]);
	
	//Default file options
	$file_properties = array();
	
	foreach ($file_params as $param => $opts) {
		//If posix extension is active, return desired option using posix function
		if (isset($opts["posix_func"]) && function_exists($opts["posix_func"])) {
			$res = call_user_func($opts["posix_func"],$opts["posix_func_param"]);
			if (isset($res["name"])) {
				$file_properties[$param] = $res["name"];
				continue;
			}
		}
		
		if ($bash_line) {
			$results = explode(" ", $bash_line);
			$bash_output_index = $opts["bash_output_index"];
			$file_properties[$param] = $results[$bash_output_index];
			continue;
		}
	
		$file_properties[$param] = null;
	}
	
	return $file_properties;
}

function remove_dir($path) 
{
	$files = glob($path . '/*');
	if (!$files)
		return;
	
	foreach ($files as $file) {
		is_dir($file) ? remove_dir($file) : unlink($file);
	}
	rmdir($path);

	return;
}

/**
 * The function highlights the specify needle in the specified haystack.
 * @param string $haystack The string in which $needle should be highlighted.
 * @param string $needle The string that should be highlighted.
 * @param bool $insensitive The search case type. Default is true. If true case type is insensitive. If false, case type is sensitive.
 * @param string $match_type Is the type of the match. Possible values: 'starts', 'ends', 'exact' and empty string. Default is empty string. <br>
 *	empty string -> is the default value. Matches the string everywhere inside the haystack. (ex. EIR will be highlighted in 'theirs') <br>
 *	'starts' -> search in haystack the string that starts with the specified needle. (ex. 'param' will be highlighted both in 'param' as in 'params') <br>
 *	'ends' -> search in haystack the string that ends with the specified needle <br>
 *	'exact' -> search in haystack the exact needle string. (ex. EIR won't be highlighted in 'theirs')
 * @param string $bg_color The background-color to apply where the $needle is found. Default is '#76FBF5'.
 * @return string The inputed $haystack string, highlighted where $needle was found. 
 */
function highlight($haystack, $needle, $insensitive = true, $match_type = "", $bg_color = "#76FBF5")
{
	$case = ($insensitive) ? "/i" : "/";
	$words = array();
	$needle = preg_quote($needle, '/');
	preg_match_all("/".$needle.$case, $haystack, $words);
	$result = $haystack;
	
	if (!isset($words[0]))
		return $result;
	$found_words = array_unique($words[0]);
	foreach($found_words as $word) {
		//do not highlight strings inside <a> html tag links
		$search = "/<a[\S\s]+?<\/a>(*SKIP)(*FAIL)|";
		$original_word = $word;
		$word = preg_quote($word, '/');
		switch($match_type) {
			case "starts":
				$search .= "\b".$word."/";
				break;
			case "ends":
				$search .= $word."\b/";
				break;
			case "exact":
				$search .= "\b".$word."\b/";
				break;
			default:
				$search .= $word."/";
		}
		
		$result = preg_replace($search, "<span style='background-color:".$bg_color.";'>".$original_word."</span>", $result);
	}
	return $result;
}

/**
 * 
 * Verify if project hhas searched addon
 * @param string $adddon Addon partial or full name for addon folder
 * @return boolean|string Return false if there is no addon containing provided string as name
 */
function is_addon($addon)
{
	if (!is_dir(__DIR__ . "/../addons"))
		return false;
	
	$addons = scandir(__DIR__ . "/../addons/");
	
	$matches = preg_grep("/".$addon."/",$addons);
	if (!count($matches))
		return false;
	
	$matches = array_values($matches);
	
	$path = "addons/" . $matches[0];
	
	return $path;
}

/**
 * Transform hours into seconds
 */
function hours_to_seconds($hours)
{
	return $hours * 60 * 60;
}

/**
 * Associative list of timezone offsets. 
 * Array format:
 *	value = timezone name
 *	key = offset in seconds between timezone and UTC standard time 
 * Ex: 
 *	Array
	(
	    [Africa/Abidjan] => 0
	    [Africa/Accra] => 0
	    [Africa/Addis_Ababa] => 10800
	    [Africa/Algiers] => 3600
	    ............
	    [Europe/Bucharest] => 7200
	    ............
	    [Pacific/Rarotonga] => -36000
	    [Pacific/Saipan] => 36000
	    [Pacific/Tahiti] => -36000
	    [Pacific/Tarawa] => 43200
	    [Pacific/Tongatapu] => 50400
	    [Pacific/Wake] => 43200
	    [Pacific/Wallis] => 43200
	    [UTC] => 0
    )
 * @return associative array
 */
function timezone_offsets_list()
{
	$timezones = timezone_identifiers_list();
	// add 'GMT' to list of recognized timezones
	$timezones[] = "GMT";

	$timezone_offsets = array();
	foreach( $timezones as $timezone )
	{
	    $tz = new DateTimeZone($timezone);
	    $timezone_offsets[$timezone] = $tz->getOffset(new DateTime);
	}

	return $timezone_offsets;
}


/**
 * List of timezones groupped by offset
 * Array format:
 *	key = offset in seconds between timezone and UTC standard time 
 *	value = group of timezone names
 * Array
(
    [0] => Array
        (
            [0] => Africa/Abidjan
            [1] => Africa/Accra
            .......
            [26] => Europe/Lisbon
            [27] => Europe/London
            [28] => UTC
        )
     ....

     [7200] => Array
        (
            [0] => Africa/Blantyre
            [1] => Africa/Bujumbura
            [2] => Africa/Cairo
	    .....
            [22] => Europe/Bucharest
            [23] => Europe/Chisinau
            ....
            [32] => Europe/Vilnius
            [33] => Europe/Zaporozhye
        )
     ....
 )
 * @return array
 */
function timezones_grouped_by_offset()
{
	$timezone_offsets = timezone_offsets_list();
	
	// group timezones by offsets
	$timezone_group = array();
	foreach( $timezone_offsets as $timezone => $offset )
	    $timezone_group[$offset][] = $timezone;

	return $timezone_group;
}

/**
 * List of timezone offsets grouped by offset and sorted by timezone name 
 * Ex: 
 *	Array
	(
	     [Pacific/Midway] => -39600
	     [Pacific/Niue] => -39600
	     [Pacific/Pago_Pago] => -39600
	     [America/Adak] => -36000
	     [Pacific/Honolulu] => -36000
	     [Pacific/Rarotonga] => -36000
	     [Pacific/Tahiti] => -36000
	     [Pacific/Marquesas] => -34200
	     ............
	     [Europe/London] => 0
	     [UTC] => 0
	     .............
	     [Europe/Bucharest] => 7200
	     .............
	     [Pacific/Fiji] => 46800
	     [Pacific/Chatham] => 49500
	     [Pacific/Apia] => 50400
	     [Pacific/Kiritimati] => 50400
	     [Pacific/Tongatapu] => 50400
	)
 * @return associative array
 */
function sorted_timezone_offsets_list()
{
	$timezones_list = timezones_grouped_by_offset();

	//sort the list by offsets
	ksort($timezones_list);

	$timezone_offsets = array();
	foreach ($timezones_list as $offset => $timezones) {
		//Sort each timezones group by timezone name
		asort($timezones);
		foreach ($timezones as $timezone) {
			$timezone_offsets[$timezone] = $offset;
		}
	}
	
	return $timezone_offsets;
	
}

/**
 * List of timezone offsets grouped by offset and sorted by timezone name 
 * Ex: 
 *	Array
	(
	    [Pacific/Midway] => (GMT-11:00) Pacific/Midway
	    [Pacific/Niue] => (GMT-11:00) Pacific/Niue
	    [Pacific/Pago_Pago] => (GMT-11:00) Pacific/Pago_Pago
	    [America/Adak] => (GMT-10:00) America/Adak
	    [Pacific/Honolulu] => (GMT-10:00) Pacific/Honolulu
	    [Pacific/Rarotonga] => (GMT-10:00) Pacific/Rarotonga
	    [Pacific/Tahiti] => (GMT-10:00) Pacific/Tahiti
	    [Pacific/Marquesas] => (GMT-09:30) Pacific/Marquesas
	    ............
	    [Europe/London] => (GMT+00:00) Europe/London
	    [UTC] => (GMT+00:00) UTC
	    .............
	    [Europe/Bucharest] => (GMT+02:00) Europe/Bucharest
	    .............
	    [Pacific/Chatham] => (GMT+13:45) Pacific/Chatham
	    [Pacific/Apia] => (GMT+14:00) Pacific/Apia
	    [Pacific/Kiritimati] => (GMT+14:00) Pacific/Kiritimati
	    [Pacific/Tongatapu] => (GMT+14:00) Pacific/Tongatapu
    )
 * @return associative array
 */
function pretty_timezone_list($offset_format="H:i", $prefix="GMT")
{
	$timezone_offsets = sorted_timezone_offsets_list();

	$timezone_list = array();
	foreach ($timezone_offsets as $timezone => $offset) {
		$offset_prefix = $offset < 0 ? '-' : '+';
		$offset_formatted = gmdate($offset_format, abs($offset) );

		$pretty_offset = $prefix . $offset_prefix . $offset_formatted;

		$timezone_list[$timezone] = "({$pretty_offset}) $timezone";
	}

	return $timezone_list;
}

/**
 * List of timezone hours sorted by hour
 * Ex: 
 *	Array
	(
		[-11:00] => GMT-11:00
		[-10:00] => GMT-10:00
		[-09:30] => GMT-09:30
		[-09:00] => GMT-09:00
		[-08:00] => GMT-08:00
		[-07:00] => GMT-07:00
		[-06:00] => GMT-06:00
		[-05:00] => GMT-05:00
		[-04:00] => GMT-04:00
		[-03:30] => GMT-03:30
		[-03:00] => GMT-03:00
		[-02:00] => GMT-02:00
		[-01:00] => GMT-01:00
		[+00:00] => GMT+00:00
		[+01:00] => GMT+01:00
		[+02:00] => GMT+02:00
		[+03:00] => GMT+03:00
		[+03:30] => GMT+03:30
		[+04:00] => GMT+04:00
		[+04:30] => GMT+04:30
		[+05:00] => GMT+05:00
		[+05:30] => GMT+05:30
		[+05:45] => GMT+05:45
		[+06:00] => GMT+06:00
		[+06:30] => GMT+06:30
		[+07:00] => GMT+07:00
		[+08:00] => GMT+08:00
		[+08:30] => GMT+08:30
		[+08:45] => GMT+08:45
		[+09:00] => GMT+09:00
		[+09:30] => GMT+09:30
		[+10:00] => GMT+10:00
		[+10:30] => GMT+10:30
		[+11:00] => GMT+11:00
		[+12:00] => GMT+12:00
		[+13:00] => GMT+13:00
		[+13:45] => GMT+13:45
		[+14:00] => GMT+14:00	     
	)
 * @return associative array
 */
function pretty_timezone_hours_list($offset_format="H:i", $prefix="GMT")
{
	$timezones = sorted_timezone_offsets_list();
	
	$hours_list = array();
	foreach ($timezones as $timezone) {
		
		$offset_prefix = $offset < 0 ? '-' : '+';
		$offset_formatted = gmdate($offset_format, abs($offset) );

		$pretty_offset = $prefix . $offset_prefix . $offset_formatted;

		$hours_list[$offset_formatted] = $pretty_offset;
	}
	
	return $hours_list;
}

/**
 * Get the timestamp for provided datetime 
 * @param string $format To see valid formats reffer to https://www.php.net/manual/en/datetime.createfromformat.php
 * @param string $human_datetime Datetime formated in the specifed $format. 
 * Ex:  
 *	for $format = 'Y-m-j H:i:s' 
 *	$human_datetime must be like: "2020-02-05 13:07:02"
 * 
 *	also for this example format the hour must be between 00 and 23
 * 
 * @param string $timezone_name To see valid timezone examples go to https://www.php.net/manual/en/datetimezone.listidentifiers.php
 * @return array(true, timestamp) on succes, array(false, message) on failure
 * !Caution! The error message might contain HTML <br> tags
 */
function human_datetime_to_timestamp($format,$human_datetime,$timezone_name,$glue_error_message="<br>")
{
	$timezone = new DateTimeZone($timezone_name);
	
	$datetime = DateTime::createFromFormat($format, $human_datetime, $timezone);
	$err_msg = array();
	
	$errors = DateTime::getLastErrors();
	
	if (isset($errors["error_count"]) && $errors["error_count"]>0)
		$err_msg[] = implode($glue_error_message,$errors["errors"]);

	if (isset($errors["warning_count"]) && $errors["warning_count"]>0)
		$err_msg[] = implode($glue_error_message,$errors["warnings"]);

	$err_msg = implode($glue_error_message,$err_msg);
	if (strlen($err_msg)>0)
		return array(false, $err_msg);
	
	return array(true, $datetime->getTimestamp());
}

/**
 * Function checks if PHP is used in CLI mode. 
 * @return boolean Returns true if CLI mode is used, otherwise returns false.
 */
function is_cli()
{
	if (php_sapi_name() == "cli")
		return true;
	return false;
}

/**
 * Calculate differences between two dates with precise semantics. Based on PHPs DateTime::diff()
 * implementation by Derick Rethans. Ported to PHP by Emil H, 2011-05-02. No rights reserved.
 * 
 * See here for original code:
 * http://svn.php.net/viewvc/php/php-src/trunk/ext/date/lib/tm2unixtime.c?revision=302890&view=markup
 * http://svn.php.net/viewvc/php/php-src/trunk/ext/date/lib/interval.c?revision=298973&view=markup
 */
function _date_range_limit($start, $end, $adj, $a, $b, &$result)
{
    if ($result[$a] < $start) {
        $result[$b] -= intval(($start - $result[$a] - 1) / $adj) + 1;
        $result[$a] += $adj * intval(($start - $result[$a] - 1) / $adj + 1);
    }
	
    if ($result[$a] >= $end) {
        $result[$b] += intval($result[$a] / $adj);
        $result[$a] -= $adj * intval($result[$a] / $adj);
    }

    return $result;
}

function _date_range_limit_days(&$base, &$result)
{
    $days_in_month_leap = array(31, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $days_in_month = array(31, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

    _date_range_limit(1, 13, 12, "m", "y", $base);

    $year = $base["y"];
    $month = $base["m"];

    if (!$result["invert"]) {
        while ($result["d"] < 0) {
            $month--;
            if ($month < 1) {
                $month += 12;
                $year--;
            }

            $leapyear = $year % 400 == 0 || ($year % 100 != 0 && $year % 4 == 0);
            $days = $leapyear ? $days_in_month_leap[$month] : $days_in_month[$month];

            $result["d"] += $days;
            $result["m"]--;
        }
    } else {
        while ($result["d"] < 0) {
            $leapyear = $year % 400 == 0 || ($year % 100 != 0 && $year % 4 == 0);
            $days = $leapyear ? $days_in_month_leap[$month] : $days_in_month[$month];

            $result["d"] += $days;
            $result["m"]--;

            $month++;
            if ($month > 12) {
                $month -= 12;
                $year++;
            }
        }
    }

    return $result;
}

function _date_normalize(&$base, &$result)
{
    $result = _date_range_limit(0, 60, 60, "s", "i", $result);
    $result = _date_range_limit(0, 60, 60, "i", "h", $result);
    $result = _date_range_limit(0, 24, 24, "h", "d", $result);
    $result = _date_range_limit(0, 12, 12, "m", "y", $result);

    $result = _date_range_limit_days($base, $result);

    $result = _date_range_limit(0, 12, 12, "m", "y", $result);

    return $result;
}

/**
 * Accepts two unix timestamps.
 */
function _date_diff($one, $two)
{
    $invert = false;
    if ($one > $two) {
        list($one, $two) = array($two, $one);
        $invert = true;
    }

    $key = array("y", "m", "d", "h", "i", "s");
    $a = array_combine($key, array_map("intval", explode(" ", date("Y m d H i s", $one))));
    $b = array_combine($key, array_map("intval", explode(" ", date("Y m d H i s", $two))));

    $result = array();
    $result["y"] = $b["y"] - $a["y"];
    $result["m"] = $b["m"] - $a["m"];
    $result["d"] = $b["d"] - $a["d"];
    $result["h"] = $b["h"] - $a["h"];
    $result["i"] = $b["i"] - $a["i"];
    $result["s"] = $b["s"] - $a["s"];
    $result["invert"] = $invert ? 1 : 0;
    $result["days"] = intval(abs(($one - $two)/86400));

    if ($invert) {
        _date_normalize($a, $result);
    } else {
        _date_normalize($b, $result);
    }

    return $result;
}

function date_difference($start,$end=null)
{
	if (!class_exists('DateTime')) {
		if (!$end)
			$end = time();

		$res = _date_diff($start, $end);
		if ($res["y"]!=0)
			return $res["y"]."y ".$res["m"]."m ". $res["d"]."d";
		if ($res["m"]!=0)
			return $res["m"]."m ". $res["d"]."d ". $res["h"]."h";
		if ($res["d"]!=0)
			return $res["d"]."d ". $res["h"]."h ". $res["i"]."m";
		if ($res["h"]!=0)
			return $res["h"]."h ". $res["i"]."m ". $res["s"]."s";
		if ($res["i"]!=0)
			return $res["i"]."m ". $res["s"]."s";

		return $res["s"]."s";
	}

	$start = date('Y-m-d H:i:s',$start);
	if (!$end)
		$end = date('Y-m-d H:i:s');
	else
		$end = date('Y-m-d H:i:s',$end);
	$d_start = new DateTime($start);
	$d_end   = new DateTime($end);
	$diff = $d_start->diff($d_end);

	if ($diff->format('%y'))
		return $diff->format('%y'). "y ". $diff->format('%m'). "m ". $diff->format('%d')."d";
	elseif ($diff->format('%m'))
		return $diff->format('%m'). "m ". $diff->format('%d')."d ". $diff->format('%h')."h";
	elseif ($diff->format('%d'))
		return$diff->format('%d')."d ". $diff->format('%h')."h ". $diff->format('%i')."m";
	elseif ($diff->format('%h'))
		return $diff->format('%h')."h ". $diff->format('%i')."m ". $diff->format('%s')."s";
	elseif ($diff->format('%i'))
		return $diff->format('%i')."m ". $diff->format('%s')."s";
	else
		return $diff->format('%s')."s";
}
/**
 *  Function display custom alert message for a specified field into $generate_alert_message array from structure.php
 *  @param $fieldname string that specified the field name
 *  @return Array where [0] is bool value showing whether action succesed , [1] is the default action
 */
function display_alert_message($fieldname) 
{
	global $generate_alert_message, $method;

	if (!is_array($generate_alert_message))
		return array(false, "Not a valid array.");
	
	$check_last_digit = $fieldname[strlen($fieldname)-1];
	if (is_numeric($check_last_digit) && ($fieldname[strlen($fieldname)-2] == "_")) {
		$current_fieldname = substr($fieldname, 0, strlen($fieldname)-1);
		$current_fieldname = rtrim($current_fieldname, "_");
	} elseif (is_numeric($check_last_digit) && $method == "network_settings")
		$current_fieldname = substr($fieldname, 0, strlen($fieldname)-1);
		
	foreach ($generate_alert_message as $key => $value) {
		$keys = explode("/", $key);
		$params = array();
		foreach ($keys as $item) {
			$item = trim($item);
			array_push($params, $item);
		}
		
		if ($method != $params[0])
			continue;

		if ((isset($current_fieldname) && in_array($current_fieldname, $params)) || in_array($fieldname, $params)) {
			$message = $value["message"];
			return array(true, "onchange = \"show_alert_message('$fieldname' , message = '$message');\""); 
		}
	}
	return array(false, "No need to set a specific alert message for the current field.");
}

?>

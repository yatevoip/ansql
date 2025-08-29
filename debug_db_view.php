<?php

/**
 * debug_db_view.php
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

if (is_file("defaults.php"))
	include_once("defaults.php");
if (is_file("config.php"))
	include_once("config.php");

if (!isset($view_log_db_conn))
	$view_log_db_conn = connect_view_db_log();

function connect_view_db_log()
{
	global $view_log_db_host,$view_log_db_user,$view_log_db_database,$view_log_db_passwd,$view_log_db_port;

	if (!function_exists("mysqli_connect")) {
		error_log("Missing mysqli package for php installed on ". $view_log_db_host);
		Debug::trigger_report('critical', "You don't have mysqli package for php installed on ". $view_log_db_host);
                return false;
	}

	$conn = mysqli_connect($view_log_db_host, $view_log_db_user, $view_log_db_passwd, $view_log_db_database, $view_log_db_port);
	if (!$conn) {
		error_log("Connection failed to the log database: ". mysqli_connect_error());
		Debug::trigger_report('critical', "Connection failed to the log database: ". mysqli_connect_error());
                return false;
	}

	return $conn;
}

function view_query($query)
{
	global $view_log_db_conn;

	if (!isset($view_log_db_conn))
              $view_log_db_conn = connect_view_db_log();

	$res = mysqli_query($view_log_db_conn, $query);
	return $res;
}
/**
 * Function used to display database API logs with pagination and search bar. This function can be used as additional module to the application.
 */
function display_db_api_logs()
{
	global $method, $limit, $page;

	/* Need to be set in order to create and display the "Cancel" button created using editObject() function. */
	$_SESSION["previous_page"] = array("module" => "display_db_api_logs");
	$method = "display_db_api_logs";

	$total = getparam("total");
	$conditions = build_db_log_conditions();

	$query = "SELECT count(*) FROM logs ".$conditions.";";
	$res = view_query($query);
	$result = mysqli_fetch_assoc($res);
	if (isset($result["count(*)"]))
		$total = $result["count(*)"];

	items_on_page($total, null,  array("log_tag","log_type","log_from","log_contains","performer","performer_id","from_date","to_date","start_time","end_time","run_id"));
	pages($total, array("log_tag","log_type","log_from","log_contains","performer","performer_id","from_date","to_date","start_time","end_time","run_id"),true);

	system_db_search_box($conditions);
	br();

	$formats = array(
		"Date"		=> "date",
		"Log tag"	=> "log_tag",
		"Log type"	=> "log_type",
		"Log from"	=> "log_from",
		"Performer"	=> "performer",
		"Performer ID"	=> "performer_id",
		"function_run_id_filter:Run ID"		=> "run_id",
		"function_log_display:Log"		=> "log",
	);

	$query = "SELECT * FROM logs ".$conditions." limit ".$limit." offset ".$page.";";
	$result = view_query($query);
	$logs = mysqli_fetch_all($result, MYSQLI_ASSOC);
	table($logs, $formats, "Logs", "", array(), array(), NULL, false, "content");
}

/*
 * Function used to display run_id as a link that opens in new web page and displays all related logs;
 */
function run_id_filter($run_id)
{
	$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	// remove limit and page parameters before opening new tab
	$arr_link = explode("&",$actual_link);
	foreach($arr_link as $key=>$value) {
		if ((strpos($value, "page") !== false) || (strpos($value, "limit") !== false))
			unset($arr_link[$key]);
	}

	$link = implode("&", $arr_link);
	$new_link = $link."&run_id=".$run_id;

	print "<a class='llink' href='$new_link' target='_blank'>".$run_id."</a>";
}

function log_display($log)
{
	print "<div style='overflow-wrap:anywhere;text-align:left;'>".$log."</div>";
}
/**
 * Function used to build database API logs display conditions.
 * @return string
 */
function build_db_log_conditions()
{
	global $log_db_conn;

	$conditions = array();

	$fields = array("performer","performer_id","log_contains","run_id");
	foreach ($fields as $param) {
		$val = getparam($param);
		if ($val) {
			if ($param == "log_contains")
				$conditions["log"] = " LIKE '%".$val."%' OR log_tag LIKE '%".$val."%'";
			else
				$conditions[$param] = "='".$val."'";
		}
	}

	$multival_fields = array("log_tag","log_type","log_from");
	foreach ($multival_fields as $param) {
		$val = getparam($param);
		if (!strlen($val))
			continue;
		$val = str_replace(" ", "", $val);
		$val = explode(",",$val);
		if (count($val)) {
			if ($param == "log_type")
				$conditions[$param] = " LIKE '%".implode("%' OR ".$param." LIKE '%",$val)."%'";
			else
				$conditions[$param] = "='".implode("' OR ".$param."='",$val)."'";
		}
	}

	$from_date = getparam("from_date");
	$to_date = getparam("to_date");
	if ($from_date || $to_date) {
		$start = $end = null;
		if ($from_date) {
			$start_time = (getparam("start_time")) ? getparam("start_time") : "00:00:00";
			$start = $from_date." ".$start_time;
		}
		if ($to_date) {
			$end_time = (getparam("end_time")) ? getparam("end_time") : "23:59:59.99";
			$end = $to_date." ".$end_time;
		}
		if ($start && $end)
			$conditions["date"] = ">'".$start."' AND date<'".$end."'";
		elseif(!isset($start))
			$conditions["date"] = "<'".$end."'";
		elseif (!isset($end))
			$conditions["date"] = ">'".$start."'";
	}

	$where = "";
	$count = 1;
	foreach($conditions as $parameter=>$value) {
		$where .= ($count == 1) ? " WHERE " : " AND ";
		$where .= "(".$parameter.$value.")";
		$count++;
	}

	return $where;
}

/**
 * Function builds database API logs search bar.
 */
function system_db_search_box($conditions = "")
{
	$filters = array("log_tag","log_from","log_type");
	foreach ($filters as $filter) {
		${$filter} = array();
		$query = "SELECT DISTINCT ".$filter." FROM logs;";
		$res = view_query($query);
		if (mysqli_num_rows($res) > 0) {
			// output data of each row
			while($row = mysqli_fetch_assoc($res)) {
				if ($row[$filter]) {
					if ($filter == "log_type") {
						$values = explode(",", $row[$filter]);
						foreach($values as $value) {
							$value = trim($value);
							if(!in_array($value, ${$filter}))
								${$filter}[] = $value;
						}
					} else
						${$filter}[] = $row[$filter];
				}
			}
		}
	}

	$from_date = getparam("from_date");
	if (!$conditions) {
		$query = "SELECT date FROM logs ORDER BY log_id DESC LIMIT 1;";
		$res = view_query($query);
		$result = mysqli_fetch_assoc($res);
		if (isset($result["date"]))
			$from_date = strtok($result["date"]," ");
	}
	$title = array("Date", "Performer", "Performer ID","Log tag", "Log type", "Log from", "Log contains", "&nbsp;", "&nbsp;");
	$fields = array(
			array(
			    '<span id="filter_by_date">'.
			    '<div style="padding-top:5px;"><label><span style="display:inline-block; width:40px;">From:&nbsp;</span><input type="date" name="from_date" style="width:100px;" value="'.$from_date.'"/>&nbsp;<input type="time" style="width:100px;" id="start_time" name="start_time" value="'.getparam("start_time").'"/></label></div>'.
			    '<div style="padding-top:5px;"><label><span style="display:inline-block; width:40px;">To:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><input type="date" style="width:100px;" name="to_date" value="'.getparam("to_date").'"/>&nbsp;<input type="time" style="width:100px;" id="end_time" name="end_time"  value="'.getparam("end_time").'"/></label></div>'.'</span>',
			    "<input type=\"text\" value=". html_quotes_escape(getparam("performer"))." name=\"performer\" id=\"performer\" size=\"10\"/>",
			    "<input type=\"text\" value=". html_quotes_escape(getparam("performer_id"))." name=\"performer_id\" id=\"performer_id\" size=\"10\"/>",
			    html_checkboxes_filter(array("checkboxes"=>$log_tag, "checkbox_input_name"=>"log_tag")),
			    html_checkboxes_filter(array("checkboxes"=>$log_type, "checkbox_input_name"=>"log_type")),
			    html_checkboxes_filter(array("checkboxes"=>$log_from, "checkbox_input_name"=>"log_from")),
			    "<input type=\"text\" value=". html_quotes_escape(getparam("log_contains"))." name=\"log_contains\" id=\"log_contains\" size=\"20\"/>",
			    "&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"submit\" value=\"Search\" />",
			    '<input type="reset" value="Reset" onClick="window.location=\'main.php?module=display_db_api_logs\'"/>'
			)
		    );

	if (getparam("run_id")) {
		$title = array_merge(array("Filter by Run ID"), $title);
		$fields[0] = array_merge(array("<span style='color:#000;'>".getparam("run_id")."</span> <input type='checkbox' id='filter_run_id' name='filter_run_id' onchange='remove_run_id();' checked/>"), $fields[0]);
	}

	$hidden_params = array(
		"page"		    => 0,
		"performer_id"	    => getparam("performer_id"),
		"performer"	    => getparam("performer"),
		"log_tag"	    => getparam("log_tag"),
		"log_type"	    => getparam("log_type"),
		"log_from"	    => getparam("log_from"),
		"log_contains"	    => getparam("log_contains"),
		"from_date"	    => getparam("from_date"),
		"to_date"	    => getparam("to_date"),
		"run_id"	    => getparam("run_id")
	    );

	start_form(null,"get");
	addHidden(null, $hidden_params);
	formTable($fields,$title);
	end_form();

	print "<script>document.addEventListener('click', function (e) {hide_select_checkboxes(e,['log_tag','log_type','log_from']);});</script>";
	if (!$conditions)
		print "<script>document.getElementById('display_db_api_logs').submit();</script>";
}
?>
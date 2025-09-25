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

	$conn = manual_db_connection($view_log_db_host, $view_log_db_user, $view_log_db_passwd, $view_log_db_database, $view_log_db_port);

	return $conn;
}

function view_query($query, $conn = null)
{
	global $view_log_db_conn;

	if ($conn) {
		$res = mysqli_query($conn, $query);
		return $res;
	}

	if (!isset($view_log_db_conn))
              $view_log_db_conn = connect_view_db_log();

	$res = mysqli_query($view_log_db_conn, $query);
	return $res;
}
/**
 * Function used to display database API logs with pagination and search bar. This function can be used as additional module to the application.
 */
function display_db_api_logs($conn = null)
{
	global $method, $limit, $page;

	$total = getparam("total");
	$conditions = build_db_log_conditions();

	$query = "SELECT count(*) FROM logs ".$conditions.";";
	$res = view_query($query,$conn);
	$result = mysqli_fetch_assoc($res);
	if (isset($result["count(*)"]))
		$total = $result["count(*)"];

	items_on_page($total, null,  array("log_tag","log_type","log_from","log_contains","performer","performer_id","from_date","to_date","start_time","end_time","run_id"));
	pages($total, array("log_tag","log_type","log_from","log_contains","performer","performer_id","from_date","to_date","start_time","end_time","run_id"),true);

	system_db_search_box($conditions, $conn);
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
	$result = view_query($query, $conn);
	$logs = mysqli_fetch_all($result, MYSQLI_ASSOC);
	table($logs, $formats, "Logs", "", array(), array(), NULL, false, "content");
}

/*
 * Function used to display run_id as a link that opens in new web page and displays all related logs;
 */
function run_id_filter($run_id)
{
	$module = (getparam("module")) ? getparam("module") : "display_db_api_logs";
	$method = (getparam("method")) ? getparam("method") : "display_db_api_logs";
	$protocol = (empty($_SERVER['HTTPS'])) ? 'http' : 'https';
	$link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?module={$module}&method={$method}";
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
	$conditions = array();

	$fields = array("performer","performer_id","log_contains","run_id");
	foreach ($fields as $param) {
		$val = getparam($param);
		if ($val) {
			if ($param == "log_contains")
				$conditions["log"] = " LIKE '%".$val."%' OR log_tag LIKE '%".$val."%'";
			elseif ($param == "performer")
				$conditions["performer"] = " LIKE '%".$val."%'";
			else
				$conditions[$param] = "='".$val."'";
		}
	}

	$multival_fields = array("log_tag","log_type","log_from");
	foreach ($multival_fields as $param) {
		$val = getparam($param);
		if (!strlen($val))
			continue;
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
function system_db_search_box($conditions = "", $conn = "")
{
	$module = getparam("module");
	$method = getparam("method");

	$filters = array("log_tag","log_from","log_type");
	foreach ($filters as $filter) {
		${$filter} = array();
		$query = "SELECT DISTINCT ".$filter." FROM logs;";
		$res = view_query($query,$conn);
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
						sort(${$filter});
				}
			}
		}
	}

	$from_date = getparam("from_date");
	if (!$conditions) {
		$query = "SELECT date FROM logs ORDER BY log_id DESC LIMIT 1;";
		$res = view_query($query,$conn);
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
			    '<input type="reset" value="Reset" onClick="window.location=\'main.php?module='.$module.'&method='.$method.'\'"/>'
			)
		    );

	if (in_array("api_logs",$log_type)) {
		$fields[0][] = '<input type="button" value="API logs" onClick="filter_db_logs();"/>';
	}

	if (in_array("notification_logs",$log_type)) {
		$fields[0][] = '<input type="button" value="Notification logs" onClick="filter_db_logs(\'notification_logs\');"/>';
	}

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
		"from_date"	    => getparam("from_date"),
		"to_date"	    => getparam("to_date"),
		"run_id"	    => getparam("run_id")
	    );

	start_form(null,"get",false,"system_db_search_box");
	addHidden(null, $hidden_params);
	formTable($fields,$title);
	end_form();

	print "<script>document.addEventListener('click', function (e) {hide_select_checkboxes(e,['log_tag','log_type','log_from']);});</script>";
	if (!$conditions)
		print "<script>document.getElementById('system_db_search_box').submit();</script>";
	else {
		print "<script>save_selected_checkboxes('log_tag', true);</script>";
		print "<script>save_selected_checkboxes('log_type', true);</script>";
		print "<script>save_selected_checkboxes('log_from', true);</script>";
	}
}
?>
<?php
/**
 * bug_params.php
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

require_once("ansql/framework.php");
require_once("ansql/lib.php");
// Class used for keeping debug reports 
class Bug_report extends Model
{
	public static function variables()
	{
		return array(
			"bug_report_id" => new Variable("serial"),
			"date" => new Variable("timestamp"),
			"hostname" => new Variable("text"), 
			"application" => new Variable("text"), 
			"version" => new Variable("text"), 
			"interfaces" => new Variable("text"),
			"report_name" => new Variable("text"),
			"username" => new Variable("text"),
			"report_for" => new Variable("text"),
		);
	}

	public static function aggregate_report($bug_params)
	{
		$interfaces = implode(",",$bug_params["interfaces"]);
		$hostname = $bug_params["host_name"];
		$email = isset($bug_params["email"]) ? $bug_params["email"] : "";
		unset($bug_params["log"],$bug_params["interfaces"],$bug_params["host_name"],$bug_params["email"]);
		$bugs = Model::selection("bug_report", $bug_params);
		if (!count($bugs)) {
			$obj = new Bug_report;
			$obj->date = "now()";
			$obj->username = $bug_params["username"];
			$obj->hostname = $hostname;
			$obj->application = $bug_params["application"];
			$obj->version = $bug_params["version"];
			$obj->interfaces = $interfaces;
			$obj->report_name = $bug_params["report_name"];
			$obj->report_for = $email;
			// insert the bug report whitout trying to retrive the id and without going into a loop of inserting bug report 
			$obj->insert(false,false);
			return true;
		}

		$bug = $bugs[0];
		if (time() < strtotime($bug->date) + 86400)
			return false;

		$bug->date = "now()";
		$bug->fieldUpdate(array("bug_report_id"=>$bug->bug_report_id), array("date"));
		return true;
	}
}

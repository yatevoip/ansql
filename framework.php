<?php
/**
 * framework.php
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
/**
* the base classes for the database framework
*/

if (is_file("defaults.php"))
	require_once("defaults.php");

if (is_file("config.php"))
	require_once("config.php");

if (!isset($enforce_basic_constrains))
	$enforce_basic_constrains = false;
if (!isset($critical_col_diff))
	$critical_col_diff = false;
if (!isset($check_col_diff))
	$check_col_diff = true;

require_once("debug.php");

config_globals_from_session();

// class for defining a variable that will be mapped to a column in a sql table
// name of variable must not be a numer or a numeric string
class Variable
{
	public $_type;
	public $_value;
	public $_key;
	public $_owner;
	public $_critical;
	public $_matchkey;
	public $_join_type;
	public $_required;
	/**
	 * Constructor for Variable class. Name of variable must not be a string
	 * @param $type Text representing the type of object: serial, text, int2, bool, interval etc
	 * @param $def_value Text or number representing the default value. Exception: if $def_value is set to !null then $required parameter is considered true. Exception was added so that we don't set a list false and null default params and to maintain compatibility
	 * @param $foreign_key Name of the table this column is a foreign key to. Unless $match_key is defined, this is a foreign
	 * key to a column with the same name in the $foreign_key table
	 * @param $critical Bool value. 
	 * true for ON DELETE CASCADE, if referenced row is deleted then this one will also be deleted
	 * false for ON DELETE SET NULL, if referenced is deleted then this column will be set to null
	 * @param $match_key Referenced variable name (Text representing the name of the column from the $foreign_key table to which this variable(column) will
	 * be a foreign key to).If not given the name is the same as this variable's
	 * @param $join_type Usually set when extending objects. Possible values: LEFT, RIGHT, FULL, INNER. Default is LEFT is queries.
	 * @param $required Bool value specifing whether this field can't be null in the database. Default value is false
	 */
	function __construct($type, $def_value = NULL, $foreign_key = NULL, $critical = false, $match_key = NULL, $join_type = NULL, $required = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"paranoid");

		$this->_type = $type;
		$this->_value = (strtolower($def_value) != "!null") ? $def_value : NULL;
		$this->_key = $foreign_key;
		$this->_critical = $critical;
		$this->_owner = null;
		$this->_matchkey = $match_key;
		$this->_join_type = $join_type;
		$this->_required = ($required === true || strtolower($def_value) == "!null") ? true : false;
	}

	public static function varCopy($var)
	{
		$new_var = new Variable($var->_type, $var->_value, $var->_key, $var->_critical, $var->_matchkey, $var->_join_type, $var->_required);
		return $new_var;
	}

	/**
	 * Returns the correctly formated value of a Variable object. Value will be used inside a query. Formating is done 
	 * according to the type of the object
	 * @param $value Instance of Variable class
	 * @return Text or number to be used inside a query
	 */
	public function escape($value)
	{
		global $db_type;

		Debug::func_start(__METHOD__,func_get_args(),"paranoid");

		if (!strlen($value) && ($value!==false && $this->_type!="bool"))
			return 'NULL';

		$value = trim($value);
		if ($this->_type == "bool") {
			switch ($db_type) {
				case "mysql":
					if ($value === true || $value == 't' || $value == "1")
						return "1";
					else
						return "0";
					break;
				case "postgresql":
					if ($value === true || $value == 't' || $value == "1")
						return "'t'";
					else
						return "'f'";
					break;
			} 
		} elseif ( (substr($this->_type,0,3) == "int" && $this->_type!="interval") || substr($this->_type,-3) == "int" || substr($this->_type,0,7)=="tinyint" || $this->_type == "serial" || $this->_type == "bigserial" || substr($this->_type,0,6) == "bigint") {
			$value = str_replace(',','',$value);
			return (int)$value;
		} elseif (substr($this->_type,0,5) == "float") {
			return (float)$value;
		} elseif (($this->_type == "timestamp" || $this->_type == "date") && $value == "now()")
			return $value;
		elseif ($this->_type == "bit(1)") {
			if ($value == 1 || $value == "1" || $value == "bit(1)")
				return 1;
			else
				return 0;
		} elseif (substr($this->_type,0,4) == "bit(") 
			return (int)$value;
		return "'" . Database::escape($value) . "'";
	}

	public function isRequired()
	{
		Debug::func_start(__METHOD__,func_get_args(),"paranoid");

		return $this->_required;
	}
	
	public function numericType()
	{
		if ((substr($this->_type,0,3) == "int" && $this->_type!="interval") || substr($this->_type,-3) == "int" || substr($this->_type,0,5) == "float" || substr($this->_type,0,7)=="tinyint" || $this->_type == "serial" || $this->_type == "bigserial" || substr($this->_type,0,6) == "bigint")
			return true;
		if (substr($this->_type,0,4) == "bit(")
			return true;
		return false;
	}
}

// class that does the operations with the database 
class Database
{
	protected static $_connection = true;
	protected static $_object_model = "Model";
	public static $_in_transaction = false;

	// this library uses mostly postgresql types
	// if type is not in the below list make sure that given type of a variables mathches real type in database
	// Ex: in mysl if you use "bool" the column type will be tinyint(1) or int is int(11)
	private static $_translate_sql_types = array(
			"mysql" => array(
				"int2" => "smallint",
				"int4" => "int",
				"int8" => "bigint",
				"float4" => "float",
				"float8" => "double precision",
				"bool" => "tinyint",
				// mysql doesn't know bigserial
				// postgresql has both serial and bigserial. check documentation for type limits
				"serial" => "bigint unsigned NOT NULL auto_increment",
				"bigserial" => "bigint unsigned NOT NULL auto_increment",
				"interval" => "time"
			)
		);

	/**
	 * Disconnect current database connection
	 */
	public static function disconnect()
	{
		global $db_type;

		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if (!self::$_connection || self::$_connection===true)
			return;

		switch ($db_type) {
			case "mysql":
				mysqli_close(self::$_connection);
				break;
			case "postgresql":
				pg_close(self::$_connection);
				break;
		}
		self::$_connection = NULL;
		Model::reset_modified();
	}

	/**
	 * Make database connection
	 * @param $connection_index Numeric. Mark connection index in case backup connection is available
	 * Default value is ''. Valid values '',2,3 .. (1 is excluded)
	 * @return The connection to the database. If the connection is not possible, backup connection is tried if set, else return NULL if $exit_gracefully is set, otherwise page dies 
	 */
	public static function connect($connection_index="")
	{
		global $db_type, $cb_when_backup, $exit_gracefully;

		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if (self::$_connection && self::$_connection!==true)
			return self::$_connection;
                
                if ($connection_index!='' && (!is_int($connection_index) || $connection_index<2 )) {
                        Debug::trigger_report('critical', "Invalid connection index in Database::connect(): $connection_index");
                        return;
                }

		$db_data = array("db_host","db_user","db_database","db_passwd","db_type","db_port");
		for ($i=0; $i<count($db_data); $i++) {
			$db_setting = $db_data[$i].$connection_index;
			global ${$db_setting};
			if (!isset(${$db_setting}) && isset(${$db_data[$i]}) && $db_data[$i]!="db_host")
				${$db_setting} = ${$db_data[$i]};
			if ($db_data[$i]=="db_type" && $connection_index!="")
				$db_type = ${$db_setting};
			if (!isset(${$db_setting}) && $db_data[$i]!="db_port") {
				Debug::Output("config", _("Missing setting")." '$".$db_setting."' ");
				return;
			}
		}

		$next_index = ($connection_index=="") ? 2 : $connection_index+1;

		switch ($db_type) {
			case "mysql":
				if (!function_exists("mysqli_connect")) {
					Debug::Output("config", _("You don't have mysqli package for php installed."));
					die("You don't have mysqli package for php installed.");
				}
				if (self::$_connection === true || !self::$_connection) { 
					$port = (isset(${"db_port$connection_index"})) ? ${"db_port$connection_index"} : NULL;
					self::$_connection = mysqli_connect(${"db_host$connection_index"}, ${"db_user$connection_index"}, ${"db_passwd$connection_index"}, ${"db_database$connection_index"}, $port);
				} 
				break;
			case "postgresql":
				if (!function_exists("pg_connect")) {
					Debug::Output("config", _("You don't have php-pgsql package installed."));
					die("You don't have php-pgsql package installed.");
				}
				if (self::$_connection === true || !self::$_connection) {
					$port = (isset(${"db_port$connection_index"})) ? " port=".${"db_port$connection_index"} : '';
					self::$_connection = pg_connect("host='".${"db_host$connection_index"}."' $port dbname='".${"db_database$connection_index"}."' user='".${"db_user$connection_index"}."' password='".${"db_passwd$connection_index"}."'");
				}
				break;
			default:
				Debug::Output("config", _("Unsupported or unspecified database type")." db_type='$db_type'");
				die("Unsupported or unspecified database type: db_type='$db_type'");
				break;
		}
		if (!self::$_connection) {
			Debug::Output("config", _("Could not connect to the database")." $connection_index");
			global ${"db_host".$next_index};
			if ( isset(${"db_host".$next_index}) )
				self::$_connection = self::connect($next_index);
			if (!self::$_connection) {
				if (!isset($exit_gracefully) || !$exit_gracefully)
					die("Could not connect to the database\n");
				else
					return; 
			} elseif (isset($cb_when_backup) && is_callable($cb_when_backup))
				call_user_func_array($cb_when_backup, array($next_index));
		}

		return self::$_connection;
	}

	/**
	 * Switch existing connection and connect to another database 
	 * @param $connection_index Mark connection index in case backup connection is available
	 * Default value is ''. Valid values '',2,3 .. (1 is excluded)
	 * @param $db_connections Array with all database connections that will be used when switching is done
	 * @return The connection to new database. If the connection is not possible, backup connection is tried if set, else return NULL if $exit_gracefully is set, otherwise page dies 
	 */ 
	public static function switchDatabase($connection_index='')
	{
		global $db_identifier, $db_connections;

		// Database::disconnect();
		if (!isset($_SESSION["ansql_default_db_identifier"]) && $db_identifier) {
			$_SESSION["ansql_default_db_identifier"] = $db_identifier;
		}

		if ($connection_index != '') {
			global ${"db_identifier$connection_index"};
			$db_identifier = ${"db_identifier$connection_index"};
			if (!$db_identifier) {
				Debug::trigger_report("critical", "No indentifier for connection_index=$connection_index");
			}
			Debug::xdebug("switch_database", "db_identifier='$db_identifier', default_db_identifier='".$_SESSION['ansql_default_db_identifier']."'");
		} else {
			if (!$_SESSION["ansql_default_db_identifier"]) {
				Debug::trigger_report("critical", "No default identifier in switch_database(), $connection_index=$connection_index");
			}

			$db_identifier = $_SESSION["ansql_default_db_identifier"];
			unset($_SESSION["ansql_default_db_identifier"]);
			Debug::xdebug("switch_database", "db_identifier='$db_identifier'");
		}

		if (!is_array($db_connections)) {
			$db_connections = array();
			$db_connections[''] = self::$_connection;
		}

		if (!isset($db_connections[$connection_index])) {
			self::$_connection = NULL;
			self::$_in_transaction = false;
			$conn = Database::connect($connection_index);
			$db_connections[$connection_index] = $conn; 
			return $conn;
		}

		return self::$_connection = $db_connections[$connection_index];
	}

	/**
	 * Start transaction
	 */
	public static function transaction()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		if (self::$_in_transaction===true)
			return;
		self::$_in_transaction = true;
		return Database::query("BEGIN WORK");
	}

	/**
	 * Perform roolback on the current transaction
	 */
	public static function rollback()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		self::$_in_transaction = false;
		return Database::query("ROLLBACK");
	}

	/**
	 * Commit current tranction
	 */
	public static function commit()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		self::$_in_transaction = false;
		return Database::query("COMMIT");
	}

	/**
	 * Perform query.If query fails, unless query is a ROLLBACK, it is supposed that the database structure changed. Try to 
	 * modify database structure, then perform query again using the  queryRaw() method
	 * @param $query Text representing the query to perform
	 * @param $retrieve_last_id false or Array (Name of id field, table name) -- just for INSERT queries
	 * @return Result received after the performing of the query 
	 */
	public static function query($query, $retrieve_last_id=false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		if (!self::connect())
			return false;

		Debug::xdebug("query", $query);
		if(isset($_SESSION["debug_all"]))
			Debug::Output("query", $query);

//		if (!self::is_single_query($query))
//			return false;

		$res = self::db_query($query);
		$res = self::query_result($query, $res, $retrieve_last_id);
		if ($res!==false && $res!==NULL)
			return $res;
		elseif ($query != "ROLLBACK")
		{
			$model = self::$_object_model;
			if(!call_user_func(array($model,"modified")))
			{
				call_user_func(array($model,"updateAll"));
				$res = self::queryRaw($query, $retrieve_last_id);
				if ($res===false || $res===NULL) {
					$mess = $query;
					$err = self::get_last_db_error();
					if ($err) {
						$mess .= ". Error: ".$err;
						Debug::trigger_report('critical', "The query: ". $query . " failed with error: ". $err);
					}
					Debug::Output("query failed", $mess);
				}
				return $res;
			}
			else
				return $res;
		}
	}

	public static function setObjModel($model)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		self::$_object_model = $model;
	}

	/**
	 * Make sure queries separated by ; won't we run
	 * @param $query String that will be verified
	 * @return Bool - true if single query, false otherwise
	 */
	/*public static function is_single_query($query)
	{
	 	$pattern = "/'[^']*'/";
		$replacement = "";
		// all that is in between '' is ok
		$mod_query = preg_replace($pattern,$replacement,$query);
		// after striping all '..' if we still have ; then query is composed of multiple queries
		if (strpos($mod_query,";"))
			return false;   
		return true;
	}*/

	public static function get_last_db_error()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		global $db_type;
		switch ($db_type) {
			case "mysql":
				return mysqli_error(self::$_connection);
			case "postgresql":
				return pg_last_error(self::$_connection);
		}
		return false;
	}

	/**
	 * Make query taking into account the database type
	 * @param $query String - query to pe performed
	 * @return Result of function that performs query for set database type
	 */
	public static function db_query($query)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $db_type;
		switch ($db_type) {
			case "mysql":
				$res = mysqli_query(self::$_connection,$query);
				$warn_count = mysqli_warning_count(self::$_connection);
				if ($warn_count) {
					$warnings = array();
					$warning = mysqli_get_warnings(self::$_connection);
					do {
						$warnings[] = $warning->message;
					} while ($warning->next()); 
					$warning = "$warn_count warning(s) when running query: '$query'. ".implode(", ",$warnings);
					if (substr(strtoupper($query),1,4) !== "DROP") {
						Debug::trigger_report('critical', $warning);
					}
				}
				return $res;
				break;
			case "postgresql":
				return pg_query(self::$_connection,$query);
		}
		return false;
	}

	/**
	 * Build array describing query result
	 * @param $query String - query that was performed
	 * @param $res Returned value by db_query
	 * @param $retrieve_last_id false or Array (Name of id field, table name) -- just for INSERT queries
	 * @return false - if $res is false <=> query failed
	 *         for INSERT/UPDATE/DELETE array(bool, affected_rows) or array(bool, affected_rows, last_id) for INSERT 
	 *            with $retrieve_last_id specified
	 *         otherwise Array(0=>array(col1_name=>col1_value, col2_name=>col2_value ..), 1=>...)
	 */
	private static function query_result($query, $res, $retrieve_last_id)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $db_type, $func_query_result;

		if (!isset($func_query_result))
			$func_query_result = "htmlentities";

		if (!$res)
			return false;
		$query = strtolower($query);
		$query = trim($query);  // trim white spaces and \n and a few others
		if (substr($query,0,6) == "insert" && $retrieve_last_id) {
			switch ($db_type) {
				case "mysql":
					$last_id = mysqli_insert_id(self::$_connection);
					break;
				case "postgresql":
					$oid = pg_last_oid($res);
					$query2 = "SELECT ".$retrieve_last_id[0]." FROM ".$retrieve_last_id[1]." WHERE oid=$oid";
					$res2 = self::queryRaw($query2);
					if (!$res2)
						return false;
					$last_id = pg_fetch_result($res2,0,0);
					break;
			}
		}
		if (substr($query,0,6) == "delete" || substr($query,0,6) == 
		"update" || substr($query,0,6) == "insert") {
			// return type = array(bool, int2)
			switch ($db_type) {
				case "mysql":
					$affected = mysqli_affected_rows(self::$_connection);
					break;
				case "postgresql":
					$affected = pg_affected_rows($res);
					break;
			}
			$status = ($res) ? true : false;
			if (substr($query,0,6) == "insert" && $retrieve_last_id) {
				return array($status, $affected, $last_id);
			}else
				return array($status, $affected);
		} elseif (substr($query,0,6) == "select" || substr($query,0,4)== "show") {
			if (!$res)
				return false;
			$array = array();
			$i = 0;
			switch ($db_type) {
				case "mysql":
					if (mysqli_num_rows($res))
					while ($row = mysqli_fetch_assoc($res))
					{
						$array[$i] = array();
						foreach ($row as $var_name=>$value)
							$array[$i][$var_name] = ($func_query_result && is_callable($func_query_result)) ? call_user_func($func_query_result, self::unescape($value)) : self::unescape($value);
						$i++;
					}
					break;
				case "postgresql":
					for($i=0; $i<pg_num_rows($res); $i++)
					{
						$array[$i] = array();
						for($j=0; $j<pg_num_fields($res); $j++)
							$array[$i][pg_field_name($res,$j)] = ($func_query_result && is_callable($func_query_result)) ? call_user_func($func_query_result, self::unescape(pg_fetch_result($res,$i,$j))) : self::unescape(pg_fetch_result($res,$i,$j));
					}
					break;
			}
			return $array;
		}
		return true;
	}

	/**
	 * Perform query without verifying if it failed or not
	 * @param $query Text representing the query to perform
	 * @param $retrieve_last_id false or Array (Name of id field, table name) -- just for INSERT queries
	 * @return Result received after the performinng of the query 
	 */
	public static function queryRaw($query, $retrieve_last_id=false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if (!self::connect())
			return false;
		Debug::xdebug("queryRaw", $query);
		if(isset($_SESSION["debug_all"]))
			Debug::Output("queryRaw", $query);

		//if (!self::is_single_query($query))
		//	return false;
		$res = self::db_query($query);
		$res = self::query_result($query, $res, $retrieve_last_id);
		return $res;
	}

	/**
	 * Create corresponding sql table for this object
	 * @param $table Name of the table
	 * @param $vars Array of variables for this object
	 * @return Result returned by performing the query to create this $table  
	 */
	public static function createTable($table,$vars)
	{
		Debug::func_start(__METHOD__,func_get_args(),"dbstruct");

		global $db_type, $enforce_basic_constrains;
		if (!self::connect())
			return false;
		$query = "";
		$nr_serial_fields = 0;

		foreach ($vars as $name => $var)
		{
			if(is_numeric($name))
			{
				Debug::trigger_report('critical',"You are trying to add a column named $name, numeric names of columns are not allowed");

				//  do not allow numeric names of columns
				exit(_("You are trying to add a column named ").$name.", "._("numeric names of columns are not allowed").".");
			}
			$type = $var->_type;
			if (isset(self::$_translate_sql_types[$db_type][$type]))
				$type = self::$_translate_sql_types[$db_type][$type];
			$type = strtolower($type);
			switch ($type)
			{
				case "timestamp":
					$type = "$type NULL DEFAULT NULL";
					break;
				case "serial":
					if ($var->_key != "")
						$type = "int4";
					break;
				case "bigserial":
					if ($var->_key != "")
						$type = "int8";
					break;
				case "bigint unsigned not null auto_increment":
					if ($var->_key != "")
						$type = "bigint";
					break;
			}
			if ($query != "")
				$query.= ",";

			if ($type=="serial" || $type=="bigserial" || $type=="bigint unsigned not null auto_increment") {
				$nr_serial_fields++;
				$query.= esc($name)." $type"; 
				if ($type == "bigint unsigned not null auto_increment")
					$query.= ", primary key ($name)";
			} else {
				if (substr($table,0,6)=="_temp_" && ($name == 'inserted' || $name == 'deleted')) {
					$old_enforce_basic_constrains = $enforce_basic_constrains;
					$enforce_basic_constrains = true;
				}
				$query.= esc($name)." $type";
				if (!$enforce_basic_constrains)
					continue;
				if ($var->_required)
					$query.= " NOT NULL";
				if ((strlen($var->_value) || $var->_value===true || $var->_value===false) && !in_array($var->_type,array("timestamp","date","datetime"))) {
					$value = $var->escape($var->_value);
					$query.= " DEFAULT ".$value;
				}
				if (substr($table,0,6)=="_temp_" && ($name == 'inserted' || $name == 'deleted'))
					$enforce_basic_constrains = $old_enforce_basic_constrains;

			}
		}

		// do not allow creation of tables with more than one serial field
		// protection is inforced here because i rely on the fact that there is just one numeric id or none
		if($nr_serial_fields > 1) {
			Debug::trigger_report('critical', "Table $table has $nr_serial_fields serial or bigserial fields. You can use 1 serial or bigserial field per table or none. ");

			exit(_("Error").": "._("Table ").$table." "._("has")." ".$nr_serial_fields." ".("serial or bigserial fields. You can use 1 serial or bigserial field per table or none."));
		}

		$query = "CREATE TABLE $table ($query)";
		if ($db_type == "postgresql")
			$query.= " WITH OIDS";
		elseif ($db_type == "mysql") {
			if (substr($table,0,6)!="_temp_")
				$obj = Model::getObject($table);
			else
				$obj = TemporaryModel::getObject(substr($table,6));
			$engine = call_user_func(array($obj,"getDbEngine"));

			if ($engine)
				$query.= "ENGINE $engine";
		}

		$res = self::queryRaw($query) !== false;
		if (!$res)
			Debug::Output(_("Could not create table")." '$table'. "._("Query failed").": $query". " .Error: ".Database::get_last_db_error());

		return $res;
	}

	/**
	 * Update the structure of the table
	 * @param $table Name of the table
	 * @param $vars Array of variables for this object
	 * @return Bool value showing if the update succeeded or not
	 */
	public static function updateTable($table,$vars)
	{
		Debug::func_start(__METHOD__,func_get_args(),"dbstruct");

		global $db_type, $db_database, $critical_col_diff, $error_sql_update, $enforce_basic_constrains;

		if (!self::connect())
			return false;

		switch ($db_type) {
			case "mysql":
				$query = "SHOW TABLES FROM ".$db_database." LIKE '".$table."'";
				$res = self::queryRaw($query);
				if (!$res)
				{
					Debug::Output('dbstruct', _("Table")." '$table' "._("does not exist, we'll create it"));
					return self::createTable($table,$vars);
				}
				$cols = array();
				$query = "SHOW COLUMNS FROM $table";
				$res = self::queryRaw($query);
				for ($i=0; $i<count($res); $i++)
					$cols[$res[$i]["Field"]] = array("type"=>$res[$i]["Type"], "not_null"=>(($res[$i]["Null"]=="NO") ? true : false), "default"=>$res[$i]['Default'], "extra"=>$res[$i]['Extra']);
				break;
			case "postgresql":
				$query = "SELECT pg_attribute.attname as name, pg_type.typname as type, (CASE WHEN atthasdef IS TRUE THEN pg_get_expr(adbin,'pg_catalog.pg_class'::regclass) ELSE NULL END) as default, pg_attribute.attnotnull as not_null FROM pg_class, pg_type, pg_attribute LEFT OUTER JOIN pg_attrdef ON pg_attribute.attnum=pg_attrdef.adnum WHERE pg_class.oid=pg_attribute.attrelid and pg_class.relname='$table' and pg_class.relkind='r' and pg_type.oid=pg_attribute.atttypid";
				$res = self::db_query($query);
				if (!$res || !pg_num_rows($res))
				{
					Debug::Output('dbstruct', _("Table")." '$table' "._("does not exist, we'll create it"));
					return self::createTable($table,$vars);
				}
				for ($i=0; $i<pg_num_rows($res); $i++) {
					$row = pg_fetch_array($res,$i);
					$default = $row["default"];
					$default = explode("'::",$default);
					if (count($default)>1) {
						$default = $default[0];
						$default = substr($default,1);
					} else
						$default = $default[0];
					$cols[$row["name"]] = array("type"=>$row["type"], "not_null"=>(($row["not_null"]=="t") ? true : false), "default"=>$default, "extra"=>"");
				}
				break;
		}

		foreach ($vars as $name => $var)
		{
			$type = $var->_type;
			if (isset(self::$_translate_sql_types[$db_type][$type]))
				$type = self::$_translate_sql_types[$db_type][$type];
			$type = strtolower($type);

			if (!isset($cols[$name]))
			{
				if ($type == "timestamp")
					$type = "$type NULL DEFAULT NULL";
				if ($type == "serial")
					$type = "int4";
				if ($type == "bigserial")
					$type = "int8";
				if ($type == "bigint unsigned not null auto_increment") {
					if ($db_type!="mysql" || strlen($var->_key)) {
						$type = "bigint";
					} else {
						$type .= ", ADD primary key ($name)";
					}
				}

				Debug::Output('dbstruct', _("No field")." '$name' "._("in table")." '$table'".(", we'll create it"));

				$query = "ALTER TABLE ".esc($table)." ADD COLUMN ".esc($name)." $type";

				if ($enforce_basic_constrains) {
					if ($var->_required)
						$query.= " NOT NULL";
					if ((strlen($var->_value) || $var->_value===true || $var->_value===false) && !in_array($var->_type,array("timestamp","date","datetime"))) {
						$value = $var->escape($var->_value);
						$query.= " DEFAULT ".$value;
					}
				}
				if (!self::queryRaw($query)) {
					Debug::Output('critical', _("Could not update table")." '$table'. ".("Query failed").": '$query'". " .Error: ".Database::get_last_db_error());
					$error_sql_update = true;
					continue;
				}
				if ($var->_value !== null)
				{
					$val = $var->escape($var->_value);
					$query = "UPDATE ".esc($table)." SET ".esc($name)."=$val";
					if (!self::queryRaw($query)) {
						Debug::Output('critical', _("Could not update table fields to default value for")." '$table'. "._("Query failed").": '$query'". " .Error: ".Database::get_last_db_error());
						$error_sql_update = true;
					}
				}
			}
			else
			{
				$orig_type = $type;
				// we need to adjust what we expect for some types
				switch ($type)
				{
					case "serial":
						$type = "int4";
						break;
					case "bigserial":
						$type = "int8";
						break;
				}


				$dbtype = $cols[$name]["type"];
				preg_match('#\((.*?)\)#',$dbtype,$dbtype_brackets);
				if (isset($dbtype_brackets[0])) {
					$accepted_val = array("int", "int unsigned", "bigint", "bigint unsigned", "tinyint", "smallint");
					$stripped_val = str_replace($dbtype_brackets[0],"",$dbtype);
					if (in_array($stripped_val,$accepted_val))
						$dbtype = $stripped_val;
				}

				$full_dbtype = $dbtype." DEFAULT ".$cols[$name]["default"]." ".$cols[$name]["extra"];

				if ($enforce_basic_constrains && $critical_col_diff) {
					if ($dbtype=="bool")
						$cols[$name]["default"] = ($cols[$name]["default"]=="true" || $cols[$name]["default"]=="1") ? true : false;
					$db_default = $var->escape($cols[$name]["default"]);
					$db_not_null = $cols[$name]["not_null"];
					$class_default = $var->escape($var->_value);

					if ($dbtype == $type && $db_default==$class_default && $db_not_null===$var->_required)
						continue;
					elseif (($dbtype == "bigint unsigned" || $dbtype == "bigint")  && $type == "bigint unsigned not null auto_increment")
						continue;
					elseif ($dbtype == "timestamp" && (strtolower($full_dbtype) == $type || $type == "timestamp default current_timestamp on update current_timestamp"))
						continue;
					elseif ($dbtype!=$type) {
						if ($db_type=='postgresql')
							$query = "ALTER TABLE ".esc($table)." ALTER COLUMN ".esc($name)." TYPE $type";
						else
							$query = "ALTER TABLE ".esc($table)." CHANGE ".esc($name)." ".esc($name)." $type";

						if (!self::queryRaw($query)) {
							Debug::Output('critical', _("Could not change table field $name from $dbtype to $type for")." '$table'. "._("Query failed").": '$query'". " .Error: ".Database::get_last_db_error());
							$error_sql_update = true;
						}
						continue;
					}
				
					elseif ($orig_type=="serial" || $orig_type=="bigserial")
						continue;
					$db_str_not_null = ($db_not_null) ? " NOT NULL" : "";
					$db_str_default = (strlen($db_default)) ? " DEFAULT ".$db_default : "";
					$str_not_null = ($var->_required) ? " NOT NULL" : "";
					$str_default = (strlen($class_default)) ? " DEFAULT ".$class_default : "";

					Debug::Output('critical', _("Field")." '".$name."' "._("in table")." "."'$table' "._("is of type")." $dbtype$db_str_not_null$db_str_default ".strtoupper(_("but should be"))." $type$str_not_null$str_default");

				} else {
					// Maintain compatibility with previous implementation
					// Don't force user to set DEFAULT VALUES and NOT NULL for columns when their projects didn't need to
					if ($dbtype == $type)
						continue;
					elseif (($dbtype == "bigint unsigned" || $dbtype == "bigint")  && $type == "bigint unsigned not null auto_increment")
						continue;
					elseif ($dbtype == "timestamp" && (strtolower($full_dbtype) == $type || $type == "timestamp default current_timestamp on update current_timestamp"))
                                                continue;
					elseif ($dbtype!=$type) {

						if ($db_type=='postgresql')
							$query = "ALTER TABLE ".esc($table)." ALTER COLUMN ".esc($name)." TYPE $type";
						else
							$query = "ALTER TABLE ".esc($table)." CHANGE ".esc($name)." ".esc($name)." $type";

						if (!self::queryRaw($query)) {
							Debug::Output('critical', _("Could not change table field $name from $dbtype to $type for")." '$table'. "._("Query failed").": '$query'". " .Error: ".Database::get_last_db_error());
							$error_sql_update = true;
						}
						continue;
					}


					Debug::Output('critical', _("Field")." '".$name."' "._("in table")." "."'$table' "._("is of type")." '$dbtype' "._("but should be")." '$type'");
				}

				$error_sql_update = true;
			}
		}
		return true;
	}

	/**
	 * Creates one or more btree indexs on the specified table
	 * @param $table Name of the table
	 * @param $columns Array with the columns for defining each index
	 * Ex: array("time") will create an index with the name "$table-time" on column "time"
	 * array("index-time"=>"time", "comb-time-user_id"=>"time, user_id") creates an index called "index-time" on column "time"
	 * and an index called "comb-time-user_id" using both "time" and "user_id" columns
	 * @return Bool value showing if the creating succeeded or not 
	 */
	public static function createIndex($table, $columns)
	{
		Debug::func_start(__METHOD__,func_get_args(),"dbstruct");

		global $db_type;
		$no_error = true;
		$make_vacuum = false;
		foreach($columns as $index_name=>$index_columns)
		{
			if ($index_columns == '' || !$index_columns)
				continue;
			if(is_numeric($index_name))
				$index_name = "$table-index";
			switch ($db_type) {
				case "mysql":
					$query_check = "SELECT COUNT(*) AS indexisthere FROM INFORMATION_SCHEMA.STATISTICS
						WHERE table_schema=DATABASE() AND table_name='".$table."' AND index_name='".$index_name."';";
					$query = "CREATE INDEX ".esc($index_name)." USING btree ON ".esc($table)."($index_columns)";
					break;
				case "postgresql":
					$query_check = "SELECT COUNT(*) AS indexisthere FROM pg_indexes
						WHERE tablename='".$table."' AND indexname='".$index_name."';";
					$query = "CREATE INDEX ".esc($index_name)." ON ".esc($table)." USING btree ($index_columns)";
					break;
			}

			// check if index is already created
			$res_check = self::queryRaw($query_check);
			if (!$res_check) {
				$no_error = false;
				continue;
			}

			// create index if it didn't exist and optimize table afterwards
			if (isset($res_check[0]["indexisthere"]) && $res_check[0]["indexisthere"] == 0) {
				$res = self::queryRaw($query);
				if (!$res)
				{
					$no_error = false;
					continue;
				}
				$make_vacuum = true;
			}
		}
		if ($make_vacuum)
			self::vacuum($table);
		return $no_error;
	}

	public static function vacuum($table)
	{
		Debug::func_start(__METHOD__,func_get_args(),"dbstruct");

		global $db_type;
		switch ($db_type) {
			case "mysql":
				$query = "OPTIMIZE TABLE $table";
				break;
			case "postgresql":
				$query = "VACUUM ANALYZE $table";
				break;
			default:
				$query = null;
		}
		if ($query)
			self::db_query($query);
	}

	public static function escape($value)
	{
		Debug::func_start(__METHOD__,func_get_args(),"paranoid");

		global $db_type;
		switch ($db_type) {
			case "mysql":
				return mysqli_real_escape_string(self::connect(), $value);
			case "postgresql":
				return pg_escape_string(self::connect(), $value);
		}
		return $value;
	}

	public static function escape_string()
	{
		Debug::func_start(__METHOD__,func_get_args(),"paranoid");

		global $db_type;

		if ($db_type == "mysql")
			return "`";
		else
			return "\"";
	}

	public static function unescape($value)
	{
		Debug::func_start(__METHOD__,func_get_args(),"paranoid");

		return $value;
	}

	public static function likeOperator()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $db_type;
		// we want case insensitive like. Use ILIKE for postgres, LIKE for mysql
		switch ($db_type) {
			case "mysql":
				return "LIKE";
			case "postgresql":
				return "ILIKE";
		}
		return "LIKE";
	}

	public static function boolCondition($value)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $db_type;
		switch ($db_type) {
			case "mysql":
				if ($value)
					return "=1";
				else
					return "=0";
				break;
			case "postgresql":
			case "default":
				if ($value)
					return " IS TRUE";
				else
					return " IS NOT TRUE";
				break;
		}
	}
	
	public static function boolValue($value)
	{
		Debug::func_start(__METHOD__,func_get_args(),"paranoid");
		if ($value==="t" || $value==="1" || $value===1 || $value===true)
			return true;
		return false;
	}
}

function esc($col)
{
	Debug::func_start(__FUNCTION__,func_get_args(),"paranoid");

	return Database::escape_string().$col.Database::escape_string();
}

// general model for defining an object that will be mapped to an sql table 
class Model
{
	protected $_model;
	//if $_invalid is set to true, this object can't be set as a key for update or delete clauses 
	protected $_invalid;
	//whether the params for a certain object were set or not. It is set to true in setParams dar is called from setObj
	protected $_set;
	//whether a select or extendedSelect was performed on the object 
	protected $_retrieved;

	//whether to check or not which fields have been modified before making the update
	protected $_check_col_diff;
	protected $_modified_col = array();

	protected static $_models = false;
	protected static $_modified = false;
	// array with name of objects that are performers when using the ActionLog class
	protected static $_performers = array();

	/**
	 * Base class constructor, populates the list of variables of this object
	 */
	function __construct()
	{
		global $check_col_diff;

		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$this->_invalid = false;
		$this->_model = self::getVariables(get_class($this));
		$this->_set = false;
		$this->_retrieved = false;
		if (!is_array($this->_model))
			return;
		foreach ($this->_model as $name => $var) {
			if($name == "__sql_relation") {
				Debug::trigger_report('critical', "Invalid column name: __sql_relation.");
				exit(_("Invalid column name").": __sql_relation.");
			}
			$this->$name = $var->_value;
		}

		$this->_check_col_diff = isset($check_col_diff) ? $check_col_diff : true;
	}

	/**
	 * Creates an array of objects by selecting rows that match the conditions.
	 * @param $class Name of the class of the returned object
	 * @param $conditions Array of conditions of type $key=>$value
	 * @param $order Array used for specifing order options OR just Text with options
	 * @param $limit Number used for setting the LIMIT clause of the query
	 * @param $offset Number used for seeting the OFFSET clause of the query
	 * @param $given_where Text with the conditions correctly formatted
	 * @param $inner_query Array used for defining an inner query inside the current query.
	 * See method @ref makeInnerQuery() for more detailed explanation
	 * @return array of objects of type $class.
	 */
	public static function selection($class, $conditions=array(), $order= NULL, $limit=NULL, $offset=0, $given_where = NULL, $inner_query=NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables($class);
		if (!$vars) 
		{
//			print '<font style="weight:bold;">You haven\'t included file for class '.$class.' try looking in ' . $class . 's.php</font>';
			Debug::trigger_report('critical','You haven\'t included file for class '.$class.' try looking in ' . $class . 's.php');
			return null;
		}
		$table = self::getClassTableName($class);
		$obj = new $class;
		$where = ($given_where) ? $given_where : $obj->makeWhereClause($conditions,true);
		if ($inner_query)
			$where = $obj->makeInnerQuery($inner_query, $table, $where);
		$add_to_select = "";
		foreach ($vars as $var_name=>$var) {
			if (substr($var->_type,0,4) == "bit(") {
				$add_to_select .= ", bin(".esc($var_name).") as ".esc($var_name);
			}
		}

		$query = self::buildSelect("*$add_to_select", esc($table), $where, $order, $limit, $offset);
		$res = Database::query($query);
		if($res === false)
		{ 
			self::warning(_("Could not select")." "._($class)." "._("from database in selection").".");
			return null;
		}
		$object = new $class;
		return $obj->buildArrayOfObjects($res);
	}

	/**
	 * Perform query with fields given as a string.
	 * Allows using sql functions on the results being returned
	 * @param $fields String containing the items to select. 
	 * Example: count(user_id), max(price), (case when $condition then value1 else value2 end) as field_name
	 * @param $conditions Array of conditions
	 * @param $group_by String for creating GROUP BY clause
	 * @param $order Array or String for creating ORDER clause
	 * @param $inner_query Array for using an inner query inside the WHERE clause
	 * @param $extend_with Array of $key=>$value pair, $key is the name of a column, $value is the name of the table
	 * Example: class of $this is Post and $extended=array("category_id"=>"categories") 
	 * becomes AND post.category_id=categories.category_id 
	 * @param $given_where String representing the WHERE clause
	 * @param $given_from String representing the FROM clause
	 * @return Value for single row, sigle column in sql result/ Array of results : $result[row][field_name] 
	 * if more rows or columns where returned 
	 */
	public function fieldSelect($fields, $conditions=array(), $group_by=NULL, $order=NULL, $inner_query=NULL, $extend_with=NULL, $given_where=NULL, $given_from=NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$table = $this->getTableName();
		$class = get_class($this);

		$where = ($given_where) ? $given_where : $this->makeWhereClause($conditions, true);
		$where = $this->makeInnerQuery($inner_query, $table, $where);

		$from_clause = ($given_from) ? $given_from : $table;
		$clause = '';
		$tables = str_replace(' ', '',$from_clause);
		$tables = explode(',',$tables);

		if (is_array($extend_with) && count($extend_with))
		{
			foreach($extend_with as $column_name=>$table_name)
			{
				if (!in_array($table_name, $tables))
				{
					$from_clause .= ', '.$table_name;
					array_push($tables, $table_name);
				}
				if ($clause != '')
					$clause .= ' AND ';
				$clause .= esc($table).".".esc($column_name)."=".esc($table_name).".".esc($column_name);
			}
		}
		if ($where != '' && $clause != '')
			$where .= ' AND '.$clause;
		elseif($clause != '' && $where == '')
			$where = "WHERE $clause";

		$query = self::buildSelect($fields, $from_clause, $where, $order, NULL, NULL, $group_by);
		$res = Database::query($query);
		if ($res === false) { 
			self::warning(_("Could not select")." "._($class)." "._("from database"));
			return null;
		}
		if (count($res) == 1 && count($res[0]) == 1) {
			foreach ($res[0] as $key=>$value)
				return $value;
		}
		return $res; 
	}

	/**
	 * Fill $this object from it's corresponsing row in the database
	 * @param $condition 
	 * $condition should be NULL if we wish to use the value of the numeric id for the corresponding table
	 * $condition should be a STRING representing the name of a column that could act as primary key
	 * In both the above situations $this->{$condition} must be set before calling this method
	 * $condition should be an ARRAY formed by pairs of $key=>$value for more complex conditions 
	 * This is the $conditions array that will be passed to the method @ref makeWhereClause()
	 */
	public function select($condition = NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables(get_class($this));
		$add_select = "";
		foreach ($vars as $var_name=>$var) {
			if (substr($var->_type,0,4)=="bit(")
				$add_select .= ",bin(".esc($var_name).") as ".esc($var_name);
		}
		$table = $this->getTableName();
		$class = get_class($this);
		if (!is_array($condition))
		{
			if(!$condition)
			{
				if(!($id = $this->getIdName()))
				{
					$this->invalidate();
					return;
				}
				$condition = $id;
			}
			$var = $this->variable($condition);
			$value_condition = ($this->{$condition}) ? $var->escape($this->{$condition}) : NULL;
			if (!isset($this->{$condition}) || !$value_condition)
			{
				$this->invalidate();
				return;
			}
			$where_clause = " WHERE ".esc($condition)."=".$value_condition;
		} else
			$where_clause = $this->makeWhereClause($condition, true);

		$query = self::buildSelect("*$add_select", esc($table), $where_clause);
		$res = Database::query($query);
		if($res===false)
		{ 
			self::warning(_("Could not select")." $class "._("from database").". Query: $query");
			$this->invalidate();
			return ;
		} elseif(!is_array($res) || !count($res)) {
			$this->invalidate();
			return;
		} elseif(count($res)>1) {
			$this->invalidate();
			Debug::trigger_report('critical',_("More results for a single id."));
			return;
		} else
			$this->populateObject($res);
	}

	/**
	 * Populates $this object, that could have been previously extended, with the fields returned by the generated query or Returns an array of objects 
	 * @param $conditions Array of conditions or pairs field_name=>value
	 * @param $order String representing the order or Array of pairs field_name=>"ASC"/"DESC"
	 * @param $limit Integer representing the maximum number or objects to be returned
	 * @param $offset Integer representing the offset
	 * @param $inner_query Array used for defining an inner query
	 * @param $given_where	WHERE clause already formated
	 * @return NULL if a single row was returned and method was called without any of the above params, $this object is populated with the results of the query / Array of objects having the same variables as $this object
	 */
	public function extendedSelect($conditions = array(), $order = NULL, $limit = NULL, $offset = NULL, $inner_query = array(), $given_where = NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		// the array of variables from the object
		$vars = $this->_model;
		$table = $this->getTableName();
		$id = $this->getIdName();
		// array holding the columns that were added to the query
		$added_fields = array();
		// array of the tables that will appear in the FROM clause 
		$from_tables = array();
		// 
		$from_tables[$table] = true;

		$from_clause = '';
		$columns = '';


		$ref_tables = array();
		// pass once over object vars to just find how many times each table is referenced
		foreach($vars as $var_name => $var) 
		{
			// name of table $var_name is a foreign key to
			$foreign_key_to = $var->_key;
			if (!isset($ref_tables[$foreign_key_to]))
				$ref_tables[$foreign_key_to] = 1;
			else
				$ref_tables[$foreign_key_to]++;
		}

		foreach($vars as $var_name => $var)
		{
			// name of table $var_name is a foreign key to
			$foreign_key_to = $var->_key;

			if (!isset($ref_tables[$foreign_key_to]) || $ref_tables[$foreign_key_to]==1)
				$foreign_key_to = NULL;

			// name of variable in table $foreign_key_to $var_name references 
			$references_var = $var->_matchkey;
			$join_type = strtoupper($var->_join_type);
			$var_type = $var->_type;
			if(!$join_type)
				$join_type = "LEFT";
			if($join_type != "INNER")
				$join_type .= " OUTER";
			$added_fields[$var_name] = true;

			if($foreign_key_to)
			{
				if($from_clause == '')
					$from_clause = " ".esc($table)." ";
				elseif(!isset($from_tables[$foreign_key_to]))
					$from_clause = " ($from_clause) ";
			}

			// if this is not a foreign key to another table and is a valid variable inside the corresponding object
			if(!$foreign_key_to && self::inTable($var_name,$table)) {
				if ($columns != '')
					$columns .= ', ';
				$col = esc($table).".".esc($var_name);
				$columns .= " ";
				if (substr($var_type,0,4) != "bit(")
					$columns .= $col;
				else
					$columns .= "bin($col) as ".esc($var_name);
			// if this is a foreign key to another table, but does not define a recursive relation inside the $table
			} elseif($foreign_key_to && $foreign_key_to != $table) {
				if ($columns != '')
					$columns .= ', ';
				$columns .= " ";
				if($references_var) {
					// when extending one might need to use another name than the one used in the actual table
					// prime reason: common field names as status or date are found in both tables
					if (substr($var_type,0,4) != "bit(")
						$columns .= esc($foreign_key_to).".".esc($references_var)." as ".esc($var_name)." ";
					else
						$columns .= "bin(".esc($foreign_key_to).".".esc($references_var).") as ".esc($var_name)." ";
				} else {
					$col = esc($foreign_key_to).".".esc($var_name);
					if (substr($var_type,0,4) != "bit(")
						$columns .= $col;
					else
						$columns .= "bin($col) as ".esc($var_name);
				}
				// this table was already added in the FROM clause
				if(isset($from_tables[$foreign_key_to])) {
					if($join_type != "LEFT OUTER") {
						// must overrite old join type with new join type
						$from_clause = str_replace("LEFT OUTER JOIN ".esc($foreign_key_to), "$join_type JOIN ".esc($foreign_key_to), $from_clause);
					}
					continue;
				}
				// if $var_name was not added by extending the object, but it's inside the $class of the object 

				$from_tables[$foreign_key_to] = true;
				if(self::inTable($var_name,$table))
				{
					// must add table inside the FROM clause and build the join
					$key = ($references_var) ? $references_var : $var_name;
					$from_clause .= "$join_type JOIN ".esc($foreign_key_to)." ON ".esc($table).".".esc($var_name)."=".esc($foreign_key_to).".".esc($key);
					continue;
				}
				// keeping in mind that the $var_name fields that were added using the extend method are always at the end 
				// in the $vars array: 
				// if we reach here then $var_name is a field in the table represented by foreign_key_to and that table has a foreign key to the table corresponding to this object, and not the other way around
				// Example: i have an instance of the group class, i extend that object sending 
				// array("user"=>"users") as param to extend() method, but when query is made i need to look in the
				// user object to find the key that links the two tables 

				$obj = self::getObject($foreign_key_to);
				$obj_vars = $obj->_model;

				$continue = false;
				foreach($obj_vars as $varname => $obj_var)
				{
					if($obj_var->_key != $table)
						continue;
					$referenced = ($obj_var->_matchkey) ? $obj_var->_matchkey : $varname;
					$from_clause .= "$join_type JOIN ".esc($foreign_key_to)." ON ".esc($table).".".esc($referenced)."=".esc($foreign_key_to).".".esc($varname);
					//found the condition for join=> break from this for and the continue in the loop to pass to the next var 
					$continue = true;
					break;
				}
				if($continue)
					continue;
				// if i get here then the object was wrongly extended OR user wanted to get a cartesion product of the two tables: 
				// $table does not have a foreign key to $foreign_key_to table
				// $foreign_key_to table oes not have a foreign key to $table
				Debug::trigger_report('critical', _("No rule for extending table")." '$table' "._("with field")." '$var_name' "._("from table")." '$foreign_key_to'. "._("Generating cartesian product."));
				$from_clause .= ", ".esc($foreign_key_to)." ";
			} elseif($foreign_key_to && $foreign_key_to == $table) {
				// this defines a recursive relation inside the same table, just 1 level
				if ($columns != '')
					$columns .= ', ';
				if(self::inTable($var_name,$table)) {
					$col = "$table".'1'.".".esc($references_var);
					$columns .= " ";
					if (substr($var_type,0,4)!="bit(")
						$columns .= $col;
					else
						$columns .= "bin($col) as ".esc($var_name);
					$columns .= " as ".esc($var_name);
					$from_clause .= "$join_type JOIN ".esc($foreign_key_to)." as $foreign_key_to" . "1" . " ON ".esc($table).".".esc($var_name)."=$foreign_key_to"."1".".".esc($references_var);
				} else {
					// 1 level recursive relation
					$col = "$table".'1'.".".esc($references_var);
					$columns .= " ";
					if (substr($var_type,0,4)!="bit(")
						$columns .= $col;
					else
						$columns .= "bin($col)";
					$columns .= " as ".esc($var_name);
				}
			}
		}
		if ($from_clause == '')
			$from_clause = $table;

		if(!$given_where) 
		{
			$where = $this->makeWhereClause($conditions);
			$where = $this->makeInnerQuery($inner_query, $table, $where);
		}else
			$where = $given_where;

	/*	if(!is_array($order))
			if(substr($order,0,3) == 'oid')
				// if we need to order by the oid then add oid to the columns
				$columns = 'distinct '.$my_table.'.oid,'.$columns;*/

		// we asume that this query will return more than one row
		$single_object = false;
		if($id)
		{
			$var = $this->variable($id);
			$value_id = $this->{$id};
			//if this object has a numeric id defined, no conditions were given, then i want that object to be returned
			if ( (!is_array($conditions) || !count($conditions)) && !$given_where && !$order && !$limit && !$offset && $value_id)
			{
				$value_id = $var->escape($value_id);
				// one expectes a single row to be returned from the resulted query
				$where = "WHERE ".esc($table).".".esc($id)."=".$value_id;
				$single_object =true;
			}
		}

		$query = self::buildSelect($columns, $from_clause,$where,$order,$limit,$offset); 
		$res = Database::query($query);
		if($res===false)
		{
			$this->invalidate(); 
			self::warning(_("Could not select")." ".get_class($this)." "._("from database").". Query: $query");
			return ;
		}

		if (count($res) == 1 && $single_object)
			$this->populateObject($res);
		else
			return $this->buildArrayOfObjects($res);
	}

	/**
	 * Set the params for this object in the database.
	 * @param $params Array of type $field_name => $field_value
	 * @return Array where [0] is bool value showing whether query succesed , [1] is the default message
	 */
	public function setObj($params)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!$this->_set)
			$this->setParams($params);
		return array(true, '', array());
	}

	/**
	 * Set the variables of object and insert it in the database
	 * @param $params Array of param_name=>param_value used for setting the variables of this object
	 * @param $retrieve_id Bool value, true if you want the id of the inserted object to be retrived or not
	 * @param $keep_log Bool value, true when you wish to insert a log entry for that action
	 * @return Array, array[0] is true/false, true when inserting was succesfull, array[1] default message to could be printed to the user, array[2] is array with fields there was an error with
	 */
	public function add($params=NULL, $retrieve_id=true, $keep_log=true)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$res = array();
		if($params) {
			$res = $this->verifyRequiredFields($params);
			if(!$res[0])
				return $res;
			$res = $this->setObj($params);
			if(!$res[0])
				return $res;
		}
		if (count($res)>3) {
			unset($res[0]); // true/false
			unset($res[1]); // message
			unset($res[2]); // possible error fields
		}
		$res_insert = $this->insert($retrieve_id, $keep_log);
		// in some cases we need to add custom fields to result for setObj. Added them to result from insert
		return array_merge($res_insert,$res);
	}

	/**
	 * Set the variables of object and insert it in the database
	 * @param $params Array of param_name=>param_value used for setting the variables of this object
	 * @param $conditons Array of conditions after which to do the update. Default is NULL(update after object id)
	 * @param $verifications Array with conditions trying to specify if this object can be modified or not
	 * @param $check_col_diff Bool whether to check or not which fields have been modified before making the update 
	 * @return Array, array[0] is true/false, true when inserting was succesfull, array[1] default message to could be printed to the user, array[2] is array with fields there was an error with
	 */
	public function edit($params, $conditions = NULL, $verifications = array(), $check_col_diff = true)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!$check_col_diff)
			$this->_check_col_diff = $check_col_diff;

		if($params) {
			$res = $this->verifyRequiredFields($params);
			if(!$res[0])
				return $res;
			$res = $this->setObj($params);
			if(!$res[0])
				return $res;
			if (count($res)>3) {
				unset($res[0]); // true/false
				unset($res[1]); // message
				unset($res[2]); // possible error fields
			}
		} else
			$res = array();

		$res_update = $this->update($conditions, $verifications);
		// in some cases we need to add custom fields to result from setObj, keep them when returning answer from update
		return array_merge($res_update,$res);
	}

	/**
	 * Set the params for $this object
	 */
	public function setParams($params)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$this->_set = true;
		$this->_modified_col = array();

		foreach($params as $param_name=>$param_value) {
			$var = $this->variable($param_name);
			if ($var) {
				if($var->_type=="bool") {
					$param_value = Model::sqlBool($param_value);

					if (Database::boolValue($param_value) !== Database::boolValue($this->{$param_name}))
						$this->_modified_col[$param_name] = true;
				
				} elseif ($var->_type=="text" || substr($var->_type,0,7)=="varchar") {
					$param_value = Model::sqlString($param_value);

					if ($this->{$param_name} !== $param_value)
						$this->_modified_col[$param_name] = true;
					
				} else {
					if($this->{$param_name} != $param_value)
						$this->_modified_col[$param_name] = true;
				}
				$this->{$param_name} = $param_value;
			}
		}
	}

	/**
	 * Insert this object in the database
	 * @param $retrieve_id BOOL value marking if we don't wish the numeric id of the inserted object to be returned
	 * By default the numeric id of the object is retrieved
	 * @param $keep_log BOOL value marking whether to log this operation or not
	 * @return Array(Bool value,String), bool shows whether the inserting succeded or not, String is a default message that could be printed 
	 */
	public function insert($retrieve_id = true, $keep_log = true)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		$insert_parts = $this->buildInsertParts();
		foreach($insert_parts as $piece_name=>$val)
			${$piece_name} = $val;

		if ($columns == "")
			return;
		$table = $this->getTableName();

		if($error != "")
			return array(false, _("Failed to insert into ")._($table).".".$error, $error_fields);

		$query = "INSERT INTO ".esc($table)."($columns) VALUES($values)";
		$retrieve_last_id = ($retrieve_id && count($serials)) ? array($this->getIdName(),$table) : false;
		$res = Database::query($query, $retrieve_last_id);
		if ($res===false)
			return array(false,_("Failed to insert into ")._($table).".",$error_fields);
		if ($retrieve_id && count($serials))
			$this->{$this->getIdName()} = $res[2];
		$log = "inserted ".$this->getNameInLogs().": $insert_log";
		if($keep_log === true)
			self::writeLog($log,$query);
		return array(true,_("Successfully inserted into ")._(ucwords(str_replace("_"," ",$table))),array());
	}

	/**
	 * Build various pieces necesary for building an INSERT query. Build error string, error_field array and insert log
	 * @param #with_serials Bool. Defaults to false. Whether to add the serial fields in the built parts or not.
	 * This is true only when the parts are used from outside the Model class
	 * @return array("columns"=>String, "values"=>String, "error"=>String, "error_fields"=>array(), "serials"=>array(), "insert_log"=>String, "update_fields"=>String)
	 */
	protected function buildInsertParts($with_serials=false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$columns = "";
		$values = "";
		$serials = array();
		$insert_log = "";
		$error = "";
		$error_fields = array();
		$update_fields = "";
		foreach ($this->_model as $var_name => $var)
		{
			$value = $this->$var_name;
			if (is_array($value) || is_object($value))
				$value = print_r($value,true);
			
			if (!strlen($value) && !$with_serials)
			{
				// some types have defaults assigned by DB server so we don't set them
				switch ($var->_type)
				{
					case "serial":
						if ($var->_key == "") {
							$serials[$var_name] = true;
							$var = null;
						}
						break;
					case "bigserial":
						if ($var->_key == "") {
							$serials[$var_name] = true;
							$var = null;
						}
						break;
				}
			}
			if (!$var)
				continue;
			if (!strlen($value) && $var->isRequired()) {
				$error .= " "._("Required field")." '"._($var_name)."' "._("not set").".";
				$error_fields[] = $var_name;
				// gather other errors as well
				continue;
			}
			if (substr($var->_type,0,7) == "varchar")
			{
				$res = $this->isValidVarchar($var->_type, $value);
				if (!$res[0])
				{
					$error .= " "._("Field ")." '"._($var_name)."' "._("must be at most ".$res[1]." characters long but has '").$res[2]."'.";
					$error_fields[] = $var_name;
					continue;
				}
			}
			if ($columns != "")
			{
				$columns .= ",";
				$values .= ",";
				$insert_log .= ", ";
				$update_fields .= ",";
			}
			$columns .= esc($var_name);
			$values .= $var->escape($value);
			$update_fields .= esc($var_name)."=".$var->escape($value);
			if ($var_name!="password")
				$insert_log .= "$var_name=".$value;
			else
				$insert_log .= "$var_name=***";
		}

		return array("columns"=>$columns, "values"=>$values, "error"=>$error, "error_fields"=>$error_fields, "serials"=>$serials, "insert_log"=>$insert_log, "update_fields"=>$update_fields);
	}

	/**
	 * Verify the allowed length set on varchar type variable
	 */  
	private function isValidVarchar($type, $value)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$var_length = strlen($value);
		$allowed_length = explode("(", $type);
		$allowed_length = substr($allowed_length[1],0,-1);
		$allowed_length = (int)$allowed_length;
		if ($var_length > $allowed_length)
			return array(false,$allowed_length,$var_length);
		return array(true);
	}

	/**
	 * Build insert query for this $object
	 * @return Text representing the query
	 */
	public function buildInsertQuery()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$columns = "";
		$values = "";
		foreach ($this->_model as $var_name => $var)
		{
			$value = $this->$var_name;
			if (!$value)
				continue;
			if ($columns != "")
			{
				$columns .= ",";
				$values .= ",";
			}
			$columns .= esc($var_name);
			$values .= $var->escape($value);
		}
		if ($columns == "")
			return;
		$table = $this->getTableName();

		$query = "INSERT INTO ".esc($table)."($columns) VALUES($values)";
		return $query;
	}
	
	/**
	 * Update object (!! Use this after you selected the object or else the majority of the fields will be set to null)
	 * If you want to use it directly: set global $check_col_diff = false
	 * @param $conditions Array of conditions for making an update
	 * if no parameter is sent when method is called it will try to update based on the numeric id od the object, unless is was invalidated
	 * @param $verifications Array with conditions trying to specify if this object can be modified or not
	 * @return Array(BOOL value, String, Array, Int), boolean markes whether the update succeeded or not, String is a default message that might be printed, Array with name of fields there was a problem with, Int shows the number of affected rows
	 */
	public function update($conditions = array(), $verifications = array(), $keep_log = true)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$where = "";
		$variables = "";
		$update_log = "";
		$error = "";
		$error_fields = array();
		if (!is_array($conditions) || !count($conditions))  {
			if($this->isInvalid())
				return array(false, _("Update was not made. Object was invalidated previously."),$error_fields, 0);
			$id = $this->getIdName();
			if(!$id || !$this->{$id})
				return array(false, _("Don't have conditions to perform update."),$error_fields,0);
			$conditions = array($id=>$this->{$id});
		}
		// add the received verifications to the conditions
		if($verifications)
			$conditions = array_merge($conditions, $verifications);
		$where = $this->makeWhereClause($conditions, true);
		$vars = self::getVariables(get_class($this));
		if (!$vars)
			return array(false, _("Could not retrieve object variables"),array(),0);
		foreach($vars as $var_name=>$var) 
		{
			if ($this->_check_col_diff && !isset($this->_modified_col[$var_name]))
				continue;

			if ($variables != '')
			{
				$variables .= ", ";
				$update_log .= ", ";
			}
		
			if(!strlen($this->{$var_name}) && $var->isRequired()) {
				$error .= " "._("Required field")." '"._($var_name)."' "._("not set").".";
				$error_fields[] = $var_name;
				continue;
			}
			$value = $this->{$var_name};
			if (substr($var->_type,0,7) == "varchar") {
				$res = $this->isValidVarchar($var->_type,$value);
			   	if (!$res[0]) {
					$error .= " "._("Field ")." '"._($var_name)."' "._("must be at most ".$res[1]." characters long but has '").$res[2]."'.";
					$error_fields[] = $var_name;
					continue;
				}
			}
			$value = $var->escape($value);

			$variables .= esc($var_name)."=".$value."";
			if ($var_name!="password")
				$update_log .= "$var_name=".$this->{$var_name}.""; 
			else
				$update_log .= "$var_name=***";
		}
		$obj_name = $this->getObjectName();
		
		if ($error != "")
			return array(false,_("Failed to update").' '._($obj_name).".".$error, $error_fields,0);
		if ($variables == "")
			return array(true, _('Nothing to update in ')._($obj_name).".",array());

		$table = $this->getTableName();
		$query = "UPDATE ".esc($table)." SET $variables $where";
		//print "query-update:$query";
		$res = Database::query($query);
		if($res===false || $res[0]===false) 
			return array(false,_('Failed to update ')._($obj_name).".",array(),0);
		else {
			$affected = $res[1];
			$message = _('Successfully updated ').$affected.' ' ._($obj_name);
			if ($affected != 1)
				$message .= 's';
			$update_log = "updated ".$this->getNameInLogs().": $update_log $where";
			if ($keep_log === true)
				self::writeLog($update_log,$query);
			return array(true,$message,array(),$affected);
		}
	}

	/**
	 * Update only the specified fields for this object
	 * @param $conditions NULL for using the id / Array on pairs $key=>$value
	 * @param $fields Array($field1, $field2 ..)
	 * @param $verifications Array with conditions trying to specify if this object can be modified or not
	 * @return Array(Bool,String,Array,Int) Bool whether the update succeeded or not, String is a default message to print, 
	 * Array with name of fields there was a problem with -always empty for this method, Int is the number of affected rows 
	 */
	public function fieldUpdate($conditions = array(), $fields = array(), $verifications = array())
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$where = "";
		$variables = "";
		$update_log = "";
		$error = "";
		$error_fields = array();

		if(!is_array($conditions) || !count($conditions)) {
			if($this->isInvalid())
				return array(false, _("Update was not made. Object was invalidated previously."),array(),0);
			$id = $this->getIdName();
			if(!$id || !$this->{$id})
				return array(false, _("Don't have conditions to perform update."), array(),0);
			$conditions = array($id=>$this->{$id});
		}
		if($verifications)
			$conditions = array_merge($conditions, $verifications);

		$where = $this->makeWhereClause($conditions, true);
		$vars = self::getVariables(get_class($this));
		if (!is_array($fields) || !count($fields))
			return array(false,_("Update was not made. No fields were specified."),array(),0);
		foreach($vars as $var_name=>$var) 
		{
			if(!in_array($var_name,$fields))
				continue;
			if(!isset($vars[$var_name]))
				continue;

			if($variables != '')
			{
				$variables .= ", ";
				$update_log .= ", ";
			}

			$value = $this->{$var_name};
			if(substr($value,0,6) == "__sql_")
			{
				//Value is an sql function or other column from the same table
				//When using this and referring to a column named the same as a reserved word 
				//in PostgreSQL "" must be used inside the $value field
				$value = substr($value,6,strlen($value));
				$variables .= esc($var_name)."="."$value";
			}else{
				$variables .= esc($var_name)."=".$var->escape($value)."";
			}
			if (substr($var->_type,0,7) == "varchar") {
				$res = $this->isValidVarchar($var->_type,$value);
			   	if (!$res[0]) {
					$error .= " "._("Field ")." '"._($var_name)."' "._("must be at most ".$res[1]." characters long but has '").$res[2]."'.";
					$error_fields[] = $var_name;
					continue;
				}
			}                 	

			if ($var_name!="password")
				$update_log .= "$var_name=$value";
			else
				$update_log .= "$var_name=***";
		}

		$obj_name = $this->getObjectName();
		if ($error != "")
			return array(false,_("Failed to update").' '._($obj_name).".".$error, $error_fields,0);

		$query = "UPDATE ".esc($this->getTableName())." SET $variables $where";
		$res = Database::query($query);
		if($res===false || $res[0]===false) 
			return array(false,_('Failed to update ')._($obj_name),array(),0);
		else
		{
			$affected = $res[1];
			$mess = _('Successfully updated ').$affected.' ' ._($obj_name);
			if ($affected != 1)
				$mess .= 's';
			$update_log = "update ".$this->getNameInLogs().": $update_log $where";
			self::writeLog($update_log,$query);
			return array(true,$mess,array(),$affected);
		}
	}

	/**
	 *	Verify if the required fields for this object will be set.
	 * @param $params Array of type $param=>$value. Only the required fields appearing in this array will be verified
	 * @return Array(Bool, Text, Array) Bool true if all required fields in $params are set, Text with the error message, Array with the name of the fields that were not set
	 */
	public function verifyRequiredFields($params)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$error_fields = array();
		$error = "";
		$class = get_class($this);
		foreach ($params as $param_name => $param_value) {
			$var = Model::getVariable($class, $param_name);
			if (!$var)
				continue;
			if ($var->_required === true && (!strlen($param_value) || $param_value =='')) {
				if ($error != "")
					$error .= ", ";
				$error .= _("Field")." '"._(str_replace("_"," ",$param_name))."' "._("is required");
				$error_fields[] = $param_name;
			}
		}
		if ($error != "") {
			$error .= ".";
			return array(false, $error, $error_fields);
		} else
			return array(true, "", array());
	}
	
	 /**
	  * Verify if there is an entry in the database for the object of the given class
	  * @param $param Name of the field that we don't want to have duplicates
	  * @param $value Value of the field @ref $param
	  * @param $class Object class that we want to check for
	  * @param $id Name of the id field for the type of @ref $class object
	  * @param $value_id Value of the id
	  * @param $additional Other conditions that will be writen directly in the query
	  * @return true if the object exits, false if not
	  */
	public static function rowExists($param, $value, $class, $id, $value_id = NULL, $additional = NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$table = self::getClassTableName($class);

		$value_id = Database::escape($value_id);
		if ($value_id)
			$query = "SELECT $id FROM $table WHERE ".esc($param)."='".Database::escape($value)."' AND $id!='$value_id' $additional";
		else
			$query = "SELECT $id FROM $table WHERE ".esc($param)."='".Database::escape($value)."' $additional";
		$res = Database::query($query);
		if ($res===false) {
			Debug::trigger_report('critical',"Could not do: $query");
			exit(_("Could not do").": $query");
		}
		if (count($res))
			return true;
		return false;
	}

	 /**
	  * Verify if an object has an entry in the database. Before this method is called one must set
	  * the values of the fields that will build the WHERE clause. Variables that have a default value 
	  * will be ignored unless added in $columns_with_default
	  * @param $id_name Name of the id of this object
	  * @param $columns_with_default Array. List of the columns with default values that should be added 
	  * when building condition
	  * @param $extra_conditions Array. List of additional conditions to take into account when db query is performed
	  * @return id or the object that matches the conditions, false otherwise
	  */
	public function objectExists($id_name = NULL, $columns_with_default=array(), $extra_conditions=array())
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables(get_class($this));
		if (!$vars)
			return null;

		if(!$id_name) 
			$id_name = $this->getIdName();

		$class = get_class($this);
		if(!$id_name || !$this->variable($id_name))
		{
			Debug::trigger_report('critical',_("Class")." $class "._("doesn't have an id. You can't use method objectExists."));
			exit();
		}

		$fields = '';
		$table = $this->getTableName();

		//get an object of the same class as $this
		$clone = new $class;

		$conditions = array();
		foreach($vars as $var_name=>$var) 
		{
			// ignoring fields that have a default value and the numeric id of the object
			if ($this->{$var_name} != '' && $var_name != $id_name) 
			{
				if($clone->{$var_name} != '' && !in_array($var_name, $columns_with_default))
					continue;
				$conditions[$var_name] = $this->{$var_name};
			}
		}

		$var = $this->variable($id_name);
	//	$value_id = $var->escape($this->{$id_name});
		$value_id = $this->{$id_name};
		$where = ($value_id && $value_id != "NULL") ? "WHERE $fields AND ".esc($id_name)."!=$value_id" : "WHERE $fields";
		if($value_id && $value_id != "NULL")
			$conditions[$id_name] = "!=".$value_id;
		$conditions = array_merge($conditions, $extra_conditions);
		$where = $this->makeWhereClause($conditions,true);
		$query = self::buildSelect($id_name,$table,$where);
		$res = Database::query($query);
		if($res === false || $res === NULL) {
			Debug::trigger_report('critical',_("Operation was blocked because query failed").": '$query'.");
			return true;
		}
		if(count($res)) {
			return $res[0][$id_name];
		}
		return false;
	}


	/**
	 * Recursive function that deletes the object(s) matching the condition and all the objects having foreign keys to
	 * this one with _critical=true, the other ones having _critical=false with the associated column set to NULL
	 * @param $conditions Array of conditions for deleting (if count=0 then we look for the id of the object) 
	 * @param $seen Array of classes from were we deleted
	 * @param $recursive Bool default true. Whether to delete/clean objects pointing to this one 
	 * @param $cb_recursive String default NULL. If set and $recursive=true it will call this method recursively instead of objDelete
	 * @return array(true/false,message) if the object(s) were deleted or not
	 */
	public function objDelete($conditions=array(), $seen=array(), $recursive=true, $cb_recursive=null)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables(get_class($this));
		if (!$vars)
			return null;
		if ($recursive && $cb_recursive==null)
			$cb_recursive = "objDelete";

		$orig_cond = $conditions;
		$table = $this->getTableName();
		if (!is_array($conditions) || !count($conditions)) {
			if ($this->isInvalid())
				return array(false, _("Could not delete object of class")." "._(get_class($this)).". "._("Object was previously invalidated").".");
			
			if (($id_name = $this->GetIdName())) {
				$var = $this->variable($id_name);
				$id_value = $var->escape($this->{$id_name});
				if (!$id_value || $id_value == "NULL")
					$where = '';
				else {
					$where = " where ".esc($id_name)."=$id_value";
					$conditions[$id_name] = $id_value;
				}
			} else
				$where = '';
		} else
			$where = $this->makeWhereClause($conditions, true);

		Debug::xdebug('framework',"in objDelete ".get_class($this)." with conditions ".$where);

		if ($where == '') 
			return array(false, _("Don't have any condition for deleting for object")." "._(get_class($this)));

		// array of pairs object_name=>array(array(var_name=>var_value),array(var_name2=>var_value2)) in which we have to check for deleting on cascade
		$to_delete = array();

		if ($recursive) {
			$objs = Model::selection(get_class($this),$conditions);
			for ($i=0; $i<count($objs); $i++) {
				foreach ($vars as $var_name=>$var) {
					$value = $objs[$i]->{$var_name};
					if (!$value)
						continue;
					//search inside the other objects if there are column that reference $var_name column
					foreach (self::$_models as $class_name=>$class_vars) {
						foreach ($class_vars as $class_var_name=>$class_var) {
					//		if (!($class_var->_key == $table && ($class_var_name == $var_name || $class_var->_matchkey == $var_name)))
							if (!($class_var->_key == $table && (($class_var_name == $var_name && !$class_var->_matchkey) || $class_var->_matchkey == $var_name)))
								continue;
							if ($class_var_name == $var_name)
								$cond_field = $class_var_name;
							elseif ($class_var->_matchkey)
								$cond_field = $class_var->_matchkey;

							$obj = new $class_name;
							$obj->{$class_var_name} = $value;
							if ($class_var->_critical) {
								// if relation is critical equivalent to delete on cascade, add $class_name to array of classes on which same method will be applied 
								if (!isset($to_delete[$class_name]))
									$to_delete[$class_name] = array(array($class_var_name=>$value));
								else
									$to_delete[$class_name][] = array($class_var_name=>$value);
							} else {
								// relation is not critical. we just need to set to NULL the fields pointing to this one
								$nr = $obj->fieldSelect('count(*)',array($class_var_name=>$value));
								if($nr) {
									//set column $class_var_name to NULL in all rows that have the value $value 
									$obj->{$class_var_name} = NULL;
									$obj->fieldUpdate(array($class_var_name=>$value),array($class_var_name));
								}
							}
						}
					}
				}
			}
		}
		$query = "DELETE FROM ".esc($table)." $where";
		$res = Database::query($query);
		array_push($seen,strtolower(get_class($this)));
		$cnt = count($seen);

		foreach ($to_delete as $object_name=>$conditions) {
			$obj = new $object_name;
			for ($i=0;$i<count($conditions);$i++)
				$obj->$cb_recursive($conditions[$i],$seen);
		}

		if ($res && $res[0]) {
			if ($cnt) {
				self::writeLog("deleted ".$this->getNameInLogs()." $where","$query");
			}// else
				return array(true, _("Successfully deleted ")._($res[1])._(" object(s) of type ")._(get_class($this)),$res[1]);
		} else {
			if ($cnt)
				Debug::trigger_report('critical',_("Could not delete object of class ")._(get_class($this)));
			else
				return array(false, _("Could not delete object of class ")._(get_class($this)));
		}
		return;
	}

	/**
	 * Get objects depending on the specified conditions from $dump_field from $table
	 * @param $objects_to_dump Array - Variable passed by reference where objects containing all found objects
		Ex: Array("extenion:2"=>The extension object with id 2, "group:3"=>The group object with id 3)
	 * @param $field_name Text - name of the field to use as condition
	 * @param $field_value Value of $field_name
	 * @param $table Text - name of the table where objects would point to
	 * @param $exceptions Array containing class names to skip 
	 * @param $depth_in_search Int default 0. The name of objects when this variable is 0 will added in returned array
	 * @return Array with pairs: class_name=>"name of the (first level) objects that will be dumped separated by ,"
	 */
	static function getObjectsToDump(&$objects_to_dump,$field_name, $field_value, $table, $exceptions=array(), $depth_in_search=0)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if (!self::$_models)
			self::init();
		if (!self::$_models) {
			Debug::trigger_report("critical",_("Don't have models after init() was called."));
			exit(_("Don't have modeles after init() was called."));
		}

		$dump_description = array();

		// look at all the models in the system
		foreach (self::$_models as $class_name=>$model)
		{
			if (in_array($class_name, $exceptions))
				continue;
			if (!($use_dump_fields = self::matchesDumpConditions($model, $field_name, $field_value, $table)))
				continue;
			// get the objects in current class_name that match the dump_fields
			$objs = Model::selection($class_name, $use_dump_fields);
			$dump_desc = "";
			// for all found objects
			for ($i=0; $i<count($objs); $i++)
			{
				$obj = $objs[$i];
				$id_name = $obj->getIdName();
				if (!$id_name)
					continue;
				$id_value = $obj->{$id_name};

				$dump = self::objectDumpDescription($obj, $objects_to_dump, $depth_in_search);
				if ($dump != "") {
				    if ($dump_desc != "")
					$dump_desc .= ", ";
				    $dump_desc .= $dump;
				}

				self::getObjectsToDump($objects_to_dump, $id_name, $id_value, $obj->getTableName(), array_merge($exceptions,array($class_name)), $depth_in_search+1);
			}
			if ($dump_desc != "")
				$dump_description[self::getClassTableName($class_name)] = $dump_desc;
		}
		return $dump_description;
	}

	/**
	 * Build description for a dumped object
	 * @param $obj Object to build description for
	 * @param &$objects_to_dump Array of dumped objects so far
	 * @param $depth_in_search Int. Objects description is returned only if $depth_in_search == 0
	 * @return String representing name of id of dumped object
	 */ 
	static function objectDumpDescription($obj, &$objects_to_dump, $depth_in_search)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$class_name = strtolower(get_class($obj));
		$id_name = $obj->getIdName();
		if (!$id_name)
			return '';
		$id_value = $obj->{$id_name};

		$index = "$class_name:$id_value";

		// make sure we don't add the same object twice
		if (isset($objects_to_dump[$index]))
			return '';
		$objects_to_dump[$index] = $obj;
		if ($depth_in_search === 0) 
		{
			$name_var = '';
			if ($obj->variable($class_name))
				$name_var = $class_name;
			elseif ($obj->variable("name"))
				$name_var = "name";
			else {
				$name_var = $id_name;
			}
			return $obj->{$name_var};
		}
		return "";
	}

	/**
	 * Verify if there are variables in provided $model that match $field_name from $table
	 * if yes then build conditions array for specied $model
	 * @param $model Array containing the variables of an object
	 * @param $field_name Text representing the name of the field where the variables from $model might point
	 * @param $field_value Value of $field_name. Will be used when building the return value
	 * @param $table Name of table from where $field_name is 
	 * @return false if no match Array defining the conditions to select objects
	 */
	static function matchesDumpConditions($model, $field_name, $field_value, $table)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$dump_fields = array();
		// or some other field might point to it (more than one field in the same object)
		foreach ($model as $var_name=>$var) 
		{
			if ($var->_key == $table && ($var_name==$field_name || $var->_matchkey==$field_name)) {
				if (!count($dump_fields))
					$dump_fields[$var_name] = $field_value;
				elseif (count($dump_fields)==1 && !isset($dump_fields[0])) {
					// if more than one field point to $field_name then build an OR condition between there fields
					$dump_fields = array(
						array_merge(
							array("__sql_relation"=>"OR",$var_name=>$field_value), 
							$dump_fields
						)
					);
				} else
					$dump_fields[0][$var_name] = $field_value;
				continue;
			}
		}
		if (!count($dump_fields))
			return false;
		return $dump_fields;
	}

	/**
	 * Recursive function that checks how many rows will be affected in the database, if objDelete will be called on this object using the same $conditions param.
	 * If table doesn't have references from other tables then this table will be the only one affected.
	 * @param $conditions Array of conditions for deleting (if count=0 then we look for the id of the object) 
	 * @param $message String representing the message 
	 * @return The message with the number affected row (deleted or set to NULL) in tables 
	 */
	public function ackDelete($conditions=array(), $message = "")
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables(get_class($this));
		if(!$vars)
			return null;

		$original_message = $message;
		$orig_cond = $conditions;
		$table = $this->getTableName();
		if(!is_array($conditions) || !count($conditions)) 
		{
			if($this->isInvalid())
				return array(false, _("Could not try to delete object of class")." "._(get_class($this)).". "._("Object was previously invalidated").".");
			
			if(($id_name = $this->GetIdName()))
			{
				$var = $this->variable($id_name);
				$id_value = $var->escape($this->{$id_name});
				if(!$id_value || $id_value == "NULL")
					$where = '';
				else {
					$where = " where ".esc($id_name)."=$id_value";
					$conditions[$id_name] = $id_value;
				}
			} else
				$where = '';
		} else
			$where = $this->makeWhereClause($conditions, true);

		$objs = Model::selection(get_class($this),$conditions);
		// array of pairs object_name=>array(array(var_name=>var_value),array(var_name2=>var_value2)) in which we have to check for deleting on cascade
		$to_delete = array();
		for($i=0; $i<count($objs); $i++) 
		{
			foreach($vars as $var_name=>$var)
			{
				$value = $objs[$i]->{$var_name};
				if (!$value)
					continue;
				//search inside the other objects if there are column that reference $var_name column
				foreach(self::$_models as $class_name=>$class_vars)
				{
					foreach($class_vars as $class_var_name=>$class_var)
					{
						if (!($class_var->_key == $table && ($class_var_name == $var_name || $class_var->_matchkey == $var_name)))
							continue;

						$obj = new $class_name;
						$obj->{$class_var_name} = $value;
						if ($class_var->_critical)
						{
							// if relation is critical equivalent to delete on cascade, add $class_name to array of classes on which same method will be applied 
							if(!isset($to_delete[$class_name]))
								$to_delete[$class_name] = array(array($class_var_name=>$value));
							else
								$to_delete[$class_name][] = array($class_var_name=>$value);
						}
						else
						{
							$nr = $obj->fieldSelect('count(*)',array($class_var_name=>$value));
							if($nr)
								$message .= ", ".$obj->getTableName();
						}
					}
				}
			}
		}
		$message .= ", ".$this->getTableName();
 
		foreach($to_delete as $object_name=>$conditions)
		{
			$obj = new $object_name;
			for($i=0;$i<count($conditions);$i++)
				$message .= $obj->ackDelete($conditions[$i]);
		}

		return $message;
	}

	/**
	 * Extend the calling object with the variables provided
	 * @param $vars Array of pairs : $var_name=>$table_name that will be added to this objects model
	 * If $var_name = "var_name_in_calling_table:referenced_var_name" then the new variable will be called  var_name_in_calling_table and will point to referenced_var_name in $table_name
	 */
	public function extend($vars)
	{
		global $db_type;
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		foreach($vars as $var_name=>$table_name) 
		{
			$break_var_name = explode(":",$var_name);
			if(count($break_var_name) == "2") 
			{
				$var_name = $break_var_name[0];
				$references = $break_var_name[1];
			}else
				$references = NULL;

			if(isset($this->_model[$var_name]))
			{
				Debug::trigger_report('critical', _("Trying to override existing variable")." $var_name. "._("Ignoring this field when extending").".");
				continue;
			}
			// don't let user extend the object using a numeric key
			if(is_numeric($var_name))
			{
				Debug::trigger_report('critical',"$var_name "."is not a valid variable name. Please do not use numbers or numeric strings as names for variables.");

				exit("$var_name "._("is not a valid variable name. Please do not use numbers or numeric strings as names for variables").".");
			}
			$tb_name = (!is_array($table_name)) ? $table_name : $table_name["table"];
			$obj = Model::getObject($tb_name);
			if (!$obj) {
				Debug::trigger_report('critical',"Can't get object for table $tb_name");

				exit(_("Can't get object for table $tb_name"));
			}
			$ref_var = ($references) ? $obj->variable($references) : $obj->variable($var_name);
			if (!$ref_var) {
				Debug::trigger_report('critical',"Can't get referenced variable for var_name=$var_name");

				exit(_("Can't get referenced variable for var_name=$var_name"));
			}
			$join_type = (is_array($table_name)) ? strtoupper($table_name["join"]) : NULL;

			// in MYSQL FULL OUTER JOIN is not supported
			if ($db_type == "mysql" && $join_type == "FULL") {
				Debug::trigger_report('critical', _("Wrong developer implementation, JOIN TYPE 'FULL' is not supported."));
				exit(_("Wrong developer implementation, JOIN TYPE 'FULL' is not supported."));
			}

			// check the allowed types of JOIN
			$allowed_types = array("RIGHT", "LEFT", "INNER", "FULL");
			if ($join_type != NULL && !in_array($join_type, $allowed_types)) {
				Debug::trigger_report('critical', _("Wrong developer implementation, JOIN TYPE is incorrect").": ".$join_type);
				exit(_("Wrong developer implementation, JOIN TYPE is incorrect").": ".$join_type.". "._("The allowed JOIN TYPES are: 'RIGHT', 'LEFT', 'INNER', 'FULL'."));
			}

			$this->_model[$var_name] = new Variable($ref_var->_type,null,$tb_name,false,$references,$join_type);
			$this->{$var_name} = NULL;
		}
	}

	/**
	 * Merge two objects (that have a single field common, usually an id)
	 * Function was done to make easier using 1-1 relations
	 * @param $object is the object whose properties will be merged with those of the calling object: $this
	 */
	public function merge($object)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$party_vars = self::getVariables(get_class($object));
		$vars = self::getVariables(get_class($this));
		$party_table = $object->getTableName();

		foreach($party_vars as $var_name=>$value)
		{
			// fields that are named the same as one of the calling object will be ignored
			// for fields that have the same name in both objects please use the extend function : extend(array("key_in_original_table:rename_key_for_calling_table"=>"original_table"));
			if(isset($vars[$var_name]))
				continue;
			$this->_model[$var_name] = new Variable("text",null,$party_table);
		}
	}


	/**
	 * Sets the value of each variable inside this object to NULL
	 */
	public function nulify()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables(get_class($this));
		foreach($vars as $var_name=>$var)
			$this->{$var_name} = NULL;
	}

	/**
	 * Exports an array of objects to an array of array.
	 * @param $objects Array of objects to be exported
	 * @param $formats Array of pairs $var_name(s)=>$value
	 * $var_name is a name of a variable or more variable names separated by commas ','
	 * $value can be '' if column will be added in the array with the same name and the value resulted from query 
	 * $value can be 'function_nameOfFunction' the $nameOfFunction() will be applied on the value resulted from query  and that result will be added in the array to be returned
	 * $value can be 'name_to_appear_under' if  column will be added in the array with the name name_to_appear_under and the value resulted from query 
	 * $value can be 'function_nameOfFunction:name_to_appear_under' in order to have name_to_appear_under and value returned from calling the function nameOfFunction
	 * The most complex usage is: $var_name is 'var1,var2..' and $value is 'function_nameOfFunction:name_to_appear_under', then nameOfFunction(var1,var2,..) will be called and the result will be added in the array to be returned under name_to_appear_under
	 * $var_name can start with ('1_', '2_' ,'3_' , '4_', '5_', '6_', '7_', '8_', '9_', '0_'), that will be stripped in order to have 
	 * multiple fields in the array generated from the same $variable 
	 * @param $block Bool value. If true then only the fields specified in $formats will be returned
	 */
	public static function objectsToArray($objects, $formats, $block = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if (!is_array($objects) || !count($objects))
			return array();
		// array of beginnings that will be stripped
		// usage is motivated by need of having two columns in the array generated from a single variable
		// Example: we have a timestamp field called date, but we need two fields in the array, one for date and the other for time
		// $formats=array("1_date"=>"function_get_date:date", "2_date"=>"function_get_time:time")
		$begginings = array('1_', '2_' ,'3_' , '4_', '5_', '6_', '7_', '8_', '9_', '0_');
		$i = 0;
		if (!isset($objects[$i])) {
			while(!isset($objects[$i])) {
				$i++;
				if ($i>200) 
				{
					Debug::trigger_report('critical',_("Infinit loop"));
					return;
				}
			}	
		}
		$vars = $objects[$i]->_model;
		if(!$vars)
			return null;
		$array = array();

		if(count($objects))
			$id =$objects[0]->getIdName(); 
		for($i=0; $i<count($objects); $i++) 
		{
			if(!isset($objects[$i]))
				continue;
			$vars = $objects[$i]->_model;
			$keep = array();
			if ($formats != 'all')
				foreach($formats as $key=>$value)
				{
					$key = trim($key);
					if(in_array(substr($key,0,2), $begginings))
						$key = substr($key,2,strlen($key));
					$name = ($value == '') ? $key : $value;
					if(substr($name,0,9) == "function_") 
					{
						$name = substr($name,9,strlen($name));
						$arr = explode(':',$name);
						if(count($arr)>1)
						{
							$newname = $arr[1];
							$name = $arr[0];
						}else
							$newname = $key;
						if(str_replace(',','',$key) == $key)
							$array[$i]["$newname"] = call_user_func($name,$objects[$i]->{$key});
						else
						{
							$key = explode(',',$key);
							$params = array();
							for($x=0; $x<count($key); $x++)
								$params[trim($key[$x])] = $objects[$i]->{trim($key[$x])};
							$array[$i]["$newname"] = call_user_func_array($name,$params);
							$key = implode(":",$key);
						}
					}else
						$array[$i]["$name"] = $objects[$i]->{$key};
					$keep[$key] = true;
				}
			//by default if $block is not true then the id of this object will be added to the result
			if (!$block) 
			{
				foreach($vars as $key=>$value)
				{
					if ($formats != 'all' && $key!=$id)
						continue;
					$array[$i]["$key"] = $objects[$i]->{$key};
					$keep[$key] = true;
				}
			}
		}
		return $array;
	}

	/**
	 * Perform vacuum on this object's associated table
	 */
	public function vacuum()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$table = $this->getTableName();
		Database::vacuum($table);
	}

	/**
	* Convert a boolean or SQL bool representation to a SQL bool
	* @param $value Value to convert, can be true, false, 't' or 'f'
	* @param $defval Default to return if $value doesn't match
	*/
	public static function sqlBool($value, $defval = 'NULL')
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if ($value===true || $value==='t' || $value==='1' || $value===1)
			return 't';
		if ($value===false || $value==='f' || $value==='0' || $value===0)
			return 'f';
		return $defval;
	}

	/**
	 * Convert a given value to a SQL string
	 * @param $value the Value to convert
	 */
	public static function sqlString($value)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if ($value === "" || $value === NULL)
			return NULL;

		if (!is_string($value))
			return strval($value);

		return $value;
	}

	/**
	 * Creates a WHERE clause for a query
	 * @param $conditions Array defining the condtions
	 * @return Text representing the WHERE clause
	 */
	public function exportWhereClause($conditions)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return $this->makeWhereClause($conditions);
	}

	/**
	 * Return whether the model was modified or not
	 */
	public static function modified()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return self::$_modified;
	}

	/**
	 * Resed modified flag. Automatically done when Database::disconnect() is called
	 */
	public static function reset_modified()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		self::$_modified = false;
	}

	/**
	 * Get a list of all classes derived from Model
	 * @return Array of strings that represent the names of Model classes
	 */
	static function getModels()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$models = array();
		$classes = get_declared_classes();
		foreach ($classes as $class)
		{
			if (get_parent_class($class) == "Model" || get_parent_class(get_parent_class($class)) == "Model")
				$models[] = $class;
		}
		return $models;
	}

	/**
	 * One-time initialization of the static array of model variables.
	 * This method is called internally from any methods that need access
	 *  to the variables of any derived class.
	 * IMPORTANT: All derived classes must be defined when this method is
	 *  called for the first time.
	 */
	static function init()
	{
		Debug::func_start(__METHOD__,func_get_args(),"in_framework");

		if (self::$_models)
			return;

		$classes = get_declared_classes();
		$db_identifier = self::getCurrentDbIdentifier();
		$default_identifier = self::getDefaultDbIdentifier();

		//print "db_identifier=$db_identifier\n";
		//print "default_identifier=$default_identifier\n";
		foreach ($classes as $class)
		{
			if (self::checkAncestors($class))
			{
				$vars = null;
				if (!method_exists($class,"variables"))
					continue;
				$vars = call_user_func(array($class,"variables"));
				if (!$vars)
					continue;

				//$identifier = @call_user_func(array($class,"getDbIdentifier"));
				// if object doesn't have identifier and current identier if different than the default => skip
				// if object has identifier but current identier is not in the object identifiers => skip

				// gave up on this -- very confusing for developer when classes are not loaded in modules
				// now all classes are loaded but they won't be updated and if query for wrong class is made we'd rather seen the error
				//if ( (!in_array($db_identifier, $identifier) && count($identifier)) || ($default_identifier!=$db_identifier  && !count($identifier)) )
				//	continue;

				foreach ($vars as &$var)
					$var->_owner = $class;
				self::$_models[strtolower($class)] = $vars;
				$obj = new $class;
				// check to see if this object is a performer for the ActionLog class
				$performer = $obj->isPerformer();
				if($performer && count($performer))
					self::$_performers[strtolower($class)] = $performer;
			}
		}
	}

	/**
	 * Check class ancestors to see if one of them is Model
	 * @param $class String
	 * @return Bool
	 */
	static function checkAncestors($class)
	{
		$parent = get_parent_class($class);

		if ($parent=="Model")
			return true;
		elseif (!$parent)
			return false;
		return self::checkAncestors($parent);
	}

	/**
	 * Get the identifier of the current database connection
	 * @return String representing the db identifier
	 */ 
	static function getCurrentDbIdentifier()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $db_identifier;

		if (isset($db_identifier))
			return $db_identifier;

		return self::getDefaultDbIdentifier();
	}

	/**
	 * Get default identifier of the database connection, if set
	 * @return String representing the identifier
	 */ 
	static function getDefaultDbIdentifier()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $servers;
		global $default_server;

		if (isset($servers[$default_server])) {
			foreach ($servers[$default_server] as $type=>$data)
				if (isset($data["db_identifier"]))
					return $data["db_identifier"];
		}
		return NULL;
	}

	/**
	 * Default function that will be reimplemented from objects that will not be in the default database
	 * @return Array empty from here, Array with identifier keys from the derivated classes
	 */ 
	public function getDbIdentifier()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return array();
	}

	/**
	 * 
	 * @return array. Returns an array containing all db_identifiers defined in configuration files
	 */
	static function getAllDbIdentifiers()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		
		$all_db_identifiers = array();
		
		$i = 1;
		while(true) {
			$index = ($i == 1) ? "" : $i;
			
			global ${"db_identifier".$index};
			if (!isset(${"db_identifier".$index}))
				break;
			
			$all_db_identifiers[] = ${"db_identifier".$index};
			$i++;
		}
		
		if (!is_array($all_db_identifiers) || !count($all_db_identifiers)) 
			return array(NULL);
		
		return $all_db_identifiers;
	}
	/**
	 * Update the database to match all the models
	 * @param $skip_default_objects Bool. Default false. If true, just tables will be updated, without calling defaultObject
	 * @return True if the database was synchronized with all the models
	 */
	static function updateAll($skip_default_objects = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $error_sql_update;		
		
		// Check the table columns
		// We assume there are no changes
		$error_sql_update = false;

		Debug::Output("------- "._("Entered")." updateAll()");
	
		// if no db identifier defined will return array(null)
		$all_db_identifiers = self::getAllDbIdentifiers();
		
		foreach ($all_db_identifiers as $key => $ident) {

			if ($key == 0 && !Database::connect())
				return false;
			else if ($key > 0 && !Database::switchDatabase($key+1))
				return false;
			
			self::init();

			$db_identifier = self::getCurrentDbIdentifier();
			$default_identifier = self::getDefaultDbIdentifier();
			
			foreach (self::$_models as $class => $vars)
			{
				$object = new $class;

				$identifier = call_user_func(array($object,"getDbIdentifier"));
				//if object doesn't have identifier consider identifier to be the first one defined in config.php (or null if not in config.php)
				if ( (!is_array($identifier) || !count($identifier)) && $all_db_identifiers[0])
					$identifier = array($all_db_identifiers[0]);
				
				// if object doesn't have identifier and current identier is different than the default => skip
				// if object has identifier but current identier is not in the object identifiers => skip
				// even if classes are loaded, they won't be updated
				// because it would build all tables on call connections

				if ( (!in_array($db_identifier, $identifier) && count($identifier)) || ($default_identifier!=$db_identifier  && !count($identifier)) )
					continue;

				$table = $object->getTableName();
				if (!Database::updateTable($table,$vars))
				{
					Debug::xdebug('critical',_("Could not update table of class")." $class");
					//return false;
					$error_sql_update = true;
				}
				else
					self::$_modified = true;

				if (!method_exists($object,"index"))
					continue;
				if ($index = call_user_func(array($object,"index")))
					Database::createIndex($table,$index);
			}

			if ($error_sql_update) {
				Debug::trigger_report('critical',_("There were errors when updating your sql structure. Please check above logs for more details."));
				return false;
			}

			if(self::$_modified && !$skip_default_objects)
				foreach(self::$_models as $class => $vars) {
					$object = new $class;

					$identifier = (method_exists($object,"getDbIdentifier")) ? call_user_func(array($object,"getDbIdentifier")) : null;
					//if object doesn't have identifier consider identifier to be the first one defined in config.php (or null if not in config.php)
					if (!count($identifier) && $all_db_identifiers[0])
						$identifier = array($all_db_identifiers[0]);
					
					if ((!in_array($db_identifier, $identifier) && count($identifier)) || ($default_identifier!=$db_identifier  && !count($identifier)))
						continue;
					if(method_exists($object, "defaultObject"))
						$res = call_user_func(array($object,"defaultObject"));
				}
		}

		if ($db_identifier != $all_db_identifiers[0])
			Database::switchDatabase();
		
		return true;
	}

	/**
	 * Get the database mapped variables of a Model derived class
	 * @param $class Name of class whose variables will be described
	 * @return Array of objects of type Variable that describe the
	 *  database mapped variables of any of the @ref $class objects
	 */
	public static function getVariables($class)
	{
		Debug::func_start(__METHOD__,func_get_args(),"in_framework");

		self::init();
		$class = strtolower($class);
		if (isset(self::$_models[$class]))
			return self::$_models[$class];
		return null;
	}

	/**
	 * Get the Variable object with the name specified by $name from class $class, if valid variable name in class
	 * @param $class Name of the class
	 * @param $name Name of the variable in the object
	 * @return Object of type Variable or null if not found
	 */
	public static function getVariable($class,$name)
	{
		Debug::func_start(__METHOD__,func_get_args(),"in_framework");

		$vars = self::getVariables($class);
		if (!$vars)
			return null;
		return isset($vars[$name]) ? $vars[$name] : null;
	}

	/**
	 * Gets the variables of a certain object(including thoses that were added using the extend function)
	 * @return Array of objects of type Variable that describe the current extended object
	 */
	public function extendedVariables()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return $this->_model;
	}

	/**
	 * Returns the variable with the specified name or NULL if variable is not in the object
	 * @param $name Name of the variable
	 * @return Variable object or NULL is variable is not defined
	 */
	public function variable($name)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return isset($this->_model[$name]) ? $this->_model[$name] : null;
	}

	/**
	 * Get the name of a table corresponding to this object. Method can be overwrited from derived class when other name for the table is desired. 
	 * @return Name of table corresponding to $this object
	 */
	public function getTableName()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$class = strtolower(get_class($this));
		if(substr($class,-1) != "y")
			return $class . "s";
		else
			return substr($class,0,strlen($class)-1) . 'ies';
	}

	/**
	 * Get the name of the table associated to the given class
	 * @param $class Name of the class to get the table for
	 * @return Table name 
	 */
	public static function getClassTableName($class)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!isset(self::$_models[strtolower($class)]))
			return null;

		$obj = new $class;
		return $obj->getTableName();
	}

	/**
	 * Get an object by giving the name of the sql table
	 * @param $table Name of table in sql
	 * @return Object or NULL
	 */
	public static function getObject($table)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!$table)
			return NULL;

		foreach(self::$_models as $class=>$vars)
		{
			if(self::getClassTableName($class) == $table)
				return new $class;
		}

		return NULL;
	}

	/**
	 * Print warning if warnings where set as enabled in $_SESSION
	 * @param $warn String representing the warning
	 */
	public static function warning($warn)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(isset($_SESSION["warning_on"]))
			Debug::Output("warning",$warn);
	}

	/**
	 * Print notice if notices were enabled in $_SESSION
	 * @param $note The notice to be printed
	 */
	public static function notice($note)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(isset($_SESSION["notice_on"]))
			Debug::Output("notice",$note);
	}

	/**
	 * Checks if $name is a valid column inside the specified table
	 * @param $column_name Name of column(variable) to check
	 * @param $table Name of table
	 * @return BOOL value: true if $table is associated to an object and $column_name is a valid variable for that object, false otherwise
	 */
	protected static function inTable($column_name, $table)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!($obj = self::getObject($table)))
			return false;
		if($obj->variable($column_name))
			return true;

		return false;
	}

	/**
	 * Get the name of the variable representing the numeric id for this object
	 * @return Name of id variable or NULL if object was defined without a numeric id
	 */
	public function getIdName()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$vars = self::getVariables(get_class($this));
		foreach($vars as $name => $var)
		{
			//the id of a table can only be serial or bigserial
			if($var->_type != "serial" && $var->_type != "bigserial")
				continue;
			//if it's a foreign key to another table,we ignore that it was defined and serial or bigserial
			if($var->_key && $var->_key != '')
				continue;
			return $name;
		}
		//it might be possible that the object was defined without a numeric id
		return NULL;
	}

	/**
	 * Invalidate object. Object can't be used for generating WHERE clause for DELETE or UPDATE statements
	 */
	protected function invalidate()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		self::warning(_("Invalidating object").": ".get_class($this).".");
		$this->_invalid = true;
	}

	/**
	 * Checks to see if an object is invalid(can't be used to generated WHERE clause for DELETE or UPDATE statements)
	 * @return Bool: true is object is invalid, false otherwise
	 */
	protected function isInvalid()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return $this->_invalid;
	}

	/**
	 * Creates SELECT statement from given clauses
	 * @param $columns String representing what to select
	 * @param $from_clause String telling where to select from
	 * @param $where_clause String with conditions
	 * @param $order String/Array of pairs of field=>"ASC"/"DESC" defining order
	 * @param $limit Number representing the maximum number of fields to be selected
	 * @param $offset Number representing the offset to be used in the query
	 */
	protected static function buildSelect($columns, $from_clause=NULL, $where_clause=NULL, $order=NULL, $limit=NULL, $offset=0, $group_by = NULL, $having = NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$ord = self::makeOrderClause($order);
		$order_clause = ($ord) ? " ORDER BY $ord" : NULL;
		$limit_clause = ($limit) ? " LIMIT $limit" : NULL;
		$offset_clause = ($offset) ? " OFFSET $offset" : NULL;
		$group_by = ($group_by) ? "GROUP BY $group_by" : NULL;
		$having = ($having) ? " HAVING $having" : NULL;

		$query = "SELECT $columns FROM $from_clause $where_clause $group_by $having $order_clause $limit_clause $offset_clause";
		return $query;
	}

	/**
	 * Returns a WHERE clause for a query
	 * @param $conditions Array defining the conditions for a query
	 * Array is formed by pairs of $key=>$value. $value can also be an array
	 * Ex to buid AND : "date"=>array(">2008-07-07 00:00:00", "<2008-07-07 12:00:00") means WHERE date>'2008-07-07 00:00:00' AND date<'2008-07-07 12:00:00'
	 * EX to build OR : ("date"=>"<2008-07-07 00:00:00", "date"=>">2008-07-07 12:00:00") means WHERE date<'2008-07-07 00:00:00' OR date>'2008-07-07 12:00:00'
	 * @param $only_one_table Bool value specifing if inside the query only one table is referred
	 * Value is true when method is called from within a method that never returns extended objects.
	 * @param $without_table 
	 * @param $null_exception Enables a verification 
	 * @return Text representing the WHERE clause or '' if the count($conditions) is 0
	 */
	public function makeWhereClause($conditions, $only_one_table = false, $without_table = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$where = ' WHERE ';
		if(!is_array($conditions) || !count($conditions))
			return '';
		$obj_table = $this->getTableName();
		foreach($conditions as $key=>$value)
		{
			if ($value === NULL)
				continue;

			if ($where != " WHERE ")
				$where .= " AND ";
/*
			// old implementation. I will keep for some time
			if(is_array($value) && is_numeric($key))
				$clause = $this->buildOR($value, $obj_table, $only_one_table, $without_table);
			elseif(is_array($value)) {
				$clause = $this->buildAND($key, $value, $obj_table, $only_one_table, $without_table);
			} else
				$clause = $this->makeCondition($key, $value, $obj_table, $only_one_table, $without_table);
*/

			if (is_array($value))
				$clause = $this->buildAND_OR($key, $value, $obj_table, $only_one_table, $without_table);
			else
				$clause = $this->makeCondition($key, $value, $obj_table, $only_one_table, $without_table);

			$where .= $clause;
		}
		if($where == " WHERE ")
			return '';
		return $where;
	}

	/**
	 *	Builds AND/OR subcondition
	 * @param $key name of the column on which the conditions are set
	 * @param $value Array with the allowed values for the $key field
	 * @param $obj_table Name of the table associated to the object on which method is called
	 * @param $only_one_table Bool value specifing if inside the query only one table is referred
	 * Value is true when method is called from within a method that never returns extended objects.
	 * @param $without_table The name of the tables won't be specified in the query: Ex: we won't have table_name.column, just column
	 */
	protected function buildAND_OR($key, $value, $obj_table, $only_one_table, $without_table)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		// this is an OR or an AND
		$sql_rel = (isset($value["__sql_relation"])) ? $value["__sql_relation"] : "AND";
		if($sql_rel == "AND")
			$clause = "(".$this->buildAND($key, $value, $obj_table, $only_one_table, $without_table).")";
		else
			$clause = $this->buildOR($key, $value, $obj_table, $only_one_table, $without_table);
		return $clause;
	}

	/**
	 * Build part of a WHERE clause (conditions will be linked by AND)
	 * @param $key name of the column on which the conditions are set
	 * @param $allowed_values Array with the allowed values for the $key field
	 * @param $obj_table Name of the table associated to the object on which method is called
	 * @param $only_one_table Bool value specifing if inside the query only one table is referred
	 * Value is true when method is called from within a method that never returns extended objects.
	 * @param $without_table The name of the tables won't be specified in the query: Ex: we won't have table_name.column, just column
	 */
	protected function buildAND($key, $allowed_values, $obj_table, $only_one_table = false, $without_table = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$t_k = $this->getColumnName($key, $obj_table, $only_one_table, $without_table);

		$clause = "";
//		for($i=0; $i<count($allowed_values); $i++)
//		{
//			if($clause != "")
//				$clause .= " AND "; 
//			$clause .= $this->makeCondition($t_k, $allowed_values[$i], $obj_table, $only_one_table, true);
//		}
		foreach($allowed_values as $var_name=>$var_value)
		{
			if($var_name === "__sql_relation")
				continue;

			if($clause != "")
				$clause .= " AND "; 

			if(is_array($var_value)) {
				$clause .= $this->buildAND_OR($var_name, $var_value, $obj_table, $only_one_table, $without_table);
				continue;
			}
			elseif(is_numeric($var_name))
				$t_k = $this->getColumnName($key, $obj_table, $only_one_table, $without_table);
			else
				$t_k = $this->getColumnName($var_name, $obj_table, $only_one_table, $without_table);

			$clause .= $this->makeCondition($t_k, $var_value, $obj_table, $only_one_table, true);
		}
		return $clause;
	}

	/**
	 * Build part of a WHERE clause (conditions will be linked by AND)
	 * @param $conditions Array of type $key=>$value representing the clauses that will be separated by OR
	 * @param $obj_table Name of the table associated to the object on which method is called
	 * @param $only_one_table Bool value specifing if inside the query only one table is referred
	 * Value is true when method is called from within a method that never returns extended objects.
	 * @param $without_table The name of the tables won't be specified in the query: Ex: we won't have table_name.column, just column
	 */
	protected function buildOR($key, $conditions, $obj_table, $only_one_table = false, $without_table = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$clause = "";
		foreach($conditions as $column_name=>$value)
		{
			if($column_name === "__sql_relation")
				continue;
			if($clause != "")
				$clause .= " OR "; 
			if(is_array($value)) {
				$clause .= $this->buildAND_OR($column_name, $value, $obj_table, $only_one_table, $without_table);
				continue;
			}
			if(is_numeric($column_name))
				$t_k = $this->getColumnName($key, $obj_table, $only_one_table, $without_table);
			else
				$t_k = $this->getColumnName($column_name, $obj_table, $only_one_table, $without_table);
			$clause .= $this->makeCondition($t_k, $value, $obj_table, $only_one_table, true);
		}
		return " (" . $clause. ") ";
	}

	/**
	 * Return the name of a column in form "table_name"."column" that will be used inside a query
	 * @param $key Name of the column
	 * @param $obj_table Table associated to $this object
	 * @param $only_one_table Bool value specifing if inside the query only one table is referred(if true, "table_name" won't be added)
	 * @param $without_table Bool value, if true "table_name" won't be specified automatically (it might be that it was already specified in the $key)
	 */
	protected function getColumnName($key, $obj_table, $only_one_table, $without_table)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!$without_table) 
		{
			// If $key starts with "__sql_" then use of function inside the clause is allowed.
			// Example: $key can be date(tablename.timestamp_field) or length(tablename.text_field)
			// Developer has the responsibility to add the name of the table if necessary and to add "" 
			// in case reserved words in PostgreSQL were used as column names or table names
			if (substr($key,0,6) == "__sql_")
				$t_k = substr($key,6,strlen($key));
			else
			{ 
				$look_for_other_table = true;
				//if we use only one table and $key is a variable inside this object
				if($only_one_table)
				{
					$var = self::getVariable(get_class($this), $key);
					//this condition should always be valid, if methods were used in the right way
					//if condition won't be verified because this object was extended and a method for objects that 
					//were not extended was called WHERE clause will be correct but query will most likely fail in
					//the FROM section
					if($var)
					{
						$table = $obj_table;
						$look_for_other_table = false;
					}
				}
				if($look_for_other_table)
				{
					$var = $this->_model[$key];
					$table = $var->_key;
					$matchkey = $var->_matchkey;
					//if matchkey is not specified 
					if(!$table || $table == '')
						$table = $obj_table;
					if(!Model::getVariable(get_class($this),$key)) {

					/*  ex: status field is in the both classes and i put condition on the field that was inserted with method extend*/
					if($table != $obj_table && $matchkey)
						$key = $matchkey;
					}else
						$table = $obj_table;
				}
				$t_k = esc($table).".".esc($key);
			}
		}else{
			if (substr($key,0,6) == "__sql_")
				$t_k = substr($key,6,strlen($key));
			else
				$t_k = "$key";
		}

		return $t_k;
	}

	/**
	 * Build a condition like table_name.column='$value' or table_name.column>'$value'
	 * @param $key Represents the table_name.column part of the condition
	 * @param $value String representing the operator and the value, or just the value when then default operator = will be used
	 * @param $obj_table Table associated to $this object
	 * @param $only_one_table Bool value specifing if inside the query only one table is referred(if true, "table_name" won't be added)
	 * @param $without_table Bool value, if true "table_name" won't be specified automatically (it might be that it was already specified in the $key)
	 */
	protected function makeCondition($key, $value, $obj_table, $only_one_table = false, $without_table = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$t_k = $this->getColumnName($key, $obj_table, $only_one_table, $without_table);
		// Arrays of operators that should be put at the beggining in $value 
		// If none of this operators is used and $value does not have a special value then 
		// the default operator is =
		//Added "<>" MySQL Operator into $two_dig_operators array
		$two_dig_operators = array("<=",">=","!=", "<>");
		$one_dig_operators = array(">","<","=");

		$first_two = substr($value,0,2);
		$first_one = substr($value,0,1);
		$clause = '';

		if ($value === false)
			$clause .= " $t_k".Database::boolCondition($value)." ";
		elseif($value === true)
			$clause .= " $t_k".Database::boolCondition($value)."  ";
		elseif($value === "__empty")
			$clause .= " $t_k IS NULL ";
		elseif($value === "__non_empty" || $value === "__not_empty")
			$clause .= " $t_k IS NOT NULL ";
		elseif(in_array($first_two, $two_dig_operators)){
			$value = substr($value,2,strlen($value));
			if (substr($value,0,6) == "__sql_")
			{
				// If $value starts with "sql_" then $value is not actually a value but 
				// refers to a column from a table
				$value = substr($value, 6, strlen($value));
				$clause .= " $t_k" . $first_two . "$value ";
			} elseif ($first_two == "!=" ||  $first_two == "<>") {
				//If the where operator is "!=" or "<>" => it will be used  NOT table.column <=> value.
				$value = Database::escape($value);
				$null_safe_equal_operator = "<=>";
				$clause .= " NOT $t_k" . $null_safe_equal_operator . "'$value' ";
			}else{
				$value = Database::escape($value);
				$clause .= " $t_k" . $first_two . "'$value' ";
			}
		}elseif (in_array($first_one, $one_dig_operators)) {
			$value = substr($value,1,strlen($value));
			if (substr($value,0,6) == "__sql_")
			{
				$value = substr($value, 6, strlen($value));
				$clause .= " $t_k" . $first_one . "$value ";
			}else{
				$value = Database::escape($value);
				$clause .= " $t_k" . $first_one . "'$value' ";
			}
		}elseif (substr($value,0,6) == "__LIKE") {
			$value = Database::escape(substr($value,6,strlen($value)));
			if (substr($value,0,1) != '%' && substr($value,-1) != '%')
				$clause .= " $t_k ".Database::likeOperator()." '$value%' ";
			else
				$clause .= " $t_k ".Database::likeOperator()." '$value' ";
		}elseif (substr($value,0,10) == "__NOT LIKE") {
			$value = Database::escape(substr($value,10,strlen($value)));
			if (substr($value,0,1) != '%' && substr($value,-1) != '%')
				$clause .= " $t_k NOT ".Database::likeOperator()." '$value%' ";
			else
				$clause .= " $t_k NOT ".Database::likeOperator()." '$value' ";
		}elseif(substr($value,0,6) == "__sql_") {
			$value = substr($value,6,strlen($value));
			$clause .= " $t_k=$value";
		}else{
			if ($value != '' && strlen($value))
				$clause .= " $t_k='".Database::escape($value)."'";
			else
				// it should never get here
				// if verification for NULL is needed set $value = '__empty' 
				$clause .= " $t_k is NULL";
		}
		return $clause;
	}

	/**
	 * Creates an ORDER clause
	 * @param $order Array for building clause array("name"=>"DESC", "created_on"=>"ASC") or String with 
	 * clause already inserted "name DESC, created_on"
	 * string can also be "rand()", for getting the results in random order
	 * @return ORDER clause 
	 */
	protected static function makeOrderClause($order)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		// When writing the String one must pay attention to use "" for fields and tables that are in
		// the special words in PostgreSQL
		if (!is_array($order))
			return $order;
		if (!count($order))
			return;
		$clause = '';
		foreach($order as $key=>$value) 
		{
			if ($clause != '')
				$clause .= ',';
			if ($value == "DESC")
			{
				if (substr($key,0,1) == Database::escape_string())
					$clause .= " $key $value";
				else
					$clause .= " ".esc($key)." $value";
			}else{
				if (substr($key,0,1) == Database::escape_string())
					$clause .= " $key";
				else
					$clause .= " ".esc($key);
			}
		}
		return $clause;
	}

	/**
	 * Adding an inner query to a WHERE clause
	 * @param $inner_query Array of params for defining the inner query
	 * Ex: array("column"=>"user_id", "relation"=>"IN"/"NOT IN","table"=>"users","inner_table"=>"banned_users", "inner_column"=>"user_id", "conditions"=>array("reason"=>"abuse"))
	 * inner_column can be omitted if it's the same as 'column'. Ex: array("column"=>"user_id", "relation"=>"IN"/"NOT IN","table"=>"users","inner_table"=>"banned_users", "inner_column"=>"user_id", "conditions"=>array("reason"=>"abuse"))
	 * You can specify the inner values directly: Ex: array("column"=>"user_id", "relation"=>"IN"/"NOT IN","table"=>"users", "options"=>"1,2,3,4")
	 * @param $table Table to use for the column on which the inner query is applied
	 * @param $where Clause to append to 
	 * @return WHERE clause
	 */
	public function makeInnerQuery($inner_query=array(), $table = NULL, $where='')
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		if(!is_array($inner_query) || !count($inner_query))
			return $where;

		if(!$table || $table == '')
			$table = $this->getTableName();

		// Verifying the compulsory $keys 
		$compulsory = array("column", "relation");
		$error = '';
		for($i=0; $i<count($compulsory); $i++)
		{
			if (!isset($inner_query[$compulsory[$i]]))
				$error .= _("Field").' '.$compulsory[$i].' '._("is not defined").". ";
		}
		if ($error != '') {
			Debug::trigger_report('critical', $error);
			exit($error);
		}

		if ($where == '')
			$where = ' WHERE ';
		else
			$where .= ' AND ';

		if (isset($inner_query['table']))
			$table = $inner_query['table'];
		$column = $inner_query["column"];
		$relation = $inner_query["relation"];
		$outer_column = $this->getColumnName($column,$table,false,false);
		if (!array_key_exists("options",$inner_query)) {
			if (!isset($inner_query["other_table"]) && !isset($inner_query["inner_table"])) {
				Debug::trigger_report("critical", _("You must either insert")." 'other_table' "._("or")." 'inner_table'. ".'$inner_query='.print_r($inner_query,true));
				return $where;
			}

			$inner_table = (isset($inner_query["inner_table"])) ? $inner_query["inner_table"] : $inner_query["other_table"];
			$inner_column = (isset($inner_query["inner_column"])) ? $inner_query["inner_column"] : $inner_query["column"];
			$inner_column = $this->getColumnName($inner_column, $inner_table, false, true);
			$where .= " $outer_column $relation (SELECT $inner_column from ".esc($inner_table)." ";
			$inner_where = '';

			if(!($obj = self::getObject($inner_table))) {
				Debug::trigger_report('critical', "Quit when wanting to create object from table $inner_table");

				exit(_("Quit when wanting to create object from table")." $inner_table");
			}

			if(isset($inner_query["conditions"]))
				$inner_where .= $obj->makeWhereClause($inner_query["conditions"],true);

			if(isset($inner_query["inner_query"]))
				$inner_where .=$obj->makeInnerQuery($inner_query["inner_query"]);

			$group_by = (isset($inner_query['group_by'])) ? 'group by '.$inner_query['group_by'] : '';
			$having = (isset($inner_query['having'])) ? 'having '.$inner_query['having'] : '';
			$where .= $inner_where ." $group_by $having )";
		} elseif (isset($inner_query["options"]) && strlen($inner_query["options"]))
			$where .= " $outer_column $relation (".$inner_query["options"].")";
		else {
			// $inner_query["options"] is null
			if (strtolower($relation)=="in")
				$where .= " false";
			else
				$where .= " true";
		}
		
		return $where;
	}

	/**
	 * Populates the variables of $this object with the fields from a query result
	 * @param $result Query result
	 */
	protected function populateObject($result)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		global $db_type;
		global $debug_old_data;

		if(count($result) != 1)
		{
			Debug::trigger_report('critical', _("Trying to build single object from sql that has")." ".count($result)." "._("rows")."." ._("Invalidating object").".");
			$this->invalidate();
			return;
		}
		$this->_retrieved = true;
		$allow_html  = $this->allowHTML();
		foreach($result[0] as $var_name=>$value) {
			$var = $this->variable($var_name);
			if (!$var || get_class($var)!="Variable") {
				if (isset($debug_old_data))
					Debug::trigger_report ("critical", "populateObject - Class of var '$var_name' is ".get_class($var)." not Variable, value='$value'");
				continue;
			}
			
			if ($db_type == "postgresql" && $var && $var->_type == "bool") {
				if ($value == "1")
				    $value = "t";
				elseif ($value == "0")
				    $value = "f";
			}
			Model::setValueType($value, $var);			
			$this->{$var_name} = $value;
			if(in_array($var_name, $allow_html))
				$this->{$var_name} = html_entity_decode($this->{$var_name});
		}
	}
	
	protected static function setValueType(&$value, $var)
	{
		global $enforce_out_types;
		global $debug_old_data;

		if (!isset($enforce_out_types) || !$enforce_out_types)
			return;
		
		if (!$var || get_class($var)!="Variable") {
			if (isset($debug_old_data))
				Debug::trigger_report ("critical", "setValueType - Class of var is ".get_class($var).", not Variable, value='$value'");
			$value = NULL;
			return;
		}
		
		if ($var->_type == "bool") {
			$value = Database::boolValue($value);
		} elseif ($var->numericType()) {
			if (strlen($value))
				$value = 1 * $value;
			else
				$value = NULL;
		} else
			$value = "$value";
	}

	/**
	 * Builds an array of objects that have the same variables as $this object from a result of a query
	 * @param $result Query result to build objects from
	 * @return Array of objects
	 */
	protected function buildArrayOfObjects($result)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");
		global $db_type;
		global $debug_old_data;

		if(!count($result))
			return array();
		$objects = array();
		$allow_html  = $this->allowHTML();
		//get the name of the class of $this object
		$class_name = get_class($this);
		$i = 0;
		for ($i=0; $i<count($result); $i++) {
			// create a clone of $this object, not just having the same class, but also the same variables
			// (in case $this object was extended previously)
			$row = $result[$i];
			$clone = new $class_name;
			$clone->_model = $this->_model;
			foreach ($row as $var_name=>$value) {
				$var = $clone->variable($var_name);
				if (!$var || get_class($var)!="Variable") {
					if (isset($debug_old_data))
						Debug::trigger_report ("critical", "buildArrayOfObjects - Class of var '$var_name' is ".get_class($var)." not Variable, value='$value'");
					continue;
				}
				
				if ($db_type == "postgresql" && $var && $var->_type == "bool") {
					if ($value == "1")
					    $value = "t";
					elseif ($value == "0")
					    $value = "f";
				}
				Model::setValueType($value, $var);
				$clone->{$var_name} = $value;
				if(in_array($var_name, $allow_html))
					$clone->{$var_name} = html_entity_decode($clone->{$var_name});
			}
			$objects[$i] = $clone;
			$objects[$i]->_retrieved = true;
		}
		return $objects;
	}

	/**
	 * Specify the name of the columns that allow html to be stored (HTML can be stored, but it be passed thought htmlentities when setting the values of the columns)
	 * This function must be reimplemented in each object that allows this type of columns
	 * @return Array contining the name of the columns or empty array
	 */
	protected function allowHTML()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return array();
	}

	/**
	 *	Build a string from all the variables of the objects: "var1=value1 var2=value2 ..."
	 * @param $prefix String representing a prefix that will be added in front of every var: "$prefix"."var1=value1 "."$prefix"."var2=value2 ..."
	 * @param $skip Array with name of variables to be skipped when building the string
	 * @return String of type:  "var1=value1 var2=value2 ..."
	 */
	public function toString($prefix = '', $skip=array())
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$model = $this->_model;
		$str = "";
		foreach($model as $var_name=>$var)
		{
			if(in_array($var_name, $skip))
				continue;
			if($str != "")
				$str .= " ";
			$name = ($prefix != '') ? $prefix.".".$var_name : $var_name;
			$str .= "$name=".$this->{$var_name};
		}
		return $str;
	}

	/**
	 *	Get name of this object
	 * @return String representing the name of the object. If all letters in class name are uppercase then they are returned the same, else all letters are lowercased
	 */
	public function getObjectName()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$class_name = get_class($this);
		if(strtoupper($class_name) == $class_name)
			return str_replace("_"," ",$class_name);
		else
			return strtolower(str_replace("_"," ",$class_name));
	}

	/**
	 * Escape spaces from given parameter $val and remove new lines
	 * @param $val Value to escape
	 * @return String representing the escaped value
	 */
	public static function escapeSpace($val)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		$val = str_replace("\n", "", $val); //make sure new lines are not accepted
		return str_replace(" ", "\ ", $val);
	}

	/**
	 * Write a log entry in the database coresponding to a certain operation
	 * Note!! Only insert, delete, update queries are logged
	 * Other operations should be implemented in the classes or directly in the code
	 * The actual log writting is implemented in class ActionLog. If this class is not present logs are not written
	 */
	static function writeLog($log, $query = NULL)
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $enable_logging;

		if($enable_logging !== true && $enable_logging != "yes" && $enable_logging != "on")
			return;

		if(!self::getVariables("actionlog"))
			return;

		// if class ActionLog is present trying writting log
		ActionLog::writeLog($log, $query);
	}

	/**
	 * Verify if an object is a performer or not
	 * This function should be reimplemented in the classes that you wish to mark as performers
	 * Example: for class User, function should return array("performer_id"=>"user_id", "performer"=>"username"), "performer_id" and "performer" are constants and their values will be taken from the coresponding variables kept in $_SESSION: user_id and "username"
	 * @return Bool false
	 */
	protected function isPerformer()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return false;
	}

	/**
	 * Get the name of the object that should be used when writing logs
	 * This function returns the class of the object. If other name is desired one should reimplement it in the derived classes
	 * @return Name to be used when writing logs for this object 
	 */
	public function getNameInLogs()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		return get_class($this);
	}

	/** 
	 * Search object by $col_name=>$col_value and return index in object array
	 * @param $objects Array of objects -- typically returned from selection/extendedSelect
	 * @param $col_name String Name of the column the search is done for
	 * @param $col_value String Value of @col_name to search for
	 * @return Integer - index of the object or NULL if not found 
	 */
	public function getObjectIndex($objects, $col_name, $col_value)
	{
		foreach ($objects as $i=>$obj)
			if ($obj->{$col_name}==$col_value)
				return $i;
		return false;
	}

	/**
	 * Get the engine to use when creting mysql table
	 * Setting should be made in $db_engine in config.php
	 * Default engine is innodb. If engine without transaction support is used, then transaction(), rollback() and commit() should not be used
	 * Function can be reimplemented in classes to use different database engine if desired
	 */
	public function getDbEngine()
	{
		Debug::func_start(__METHOD__,func_get_args(),"framework");

		global $db_engine;

		if (isset($db_engine))
			return $db_engine;
		return "innodb";
	}


	/**
	 * Concatenates values from @param $options so they can be used as 'options' when building an inner query
	 * @param $options Array. Contains values that will be concatenated
	 * @return String
	 * Ex: array(1,2) => "'1','2'"
	 */
	public static function optionsInnerQuery($options)
	{
		if (!is_array($options) || !count($options))
			return "";

		$options = implode("','",$options);
		return "'".$options."'";
	}
	
	public function isPopulated()
	{
		if ($this->_set || $this->_retrieved) 
			return true;
		return false;
	}
}

?>

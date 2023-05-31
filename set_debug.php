<?php
/**
 * set_debug.php
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

if(is_file("defaults.php"))
	require_once("defaults.php");
elseif(is_file("../defaults.php"))
	require_once("../defaults.php");

if(is_file("config.php"))
	require_once("config.php");
elseif(is_file("../config.php"))
	require_once("../config.php");

//Set $session_lifetime, $session_path from defaults.php
//Also it can be set/override from config.php
global $session_lifetime;
global $session_path;

if(!isset($session_lifetime) || !strlen($session_lifetime))
	$session_lifetime = null;
if(!isset($session_path) || !strlen($session_path))
	$session_path = null;

if($session_lifetime && $session_path && php_sapi_name() !== "cli") {
	// Set the max lifetime
	ini_set("session.gc_maxlifetime", $session_lifetime);

	// Set the session cookie to timout
	ini_set("session.cookie_lifetime", $session_lifetime);
	
	// Change the save path. Sessions stored in the same path
	// all share the same lifetime; the lowest lifetime will be
	// used for all. Therefore, for this to work, the session
	// must be stored in a directory where only sessions sharing
	// it's lifetime are. Best to just dynamically create on.
	if(!file_exists($session_path))
		@mkdir($session_path);

	//If the new path exists, set it, otherwise PHP will use the default session.save_path
	if(is_dir($session_path) && is_writable($session_path))
		ini_set("session.save_path", $session_path);
	else {
		//Log warning only in index.php, not everytime
		if(isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'index.php'))
			error_log("PHP Warning: Failed to extend PHP session. Use default session_path: ".ini_get("session.save_path"));
	}
	
	// Set the chance to trigger the garbage collection.
	ini_set("session.gc_probability", 1);
	ini_set("session.gc_divisor", 100); // Should always be 100

	// Renew the time left until this session times out.
	// If you skip this, the session will time out based
	// on the time when it was created, rather than when
	// it was last used.
	if(isset($_COOKIE[session_name()])) {
		setcookie(session_name(), $_COOKIE[session_name()], time() + $session_lifetime);
	}
}

session_start();

if(isset($_SESSION["error_reporting"]))
	ini_set("error_reporting",$_SESSION["error_reporting"]);

if(isset($_SESSION["display_errors"]))
	ini_set("display_errors",true);
//if this was set then errors will be hidden unless set from debug_all.php page
//else
//	ini_set("display_errors",false);

if(isset($_SESSION["log_errors"]))
	ini_set("log_errors",$_SESSION["log_errors"]);
//if this was set then errors will be logged unless set from debug_all.php page
//else
//	ini_set("log_errors",false);

if(isset($_SESSION["error_log"]))
	ini_set("error_log", $_SESSION["error_log"]);

?>

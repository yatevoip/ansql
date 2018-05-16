<?php
/* 
 * lib_plugins.php
 * This file is part of the Yate-LMI Project http://www.yatebts.com
 *
 * Copyright (C) 2016 Null Team
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


class Plugin 
{
	/**
	* Includes plugins located in plugins/ directory 
	* Function should be called between including defaults.php & config.php like Plugin::includePlugins(), because some plugins might modify global variables from defaults.php
	*/
	public static function includePlugins()
	{
		if (!is_dir("plugins"))
			return;
	
		$handle = opendir("plugins");

		while (false !== ($file = readdir($handle))) {
			if (substr($file,-4) != '.php')
				continue;
			else {
				$plugin_name = explode("_", $file);
				$class = $plugin_name[0];
				require_once("plugins/".$file);
				//loaded_plugin($plugin_name[0]);
				if (class_exists($class)) {
					$obj = new $class;
					$cb = array($obj,"registerHooks");
					if (is_callable($cb))
						call_user_func($cb);
				}
			}
		}
	}
	
	/**
	* Includes javascript for plugins located in plugins/ directory 
	* Function should be called between HTML tags <head> and </head>
	*/
	public static function includeJSPlugins()
	{
		if (!is_dir("plugins"))
			return;
	
		$handle = opendir("plugins");

		while (false !== ($file = readdir($handle))) {
			if (substr($file,-4) != '.php')
				continue;
			else {
				$full_name = explode("_", $file);
				$class = $full_name[0];
				if (class_exists($class)) {
					$obj = new $class;
					$cb = array($obj,$obj->_js);
					if (isset($obj->_js) && is_callable($cb))
						call_user_func($cb);
				}
			}
		}
	}
	
	/**
	 * Register hook $hook_handler to be called for hook name $hook_name. If unicity tags are specified then system will return an error in case 
	 * @global type $plugin_hooks
	 * @param String $hook_name
	 * @param Callback $hook_handler string
	 * @param String $plugin_name
	 * @param Array $unicity_tags
	 * @param Bool $class, if is true then $hook_handler will be a method name, else will be a function name
	 */
	public static function registerHook($hook_name, $hook_handler, $plugin_name=null, $unicity_tags=array(), $class=false)
	{
		global $plugin_hooks;
		global $plugin_errors;
		
		if (!isset($plugin_hooks))
			$plugin_hooks = array();

		if (!array_key_exists($hook_name, $plugin_hooks))
			$plugin_hooks[$hook_name] = array(
			    "unique_tags" => array(),
			    "hooks"       => array()
			);

		foreach ($unicity_tags as $tag) {
			if (isset($plugin_hooks[$hook_name]["unique_tags"][$tag]))
				$plugin_errors[] = "Invalid plugin combination '$plugin_name' - '".$plugin_hooks[$hook_name]["unique_tags"][$tag]."' ($tag). Problems registering hook '$hook_handler' on '$hook_name' for plugin '$plugin_name'.";
		}
		
		if ($class) {
			$obj_class = get_called_class();
			$obj = new $obj_class;
			$cb = array($obj,$hook_handler);
		} else
			$cb = $hook_handler;
		
		if (!is_callable($cb)) {
			$plugin_errors[] = "Hook handler is not callable: $hook_handler.";
			return;
		}

		$plugin_hooks[$hook_name]["hooks"][] = $cb;
	}
	
	/**
	 * Wrapper function for registerHook
	 * Register hook $hook_handler to be called for hook name $hook_name where $hook_handler will be a class method
	 */
	public static function registerHookClass($hook_name, $hook_handler, $unicity_tags=array())
	{
		$plugin_name = get_called_class();
		self::registerHook($hook_name, $hook_handler, $plugin_name, $unicity_tags, true);
	}
	
	/**
	 * Call registered hooks for specific hook name
	 * @global type $plugin_hooks
	 * @param type $hook_name
	 */	
	public static function callHooks($hook_name, $params=array())
	{
		global $plugin_hooks;

		if (isset($plugin_hooks[$hook_name]["hooks"])) {
			foreach ($plugin_hooks[$hook_name]["hooks"] as $cb_hook) {
				if (is_callable($cb_hook))
					call_user_func_array($cb_hook,$params);
				else {
					Debug::trigger_report("critical", "Tried to call hook '$cb_hook', but it's not callable.");
				}
			}
		}
	}
}
	
/**
 * Includes plugins located in plugins/ directory 
 * Function should be called between including defaults.php & config.php, because some plugins might modify global variables from defaults.php
 */

function include_plugins()
{
	Plugin::includePlugins();
}

/**
 * Includes javascript for plugins located in plugins/ directory 
 * Function should be called between html tags <head> && </head>
 */
function include_js_plugins()
{
	Plugin::includeJSPlugins();
}

function register_hook($hook_name, $hook_handler, $plugin_name=NULL, $unicity_tags=array())
{
	Plugin::registerHook($hook_name, $hook_handler, $plugin_name, $unicity_tags);
}

function call_hooks($hook_name)
{
	Plugin::callHooks($hook_name);
}

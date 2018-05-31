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
require_once ("ansql/debug.php");

abstract class Plugin 
{
	static $plugin_classes = array();
	
	/*
	 Example of defining $hooks	
	 protected $_hooks = array(
		array(
		    "name"         => "predefined_network_settings", 
		    "callback"     => "secMccMnc",
		    "unicity_tags" => array("mcc,mnc")
		),
		array(
		    "name"         => "global_sim_options" ,
		    "callback"     => "simProgrammerLink"
		)
	 );
	 */
	
	/**
  	 * Includes plugins located in plugins/ directory 
	 * Function should be called between including defaults.php & config.php like Plugin::includePlugins(), because some plugins might modify global variables from defaults.php
	 * When plugins are included hooks for them are automatically registered, you won't need to register hooks manually
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
				require_once("plugins/".$file);	
			}
		}
		
		$classes = get_declared_classes();
		foreach ($classes as $class) {
			if (self::checkAncestors($class)) {
				self::$plugin_classes[] = $class;
				$obj = new $class;
				$cb = array($obj,"registerHooks");
				if (is_callable($cb))
					call_user_func($cb);
			}
		}
	}
	
	/**
	 * Includes javascript for plugins located in plugins/ directory 
	 * Function should be called between HTML tags <head> and </head>
	 * A plugin must implement "includeJs" method in which it outputs the desired js
	 */
	public static function includeJSPlugins()
	{
		if (!count(self::$plugin_classes))
			return;

		foreach (self::$plugin_classes as $class) {
			$obj = new $class;
			$cb = array($obj,"includeJs");
			if (is_callable($cb))
				call_user_func($cb);
		}
	}

	/**
	 * Includes css for plugins located in plugins/ directory 
	 * Function should be called between HTML tags <head> and </head>
	 * A plugin must implement "includeCSS" method in which it outputs the desired css
	 */
	public static function includeCSSPlugins()
	{
		if (!count(self::$plugin_classes))
			return;

		foreach (self::$plugin_classes as $class) {
			$obj = new $class;
			$cb = array($obj,"includeCSS");
			if (is_callable($cb))
				call_user_func($cb);
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
	public static function registerHook($hook_name, $hook_handler, $unicity_tags=array(), $plugin_name=null)
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
		
		$obj_class = get_called_class();
		if (!$plugin_name)
			$plugin_name = $obj_class;

		if (is_array($unicity_tags)) {
			foreach ($unicity_tags as $tag) {
				if (isset($plugin_hooks[$hook_name]["unique_tags"][$tag]))
					$plugin_errors[] = "Invalid plugin combination '$plugin_name' - '".$plugin_hooks[$hook_name]["unique_tags"][$tag]."' ($tag). Problems registering hook '$hook_handler' on '$hook_name' for plugin '$plugin_name'.";
			}
		}
		
		if ($obj_class!="Plugin") {
			$obj = new $obj_class;
			$cb = array($obj,$hook_handler);
		} else {
			$cb = $hook_handler;
		}

		if (!is_callable($cb)) {
			$plugin_errors[] = "Hook handler is not callable: $hook_handler.";
			return;
		}

		$plugin_hooks[$hook_name]["hooks"][] = $cb;
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
	
	/**
	 * Register all hooks for the class. This is done automatically when plugins are included, it should not be called individually
	 */
	protected function registerHooks()
	{
		if (!isset($this->_hooks) || !is_array($this->_hooks))
			return;
		
		foreach($this->_hooks as $hook ) {
			$unicity_tags = (isset($hook["unicity_tags"])) ? $hook["unicity_tags"] : null;
			self::registerHook($hook["name"], $hook["callback"], $unicity_tags);
		}
	}
	
	/**
	 * Check class ancestors to see if one of them is Plugin 
	 * @param $class String
	 * @return Bool
	 */
	protected static function checkAncestors($class)
	{
		$parent = get_parent_class($class);
		if ($parent=="Plugin")
			return true;
		elseif (!$parent)
			return false;
		return self::checkAncestors($parent);
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
	Plugin::registerHook($hook_name, $hook_handler, $unicity_tags, $plugin_name);
}

function call_hooks($hook_name)
{
	Plugin::callHooks($hook_name);
}
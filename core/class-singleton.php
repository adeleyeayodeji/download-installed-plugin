<?php

/**
 * File Description:
 * Singleton abstract class to be inherited by other classes
 *
 * @link    https://www.biggidroid.com/
 * @since   1.0.0
 *
 * @author  Biggidroid (https://www.biggidroid.com)
 * @package Download_Installed_Plugin
 *
 * @copyright (c) 2024, Biggidroid (https://www.biggidroid.com)
 */

namespace Download_Installed_Plugin;

// Abort if called directly.
defined('WPINC') || die;

/**
 * Class Singleton
 *
 * @package Download_Installed_Plugin
 */
abstract class Singleton
{

	/**
	 * Singleton constructor.
	 *
	 * Protect the class from being initiated multiple times.
	 *
	 * @param array $props Optional properties array.
	 *
	 * @since 1.0.0
	 */
	protected function __construct($props = array())
	{
		// Protect class from being initiated multiple times.
	}

	/**
	 * Instance obtaining method.
	 *
	 * @return static Called class instance.
	 * @since 1.0.0
	 */
	public static function instance()
	{
		static $instances = array();

		// @codingStandardsIgnoreLine Plugin-backported
		$called_class_name = get_called_class();

		if (! isset($instances[$called_class_name])) {
			$instances[$called_class_name] = new $called_class_name();
		}

		return $instances[$called_class_name];
	}
}
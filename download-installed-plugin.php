<?php

/**
 * Plugin Name:     Download Installed Plugin
 * Plugin URI:      https://www.biggidroid.com/
 * Description:     Download Installed Plugin
 * Author:          Biggidroid
 * Author URI:      https://www.biggidroid.com/
 * Text Domain:     download-installed-plugin
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Download_Installed_Plugin
 */

//check for security
if (!defined('ABSPATH')) {
	exit("You can't access file directly");
}

//define constants
define('DOWNLOAD_INSTALLED_PLUGIN_VERSION', time());
define('DOWNLOAD_INSTALLED_PLUGIN_FILE', __FILE__);
define('DOWNLOAD_INSTALLED_PLUGIN_DIR', __DIR__);
define('DOWNLOAD_INSTALLED_PLUGIN_URL', plugin_dir_url(__FILE__));

//load the plugin
require_once __DIR__ . '/vendor/autoload.php';

//init the plugin
Download_Installed_Plugin\Loader::instance();

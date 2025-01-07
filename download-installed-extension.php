<?php

/**
 * Plugin Name:     BiggiDroid Backup and Restore
 * Plugin URI:      https://www.biggidroid.com/
 * Description:     Easily back up and and restore your wordpress site easily along with downloading plugins and themes as zip files.
 * Author:          Adeleye Ayodeji
 * Author URI:      https://www.adeleyeayodeji.com/
 * Text Domain:     download-installed-extension
 * Version:         0.1.0
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Download_Installed_Extension
 */

//check for security
if (!defined('ABSPATH')) {
	exit("You can't access file directly");
}

//define constants
define('DOWNLOAD_INSTALLED_EXTENSION_VERSION', time());
define('DOWNLOAD_INSTALLED_EXTENSION_FILE', __FILE__);
define('DOWNLOAD_INSTALLED_EXTENSION_DIR', __DIR__);
define('DOWNLOAD_INSTALLED_EXTENSION_URL', plugin_dir_url(__FILE__));

//load the plugin
require_once __DIR__ . '/vendor/autoload.php';

//init the plugin
Download_Installed_Extension\Loader::instance();

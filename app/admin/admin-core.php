<?php

/**
 * Admin Core
 *
 * @package Download Installed Plugin
 */

namespace Download_Installed_Plugin\Admin;

use Download_Installed_Plugin\Base;

/**
 * Class Admin_Core
 *
 * @package Download_Installed_Plugin\Admin
 */
class Admin_Core extends Base
{
	/**
	 * Init
	 *
	 * @return void
	 */
	public function init()
	{
		//add column to plugins page
		add_filter('manage_plugins_columns', array($this, 'add_download_column'));
		add_action('manage_plugins_custom_column', array($this, 'render_download_column'), 10, 2);
	}

	/**
	 * Add download column to plugins page
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_download_column($columns)
	{
		$columns['download-installed-plugin'] = __('Download');
		return $columns;
	}

	/**
	 * Render download column
	 *
	 * @param string $column_name
	 * @param int $plugin_file
	 * @return void
	 */
	public function render_download_column($column_name, $plugin_file)
	{
		if ($column_name === 'download-installed-plugin') {
			echo '<a href="' . esc_url(admin_url('plugins.php?action=download-installed-plugin&plugin=' . $plugin_file)) . '" class="button button-primary download-installed-plugin">' . __('Download') . '</a>';
		}
	}
}

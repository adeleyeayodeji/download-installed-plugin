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
		//add script to plugins page
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		//add ajax action
		add_action('wp_ajax_ade_download_installed_plugin', array($this, 'validate_installed_plugin'));
		//api route
		add_action('rest_api_init', array($this, 'api_routes'));
	}

	/**
	 * API routes
	 *
	 * @return void
	 */
	public function api_routes()
	{
		register_rest_route('ade-download-installed-plugin/v1', '/download-plugin', array(
			'methods' => 'GET',
			'callback' => array($this, 'download_installed_plugin_as_zip'),
			'permission_callback' => array($this, 'download_installed_plugin_permission'),
		));
	}

	/**
	 * Get download link
	 *
	 * @param string $plugin_file
	 * @return string
	 */
	public function get_download_link($plugin_file)
	{
		return rest_url('ade-download-installed-plugin/v1/download-plugin?plugin_file=' . $plugin_file);
	}

	/**
	 * Permission callback
	 *
	 * @return bool
	 */
	public function download_installed_plugin_permission()
	{
		//return true for now
		return true;
	}

	/**
	 * Download installed plugin as zip
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function download_installed_plugin_as_zip(\WP_REST_Request $request)
	{
		try {
			//get the plugin file
			$plugin_file = $request->get_param('plugin_file');
			//check if the plugin file exists
			if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
				throw new \Exception('Plugin file not found, please try again.');
			}
			//check if the session is started
			if (session_status() === PHP_SESSION_NONE) {
				session_start();
			}
			//validate the plugin file nonce
			if (!isset($_SESSION['ade_download_installed_plugin']['plugin_file_nonce']) || $_SESSION['ade_download_installed_plugin']['plugin_file_nonce'] !== md5($plugin_file)) {
				throw new \Exception('Invalid plugin file nonce, please try again.');
			}
			//download the plugin
			$this->download_plugin_file($plugin_file);
			return rest_ensure_response(array('message' => 'Plugin file downloaded successfully.'));
		} catch (\Exception $e) {
			//log the error
			error_log("Download Installed Plugin Error: " . $e->getMessage());
			//return error
			return new \WP_Error('error', $e->getMessage());
		}
	}

	/**
	 * Download plugin file
	 *
	 * @param string $plugin_file
	 * @return void
	 */
	public function download_plugin_file($plugin_file)
	{
		// Get the plugin directory path
		$plugin_dir_path = WP_PLUGIN_DIR . '/' . dirname($plugin_file);

		// Create a temporary zip file
		$zip_file = explode('/', $plugin_file);
		//get the last element of the array
		$zip_file = $zip_file[count($zip_file) - 1] . '.zip';

		// Initialize the ZipArchive class
		$zip = new \ZipArchive();
		if ($zip->open($zip_file, \ZipArchive::CREATE) !== true) {
			throw new \Exception('Could not create zip file.');
		}

		// Add files to the zip archive
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($plugin_dir_path),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $name => $file) {
			// Skip directories (they would be added automatically)
			if (!$file->isDir()) {
				// Get real and relative path for current file
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($plugin_dir_path) + 1);

				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
			}
		}

		// Close the zip archive
		$zip->close();

		// Serve the zip file for download
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . basename($plugin_dir_path) . '.zip"');
		header('Content-Length: ' . filesize($zip_file));
		readfile($zip_file);

		// Delete the temporary zip file
		unlink($zip_file);
		//exit the script
		exit;
	}

	/**
	 * Validate installed plugin
	 *
	 * @return void
	 */
	public function validate_installed_plugin()
	{
		try {
			//verify nonce
			if (!wp_verify_nonce($_POST['nonce'], 'download_installed_plugin_nonce')) {
				throw new \Exception('Invalid nonce, please try again.');
			}
			//get the plugin file
			$plugin_file = sanitize_text_field($_POST['plugin_file']);
			//check if the plugin file exists
			if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
				throw new \Exception('Plugin file not found, please try again.');
			}
			//save session
			if (session_status() === PHP_SESSION_NONE) {
				session_start();
			}
			//save the plugin file to the session
			$_SESSION['ade_download_installed_plugin'] = array(
				'plugin_file_nonce' => md5($plugin_file),
			);
			//return success
			wp_send_json_success(
				array(
					'download_link' => $this->get_download_link($plugin_file),
				)
			);
		} catch (\Exception $e) {
			//log the error
			error_log("Download Installed Plugin Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array('message' => $e->getMessage()));
		}
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
			echo '<a href="javascript:void(0)" data-plugin-file="' . esc_attr($plugin_file) . '" class="download-installed-plugin">' . __('Download') . '</a>';
		}
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('downloadinstalledplugin', DOWNLOAD_INSTALLED_PLUGIN_URL . 'assets/js/downloadinstalledplugin.min.js', array('jquery'), DOWNLOAD_INSTALLED_PLUGIN_VERSION, true);
		wp_localize_script(
			'downloadinstalledplugin',
			'downloadinstalledplugin',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('download_installed_plugin_nonce'),
			)
		);
	}
}

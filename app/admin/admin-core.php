<?php

/**
 * Admin Core
 *
 * @package Download Installed Extension
 */

namespace Download_Installed_Extension\Admin;

use Download_Installed_Extension\Base;

/**
 * Class Admin_Core
 *
 * @package Download_Installed_Extension\Admin
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
		add_action('wp_ajax_ade_download_installed_extension', array($this, 'validate_installed_extension'));
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
		register_rest_route('ade-download-installed-extension/v1', '/download-extension', array(
			'methods' => 'GET',
			'callback' => array($this, 'download_installed_extension_as_zip'),
			'permission_callback' => array($this, 'download_installed_extension_permission'),
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
		return rest_url('ade-download-installed-extension/v1/download-extension?plugin_file=' . $plugin_file);
	}

	/**
	 * Permission callback
	 *
	 * @return bool
	 */
	public function download_installed_extension_permission()
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
	public function download_installed_extension_as_zip(\WP_REST_Request $request)
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
			if (!isset($_SESSION['ade_download_installed_extension']['plugin_file_nonce']) || $_SESSION['ade_download_installed_extension']['plugin_file_nonce'] !== md5($plugin_file)) {
				throw new \Exception('Invalid plugin file nonce, please try again.');
			}
			//download the plugin
			$this->download_extension_file($plugin_file);
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
	public function download_extension_file($plugin_file)
	{
		// Get the plugin directory path
		$plugin_dir_path = WP_PLUGIN_DIR . '/' . dirname($plugin_file);

		// Create a temporary zip file in the system's temp directory
		$temp_dir = sys_get_temp_dir();
		$zip_file = tempnam($temp_dir, 'plugin_') . '.zip';

		// Initialize the ZipArchive class
		$zip = new \ZipArchive();
		if ($zip->open($zip_file, \ZipArchive::CREATE) !== true) {
			throw new \Exception('Could not create zip file.');
		}

		// Add files to the zip archive
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($plugin_dir_path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		// Ignore directories and files
		$ignore_directories = array(
			'node_modules', //node modules
			'tests', //tests
			'bin', //bin
		);

		/**
		 * Ignore files
		 *
		 * @var array
		 */
		$ignore_files = array(
			'Gruntfile.js', //Gruntfile
			'package-lock.json', //package lock
			'composer.lock', //composer lock
			'webpack.config.js', //webpack config
			'phpunit.xml', //phpunit
			'phpcs.xml', //phpcs
			'phpcs.xml.dist', //phpcs dist
			'editorconfig', //editorconfig
			'eslintrc.js', //eslintrc
			'eslintignore', //eslintignore
			'.gitignore', //gitignore
			'.distignore', //distignore
			'phpunit.xml.dist', //phpunit dist
		);

		foreach ($files as $file) {
			// Skip directories and ignored files
			if (!$file->isDir()) {
				//get the relative path
				$relativePath = substr($file->getRealPath(), strlen($plugin_dir_path) + 1);
				//explode the relative path
				$pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
				//check if the first part of the path is in the ignore directories array and the file is not in the ignore files array
				if (!in_array($pathParts[0], $ignore_directories) && !in_array(basename($file), $ignore_files)) {
					// Add current file to archive
					$zip->addFile($file->getRealPath(), $relativePath);
				}
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
		// Exit the script
		exit;
	}

	/**
	 * Validate installed extension
	 *
	 * @return void
	 */
	public function validate_installed_extension()
	{
		try {
			//verify nonce
			if (!wp_verify_nonce($_POST['nonce'], 'download_installed_extension_nonce')) {
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
			$_SESSION['ade_download_installed_extension'] = array(
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
		$columns['download-installed-extension'] = __('Download');
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
		if ($column_name === 'download-installed-extension') {
			echo '<a href="javascript:void(0)" data-plugin-file="' . esc_attr($plugin_file) . '" class="download-installed-extension">' . __('Download') . '</a>';
		}
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('downloadinstalledextension', DOWNLOAD_INSTALLED_EXTENSION_URL . 'assets/js/downloadinstalledextension.min.js', array('jquery'), DOWNLOAD_INSTALLED_EXTENSION_VERSION, true);
		wp_localize_script(
			'downloadinstalledextension',
			'downloadinstalledextension',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('download_installed_extension_nonce'),
			)
		);
	}
}

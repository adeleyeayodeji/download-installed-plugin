<?php

/**
 * Backup core
 *
 * @package Download Installed Extension
 * @since 1.0.0
 */

namespace Download_Installed_Extension\Admin;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Backup core
 *
 * @package Download Installed Extension
 * @since 1.0.0
 */
class BackupCore extends BackupHandler
{

	/**
	 * Init
	 *
	 * @return void
	 */
	public function init()
	{
		//create backup folder
		$this->create_backup_folder();
		//backup wordpress
		$this->backup_wordpress();
	}

	/**
	 * Cancel backup and clear all backup files
	 *
	 * @return void
	 */
	public function cancel_backup()
	{
		//delete all backup files
		$this->delete_all_backup_files();
	}

	/**
	 * Folders to backup array
	 *
	 * @return array
	 */
	public function folders_to_backup()
	{
		return apply_filters('biggidroid_folders_to_backup', [
			[
				/**
				 * Plugins
				 * Priority 1
				 */
				'priority' => 1,
				'folder' => WP_CONTENT_DIR . '/plugins',
			],
			[
				/**
				 * Themes
				 * Priority 2
				 */
				'priority' => 2,
				'folder' => WP_CONTENT_DIR . '/themes',
			],
			[
				/**
				 * Uploads
				 * Priority 3
				 */
				'priority' => 3,
				'folder' => WP_CONTENT_DIR . '/uploads',
			]
		]);
	}

	/**
	 * Root folders files backup
	 *
	 * @param int $chunkSize
	 * @return void
	 * @throws \Exception
	 */
	public function wp_chunked_backup($chunkSize = 5000)
	{
		try {
			$rootDir = ABSPATH; // WordPress root directory
			$backup_folder_name = 'others-' . date('Y-m-d');

			$excludedPaths = $this->wp_core_paths_to_exclude();

			$this->backupHandlerCore(
				$chunkSize,
				$rootDir,
				$backup_folder_name,
				$this->backup_progress_key,
				$excludedPaths
			);
		} catch (\Throwable $e) {
			// Log and throw error
			error_log("Error during chunked backup: " . $e->getMessage() . " - " . $e->getFile() . " - " . $e->getLine());
			throw new \Exception("Error during chunked backup: " . $e->getMessage());
		}
	}

	/**
	 * Init backup
	 *
	 * @return void
	 */
	public function init_backup()
	{
		try {
			//get folders to backup
			$folders_to_backup = $this->folders_to_backup();
			//sort folders by priority
			usort($folders_to_backup, function ($a, $b) {
				return $a['priority'] - $b['priority'];
			});
			//backup folders
			foreach ($folders_to_backup as $folder) {
				//backup folder
				$this->backup_folder($folder);
			}
		} catch (\Throwable $e) {
			//throw error
			throw new \Exception("Error backing up wordpress: " . $e->getMessage());
		}
	}

	/**
	 * Backup folder
	 *
	 * @param array $folder
	 * @param int $chunkSize
	 * @return void
	 *
	 * Example usage:
	 * $this->backup_folder([
	 *     'folder' => WP_CONTENT_DIR . '/sample-folder',
	 *     'priority' => 1,
	 * ], 500);
	 */
	public function backup_folder($folder, $chunkSize = 500)
	{
		try {
			// Check if folder exists
			if (!file_exists($folder['folder'])) {
				return;
			}

			// Add date to folder name
			$folder_name = basename($folder['folder']) . '-' . date('Y-m-d');

			// Set up progress tracking
			$progressKey = $this->backup_assets_progress_key . '_' . md5($folder['folder']);

			//backup folder
			$this->backupHandlerCore(
				$chunkSize,
				$folder['folder'],
				$folder_name,
				$progressKey
			);
		} catch (\Throwable $e) {
			// Log error
			error_log("Error backing up folder: " . $e->getMessage());
			throw new \Exception("Error backing up folder: " . $e->getMessage());
		}
	}

	/**
	 * Backup wordpress
	 *
	 * @return void
	 */
	public function backup_wordpress()
	{
		try {
			//init backup
			// $this->backup_folder([
			// 	'folder' => realpath(WP_CONTENT_DIR . '/plugins'),
			// 	'priority' => 1,
			// ], 0);
			// $progress = get_transient($this->backup_assets_progress_key . '_' . md5(WP_CONTENT_DIR . '/plugins'));
			// if ($progress) {
			// 	error_log('Backup Progress: ' . $progress['percentage'] . '%');
			// 	error_log('Total files: ' . $progress['totalFiles']);
			// 	error_log('Total directories: ' . $progress['totalDirs']);
			// 	error_log('Processed files: ' . $progress['processedFiles']);
			// 	error_log('Processed directories: ' . count($progress['processedDirs']));
			// }



			// $this->wp_chunked_backup(500);
			// $progress = get_transient($this->backup_progress_key);
			// if ($progress) {
			// 	error_log('Backup Progress: ' . $progress['percentage'] . '%');
			// 	error_log('Total files: ' . $progress['totalFiles']);
			// 	error_log('Total directories: ' . $progress['totalDirs']);
			// 	error_log('Processed files: ' . $progress['processedFiles']);
			// 	error_log('Processed directories: ' . count($progress['processedDirs']));
			// }
			//cancel backup
			// $this->cancel_backup();
		} catch (\Throwable $e) {
			//log error
			error_log("Error backing up wordpress: " . $e->getMessage());
			//return message
			return [
				'status' => 'error',
				'message' => 'Error backing up wordpress: ' . $e->getMessage(),
			];
		}
	}
}

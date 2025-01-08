<?php

/**
 * Backup core
 *
 * @package Download Installed Extension
 * @since 1.0.0
 */

namespace Download_Installed_Extension\Admin;

use Download_Installed_Extension\Base;

/**
 * Backup core
 *
 * @package Download Installed Extension
 * @since 1.0.0
 */
class BackupCore extends Base
{
	/**
	 * Backup folder
	 *
	 * @var string
	 */
	private $backup_folder = WP_CONTENT_DIR . '/biggidroid-backups';

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
	 * Create backup folder
	 *
	 * @return void
	 */
	public function create_backup_folder()
	{
		try {
			//check if folder exists
			if (file_exists($this->backup_folder)) {
				return;
			}

			//create folder
			wp_mkdir_p($this->backup_folder);
		} catch (\Exception $e) {
			//log error
			error_log("Error creating backup folder: " . $e->getMessage());
		}
	}

	/**
	 * Folders to backup array
	 *
	 * @return array
	 */
	public function folders_to_backup()
	{
		return [
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
			// [
			// 	/**
			// 	 * Uploads
			// 	 * Priority 3
			// 	 */
			// 	'priority' => 3,
			// 	'folder' => WP_CONTENT_DIR . '/uploads',
			// ],
			// [
			// 	/**
			// 	 * Others
			// 	 * Exclude less priority folders
			// 	 */
			// 	'priority' => 4,
			// 	'folder' => 'others',
			// ],
		];
	}

	/**
	 * Folders to exclude
	 *
	 * @return array
	 */
	public function folders_to_exclude()
	{
		return [
			'node_modules' //ignore node_modules directory
		];
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
	 * @return void
	 */
	public function backup_folder($folder)
	{
		try {
			// Check if folder exists
			if (!file_exists($folder['folder'])) {
				return;
			}

			//add date to folder name
			$folder_name = basename($folder['folder']) . '-' . date('Y-m-d');

			//check if zip file already exists
			$zipFileName = $this->backup_folder . '/' . $folder_name . '.zip';
			if (file_exists($zipFileName)) {
				return;
			}

			// Create a new ZipArchive instance
			$zip = new \ZipArchive();

			// Open the archive
			if ($zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
				throw new \Exception("Cannot open <$zipFileName>\n");
			}

			// Add files to the archive
			$this->addFolderToZip($folder['folder'], $zip);

			// Close the archive
			$zip->close();
		} catch (\Throwable $e) {
			// Throw error
			throw new \Exception("Error backing up folder: " . $e->getMessage());
		}
	}

	/**
	 * Recursively add a folder to a zip archive
	 *
	 * @param string $folder
	 * @param \ZipArchive $zip
	 * @param string $parentFolder
	 * @return void
	 */
	private function addFolderToZip($folder, $zip, $parentFolder = '')
	{
		// Open the folder
		$handle = opendir($folder);
		// Read the folder
		while (false !== ($entry = readdir($handle))) {
			// Ignore . and .. from the folder
			if (
				$entry != '.' && $entry != '..'
			) {
				$path = $folder . '/' . $entry;
				$localPath = $parentFolder . $entry;
				// Check if the entry is a directory
				if (is_dir($path)) {
					// Ignore node_modules directory
					if (in_array($entry, $this->folders_to_exclude())) {
						//ignore folder
						continue;
					}
					// Add directory
					$zip->addEmptyDir($localPath);
					// Recursively add files to the zip archive
					$this->addFolderToZip($path, $zip, $localPath . '/');
				} else {
					// Add file
					$zip->addFile($path, $localPath);
				}
			}
		}
		// Close the folder
		closedir($handle);
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
			$this->init_backup();
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

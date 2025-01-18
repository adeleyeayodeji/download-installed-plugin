<?php

/**
 * Backup core
 *
 * @package Download Installed Extension
 * @since 1.0.0
 */

namespace Download_Installed_Extension\Admin;

use DirectoryIterator;
use Download_Installed_Extension\Base;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
	 * Backup progress key for wordpress core
	 *
	 * @var string
	 */
	private $backup_progress_key = 'biggidroid_wp_core_backup_progress';

	/**
	 * Backup progress key for wordpress assets
	 *
	 * @var string
	 */
	private $backup_assets_progress_key = 'biggidroid_wp_assets_backup_progress';

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
	 * Delete all backup files
	 *
	 * @return void
	 */
	public function delete_all_backup_files()
	{
		try {
			// Delete all backup files and directories
			$backup_files = glob($this->backup_folder . DIRECTORY_SEPARATOR . '*');

			foreach ($backup_files as $backup_file) {
				// Check if the backup file is a directory
				if (is_dir($backup_file)) {
					// Recursively delete the directory and its contents
					$this->delete_directory($backup_file);
				} else {
					// Delete file
					unlink($backup_file);
				}
			}

			// Delete transients
			delete_transient($this->backup_progress_key);

			global $wpdb;

			// Define the transient pattern to match
			$pattern = $wpdb->esc_like($this->backup_assets_progress_key) . '%';

			// Build the query to delete matching transients
			$sql = $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $pattern
			);

			// Execute the query
			$wpdb->query($sql);

			error_log("Transients deleted");

			// Return success message
			return [
				'status' => 'success',
				'message' => 'Backup cancelled and all backup files deleted',
			];
		} catch (\Throwable $e) {
			// Log error with more context
			error_log("Error deleting all backup files: " . $e->getMessage() . " at " . $e->getFile() . " on line " . $e->getLine());

			// Return error message
			return [
				'status' => 'error',
				'message' => 'Error deleting all backup files: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Recursively delete a directory and its contents
	 *
	 * @param string $dir
	 * @return void
	 */
	private function delete_directory($dir)
	{
		// Open the directory
		$files = array_diff(scandir($dir), ['.', '..']);

		foreach ($files as $file) {
			$filePath = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_dir($filePath)) {
				// Recursively delete subdirectory
				$this->delete_directory($filePath);
			} else {
				// Delete file
				unlink($filePath);
			}
		}

		// Remove the empty directory
		rmdir($dir);
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
			[
				/**
				 * Uploads
				 * Priority 3
				 */
				'priority' => 3,
				'folder' => WP_CONTENT_DIR . '/uploads',
			]
		];
	}

	/**
	 * Root folders files backup
	 *
	 * @param int $chunkSize
	 * @return void
	 * @throws \Exception
	 */
	public function wp_chunked_backup($chunkSize = 10500)
	{
		try {
			$rootDir = ABSPATH; // WordPress root directory
			$backupDir = $this->backup_folder;
			$backup_folder_name = 'others-' . date('Y-m-d');
			$backupZipFile = $backupDir . DIRECTORY_SEPARATOR . $backup_folder_name . '.zip';

			// Validate directories
			if (!is_writable($backupDir)) {
				throw new \Exception("Backup directory is not writable: $backupDir");
			}

			if (!is_readable($rootDir)) {
				throw new \Exception("Root directory is not readable: $rootDir");
			}

			// Excluded paths and folder names
			$excludedPaths = $this->wp_core_paths_to_exclude();
			$excludedFolderNames = $this->folders_to_exclude();

			// Track progress with WordPress transient
			$progressKey = $this->backup_progress_key;
			$progress = get_transient($progressKey) ?: [
				'processedDirs' => [],
				'currentDir' => '',
				'fileOffset' => 0,
			];

			// Initialize ZipArchive
			$zip = new \ZipArchive();
			if (!$zip->open($backupZipFile, \ZipArchive::CREATE)) {
				throw new \Exception("Failed to create or open ZIP file: $backupZipFile");
			}

			// Traverse root files first (non-directories)
			$rootFiles = new DirectoryIterator($rootDir);
			$fileCount = 0;
			foreach ($rootFiles as $file) {
				if ($file->isDot()) {
					continue; // Skip '.' and '..'
				}

				// Skip excluded files
				$filePath = $file->getRealPath();
				if ($this->isExcluded($filePath, $excludedPaths, $excludedFolderNames)) {
					continue;
				}

				$relativePath = str_replace($rootDir, '', $filePath);

				// Add the file to the ZIP
				if ($file->isFile()) {
					$zip->addFile($filePath, $relativePath);
					$fileCount++;
				}

				$progress['fileOffset'] = $fileCount;

				// Break when chunk size is reached
				if ($fileCount >= $chunkSize) {
					set_transient($progressKey, $progress, HOUR_IN_SECONDS); // Save progress
					$zip->close();
					return; // Exit for the next chunk
				}
			}

			// Traverse directories (using RecursiveIteratorIterator)
			$directories = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($directories as $directory) {
				$dirPath = $directory->getRealPath();

				// Skip non-directories and excluded paths
				if (!$directory->isDir() || $this->isExcluded($dirPath, $excludedPaths, $excludedFolderNames)) {
					continue;
				}

				// Skip already processed directories
				if (in_array($dirPath, $progress['processedDirs'])) {
					continue;
				}

				// Add directory to ZIP
				$relativePath = str_replace($rootDir, '', $dirPath);
				$zip->addEmptyDir($relativePath);

				// Process files in the directory
				$files = new DirectoryIterator($dirPath);
				foreach ($files as $file) {
					if ($file->isDot()) {
						continue; // Skip '.' and '..'
					}

					// Skip already processed files
					$filePath = $file->getRealPath();
					if ($this->isExcluded($filePath, $excludedPaths, $excludedFolderNames)) {
						continue;
					}

					// Ensure the path is a file before adding to the ZIP
					if ($file->isFile()) {
						$relativeFilePath = str_replace($rootDir, '', $filePath);
						$zip->addFile($filePath, $relativeFilePath);
					}

					$progress['fileOffset']++;

					// Break when chunk size is reached
					if ($progress['fileOffset'] >= $chunkSize) {
						set_transient($progressKey, $progress, HOUR_IN_SECONDS); // Save progress
						$zip->close();
						return; // Exit for the next chunk
					}
				}

				// Mark directory as processed
				$progress['processedDirs'][] = $dirPath;
			}

			// Finalize the backup
			$zip->close();
			delete_transient($progressKey); // Clear progress
		} catch (\Throwable $e) {
			// Log and throw error
			error_log("Error during chunked backup: " . $e->getMessage() . " - " . $e->getFile() . " - " . $e->getLine());
			throw new \Exception("Error during chunked backup: " . $e->getMessage());
		}
	}

	/**
	 * Determine if a path should be excluded from the backup.
	 *
	 * @param string $path
	 * @param array $excludedPaths
	 * @param array $excludedFolderNames
	 * @return bool
	 */
	private function isExcluded($path, $excludedPaths, $excludedFolderNames)
	{
		foreach ($excludedPaths as $excluded) {
			if (strpos($path, $excluded) === 0) {
				return true;
			}
		}
		foreach ($excludedFolderNames as $excludedFolderName) {
			if (basename($path) === $excludedFolderName) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Folders to exclude
	 *
	 * @return array
	 */
	public function folders_to_exclude()
	{
		return apply_filters('biggidroid_excluded_folders', [
			'node_modules' //ignore node_modules directory
		]);
	}

	/**
	 * Paths to exclude
	 *
	 * @return array
	 */
	public function wp_core_paths_to_exclude()
	{
		$rootDir = ABSPATH;
		//get excluded paths
		$excluded_paths = apply_filters('biggidroid_excluded_wp_core_paths', [
			realpath($rootDir . 'wp-content/uploads'),
			realpath($rootDir . 'wp-content/plugins'),
			realpath($rootDir . 'wp-content/themes'),
			realpath($rootDir . 'wp-content/ai1wm-backups'),
			realpath($rootDir . 'wp-content/cache'),
			realpath($rootDir . 'wp-content/biggidroid-backups'),
			realpath($rootDir . 'wp-content/upgrade'),
			realpath($rootDir . 'wp-content/upgrade-temp-backup'),
		]);
		//return excluded paths
		return $excluded_paths;
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

			// Add date to folder name
			$folder_name = basename($folder['folder']) . '-' . date('Y-m-d');

			// Define zip file path
			$zipFileName = $this->backup_folder . DIRECTORY_SEPARATOR . $folder_name . '.zip';

			// Skip if the zip file already exists
			if (file_exists($zipFileName)) {
				return;
			}

			// Check for any similarly named files
			$matched_zip_file = glob($this->backup_folder . DIRECTORY_SEPARATOR . $folder_name . '.*');
			if ($matched_zip_file) {
				return;
			}

			// Set up progress tracking
			$progressKey = $this->backup_assets_progress_key . '_' . md5($folder['folder']);
			$progress = get_transient($progressKey) ?: [
				'processedFiles' => [],
				'currentOffset' => 0,
			];

			// Create a new ZipArchive instance
			$zip = new \ZipArchive();
			if (!$zip->open($zipFileName, \ZipArchive::CREATE)) {
				throw new \Exception("Cannot create <$zipFileName>");
			}

			// Add files to the archive in chunks
			$chunkSize = 500; // Number of files to process in one run
			$fileCount = 0;

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($folder['folder'], RecursiveDirectoryIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file) {
				// Skip already processed files
				if (in_array($file->getRealPath(), $progress['processedFiles'])) {
					continue;
				}

				$filePath = $file->getRealPath();
				$relativePath = str_replace($folder['folder'], '', $filePath);

				// Skip excluded folders or files
				if ($this->shouldExclude($filePath)) {
					continue;
				}

				// Add directories and files to ZIP
				if ($file->isDir()) {
					$zip->addEmptyDir($relativePath);
				} else {
					$zip->addFile($filePath, $relativePath);
				}

				// Track processed files
				$progress['processedFiles'][] = $filePath;
				$fileCount++;

				// Break after processing chunk size
				if ($fileCount >= $chunkSize) {
					set_transient($progressKey, $progress, HOUR_IN_SECONDS);
					$zip->close();
					return; // Exit to continue in the next run
				}
			}

			// Finalize the backup
			$zip->close();
			delete_transient($progressKey); // Clear progress
		} catch (\Throwable $e) {
			// Log error
			error_log("Error backing up folder: " . $e->getMessage());
			throw new \Exception("Error backing up folder: " . $e->getMessage());
		}
	}

	/**
	 * Determine if a path or folder name should be excluded from the backup.
	 *
	 * @param string $path
	 * @return bool
	 */
	private function shouldExclude($path)
	{
		$excludedFolders = $this->folders_to_exclude();
		foreach ($excludedFolders as $excluded) {
			if (strpos($path, $excluded) !== false || basename($path) === $excluded) {
				return true;
			}
		}
		return false;
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
			// $this->init_backup();
			// $this->wp_chunked_backup();
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

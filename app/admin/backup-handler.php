<?php

/**
 * Backup handler
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
 * Backup handler
 *
 * @package Download Installed Extension
 * @since 1.0.0
 */
class BackupHandler extends Base
{
	/**
	 * Backup folder
	 *
	 * @var string
	 */
	public $backup_folder = WP_CONTENT_DIR . '/biggidroid-backups';

	/**
	 * Backup progress key for wordpress core
	 *
	 * @var string
	 */
	public $backup_progress_key = 'biggidroid_wp_core_backup_progress';

	/**
	 * Backup progress key for wordpress assets
	 *
	 * @var string
	 */
	public $backup_assets_progress_key = 'biggidroid_wp_assets_backup_progress';


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
			delete_transient($this->backup_assets_progress_key);

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

			$pattern2 = $wpdb->esc_like($this->backup_progress_key) . '%';

			//delete like for backup_progress_key
			$sql2 = $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $pattern2
			);

			// Execute the query
			$wpdb->query($sql2);

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
	 * Count the total number of files and directories to be backed up.
	 *
	 * @param string $dir
	 * @param array $excludedPaths
	 * @param array $excludedFolderNames
	 * @param array $progress
	 *
	 * @return array|mixed
	 */
	private function count_total_files_and_dirs($dir, $excludedPaths, $excludedFolderNames, $progress)
	{
		$directories = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($directories as $directory) {
			$dirPath = $directory->getRealPath();

			// Skip excluded directories
			if ($this->isExcluded($dirPath, $excludedPaths, $excludedFolderNames)) {
				continue;
			}

			// Increment directory count
			if ($directory->isDir()) {
				$progress['totalDirs']++;
			}

			// Process files in the directory
			if ($directory->isFile()) {
				$progress['totalFiles']++;
			}
		}

		//return progress
		return $progress;
	}

	/**
	 * Update the progress percentage and store the progress.
	 *
	 * @param array $progress
	 * @return array|mixed
	 */
	private function update_progress_percentage($progress)
	{
		$totalDirs = $progress['totalDirs'];
		$processedDirs = count($progress['processedDirs']);

		// Calculate the progress percentage using only directories
		$percentage = $totalDirs > 0 ? round(($processedDirs / $totalDirs) * 100) : 0;

		// Store the progress in the transient
		$progress['percentage'] = $percentage;

		//return progress
		return $progress;
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
	 * Backup handler core
	 * @param int $chunkSize
	 * @param string $directoryToBackup
	 * @param string $backup_folder_name
	 * @param string $progress_key
	 * @param array $excludedPaths
	 *
	 * @return void
	 */
	public function backupHandlerCore($chunkSize, $directoryToBackup, $backup_folder_name, $progress_key, $excludedPaths = [])
	{
		try {
			$rootDir = $directoryToBackup;
			$backupDir = $this->backup_folder;
			$backupZipFile = $backupDir . DIRECTORY_SEPARATOR . $backup_folder_name . '.zip';

			// Validate directories
			if (!is_writable($backupDir)) {
				throw new \Exception("Backup directory is not writable: $backupDir");
			}

			if (!is_readable($rootDir)) {
				throw new \Exception("Root directory is not readable: $rootDir");
			}

			// Excluded paths and folder names
			$excludedFolderNames = $this->folders_to_exclude();

			// Track progress with WordPress transient
			$progressKey = $progress_key;
			$progress = get_transient($progressKey) ?: [
				'processedDirs' => [],
				'processedFiles' => 0,
				'currentDir' => '',
				'totalFiles' => 0,
				'totalDirs' => 0,
				'percentage' => 0,
			];

			//check if progress is 100
			if ($progress['percentage'] === 100) {
				//backup already completed, return
				return;
			}

			// Initialize ZipArchive
			$zip = new \ZipArchive();
			if (!$zip->open($backupZipFile, \ZipArchive::CREATE)) {
				throw new \Exception("Failed to create or open ZIP file: $backupZipFile");
			}

			// Only count total files and directories once
			if ($progress['totalFiles'] === 0) {
				// Count total files and directories
				$progress = $this->count_total_files_and_dirs($rootDir, $excludedPaths, $excludedFolderNames, $progress);

				error_log('Total files: ' . $progress['totalFiles']);
				error_log('Total directories: ' . $progress['totalDirs']);
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

				$progress['processedFiles'] = $fileCount;

				// Update progress after every chunk
				$progress = $this->update_progress_percentage($progress);

				// Update progress after every chunk
				if ($chunkSize > 0 && $fileCount >= $chunkSize) {
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

					// Skip excluded files
					$filePath = $file->getRealPath();
					if ($this->isExcluded($filePath, $excludedPaths, $excludedFolderNames)) {
						continue;
					}

					// Ensure the path is a file before adding to the ZIP
					if ($file->isFile()) {
						$relativeFilePath = str_replace($rootDir, '', $filePath);
						$zip->addFile($filePath, $relativeFilePath);
					}

					$progress['processedFiles']++;

					// Update progress after every chunk
					$progress = $this->update_progress_percentage($progress);

					// Update progress after every chunk
					if ($chunkSize > 0 && $progress['processedFiles'] >= $chunkSize) {
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
			//set progress to 100
			$progress['percentage'] = 100;
			set_transient($progressKey, $progress, HOUR_IN_SECONDS);
		} catch (\Throwable $e) {
			//throw error
			throw $e;
		}
	}
}

<?php

/**
 * Created by PhpStorm.
 * User: wegan
 * Date: 3/21/2018
 * Time: 10:48 AM
 * 
 * This class is responsible for picking up FedEx tracking files from the FedEx sFTP site
 * Nagesh 09/10/24 - Changed the code to class and function based structure.
 */

require_once("app-includes/sftp_functions/sftp_transfer_functions.php");
require_once('siteEnvironment/siteEnvironment.php');
require_once("encryption/encryption/encryption.php");

class FedExFileService
{
	public function pickupFiles(): bool
	{

		$encrypt = new encryption();
		$credArray = $encrypt->credentials('FedExSFTP');

		$sftpHost = $credArray['host'];
		$sftpPort = $credArray['port'];
		$sftpUsername = $credArray['user'];
		$sftpPassword = $credArray['pass'];
		$sftpDelete = FALSE;

		//Get Tracking Files
		echo "Picking up Tracking Files.";

		$filePickupDir = "INSIGHTCSV";
		if (SITE_ENVIRONMENT == 'PROD') {
			$destinationDir = "D:\\inetpub\\php_scheduled_tasks\\shipping\\fed_ex_tracking\\incoming\\tracking_files";
		} else {
			$destinationDir = "./incoming/tracking_files";
		}
		//Connect to FedEx sFTP site, pull down all files and save them in the /incoming/tracking_files folder
		sftp_pickup_files($sftpHost, $sftpUsername, $sftpPassword, $filePickupDir, $destinationDir, $sftpPort, $sftpDelete);

		// check if the files were picked up

		$dirInfo = scandir($destinationDir);
		$files = array();

		foreach ($dirInfo as $filename) {
			$filePath = $destinationDir . DIRECTORY_SEPARATOR . $filename;
			if (is_file($filePath)) {
				$files[] = $filename;
			}
		}

		if (count($files) == 0) {
			return false;
		}

		return true;
	}

	public function removeProcessedFiles()
	{
		$processedDir = ".\\incoming\\tracking_files\\processed\\";
		$days = 7;
		$cutoff = time() - ($days * 24 * 60 * 60);

		if (is_dir($processedDir)) {

			// Get all files in the directory
			$files = scandir($processedDir);

			foreach ($files as $file) {
				$filePath = $processedDir . DIRECTORY_SEPARATOR . $file;

				// Skip '.' and '..' (current and parent directory links)
				if ($file === '.' || $file === '..') {
					continue;
				}

				if (is_file($filePath)) {

					$fileModifiedTime = filemtime($filePath);

					if ($fileModifiedTime < $cutoff) {
						if (unlink($filePath)) {
							echo "Deleted: $filePath\n";
						} else {
							echo "Failed to delete: $filePath\n";
						}
					}
				}
			}
		} else {
			echo "Directory does not exist: $processedDir\n";
		}
	}
}

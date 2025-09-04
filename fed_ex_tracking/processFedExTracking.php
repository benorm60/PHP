<?php

/**
 * Created by PhpStorm.
 * User: wegan
 * Date: 3/22/2018
 * Time: 11:07 AM
 * This script processes FedEx Tracking files and inserts them into the database and then moves the files to a processed directory
 * Nagesh 09/10/24 - Fixed the fgetCsv() function to use named parameters instead of positional parameters, which was causing the script to fail.
 * Nagesh 09/10/24 - Added code to delete data older than 30 days from the database and delete processed files older than 7 days
 */

require 'autoload.php';
require 'FedExFileService.php';

// Copy files from FedEx sFTP site to local directory
$fedExFileService = new FedExFileService();
$filesCopied = $fedExFileService->pickupFiles();

// Remove the processed files older than 7 days
$fedExFileService->removeProcessedFiles();

if (!$filesCopied) {
	echo "No FedEx Tracking files found";
	return;
}

/* ---------------------------------- Load the files to database ----------------------------------- */

date_default_timezone_set("America/New_York");
$mssql = dbConn::getInstance('mssql');

// Clean up the data more than 30 days old from the database

$sqlDelete = "
	DELETE FROM cognos_reports.fedex_tracking
	WHERE lastUpdated < DATEADD(DAY, -30, GETDATE())";

try {

	$queryDelete = $mssql->prepare($sqlDelete);
	$queryDelete->execute();
} catch (Throwable $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlDelete);
}

//prepare SQL Queries
$sqlInsert = "
	INSERT INTO cognos_reports.fedex_tracking 
	(lastUpdated, TRCK#, SHIPD, ESTDD, DELVD, SHPCO, SHPA1, SHPRC, SHPRS, SHPRZ, ACCT#, SIREF, RCPTN, RCPCO, POREF, SHPID, LBWGT, KGWGT, SCODE, AINFO)
	VALUES(:lastUpdated, :TRCK, :SHIPD, :ESTDD, :DELVD, :SHPCO, :SHPA1, :SHPRC, :SHPRS, :SHPRZ, :ACCT, :SIREF, :RCPTN, :RCPCO, :POREF, :SHPID, :LBWGT, :KGWGT, :SCODE, :AINFO) 
";
try {
	$queryInsert = $mssql->prepare($sqlInsert);
} catch (PDOException $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlInsert);
}

$trackingFileDir = ".\\incoming\\tracking_files\\";
$dirInfo = scandir($trackingFileDir);


foreach ($dirInfo as $filename) {
	$filePieces = explode('_', $filename);
	$filePath = $trackingFileDir . $filename;
	if (is_file($filePath)) {
		if (file_exists($filePath)) {

			$format = 'mdY His';
			$timeStamp = DateTime::createFromFormat($format, $filePieces[2] . ' ' . $filePieces[1]);

			//Open target file for reading
			$fres = fopen($filePath, "r");

			$row = 1;
			while (($rowData = fgetcsv(stream: $fres, separator: ",")) !== FALSE) {
				//Loop through rows
				//Convert Dates to DateTimes
				$dateFormat = 'Ymd';
				$sqlDateFormat = 'Y-m-d';

				$shipDate = DateTime::createFromFormat($dateFormat, $rowData[7]);
				$estDeliveryDate = DateTime::createFromFormat($dateFormat, $rowData[8]);
				$deliveryDate = DateTime::createFromFormat($dateFormat, $rowData[10]);

				if ($row > 1) {
					//skip the header row
					$insertParams = array(
						":lastUpdated" => $timeStamp->format('Y-m-d H:i:s'),
						":TRCK"        => $rowData[3],
						":SHIPD"       => !empty($shipDate) ? $shipDate->format($sqlDateFormat) : null,
						":ESTDD"       => !empty($estDeliveryDate) ? $estDeliveryDate->format($sqlDateFormat) : null,
						":DELVD"       => !empty($deliveryDate) ? $deliveryDate->format($sqlDateFormat) : null,
						":SHPCO"       => $rowData[19],
						":SHPA1"       => $rowData[20],
						":SHPRC"       => $rowData[23],
						":SHPRS"       => $rowData[24],
						":SHPRZ"       => $rowData[26],
						":ACCT"        => $rowData[27],
						":SIREF"       => $rowData[28],
						":RCPTN"       => $rowData[29],
						":RCPCO"       => $rowData[30],
						":POREF"       => $rowData[52],
						":SHPID"       => $rowData[55],
						":LBWGT"       => $rowData[56],
						":KGWGT"       => $rowData[57],
						":SCODE"       => $rowData[59],
						":AINFO"       => $rowData[72]
					);

					try {
						$queryInsert->execute($insertParams);
					} catch (PDOException $e) {
						(new ExceptionEmail)
							->newEmail($e)
							->addQuery($sqlInsert)
							->addParameters($insertParams);
					}
				}
				$row++;
			}

			fclose($fres);

			//move processed files to processed directory and delete from current directory
			copy($filePath, $trackingFileDir . "processed\\" . $filename);
			if (file_exists($trackingFileDir . "processed\\" . $filename)) {
				unlink($filePath);
			}

			echo "File " . $filename . " processed and moved to processed directory";
		} else {
			echo "File " . $filename . " does not exist";
		}
	}
}

echo "Processing FedEx Tracking Files Completed";

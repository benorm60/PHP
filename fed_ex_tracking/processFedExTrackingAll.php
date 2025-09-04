<?php
/**
 * Created by PhpStorm.
 * User: wegan
 * Date: 3/22/2018
 * Time: 11:07 AM
 */

/* This code is here as a reference just in case any columns are needed to be added back in in the future.
 * This is NOT the code that is run in production.
 */

require 'autoload.php';

date_default_timezone_set("America/New_York");
$mssql = dbConn::getInstance('mssql');

//prepare SQL Queries
$sqlInsert = "
	INSERT INTO cognos_reports.fedex_tracking 
	(lastUpdated, RTYPE, CCODE, TPIDC, TRCK#, FILL1, MTRK#, FILL2, SHIPD, ESTDD, ESTDT, DELVD, DELVT, PODNM, OCODE, DCODE, STATD, STATC, FILL3, SHPRN, SHPCO, SHPA1, SHPA2, SHPA3, SHPRC, SHPRS, SHPCC, SHPRZ, ACCT#, SIREF, RCPTN, RCPCO, RCPA1, RCPA2, RCPA3, RCPTC, RCPTS, RCPTZ, RCPCC, FILL4, SVCCD, PKGCD, TRPAY, DTPAY, TYPCD, FILL5, PIECS, UOMCD, DIMCD, FILL6, PKGLN, PKGWD, PKGHT, POREF, INREF, DEPT#, SHPID, LBWGT, KGWGT, DEXCD, SCODE, TCN#, BOL#, PC#1, PC#2, RMA#, APPTD, APPTT, ECITY, EVEST, EVECO, CDRC1, CDRC2, AINFO, SPHC1, SPHC2, SPHC3, SPHC4, RCPT#, FILL7)
	VALUES(:lastUpdated, :RTYPE, :CCODE, :TPIDC, :TRCK, :FILL1, :MTRK, :FILL2, :SHIPD, :ESTDD, :ESTDT, :DELVD, :DELVT, :PODNM, :OCODE, :DCODE, :STATD, :STATC, :FILL3, :SHPRN, :SHPCO, :SHPA1, :SHPA2, :SHPA3, :SHPRC, :SHPRS, :SHPCC, :SHPRZ, :ACCT, :SIREF, :RCPTN, :RCPCO, :RCPA1, :RCPA2, :RCPA3, :RCPTC, :RCPTS, :RCPTZ, :RCPCC, :FILL4, :SVCCD, :PKGCD, :TRPAY, :DTPAY, :TYPCD, :FILL5, :PIECS, :UOMCD, :DIMCD, :FILL6, :PKGLN, :PKGWD, :PKGHT, :POREF, :INREF, :DEPT, :SHPID, :LBWGT, :KGWGT, :DEXCD, :SCODE, :TCN, :BOL, :PC1, :PC2, :RMA, :APPTD, :APPTT, :ECITY, :EVEST, :EVECO, :CDRC1, :CDRC2, :AINFO, :SPHC1, :SPHC2, :SPHC3, :SPHC4, :RCPT, :FILL7) 
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

foreach($dirInfo as $filename) {
	$filePieces = explode('_', $filename);
	$filePath = $trackingFileDir.$filename;
	if(is_file($filePath)) {
		if(file_exists($filePath)) {
			$format = 'mdY His';
			$timeStamp = DateTime::createFromFormat($format, $filePieces[2].' '.$filePieces[1]);

			//Open target file for reading
			$fres = fopen($filePath, "r");

			$row = 1;
			while(($rowData = fgetcsv(stream: $fres, separator:",")) !== FALSE) {
				//Loop through rows
				//Convert Dates to DateTimes
				$dateFormat = 'Ymd';
				$sqlDateFormat = 'Y-m-d';

				$shipDate = DateTime::createFromFormat($dateFormat, $rowData[7]);
				$estDeliveryDate = DateTime::createFromFormat($dateFormat, $rowData[8]);
				$deliveryDate = DateTime::createFromFormat($dateFormat, $rowData[10]);
				$statusDate = DateTime::createFromFormat($dateFormat, $rowData[15]);

				//Convert Times to DateTimes
				$timeFormat = 'Hi';
				$sqlTimeFormat = 'H:i';

				$estDeliveryTime = DateTime::createFromFormat($timeFormat, $rowData[9]);
				$deliveryTime = DateTime::createFromFormat($timeFormat, $rowData[11]);
				$statusTime = DateTime::createFromFormat($timeFormat,$rowData[16]);

				if($row > 1) {
					//skip the header row
					$insertParams = array(
						":lastUpdated" => $timeStamp->format('Y-m-d H:i:s'),
						":RTYPE"       => $rowData[0],
						":CCODE"       => $rowData[1],
						":TPIDC"       => $rowData[2],
						":TRCK"        => $rowData[3],
						":FILL1"       => $rowData[4],
						":MTRK"        => $rowData[5],
						":FILL2"       => $rowData[6],
						":SHIPD"       => !empty($shipDate) ? $shipDate->format($sqlDateFormat) : null,
						":ESTDD"       => !empty($estDeliveryDate) ? $estDeliveryDate->format($sqlDateFormat) : null,
						":ESTDT"       => !empty($estDeliveryTime) ? $estDeliveryTime->format($sqlTimeFormat) : null,
						":DELVD"       => !empty($deliveryDate) ? $deliveryDate->format($sqlDateFormat) : null,
						":DELVT"       => !empty($deliveryTime) ? $deliveryTime->format($sqlTimeFormat) : null,
						":PODNM"       => $rowData[12],
						":OCODE"       => $rowData[13],
						":DCODE"       => $rowData[14],
						":STATD"       => !empty($statusDate) ? $statusDate->format($sqlDateFormat) : null,
						":STATC"       => !empty($statusTime) ? $statusTime->format($sqlTimeFormat) : null,
						":FILL3"       => $rowData[17],
						":SHPRN"       => $rowData[18],
						":SHPCO"       => $rowData[19],
						":SHPA1"       => $rowData[20],
						":SHPA2"       => $rowData[21],
						":SHPA3"       => $rowData[22],
						":SHPRC"       => $rowData[23],
						":SHPRS"       => $rowData[24],
						":SHPCC"       => $rowData[25],
						":SHPRZ"       => $rowData[26],
						":ACCT"        => $rowData[27],
						":SIREF"       => $rowData[28],
						":RCPTN"       => $rowData[29],
						":RCPCO"       => $rowData[30],
						":RCPA1"       => $rowData[31],
						":RCPA2"       => $rowData[32],
						":RCPA3"       => $rowData[33],
						":RCPTC"       => $rowData[34],
						":RCPTS"       => $rowData[35],
						":RCPTZ"       => $rowData[36],
						":RCPCC"       => $rowData[37],
						":FILL4"       => $rowData[38],
						":SVCCD"       => $rowData[39],
						":PKGCD"       => $rowData[40],
						":TRPAY"       => $rowData[41],
						":DTPAY"       => $rowData[42],
						":TYPCD"       => $rowData[43],
						":FILL5"       => $rowData[44],
						":PIECS"       => $rowData[45],
						":UOMCD"       => $rowData[46],
						":DIMCD"       => $rowData[47],
						":FILL6"       => $rowData[48],
						":PKGLN"       => $rowData[49],
						":PKGWD"       => $rowData[50],
						":PKGHT"       => $rowData[51],
						":POREF"       => $rowData[52],
						":INREF"       => $rowData[53],
						":DEPT"        => $rowData[54],
						":SHPID"       => $rowData[55],
						":LBWGT"       => $rowData[56],
						":KGWGT"       => $rowData[57],
						":DEXCD"       => $rowData[58],
						":SCODE"       => $rowData[59],
						":TCN"         => $rowData[60],
						":BOL"         => $rowData[61],
						":PC1"         => $rowData[62],
						":PC2"         => $rowData[63],
						":RMA"         => $rowData[64],
						":APPTD"       => $rowData[65],
						":APPTT"       => $rowData[66],
						":ECITY"       => $rowData[67],
						":EVEST"       => $rowData[68],
						":EVECO"       => $rowData[69],
						":CDRC1"       => $rowData[70],
						":CDRC2"       => $rowData[71],
						":AINFO"       => $rowData[72],
						":SPHC1"       => $rowData[73],
						":SPHC2"       => $rowData[74],
						":SPHC3"       => $rowData[75],
						":SPHC4"       => $rowData[76],
						":RCPT"        => $rowData[77],
						":FILL7"       => $rowData[78]
					);

					try {
						$queryInsert->execute($insertParams);
					} catch (PDOException $e) {
						(new ExceptionEmail)
							->newEmail($e)
							->addQuery($sqlInsert)
							->addParameters($insertParams);
						//TODO remove die before moving to production
						die;
					}
				}
				$row++;
			}

			fclose($fres);

			//move processed files to processed directory and delete from current directory
			copy($filePath, $trackingFileDir."processed\\".$filename);
			if(file_exists($trackingFileDir."processed\\".$filename)) {
				unlink($filePath);
			}
		} else {
			echo "File Not Found";
		}
	}
}
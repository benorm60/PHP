<?php
/**
 * Created by PhpStorm.
 * User: wegan
 * Date: 6/25/2018
 * Time: 11:23 AM
 */

require_once "vendor/autoload.php";
require_once "upsAPICredentials.php";
require_once "autoload.php";

set_time_limit(3600);

$mssql = dbConn::getInstance('mssql');
$iseries = dbConn::getInstance('ibm_db2_general_scheduled_tasks');

try {
	$sqlDelete = "DELETE FROM cognos_reports.ups_tracking";

	$resTruncate = $mssql->query($sqlDelete);
} catch (PDOException $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlDelete);
}

try {
	$sqlReset = "DBCC CHECKIDENT ('cognos_reports.ups_tracking', RESEED, 0)";

	$resTruncate = $mssql->query($sqlReset);
} catch (PDOException $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlReset);
}

//This is for UPS TRACKING
//Query to determine tracking numbers in "not shipped" but picked status
$sqlTrackingNumbers = "

	SELECT   *
	FROM     PWLIBCUST.FVJ109SHP5
	WHERE    ssstatus < '728'
		AND      ssdoc IN (
		SELECT dhdoc 
		FROM pwlib.fpw280,pwlib.fpw901 
		WHERE dhscac = uccode and uctype = 'AM' and uccode like 'U%'
		AND dhstat BETWEEN '706' AND '744'
	  )
	AND SSTRACKING != ''

	UNION

	SELECT   *
	FROM     PWLIBCUST.FVJ109SHP5
	WHERE    ssstatus = '728'
	AND  sspickdate >= (CURRENT_DATE - 20 DAYS)
	AND  ssdoc IN (
		SELECT dhdoc 
		FROM pwlib.fpw280
		JOIN pwlib.fpw901 ON dhscac = uccode
		WHERE uctype = 'AM'
		AND uccode LIKE 'U%'
	AND dhstat BETWEEN '745' AND '757'
	  )
	AND SSTRACKING != ''
";
try {
	$resTrackingNumbers = $iseries->query($sqlTrackingNumbers);
	$trackingNumbers = $resTrackingNumbers->fetchAll();
} catch (PDOException $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlTrackingNumbers);
}

//Query to insert data from UPS into MSSQL table
$sqlInsert = "
	INSERT INTO cognos_reports.ups_tracking
	(lastUpdated, TrackingNumber, StatusDate, StatusTime, AddressCity, 
	AddressState, AddressZip, AddressCountry, AddressDescription, SignedFor, 
	StatusTypeCode, StatusDescription, StatusCode, LoadNumber, GS1Label)
	VALUES(:lastUpdated, :TrackingNumber, :StatusDate, :StatusTime,	:AddressCity, 
	:AddressState, :AddressZip, :AddressCountry, :AddressDescription, :SignedFor, 
	:StatusTypeCode, :StatusDescription, :StatusCode, :LoadNumber, :GS1Label)
";

try {
	$queryInsert = $mssql->prepare($sqlInsert);
} catch (PDOException $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlInsert);
}

//Query to check if a given tracking number already has been pulled on a previous run of this program
$sqlCheck = "
	SELECT COUNT(TrackingNumber) AS TNCOUNT
	FROM cognos_reports.ups_tracking
	WHERE TrackingNumber = :TrackingNumber
";
try {
	$queryCheck = $mssql->prepare($sqlCheck);
} catch (PDOException $e) {
	(new ExceptionEmail)
		->newEmail($e)
		->addQuery($sqlCheck);
}

$dateFormat = 'Ymd';
$sqlDateFormat = 'Y-m-d';

$timeFormat = 'His';
$sqlTimeFormat = 'H:i:s';

$currentTimeStamp = new DateTime('now');

//This is for UPS TRACKING
$tracking = new Ups\Tracking($accessKey, $userId, $password);
foreach($trackingNumbers as $trackingNumber) {

	$checkParam = array(
		':TrackingNumber' => trim($trackingNumber['SSTRACKING'])
	);

	try{
		$queryCheck->execute($checkParam);
		$check = $queryCheck->fetch();
	} catch (PDOException $e) {
		(new ExceptionEmail)
			->newEmail($e)
			->addQuery($sqlCheck)
			->addParameters($checkParam);
	}

	if($check['TNCOUNT'] == 0) {

		try {
			//$trackingNumber = '1ZA083W30202542261';
			$shipment = $tracking->track(trim($trackingNumber['SSTRACKING']));

			if(!empty($shipment->Package->Activity)) {

				foreach($shipment->Package->Activity as $activity) {

					var_dump($activity);

					//Convert Dates to DateTimes
					$statusDate = !empty($activity->Date) ? DateTime::createFromFormat($dateFormat, $activity->Date) : null;

					//Convert Times to DateTimes
					$statusTime = !empty($activity->Time) ? DateTime::createFromFormat($timeFormat, $activity->Time) : null;

					$insertParams = array(
						':lastUpdated'    => $currentTimeStamp->format('Y-m-d H:i:s'),
						':TrackingNumber' => trim($trackingNumber['SSTRACKING']),
						':StatusDate'     => !empty($statusDate) ? $statusDate->format($sqlDateFormat) : null,
						':StatusTime'     => !empty($statusTime) ? $statusTime->format($sqlTimeFormat) : null,
						':AddressCity'    => !empty($activity->ActivityLocation->Address->City) ? $activity->ActivityLocation->Address->City : null,

						':AddressState'       => !empty($activity->ActivityLocation->Address->StateProvinceCode) ? $activity->ActivityLocation->Address->StateProvinceCode : null,
						':AddressZip'         => !empty($activity->ActivityLocation->Address->PostalCode) ? $activity->ActivityLocation->Address->PostalCode : null,
						':AddressCountry'     => !empty($activity->ActivityLocation->Address->CountryCode) ? $activity->ActivityLocation->Address->CountryCode : null,
						':AddressDescription' => !empty($activity->ActivityLocation->Description) ? $activity->ActivityLocation->Description : null,
						':SignedFor'          => !empty($activity->ActivityLocation->SignedForByName) ? $activity->ActivityLocation->SignedForByName : null,

						':StatusTypeCode'    => !empty($activity->Status->StatusType->Code) ? $activity->Status->StatusType->Code : null,
						':StatusDescription' => !empty($activity->Status->StatusType->Description) ? $activity->Status->StatusType->Description : null,
						':StatusCode'        => !empty($activity->Status->StatusCode->Code) ? $activity->Status->StatusCode->Code : null,
						':LoadNumber'        => !empty(trim($trackingNumber['SSSHIPMENT'])) ? trim($trackingNumber['SSSHIPMENT']) : null,
						':GS1Label'        	 => !empty(trim($trackingNumber['SSCASELBL'])) ? trim($trackingNumber['SSCASELBL']) : null
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
			}
		} catch (\Ups\Exception\InvalidResponseException $e) {
			if($e->getMessage() == 'Failure: No tracking information available (151044)') {
				//do nothing
				continue;
			} else {
				(new ExceptionEmail)
					->newEmail($e);
			}
		} catch (Exception $e) {
			var_dump($e);
		}
	}
}
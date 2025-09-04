<?php
/**
 * Created by PhpStorm.
 * User: wegan
 * Date: 6/25/2018
 * Time: 11:31 AM
 */

require_once("encryption/encryption/encryption.php");

$encrypt = new encryption();
$credArray = $encrypt->credentials('UPSAPI');

$accessKey = $credArray['key'];
$userId = $credArray['user'];
$password = $credArray['pass'];
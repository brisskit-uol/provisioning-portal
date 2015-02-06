<?php

require_once 'main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

// Get customer ID and instance URL from the database
$custid = $user->custid;
$instanceurl = $user->instance_url;

// Die if user attempts to generate more than one instance
if (!empty($instanceurl)) {
	die("Instance has already been provisioned");
}

// Get platform type (AWS/Azure)
$platformtype = $_POST["platformtype"];

if ($platformtype == '1') {
	// AWS platform
	include 'aws_provision_instance.php';
} else if ($platformtype == '2') {
	// Azure platform
	include 'azure_provision_instance.php';
} else {
	die('Something broke :(');
}

?>
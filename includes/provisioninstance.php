<?php

require_once 'main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

$custid = $user->custid;
$isurlnull = $user->instance_url;


if (!empty($isurlnull)) {
	die("Instance has already been provisioned");
}

$platformtype = $_POST["platformtype"];

if ($platformtype == '1') {
	include 'aws_provision_instance.php';
} else if ($platformtype == '2') {
	include 'azure_provision_instance.php';
} else {
	die('Something broke :(');
}

?>
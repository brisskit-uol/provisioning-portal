<?php

require_once 'main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

if (empty($_POST['azuresubid']) && $_POST['azuretype'] == '2') {
	die("Subscription ID cannot be left blank");
}

if (!preg_match('|^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$|', $_POST['azuresubid']) && $_POST['azuretype'] == '2') {
	die('Credential error! 2');
}
die("ID: " . $_POST['azuresubid']);
$azuresubid = (empty($_POST['azuresubid']) ? '67c600b2-92f0-4c26-9116-e0f76b409f63' : $_POST['azuresubid']);

$certpath = (empty($_POST['azuresubid']) ? '/etc/ssl/certs/mycert.pem' : __DIR__ . '/' . $_SESSION['custid'] . '-cert.cer');

$keypath = (empty($_POST['azuresubid']) ? '/etc/ssl/certs/mycert.pem' : __DIR__ . '/' . $_SESSION['custid'] . '-key.pem');

require_once "guzzle5/vendor/autoload.php";

use GuzzleHttp\Client;

$client = new Client([
    'base_url' => ['https://management.core.windows.net/{subscription}/', ['subscription' => $azuresubid]],
    'defaults' => [
		'headers' 	=> ['x-ms-version' => '2014-06-01',
						'Content-Type' => 'application/xml',
		],
		'cert'		=> $certpath,
		'ssl_key'	=> $keypath,
	],
]);

try {

    $CreateCloudXML = simplexml_load_file('azure_CreateCloudService.xml');
    $CreateCloudXML->ServiceName = 'i2b2dev' . $_SESSION["custid"];
    $CreateCloudXML = $CreateCloudXML->asXML();
    $responseCreateCloud = $client->post('services/hostedservices', ['body' => $CreateCloudXML]);

    $requestIDCreateCloud = $responseCreateCloud->getHeader('x-ms-request-id');
    
    $successCreateCloud = "";
    
    while ($successCreateCloud != "Succeeded") {
		//echo "Waiting to CreateCloud\r\n";
		$responseStatusCreateCloud = $client->get('operations/' . $requestIDCreateCloud);
		$statusCreateCloudXML = new SimpleXMLElement($responseStatusCreateCloud->getBody());
		$successCreateCloud = $statusCreateCloudXML->Status;
		sleep(2);
    }
    
    $CreateStorageAccountXML = simplexml_load_file('azure_CreateStorageAccount.xml');
    $CreateStorageAccountXML->ServiceName = 'i2b2dev' . $_SESSION["custid"];
    $CreateStorageAccountXML = $CreateStorageAccountXML->asXML();
    $responseCreateStorageAccount = $client->post('services/storageservices', ['body' => $CreateStorageAccountXML]);
    
    $requestIDCreateStorageAccount = $responseCreateStorageAccount->getHeader('x-ms-request-id');
    
    $successCreateStorageAccount = "";
    
    while ($successCreateStorageAccount != "Succeeded") {
		//echo "Waiting to CreateStorageAccount\r\n";
		$responseStatusCreateStorageAccount = $client->get('operations/' . $requestIDCreateStorageAccount);
		$statusCreateStorageAccountXML = new SimpleXMLElement($responseStatusCreateStorageAccount->getBody());
		$successCreateStorageAccount = $statusCreateStorageAccountXML->Status;
		sleep(2);
    }
    
	// Generate a random password
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";	
	for( $i = 0; $i < 10; $i++ ) {
		$portalpass .= $chars[ rand( 0, (strlen($chars)) - 1 ) ];
	}

	if (strlen($portalpass) < 10) {
		die('Credential error! 7');
	}
		
	if (!preg_match('|^[A-Za-z0-9]+$|', $portalpass)) {
		die('Credential error! 8');
	}

	$portalpasshash = password_hash($portalpass, PASSWORD_BCRYPT);
	$portalpasshash = str_replace('$2y$', '$2a$', $portalpasshash);

	$pgsqlcmd = 'psql -d i2b2 -c "INSERT INTO i2b2portal.users(username,password,enabled) VALUES (\'%portaluser%\', \'%portalpasshash%\', true); INSERT INTO i2b2portal.user_roles(username, role) VALUES (\'%portaluser%\',\'ROLE_USER\');"';
	$pgsqlcmd = strtr($pgsqlcmd, array('%portaluser%' => addslashes($portaluser), '%portalpasshash%' => addslashes($portalpasshash)));
	$pgsqlcmd = str_replace('$', '\$', escapeshellarg($pgsqlcmd));
	$pgsqlcmd = strtr('sudo su - postgres -c %pgsqlcmd%', array('%pgsqlcmd%' => $pgsqlcmd));

	$emailSuccess = "php /var/local/brisskit/i2b2/sendEmail.php " . $user->email . " " . $portalpass;

	$installer = implode(PHP_EOL, array($installscript, $pgsqlcmd, $emailSuccess));

	$userdata = base64_encode($installer);
	
    $CreateVMDeploymentXML = simplexml_load_file('azure_CreateVMDeployment.xml');
    $CreateVMDeploymentXML->RoleList->Role->RoleName = 'i2b2dev' . $_SESSION["custid"];
    $CreateVMDeploymentXML->RoleList->Role->ConfigurationSets->ConfigurationSet[0]->HostName = 'i2b2dev' . $_SESSION["custid"];
    $CreateVMDeploymentXML->RoleList->Role->ConfigurationSets->ConfigurationSet[0]->CustomData = $userdata;
    $CreateVMDeploymentXML->RoleList->Role->OSVirtualHardDisk->MediaLink = 'https://i2b2dev' . $_SESSION["custid"] . '.blob.core.windows.net/vhds/i2b2dev' . $_SESSION["custid"] . '.vhd';
    $CreateVMDeploymentXML = $CreateVMDeploymentXML->asXML();
    $responseCreateVMDeployment = $client->post('services/hostedservices/i2b2dev' . $_SESSION["custid"] . '/deployments', ['body' => $CreateVMDeploymentXML]);
    
    $requestIDCreateVMDeployment = $responseCreateVMDeployment->getHeader('x-ms-request-id');
    
    $successCreateVMDeployment = "";
    
    while ($successCreateVMDeployment != "Succeeded") {
		//echo "Waiting to CreateVMDeployment\r\n";
		$responseStatusCreateVMDeployment = $client->get('operations/' . $requestIDCreateVMDeployment);
		$statusCreateVMDeploymentXML = new SimpleXMLElement($responseStatusCreateVMDeployment->getBody());
		$successCreateVMDeployment = $statusCreateVMDeploymentXML->Status;
		sleep(2);
    }
    
    $responseGetCloudServiceProperties = $client->get('services/hostedservices/i2b2dev' . $_SESSION["custid"]);

    $xmlresponseGetCloudServiceProperties = $responseGetCloudServiceProperties->xml();
    $azureurl = $xmlresponseGetCloudServiceProperties->Url;
    
    //echo "Success!\r\n";
    
    echo 'In around 20 minutes, i2b2 will be available here <a href="' . $azureurl . 'i2b2/webclient">' . $azureurl . 'i2b2/webclient</a><br><br>';

	$message = "A new i2b2 instance has been generated by *". $user->email . "* at <http://" . $azureurl . "/i2b2UploaderWebapp|" . $azure url . ">";

	// Array of data posted Slack
	$fields = array(
		'channel' => "#azure-cloud",
		//'username' => "deploybot",
		//'icon_emoji' => ":shipit:",
		'text' => $message
	);  

	// 'payload' parameter is required by Slack
	// $fields array must be json encoded
	$payload = "payload=" . json_encode($fields);

	// URL we need to post data to (Given to you by Slack when creating an Incoming Webhook integration)
	$url = 'https://hooks.slack.com/services/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';

	// Start CURL connection
	$ch = curl_init();

	// Set the:
	// - URL
	// - Number of POST variables
	// - Data
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $payload);

	// Execute post to Slack integration
	$curlresult = curl_exec($ch);

	// Close CURL connection
	curl_close($ch);

} catch (Exception $ex) {

    echo $ex->getRequest();
    echo $ex->getResponse();
    

}
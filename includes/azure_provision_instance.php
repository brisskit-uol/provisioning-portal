<?php

require_once 'main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

// Remove characters from custid for Azure
$custidtrimmed = substr(str_replace(array('-', '_'), '', $custid), -6, 6);

// If provisioning on user's account subscription ID is a required field
if (empty($_POST['azuresubid']) && $_POST['azuretype'] == '2') {
	die("Subscription ID cannot be left blank");
}

// Validate subscription id based on format aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa
if (!preg_match('|^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$|', $_POST['azuresubid']) && $_POST['azuretype'] == '2') {
	die('Credential error! 2');
}

// Use BRISSKit credentials if customer does not provide their own
$azuresubid = (empty($_POST['azuresubid']) ? '67c600b2-92f0-4c26-9116-e0f76b409f63' : $_POST['azuresubid']);
$certpath = (empty($_POST['azuresubid']) ? '/etc/ssl/certs/mycert.pem' : dirname(__DIR__) . '/azure-' . $custid . '-cert.cer');
$keypath = (empty($_POST['azuresubid']) ? '/etc/ssl/certs/mycert.pem' : '/tmp/azure-' . $custid . '-key.pem');

require_once "guzzle5/vendor/autoload.php";

use GuzzleHttp\Client;

// Build a guzzle client
$client = new Client([
    'base_url' => ['https://management.core.windows.net/{subscription}/', ['subscription' => $azuresubid]],
    'defaults' => [
		'headers' 	=> ['x-ms-version' => '2014-06-01', // specify version of API to use
						'Content-Type' => 'application/xml',
		],
		'cert'		=> $certpath,
		'ssl_key'	=> $keypath,
	],
]);

try {

	// Create Azure Cloud Service
    $CreateCloudXML = simplexml_load_file('azure_CreateCloudService.xml');
    $CreateCloudXML->ServiceName = 'i2b2dev' . $custidtrimmed;
    $CreateCloudXML = $CreateCloudXML->asXML();
    $responseCreateCloud = $client->post('services/hostedservices', ['body' => $CreateCloudXML]);
    $requestIDCreateCloud = $responseCreateCloud->getHeader('x-ms-request-id');
    
    // wait for success
    $successCreateCloud = "";
    while ($successCreateCloud != "Succeeded") {
		//echo "Waiting to CreateCloud\r\n";
		$responseStatusCreateCloud = $client->get('operations/' . $requestIDCreateCloud);
		$statusCreateCloudXML = new SimpleXMLElement($responseStatusCreateCloud->getBody());
		$successCreateCloud = $statusCreateCloudXML->Status;
		sleep(2);
    }
    
    // Create Azure Storage Account
    $CreateStorageAccountXML = simplexml_load_file('azure_CreateStorageAccount.xml');
    $CreateStorageAccountXML->ServiceName = 'i2b2dev' . $custidtrimmed;
    $CreateStorageAccountXML = $CreateStorageAccountXML->asXML();
    $responseCreateStorageAccount = $client->post('services/storageservices', ['body' => $CreateStorageAccountXML]);
    
    $requestIDCreateStorageAccount = $responseCreateStorageAccount->getHeader('x-ms-request-id');
    
    // wait for success
    $successCreateStorageAccount = "";
    while ($successCreateStorageAccount != "Succeeded") {
		//echo "Waiting to CreateStorageAccount\r\n";
		$responseStatusCreateStorageAccount = $client->get('operations/' . $requestIDCreateStorageAccount);
		$statusCreateStorageAccountXML = new SimpleXMLElement($responseStatusCreateStorageAccount->getBody());
		$successCreateStorageAccount = $statusCreateStorageAccountXML->Status;
		sleep(2);
    }
    
    // Get user's email address to inject into portal

	$portaluser = $user->email;

	// Validate user email

	if (strlen($portaluser) < 1) {
		die('Credential error! 5');
	}
		
	if (!filter_var($portaluser, FILTER_VALIDATE_EMAIL)) {
		die('Credential error! 6');
	}
    
	// Generate a random password
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";	
	for( $i = 0; $i < 10; $i++ ) {
		$portalpass .= $chars[ rand( 0, (strlen($chars)) - 1 ) ];
	}

	// Validate password
	if (strlen($portalpass) < 10) {
		die('Credential error! 7');
	}
		
	if (!preg_match('|^[A-Za-z0-9]+$|', $portalpass)) {
		die('Credential error! 8');
	}

	// Hash password using bcrypt
	$portalpasshash = password_hash($portalpass, PASSWORD_BCRYPT);
	
	// Replace hash identifier as Java Spring not updated to include new PHP identifier
	// See http://php.net/security/crypt_blowfish.php for more details
	$portalpasshash = str_replace('$2y$', '$2a$', $portalpasshash);
	
	// Build up SQL statement with copious amounts of escaping
	$pgsqlcmd = 'psql -d i2b2 -c "INSERT INTO i2b2portal.users(username,password,enabled) VALUES (\'%portaluser%\', \'%portalpasshash%\', true); INSERT INTO i2b2portal.user_roles(username, role) VALUES (\'%portaluser%\',\'ROLE_USER\');"';
	$pgsqlcmd = strtr($pgsqlcmd, array('%portaluser%' => addslashes($portaluser), '%portalpasshash%' => addslashes($portalpasshash)));
	$pgsqlcmd = str_replace('$', '\$', escapeshellarg($pgsqlcmd));
	$pgsqlcmd = strtr('sudo su - postgres -c %pgsqlcmd%', array('%pgsqlcmd%' => $pgsqlcmd));

	// Use PHP to send an email once instance is build via command line
	$emailSuccess = "php /var/local/brisskit/i2b2/sendEmail.php " . $user->email . " " . $portalpass;
	
	// Get the userdata into a string
	$installscript = file_get_contents('i2b2install.sh');

	// Combine install script with SQL statement and email for command line processing
	$installer = implode(PHP_EOL, array($installscript, $pgsqlcmd, $emailSuccess));

	// Encode complete installer script in base64 as required by Azure
	$userdata = base64_encode($installer);
	
	// Create Azure VM Deployment
    $CreateVMDeploymentXML = simplexml_load_file('azure_CreateVMDeployment.xml');
    $CreateVMDeploymentXML->RoleList->Role->RoleName = 'i2b2dev' . $custidtrimmed;
    $CreateVMDeploymentXML->RoleList->Role->ConfigurationSets->ConfigurationSet[0]->HostName = 'i2b2dev' . $custidtrimmed;
    $CreateVMDeploymentXML->RoleList->Role->ConfigurationSets->ConfigurationSet[0]->CustomData = $userdata;
    $CreateVMDeploymentXML->RoleList->Role->OSVirtualHardDisk->MediaLink = 'https://i2b2dev' . $custidtrimmed . '.blob.core.windows.net/vhds/i2b2dev' . $custidtrimmed . '.vhd';
    $CreateVMDeploymentXML = $CreateVMDeploymentXML->asXML();
    $responseCreateVMDeployment = $client->post('services/hostedservices/i2b2dev' . $custidtrimmed . '/deployments', ['body' => $CreateVMDeploymentXML]);
    
    $requestIDCreateVMDeployment = $responseCreateVMDeployment->getHeader('x-ms-request-id');
    
    // wait for success
    $successCreateVMDeployment = "";
    while ($successCreateVMDeployment != "Succeeded") {
		//echo "Waiting to CreateVMDeployment\r\n";
		$responseStatusCreateVMDeployment = $client->get('operations/' . $requestIDCreateVMDeployment);
		$statusCreateVMDeploymentXML = new SimpleXMLElement($responseStatusCreateVMDeployment->getBody());
		$successCreateVMDeployment = $statusCreateVMDeploymentXML->Status;
		sleep(2);
    }
    
    $responseGetCloudServiceProperties = $client->get('services/hostedservices/i2b2dev' . $custidtrimmed . '/deployments/i2b2');

    $xmlresponseGetCloudServiceProperties = $responseGetCloudServiceProperties->xml();
    $azureurl = $xmlresponseGetCloudServiceProperties->Url;
    
    //Strip the protocol to remain consistent with AWS
    $azureurl = parse_url($azureurl, PHP_URL_HOST);
    
    //echo "Success!\r\n";

	// Store the public URL in the database
	$user->storeURL($azureurl);
    
    echo 'In around 20 minutes, i2b2 will be available here <a href="http://' . $azureurl . '/i2b2UploaderWebapp" target="_blank">http://' . $azureurl . '/i2b2UploaderWebapp</a><br><br>';

	$message = "A new i2b2 instance has been generated by *". $user->email . "* (" . $custid . ") at <http://" . $azureurl . "/i2b2UploaderWebapp|" . $azureurl . ">";

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

	echo "Request<br><br>";
    echo $ex->getRequest();
    echo "Response<br><br>";
    echo $ex->getResponse();
    

}
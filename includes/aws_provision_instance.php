<?php
 
require_once 'main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

// Taken from http://blogs.aws.amazon.com/php/post/TxMLFLE50WUAMR/Provision-an-Amazon-EC2-Instance-with-PHP

require 'aws-sdk-php/vendor/autoload.php';
 
use Aws\Ec2\Ec2Client;
 
// Get instance type and credentials
$awstype = $_POST['awstype'];
$awsaccessid = $_POST["awsaccessid"];
$awssecretkey = $_POST["awssecretkey"];


if ($awstype == "1") { // if BRISSKit hosted use role credentials
	$ec2Client = Ec2Client::factory(array(
		'region' => 'eu-west-1', // (e.g., us-east-1)
	));
} else if ($awstype == "2") { // if selft hosted do credential validation
	if (strlen($awsaccessid) != 20) {
		die('Credential error! 1');
	}
	
	if (!preg_match('|^[A-Z0-9]+$|', $awsaccessid)) {
		die('Credential error! 2');
	}
	
	if (strlen($awssecretkey) != 40) {
		die('Credential error! 3');
	}
	
	if (!preg_match('|^[A-Za-z0-9+\/]+$|', $awssecretkey)) {
		die('Credential error! 4');
	}

	$ec2Client = Ec2Client::factory(array(
		'region' => 'eu-west-1', // (e.g., us-east-1)
		'key'    => $awsaccessid, // use provided AWS access ID
		'secret' => $awssecretkey, // use provided AWS secret key
	));
} else {
	die('Error');
}

// Create the key pair
$keyPairName = 'keypair-' . $custid;

$result = $ec2Client->createKeyPair(array(
    'KeyName' => $keyPairName
));

// Save the private key
$saveKeyLocation =  "/tmp/{$keyPairName}.pem";
file_put_contents($saveKeyLocation, $result['KeyMaterial']);
 
// Update the key's permissions so it can be used with SSH
chmod($saveKeyLocation, 0600);

// Create the security group
$securityGroupName = 'security-group-' . $custid;
$result = $ec2Client->createSecurityGroup(array(
    'GroupName'   => $securityGroupName,
    'Description' => 'i2b2 security rules'
));
 
// Get the security group ID (optional)
$securityGroupId = $result->get('GroupId');

// Get the remote IP address
$ipaddress = $_SERVER['REMOTE_ADDR'] . '/32';

// Set ingress rules for the security group
$ec2Client->authorizeSecurityGroupIngress(array(
    'GroupName'     => $securityGroupName,
    'IpPermissions' => array(
        array(
            'IpProtocol' => 'tcp',
            'FromPort'   => 80, // allow HTTP traffic
            'ToPort'     => 80,
            'IpRanges'   => array(
                array('CidrIp' => $ipaddress), // restrict to user's IP address
            ),
        ),
        array(
            'IpProtocol' => 'tcp',
            'FromPort'   => 22, // allow SSH traffic
            'ToPort'     => 22,
            'IpRanges'   => array(
                array('CidrIp' => '212.159.100.127/32'), // restrict to dev IP address
            ),
        )
    )
));

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

// Encode complete installer script in base64 as required by AWS
$userdata = base64_encode($installer);

// Launch an instance with the key pair, security group and install script
$result = $ec2Client->runInstances(array(
    'ImageId'        => 'ami-f0b11187',
    'MinCount'       => 1,
    'MaxCount'       => 1,
    'InstanceType'   => 't2.micro',
    'KeyName'        => $keyPairName,
    'SecurityGroups' => array($securityGroupName),
    'UserData'       => $userdata,
));

// Get the instance ID
$instanceIds = $result->getPath('Instances/*/InstanceId');

// Tag our new instance for easy identification
$ec2Client->createTags(array(
    'Resources' => $instanceIds,
    'Tags' => array(
        array(
            'Key' => 'Name',
            'Value' => 'i2b2-' . $custid,
        ),
    ),
));


// Wait until the instance is launched
$ec2Client->waitUntilInstanceRunning(array(
    'InstanceIds' => $instanceIds,
));

// Describe the now-running instance to get the public URL
$result = $ec2Client->describeInstances(array(
    'InstanceIds' => $instanceIds,
));

$ec2url = current($result->getPath('Reservations/*/Instances/*/PublicDnsName'));

// Store the public URL in the database
$user->storeURL($ec2url);

// Return the link to the instance
echo 'In around 20 minutes, i2b2 will be available here <a href="http://' . $ec2url . '/i2b2UploaderWebapp" target="_blank">http://' . $ec2url . '/i2b2UploaderWebapp</a><br><br>We\'ll send you an email when it\'s ready!<br><br>';

// Post a message to Slack
$message = "A new i2b2 instance has been generated by *". $user->email . "* (" . $custid . ") at <http://" . $ec2url . "/i2b2UploaderWebapp|" . $ec2url . ">";

// Array of data posted Slack
$fields = array(
	'channel' => "#amazon-cloud",
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

?>
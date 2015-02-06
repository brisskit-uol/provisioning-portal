<?php

require_once 'main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

// Build array of CSR variables
$dn = array(
		"countryName" => "UK",
		"stateOrProvinceName" => "Leicestershire",
		"localityName" => "Leicester",
		"organizationName" => "The University of Leicester",
		"organizationalUnitName" => "BRISSKit Team",
		"commonName" => "i2b2.brisskit.org",
		"emailAddress" => "i2b2@brisskit.org"
	);

// Generate private key
$privkey = openssl_pkey_new();

// Generate CSR
$csr = openssl_csr_new($dn, $privkey);

// Sign CSR
$sscert = openssl_csr_sign($csr, null, $privkey, 365);

// Save certificate to disk if it does not already exist
if (!file_exists($_SESSION["custid"] . "-cert.pem")) {
	openssl_x509_export_to_file($sscert, $_SESSION["custid"] . "-cert.cer");
}

// Provide link to certificate
$link_to_cert = '<a href="' . htmlentities($_SESSION["custid"]) . '-cert.cer">Link to your certificate</a><br/><br/>';

// Save private key to disk if it does not already exist
if (!file_exists("/tmp/azure-" . $_SESSION["custid"] . "-key.pem")) {
	openssl_pkey_export_to_file($privkey, "/tmp/azure-" . $_SESSION["custid"] . "-key.pem");
}

?>
<?php

if (!isset($_SESSION["custid"])) {
	header('Location: setup.php');
}

$dn = array(
		"countryName" => "UK",
		"stateOrProvinceName" => "Leicestershire",
		"localityName" => "Leicester",
		"organizationName" => "The University of Leicester",
		"organizationalUnitName" => "BRISSKit Team",
		"commonName" => "i2b2.brisskit.org",
		"emailAddress" => "i2b2@brisskit.org"
	);

	$privkey = openssl_pkey_new();

	$csr = openssl_csr_new($dn, $privkey);

	$sscert = openssl_csr_sign($csr, null, $privkey, 365);

	if (!file_exists($_SESSION["custid"] . "-cert.pem")) {
		openssl_x509_export_to_file($sscert, $_SESSION["custid"] . "-cert.cer");
	}
	
	$link_to_cert = '<a href="' . htmlentities($_SESSION["custid"]) . '-cert.cer">Link to your certificate</a><br/><br/>';
	
	if (!file_exists($_SESSION["custid"] . "-key.pem")) {
		openssl_pkey_export_to_file($privkey, $_SESSION["custid"] . "-key.pem");
	}

?>
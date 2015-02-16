<?php

// To protect any php page on your site, include main.php
// and create a new User object. It's that simple!

require_once 'includes/main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

?>

<!DOCTYPE html>
<html>

	<head>
		<meta charset="utf-8"/>
		<title>AWS Help</title>

		<!-- The main CSS file -->
		<link href="assets/css/style.css" rel="stylesheet" />
		<link href="//cdn.rawgit.com/noelboss/featherlight/1.0.4/release/featherlight.min.css" type="text/css" rel="stylesheet" title="Featherlight Styles" />

		<!--[if lt IE 9]>
			<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
	</head>

	<body>
		<div id="protected-page">
			<img src="assets/img/brisskit-logo.jpg" alt="Lock" />
			<h1>Public Cloud i2b2</h1>
			<h3>Create AWS user credentials</h3>
			<p>Go to the <a href="https://console.aws.amazon.com/iam/home?#users" target="_blank">IAM console</a> and log in with your AWS credentials</p>
			<p>Click <code>Create New Users</code>.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_1.png"><img src="assets/img/aws_1_thumb.png" alt="" /></a></p>
			<p>Enter a username and ensure <code>Generate an access key for each user</code> is checked. Click <code>Create</code>.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_2.png"><img src="assets/img/aws_2_thumb.png" alt="" /></a></p>
			<p>Click <code>Show User Security Credentials</code> and you will be provided with an Access ID and Secret Key for the newly created user. Record these details as they cannot be retrieved later. You may choose to download these credentials by clicking <code>Download Credentials</code>. Click <code>Close</code> to be returned to the IAM Users.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_3.png"><img src="assets/img/aws_3_thumb.png" alt="" /></a></p>
			<p>Click on the newly created user account and click <code>Attach User Policy</code>.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_4.png"><img src="assets/img/aws_4_thumb.png" alt="" /></a></p>
			<p>While you can create your own custom policy, we recommend clicking <code>Select</code> on the <strong>Amazon EC2 Full Access</strong> policy template to ensure the necessary access rights are provided to the user account.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_5.png"><img src="assets/img/aws_5_thumb.png" alt="" /></a></p>
			<p>You can rename the policy or use the default provided by Amazon. You can also modify the policy if there are specific changes you wish to make. Then click <code>Apply Policy</code>.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_6.png"><img src="assets/img/aws_6_thumb.png" alt="" /></a></p>
			<p>You should now see the policy attached to the user account. This account can now be used to provision i2b2 on your own AWS account.</p>
			<p><a href="#" class="imglink" data-featherlight="assets/img/aws_7.png"><img src="assets/img/aws_7_thumb.png" alt="" /></a></p>
		</div>
		<script src="//code.jquery.com/jquery-latest.js"></script>
		<script src="//cdn.rawgit.com/noelboss/featherlight/1.0.4/release/featherlight.min.js" type="text/javascript" charset="utf-8"></script>
	</body>
</html>
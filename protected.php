<?php

// To protect any php page on your site, include main.php
// and create a new User object. It's that simple!

require_once 'includes/main.php';

$user = new User();

if(!$user->loggedIn()){
	redirect('index.php');
}

//$_SESSION["custid"] = $user->custid;

include("cert.php");

$isurlnull = $user->instance_url;

?>

<!DOCTYPE html>
<html>

	<head>
		<meta charset="utf-8"/>
		<title>Generate i2b2 instance</title>

		<!-- The main CSS file -->
		<link href="assets/css/style.css" rel="stylesheet" />

		<!--[if lt IE 9]>
			<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
	</head>

	<body>
<?php if (empty($isurlnull)): ?>
		<div id="protected-page">
			<img src="assets/img/lock.jpg" alt="Lock" />
			<h1>Welcome to Public Cloud i2b2</h1>

			<!-- <p>Please create details for your i2b2 portal:</p>
			<div id="portal">
				Username: <input type="text" id="portal_user" /><br>
				<span class="info">Username must contain alphanumeric characters <strong>only</strong></span><br>
				Password: <input type="password" id="portal_pass" /><br>
				<span class="info">Password must contain alphanumeric characters <strong>only</strong> and have a minimum of 8 characters</span>
			</div> -->
			<p>Please choose a platform:</p>
			<br>
			<div id="platform">
				<input class="service" type="image" src="assets/img/aws.png" name="platformtype" data-value="1">
				<input class="service" type="image" src="assets/img/azure.png" name="platformtype" data-value="2"><br>
				<input type="hidden" id="image-value" name="selected_image" value="">
			</div>
			<div class="clearboth">&nbsp;</div>
			<div id="platform1" class="platformtext" style="display: none">
				<input type="radio" name="awstype" value="1" checked>I don't have an AWS account<br>
				<input type="radio" name="awstype" value="2">I have my own AWS account<br>
			</div>
			<div id="platform2" class="platformtext" style="display: none">
				<input type="radio" name="azuretype" value="1" checked>I don't have an Azure account<br>
				<input type="radio" name="azuretype" value="2">I have my own Azure account<br>
			</div>
			<div id="aws1" class="awstext" style="display: none">
			</div>
			<div id="aws2" class="awstext" style="display: none">
				<br>
				Access ID: <input type="text" id="aws_access_id" /><br>
				Secret Key: <input type="text" id="aws_secret_key" />
			</div>
			<div id="azure1" class="azuretext" style="display: none">
			</div>
			<div id="azure2" class="azuretext" style="display: none">
				<br>
				Please upload this certificate to Azure <?php echo $link_to_cert; ?>
				Subscription ID: <input type="text" id="azure_subscription_id" /><br>
			</div>
			<br>
			<button disabled="disabled">Generate i2b2 instance</button>
			<br><br>
			<div id="pleasewait" style="display: none">
				<p>Please wait while we provision your new i2b2 instance</p>
				<p><img src="assets/img/712.gif" /></p>
			</div>
			<div id="i2b2url"><p></p></div>

			<a href="index.php?logout=1" class="logout-button">Logout</a>

		</div>
		<script src="http://code.jquery.com/jquery-1.10.2.js"></script>
<script>
$(document).ready(function() {
	$("button").prop("disabled", false);
	$("input[name$='platformtype']").click(function() {
		var test = $(this).attr('data-value');
		$("#image-value").val($(this).attr('data-value'));
		$("input[name$='platformtype']").removeClass('active');
		$(this).addClass('active');
		$("div.platformtext").not("#platform" + test).stop(true, false).hide("fast");
		$("div.awstext").hide("fast");
		$("input[name$='awstype']").eq(0).prop('checked', true);
		$("div.azuretext").hide("fast");
		$("input[name$='azuretype']").eq(0).prop('checked', true);
		$("#platform" + test).show("slow");
    });
	$("input[name$='awstype']").click(function() {
		var test = $(this).val();
		$("div.awstext").not("#aws" + test).hide("fast");
		$("#aws" + test).show("slow");
    });
	$("input[name$='azuretype']").click(function() {
		var test = $(this).val();
		$("div.azuretext").not("#azure" + test).hide("fast");
		$("#azure" + test).show("slow");
    });
	$( "button" ).click(function() {
		var platformtype = $("#image-value").val();
		var awstype = $("input[name$='awstype']:checked").val();
		var azuretype = $("input[name$='azuretype']:checked").val();
		var awsaccessid = $("#aws_access_id").val();
		var awssecretkey = $("#aws_secret_key").val();
		var azuresubid = $("#azure_subscription_id").val();
		console.log(platformtype, awstype, awsaccessid, awssecretkey, azuretype);
		$( "button" ).prop( "disabled", true );
		$( "input" ).prop( "disabled", true );
		$( "#pleasewait" ).show( "slow" );
		$( "#i2b2url p" ).load( "./includes/provisioninstance.php", { "platformtype": platformtype, "awstype": awstype, "awsaccessid": awsaccessid, "awssecretkey": awssecretkey, "azuretype": azuretype, "azuresubid": azuresubid, }, function() {
			$( "#pleasewait" ).hide( "fast" );
		});
	});
});
</script>
<?php else: ?>
		<div id="protected-page">
			<img src="assets/img/lock.jpg" alt="Lock" />
			<h1>Public Cloud i2b2</h1>

			<p>You already have an i2b2 instance!</p>
			<p>It was last seen here:</p>
			<div id="i2b2url"><p><a href="http://<?php echo $isurlnull; ?>/i2b2UploaderWebapp">http://<?php echo $isurlnull; ?>/i2b2UploaderWebapp</a></p></div>
			<a href="index.php?logout=1" class="logout-button">Logout</a>
		</div>
<?php endif; ?>
	</body>
</html>
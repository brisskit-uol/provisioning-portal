#!/bin/bash

exec >> /tmp/install-$(date +"%F-%H%M%S").log 2>&1

sudo mkdir -p /var/local/brisskit/i2b2
cd /var/local/brisskit/i2b2
sudo wget http://www.h2ss.co.uk/q/i2b2pg/dbcmds.zip
sudo wget http://www.h2ss.co.uk/q/i2b2pg/i2b2-1.7-install-procedures.zip
sudo wget http://www.h2ss.co.uk/q/i2b2pg/webclient_brisskit.zip
sudo wget http://www.h2ss.co.uk/q/i2b2pg/i2b2UploaderWebapp.war
sudo wget https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/class.phpmailer.php
sudo wget https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/class.smtp.php
sudo cat > sendEmail.php << 'EOL'
<?php
require 'class.phpmailer.php';
require 'class.smtp.php';

if (defined('STDIN')) {
	$email = $argv[1];
	$portalpass = $argv[2];
} else { 
	die();
}

$mail = new PHPMailer;

//$mail->SMTPDebug = 3;                               // Enable verbose debug output

$mail->isSMTP();                                      // Set mailer to use SMTP
$mail->Host = 'smtp.mandrillapp.com';  // Specify main and backup SMTP servers
$mail->SMTPAuth = true;                               // Enable SMTP authentication
$mail->Username = 'rp354@le.ac.uk';                 // SMTP username
$mail->Password = 'pE7jhgLf5GTpQ7izY1ISEA';                           // SMTP password
$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
$mail->Port = 587;                                    // TCP port to connect to

$mail->From = 'brisskit@le.ac.uk';
$mail->FromName = 'BRISSKit Cloud i2b2';
$mail->addAddress($email);     // Add a recipient

$mail->isHTML(true);                                  // Set email format to HTML

$mail->Subject = 'BRISSKit i2b2 instance';
$mail->Body    = 'Your i2b2 instance is now ready to use! Your username is: ' . $email . ' and your password is ' . $portalpass;
$mail->AltBody = 'Your i2b2 instance is now ready to use! Your username is: ' . $email . ' and your password is ' . $portalpass;

if(!$mail->send()) {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}

?>
EOL
sudo apt-get update
sudo apt-get install unzip
sudo unzip dbcmds.zip -d dbcmds
sudo unzip i2b2-1.7-install-procedures.zip
sudo apt-get -y install postgresql postgresql-contrib postgresql-doc
sudo su - postgres -c 'psql -f /var/local/brisskit/i2b2/dbcmds/setupi2b2db.cmds'
sudo sed -i 's/i2b2-1.7-install-procedures-1.0-RC1-development/i2b2-1.7-install-procedures/g' /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/global/set.sh
sudo sed -i 's:/var/www/i2b2:/var/www/html/i2b2:g' /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/config/defaults.sh
source /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/global/set.sh
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/1-prerequisites.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/2-acquisitions.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/3-install-ant.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/4-install-jdk.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/5-install-jboss.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/6-data-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/7-pm-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/8-webclient-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/9-ont-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/A-crc-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/B-work-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/C-fr-install.sh first
sudo -E /var/local/brisskit/i2b2/i2b2-1.7-install-procedures/bin/installs/D-im-install.sh first
sudo unzip webclient_brisskit.zip -d /var/www/html/i2b2/
mv /var/local/brisskit/i2b2/i2b2UploaderWebapp.war /var/local/brisskit/i2b2/jboss/standalone/deployments/
sudo a2enmod proxy
sudo a2enmod proxy_http
sed -i '/DocumentRoot \/var\/www\/html/a \\n\tProxyPass /i2b2UploaderWebapp http://localhost:9090/i2b2UploaderWebapp\n\tProxyPassReverse /i2b2UploaderWebapp http://localhost:9090/i2b2UploaderWebapp' /etc/apache2/sites-available/000-default.conf
sudo service apache2 restart
echo "sudo shutdown -h now" | at now + 60 minutes
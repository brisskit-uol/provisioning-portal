# README #

This README details the steps required to host a i2b2 provisioning portal.

### Server installation ###

This has been tested on an AWS instance running Amazon Linux with PHP 5.5 and MySQL 5.5

1. [Create an IAM role](http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/iam-roles-for-amazon-ec2.html) to allow the creation of EC2 instances.
2. Launch an EC2 instance using Amazon Linux AMI using this role (tested using t2.micro with 8GB General Purpose (SSD) storage)
3. Install PHP 5.5 and MySQL 5.5
```
sudo yum install php55 php55-mysqlnd php55-pdo mysql55 mysql55-server
```
4. Start httpd and mysqld services
```
sudo service httpd start
sudo service mysqld start
```
5. Make httpd and mysqld start on boot
```
sudo chkconfig httpd on
sudo chkconfig mysqld on
```
6. Create database in MySQL
```
create database if not exists login;
```
7. Create tables in database using commands in `tables.sql`
8. Create user and grant permissions on database
```
CREATE USER 'newuser'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON databasename . * TO 'newuser'@'localhost';
```
9. Upload code to instance `/var/www/html/`
10. Modify database details in `includes/main.php`
11. PROFIT!

### Notes ###

The code contains the AWS SDK for PHP and the Guzzle 5 client which are separately maintained modules. Ideally they should be installed independently to benefit from code improvements.


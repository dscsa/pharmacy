#To Expose Server Via Comcast
1-800-391-3000 ask for Tech Support

1. Turn off Control Panel > Windows Firewall
2. Set IP info on networking card
  - Control Planel > Network and Sharing Center > Change Adapter Setting (left menu) > Right Click Ethernet Properties > IPV4 Properties
  - IP address 96.67.225.25 (this is one less than whatismyip.com provided ".26")
  - Subnet Mask 255.255.255.252
  - Default Gateway 96.67.225.26 (this is what whatismyip.com provided)
  - Preferred DNS 75.75.75.75
  - Alternate DNS 75.75.76.76
3. Run ipconfig /all to double check setting were saved
4. Goto gateway 96.67.225.26
  - Login with cusadmin, highspeed
  - Goto Advanced > Port Forwarding > Enable > Add Service > Custom > Add Server & Port
  - Goto Advanced > Port Management > Check box to disable all rules and allow inbound traffic

#Install SQLSRV (for MSSQL)
  - Download SQLSRV (3.2 for PHP5.6, 4.0 for PHP7.0+)
  - Extract into C:\Program Files (x86)\PHP\v5.6\ext
  - Edit C:\Program Files (x86)\PHP\v5.6\php.ini
  * add extension=php_sqlsrv_56_nts.dll
  * add sendmail_from=webform@goodpill.org
  * memory_limit = 256M
  * smtp_port = 587
  * do you want to turn on display_errors?
  * Install ODBC Driver 11 for SQL Server (x64 version: just double click the exe once downloaded)

#Get Email Working
- Open php.ini (see instructions in Install SQLSRV) and ensure that curl.cainfo is set.
If not, follow instructions here https://stackoverflow.com/questions/29822686/curl-error-60-ssl-certificate-unable-to-get-local-issuer-certificate
If this is not set the Gmail SMTP Plugin will show a 500 error when retrieving OAuth code
- Install Gmail SMTP plugin
- Follow instructions on https://wphowto.net/gmail-smtp-plugin-for-wordpress-1341

/****** OLD ******/
- Install SMTP Server Start Bar > Server Manager > Tools > Add New Services > Features > Check Box for SMTP Server > Instal
- Configure https://www.ruhanirabin.com/php-sendmail-setup-with-smtp-iis-and-windows-servers/
- Ensure you add limit connection and relay to 127.0.0.1 or you will spam people within days.
- Open the run dialog on the server, and enter services.msc.
- Locate the 'Simple Mail Transfer Protocol (SMTP)' service, right click, choose Properties and set the service to Automatic. From now on, it will start at boot.

#Installing SSH on windows
https://www.server-world.info/en/note?os=Windows_Server_2016&p=openssh

#Installing Git on windows
https://git-for-windows.github.io/

#Setup User on MSSQL
In Object Explorer > Server:
- Right Click Stored Procedure > Properties > Permission > Search > NT AUTHORITY\IUSR > OK > Grant Execute > OK

If not using windows authentication:
- Setup Login = Security (Right Click) > New > Login
- Create Users = Databases > cph > Security (Right Click) > New User
 * In General Add User Name, Login Name
 * In Membership check db_datareader, db_datawriter

# Wordpress on Linux (Amazon-AMI)

- Reference:http://coenraets.org/blog/2012/01/setting-up-wordpress-on-amazon-ec2-in-5-minutes/
- php 5.6 is last version that currently supports mssql: `sudo yum install php56`
- `sudo yum install php56-mysqlnd`
- Assuming encrypted EBS drive is named `sdb`:`sudo mkfs -t ext4 /dev/sdb`
- Put MySQL on the encrypted drive
- `sudo mkdir /goodpill`
- `sudo mount /dev/sdb /goodpill`
- `sudo mv /var/lib/mysql /goodpill/`
- `sudo ln -s /goodpill/mysql /var/lib/`
- `sudo mysql_secure_installation`
* Answer the wizard questions as follows:
**Enter current password for root: Press return for none
**Change Root Password: Y
**New Password: Enter your new password
**Remove anonymous user: Y
**Disallow root login remotely: Y
**Remove test database and access to it: Y
**Reload privilege tables now: Y
- Put Wordpress on the encrypted drive
- `sudo ln -s /goodpill/html /var/www`
- `cd /goodpill/html`
- `sudo wget http://wordpress.org/latest.tar.gz`
- `sudo tar -xzvf latest.tar.gz`
- `sudo mv wordress/* ./`

- Install FTP so Wordpress can install themes and plugins
- `sudo yum install vsftpd`
- `sudo nano /etc/vsftpd/vsftpd.conf`
*	change anonymous_enable to “=NO”
* Uncomment chroot_local_user=YES
*	add pasv_enable=YES
*	add pasv_min_port=1024
* add pasv_max_port=1048
* add pasv_address=<Public IP>
- `sudo usermod -d /goodpill/html adminsirum`
- `sudo chown -R adminsirum /goodpill/html`
- `sudo adduser adminsirum`
- `sudo passwd adminsirum`
- `sudo /etc/init.d/vsftpd restart`


- `mysqladmin -uroot create goodpill`
- `sudo service mysqld start`
- `sudo service httpd start`


# Wordpress on Windows
User Windows Platform Installer to install.
For Missing Dependency Errors:
- Server Manager > Manage > Add Roles and Features
* SERVER ROLES: Web Server IIS (15 of 43 needed)
* Features: SMTP Server
- Server Manager > Tools > Internet Information Services (IIS)
* Add Default site
* Binding http, port 80, IP = All Unassigned, hostname = *
* Default Document = index.php
* You may need to add IUSR as user to wordpress installation folder (with read and write)
* Handler Mappings = *.php, Module = FastCgiModule, Executable = D;\Program Files (x86)\PHP\v5.6\php-cgi.exe
* SSL Settings?
- Server Manager > Tools > IIS (v6.0)
* Run SMTP Server (don't remember how)
* Right Click > Properties > Access > Relay > Select All Except the List Below

#Install SSL Certs
- I installed Server Manager > Manage > Add Role and Features > Feature > DNS, but not sure if that is necessary or not.
- Set MIME type for "." (no) filetype (in IIS) to "text/plain" (this is needed for the authtoken to be public)
- Extract letsencrypt-win-simple.exe https://github.com/Lone-Coder/letsencrypt-win-simple/releases
- Run with domain and manual (-M) flag.  
- Enter a site (installation) path C:/Users/Administrator/wordpress
- If error, make sure token within .well-known directory is accessible via the provided domain
- Run IIS > Bindings > HTTPS > Edit... > Choose SSL Cert from the Dropdown

#Helpful File Locations
- MySQL Slow Queries: C:\ProgramData\MySQL\My SQL Server 5.1\Data\GoodPill-Server-Slow.log
- Wordpress: C:\Users\Administrator\Wordpress
- PHP Ini C:\Program Files (x86)\PHP\v5.6\php.ini (requires IIS restart)
- IIS Logs C:\inetpub\logs\Log Files\W3SVC2

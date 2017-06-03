#To Expose Server Via Comcast
1-800-391-3000 ask for Tech Support

1. Run node server on desired port
2. Turn off Control Panel > Windows Firewall
3. Set IP info on networking card
  - Control Planel > Network and Sharing Center > Change Adapter Setting (left menu) > Right Click Ethernet Properties > IPV4 Properties
  - IP address 96.67.225.25 (this is one less than whatismyip.com provided ".26")
  - Subnet Mask 255.255.255.252
  - Default Gateway 96.67.225.26 (this is what whatismyip.com provided)
  - Preferred DNS 75.75.75.75
  - Alternate DNS 75.75.76.76
4. Run ipconfig /all to double check setting were saved
5. Goto gateway 96.67.225.26
  - Login with cusadmin, highspeed
  - Goto Advanced > Port Forwarding > Enable > Add Service > Custom > Add Server & Port
  - Goto Advanced > Port Management > Check box to disable all rules and allow inbound traffic

#Get Email Working
- Install SMTP Server Start Bar > Server Manager > Tools > Add New Services > Features > Check Box for SMTP Server > Instal
- Configure https://www.ruhanirabin.com/php-sendmail-setup-with-smtp-iis-and-windows-servers/

#Installing SSH on windows
https://www.server-world.info/en/note?os=Windows_Server_2016&p=openssh

#Installing Git on windows
https://git-for-windows.github.io/

#Setup User on MSSQL
In Object Explorer > Server:
- Setup Login = Security (Right Click) > New > Login
- Create Users = Databases > cph > Security (Right Click) > New User
 * In General Add User Name, Login Name
 * In Membership check db_datareader, db_datawriter

# Wordpress
User Windows Platform Installer to install.
For Missing Dependency Errors:
- Server Manager > Manage > Add Roles and Features > Server Roles
* Web Server IIS (15 of 43 needed)
- Server Manager > Tools > Internet Information Services (IIS)
* Add Default site
* Binding http, port 80, IP = All Unassigned
* Default Document = index.php
* You may need to add IUSR as user to wordpress installation folder (with read and write)
* Handler Mappings = *.php, Module = FastCgiModule, Executable = D;\Program Files (x86)\PHP\v5.6\php-cgi.exe
* SSL Settings?

#Install SQLSRV (for MSSQL)
- Download SQLSRV (3.2 for PHP5.6, 4.0 for PHP7.0+)
- Extract into C:\Program Files (x86)\PHP\v5.6\ext
- Edit C:\Program Files (x86)\PHP\v5.6\php.ini
* add extension=php_sqlsrv_56_nts.dll
* Install ODBC Driver 11 for SQL Server (x64 version: just double click the exe once downloaded)

#Install SSL Certs
npm install -g letsencrypt-cli

letsencrypt certonly --agree-tos --email adam@sirum.org --standalone --config-dir C: /Users/Administrator/letsencrypt --domains webform.goodpill.org --server https://acme-v01.api.letsencrypt.org/directory --renew-within 60

Get Allergy Syncing Working
Fix Mismatched Patient
Unclassified Orders in WC
Understand Created Item Readded.
ERROR: Missing Order Item!
Created Item Missing.
"Shipped/Paid WC not in Guardian. Delete/Refund?
Get rid of old WC Order Statuses
 1168 deleted patients in Guardian
 Clear out unused WC Meta_Keys

Review and Switch CommCalendar
Review and Switch Invoices
Implement, Review, and Switch Fax Out Transfers

# Helpful Queries
```
Ensure Unique GSNs:
  SELECT GROUP_CONCAT(drug_generic, '; '), drug_gsns, COUNT(*) as number
  FROM `gp_stock_live`
  GROUP BY drug_gsns
  HAVING number > 1

Review Duplicated WC Order Data:
  SELECT post_id, meta_key, COUNT(*) as number, meta_value
  FROM `wp_postmeta`
  GROUP BY post_id, meta_key, meta_value
  HAVING number > 1

Remove Duplicated WC Order Data by Key:
  DELETE t1 FROM wp_postmeta t1
  INNER JOIN wp_postmeta t2
  WHERE
    t1.meta_id < t2.meta_id AND
    t1.post_id = t2.post_id AND
    t1.meta_key = t2.meta_key AND
    t1.meta_value=t2.meta_value AND
    t1.meta_key = 'invoice_doc_id' AND
    -- t1.post_id = 32945

Remove Duplicated Orders/Invoice Numbers:
  DELETE t1 FROM wp_postmeta t1
  INNER JOIN wp_postmeta t2
  JOIN wp_posts ON t1.post_id = wp_posts.ID
  WHERE
    t1.meta_id < t2.meta_id AND
    t1.meta_key = 'invoice_number' AND
    t1.meta_value = t2.meta_value AND
    (wp_posts.post_status LIKE 'wc-prepare-%' OR wp_posts.post_status LIKE 'wc-confirm-%')

Review Orders that have NO invoice number:
  SELECT *
  FROM wp_posts
  LEFT JOIN wp_postmeta ON post_id = ID AND meta_key = 'invoice_number'
  WHERE post_type = 'shop_order'
  AND post_status != 'trash'
  AND meta_id IS NULL
  ORDER BY ID ASC

Review Orders that have a DUPLICATE invoice number:
  SELECT meta_key, meta_value, GROUP_CONCAT(post_status), COUNT(*) as number
  FROM `wp_postmeta`
  JOIN wp_posts ON post_id = wp_posts.ID
  WHERE meta_key = 'invoice_number'
  GROUP BY meta_value
  HAVING number > 1

Review Patients that have a DUPLICATE patient_id_cp:
  SELECT GROUP_CONCAT(user_login), GROUP_CONCAT(user_id), meta_key, meta_value, COUNT(*) as number
  FROM `wp_usermeta`
  JOIN wp_users ON user_id = wp_users.ID
  WHERE meta_key = 'patient_id_cp'
  GROUP BY meta_value
  HAVING number > 1

Delete Duplicate Invoice Number.  

  SELECT * FROM wp_usermeta t1
  INNER JOIN wp_usermeta t2
  JOIN wp_users ON t1.user_id = wp_users.ID
  WHERE
    t1.umeta_id < t2.umeta_id AND
    t1.meta_key = 'patient_id_cp' AND
    t1.meta_value = t2.meta_value AND
    t1.meta_value > 0

WARNING THIS DELETED MORE ROWS THAN EXPECTED!!! TEST WITH SELECT ABOVE FIRST  
  DELETE t1 FROM wp_usermeta t1
  INNER JOIN wp_usermeta t2
  JOIN wp_users ON t1.user_id = wp_users.ID
  WHERE
    t1.umeta_id < t2.umeta_id AND
    t1.meta_key = 'patient_id_cp' AND
    t1.meta_value = t2.meta_value AND
    t1.meta_value > 0
*/
```

#To Expose Server Via Comcast
1-800-391-3000 ask for Tech Support

1. Turn off Control Panel > Windows Firewall
2. Set IP info on networking card
  - Control Planel > Network and Sharing Center > Change Adapter Setting (left menu) > Right Click Ethernet Properties > IPV4 Properties
  - Subnet Mask 255.255.255.252
  - Preferred DNS 75.75.75.75
  - Alternate DNS 75.75.76.76
3. Run ipconfig /all to double check setting were saved
4. Goto gateway provided by Internet Service Provider (Comcast)
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

#Linux Get Email Working
`sudo nano /etc/php56.ini`
sendmail add flag `-f hello@sirum.org`

#Setup User on MSSQL
In Object Explorer > Server:
- Right Click Stored Procedure > Properties > Permission > Search > NT AUTHORITY\IUSR > OK > Grant Execute > OK

If not using windows authentication:
- Setup Login = Security (Right Click) > New > Login
- Create Users = Databases > cph > Security (Right Click) > New User
 * In General Add User Name, Login Name
 * In Membership check db_datareader, db_datawriter

# Wordpress on Linux (Amazon-AMI)

Good Reference:http://coenraets.org/blog/2012/01/setting-up-wordpress-on-amazon-ec2-in-5-minutes/

php 5.6 is last version that currently supports mssql:
```
sudo yum update`
sudo yum install php56
sudo yum install php56-mbstring
sudo yum install php56-mssql
sudo yum install php56-gd //Wordpress needs image library to crop & resize
```

```
sudo nano /etc/php.ini
- sendmail_from = "webform@goodpill.org"
- sendmail_path = /usr/sbin/sendmail -t -i -f webform@goodpill.org
- smtp_port = 25
- expose_php = Off
- max_execution_time = 300
- memory_limit = 900M
- post_max_size = 300M
- upload_max_filesize = 300M
- max_input_time = 300
- date.timezone = 'UTC'
- display_errors = On
- error_log = "/goodpill/html/php_errors.log"
```

MYSQL
`sudo yum install mysql57-server`
`sudo yum install php56-mysqlnd`

Assuming encrypted EBS drive is named
`sdb`:`sudo mkfs -t ext4 /dev/sdb`

Put MySQL on the encrypted drive
`sudo mkdir /goodpill`
`sudo mount /dev/sdb /goodpill`
`sudo mv /var/lib/mysql /goodpill/`
`sudo ln -s /goodpill/mysql /var/lib/`

Put Wordpress on the encrypted drive
`sudo wget http://wordpress.org/latest.tar.gz`
`sudo tar -xzvf latest.tar.gz`
`sudo mv wordpress /goodpill/html`
`sudo rm -R /var/www/html`
`sudo ln -s /goodpill/html /var/www`
`sudo cp /goodpill/html/wp-config-sample.php /goodpill/html/wp-config.php`
`sudo nano /goodpill/html/wp-config.php`
- Copy over all constants from old file

Update Permissions
`sudo mkdir /goodpill/html/wp-content/uploads`
`sudo find /goodpill/html/ -type d -exec chmod 755 {} \;`
`sudo find /goodpill/html/ -type f -exec chmod 644 {} \;`
`sudo chmod 400 /goodpill/html/wp-config.php`
`sudo chown -R apache:apache /goodpill/html`

Configure Apache
`sudo yum install httpd24`
`sudo yum install openssl`
`sudo yum install mod24_ssl`
`sudo nano /etc/httpd/conf/httpd.conf`
- Change `DocumentRoot` to `/goodpill/html`
- Change `<Directory "/var/www">` to `<Directory "/goodpill">` and `Override None` to `Override All`
- Change `<Directory "/var/www/html">` to `<Directory "/goodpill/html">` and `Override None` to `Override All`
- Add within `<Directory "/var/www/html">`
`# BEGIN WordPress
      RewriteEngine On
      RewriteBase /
      RewriteRule ^index\.php$ - [L]
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteRule . /index.php [L]
# END WordPress`
- Change `DirectoryIndex index.html` to `DirectoryIndex index.html index.php`
//Necessary? - Add `AddType application/x-httpd-php .php`
- Add
`<VirtualHost *:443>
      SSLEngine on
      SSLCertificateFile /goodpill/ssl/goodpill.org.pem
      SSLCertificateKeyFile /goodpill/ssl/goodpill.org.key
      SSLCertificateChainFile /goodpill/ssl/goodpill.org.root
</VirtualHost>`

Test
`sudo service mysqld start`
`sudo service httpd start`
`sudo nano /etc/httpd/logs/error_log` //To debug
- Goto Public IP

Add Swap Memory of mysql will crash: http://danielgriff.in/2014/add-swap-space-to-ec2-to-increase-performance-and-mitigate-failure/

Setup and secure MySQL
`sudo mysql_secure_installation`
- Enter current password for root: Press return for none
- Change Root Password: Y
- New Password: Enter your new password
- Remove anonymous user: Y
- Disallow root login remotely: Y
- Remove test database and access to it: Y
- Reload privilege tables now: Y
`sudo nano /etc/my.cnf`
- Add `max_allowed_packet=300M` //so we can import a large SQL file


Install PHPMyAdmin
- Find latest version http://www.phpmyadmin.net/home_page/downloads.php
`cd /goodpill/html`
`sudo wget https://files.phpmyadmin.net/phpMyAdmin/<version>/phpMyAdmin-<version>-all-languages.tar.gz`
`sudo tar -xzvf phpMyAdmin-<version>-all-languages.tar.gz`
`sudo mv phpMyAdmin-<version>-all-languages wp-goodpill`
`cd /goodpill/html/wp-goodpill`
`sudo mkdir config`
`sudo chmod o+rw config`
`sudo cp config.sample.inc.php config/config.inc.php`
`sudo chmod o+w config/config.inc.php`
`mysqladmin -u root create goodpill` or browse to `<public_ip>/wp-goodpill`, login, and create `goodpill` database

Transfer Data
- Exit out of ssh and then `scp -v -r -i /Volumes/EC2/sirum_ec2_key.pem /Users/adam/Downloads/<localSQL> ec2-user@<ip address>:`
`sudo gunzip <filename>`
`sudo mysql -u root -p goodpill < file.sql`
If needed, goto goodpill > wp_options > site_url/home to <public IP>

Install FTP so Wordpress can install themes and plugins
`sudo yum install vsftpd`
`sudo nano /etc/vsftpd/vsftpd.conf`
- change anonymous_enable to “=NO”
- uncomment chroot_local_user=YES
- add
`pasv_enable=YES
pasv_min_port=1024
pasv_max_port=1048
pasv_address=<Public IP>`

`sudo service vsftpd restart`
- Debug `sudo nano /var/log/xferlog`

Install Wordpress
Browse to `/`, login, and install wordpress and db updates as necessary
- Install Storefront Theme
- Install Custom Order Status for WooCommerce
- Install Fast User Switching
- Install My Custom functions
- Install Username Changer
- Install WC Duplicate Order
- Install WooCommerce
- Install WooCommerce Admin
- Install WooCommerce Stripe Gateway
- Copy and Pase Woo.php into Settings > PHP Inserter

Fix IP Addresses
- WP Settings > General > Actual URL
- In AWS Console, move elastic IP to new server OR
- Switch CloudFlare/Siteground to new IP and test
- To get WP Pages to Save you may need to goto WP Setting Permalinks -> Plain (Save) -> (Re)Update Each Page
-> Post Name (Save)


* Edit .htaccess to remove index.php from URLs
- sudo nano /etc/httpd/conf/httpd.conf
*
* Add
*  LogLevel rewrite:trace3 # if htaccess troubles
-

`sudo usermod -d /goodpill/html adminsirum`
`sudo chown -R adminsirum /goodpill/html`
- `mysqladmin -uroot create goodpill`
- `sudo service mysqld start`
- `sudo service httpd start`

- Change page permalink from my-account to account
- Change woocommerce links
- Change wordpress settings > permalink's to post-name

Install Webform
```
sudo yum install git
sudo git clone https://github.com/dscsa/webform (sudo git pull to update)
sudo nano /goodpill/webform/keys.php //Copy & paste keys over
sudo crontab -e
- "i" for insert mode, "esc" to exit insert mode
- Add
MAILTO=<info email>
*/5 * * * * sudo php /goodpill/webform/cronjobs/syncing.php
- Shift ZZ to Save & Exit
```

Install SSL Certs
- Idea is that we can skip LetsEncrypt and just use CloudFlare for our public certificates and use a self-signed certificate for cloudflare to connect to
- Set CloudFlare > Cyrpto > SSL > "Full" (which works with self-signed certs)
- Update httpd.conf
```
# Redirect http://example.com and http://www.example.com to main site
<VirtualHost *:80>
#  SSLEngine on
#  SSLCertificateFile /goodpill/ssl/certificate.pem
#  SSLCertificateKeyFile /goodpill/ssl/key.pem
  ServerName www.goodpill.org
  ServerAlias goodpill.org

  Redirect / https://www.goodpill.org/
</VirtualHost>
```

Install Composer & Logging
```
cd ~
sudo curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer -V

cd /goodpill
composer require google/cloud-logging

```





## OLD Wordpress on Windows
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

- Make a self-signed certs `sudo openssl req -x509 -nodes -days 2000 -newkey rsa:2048 -keyout /goodpill/ssl/key.pem -out /goodpill/ssl/certificate.pem`

#Install SSL Certs (OLD)
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

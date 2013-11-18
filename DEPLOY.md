99chan (development) Deployment Procedure
=========================================
* Ubuntu 12.04
* Get your shell set up to your liking.
### Make sure we have the most recent packages.

	[duchess@dev:~] sudo apt-get update

### Install git!

	[duchess@dev:~] sudo apt-get install git-core

### Change to web dir

	[duchess@dev:~] cd /var/www

### Change perms

	[duchess@dev:www] sudo chown www-data:www-data .

	[duchess@dev:www] sudo chmod g+w .

* Remove index.html.

	[duchess@dev:www] sudo rm index.html

* Change your user`s primary group to www-data.

	[duchess@dev:www] sudo usermod -g www-data duchess

* Log out and log back in.

	[duchess@dev:www] exit

	[duchess@graphene:~] ssh duchess@99dev

* Add your pubkey (id_dsa.pub) to github.

	[duchess@dev:~] ssh-keygen -t dsa

	[duchess@dev:~] cat .ssh/id_dsa.pub

Copy and paste this into your github public keys page.

/***
  * Change directory to /var/www.
***/
cd /var/www/

/***
  * Set up git.
***/
git init
git remote add origin git@github.com:99chan-dev/morbid_obesity.git

/***
  * Git your repo.
***/
git pull origin master

/***
  * Install mysql
***/
sudo apt-get install mysql-server libapache2-mod-auth-mysql php5-mysql

set root pw

mysql_install_db

mysql_secure_installation

/***
  * Install php
***/
sudo apt-get install php5 libapache2-mod-php5 php5-mcrypt

/***
  * Copy over config.php.
***/
exit

scp duchess@99chan:/var/www/html/config.php .

scp config.php duchess@99dev:/var/www

/***
  * Add pluppawhat user to mysql
***/
mysql

create database new99;

create user 'pluppawhat'@'localhost' identified by '********';

grant all privileges on new99.* to 'pluppawhat'@'localhost';

exit;

/***
  * Copy db dump over to new server and import it.
***/
exit

scp 99chan.sql duchess@99dev:

ssh duchess@99dev

mysql -u root -p new99 < 99chan.sql

/***
  * Create the templates_c directory and ensure it's writeable.
***/
cd /var/www/dwoo/

mkdir templates_c

chmod g+w templates_c/

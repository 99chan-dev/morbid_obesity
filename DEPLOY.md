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

### Remove index.html.

	[duchess@dev:www] sudo rm index.html

### Change your user`s primary group to www-data.

	[duchess@dev:www] sudo usermod -g www-data duchess

### Log out and log back in.

	[duchess@dev:www] exit
	[duchess@graphene:~] ssh duchess@99dev

### Add your pubkey (id_dsa.pub) to github.

	[duchess@dev:~] ssh-keygen -t dsa
	[duchess@dev:~] cat .ssh/id_dsa.pub

* Copy and paste this into your github public keys page.

### Change directory to /var/www.

	[duchess@dev:~] cd /var/www/

### Set up git.

	[duchess@dev:www] git init
	[duchess@dev:www] git remote add origin git@github.com:99chan-dev/morbid_obesity.git

### Git your repo.

	[duchess@dev:www] git pull origin master

### Install mysql

	[duchess@dev:www] sudo apt-get install mysql-server libapache2-mod-auth-mysql php5-mysql

### Set mysql root pw

	[duchess@dev:www] mysql_install_db
	[duchess@dev:www] mysql_secure_installation

### Install php

	[duchess@dev:www] sudo apt-get install php5 libapache2-mod-php5 php5-mcrypt

### Copy over config.php.

	[duchess@dev:www] exit
	[duchess@graphene:~] scp duchess@99chan:/var/www/html/config.php .
	[duchess@graphene:~] scp config.php duchess@99dev:/var/www

### SSH back to dev

	[duchess@graphene:~] ssh duchess@99dev 

### Add pluppawhat user to mysql

	[duchess@dev:~] mysql
	mysql> create database new99;
	mysql> create user 'pluppawhat'@'localhost' identified by '********';
	mysql> grant all privileges on new99.* to 'pluppawhat'@'localhost';
	mysql> exit;

### Copy db dump over to new server and import it.

	[duchess@dev:~] exit
	[duchess@graphene:~] scp 99chan.sql duchess@99dev:
	[duchess@graphene:~] ssh duchess@99dev
	[duchess@dev:~] mysql -u root -p new99 < 99chan.sql

### Create the templates_c directory and ensure it`s writeable.

	[duchess@dev:~] cd /var/www/dwoo/
	[duchess@dev:dwoo] mkdir templates_c
	[duchess@dev:dwoo] chmod g+w templates_c/

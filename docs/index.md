
<p align="center"><img src="https://arliden.com/images/kilvin-icon-small.png"></p>


# Kilvin CMS

## About Kilvin

Kilvin CMS is a content management system built on top of the [Laravel framework](https://laravel.com). The project is currently in a development state and is not ready for use in production. We do not suggest using this for live websites at this time since major architectural changes are still possible, which could break existing functionality. This means you, Mark.


## Demo

There is now a demo for developers wishing to preview Kilvin CMS. The database has been locked down so no form submissions will work.

- [Demo Control Panel](https://demo.kilvincms.com/admin.php)
 - Email Address: `demo@kilvincms.com`
 - Password: `suprasecretpassword`


## Installing Kilvin CMS

### Server Requirements
 - PHP 7.0 or later with safe mode disabled
 - MySQL 5.5.0 or later, with the InnoDB storage engine installed. MariaDB works too.
 - A web server (Apache, Nginx)
 - OpenSSL PHP Extension
 - PDO PHP Extension
 - Mbstring PHP Extension
 - Tokenizer PHP Extension

### Installation

 - Insure you have a server meeting the above requirements. [Laravel Homestead](https://laravel.com/docs/5.4/homestead) is a superb development environment for Kilvin CMS.
 - Clone this GitHub repo onto your server.
 - In your terminal, run the following [Composer](https://getcomposer.org) command in your cloned directory to install Kilvin's dependencies: `composer create-project --prefer-dist`.
 - Permissions. Insure that the following files and directories are writeable on your server. Homestead is set up to allow this automatically:
   - .env
   - cms/storage
   - cms/templates
   - public/images
 - Create a database for your new site in MySQL/MariaDB.
 - Configure your webserver to make the `./public` directory your web root.
 - Direct your browser to the install.php file on your new site and run the installer. Example: http://mysite.com/install.php


## Multiple Sites

 - Weblogs, Fields, Categories, Statuses, Member Groups, Members, and most preferences are global and NOT site-specific
 - Templates and Stats are Site-specific
 - Member Groups have permissions that allow you to restrict them to only certain Sites, Weblogs, and Statuses


## Kilvin CMS Sponsors

We would like to extend our thanks to the following sponsors for helping fund Kilvin CMS development. If you are interested in becoming a sponsor, please visit the Kilvin CMS [Patreon page](http://patreon.com/reedmaniac):

- **[Paul Burdick](https://paulburdick.me)** - The laziest man on the planet.



## Security Vulnerabilities

If you discover a security vulnerability within Kilvin CMS, please send an e-mail to Paul Burdick at paul@reedmaniac.com. All security vulnerabilities will be promptly addressed.

## License

The Kilvin CMS is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).


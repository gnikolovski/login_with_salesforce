CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

This module allows users to register and login to your Drupal site with their
Salesforce account. It works by adding "Login with Salesforce" link to the user
login form.


REQUIREMENTS
------------

This module requires no modules outside of Drupal 8 core.


INSTALLATION
------------

 * Install the Login with Salesforce module as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.

 * To download the module, enter the following command at the root of your site:

 ```shell
composer require gnikolovski/login_with_salesforce
 ```


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to /admin/config/services/login-with-salesforce and enter your
       Salesforce app data. If you don't have a connected app, go to your
       account and create it. Once you have created your connected app, note
       down its consumer key, consumer secret and redirect_url, you'll be
       needing those to properly configure the module.


MAINTAINERS
-----------

Current maintainers:
 * Goran Nikolovski (gnikolovski) - https://www.drupal.org/u/gnikolovski

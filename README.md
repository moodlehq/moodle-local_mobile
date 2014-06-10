moodle-local_mobile
===================

Local plugin for adding new features to the current Moodle Mobile app.

This add-on provides new features and web services which are currently only available in Moodle 2.7.

How it works
============

Once installed the plugin creates a new service "Moodle Mobile additional features".

The Mobile app checks if this service is enabled. If not, the Mobile app falls backs to the standard core Mobile app service.

Features
========

* Support for sites using SSO authentication methods (Shibboleth and CAS)
* Push notifications
* Retrieval of private messages and notifications from the site
* Retrieval of calendar events from the site

Installation
============

1. Unpack the zip file into the local/ directory. A new directory will be created called local/mobile.
2. Go to Site administration > Notifications to complete the plugin installation.
3. Go to Site administration > Plugins > Web services > External services, edit "Moodle Mobile additional features" and check the "Enabled" field, then save changes.
4. Go to Site administration > Users > Define roles, edit the Authenticated user role and allow the capability moodle/webservice:createtoken.

Note: You need to have upgraded the Moodle Mobile app to version 1.4.4.

If you are currently using the Moodle Mobile app, you will need to log out of all your sites in order for the app to detect the new service.
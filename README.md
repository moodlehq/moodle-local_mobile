moodle-local_mobile
===================

Local plugin for adding new features to the current Moodle Mobile app.

This add-one includes new features, new Web services and Web services only available in latest Moodle versions.

How it works
============

Once instaled the plugin creates a new service "Moodle Mobile additional features".

The Mobile app checks if that service is enabled, if doesn't, it fallbacks to the standard core Mobile app service.

New features
============

Support for sites using SSO authentication methods (Shibboleth and CAS) for Moodle 2.4 and onwards.

Push notifications (Moodle 2.4 and onwards)

Retrieve private messages and notifications from the site (Moodle 2.4 and onwards)

Retrieve calendar events from the site (Moodle 2.4 and onwards)

Installation
============

Unpack the zip file into the local/ directory. It will create a new directory called local/mobile

Go to Site administration / Notifications for finishing the plugin installation.

Go to  Site administration / Plugins / Web services / External services, edit the "Moodle Mobile additional features" and check the "Enabled" field. Save changes.

Go to Site administration / Users / Define roles. Edit the Authenticated user role. Allow the "Create a web service token"
(moodle/webservice:createtoken) capability.

You need to have upgraded the Mobile app to version 1.4.4

If you are currently using the app, you need to logout of all your sites so the app can detect the new service.
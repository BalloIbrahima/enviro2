=== Backuply - Backup, Restore, Migrate and Clone ===
Contributors: softaculous, backuply
Tags: backup, restore, database backup, cloud backup, wordpress backup, migration, cloning, backuply, local backup, amazon s3, database, google drive, gdrive, dropbox, FTP, SCP, SFTP, onedrive, WebDAV
Requires at least: 4.7
Tested up to: 6.0
Requires PHP: 5.5
Stable tag: 1.0.4
License: LGPL v2.1
License URI: http://www.gnu.org/licenses/lgpl-2.1.html

Backup and restores with Backuply are fairly simple with a wide range of storage options from Local Backups, FTP to cloud options like AWS S3, Dropbox, Google Drive, SFTP, FTPS, WebDav.

== Description ==

Backuply is a WordPress plugin that helps you backup your WordPress website, saving you from loss of data because of server crashes, hacks, dodgy updates, or bad plugins.

Backuply comes with Local Backups and Secure Cloud backups with easy integrations with FTP, FTPS, SFTP, WebDAV, Google Drive, Microsoft OneDrive, Dropbox, Amazon S3 and easy One-click restoration.

Your website is your asset and it needs to constantly be protected from various security issues, server issues, hacking, etc. While you take all precautionary steps to protect your website, backups are the best form of security. With Backuply, you can be confident that your data is protected and is always available for restore during any disaster. Backuply creates full backups of your website and you can restore it to the same or a new WordPress website by the click of a button.

Our backup and website cloning technology has been in use for more than a decade and we have now ported it to WordPress. 
 
You can find our official documentation at [https://backuply.com/docs](https://backuply.com/docs). We are also active in our community support forums on wordpress.org if you are one of our free users. Our Premium Support Ticket System is at [https://softaculous.deskuss.com/open.php?topicId=17](https://softaculous.deskuss.com/open.php?topicId=17)

[Home Page](https://backuply.com "Backuply Homepage") | [Support](https://softaculous.deskuss.com/open.php?topicId=17 "Backuply Support") | [Documents](http://backuply.com/docs "Documents")



== Features ==
* **Local Backups:** Backup your complete website locally on your server by just one click.
* **FTP:** Easily backup and restore your backup using FTP.
* **Backup to Google Drive**
* **One-Click Restore:** Restore your website files and databases with one-click restore.
* **Migration:** Stress-free migration to any domain or host.
* **Database Backups:** Backup your website's database only.

== Premium Features == 

* **Automatic Backups:** Choose to backup your website at a regular intervals like Daily, Weekly, Monthly. You can also customize the interval.
* **One-click Restore :** With Backuply, restoring your website is simple. Just click on the restore button next to the backup you want to restore from. Your entire backup will be downloaded and the changes will be applied to the website.
* **Selective Backup :** You have the option to choose from whether only files or database backups or full backups should be performed.
* **Website Migration :** You can easily migrate your website by restoring from one of the Cloud Backup options on the new website.
* **Website Cloning :** If you would like to clone your website for any purpose, Backuply can do that for you. Backuply will restore the data, but replace the URLs and information as per the existing website. In this way you can create multiple clones.
* **Backup to FTPS :** You can backup your site to an FTPS i.e. FTP over SSL / TLS.
* **Backup to SFTP :** Supports the SFTP protocol.
* **Backup to Dropbox**
* **Backup to Microsoft One Drive**
* **Backup to Amazon S3**
* **Backup to WebDAV**
* **Backup to S3 Compatible Storages :** Added support for DigitalOcean Spaces, Linode Obejct Storage, Vultr Object Storage, and Cloudflare R2.
* **Professional Support :** Get professional support and more features to make backup your website with [Backuply](https://backuply.com/pricing)


== Backups ==
Backup is a way of copying your data or files in a secure place, which can be used to restore your website in case of data loss. Backups are vital in securing the data that you have published or written. Backups with Backuply are easy and secure with support for multiple options of storage like local storage using FTP or using third-party services like Google Drive, Dropbox, Microsoft OneDrive, AWS S3 and WebDAV.
To make it even easier we support Automatic Backups with a customizable backup schedule.


== Restores == 
Restoring is just a One-Click process using Backuply. If the selected backup is available then Backuply will restore your backups safely. Restoring a backup will roll back your site in the exact same state as it was when the backup was created.


== Migration ==
Backuply creates a tar file of your whole WordPress install with the Database, so you can migrate your site to any host or location where WordPress can be installed. All you need to do is create a Backup of your WordPress install on a remote location, and that's it, It can be synced on any WordPress install with ease so you just need to restore the synced backup on the new location for Migration to happen.


== Frequently Asked Questions ==

Do you have questions related to Backuply? Use the following links :

1. [Docs](https://backuply.com/docs)
3. [Help Desk](https://backuply.deskuss.com)
2. [Support Forum](http://wordpress.org/support/plugin/backuply)

== How to install Backuply ==
Go To your WordPress install -> Plugins -> Add New Button -> In Search Box search For Backuply -> Click on Install.

== Screenshots ==

1. **Dashboard** manual backup and info.
2. **Settings** set backup settings like backup location, backup options and email to notify.
3. **Backup Locations** add remote locations to backup and restore from.
4. **Backup History** manage all your backups.
5. **Restore Process** easy to understand restore progress.
6. **Add Backup Location** with a fairly simple form to add backup location.
7. **Backup Process** easy to understand backup progress.

== Changelog ==

= 1.0.4 (October 14, 2022) =
* [Feature] Added support for S3 Compatible backup locations like DigitalOcean Spaces, Linode Object Storage, Vultr Object Storage, and Cloudflare R2.
* [Feature] Added support for Server Side Encryption for AWS.
* [Task] Google Drive is now available for all users.
* [Bug-Fix] Part Number while downloading for restore in AWS had an issue. That has been fixed.
* [Bug-Fix] For some user restore was getting stuck at repairing database status, that has been fixed.

= 1.0.3 (September 20, 2022) =
* [Improvement] Added Backup Download progress
* [Task] The Backuply nag will now appear after 7 days instead of 1 day.
* [Bug-Fix] The last backup time was shown from 1970 when no backup was created. This has been fixed.
* [Bug-Fix] Backup on Google were failing for some users. This has been fixed.
* [Bug-Fix] On failure, in some cases the partial backup file was not cleaned. This has been fixed.
* [Bug-Fix] At times, the backup nag was not getting dismissed. This has been fixed.

= 1.0.2 (August 18, 2022) =
* Exclude Files, Directories or Database tables from backup
* Logs for every backup
* Minor bug fixes

= 1.0.1 (July 22, 2022) =
* Added Last Logs of Backups and Restore

= 1.0.0 (July 21, 2022) =
* Released Plugin



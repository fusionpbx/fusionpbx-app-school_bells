# School Bells App

This gives periodic signals to phones or SIP-Speakers in a `cron`-style way

---
## Install HOW TO
1. `# cd /var/www/fusionpbx`
2. `# git clone https://github.com/fusionpbx/fusionpbx-app-school_bells school_bells`
3. `# chown -R www-data:www-data /var/www/fusionpbx`
4. `# php /var/www/fusionpbx/core/upgrade/upgrade.php`
5. Add `* * * * * php //var/www/fusionpbx/app/school_bells/school_bells_cron.php` to your *crontab*
6. Go to GUI
7. Upgrades -> *SCHEMA*; *APP DEFAULTS*; *MENU DEFAULTS*; *PERMISSION DEFAULTS*
8. Log out and back in
9. *Apps* -> *School Bells*
---
By initial design, the app allows you to ring an extension for a certain amount of time (via Ring Timeout parameter), or to playback recoding after the extension answers.

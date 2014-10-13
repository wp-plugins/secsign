=== SecSign ===
Contributors: SecSign
Tags: two-factor authentication, two-factor, authentication, 2 factor authentication, login, sign in, single sign-on, challenge response, rsa, password, mobile, iphone, android, security, authenticator, authenticate, two step authentication, 2fa
Requires at least: 3.0.1
Tested up to: 4.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use the SecSign ID two-factor authentication on your WordPress site to enable easy and secure login using your iPhone or Android phone.

== Description ==

SecSign ID - The mobile way to log into web sites


SecSign ID is a plugin for real two-factor authentication (2FA) for Wordpress sites. 2FA adds another layer of security to your website by using a second token. In this case the physical token is your smartphone. 
If you seek for more information about about two-factor authentication have a look at [https://www.secsign.com](https://www.secsign.com/two-factor-authentication/).


* Integrate SecSign ID into your own WordPress site in less than one minute. (There are also APIs for PHP and Java.)
* You and your users can also use SecSign ID to visit securely other web sites (e.g. [portal.secsign.com](https://portal.secsign.com) for truly professional messaging and cloud sharing.)
* This service is free for users and web site owners and free of advertising - no matter how many users the web site has.
* You can also integrate SecSign ID as in-house solution into your existing infrastructure (on request with licensed service and maintenance contract)


[youtube http://www.youtube.com/watch?v=DNjrbEuMB7Y]

SecSign ID features:

* Quick and easy to use single sign-on with 2048-bit high security
* Eliminates password chaos and security concerns
* No mobile number, credit card or time-consuming registration required
* No need for long cryptical passwords, time-consuming retyping of codes from SMS or reading of QR codes
* High security and strong cryptography on all levels

Technical details (only for experts):

* Up to 2048-bit asymmetric private keys
* Brute force resistant private key storage (SafeKey mechanism)
* Private keys are never transmitted to the authentication server
* High availability through redundant remote failover servers
* Multi-tier high security architecture with multiple firewalls and protocol filters

More information at [https://www.secsign.com](https://www.secsign.com/security-id/).

SecSign ID in action:

1. Get the app for [iPhone](https://itunes.apple.com/app/secsign/id581467871) or [Android](https://play.google.com/store/apps/details?id=com.secsign.secsignid)
2. Choose a unique user short name
3. Choose a short PIN to secure your SecSign ID on your phone

That's it! You can now use your SecSign ID to sign in.

How to sign in:

Just type in your user short name (for instance at [SecSign Portal](https://portal.secsign.com) or your WordPress site using this plugin), confirm your sign-in on your phone and you are done within seconds.

Despite its simplicity SecSign ID works with comprehensive and strongest security technologies. The solution we offer is unique and does not submit any confidential data through a web browser.

We have a noticeable background of more than 14 years in developing strong cryptography and highly sophisticated security software products for governments, public institutions and private companies.

Visit our official site to get the app and more information: [https://www.secsign.com](https://www.secsign.com)


and check out our [flyer](https://www.secsign.com/secsign_portal_flyer.pdf).

== Installation ==

= Install the Plugin =

* Login into WordPress as admin, go to the plugins screen and select the "Add New" submenu.
* Search for "SecSign" and click "Install Now" or click on "Upload" and select the downloaded zip archive.
* Activate the plugin in the "Installed Plugins" list.

= Add the Login Widget =

* You can add the SecSignID login widget to your site to allow the login on, for example, the side menu.
* Go to the "Appearance" screen and click on the "Widgets" submenu.
* Drag and drop the "SecSign ID Login" widget to, for example, the "Main Sidebar"

= General Configuration =

* Go to the "Settings" screen and select the "SecSign ID Login" submenu.
* Change the service address which will be shown to the user in the smartphone app. This should match the URL the users will see, when visiting your site.

= Co-worker Configuration =

* You can integrate the SecSign ID login on the wp-login.php page. This is done by default.
* Optionally, you can assign SecSign IDs to the WordPress users of your co-workers (admins, editors, authors and contributors). The users themselves can also assign a SecSign ID in their profile.
* You can also deactivate the normal password-based login for the users, so they can only login using the SecSign ID. It’s recommended that you deactivate the password login for all co-workers, so your site is secured against brute force attacks. You should only allow the password-based login for your own admin account, in case you lose your phone, and of course for all co-workers without smartphones. These accounts should be secured using a very strong password.

= User Configuration =

* Optionally, you can assign SecSign IDs to the WordPress users of your website users (subscribers). The users themselves can also assign a SecSign ID in their profile.
* It’s also possible to activate and deactivate the password-based login for your users.

= Fast Registration =

* In order not to have to create new user accounts yourself you can allow your co-workers or web site users to create user accounts themselves by logging in with their SecSign ID via wp-login.php or the login widget. You can allow them to create a new wordpress user or assign an existing one. After they created an wordpress account, you can assign wordpress roles to your co-workers via the user administration.

[youtube http://www.youtube.com/watch?v=utphj_m6jd4]

== Frequently Asked Questions ==

= How can users assign a SecSign ID to their WordPress account? =

You can just sign in with your SecSign ID. You will then be shown a dialog, where you can create a new user or assign your SecSign ID to an existing WordPress user.

Alternatively, you can go to your profile page to assign a SecSign ID.

= Is this service for free? =

Yes, it's free for the user and the WordPress admin - no matter how many users the site has. It's also free of advertising.

== Screenshots ==

1. This is the login form in which you enter your SecSign ID shown in the smartphone app.
2. You will be shown an access pass. Tab on the matching one on your phone.
3. If your SecSign ID is not associated with a WordPress username, you can create one or assign the SecSign ID to an existing user.
4. The SecSign ID login form at the admin panel login page.
5. Again you will be shown an access pass. Tab on the matching one on your phone.
6. The options for the SecSign ID plugin. You can choose a service name which is shown to a user on his or her smartphone and the assignments between a wordpress user and a SecSign ID.
7. The options for self enrollment whether a user can assign his or her SecSign ID by him- or herself and whether a user can create a new account.

== Changelog ==
= 1.0.7 =
* When SecSign ID plugin is enabled the focus is set to secsign id input field at wp-login.php.
* CSS corrections,
* Added new screenshots for the description.

= 1.0.6 =
* Fixed a problem with some redirect urls
* Use of SecSign ID JS API [GitHub - ](https://github.com/SecSign/secsign-js-api) for automatically login
* The login will now automatically continue after you selected the access pass on your smartphone. No need to click the OK button.
* Added new installer icons
* Fixed a problem on multisites which could allow a password-based login, although the password-based login was deactivated for this user.

= 1.0.5 =
* Integration in wp-login.php page
* Possibility to deactivate the normal password-based login
* Layout fixes

= 1.0.4 =
* Allowing wordpress installations on nonstandard ports
* Added new version of SecSign ID PHP API [GitHub](https://github.com/SecSign/secsign-php-api)

= 1.0.3 =
* Added new version of SecSign ID PHP API [GitHub](https://github.com/SecSign/secsign-php-api)
* Fixed wpdb::prepare() warning

= 1.0.2 =
* Changed color of errors

= 1.0.1 =
* Bug fixes

= 1.0 =
* Initial release


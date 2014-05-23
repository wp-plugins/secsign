=== SecSign ===
Contributors: SecSign
Tags: two-factor authentication, two-factor, authentication, login, sign in, single sign-on, challenge response, rsa, password, mobile, iphone, android, security, authenticator, authenticate, two step authentication, 2fa
Requires at least: 3.0.1
Tested up to: 3.9.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use the SecSign ID two-factor authentication on your Wordpress site to enable easy and secure login using your iPhone or Android phone.

== Description ==

SecSign ID - The mobile way to log into web sites

* Integrate SecSign ID into your own Wordpress site in less than one minute. (There are also APIs for PHP and Java.)
* You and your users can also use SecSign ID to visit securely other web sites (e.g. [Portal.SecSign.com](https://portal.secsign.com) for true professional messaging and cloud sharing.)
* This service is free for users and website owners and free of advertising - no matter how many users you have.
* You can also integrate the SecSign ID as inhouse solution into your existing infrastructure (on request with licensed service and maintenance contract)

[youtube http://www.youtube.com/watch?v=DNjrbEuMB7Y]

SecSign ID features:

* Quick and easy to use single sign-on with 2048 bit high security
* Eliminates password chaos and security concerns
* No mobile number, credit card or time-consuming registration required.
* No need for long cryptical passwords, time-consuming retyping of codes from SMS or reading of QR codes
* High security and strong cryptography on all levels

Technical details (only for experts):

* Up to 2048 bit asymmetric private keys
* Brute force resistant private key storage (SafeKey mechanism)
* Private keys are never transmitted to authentication server
* High availability through redundant remote failover servers
* Multi-tier high security architecture with multiple firewalls and protocol filters.

SecSign ID in action:

1. Get the app for [iPhone](https://itunes.apple.com/app/secsign/id581467871) or [Android](https://play.google.com/store/apps/details?id=com.secsign.secsignid)
2. Choose a unique user short name
3. Choose a short PIN to secure your SecSign ID on your phone

That's it! You can now use your SecSign ID to sign in.

How to sign in:

Just type in your user short name (for instance at [SecSign Portal](https://portal.secsign.com) or your Wordpress site using this plugin), confirm your sign-in on your phone and you are done within seconds.

Despite its simplicity SecSign ID works with comprehensive strongest security technologies. The solution we offer is unique and does not submit any confidential data through a web browser.

We have a strong background of more than 14 years in developing strong cryptography and highly sophisticated security software products for governments, public institutions and private companies.

Visit our official site to get the app and more information: [SecSign.com](https://www.secsign.com)

and check out our [flyer](https://www.secsign.com/secsign_portal_flyer.pdf).

== Installation ==

1. Login into Wordpress as admin and go to the plugins screen and select the "Add New" submenu.
2. Search for "SecSign" and click "Install Now" or click on "Upload" and select the downloaded zip archive.
3. Activate the plugin in the "Installed Plugins" list.
4. Go to the "Appearance" screen and click the "Widgets" submenu.
5. Drag and drop the "SecSign ID Login" widget to the "Main Sidebar"
6. Go to the "Settings" screen and select the "SecSign ID Login" submenu.
7. Change the service address which will be shown to the user in the smartphone app. This should match the URL the user will see, when he visits your site. Optionally, assign SecSign IDs to Wordpress users.

[youtube http://www.youtube.com/watch?v=utphj_m6jd4]

== Frequently Asked Questions ==

= How can users assign a SecSign ID to their Wordpress account? =

You can just sign in with your SecSign ID. You will then be shown a dialog, where you can create a new user or assign your SecSign ID to an existing Wordpress user.

Alternatively, you can go to your profile page to assign a SecSign ID.

= Is this service for free? =

Yes, it's free for the user and the Wordpress admin - no matter how many users you have. It's also free of advertising.

== Screenshots ==

1. This is the Login Form, where you input your SecSign ID from the smartphone app.
2. You will be shown an Access Pass. Touch the matching one on your phone.
3. If your SecSign ID is not associated with an Wordpress username, you can create one or assign the SecSign ID to an existing user.

== Changelog ==

= 1.0.4 =
* allowing wordpress installations on nonstandard ports
* added new PHP API

= 1.0.3 =
* added new PHP API
* fixed wpdb::prepare() warning

= 1.0.2 =
* changed color of errors

= 1.0.1 =
* bug fix

= 1.0 =
* initial release


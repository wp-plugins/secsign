<?php
/*
Plugin Name: SecSign
Plugin URI: https://www.secsign.com/add-it-to-your-website/
Version: 1.7.2
Description: The plugin allows a user to login using a SecSign ID and his smartphone.
Author: SecSign Technologies Inc.
Author URI: http://www.secsign.com
*/

// $Id: secsignid_login.php,v 1.21 2015/04/21 12:29:18 titus Exp $

global $secsignid_login_text_domain;
global $secsignid_login_plugin_name;

$secsignid_login_text_domain = "secsign";
$secsignid_login_plugin_name = "secsign";

include(WP_PLUGIN_DIR . '/' . $secsignid_login_plugin_name . '/secsignid_login_db.php');
include(WP_PLUGIN_DIR . '/' . $secsignid_login_plugin_name . '/SecSignIDApi.php'); // include low-level interface to connector to SecSign ID Server

// check if admin page is called
if (is_admin()) {
    // this creates a submenu entry and adds options to wordpress database
    include(WP_PLUGIN_DIR . '/' . $secsignid_login_plugin_name . '/secsignid_login_admin.php');
}

//buttons
global $check_auth_button;
global $cancel_auth_button;
$check_auth_button = "check_auth";
$cancel_auth_button = "cancel_auth";

//session state
global $secsignid_login_auth_session_status;
$secsignid_login_auth_session_status = AuthSession::NOSTATE;

//cookies
global $secsignid_login_auth_cookie_name;
global $secsignid_login_secure_auth_cookie_name;
$secsignid_login_auth_cookie_name = 'secsign_id_wordpress_cookie';
$secsignid_login_secure_auth_cookie_name = 'secsign_id_wordpress_secure_cookie';

/**
 * wordpress hooks
 */
add_action('init', 'secsign_id_init_auth_cookie_check', 100); //checks the secsign id cookie
add_action('init', 'secsign_id_init', 1); //widget init
add_action('init', 'secsign_id_check_ticket', 0); //checks state of the session and does the login
add_action('clear_auth_cookie', 'secsign_id_unset_cookie', 5); //unsets the secsign id cookie
add_filter('authenticate', 'secsign_id_check_login', 100, 3); //high priority, so it will be called last, and can disallow password based authentication
add_action('login_footer', 'secsign_custom_login_form', 0); //custom login form
add_action('wp_login_failed', 'secsign_front_end_pw_login_fail');  // hook failed login


if (!(function_exists('secsign_front_end_pw_login_fail'))) {
    /**
     * change referrer when frontend password login fails
     */
    function secsign_front_end_pw_login_fail($username)
    {
        // Get the reffering page, where did the post submission come from?
        $referrer = $_SERVER['HTTP_REFERER'];
        if ( strpos($referrer,'?login=failed') !== false ) {
            $parameter = '';
        } else {
            $parameter = '?login=failed';
        }

        // if there's a valid referrer, and it's not the default log-in screen
        if (!empty($referrer) && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
            // let's append some information (login=failed) to the URL for the theme to use
            wp_redirect($referrer . $parameter);
            //exit;
            return $username;
        }
    }
}


if (!(function_exists('secsign_print_parameters'))) {
    /**
     * Adds the SecSign ID JS parameters
     */
    function secsign_print_parameters()
    {
        $plugin_path = plugin_dir_url(__FILE__);
        $wp_site_url = get_site_url();
        echo '<script>
            //Parameters
            var url = "";
            var siteurl = "' . $wp_site_url . '";
            var title = "' . addslashes(get_option('secsignid_service_name')) . '";
            var secsignPluginPath = "' .addslashes($plugin_path) . '";
            var apiurl = secsignPluginPath+"/signin-bridge.php";
            var errormsg = "Your login session has expired, was canceled, or was denied.";
            var noresponse = "The authentication server sent no response or you are not connected to the internet.";
            var nosecsignid = "Invalid SecSignID.";
            var secsignid = "";
            var frameoption = "' . addslashes(get_option('secsignid_frame')) . '";

            if (url == "") {
                //url = document.URL;
                url = "' . $wp_site_url . '";
            }
            if (title == "") {
                title = document.title;
            }
            if (typeof backend == "undefined") {
                var backend = false;
            }
        </script>
        ';
    }
}


if (!(function_exists('secsign_custom_login_form'))) {
    /**
     * Adds the SecSign ID login form to the wp-login.php page
     */
    function secsign_custom_login_form()
    {
        if (get_option('secsignid_show_on_login_page')) {
            echo <<<SECSIGNJS
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
				<script type="text/javascript">
					jQuery(document).ready(function(){

						// switch order of normal login fields and the secsign id block
						if(jQuery("#login .message").length > 0){
							jQuery("#secsignid-login").insertBefore($("#login .message"));
						} else {
							jQuery("#secsignid-login").insertBefore(jQuery("#loginform"));
						}
						// try to get focus from normal input field
						setTimeout( function(){								
							try {
								jQuery("#login-secsignid").focus();
								jQuery("#login-secsignid").select();
							} catch(ex) {
							}
						}, 100);
					});

					var backend = true;
				</script>
SECSIGNJS;

            echo "<div id='secsignid-login'>";
            // this cannot be put into a variable. the function will echo html code itself.
            secsign_id_login(array());
            echo "</div>";
        }
    }
}

if (!(function_exists('secsign_id_check_login'))) {
    /**
     * this hook will be called for every password based login
     *
     * @param null|WP_USER|WP_Error $user null indicates no process has authenticated the user yet.
     *                                    A WP_Error object indicates another process has failed the authentication.
     *                                    A WP_User object indicates another process has authenticated the user.
     * @param string $username the user's username
     * @param string $password Optional. the user's password (encypted)
     *
     * @return null|WP_Error|WP_User returns WP_User if password based login is allowed and password is correct. Else returns WP_Error or null.
     */
    function secsign_id_check_login($user, $username, $password)
    {
        if (!empty($username)) {
            $user_object = get_user_by('login', $username);
            if ($user_object) {
                $allow_password_login = get_allow_password_login($user_object->id);
                if ($allow_password_login) {
                    return $user;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            return $user;
        }
    }
}



if (!(function_exists('check_session_for_bruteforce'))) {
    /**
     * the function will check a counter in session. if the counter exceeds a maximum, the session is destroyd to prevent brute force attacks.
     */
    function check_session_for_bruteforce()
    {
        //counter to prevent brute force attacks
		if (!session_id()) {
			session_start();
		}
		
		// check session for secsign user mapping counter
		if (!$_SESSION['secsign-mapping']) {
			// init counter
			$_SESSION['secsign-mapping'] = 0;
		}
		
		$_SESSION['secsign-mapping']++;
		
		// check whether session must be destroyed due to counter limit exceeding
		if ($_SESSION['secsign-mapping'] > 5) {
			
			// session must be destroyed. otherwise the session still exists and a brute force attack can still be done
			session_destroy();
			return true;
		}
		return false;
    }
}

if (!(function_exists('secsign_id_init'))) {
    /**
     * init function which is hooked to wordpress init action.
     * the init function declares this php script to a widget which can be used in wordpress.
     * the overriden function widget() calls secsign_id_login($args);
     */
    function secsign_id_init()
    {
        global $secsignid_login_plugin_name; // get global variable $secsignid_login_plugin_name
        global $secsignid_login_text_domain;

        // create widget class and hook widget initialization
        // @see http://codex.wordpress.org/Widgets_API
        class SecSignIDLogin_Widget extends WP_Widget
        {
            // constructor
            function SecSignIDLogin_Widget()
            {
                global $secsignid_login_text_domain;
                $widget_ops = array('description' => __('SecSign ID Login.', $secsignid_login_text_domain));
                $this->WP_Widget('wp_secsignidlogin', __('SecSign ID Login', $secsignid_login_text_domain), $widget_ops);
            }

            // this method is called whenever the widget shall be drawn
            // redirect to method which decides whether the user is logged in or not
            function widget($args, $instance)
            {
                secsign_id_login($args);
            }
        }

        register_widget('SecSignIDLogin_Widget');
    }
}

if (!(function_exists('secsign_id_init_auth_cookie_check'))) {
    /**
     * init function which is hooked to wordpress init action.
     * used to check if this login is legit or not
     * on multisites you can otherwise bypass the authentication and use the password-based one even if deactivated
     */
    function secsign_id_init_auth_cookie_check()
    {
        if (is_multisite() && is_user_logged_in() //only applies to multisites, only check if logged in
            && (strpos($_SERVER['REQUEST_URI'], 'wp-login') === false)
        ) // not on wp-login
        {
            $user = wp_get_current_user();
            if ($user) {
                $allow_password_login = get_allow_password_login($user->id);
                if (!$allow_password_login && !secsign_id_verify_cookie($user->user_login)) //if password-based login not allowed and cookie not verified -> logout
                {
                    wp_logout();
                    wp_safe_redirect(secsign_id_login_post_url());
                }
            }
        }
    }
}

if (!(function_exists('secsign_id_get_random_secret'))) {
    /**
     * gets a random secret from the db or creates it if not available
     * @return string returns the random secret to sign the auth cookie
     */
    function secsign_id_get_random_secret()
    {
        if (!get_option('secsign_id_cookie_secret')) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $random = openssl_random_pseudo_bytes(32);
            } else {
                $random = wp_generate_password(32, true, true);
            }

            add_option('secsign_id_cookie_secret', base64_encode($random));
        }
        return base64_decode(get_option('secsign_id_cookie_secret'));
    }
}

if (!(function_exists('secsign_id_verify_cookie'))) {
    /**
     * verifies a user cookie
     * @param string $username the user's username
     * @return bool returns true if the auth cookie is ok, or false if something is wrong
     */
    function secsign_id_verify_cookie($username)
    {
        global $secsignid_login_auth_cookie_name;
        global $secsignid_login_secure_auth_cookie_name;

        $cookie_name = $secsignid_login_auth_cookie_name;
        if (is_ssl()) {
            $cookie_name = $secsignid_login_secure_auth_cookie_name;
        }

        if (!isset($_COOKIE[$cookie_name])) {
            return false; //cookie not there
        }

        $cookie = explode('|', $_COOKIE[$cookie_name]);
        if (count($cookie) != 2) {
            return false; //cookie doesn't contain value and hmac
        }

        list($cookie_value, $signature) = $cookie;
        if (hash_hmac('sha512', $cookie_value, secsign_id_get_random_secret()) !== $signature) {
            return false; //hmac doesn't match
        }

        $cookie_array = explode('|', base64_decode($cookie_value));
        if (count($cookie_array) != 2) {
            return false; //cookie doesn't contain username and expiration date
        }

        list($username_in_cookie, $expire_in_cookie) = $cookie_array;
        if (base64_decode($username_in_cookie) !== $username) {
            return false; //wrong username in cookie
        }

        $expire = intval($expire_in_cookie);
        if ($expire < strtotime('now')) {
            return false; //cookie expired
        }

        return true;
    }
}

if (!(function_exists('secsign_id_set_cookie'))) {
    /**
     * sets a secsign id auth cookie, which proves that the login was done with this plugin
     * @param string $username the user's username
     */
    function secsign_id_set_cookie($username)
    {
        global $secsignid_login_auth_cookie_name;
        global $secsignid_login_secure_auth_cookie_name;

        if (is_multisite()) //only needed on multisite
        {
            $expire = strtotime('+1 day');
            $secure = false;
            $cookie_name = $secsignid_login_auth_cookie_name;
            if (is_ssl()) {
                $secure = true;
                $cookie_name = $secsignid_login_secure_auth_cookie_name;
            }

            $cookie_value = base64_encode(sprintf("%s|%d", base64_encode($username), $expire));
            $signature = hash_hmac('sha512', $cookie_value, secsign_id_get_random_secret());
            $cookie = sprintf("%s|%s", $cookie_value, $signature);
            setcookie($cookie_name, $cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
        }
    }
}

if (!(function_exists('secsign_id_unset_cookie'))) {
    /**
     * unsets the secsign id auth cookie
     */
    function secsign_id_unset_cookie()
    {
        global $secsignid_login_auth_cookie_name;
        global $secsignid_login_secure_auth_cookie_name;

        if (is_multisite()) //only needed on multisite
        {
            $cookie_name = $secsignid_login_auth_cookie_name;
            if (is_ssl()) {
                $cookie_name = $secsignid_login_secure_auth_cookie_name;
            }
            setcookie($cookie_name, '', strtotime('-1 day'), COOKIEPATH, COOKIE_DOMAIN);
        }
    }
}

if (!(function_exists('secsign_id_login'))) {
    /**
     * Draws the widget. This function is called by widgets widget() function.
     * it is called whenever wordpress needs to render the widget
     */
    function secsign_id_login($args)
    {
        extract($args); // after this the key names of the associative array can be used like variables

        global $current_user; // instance of type WP_User: http://codex.wordpress.org/Class_Reference/WP_User
        global $user_ID;

        get_currentuserinfo(); // http://codex.wordpress.org/Function_Reference/get_currentuserinfo

        // print widget opening tage
        echo $before_widget; //come out of $args

        if ($user_ID == 0 || $user_ID == '') {
            // no user is logged in
            global $error;
            global $login_errors;

            $found_login_errors = false;
            $wp_error = new WP_Error();

            // in case a plugin uses $error rather than the $wp_errors object
            if (!empty($error)) {
                $wp_error->add('error', $error);
                unset($error);
            }

            $errors = '';
            $messages = '';

            // snippet from standard login plugin of wordpress:
            if ($wp_error->get_error_code()) {
                foreach ($wp_error->get_error_codes() as $code) {
                    $severity = $wp_error->get_error_data($code);
                    foreach ($wp_error->get_error_messages($code) as $error) {
                        if ('message' == $severity)
                            $messages .= '	' . $error . "<br />" . PHP_EOL;
                        else
                            $errors .= '	' . $error . "<br />" . PHP_EOL;
                    }
                }
                // print error codes and messages
                if (!empty($errors)) {
                    $found_login_errors = true;
                }
            }

            // check if login errors or wordpress error exist
            if (is_wp_error($login_errors) && $login_errors->get_error_code()) {
                foreach ($login_errors->get_error_messages() as $error) {
                    $errors .= '  ' . $error . "<br />" . PHP_EOL;

                    $found_login_errors = true;
                    break;
                }
            }

            //echo $before_title . '<span>' . $widget_name . '</span>' . $after_title;

            global $secsignid_login_no_wp_mapping;

            if (isset($secsignid_login_no_wp_mapping)) {
                if ($found_login_errors) {
                    // print error codes and messages
                    if (!empty($messages)) {
                        echo "<p class='message'>" . apply_filters('login_messages', $messages) . "</p>\n<br>";
                    }
                }
                print_wpuser_mapping_form();
            } // check if secsign id login variables are set
            else if ((!$found_login_errors) && isset($_POST['secsignidrequestid']) && isset($_POST['secsignidauthsessionid'])) {
                // check or cancelauth session status
                try {
                    $authsession = new AuthSession();
                    $authsession->createAuthSessionFromArray(array(
                        'requestid' => $_POST['secsignidrequestid'],
                        'secsignid' => $_POST['secsigniduserid'],
                        'authsessionid' => $_POST['secsignidauthsessionid'],
                        'servicename' => $_POST['secsignidservicename'],
                        'serviceaddress' => $_POST['secsignidserviceaddress'],
                        'authsessionicondata' => $_POST['secsignidauthsessionicondata']
                    ));

                    $secSignIDApi = get_secsignid_server_instance();

                    global $secsignid_login_auth_session_status;
                    global $check_auth_button;

                    if (isset($_POST[$check_auth_button])) {
                        //auth session status already checked in hooked method secsign_id_check_ticket()

                        if (($secsignid_login_auth_session_status == AuthSession::PENDING) || ($secsignid_login_auth_session_status == AuthSession::FETCHED)) {
                            // print_check_accesspass($authsession, $secsignid_login_auth_session_status);
                        } else {
                            if ($secsignid_login_auth_session_status == AuthSession::EXPIRED) {
                                print_error("Access Pass expired.", null, true);
                            } else if ($secsignid_login_auth_session_status == AuthSession::SUSPENDED) {
                                print_error("The server suspended this session.", null, true);
                            } else if ($secsignid_login_auth_session_status == AuthSession::INVALID) {
                                print_error("This session has become invalid.", null, true);
                            } else if ($secsignid_login_auth_session_status == AuthSession::CANCELED) {
                                print_error("The server canceled this session.", null, true);
                            } else if ($secsignid_login_auth_session_status == AuthSession::DENIED) {
                                print_error("Authentication has been denied.", null, true);
                            }
                        }
                    } else {
                        // cancelauth session
                        $secSignIDApi->cancelAuthSession($authsession);

                        // show login form
                        print_login_form();
                    }
                } catch (Exception $e) {
                    print_error("An error occured when checking status of authentication session: " . $e->getMessage(),
                        "Cannot check status of authentication session.",
                        true);
                }
            } else {
                // check if auth session id is set
                if (isset($_POST['requestid']) && isset($_POST['authsessionid'])) {
                    // an error occured during login process. withdraw auth session
                    try {
                        $authsession = new AuthSession();
                        $authsession->createAuthSessionFromArray(array(
                            'requestid' => $_POST['requestid'],
                            'secsignid' => $_POST['secsignid'],
                            'authsessionid' => $_POST['authsessionid'],
                            'servicename' => $_POST['servicename'],
                            'serviceaddress' => $_POST['serviceaddress'],
                            'authsessionicondata' => $_POST['authsessionicondata']
                        ));

                        $secSignIDApi = get_secsignid_server_instance();

                        $secSignIDApi->cancelAuthSession($authsession);
                    } catch (Exception $e) {
                        print_error("An error occured while canceling auth session: " . $e->getMessage(),
                            "Cannot cancel authentication session. No session exists.",
                            false);
                    }
                }

                if ($found_login_errors) {
                    // print error codes and messages
                    if (!empty($errors)) {
                        print_error($errors, null);
                        echo "<br />";
                    }
                    if (!empty($messages)) {
                        echo "<p class='message'>" . apply_filters('login_messages', $messages) . "</p>\n";
                        echo "<br />";
                    }
                }

                // get post to url. the widget will be called again
                print_login_form();
            }
        } else {
            // user is logged in, show logout screen

            $form_post_url = secsign_id_login_post_url();
            $plugin_path = plugin_dir_url(__FILE__);

            echo "<link rel='stylesheet' type='text/css' href='" . plugins_url('secsignid_layout.css', __FILE__) . "'></link>" . PHP_EOL;
            secsign_print_parameters();
            wp_register_script('SecSignIDApi', plugins_url('/SecSignIDApi.js', __FILE__), array('jquery'));
            wp_register_script('secsignfunctions', plugins_url('/secsignfunctions.js', __FILE__), array('jquery'));
            wp_enqueue_script('SecSignIDApi');
            wp_enqueue_script('secsignfunctions');
            $redirectAfterLogoutTo = site_url();

            echo '
                <div id="secsignidplugincontainer">
                    <noscript>
                        <div class="secsignidlogo"></div>
                        <p>Noscript</p>
                        <a style="color: #fff; text-decoration: none;" id="noscriptbtn"
                           href="https://www.secsign.com/support/" target="_blank">SecSign Support</a>
                    </noscript>
                        <div id="secsignidplugin">
                        <!-- Page Login -->
                        <div id="secsignid-page-logout">
                            <div class="secsignidlogo"></div>
                            <div id="secsignid-error"></div>
                            <p>Welcome ' . $current_user->user_login . '</p>
                            <a id="seclogoutbtn" href="' . wp_logout_url($redirectAfterLogoutTo) . '">Logout</a>
                        </div>
                    </div>
                </div>
            ';

            if (strpos($_SERVER['REQUEST_URI'], 'interim-login=1') !== false) {
                echo <<<INTERIM_LOGIN
						<script>
							jQuery(document).ready(function(){
							    window.parent.jQuery(".wp-auth-check-close").click();
								if(jQuery("#login .message").length > 0) {
									jQuery("#login .message").hide();
								}
								jQuery("#loginform").hide();
							});
						</script>
INTERIM_LOGIN;
            }
        }

        secsignid_login_hide_wp_login();
        // print widget closing tag
        echo $after_widget;
    }
}

if (!(function_exists('secsign_id_check_ticket'))) {
    /**
     * the actual login process.
     * the function is hooked to init action of wordpress.
     * for this reason this method is called before the widget rendering function.
     *
     * all post parameter are available and a possible auth session can be checked if its status is AUTHENTICATED.
     * the auth session status is saved in a global variable $secsignid_login_auth_session_status
     *
     * if the auth session status is authenticated, the user will be logged in.
     * otherwise the function just will end without any effects.
     */
    function secsign_id_check_ticket()
    {
        session_start();
        if (isset($_POST['newaccount']) && get_option('secsignid_allow_account_creation') && isset($_SESSION['authenticated']) && ($_SESSION['authenticated'] == $_POST['secsignid'])) {
            if (!is_user_logged_in()) // no user is logged in
            {
                /**if ($_POST['wp-password'] == '')
                 * {
                 * add_error("Please enter a password.");
                 * global $secsignid_login_no_wp_mapping;
                 * $secsignid_login_no_wp_mapping = true;
                 * return;
                 * }
                 *
                 * if ($_POST['wp-email'] == '')
                 * {
                 * add_error("Please enter an email address.");
                 * global $secsignid_login_no_wp_mapping;
                 * $secsignid_login_no_wp_mapping = true;
                 * return;
                 * }*/

                if (username_exists($_POST['wp-username'])) {
                    add_error("User already exists. Please try another user name or assign your SecSign ID to this user name.");
                    global $secsignid_login_no_wp_mapping;
                    $secsignid_login_no_wp_mapping = true;
                    return;
                }

                /**if (email_exists($_POST['wp-email'])) {
                 * add_error("Email address already exists. Please try another email.");
                 * global $secsignid_login_no_wp_mapping;
                 * $secsignid_login_no_wp_mapping = true;
                 * return;
                 * }*/

                //$user_id = wp_create_user($_POST['wp-username'], $_POST['wp-password'], $_POST['wp-email']);

                //generate random password, so nobody can login
                $random_password = wp_generate_password(20);
                $user_id = wp_create_user($_POST['wp-username'], $random_password, '');


                $user_to_login = get_user_by('login', $_POST['wp-username']);
                $user_data = apply_filters('wp_authenticate_user', $user_to_login, $random_password);
                $user = $user_to_login;

                if ($user_data != null) {
                    // re-create user
                    $user = new WP_User($user_data->ID);
                }

                if (!wp_check_password($random_password, $user_data->user_pass, $user_data->ID)) {
                    add_error("Sign in failed. Please try again.");
                } else {
                    //Assign SecSign ID to WP User
                    $mapping_array = get_user_mappings();
                    $password_login_allowed = false;
                    // check if mapping already exist to decide whether to call update or insert
                    if ($mapping_array[$user->ID]) {
                        // check if mapping equals the new secsign id.
                        if ($mapping_array[$user->ID]['secsignid'] !== $_POST['secsignid']) {
                            update_user_mapping($user->ID, $_POST['secsignid'], $password_login_allowed);
                        }
                    } else {
                        insert_user_mapping($user->ID, $user->user_login, $_POST['secsignid'], $password_login_allowed);
                    }

                    wp_set_auth_cookie($user->ID, false, is_ssl());
                    secsign_id_set_cookie($user->user_login);
                    do_action('wp_login', $user->user_login, $user);

                    wp_set_current_user($user->ID);

                    $redirect = secsign_id_login_post_url(); //redirect to same page

                    if (isset($_SESSION['redirect_to'])) //if redirect url is given, use it
                    {
                        $redirect = $_SESSION['redirect_to'];
                    } else if ((strpos($_SERVER['REQUEST_URI'], 'wp-login') !== false) && (strpos($_SERVER['REQUEST_URI'], 'interim-login=1') === false)) //if on login page and not in the wp-admin iframe, redirect to wp-admin
                    {
                        $redirect = admin_url();
                    }

                    if (strpos($_SERVER['REQUEST_URI'], 'interim-login=1') === false) {
                        wp_safe_redirect($redirect);
                        exit;
                    }
                }
            }
            session_destroy();
        } 
        else if (isset($_POST['existingaccount']) && get_option('secsignid_allow_account_assignment') && isset($_SESSION['authenticated']) && ($_SESSION['authenticated'] == $_POST['secsignid'])) //login and assign secsign id to wp user
        {
            $user_to_login = get_user_by('login', $_POST['wp-username']);
            if ($user_to_login) {
                if (!is_user_logged_in()) // no user is logged in
                {
                    $user_data = apply_filters('wp_authenticate_user', $user_to_login, $_POST['wp-password']);
                    $user = $user_to_login;

                    if ($user_data != null) {
                        // re-create user
                        $user = new WP_User($user_data->ID);
                    }

                    if (!wp_check_password($_POST['wp-password'], $user_data->user_pass, $user_data->ID)) {
                        add_error("Wrong Password. Please try again.");

						if(check_session_for_bruteforce()){
                			return;
		                }
                        
                        global $secsignid_login_no_wp_mapping;
                        $secsignid_login_no_wp_mapping = true;
                        return;
                    } else {
                        //Assign SecSign ID to WP User
                        $mapping_array = get_user_mappings();
                        $password_login_allowed = true;
                        // check if mapping already exist to decide whether to call update or insert
                        if ($mapping_array[$user->ID]) {
                            // check if mapping equals the new secsign id.
                            if ($mapping_array[$user->ID]['secsignid'] !== $_POST['secsignid']) {
                                update_user_mapping($user->ID, $_POST['secsignid'], $password_login_allowed);
                            }
                        } else {
                            insert_user_mapping($user->ID, $user->user_login, $_POST['secsignid'], $password_login_allowed);
                        }

                        wp_set_auth_cookie($user->ID, false, is_ssl());
                        secsign_id_set_cookie($user->user_login);
                        do_action('wp_login', $user->user_login, $user);

                        wp_set_current_user($user->ID);

                        $redirect = secsign_id_login_post_url(); //redirect to same page

                        if (isset($_SESSION['redirect_to'])) //if redirect url is given, use it
                        {
                            $redirect = $_SESSION['redirect_to'];
                        } else if ((strpos($_SERVER['REQUEST_URI'], 'wp-login') !== false) && (strpos($_SERVER['REQUEST_URI'], 'interim-login=1') === false)) //if on login page and not in the wp-admin iframe, redirect to wp-admin
                        {
                            $redirect = admin_url();
                        }
                        wp_safe_redirect($redirect);
                    }
                }
                session_destroy();
            } else {
                add_error("No wordpress user exists for the username '" . $_POST['wp-username'] . "'.");
                
                if(check_session_for_bruteforce()){
                	return;
                }

                global $secsignid_login_no_wp_mapping;
                $secsignid_login_no_wp_mapping = true;
                return;

            }
        } 
        else if (isset($_POST['secsignidrequestid']) && isset($_POST['secsignidauthsessionid'])) //check state of session
        {
            global $check_auth_button;
            $_POST[$check_auth_button] = true;

            if (isset($_POST[$check_auth_button])) {
                global $secsignid_login_auth_session_status;

                $secsignid_login_auth_session_status = AuthSession::NOSTATE;

                try {
                    $authsession = new AuthSession();
                    $authsession->createAuthSessionFromArray(array(
                        'requestid' => $_POST['secsignidrequestid'],
                        'secsignid' => $_POST['secsigniduserid'],
                        'authsessionid' => $_POST['secsignidauthsessionid'],
                        'servicename' => $_POST['secsignidservicename'],
                        'serviceaddress' => $_POST['secsignidserviceaddress'],
                        'authsessionicondata' => $_POST['secsignidauthsessionicondata']
                    ));

                    $secSignIDApi = get_secsignid_server_instance();
                    $secsignid_login_auth_session_status = $secSignIDApi->getAuthSessionState($authsession);
                } catch (Exception $e) {
                    $errorMessage = "An error occured when checking status of authentication session: " . $e->getMessage();
                    add_error($errorMessage);

                    $secsignid_login_auth_session_status = AuthSession::NOSTATE;
                }

                if ($secsignid_login_auth_session_status == AuthSession::AUTHENTICATED) {
                    //save to the session, that the secsign id was authenticated. This will later allow the assignment to/creation of a wordpress user
                    $_SESSION['authenticated'] = $_POST['secsigniduserid'];
                    // release authentication session. it is not used any more
                    $secSignIDApi->releaseAuthSession($authsession);

                    $user_to_login = get_wp_user($_POST['secsigniduserid']);
                    if ($user_to_login) {
                        if ($user_to_login->user_login) {// == $_POST['mapped_wp_user']) {
                            if (!is_user_logged_in()) // no user is logged in
                            {
                                $user_data = apply_filters('wp_authenticate_user', $user_to_login, '');
                                $user = $user_to_login;

                                if ($user_data != null) {
                                    // re-create user
                                    $user = new WP_User($user_data->ID);
                                }

                                wp_set_auth_cookie($user->ID, false, is_ssl());
                                secsign_id_set_cookie($user->user_login);
                                do_action('wp_login', $user->user_login, $user);

                                wp_set_current_user($user->ID);

                                $redirect = secsign_id_login_post_url(); //redirect to same page

                                if (isset($_SESSION['redirect_to'])) //if redirect url is given, use it
                                {
                                    $redirect = $_SESSION['redirect_to'];
                                } else if ((strpos($_SERVER['REQUEST_URI'], 'wp-login') !== false) && (strpos($_SERVER['REQUEST_URI'], 'interim-login=1') === false)) //if on login page and not in the wp-admin iframe, redirect to wp-admin
                                {
                                    $redirect = admin_url();
                                }

                                session_destroy();
                                wp_safe_redirect($redirect);
                                exit;

                            }
                        } else {
                            // found word press user is not same than wp user from POST parameters
                            add_error("Wrong wordpress user specified for secsign id '" . $_POST['secsignid'] . "'.");
                        }
                    } else {
                        // no wordpress user exists in database for secsign id,
                        // the secsign_id_login() function will later show the wp-user mapping form
                        global $secsignid_login_no_wp_mapping;
                        $secsignid_login_no_wp_mapping = true;
                    }
                }
            }
        }
    }
}


if (!(function_exists('secsign_id_login_post_url'))) {
    /**
     * builds an url which is used for all html forms to post data to.
     */
    function secsign_id_login_post_url()
    {
        if (strncmp(get_site_url(), "https", 5) == 0) {
            $prot = "https";
        } else $prot = "http";

        $redirect_url = ""; // is modified in function secsign_id_login_remove_all_url_params()
        $post_url = secsign_id_login_remove_all_url_params($_SERVER['REQUEST_URI'], $redirect_url);

        if (!empty($redirect_url)) {
            session_start();
            $_SESSION['redirect_to'] = urldecode($redirect_url);
        }

        if (strcmp($post_url, "") == 0) {
            $post_url = "/";
        }

        return $prot . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . $post_url;
    }
}

if (!(function_exists('secsign_id_login_remove_all_url_params'))) {
    /**
     * removes all not needed parameter (loggedout, reauth, action) from a url path
     * the second parameter is optional and returns the redirect_to value by reference if available
     * Example: secsign_id_login_remove_url_param('/wp-login-php?para1=1&para2=2')
     *  -> '/wp-login-php'
     *
     * @param string $url the URL path to remove the parameters from
     * @param string $redirect_to Optional. if given, will be set to the value of the redirect_to parameter, that was removed
     *
     * @return string the url without the parameters
     */
    function secsign_id_login_remove_all_url_params($url, &$redirect_to = NULL)
    {
        if (strpos($url, '?') === false) //no parameters
        {
            return $url;
        }

        $exploded_url = explode("?", $url);
        $begin = $exploded_url[0];

        if (count($exploded_url) == 1) //contains '?' but no parameters
        {
            return $begin;
        }

        $exploded_params = explode("&", $exploded_url[1]);

        $parameters = "";

        if (count($exploded_params) > 0) //there are parameters
        {
            foreach ($exploded_params as $para) //for each parameter
            {
                $exploded_para = explode("=", $para);
                if (count($exploded_para) == 2) {
                    if ($exploded_para[0] == "redirect_to") {
                        $redirect_to = $exploded_para[1];
                    } else if (($exploded_para[0] == "loggedout") || ($exploded_para[0] == "reauth") || ($exploded_para[0] == "action")) {
                        //do nothing, we don't want these parameters
                    } else //all other parameters are added to the url again
                    {
                        if (strlen($parameters) > 0) $parameters = $parameters . "&";
                        $parameters = $parameters . $para;
                    }
                }
            }
        }

        return $begin . "?" . $parameters;
    }
}

if (!(function_exists('get_secsignid_server_instance'))) {
    /**
     * creates an instance of the SecSignIDApi and returns it.
     *
     * @return SecSignIDApi the SecSign ID server API
     */
    function get_secsignid_server_instance()
    {
        $secSignIDServer = new SecSignIDApi();
        $secSignIDServer->setPluginName("SecSignID-WordPress");

        return $secSignIDServer;
    }
}

if (!(function_exists('print_login_form'))) {
    /**
     * prints out the actual login form
     */
    function print_login_form()
    {
        $form_post_url = secsign_id_login_post_url();
        $plugin_path = plugin_dir_url(__FILE__);

        echo "<link rel='stylesheet' type='text/css' href='" . plugins_url('secsignid_layout.css', __FILE__) . "'></link>" . PHP_EOL;
        secsign_print_parameters();
        wp_register_script('SecSignIDApi', plugins_url('/SecSignIDApi.js', __FILE__), array('jquery'));
        wp_register_script('secsignfunctions', plugins_url('/secsignfunctions.js', __FILE__), array('jquery'));
        wp_enqueue_script('SecSignIDApi');
        wp_enqueue_script('secsignfunctions');

        if ((strpos($_SERVER['REQUEST_URI'], 'wp-login') !== false) && get_option('secsignid_show_on_login_page')) {
            $return = admin_url();
            $password_login_form = wp_login_form(array('echo' => false, 'form_id' => 'secsign-login-form', 'redirect' => $return));
        } else {
            $password_login_form = wp_login_form(array('echo' => false, 'form_id' => 'secsign-login-form'));
            $return = "";
        }




        echo <<<LOGIN_FORMS
<div id="secsignidplugincontainer">
        <noscript>
            <div class="secsignidlogo"></div>
            <p>nojs</p>
            <a style="color: #fff; text-decoration: none;" id="noscriptbtn"
               href="https://www.secsign.com/support/" target="_blank">SecSign Support</a>
        </noscript>
        <div style="display:none;" id="secsignidplugin">
            <!-- Page Login -->
            <div id="secsignid-page-login">
                <div class="secsignidlogo"></div>
                <div id="secsignid-error"></div>
                <form id="secsignid-loginform">
                    <div class="form-group">
                        <input type="text" class="form-control login-field" value="" placeholder="SecSign ID"
                               id="login-secsignid" name="secsigniduserid">
                        <label class="login-field-icon fui-user" for="login-secsignid"></label>
                    </div>

                    <div id="secsignid-checkbox">
		        <span>
	                <input id="rememberme" name="rememberme" type="checkbox" value="rememberme" checked>
	                <label for="rememberme">Remember my SecSign ID</label>
	            </span>
                    </div>
                    <button id="secloginbtn" type="submit">Log in</button>
                </form>
                <div class="secsignid-login-footer">
                    <a href="#" class="infobutton" id="secsignid-infobutton">Info</a>
                    <a href="#" class="linktext" id="secsignid-pw">Log in with a password</a>

                    <div class="clear"></div>
                </div>
            </div>

            <!-- Page Password Login -->
            <div id="secsignid-page-pw">
                <div class="secsignidlogo"></div>
LOGIN_FORMS;
        $uri = $_SERVER['REQUEST_URI'];
        if ( strpos($uri,'login=failed') !== false ) {
            echo'<div id="secsignid-reg-error">Wrong username or password</div>';
        }

        echo <<<LOGIN_FORMS

                {$password_login_form}

LOGIN_FORMS;
        if (strpos($_SERVER['REQUEST_URI'], 'interim-login=1') !== false) {
            echo '<input type="hidden" name="interim-login" value="1" form ="secsign-login-form">';
        }

        echo <<< LOGIN_FORMS2
                <div class="secsignid-login-footer">
                    <a class="linktext" href="#" id="secsignid-login-secsignid">Log in with SecSign ID</a>

                    <div class="clear"></div>
                </div>
            </div>

            <!-- Page Info SecSign Login -->
            <div id="secsignid-page-info">
                <div class="secsignidlogo secsignidlogo-left"></div>
                <h3 id="headinginfo">Eliminate Passwords and Password Theft.</h3>

                <div class="clear"></div>
                <p>Protect your organization and your sensitive data with two-factor authentication.</p>
                <a id="secsignid-learnmore" href="https://www.secsign.com/products/secsign-id/" target="_blank">Learn more</a>

                <img style="margin: 0 auto;width: 100%;display: block;max-width: 200px;"
                     src="{$plugin_path}/images/secsignhelp.png">

                <a class="linktext" id="secsignid-info-secsignid" href="#">&lt; Go back to the login screen</a>

                <a style="color: #fff; text-decoration: none;"
                   href="https://www.secsign.com/try-it/#login" target="_blank"
                   id="secsignidapp1">See how it works</a>

                <div class="clear"></div>
            </div>

            <!-- Page Accesspass -->
            <div id="secsignid-page-accesspass">
                <div class="secsignidlogo"></div>

                <div id="secsignid-accesspass-container">
                    <img id="secsignid-accesspass-img"
                         src="{$plugin_path}/images/preload.gif">
                </div>

                <div id="secsignid-accesspass-info">
                    <a href="#" class="infobutton" id="secsignid-questionbutton">Info</a>

                    <p class="accesspass-id">Access pass for <b id="accesspass-secsignid"></b></p>

                    <div class="clear"></div>
                </div>

                <form action="" method="post"
                      id="secsignid-accesspass-form">
                    <button id="secsignid-cancelbutton" type="submit">Cancel</button>

                    <!-- OK -->
                    <input type="hidden" name="check_authsession" id="check_authsession" value="1"/>
                    <input type="hidden" name="option" value="com_secsignid"/>
                    <input type="hidden" name="task" value="getAuthSessionState"/>

                    <!-- Cancel
                    <input type="hidden" name="cancel_authsession" id="cancel_authsession" value="0"/>
                    -->

                    <!-- Values -->
                    <input type="hidden" name="return" value=""/>
                    <input type="hidden" name="secsigniduserid" value=""/>
                    <input type="hidden" name="secsignidauthsessionid" value=""/>
                    <input type="hidden" name="secsignidrequestid" value=""/>
                    <input type="hidden" name="secsignidservicename" value=""/>
                    <input type="hidden" name="secsignidserviceaddress" value=""/>
                    <input type="hidden" name="secsignidauthsessionicondata" value=""/>
                    <input type="hidden" name="redirect_to" value=""/>
LOGIN_FORMS2;
        global $interim_login;
        if ($interim_login) {
            echo '<input type="hidden" name="interim-login" value="1">
                 <input type="hidden" name="testcookie" value="1">';
        }


        echo <<<LOGIN_FORMS2

                </form>
            </div>

            <!-- Page Question SecSign Accesspass -->
            <div id="secsignid-page-question">
                <div class="secsignidlogo secsignidlogo-left"></div>
                <h3 id="headingquestion">How to sign in with SecSign ID</h3>

                <div class="clear"></div>
                <p>In order to log in using your SecSign ID, you need to follow the following steps:</p>
                <ol>
                    <li>Open the SecSign ID app on your mobile device</li>
                    <li>Tap your ID</li>
                    <li>Enter your PIN or passcode or scan your fingerprint</li>
                    <li>Select the correct access symbol</li>
                </ol>

                <a class="linktext" id="secsignid-question-secsignid" href="#">&lt; Go back to the Access Pass verification</a>

                <a style="color: #fff; text-decoration: none;" class="button-secsign blue"
                   href="https://www.secsign.com/try-it/#account" target="_blank" id="secsignidapp2">Get the SecSign ID App</a>

                <div class="clear"></div>
            </div>
        </div>
    </div>

LOGIN_FORMS2;

        try {
            echo "<!-- secsign id plugin version: " . get_plugin_version() . " -->\n\n";
        } catch (Exception $e) {
            echo "<!-- error finding version: " . $e . " -->\n\n";
        }

        // hide the login
        secsignid_login_hide_wp_login();
    }
}

if (!(function_exists('print_wpuser_mapping_form'))) {
    /**
     * prints out the WP User mapping login form
     */
    function print_wpuser_mapping_form()
    {
        global $error;
        global $login_errors;

        $secsignid = $_POST['secsigniduserid'];
        if ($secsignid == "") {
            $secsignid = $_POST['secsignid'];
        }

        echo "<link rel='stylesheet' type='text/css' href='" . plugins_url('secsignid_layout.css', __FILE__) . "'></link>" . PHP_EOL;
        secsign_print_parameters();
        wp_register_script('SecSignIDApi', plugins_url('/SecSignIDApi.js', __FILE__), array('jquery'));
        wp_register_script('secsignfunctions', plugins_url('/secsignfunctions.js', __FILE__), array('jquery'));
        wp_enqueue_script('SecSignIDApi');
        wp_enqueue_script('secsignfunctions');
        $form_post_url = secsign_id_login_post_url();

        echo '
                <div id="secsignidplugincontainer">
                <div id="secsignidplugin">
                    <!-- Page Password Login -->
                    <div id="secsignid-page-pww">
                        <div class="secsignidlogo"></div>';


        // print error codes and messages
        if (!empty($error)) {
            print_error($error, null);
        }
        if (!empty($messages)) {
            echo "<p class='message'>" . apply_filters('login_messages', $messages) . "</p>\n<br>";
        }

        echo '
                        <p>There is no user assigned to your SecSign ID on "' . get_bloginfo('name') . '"</p>

        ';

        //if create new account is enabled
        if (get_option('secsignid_allow_account_creation')) {
            echo '<form action="' . $form_post_url . '" method="post" id="login-form">
                    <div class="form-group">
                        <input id="wp-username" name="wp-username" type="text" size="15" maxlength="30" class="form-control login-field" placeholder="Username">
                    </div>
                    <button type="submit" name="newaccount" value="1" id="g">Create new account</button>
                    <input type="hidden" name="secsignid" value="' . $secsignid . '" />
                </form>
        ';
        }

        if (get_option('secsignid_allow_account_creation') && get_option('secsignid_allow_account_assignment')) {
            echo "  <br><center style='font-size:150%;'>--- or ---</center><br>";
        }

        //if allow account assignment is enabled
        if (get_option('secsignid_allow_account_assignment')) {
            echo '<form action="' . $form_post_url . '" method="post" id="login-form">
                    <div class="form-group">
                        <input id="wp-username" name="wp-username" type="text" size="15" maxlength="30" class="form-control login-field" placeholder="Username">
                        <!--id="login-user" type="text" name="username" class="form-control login-field" tabindex="0" size="18" placeholder="Username">-->
                    </div>
                    <div class="form-group">
                        <!--<input  id="login-user" type="password" name="username" class="form-control login-field" tabindex="0" size="18" placeholder="Password">-->
                        <input id="wp-password" name="wp-password" type="password" size="15" maxlength="30" class="form-control login-field" placeholder="Password">
                    </div>
                    <button type="submit" tabindex="0" name="existingaccount" value="1" id="pwdloginbtn">Assign to existing account</button>
                    <input type="hidden" name="secsignid" value="' . $secsignid . '" />
                </form>
                ';
        }

        echo '</div></div></div>';

        // hide the login
        secsignid_login_hide_wp_login();
    }
}

if (!function_exists('secsignid_login_hide_wp_login')) {
    /**
     * prints jQuery code to hide the normal password based login, when using the secsign id login
     */
    function secsignid_login_hide_wp_login()
    {
        if ((strpos($_SERVER['REQUEST_URI'], 'wp-login') !== false) && get_option('secsignid_show_on_login_page')) {
            //if(get_option('secsignid_show_on_login_page')){
            echo '<script>
						jQuery("#loginform").hide();
						jQuery("#nav").hide();
						jQuery("#login .message").hide();
						jQuery("#login .message").hide();
					</script>';

        }
    }
}

if (!function_exists('add_error')) {
    /**
     * check if the global variable error is set and is an instance of WP_Error.
     * If not the function creates a new WP_Error instance and assignes it to global variable $errors.
     * After that the given error message is added to WP_Error instance.
     *
     * @param string $error_message an error message
     */
    function add_error($error_message)
    {
        global $login_errors;
        if (empty($login_errors)) {
            $login_errors = new WP_Error();
        } else if (!is_wp_error($login_errors)) {
            $errors = $login_errors;
            $login_errors = new WP_Error();
            $login_errors->add('former_error', $errors);
        }

        $login_errors->add('error', $error_message);
    }
}

if (!(function_exists('print_error'))) {
    /**
     * prints out an error
     *
     * @param string $error an error message
     * @param BOOL $print_login_form Optional. if true, it prints the login form
     */
    function print_error($error, $msg, $print_login_form = false)
    {
        if ($msg == null) {
            $msg = $error;
        } else {
            error_log($error, 0);
        }

        echo '<div id="secsignid-reg-error"><strong>Error: </strong>' . apply_filters('login_errors', $msg) . '</div>' . PHP_EOL;

        if ($print_login_form) {
            echo '<br />';
            print_login_form();
        }
    }
}

if (!(function_exists('print_message'))) {
    /**
     * prints out a message
     *
     * @param string $msg the messsage
     */
    function print_message($msg, $warning = false)
    {
        if ($msg == null) {
            return;
        }
        if (is_front_page()) {
            echo '<p>' . $msg . '</p>';
        } else {
            if ($warning) {
                // use a darker yellow as left border color
                echo '<div class="updated" style="border-left:4px solid #FFF700;background-color:#fff;padding:12px;box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.2);">';

                // or use existing wordpress update div: #update-nag
            } else {
                echo '<div class="updated" style="border-left:4px solid #4EA813;background-color:#fff;padding:12px;box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.13);">';
            }
            echo $msg;
            echo '</div><br>' . PHP_EOL;
        }
    }
}

if (!(function_exists('get_plugin_version'))) {
    /**
     * Gets the version of this plugin. It propably costs some time to parse the plugin file. But it is better to hve another variable to keep updated.
     */
    function get_plugin_version()
    {
        if (!function_exists("get_plugin_data")) {
            require_once(ABSPATH . "wp-admin/includes/plugin.php");
        }

        $plugin_data = get_plugin_data(__FILE__);
        return $plugin_data["Version"];
    }
}
?>
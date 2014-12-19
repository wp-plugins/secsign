<?php
/*
Plugin Name: SecSign
Plugin URI: https://www.secsign.com/add-it-to-your-website/
Version: 1.3
Description: The plugin allows a user to login using a SecSign ID and his smartphone.
Author: SecSign Technologies Inc.
Author URI: http://www.secsign.com
*/

// $Id: secsignid_login.php,v 1.3 2014/12/16 15:05:18 titus Exp $

    global $secsignid_login_text_domain;
    global $secsignid_login_plugin_name;

    $secsignid_login_text_domain   = "secsign";
    $secsignid_login_plugin_name   = "secsign";
    
    include(WP_PLUGIN_DIR . '/' . $secsignid_login_plugin_name . '/secsignid_login_db.php' );
    include(WP_PLUGIN_DIR . '/' . $secsignid_login_plugin_name . '/SecSignIDApi.php'); // include low-level interface to connector to SecSign ID Server
    
    // check if admin page is called
    if(is_admin())
    {
        // this creates a submenu entry and adds options to wordpress database
        include( WP_PLUGIN_DIR . '/' . $secsignid_login_plugin_name . '/secsignid_login_admin.php' );
    }

    //buttons
    global $check_auth_button;
    global $cancel_auth_button;
    $check_auth_button    = "check_auth";
    $cancel_auth_button   = "cancel_auth";
    
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
    add_action('login_footer', 'secsign_custom_login_form',0); //custom login form
    
    if(! (function_exists('secsign_custom_login_form')))
    {
		/**
		* Adds the SecSign ID login form to the wp-login.php page
		*/
		function secsign_custom_login_form()
		{
			if (get_option('secsignid_show_on_login_page'))
			{
				echo <<<SECSIGNCSS
				<script type="text/javascript">
					window.wp_attempt_focus = function(args){
					}
					
					for(var timerId = 1; timerId < 5000; timerId++){
						clearTimeout(timerId);
					}
				</script>
				<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
				<script type="text/javascript">
					$(document).ready(function(){
						// switch order of normal login fields and the secsign id block
						if($("#login .message").length > 0){
							$("#secsignid-login").insertBefore($("#login .message"));
						} else {
							$("#secsignid-login").insertBefore($("#loginform"));
						}
						
						if(typeof ajaxCheckForSessionState == 'function'){
							checkSessionStateTimerId = window.setInterval(function(){ajaxCheckForSessionState()}, timeTillAjaxSessionStateCheck);
						}
						
						// try to get focus from normal input field
						setTimeout( function(){								
							try {
								$("#secsignid").focus();
								$("#secsignid").select();
							} catch(ex) {
							}
						}, 100);
					});
				</script>    
				<style type='text/css'>
					#secsignid-login {
						position:relative;
						display:block;
						clear:both;
						width:320px;
						height:auto!important;
						margin:0px auto;
						margin-bottom:30px;
					}
				</style>;
SECSIGNCSS;

				echo "<div id='secsignid-login'>";
				secsign_id_login(array());
				echo "</div>";
			}
	   }
   }
    
    if(! (function_exists('secsign_id_check_login')))
    {
		/**
		 * this hook will be called for every password based login
		 *
		 * @param null|WP_USER|WP_Error $user null indicates no process has authenticated the user yet. 
		 *                                    A WP_Error object indicates another process has failed the authentication. 
		 *                                    A WP_User object indicates another process has authenticated the user.
		 * @param string $username  the user's username
		 * @param string $password Optional. the user's password (encypted)
		 *
		 * @return null|WP_Error|WP_User returns WP_User if password based login is allowed and password is correct. Else returns WP_Error or null.
		 */
		function secsign_id_check_login($user, $username, $password) 
		{
			if (!empty($username))
			{
				$user_object = get_user_by('login', $username);
				if ($user_object)
				{
					$allow_password_login = get_allow_password_login($user_object->id);
					if($allow_password_login)
					{
						return $user;
					}
					else
					{
						return null;
					}
				}
				else
				{
					return null;
				}
			}
			else
			{
				return $user;
			}
		}
	}
    
    if(! (function_exists('secsign_id_init')))
    {
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
					$widget_ops = array('description' => __( 'SecSign ID Login.', $secsignid_login_text_domain) );
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
    
    if(! (function_exists('secsign_id_init_auth_cookie_check')))
    {
    	/**
		 * init function which is hooked to wordpress init action.
		 * used to check if this login is legit or not
		 * on multisites you can otherwise bypass the authentication and use the password-based one even if deactivated
		 */
    	function secsign_id_init_auth_cookie_check()
    	{
    		if(is_multisite() && is_user_logged_in() //only applies to multisites, only check if logged in
    		&& (strpos($_SERVER['REQUEST_URI'],'wp-login') === false)) // not on wp-login
			{
				$user = wp_get_current_user();
				if ($user)
				{
					$allow_password_login = get_allow_password_login($user->id);
					if(!$allow_password_login && !secsign_id_verify_cookie($user->user_login)) //if password-based login not allowed and cookie not verified -> logout
					{
						wp_logout();
						wp_safe_redirect(secsign_id_login_post_url());
					}
				}
			}
		}
    }
    
    if(! (function_exists('secsign_id_get_random_secret')))
    {
    	/**
		 * gets a random secret from the db or creates it if not available
		 * @return string returns the random secret to sign the auth cookie
		 */
    	function secsign_id_get_random_secret()
    	{
    		if (!get_option('secsign_id_cookie_secret'))
    		{
    			if(function_exists('openssl_random_pseudo_bytes'))
    			{
    				$random = openssl_random_pseudo_bytes(32);
    			}
    			else
    			{
    				$random = wp_generate_password(32, true, true);
    			}
    			
    			add_option('secsign_id_cookie_secret', base64_encode($random));
    		}
    		return base64_decode(get_option('secsign_id_cookie_secret'));
		}
    }
    
    if(! (function_exists('secsign_id_verify_cookie')))
    {
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
        	if (is_ssl())
        	{
        		$cookie_name = $secsignid_login_secure_auth_cookie_name;
        	}
			
        	if(!isset($_COOKIE[$cookie_name]))
        	{
            	return false; //cookie not there
        	}

        	$cookie = explode('|', $_COOKIE[$cookie_name]);
        	if (count($cookie) != 2)
        	{
            	return false; //cookie doesn't contain value and hmac
        	}
        	
        	list($cookie_value, $signature) = $cookie;
        	if (hash_hmac('sha512', $cookie_value, secsign_id_get_random_secret()) !== $signature)
        	{
            	return false; //hmac doesn't match
        	}
			
        	$cookie_array = explode('|', base64_decode($cookie_value));
        	if (count($cookie_array) != 2)
        	{
            	return false; //cookie doesn't contain username and expiration date
        	}
        	
        	list($username_in_cookie, $expire_in_cookie) = $cookie_array;
        	if (base64_decode($username_in_cookie) !== $username)
        	{
            	return false; //wrong username in cookie
        	}
			
        	$expire = intval($expire_in_cookie);
        	if ($expire < strtotime('now'))
        	{
            	return false; //cookie expired
        	}
        	
        	return true;
		}
    }
    
    if(! (function_exists('secsign_id_set_cookie')))
    {
    	/**
		 * sets a secsign id auth cookie, which proves that the login was done with this plugin
		 * @param string $username the user's username
		 */
    	function secsign_id_set_cookie($username)
    	{
			global $secsignid_login_auth_cookie_name;
    		global $secsignid_login_secure_auth_cookie_name;
    		
    		if(is_multisite()) //only needed on multisite
    		{
        		$expire = strtotime('+1 day');
        		$secure = false;
        		$cookie_name = $secsignid_login_auth_cookie_name;
        		if (is_ssl())
        		{
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
    
    if(! (function_exists('secsign_id_unset_cookie')))
    {
    	/**
		 * unsets the secsign id auth cookie
		 */
    	function secsign_id_unset_cookie()
    	{
			global $secsignid_login_auth_cookie_name;
    		global $secsignid_login_secure_auth_cookie_name;
    		
    		if(is_multisite()) //only needed on multisite
    		{
    			$cookie_name = $secsignid_login_auth_cookie_name;
        		if (is_ssl())
        		{
        			$cookie_name = $secsignid_login_secure_auth_cookie_name;
        		}
    			setcookie($cookie_name, '', strtotime('-1 day'), COOKIEPATH, COOKIE_DOMAIN);
			}
		}
    }
    
    if(! (function_exists('secsign_id_login')))
    {
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
		
			if($user_ID == 0 || $user_ID == '')
			{
				// no user is logged in
				global $error;
				global $login_errors;
			
				$found_login_errors = false;
				$wp_error = new WP_Error();
			
				// in case a plugin uses $error rather than the $wp_errors object
				if( !empty( $error )) {
					$wp_error->add('error', $error);
					unset($error);
				}
			
				$errors = '';
				$messages = '';
			
				// snippet from standard login plugin of wordpress: 
				if($wp_error->get_error_code())
				{
					foreach ($wp_error->get_error_codes() as $code) 
					{
						$severity = $wp_error->get_error_data($code);
						foreach ($wp_error->get_error_messages($code) as $error) 
						{
							if('message' == $severity)
								$messages .= '	' . $error . "<br />" . PHP_EOL;
							else
								$errors   .= '	' . $error . "<br />" . PHP_EOL;
						}
					}
					// print error codes and messages
					if( !empty($errors))
					{
						$found_login_errors = true;
					}
				}

				// check if login errors or wordpress error exist
				if(is_wp_error($login_errors) && $login_errors->get_error_code())
				{    
					foreach ($login_errors->get_error_messages() as $error)
					{
						$errors .= '  ' . $error . "<br />" . PHP_EOL;
					
						$found_login_errors = true;
						break;
					}
				}
			
				echo $before_title .'<span>'. $widget_name .'</span>' . $after_title;   

				global $secsignid_login_no_wp_mapping;
			
				if(isset($secsignid_login_no_wp_mapping))
				{
					if($found_login_errors)
					{
						// print error codes and messages
						if( !empty($errors))
						{
							echo "<span style='color:#FF0000;'>";
							print_error($errors, null);
							echo "</span><br />";
						}
						if( !empty($messages))
						{
							echo "<p class='message'>" . apply_filters('login_messages', $messages) . "</p>\n";
							echo "<br />";
						}
					}
					print_wpuser_mapping_form();
				}
				// check if secsign id login variables are set
				else if((! $found_login_errors) && isset($_POST['requestid']) && isset($_POST['authsessionid']))
				{
					// check or cancelauth session status
					try
					{
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
					
						global $secsignid_login_auth_session_status;
						global $check_auth_button; 
					
						if(isset($_POST[$check_auth_button]))
						{
							//auth session status already checked in hooked method secsign_id_check_ticket()
						
							if(($secsignid_login_auth_session_status == AuthSession::PENDING) || ($secsignid_login_auth_session_status == AuthSession::FETCHED))
							{
								print_check_accesspass($authsession);
							}
							else
							{
								if($secsignid_login_auth_session_status == AuthSession::EXPIRED)
								{
									print_error("Access Pass expired.", null, true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::SUSPENDED)
								{
									print_error("The server suspended this session.", null, true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::INVALID)
								{
									print_error("This session has become invalid.", null, true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::CANCELED)
								{
									print_error("The server canceled this session.", null, true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::DENIED)
								{
									print_error("Authentication has been denied.", null, true);
								}
							}
						}
						else 
						{   
							// cancelauth session
							$secSignIDApi->cancelAuthSession($authsession);
						
							// show login form
							print_login_form();
						}
					}
					catch(Exception $e)
					{
						print_error("An error occured when checking status of authentication session: " . $e->getMessage(), 
									"Cannot check status of authentication session.", 
									true);
					}   
				}
				else if(isset($_POST['secsignid']) && isset($_POST['login-secsign']))
				{
						$secsignid = $_POST['secsignid'];
						// show access pass
						// contact secsign id server and request auth session
						try
						{
							$secSignIDApi          		= get_secsignid_server_instance();
							$secsignid_service_address  = site_url();
							$secsignid_service_name   	= get_option('secsignid_service_name');
							
							if(empty($secsignid_service_name)){
								$secsignid_service_name = home_url();
							}
							if (strncmp($secsignid_service_name, "https://", 8)== 0) {
								$secsignid_service_name = substr($secsignid_service_name, 8);
							}
							if (strncmp($secsignid_service_name, "http://", 7)== 0) {
								$secsignid_service_name = substr($secsignid_service_name, 7);
							}

					
							// request auth session
							$authsession = $secSignIDApi->requestAuthSession($secsignid, $secsignid_service_name, $secsignid_service_address);
					
							// got auth session
							if(isset($authsession)) {                            
								// prints a html-table with the access pass
								print_check_accesspass($authsession);                        
							} else {
								print_error("Server sent empty auth session.", 
											"Did not get authentication session. Reload page and try again later.", 
											true);
							}
						}
						catch(Exception $e)
						{
							if (strncmp($e->getMessage(), "500", 3)==0)
							{
								// internal server error code is used only for the eroor of not existing ids
								print_error("The SecSign ID does not exist. If you don't have a SecSign ID, get the free app from <a href='https://www.secsign.com' target='_blank'>SecSign.com</a> and create a new SecSign ID.", 
											null, 
											true);
							} 
							else if (strncmp($e->getMessage(), "422", 3)==0)
							{
								// actually this error should not be returned any more
								// a message would look like: 
								//
								// 422: cannot process entity
								print_error($e->getMessage(), 
											"An error occured:" . substr($e->getMessage(),5),
											true);
							} else {
								// general error
								print_error("An error occured when requesting auth session: " . $e->getMessage(), 
											"Did not get authentication session. Reload page and try again later.", 
											true);
							}
						}
				}
				else 
				{
					// check if auth session id is set
					if(isset($_POST['requestid']) && isset($_POST['authsessionid']))
					{
						// an error occured during login process. withdraw auth session
						try
						{
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
						}
						catch(Exception $e)
						{
							print_error("An error occured while canceling auth session: " . $e->getMessage(), 
										"Cannot cancel authentication session. No session exists.", 
										false);
						}
					}
				
					if($found_login_errors)
					{
						// print error codes and messages
						if( !empty($errors))
						{
							print_error($errors, null);
							echo "<br />";
						}
						if( !empty($messages))
						{
							echo "<p class='message'>" . apply_filters('login_messages', $messages) . "</p>\n";
							echo "<br />";
						}
					}
				
					// get post to url. the widget will be called again
					print_login_form();
				}
			} 
			else 
			{
				echo "<form>";
				// a user is logged in...
				echo $before_title . "SecSign ID:<br>". $after_title . "Welcome " . $current_user->user_login . "...<br><br>";

				// show a logout link and redirect to wordpress blog
				$redirectAfterLogoutTo = site_url();
				echo '<a href="' . wp_logout_url($redirectAfterLogoutTo) . '">Logout</a>';
				echo "</form>";
				if (strpos($_SERVER['REQUEST_URI'],'interim-login=1') !== false)
				{
					echo "<script>";
					echo "$(document).ready(function(){";
					echo "if($('#login .message').length > 0) $('#login .message').hide();";
					echo "$('#loginform').hide();";
					echo "});</script>";
				}
			}
		
			// print widget closing tag
			echo $after_widget;
		}
    }
    
    if(! (function_exists('secsign_id_check_ticket')))
    {
		/**
		 * the actual login process.
		 * the function is hooked to init action of wordpress.
		 * for this reasion this method is called before the widget rendering function.
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
			if (isset($_POST['newaccount']) && get_option('secsignid_allow_account_creation') && isset($_SESSION['authenticated']) && ($_SESSION['authenticated'] == $_POST['secsignid']))
			{
				if(! is_user_logged_in()) // no user is logged in
				{
					/**if ($_POST['wp-password'] == '')
					{
						add_error("Please enter a password.");
						global $secsignid_login_no_wp_mapping;
						$secsignid_login_no_wp_mapping = true;
						return;
					}
				
					if ($_POST['wp-email'] == '')
					{
						add_error("Please enter an email address.");
						global $secsignid_login_no_wp_mapping;
						$secsignid_login_no_wp_mapping = true;
						return;
					}*/
				
					if ( username_exists($_POST['wp-username'])) {
						add_error("User already exists. Please try another user name or assign your SecSign ID to this user name.");
						global $secsignid_login_no_wp_mapping;
						$secsignid_login_no_wp_mapping = true;
						return;
					}
				
					/**if (email_exists($_POST['wp-email'])) {
						add_error("Email address already exists. Please try another email.");
						global $secsignid_login_no_wp_mapping;
						$secsignid_login_no_wp_mapping = true;
						return;
					}*/
				
					//$user_id = wp_create_user($_POST['wp-username'], $_POST['wp-password'], $_POST['wp-email']);
				
					//generate random password, so nobody can login
					$random_password = wp_generate_password(20);
					$user_id = wp_create_user($_POST['wp-username'], $random_password, '');
				
				
					$user_to_login = get_user_by('login', $_POST['wp-username']);
					$user_data = apply_filters('wp_authenticate_user', $user_to_login, $random_password);
					$user      = $user_to_login;
				
					if($user_data != null){
						// re-create user
						$user =  new WP_User($user_data->ID);
					}
				
					if(!wp_check_password($random_password, $user_data->user_pass, $user_data->ID))
					{
						add_error("Sign in failed. Please try again.");
					}
					else
					{
						//Assign SecSign ID to WP User
						$mapping_array = get_user_mappings();
						$password_login_allowed = false;
						// check if mapping already exist to decide whether to call update or insert
						if($mapping_array[$user->ID])
						{
							// check if mapping equals the new secsign id.
							if($mapping_array[$user->ID]['secsignid'] !== $_POST['secsignid']){
								update_user_mapping($user->ID, $_POST['secsignid'], $password_login_allowed);
							}
						}
						else
						{
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
						}
						else if ((strpos($_SERVER['REQUEST_URI'],'wp-login') !== false) &&  (strpos($_SERVER['REQUEST_URI'],'interim-login=1') === false)) //if on login page and not in the wp-admin iframe, redirect to wp-admin
						{
							$redirect = admin_url();
						}
						wp_safe_redirect($redirect);
					}
				}
				session_destroy();
			}
			else if (isset($_POST['existingaccount']) && get_option('secsignid_allow_account_assignment') && isset($_SESSION['authenticated']) && ($_SESSION['authenticated'] == $_POST['secsignid'])) //login and assign secsign id to wp user
			{
				$user_to_login = get_user_by('login', $_POST['wp-username']);
				if($user_to_login)
				{
					if(! is_user_logged_in()) // no user is logged in
					{
						$user_data = apply_filters('wp_authenticate_user', $user_to_login, $_POST['wp-password']);
						$user      = $user_to_login;
					
						if($user_data != null){
							// re-create user
							$user =  new WP_User($user_data->ID);
						}
					
						if(!wp_check_password($_POST['wp-password'], $user_data->user_pass, $user_data->ID))
						{
							add_error("Wrong Password. Please try again.");
							global $secsignid_login_no_wp_mapping;
							$secsignid_login_no_wp_mapping = true;
							return;
						}
						else
						{
							//Assign SecSign ID to WP User
							$mapping_array = get_user_mappings();
							$password_login_allowed = true;
							// check if mapping already exist to decide whether to call update or insert
							if($mapping_array[$user->ID])
							{
								// check if mapping equals the new secsign id.
								if($mapping_array[$user->ID]['secsignid'] !== $_POST['secsignid']){
									update_user_mapping($user->ID, $_POST['secsignid'], $password_login_allowed);
								}
							}
							else
							{
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
							}
							else if ((strpos($_SERVER['REQUEST_URI'],'wp-login') !== false) &&  (strpos($_SERVER['REQUEST_URI'],'interim-login=1') === false)) //if on login page and not in the wp-admin iframe, redirect to wp-admin
							{
								$redirect = admin_url();
							}
							wp_safe_redirect($redirect);
						}
					}
					session_destroy();
				}
				else
				{
					add_error("No wordpress user exists for the username '" . $_POST['wp-username'] . "'.");
					global $secsignid_login_no_wp_mapping;
					$secsignid_login_no_wp_mapping = true;
					return;

				}
			}
			else if(isset($_POST['requestid']) && isset($_POST['authsessionid'])) //check state of session
			{
				global $check_auth_button;
				if(isset($_POST[$check_auth_button]))
				{
					global $secsignid_login_auth_session_status;
				
					$secsignid_login_auth_session_status = AuthSession::NOSTATE;
			
					try
					{
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
						$secsignid_login_auth_session_status = $secSignIDApi->getAuthSessionState($authsession);
					}
					catch(Exception $e)
					{
						$errorMessage = "An error occured when checking status of authentication session: " . $e->getMessage();
						add_error($errorMessage);
					
						$secsignid_login_auth_session_status = AuthSession::NOSTATE;
					}

					if($secsignid_login_auth_session_status == AuthSession::AUTHENTICATED)
					{
						//save to the session, that the secsign id was authenticated. This will later allow the assignment to/creation of a wordpress user
						$_SESSION['authenticated']=$_POST['secsignid'];
						// release authentication session. it is not used any more
						$secSignIDApi->releaseAuthSession($authsession);
					
						$user_to_login = get_wp_user($_POST['secsignid']);
						if($user_to_login)
						{
							if($user_to_login->user_login == $_POST['mapped_wp_user'])
							{
								if(! is_user_logged_in()) // no user is logged in
								{
									$user_data = apply_filters('wp_authenticate_user', $user_to_login, '');
									$user      = $user_to_login;
								
									if($user_data != null){
										// re-create user
										$user =  new WP_User($user_data->ID);
									}
								
									wp_set_auth_cookie($user->ID, false, is_ssl());
									secsign_id_set_cookie($user->user_login);
									do_action('wp_login', $user->user_login, $user);
					
									wp_set_current_user($user->ID);
						
									$redirect = secsign_id_login_post_url(); //redirect to same page
					
									if (isset($_SESSION['redirect_to'])) //if redirect url is given, use it
									{
										$redirect = $_SESSION['redirect_to'];
									}
									else if ((strpos($_SERVER['REQUEST_URI'],'wp-login') !== false) &&  (strpos($_SERVER['REQUEST_URI'],'interim-login=1') === false)) //if on login page and not in the wp-admin iframe, redirect to wp-admin
									{
										$redirect = admin_url();
									}
									wp_safe_redirect($redirect);
									session_destroy();
								}
							}
							else
							{
								// found word press user is not same than wp user from POST parameters
								add_error("Wrong wordpress user specified for secsign id '" . $_POST['secsignid'] . "'.");
							}
						}
						else
						{
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
    
    
    if(! (function_exists('secsign_id_login_post_url')))
    {
        /**
         * builds an url which is used for all html forms to post data to.
         */
        function secsign_id_login_post_url()
        {
            if (strncmp(get_site_url(), "https", 5)== 0) $prot = "https";
            else $prot = "http";
            
            $post_url = $_SERVER['REQUEST_URI'];
            
            $post_url = secsign_id_login_remove_all_url_params($post_url, $redirect_url);
            if (!empty($redirect_url))
            {
            	session_start();
            	$_SESSION['redirect_to']=urldecode($redirect_url);
            }
            
            if (strcmp($post_url,"")==0) $post_url = "/";
            
            $port = ":" . $_SERVER['SERVER_PORT'];
            
            return $prot . "://" . $_SERVER['SERVER_NAME'] . $port . $post_url;
        }
    }
    
    if(! (function_exists('secsign_id_login_remove_all_url_params')))
    {
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
        function secsign_id_login_remove_all_url_params($url, &$redirect_to=NULL)
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
            	foreach($exploded_params as $para) //for each parameter
            	{
            		$exploded_para = explode("=", $para);
            		if (count($exploded_para) == 2)
            		{
            			if ($exploded_para[0] == "redirect_to")
            			{
            				$redirect_to = $exploded_para[1];
            			}
            			else if (($exploded_para[0] == "loggedout") || ($exploded_para[0] == "reauth") || ($exploded_para[0] == "action"))
            			{
            				//do nothing, we don't want these parameters
            			}
            			else //all other parameters are added to the url again
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
    
    if(! (function_exists('get_secsignid_server_instance')))
    {
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
    
    if(! (function_exists('print_login_form')))
    {
        /**
         * prints out the actual login form
         */
        function print_login_form()
        {
            $form_post_url = secsign_id_login_post_url();


            $css = <<<ENDCSS
\n\n<style type='text/css'>
        .widget-area #secsignid_loginform {
            margin:5px 0px 5px 0;width:100%;
        }

        .widget-area #secsignid_loginform p{
            margin:3px 0px 3px 0;
            padding: 0;
        }

        .widget-area #secsignid_loginform button{
        width: 100%;
min-height: 25px;
margin: 5px 0;
        }

        .widget-area #secsignid{
        width:100%;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        -ms-box-sizing: border-box;
        box-sizing: border-box;
        }

        .login .login_wrapper{
        padding: 20px;
        }
</style>\n\n
ENDCSS;
            echo $css;

            echo "<form id='secsignid_loginform' action='" . $form_post_url . "' method='post' style='width:100%;margin:0;padding:0;border:none'>" . PHP_EOL;
            echo "  <div class='login_wrapper'><p>SecSign ID:</p>" . PHP_EOL;
            echo "  <input id='secsignid' name='secsignid' type='text' size='30' maxlength='30' />" . PHP_EOL;
            echo "  <button type ='submit' name='login-secsign' value='1' class='button button-primary button-large'>Log In</button><a href='https://www.secsign.com/sign-up/' target='_blank'>New to SecSign?</a>" . PHP_EOL;
            echo "<div style='clear:both;'></div>\n</div>\n</form>\n";
         
			try {
				echo "<!-- secsign id plugin version: " . get_plugin_version() . " -->\n\n";
			} catch(Exception $e){
					echo "<!-- error finding version: " . $e . " -->\n\n";
			}
        }
    }

    if(! (function_exists('print_wpuser_mapping_form')))
    {
        /**
         * prints out the WP User mapping login form
         */
        function print_wpuser_mapping_form()
        {
            $form_post_url = secsign_id_login_post_url();
$css = <<<ENDCSS
<style type='text/css'>																													
					table.secsignid,
					table.secsignid tbody,
					table.secsignid tr,
					table.secsignid td {
						margin:0;
						padding:0;
						width:100%;
						display:block;
						position:relative;
					}
					
					table.secsignid {
						float:left
					}
					
					table.secsignid button {
						width:100%;
					}
					
					table.secsignid td {
						padding:2%;
					}
					
					table.secsignid td .descr {
						position:relative;
						display:block;
						width:96%;
					}
					
					table.secsignid td input {
						margin:10px 0px;
						/*font-size: 24px;*/
						/*padding: 3px;*/
						background: none repeat scroll 0% 0% #FBFBFB;
						position:relative;
						display:block;
						width:100%;
						clear:both;
					}
</style>
ENDCSS;
            
            echo $css;
            
            echo "<form action='" . $form_post_url . "' method='post'>" . PHP_EOL;
            echo "  There is no user assigned to your SecSign ID on \"" . get_bloginfo('name') . "\".<br><br>";
            if (get_option('secsignid_allow_account_creation'))
            {
            	echo "  <table class='secsignid'>" . PHP_EOL;
            	echo "  <tr><td>Username:</td></tr><tr><td><input id='wp-username' name='wp-username' value='" . $_POST['secsignid'] . "' type='text' size='15' maxlength='30' /></td></tr>" . PHP_EOL;
            	//echo "  <tr><td>Password:</td><td><input id='wp-password' name='wp-password' type='password' size='15' maxlength='30' /></td></tr>" . PHP_EOL;
            	//echo "  <tr><td>E-Mail:</td><td><input id='wp-email' name='wp-email' type='text' size='15' maxlength='30' /></td></tr></table>" . PHP_EOL;
            	echo "</table>";
            	echo "<input type='hidden' name='secsignid' value='" . $_POST['secsignid'] . "' />" . PHP_EOL;
            	echo "  <center><button style='margin-top:10px;padding:8px 4px;' type ='submit' name='newaccount' value='1'>Create new account</button></center>" . PHP_EOL;
            	
            }
            echo "</form>";
            if (get_option('secsignid_allow_account_creation') && get_option('secsignid_allow_account_assignment'))
            {
            	echo "  <br><center style='font-size:150%;'>--- or ---</center><br>";
            }
            if (get_option('secsignid_allow_account_assignment'))
            {
            	echo "<form action='" . $form_post_url . "' method='post'>" . PHP_EOL;
            	echo "  <table class='secsignid'><tr><td>Username:</td></tr><tr><td><input id='wp-username' name='wp-username' type='text' size='15' maxlength='30' /></td></tr>" . PHP_EOL;
            	echo "  <tr><td>Password:</td></tr><tr><td><input id='wp-password' name='wp-password' type='password' size='15' maxlength='30' /></td></tr></table>" . PHP_EOL;
            	echo "<input type='hidden' name='secsignid' value='" . $_POST['secsignid'] . "' />" . PHP_EOL;
            	echo "  <center><button style='margin-top:10px;padding:8px 4px;' type ='submit' name='existingaccount' value='1'>Assign to existing account</button></center> <br />" . PHP_EOL;
            	echo "</form>";
            }
            
            // hide the login
            secsignid_login_hide_wp_login();
        }
    }
    
    
    if(! (function_exists('print_check_accesspass')))
    {
        /**
         * prints out the access pass and the check form
         *
         * @param AuthSession $authsession the authentication session including the access pass
         * @throws Exception
         */
        function print_check_accesspass($authsession)
        {
            if(!isset($authsession) || !($authsession instanceof AuthSession))
            {
                throw new Exception("Cannot show access pass, given \$authsession is either null or not an instance of AuthSession.");
            }



           echo "
<style type='text/css'>
 #secsign_accesspass_form button{
        width:90px;

    }

    .secsign_accesspass_big {
    display:block;
background:url(" . get_site_url() . "/wp-content/plugins/secsign/accesspass_bg.png) transparent no-repeat scroll left top;
    background-size:155px 206px;
    width:155px;
    height:206px;
    vertical-align:middle;
    margin:0px auto;
    }

    .secsign_accesspass_small {
    display:block;
    background: white;
    width:100%;
    margin:0px auto;
    }

    .secsign_accesspass_img_big{
    position:relative;
    width:80px;
    height:80px;
    left:35px;
    top:90px;
    box-shadow:0px 0px 0px #FFF;
    }

    .secsign_accesspass_img_small{
    position:relative;
    width:100%;
    }


    </style>
       ";



//            if secsign_accesspass_form < 300

        
            global $check_auth_button;
            global $cancel_auth_button;
            
            $form_post_url = secsign_id_login_post_url();
        
            // show access pass and print all information which is need to verify auth session
            echo "<form id='secsign_accesspass_form' action='" . $form_post_url . "' method='post' style='width:100%;margin:0;padding:0;float:left;display:block;position:relative;border:none'>". PHP_EOL;
        
            // all information which is need to get auth session status if user hit 'OK' button
            echo "<input type='hidden' name='requestid' value='" . $authsession->getRequestID() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='secsignid' value='" . $authsession->getSecSignID() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='authsessionid' value='" . $authsession->getAuthSessionID() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='servicename' value='" . $authsession->getRequestingServiceName() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='serviceaddress' value='" . $authsession->getRequestingServiceAddress() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='authsessionicondata' value='" . $authsession->getIconData() . "' />" . PHP_EOL;

            $mapped_user = get_wp_user($authsession->getSecSignID());
            echo "<input type='hidden' name='mapped_wp_user' value='" . ($mapped_user != null ? $mapped_user->user_login : "null") . "' />" . PHP_EOL;

            echo "<p style='text-align: center'><b>Access Pass for " . $authsession->getSecSignID() . "</b></p>" . PHP_EOL;
            echo "<div id='secsign_accesspass' class='secsign_accesspass_big'>" . PHP_EOL;
            echo "<img id='secsign_accesspass_img' class='secsign_accesspass_img_big' src=\"data:image/png;base64," . $authsession->getIconData() . "\">" . PHP_EOL;
            echo "</div>";
            echo "<p style='text-align: center'>Please verify the access pass using your smartphone.</p>" . PHP_EOL;
            echo "<div style='margin: 5px auto; text-align: center;'><div id='secsign_button_wrapper' style='display: inline-block;'>";
            echo "<button type ='submit' name='" . $cancel_auth_button . "' value='1' style='margin: 5px 0;min-height:25px;'>Cancel</button>" . PHP_EOL;
            echo "<button type ='submit' name='" . $check_auth_button . "' value='1' style='margin: 5px 0;min-height:25px;'>OK</button>" . PHP_EOL;
            echo "</div></div>";
            // end of form
            echo "</form><div style='display:block;clear:both'></div>". PHP_EOL;
            secsignid_login_hide_wp_login();
            secsignid_login_print_ajax_check($authsession);

            echo '
            <script type="text/javascript">

function responsive() {
          var width = document.getElementById("secsign_accesspass_form").offsetWidth;
        if(width<= 220){
            $("#secsign_accesspass_form button").css("width", "100%");
            $("#secsign_button_wrapper").css("width", "100%");
        } else {
            $("#secsign_accesspass_form button").css("width", "90px");
            $("#secsign_button_wrapper").css("width", "initial");
        }

        if(width<= 160){
            $("#secsign_accesspass").removeClass("secsign_accesspass_big");
            $("#secsign_accesspass").addClass("secsign_accesspass_small");
            $("#secsign_accesspass_img").removeClass("secsign_accesspass_img_big");
            $("#secsign_accesspass_img").addClass("secsign_accesspass_img_small");
        } else {
            $("#secsign_accesspass").removeClass("secsign_accesspass_small");
            $("#secsign_accesspass").addClass("secsign_accesspass_big");
            $("#secsign_accesspass_img").removeClass("secsign_accesspass_img_small");
            $("#secsign_accesspass_img").addClass("secsign_accesspass_img_big");
        }
}
$( window ).resize(function() {
  responsive();
});

responsive();
</script>


            ';
        }
    }
    
    if(! function_exists('secsignid_login_hide_wp_login'))
    {
        /**
         * prints jQuery code to hide the normal password based login, when using the secsign id login
         */
        function secsignid_login_hide_wp_login()
        {
            if ((strpos($_SERVER['REQUEST_URI'],'wp-login') !== false) && get_option('secsignid_show_on_login_page'))
            {
            	echo "<script>";
            	echo '$("#loginform").hide();';
				echo '$("#nav").hide();';
				echo '$("#login .message").hide();';
				echo "</script>";
            }
        }
    }
    
    if(! function_exists('secsignid_login_print_ajax_check'))
    {
        /**
         * prints ajax code to check the status of the login and click the OK button automatically when the login was accepted
         *
         * @param AuthSession $authsession the authentication session to poll the session state for
         */
        function secsignid_login_print_ajax_check($authsession)
        {
        	global $check_auth_button;
            echo "<script type='text/javascript' src='". plugins_url( 'SecSignIDApi.js' , __FILE__ )  . "'></script>". PHP_EOL . "<script>";
            echo "var timeTillAjaxSessionStateCheck = 3700; var checkSessionStateTimerId = -1;". PHP_EOL;
            echo "function ajaxCheckForSessionState(){". PHP_EOL;  
            echo "var secSignIDApi = new SecSignIDApi({'posturl' : '" . parse_url(plugins_url( 'signin-bridge.php' , __FILE__ ), PHP_URL_PATH) . "'});". PHP_EOL;
			echo "secSignIDApi.getAuthSessionState(";
			echo "'" .$authsession->getSecSignID() . "', '" . $authsession->getRequestID() ."', '". $authsession->getAuthSessionID() . "', ". PHP_EOL;
$js = <<<VERBATIMJS
function(responseMap){  
if(responseMap){
	// check if response map contains error message or if authentication state could not be fetched from server.
	if("errormsg" in responseMap){
    	return;
    } else if(! ("authsessionstate" in responseMap)){
    	return;
	}
	if(responseMap["authsessionstate"] == undefined || responseMap["authsessionstate"].length < 1){
    	// got answer without an auth session state. this is not parsable and will throw the error UNKNOWN
        return;
    }
                    
    // everything okay. authentication state can be checked...
    var authSessionStatus = parseInt(responseMap["authsessionstate"]);
    var SESSION_STATE_NOSTATE = 0;
    var SESSION_STATE_PENDING = 1;
    var SESSION_STATE_EXPIRED = 2;
    var SESSION_STATE_AUTHENTICATED = 3;
    var SESSION_STATE_DENIED = 4;
    var SESSION_STATE_SUSPENDED = 5;
    var SESSION_STATE_CANCELED = 6;
    var SESSION_STATE_FETCHED = 7;
    var SESSION_STATE_INVALID = 8;
    
    if((authSessionStatus == SESSION_STATE_AUTHENTICATED) || (authSessionStatus == SESSION_STATE_DENIED) || (authSessionStatus == SESSION_STATE_EXPIRED)
    || (authSessionStatus == SESSION_STATE_SUSPENDED) || (authSessionStatus == SESSION_STATE_INVALID) || (authSessionStatus == SESSION_STATE_CANCELED)){
    	window.clearInterval(checkSessionStateTimerId);
VERBATIMJS;
			echo $js . PHP_EOL;
            echo "$(\"button[name='". $check_auth_button ."']\").click();". PHP_EOL;
            echo "}";
            echo "}});";
			echo "}";
			echo "</script>";
			
			if (strpos($_SERVER['REQUEST_URI'],'wp-login') === false) //we are not on the login page, so start the ajax request to check the session state
            {
            	echo "<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js'></script><script>";
            	echo "if(typeof ajaxCheckForSessionState == 'function')";
				echo "{";
				echo "	checkSessionStateTimerId = window.setInterval(function(){ajaxCheckForSessionState()}, timeTillAjaxSessionStateCheck);";
				echo "}";
				echo "</script>";
            } //else start it in the secsign_custom_login_form hook, because we are moving parts in the DOM tree around that would otherwise end in evaluating the js call two times
        }
    }
    
    if(! function_exists('add_error'))
    {
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
            if(empty($login_errors))
            {
                $login_errors = new WP_Error();
            } 
            else if(! is_wp_error($login_errors))
            {
                $errors = $login_errors;
                $login_errors = new WP_Error();
                $login_errors->add('former_error', $errors);
            }
            
            $login_errors->add('error', $error_message);
        }
    }
    
    if(! (function_exists('print_error')))
    {
        /**
         * prints out an error
         *
         * @param string $error an error message
         * @param BOOL $print_login_form Optional. if true, it prints the login form
         */
        function print_error($error, $msg, $print_login_form = false)
        {
        	if($msg == null){
        		$msg = $error;
        	} else {
	        	error_log($error, 0);
	        }
	        
            echo '<div class="login_error">' . apply_filters('login_errors', $msg) . '</div>' . PHP_EOL;

            if($print_login_form){
                echo '<br />';
                print_login_form();
            }
        }
    }
    
    if(! (function_exists('get_plugin_version')))
    {
    	/**
    	 * Gets the version of this plugin. It propably costs some time to parse the plugin file. But it is better to hve another variable to keep updated.
    	 */
    	function get_plugin_version() {
    		/*
	    	if(! function_exists("get_plugins")){
	   	     	require_once(ABSPATH . "wp-admin/includes/plugin.php");
	  	  	}
	    
	    	$plugin_folder = get_plugins("/" . plugin_basename(dirname( __FILE__ )));
	    	$plugin_file = basename(( __FILE__ ));
	    
	    	return $plugin_folder[$plugin_file]["Version"];
	    	*/
	    
	    	if(! function_exists("get_plugin_data")){
	        	require_once(ABSPATH . "wp-admin/includes/plugin.php");
	    	}
	    
	    	$plugin_data = get_plugin_data(__FILE__);
        	return $plugin_data["Version"];
        }
	}
?>
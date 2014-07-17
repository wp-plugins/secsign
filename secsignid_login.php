<?php
/*
Plugin Name: SecSign
Version: 1.0.5
Description: The plugin allows a user to login using a SecSign ID and his smartphone.
Author: SecSign Technologies Inc.
Author URI: http://www.secsign.com
*/

// $Id: secsignid_login.php,v 1.17 2014/07/07 14:43:32 jwollner Exp $

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

    // globals
    global $check_auth_button;
    global $cancel_auth_button;
    
    $check_auth_button    = "check_auth";
    $cancel_auth_button   = "cancel_auth";
    
    global $secsignid_login_auth_session_status;
    $secsignid_login_auth_session_status = AuthSession::NOSTATE;
    
    /**
     * wordpress hooks
     */
    add_action('init', 'secsign_id_init', 1);
    add_action('init', 'secsign_id_check_ticket', 0);
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
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
				<script>
					$(document).ready(function(){
						if($("#login .message").length > 0)
							$("#secsignid-login").insertBefore($("#login .message"));
						else
							$("#secsignid-login").insertBefore($("#loginform"));

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
    
    if(! (function_exists('secsign_id_init')))
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
							print_error($errors);
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
					// check or cancel ticket status
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
							// ticket status already checked in hooked method secsign_id_check_ticket()
						
							if(($secsignid_login_auth_session_status == AuthSession::PENDING) || ($secsignid_login_auth_session_status == AuthSession::FETCHED))
							{
								print_check_accesspass($authsession);
							}
							else
							{
								if($secsignid_login_auth_session_status == AuthSession::EXPIRED)
								{
									print_error("Access Pass expired...", true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::SUSPENDED)
								{
									print_error("The server suspended this session.", true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::INVALID)
								{
									print_error("This session has become invalid.", true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::CANCELED)
								{
									print_error("The server canceled this session.", true);
								}
								else if($secsignid_login_auth_session_status == AuthSession::DENIED)
								{
									print_error("Authentication has been denied...", true);
								}
							}
						}
						else 
						{   
							// cancel ticket
							$secSignIDApi->cancelAuthSession($authsession);
						
							// show login form
							print_login_form();
						}
					}
					catch(Exception $e)
					{
						print_error("An error occured when checking status of ticket: " . $e->getMessage(), true);
					}   
				}
				else if(isset($_POST['secsignid']) && isset($_POST['login']))
				{
						$secsignid = $_POST['secsignid'];
						// show access pass
						// contact secsign id server and request auth session
						try
						{
							$secSignIDApi          = get_secsignid_server_instance();
							$secsignid_service_name   = get_option('secsignid_service_name');
							if(empty($secsignid_service_name)){
								$secsignid_service_name = home_url();
							}
							if (strncmp($secsignid_service_name, "https://", 8)== 0)
								$secsignid_service_name = substr($secsignid_service_name, 8);
							if (strncmp($secsignid_service_name, "http://", 7)== 0)
								$secsignid_service_name = substr($secsignid_service_name, 7);

					
							// request auth session
							$authsession = $secSignIDApi->requestAuthSession($secsignid, $secsignid_service_name, get_option('secsignid_service_name'));
					
							// got auth session
							if(isset($authsession))
							{                            
								// prints a html-table with the access pass
								print_check_accesspass($authsession);                        
							}
							else
							{
								print_error("Server sent empty auth session.", true);
							}
						}
						catch(Exception $e)
						{
							if (strncmp($e->getMessage(), "500",3)==0)
							{
								print_error("The SecSign ID does not exist. If you don't have a SecSign ID, get the free app from <a href='https://www.secsign.com' target='_blank'>SecSign.com</a> and create a new SecSign ID.",true);
							}
							else if (strncmp($e->getMessage(), "422",3)==0)
							{
								print_error(substr($e->getMessage(),5),true);
							}

							else
							print_error("An error occured when requesting auth session: " . $e->getMessage() , true);
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
							print_error("An error occured while canceling auth session: " . $e->getMessage(), false);
						}
					}
				
					if($found_login_errors)
					{
						// print error codes and messages
						if( !empty($errors))
						{
							print_error($errors);
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
					
						wp_set_auth_cookie($user->ID, false, is_ssl()); // http://codex.wordpress.org/Function_Reference/wp_set_auth_cookie
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
						$errorMessage = "An error occured when checking status of ticket: " . $e->getMessage();
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
								
									wp_set_auth_cookie($user->ID, false, is_ssl()); // http://codex.wordpress.org/Function_Reference/wp_set_auth_cookie
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
            
            $post_url = secsign_id_login_remove_url_param($post_url, 'loggedout');
            $post_url = secsign_id_login_remove_url_param($post_url, 'redirect_to', $redirect_url);
            if (!empty($redirect_url))
            {
            	session_start();
            	$_SESSION['redirect_to']=$redirect_url;
            }
            $post_url = secsign_id_login_remove_url_param($post_url, 'reauth');
            $post_url = secsign_id_login_remove_url_param($post_url, 'action');
            
            if (strcmp($post_url,"")==0) $post_url = "/";
            
            $port = ":" . $_SERVER['SERVER_PORT'];
            
            return $prot . "://" . $_SERVER['SERVER_NAME'] . $port . $post_url;
        }
    }
    
    if(! (function_exists('secsign_id_login_remove_url_param')))
    {
        /**
         * removes a given parameter from a url path
         * the third parameter is optional and returns the value by reference
         * Example: secsign_id_login_remove_url_param('/wp-login-php?para1=1&para2=2', 'para1')
         *  -> '/wp-login-php?para2=2'
         *
         * @param string $url the URL path to remove the parameter from
         * @param string $param_to_remove the name of the parameter to remove
         * @param string $value Optional. if given, will be set to the value of the parameter, that was removed
         *
         * @return string the url without the given parameter
         */
        function secsign_id_login_remove_url_param($url, $param_to_remove, &$value=NULL)
        {
            $parsed_uri = parse_url($url);
        	if (isset($parsed_uri['query']))
        	{
        		parse_str($parsed_uri['query'],$query);
        		if (isset($query[$param_to_remove]))
        		{
        			$value = $query[$param_to_remove];
        			unset($query[$param_to_remove]);
        		}
            	
            	return $parsed_uri['path'] . '?' . http_build_query($query);
        	}
            return $url;
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
        
            echo "<form action='" . $form_post_url . "' method='post' style='width:90%;margin:0;padding:5%;border:none'>" . PHP_EOL;
            echo "  SecSign ID:<br>" . PHP_EOL;
            echo "  <input id='secsignid' name='secsignid' type='text' size='30' maxlength='30' style='margin-top:5px;width:96%;float:left'/>" . PHP_EOL;
            echo "  <button type ='submit' name='login' value='1' style='width:70px;min-height:25px;'>Login</button> <span style='font-size:80%;position:relative;left:40px;'><a href='https://www.secsign.com/sign-up/' target='_blank'>New to SecSign?</a></span>" . PHP_EOL;
            echo "</form>";
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
						font-size: 24px;
						padding: 3px;
						background: none repeat scroll 0% 0% #FBFBFB;
						position:relative;
						display:block;
						width:100%;
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
            	echo "  <center><button style='margin-top:10px;font-size:110%;' type ='submit' name='newaccount' value='1'>Create new account</button></center>" . PHP_EOL;
            	
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
            	echo "  <center><button style='margin-top:10px;font-size:110%;' type ='submit' name='existingaccount' value='1'>Assign to existing account</button></center> <br />" . PHP_EOL;
            	echo "</form>";
            }
            secsignid_login_hide_wp_login();
        }
    }
    
    
    if(! (function_exists('print_check_accesspass')))
    {
        /**
         * prints out the access pass and the check form
         *
         * @param AuthSession $authsession the authentication session including the access pass
         */
        function print_check_accesspass($authsession)
        {
            if(!isset($authsession) || !($authsession instanceof AuthSession))
            {
                throw new Exception("Cannot show access pass, given \$authsession is either null or not an instance of AuthSession.");
            }
        
            global $check_auth_button;
            global $cancel_auth_button;
            
            $form_post_url = secsign_id_login_post_url();
        
            // show access pass and print all information which is need to verify auth session
            echo "<form action='" . $form_post_url . "' method='post' style='width:100%;margin:0;padding:0;float:left;display:block;position:relative;border:none'>". PHP_EOL;
        
            // all information which is need to get auth session status if user hit 'OK' button
            echo "<input type='hidden' name='requestid' value='" . $authsession->getRequestID() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='secsignid' value='" . $authsession->getSecSignID() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='authsessionid' value='" . $authsession->getAuthSessionID() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='servicename' value='" . $authsession->getRequestingServiceName() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='serviceaddress' value='" . $authsession->getRequestingServiceAddress() . "' />" . PHP_EOL;
            echo "<input type='hidden' name='authsessionicondata' value='" . $authsession->getIconData() . "' />" . PHP_EOL;

            $mapped_user = get_wp_user($authsession->getSecSignID());
            echo "<input type='hidden' name='mapped_wp_user' value='" . ($mapped_user != null ? $mapped_user->user_login : "null") . "' />" . PHP_EOL;
            
            // table whith access pass and two button for 'OK' and 'Cancel'
            echo "<table style='width:100%;height:80%;display:block;position:relative;float:left'>" . PHP_EOL;
            echo "  <tr style='margin:0px;padding:0px;'>" . PHP_EOL;
            echo "      <td colspan='2' style='text-align:center;padding:0;margin:0px;'>" . PHP_EOL;
            echo "          <b>Access Pass for " . $authsession->getSecSignID() . "</b>" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;
            echo "  <tr style='margin:0px;padding:0px;'>" . PHP_EOL;
            echo "      <td colspan='2' style='margin:0px;padding:20px 0px 0px 0px;'>" . PHP_EOL;
            echo "<div style='display:block;background:url(" . get_site_url() . "/wp-content/plugins/secsign/accesspass_bg.png) transparent no-repeat scroll left top;background-size:155px 206px;width:155px;height:206px;vertical-align:middle;margin:0px auto;'>" . PHP_EOL;
            echo "<img style='position:relative;width:80px;height:80px;left:35px;top:90px;box-shadow:0px 0px 0px #FFF;' src=\"data:image/png;base64," . $authsession->getIconData() . "\">" . PHP_EOL;
            echo "</div><br /><br />" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;

            echo "  <tr style='margin:0px;padding:0px;'>" . PHP_EOL;
            echo "      <td colspan='2' style='margin:0px;padding:0px;'>" . PHP_EOL;
            echo "          <div style='width:90%;padding:5%;'>Please verify the access pass using your smartphone.<br /><br /></div>" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;

            echo "  <tr style='margin:0px;padding:0px;border:none;'>" . PHP_EOL;
            echo "      <td align='left' style='margin:0px;padding:10px 0px;border-right:none;'>" . PHP_EOL;
        
            // cancel button
            echo "          <button type ='submit' name='" . $cancel_auth_button . "' value='1' style='width:100px;min-height:25px;'>Cancel</button>" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "      <td align='right' style='margin:0px;padding:10px 0px;border-left:none;'>" . PHP_EOL;
        
            // ok button which will trigger auth status check
            echo "          <button type ='submit' name='" . $check_auth_button . "' value='1' style='width:100px;min-height:25px;'>OK</button>" . PHP_EOL;
        
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;
            echo "</table>" . PHP_EOL;
        
            // end of form
            echo "</form><div style='display:block;clear:both'></div>". PHP_EOL;
            secsignid_login_hide_wp_login();

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
        function print_error($error, $print_login_form = false)
        {
            echo '<div class="login_error">' . apply_filters('login_errors', $error) . '</div>' . PHP_EOL;

            if($print_login_form){
                echo '<br />';
                print_login_form();
            }
        }
    }
?>
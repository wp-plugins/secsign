<?php
/*
Plugin Name: SecSign
Version: 1.0.2
Description: The plugin allows a user to login using a SecSign ID and their smartphone.
Author: SecSign Technologies Inc.
Author URI: http://www.secsign.com
*/

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
        
        // register widget
        register_widget('SecSignIDLogin_Widget');
    }
    
    
    
    /**
     * function top draw widget. this function is called by widgets widget() function.
     * it is called whenever wordpress needs to render the widget
     */
    function secsign_id_login($args)
    {
        extract($args); // after this the key names of the associative array can be used like variables
        
        global $current_user; // instance of type WP_User: http://codex.wordpress.org/Class_Reference/WP_User
        global $user_ID;
        
        get_currentuserinfo(); // http://codex.wordpress.org/Function_Reference/get_currentuserinfo
        
        // print widget opening tage
        echo $before_widget;
        
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
            
            // snippet got from standard login plugin of wordpress:
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
            else if((! $found_login_errors) && isset($_POST['secsignid']) && isset($_POST['login']))
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
            // a user is logged in...
            echo $before_title . "Welcome " . $current_user->user_login . "..." . $after_title;
            
            // show a logout link and redirect to wordpress blog
            $redirectAfterLogoutTo = site_url();
            echo '<a href="' . wp_logout_url($redirectAfterLogoutTo) . '">Logout</a>';
        }
        
        // print widget closing tag
        echo $after_widget;
    }
    
    
    
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
        if (isset($_POST['newaccount']))
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
                $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
                $user_id = wp_create_user($_POST['wp-username'], $random_password, '');
                
                
                $user_to_login = get_user_by('login', $_POST['wp-username']);
                $user_data = apply_filters('wp_authenticate_user', $user_to_login, $random_password);
                $user      = $user_to_login;
                
                if($user_data != null){
                    // re-create user
                    $user =  new WP_User($user_data->ID);
                }
                
                if ( !user_pass_ok( $_POST['wp-username'], $random_password ) )
                {
                    //add_error("Wrong Password. Please try again.");
                    add_error("Sign in failed. Please try again.");
                }
                else
                {
                    //Assign SecSign ID to WP User
                    $mapping_array = get_user_mappings();
                    // check if mapping already exist to decide whether to call update or insert
                    if($mapping_array[$user->ID])
                    {
                        // check if mapping equals the new secsign id.
                        if($mapping_array[$user->ID]['secsignid'] !== $_POST['secsignid']){
                            update_user_mapping($user->ID, $_POST['secsignid']);
                        }
                    }
                    else
                    {
                        insert_user_mapping($user->ID, $user->user_login, $_POST['secsignid']);
                    }
                    
                    // set auth cookie
                    wp_set_auth_cookie($user->ID, false, is_ssl()); // http://codex.wordpress.org/Function_Reference/wp_set_auth_cookie
                    do_action('wp_login', $user->user_login, $user);
                    
                    wp_set_current_user($user->ID);
                    
                    // safe direct to same domain
                    wp_safe_redirect(secsign_id_login_post_url());
                }
            }
        }
        else if (isset($_POST['existingaccount'])) //login and assign secsign id to wp user
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
                    
                    if ( !user_pass_ok( $_POST['wp-username'], $_POST['wp-password'] ) )
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
                        // check if mapping already exist to decide whether to call update or insert
                        if($mapping_array[$user->ID])
                        {
                            // check if mapping equals the new secsign id.
                            if($mapping_array[$user->ID]['secsignid'] !== $_POST['secsignid']){
                                update_user_mapping($user->ID, $_POST['secsignid']);
                            }
                        }
                        else
                        {
                            insert_user_mapping($user->ID, $user->user_login, $_POST['secsignid']);
                        }
                        
                        // set auth cookie
                        wp_set_auth_cookie($user->ID, false, is_ssl());
                        do_action('wp_login', $user->user_login, $user);
                        
                        wp_set_current_user($user->ID);
                        
                        // safe direct to same domain
                        wp_safe_redirect(secsign_id_login_post_url());
                    }
                }
            }
            else
            {
                add_error("No wordpress user exists for the username '" . $_POST['wp-username'] . "'.");
                global $secsignid_login_no_wp_mapping;
                $secsignid_login_no_wp_mapping = true;
                return;
                
            }
        }
        else if(isset($_POST['requestid']) && isset($_POST['authsessionid']))
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
                                
                                // set auth cookie
                                wp_set_auth_cookie($user->ID, false, is_ssl()); // http://codex.wordpress.org/Function_Reference/wp_set_auth_cookie
                                do_action('wp_login', $user->user_login, $user);
                                
                                wp_set_current_user($user->ID);
                                
                                // safe direct to same domain
                                wp_safe_redirect(secsign_id_login_post_url());
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
                        // no wordpress user exists in database for secsign id
                        //add_error("No wordpress user exists in database for secsign id '" . $_POST['secsignid'] . "'.");
                        global $secsignid_login_no_wp_mapping;
                        $secsignid_login_no_wp_mapping = true;
                    }
                }
            }
        }
    }
    
    
    
    if(! (function_exists('secsign_id_login_post_url')))
    {
        /**
         * build an url which is used for all html forms to post data to.
         */
        function secsign_id_login_post_url()
        {
            if (strncmp(get_site_url(), "https", 5)== 0) $prot = "https";
            else $prot = "http";
            
            $post_url = $_SERVER['REQUEST_URI'];
            if (strcmp($post_url,"")==0) $post_url = "/";
            return $prot . "://" . $_SERVER['SERVER_NAME'] . $post_url;
        }
    }
    
    if(! (function_exists('get_secsignid_server_instance')))
    {
        /**
         * creates an instance of SecSignIDApi and returns it.
         */
        function get_secsignid_server_instance()
        {
            $secSignIDServer = new SecSignIDApi();
            
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
            
            echo "<form action='" . $form_post_url . "' method='post'>" . PHP_EOL;
            echo "  SecSign ID:<br>" . PHP_EOL;
            echo "  <input id='secsignid' name='secsignid' type='text' size='30' maxlength='30' style='margin-top:5px;'/>" . PHP_EOL;
            echo "  <button type ='submit' name='login' value='1' style='width:70px'>Login</button> <span style='font-size:80%;position:relative;left:40px;'><a href='https://www.secsign.com' target='_blank'>New to SecSign?</a></span>" . PHP_EOL;
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
            
            echo "<form action='" . $form_post_url . "' method='post'>" . PHP_EOL;
            echo "  There is no user assigned to your SecSign ID on \"" . get_bloginfo('name') . "\".<br><br>";//<b style='font-size:110%;'>Create a new account:</b><br>" . PHP_EOL; // on \"".get_bloginfo('name')."\"
            echo "  <table>" . PHP_EOL;
            echo "  <tr><td>Username:&nbsp;</td><td><input id='wp-username' name='wp-username' value='" . $_POST['secsignid'] . "'type='text' size='15' maxlength='30' /></td></tr>" . PHP_EOL;
            //echo "  <tr><td>Password:</td><td><input id='wp-password' name='wp-password' type='password' size='15' maxlength='30' /></td></tr>" . PHP_EOL;
            //echo "  <tr><td>E-Mail:</td><td><input id='wp-email' name='wp-email' type='text' size='15' maxlength='30' /></td></tr></table>" . PHP_EOL;
            echo "</table>";
            echo "<input type='hidden' name='secsignid' value='" . $_POST['secsignid'] . "' />" . PHP_EOL;
            echo "  <center><button style='margin-top:10px;font-size:110%;' type ='submit' name='newaccount' value='1'>Create new account</button></center>" . PHP_EOL;
            echo "</form>";
            echo "<form action='" . $form_post_url . "' method='post'>" . PHP_EOL;
            echo "  <br><center style='font-size:150%;'>--- or ---</center><br>";//Assign your SecSign ID to an existing account:</b><br>" . PHP_EOL;
            echo "  <table><tr><td>Username:&nbsp;</td><td><input id='wp-username' name='wp-username' type='text' size='15' maxlength='30' /></td></tr>" . PHP_EOL;
            echo "  <tr><td>Password:</td><td><input id='wp-password' name='wp-password' type='password' size='15' maxlength='30' /></td></tr></table>" . PHP_EOL;
            echo "<input type='hidden' name='secsignid' value='" . $_POST['secsignid'] . "' />" . PHP_EOL;
            echo "  <center><button style='margin-top:10px;font-size:110%;' type ='submit' name='existingaccount' value='1'>Assign to existing account</button></center> <br />" . PHP_EOL;
            echo "</form>";
        }
    }
    
    
    if(! (function_exists('print_check_accesspass')))
    {
        /**
         * prints out the access pass and the check form
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
            echo "<form action='" . $form_post_url . "' method='post'>". PHP_EOL;
            
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
            echo "<table>" . PHP_EOL;
            echo "  <tr>" . PHP_EOL;
            echo "      <td colspan='2' style='text-align:center;'>" . PHP_EOL;
            echo "          <b>Access Pass for " . $authsession->getSecSignID() . "</b>" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;
            echo "  <tr>" . PHP_EOL;
            echo "      <td colspan='2'>" . PHP_EOL;
            echo "<div style='display:block;background:url(./wp-content/plugins/secsign/accesspass_bg.png) transparent no-repeat scroll left top;background-size: 155px 206px;width: 155px;height: 206px;vertical-align: middle;margin:auto;margin-left:45px;'>" . PHP_EOL;
            echo "<img style='position:relative;width:80px;height:80px;left:35px;top:90px;box-shadow:0px 0px 0px #FFF;' src=\"data:image/png;base64," . $authsession->getIconData() . "\">" . PHP_EOL;
            echo "</div><br /><br />" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;
            
            echo "  <tr>" . PHP_EOL;
            echo "      <td colspan='2'>" . PHP_EOL;
            echo "          Please verify the access pass using your smartphone.<br /><br />" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;
            
            echo "  <tr>" . PHP_EOL;
            echo "      <td align='left'>" . PHP_EOL;
            
            // cancel button
            echo "          <button type ='submit' name='" . $cancel_auth_button . "' value='1' style='width:100px'>Cancel</button>" . PHP_EOL;
            echo "      </td>" . PHP_EOL;
            echo "      <td align='right'>" . PHP_EOL;
            
            // ok button which will trigger auth status check
            echo "          <button type ='submit' name='" . $check_auth_button . "' value='1' style='width:100px'>OK</button>" . PHP_EOL;
            
            echo "      </td>" . PHP_EOL;
            echo "  </tr>" . PHP_EOL;
            echo "</table>" . PHP_EOL;
            
            // end of form
            echo "</form>". PHP_EOL;
        }
    }
    
    
    
    if(! function_exists('add_error'))
    {
        /**
         * check if the global variable error is set and is an instance of WP_Error.
         * If not the function creates a new WP_Error instance and assignes it to global variabel $error.
         After that the given error message is added to WP_Error instance.
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
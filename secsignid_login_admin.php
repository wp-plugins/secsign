<?php

    // for all hooks, see http://adambrown.info/p/wp_hooks
    
    add_action('admin_init', 'secsignid_login_options_init' );
    add_action('admin_menu', 'secsignid_login_options_add_page' );
    

    add_action('delete_user',                'delete_user_secsignid_mapping'); // is called when a user is deleted

    add_action('show_user_profile',          'add_secsignid_login_fields'); // is called if logged in user opens his own profile...
    add_action('edit_user_profile',          'add_secsignid_login_fields'); // is called when admin edits a user profile...

    add_action('user_profile_update_errors', 'check_secsignid_login_fields'); // called before a user is updated.  when creating a new user this hook action is called too. http://adambrown.info/p/wp_hooks/hook/profile_update
    
    add_action('profile_update',             'save_secsignid_login_fields'); // is called whenever a profile is updated.
    

    add_filter('pre_update_option_secsignid_user_mapping', 'save_all_secsignid_user_mappings'); // is called before secsign id login options will be saved
    
    
    global $secsignid_login_text_domain;
    global $secsignid_login_plugin_name;
    global $secsignid_login_options; // global options object
    
    
    // define options
    $secsignid_login_options = (
                                  array(
                                        // a section per array
                                        array(
                                              __('Service Address', $secsignid_login_text_domain), 
                                              array(
                                                    array(
                                                          'name'    => 'secsignid_service_name', 
                                                          'default' => home_url(), 
                                                          'label'   => __('Service address', $secsignid_login_text_domain),  
                                                          'desc'	=> __('The service address is displayed during authentication on the smartphone of the user.', $secsignid_login_text_domain)
                                                          )
                                                    )
                                              ),
                                        // next section
                                        array(
                                              __('Wordpress User - SecSign ID', $secsignid_login_text_domain), 
                                              array(
                                                    array(
                                                          'name'    => 'secsignid_user_mapping', 
                                                          'default' => '', 
                                                          'label'   => 'Assigned SecSign IDs',
                                                          'desc'	=> __('A list with all known WordPress users and their corresponding SecSign ID.', $secsignid_login_text_domain),
                                                          'type'    => 'database_table_users'
                                                          )
                                                    )
                                              )
                                        )

                                  );
    
    
    /**
     * get options and register settings
     */
    function secsignid_login_options_init()
    {
        global $secsignid_login_options;
        global $secsignid_login_plugin_name;
        
        // poll over sections and options
        foreach($secsignid_login_options as $section) 
        {
            foreach($section[1] as $option)
            {
                if(isset($option['default']))
                {   
                    add_option($option['name'], $option['default']);
                }
                register_setting($secsignid_login_plugin_name, $option['name']);
            }   
        }
    }

    

    /**
     * loads a menu page for options
     */
    function secsignid_login_options_add_page() 
    {
        global $secsignid_login_plugin_name;
        global $secsignid_login_text_domain;
        
        // http://codex.wordpress.org/Function_Reference/add_options_page
        add_options_page(__('SecSign ID Login', $secsignid_login_text_domain), 
                         __('SecSign ID Login', $secsignid_login_text_domain), 
                         'manage_options', 
                         $secsignid_login_plugin_name, 
                         'secsignid_login_options_page');
    }

    
    
    /**
     * create a webpage where options can be set
     * @see http://codex.wordpress.org/Creating_Options_Pages
     */
    function secsignid_login_options_page() 
    {
        global $secsignid_login_options;
        global $secsignid_login_plugin_name;
        global $secsignid_login_text_domain;
        
        if (! isset($_REQUEST['settings-updated'])){
            $_REQUEST['settings-updated'] = false;
        }
        
        // print html code
        echo "<div class='wrap'>" . PHP_EOL;
        
        screen_icon(); // echo a nice icon
        
        echo "<h2>" . __( 'SecSign ID Login Options', $secsignid_login_text_domain) . "</h2>" . PHP_EOL;
        echo "<form method='post' action='options.php'>" . PHP_EOL;
        
        // http://codex.wordpress.org/Function_Reference/settings_fields
        settings_fields($secsignid_login_plugin_name);
        
        // print options
        foreach($secsignid_login_options as $section)
        {
            if($section[0])
            {
                echo "<h3 class='title'>" . $section[0] . "</h3>" . PHP_EOL;
            }
            
            // print options per section
            echo "<table class='form-table'>" . PHP_EOL;
            foreach($section[1] as $option)
            {
                echo "<tr valign='top'><th scope='row'>" . $option['label'] . "</th><td>" . PHP_EOL;
                
                if('database_table_users' === $option['type'])
                {
                    if($option['desc']){
                        echo "<span class='description'>" . $option['desc'] . "</span><br /><br />" . PHP_EOL;
                    }
                    
                    $secsignid_mapping_table = get_secsignid_mapping_table();
                    echo $secsignid_mapping_table;
                }
                else
                {
                    $editablestring = ((isset($option['editable']) && $option['editable'] === false) ? "readonly" : "");
                    
                    // print input text field
                    echo "<input id='" . $option['name'] . "' class='regular-text' type='text' name='" . $option['name'] . "' value='" . get_option($option['name']) . "' " . $editablestring . "/>" . PHP_EOL;
                
                    if($option['desc']){
                        echo "<span class='description'>" . $option['desc'] . "</span>" . PHP_EOL;
                    }
                }
                echo "</td></tr>" . PHP_EOL;
            }
            echo "</table>" . PHP_EOL;
        }
        
?>


<script type="text/javascript">
function check_secsignid_mappings()
{
    // check for correct values and uniqueness
    var wp_ids = document.getElementById('wp_ids');
    if(wp_ids != null)
    {
        var wp_ids_array = wp_ids.value.split(",");
        var secsid_array = new Array();
        
        for(var i = 0; i <  wp_ids_array.length; i++)
        {
            var wp_id = wp_ids_array[i];

            var field = document.getElementById('secsignid_for_wp_user_' + wp_id);
            if(field != null)
            {
                var value = field.value;
                var span  = document.getElementById('wp_user_' + wp_id);
                
                // check value for forbidden characters
                if(value.length > 255){
                    alert("SecSign ID for wordpress user '" + span.innerHTML + "' has to many characters.");
                    return false;
                }
                var chk = /^[\w@_-]*$/.test(value);
                if(! chk)
                {
                    alert("SecSign ID for wordpress user '" + span.innerHTML + "' contains illegal characters.");
                    return false;
                }
                // check value for uniquens
                if(value !== '')
                {
                    if(secsid_array[value.toLowerCase()] === 1)
                    {
                        alert("SecSign ID has to be unique. Check value for wordpress user '" + span.innerHTML + "'.");
                        
                        return false;
                    } 
                    else 
                    {
                        secsid_array[value.toLowerCase()] = 1;
                    }
                }
            }
        }
    }
    return true;
}
</script>


<?php
        echo "<p class='submit'>" . PHP_EOL;
        echo "<input type='submit' id='submit' name='submit' class='button-primary' value='";
        _e('Save Changes'); // <- this will echo the translation therefor it must be called as a function and not echo "..." . _e('Save Changes') . "...."
        //echo "' onclick='return check_secsignid_mappings()'/>" . PHP_EOL;
        echo "'/>" . PHP_EOL;
        echo "</p>" . PHP_EOL;

        echo "</form>" . PHP_EOL;
        echo "</div>" . PHP_EOL;
    }
    
    
    
    if(! function_exists('get_secsignid_mapping_table'))
    {
        /**
         * checks if admin is logged in. after that all wordpress users are queried and their secsign id mappings.
         * both are printed to a html table which is returnd.
         */
        function get_secsignid_mapping_table()
        {
            global $secsignid_login_text_domain;
            
            global $current_user; // instance of type WP_User: http://codex.wordpress.org/Class_Reference/WP_User
            global $user_ID;
            
            get_currentuserinfo();
            
            // check if a user is logged in
            if($user_ID == 0 || $user_ID == '')
            {
                return "No user is logged in.";
            }
            
            // check if user has role of an admin
            if(! current_user_can('manage_options')) // http://codex.wordpress.org/Function_Reference/current_user_can
            { 
                // http://codex.wordpress.org/Roles_and_Capabilities
                // http://codex.wordpress.org/Roles_and_Capabilities#manage_options
                // only administrator and super administrator can change options...
                return "Logged in user is not allowed to manage options.";
            }
            
            $wp_userids_array = array();
            $wp_user_array = get_all_wp_users();
            $mapping_array = get_user_mappings();
            
            $user_table = "<table>". PHP_EOL . "<th><b>" .  __('Wordpress User', $secsignid_login_text_domain) . "</b></th><th><b>" .  __('SecSign ID', $secsignid_login_text_domain) . "</b></th>". PHP_EOL;
            foreach ($wp_user_array as $wpu)
            {
                // start table row and cols
                $user_table .= "<tr>". PHP_EOL;
                $user_table .= "<td><span id='wp_user_" . $wpu->ID . "'>" . $wpu->user_login . "</span></td>" . PHP_EOL . "<td>". PHP_EOL;
                
                // input field with name of secsign id
                $user_table .= "<input class='regular-text' type='text' id='secsignid_for_wp_user_" . $wpu->ID . "' name='secsignid_for_wp_user_" . $wpu->ID . "' value='";
                if($mapping_array[$wpu->ID])
                {
                    $user_table .= $mapping_array[$wpu->ID]['secsignid'];
                }
                $user_table .= "' />" . PHP_EOL;
                
                // end table row
                $user_table .= "</td></tr>". PHP_EOL;
                
                // save id in id array
                array_push($wp_userids_array, $wpu->ID);
            }
            $user_table .= "</table>". PHP_EOL;
            
            // add wp ids
            $user_table .= "<input type='hidden' id='wp_ids' value='" . implode(',', $wp_userids_array) . "' />" . PHP_EOL;
            
            return $user_table;
        }
    }
    
    
    
    if(! function_exists('save_all_secsignid_user_mappings'))
    {
        /**
         * Checks the Secsign ID Wordpress User mappings and insert the values into database.
         */
        function save_all_secsignid_user_mappings()
        {
            $wp_user_array = get_all_wp_users();
            $mapping_array = get_user_mappings();

            global $wp_settings_errors;
            
            // check that global $wp_settings_errors is an array
            if(!is_array($wp_settings_errors)){
                $error = $wp_settings_errors;
                $wp_settings_errors = array();
                
                array_push($wp_settings_errors, $error);
            }
            
            $test_array = array();
            foreach ($wp_user_array as $wpu)
            {
                // check whether there exists a post with the name equal to the word press user id
                $secsignid = $_POST['secsignid_for_wp_user_'.$wpu->ID];
                
                // check if secsign id contains illegal characters
                if(preg_match('/^[\w@_-]*$/', $secsignid) == 0)
                {
                    array_push($wp_settings_errors, 
                               array('code' => 0, 'type' => 'error', 'message' => __('SecSign ID for wordpress user ' . $wpu->user_login . ' contains illegal characters.')));
                    
                    return $wp_settings_errors;
                }
                
                if($secsignid != '')
                {
                    if($test_array[strtolower($secsignid)] === 1)
                    {
                        array_push($wp_settings_errors, 
                                   array('code' => 0, 'type' => 'error', 'message' => __('_SecSign ID has to be unique. Check value for wordpress user ' . $wpu->user_login . '.')));
                                          
                        return $wp_settings_errors;

                    }
                    else
                    {
                        $test_array[strtolower($secsignid)] = 1;
                    }
                }
            }
            
            // save mappings only if check has been done
            // in case of an error the method will be left by here
            foreach ($wp_user_array as $wpu)
            {
                // check whether there exists a post with the name equal to the word press user id
                $secsignid = $_POST['secsignid_for_wp_user_'.$wpu->ID];
                
                // everything okay with mappsing secsign id <-> wordpress user
                handle_mapping($secsignid, $wpu->ID, $wpu->user_login, $mapping_array);
            }
        }
    }
    
    
    if(! function_exists('add_secsignid_login_fields'))
    {
        /**
         * Adds an additional row to profile site to entry secsign id
         *
         * see http://codex.wordpress.org/Plugin_API/Action_Reference/show_user_profile
         */
        function add_secsignid_login_fields($user) // an instance of WP_User is given
        {
            $secsignid = "";
            if($user)
            {
                $secsignid = get_secsignid($user->ID);
            }
            $plugin_name_public = "SecSign ID";
            
            
            echo "<h3>" . $plugin_name_public . "</h3>" . PHP_EOL;
            
            echo "<table class='form-table'>" . PHP_EOL;
            echo "<tr valign='top'><th scope='row'>" . $plugin_name_public . "</th>"  . PHP_EOL;
            echo "<td>" . PHP_EOL;
            
            echo "<input type='text' class='regular-text' id='secsign_id' name='secsign_id' value='" . $secsignid . "' />" . PHP_EOL;
            
            // check errors
            global $error;
            if($error && is_wp_error($error))
            {
                $errmsg = $error->get_error_message('profile_error_secsignid') . " " .  $error->get_error_data('profile_error_secsignid');
                if($errmsg)
                {
                    // only show once
                    unset($error);    
                    echo "<span id='error'>" . $errmsg . "</span>";
                }
            } 
         
            echo "</td>" . PHP_EOL;
            echo "</tr>" . PHP_EOL;
            echo "</table>" . PHP_EOL;
        }
    }
    
      
    
    if(! function_exists('check_secsignid_login_fields'))
    {
        /**
         * check if chosen secsign is unique and does not contain illegal characters.
         * if an error is added to given $wp_error the update process will be stopped and the error message is shown at top of profile page.
         */
        function check_secsignid_login_fields($wp_error)
        {
            $wp_user_id_to_check  = $_POST['user_id'];
            $secsignid_to_check = $_POST['secsign_id'];
            
            if($secsignid_to_check)
            {
                // check if secsign id contains illegal characters
                if(preg_match('/^[\w@_-]*$/', $secsignid_to_check) == 0)
                {
                    $wp_error->add('profile_error_secsignid', __('Chosen SecSign ID contains illegal characters.'));
                    return $wp_error;
                }
                
                $mapping_array = get_user_mappings();
                foreach ($mapping_array as $mapping)
                {
                    // check whether there exists a post with the name equal to the word press user id
                    if(strtolower($mapping['secsignid']) === strtolower($secsignid_to_check))
                    {
                        if($_POST['action'] === 'createuser')
                        {
                            // a new user is created. the specified secsign id already exist
                            $wp_error->add('profile_error_secsignid', __('Chosen SecSign ID is already in use by another wordpress user.'));
                            return $wp_error;
                        }
                        else if($_POST['action'] === 'update')
                        {
                            // check wp_user_id which must be the same, otherwise the chosen secsign id is already assigned to another wordpress user
                            if($mapping['wp_user_id'] != $wp_user_id_to_check)
                            {
                                $wp_error->add('profile_error_secsignid', __('Chosen SecSign ID is already in use by another wordpress user'));
                                return $wp_error;
                            }
                        }
                    }
                }
            }
        }
    }


    
    if(! function_exists('save_secsignid_login_fields'))
    {
        /**
         * Save values from additional secsign id fields.
         */
        function save_secsignid_login_fields($user_id)
        {
            if( !current_user_can('edit_user', $user_id)){
                return false;
            }
            
            $mapping_array = get_user_mappings();
            
            $user = get_userdata($user_id);
            $secsignid_to_save = $_POST['secsign_id'];

            handle_mapping($secsignid_to_save, $user->ID, $user->user_login, $mapping_array);
        }
    }
 
    
     
    if(! function_exists('delete_user_secsignid_mapping'))
    {
        /**
         * Gets details for new user and save secsign id in seperate table.
         */
        function delete_user_secsignid_mapping($user_id)
        {
            if($user_id){
                delete_user_mapping($user_id);
            }
        }
    }
    
    
    
    if(! function_exists('handle_mapping'))
    {
        /**
         * the function check whether to delete, update or insert the given secsign id
         */
        function handle_mapping($secsignid, $wpu_id, $wpu_login, $mapping_array)
        {
            if($secsignid === '') // empty, null or whatever
            {
                // delete the entry in secsign id login database table
                
                // check if a mapping exist.
                if($mapping_array[$wpu_id])
                {
                    delete_user_mapping($wpu_id);
                }
            }
            else 
            {
                // update or insert new mapping
                // check if mapping already exist to decide whether to call update or insert
                if($mapping_array[$wpu_id])
                {
                    // check if mapping equals the new secsign id.
                    if($mapping_array[$wpu_id]['secsignid'] !== $secsignid){
                        update_user_mapping($wpu_id, $secsignid);
                    }
                }
                else 
                {
                    insert_user_mapping($wpu_id, $wpu_login, $secsignid);
                }
            }
        }
    }
    

?>

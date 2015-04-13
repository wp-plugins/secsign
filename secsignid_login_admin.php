<?php

// $Id: secsignid_login_admin.php,v 1.8 2015/04/13 13:00:04 titus Exp $

// for all hooks, see http://adambrown.info/p/wp_hooks

add_action('admin_init', 'secsignid_login_options_init');
add_action('admin_menu', 'secsignid_login_options_add_page');

add_action('delete_user', 'delete_user_secsignid_mapping'); // is called when a user is deleted

add_action('show_user_profile', 'add_secsignid_login_fields'); // is called if logged in user opens his own profile...
add_action('edit_user_profile', 'add_secsignid_login_fields'); // is called when admin edits a user profile...

add_action('user_profile_update_errors', 'check_secsignid_login_fields'); // called before a user is updated.  when creating a new user this hook action is called too. http://adambrown.info/p/wp_hooks/hook/profile_update

add_action('profile_update', 'save_secsignid_login_fields'); // is called whenever a profile is updated.

add_filter('pre_update_option_secsignid_user_mapping', 'save_all_secsignid_user_mappings'); // is called before secsign id login options will be saved

add_action('admin_notices', 'secsign_admin_notice');


global $secsignid_login_text_domain;
global $secsignid_login_plugin_name;
global $secsignid_login_options;


// define options.
// @see http://codex.wordpress.org/Administration_Menus
// @see http://codex.wordpress.org/Creating_Options_Pages
$secsignid_login_options = (
array(
    // a section per array
    array(
        __('General', $secsignid_login_text_domain), //title
        '',  //label
        array(
            array(
                'name' => 'secsignid_service_name',
                'default' => get_bloginfo('name'),
                'label' => __('Service Name', $secsignid_login_text_domain),
                'desc' => __('The name of this web site as it shall be displayed on the user\'s smart phone.', $secsignid_login_text_domain)
                //'editable' => false
            ),

            array(
                'name' => 'secsignid_frame',
                'default' => 'frame',
                'label' => __('Plugin Layout', $secsignid_login_text_domain),
                'desc' => __('The layout specifies the look of the SecSign ID plugin at the frontpage.', $secsignid_login_text_domain),
                'type' => 'select',
                'values' => array('frame', 'no-frame'),
                'value_descr' => array('SecSign standard box shadow & padding', 'no border or padding')
            )
        )
    ),
    // next section
    array(
        __('Two factor authentication for you and your website coworkers (Administrator, Editor, Author, Contributor)', $secsignid_login_text_domain),
        '',//label
        array(
            array(
                'name' => 'secsignid_show_on_login_page',
                'default' => 1,
                'label' => 'Show SecSign ID login form on <a href="' . wp_login_url() . '">wp-login.php</a> Page?',
                'desc' => __('Show SecSign ID login on the WordPress login page.', $secsignid_login_text_domain),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'secsignid_user_mapping',
                'default' => '',
                'label' => 'Assigned SecSign IDs to Wordpress Users',
                'desc' => __('It\'s recommended to deactivate the password based login for all coworker accounts, except for your admin account and everyone who has no smartphone. These accounts should be secured using a very strong password.', $secsignid_login_text_domain),
                'type' => 'database_table_users',
                'get_subscribers' => false
            )
        )
    ),
    // next section
    array(
        __('Two factor authentication for your website users (Subscriber)', $secsignid_login_text_domain),
        '',//label
        array(
            array(
                'name' => 'secsignid_user_mapping',
                'default' => '',
                'label' => 'Assigned SecSign IDs to Wordpress Users',
                'desc' => __('Your users can also assign a SecSign ID themselves in their profile.', $secsignid_login_text_domain),
                'type' => 'database_table_users',
                'get_subscribers' => true
            )
        )
    ),
    // next section
    array(
        __('Fast Registration', $secsignid_login_text_domain),
        'In order not to have to create new user accounts yourself you can allow your co-workers or web site users to create user accounts themselves by logging in with their SecSign ID via <a href="' . wp_login_url() . '">wp-login.php</a> or the login widget. You can allow them to create a new wordpress user or assign an existing one. After they created a wordpress account, you can assign wordpress roles to your co-workers via the user administration.',//label
        array(
            array(
                'name' => 'secsignid_allow_account_creation',
                'default' => 0,
                'label' => 'Allow SecSign ID users to create a new WordPress user when logging in?',
                'desc' => __('SecSign ID users who have no WordPress user assigned can create a new one after login.', $secsignid_login_text_domain),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'secsignid_allow_account_assignment',
                'default' => 1,
                'label' => 'Allow SecSign ID users to assign an existing WordPress user when logging in?',
                'desc' => __('SecSign ID users who have no WordPress user assigned can assign an existing WordPress account after login.', $secsignid_login_text_domain),
                'type' => 'checkbox'
            )
        )
    )
)

);

if (!(function_exists('secsignid_login_options_init'))) {
    /**
     * get options and register settings
     */
    function secsignid_login_options_init()
    {
        global $secsignid_login_options;
        global $secsignid_login_plugin_name;

        // poll over sections and options
        foreach ($secsignid_login_options as $section) {
            foreach ($section[2] as $option) {
                if (isset($option['default'])) {
                    add_option($option['name'], $option['default']);
                }

                register_setting($secsignid_login_plugin_name, $option['name']);
            }
        }
    }
}

if (!(function_exists('secsignid_login_options_add_page'))) {
    /**
     * loads a menu page for options
     */
    function secsignid_login_options_add_page()
    {
        global $secsignid_login_plugin_name;
        global $secsignid_login_text_domain;

        //add it to the options page
        add_options_page(__('SecSign ID Login', $secsignid_login_text_domain),
            __('SecSign ID Login', $secsignid_login_text_domain),
            'manage_options',
            $secsignid_login_plugin_name,
            'secsignid_login_options_page');
        //add it to the side menu
        add_menu_page(__('SecSign ID Login', $secsignid_login_text_domain),
            __('SecSign ID Login', $secsignid_login_text_domain),
            'manage_options',
            $secsignid_login_plugin_name,
            'secsignid_login_options_page',
            plugin_dir_url(__FILE__) . 'images/secsign_icon20.png');
    }
}

if (!(function_exists('secsignid_login_options_page'))) {
    /**
     * create a webpage where options can be set
     * @see http://codex.wordpress.org/Creating_Options_Pages
     */
    function secsignid_login_options_page()
    {
        global $secsignid_login_options;
        global $secsignid_login_plugin_name;
        global $secsignid_login_text_domain;

        if (!isset($_REQUEST['settings-updated'])) {
            $_REQUEST['settings-updated'] = false;
        }

        // print header
        echo "<div class='wrap'>" . PHP_EOL;
        echo "<h2>" . __('SecSign ID Login Options', $secsignid_login_text_domain) . "</h2>" . PHP_EOL;

        echo "<form method='post' action='options.php'>" . PHP_EOL;

        //print settings field
        settings_fields($secsignid_login_plugin_name);

        // print options
        for ($x = 0; $x < count($secsignid_login_options); $x++) {
            $section = $secsignid_login_options[$x];

            //print horizontal line
            if ($x > 0) echo "</br><hr></br>" . PHP_EOL;

            //print section title
            if ($section[0]) {
                echo "<h3 class='title' style='font-weight:bold;'>" . $section[0] . "</h3>" . PHP_EOL;
            }

            //print section label
            if ($section[1] != '') {
                echo "<label>" . $section[1] . "</label>" . PHP_EOL;
            }

            // print options per section
            echo "<table class='form-table'>" . PHP_EOL;
            foreach ($section[2] as $option) {
                echo "<tr valign='top'><th scope='row'>" . $option['label'] . "</th><td>" . PHP_EOL;

                if ('database_table_users' === $option['type']) {
                    $secsignid_mapping_table = get_secsignid_mapping_table($option['get_subscribers']);
                    echo $secsignid_mapping_table;

                    if ($option['desc']) {
                        echo "<br /><br /><span class='description'>" . $option['desc'] . "</span>" . PHP_EOL;
                    }
                } else if ('checkbox' === $option['type']) {
                    $checkbox_value = get_option($option['name']);

                    $html = '<input type="checkbox" id="' . $option['name'] . '" name="' . $option['name'] . '" value="1"' . checked(1, $checkbox_value, false) . '/>';

                    if ($option['desc']) {
                        $html .= '<label for="' . $option['name'] . '">' . $option['desc'] . '</label>';
                    }
                    echo $html;

                } else if ('select' === $option['type']) {
                    echo '<select id="' . $option['name'] . '" name="' . $option['name'] . '" size="1" style="width:25em">';

                    $values = $option['values'];
                    $value_descr = $option['value_descr'];
                    if ($value_descr == null) {
                        $value_descr = $values;
                    }
                    $curval = get_option($option['name']);
                    if (empty($curval)) {
                        $curval = $values[0];
                    }

                    //foreach($values as $v){
                    for ($kk = 0; $kk < count($values); $kk++) {

                        $v = $values[$kk];
                        $v_desc = $value_descr[$kk];

                        // check if description is empty. in that case just use the value
                        if (empty($v_desc)) {
                            $v_desc = $v;
                        }

                        $sel = ($v == $curval);
                        if ($sel) {
                            echo '<option selected value="' . $v . '">' . $v_desc . '</option>';
                        } else {
                            echo '<option value="' . $v . '">' . $v_desc . '</option>';
                        }
                    }

                    echo '</select>';
                } else //TextField
                {
                    $editablestring = ((isset($option['editable']) && $option['editable'] === false) ? "readonly" : "");

                    // print input text field
                    echo "<input id='" . $option['name'] . "' class='regular-text' type='text' name='" . $option['name'] . "' value='" . get_option($option['name']) . "' " . $editablestring . "/>" . PHP_EOL;

                    if ($option['desc']) {
                        echo "<br><span class='description'>" . $option['desc'] . "</span>" . PHP_EOL;
                    }
                }
                echo "</td></tr>" . PHP_EOL;
            }
            echo "</table>" . PHP_EOL;
        }

        ?>

        <script type="text/javascript">
            function check_secsignid_mappings() {
                // check for correct values and uniqueness
                var wp_ids1 = document.getElementById('wp_ids1');
                var wp_ids2 = document.getElementById('wp_ids2');
                if ((wp_ids1 != null) && (wp_ids2 != null)) {
                    var wp_ids_array = wp_ids1.value.split(",").concat(wp_ids2.value.split(","));
                    var secsid_array = new Array();

                    for (var i = 0; i < wp_ids_array.length; i++) {
                        var wp_id = wp_ids_array[i];

                        var field = document.getElementById('secsignid_for_wp_user_' + wp_id);
                        if (field != null) {
                            var value = field.value;
                            var span = document.getElementById('wp_user_' + wp_id);

                            // check value for forbidden characters
                            if (value.length > 255) {
                                alert("SecSign ID for wordpress user '" + span.innerHTML + "' has to many characters.");
                                return false;
                            }
                            var chk = /^[\w@_\-\.]*$/.test(value);
                            if (!chk) {
                                alert("SecSign ID for wordpress user '" + span.innerHTML + "' contains illegal characters.");
                                return false;
                            }
                            // check value for uniqueness
                            if (value !== '') {
                                if (secsid_array[value.toLowerCase()] === 1) {
                                    alert("SecSign ID has to be unique. Check value for wordpress user '" + span.innerHTML + "'.");

                                    return false;
                                }
                                else {
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

        //print submit button
        echo "<p class='submit'>" . PHP_EOL;
        echo "<input type='submit' id='submit' name='submit' class='button-primary' value='";
        _e('Save Changes'); // <- this will echo the translation therefor it must be called as a function and not echo
        echo "' onclick='return check_secsignid_mappings()'/>" . PHP_EOL;
        //echo "'/>" . PHP_EOL;
        echo "</p>" . PHP_EOL;

        echo "</form>" . PHP_EOL;
        echo "</div>" . PHP_EOL;
    }
}

if (!function_exists('get_secsignid_mapping_table')) {
    /**
     * checks if admin is logged in. after that all wordpress users are queried and their secsign id mappings.
     * both are printed to a html table which is returned.
     *
     * @param BOOL $get_subscribers Optional. if true, get only subscribers, else get the other roles
     * @return string the html table
     */
    function get_secsignid_mapping_table($get_subscribers = true)
    {
        global $secsignid_login_text_domain;

        global $current_user; // instance of type WP_User
        global $user_ID;

        get_currentuserinfo();

        // check if a user is logged in
        if ($user_ID == 0 || $user_ID == '') {
            return "No user is logged in.";
        }

        // check if user has role of an admin
        if (!current_user_can('manage_options')) {
            // only administrator and super administrator can change options...
            return "Logged in user is not allowed to manage options.";
        }

        //get wp users
        if ($get_subscribers)
            $wp_user_array = get_all_subscribers();
        else
            $wp_user_array = get_all_coworkers();

        //user ids used by the javascript validation
        $wp_userids_array = array();

        $mapping_array = get_user_mappings();

        $user_table = "<table>" . PHP_EOL . "<th><b>" . __('Wordpress User', $secsignid_login_text_domain) . "</b></th><th><b>" . __('SecSign ID', $secsignid_login_text_domain) . "</b></th><th style='width:250px'><b>" . __('Deactivate Password Login', $secsignid_login_text_domain) . "</b></th>" . PHP_EOL;
        foreach ($wp_user_array as $wpu) {
            // start table row and cols
            $user_table .= "<tr>" . PHP_EOL;
            $user_table .= "<td style='padding:0px'><span id='wp_user_" . $wpu->ID . "'>" . $wpu->user_login . "</span></td>" . PHP_EOL . "<td style='padding:0px'>" . PHP_EOL;

            // input field with name of secsign id
            $user_table .= "<input class='regular-text' type='text' id='secsignid_for_wp_user_" . $wpu->ID . "' name='secsignid_for_wp_user_" . $wpu->ID . "' value='";
            if ($mapping_array[$wpu->ID]) {
                $user_table .= $mapping_array[$wpu->ID]['secsignid'];
            }
            $user_table .= "' /></td>" . PHP_EOL;

            //checkbox
            if (isset($mapping_array[$wpu->ID])) {
                $checkbox_value = $mapping_array[$wpu->ID]['allow_password_login'];
            } else {
                $checkbox_value = 1; //allow password login per default
            }

            $user_table .= "<td style='padding:0px'><label for='allow_password_login_for_wp_user_" . $wpu->ID
                . "' id='label_for_wp_user_" . $wpu->ID . "'><input type='checkbox' id='allow_password_login_for_wp_user_" . $wpu->ID
                . "' name='allow_password_login_for_wp_user_" . $wpu->ID
                . "' value='1'" . checked(1, $checkbox_value, false) . " />" . PHP_EOL;
            $user_table .= 'Login by password still allowed</label></td>';

            // end table row
            $user_table .= "</tr>" . PHP_EOL;

            // save id in id array
            array_push($wp_userids_array, $wpu->ID);
        }
        $user_table .= "</table>" . PHP_EOL;

        // add wp ids
        $user_table .= "<input type='hidden' id='wp_ids" . (($get_subscribers) ? "1" : "2") . "' value='" . implode(',', $wp_userids_array) . "' />" . PHP_EOL;

        return $user_table;
    }
}

if (!function_exists('save_all_secsignid_user_mappings')) {
    /**
     * Checks the Secsign ID Wordpress User mappings and insert the values into database.
     */
    function save_all_secsignid_user_mappings()
    {
        $wp_user_array = get_all_wp_users();
        $mapping_array = get_user_mappings();

        global $wp_settings_errors;

        // check that global $wp_settings_errors is an array
        if (!is_array($wp_settings_errors)) {
            $error = $wp_settings_errors;
            $wp_settings_errors = array();

            array_push($wp_settings_errors, $error);
        }

        $test_array = array();
        foreach ($wp_user_array as $wpu) {
            // check whether there exists a post with the name equal to the word press user id
            $secsignid = $_POST['secsignid_for_wp_user_' . $wpu->ID];

            // check if secsign id contains illegal characters
            if (preg_match('/^[\w@_\-\.]*$/', $secsignid) == 0) {
                array_push($wp_settings_errors,
                    array('code' => 0, 'type' => 'error', 'message' => __('SecSign ID for wordpress user ' . $wpu->user_login . ' contains illegal characters.')));

                return $wp_settings_errors;
            }

            if ($secsignid != '') {
                if ($test_array[strtolower($secsignid)] === 1) {
                    array_push($wp_settings_errors,
                        array('code' => 0, 'type' => 'error', 'message' => __('SecSign ID has to be unique. Check value for wordpress user ' . $wpu->user_login . '.')));

                    return $wp_settings_errors;

                } else {
                    $test_array[strtolower($secsignid)] = 1;
                }
            }
        }

        // save mappings only if check has been done
        // in case of an error the method will be left by here
        foreach ($wp_user_array as $wpu) {
            // check whether there exists a post with the name equal to the word press user id
            $secsignid = $_POST['secsignid_for_wp_user_' . $wpu->ID];
            if (isset($_POST['allow_password_login_for_wp_user_' . $wpu->ID]))
                $allow_password = 1;
            else
                $allow_password = 0;

            // everything okay with mapping secsign id <-> wordpress user
            handle_mapping($secsignid, $wpu->ID, $wpu->user_login, $allow_password, $mapping_array);
        }
    }
}

if (!function_exists('add_secsignid_login_fields')) {
    /**
     * Adds an additional row to profile site to entry secsign id
     *
     * see http://codex.wordpress.org/Plugin_API/Action_Reference/show_user_profile
     * @param WP_User $user the current user
     */
    function add_secsignid_login_fields($user) // an instance of WP_User is given
    {
        $secsignid = "";
        if ($user) {
            $secsignid = get_secsignid($user->ID);
        }
        $plugin_name_public = "SecSign ID";

        echo "<h3>" . $plugin_name_public . "</h3>" . PHP_EOL;

        echo "<table class='form-table'>" . PHP_EOL;
        echo "<tr valign='top'><th scope='row'>" . $plugin_name_public . "</th>" . PHP_EOL;
        echo "<td>" . PHP_EOL;

        echo "<input type='text' class='regular-text' id='secsign_id' name='secsign_id' value='" . $secsignid . "' />" . PHP_EOL;

        $allow_password_login = get_allow_password_login($user->id);

        echo "<label for='allow_password_login'>";
        echo "<input type='checkbox' id='allow_password_login' name='allow_password_login' value='1'" . checked(1, $allow_password_login, false) . " />" . PHP_EOL;
        echo "Login by password still allowed</label>";

        // check errors
        global $error;
        if ($error && is_wp_error($error)) {
            $errmsg = $error->get_error_message('profile_error_secsignid') . " " . $error->get_error_data('profile_error_secsignid');
            if ($errmsg) {
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

if (!function_exists('check_secsignid_login_fields')) {
    /**
     * check if chosen secsign is unique and does not contain illegal characters.
     * if an error is added to given $wp_error the update process will be stopped and the error message is shown at top of profile page.
     *
     * @param WP_Error $wp_error the error object
     */
    function check_secsignid_login_fields($wp_error)
    {
        $wp_user_id_to_check = $_POST['user_id'];
        $secsignid_to_check = $_POST['secsign_id'];

        if ($secsignid_to_check) {
            // check if secsign id contains illegal characters
            if (preg_match('/^[\w@_\-\.]*$/', $secsignid_to_check) == 0) {
                $wp_error->add('profile_error_secsignid', __('Chosen SecSign ID contains illegal characters.'));
                return $wp_error;
            }

            $mapping_array = get_user_mappings();
            foreach ($mapping_array as $mapping) {
                // check whether there exists a post with the name equal to the word press user id
                if (strtolower($mapping['secsignid']) === strtolower($secsignid_to_check)) {
                    if ($_POST['action'] === 'createuser') {
                        // a new user is created. the specified secsign id already exist
                        $wp_error->add('profile_error_secsignid', __('Chosen SecSign ID is already in use by another wordpress user.'));
                        return $wp_error;
                    } else if ($_POST['action'] === 'update') {
                        // check wp_user_id which must be the same, otherwise the chosen secsign id is already assigned to another wordpress user
                        if ($mapping['wp_user_id'] != $wp_user_id_to_check) {
                            $wp_error->add('profile_error_secsignid', __('Chosen SecSign ID is already in use by another wordpress user'));
                            return $wp_error;
                        }
                    }
                }
            }
        }
    }
}

if (!function_exists('save_secsignid_login_fields')) {
    /**
     * Save values from additional secsign id fields.
     *
     * @param int $user_id the wp user id of the current user
     */
    function save_secsignid_login_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $mapping_array = get_user_mappings();

        $user = get_userdata($user_id);
        $secsignid_to_save = $_POST['secsign_id'];
        $allow_password_login = $_POST['allow_password_login'];

        handle_mapping($secsignid_to_save, $user->ID, $user->user_login, $allow_password_login, $mapping_array);
    }
}

if (!function_exists('delete_user_secsignid_mapping')) {
    /**
     * Gets details for new user and save secsign id in seperate table.
     *
     * @param int $user_id the wp user id of the current user‚
     */
    function delete_user_secsignid_mapping($user_id)
    {
        if ($user_id) {
            delete_user_mapping($user_id);
        }
    }
}

if (!function_exists('handle_mapping')) {
    /**
     * the function check whether to delete, update or insert the given secsign id
     *
     * @param string $secsignid the secsign id
     * @param int $user_id the wp user id
     * @param string $wpu_login the wordpress user name
     * @param BOOL $password_login_allowed whether or not the password based login is allowed
     * @param array the mapping array
     */
    function handle_mapping($secsignid, $wpu_id, $wpu_login, $password_login_allowed, $mapping_array)
    {
        if ($secsignid === '') // empty, null or whatever
        {
            // delete the entry in secsign id login database table

            // check if a mapping exist.
            if ($mapping_array[$wpu_id]) {
                delete_user_mapping($wpu_id);
            }
        } else {
            // update or insert new mapping
            // check if mapping already exist to decide whether to call update or insert
            if ($mapping_array[$wpu_id]) {
                // check if mapping equals the new secsign id.
                if (($mapping_array[$wpu_id]['secsignid'] !== $secsignid) || ($mapping_array[$wpu_id]['allow_password_login'] !== $password_login_allowed)) {
                    update_user_mapping($wpu_id, $secsignid, $password_login_allowed);
                }
            } else {
                insert_user_mapping($wpu_id, $wpu_login, $secsignid, $password_login_allowed);
            }
        }
    }
}

if (!(function_exists('secsign_admin_notice'))) {
    /**
     * Checks settings for interfering options and displays a warning.
     */
    function secsign_admin_notice()
    {
        $screen = get_current_screen();
        if ($screen->base == 'toplevel_page_secsign') {
            $error = get_mapping_error();
            if ($error == 1) {
                echo '<div class="error"><br>
        <strong>Warning:</strong><br>
           <p>You disabled the option "Show SecSign ID login on the WordPress login page." and also deactivated Password Login for one user.<br>
           This user is not able to log into the Wordpress admin panel anymore. For more Information visit <a href="https://www.secsign.com/wordpress-tutorial/#troubleshooting" target="_blank">secsign.com/wordpress-tutorial</a>.</p>
        <br></div>';
            } elseif ($error > 1) {
                echo '<div class="error"><br>
        <strong>Warning:</strong><br>
           <p>You disabled the option "Show SecSign ID login on the WordPress login page." and also deactivated Password Login for ' . $error . ' users.<br>
           These users are not able to log into the Wordpress admin panel anymore. For more Information visit <a href="https://www.secsign.com/wordpress-tutorial/#troubleshooting" target="_blank">secsign.com/wordpress-tutorial</a>.</p>
        <br></div>';
            }
        }
    }
}

?>

<?php

// $Id: secsignid_login_db.php,v 1.3 2014/06/11 15:31:39 jwollner Exp $
    
    add_action('plugins_loaded', 'check_database_table'); // wordpress calls this function whenever plugins are loaded
    
    if(! function_exists('check_database_table'))
    {
        /**
         * check if table for secsign ids exist in wordpress database.
         */
        function check_database_table() 
        {
            $table_name = get_database_table_name();
        
        
            global $wpdb; // http://codex.wordpress.org/Class_Reference/wpdb
            if($wpdb->get_var("SHOW TABLES LIKE '" . $table_name . "'") != $table_name)
            {
                // table does not exist.
                // create table
                create_database_table();
            }
            //check if all columns are correct
            check_database_columns();
        }
    }
    
    if(! function_exists('check_database_columns'))
    {
        /**
         * check if all columns in the table exist in wordpress database
         * and creates them if missing
         */
        function check_database_columns() 
        {
            $table_name = get_database_table_name();
        
            global $wpdb; // http://codex.wordpress.org/Class_Reference/wpdb
            $column_names = $wpdb->get_col("SHOW COLUMNS FROM " . $table_name);
            if (count($column_names) == 3)
            {
            	//missing last column that was added in version 1.0.5
            	$sql = "ALTER TABLE " . $table_name . " ADD COLUMN allow_password_login BOOL DEFAULT 1;";
            	$wpdb->query($sql);
            }
            else if(count($column_names) == 4)
            {
                //all good
            }
            else
            {
            	//should not happen
            }
        }
    }
    
    if(! function_exists('create_database_table'))
    {
        /**
         * creates the database table for the mapping wordpress user <-> secsign id
         */
        function create_database_table()
        {
            global $wpdb;
            $table_name = get_database_table_name();
 
            //$wpdb->show_errors();
            $sql = "CREATE TABLE " . $table_name . " (" .
                                  "wp_user_id  mediumint(9) NOT NULL, " .
                                  "wp_username text NOT NULL, " .
                                  "secsignid text NOT NULL, " .
                                  "allow_password_login BOOL DEFAULT 1, " .
                                  "UNIQUE KEY ID (wp_user_id)" .
                                  ");";
            $wpdb->query($sql);
        }
    }
    
    if(! function_exists('get_database_table_name'))
    {
        /**
         * gets the name of the table which contains the mapping of wordpress users and secsign ids
         *
         * @return string the table name of the secsign id table
         */
        function get_database_table_name()
        {
            global $wpdb;
            global $secsignid_login_plugin_name;
        
            $table_name = $wpdb->prefix . $secsignid_login_plugin_name;
        
            return $table_name;
        }
    }
    
    if(! function_exists('get_user_mappings'))
    {
        /**
         * gets all users from table 'wp_secsignid_login' and returns an array containing an associated arrays
         *
         * @return array Returns array with wordpress user ids as key and an array containing the secsignid, wp_username, wp_user_id and allow_passord_login as value
         */
        function get_user_mappings()
        {
            global $wpdb; // http://codex.wordpress.org/Class_Reference/wpdb
            $table_name = get_database_table_name();
        
            $result_set = array();

            $sql = "SELECT wp_user_id, wp_username, secsignid, allow_password_login FROM ".$table_name.";";

            $all_users = $wpdb->get_results($sql);
            if($all_users)
            {
                foreach($all_users as $user) 
                {
                    $result_set[$user->wp_user_id] = array('wp_user_id' => $user->wp_user_id, 'wp_username' => $user->wp_username, 'secsignid' => $user->secsignid, 'allow_password_login' => $user->allow_password_login);
                }
            }
            return $result_set;
        }
    }    
    
    if(! function_exists('get_all_wp_users'))
    {
        /**
         * gets all wordpress users
         *
         * @return array all WP_Users
         */
        function get_all_wp_users()
        {
            return get_users('fields=all_with_meta');;
        }
    }
    
    if(! function_exists('get_all_subscribers'))
    {
        /**
         * gets all subscribers
         *
         * @return array all subscribers as WP_Users
         */
        function get_all_subscribers()
        {   
            return get_users('role=subscriber&fields=all_with_meta');;
        }
    }
    
	if(! function_exists('get_all_coworkers'))
    {
    	/**
         * gets all coworkers (Admins, Editors, Authors, Contributors)
         *
         *  @return array all coworkers as WP_Users
         */
        function get_all_coworkers()
        {   
            return array_merge(get_users('role=administrator&fields=all_with_meta'),
            				   get_users('role=editor&fields=all_with_meta'),
            				   get_users('role=author&fields=all_with_meta'),
            				   get_users('role=contributor&fields=all_with_meta'));
        }
    }

    if(! function_exists('get_wp_user_id'))
    {
        /**
         * gets the user id of a wordpress user the given secsign id is bind to
         *
         * @param string $secsignid the secsignid to get the wordpress user id to
         *
         * @return NULL|int the user id or NULL, if no user is found
         */
        function get_wp_user_id($secsignid)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $sql = $wpdb->prepare("SELECT wp_user_id FROM " . $table_name . " WHERE secsignid = %s", $secsignid);
            $single_wp_user = $wpdb->get_row($sql); // will fetch just a single row
            
            if($single_wp_user)
            {
                return $single_wp_user->wp_user_id;
            }
            return NULL;
        }
    }
    
    if(! function_exists('get_wp_user'))
    {
        /**
         * gets the user id of a wordpress user the given secsign id is bind to
         *
         * @param string $secsignid the secsignid to get the wordpress user to
         *
         * @return NULL|WP_User the user or NULL, if no user is found
         */
        function get_wp_user($secsignid)
        {
            $wp_user_id = get_wp_user_id($secsignid);        
            if($wp_user_id)
            {
                return get_userdata($wp_user_id);
            }
            return NULL;
        }
    }
    
    if(! function_exists('get_secsignid'))
    {
        /**
         * gets the secsign id which is bind to the specified wordpress user id
         *
         * @param int $wp_user_id the user id to get the secsignid to
         *
         * @return NULL|string the secsignid or NULL, if no user is found
         */
        function get_secsignid($wp_user_id)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $sql = $wpdb->prepare("SELECT secsignid FROM " . $table_name . " WHERE wp_user_id = %d", $wp_user_id);
            $single_secsignid = $wpdb->get_row($sql); // will fetch just a single row
            
            if($single_secsignid)
            {
                return $single_secsignid->secsignid;
            }
            return NULL;
        }
    }
    
    if(! function_exists('get_allow_password_login'))
    {
        /**
         * returns if wordpress user is allowed to use the password login
         *
         * @param int $wp_user_id the user id
         *
         * @return BOOL true if password login is allowed, false otherwise
         */
        function get_allow_password_login($wp_user_id)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $sql = $wpdb->prepare("SELECT allow_password_login FROM " . $table_name . " WHERE wp_user_id = %d", $wp_user_id);
            $single_row = $wpdb->get_row($sql); // will fetch just a single row
            
            if($single_row)
            {
                return $single_row->allow_password_login;
            }
            return true; //default value
        }
    }
    
    if(! function_exists('delete_user_mapping'))
    {
        /**
         * deletes the secsign id which is bind to the specified worpress user id
         */
        function delete_user_mapping($wp_user_id)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $sql = $wpdb->prepare("DELETE FROM " . $table_name . " WHERE wp_user_id = %d", $wp_user_id);
            $wpdb->query($sql);
        }
    }
    
    if(! function_exists('insert_user_mapping'))
    {
        /**
         * inserts into database a pair of wp_user with its user id and the mapped secsign id
         */
        function insert_user_mapping($wp_user_id, $wp_user_name, $secsignid, $password_login_allowed)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $wpdb->insert($table_name,
                          array( 
                                'wp_user_id' => $wp_user_id, 
                                'wp_username' => $wp_user_name, 
                                'secsignid' => $secsignid,
                                'allow_password_login' => $password_login_allowed,
                                ), 
                          array('%d','%s','%s','%d')
                          );
        }
    }
    
    if(! function_exists('update_user_mapping'))
    {
        /**
         * update the user mapping wordpress user and secsign id
         */
        function update_user_mapping($wp_user_id, $secsignid, $password_login_allowed)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $wpdb->update($table_name,
                          array('secsignid' => $secsignid, 'allow_password_login' => $password_login_allowed), // update column secsignid, allow_password_login
                          array('wp_user_id' => $wp_user_id), // where wp_user_id equals $wp_user_id
                          array('%s', '%d') // format: string, int (bool)
                          );
        }
    }

?>

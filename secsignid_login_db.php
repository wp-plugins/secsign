<?php

// $Id: secsignid_login_db.php,v 1.2 2013-10-15 13:52:02 jwollner Exp $
// $Source: /cvsroot/SecCommerceDev/seccommerce/secsignerid/examples/wordpress/secsign/secsignid_login_db.php,v $
    
    add_action('plugins_loaded', 'check_database_table'); // wordpress calls this functionb whenever plugins are loaded
   
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
                                  "UNIQUE KEY ID (wp_user_id)" .
                                  ");";
            $wpdb->query($sql);
        }
    }

    
    
    if(! function_exists('get_database_table_name'))
    {
        /**
         * gets the name of the table which contains the mapping of wordpress users and secsign ids
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
         * gets all users from table 'wp_secsignid_login' and returns an array containing an assosiated arrays
         */
        function get_user_mappings()
        {
            global $wpdb; // http://codex.wordpress.org/Class_Reference/wpdb
            $table_name = get_database_table_name();
        
            $result_set = array();

            $sql = "SELECT wp_user_id, wp_username, secsignid FROM ".$table_name.";";

            $all_users = $wpdb->get_results($sql);
            if($all_users)
            {
                foreach($all_users as $user) 
                {
                    $result_set[$user->wp_user_id] = array('wp_user_id' => $user->wp_user_id, 'wp_username' => $user->wp_username, 'secsignid' => $user->secsignid);
                }
            }
            return $result_set;
        }
    }
    
    
    
    if(! function_exists('get_all_wp_users'))
    {
        /**
         * gets all wordpress users
         */
        function get_all_wp_users()
        {
            global $wpdb; // http://codex.wordpress.org/Class_Reference/wpdb
            $table_name = $wpdb->users;
            
            $result_set = array();

            $sort_by = $table_name . ".user_login";
            $sql = $wpdb->prepare("SELECT ID FROM " . $table_name . " ORDER BY %s ASC;", $sort_by);
            $all_wp_users = $wpdb->get_col($sql);
            
            if($all_wp_users)
            {
                foreach($all_wp_users as $wpu_id) 
                {
                    array_push($result_set, get_userdata($wpu_id));
                }
            }
            
            return $result_set;
        }
    }
    
    

    if(! function_exists('get_wp_user_id'))
    {
        /**
         * gets the user id of a wordpress user the given secsign id is bind to
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
        function insert_user_mapping($wp_user_id, $wp_user_name, $secsignid)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $wpdb->insert($table_name,
                          array( 
                                'wp_user_id' => $wp_user_id, 
                                'wp_username' => $wp_user_name, 
                                'secsignid' => $secsignid 
                                ), 
                          array('%d','%s','%s')
                          );
        }
    }
    
    
    
    if(! function_exists('update_user_mapping'))
    {
        /**
         * update the user mapping wordpress user and secsign id
         */
        function update_user_mapping($wp_user_id, $secsignid)
        {
            global $wpdb;
            $table_name = get_database_table_name();
            
            $wpdb->update($table_name,
                          array('secsignid' => $secsignid), // update column secsignid
                          array('wp_user_id' => $wp_user_id), // where wp_user_id equals $wp_user_id
                          array('%s') // whith value of type string
                          );
        }
    }

?>

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/* @author Royyana Muslim
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

/**
 * PDITT plugin.
 */
class auth_plugin_pditt extends auth_plugin_base {

    var $lasterror;

    /**
     * Constructor.
     */
    function auth_plugin_pditt() {
	$this->pluginname='PDITT';
        $this->authtype = 'pditt';
        $this->errormessage = '';
    }


    function user_login ($username, $password) {
	    global $CFG, $DB, $USER;

            //if (!$user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id))) {
            if (!$user = $DB->get_record('user', array('username'=>$username))) {
	           return false;
	    }



	    $k = explode('$', $password);
	    
	    //echo $username . '-->' . $password . '-->' . $user->password . "\n";

	    $cek_x = md5($username . $user->password . $k[1] . 'ROMBONGSOTO');
	    $cek_y = $k[0];

	    //echo 'cekx=' . $cek_x . '--->' . 'ceky=' . $cek_y . "\n";

	    if ($cek_x!=$cek_y){
	    	return false;
	    }

/*
	    if (!validate_internal_user_password($user, $password)) {
                    return false;
            }

 */
	    return true;

    }


    	function prevent_local_passwords() {
        	return false;
    	}
    
    	function is_internal() {
        	return true;
    	}

    	function can_change_password() {
        	return false;
    	}

	function edit_profile_url(){
		return null;
	}

	function can_edit_profile(){
		return true;
	}

	function change_password_url(){
		return null;
	}


    	function config_form($config, $err, $user_fields) {
        	include "config.html";
    	}

    	function process_config($config) {
        	return true;
    	}

    	function loginpage_hook(){
    	}

    	function logoutpage_hook(){
		global $USER;
		global $CFG;
		global $redirect;
		
		$redirect=$CFG->wwwroot . '/auth/pditt/logout.php';
    	}

}



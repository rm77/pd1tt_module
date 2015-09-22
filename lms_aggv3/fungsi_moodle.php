<?
include "moodle_def.php";
require (MOODLE_LOCATION . '/config.php');
@ini_set('display_errors', '1');
$CFG->debug = 6143; 
$CFG->debugdisplay = 1;


require_once( $CFG->libdir . '/moodlelib.php');
require_once( $CFG->libdir . '/adminlib.php');
require_once( $CFG->libdir . '/datalib.php');
require_once( $CFG->libdir . '/authlib.php');
require_once( $CFG->libdir . '/coursecatlib.php');
require_once( $CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');



$moodle_config = get_config('backup');
$moodle_admin = get_admin();


function find_course_pditt($idnumber){
    global $DB,  $CFG;
    $x = $DB->get_record('course', array('idnumber' => $idnumber), '*');
    if (!$x){
        return -1;
    } else {
        return array('id'=>$x->id,'idnumber'=> $x->idnumber ,'url'=> COURSE_URL . '?id=' . $x->id);
    }

}

function create_category_pditt($nama,$deskripsi=''){
    global $DB,  $CFG;
    $x = $DB->get_record('course_categories', array('name' => 'PDITT-' . $nama), '*');
    if (!$x){
        $data = new stdClass();
        $data->name='PDITT-' . $nama;
        $data->description=$deskripsi;
        $data->descriptionformat=0;
        $data->parent=0;
        $category = coursecat::create($data);
        return $category->id;
    } else {
        return $x->id;
    }
}

function create_course_pditt($category,$idnumber,$kodemk,$namamk,$summary,$startdate=0,$visible=0,$format='topics'){
    global $DB, $CFG;
    //$x = $DB->get_record('course', array('idnumber'=>$idnumber, 'shortname'=>$kodemk), '*');
    $x = $DB->get_record('course', array('idnumber'=>$idnumber), '*');
    if (!$x) {
        $data = new stdClass();
        $data->category=$category;
        $data->idnumber = $idnumber;
        $data->fullname=$namamk;
        $data->shortname = $kodemk;
        $data->summary = $summary;
        $data->summaryformat=0;
        $data->format=$format;
        $data->startdate = $startdate;
        $data->showgrades=1;
        $data->visible=$visible;
        $h=create_course($data);
        return $h->id; 
    } else {
        $data = new stdClass();
        $data->fullname=$namamk;
        $data->idnumber = $idnumber;
        $data->shortname = $kodemk;
        $data->summary = $summary;
        $data->id = $x->id;
        update_course($data);
        return $x->id;
    }

}


function delete_course_pditt($courseid){
    delete_course($courseid,$showfeedback=false);
    return true;
}

function create_user_pditt($username,$password,$name_desc,$email,$guid,$type){
    global $DB, $CFG;
    $record = array();
    $record['auth']='pditt';
    $record['firstname']=$name_desc;
    $record['lastname']=' [' . strtoupper($type). '-PDITT]';
    $record['alternatename']='PDITT-' .  $type .  ((strlen($guid)>0) ? '-' . $guid : '');
    $record['firstnamephonetic']='';
    $record['lastnamephonetic']='';
    $record['email']=$email;
    $record['confirmed']=1;
    $record['lang']='en';
    $record['maildisplay']=1;
    $record['mnethostid']=1;
    $record['timecreated']=time();
    $record['timemodified']=$record['timecreated'];
    $record['username']=$username;
    $record['password']=hash_internal_user_password($password);
    
    $cek = $DB->get_record('user',array('username'=>$username),'*');
    if (!$cek) {
        $userid = $DB->insert_record('user',$record);
        $user_detail = $DB->get_record('user',array('id'=>$userid),'*',MUST_EXIST);
        $x = $user_detail->password;
        return array('userid'=>$userid,'new'=>1,'d'=>$x);   
    } else {
        $x = $cek->password;
        return array('userid'=>$cek->id,'new'=>0,'d'=>$x);
    }
    

}

function cek_user_pditt($username,$password){
    global $DB;
    $cek = $DB->get_record('user',array('username'=>$username),'*');
    if (!$cek){
        return false;
    }
    return array('userid'=>$cek->id, 'd'=>$cek->password);
}

function create_mhs_if_not_exists($username,$password,$student_name,$email,$guid){
    return create_user_pditt($username,$password,$student_name,$email,$guid,'mhs');
}

function create_dosen_if_not_exists($username,$password,$student_name,$email,$guid){
    return create_user_pditt($username,$password,$student_name,$email,$guid,'dosen');
}


/*

type = student, teacher
*/

function enrol_user_pditt($type='teacher',$userid,$courseid,$timestart=0,$timeend=0,$status=null,$DEBUG=false) {
    global $DB;


    //$DEBUG=true;
    if ($DEBUG==true){
        print_r(array($type,$userid,$courseid));
    }   

    
    $hasil=array();

    if (!$plugin=enrol_get_plugin('manual')) {
        $hasil['result']=0;

        return $hasil;
    }




    $instances = enrol_get_instances($courseid, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                break;
            }
        }
        if ($instance->enrol !== 'manual') {
        $hasil['result']=0;
        return $hasil;
        }

        $role = $DB->get_record('role', array('shortname' => $type), '*', MUST_EXIST);

    $cc = $DB->get_record('course',array('id'=>$courseid),'*');
    $cc->visible=1;
    $cc->visibleold=1;
    $DB->update_record_raw('course', $cc);
    $plugin->enrol_user($instance, $userid, $role->id, $timestart, $timeend, $status);
    $hasil['result']=31;
    return $hasil;
}


function enrol_mhs_pditt($userid,$courseid){
    return enrol_user_pditt($type='student',$userid,$courseid);
}

function enrol_dosen_pditt($userid,$courseid){
    return enrol_user_pditt($type='editingteacher',$userid,$courseid);
}

function enrol_tutor_pditt($userid,$courseid){
    return enrol_user_pditt($type='teacher',$userid,$courseid);
}

function enrol_author_pditt($userid,$courseid){
    return enrol_user_pditt($type='coursecreator',$userid,$courseid);
}


/*

type = teacher, student

*/

function my_unenrol_user($type='teacher',$userid,$courseid) {
    global $DB;
    
    $hasil=array();

    if (!$plugin=enrol_get_plugin('manual')) {
        $hasil['result']=0;
        return $hasil;
    }

    $instances = enrol_get_instances($courseid, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                break;
            }
        }
        if ($instance->enrol !== 'manual') {
        $hasil['result']=0;
        return $hasil;
        }

        $role = $DB->get_record('role', array('shortname' => $type), '*', MUST_EXIST);


    $plugin->unenrol_user($instance, $userid);

    $hasil['result']=32;
    return $hasil;

}


function un_enrol_mhs($userid,$courseid){
    return my_unenrol_user('student',$userid,$courseid);
}

function un_enrol_tutor($userid,$courseid){
    return my_unenrol_user('teacher', $userid, $courseid);
}

function un_enrol_dosen($userid,$courseid){
    return my_unenrol_user('editingteacher', $userid, $courseid);
}

function un_enrol_author($userid,$courseid){
    return my_unenrol_user('coursecreator', $userid, $courseid);
}


function enter($userid,$password){
    global $CFG,$USER;
    $authsequence = get_enabled_auth_plugins(true);
    foreach($authsequence as $authname){
        $authplugin = get_auth_plugin($authname);
        $authplugin->loginpage_hook();
    }

    $frm=new stdClass();
    $frm->username = $userid;
    $frm->password = $password;
    $user = authenticate_user_login($frm->username,$frm->password);
    if ($user){
        complete_user_login($user);
        set_moodle_cookie('');
    }
    return $user;
    
}



 

function get_version(){
    global $CFG;
    $namafile = $CFG->dirroot . '/version.php';
    if (file_exists($namafile)) {
        include($namafile);
        return ($branch)+0;
    } else {
        return -1;
    }
}


function backup_and_restore($courseid_from,$courseid_to,$admin) {
        global $USER, $CFG;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // Do backup with default settings. MODE_IMPORT means it will just
        // create the directory and not zip it.
        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid_from,
                backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
                $admin->id);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Do restore to new course with default settings.
        //$newcourseid = restore_dbops::create_new_course(
        //        $course_fullname, $course_shortname, $course_category);
        //directly to particular course id
        restore_dbops::delete_course_content($courseid_to);

        $rc = new restore_controller($backupid, $courseid_to,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $admin->id,1);

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return 0;
    }


 function backup_template($courseid_from,$settings,$config,$admin) {
    $bc = new backup_controller(backup::TYPE_1COURSE, $courseid_from, backup::FORMAT_MOODLE,
                                backup::INTERACTIVE_YES, backup::MODE_IMPORT,$admin->id);
    $backupid = $bc->get_backupid();
    $bc->get_plan()->get_setting('users')->set_status(backup_setting::LOCKED_BY_CONFIG);
    
    foreach ($settings as $setting => $configsetting) {
        if ($bc->get_plan()->setting_exists($setting)) {
            $bc->get_plan()->get_setting($setting)->set_value($config->{$configsetting});
        }
    }
 
    $bc->finish_ui();
    $bc->execute_plan();
    $bc->destroy();
    unset($bc);
 
    return $backupid;
}
 
 
function restore_to_course($courseid, $backupid, $restoretarget, $admin) {
    global $CFG; 

    // Check whether the backup directory still exists. If missing, something
    // went really wrong in backup, throw error. Note that backup::MODE_IMPORT
    // backups don't store resulting files ever
    $tempdestination = $CFG->tempdir . '/backup/' . $backupid;
    if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
        print_error('unknownbackupexporterror'); // shouldn't happen ever
    }
 
    $rc = new restore_controller($backupid, $courseid, backup::INTERACTIVE_YES, 
                                backup::MODE_IMPORT,$admin->id,1);
 
    // Convert the backup if required.... it should NEVER happed
    if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
        $rc->convert();
    }
    // Mark the UI finished.
    $rc->finish_ui();
    // Execute prechecks
    $rc->execute_precheck();
    
    if ($restoretarget == backup::TARGET_CURRENT_DELETING || $restoretarget == backup::TARGET_EXISTING_DELETING) {
        restore_dbops::delete_course_content($courseid);
    }
    // Execute the restore.
    $rc->execute_plan();
    $rc->destroy();
    unset($rc);
    
    // Delete the temp directory now
    fulldelete($tempdestination);
}
 

?>

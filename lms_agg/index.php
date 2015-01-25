<?

//a
require('../config.php');
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
 

 
//$course_import_from = 7;
//$course_restore_to = 11;
//$restoretarget=1;




$config = get_config('backup');
$admin = get_admin();
 
function get_auth_code(){
	if (!file_exists('SITE_CODE')){
		return 'ROMBONGSOTO';
	}
	return file_get_contents('SITE_CODE');	
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
 
 
 
//$backupid = backup_template($course_import_from,$settings,$config,$admin);
//restore_to_course($course_restore_to, $backupid, $restoretarget,$admin);

function curPageURL() {
 $pageURL = 'http';
 $pageURL .= (isset($_SERVER["HTTPS"]))? (($_SERVER["HTTPS"] == "on") ? "s" :'') : '';
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}

if (file_exists('site_config.php')) {
	include ('site_config.php');
} else {
	$GLOBAL = array();
	$GLOBAL['login_url']=$CFG->wwwroot . '/login/index.php';
}

$GLOBAL['course_url']=$CFG->wwwroot . '/course/view.php';	
$GLOBAL['course_mgt']=curPageURL();

function get_secure_info($encrypted){
	$e = convert_uudecode($encrypted);
	$key=get_auth_code();
	$iv='56781234';
 	$cipher = mcrypt_module_open(MCRYPT_BLOWFISH,'','cbc','');
	mcrypt_generic_init($cipher, $key, $iv);
	$decrypted = base64_decode(mdecrypt_generic($cipher,$e));
	mcrypt_generic_deinit($cipher);
	$hasil = json_decode($decrypted,true);
	return $hasil;
}

function create_category_if_not_exists($nama,$deskripsi=''){
	global $DB,  $CFG;

	$x = $DB->get_record('course_categories', array('name' => 'PDITT-' . $nama), '*');
	if (!$x){
		$data = new stdClass();
		$data->name='PDITT-' . $nama;
		//$data->idnumber='99';
		$data->description=$deskripsi;
		$data->descriptionformat=0;
		$data->parent=0;
		$category = coursecat::create($data);
		return $category->id;
	} else {
		return $x->id;
	}
}

function create_course_pditt($category,$univ,$kodemk,$namamk,$summary,$startdate=0,$visible=0,$format='topics'){
	global $DB, $CFG;


	$x = $DB->get_record('course', array('shortname' => $univ . '-' . $kodemk), '*');
	if (!$x) {
		$data = new stdClass();
		$data->category=$category;
		$data->fullname=$kodemk . '-' . $namamk;
		$data->shortname = $univ . '-' . $kodemk;
		$data->summary = $summary;
		$data->summaryformat=0;
		$data->format=$format;
		$data->startdate = $startdate;
		$data->showgrades=1;
		$data->visible=$visible;
		$h=create_course($data);
		return $h->id; 
	} else {
		return $x->id;
	}

}


function create_user_if_not_exists($username,$password,$name_desc,$email,$guid,$type){
	global $DB, $CFG;


	$record = array();

	$record['auth']='pditt';
	$record['firstname']=$name_desc;
	$record['lastname']=' [' . strtoupper($type). '-PDITT]';
	$record['alternatename']='PDITT-' .  $type .  '-' . $guid;
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


function cek_user($username,$password){
	global $DB;
	$cek = $DB->get_record('user',array('username'=>$username),'*');
	if (!$cek){
		return false;
	}
	return array('userid'=>$cek->id, 'd'=>$cek->password);
}




function create_mhs_if_not_exists($username,$password,$student_name,$email,$guid){
	return create_user_if_not_exists($username,$password,$student_name,$email,$guid,'mhs');
}

function create_dosen_if_not_exists($username,$password,$student_name,$email,$guid){
	return create_user_if_not_exists($username,$password,$student_name,$email,$guid,'dosen');
}


/*

type = student, teacher
*/

function enrol_user_if_not_exists($type='teacher',$userid,$courseid,$timestart=0,$timeend=0,$status=null,$DEBUG=false) {
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

//	$the_role = $DB->get_record('role', array('shortname'=>$type));
//	$instance = $DB->get_record('enrol', array('courseid'=>$courseid, 'enrol'=>'manual'), '*', MUST_EXIST);
//	$manual = enrol_get_plugin('manual');
//    $manual-> enrol_user($instance, $userid, $the_role->id);
	
	$hasil['result']=31;
	return $hasil;
}


function enrol_mhs_if_not_exists($userid,$courseid){
	return enrol_user_if_not_exists($type='student',$userid,$courseid);
}

function enrol_dosen_if_not_exists($userid,$courseid){
	return enrol_user_if_not_exists($type='editingteacher',$userid,$courseid);
}

function enrol_tutor_if_not_exists($userid,$courseid){
	return enrol_user_if_not_exists($type='teacher',$userid,$courseid);
}

function enrol_author_if_not_exists($userid,$courseid){
	return enrol_user_if_not_exists($type='coursecreator',$userid,$courseid);
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

	//print_r($CFG);

	$authsequence = get_enabled_auth_plugins(true);
	foreach($authsequence as $authname){
		$authplugin = get_auth_plugin($authname);
		$authplugin->loginpage_hook();
	}

	//print_r($authsequence);

	/*
	$authplugin  = get_auth_plugin('pditt');
	$authplugin->loginpage_hook();
	 */

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

/* controller */

$cmd = isset($_GET['c']) ? $_GET['c'] : (isset($_POST['c']) ? $_POST['c'] : 'index');
//$cmd = isset($_POST['c']) ? $_POST['c'] : 'index';
$auth = isset($_GET['a']) ? $_GET['a'] : 'xx';




if (function_exists($cmd)){
	echo json_encode($cmd($post=$_POST,$get=$_GET));
} else {
	echo json_encode(array('result'=>0,'msg'=>'no command specified'));
}



/*  MULAI WEB SERVICE */

function index(){
	$str_version = (get_version() >= 26) ? get_version() : 'Version Improper';
	$hasil = array('result'=>11,'version'=>'1.1','cr'=>'RM','moodle_version'=> $str_version);
	return $hasil;
}




function pditt_functions($post,$get){
	global $CFG;
	$B = get_defined_functions();
	$H=array();
	foreach($B['user'] as $y){
		if (substr($y,0,6)=='pditt_')
			$H[]=$y;
	}
	$hasil = array('result'=>12,'cfg'=>array(),'functions'=>$H);
	return $hasil;
}

function pditt_create_dosen_g($post,$get){
	global $GLOBAL;

	$username = $get['username'];
	$password = $get['password'];

	return create_user_if_not_exists($username,$password,$username,$username . '@local','123123$$','dosen');

}


function pditt_enter_g($post,$get){
	global $GLOBAL;
	$username = $get['username'];
	$password = $get['password'];
	
	$hasil = enter($username,$password);

	if ($hasil->id==0){
		return array('result'=>0,'msg'=>'Login Gagal');
	} else {
		return array('result'=>51,'user'=>$hasil);
	}

}

function pditt_enter_page($post,$get){
	global $GLOBAL;

	if (!isset($get['e'])) {
		echo "Gagal";
		exit(0);
	}

	$hasil = get_secure_info($get['e']);
	$username = $hasil['username'];
	$password = $hasil['password'];
	$url=$get['cd'];
	
	$hasil = enter($username,$password);

	$d = isset($hasil) ? $hasil->id : 0;

	if ($d==0){
?>
		<script type="text/javascript">
                var y = '<div id=xdxd style="position:absolute;left:0px;top:0px;width:100%;height:100%;background:red;">';
                y=y+'<div id=gg style="margin:0px;font-family:arial;color:white;">';
                y=y+'<strong>Telah Terjadi Kegagalan Dalam Menyambungkan Diri</strong>&nbsp;<br>Silahkan dicoba kembali<br>';
                y=y+'<button type="button" onclick="window.close()" class="btn btn-primary">Tutup</button>';
                y=y+'</div>';
                y=y+'<iframe id=mainwind src="" style="width:100%;height:100%;margin:0px;"></iframe>';
                y=y+'</div>';
		document.write(y);
		</script>
<?
	} else {
?>
		<script type="text/javascript">
                var y = '<div id=xdxd style="position:absolute;left:0px;top:0px;width:100%;height:100%;background:#428bca;">';
                //y=y+'<div id=gg style="margin:0px;font-family:arial;color:white;">';
                //y=y+'<button type="button" onclick="window.close()" class="btn btn-primary">Tutup</button>&nbsp;&nbsp;';
                //y=y+'<strong>&nbsp;&nbsp;Anda sedang berada di lingkungan Learning Management Systems</strong>&nbsp;&nbsp;';
                //y=y+'</div>';
                y=y+'<iframe id=mainwind src="<?=$url?>" style="width:100%;height:100%;margin:0px;"></iframe>';
                y=y+'</div>';
		document.write(y);
		</script>
<?		
	}
	return array();
}

function pditt_enter($post,$get){
	global $GLOBAL;
	$hasil = get_secure_info($post['e']);

	$username = $hasil['username'];
	$password = $hasil['password'];
	
	$result = enter($username,$password);

	if ($result->id==0){
		return array('result'=>0,'msg'=>'Login Gagal');
	} else {
		return array('result'=>51,'user'=>$result);
	}

}

function pditt_create_dosen($post,$get){
	global $GLOBAL;
	$hasil = get_secure_info($post['e']);

	$username = $hasil['username'];
	$password = $hasil['password'];
	$email = $hasil['email'];
	$nama = $hasil['nama'];
	$guid = $hasil['guid'];

	return array('result'=>21, 'userdetail'=> create_dosen_if_not_exists($username,$password,$nama,$email,$guid));

}

function pditt_enrol_users($post,$get){
	global $GLOBAL;
	$hasil = get_secure_info($post['e']);
	$user = $hasil['userid'];
	$course = $hasil['courseid'];
	$type = $hasil['type'];

	return array('result'=>21, 'result'=>enrol_user_if_not_exists($type='student',$userid,$courseid));
}


function pditt_create_user_and_enrol($post,$get){
	global $GLOBAL;
	$hasil = get_secure_info($post['e']);
	$enrol_result = array();
	if (isset($hasil['type'])){
			foreach($hasil['type'] as $xx=>$tipe){
				//$tipe = $hasil['type'];
				if ($tipe=='mahasiswa'){
					$user = create_mhs_if_not_exists($hasil['username'],$hasil['password'],$hasil['nama'],$hasil['email'],$hasil['guid']);
					$userid=$user['userid'];
					$enrol_result[$tipe]=enrol_mhs_if_not_exists($userid,$hasil['courseid']);
				}

				if ($tipe=='dosen'){
					$user = create_dosen_if_not_exists($hasil['username'],$hasil['password'],$hasil['nama'],$hasil['email'],$hasil['guid']);
					$userid=$user['userid'];
					$enrol_result[$tipe]=enrol_dosen_if_not_exists($userid,$hasil['courseid']);
				}

				if ($tipe=='tutor'){
					$user = create_dosen_if_not_exists($hasil['username'],$hasil['password'],$hasil['nama'],$hasil['email'],$hasil['guid']);
					$userid=$user['userid'];
					$enrol_result[$tipe]=enrol_tutor_if_not_exists($userid,$hasil['courseid']);
				}

				if ($tipe=='author'){
					$user = create_dosen_if_not_exists($hasil['username'],$hasil['password'],$hasil['nama'],$hasil['email'],$hasil['guid']);
					$userid=$user['userid'];
					$enrol_result[$tipe]=enrol_author_if_not_exists($userid,$hasil['courseid']);
				}
			}


			$o=cek_user($hasil['username'],$hasil['password']);
			$p = $o['d'];
			$userid = $o['userid'];




			$hasil = array(	'result'=>21,
					'user'=>$user, 
					'go'=>$hasil['murl'],
					'enrol'=>$enrol_result ,
					'u'=>$hasil['username'], 
					'o'=>md5($hasil['username'] . $p . $hasil['qkey'] . get_auth_code()), 
					'qkey'=>$hasil['qkey'],
					'lurl'=>$GLOBAL['login_url']
					);
			return $hasil;
	} else {
		$hasil = array('result'=>0);
		return $hasil;
	}


}

function pditt_get_data($post,$get){
	global $GLOBAL;
	$hasil = get_secure_info($post['e']);
	$o=cek_user($hasil['username'],$hasil['password']);
	$p = $o['d'];
	$userid = $o['userid'];
	
	$hasil = array(	'result'=>61,
			'user'=>$hasil['username'], 
			'go'=>$hasil['murl'],
			'u'=>$hasil['username'], 
			'o'=>md5($hasil['username'] . $p . $hasil['qkey'] . get_auth_code()), 
			'qkey'=>$hasil['qkey'],
			'lurl'=>$GLOBAL['login_url'],
			'mgt_url'=>$GLOBAL['course_mgt']
		);
	return $hasil;
	
}

function pditt_create_course($post,$get){
	global $GLOBAL;
	$h = get_secure_info($post['e']);

	$username = $h['username'];
	$password = $h['password'];
	$univ = $h['univ'];
	$kodemk = $h['kodemk'];
	$namamk = $h['namamk'];
	$summary = $namamk;
	$namacategory = $h['category'];

	$o=cek_user($username,$password);
	if (!$o){
		return array('result'=>0, 'msg'=>'pditt_create_course cek user gagal');
	} else {
		$p = $o['d'];
		$userid = $o['userid'];
	}

	$cat = create_category_if_not_exists($namacategory,$namacategory . ' (PDITT)');
	$idcourse = create_course_pditt($cat,$univ,$kodemk,$namamk,$summary);
	enrol_author_if_not_exists($userid,$idcourse);
	enrol_dosen_if_not_exists($userid,$idcourse);

	$hasil = array (
				'result'=> 21,
				'idcourse'=>$idcourse,
				'go'=> $GLOBAL['course_url'] . '?id=' . $idcourse,
				'u'=>$username,
				'o'=>md5($username . $p . $h['qkey'] . get_auth_code()),
				'qkey'=>$h['qkey'],
				'lurl'=>$GLOBAL['login_url']
			);

	return $hasil;
	
}

function pditt_create_course_dan_enrol_dosen($post,$get){
	global $GLOBAL;
	$h = get_secure_info($post['e']);

	$username = $h['username'];
	$password = $h['password'];

	
	$o=cek_user($username,$password);
	if (!$o){
		return array('result'=>0, 'msg'=>'pditt_create_course_dan_dosen cek user gagal');
	} else {
		$p = $o['d'];
		$userid = $o['userid'];
	}

	$univ = $h['univ'];
	$kodemk = $h['kodemk'];
	$namamk = $h['namamk'];
	$summary = $namamk;
	$namacategory = $h['category'];

	$cat = create_category_if_not_exists($namacategory,$namacategory . ' (PDITT)');
	$idcourse = create_course_pditt($cat,$univ,$kodemk,$namamk,$summary);
	enrol_author_if_not_exists($userid,$idcourse);
	enrol_dosen_if_not_exists($userid,$idcourse);

	$hasil = array (
				'result'=> 21,
				'courseid'=>$idcourse,
				'userid'=>$userid,
				'courseurl'=>$GLOBAL['course_url'] . '?id=' . $idcourse,
				'coursemgt'=>$GLOBAL['course_mgt'],
				'lurl'=>$GLOBAL['login_url']
			);

	return $hasil;
}



function pditt_create_course_only($post,$get){
	global $GLOBAL;
	$h = get_secure_info($post['e']);

	$univ = $h['univ'];
	$kodemk = $h['kodemk'];
	$namamk = $h['namamk'];
	$summary = $namamk;
	$namacategory = $h['category'];

	$cat = create_category_if_not_exists($namacategory,$namacategory . ' (PDITT)');
	$idcourse = create_course_pditt($cat,$univ,$kodemk,$namamk,$summary);

	$hasil = array (
				'result'=> 21,
				'courseid'=>$idcourse,
				'courseurl'=>$GLOBAL['course_url'] . '?id=' . $idcourse,
				'coursemgt'=>$GLOBAL['course_mgt'],
				'lurl'=>$GLOBAL['login_url']
			);

	return $hasil;
}

function pditt_create_course_test($post,$get){
	global $GLOBAL;
	//$h = get_secure_info($post['e']);
	$h = $get;


	$univ = $h['univ'];
	$kodemk = $h['kodemk'];
	$namamk = $h['namamk'];
	$summary = $namamk;
	$namacategory = $h['category'];

	$cat = create_category_if_not_exists($namacategory,$namacategory . ' (PDITT)');
	$idcourse = create_course_pditt($cat,$univ,$kodemk,$namamk,$summary);

	$hasil = array (
				'result'=> 21,
				'courseid'=>$idcourse,
				'courseurl'=>$GLOBAL['course_url'] . '?id=' . $idcourse,
				'coursemgt'=>$GLOBAL['course_mgt'],
				'lurl'=>$GLOBAL['login_url']
			);

	return $hasil;
}


function pditt_create_course_import2($post,$get){
	global $admin;
	global $config;
	global $GLOBAL;
	$h = get_secure_info($post['e']);

	$settings = array(
		'activities' => 'backup_auto_activities',
		'blocks' => 'backup_auto_blocks',
		'filters' => 'backup_auto_filters',
		'questionbank'=>'backup_auto_questionbank',
		'badges'=>'backup_auto_badges',
		'comments'=>'backup_auto_comments'
	);

	$course_import_from = $h['course_import_from'];
	$course_restore_to = $h['course_restore_to'];

	backup_and_restore($course_import_from,$course_restore_to,$admin);


	//$restoretarget=1;
	//$backupid = backup_template($course_import_from,$settings,$config,$admin);
	//restore_to_course($course_restore_to, $backupid, $restoretarget,$admin);


	$hasil = array(
			'result'	=>	41,
			'lurl'		=>	$GLOBAL['login_url'],
			'courseurl'	=>	$GLOBAL['course_url'],
			'course_import_from'	=>	$course_import_from,
			'course_restore_to'	=>	$course_restore_to
	);
	return $hasil;
}

function pditt_create_course_import($post,$get){
	global $admin;
	global $config;
	global $GLOBAL;
	$h = get_secure_info($post['e']);

	$settings = array(
		'activities' => 'backup_auto_activities',
		'blocks' => 'backup_auto_blocks',
		'questionbank'=>'backup_auto_questionbank',
		'badges'=>'backup_auto_badges',
		'comments'=>'backup_auto_comments'
	);

	$course_import_from = $h['course_import_from'];
	$course_restore_to = $h['course_restore_to'];

	$restoretarget=1;
	$backupid = backup_template($course_import_from,$settings,$config,$admin);
	restore_to_course($course_restore_to, $backupid, $restoretarget,$admin);


	$hasil = array(
			'result'	=>	41,
			'lurl'		=>	$GLOBAL['login_url'],
			'courseurl'	=>	$GLOBAL['course_url'],
			'course_import_from'	=>	$course_import_from,
			'course_restore_to'	=>	$course_restore_to
	);
	return $hasil;
}



function pditt_coba_import2($post,$get){
	global $admin;
	global $config;
	global $GLOBAL;
	//$h = get_secure_info($post['e']);

	$from = $get['from'];
	$to = $get['to'];

	$settings = array(
		'activities' => 'backup_auto_activities',
		'blocks' => 'backup_auto_blocks',
		'filters' => 'backup_auto_filters',
		'questionbank'=>'backup_auto_questionbank',
		'badges'=>'backup_auto_badges',
		'comments'=>'backup_auto_comments'
	);

	$course_import_from = $from;
	$course_restore_to = $to;

	backup_and_restore($course_import_from,$course_restore_to,$admin);

	//$restoretarget=1;
	//$backupid = backup_template($course_import_from,$settings,$config,$admin);
	//restore_to_course($course_restore_to, $backupid, $restoretarget,$admin);


	$hasil = array(
			'result'	=>	41,
			'course_import_from'	=>	$course_import_from,
			'course_restore_to'	=>	$course_restore_to
	);
	return $hasil;
}

function pditt_coba_import($post,$get){
	global $admin;
	global $config;
	global $GLOBAL;
	//$h = get_secure_info($post['e']);

	$from = $get['from'];
	$to = $get['to'];

	$settings = array(
		'activities' => 'backup_auto_activities',
		'blocks' => 'backup_auto_blocks',
		'questionbank'=>'backup_auto_questionbank',
		'badges'=>'backup_auto_badges',
		'comments'=>'backup_auto_comments'
	);

	$course_import_from = $from;
	$course_restore_to = $to;

	$restoretarget=1;
	$backupid = backup_template($course_import_from,$settings,$config,$admin);
	restore_to_course($course_restore_to, $backupid, $restoretarget,$admin);


	$hasil = array(
			'result'				=>	41,
			'lurl'					=>	$GLOBAL['login_url'],
			'courseurl'				=>	$GLOBAL['course_url'],
			'course_import_from'	=>	$course_import_from,
			'course_restore_to'		=>	$course_restore_to
	);
	return $hasil;
}

function pditt_get_courses($post,$get) {
	global $GLOBAL;
	return get_courses();
}


function pditt_get_users($post,$get){
	return get_users_confirmed();
	
}



?>

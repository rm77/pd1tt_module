<?
include('fungsi_moodle.php');

function PDITT_CreateCourseX($data){
        return array('code'=>0, 'data'=>$data);
}


function PDITT_CreateCourse($data){
                $idnumber = $data['idnumber'];
                $kodemk = $data['kodemk'];
                $namamk = $data['namamk'];
                $summary = $data['summary'];

                $namacategory = 'PDITT-SOURCE';
                $cat = create_category_pditt($namacategory,'Mata-Kuliah PDITT');
                $idcourse = create_course_pditt($cat,$idnumber,$kodemk,$namamk,$summary);

                return array('errcode'=>0, 'kodemk'=>$kodemk, 'courseid'=>$idcourse, 'idnumber'=>$idnumber);
}


function PDITT_FindCourseID($data){
                $idnumber = $data['idnumber'];
                $result = find_course_pditt($idnumber);
                if ($result==-1){
                        return array('errcode'=>100, 'courseid'=>0 ,'reason'=>'course not found');
                }
                return array('errcode'=>0, 'idnumber'=>$result['idnumber'], 'courseid'=>$result['id'],'url'=>$result['url']); 
}

function PDITT_UserConnect($data){
                $username = $data['username'];
                $password = $data['password'];
                $idnumber = $data['idnumber'];

                $cek = cek_user_pditt($username,$password);
                if (!$cek){
                        return array('errcode'=>100, 'reason'=>'user cannot login');
                }

                $username_moodle = $cek['userid'];
                $password_moodle = $cek['d'];

                $result = find_course_pditt($idnumber);
                if ($result==-1){
                        return array('errcode'=>100, 'courseid'=>0, 'reason'=>'course not found');
                }



                enter($username,$password_moodle);
                return array('errcode'=>0, 'cmd'=>'GO','url'=> $result['url']);
}

function PDITT_DeleteCourse($data){
    $idnumber = $data['idnumber'];
    $coursef = find_course_pditt($idnumber);
    if ($coursef==-1){
            return array('errcode'=>100, 'courseid'=>0 ,'reason'=>'course not found');
    }

    $id = $coursef['id'];

    delete_course_pditt($id);
    return array('errcode'=>0, 'idnumber'=>$idnumber, 'courseid'=>$id);

}


function PDITT_OpenCourse($data){
                global $moodle_config,$moodle_admin, $DB;
                $idnumber_mk = $data['matkul_idnumber'];
                $idnumber_kelas = $data['kelas_idnumber'];
                $kodekelas = $data['nama_kelas'];
                $coursef = find_course_pditt($idnumber_mk);
                if ($coursef==-1){
                        return array('errcode'=>100, 'courseid'=>0 ,'reason'=>'course not found');
                }
                $id_course_asal=$coursef['id'];

                $x2 = $DB->get_record('course', array('idnumber'=>$idnumber_mk), '*');
                $kelas_shortname = $idnumber_kelas . '|' . $x2->shortname . '|' . $x2->fullname; 
                $kelas_fullname = $x2->fullname;
                $kelas_summary = $x2->summary;

                $cat = create_category_pditt('PDITT-KELAS-' . date("Y"));
                $id_course_tujuan = create_course_pditt($cat,$idnumber_kelas,$kelas_shortname,$kelas_fullname,$kelas_summary);

                $settings = array(
                        'activities' => 'backup_auto_activities',
                        'blocks' => 'backup_auto_blocks',
                        'questionbank'=>'backup_auto_questionbank',
                        'badges'=>'backup_auto_badges',
                        'comments'=>'backup_auto_comments'
                );

                $course_import_from = $id_course_asal;
                $course_restore_to = $id_course_tujuan;

                $restoretarget=1;
                $backupid = backup_template($course_import_from,$settings,$moodle_config,$moodle_admin);
                restore_to_course($course_restore_to, $backupid, $restoretarget,$moodle_admin);

                return array('errcode'=>0, 'idnumber'=>$idnumber_kelas, 'courseid'=>$id_course_tujuan);

}



function PDITT_Enrol($data){
                $idnumber = $data['idnumber'];
                $tipe = $data['tipe'];
                $result = find_course_pditt($idnumber);
                if ($result==-1){
                        return array('errcode'=>100, 'courseid'=>0 ,'reason'=>'course not found');
                }
                $idcourse = $result['id']; 
                $peserta = $data['list'];
                $peserta_list=array();

                switch ($tipe) {
                    case 'author':
                        $fname = 'create_dosen_if_not_exists';
                        $fname2 = 'enrol_author_pditt';
                        break;
                    case 'dosen':
                        $fname = 'create_dosen_if_not_exists';
                        $fname2 = 'enrol_dosen_pditt';
                        break;
                    case 'tutor':
                        $fname = 'create_dosen_if_not_exists';
                        $fname2 = 'enrol_tutor_pditt';
                        break;
                    case 'mhs':
                        $fname = 'create_mhs_if_not_exists';
                        $fname2 = 'enrol_mhs_pditt';
                        break;
                    case 'mahasiswa':
                        $fname = 'create_mhs_if_not_exists';
                        $fname2 = 'enrol_mhs_pditt';
                        break;
                }

                foreach($peserta as $d=>$pesertad){
                        $username = $pesertad['username'];
                        $password = $pesertad['password'];
                        $nama  = $pesertad['nama'];
                        $email = $pesertad['email'];
                        $guid = $pesertad['guid'];
                        $cr=$fname($username,$password,$nama,$email,$guid);
                        $userid = $cr['userid'];
                        $peserta_list[]=$userid;
                        $fname2($userid,$idcourse);
                }



                
                return  array(  'errcode'=>0,
                                'courseid'=>$idcourse, 
                                'idnumber'=>$idnumber,
                                'peserta_ids'=>$peserta_list
                        );
}



?>
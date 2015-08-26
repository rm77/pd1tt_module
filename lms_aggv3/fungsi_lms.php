<?
include('fungsi_moodle.php');

function PDITT_CreateCourseX($data){
        return array('code'=>0, 'data'=>$data);
}


function PDITT_CreateCourse($data){
                $kodemk = $data['kodemk'];
                $namamk = $data['namamk'];
                $summary = $data['summary'];

                $namacategory = 'PDITT-SOURCE';
                $cat = create_category_pditt($namacategory,'Mata-Kuliah PDITT');
                $idcourse = create_course_pditt($cat,$kodemk,$namamk,$summary);

                return array('errcode'=>0, 'kodemk'=>$kodemk, 'courseid'=>$idcourse);
}


function PDITT_FindCourseID($data){
                $kodemk = $data['kodemk'];
                $result = find_course_pditt($kodemk);
                if ($result==-1){
                        return array('errcode'=>100, 'courseid'=>0);
                }
                return array('errcode'=>0, 'courseid'=>$result['id'],'url'=>$result['url']); 
}

function PDITT_UserConnect($data){
                $username = $data['username'];
                $password = $data['password'];
                $kodemk = $data['kodemk'];

                $cek = cek_user_pditt($username,$password);
                if (!$cek){
                        return array('errcode'=>100);
                }

                $username_moodle = $cek['userid'];
                $password_moodle = $cek['d'];

                $result = find_course_pditt($kodemk);
                if ($result==-1){
                        return array('errcode'=>100, 'courseid'=>0);
                }

                enter($username,$password_moodle);
                return array('errcode'=>0, 'url'=> $result['url']);
}



function PDITT_OpenCourse($data){
                global $moodle_config,$moodle_admin;
                $university = $data['univ'];
                $tahun  = $data['tahun'];
                $periode = $data['periode'];
                $kodemk = $data['kodemk'];
                $namamk = $data['namamk'];
                $summary = $data['summary'];
                $coursef = find_course_pditt($kodemk);
                if ($coursef==-1){
                        return array('errcode'=>100, 'courseid'=>0);
                }
                $id_course_asal=$coursef['id'];
                $namacategory = $university . $tahun . $periode;
                $cat = create_category_pditt($namacategory,'PDITT-' . $namacategory);
                $id_course_tujuan = create_course_pditt($cat,$kodemk . '-' . $tahun . '-' . $periode,$namamk,$summary);

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

                return array('errcode'=>0, 'kodemk'=>$kodemk, 'courseid'=>$id_course_tujuan);

}

function PDITT_Enrol($data){
                $kodemk = $data['kodemk'];
                $tipe = $data['tipe'];
                $result = find_course_pditt($kodemk);
                if ($result==-1){
                        return array('errcode'=>100, 'courseid'=>0);
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
                                'kodemk'=>$kodemk,
                                'peserta_ids'=>$peserta_list
                        );
}



?>
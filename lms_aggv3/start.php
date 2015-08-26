<?
if (file_exists('site_config.php')) {
    include ('site_config.php');
} else {
    $GLOBAL = array();
    
}

@ini_set('display_errors', '1');
require_once('lib/token_tool.php');
require_once(isset($GLOBAL['lms_fungsi']) ? $GLOBAL['lms_fungsi'] : 'fungsi_lms.php');
$GLOBAL['lms_id']=isset($GLOBAL['lms_id']) ? $GLOBAL['lms_id'] : 'XXX1';
$GLOBAL['lms_desc']=isset($GLOBAL['lms_desc']) ? $GLOBAL['lms_desc'] : 'XXX2';
$GLOBAL['lms_url']=isset($GLOBAL['lms_url']) ? $GLOBAL['lms_url'] : '';
$GLOBAL['lms_secret']=isset($GLOBAL['lms_secret']) ? $GLOBAL['lms_secret'] : 'ROMBONGSOTO';
$GLOBAL['lms_deskripsi']=isset($GLOBAL['lms_deskripsi']) ? $GLOBAL['lms_deskripsi'] : '';




function serve($slimapp,$token){
    if ($token==''){
        //redirect to gate to get token
        $nexturl = $slimapp->global['lms_token_server'] . '?next=' . urlencode($slimapp->global['lms_url']); 
        if ($nextcommand!='')
            $nexturl . '&nextc=' . $nextcommand;
        $slimapp->redirect($nexturl,302);
    }

    $result=array();
    $slimapp->token_extractor_instance->setToken($token);
    if ($slimapp->token_extractor_instance->isTokenValid()){
        foreach($slimapp->token_extractor_instance->get_commands() as $k=>$cmds){
            if (!empty($cmds)){
                $fname  = $cmds['command'];
                $args   = $cmds['args'];
                $result[]=$fname($args);
            } else {
                $result[]=array('errcode'=>1010, 'msg'=>'invalid command due to time expiration');
            }
        }
    } else {
        $result[]=array('errcode'=>1000, 'msg'=>'invalid Token');
    }
    $slimapp->response->headers->set('Content-Type','application/json');
    $slimapp->response->write(json_encode($result));
}


?>

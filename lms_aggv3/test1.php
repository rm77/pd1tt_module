<?

/*

openssl genrsa -out mykey.pem 1024
will actually produce a public - private key pair. The pair is stored in the generated mykey.pem file.

openssl rsa -in mykey.pem -pubout > mykey.pub
will extract the public key and print that out. Here is a link to a page that describes this better.

To just output the public part of a private key:

openssl rsa -in key.pem -pubout -out pubkey.pem
*/

require_once('lib/token_tool.php');

function curlx($url, $fields = array(), $auth = false){
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_VERBOSE, 1);
    curl_setopt($curl, CURLOPT_HEADER, 1);
    
    if($auth){
        curl_setopt($curl, CURLOPT_USERPWD, "$auth");
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }

    if($fields){        
        $fields_string = http_build_query($fields);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
    }
    
    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header_string = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    $header_rows = explode(PHP_EOL, $header_string);
    $header_rows = array_filter($header_rows, trim);
    foreach((array)$header_rows as $hr){
        $colonpos = strpos($hr, ':');
        $key = $colonpos !== false ? substr($hr, 0, $colonpos) : (int)$i++;
        $headers[$key] = $colonpos !== false ? trim(substr($hr, $colonpos+1)) : $hr;
    }
    foreach((array)$headers as $key => $val){
        $vals = explode(';', $val);
        if(count($vals) >= 2){
            unset($headers[$key]);
            foreach($vals as $vk => $vv){
                $equalpos = strpos($vv, '=');
                $vkey = $equalpos !== false ? trim(substr($vv, 0, $equalpos)) : (int)$j++;
                $headers[$key][$vkey] = $equalpos !== false ? trim(substr($vv, $equalpos+1)) : $vv;
            }
        }
    }
    //print_rr($headers);
    curl_close($curl);
    return array($body, $headers);
}

function curly($url){
		$ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);       
        curl_close($ch);
        return $output;
}






/*
$myexpire = "09-08-2015 07:52";
$time_expire = strtotime($myexpire);
echo "TIME EXPIRE " . $time_expire . "\n";

$nowtime = strtotime("now");
echo "NOW " . $nowtime . "\n";

$expired = ($nowtime> $time_expire);
echo "EXPIRED : " . $expired . "\n";
exit(0);



$args = array('dosen'=>array('nama'=>'royyana'));

$u = create_token('PDITT_CreateCourse',json_encode($args),'123','LMS1','1234','http://localhost','240877');
echo $u;

$Token = new TokenMaker('X123','LMS1','abcd123','http://localhost/');
$Token->add_command('PDITT_CreateCourse',array('dosen'=>array('roy123','123')),'09-08-2015 07:57');
$Token->add_command('PDITT_CreateCourse',array('dosen'=>array('roy234','124')),'09-08-2015 07:57');
$encrypted = $Token->get_token();
echo 'ENC ' . $encrypted . "\n";

$TokenE = new TokenExtractor('X123','LMS1','abcd123','http://localhost/');
$TokenE->setToken($encrypted);
print_r($TokenE->get_commands());

exit(0);
*/

function create_course(){
		$dosen = array();
		$dosen[]=array('username'=>'kadal_1','password'=>'12345','nama'=>'Daley Blind1999','email'=>'blink1999@bl.co.id','guid'=>'18929993133aa');
		$dosen[]=array('username'=>'kadal_2','password'=>'12345','nama'=>'Daley Blind2999','email'=>'blink2999@bl.co.id','guid'=>'99928923133aa');
		$dosen[]=array('username'=>'kadal_3','password'=>'12345','nama'=>'Daley Blind3999','email'=>'blink3999@bl.co.id','guid'=>'38999923133aa');

		$data = array();
		$data['kodemk']='002002XX4009';
		$data['namamk']='Matematika Lanjut USIA';
		$data['summary']='Matematika Yang Harus Dilanjutkan';

		$Token = new TokenMaker('PDITTCreateCourse','X123','LMS1','abcd123','http://localhost/moodle/pditt_module/lms_aggv3/lms/gate');
		$Token->add_command('PDITT_CreateCourse',$data,'27-08-2015 12:00');


		$data=array();
		$data['kodemk']='002002XX4009';
		$data['tipe']='author';
		$data['list']=$dosen;
		$Token->add_command('PDITT_Enrol',$data,'27-08-2015 12:00');

		$data=array();
		$data['kodemk']='002002XX4009';
		$data['tipe']='dosen';
		$data['list']=$dosen;
		$Token->add_command('PDITT_Enrol',$data,'27-08-2015 12:00');


		$infohttp=array();
		$infohttp['token']=$Token->get_credential();

		$url='http://localhost/moodle/pditt_module/lms_aggv3/lms/gate';
		list($body,$headers) = curlx($url, $infohttp);
		$result=array('body'=>$body, 'headers'=>$headers);
		print_r($result);
}


function find_course(){
	$data = array();
	$data['kodemk']='002002XX400XX9';
	$Token = new TokenMaker('PDITTFindID','X123','LMS1','abcd123','http://localhost/moodle/pditt_module/lms_aggv3/lms/gate');
	$Token->add_command('PDITT_FindCourseID',$data,'29-08-2015 12:00');
	
//	$data['kodemk']='002002-ITS2001';
//	$Token->add_command('PDITT_FindCourse',$data,'24-08-2015 12:00');


	$infohttp=array();
	$infohttp['token']=$Token->get_credential();
	$url='http://localhost/moodle/pditt_module/lms_aggv3/lms/gate';
	list($body,$headers) = curlx($url, $infohttp);
	$result=array('body'=>$body, 'headers'=>$headers);
	print_r($result);
}

function user_connect(){
	$data = array();
	$data['username']='blind_daley123';
	$data['password']='12345';
	$data['kodemk']='002002CS1404';
	$Token = new TokenMaker('PDITTConnect','X123','LMS1','abcd123','http://localhost/moodle/pditt_module/lms_aggv3/lms/gate');
	$Token->add_command('PDITT_UserConnect',$data,'24-08-2015 12:00');
	


	$infohttp=array();
	$infohttp['token']=$Token->get_credential();
	$url='http://localhost/moodle/pditt_module/lms_aggv3/lms/gate';
	list($body,$headers) = curlx($url, $infohttp);
	$result=array('body'=>$body, 'headers'=>$headers);
	print_r($result);
}


function open_course(){
/*

                $university = $data['univ'];
                $tahun  = $data['tahun'];
                $periode = $data['periode'];
                $kodemk = $data['kodemk'];
                $namamk = $data['namamk'];
                $summary = $data['summary'];

*/

	$dosen = array();
	$dosen[]=array('username'=>'blind_daley100','password'=>'12345','nama'=>'Daley Blind100','email'=>'blink100@bl.co.id','guid'=>'18923133aaX100');

	$tutor = array();
	$tutor[]=array('username'=>'blind_daley201','password'=>'12345','nama'=>'Daley Blind201','email'=>'blink201@bl.co.id','guid'=>'18923133aaX201');
	$tutor[]=array('username'=>'blind_daley202','password'=>'12345','nama'=>'Daley Blind202','email'=>'blink202@bl.co.id','guid'=>'18923133aaX202');

	$mhs= array();
	$mhs[]=array('username'=>'blind_daley301','password'=>'12345','nama'=>'Daley Blind301','email'=>'blink301@bl.co.id','guid'=>'18923133aaX301');
	$mhs[]=array('username'=>'blind_daley302','password'=>'12345','nama'=>'Daley Blind302','email'=>'blink302@bl.co.id','guid'=>'18923133aaX302');
	$mhs[]=array('username'=>'blind_daley303','password'=>'12345','nama'=>'Daley Blind303','email'=>'blink303@bl.co.id','guid'=>'18923133aaX303');


	$data = array();
	$data['univ']='002002';
	$data['tahun']='2015';
	$data['periode']='1';
	$data['kodemk']='002002CS1404';
	$data['namamk']='Matematika 1 2';
	$data['summary']='Matematika 1 2 a';
	$Token = new TokenMaker('PDITTCourse','X123','LMS1','abcd123','http://localhost/moodle/pditt_module/lms_aggv3/lms/gate');
	$Token->add_command('PDITT_OpenCourse',$data,'24-08-2015 12:00');


	$data=array();
	$data['kodemk']='002002CS1404';
	$data['tipe']='dosen';
	$data['list']=$dosen;
	$Token->add_command('PDITT_Enrol',$data,'24-08-2015 12:00');

	$data=array();
	$data['kodemk']='002002CS1404';
	$data['tipe']='tutor';
	$data['list']=$tutor;
	$Token->add_command('PDITT_Enrol',$data,'24-08-2015 12:00');

	$data=array();
	$data['kodemk']='002002CS1404';
	$data['tipe']='mhs';
	$data['list']=$mhs;
	$Token->add_command('PDITT_Enrol',$data,'24-08-2015 12:00');

	$infohttp=array();
	$infohttp['token']=$Token->get_credential();
	$url='http://localhost/moodle/pditt_module/lms_aggv3/lms/gate';
	list($body,$headers) = curlx($url, $infohttp);
	$result=array('body'=>$body, 'headers'=>$headers);
	print_r($result);
}

//create_course();
//open_course();
//user_connect();
find_course();

?>

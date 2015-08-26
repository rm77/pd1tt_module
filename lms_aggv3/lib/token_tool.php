<?
require __DIR__ . '/../vendor/autoload.php';
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Keychain; 
use Lcobucci\JWT\Signer\Rsa\Sha256;

class TokenMaker {
    public function __construct($token_desc,$lms_id,$lms_desc,$lms_secret,$lms_url){
        $this->token_desc = $token_desc;
        $this->lms_id = $lms_id;
        $this->lms_desc = $lms_desc;
        $this->lms_secret = $lms_secret;
        $this->lms_url = $lms_url;
        $this->private_key_file_name='mykey.pem';
        $this->cmds = array();
    }

    public function encrypt($decrypted, $password, $salt='!kQm*fF3pXe1Kbm%9') { 
        $key = hash('SHA256', $salt . $password, true);
        srand(); 
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
        if (strlen($iv_base64 = rtrim(base64_encode($iv), '=')) != 22) return false;
        $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $decrypted . md5($decrypted), MCRYPT_MODE_CBC, $iv));
        return $iv_base64 . $encrypted;
    }    


    public function add_command($command,$args,$date_to_expire){
        $this->cmds[]=array('command'=>$command, 'args'=>$args, 'expire'=>$date_to_expire);
    }

    public function get_token(){
        $data=array();
        $data['ident']='OK';
        $data['cmd']=$this->cmds;
        $data['lms']=array('id'=>$this->lms_id,  'lms_desc'=>$this->lms_desc,  'lms_url'=>$this->lms_url);
        $token = $this->encrypt(json_encode($data),$this->lms_secret,$salt=base64_encode(rand()));
        return $salt . '$$' . $token;
    }

    public function get_token_description(){
        return $this->token_desc;
    }

    public function set_private_key_file($private_key_file_name){
        $this->$private_key_file_name=$private_key_file_name;
    }

    public function get_sign(){
        $data = $this->get_token();
        $signer = new Sha256();
        $keychain = new Keychain();

        $sign = (new Builder())->setIssuer('pditt') // Configures the issuer (iss claim)
                                ->setAudience('pditt') // Configures the audience (aud claim)
                                ->setId($this->lms_desc, true) // Configures the id (jti claim), replicating as a header item
                                ->set('token', $data) // Configures a new claim, called "uid"
                                ->sign($signer,  $keychain->getPrivateKey('file://' . $this->private_key_file_name)) // creates a signature using your private key
                                ->getToken(); // Retrieves the generated token
                                
        return (string)$sign;
    }

    public function get_credential(){
        return json_encode(array('token_description'=>$this->get_token_description(),'token'=>$this->get_token(),'sign'=>$this->get_sign($this->private_key_file_name)));
    }
}



class TokenExtractor {
    public function __construct($lms_id,$lms_desc,$lms_secret,$lms_url){
        $this->lms_id = $lms_id;
        $this->lms_desc = $lms_desc;
        $this->lms_secret = $lms_secret;
        $this->lms_url = $lms_url;
        $this->isValid = false;
        $this->sign='';
        $this->public_key_file_name='mykey.pub';
        $this->result = array();
        $this->token_desc = '';
    }

    public  function decrypt($encrypted, $password, $salt='!kQm*fF3pXe1Kbm%9') {
        $key = hash('SHA256', $salt . $password, true);
        $iv = base64_decode(substr($encrypted, 0, 22) . '==');
        $encrypted = substr($encrypted, 22);
        $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, base64_decode($encrypted), MCRYPT_MODE_CBC, $iv), "\0\4");
        $hash = substr($decrypted, -32);
        $decrypted = substr($decrypted, 0, -32);
        if (md5($decrypted) != $hash) 
            return false;
        return $decrypted;
    }


    public function set_public_key_file($public_key_file_name){
        $this->public_key_file_name=$public_key_file_name;
    }

    public function setToken($tokencred){
        $tk = json_decode($tokencred,true);
        $this->token_desc  = $tk['token_description'];
        $this->token = $tk['token'];
        $this->sign = $tk['sign'];
        list($this->salt,$this->tokenstr)=explode('$$',$this->token);
        $hasil= $this->decrypt($this->tokenstr,$this->lms_secret,$this->salt);
        $hasil = json_decode($hasil,true);
        $this->result = $hasil;


        $signer = new Sha256();
        $keychain = new Keychain();
        $mysign = (new Parser())->parse($this->sign);
        $isSignValid = $mysign->verify($signer,$keychain->getPublicKey('file://' . $this->public_key_file_name));
        $this->isValid = ($isSignValid==true) && ($hasil!=false) && isset($hasil['ident']) && ($hasil['ident']=='OK');
    }



    public function get_token_description(){
        return $this->token_desc;
    }


    public function isTokenValid(){
        return $this->isValid;
    }

    public function get_commands(){
        $hasil = array();
        if ($this->isValid){
            foreach($this->result['cmd'] as $k=>$ccmd){
                $time_expire = strtotime($ccmd['expire']);
                $nowtime = strtotime("now");
                if ($nowtime<=$time_expire)
                    $hasil[]=$ccmd;
                else
                    $hasil[]=array();
            }
            return $hasil;
        } else {
            return array();
        }
    }

}


?>

<?php
/*
$Crypt = new Crypt('5f9f5dc4e72c1d57f65773a72ee0462e', 'b3873a03caefc1163206a190eb3ac3b9', 'StoneEDM');

$str = ['test' => 'test'];
$str = $Crypt->encrypt($str);
$str = $Crypt->decrypt($str, 'b3873a03caefc1163206a190eb3ac3b9');
var_dump($str);
*/

class Crypt {
    var $key;
    var $iv;
    var $appkey;

    function __construct ($appkey = '', $key = '', $iv = 'StoneEDM') {
        $this->key = $key;
        $this->iv = $iv;
        $this->appkey = $appkey;
    }

    function encrypt($data){
        if (!is_array($data)) {
            return '';
        }
        $data['expired_date'] = time()+12*60*60;
        $dataser = @json_encode($data);
        $c_t =  openssl_encrypt($dataser, 'DES-EDE3-CBC', $this->key, 0, $this->iv);
        $verify = md5(md5($this->appkey).$this->key.$c_t);
        return urlencode(base64_encode(json_encode(array('app_key'=>md5($this->appkey), 'encrypt_data'=>$c_t, 'verify'=>$verify))));
    }

	function decrypt($data){
        $data = base64_decode(urldecode($data));
        if (empty($data)){
            return false;
        }
        $data = @json_decode($data,true);
        if (!isset($data['app_key']) || empty($data['app_key']) ||!isset($data['encrypt_data']) ||empty($data['encrypt_data']) ||!isset($data['verify']) ||empty($data['verify']) ) {
            return false;
        }
        if (md5($data['app_key'].$this->key.$data['encrypt_data']) != $data['verify']){
            return false;
        }
        $str = openssl_decrypt($data['encrypt_data'], 'DES-EDE3-CBC', $this->key, 0, $this->iv);
        $str = @json_decode(trim($str),true);
        if (is_null($str) || !isset($str['expired_date'])){
            return false;
        }
        if (intval($str['expired_date']) < time()){
            return false;
        }
        unset($str['expired_date']);
        return $str;
	}


}
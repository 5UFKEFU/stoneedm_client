<?php

//!defined('IN_FRAMEWORK') && exit('No direct script access allowed');


/**
 * 通过curl方式请求url
 *
 * @author binbin.yin
 *
 * @param string $url 待请求的网址
 * @param string $method 请求方式，
 * @param array $params 请求时附带参数
 * @param bool $multi 是否以multi方式
 * @param array $headers 请求带上的header头信息
 * @param string $referer 来源
 * @return string
 */
function curl ($url, $method = 'get', $params = array(), $multi = false, $headers = array('Cache-Control:max-age=0', 'Accept-Language:zh-CN,zh;q=0.8'), $referer = false) {
    $method = strtolower($method);
    $ci = curl_init();
    curl_setopt($ci, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.41 Safari/537.36');
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ci, CURLOPT_TIMEOUT, 60);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ci, CURLOPT_ENCODING, '');
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ci, CURLOPT_HEADER, false);
    curl_setopt($ci, CURLINFO_HEADER_OUT, true);

    $referer && curl_setopt($ci, CURLOPT_REFERER, $referer);

    if ('post' == $method) {
        curl_setopt($ci, CURLOPT_POST, true);
        if (!$multi && (is_array($params) || is_object($params))) {
            $params = http_build_query($params);
        }
        if (!empty($params)) {
            curl_setopt($ci, CURLOPT_POSTFIELDS, $params);
        }
    } else {
        $url .= (false !== strpos($url, '?') ? '&' : '?') . (is_array($params) ? http_build_query($params) : $params);
    }

    curl_setopt($ci, CURLOPT_URL, $url);

    if (!empty($headers)) {
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ci);
    curl_close ($ci);
    return $response;
}

/**
 * 加密解密
 *
 * @author binbin.yin
 *
 * @param string $string 待加密或解密的字符串
 * @param string $operation 加解密操作，加密：ENCODE，解密：DECODE，
 * @param string $key 请求时附带参数
 * @param int $expiry 是否以multi方式
 * @return string
 */
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
    $ckey_length = 4;
    $key = md5($key != '' ? $key : getglobal('authkey'));
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);

    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = array();
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation == 'DECODE') {
        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc.str_replace('=', '', base64_encode($result));
    }
}

function PrintLog($log, $exit=false){
    if (is_array($log)) {
        foreach ($log as $v){
            PrintLog($v);
        }
        return true;
    }
    $mem = formatBytes(memory_get_usage(true));
    $mem = $mem.str_repeat(' ', 12-strlen($mem));
    echo date('Y-m-d H:i:s')."     {$mem}{$log}\r\n";
    return true;
}

function formatBytes($size) {
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) {
        $size /= 1024;
    }
    return round($size, 2).$units[$i];
}

function isEmail($email) {
    return strlen($email) > 6 && preg_match("/^([A-Za-z0-9\-_.+]+)@([A-Za-z0-9\-]+[.][A-Za-z0-9\-.]+)$/", $email);
}

function get_mx($email){
    if (!isEmail($email)) {
        printLog("错误的邮箱: {$email}");
        exit;
    }
    printLog('正在获得MX记录...');
    $email_arr = explode('@', $email);
    $domain = array_pop($email_arr);
    $mx_arr = array();
    $output = dns_get_record($domain, DNS_MX);
    if (empty($output)) {
        return '';
    }
    $output = multi_array_sort($output, 'pri', SORT_ASC);
    $mx = $output[0]['target'];

    //随机获取mx
     foreach ($output as $v){
         $mx_arr[] = $v['target'];
     }
     $mx = $mx_arr[array_rand($mx_arr)];
//     printLog('成功得到MX记录：'.$mx);
    return $mx;
}

function multi_array_sort($multi_array, $sort_key, $sort=SORT_ASC){
    if(is_array($multi_array)){
        foreach ($multi_array as $row_array){
            if(is_array($row_array)){
                $key_array[] = $row_array[$sort_key];
            }else{
                return false;
            }
        }
    }else{
        return false;
    }
    array_multisort($key_array,$sort,$multi_array);
    return $multi_array;
}

function get_email_domain($email){
    $arr = explode('@', $email);
    return $arr[count($arr)-1];
}

function get_sendEmail_ret($output) {
    if (empty($output)) {
        return '';
    }

    $last_line = $output[count($output)-1];
    if (false !== strpos($last_line, 'Email was sent successfully')) {
        return array('code'=>250, 'reason'=>'Email was sent successfully');
    }
    $reason = array();

    $output_all_str = implode('|', $output);
    preg_match('/\s{2}\d{3}\s{1}/', $output_all_str, $matches);
    $code = empty($matches) ? 0 : intval($matches[0]);

    foreach ($output as $line) {
        $line = explode(' => ', $line);
        if (count($line)>1) {
            $reason[] = array_pop($line);
        }

    }
    return array('code'=>$code, 'reason'=>implode("|", $reason));
}
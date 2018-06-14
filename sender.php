<?php
define('IN_FRAMEWORK', true);
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');
date_default_timezone_set('Asia/Shanghai');
$config = include 'config/config.php';
require_once 'common/functions.php';
require_once 'class/crypt.class.php';
set_time_limit(600);
ini_set('memory_limit','128m');
$Crypt = new Crypt($config['appkey'], $config['key']);

$arg = $_SERVER['argv'];
count($arg) != 19 && exit("使用方法：sender.php <from> <from_name> <to> <subject> <message> <message_text> <ip> <reply> <did> <msp> <timeout> <fqdn> <mx> <is_retry> <use_multipart> <unsubscribe> <private_key> <dkim_selector>\r\n");

echo "\r\n";
printLog("------------------------------- 脚本初始化完成 ! -------------------------------");

list($null, $from, $from_name, $to, $subject, $message, $message_text, $ip, $reply, $qid, $msp, $timeout, $fqdn, $mx, $is_retry, $use_multipart, $unsubscribe, $private_key, $dkim_selector) = $arg;

$dirname = dirname(__FILE__);
$cmd = <<<EOF
{$config['php_path']} {$dirname}/sender.php "{$from}" "{$from_name}" "{$to}" "{$subject}" "{$message}" "{$message_text}" "{$ip}" "{$reply}" "{$qid}" "{$msp}" "{$timeout}" "{$fqdn}" "{$mx}" "{$is_retry}" "{$use_multipart}" "{$unsubscribe}" "{$private_key}" "{$dkim_selector}"
EOF;


$domain = empty($mx) ? get_mx($to) : $mx;
printLog('MX:'.$domain);
use PHPMailer\PHPMailer\PHPMailer;
require_once($dirname.'/phpmailer/src/PHPMailer.php');
require_once($dirname.'/phpmailer/src/SMTP.php');
require_once($dirname.'/phpmailer/src/Exception.php');
$from = base64_decode($from);
$subject = base64_decode($subject);
$message = base64_decode($message);
$message_text = base64_decode($message_text);
$private_key = base64_decode($private_key);
$from_name = !empty($from_name) ? base64_decode($from_name) : '';

$mail = new PHPMailer;
$mail->IsSMTP();
$mail->Host = $domain;
$mail->SMTPAuth = false;
$mail->Mailer = 'smtp';
$mail->setFrom($from, $from_name);
printLog('FROM:'.$from.' '.$from_name);
$mail->addAddress($to);
$mail->AddReplyTo($reply, $from_name);
$mail->Subject = $subject;
$mail->msgHTML($message, __DIR__);
$mail->AltBody = $message_text;
$mail->CharSet ="UTF-8";
$mail->Encoding ="base64";
$mail->Timeout = $timeout;

if (!empty($ip)) {
    $mail->SMTPOptions = ['socket' => ['bindto' => $ip.':0']];
}
$mail->addCustomHeader('List-Unsubscribe', "<{$unsubscribe}>");

if (!empty($private_key)) {
    $mail->DKIM_domain = get_email_domain($from);
    $mail->DKIM_private_string = $private_key;
    $mail->DKIM_selector = $dkim_selector;
    $mail->DKIM_passphrase = '';
    $mail->DKIM_identity = $mail->From;
}
$mail->SMTPDebug = 1;

$status = $mail->send();
if (!$status) {
    $ret = ['reason' => $mail->ErrorInfo];
    $ret['code'] = 999;
} else {
    $ret = ['code' => 250, 'reason' => 'Email was sent successfully'];
}
printLog(array('执行结果： '.$ret['reason'], '正在向服务器反馈结果...'));
$stat = false !== strpos($ret['reason'], 'Email was sent successfully') ? 2 : 3;
$data = [
    'appkey' => $config['appkey'],
    'data' => $Crypt->encrypt([
        'from'       => $from,
        'email'       => $to,
        'client_name' => $config['client_name'],
        'msp'         => $msp,
        'ip'          => $ip,
        'qid'         => $qid,
        'stat'        => $stat,
        'code'        => $ret['code'],
        'mx'          => $domain,
        'reason'      => $ret['reason'],
        'is_retry'    => $is_retry,
        'cmd'         => $cmd
    ])
];
$ret = $ret_old = curl($config['feedback_api'], 'POST', $data);
var_dump($data);
$ret = $Crypt->decrypt($ret);
if (!$ret) {
    $ret_old = json_decode($ret_old, true);
    if (is_array($ret_old) && !empty($ret_old)) {
        printLog("返回结果出错 ！ (ㄒoㄒ)  code:{$ret_old['code']}  msg:{$ret_old['msg']}");
    } else {
        printLog("返回结果出错 ！ (ㄒoㄒ) 返回：{$ret_old}");
    }
    exit;
} else if ($ret['code'] != 1) {
    printLog("返回结果出错 ！ (ㄒoㄒ)  code:{$ret['code']}  msg:{$ret['msg']}");
    exit;
}

printLog(array(
    (1 == $ret['code'] ? '成功 ^_^' : '失败 (ㄒoㄒ)').'  => '.$ret['msg'],
    '------------------------------- 脚本已执行完毕 ! -------------------------------'
));

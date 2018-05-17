<?php
define('IN_FRAMEWORK', true);
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');
date_default_timezone_set('Asia/Shanghai');
$config = include 'config/config.php';
include 'common/functions.php';
set_time_limit(0);
ini_set('memory_limit','30m');

$arg = $_SERVER['argv'];
count($arg) != 16 && exit("使用方法：sender.php <from> <to> <subject> <message> <message_text> <ip> <reply> <did> <msp> <timeout> <fqdn> <mx> <is_retry> <use_multipart>\r\n");
echo "\r\n";
printLog("------------------------------- 脚本初始化完成 ! -------------------------------");

list($null, $from, $to, $subject, $message, $message_text, $ip, $reply, $qid, $msp, $timeout, $fqdn, $mx, $is_retry, $use_multipart, $unsubscribe) = $arg;
$script = dirname(__FILE__).'/sendEmail';

$script .= $use_multipart ? '_multi' : '';

$domain = empty($mx) ? get_mx($to) : $mx;

$cmd = "{$script} -f \"{$from}\" -t \"{$to}\" -u \"{$subject}\" -m \"{$message}\" -s {$domain}  -b {$ip}  -o reply-to={$reply} -o message-content-type=html -o message-charset=utf8 -o message-header=\"List-Unsubscribe: <{$unsubscribe}>\" -o timeout={$timeout}";
$cmd .= !empty($fqdn) ? " -o fqdn=\"{$fqdn}\"" : '';
$cmd .= $use_multipart ? ' -bt "'.$message_text.'" ' : '';

printLog("正在发送邮件  => {$to} ...");
if (substr($to, 0, 1) == '-') {
    $ret = array('code'=>999, 'reason'=>'邮箱格式错误！');
} else {
    //file_put_contents(dirname(__FILE__).'/cmd.log', $cmd."\r\n", FILE_APPEND);
    exec($cmd, $output);
    $ret = get_sendEmail_ret($output);
}


printLog(array('执行结果： '.$ret['reason'], '正在向服务器反馈结果...'));

$stat = false !== strpos($ret['reason'], 'Email was sent successfully') ? 2 : 3;
$params = array(
    'email'    => $to,
    'client_name' => $config['client_name'],
    'msp'      => $msp,
    'ip'       => $ip,
    'qid'      => $qid,
    'stat'     => $stat,
    'code'     => $ret['code'],
    'mx'       => $domain,
    'reason'   => $ret['reason'],
    'is_retry' => $is_retry,
    'cmd'      => $cmd
);
$content = $content1 = curl($config['feedback_api'], 'POST', $params);

$content = json_decode($content, true);
null == $content && (printLog('返回结果出错 ！')&&exit($content1));
printLog(array(
    (1 == $content['code'] ? '成功 ^_^' : '失败 (ㄒoㄒ)').'  => '.$content['msg'],
    '------------------------------- 脚本已执行完毕 ! -------------------------------'
));
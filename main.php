<?php
define('IN_FRAMEWORK', true);
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');

date_default_timezone_set('Asia/Shanghai');
$config = include 'config/config.php';
require_once 'common/functions.php';
require_once 'class/crypt.class.php';
set_time_limit(0);
ini_set('memory_limit',$config['memory_limit']);

if (function_exists('exec')) {
    printLog('exec函数不可用，请修改php.ini打开exec权限！');
}

$switch_file = dirname(__FILE__).'/switch.txt';
file_put_contents($switch_file, '1');
$Crypt = new Crypt($config['appkey'], $config['key']);

echo "\r\n";
printLog('------------------------------- 脚本初始化完成 ! -------------------------------');
if (empty($config['msp'])) {
    printLog('正在获取服务端MSP配置信息... ');
    $mspConfig = get_msp();
    printLog('MSP配置信息获取成功  ^_^');
} else {
    $mspConfig = $config['msp'];
}

printLog('正在上报msp和ip信息... ');
$data = [
    'appkey' => $config['appkey'],
    'data' => $Crypt->encrypt([
        'msp' => $mspConfig,
        'ip' => $config['ip'],
    ])
];
$ret = $ret_old = curl($config['report_api'], 'post', $data);
$ret = $Crypt->decrypt($ret);
if (!$ret) {
    $ret_old = json_decode($ret_old, true);
    if (is_array($ret_old) && !empty($ret_old)) {
        printLog("上报返回错误！ (ㄒoㄒ)  code:{$ret_old['code']}  msg:{$ret_old['msg']}");
    } else {
        printLog("上报返回错误！ (ㄒoㄒ) 返回：{$ret_old}");
    }
    exit;
} else if ($ret['code'] != 1) {
    printLog("上报返回错误！ (ㄒoㄒ)  code:{$ret['code']}  msg:{$ret['msg']}");
    exit;
}

printLog('上报完成，结果 => 成功  ^_^');
unset($ret);

$counter = 0;
while (true) {
    if ($counter == 10) {
        if (empty($config['msp'])) {
            printLog("开始获取msp配置...");
            $data =  get_msp();
            if (!empty($data)) {
                printLog("获取msp配置成功 ^_^");
                $mspConfig = $data;
            }
        }
        $counter = 0;
    }
    $counter++;
    foreach ($mspConfig as $msp) {
        sleep($config['request_interval']);
        $is_open = file_get_contents($switch_file);
        if (1 != $is_open) {
            printLog("运行结束：switch");
            exit;
        }

        $taskCount = check_task($config['max_process']);
        printLog("sendmail进程数：{$taskCount}");
        printLog("开始获取msp => {$msp}的任务！");

        $data = [
            'appkey' => $config['appkey'],
            'data' => $Crypt->encrypt([
                'client_name' => $config['client_name'],
                'msp' => $msp,
                'ip' => $config['ip'],
                'max_send_num' => $config['max_send_num']
            ])
        ];
        $ret = $ret_old = curl($config['task_api'], 'post', $data);

        $ret = $Crypt->decrypt($ret);
        if (!$ret) {
            $ret_old_json = json_decode($ret_old, true);
            if (is_array($ret_old_json) && !empty($ret_old_json)) {
                printLog("获取任务时出错 (ㄒoㄒ)  code:{$ret_old_json['code']}  msg:{$ret_old_json['msg']}");
            } else {
                printLog("获取任务时出错 (ㄒoㄒ) 返回：{$ret_old}");
            }
            continue;
        } else if ($ret['code'] != 1) {
            printLog("获取任务时出错 (ㄒoㄒ)  code:{$ret['code']}  msg:{$ret['msg']}");
            continue;
        }
        printLog("获取任务成功，共获得常规邮件".(isset($ret['data']['nomal_task']) ? count($ret['data']['nomal_task']) : 0)."封，特殊邮件".(isset($ret['data']['special_task']) ? count($ret['data']['special_task']) : 0)."封");

        $timeout = $ret['data']['timeout'];
        $use_multipart = $ret['data']['use_multipart'];
        $php_path = $config['php_path'];
        $script = dirname(__FILE__).'/sender.php';

        //普通任务
        $nomal_task = $ret['data']['nomal_task'];

        foreach ($nomal_task as $k => $email) {
            $ip = $k;
            $from = base64_encode($email['from']);
            $from_name = base64_encode($email['from_name']);
            $to = $email['to_mailbox'];

            $subject = base64_encode($email['subject']);
            $body = base64_encode($email['body']);
            $body_text = base64_encode($email['body_text']);
            $reply = $email['reply'];
            $did = $email['did'];

            $email_domain = get_email_domain($to);
            $mx = isset($email['mx']) ? $email['mx'] : '';

            $is_retry = $email['is_retry'];
            $unsubscribe = $email['unsubscribe'];
            $private_key = base64_encode($email['private_key']);
            $dkim_selector = base64_encode($email['dkim_selector']);
            $cmd = "nohup {$php_path} {$script}  \"{$from}\" \"{$from_name}\" \"{$to}\" \"{$subject}\" \"{$body}\" \"{$body_text}\" \"{$ip}\" \"{$reply}\" {$did} \"{$msp}\" \"{$timeout}\" \"{$email['fqdn']}\" \"{$mx}\" \"{$is_retry}\" \"{$use_multipart}\" \"{$unsubscribe}\" \"{$private_key}\" \"{$dkim_selector}\" 1>out.txt 2>err.txt &";
            printLog($cmd);
            //file_put_contents(dirname(__FILE__).'/cmd_sender.log', $cmd."\r\n");
            printLog("mx => {$mx}");
            printLog('[普通'.($is_retry ? " - 重试（{$is_retry}）" : '').'] '.$ip.str_repeat(' ', 15-strlen($ip)).' => '.$to);
            exec($cmd);

        }


        //特殊任务
        $special_task = $ret['data']['special_task'];
        foreach ($special_task as $k => $email) {
            $ip = $k;
            $from = base64_encode($email['from']);
            $from_name = base64_encode($email['from_name']);
            $to = $email['to_mailbox'];

            $subject = base64_encode($email['subject']);
            $body = base64_encode($email['body']);
            $body_text = base64_encode($email['body_text']);
            $reply = $email['reply'];
            $did = $email['did'];

            $email_domain = get_email_domain($to);
            $mx = isset($ret['data']['mx'][$email_domain]) ? $ret['data']['mx'][$email_domain][array_rand($ret['data']['mx'][$email_domain])] : '';

            $is_retry = $email['is_retry'];
            $unsubscribe = $email['unsubscribe'];
            $private_key = base64_encode($email['private_key']);
            $dkim_selector = base64_encode($email['dkim_selector']);
            $cmd = "nohup {$php_path} {$script}  \"{$from}\" \"{$from_name}\" \"{$to}\" \"{$subject}\" \"{$body}\" \"{$body_text}\" \"{$ip}\" \"{$reply}\" {$did} \"{$msp}\" \"{$timeout}\" \"{$email['fqdn']}\" \"{$mx}\" \"{$is_retry}\" \"{$use_multipart}\" \"{$unsubscribe}\" \"{$private_key}\" \"{$dkim_selector}\" 1>out.txt 2>err.txt &";
            //printLog($cmd);
            //file_put_contents(dirname(__FILE__).'/cmd_sender.log', $cmd."\r\n");
            printLog('[特殊'.($is_retry ? " - 重试（{$is_retry}）" : '').'] '.$ip.str_repeat(' ', 15-strlen($ip)).' => '.$to);
            printLog("mx => {$mx}");
            exec($cmd);

        }
    }

}


function get_msp () {
    global $config;
    global $Crypt;
    $mspContent = curl($config['msp_api'], 'post', ['appkey' =>$config['appkey']]);
    $mspContent = $Crypt->decrypt($mspContent);
    if (!$mspContent ) {
        printLog('获取MSP列表时json解析出错 !');
        return array();
    } else {
        $mspContent = array_values($mspContent['data']['msp_domain']);
        $mspContent[] = 'all';
        $mspContent = array_unique($mspContent);
        return $mspContent;
    }
}



function check_task ($max) {
    $cmd = "ps -ef |grep sender.php |wc -l ";
    exec($cmd, $output);
    $num = intval($output[0])-2;
    if ($num >= $max) {
        printLog('sender.php进程数（'.$num.'）超过'.$max.'，暂时睡眠等待...');
        sleep(5);
        return check_task($max);
    } else {
        return $num;
    }
}
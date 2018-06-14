 <?php
!defined('IN_FRAMEWORK') && exit('No direct script access allowed');
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');


//这里是需要配置的选项
return array(
    'client_name' => '香港1',
    //edm上报msp实例接口
    'appkey' => '',  //
    'key' => '',
    'report_api' => 'https://www.stoneedm.com/api/send/report.html',
    'task_api' => 'https://www.stoneedm.com/api/task/index.html',
    'msp_api' => 'https://www.stoneedm.com/api/task/msp.html',
    'feedback_api' => 'https://www.stoneedm.com/api/feedback/index.html',
    //进程最大数，同时限制每次请求获得的邮件数量，如果配置为20，则理论获得最大邮件数为40，20个普通，20个ip分组邮件
    'max_send_num' => 20,
    'max_process' => 500,
    'request_interval' => 1,//每次请求获取任务间隔，单位为秒
    //这里如果设置为空数组，程序会使用服务端的配置
    'msp' => array(

    ),
    'ip' => array(
        'mta110-12.magvision.com' =>    '1.1.1.1',
        'mta110-13.magvision.com' =>    '2.2.2.2',
		//...
    ),
    'memory_limit' => '1280M',
    'php_path' => '/usr/local/webserver/php/bin/php'
);

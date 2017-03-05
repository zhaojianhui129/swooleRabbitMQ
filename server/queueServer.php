<?php
//初始化文件
define('DEBUG', 'on');
define('DS', DIRECTORY_SEPARATOR);
define('WEBPATH', __DIR__ . DS . '..');
define('APPSPATH', WEBPATH .'/queue');

//使用composer扩展
require_once WEBPATH . '/vendor/autoload.php';
//载入swoole frameworkZ框架配置
require_once WEBPATH . '/vendor/matyhtf/swoole_framework/libs/lib_config.php';

//Swoole::$php->setAppPath(WEBPATH . '/queue/');
//设置调试模式
Swoole\Config::$debug = true;

class QueueServer extends \Swoole\Protocol\Base
{
    const MAX_REQUEST = 10000;//每个子进程while循环里面最多循环次数，防止内存泄露
    protected $queueDriver;
    protected $queueName;
    public function __construct($queueDriver)
    {
        $this->queueDriver = $queueDriver;
    }

    public function onStart($server)
    {
        parent::onStart($server); // TODO: Change the autogenerated stub
    }

    /**
     * 检查队列数据
     * @param $queueData
     * @return bool
     */
    protected function checkQueueData($queueData)
    {
        return true;
    }

    /**
     * 路由
     * @return array
     */
    public function router()
    {
        if (isset($this->queueName) && strpos($this->queueName, '/' ) !== false){
            $urlParam = explode('/', $this->queueName);
            return ['controller' => $urlParam[0], 'view' => $urlParam[1]];
        }else{
            return ['controller' => 'Home', 'view'=>'index'];
        }
    }
    /**
     * 接收数据
     * @param $server
     * @param $client_id
     * @param $from_id
     * @param $data
     */
    public function onReceive($server, $client_id, $from_id, $data)
    {
        $receiveData = json_decode($data, true);
        /*var_dump($receiveData);
        echo "\n";*/
        try {
            $this->checkQueueData($receiveData);
            $this->server->task($receiveData);
            //$this->server->finish(['code' => 1000, 'msg'=>'操作成功']);
        } catch (\Exception $e) {
            $this->server->finish(['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 执行任务
     * @param $server
     * @param $taskId
     * @param $fromId
     * @param $data
     */
    public function onTask($server, $taskId, $fromId, $data)
    {
        var_dump($data);
        echo "\n";
        try {
            $this->checkQueueData($data);
            $this->queueName = $data['queueName'];
            Swoole::$php->router([$this, 'router']);
            Swoole::$php->request = $data['recData'];
            $rs = Swoole::$php->runMVC();
            $this->server->finish("处理完成 -> OK");
        } catch (Exception $e) {
            $this->log($e->getCode().':'.$e->getMessage());
        }
    }

    /**
     * 完成处理
     * @param $server
     * @param $taskId
     * @param $data
     */
    public function onFinish($server, $taskId, $data)
    {
        echo "AsyncTask[$taskId] Finish:" . PHP_EOL;
    }
}
//设置PID文件的存储路径
Swoole\Network\Server::setPidFile(WEBPATH . '/server/pid/queueServer.pid');
Swoole\Error::$echo_html = false;
Swoole\Network\Server::addOption('q|queue?', '队列名称');
/**
 * 显示Usage界面
 * php queueServer.php start|stop|reload
 */
Swoole\Network\Server::start(function ($options) {
    $queueName = isset($options['queue']) && $options['queue'] ? $options['queue'] : 'queue';
    $queueDriver = new Swoole\Queue([
        'host'    => "172.17.0.3",
        'port'    => 6379,
        'password' => '',
        'timeout' => 0.25,
        'pconnect' => false,
        'key' => 'swoole:'.$queueName,
    ], 'Swoole\Queue\Redis');
    $AppSvr = new QueueServer($queueDriver);
    $AppSvr->setLogger(new \Swoole\Log\EchoLog(true));
    $server = Swoole\Network\Server::autoCreate('0.0.0.0', 9443);
    $server->setProtocol($AppSvr);
    $server->run([
        'worker_num' => 100,
        'max_request' => 1,
        'ipc_mode' => 2,
        'task_worker_num' => 100,
    ]);
});
<?php

class Worker
{
    //监听socket
    protected $socket = null;
    //连接事件回调
    public $onConnect = null;
    public $reusePort = 1;
    //接收消息事件回调
    public $onMessage = null;
    public $workerNum = 3; //子进程个数
    public $allSocket; //存放所有socket
    public $addr;
    public function __construct($socket_address)
    {
        $this->addr = $socket_address; //监听地址+端口
    }

    public function start()
    {
        $this->fork(); //获取配置文件
    }

    public function fork()
    {
        for ($i = 0; $i < $this->workerNum; $i++) {
            $pid = pcntl_fork(); //创建成功会返回子进程id
            if ($pid < 0) {
                exit('创建失败');
            } else if ($pid > 0) { //父进程空间，返回子进程id
                #TODO
            } else { //返回为0子进程空间
                $this->accept(); //子进程负责接收客户端请求
                exit;
            }
        }
        //放在父进程空间，结束的子进程信息，阻塞状态
        $status = 0;
        for ($i = 0; $i < $this->workerNum; $i++) {
            $pid = pcntl_wait($status);
        }
    }
    public function accept()
    {
        $opts = array(
            'socket' => array(
                'backlog' => 10240, //成功建立socket连接的等待个数
            ),
        );

        $context = stream_context_create($opts);
        //开启多端口监听,并且实现负载均衡
        stream_context_set_option($context, 'socket', 'so_reuseport', 1);
        stream_context_set_option($context, 'socket', 'so_reuseaddr', 1);
        $this->socket = stream_socket_server($this->addr, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        //第一个需要监听的事件(服务端socket的事件),一旦监听到可读事件之后会触发
        swoole_event_add($this->socket, function ($fd) {
            $clientSocket = stream_socket_accept($fd);
            //触发事件的连接的回调
            if (!empty($clientSocket) && is_callable($this->onConnect)) {
                call_user_func($this->onConnect, $clientSocket);
            }
            //监听客户端可读
            swoole_event_add($clientSocket, function ($fd) {
                //从连接当中读取客户端的内容
                $buffer = fread($fd, 1024);
                //如果数据为空，或者为false,不是资源类型
                if (empty($buffer)) {
                    if (!is_resource($fd) || feof($fd)) {
                        //触发关闭事件
                        fclose($fd);
                    }
                }
                //正常读取到数据,触发消息接收事件,响应内容
                if (!empty($buffer) && is_callable($this->onMessage)) {
                    call_user_func($this->onMessage, $fd, $buffer);
                }
            });
        });
    }

}

$worker = new Worker('tcp://0.0.0.0:9810');

//开启多进程的端口监听
$worker->reusePort = true;

//连接事件
$worker->onConnect = function ($fd) {
    echo '连接事件触发', (int) $fd, PHP_EOL;
};

//消息接收
$worker->onMessage = function ($conn, $message) {
    $content = "你好你好";
    $http_resonse = "HTTP/1.1 200 OK\r\n";
    $http_resonse .= "Content-Type: text/html;charset=UTF-8\r\n";
    $http_resonse .= "Connection: keep-alive\r\n"; //连接保持
    $http_resonse .= "Server: php socket server\r\n";
    $http_resonse .= "Content-length: " . strlen($content) . "\r\n\r\n";
    $http_resonse .= $content;
    fwrite($conn, $http_resonse);
};

$worker->start(); //启动

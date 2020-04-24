<?php
class Worker
{
    //监听socket
    protected $socket = null;
    //连接事件回调
    public $onConnect = null;
    //接收消息事件回调
    public $onMessage = null;
    public $workerNum = 4; //子进程个数
    public $allSocket; //存放所有socket

    public function __construct($socket_address)
    {
        //监听地址+端口
        $this->socket = stream_socket_server($socket_address);
        stream_set_blocking($this->socket, 0); //设置非阻塞
        $this->allSocket[(int) $this->socket] = $this->socket;
    }
    public function start()
    {
        //获取配置文件
        $this->fork();
    }

    public function fork()
    {
        $this->accept(); //子进程负责接收客户端请求
    }
    public function accept()
    {
        //创建多个子进程阻塞接收服务端socket
        while (true) {
            $write = $except = [];
            //需要监听socket
            $read = $this->allSocket;
            // var_dump($read);
            //状态谁改变
            stream_select($read, $write, $except, 60);
            //怎么区分服务端跟客户端
            foreach ($read as $index => $val) {
                var_dump($val);
                //当前发生改变的是服务端，有连接进入
                if ($val === $this->socket) {
                    $clientSocket = stream_socket_accept($this->socket); //阻塞监听
                    //触发事件的连接的回调
                    if (!empty($clientSocket) && is_callable($this->onConnect)) {
                        call_user_func($this->onConnect, $clientSocket);
                    }
                    $this->allSocket[(int) $clientSocket] = $clientSocket;
                } else {
                    //从连接当中读取客户端的内容
                    $buffer = fread($val, 1024);
                    //如果数据为空，或者为false,不是资源类型
                    if (empty($buffer)) {
                        if (feof($val) || !is_resource($val)) {
                            //触发关闭事件
                            fclose($val);
                            unset($this->allSocket[(int) $val]);
                            continue;
                        }
                    }
                    //正常读取到数据,触发消息接收事件,响应内容
                    if (!empty($buffer) && is_callable($this->onMessage)) {
                        call_user_func($this->onMessage, $val, $buffer);
                    }
                }
            }
        }
    }
}

$worker = new Worker('tcp://0.0.0.0:9805');

//连接事件
$worker->onConnect = function ($fd) {
    //echo '连接事件触发',(int)$fd,PHP_EOL;
};

//消息接收
$worker->onMessage = function ($conn, $message) {
    //事件回调当中写业务逻
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

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
    public function __construct($socket_address)
    {
        //监听地址+端口
        $this->socket = stream_socket_server($socket_address);
    }
    public function start()
    {
        //获取配置文件
        $this->fork(); //用来创建多个助教老师，创建多个子进程负责接收请求的
    }

    public function fork()
    {
        for ($i = 0; $i < $this->workerNum; $i++) {
            $pid = pcntl_fork(); //创建成功会返回子进程id
            if ($pid < 0) {
                exit('创建失败');
            } else if ($pid > 0) {
                //父进程空间，返回子进程id
            } else { //返回为0子进程空间
                $this->accept(); //子进程负责接收客户端请求
            }
        }
        //放在父进程空间，结束的子进程信息，阻塞状态
        $status = 0;
        $pid = pcntl_wait($status);
        echo "子进程回收了:$pid" . PHP_EOL;
    }

    public function accept()
    {

        //创建多个子进程阻塞接收服务端socket
        while (true) {
            $clientSocket = stream_socket_accept($this->socket); //阻塞监听
            var_dump(posix_getpid());
            //触发事件的连接的回调
            if (!empty($clientSocket) && is_callable($this->onConnect)) {
                call_user_func($this->onConnect, $clientSocket);
            }
            //从连接当中读取客户端的内容
            $buffer = fread($clientSocket, 65535);
            //正常读取到数据,触发消息接收事件,响应内容
            if (!empty($buffer) && is_callable($this->onMessage)) {
                call_user_func($this->onMessage, $clientSocket, $buffer);
            }
            fclose($clientSocket); //必须关闭，子进程不会释放不会成功拿下进入accpet
        }

    }

}

$worker = new Worker('tcp://0.0.0.0:9800');

//连接事件
$worker->onConnect = function ($fd) {
    echo '连接事件触发', (int) $fd, PHP_EOL;
};

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

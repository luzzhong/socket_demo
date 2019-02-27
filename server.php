<?php
 class Worker{
     //监听socket
     protected $socket = NULL;
     //连接事件回调
     public $onConnect = NULL;
     //接收消息事件回调
     public $onMessage = NULL;
     public function __construct($socket_address) {
         //监听地址+端口
         $this->socket=stream_socket_server($socket_address);
     }

     public function run() {
         while (true){ //循环监听
             $client = stream_socket_accept($this->socket);//在服务端阻塞监听
             if(!empty($client) && is_callable($this->onConnect)){//socket连接成功并且是我们的回调
                 //触发事件的连接的回调
                 call_user_func($this->onConnect,$client);
             }
             //从连接中读取客户端内容
             $buffer=fread($client,65535);//参数2：在缓冲区当中读取的最大字节数
             //正常读取到数据。触发消息接收事件，进行响应
             if(!empty($buffer) && is_callable($this->onMessage)){
                 //触发时间的消息接收事件
                 call_user_func($this->onMessage,$this,$client,$buffer);//传递到接收消息事件》当前连接、接收到的消息
             }
         }
     }
     //响应http请求
     public function  send($conn,$content){
         $http_resonse = "HTTP/1.1 200 OK\r\n";
         $http_resonse .= "Content-Type: text/html;charset=UTF-8\r\n";
         $http_resonse .= "Connection: keep-alive\r\n";
         $http_resonse .= "Server: php socket server\r\n";
         $http_resonse .= "Content-length: ".strlen($content)."\r\n\r\n";
         $http_resonse .= $content;
         fwrite($conn, $http_resonse);
     }
 }



$worker = new Worker('tcp://0.0.0.0:9810');

$worker->onConnect = function ($data) {
    echo '连接事件：', $data, PHP_EOL;
};
$worker->onMessage = function ($server,$conn, $message) {
    echo '来自客户端消息:',$message,PHP_EOL;
    $server->send($conn,'来自服务端消息');
};
$worker->run();



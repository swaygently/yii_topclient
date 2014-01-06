<?php

/**
 * 主动通知接口消息接收
 * 
 * @author swaygently@gmail.com 
 * 
 * <code>
 * $service=new TopClient_Notify;
 * $service->getLogger()->attachEventHandler('onFlush', 'handler');
 * $service->run();
 * 
 * function handler($event)
 * {
 *   $logs=$event->sender->getLogs();
 *   foreach($logs as $log)
 *   {
 *      print 'message:   ' . $log[0];
 *      print 'level:     ' . $log[1];
 *      print 'category:  ' . $log[2];
 *      print 'timestamp: ' . $log[3];
 *      print PHP_EOL;
 *   }
 * }
 * </code>
 */
require_once(dirname(__FILE__).'/TopClient.php');
class TopClient_Notify extends CApplicationComponent
{
	//public $gateway_url='http://stream.api.taobao.com/stream';
	
	public $app_key;
	public $app_secret;
	public $is_sandbox=true;
	
	private $_logger;
	private $_socket;
	
	// 连接次数
	private $_connected_count=0;
	// 上次链接时间
	private $_last_connected;
	
	/**
	 * @return $this
	 */
	public function setTopClient($topClient)
	{
		$this->app_key=$topClient->app_key;
		$this->app_secret=$topClient->app_secret;
		$this->is_sandbox=$topClient->is_sandbox;
		return $this;
	}
	
	/**
	 * @return CLogger
	 */
	public function getLogger()
	{
		// 启用  logger 来接受消息
		if($this->_logger===null)
		{
			$this->_logger=Yii::createComponent(array(
				'class'=>'CLogger',
				'autoFlush'=>1,
			));
		}
		return $this->_logger;
	}
	
	/**
	 * @throws CException
	 */
	public function run()
	{
		$stream=$this->connect();
		while(!feof($stream))
		{
		    $str=fgets($stream,1024);
			if(YII_DEBUG || Yii::app() instanceof CConsoleApplication)
				print '['.date('c').'] '.$str;
			if($str)
				$str=trim($str);
			if($str!='' && $str[0]=='{')
			{
				// $message=serialize(array('app_key'=>$this->app_key,'message'=>$str));
				$this->getLogger()->log($this->app_key.'@'.$str,CLogger::LEVEL_INFO,'taobao.increment.message');
			}
			$info=stream_get_meta_data($stream);
			if($info['timed_out'])
			{
				// 超时后关闭连接，一般24小时后淘宝服务器端会主动超时
				$this->close($stream);
				
				// 1小时内最多尝试重连5次，避免由于网络问题的不断重连
				if(time()-$this->_last_connected > 3600)
					$this->_connected_count=0;
				
				// 在淘宝主动断开连接后重连
				if($this->_connected_count<5)
				{
					if(YII_DEBUG || Yii::app() instanceof CConsoleApplication)
						print 'reconnect [' . $this->_connected_count . '] ...' . PHP_EOL;
					$stream=$this->connect();
				}
				else
				{
					throw new CException('connection time out');
				}
			}
		}
		if(YII_DEBUG || Yii::app() instanceof CConsoleApplication)
			print 'EOF' . PHP_EOL;
		$this->close($stream);
	}
	
	/**
	 * 建立连接
	 * 
	 * @throws CException
	 */
	public function connect()
	{
		// 重连时关闭上一个连接
		if(is_resource($this->_socket))
			fclose($this->_socket);
		
		$gateway_url=$this->is_sandbox ? 'http://stream.api.tbsandbox.com/stream' : 'http://stream.api.taobao.com/stream';
		$p=parse_url($gateway_url);
		$this->_socket=@fsockopen($p['host'], 80, $errno, $errstr, 30);
		if(!$this->_socket)
		    throw CException("can not open socket {$errstr}, {$errno}");
		else
		{
			$params=array(
	     		'app_key'=>$this->app_key,
				'v'=>'2.0',
				'format'=>'json',
				'sign_method'=>'md5',
				'timestamp'=>date('Y-m-d H:i:s'),
			);
			$params['sign']=TopClient::sign($params,$this->app_secret);
			$data=http_build_query($params);
			
			// 
			fwrite($this->_socket, "POST {$p['path']} HTTP/1.1\r\n");
			fwrite($this->_socket, "Host: localhost\r\n");
			fwrite($this->_socket, "Content-type: application/x-www-form-urlencoded\r\n");
			fwrite($this->_socket, "Content-length: " . strlen($data) . "\r\n");
			fwrite($this->_socket, "Accept: */*\r\n");
			fwrite($this->_socket, "Connection: Keep-Alive\r\n");
			fwrite($this->_socket, "\r\n");
			fwrite($this->_socket, "$data\r\n");
			fwrite($this->_socket, "\r\n");
			
			// 设置读取超时，如果1分钟都没有读到心跳包，说明网络有问题了，需要重新连接
			stream_set_timeout($this->_socket, 60);
			
			Yii::trace('connented to '. $gateway_url);
			$this->_connected_count++;
			$this->_last_connected=time();
			return $this->_socket;
		}
	}
	
	/**
	 * 关闭连接
	 */
	public function close($socket=null)
	{
		if($socket!==null)
			fclose($socket);
		else if(is_resource($this->_socket))
			fclose($this->_socket);
	}

	/**
	 * __destruct
	 */
	public function __destruct()
	{
		$this->close();
	}
}

/**
 * 处理通知的一个简单例子
 */
class TopClient_Notify_Handler
{
	public function handle($event)
	{
		$logs=$event->sender->getLogs();
		foreach($logs as $log)
		{
			$response=@json_decode($log[0]);
			if(is_object($response) && isset($response->packet))
			{
				$msg=$response->packet->msg;
				$code=$response->packet->code;
				if($code==202)
					print '业务信息';
				else if($code==203)
					print '消息丢弃';
				else if($code==200 || $code==201)
					print '心跳包';
				else
					print '断开记录';
			}
		}
	}
}
yii_topclient
=============

Yii的淘宝API客户端组件

特性
-------------
* 支持多个app_key配置
* 支持沙箱
* 支持淘宝API的通知接口

使用
-------------

假设已经有一个基于yii framework的项目（可以使用yiic webapp {project_path}来生成一个项目）：

### 配置

在config/main.php 和 config/console.php 中添加组件：

	'components'=>array(
		'topClient'=>array(
			'class'=>'ext.topclient.TopClient_Configure',
			'appKeys'=>array(
				array(
					'app_key'=>'1021644418',
					'app_secret'=>'sandboxe18ada1573da2d14e81b3db1f',
					'is_sandbox'=>true,
				),
			),
		),
		
		// ....
	),


### 客户端使用

    $client=Yii::app()->topClient->setAppKey('1021644418');
    $response=$client->execute('taobao.shopcats.list.get');
    if($response->hasErrors())
        print $response;
    else
        print_r($response);
    
    
### 淘宝通知接口使用

在 protected/commands/下创建 TopClientCommand.php 

    class TopClientCommand extends CConsoleCommand
    {
	    public function actionServe()
	    {
		    $client=Yii::app()->getComponent('topClient')->setAppKey('1021644418');
		
		    $handler=new TopClient_Notify_Handler;
		
		    $service=new TopClient_Notify;
		    $service->setTopClient($client);
		    $service->getLogger()->attachEventHandler('onFlush', array($handler,'handle'));
		    $service->run();
	    }
    }

    
然后在命令行下运行：

    protected/yiic topclient serve
    
然后该server会一直运行，接受淘宝通知接口发送过来的通知（比如订单生成、付款、发货，商品修改等等）。

不出意外的话，你起码会在console里看到如下打印结果：

    [2014-01-06T17:16:22+08:00] HTTP/1.1 200 OK
    [2014-01-06T17:16:22+08:00] Date: Mon, 06 Jan 2014 09:16:23 GMT
    [2014-01-06T17:16:22+08:00] Transfer-Encoding: chunked
    [2014-01-06T17:16:22+08:00] Server: Jetty(7.1.6.v20100715)
    [2014-01-06T17:16:22+08:00]
    [2014-01-06T17:16:22+08:00] 49
    [2014-01-06T17:16:22+08:00] {"packet":{"code":200,"msg":"connected vsandbox067169.cm4.tbsite.net"}}
    [2014-01-06T17:16:22+08:00]
    [2014-01-06T17:16:23+08:00] 21
    [2014-01-06T17:16:23+08:00] {"packet":{"code":201,"msg":0}}
    [2014-01-06T17:16:23+08:00]

当然，这里自带的消息处理句柄（见源码，TopClient_Notify_Handler::handle）只是把消息打印出来，你可以自定义你的消息处理机制。比如，分发一个Gearman任务。 

PS:如果需要让服务在后台一直运行的话，可以用nohup 结合 & 来执行命令：

    nohup path_to_project/protected/yiic topclient serve >> /var/log/taobao_notify_serve.log 2>&1 &

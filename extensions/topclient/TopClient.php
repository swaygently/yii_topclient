<?php

/**
 * 淘宝 APi php客户端 
 *
 * @author swaygently@gmail.com
 */
class TopClient extends CComponent
{
	public $app_key;
	public $app_secret;
	public $session_key;
	public $is_sandbox=true;
	
	public $callback_uri='http://treasure.aihuishou.com/index.php?r=taobao/authorize';
	
	public function setConfig($name, $value=null)
	{
		if(is_object($name))
		{
			$this->app_key=$name->app_key;
			$this->app_secret=$name->app_secret;
			$this->session_key=$name->session_key;
			$this->is_sandbox=$name->is_sandbox;
		}
		else if(is_array($name))
		{
			foreach(array('app_key','app_secret','session_key','is_sandbox') as $key)
				if(isset($name[$key]))
					$this->{$key}=$name[$key];
		}
		else
			$this->{$name}=$value;
		
		return $this;
	}
	
	public function getIsSandbox()
	{
		return $this->is_sandbox;
	}
	
	public static function sign($params, $secret_key)
	{
    	ksort($params);
    	$sign=$secret_key;
    	foreach($params as $k => $v)
    	{
        	if("@"!=substr($v, 0, 1))
            	$sign.="$k$v"; 
    	}
    	$sign.=$secret_key;
    	return strtoupper(md5($sign));
	}
	
	/**
	 * 执行 Top api，并取回结果 
	 *
	 * @return TopClient_Response
	 * @throws CException
	 */
	public function execute($method, $params=array(), $session=null)
	{
		// 系统参数
		$config=array(
			//'method'=>$method,
			'timestamp'=>date('Y-m-d H:i:s'),
			'format'=>'json',
			'app_key'=>$this->app_key,
			'v'=>'2.0',
			'sign_method'=>'md5',
		);
		if($session)
			$config['session']=$session;
		else if($this->session_key)
			$config['session']=$this->session_key;
		
		if(is_array($method))
			$params=array_merge($config,$method);
		else
		{
			$config['method']=$method;
			$params=array_merge($config,$params);
		}
		
		// sign
		ksort($params);
		$sign=$this->app_secret;
		foreach($params as $k => $v)
		{
			if("@"!=substr($v,0,1))
				$sign.="$k$v";
		}
		$sign=strtoupper(md5($sign.$this->app_secret));
		
		$params['sign']=$sign;
		// no sign, only oauth, not support client-side flow 
		// $this->getIsSandbox() ? 'https://gw.api.tbsandbox.com/router/rest' : 'https://eco.taobao.com/router/rest';
		// $config=array(
		//   'method', 'format'=>'json', 'access_token'=>'', 'v'=>'2.0',
		//);
		$url= $this->getIsSandbox() ? 'http://gw.api.tbsandbox.com/router/rest' : 'http://gw.api.taobao.com/router/rest';
		$request=new TopClient_Request_CURL;
		$content=$request->fetch($url,$params);
		if($content===false)
			throw CException('fetch error');
		return new TopClient_Response($content);
	}
	
	/**
	 * 定时任务
	 */
	public function schedule($method, $params=array(), $session, $schedule=null, $callbackurl=null)
	{
		$config=array(
			#'method'=>$method,
			'timestamp'=>date('Y-m-d H:i:s'),
			'v'=>'2.0',
			'app_key'=>$app_key,
			'format'=>'json',
			'sign_method'=>'md5',
		);
		
		if($this->$session)
			$config['session']=$session;
		
		if(is_array($method))
			$params=array_merge($config,$method);
		else
		{
			$config['method']=$method;
			$config['schedule']=$schedule;
			if($callbackurl)
				$config['callbackurl']=$callbackurl;
			$params=array_merge($config,$params);
		}
		$params['sign']=self::sign($params, $this->secret_key);
		
		$url=$this->getIsSandbox() ? 'http://gw.api.tbsandbox.com/schedule/2.0/json' : 'http://gw.api.taobao.com/schedule/2.0/json';
		$request=new TopClient_Request_CURL;
		$content=$request->fetch($url,$params);
		if($content===false)
			throw CException('fetch error');
		return new TopClient_Response($content);
	}
	 
	/**
	 * 获取授权URL
	 * 
	 * @return string
	 */
	public function getAuthorizeUrl($type='Server-side')
	{
		$url=$this->is_sandbox ? 'https://oauth.tbsandbox.com/authorize' : 'https://oauth.taobao.com/authorize';
		switch($type)
		{
			case 'Client-side':
				return $url.='?response_type=token&client_id='.$this->app_key.'&redirect_uri='.$this->callback_uri.'&state=$view=web';
				break;
								
			case 'Server-side':
			default:
				return $url.='?response_type=code&client_id='.$this->app_key.'&redirect_uri='.$this->callback_uri.'&state=&view=web';
		}
	}
	
	/**
	 * 登录帐号退出，这个退出流程目前只支持web访问，起到的作用是清除taobao.com的cookie，并不是取消用户的授权。在WAP上访问无效
	 *
	 * @return string
	 */
	public function getLogoutUrl()
	{
		$url=$this->is_sandbox  ? 'https://oauth.tbsandbox.com/logoff' : 'https://oauth.taobao.com/logoff';
		return $url.'?client_id='.$this->app_key.'&view=web';
	}
	
	/**
	 * 使用RequestToken换取 AccesToken 
	 *
	 *     code
	 *     error
	 *     error_description
	 * 
	 * @return stdClass
	 *     access_token
	 *     token_type=Bearer
	 *     expires_in
	 *     refresh_token
	 *     re_expires_in
	 *     r1_expires_in
	 *     r2_expires_in
	 *     w1_expires_in
	 *     w2_expires_in
	 *     taobao_user_nick
	 *     taobao_user_id
	 *     sub_taobao_user_id
	 *     sub_taobao_user_nick
	 */
	public function getAccessToken($code)
	{
		$params=array(
			'client_id'=>$this->app_key,
			'client_secret'=>$this->app_secret,
			'grant_type'=>'authorization_code',
			'code'=>$code,
			'redirect_uri'=>$this->callback_uri,
			'state'=>'',
			'view'=>'web'
		);
		$url=$this->is_sandbox ? 'https://oauth.tbsandbox.com/token' : 'https://oauth.taobao.com/token';
		$request=new TopClient_Request_CURL;
		$response=$request->fetch($url,$params);
		if(empty($response))
			throw new CException('取得AccessToken的内容为空');
		$result=json_decode($response);
		return $result;
	}
	
	/**
	 * 刷新 AccessToken
	 * 
	 * @return stdClass
	 *     w2_expires_in
	 *     taobao_user_id
	 *     taobao_user_nick
	 *     w1_expires_in
	 *     re_expires_in
	 *     r2_expires_in
	 *     expires_in
	 *     token_type
	 *     refresh_token
	 *     access_token
	 *     r1_expires_in
	 */
	public function refreshToken($refresh_token)
	{
		$url=$this->is_sandbox ? 'https://oauth.tbsandbox.com/token' : 'https://oauth.taobao.com/token';
		$params=array(
			'client_id'=>$this->app_key,
			'client_secret'=>$this->app_secret,
			'grant_type'=>'refresh_token',
			'refresh_token'=>$refresh_token,
			//'state'=>'',
			//'view'=>'web'
		);
		$request=new TopClient_Request_CURL;
		$content=$request->fetch($url,$params);
		if(empty($content))
			throw new CException('refreshToken: return conent is empty');
		$result=json_decode($content);
		if(!is_object($result))
			throw new CException('refreshToken: can not parse return content '. $content);
		return $result;
	}
}

/**
 * curl适配器，默认使用
 */
class TopClient_Request_CURL
{
	public function fetch($url,$data)
	{
	    $ch=curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
	    curl_setopt($ch, CURLOPT_POST, true);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);  // 连接超时
	    curl_setopt($ch, CURLOPT_TIMEOUT, 60);  // 读取超时
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    $result=curl_exec($ch);
	    if(curl_errno($ch))
			throw new CException(curl_error($ch),0);
		else
	    {
	        $http_code=curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if(200 != $http_code)
				throw new CException($result, $http_code);
	    }
	    curl_close($ch);
		return $result;
	}
}

/**
 * socket适配器，TODO，未经测试，默认不使用
 */
class TopClient_Request_Socket
{
	public function fetch($url,$data)
	{
		$p=parse_url($url);
		$socket=@fsockopen($p['host'], 80, $errno, $errstr, 30);  // 连接超时
		if(!$socket)
		    throw CException($errstr, $errno);
		$data=http_build_query($params);
		fwrite($socket, "POST {$p['path']} HTTP/1.0\r\n");
		fwrite($socket, "Host: localhost\r\n");
		fwrite($socket, "Content-type: application/x-www-form-urlencoded\r\n");
		fwrite($socket, "Content-length: " . strlen($data) . "\r\n");
		fwrite($socket, "Accept: */*\r\n");
		fwrite($socket, "Connection: close\r\n");
		fwrite($socket, "\r\n");
		fwrite($socket, "$data\r\n");
		fwrite($socket, "\r\n");
		stream_set_timeout($socket, 60);  // 读取超时
		$result='';
		while(!feof($socket))
		    $result.=fgets($socket,1024);
		fclose($socket);
		return $result;
	}
}

/**
 * 返回结果
 * 
 * <code>
 * $response=$client->execute('taobao.shopcats.list.get');
 * if($response->hasErrors())
 *     print $response;
 * else
 *     print_r($response);
 * </code>
 */
class TopClient_Response
{
	private $_response;
	private $_struct;
	
	public $errors=array();
	
	public function __construct($content)
	{
		$this->_response=$content;
		$struct=CJSON::decode($content,false);
		if(is_object($struct))
		{
			$vars=get_object_vars($struct);
			if('error_response'==key($vars))
			{
				$this->errors[$vars['error_response']->code]=$vars['error_response']->msg;
			}
			$this->_struct=current($vars);
		}
		else
			$this->errors[]='返回的结果不能解析';
	}
	
	public function __get($name)
	{
		if(isset($this->_struct->{$name}))
			return $this->_struct->{$name};
		return null;
	}
	
	public function __isset($name)
	{
		return isset($this->_struct->{$name});
	}
	
	public function __toString()
	{
		return $this->_response;
	}
	
	/**
	 * @return boolean
	 */
	public function hasErrors()
	{
		return !empty($this->errors);
	}
	
	/**
	 * @return array
	 */
	public function getErrors()
	{
		return $this->errors;
	}
}

<?php

/**
 * 淘宝API客户端，配置器。 可以配 
 *
 * @author swaygently@gmail.com
 */
require_once(dirname(__FILE__).'/TopClient.php');
require_once(dirname(__FILE__).'/TopClient_Notify.php');
class TopClient_Configure extends CApplicationComponent implements IteratorAggregate, Countable
{
	private $_apps=array();
	private $_appConfig=array();
	
	public function app($appKey=null)
	{
		if($appKey===null && !empty($this->_appConfig))
			return $this->app($this->_appConfig[0]['app_key']);
		else if(isset($this->_apps[$appKey]))
			return $this->_apps[$appKey];
		else if(!empty($this->_appConfig))
		{
			foreach($this->_appConfig as $config)
			{
				if($config['app_key']==$appKey)
				{
					$app=new TopClient;
					$app->setConfig($config);
					$this->_apps[$appKey]=$app;
					return $app;
				}
			}
		}
		
		throw new CException('invalid appKey: ' .$appKey);
	}
	
	public function setAppKeys($appKeys)
	{
		$this->_appConfig=$appKeys;
		return $this;
	}
	
	/**
	 * @param string
	 * @return TopClient
	 */
	public function setAppKey($appKey)
	{
		return $this->app($appKey);
	}
	
	public function getIterator()
	{
		foreach($this->_appConfig as $config)
		{
			if(!isset($this->_apps[$config['app_key']]))
			{
				$app=new TopClient;
				$app->setConfig($config);
				$this->_apps[$config['app_key']]=$app;
			}
		}
		
		return new CMapIterator($this->_apps);
	}
	
	public function count()
	{
		return count($this->_appConfig);
	}
}

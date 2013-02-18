<?php
/**
 * User: Zachary Tong
 * Date: 2/12/13
 * Time: 7:37 PM
 */

namespace sherlock\requests;

use sherlock\components\queries;
use sherlock\common\exceptions;
use sherlock\wrappers;


class IndexRequest extends Request
{

	/**
	 * @var array
	 */
	protected $params;

	public function __construct($node, $index)
	{
		if (!isset($node))
			throw new \sherlock\common\exceptions\BadMethodCallException("Node argument required for IndexRequest");
		if (!isset($index))
			throw new \sherlock\common\exceptions\BadMethodCallException("Index argument required for IndexRequest");

		if (!is_array($node))
			throw new \sherlock\common\exceptions\BadMethodCallException("First parameter must be an node array");

		if(!is_array($index))
			$this->params['index'][] = $index;
		else
			$this->params['index'] = $index;

		$this->params['indexSettings'] = array();
		$this->params['indexMappings'] = array();

		parent::__construct($node);
	}

	public function __call($name, $args)
	{
		$this->params[$name] = $args[0];
		return $this;
	}


	/**
	 * ---- Settings / Parameters ----
	 * Various settings and parameters to be set before invoking an action
	 * Returns $this
	 *
	 */

	/**
	 * @param string $index indices to operate on
	 * @param string $index,... indices to operate on
	 * @return SearchRequest
	 */
	public function index($index)
	{
		$this->params['index'] = array();
		$args = func_get_args();
		foreach($args as $arg)
		{
			$this->params['index'][] = $arg;
		}
		return $this;
	}

	/**
	 * @param string $type indices to operate on
	 * @param string $type,... indices to operate on
	 * @return SearchRequest
	 */
	public function type($type)
	{
		$this->params['type'] = array();
		$args = func_get_args();
		foreach($args as $arg)
		{
			$this->params['type'][] = $arg;
		}
		return $this;
	}


	/**
	 * @param array|\sherlock\components\MappingInterface|bool $mapping,...
	 * @throws \sherlock\common\exceptions\BadMethodCallException
	 * @return \sherlock\requests\IndexRequest
	 */
	public function mappings($mapping)
	{
		$append = false;
		$args = func_get_args();
		foreach ($args as $arg)
		{
			//if there is a "false" bool in the arg list, append instead of overwrite
			//Defaults to overwrite
			if (is_bool($arg) && $arg == true)
			{
				$append = true;
				break;
			}
		}
		if ($append == false)
			$this->params['indexMappings'] = array();

		foreach ($args as $arg)
		{
			if ($arg instanceof \sherlock\components\MappingInterface)
				$this->params['indexMappings'][] = $arg->toArray();
			elseif (is_array($arg))
				$this->params['indexMappings'][] = $arg;
			elseif (is_bool($arg))
				continue;
			else
				throw new \sherlock\common\exceptions\BadMethodCallException("Arguments must be an array or a Mapping Property.");

		}



		return $this;
	}

	/**
	 * @param array|\sherlock\wrappers\IndexSettingsWrapper $settings
	 * @param bool $merge
	 * @throws \sherlock\common\exceptions\BadMethodCallException
	 * @return IndexRequest
	 */
	public function settings($settings, $merge = false)
	{
		if ($settings instanceof \sherlock\wrappers\IndexSettingsWrapper)
			$newSettings = $settings->toArray();
		else if (is_array($settings))
			$newSettings = $settings;
		else
			throw new \sherlock\common\exceptions\BadMethodCallException("Unknown parameter provided to settings(). Must be array of settings or IndexSettingsWrapper.");


		if ($merge)
			$this->params['indexSettings'] = array_merge($this->params['indexSettings'], $newSettings);
		else
			$this->params['indexSettings'] = $newSettings;


		return $this;
	}




	/*
	 * ---- Actions -----
	 * Actions are applied to the index through an HTTP request, and return a response
	 *
	 */

	/**
	 * @return \sherlock\responses\IndexResponse
	 * @throws \sherlock\common\exceptions\RuntimeException
	 */
	public function delete()
	{
		\Analog\Analog::log("IndexRequest->execute() - ".print_r($this->params, true), \Analog\Analog::DEBUG);

		if (!isset($this->params['index']))
			throw new \sherlock\common\exceptions\RuntimeException("Index cannot be empty.");

		$index = implode(',', $this->params['index']);

		$uri = 'http://'.$this->node['host'].':'.$this->node['port'].'/'.$index;

		//required since PHP doesn't allow argument differences between
		//parent and children under Strict
		$this->_uri = $uri;
		$this->_data = null;
		$this->_action = 'delete';

		$ret =  parent::execute();
		return $ret;
	}
	/**
	 * @return \sherlock\responses\IndexResponse
	 * @throws \sherlock\common\exceptions\RuntimeException
	 */
	public function create()
	{
		\Analog\Analog::log("IndexRequest->create() - ".print_r($this->params, true), \Analog\Analog::DEBUG);

		if (!isset($this->params['index']))
			throw new \sherlock\common\exceptions\RuntimeException("Index cannot be empty.");

		$index = implode(',', $this->params['index']);

		$uri = 'http://'.$this->node['host'].':'.$this->node['port'].'/'.$index;




		//Final JSON should be object properties, not an array.  So we need to iterate
		//through the array members and merge into an associative array.
		$mappings = array();
		foreach($this->params['indexMappings'] as $mapping)
		{
			$mappings = array_merge($mappings, $mapping);
		}
		$body = array("settings" => $this->params['indexSettings'],
						"mappings" => $mappings);


		//force JSON object when encoding because we may have empty parameters
		$this->_data = json_encode($body, JSON_FORCE_OBJECT);
		$this->_action = 'put';
		$this->_uri = $uri;

		/**
		 * @var \sherlock\responses\IndexResponse
		 */
		$ret =  parent::execute();
		return $ret;
	}

	/**
	 * @todo allow updating settings of all indices
	 *
	 * @return \sherlock\responses\IndexResponse
	 * @throws \sherlock\common\exceptions\RuntimeException
	 */
	public function updateSettings()
	{
		\Analog\Analog::log("IndexRequest->updateSettings() - ".print_r($this->params, true), \Analog\Analog::DEBUG);

		if (!isset($this->params['index']))
		{
			\Analog\Analog::log("Index cannot be empty.", \Analog\Analog::ERROR);
			throw new \sherlock\common\exceptions\RuntimeException("Index cannot be empty.");
		}


		$index = implode(',', $this->params['index']);

		$uri = 'http://'.$this->node['host'].':'.$this->node['port'].'/'.$index.'/_settings';
		$body = array("index" => $this->params['indexSettings']);


		$this->_uri = $uri;
		$this->_data = json_encode($body);
		$this->_action = 'put';

		$ret =  parent::execute();
		return $ret;

	}

	/**
	 * @return \sherlock\responses\IndexResponse
	 * @throws \sherlock\common\exceptions\RuntimeException
	 */
	public function updateMapping()
	{
		\Analog\Analog::log("IndexRequest->updateMapping() - ".print_r($this->params, true), \Analog\Analog::DEBUG);

		if (!isset($this->params['index']))
		{
			\Analog\Analog::log("Index cannot be empty.", \Analog\Analog::ERROR);
			throw new \sherlock\common\exceptions\RuntimeException("Index cannot be empty.");
		}

		if (count($this->params['indexMappings']) > 1)
		{
			\Analog\Analog::log("May only update one mapping at a time.", \Analog\Analog::ERROR);
			throw new \sherlock\common\exceptions\RuntimeException("May only update one mapping at a time.");
		}

		if (!isset($this->params['type']))
		{
			\Analog\Analog::log("Type must be specified.", \Analog\Analog::ERROR);
			throw new \sherlock\common\exceptions\RuntimeException("Type must be specified.");
		}

		if (count($this->params['type']) > 1)
		{
			\Analog\Analog::log("Only one type may be updated at a time.", \Analog\Analog::ERROR);
			throw new \sherlock\common\exceptions\RuntimeException("Only one type may be updated at a time.");
		}


		$index = implode(',', $this->params['index']);
		$uri = 'http://'.$this->node['host'].':'.$this->node['port'].'/'.$index.'/'.$this->params['type'][0].'/_mapping';
		$body = array($this->params['type'][0] => array("properties" => $this->params['indexMappings'][0]));


		$this->_uri = $uri;
		$this->_data = json_encode($body, JSON_FORCE_OBJECT);
		$this->_action = 'put';

		$ret =  parent::execute();
		return $ret;
	}
}
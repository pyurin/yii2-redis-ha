<?php

namespace pyurin\yii\redisHa;

use Yii;

class SentinelConnection {

	public $hostname;
	
	public $masterName;

	public $port = 26379;

	public $connectionTimeout;
	
	/**
	 * Depricated. Redis sentinel does not work on unix socket
	 **/
	public $unixSocket;

	protected $_socket;

	/**
	 * Connects to redis sentinel 
	 **/
	protected function open () {
		if ($this->_socket !== null) {
			return;
		}
		$connection = ($this->unixSocket ?  : $this->hostname . ':' . $this->port);
		Yii::trace('Opening redis sentinel connection: ' . $connection, __METHOD__);
		Yii::beginProfile("Connect to sentinel", __CLASS__);
		$this->_socket = @stream_socket_client($this->unixSocket ? 'unix://' . $this->unixSocket : 'tcp://' . $this->hostname . ':' . $this->port, $errorNumber, $errorDescription, $this->connectionTimeout ? $this->connectionTimeout : ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT);
		Yii::endProfile("Connect to sentinel", __CLASS__);
		if ($this->_socket) {
			if ($this->connectionTimeout !== null) {
				stream_set_timeout($this->_socket, $timeout = (int) $this->connectionTimeout, (int) (($this->connectionTimeout - $timeout) * 1000000));
			}
			return true;
		} else {
			\Yii::warning('Failed opening redis sentinel connection: ' . $connection, __METHOD__);
			$this->_socket = false;
			return false;
		}
	}

	/**
	 * Asks sentinel to tell redis master server
	 * 
	 * @return array|false [host,port] array or false if case of error
	 **/
	function getMaster () {
		if ($this->open()) {
			return Helper::executeCommand('sentinel', [
					'get-master-addr-by-name',
					$this->masterName
			], $this->_socket);
		} else {
			return false;
		}
	}
}
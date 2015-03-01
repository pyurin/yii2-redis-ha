<?php

namespace pyurin\yii\redisHa;

use yii\db\Exception;

class Connection extends \yii\redis\Connection {

	public $sentinels = null;

	public $hostname = null;

	public $port = null;

	/**
	 *
	 * @var resource redis socket connection
	 */
	protected $_socket;

	/**
	 * Returns a value indicating whether the DB connection is established.
	 *
	 * @return boolean whether the DB connection is established
	 */
	public function getIsActive () {
		return $this->_socket !== null;
	}

	/**
	 * Establishes a DB connection.
	 * It does nothing if a DB connection has already been established.
	 *
	 * @throws Exception if connection fails
	 */
	public function open () {
		if ($this->_socket !== null) {
			return;
		}
		if (! $this->sentinels) {
			throw new Exception("Sentinels must be set");
		}
		$this->hostname = $this->unixSocket = $this->port;
		list ($this->hostname, $this->port) = (new SentinelsManager())->discoverMaster($this->sentinels);
		$connection = ($this->unixSocket ?  : $this->hostname . ':' . $this->port) . ', database=' . $this->database;
		\Yii::trace('Opening redis DB connection: ' . $connection, __METHOD__);
		$this->_socket = @stream_socket_client($this->unixSocket ? 'unix://' . $this->unixSocket : 'tcp://' . $this->hostname . ':' . $this->port, $errorNumber, $errorDescription, $this->connectionTimeout ? $this->connectionTimeout : ini_get("default_socket_timeout"));
		if ($this->_socket) {
			if ($this->dataTimeout !== null) {
				stream_set_timeout($this->_socket, $timeout = (int) $this->dataTimeout, (int) (($this->dataTimeout - $timeout) * 1000000));
			}
			if ($this->password !== null) {
				$this->executeCommand('AUTH', [
						$this->password
				]);
			}
			list ($role) = $this->executeCommand("ROLE");
			if ($role != 'master') {
				throw new Exception("Failed connecting to redis - role is `{$role}` but not master");
			}
			if ($this->database) {
				$this->executeCommand('SELECT', [
						$this->database
				]);
			}
			$this->initConnection();
		} else {
			\Yii::error("Failed to open redis DB connection ($connection): $errorNumber - $errorDescription", __CLASS__);
			$message = YII_DEBUG ? "Failed to open redis DB connection ($connection): $errorNumber - $errorDescription" : 'Failed to open DB connection.';
			throw new Exception($message, $errorDescription, (int) $errorNumber);
		}
	}

	public function close () {
		if ($this->_socket !== null) {
			$connection = ($this->unixSocket ?  : $this->hostname . ':' . $this->port) . ', database=' . $this->database;
			\Yii::trace('Closing DB connection: ' . $connection, __METHOD__);
			$this->executeCommand('QUIT');
			stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR);
			$this->_socket = null;
		}
	}

	public function executeCommand ($name, $params = []) {
		$this->open();
		return Helper::executeCommand($name, $params, $this->_socket);
	}
}

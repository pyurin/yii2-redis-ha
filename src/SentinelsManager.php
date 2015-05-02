<?php

namespace pyurin\yii\redisHa;

use yii\db\Exception;

class SentinelsManager {

	/**
	 * Facade function for interraction with sentinel.
	 * 
	 * Connects to sentinels (iterrates them if ones fail) and asks for master server address.
	 * 
	 * @return array [host,port] address of redis master server or throws exception.
	 **/
	function discoverMaster ($sentinels, $masterName) {
		foreach ($sentinels as $sentinel) {
			if (is_scalar($sentinel)) {
				$sentinel = [
						'hostname' => $sentinel
				];
			}
			$connection = new SentinelConnection();
			$connection->hostname = isset($sentinel['hostname']) ? $sentinel['hostname'] : null;
			$connection->masterName = $masterName;
			if (isset($sentinel['port'])) {
				$connection->port = $sentinel['port'];
			}
			$connection->connectionTimeout = isset($sentinel['connectionTimeout']) ? $sentinel['connectionTimeout'] : null;
			$connection->unixSocket = isset($sentinel['unixSocket']) ? $sentinel['unixSocket'] : null;
			$r = $connection->getMaster();
			if (isset($sentinel['hostname'])) {
				$connectionName = "{$connection->hostname}:{$connection->port}";
			} else {
				$connectionName = $connection->unixSocket;
			}
			if ($r) {
				\Yii::info("Sentinel @{$connectionName} gave master addr: {$r[0]}:{$r[1]}", __METHOD__);
				return $r;
			} else {
				\Yii::info("Did not get any master from sentinel @{$connectionName}", __METHOD__);
			}
		}
		throw new \Exception("Master could not be discovered");
	}
}

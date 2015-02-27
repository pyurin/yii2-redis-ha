<?php

namespace pyurin\yii\redisHa;

class SentinelsManager {

	function discoverMaster ($sentinels) {
		foreach ($sentinels as $sentinel) {
			$connection = new SentinelConnection();
			$connection->hostname = isset($sentinel['hostname']) ? $sentinel['hostname'] : null;
			$connection->port = isset($sentinel['port']) ? $sentinel['port'] : null;
			$connection->connectionTimeout = isset($sentinel['connectionTimeout']) ? $sentinel['connectionTimeout'] : null;
			$connection->unixSocket = isset($sentinel['unixSocket']) ? $sentinel['unixSocket'] : null;
			$r = $connection->getMaster();
			if ($r) {
				return $r;
			}
		}
		throw new \Exception("Master could not be discovered");
	}
}
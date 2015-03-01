<?php

namespace pyurin\yii\redisHa;

use yii\db\Exception;

class SentinelsManager {

	function discoverMaster ($sentinels) {
		foreach ($sentinels as $sentinel) {
			if (is_scalar($sentinel)) {
				$sentinel = [
						'hostname' => $sentinel
				];
			}
			$connection = new SentinelConnection();
			$connection->hostname = isset($sentinel['hostname']) ? $sentinel['hostname'] : null;
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
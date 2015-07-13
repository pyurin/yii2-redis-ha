<?php

namespace pyurin\yii\redisHa;

use Yii;

class Helper {

	/**
	 * Execute redis command on socket and return parsed response
	 **/
	static function executeCommand ($name, $params, $socket) {
		Yii::beginProfile("Execute command $name", __CLASS__);
		array_unshift($params, $name);
		$command = '*' . count($params) . "\r\n";
		foreach ($params as $arg) {
			$command .= '$' . mb_strlen($arg, '8bit') . "\r\n" . $arg . "\r\n";
		}
		
		
		Yii::trace("Executing redis Command: {$name} " . (isset($params[1]) ? $params[1] : null), __METHOD__);
		fwrite($socket, $command);
		
		$result = static::parseResponse(implode(' ', $params), $socket);
		
		Yii::endProfile("Execute command $name", __CLASS__);
		return $result;
	}

	/**
	 *
	 * @param string $command        	
	 * @return mixed
	 * @throws Exception on error
	 */
	static function parseResponse ($command, $socket) {
		if (($line = fgets($socket)) === false) {
			throw new \Exception("Failed to read from socket.\nRedis command was: " . $command);
		}
		$type = $line[0];
		$line = mb_substr($line, 1, - 2, '8bit');
		switch ($type) {
			case '+': // Status reply
				if ($line === 'OK' || $line === 'PONG') {
					return true;
				} else {
					return $line;
				}
			case '-': // Error reply
				throw new \Exception("Redis error: " . $line . "\nRedis command was: " . $command);
			case ':': // Integer reply
				// no cast to int as it is in the range of a signed 64 bit integer
				return $line;
			case '$': // Bulk replies
				if ($line == '-1') {
					return null;
				}
				$length = $line + 2;
				$data = '';
				while ($length > 0) {
					if (($block = fread($socket, $length)) === false) {
						throw new \Exception("Failed to read from socket.\nRedis command was: " . $command);
					}
					$data .= $block;
					$length -= mb_strlen($block, '8bit');
				}
				
				return mb_substr($data, 0, - 2, '8bit');
			case '*': // Multi-bulk replies
				$count = (int) $line;
				$data = [];
				for ($i = 0; $i < $count; $i ++) {
					$data[] = static::parseResponse($command, $socket);
				}
				
				return $data;
			default:
				throw new \Exception('Received illegal data from redis: ' . $line . "\nRedis command was: " . $command);
		}
	}
}

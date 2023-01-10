<?php

namespace pyurin\yii\redisHa;

use Yii;

class SentinelConnection
{

    public $hostname;

    public $masterName;

    public $password;

    public $port = 26379;

    public $connectionTimeout;

    /**
     * @deprecated Redis sentinel does not work on unix socket
     */
    public $unixSocket;

    protected $socket;

    public function __construct($config = [])
    {
        if (!empty($config)) {
            Yii::configure($this, $config);
        }
    }

    /**
     * Asks sentinel to tell redis master server
     *
     * @return array|false [host,port] array or false if case of error
     */
    function getMaster()
    {
        if ($this->open()) {
            if ($this->password) {
                Helper::executeCommand('AUTH', [$this->password], $this->socket);
            }

            return Helper::executeCommand('sentinel', [
                'get-master-addr-by-name',
                $this->masterName,
            ], $this->socket);
        } else {
            return false;
        }
    }

    /**
     * Connects to redis sentinel
     */
    protected function open()
    {
        if ($this->socket !== null) {
            return;
        }
        $connection = ($this->unixSocket ?: $this->hostname . ':' . $this->port);
        Yii::trace('Opening redis sentinel connection: ' . $connection, __METHOD__);
        Yii::beginProfile("Connect to sentinel", __CLASS__);
        $this->socket = @stream_socket_client(
            $this->unixSocket ? 'unix://' . $this->unixSocket : 'tcp://' . $this->hostname . ':' . $this->port,
            $errorNumber,
            $errorDescription,
            $this->connectionTimeout ? $this->connectionTimeout : ini_get("default_socket_timeout"),
            STREAM_CLIENT_CONNECT
        );
        Yii::endProfile("Connect to sentinel", __CLASS__);
        if ($this->socket) {
            if ($this->connectionTimeout !== null) {
                stream_set_timeout(
                    $this->socket,
                    $timeout = (int) $this->connectionTimeout,
                    (int) (($this->connectionTimeout - $timeout) * 1000000)
                );
            }

            return true;
        } else {
            Yii::warning('Failed opening redis sentinel connection: ' . $connection, __METHOD__);
            $this->socket = false;

            return false;
        }
    }
}

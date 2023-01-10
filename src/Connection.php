<?php

namespace pyurin\yii\redisHa;

use Yii;
use yii\db\Exception;

class Connection extends \yii\redis\Connection
{

    /**
     * List of sentinel servers like that:
     * ['host1','host2']
     */
    public $sentinels = null;

    /**
     * @var string Password for sentinel
     */
    public $sentinelPassword;

    /**
     * Redis server hostname, should not be modified from outside
     */
    public $hostname = null;

    /**
     * Redis port hostname, should not be modified from outside
     */
    public $port = null;

    /**
     * Name of master
     */
    public $masterName;

    /**
     *
     * @var resource redis socket connection
     */
    protected $socket;

    /**
     * Returns a value indicating whether the DB connection is established.
     *
     * @return boolean whether the DB connection is established
     */
    public function getIsActive()
    {
        return $this->socket !== null;
    }

    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     *
     * @throws Exception if connection fails
     */
    public function open()
    {
        if ($this->socket !== null) {
            return;
        }
        if (!$this->sentinels) {
            throw new Exception("Sentinels must be set");
        }
        $this->hostname = $this->unixSocket = $this->port = null;
        list ($this->hostname, $this->port) = (new SentinelsManager([
            'sentinels' => $this->sentinels,
            'sentinelPassword' => $this->sentinelPassword,
            'masterName' => $this->masterName,
        ]))->discoverMaster();
        $connection = ($this->unixSocket ?: $this->hostname . ':' . $this->port) . ', database=' . $this->database;
        Yii::trace('Opening redis DB connection: ' . $connection, __METHOD__);
        Yii::beginProfile("Connect to redis master", __CLASS__);
        $this->socket = @stream_socket_client(
            $this->unixSocket ? 'unix://' . $this->unixSocket : 'tcp://' . $this->hostname . ':' . $this->port,
            $errorNumber,
            $errorDescription,
            $this->connectionTimeout ? $this->connectionTimeout : ini_get("default_socket_timeout")
        );
        Yii::endProfile("Connect to redis master", __CLASS__);
        if ($this->socket) {
            if ($this->dataTimeout !== null) {
                stream_set_timeout(
                    $this->socket,
                    $timeout = (int) $this->dataTimeout,
                    (int) (($this->dataTimeout - $timeout) * 1000000)
                );
            }
            if ($this->password !== null) {
                $this->executeCommand('AUTH', [
                    $this->password,
                ]);
            }
            list ($role) = $this->executeCommand("ROLE");
            if ($role != 'master') {
                throw new Exception("Failed connecting to redis - role is `{$role}` but not master");
            }
            if ($this->database) {
                $this->executeCommand('SELECT', [
                    $this->database,
                ]);
            }
            $this->initConnection();
        } else {
            \Yii::error(
                "Failed to open redis DB connection ($connection): $errorNumber - $errorDescription",
                __CLASS__
            );
            $message = YII_DEBUG ? "Failed to open redis DB connection ($connection): $errorNumber - $errorDescription" : 'Failed to open DB connection.';
            throw new Exception($message, $errorDescription, (int) $errorNumber);
        }
    }

    public function executeCommand($name, $params = [])
    {
        $this->open();
        $result = Helper::executeCommand($name, $params, $this->socket);

        return $result;
    }

    public function close()
    {
        if ($this->socket !== null) {
            $connection = ($this->unixSocket ?: $this->hostname . ':' . $this->port) . ', database=' . $this->database;
            \Yii::trace('Closing DB connection: ' . $connection, __METHOD__);
            $this->executeCommand('QUIT');
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            $this->socket = null;
        }
    }
}

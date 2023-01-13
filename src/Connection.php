<?php

namespace pyurin\yii\redisHa;

use Yii;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii\redis\LuaScriptBuilder;
use yii\redis\SocketException;

/**
 * @property-read string $connectionString Socket connection string.
 * @property-read string $driverName Name of the DB driver.
 * @property-read bool $isActive Whether the DB connection is established.
 * @property-read LuaScriptBuilder $luaScriptBuilder
 * @property-read resource|false $socket
 */
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
     * @var array redis redirect socket connection pool
     */
    private $_pool = [];

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
     * Return the connection string used to open a socket connection. During a redirect (cluster mode) this will be the
     * target of the redirect.
     * @return string socket connection string
     * @since 2.0.11
     */
    public function getConnectionString()
    {
        Yii::warning($this->unixSocket, 'getConnectionString');
        Yii::warning($this->hostname, 'getConnectionString');
        Yii::warning($this->port, 'getConnectionString');
        if ($this->unixSocket) {
            return 'unix://' . $this->unixSocket;
        }

        return 'tcp://' . ($this->redirectConnectionString ?: "$this->hostname:$this->port");
    }

    /**
     * Return the connection resource if a connection to the target has been established before, `false` otherwise.
     * @return resource|false
     */
    public function getSocket()
    {
        return ArrayHelper::getValue($this->_pool, $this->connectionString, false);
    }

    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     *
     * @throws Exception if connection fails
     */
    public function open()
    {
        if ($this->socket !== false) {
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


        $connection = $this->connectionString . ', database=' . $this->database;
        Yii::trace('Opening redis DB connection: ' . $connection, __METHOD__);

        $socket = @stream_socket_client(
            $this->connectionString,
            $errorNumber,
            $errorDescription,
            $this->connectionTimeout ?: ini_get("default_socket_timeout"),
            $this->socketClientFlags,
            stream_context_create($this->contextOptions)
        );

        if ($socket) {
            $this->_pool[ $this->connectionString ] = $socket;

            if ($this->dataTimeout !== null) {
                stream_set_timeout(
                    $socket,
                    $timeout = (int)$this->dataTimeout,
                    (int)(($this->dataTimeout - $timeout) * 1000000)
                );
            }
            if ($this->useSSL) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            if ($this->password !== null) {
                $this->executeCommand('AUTH', array_filter([$this->username, $this->password]));
            }
            if ($this->database !== null) {
                $this->executeCommand('SELECT', [$this->database]);
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
            throw new Exception($message, $errorDescription, (int)$errorNumber);
        }
    }

    public function close()
    {
        foreach ($this->_pool as $socket) {
            $connection = $this->connectionString . ', database=' . $this->database;
            \Yii::trace('Closing DB connection: ' . $connection, __METHOD__);
            try {
                $this->executeCommand('QUIT');
            } catch (SocketException $e) {
                // ignore errors when quitting a closed connection
            }
            fclose($socket);
        }

        $this->_pool = [];
    }
}

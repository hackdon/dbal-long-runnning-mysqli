<?php

namespace Don\DBAL\Driver\LongRunningMysqli;

use Doctrine\DBAL\Driver\Connection as Connection;
use Doctrine\DBAL\Driver\PingableConnection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

class MysqliConnection implements Connection, PingableConnection, ServerInfoAwareConnection
{
    /**
     * @var \mysqli
     */
    private $_conn;

    /**
     * @var \Doctrine\DBAL\Driver\MysqliPlus\ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @param array  $params
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     */
    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $this->connectionHelper = new ConnectionHelper($params, $username, $password, $driverOptions);
        $this->_conn = $this->connectionHelper->connect();
    }

    /**
     * Retrieves mysqli native resource handle.
     *
     * Could be used if part of your application is not using DBAL.
     *
     * @return \mysqli
     */
    public function getWrappedResourceHandle()
    {
        return $this->_conn;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        $majorVersion = floor($this->_conn->server_version / 10000);
        $minorVersion = floor(($this->_conn->server_version - $majorVersion * 10000) / 100);
        $patchVersion = floor($this->_conn->server_version - $majorVersion * 10000 - $minorVersion * 100);

        return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        $stmt = $this->tryCallMethod('prepare', [$prepareString]);
        if (false === $stmt) {
            throw new MysqliException($this->_conn->error, $this->_conn->sqlstate, $this->_conn->errno);
        }

        return new MysqliStatement($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type=\PDO::PARAM_STR)
    {
        return "'" . $this->tryCallMethod('escape_string', [$input]) . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        if (false === $this->tryCallMethod('query', [$statement])) {
            throw new MysqliException($this->_conn->error, $this->_conn->sqlstate, $this->_conn->errno);
        }

        return $this->_conn->affected_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return $this->_conn->insert_id;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->tryCallMethod('query', ['START TRANSACTION']);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->_conn->commit();
    }

    /**
     * {@inheritdoc}non-PHPdoc)
     */
    public function rollBack()
    {
        return $this->_conn->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->_conn->errno;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->_conn->error;
    }

    /**
     * Pings the server and re-connects when `mysqli.reconnect = 1`
     *
     * @return bool
     */
    public function ping()
    {
        return $this->_conn->ping();
    }

    private function tryCallMethod($method, array $params = [])
    {
        for ($i = 0; $i < 2; $i++) {
            $result = @call_user_func_array([$this->_conn, $method], $params);

            if ($result === false) {
                if (($this->_conn->errno == 2013) || ($this->_conn->errno == 2006)) {
                    $this->_conn = $this->connectionHelper->reconnect();
                    continue;
                }
            }

            return $result;
        }
    }
}

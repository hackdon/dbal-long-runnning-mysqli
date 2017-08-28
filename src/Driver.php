<?php

namespace Don\DBAL\Driver\LongRunningMysqli;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\DBALException;

class Driver extends AbstractMySQLDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        try {
            return new MysqliConnection($params, $username, $password, $driverOptions);
        } catch (MysqliException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'long_running_mysqli';
    }
}

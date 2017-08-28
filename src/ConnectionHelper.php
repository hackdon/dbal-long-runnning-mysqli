<?php

namespace Don\DBAL\Driver\LongRunningMysqli;

class ConnectionHelper
{
    /**
     * Name of the option to set connection flags
     */
    const OPTION_FLAGS = 'flags';

    /**
     * @var \mysqli
     */
    private $_conn;

    private $params;
    private $username;
    private $password;
    private $driverOptions;

    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $this->params = $params;
        $this->username = $username;
        $this->password = $password;
        $this->driverOptions = $driverOptions;
    }

    public function reconnect()
    {
        $this->disconnect();

        return $this->connect();
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function connect()
    {
        $port = isset($this->params['port']) ? $this->params['port'] : ini_get('mysqli.default_port');

        // Fallback to default MySQL port if not given.
        if ( ! $port) {
            $port = 3306;
        }

        $socket = isset($this->params['unix_socket']) ? $this->params['unix_socket'] : ini_get('mysqli.default_socket');
        $dbname = isset($this->params['dbname']) ? $this->params['dbname'] : null;

        $flags = isset($this->driverOptions[static::OPTION_FLAGS]) ? $this->driverOptions[static::OPTION_FLAGS] : null;

        $this->_conn = mysqli_init();

        $this->setDriverOptions();

        set_error_handler(function () {});

        if ( ! $this->_conn->real_connect($this->params['host'], $this->username, $this->password, $dbname, $port, $socket, $flags)) {
            restore_error_handler();

            throw new MysqliException($this->_conn->connect_error, @$this->_conn->sqlstate ?: 'HY000', $this->_conn->connect_errno);
        }

        restore_error_handler();

        if (isset($this->params['charset'])) {
            $this->_conn->set_charset($this->params['charset']);
        }

        return $this->_conn;
    }

    private function disconnect()
    {
        $this->_conn->close();
    }

    /**
     * Apply the driver options to the connection.
     *
     * @throws MysqliException When one of of the options is not supported.
     * @throws MysqliException When applying doesn't work - e.g. due to incorrect value.
     */
    private function setDriverOptions()
    {
        $supportedDriverOptions = array(
            \MYSQLI_OPT_CONNECT_TIMEOUT,
            \MYSQLI_OPT_LOCAL_INFILE,
            \MYSQLI_INIT_COMMAND,
            \MYSQLI_READ_DEFAULT_FILE,
            \MYSQLI_READ_DEFAULT_GROUP,
        );

        if (defined('MYSQLI_SERVER_PUBLIC_KEY')) {
            $supportedDriverOptions[] = \MYSQLI_SERVER_PUBLIC_KEY;
        }

        $exceptionMsg = "%s option '%s' with value '%s'";

        foreach ($this->driverOptions as $option => $value) {

            if ($option === static::OPTION_FLAGS) {
                continue;
            }

            if (!in_array($option, $supportedDriverOptions, true)) {
                throw new MysqliException(
                    sprintf($exceptionMsg, 'Unsupported', $option, $value)
                );
            }

            if (@mysqli_options($this->_conn, $option, $value)) {
                continue;
            }

            $msg  = sprintf($exceptionMsg, 'Failed to set', $option, $value);
            $msg .= sprintf(', error: %s (%d)', mysqli_error($this->_conn), mysqli_errno($this->_conn));

            throw new MysqliException(
                $msg,
                $this->_conn->sqlstate,
                $this->_conn->errno
            );
        }
    }
}

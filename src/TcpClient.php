<?php

namespace Spatie\SimpleTcpClient;

use Spatie\SimpleTcpClient\Exceptions\ClientNotConnected;
use Spatie\SimpleTcpClient\Exceptions\CommunicationFailed;
use Spatie\SimpleTcpClient\Exceptions\ConnectionTimeout;
use Spatie\SimpleTcpClient\Exceptions\CouldNotConnect;

class TcpClient
{
    protected mixed $socket = null;

    protected string $host;

    protected int $port;

    protected int|float $timeout;

    public function __construct(
        string $host = 'localhost',
        int $port = 8080,
        int|float $timeout = 10
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    public function connect(): self
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($this->socket === false) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw CouldNotConnect::failedToCreateSocket($this->host, $this->port, $errorCode, $errorMessage);
        }

        // Set socket to non-blocking for connection timeout
        socket_set_nonblock($this->socket);

        $result = socket_connect($this->socket, $this->host, $this->port);

        if ($result === false) {
            $error = socket_last_error($this->socket);

            // EINPROGRESS is expected for non-blocking connect
            if ($error === SOCKET_EINPROGRESS || $error === SOCKET_EALREADY || $error === SOCKET_EWOULDBLOCK) {
                // Use select to wait for connection with timeout
                $read = null;
                $write = [$this->socket];
                $except = [$this->socket];

                $result = socket_select($read, $write, $except, (int) $this->timeout);

                if ($result === 0) {
                    $this->close();
                    throw ConnectionTimeout::toHost($this->host, $this->port);
                }

                if ($result === false || ! empty($except)) {
                    $errorCode = socket_last_error($this->socket);
                    $errorMessage = socket_strerror($errorCode);
                    $this->close();
                    throw CouldNotConnect::connectionFailed($this->host, $this->port, $errorCode, $errorMessage);
                }
            } else {
                $errorMessage = socket_strerror($error);
                $this->close();
                throw CouldNotConnect::connectionFailed($this->host, $this->port, $error, $errorMessage);
            }
        }

        // Set socket back to blocking mode
        socket_set_block($this->socket);

        $timeoutArray = [
            'sec' => (int) $this->timeout,
            'usec' => (int) (($this->timeout - (int) $this->timeout) * 1000000),
        ];

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $timeoutArray);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, $timeoutArray);

        return $this;
    }

    public function send(string $message): self
    {
        if (! $this->socket) {
            throw ClientNotConnected::toServer();
        }

        $bytes = socket_write($this->socket, $message, strlen($message));

        if ($bytes === false) {
            $errorCode = socket_last_error($this->socket);
            $errorMessage = socket_strerror($errorCode);
            throw CommunicationFailed::sendFailed($errorCode, $errorMessage);
        }

        return $this;
    }

    public function receive(int $maxLength = 4096): ?string
    {
        if (! $this->socket) {
            throw ClientNotConnected::toServer();
        }

        $data = socket_read($this->socket, $maxLength);

        if ($data === false) {
            $error = socket_last_error($this->socket);
            if ($error !== 0) {
                $errorMessage = socket_strerror($error);
                throw CommunicationFailed::receiveFailed($error, $errorMessage);
            }

            return null; // Connection closed gracefully
        }

        return trim($data);
    }

    public function close(): self
    {

        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }

        return $this;
    }

    public function __destruct()
    {
        $this->close();
    }
}

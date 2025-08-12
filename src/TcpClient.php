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

    protected int $timeoutInMs;

    public function __construct(
        string $host,
        int $port,
        int $timeoutInMs = 2_000
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->timeoutInMs = $timeoutInMs;
    }

    public function connect(): self
    {
        // Detect address family (IPv4 or IPv6)
        $addressFamily = filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? AF_INET6
            : AF_INET;

        $this->socket = socket_create($addressFamily, SOCK_STREAM, SOL_TCP);

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

                $seconds = (int) ($this->timeoutInMs / 1000);
                $microseconds = (int) (($this->timeoutInMs % 1000) * 1000);
                $result = socket_select($read, $write, $except, $seconds, $microseconds);

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

        // Set socket back to blocking mode for reliable communication
        socket_set_block($this->socket);

        return $this;
    }

    public function send(string $message): self
    {
        if (! $this->socket) {
            throw ClientNotConnected::toServer();
        }

        // Convert literal \r\n escape sequences to actual CR+LF bytes
        $message = str_replace(['\\r\\n', '\\r', '\\n'], ["\r\n", "\r", "\n"], $message);

        $totalBytes = strlen($message);
        $bytesSent = 0;

        while ($bytesSent < $totalBytes) {
            $result = socket_write($this->socket, substr($message, $bytesSent), $totalBytes - $bytesSent);

            if ($result === false) {
                $errorCode = socket_last_error($this->socket);
                // Handle EAGAIN/EWOULDBLOCK - retry operation
                if ($errorCode === SOCKET_EAGAIN || $errorCode === SOCKET_EWOULDBLOCK) {
                    usleep(1_000); // Wait 1ms before retry

                    continue;
                }

                $errorMessage = socket_strerror($errorCode);
                throw CommunicationFailed::sendFailed($errorCode, $errorMessage);
            }

            $bytesSent += $result;
        }

        return $this;
    }

    public function receive(int $maxLength = 4096, bool $debug = false, bool $trim = true): ?string
    {
        if (! $this->socket) {
            throw ClientNotConnected::toServer();
        }

        if ($debug) {
            error_log("TcpClient: Starting receive with timeout: {$this->timeoutInMs}ms, maxLength: {$maxLength}");
        }

        // Use socket_select to check for data availability with timeout
        $read = [$this->socket];
        $write = null;
        $except = null;

        $selectStart = microtime(true);
        $seconds = (int) ($this->timeoutInMs / 1000);
        $microseconds = (int) (($this->timeoutInMs % 1000) * 1000);
        $result = socket_select($read, $write, $except, $seconds, $microseconds);
        $selectDuration = microtime(true) - $selectStart;

        if ($debug) {
            error_log("TcpClient: socket_select result: {$result}, duration: {$selectDuration}s");
        }

        if ($result === 0) {
            if ($debug) {
                error_log("TcpClient: Timeout - no data available after {$this->timeoutInMs}ms");
            }

            return null; // Timeout - no data available
        }

        if ($result === false) {
            $error = socket_last_error($this->socket);
            $errorMessage = socket_strerror($error);
            if ($debug) {
                error_log("TcpClient: socket_select failed - Error: {$error}, Message: {$errorMessage}");
            }
            throw CommunicationFailed::receiveFailed($error, $errorMessage);
        }

        // Data is available, read it
        $data = socket_read($this->socket, $maxLength);

        if ($debug) {
            $dataLength = $data === false ? 'false' : strlen($data);
            error_log("TcpClient: socket_read returned data length: {$dataLength}");
            if ($data !== false && $dataLength > 0) {
                error_log('TcpClient: Data preview: '.substr($data, 0, 100).($dataLength > 100 ? '...' : ''));
            }
        }

        if ($data === false) {
            $error = socket_last_error($this->socket);
            if ($error !== 0) {
                $errorMessage = socket_strerror($error);
                if ($debug) {
                    error_log("TcpClient: socket_read failed - Error: {$error}, Message: {$errorMessage}");
                }
                throw CommunicationFailed::receiveFailed($error, $errorMessage);
            }
            if ($debug) {
                error_log('TcpClient: Connection closed gracefully');
            }

            return null; // Connection closed gracefully
        }

        return $trim ? trim($data) : $data;
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

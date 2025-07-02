<?php

namespace Spatie\SimpleTcpClient\Exceptions;

use Spatie\SimpleTcpClient\Support\SocketError;

class CouldNotConnect extends TcpClientException
{
    public readonly int $errorCode;
    public readonly string $errorMessage;

    public function __construct(string $message, int $errorCode, string $errorMessage)
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public static function failedToCreateSocket(string $host, int $port, int $errorCode, string $errorMessage): self
    {
        $errorDescription = SocketError::description($errorCode, $errorMessage);

        return new static("Could not create socket to {$host}:{$port}. [{$errorCode}] {$errorDescription}", $errorCode, $errorMessage);
    }

    public static function connectionFailed(string $host, int $port, int $errorCode, string $errorMessage): self
    {
        $errorDescription = SocketError::description($errorCode, $errorMessage);

        return new static("Failed to connect to {$host}:{$port}. [{$errorCode}] {$errorDescription}", $errorCode, $errorMessage);
    }



}

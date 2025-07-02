<?php

namespace Spatie\SimpleTcpClient\Exceptions;

use Spatie\SimpleTcpClient\Support\SocketError;

class CommunicationFailed extends TcpClientException
{
    public readonly int $errorCode;

    public readonly string $errorMessage;

    public function __construct(string $message, int $errorCode, string $errorMessage)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public static function sendFailed(int $errorCode, string $errorMessage): self
    {
        $errorDescription = SocketError::description($errorCode, $errorMessage);

        return new static("Failed to send data. [{$errorCode}] {$errorDescription}", $errorCode, $errorMessage);
    }

    public static function receiveFailed(int $errorCode, string $errorMessage): self
    {
        $errorDescription = SocketError::description($errorCode, $errorMessage);

        return new static("Failed to receive data. [{$errorCode}] {$errorDescription}", $errorCode, $errorMessage);
    }
}

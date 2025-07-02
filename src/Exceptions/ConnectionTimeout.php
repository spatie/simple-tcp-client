<?php

namespace Spatie\SimpleTcpClient\Exceptions;

class ConnectionTimeout extends TcpClientException
{
    public static function toHost(string $host, int $port): self
    {
        return new static("Connection timeout to {$host}:{$port}");
    }
}

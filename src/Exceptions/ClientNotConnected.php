<?php

namespace Spatie\SimpleTcpClient\Exceptions;

class ClientNotConnected extends TcpClientException
{
    public static function toServer(): self
    {
        return new static('Not connected to server. Make sure to call `connect` first.');
    }
}

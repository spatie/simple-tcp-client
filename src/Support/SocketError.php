<?php

namespace Spatie\SimpleTcpClient\Support;

class SocketError
{
    public static function description(int $errorCode, string $errorMessage): string
    {
        return match ($errorCode) {
            SOCKET_ECONNREFUSED => 'Connection refused - the target actively rejected the connection',
            SOCKET_ETIMEDOUT => 'Connection timed out - no response from the target',
            SOCKET_EHOSTUNREACH => 'Host unreachable - no route to the destination host',
            SOCKET_ENETUNREACH => 'Network unreachable - no route to the destination network',
            SOCKET_EADDRINUSE => 'Address already in use - the local address is already bound',
            SOCKET_EADDRNOTAVAIL => 'Address not available - the specified address is not available',
            SOCKET_EINPROGRESS => 'Operation in progress - connection attempt is still in progress',
            SOCKET_EALREADY => 'Operation already in progress - connection attempt already started',
            SOCKET_EWOULDBLOCK => 'Operation would block - resource temporarily unavailable',
            default => $errorMessage,
        };
    }
}

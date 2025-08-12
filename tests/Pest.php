<?php

function markTestPassed()
{
    expect(true)->toBeTrue();
}

// Helper function to check IPv6 availability
function isIPv6Available(): bool
{
    // Check if IPv6 is supported by the system
    if (! filter_var('::1', FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return false;
    }

    // Test if we can create an IPv6 socket
    $testSocket = @socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
    if ($testSocket === false) {
        return false;
    }

    socket_close($testSocket);

    // Check if system has IPv6 routes (on Unix-like systems)
    if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
        // Check if there's a default IPv6 route
        $routes = @shell_exec('ip -6 route 2>/dev/null || route -n get -inet6 default 2>/dev/null');
        if (empty($routes) || strpos($routes, 'default') === false) {
            return false;
        }
    }

    return true;
}

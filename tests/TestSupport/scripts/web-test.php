<?php

// Simple web endpoint that uses TcpClient to test web request compatibility
require_once __DIR__ . '/../../../vendor/autoload.php';

use Spatie\SimpleTcpClient\TcpClient;

// Set a short timeout to ensure we don't hang web requests
ini_set('max_execution_time', 10);

// Simulate a web request that uses TcpClient
$client = new TcpClient('httpbin.org', 80, 5);

try {
    $client->connect();
    
    $request = "GET /get?source=web-test HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";
    $client->send($request);
    
    $response = $client->receive(4096);
    $client->close();
    
    if ($response && strpos($response, 'HTTP/1.1 200 OK') !== false) {
        echo "SUCCESS: TcpClient works in web context\n";
        echo "Response length: " . strlen($response) . " bytes\n";
        exit(0);
    } else {
        echo "FAIL: No valid response received\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
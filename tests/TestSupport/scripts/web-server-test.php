<?php

// Web server endpoint that uses TcpClient with Gmail SMTP
require_once __DIR__.'/../../../vendor/autoload.php';

use Spatie\SimpleTcpClient\TcpClient;

// Simulate typical web request headers
header('Content-Type: text/plain');

// This simulates what would happen in a real web request using Gmail SMTP
$client = new TcpClient('smtp.gmail.com', 587, 10_000); // 10 second timeout

try {
    $client->connect();

    // First, receive the greeting from Gmail SMTP server
    $greeting = $client->receive();

    if (! $greeting || strpos($greeting, '220') === false) {
        echo 'WEB_FAIL_GREETING';
        $client->close();
        exit;
    }

    // Send EHLO command
    $client->send("EHLO webtest.local\r\n");

    // Receive the EHLO response
    usleep(200_000); // 200ms delay to ensure response is ready
    $ehloResponse = $client->receive(4096);

    $client->close();

    // Verify we got a proper EHLO response
    if ($ehloResponse &&
        strpos($ehloResponse, '250') !== false &&
        strpos($ehloResponse, 'smtp.gmail.com') !== false &&
        strpos($ehloResponse, 'STARTTLS') !== false) {
        echo 'WEB_SUCCESS_SMTP';
    } else {
        echo 'WEB_FAIL_EHLO';
    }

} catch (Exception $e) {
    echo 'WEB_ERROR: '.$e->getMessage();
}

<?php

use Spatie\SimpleTcpClient\TcpClient;
use Spatie\SimpleTcpClient\Exceptions\ClientNotConnected;
use Spatie\SimpleTcpClient\Exceptions\ConnectionTimeout;

it('can connect to httpbin.org and send HTTP request', function () {
    $client = new TcpClient('httpbin.org', 80, 10);

    $client->connect();

    $request = "GET /get HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";

    $client->send($request);

    $response = $client->receive();

    expect($response)->toContain('HTTP/1.1 200 OK');

    $client->close();
});

it('can connect to echo server and receive echoed data', function () {
    // tcpbin.com provides echo service on port 4242
    $client = new TcpClient('tcpbin.com', 4242, 10);

    $client->connect();

    $testMessage = "Hello TCP Echo Server\n";
    $client->send($testMessage);

    $response = $client->receive();

    expect($response)->toBe(trim($testMessage));

    $client->close();
});

it('can connect to time server and receive time data', function () {
    $client = new TcpClient('time.nist.gov', 13, 10);

    $client->connect();

    $response = $client->receive();

    expect($response)->toContain('(NIST)');

    $client->close();
});

it('can connect to Gmail SMTP and send EHLO command', function () {
    $client = new TcpClient('smtp.gmail.com', 587, 15);

    $client->connect();

    $greeting = $client->receive();
    expect($greeting)->toContain('220');
    expect($greeting)->toContain('smtp.gmail.com');

    $client->send("EHLO test.local\r\n");

    usleep(200000); // 200ms delay
    $response = $client->receive(4096);

    expect($response)->toContain('250');
    expect($response)->toContain('smtp.gmail.com');
    expect($response)->toContain('STARTTLS');
    // AUTH might not always be advertised before STARTTLS, so we'll check for common SMTP capabilities
    expect($response)->toMatch('/(AUTH|SIZE|8BITMIME|SMTPUTF8)/');

    // Send QUIT to cleanly close
    $client->send("QUIT\r\n");
    $quitResponse = $client->receive();
    expect($quitResponse)->toContain('221');

    $client->close();
});

it('can test if a port is open by attempting connection', function () {
    // Test open port (Google DNS on 53)
    $client = new TcpClient('8.8.8.8', 53, 5);

    expect(fn() => $client->connect())->not->toThrow(Exception::class);

    $client->close();
});

it('throws exception when connecting to closed port', function () {
    // Try to connect to a likely closed port
    $client = new TcpClient('httpbin.org', 12345, 3);

    expect(fn() => $client->connect())->toThrow(ConnectionTimeout::class);
});

it('throws exception when sending without connection', function () {
    $client = new TcpClient('localhost', 8080);

    $client->send('test');
})->throws(ClientNotConnected::class);

it('throws exception when receiving without connection', function () {
    $client = new TcpClient('localhost', 8080);

    $client->receive();
})->throws(ClientNotConnected::class);

it('can handle connection timeout', function () {
    // Use a valid IP with filtered port to trigger timeout
    $client = new TcpClient('8.8.8.8', 12345, 2);

    $start = microtime(true);

    expect(fn() => $client->connect())->toThrow(ConnectionTimeout::class, 'Connection timeout');

    $elapsed = microtime(true) - $start;

    // Should timeout close to the specified timeout (2 seconds + small buffer)
    expect($elapsed)->toBeGreaterThan(1.5);
    expect($elapsed)->toBeLessThan(3);
});

it('can connect to FTP server and receive welcome message', function () {
    $client = new TcpClient('ftp.dlptest.com', 21, 10);

    $client->connect();

    $welcome = $client->receive();
    expect($welcome)->toContain('220');
    expect($welcome)->toContain('FTP');

    $client->send("USER anonymous\r\n");
    $userResponse = $client->receive();
    expect($userResponse)->toMatch('/33[01]/'); // 330 or 331 response

    $client->send("QUIT\r\n");
    $quitResponse = $client->receive();
    expect($quitResponse)->toContain('221');

    $client->close();
});

it('can handle multiple sends and receives in one session', function () {
    $client = new TcpClient('tcpbin.com', 4242, 10);

    $client->connect();

    $messages = ['First message', 'Second message', 'Third message'];

    foreach ($messages as $message) {
        $client->send($message . "\n");
        $response = $client->receive();
        expect($response)->toBe($message);
    }

    $client->close();
});

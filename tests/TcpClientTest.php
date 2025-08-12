<?php

use Spatie\SimpleTcpClient\Exceptions\ClientNotConnected;
use Spatie\SimpleTcpClient\Exceptions\ConnectionTimeout;
use Spatie\SimpleTcpClient\TcpClient;

it('can connect to httpbin.org and send HTTP request', function () {
    $client = new TcpClient('httpbin.org', 80, 10_000);

    $client->connect();

    $request = "GET /get HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";

    $client->send($request);

    $response = $client->receive();

    expect($response)->toContain('HTTP/1.1 200 OK');

    $client->close();
});

it('can connect to an ipv6 address', function () {
    $client = new TcpClient('2001:4860:4860::8888', 53, 10_000);

    $client->connect();

    $response = $client->receive();
});

it('can connect to echo server and receive echoed data', function () {
    // tcpbin.com provides echo service on port 4242
    $client = new TcpClient('tcpbin.com', 4242, 10_000);

    $client->connect();

    $testMessage = "Hello TCP Echo Server\n";
    $client->send($testMessage);

    $response = $client->receive();

    expect($response)->toBe(trim($testMessage));

    $client->close();
});

it('can connect to quote server and receive quote data', function () {
    $client = new TcpClient('djxmmx.net', 17, 10_000);

    $client->connect();

    $response = $client->receive(1024);

    expect($response)->not->toBeNull();
    expect($response)->not->toBeEmpty();
    // Quote responses typically contain quotation marks or author names
    expect($response)->toMatch('/[""\'()]|[A-Z][a-z]+ [A-Z][a-z]+/');

    $client->close();
});

it('can connect to Gmail SMTP and send EHLO command', function () {
    $client = new TcpClient('smtp.gmail.com', 587, 15_000);

    $client->connect();

    $greeting = $client->receive();
    expect($greeting)->toContain('220');
    expect($greeting)->toContain('smtp.gmail.com');

    $client->send("EHLO test.local\r\n");

    usleep(200_000); // 200ms delay
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
    $client = new TcpClient('8.8.8.8', 53, 5_000);

    expect(fn () => $client->connect())->not->toThrow(Exception::class);

    $client->close();
});

it('throws exception when connecting to closed port', function () {
    // Try to connect to a likely closed port
    $client = new TcpClient('httpbin.org', 12345, 3_000);

    expect(fn () => $client->connect())->toThrow(ConnectionTimeout::class);
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
    $client = new TcpClient('8.8.8.8', 12345, 2_000);

    $start = microtime(true);

    expect(fn () => $client->connect())->toThrow(ConnectionTimeout::class, 'Connection timeout');

    $elapsed = microtime(true) - $start;

    // Should timeout close to the specified timeout (2000ms + small buffer)
    expect($elapsed)->toBeGreaterThan(1.5);
    expect($elapsed)->toBeLessThan(3);
});

it('can connect to FTP server and receive welcome message', function () {
    $client = new TcpClient('ftp.dlptest.com', 21, 10_000);

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
    $client = new TcpClient('tcpbin.com', 4242, 10_000);

    $client->connect();

    $messages = ['First message', 'Second message', 'Third message'];

    foreach ($messages as $message) {
        $client->send($message."\n");
        $response = $client->receive();
        expect($response)->toBe($message);
    }

    $client->close();
});

it('can work in actual web request context via PHP built-in server', function () {
    // Start PHP built-in web server in background
    $port = 8999;

    // Start the web server
    $serverProcess = proc_open(
        "php -S localhost:$port -t ".__DIR__.'/TestSupport/scripts',
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    // Wait for server to start
    sleep(1);

    try {
        // Make an HTTP request to our test endpoint
        $context = stream_context_create([
            'http' => [
                'timeout' => 10_000,
                'method' => 'GET',
            ],
        ]);

        $startTime = microtime(true);
        $response = file_get_contents("http://localhost:$port/web-server-test.php", false, $context);
        $elapsed = microtime(true) - $startTime;

        // Verify the web request completed successfully with SMTP
        expect($response)->toBe('WEB_SUCCESS_SMTP');

        // Should complete within reasonable time (proves non-blocking)
        expect($elapsed)->toBeLessThan(8);
        expect($elapsed)->toBeGreaterThan(0.1);

    } finally {
        // Clean up the server process
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    }
});

it('can handle timeout gracefully in web request context', function () {
    // Simulate a web request with timeout
    $client = new TcpClient('httpbin.org', 80, 2_000); // Short timeout in milliseconds

    $client->connect();

    // Send request but don't expect immediate response to test timeout
    $client->send("GET /delay/5 HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n");

    $startTime = microtime(true);
    $response = $client->receive();
    $elapsed = microtime(true) - $startTime;

    // Should timeout and return null, not throw exception or block indefinitely
    expect($response)->toBeNull();
    expect($elapsed)->toBeLessThan(3); // Should timeout around 2000ms
    expect($elapsed)->toBeGreaterThan(1.5); // But not too quickly

    $client->close();
});

it('can handle line ending conversion in send method', function () {
    $client = new TcpClient('smtp.gmail.com', 587, 10_000);

    $client->connect();

    // Receive initial greeting
    $greeting = $client->receive();
    expect($greeting)->toContain('220');

    // Send EHLO command with literal \r\n that should be converted
    $client->send('EHLO test.local\\r\\n');

    usleep(200_000); // 200ms delay
    $response = $client->receive();

    // Should receive proper SMTP response indicating the command was processed
    expect($response)->toContain('250');
    expect($response)->toContain('smtp.gmail.com');

    // Send QUIT to cleanly close
    $client->send('QUIT\\r\\n');
    $quitResponse = $client->receive();
    expect($quitResponse)->toContain('221');

    $client->close();
});

it('verifies millisecond timeout precision', function () {
    // Test with a filtered port that will cause timeout
    $client = new TcpClient('8.8.8.8', 12345, 500); // 500ms timeout

    $start = microtime(true);

    expect(fn () => $client->connect())->toThrow(ConnectionTimeout::class);

    $elapsed = (microtime(true) - $start) * 1000; // Convert to milliseconds

    // Should timeout close to 500ms (allowing for some overhead)
    expect($elapsed)->toBeGreaterThan(400); // At least 400ms
    expect($elapsed)->toBeLessThan(700); // But less than 700ms
});

it('can handle sub-second receive timeout', function () {
    $client = new TcpClient('httpbin.org', 80, 1_500); // 1.5 second timeout

    $client->connect();

    // Send a request that will take longer than our timeout
    $client->send("GET /delay/3 HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n");

    $start = microtime(true);
    $response = $client->receive();
    $elapsed = (microtime(true) - $start) * 1000; // Convert to milliseconds

    // Should timeout and return null
    expect($response)->toBeNull();

    // Should timeout close to 1500ms
    expect($elapsed)->toBeGreaterThan(1_400); // At least 1400ms
    expect($elapsed)->toBeLessThan(1_700); // But less than 1700ms

    $client->close();
});

# 🔌 Connect and send data through a TCP connection

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/simple-tcp-client.svg?style=flat-square)](https://packagist.org/packages/spatie/simple-tcp-client)
[![Tests](https://img.shields.io/github/actions/workflow/status/spatie/simple-tcp-client/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/simple-tcp-client/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/simple-tcp-client.svg?style=flat-square)](https://packagist.org/packages/spatie/simple-tcp-client)

This package provides a simple and elegant way to create TCP connections, send data, and receive responses. Perfect for interacting with TCP servers, testing network services, or building simple network clients.

```php
use Spatie\SimpleTcpClient\TcpClient;

$client = new TcpClient('smtp.gmail.com', 587);

$client->connect();

$greeting = $client->receive();
echo $greeting; // 220 smtp.gmail.com ESMTP...

$client->send("EHLO test.local\r\n");
$response = $client->receive();
echo $response; // 250-smtp.gmail.com capabilities...

$client->close();
```

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/simple-tcp-client.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/simple-tcp-client)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/simple-tcp-client
```

## Usage

Here's how you can connect to TCP service:

```php
use Spatie\SimpleTcpClient\TcpClient;

$client = new TcpClient('example.com', 80);

$client->connect();
```

### Sending and receiving data

You can use `send` and `receive` methods to send and receive data.

```php
$client->send("Hello, server!");

$response = $client->receive(); // Read response from server

echo $response;
```

By default, we'll read 8192 bytes of data. You can optionally specify the maximum number of bytes to read:

```php
$response = $client->receive(1024);
```

### Handling timeouts

The client supports connection timeouts to prevent hanging:

```php
use Spatie\SimpleTcpClient\TcpClient;
use Spatie\SimpleTcpClient\Exceptions\ConnectionTimeout;

$client = new TcpClient('slow-server.com', 80, 5_000); // 5000ms (5 second) timeout

try {
    $client->connect();
} catch (ConnectionTimeout $exception) {
    echo "Server took too long to respond: " . $exception->getMessage();
}
```

### HTTP requests over TCP

Here's a full example where we use all methods.

```php
$client = new TcpClient('httpbin.org', 80);

$client->connect();

$request = "GET /get HTTP/1.1\r\n";
$request .= "Host: httpbin.org\r\n";
$request .= "Connection: close\r\n\r\n";

$client->send($request);

$response = $client->receive();

echo $response;

$client->close();
```

### Testing SMTP connections

Here's how you could connect and communicate with an SMTP server.

```php
use Spatie\SimpleTcpClient\TcpClient;

$client = new TcpClient('smtp.gmail.com', 587);

$client->connect();

// Read the greeting
$greeting = $client->receive();
echo $greeting; // 220 smtp.gmail.com ESMTP...

// Send EHLO command
$client->send("EHLO test.local\r\n");
$response = $client->receive();
echo $response; // 250-smtp.gmail.com capabilities...

$client->close();
```

### Port scanning

In this example, we are going to check if port 53 is open or closed.

```php
use Spatie\SimpleTcpClient\TcpClient;
use Spatie\SimpleTcpClient\Exceptions\CouldNotConnect;

try {
    $client = new TcpClient('8.8.8.8', 53);
    
    $client->connect();
    
    echo "Port 53 is open!";
    
    $client->close();
} catch (CouldNotConnect $exception) {
    echo "Port 53 is closed or filtered";
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

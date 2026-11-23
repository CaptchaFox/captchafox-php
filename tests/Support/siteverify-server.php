<?php

// A siteverify on the loopback interface, run as a process of its own by LocalSiteverify.
// Usage: php siteverify-server.php <answer|redirect|silent|stall|trickle> <status> <body file> <record file>
// Prints the port it listens on, then serves one connection after the other until it is terminated. Every request is
// appended to the record file as a line of JSON; a trickle that finds the connection closed appends "broken". A
// redirect sends the body as its location.

declare(strict_types=1);

// A connection is held at most this long, so that a client whose timeout does not take effect is slow, not stuck.
const HOLD_SECONDS = 10;

[, $mode, $status, $bodyFile, $record] = $argv;
$body = file_get_contents($bodyFile);

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, "cannot listen: $error\n");
    exit(1);
}
echo parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT), "\n";

while (true) {
    $connection = stream_socket_accept($server, -1);
    if ($connection === false) {
        continue;
    }

    file_put_contents($record, json_encode(receive($connection)) . "\n", FILE_APPEND);
    switch ($mode) {
        case 'answer':
            fwrite($connection, "HTTP/1.1 $status Status\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n$body");
            break;
        case 'redirect':
            fwrite($connection, "HTTP/1.1 $status Status\r\nLocation: $body\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            break;
        case 'silent':
            sleep(HOLD_SECONDS);
            break;
        case 'stall':
            sendPart($connection, (int) $status, $body);
            sleep(HOLD_SECONDS);
            break;
        case 'trickle':
            sendPart($connection, (int) $status, $body);
            $until = microtime(true) + HOLD_SECONDS;
            while (microtime(true) < $until) {
                usleep(100_000);
                // A write to a connection the client closed fails at the latest with the second one.
                if (@fwrite($connection, "1\r\n \r\n") === false) {
                    file_put_contents($record, "\"broken\"\n", FILE_APPEND);
                    break;
                }
            }
            break;
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(1);
    }
    fclose($connection);
}

/**
 * @param resource $connection
 *
 * @return array{method: string, target: string, contentType: ?string, body: string}
 */
function receive($connection): array
{
    $head = '';
    while (!str_contains($head, "\r\n\r\n")) {
        $line = fgets($connection);
        if ($line === false) {
            break;
        }
        $head .= $line;
    }

    $lines = explode("\r\n", trim($head));
    [$method, $target] = explode(' ', array_shift($lines));
    $headers = [];
    foreach ($lines as $line) {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)] = trim($value);
    }

    $length = (int) ($headers['content-length'] ?? 0);
    $body = $length > 0 ? stream_get_contents($connection, $length) : '';

    return ['method' => $method, 'target' => $target, 'contentType' => $headers['content-type'] ?? null, 'body' => $body];
}

/**
 * Sends the status, the headers and the start of a chunked body, which has no end until the stream is closed.
 *
 * @param resource $connection
 */
function sendPart($connection, int $status, string $start): void
{
    fwrite($connection, "HTTP/1.1 $status Status\r\nContent-Type: application/json\r\nTransfer-Encoding: chunked\r\n\r\n");
    fwrite($connection, dechex(strlen($start)) . "\r\n$start\r\n");
}

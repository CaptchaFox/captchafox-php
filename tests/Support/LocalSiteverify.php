<?php

declare(strict_types=1);

namespace CaptchaFox\Tests\Support;

use CaptchaFox\CaptchaFoxClient;

/**
 * A siteverify on the loopback interface that records every request it receives.
 */
final class LocalSiteverify
{
    /** @var resource */
    private $process;

    private readonly string $record;

    private readonly string $bodyFile;

    private readonly int $port;

    private function __construct(string $mode, int $status, string $body)
    {
        $this->record = tempnam(sys_get_temp_dir(), 'siteverify');
        // In a file, because a body can be longer than an argument may be.
        $this->bodyFile = tempnam(sys_get_temp_dir(), 'siteverify');
        file_put_contents($this->bodyFile, $body);
        $command = [PHP_BINARY, __DIR__ . '/siteverify-server.php', $mode, (string) $status, $this->bodyFile, $this->record];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if ($process === false) {
            throw new \RuntimeException('the local siteverify did not start');
        }
        $this->process = $process;

        $port = fgets($pipes[1]);
        if ($port === false) {
            $this->close();
            throw new \RuntimeException('the local siteverify did not name its port');
        }
        $this->port = (int) $port;
    }

    public static function answering(int $status, string $body): self
    {
        return new self('answer', $status, $body);
    }

    public static function redirecting(int $status, string $location): self
    {
        return new self('redirect', $status, $location);
    }

    /** Receives requests and never answers them. */
    public static function silent(): self
    {
        return new self('silent', 0, '');
    }

    /** Sends the status, the headers and the start of a body, then nothing more. */
    public static function stalling(int $status, string $start): self
    {
        return new self('stall', $status, $start);
    }

    /** Sends the status, the headers and the start of a body, then a space every 100 ms until the client goes. */
    public static function trickling(int $status, string $start): self
    {
        return new self('trickle', $status, $start);
    }

    /** An endpoint at a port of the loopback interface where nothing listens. */
    public static function refusingEndpoint(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
        fclose($socket);

        return "http://127.0.0.1:$port/siteverify";
    }

    /** Points a client to an endpoint instead of CaptchaFox's siteverify. */
    public static function point(CaptchaFoxClient $client, string $endpoint): CaptchaFoxClient
    {
        (new \ReflectionProperty(CaptchaFoxClient::class, 'endpoint'))->setValue($client, $endpoint);

        return $client;
    }

    public function endpoint(): string
    {
        return "http://127.0.0.1:{$this->port}/siteverify";
    }

    /**
     * @return list<array{method: string, target: string, contentType: ?string, body: string}>
     */
    public function requests(): array
    {
        $requests = [];
        foreach ($this->lines() as $line) {
            if (is_array($line)) {
                $requests[] = $line;
            }
        }

        return $requests;
    }

    /** Whether a trickling server found the connection closed. */
    public function connectionBroken(): bool
    {
        return in_array('broken', $this->lines(), true);
    }

    public function close(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
        @unlink($this->record);
        @unlink($this->bodyFile);
    }

    /**
     * Decodes a form body; a field that arrives twice fails.
     *
     * @return array<string, string>
     */
    public static function form(string $body): array
    {
        $form = [];
        if ($body === '') {
            return $form;
        }

        foreach (explode('&', $body) as $pair) {
            [$name, $value] = explode('=', $pair, 2);
            $name = urldecode($name);
            if (array_key_exists($name, $form)) {
                throw new \UnexpectedValueException("the field $name was sent twice");
            }
            $form[$name] = urldecode($value);
        }

        return $form;
    }

    /**
     * @return list<mixed>
     */
    private function lines(): array
    {
        $lines = [];
        foreach (file($this->record, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $lines[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $lines;
    }
}

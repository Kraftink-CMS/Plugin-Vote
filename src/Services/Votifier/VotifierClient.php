<?php

namespace KraftInk\Plugins\Vote\Services\Votifier;

use App\Exceptions\TransportFailure;
use App\Kraft\Games\Transports\SocketConnection;
use App\Kraft\Games\Transports\SocketTarget;
use Closure;

class VotifierClient
{
    public function __construct(private readonly SocketTarget $target)
    {
    }

    public function send(array $configuration, array $vote, Closure $writing): void
    {
        if (($configuration['protocol'] ?? null) !== 'v2' || ($configuration['token'] ?? '') === '') {
            throw new TransportFailure('transports.commands_unsupported', true);
        }
        $socket = $this->target->connect($configuration['host'], $configuration['port']);
        try {
            $greeting = $this->line($socket, 512);
            if (!preg_match('/^VOTIFIER ([^ ]+) ([A-Za-z0-9+\/_=-]{1,256})$/D', $greeting, $matches)) {
                throw new TransportFailure('transports.invalid_response', true);
            }
            $payload = json_encode($vote + ['challenge' => $matches[2]], JSON_THROW_ON_ERROR);
            $message = json_encode(
                [
                    'payload' => $payload,
                    'signature' => base64_encode(hash_hmac('sha256', $payload, $configuration['token'], true)),
                ],
                JSON_THROW_ON_ERROR,
            );
            if (strlen($message) > 1020) {
                throw new TransportFailure('transports.invalid_response', true);
            }
            $writing();
            $socket->write(pack('nn', 0x733A, strlen($message)).$message);
            $response = $this->response($socket);
            if (($response['status'] ?? null) !== 'ok') {
                throw new TransportFailure('transports.invalid_response', true);
            }
        } finally {
            $socket->close();
        }
    }

    private function response(SocketConnection $socket): array
    {
        $response = '';
        for ($index = 0; $index < 4096; $index++) {
            $response .= $socket->read(1);
            $decoded = json_decode($response, true, 16);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!is_array($decoded)) {
                    throw new TransportFailure('transports.invalid_response', true);
                }

                return $decoded;
            }
        }
        throw new TransportFailure('transports.invalid_response', true);
    }

    private function line(SocketConnection $socket, int $limit): string
    {
        $line = '';
        for ($index = 0; $index < $limit; $index++) {
            $byte = $socket->read(1);
            if ($byte === "\n") {
                return rtrim($line, "\r");
            }
            $line .= $byte;
        }
        throw new TransportFailure('transports.invalid_response', true);
    }
}

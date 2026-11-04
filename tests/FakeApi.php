<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests;

use Exception;
use WISECP\Modules\Servers\PanelicaApi;

/**
 * The Panelica External API, faked.
 *
 * Answers are queued per method and path, so a test says what the panel
 * replies and then reads back exactly which calls the module made, in order.
 */
final class FakeApi implements PanelicaApi
{
    /** @var array<string,mixed> path => payload, or a callable, or an Exception to throw */
    private array $answers = [];

    /** @var list<array{method:string,path:string,body:array<string,mixed>|null}> */
    public array $calls = [];

    private int $lastStatus = 200;

    public function answer(string $method, string $path, mixed $payload, int $status = 200): self
    {
        $this->answers[strtoupper($method) . ' ' . $path] = ['payload' => $payload, 'status' => $status];

        return $this;
    }

    /** @return array<mixed> */
    public function get(string $path): array
    {
        return $this->call('GET', $path, null);
    }

    /** @param array<string,mixed>|null $body @return array<mixed> */
    public function post(string $path, ?array $body = null): array
    {
        return $this->call('POST', $path, $body);
    }

    /** @param array<string,mixed> $body @return array<mixed> */
    public function patch(string $path, array $body): array
    {
        return $this->call('PATCH', $path, $body);
    }

    /** @return array<mixed> */
    public function delete(string $path): array
    {
        return $this->call('DELETE', $path, null);
    }

    public function lastStatusWas(int $status): bool
    {
        return $this->lastStatus === $status;
    }

    /** Was this call made, with this body if one is given? */
    public function made(string $method, string $path, ?array $body = null): bool
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === strtoupper($method) && $call['path'] === $path && ($body === null || $call['body'] === $body)) {
                return true;
            }
        }

        return false;
    }

    public function timesCalled(string $method, string $path): int
    {
        return count(array_filter($this->calls, fn (array $c): bool => $c['method'] === strtoupper($method) && $c['path'] === $path));
    }

    /** @param array<string,mixed>|null $body @return array<mixed> */
    private function call(string $method, string $path, ?array $body): array
    {
        $this->calls[] = ['method' => $method, 'path' => $path, 'body' => $body];
        $key = $method . ' ' . $path;

        if (! array_key_exists($key, $this->answers)) {
            // An unanswered call is a test saying one thing and the module doing
            // another: louder as a failure than as an empty array.
            throw new Exception('the fake was not told what to answer for ' . $key);
        }

        $answer = $this->answers[$key];
        $this->lastStatus = $answer['status'];

        if ($answer['payload'] instanceof Exception) {
            throw $answer['payload'];
        }

        return is_array($answer['payload']) ? $answer['payload'] : [];
    }
}

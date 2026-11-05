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

    /** @var array<string,list<array{payload:mixed,status:int}>> later answers for the same path, in order */
    private array $repeats = [];

    /** @var array<string,int> how many times each path has been called */
    private array $seen = [];

    /** @var list<array{method:string,path:string,body:array<string,mixed>|null}> */
    public array $calls = [];

    private int $lastStatus = 200;

    public function answer(string $method, string $path, mixed $payload, int $status = 200): self
    {
        $this->answers[strtoupper($method) . ' ' . $path] = ['payload' => $payload, 'status' => $status];

        return $this;
    }

    /**
     * The answer for the call after the one already queued for this path.
     *
     * A retry is not a repeat: the module asks the same path twice and the
     * panel answers differently the second time - a refused address, then an
     * account. Both answers have to be sayable or the retry cannot be tested.
     */
    public function answerAgain(string $method, string $path, mixed $payload, int $status = 200): self
    {
        $this->repeats[strtoupper($method) . ' ' . $path][] = ['payload' => $payload, 'status' => $status];

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

    /** A test says so; the fake has no certificate of its own. */
    public bool $certificateUnverified = false;

    public function certificateWasNotVerified(): bool
    {
        return $this->certificateUnverified;
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

    /**
     * The body of the first call to this path, whatever its position.
     *
     * Asserting on $api->calls[1] breaks as soon as the module makes one more
     * call earlier - resolving a plan name, for instance - and the test then
     * fails for a reason that has nothing to do with what it is about.
     *
     * @return array<string,mixed>|null
     */
    public function bodyOf(string $method, string $path): ?array
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === strtoupper($method) && $call['path'] === $path) {
                return $call['body'];
            }
        }

        return null;
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
        $this->seen[$key] = ($this->seen[$key] ?? 0) + 1;

        // The queued answer is for the first call; a repeat is for the ones
        // after it, in the order they were queued.
        if ($this->seen[$key] > 1 && ! empty($this->repeats[$key])) {
            $answer = array_shift($this->repeats[$key]);
        }

        $this->lastStatus = $answer['status'];

        if ($answer['payload'] instanceof Exception) {
            throw $answer['payload'];
        }

        return is_array($answer['payload']) ? $answer['payload'] : [];
    }
}

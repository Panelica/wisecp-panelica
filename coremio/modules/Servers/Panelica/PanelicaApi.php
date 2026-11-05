<?php

declare(strict_types=1);

namespace WISECP\Modules\Servers;

/**
 * What the module needs of the panel.
 *
 * Named as a contract so the module can be run against a fake in the tests
 * without loosening the type it holds, and so a second transport (a queue, a
 * recorded session) can be put behind it later without touching the verbs.
 */
interface PanelicaApi
{
    /** @return array<mixed> */
    public function get(string $path): array;

    /**
     * @param array<string,mixed>|null $body
     * @return array<mixed>
     */
    public function post(string $path, ?array $body = null): array;

    /**
     * @param array<string,mixed> $body
     * @return array<mixed>
     */
    public function patch(string $path, array $body): array;

    /** @return array<mixed> */
    public function delete(string $path): array;

    /** Whether the last answer carried this status. */
    public function lastStatusWas(int $status): bool;

    /** True when the panel answered only with the certificate check off. */
    public function certificateWasNotVerified(): bool;
}

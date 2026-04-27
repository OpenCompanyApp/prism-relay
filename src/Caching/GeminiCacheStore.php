<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Caching;

final class GeminiCacheStore
{
    public function __construct(
        private readonly ?string $path = null,
    ) {}

    public function get(string $key): ?string
    {
        $cache = $this->withLockedCache(fn (array $cache): array => [$cache, $cache]);
        $entry = $cache[$key] ?? null;

        if (! is_array($entry) || ! isset($entry['name'], $entry['expires_at'])) {
            return null;
        }

        $expiresAt = strtotime((string) $entry['expires_at']);
        if ($expiresAt === false || $expiresAt <= time()) {
            $this->withLockedCache(function (array $cache) use ($key): array {
                unset($cache[$key]);

                return [$cache, null];
            });

            return null;
        }

        return (string) $entry['name'];
    }

    public function put(string $key, string $name, \DateTimeInterface $expiresAt): void
    {
        $this->withLockedCache(function (array $cache) use ($key, $name, $expiresAt): array {
            $cache[$key] = [
                'name' => $name,
                'expires_at' => $expiresAt->format(DATE_ATOM),
            ];

            return [$cache, null];
        });
    }

    /**
     * @template T
     *
     * @param  callable(array<string, array{name: string, expires_at: string}>): array{0: array<string, array{name: string, expires_at: string}>, 1: T}  $callback
     * @return T
     */
    private function withLockedCache(callable $callback): mixed
    {
        $path = $this->resolvePath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $lockPath = $path.'.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            [$cache, $result] = $callback($this->load());
            $this->save($cache);

            return $result;
        }

        try {
            flock($lock, LOCK_EX);
            [$cache, $result] = $callback($this->load());
            $this->save($cache);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<string, array{name: string, expires_at: string}>
     */
    private function load(): array
    {
        $path = $this->resolvePath();
        if (! is_file($path)) {
            return [];
        }

        $json = @file_get_contents($path);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        return $this->pruneExpired($data);
    }

    /**
     * @param  array<string, array{name: string, expires_at: string}>  $cache
     */
    private function save(array $cache): void
    {
        $path = $this->resolvePath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $cache = $this->pruneExpired($cache);
        $tmp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        $json = json_encode($cache, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (@file_put_contents($tmp, $json) === false) {
            return;
        }

        @rename($tmp, $path);
    }

    /**
     * @param  array<string, mixed>  $cache
     * @return array<string, array{name: string, expires_at: string}>
     */
    private function pruneExpired(array $cache): array
    {
        $now = time();
        $fresh = [];

        foreach ($cache as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['name'], $entry['expires_at'])) {
                continue;
            }

            $expiresAt = strtotime((string) $entry['expires_at']);
            if ($expiresAt === false || $expiresAt <= $now) {
                continue;
            }

            $fresh[(string) $key] = [
                'name' => (string) $entry['name'],
                'expires_at' => (string) $entry['expires_at'],
            ];
        }

        return $fresh;
    }

    private function resolvePath(): string
    {
        if ($this->path !== null && $this->path !== '') {
            return $this->path;
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();

        return rtrim($home, '/').'/'.'.prism-relay/gemini-cache.json';
    }
}

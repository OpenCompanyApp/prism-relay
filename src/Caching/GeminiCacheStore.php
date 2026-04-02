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
        $cache = $this->load();
        $entry = $cache[$key] ?? null;

        if (! is_array($entry) || ! isset($entry['name'], $entry['expires_at'])) {
            return null;
        }

        $expiresAt = strtotime((string) $entry['expires_at']);
        if ($expiresAt === false || $expiresAt <= time()) {
            unset($cache[$key]);
            $this->save($cache);

            return null;
        }

        return (string) $entry['name'];
    }

    public function put(string $key, string $name, \DateTimeInterface $expiresAt): void
    {
        $cache = $this->load();
        $cache[$key] = [
            'name' => $name,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];

        $this->save($cache);
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

        return is_array($data) ? $data : [];
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

        @file_put_contents($path, json_encode($cache, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
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

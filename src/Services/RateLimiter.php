<?php
declare(strict_types=1);

namespace App\Services;

final class RateLimiter
{
    public function __construct(private string $dir)
    {
    }

    public function tooMany(string $key, int $max, int $windowSeconds, ?int $now = null): bool
    {
        return count($this->recent($key, $windowSeconds, $now ?? time())) >= $max;
    }

    public function hit(string $key, int $windowSeconds, ?int $now = null): void
    {
        $now ??= time();
        $hits = $this->recent($key, $windowSeconds, $now);
        $hits[] = $now;
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        file_put_contents($this->file($key), json_encode($hits), LOCK_EX);
    }

    public function clear(string $key): void
    {
        if (is_file($this->file($key))) {
            unlink($this->file($key));
        }
    }

    /** @return list<int> */
    private function recent(string $key, int $windowSeconds, int $now): array
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return [];
        }
        $hits = json_decode((string) file_get_contents($file), true);
        if (!is_array($hits)) {
            return [];
        }
        return array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $windowSeconds));
    }

    private function file(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.json';
    }
}

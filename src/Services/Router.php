<?php
declare(strict_types=1);

namespace App\Services;

final class Router
{
    /** @var list<array{0: string, 1: string, 2: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[a-z0-9-]+') . ')',
            $pattern
        );
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    /** @return array{0: callable, 1: array<string, string>}|null */
    public function match(string $method, string $path): ?array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = '/' . trim($path, '/');
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod === $method && preg_match($regex, $path, $m)) {
                return [$handler, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        return null;
    }

    public function dispatch(string $method, string $uri, callable $notFound): void
    {
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/'));
        $found = $this->match($method, $path);
        if ($found === null) {
            $notFound();
            return;
        }
        [$handler, $params] = $found;
        $handler(...$params);
    }
}

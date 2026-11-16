<?php
declare(strict_types=1);
namespace App;

final class Pagination {
    public readonly int $total;
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $pages;
    public readonly int $offset;
    public readonly string $pageKey;
    public readonly string $sizeKey;
    private readonly string $currentPath;
    private readonly array $queryParameters;

    public function __construct(int $total, string $pageKey = 'page', string $sizeKey = 'per_page', int $defaultSize = 10) {
        $this->total = max(0, $total);
        $this->pageKey = $pageKey;
        $this->sizeKey = $sizeKey;
        $this->queryParameters = $_GET;
        $defaultSize = in_array($defaultSize, [10, 25, 50], true) ? $defaultSize : 10;
        $requestedSize = self::positiveInteger($_GET[$sizeKey] ?? null, $defaultSize);
        $this->perPage = in_array($requestedSize, [10, 25, 50], true) ? $requestedSize : $defaultSize;
        $this->pages = $this->total === 0 ? 1 : intdiv($this->total - 1, $this->perPage) + 1;
        $this->page = min($this->pages, self::positiveInteger($_GET[$pageKey] ?? null, 1));
        $this->offset = ($this->page - 1) * $this->perPage;

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = is_string($requestUri) ? parse_url($requestUri, PHP_URL_PATH) : false;
        // Always emit a local absolute path, even for a malformed or absolute request URI.
        $this->currentPath = is_string($path) && $path !== ''
            ? '/' . ltrim(str_replace('\\', '/', $path), '/')
            : '/';
    }

    private static function positiveInteger(mixed $value, int $default): int {
        if (!is_string($value) && !is_int($value)) return $default;
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $integer === false ? $default : $integer;
    }

    public function start(): int { return $this->total === 0 ? 0 : $this->offset + 1; }
    public function end(): int { return $this->offset + min($this->perPage, $this->total - $this->offset); }
    public function path(): string { return $this->currentPath; }
    public function query(): array { return $this->queryParameters; }

    public function url(int $page): string {
        $query = $this->queryParameters;
        $query[$this->pageKey] = max(1, min($page, $this->pages));
        return $this->currentPath . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

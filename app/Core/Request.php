<?php

namespace App\Core;

class Request
{
    private array $get;
    private array $post;
    private array $files;
    private array $server;
    private ?array $jsonBody = null;

    public function __construct(
        ?array $get = null,
        ?array $post = null,
        ?array $files = null,
        ?array $server = null
    ) {
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;
    }

    public static function createFromGlobals(): self
    {
        return new self();
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function hasQuery(string $key): bool
    {
        return isset($this->get[$key]);
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function getJsonBody(): array
    {
        if ($this->jsonBody === null) {
            $rawInput = file_get_contents('php://input');
            $decoded = json_decode($rawInput, true);
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonBody;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->getJsonBody();
        if (isset($json[$key])) {
            return $json[$key];
        }
        if (isset($this->post[$key])) {
            return $this->post[$key];
        }
        if (isset($this->get[$key])) {
            return $this->get[$key];
        }
        return $default;
    }

    public function allInput(): array
    {
        $json = $this->getJsonBody();
        if (!empty($json)) {
            return array_merge($this->post, $json);
        }
        return $this->post;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && ($this->files[$key]['error'] === UPLOAD_ERR_OK);
    }
}

<?php

namespace CRMMarketing;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $data = null;

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function json(): array
    {
        header('Content-Type: application/json', true, $this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        return [
            'status' => $this->statusCode,
            'data' => $this->data,
        ];
    }
}
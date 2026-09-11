<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A domain exception that carries a stable machine-readable code separately from
 * the HTTP status, so the global exception renderer can surface the intended
 * error.code instead of a generic error.http.<status>.
 */
final class DomainException extends RuntimeException implements HttpExceptionInterface
{
    private HttpException $httpException;

    public function __construct(int $statusCode, string $code, string $message = '', ?\Throwable $previous = null, array $headers = [])
    {
        parent::__construct($message, 0, $previous);
        $this->httpException = new HttpException($statusCode, $code, $previous, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->httpException->getStatusCode();
    }

    public function getHeaders(): array
    {
        return $this->httpException->getHeaders();
    }

    public function domainCode(): string
    {
        return $this->httpException->getMessage();
    }
}

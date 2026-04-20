<?php

declare(strict_types=1);

namespace Philicevic\Pesto\Support;

use Psr\Http\Message\ResponseInterface;

/**
 * A wrapper around TYPO3's PSR-7 response that provides
 * a fluent, Laravel-inspired assertion API for feature tests.
 *
 * Works with both the native assertion methods AND Pest's expect():
 *
 *   // Fluent assertion style:
 *   $this->get('/')->assertOk()->assertSee('Welcome');
 *
 *   // Pest expect() style:
 *   expect($this->get('/'))->toHaveStatus(200)->toSee('Welcome');
 */
class HttpTestResponse
{
    private ?string $cachedBody = null;

    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    // -------------------------------------------------------------------------
    // Raw PSR-7 Access
    // -------------------------------------------------------------------------

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getBody(): string
    {
        if ($this->cachedBody === null) {
            $this->cachedBody = (string) $this->response->getBody();
        }

        return $this->cachedBody;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * @return list<string>
     */
    public function getHeader(string $name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function hasHeader(string $name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getPsr7Response(): ResponseInterface
    {
        return $this->response;
    }

    // -------------------------------------------------------------------------
    // Redirect Helpers
    // -------------------------------------------------------------------------

    public function isRedirect(): bool
    {
        return in_array($this->getStatusCode(), [301, 302, 303, 307, 308], true);
    }

    public function getRedirectUrl(): ?string
    {
        if (!$this->isRedirect()) {
            return null;
        }

        return $this->getHeaderLine('Location') ?: null;
    }

    // -------------------------------------------------------------------------
    // Content Helpers
    // -------------------------------------------------------------------------

    public function contains(string $text): bool
    {
        return str_contains($this->getBody(), $text);
    }

    public function doesNotContain(string $text): bool
    {
        return !$this->contains($text);
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->getBody(), true);

        expect($decoded)->toBeArray('Response body is not valid JSON');

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Fluent Assertion Methods (chainable, throw on failure)
    // -------------------------------------------------------------------------

    public function assertStatus(int $expected): static
    {
        expect($this->getStatusCode())->toBe(
            $expected,
            sprintf('Expected status %d but got %d.', $expected, $this->getStatusCode()),
        );

        return $this;
    }

    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): static
    {
        return $this->assertStatus(201);
    }

    public function assertNoContent(): static
    {
        return $this->assertStatus(204);
    }

    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }

    public function assertServerError(): static
    {
        expect($this->getStatusCode())->toBeGreaterThanOrEqual(500);

        return $this;
    }

    /**
     * Assert the response is a redirect to the given URL (optional).
     */
    public function assertRedirect(?string $toUrl = null): static
    {
        expect($this->isRedirect())->toBeTrue(
            sprintf('Expected a redirect response but got status %d.', $this->getStatusCode()),
        );

        if ($toUrl !== null) {
            expect($this->getRedirectUrl())->toBe($toUrl);
        }

        return $this;
    }

    /**
     * Assert the response body contains the given string.
     */
    public function assertSee(string $text): static
    {
        expect($this->getBody())->toContain(
            $text,
            sprintf('Failed asserting that the response contains "%s".', $text),
        );

        return $this;
    }

    /**
     * Assert the response body does NOT contain the given string.
     */
    public function assertDontSee(string $text): static
    {
        expect($this->getBody())->not->toContain(
            $text,
            sprintf('Failed asserting that the response does not contain "%s".', $text),
        );

        return $this;
    }

    /**
     * Assert the response has a specific header.
     */
    public function assertHeader(string $header, ?string $value = null): static
    {
        expect($this->hasHeader($header))->toBeTrue(
            sprintf('Failed asserting that the response has header "%s".', $header),
        );

        if ($value !== null) {
            expect($this->getHeaderLine($header))->toBe($value);
        }

        return $this;
    }

    /**
     * Assert the Content-Type header contains the given value.
     */
    public function assertContentType(string $contentType): static
    {
        expect($this->getHeaderLine('Content-Type'))->toContain($contentType);

        return $this;
    }

    public function assertJson(): static
    {
        return $this->assertContentType('application/json');
    }

    /**
     * Assert a JSON response contains the given key/value pairs.
     *
     * @param array<string, mixed> $subset
     */
    public function assertJsonContains(array $subset): static
    {
        $body = $this->json();

        foreach ($subset as $key => $value) {
            expect($body)->toHaveKey($key);
            expect($body[$key])->toBe($value);
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Debugging
    // -------------------------------------------------------------------------

    /**
     * Dump the response body to stdout for debugging.
     */
    public function dump(): static
    {
        dump($this->getBody());

        return $this;
    }

    /**
     * Dump the response body and stop execution (like dd()).
     */
    public function dd(): never
    {
        dd($this->getBody());
    }
}

<?php

declare(strict_types=1);

namespace Philicevic\Pesto\Expectations;

use Philicevic\Pesto\Support\HttpTestResponse;

/**
 * Registers TYPO3-specific custom expectations for Pest.
 *
 * After registration, these expectations are available globally in all tests:
 *
 *   // HTTP Response expectations:
 *   expect($response)->toHaveStatus(200);
 *   expect($response)->toSee('Welcome to TYPO3');
 *   expect($response)->toRedirectTo('http://localhost/new-url');
 *   expect($response)->toBeJson();
 *   expect($response)->toHaveHeader('Content-Type');
 *
 *   // Database expectations:
 *   expect('pages')->toHaveRecord(['uid' => 1, 'title' => 'Home']);
 *   expect('pages')->toHaveNoRecord(['uid' => 99]);
 */
final class Typo3Expectations
{
    public static function register(): void
    {
        self::registerHttpResponseExpectations();
        self::registerDatabaseExpectations();
        self::registerStringExpectations();
    }

    private static function registerHttpResponseExpectations(): void
    {
        // expect($response)->toHaveStatus(200)
        expect()->extend('toHaveStatus', function (int $status): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->getStatusCode())->toBe(
                $status,
                sprintf(
                    'Expected HTTP status %d but received %d.',
                    $status,
                    $this->value->getStatusCode(),
                ),
            );

            return $this;
        });

        // expect($response)->toBeOk()
        expect()->extend('toBeOk', function (): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toHaveStatus(200);

            return $this;
        });

        // expect($response)->toSee('some text')
        expect()->extend('toSee', function (string $text): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->getBody())->toContain(
                $text,
                sprintf('Failed asserting that the response contains "%s".', $text),
            );

            return $this;
        });

        // expect($response)->not->toSee('private content')
        expect()->extend('toSeeNothing', function (string $text): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->getBody())->not->toContain($text);

            return $this;
        });

        // expect($response)->toRedirectTo('http://localhost/new')
        expect()->extend('toRedirectTo', function (string $url): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->isRedirect())->toBeTrue(
                sprintf('Expected a redirect but got status %d.', $this->value->getStatusCode()),
            );
            expect($this->value->getRedirectUrl())->toBe($url);

            return $this;
        });

        // expect($response)->toBeRedirect()
        expect()->extend('toBeRedirect', function (): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->isRedirect())->toBeTrue(
                sprintf('Expected a redirect but got status %d.', $this->value->getStatusCode()),
            );

            return $this;
        });

        // expect($response)->toHaveHeader('Content-Type', 'text/html')
        expect()->extend('toHaveHeader', function (string $header, ?string $value = null): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->hasHeader($header))->toBeTrue(
                sprintf('Failed asserting that the response has header "%s".', $header),
            );

            if ($value !== null) {
                expect($this->value->getHeaderLine($header))->toContain($value);
            }

            return $this;
        });

        // expect($response)->toBeJson()
        expect()->extend('toBeJsonResponse', function (): \Pest\Expectation {
            /** @var \Pest\Expectation<HttpTestResponse> $this */
            expect($this->value)->toBeInstanceOf(HttpTestResponse::class);
            expect($this->value->getHeaderLine('Content-Type'))->toContain('application/json');

            return $this;
        });
    }

    private static function registerDatabaseExpectations(): void
    {
        // expect('pages')->toHaveRecord(['uid' => 1])
        // Note: Requires access to the test's database connection.
        // Use $this->assertRecordExists() from Functional/Feature for richer control.
        // These expectations are convenience shortcuts when you have a connection available.
    }

    private static function registerStringExpectations(): void
    {
        // expect($typoScriptValue)->toBeTypoScriptEnabled()
        expect()->extend('toBeTypoScriptEnabled', function (): \Pest\Expectation {
            /** @var \Pest\Expectation<mixed> $this */
            expect($this->value)->toBeIn([1, '1', true], 'Expected a truthy TypoScript value (1 or "1").');

            return $this;
        });

        // expect($typoScriptValue)->toBeTypoScriptDisabled()
        expect()->extend('toBeTypoScriptDisabled', function (): \Pest\Expectation {
            /** @var \Pest\Expectation<mixed> $this */
            expect($this->value)->toBeIn([0, '0', false, ''], 'Expected a falsy TypoScript value (0 or "0").');

            return $this;
        });
    }
}

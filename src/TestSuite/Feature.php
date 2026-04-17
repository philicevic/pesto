<?php

declare(strict_types=1);

namespace Philicevic\Pesto\TestSuite;

use Philicevic\Pesto\Support\HttpTestResponse;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Base class for TYPO3 feature (HTTP) tests written with Pest.
 *
 * Feature tests make real HTTP requests through TYPO3's internal request
 * handler — no external web server needed. The full TYPO3 stack is involved:
 * routing, middleware, TypoScript, Fluid templates, extensions.
 *
 * Usage in tests/Pest.php:
 *   uses(\Philicevic\Pesto\TestSuite\Feature::class)->in('Feature');
 *
 * Global extension configuration:
 *   Feature::loadExtensions(['my_sitepackage']);
 *
 * Example tests:
 *   it('renders the homepage', function () {
 *       $this->fixture(__DIR__ . '/Fixtures/pages.csv');
 *       $response = $this->get('http://localhost/');
 *       expect($response)->toHaveStatus(200)->toSee('Welcome');
 *   });
 *
 *   it('shows members-only content when logged in', function () {
 *       $this->fixture(__DIR__ . '/Fixtures/pages.csv');
 *       $this->fixture(__DIR__ . '/Fixtures/fe_users.csv');
 *       $response = $this->actingAsFrontendUser(1)->get('http://localhost/members');
 *       expect($response)->toHaveStatus(200)->toSee('Welcome, Member!');
 *   });
 *
 * All database helpers (fixture, getRecord, assertRecordExists, …) are
 * inherited from AbstractTypo3TestCase.
 */
class Feature extends AbstractTypo3TestCase
{
    /**
     * Redeclared so that Feature has its own independent static state,
     * separate from Functional::$defaultExtensions.
     *
     * @var list<non-empty-string>
     */
    protected static array $defaultExtensions = [];

    /** @var list<non-empty-string> */
    protected static array $defaultCoreExtensions = [];

    private ?InternalRequestContext $requestContext = null;

    protected function tearDown(): void
    {
        // Reset authentication context so tests don't bleed into each other.
        $this->requestContext = null;

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // HTTP Request Helpers
    // -------------------------------------------------------------------------

    /**
     * Perform a GET request to the given URL.
     *
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpTestResponse
    {
        return $this->request('GET', $url, [], $headers);
    }

    /**
     * Perform a POST request to the given URL.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public function post(string $url, array $data = [], array $headers = []): HttpTestResponse
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * Perform a PUT request to the given URL.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public function put(string $url, array $data = [], array $headers = []): HttpTestResponse
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * Perform a DELETE request to the given URL.
     *
     * @param array<string, string> $headers
     */
    public function delete(string $url, array $headers = []): HttpTestResponse
    {
        return $this->request('DELETE', $url, [], $headers);
    }

    /**
     * Perform an HTTP request with the given method through TYPO3's internal
     * request handler. All other HTTP methods delegate to this one.
     *
     * @param array<string, mixed>  $data    Request body data (for POST/PUT)
     * @param array<string, string> $headers Additional HTTP headers
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): HttpTestResponse
    {
        $internalRequest = (new InternalRequest($url))->withMethod($method);

        foreach ($headers as $name => $value) {
            $internalRequest = $internalRequest->withAddedHeader($name, $value);
        }

        if ($data !== []) {
            $internalRequest = $internalRequest->withParsedBody($data);
        }

        $response = $this->executeFrontendSubRequest($internalRequest, $this->requestContext);

        return new HttpTestResponse($response);
    }

    // -------------------------------------------------------------------------
    // Authentication Helpers
    // -------------------------------------------------------------------------

    /**
     * Simulate a logged-in frontend user for all subsequent requests in this test.
     * The user must exist in the test database (fe_users table).
     *
     * Resets automatically in tearDown — no manual cleanup needed.
     */
    public function actingAsFrontendUser(int $userId): static
    {
        $this->requestContext = (new InternalRequestContext())->withFrontendUserId($userId);

        return $this;
    }

    /**
     * Simulate a logged-in backend user for subsequent requests (e.g. preview mode).
     */
    public function actingAsBackendUser(int $userId): static
    {
        $this->requestContext = (new InternalRequestContext())->withBackendUserId($userId);

        return $this;
    }

    /**
     * Explicitly reset to an unauthenticated (guest) context.
     */
    public function actingAsGuest(): static
    {
        $this->requestContext = null;

        return $this;
    }
}

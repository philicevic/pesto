<?php

declare(strict_types=1);

namespace Philicevic\Pesto\TestSuite;

use Philicevic\Pesto\Support\HttpTestResponse;
use Symfony\Component\Yaml\Yaml;
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
 *       $response = $this->get('/');
 *       expect($response)->toHaveStatus(200)->toSee('Welcome');
 *   });
 *
 *   it('shows members-only content when logged in', function () {
 *       $this->fixture(__DIR__ . '/Fixtures/pages.csv');
 *       $this->fixture(__DIR__ . '/Fixtures/fe_users.csv');
 *       $response = $this->actingAsFrontendUser(1)->get('/members');
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

    private ?string $currentSiteBase = null;

    protected function tearDown(): void
    {
        // Reset authentication context so tests don't bleed into each other.
        $this->requestContext = null;
        $this->currentSiteBase = null;

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // HTTP Request Helpers
    // -------------------------------------------------------------------------

    /**
     * Target a specific site for subsequent requests in this test.
     * The identifier is used to construct the site base as http://{identifier}.localhost/,
     * matching the URL that SiteConfigWriter assigns to each site in the test instance.
     *
     * Example:
     *   $this->onSite('shop')->get('/')->assertOk();
     */
    public function onSite(string $identifier): static
    {
        $this->currentSiteBase = 'http://' . $identifier . '.localhost/';

        return $this;
    }

    /**
     * Resolve a URL against the current site base.
     *
     * If $url already starts with http:// or https://, it is returned as-is.
     * Otherwise, $currentSiteBase is prepended. If no site has been selected
     * via onSite(), the base is auto-detected from the first subdirectory under
     * {instancePath}/typo3conf/sites/ that contains a config.yaml with a valid
     * string `base` key. The detected value is cached for the duration of the test.
     */
    private function resolveUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if ($this->currentSiteBase === null) {
            $sitesPath = $this->instancePath . '/typo3conf/sites';
            foreach (scandir($sitesPath) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $configFile = $sitesPath . '/' . $entry . '/config.yaml';
                if (!is_dir($sitesPath . '/' . $entry) || !file_exists($configFile)) {
                    continue;
                }
                $config = Yaml::parseFile($configFile);
                if (is_array($config) && isset($config['base']) && is_string($config['base'])) {
                    $this->currentSiteBase = $config['base'];
                    break;
                }
            }
        }

        return ($this->currentSiteBase ?? '') . ltrim($url, '/');
    }

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
        $internalRequest = (new InternalRequest($this->resolveUrl($url)))->withMethod($method);

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

<?php

declare(strict_types=1);

namespace Philicevic\Pesto\TestSuite;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Base class for TYPO3 unit tests written with Pest.
 *
 * Usage in tests/Pest.php:
 *   uses(\Philicevic\Pesto\TestSuite\Unit::class)->in('Unit');
 *
 * Example test:
 *   it('formats something', function () {
 *       $result = (new MyService())->format('hello');
 *       expect($result)->toBe('HELLO');
 *   });
 */
class Unit extends TestCase
{
    /** @var list<callable> Callbacks registered via addTearDownCallback(). */
    private array $tearDownCallbacks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tearDownCallbacks = [];
    }

    protected function tearDown(): void
    {
        // Run any registered cleanup callbacks (e.g. config resets).
        foreach ($this->tearDownCallbacks as $callback) {
            $callback();
        }
        $this->tearDownCallbacks = [];

        // Clean up GeneralUtility singleton instances between tests
        // to avoid state leaking from one test to the next.
        GeneralUtility::purgeInstances();

        parent::tearDown();
    }

    /**
     * Create a mock or stub of a TYPO3 singleton and register it
     * in GeneralUtility so it's returned by makeInstance().
     *
     * @template T of object
     * @param class-string<T> $className
     * @param T $instance
     */
    protected function registerSingleton(string $className, object $instance): void
    {
        GeneralUtility::setSingletonInstance($className, $instance);
    }

    /**
     * Register a non-singleton instance with GeneralUtility::makeInstance().
     *
     * @template T of object
     * @param class-string<T> $className
     * @param T $instance
     */
    protected function registerInstance(string $className, object $instance): void
    {
        GeneralUtility::addInstance($className, $instance);
    }

    /**
     * Get a TYPO3 configuration value (GLOBALS['TYPO3_CONF_VARS']).
     */
    protected function typo3Config(string $path): mixed
    {
        $keys = explode('/', trim($path, '/'));
        $value = $GLOBALS['TYPO3_CONF_VARS'] ?? [];

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Temporarily override a TYPO3 configuration value for the duration of the test.
     * The original value is automatically restored in tearDown.
     */
    protected function withTypo3Config(string $path, mixed $value): void
    {
        $keys = explode('/', trim($path, '/'));
        $original = $this->typo3Config($path);

        // Set the new value
        $target = &$GLOBALS['TYPO3_CONF_VARS'];
        foreach ($keys as $key) {
            if (!isset($target[$key]) || !is_array($target[$key])) {
                $target[$key] = [];
            }
            $target = &$target[$key];
        }
        $target = $value;

        // Restore in tearDown
        $this->addTearDownCallback(function () use ($keys, $original): void {
            $target = &$GLOBALS['TYPO3_CONF_VARS'];
            foreach (array_slice($keys, 0, -1) as $key) {
                $target = &$target[$key];
            }
            $target[end($keys)] = $original;
        });
    }

    /**
     * Register a callback to be executed in tearDown.
     * Useful for cleanup logic inside tests or helpers.
     */
    private function addTearDownCallback(callable $callback): void
    {
        $this->tearDownCallbacks[] = $callback;
    }
}

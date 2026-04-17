<?php

declare(strict_types=1);

namespace Philicevic\Pesto\TestSuite;

use Philicevic\Pesto\SiteConfig\SiteConfigWriter;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Abstract base class shared by all Pesto test suites that require
 * a TYPO3 instance and a database (Functional and Feature).
 *
 * Provides:
 *   - Global extension configuration via static loadExtensions()
 *   - Automatic site configuration linking via static setSiteConfigurationPath()
 *   - Database fixture helpers: fixture(), assertDatabase(), getRecord()
 *   - Database assertion helpers: assertRecordExists(), assertRecordMissing()
 *
 * Do not use this class directly in tests — use Functional or Feature instead.
 */
abstract class AbstractTypo3TestCase extends FunctionalTestCase
{
    /**
     * Extensions loaded by default for ALL tests of this suite.
     * Redeclared in each child class so that Functional and Feature
     * maintain independent static state via late static binding.
     *
     * @var list<non-empty-string>
     */
    protected static array $defaultExtensions = [];

    /**
     * Additional TYPO3 core extensions loaded by default.
     * Redeclared in each child class for the same reason.
     *
     * @var list<non-empty-string>
     */
    protected static array $defaultCoreExtensions = [];

    /**
     * Path to the site configuration directory, relative to the project root.
     * On setUp(), this directory is copied into the test instance as typo3conf/sites,
     * with the base URL rewritten to http://{identifier}.localhost/ per site.
     * TYPO3's site finder can then resolve routes.
     *
     * Set to null to disable. Override via setSiteConfigurationPath() in tests/Pest.php.
     */
    protected static ?string $siteConfigurationPath = 'config/sites';

    /**
     * Configure the site configuration directory for all tests.
     * Pass null to disable automatic site configuration copying.
     * Call this in tests/Pest.php before running tests.
     *
     * Example:
     *   AbstractTypo3TestCase::setSiteConfigurationPath('config/sites');
     *   AbstractTypo3TestCase::setSiteConfigurationPath(null); // disable
     */
    public static function setSiteConfigurationPath(?string $path): void
    {
        static::$siteConfigurationPath = $path;
    }

    /**
     * Configure extensions that should be loaded for all tests of this suite.
     * Call this in your tests/Pest.php before running tests.
     *
     * Because this method uses late static binding (static::), calling
     * Functional::loadExtensions() and Feature::loadExtensions() writes
     * to each class's own independent static property.
     *
     * @param list<non-empty-string> $extensions Local extension keys
     */
    public static function loadExtensions(array $extensions): void
    {
        static::$defaultExtensions = $extensions;
    }

    /**
     * Configure additional TYPO3 core extensions for all tests of this suite.
     *
     * @param list<non-empty-string> $extensions Core extension keys (e.g. 'frontend', 'fluid')
     */
    public static function loadCoreExtensions(array $extensions): void
    {
        static::$defaultCoreExtensions = $extensions;
    }

    protected function setUp(): void
    {
        // Merge globally configured default extensions with any per-class overrides.
        // static:: ensures we read from the correct child class's static property.
        $this->testExtensionsToLoad = array_unique(array_merge(
            static::$defaultExtensions,
            $this->testExtensionsToLoad,
        ));

        $this->coreExtensionsToLoad = array_unique(array_merge(
            static::$defaultCoreExtensions,
            $this->coreExtensionsToLoad,
        ));

        parent::setUp();

        // Copy the project site configuration into the test instance so that
        // TYPO3's site finder can resolve routes. ComposerPackageManager reads
        // from Composer's installed package metadata, so it always returns the
        // real project root regardless of what the testing framework sets in env vars.
        // The copied directory is recursively removed by FunctionalTestCase::tearDown().
        if (static::$siteConfigurationPath !== null) {
            $projectRoot = (new ComposerPackageManager())->getRootPath();
            $sourcePath = rtrim($projectRoot, '/') . '/' . ltrim(static::$siteConfigurationPath, '/');
            $targetPath = $this->instancePath . '/typo3conf/sites';
            if (is_dir($sourcePath)) {
                (new SiteConfigWriter())->write($sourcePath, $targetPath);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Fixture Helpers
    // -------------------------------------------------------------------------

    /**
     * Import a CSV fixture file into the test database.
     * The CSV filename determines the table name (e.g. pages.csv → pages table).
     */
    public function fixture(string $csvPath): static
    {
        $this->importCSVDataSet($csvPath);

        return $this;
    }

    /**
     * Assert that the current database state matches a given CSV fixture file.
     */
    public function assertDatabase(string $csvPath): static
    {
        $this->assertCSVDataSet($csvPath);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Record Query Helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve a single record from a database table by uid.
     *
     * @return array<string, mixed>|null
     */
    public function getRecord(string $table, int $uid): ?array
    {
        $connection = $this->getConnectionPool()->getConnectionForTable($table);
        $row = $connection->select(
            ['*'],
            $table,
            ['uid' => $uid],
        )->fetchAssociative();

        return $row !== false ? $row : null;
    }

    /**
     * Retrieve a page record by uid.
     *
     * @return array<string, mixed>|null
     */
    public function getPageRecord(int $uid): ?array
    {
        return $this->getRecord('pages', $uid);
    }

    // -------------------------------------------------------------------------
    // Record Assertion Helpers
    // -------------------------------------------------------------------------

    /**
     * Assert that at least one record matching the conditions exists in the database.
     *
     * @param array<string, mixed> $conditions Column => value conditions
     */
    public function assertRecordExists(string $table, array $conditions): static
    {
        $connection = $this->getConnectionPool()->getConnectionForTable($table);
        $count = $connection->count('uid', $table, $conditions);

        expect($count)->toBeGreaterThan(0, sprintf(
            'Failed asserting that a record exists in table "%s" matching: %s',
            $table,
            json_encode($conditions),
        ));

        return $this;
    }

    /**
     * Assert that no record matching the conditions exists in the database.
     *
     * @param array<string, mixed> $conditions Column => value conditions
     */
    public function assertRecordMissing(string $table, array $conditions): static
    {
        $connection = $this->getConnectionPool()->getConnectionForTable($table);
        $count = $connection->count('uid', $table, $conditions);

        expect($count)->toBe(0, sprintf(
            'Failed asserting that no record exists in table "%s" matching: %s',
            $table,
            json_encode($conditions),
        ));

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Philicevic\Pesto\TestSuite;

/**
 * Base class for TYPO3 functional tests written with Pest.
 *
 * Functional tests boot a real TYPO3 instance with a database and test
 * PHP logic directly — repositories, services, domain classes — without
 * going through the HTTP layer.
 *
 * Usage in tests/Pest.php:
 *   uses(\Philicevic\Pesto\TestSuite\Functional::class)->in('Functional');
 *
 * Global extension configuration:
 *   Functional::loadExtensions(['my_sitepackage', 'my_extension']);
 *
 * Example test:
 *   it('finds published news records', function () {
 *       $this->fixture(__DIR__ . '/Fixtures/tx_news_domain_model_news.csv');
 *
 *       $repo = typo3(\GeorgRinger\News\Domain\Repository\NewsRepository::class);
 *       $result = $repo->findAll();
 *
 *       expect($result)->toHaveCount(3);
 *   });
 *
 * All database helpers (fixture, getRecord, assertRecordExists, …) are
 * inherited from AbstractTypo3TestCase.
 */
class Functional extends AbstractTypo3TestCase
{
    /**
     * Redeclared so that Functional has its own independent static state,
     * separate from Feature::$defaultExtensions.
     *
     * @var list<non-empty-string>
     */
    protected static array $defaultExtensions = [];

    /** @var list<non-empty-string> */
    protected static array $defaultCoreExtensions = [];
}

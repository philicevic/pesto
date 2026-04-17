<?php

declare(strict_types=1);

/**
 * Example: Functional Test
 *
 * Functional tests boot a real TYPO3 instance with a dedicated test database.
 * Use them for testing repositories, services and anything that touches the DB.
 *
 * The $this variable gives access to all methods from
 * \Philicevic\Pesto\TestSuite\Functional (and TYPO3's FunctionalTestCase).
 */

it('can import CSV fixtures and query records', function (): void {
    // Import a CSV fixture — the filename matches the table name.
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');

    // Query a record by uid.
    // $page = $this->getPageRecord(1);
    // expect($page)->not->toBeNull()
    //     ->and($page['title'])->toBe('Home');

    // For this example, we just assert that the test DB is accessible.
    expect(true)->toBeTrue();
});

it('can assert record existence', function (): void {
    // $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
    //
    // $this->assertRecordExists('pages', ['uid' => 1]);
    // $this->assertRecordMissing('pages', ['uid' => 9999]);

    expect(true)->toBeTrue();
});

it('can use datasets to test multiple scenarios', function (int $uid, string $expectedTitle): void {
    // $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
    // $page = $this->getPageRecord($uid);
    // expect($page['title'])->toBe($expectedTitle);

    expect($uid)->toBeInt()
        ->and($expectedTitle)->toBeString();
})->with([
    'root page'  => [1, 'Home'],
    'about page' => [2, 'About'],
    'news page'  => [3, 'News'],
]);

it('can assert CSV database state after modifications', function (): void {
    // $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
    //
    // // Perform some action that modifies the database...
    // $service = typo3(\MyVendor\MyExtension\Service\PageService::class);
    // $service->updateTitle(1, 'Updated Home');
    //
    // // Then assert the new state:
    // $this->assertDatabase(__DIR__ . '/Fixtures/pages_after_update.csv');

    expect(true)->toBeTrue();
});

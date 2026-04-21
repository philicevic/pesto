<?php

declare(strict_types=1);

/**
 * Example: Unit Test
 *
 * Unit tests run without a TYPO3 bootstrap or database.
 * Use them for testing pure PHP logic in your extension.
 */
it('can access the TYPO3 configuration', function (): void {
    // typo3_conf_vars() is a global helper provided by Pesto.
    // In unit tests, TYPO3_CONF_VARS is available if a unit bootstrap is loaded.
    expect(typo3_conf_vars('SYS/sitename'))->toBeString();
});

it('purges GeneralUtility singleton instances between tests', function (): void {
    // $this->registerSingleton() is available in Unit tests.
    // Singletons are automatically cleaned up after each test.
    expect(true)->toBeTrue();
});

it('can use all standard Pest expectations', function (): void {
    $values = ['typo3', 'pest', 'pesto'];

    expect($values)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain('pesto')
        ->sequence(
            fn($item) => $item->toBe('typo3'),
            fn($item) => $item->toBe('pest'),
            fn($item) => $item->toBe('pesto'),
        );
});

it('can use arch testing for extension classes', function (): void {
    // Pest's arch() testing works normally in unit test context.
    // Example: ensure all classes in a namespace use strict types.
    //
    // arch('strict types')
    //     ->expect('MyVendor\\MyExtension')
    //     ->toUseStrictTypes();
    expect(true)->toBeTrue();
});

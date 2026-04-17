<?php

declare(strict_types=1);

/**
 * Global Pest helper functions for TYPO3 tests.
 *
 * These functions are available in all tests without any import.
 * They are automatically loaded via composer.json's autoload.files.
 */

if (!function_exists('typo3')) {
    /**
     * Access the TYPO3 dependency injection container.
     * Returns a specific service if a class/interface name is given.
     *
     * @template T of object
     * @param class-string<T>|null $serviceId
     * @return ($serviceId is null ? \Psr\Container\ContainerInterface : T)
     */
    function typo3(?string $serviceId = null): object
    {
        $container = \TYPO3\CMS\Core\Utility\GeneralUtility::getContainer();

        if ($serviceId === null) {
            return $container;
        }

        return $container->get($serviceId);
    }
}

if (!function_exists('typo3_extension_path')) {
    /**
     * Get the absolute filesystem path to an extension.
     *
     * @example typo3_extension_path('my_extension') → '/var/www/html/packages/my_extension/'
     */
    function typo3_extension_path(string $extensionKey): string
    {
        return \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($extensionKey);
    }
}

if (!function_exists('typo3_conf_vars')) {
    /**
     * Read a value from $GLOBALS['TYPO3_CONF_VARS'] using a slash-separated path.
     *
     * @example typo3_conf_vars('FE/pageNotFoundOnCHashError') → true
     */
    function typo3_conf_vars(string $path): mixed
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
}

if (!function_exists('typo3_site')) {
    /**
     * Get a TYPO3 Site object by its identifier.
     *
     * @example typo3_site('main') → Site object
     */
    function typo3_site(string $identifier): \TYPO3\CMS\Core\Site\Entity\Site
    {
        /** @var \TYPO3\CMS\Core\Site\SiteFinder $siteFinder */
        $siteFinder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Site\SiteFinder::class,
        );

        return $siteFinder->getSiteByIdentifier($identifier);
    }
}

if (!function_exists('typo3_version')) {
    /**
     * Get the current TYPO3 version string.
     *
     * @example typo3_version() → '13.4.3'
     */
    function typo3_version(): string
    {
        return \TYPO3\CMS\Core\Information\Typo3Version::getReleaseString()
            ?? \TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version();
    }
}

if (!function_exists('typo3_version_satisfies')) {
    /**
     * Check if the current TYPO3 version satisfies a version constraint.
     * Uses PHP's version_compare syntax.
     *
     * @example typo3_version_satisfies('>=', '13.0.0') → true
     */
    function typo3_version_satisfies(string $operator, string $version): bool
    {
        $current = \TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version();

        return version_compare($current, $version, $operator);
    }
}

<?php

declare(strict_types=1);

namespace Philicevic\Pesto\SiteConfig;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Copies TYPO3 site configurations into a test instance, rewriting
 * the base URL to http://{identifier}.localhost/ and stripping baseVariants
 * so TYPO3's site finder can resolve routes during tests.
 */
final class SiteConfigWriter
{
    public function write(string $sourcePath, string $targetPath): void
    {
        $sourcePath = rtrim($sourcePath, '/');
        $targetPath = rtrim($targetPath, '/');

        if (!is_dir($sourcePath)) {
            throw new \RuntimeException(sprintf(
                'Cannot read site configuration directory: %s',
                $sourcePath
            ));
        }

        $entries = scandir($sourcePath);
        if ($entries === false) {
            throw new \RuntimeException(sprintf(
                'Cannot read site configuration directory: %s',
                $sourcePath
            ));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourceDir = $sourcePath . '/' . $entry;
            $sourceConfigFile = $sourceDir . '/config.yaml';

            if (!is_dir($sourceDir) || !file_exists($sourceConfigFile)) {
                continue;
            }

            try {
                $config = Yaml::parseFile($sourceConfigFile);
            } catch (ParseException $e) {
                throw new \RuntimeException(sprintf(
                    'Malformed site configuration file "%s": %s',
                    $sourceConfigFile,
                    $e->getMessage()
                ), 0, $e);
            }

            if (!is_array($config)) {
                throw new \RuntimeException(sprintf(
                    'Malformed site configuration file "%s": expected a YAML mapping',
                    $sourceConfigFile
                ));
            }

            $config['base'] = 'http://' . $entry . '.localhost/';
            unset($config['baseVariants']);

            $targetDir = $targetPath . '/' . $entry;
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new \RuntimeException(sprintf(
                    'Cannot create target directory: %s',
                    $targetDir
                ));
            }

            $targetFile = $targetDir . '/config.yaml';
            if (file_put_contents($targetFile, Yaml::dump($config, 6, 2)) === false) {
                throw new \RuntimeException(sprintf(
                    'Cannot write site configuration file: %s',
                    $targetFile
                ));
            }
        }
    }
}

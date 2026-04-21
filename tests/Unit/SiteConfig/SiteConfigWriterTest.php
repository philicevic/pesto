<?php

declare(strict_types=1);

use Philicevic\Pesto\SiteConfig\SiteConfigWriter;
use Symfony\Component\Yaml\Yaml;

describe('SiteConfigWriter', function (): void {
    beforeEach(function (): void {
        $this->sourcePath = sys_get_temp_dir() . '/pesto-src-' . uniqid();
        $this->targetPath = sys_get_temp_dir() . '/pesto-target-' . uniqid();
    });

    afterEach(function (): void {
        foreach ([$this->sourcePath, $this->targetPath] as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($path);
        }
    });

    it('rewrites base to subdomain localhost URL', function (): void {
        mkdir($this->sourcePath . '/mysite', 0755, true);
        file_put_contents($this->sourcePath . '/mysite/config.yaml', Yaml::dump([
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
            'languages' => [['languageId' => 0, 'base' => '/']],
        ], 6, 2));

        (new SiteConfigWriter())->write($this->sourcePath, $this->targetPath);

        expect(Yaml::parseFile($this->targetPath . '/mysite/config.yaml')['base'])
            ->toBe('http://mysite.localhost/');
    });

    it('strips baseVariants', function (): void {
        mkdir($this->sourcePath . '/mysite', 0755, true);
        file_put_contents($this->sourcePath . '/mysite/config.yaml', Yaml::dump([
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
            'baseVariants' => [
                ['base' => 'http://dev.localhost/', 'condition' => 'applicationContext == "Development"'],
            ],
        ], 6, 2));

        (new SiteConfigWriter())->write($this->sourcePath, $this->targetPath);

        expect(Yaml::parseFile($this->targetPath . '/mysite/config.yaml'))
            ->not->toHaveKey('baseVariants');
    });

    it('preserves all other config keys', function (): void {
        mkdir($this->sourcePath . '/mysite', 0755, true);
        file_put_contents($this->sourcePath . '/mysite/config.yaml', Yaml::dump([
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
            'languages' => [['languageId' => 0, 'base' => '/']],
            'routes' => [['route' => 'sitemap.xml', 'type' => 'uri']],
        ], 6, 2));

        (new SiteConfigWriter())->write($this->sourcePath, $this->targetPath);

        $result = Yaml::parseFile($this->targetPath . '/mysite/config.yaml');
        expect($result['rootPageId'])->toBe(1)
            ->and($result['languages'][0]['base'])->toBe('/')
            ->and($result['routes'][0]['route'])->toBe('sitemap.xml');
    });

    it('handles multiple sites independently', function (): void {
        foreach (['site-one', 'site-two'] as $id) {
            mkdir($this->sourcePath . '/' . $id, 0755, true);
            file_put_contents($this->sourcePath . '/' . $id . '/config.yaml', Yaml::dump([
                'rootPageId' => 1,
                'base' => 'https://www.example.com/',
            ], 6, 2));
        }

        (new SiteConfigWriter())->write($this->sourcePath, $this->targetPath);

        expect(Yaml::parseFile($this->targetPath . '/site-one/config.yaml')['base'])
            ->toBe('http://site-one.localhost/')
            ->and(Yaml::parseFile($this->targetPath . '/site-two/config.yaml')['base'])
            ->toBe('http://site-two.localhost/');
    });

    it('throws a RuntimeException on malformed YAML', function (): void {
        mkdir($this->sourcePath . '/badsite', 0755, true);
        file_put_contents($this->sourcePath . '/badsite/config.yaml', 'invalid: yaml: [unclosed');

        expect(fn() => (new SiteConfigWriter())->write($this->sourcePath, $this->targetPath))
            ->toThrow(\RuntimeException::class);
    });

    it('throws a RuntimeException when source directory cannot be read', function (): void {
        expect(fn() => (new SiteConfigWriter())->write('/nonexistent/path', $this->targetPath))
            ->toThrow(\RuntimeException::class);
    });
});

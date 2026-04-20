<?php

declare(strict_types=1);

use Philicevic\Pesto\TestSuite\Feature;

describe('Feature::resolveUrl()', function (): void {
    beforeEach(function (): void {
        // Create a Feature instance without running setUp() (no TYPO3 bootstrap needed).
        $this->feature = new Feature('test');

        // Expose resolveUrl() via reflection.
        $method = new ReflectionMethod(Feature::class, 'resolveUrl');
        $this->resolveUrl = fn(string $url) => $method->invoke($this->feature, $url);

        // Expose instancePath via reflection so we can set it without a TYPO3 bootstrap.
        $prop = new ReflectionProperty(Feature::class, 'instancePath');
        $this->setInstancePath = fn(string $path) => $prop->setValue($this->feature, $path);

        // Shared temp directory for tests that need a real filesystem.
        $this->tmpDir = null;
    });

    afterEach(function (): void {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $configFile = $this->tmpDir . '/typo3conf/sites/mysite/config.yaml';
            $sitesDir = $this->tmpDir . '/typo3conf/sites/mysite';
            if (file_exists($configFile)) {
                unlink($configFile);
            }
            if (is_dir($sitesDir)) {
                rmdir($sitesDir);
            }
            if (is_dir($this->tmpDir . '/typo3conf/sites')) {
                rmdir($this->tmpDir . '/typo3conf/sites');
            }
            if (is_dir($this->tmpDir . '/typo3conf')) {
                rmdir($this->tmpDir . '/typo3conf');
            }
            rmdir($this->tmpDir);
        }
    });

    it('returns absolute URLs unchanged', function (string $url): void {
        $result = ($this->resolveUrl)($url);
        expect($result)->toBe($url);
    })->with([
        'http URL' => ['http://example.com/foo'],
        'https URL' => ['https://secure.example.com/'],
    ]);

    it('prepends the site base set via onSite()', function (): void {
        $this->feature->onSite('shop');
        $result = ($this->resolveUrl)('/products');
        expect($result)->toBe('http://shop.localhost/products');
    });

    it('strips leading slash when prepending site base', function (): void {
        $this->feature->onSite('blog');
        $result = ($this->resolveUrl)('/posts/hello');
        expect($result)->toBe('http://blog.localhost/posts/hello');
    });

    it('auto-detects single site base from config.yaml', function (): void {
        $this->tmpDir = sys_get_temp_dir() . '/pesto-test-' . uniqid();
        $sitesDir = $this->tmpDir . '/typo3conf/sites/mysite';
        mkdir($sitesDir, 0755, true);
        file_put_contents($sitesDir . '/config.yaml', "base: 'http://mysite.localhost/'\n");

        ($this->setInstancePath)($this->tmpDir);

        $result = ($this->resolveUrl)('/home');
        expect($result)->toBe('http://mysite.localhost/home');
    });

    it('falls back to an empty base when typo3conf/sites/ is absent, resulting in a TYPO3 404', function (): void {
        // This is intentional: tests that need site routing will fail with a
        // TYPO3 404 when the sites directory is absent, which is the correct signal.
        ($this->setInstancePath)('/nonexistent/path');
        $result = ($this->resolveUrl)('/home');
        expect($result)->toBe('home');
    });
});

# Automatic Site Config Rewriting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pesto automatically copies and rewrites TYPO3 site configs into the test instance so developers never need to manually add Testing base variants.

**Architecture:** A new `SiteConfigWriter` class reads each `config/sites/{identifier}/config.yaml`, rewrites `base` to `http://{identifier}.localhost/`, strips `baseVariants`, and writes the result into the test instance. `AbstractTypo3TestCase` calls this instead of symlinking. `Feature` gains `onSite()` and auto-resolves relative request URLs against the active site base.

**Tech Stack:** PHP 8.2+, `symfony/yaml` (available transitively via `typo3/cms-core`), Pest 3, TYPO3 Testing Framework 8.

---

### Task 1: Create `SiteConfigWriter`

**Files:**
- Create: `src/SiteConfig/SiteConfigWriter.php`
- Create: `tests/Unit/SiteConfig/SiteConfigWriterTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/SiteConfig/SiteConfigWriterTest.php`:

```php
<?php

declare(strict_types=1);

use Philicevic\Pesto\SiteConfig\SiteConfigWriter;
use Symfony\Component\Yaml\Yaml;

function makeTempSiteDir(string $identifier, array $config): string
{
    $root = sys_get_temp_dir() . '/pesto-src-' . uniqid();
    mkdir($root . '/' . $identifier, 0755, true);
    file_put_contents($root . '/' . $identifier . '/config.yaml', Yaml::dump($config, 6, 2));
    return $root;
}

function removeTempDir(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

it('rewrites base to subdomain localhost URL', function (): void {
    $sourcePath = makeTempSiteDir('mysite', [
        'rootPageId' => 1,
        'base' => 'https://www.example.com/',
        'languages' => [['languageId' => 0, 'base' => '/']],
    ]);
    $targetPath = sys_get_temp_dir() . '/pesto-target-' . uniqid();

    (new SiteConfigWriter())->write($sourcePath, $targetPath);

    $result = Yaml::parseFile($targetPath . '/mysite/config.yaml');
    expect($result['base'])->toBe('http://mysite.localhost/');

    removeTempDir($sourcePath);
    removeTempDir($targetPath);
});

it('strips baseVariants', function (): void {
    $sourcePath = makeTempSiteDir('mysite', [
        'rootPageId' => 1,
        'base' => 'https://www.example.com/',
        'baseVariants' => [
            ['base' => 'http://dev.localhost/', 'condition' => 'applicationContext == "Development"'],
        ],
    ]);
    $targetPath = sys_get_temp_dir() . '/pesto-target-' . uniqid();

    (new SiteConfigWriter())->write($sourcePath, $targetPath);

    $result = Yaml::parseFile($targetPath . '/mysite/config.yaml');
    expect($result)->not->toHaveKey('baseVariants');

    removeTempDir($sourcePath);
    removeTempDir($targetPath);
});

it('preserves all other config keys', function (): void {
    $sourcePath = makeTempSiteDir('mysite', [
        'rootPageId' => 1,
        'base' => 'https://www.example.com/',
        'languages' => [['languageId' => 0, 'base' => '/']],
        'routes' => [['route' => 'sitemap.xml', 'type' => 'uri']],
    ]);
    $targetPath = sys_get_temp_dir() . '/pesto-target-' . uniqid();

    (new SiteConfigWriter())->write($sourcePath, $targetPath);

    $result = Yaml::parseFile($targetPath . '/mysite/config.yaml');
    expect($result['rootPageId'])->toBe(1)
        ->and($result['languages'][0]['base'])->toBe('/')
        ->and($result['routes'][0]['route'])->toBe('sitemap.xml');

    removeTempDir($sourcePath);
    removeTempDir($targetPath);
});

it('handles multiple sites independently', function (): void {
    $sourcePath = sys_get_temp_dir() . '/pesto-src-' . uniqid();
    foreach (['site-one', 'site-two'] as $id) {
        mkdir($sourcePath . '/' . $id, 0755, true);
        file_put_contents($sourcePath . '/' . $id . '/config.yaml', Yaml::dump([
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
        ], 6, 2));
    }
    $targetPath = sys_get_temp_dir() . '/pesto-target-' . uniqid();

    (new SiteConfigWriter())->write($sourcePath, $targetPath);

    expect(Yaml::parseFile($targetPath . '/site-one/config.yaml')['base'])->toBe('http://site-one.localhost/')
        ->and(Yaml::parseFile($targetPath . '/site-two/config.yaml')['base'])->toBe('http://site-two.localhost/');

    removeTempDir($sourcePath);
    removeTempDir($targetPath);
});

it('throws a RuntimeException on malformed YAML', function (): void {
    $sourcePath = sys_get_temp_dir() . '/pesto-src-' . uniqid();
    mkdir($sourcePath . '/badsite', 0755, true);
    file_put_contents($sourcePath . '/badsite/config.yaml', "invalid: yaml: [unclosed");
    $targetPath = sys_get_temp_dir() . '/pesto-target-' . uniqid();

    expect(fn() => (new SiteConfigWriter())->write($sourcePath, $targetPath))
        ->toThrow(\RuntimeException::class);

    removeTempDir($sourcePath);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /path/to/pesto
./vendor/bin/pest tests/Unit/SiteConfig/SiteConfigWriterTest.php
```

Expected: FAIL — `Class "Philicevic\Pesto\SiteConfig\SiteConfigWriter" not found`

- [ ] **Step 3: Implement `SiteConfigWriter`**

Create `src/SiteConfig/SiteConfigWriter.php`:

```php
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
                    'Malformed site configuration file: %s',
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

            file_put_contents(
                $targetDir . '/config.yaml',
                Yaml::dump($config, 6, 2)
            );
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Unit/SiteConfig/SiteConfigWriterTest.php
```

Expected: 5 passed

- [ ] **Step 5: Commit**

```bash
git add src/SiteConfig/SiteConfigWriter.php tests/Unit/SiteConfig/SiteConfigWriterTest.php
git commit -m "feat: add SiteConfigWriter for test instance site config rewriting"
```

---

### Task 2: Wire `SiteConfigWriter` into `AbstractTypo3TestCase`

**Files:**
- Modify: `src/TestSuite/AbstractTypo3TestCase.php`

- [ ] **Step 1: Replace the symlink with `SiteConfigWriter::write()`**

In `src/TestSuite/AbstractTypo3TestCase.php`, update the `setUp()` method. Replace the existing site config block:

```php
// OLD — remove this
if (static::$siteConfigurationPath !== null) {
    $projectRoot = (new ComposerPackageManager())->getRootPath();
    $sourcePath = rtrim($projectRoot, '/') . '/' . ltrim(static::$siteConfigurationPath, '/');
    $targetPath = $this->instancePath . '/typo3conf/sites';
    if (is_dir($sourcePath) && !file_exists($targetPath)) {
        symlink($sourcePath, $targetPath);
    }
}
```

With:

```php
// NEW
if (static::$siteConfigurationPath !== null) {
    $projectRoot = (new ComposerPackageManager())->getRootPath();
    $sourcePath = rtrim($projectRoot, '/') . '/' . ltrim(static::$siteConfigurationPath, '/');
    $targetPath = $this->instancePath . '/typo3conf/sites';
    if (is_dir($sourcePath)) {
        (new SiteConfigWriter())->write($sourcePath, $targetPath);
    }
}
```

Also add the import at the top of the file alongside the existing imports:

```php
use Philicevic\Pesto\SiteConfig\SiteConfigWriter;
```

- [ ] **Step 2: Run the full pesto test suite to verify nothing is broken**

```bash
./vendor/bin/pest
```

Expected: all existing tests pass

- [ ] **Step 3: Commit**

```bash
git add src/TestSuite/AbstractTypo3TestCase.php
git commit -m "feat: replace site config symlink with SiteConfigWriter in setUp"
```

---

### Task 3: Add `onSite()` and URL auto-resolution to `Feature`

**Files:**
- Modify: `src/TestSuite/Feature.php`

- [ ] **Step 1: Add `$currentSiteBase` property, `onSite()`, `resolveUrl()`, and update `tearDown()` and `request()`**

Open `src/TestSuite/Feature.php`. Make the following changes:

Add `use Symfony\Component\Yaml\Yaml;` to the imports at the top.

Add the property after `private ?InternalRequestContext $requestContext = null;`:

```php
private ?string $currentSiteBase = null;
```

Update `tearDown()` to reset both properties:

```php
protected function tearDown(): void
{
    $this->requestContext = null;
    $this->currentSiteBase = null;

    parent::tearDown();
}
```

Add `onSite()` after the `tearDown()` method:

```php
// -------------------------------------------------------------------------
// Site Helpers
// -------------------------------------------------------------------------

/**
 * Target a specific site by its identifier for subsequent requests in this test.
 * Requests with relative URLs will be resolved against http://{identifier}.localhost/.
 * Resets automatically in tearDown.
 */
public function onSite(string $identifier): static
{
    $this->currentSiteBase = 'http://' . $identifier . '.localhost';

    return $this;
}
```

Add the private `resolveUrl()` method before the HTTP Request Helpers section:

```php
/**
 * Resolve a URL for use in an InternalRequest.
 * URLs that already include a scheme and host are returned unchanged.
 * Relative URLs are prefixed with the current site base. If no site has been
 * set via onSite(), the base is auto-detected from the first site found in
 * the test instance's typo3conf/sites/ directory.
 */
private function resolveUrl(string $url): string
{
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }

    if ($this->currentSiteBase === null) {
        $sitesPath = $this->instancePath . '/typo3conf/sites';
        if (is_dir($sitesPath)) {
            $entries = array_values(array_filter(
                scandir($sitesPath) ?: [],
                fn(string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($sitesPath . '/' . $entry)
            ));
            if ($entries !== []) {
                $config = Yaml::parseFile($sitesPath . '/' . $entries[0] . '/config.yaml');
                $this->currentSiteBase = rtrim((string)($config['base'] ?? ''), '/');
            }
        }
    }

    return rtrim($this->currentSiteBase ?? '', '/') . '/' . ltrim($url, '/');
}
```

Update the `request()` method to call `resolveUrl()`:

```php
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
```

- [ ] **Step 2: Run the full pesto test suite**

```bash
./vendor/bin/pest
```

Expected: all tests pass

- [ ] **Step 3: Commit**

```bash
git add src/TestSuite/Feature.php
git commit -m "feat: add onSite() and auto URL resolution to Feature test case"
```

---

### Task 4: Update `CLAUDE.md` in pesto

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Remove the stale `linkPaths()` reference and document the new API**

In `CLAUDE.md`, find the paragraph that reads:

> `AbstractTypo3TestCase` owns the shared infrastructure: global extension registration (`loadExtensions()`, `loadCoreExtensions()`), path linking (`linkPaths()`), and all DB helpers (`fixture()`, `assertDatabase()`, `getRecord()`, `assertRecordExists()`, `assertRecordMissing()`).

Replace it with:

> `AbstractTypo3TestCase` owns the shared infrastructure: global extension registration (`loadExtensions()`, `loadCoreExtensions()`), automatic site config rewriting (`setSiteConfigurationPath()`), and all DB helpers (`fixture()`, `assertDatabase()`, `getRecord()`, `assertRecordExists()`, `assertRecordMissing()`). On each `setUp()` it copies the project's `config/sites/` into the test instance via `SiteConfigWriter`, rewriting each site's `base` to `http://{identifier}.localhost/` so TYPO3's site finder resolves routes. `Feature` tests can target a specific site with `$this->onSite('identifier')` or rely on auto-detection for single-site projects.

Also add `Feature` resets `$currentSiteBase` in `tearDown` to the key conventions section alongside the existing `$requestContext` note:

Find:
> `Feature` resets `$requestContext` in `tearDown` so auth state (`actingAsFrontendUser` etc.) never bleeds into the next test.

Replace with:
> `Feature` resets both `$requestContext` and `$currentSiteBase` in `tearDown` so auth state and site context never bleed into the next test.

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md to reflect new site config API"
```

---

### Task 5: Update the flstad-website integration

**Files:**
- Modify: `/path/to/flstad-website/config/sites/flstad/config.yaml`
- Modify: `/path/to/flstad-website/tests/Feature/SiteTest.php`

> Note: these files are in the consuming project (`flstad-website`), not in pesto itself. Run commands from that project's root.

- [ ] **Step 1: Remove the manually-added Testing baseVariant from `config/sites/flstad/config.yaml`**

Find and remove the block added during debugging:

```yaml
  -
    base: 'http://localhost/'
    condition: 'applicationContext == "Testing"'
```

The `baseVariants` section should only contain the two Development variants after this change:

```yaml
baseVariants:
  -
    base: 'http://flstad.localhost/'
    condition: 'applicationContext == "Development/InternalDns"'
  -
    base: 'http://flstad.localhost.visuellverstehen.de/'
    condition: 'applicationContext == "Development/ExternalDns"'
```

- [ ] **Step 2: Update `tests/Feature/SiteTest.php` to use a relative URL**

```php
<?php

it('can visit the site', function () {
    $this->fixture(__DIR__ . '/../Fixtures/pages.csv');
    $this->get('/')->assertOk();
});
```

- [ ] **Step 3: Run pest in the flstad-website project to verify end-to-end**

```bash
docker compose exec tooling vendor/bin/pest
```

Expected: `1 deprecated (1 assertions)` — the deprecation about `list_type` is pre-existing and unrelated; the assertion itself passes.

- [ ] **Step 4: Commit in flstad-website**

```bash
git add config/sites/flstad/config.yaml tests/Feature/SiteTest.php
git commit -m "test: remove manual Testing baseVariant, use pesto's auto URL resolution"
```

# Automatic Site Config Rewriting for Tests

**Date:** 2026-04-17
**Status:** Approved

## Problem

TYPO3's `SiteMatcher` routes requests by matching the request URL against the `base` values in `config/sites/*/config.yaml`. In a standard project those bases are production URLs (e.g. `https://www.example.com/`). The TYPO3 testing framework bootstraps a test instance in isolation, so requests sent through `InternalRequest` must match one of those bases or TYPO3 returns 404.

Currently developers must manually add a `baseVariant` with `condition: 'applicationContext == "Testing"'` to every site config they want to test against. In a multi-site project this is repetitive and error-prone.

## Goal

Pesto automatically rewrites site configs when setting up the test instance so that:

- No manual changes are required to project site configs.
- Multi-site setups work out of the box, with each site addressable by a distinct subdomain.
- Developers can target a specific site in tests with `$this->onSite('identifier')`.
- Single-site tests default to the only available site automatically.

## Approach

Copy each `config/sites/{identifier}/config.yaml` into the test instance, rewriting `base` to `http://{identifier}.localhost/` and stripping `baseVariants`. All other config keys (languages, routes, errorHandling, etc.) are preserved, ensuring site-specific features remain testable.

Rejected alternatives:
- **Symlink** — cannot modify files without affecting the real config.
- **Inject baseVariant** — relies on applicationContext value staying stable; more YAML complexity for no benefit.
- **Host header injection** — tests would internally use production hostnames, confusing and fragile.

## Components

### `SiteConfigWriter` (new — `src/SiteConfig/SiteConfigWriter.php`)

Single-responsibility class. No state, no TYPO3 dependencies beyond `symfony/yaml`.

**Public API:**
```php
public function write(string $sourcePath, string $targetPath): void
```

**Behaviour:**
- Iterates subdirectories of `$sourcePath`. Each subdirectory name is the site identifier.
- Reads `{identifier}/config.yaml` using `Symfony\Component\Yaml\Yaml::parseFile()`.
- Rewrites `base` to `http://{identifier}.localhost/`.
- Removes `baseVariants` key entirely.
- Creates `{targetPath}/{identifier}/` if absent.
- Writes the modified config using `Yaml::dump()`.
- Throws a descriptive exception on unreadable source, unwritable target, or malformed YAML.

### `AbstractTypo3TestCase` (modified — `src/TestSuite/AbstractTypo3TestCase.php`)

Replaces the `symlink()` call in `setUp()` with `SiteConfigWriter::write()`. The `!file_exists($targetPath)` guard is dropped — `FunctionalTestCase::tearDown()` recursively removes the real directory after each test, so the target is always absent at setUp time.

```php
if (static::$siteConfigurationPath !== null) {
    $projectRoot = (new ComposerPackageManager())->getRootPath();
    $sourcePath = rtrim($projectRoot, '/') . '/' . ltrim(static::$siteConfigurationPath, '/');
    $targetPath = $this->instancePath . '/typo3conf/sites';
    if (is_dir($sourcePath)) {
        (new SiteConfigWriter())->write($sourcePath, $targetPath);
    }
}
```

`setSiteConfigurationPath(null)` still disables the feature entirely.

### `Feature` (modified — `src/TestSuite/Feature.php`)

**New property:**
```php
private ?string $currentSiteBase = null;
```
Reset to `null` in `tearDown()`.

**New public method:**
```php
public function onSite(string $identifier): static
```
Sets `$currentSiteBase` to `http://{identifier}.localhost/` and returns `$this` for chaining.

**New private method:**
```php
private function resolveUrl(string $url): string
```
- If `$url` already contains a host (`http://` or `https://` prefix), returns it unchanged.
- Otherwise prepends `$currentSiteBase`.
- If `$currentSiteBase` is null, auto-detects by scanning `{instancePath}/typo3conf/sites/` for the first subdirectory, reads its `config.yaml`, and caches the `base` value in `$currentSiteBase`.

All request methods (`get`, `post`, `put`, `delete`, `request`) pass their URL through `resolveUrl()` before constructing the `InternalRequest`.

## Usage Examples

```php
// Single-site — no change required, '/' resolves against the only site
it('renders the homepage', function () {
    $this->fixture(__DIR__ . '/../Fixtures/pages.csv');
    $this->get('/')->assertOk();
});

// Multi-site — explicit site targeting
it('renders the shop homepage', function () {
    $this->fixture(__DIR__ . '/../Fixtures/pages.csv');
    $this->onSite('shop')->get('/')->assertOk();
});

// Multi-site — chained
it('shop redirects to login when unauthenticated', function () {
    $this->fixture(__DIR__ . '/../Fixtures/pages.csv');
    $this->onSite('shop')->get('/account')->assertRedirect();
});
```

## Error Handling

- If `config/sites/` is absent or empty, the writer does nothing (no exception). Tests that need site routing will fail with a TYPO3 404, which is the correct signal.
- If a `config.yaml` is malformed, `SiteConfigWriter` throws a `\RuntimeException` with the site identifier and file path in the message.
- If `onSite()` references an identifier that has no corresponding directory in `typo3conf/sites/`, `resolveUrl()` will prepend the `http://{identifier}.localhost/` URL regardless — TYPO3 will return 404, which clearly signals the misconfiguration.

## Out of Scope

- Language base rewriting — relative language bases are left as-is; TYPO3 resolves them correctly against the rewritten site base.
- `settings.yaml` rewriting — not needed for routing.
- Auto-removal of the Testing base variant from `config.yaml` if a project had manually added one — not worth the complexity.

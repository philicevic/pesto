# Pesto 🌿

A [Pest](https://pestphp.com) plugin for testing [TYPO3](https://typo3.org) — write idiomatic Pest tests for your extensions without boilerplate.

Pesto provides three ready-to-use test suite classes that cover every layer of a TYPO3 application:

| Suite | What it tests |
|---|---|
| `Unit` | Pure PHP logic, no database, no HTTP |
| `Functional` | Repositories, services, anything that touches the database |
| `Feature` | Full HTTP requests through TYPO3's frontend stack |

---

## Requirements

- PHP 8.2+
- TYPO3 13 LTS
- Pest 3

---

## Installation

```bash
composer require --dev philicevic/pesto
```

Copy the provided stubs into your project:

```bash
cp vendor/philicevic/pesto/stubs/phpunit.xml.stub phpunit.xml
cp vendor/philicevic/pesto/stubs/Pest.php.stub tests/Pest.php
```

Open `phpunit.xml` and adjust the database credentials and your extension's source path. Then open `tests/Pest.php` and tell Pesto which extensions to load:

```php
Feature::loadExtensions(['my_sitepackage', 'my_extension']);
Functional::loadExtensions(['my_sitepackage', 'my_extension']);
```

Run your tests:

```bash
./vendor/bin/pest
```

---

## Usage

### Unit Tests

`tests/Unit/` — No TYPO3 bootstrap, no database. Use for testing pure PHP logic.

```php
it('formats a date correctly', function () {
    $result = (new MyDateUtility())->format(new \DateTime('2024-01-01'));
    expect($result)->toBe('01.01.2024');
});

it('can override TYPO3 config for the duration of a test', function () {
    $this->withTypo3Config('FE/debug', true);
    expect(typo3_conf_vars('FE/debug'))->toBeTrue();
    // Original value is restored automatically after the test
});
```

Available helpers: `registerSingleton()`, `registerInstance()`, `typo3Config()`, `withTypo3Config()`

---

### Functional Tests

`tests/Functional/` — Real TYPO3 instance with a dedicated test database. Use for repositories, services, and anything that touches the DB.

```php
it('finds all published news records', function () {
    $this->fixture(__DIR__ . '/Fixtures/tx_news_domain_model_news.csv');

    $repo = typo3(\GeorgRinger\News\Domain\Repository\NewsRepository::class);

    expect($repo->findAll())->toHaveCount(3);
});

it('stores a new record correctly', function () {
    $service = typo3(\MyVendor\MyExtension\Service\ContactService::class);
    $service->create(['name' => 'John', 'email' => 'john@example.com']);

    $this->assertRecordExists('tx_myextension_contact', ['email' => 'john@example.com']);
});
```

Available helpers: `fixture()`, `assertDatabase()`, `getRecord()`, `getPageRecord()`, `assertRecordExists()`, `assertRecordMissing()`

---

### Feature Tests

`tests/Feature/` — Real HTTP requests through TYPO3's internal request handler. No web server needed. The full stack is involved: routing, middleware, TypoScript, Fluid templates.

```php
it('renders the homepage', function () {
    $this->fixture(__DIR__ . '/Fixtures/pages.csv');

    $response = $this->get('http://localhost/');

    expect($response)->toHaveStatus(200)->toSee('Welcome');
});

it('protects members-only pages', function () {
    $this->fixture(__DIR__ . '/Fixtures/pages.csv');

    expect($this->get('http://localhost/members'))->toBeRedirect();
});

it('shows personalised content when logged in', function () {
    $this->fixture(__DIR__ . '/Fixtures/pages.csv');
    $this->fixture(__DIR__ . '/Fixtures/fe_users.csv');

    $response = $this->actingAsFrontendUser(1)->get('http://localhost/members');

    expect($response)->toHaveStatus(200)->toSee('Welcome, Member!');
});
```

Available request methods: `get()`, `post()`, `put()`, `delete()`, `request()`

Available auth helpers: `actingAsFrontendUser()`, `actingAsBackendUser()`, `actingAsGuest()`

The response object supports both a fluent assertion style and Pest's `expect()`:

```php
// Fluent
$this->get('/')->assertOk()->assertSee('Hello')->assertHeader('Content-Type');

// Pest expect() with custom Pesto expectations
expect($this->get('/'))->toHaveStatus(200)->toSee('Hello')->toHaveHeader('Content-Type');
```

---

### Global Helpers

These functions are available in all tests without any import:

```php
typo3()                              // Returns the DI container
typo3(MyService::class)              // Returns a specific service (type-safe)
typo3_conf_vars('FE/debug')          // Read from TYPO3_CONF_VARS
typo3_site('main')                   // Get a Site object by identifier
typo3_version()                      // Current TYPO3 version string
typo3_version_satisfies('>=', '13.4.0') // Version constraint check
```

---

## License

MIT — [Philipp Ehrenberg](https://github.com/philicevic)

<?php

declare(strict_types=1);

/**
 * Example: Feature (HTTP) Test
 *
 * Feature tests make real HTTP requests through TYPO3's internal request handler.
 * No external web server is needed — TYPO3 handles everything in-process.
 *
 * Requires a site configuration and at least a root page in your fixtures.
 *
 * The $this variable gives access to all methods from
 * \Philicevic\Pesto\TestSuite\Feature (and TYPO3's FunctionalTestCase).
 */

it('can make a GET request to the homepage', function (): void {
    // Import your site fixtures first:
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');
    // $this->fixture(__DIR__ . '/Fixtures/sys_template.csv');

    // Make a GET request — the URL must match your TYPO3 site configuration.
    // $response = $this->get('http://localhost/');

    // Use Pest expect() with custom Pesto expectations:
    // expect($response)
    //     ->toHaveStatus(200)
    //     ->toSee('Welcome to my TYPO3 site');

    // Or use the fluent assertion API directly on the response:
    // $response
    //     ->assertOk()
    //     ->assertSee('Welcome to my TYPO3 site');

    expect(true)->toBeTrue();
});

it('returns 404 for unknown pages', function (): void {
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');
    //
    // $response = $this->get('http://localhost/this-page-does-not-exist');
    // expect($response)->toHaveStatus(404);

    expect(true)->toBeTrue();
});

it('redirects to login when accessing a protected page as guest', function (): void {
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');

    // By default requests are made as a guest (no fe_user).
    // $response = $this->get('http://localhost/members-area');
    // expect($response)->toBeRedirect();

    expect(true)->toBeTrue();
});

it('shows protected content when logged in as a frontend user', function (): void {
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');
    // $this->fixture(__DIR__ . '/Fixtures/fe_users.csv');

    // Use actingAsFrontendUser() to simulate a logged-in frontend user.
    // $response = $this->actingAsFrontendUser(1)->get('http://localhost/members-area');
    // expect($response)
    //     ->toHaveStatus(200)
    //     ->toSee('Welcome, Member!');

    expect(true)->toBeTrue();
});

it('can send a POST request with form data', function (): void {
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');

    // $response = $this->post('http://localhost/contact', [
    //     'tx_form_formframework[contact][name]'  => 'John Doe',
    //     'tx_form_formframework[contact][email]' => 'john@example.com',
    // ]);

    // expect($response)->toHaveStatus(200)->toSee('Thank you for your message');

    expect(true)->toBeTrue();
});

it('can check JSON API responses', function (): void {
    // $this->fixture(__DIR__ . '/Fixtures/pages.csv');

    // $response = $this->get('http://localhost/api/news?format=json');
    // expect($response)
    //     ->toHaveStatus(200)
    //     ->toBeJsonResponse();

    // $data = $response->json();
    // expect($data)->toHaveKey('items');

    expect(true)->toBeTrue();
});

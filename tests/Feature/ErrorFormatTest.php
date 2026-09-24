<?php

it('returns 401 JSON for a guest even without the Accept header', function () {
    $this->get('/api/v1/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns 404 JSON for an unknown API route', function () {
    $this->get('/api/v1/unknown')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

it('redirects the home page to the API documentation', function () {
    $this->get('/')->assertRedirect('/docs/index.html');
});

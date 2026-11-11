<?php

test('the application health endpoint returns a successful response', function () {
    $response = $this->get('/up');

    $response->assertStatus(200);
});

test('the application root returns no content for the API-only domain', function () {
    $this->get('/')->assertNoContent();
});

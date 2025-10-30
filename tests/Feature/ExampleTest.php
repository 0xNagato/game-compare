<?php

it('renders the public landing page', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Game pricing, visual first.');
});

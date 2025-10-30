<?php

use App\Models\User;

test('guests are redirected to the filament login page', function () {
    $this->get(route('filament.admin.pages.dashboard'))->assertRedirect('/admin/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee($user->name);
});

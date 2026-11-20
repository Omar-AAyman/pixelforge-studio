<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

// The app has no mail provider, so features that rely on email are turned off.
it('does not offer password reset or email verification', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse()
        ->and(Route::has('verification.notice'))->toBeFalse();

    $this->get('/forgot-password')->assertNotFound();
    $this->get(route('login'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->missing('canResetPassword'));
});

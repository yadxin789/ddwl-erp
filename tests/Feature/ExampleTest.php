<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * 应用骨架检查：首页需要登录（未登录访问 / 会被重定向到登录页）。
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}

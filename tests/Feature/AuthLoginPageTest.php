<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthLoginPageTest extends TestCase
{
    public function test_login_page_displays_hims_sign_in_form(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Staff Sign in');
        $response->assertSee('Email');
        $response->assertSee('Password');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageExpiredErrorPageTest extends TestCase
{
    public function test_token_mismatch_uses_the_custom_hims_page_expired_screen(): void
    {
        config()->set('app.debug', true);

        Route::match(['GET', 'POST'], '/_test/page-expired', function (Request $request): string {
            if ($request->isMethod('POST')) {
                throw new TokenMismatchException;
            }

            return 'Fresh page loaded.';
        });

        $this->post('/_test/page-expired')
            ->assertStatus(419)
            ->assertSee('<title>419 | Page Expired &middot; HIMS</title>', false)
            ->assertSee(asset('img/hims-logo.png'), false)
            ->assertSee('Hospital Inventory Management System')
            ->assertSee('Error 419:', false)
            ->assertSee('Page Expired')
            ->assertSee('Your session has expired or the page is no longer available.')
            ->assertSee('href="'.url('/_test/page-expired').'"', false)
            ->assertSee('Refresh Page')
            ->assertSee('expired forms are not submitted automatically.');

        $this->get('/_test/page-expired')
            ->assertOk()
            ->assertSee('Fresh page loaded.');
    }

    public function test_json_token_mismatch_keeps_laravels_non_html_error_response(): void
    {
        config()->set('app.debug', false);

        Route::get('/_test/json-page-expired', function (): never {
            throw new TokenMismatchException;
        });

        $this->getJson('/_test/json-page-expired')
            ->assertStatus(419)
            ->assertJsonStructure(['message'])
            ->assertDontSee('Hospital Inventory Management System');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, string $username, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'username' => $username,
            'password' => Hash::make('ExamplePassword2026'),
            'role' => $role,
            'is_admin' => $role !== 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ], $attributes));
    }

    public function test_all_entry_aliases_offer_the_same_form(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('action="'.route('login.store').'"', false);

        foreach (['admin.login', 'superadmin.login', 'pumk-admin.login'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_every_role_uses_one_login_and_can_visit_monitoring_without_another_login(): void
    {
        foreach ([
            ['admin', 'admin.home', 'admin'],
            ['super_admin', 'superadmin.home', 'superadmin'],
            ['pumk_admin', 'pumk-admin.home', 'pumk'],
        ] as [$role, $dashboard, $guard]) {
            $account = $this->account($role, 'single-'.$role);
            $this->post(route('login.store'), [
                'username' => $account->username,
                'password' => 'ExamplePassword2026',
            ])->assertRedirect(route($dashboard));

            $this->assertAuthenticatedAs($account, 'web');
            $this->assertAuthenticatedAs($account, $guard);
            $this->get(route('home'))->assertOk()
                ->assertSee(route($dashboard), false)
                ->assertSee('Dashboard Saya');
            $this->get(route($dashboard))->assertOk()
                ->assertSee('href="'.route('home').'"', false);
            $this->get('/')->assertRedirect(route($dashboard));
            $this->get(route('login'))->assertRedirect(route($dashboard));

            $this->post(route('public.logout'))->assertRedirect(route('login'));
            $this->assertGuest('web');
            $this->assertGuest($guard);
        }
    }

    public function test_legacy_login_posts_use_the_account_role_and_ignore_old_intended_destinations(): void
    {
        $account = $this->account('admin', 'through-old-pumk-url');

        $this->withSession(['url.intended' => route('superadmin.home')]);
        $this->post(route('pumk-admin.login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ])->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($account, 'web');
        $this->assertAuthenticatedAs($account, 'admin');
        $this->assertGuest('pumk');
        $this->assertGuest('superadmin');
        $this->get(route('superadmin.home'))->assertForbidden();
    }

    public function test_invalid_inactive_and_unmapped_accounts_cannot_keep_a_privileged_session(): void
    {
        $inactive = $this->account('admin', 'disabled-login', ['is_active' => false]);
        $unmapped = $this->account('unmapped', 'unknown-role');

        foreach ([$inactive, $unmapped] as $account) {
            $this->post(route('login.store'), [
                'username' => $account->username,
                'password' => 'ExamplePassword2026',
            ])->assertSessionHasErrors('username');
            $this->assertGuest('web');
            $this->assertGuest('admin');
            $this->assertGuest('superadmin');
            $this->assertGuest('pumk');
        }

        $this->post(route('login.store'), [
            'username' => 'missing-account',
            'password' => 'not-correct',
        ])->assertSessionHasErrors('username');
        $this->assertGuest('web');
    }

    public function test_repeated_failures_are_shared_across_canonical_and_legacy_urls(): void
    {
        $routes = ['login.store', 'admin.login.store', 'superadmin.login.store', 'pumk-admin.login.store'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route($routes[$attempt % count($routes)]), [
                'username' => 'same-failed-account',
                'password' => 'incorrect',
            ])->assertSessionHasErrors('username');
        }

        $this->post(route('pumk-admin.login.store'), [
            'username' => 'same-failed-account',
            'password' => 'incorrect',
        ])->assertSessionHasErrors('username');
        $message = (string) session('errors')->first('username');
        $this->assertStringStartsWith('Terlalu banyak percobaan login.', $message);
    }

    public function test_password_rotation_blocks_monitoring_until_password_is_changed(): void
    {
        $account = $this->account('pumk_admin', 'temporary-unified', ['must_change_password' => true]);
        $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ])->assertRedirect(route('pumk-admin.profile'));

        $this->get(route('home'))->assertRedirect(route('pumk-admin.profile'));
        $this->get(route('pumk-admin.home'))->assertRedirect(route('pumk-admin.profile'));
        $this->get(route('public.profile'))->assertRedirect(route('pumk-admin.profile'));
        $this->put(route('public.password.update'), [
            'current_password' => 'ExamplePassword2026',
            'password' => 'ExamplePassword2026',
            'password_confirmation' => 'ExamplePassword2026',
        ])->assertRedirect(route('pumk-admin.profile'));
        $this->assertTrue($account->fresh()->must_change_password);
        $this->get(route('pumk-admin.profile'))->assertOk();

        $this->put(route('pumk-admin.password.update'), [
            'current_password' => 'ExamplePassword2026',
            'password' => 'ReplacementPassword2026',
            'password_confirmation' => 'ReplacementPassword2026',
        ])->assertRedirect(route('pumk-admin.profile'));

        $this->assertFalse($account->fresh()->must_change_password);
        $this->get(route('home'))->assertOk();
        $this->get(route('pumk-admin.home'))->assertOk();
    }

    public function test_switching_accounts_clears_all_old_panel_guards_and_all_logout_aliases_end_access(): void
    {
        $super = $this->account('super_admin', 'previous-super');
        $admin = $this->account('admin', 'replacement-admin');

        $this->post(route('login.store'), [
            'username' => $super->username,
            'password' => 'ExamplePassword2026',
        ])->assertRedirect(route('superadmin.home'));
        $this->post(route('admin.login.store'), [
            'username' => $admin->username,
            'password' => 'ExamplePassword2026',
        ])->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('superadmin');
        $this->get(route('superadmin.home'))->assertForbidden();

        $this->post(route('admin.logout'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertGuest('admin');
        $this->get(route('home'))->assertRedirect(route('login'));

        foreach ([
            ['super_admin', 'superadmin.logout'],
            ['pumk_admin', 'pumk-admin.logout'],
        ] as [$role, $logout]) {
            $account = $this->account($role, 'logout-'.$role);
            $this->post(route('login.store'), [
                'username' => $account->username,
                'password' => 'ExamplePassword2026',
            ]);
            $this->post(route($logout))->assertRedirect(route('login'));
            $this->assertGuest('web');
        }
    }

    public function test_deactivation_role_change_and_mixed_guard_sessions_do_not_run_a_request(): void
    {
        $account = $this->account('admin', 'changing-role');
        $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ]);

        $account->update(['is_active' => false]);
        $this->get(route('admin.home'))->assertRedirect(route('login'));
        $this->assertGuest('web');

        $account->update(['is_active' => true]);
        $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ]);
        $account->update(['role' => 'pumk_admin']);
        $this->get(route('admin.home'))->assertRedirect(route('login'));
        $this->assertGuest('web');

        $account->update(['role' => 'admin']);
        $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ]);
        $other = $this->account('super_admin', 'stale-other');
        Auth::guard('superadmin')->login($other);
        $this->get(route('admin.home'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertGuest('admin');
        $this->assertGuest('superadmin');
    }

    public function test_panel_only_old_session_is_rejected_instead_of_promoted_to_shared_identity(): void
    {
        $account = $this->account('super_admin', 'old-panel-session');
        Auth::guard('superadmin')->login($account);

        $this->get(route('superadmin.home'))->assertRedirect(route('login'));
        $this->assertGuest('superadmin');
        $this->assertGuest('web');
    }

    public function test_a_new_temporary_password_flag_takes_effect_on_the_next_request(): void
    {
        $account = $this->account('admin', 'password-flag-changed');
        $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ]);

        $account->update(['must_change_password' => true]);
        $this->get(route('home'))->assertRedirect(route('admin.profile'));
        $this->get(route('admin.home'))->assertRedirect(route('admin.profile'));
    }

    public function test_remember_me_recovers_only_the_primary_identity_then_resynchronizes_its_panel(): void
    {
        $account = $this->account('admin', 'remembered-admin');
        $response = $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
            'remember' => true,
        ]);

        $webCookieName = Auth::guard('web')->getRecallerName();
        $panelCookieName = Auth::guard('admin')->getRecallerName();
        $cookies = collect($response->headers->getCookies());
        $webCookie = $cookies->first(fn ($cookie) => $cookie->getName() === $webCookieName);
        $this->assertNotNull($webCookie);
        $this->assertFalse($cookies->contains(fn ($cookie) => $cookie->getName() === $panelCookieName));

        $this->flushSession();
        Auth::forgetGuards();
        $restored = $this->withUnencryptedCookie($webCookieName, $webCookie->getValue())
            ->get(route('home'));
        $restored->assertOk();
        $this->assertAuthenticatedAs($account, 'web');
        $this->assertAuthenticatedAs($account, 'admin');

        $account->update(['is_active' => false]);
        $this->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_cross_role_mutation_and_pumk_upload_are_forbidden(): void
    {
        $admin = $this->account('admin', 'regular-admin');
        $this->post(route('login.store'), [
            'username' => $admin->username,
            'password' => 'ExamplePassword2026',
        ]);

        $this->post(route('pumk-admin.monitoring.upload.store'), [])->assertForbidden();
        $this->post(route('pumk-admin.mitra.store'), [])->assertForbidden();
        $this->post(route('superadmin.users.store'), [])->assertForbidden();
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_post_login_and_logout_reject_missing_csrf_when_csrf_checks_are_enabled(): void
    {
        $this->app->bind(PreventRequestForgery::class, StrictCsrfForUnifiedLoginTest::class);
        $account = $this->account('admin', 'csrf-account');

        $this->get(route('login'))->assertOk();
        $this->post(route('login.store'), [
            'username' => $account->username,
            'password' => 'ExamplePassword2026',
        ])->assertStatus(419);

        $this->actingAs($account, 'admin');
        $this->post(route('admin.logout'))->assertStatus(419);
    }
}

class StrictCsrfForUnifiedLoginTest extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

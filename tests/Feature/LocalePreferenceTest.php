<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_language_choice_is_saved_in_session_and_browser_cookie(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->from(route('settings.system'))
            ->post(route('settings.system.locale'), ['locale' => 'id'])
            ->assertRedirect(route('settings.system'))
            ->assertCookie('jooal_locale', 'id');

        $this->get(route('settings.system'))->assertOk()->assertSee('value="id" selected', false);
    }

    public function test_english_choice_is_applied_to_sidebar_and_setting_selector(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->from(route('settings.system'))
            ->post(route('settings.system.locale'), ['locale' => 'en'])
            ->assertRedirect(route('settings.system'))
            ->assertCookie('jooal_locale', 'en');

        $this->get(route('settings.system'))->assertOk()->assertSee('value="en" selected', false);
        $this->get(route('dashboard.owner'))->assertOk()->assertSee('Orders')->assertSee('Settings');
    }
}

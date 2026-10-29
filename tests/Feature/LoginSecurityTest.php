<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use App\Support\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The office's front door: lockout after repeated wrong passwords, the real
 * password policy at the places passwords are set, and the two-factor dance —
 * enrolment on the account page, the challenge after a right password, and
 * single-use recovery codes.
 */
class LoginSecurityTest extends TestCase
{
    private const PASSWORD = 'Passw0rd-Valid1';

    protected function setUp(): void
    {
        parent::setUp();
        // Module fixture: only the tables the door touches, on in-memory SQLite.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_10_25_220000_add_two_factor_authentication_to_users_table',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
    }

    /* ------------------------------------------------------------- lockout */

    public function test_five_wrong_passwords_lock_the_email_and_network_out(): void
    {
        $user = $this->officeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // Even the right password is refused while the lockout is on.
        $blocked = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $blocked->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.throttled',
            'outcome' => 'throttled',
        ]);
    }

    public function test_a_successful_sign_in_forgets_earlier_failures(): void
    {
        $user = $this->officeUser();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');

        // Three more wrong evenings still leave room for a right one.
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($user);
    }

    /* ------------------------------------------------------ password policy */

    public function test_password_change_enforces_the_default_strength_rule(): void
    {
        $user = $this->officeUser();

        $weak = $this->actingAs($user)->put('/account/password', [
            'current_password' => self::PASSWORD,
            'password' => 'lowercaseonly1x', // long enough, but no upper case
            'password_confirmation' => 'lowercaseonly1x',
        ]);
        $weak->assertSessionHasErrors('password');

        $good = $this->actingAs($user)->put('/account/password', [
            'current_password' => self::PASSWORD,
            'password' => 'Str0nger-Passw0rd',
            'password_confirmation' => 'Str0nger-Passw0rd',
        ]);
        $good->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Str0nger-Passw0rd', $user->fresh()->password));
    }

    /* ------------------------------------------------------------- two-factor */

    public function test_a_right_password_only_buys_a_challenge_when_two_factor_is_on(): void
    {
        $user = $this->officeUser();
        $twoFactor = new TwoFactorAuthentication();
        $secret = $twoFactor->beginEnrollment($user);
        $this->assertNotNull($twoFactor->confirmEnrollment($user->fresh(), Totp::code($secret)));

        $response = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        // No session yet: the password got as far as the challenge page.
        $this->assertGuest();
        $response->assertRedirect(route('two-factor.challenge'));

        $this->get('/two-factor-challenge')->assertOk();

        $finish = $this->post('/two-factor-challenge', [
            'code' => Totp::code($user->fresh()->two_factor_secret),
        ]);

        $this->assertAuthenticatedAs($user);
        $finish->assertRedirect('/dashboard');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.two_factor.succeeded']);
    }

    public function test_recovery_codes_work_exactly_once(): void
    {
        $user = $this->officeUser();
        $twoFactor = new TwoFactorAuthentication();
        $codes = $twoFactor->confirmEnrollment($user, Totp::code($twoFactor->beginEnrollment($user)));
        $this->assertCount(8, $codes);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]]);
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $replay = $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]]);

        $replay->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_gives_up_after_five_wrong_codes(): void
    {
        $user = $this->officeUser();
        $twoFactor = new TwoFactorAuthentication();
        $twoFactor->confirmEnrollment($user, Totp::code($twoFactor->beginEnrollment($user)));

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        // A code that is definitely wrong, whatever the clock says.
        $wrongCode = Totp::code($user->fresh()->two_factor_secret) === '000000' ? '123456' : '000000';

        for ($i = 0; $i < 6; $i++) {
            $this->post('/two-factor-challenge', ['code' => $wrongCode]);
        }

        // The challenge is torn up: back to the front door to start over.
        $this->assertGuest();
        $this->assertFalse(session()->has('two_factor.user_id'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.two_factor.throttled']);
    }

    public function test_the_account_page_enrols_and_disables_two_factor(): void
    {
        $user = $this->officeUser();

        $start = $this->actingAs($user)->post('/account/two-factor');
        $start->assertRedirect();
        $this->assertTrue((new TwoFactorAuthentication())->isPending($user->fresh()));
        // A pending secret never locks the door: plain sign-in still works.
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($user);

        $wrong = $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => '000000']);
        $wrong->assertSessionHasErrors('code', null, 'twoFactor');

        $confirm = $this->actingAs($user)->post('/account/two-factor/confirm', [
            'code' => Totp::code($user->fresh()->two_factor_secret),
        ]);
        $confirm->assertSessionHas('twoFactorRecoveryCodes');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        // Sign-in now goes through the challenge.
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertGuest();

        $off = $this->actingAs($user)->delete('/account/two-factor', [
            'current_password' => self::PASSWORD,
        ]);
        $off->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    /* ---------------------------------------------------------------- helpers */

    private function officeUser(): User
    {
        return User::create([
            'name' => 'Office User',
            'email' => 'office'.uniqid().'@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }
}

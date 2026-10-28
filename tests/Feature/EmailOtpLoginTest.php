<?php

namespace Tests\Feature;

use App\Filament\Auth\Pages\EmailLogin;
use App\Mail\EmailLoginMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\EmailOtpService;
use Filament\Facades\Filament;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailOtpLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.whatsapp_gateway.url' => 'https://whatsapp.example.test',
            'services.whatsapp_gateway.token' => '',
            'services.email_login.expires_in' => 600,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.from.address' => 'noreply@example.test',
        ]);
        DB::purge('sqlite');
        Cache::flush();
        Mail::fake();
        $this->withoutVite();

        $settingsMigration = require database_path('migrations/2022_12_14_083707_create_settings_table.php');
        $settingsMigration->up();
        require_once database_path('migrations/2024_11_16_101110_pwa_settings.php');
        (new \PWASettings)->up();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('whatsapp_login_number')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'guard_name' => 'web']);

        $migration = require database_path('migrations/2026_10_28_100000_create_email_otps_table.php');
        $migration->up();
    }

    public function test_login_page_shows_email_next_to_whatsapp(): void
    {
        config(['services.whatsapp_gateway.token' => 'test-token']);

        $this->get('/admin/login')->assertOk()
            ->assertSeeInOrder(['Atau masuk dengan', 'WhatsApp', 'Email'])
            ->assertSee(route('phone-login'), false)
            ->assertSee(route('email-login'), false);
    }

    public function test_login_page_shows_email_alone_when_whatsapp_is_not_configured(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('Atau masuk dengan')
            ->assertSee(route('email-login'), false)
            ->assertDontSee(route('phone-login'), false);
    }

    public function test_login_page_hides_email_when_mailer_does_not_deliver(): void
    {
        foreach ([
            ['mail.default' => 'log'],
            ['mail.default' => 'array'],
            ['mail.default' => 'smtp', 'mail.from.address' => null],
        ] as $mailConfig) {
            config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.test']);
            config($mailConfig);

            $this->get('/admin/login')->assertOk()
                ->assertDontSee('Atau masuk dengan')
                ->assertDontSee(route('email-login'), false);
        }

        // WhatsApp yang aktif tetap tampil sendiri.
        config(['services.whatsapp_gateway.token' => 'test-token']);
        $this->get('/admin/login')->assertOk()
            ->assertSee('Atau masuk dengan')
            ->assertSee(route('phone-login'), false)
            ->assertDontSee(route('email-login'), false);
    }

    public function test_email_login_page_explains_when_mailer_is_not_configured(): void
    {
        config(['mail.default' => 'log']);
        $this->registeredUser();

        $this->get('/email-login')->assertOk()->assertSee('Pengiriman email belum dikonfigurasi');

        Livewire::test(EmailLogin::class)->set('data.email', 'budi@example.test')
            ->call('send')->assertHasFormErrors(['email'])->assertSet('awaitingOtp', false);
        Mail::assertNothingSent();
    }

    public function test_routes_component_and_middleware_are_registered(): void
    {
        $this->assertSame(EmailLogin::class, app(ComponentRegistry::class)->getClass('email-login'));

        foreach (['email-login', 'email-login.link'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $middleware = app('router')->gatherRouteMiddleware($route);

            foreach ([EncryptCookies::class, StartSession::class, VerifyCsrfToken::class] as $class) {
                $this->assertSame(1, collect($middleware)->filter(
                    fn (string $candidate): bool => is_a($candidate, $class, true),
                )->count());
            }
        }

        $this->get('/email-login')->assertOk()->assertSee('Masuk dengan Email')
            ->assertSee('Kembali ke halaman masuk')->assertSee('Kirim Kode ke Email');
    }

    public function test_user_can_login_with_the_emailed_code(): void
    {
        $user = $this->registeredUser('Budi.Santoso@Example.test');
        $component = $this->requestCode('budi.santoso@EXAMPLE.test')
            ->assertSet('data.email', 'budi.santoso@example.test')
            ->assertSee('Verifikasi dan Masuk');

        $mail = $this->sentMail('budi.santoso@example.test');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $mail->otp);
        $this->assertSame('Budi', $mail->name);
        $this->assertSame(10, $mail->expiresInMinutes);

        $record = EmailOtp::query()->sole();
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame('budi.santoso@example.test', $record->email);
        $this->assertNotSame($mail->otp, $record->otp_hash);
        $this->assertSame(hash('sha256', $this->tokenFrom($mail)), $record->token_hash);
        $this->assertTrue($record->expires_at->between(now()->addSeconds(590), now()->addSeconds(600)));

        $oldSessionId = Session::getId();
        $component->set('data.otp', $mail->otp)->call('verify')
            ->assertHasNoFormErrors()->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, Session::getId());
        $this->assertNotNull($record->fresh()->verified_at);
    }

    public function test_magic_link_logs_in_without_typing_the_code(): void
    {
        $user = $this->registeredUser();
        $this->requestCode();
        $mail = $this->sentMail();
        $token = $this->tokenFrom($mail);

        $this->assertSame(route('email-login.link', ['token' => $token]), $mail->loginUrl);

        Livewire::test(EmailLogin::class, ['token' => $token])
            ->assertSee('Memproses link masuk')
            ->assertSeeHtml('wire:init="consumeMagicLink"')
            ->call('consumeMagicLink')
            ->assertHasNoFormErrors()->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($user);
    }

    public function test_opening_the_magic_link_does_not_consume_it(): void
    {
        $user = $this->registeredUser();
        $this->requestCode();
        $token = $this->tokenFrom($this->sentMail());

        // Pemindai link di email hanya melakukan GET; token baru dipakai saat browser menjalankan wire:init.
        $html = $this->get(route('email-login.link', ['token' => $token]))->assertOk()
            ->assertSee('wire:init="consumeMagicLink"', false)->getContent();
        $this->get(route('email-login.link', ['token' => $token]))->assertOk();

        $this->assertGuest();
        $this->assertNull(EmailOtp::query()->sole()->verified_at);

        // Browser menjalankan wire:init sebagai request Livewire biasa.
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $response = $this->withHeader('X-Livewire', 'true')->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5),
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'consumeMagicLink', 'params' => []]],
            ]],
        ])->assertOk();

        $this->assertSame(Filament::getPanel('admin')->getUrl(), $response->json('components.0.effects.redirect'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(EmailOtp::query()->sole()->verified_at);
    }

    public function test_magic_link_and_code_share_one_use(): void
    {
        $this->registeredUser();
        $component = $this->requestCode();
        $mail = $this->sentMail();

        Livewire::test(EmailLogin::class, ['token' => $this->tokenFrom($mail)])
            ->call('consumeMagicLink')->assertRedirect(Filament::getUrl());
        Filament::auth()->logout();

        Livewire::test(EmailLogin::class, ['token' => $this->tokenFrom($mail)])
            ->call('consumeMagicLink')->assertHasFormErrors(['email'])
            ->assertSet('magicToken', null)->assertNoRedirect();
        $component->set('data.otp', $mail->otp)->call('verify')
            ->assertHasFormErrors(['otp'])->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_code_used_first_also_kills_the_magic_link(): void
    {
        $this->registeredUser();
        $component = $this->requestCode();
        $mail = $this->sentMail();

        $component->set('data.otp', $mail->otp)->call('verify')->assertRedirect(Filament::getUrl());
        Filament::auth()->logout();

        Livewire::test(EmailLogin::class, ['token' => $this->tokenFrom($mail)])
            ->call('consumeMagicLink')->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_expired_or_unknown_code_and_link_are_rejected(): void
    {
        $this->registeredUser();
        $component = $this->requestCode();
        $mail = $this->sentMail();
        $this->travel(601)->seconds();

        $component->set('data.otp', $mail->otp)->call('verify')->assertHasFormErrors(['otp']);
        foreach ([$this->tokenFrom($mail), Str::random(64)] as $token) {
            Livewire::test(EmailLogin::class, ['token' => $token])
                ->call('consumeMagicLink')->assertHasFormErrors(['email'])->assertNoRedirect();
        }

        $this->assertGuest();
    }

    public function test_wrong_codes_exhaust_the_code_and_the_link(): void
    {
        $this->registeredUser();
        $component = $this->requestCode();
        $mail = $this->sentMail();
        $wrongOtp = $mail->otp === '000000' ? '111111' : '000000';

        for ($attempt = 1; $attempt <= EmailOtpService::MAX_ATTEMPTS; $attempt++) {
            $component->set('data.otp', $wrongOtp)->call('verify')->assertHasFormErrors(['otp']);
        }

        $this->assertSame(EmailOtpService::MAX_ATTEMPTS, EmailOtp::query()->sole()->attempt_count);
        $component->set('data.otp', $mail->otp)->call('verify')->assertHasFormErrors(['otp']);
        Livewire::test(EmailLogin::class, ['token' => $this->tokenFrom($mail)])
            ->call('consumeMagicLink')->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_new_code_invalidates_the_previous_code_and_link(): void
    {
        $user = $this->registeredUser();
        $this->requestCode();
        $first = $this->sentMail();
        $component = $this->requestCode();
        $second = $this->sentMail();

        $this->assertNotSame($first->loginUrl, $second->loginUrl);
        Livewire::test(EmailLogin::class, ['token' => $this->tokenFrom($first)])
            ->call('consumeMagicLink')->assertHasFormErrors(['email']);
        if ($first->otp !== $second->otp) {
            $component->set('data.otp', $first->otp)->call('verify')->assertHasFormErrors(['otp']);
        }

        $component->set('data.otp', $second->otp)->call('verify')->assertRedirect(Filament::getUrl());
        $this->assertAuthenticatedAs($user);
    }

    public function test_changing_the_user_email_cancels_pending_codes(): void
    {
        $user = $this->registeredUser();
        $component = $this->requestCode();
        $mail = $this->sentMail();

        $user->update(['email' => 'budi.baru@example.test']);

        $this->assertTrue(EmailOtp::query()->sole()->expires_at->lte(now()));
        $component->set('data.otp', $mail->otp)->call('verify')->assertHasFormErrors(['otp']);
        Livewire::test(EmailLogin::class, ['token' => $this->tokenFrom($mail)])
            ->call('consumeMagicLink')->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_unknown_shared_or_unauthorized_email_gets_no_code_and_no_hint(): void
    {
        $this->registeredUser('shared@example.test', 'Pertama');
        $this->registeredUser('SHARED@example.test', 'Kedua');
        $this->registeredUser('norole@example.test', 'Tanpa Role', withRole: false);

        foreach (['unknown@example.test', 'shared@example.test', 'norole@example.test'] as $email) {
            // Sama seperti login WhatsApp, halaman tidak mengungkap apakah email terdaftar.
            $this->requestCode($email)->set('data.otp', '123456')
                ->call('verify')->assertHasFormErrors(['otp'])->assertNoRedirect();
        }

        Livewire::test(EmailLogin::class)->fillForm(['email' => 'bukan-email'])
            ->call('send')->assertHasFormErrors(['email'])->assertSet('awaitingOtp', false);

        Mail::assertNothingSent();
        $this->assertSame(0, EmailOtp::query()->count());
        $this->assertGuest();
    }

    public function test_send_is_rate_limited_per_email_and_per_ip(): void
    {
        $this->registeredUser();

        for ($request = 1; $request <= 3; $request++) {
            $this->requestCode();
        }

        Livewire::test(EmailLogin::class)->fillForm(['email' => 'BUDI@example.test'])
            ->call('send')->assertHasFormErrors(['email'])->assertSet('awaitingOtp', false);
        Mail::assertSentCount(3);

        for ($request = 4; $request <= 20; $request++) {
            $this->requestCode("orang{$request}@example.test");
        }

        Livewire::test(EmailLogin::class)->fillForm(['email' => 'orang21@example.test'])
            ->call('send')->assertHasFormErrors(['email'])->assertSet('awaitingOtp', false);
    }

    public function test_failed_delivery_expires_the_code_and_shows_a_generic_error(): void
    {
        $this->registeredUser();
        Log::spy();
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('Connection refused'));

        Livewire::test(EmailLogin::class)->fillForm(['email' => 'budi@example.test'])
            ->call('send')->assertHasFormErrors(['email'])
            ->assertSee('Email belum bisa dikirim')->assertSet('awaitingOtp', false);

        $this->assertTrue(EmailOtp::query()->sole()->expires_at->lte(now()));
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Gagal mengirim email login.'
                && $context['email_domain'] === 'example.test'
                && ! str_contains(json_encode($context), 'budi@'),
        );
    }

    public function test_login_mail_contains_the_code_and_the_magic_link(): void
    {
        $url = 'http://shelf.test/email-login/link/'.str_repeat('a', 64);
        $html = (new EmailLoginMail(name: 'Budi', otp: '482913', loginUrl: $url, expiresInMinutes: 10))->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString($url, $html);
        $this->assertStringContainsString('10 menit', $html);
    }

    private function registeredUser(string $email = 'budi@example.test', string $name = 'Budi', bool $withRole = true): User
    {
        $user = User::create(['name' => $name, 'email' => $email]);
        if ($withRole) {
            DB::table('model_has_roles')->insert([
                'role_id' => 1, 'model_type' => User::class, 'model_id' => $user->id,
            ]);
        }

        return $user;
    }

    private function requestCode(string $email = 'budi@example.test')
    {
        return Livewire::test(EmailLogin::class)->fillForm(['email' => $email])
            ->call('send')->assertHasNoFormErrors()->assertSet('awaitingOtp', true)
            ->assertSeeHtml('wire:submit="verify"');
    }

    private function sentMail(string $email = 'budi@example.test'): EmailLoginMail
    {
        $sent = Mail::sent(EmailLoginMail::class, fn (EmailLoginMail $mail): bool => $mail->hasTo($email));
        $this->assertNotEmpty($sent, "Tidak ada email login ke {$email}.");

        return $sent->last();
    }

    private function tokenFrom(EmailLoginMail $mail): string
    {
        return Str::afterLast($mail->loginUrl, '/');
    }
}

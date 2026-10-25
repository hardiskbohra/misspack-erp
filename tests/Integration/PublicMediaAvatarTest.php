<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Support\AnalyticsDatabaseFixture;
use Tests\TestCase;

class PublicMediaAvatarTest extends TestCase
{
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6GkAAAAASUVORK5CYII=';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MEDIA_BROWSER_FIXTURES') && ! is_dir(base_path('.work/media-avatar'))) {
            mkdir(base_path('.work/media-avatar'), 0777, true);
        }
        AnalyticsDatabaseFixture::install();
        foreach (['2026_10_03_030100_create_employee_documents_table', '2026_10_03_030200_create_employee_payslips_table', '2026_10_03_030300_add_breakdown_to_employee_payslips_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('avatars/example.png', base64_decode(self::PNG));
        $this->user = User::create(['name' => 'Hardik Bohra', 'email' => 'avatar@example.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full', 'avatar' => 'avatars/example.png']);
    }

    public function test_public_images_load_without_a_symlink_and_private_or_traversal_paths_do_not(): void
    {
        $image = $this->get('/storage/avatars/example.png')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        Storage::disk('local')->put('vendor-kyc/secret.pdf', 'PRIVATE KYC');
        if (getenv('MEDIA_BROWSER_FIXTURES')) {
            file_put_contents(base_path('.work/media-avatar/image.png'), file_get_contents($image->baseResponse->getFile()->getPathname()));
        }
        Storage::disk('public')->put('.env', 'SECRET');
        foreach (['vendor-kyc/secret.pdf', '%2e%2e/private/secret.pdf', '.env', 'avatars/missing.png', 'avatars/%2e%2e/.env', 'avatars/foo%5csecret.png', 'avatars/%00secret.png', 'avatars'] as $path) {
            $this->get('/storage/'.$path)->assertNotFound();
        }
        Storage::disk('public')->put('documents/active.html', '<script>window.mediaXss=1</script>');
        $response = $this->get('/storage/documents/active.html')->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_symlinks_cannot_escape_the_public_disk(): void
    {
        Storage::disk('local')->put('secret.txt', 'PRIVATE');
        $link = Storage::disk('public')->path('escaped.txt');
        $this->assertTrue(symlink(Storage::disk('local')->path('secret.txt'), $link));
        try {
            $this->get('/storage/escaped.txt')->assertNotFound();
        } finally {
            unlink($link);
        }
    }

    public function test_photo_removal_and_invalid_uploads_preserve_the_upload_contract(): void
    {
        $data = ['name' => $this->user->name, 'email' => $this->user->email, 'role' => 'admin'];
        $this->actingAs($this->user)->from('/users')->put('/users/'.$this->user->id, $data + ['avatar' => UploadedFile::fake()->create('not-a-photo.html', 1, 'text/html')])->assertSessionHasErrors('avatar');
        $this->assertSame('avatars/example.png', $this->user->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/example.png');
        $this->put('/users/'.$this->user->id, $data + ['remove_avatar' => 1])->assertRedirect();
        $this->assertNull($this->user->fresh()->avatar);
        Storage::disk('public')->assertMissing('avatars/example.png');
        $this->actingAs($this->user->fresh())->get('/users')->assertOk()->assertDontSee('/storage/avatars/example.png', false)->assertSee('data-avatar-initials', false);
    }

    public function test_private_storage_keeps_a_distinct_signed_only_endpoint(): void
    {
        Storage::disk('local')->put('vendor-kyc/secret.pdf', 'PRIVATE KYC');
        $url = URL::temporarySignedRoute('storage.local', now()->addMinute(), ['path' => 'vendor-kyc/secret.pdf'], absolute: false);
        $this->assertStringContainsString('/private-storage/', $url);
        $this->get('/private-storage/vendor-kyc/secret.pdf')->assertForbidden();
        $this->get($url)->assertOk();
    }

    public function test_user_listing_record_account_and_edit_json_use_the_current_photo(): void
    {
        $this->actingAs($this->user);
        foreach (['/users', '/users/'.$this->user->id, '/account'] as $url) {
            $page = $this->get($url)->assertOk()->assertSee('data-user-avatar', false)->assertSee('/storage/avatars/example.png', false);
            if (getenv('MEDIA_BROWSER_FIXTURES')) {
                $dir = base_path('.work/media-avatar');
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($dir.($url === '/users' ? '/users.html' : ($url === '/account' ? '/account.html' : '/record.html')), $page->getContent());
            }
        }
        $data = $this->getJson('/users/'.$this->user->id.'/data')->assertOk()->assertJsonPath('avatar', '/storage/avatars/example.png');
        if (getenv('MEDIA_BROWSER_FIXTURES')) {
            file_put_contents(base_path('.work/media-avatar/edit.json'), $data->getContent());
        }
    }

    public function test_replacement_photo_wins_over_a_stale_remove_flag_and_is_servable(): void
    {
        $this->actingAs($this->user)->put('/users/'.$this->user->id, ['name' => $this->user->name, 'email' => $this->user->email, 'role' => 'admin',
            'remove_avatar' => 1, 'avatar' => UploadedFile::fake()->createWithContent('replacement.png', base64_decode(self::PNG))])->assertRedirect();
        $user = $this->user->fresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
        Storage::disk('public')->assertMissing('avatars/example.png');
        $this->get('/storage/'.$user->avatar)->assertOk();
    }
}

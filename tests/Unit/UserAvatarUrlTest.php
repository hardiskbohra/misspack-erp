<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarUrlTest extends TestCase
{
    public function test_initials_handle_extra_whitespace_and_non_latin_names(): void
    {
        $this->assertSame('HB', (new User(['name' => '  Hardik   Bohra ']))->initials);
        $this->assertSame('હબ', (new User(['name' => 'હાર્દિક બોહરા']))->initials);
    }

    public function test_explicit_public_storage_cdn_is_respected(): void
    {
        config(['filesystems.disks.public.url' => 'https://cdn.example.test/media']);
        Storage::forgetDisk('public');
        $this->assertSame('https://cdn.example.test/media/avatars/photo.png', (new User(['avatar' => 'avatars/photo.png']))->avatar_url);
    }

    public function test_default_public_media_urls_do_not_depend_on_the_app_host_or_port(): void
    {
        $this->assertSame('/storage', config('filesystems.disks.public.url'));
        $user = new User(['name' => 'Hardik Bohra', 'avatar' => 'avatars/example.png']);
        $this->assertSame('/storage/avatars/example.png', $user->avatar_url);
        $this->assertSame('HB', $user->initials);
        $this->assertNull((new User(['name' => 'No Photo']))->avatar_url);
    }
}

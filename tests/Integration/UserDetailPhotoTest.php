<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AnalyticsDatabaseFixture;
use Tests\TestCase;

class UserDetailPhotoTest extends TestCase
{
    private User $manager;

    private User $person;

    protected function setUp(): void
    {
        parent::setUp();
        AnalyticsDatabaseFixture::install();
        foreach (['2026_10_03_030100_create_employee_documents_table', '2026_10_03_030200_create_employee_payslips_table', '2026_10_03_030300_add_breakdown_to_employee_payslips_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        Storage::fake('public');
        Storage::disk('public')->put('avatars/current.png', base64_decode(PublicMediaAvatarTest::PNG));
        $this->manager = User::create(['name' => 'Photo manager', 'email' => 'manager@photo.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['people.manage']]);
        $this->person = User::create(['name' => 'Hardik Bohra', 'email' => 'person@photo.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['people.view'], 'avatar' => 'avatars/current.png', 'bank_account_number' => 'DO-NOT-CHANGE']);
    }

    private function url(): string
    {
        return '/users/'.$this->person->id.'/photo?tab=overview&year=2026';
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('replacement.png', base64_decode(PublicMediaAvatarTest::PNG));
    }

    private function capture(string $name, $response): void
    {
        if (! getenv('DETAIL_PHOTO_FIXTURES')) {
            return;
        }
        $dir = base_path('.work/detail-photo');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$name.'.html', $response->getContent());
        file_put_contents($dir.'/image.png', base64_decode(PublicMediaAvatarTest::PNG));
    }

    public function test_detail_header_exposes_a_scoped_photo_editor_on_any_tab_only_to_managers(): void
    {
        $this->actingAs($this->manager);
        foreach (['overview', 'details', 'salary', 'documents', 'work', 'access'] as $tab) {
            $response = $this->get('/users/'.$this->person->id.'?tab='.$tab.'&year=2026')->assertOk()->assertSee('data-open-photo', false)->assertSee('id="userPhotoModal"', false)->assertSee('Edit photo')->assertSee('name="avatar"', false);
            if ($tab === 'overview') {
                $this->capture('record', $response);
            }
        }
        $reader = $this->person;
        $response = $this->actingAs($reader)->get('/users/'.$reader->id)->assertOk()->assertDontSee('data-open-photo', false)->assertDontSee('id="userPhotoModal"', false);
        $this->capture('readonly', $response);
    }

    public function test_photo_only_upload_preserves_all_other_fields_and_returns_to_the_same_record_context(): void
    {
        $before = $this->person->fresh()->getAttributes();
        unset($before['avatar'],$before['updated_at']);
        $this->actingAs($this->manager)->patch($this->url(), ['avatar' => $this->photo(), 'remove_avatar' => 1, 'name' => 'HACK', 'role' => 'employee', 'bank_account_number' => 'HACK', 'password' => 'HACK'])->assertRedirect('/users/'.$this->person->id.'?tab=overview&year=2026')->assertSessionHas('success');
        $person = $this->person->fresh();
        $after = $person->getAttributes();
        unset($after['avatar'],$after['updated_at']);
        $this->assertSame($before, $after);
        $this->assertNotSame('avatars/current.png', $person->avatar);
        Storage::disk('public')->assertExists($person->avatar);
        Storage::disk('public')->assertMissing('avatars/current.png');
        $this->get('/storage/'.$person->avatar)->assertOk();
    }

    public function test_photo_removal_only_happens_on_save_and_displays_initials(): void
    {
        $this->actingAs($this->manager)->patch($this->url(), ['remove_avatar' => 1])->assertRedirect();
        $this->assertNull($this->person->fresh()->avatar);
        Storage::disk('public')->assertMissing('avatars/current.png');
        $response = $this->get('/users/'.$this->person->id)->assertOk()->assertSee('data-open-photo', false)->assertSee('data-avatar-initials', false)->assertDontSee('/storage/avatars/current.png', false);
        $this->capture('no-photo', $response);
    }

    public function test_invalid_empty_and_oversize_uploads_reopen_the_photo_modal_without_losing_the_existing_photo(): void
    {
        foreach ([[], ['avatar' => UploadedFile::fake()->create('script.html', 1, 'text/html')], ['avatar' => UploadedFile::fake()->createWithContent('oversize.png', base64_decode(PublicMediaAvatarTest::PNG).str_repeat('x', 2048 * 1024))]] as $data) {
            $this->actingAs($this->manager)->patch($this->url(), $data)->assertRedirect('/users/'.$this->person->id.'?tab=overview&year=2026')->assertSessionHasErrors('avatar', null, 'userPhoto')->assertSessionHas('edit_user_photo', $this->person->id);
            $this->assertSame('avatars/current.png', $this->person->fresh()->avatar);
            Storage::disk('public')->assertExists('avatars/current.png');
        }
        $response = $this->withCookie(session()->getName(), session()->getId())->get('/users/'.$this->person->id.'?tab=overview&year=2026')->assertOk()->assertSee('data-reopen-photo="1"', false)->assertSee('id="recordPhotoError"', false);
        $this->capture('validation', $response);
    }

    public function test_storage_failure_does_not_delete_the_current_photo_or_change_the_record(): void
    {
        $disk = \Mockery::mock(Storage::disk('public'))->makePartial();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('public', $disk);
        $this->actingAs($this->manager)->patch($this->url(), ['avatar' => $this->photo()])->assertRedirect()->assertSessionHasErrors('avatar', null, 'userPhoto');
        $this->assertSame('avatars/current.png', $this->person->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/current.png');
    }

    public function test_guest_employee_and_read_only_accounts_cannot_change_photos(): void
    {
        $this->patch($this->url(), ['remove_avatar' => 1])->assertRedirect('/login');
        $this->actingAs($this->person)->patch($this->url(), ['remove_avatar' => 1])->assertForbidden();
        $employee = User::create(['name' => 'Employee', 'email' => 'employee@photo.test', 'password' => 'fixture', 'role' => 'employee', 'access_profile' => 'employee']);
        $this->actingAs($employee)->patch($this->url(), ['remove_avatar' => 1])->assertRedirect('/my');
        $this->assertSame('avatars/current.png',$this->person->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/current.png');
    }
}

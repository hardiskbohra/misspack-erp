<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ShipmentAttachmentUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_06_09_010000_create_shipments_table',
            '2026_07_14_010300_create_shipment_attachments_table',
            '2026_10_01_010200_add_document_type_to_shipment_attachments_table',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        Storage::fake('public');
        view()->share('errors', new ViewErrorBag);
    }

    private function office(array $permissions = ['operations.manage']): User
    {
        $user = User::create(['name' => 'Shipping officer', 'email' => 'shipping@example.test', 'password' => 'password',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
        $this->actingAs($user);
        return $user;
    }

    private function shipment(string $number = 'SHIP-001'): Shipment
    {
        return Shipment::create(['shipment_number' => $number, 'identity_name' => 'Bottle shipment', 'status' => 'in_transit']);
    }

    private function card(Shipment $shipment): string
    {
        return view('shipments.partials.attachments-card', ['shipment' => $shipment->load('attachments')])->render();
    }

    public function test_detail_card_offers_direct_multi_upload_with_private_default(): void
    {
        $this->office();
        $shipment = $this->shipment();
        $html = $this->card($shipment);
        $this->assertStringContainsString('shipments.partials.attachments-card', file_get_contents(resource_path('views/shipments/show.blade.php')));
        $this->assertStringContainsString('action="'.route('shipments.attachments.store', $shipment).'"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('name="attachment_photos[]" multiple required', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('8 MB each', $html);
        $this->assertStringNotContainsString('checked', $html);
        $this->assertStringNotContainsString('while editing', $html);
        $this->assertStringContainsString("project's Workflow Control Centre", $html);
    }

    public function test_upload_returns_to_the_detail_attachments_without_editing_shipment_fields(): void
    {
        $user = $this->office();
        $shipment = $this->shipment();
        $other = $this->shipment('SHIP-002');
        $before = $shipment->fresh()->getAttributes();
        $this->from(route('shipments.show', $shipment))->post(route('shipments.attachments.store', $shipment), [
            'attachment_photos' => [UploadedFile::fake()->create('package.jpg', 4, 'image/jpeg'), UploadedFile::fake()->create('support.pdf', 5, 'application/pdf')],
            // This endpoint must not act as an alternate shipment-edit form.
            'status' => 'delivered', 'identity_name' => 'Changed name',
        ])->assertRedirect(route('shipments.show', $shipment).'#shipmentAttachments')
            ->assertSessionHas('success', 'Shipment attachments uploaded successfully.');
        $this->assertSame($before, $shipment->fresh()->getAttributes());
        $this->assertSame(0, $other->attachments()->count());
        $files = $shipment->attachments()->reorder('id')->get();
        $this->assertCount(2, $files);
        $this->assertSame(['package.jpg', 'support.pdf'], $files->pluck('original_name')->all());
        $this->assertSame(['photo', 'document'], $files->pluck('attachment_type')->all());
        foreach ($files as $file) {
            $this->assertFalse($file->is_public);
            $this->assertNull($file->document_type); // Not a workflow dispatch document.
            $this->assertSame($user->id, $file->uploaded_by);
            Storage::disk('public')->assertExists($file->file_path);
        }
        $html = $this->card($shipment);
        $this->assertStringContainsString('package.jpg', $html);
        $this->assertStringContainsString('support.pdf', $html);
        $this->assertStringContainsString('Internal', $html);
        $this->assertStringNotContainsString('No shipment attachments yet', $html);
    }

    public function test_visibility_is_an_explicit_opt_in(): void
    {
        $this->office();
        $shipment = $this->shipment();
        $this->post(route('shipments.attachments.store', $shipment), [
            'attachment_photos' => [UploadedFile::fake()->create('dispatch.jpg', 4, 'image/jpeg')], 'is_public' => '1',
        ])->assertRedirect();
        $this->assertTrue($shipment->attachments()->firstOrFail()->is_public);
        $this->assertStringContainsString('Public', $this->card($shipment));
    }

    public function test_invalid_or_oversized_batches_do_not_store_any_files(): void
    {
        $this->office();
        $shipment = $this->shipment();
        foreach ([UploadedFile::fake()->create('script.exe', 1), UploadedFile::fake()->create('large.jpg', 8193, 'image/jpeg')] as $invalid) {
            $this->from(route('shipments.show', $shipment))->post(route('shipments.attachments.store', $shipment), [
                'attachment_photos' => [UploadedFile::fake()->create('valid.jpg', 1, 'image/jpeg'), $invalid],
            ])->assertRedirect(route('shipments.show', $shipment))->assertSessionHasErrors();
            $this->assertSame(0, ShipmentAttachment::count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
        $this->post(route('shipments.attachments.store', $shipment), [])->assertSessionHasErrors('attachment_photos');
    }

    public function test_read_only_users_see_attachments_but_cannot_upload(): void
    {
        $this->office(['operations.view']);
        $shipment = $this->shipment();
        $html = $this->card($shipment);
        $this->assertStringContainsString('Shipment Attachments', $html);
        $this->assertStringNotContainsString('enctype="multipart/form-data"', $html);
        $this->post(route('shipments.attachments.store', $shipment), [
            'attachment_photos' => [UploadedFile::fake()->create('package.jpg', 1, 'image/jpeg')],
        ])->assertForbidden();
        $this->assertSame(0, ShipmentAttachment::count());
    }

    public function test_attachment_field_errors_render_next_to_the_picker(): void
    {
        $this->office();
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['attachment_photos.0' => 'The attachment is too large.']));
        view()->share('errors', $errors);
        $this->assertStringContainsString('The attachment is too large.', $this->card($this->shipment()));
    }

    public function test_csrf_still_protects_uploads(): void
    {
        $this->office();
        $shipment = $this->shipment();
        $this->app->detectEnvironment(fn () => 'local');
        $this->post(route('shipments.attachments.store', $shipment), [
            'attachment_photos' => [UploadedFile::fake()->create('package.jpg', 1, 'image/jpeg')],
        ])->assertStatus(419);
        $this->assertSame(0, ShipmentAttachment::count());
    }
}

<?php

namespace Tests\Integration;

use App\Models\Client;
use App\Models\ClientPortalUser;
use App\Models\Shipment;
use App\Models\ShipmentCommentAttachment;
use App\Models\ShipmentDeliveryReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Real office routes, full disposable schema, and fake private/public disks. */
class ShipmentDetailTabsCommentsTest extends TestCase
{
    use IsolatedDatabase;

    private User $manager;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        Storage::fake('local');
        Storage::fake('public');
        $this->manager = User::create(['name' => 'Shipping Manager', 'email' => 'ship-manager@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $this->actingAs($this->manager);
        $this->shipment = $this->makeShipment();
    }

    private function makeShipment(array $overrides = []): Shipment
    {
        return Shipment::create(array_merge(['shipment_number' => 'TABS-'.(Shipment::count() + 1),
            'identity_name' => 'Fictional shipment', 'shipment_type' => 'domestic', 'status' => 'in_transit',
            'from_name' => 'Fictional origin', 'to_name' => 'Fictional destination', 'currency' => 'INR'], $overrides));
    }

    private function commentsUrl(?Shipment $shipment = null): string
    {
        return '/shipments/'.($shipment ?? $this->shipment)->id.'/comments';
    }

    private function commentsPage(?Shipment $shipment = null): string
    {
        return route('shipments.show', ['shipment' => $shipment ?? $this->shipment, 'tab' => 'comments']);
    }

    private function addComment(string $body = 'Internal shipping note', array $files = []): object
    {
        $response = $this->from($this->commentsPage())->post($this->commentsUrl(), ['body' => $body, 'attachments' => $files]);
        $response->assertRedirect($this->commentsPage())->assertSessionHasNoErrors();
        return DB::table('shipment_comments')->where('shipment_id', $this->shipment->id)->latest('id')->first();
    }

    private function fileUrl(Shipment $shipment, object $comment, object $file): string
    {
        return '/shipments/'.$shipment->id.'/comments/'.$comment->id.'/attachments/'.$file->id;
    }

    public function test_record_sections_are_bookmarkable_tabs_and_only_the_selected_panel_renders(): void
    {
        $this->shipment->items()->create(['product_name' => 'Fictional box', 'quantity' => 10, 'unit' => 'pcs']);
        $overview = $this->get(route('shipments.show', $this->shipment))->assertOk();
        $overview->assertSee('Shipment Overview')->assertSee('Route Details')->assertSee('Tracking Progress')
            ->assertSee('aria-label="Shipment record sections"', false)->assertSee('id="shipment-panel-overview"', false)
            ->assertDontSee('Add Tracking Update')->assertDontSee('Freight Cost Breakdown')
            ->assertDontSee('Delivery receipt reconciliation')->assertDontSee('Shipment Attachments');
        foreach (['overview', 'tracking', 'products', 'costs', 'documents', 'comments'] as $key) {
            $overview->assertSee(route('shipments.show', ['shipment' => $this->shipment, 'tab' => $key]), false);
        }
        $panels = [
            'tracking' => ['Share Tracking', 'Add Tracking Update', 'Tracking History'],
            'products' => ['Shipment Products', 'Fictional box', 'Delivery receipt reconciliation', 'Create Packaging List'],
            'costs' => ['Freight Cost Breakdown', 'Add cost'],
            'documents' => ['Shipment Attachments', 'Shipping Mark', 'Challan'],
            'comments' => ['Internal comments', 'No comments yet'],
        ];
        foreach ($panels as $tab => $contents) {
            $response = $this->get(route('shipments.show', ['shipment' => $this->shipment, 'tab' => $tab]))->assertOk();
            $response->assertSee('id="shipment-panel-'.$tab.'"', false)->assertSee('data-shipment-tab-link="'.$tab.'"', false)
                ->assertSee('Fictional shipment')->assertDontSee('Shipment Overview');
            foreach ($contents as $text) $response->assertSee($text);
        }
        $this->get(route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'not-a-section']))->assertOk()
            ->assertSee('id="shipment-panel-overview"', false);
        $this->get(route('shipments.show', ['shipment' => $this->shipment, 'tab' => ['bad']]))->assertOk()
            ->assertSee('id="shipment-panel-overview"', false);
    }

    public function test_existing_receipt_deep_link_opens_the_product_panel_and_receipt_form(): void
    {
        $this->shipment->items()->create(['product_name' => 'Fictional box', 'quantity' => 2, 'unit' => 'pcs']);
        $this->get(route('shipments.show', ['shipment' => $this->shipment, 'record_delivery_receipt' => 1]))
            ->assertOk()->assertSee('id="shipment-panel-products"', false)
            ->assertSee('data-open-on-load="true"', false)->assertSee('Record delivery receipt');
    }

    public function test_comment_with_multiple_private_files_stays_on_its_shipment_and_is_escaped(): void
    {
        $body = "Carrier called\n<script>alert(1)</script>";
        $comment = $this->addComment($body, [
            UploadedFile::fake()->create('box.pdf', 3, 'application/pdf'),
            UploadedFile::fake()->create('photo.jpg', 4, 'image/jpeg'),
        ]);
        $files = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $comment->id)->orderBy('id')->get();
        $this->assertCount(2, $files);
        $this->assertSame($this->manager->id, $comment->created_by);
        $this->assertSame($body, $comment->body);
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file->file_path);
            Storage::disk('public')->assertMissing($file->file_path);
            $this->get($this->fileUrl($this->shipment, $comment, $file))->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Content-Disposition', 'attachment; filename='.$file->original_name);
            $this->get('/storage/'.$file->file_path)->assertNotFound();
        }
        $this->assertSame(0, $this->shipment->attachments()->count(), 'A comment file is not a shipment checklist document.');
        $this->get($this->commentsPage())->assertOk()->assertSee('Shipping Manager')->assertSee('box.pdf')->assertSee('photo.jpg')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Internal only');
        $this->get(route('shipments.publicTrack', $this->shipment->public_token))->assertOk()
            ->assertDontSee('Carrier called')->assertDontSee('box.pdf')->assertDontSee('photo.jpg');
    }

    public function test_plain_comments_work_and_are_ordered_newest_first_with_stable_counts(): void
    {
        $first = $this->addComment('First internal note');
        $second = $this->addComment('Second internal note');
        $this->assertGreaterThan($first->id, $second->id);
        $response = $this->get($this->commentsPage())->assertOk();
        $response->assertSeeInOrder(['Second internal note', 'First internal note']);
        $this->assertSame(2, $response->viewData('tabCounts')['comments']);
        $this->assertSame(2, $this->get(route('shipments.show', $this->shipment))->viewData('tabCounts')['comments']);
    }

    public function test_invalid_or_mixed_uploads_do_not_create_comments_or_leave_files(): void
    {
        $batches = [
            [UploadedFile::fake()->create('ok.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('run.exe', 1, 'application/octet-stream')],
            [UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
            array_map(fn ($i) => UploadedFile::fake()->create('file'.$i.'.pdf', 1, 'application/pdf'), range(1, 6)),
            [UploadedFile::fake()->create('report.pdf.php', 1, 'application/pdf')],
            [UploadedFile::fake()->create('picture.jpg', 1, 'application/pdf')],
        ];
        foreach ($batches as $batch) {
            $this->from($this->commentsPage())->post($this->commentsUrl(), ['body' => 'Keep this text', 'attachments' => $batch])
                ->assertRedirect($this->commentsPage())->assertSessionHasErrors()->assertSessionHasInput('body', 'Keep this text');
            $this->assertSame(0, DB::table('shipment_comments')->count());
            $this->assertSame(0, DB::table('shipment_comment_attachments')->count());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
        $this->from($this->commentsPage())->post($this->commentsUrl(), ['body' => '   '])->assertSessionHasErrors('body');
        $this->assertSame(0, DB::table('shipment_comments')->count());
    }

    public function test_comment_limits_accept_five_ten_megabyte_files_and_reject_oversized_text(): void
    {
        $files = array_map(fn ($i) => UploadedFile::fake()->create('boundary-'.$i.'.pdf', 10240, 'application/pdf'), range(1, 5));
        $comment = $this->addComment(str_repeat('A', 4000), $files);
        $rows = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $comment->id)->get();
        $this->assertCount(5, $rows);
        foreach ($rows as $row) {
            $this->assertSame(10240 * 1024, $row->file_size);
            Storage::disk('local')->assertExists($row->file_path);
        }
        $this->from($this->commentsPage())->post($this->commentsUrl(), ['body' => str_repeat('B', 4001)])
            ->assertRedirect($this->commentsPage())->assertSessionHasErrors('body');
        $this->assertSame(1, DB::table('shipment_comments')->count());
    }

    public function test_comment_files_are_scoped_to_their_shipment_and_require_office_operations_access(): void
    {
        $comment = $this->addComment('Private note', [UploadedFile::fake()->create('private.pdf', 1, 'application/pdf')]);
        $file = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $comment->id)->first();
        $other = $this->makeShipment();
        $this->get($this->fileUrl($other, $comment, $file))->assertNotFound();
        $this->delete($this->commentsUrl($other).'/'.$comment->id)->assertNotFound();
        $this->assertSame(1, DB::table('shipment_comments')->count());
        $reader = User::create(['name' => 'Reader', 'email' => 'ship-reader@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.view']]);
        $this->actingAs($reader)->get($this->commentsPage())->assertOk()->assertSee('Private note')->assertDontSee('Add comment');
        $this->get($this->fileUrl($this->shipment, $comment, $file))->assertOk();
        $this->post($this->commentsUrl(), ['body' => 'Not allowed'])->assertForbidden();
        $this->delete($this->commentsUrl().'/'.$comment->id)->assertForbidden();
        $otherReader = User::create(['name' => 'Other', 'email' => 'ship-other@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $this->actingAs($otherReader)->get($this->fileUrl($this->shipment, $comment, $file))->assertForbidden();
        auth()->logout();
        $this->get($this->fileUrl($this->shipment, $comment, $file))->assertRedirect(route('login'));
    }

    public function test_comment_delete_removes_only_its_own_files_and_shipment_delete_removes_the_rest(): void
    {
        $this->shipment->update(['status' => 'planning']);
        $first = $this->addComment('First note', [UploadedFile::fake()->create('one.pdf', 1, 'application/pdf')]);
        $second = $this->addComment('Second note', [UploadedFile::fake()->create('two.pdf', 1, 'application/pdf')]);
        $file1 = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $first->id)->first();
        $file2 = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $second->id)->first();
        $this->delete($this->commentsUrl().'/'.$first->id)->assertRedirect($this->commentsPage());
        $this->assertDatabaseMissing('shipment_comments', ['id' => $first->id]);
        $this->assertDatabaseMissing('shipment_comment_attachments', ['id' => $file1->id]);
        Storage::disk('local')->assertMissing($file1->file_path);
        Storage::disk('local')->assertExists($file2->file_path);
        $this->delete(route('shipments.destroy', $this->shipment))->assertRedirect(route('shipments.index'));
        $this->assertDatabaseMissing('shipment_comments', ['id' => $second->id]);
        $this->assertDatabaseMissing('shipment_comment_attachments', ['id' => $file2->id]);
        Storage::disk('local')->assertMissing($file2->file_path);
    }

    public function test_receipt_and_shipment_upload_forms_return_to_their_own_tabs(): void
    {
        $item = $this->shipment->items()->create(['product_name' => 'Receipt box', 'quantity' => 2, 'unit' => 'pcs']);
        $productsPage = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'products']);
        $this->from($productsPage)->post(route('shipments.delivery-receipts.store', $this->shipment), [
            '_shipment_tab' => 'products', 'delivery_receipt_form' => '1', 'recipient_name' => 'Receiver',
            'received_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'lines' => [[
                'shipment_item_id' => $item->id, 'received_quantity' => 2, 'accepted_quantity' => 2,
                'short_quantity' => 0, 'damaged_quantity' => 0, 'rejected_quantity' => 0,
                'returned_quantity' => 0, 'replacement_quantity' => 0,
            ]],
        ])->assertRedirect($productsPage)->assertSessionHasNoErrors();
        $this->get($productsPage)->assertOk()->assertSee('Receipt #')->assertSee('Receiver');
        $documentsPage = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'documents']);
        $this->from($documentsPage)->post(route('shipments.attachments.store', $this->shipment), [
            '_shipment_tab' => 'documents', 'attachment_photos' => [UploadedFile::fake()->create('manifest.pdf', 1, 'application/pdf')],
        ])->assertRedirect($documentsPage.'#shipmentAttachments')->assertSessionHasNoErrors();
        $this->get($documentsPage)->assertOk()->assertSee('manifest.pdf');
    }

    public function test_failed_comment_validation_preserves_text_and_reopens_the_comments_tab(): void
    {
        $this->from(route('shipments.show', $this->shipment))->post($this->commentsUrl(), [
            'shipment_comment_form' => 1, 'body' => 'Text to preserve',
            'attachments' => [UploadedFile::fake()->create('unsafe.exe', 1, 'application/octet-stream')],
        ])->assertRedirect(route('shipments.show', $this->shipment));
        // Follow immediately: inspecting the JSON session first starts it and
        // marshals the error bag twice in the in-memory test client. A separate
        // validation test asserts the errors on the POST response itself.
        $this->get(route('shipments.show', $this->shipment))->assertOk()
            ->assertSee('id="shipment-panel-comments"', false)->assertSee('Text to preserve')
            ->assertSee('Comment could not be saved')->assertSee('choose your files again');
        $this->assertSame(0, DB::table('shipment_comments')->count());
    }

    public function test_failed_database_write_rolls_back_the_comment_and_deletes_previously_uploaded_files(): void
    {
        $attempt = 0;
        ShipmentCommentAttachment::creating(function () use (&$attempt) {
            if (++$attempt === 2) throw new \RuntimeException('Fictional attachment write failed.');
        });
        $this->withoutExceptionHandling();
        try {
            $this->post($this->commentsUrl(), ['body' => 'Roll back both files', 'attachments' => [
                UploadedFile::fake()->create('first.pdf', 1, 'application/pdf'),
                UploadedFile::fake()->create('second.pdf', 1, 'application/pdf'),
            ]]);
            $this->fail('The second attachment write should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fictional attachment write failed.', $exception->getMessage());
        } finally {
            ShipmentCommentAttachment::flushEventListeners();
            $this->withExceptionHandling();
        }
        $this->assertSame(2, $attempt);
        $this->assertSame(0, DB::table('shipment_comments')->count());
        $this->assertSame(0, DB::table('shipment_comment_attachments')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_private_storage_write_rolls_back_the_comment_and_cleans_earlier_files(): void
    {
        $disk = Storage::disk('local');
        $failing = new class($disk) {
            public int $writes = 0;
            public function __construct(private $disk) {}
            public function putFileAs(...$arguments) {
                ++$this->writes;
                // A filesystem can report failure after creating partial bytes.
                // Simulate that case, not just a write that made no file.
                $path = $this->disk->putFileAs(...$arguments);
                return $this->writes === 2 ? false : $path;
            }
            public function delete($paths) { return $this->disk->delete($paths); }
        };
        Storage::set('local', $failing);
        $this->withoutExceptionHandling();
        try {
            $this->post($this->commentsUrl(), ['body' => 'Storage failure', 'attachments' => [
                UploadedFile::fake()->create('first.pdf', 1, 'application/pdf'),
                UploadedFile::fake()->create('second.pdf', 1, 'application/pdf'),
            ]]);
            $this->fail('The second file write should fail.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Could not store the private shipment comment attachment', $error->getMessage());
        } finally {
            Storage::set('local', $disk);
            $this->withExceptionHandling();
        }
        $this->assertSame(2, $failing->writes);
        $this->assertSame(0, DB::table('shipment_comments')->count());
        $this->assertSame(0, DB::table('shipment_comment_attachments')->count());
        $this->assertSame([], $disk->allFiles());
    }

    public function test_failed_file_cleanup_warns_the_operator_and_logs_the_private_path_for_recovery(): void
    {
        $comment = $this->addComment('Remove with warning', [UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf')]);
        $file = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $comment->id)->first();
        $disk = Storage::disk('local');
        Storage::set('local', new class {
            public function delete($paths) { return false; }
        });
        Log::spy();
        try {
            $this->delete($this->commentsUrl().'/'.$comment->id)->assertRedirect($this->commentsPage())
                ->assertSessionHas('warning', 'Comment deleted, but some private files could not be removed. Please contact an administrator.');
            $this->assertDatabaseMissing('shipment_comments', ['id' => $comment->id]);
            Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) =>
                str_contains($message, 'Could not remove private shipment comment files')
                && in_array($file->file_path, $context['paths'] ?? [], true));
        } finally {
            Storage::set('local', $disk);
        }
        $disk->assertExists($file->file_path);
        $disk->delete($file->file_path);
    }

    public function test_migration_refuses_to_drop_comments_or_files_and_an_empty_rollback_leaves_the_record_usable(): void
    {
        $comment = $this->addComment('Must not be discarded', [UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf')]);
        $file = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $comment->id)->first();
        $migration = require database_path('migrations/2026_11_02_100000_create_shipment_internal_comments.php');
        try {
            $migration->down();
            $this->fail('A rollback must not discard the discussion or orphan its private files.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Cannot roll back shipment comments', $error->getMessage());
        }
        $this->assertDatabaseHas('shipment_comments', ['id' => $comment->id]);
        $this->assertDatabaseHas('shipment_comment_attachments', ['id' => $file->id]);
        Storage::disk('local')->assertExists($file->file_path);
        $this->delete($this->commentsUrl().'/'.$comment->id)->assertRedirect($this->commentsPage());
        $migration->down();
        $this->assertFalse(Schema::hasTable('shipment_comments'));
        $this->assertFalse(Schema::hasTable('shipment_comment_attachments'));
        $this->get($this->commentsPage())->assertOk()->assertSee('Comments are unavailable until the latest database migration is applied.');
        $this->post($this->commentsUrl(), ['body' => 'Unavailable'])->assertStatus(503);
    }

    public function test_missing_and_wrong_comment_attachments_are_not_downloaded_and_deletion_rules_remain(): void
    {
        $first = $this->addComment('First', [UploadedFile::fake()->create('first.pdf', 1, 'application/pdf')]);
        $second = $this->addComment('Second', [UploadedFile::fake()->create('second.pdf', 1, 'application/pdf')]);
        $file1 = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $first->id)->first();
        $file2 = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $second->id)->first();
        $this->get($this->fileUrl($this->shipment, $first, $file2))->assertNotFound();
        $this->delete(route('shipments.destroy', $this->shipment))->assertStatus(422);
        $this->assertDatabaseHas('shipment_comments', ['id' => $first->id]);
        Storage::disk('local')->assertExists($file1->file_path);
        Storage::disk('local')->delete($file1->file_path);
        $this->get($this->fileUrl($this->shipment, $first, $file1))->assertNotFound();
        Storage::disk('local')->assertExists($file2->file_path);
    }

    public function test_csrf_and_employee_office_boundary_protect_comment_mutations(): void
    {
        $employee = User::create(['name' => 'Employee', 'email' => 'ship-employee@example.test', 'password' => 'fixture', 'role' => 'employee']);
        $this->actingAs($employee)->post($this->commentsUrl(), ['body' => 'No office access'])->assertRedirect(route('my.dashboard'));
        $this->actingAs($this->manager);
        $this->app->detectEnvironment(fn () => 'local');
        $this->post($this->commentsUrl(), ['body' => 'Missing CSRF'])->assertStatus(419);
        $this->assertSame(0, DB::table('shipment_comments')->count());
    }

    public function test_client_portal_and_printouts_never_reveal_internal_comment_or_files(): void
    {
        $client = Client::create(['client_number' => 'TABS-CLIENT', 'company_name' => 'Fictional client']);
        $client->forceFill(['portal_enabled' => true])->save();
        $portal = ClientPortalUser::create(['client_id' => $client->id, 'name' => 'Fictional client user',
            'username' => 'tabs-client', 'password' => 'fixture', 'portal_enabled' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->shipment->update(['client_id' => $client->id, 'show_client_portal' => true]);
        $comment = $this->addComment('Confidential shipping decision', [UploadedFile::fake()->create('confidential.pdf', 1, 'application/pdf')]);
        $file = DB::table('shipment_comment_attachments')->where('shipment_comment_id', $comment->id)->first();
        $audit = DB::table('audit_logs')->where('route_name', 'shipments.comments.store')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('Confidential shipping decision', json_encode($audit));
        $this->assertStringNotContainsString('confidential.pdf', json_encode($audit));

        $item = $this->shipment->items()->create(['product_name' => 'Fictional box', 'quantity' => 10, 'unit' => 'pcs']);
        $receipt = ShipmentDeliveryReceipt::create(['shipment_id' => $this->shipment->id, 'recipient_name' => 'Receiver',
            'received_at' => '2026-11-02 10:00:00', 'recorded_by' => $this->manager->id, 'content_hash' => str_repeat('c', 64)]);
        $receipt->lines()->create(['shipment_item_id' => $item->id, 'received_quantity' => 10, 'accepted_quantity' => 10, 'damaged_quantity' => 0]);
        foreach ([route('shipments.print', [$this->shipment, 'summary']), route('shipments.print', [$this->shipment, 'delivery-challan']),
            route('shipments.shipping-mark', $this->shipment), route('shipments.delivery-receipts.print', [$this->shipment, $receipt])] as $printUrl) {
            $this->get($printUrl)->assertOk()->assertDontSee('Confidential shipping decision')->assertDontSee('confidential.pdf');
        }
        auth()->logout();
        foreach ([route('client-portal.shipments.index'), route('client-portal.shipments.show', $this->shipment->id)] as $portalUrl) {
            $this->withSession(['client_portal_user_id' => $portal->id])->get($portalUrl)->assertOk()
                ->assertDontSee('Confidential shipping decision')->assertDontSee('confidential.pdf');
        }
        $this->get($this->fileUrl($this->shipment, $comment, $file))->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Integration;

use App\Models\Note;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class NotesOwnershipTest extends TestCase
{
    use IsolatedDatabase;
    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase(['0001_01_01_000000_create_users_table', '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table', '2026_10_07_120000_create_notes_table']);
        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret-password', 'role' => 'employee']);
        $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret-password', 'role' => 'admin']);
    }

    public function test_create_ignores_forged_owner_and_persists_authenticated_owner(): void
    {
        $this->actingAs($this->owner)->post(route('notes.store'), ['title' => 'Private reminder',
            'body' => 'Only mine', 'colour' => 'yellow', 'user_id' => $this->other->id])->assertRedirect();
        $this->assertDatabaseHas('notes', ['title' => 'Private reminder', 'user_id' => $this->owner->id]);
        $this->assertDatabaseCount('notes', 1);
    }

    public static function foreignActions(): array
    {
        return [['GET', 'notes.edit'], ['PUT', 'notes.update'], ['PATCH', 'notes.pin'], ['PATCH', 'notes.archive'], ['DELETE', 'notes.destroy']];
    }

    #[DataProvider('foreignActions')]
    public function test_even_an_office_admin_cannot_access_somebody_elses_note(string $method, string $route): void
    {
        $note = Note::create(['user_id' => $this->owner->id, 'title' => 'Private', 'body' => 'Unchanged', 'colour' => 'yellow']);
        $this->actingAs($this->other)->call($method, route($route, $note), ['title' => 'Stolen', 'body' => 'Changed', 'is_pinned' => true])->assertNotFound();
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'user_id' => $this->owner->id, 'title' => 'Private', 'body' => 'Unchanged', 'is_pinned' => false]);
    }

    public function test_owner_can_update_pin_archive_restore_and_delete_without_transferring_ownership(): void
    {
        $note = Note::create(['user_id' => $this->owner->id, 'title' => 'Before', 'colour' => 'yellow']);
        $this->actingAs($this->owner)->put(route('notes.update', $note), ['title' => 'After', 'user_id' => $this->other->id])->assertRedirect();
        $this->patch(route('notes.pin', $note), ['pinned' => true])->assertRedirect();
        $this->assertTrue($note->fresh()->is_pinned);
        $this->patch(route('notes.archive', $note))->assertRedirect();
        $this->assertNotNull($note->fresh()->archived_at);
        $this->patch(route('notes.archive', $note))->assertRedirect();
        $this->assertNull($note->fresh()->archived_at);
        $this->assertSame($this->owner->id, $note->fresh()->user_id);
        $this->delete(route('notes.destroy', $note))->assertRedirect();
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }
}

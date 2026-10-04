<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPortalDocument;
use App\Models\ClientPortalUser;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientPortalManagementController extends Controller
{
    /** Keep old bookmarks working, but manage the portal in the client record. */
    public function show(Request $request, Client $client): RedirectResponse
    {
        $query = ['client' => $client, 'tab' => 'portal'];
        $selectedUserId = $request->query('portal_user_id');
        if (is_scalar($selectedUserId) && (string) $selectedUserId !== '') {
            $query['portal_user_id'] = $selectedUserId;
        }

        return redirect()->route('clients.show', $query);
    }

    public function store(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $requestedUserId = $request->input('portal_user_id');
        $isCreatingUser = $request->boolean('create_user');
        $portalUser = $isCreatingUser
            ? null
            : ($requestedUserId
                ? ClientPortalUser::where('client_id', $client->id)->whereKey($requestedUserId)->firstOrFail()
                : ClientPortalUser::where('client_id', $client->id)->first());

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('client_portal_users', 'username')->ignore($portalUser ? $portalUser->id : null)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('client_portal_users', 'email')->ignore($portalUser?->id)],
            'mobile' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:12', 'max:255'],
            'generate_password' => ['nullable', 'boolean'],
            'portal_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        $plainPassword = null;
        if ($request->boolean('generate_password') || ! $portalUser) {
            $plainPassword = ($data['password'] ?? null) ?: Str::random(16);
        } elseif (! empty($data['password'])) {
            $plainPassword = $data['password'];
        }

        $payload = [
            'client_id' => $client->id,
            'name' => $data['name'] ?: ($client->account_person_name ?: $client->company_name),
            'username' => $data['username'],
            'email' => array_key_exists('email', $data)
                ? $data['email']
                : ($portalUser?->email ?? ($isCreatingUser ? null : ($client->account_person_email ?: $client->ceo_email))),
            'mobile' => $data['mobile'] ?: ($client->account_person_contact ?: $client->ceo_contact),
            'portal_enabled' => $request->boolean('portal_enabled', $portalUser?->portal_enabled ?? true),
            'is_active' => $request->boolean('is_active', $portalUser?->is_active ?? true),
            'must_change_password' => $request->boolean('must_change_password', true),
            'updated_by' => Auth::id(),
        ];

        if ($plainPassword) {
            $payload['password'] = Hash::make($plainPassword);
            $payload['must_change_password'] = true;
            $payload['password_changed_at'] = null;
        }

        if ($portalUser) {
            $portalUser->update($payload);
        } else {
            $payload['created_by'] = Auth::id();
            $portalUser = ClientPortalUser::create($payload);
        }

        $hasActivePortalUser = ClientPortalUser::where('client_id', $client->id)
            ->where('portal_enabled', true)
            ->where('is_active', true)
            ->exists();
        $client->forceFill([
            'portal_enabled' => $hasActivePortalUser,
            'portal_enabled_at' => $hasActivePortalUser ? ($client->portal_enabled_at ?: now()) : null,
        ])->save();

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Client portal access updated', 'Your MissPack client portal access has been updated.', 'account', null, null, route('client-portal.dashboard'));

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal', 'portal_user_id' => $portalUser->id])
            ->with('success', 'Client portal credentials saved successfully.')
            ->with('portal_plain_password', $plainPassword)
            ->with('portal_plain_password_user_id', $portalUser->id);
    }

    public function resetPassword(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->firstOrFail();
        $plainPassword = Str::random(16);
        $portalUser->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
            'password_changed_at' => null,
            'updated_by' => Auth::id(),
        ]);

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Password reset', 'Your client portal password has been reset by MissPack team.', 'account', null, null, route('client-portal.login'));

        return redirect()->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('success', 'One-time password reset successfully.')
            ->with('portal_plain_password', $plainPassword)
            ->with('portal_plain_password_user_id', $portalUser->id);
    }

    public function resetPortalUserPassword(Request $request, \App\Models\Client $client, ClientPortalUser $portalUser): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->whereKey($portalUser->id)->firstOrFail();
        $plainPassword = Str::random(16);
        $portalUser->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
            'password_changed_at' => null,
            'updated_by' => Auth::id(),
        ]);
        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Password reset', 'Your client portal password has been reset by MissPack team.', 'account', null, null, route('client-portal.login'));

        return redirect()->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('success', 'One-time password reset successfully.')
            ->with('portal_plain_password', $plainPassword)
            ->with('portal_plain_password_user_id', $portalUser->id);
    }

    public function markPortalUserShared(\App\Models\Client $client, ClientPortalUser $portalUser): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->whereKey($portalUser->id)->firstOrFail();
        $portalUser->update(['invitation_sent_at' => now()]);
        $client->forceFill(['portal_last_shared_at' => now()])->save();

        return redirect()->route('clients.show', [
            'client' => $client,
            'tab' => 'portal',
            'portal_user_id' => $portalUser->id,
        ])->with('success', 'Portal credential sharing marked as done.');
    }

    public function markShared(\App\Models\Client $client): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->firstOrFail();
        $portalUser->update(['invitation_sent_at' => now()]);
        $client->forceFill(['portal_last_shared_at' => now()])->save();

        return redirect()->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('success', 'Portal credential sharing marked as done.');
    }

    public function storeNotification(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:4000'],
            'type' => ['nullable', 'string', 'max:60'],
            'action_url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && ! preg_match('#^/client-portal/[a-zA-Z0-9_/?=&%.-]*$#', $value)) {
                    $fail('Use an internal client portal path, for example /client-portal/projects.');
                }
            }],
        ]);

        app(ClientPortalNotifier::class)->notifyClient(
            $client->id,
            $data['title'],
            $data['message'] ?? null,
            $data['type'] ?? 'info',
            null,
            null,
            filled($data['action_url'] ?? null) ? $data['action_url'] : route('client-portal.dashboard')
        );

        return redirect()->route('clients.show', ['client' => $client, 'tab' => 'notifications'])
            ->with('success', 'Notification sent to client portal.');
    }

    public function storeDocument(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(ClientPortalDocument::categoryOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'is_public_to_client' => ['nullable', 'boolean'],
            'file' => [
                'required', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,webp,gif,heic,heif,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store('client-portal/documents/'.$client->id, 'local');
        $document = ClientPortalDocument::create([
            'client_id' => $client->id,
            'client_portal_user_id' => null,
            'related_type' => 'general',
            'related_id' => null,
            'category' => $data['category'],
            'title' => ($data['title'] ?? null) ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'storage_disk' => 'local',
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'extension' => strtolower((string) $file->getClientOriginalExtension()),
            'is_public_to_client' => $request->boolean('is_public_to_client', true),
            'is_reviewed' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($document->is_public_to_client) {
            app(ClientPortalNotifier::class)->notifyClient(
                $client->id,
                'New document shared',
                'A document has been added to your client portal.',
                'document',
                'general',
                $document->id,
                route('client-portal.attachments.index')
            );
        }

        return redirect()->route('clients.show', ['client' => $client, 'tab' => 'documents'])
            ->with('success', 'Document attached to the client record.');
    }

    public function downloadDocument(Client $client, ClientPortalDocument $document)
    {
        abort_unless((int) $document->client_id === (int) $client->id && $document->file_path, 404);
        $disk = $this->safeStorageDisk($document->storage_disk);
        abort_unless(Storage::disk($disk)->exists($document->file_path), 404);

        return Storage::disk($disk)->response(
            $document->file_path,
            $document->original_name ?: basename($document->file_path),
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            'attachment'
        );
    }

    private function safeStorageDisk(?string $disk): string
    {
        $disk = $disk ?: 'public';
        abort_unless(in_array($disk, ['local', 'public', 's3'], true), 404);

        return $disk;
    }

}

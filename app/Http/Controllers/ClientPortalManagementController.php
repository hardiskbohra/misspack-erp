<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalInvoice;
use App\Models\ClientPortalNotification;
use App\Models\ClientPortalUser;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientPortalManagementController extends Controller
{
    public function show(\App\Models\Client $client): View
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->first();
        $invoices = ClientPortalInvoice::where('client_id', $client->id)->latest('id')->get();
        $notifications = ClientPortalNotification::where('client_id', $client->id)->latest('id')->limit(20)->get();
        $loginUrl = route('client-portal.login');
        $shareMessage = $this->shareMessage($client, $portalUser, session('portal_plain_password'));

        return view('clients.portal', compact('client', 'portalUser', 'invoices', 'notifications', 'loginUrl', 'shareMessage'));
    }

    public function store(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->first();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('client_portal_users', 'username')->ignore($portalUser ? $portalUser->id : null)],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
            'generate_password' => ['nullable', 'boolean'],
            'portal_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        $plainPassword = null;
        if ($request->boolean('generate_password') || ! $portalUser) {
            $plainPassword = $data['password'] ?: Str::random(10);
        } elseif (! empty($data['password'])) {
            $plainPassword = $data['password'];
        }

        $payload = [
            'client_id' => $client->id,
            'name' => $data['name'] ?: ($client->account_person_name ?: $client->company_name),
            'username' => $data['username'],
            'email' => $data['email'] ?: ($client->account_person_email ?: $client->ceo_email),
            'mobile' => $data['mobile'] ?: ($client->account_person_contact ?: $client->ceo_contact),
            'portal_enabled' => $request->boolean('portal_enabled'),
            'is_active' => $request->boolean('is_active'),
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

        $client->forceFill([
            'portal_enabled' => $portalUser->portal_enabled && $portalUser->is_active,
            'portal_enabled_at' => ($portalUser->portal_enabled && $portalUser->is_active) ? ($client->portal_enabled_at ?: now()) : null,
        ])->save();

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Client portal access updated', 'Your MissPack client portal access has been updated.', 'account', null, null, route('client-portal.dashboard'));

        return redirect()
            ->route('clients.portal.show', $client)
            ->with('success', 'Client portal credentials saved successfully.')
            ->with('portal_plain_password', $plainPassword);
    }

    public function resetPassword(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->firstOrFail();
        $plainPassword = Str::random(10);
        $portalUser->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
            'updated_by' => Auth::id(),
        ]);

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Password reset', 'Your client portal password has been reset by MissPack team.', 'account', null, null, route('client-portal.login'));

        return redirect()->route('clients.portal.show', $client)
            ->with('success', 'One-time password reset successfully.')
            ->with('portal_plain_password', $plainPassword);
    }

    public function markShared(\App\Models\Client $client): RedirectResponse
    {
        $portalUser = ClientPortalUser::where('client_id', $client->id)->firstOrFail();
        $portalUser->update(['invitation_sent_at' => now()]);
        $client->forceFill(['portal_last_shared_at' => now()])->save();

        return back()->with('success', 'Portal credential sharing marked as done.');
    }

    public function storeNotification(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:60'],
            'action_url' => ['nullable', 'string', 'max:255'],
        ]);

        app(ClientPortalNotifier::class)->notifyClient(
            $client->id,
            $data['title'],
            $data['message'] ?? null,
            $data['type'] ?? 'info',
            null,
            null,
            $data['action_url'] ?? route('client-portal.dashboard')
        );

        return back()->with('success', 'Notification sent to client portal.');
    }

    public function storeInvoice(Request $request, \App\Models\Client $client): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:client_portal_invoices,invoice_number'],
            'project_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'in:INR,USD,RMB'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys(ClientPortalInvoice::statusOptions()))],
            'is_public_to_client' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        $path = null;
        $originalName = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('client-portal/invoices/'.$client->id, 'public');
            $originalName = $file->getClientOriginalName();
        }

        $invoice = ClientPortalInvoice::create([
            'client_id' => $client->id,
            'project_id' => $data['project_id'] ?? null,
            'invoice_number' => $data['invoice_number'] ?: $this->makeInvoiceNumber(),
            'title' => $data['title'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
            'due_date' => $data['due_date'] ?? null,
            'currency' => $data['currency'],
            'subtotal' => $data['subtotal'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'total_amount' => $data['total_amount'],
            'paid_amount' => $data['paid_amount'] ?? 0,
            'status' => $data['status'],
            'is_public_to_client' => $request->boolean('is_public_to_client', true),
            'file_path' => $path,
            'original_name' => $originalName,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        if ($invoice->is_public_to_client) {
            app(ClientPortalNotifier::class)->notifyClient($client->id, 'New invoice published', 'Invoice '.$invoice->invoice_number.' is now available in your client portal.', 'invoice', 'invoice', $invoice->id, route('client-portal.invoices.show', $invoice));
        }

        return back()->with('success', 'Invoice added to client portal.');
    }

    public function destroyInvoice(ClientPortalInvoice $invoice): RedirectResponse
    {
        if ($invoice->file_path) {
            Storage::disk('public')->delete($invoice->file_path);
        }
        $invoice->delete();

        return back()->with('success', 'Invoice removed from client portal.');
    }

    private function makeInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('ymd').'-';
        $next = str_pad((string) (ClientPortalInvoice::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (ClientPortalInvoice::where('invoice_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function shareMessage(\App\Models\Client $client, ?ClientPortalUser $portalUser, ?string $plainPassword = null): string
    {
        if (! $portalUser) {
            return '';
        }

        $message = "Hello ".$client->company_name.",\n\nYour MissPack Client Portal is ready.\nLogin URL: ".route('client-portal.login')."\nUsername: ".$portalUser->username;

        if ($plainPassword) {
            $message .= "\nOne-time Password: ".$plainPassword;
        } else {
            $message .= "\nPassword: The latest one-time password shared by MissPack.";
        }

        $message .= "\n\nPlease login and change your password.\nMissPack - Packed Perfect";

        return $message;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPortalConversation;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientPortalSupportManagementController extends Controller
{
    public function index(Request $request, Client $client): View
    {
        $status = $request->query('status', 'all');
        if ($status !== 'all' && ! array_key_exists($status, ClientPortalConversation::statusOptions())) {
            $status = 'all';
        }

        $conversations = ClientPortalConversation::query()
            ->where('client_id', $client->id)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->with('portalUser')
            ->withCount('messages')
            ->withCount(['messages as unread_client_messages_count' => fn ($messages) => $messages
                ->where('sender_type', 'client')
                ->whereNull('read_at')])
            ->latest('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('clients.portal-support.index', [
            'client' => $client,
            'conversations' => $conversations,
            'status' => $status,
            'statusOptions' => ClientPortalConversation::statusOptions(),
        ]);
    }

    public function show(Client $client, int $conversation): View
    {
        $conversation = ClientPortalConversation::query()
            ->where('client_id', $client->id)
            ->with(['portalUser', 'messages.portalUser', 'messages.staffUser'])
            ->findOrFail($conversation);

        $conversation->messages()
            ->where('sender_type', 'client')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $conversation->load('messages.portalUser', 'messages.staffUser');

        return view('clients.portal-support.show', compact('client', 'conversation'));
    }

    public function reply(Request $request, Client $client, int $conversation): RedirectResponse
    {
        $conversation = ClientPortalConversation::query()
            ->where('client_id', $client->id)
            ->with('portalUser')
            ->findOrFail($conversation);
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);

        DB::transaction(function () use ($conversation, $data) {
            $conversation->messages()->create([
                'sender_type' => 'staff',
                'sender_id' => (int) Auth::id(),
                'body' => $data['message'],
            ]);
            $conversation->update([
                'status' => 'waiting',
                'last_message_at' => now(),
                'closed_at' => null,
            ]);
        });

        app(ClientPortalNotifier::class)->notifyClient(
            $client->id,
            'MissPack replied to your support request',
            'There is a new reply on “'.$conversation->subject.'”.',
            'support',
            'support',
            $conversation->id,
            route('client-portal.support.show', $conversation->id)
        );

        return back()->with('success', 'Reply sent to the client portal.');
    }

    public function updateStatus(Request $request, Client $client, int $conversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(ClientPortalConversation::statusOptions()))],
        ]);

        $conversation = ClientPortalConversation::query()
            ->where('client_id', $client->id)
            ->findOrFail($conversation);
        $conversation->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'resolved' ? now() : null,
        ]);

        return back()->with('success', 'Support request status updated.');
    }
}

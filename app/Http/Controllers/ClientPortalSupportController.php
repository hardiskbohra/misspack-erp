<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalConversation;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientPortalSupportController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $client = $this->client($request);
        $status = $request->query('status', 'all');
        $category = $request->query('category', 'all');

        if ($status !== 'all' && ! array_key_exists($status, ClientPortalConversation::statusOptions())) {
            $status = 'all';
        }
        if ($category !== 'all' && ! array_key_exists($category, ClientPortalConversation::categoryOptions())) {
            $category = 'all';
        }

        $conversations = ClientPortalConversation::query()
            ->where('client_id', $client->id)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
            ->with('portalUser')
            ->withCount('messages')
            ->withCount(['messages as unread_reply_count' => fn ($messages) => $messages
                ->where('sender_type', 'staff')
                ->whereNull('read_at')])
            ->latest('last_message_at')
            ->paginate(12)
            ->withQueryString();

        $statusOptions = ClientPortalConversation::statusOptions();
        $categoryOptions = ClientPortalConversation::categoryOptions();
        $priorityOptions = ClientPortalConversation::priorityOptions();

        return view('client_portal.support.index', compact(
            'conversations', 'status', 'category', 'statusOptions', 'categoryOptions', 'priorityOptions'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:180'],
            'category' => ['required', 'string', Rule::in(array_keys(ClientPortalConversation::categoryOptions()))],
            'priority' => ['required', 'string', Rule::in(array_keys(ClientPortalConversation::priorityOptions()))],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $conversation = DB::transaction(function () use ($portalUser, $data) {
            $conversation = ClientPortalConversation::create([
                'client_id' => $portalUser->client_id,
                'client_portal_user_id' => $portalUser->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            $conversation->messages()->create([
                'sender_type' => 'client',
                'sender_id' => $portalUser->id,
                'body' => $data['message'],
            ]);

            return $conversation;
        });

        app(ClientPortalNotifier::class)->notifyPortalUser(
            $portalUser,
            'Support request received',
            'Your request “'.$conversation->subject.'” has been added to your support inbox.',
            'support',
            'support',
            $conversation->id,
            route('client-portal.support.show', $conversation->id)
        );

        return redirect()->route('client-portal.support.show', $conversation->id)
            ->with('success', 'Your support request has been sent.');
    }

    public function show(Request $request, int $conversation): View
    {
        $portalUser = $this->portalUser($request);
        $conversation = ClientPortalConversation::query()
            ->where('client_id', $portalUser->client_id)
            ->with(['portalUser', 'messages.portalUser', 'messages.staffUser'])
            ->findOrFail($conversation);

        $conversation->messages()
            ->where('sender_type', 'staff')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $conversation->load('messages.portalUser', 'messages.staffUser');

        return view('client_portal.support.show', compact('conversation', 'portalUser'));
    }

    public function reply(Request $request, int $conversation): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        $conversation = ClientPortalConversation::query()
            ->where('client_id', $portalUser->client_id)
            ->findOrFail($conversation);
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);

        DB::transaction(function () use ($conversation, $portalUser, $data) {
            $conversation->messages()->create([
                'sender_type' => 'client',
                'sender_id' => $portalUser->id,
                'body' => $data['message'],
            ]);
            $conversation->update([
                'status' => 'open',
                'last_message_at' => now(),
                'closed_at' => null,
            ]);
        });

        return back()->with('success', 'Your message has been sent.');
    }

    public function close(Request $request, int $conversation): RedirectResponse
    {
        $conversation = ClientPortalConversation::query()
            ->where('client_id', $this->client($request)->id)
            ->findOrFail($conversation);
        $conversation->update(['status' => 'resolved', 'closed_at' => now()]);

        return back()->with('success', 'This request has been marked as resolved.');
    }

    public function reopen(Request $request, int $conversation): RedirectResponse
    {
        $conversation = ClientPortalConversation::query()
            ->where('client_id', $this->client($request)->id)
            ->findOrFail($conversation);
        $conversation->update(['status' => 'open', 'closed_at' => null, 'last_message_at' => now()]);

        return back()->with('success', 'This request has been reopened.');
    }
}

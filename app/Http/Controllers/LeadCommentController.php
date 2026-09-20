<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadCommentController extends Controller
{
    public function store(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'comment' => ['required', 'string'],
            'comment_type' => ['required', 'in:internal,customer_update,follow_up,purchase_note'],
            'next_follow_up_at' => ['nullable', 'date'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['created_by'] = Auth::id();

        $lead->comments()->create($data);

        return back()->with('success', 'Comment added successfully.');
    }

    public function destroy(LeadComment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('success', 'Comment deleted successfully.');
    }
}

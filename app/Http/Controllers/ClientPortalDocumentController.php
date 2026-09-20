<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalDocument;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalDocumentController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $category = $request->query('category', 'all');
        $documents = ClientPortalDocument::where('client_id', $this->client($request)->id)
            ->when($category !== 'all', function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $categories = ClientPortalDocument::categoryOptions();

        return view('client_portal.attachments.index', compact('documents', 'category', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        $data = $request->validate([
            'related_type' => ['nullable', 'string', 'max:80'],
            'related_id' => ['nullable', 'integer'],
            'category' => ['required', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:20480'],
        ]);

        $count = 0;
        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('client-portal/documents/'.$portalUser->client_id, 'public');

            ClientPortalDocument::create([
                'client_id' => $portalUser->client_id,
                'client_portal_user_id' => $portalUser->id,
                'related_type' => $data['related_type'] ?? 'general',
                'related_id' => $data['related_id'] ?? null,
                'category' => $data['category'],
                'title' => $data['title'] ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'notes' => $data['notes'] ?? null,
            ]);
            $count++;
        }

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Document uploaded', $count.' document(s) uploaded successfully.', 'document', $data['related_type'] ?? 'general', $data['related_id'] ?? null, route('client-portal.attachments.index'));

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Request $request, ClientPortalDocument $document): RedirectResponse
    {
        abort_unless($document->client_id === $this->client($request)->id, 404);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();

        return back()->with('success', 'Document deleted successfully.');
    }

    private function validateAllowedFile($file): void
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip'];
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Only image, PDF, Office, CSV, TXT and ZIP files are allowed.',
            ]);
        }
    }
}

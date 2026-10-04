<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalDocument;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalDocumentController extends ClientPortalBaseController
{
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'pdf', 'doc', 'docx',
        'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip',
    ];

    public function index(Request $request): View
    {
        $client = $this->client($request);
        $category = $request->query('category', 'all');
        if ($category !== 'all' && ! array_key_exists($category, ClientPortalDocument::categoryOptions())) {
            $category = 'all';
        }

        $documents = ClientPortalDocument::query()
            ->where('client_id', $client->id)
            ->where('is_public_to_client', true)
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
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
            'category' => ['required', 'string', Rule::in(array_keys(ClientPortalDocument::categoryOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => [
                'required', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,webp,gif,heic,heif,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip',
            ],
        ]);

        $count = 0;
        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('client-portal/documents/'.$portalUser->client_id, 'local');

            ClientPortalDocument::create([
                'client_id' => $portalUser->client_id,
                'client_portal_user_id' => $portalUser->id,
                // General uploads cannot attach themselves to arbitrary ERP records.
                // Contextual uploads set these server-side after ownership checks.
                'related_type' => 'general',
                'related_id' => null,
                'category' => $data['category'],
                'title' => ($data['title'] ?? null) ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'storage_disk' => 'local',
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'is_public_to_client' => true,
                'notes' => $data['notes'] ?? null,
            ]);
            $count++;
        }

        app(ClientPortalNotifier::class)->notifyPortalUser(
            $portalUser,
            'Document uploaded',
            $count.' document(s) uploaded successfully.',
            'document',
            'general',
            null,
            route('client-portal.attachments.index')
        );

        return back()->with('success', 'Document uploaded securely.');
    }

    public function file(Request $request, ClientPortalDocument $document)
    {
        abort_unless(
            $document->client_id === $this->client($request)->id && $document->is_public_to_client,
            404
        );

        return $this->streamDocument($request, $document);
    }

    public function destroy(Request $request, ClientPortalDocument $document): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        abort_unless(
            $document->client_id === $portalUser->client_id
                && $document->is_public_to_client
                && (int) $document->client_portal_user_id === (int) $portalUser->id,
            404
        );

        $disk = $this->safeDisk($document->storage_disk);
        if ($document->file_path) {
            Storage::disk($disk)->delete($document->file_path);
        }
        $document->delete();

        return back()->with('success', 'Your document has been deleted.');
    }

    private function streamDocument(Request $request, ClientPortalDocument $document)
    {
        $disk = $this->safeDisk($document->storage_disk);
        abort_unless($document->file_path && Storage::disk($disk)->exists($document->file_path), 404);

        $mimeType = $document->mime_type ?: 'application/octet-stream';
        $isSafeInlineImage = Str::startsWith($mimeType, 'image/')
            && in_array(strtolower((string) $document->extension), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);

        return Storage::disk($disk)->response(
            $document->file_path,
            $document->original_name ?: basename($document->file_path),
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $isSafeInlineImage && ! $request->boolean('download') ? 'inline' : 'attachment'
        );
    }

    private function safeDisk(?string $disk): string
    {
        $disk = $disk ?: 'public';
        abort_unless(in_array($disk, ['local', 'public', 's3'], true), 404);

        return $disk;
    }

    private function validateAllowedFile($file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Only image, PDF, Office, CSV, TXT and ZIP files are allowed.',
            ]);
        }
    }
}

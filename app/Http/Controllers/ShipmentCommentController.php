<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentComment;
use App\Models\ShipmentCommentAttachment;
use App\Services\ShipmentCommentFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Private office comments are never part of the shipment's public documents. */
class ShipmentCommentController extends Controller
{
    private const FILE_TYPES = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/x-csv', 'application/vnd.ms-excel'],
        'txt' => ['text/plain'],
    ];

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->requireTables();
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['required', 'file', 'max:10240'],
        ]);
        $files = (array) $request->file('attachments', []);
        foreach ($files as $index => $file) $this->validateFile($file, (string) $index);

        $stored = [];
        try {
            DB::transaction(function () use ($shipment, $request, $data, $files, &$stored) {
                // The same parent-first lock is used by comment and shipment
                // deletion, so an upload cannot attach to a deleted shipment.
                $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                $comment = $locked->comments()->create(['body' => $data['body'], 'created_by' => $request->user()->id]);
                foreach ($files as $file) {
                    // Register the random destination *before* writing: a disk
                    // can report failure after leaving a partially written file.
                    $directory = 'shipments/comments/'.$locked->id;
                    $name = $file->hashName();
                    $path = $directory.'/'.$name;
                    $stored[] = $path;
                    if ($file->storeAs($directory, $name, 'local') !== $path) {
                        throw new RuntimeException('Could not store the private shipment comment attachment.');
                    }
                    $comment->attachments()->create([
                        'file_path' => $path,
                        'original_name' => $this->safeName($file),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $file->getSize(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            app(ShipmentCommentFiles::class)->remove($stored);
            throw $exception;
        }

        return redirect()->route('shipments.show', ['shipment' => $shipment, 'tab' => 'comments'])
            ->with('success', 'Internal shipment comment added.');
    }

    public function file(Shipment $shipment, ShipmentComment $comment, ShipmentCommentAttachment $attachment)
    {
        $this->requireTables();
        abort_unless((int) $comment->shipment_id === (int) $shipment->id
            && (int) $attachment->shipment_comment_id === (int) $comment->id, 404);
        $disk = Storage::disk('local');
        abort_unless($attachment->file_path && $disk->exists($attachment->file_path), 404);

        // Force a download even for images/PDF: user-provided data must never
        // execute on the ERP origin, and private responses must not be cached.
        return $disk->download($attachment->file_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroy(Shipment $shipment, ShipmentComment $comment): RedirectResponse
    {
        $this->requireTables();
        $paths = DB::transaction(function () use ($shipment, $comment) {
            Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $locked = ShipmentComment::query()->whereKey($comment->id)->where('shipment_id', $shipment->id)->lockForUpdate()->firstOrFail();
            $paths = $locked->attachments()->lockForUpdate()->pluck('file_path')->all();
            $locked->delete();
            return $paths;
        });

        $removed = app(ShipmentCommentFiles::class)->remove($paths);
        return redirect()->route('shipments.show', ['shipment' => $shipment, 'tab' => 'comments'])
            ->with($removed ? 'success' : 'warning', $removed
                ? 'Internal shipment comment deleted.'
                : 'Comment deleted, but some private files could not be removed. Please contact an administrator.');
    }

    private function validateFile(UploadedFile $file, string $index): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        if (! isset(self::FILE_TYPES[$extension]) || ! in_array($mime, self::FILE_TYPES[$extension], true)) {
            throw ValidationException::withMessages([
                'attachments.'.$index => 'Use a matching PDF, JPG, PNG, WEBP, GIF, DOC, DOCX, XLS, XLSX, CSV or TXT file.',
            ]);
        }
    }

    private function safeName(UploadedFile $file): string
    {
        $name = preg_replace('/[\\x00-\\x1f\\x7f\\/\\\\]/', '_', $file->getClientOriginalName());
        return mb_substr(trim((string) $name) ?: 'attachment', 0, 255);
    }

    private function requireTables(): void
    {
        abort_unless(Schema::hasTable('shipment_comments') && Schema::hasTable('shipment_comment_attachments'), 503, 'Shipment comments require the latest database migration.');
    }
}

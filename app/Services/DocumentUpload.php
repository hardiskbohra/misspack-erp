<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * What may be uploaded, and what gets recorded about it.
 *
 * The same seventeen extensions and the same 20 MB ceiling were written out in
 * five controllers (cashflow attachments, the client portal's own documents and
 * shipment uploads, both project attachment paths), each with its own copy of
 * the list and its own wording of the same error. Five copies of a security
 * allowlist is five chances for one of them to drift, so the list lives here and
 * every upload path asks this class.
 *
 * The metadata is here for the same reason: a stored file is only useful if
 * something remembered its real name, its size and its type, and every module
 * was assembling that array by hand.
 */
class DocumentUpload
{
    /** The allowlist. Images, PDFs, Office documents, text and archives. */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip',
    ];

    /** The ceiling, in kilobytes: 20 MB. Big enough for a scanned bundle. */
    public const MAX_KB = 20480;

    public const MESSAGE = 'Only JPG, JPEG, PNG, WEBP, GIF, HEIC, HEIF, PDF, DOC, DOCX, '
        .'XLS, XLSX, CSV, PPT, PPTX, TXT and ZIP files are allowed, up to 20 MB each.';

    public static function extension(UploadedFile $file): string
    {
        return strtolower((string) $file->getClientOriginalExtension());
    }

    public static function allows(UploadedFile $file): bool
    {
        return in_array(self::extension($file), self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Refuse a file this application will not store.
     *
     * `$field` is the input the error belongs to, so a form can light up the
     * right control instead of a generic banner.
     */
    public static function assertAllowed(UploadedFile $file, string $field = 'attachments'): void
    {
        if (! self::allows($file)) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }

        /* `max:` on the rules and this check are not the same test: the rule
           reads the request's own limit, this one reads the file. A large upload
           that PHP truncated arrives here with its real size gone. */
        if ((int) $file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
    }

    /** The validation rules an upload control should carry. */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.self::MAX_KB,
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
        ];
    }

    /**
     * Store the file and return the columns that describe it.
     *
     * @return array<string, mixed>
     */
    public static function store(UploadedFile $file, string $folder): array
    {
        return [
            'file_path' => $file->store($folder, 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'extension' => self::extension($file),
        ];
    }

    /** The same array for a file that is being replaced: the old one goes. */
    public static function replace(UploadedFile $file, string $folder, ?string $oldPath): array
    {
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return self::store($file, $folder);
    }

    /**
     * Send a stored file as a download, or null when the server no longer has
     * it.
     *
     * One owner for "serve a stored document", because the two surfaces that do
     * it — the office's record page and the employee's own workspace — must
     * behave identically when a file has been moved or deleted underneath them.
     */
    public static function download(?string $path, string $name): ?BinaryFileResponse
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return response()->download(Storage::disk('public')->path($path), $name);
    }

    /**
     * A temporary file for something the application generates (a CSV, a ZIP),
     * so it can be sent as a download without ever being written into the
     * uploads folder.
     */
    public static function temporaryDownload(string $contents, string $filename, string $mime = 'text/csv; charset=UTF-8')
    {
        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

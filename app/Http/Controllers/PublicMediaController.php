<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Only the public disk: never resolve a private document through this route. */
class PublicMediaController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        // Reject hidden segments, traversal, Windows separators and control bytes.
        abort_if(preg_match('/[\\\\\x00-\x1f\x7f]/', $path), 404);
        foreach (explode('/', $path) as $segment) {
            abort_if($segment === '' || str_starts_with($segment, '.'), 404);
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        // realpath also prevents a symlink inside the public disk escaping its root.
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/x-icon', 'image/vnd.microsoft.icon', 'application/pdf'], true);
        $response = response()->file($file, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age=86400',
        ]);
        // SVG, HTML, script and other active content must not execute on the ERP origin.
        $response->setContentDisposition($inline ? 'inline' : 'attachment', basename($file));

        return $response;
    }
}

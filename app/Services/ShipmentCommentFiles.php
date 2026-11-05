<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Cleanup is deliberately limited to the private disk. */
class ShipmentCommentFiles
{
    public function remove(array $paths): bool
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
        if ($paths === []) return true;

        try {
            if (Storage::disk('local')->delete($paths)) return true;
        } catch (Throwable $exception) {
            // These are relative random storage paths, not original filenames
            // or comment contents. They let an administrator locate leftovers
            // after the database rows have already been removed.
            Log::warning('Could not remove private shipment comment files.', ['count' => count($paths), 'paths' => $paths, 'error' => $exception->getMessage()]);
            return false;
        }

        Log::warning('Could not remove private shipment comment files.', ['count' => count($paths), 'paths' => $paths]);
        return false;
    }
}

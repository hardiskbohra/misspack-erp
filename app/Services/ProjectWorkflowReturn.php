<?php

namespace App\Services;

use Illuminate\Http\Request;

/** Allows source-document forms to return to the project that launched them. */
class ProjectWorkflowReturn
{
    public function from(Request $request): ?string
    {
        $return = trim((string) $request->input('return_to'));
        $projectBase = route('projects.index').'/';

        return $return !== '' && str_starts_with($return, $projectBase)
            ? $return
            : null;
    }
}

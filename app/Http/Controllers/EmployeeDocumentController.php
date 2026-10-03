<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\DocumentUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The office's side of an employee's papers: file them on somebody's behalf,
 * mark them seen, remove them.
 *
 * "Mark as seen" is the whole reason this side exists. An employee uploading
 * their own ID proof is a claim; `verified_at` is the office saying they have
 * looked at the paper itself, and the difference is what makes the file worth
 * anything at an audit — which is why a verified document cannot be quietly
 * replaced by the person who uploaded it (see the workspace controller), and why
 * the employee's own page shows the mark beside every paper.
 */
class EmployeeDocumentController extends Controller
{
    /** File a document against somebody, from their record page. */
    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(EmployeeDocument::TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date'],
            'documents' => ['required', 'array', 'max:10'],
            'documents.*' => DocumentUpload::rules(),
        ]);

        $count = 0;

        foreach ((array) $request->file('documents', []) as $file) {
            DocumentUpload::assertAllowed($file, 'documents');

            EmployeeDocument::create(array_merge(DocumentUpload::store($file, 'employee-documents/'.$user->id), [
                'user_id' => $user->id,
                'document_type' => $data['document_type'],
                'title' => $data['title'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'uploaded_by' => Auth::id(),
            ]));

            $count++;
        }

        return back()->with('success', $count === 1
            ? 'Document added to '.$user->name.'\'s file.'
            : $count.' documents added to '.$user->name.'\'s file.');
    }

    /**
     * Mark a document seen — or take the mark back.
     *
     * Reversible on purpose: verification names the person who did it and when,
     * and a mistake (the wrong paper, the wrong employee) has to be undoable
     * without deleting the evidence.
     */
    public function verify(Request $request, User $user, EmployeeDocument $document): RedirectResponse
    {
        $this->assertOwned($user, $document);

        $verified = $request->boolean('verified', true);

        $document->update($verified
            ? ['verified_by' => Auth::id(), 'verified_at' => now()]
            : ['verified_by' => null, 'verified_at' => null]);

        return back()->with('success', $verified
            ? $document->typeLabel().' marked as seen.'
            : 'The verification mark was removed.');
    }

    public function destroy(User $user, EmployeeDocument $document): RedirectResponse
    {
        $this->assertOwned($user, $document);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $label = $document->typeLabel();
        $document->delete();

        return back()->with('success', $label.' removed from '.$user->name.'\'s file.');
    }

    public function file(User $user, EmployeeDocument $document): BinaryFileResponse|RedirectResponse
    {
        $this->assertOwned($user, $document);

        return DocumentUpload::download($document->file_path, $document->fileName())
            ?? back()->with('error', 'That file is no longer on the server.');
    }

    /** A document from the URL has to belong to the person in the URL. */
    private function assertOwned(User $user, EmployeeDocument $document): void
    {
        abort_unless((int) $document->user_id === (int) $user->id, 404);
    }
}

<?php

namespace App\Services;

use App\Events\VendorKycWasSubmitted;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorKycDocument;
use App\Models\VendorKycEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Vendor KYC is staged identity data, never a public vendor-master editor. */
class VendorKyc
{
    public const FIELDS = [
        'vendor_name', 'brand_name', 'vendor_type', 'website',
        'contact_person_name', 'contact_person_email', 'contact_person_mobile', 'whatsapp_number', 'alternate_contact',
        'country', 'state', 'city', 'pincode', 'address', 'gstin', 'pan', 'tax_id', 'import_export_code',
        'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'swift_code', 'bank_branch', 'bank_address', 'beneficiary_address',
    ];

    public const BANK_FIELDS = ['bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'swift_code', 'bank_branch', 'bank_address', 'beneficiary_address'];

    public static function isIndian(?string $country): bool
    {
        return in_array(mb_strtolower(trim($country ?? '')), ['india', 'in', 'ind', 'bharat', 'भारत'], true);
    }

    public static function availableEvidence(Vendor $vendor): array
    {
        $available = [];
        foreach (VendorKycDocument::where('vendor_id', $vendor->id)->whereIn('category', ['gst_certificate', 'pan_document'])->get() as $doc) {
            if (Storage::disk('local')->exists($doc->file_path)) {
                $available[$doc->category] = true;
            }
        }

        return $available;
    }

    private static function assertIndianCompliance(array $profile, array $available): void
    {
        if (! self::isIndian($profile['country'] ?? null)) {
            return;
        }
        Validator::make(array_merge($profile, [
            'gst_certificate' => $available['gst_certificate'] ?? false,
            'pan_document' => $available['pan_document'] ?? false,
        ]), [
            'gstin' => ['required', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'account_number' => ['required', 'string', 'max:255'],
            'ifsc_code' => ['required', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'],
            'gst_certificate' => ['accepted'], 'pan_document' => ['accepted'],
        ], [
            'gstin.required' => 'GST number is required for Indian vendors.',
            'account_number.required' => 'Bank account number is required for Indian vendors.',
            'ifsc_code.required' => 'IFSC code is required for Indian vendors.',
            'gst_certificate.accepted' => 'GST Certificate upload is required for Indian vendors.',
            'pan_document.accepted' => 'PAN upload is required for Indian vendors.',
        ])->validate();
    }

    public static function statusOptions(): array
    {
        return ['draft' => 'Draft', 'under_review' => 'Under review', 'approved' => 'Approved', 'revision' => 'Changes requested', 'rejected' => 'Rejected'];
    }

    public static function canReview(Vendor $vendor, User $user): bool
    {
        return $vendor->kyc_status === 'under_review'
            && app(PermissionMatrix::class)->allows($user, 'purchase.manage');
    }

    public static function profile(Vendor $vendor): array
    {
        // A newly issued bearer link must not disclose pre-existing bank data.
        return $vendor->kyc_data ?? array_replace(Arr::only($vendor->getAttributes(), self::FIELDS), array_fill_keys(self::BANK_FIELDS, null));
    }

    public function link(Vendor $record, User $actor, string $action): void
    {
        DB::transaction(function () use ($record, $actor, $action) {
            $v = Vendor::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($action === 'revoke') {
                $v->kyc_public_token = null;
            } elseif ($action === 'mark_sent') {
                if (! $v->kyc_public_token) {
                    $this->invalid('action', 'Create a KYC link before marking it as sent.');
                }
                $v->kyc_sent_at = now();
            } elseif ($action === 'rotate' || ! $v->kyc_public_token) {
                $v->kyc_public_token = Str::random(48);
                $v->kyc_sent_at = null;
            } else {
                return;
            } // Issuing twice must not silently invalidate a shared link.
            $v->kyc_maker_id ??= $v->created_by ?: $actor->id;
            if ($action !== 'mark_sent') {
                $v->kyc_version++;
            }
            $v->save();
            $this->audit($v, 'link_'.$action, $actor);
        });
    }

    public function savePublic(string $token, Request $request): Vendor
    {
        $stored = [];
        try {
            $vendor = DB::transaction(function () use ($token, $request, &$stored) {
                $v = Vendor::where('kyc_public_token', $token)->lockForUpdate()->firstOrFail();
                if (! $v->isPublicKycEditable()) {
                    $this->invalid('intent', 'This KYC is locked. Contact MissPack to request changes.');
                }
                $meta = $request->validate(['intent' => ['required', Rule::in(['draft', 'submit'])], 'kyc_version' => ['required', 'integer', 'min:0']]);
                $this->checkVersion($v, (int) $meta['kyc_version']);
                $final = $meta['intent'] === 'submit';
                $rules = [];
                foreach (self::FIELDS as $field) {
                    $rules[$field] = ['nullable', 'string', 'max:255'];
                }
                foreach (['address', 'bank_address', 'beneficiary_address'] as $field) {
                    $rules[$field] = ['nullable', 'string', 'max:3000'];
                }
                $rules['pincode'] = ['nullable', 'string', 'max:30'];
                foreach (['contact_person_mobile', 'whatsapp_number', 'alternate_contact'] as $field) {
                    $rules[$field] = ['bail', 'nullable', 'string', 'max:40', function ($attribute, $value, $fail) {
                        if (strlen(preg_replace('/\D/', '', (string) $value)) < 8) {
                            $fail('Enter a phone number with at least 8 digits.');
                        }
                    }];
                }
                $rules['vendor_type'] = ['nullable', Rule::in(array_keys(Vendor::typeOptions()))];
                $rules['contact_person_email'] = ['nullable', 'email', 'max:255'];
                $rules['website'] = ['nullable', 'url:http,https', 'max:255'];
                $rules['gstin'] = ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'];
                $rules['pan'] = ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'];
                $rules['ifsc_code'] = ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'];
                if ($final) {
                    foreach (['vendor_name', 'vendor_type', 'contact_person_name', 'contact_person_email', 'contact_person_mobile', 'country', 'city', 'address'] as $field) {
                        $rules[$field] = array_merge(['required'], array_values(array_filter($rules[$field], fn ($rule) => $rule !== 'nullable')));
                    }
                }
                $rules['documents'] = ['nullable', 'array', 'max:5'];
                $fileRules = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'];
                $rules['documents.*'] = $fileRules;
                foreach (['gst_certificate', 'pan_document'] as $category) {
                    $rules[$category] = array_merge(['nullable'], $fileRules);
                }
                $data = Arr::only($request->validate($rules), self::FIELDS);
                foreach (['gstin', 'pan', 'ifsc_code', 'swift_code'] as $field) {
                    if (isset($data[$field])) {
                        $data[$field] = strtoupper($data[$field]);
                    }
                }
                $uploads = [];
                foreach ($request->file('documents', []) as $file) {
                    $uploads[] = ['file' => $file, 'category' => 'supporting'];
                }
                $available = self::availableEvidence($v);
                foreach (['gst_certificate', 'pan_document'] as $category) {
                    if ($request->hasFile($category)) {
                        $uploads[] = ['file' => $request->file($category), 'category' => $category];
                        $available[$category] = true;
                    }
                }
                if (count($uploads) > 5) {
                    $this->invalid('documents', 'Upload a maximum of 5 files per save, including GST Certificate and PAN.');
                }
                $v->kyc_data = array_replace(self::profile($v), $data);
                if ($final) {
                    self::assertIndianCompliance($v->kyc_data, $available);
                }
                $v->kyc_version++;
                if (VendorKycDocument::where('vendor_id', $v->id)->count() + count($uploads) > 20) {
                    $this->invalid('documents', 'A maximum of 20 supporting documents is allowed. Contact MissPack for help.');
                }
                foreach ($uploads as $upload) {
                    $file = $upload['file'];
                    $path = $file->store('vendor-kyc/'.$v->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Unable to store KYC document.');
                    }
                    $stored[] = $path;
                    VendorKycDocument::create(['vendor_id' => $v->id, 'file_path' => $path, 'category' => $upload['category'],
                        'original_name' => mb_substr(preg_replace('/[\x00-\x1f\x7f]/', '', basename($file->getClientOriginalName())), 0, 200),
                        'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'kyc_version' => $v->kyc_version]);
                }
                if ($final) {
                    $v->kyc_status = 'under_review';
                    $v->kyc_submitted_data = $v->kyc_data;
                    $v->kyc_submitted_at = now();
                    $v->kyc_revision_note = $v->kyc_rejection_reason = null;
                    $v->kyc_reviewed_at = $v->kyc_reviewed_by = null;
                }
                $v->save();
                $this->audit($v, $final ? 'submitted' : 'draft_saved');

                return $v;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        if ($request->input('intent') === 'submit') {
            VendorKycWasSubmitted::dispatch($vendor);
        }

        return $vendor;
    }

    public function review(Vendor $record, User $actor, array $data): void
    {
        DB::transaction(function () use ($record, $actor, $data) {
            $v = Vendor::whereKey($record->id)->lockForUpdate()->firstOrFail();
            // State first gives repeat requests a useful conflict, not a false permissions error.
            if ($v->kyc_status !== 'under_review') {
                $this->invalid('decision', 'Only a current submission can be reviewed.');
            }
            abort_unless(self::canReview($v, $actor), 403, 'Vendor management access is required to review KYC.');
            $this->checkVersion($v, (int) $data['kyc_version']);
            $decision = $data['decision'];
            if ($decision === 'approved') {
                self::assertIndianCompliance($v->kyc_submitted_data ?? [], self::availableEvidence($v));
                $v->fill(Arr::only($v->kyc_submitted_data ?? [], self::FIELDS));
            }
            $v->kyc_status = $decision;
            $v->kyc_reviewed_at = now();
            $v->kyc_reviewed_by = $actor->id;
            $v->kyc_revision_note = $decision === 'revision' ? $data['reason'] : null;
            $v->kyc_rejection_reason = $decision === 'rejected' ? $data['reason'] : null;
            $v->kyc_version++;
            $v->save();
            $this->audit($v, $decision, $actor, $data['reason'] ?? null);
        });
    }

    public function reopen(Vendor $record, User $actor, array $data): void
    {
        DB::transaction(function () use ($record, $actor, $data) {
            $v = Vendor::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (! in_array($v->kyc_status, ['approved', 'rejected'], true)) {
                $this->invalid('reason', 'Only completed KYC can be reopened. Use Request changes for a pending submission.');
            }
            $this->checkVersion($v, (int) $data['kyc_version']);
            $v->kyc_status = 'revision';
            $v->kyc_revision_note = $data['reason'];
            $v->kyc_rejection_reason = null;
            $v->kyc_reviewed_at = now();
            $v->kyc_reviewed_by = $actor->id;
            $v->kyc_version++;
            $v->save();
            $this->audit($v, 'reopened', $actor, $data['reason']);
        });
    }

    /** Keep normal vendor editing authoritative; never leave changed bank data labelled approved. */
    public function updateOfficeProfile(Vendor $record, array $data, User $actor, ?int $version): void
    {
        DB::transaction(function () use ($record, $data, $actor, $version) {
            $v = Vendor::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $hasKyc = array_key_exists('kyc_version', $v->getAttributes());
            if ($hasKyc) {
                $this->checkVersion($v, $version);
            }
            $v->fill($data);
            $changed = $hasKyc && count(array_intersect(array_keys($v->getDirty()), self::FIELDS)) > 0;
            if ($changed) {
                $v->kyc_status = 'draft';
                $v->kyc_maker_id = $actor->id;
                $v->kyc_data = $v->kyc_submitted_data = null;
                $v->kyc_reviewed_at = $v->kyc_reviewed_by = $v->kyc_submitted_at = null;
                $v->kyc_revision_note = $v->kyc_rejection_reason = null;
                $v->kyc_version++;
            }
            $v->save();
            if ($changed) {
                $this->audit($v, 'office_profile_changed', $actor);
            }
        });
    }

    private function checkVersion(Vendor $v, ?int $version): void
    {
        if ($version !== (int) $v->kyc_version) {
            $this->invalid('kyc_version', 'This KYC has changed. Reload the page before continuing.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function audit(Vendor $v, string $action, ?User $actor = null, ?string $reason = null): void
    {
        VendorKycEvent::create(['vendor_id' => $v->id, 'actor_id' => $actor?->id, 'action' => $action, 'status' => $v->kyc_status,
            'kyc_version' => $v->kyc_version, 'reason' => $reason,
            'snapshot' => in_array($action, ['submitted', 'approved', 'revision', 'rejected'], true) ? [
                'profile' => $v->kyc_submitted_data, 'document_ids' => VendorKycDocument::where('vendor_id', $v->id)->pluck('id')->all(),
            ] : null]);
    }
}

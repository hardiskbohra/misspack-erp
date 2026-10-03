<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One paper in an employee's file.
 *
 * The type is the vocabulary the office already uses for hiring — identity,
 * address, resume, experience — because the point of the file is to be the same
 * list the HR checklist has, not a folder of files named by whoever uploaded
 * them. `documentTypeOptions()` is the single definition of that list; the
 * checklist on the employee's own page, the upload form and the admin's record
 * page all read it.
 */
class EmployeeDocument extends Model
{
    use HasFactory;

    public const TYPE_ID_PROOF = 'id_proof';
    public const TYPE_ADDRESS_PROOF = 'address_proof';
    public const TYPE_RESUME = 'resume';
    public const TYPE_EXPERIENCE = 'experience_certificate';
    public const TYPE_EDUCATION = 'education';
    public const TYPE_PAN = 'pan_card';
    public const TYPE_BANK_PROOF = 'bank_proof';
    public const TYPE_OFFER_LETTER = 'offer_letter';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'user_id', 'document_type', 'title', 'document_number',
        'file_path', 'original_name', 'mime_type', 'file_size', 'extension',
        'expires_on', 'verified_by', 'verified_at', 'remarks', 'uploaded_by',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'verified_at' => 'datetime',
        'file_size' => 'integer',
    ];

    /**
     * The document types, in the order an HR file is built.
     *
     * `required` is what "the employee's file is complete" means: the three
     * papers every employer must hold. Everything else is the office's choice,
     * and the checklist says so rather than nagging about a passport photo.
     *
     * @var array<string, array{label: string, required: bool, hint: string}>
     */
    public const TYPES = [
        self::TYPE_ID_PROOF => ['label' => 'ID Proof', 'required' => true, 'hint' => 'Aadhaar, passport, driving licence or voter card'],
        self::TYPE_ADDRESS_PROOF => ['label' => 'Address Proof', 'required' => true, 'hint' => 'Electricity bill, rent agreement or bank statement'],
        self::TYPE_RESUME => ['label' => 'Resume / CV', 'required' => true, 'hint' => 'The CV they applied with'],
        self::TYPE_EXPERIENCE => ['label' => 'Experience Certificate', 'required' => false, 'hint' => 'Relieving letter or experience certificate from a previous employer'],
        self::TYPE_EDUCATION => ['label' => 'Education Certificate', 'required' => false, 'hint' => 'Degree, diploma or marksheet'],
        self::TYPE_PAN => ['label' => 'PAN Card', 'required' => false, 'hint' => 'Needed before the first salary is paid'],
        self::TYPE_BANK_PROOF => ['label' => 'Bank Proof', 'required' => false, 'hint' => 'Cancelled cheque or passbook page'],
        self::TYPE_OFFER_LETTER => ['label' => 'Offer / Appointment Letter', 'required' => false, 'hint' => 'Signed copy held by the employee'],
        self::TYPE_OTHER => ['label' => 'Other', 'required' => false, 'hint' => 'Anything else in the file'],
    ];

    /** @return array<string, string> */
    public static function documentTypeOptions(): array
    {
        return array_map(fn (array $type) => $type['label'], self::TYPES);
    }

    /** The three papers that make an employee's file complete. */
    public static function requiredTypes(): array
    {
        return array_keys(array_filter(self::TYPES, fn (array $type) => $type['required']));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type]['label'] ?? Str::headline((string) $this->document_type);
    }

    /** What the row is called on screen: their title, or the type's name. */
    public function displayName(): string
    {
        return trim((string) ($this->title ?: $this->typeLabel()));
    }

    public function fileName(): string
    {
        return (string) ($this->original_name ?: $this->displayName());
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '—';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max(1, $bytes / 1024), 0).' KB';
    }

    /** Whether the employee put it there themselves. */
    public function uploadedByEmployee(): bool
    {
        return $this->uploaded_by !== null && (int) $this->uploaded_by === (int) $this->user_id;
    }
}

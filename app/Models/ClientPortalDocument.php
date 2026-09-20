<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientPortalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'client_portal_user_id', 'related_type', 'related_id', 'category', 'title',
        'file_path', 'original_name', 'mime_type', 'file_size', 'extension', 'is_public_to_client',
        'is_reviewed', 'notes',
    ];

    protected $casts = [
        'is_public_to_client' => 'boolean',
        'is_reviewed' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function fileUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? Str::headline((string) $this->category);
    }

    public static function categoryOptions(): array
    {
        return [
            'general' => 'General Document',
            'kyc' => 'KYC Document',
            'artwork' => 'Artwork / Design',
            'po' => 'Purchase Order',
            'payment_proof' => 'Payment Proof',
            'invoice' => 'Invoice Related',
            'shipment' => 'Shipment Document',
            'project' => 'Project Document',
            'other' => 'Other',
        ];
    }
}

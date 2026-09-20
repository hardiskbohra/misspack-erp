<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'attachment_type', 'title', 'file_path', 'external_url', 'original_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

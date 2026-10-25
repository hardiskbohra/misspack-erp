<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorKycDocument extends Model
{
    protected $guarded = ['id'];

    public function categoryLabel(): string
    {
        return ['gst_certificate' => 'GST Certificate', 'pan_document' => 'PAN', 'supporting' => 'Supporting document'][$this->category ?? 'supporting'] ?? 'Supporting document';
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}

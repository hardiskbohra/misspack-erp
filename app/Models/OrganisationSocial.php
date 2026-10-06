<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationSocial extends Model
{
    public const NETWORKS = [
        'instagram' => ['Instagram', 'fa-brands fa-instagram'],
        'facebook' => ['Facebook', 'fa-brands fa-facebook'],
        'linkedin' => ['LinkedIn', 'fa-brands fa-linkedin'],
        'pinterest' => ['Pinterest', 'fa-brands fa-pinterest'],
        'youtube' => ['YouTube', 'fa-brands fa-youtube'],
        'x' => ['X', 'fa-brands fa-x-twitter'],
        'whatsapp' => ['WhatsApp', 'fa-brands fa-whatsapp'],
        'website' => ['Website', 'fa-solid fa-globe'],
    ];

    protected $fillable = [
        'organisation_id', 'network', 'handle', 'url',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function networkLabel(): string
    {
        return self::NETWORKS[$this->network][0] ?? ucfirst((string) $this->network);
    }

    public function networkIcon(): string
    {
        return self::NETWORKS[$this->network][1] ?? 'fa-solid fa-link';
    }
}

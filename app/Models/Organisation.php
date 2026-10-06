<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Organisation extends Model
{
    protected $fillable = [
        'trade_name', 'legal_name', 'tagline', 'email', 'mobile', 'website', 'website_url',
        'instagram', 'instagram_handle', 'gstin', 'pan', 'cin', 'iec', 'msme', 'lut',
        'jurisdiction_city', 'jurisdiction_state', 'logo_path', 'logo_print_path',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(OrganisationAddress::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(OrganisationBank::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(OrganisationContact::class);
    }

    public function socials(): HasMany
    {
        return $this->hasMany(OrganisationSocial::class);
    }

    public static function current(): self
    {
        static $cached = false;
        static $row = null;

        if ($cached) {
            return $row ??= new self;
        }

        $cached = true;

        if (! Schema::hasTable('organisations')) {
            $row = (new self)->forceFill(self::fallback());

            return $row;
        }

        $with = ['addresses', 'banks'];
        if (Schema::hasTable('organisation_contacts')) {
            $with[] = 'contacts';
        }
        if (Schema::hasTable('organisation_socials')) {
            $with[] = 'socials';
        }

        $row = static::query()->with($with)->first() ?? (new self)->forceFill(self::fallback());

        return $row;
    }

    public static function fallback(): array
    {
        return [
            'trade_name' => 'MissPack',
            'legal_name' => 'MissPack India Pvt Ltd',
            'tagline' => 'Packed Perfect',
            'email' => 'misspackindia@gmail.com',
            'mobile' => '7041110823',
            'website' => 'www.themisspack.com',
            'website_url' => 'https://www.themisspack.com',
            'instagram' => 'https://www.instagram.com/themisspack/',
            'instagram_handle' => '@themisspack',
            'gstin' => '24AATCM8816E1Z5',
            'pan' => 'AATCM8816E',
            'jurisdiction_city' => 'Ahmedabad',
            'jurisdiction_state' => 'Gujarat',
        ];
    }

    public function defaultAddress(string $kind): ?OrganisationAddress
    {
        $rows = $this->relationLoaded('addresses') ? $this->addresses : $this->addresses()->get();

        return $rows->first(fn (OrganisationAddress $row) => $row->kind === $kind && $row->is_default)
            ?: $rows->firstWhere('kind', $kind);
    }

    public function defaultBank(): ?OrganisationBank
    {
        $rows = $this->relationLoaded('banks') ? $this->banks : $this->banks()->get();

        return $rows->firstWhere('is_default', true) ?: $rows->first();
    }

    public function logoUrl(string $which = 'screen'): string
    {
        $path = $which === 'print' ? $this->logo_print_path : $this->logo_path;
        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset($which === 'print' ? 'images/logo-dark.png' : 'images/logo.png');
    }

    public function social(string $network): ?OrganisationSocial
    {
        if (! Schema::hasTable('organisation_socials') || ! $this->exists) {
            return null;
        }

        $rows = $this->relationLoaded('socials') ? $this->socials : $this->socials()->get();

        return $rows->firstWhere('network', $network);
    }

    public function brand(): array
    {
        $instagram = $this->social('instagram');

        return [
            'name' => $this->trade_name ?: config('brand.name'),
            'legal_name' => $this->legal_name ?: config('brand.name'),
            'tagline' => $this->tagline ?: config('brand.tagline'),
            'website' => $this->website ?: config('brand.website'),
            'website_url' => $this->website_url ?: config('brand.website_url'),
            'instagram' => $instagram?->url ?: ($this->instagram ?: config('brand.instagram')),
            'instagram_handle' => $instagram?->handle ?: ($this->instagram_handle ?: config('brand.instagram_handle')),
            'logo_print' => $this->logo_print_path && Storage::disk('public')->exists($this->logo_print_path)
                ? 'storage/'.$this->logo_print_path
                : config('brand.logo_print'),
            'logo_dark' => $this->logo_path && Storage::disk('public')->exists($this->logo_path)
                ? 'storage/'.$this->logo_path
                : config('brand.logo_dark'),
        ];
    }

    public function sellerDetails(): array
    {
        $bill = $this->defaultAddress('billing');
        $bank = $this->defaultBank();

        return [
            'seller_company_name' => $this->legal_name,
            'seller_address' => trim(implode(', ', array_filter([$bill?->line1, $bill?->line2]))),
            'seller_city' => $bill?->city,
            'seller_state' => $bill?->state,
            'seller_country' => $bill?->country,
            'seller_pincode' => $bill?->pincode,
            'seller_gstin' => $this->gstin,
            'seller_pan' => $this->pan,
            'seller_email' => $this->email,
            'seller_mobile' => $this->mobile,
            'seller_website' => $this->website,
            'seller_bank_name' => $bank?->bank_name,
            'seller_account_holder' => $bank?->account_holder,
            'seller_account_number' => $bank?->account_number,
            'seller_ifsc' => $bank?->ifsc,
            'seller_branch' => $bank?->branch,
            'seller_swift' => $bank?->swift,
        ];
    }

    public function buyerDetails(): array
    {
        $seller = $this->sellerDetails();
        $map = [];
        foreach ($seller as $key => $value) {
            $map[str_replace('seller_', 'buyer_', $key)] = $value;
        }

        unset(
            $map['buyer_bank_name'],
            $map['buyer_account_holder'],
            $map['buyer_account_number'],
            $map['buyer_ifsc'],
            $map['buyer_branch'],
            $map['buyer_swift']
        );

        return $map;
    }
}

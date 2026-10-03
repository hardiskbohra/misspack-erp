<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * One statement of account, handed to one party, for one period.
 *
 * The row is the *right to look*: the statement is rebuilt from the ledger on
 * every open, so it can never be stale, and this record is what says whether
 * the door is still open (revoked_at, expires_at) and what was sent when
 * (shared_via, first_viewed_at, views).
 *
 * A link is a bearer token: whoever holds it reads that party's statement. It
 * is therefore never listed anywhere public, expires by default, and every
 * open is counted — a statement that has been read is a statement that can be
 * followed up on.
 */
class PartyStatementShare extends Model
{
    use HasFactory;

    /** The two ledgers a statement can be built from. */
    public const PARTY_TYPES = [
        'client' => 'Client',
        'vendor' => 'Vendor',
    ];

    /** How the link left the building. */
    public const CHANNELS = [
        'link' => 'Copy link',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'portal' => 'Client portal',
    ];

    /** How long a new link lives, in days. 0 = never expires. */
    public const EXPIRY_CHOICES = [
        7 => '7 days',
        30 => '30 days',
        90 => '90 days',
        0 => 'No expiry',
    ];

    /** The default: long enough to be read, short enough to be a handover. */
    public const DEFAULT_EXPIRY_DAYS = 30;

    protected $fillable = [
        'token', 'party_type', 'party_id', 'party_name', 'party_currency',
        'date_from', 'date_to', 'label', 'note', 'options', 'shared_via',
        'expires_at', 'revoked_at', 'views', 'first_viewed_at', 'last_viewed_at',
        'last_viewed_ip', 'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'options' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'views' => 'integer',
    ];

    /* --------------------------------------------------------------- issuing */

    /**
     * Mint a link. The token is generated here, not by the caller, so there is
     * one place that answers "what makes a token unique" — and it retries,
     * because a unique index that fails on a birthday collision is a 500 in
     * front of a customer.
     */
    public static function issue(array $attributes): self
    {
        $attributes['token'] = static::freshToken();
        $attributes['created_by'] = $attributes['created_by'] ?? Auth::id();
        $attributes['options'] = $attributes['options'] ?? [];

        $days = (int) ($attributes['expires_in_days'] ?? self::DEFAULT_EXPIRY_DAYS);
        unset($attributes['expires_in_days']);
        $attributes['expires_at'] = $days > 0 ? now()->addDays($days) : null;

        return static::create($attributes);
    }

    public static function freshToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    /* ---------------------------------------------------------------- state */

    /** Is this link still a door? */
    public function isLive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** live · expired · revoked — one word for the log's badge. */
    public function state(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'live';
    }

    public function stateLabel(): string
    {
        return [
            'live' => 'Live',
            'expired' => 'Expired',
            'revoked' => 'Revoked',
        ][$this->state()];
    }

    /** "Never" reads better than an empty cell in a column about time. */
    public function expiresLabel(): string
    {
        return $this->expires_at?->format('d M Y') ?? 'Never';
    }

    public function periodLabel(): string
    {
        if (! $this->date_from || ! $this->date_to) {
            return 'All time';
        }

        return $this->date_from->format('d M Y').' – '.$this->date_to->format('d M Y');
    }

    /** What the log shows first: the label if it has one, else the period. */
    public function title(): string
    {
        return $this->label ?: $this->periodLabel();
    }

    public function partyTypeLabel(): string
    {
        return self::PARTY_TYPES[$this->party_type] ?? Str::headline((string) $this->party_type);
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->shared_via] ?? '—';
    }

    public function option(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /** The link itself. Named for what it is, so a view never spells the route. */
    public function url(): string
    {
        return route('statements.public', $this->token);
    }

    /** The message that goes out with the link — one wording, app and mail alike. */
    public function message(): string
    {
        return 'Statement of account — '.$this->party_name
            .' — '.$this->title()
            .'. View or download it here: '.$this->url();
    }

    /**
     * WhatsApp, where most of these actually go.
     *
     * A ten-digit number is an Indian mobile without its country code — the
     * number as the office writes it down. Anything longer is trusted as it is,
     * because a vendor abroad already carries their country code.
     */
    public function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->message());
    }

    /** The same message, as a draft the user can edit before sending. */
    public function mailUrl(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return 'mailto:'.$email
            .'?subject='.rawurlencode('Statement of account — '.$this->party_name.' — '.$this->title())
            .'&body='.rawurlencode($this->message());
    }

    /* -------------------------------------------------------------- scopes */

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForParty(Builder $query, string $type, int $id): Builder
    {
        return $query->where('party_type', $type)->where('party_id', $id);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

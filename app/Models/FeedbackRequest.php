<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * One ask, and the link it travels on.
 *
 * The row is the *right to answer*: the token is the whole of the authentication
 * the public form has, so it is long, it expires by default, it can be revoked
 * in one click, and every open is counted — which is how the office tells
 * "they didn't want to answer" apart from "they never saw it".
 *
 * `issue()` is the only place a request is created and the only place a token is
 * made. It retries, because a unique index that fails on a birthday collision is
 * a 500 in front of a customer.
 *
 * `state()` answers one question in one word — live, opened, answered, expired,
 * revoked — and the list, the badges and the project tab all read that word
 * rather than re-deriving it from four timestamps in three places.
 */
class FeedbackRequest extends Model
{
    use HasFactory;

    public const KIND_CLOSE_OUT = 'close_out';
    public const KIND_PULSE = 'pulse';

    public const STATE_LIVE = 'live';
    public const STATE_OPENED = 'opened';
    public const STATE_ANSWERED = 'answered';
    public const STATE_EXPIRED = 'expired';
    public const STATE_REVOKED = 'revoked';

    /** The ladder is two reminders, and the third is pestering. */
    public const MAX_REMINDERS = 2;

    public const DEFAULT_EXPIRY_DAYS = 45;

    protected $fillable = [
        'token', 'project_id', 'client_id', 'kind', 'note', 'created_by', 'shared_via',
        'expires_at', 'revoked_at', 'views', 'first_viewed_at', 'last_viewed_at',
        'last_viewed_ip', 'reminder_count', 'last_reminded_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'views' => 'integer',
        'reminder_count' => 'integer',
    ];

    /* ------------------------------------------------------------------ issue */

    /**
     * Hand someone the right to answer.
     *
     * @param  array  $attributes  project_id, client_id, kind, note, expires_in_days, shared_via
     */
    public static function issue(array $attributes): self
    {
        $attributes['token'] = static::freshToken();
        $attributes['created_by'] = $attributes['created_by'] ?? Auth::id();

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

    /* ------------------------------------------------------------------ state */

    /** Is this link still a door? */
    public function isLive(): bool
    {
        return $this->revoked_at === null
            && ! $this->hasAnswered()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasAnswered(): bool
    {
        return $this->relationLoaded('response')
            ? $this->response !== null
            : $this->response()->exists();
    }

    /** live · opened · answered · expired · revoked — one word for every badge. */
    public function state(): string
    {
        if ($this->hasAnswered()) {
            return self::STATE_ANSWERED;
        }

        if ($this->revoked_at !== null) {
            return self::STATE_REVOKED;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return self::STATE_EXPIRED;
        }

        return $this->views > 0 ? self::STATE_OPENED : self::STATE_LIVE;
    }

    public function stateLabel(): string
    {
        return [
            self::STATE_LIVE => 'Live',
            self::STATE_OPENED => 'Opened',
            self::STATE_ANSWERED => 'Answered',
            self::STATE_EXPIRED => 'Expired',
            self::STATE_REVOKED => 'Revoked',
        ][$this->state()];
    }

    /** A colour the badge can wear without the view inventing one. */
    public function stateTone(): string
    {
        return [
            self::STATE_LIVE => 'blue',
            self::STATE_OPENED => 'purple',
            self::STATE_ANSWERED => 'green',
            self::STATE_EXPIRED => 'orange',
            self::STATE_REVOKED => 'red',
        ][$this->state()];
    }

    /** May the first reminder go out, and the second, and then stop? */
    public function canRemind(): bool
    {
        return $this->isLive()
            && $this->reminder_count < self::MAX_REMINDERS
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function expiresLabel(): string
    {
        return $this->expires_at?->format('d M Y') ?? 'Never';
    }

    public function kindLabel(): string
    {
        return self::kindOptions()[$this->kind] ?? Str::headline((string) $this->kind);
    }

    public function channelLabel(): string
    {
        return $this->shared_via ? (self::CHANNELS[$this->shared_via] ?? Str::headline($this->shared_via)) : '—';
    }

    /** What the log and the project tab read first. */
    public function title(): string
    {
        return $this->kind === self::KIND_PULSE ? 'Mid-project pulse' : 'Project feedback';
    }

    /* --------------------------------------------------------------- the link */

    /** The link itself. Named for what it is, so a view never spells the route. */
    public function url(): string
    {
        return route('feedback.public.show', $this->token);
    }

    /**
     * The message that goes out with the link — one wording, app and mail alike.
     * Shorter than a statement's: the ask is a favour, and a paragraph about it
     * reads like a bill.
     */
    public function message(): string
    {
        $project = $this->project?->name ?? 'your recent project';

        return 'Thank you for working with us on '.$project
            .'. Could you spare two minutes to tell us how it went? '.$this->url();
    }

    /**
     * WhatsApp, where most of these actually go.
     *
     * A ten-digit number is an Indian mobile without its country code — the
     * number as the office writes it down. Anything longer is trusted as it is.
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
            .'?subject='.rawurlencode('How did we do? — '.($this->project?->name ?? 'MissPack'))
            .'&body='.rawurlencode($this->message());
    }

    /* ----------------------------------------------------------------- scopes */

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->whereDoesntHave('response')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeKind(Builder $query, ?string $kind): Builder
    {
        return $kind ? $query->where('kind', $kind) : $query;
    }

    /**
     * One state, as SQL — the same five words `state()` returns for a row, so a
     * chip that says "Opened" lists exactly the rows that would wear that badge.
     */
    public function scopeState(Builder $query, ?string $state): Builder
    {
        $unanswered = fn (Builder $q) => $q->whereDoesntHave('response');

        return match ($state) {
            self::STATE_LIVE => $query->whereNull('revoked_at')->where($unanswered)->where('views', 0)
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            self::STATE_OPENED => $query->whereNull('revoked_at')->where($unanswered)->where('views', '>', 0)
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            self::STATE_ANSWERED => $query->whereHas('response'),
            self::STATE_EXPIRED => $query->whereNull('revoked_at')->where($unanswered)
                ->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            self::STATE_REVOKED => $query->whereNotNull('revoked_at'),
            default => $query,
        };
    }

    /** The asks whose answer scored a certain way. */
    public function scopeBand(Builder $query, string $band): Builder
    {
        return $query->whereHas('response', function (Builder $response) use ($band) {
            match ($band) {
                'promoter' => $response->promoters(),
                'detractor' => $response->detractors(),
                'attention' => $response->needsAttention(),
                'passive' => $response
                    ->whereNotIn('id', FeedbackResponse::query()->detractors()->select('id'))
                    ->whereNotIn('id', FeedbackResponse::query()->promoters()->select('id')),
                default => $response,
            };
        });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($search) {
            $nested->where('token', 'like', "%{$search}%")
                ->orWhere('note', 'like', "%{$search}%")
                ->orWhereHas('project', fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_number', 'like', "%{$search}%"))
                ->orWhereHas('client', fn ($q) => $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%"));
        });
    }

    /* -------------------------------------------------------------- relations */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function response()
    {
        return $this->hasOne(FeedbackResponse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ------------------------------------------------------------- vocabulary */

    public static function kindOptions(): array
    {
        return [
            self::KIND_CLOSE_OUT => 'Close-out feedback',
            self::KIND_PULSE => 'Mid-project pulse',
        ];
    }

    public static function expiryChoices(): array
    {
        return [0 => 'Never', 15 => '15 days', 30 => '30 days', 45 => '45 days', 90 => '90 days'];
    }

    public const CHANNELS = [
        'link' => 'Link copied',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'portal' => 'Client portal',
    ];

    /** The client's best contact for this project, for the share buttons. */
    public function contactName(): ?string
    {
        return $this->client?->account_person_name ?: $this->client?->ceo_name;
    }

    public function contactEmail(): ?string
    {
        return $this->client?->account_person_email ?: $this->client?->ceo_email;
    }

    public function contactPhone(): ?string
    {
        return $this->client?->account_person_contact ?: $this->client?->ceo_contact;
    }
}

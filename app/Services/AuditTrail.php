<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClientPortalUser;
use App\Models\User;
use App\Models\VendorPortalUser;
use App\Models\VendorShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditTrail
{
    private const SECRET_FIELDS = ['password','password_confirmation','current_password','token','_token','remember_token','account_number','bank_account_number'];

    public function mutation(Request $request, mixed $response, array $actor = []): void
    {
        if (! Schema::hasTable('audit_logs')) return;
        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
        $identity = $actor ?: $this->actor($request);
        $subject = $this->subject($request);
        $inputKeys = collect(array_keys($request->except(self::SECRET_FIELDS)))->reject(fn ($key) => str_starts_with($key, '_'))->values()->all();
        $this->write([
            'event' => $request->route()?->getName() ?: 'web.mutation',
            'outcome' => $status >= 400 ? 'failed' : 'success',
            'route_name' => $request->route()?->getName(), 'method' => $request->method(),
            'url' => $this->safeUrl($request), 'response_status' => $status,
            'changed_fields' => $inputKeys,
            'context' => ['route_parameters' => $subject['parameters'], 'content_type' => $request->header('Content-Type')],
        ] + $identity + $subject['subject'] + $this->requestFacts($request));
    }

    public function denied(Request $request, User $user, string $permission): void
    {
        $this->write([
            'event' => 'permission.denied', 'outcome' => 'denied', 'actor_type' => 'office_user', 'actor_id' => $user->id,
            'actor_name' => $user->name, 'actor_identifier' => $user->email, 'route_name' => $request->route()?->getName(),
            'method' => $request->method(), 'url' => $this->safeUrl($request), 'response_status' => 403,
            'changed_fields' => [], 'context' => ['required_permission' => $permission],
        ] + $this->requestFacts($request));
    }

    public function event(string $event, array $data = []): void
    {
        $this->write(array_merge(['event' => $event, 'outcome' => 'success', 'method' => 'SYSTEM', 'occurred_at' => now()], $data));
    }

    private function write(array $data): void
    {
        if (! Schema::hasTable('audit_logs')) return;
        AuditLog::create($data + ['occurred_at' => now()]);
    }

    private function actor(Request $request): array
    {
        if ($user = Auth::user()) return ['actor_type' => 'office_user','actor_id' => $user->id,'actor_name' => $user->name,'actor_identifier' => $user->email];
        if ($user = $request->attributes->get('clientPortalUser')) return $this->portalActor('client_portal_user', $user);
        if ($user = $request->attributes->get('vendorPortalUser')) return $this->portalActor('vendor_portal_user', $user);
        $token = $request->route('token');
        if ($token && $request->routeIs('vendor-shares.*')) {
            $share = VendorShareLink::where('token', $token)->first();
            if ($share) return ['actor_type' => 'vendor_share_link','actor_id' => $share->id,'actor_name' => $share->label,'actor_identifier' => 'vendor:'.$share->vendor_id];
        }
        return ['actor_type' => 'public','actor_id' => null,'actor_name' => null,'actor_identifier' => null];
    }

    private function portalActor(string $type, ClientPortalUser|VendorPortalUser $user): array
    {
        return ['actor_type' => $type,'actor_id' => $user->id,'actor_name' => $user->displayName(),'actor_identifier' => $user->email ?: $user->username];
    }

    private function subject(Request $request): array
    {
        $parameters = []; $subject = ['auditable_type' => null, 'auditable_id' => null];
        foreach ($request->route()?->parameters() ?: [] as $key => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                $parameters[$key] = $value->getKey();
                if ($subject['auditable_id'] === null) $subject = ['auditable_type' => $value::class, 'auditable_id' => $value->getKey()];
            } elseif ($key !== 'token' && is_scalar($value)) $parameters[$key] = (string) $value;
        }
        return compact('parameters','subject');
    }

    private function safeUrl(Request $request): string
    {
        $url = $request->fullUrl();
        $token = $request->route('token');
        return is_string($token) && $token !== '' ? str_replace($token, '[redacted-token]', $url) : $url;
    }

    private function requestFacts(Request $request): array
    {
        return ['ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000)];
    }
}

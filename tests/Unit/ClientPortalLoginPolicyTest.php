<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientPortalUser;
use App\Services\ClientPortalAccess;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientPortalLoginPolicyTest extends TestCase
{
    #[DataProvider('accessFlags')]
    public function test_every_enabled_flag_is_required(bool $enabled, bool $active, bool $clientEnabled): void
    {
        $user = new ClientPortalUser(['portal_enabled' => $enabled, 'is_active' => $active]);
        $user->setRelation('client', (new Client())->forceFill(['portal_enabled' => $clientEnabled]));
        $this->assertSame($enabled && $active && $clientEnabled, $user->canLogin());
    }

    public static function accessFlags(): array
    {
        $cases = [];
        foreach ([false, true] as $enabled) foreach ([false, true] as $active) foreach ([false, true] as $clientEnabled) $cases[] = [$enabled, $active, $clientEnabled];
        return $cases;
    }

    public function test_missing_client_or_wrong_request_attribute_never_resolves_portal_access(): void
    {
        $user = new ClientPortalUser(['portal_enabled' => true, 'is_active' => true]);
        $user->setRelation('client', null);
        $this->assertFalse($user->canLogin());
        $request = Request::create('/client-portal');
        $request->attributes->set('clientPortalUser', new \stdClass());
        $access = new ClientPortalAccess();
        $this->assertNull($access->user($request));
        $this->assertNull($access->client($request));
    }
}

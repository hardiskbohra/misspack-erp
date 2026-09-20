<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

abstract class ClientPortalBaseController extends Controller
{
    protected function portalUser(Request $request): ClientPortalUser
    {
        return $request->attributes->get('clientPortalUser');
    }

    protected function client(Request $request)
    {
        return $this->portalUser($request)->client;
    }

    protected function classTableAvailable(string $class, string $table): bool
    {
        return class_exists($class) && Schema::hasTable($table);
    }

    protected function projectsAvailable(): bool
    {
        return $this->classTableAvailable(\App\Models\Project::class, 'projects');
    }

    protected function shipmentsAvailable(): bool
    {
        return $this->classTableAvailable(\App\Models\Shipment::class, 'shipments');
    }

    protected function quotesAvailable(): bool
    {
        return $this->classTableAvailable(\App\Models\CustomerQuote::class, 'customer_quotes');
    }

    protected function productsAvailable(): bool
    {
        return $this->classTableAvailable(\App\Models\Product::class, 'products');
    }
}

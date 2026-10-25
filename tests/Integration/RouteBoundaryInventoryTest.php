<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Registration-level contracts complement actual HTTP and record-ownership tests; not a substitute. */
class RouteBoundaryInventoryTest extends TestCase
{
    public function test_every_office_route_also_requires_authentication(): void
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (! in_array('office', $middleware, true)) continue;
            $this->assertContains('auth', $middleware, $route->uri().' has office access without authentication');
            $count++;
        }
        $this->assertGreaterThan(100, $count, 'The test must inspect the real ERP route collection.');
    }

    public function test_private_note_mutations_are_authenticated_but_not_office_only(): void
    {
        foreach (['store', 'update', 'pin', 'archive', 'destroy'] as $action) {
            $route = Route::getRoutes()->getByName('notes.'.$action);
            $this->assertNotNull($route);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertNotContains('office', $route->gatherMiddleware(), 'Employees must be able to manage their own notes.');
        }
    }
}

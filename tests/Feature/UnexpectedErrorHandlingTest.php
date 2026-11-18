<?php

namespace Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class UnexpectedErrorHandlingTest extends TestCase
{
    private function productionWithDebugMistakenlyEnabled(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);
    }

    public function test_production_html_error_is_safe_even_with_debug_enabled_and_matches_the_reported_reference(): void
    {
        $this->productionWithDebugMistakenlyEnabled();
        $logger = new class extends AbstractLogger {
            public array $records = [];
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };
        app()->instance(LoggerInterface::class, $logger);
        Route::get('/__reliability/html-error', fn () => throw new \RuntimeException('SQLSTATE secret /private/path and customer value'));

        $response = $this->get('/__reliability/html-error')->assertStatus(500)
            ->assertDontSee('SQLSTATE secret')->assertDontSee('/private/path')->assertDontSee('customer value')
            ->assertSee('Check the record')->assertSee('before retrying');
        $reference = $response->headers->get('X-Error-Reference');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $reference);
        $response->assertSee($reference);
        $this->assertTrue(collect($logger->records)->contains(fn ($record) =>
            ($record['context']['error_reference'] ?? null) === $reference
            && ($record['context']['exception'] ?? null) instanceof \RuntimeException
            && str_contains((string) $record['message'], 'SQLSTATE secret')),
            'The original exception must still be reported with the displayed reference.');
    }

    public function test_production_api_error_is_json_and_contains_no_debug_details(): void
    {
        $this->productionWithDebugMistakenlyEnabled();
        Route::get('/api/__reliability/error', fn () => throw new \RuntimeException('SQLSTATE secret /private/path'));

        $response = $this->getJson('/api/__reliability/error')->assertStatus(500)->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message', 'reference'])->assertDontSee('SQLSTATE secret')->assertDontSee('/private/path');
        $this->assertSame($response->json('reference'), $response->headers->get('X-Error-Reference'));
        $this->assertStringContainsString('before retrying', $response->json('message'));
    }

    public function test_explicit_500_response_exception_does_not_bypass_the_safe_fallback(): void
    {
        $this->productionWithDebugMistakenlyEnabled();
        Route::get('/__reliability/explicit-error', fn () => throw new HttpResponseException(response('sensitive internal detail', 500)));
        $response = $this->get('/__reliability/explicit-error')->assertStatus(500)->assertDontSee('sensitive internal detail');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $response->headers->get('X-Error-Reference'));
    }

    public function test_explicit_api_500_uses_json_even_if_the_original_response_was_html(): void
    {
        $this->productionWithDebugMistakenlyEnabled();
        Route::get('/api/__reliability/explicit-error', fn () => throw new HttpResponseException(response('private internal detail', 500)));

        foreach ([$this->getJson('/api/__reliability/explicit-error'), $this->get('/api/__reliability/explicit-error')] as $response) {
            $response->assertStatus(500)->assertHeader('Content-Type', 'application/json')
                ->assertJsonStructure(['message', 'reference'])->assertDontSee('private internal detail');
            $this->assertSame($response->json('reference'), $response->headers->get('X-Error-Reference'));
        }
    }

    public function test_failure_of_the_fallback_log_transport_does_not_expose_the_response_or_cause_another_failure(): void
    {
        $this->productionWithDebugMistakenlyEnabled();
        app()->instance(LoggerInterface::class, new class extends AbstractLogger {
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                throw new \RuntimeException('logging transport unavailable');
            }
        });
        Route::get('/__reliability/log-unavailable', fn () => throw new HttpResponseException(response('private response', 500)));

        $response = $this->get('/__reliability/log-unavailable')->assertStatus(500)
            ->assertDontSee('private response')->assertDontSee('logging transport unavailable')->assertSee('Check the record');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $response->headers->get('X-Error-Reference'));
    }

    public function test_validation_authentication_and_other_client_errors_keep_their_status_and_contract(): void
    {
        $this->productionWithDebugMistakenlyEnabled();
        Route::get('/api/__reliability/validation', fn () => throw ValidationException::withMessages(['name' => 'A name is required.']));
        Route::get('/__reliability/forbidden', fn () => abort(403, 'Not permitted'));
        Route::get('/__reliability/auth', fn () => throw new AuthenticationException());

        $this->getJson('/api/__reliability/validation')->assertStatus(422)->assertJsonPath('errors.name.0', 'A name is required.')
            ->assertHeaderMissing('X-Error-Reference');
        $this->get('/__reliability/forbidden')->assertForbidden()->assertHeaderMissing('X-Error-Reference');
        $this->get('/__reliability/auth')->assertRedirect(route('login'))->assertHeaderMissing('X-Error-Reference');
    }

    public function test_local_debug_mode_retains_the_diagnostic_exception_output(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
        Route::get('/__reliability/local-error', fn () => throw new \RuntimeException('Local diagnostic detail'));
        $this->get('/__reliability/local-error')->assertStatus(500)->assertSee('Local diagnostic detail');
    }
}

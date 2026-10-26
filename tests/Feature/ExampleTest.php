<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_sends_visitors_directly_to_the_public_register(): void
    {
        $this->get('/')->assertRedirect(route('transmittals.index'));
    }

    public function test_the_public_register_is_available_without_authentication(): void
    {
        $this->get(route('transmittals.index'))->assertOk()->assertSee('Transmittal V2');
    }
}

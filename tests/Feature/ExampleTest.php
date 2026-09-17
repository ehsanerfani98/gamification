<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * روت‌های وب — پنل و PWA (فصل ۹).
 */
class ExampleTest extends TestCase
{
    public function test_root_redirects_to_panel(): void
    {
        $this->get('/')->assertRedirect('/panel');
    }

    public function test_panel_host_renders(): void
    {
        $this->get('/panel')->assertOk()->assertSee('id="app"', false);
    }

    public function test_pwa_host_renders_for_valid_slug(): void
    {
        $this->get('/c/demo-campaign')->assertOk()->assertSee('id="app"', false);
    }

    public function test_pwa_host_rejects_invalid_slug(): void
    {
        $this->get('/c/INVALID!')->assertNotFound();
    }
}

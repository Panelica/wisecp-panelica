<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Panelica\Wisecp\Tests\ModuleTestCase;
use WISECP\Modules\Servers\Panelica;

/**
 * The package form, drawn before a server is chosen.
 *
 * Reported from a live install: the server could be picked on the new hosting
 * package screen, but there was no field to pick a Panelica plan at all. The
 * cause was not the form's shape - it was that WISECP builds the module to
 * draw that screen before any server is set, and the API client was a typed
 * property with no default. Reading it threw Error, which is not an Exception,
 * so the catch around the plan list did not catch it, the form died, and
 * nothing was logged anywhere.
 *
 * These run without the fixture, because the point is a module that has not
 * been given a server at all.
 */
final class PackageFormBeforeServerTest extends ModuleTestCase
{
    private function bare(): Panelica
    {
        $class = $this->isLegacy() ? 'Panelica_Module' : 'WISECP\Modules\Servers\Panelica';

        return new $class;          // no server, as that screen builds it
    }

    public function test_the_form_still_offers_its_fields(): void
    {
        $module = $this->bare();
        $form = $this->isLegacy() ? $module->config_options([]) : $module->product_configuration([]);

        $this->assertArrayHasKey('plan_id', $form);
        $this->assertArrayHasKey('account_role', $form);
    }

    public function test_the_plan_field_says_why_it_has_no_list_yet(): void
    {
        $module = $this->bare();
        $form = $this->isLegacy() ? $module->config_options([]) : $module->product_configuration([]);

        // A text box the operator can still fill, and a reason next to it -
        // rather than a dropdown with nothing in it, or no field at all.
        $this->assertSame('text', $form['plan_id']['type']);
        $this->assertStringContainsString('server', strtolower((string) $form['plan_id']['description']));
    }

    public function test_nothing_escapes_that_the_core_cannot_catch(): void
    {
        $module = $this->bare();

        try {
            $this->isLegacy() ? $module->config_options([]) : $module->product_configuration([]);
        } catch (\Throwable $e) {
            $this->fail('the form threw ' . get_class($e) . ': ' . $e->getMessage());
        }

        $this->assertTrue(true);
    }
}

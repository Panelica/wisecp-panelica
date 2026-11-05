<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The names the two cores load the module by.
 *
 * Measured on a live WISECP 3.1.9.8.2 install: the core includes
 * modules/Servers/<Folder>/<Folder>.php and looks for <Folder>_Module in the
 * global namespace. All eighteen of its stock server modules are declared that
 * way and not one uses a namespace. WISECP 5 is the opposite: namespace
 * WISECP\Modules\Servers, class <Folder>.
 *
 * A class the core cannot find is the quietest failure there is - nothing is
 * called, nothing is logged, and the module appears to do nothing at all.
 */
final class ClassNameTest extends ModuleTestCase
{
    public function test_both_names_exist(): void
    {
        $this->assertTrue(class_exists('WISECP\Modules\Servers\Panelica'), 'the name WISECP 5 loads');
        $this->assertTrue(class_exists('Panelica_Module'), 'the name WISECP 3 loads');
    }

    public function test_the_older_name_is_the_same_code_not_a_second_copy(): void
    {
        // One implementation, two names. A second implementation would be two
        // things to fix every time and one of them would be forgotten.
        $this->assertTrue(is_subclass_of('Panelica_Module', 'WISECP\Modules\Servers\Panelica'));

        $ownMethods = array_filter(
            (new \ReflectionClass('Panelica_Module'))->getMethods(),
            static fn (\ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === 'Panelica_Module'
        );

        $this->assertSame([], array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $ownMethods),
            'the older name declares nothing of its own');
    }

    public function test_the_module_loads_its_own_parts(): void
    {
        // The core includes <Folder>.php and nothing else in the directory, so
        // the file has to pull in the trait and the API client itself. Without
        // that it dies the moment WISECP loads it - and the suite would not
        // notice, because the test bootstrap loads them in advance.
        $file = file_get_contents(dirname(__DIR__, 2) . '/coremio/modules/Servers/Panelica/Panelica.php');

        foreach (['PanelicaApi.php', 'PanelicaCore.php', 'ApiClient.php'] as $part) {
            $this->assertStringContainsString("require_once __DIR__ . DIRECTORY_SEPARATOR . '" . $part . "'", (string) $file);
        }
    }
}

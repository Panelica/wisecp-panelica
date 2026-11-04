<?php

declare(strict_types=1);

/*
 * Loads the module outside WISECP: the stand-ins first, then the module, then
 * the fake API and the test case.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../coremio/modules/Servers/Panelica/PanelicaApi.php';
require_once __DIR__ . '/../coremio/modules/Servers/Panelica/Panelica.php';
require_once __DIR__ . '/FakeApi.php';
require_once __DIR__ . '/TestPanelica.php';
require_once __DIR__ . '/ModuleTestCase.php';

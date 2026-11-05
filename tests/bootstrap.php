<?php

declare(strict_types=1);

/*
 * Loads the module outside WISECP: the stand-in host first, then the module,
 * then the fake API and the test case.
 *
 * WISECP_GENERATION picks which host the module is loaded onto. A PHP process
 * can define ServerModule only once, so one run is one generation and the suite
 * is run twice - see composer.json's "test".
 */

require_once __DIR__ . '/../vendor/autoload.php';

$generation = getenv('WISECP_GENERATION') ?: '5';

if (! in_array($generation, ['3', '5'], true)) {
    fwrite(STDERR, "WISECP_GENERATION must be 3 or 5, got: {$generation}\n");
    exit(1);
}

require_once __DIR__ . '/stubs-common.php';
require_once __DIR__ . '/stubs-v' . $generation . '.php';
require_once __DIR__ . '/../coremio/modules/Servers/Panelica/PanelicaApi.php';
require_once __DIR__ . '/../coremio/modules/Servers/Panelica/PanelicaCore.php';
require_once __DIR__ . '/../coremio/modules/Servers/Panelica/Panelica.php';
require_once __DIR__ . '/FakeApi.php';
require_once __DIR__ . '/TestPanelica.php';
require_once __DIR__ . '/ModuleTestCase.php';

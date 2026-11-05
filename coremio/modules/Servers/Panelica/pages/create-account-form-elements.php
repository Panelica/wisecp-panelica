<?php

declare(strict_types=1);

/*
 * The module's own fields on the new hosting package screen.
 *
 * WISECP 3 does not read config_options() by itself. It includes this page, and
 * the page is what asks the module for its fields and hands them to the base
 * class to render. Without this file the screen shows the server selector and
 * then nothing at all - no plan field, and no error anywhere, because nothing
 * was called. That is exactly what was reported, and measured on a live
 * 3.1.9.8.2 install where all eighteen stock server modules carry this page.
 *
 * Deliberately the same six lines as the stock ApisCP module: the fields stay
 * in PHP, where both generations share them, and the base class draws them.
 * "module_data" is the input-name prefix, so what the operator picks is saved
 * as module_data[plan_id] on the product.
 *
 * @var object $module
 * @var array<string,mixed> $product
 */

$product = isset($product) && is_array($product) ? $product : [];
$moduleData = isset($product['module_data']) && class_exists('Utility')
    ? (Utility::jdecode($product['module_data'], true) ?: [])
    : [];

if (method_exists($module, 'config_options') && $fields = $module->config_options(is_array($moduleData) ? $moduleData : [])) {
    return $module->config_options_output($fields, 'module_data');
}

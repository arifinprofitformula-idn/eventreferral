<?php
/**
 * Load shared helpers that used to be pulled from config.php.
 *
 * config.php is often kept outside deployments because it contains secrets.
 * Keeping these includes here lets updated entrypoints work even when a
 * server still has an older, manually maintained config.php.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/theme.php';

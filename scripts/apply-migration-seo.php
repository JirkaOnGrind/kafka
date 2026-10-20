<?php
// CLI-only allowlist repair. No catalog writes, no activation of languages or imported integrations.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db'
    || (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'shop`') !== 1) {
    throw new RuntimeException('Only the isolated single-shop 8083 target is supported.');
}
$options = getopt('', ['apply', 'backup:', 'restore:']);
$policy = json_decode(file_get_contents(__DIR__ . '/migration-seo-policy.json'), true, 512, JSON_THROW_ON_ERROR);
$managed = $policy['configuration'];
$language = (int) Language::getIdByIso($policy['default_language_iso']);
if (!$language) { throw new RuntimeException('Existing Czech language required.'); }
$managed['PS_LANG_DEFAULT'] = (string) $language;
$db = Db::getInstance();
$names = "'" . implode("','", array_map('pSQL', array_keys($managed))) . "'";
$before = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'configuration` WHERE name IN (' . $names . ')');
$changes = [];
foreach ($managed as $key => $value) {
    if ((string) Configuration::get($key) !== $value) { $changes[$key] = ['before' => Configuration::get($key), 'after' => $value]; }
}
if (isset($options['restore'])) {
    $backup = json_decode(file_get_contents($options['restore']), true, 512, JSON_THROW_ON_ERROR);
    if (($backup['target'] ?? '') !== $policy['target'] || ($backup['managed_names'] ?? []) !== array_keys($managed)) { throw new RuntimeException('Wrong settings backup.'); }
    $db->execute('START TRANSACTION');
    try {
        if (!$db->delete('configuration', 'name IN (' . $names . ')')) { throw new RuntimeException('Restore delete failed.'); }
        foreach ($backup['configuration_rows'] as $row) {
            if (!array_key_exists($row['name'], $managed)) { throw new RuntimeException('Unmanaged key in backup.'); }
            unset($row['id_configuration']);
            foreach ($row as &$value) { if (is_string($value)) { $value = pSQL($value); } } unset($value);
            if (!$db->insert('configuration', $row, true)) { throw new RuntimeException('Restore insert failed.'); }
        }
        $db->execute('COMMIT');
    } catch (Throwable $e) { $db->execute('ROLLBACK'); throw $e; }
    Configuration::clearConfigurationCacheForTesting();
    echo json_encode(['restored' => true]), PHP_EOL;
    exit;
}
if (isset($options['apply'])) {
    $path = $options['backup'] ?? '';
    $directory = realpath(dirname($path));
    if (!$directory || str_starts_with($directory . '/', realpath(_PS_ROOT_DIR_) . '/')) { throw new RuntimeException('Backup must be outside webroot.'); }
    if (Configuration::get('PS_DISABLE_OVERRIDES')) { throw new RuntimeException('PS9 overrides are disabled. Review migration compatibility; no unrelated override policy was changed.'); }
    // Module installation updates translations and therefore needs the native Symfony container in CLI.
    $kernel = new AdminKernel('prod', false);
    $kernel->boot();
    $module = Module::getInstanceByName('kafkaseo');
    if (!$module) { throw new RuntimeException('Deploy the tracked kafkaseo module first.'); }
    $backup = ['target' => $policy['target'], 'created_at' => gmdate('c'), 'managed_names' => array_keys($managed),
        'configuration_rows' => $before, 'module_installed' => Module::isInstalled('kafkaseo'), 'module_active' => $module->active];
    $handle = fopen($path, 'x');
    if (!$handle) { throw new RuntimeException('A new private backup is required.'); }
    $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (fwrite($handle, $json) !== strlen($json)) { throw new RuntimeException('Incomplete backup.'); } fclose($handle);
    foreach ($changes as $key => $change) {
        if (!Configuration::updateValue($key, $change['after'])) { throw new RuntimeException('Cannot update ' . $key); }
    }
    if (!Module::isInstalled('kafkaseo') && !$module->install()) { throw new RuntimeException('Cannot install project SEO module.'); }
    if (!$module->active && !$module->enable()) { throw new RuntimeException('Cannot enable project SEO module.'); }
    $method = new ReflectionMethod('Link', 'getProductLink');
    if (str_ends_with(str_replace('\\', '/', $method->getFileName()), '/classes/Link.php')
        && !str_contains(str_replace('\\', '/', $method->getFileName()), '/override/')) {
        if (!$module->addOverride('Link')) { throw new RuntimeException('Cannot install the PS9 legacy Link compatibility override.'); }
    } elseif (!str_contains(file_get_contents($method->getFileName()), 'PS9 adds an empty query marker')) {
        throw new RuntimeException('An unrelated Link override requires manual review; it was not replaced.');
    }
    foreach ($policy['hooks'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) { throw new RuntimeException('Cannot register ' . $hook); }
    }
    if (!Tools::generateHtaccess()) { throw new RuntimeException('Cannot regenerate local rewrite rules.'); }
}
$module = Module::getInstanceByName('kafkaseo');
$hooksReady = $module && Module::isInstalled('kafkaseo') && $module->active;
foreach ($policy['hooks'] as $hook) { $hooksReady = $hooksReady && $module->isRegisteredInHook($hook); }
$method = new ReflectionMethod('Link', 'getProductLink');
$overrideReady = str_contains(str_replace('\\', '/', $method->getFileName()), '/override/')
    && str_contains(file_get_contents($method->getFileName()), 'PS9 adds an empty query marker');
// A newly installed override is loaded by the next PHP process; dry-run idempotence always verifies it fresh.
echo json_encode(['applied' => isset($options['apply']), 'changes' => $changes, 'count' => count($changes),
    'hooks_ready' => (bool) $hooksReady, 'link_override_loaded' => $overrideReady], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;

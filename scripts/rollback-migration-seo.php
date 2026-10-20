<?php
// Restore module state and targeted system titles. Configuration/file restore is orchestrated separately.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Not isolated target.'); }
$options = getopt('', ['backup:', 'apply']);
$directory = realpath($options['backup'] ?? '');
if (!$directory || !str_starts_with($directory, '/lab/seo-runs/seo-')) { throw new RuntimeException('Supply the private SEO run backup directory.'); }
$settings = json_decode(file_get_contents($directory . '/settings-before.json'), true, 512, JSON_THROW_ON_ERROR);
$changes = ['restore_managed_configuration' => true, 'module_installed_before' => $settings['module_installed'],
    'module_active_before' => $settings['module_active'], 'restore_czech_titles' => is_file($directory . '/czech-titles-before.json')];
if (isset($options['apply'])) {
    $kernel = new AdminKernel('prod', false); $kernel->boot();
    $module = Module::getInstanceByName('kafkaseo');
    if (!$settings['module_installed'] && Module::isInstalled('kafkaseo')) {
        if (!$module->uninstall()) { throw new RuntimeException('Cannot uninstall the new SEO module.'); }
    } elseif ($settings['module_installed'] && !$settings['module_active'] && $module->active) {
        if (!$module->disable()) { throw new RuntimeException('Cannot restore inactive module state.'); }
    }
    if ($changes['restore_czech_titles']) {
        foreach (json_decode(file_get_contents($directory . '/czech-titles-before.json'), true, 512, JSON_THROW_ON_ERROR) as $row) {
            // Do not overwrite metadata edited since the repair.
            $where = 'id_meta=' . (int) $row['id_meta'] . ' AND id_lang=' . (int) $row['id_lang'] . ' AND id_shop=' . (int) $row['id_shop']
                . " AND title='" . pSQL($row['new_title']) . "'";
            if (!Db::getInstance()->update('meta_lang', ['title' => pSQL($row['title'])], $where)) { throw new RuntimeException('Title restore failed.'); }
        }
    }
}
echo json_encode(['applied' => isset($options['apply']), 'changes' => $changes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;

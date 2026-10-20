<?php
// Local-only, additive repair. A reviewed image map is data, never executable PHP.
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') {
    throw new RuntimeException('Isolated target only.');
}
$options = getopt('', ['map:', 'apply', 'backup:', 'restore:']);
$db = Db::getInstance();
$table = _DB_PREFIX_ . 'product_attribute_image';
if (isset($options['restore'])) {
    $backup = json_decode(file_get_contents($options['restore']), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode(['rollback_pairs' => count($backup['inserted'])]) . PHP_EOL;
    if (!isset($options['apply'])) { exit; }
    $db->execute('START TRANSACTION');
    try {
        foreach ($backup['inserted'] as [$combination, $image]) {
            if (!$db->execute('DELETE FROM `' . $table . '` WHERE id_product_attribute=' . (int) $combination . ' AND id_image=' . (int) $image)) {
                throw new RuntimeException('Rollback failed.');
            }
        }
        $db->execute('COMMIT');
    } catch (Throwable $e) { $db->execute('ROLLBACK'); throw $e; }
    Tools::clearAllCache();
    exit;
}
$mapPath = $options['map'] ?? '';
$map = json_decode(file_get_contents($mapPath), true, 512, JSON_THROW_ON_ERROR);
$group = (int) $map['color_group'];
$insert = [];
$checked = 0;
foreach ($map['products'] as $product) {
    $id = (int) $product['id_product'];
    $images = $db->executeS('SELECT id_image, position FROM `' . _DB_PREFIX_ . 'image` WHERE id_product=' . $id . ' ORDER BY position');
    if (array_map('intval', array_column($images, 'id_image')) !== array_column($product['images'], 'id_image')) {
        throw new RuntimeException('Image catalog changed; review product ' . $id);
    }
    foreach ($product['images'] as $image) {
        $imageId = (int) $image['id_image'];
        $base = _PS_PRODUCT_IMG_DIR_ . Image::getImgFolderStatic($imageId) . $imageId;
        $file = null;
        foreach (['-default_md.jpg', '-medium_default.jpg', '.jpg'] as $suffix) {
            if (is_file($base . $suffix)) { $file = $base . $suffix; break; }
        }
        if (!$file || hash_file('sha256', $file) !== $image['sha256']) {
            throw new RuntimeException('Reviewed photo changed; review image ' . $imageId);
        }
    }
    $combinations = $db->executeS('SELECT pa.id_product_attribute, a.id_attribute FROM `' . _DB_PREFIX_ . 'product_attribute` pa'
        . ' JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.id_product_attribute=pa.id_product_attribute'
        . ' JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute=pac.id_attribute'
        . ' WHERE pa.id_product=' . $id . ' AND a.id_attribute_group=' . $group);
    $actualColors = array_values(array_unique(array_map('intval', array_column($combinations, 'id_attribute'))));
    $reviewedColors = array_map('intval', array_keys($product['colors']));
    sort($actualColors); sort($reviewedColors);
    if ($actualColors !== $reviewedColors) { throw new RuntimeException('Product colors changed; review product ' . $id); }
    foreach ($combinations as $combination) {
        $attribute = (int) $combination['id_product_attribute'];
        $color = (int) $combination['id_attribute'];
        if (!array_key_exists($color, $product['colors'])) { throw new RuntimeException('New color requires review: ' . $id); }
        $wanted = array_map('intval', $product['colors'][$color]);
        if (array_diff($wanted, array_column($product['images'], 'id_image'))) { throw new RuntimeException('Image belongs to another product.'); }
        $existing = array_map('intval', array_column($db->executeS('SELECT id_image FROM `' . $table . '` WHERE id_product_attribute=' . $attribute), 'id_image'));
        // Never overwrite later manual corrections or delete catalog relationships.
        if (array_diff($existing, $wanted)) { throw new RuntimeException('Existing image links differ; review combination ' . $attribute); }
        foreach (array_diff($wanted, $existing) as $imageId) { $insert[] = [$attribute, $imageId]; }
        ++$checked;
    }
}
echo json_encode(['products' => count($map['products']), 'combinations_checked' => $checked, 'new_links' => count($insert), 'missing_colors' => $map['missing']], JSON_UNESCAPED_UNICODE) . PHP_EOL;
if (!isset($options['apply'])) { exit; }
$backupPath = $options['backup'] ?? '';
if (!str_starts_with($backupPath, '/lab/') || file_exists($backupPath)) { throw new RuntimeException('Supply a new private /lab/ backup path.'); }
$before = $db->executeS('SELECT id_product_attribute,id_image FROM `' . $table . '` ORDER BY id_product_attribute,id_image');
$backup = ['created' => date(DATE_ATOM), 'map_sha256' => hash_file('sha256', $mapPath), 'before' => $before, 'inserted' => $insert];
if (file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) { throw new RuntimeException('Backup failed.'); }
chmod($backupPath, 0600);
$db->execute('START TRANSACTION');
try {
    foreach ($insert as [$attribute, $imageId]) {
        if (!$db->insert('product_attribute_image', ['id_product_attribute' => $attribute, 'id_image' => $imageId])) {
            throw new RuntimeException('Insert failed.');
        }
    }
    $db->execute('COMMIT');
} catch (Throwable $e) { $db->execute('ROLLBACK'); throw $e; }
Tools::clearAllCache();
echo "Variant image links repaired; native detail and cart use the same associations.\n";

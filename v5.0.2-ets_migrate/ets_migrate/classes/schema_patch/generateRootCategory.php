<?php
/**
 * Copyright ETS Software Technology Co., Ltd
 *
 * NOTICE OF LICENSE
 *
 * This file is not open source! Each license that you purchased is only available for 1 website only.
 * If you want to use this file on more websites (or projects), you need to purchase additional licenses.
 * You are not allowed to redistribute, resell, lease, license, sub-license or offer our resources to any third party.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future.
 *
 * @author ETS Software Technology Co., Ltd
 * @copyright  ETS Software Technology Co., Ltd
 * @license    Valid for 1 website (or project) for each purchase of license
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function generateRootCategory()
{
    $ok = Db::getInstance()->execute('
        UPDATE `' . _DB_PREFIX_ . 'category` SET `is_root_category` = 1 WHERE `id_category` = 1;
    ');
    if (!$ok) {
        return false;
    }

    $ok = Db::getInstance()->execute('
        UPDATE `' . _DB_PREFIX_ . 'category` SET `level_depth`=`level_depth`+1
    ');
    if (!$ok) {
        return false;
    }

    $ok = Db::getInstance()->execute('
        INSERT INTO `' . _DB_PREFIX_ . 'category` (`id_parent`, `level_depth`, `active`, `date_add`, `date_upd`, `is_root_category`) VALUES
        (0, 0, 1, NOW(), NOW(), 0)
    ');
    if (!$ok) {
        return false;
    }
    $id = (int) Db::getInstance()->Insert_ID();
    if ($id <= 0) {
        return false;
    }

    $ok = Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'configuration` (`name`, `value`, `date_add`, `date_upd`) VALUES
    (\'PS_ROOT_CATEGORY\', ' . (int) $id . ', NOW(), NOW()),
    (\'PS_HOME_CATEGORY\', 1, NOW(), NOW())
    ');
    if (!$ok) {
        return false;
    }

    return (bool) Db::getInstance()->execute('
        UPDATE `' . _DB_PREFIX_ . 'category`
        SET `id_parent` = ' . (int) $id . '
        WHERE `id_parent` = 0 AND `id_category` <> ' . (int) $id . '
    ');
}

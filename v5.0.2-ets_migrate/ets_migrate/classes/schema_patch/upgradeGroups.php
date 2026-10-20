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

function upgradeGroups()
{
    $id_group = (int) Db::getInstance()->getValue('SELECT id_group FROM `' . _DB_PREFIX_ . 'group` ORDER BY id_group ASC');
    if ($id_group <= 0) {
        $id_group = 1;
    }
    $ok = Db::getInstance()->update('configuration', ['value' => $id_group, 'date_upd' => date('Y-m-d H:i:s')], 'name = "PS_CUSTOMER_GROUP"');
    if (!$ok) {
        return false;
    }

    $languages = Db::getInstance()->executeS('SELECT id_lang, iso_code FROM `' . _DB_PREFIX_ . 'lang`');
    if ($languages === false || !count($languages)) {
        return false;
    }

    if (!addNewGroups('Visiteur', 'Visitor', $languages)) {
        return false;
    }
    if (!addNewGroups('Invité', 'Guest', $languages)) {
        return false;
    }

    return true;
}

function addNewGroups($frenchName, $englishName, $languages)
{
    $ok = Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'group` (`id_group`, `date_add`, `date_upd`) VALUES (NULL, NOW(), NOW())');
    if (!$ok) {
        return false;
    }
    $last_id = (int) Db::getInstance()->Insert_ID();
    if ($last_id <= 0) {
        return false;
    }

    $sql = '';
    foreach ($languages as $lang) {
        if (Tools::strtolower($lang['iso_code']) == 'fr') {
            $sql .= '(' . (int) $last_id . ', ' . (int) $lang['id_lang'] . ', "' . pSQL($frenchName) . '"),';
        } else {
            $sql .= '(' . (int) $last_id . ', ' . (int) $lang['id_lang'] . ', "' . pSQL($englishName) . '"),';
        }
    }
    $sql = Tools::substr($sql, 0, Tools::strlen($sql) - 1);
    $ok = Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'group_lang` (`id_group`, `id_lang`, `name`) VALUES ' . $sql);
    if (!$ok) {
        return false;
    }
    if (Tools::strtolower($englishName) == 'visitor') {
        $ok = Db::getInstance()->update('configuration', ['value' => (int) $last_id, 'date_upd' => date('Y-m-d H:i:s')], 'name = "PS_UNIDENTIFIED_GROUP"');
        if (!$ok) {
            return false;
        }
    } elseif (Tools::strtolower($englishName) == 'guest') {
        $ok = Db::getInstance()->update('configuration', ['value' => (int) $last_id, 'date_upd' => date('Y-m-d H:i:s')], 'name = "PS_GUEST_GROUP"');
        if (!$ok) {
            return false;
        }
    }

    $ok = Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'group_shop` (`id_group`, `id_shop`) (SELECT ' . (int) $last_id . ', `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'PS_SHOP_DEFAULT\')');
    if (!$ok) {
        return false;
    }

    $ok = Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'category_group` (`id_category`, `id_group`) (SELECT `id_category`, ' . (int) $last_id . ' FROM `' . _DB_PREFIX_ . 'category_group` WHERE `id_group` = 1)');
    if (!$ok) {
        return false;
    }

    return true;
}

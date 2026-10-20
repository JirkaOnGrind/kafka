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

function addAttributePosition()
{
    $attributes = Db::getInstance()->executeS('SELECT `id_attribute`, `id_attribute_group` FROM `' . _DB_PREFIX_ . 'attribute`
        ORDER BY `id_attribute_group` ASC, `id_attribute` ASC');
    if ($attributes === false) {
        return false;
    }
    if (!count($attributes)) {
        return true;
    }

    $cases = [];
    $ids = [];
    $position = 0;
    $lastGroupId = null;
    foreach ($attributes as $attribute) {
        $groupId = (int) $attribute['id_attribute_group'];
        if ($lastGroupId !== $groupId) {
            $position = 0;
            $lastGroupId = $groupId;
        }
        $idAttribute = (int) $attribute['id_attribute'];
        $cases[] = 'WHEN ' . $idAttribute . ' THEN ' . (int) $position;
        $ids[] = $idAttribute;
        ++$position;
    }

    return (bool) Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'attribute`
        SET `position` = CASE `id_attribute` ' . implode(' ', $cases) . ' END
        WHERE `id_attribute` IN (' . implode(',', $ids) . ')');
}

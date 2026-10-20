{*
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
*}

<h4 class="ets_em_title_block">{l s='Source store information' mod='ets_migrate'}</h4>
<div class="form-group-content">
	<p><span>{l s='Source store URL' mod='ets_migrate'}</span>: <span class="ETS_EM_DOMAIN"><b>{$configs_value.ETS_EM_DOMAIN|escape:'html':'UTF-8'}</b></span></p>
	<p><span>{l s='Platform' mod='ets_migrate'}</span>: <b>PrestaShop ({$configs_value.ETS_EM_MIGRATE_VERSION|escape:'html':'UTF-8'})</b></p>
</div>
<h4 class="ets_em_title_block">{l s='Data entities to migrate' mod='ets_migrate'}</h4>
{if isset($configs_value.ETS_EM_DATA_TO_MIGRATE) && is_array($configs_value.ETS_EM_DATA_TO_MIGRATE) && $configs_value.ETS_EM_DATA_TO_MIGRATE|count > 0 && isset($resources) && is_array($resources) && $resources|count > 0}
	<div class="form-group-content">
        <ul class="ets_em_data_to_migrate">
    	{foreach from=$resources key='data' item='resource'}
    		{if $resource.position|intval > 0 && in_array($data, $configs_value.ETS_EM_DATA_TO_MIGRATE) && isset($info.nb.$data.nb) && $info.nb.$data.nb|intval > 0}
    			<li class="ets_em_data_group {$data|escape:'html':'UTF-8'}">
    				<svg class="selected_icon w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1412 734q0-28-18-46l-91-90q-19-19-45-19t-45 19l-408 407-226-226q-19-19-45-19t-45 19l-91 90q-18 18-18 46 0 27 18 45l362 362q19 19 45 19 27 0 46-19l543-543q18-18 18-45zm252 162q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
                    <span class="group_name">{$resource.name|cleanHtml nofilter}</span>&nbsp;<span class="group_value">{$info.nb.$data.nb|intval}&nbsp;{if $info.nb.$data.nb|intval == 1}{l s='item' mod='ets_migrate'}{else}{l s='items' mod='ets_migrate'}{/if}</span>
    			</li>
    		{/if}
    	{/foreach}
    	</ul>
    </div>
{/if}
<h4 class="ets_em_title_block">{l s='Migration options' mod='ets_migrate'}</h4>
<div class="form-group-content ets_em_migrate_option">
	{if isset($ETS_EM_SUPPLIER_DEFAULT) && $ETS_EM_SUPPLIER_DEFAULT|trim !== ''}
	<p data-id="ETS_EM_SUPPLIER_DEFAULT">
		<svg class="selected_icon w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1412 734q0-28-18-46l-91-90q-19-19-45-19t-45 19l-408 407-226-226q-19-19-45-19t-45 19l-91 90q-18 18-18 46 0 27 18 45l362 362q19 19 45 19 27 0 46-19l543-543q18-18 18-45zm252 162q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
		<span class="group_name ets_em_supplier_default">{l s='Default supplier' mod='ets_migrate'}</span>:
		<span class="ETS_EM_SUPPLIER_DEFAULT"><b>{$ETS_EM_SUPPLIER_DEFAULT|escape:'html':'UTF-8'}</b></span>
	</p>
	{/if}
    {if isset($ETS_EM_MANUFACTURER_DEFAULT) && $ETS_EM_MANUFACTURER_DEFAULT|trim !== ''}
		<p data-id="ETS_EM_MANUFACTURER_DEFAULT">
			<svg class="selected_icon w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1412 734q0-28-18-46l-91-90q-19-19-45-19t-45 19l-408 407-226-226q-19-19-45-19t-45 19l-91 90q-18 18-18 46 0 27 18 45l362 362q19 19 45 19 27 0 46-19l543-543q18-18 18-45zm252 162q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
			<span class="group_name ets_em_manufacturer_default">{l s='Default manufacturer' mod='ets_migrate'}</span>:
			<span class="ETS_EM_MANUFACTURER_DEFAULT"><b>{$ETS_EM_MANUFACTURER_DEFAULT|escape:'html':'UTF-8'}</b></span>
		</p>
    {/if}
{if isset($configs) && is_array($configs) && $configs|count > 0 && isset($configs_value) && is_array($configs_value) && $configs_value|count > 0}
	{foreach from=$configs key='id' item='field'}
		{if isset($field.group) && $field.group|trim === 'option'&& $id|trim != 'ETS_EM_DATA_TO_MIGRATE'}
			<p data-id="{$id|escape:'html':'UTF-8'}">
                <svg class="selected_icon w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1412 734q0-28-18-46l-91-90q-19-19-45-19t-45 19l-408 407-226-226q-19-19-45-19t-45 19l-91 90q-18 18-18 46 0 27 18 45l362 362q19 19 45 19 27 0 46-19l543-543q18-18 18-45zm252 162q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
                <span class="group_name {$field.name|lower|escape:'html':'UTF-8'}">{$field.label|escape:'html':'UTF-8'}</span>:
				{if $field.type|trim == 'switch' || $field.type|trim == 'radio' && isset($field.is_bool) && $field.is_bool}
					<span class="{$id|escape:'html':'UTF-8'}"><b>{if $configs_value.$id|intval > 0}{l s='Yes' mod='ets_migrate'}{else}{l s='No' mod='ets_migrate'}{/if}</b></span>
				{else}
					<span class="{$id|escape:'html':'UTF-8'}"><b>{$configs_value.$id|ucfirst|escape:'html':'UTF-8'}</b></span>
				{/if}
            </p>
		{/if}
	{/foreach}
{/if}
<div class="ets_clearfix"></div>
</div>
<input type="hidden" name="migrate_option" value="1">

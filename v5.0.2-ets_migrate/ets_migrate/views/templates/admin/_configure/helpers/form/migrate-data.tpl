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
{if isset($resources) && $resources}
	{if $input_checkbox}
	<div class="ets_em_select_all">
		<label class="ets_custom_checkbox" for="{$input.name|escape:'html':'UTF-8'}_ALL">
			<input name="{$input.name|escape:'html':'UTF-8'}_ALL" value="" type="checkbox" id="{$input.name|escape:'html':'UTF-8'}_ALL" checked="checked" /><div class="ets_checkbox"></div>
            {l s='Select/unselect all' mod='ets_migrate'}
		</label>
	</div>
	{/if}
	<div class="ets_em_export_import_form_content {if isset($class) && $class}{$class|escape:'html':'UTF-8'}{/if}">
        {foreach from=$resources key='id_resource' item='resource'}
            {if $id_resource !== 'minor_data' && $id_resource !== 'images' && $id_resource !== 'files' && $id_resource !== 'finished'}
				<div class="data_to_migrate {$id_resource|escape:'html':'UTF-8'} checkbox{if isset($resource.images) && is_array($resource.images) && $resource.images|count > 0} group-images{/if}{if isset($resource.files) && is_array($resource.files) && $resource.files|count > 0} group-files{/if}" data-import="{$id_resource|escape:'html':'UTF-8'}">
					{if $type|trim === 'column'}
					<div class="items_left col-xs-6 col-sm-6">
						<div class="items_left_label">
							{/if}
							<label class="ets_custom_checkbox" for="{$input.name|escape:'html':'UTF-8'}_{$id_resource|escape:'html':'UTF-8'}">
		                        {if $input_checkbox}
			                        <input name="{$input.name|escape:'html':'UTF-8'}[]" value="{$id_resource|escape:'html':'UTF-8'}" type="checkbox" id="{$input.name|escape:'html':'UTF-8'}_{$id_resource|escape:'html':'UTF-8'}" /><div class="ets_checkbox"></div>
		                        {/if}
		                        {$resource.name|cleanHtml nofilter}
							</label>
							{if $type|trim === 'column'}
						</div>
					</div>
					<div class="items_right col-xs-6 col-sm-6"><span class="nb_{$id_resource|escape:'html':'UTF-8'}"></span></div>
					{/if}
				</div>
            {elseif $id_resource === 'minor_data' && $input_checkbox}
				<input name="{$input.name|escape:'html':'UTF-8'}[]" value="{$id_resource|escape:'html':'UTF-8'}" type="hidden" id="{$input.name|escape:'html':'UTF-8'}_{$id_resource|escape:'html':'UTF-8'}" checked="checked" />
            {/if}
        {/foreach}
	</div>
{/if}

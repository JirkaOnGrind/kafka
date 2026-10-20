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

<div class="row">
    {if isset($configs_value.ETS_EM_DATA_TO_MIGRATE) && is_array($configs_value.ETS_EM_DATA_TO_MIGRATE) && $configs_value.ETS_EM_DATA_TO_MIGRATE|count > 0 && isset($resources) && is_array($resources) && $resources|count > 0}
	    {assign var='migrate_image' value=0}
        {foreach from=$configs_value.ETS_EM_DATA_TO_MIGRATE item='data'}
            {if isset($resources.$data) && $resources.$data && ($data|trim == 'minor_data' || $data|trim == 'images' || $data|trim == 'files' || isset($info.nb.$data.nb) && $info.nb.$data.nb|intval > 0)}
                {assign var="resource" value=$resources.$data}
	            {assign var="complete_task" value=in_array($data, $migrated_tables)}
                {if $migrating|trim !== $data|trim || !isset($info.nb.$data.nb_group_table) || $info.nb.$data.nb_group_table <= 0}{assign var="percent_task" value=0}{else}{assign var="percent_task" value={math equation="((x*y)/z)" x=$count y=100 z=$info.nb.$data.nb_group_table format="%.2f"}}{/if}
	            <div class="ets_em_process_item {$data|escape:'html':'UTF-8'} col-sm-4 col-xs-12" data-task="{$data|escape:'html':'UTF-8'}">
		            <div class="ets_em_process_item_content {$data|escape:'html':'UTF-8'} {if $complete_task}item_success{elseif $migrating|trim === $data|trim}item_processing{else}item_waiting{/if}">
			            <div class="ets_em_process_item_header">
				            <span class="ets_em_process_item_title">{$resource.name|cleanHtml nofilter} <span class="ets_em_process_item_icon {$data|escape:'html':'UTF-8'}"><span class="{if $complete_task}icon_success{elseif $migrating|trim === $data|trim}dot-flashing{/if}"></span></span></span>
				            <span class="ets_em_process_item_img"><img src="{$img_path|escape:'html':'UTF-8'}process/{$data|escape:'html':'UTF-8'}.png"></span>
			            </div>
			            <div class="ets_em_process_item_run">
				            <div class="ets_em_process_item_range">
					            <span class="ets_em_process_item_running {$data|escape:'html':'UTF-8'}" style="width:{if $complete_task}100%{else}{$percent_task|floatval}%{/if};"></span>
				            </div>
				            <span class="ets_em_process_item_percent {$data|escape:'html':'UTF-8'}">{if $complete_task}100%{else}{$percent_task|floatval}%{/if}</span>
				            {if $data !== 'minor_data'}<span class="ets_em_process_item_files {$data|escape:'html':'UTF-8'}">{if $data|trim !== 'images' && $data|trim !== 'files'}{$info.nb.$data.nb|intval}{/if}</span>{/if}
			            </div>
		            </div>
	            </div>
            {/if}
        {/foreach}
    {/if}
</div>

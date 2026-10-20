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

{extends file="helpers/form/form.tpl"}
{block name="legend"}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&family=Roboto:wght@400;500;600&display=swap" rel="stylesheet" />
    {$smarty.block.parent}
    <div class="form ets_header_module">
        <div class="logo_left">
            <img src="{$img_path|escape:'html':'UTF-8'}origin/lg.png" alt="" title="" />
            <div class="logo_left_text">
                <span class="ets-em-module-name">{l s='MigrationPro Ultimate' mod='ets_migrate'}</span>
                <span class="ets-em-module-slogan">{l s='Faster & more accurate!' mod='ets_migrate'}</span>
            </div>
        </div>
        <ul class="ets_header_link_action">
            <li class="ets-em-menu">
                <span>
                    <i class="ets_svg_icon ets_svg_fill_violet">
                        <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 2048 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1344 928q0-14-9-23t-23-9h-224v-352q0-13-9.5-22.5t-22.5-9.5h-192q-13 0-22.5 9.5t-9.5 22.5v352h-224q-13 0-22.5 9.5t-9.5 22.5q0 14 9 23l352 352q9 9 23 9t23-9l351-351q10-12 10-24zm640 224q0 159-112.5 271.5t-271.5 112.5h-1088q-185 0-316.5-131.5t-131.5-316.5q0-130 70-240t188-165q-2-30-2-43 0-212 150-362t362-150q156 0 285.5 87t188.5 231q71-62 166-62 106 0 181 75t75 181q0 76-41 138 130 31 213.5 135.5t83.5 238.5z"/></svg>
                    </i> {l s='Downloads' mod='ets_migrate'}
                </span>
                <ul class="ets-em-menu-child">
                    <li class="ets-em-menu-child-item">
                        <a href="{$download_connector_link|escape:'html':'UTF-8'}" rel="noopener noreferrer" target="_blank">
                            <i class="img_bg_sub connector"></i> {l s='PrestaShop Connector module' mod='ets_migrate'}
                        </a>
                    </li>
                    <li class="ets-em-menu-child-item">
                        <a href="{$download_password_keeper_link|escape:'html':'UTF-8'}" rel="noopener noreferrer" target="_blank">
                            <i class="img_bg_sub keeper"></i> {l s='PrestaShop Password Keeper module' mod='ets_migrate'}
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="{$download_documentation_link|escape:'html':'UTF-8'}" rel="noopener noreferrer" target="_blank">
                    <i class="ets_svg_icon ets_svg_fill_violet">
                        <svg class="w_16 h_16" width="16" height="16" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1596 380q28 28 48 76t20 88v1152q0 40-28 68t-68 28h-1344q-40 0-68-28t-28-68v-1600q0-40 28-68t68-28h896q40 0 88 20t76 48zm-444-244v376h376q-10-29-22-41l-313-313q-12-12-41-22zm384 1528v-1024h-416q-40 0-68-28t-28-68v-416h-768v1536h1280zm-514-593q33 26 84 56 59-7 117-7 147 0 177 49 16 22 2 52 0 1-1 2l-2 2v1q-6 38-71 38-48 0-115-20t-130-53q-221 24-392 83-153 262-242 262-15 0-28-7l-24-12q-1-1-6-5-10-10-6-36 9-40 56-91.5t132-96.5q14-9 23 6 2 2 2 4 52-85 107-197 68-136 104-262-24-82-30.5-159.5t6.5-127.5q11-40 42-40h22q23 0 35 15 18 21 9 68-2 6-4 8 1 3 1 8v30q-2 123-14 192 55 164 146 238zm-576 411q52-24 137-158-51 40-87.5 84t-49.5 74zm398-920q-15 42-2 132 1-7 7-44 0-3 7-43 1-4 4-8-1-1-1-2-1-2-1-3-1-22-13-36 0 1-1 2v2zm-124 661q135-54 284-81-2-1-13-9.5t-16-13.5q-76-67-127-176-27 86-83 197-30 56-45 83zm646-16q-24-24-140-24 76 28 124 28 14 0 18-1 0-1-2-3z"/></svg>
                    </i> {l s='Documentation' mod='ets_migrate'}
                </a>
            </li>
            <li>
                <a href="{$youtube_link|escape:'html':'UTF-8'}" rel="noopener noreferrer" target="_blank">
                    <i class="ets_svg_icon ets_svg_fill_violet">
                        <svg class="w_16 h_16" width="16" height="16" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1792 352v1088q0 42-39 59-13 5-25 5-27 0-45-19l-403-403v166q0 119-84.5 203.5t-203.5 84.5h-704q-119 0-203.5-84.5t-84.5-203.5v-704q0-119 84.5-203.5t203.5-84.5h704q119 0 203.5 84.5t84.5 203.5v165l403-402q18-19 45-19 12 0 25 5 39 17 39 59z"/></svg>
                    </i> {l s='Tutorial video' mod='ets_migrate'}
                </a>
            </li>
        </ul>
    </div>
    {if isset($steps) && $steps|count > 0}
        <ul class="ets-em-nav-steps">
            {assign var="ik" value=0}
            {foreach from=$steps item='name'}
                {assign var="ik" value=$ik+1}
                <li class="ets-em-nav-step step{$ik|escape:'html':'UTF-8'}{if $current_step|intval === $ik|intval} active{/if}" data-step="{$ik|escape:'html':'UTF-8'}">{$name|escape:'html':'UTF-8'}</li>
            {/foreach}
        </ul>
        <input id="current_step" name="current_step" value="{$current_step|intval}" type="hidden" />
    {/if}
    <div class="form_header_block h_step1 step1" data-step="1">
        <div class="header_img text-center">
            <img src="{$img_path|escape:'html':'UTF-8'}origin/2.png"/>
        </div>
        <h2 class="text-center">{l s='Source connection' mod='ets_migrate'}</h2>
        <p class="title_sub">
            {l s='Download and install the PrestaShop Connector module on the source store, then enter the Source store URL and Access token below to establish the connection.' mod='ets_migrate'}
        </p>
        <h4>
        <a href="{$download_connector_link|escape:'html':'UTF-8'}" target="_blank">
            <svg class="w_14 h_14" width="14" height="14" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1344 1344q0-26-19-45t-45-19-45 19-19 45 19 45 45 19 45-19 19-45zm256 0q0-26-19-45t-45-19-45 19-19 45 19 45 45 19 45-19 19-45zm128-224v320q0 40-28 68t-68 28h-1472q-40 0-68-28t-28-68v-320q0-40 28-68t68-28h465l135 136q58 56 136 56t136-56l136-136h464q40 0 68 28t28 68zm-325-569q17 41-14 70l-448 448q-18 19-45 19t-45-19l-448-448q-31-29-14-70 17-39 59-39h256v-448q0-26 19-45t45-19h256q26 0 45 19t19 45v448h256q42 0 59 39z"/></svg>
             {l s='Download PrestaShop Connector module' mod='ets_migrate'}
        </a>
        </h4>
    </div>
    <div class="form_header_block h_step2 step2" data-step="2">
        <div class="header_img text-center">
            <img src="{$img_path|escape:'html':'UTF-8'}origin/3.png"/>
        </div>
        <h2 class="text-center">{l s='Migration options' mod='ets_migrate'}</h2>
        <p class="title_sub">
            {l s='Selected data entities indicated below will be migrated. By default, all data entities are selected.' mod='ets_migrate'}<br/>
            {l s='Click ' mod='ets_migrate'}<b>{l s='Advanced settings' mod='ets_migrate'} </b>{l s='for more migration options.' mod='ets_migrate'}
        </p>
    </div>
{/block}

{block name="input"}
    {if $input.type=='range'}
        <div class="ets_range_input">
            <div class="range_title">
                <span class="low">{l s='Low' mod='ets_migrate'}</span>
                <span class="medium">{l s='Medium' mod='ets_migrate'}</span>
                <span class="high">{l s='High' mod='ets_migrate'}</span>
            </div>
            <span id="{$input.name|escape:'html':'UTF-8'}_value">{if isset($fields_value[$input.name]) && $fields_value[$input.name]|intval}{$fields_value[$input.name]|intval}{else}200{/if}</span>
            <input type="range" id="{$input.name|escape:'html':'UTF-8'}" name="{$input.name|escape:'html':'UTF-8'}" min="{$input.min|intval}" max="{$input.max|intval}" step="100" value="{if isset($fields_value[$input.name]) && $fields_value[$input.name]|intval}{$fields_value[$input.name]|intval}{else}200{/if}">
            <div class="ets_range_input_slide">
                <span class="ets_range_input_val"></span>
            </div>
        </div>
    {elseif $input.type=='radio'}
        {foreach $input.values as $value}
			<div class="radio {if isset($input.class)}{$input.class|escape:'html':'UTF-8'}{/if}">
				{strip}
				<label class="ets_custom_radio">
                    <span class="ets_custom_radio_content">
                        <input type="radio"	name="{$input.name|escape:'html':'UTF-8'}" id="{$value.id|escape:'html':'UTF-8'}" value="{$value.value|escape:'html':'UTF-8'}"{if $fields_value[$input.name] == $value.value} checked="checked"{/if}{if (isset($input.disabled) && $input.disabled) or (isset($value.disabled) && $value.disabled)} disabled="disabled"{/if}/>
                        <span class="ets_checkbox"></span>
                    </span>
                    {$value.label|escape:'html':'UTF-8'}
				</label>
				{/strip}
			</div>
			{if isset($value.p) && $value.p}<p class="help-block">{$value.p|escape:'html':'UTF-8'}</p>{/if}
		{/foreach}
    {else}
        {if $input.name|trim === 'ETS_EM_DOMAIN'}
            <div class="ETS_EM_DOMAIN">
                {$smarty.block.parent}
                <span class="ets_em_tooltip ets_em_domain">
                    <i class="ets_svg_icon svg_fill_blue svg_fill_hover_gray">
                        <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1024 1376v-192q0-14-9-23t-23-9h-192q-14 0-23 9t-9 23v192q0 14 9 23t23 9h192q14 0 23-9t9-23zm256-672q0-88-55.5-163t-138.5-116-170-41q-243 0-371 213-15 24 8 42l132 100q7 6 19 6 16 0 25-12 53-68 86-92 34-24 86-24 48 0 85.5 26t37.5 59q0 38-20 61t-68 45q-63 28-115.5 86.5t-52.5 125.5v36q0 14 9 23t23 9h192q14 0 23-9t9-23q0-19 21.5-49.5t54.5-49.5q32-18 49-28.5t46-35 44.5-48 28-60.5 12.5-81zm384 192q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
                    </i> <span class="ets_tooltip top">{l s='Enter source store URL (home page URL of the source store) including http:// or https://' mod='ets_migrate'}</span>
                </span>
            </div>
        {elseif $input.name|trim === 'ETS_EM_ACCESS_TOKEN'}
            <div class="ETS_EM_ACCESS_TOKEN">
                {$smarty.block.parent}
                <span class="ets_em_tooltip ets_em_domain">
                    <i class="ets_svg_icon svg_fill_blue svg_fill_hover_gray">
                        <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1024 1376v-192q0-14-9-23t-23-9h-192q-14 0-23 9t-9 23v192q0 14 9 23t23 9h192q14 0 23-9t9-23zm256-672q0-88-55.5-163t-138.5-116-170-41q-243 0-371 213-15 24 8 42l132 100q7 6 19 6 16 0 25-12 53-68 86-92 34-24 86-24 48 0 85.5 26t37.5 59q0 38-20 61t-68 45q-63 28-115.5 86.5t-52.5 125.5v36q0 14 9 23t23 9h192q14 0 23-9t9-23q0-19 21.5-49.5t54.5-49.5q32-18 49-28.5t46-35 44.5-48 28-60.5 12.5-81zm384 192q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
                    </i> <span class="ets_tooltip top">{l s='Copy the access token from the PrestaShop Connector module installed on the source store.' mod='ets_migrate'}</span>
                </span>
            </div>
        {else}
            {$smarty.block.parent}
        {/if}
    {/if}
{/block}

{block name="input_row"}
    {if isset($steps) && $steps}
        {if !isset($ik2)}{assign var="ik2" value=0}{else}{assign var="ik2" value=$ik2+1}{/if}
        {if !isset($form_group_step)}
            {assign var="form_group_step" value=$input.step}
            <div class="form-wrapper-group-step step{$input.step|escape:'html':'UTF-8'}{if $current_step|intval === $input.step|intval} active{/if}" data-step="{$input.step|escape:'html':'UTF-8'}">
        {/if}
        {if $form_group_step == $input.step}
            {if isset($input.group_title) && $input.group_title|trim !== '' && $input.name|trim !== 'ETS_EM_DATA_TO_MIGRATE'}
                <h4 class="ets_em_title_block ets_em_option_group_title">{$input.group_title|escape:'html':'UTF-8'}</h4>
            {/if}
            {$smarty.block.parent}
            {if $input.name == 'ETS_EM_MIGRATE_VERSION'}
                {include file="./form-footer.tpl" show_btn_cancel=true}
            {/if}
            {if $input.name == 'ETS_EM_MANUFACTURER_DEFAULT'}
                </div>
                <div class="ets_em_form_group migrate_review">
                    {include file="./migrate-review.tpl"}
                </div>
                <div class="ets_em_form_group migrate_resume">
                    {include file="./migrate-resume.tpl"}
                </div>
            {/if}
            {if $input.name == 'ETS_EM_MIGRATE_VERSION'}
                        </div>
                    </div>
                </div>
            </div>
            {/if}
        {elseif $form_group_step != $input.step}
            {assign var="form_group_step" value=$input.step}
            </div>
            <div class="form-wrapper-group-step step{$input.step|escape:'html':'UTF-8'}{if $current_step|intval === $input.step|intval} active{/if}" data-step="{$input.step|escape:'html':'UTF-8'}">
            {if !empty($input.group_title)}
                <h4 class="ets_em_title_block ets_em_option_group_title">{$input.group_title|escape:'html':'UTF-8'}</h4>
            {/if}
            {if $input.name == 'ETS_EM_DATA_TO_MIGRATE'}
                <div class="form-group data-to-migrate">
                    <div class="ets_em_import_info">
                        <div class="ets_em_wrap">
                            <div class="title_form col-sm-6 col-xs-6">{l s='Data entities' mod='ets_migrate'}</div>
                            <div class="title_form title_form_items col-sm-6 col-xs-6">{l s='Item count' mod='ets_migrate'}</div>
                            {include file="./migrate-data.tpl" class='info_data_source' type='column' input_checkbox=0}
                        </div>
                        <div class="ets_em_bottom viewmore_form">
                            <span class="viewmore_button more active">{l s='View more' mod='ets_migrate'}</span>
                            <span class="viewmore_button less">{l s='View less' mod='ets_migrate'}</span>
                        </div>
                    </div>
                </div>
                <div class="form-group wrap-migrate">
                    <div class="table">
                        <div class="table-cell">
                            <div class="ets_em_form">
                                <div class="ets_em_form_group advanced_settings">
                                    <div class="ets_em_panel_header">
                                        <span class="ets_em_close_popup">{l s='Close' mod='ets_migrate'}</span>
                                        <div class="ets_em_header_block">
                                            <svg class="w_30 h_30" width="30" height="30" viewBox="0 0 2048 1792" xmlns="http://www.w3.org/2000/svg"><path d="M960 896q0-106-75-181t-181-75-181 75-75 181 75 181 181 75 181-75 75-181zm768 512q0-52-38-90t-90-38-90 38-38 90q0 53 37.5 90.5t90.5 37.5 90.5-37.5 37.5-90.5zm0-1024q0-52-38-90t-90-38-90 38-38 90q0 53 37.5 90.5t90.5 37.5 90.5-37.5 37.5-90.5zm-384 421v185q0 10-7 19.5t-16 10.5l-155 24q-11 35-32 76 34 48 90 115 7 11 7 20 0 12-7 19-23 30-82.5 89.5t-78.5 59.5q-11 0-21-7l-115-90q-37 19-77 31-11 108-23 155-7 24-30 24h-186q-11 0-20-7.5t-10-17.5l-23-153q-34-10-75-31l-118 89q-7 7-20 7-11 0-21-8-144-133-144-160 0-9 7-19 10-14 41-53t47-61q-23-44-35-82l-152-24q-10-1-17-9.5t-7-19.5v-185q0-10 7-19.5t16-10.5l155-24q11-35 32-76-34-48-90-115-7-11-7-20 0-12 7-20 22-30 82-89t79-59q11 0 21 7l115 90q34-18 77-32 11-108 23-154 7-24 30-24h186q11 0 20 7.5t10 17.5l23 153q34 10 75 31l118-89q8-7 20-7 11 0 21 8 144 133 144 160 0 8-7 19-12 16-42 54t-45 60q23 48 34 82l152 23q10 2 17 10.5t7 19.5zm640 533v140q0 16-149 31-12 27-30 52 51 113 51 138 0 4-4 7-122 71-124 71-8 0-46-47t-52-68q-20 2-30 2t-30-2q-14 21-52 68t-46 47q-2 0-124-71-4-3-4-7 0-25 51-138-18-25-30-52-149-15-149-31v-140q0-16 149-31 13-29 30-52-51-113-51-138 0-4 4-7 4-2 35-20t59-34 30-16q8 0 46 46.5t52 67.5q20-2 30-2t30 2q51-71 92-112l6-2q4 0 124 70 4 3 4 7 0 25-51 138 17 23 30 52 149 15 149 31zm0-1024v140q0 16-149 31-12 27-30 52 51 113 51 138 0 4-4 7-122 71-124 71-8 0-46-47t-52-68q-20 2-30 2t-30-2q-14 21-52 68t-46 47q-2 0-124-71-4-3-4-7 0-25 51-138-18-25-30-52-149-15-149-31v-140q0-16 149-31 13-29 30-52-51-113-51-138 0-4 4-7 4-2 35-20t59-34 30-16q8 0 46 46.5t52 67.5q20-2 30-2t30 2q51-71 92-112l6-2q4 0 124 70 4 3 4 7 0 25-51 138 17 23 30 52 149 15 149 31z"/></svg>
                                            <h3 class="ets_em_title_block">{l s='Advanced settings' mod='ets_migrate'}</h3>
                                        </div>
                                    </div>
                                    <div class="form-group data-to-migrate advanced_settings">
                                        <h4 class="ets_em_title_block">{l s='Data entities to migrate' mod='ets_migrate'}</h4>
                                        <div class="ets_em_export_import_form">
                                            {include file="./migrate-data.tpl" class='select_data_import' type='list' input_checkbox=1}
                                        </div>
                                    </div>
                                    <div class="form-group ets-em-shop-mapping advanced_settings"{if (isset($current_step) && $current_step == 1) || (!empty($source_shops) && count($source_shops) <= 1 && !empty($target_shops) && count($target_shops) <= 1)} style="display:none;"{/if}>
                                        <h4 class="ets_em_title_block">{l s='Shop mapping' mod='ets_migrate'}</h4>
                                        <input id="ETS_EM_SHOPS_MAPPING" type="hidden" name="ETS_EM_SHOPS_MAPPING" value="{if isset($shop_mapping_ids)}{$shop_mapping_ids|escape:'html':'UTF-8'}{/if}"/>
                                        <div class="ets-em-shop-mapping-list">
                                            {if !empty($shop_mapping_rows)}
                                                {foreach from=$shop_mapping_rows item=mapping_row}
                                                    <div class="shop-mapping-item form-group">
                                                        <select class="col-lg-4" id="id_shop_source_{$mapping_row.id_shop_source|intval}" name="id_shop_source_{$mapping_row.id_shop_source|intval}">
                                                            <option value="{$mapping_row.id_shop_source|intval}">{$mapping_row.name_source|escape:'html':'UTF-8'}</option>
                                                        </select>
                                                        <span class="ets_svg_icon">
                                                            <svg class="w_14 h_14" width="14" height="14" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M1600 960q0 54-37 91l-651 651q-39 37-91 37-51 0-90-37l-75-75q-38-38-38-91t38-91l293-293h-704q-52 0-84.5-37.5t-32.5-90.5v-128q0-53 32.5-90.5t84.5-37.5h704l-293-294q-38-36-38-90t38-90l75-75q-38-38 90-38 53 0 91 38l651 651q37 35 37 90z"/>
                                                            </svg>
                                                        </span>
                                                        <select class="col-lg-4" id="id_shop_target_{$mapping_row.id_shop_source|intval}" name="id_shop_target_{$mapping_row.id_shop_source|intval}">
                                                            {if count($source_shops) > 1}
                                                                <option value="-1"{if $mapping_row.id_shop_target === -1} selected="selected"{/if}>{l s='Do not import' mod='ets_migrate'}</option>
                                                            {/if}
                                                            {if !empty($target_shops)}
                                                                {foreach from=$target_shops item=target_shop}
                                                                    <option value="{$target_shop.id_shop|intval}"{if $mapping_row.id_shop_target == $target_shop.id_shop} selected="selected"{/if}>{$target_shop.name|escape:'html':'UTF-8'}</option>
                                                                {/foreach}
                                                            {/if}
                                                            <option value="0"{if $mapping_row.id_shop_target === 0} selected="selected"{/if}>{l s='Create new shop' mod='ets_migrate'}</option>
                                                        </select>
                                                    </div>
                                                {/foreach}
                                            {/if}
                                        </div>
                                    </div>
            {elseif $input.name == 'ETS_EM_PROCESS_MIGRATION'}
                <div class="form-group process-migration">
                    {include file="./migrate-process.tpl"}
                </div>
            {elseif $input.name == 'ETS_EM_MIGRATION_DONE'}
                <div class="form-group migration-done">
                    {include file="./migrate-complete.tpl"}
                </div>
            {else}{$smarty.block.parent}{/if}
        {/if}
        {if $input.name == 'ETS_EM_MIGRATION_DONE'}
            </div>
        {/if}
    {else}{$smarty.block.parent}{/if}
{/block}

{block name="footer"}
    {if isset($partial) && $partial|trim !== ''}
        <div class="form-footer">
            <input type="hidden" value="{$partial|escape:'html':'UTF-8'}" name="partial">
            <button name="module_form_cancel_btn" type="button" class="btn btn-default ets_em_popup_cancel no_svg_icon">{l s='Close' mod='ets_migrate'}</button>
            <button type="submit" value="1" class="ets_em_form_submit_btn no_svg_icon ets_em_setting" name="submitConfig">{l s='Save' mod='ets_migrate'}</button>
        </div>
    {else}
<div class="panel-form-footer step1" data-step="1">
            <button type="submit" value="1" class="ets_em_form_submit_btn no_svg_icon" name="submitConfig">
                {l s='Connect' mod='ets_migrate'}
            </button>
        </div>
<div class="panel-form-footer step2" data-step="2">
            <button type="submit" value="1" class="ets_em_form_submit_btn no_svg_icon" name="submitConfig">
                {l s='Migrate' mod='ets_migrate'}
            </button><br/>
            <button type="button" id="ets_em_advanced_popup" class="ets_em_advanced_settings">
                <i class="ets_svg_icon ets_em_svg ets_svg_fill_violet" aria-hidden="true">
                    <svg class="w_20 h_20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                        <path d="M512.1 191l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7 9.5-7.9 20.4-14.2 32.1-18.6 5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0L552 6.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c6.9 8 12.3 17.3 15.9 27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zm-10.5-58.8c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.7-82.4 14.3-52.8 52.8zM386.3 286.1l33.7 16.8c10.1 5.8 14.5 18.1 10.5 29.1-8.9 24.2-26.4 46.4-42.6 65.8-7.4 8.9-20.2 11.1-30.3 5.3l-29.1-16.8c-16 13.7-34.6 24.6-54.9 31.7v33.6c0 11.6-8.3 21.6-19.7 23.6-24.6 4.2-50.4 4.4-75.9 0-11.5-2-20-11.9-20-23.6V418c-20.3-7.2-38.9-18-54.9-31.7L74 403c-10 5.8-22.9 3.6-30.3-5.3-16.2-19.4-33.3-41.6-42.2-65.7-4-10.9.4-23.2 10.5-29.1l33.3-16.8c-3.9-20.9-3.9-42.4 0-63.4L12 205.8c-10.1-5.8-14.6-18.1-10.5-29 8.9-24.2 26-46.4 42.2-65.8 7.4-8.9 20.2-11.1 30.3-5.3l29.1 16.8c16-13.7 34.6-24.6 54.9-31.7V57.1c0-11.5 8.2-21.5 19.6-23.5 24.6-4.2 50.5-4.4 76-.1 11.5 2 20 11.9 20 23.6v33.6c20.3 7.2 38.9 18 54.9 31.7l29.1-16.8c10-5.8 22.9-3.6 30.3 5.3 16.2 19.4 33.2 41.6 42.1 65.8 4 10.9.1 23.2-10 29.1l-33.7 16.8c3.9 21 3.9 42.5 0 63.5zm-117.6 21.1c59.2-77-28.7-164.9-105.7-105.7-59.2 77 28.7 164.9 105.7 105.7zm243.4 182.7l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7 9.5-7.9 20.4-14.2 32.1-18.6 5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0l8.2-14.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c6.9 8 12.3 17.3 15.9 27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zM501.6 431c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.6-82.4 14.3-52.8 52.8z"/></svg>
                </i> {l s='Advanced settings' mod='ets_migrate'}</button>
            <button type="submit" class="back_step1">
                <i class="ets_svg_icon ets_svg_fill_gray">
                    <svg class="w_14 h_14" width="14" height="14" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1792 800v192q0 14-9 23t-23 9h-1248v224q0 21-19 29t-35-5l-384-350q-10-10-10-23 0-14 10-24l384-354q16-14 35-6 19 9 19 29v224h1248q14 0 23 9t9 23z"/></svg>
                </i> {l s='Back' mod='ets_migrate'}
            </button>
        </div>
    {/if}
    {$smarty.block.parent}
{/block}
{block name="after"}
    {if isset($infos) && is_array($infos) && count($infos) > 0}
        <script type="text/javascript">
            ETS_EM_DATA_INFO_SOURCE = JSON.parse('{$infos|json_encode|escape:'javascript':'UTF-8'}');
        </script>
    {/if}
    {if !isset($partial) || $partial|trim == ''}
        <div class="ets_copyright nocopy">
            {l s='Made with' mod='ets_migrate'} <svg class="ets_copyright_love w_14 h_14" width="14" height="14" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M896 1664q-26 0-44-18l-624-602q-10-8-27.5-26t-55.5-65.5-68-97.5-53.5-121-23.5-138q0-220 127-344t351-124q62 0 126.5 21.5t120 58 95.5 68.5 76 68q36-36 76-68t95.5-68.5 120-58 126.5-21.5q224 0 351 124t127 344q0 221-229 450l-623 600q-18 18-44 18z"/></svg> {l s='by PrestaHero. All rights reserved.'  mod='ets_migrate'}
        </div>
        <div class="ets_em_overlay">
            <div class="table">
                <div class="table-cell">
                    <div class="ets_em_form">
                        <div class="ets_em_form_header">
                            <svg class="w_30 h_30" width="30" height="30" viewBox="0 0 2048 1792" xmlns="http://www.w3.org/2000/svg"><path d="M960 896q0-106-75-181t-181-75-181 75-75 181 75 181 181 75 181-75 75-181zm768 512q0-52-38-90t-90-38-90 38-38 90q0 53 37.5 90.5t90.5 37.5 90.5-37.5 37.5-90.5zm0-1024q0-52-38-90t-90-38-90 38-38 90q0 53 37.5 90.5t90.5 37.5 90.5-37.5 37.5-90.5zm-384 421v185q0 10-7 19.5t-16 10.5l-155 24q-11 35-32 76 34 48 90 115 7 11 7 20 0 12-7 19-23 30-82.5 89.5t-78.5 59.5q-11 0-21-7l-115-90q-37 19-77 31-11 108-23 155-7 24-30 24h-186q-11 0-20-7.5t-10-17.5l-23-153q-34-10-75-31l-118 89q-7 7-20 7-11 0-21-8-144-133-144-160 0-9 7-19 10-14 41-53t47-61q-23-44-35-82l-152-24q-10-1-17-9.5t-7-19.5v-185q0-10 7-19.5t16-10.5l155-24q11-35 32-76-34-48-90-115-7-11-7-20 0-12 7-20 22-30 82-89t79-59q11 0 21 7l115 90q34-18 77-32 11-108 23-154 7-24 30-24h186q11 0 20 7.5t10 17.5l23 153q34 10 75 31l118-89q8-7 20-7 11 0 21 8 144 133 144 160 0 8-7 19-12 16-42 54t-45 60q23 48 34 82l152 23q10 2 17 10.5t7 19.5zm640 533v140q0 16-149 31-12 27-30 52 51 113 51 138 0 4-4 7-122 71-124 71-8 0-46-47t-52-68q-20 2-30 2t-30-2q-14 21-52 68t-46 47q-2 0-124-71-4-3-4-7 0-25 51-138-18-25-30-52-149-15-149-31v-140q0-16 149-31 13-29 30-52-51-113-51-138 0-4 4-7 4-2 35-20t59-34 30-16q8 0 46 46.5t52 67.5q20-2 30-2t30 2q51-71 92-112l6-2q4 0 124 70 4 3 4 7 0 25-51 138 17 23 30 52 149 15 149 31zm0-1024v140q0 16-149 31-12 27-30 52 51 113 51 138 0 4-4 7-122 71-124 71-8 0-46-47t-52-68q-20 2-30 2t-30-2q-14 21-52 68t-46 47q-2 0-124-71-4-3-4-7 0-25 51-138-18-25-30-52-149-15-149-31v-140q0-16 149-31 13-29 30-52-51-113-51-138 0-4 4-7 4-2 35-20t59-34 30-16q8 0 46 46.5t52 67.5q20-2 30-2t30 2q51-71 92-112l6-2q4 0 124 70 4 3 4 7 0 25-51 138 17 23 30 52 149 15 149 31z"></path></svg>
                            <h3 class="ets_em_title_block">{l s='Settings' mod='ets_migrate'}</h3>
                            <span class="ets_em_popup_cancel"></span>
                        </div>
                        <div class="ets_em_form_wrap"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="ets_em_confirm_modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{l s='Close' mod='ets_migrate'}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="ets_em_confirm_modal_title"></h4>
                    </div>
                    <div class="modal-body">
                        <p id="ets_em_confirm_modal_message"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" id="ets_em_confirm_modal_btn_cancel"></button>
                        <button type="button" class="btn btn-primary" id="ets_em_confirm_modal_btn_confirm"></button>
                    </div>
                </div>
            </div>
        </div>
    {/if}
{/block}

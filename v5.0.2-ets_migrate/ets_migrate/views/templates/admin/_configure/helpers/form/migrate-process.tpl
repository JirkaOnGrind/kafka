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
<div class="form_header_block h_step3 step3" data-step="3">
    <div class="header_img text-center">
        <img src="{$img_path|escape:'html':'UTF-8'}origin/4.png">
    </div>
    <h2 class="text-center">{l s='Migration in progress' mod='ets_migrate'}</h2>
    <p class="title_sub">{l s='This process is running automatically. Please be patient and do not close your web browser.' mod='ets_migrate'} <br/>
    {l s='The migration status of each data entity is displayed below.' mod='ets_migrate'}</p>
    <div class="ets_em_action">
        <span class="action_start disabled">
            <i class="ets_svg_icon">
                <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1312 896q0 37-32 55l-544 320q-15 9-32 9-16 0-32-8-32-19-32-56v-640q0-37 32-56 33-18 64 1l544 320q32 18 32 55zm128 0q0-148-73-273t-198-198-273-73-273 73-198 198-73 273 73 273 198 198 273 73 273-73 198-198 73-273zm224 0q0 209-103 385.5t-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103 385.5 103 279.5 279.5 103 385.5z"/></svg>
            </i> {l s='Play' mod='ets_migrate'}
        </span>
        <span class="action_pause">
            <i class="ets_svg_icon">
                <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M896 128q209 0 385.5 103t279.5 279.5 103 385.5-103 385.5-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103zm0 1312q148 0 273-73t198-198 73-273-73-273-198-198-273-73-273 73-198 198-73 273 73 273 198 198 273 73zm96-224q-14 0-23-9t-9-23v-576q0-14 9-23t23-9h192q14 0 23 9t9 23v576q0 14-9 23t-23 9h-192zm-384 0q-14 0-23-9t-9-23v-576q0-14 9-23t23-9h192q14 0 23 9t9 23v576q0 14-9 23t-23 9h-192z"/></svg>
            </i> {l s='Pause' mod='ets_migrate'}
        </span>
        <span class="action_cancel">
            <i class="ets_svg_icon">
                <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1664 192v1408q0 26-19 45t-45 19h-1408q-26 0-45-19t-19-45v-1408q0-26 19-45t45-19h1408q26 0 45 19t19 45z"/></svg>
            </i> {l s='Cancel' mod='ets_migrate'}
        </span>
        <span class="action_setting">
            <i class="ets_svg_icon">
                <svg class="w_20 h_20" width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1152 896q0-106-75-181t-181-75-181 75-75 181 75 181 181 75 181-75 75-181zm512-109v222q0 12-8 23t-20 13l-185 28q-19 54-39 91 35 50 107 138 10 12 10 25t-9 23q-27 37-99 108t-94 71q-12 0-26-9l-138-108q-44 23-91 38-16 136-29 186-7 28-36 28h-222q-14 0-24.5-8.5t-11.5-21.5l-28-184q-49-16-90-37l-141 107q-10 9-25 9-14 0-25-11-126-114-165-168-7-10-7-23 0-12 8-23 15-21 51-66.5t54-70.5q-27-50-41-99l-183-27q-13-2-21-12.5t-8-23.5v-222q0-12 8-23t19-13l186-28q14-46 39-92-40-57-107-138-10-12-10-24 0-10 9-23 26-36 98.5-107.5t94.5-71.5q13 0 26 10l138 107q44-23 91-38 16-136 29-186 7-28 36-28h222q14 0 24.5 8.5t11.5 21.5l28 184q49 16 90 37l142-107q9-9 24-9 13 0 25 10 129 119 165 170 7 8 7 22 0 12-8 23-15 21-51 66.5t-54 70.5q26 50 41 98l183 28q13 2 21 12.5t8 23.5z"/></svg>
            </i> {l s='Settings' mod='ets_migrate'}
        </span>
    </div>
    <div class="ets_em_timerun">
        {l s='Execution time:' mod='ets_migrate'}
        <div class="ets_em_timerun_clock" style="display: none;">
            <div class="hours"><span class="number">00</span>{l s='hour(s)' mod='ets_migrate'}</div>
            <div class="minutes"><span class="number">00</span>{l s='minute(s)' mod='ets_migrate'}</div>
            <div class="second"><span class="number">00</span>{l s='second(s)' mod='ets_migrate'}</div>
        </div>
    </div>
</div>
<div class="ets_em_list_process"></div>
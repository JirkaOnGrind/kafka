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

<div class="form-group footer-step2 migrate">
    <button type="submit" value="1" class="ets_em_form_submit_btn no_svg_icon" name="submitConfig">{l s='Migrate' mod='ets_migrate'}</button>
	{if isset($show_btn_cancel) && $show_btn_cancel}
		<button name="module_form_cancel_btn" type="button" class="btn btn-default ets_em_popup_cancel no_svg_icon">{l s='Close' mod='ets_migrate'}</button>
	{/if}
	<button name="module_form_advanced_settings_btn" type="button" class="btn btn-default ets_em_advanced_settings no_svg_icon"style="display: none;">{l s='Advanced settings' mod='ets_migrate'}</button>
</div>
<div class="form-group footer-step2 migrate-resume" style="display: none;">
	<button type="button" class="btn btn-default ets_em_new_migration no_svg_icon ets_em_button_default ets_em_popup_resume" name="ets_em_new_migration">{l s='New migration' mod='ets_migrate'}</button>
	<button type="button" class="ets_em_migrate_resume no_svg_icon ets_em_button_primary pull-right ets_em_popup_resume" name="ets_em_migrate_resume">{l s='Resume' mod='ets_migrate'}</button>
</div>
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
<div class="form_header_block h_step4 step4" data-step="4">
	<div class="header_img text-center">
		<img src="{$img_path|escape:'html':'UTF-8'}origin/6.png">
	</div>
	<h2 class="text-center">{l s='Congratulations!' mod='ets_migrate'}</h2>
	<p class="title_sub text-center">{l s='Database migration finished successfully.' mod='ets_migrate'}
		<br/><span class="ets_em_title_sub">{l s='Complete the final steps below before going live.' mod='ets_migrate'}</span>
	</p>
</div>
<div class="ets_em_employee_password_reset_notice ets_em_thankyou" id="ets_em_employee_password_reset_notice" style="display:none;">
	<div class="alert alert-warning ets_em_employee_password_reset_notice_inner">
		<h4 class="alert-heading">{l s='Important: Reset all employee passwords' mod='ets_migrate'}</h4>
		<p class="mb-1"><strong>{l s='You must manually reset the password for every employee in the back office.' mod='ets_migrate'}</strong></p>
		<p class="mb-0">{l s='Reason: PrestaShop 9.0 has completely changed the password hashing mechanism compared to versions before 1.7. Migrated employee passwords from the source store are not compatible and will not work on this store until each password is reset.' mod='ets_migrate'}</p>
		<p class="ets_em_employee_password_reset_list_title">{l s='Employees who must reset their password:' mod='ets_migrate'}</p>
		<ul class="ets_em_employee_password_reset_list"></ul>
	</div>
</div>
<div class="alert alert-info ets_em_thankyou" id="ets_em_csblog_followup" style="display:none;">
	<p class="mb-0">{l s='CS Blog data was migrated. If you use Latest Posts, copy its XML from the source store. Re-apply CS_* settings under Modules > CS Blog. Verify thumbnails in modules/csblog/media/ if images are missing.' mod='ets_migrate'}</p>
</div>
<div class="ets_em_keep_password ets_em_thankyou" style="display:none;">
	<img class="img_pos_left" src="{$img_path|escape:'html':'UTF-8'}origin/7.png"/>
	<h4>{l s='Keep customer passwords' mod='ets_migrate'}</h4>
	<p><b>{l s='Step 1:' mod='ets_migrate'}</b> {l s='Download our free' mod='ets_migrate'} <a href="{$download_plugin_link|cat:'&file=ets_passwordkeeper.zip'|escape:'html':'UTF-8'}" target="_blank"><b class="ets_em_text_danger">{l s='PrestaShop Password Keeper' mod='ets_migrate'}</b></a>{l s=' module' mod='ets_migrate'}</p>
	<p><b>{l s='Step 2:' mod='ets_migrate'}</b> {l s='Install' mod='ets_migrate'} <a href="{$download_plugin_link|cat:'&file=ets_passwordkeeper.zip'|escape:'html':'UTF-8'}" target="_blank"><b class="ets_em_text_danger">{l s='PrestaShop Password Keeper' mod='ets_migrate'}</b></a> {l s=' module on target store (this website)' mod='ets_migrate'}</p>
	<p><b>{l s='Step 3:' mod='ets_migrate'}</b> {l s='Copy the' mod='ets_migrate'} <b>_COOKIE_KEY_</b> {l s='of source store provided below then paste it into' mod='ets_migrate'} <b>{l s='PrestaShop Password Keeper' mod='ets_migrate'}</b> {l s='configuration page.' mod='ets_migrate'}</p>
	<br/>
	<p>
        {l s='Here is' mod='ets_migrate'} <b>_COOKIE_KEY_</b> {l s='of the source store:' mod='ets_migrate'}
			  <input class="ets_em_keycode" type="text" readonly="readonly" title="{l s='Click to copy' mod='ets_migrate'}" placeholder="{l s='Source store _COOKIE_KEY_' mod='ets_migrate'}" value="{if isset($source_cookie_key)}{$source_cookie_key|escape:'html':'UTF-8'}{/if}">
	</p>
	<p>{l s='This key is required for Password Keeper if it is not filled automatically.' mod='ets_migrate'}</p>
</div>
<div class="ets_em_todo_list ets_em_thankyou images">
	<img class="img_pos_left" src="{$img_path|escape:'html':'UTF-8'}origin/7.png"/>
	<h4>{l s='Copy images' mod='ets_migrate'}</h4>
	<div class="ets_em_todo_list_des">{l s='Please copy images from the source store and upload them to respective folders on target store (this website) to complete the migration.' mod='ets_migrate'}</div>
    {*--- Product ---*}
	<div class="ets_em_todo_list_item image" data-image-type="image">
		<h5>{l s='Product images' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/p/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /img/p/
		</div>
	</div>
    {*---Category ---*}
	<div class="ets_em_todo_list_item category" data-image-type="category">
		<h5>{l s='Product category images' mod='ets_migrate'}</h5>

		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/c/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /img/c/
		</div>
	</div>
    {*---Supplier ---*}
	<div class="ets_em_todo_list_item supplier" data-image-type="supplier">
		<h5>{l s='Supplier logo images' mod='ets_migrate'}</h5>

		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/su/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /img/su/
		</div>
	</div>
    {*---Manufacturer ---*}
	<div class="ets_em_todo_list_item manufacturer" data-image-type="manufacturer">
		<h5>{l s='Manufacturer (Brand) logo images' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/m/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /img/m/
		</div>
	</div>
    {*---Carrier ---*}
	<div class="ets_em_todo_list_item carrier" data-image-type="carrier">
		<h5>{l s='Carrier logo images' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/s/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /img/s/
		</div>
	</div>
    {*---Blog ---*}
	<div class="ets_em_todo_list_item ybc_blog_gallery_lang" data-image-type="ybc_blog_gallery_lang">
		<div class="image ets_em_todo_list_item">
			<h5>{l s='Blog gallery images' mod='ets_migrate'}</h5>
			<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span
						class="path"></span>/img/ybc_blog/gallery/
			</div>
			<div class="ets_em_todo_url">
				<span>{l s='Target store:' mod='ets_migrate'}</span> 
				/img/ybc_blog/gallery/
			</div>
		</div>
		<div class="thumb">
			<h5>{l s='Blog gallery thumbnail' mod='ets_migrate'}</h5>
			<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span
						class="path"></span>/img/ybc_blog/gallery/thumb/
			</div>
			<div class="ets_em_todo_url">
				<span>{l s='Target store:' mod='ets_migrate'}</span> 
				/img/ybc_blog/gallery/thumb/
			</div>
		</div>
	</div>
    {*---Blog category ---*}
	<div class="ets_em_todo_list_item ybc_blog_category_lang" data-image-type="ybc_blog_category_lang">
		<div class="image ets_em_todo_list_item">
			<h5>{l s='Blog category images' mod='ets_migrate'}</h5>
			<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span
						class="path"></span>/img/ybc_blog/category/
			</div>
			<div class="ets_em_todo_url">
				<span>{l s='Target store:' mod='ets_migrate'}</span> 
				/img/ybc_blog/category/
			</div>
		</div>
		<div class="thumb">
			<h5>{l s='Blog category thumbnail images' mod='ets_migrate'}</h5>
			<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span
						class="path"></span>/img/ybc_blog/category/thumb/
			</div>
			<div class="ets_em_todo_url">
				<span>{l s='Target store:' mod='ets_migrate'}</span> 
				/img/ybc_blog/category/thumb/
			</div>
		</div>
	</div>
    {*---Blog post ---*}
	<div class="ets_em_todo_list_item ybc_blog_post_lang" data-image-type="ybc_blog_post_lang">
		<div class="image ets_em_todo_list_item">
			<h5>{l s='Blog post images' mod='ets_migrate'}</h5>
			<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span
						class="path"></span>/img/ybc_blog/post/
			</div>
			<div class="ets_em_todo_url">
				<span>{l s='Target store:' mod='ets_migrate'}</span> 
				/img/ybc_blog/post/
			</div>
		</div>
		<div class="thumb">
			<h5>{l s='Blog post thumbnail images' mod='ets_migrate'}</h5>
			<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span
						class="path"></span>/img/ybc_blog/post/thumb/
			</div>
			<div class="ets_em_todo_url">
				<span>{l s='Target store:' mod='ets_migrate'}</span> 
				/img/ybc_blog/post/thumb/
			</div>
		</div>
	</div>
    {*---Blog slide ---*}
	<div class="ets_em_todo_list_item ybc_blog_slide_lang" data-image-type="ybc_blog_slide_lang">
		<h5>{l s='Blog slider images' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/ybc_blog/slide/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> 
			/img/ybc_blog/slide/
		</div>
	</div>
    {*---Blog employee ---*}
	<div class="ets_em_todo_list_item ybc_blog_employee" data-image-type="ybc_blog_employee">
		<h5>{l s='Blog author avatar images' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/img/ybc_blog/avata/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> 
			/img/ybc_blog/avata/
		</div>
	</div>
</div>
<div class="ets_em_todo_list ets_em_thankyou products">
	<img class="img_pos_left" src="{$img_path|escape:'html':'UTF-8'}origin/7.png"/>
	<h4>{l s='Generate thumbnail images' mod='ets_migrate'}</h4>
	<div class="product_thumbnail">
		{l s='Main product images have been downloaded from source store, however thumbnail images have not been generated yet. Please generate thumbnail images for products to complete the migration.' mod='ets_migrate'}
		<br/>
		<a href="{$product_thumb_link|escape:'html':'UTF-8'}" target="_blank">
			<span>{l s='Generate thumbnail images now' mod='ets_migrate'}</span>
		</a>
	</div>
</div>
<div class="ets_em_todo_list ets_em_thankyou files">
	<img class="img_pos_left" src="{$img_path|escape:'html':'UTF-8'}origin/7.png"/>
	<h4>{l s='Copy files & attachments' mod='ets_migrate'}</h4>
	<div class="ets_em_todo_list_des">{l s='Please download files & attachments from source store and upload them into respective folders on target store (this website) to complete the migration.' mod='ets_migrate'}</div>
	<div class="ets_em_todo_list_item attachment" data-file-type="attachment">
		<h5>{l s='Attachment files' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/download/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /download/
		</div>
	</div>
	<div class="ets_em_todo_list_item customized_data" data-file-type="customized_data">
		<h5>{l s='Customization files' mod='ets_migrate'}</h5>
		<div class="ets_em_todo_url"><span>{l s='Source store:' mod='ets_migrate'}</span> <span class="path"></span>/upload/
		</div>
		<div class="ets_em_todo_url">
			<span>{l s='Target store:' mod='ets_migrate'}</span> /upload/
		</div>
	</div>
</div>
<div class="ets_em_thankyou_footer">
	<button class="ets_em_button_default ets_em_new_migration ets_em_popup_resume">{l s='New migration' mod='ets_migrate'}</button>
	<a href="{$page_index|escape:'html':'UTF-8'}" class="ets_em_button_primary pull-right"
	   target="_blank">{l s='View your shop' mod='ets_migrate'}</a>
</div>













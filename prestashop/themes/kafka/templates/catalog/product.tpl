{extends file='parent:catalog/product.tpl'}

{block name='product_description'}
  {* Ignore empty editor markup, while keeping descriptions containing media. *}
{assign var='kafka_description_content' value=$product.description|regex_replace:'#<(img|video|iframe|audio|object|embed)\b#i':'KAFKA_MEDIA'|strip_tags|regex_replace:'~(?:\s|&nbsp;|&#160;|&#xA0;|\x{00A0})+~u':''}
{assign var='kafka_has_description' value=($kafka_description_content != '')}
  {if $kafka_has_description}
    <div class="accordion-item" id="product_description">
      <h2 class="accordion-header" id="product_description_heading">
        <span class="kafka-product-panel-title d-none d-md-flex">{l s='Description' d='Shop.Theme.Catalog'}</span>
        <button class="accordion-button d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#product_description_collapse" aria-expanded="true" aria-controls="product_description_collapse">
          {l s='Description' d='Shop.Theme.Catalog'}
        </button>
      </h2>
      <div id="product_description_collapse" class="accordion-collapse collapse show" aria-labelledby="product_description_heading">
        <div class="accordion-body">
          <div class="product__description rich-text">{$product.description nofilter}</div>
        </div>
      </div>
    </div>
    <div class="kafka-product-comments kafka-product-comments--following">
      {hook h='displayFooterProduct' mod='productcomments' product=$product category=$category}
    </div>
  {else}
    <div class="kafka-product-comments">
      {hook h='displayFooterProduct' mod='productcomments' product=$product category=$category}
    </div>
  {/if}
{/block}

{* Keep the native description, downloads and module panels in the left column. *}
{block name='product_details'}{/block}

{* Replace the default reassurance block with live product details. *}
{block name='hook_display_reassurance'}
  <div class="product__accordion accordion accordion-flush kafka-product-details">
    {include file='catalog/_partials/product-details.tpl'}
  </div>
{/block}

{* Only one recommendation list, using the native category module. *}
{block name='product_accessories'}{/block}
{block name='product_footer'}
  {hook h='displayFooterProduct' excl='productcomments,ps_viewedproduct,ps_categoryproducts' product=$product category=$category}
  {hook h='displayFooterProduct' mod='ps_categoryproducts' product=$product category=$category}
{/block}

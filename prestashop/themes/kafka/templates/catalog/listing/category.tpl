{extends file='parent:catalog/listing/category.tpl'}

{block name='content_columns'}
  {capture assign='kafka_custom_production'}{hook h='displayKafkaCustomProduction' id_category=$category.id}{/capture}
  {if $kafka_custom_production|trim}
    <div class="columns-container container"><div class="row"><div id="center-column" class="center-column page col-12">
      {hook h='displayContentWrapperTop'}
      {$kafka_custom_production nofilter}
      {hook h='displayContentWrapperBottom'}
    </div></div></div>
  {else}
    {$smarty.block.parent}
  {/if}
{/block}

{block name='content'}
  {if empty($listing.products) && !empty($subcategories)}
    {* A parent category remains a useful navigation page even without directly assigned products. *}
    {include file='catalog/_partials/category-header.tpl' listing=$listing category=$category}
    {hook h='displayHeaderCategory'}
    {include file='catalog/_partials/category-footer.tpl' listing=$listing category=$category}
    {hook h='displayFooterCategory'}
  {else}
    {$smarty.block.parent}
  {/if}
{/block}

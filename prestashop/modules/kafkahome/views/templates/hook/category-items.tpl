{foreach $items as $item}
  <li {if $preview && $item@index >= 3}data-category-extra{/if}>
    <div class="kafka-category-row">
      <a href="{$item.url|escape:'html':'UTF-8'}">{$item.name|escape:'html':'UTF-8'}</a>
      {if $item.items}
        <button type="button" class="kafka-subcategory-toggle" data-kafka-subcategory-toggle aria-expanded="false" aria-controls="{$treeId}-{$item.key|escape:'html':'UTF-8'}" aria-label="Podkategorie {$item.name|escape:'html':'UTF-8'}"><span aria-hidden="true">▼</span></button>
      {/if}
    </div>
    {if $item.items}
      <ul class="kafka-nested-categories" id="{$treeId}-{$item.key|escape:'html':'UTF-8'}" hidden>
        {include file='module:kafkahome/views/templates/hook/category-items.tpl' items=$item.items preview=false treeId=$treeId}
      </ul>
    {/if}
  </li>
{/foreach}

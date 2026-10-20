{* Keep native provider URLs and AJAX behavior; present only customer-facing sorts. *}
{assign var=kafka_sort_labels value=['default' => 'Default order', 'price_asc' => 'Lowest price', 'price_desc' => 'Highest price', 'name_asc' => 'Name A–Z', 'name_desc' => 'Name Z–A']}
{if $language.iso_code == 'cs'}
  {assign var=kafka_sort_labels value=['default' => 'Výchozí pořadí', 'price_asc' => 'Od nejlevnějšího', 'price_desc' => 'Od nejdražšího', 'name_asc' => 'Název A–Z', 'name_desc' => 'Název Z–A']}
{elseif $language.iso_code == 'sk'}
  {assign var=kafka_sort_labels value=['default' => 'Predvolené poradie', 'price_asc' => 'Od najlacnejšieho', 'price_desc' => 'Od najdrahšieho', 'name_asc' => 'Názov A–Z', 'name_desc' => 'Názov Z–A']}
{elseif $language.iso_code == 'de'}
  {assign var=kafka_sort_labels value=['default' => 'Standardsortierung', 'price_asc' => 'Preis aufsteigend', 'price_desc' => 'Preis absteigend', 'name_asc' => 'Name A–Z', 'name_desc' => 'Name Z–A']}
{/if}

{assign var=kafka_default_sort value=false}
{* Categories/search use position. Other providers supply their own default first. *}
{foreach from=$listing.sort_orders item=sort_order}
  {if $sort_order.field == 'position'}
    {assign var=kafka_default_sort value=$sort_order}
    {break}
  {/if}
{/foreach}
{if !$kafka_default_sort}
  {foreach from=$listing.sort_orders item=sort_order}
    {if !in_array($sort_order.field, ['price', 'name', 'reference'])}
      {assign var=kafka_default_sort value=$sort_order}
      {break}
    {/if}
  {/foreach}
{/if}

{assign var=kafka_sort_options value=[]}
{if $kafka_default_sort}
  {append var=kafka_sort_options value=['label' => $kafka_sort_labels.default, 'url' => $kafka_default_sort.url, 'current' => $kafka_default_sort.current]}
{/if}
{foreach from=['price', 'name'] item=kafka_sort_field}
  {foreach from=['asc', 'desc'] item=kafka_sort_direction}
    {foreach from=$listing.sort_orders item=sort_order}
      {if $sort_order.field == $kafka_sort_field && $sort_order.direction == $kafka_sort_direction}
        {assign var=kafka_label_key value="`$kafka_sort_field`_`$kafka_sort_direction`"}
        {append var=kafka_sort_options value=['label' => $kafka_sort_labels[$kafka_label_key], 'url' => $sort_order.url, 'current' => $sort_order.current]}
      {/if}
    {/foreach}
  {/foreach}
{/foreach}
{assign var=kafka_selected_label value=$kafka_sort_labels.default}
{foreach from=$kafka_sort_options item=sort_order}
  {if $sort_order.current}{assign var=kafka_selected_label value=$sort_order.label}{/if}
{/foreach}

<div class="products__sort-label">
  <span class="align-middle">{l s='Sort by:' d='Shop.Theme.Global'}</span>
</div>
<div class="products__sort-dropdown">
  <button
    class="products__sort-dropdown-button btn btn-outline-tertiary dropdown-toggle"
    id="sort_dropdown_button"
    rel="nofollow"
    data-bs-toggle="dropdown"
    aria-haspopup="true"
    aria-expanded="false"
    aria-label="{l s='Change sort order' d='Shop.Theme.Global'}">
    {$kafka_selected_label}
  </button>
  <div class="dropdown-menu" role="menu" aria-labelledby="sort_dropdown_button">
    {foreach from=$kafka_sort_options item=sort_order}
      <a
        rel="nofollow"
        href="{$sort_order.url}"
        aria-label="{l s='Sort products by: %sort_order%' sprintf=['%sort_order%' => $sort_order.label] d='Shop.Theme.Global'}"
        role="menuitem"
        {if $sort_order.current}aria-current="true"{/if}
        class="dropdown-item {['current' => $sort_order.current, 'js-search-link' => true]|classnames}">
        {$sort_order.label}
      </a>
    {/foreach}
  </div>
</div>

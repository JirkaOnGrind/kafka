{* The native on_sale flag denotes clearance, independently of a price reduction. *}
{if !empty($product.flags)}
  {block name='product_flags'}
    <ul class="product-flags js-product-flags">
      {foreach from=$product.flags item=flag}
        <li class="badge {$flag.type}">{if $flag.type == 'on-sale'}{if $language.iso_code == 'cs'}Výprodej{elseif $language.iso_code == 'sk'}Výpredaj{elseif $language.iso_code == 'de'}Abverkauf{else}Clearance{/if}{else}{$flag.label}{/if}</li>
      {/foreach}
    </ul>
  {/block}
{/if}

{block name='cart_detailed_product'}
  {* Preserve the native AJAX replacement container and product-line controls. *}
  <div class="cart__overview cart-overview js-cart"
    data-refresh-url="{url entity='cart' params=['ajax' => true, 'action' => 'refresh']}"
    tabindex="-1"
  >
    {if $cart.products}
      <div class="cart__list js-cart-list" role="list" aria-label="{l s='Products in cart' d='Shop.Theme.Checkout'}">
        {foreach from=$cart.products item=product}
          <div class="cart__item js-cart-item" role="listitem">
            {block name='cart_detailed_product_line'}
              {include file='checkout/_partials/cart-detailed-product-line.tpl' product=$product}
            {/block}
          </div>
        {/foreach}
      </div>
    {else}
      <div class="kafka-cart-empty">
        <svg class="kafka-cart-empty__icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <path d="M8 12h8l7 29h27l6-21H19M25 30h23"/>
          <circle cx="27" cy="51" r="3"/><circle cx="47" cy="51" r="3"/>
        </svg>
        <p class="cart__empty">{l s='There are no more items in your cart' d='Shop.Theme.Checkout'}</p>
      </div>
    {/if}
  </div>
{/block}

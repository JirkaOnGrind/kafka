<div id="_desktop_ps_shoppingcart">
  <div class="ps-shoppingcart">
    <div class="header-block d-flex align-items-center blockcart cart-preview {if $cart.products_count > 0}header-block--active{else}inactive{/if}" data-refresh-url="{$refresh_url}">
      <a class="header-block__action-btn pe-md-0" rel="nofollow" href="{$cart_url}" aria-label="{l s='View cart (%d products)' d='Shop.Theme.Checkout' sprintf=[$cart.products_count]}">
        <svg class="kafka-cart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <span class="d-none d-md-flex header-block__title">{l s='Cart' d='Shop.Theme.Checkout'}</span>
        <span class="header-block__badge">{$cart.products_count}</span>
      </a>
    </div>
  </div>
</div>

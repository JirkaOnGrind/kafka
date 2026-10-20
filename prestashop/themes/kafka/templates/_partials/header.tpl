{capture name='kafka_banner'}{hook h='displayBanner'}{/capture}
{if $smarty.capture.kafka_banner}<div class="kafka-banner">{$smarty.capture.kafka_banner nofilter}</div>{/if}
<div class="kafka-header-main">
  <a class="kafka-brand" href="{$urls.pages.index}" aria-label="Kafka Monster — domů"><img src="{$urls.base_url}themes/kafka/assets/img/kafka-monster-client-logo.svg" width="94" height="70" alt="Kafka Monster"></a>
  <a class="kafka-wordmark" href="{$urls.pages.index}" aria-label="Kafka Monster — Punk Rock Shop — domů"><span class="kafka-wordmark-name"><span>KAFKA</span> <span>MONSTER</span></span><span class="kafka-wordmark-tagline">PUNK ROCK SHOP</span></a>
  <div class="kafka-header-tools">
    <button class="kafka-icon-button" type="button" data-kafka-search-toggle aria-controls="kafka-search-panel" aria-expanded="false" aria-label="Hledat produkty"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/></svg></button>
    <div class="kafka-desktop-account">{hook h='displayNav2' mod='ps_customersignin'}</div>
    {if !$configuration.is_catalog}<div class="kafka-desktop-cart">{hook h='displayNav2' mod='ps_shoppingcart'}</div>{/if}
    <div id="_mobile_ps_customersignin" class="kafka-mobile-tool"></div>
    {if !$configuration.is_catalog}<div id="_mobile_ps_shoppingcart" class="kafka-mobile-tool"></div>{/if}
    <button class="kafka-icon-button kafka-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Otevřít menu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
  </div>
</div>
<div class="kafka-navigation">{hook h='displayKafkaNavigation' mod='kafkahome'}</div>
<div id="kafka-search-panel" class="kafka-search-panel" hidden>{hook h='displayTop' mod='ps_searchbar'}</div>

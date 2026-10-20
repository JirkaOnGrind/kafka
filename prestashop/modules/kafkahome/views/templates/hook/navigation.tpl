<nav class="kafka-main-nav" aria-label="Hlavní navigace">
  {foreach $kafka_sections as $section}<a href="{$section.url|escape:'html':'UTF-8'}"{if $section.is_active} aria-current="{if $section.is_current}page{else}location{/if}"{/if}>{$section.name|escape:'html':'UTF-8'}</a>{/foreach}
</nav>
<div class="offcanvas offcanvas-start kafka-mobile-menu" tabindex="-1" id="mobileMenu" aria-label="Hlavní navigace">
  <div class="offcanvas-header"><span class="kafka-mobile-menu-title">Kafka Monster</span><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Zavřít menu"></button></div>
  <nav class="kafka-mobile-links" aria-label="Mobilní navigace">
    {foreach $kafka_sections as $section}
      <div class="kafka-mobile-link"><a href="{$section.url|escape:'html':'UTF-8'}"{if $section.is_active} aria-current="{if $section.is_current}page{else}location{/if}"{/if}>{$section.name|escape:'html':'UTF-8'}</a>
        {if $section.items}<details><summary aria-label="Podkategorie {$section.name|escape:'html':'UTF-8'}"><span aria-hidden="true">▾</span></summary><ul>{include file='module:kafkahome/views/templates/hook/category-items.tpl' items=$section.items preview=false treeId="menu-{$section.key}"}</ul></details>{/if}
      </div>
    {/foreach}
  </nav>
  <div class="kafka-mobile-locales"><div id="_mobile_ps_currencyselector"></div><div id="_mobile_ps_languageselector"></div></div>
</div>

{extends file='page.tpl'}
{block name='page_title'}{$kafka_section.name|escape:'html':'UTF-8'}{/block}
{block name='page_content'}
  <div class="kafka-section-landing">
    <a class="kafka-category-entry" href="{$urls.pages.index}#kategorie">← Zpět do kategorií</a>
    {if $kafka_section.items}
      <ul class="kafka-section-links">{foreach $kafka_section.items as $item}<li><a href="{$item.url|escape:'html':'UTF-8'}">{$item.name|escape:'html':'UTF-8'} <span aria-hidden="true">→</span></a></li>{/foreach}</ul>
    {else}<p class="kafka-empty-section">V této sekci zatím nejsou žádné položky.</p>{/if}
  </div>
{/block}

{extends file='page.tpl'}
{block name='page_title'}{$kafka_information_page.name|escape:'html':'UTF-8'}{/block}
{block name='page_content'}
  <div class="kafka-section-landing"><p>Pro podrobnosti nás kontaktujte.</p><a class="kafka-category-entry" href="{$urls.pages.contact}">Kontakt →</a></div>
{/block}

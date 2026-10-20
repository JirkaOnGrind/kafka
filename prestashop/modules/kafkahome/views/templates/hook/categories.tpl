<section id="kategorie" class="kafka-categories" aria-labelledby="kafka-categories-title">
  <h2 id="kafka-categories-title">Kategorie</h2>
  <div class="kafka-category-grid">
    {foreach $kafka_sections as $section}
      <article class="kafka-category-card" data-kafka-category="{$section.key}" data-subcategory-count="{$section.items|count}" style="--mobile-order:{$section.mobileOrder}">
        <a class="kafka-category-primary" href="{$section.url|escape:'html':'UTF-8'}" aria-label="{$section.name|escape:'html':'UTF-8'} — otevřít kategorii">
          <div class="kafka-icon-frame"><svg class="kafka-category-icon" viewBox="0 0 240 150" aria-hidden="true"><use href="#{$section.icon}"/></svg></div>
          <h3>{$section.name|escape:'html':'UTF-8'}</h3>
        </a>
        {if $section.items}
          <div class="kafka-category-disclosure"><ul id="kafka-items-{$section.key}">
            {include file='module:kafkahome/views/templates/hook/category-items.tpl' items=$section.items preview=true treeId="card-{$section.key}"}
          </ul></div>
        {/if}
        <div class="kafka-category-trigger">
          {if $section.items|count <= 3}<a class="kafka-category-entry" href="{$section.url|escape:'html':'UTF-8'}">Vstoupit</a>{/if}
          {if $section.items}<button type="button" class="kafka-category-toggle" data-kafka-category-toggle aria-expanded="false" aria-controls="kafka-items-{$section.key}" aria-label="Zobrazit podkategorie {$section.name|escape:'html':'UTF-8'}"><span aria-hidden="true">▼</span></button>{/if}
        </div>
      </article>
    {/foreach}
  </div>
</section>

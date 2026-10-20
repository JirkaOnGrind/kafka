{if !empty($subcategories)}
  <section class="kafka-subcategory-overview">
    <ul class="kafka-subcategory-grid">
      {foreach $subcategories as $subcategory}
        <li>
          <a class="kafka-subcategory-card" href="{$subcategory.url|escape:'html':'UTF-8'}">
            {if !empty($subcategory.thumbnail.bySize.category_default.url)}
              <img src="{$subcategory.thumbnail.bySize.category_default.url|escape:'html':'UTF-8'}" alt="" width="{$subcategory.thumbnail.bySize.category_default.width}" height="{$subcategory.thumbnail.bySize.category_default.height}" loading="lazy">
            {/if}
            <span>{$subcategory.name|escape:'html':'UTF-8'}</span>
            <span class="kafka-subcategory-card-arrow" aria-hidden="true">→</span>
          </a>
        </li>
      {/foreach}
    </ul>
  </section>
{/if}

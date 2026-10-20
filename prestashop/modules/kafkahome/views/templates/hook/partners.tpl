<section id="spojenci" class="kafka-partners" aria-labelledby="kafka-partners-title">
  <div class="kafka-partners-intro">
    <p class="kafka-partners-eyebrow">Partneři &amp; kámoši</p>
    <h2 id="kafka-partners-title">Spojenci<br> na <span>scéně.</span></h2>
    <p>Stejný hluk. Stejný postoj.</p>
  </div>
  <ul class="kafka-partners-list">
    {foreach $kafka_partners as $partner}
      <li>
        <a class="kafka-partner" href="{$partner.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer" aria-label="{$partner.name|escape:'html':'UTF-8'} — otevřít web v nové kartě">
          <div class="kafka-partner-logo">
            <img src="{$urls.base_url}themes/kafka/assets/img/partners/{$partner.logo|escape:'html':'UTF-8'}" alt="{$partner.name|escape:'html':'UTF-8'}" width="{$partner.width}" height="{$partner.height}" loading="lazy" decoding="async">
          </div>
          <div class="kafka-partner-caption">
            <span><strong>{$partner.name|escape:'html':'UTF-8'}</strong><small>{$partner.description|escape:'html':'UTF-8'}</small></span>
            <span class="kafka-partner-arrow" aria-hidden="true">↗</span>
          </div>
        </a>
      </li>
    {/foreach}
  </ul>
</section>

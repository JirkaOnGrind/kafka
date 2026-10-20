<section class="kafka-production" aria-labelledby="production-title">
  <header class="kafka-production-header">
    <p class="kafka-production-eyebrow">Vlastní motiv / zakázková výroba</p>
    <h1 id="production-title">{$kafka_service.name|escape:'html':'UTF-8'} s vlastním motivem</h1>
    <p>Vyberte provedení v ceníku a pošlete nám svůj motiv. Cenu a možnosti výroby potvrdíme e-mailem.</p>
    <ol class="kafka-production-steps"><li><strong>01 / Vyberte provedení</strong><span>Prohlédněte si ceník a podklady.</span></li><li><strong>02 / Nahrajte motiv</strong><span>Přiložte soubor a napište počet kusů.</span></li><li><strong>03 / Potvrdíme kalkulaci</strong><span>Vše společně doladíme e-mailem.</span></li></ol>
  </header>
  <div class="kafka-production-grid">
    <section class="kafka-production-prices" aria-labelledby="production-prices">
      <h2 id="production-prices">Ceník a provedení</h2>
      <p>Ceny jsou orientační. Konečnou cenu vám potvrdíme podle motivu, množství a materiálu.</p>
      {foreach $kafka_price_lists as $price}
        <details class="kafka-production-price" {if $price@first}open{/if}>
          <summary>{$price.name|escape:'html':'UTF-8'}</summary>
          <div class="rich-text">{$price.description_short nofilter}{$price.description nofilter}</div>
        </details>
      {foreachelse}<p>Pro tuto službu zatím není zveřejněný ceník. Pošlete motiv a požadované množství, připravíme individuální kalkulaci.</p>{/foreach}
    </section>
    <section class="kafka-production-form" aria-labelledby="production-form-title">
      <p class="kafka-production-eyebrow">Váš nápad, naše výroba</p>
      <h2 id="production-form-title">Pošlete nám motiv</h2>
      <p>Nezávazná poptávka. Výrobu zahájíme až po společném potvrzení.</p>
      {if $kafka_quote.error}<p class="alert alert-danger" role="alert">{$kafka_quote.error|escape:'html':'UTF-8'}</p>{/if}
      {if $kafka_quote.success}<p class="alert alert-success" role="status">{$kafka_quote.success|escape:'html':'UTF-8'}</p>{/if}
      <form method="post" action="{$kafka_quote_url|escape:'html':'UTF-8'}#production-form-title" enctype="multipart/form-data">
        <input type="hidden" name="quote_token" value="{$kafka_quote_token|escape:'html':'UTF-8'}">
        <div hidden aria-hidden="true"><label>Web<input type="text" name="quote_website" tabindex="-1" autocomplete="off"></label></div>
        <label for="quote-email">Váš e-mail <span>(povinné)</span></label>
        <input id="quote-email" type="email" name="quote_email" autocomplete="email" required maxlength="254" value="{$kafka_quote.email|escape:'html':'UTF-8'}">
        <label for="quote-file">Soubor s motivem <span>(povinné)</span></label>
        <input id="quote-file" type="file" name="quote_file" accept=".pdf,.png,.jpg,.jpeg" aria-describedby="quote-file-help" required>
        <small id="quote-file-help">PDF, PNG nebo JPG · nejvýše 10 MB. Pro výrobu doporučujeme PDF; požadavky na rozměr najdete u provedení.</small>
        <label for="quote-message">Provedení, počet kusů a poznámka</label>
        <textarea id="quote-message" name="quote_message" rows="5" maxlength="5000" placeholder="Např. kovový náramek, stříbrný, 25 kusů…">{$kafka_quote.message|escape:'html':'UTF-8'}</textarea>
        <small>Kontaktní e-mail a přílohu použijeme k vyřízení vaší poptávky.</small>
        <button class="btn btn-primary" type="submit" name="kafka_quote_submit" value="1">Odeslat nezávaznou poptávku →</button>
      </form>
    </section>
  </div>
</section>

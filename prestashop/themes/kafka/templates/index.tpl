{extends file='parent:index.tpl'}
{block name='hook_home'}
  {hook h='displayKafkaTrust' mod='kafkahome'}
  <section class="kafka-hero" aria-labelledby="kafka-hero-title">
    <div class="kafka-hero-copy">
      <h1 id="kafka-hero-title">Punk není trend.<br><span>Je to postoj.</span></h1>
      <p>Oblečení, doplňky a punkové artefakty pro lidi, kteří<br class="kafka-desktop-break"> se nepotřebují vejít do řady.</p>
      <a class="kafka-hero-cta" href="#kategorie">Podívat se do obchodu <span aria-hidden="true">⟶</span></a>
    </div>
  </section>
  {hook h='displayKafkaCategories' mod='kafkahome'}
{/block}

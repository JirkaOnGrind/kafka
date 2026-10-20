"""Generate review inventories from the sanitized audit, without changing the shop."""
import csv, json
from pathlib import Path

ROOT=Path(__file__).parent
s=json.loads((ROOT/'evidence/audit-summary.json').read_text(encoding='utf-8'))
o=s['operational']
def write(name,fields,rows):
    with (ROOT/name).open('w',encoding='utf-8-sig',newline='') as f:
        w=csv.DictWriter(f,fieldnames=fields); w.writeheader(); w.writerows(rows)

enabled={x['id_module'] for x in o['module_shop'] if x['id_shop']==1}
special={
 'dm_comgate':('Platby','Nový modul pro Comgate; potvrdit původního dodavatele a kompatibilitu 9.2. Staré transakce archivovat, rozpracované platby dořešit.','Alternativa shaim_comgate deklaruje PS9; původní dm_comgate neověřen','https://psmoduly.cz/platebni-modul-platebni-brana-comgate-293/platebni-moduly.htm'),
 'dm_cashondelivery':('Platby','Nahradit podporovaným modulem dobírky s příplatkem; zachovat omezení dopravců/měn.','Původní dm_cashondelivery neověřen','https://psmoduly.cz/platebni-modul-dobirka-s-priplatkem-platba-na-dobirku-277/platebni-moduly.htm'),
 'bankwire':('Platby','Nativní ps_wirepayment; účet a pokyny nastavit znovu.','Nová instalace místo starého bankwire','https://github.com/PrestaShop/PrestaShop/blob/9.2.0/composer.json'),
 'packetery':('Doprava','Nový oficiální modul Packeta; přenos poboček u otevřených objednávek vyřešit zvlášť.','Dodavatel deklaruje PS9; nativní OPC9.2 ověřit testem','https://github.com/Zasilkovna/prestashop'),
 'shaim_dpdparcelshop':('Doprava','Aktuální balíček dodavatele; nastavit mapu/dopravce; staré tabulky nekopírovat naslepo.','Dodavatel deklaruje PS9; OPC9.2 ověřit zvlášť','https://psmoduly.cz/dpd-parcel-shop-pick-up-vyber-odberneho-mista-primo-v-objednavce-alzaboxy-242/vydejni-mista.htm'),
 'mergado':('Marketing','Nový Mergado Pack; nastavit feedy, cron, souhlasy a měření; zachovat identifikátory položek.','Dodavatel deklaruje PS9','https://pack.mergado.cz/prestashop'),
 'shaim_cookies_eu':('Souhlasy','Aktuální CMP; obnovit blokování měření a souhlasy, staré návštěvnické logy archivovat.','Přesnou verzi pro 9.2 ověřit',''),
 'usercentrics':('Souhlasy','Prověřit překryv s shaim_cookies_eu, zvolit jednu hlavní CMP.','Neověřeno',''),
 'shaim_recaptcha_v3':('Ochrana formulářů','Aktuální balíček; domény a veřejný/tajný klíč nastavit mimo veřejné dokumenty.','Neověřeno pro konkrétní 9.2',''),
 'shaim_pobidka_dopravy_zdarma':('Obchodní pravidla','Sjednotit zobrazený limit s dopravními pravidly; nová verze nebo drobný vlastní blok.','Neověřeno pro konkrétní 9.2',''),
 'ps_checkout':('Platby','V datech nejsou objednávky s tímto modulem; ověřit použití, při spuštění přidávat jen potřebné brány.','Starý balíček 6.3.5.1 nepřenášet',''),
 'paypal':('Platby','Není přiřazen do shopu; jedna historická objednávka neprokazuje současné používání.','Obnovit jen pokud klient chce',''),
 'cashondelivery':('Platby','Není přiřazen do shopu; historie zachovat, aktuální dobírku řeší dm_cashondelivery.','Nová instalace místo starého balíčku',''),
 'mailalerts':('E-maily','Nový ps_emailalerts; ověřit upozornění klientovi/adminovi a dostupnost produktu.','Aktuální modul pro PS9.2 ověřit',''),
 'gsitemap':('SEO','Aktuální generátor sitemap; po finální migraci regenerovat a odeslat do Search Console.','Nový podporovaný balíček',''),
}
rows=[]
for m in o['module']:
    if m['name'] in special: role,action,status,url=special[m['name']]
    elif m['name'].startswith(('stats','dash')) or m['name'] in ['sekeywords','pagesnotfound','gamification','graphnvd3','gridhtml','gapi']:
        role,action,status,url='Statistiky','Staré logy/indexy archivovat; na cíli používat podporované aktuální moduly.','Starý balíček nepřenášet',''
    else: role,action,status,url='Nativní/prezentační modul','Obnovit potřebnou funkci současným nativním nebo theme modulem; 1.6 soubory nekopírovat.','Konkrétní náhradu určit podle použití',''
    rows.append(dict(name=m['name'],source_version=m['version'],module_active_flag=m['active'],assigned_to_shop=int(m['id_module'] in enabled),role=role,proposed_action=action,compatibility_evidence=status,source=url))
write('module-matrix.csv',list(rows[0]),rows)

cats={x['id_category']:x for x in o['category']}; labels={x['id_category']:x for x in o['category_lang'] if x['id_lang']==2}
rootmap={107:'Oblečení / Pánské',105:'Oblečení / Dámské',82:'Oblečení / Dětské',32:'Doplňky / Čepice a šátky',88:'Doplňky / Šperky a ostatní',90:'Doplňky / Ostatní',3:'Doplňky / Placky a piny',19:'Doplňky / Nášivky',58:'Doplňky / Tašky a peněženky',111:'Merche',49:'Hudba',81:'Výprodej',10:'Custom'}
rows=[]
for cid,c in cats.items():
    label=labels.get(cid,{})
    root=cid
    while root not in rootmap and root in cats and cats[root]['id_parent'] not in [0,1,2]: root=cats[root]['id_parent']
    path=rootmap.get(root,'Technická/archivní kategorie')
    if cid not in [1,2] and cid!=root: path+=' / '+label.get('name','')
    action='Zachovat ID, slug a výchozí kategorii produktů; měnit jen navigaci.'
    note=''
    if cid in [10,11,12,16,18,41,44,63,64,79,25]:
        action='Custom CMS vstup; původní URL/katalog zachovat nebo individuálně přesměrovat po kontrole objednávek.'
        note='Zakázkové produkty jsou součást historie; Custom bez feedu neznamená odstranit produkty.'
    if cid==25: note='98 vazeb produktů, pouze 1 aktivní; nutné zachovat nákup klientovy konkrétní zakázky přes odkaz.'
    if cid in [118,120,86]: note='Menu může sloučit pod mikiny, typ zapínání jako filtr; URL při prvním spuštění zachovat.'
    if cid in [87,74]: note='Dětské nášivky sjednotit navigačně; zachovat staré odkazy.'
    if cid==81: note='Výprodej má 56 vazeb; slevových záznamů je 1564. Nejsou to stejné množiny.'
    if cid==70 or c['active']==0: action='Ponechat neaktivní, rozhodnout SEO/archiv individuálně; nezapínat automaticky.'
    if cid in [1,2]: action='Technický root, nevystavovat jako novou hlavní kategorii.'
    rows.append(dict(source_id=cid,source_name=label.get('name',''),source_parent=c['id_parent'],active=c['active'],product_links=c['product_links'],active_product_links=c['active_product_links'],source_url=f"https://kafka.monster/cs/{cid}-{label.get('link_rewrite','')}",new_navigation=path,action=action,note=note))
write('category-mapping.csv',list(rows[0]),rows)

rows=[]
ranges={r['id_range_price']:r for r in o['range_price']}
zones={r['id_zone']:r['name'] for r in o['zone']}
for c in o['carrier']:
    if not c['active'] or c['deleted']: continue
    for d in o['delivery']:
        if d['id_carrier']!=c['id_carrier']: continue
        ra=ranges.get(d['id_range_price'],{})
        rows.append(dict(carrier_id=c['id_carrier'],carrier_reference=c['id_reference'],carrier_name=c['name'],zone_id=d['id_zone'],zone=zones.get(d['id_zone'],''),range_from=ra.get('delimiter1'),range_to=ra.get('delimiter2'),base_shipping_price=d['price'],note='Částky v základní měně CZK; ověřit handling, dobírku, měnu, dostupnost a skutečný checkout.'))
write('shipping-rules.csv',list(rows[0]),rows)
print(json.dumps({'modules':len(o['module']),'assigned':len(enabled),'categories':len(cats),'shipping_rows':len(rows)},ensure_ascii=True))

"""Read-only phpMyAdmin dump audit. Never executes SQL or exports personal data.

Supports this dump's explicit-column INSERT ... VALUES format, MySQL quoting
and escapes. Counts every tuple, keeps only whitelisted operational fields.
"""
import argparse, csv, hashlib, json, re
from collections import Counter, defaultdict
from pathlib import Path

KEEP = {
 'configuration': ['name','value','id_shop','id_shop_group'],
 'module':['id_module','name','active','version'],
 'module_shop':['id_module','id_shop'], 'theme':['id_theme','name','directory'],
 'shop':['id_shop','id_shop_group','id_theme','name','active','deleted'],
 'shop_url':['id_shop','domain','domain_ssl','physical_uri','virtual_uri','main','active'],
 'lang':['id_lang','name','active','iso_code','language_code'],
 'currency':['id_currency','name','iso_code','conversion_rate','active','deleted'],
 'category':['id_category','id_parent','active','is_root_category','level_depth'],
 'category_lang':['id_category','id_shop','id_lang','name','link_rewrite'],
 'category_product':['id_category','id_product'],
 'product':['id_product','id_category_default','active','price','id_tax_rules_group','reference','available_for_order','out_of_stock','advanced_stock_management','customizable','uploadable_files','text_fields','date_add','date_upd','cache_is_pack','is_virtual'],
 'product_shop':['id_product','id_shop','id_category_default','active','price','id_tax_rules_group'],
 'product_lang':['id_product','id_shop','id_lang','name','link_rewrite'],
 'product_attribute':['id_product_attribute','id_product','price','default_on','reference'],
 'product_attribute_combination':['id_attribute','id_product_attribute'],
 'product_attribute_image':['id_product_attribute','id_image'],
 'attribute':['id_attribute','id_attribute_group','color'],
 'attribute_lang':['id_attribute','id_lang','name'],
 'attribute_group':['id_attribute_group','is_color_group','group_type'],
 'attribute_group_lang':['id_attribute_group','id_lang','name','public_name'],
 'feature':['id_feature'], 'feature_lang':['id_feature','id_lang','name'],
 'feature_product':['id_feature','id_product','id_feature_value'],
 'feature_value':['id_feature_value','id_feature','custom'],
 'image':['id_image','id_product','position','cover'],
 'stock_available':['id_stock_available','id_product','id_product_attribute','id_shop','id_shop_group','quantity','depends_on_stock','out_of_stock'],
 'carrier':['id_carrier','id_reference','name','active','deleted','is_module','external_module_name','shipping_method','is_free'],
 'carrier_lang':['id_carrier','id_lang','delay'],
 'delivery':['id_carrier','id_range_price','id_range_weight','id_zone','price','id_shop'],
 'range_price':['id_range_price','id_carrier','delimiter1','delimiter2'],
 'range_weight':['id_range_weight','id_carrier','delimiter1','delimiter2'],
 'zone':['id_zone','name','active'], 'country':['id_country','id_zone','iso_code','active'],
 'cms':['id_cms','active','indexation','position'],
 'cms_lang':['id_cms','id_shop','id_lang','meta_title','link_rewrite'],
 'tax':['id_tax','rate','active','deleted'],
 'tax_rules_group':['id_tax_rules_group','name','active','deleted'],
 'tax_rule':['id_tax_rules_group','id_country','id_state','id_tax','behavior'],
 'orders':['id_order','id_shop','id_customer','id_carrier','id_currency','id_lang','current_state','module','payment','total_paid','total_paid_tax_incl','total_shipping_tax_incl','conversion_rate','invoice_number','date_add','date_upd'],
 'order_detail':['id_order','product_id','product_attribute_id','product_quantity','total_price_tax_incl'],
 'order_state':['id_order_state','paid','shipped','delivery','invoice','deleted'],
 'order_state_lang':['id_order_state','id_lang','name'],
 'customer':['id_customer','id_shop','id_default_group','active','deleted','is_guest','newsletter','optin','date_add'],
 'customer_group':['id_customer','id_group'], 'address':['id_address','id_customer','id_country','deleted'],
 'group':['id_group','reduction','price_display_method'], 'group_lang':['id_group','id_lang','name'],
 'specific_price':['id_product','id_product_attribute','id_customer','id_group','id_currency','price','from_quantity','reduction','reduction_type','from','to'],
 'cart_rule':['id_cart_rule','id_customer','date_from','date_to','quantity','active','reduction_percent','reduction_amount','free_shipping'],
 'pack':['id_product_pack','id_product_item','id_product_attribute_item','quantity'],
 'product_download':['id_product','active','nb_downloadable','date_expiration'],
 'customization_field':['id_product','type','required'],
 'hook':['id_hook','name'], 'hook_module':['id_module','id_hook','id_shop','position'],
 'meta':['id_meta','page'], 'meta_lang':['id_meta','id_shop','id_lang','url_rewrite'],
}
CONFIG = {
 'PS_VERSION_DB','PS_SHOP_NAME','PS_SHOP_DOMAIN','PS_SHOP_DOMAIN_SSL','PS_LANG_DEFAULT',
 'PS_CURRENCY_DEFAULT','PS_COUNTRY_DEFAULT','PS_MULTISHOP_FEATURE_ACTIVE',
 'PS_SSL_ENABLED','PS_SSL_ENABLED_EVERYWHERE','PS_REWRITING_SETTINGS','PS_ROUTE_product_rule',
 'PS_ROUTE_category_rule','PS_ROUTE_cms_rule','PS_ROUTE_cms_category_rule','PS_ROUTE_supplier_rule',
 'PS_ROUTE_manufacturer_rule','PS_TAX','PS_TAX_DISPLAY','PS_PRICE_ROUND_MODE','PS_ROUND_TYPE',
 'PS_ORDER_PROCESS_TYPE','PS_GUEST_CHECKOUT_ENABLED','PS_CATALOG_MODE','PS_STOCK_MANAGEMENT',
 'PS_ADVANCED_STOCK_MANAGEMENT','PS_ORDER_OUT_OF_STOCK','PS_SHIPPING_FREE_PRICE','PS_SHIPPING_FREE_WEIGHT',
 'PS_SHIPPING_HANDLING','PS_SHIPPING_METHOD','PS_CARRIER_DEFAULT','PS_INVOICE_PREFIX','PS_INVOICE_NUMBER',
 'PS_DELIVERY_PREFIX','PS_DELIVERY_NUMBER','PS_PRODUCTS_ORDER_BY','PS_PRODUCTS_ORDER_WAY',
 'PS_ALLOW_ACCENTED_CHARS_URL','PS_TIMEZONE','PS_INVOICE','PS_ORDER_RETURN','PS_ORDER_RETURN_NB_DAYS',
 'PS_ATTRIBUTE_CATEGORY_DISPLAY','PS_DISCOUNT_FEATURE_ACTIVE','PS_COMBINATION_FEATURE_ACTIVE',
 'PS_FEATURE_FEATURE_ACTIVE','PS_ORDER_MINIMUM','PS_GROUP_FEATURE_ACTIVE','PS_PURCHASE_MINIMUM',
}

def tuples(text):
    i, n = 0, len(text)
    while i < n:
        if text[i] != '(':
            i += 1; continue
        i += 1; vals = []
        while i < n:
            while i < n and text[i].isspace(): i += 1
            if text[i] == "'":
                i += 1; chars = []
                while i < n:
                    c = text[i]; i += 1
                    if c == '\\':
                        if i >= n: raise ValueError('Truncated escape')
                        c = text[i]; i += 1
                        chars.append({'n':'\n','r':'\r','t':'\t','0':'\0','Z':'\x1a'}.get(c,c))
                    elif c == "'":
                        if i < n and text[i] == "'": chars.append("'"); i += 1
                        else: break
                    else: chars.append(c)
                v = ''.join(chars)
            else:
                start = i
                while i < n and text[i] not in ',)': i += 1
                raw = text[start:i].strip()
                if raw.upper() == 'NULL': v = None
                elif re.fullmatch(r'-?\d+',raw): v = int(raw)
                else:
                    try: v = float(raw)
                    except ValueError: v = raw
            vals.append(v)
            while i < n and text[i].isspace(): i += 1
            if i >= n: raise ValueError('Truncated tuple')
            if text[i] == ')': i += 1; yield vals; break
            if text[i] != ',': raise ValueError('Unexpected tuple separator')
            i += 1

def read_dump(path, keep=KEEP):
    tables, data, count = {}, defaultdict(list), Counter()
    table = None; ddl = []; insert = None; parts = []
    with path.open(encoding='utf-8-sig') as stream:
        for line_no,line in enumerate(stream,1):
            if line.startswith('CREATE TABLE') or line.startswith('CREATE TEMPORARY TABLE'):
                table = re.search(r'`([^`]+)`',line).group(1); ddl=[line]; start=line_no
            elif table:
                ddl.append(line)
                if line.rstrip().endswith(';'):
                    s=''.join(ddl); columns=re.findall(r'^\s+`([^`]+)`',s,re.M)
                    tables[table]={'columns':columns,'ddl_line':start,'engine':next(iter(re.findall(r'ENGINE=(\w+)',s)),None),'auto_increment':next(iter(re.findall(r'AUTO_INCREMENT=(\d+)',s)),None)}
                    table=None
            if line.startswith('INSERT INTO'):
                m=re.match(r'INSERT INTO `([^`]+)`\s*\((.*?)\) VALUES\s*(.*)',line,re.S)
                if not m: raise ValueError(f'Unsupported INSERT at {line_no}')
                insert=(m[1],re.findall(r'`([^`]+)`',m[2])); parts=[m[3]]
            elif insert: parts.append(line)
            if insert and line.rstrip().endswith(';'):
                name,cols=insert; base=name.removeprefix('ejf6_'); wanted=keep.get(base,[])
                indexes=[(c,cols.index(c)) for c in wanted if c in cols]
                # Skip tuple parsing for tables with no fields of interest. This dump has
                # one tuple per line. Verify tuple boundaries before counting them.
                if not indexes:
                    nonempty=[p for p in parts if p.strip()]
                    if all(p.lstrip().startswith('(') and re.search(r'\)[,;]\s*$',p) for p in nonempty):
                        count[name]+=len(nonempty)
                    else: count[name]+=sum(1 for _ in tuples(''.join(parts)))
                else:
                    for row in tuples(''.join(parts)):
                        if len(row)!=len(cols): raise ValueError(f'Column mismatch {name}')
                        count[name]+=1
                        record={c:row[idx] for c,idx in indexes}
                        if base!='configuration' or record.get('name') in CONFIG: data[base].append(record)
                insert=None; parts=[]
    if insert or table: raise ValueError('Incomplete dump')
    return tables, data, count

def main():
    p=argparse.ArgumentParser(); p.add_argument('dump',type=Path); p.add_argument('--out',type=Path,default=Path('migration/evidence')); p.add_argument('--schema',type=Path)
    args=p.parse_args(); args.out.mkdir(parents=True,exist_ok=True)
    tables,d,c=read_dump(args.dump)
    with (args.out/'table-inventory.csv').open('w',encoding='utf-8-sig',newline='') as f:
        w=csv.writer(f); w.writerow(['table','dump_rows','engine','ddl_line','columns','auto_increment'])
        for t,s in tables.items(): w.writerow([t,c[t],s['engine'],s['ddl_line'],len(s['columns']),s['auto_increment']])
    # Personal identifiers are used only for aggregate referential checks, then discarded.
    products={r['id_product'] for r in d['product']}; customers={r['id_customer'] for r in d['customer']}; cats={r['id_category'] for r in d['category']}
    combinations={r['id_product_attribute'] for r in d['product_attribute']}; orders={r['id_order'] for r in d['orders']}
    def dist(tab,key): return dict(Counter(str(r.get(key)) for r in d[tab]))
    summary={'source':str(args.dump),'bytes':args.dump.stat().st_size,'sha256':hashlib.file_digest(args.dump.open('rb'),'sha256').hexdigest(),'tables':len(tables),'total_rows':sum(c.values()),'counts':dict(c),'configuration':d['configuration'],
      'product_active':dist('product','active'),'products_with_combinations':len({r['id_product'] for r in d['product_attribute']}),'product_tax_groups':dist('product','id_tax_rules_group'),'product_customizable':dist('product','customizable'),'product_virtual':dist('product','is_virtual'),'product_pack':dist('product','cache_is_pack'),
      'stock_negative_rows':sum(r['quantity']<0 for r in d['stock_available']),'stock_policy':dist('stock_available','out_of_stock'),'stock_depends_on_stock':dist('stock_available','depends_on_stock'),
      'customer_guests':dist('customer','is_guest'),'customer_active':dist('customer','active'),'customer_deleted':dist('customer','deleted'),'customer_newsletter':dist('customer','newsletter'),'customer_optin':dist('customer','optin'),
      'orders_by_module':dist('orders','module'),'orders_by_payment':dist('orders','payment'),'orders_by_state':dist('orders','current_state'),'orders_by_currency':dist('orders','id_currency'),'orders_by_lang':dist('orders','id_lang'),
      'orders_by_year':dict(Counter(r['date_add'][:4] for r in d['orders'])),'orders_latest':max((r['date_add'] for r in d['orders']),default=None),'orders_earliest':min((r['date_add'] for r in d['orders']),default=None),
      'orphan_checks':{'order_missing_customer':sum(r['id_customer'] not in customers for r in d['orders']),'order_detail_missing_order':sum(r['id_order'] not in orders for r in d['order_detail']),'order_detail_deleted_product':sum(r['product_id'] not in products for r in d['order_detail']),'combination_missing_product':sum(r['id_product'] not in products for r in d['product_attribute']),'stock_missing_product':sum(r['id_product'] not in products for r in d['stock_available']),'stock_missing_combination':sum(r['id_product_attribute']!=0 and r['id_product_attribute'] not in combinations for r in d['stock_available']),'product_missing_default_category':sum(r['id_category_default'] not in cats for r in d['product']),'image_missing_product':sum(r['id_product'] not in products for r in d['image'])}}
    summary['orders_recent']={key:dict(Counter(str(r[field]) for r in d['orders'] if r['date_add']>='2026-01-01')) for key,field in [('modules','module'),('states','current_state'),('currencies','id_currency')]}
    safe_tabs=['module','module_shop','theme','shop','shop_url','lang','currency','category','category_lang','attribute','attribute_lang','attribute_group','attribute_group_lang','feature_lang','carrier','carrier_lang','delivery','range_price','range_weight','zone','cms','cms_lang','tax','tax_rules_group','tax_rule','order_state','order_state_lang','group','group_lang','hook','hook_module','meta','meta_lang']
    summary['operational']={t:d[t] for t in safe_tabs}
    active_products={x['id_product'] for x in d['product'] if x['active']==1}
    cat_counts=Counter(r['id_category'] for r in d['category_product']); cat_active=Counter(r['id_category'] for r in d['category_product'] if r['id_product'] in active_products)
    for r in summary['operational']['category']: r.update(product_links=cat_counts[r['id_category']],active_product_links=cat_active[r['id_category']])
    summary['specific_prices']={'reduction_type':dist('specific_price','reduction_type'),'by_currency':dist('specific_price','id_currency'),'customer_specific':sum(r['id_customer']!=0 for r in d['specific_price']),'quantity_breaks':sum(r['from_quantity']>1 for r in d['specific_price'])}
    summary['top_tables']=sorted(c.items(),key=lambda x:x[1],reverse=True)[:20]
    summary['category_relation_orphans']={'missing_product':sum(r['id_product'] not in products for r in d['category_product']),'missing_category':sum(r['id_category'] not in cats for r in d['category_product'])}
    summary['image_coverage']={'products_without_image':len(products-{r['id_product'] for r in d['image']}),'active_products_without_image':len({r['id_product'] for r in d['product'] if r['active']==1}-{r['id_product'] for r in d['image']})}
    summary['translation_coverage']={str(l['id_lang']):{'products':len({r['id_product'] for r in d['product_lang'] if r['id_lang']==l['id_lang']}),'empty_product_name':sum(not r['name'].strip() for r in d['product_lang'] if r['id_lang']==l['id_lang'])} for l in d['lang']}
    names={(r['id_product'],r['id_lang']):r['name'] for r in d['product_lang']}
    summary['translated_name_equal_to_cs']={str(l['id_lang']):sum(r['name']==names.get((r['id_product'],2)) for r in d['product_lang'] if r['id_lang']==l['id_lang']) for l in d['lang'] if l['id_lang']!=2}
    summary['orphan_ids']={'combination_ids':[r['id_product_attribute'] for r in d['product_attribute'] if r['id_product'] not in products], 'combination_parent_products':sorted({r['id_product'] for r in d['product_attribute'] if r['id_product'] not in products}), 'stock_parent_products':sorted({r['id_product'] for r in d['stock_available'] if r['id_product'] not in products})}
    attrlinks=Counter(r['id_product_attribute'] for r in d['product_attribute_combination'])
    summary['combination_attribute_count']=dict(Counter(attrlinks[r['id_product_attribute']] for r in d['product_attribute']))
    defaults=Counter(r['id_product'] for r in d['product_attribute'] if r['default_on']==1)
    with_combinations={r['id_product'] for r in d['product_attribute'] if r['id_product'] in products}
    summary['combination_defaults']={'multiple':sum(defaults[x]>1 for x in with_combinations),'none':sum(defaults[x]==0 for x in with_combinations)}
    summary['empty_product_reference']=sum(not (r['reference'] or '').strip() for r in d['product'])
    summary['category_status']=dist('category','active')
    summary['category_default_distribution']=dict(Counter(r['id_category_default'] for r in d['product']))
    summary['live_carrier_count']=sum(r['active']==1 and r['deleted']==0 for r in d['carrier'])
    summary['order_shipping_methods']=dict(Counter(r['id_carrier'] for r in d['orders']))
    summary['order_invoice_count_max']=max((r['invoice_number'] for r in d['orders']),default=0)
    summary['sample_products']=[r for r in d['product'] if r['id_product'] in [3415,3454,3501]]
    sample_ids={r['id_product'] for r in summary['sample_products']}
    summary['sample_combinations']=[r for r in d['product_attribute'] if r['id_product'] in sample_ids]
    summary['sample_combination_attribute_links']=[r for r in d['product_attribute_combination'] if r['id_product_attribute'] in {x['id_product_attribute'] for x in summary['sample_combinations']}]
    summary['sample_stock']=[r for r in d['stock_available'] if r['id_product'] in sample_ids]
    # Public URL inventory, reconstructed from source IDs/slugs. Validate against live URLs before using.
    lang={r['id_lang']:r['iso_code'] for r in d['lang']}; catlang={(r['id_category'],r['id_lang']):r for r in d['category_lang']}; product={r['id_product']:r for r in d['product']}
    with (args.out/'source-url-inventory.csv').open('w',encoding='utf-8-sig',newline='') as f:
        w=csv.writer(f); w.writerow(['entity','id','language','active','source_url','name','status'])
        for r in d['category_lang']:
            if r['id_lang'] in lang: w.writerow(['category',r['id_category'],lang[r['id_lang']],next((x['active'] for x in d['category'] if x['id_category']==r['id_category']),''),f"https://kafka.monster/{lang[r['id_lang']]}/{r['id_category']}-{r['link_rewrite']}",r['name'],'reconstructed_not_crawled'])
        for r in d['product_lang']:
            pr=product.get(r['id_product']); cl=catlang.get((pr['id_category_default'],r['id_lang'])) if pr else None
            if pr and cl and r['id_lang'] in lang: w.writerow(['product',r['id_product'],lang[r['id_lang']],pr['active'],f"https://kafka.monster/{lang[r['id_lang']]}/{cl['link_rewrite']}/{r['id_product']}-{r['link_rewrite']}.html",r['name'],'reconstructed_not_crawled'])
        for r in d['cms_lang']:
            if r['id_lang'] in lang: w.writerow(['cms',r['id_cms'],lang[r['id_lang']],next((x['active'] for x in d['cms'] if x['id_cms']==r['id_cms']),''),f"https://kafka.monster/{lang[r['id_lang']]}/content/{r['id_cms']}-{r['link_rewrite']}",r['meta_title'],'reconstructed_not_crawled'])
    if args.schema:
        st,sd,sc=read_dump(args.schema,{'TABLES':['TABLE_SCHEMA','TABLE_NAME','ENGINE','TABLE_ROWS','DATA_LENGTH','INDEX_LENGTH'],'COLUMNS':['TABLE_SCHEMA','TABLE_NAME','COLUMN_NAME','COLUMN_TYPE']})
        metadata=[r for r in sd['TABLES'] if r.get('TABLE_SCHEMA')=='d278806_rvujjz']
        summary['information_schema']={'tables':len(st),'rows':sum(sc.values()),'shop_table_metadata_count':len(metadata),'shop_table_rows_estimated':sum(r['TABLE_ROWS'] or 0 for r in metadata),'shop_bytes':sum((r['DATA_LENGTH'] or 0)+(r['INDEX_LENGTH'] or 0) for r in metadata),'table_names_match':set(r['TABLE_NAME'] for r in metadata)==set(tables)}
    (args.out/'audit-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({k:summary[k] for k in ['tables','total_rows','product_active','products_with_combinations','orphan_checks','translated_name_equal_to_cs','combination_attribute_count','combination_defaults','empty_product_reference','category_status','information_schema'] if k in summary},ensure_ascii=True,indent=2))

if __name__=='__main__': main()

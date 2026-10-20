"""Read-only SQL whitelist for payment behavior; excludes secrets and contacts."""
import json
import re
from pathlib import Path
from datetime import datetime, timezone
import audit_dump

HERE = Path(__file__).resolve().parent
SAFE_CONFIG = {
    'DM_COMGATE_IDENT_PAYMENT', 'DM_COMGATE_PRE_AUTHORIZED', 'DM_COMGATE_REPEAT_PAYMENT_ACTIVE',
    'DM_COMGATE_TEST_ACTIVE', 'DM_COMGATE_MAIL_ACTIVE', 'DM_COMGATE_VALIDATION_STEP',
    'DM_COMGATE_STATUS_CREATED', 'DM_COMGATE_STATUS_PAID', 'DM_COMGATE_STATUS_PAID_NOT_STOCK',
    'DM_COMGATE_STATUS_CANCELED', 'DM_COMGATE_STATUS_ERROR', 'DM_COMGATE_STATUS_PRE_AUTHORIZED',
    'DM_COMGATE_STATUS_REFUND', 'DM_COMGATE_TYPE_CUSTOMER_MAIL',
    'DM_COD_ORDER_STATE', 'DM_COD_ROUNDING_ACTIVE', 'DM_COD_TYPE_FEE_ORDER',
    'DM_COD_FEE_ORDER_ID_PRODUCT', 'DM_COD_CARRIERS_ALLOWED', 'PS_TAX', 'PS_ORDER_RECALCULATE_SHIPPING',
}

def main():
    audit_dump.CONFIG = SAFE_CONFIG
    tables, rows, counts = audit_dump.read_dump(Path(r'C:\Users\Utrh\Downloads\d278806_rvujjz.sql'), {
        'configuration': ['name', 'value'],
        'cashondelivery_fee': ['id_fee', 'id_carrier', 'id_zone', 'id_type_fee', 'fee_price', 'fee_percent',
            'fee_limit', 'fee_top_limit', 'fee_free', 'allowed_currency', 'active', 'deleted'],
        'product': ['id_product', 'active', 'dm_cod_active', 'dm_comgate_active'],
        'product_shop': ['id_product', 'id_shop', 'dm_cod_active', 'dm_comgate_active'],
    })
    safe = {}
    for record in rows['configuration']:
        value = str(record.get('value', ''))
        if re.fullmatch(r'[0-9,; .+-]{1,150}', value) or value in {'order_detail', 'order_carrier', 'carrier', 'payment', 'module', 'eshop'}:
            safe[record['name']] = value
        else:
            safe[record['name']] = 'value_not_exported'
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(), 'source': 'supplied SQL dump; not live state',
        'configuration': safe, 'cod_fee_rows': rows['cashondelivery_fee'], 'product_flags': {}}
    for table in ['product', 'product_shop']:
        for flag in ['dm_cod_active', 'dm_comgate_active']:
            records = [r for r in rows[table] if flag in r]
            excluded = sorted(set(r['id_product'] for r in records if r[flag] == 0))
            result['product_flags'][table + '.' + flag] = {'rows_with_field': len(records),
                'disabled_product_ids': excluded}
    (HERE / 'evidence/payment-rules.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()

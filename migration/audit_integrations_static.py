"""Read-only, selected integration evidence. Never executes source PHP or SQL."""
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path
from zipfile import ZipFile
from audit_ftp import SOURCE_DEFAULT, stable_read

HERE = Path(__file__).resolve().parent
FILES = [
    'modules/bankwire/bankwire.php',
    'modules/bankwire/controllers/front/validation.php',
    'modules/shaim_dpdparcelshop/shaim_dpdparcelshop.php',
    'modules/shaim_dpdparcelshop/shaim_cron.php',
    'modules/shaim_dpdparcelshop/shaim_hledat_dpdparcelshop_ajax.php',
    'modules/shaim_recaptcha_v3/shaim_recaptcha_v3.php',
    'modules/shaim_recaptcha_v3/recaptchalib.php',
    'modules/shaim_recaptcha_v3/override/controllers/front/AuthController.php',
    'modules/shaim_recaptcha_v3/override/controllers/front/ContactController.php',
    'modules/shaim_cookies_eu/shaim_cookies_eu.php',
    'modules/shaim_cookies_eu/shaim_cookies_eu_ajax.php',
    'modules/shaim_cookies_eu/override/classes/Hook.php',
    'modules/usercentrics/usercentrics.php',
    'modules/packetery/packetery.php',
    'modules/packetery/AdminOrderPacketery.php',
    'modules/packetery/export.php',
    'modules/packetery/sql-install.php',
    'modules/mergado/mergado.php',
    'modules/mergado/cron.php',
    'modules/mergado/importPrices.php',
    'modules/mergado/src/Manager/FeedGeneratorManager.php',
    'modules/mergado/src/Service/ProductPriceImportService.php',
    'modules/mergado/override/classes/Product.php',
    'modules/mergado/override/controllers/front/OrderController.php',
    'modules/mergado/override/controllers/front/OrderOpcController.php',
]

def main():
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
              'scope': 'Selected stable module code; not runtime or FTP completeness',
              'files': []}
    contents = {}
    for rel in FILES:
        raw = stable_read(SOURCE_DEFAULT / rel)
        record = {'path': rel, 'stable_available': raw is not None}
        if raw is not None:
            record.update(bytes=len(raw), sha256=hashlib.sha256(raw).hexdigest())
            contents[rel] = raw.decode('utf-8', errors='replace')
        result['files'].append(record)
    ddl = contents.get('modules/packetery/sql-install.php', '')
    # Column identifiers only; never data values or whole SQL statements.
    result['packetery_schema_column_identifiers'] = sorted(set(re.findall(
        r'(?m)^\s*`([A-Za-z_][A-Za-z0-9_]*)`\s+(?:int|tinyint|bigint|varchar|text|decimal|float|double|datetime|timestamp|char)\b', ddl, re.I)))
    price_code = contents.get('modules/mergado/src/Service/ProductPriceImportService.php', '')
    result['mergado_price_import_tls_disabled_lines'] = [i for i, line in enumerate(price_code.splitlines(), 1)
        if re.search(r'CURLOPT_SSL_VERIFY(?:PEER|HOST)\s*,\s*0\s*\)', line)]
    with ZipFile(HERE / 'evidence/prestashop-1.6.1.13-reference.zip') as ref:
        name = next(n for n in ref.namelist() if n.endswith('/classes/Product.php'))
        core = ref.read(name).decode('utf-8', errors='replace')
    match = re.search(r'public\s+\$pack_stock_type\s*=\s*(\d+)\s*;', core)
    result['reference_product_pack_stock_type_default'] = int(match[1]) if match else None
    result['limitations'] = [
        'Packeta exportCsv observed; no source execution or shipment creation performed.',
        'Root override attribution and comparison are separately recorded in override-origins.json.',
        'Cron registration and actual enabled marketing/price-import settings require separate verification.',
    ]
    target = HERE / 'evidence/integration-code-audit.json'
    target.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({k: v for k, v in result.items() if k != 'files'}, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()

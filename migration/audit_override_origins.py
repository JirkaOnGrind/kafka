"""Static root/module override comparison. Never runs any shop source."""
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path
from audit_ftp import SOURCE_DEFAULT, stable_read

HERE = Path(__file__).resolve().parent
PAIRS = {
 'override/classes/Hook.php': 'modules/shaim_cookies_eu/override/classes/Hook.php',
 'override/classes/Product.php': 'modules/mergado/override/classes/Product.php',
 'override/classes/order/Order.php': 'modules/dm_cashondelivery/override/classes/order/Order.php',
 'override/controllers/front/AuthController.php': 'modules/shaim_recaptcha_v3/override/controllers/front/AuthController.php',
 'override/controllers/front/ContactController.php': 'modules/shaim_recaptcha_v3/override/controllers/front/ContactController.php',
 'override/controllers/front/OrderController.php': 'modules/mergado/override/controllers/front/OrderController.php',
 'override/controllers/front/OrderOpcController.php': 'modules/mergado/override/controllers/front/OrderOpcController.php',
}

def tokens(raw):
    code = raw.decode('utf-8', errors='replace')
    return re.sub(r"(?P<literal>'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\")|/\*.*?\*/|//[^\n]*|\#[^\n]*|\s+",
                  lambda m: m[0] if m.group('literal') else '', code, flags=re.S)

def main():
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
              'scope': 'Seven cached root overrides versus corresponding packaged candidates', 'pairs': []}
    for root, candidate in PAIRS.items():
        a, b = stable_read(SOURCE_DEFAULT / root), stable_read(SOURCE_DEFAULT / candidate)
        item = {'root_path': root, 'candidate_path': candidate,
                'root_stable_available': a is not None, 'candidate_stable_available': b is not None}
        if a is not None:
            item['root_sha256'] = hashlib.sha256(a).hexdigest()
            item['root_methods'] = re.findall(r'\bfunction\s+(\w+)\s*\(', a.decode('utf-8', errors='replace'))
        if b is not None:
            item['candidate_sha256'] = hashlib.sha256(b).hexdigest()
        if a is not None and b is not None:
            item['byte_identical'] = a == b
            item['same_after_comments_whitespace_removed'] = tokens(a) == tokens(b)
        result['pairs'].append(item)
    target = HERE / 'evidence/override-origins.json'
    target.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()

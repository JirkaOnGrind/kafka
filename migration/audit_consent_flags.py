"""Read selected configuration INSERTs, never execute SQL or export secrets/contacts."""
import json
import re
from datetime import datetime, timezone
from pathlib import Path
from audit_dump import tuples

HERE = Path(__file__).resolve().parent
DUMP = Path(r'C:\Users\Utrh\Downloads\d278806_rvujjz.sql')
FLAGS = {
    'shaim_cookies_eu_ostry_rezim', 'shaim_recaptcha_v3_register',
    'shaim_recaptcha_v3_contact_form', 'shaim_recaptcha_v3_login',
    'shaim_recaptcha_v3_newsletter', 'shaim_recaptcha_v3_bo_login',
    'USERCENTRICS_ENABLE_SDP',
}
MODULE_LISTS = {'shaim_cookies_eu_analyticke_moduly', 'shaim_cookies_eu_reklamni_moduly'}

def main():
    module_info = json.loads((HERE / 'evidence/ftp-audit.json').read_text(encoding='utf-8'))
    known_modules = {m['directory'] for m in module_info['modules'] if m.get('db_present')}
    result = {'observed_at_utc': datetime.now(timezone.utc).isoformat(),
              'source': 'supplied SQL dump; not runtime', 'flags': {}, 'module_lists': {},
              'usercentrics_setting_id_configuration_found': False}
    with DUMP.open(encoding='utf-8', errors='replace') as source:
        iterator = iter(source)
        for line in iterator:
            if not line.startswith('INSERT INTO `ejf6_configuration`'):
                continue
            statement = line
            while not statement.rstrip().endswith(';'):
                statement += next(iterator)
            match = re.match(r'INSERT INTO `ejf6_configuration`\s*\((.*?)\) VALUES\s*(.*)', statement, re.S)
            if not match:
                raise ValueError('Unsupported configuration INSERT format')
            columns = re.findall(r'`([^`]+)`', match[1])
            for values in tuples(match[2]):
                record = dict(zip(columns, values))
                name = record.get('name')
                if name in FLAGS:
                    value = str(record.get('value', ''))
                    result['flags'][name] = int(value) if value in {'0', '1'} else 'value_not_exported'
                elif name == 'USERCENTRICS_SETTING_ID':
                    result['usercentrics_setting_id_configuration_found'] = True
                    result['usercentrics_setting_id_nonempty'] = record.get('value') not in {None, ''}
                elif name in MODULE_LISTS:
                    value = str(record.get('value', ''))
                    hex_encoded = bool(re.fullmatch(r'0x(?:[0-9A-Fa-f]{2})*', value))
                    if hex_encoded:
                        value = bytes.fromhex(value[2:]).decode('utf-8', errors='replace')
                    # Only installed, public module identifiers; never arbitrary string values.
                    candidates = set(re.findall(r's:\d+:"([a-z][a-z0-9_]+)";', value))
                    result['module_lists'][name] = {
                        'known_module_identifiers': sorted(candidates & known_modules),
                        'serialized_empty_array': value == 'a:0:{}',
                        'empty_or_null': record.get('value') is None or value == '',
                        'hex_encoded': hex_encoded,
                        'serialized_false': value == 'b:0;',
                        'serialized_null': value == 'N;',
                        'value_type': type(record.get('value')).__name__,
                        'value_length': len(value),
                        'storage_format': ('php_serialized_array' if re.match(r'^a:\d+:\{', value)
                            else 'php_serialized_false' if value == 'b:0;' else 'not_classified'),
                        'interpretation': ('No selected modules in this stored list' if value in {'b:0;', 'a:0:{}'}
                            else 'Identifier scan only; empty scan does not prove no selected modules'),
                    }
    result['flags_not_found'] = sorted(FLAGS - result['flags'].keys())
    (HERE / 'evidence/consent-flags.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()

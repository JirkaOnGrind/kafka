"""Display selected static PHP regions with literal values masked; never execute PHP."""
import argparse
import re
from pathlib import Path
from audit_ftp import SOURCE_DEFAULT, stable_read

def php_regions_only(code):
    lexical = re.compile(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"|/\*.*?\*/|//[^\n]*|(?m:^[ \t]*#[^\n]*)|(?P<close>\?>)", re.S)
    chunks, cursor = [], 0
    while opening := re.search(r'<\?(?:php\b|=)', code[cursor:], re.I):
        start = cursor + opening.start()
        body_start = cursor + opening.end()
        chunks.append('\n' * code[cursor:start].count('\n'))
        close = next((token for token in lexical.finditer(code, body_start) if token.group('close')), None)
        end = close.end() if close else len(code)
        chunks.append(code[start:end])
        cursor = end
        if close is None:
            break
    chunks.append('\n' * code[cursor:].count('\n'))
    return ''.join(chunks)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('relative_path')
    parser.add_argument('--methods', default='')
    parser.add_argument('--start', type=int, default=1)
    parser.add_argument('--end', type=int, default=160)
    args = parser.parse_args()
    path = (SOURCE_DEFAULT / args.relative_path).resolve()
    if not path.is_relative_to(SOURCE_DEFAULT.resolve()) or path.suffix != '.php':
        raise ValueError('Only source PHP below the read-only FTP root is allowed')
    if path.name.lower().endswith('.appconfig.php'):
        raise ValueError('Credential configuration excluded')
    if any(part.lower() in {'log', 'logs', 'sessions', 'tmp', 'config', 'crt'} for part in path.relative_to(SOURCE_DEFAULT).parts):
        raise ValueError('Sensitive or operational directory excluded')
    raw = stable_read(path)
    if raw is None:
        print('Unstable or not yet available')
        return
    code = raw.decode('utf-8', errors='replace')
    # A PHP extension may hide plaintext credentials after an exit/closing tag.
    # Only retain PHP regions, lexing literals/comments before closing tags.
    code = php_regions_only(code)
    def mask(match):
        if not match.group('literal'):
            return '\n' * match[0].count('\n')
        literal = match[0][1:-1]
        prefix = code[max(0, match.start() - 100):match.start()]
        suffix = code[match.end():match.end() + 8]
        if re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]{0,70}', literal):
            if prefix.rstrip().endswith('[') and suffix.lstrip().startswith(']'):
                return match[0]
            if re.search(r'Configuration::(?:get|updateValue|deleteByName)\(\s*$', prefix):
                return match[0]
            if re.search(r'registerHook\(\s*$', prefix):
                return match[0]
            if re.search(r'Tools::getValue\(\s*$', prefix) or suffix.lstrip().startswith('=>'):
                return match[0]
            if literal.startswith(('dm_comgate', 'dm_cod', 'dm_cashondelivery')):
                return match[0]
            if literal in {'PAID', 'PENDING', 'CANCELLED', 'AUTHORIZED', 'OK', 'CZK', 'EUR'}:
                return match[0]
        return match[0][0] + '[literal masked]' + match[0][-1] + '\n' * match[0].count('\n')
    # Lex strings before comments, so comment-like glob paths inside literals stay masked.
    code = re.sub(r"(?P<literal>'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\")|/\*.*?\*/|//[^\n]*|(?m:^[ \t]*#[^\n]*)", mask, code, flags=re.S)
    lines = code.splitlines()
    requested = set(filter(None, args.methods.split(',')))
    regions = []
    if requested:
        starts = [(i, m[1]) for i, line in enumerate(lines)
                  if (m := re.search(r'\bfunction\s+(\w+)\s*\(', line))]
        for pos, (start, name) in enumerate(starts):
            if name in requested:
                regions.append((start, starts[pos + 1][0] if pos + 1 < len(starts) else len(lines)))
    else:
        regions = [(args.start - 1, min(args.end, len(lines)))]
    for start, end in regions:
        for i in range(start, end):
            if lines[i].strip():
                print(f'{i + 1}: {lines[i]}')

if __name__ == '__main__':
    main()

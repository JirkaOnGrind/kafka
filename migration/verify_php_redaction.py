"""Synthetic regression checks only; does not read or execute any shop files."""
from read_php_safely import php_regions_only

cases = [
    ('<?php exit;?>\nPRIVATE_OUTSIDE_MARKER', 'PRIVATE_OUTSIDE_MARKER', 'exit'),
    ('PRIVATE_PREFIX\n<?php echo "?> inside literal"; ?>\nPRIVATE_SUFFIX', 'PRIVATE_SUFFIX', 'inside literal'),
    ('<?php /* ?> inside comment */ echo 1; ?>PRIVATE_TAIL<?php echo 2;', 'PRIVATE_TAIL', 'echo 2'),
]
for original, excluded, retained in cases:
    result = php_regions_only(original)
    assert excluded not in result
    assert retained in result
    assert result.count('\n') == original.count('\n')
assert 'PRIVATE_PREFIX' not in php_regions_only(cases[1][0])
print('PASS: synthetic outside-PHP redaction, literal/comment closing tags, multiple regions, line count')

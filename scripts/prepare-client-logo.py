"""Extract the approved client's PDF paths into a tightly cropped transparent SVG."""
from pathlib import Path
import re
import xml.etree.ElementTree as ET
from pypdf import PdfReader
from pypdf.generic import ContentStream

ROOT = Path(__file__).resolve().parents[1]
SOURCE = Path.home() / 'Downloads' / 'KAFKA MONSTER ČERNÉ LOGO (1).pdf'
reader = PdfReader(SOURCE)
page = reader.pages[0]
height = float(page.mediabox.height)
# Existing approved vector uses the same paths and calibrated RGB colors.
reference = ET.parse(ROOT / 'assets/kafka-monster-logo.svg').getroot()
styles = list(reference.findall('{http://www.w3.org/2000/svg}path'))
paths, commands = [], []
for operands, operator in ContentStream(page.get_contents(), reader).operations:
    if operator in (b'm', b'l', b'c'):
        values = [float(v) for v in operands]
        values[1::2] = [height - v for v in values[1::2]]
        commands.append(operator.decode().upper() + ' ' + ' '.join(f'{v:.4f}' for v in values))
    elif operator == b'h':
        commands.append('Z')
    elif operator in (b'f*', b'b*', b'b'):
        if operator in (b'b*', b'b') and commands[-1] != 'Z':
            commands.append('Z')
        paths.append(' '.join(commands))
        commands = []
assert len(paths) == len(styles) == 15
for path, style in zip(paths, styles):
    original = [float(v) for v in re.findall(r'-?\d+(?:\.\d+)?', style.attrib['d'])]
    extracted = [float(v) for v in re.findall(r'-?\d+(?:\.\d+)?', path)]
    assert len(original) == len(extracted)
    assert all(abs(a-b) < .002 for a,b in zip(original, extracted)), 'PDF differs from approved vector'
svg = ET.Element('svg', {'xmlns': 'http://www.w3.org/2000/svg', 'viewBox': '79 187 294 219', 'role': 'img', 'aria-labelledby': 'title'})
ET.SubElement(svg, 'title', {'id': 'title'}).text = 'Kafka Monster'
# First path is the rectangular background, not part of the logo artwork.
for path, style in zip(paths[1:], styles[1:]):
    attrs = dict(style.attrib)
    attrs['d'] = path
    ET.SubElement(svg, 'path', attrs)
output = ET.tostring(svg, encoding='unicode') + '\n'
for target in ('assets/kafka-monster-client-logo.svg', 'prestashop/themes/kafka/assets/img/kafka-monster-client-logo.svg'):
    (ROOT / target).write_text(output, encoding='utf-8')
print('Extracted 14 original vector paths; removed background; preserved calibrated colors.')

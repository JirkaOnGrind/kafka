#!/usr/bin/env python3
"""Fail when a referenced product image is too small for the product detail.

Product photography is rendered in a large square frame, so every referenced
asset must have at least 800 pixels on both axes. Run this check after adding or
replacing catalog images:

    python scripts/check-product-images.py
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
MIN_PRODUCT_EDGE = 800
SOURCE_FILES = (ROOT / "graficky_navrh/catalog-data.js", ROOT / "graficky_navrh/product.html", ROOT / "graficky_navrh/product.js")
IMAGE_PATTERN = re.compile(r"(?:(?:\.\./|\./))?assets/products/[A-Za-z0-9_.-]+")


def referenced_images() -> list[Path]:
    references: set[str] = set()
    for source in SOURCE_FILES:
        references.update(IMAGE_PATTERN.findall(source.read_text(encoding="utf-8")))
    return sorted(ROOT / reference.removeprefix("../").removeprefix("./") for reference in references)


def main() -> int:
    failures: list[str] = []
    images = referenced_images()
    for path in images:
        if not path.is_file():
            failures.append(f"missing: {path.relative_to(ROOT)}")
            continue
        with Image.open(path) as image:
            width, height = image.size
        label = path.relative_to(ROOT)
        print(f"{label}: {width}x{height}")
        if width < MIN_PRODUCT_EDGE or height < MIN_PRODUCT_EDGE:
            failures.append(
                f"too small: {label} is {width}x{height}; "
                f"minimum is {MIN_PRODUCT_EDGE}x{MIN_PRODUCT_EDGE}"
            )

    if failures:
        print("\nProduct image quality check failed:", file=sys.stderr)
        for failure in failures:
            print(f"- {failure}", file=sys.stderr)
        return 1

    print(f"Product image quality check passed for {len(images)} referenced assets.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

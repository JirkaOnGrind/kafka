#!/usr/bin/env python3
"""Batch-crop product photography and place it on a uniform aspect-ratio canvas.

The crop is derived from the image border colour, so light studio backgrounds do
not need to be perfectly white. Example:

    python scripts/process-product-images.py raw/*.jpg --output assets/products
    python scripts/process-product-images.py black.png red.png --aspect 1:1 --format webp
"""

from __future__ import annotations

import argparse
import glob
import math
import statistics
from pathlib import Path
from typing import Iterable

from PIL import Image, ImageChops, ImageFilter


def parse_aspect(value: str) -> tuple[int, int]:
    try:
        width, height = (int(part) for part in value.split(":"))
    except (TypeError, ValueError) as exc:
        raise argparse.ArgumentTypeError("aspect must look like 1:1 or 4:5") from exc
    if width <= 0 or height <= 0:
        raise argparse.ArgumentTypeError("aspect values must be positive")
    return width, height


def expand_inputs(patterns: Iterable[str]) -> list[Path]:
    files: list[Path] = []
    for pattern in patterns:
        matches = [Path(item) for item in glob.glob(pattern)]
        files.extend(matches or [Path(pattern)])
    unique = dict.fromkeys(path.resolve() for path in files if path.is_file())
    return list(unique)


def estimate_background(image: Image.Image) -> tuple[int, int, int]:
    """Return the median RGB colour sampled from a thin strip on all edges."""
    rgb = image.convert("RGB")
    width, height = rgb.size
    strip = max(1, round(min(width, height) * 0.025))
    pixels = []
    for edge in (
        rgb.crop((0, 0, width, strip)),
        rgb.crop((0, height - strip, width, height)),
        rgb.crop((0, strip, strip, height - strip)),
        rgb.crop((width - strip, strip, width, height - strip)),
    ):
        pixels.extend(edge.get_flattened_data())
    return tuple(round(statistics.median(pixel[channel] for pixel in pixels)) for channel in range(3))


def subject_bbox(image: Image.Image, threshold: int) -> tuple[int, int, int, int]:
    rgb = image.convert("RGB")
    background = Image.new("RGB", rgb.size, estimate_background(rgb))
    difference = ImageChops.difference(rgb, background).convert("L")
    # Close tiny gaps and discard low-level JPEG noise before reading the bounds.
    mask = difference.filter(ImageFilter.MedianFilter(5)).point(
        lambda value: 255 if value >= threshold else 0
    )
    # Find the largest connected component on a small working mask. This rejects
    # dust, colour chips and stray editor artefacts without depending on OpenCV.
    scale = min(1.0, 240 / max(mask.size))
    working_size = (max(1, round(mask.width * scale)), max(1, round(mask.height * scale)))
    working = mask.resize(working_size, Image.Resampling.NEAREST)
    pixels = working.load()
    visited: set[tuple[int, int]] = set()
    largest: tuple[int, tuple[int, int, int, int]] | None = None
    for y in range(working.height):
        for x in range(working.width):
            if not pixels[x, y] or (x, y) in visited:
                continue
            stack = [(x, y)]
            visited.add((x, y))
            count = 0
            left = right = x
            top = bottom = y
            while stack:
                current_x, current_y = stack.pop()
                count += 1
                left, right = min(left, current_x), max(right, current_x)
                top, bottom = min(top, current_y), max(bottom, current_y)
                for next_x, next_y in (
                    (current_x - 1, current_y), (current_x + 1, current_y),
                    (current_x, current_y - 1), (current_x, current_y + 1),
                ):
                    if (0 <= next_x < working.width and 0 <= next_y < working.height
                            and pixels[next_x, next_y] and (next_x, next_y) not in visited):
                        visited.add((next_x, next_y))
                        stack.append((next_x, next_y))
            candidate = (count, (left, top, right + 1, bottom + 1))
            if largest is None or candidate[0] > largest[0]:
                largest = candidate
    if not largest:
        return 0, 0, rgb.width, rgb.height
    left, top, right, bottom = largest[1]
    inverse = 1 / scale
    return (
        max(0, math.floor(left * inverse)),
        max(0, math.floor(top * inverse)),
        min(rgb.width, math.ceil(right * inverse)),
        min(rgb.height, math.ceil(bottom * inverse)),
    )


def add_crop_margin(
    bbox: tuple[int, int, int, int], image_size: tuple[int, int], margin: float
) -> tuple[int, int, int, int]:
    left, top, right, bottom = bbox
    extra_x = round((right - left) * margin)
    extra_y = round((bottom - top) * margin)
    width, height = image_size
    return (
        max(0, left - extra_x),
        max(0, top - extra_y),
        min(width, right + extra_x),
        min(height, bottom + extra_y),
    )


def canvas_size(aspect: tuple[int, int], max_size: int) -> tuple[int, int]:
    aspect_width, aspect_height = aspect
    scale = max_size / max(aspect_width, aspect_height)
    return round(aspect_width * scale), round(aspect_height * scale)


def process_image(
    source: Path,
    destination: Path,
    aspect: tuple[int, int],
    max_size: int,
    padding: float,
    crop_margin: float,
    threshold: int,
    background: tuple[int, int, int],
    output_format: str,
    quality: int,
) -> tuple[Path, tuple[int, int, int, int]]:
    with Image.open(source) as original:
        image = original.convert("RGB")
        bbox = add_crop_margin(subject_bbox(image, threshold), image.size, crop_margin)
        subject = image.crop(bbox)

        target_width, target_height = canvas_size(aspect, max_size)
        available_width = max(1, round(target_width * (1 - 2 * padding)))
        available_height = max(1, round(target_height * (1 - 2 * padding)))
        scale = min(available_width / subject.width, available_height / subject.height)
        subject = subject.resize(
            (max(1, round(subject.width * scale)), max(1, round(subject.height * scale))),
            Image.Resampling.LANCZOS,
        )

        canvas = Image.new("RGB", (target_width, target_height), background)
        canvas.paste(
            subject,
            ((target_width - subject.width) // 2, (target_height - subject.height) // 2),
        )
        destination.parent.mkdir(parents=True, exist_ok=True)
        save_options = {"optimize": True}
        if output_format in {"webp", "jpeg"}:
            save_options["quality"] = quality
        if output_format == "webp":
            save_options["method"] = 6
        canvas.save(destination, format=output_format.upper(), **save_options)
        return destination, bbox


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("inputs", nargs="+", help="Image files or glob patterns")
    parser.add_argument("--output", type=Path, default=Path("assets/products"))
    parser.add_argument("--aspect", type=parse_aspect, default=(1, 1))
    parser.add_argument("--max-size", type=int, default=1200)
    parser.add_argument("--padding", type=float, default=0.06, help="Canvas padding per edge")
    parser.add_argument("--crop-margin", type=float, default=0.025)
    parser.add_argument("--threshold", type=int, default=20, help="Background difference threshold")
    parser.add_argument("--background", default="ffffff", help="Six-digit canvas colour")
    parser.add_argument("--format", choices=("webp", "png", "jpeg"), default="webp")
    parser.add_argument("--quality", type=int, default=88)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    sources = expand_inputs(args.inputs)
    if not sources:
        raise SystemExit("No input images found")
    if not 0 <= args.padding < 0.5:
        raise SystemExit("--padding must be between 0 and 0.5")
    try:
        background = tuple(bytes.fromhex(args.background))
    except ValueError as exc:
        raise SystemExit("--background must be a six-digit hex colour") from exc
    if len(background) != 3:
        raise SystemExit("--background must be a six-digit hex colour")

    extension = "jpg" if args.format == "jpeg" else args.format
    for source in sources:
        destination = args.output / f"{source.stem}.{extension}"
        output, bbox = process_image(
            source=source,
            destination=destination,
            aspect=args.aspect,
            max_size=args.max_size,
            padding=args.padding,
            crop_margin=args.crop_margin,
            threshold=args.threshold,
            background=background,
            output_format=args.format,
            quality=args.quality,
        )
        print(f"{source.name}: crop={bbox} -> {output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

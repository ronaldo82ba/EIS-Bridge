#!/usr/bin/env python3
"""Generate brand PNG assets for EIS Bridge marketing site."""

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

BRAND_DIR = Path(__file__).resolve().parent.parent / "assets" / "brand"
EIS_BLUE = (0, 87, 217)
GRAPHITE = (26, 26, 26)
WHITE = (255, 255, 255)
CLOUD = (247, 249, 250)


def load_font(size: int, bold: bool = True) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
        if bold
        else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    ]
    for path in candidates:
        try:
            return ImageFont.truetype(path, size)
        except OSError:
            continue
    return ImageFont.load_default()


def draw_wordmark(draw: ImageDraw.ImageDraw, x: int, y: int, scale: float = 1.0) -> None:
    font = load_font(int(48 * scale), bold=True)
    eis = "EIS"
    bridge = " Bridge"
    eis_w = draw.textlength(eis, font=font)
    draw.text((x, y), eis, fill=EIS_BLUE, font=font)
    draw.text((x + eis_w, y), bridge, fill=GRAPHITE, font=font)


def gen_apple_touch_icon() -> None:
    size = 180
    img = Image.new("RGB", (size, size), EIS_BLUE)
    draw = ImageDraw.Draw(img)
    font = load_font(72, bold=True)
    text = "EB"
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text(((size - tw) / 2 - bbox[0], (size - th) / 2 - bbox[1]), text, fill=WHITE, font=font)
    img.save(BRAND_DIR / "apple-touch-icon.png", "PNG", optimize=True)


def gen_favicon_32() -> None:
    size = 32
    img = Image.new("RGB", (size, size), EIS_BLUE)
    draw = ImageDraw.Draw(img)
    font = load_font(14, bold=True)
    text = "EB"
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text(((size - tw) / 2 - bbox[0], (size - th) / 2 - bbox[1]), text, fill=WHITE, font=font)
    png_path = BRAND_DIR / "favicon-32.png"
    img.save(png_path, "PNG", optimize=True)
    img.save(BRAND_DIR / "favicon.ico", format="ICO", sizes=[(32, 32)])


def gen_og_image() -> None:
    w, h = 1200, 630
    img = Image.new("RGB", (w, h), CLOUD)
    draw = ImageDraw.Draw(img)

    # Subtle gradient band
    for i in range(h):
        t = i / h
        r = int(CLOUD[0] * (1 - t * 0.05) + 255 * (t * 0.05))
        g = int(CLOUD[1] * (1 - t * 0.05) + 255 * (t * 0.05))
        b = int(CLOUD[2] * (1 - t * 0.05) + 255 * (t * 0.05))
        draw.line([(0, i), (w, i)], fill=(r, g, b))

    draw.rectangle([(0, 0), (w, 8)], fill=EIS_BLUE)

    draw_wordmark(draw, 80, 200, scale=1.35)

    tagline_font = load_font(36, bold=False)
    draw.text((82, 310), "One Bridge. Any POS. BIR EIS Transmission.", fill=GRAPHITE, font=tagline_font)

    sub_font = load_font(24, bold=False)
    draw.text(
        (82, 380),
        "Universal e-invoicing middleware for POS vendors and merchants",
        fill=(46, 58, 69),
        font=sub_font,
    )

    draw.rectangle([(80, 520), (320, 524)], fill=(0, 168, 168))

    img.save(BRAND_DIR / "og-image.png", "PNG", optimize=True)


def main() -> None:
    BRAND_DIR.mkdir(parents=True, exist_ok=True)
    gen_apple_touch_icon()
    gen_favicon_32()
    gen_og_image()
    print(f"Generated PNG brand assets in {BRAND_DIR}")


if __name__ == "__main__":
    main()

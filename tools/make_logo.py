"""Färbt das Original-Logo (weiss auf schwarz) golden ein und stellt es frei → assets/logo-cooltourszene-gold.png.
Braucht Pillow (pip install pillow)."""
import pathlib
from PIL import Image, ImageChops

root = pathlib.Path(__file__).resolve().parent.parent
src = Image.open(root / "assets/logo-cooltourszene-original.jpg").convert("L")
# Helligkeit = Deckkraft; JPEG-Rauschen im schwarzen Grund wegschneiden
alpha = src.point(lambda v: 0 if v < 40 else min(255, round((v - 40) * 255 / 200)))
alpha = alpha.crop(alpha.getbbox())
W = 1200                                    # reicht für 300 dpi bei voller Kartenbreite
alpha = alpha.resize((W, round(W * alpha.height / alpha.width)), Image.LANCZOS)
w, h = alpha.size
# gleicher Goldverlauf wie im Studio (PAINTS.gold), diagonal
stops = ["#8a6424", "#f7e7b0", "#c9a04a", "#fff3cf", "#9c7530"]
rgb = [tuple(int(c[i:i + 2], 16) for i in (1, 3, 5)) for c in stops]
grad = Image.new("RGB", (w, h))
px = grad.load()
for x in range(w):
    for y in range(h):
        t = (x / w * 0.85 + y / h * 0.15) * (len(rgb) - 1)
        i = min(int(t), len(rgb) - 2); f = t - i
        px[x, y] = tuple(round(a + (b - a) * f) for a, b in zip(rgb[i], rgb[i + 1]))
out = grad.convert("RGBA"); out.putalpha(alpha)
out.save(root / "assets/logo-cooltourszene-gold.png", optimize=True)
print("ok", out.size)

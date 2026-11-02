"""Erzeugt kartenstudio.ico (Programmsymbol) ohne Zusatzpakete: goldumrandete Karte mit Chip und Schriftzeilen."""
import pathlib, struct, zlib

S = 256   # Grundgrösse
AA = 3    # Kantenglättung (Unterabtastung je Achse)

def inside_rrect(x, y, x0, y0, x1, y1, r):
    if x < x0 or x > x1 or y < y0 or y > y1:
        return False
    cx = min(max(x, x0 + r), x1 - r)
    cy = min(max(y, y0 + r), y1 - r)
    return (x - cx) ** 2 + (y - cy) ** 2 <= r * r

GOLD_A, GOLD_B = (247, 231, 176), (176, 132, 56)
def gold(t):
    return tuple(round(a + (b - a) * t) for a, b in zip(GOLD_A, GOLD_B))

def sample(x, y):
    """Farbe (r, g, b, a) an Punkt x, y in 0..256."""
    card = (14, 66, 242, 190, 26)                     # Karte im Format ca. 85,6 : 54
    if not inside_rrect(x, y, *card):
        return (0, 0, 0, 0)
    t = (x + y) / (2 * S)
    if not inside_rrect(x, y, 22, 74, 234, 182, 19):  # Goldrahmen
        return gold(t) + (255,)
    if inside_rrect(x, y, 44, 98, 92, 136, 7):        # Chip
        if abs(y - 117) < 1.6 or abs(x - 68) < 1.6:
            return (122, 90, 32, 255)
        return gold(t) + (255,)
    for (x0, y0, x1, y1) in ((44, 152, 160, 162), (176, 152, 212, 162)):  # Name / Nummer
        if inside_rrect(x, y, x0, y0, x1, y1, 5):
            return (239, 232, 218, 255)
    if inside_rrect(x, y, 150, 98, 212, 136, 6):      # QR-Andeutung
        cx, cy = int((x - 150) // 7.75), int((y - 98) // 9.5)
        if (cx * 7 + cy * 3 + cx * cy) % 3 != 1:
            return (239, 232, 218, 255)
        return (37, 34, 29, 255)
    g = 1 - t * 0.6                                    # dunkler Kartengrund
    return (round(37 * g), round(34 * g), round(29 * g), 255)

def render():
    px = []
    for j in range(S):
        row = []
        for i in range(S):
            acc = [0, 0, 0, 0]
            for sj in range(AA):
                for si in range(AA):
                    r, g, b, a = sample(i + (si + .5) / AA, j + (sj + .5) / AA)
                    acc[0] += r * a; acc[1] += g * a; acc[2] += b * a; acc[3] += a
            n = AA * AA
            a = acc[3]
            row.append((0, 0, 0, 0) if a == 0 else (round(acc[0] / a), round(acc[1] / a), round(acc[2] / a), round(a / n)))
        px.append(row)
    return px

def shrink(px, size):
    f = S // size
    out = []
    for j in range(size):
        row = []
        for i in range(size):
            acc = [0, 0, 0, 0]
            for y in range(j * f, j * f + f):
                for x in range(i * f, i * f + f):
                    r, g, b, a = px[y][x]
                    acc[0] += r * a; acc[1] += g * a; acc[2] += b * a; acc[3] += a
            a = acc[3]
            row.append((0, 0, 0, 0) if a == 0 else (round(acc[0] / a), round(acc[1] / a), round(acc[2] / a), round(a / (f * f))))
        out.append(row)
    return out

def png(px):
    h, w = len(px), len(px[0])
    raw = b"".join(b"\0" + bytes(c for p in row for c in p) for row in px)
    chunk = lambda t, d: struct.pack(">I", len(d)) + t + d + struct.pack(">I", zlib.crc32(t + d) & 0xffffffff)
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 6, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw, 9)) + chunk(b"IEND", b"")

def main():
    big = render()
    images = [(s, png(big if s == S else shrink(big, s))) for s in (16, 32, 48, 64, 128, 256)]
    head = struct.pack("<HHH", 0, 1, len(images))
    off = 6 + 16 * len(images)
    dirs, data = b"", b""
    for s, d in images:
        dirs += struct.pack("<BBBBHHII", s % 256, s % 256, 0, 0, 1, 32, len(d), off + len(data))
        data += d
    out = pathlib.Path(__file__).resolve().parent.parent / "kartenstudio.ico"
    out.write_bytes(head + dirs + data)
    print("ok", out, len(head + dirs + data), "Bytes")

if __name__ == "__main__":
    main()

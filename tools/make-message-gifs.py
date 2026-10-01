"""
Builds the built-in GIF stickers for Messages: assets/gifs/*.gif + assets/gifs/gifs.json.

Every sticker is drawn here in code (shapes + text), so the company owns them
outright. Re-run after editing a sticker:   python tools/make-message-gifs.py
Needs Pillow and the Windows Segoe UI fonts.

HR can also drop any other .gif they have the rights to into assets/gifs/;
it is picked up automatically (title from the file name, or add it to gifs.json).
"""
import json
import math
import os
import random

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'assets', 'gifs')
FONT_BLACK = 'C:/Windows/Fonts/seguibl.ttf'    # Segoe UI Black
FONT_BOLD = 'C:/Windows/Fonts/segoeuib.ttf'

W, H = 240, 160          # output size
S = 2                    # supersampling: drawn at 2x, scaled down = smooth edges
FRAMES = 20
MS = 70                  # per frame
TAU = math.tau

# ------------------------------------------------------------------ drawing helpers


def hexrgb(c):
    c = c.lstrip('#')
    return tuple(int(c[i:i + 2], 16) for i in (0, 2, 4))


def mix(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))


def gradient(c1, c2, diagonal=True):
    a, b = hexrgb(c1), hexrgb(c2)
    img = Image.new('RGB', (W * S, H * S))
    px = img.load()
    for y in range(H * S):
        for x in range(0, W * S):
            t = ((x / (W * S)) * 0.45 + (y / (H * S)) * 0.55) if diagonal else y / (H * S)
            px[x, y] = mix(a, b, t)
    return img


_bg_cache = {}


def bg(c1, c2, diagonal=True):
    key = (c1, c2, diagonal)
    if key not in _bg_cache:
        _bg_cache[key] = gradient(c1, c2, diagonal)
    return _bg_cache[key].copy().convert('RGBA')


def font(size, path=FONT_BLACK):
    return ImageFont.truetype(path, int(size * S))


def text_layer(txt, size, fill='#ffffff', stroke='#00000000', stroke_w=0, shadow=True, path=FONT_BLACK):
    """Text on its own transparent layer (tightly cropped), with a soft drop shadow."""
    f = font(size, path)
    l, t, r, b = f.getbbox(txt, stroke_width=int(stroke_w * S))
    pad = int(8 * S)
    img = Image.new('RGBA', (r - l + pad * 2, b - t + pad * 2), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if shadow:
        d.text((pad - l + 2 * S, pad - t + 3 * S), txt, font=f, fill=(0, 0, 0, 70),
               stroke_width=int(stroke_w * S), stroke_fill=(0, 0, 0, 70))
    d.text((pad - l, pad - t), txt, font=f, fill=fill, stroke_width=int(stroke_w * S), stroke_fill=stroke)
    return img


def fit_size(txt, max_w, start, path=FONT_BLACK):
    size = start
    while size > 10:
        l, _, r, _ = font(size, path).getbbox(txt)
        if (r - l) / S <= max_w:
            return size
        size -= 1
    return size


def paste(canvas, layer, cx, cy, scale=1.0, angle=0.0):
    """Paste a layer centred at (cx, cy) in output pixels, scaled and rotated."""
    if scale != 1.0:
        layer = layer.resize((max(1, int(layer.width * scale)), max(1, int(layer.height * scale))), Image.LANCZOS)
    if angle:
        layer = layer.rotate(angle, resample=Image.BICUBIC, expand=True)
    canvas.alpha_composite(layer, (int(cx * S - layer.width / 2), int(cy * S - layer.height / 2)))


def title(canvas, txt, cy=80, size=44, fill='#ffffff', stroke='#00000000', stroke_w=0, scale=1.0, angle=0.0, dx=0.0, max_w=208):
    size = fit_size(txt, max_w, size)
    paste(canvas, text_layer(txt, size, fill, stroke, stroke_w), W / 2 + dx, cy, scale, angle)


def heart_points(cx, cy, r):
    pts = []
    for i in range(40):
        a = TAU * i / 40
        x = 16 * math.sin(a) ** 3
        y = 13 * math.cos(a) - 5 * math.cos(2 * a) - 2 * math.cos(3 * a) - math.cos(4 * a)
        pts.append(((cx + x * r / 16) * S, (cy - y * r / 16) * S))
    return pts


def star_points(cx, cy, r, rot=0.0, inner=0.45):
    pts = []
    for i in range(10):
        a = rot - math.pi / 2 + math.pi * i / 5
        rr = r if i % 2 == 0 else r * inner
        pts.append(((cx + rr * math.cos(a)) * S, (cy + rr * math.sin(a)) * S))
    return pts


def ellipse(d, cx, cy, rx, ry, fill, outline=None, width=0):
    d.ellipse([(cx - rx) * S, (cy - ry) * S, (cx + rx) * S, (cy + ry) * S], fill=fill, outline=outline, width=int(width * S))


def line(d, pts, fill, width):
    d.line([(x * S, y * S) for x, y in pts], fill=fill, width=int(width * S), joint='curve')
    for x, y in (pts[0], pts[-1]):     # round caps
        ellipse(d, x, y, width / 2, width / 2, fill)


def overlay(canvas):
    return ImageDraw.Draw(canvas, 'RGBA')


def wave_text(canvas, txt, t, cy=80, size=44, amp=7, fill='#ffffff', stroke='#00000000', stroke_w=0, max_w=210):
    """Letters bobbing one after another (a "wave")."""
    size = fit_size(txt, max_w, size)
    f = font(size)
    widths = [(f.getlength(ch) / S) for ch in txt]
    x = W / 2 - sum(widths) / 2
    for i, ch in enumerate(txt):
        if ch != ' ':
            y = cy - amp * max(0.0, math.sin(TAU * (t - i / (len(txt) * 1.6))))
            paste(canvas, text_layer(ch, size, fill, stroke, stroke_w), x + widths[i] / 2, y)
        x += widths[i]


def seeded(seed, n, fn):
    rnd = random.Random(seed)
    return [fn(rnd) for _ in range(n)]


CONFETTI = ['#ffd43b', '#ff6b6b', '#4dabf7', '#69db7c', '#f783ac', '#ffffff', '#b197fc']


def confetti(canvas, t, seed=1, n=26, colors=CONFETTI):
    d = overlay(canvas)
    for p in seeded(seed, n, lambda r: (r.random() * W, r.random(), r.choice(colors), r.random(), r.uniform(4, 7))):
        x0, phase, c, spin, size = p
        y = ((phase + t) % 1.0) * (H + 20) - 10
        x = x0 + 6 * math.sin(TAU * (t * 2 + phase))
        a = TAU * (spin + t * 2)
        w, hgt = size, size * 0.45 * abs(math.cos(a)) + 1
        d.rectangle([(x - w / 2) * S, (y - hgt / 2) * S, (x + w / 2) * S, (y + hgt / 2) * S], fill=c)


def rising_hearts(canvas, t, seed=2, n=9, color='#ffffff', alpha=170):
    d = overlay(canvas)
    rgb = hexrgb(color)
    for x0, phase, r in seeded(seed, n, lambda r: (r.uniform(12, W - 12), r.random(), r.uniform(5, 10))):
        p = (phase + t) % 1.0
        y = H + 12 - p * (H + 30)
        x = x0 + 5 * math.sin(TAU * (p * 2))
        a = int(alpha * (1 - p))
        d.polygon(heart_points(x, y, r), fill=rgb + (a,))


def twinkles(canvas, t, spots, color='#ffffff'):
    d = overlay(canvas)
    rgb = hexrgb(color)
    for i, (x, y, r) in enumerate(spots):
        k = 0.5 + 0.5 * math.sin(TAU * (t + i / len(spots)))
        d.polygon(star_points(x, y, r * (0.5 + 0.6 * k), rot=TAU * t * 0.25, inner=0.4), fill=rgb + (int(120 + 135 * k),))


# ------------------------------------------------------------------ the stickers  (t = 0..1, loops)


def thank_you(t):
    c = bg('#ff6a88', '#ff9a5a')
    rising_hearts(c, t)
    title(c, 'Thank you!', size=46, scale=1 + 0.05 * math.sin(TAU * t))
    return c


def salamat(t):
    c = bg('#11998e', '#38ef7d')
    rising_hearts(c, t, seed=5, color='#fff3bf')
    title(c, 'Salamat!', size=50, scale=1 + 0.05 * math.sin(TAU * t))
    return c


def good_job(t):
    c = bg('#4776e6', '#8e54e9')
    twinkles(c, t, [(28, 30, 11), (210, 34, 9), (34, 128, 8), (206, 126, 12), (120, 22, 7), (122, 140, 7)], '#ffe066')
    title(c, 'Good job!', size=46, cy=80 - 5 * abs(math.sin(TAU * t)))
    return c


def congrats(t):
    c = bg('#7f53ac', '#ff5fa2')
    confetti(c, t, seed=3)
    title(c, 'Congrats!', size=48, scale=1 + 0.04 * math.sin(TAU * t * 2))
    return c


def happy_birthday(t):
    c = bg('#ffb347', '#ff6f61')
    d = overlay(c)
    for x0, phase, col in seeded(9, 6, lambda r: (r.uniform(14, W - 14), r.random(), r.choice(['#4dabf7', '#ffd43b', '#f783ac', '#69db7c', '#b197fc']))):
        p = (phase + t) % 1.0
        y = H + 30 - p * (H + 70)
        x = x0 + 6 * math.sin(TAU * p * 2)
        line(d, [(x, y + 15), (x + 3 * math.sin(TAU * p * 3), y + 34)], (255, 255, 255, 150), 1.2)
        ellipse(d, x, y, 11, 14, hexrgb(col) + (230,))
        ellipse(d, x - 4, y - 5, 3, 4, (255, 255, 255, 120))
    confetti(c, t, seed=4, n=14)
    title(c, 'Happy', cy=56, size=40, angle=4 * math.sin(TAU * t))
    title(c, 'Birthday!', cy=104, size=44, angle=-4 * math.sin(TAU * t))
    return c


def welcome(t):
    c = bg('#00b09b', '#96c93d')
    twinkles(c, t, [(22, 24, 8), (218, 136, 9), (214, 26, 7), (24, 134, 7)])
    wave_text(c, 'Welcome!', t, size=46, amp=9)
    return c


def good_morning(t):
    c = bg('#56ccf2', '#ffe29f', diagonal=False)
    d = overlay(c)
    sx, sy = 190, 42 + 3 * math.sin(TAU * t)
    for i in range(12):
        a = TAU * (i / 12 + t / 6)
        line(d, [(sx + 26 * math.cos(a), sy + 26 * math.sin(a)), (sx + 36 * math.cos(a), sy + 36 * math.sin(a))], (255, 200, 60, 230), 4)
    ellipse(d, sx, sy, 20, 20, (255, 200, 60, 255))
    title(c, 'Good', cy=74, size=40, fill='#ffffff', stroke='#f08c00', stroke_w=2.5)
    title(c, 'morning!', cy=114, size=40, fill='#ffffff', stroke='#f08c00', stroke_w=2.5)
    return c


def good_night(t):
    c = bg('#141e30', '#4b3b8f', diagonal=False)
    twinkles(c, t, [(24, 24, 6), (60, 46, 4), (110, 18, 5), (150, 40, 4), (34, 136, 5), (206, 140, 6), (176, 112, 4)], '#fff3bf')
    d = overlay(c)
    mx, my = 196, 40 + 3 * math.sin(TAU * t)
    ellipse(d, mx, my, 22, 22, (255, 236, 153, 255))
    ellipse(d, mx + 10, my - 7, 19, 19, (40, 38, 90, 255))       # bite out of the moon
    title(c, 'Good night', cy=96, size=40)
    return c


def noted(t):
    c = bg('#43cea2', '#185a9d')
    d = overlay(c)
    p = min(1.0, t / 0.45)                       # tick draws in, then holds
    a, b, e = (88, 52), (104, 68), (136, 32)
    ellipse(d, 112, 50, 30, 30, '#ffffff')
    if p > 0:
        if p < 0.4:
            q = p / 0.4
            line(d, [a, (a[0] + (b[0] - a[0]) * q, a[1] + (b[1] - a[1]) * q)], '#12b886', 8)
        else:
            q = (p - 0.4) / 0.6
            line(d, [a, b, (b[0] + (e[0] - b[0]) * q, b[1] + (e[1] - b[1]) * q)], '#12b886', 8)
    title(c, 'Noted!', cy=118, size=40, scale=1 + (0.08 * math.sin(math.pi * min(1, max(0, (t - 0.45) / 0.2)))))
    return c


def on_it(t):
    c = bg('#f7971e', '#ffd200')
    d = overlay(c)
    for y0, phase, ln in seeded(6, 9, lambda r: (r.uniform(14, H - 14), r.random(), r.uniform(30, 70))):
        x = W + 40 - ((phase + t) % 1.0) * (W + 120)
        line(d, [(x, y0), (x + ln, y0)], (255, 255, 255, 150), 3)
    title(c, 'On it!', size=56, fill='#ffffff', stroke='#e8590c', stroke_w=3, dx=4 * math.sin(TAU * t * 2), angle=6)
    return c


def lol(t):
    c = bg('#fceabb', '#f8b500')
    d = overlay(c)
    for i, (x, y) in enumerate([(40, 34), (196, 40), (54, 128), (190, 124)]):
        k = (t + i / 4) % 1.0
        paste(c, text_layer('HA', 18, '#e8590c', shadow=False), x, y - 10 * k, 0.8 + 0.3 * k)
    f = fit_size('LOL', 200, 74)
    for i, ch in enumerate('LOL'):
        dx = (i - 1) * 62
        jig = math.sin(TAU * (t * 3 + i * 0.3))
        paste(c, text_layer(ch, f, '#ffffff', '#e8590c', 3), W / 2 + dx, 80 + 6 * jig, angle=10 * jig)
    return c


def wow(t):
    c = bg('#ff512f', '#dd2476')
    d = overlay(c)
    cx, cy = W / 2, H / 2
    for i in range(16):
        a0 = TAU * (i / 16 + t / 8)
        a1 = a0 + TAU / 32
        R = 220
        d.polygon([(cx * S, cy * S), ((cx + R * math.cos(a0)) * S, (cy + R * math.sin(a0)) * S),
                   ((cx + R * math.cos(a1)) * S, (cy + R * math.sin(a1)) * S)], fill=(255, 255, 255, 40))
    title(c, 'WOW!', size=64, fill='#ffe066', stroke='#c2255c', stroke_w=3, scale=1 + 0.08 * math.sin(TAU * t * 2))
    return c


def yay(t):
    c = bg('#ff9966', '#ff5e62')
    confetti(c, t, seed=7, n=20)
    j = abs(math.sin(TAU * t))
    title(c, 'Yay!', cy=88 - 18 * j, size=64, scale=1 + 0.06 * (1 - j))
    return c


def coffee_break(t):
    c = bg('#c79081', '#dfa579')
    d = overlay(c)
    cx, cy = 68, 92
    for i in range(3):                                 # steam
        x = cx - 14 + i * 14
        pts = [(x + 5 * math.sin(TAU * (t + k / 10 + i / 3)), cy - 30 - k * 4) for k in range(9)]
        line(d, pts, (255, 255, 255, 170), 3)
    d.rounded_rectangle([(cx - 26) * S, (cy - 24) * S, (cx + 26) * S, (cy + 24) * S], radius=10 * S, fill='#ffffff')
    d.rounded_rectangle([(cx - 22) * S, (cy - 20) * S, (cx + 22) * S, (cy - 12) * S], radius=4 * S, fill='#6f4e37')
    ellipse(d, cx + 30, cy, 10, 11, None, '#ffffff', 5)
    title(c, 'Coffee', cy=62, size=34, dx=52, max_w=120)
    title(c, 'break?', cy=100, size=34, dx=52, max_w=120, angle=4 * math.sin(TAU * t))
    return c


def lunch(t):
    c = bg('#f6d365', '#fda085')
    d = overlay(c)
    cx, cy = 66, 96
    for i in range(3):
        x = cx - 14 + i * 14
        line(d, [(x + 4 * math.sin(TAU * (t + k / 10 + i / 3)), cy - 26 - k * 4) for k in range(8)], (255, 255, 255, 170), 3)
    ellipse(d, cx, cy - 8, 26, 10, (255, 255, 255, 255))                  # rice
    d.chord([(cx - 32) * S, (cy - 30) * S, (cx + 32) * S, (cy + 30) * S], 0, 180, fill='#e03131')   # bowl
    line(d, [(cx + 8, cy - 40), (cx + 40, cy - 6)], '#7f5539', 4)            # chopsticks
    line(d, [(cx + 16, cy - 44), (cx + 46, cy - 12)], '#7f5539', 4)
    title(c, 'Lunch?', cy=82, size=42, dx=50, max_w=120, angle=6 * math.sin(TAU * t))
    return c


def brb(t):
    c = bg('#667eea', '#764ba2')
    d = overlay(c)
    cx, cy = 62, 80
    ellipse(d, cx, cy, 34, 34, '#ffffff')
    ellipse(d, cx, cy, 30, 30, None, '#e9ecef', 2)
    for i in range(12):
        a = TAU * i / 12
        ellipse(d, cx + 25 * math.cos(a), cy + 25 * math.sin(a), 1.6, 1.6, '#868e96')
    a = TAU * t - math.pi / 2
    line(d, [(cx, cy), (cx + 22 * math.cos(a), cy + 22 * math.sin(a))], '#e03131', 3)
    line(d, [(cx, cy), (cx + 14 * math.cos(a / 12 - 1), cy + 14 * math.sin(a / 12 - 1))], '#343a40', 4)
    ellipse(d, cx, cy, 3, 3, '#343a40')
    title(c, 'BRB', cy=70, size=46, dx=52, max_w=120)
    paste(c, text_layer('be right back', 13, '#ffffff', path=FONT_BOLD), W / 2 + 52, 106)
    return c


def great_idea(t):
    c = bg('#2b5876', '#4e4376')
    d = overlay(c)
    cx, cy = 120, 52
    k = 0.5 + 0.5 * math.sin(TAU * t)
    ellipse(d, cx, cy, 34 + 6 * k, 34 + 6 * k, (255, 236, 153, int(40 + 50 * k)))
    for i in range(8):
        a = TAU * i / 8 - math.pi / 2
        r0, r1 = 30, 38 + 5 * k
        line(d, [(cx + r0 * math.cos(a), cy + r0 * math.sin(a)), (cx + r1 * math.cos(a), cy + r1 * math.sin(a))], (255, 224, 102, 230), 3)
    ellipse(d, cx, cy, 20, 20, (255, 224, 102, 255))
    d.rounded_rectangle([(cx - 9) * S, (cy + 16) * S, (cx + 9) * S, (cy + 30) * S], radius=3 * S, fill='#adb5bd')
    title(c, 'Great idea!', cy=122, size=38)
    return c


def love_it(t):
    c = bg('#ee9ca7', '#ffdde1')
    d = overlay(c)
    beat = max(0.0, math.sin(TAU * t * 2)) ** 3 if t < 0.5 else 0.0      # lub-dub, then rest
    ellipse(d, 120, 56, 48, 40, (255, 255, 255, 70))
    d.polygon(heart_points(120, 54, 30 * (1 + 0.18 * beat)), fill='#f03e3e')
    title(c, 'Love it!', cy=122, size=38, fill='#ffffff', stroke='#c92a2a', stroke_w=2.5)
    return c


def sorry(t):
    c = bg('#a1c4fd', '#c2e9fb', diagonal=False)
    d = overlay(c)
    p = t % 1.0
    x, y = 176, 34 + p * 26
    d.polygon([(x * S, (y - 10) * S), ((x - 6) * S, (y + 2) * S), ((x + 6) * S, (y + 2) * S)], fill=(77, 171, 247, int(255 * (1 - p))))
    ellipse(d, x, y + 3, 6, 6, (77, 171, 247, int(255 * (1 - p))))
    title(c, 'Sorry!', cy=84, size=52, fill='#ffffff', stroke='#1971c2', stroke_w=3, angle=5 * math.sin(TAU * t))
    return c


def approved(t):
    c = bg('#fdfbfb', '#ebedee')
    d = overlay(c)
    for i in range(5):                                       # paper lines
        line(d, [(26, 30 + i * 24), (214, 30 + i * 24)], (206, 212, 218, 255), 1.2)
    if t < 0.25:
        k = t / 0.25
        scale, alpha = 2.2 - 1.2 * k, int(255 * k)
    else:
        scale, alpha = 1.0 + 0.04 * math.sin(math.pi * min(1, (t - 0.25) / 0.12)), 255
    stamp = Image.new('RGBA', (190 * S, 64 * S), (0, 0, 0, 0))
    sd = ImageDraw.Draw(stamp)
    sd.rounded_rectangle([3 * S, 3 * S, 187 * S, 61 * S], radius=8 * S, outline=(224, 49, 49, alpha), width=5 * S)
    f = font(fit_size('APPROVED', 160, 36))
    l, tp, r, b = f.getbbox('APPROVED')
    sd.text(((190 * S - (r - l)) / 2 - l, (64 * S - (b - tp)) / 2 - tp), 'APPROVED', font=f, fill=(224, 49, 49, alpha))
    paste(c, stamp, W / 2, H / 2, scale, angle=12)
    return c


def payday(t):
    c = bg('#134e5e', '#71b280')
    d = overlay(c)
    for x0, phase, r in seeded(8, 9, lambda r: (r.uniform(14, W - 14), r.random(), r.uniform(9, 13))):
        p = (phase + t) % 1.0
        y = -20 + p * (H + 40)
        squash = abs(math.cos(TAU * (p * 2 + phase)))
        ellipse(d, x0, y, r * (0.35 + 0.65 * squash), r, '#fcc419', '#f59f00', 2)
        if squash > 0.6:
            paste(c, text_layer('₱', r * 1.1, '#e67700', shadow=False, path=FONT_BOLD), x0, y)
    title(c, 'Payday!', size=50, fill='#ffe066', stroke='#2b8a3e', stroke_w=3, scale=1 + 0.05 * math.sin(TAU * t))
    return c


def lets_go(t):
    c = bg('#e52d27', '#ff7e5f')
    d = overlay(c)
    for i in range(6):
        x = ((i / 6 + t) % 1.0) * (W + 60) - 30
        line(d, [(x - 12, 46), (x + 6, 80), (x - 12, 114)], (255, 255, 255, 34), 10)
    title(c, "Let's go!", size=48, stroke='#a61e4d', stroke_w=2.5, dx=6 * math.sin(TAU * t), angle=4)
    return c


def hi(t):
    c = bg('#00c6ff', '#0072ff')
    d = overlay(c)
    for i in range(3):
        k = (t + i / 3) % 1.0
        r = 30 + k * 26
        d.arc([(166 - r) * S, (60 - r) * S, (166 + r) * S, (60 + r) * S], -40, 40, fill=(255, 255, 255, int(200 * (1 - k))), width=3 * S)
    size = fit_size('Hi!', 140, 76)
    layer = text_layer('Hi!', size)
    paste(c, layer, 100, 84, angle=12 * math.sin(TAU * t * 2))
    return c


def fighting(t):
    c = bg('#200122', '#6f0000', diagonal=False)
    d = overlay(c)
    for i in range(14):                                     # flames along the bottom
        x = 8 + i * 17
        hgt = 34 + 16 * math.sin(TAU * (t * 2 + i * 0.37)) + 8 * math.sin(TAU * (t * 3 + i))
        for col, k in (((255, 107, 0, 230), 1.0), ((255, 212, 59, 240), 0.55)):
            d.polygon([((x - 11 * k) * S, H * S), (x * S, (H - hgt * k) * S), ((x + 11 * k) * S, H * S)], fill=col)
    jit = math.sin(TAU * t * 4)
    title(c, 'Fighting!', cy=66, size=46, fill='#ffe066', stroke='#c92a2a', stroke_w=3, dx=1.5 * jit, angle=1.5 * jit)
    return c


def tgif(t):
    hue = [('#f857a6', '#ff5858'), ('#7f00ff', '#e100ff'), ('#00c9ff', '#92fe9d'), ('#f7971e', '#ffd200')]
    c1, c2 = hue[int(t * 4) % 4]
    c = bg(c1, c2)
    confetti(c, t, seed=11, n=16)
    title(c, 'TGIF!', cy=72 - 8 * abs(math.sin(TAU * t * 2)), size=60)
    paste(c, text_layer('Happy Friday', 15, '#ffffff', path=FONT_BOLD), W / 2, 126)
    return c


def ingat(t):
    c = bg('#a8e063', '#56ab2f')
    twinkles(c, t, [(30, 30, 8), (210, 30, 9), (40, 132, 7), (200, 130, 8)])
    d = overlay(c)
    d.polygon(heart_points(120, 46, 18 * (1 + 0.1 * math.sin(TAU * t))), fill='#ff6b6b')
    title(c, 'Ingat!', cy=104, size=46)
    return c


STICKERS = [
    ('thank-you', 'Thank you!', 'thanks thank you ty', thank_you),
    ('salamat', 'Salamat!', 'thanks thank you salamat', salamat),
    ('good-job', 'Good job!', 'good job great work nice well done', good_job),
    ('congrats', 'Congrats!', 'congratulations congrats celebrate', congrats),
    ('happy-birthday', 'Happy Birthday!', 'birthday bday celebrate party', happy_birthday),
    ('welcome', 'Welcome!', 'welcome new hello', welcome),
    ('good-morning', 'Good morning!', 'good morning gm hello', good_morning),
    ('good-night', 'Good night', 'good night gn bye', good_night),
    ('hi', 'Hi!', 'hi hello hey wave', hi),
    ('noted', 'Noted!', 'noted ok okay got it check', noted),
    ('on-it', 'On it!', 'on it doing working', on_it),
    ('approved', 'Approved', 'approved yes ok stamp', approved),
    ('great-idea', 'Great idea!', 'idea great smart', great_idea),
    ('love-it', 'Love it!', 'love heart like', love_it),
    ('wow', 'WOW!', 'wow amazing omg', wow),
    ('yay', 'Yay!', 'yay happy hooray', yay),
    ('lol', 'LOL', 'lol haha funny laugh', lol),
    ('lets-go', "Let's go!", 'lets go go start', lets_go),
    ('fighting', 'Fighting!', 'fighting laban kaya go', fighting),
    ('payday', 'Payday!', 'payday sweldo salary money pay', payday),
    ('coffee-break', 'Coffee break?', 'coffee break kape', coffee_break),
    ('lunch', 'Lunch?', 'lunch food eat kain', lunch),
    ('brb', 'BRB', 'brb be right back wait', brb),
    ('sorry', 'Sorry!', 'sorry apology pasensya', sorry),
    ('tgif', 'TGIF!', 'tgif friday weekend', tgif),
    ('ingat', 'Ingat!', 'ingat take care bye', ingat),
]


def render(fn):
    frames = []
    for i in range(FRAMES):
        img = fn(i / FRAMES).convert('RGB').resize((W, H), Image.LANCZOS)
        frames.append(img)
    # one palette for the whole loop (no colour flicker between frames)
    sheet = Image.new('RGB', (W, H * len(frames)))
    for i, f in enumerate(frames):
        sheet.paste(f, (0, i * H))
    pal = sheet.quantize(colors=255, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)
    return [f.quantize(palette=pal, dither=Image.Dither.NONE) for f in frames]


def main():
    os.makedirs(OUT, exist_ok=True)
    manifest = []
    for name, label, tags, fn in STICKERS:
        frames = render(fn)
        path = os.path.join(OUT, name + '.gif')
        frames[0].save(path, save_all=True, append_images=frames[1:], duration=MS, loop=0, optimize=True, disposal=1)
        manifest.append({'file': name + '.gif', 'title': label, 'tags': tags})
        print(f'{name + ".gif":22} {os.path.getsize(path) // 1024:4d} KB')
    with open(os.path.join(OUT, 'gifs.json'), 'w', encoding='utf-8', newline='\n') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)
        fh.write('\n')


if __name__ == '__main__':
    main()

/* ==========================================================================
   idcard-render.js  —  draws the WeDo company ID card (front + back) as SVG.
   ---------------------------------------------------------------------------
   One SVG per side, CR80 portrait (54 x 85.6 mm). The viewBox is in tenths of
   a millimetre (540 x 856), so the same markup is exact on screen and in print,
   and colours print even with "Background graphics" off.

     IDCard.front(card, opts) -> SVG string     card = row from idc_card_data()
     IDCard.back(cfg, opts)   -> SVG string     cfg  = idc_back_config()
     IDCard.fit(rootEl)       -> Promise        shrink long text to its box
                                                (call after the SVG is in the DOM)
     IDCard.photoBox(card)    -> geometry used to drag the photo

   Text stays at least 3 mm inside the edges (card printers shift slightly).
   ========================================================================== */
(function (global) {
  'use strict';

  var W = 540, H = 856;
  var MAROON = '#6A0000', RED = '#E00A0A', ICON_RED = '#C1272D', INK = '#111111';
  var LIGHT = '#F4F0F0';
  var FONT = "'Roboto Condensed','Arial Narrow',Arial,sans-serif";

  // photo circle
  var CX = 270, CY = 345, R_RING = 161, R_PHOTO = 152;

  var uid = 0;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function svgOpen(cls) {
    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" ' +
      'class="idc-svg ' + cls + '" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet">';
  }

  /* text that IDCard.fit() may shrink: data-max = box width, data-min = smallest size */
  function fitText(x, y, size, max, min, attrs, body) {
    return '<text x="' + x + '" y="' + y + '" font-size="' + size + '" data-size="' + size + '" data-max="' + max +
      '" data-min="' + min + '" text-anchor="middle" font-family="' + esc(FONT) + '" ' + attrs + '>' + esc(body) + '</text>';
  }

  /* shared defs: the fabric texture of the light area + a filter that turns the logo white */
  function defs(p) {
    return '<defs>' +
      '<pattern id="' + p + 'tex" width="10" height="8" patternUnits="userSpaceOnUse">' +
        '<rect width="10" height="8" fill="' + LIGHT + '"/>' +
        '<rect x="1" y="1" width="8" height="6" rx="1.6" fill="#ECE5E5"/>' +
        '<rect x="1.8" y="1.6" width="6.4" height="1.6" rx=".8" fill="#F7F4F4"/>' +
      '</pattern>' +
      '<filter id="' + p + 'white" color-interpolation-filters="sRGB">' +
        '<feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 1 0"/>' +
      '</filter>' +
      '<clipPath id="' + p + 'photo"><circle cx="' + CX + '" cy="' + CY + '" r="' + R_PHOTO + '"/></clipPath>' +
    '</defs>';
  }

  /* bottom sweep (front and back); the back's top is the same element turned 180° */
  function bottomWaves(lightFill) {
    return '<g class="idc-waves">' +
      // red sweep
      '<path fill="' + RED + '" d="M0,729 C180,729 351,806 540,729 L540,856 L0,856 Z"/>' +
      // light sliver that splits the red into a thin line and a thick band
      '<path fill="' + lightFill + '" d="M146,734 C279,767 429,793 540,748 L540,765 C429,810 279,784 146,734 Z"/>' +
      // maroon base
      '<path fill="' + MAROON + '" d="M0,748 C150,754 343,827 540,789 L540,856 L0,856 Z"/>' +
    '</g>';
  }

  function logo(src, x, y, w, white, p) {
    var h = Math.round(w / 1.2457 * 10) / 10;   // WeDo.png is 1957 x 1571
    return '<image href="' + esc(src) + '" xlink:href="' + esc(src) + '" x="' + x + '" y="' + y + '" width="' + w +
      '" height="' + h + '"' + (white ? ' filter="url(#' + p + 'white)"' : '') + ' preserveAspectRatio="xMidYMid meet"/>';
  }

  /* where the photo sits for a given crop: cover the circle, then zoom and pan */
  function photoBox(card) {
    var d = R_PHOTO * 2;
    var w = card.photoW || 1, h = card.photoH || 1;
    var c = card.crop || {};
    var zoom = Math.min(3, Math.max(1, +c.zoom || 1));
    var s = Math.max(d / w, d / h) * zoom;
    var iw = w * s, ih = h * s;
    var panX = (iw - d) / 2, panY = (ih - d) / 2;   // how far it can move each way
    var x = Math.min(1, Math.max(-1, +c.x || 0)), y = Math.min(1, Math.max(-1, +c.y || 0));
    return {
      x: CX - iw / 2 + x * panX, y: CY - ih / 2 + y * panY, w: iw, h: ih,
      panX: panX, panY: panY, viewW: W
    };
  }

  function photo(card, p) {
    if (!card.photo) {
      // no photo: soft silhouette so the layout still reads
      return '<g fill="#DCD3D3">' +
        '<circle cx="' + CX + '" cy="' + (CY - 38) + '" r="58"/>' +
        '<path clip-path="url(#' + p + 'photo)" d="M' + (CX - 120) + ',' + (CY + 150) + ' C' + (CX - 115) + ',' + (CY + 40) + ' ' +
          (CX - 60) + ',' + (CY + 32) + ' ' + CX + ',' + (CY + 32) + ' C' + (CX + 60) + ',' + (CY + 32) + ' ' + (CX + 115) + ',' +
          (CY + 40) + ' ' + (CX + 120) + ',' + (CY + 150) + ' Z"/>' +
        '<text x="' + CX + '" y="' + (CY + 110) + '" text-anchor="middle" font-family="' + esc(FONT) +
          '" font-weight="700" font-size="20" fill="#B9AEAE" letter-spacing="2">NO PHOTO</text>' +
      '</g>';
    }
    var b = photoBox(card);
    return '<g clip-path="url(#' + p + 'photo)"><image class="idc-photo" href="' + esc(card.photo) + '" xlink:href="' + esc(card.photo) +
      '" x="' + b.x.toFixed(2) + '" y="' + b.y.toFixed(2) + '" width="' + b.w.toFixed(2) + '" height="' + b.h.toFixed(2) +
      '" preserveAspectRatio="none"/></g>';
  }

  /* ------------------------------------------------------------------ front */
  function front(card, opts) {
    opts = opts || {};
    var p = 'idc' + (++uid) + '-';
    var num = card.idNumber || opts.previewNumber || '';
    return svgOpen('idc-front') + defs(p) +
      // light textured body
      '<rect width="' + W + '" height="' + H + '" fill="url(#' + p + 'tex)"/>' +
      // maroon head, bounded below by the main sweep
      '<path fill="' + MAROON + '" d="M0,0 H540 V414 C430,395 360,330 270,300 C180,272 90,276 0,281 Z"/>' +
      // red sweep from upper left to lower right
      '<path fill="' + RED + '" d="M0,227 C120,245 200,262 270,282 C350,305 440,325 540,333 L540,414 C430,395 360,330 270,300 C180,272 90,276 0,281 Z"/>' +
      // maroon inlay that splits the red sweep into a wide band and a thin line (starts behind the photo)
      '<path fill="' + MAROON + '" d="M130,290 C190,292 230,300 270,308 C350,328 440,350 540,356 L540,398 C450,388 360,350 270,318 C220,305 170,295 130,292 Z"/>' +
      logo(opts.logo || 'assets/images/logos/WeDo.png', 176, 18, 188, true, p) +
      // photo in a red ring
      '<circle cx="' + CX + '" cy="' + CY + '" r="' + R_RING + '" fill="' + RED + '"/>' +
      '<circle cx="' + CX + '" cy="' + CY + '" r="' + R_PHOTO + '" fill="#FFFFFF"/>' +
      photo(card, p) +
      // employee's signature, just above the name (never over it, so the name stays readable)
      (card.signature ? '<image class="idc-sign" href="' + esc(card.signature) + '" xlink:href="' + esc(card.signature) +
        '" x="165" y="519" width="210" height="84" preserveAspectRatio="xMidYMax meet"/>' : '') +
      // identity
      fitText(270, 632, 30, 470, 20, 'font-weight="700" fill="' + INK + '" class="idc-name"', card.name || '') +
      fitText(270, 662, 23, 470, 15, 'font-weight="500" fill="' + RED + '" class="idc-pos"', card.position || '') +
      (num ? fitText(270, 690, 19, 300, 14, 'font-weight="700" fill="' + INK + '" letter-spacing="0.6" class="idc-num' +
        (card.idNumber ? '' : ' idc-num--preview') + '"', num) : '') +
      bottomWaves('url(#' + p + 'tex)') +
    '</svg>';
  }

  /* ------------------------------------------------------------------- back */
  var ICONS = {   // Material Design icons (Apache 2.0), 24 x 24
    phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
    mail:  'M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z',
    pin:   'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z'
  };

  function infoRow(cy, icon, label, lines) {
    var out = '<circle cx="104" cy="' + cy + '" r="27.5" fill="' + ICON_RED + '"/>' +
      '<path fill="#FFFFFF" transform="translate(104 ' + cy + ') scale(1.45) translate(-12 -12)" d="' + ICONS[icon] + '"/>' +
      '<text x="163" y="' + (cy - 6) + '" font-family="' + esc(FONT) + '" font-weight="700" font-size="23" fill="' + INK + '">' + esc(label) + '</text>';
    lines.forEach(function (ln, i) {
      out += '<text x="163" y="' + (cy + 16 + i * 21) + '" font-size="17" data-size="17" data-max="340" data-min="12" data-anchor="start" ' +
        'font-family="' + esc(FONT) + '" font-weight="700" fill="' + INK + '" letter-spacing="0.5">' + esc(ln) + '</text>';
    });
    return out;
  }

  function back(cfg, opts) {
    opts = opts || {};
    var p = 'idc' + (++uid) + '-';
    var st = cfg.statement || [];
    return svgOpen('idc-back') + defs(p) +
      '<rect width="' + W + '" height="' + H + '" fill="#FFFFFF"/>' +
      '<g transform="rotate(180 270 428)">' + bottomWaves('#FFFFFF') + '</g>' +
      fitText(270, 133, 21.5, 450, 14, 'font-weight="700" fill="' + INK + '"', st[0] || '') +
      fitText(270, 161, 21.5, 450, 14, 'font-weight="700" fill="' + INK + '"', st[1] || '') +
      logo(cfg.logo || 'assets/images/logos/WeDo.png', 224, 180, 94, false, p) +
      infoRow(366, 'phone', 'PHONE', [cfg.phone || '']) +
      infoRow(449, 'mail', 'EMAIL', [cfg.email || '']) +
      infoRow(533, 'pin', 'ADDRESS', cfg.address || []) +
      // signature over the signatory's name; multiply drops a white background
      (cfg.signature ? '<image href="' + esc(cfg.signature) + '" xlink:href="' + esc(cfg.signature) +
        '" x="160" y="626" width="220" height="100" preserveAspectRatio="xMidYMid meet" style="mix-blend-mode:multiply"/>' : '') +
      fitText(270, 716, 19, 360, 13, 'font-weight="700" fill="' + INK + '"', cfg.signatory || '') +
      fitText(270, 736, 14, 300, 10, 'font-weight="400" fill="#B3242A" letter-spacing="0.4"', cfg.sign_title || '') +
      bottomWaves('#FFFFFF') +
    '</svg>';
  }

  /* --------------------------------------------------------------- fitting */
  function fontsReady() {
    if (!document.fonts || !document.fonts.load) { return Promise.resolve(); }
    return Promise.all([
      document.fonts.load('700 30px "Roboto Condensed"'),
      document.fonts.load('500 23px "Roboto Condensed"')
    ]).then(function () { return document.fonts.ready; }).catch(function () {});
  }

  function fitOne(t) {
    var max = +t.getAttribute('data-max'), min = +t.getAttribute('data-min');
    var size = +t.getAttribute('data-size');
    t.setAttribute('font-size', size);
    t.removeAttribute('textLength'); t.removeAttribute('lengthAdjust');
    var len = t.getComputedTextLength();
    if (!len) { return; }   // not rendered (display:none) — nothing to measure
    while (len > max && size > min) {
      size = Math.max(min, size - 0.5);
      t.setAttribute('font-size', size);
      len = t.getComputedTextLength();
    }
    if (len > max) {   // still too long at the smallest size: condense the glyphs
      t.setAttribute('textLength', max);
      t.setAttribute('lengthAdjust', 'spacingAndGlyphs');
    }
  }

  function fit(root) {
    return fontsReady().then(function () {
      Array.prototype.forEach.call((root || document).querySelectorAll('.idc-svg text[data-max]'), fitOne);
    });
  }

  global.IDCard = { front: front, back: back, fit: fit, photoBox: photoBox, W: W, H: H };
})(window);

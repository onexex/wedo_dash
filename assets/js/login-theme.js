// Falling / rising particles for the seasonal login theme (Maintenance > Login Theme).
// The effect config comes from lt_presets() in includes/login-theme.php via #loginFx[data-fx].
// Decorative only: pointer-events are off and it is skipped for reduced motion.
(function () {
    var box = document.getElementById('loginFx');
    if (!box) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var fx;
    try { fx = JSON.parse(box.getAttribute('data-fx') || 'null'); } catch (e) { return; }
    if (!fx || !fx.kind) return;

    // Fewer particles on small screens — phones pay for every animated node.
    var small = window.innerWidth < 600;
    var counts = { snow: 70, confetti: 70, hearts: 26, glow: 28 };
    var n = Math.round((counts[fx.kind] || 40) * (small ? 0.5 : 1));
    var colors = fx.colors || ['#ffffff'];

    for (var i = 0; i < n; i++) {
        var s = document.createElement('span');
        var dur = 6 + Math.random() * 10;
        s.className = 'p';
        s.style.left = (Math.random() * 100) + 'vw';
        s.style.animationDuration = dur + 's';
        s.style.animationDelay = (-Math.random() * dur) + 's';
        s.style.setProperty('--sway', ((Math.random() - 0.5) * 160) + 'px');
        s.style.setProperty('--spin', ((Math.random() - 0.5) * 720) + 'deg');

        if (fx.kind === 'snow') {
            var sz = 3 + Math.random() * 6;
            s.style.width = s.style.height = sz + 'px';
            s.style.borderRadius = '50%';
            s.style.background = '#fff';
            s.style.opacity = 0.4 + Math.random() * 0.6;
        } else if (fx.kind === 'confetti') {
            s.style.width = (6 + Math.random() * 6) + 'px';
            s.style.height = (10 + Math.random() * 8) + 'px';
            s.style.background = colors[i % colors.length];
            s.style.borderRadius = '2px';
            s.style.opacity = 0.9;
        } else if (fx.kind === 'hearts') {
            s.className += ' rise';
            s.textContent = '♥';
            s.style.color = ['#ff6b9d', '#ffb3c9', '#e0457b'][i % 3];
            s.style.fontSize = (14 + Math.random() * 20) + 'px';
        } else if (fx.kind === 'glow') {
            var g = 4 + Math.random() * 6, c = colors[i % colors.length];
            s.className += ' rise';
            s.style.width = s.style.height = g + 'px';
            s.style.borderRadius = '50%';
            s.style.background = c;
            s.style.boxShadow = '0 0 12px 3px ' + c;
        }
        box.appendChild(s);
    }
})();

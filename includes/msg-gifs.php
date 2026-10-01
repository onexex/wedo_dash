<?php
/* =============================================================================
 * includes/msg-gifs.php — GIF stickers in Messages, from our own library.
 *
 * The library is the folder assets/gifs/: every *.gif there can be sent. The
 * starter set is drawn by tools/make-message-gifs.py (the company owns them);
 * HR can add any other .gif they have the rights to — it shows up by itself.
 * assets/gifs/gifs.json gives titles, search words and order:
 *   [{"file": "thank-you.gif", "title": "Thank you!", "tags": "thanks ty"}, …]
 * A file not listed there is titled from its name ("happy-new-year.gif" → "Happy new year").
 *
 * A GIF message is messages.Kind = 'gif' with a small JSON card as its text:
 *   {"f": "thank-you.gif", "w": 240, "h": 160, "t": "Thank you!"}
 * Nothing leaves the server: no outside service, no key.
 * Needs messages.Kind (sql/2026-10-01-add-message-groups.sql).
 *
 * Used by: query/Query-messages.php
 * ========================================================================== */

require_once __DIR__ . '/messages-lib.php';

const GIF_FILE_RE = '/^[a-z0-9][a-z0-9_-]{0,60}\.gif$/';

function gif_dir(): string { return dirname(__DIR__) . '/assets/gifs'; }

/** The library: file => ['file', 'title', 'tags', 'w', 'h'], in gifs.json order, then the rest by name. */
function gif_library(?string $dir = null): array
{
    static $cache = [];
    $dir = $dir ?? gif_dir();
    if (isset($cache[$dir])) { return $cache[$dir]; }

    $meta = [];
    $manifest = $dir . '/gifs.json';
    if (is_file($manifest)) {
        $list = json_decode((string) file_get_contents($manifest), true);
        foreach (is_array($list) ? $list : [] as $e) {
            if (is_array($e) && isset($e['file']) && is_string($e['file'])) { $meta[$e['file']] = $e; }
        }
    }
    $files = [];
    foreach (glob($dir . '/*.gif') ?: [] as $path) {
        $f = basename($path);
        if (preg_match(GIF_FILE_RE, $f)) { $files[] = $f; }
    }
    $order = array_flip(array_keys($meta));
    usort($files, fn($a, $b) => [isset($order[$a]) ? 0 : 1, $order[$a] ?? 0, $a] <=> [isset($order[$b]) ? 0 : 1, $order[$b] ?? 0, $b]);

    $out = [];
    foreach ($files as $f) {
        $size = @getimagesize($dir . '/' . $f);
        if (!$size || $size[2] !== IMAGETYPE_GIF) { continue; }
        $m = $meta[$f] ?? [];
        $name = ucfirst(str_replace(['-', '_'], ' ', substr($f, 0, -4)));
        $out[$f] = [
            'file'  => $f,
            'title' => mb_substr(trim((string) ($m['title'] ?? '')) ?: $name, 0, 60),
            'tags'  => mb_substr(trim((string) ($m['tags'] ?? '')), 0, 200),
            'w'     => (int) $size[0],
            'h'     => (int) $size[1],
        ];
    }
    return $cache[$dir] = $out;
}

function gif_enabled(PDO $pdo, ?string $dir = null): bool
{
    return msg_has_kind($pdo) && gif_library($dir) !== [];
}

/** The message text for library file $file, or an error. */
function gif_message_text(string $file, ?string $dir = null): array
{
    $lib = gif_library($dir);
    if (!preg_match(GIF_FILE_RE, $file) || !isset($lib[$file])) { return ['ok' => false, 'error' => 'That GIF isn’t available.']; }
    $g = $lib[$file];
    return ['ok' => true, 'text' => json_encode(['f' => $g['file'], 'w' => $g['w'], 'h' => $g['h'], 't' => $g['title']],
                                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
}

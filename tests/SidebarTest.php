<?php
namespace Wedo\Tests;

use Wedo\Tests\Support\AppTestCase;
use DOMDocument;

/**
 * The themed sidebar (includes/wd-header.php) and its icon sprite
 * (assets/icons/wd-nav.svg).
 *
 * Browsers only use an external <use href="file.svg#id"> sprite when the
 * file is strictly well-formed XML: one stray "--" inside a comment and
 * every icon in the menu silently renders blank.
 */
final class SidebarTest extends AppTestCase
{
    private const SPRITE = __DIR__ . '/../assets/icons/wd-nav.svg';

    public function testIconSpriteIsWellFormedXml(): void
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $doc->load(self::SPRITE);
        $errors = array_map(fn($e) => trim($e->message) . ' (line ' . $e->line . ')', libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($ok && !$errors, "wd-nav.svg is not well-formed XML:\n" . implode("\n", $errors));
    }

    public function testEveryIconTheMenuUsesIsInTheSprite(): void
    {
        $doc = new DOMDocument();
        $doc->load(self::SPRITE);
        $have = [];
        foreach ($doc->getElementsByTagName('symbol') as $s) { $have[$s->getAttribute('id')] = true; }

        // the admin sees every section, so this renders (almost) every menu icon
        self::db()->exec("UPDATE accessrights SET dashboard = 2, alas = 2, payroll = 2, alasv = 2, lilov = 2, arights = 2,
                          agncy = 2, hmo = 2, gcorner = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $body = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $this->assertNoPhpErrors($body);

        preg_match_all('/wd-nav\.svg\?v=\d+#(i-[a-z0-9-]+)/', $body, $m);
        $used = array_unique($m[1]);
        $this->assertGreaterThan(15, count($used), 'expected the menu to render its icons');
        $this->assertSame([], array_values(array_diff($used, array_keys($have))), 'icons used by the menu but missing from wd-nav.svg');

        // and every icon named in the menu source (pages a seeded user can't see) exists too
        // the icon is the column right before a page label ('wallet', 'Leave Credit Viewer'])
        preg_match_all("/'([a-z0-9-]+)',\s+'[A-Z0-9][^']*'(?:,\s*\\\$wdPcrCount)?\]/", file_get_contents(__DIR__ . '/../includes/wd-header.php'), $src);
        preg_match_all("/wd_icon\('([a-z0-9-]+)'/", file_get_contents(__DIR__ . '/../includes/wd-header.php'), $calls);
        $named = array_map(fn($n) => 'i-' . $n, array_unique(array_merge($src[1], $calls[1])));
        $this->assertGreaterThan(50, count($named), 'expected to find the menu icon names in wd-header.php');
        $this->assertSame([], array_values(array_diff($named, array_keys($have))), 'icons named in wd-header.php but missing from wd-nav.svg');
    }

    public function testMenuHasSearchSectionsAndAccountCard(): void
    {
        self::db()->exec("UPDATE accessrights SET alasv = 2 WHERE EmpID = '" . self::ADMIN . "'");
        $body = $this->request('idcard.php', [], [], $this->asAdmin())['body'];
        $x = $this->dom($body);

        $this->assertNotNull($x->query("//input[@id='wdNavSearch']")->item(0));
        $this->assertNotNull($x->query("//div[@data-sec='reports']//a[@href='alasviewer']")->item(0));
        // the page you're on is marked active inside its section
        $this->assertNotNull($x->query("//div[@data-sec='management']//a[@href='idcard' and contains(@class,'is-active')]")->item(0));
        $this->assertNotNull($x->query("//div[contains(@class,'wd-account')]//a[@href='login.php?logout']")->item(0));
    }
}

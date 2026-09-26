<?php

namespace Tests\Unit;

use App\Support\HtmlCleaner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlCleanerTest extends TestCase
{
    public function test_keeps_normal_formatting(): void
    {
        $html = '<p>Which is <strong>correct</strong>? <em>Choose</em> one.</p><ul><li>a</li></ul><table><tr><td colspan="2">x</td></tr></table><sup>2</sup>';

        $this->assertSame($html, HtmlCleaner::clean($html));
    }

    public function test_keeps_unicode_and_maths_text(): void
    {
        $this->assertSame('<p>Ọ̀gbẹ́ni, x² ≤ 5, \(a &lt; b\), ₦500</p>', HtmlCleaner::clean('<p>Ọ̀gbẹ́ni, x² ≤ 5, \(a &lt; b\), ₦500</p>'));
    }

    public function test_empty_input(): void
    {
        $this->assertSame('', HtmlCleaner::clean(null));
        $this->assertSame('', HtmlCleaner::clean('   '));
    }

    #[DataProvider('attacks')]
    public function test_removes_attacks(string $html, array $mustNotContain, string $mustContain = ''): void
    {
        $out = HtmlCleaner::clean($html);

        foreach ($mustNotContain as $bad) {
            $this->assertStringNotContainsStringIgnoringCase($bad, $out, "leaked '$bad' in: $out");
        }
        if ($mustContain !== '') {
            $this->assertStringContainsString($mustContain, $out);
        }
    }

    public static function attacks(): array
    {
        return [
            'script tag' => ['<p>Hi</p><script>alert(1)</script>', ['<script', 'alert'], 'Hi'],
            'script in caps with attrs' => ['<SCRIPT SRC=//evil.test/x.js></SCRIPT>ok', ['script', 'evil'], 'ok'],
            'event handler' => ['<p onclick="steal()" onmouseover=steal()>Hi</p>', ['onclick', 'onmouseover', 'steal'], 'Hi'],
            'img onerror' => ['<img src="x" onerror="alert(1)">', ['onerror', 'alert']],
            'javascript href' => ['<a href="javascript:alert(1)">x</a>', ['javascript']],
            'javascript href with entities and spaces' => ['<a href="  jav&#x09;ascript:alert(1)">x</a>', ['alert']],
            'javascript href in caps' => ['<a href="JaVaScRiPt:alert(1)">x</a>', ['javascript']],
            'vbscript' => ['<a href="vbscript:msgbox(1)">x</a>', ['vbscript']],
            'data html url' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:text']],
            'iframe' => ['<iframe src="//evil.test"></iframe>after', ['iframe', 'evil'], 'after'],
            // libxml treats <embed> as wrapping what follows, so text after it may be dropped too: the safe direction.
            'object and embed' => ['<object data="x"></object><embed src="x">t', ['object', 'embed']],
            'svg with script' => ['<svg onload="alert(1)"><script>alert(2)</script></svg>t', ['svg', 'alert'], 't'],
            'math tag' => ['<math><mi xlink:href="javascript:alert(1)">x</mi></math>', ['javascript', '<math']],
            'form and input' => ['<form action="//evil.test"><input name="x"><button>Go</button></form>', ['<form', 'evil', '<input', '<button']],
            'style tag' => ['<style>body{display:none}</style>text', ['<style', 'display'], 'text'],
            'style url' => ['<p style="background:url(javascript:alert(1))">x</p>', ['url(', 'javascript']],
            'style expression' => ['<p style="width:expression(alert(1))">x</p>', ['expression']],
            'style import' => ['<p style="color:red;@import \'x\'">x</p>', ['@import']],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=//evil.test">t', ['meta', 'evil'], 't'],
            'base tag' => ['<base href="//evil.test/">t', ['base', 'evil']],
            'html comment trick' => ['<!--<img src="--><img src=x onerror=alert(1)//">', ['onerror', 'alert']],
            'conditional comment' => ['<!--[if IE]><script>alert(1)</script><![endif]-->t', ['script', 'alert'], 't'],
            'nested broken tags' => ['<p><b><i>a</b></i><script>alert(1)</script></p>', ['<script', 'alert']],
            'srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>', ['srcdoc', 'alert']],
            'xlink' => ['<a xlink:href="javascript:alert(1)">x</a>', ['javascript']],
            'formaction' => ['<button formaction="javascript:alert(1)">x</button>', ['formaction', 'javascript']],
            'template' => ['<template><script>alert(1)</script></template>', ['template', 'alert']],
        ];
    }

    public function test_links_are_made_safe_to_open(): void
    {
        $out = HtmlCleaner::clean('<a href="https://example.com/page">read</a>');

        $this->assertStringContainsString('href="https://example.com/page"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
    }

    public function test_images_keep_http_and_inline_image_data_but_not_other_schemes(): void
    {
        $this->assertStringContainsString('src="https://x.test/a.png"', HtmlCleaner::clean('<img src="https://x.test/a.png" alt="d">'));
        $this->assertStringContainsString('src="/uploads/a.png"', HtmlCleaner::clean('<img src="/uploads/a.png">'));
        $this->assertStringContainsString('src="data:image/png;base64,iVBORw0KGgo="', HtmlCleaner::clean('<img src="data:image/png;base64,iVBORw0KGgo=">'));
        $this->assertStringNotContainsString('src=', HtmlCleaner::clean('<img src="data:image/svg+xml;base64,PHN2Zz4=">'), 'svg data can carry scripts');
        $this->assertStringNotContainsString('src=', HtmlCleaner::clean('<img src="javascript:alert(1)">'));
    }

    public function test_safe_styles_survive_and_unsafe_ones_go(): void
    {
        $out = HtmlCleaner::clean('<p style="text-align: center; color: #c00; position: fixed; top: 0">x</p>');

        $this->assertStringContainsString('text-align: center', $out);
        $this->assertStringContainsString('color: #c00', $out);
        $this->assertStringNotContainsString('position', $out);
    }

    public function test_unknown_tags_are_unwrapped_and_their_text_kept(): void
    {
        $this->assertSame('<p>keep this text</p>', HtmlCleaner::clean('<p><blink>keep <marquee>this</marquee> text</blink></p>'));
    }
}

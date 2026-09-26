<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Makes question HTML safe to show to students (on the web and inside offline packs).
 * Allowlist based: anything not known to be harmless is removed, so new tricks do not get through by default.
 * Question text is written by staff in a rich-text editor, so normal formatting, tables and images are kept.
 */
class HtmlCleaner
{
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup', 'dd', 'del', 'div', 'dl', 'dt', 'em',
        'figcaption', 'figure', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark',
        'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot',
        'th', 'thead', 'tr', 'u', 'ul',
    ];

    /** Removed together with everything inside them. Other unknown tags are unwrapped (their text is kept). */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'noscript', 'template',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'title', 'head', 'audio', 'video',
        'source', 'canvas', 'dialog',
    ];

    private const ALLOWED_ATTRS = ['alt', 'title', 'width', 'height', 'colspan', 'rowspan', 'align', 'dir', 'lang', 'class', 'href', 'src', 'style', 'color', 'size'];

    private const SAFE_STYLES = [
        'color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration', 'font-size', 'font-family',
        'width', 'height', 'max-width', 'vertical-align', 'line-height', 'text-indent', 'margin-left', 'padding-left',
        'border', 'border-collapse', 'border-color', 'border-style', 'border-width',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="tc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('tc-root');
        if (! $root) {
            return e(strip_tags($html));   // unparseable: fall back to plain text
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $parent->removeChild($node);
                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;   // text stays
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::walk($node);   // clean the children first, then lift them out
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            self::filterAttributes($node);
            self::walk($node);
        }
    }

    private static function filterAttributes(DOMElement $el): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue;

            $keep = in_array($name, self::ALLOWED_ATTRS, true) && ! str_starts_with($name, 'on');

            if ($keep && $name === 'href') { $keep = self::safeUrl($value, ['http', 'https', 'mailto']); }
            if ($keep && $name === 'src') { $keep = self::safeUrl($value, ['http', 'https'], true); }

            if (! $keep) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'style') {
                $safe = self::safeStyle($value);
                $safe === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $safe);
            }
        }

        if (strtolower($el->nodeName) === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener noreferrer');
            $el->setAttribute('target', '_blank');
        }
    }

    /** Relative and protocol-relative URLs are fine; so are the listed schemes. Anything else (javascript:, vbscript:...) is not. */
    private static function safeUrl(string $url, array $schemes, bool $allowImageData = false): bool
    {
        $u = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url)));

        if ($u === '' || str_starts_with($u, '#') || str_starts_with($u, '/') || str_starts_with($u, './') || str_starts_with($u, '../')) {
            return true;
        }
        if ($allowImageData && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#', $u)) {
            return true;
        }
        if (preg_match('#^([a-z][a-z0-9+.-]*):#', $u, $m)) {
            return in_array($m[1], $schemes, true);
        }

        return true;   // no scheme: a relative path
    }

    private static function safeStyle(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $decl) {
            if (! str_contains($decl, ':')) { continue; }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            $lower = strtolower($val);

            if (! in_array($prop, self::SAFE_STYLES, true)) { continue; }
            if (preg_match('/url\s*\(|expression|javascript|behaviou?r|@import|[<>\\\\]/i', $lower)) { continue; }

            $kept[] = "$prop: $val";
        }

        return implode('; ', $kept);
    }
}

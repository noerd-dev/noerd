<?php

declare(strict_types=1);

namespace Noerd\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitiser for tenant-editable rich text.
 *
 * The rich text editor (tiptap) stores real HTML, so the shop has to render it as markup
 * instead of escaping it. Everything outside the allow-list below is dropped: disallowed
 * elements keep their text but lose their tag, and the few elements that carry no useful
 * text at all (script, style, iframe, …) are removed with their content.
 */
final class HtmlSanitizer
{
    /**
     * Allowed elements mapped to the attributes they may keep.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ELEMENTS = [
        'p' => [],
        'br' => [],
        'hr' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'del' => [],
        'ins' => [],
        'sub' => [],
        'sup' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => [],
        'td' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title'],
    ];

    /**
     * Elements that are removed together with everything they contain.
     *
     * @var list<string>
     */
    private const DROPPED_ELEMENTS = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'form',
        'input', 'button', 'select', 'textarea', 'svg', 'math', 'link', 'meta', 'base',
    ];

    /**
     * URL schemes a href/src may use; everything else (javascript:, data:, …) is dropped.
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function sanitize(?string $html): string
    {
        $html = mb_trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($body);

        $result = '';

        foreach (iterator_to_array($body->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return mb_trim($result);
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            self::cleanNode($child);
        }
    }

    private static function cleanNode(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            if ($node->nodeType === XML_TEXT_NODE) {
                return;
            }

            $node->parentNode?->removeChild($node);

            return;
        }

        $name = mb_strtolower($node->nodeName);

        if (in_array($name, self::DROPPED_ELEMENTS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        self::cleanChildren($node);

        if (! array_key_exists($name, self::ALLOWED_ELEMENTS)) {
            self::unwrap($node);

            return;
        }

        self::cleanAttributes($node, self::ALLOWED_ELEMENTS[$name]);
    }

    /**
     * @param  list<string>  $allowedAttributes
     */
    private static function cleanAttributes(DOMElement $element, array $allowedAttributes): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = mb_strtolower($attribute->nodeName);

            if (! in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(?string $url): bool
    {
        $url = mb_trim((string) $url);

        if ($url === '') {
            return false;
        }

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $matches)) {
            // Relative URLs and fragments carry no scheme and are safe.
            return true;
        }

        return in_array(mb_strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Replace an element with its children, so its text survives but its tag does not.
     */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent instanceof DOMNode) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}

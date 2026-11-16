<?php
declare(strict_types=1);
namespace App;

final class RichText {
    private const TAGS = ['p','br','strong','em','u','s','h1','h2','h3','ol','ul','li','blockquote','pre','code','sub','sup','span','a','img'];
    private const DISCARD = ['script','style','iframe','object','embed','svg','math','template','form','input','button','textarea','select','option','video','audio','source','link','meta','base','noscript'];
    private const IMAGE = '#^/media/images/([a-f0-9]{40}\.(?:jpg|png|webp))$#D';

    public static function prepare(string $content, string $format, int $courseId, bool $required = false, int $max = 200000): string {
        if (!in_array($format, ['text','html'], true) || !preg_match('//u', $content) || strlen($content) > $max) {
            abort(422, 'รูปแบบหรือขนาดเนื้อหาไม่ถูกต้อง');
        }
        $safe = $format === 'html' ? self::sanitize($content) : $content;
        if (strlen($safe) > $max) abort(422, 'เนื้อหายาวเกินกำหนด');
        $images = $format === 'html' ? self::imagePaths($safe) : [];
        $text = $format === 'html' ? html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $safe;
        if ($required && !$images && preg_replace('/[\p{Z}\p{C}\s]+/u', '', $text) === '') {
            abort(422, 'กรุณาเพิ่มข้อความหรือรูปภาพในเนื้อหา');
        }
        if ($images) EditorImage::validateReferences($images, $courseId);
        return $safe;
    }

    public static function render(?string $content, ?string $format = 'text'): string {
        return $format === 'html' ? self::sanitize($content ?? '') : nl2br(e($content ?? ''));
    }

    public static function imagePaths(string $content): array {
        preg_match_all('#<img\s+src="/media/images/([a-f0-9]{40}\.(?:jpg|png|webp))"#', self::sanitize($content), $matches);
        return array_values(array_unique($matches[1]));
    }

    public static function sanitize(string $content): string {
        if ($content === '') return '';
        if (!preg_match('//u', $content)) return '';
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $content . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$loaded) return '';
            $body = $document->getElementsByTagName('body')->item(0);
            if (!$body) return '';
            $html = '';
            foreach ($body->childNodes as $node) $html .= self::node($node);
            return $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function node(\DOMNode $node): string {
        if ($node instanceof \DOMText) return e($node->data);
        if (!$node instanceof \DOMElement) return '';
        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DISCARD, true)) return '';
        $inner = '';
        foreach ($node->childNodes as $child) $inner .= self::node($child);
        if (!in_array($tag, self::TAGS, true)) return $inner;
        $attributes = '';
        if ($tag === 'img') {
            $source = $node->getAttribute('src');
            if (!preg_match(self::IMAGE, $source)) return '';
            $attributes .= ' src="' . e($source) . '"';
            if ($node->hasAttribute('alt')) $attributes .= ' alt="' . e(self::shortText($node->getAttribute('alt'))) . '"';
        }
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if (self::safeLink($href)) {
                $attributes .= ' href="' . e($href) . '"';
                if ($node->getAttribute('target') === '_blank') $attributes .= ' target="_blank" rel="noopener noreferrer"';
            }
            if ($node->hasAttribute('title')) $attributes .= ' title="' . e(self::shortText($node->getAttribute('title'))) . '"';
        }
        if ($tag !== 'img' && $tag !== 'br') {
            $classes = array_filter(preg_split('/\s+/', trim($node->getAttribute('class'))), static fn(string $class): bool => (bool)preg_match('/^ql-(?:align-(?:center|right|justify)|indent-[1-8]|size-(?:small|large|huge))$/D', $class));
            if ($classes) $attributes .= ' class="' . e(implode(' ', array_unique($classes))) . '"';
            $styles = self::styles($node->getAttribute('style'));
            if ($styles !== '') $attributes .= ' style="' . e($styles) . '"';
        }
        if ($tag === 'li' && in_array($node->getAttribute('data-list'), ['ordered','bullet','checked','unchecked'], true)) {
            $attributes .= ' data-list="' . e($node->getAttribute('data-list')) . '"';
        }
        return '<' . $tag . $attributes . '>' . (in_array($tag, ['img','br'], true) ? '' : $inner . '</' . $tag . '>');
    }

    private static function shortText(string $value): string {
        preg_match('/^.{0,200}/us', $value, $match);
        return $match[0] ?? '';
    }

    private static function safeLink(string $href): bool {
        if ($href === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $href)) return false;
        if (str_starts_with($href, '#')) return true;
        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) return true;
        if (preg_match('/^https?:\/\//i', $href)) return filter_var($href, FILTER_VALIDATE_URL) !== false;
        return str_starts_with(strtolower($href), 'mailto:') && filter_var(substr($href, 7), FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function styles(string $style): string {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) continue;
            $property = strtolower(trim($parts[0]));
            $value = strtolower(trim($parts[1]));
            if (!in_array($property, ['color','background-color'], true)) continue;
            if (preg_match('/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/D', $value) || in_array($value, ['black','white','red','green','blue','yellow','orange','purple','gray','grey','teal','navy','maroon','olive','lime','aqua','fuchsia','silver'], true)) {
                $safe[$property] = $value;
            } elseif (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/D', $value, $rgb) && max((int)$rgb[1], (int)$rgb[2], (int)$rgb[3]) <= 255) {
                $safe[$property] = 'rgb(' . (int)$rgb[1] . ', ' . (int)$rgb[2] . ', ' . (int)$rgb[3] . ')';
            }
        }
        return implode('; ', array_map(static fn(string $property, string $value): string => $property . ': ' . $value, array_keys($safe), $safe));
    }
}

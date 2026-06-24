<?php

namespace App\Services\Social;

class LinkedInTextHelper
{
    /**
     * Convert HTML from a contenteditable post body to plain UTF-8 text
     * suitable for LinkedIn's commentary field.
     *
     * LinkedIn does not render HTML — tags appear literally and must not be
     * present in the commentary. This method:
     *   - Converts block-close tags and <br> to newlines
     *   - Strips all remaining HTML tags
     *   - Decodes HTML entities
     *   - Collapses runs of 3+ newlines to two
     *   - Trims leading/trailing whitespace
     */
    public static function htmlToLinkedInText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Fast path: no tags at all
        if (! str_contains($html, '<')) {
            return trim(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $text = str_replace(["\r\n", "\r"], "\n", $html);

        // Block-close tags → newline
        $text = preg_replace(
            '/<\/(?:p|div|h[1-6]|ul|ol|li|blockquote|pre|section|article|header|footer|main)>/i',
            "\n",
            $text
        ) ?? $text;

        // <br> and <br/> → newline
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;

        // Strip all remaining tags
        $text = strip_tags($text);

        // Decode HTML entities (&amp; &lt; &gt; &nbsp; &#x…; etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse 3+ consecutive newlines → two (one blank line between paragraphs)
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}

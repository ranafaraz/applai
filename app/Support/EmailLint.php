<?php

namespace App\Support;

use App\Models\EmailMessage;

/**
 * Pre-send quality checks for outbound email. Surfaces the "looks
 * unprofessional" mistakes that are easy to ship by accident — a wall of text
 * with no paragraph breaks, a second sign-off on top of the signature, leftover
 * placeholder tokens, or encoding garbage from an upstream pipeline.
 *
 * Each issue is returned as ['level' => 'danger'|'warning', 'title', 'detail'].
 * "danger" = almost certainly wrong, fix before sending; "warning" = worth a
 * second look. Nothing here blocks a send — it only informs the human.
 */
class EmailLint
{
    /** Closing phrases that indicate the body already contains its own sign-off. */
    private const SIGNOFF_PHRASES = [
        'best', 'best regards', 'warm regards', 'kind regards', 'regards',
        'sincerely', 'thanks', 'thank you', 'many thanks', 'cheers',
        'yours truly', 'yours sincerely', 'respectfully', 'warmly',
    ];

    /**
     * @return array<int, array{level: string, title: string, detail: string}>
     */
    public static function check(EmailMessage $email): array
    {
        return self::inspect(
            (string) $email->subject,
            (string) $email->body,
            $email->email_signature_id !== null
                || str_contains((string) $email->body, 'data-email-signature'),
        );
    }

    /**
     * @return array<int, array{level: string, title: string, detail: string}>
     */
    public static function inspect(string $subject, string $body, bool $hasSignature): array
    {
        $issues = [];

        // Body without the signature block, as plain text, for content checks.
        $bodyNoSig = \App\Models\EmailSignature::stripSignatureHtml($body);
        $text = trim(html_entity_decode(strip_tags($bodyNoSig), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\x{00a0}/u', ' ', $text) ?? $text;

        // 1. Encoding garbage (U+FFFD replacement char) — em-dashes/quotes that
        //    got mangled upstream. Always a danger; it's visible to the recipient.
        if (str_contains($subject . $bodyNoSig, "\u{FFFD}")) {
            $issues[] = [
                'level'  => 'danger',
                'title'  => 'Garbled characters (�) detected',
                'detail' => 'Some characters (often dashes or quotes) are corrupted and show as �. Retype them before sending.',
            ];
        }

        // 2. Empty / weak subject.
        if (trim($subject) === '') {
            $issues[] = [
                'level'  => 'danger',
                'title'  => 'Subject is empty',
                'detail' => 'An empty subject line gets filtered as spam and looks careless.',
            ];
        } elseif (mb_strlen(trim($subject)) > 90) {
            $issues[] = [
                'level'  => 'warning',
                'title'  => 'Subject is very long',
                'detail' => 'Subjects over ~90 characters get truncated in most inboxes. Tighten it.',
            ];
        }

        // 3. Wall of text — long body with no paragraph structure.
        $paragraphCount = max(
            preg_match_all('/<p[\s>]/i', $bodyNoSig),
            count(preg_split('/\n\s*\n/', $text) ?: [1]),
        );
        $hasBreaks = (bool) preg_match('/<br\b|<\/p>\s*<p|<\/li>|<\/h[1-6]>/i', $bodyNoSig);
        if (mb_strlen($text) > 280 && $paragraphCount <= 1 && ! $hasBreaks) {
            $issues[] = [
                'level'  => 'danger',
                'title'  => 'One giant paragraph (wall of text)',
                'detail' => 'This email is a single block with no breaks — it reads as a wall of text. Split it into short paragraphs.',
            ];
        }

        // 4. Duplicate sign-off: body ends with its own closing AND a signature
        //    will be attached → the recipient sees two sign-offs.
        if ($hasSignature && self::hasOwnSignoff($text)) {
            $issues[] = [
                'level'  => 'warning',
                'title'  => 'Two sign-offs (closing + signature)',
                'detail' => 'The body already ends with a closing (e.g. "Best, …") and a signature is also attached. Remove the closing from the body so it appears only once.',
            ];
        }

        // 5. Leftover placeholder tokens.
        if (preg_match('/\[(?:name|first[\s_]?name|company|role|title|date|insert[^\]]*|x{3,})\]|\{\{\s*[\w.]+\s*\}\}|<insert[^>]*>|\bX{4,}\b|\bTBD\b|lorem ipsum/i', $text)) {
            $issues[] = [
                'level'  => 'danger',
                'title'  => 'Unfilled placeholder text',
                'detail' => 'Found a placeholder like [name], {{var}}, XXXX, or TBD. Replace it with real content.',
            ];
        }

        // 6. Missing greeting.
        if ($text !== '' && ! preg_match('/^\s*(hi|hello|hey|dear|good (morning|afternoon|evening)|greetings|to whom)/i', $text)) {
            $issues[] = [
                'level'  => 'warning',
                'title'  => 'No greeting',
                'detail' => 'The email does not open with a greeting (Hi / Hello / Dear …). Add one to set a professional tone.',
            ];
        }

        // 7. Shouty subject / excessive punctuation.
        if (preg_match('/[!?]{2,}/', $subject) || (mb_strlen($subject) > 6 && mb_strtoupper($subject) === $subject)) {
            $issues[] = [
                'level'  => 'warning',
                'title'  => 'Subject looks like spam',
                'detail' => 'ALL CAPS or repeated punctuation (!!!) trips spam filters and reads as unprofessional.',
            ];
        }

        return $issues;
    }

    private static function hasOwnSignoff(string $text): bool
    {
        // Look only at the tail of the message, where a closing would live.
        $tail = mb_substr($text, max(0, mb_strlen($text) - 200));
        foreach (self::SIGNOFF_PHRASES as $phrase) {
            if (preg_match('/(^|[\.\!\?\n]\s*|\n\s*)' . preg_quote($phrase, '/') . '\s*[,!\n]/i', $tail)) {
                return true;
            }
        }
        return false;
    }
}

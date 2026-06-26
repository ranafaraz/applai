<?php

namespace App\Services;

use App\Exceptions\DocumentExportException;
use App\Models\ApiDocumentVersion;
use App\Support\RichText;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Converts a content-document version (inline HTML / Markdown / plain text) into
 * any of the supported download formats. Generated binaries are cached on the
 * private disk keyed by (version_id, format, checksum) so repeat downloads are
 * cheap and deterministic.
 *
 * Markdown ↔ HTML uses league/commonmark (already vendored). PDF uses dompdf and
 * DOCX uses PhpWord — both pure-PHP so the locked deploy needs no system binaries
 * (the VPS has no LibreOffice).
 */
class DocumentExportService
{
    /** format => [extension, mime]. 'gdoc' aliases docx (Google Docs imports .docx losslessly). */
    public const FORMATS = [
        'html' => ['html', 'text/html'],
        'md'   => ['md',   'text/markdown'],
        'txt'  => ['txt',  'text/plain'],
        'pdf'  => ['pdf',  'application/pdf'],
        'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'gdoc' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'csv'  => ['csv',  'text/csv'],
    ];

    /**
     * @return array{body: string, mime: string, extension: string, filename: string}
     */
    public function export(ApiDocumentVersion $version, string $format, string $baseName): array
    {
        $format = strtolower(trim($format));

        if (! isset(self::FORMATS[$format])) {
            throw new DocumentExportException("Unsupported export format: {$format}", 422);
        }
        if (! $version->isContentDoc()) {
            throw new DocumentExportException('Export is only available for content documents (text). Use the download endpoint for stored files.', 422);
        }

        [$extension, $mime] = self::FORMATS[$format];
        $filename = $this->safeBase($baseName) . '.' . $extension;

        $cacheKey  = "private/exports/{$version->id}/" . ($version->checksum ?: 'nocs') . "-{$format}.{$extension}";
        if (Storage::disk('local')->exists($cacheKey)) {
            return ['body' => Storage::disk('local')->get($cacheKey), 'mime' => $mime, 'extension' => $extension, 'filename' => $filename];
        }

        $body = match ($format) {
            'html'         => $this->toFullHtml($version, $baseName),
            'md'           => $this->toMarkdown($version),
            'txt'          => $this->toPlainText($version),
            'pdf'          => $this->toPdf($version, $baseName),
            'docx', 'gdoc' => $this->toDocx($version),
            'csv'          => $this->toCsv($version),
        };

        Storage::disk('local')->put($cacheKey, $body);

        return ['body' => $body, 'mime' => $mime, 'extension' => $extension, 'filename' => $filename];
    }

    // ── Source resolution ──────────────────────────────────────────────────────

    /** Resolve the version's content to a body-level HTML fragment. */
    private function toHtmlFragment(ApiDocumentVersion $version): string
    {
        $body   = (string) $version->content_body;
        $format = $version->content_format ?: 'markdown';

        return match ($format) {
            'html'      => RichText::sanitize($body),
            'plaintext' => '<p>' . nl2br(e($body)) . '</p>',
            default     => $this->markdownToHtml($body),
        };
    }

    private function markdownToHtml(string $markdown): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return RichText::sanitize((string) $converter->convert($markdown));
    }

    // ── Per-format converters ──────────────────────────────────────────────────

    private function toFullHtml(ApiDocumentVersion $version, string $title): string
    {
        $fragment = $this->toHtmlFragment($version);

        return "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . e($title) . "</title>\n"
            . "<style>body{font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.6;color:#1e293b;max-width:800px;margin:2rem auto;padding:0 1rem;}"
            . "table{border-collapse:collapse;width:100%;margin:1rem 0;}th,td{border:1px solid #cbd5e1;padding:6px 10px;text-align:left;}"
            . "blockquote{border-left:3px solid #cbd5e1;margin:1rem 0;padding:.25rem 1rem;color:#475569;}"
            . "pre{background:#f1f5f9;padding:1rem;overflow:auto;}code{font-family:ui-monospace,monospace;}img{max-width:100%;}</style>\n"
            . "</head>\n<body>\n{$fragment}\n</body>\n</html>\n";
    }

    private function toMarkdown(ApiDocumentVersion $version): string
    {
        $format = $version->content_format ?: 'markdown';
        $body   = (string) $version->content_body;

        // Markdown/plaintext are already text — return verbatim. HTML has no
        // lossless pure-PHP path to Markdown without an extra dependency, so we
        // degrade to the readable text content.
        if ($format === 'markdown' || $format === 'plaintext') {
            return $body;
        }

        return $this->htmlToText($body);
    }

    private function toPlainText(ApiDocumentVersion $version): string
    {
        $format = $version->content_format ?: 'markdown';
        $body   = (string) $version->content_body;

        if ($format === 'plaintext') {
            return $body;
        }

        $html = $format === 'html' ? $body : $this->markdownToHtml($body);

        return $this->htmlToText($html);
    }

    private function toPdf(ApiDocumentVersion $version, string $title): string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            throw new DocumentExportException('PDF export is unavailable on this server (dompdf not installed).', 501);
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($this->toFullHtml($version, $title), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function toDocx(ApiDocumentVersion $version): string
    {
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            throw new DocumentExportException('DOCX export is unavailable on this server (PhpWord not installed).', 501);
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();

        // PhpWord's HTML reader is strict about well-formed markup; our fragment
        // is already sanitized/normalized by RichText, which keeps it happy.
        $fragment = $this->toHtmlFragment($version);

        try {
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $fragment, false, false);
        } catch (\Throwable $e) {
            // Fall back to plain text so we always return a valid .docx.
            $section->addText($this->toPlainText($version));
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    private function toCsv(ApiDocumentVersion $version): string
    {
        $format = $version->content_format ?: 'markdown';
        $body   = (string) $version->content_body;

        $rows = $format === 'markdown'
            ? $this->extractMarkdownTables($body)
            : $this->extractHtmlTables($format === 'html' ? $body : $this->markdownToHtml($body));

        if (empty($rows)) {
            throw new DocumentExportException('This document has no tabular data to export as CSV.', 422);
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            // Explicit escape ('') avoids PHP 8.4's deprecated default-escape notice.
            fputcsv($fh, $row, ',', '"', '');
        }
        rewind($fh);

        return (string) stream_get_contents($fh);
    }

    // ── Table extraction ───────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> */
    private function extractHtmlTables(string $html): array
    {
        if (! str_contains(strtolower($html), '<table')) {
            return [];
        }

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $rows = [];
        foreach ($doc->getElementsByTagName('table') as $table) {
            foreach ($table->getElementsByTagName('tr') as $tr) {
                $cells = [];
                foreach ($tr->childNodes as $cell) {
                    if ($cell->nodeType === XML_ELEMENT_NODE && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                        $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? '');
                    }
                }
                if ($cells !== []) {
                    $rows[] = $cells;
                }
            }
            $rows[] = []; // blank separator between multiple tables
        }

        return $this->trimTrailingBlank($rows);
    }

    /** @return array<int, array<int, string>> */
    private function extractMarkdownTables(string $markdown): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $markdown) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] !== '|') {
                continue;
            }
            // Skip the header-separator row (|---|---|).
            if (preg_match('/^\|?[\s:\-|]+\|?$/', $trimmed) && str_contains($trimmed, '-')) {
                continue;
            }
            $cells = array_map('trim', explode('|', trim($trimmed, '|')));
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /** Drop a trailing blank separator row if present. */
    private function trimTrailingBlank(array $rows): array
    {
        while ($rows !== [] && end($rows) === []) {
            array_pop($rows);
        }

        return $rows;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<\s*(br|\/p|\/div|\/li|\/h[1-6])\s*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function safeBase(string $name): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $name) ?? 'document';
        $safe = trim(preg_replace('/\s+/', ' ', $safe) ?? 'document');

        return substr($safe ?: 'document', 0, 120);
    }
}

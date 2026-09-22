<?php

declare(strict_types=1);

namespace App\Services;

use TCPDF;

/**
 * PdfService
 *
 * Wraps TCPDF (vendored in libs/tcpdf) to produce OXIAURA documents:
 *  - generateQuotation(): a personal letter-style quotation — branded
 *    letterhead, date, addressee, plan-specific projection table, benefits,
 *    signatory block, and a small NATIVE QR code (no GD) linking to the public
 *    verification page.
 *  - generateReport(): a generic tabular report used by ReportService.
 *
 * The letter is layout-driven by the stored projection (intro + headers + rows
 * + summary + benefits), so a single renderer serves every plan type.
 */
final class PdfService
{
    /** Brand colours (OXIAURA green / blue). */
    private const GREEN = '#1f7a34';
    private const BLUE  = '#1d4ed8';

    public function __construct()
    {
        require_once dirname(__DIR__, 2) . '/libs/tcpdf/tcpdf.php';
    }

    /**
     * Build a letter-style quotation PDF and return its raw bytes.
     *
     * @param array<string,mixed>  $quotation  Detailed quotation row (incl. customer_*).
     * @param array<string,mixed>  $projection Decoded projection JSON.
     * @param array<string,string> $settings   Company settings map.
     * @param string               $verifyUrl  Public verification URL (QR target).
     */
    public function generateQuotation(array $quotation, array $projection, array $settings, string $verifyUrl): string
    {
        return $this->render(function () use ($quotation, $projection, $settings, $verifyUrl) {
            $pdf = $this->newDocument($settings, 'Quotation ' . ($quotation['quotation_number'] ?? ''));
            $pdf->AddPage();

            $pdf->writeHTML($this->letterhead($settings), true, false, true, false, '');
            $pdf->writeHTML($this->letterBody($quotation, $projection, $settings), true, false, true, false, '');

            // Small native QR (needs no GD), placed just after the letter body
            // rather than at a fixed y — the letter now runs to a details table
            // plus summary and terms, so an absolute position would land on top
            // of the text. Falls to a fresh page if there isn't room.
            $y = $pdf->GetY() + 4;
            if ($y > 240) {
                $pdf->AddPage();
                $y = 20;
            }

            $style = ['border' => false, 'padding' => 1, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255]];
            $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', 15, $y, 24, 24, $style, 'N');
            $pdf->SetXY(42, $y + 4);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell(120, 4, "Scan to verify the authenticity of this quotation:\n" . $verifyUrl, 0, 'L');

            return $pdf->Output('quotation.pdf', 'S');
        });
    }

    /**
     * Build a generic report PDF (title + table) and return its raw bytes.
     *
     * @param string[]                     $headers
     * @param array<int,array<int,string>> $rows
     * @param array<string,string>         $settings
     * @param array<string,string>         $meta
     */
    public function generateReport(string $title, array $headers, array $rows, array $settings, array $meta = []): string
    {
        return $this->render(function () use ($title, $headers, $rows, $settings, $meta) {
            $pdf = $this->newDocument($settings, $title);
            $pdf->AddPage();
            $pdf->writeHTML($this->letterhead($settings), true, false, true, false, '');
            $pdf->writeHTML($this->reportHtml($title, $headers, $rows, $meta), true, false, true, false, '');

            return $pdf->Output('report.pdf', 'S');
        });
    }

    /**
     * Run a TCPDF build with warning OUTPUT suppressed and any stray echoes
     * captured, so PHP notices emitted by TCPDF internals (a known PHP 8 issue)
     * can never contaminate the returned binary. The PDF string itself is
     * always returned clean.
     *
     * @param callable():string $build
     */
    private function render(callable $build): string
    {
        $prevLevel = error_reporting();
        error_reporting($prevLevel & ~(E_WARNING | E_NOTICE | E_DEPRECATED));
        ob_start();
        try {
            return $build();
        } finally {
            ob_end_clean();
            error_reporting($prevLevel);
        }
    }

    /* ------------------------------------------------------------------ */

    /**
     * Create a base TCPDF document. We render our own letterhead in the body
     * (no TCPDF header/footer) for full control over the OXIAURA layout.
     *
     * @param array<string,string> $settings
     */
    private function newDocument(array $settings, string $docTitle): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OXIAURA QMS');
        $pdf->SetAuthor($settings['company_name'] ?? 'OXIAURA');
        $pdf->SetTitle($docTitle);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont('helvetica', '', 10);

        return $pdf;
    }

    /**
     * Resolve an absolute path to the uploaded logo, if present.
     *
     * @param array<string,string> $settings
     */
    private function logoPath(array $settings): ?string
    {
        $logo = $settings['company_logo'] ?? '';
        if ($logo === '') {
            return null;
        }
        $path = dirname(__DIR__, 2) . '/public/assets/uploads/' . basename($logo);

        return is_file($path) ? $path : null;
    }

    /**
     * Branded letterhead: logo/company on the left, contacts on the right.
     *
     * @param array<string,string> $settings
     */
    private function letterhead(array $settings): string
    {
        $esc  = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $logo = $this->logoPath($settings);

        $logoCell = $logo !== null
            ? '<img src="' . $esc($logo) . '" height="48">'
            : '';

        $name = $esc($settings['company_name'] ?? 'OXIAURA Plantation (PVT) LTD.');
        $reg  = !empty($settings['company_reg_no'])
            ? '<br/><span style="color:#888;font-size:8px">(' . $esc($settings['company_reg_no']) . ')</span>'
            : '';

        $contacts = [];
        foreach (['company_phone', 'company_email', 'company_website', 'company_address'] as $key) {
            if (!empty($settings[$key])) {
                $contacts[] = $esc($settings[$key]);
            }
        }
        $contactHtml = implode('<br/>', $contacts);

        return '
        <table cellpadding="4">
            <tr>
                <td width="55%">' . $logoCell
                    . '<span style="color:' . self::GREEN . ';font-size:16px;font-weight:bold;">' . $name . '</span>' . $reg . '</td>
                <td width="45%" align="right"
    style="font-size:8.5px;color:#333;vertical-align:top;padding-bottom:0">' . $contactHtml . '</td>
            </tr>
        </table>
        <div style="border-bottom:2px solid ' . self::GREEN . ';">&nbsp;</div><br/>';
    }

    /**
     * The letter body: date, addressee, salutation, opening paragraph, the
     * "Investment Plan Details" table, then the plan's Investment Summary and
     * Terms & Conditions, and finally the signatory block.
     *
     * Every section below the opening paragraph is driven entirely by the
     * stored projection, so this one renderer serves all six plan types — and
     * any plan type added later — without knowing their specifics.
     *
     * @param array<string,mixed>  $q
     * @param array<string,mixed>  $projection
     * @param array<string,string> $settings
     */
    private function letterBody(array $q, array $projection, array $settings): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $date     = date('d-M-y', strtotime((string) ($q['created_at'] ?? 'now')));
        $customer = $esc($q['customer_name'] ?? '');
        $address  = $esc(trim((string) ($q['customer_address'] ?? '')));
        $intro    = $esc($projection['intro'] ?? '');

        // Prefer the structured sectioned table; fall back to the flat
        // headers/rows table for quotations issued before this layout existed.
        $details = $projection['details'] ?? null;
        $table   = is_array($details) && !empty($details['sections'])
            ? $this->detailsTable($details)
            : $this->legacyTable($projection);

        // Investment Summary / Terms & Conditions were resolved from the plan's
        // `${token}` templates when the quotation was created.
        $summaryLines = (array) ($projection['summary_lines'] ?? []);
        $termsLines   = (array) ($projection['terms_lines'] ?? []);

        $summaryHtml = $this->listFromLines($summaryLines, false);
        $termsHtml   = $this->listFromLines($termsLines, true);

        $signName  = $esc($q['created_by_name'] ?? '');
        $signTitle = $esc($q['created_by_position'] ?? '');
        $contactPerson = $esc($q['created_by_phone'] ?? '');
        $expiry    = !empty($q['expiry_date']) ? date('jS \o\f F Y', strtotime((string) $q['expiry_date'])) : null;

        $benefitsHtml = $this->benefitsBlock((string) ($projection['benefits'] ?? ''), $esc);

        return '
        <style>
            table.proj { border-collapse: collapse; }
            table.proj th, table.proj td { border: 1px solid #cbd5e1; font-size: 10px; padding: 7px; }
            .sect-h { font-size: 10.5px; color: ' . self::GREEN . '; font-weight: bold; }
        </style>

        <p style="font-size:10px">' . $date . '<br/>' . $customer
            . ($address !== '' ? '<br/>' . nl2br($address) : '') . '</p>
        <p style="font-size:10px">Dear Sir/ Madam</p>
        <p style="font-size:10px">' . $intro . '</p>
        ' . $table . '
        ' . ($summaryHtml !== '' ? '<p class="sect-h">Investment Summary</p>' . $summaryHtml : '') . '
        ' . ($termsHtml !== '' ? '<p class="sect-h">Terms &amp; Conditions</p>' . $termsHtml : '') . '
        ' . $benefitsHtml . '
        ' . ($expiry ? '<p style="font-size:9px;color:#666">This quotation is valid until ' . $expiry . '.</p>' : '') . '
        <br/>
        <p style="font-size:10px">Thank you,<br/><br/><strong>' . $signName . '</strong>'
            . ($signTitle !== '' ? '<br/>' . $signTitle : '') . '</p>'
            . ($contactPerson !== '' ? '<p style="font-size:9px;color:#666">Contact: ' . $contactPerson . '</p>' : '');
    }

    /**
     * Render the structured "Investment Plan Details" table: a titled list of
     * sections, each holding label/value rows. Empty rows were already dropped
     * by AbstractPlanType::details().
     *
     * @param array<string,mixed> $details
     */
    private function detailsTable(array $details): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $head = '';
        foreach (($details['headers'] ?? ['Description', 'Details']) as $h) {
            $head .= '<th style="background-color:' . self::GREEN . ';color:#fff;font-weight:bold;">' . $esc($h) . '</th>';
        }

        $body = '';
        foreach (($details['sections'] ?? []) as $section) {
            $title = trim((string) ($section['title'] ?? ''));
            if ($title !== '') {
                $body .= '<tr><td colspan="2" style="background-color:#eef7ee;color:' . self::GREEN . ';font-weight:bold;">'
                    . $esc($title) . '</td></tr>';
            }
            foreach (($section['rows'] ?? []) as $row) {
                $body .= '<tr><td>' . $esc($row['label'] ?? '') . '</td><td>' . $esc($row['value'] ?? '') . '</td></tr>';
            }
        }

        return '<p class="sect-h">' . $esc($details['title'] ?? 'Investment Plan Details') . '</p>'
            . '<table class="proj" width="100%" cellpadding="7"><thead><tr>' . $head . '</tr></thead><tbody>'
            . $body . '</tbody></table>';
    }

    /**
     * Flat headers/rows table — the pre-existing layout, kept so quotations
     * issued before the sectioned details table still render correctly.
     *
     * @param array<string,mixed> $projection
     */
    private function legacyTable(array $projection): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $headers = $projection['headers'] ?? [];
        if ($headers === [] && empty($projection['rows'])) {
            return '';
        }

        $head = '';
        foreach ($headers as $h) {
            $head .= '<th style="background-color:' . self::GREEN . ';color:#fff;font-weight:bold;">' . $esc($h) . '</th>';
        }

        $body = '';
        foreach (($projection['rows'] ?? []) as $row) {
            $body .= '<tr>';
            foreach ($row as $cell) {
                $body .= '<td align="center">' . $esc($cell) . '</td>';
            }
            $body .= '</tr>';
        }

        return '<table class="proj" width="100%" cellpadding="7"><thead><tr>' . $head . '</tr></thead><tbody>'
            . $body . '</tbody></table>';
    }

    /**
     * Render pre-resolved lines as either a bulleted or numbered list.
     *
     * Emitted as <br/>-joined lines inside a <p>, not <ul>/<ol> or a nested
     * table. TCPDF's list handling emits PHP 8 warnings (see class notes), and
     * a nested table leaves the write cursor inside its last cell — which
     * drifts every following block to the right.
     *
     * @param string[] $lines
     */
    private function listFromLines(array $lines, bool $numbered): string
    {
        if ($lines === []) {
            return '';
        }

        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $out = [];
        foreach (array_values($lines) as $i => $line) {
            $marker = $numbered ? ($i + 1) . '.&nbsp; ' : '&#8226;&nbsp; ';
            $out[]  = $marker . $esc($line);
        }

        return '<p style="font-size:9.5px;line-height:1.5">' . implode('<br/>', $out) . '</p>';
    }

    /**
     * The optional "Benefits & Conditions" block (one bullet per line).
     *
     * @param callable(mixed):string $esc
     */
    private function benefitsBlock(string $benefits, callable $esc): string
    {
        $benefits = trim($benefits);
        if ($benefits === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $benefits) ?: [];
        $items = [];
        foreach ($lines as $line) {
            // Strip any leading bullet/dash/whitespace (Unicode-aware) and
            // re-add a clean HTML-entity bullet — TCPDF's core font renders a
            // literal "•" as mojibake, but the entity maps correctly.
            $clean = trim((string) preg_replace('/^[\x{2022}\x{00B7}\-\*\s]+/u', '', trim($line)));
            if ($clean !== '') {
                $items[] = $clean;
            }
        }

        if ($items === []) {
            return '';
        }

        return '<p class="sect-h">Benefits &amp; Conditions</p>'
            . $this->listFromLines($items, false);
    }

    /**
     * Generic report table HTML (used after the letterhead).
     *
     * @param string[]                     $headers
     * @param array<int,array<int,string>> $rows
     * @param array<string,string>         $meta
     */
    private function reportHtml(string $title, array $headers, array $rows, array $meta): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $head = '';
        foreach ($headers as $h) {
            $head .= '<th style="background-color:' . self::BLUE . ';color:#fff">' . $esc($h) . '</th>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($row as $cell) {
                $body .= '<td>' . $esc($cell) . '</td>';
            }
            $body .= '</tr>';
        }
        if ($rows === []) {
            $body = '<tr><td colspan="' . count($headers) . '" align="center">No data.</td></tr>';
        }

        $metaHtml = '';
        foreach ($meta as $k => $v) {
            $metaHtml .= '<tr><td><strong>' . $esc($k) . ':</strong></td><td>' . $esc($v) . '</td></tr>';
        }
        if ($metaHtml !== '') {
            $metaHtml = '<table cellpadding="2" style="font-size:10px">' . $metaHtml . '</table><br/>';
        }

        return '
        <style>
            table.rep { border-collapse: collapse; }
            table.rep th, table.rep td { border-bottom: 1px solid #e2e8f0; font-size: 9px; padding: 5px; }
        </style>
        <h2 style="color:' . self::BLUE . ';font-size:15px">' . $esc($title) . '</h2>
        <p style="font-size:9px;color:#64748b">Generated on ' . date('d M Y H:i') . '</p>
        ' . $metaHtml . '
        <table class="rep" width="100%" cellpadding="5"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>';
    }
}

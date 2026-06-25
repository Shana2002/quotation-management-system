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

            // Small native QR (needs no GD) anchored near the bottom of the page.
            $style = ['border' => false, 'padding' => 1, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255]];
            $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', 15, 250, 24, 24, $style, 'N');
            $pdf->SetXY(42, 254);
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
            ? '<img src="' . $esc($logo) . '" height="48">&nbsp;<br>'
            : '';

        $name = $esc($settings['company_name'] ?? 'OXIAURA Plantation (PVT) LTD.');
        $reg  = !empty($settings['company_reg_no']) ? '<br/><span style="color:#888;font-size:8px">(' . $esc($settings['company_reg_no']) . ')</span>' : '';

        $contacts = [];
        foreach (['company_phone' => 'Tel', 'company_email' => 'Email', 'company_website' => 'Web', 'company_address' => 'Address'] as $key => $label) {
            if (!empty($settings[$key])) {
                $contacts[] = $esc($settings[$key]);
            }
        }
        $contactHtml = implode('<br/>', $contacts);

        return '
        <table cellpadding="4"><tr>
            <td width="55%">' . $logoCell
                . '<span style="color:' . self::GREEN . ';font-size:16px;font-weight:bold;">' . $name . '</span>' . $reg . '</td>
            <td width="45%" align="right" style="font-size:8.5px;color:#333">' . $contactHtml . '</td>
        </tr></table>
        <div style="border-bottom:2px solid ' . self::GREEN . ';">&nbsp;</div><br/>';
    }

    /**
     * The letter body: date, addressee, salutation, title, intro, projection
     * table, benefits and signatory block.
     *
     * @param array<string,mixed>  $q
     * @param array<string,mixed>  $projection
     * @param array<string,string> $settings
     */
    private function letterBody(array $q, array $projection, array $settings): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $date     = date('jS \o\f F Y', strtotime((string) ($q['created_at'] ?? 'now')));
        $customer = $esc($q['customer_name']);
        $city     = $esc($this->lastAddressLine((string) ($q['customer_address'] ?? '')));
        $first    = $esc($this->firstName((string) ($q['customer_name'] ?? 'Customer')));
        $title    = $esc($projection['letter_title'] ?? 'Investment Proposal');
        $intro    = $esc($projection['intro'] ?? '');

        // Projection table.
        $headers = $projection['headers'] ?? [];
        $rows    = $projection['rows'] ?? [];
        $head = '';
        foreach ($headers as $h) {
            $head .= '<th style="background-color:' . self::GREEN . ';color:#fff;font-weight:bold;">' . $esc($h) . '</th>';
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($row as $cell) {
                $body .= '<td align="center">' . $esc($cell) . '</td>';
            }
            $body .= '</tr>';
        }

        // Benefits (one bullet per line). Rendered as <br/>-separated lines —
        // TCPDF's <ul>/<li> handling emits warnings on PHP 8, so we avoid it.
        $benefits = trim((string) ($projection['benefits'] ?? ''));
        $benefitsHtml = '';
        if ($benefits !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $benefits) ?: [];
            $items = [];
            foreach ($lines as $line) {
                // Strip any leading bullet/dash/whitespace (Unicode-aware) and
                // re-add a clean HTML-entity bullet — TCPDF's core font renders
                // a literal "•" as mojibake, but the entity maps correctly.
                $clean = trim((string) preg_replace('/^[\x{2022}\x{00B7}\-\*\s]+/u', '', trim($line)));
                if ($clean !== '') {
                    $items[] = '&#8226; ' . $esc($clean);
                }
            }
            $benefitsHtml = '<br/><strong>Benefits &amp; Conditions</strong>'
                . '<p style="font-size:9px;color:#444;line-height:1.5">' . implode('<br/>', $items) . '</p>';
        }

        $signName  = $esc($q['created_by_name'] ?? '');
        $signTitle = $esc($q['created_by_position'] ?? '');
        $contactPerson = $esc($q['created_by_phone'] ?? '');
        $expiry    = !empty($q['expiry_date']) ? date('jS \o\f F Y', strtotime((string) $q['expiry_date'])) : null;

        return '
        <style>
            table.proj { border-collapse: collapse; }
            table.proj th, table.proj td { border: 1px solid #cbd5e1; font-size: 10px; padding: 7px; }
        </style>

        <table cellpadding="2"><tr>
            <td width="60%" style="font-size:10px"><strong>' . $date . '</strong><br/>'
                . $customer . ($city !== '' ? '<br/>' . $city : '') . '.</td>
            <td width="40%" align="right" style="font-size:9px;color:#555">Ref: ' . $esc($q['quotation_number']) . '</td>
        </tr></table>
        <br/>
        <p style="font-size:10px">Dear ' . $first . ',</p>
        <p align="center" style="font-size:12px"><strong><u>' . $title . '</u></strong></p>
        <p style="font-size:10px">Dear Valuable Customer, ' . $intro . '</p>
        <br/>
        <table class="proj" width="100%" cellpadding="7"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>
        ' . $benefitsHtml . '
        ' . ($expiry ? '<p style="font-size:9px;color:#666">This quotation is valid until ' . $expiry . '.</p>' : '') . '
        <br/><br/>
        <p style="font-size:10px">Thank You,<br/><br/><strong>' . $signName . '</strong>'
            . ($signTitle !== '' ? '<br/>' . $signTitle . '.' : '') . '</p>'
            . ($contactPerson !== '' ? '<p style="font-size:9px;color:#666">Contact: ' . $contactPerson . '</p>' : '');
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

    /** Extract a likely given name (strip a leading honorific). */
    private function firstName(string $fullName): string
    {
        $clean = preg_replace('/^\s*(Mr|Mrs|Ms|Miss|Dr|Rev)\.?\s+/i', '', trim($fullName)) ?? $fullName;
        $parts = preg_split('/\s+/', trim($clean)) ?: [];

        return $parts[0] ?? $fullName;
    }

    /** The last non-empty line of an address (typically the city). */
    private function lastAddressLine(string $address): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $address) ?: [])));

        return $lines === [] ? '' : end($lines);
    }
}

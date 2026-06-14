<?php

declare(strict_types=1);

namespace App\Services;

use TCPDF;

/**
 * PdfService
 *
 * Wraps TCPDF (vendored in libs/tcpdf) to produce professional A4 documents:
 *  - generateQuotation(): a full quotation with logo, breakdown, terms,
 *    signature block and a NATIVE QR code (no GD required) linking to the
 *    public verification page.
 *  - generateReport(): a generic tabular report used by ReportService.
 */
final class PdfService
{
    public function __construct()
    {
        // Vendored TCPDF (installed via scripts/install_tcpdf.ps1).
        require_once dirname(__DIR__, 2) . '/libs/tcpdf/tcpdf.php';
    }

    /**
     * Build a quotation PDF and return its raw bytes.
     *
     * @param array<string,mixed>            $quotation  Detailed quotation row.
     * @param array<int,array<string,mixed>> $items      Line items.
     * @param array<string,string>           $settings   Company settings map.
     * @param string                         $verifyUrl  Public verification URL (QR target).
     */
    public function generateQuotation(array $quotation, array $items, array $settings, string $verifyUrl): string
    {
        $pdf = $this->newDocument($settings, 'Quotation ' . ($quotation['quotation_number'] ?? ''));
        $pdf->AddPage();

        $pdf->writeHTML($this->quotationHtml($quotation, $items, $settings), true, false, true, false, '');

        // Native QR code (2D barcode) — drawn directly, needs no GD extension.
        $style = [
            'border' => false,
            'padding' => 1,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => [255, 255, 255],
        ];
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', 15, $pdf->GetY() + 4, 30, 30, $style, 'N');
        $pdf->SetXY(48, $pdf->GetY() + 6);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(120, 5, "Scan to verify this quotation online:\n" . $verifyUrl, 0, 'L');

        return $pdf->Output('quotation.pdf', 'S');
    }

    /**
     * Build a generic report PDF (title + table) and return its raw bytes.
     *
     * @param string[]                       $headers
     * @param array<int,array<int,string>>   $rows
     * @param array<string,string>           $settings
     * @param array<string,string>           $meta     Optional summary key=>value rows.
     */
    public function generateReport(string $title, array $headers, array $rows, array $settings, array $meta = []): string
    {
        $pdf = $this->newDocument($settings, $title);
        $pdf->AddPage();
        $pdf->writeHTML($this->reportHtml($title, $headers, $rows, $settings, $meta), true, false, true, false, '');

        return $pdf->Output('report.pdf', 'S');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Create and configure a base TCPDF document with company header/footer.
     *
     * @param array<string,string> $settings
     */
    private function newDocument(array $settings, string $docTitle): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('QMS');
        $pdf->SetAuthor($settings['company_name'] ?? 'QMS');
        $pdf->SetTitle($docTitle);

        $pdf->SetMargins(15, 38, 15);
        $pdf->SetHeaderMargin(8);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 18);

        // Company header (logo + name) rendered on every page.
        $logoFile = $this->logoPath($settings);
        $companyName = $settings['company_name'] ?? 'Company';
        $pdf->setHeaderData(
            $logoFile ?? '',
            $logoFile ? 24 : 0,
            $companyName,
            $this->companyContactLine($settings),
            [37, 99, 235],
            [37, 99, 235]
        );
        $pdf->setHeaderFont(['helvetica', 'B', 13]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->setFooterData([100, 100, 100], [200, 200, 200]);

        $pdf->SetFont('helvetica', '', 10);

        return $pdf;
    }

    /**
     * Resolve an absolute path to the uploaded logo, if it exists.
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
     * @param array<string,string> $settings
     */
    private function companyContactLine(array $settings): string
    {
        $parts = array_filter([
            $settings['company_address'] ?? '',
            $settings['company_phone'] ?? '',
            $settings['company_email'] ?? '',
        ]);

        return implode("\n", $parts);
    }

    /**
     * Build the quotation body as HTML for TCPDF::writeHTML.
     *
     * @param array<string,mixed>            $q
     * @param array<int,array<string,mixed>> $items
     * @param array<string,string>           $settings
     */
    private function quotationHtml(array $q, array $items, array $settings): string
    {
        $currency = $settings['currency_symbol'] ?? 'Rs.';
        $fmt = static fn ($n) => $currency . ' ' . number_format((float) $n, 2);
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        // Line item rows.
        $rowsHtml = '';
        $i = 1;
        foreach ($items as $item) {
            $rowsHtml .= '<tr>'
                . '<td align="center">' . $i++ . '</td>'
                . '<td>' . $esc($item['description']) . '</td>'
                . '<td align="center">' . number_format((float) $item['quantity'], 2) . '</td>'
                . '<td align="right">' . $fmt($item['unit_price']) . '</td>'
                . '<td align="right">' . $fmt($item['line_total']) . '</td>'
                . '</tr>';
        }

        $statusLabel = ucfirst((string) ($q['status'] ?? 'draft'));
        $terms = $q['terms'] ?? ($settings['default_terms'] ?? '');

        $notesBlock = '';
        if (!empty($q['notes'])) {
            $notesBlock = '<p><strong>Notes:</strong><br>' . nl2br($esc($q['notes'])) . '</p>';
        }

        return '
        <style>
            h1 { color: #2563eb; font-size: 18px; }
            .meta td { font-size: 10px; padding: 2px 0; }
            .box { border: 1px solid #e2e8f0; }
            table.items { border-collapse: collapse; }
            table.items th { background-color: #2563eb; color: #ffffff; font-size: 10px; padding: 6px; }
            table.items td { border-bottom: 1px solid #e2e8f0; font-size: 10px; padding: 6px; }
            .totals td { font-size: 10px; padding: 4px 6px; }
            .grand { background-color: #f1f5f9; font-weight: bold; font-size: 12px; }
            .muted { color: #64748b; font-size: 9px; }
        </style>

        <h1>QUOTATION</h1>
        <table class="meta" cellpadding="0">
            <tr>
                <td width="55%"><strong>Bill To:</strong><br>'
                    . '<strong>' . $esc($q['customer_name']) . '</strong><br>'
                    . nl2br($esc($q['customer_address'] ?? '')) . '<br>'
                    . 'NIC: ' . $esc($q['customer_nic'] ?? '—') . '<br>'
                    . 'Tel: ' . $esc($q['customer_telephone'] ?? '—') . '<br>'
                    . ($q['customer_email'] ? 'Email: ' . $esc($q['customer_email']) : '') .
                '</td>
                <td width="45%" align="right">
                    <strong>No:</strong> ' . $esc($q['quotation_number']) . '<br>
                    <strong>Date:</strong> ' . $esc(date('d M Y', strtotime((string) ($q['created_at'] ?? 'now')))) . '<br>
                    <strong>Valid Until:</strong> ' . $esc($q['expiry_date'] ? date('d M Y', strtotime((string) $q['expiry_date'])) : '—') . '<br>
                    <strong>Status:</strong> ' . $esc($statusLabel) . '<br>
                    <strong>Prepared By:</strong> ' . $esc($q['created_by_name'] ?? '—') . '
                </td>
            </tr>
        </table>
        <br>

        <table class="items" cellpadding="6" width="100%" border="0">
            <thead>
                <tr>
                    <th width="7%">#</th>
                    <th width="48%">Description</th>
                    <th width="12%">Qty</th>
                    <th width="16%">Unit Price</th>
                    <th width="17%">Amount</th>
                </tr>
            </thead>
            <tbody>' . $rowsHtml . '</tbody>
        </table>
        <br>

        <table width="100%" cellpadding="0"><tr>
            <td width="55%" valign="top">' . $notesBlock . '</td>
            <td width="45%">
                <table class="totals" width="100%" cellpadding="4">
                    <tr><td>Subtotal:</td><td align="right">' . $fmt($q['subtotal']) . '</td></tr>
                    <tr><td>Discount:</td><td align="right">- ' . $fmt($q['discount']) . '</td></tr>
                    <tr><td>Tax:</td><td align="right">' . $fmt($q['tax']) . '</td></tr>
                    <tr class="grand"><td>TOTAL:</td><td align="right">' . $fmt($q['total']) . '</td></tr>
                </table>
            </td>
        </tr></table>
        <br>

        <p class="muted"><strong>Terms &amp; Conditions</strong><br>' . nl2br($esc($terms)) . '</p>
        <br><br><br>

        <table width="100%"><tr>
            <td width="50%">_____________________________<br><span class="muted">Authorized Signature</span></td>
            <td width="50%" align="right">_____________________________<br><span class="muted">Customer Acceptance</span></td>
        </tr></table>
        ';
    }

    /**
     * Build a generic report table as HTML.
     *
     * @param string[]                     $headers
     * @param array<int,array<int,string>> $rows
     * @param array<string,string>         $settings
     * @param array<string,string>         $meta
     */
    private function reportHtml(string $title, array $headers, array $rows, array $settings, array $meta): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $head = '';
        foreach ($headers as $h) {
            $head .= '<th>' . $esc($h) . '</th>';
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
            $metaHtml = '<table cellpadding="2" style="font-size:10px">' . $metaHtml . '</table><br>';
        }

        return '
        <style>
            h1 { color: #2563eb; font-size: 16px; }
            table.rep { border-collapse: collapse; }
            table.rep th { background-color: #2563eb; color: #fff; font-size: 9px; padding: 5px; }
            table.rep td { border-bottom: 1px solid #e2e8f0; font-size: 9px; padding: 5px; }
        </style>
        <h1>' . $esc($title) . '</h1>
        <p style="font-size:9px;color:#64748b">Generated on ' . date('d M Y H:i') . '</p>
        ' . $metaHtml . '
        <table class="rep" width="100%" cellpadding="5"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>
        ';
    }
}

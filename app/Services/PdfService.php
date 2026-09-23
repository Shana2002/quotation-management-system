<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quotation;
use TCPDF;

/**
 * PdfService
 *
 * Wraps TCPDF (vendored in libs/tcpdf) to produce OXIAURA documents:
 *  - generateQuotation(): the personal letter-style quotation the company
 *    actually issues — a full-bleed letterhead image with the letter set on
 *    top of it.
 *  - generateReport(): a generic tabular report used by ReportService.
 *
 * The letter's anatomy and geometry are taken from a real issued quotation
 * ("Royal Plus Quotation New II.pdf"), which is the reference for this
 * layout: A4, Calibri (Carlito), all-black text, a flat 2-column details
 * table, and hanging-indent summary/terms lists.
 *
 * Everything below the letterhead is driven by the stored projection, so this
 * one renderer serves every plan type without knowing its specifics.
 */
final class PdfService
{
    /** Report accent colour (OXIAURA blue). */
    private const BLUE = '#1d4ed8';

    /* ------------------------------------------------------------------
     * Letter geometry, in millimetres, measured from the reference letter.
     * ------------------------------------------------------------------ */

    private const L_MARGIN = 19.05;   // 54.0 pt
    /**
     * Where the first line of the letter's ink sits, not where TCPDF's line box
     * starts: TCPDF draws a paragraph's first line a shade below the y it is
     * given, so the reference's 125.9 pt is set here as 44.05 mm.
     */
    private const T_MARGIN = 44.05;
    private const R_MARGIN = 17.09;   // 48.5 pt
    private const CONTENT_W = 173.86; // 210 - L_MARGIN - R_MARGIN

    /*
     * Where the letter's text must stop, measured from the foot of the page.
     *
     * The letterhead is a full-page background, and the strip of partner logos
     * across its foot begins at about 272 mm — so the reference letter's own
     * last line, the signatory, sits at 265 mm and leaves the artwork clear.
     * A letter that runs past one page has to break at the same place, which is
     * what this margin is for: 30 mm puts the break at 267 mm, just above the
     * logos.
     */
    private const B_MARGIN = 30;

    /*
     * Vertical rhythm: the space between one block of letterBody() and the next,
     * in millimetres. Each figure is the reference letter's own spacing less
     * what TCPDF's line box already contributes, so that the ink — not the box —
     * lands where the reference puts it.
     */
    private const GAP_ADDR      = 7.37;  // date block  → salutation
    private const GAP_SALUTE    = 2.97;  // salutation  → opening paragraph
    private const GAP_INTRO     = 5.15;  // paragraph   → details heading
    private const GAP_DETAILS   = 6.16;  // heading     → details table
    private const GAP_SUMMARY   = 6.20;  // table       → summary heading
    private const GAP_SUMMARY_L = 0.60;  // heading     → summary list
    private const GAP_TERMS     = 0.94;  // summary     → terms heading
    private const GAP_TERMS_L   = 0.38;  // heading     → terms list
    private const GAP_SIGNOFF   = 4.34;  // terms       → "Thank you,"
    private const GAP_SIGNATURE = 10.88; // signoff     → signature block

    private const TBL_X      = 34.85; // table is centred on the page
    private const TBL_W      = 139.89;
    private const TBL_C1     = 63.20; // Description column, to the reference's rule
    private const TBL_ROW_H  = 6.14;  // 17.4 pt
    private const TBL_HEAD_H = 6.48;  // 18.4 pt

    /*
     * The reference letter's table is not a collapsed grid. Every cell carries
     * its own rule, the cells are set apart — 2.05 pt between the columns,
     * 2.16 pt between the rows — and the table draws a rule of its own a further
     * 1.8 pt outside the cells. A collapsed grid draws each interior boundary
     * once, which reads visibly lighter than the original.
     */
    private const TBL_SPACING_H = 0.723;  // 2.05 pt — between the two columns
    private const TBL_SPACING_V = 0.762;  // 2.16 pt — between the rows
    private const TBL_FRAME_IN = 0.635;   // 1.80 pt — table rule outside the cells
    private const TBL_CELL1_W   = 61.898; // 175.46 pt — first column's cell
    /**
     * Where the cell grid starts, relative to the cell TCPDF lays the first
     * row's text out in (-2.86 pt).
     *
     * The two are not the same box. TCPDF centres a row's text in the cell it
     * is given, whereas the reference carries each row's spacing above its rule:
     * every rule sits just under its own row's text. Anchoring the grid to the
     * reference's own placement — measured against a row's text, which is what
     * the reader sees — keeps the rules on the letters' lines.
     */
    private const TBL_GRID_SHIFT = -1.009;

    private const LIST_MARKER_X = 25.40; // 72.0 pt — bullet / "1."
    private const LIST_TEXT_X   = 31.75; // 90.0 pt — text, and wrapped lines

    private const BODY_PT = 11;
    private const HEAD_PT = 12;
    private const LINE_H  = 4.74;  // 13.44 pt — list line height

    /**
     * The table's own text insets, in millimetres. The reference tucks the
     * label ~1.06 mm in from the left rule but lets the value run to within
     * 0.18 mm of the right one; TCPDF applies a single padding to both sides,
     * so the pair is set for the duration of the table and restored after.
     */
    private const TBL_PAD_L = 1.06;
    private const TBL_PAD_R = 0.18;

    /**
     * Line heights as a multiple of the font size — what TCPDF's HTML renderer
     * means by `line-height`. The reference letter uses two settings: the
     * date/addressee block is set loosely at 15.48 pt on 11 pt type, while the
     * body paragraph is set solid at 13.32 pt.
     */
    private const ADDR_LH = 1.407; // 15.48 pt
    private const BODY_LH = 1.211; // 13.32 pt
    private const HEAD_LH = 1.0;   // section headings sit on their own baseline

    /**
     * Leading for the hanging lists. MultiCell takes its line height from the
     * document's cell-height ratio rather than from the $h argument, so the
     * lists are driven by this — 13.44 pt on 11 pt type, as in the reference.
     */
    private const LIST_LH = 1.222;

    /** Cached font family: 'carlito' when installed, else TCPDF's Helvetica. */
    private static ?string $font = null;

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
     * @param string               $verifyUrl  Unused by the letter; retained so
     *                                         the controller's call stays stable.
     */
    public function generateQuotation(array $quotation, array $projection, array $settings, string $verifyUrl = ''): string
    {
        return $this->render(function () use ($quotation, $projection, $settings) {
            $pdf = $this->newLetterDocument($settings);
            $pdf->AddPage();
            $this->letterBody($pdf, $quotation, $projection);

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
    /* Font                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * The letter is set in Calibri. Carlito is a metric-compatible clone, so
     * TCPDF is asked for it when scripts/install_fonts.ps1 has been run, and
     * falls back to Helvetica otherwise — a checkout that skipped the font
     * step should still produce a usable PDF rather than fatal.
     */
    private function font(): string
    {
        if (self::$font === null) {
            $installed = is_file(dirname(__DIR__, 2) . '/libs/tcpdf/fonts/carlito.php');
            self::$font = $installed ? 'carlito' : 'helvetica';
        }

        return self::$font;
    }

    /* ------------------------------------------------------------------ */
    /* Documents                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * The letter document: A4 with the letterhead image painted behind the
     * content of every page.
     *
     * @param array<string,string> $settings
     */
    private function newLetterDocument(array $settings): LetterPdf
    {
        $pdf = new LetterPdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OXIAURA QMS');
        $pdf->SetAuthor($settings['company_name'] ?? 'OXIAURA');
        $pdf->SetTitle('Quotation');
        // The letterhead is the page background, so TCPDF's own header must be
        // on (Header() paints it) but must not reserve any vertical space.
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        $pdf->setHeaderMargin(0);
        $pdf->SetMargins(self::L_MARGIN, self::T_MARGIN, self::R_MARGIN);
        $pdf->SetAutoPageBreak(true, self::B_MARGIN);
        $pdf->SetFont($this->font(), '', self::BODY_PT);
        $pdf->SetTextColor(0, 0, 0);
        // TCPDF insets every cell's text by 1 mm on each side by default. The
        // letter positions its text by hand — the hanging lists in particular,
        // whose markers must land on an exact x — so that inset is removed and
        // the table re-applies its own (asymmetric) insets around itself.
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellHeightRatio(self::LIST_LH);

        $background = $this->letterheadPath($settings);
        if ($background !== null) {
            $pdf->setBackgroundImage($background);
        }

        return $pdf;
    }

    /**
     * Create a base TCPDF document for reports, which are internal tabular
     * documents rather than letters and so keep the coded letterhead.
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
     * Resolve the letterhead image: an admin upload if configured, otherwise
     * the copy of the issued letterhead shipped with the app.
     *
     * @param array<string,string> $settings
     */
    private function letterheadPath(array $settings): ?string
    {
        $root = dirname(__DIR__, 2);

        $uploaded = trim((string) ($settings['letterhead_image'] ?? ''));
        if ($uploaded !== '') {
            $path = $root . '/public/assets/uploads/' . basename($uploaded);
            if (is_file($path)) {
                return $path;
            }
        }

        $default = $root . '/public/assets/img/letterhead-default.jpg';

        return is_file($default) ? $default : null;
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

    /* ------------------------------------------------------------------ */
    /* The letter                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Draw the letter: date, addressee, salutation, opening paragraph, the
     * "Investment Plan Details" table, then the plan's Investment Summary and
     * Terms & Conditions, and finally the signatory block.
     *
     * @param array<string,mixed> $q
     * @param array<string,mixed> $projection
     */
    private function letterBody(LetterPdf $pdf, array $q, array $projection): void
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $date     = date('d-M-y', strtotime((string) ($q['created_at'] ?? 'now')));
        $customer = $esc($q['customer_name'] ?? '');
        $address  = trim((string) ($q['customer_address'] ?? ''));
        $intro    = $esc($projection['intro'] ?? '');

        // Date block, then the addressee beneath it, sharing one paragraph's
        // line spacing exactly as the reference does.
        $this->paragraph($pdf, $date . '<br/>' . $customer . ($address !== '' ? '<br/>' . nl2br($address) : ''), self::ADDR_LH);
        $this->gap($pdf, self::GAP_ADDR);
        $this->paragraph($pdf, 'Dear Sir/ Madam', self::ADDR_LH);
        $this->gap($pdf, self::GAP_SALUTE);
        $this->paragraph($pdf, $intro);
        $this->gap($pdf, self::GAP_INTRO);
        $this->heading($pdf, 'Investment Plan Details', self::BODY_PT);
        $this->gap($pdf, self::GAP_DETAILS);

        $details = $projection['details'] ?? null;
        if (is_array($details) && Quotation::detailRows($projection) !== []) {
            $this->detailsTable($pdf, $details);
        } elseif (!empty($projection['headers']) || !empty($projection['rows'])) {
            // Quotations issued before the details table existed.
            $this->legacyTable($pdf, $projection);
        }

        $this->gap($pdf, self::GAP_SUMMARY);
        $this->heading($pdf, 'Investment Summary');
        $this->gap($pdf, self::GAP_SUMMARY_L);
        $this->hangingList($pdf, (array) ($projection['summary_lines'] ?? []), false);
        $this->gap($pdf, self::GAP_TERMS);
        $this->heading($pdf, 'Terms & Conditions');
        $this->gap($pdf, self::GAP_TERMS_L);
        $this->hangingList($pdf, (array) ($projection['terms_lines'] ?? []), true);

        // "Thank you," and the signatory are one closing block, so the break is
        // taken before them rather than inside them: a letter whose signatory is
        // named on one page and given their title on the next reads as a fault.
        // Nothing measured here is drawn — on a letter that fits, this does
        // nothing at all.
        $signName  = $esc($q['created_by_name'] ?? '');
        $signTitle = $esc($q['created_by_position'] ?? '');
        $signatory = implode('<br/>', array_filter([$signName, $signTitle], static fn ($v) => $v !== ''));

        $signLines    = $signatory === '' ? 0 : substr_count($signatory, '<br/>') + 1;
        $closingLines = 1 + $signLines;
        $this->pageBreak(
            $pdf,
            self::textHeight($closingLines, self::ADDR_LH) + self::GAP_SIGNOFF + self::GAP_SIGNATURE
        );

        $this->gap($pdf, self::GAP_SIGNOFF);
        $this->paragraph($pdf, 'Thank you,', self::ADDR_LH);

        $this->gap($pdf, self::GAP_SIGNATURE);
        if ($signatory !== '') {
            $this->paragraph($pdf, $signatory, self::ADDR_LH);
        }
    }

    /**
     * How tall `$lines` lines of letter text stand, in millimetres.
     *
     * Letter text is set at a fixed point size with its leading expressed as a
     * multiple of it — TCPDF's cell-height ratio — so a block's height follows
     * from the line count alone. Used to decide whether a block that must not be
     * split will fit before it is drawn.
     */
    private static function textHeight(int $lines, float $ratio, int $pt = self::BODY_PT): float
    {
        return $lines * $ratio * $pt * 25.4 / 72;
    }

    /**
     * Draw a block of body text at the current position.
     *
     * The leading is set through CSS `line-height`, which TCPDF maps onto its
     * internal cell-height ratio — a multiple of the font size, which is what
     * the reference's absolute 13.32 pt and 15.48 pt leadings come to here.
     * `margin:0` cancels the top/bottom margin TCPDF would otherwise add to a
     * `<p>`, so letterBody's own gaps alone control the vertical rhythm.
     */
    private function paragraph(LetterPdf $pdf, string $html, float $ratio = self::BODY_LH): void
    {
        $pdf->SetFont($this->font(), '', self::BODY_PT);
        $pdf->writeHTML(
            '<p style="font-size:' . self::BODY_PT . 'pt;line-height:' . $ratio . ';margin:0;">' . $html . '</p>',
            true,
            false,
            true,
            false,
            ''
        );
    }

    /** Draw a section heading. The details heading is set at body size. */
    private function heading(LetterPdf $pdf, string $text, float $pt = self::HEAD_PT): void
    {
        $pdf->SetFont($this->font(), '', $pt);
        $pdf->writeHTML(
            '<p style="font-size:' . $pt . 'pt;line-height:' . self::HEAD_LH . ';margin:0;">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>',
            true,
            false,
            true,
            false,
            ''
        );
    }

    /** Advance the write cursor by a fixed vertical gap, in millimetres. */
    private function gap(LetterPdf $pdf, float $mm): void
    {
        $pdf->SetY($pdf->GetY() + $mm);
    }

    /**
     * Draw the flat "Investment Plan Details" table: two columns, a hairline
     * grid, header centred and the values right-aligned, on no fill — so the
     * letterhead's watermark stays visible through the table.
     *
     * Drawn with the Cell()/Rect() API rather than writeHTML because TCPDF's
     * HTML renderer has no per-side border control (no border-collapse, no
     * border-left) and no way to set cells apart from one another, which is
     * exactly the treatment the reference letter's table has.
     *
     * @param array<string,mixed> $details
     */
    private function detailsTable(LetterPdf $pdf, array $details): void
    {
        $headers = (array) ($details['headers'] ?? ['Description', 'Details']);
        // Flattens the sectioned shape older quotations carry — see the model.
        $rows    = Quotation::detailRows(['details' => $details]);

        $pad = $pdf->getCellPaddings();
        $pdf->setCellPaddings(self::TBL_PAD_L, 0, self::TBL_PAD_R, 0);

        $widths    = [self::TBL_CELL1_W, self::TBL_W - 2 * self::TBL_FRAME_IN - self::TBL_CELL1_W - self::TBL_SPACING_H];
        $gridTop   = $pdf->GetY() + self::TBL_GRID_SHIFT;
        $gridStart = $gridTop;
        $page      = $pdf->getPage();

        $this->tableRow($pdf, (string) ($headers[0] ?? ''), (string) ($headers[1] ?? ''), self::TBL_HEAD_H, true, $widths, $gridTop);
        $gridTop += self::TBL_HEAD_H;
        foreach ($rows as $row) {
            $this->tableRow(
                $pdf,
                html_entity_decode((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8'),
                html_entity_decode((string) ($row['value'] ?? ''), ENT_QUOTES, 'UTF-8'),
                self::TBL_ROW_H,
                false,
                $widths,
                $gridTop
            );
            $gridTop += self::TBL_ROW_H;
        }

        $pdf->setCellPaddings($pad['L'], $pad['T'], $pad['R'], $pad['B']);

        if ($pdf->getPage() === $page) {
            $this->tableFrame($pdf, $gridStart, $gridTop);
        }
    }

    /**
     * One table row: label left, value right, each inside a cell of its own.
     *
     * @param float[] $widths
     */
    private function tableRow(LetterPdf $pdf, string $label, string $value, float $height, bool $header, array $widths, float $gridTop): void
    {
        $this->pageBreak($pdf, $height);

        $y = $pdf->GetY();

        $pdf->SetFont($this->font(), '', $header ? self::HEAD_PT : self::BODY_PT);

        $pdf->SetXY(self::TBL_X, $y);
        $pdf->Cell(self::TBL_C1, $height, $label, 0, 0, $header ? 'C' : 'L', false, '', 0, false, 'T', 'M');

        $pdf->SetXY(self::TBL_X + self::TBL_C1, $y);
        $pdf->Cell(self::TBL_W - self::TBL_C1, $height, $value, 0, 0, $header ? 'C' : 'R', false, '', 0, false, 'T', 'M');

        $this->tableCells($pdf, $gridTop, $height, $widths);

        $pdf->SetY($y + $height);
    }

    /**
     * Draw one row's cell rules.
     *
     * Each cell is a rectangle of its own, inset from the row's band by half the
     * row spacing, so that consecutive rows show the reference's two rules with
     * a hairline of paper between them.
     *
     * @param float[] $widths
     */
    private function tableCells(LetterPdf $pdf, float $gridTop, float $height, array $widths): void
    {
        $top = $gridTop + self::TBL_SPACING_V / 2;
        $h   = $height - self::TBL_SPACING_V;
        $x   = self::TBL_X + self::TBL_FRAME_IN;

        foreach ($widths as $w) {
            $pdf->Rect($x, $top, $w, $h);
            $x += $w + self::TBL_SPACING_H;
        }
    }

    /**
     * Draw the table's own rule around the completed cell grid.
     *
     * Called once the whole table has been laid out, because the rule needs the
     * table's full height. The caller skips it when the table ran over a page
     * break: the rule describes the table as a whole, and a fragment of it
     * drawn down one page would not describe anything.
     */
    private function tableFrame(LetterPdf $pdf, float $gridTop, float $gridBottom): void
    {
        $pdf->Rect(
            self::TBL_X,
            $gridTop + self::TBL_SPACING_V / 2 - self::TBL_FRAME_IN,
            self::TBL_W,
            $gridBottom - $gridTop - self::TBL_SPACING_V + 2 * self::TBL_FRAME_IN
        );
    }

    /**
     * Flat headers/rows table — the pre-existing layout, kept so quotations
     * issued before the details table still render.
     *
     * @param array<string,mixed> $projection
     */
    private function legacyTable(LetterPdf $pdf, array $projection): void
    {
        $headers = (array) ($projection['headers'] ?? []);
        $rows    = (array) ($projection['rows'] ?? []);
        if ($headers === [] && $rows === []) {
            return;
        }

        $cols = max(1, count($headers));
        $esc  = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        // Column width inside the cell grid, so the legacy table keeps the same
        // spaced-cell treatment as the details table.
        $gridW  = self::TBL_W - 2 * self::TBL_FRAME_IN;
        $w      = ($gridW - ($cols - 1) * self::TBL_SPACING_H) / $cols;
        $widths = array_fill(0, $cols, $w);

        $pad  = $pdf->getCellPaddings();
        $pdf->setCellPaddings(self::TBL_PAD_L, 0, self::TBL_PAD_R, 0);

        $gridTop   = $pdf->GetY() + self::TBL_GRID_SHIFT;
        $gridStart = $gridTop;
        $page      = $pdf->getPage();

        $this->tableRowRaw($pdf, array_map($esc, $headers), $widths, self::TBL_HEAD_H, true, $gridTop);
        $gridTop += self::TBL_HEAD_H;
        foreach ($rows as $row) {
            $this->tableRowRaw($pdf, array_map($esc, (array) $row), $widths, self::TBL_ROW_H, false, $gridTop);
            $gridTop += self::TBL_ROW_H;
        }

        $pdf->setCellPaddings($pad['L'], $pad['T'], $pad['R'], $pad['B']);

        if ($pdf->getPage() === $page) {
            $this->tableFrame($pdf, $gridStart, $gridTop);
        }
    }

    /**
     * @param string[] $cells
     * @param float[]  $widths
     */
    private function tableRowRaw(LetterPdf $pdf, array $cells, array $widths, float $height, bool $header, float $gridTop): void
    {
        $this->pageBreak($pdf, $height);

        $y = $pdf->GetY();
        $pdf->SetFont($this->font(), '', $header ? self::HEAD_PT : self::BODY_PT);

        $x = self::TBL_X + self::TBL_FRAME_IN;
        foreach ($cells as $i => $cell) {
            $w = $widths[$i] ?? end($widths);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $height, $cell, 0, 0, $header ? 'C' : 'L', false, '', 0, false, 'T', 'M');
            $x += $w + self::TBL_SPACING_H;
        }

        $this->tableCells($pdf, $gridTop, $height, $widths);

        $pdf->SetY($y + $height);
    }

    /**
     * Render pre-resolved lines as a bulleted or numbered list with a hanging
     * indent, so a line that wraps aligns under the text rather than under the
     * marker — matching the reference letter.
     *
     * @param string[] $lines
     */
    private function hangingList(LetterPdf $pdf, array $lines, bool $numbered): void
    {
        if ($lines === []) {
            return;
        }

        $textW = self::L_MARGIN + self::CONTENT_W - self::LIST_TEXT_X;
        $markW = self::LIST_TEXT_X - self::LIST_MARKER_X;

        foreach (array_values($lines) as $i => $line) {
            $pdf->SetFont($this->font(), '', self::BODY_PT);

            // Break for the whole item, not for one line of it. A long item wraps
            // to two or three lines, and MultiCell would happily break inside it,
            // leaving the item's marker stranded at the foot of the page it broke
            // away from. getStringHeight() measures the wrap without drawing it.
            $height = max(self::LINE_H, $pdf->getStringHeight($textW, (string) $line));
            $this->pageBreak($pdf, $height);

            $y0 = $pdf->GetY();

            // Body first, so MultiCell computes the wrapped height and moves
            // the cursor; the marker is then drawn back at the original y.
            $pdf->SetXY(self::LIST_TEXT_X, $y0);
            $pdf->MultiCell($textW, self::LINE_H, (string) $line, 0, 'L', false, 1, '', '', true, 0, false, false, 0, 'T');

            $y1 = $pdf->GetY();

            // "1." reads better flush with the marker column; the bullet is
            // drawn as a real glyph via the same entity the rest of the app
            // uses (TCPDF's core font renders a literal bullet as mojibake).
            $marker = $numbered ? ($i + 1) . '.' : "\u{2022}";
            $pdf->SetXY(self::LIST_MARKER_X, $y0);
            $pdf->Cell($markW, self::LINE_H, $marker, 0, 0, 'L', false, '', 0, false, 'T', 'T');

            $pdf->SetY($y1);
        }
    }

    /** Start a new page when the next block would not fit. */
    private function pageBreak(LetterPdf $pdf, float $height): void
    {
        if ($pdf->GetY() + $height > $pdf->pageBreakTrigger()) {
            $pdf->AddPage();
            $pdf->SetY(self::T_MARGIN);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Reports                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Branded letterhead for reports: logo/company on the left, contacts right.
     *
     * @param array<string,string> $settings
     */
    private function letterhead(array $settings): string
    {
        $esc  = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $logo = $this->logoPath($settings);

        $logoCell = $logo !== null ? '<img src="' . $esc($logo) . '" height="48">' : '';

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
                    . '<span style="color:#1f7a34;font-size:16px;font-weight:bold;">' . $name . '</span>' . $reg . '</td>
                <td width="45%" align="right"
    style="font-size:8.5px;color:#333;vertical-align:top;padding-bottom:0">' . $contactHtml . '</td>
            </tr>
        </table>
        <div style="border-bottom:2px solid #1f7a34;">&nbsp;</div><br/>';
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

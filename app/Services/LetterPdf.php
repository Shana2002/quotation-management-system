<?php

declare(strict_types=1);

namespace App\Services;

use TCPDF;

/**
 * LetterPdf
 *
 * TCPDF with a full-bleed letterhead painted behind the content of every page.
 *
 * The OXIAURA letterhead (flag bar, logo, contact block, rule, watermark and
 * the partner-logo strip along the foot) is supplied as a single A4 image, so
 * it is placed as a page background rather than drawn from settings.
 *
 * This is a subclass purely so the image can be painted from Header(): TCPDF
 * calls Header() on every page it starts — including the pages it adds itself
 * on an automatic page break — whereas a one-shot Image() call straight after
 * AddPage() would leave pages 2 and beyond unbranded.
 */
final class LetterPdf extends TCPDF
{
    /** Absolute path to the A4 letterhead image, or '' for none. */
    private string $backgroundImage = '';

    /**
     * TCPDF stamps every document it closes with a 1 pt "Powered by TCPDF"
     * hyperlink along the foot of the last page. It is set by the TCPDF
     * constructor, which offers no way to switch it off afterwards, and it has
     * no business on a letter that goes to a customer.
     */
    public function __construct(
        string $orientation = 'P',
        string $unit = 'mm',
        mixed $format = 'A4',
        bool $unicode = true,
        string $encoding = 'UTF-8',
        bool $diskcache = false,
        bool $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->tcpdflink = false;
    }

    public function setBackgroundImage(string $path): void
    {
        $this->backgroundImage = $path;
    }

    /**
     * The y beyond which TCPDF would start a new page.
     *
     * TCPDF keeps this as a protected property and exposes no getter, so
     * PdfService — which decides for itself when a table row or list item will
     * not fit — reads it through here rather than reaching into the parent.
     */
    public function pageBreakTrigger(): float
    {
        return $this->PageBreakTrigger;
    }

    /**
     * Paint the letterhead behind this page's content.
     *
     * The image is stretched to the exact A4 box (210 x 297 mm). That is safe
     * here because the artwork is authored at A4 proportions — 1131 x 1600 px
     * is an aspect ratio of 0.707, against A4's 0.707 — so the stretch is
     * sub-pixel and it guarantees a true full bleed with no white edge.
     *
     * Automatic page breaking is switched off for the duration of the call
     * because TCPDF's Image() otherwise runs the box through fitBlock(), which
     * — whenever AutoPageBreak is on, whether or not $fitonpage was asked for —
     * shrinks anything taller than the page's writable height. That would
     * letterbox the letterhead into the text area instead of bleeding it off
     * the paper. Nothing else is drawn in between, so no break is being
     * suppressed that ought to happen; the flag is put back immediately.
     */
    public function Header(): void
    {
        if ($this->backgroundImage === '' || !is_file($this->backgroundImage)) {
            return;
        }

        // setAutoPageBreak() writes the break margin as well as the flag, and that
        // margin defaults to 0 — so the flag has to be restored together with the
        // margin it was set with, or the first page's Header() would quietly reset
        // the document's bottom margin to zero and let later pages run their text
        // off the foot of the paper.
        $autoPageBreak = $this->AutoPageBreak;
        $breakMargin   = $this->getBreakMargin();
        $this->setAutoPageBreak(false);
        try {
            $this->Image(
                $this->backgroundImage,
                0,      // x
                0,      // y
                210,    // w  (full A4 width)
                297,    // h  (full A4 height)
                '',     // type (inferred from the file)
                '',     // link
                '',     // align
                false,  // resize
                300,    // dpi (irrelevant once w and h are both given)
                '',     // palign
                false,  // ismask
                false,  // imgmask
                0,      // border
                false,  // fitbox — stretch to the box rather than letterbox
                false,  // hidden
                false   // fitonpage
            );
        } finally {
            $this->setAutoPageBreak($autoPageBreak, $breakMargin);
        }
    }
}

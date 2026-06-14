<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Quotation;
use App\Models\Setting;

/**
 * VerifyController
 *
 * Public, no-login landing page for the QR code printed on quotation PDFs.
 * Shows a minimal, read-only authenticity summary for a verification token.
 */
final class VerifyController extends Controller
{
    public function show(string $token): void
    {
        // Tokens are 64 hex chars; reject anything malformed early.
        $valid = preg_match('/^[a-f0-9]{16,64}$/i', $token) === 1;
        $quotation = $valid ? (new Quotation())->findByToken($token) : null;

        $this->view('verify/show', [
            'title'     => 'Quotation Verification',
            'quotation' => $quotation,
            'company'   => (new Setting())->get('company_name', 'Company'),
        ], 'layouts/auth');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\UploadService;

/**
 * SettingController — admin-managed company info, branding and defaults
 * used throughout the app and on generated PDFs.
 */
final class SettingController extends Controller
{
    /** Editable text setting keys. */
    private const KEYS = [
        'company_name', 'company_reg_no', 'company_address', 'company_phone',
        'company_email', 'company_website', 'quotation_prefix', 'tax_rate',
        'currency_symbol', 'signatory_name', 'signatory_title', 'default_terms',
    ];

    public function index(): void
    {
        $this->view('settings/index', [
            'title'    => 'Settings',
            'settings' => (new Setting())->allAsMap(),
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();

        $input = $this->request->only(self::KEYS);
        $validator = new Validator($input, [
            'company_name'   => 'required|max:150',
            'company_email'  => 'email|max:190',
            'tax_rate'       => 'numeric',
            'quotation_prefix' => 'required|max:10',
        ]);

        if ($validator->fails()) {
            $this->back('/settings', $validator->flatErrors(), $input);
            return;
        }

        $settings = new Setting();

        // Handle optional logo upload.
        $logo = $this->request->file('logo');
        if ($logo !== null && ($logo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $uploader = new UploadService();
                $filename = $uploader->storeImage($logo, 'logo');

                // Remove the previous logo file if any.
                $old = $settings->get('company_logo');
                if ($old !== '') {
                    $uploader->delete($old);
                }
                $settings->put('company_logo', $filename);
            } catch (\Throwable $e) {
                Flash::error('Logo upload failed: ' . $e->getMessage());
                $this->back('/settings', [], $input);
                return;
            }
        }

        // Persist text settings.
        $pairs = [];
        foreach (self::KEYS as $key) {
            $pairs[$key] = (string) ($input[$key] ?? '');
        }
        $settings->putMany($pairs);

        ActivityLog::log('update', 'settings', null, 'Updated company settings');
        Flash::success('Settings saved successfully.');
        $this->redirect('/settings');
    }
}

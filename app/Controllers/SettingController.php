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

        // Handle the optional image uploads — the logo and the full-page letter
        // background. Both are stored and replaced the same way, so they share
        // one block: each is skipped when no file was sent, and the file it
        // replaces is deleted only after the new one is safely stored.
        foreach (['logo' => 'company_logo', 'letterhead' => 'letterhead_image'] as $field => $key) {
            $file = $this->request->file($field);
            if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            try {
                $uploader = new UploadService();
                $filename = $uploader->storeImage($file, $field);

                $old = $settings->get($key);
                if ($old !== '') {
                    $uploader->delete($old);
                }
                $settings->put($key, $filename);
            } catch (\Throwable $e) {
                Flash::error(ucfirst($field) . ' upload failed: ' . $e->getMessage());
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

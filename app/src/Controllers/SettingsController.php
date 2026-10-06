<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\Upload;

final class SettingsController extends Controller
{
    private const KEYS = ['org_name_en', 'org_name_ar', 'currency_code', 'currency_decimals', 'currency_symbol_en', 'currency_symbol_ar', 'default_lang', 'review_warning_days', 'incident_escalation_threshold', 'accept_level2_min', 'mfa_required'];

    public function index(): string
    {
        return $this->view('admin/settings', ['title' => t('admin.settings'), 's' => Settings::all()]);
    }

    public function save(): never
    {
        $v = new Validator($_POST);
        $v->required('org_name_en', 'settings.org_name_en')->max('org_name_en', 'settings.org_name_en', 150)->required('org_name_ar', 'settings.org_name_ar')->max('org_name_ar', 'settings.org_name_ar', 150)
            ->in('default_lang', 'settings.default_lang', ['ar', 'en'])->in('currency_decimals', 'settings.currency_decimals', ['0', '1', '2', '3'])
            ->required('currency_symbol_en', 'settings.symbol_en')->max('currency_symbol_en', 'settings.symbol_en', 10)->required('currency_symbol_ar', 'settings.symbol_ar')->max('currency_symbol_ar', 'settings.symbol_ar', 10)
            ->intRange('review_warning_days', 'settings.review_warning_days', 0, 365);
        if ($v->str('accept_level2_min') !== '') {
            $v->intRange('accept_level2_min', 'settings.accept_level2', 1, 25);
        }
        if (!preg_match('/^[A-Z]{3}$/', strtoupper($v->str('currency_code')))) {
            $v->fail('currency_code', 'settings.err_currency');
        }
        $th = $v->str('incident_escalation_threshold');
        if ($th !== '' && \App\Core\Money::parse($th, \App\Core\Money::decimals()) === null) {
            $v->fail('incident_escalation_threshold', 'validation.amount', ['field' => t('settings.incident_threshold')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('admin/settings'));
        }
        $before = Settings::all();
        foreach (self::KEYS as $k) {
            $val = $k === 'currency_code' ? strtoupper($v->str($k)) : $v->str($k);
            if (($before[$k] ?? null) !== $val) {
                Settings::set($k, $val);
                $changed[$k] = [$before[$k] ?? null, $val];
            }
        }
        $this->audit('settings.updated', 'settings', null, ['changes' => $changed ?? []]);
        $this->redirect(url('admin/settings'), 'admin.saved');
    }
}

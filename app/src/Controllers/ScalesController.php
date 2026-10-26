<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Settings;
use App\Support\Ref;
use App\Support\Scoring;

final class ScalesController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/scales', [
            'title' => t('scales.title'), 'lik' => Ref::levels('likelihood'), 'imp' => Ref::levels('impact'), 'bands' => Ref::bands(),
            'approved' => Settings::get('thresholds_approved') === '1', 'approvedBy' => Settings::get('thresholds_approved_by'), 'approvedAt' => Settings::get('thresholds_approved_at'),
        ]);
    }

    private function unapprove(): void
    {
        Settings::set('thresholds_approved', '0');
    }

    public function saveLevels(): never
    {
        $errors = [];
        $rows = [];
        $prev = -1;
        foreach (['likelihood', 'impact'] as $scale) {
            for ($l = 1; $l <= 5; $l++) {
                $k = $scale . '_' . $l;
                $en = trim((string)($_POST[$k . '_label_en'] ?? ''));
                $ar = trim((string)($_POST[$k . '_label_ar'] ?? ''));
                $de = trim((string)($_POST[$k . '_desc_en'] ?? ''));
                $da = trim((string)($_POST[$k . '_desc_ar'] ?? ''));
                if ($en === '' || $ar === '' || mb_strlen($en) > 80 || mb_strlen($ar) > 80) {
                    $errors[$k . '_label_en'] = t('scales.err_labels');
                }
                if (mb_strlen($de) > 2000 || mb_strlen($da) > 2000) {
                    $errors[$k . '_desc_en'] = t('validation.max_length', ['field' => t('scales.description'), 'max' => 2000]);
                }
                $fin = null;
                if ($scale === 'impact') {
                    $fin = Money::parse((string)($_POST[$k . '_fin'] ?? ''), Money::decimals());
                    if ($fin === null) {
                        $errors[$k . '_fin'] = t('validation.amount', ['field' => t('scales.financial_min')]);
                    } elseif ($l === 1 && $fin !== 0) {
                        $errors[$k . '_fin'] = t('scales.err_fin_first');
                    } elseif ($l > 1 && $fin <= $prev) {
                        $errors[$k . '_fin'] = t('scales.err_fin_increasing');
                    }
                    $prev = $fin ?? $prev;
                }
                $rows[] = [$scale, $l, $en, $ar, $de, $da, $fin];
            }
        }
        if ($errors) {
            $this->invalid($errors, url('admin/scales'));
        }
        $now = date('Y-m-d H:i:s');
        Db::tx(function () use ($rows, $now) {
            foreach ($rows as [$scale, $l, $en, $ar, $de, $da, $fin]) {
                Db::update('rating_levels', ['label_en' => $en, 'label_ar' => $ar, 'description_en' => $de ?: null, 'description_ar' => $da ?: null, 'financial_min' => $fin !== null ? Money::toDb($fin) : null, 'updated_at' => $now], 'scale = ? AND level = ?', [$scale, $l]);
            }
        });
        $this->unapprove();
        $this->audit('scales.levels_updated', 'scales');
        $this->redirect(url('admin/scales'), 'scales.saved_unapproved');
    }

    public function saveBands(): never
    {
        $errors = [];
        $bands = [];
        $post = $_POST['band'] ?? [];
        if (!is_array($post)) {
            $post = [];
        }
        foreach ($post as $i => $b) {
            if (!is_array($b)) {
                continue;
            }
            $min = trim((string)($b['min'] ?? ''));
            $max = trim((string)($b['max'] ?? ''));
            $en = trim((string)($b['label_en'] ?? ''));
            $ar = trim((string)($b['label_ar'] ?? ''));
            if ($min === '' && $max === '' && $en === '' && $ar === '') {
                continue; // blank spare row
            }
            $row = ['id' => (int)($b['id'] ?? 0), 'min' => (int)$min, 'max' => (int)$max, 'en' => $en, 'ar' => $ar, 'color' => (string)($b['color'] ?? ''),
                'de' => trim((string)($b['desc_en'] ?? '')), 'da' => trim((string)($b['desc_ar'] ?? '')), 'high' => !empty($b['is_high']) ? 1 : 0];
            if (!ctype_digit($min) || !ctype_digit($max) || (int)$min < 1 || (int)$max > 25) {
                $errors["band_$i"] = t('scales.err_band_range');
            }
            if ($en === '' || $ar === '' || mb_strlen($en) > 60 || mb_strlen($ar) > 60) {
                $errors["band_$i"] = t('scales.err_labels');
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $row['color'])) {
                $errors["band_$i"] = t('scales.err_color');
            }
            $bands[] = $row;
        }
        if (!$bands) {
            $errors['bands'] = t('scales.err_band_coverage');
        } elseif (!$errors) {
            foreach (Scoring::validateBands($bands) as $k) {
                $errors['bands'] = t($k);
            }
        }
        if ($errors) {
            $this->invalid($errors, url('admin/scales'));
        }
        $now = date('Y-m-d H:i:s');
        $keep = [];
        Db::tx(function () use ($bands, $now, &$keep) {
            foreach ($bands as $b) {
                $data = ['min_score' => $b['min'], 'max_score' => $b['max'], 'label_en' => $b['en'], 'label_ar' => $b['ar'], 'color' => strtolower($b['color']),
                    'description_en' => $b['de'] ?: null, 'description_ar' => $b['da'] ?: null, 'is_high' => $b['high'], 'updated_at' => $now];
                if ($b['id'] && Db::val('SELECT 1 FROM score_bands WHERE id = ?', [$b['id']])) {
                    Db::update('score_bands', $data, 'id = ?', [$b['id']]);
                    $keep[] = $b['id'];
                } else {
                    $keep[] = Db::insert('score_bands', $data);
                }
            }
            $in = implode(',', array_fill(0, count($keep), '?'));
            Db::exec("DELETE FROM score_bands WHERE id NOT IN ($in)", $keep);
        });
        $this->unapprove();
        $this->audit('scales.bands_updated', 'scales', null, ['bands' => count($bands)]);
        $this->redirect(url('admin/scales'), 'scales.saved_unapproved');
    }

    public function approve(): never
    {
        $u = $this->user();
        Settings::set('thresholds_approved', '1');
        Settings::set('thresholds_approved_by', $u['full_name']);
        Settings::set('thresholds_approved_at', date('Y-m-d H:i:s'));
        $this->audit('scales.approved', 'scales');
        $this->redirect(url('admin/scales'), 'scales.approved_done');
    }
}

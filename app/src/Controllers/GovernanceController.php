<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;

/** Board and committee governance: bodies, members, meetings, attendance, minutes and compliance with the meeting rules. */
final class GovernanceController extends Controller
{
    public const TYPES = ['board_risk', 'board_audit', 'executive_risk', 'sharia_board', 'other'];
    public const ROLES = ['chair', 'member', 'secretary'];

    private function body(int $id): array
    {
        $b = Db::one('SELECT * FROM governance_bodies WHERE id = ?', [$id]);
        $b || $this->notFound();
        return $b;
    }

    private function meeting(int $id): array
    {
        $m = Db::one('SELECT m.*, b.name AS body_name, b.body_type, b.quorum_percent FROM governance_meetings m JOIN governance_bodies b ON b.id = m.body_id WHERE m.id = ?', [$id]);
        $m || $this->notFound();
        return $m;
    }

    public function index(): string
    {
        $rows = Db::all("SELECT b.*, (SELECT COUNT(*) FROM governance_members m WHERE m.body_id = b.id) AS members_n, (SELECT COUNT(*) FROM governance_members m WHERE m.body_id = b.id AND m.member_role = 'chair') AS chairs_n,
            (SELECT COUNT(*) FROM governance_meetings g WHERE g.body_id = b.id AND g.status = 'held' AND YEAR(g.meeting_date) = YEAR(CURDATE())) AS held_n,
            (SELECT MIN(g.meeting_date) FROM governance_meetings g WHERE g.body_id = b.id AND g.status = 'planned' AND g.meeting_date >= CURDATE()) AS next_date FROM governance_bodies b ORDER BY b.is_active DESC, b.name");
        return $this->view('governance/index', ['title' => t('gov.title'), 'rows' => $rows]);
    }

    public function bodyNew(): string
    {
        return $this->view('governance/body_form', ['title' => t('gov.body_new'), 'b' => ['body_type' => 'other', 'min_meetings_per_year' => 4, 'quorum_percent' => 50, 'formed_on' => date('Y-m-d'), 'is_active' => 1], 'existing' => null]);
    }

    public function bodyEdit(int $id): string
    {
        $b = $this->body($id);
        return $this->view('governance/body_form', ['title' => t('gov.body_edit'), 'b' => $b, 'existing' => $b]);
    }

    public function bodyStore(): never
    {
        $this->bodySave(null);
    }

    public function bodyUpdate(int $id): never
    {
        $this->bodySave($this->body($id));
    }

    private function bodySave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('name', 'gov.b_name')->max('name', 'gov.b_name', 250)->in('body_type', 'gov.b_type', self::TYPES)->max('purpose', 'gov.b_purpose', 3000)->date('formed_on', 'gov.b_formed')
            ->intRange('min_meetings_per_year', 'gov.b_min', 0, 60)->intRange('quorum_percent', 'gov.b_quorum', 1, 100);
        $back = $ex ? url('governance/' . $ex['id'] . '/edit') : url('governance/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['name' => $v->str('name'), 'body_type' => $v->str('body_type'), 'purpose' => $v->str('purpose') ?: null, 'formed_on' => $v->str('formed_on'), 'min_meetings_per_year' => (int)$v->str('min_meetings_per_year'), 'quorum_percent' => (int)$v->str('quorum_percent'),
            'is_active' => $ex ? (isset($_POST['is_active']) ? 1 : 0) : 1, 'updated_at' => $now];
        if ($ex) {
            Db::update('governance_bodies', $d, 'id = ?', [$ex['id']]);
            $this->audit('governance.body_updated', 'governance_body', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['name', 'body_type', 'min_meetings_per_year', 'quorum_percent', 'is_active'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('governance_bodies', $d + ['created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('governance_bodies', ['code' => sprintf('GOV-%04d', $id)], 'id = ?', [$id]);
            $this->audit('governance.body_created', 'governance_body', $id, ['name' => $d['name']]);
        }
        $this->redirect(url('governance/' . $id), 'admin.saved');
    }

    public function bodyShow(int $id): string
    {
        $b = $this->body($id);
        $members = Db::all('SELECT m.*, u.full_name, u.is_active FROM governance_members m JOIN users u ON u.id = m.user_id WHERE m.body_id = ? ORDER BY FIELD(m.member_role, \'chair\', \'secretary\', \'member\'), u.full_name', [$id]);
        $meetings = Db::all('SELECT * FROM governance_meetings WHERE body_id = ? ORDER BY meeting_date DESC, id DESC LIMIT 50', [$id]);
        $have = array_column($members, 'user_id');
        $candidates = array_filter(Ref::users(true), fn($u) => !in_array($u['id'], $have, false));
        return $this->view('governance/body_show', ['title' => $b['code'] . ' — ' . $b['name'], 'b' => $b, 'members' => $members, 'meetings' => $meetings, 'candidates' => $candidates]);
    }

    public function addMember(int $id): never
    {
        $this->body($id);
        $uid = Request::int('user_id');
        $role = Request::str('member_role');
        if ($uid === null || !isset(Ref::users(true)[$uid]) || !in_array($role, self::ROLES, true)) {
            $this->redirect(url('governance/' . $id), 'validation.invalid_choice', 'error', ['field' => t('gov.member')]);
        }
        if ($role === 'chair' && Db::val("SELECT 1 FROM governance_members WHERE body_id = ? AND member_role = 'chair'", [$id])) {
            $this->redirect(url('governance/' . $id), 'gov.err_one_chair', 'error');
        }
        Db::exec('INSERT IGNORE INTO governance_members (body_id, user_id, member_role, joined_on) VALUES (?, ?, ?, ?)', [$id, $uid, $role, date('Y-m-d')]);
        $this->audit('governance.member_added', 'governance_body', $id, ['user_id' => $uid, 'role' => $role]);
        $this->redirect(url('governance/' . $id), 'gov.member_added');
    }

    public function removeMember(int $id, int $uid): never
    {
        $this->body($id);
        Db::exec('DELETE FROM governance_members WHERE body_id = ? AND user_id = ?', [$id, $uid]);
        $this->audit('governance.member_removed', 'governance_body', $id, ['user_id' => $uid]);
        $this->redirect(url('governance/' . $id), 'gov.member_removed');
    }

    public function meetingStore(int $id): never
    {
        $b = $this->body($id);
        $v = new Validator($_POST);
        $v->date('meeting_date', 'gov.m_date')->max('agenda', 'gov.m_agenda', 4000);
        if (!(int)Db::val("SELECT COUNT(*) FROM governance_members WHERE body_id = ? AND member_role = 'chair'", [$id])) {
            $v->fail('meeting_date', 'gov.err_no_chair');
        }
        if (!$b['is_active']) {
            $v->fail('meeting_date', 'gov.err_inactive');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('governance/' . $id));
        }
        $now = date('Y-m-d H:i:s');
        $mid = Db::insert('governance_meetings', ['body_id' => $id, 'meeting_date' => $v->str('meeting_date'), 'agenda' => $v->str('agenda') ?: null, 'status' => 'planned', 'created_by' => $this->user()['id'], 'created_at' => $now, 'updated_at' => $now]);
        $this->audit('governance.meeting_scheduled', 'governance_body', $id, ['meeting_id' => $mid]);
        $this->redirect(url('governance/meetings/' . $mid), 'gov.meeting_scheduled');
    }

    public function meetingShow(int $id): string
    {
        $m = $this->meeting($id);
        $members = Db::all('SELECT mb.user_id, mb.member_role, u.full_name, COALESCE(a.present, 0) AS present FROM governance_members mb JOIN users u ON u.id = mb.user_id LEFT JOIN governance_attendance a ON a.meeting_id = ? AND a.user_id = mb.user_id WHERE mb.body_id = ? ORDER BY FIELD(mb.member_role, \'chair\', \'secretary\', \'member\'), u.full_name', [$id, $m['body_id']]);
        if ($m['status'] === 'held') {
            $members = Db::all('SELECT a.user_id, a.present, u.full_name, COALESCE(mb.member_role, \'member\') AS member_role FROM governance_attendance a JOIN users u ON u.id = a.user_id LEFT JOIN governance_members mb ON mb.body_id = ? AND mb.user_id = a.user_id WHERE a.meeting_id = ? ORDER BY u.full_name', [$m['body_id'], $id]);
        }
        $decisions = Db::all('SELECT id, code, title FROM committee_decisions WHERE committee = ? AND meeting_date = ? ORDER BY id', [$m['body_type'], $m['meeting_date']]);
        return $this->view('governance/meeting_show', ['title' => $m['body_name'] . ' — ' . $m['meeting_date'], 'm' => $m, 'members' => $members, 'decisions' => $decisions]);
    }

    /** Record that the meeting took place: attendance and minutes are mandatory, quorum is computed. */
    public function meetingHold(int $id): never
    {
        $m = $this->meeting($id);
        $back = url('governance/meetings/' . $id);
        if ($m['status'] !== 'planned') {
            $this->redirect($back, 'gov.err_state', 'error');
        }
        $v = new Validator($_POST);
        $v->required('minutes', 'gov.m_minutes')->max('minutes', 'gov.m_minutes', 20000);
        if ($m['meeting_date'] > date('Y-m-d')) {
            $v->fail('minutes', 'gov.err_future');
        }
        $members = array_column(Db::all('SELECT user_id FROM governance_members WHERE body_id = ?', [$m['body_id']]), 'user_id');
        if (!$members) {
            $v->fail('minutes', 'gov.err_no_members');
        }
        $present = array_values(array_intersect(array_map('intval', array_keys((array)($_POST['present'] ?? []))), array_map('intval', $members)));
        if ($members && !$present) {
            $v->fail('minutes', 'gov.err_nobody_present');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $quorum = count($members) > 0 && (count($present) * 100 >= count($members) * (int)$m['quorum_percent']);
        Db::tx(function () use ($id, $members, $present, $v, $quorum) {
            foreach ($members as $uid) {
                Db::exec('INSERT INTO governance_attendance (meeting_id, user_id, present) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE present = VALUES(present)', [$id, $uid, in_array((int)$uid, $present, true) ? 1 : 0]);
            }
            Db::update('governance_meetings', ['status' => 'held', 'minutes' => $v->str('minutes'), 'members_count' => count($members), 'present_count' => count($present), 'quorum_met' => $quorum ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
        $this->audit('governance.meeting_held', 'governance_meeting', $id, ['present' => count($present), 'members' => count($members), 'quorum' => $quorum]);
        $this->redirect($back, $quorum ? 'gov.held_ok' : 'gov.held_no_quorum', $quorum ? 'success' : 'error');
    }

    public function meetingCancel(int $id): never
    {
        $m = $this->meeting($id);
        if ($m['status'] !== 'planned') {
            $this->redirect(url('governance/meetings/' . $id), 'gov.err_state', 'error');
        }
        Db::update('governance_meetings', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('governance.meeting_cancelled', 'governance_meeting', $id);
        $this->redirect(url('governance/' . $m['body_id']), 'gov.cancelled');
    }

    /** Compliance with the meeting rules for a calendar year. */
    public function report(): string
    {
        $year = max(2000, min(2100, Request::int('year', true) ?: (int)date('Y')));
        $rows = Db::all("SELECT b.id, b.code, b.name, b.min_meetings_per_year, b.quorum_percent, b.is_active,
            (SELECT COUNT(*) FROM governance_meetings g WHERE g.body_id = b.id AND g.status = 'held' AND YEAR(g.meeting_date) = ?) AS held_n,
            (SELECT COUNT(*) FROM governance_meetings g WHERE g.body_id = b.id AND g.status = 'held' AND g.quorum_met = 0 AND YEAR(g.meeting_date) = ?) AS no_quorum_n,
            (SELECT COALESCE(ROUND(AVG(g.present_count * 100 / g.members_count)), 0) FROM governance_meetings g WHERE g.body_id = b.id AND g.status = 'held' AND g.members_count > 0 AND YEAR(g.meeting_date) = ?) AS avg_att,
            (SELECT COUNT(*) FROM governance_members m WHERE m.body_id = b.id AND m.member_role = 'chair') AS chairs_n
            FROM governance_bodies b WHERE b.is_active = 1 AND b.formed_on <= ? ORDER BY b.name", [$year, $year, $year, "$year-12-31"]);
        return $this->view('governance/report', ['title' => t('gov.report'), 'rows' => $rows, 'year' => $year]);
    }
}

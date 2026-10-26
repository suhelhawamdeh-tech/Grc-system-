<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;

final class UserController extends Controller
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $q = Request::str('q', true);
        $role = Request::str('role', true);
        $where = ['1=1'];
        $p = [];
        if ($q !== '') {
            $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            $like = Db::like($q);
            array_push($p, $like, $like, $like);
        }
        if (in_array($role, Perm::ROLES, true)) {
            $where[] = 'u.role = ?';
            $p[] = $role;
        }
        $total = (int)Db::val('SELECT COUNT(*) FROM users u WHERE ' . implode(' AND ', $where), $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT u.*, (SELECT COUNT(*) FROM risks r WHERE r.owner_user_id = u.id AND r.archived_at IS NULL) AS risk_count FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.full_name LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('admin/users_index', ['title' => t('admin.users'), 'rows' => $rows, 'q' => $q, 'role' => $role, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function create(): string
    {
        return $this->view('admin/user_form', ['title' => t('admin.user_new'), 'u' => ['role' => 'dept_manager', 'is_active' => 1], 'existing' => null, 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $u = $this->find($id);
        return $this->view('admin/user_form', ['title' => t('admin.user_edit'), 'u' => $u, 'existing' => $u, 'lists' => $this->lists(), 'owned' => (int)Db::val('SELECT COUNT(*) FROM risks WHERE owner_user_id = ? AND archived_at IS NULL', [$id])]);
    }

    private function find(int $id): array
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) {
            $this->notFound();
        }
        return $u;
    }

    private function lists(): array
    {
        return ['departments' => Ref::departments(true), 'branches' => Ref::branches(true), 'roles' => Perm::ROLES];
    }

    public function store(): never
    {
        [$data, $errors] = $this->collect(null);
        $pw = (string)($_POST['password'] ?? '');
        if ($e = Auth::checkPasswordPolicy($pw, $data['username'])) {
            $errors['password'] = t($e);
        }
        if ($errors) {
            $this->invalid($errors, url('admin/users/new'));
        }
        $now = date('Y-m-d H:i:s');
        $id = Db::insert('users', $data + ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->audit('user.created', 'user', $id, ['username' => $data['username'], 'role' => $data['role']]);
        $this->redirect(url('admin/users'), 'admin.user_created');
    }

    public function update(int $id): never
    {
        $cur = $this->find($id);
        [$data, $errors] = $this->collect($cur);
        $me = $this->user();
        if ((int)$me['id'] === $id && (int)$data['is_active'] === 0) {
            $errors['is_active'] = t('admin.err_self_deactivate');
        }
        if ($cur['role'] === 'admin' && ($data['role'] !== 'admin' || (int)$data['is_active'] === 0)) {
            $others = (int)Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$id]);
            if ($others === 0) {
                $errors['role'] = t('admin.err_last_admin');
            }
        }
        if ($errors) {
            $this->invalid($errors, url('admin/users/' . $id . '/edit'));
        }
        $ch = $this->diff($cur, $data, ['email', 'full_name', 'role', 'department_id', 'branch_id', 'is_active', 'lang']);
        Db::update('users', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('user.updated', 'user', $id, ['changes' => $ch]);
        $this->redirect(url('admin/users'), 'admin.user_updated');
    }

    public function resetPassword(int $id): never
    {
        $cur = $this->find($id);
        $pw = (string)($_POST['password'] ?? '');
        if ($e = Auth::checkPasswordPolicy($pw, $cur['username'])) {
            $this->invalid(['reset_password' => t($e)], url('admin/users/' . $id . '/edit'));
        }
        $now = date('Y-m-d H:i:s');
        Db::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 1, 'password_changed_at' => $now, 'updated_at' => $now], 'id = ?', [$id]);
        Db::exec('DELETE FROM login_attempts WHERE username = ?', [$cur['username']]);
        $this->audit('user.password_reset', 'user', $id);
        $this->redirect(url('admin/users/' . $id . '/edit'), 'admin.password_reset_done');
    }

    /** Move all non-archived risks owned by one user to another (ownership hand-over). */
    public function transfer(int $id): never
    {
        $this->find($id);
        $to = Request::int('to_user');
        if ($to === null || $to === $id || !isset(Ref::users(true)[$to])) {
            $this->invalid(['to_user' => t('validation.invalid_choice', ['field' => t('admin.transfer_to')])], url('admin/users/' . $id . '/edit'));
        }
        $now = date('Y-m-d H:i:s');
        $me = $this->user();
        $ids = array_column(Db::all('SELECT id FROM risks WHERE owner_user_id = ? AND archived_at IS NULL', [$id]), 'id');
        Db::tx(function () use ($ids, $id, $to, $now, $me) {
            foreach ($ids as $rid) {
                Db::update('risks', ['owner_user_id' => $to, 'updated_at' => $now, 'updated_by' => $me['id']], 'id = ?', [$rid]);
                Db::insert('risk_history', ['risk_id' => $rid, 'user_id' => $me['id'], 'event' => 'updated', 'changes' => json_encode(['owner_user_id' => [$id, $to]]), 'created_at' => $now]);
            }
        });
        $this->audit('user.ownership_transferred', 'user', $id, ['to' => $to, 'risks' => count($ids)]);
        $this->redirect(url('admin/users/' . $id . '/edit'), 'admin.transfer_done', 'success', ['n' => count($ids)]);
    }

    /** @return array{0:array,1:array} */
    private function collect(?array $existing): array
    {
        $v = new Validator($_POST);
        $v->required('full_name', 'admin.full_name')->max('full_name', 'admin.full_name', 150)->email('email', 'admin.email')->max('email', 'admin.email', 190)
            ->in('role', 'admin.role', Perm::ROLES)->in('lang', 'admin.language', ['ar', 'en'], false);
        $username = mb_strtolower($v->str('username'));
        if (!$existing) {
            if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
                $v->fail('username', 'admin.err_username');
            } elseif (Db::val('SELECT 1 FROM users WHERE username = ?', [$username])) {
                $v->fail('username', 'admin.err_username_taken');
            }
        } else {
            $username = $existing['username'];
        }
        $email = $v->str('email');
        if ($email !== '' && Db::val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $existing['id'] ?? 0])) {
            $v->fail('email', 'admin.err_email_taken');
        }
        $dept = Request::int('department_id');
        $branch = Request::int('branch_id');
        $role = $v->str('role');
        $st = Perm::scopeType($role);
        if ($st === 'department' && ($dept === null || !isset(Ref::departments()[$dept]))) {
            $v->fail('department_id', 'admin.err_dept_required');
        }
        if ($st === 'branch' && ($branch === null || !isset(Ref::branches()[$branch]))) {
            $v->fail('branch_id', 'admin.err_branch_required');
        }
        if ($dept !== null && !isset(Ref::departments()[$dept])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('admin.department')]);
        }
        if ($branch !== null && !isset(Ref::branches()[$branch])) {
            $v->fail('branch_id', 'validation.invalid_choice', ['field' => t('admin.branch')]);
        }
        $data = [
            'username' => $username, 'email' => $email !== '' ? $email : null, 'full_name' => $v->str('full_name'), 'role' => $role,
            'department_id' => $dept, 'branch_id' => $branch, 'lang' => $v->str('lang') ?: null, 'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];
        if (!$existing) {
            $data['is_active'] = 1;
        }
        return [$data, $v->errors()];
    }
}

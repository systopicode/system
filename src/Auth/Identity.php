<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Scope;
use Systopic\System\Queries\UserRights\UserRights;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

/**
 * Who the request belongs to: the logged-in user with roles and group rights,
 * nobody (anonymous), or the system recovery user (no database).
 *
 * Typed for new code (`->user`, `->roleNames`, `->readGroupIds` …). For the
 * legacy surface — `user::getActive()->is_superuser`, `->role_names`,
 * `->state->fakeRoles`, `->logout()` — it answers the old field names too
 * (`__get`, snake_case of any user column) and hands method calls on to the
 * `user` shell. Roles and rights are read once, on first use.
 */
final class Identity
{
    /** @var list<Role>|null */
    private ?array $roleRows = null;

    /** @var array{read: list<int>, write: list<int>}|null */
    private ?array $rights = null;

    /** @var list<int>|null */
    private ?array $rightsRoleIds = null;

    /**
     * @param list<string> $recoveryRoles the roles of the recovery user
     */
    public function __construct(
        private readonly Scope $scope,
        public readonly ?User $user,
        public readonly bool $recovery = false,
        private readonly array $recoveryRoles = [],
    ) {}

    public static function anonymous(Scope $scope): self
    {
        return new self($scope, null);
    }

    /** @param list<string> $roles */
    public static function recovery(Scope $scope, array $roles): self
    {
        return new self($scope, null, true, $roles);
    }

    // ------------------------------------------------------------- who

    /** The user id; 1 for the recovery user, as before; null for nobody. */
    public ?int $id {
        get => $this->recovery ? 1 : ($this->user?->id === null ? null : (int) $this->user->id);
    }

    public bool $known { get => $this->user !== null || $this->recovery; }

    public bool $isActive { get => $this->recovery || (bool) $this->user?->isActive; }

    public bool $isSuperuser { get => (bool) $this->user?->isSuperuser; }

    public string $name { get => $this->recovery ? 'systemRecovery' : (string) $this->user?->name; }

    public string $fullname { get => $this->recovery ? 'System Recovery' : (string) $this->user?->fullname; }

    public string $initials { get => $this->user === null ? '' : UserOperator::initials($this->user); }

    // ----------------------------------------------------------- roles

    /** @return list<Role> the user's own roles (assigned, not faked) */
    public array $roles {
        get {
            if ($this->roleRows === null) {
                $this->roleRows = $this->user?->id === null ? [] : UserRights::roles($this->scope, (int) $this->user->id);
            }
            return $this->roleRows;
        }
    }

    /** @return list<string> */
    public array $roleNames {
        get => $this->recovery ? $this->recoveryRoles : array_map(static fn(Role $r) => (string) $r->name, $this->roles);
    }

    /** @return list<int> */
    public array $roleIds {
        get => array_map(static fn(Role $r) => (int) $r->id, $this->roles);
    }

    /** @return list<string> the roles a superuser may take on — every role but the built-in ones, held anyway */
    public array $allRoleNames {
        get => array_values(array_map(static fn(Role $r) => (string) $r->name,
            array_filter(UserRights::roles($this->scope, null, true), static fn(Role $r) => !$r->isBuiltin)));
    }

    // ------------------------------------------------------- rights

    /**
     * The groups the user may read / write. A superuser holds every role —
     * or, with fake roles, only those; anybody else the assigned ones. On
     * top, for everybody: the built-in role `everyone`, and `authenticated`
     * for a signed-in user.
     */
    public array $readGroupIds { get => $this->rights()['read']; }

    public array $writeGroupIds { get => $this->rights()['write']; }

    /**
     * The ACLs the user may read / write: those with a group of the above.
     * Not kept here but asked each time — the scope caches the query and
     * throws it away when a new ACL is written in this request.
     *
     * @var list<int>
     */
    public array $readAclIds { get => UserRights::acls($this->scope, $this->rightsRoleIds())['read']; }

    /** @var list<int> */
    public array $writeAclIds { get => UserRights::acls($this->scope, $this->rightsRoleIds())['write']; }

    /** @return array{read: list<int>, write: list<int>} */
    private function rights(): array
    {
        return $this->rights ??= UserRights::groups($this->scope, $this->rightsRoleIds());
    }

    /** @return list<int> the roles the rights come from — a superuser's all, or the faked ones */
    private function rightsRoleIds(): array
    {
        if ($this->rightsRoleIds !== null) {
            return $this->rightsRoleIds;
        }
        $roleIds = $this->roleIds;
        if ($this->isSuperuser) {
            $all = UserRights::roles($this->scope, null, true);
            $fake = Session::fakeRoles();
            if ($fake !== []) {
                $all = array_values(array_filter($all, static fn(Role $r) => in_array((string) $r->name, $fake, true)));
            }
            $roleIds = array_map(static fn(Role $r) => (int) $r->id, $all);
        }
        // the built-in roles nobody assigns: everyone, and every signed-in user
        // (not for the recovery user, who may have no database)
        $builtin = $this->recovery ? [] : UserRights::builtinRoles($this->scope);
        foreach (['everyone' => true, 'authenticated' => $this->user !== null] as $key => $holds) {
            if ($holds && isset($builtin[$key])) {
                $roleIds[] = (int) $builtin[$key]->id;
            }
        }
        return $this->rightsRoleIds = array_values(array_unique($roleIds));
    }

    // --------------------------------------------------- legacy surface

    /** The session state (panel states, fake roles) — `->state` of before. */
    public object $state { get => Session::state(); }

    public function getState(): object
    {
        return Session::state();
    }

    public function storeState(): void
    {
        Session::storeState();
    }

    /**
     * The old field names: `user_id`, `is_superuser`, `role_names` …, and
     * any user column in snake_case (`language_iso` → `languageIso`).
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'user_id' => $this->id,
            'is_superuser' => $this->isSuperuser,
            'is_active' => $this->isActive,
            'role_names' => $this->roleNames,
            'role_ids' => $this->roleIds,
            'role_colors' => array_map(static fn(Role $r) => (string) $r->color, $this->roles),
            'read_group_ids' => $this->readGroupIds,
            'write_group_ids' => $this->writeGroupIds,
            'read_acl_ids' => $this->readAclIds,
            'write_acl_ids' => $this->writeAclIds,
            'roles' => $this->roles,
            default => $this->column($name),
        };
    }

    public function __isset(string $name): bool
    {
        if ($name === 'systemRecovery') {
            return $this->recovery;
        }
        return $this->__get($name) !== null;
    }

    /** `user::getActive()->logout()` and the like: the session's static methods. */
    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists(Session::class, $name)) {
            return Session::$name(...$arguments);
        }
        throw new \BadMethodCallException("Identity has no method $name()");
    }

    private function column(string $name): mixed
    {
        if ($this->user === null) {
            return null;
        }
        $property = lcfirst(str_replace('_', '', ucwords($name, '_')));
        return property_exists($this->user, $property) ? $this->user->$property : null;
    }
}

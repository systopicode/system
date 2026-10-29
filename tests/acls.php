<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Auth\Identity;
use Systopic\System\Auth\Session;
use Systopic\System\Queries\UserRights\UserRights;
use Systopic\System\Tables\Acls\Model as Acl;
use Systopic\System\Tables\Acls\Operator as AclOperator;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\GroupsInAcls\Model as GroupInAcl;
use Systopic\System\Tables\Nodes\AclInheritance;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\RolesOnGroups\Model as RoleOnGroup;
use Systopic\System\Tables\UsersAtRoles\Model as UserAtRole;
use Systopic\System\Tables\Users\Model as User;

// Minimal web boundary, as in passkeys.php: no project bootstrap.
class http { public static function sessionStartOnce(): void {} }
$_SESSION = [];

$dsn = getenv('FLUXO_TEST_DSN') ?: throw new RuntimeException('Set FLUXO_TEST_DSN to an isolated MySQL database named fluxo_test_…');
$connection = Connection::create($dsn, getenv('FLUXO_TEST_USER') ?: 'root', getenv('FLUXO_TEST_PASSWORD') ?: '');
$database = (string) $connection->value('SELECT DATABASE()');
check('isolated test database', str_starts_with($database, 'fluxo_test_'));
$connection->exec("DROP DATABASE `$database`");
$connection->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$connection->exec("USE `$database`");
$connection->database = $database;
Connection::setDefault($connection);
Schema::setDefaultTablePrefix('cms');
$scope = new Scope($connection);
Scope::setDefault($scope);
Scope::addObserver(new AclInheritance());

$audit = new Audit($connection);
$audit->apply($audit->run([User::class, Group::class, Role::class, RoleOnGroup::class, UserAtRole::class, Acl::class, GroupInAcl::class, Node::class]));
check('tables created', $audit->failures() === []);
// Row 1 of the usm tables is the empty default record in every installation.
$scope->save(new Group(), new Role(), new User());

$stored = static fn(Node $n): ?int => ($v = $connection->value('SELECT node_acl_id FROM cms_nodes WHERE node_id = ?', [$n->id])) === null ? null : (int) $v;
$acl = static function (array $groups) use ($scope): ?Acl {
    [$acl, $save] = AclOperator::forGroups($scope, $groups);
    if ($save !== []) {
        $scope->save(...$save);
    }
    return $acl;
};

// ---- ACLs: one row per set ------------------------------------------------

$hr = new Group(); $hr->name = 'HRDocuments';
$board = new Group(); $board->name = 'BoardDocuments';
$scope->save($hr, $board);

$both = $acl([$board, $hr->id, $hr]);
check('a new set is saved with its groups', $both?->id !== null && $both->groupIds === "{$hr->id},{$board->id}"
    && (int) $connection->value('SELECT COUNT(*) FROM usm_groups_in_acls WHERE gia_acl_id = ?', [$both->id]) === 2);
[$again, $save] = AclOperator::forGroups($scope, [$hr->id, $board->id]);
check('the same set in another order is the same ACL', $again === $both && $save === []);
check('no group is no ACL', AclOperator::forGroups($scope, [0, null])[0] === null);
$onlyHr = $acl([$hr]);
$onlyBoard = $acl([$board]);
check('group ids read off the key', AclOperator::groupIds($both) === [(int) $hr->id, (int) $board->id]);

// ---- inheritance ------------------------------------------------------------

$node = static function (?Node $parent, string $type = 'page') use ($scope): Node {
    $n = new Node(); $n->type = $type; $n->parentNode = $parent;
    $scope->save($n);
    return $n;
};
$root = new Node(); $root->type = 'page';
$scope->save(...NodeOperator::withAcl($root, $onlyHr));
$c = $node($root);
$g = $node($c);
$d = new Node(); $d->type = 'page'; $d->parentNode = $root;
$scope->save(...NodeOperator::withAcl($d, $onlyBoard));
$e = $node($d);
check('a new child takes the parent\'s ACL', $stored($c) === $onlyHr->id && $stored($g) === $onlyHr->id);
check('… in memory as well, without a change left on it', NodeOperator::aclIdOf($g) === $onlyHr->id && $g->changes() === []);
check('a child with an ACL of its own keeps it', $stored($d) === $onlyBoard->id && $stored($e) === $onlyBoard->id);

$scope->save(...NodeOperator::withAcl($root, $both));
check('the root\'s new ACL goes down to every heir', $stored($c) === $both->id && $stored($g) === $both->id);
check('… and stops at a node with its own', $stored($d) === $onlyBoard->id && $stored($e) === $onlyBoard->id);

$e->parentNode = $c;
$scope->save($e);
check('a moved node that inherits takes its new place\'s ACL', $stored($e) === $both->id);

$scope->save(...NodeOperator::withAcl($c, $onlyBoard));
check('an own ACL in the middle goes down from there', $stored($c) === $onlyBoard->id && $stored($g) === $onlyBoard->id && $stored($e) === $onlyBoard->id);
check('… and not up', $stored($root) === $both->id);

$scope->save(...NodeOperator::inheritingAcl($c));
check('inheriting again takes the parent\'s and passes it down', $stored($c) === $both->id && $stored($g) === $both->id && $c->aclInherit);

$lone = new Node(); $lone->type = 'page';
$scope->save(...NodeOperator::inheritingAcl($lone));
check('a root that inherits has no group', $stored($lone) === null);

// ---- repair -------------------------------------------------------------------

$connection->run('UPDATE cms_nodes SET node_acl_id = ? WHERE node_id IN (?, ?)', [$onlyHr->id, $g->id, $d->id]);
$scope->results->invalidate(['cms_nodes']);
$fixed = AclInheritance::repair($scope);
check('repair sets the inherited copies right and leaves own ACLs alone', $fixed === 1 && $stored($g) === $both->id && $stored($d) === $onlyHr->id);
$connection->run('UPDATE cms_nodes SET node_acl_id = ? WHERE node_id = ?', [$onlyBoard->id, $d->id]);
$scope->results->invalidate(['cms_nodes']);

// ---- rights -------------------------------------------------------------------

$hrEditor = new Role(); $hrEditor->name = 'HREditor';
$boardReader = new Role(); $boardReader->name = 'BoardReader';
$scope->save($hrEditor, $boardReader);
$grant = static function (Role $role, Group $group, bool $read, bool $write) use ($scope): void {
    $link = new RoleOnGroup(); $link->role = $role; $link->group = $group; $link->read = $read; $link->write = $write;
    $scope->save($link);
};
$grant($hrEditor, $hr, true, true);
$grant($boardReader, $board, true, false);
$user = static function (string $name, Role ...$roles) use ($scope): User {
    $u = new User(); $u->name = $name; $u->isActive = true;
    $scope->save($u);
    foreach ($roles as $role) {
        $link = new UserAtRole(); $link->user = $u; $link->role = $role;
        $scope->save($link);
    }
    return $u;
};
$anna = $user('anna', $hrEditor);
$ben = $user('ben', $boardReader);

$rights = new Identity($scope, $anna);
check('an ACL may be read and written through one of its groups',
    in_array($both->id, $rights->readAclIds, true) && in_array($both->id, $rights->writeAclIds, true)
    && in_array($onlyHr->id, $rights->writeAclIds, true) && !in_array($onlyBoard->id, $rights->readAclIds, true));
$rights = new Identity($scope, $ben);
check('reading through one group grants no writing', in_array($both->id, $rights->readAclIds, true) && !in_array($both->id, $rights->writeAclIds, true));

$press = new Group(); $press->name = 'PressDocuments';
$scope->save($press);
$withAll = $acl([$hr, $board, $press]);   // a set created during the request
check('a new ACL is in the rights at once', in_array($withAll->id, $rights->readAclIds, true));

$as = static function (User $u): void { $_SESSION['systopic_user_id'] = (int) $u->id; Session::reset(); };
$as($anna);
check('session: HR may write the HR+Board node', Session::mayWrite($c));
check('session: HR may not read a Board node', !Session::mayRead($d));
$as($ben);
check('session: Board reads the HR+Board node, but does not write it', Session::mayRead($c) && !Session::mayWrite($c));
check('session: no ACL is superusers only', !Session::mayRead($lone));
$lone->user = $ben;
$scope->save($lone);
check('session: the owner always may', Session::mayWrite($lone));

// ---- built-in roles ---------------------------------------------------------

$builtin = UserRights::builtinRoles($scope);
check('the built-in roles are made at their first use', isset($builtin['everyone'], $builtin['authenticated']) && $builtin['everyone']->isBuiltin);
check('… once', count(UserRights::builtinRoles($scope)) === 2
    && (int) $connection->value('SELECT COUNT(*) FROM usm_roles WHERE role_builtin IS NOT NULL') === 2);
$public = new Group(); $public->name = 'public';
$scope->save($public);
$grant($builtin['everyone'], $public, true, false);
$grant($builtin['authenticated'], $press, true, false);
$publicAcl = $acl([$public]);
$_SESSION = []; Session::reset();
check('a visitor reads what everyone may read', in_array($publicAcl->id, Session::current()->readAclIds, true)
    && !in_array($both->id, Session::current()->readAclIds, true) && !in_array($withAll->id, Session::current()->readAclIds, true));
check('… and writes nothing', Session::current()->writeAclIds === []);
$as($ben);
check('a signed-in user reads what everyone and the signed-in may read, beside their own',
    in_array($publicAcl->id, Session::current()->readAclIds, true) && in_array($withAll->id, Session::current()->readAclIds, true)
    && in_array($both->id, Session::current()->readAclIds, true));

$boss = $user('boss');
$boss->isSuperuser = true;
$scope->save($boss);
$publicNode = new Node(); $publicNode->type = 'page';
$scope->save(...NodeOperator::withAcl($publicNode, $publicAcl));
$as($ben);
check('a group nobody may write shuts out the others', !Session::mayWrite($publicNode));
$as($boss);
check('… but not a superuser', Session::mayWrite($publicNode));

echo "all ACL checks passed\n";

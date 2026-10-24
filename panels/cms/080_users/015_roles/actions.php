<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Panel $this */

use Systopic\System\Tables\Roles\Operator as RoleOperator;

switch ($this->action) {
    case 'addRole':
        $this->scope->save($role = RoleOperator::create());
        $this->scope->save(RoleOperator::named($role));
        $this->updateView('main');
        break;
}

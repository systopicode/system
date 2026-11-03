<?php
require __DIR__ . '/bootstrap.php';
use Systopic\Db\{Connection,UnitOfWork};
use Systopic\System\Tables\Tags\Model;
$c = new Connection(new PDO('sqlite::memory:'));
(new ReflectionProperty($c,'platform'))->setValue($c,new Systopic\Db\Platform\MySql());
$c->exec('CREATE TABLE cms_tags (tag_id INTEGER PRIMARY KEY, tag_name TEXT)');
$c->run('INSERT INTO cms_tags VALUES(1,?)',['old']);
$row=Model::withId(1);$row->hydrate('name','old');$row->markLoaded();
(new UnitOfWork($c))->persist($row)->commit(function($conn)use($row){check('guard runs inside transaction',$conn->inTransaction());$row->name='new';});
check('guarded write persisted',$c->value('SELECT tag_name FROM cms_tags')==='new');
try{(new UnitOfWork($c))->persist($row)->commit(function(){throw new RuntimeException('conflict');});}catch(RuntimeException $e){}
check('rejected guard leaves stored value intact',$c->value('SELECT tag_name FROM cms_tags')==='new');
check('rejected guard rolls back transaction',!$c->inTransaction());

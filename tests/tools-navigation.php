<?php
require __DIR__.'/bootstrap.php';
class http {static string $root='/project/';}
class html { static function create($tag){return new NavListFixture();} }
class NavListFixture { private array $items=[];function append($item){$this->items[]=$item;}function __toString(){return '<ul>'.implode('', $this->items).'</ul>';} }
class NavItemFixture { private string $children=''; function __construct(private string $name, private string $href=""){} function append($item){$this->children.=(string)$item;} function __toString(){return '<li><a href="'.$this->href.'"><span>'.$this->name.'</span></a>'.$this->children.'</li>';} }
class ToolsNavFixture {
 public bool $inNavi=true;public ?self $firstChild=null;public ?self $next=null;public object $state;public string $label;
 function __construct(public string $name,public array $children=[],public bool $active=false){$this->state=(object)['pylonWidth'=>250];$this->label=ucfirst($name);$previous=null;foreach($children as $child){if($previous)$previous->next=$child;else $this->firstChild=$child;$previous=$child;}}
 function child($name){return $this->children[$name]??null;}
 function inPath(){return $this->active;}
 function getHref($root){return $root.'cms/tools/'.($this->name==='links'?'maintenance/links':$this->name).'/';}
 function getNavi(){throw new RuntimeException('Do not walk the active navigator from a presentation group.');}
 function eachChild($fn){throw new RuntimeException('Group traversal must not use the active navigator.');}
 function getNaviItem(){return new NavItemFixture($this->label, $this->getHref(http::$root));}
 function render(){ob_start();include dirname(__DIR__).'/panels/cms/090_tools/pylon.tpl.php';return ob_get_clean();}
}
$website=new ToolsNavFixture('website',['links'=>new ToolsNavFixture('links'),'misses'=>new ToolsNavFixture('misses'),'paths'=>new ToolsNavFixture('paths')],true);
$root=new ToolsNavFixture('tools',['website'=>$website,'database'=>new ToolsNavFixture('database'),'debug'=>new ToolsNavFixture('debug'),'extra'=>new ToolsNavFixture('extra')]);
$html=$root->render();
check('website checks grouped',str_contains($html,'>Website checks</span>')&&str_contains($html,'>Incoming · 404</span>')&&str_contains($html,'>URL paths</span>'));
check('canonical website route',str_contains($html,'href="/project/cms/tools/website/"'));
check('database and remote stay separate',str_contains($html,'>Database</span>')&&str_contains($html,'>Remote debugging</span>'));
check('uninstalled migration omitted',!str_contains($html,'>Migration</span>'));
check('additional package tools remain reachable',str_contains($html,'>Extra</span>'));
$system=new ToolsNavFixture('system',['assets'=>new ToolsNavFixture('assets'),'phpinfo'=>new ToolsNavFixture('phpinfo')],true);
$systemRoot=new ToolsNavFixture('tools',['system'=>$system,'database'=>new ToolsNavFixture('database')]);
$systemHtml=$systemRoot->render();
check('active group lists only its own children',substr_count($systemHtml,'>Assets</span>')===1 && substr_count($systemHtml,'>PHP information</span>')===1);
check('database not duplicated inside system',substr_count($systemHtml,'>Database</span>')===1);
echo "Tools navigation checks passed.\n";

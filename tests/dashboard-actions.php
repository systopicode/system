<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Dashboard\Layout;
class http {public static array $data=[];public static string $moved='';static function dataset($key,$default=null){return self::$data[$key]??$default;}static function dragdata($key,$default=null){return self::$moved;}}
$panel=new class {
 public string $action='insertWidgetAfter';public object $state;public array $layout=['order'=>['a','b','|','c','|','d'],'hidden'=>[],'layouts'=>[]];public int $updated=0;
 function dashboardLayout(){return $this->layout;}
 function dashboardWidgets(){return array_fill_keys(['a','b','c','d'],'widget');}
 function saveDashboardLayout($order,$hidden,$columns=3,$reordered=true){$this->layout=$reordered?Layout::save($this->layout,$order,$hidden,$columns):array_replace($this->layout,['hidden'=>$hidden]);}
 function updateView($view){$this->updated++;}
 function invoke(){include dirname(__DIR__).'/panels/admin/actions.php';}
};
$panel->state=(object)[];
http::$data=['columns'=>2,'order'=>json_encode(['a','c','|','b','d']),'widget'=>'d'];http::$moved='a';$panel->invoke();
check('real action stores a tablet move in the correct column',$panel->layout['layouts'][2]===['c','|','b','d','a']);
check('tablet action leaves old desktop ordering intact',$panel->layout['order']===['a','b','|','c','|','d']);
$panel->action='insertWidgetEnd';http::$moved='b';http::$data=['columns'=>1,'order'=>json_encode(['d','c','b','a']),'column'=>0];$panel->invoke();
check('phone end-drop is independent of tablet state',$panel->layout['layouts'][1]===['d','c','a','b']&&$panel->layout['layouts'][2]===['c','|','b','d','a']);
$before=$panel->layout['layouts'];$panel->action='hideWidget';http::$data=['widget'=>'b'];$panel->invoke();
check('hiding a widget preserves every view order',$panel->layout['layouts']===$before&&$panel->layout['hidden']===['b']);
$panel->action='showWidget';$panel->invoke();check('showing restores visibility without reordering',$panel->layout['hidden']===[]&&$panel->layout['layouts']===$before);
$panel->action='insertWidgetAfter';http::$moved='foreign';http::$data=['columns'=>2,'order'=>json_encode(['a','foreign','|','b']),'widget'=>'b'];$panel->invoke();
check('unknown dragged widgets cannot alter a layout',$panel->layout['layouts']===$before);

$panel->action="hideDroppedWidget";http::$moved="c";$panel->invoke();
check("drop-to-hide preserves view orders",$panel->layout["hidden"]===["c"]&&$panel->layout["layouts"]===$before);

$panel->action='insertWidgetAfter';http::$moved='c';http::$data=['columns'=>2,'order'=>json_encode(['a','c','|','b','d']),'widget'=>'d'];$panel->invoke();
check('dragging a hidden widget back restores it at the target',$panel->layout['hidden']===[]&&$panel->layout['layouts'][2]===['a','|','b','d','c']);

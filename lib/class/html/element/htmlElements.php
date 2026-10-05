<?php

#[AllowDynamicProperties]
class htmlElements { // nodelist & baseclass for node

    protected
        $parent,
        $children = [],
        $hidden = FALSE,
        $hideNext = FALSE,
        // selectables:
        $ids = [],
        $tags = [],
        $classnames = []
    ;
    public
        $rootSelectors = []

    ;

    static function create($selector = NULL) {
        return new static($selector);
    }

    function firstChild($childChildrenOrSelector) { // partly tested
        $before = reset($this->children) ?: NULL;
        return $this->append($childChildrenOrSelector, get_called_class(), $before);
    }

    function addChild($childChildrenOrSelector, $parentClass = NULL, $before = NULL) {
        return $this->append($childChildrenOrSelector, $parentClass, $before);
    }

    function addSibling($childChildrenOrSelector, $parentClass = NULL, $before = NULL) {
        return $this->close()->addChild($childChildrenOrSelector, $parentClass, $before);
    }

    function append($childChildrenOrSelector, $parentClass = NULL, $before = NULL) {
        if ($this->skipNext) {
            $this->skipNext = FALSE;
            return $this;
        }
        if (is_null($childChildrenOrSelector)) {
            return $this;
        }
        $class = $parentClass ?: get_called_class();
        if (is_string($childChildrenOrSelector)) { // selector
            $child = htmlElementSelector::getElement($childChildrenOrSelector, $class);
            if ($this->skipNext) {
                $child->hidden = TRUE;
                $this->skipNext = FALSE;
            }
            return $this->append($child, $class, $before);
        }
        if (is_object($childChildrenOrSelector)) { // child
            $child = $childChildrenOrSelector->root(); // walk up if structure
            $before ? array_unshift($this->children, $child) : array_push($this->children, $child);
            $child->parent = $this;
            // dev selectors
            $this->root()->rootSelectors = array_merge($this->root()->rootSelectors, $child->rootSelectors);
            $this->root()->addTags($child->getTags());
            $this->root()->addIds($child->getIds());
            if ($this->skipNext) {
                $childChildrenOrSelector->hidden = TRUE;
                $this->skipNext = FALSE;
            }
            return $childChildrenOrSelector;
        }
        if (is_array($childChildrenOrSelector)) { // children => 2do array => nodeList compatibility use instanceOf
            foreach ($childChildrenOrSelector AS $child) {
                $this->append($child, $class, $before);
            }
            return $this;
        }
        p("html::append() was passed something strange .. see trace below");
        p($this);
        d($childChildrenOrSelector);
    }

    function getPath() { // debug only
        if ($this->parent) {
            return $this->parent->getPath() . $this->tag;
        }
        return $this->tag;
    }

    function setParent($element) {
        $this->parent = $element;
    }

    // ************** ids ******************
    function addId($id, $element) {
        if (!$element instanceof htmlElements) {
            d("passed element of wrong type");
        }
        $this->ids[$id] = $element;
    }

    function addIds($ids) { // collect from child
        $this->ids = array_merge($this->ids, $ids);
    }

    function getIds() {
        return $this->ids;
    }

    // ************** tags ******************
    function addTag($tag, $element) {
        $this->tags[$tag] = $element;
    }

    function addTags($tags) { // collect from child
        $this->tags = array_merge($this->tags, $tags);
    }

    function getTags() { // collect from child
        return $this->tags;
    }

    // ************** classnames ******************
    function addClassname($classname, $element) {
        $this->classnames[$classname] = $element;
    }

    function addClassnames($classnames) {
        $this->classnames = array_merge($this->classnames, $classnames);
    }

    function getClassnames() { // collect from child
        return $this->classnames;
    }

    // ************** get elements by selector ******************	
    function get($selector) {
        $ids = $this->root()->getIds();
        $tags = $this->root()->getTags();
        $classnames = $this->root()->getClassnames();
        // $selector === 'content' ? p($ids) && p($tags) : NULL;
        return $ids[$selector] ?? $ids["#$selector"] ?? $tags[$selector] ?? $classnames[$selector] ?? (function ()use ($tags, $ids, $selector) {
                p("htmlElement->get($selector) ==> no result dumping ids, tags and \$this... ");
                p($ids, 1);
                p($tags, 1);
                d($this);
            })();
    }

    function find($selector) {
        if (!key_exists($selector, $this->root()->rootSelectors)) {
            p("htmlElement->find($selector) ==> no result dumping ...");
            p($this->root()->rootSelectors, 1);
            d($this);
        }
        return $this->root()->rootSelectors[$selector];
    }

    function each($iterable, $callback) {
        foreach ($iterable AS $item) {
            $callback($this, $item);
        }
        return $this;
    }

    function forin($iterable, $callback) {
        foreach ($iterable AS $key => $item) {
            $callback($this, $key);
        }
        return $this;
    }

    function foreach($iterable, $callback) {
        foreach ($iterable AS $key => $item) {
            $callback($this, $item, $key);
        }
        return $this;
    }

    function call($classOrCallable, $method = NULL, ...$args) {
        if ($this->skipNext) {
            $this->skipNext = FALSE;
            return $this;
        }
        if (is_callable($classOrCallable)) {
            $classOrCallable($this);
            return $this;
        }
        $class = $classOrCallable;
        array_unshift($args, $this);
        // the called function allwas gets the parent html element passed in the first parameter
        if (!method_exists($class, $method)) {
            $className = get_class($class);
            d("no method here: $className->$method");
        }
        call_user_func_array([$class, $method], $args);
        return $this;
    }

    function __invoke(...$args) {
        return $this->get(reset($args));
    }

    function __call($name, $args) {
        $arg = count($args) ? reset($args) : NULL;
        if ($this->skipNext) {
            $this->skipNext = FALSE;
            return $this;
        }
        if (in_array($name, htmlReference::$tags)) { // shorthand
            // $item->h1('Hallo') = $item->append('h1')->text('hallo');
            return $this->append(htmlElement::create()->tag($name)->text($args[0] ?? ''));
        }
        if (in_array($name, htmlReference::$selfclosingTags)) {
            if (htmlReference::parentshipValid($this->tag, $name)) {
                return $this->append(htmlElement::create()->tag($name)->text($args[0] ?? ''));
            }
        }
        // --- moved to default:
        // if (in_array($name, htmlReference::$attributes)) {
        //   $this->attributes[$name] = $arg;
        //    return $this;
        // }
        if (in_array($name, htmlReference::$emptyAttributes)) {
            if ($arg !== FALSE) { // condition
                $this->emptyAttributes[] = $name;
            }
            return $this;
        }
        // reset returns false on empty array
        switch ($name) {
            case 'tag':
                $this->tag = $arg;
                $this->addTag($arg, $this);
                return $this;
            case 'attr':
                return $this->setAttributes($args);
            case 'get':
            case 'click':
            case 'focus':
            case 'input':
            case 'change':
            case 'mousedown':
            case 'mouseover':
                $this->attributes["data-on_$name"] = $arg;
                return $this;
            case 'route':
                $this->attributes["data-href"] = $arg;
                $this->addClass('ajax');
                return $this;
            case 'if':
                if (count($args) === 2) {
                    if ($arg) {
                        $args[1]($this);
                    }
                } else {
                    $this->skipNext = !$arg;
                }
                return $this;
            case 'show':
                $this->hidden = FALSE;
                return $this;
            case 'hide':
                $this->hidden = TRUE;
                return $this;
            case 'id':
            case 'doc':
                if (is_null($arg)) {
                    return $this;
                }
                $name = "data-doc";
                $this->root()->addId($arg, $this);
                $this->attributes[$name] = $arg;
                return $this;
            case 'href':
                if (isset($args[1])) {
                    $this->attributes[$name] = "$arg?" . http_build_query($args[1]);
                } else {
                    $this->attributes[$name] = $arg;
                }
                return $this;
            case 'data':
                foreach ($arg as $attr => $value) {
                    // check val?
                    $this->attributes["data-$attr"] = $value;
                }
                return $this;
            case 'class':
                $this->setClass($args);
                return $this;
            case 'selected': // double meaning attr or selected child option
                if ($this->tag === 'select') {
                    $this->setChildSelected($arg);
                    return $this;
                }
                if ($arg) {
                    $this->emptyAttributes[] = $name;
                }
                return $this;
            case 'style':
                return $this->setStyle($args);
            case 'backgroundImage': // generalisieren
                $this->styles['background-image'] = "url(\"$arg\")";
                return $this;

            case 'text':
            case 'html':
                $this->children[] = (string) $arg;
                //$this->text = $arg;
                return $this;
            case 'after':
                return $this->parent->append($arg);
            // ************** NAVIGATION ************
            case 'parent':
            case 'close':
                return $this->parent;
            case 'closeAll':
            case 'root':
            case 'end':
                $root = $this;
                while ($root->parent) {
                    $root = $root->parent;
                }
                return $root;
            case 'parents':
                $parent = $this;
                while ($parent->parent) {
                    $parent = $parent->parent;
                    if ($parent->tag === $arg) {
                        return $parent;
                    }
                }
                p("parents($arg) - Selector not found.");
                return $this;
            // ************** DEPR ************
            case 'icon':
                return $this->i()->class("fa-light fa-$arg")->close();
            case 'row':
                return $this->append('tr');
            case 'cell':
                return $this->append('td');

            default :
                if (in_array($name, htmlReference::$attributes)) {
                    $this->attributes[$name] = $arg;
                    return $this;
                }
                if (isset($this->$name)) { // getter for protected values
                    p("htmlElement::returning:$name");
                    return $this->$name;
                } else {
                    d('func not found:' . $name);
                }
        }
    }

    function __get($name) {
        if (isset($this->$name)) {
            return $this->$name;
        }
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
    }

    function setChildSelected($arg) {
        foreach ($this->children as $child) {
            if ($arg == $child->attributes['value']) {
                $child->selected(TRUE);
            }
        }
    }

    function insertBefore($sibling) {
        p('parent is ' . $this->parent->tag . ' - function not finished --> need collection class');
        foreach ($this->parent->children as $key => $child) {
            
        }
    }

    function lastChild() {
        return end($this->children);
    }

    function __toString() {
        if ($this->parent) { // auto end() -- experimental
            return (string) $this->parent;
        }
        return $this->toHtml();
    }

    function toHtml($level = 0, $readable = TRUE) {
        $html = '';
        $in = $readable ? str_repeat("\t", $level) : '';
        foreach ($this->children as $child) {
            if (is_string($child)) {
                $html .= $in . $child;
            } else {
                $html .= $child->toHtml($level + 1, $readable);
            }
        }
        return $html;
    }

    function pp() { // pretty print
        return $this->toHtml(0, TRUE);
    }

}

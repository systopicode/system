<?php

#[AllowDynamicProperties]
class htmlElement extends htmlElements {

    public
        $attributes = [],
        $styles = [],
        $emptyAttributes = [],
        $tag = '',
        $text = ''

    ;

    static function create($selector = NULL) {
        if (is_string($selector)) {
            return htmlElementSelector::getElement($selector, get_called_class());
        }
        if (is_null($selector)) {
            return new static;
        }
    }

    function options($objectOrArray, $selected = '') {
        foreach ($objectOrArray as $key => $value) {
            if ($key == $selected) {
                $this->append("option[value=$key][title=$key][selected=selected]")->text($value)->selected;
            } else {
                $this->append("option[value=$key][title=$key]")->text($value);
            }
        }
        return $this;
    }

    function addClass($class) {
        $this->setClass([$class]);
    }

    function setAttributes($args) {
        if (!count($args)) {
            $this->attributes = [];
            return $this;
        }
        if (is_string($args[0])) {
            if (isset($args[1])) {
                $this->attributes = array_merge($this->attributes, [$args[0] => $args[1]]);
            } else { // NULL or not passed => remove style
                unset($this->attributes[$args[0]]);
            }
        } else {
            p($args[0]);
            $this->attributes = array_merge($this->attributes, $args[0]);
        }
        return $this;
    }

    function removeClass($class) {
        if (!is_array($this->attributes['class'] ?? FALSE)) {
            return;
        }
        $key = array_search($class, $this->attributes['class']);
        if ($key !== FALSE) {
            unset($this->attributes['class'][$key]);
        }
    }

    function setClass($args) {
        if (count($args)) { // ->class('large','selected')
            if (count($args) === 1) { // ->class(['large','selected'])
                $args = reset($args);
                if (!is_array($args)) { // ->class('selected')
                    $args = [$args];
                }
            }
            foreach ($args as $arg) {
                if (empty($arg)) {
                    continue;
                }
                if (!isset($this->attributes['class'])) {
                    $this->attributes['class'] = [];
                }
                $this->attributes['class'][] = $arg;
            }
        }
    }

    function setStyle($args) {
        if (!count($args)) {
            $this->styles = [];
            return $this;
        }
        if (is_string($args[0])) {
            if (isset($args[1])) {
                $this->styles = array_merge($this->styles, [$args[0] => $args[1]]);
            } else { // NULL or not passed => remove style
                unset($this->styles[$args[0]]);
            }
        } elseif (is_array($args[0])) {
            $this->styles = array_merge($this->styles, $args[0]);
        } else {
            p("style arg of wrong type: " . gettype($args[0]) . " ... dumping args:");
            t($args);
        }
        return $this;
    }

    function renderAttributes() {
        $attr = '';
        foreach ($this->emptyAttributes as $name) {
            $attr .= " $name";
        }
        if (count($this->styles)) {
            $styles = $this->attributes['style'] ?? '';
            foreach ($this->styles AS $prop => $value) {
                $styles .= "$prop:$value;";
            }
            $this->attributes['style'] = $styles;
        }
        foreach ($this->attributes as $name => $values) {
            if ($name === 'id') {
                continue;
            }
            $lastError = error_get_last();
            $value = is_array($values) ? implode(' ', $values) : $values;
            if (error_get_last() && error_get_last() !== $lastError) {
                p(error_get_last());
                p($this->getPath());
                t($this->attributes);
            }
            if (is_null($value)) {
                $value = ''; // php8 warning
            }
            if (is_object($value)) {
                p($this->getPath());
                p("htmlElement->renderAttributes() error: property of '$name' id object");
                d($this->attributes, 5);
            }
            $attr .= " $name='" . addcslashes($value, "'") . "'";
        }
        return $attr;
    }

    function toHtml($level = 0, $readable = FALSE) {
        if ($this->hidden) {
            return '';
        }
        $nl = $readable ? "\n" : '';
        $in = $readable ? str_repeat("\t", $level) : '';
        $attrs = $this->renderAttributes();
        if (in_array($this->tag, htmlReference::$selfclosingTags)) {
            return "$nl$in<$this->tag$attrs />";
        }
        return "$nl$in<$this->tag$attrs>" . parent::toHtml($level, $readable) . "</$this->tag>";
    }

}

<?php

#[AllowDynamicProperties]
class obj {

	static function fill($default, $override) {
		$return = (object) $default;
		foreach ($return AS $key => $val) {
			if (is_object($override)) {
				if (property_exists($override, $key)) {
					$return->$key = $override->$key;
				}
			}
			if (is_array($override)) {
				if (array_key_exists($key, $override)) {
					$return->$key = $override[$key];
				}
			}
		}
		return $return;
	}

	static function fillIfNotSet(&$obj, $default) {
		foreach ($default AS $key => $val) {
			if (!is_object($obj)) {
				$obj = (object) [];
			}
			if (!property_exists($obj, $key)) {
				$obj->$key = $default->$key;
			} else {
				if (is_object($val)) {
					self::fillIfNotSet($obj->$key, $val);
				}
			}
		}
		return $obj;
	}

	static function strip(&$obj, $referenceStructure, $depth = 0, &$log = FALSE) {
		foreach ($obj AS $key => $val) {
			if (!property_exists($referenceStructure, $key)) {
				unset($obj->$key);
				is_array($log) && ($log[] = "remove '$key'");
			} else {
				if (is_object($val) && $depth !== 1) {
					self::strip($obj->$key, $referenceStructure->$key, $depth--, $log);
				}
			}
		}
		return $obj;
	}

	static function extend(&$oa, $merge) { // unfinshed
		switch (gettype($oa) . '<' . gettype($merge)) {
			case 'array<array':
			case 'array<object':
				foreach ($merge AS $key => $value) {
					obj::extend($oa[$key], $value);
				}
				break;
			case 'object<object':
			case 'object<array':
				foreach ($merge AS $key => $value) {
					obj::extend($oa->$key, $value);
				}
				break;
			default:
				$oa = $merge;
		}
		return $oa;
	}

	static function merge(&$obj, $merge) {
		if (is_array($obj)) {
			$obj = (object) $obj;
		}
		foreach ((object) $merge AS $key => $val) {
			$obj->$key = $val;
		}
		return $obj;
	}

	static function isOrExtends($obj, $className) {
		return get_class($obj) === $className || is_subclass_of($obj, $className);
	}

	static function getRootClass($object) {
		return self::getClassAtLevel($object, 1);
	}

	static function getClassAtLevel($object, $level) {
		return self::getClassHirachy(get_class($object))[$level - 1];
	}

	static function getClassHirachy($className) {
		$hirachy = [];
		do {
			$hirachy[] = $className;
			$className = get_parent_class($className);
		} while ($className);
		return array_reverse($hirachy);
	}

	static function getClassOrType($reference) {
		if (is_object($reference)) {
			return get_class($reference);
		} else {
			return gettype($reference);
		}
	}

	static function hasOwnMethod($object, $method) {
		if (method_exists($object, $method)) {
			return (new ReflectionMethod($object, $method))->class === get_class($object);
		}
		return FALSE;
	}

	static function descent($object, $path) {
		$pathArr = is_string($path) ? explode('->', $path) : $path;
		$node = $object;
		foreach ($pathArr AS $prop) {
			if (is_object($node) && property_exists($node, $prop)) {
				$node = $node->$prop;
			} else {
				return NULL;
			}
		}
		return $node;
	}

	static function debug($val) {
		if (is_scalar($val)) {
			return (string) $val;
		}
		if (is_null($val)) {
			return "NULL";
		}
		return "object of type '" . get_class($val) . "'";
	}
}

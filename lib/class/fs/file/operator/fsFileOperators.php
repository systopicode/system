<?php

#[AllowDynamicProperties]
class fsFileOperators
{

	static $existingOperators;

	/**
	 * Central conversion table: source=>target -> [operator, priority]
	 * Priority: lower = better. Future: add quality, performance, features.
	 */
	static array $conversions = [
		'pdf=>jpg' => ['operator' => 'magick', 'priority' => 1],
		'pdf=>png' => ['operator' => 'magick', 'priority' => 1],
	];

	static function getExistingOperators()
	{
		if (self::$existingOperators) {
			return self::$existingOperators;
		}
		self::$existingOperators = array_reduce(fs::glob(__DIR__ . '/*.php'), function ($operators, $filename) {
			$operatorName = self::filepath2name($filename); //.'Operator';
			if ($operatorName) {
				$operators[] = $operatorName;
			}
			return $operators;
		}, []);
		return self::$existingOperators;
	}

	static function filepath2name($filepath)
	{
		if (preg_match('~_([^_/]+)\.php$~', $filepath, $match)) {
			return $match[1];
		}
	}

	function __construct($fsFile)
	{
		$this->file = $fsFile;
	}

	public $operators = [];

	function getOperator($name): ?fsFileOperator
	{
		if (!key_exists($name, $this->operators)) {
			$className = "fsFileOperator_$name";
			$this->operators[$name] = new $className($this, $name);
		}
		return $this->operators[$name];
	}

	function destroy($operator): bool
	{
		if (key_exists($operator->name, $this->operators)) {
			unset($this->operators[$operator->name]);
			return true;
		}
		return false;
	}

	static function findOperatorFor(string $sourceType, string $targetType): ?string {
		$key = "$sourceType=>$targetType";

		if (isset(self::$conversions[$key])) {
			return self::$conversions[$key]['operator'];
		}

		foreach (self::getExistingOperators() as $name) {
			$className = "fsFileOperator_$name";
			if (!class_exists($className, true)) continue;
			$caps = $className::$capabilities;
			if (in_array($sourceType, $caps['read']) && in_array($targetType, $caps['write'])) {
				return $name;
			}
		}
		return null;
	}
}

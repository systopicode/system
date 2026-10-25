<?php

#[AllowDynamicProperties]
class fsFileOperators
{

	static $existingOperators;

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

	/** why the last firstOperator() found nothing - one reason per operator tried */
	public string $lastError = '';

	/**
	 * The first of the named operators that does the job.
	 *
	 * Tried in the given order: skipped when it is not available here or
	 * cannot read the file, otherwise $job($operator) runs, and the operator
	 * is returned as soon as it has a result. NULL when none had one;
	 * lastError then lists the reason for each.
	 *
	 * The order is the caller's decision. Which tool is right depends on the
	 * job - one resamples or composites better, the other is faster or runs
	 * in its own process - so it is written where the job is, with the reason.
	 *
	 * @param list<string> $names operator names, in the order to try them
	 * @param callable(fsFileOperator): mixed $job configures and renders
	 */
	function firstOperator(array $names, callable $job): ?fsFileOperator
	{
		$file = $this->file;
		$type = (string) ($file->exists ? ($file->mimeext ?: $file->standardizedExtension) : $file->standardizedExtension);
		$reasons = [];
		foreach ($names as $name) {
			$className = "fsFileOperator_$name";
			if (!class_exists($className, true)) {
				$reasons[] = "$name: no such operator";
				continue;
			}
			if (!$className::isAvailable()) {
				$reasons[] = "$name: not available here";
				continue;
			}
			if (!$className::canRead($type)) {
				$reasons[] = "$name: cannot read '$type'";
				continue;
			}
			// a fresh instance: one that mediainfo or an earlier job already
			// read would ignore setMegapixels()/setPage() set now
			if (isset($this->operators[$name])) {
				$this->operators[$name]->destroy();
			}
			$operator = $this->getOperator($name);
			$job($operator);
			if ($operator->hasResult()) {
				$this->lastError = '';
				return $operator;
			}
			$reasons[] = "$name: " . ($operator->lastError ?: 'no result');
			$operator->cleanup();
			$operator->destroy();
		}
		$this->lastError = $reasons ? implode(' | ', $reasons) : 'no operator named';
		return null;
	}
}

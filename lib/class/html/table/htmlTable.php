<?php

#[AllowDynamicProperties]
class htmlTable extends htmlElement
{

	static function create($selectorOrType = 'keyvalue')
	{
		if (in_array($selectorOrType, ['keyvalue', 'keycolumn'])) {
			return htmlElementSelector::getElement("table.$selectorOrType", __CLASS__)->setType($selectorOrType);
		} else {
			return htmlElementSelector::getElement("$selectorOrType", __CLASS__); // maybe returns htmlElement having rows() method by attribute
		}
	}

	private $type;
	private $head;
	private $trDataKeys = [];

	function setType($type)
	{
		$this->type = $type;
		return $this;
	}

	function class($className)
	{ // first 
		parent::class($className);
		if (!$this->type) {
			$this->type = $className;
		}
		return $this;
	}

	function head($data)
	{
		$this->head = $this->append('tr');
		foreach ($data as $key => $label) {
			if (strpos($key, 'data-') === 0) {
				$this->trDataKeys[] = $key;
				continue;
			}
			if ($label === 'tr[style]') {
				continue;
			}
			$this->head->append("th")->text($label);
		}
		return $this;
	}

	function rows($data)
	{
		switch ($this->type) {
			case 'keyvalue':
				foreach ($data as $key => $value) {
					if (is_object($value) || is_array($value)) {
						$value = print_r($value, TRUE);
					}
					if (is_bool($value)) {
						$value = $value ? 'TRUE' : 'FALSE';
					}
					if (is_null($value)) {
						$value = 'NULL';
					}
					$this->append('tr td.key')->text($key)->close()
						->append('td.value')->text($value)
					;
				}
				break;
			case 'keycolumn':
				if (!count($data)) {
					$this->append('tr')->append('td')->text('your table seems to be empty.');
					return $this;
				}
				if (!$this->head) {
					$keys = array_keys(reset($data));
					$this->head(array_combine($keys, $keys));
				}
				foreach ($data as $cells) {
					$tr = $this->append('tr');
					foreach ($cells as $key => $cell) {
						if ($key === 'tr[style]' && $cell) {
							$tr->style($cell);
							continue;
						}
						if (in_array($key, $this->trDataKeys)) {
							$tr->attributes[$key] = $cell;
							continue;
						}
						if (is_object($cell) || is_array($cell)) {
							$tr->append('td')->text(json_encode($cell));
						} else {
							$tr->append('td')->text($cell);
						}
					}
				}
				break;
		}
		return $this;
	}
}

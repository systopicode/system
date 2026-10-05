<?php

#[AllowDynamicProperties]
class clientCommand {
	public function __call($name, $arguments) {
		// p('clientCommand',$name,$arguments);
		$this->$name = $arguments[0];
		return $this;
	}
}

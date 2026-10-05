<?php

interface actions {
	// determines wether a module executes its own methods if the action is called
}

interface clientClass {
	// expects js file in same Folder 
	// sys.lib.add(setup, function(){ ...code });
	// if file is not found, parent folders are scanned
}

interface clientSingleton {
	
}

interface clientData {

	static function clientData_export();
//	return (object) [
//		'route' => 'singletons.http',
//		'data' => (object) [
//			'key'=>'value',
//		]
}

/**
 * Legacy alias for PreloadsClientScript.
 * Classes implementing this interface have a JS file that is loaded eagerly.
 */
interface clientPreload extends \Systopic\System\Client\Contracts\PreloadsClientScript {}

/**
 * Legacy alias — marks a class as having an instance-based JS counterpart.
 */
interface clientInstance {}

interface sessionData {
	
}

interface recordData {
	
}

class clientInterfaces {

	public static $clientDataClasses = [];
	public static $clientDataObjects = [];

	static function register($fileInfo) { // called from autoloader
		if ($fileInfo->reflectionClass->implementsInterface('clientData')) {
			self::$clientDataClasses[] = $fileInfo->className;
		}
		if ($fileInfo->reflectionClass->implementsInterface(\Systopic\System\Client\Contracts\PreloadsClientScript::class)) {
			client::loadJSfromFileInfo($fileInfo);
		}
	}

	static function clientData_export() {
		scripttime('begin export client data');
		$export = [];
		foreach (self::$clientDataClasses AS $clientDataClass) {
			$clientData = forward_static_call([$clientDataClass, 'clientData_export']);
			if (!is_object($clientData) && !is_array($clientData)) {
				echo "clientData_export of '$clientDataClass' did not return an object";
				d("clientData_export of '$clientDataClass' did not return an object");
			}
			$export[] = $clientData;
		}
		return $export;
	}

	static function updateClientData($className) {
		self::$clientDataClasses[] = $className;
	}

}

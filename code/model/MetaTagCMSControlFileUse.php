<?php


class MetaTagCMSControlFileUse extends DataObject {

	private static $file_usage_array = array();

	private static $excluded_classes = array(
		"SiteTree_ImageTracking"
	);

	//database
	public static $db = array(
		"DataObjectClassName" => "Varchar(255)",
		"DataObjectFieldName" => "Varchar(255)",
		"FileClassName" => "Varchar(255)",
		"ConnectionType" => "Enum('HAS_ONE,HAS_MANY,MANY_MANY,BELONGS_MANY_MANY')"
	);

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		//start again
		DB::query("DELETE FROM \"MetaTagCMSControlFileUse\";");
		//get all classes
		$allClasses = ClassInfo::subclassesFor("DataObject");
		//get all file classes
		$fileClasses = ClassInfo::subclassesFor("File");
		// files can have files attached to them so we have commented out the line below
		//$allClassesExceptFiles = array_diff($allClasses, $fileClasses);
		//lets go through class
		foreach($allClasses as $class) {
			//HAS_ONE
			$hasOneArray = null;
			//get the has_one fields
			$newItems = (array) Object::uninherited_static($class, 'has_one');
			// Validate the data
			//do we need this?
			$hasOneArray = $newItems; //isset($hasOneArray) ? array_merge($newItems, (array)$hasOneArray) : $newItems;
			//lets inspect
			if($hasOneArray && count($hasOneArray)) {
				foreach($hasOneArray as $fieldName => $hasOneClass) {
					if(in_array($hasOneClass, $fileClasses)) {
						if(!DB::query("
							SELECT COUNT(*)
							FROM \"MetaTagCMSControlFileUse\"
							WHERE \"DataObjectClassName\" = '$class' AND  \"DataObjectFieldName\" = '$fieldName' AND \"FileClassName\" = '$hasOneClass'
						")->value()) {
							$this->createNewRecord($class, $fieldName, $hasOneClass, "HAS_ONE");
						}
					}
				}
			}
			$hasManyArray = null;
			$newItems = (array) Object::uninherited_static($class, 'has_many');
			// Validate the data
			$hasManyArray = $newItems; //isset($hasManyArray) ? array_merge($newItems, (array)$hasManyArray) : $newItems;
			if($hasManyArray && count($hasManyArray)) {
				foreach($hasManyArray as $fieldName => $hasManyClass) {
					if(in_array($hasManyClass, $fileClasses)) {
						if(!DB::query("
							SELECT COUNT(*)
							FROM \"MetaTagCMSControlFileUse\"
							WHERE \"DataObjectClassName\" = '$hasManyClass' AND  \"DataObjectFieldName\" = '$fieldName' AND \"FileClassName\" = '$class'
						")->value()) {
							$this->createNewRecord($hasManyClass, $fieldName, $class, "HAS_MANY");
						}
					}
				}
			}
			//many many
			$manyManyArray = null;
			$newItems = (array) Object::uninherited_static($class, 'many_many');
			$manyManyArray = $newItems;
			//belongs many many
			$newItems = (array) Object::uninherited_static($class, 'belongs_many_many');
			$manyManyArray = isset($manyManyArray) ? array_merge($newItems, $manyManyArray) : $newItems;
			//do both
			if($manyManyArray && count($manyManyArray)) {
				foreach($manyManyArray as $fieldName => $manyManyClass) {
					if(in_array($manyManyClass, $fileClasses)) {
						if(!DB::query("
							SELECT COUNT(*)
							FROM \"MetaTagCMSControlFileUse\"
							WHERE \"DataObjectClassName\" = '$class' AND  \"DataObjectFieldName\" = '$fieldName' AND \"FileClassName\" = '$manyManyClass'
						")->value()) {
							$this->createNewRecord($class, $fieldName, $manyManyClass, "MANY_MANY");
						}
						if(!DB::query("
							SELECT COUNT(*)
							FROM \"MetaTagCMSControlFileUse\"
							WHERE \"DataObjectClassName\" = '$manyManyClass' AND  \"DataObjectFieldName\" = '$fieldName' AND \"FileClassName\" = '$class'
						")->value()) {
							$this->createNewRecord($manyManyClass, $fieldName, $class, "BELONGS_MANY_MANY");
						}
					}
				}
			}
		}
	}

	private function createNewRecord($dataObjectClassName, $dataObjectFieldName, $fileClassName, $connectionType) {
		if(in_array($dataObjectClassName, self::$excluded_classes)  || in_array($fileClassName, self::$excluded_classes)) {
			return;
		}
		$obj = new MetaTagCMSControlFileUse();
		$obj->DataObjectClassName = $dataObjectClassName;
		$obj->DataObjectFieldName = $dataObjectFieldName;
		$obj->FileClassName = $fileClassName;
		$obj->ConnectionType = $connectionType;
		$obj->write();
		if(ClassInfo::is_subclass_of($dataObjectClassName, "SiteTree")) {
			$obj = new MetaTagCMSControlFileUse();
			$obj->DataObjectClassName = $dataObjectClassName."_Live";
			$obj->DataObjectFieldName = $dataObjectFieldName;
			$obj->FileClassName = $fileClassName;
			$obj->ConnectionType = $connectionType;
			$obj->write();
		}
		DB::alteration_message("creating new MetaTagCMSControlFileUse: $dataObjectClassName, $dataObjectFieldName, $fileClassName, $connectionType");
	}

	public static function file_usage_count($fileID, $quickBooleanCheck = false) {
		if(!isset(self::$file_usage_array[$fileID])) {
			self::$file_usage_array[$fileID] = 0;
			$sql = "SELECT COUNT(ID) FROM \"File\" WHERE \"ParentID\" = {$fileID};";
			$result = DB::query($sql, false);
			$childCount = $result->value();
			if($childCount) {
				self::$file_usage_array[$fileID] = $childCount;
				return self::$file_usage_array[$fileID];
			}
			$checks = DataObject::get("MetaTagCMSControlFileUse");
			if($checks) {
				foreach($checks as $check) {
					$sql = "";
					switch ($check->ConnectionType) {
						case "HAS_ONE":
							$sql = "
								SELECT COUNT(\"{$check->DataObjectClassName}\".\"ID\")
								FROM \"{$check->DataObjectClassName}\"
								WHERE \"{$check->DataObjectFieldName}ID\" = {$fileID};
							";
							break;
						case "HAS_MANY":
							$sql = "
								SELECT COUNT(\"{$check->DataObjectClassName}\".\"ID\")
								FROM \"{$check->DataObjectClassName}\"
									INNER JOIN  {$check->FileClassName}
										ON \"{$check->DataObjectClassName}\".\"{$check->FileClassName}ID\" = \"{$check->FileClassName}\".\"ID\"
								WHERE \"{$check->DataObjectClassName}\".\"ID\" = {$fileID};
							";
							break;
						case "MANY_MANY":
							$sql = "
								SELECT COUNT(\"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\".\"ID\")
								FROM \"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\"
								WHERE \"{$check->FileClassName}ID\" = $fileID;
							";
							break;
					}
					$result = DB::query($sql, false);
					$count = $result->value();
					if($count) {
						if($quickBooleanCheck) {
							return true;
						}
						else {
							self::$file_usage_array[$fileID] += $count;
						}
					}
				}
			}
		}
		return self::$file_usage_array[$fileID];
	}

	private static $file_sub_string = array(
		".jpg",
		".png",
		".jpeg",
		".gif",
		".JPG",
		".PNG",
		".JPEG",
		".GIF"
	);

	public static function upgrade_file_names(){
		set_time_limit(60*10); // 10 minutes
		$whereArray = array();
		$whereArray[] = "\"Title\" = \"Name\"";
		foreach(self::$file_sub_string as $subString) {
			$whereArray[] = "LOCATE('$subString', \"Title\") > 0";
		}
		$whereString =  "\"ClassName\" <> 'Folder' AND ( ".implode (" OR ", $whereArray)." )";
		$files = DataObject::get("File", $whereString);
		if($files && $files->count()) {
			foreach($files as $file) {
				self::upgrade_file_name($file);
			}
		}
		else {
			DB::alteration_message("All files have proper names", "created");
		}
	}

	private static function upgrade_file_name(File $file) {
		$fileID = $file->ID;
		if(self::file_usage_count($fileID)) {
			$checks = DataObject::get("MetaTagCMSControlFileUse");
			if($checks && $checks->count()) {
				foreach($checks as $check) {
					switch ($check->ConnectionType) {
						case "HAS_ONE":
							$objName = $check->DataObjectClassName;
							$where = "\"{$check->DataObjectFieldName}ID\" = {$fileID}";
							$innerJoinTable = "";
							$innerJoinJoin = "";
							break;
						case "HAS_MANY":
							$objName = $check->DataObjectClassName;
							$where = "\"{$check->DataObjectFieldName}\".\"ID\" = {$fileID}";
							$innerJoinTable = "$check->FileClassName";
							$innerJoinJoin = "\"{$check->DataObjectClassName}\".\"{$check->FileClassName}ID\" = \"{$check->FileClassName}\".\"ID\"";
							break;
						case "BELONGS_MANY_MANY":
							$objName = "";
							$where = "";
							$innerJoinTable = "";
							$innerJoinJoin = "";
							break;
						case "MANY_MANY":
							$objName = $check->DataObjectClassName;
							$where = "\"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\".\"{$check->FileClassName}ID\" = $fileID";
							$innerJoinTable = "{$check->DataObjectClassName}_{$check->DataObjectFieldName}";
							$innerJoinJoin = "\"{$check->DataObjectClassName}\".\"ID\" = \"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\".\"ID\"";
							break;
					}
					$join = "";
					if($innerJoinTable && $innerJoinJoin) {
						$join = " INNER JOIN $innerJoinTable ON $innerJoinJoin ";
					}
					if($objName) {
						$sort = null;
						$limit = 1;
						echo "<hr />";
						echo "TYPE: ".$check->ConnectionType."<br />";
						echo "CLASS: ".$objName."<br />";
						echo "WHERE: ".$where."<br />";
						echo "SORT: ".$sort."<br />";
						echo "JOIN: ".$join."<br />";
						echo "LIMIT: ".$limit."<br />";
						echo "<hr />";
						$objects = DataObject::get(
							$objName,
							$where,
							$sort,
							$join,
							$limit
						);
						if($objects && $objects->count()) {
							$obj = $objects->First();
							$oldTitle = $file->Title;
							$newTitle =  $obj->getTitle();
							if((substr($newTitle, 0, 1) != "#") || (intval($newTitle) == $newTitle)) {
								$file->Title = $newTitle;
								$file->write();
								DB::alteration_message("Updating ".$file->Name." title from ".$oldTitle." to ".$newTitle, "created");
							}
							else {
								DB::alteration_message("There is no real title for ".$obj->ClassName.": ".$newTitle);
							}
						}
						else {
							DB::alteration_message("File <i>".$file->Title."</i> is not being used - SECOND CHECK", "deleted");
						}
					}
				}
			}
			else {
				DB::alteration_message("There are no checks", "deleted");
			}
		}
		else {
			DB::alteration_message("File <i>".$file->Title."</i> is not being used");
		}
		return self::$file_usage_array[$fileID];
	}

}




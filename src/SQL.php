<?php

class SQL {
	//Returns insert id
	static function iud(string $query, string $paramtypes = "", ...$values): int {
		//Escape HTML Code
		$escaped = array();
		foreach ($values as $value) {
			if (is_string($value) && !self::is_serialized($value)) {
				//Remove unwanted html elements
				$value = strip_tags($value);
				//Converts text to html so that values won't get escaped twice
				array_push($escaped, htmlentities(html_entity_decode($value), ENT_QUOTES));
			} else {
				array_push($escaped, $value);
			}
		}
		return self::doIUD($query, $paramtypes, $escaped);
	}

	//IUDs all ready escaped html code
	static function iud_escaped(string $query, string $paramtypes = "", ...$values): int {
		return self::doIUD($query, $paramtypes, $values);
	}

	private static function doIUD($query, $paramtypes, $values): int {
		global $mysqli;
		$stmt = $mysqli->prepare($query);
		if ($stmt === false) {
			error_log('Error preparing statement for query: ' . $query . ';' . json_encode($mysqli->error_list));
			throw new Exception();
		}

		if (!empty($paramtypes)) {
			$stmt->bind_param($paramtypes, ...$values);
		}

		if ($stmt->param_count != count($values)) {
			error_log('Wrong parameter count for query: ' . $query);
		}

		$stmt->execute();
		$insertId = $stmt->insert_id;
		if (!empty($stmt->error)) {
			error_log('MySQL error: ' . $stmt->error . "; Query: " . $query);
		}
		$stmt->close();
		return $insertId;
	}

	static function select(string $query, string $paramtypes = "", ...$values) {
		global $mysqli;
		$stmt = $mysqli->prepare($query);
		if (!empty($paramtypes)) {
			$stmt->bind_param($paramtypes, ...$values);
			if ($stmt->param_count != count($values)) {
				error_log('Wrong parameter count for query: ' . $query);
			}
		}
		$stmt->execute();
		$Array = $stmt->get_result()->fetch_array(MYSQLI_ASSOC);
		$stmt->close();
		return $Array;
	}

	static function select_array(string $query, string $paramtypes = "", ...$values): array {
		global $mysqli;
		$stmt = $mysqli->prepare($query);
		if (!empty($paramtypes)) {
			$stmt->bind_param($paramtypes, ...$values);
			if ($stmt->param_count != count($values)) {
				error_log('Wrong parameter count for query: ' . $query);
			}
		}
		$stmt->execute();
		$results = $stmt->get_result();
		$stmt->close();
		return SQL::fetch_md_array($results);
	}

	/*
	static function select_multiple($table, $rows, $inArray) {
		global $mysqli;
		$in = str_repeat('?,', count($inArray) - 1) . '?';
		$sql = "SELECT " . $rows . " FROM " . $table . " WHERE id IN ($in)";
		$stmt = $mysqli->prepare($sql);
		$types = str_repeat('s', count($inArray));
		$stmt->bind_param($types, ...$inArray);
		$stmt->execute();
		$results = $stmt->get_result();
		$stmt->close();
		return SQL::fetch_md_array($results);
	}

	static function update_multiple($table, $set, $inArray) {
		global $mysqli;
		$in = str_repeat('?,', count($inArray) - 1) . '?';
		$sql = "UPDATE " . $table . " SET " . $set . " WHERE id IN ($in)";
		$stmt = $mysqli->prepare($sql);
		$types = str_repeat('s', count($inArray));
		$stmt->bind_param($types, ...$inArray);
		$stmt->execute();
	}
	*/


//Helper functions
	static function fetch_md_array($results): array {
		$array = array();
		while ($row = mysqli_fetch_array($results)) {
			$array[] = $row;
		}

		return $array;
	}

	static function castQryToObj($query, $object): array {
		$result = array();
		foreach ($query as $entry) {
			$tmp = new $object();
			$tmp->cast($entry);
			array_push($result, $tmp);
		}
		return $result;
	}

	static function arrayToValue($data) {
		if (is_null($data)) {
			return NULL;
		} else {
			return array_shift($data);
		}
	}

	//Check if data is serialized
	static function is_serialized($data): bool {
		// if it isn't a string, it isn't serialized
		if (!is_string($data))
			return false;
		$data = trim($data);
		if ('N;' == $data)
			return true;
		if (!preg_match('/^([adObis]):/', $data, $badions))
			return false;
		switch ($badions[1]) {
			case 'a' :
			case 'O' :
			case 's' :
				if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
					return true;
				break;
			case 'b' :
			case 'i' :
			case 'd' :
				if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data))
					return true;
				break;
		}
		return false;
	}
}

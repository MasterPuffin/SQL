<?php

require_once 'SQLSession.php';

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
		global $sql;
		$stmt = $sql->mysqli->prepare($query);
		if ($stmt === false) {
			error_log('Error preparing statement for query: ' . $query . ';' . json_encode($sql->mysqli->error_list));
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
		global $sql;

		$cacheStr = self::toCacheString($query, $paramtypes, ...$values);
		if ($sql->useCaching && array_key_exists($cacheStr, $sql->cache)) {
			return $sql->cache[$stmt];
		}

		$stmt = $sql->mysqli->prepare($query);
		if (!empty($paramtypes)) {
			$stmt->bind_param($paramtypes, ...$values);
			if ($stmt->param_count != count($values)) {
				error_log('Wrong parameter count for query: ' . $query);
			}
		}

		$stmt->execute();
		$Array = $stmt->get_result()->fetch_array(MYSQLI_ASSOC);
		$stmt->close();

		if ($sql->useCaching) {
			$sql->cache[$cacheStr] = $Array;
		}

		return $Array;
	}

	static function select_array(string $query, string $paramtypes = "", ...$values): array {
		global $sql;

		$cacheStr = self::toCacheString($query, $paramtypes, ...$values);
		if ($sql->useCaching && array_key_exists($cacheStr, $sql->cache)) {
			return $sql->cache[$stmt];
		}

		$stmt = $sql->mysqli->prepare($query);
		if (!empty($paramtypes)) {
			$stmt->bind_param($paramtypes, ...$values);
			if ($stmt->param_count != count($values)) {
				error_log('Wrong parameter count for query: ' . $query);
			}
		}

		$stmt->execute();
		$results = $stmt->get_result();
		$stmt->close();

		if ($sql->useCaching) {
			$sql->cache[$cacheStr] = SQL::fetch_md_array($results);
		}

		return SQL::fetch_md_array($results);
	}

	static function init(string $host, string $database, string $username = "root", string $password = "", bool $useCaching = false) {
		$mysqli = mysqli_connect($host, $username, $password) or die("Can't connect to database");
		mysqli_select_db($mysqli, $database) or die ("Can't select database");
		mysqli_set_charset($mysqli, "utf8mb4");
		return new SQLSession($mysqli, $useCaching);
	}


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

	private static function toCacheString(string $query, string $paramtypes = "", ...$values) {
		return implode("", [$query, $paramtypes, ...$values]);
	}
}

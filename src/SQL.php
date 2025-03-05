<?php

require_once 'SQLSession.php';

class SQL {
	//TODO make universal
	private static string $legacyClassNamePrefix = 'App\\Models\\';

	//Returns insert id
	static function iud(string $query, string $paramtypes = "", ...$values): int {
		//Escape HTML Code
		$escaped = [];
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

		if ($sql->useCaching) {
			$cacheStr = self::toCacheString($query, $paramtypes, ...$values);
			if (array_key_exists($cacheStr, $sql->cache)) {
				return $sql->cache[$cacheStr];
			}
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

		if ($sql->useCaching) {
			$cacheStr = self::toCacheString($query, $paramtypes, ...$values);
			if (array_key_exists($cacheStr, $sql->cache)) {
				return $sql->cache[$cacheStr];
			}
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

		$fetchedResults = SQL::fetch_md_array($results);
		if ($sql->useCaching) {
			$sql->cache[$cacheStr] = $fetchedResults;
		}
		return $fetchedResults;
	}

	static function init(string $host, string $database, string $username = "root", string $password = "", bool $useCaching = false) {
		$mysqli = mysqli_connect($host, $username, $password) or die("Can't connect to database");
		mysqli_select_db($mysqli, $database) or die ("Can't select database");
		mysqli_set_charset($mysqli, "utf8mb4");
		return new SQLSession($mysqli, $useCaching);
	}

	//Helper functions
	static function fetch_md_array($results): array {
		$array = [];
		while ($row = mysqli_fetch_array($results)) {
			$array[] = $row;
		}

		return $array;
	}

	/**
	 * @throws Throwable
	 */
	static function castQryToObj($query, $object): array {
		$result = [];

		// Get reflection of the class
		try {
			$reflection = new ReflectionClass($object);
		} catch (Throwable $e) {
			if (!str_starts_with($object, self::$legacyClassNamePrefix)) {
				$object = self::$legacyClassNamePrefix . $object;
				$reflection = new ReflectionClass($object);
			} else {
				throw $e;
			}
		}

		foreach ($query as $entry) {
			// Get constructor
			$constructor = $reflection->getConstructor();

			if ($constructor === null) {
				// No constructor, create object directly
				$tmp = $reflection->newInstance();
			} else {
				// Get constructor parameters
				$params = $constructor->getParameters();
				$constructorArgs = [];

				// Build constructor arguments from entry data
				foreach ($params as $param) {
					$paramName = $param->getName();
					if (isset($entry[$paramName])) {
						$value = $entry[$paramName];
						// Handle type conversion if needed
						if ($param->hasType()) {
							$type = $param->getType();
							if ($type instanceof ReflectionNamedType) {
								switch ($type->getName()) {
									case 'int':
										$value = (int)$value;
										break;
									case 'string':
										$value = (string)$value;
										break;
									case 'float':
										$value = (float)$value;
										break;
									case 'bool':
										$value = (bool)$value;
										break;
								}
							}
						}
						$constructorArgs[] = $value;
					} else if ($param->isOptional()) {
						$constructorArgs[] = $param->getDefaultValue();
					} else {
						throw new Exception("Missing required constructor parameter: $paramName");
					}
				}

				// Create new instance with constructor arguments
				$tmp = $reflection->newInstanceArgs($constructorArgs);
			}

			// Only call cast if the method exists
			if (method_exists($tmp, 'cast')) {
				$tmp->cast($entry);
			}
			$result[] = $tmp;
		}
		return $result;
	}

	static function arrayToValue($data) {
		if (is_null($data)) {
			return null;
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

	private static function toCacheString(string $query, string $paramtypes = "", ...$values): string {
		//This currently doesn't work with MD Arrays.
		//TODO FIX
		return implode("", [
			$query,
			$paramtypes,
			...$values
		]);
	}
}

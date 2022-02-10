<?php

class SQLSession {
	public mysqli $mysqli;
	public bool $useCaching;
	public array $cache = [];

	public function __construct(mysqli $mysqli, bool $useCaching = false) {
		$this->mysqli = $mysqli;
		$this->useCaching = $useCaching;
	}
}
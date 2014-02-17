<?php
// emulate the silverstripe DB::query() function

/** @noinspection PhpUndefinedClassInspection */
class DB {
	/** @var mysqli */
	static $conn;


	/**
	 * Creates the connection
	 */
	static function init(){
		global $databaseConfig;
		self::$conn = new mysqli($databaseConfig['server'], $databaseConfig['username'], $databaseConfig['password'], $databaseConfig['database']);
	}


	/**
	 * @param string $sql
	 * @param int $errorLevel
	 * @return LivePubQuery
	 */
	static function query($sql, $errorLevel = E_USER_ERROR){
		$handle = self::$conn->query($sql);

		if (!$handle && $errorLevel) {
			die("SQL Error.");
		}

		return new LivePubQuery($handle);
	}

}


class LivePubQuery {
	/** @var  mysqli_result */
	private $handle;


	/**
	 * @param $handle
	 */
	public function __construct($handle) {
		$this->handle = $handle;
	}


	public function __destruct() {
		if(is_object($this->handle)) {
			$this->handle->free();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function seek($row) {
		if(is_object($this->handle)) {
			return $this->handle->data_seek($row);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function numRecords() {
		if(is_object($this->handle)) {
			return $this->handle->num_rows;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function nextRecord() {
		if(is_object($this->handle) && ($data = $this->handle->fetch_assoc())) {
			return $data;
		} else {
			return false;
		}
	}

}

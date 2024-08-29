<?php namespace sox\sdk\com;

class pdo {
	public $driver = '';
	public $conn = NULL;
	public $exec = NULL;

	public $error = '';

	public $connect_errno = '';
	public $connect_error = '';
	public $insert_id     = 0; // @@IDENTITY
	public $affected_rows = 0; // @@ROWCOUNT || row_count()

	public function __construct($dsn,$username,$password) {
		if (strpos($dsn, 'sqlsrv') !== FALSE) $this->driver = 'sqlsrv';

		$this->conn = new \PDO($dsn,$username,$password,[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]);
	}

	public function query($sql) {
		if (empty($this->conn)) return FALSE;

		$sql = str_replace('`','',$sql);

		if ($this->driver == 'sqlsrv') {
			$sql = preg_replace('/ LIMIT (.*?) /i',' ',$sql);
		}

		$this->exec = $this->conn->query($sql);

		if ($this->exec === FALSE) {
			$this->error = 'pdo error';
		}

		//SELECT, SHOW, DESCRIBE, EXPLAIN

		switch(strtoupper(substr($sql,0,4))) {
			case 'SELE':
			case 'SHOW':
			case 'DESC':
			case 'EXPL':
			case 'SP_H':
			case 'SP_C':
				return $this->exec ? $this : FALSE;
				break;
			
			default:
				return $this->exec ? TRUE : FALSE;
				break;
		}
	}

	public function fetch_row() {
		if (empty($this->exec)) return FALSE;

		$this->exec->setFetchMode(\PDO::FETCH_NUM);

		$row = $this->exec->fetch();

		$this->exec = NULL;

		return $row;
	}

	public function fetch_all($assoc = MYSQLI_ASSOC) {
		if (empty($this->exec)) return FALSE;

		$this->exec->setFetchMode(\PDO::FETCH_ASSOC);

		return $this->exec->fetchAll();
	}
}
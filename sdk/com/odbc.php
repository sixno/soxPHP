<?php namespace sox\sdk\com;

class odbc {
	public $conn = NULL;
	public $exec = NULL;

	public $error = '';

	public $connect_errno = '';
	public $connect_error = '';
	public $insert_id     = 0; // @@IDENTITY
	public $affected_rows = 0; // @@ROWCOUNT || row_count()

	public function __construct($dsn,$username,$password,$database) {
		$this->conn = odbc_connect($dsn,$username,$password);

		if ($this->conn) {
			$this->query('USE `'.$database.'`');
		}
		else {
			$this->connect_errno = odbc_error($this->conn);
			$this->connect_error = odbc_errormsg($this->conn);
		}
	}

	public function query($sql) {
		if (empty($this->conn)) return FALSE;

		$this->exec = odbc_exec($this->conn,str_replace('`','',$sql));

		if ($this->exec === FALSE) {
			$this->error = odbc_errormsg($this->conn);
		}

		//SELECT, SHOW, DESCRIBE, EXPLAIN

		switch(strtoupper(substr($sql,0,4))) {
			case 'SELE':
			case 'SHOW':
			case 'DESC':
			case 'EXPL':
				return $this->exec ? $this : FALSE;
				break;
			
			default:
				return $this->exec ? TRUE : FALSE;
				break;
		}
	}

	public function fetch_row() {
		if (empty($this->exec)) return FALSE;

		odbc_fetch_into($this->exec,$row);

		$this->exec = NULL;

		return $row;
	}

	public function fetch_all($assoc = MYSQLI_ASSOC) {
		if (empty($this->exec)) return FALSE;

		$i = 0;
		$list = [];
		
		while (odbc_fetch_row($this->exec)) {
			$j = 0;
			$item = [];

			for($j = 1;$j <= odbc_num_fields($this->exec);$j++) {
				$field = odbc_field_name($this->exec,$j);

				if ($field == 'bucket__id') continue;

				$item[$field] = odbc_result($this->exec,$field);
			}
			
			$list[$i] = $item;

			$i++;
		}


		$this->exec = NULL;

		return $list;
	}
}
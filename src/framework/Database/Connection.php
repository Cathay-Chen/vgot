<?php
/**
 * Created by PhpStorm.
 * User: pader
 * Date: 2017/5/1
 * Time: 04:04
 */

namespace vgot\Database;


use vgot\Exceptions\DatabaseException;

/**
 * Datanase Connection
 *
 * @package vgot\Database
 * @method getConnection() Get Driver Connection Base Object
 * @method int insertId() Get last insert id
 * @method string quote(string $string) Quote a string for use in query
 */
class Connection
{

	/**
	 * Database Driver Instance
	 *
	 * @var DriverInterface
	 */
	protected $di;

	protected $config;

	/**
	 * Set fetchAll() return array index is used by special result key
	 *
	 * @var string
	 */
	protected $indexBy;

	protected $lastQuery = null;

	protected $queryRecords = [];

	public function __construct($config)
	{
		$driver = isset($config['driver']) ? $config['driver'] : 'pdo';
		$driverClass = '\vgot\Database\Driver\\'.ucfirst($driver).'Driver';

		if (!class_exists($driverClass)) {
			throw new DatabaseException("Unsupport database driver '{$config['driver']}'.");
		}

		$this->config = $config;

		$this->di = new $driverClass();
		$this->connect();
	}

	public function connect()
	{
		if (!$this->di->connect($this->config)) {
			switch ($this->di->type) {
				case 'mysql': $server = 'MySQL Server'; break;
				case 'sqlserv': $server = 'SQLServer'; break;
				case 'sqlite': $server = 'SQLite'; break;
				default:
					$server = ucfirst($this->config['type']).' Server';
			}

			throw new DatabaseException("Failed to connect to $server", $this->di);
		}
	}

	public function prepare($sql, $params=[])
	{}

	/**
	 * Do a query and get result
	 *
	 * @param string $sql
	 * @param array $params
	 * @return self
	 * @throws DatabaseException
	 */
	public function query($sql, $params=null)
	{
		//debug
		if (!empty($this->config['debug'])) {
			$qst = array_sum(explode(' ', microtime()));
		}

		$query = $this->di->query($sql);

		if (isset($qst)) {
			$qet = array_sum(explode(' ', microtime()));
			$queryTime = round(($qet - $qst), 6);
			$this->queryRecords[] = ['sql'=>$sql,'time_used'=>$queryTime];
		}

		if ($query === false) {
			throw new DatabaseException("Query error", $this->di, $sql);
		}

		$this->lastQuery = $query;

		return $this;
	}

	/**
	 * Do a query and get affected rows number
	 *
	 * @param string $sql
	 * @param array $params
	 * @return int
	 * @throws DatabaseException
	 */
	public function exec($sql, $params=null)
	{
		//debug
		if (!empty($this->config['debug'])) {
			$qst = array_sum(explode(' ', microtime()));
		}

		$affected = $this->di->exec($sql);

		if (isset($qst)) {
			$qet = array_sum(explode(' ', microtime()));
			$queryTime = round(($qet - $qst), 6);
			$this->queryRecords[] = ['sql'=>$sql,'time_used'=>$queryTime];
		}

		if ($affected === false) {
			throw new DatabaseException("Query error", $this->di, $sql);
		}

		$this->lastQuery = null;

		return $affected;
	}

	/**
	 * Fetch row from query result
	 *
	 * The is different with get(), fetch() can still fetch next row from result.
	 * That mean you can fetch all data from query result like fetchAll() but you can
	 * do something when fetch each row.
	 * If you only want to get one row, use fetchOne().
	 *
	 * @param int $fetchType
	 * @return array|false
	 */
	public function fetch($fetchType=DB::FETCH_ASSOC)
	{
		$result = $this->di->fetch($this->lastQuery, $fetchType);

		if ($result === false) {
			$this->lastQuery = null;
		}

		return $result;
	}

	/**
	 * Fetch one row from query result
	 *
	 * @param int $fetchType
	 * @return array|null
	 * @throws DatabaseException
	 */
	public function get($fetchType=DB::FETCH_ASSOC)
	{
		$result = $this->di->fetch($this->lastQuery, $fetchType);

		if ($result === false) {
			throw new DatabaseException("Fetch not a query result.");
		}

		$this->lastQuery = null;

		return $result;
	}

	/**
	 * Return first column value in row
	 *
	 * @return mixed
	 */
	public function value()
	{
		$row = $this->get(DB::FETCH_NUM);
		return $row[0];
	}

	/**
	 * Set key for fetchAll() return array
	 *
	 * @param string $key
	 * @return $this
	 */
	public function indexBy($key)
	{
		$this->indexBy = $key;
		return $this;
	}

	/**
	 * Fetch all rows from query result
	 *
	 * @param int $fetchType
	 * @return array
	 * @throws DatabaseException
	 */
	public function fetchAll($fetchType=DB::FETCH_ASSOC)
	{
		$result = $this->di->fetchAll($this->lastQuery, $fetchType);
		$this->lastQuery = null;

		//index by
		if ($this->indexBy && $result) {
			$arr = [];

			foreach ($result as $row) {
				if (!isset($row[$this->indexBy])) {
					throw new DatabaseException("Undefined indexBy key '{$this->indexBy}'.");
				}
				$arr[$row[$this->indexBy]] = $row;
			}

			$this->indexBy = null;
			return $arr;
		}

		if ($result === false) {
			throw new DatabaseException("Fetch not a query result.");
		}

		return $result;
	}

	/**
	 * Fetch a column value in first result row
	 *
	 * @param int|string $col
	 * @return mixed|bool|null
	 * @throws
	 */
	public function fetchColumn($col=0)
	{
		$val = $this->di->fetchColumn($this->lastQuery, $col, is_numeric($col) ? DB::FETCH_NUM : DB::FETCH_ASSOC);

		if ($val === false) {
			throw new DatabaseException('Fetch column error', "No found column '$col' in data row.");
		}

		$this->lastQuery = null;

		return $val;
	}

	/**
	 * Is table name has prefix
	 *
	 * @param string $table
	 * @return bool
	 */
	public function hasPrefix($table)
	{
		return (
			empty($this->config['table_prefix']) ||
			substr($table, 0, strlen($this->config['table_prefix'])) == $this->config['table_prefix']
		);
	}

	/**
	 * Get table name with prefix
	 *
	 * @param string $table
	 * @return string
	 */
	public function tableName($table)
	{
		return empty($this->config['table_prefix']) ? $table : $this->config['table_prefix'].$table;
	}

	public function getQueryRecords()
	{
		return $this->queryRecords;
	}

	/**
	 * Close database connection
	 */
	public function close()
	{
		$this->lastQuery = null;
		$this->di && $this->di->close();
	}

	public function __call($name, $args)
	{
		if (is_callable([$this->di, $name])) {
			return call_user_func_array([$this->di, $name], $args);
		} else {
			throw new \ErrorException("Call to undefined method ".__CLASS__."::$name()");
		}
	}

	public function __destruct() {
		$this->close();
	}

}
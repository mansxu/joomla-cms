<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Database
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * SQLite database driver
 *
 * @see    http://php.net/pdo
 * @since  12.1
 */
class JDatabaseDriverSqlite extends JDatabaseDriverPdo
{
	/**
	 * The name of the database driver.
	 *
	 * @var    string
	 * @since  12.1
	 */
	public $name = 'sqlite';
	
	protected $nullDate = '1970-01-01 00:00:00';

	/**
	 * The character(s) used to quote SQL statement names such as table names or field names,
	 * etc. The child classes should define this as necessary.  If a single character string the
	 * same character is used for both sides of the quoted name, else the first character will be
	 * used for the opening quote and the second for the closing quote.
	 *
	 * @var    string
	 * @since  12.1
	 */
	protected $nameQuote = '`';

	/**
	 * Destructor.
	 *
	 * @since   12.1
	 */
	public function __destruct()
	{
		$this->freeResult();
		unset($this->connection);
	}

	/**
	 * Disconnects the database.
	 *
	 * @return  void
	 *
	 * @since   12.1
	 */
	public function disconnect()
	{
		$this->freeResult();
		unset($this->connection);
	}

	/**
	 * Drops a table from the database.
	 *
	 * @param   string   $tableName  The name of the database table to drop.
	 * @param   boolean  $ifExists   Optionally specify that the table must exist before it is dropped.
	 *
	 * @return  JDatabaseDriverSqlite  Returns this object to support chaining.
	 *
	 * @since   12.1
	 */
	public function dropTable($tableName, $ifExists = true)
	{
		$this->connect();

		$query = $this->getQuery(true);

		$this->setQuery('DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $query->quoteName($tableName));

		$this->execute();

		return $this;
	}

	/**
	 * Method to escape a string for usage in an SQLite statement.
	 *
	 * Note: Using query objects with bound variables is
	 * preferable to the below.
	 *
	 * @param   string   $text   The string to be escaped.
	 * @param   boolean  $extra  Unused optional parameter to provide extra escaping.
	 *
	 * @return  string  The escaped string.
	 *
	 * @since   12.1
	 */
	public function escape($text, $extra = false)
	{
		if (is_int($text) || is_float($text))
		{
			return $text;
		}

		return SQLite3::escapeString($text);
	}

	/**
	 * Method to get the database collation in use by sampling a text field of a table in the database.
	 *
	 * @return  mixed  The collation in use by the database or boolean false if not supported.
	 *
	 * @since   12.1
	 */
	public function getCollation()
	{
		return $this->charset;
	}

	/**
	 * Shows the table CREATE statement that creates the given tables.
	 *
	 * Note: Doesn't appear to have support in SQLite
	 *
	 * @param   mixed  $tables  A table name or a list of table names.
	 *
	 * @return  array  A list of the create SQL for the tables.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableCreate($tables)
	{
		$this->connect();

		// Sanitize input to an array and iterate over the list.
		settype($tables, 'array');

		return $tables;
	}

	/**
	 * This function replaces a string identifier <var>$prefix</var> with the string held is the
	 * <var>tablePrefix</var> class variable.
	 *
	 * @param   string  $query   The SQL statement to prepare.
	 * @param   string  $prefix  The common table prefix.
	 *
	 * @return  string  The processed SQL statement.
	 *
	 * @since   12.1
	 */
	public function replacePrefix($query, $prefix = '#__')
	{
		$query = trim($query);

		if (strpos($query, '\''))
		{
			// Sequence name quoted with ' ' but need to be replaced
			if (strpos($query, 'currval'))
			{
				$query = explode('currval', $query);

				for ($nIndex = 1; $nIndex < count($query); $nIndex = $nIndex + 2)
				{
					$query[$nIndex] = str_replace($prefix, $this->tablePrefix, $query[$nIndex]);
				}

				$query = implode('currval', $query);
			}

			// Sequence name quoted with ' ' but need to be replaced
			if (strpos($query, 'nextval'))
			{
				$query = explode('nextval', $query);

				for ($nIndex = 1; $nIndex < count($query); $nIndex = $nIndex + 2)
				{
					$query[$nIndex] = str_replace($prefix, $this->tablePrefix, $query[$nIndex]);
				}

				$query = implode('nextval', $query);
			}

			// Sequence name quoted with ' ' but need to be replaced
			if (strpos($query, 'setval'))
			{
				$query = explode('setval', $query);

				for ($nIndex = 1; $nIndex < count($query); $nIndex = $nIndex + 2)
				{
					$query[$nIndex] = str_replace($prefix, $this->tablePrefix, $query[$nIndex]);
				}

				$query = implode('setval', $query);
			}

			$explodedQuery = explode('\'', $query);

			for ($nIndex = 0; $nIndex < count($explodedQuery); $nIndex = $nIndex + 2)
			{
				if (strpos($explodedQuery[$nIndex], $prefix))
				{
					$explodedQuery[$nIndex] = str_replace($prefix, $this->tablePrefix, $explodedQuery[$nIndex]);
				}
			}

			$replacedQuery = implode('\'', $explodedQuery);
		}
		else
		{
			$replacedQuery = str_replace($prefix, $this->tablePrefix, $query);
		}

		return $replacedQuery;
	}

	/**
	 * Retrieves field information about a given table.
	 *
	 * @param   string   $table     The name of the database table.
	 * @param   boolean  $typeOnly  True to only return field types.
	 *
	 * @return  array  An array of fields for the database table.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableColumns($table, $typeOnly = true)
	{
		$this->connect();

		$columns = array();
		$query = $this->getQuery(true);

		$fieldCasing = $this->getOption(PDO::ATTR_CASE);

		$this->setOption(PDO::ATTR_CASE, PDO::CASE_UPPER);

		$table = strtoupper($table);

		$query->setQuery('pragma table_info(' . $table . ')');

		$this->setQuery($query);
		$fields = $this->loadObjectList();

		if ($typeOnly)
		{
			foreach ($fields as $field)
			{
				$columns[$field->NAME] = $field->TYPE;
			}
		}
		else
		{
			foreach ($fields as $field)
			{
				// Do some dirty translation to MySQL output.
				// TODO: Come up with and implement a standard across databases.
				$columns[$field->NAME] = (object) array(
					'Field' => $field->NAME,
					'Type' => $field->TYPE,
					'Null' => ($field->NOTNULL == '1' ? 'NO' : 'YES'),
					'Default' => $field->DFLT_VALUE,
					'Key' => ($field->PK != '0' ? 'PRI' : '')
				);
			}
		}

		$this->setOption(PDO::ATTR_CASE, $fieldCasing);

		return $columns;
	}

	/**
	 * Get the details list of keys for a table.
	 *
	 * @param   string  $table  The name of the table.
	 *
	 * @return  array  An array of the column specification for the table.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableKeys($table)
	{
		$this->connect();

		$keys = array();
		$query = $this->getQuery(true);

		$fieldCasing = $this->getOption(PDO::ATTR_CASE);

		$this->setOption(PDO::ATTR_CASE, PDO::CASE_UPPER);

		$table = strtoupper($table);
		$query->setQuery('pragma table_info( ' . $table . ')');

		// $query->bind(':tableName', $table);

		$this->setQuery($query);
		$rows = $this->loadObjectList();

		foreach ($rows as $column)
		{
			if ($column->PK == 1)
			{
				$keys[$column->NAME] = $column;
			}
		}

		$this->setOption(PDO::ATTR_CASE, $fieldCasing);

		return $keys;
	}

	/**
	 * Method to get an array of all tables in the database (schema).
	 *
	 * @return  array   An array of all the tables in the database.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableList()
	{
		$this->connect();

		$type = 'table';

		$query = $this->getQuery(true)
			->select('name')
			->from('sqlite_master')
			->where('type = :type')
			->bind(':type', $type)
			->order('name');

		$this->setQuery($query);

		$tables = $this->loadColumn();

		return $tables;
	}

	/**
	 * Get the version of the database connector.
	 *
	 * @return  string  The database connector version.
	 *
	 * @since   12.1
	 */
	public function getVersion()
	{
		$this->connect();

		$this->setQuery("SELECT sqlite_version()");

		return $this->loadResult();
	}

	/**
	 * Select a database for use.
	 *
	 * @param   string  $database  The name of the database to select for use.
	 *
	 * @return  boolean  True if the database was successfully selected.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function select($database)
	{
		$this->connect();

		return true;
	}

	/**
	 * Set the connection to use UTF-8 character encoding.
	 *
	 * Returns false automatically for the Oracle driver since
	 * you can only set the character set when the connection
	 * is created.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   12.1
	 */
	public function setUTF()
	{
		$this->connect();

		return false;
	}

	/**
	 * Locks a table in the database.
	 *
	 * @param   string  $table  The name of the table to unlock.
	 *
	 * @return  JDatabaseDriverSqlite  Returns this object to support chaining.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function lockTable($table)
	{
		return $this;
	}

	/**
	 * Renames a table in the database.
	 *
	 * @param   string  $oldTable  The name of the table to be renamed
	 * @param   string  $newTable  The new name for the table.
	 * @param   string  $backup    Not used by Sqlite.
	 * @param   string  $prefix    Not used by Sqlite.
	 *
	 * @return  JDatabaseDriverSqlite  Returns this object to support chaining.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function renameTable($oldTable, $newTable, $backup = null, $prefix = null)
	{
		$this->setQuery('ALTER TABLE ' . $oldTable . ' RENAME TO ' . $newTable)->execute();

		return $this;
	}

	/**
	 * Unlocks tables in the database.
	 *
	 * @return  JDatabaseDriverSqlite  Returns this object to support chaining.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function unlockTables()
	{
		return $this;
	}

	/**
	 * Test to see if the PDO ODBC connector is available.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   12.1
	 */
	public static function isSupported()
	{
		return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers());
	}

	/**
	 * Method to commit a transaction.
	 *
	 * @param   boolean  $toSavepoint  If true, commit to the last savepoint.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 * @throws  RuntimeException
	 */
	public function transactionCommit($toSavepoint = false)
	{
		$this->connect();

		if (!$toSavepoint || $this->transactionDepth <= 1)
		{
			parent::transactionCommit($toSavepoint);
		}
		else
		{
			$this->transactionDepth--;
		}
	}

	/**
	 * Method to roll back a transaction.
	 *
	 * @param   boolean  $toSavepoint  If true, rollback to the last savepoint.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 * @throws  RuntimeException
	 */
	public function transactionRollback($toSavepoint = false)
	{
		$this->connect();

		if (!$toSavepoint || $this->transactionDepth <= 1)
		{
			parent::transactionRollback($toSavepoint);
		}
		else
		{
			$savepoint = 'SP_' . ($this->transactionDepth - 1);
			$this->setQuery('ROLLBACK TO ' . $this->quoteName($savepoint));

			if ($this->execute())
			{
				$this->transactionDepth--;
			}
		}
	}

	/**
	 * Method to initialize a transaction.
	 *
	 * @param   boolean  $asSavepoint  If true and a transaction is already active, a savepoint will be created.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 * @throws  RuntimeException
	 */
	public function transactionStart($asSavepoint = false)
	{
		$this->connect();

		if (!$asSavepoint || !$this->transactionDepth)
		{
			parent::transactionStart($asSavepoint);
		}

		$savepoint = 'SP_' . $this->transactionDepth;
		$this->setQuery('SAVEPOINT ' . $this->quoteName($savepoint));

		if ($this->execute())
		{
			$this->transactionDepth++;
		}
	}
	
	/**
	 * This function return a field value as a prepared string to be used in a SQL statement.
	 *
	 * @param   array   $columns      Array of table's column returned by ::getTableColumns.
	 * @param   string  $field_name   The table field's name.
	 * @param   string  $field_value  The variable value to quote and return.
	 *
	 * @return  string  The quoted string.
	 *
	 * @since   12.1
	 */
	public function sqlValue($columns, $field_name, $field_value)
	{
	error_log("sqlValue:".$columns.":".$field_name.":".$field_value);
		switch ($columns[$field_name])
		{
			case 'date':
			case 'timestamp without time zone':
				if (empty($field_value))
				{
					$field_value = $this->getNullDate();
				}

				$val = 'date('.$this->quote($field_value).')';
				break;

			default:
				$val = $this->quote($field_value);
				break;
		}

		return $val;
	}
	
	/**
	 * Method to get the auto-incremented value from the last INSERT statement.
	 *
	 * @return  string  The value of the auto-increment field from the last inserted row.
	 *
	 * @since   12.1
	 */
	public function insertid()
	{
		$this->connect();
		error_log("INSERTID------------------------------------------------------------------------------------------------------------");

		// Error suppress this to prevent PDO warning us that the driver doesn't support this operation.
		return sqlite_last_insert_rowid();
	}
}

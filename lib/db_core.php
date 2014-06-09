<?php

define( 'OBJECT', 'OBJECT', true );
define( 'OBJECT_K', 'OBJECT_K' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

class db_core
{
	var $show_log = true;
	var $show_errors = true;
	var $suppress_errors = false;
	var $last_error = '';
	var $num_queries = 0;
	var $num_rows = 0;
	var $rows_affected = 0;
	var $insert_id = 0;
	var $last_query;
	var $last_result;
	var $col_info;
	var $queries;
	var $prefix = '';
	var $ready = false;

	var $charset;
	var $collate;
	var $real_escape = false;

	var $dbuser;
	var $dbpassword;
	var $dbname;
	var $dbhost;
	var $dbport;

	var $func_call;


	public $is_mysql = null;

	/**
	 * Connects to the database server and selects a database
	 */
	function __construct( $dbuser, $dbpassword, $dbname, $dbhost, $dbport = 3306 )
	{
		register_shutdown_function( array( &$this, '__destruct' ) );

		$this->charset = DB_CHARSET;
		$this->collate = 'utf8_general_ci';

		$this->dbuser = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;
		$this->dbport = $dbport;

		$this->db_connect();
	}


	function __destruct()
	{
		return true;
	}


	function init_charset()
	{
	}

	/**
	 * Sets the connection's character set.
	 *
	 * @since 3.1.0
	 *
	 * @param resource $dbh     The resource given by mysql_connect
	 * @param string   $charset The character set (optional)
	 * @param string   $collate The collation (optional)
	 */
	function set_charset($dbh, $charset = null, $collate = null)
	{
		mysqli_set_charset( $dbh, $charset );
	}


	/**
	 * Weak escape, using addslashes()
	 *
	 * @see addslashes()
	 * @since 2.8.0
	 * @access private
	 *
	 * @param string $string
	 * @return string
	 */
	function _weak_escape( $string )
	{
		return addslashes( $string );
	}

	/**
	 * Real escape, using mysql_real_escape_string() or addslashes()
	 *
	 * @see mysql_real_escape_string()
	 * @see addslashes()
	 * @since 2.8.0
	 * @access private
	 *
	 * @param  string $string to escape
	 * @return string escaped
	 */
	function _real_escape( $string )
	{
		if( $this->dbh && $this->real_escape )
			return mysql_real_escape_string( $string, $this->dbh );
		else
			return addslashes( $string );
	}

	/**
	 * Escape data. Works on arrays.
	 *
	 * @uses wpdb::_escape()
	 * @uses wpdb::_real_escape()
	 * @since  2.8.0
	 * @access private
	 *
	 * @param  string|array $data
	 * @return string|array escaped
	 */
	function _escape( $data )
	{
		if( is_array( $data ) )
		{
			foreach( (array) $data as $k => $v )
			{
				if( is_array($v) )
					$data[$k] = $this->_escape( $v );
				else
					$data[$k] = $this->_real_escape( $v );
			}
		}
		else
		{
			$data = $this->_real_escape( $data );
		}

		return $data;
	}

	/**
	 * Escapes content for insertion into the database using addslashes(), for security.
	 *
	 * Works on arrays.
	 *
	 * @since 0.71
	 * @param string|array $data to escape
	 * @return string|array escaped as query safe string
	 */
	function escape( $data )
	{
		if( is_array( $data ) )
		{
			foreach( (array) $data as $k => $v )
			{
				if( is_array( $v ) )
					$data[$k] = $this->escape( $v );
				else
					$data[$k] = $this->_weak_escape( $v );
			}
		}
		else
		{
			$data = $this->_weak_escape( $data );
		}

		return $data;
	}

	/**
	 * Escapes content by reference for insertion into the database, for security
	 *
	 * @uses wpdb::_real_escape()
	 * @since 2.3.0
	 * @param string $string to escape
	 * @return void
	 */
	function escape_by_ref( &$string )
	{
		$string = $this->_real_escape( $string );
	}

	/**
	 * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
	 *
	 * <code>
	 * wpdb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
	 * wpdb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
	 * </code>
	 *
	 */
	function prepare( $query = null ) { // ( $query, *$args )
		if( is_null( $query ) )
			return;

		$args = func_get_args();
		array_shift( $args );
		// If args were passed as an array (as in vsprintf), move them up
		if( isset( $args[0] ) && is_array($args[0]) )
			$args = $args[0];

		$query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
		$query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
		$query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
		array_walk( $args, array( &$this, 'escape_by_ref' ) );
		return vsprintf( $query, $args );
	}

	/**
	 * Print SQL/DB error.
	 *
	 * @since 0.71
	 * @global array $EZSQL_ERROR Stores error information of query and error string
	 *
	 * @param string $str The error to display
	 * @return bool False if the showing of errors is disabled.
	 */
	function print_error( $str = '' )
	{
		global $EZSQL_ERROR;

		if( !$str )
			$str = mysqli_error( $this->dbh );
		$EZSQL_ERROR[] = array( 'query' => $this->last_query, 'error_str' => $str );

		if( $this->suppress_errors )
			return false;

		$error_str = sprintf( ( 'database error %1$s for query %2$s' ), $str, $this->last_query );

		if( function_exists( 'error_log' ) && ( $log_file = ini_get( 'error_log' ) ) 	&& ( 'syslog' == $log_file || is_writable( $log_file ) ) )
			error_log( $error_str );

		// Are we showing errors?
		if( ! $this->show_errors )
			return false;

//		$str   = htmlspecialchars( $str, ENT_QUOTES );
//		$query = htmlspecialchars( $this->last_query, ENT_QUOTES );

		$query = $this->last_query;

		print "<pre>
		database error: [$str]
		$query
		</pre>";
	}

	/**
	 * Enables showing of database errors.
	 *
	 * This function should be used only to enable showing of errors.
	 * wpdb::hide_errors() should be used instead for hiding of errors. However,
	 * this function can be used to enable and disable showing of database
	 * errors.
	 *
	 * @since 0.71
	 * @see wpdb::hide_errors()
	 *
	 * @param bool $show Whether to show or hide errors
	 * @return bool Old value for showing errors.
	 */
	function show_errors( $show = true ) {
		$errors = $this->show_errors;
		$this->show_errors = $show;
		return $errors;
	}

	/**
	 * Disables showing of database errors.
	 *
	 * By default database errors are not shown.
	 *
	 * @since 0.71
	 * @see wpdb::show_errors()
	 *
	 * @return bool Whether showing of errors was active
	 */
	function hide_errors() {
		$show = $this->show_errors;
		$this->show_errors = false;
		return $show;
	}

	/**
	 * Whether to suppress database errors.
	 *
	 * By default database errors are suppressed, with a simple
	 * call to this function they can be enabled.
	 *
	 * @since 2.5.0
	 * @see wpdb::hide_errors()
	 * @param bool $suppress Optional. New value. Defaults to true.
	 * @return bool Old value
	 */
	function suppress_errors( $suppress = true ) {
		$errors = $this->suppress_errors;
		$this->suppress_errors = (bool) $suppress;
		return $errors;
	}

	/**
	 * Kill cached query results.
	 *
	 * @since 0.71
	 * @return void
	 */
	function flush() {
		$this->last_result = array();
		$this->col_info    = null;
		$this->last_query  = null;
	}

	/**
	 * Connect to and select database
	 *
	 * @since 3.0.0
	 */
	function db_connect()
	{
		$this->is_mysql = true;

		$this->dbh = mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $this->dbname, $this->dbport );
		if( !$this->dbh )
		{
			die("db connect error");
		}

		$this->set_charset( $this->dbh, $this->charset );
		$this->ready = true;
	}

	/**
	 * Perform a MySQL database query, using current database connection.
	 *
	 * More information can be found on the codex page.
	 *
	 * @since 0.71
	 *
	 * @param string $query Database query
	 * @return int|false Number of rows affected/selected or false on error
	 */
	function query( $query )
	{
		if( ! $this->ready )
			return false;

		// some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
//		$query = apply_filters( 'query', $query );

		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;
		if($this->show_log)
			error_log( date("Y-m-d H:i:s ")." {$query}\n", 3, LogPath.'db.log');

		$this->result = mysqli_query( $this->dbh, $query );
		$this->num_queries++;

		// If there is an error then take note of it..
		if( $this->last_error = mysqli_error( $this->dbh ) )
		{
			if($this->show_log)
				error_log( date("Y-m-d H:i:s ")." last_error:{$this->last_error}\n", 3, LogPath.'db.log');

			$this->print_error();
			return false;
		}

		if( preg_match( '/^\s*(create|alter|truncate|drop) /i', $query ) )
		{
			$return_val = $this->result;
		}
		else if( preg_match( '/^\s*(insert|delete|update|replace) /i', $query ) )
		{
			$this->rows_affected = mysqli_affected_rows( $this->dbh );
			if( preg_match( '/^\s*(insert|replace) /i', $query ) )
			{
				$this->insert_id = mysqli_insert_id($this->dbh);
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		}
		else
		{
			$i = 0;
			while ( $i < mysqli_field_count( $this->dbh ) )
			{
				$this->col_info[$i] = mysqli_fetch_field( $this->result );
				$i++;
			}
			$num_rows = 0;
			while ( $row = mysqli_fetch_object( $this->result ) )
			{
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			mysqli_free_result( $this->result );

			// Log number of rows the query returned
			// and return number of rows selected
			$this->num_rows = $num_rows;
			$return_val     = $num_rows;
		}

		return $return_val;
	}

	/**
	 * Insert a row into a table.
	 *
	 * <code>
	 * wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
	 * </code>
	 *
	 */
	function insert( $table, $data, $format = null )
	{
		return $this->_insert_replace_helper( $table, $data, $format, 'INSERT' );
	}

	function replace( $table, $data, $format = null )
	{
		return $this->_insert_replace_helper( $table, $data, $format, 'REPLACE' );
	}

	function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' )
	{
		if( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) )
			return false;

		$formats = $format = (array) $format;
		$fields = array_keys( $data );
		$formatted_fields = array();

		foreach( $fields as $field )
		{
			if( !empty( $format ) )
				$form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
			else if( isset( $this->field_types[$field] ) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$formatted_fields[] = $form;
		}
		$sql = "{$type} INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES (" . implode( ",", $formatted_fields ) . ")";
		return $this->query( $this->prepare( $sql, $data ) );
	}

	/**
	 * Update a row in the table
	 *
	 * <code>
	 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
	 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
	 * </code>
	 *
   */

	function update( $table, $data, $where, $format = null, $where_format = null )
	{
		if( ! is_array( $data ) || ! is_array( $where ) )
			return false;

		$formats = $format = (array) $format;
		$bits = $wheres = array();
		foreach( (array) array_keys( $data ) as $field )
		{
			if( !empty( $format ) )
				$form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
			else if( isset($this->field_types[$field]) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$bits[] = "`$field` = {$form}";
		}

		$where_formats = $where_format = (array) $where_format;
		foreach( (array) array_keys( $where ) as $field )
		{
			if( !empty( $where_format ) )
				$form = ( $form = array_shift( $where_formats ) ) ? $form : $where_format[0];
			else if( isset( $this->field_types[$field] ) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$wheres[] = "`$field` = {$form}";
		}

		$sql = "UPDATE `$table` SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres );
		return $this->query( $this->prepare( $sql, array_merge( array_values( $data ), array_values( $where ) ) ) );
	}

	/**
	 * Delete a row in the table
	 *
	 * <code>
	 * wpdb::delete( 'table', array( 'ID' => 1 ) )
	 * wpdb::delete( 'table', array( 'ID' => 1 ), array( '%d' ) )
	 * </code>
	 *
	 */
	function delete( $table, $where, $where_format = null )
	{
		if( !is_array( $where ) )
			return false;

		$bits = $wheres = array();

		$where_formats = $where_format = (array) $where_format;

		foreach( array_keys( $where ) as $field )
		{
			if( !empty( $where_format ) )
			{
				$form = ( $form = array_shift( $where_formats ) ) ? $form : $where_format[0];
			}
			else if( isset( $this->field_types[ $field ] ) )
			{
				$form = $this->field_types[ $field ];
			}
			else
			{
				$form = '%s';
			}

			$wheres[] = "$field = $form";
		}

		$sql = "DELETE FROM $table WHERE " . implode( ' AND ', $wheres );
		return $this->query( $this->prepare( $sql, $where ) );
	}


	/**
	 * Retrieve one variable from the database.
	 *
	 * Executes a SQL query and returns the value from the SQL result.
	 * If the SQL result contains more than one column and/or more than one row, this function returns the value in the column and row specified.
	 * If $query is null, this function returns the value in the specified column and row from the previous SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
	 * @param int $x Optional. Column of value to return. Indexed from 0.
	 * @param int $y Optional. Row of value to return. Indexed from 0.
	 * @return string|null Database query result (as string), or null on failure
	 */
	function get_var( $query = null, $x = 0, $y = 0 )
	{
		$this->func_call = "\$db->get_var(\"$query\", $x, $y)";
		if( $query )
			$this->query( $query );

		// Extract var out of cached results based x,y vals
		if( !empty( $this->last_result[$y] ) )
		{
			$values = array_values( get_object_vars( $this->last_result[$y] ) );
		}

		// If there is a value return it else return null
		return ( isset( $values[$x] ) && $values[$x] !== '' ) ? $values[$x] : null;
	}


	function get_row( $query = null, $output = OBJECT, $y = 0 )
	{
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if( $query )
			$this->query( $query );
		else
			return null;

		if( !isset( $this->last_result[$y] ) )
			return null;

		if( $output == OBJECT )
		{
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		}
		else if( $output == ARRAY_A )
		{
			return $this->last_result[$y] ? get_object_vars( $this->last_result[$y] ) : null;
		}
		else if( $output == ARRAY_N )
		{
			return $this->last_result[$y] ? array_values( get_object_vars( $this->last_result[$y] ) ) : null;
		}
		else {
			$this->print_error( " \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N" );
		}
	}

	/**
	 * Retrieve one column from the database.
	 *
	 * Executes a SQL query and returns the column from the SQL result.
	 * If the SQL result contains more than one column, this function returns the column specified.
	 * If $query is null, this function returns the specified column from the previous SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Optional. SQL query. Defaults to previous query.
	 * @param int $x Optional. Column to return. Indexed from 0.
	 * @return array Database query result. Array indexed from 0 by SQL result row number.
	 */
	function get_col( $query = null , $x = 0 )
	{
		if( $query )
			$this->query( $query );

		$new_array = array();
		// Extract the column values
		for ( $i = 0, $j = count( $this->last_result ); $i < $j; $i++ )
		{
			$new_array[$i] = $this->get_var( null, $x, $i );
		}
		return $new_array;
	}

	/**
	 * Retrieve an entire SQL result set from the database (i.e., many rows)
	 *
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string $query SQL query.
	 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. With one of the first three, return an array of rows indexed from 0 by SQL result row number.
	 * 	Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
	 * 	With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value. Duplicate keys are discarded.
	 * @return mixed Database query results
	 */
	function get_results( $query = null, $output = OBJECT )
	{
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if( $query )
			$this->query( $query );
		else
			return null;

		$new_array = array();
		if( $output == OBJECT )
		{
			// Return an integer-keyed array of row objects
			return $this->last_result;
		}
		else if( $output == OBJECT_K )
		{
			foreach( $this->last_result as $row )
			{
				$var_by_ref = get_object_vars( $row );
				$key = array_shift( $var_by_ref );

				if( !isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
			}
			return $new_array;
		}
		else if( $output == ARRAY_A || $output == ARRAY_N )
		{
			// Return an integer-keyed array of...
			if( $this->last_result )
			{
				foreach( (array) $this->last_result as $row )
				{
					if( $output == ARRAY_N )
					{
						$new_array[] = array_values( get_object_vars( $row ) );
					}
					else
					{
						$new_array[] = get_object_vars( $row );
					}
				}
			}
			return $new_array;
		}
		return null;
	}

	/**
	 * Retrieve column metadata from the last query.
	 *
	 * @since 0.71
	 *
	 * @param string $info_type Optional. Type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
	 * @param int $col_offset Optional. 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
	 * @return mixed Column Results
	 */
	function get_col_info( $info_type = 'name', $col_offset = -1 )
	{
		if( $this->col_info )
		{
			if( $col_offset == -1 )
			{
				$i = 0;
				$new_array = array();
				foreach( (array) $this->col_info as $col )
				{
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			}
			else
			{
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}




	/**
	 * The database version number.
	 *
	 * @since 2.7.0
	 *
	 * @return false|string false on failure, version number on success
	 */
	function db_version() {
		return preg_replace( '/[^0-9.].*/', '', mysql_get_server_info( $this->dbh ) );
	}

	function beginTransaction() {
		mysqli_query($this->dbh, "START TRANSACTION");
	}

	function commit() {
		mysqli_query($this->dbh, "COMMIT");
	}

	function rollBack() {
		mysqli_query($this->dbh, "ROLLBACK");
	}

}

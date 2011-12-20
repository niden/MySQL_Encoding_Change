<?php
	/**
     * Written by nikos@niden.net - http://www.niden.net
     */

	$db_user       = 'username';
	$db_password   = 'password';
	$db_host       = 'hostname';
	$output_folder = '/home/ndimopoulos'; // Do not include trailing slash
	$db_name       = 'mysql'; // Leave this one as is
	
	set_time_limit(0);
	
	/**
	 * The old collation (what needs to be changed)
	 */
	$from_encoding = 'latin1_swedish_ci';
	
	/**
	 * The new collation (what we will change it to)
	 */
	$to_encoding = 'utf8_general_ci';
	
	/**
	 * The new character set
	 */
	$new_collation = 'utf8';
	
	/**
	 * Add USE <database> before each statement?
	 */
	$use_database = TRUE;
	
	mysql_connect($db_host, $db_user, $db_password);
	mysql_select_db($db_name);
	
	$dbs = array();
	
	$exclude_databases     = array('mysql', 'information_schema',);
	$exclude_tables        = array('logs', 'logs_archived',);
	$exclude_tables_fields = array('activities');
	
	/**
	 * Get the databases available (ignore information_schema and mysql)
	 */
	$result = mysql_query("SHOW DATABASES");
	
	while ($row = mysql_fetch_row($result)) 
	{
		if (!in_array($row[0], $exclude_databases))
		{
			$dbs[] = $row[0];
		}
	}
	
	$output = '';
	
	/**
	 * Now select each db and start parsing the tables
	 */
	foreach ($dbs as $db)
	{
		mysql_select_db($db);
		$db_output = '';
		
		$statement  = "\r\n#--------------------------------------------------------------------------\r\n\r\n";
		$statement .= "USE $db;\r\n";
		$statement .= "\r\n#--------------------------------------------------------------------------\r\n\r\n";
		$statement .= "ALTER DATABASE $db CHARACTER SET $new_collation COLLATE $to_encoding;\r\n";
		$statement .= "\r\n#--------------------------------------------------------------------------\r\n\r\n";
		
		$db_output .= $statement;
		$output    .= $statement;
		$tables     = array();
		
		$result = mysql_query("SHOW TABLES");
		
		while ($row = mysql_fetch_row($result))
		{
			if (!in_array($row[0], $exclude_tables))
			{
				$tables[] = mysql_real_escape_string($row[0]);
			}
		}
		
		/**
		 * Alter statements for the tables
		 */
		foreach ($tables as $table)
		{
			$statement = '';
			if ($use_database)
			{
				$statement  = "USE $db; ";
			}
			$statement .= "ALTER TABLE `$table` DEFAULT CHARACTER SET $new_collation;\r\n";
			$db_output .= $statement;
			$output    .= $db_output;
		}
		$statement  = "\r\n#--------------------------------------------------------------------------\r\n\r\n";
		$db_output .= $statement;
		$output    .= $statement;
		
		/**
		 * Get the fields for each table
		 */
		foreach ($tables as $table)
		{
			if (in_array($table, $exclude_tables_fields))
			{
				continue;
			} 
			
			$fields_modify = array();
			$fields_change = array();
			
			$result = mysql_query("SHOW FULL FIELDS FROM `$table`");
			while ($row = mysql_fetch_assoc($result)) 
			{
				if ($row['Collation'] != $from_encoding)
				{
					continue;
				}
				
				// Is the field allowed to be null?
				$nullable = ($row['Null'] == 'YES') ? ' NULL ' : ' NOT NULL';
				
				if ($row['Default'] == 'NULL') 
				{
					$default = " DEFAULT NULL";
				} 
				else if ($row['Default']!='') 
				{
					$default = " DEFAULT '" . mysql_real_escape_string($row['Default']) . "'";
				} 
				else 
				{
					$default = '';
				}
				
				// Alter field collation:
				$field_name = mysql_real_escape_string($row['Field']);
				
				$fields_modify[] = "MODIFY `$field_name` $row['Type'] CHARACTER SET BINARY";
				$fields_change[] = "CHANGE `$field_name` `$field_name` $row['Type'] "
								 . "CHARACTER SET $new_collation "
								 . "COLLATE $to_encoding $nullable $default";
			}
			
			if (count($fields_modify) > 0)
			{
				$statement = '';
				if ($use_database)
				{
					$statement = "USE $db; ";
				}
				$statement .= "ALTER TABLE `$table` " . implode(' , ', $fields_modify) . "; \r\n";
				if ($use_database)
				{
					$statement = "USE $db; ";
				}
				$statement .= "ALTER TABLE `$table` " . implode(' , ', $fields_change) . "; \r\n";
				
				$db_output .= $statement;
				$output    .= $statement;
			}
		}
		
		$bytes = file_put_contents($output_folder . '/' . $db_host . '.' . $db . '.sql', $db_output);
	}
	
	$bytes = file_put_contents($output_folder . '/' . $db_host . '.sql', $output);
	
	echo "<pre>$db_host $bytes \r\n$output</pre>";
	

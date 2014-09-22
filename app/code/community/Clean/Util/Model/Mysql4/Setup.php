<?php

class Clean_Util_Model_Mysql4_Setup extends Mage_Eav_Model_Entity_Setup
{
    public function addColumns($tableName, $columns, $additional = "")
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }

        foreach ($columns as $column) {
            $sql = "ALTER TABLE `{$tableName}` ADD COLUMN {$column} {$additional};";
            $this->run($sql);
        }

        return $this;
    }

    public function endSetup()
    {
        $this->clearCache();
        return parent::endSetup();
    }

    public function clearCache()
    {
        Mage::app()->getCacheInstance()->flush();

        return $this;
    }

    public function tryRunning($sql)
    {
        try {
            $this->run($sql);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function createTable($tableName, $primaryKey, $columns, $indices = array(), $comment = "")
    {
        if (is_array($primaryKey)) {
            // Standard usage defines the PK as an array with key = column-name and value = column-definition
            $pkName = key($primaryKey);
            $pkDefinition = $primaryKey[$pkName];
        } else if (is_string($primaryKey)) {
            // However we also support just passing the whole column definition in as string, rather than array
            $pkName = 0;
            $pkDefinition = $primaryKey;
        } else {
            throw new Exception("\$primaryKey parameter must be an array or string.  Found: '" . gettype($primaryKey) . "'");
        }

        if (is_numeric($pkName)) {
            // If no column name was specified, attempt to extract it from the definition string
            list($pkName, $pkDefinition) = explode(' ', $pkDefinition, 2);
            $pkName = trim($pkName, '`');
        }

        // Define the column that will be our primary key
        $createDefinition = array("`{$pkName}` {$pkDefinition}");

        // Define the rest of the columns
        foreach ($columns as $key => $definition) {
            // Column can be an array with key = column-name and value = column-definition, or just a plain string
            if (!is_numeric($key)) {
                $definition = "`{$key}` {$definition}";
            }
            $createDefinition[] = $definition;
        }

        // Add the PK!
        $createDefinition[] = "PRIMARY KEY (`{$pkName}`)";

        // Add index definitions if any were specified
        foreach ($indices as $name => $columns) {
            // Supports composite indices
            if (!is_array($columns)) {
                $columns = array($columns);
            }

            // Index name can be specified as array key, but doesn't have to be; we'll figure one out.
            if (is_numeric($name)) {
                $name = $this->_generateIndexName('INDEX', $tableName, $columns);
            }

            // Add the index!
            $createDefinition[] = "INDEX `{$name}` (`" . implode('`, `', $columns) . "`)";
        }

        $createDefinition = implode(",", $createDefinition);

        // Finally, create the table.  Unless it already exists (scripts must be getting re-ran).
        $this->tryRunning("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                {$createDefinition}
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='{$comment}';
        ");

        return $this;
    }

    protected function _generateIndexName($indexType, $tableName, $fields)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        $indexType = strtoupper($indexType);
        $indexName = '';

        switch ($indexType) {
            case 'PRIMARY':
                $indexName = 'PK__';
                break;
            case 'FOREIGN':
                $indexName = 'FK__';
                break;
            case 'INDEX':
                $indexName = 'IX__';
                break;
            case 'UNIQUE':
                $indexName = 'UQ__';
                break;
            default:
                $indexName = 'IX__';
                break;
        }

        $indexName .= $tableName . '__' . implode('__', $fields);

        return $indexName;
    }
}

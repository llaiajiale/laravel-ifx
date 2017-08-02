<?php
/**
 * Created by PhpStorm.
 * User: llaijiale
 * Date: 2016/1/20
 * Time: 14:38
 */
namespace Poyii\Informix\Schema\Grammars;

use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

class IfxGrammar extends Grammar
{

    /**
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = ['Increment', 'Nullable', 'Default', 'Before'];

    /**
     * The possible column serials.
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    public function compileTableExists()
    {
        return 'select * from systables where tabname=lower(?)';
    }


    public function compileColumnExists($table)
    {
        return 'select b.colname from systables a join syscolumns b on a.tabid=b.tabid where a.tabname=lower(?)';
    }


    protected function addPrimaryKeys(Blueprint $blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');
        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);
            return ", primary key ( {$columns} ) constraint {$primary->index}";
        }
    }

    protected function addForeignKeys(Blueprint $blueprint)
    {
        $sql = '';

        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        // Once we have all the foreign key commands for the table creation statement
        // we'll loop through each of them and add them to the create table SQL we
        // are building
        foreach ($foreigns as $foreign) {
            $on = $this->wrapTable($foreign->on);

            $columns = $this->columnize($foreign->columns);

            $onColumns = $this->columnize((array) $foreign->references);

            $sql .= ", foreign key ( {$columns} ) references {$on} ( {$onColumns} ) constraint {$foreign->index}";

            // Once we have the basic foreign key creation statement constructed we can
            // build out the syntax for what should happen on an update or delete of
            // the affected columns, which will get something like "cascade", etc.
            if (! is_null($foreign->onDelete)) {
                $sql .= " on delete {$foreign->onDelete}";
            }
        }

        return $sql;
    }

    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = $blueprint->temporary ? "create temp" : 'create';

        $sql .= ' table '.$this->wrapTable($blueprint)." ( $columns";

        // To be able to name the primary/foreign keys when the table is
        // initially created we will need to check for a primary/foreign
        // key commands and add the columns to the table's declaration
        // here so they can be created on the tables.

        $sql .= (string) $this->addForeignKeys($blueprint);

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        $sql .= ' )';

        if(isset($blueprint->engine)){
            if(is_string($blueprint->engine))
                $sql.=$blueprint->engine;
            else if(is_array($blueprint->engine)){
                if($blueprint->engine['extent'] > 32)
                    $sql.=" extent size ".(int)$blueprint->engine['extent'];
                if($blueprint->engine['next'] > 32)
                    $sql.=" next size ".(int)$blueprint->engine['next'];
            }
        }

        return $sql;
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'alter table '.$this->wrapTable($blueprint)." add ( $columns";

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        return $sql .= ' )';
    }


    /**
     * Compile a primary key command.
     *
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $columns = $this->columnize($command->columns);

            $table = $this->wrapTable($blueprint);

            return "alter table {$table} add constraint primary key ({$columns}) constraint {$command->index}";
        }
    }

    /**
     * Compile a foreign key command.
     *
     * @return string
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $table = $this->wrapTable($blueprint);

            $on = $this->wrapTable($command->on);

            // We need to prepare several of the elements of the foreign key definition
            // before we can create the SQL, such as wrapping the tables and convert
            // an array of columns to comma-delimited strings for the SQL queries.
            $columns = $this->columnize($command->columns);

            $onColumns = $this->columnize((array) $command->references);

            $sql = "alter table {$table} add constraint foreign key ( {$columns} ) references {$on} ( {$onColumns} )";

            // Once we have the basic foreign key creation statement constructed we can
            // build out the syntax for what should happen on an update or delete of
            // the affected columns, which will get something like "cascade", etc.
            if (! is_null($command->onDelete)) {
                $sql .= " on delete {$command->onDelete}";
            }
            $sql .=" constraint {$command->index}";

            return $sql;
        }
    }

    /**
     * Compile a unique key command.
     *
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->columnize($command->columns);

        $table = $this->wrapTable($blueprint);

        return "alter table {$table} add constraint unique ( {$columns} ) constraint {$command->index}";
    }

    /**
     * Compile a plain index key command.
     *
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->columnize($command->columns);

        $table = $this->wrapTable($blueprint);

        return "create index {$command->index} on {$table} ( {$columns} )";
    }

    /**
     * Compile a drop table command.
     *
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop column command.
     *
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->wrapArray($command->columns);

        $table = $this->wrapTable($blueprint);

        return 'alter table '.$table.' drop ( '.implode(', ', $columns).' )';
    }

    /**
     * Compile a drop primary key command.
     *
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        return "alter table {$table} drop constraint {$command->index}";
    }

    /**
     * Compile a drop unique key command.
     *
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        return "alter table {$table} drop constraint {$command->index}";
    }

    /**
     * Compile a drop index command.
     *
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return "drop index {$command->index}";
    }

    /**
     * Compile a drop foreign key command.
     *
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        return "alter table {$table} drop constraint {$command->index}";
    }

    /**
     * Compile a rename table command.
     *
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        return "rename table {$table} to ".$this->wrapTable($command->to);
    }

    /**
     * Compile a rename column command.
     *
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $table = $this->wrapTable($blueprint);
        $rs = ["rename column {$table}.{$command->from} to {$command->to}"];
        return $rs;
    }


    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }
        return $value;
    }

    protected function typeChar(Fluent $column) {
        if($column->length < 256){
            return 'char('.(int)$column->length.')';
        }
        return 'char(255)';
    }
    /**
     * Create the column definition for a string type.
     *
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        if($column->length < 256)
            return "varchar({$column->length})";
        else if($column->length < 32740)
            return "lvarchar({$column->length})";
        return "lvarchar(32739)";
    }

    /**
     * Create the column definition for a long text type.
     *
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a text type.
     *
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a integer type.
     *
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        if($column->autoIncrement){
            return 'serial8(1)';
        }
        return 'int8';
    }

    /**
     * Create the column definition for a integer type.
     *
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        if($column->autoIncrement){
            return 'serial(1)';
        }
        return 'int';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }
    /**
     * Create the column definition for a tiny integer type.
     *
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a float type.
     *
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a double type.
     *
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }


    /**
     * Create the column definition for a boolean type.
     *
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'char(1)';
    }

    /**
     * Create the column definition for a enum type.
     *
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return 'varchar(255)';
    }

    /**
     * Create the column definition for a date type.
     *
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'datetime year to second';
    }

    /**
     * Create the column definition for a time type.
     *
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'datetime hour to second';
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        return 'datetime year to second default current year to second';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'byte';
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        $null = $column->nullable ? ' ' : ' not null';
        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default).$null;
        }

        return $null;
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            $blueprint->primary($column->name);
        }
    }

    /**
     *
     * @return string|null
     */
    protected function modifyBefore(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->before)) {
            return ' before '.$this->wrap($column->before);
        }
    }

    protected function getDefaultValue($value)
    {
        if ($value instanceof Expression) {
            return $value;
        }

        if (is_bool($value)) {
            return "'".(int) $value."'";
        }

        if (is_string($value)) {
            return "'".strval($value)."'";
        }

        return strval($value);
    }


}
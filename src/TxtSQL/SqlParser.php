<?php


namespace TxtSQL;


class SqlParser extends WordParser
{
    /**
     * Whitespace characters that should be ignored
     *
     * @var string
     */
    protected $whitespace = " \t\n\r\x0B";

    /**
     * Does the actual parsing of the SQL string
     *
     * @return array|bool $arguments The arguments as a result of the parsing
     */
    public function parse()
    {
        /* Get the action */
        $action = $this->getNextWord(true);
        $arguments = [];
        $whitespace = " \t\n\r\x0B";

        /* Parse the right query */
        switch (strtolower($action)) {
            case 'select' :
                {
                    $this->parseSelect($arguments);

                    break;
                }

            case 'insert' :
                {
                    $this->parseInsert($arguments);

                    break;
                }

            case 'show' :
                {
                    $this->parseShow($arguments);

                    break;
                }

            case 'create' :
                {
                    $this->parseCreate($arguments);

                    break;
                }

            case 'drop' :
                {
                    $this->parseDrop($arguments);

                    break;
                }

            case 'describe' :
                {
                    $this->parseDescribe($arguments);

                    break;
                }

            case 'delete' :
                {
                    $this->parseDelete($arguments);

                    break;
                }

            case 'update' :
                {
                    $this->parseUpdate($arguments);

                    break;
                }

            case 'lock' :
                {
                    $this->parseLock($arguments);

                    break;
                }

            case 'unlock' :
                {
                    $this->parseUnlock($arguments);

                    break;
                }

            case 'is' :
                {
                    $this->parseIsLocked($arguments);

                    break;
                }

            case 'use' :
                {
                    $this->parseUse($arguments);

                    break;
                }

            case 'grant' :
                {
                    $this->parseGrant($arguments);

                    break;
                }

            default :
                {
                    /* Syntax error */
                    if ($action == '') {
                        $this->throwSyntaxError($arguments);
                    } /* Invalid action */
                    else {
                        return TxtSQL::_error(
                            E_USER_ERROR,
                            'Action not supported: `'.$action.'`'
                        );
                    }
                }
        }

        /* Return the arguments */
        if ($arguments !== false) {
            return $arguments;
        }

        return false;
    }

    /**
     * Parses a FROM clause
     *
     * @param mixed $arguments The arguments thus far in the parsing
     *
     * @return bool
     */
    private function parseFrom(&$arguments)
    {
        /* Grab the table name */
        $arguments['table'] = $this->getNextWord();

        /* Filter out the table and database name if the database name exists */
        if (strpos($arguments['table'], '.')) {
            list($arguments['db'], $arguments['table']) = explode(
                '.',
                $arguments['table']
            );
        }

        return true;
    }

    /**
     * Parses a WHERE clause
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return bool
     */
    public function parseWhere(&$arguments)
    {
        /* Initiate some variables */
        $clause = '';
        $Where = [];

        /* Go through each word of the query until we need to stop */
        while ($word = $this->getNextWord(true)) {

            if (strtolower(substr($word, 0, 3)) == 'set') {
                $this->characterIndex -= strlen($word) + 1;
                break;
            }

            switch (strtolower($word)) {
                /* Back up if we find these keywords */
                case 'from' :
                    {
                        $this->characterIndex -= 5;

                        break 2;
                    }

                case 'limit' :
                    {
                        $this->characterIndex -= 6;

                        break 2;
                    }

                case 'orderby' :
                    {
                        $this->characterIndex -= 8;

                        break 2;
                    }

                /* Look for logical operators */
                case 'and' :
                case 'or' :
                case 'xor' :
                    {
                        $Where[] = $clause;
                        $Where[] = $word;
                        $clause = '';

                        break;
                    }

                /* Append the current value to the clause */
                default :
                    {
                        $this->characterIndex -= strlen($word) + 1;
                        $clause .= $this->getNextWord(true).' ';
                    }
            }
        }

        /* Add the the last element onto the $Where array */
        $Where[] = substr($clause, 0, strlen($clause) - 1);
        $arguments['where'] = $Where;

        foreach ($arguments['where'] as $key => $value) {
            if ($key <= count($arguments['where']) - 1) {
                $arguments['where'][$key] = rtrim($value);
            }
        }

        return true;
    }

    /**
     * Parses an ORDERBY clause
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return bool
     */
    protected function parseOrderBy(&$arguments)
    {
        /* Inititate some variables */
        $orderby = [];

        /* Get all of the column */
        while ($word = $this->getNextWord(true, " \t\r\n,")) {
            /* First check if they are keywords */
            switch (strtolower($word)) {
                /* Back up if we find these keywords */
                case 'from' :
                    {
                        $this->characterIndex -= 5;

                        break 2;
                    }

                case 'limit' :
                    {
                        $this->characterIndex -= 6;

                        break 2;
                    }

                case 'orderby' :
                    {
                        $this->characterIndex -= 8;

                        break 2;
                    }

                /* Remove the quotes from the column name and grab the sort direction */
                default :
                    {
                        $parser = new WordParser($word);
                        $column = $parser->getNextWord(false, " \t\r\n,");
                        $orderby[$column] = $this->getNextWord(
                            false,
                            " \t\r\n,"
                        );
                    }
            }
        }

        $arguments['orderby'] = $orderby;

        return true;
    }

    /**
     * Parses a LIMIT clause
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return bool
     */
    private function parseLimit(&$arguments)
    {
        /* Get the starting offset, and the stopping offset */
        $limit = [];
        $limit[] = $this->getNextWord(false, " \t\r\n,");
        $final = $this->getNextWord(true, " \t\r\n,");

        if ($final != "") {
            if ($final == "''") {
                $final = '';
            }

            $limit[] = $final;
        }

        $arguments['limit'] = (count($limit) == 1) ? [0, $limit[0]] : $limit;

        return true;
    }

    /**
     * Parses a set of values which are used in INSERT and UPDATE queries
     *
     * @param mixed $arguments The arguments thus far in the parsing
     *

     */
    private function getValueSet(&$arguments)
    {
        /* Get all the values */
        $this->characterIndex--;
        $word = $this->getNextWord(true);

        switch (true) {
            /* statement is in the form "(col, col, ...) VALUES ( value, value, ... )" */
            case (substr($word, 0, 1) == '(') :
                {
                    /* Create a parser for the column names */
                    $word = substr($word, 1, strlen($word) - 2);
                    $column_parser = new WordParser($word);

                    /* get column names */
                    while ($column = $column_parser->getNextWord(
                        false,
                        $this->whitespace.","
                    )) {
                        $columns[] = $column;
                    }

                    /* Get values */
                    if (substr(
                            $word = strtolower($this->getNextWord(true)),
                            0,
                            6
                        ) == 'values') {
                        while ($word = $this->getNextWord(
                            true,
                            $this->whitespace.","
                        )) {
                            /* Create a new parser for the values */
                            $values = [];
                            $word = substr($word, 1, strlen($word) - 2);
                            $word = str_replace('\\', '\\\\', $word);
                            $values_parser = new WordParser($word);

                            while ($value = $values_parser->getNextWord(
                                true,
                                $this->whitespace.","
                            )) {
                                $values[] = $value;
                            }

                            foreach ($columns as $key => $value) {
                                $arguments['values'][$value]
                                    = isset($values[$key]) ? $values[$key] : 0;
                            }

                            /* Return whatever results we got back */

                            return $arguments;
                        }
                    } else {
                        $this->throwSyntaxError($arguments);
                    }

                    break;
                }

            /* Statement is in the form "SET [col = value...] */
            case (strtolower(substr($word, 0, 3)) == 'set') :
                {
                    if (strtolower($word) == 'set') {
                        $word = $this->getNextWord(true, $this->whitespace);
                    }

                    /* Seperate all of the column/value pairs */
                    $word = substr($word, strpos($word, '(') + 1);
                    $word = substr($word, 0, strrpos($word, ')'));
                    $word = str_replace('\\', '\\\\', $word);
                    $set_parser = new WordParser($word);

                    while ($column = $set_parser->getNextWord(
                        true,
                        $this->whitespace.",="
                    )) {
                        $value = $set_parser->getNextWord(
                            true,
                            $this->whitespace.",="
                        );
                        $arguments['values'][$column] = $value;
                    }

                    break;
                }

            /* Syntax error */
            default :
                {
                    $this->throwSyntaxError($arguments);

                    break;
                }
        }

    }

    /**
     * Parses a SELECT query and returns the proper arguments
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseSelect(&$arguments)
    {
        /* Initialize some variables */
        $arguments = ['action' => 'select'];

        /* Look for the main keywords */
        while ($word = $this->getNextWord(true)) {
            switch (strtolower($word)) {
                case 'distinct' :
                    {
                        $arguments['distinct'] = $this->getNextWord(true);

                        break;
                    }

                case 'from' :
                    {
                        $this->parseFrom($arguments);

                        break;
                    }

                case 'where' :
                    {
                        $this->parseWhere($arguments);

                        break;
                    }

                case 'orderby' :
                    {
                        $this->parseOrderBy($arguments);

                        break;
                    }

                case 'limit' :
                    {
                        $this->parseLimit($arguments);

                        break;
                    }

                default :
                    {
                        if (empty($arguments['table'])) {
                            $this->characterIndex -= strlen($word) + 1;
                            $column = $this->getNextWord(
                                true,
                                $this->whitespace.","
                            );
                            $arguments['select'][] = $column;

                            if (strtolower(
                                    $nextword = $this->getNextWord(
                                        true,
                                        $this->whitespace.','
                                    )
                                ) == 'as') {
                                $arguments['aliases'][$column]
                                    = $this->getNextWord(
                                    true,
                                    $this->whitespace.','
                                );
                                break;
                            }

                            $this->characterIndex -= strlen($nextword) + 1;
                        }
                    }
            }
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses an INSERT query and returns the proper arguments
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseInsert(&$arguments)
    {
        /* Look for a syntax error */
        if (strtolower($this->getNextWord(true)) != 'into') {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Get the table name */
        $arguments['action'] = 'insert';
        $arguments['table'] = $this->getNextWord();

        /* Syntax Error */
        if ($arguments['table'] == '') {
            $this->throwSyntaxError($arguments);

            return false;
        } elseif (strpos($arguments['table'], '.')) {
            /* Check for a valid table and database name */
            $tableDB = explode('.', $arguments['table']);

            list($arguments['db'], $arguments['table']) = $tableDB;
        }

        /* Get the values */
        $this->getValueSet($arguments);

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses an SHOW {DATABASES|TABLES|USERS} query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseShow(&$arguments)
    {
        /* Get the next word so we know what to search for */
        $show = $this->getNextWord(true);
        $arguments = [];

        switch (strtolower($show)) {
            /* Show databases */
            case 'databases' :
                {
                    $arguments['action'] = 'show databases';

                    break;
                }

            /* Show all users */
            case 'users' :
                {
                    $arguments['action'] = 'show users';

                    break;
                }

            /* Show tables [ in a database ] */
            case 'tables' :
                {
                    $arguments['action'] = 'show tables';

                    /* See if we have to look in a certain database */
                    if (($word = $this->getNextWord(true)) != '') {
                        /* Grab the database name */
                        if (strtolower($word) == 'in') {
                            $arguments['db'] = $this->getNextWord(false);
                        } /* Syntax error here */
                        else {
                            $this->throwSyntaxError($arguments);

                            return false;
                        }
                    }

                    break;
                }

            /* Something went wrong */
            default :
                {
                    /* Syntax Error or incorrect action */
                    //@todo
                    if ($drop == '') {
                        $this->throwSyntaxError($arguments);
                    } else {
                        TxtSQL::_error(
                            E_USER_NOTICE,
                            'Action not supported: `DROP '.$drop.'`'
                        );
                    }

                    return false;
                }
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses a DROP {DATABASE|TABLE} query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseDrop(&$arguments)
    {
        /* Get the next word to find out whether to drop a table or a database */
        $drop = $this->getNextWord(true);

        switch (strtolower($drop)) {
            /* Drop a database */
            case 'database' :
                {
                    /* Grab database name */
                    $arguments['action'] = 'drop database';
                    $arguments['db'] = $this->getNextWord();

                    break;
                }

            /* Drop a table */
            case 'table' :
                {
                    /* Get table name and possible database name */
                    $arguments['action'] = 'drop table';
                    $arguments['table'] = $this->getNextWord();

                    if (strpos($arguments['table'], '.')) {
                        $tableDB = explode('.', $arguments['table']);

                        list($arguments['db'], $arguments['table']) = $tableDB;
                    }

                    /* Check for a valid table */
                    if ($arguments['table'] == '') {
                        $this->throwSyntaxError($arguments);

                        return false;
                    }

                    break;
                }

            /* Something went wrong */
            default:
                {
                    /* Syntax Error or incorrect action */
                    if ($drop == '') {
                        $this->throwSyntaxError($arguments);
                    } else {
                        TxtSQL::_error(
                            E_USER_NOTICE,
                            'Action not supported: `DROP '.$drop.'`'
                        );
                    }

                    return false;
                }
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses a DESCRIBE query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseDescribe(&$arguments)
    {
        /* Get the table name */
        $arguments['action'] = 'describe';
        $arguments['table'] = $this->getNextWord();

        /* Syntax error */
        if ($arguments['table'] == '') {
            $this->throwSyntaxError($arguments);

            return false;
        } elseif (strpos($arguments['table'], '.')) {
            /* Check for a database name */
            $tableDB = explode('.', $arguments['table']);

            list($arguments['db'], $arguments['table']) = $tableDB;
        }


        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses a DELETE query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseDelete(&$arguments)
    {
        /* Set the action*/
        $arguments['action'] = 'delete';

        /* Look for the main keywords */
        while ($word = $this->getNextWord(true)) {
            switch (strtolower($word)) {
                case 'from' :
                    {
                        $this->parseFrom($arguments);

                        break;
                    }

                case 'where' :
                    {
                        $this->parseWhere($arguments);

                        break;
                    }

                case 'orderby' :
                    {
                        $this->parseOrderby($arguments);

                        break;
                    }

                case 'limit' :
                    {
                        $this->parseLimit($arguments);

                        break;
                    }
            }
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses a CREATE {TABLE|DATABASE} query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseCreate(&$arguments)
    {
        /* Grab the next keyword */
        $create = $this->getNextWord(true);

        /* Match the keyword */
        switch (strtolower($create)) {
            /* Create a database */
            case 'database' :
                {
                    $arguments['action'] = 'create database';
                    $arguments['db'] = $this->getNextWord();

                    break;
                }

            /* Create a table */
            case 'table' :
                {
                    $arguments['action'] = 'create table';
                    $arguments['table'] = $this->getNextWord();

                    /* Check whether there is a database specified, and extract it */
                    if (empty($arguments['table'])) {
                        $this->throwSyntaxError($arguments);

                        return false;
                    }
                    if (strpos($arguments['table'], '.')) {
                        $tableDB = explode('.', $arguments['table']);

                        list($arguments['db'], $arguments['table']) = $tableDB;
                    }

                    /* Grab each column name and their respective properties */
                    if (substr($word = $this->getNextWord(), 0, 1) == '(') {
                        /* Create a new parser for the columns */
                        $word = substr($word, 1, strlen($word) - 2);
                        $parser = new WordParser($word);

                        /* Grab the properties of each column */
                        while ($column = $parser->getNextWord(
                            true,
                            ",",
                            true
                        )) {
                            /* Create a new parser for the properties */
                            $column_parser = new WordParser($column);
                            $name = $column_parser->getNextWord(false);

                            if (!empty($name)
                                && !isset($arguments['columns'][$name])) {
                                $arguments['columns'][$name] = [];
                            }

                            /* Go through each of the properties */
                            while ($column_properties
                                = $column_parser->getNextWord()) {
                                $lowercase_properties = strtolower(
                                    $column_properties
                                );

                                /* Validate the properties */
                                switch (true) {
                                    /* Auto_increment */
                                    case ($lowercase_properties
                                        == 'auto_increment') :
                                        {
                                            $arguments['columns'][$name]['auto_increment']
                                                = 1;

                                            break;
                                        }

                                    /* Primary key */
                                    case ($lowercase_properties == 'primary') :
                                        {
                                            if (strtolower(
                                                    $column_parser->getNextWord(
                                                    )
                                                ) != 'key') {
                                                $column_parser->characterIndex -= 7;
                                                $column_parser->throwSyntaxError(
                                                    $arguments
                                                );

                                                return false;
                                            }

                                            $arguments['columns'][$name]['primary']
                                                = 1;

                                            break;
                                        }

                                    /* Permanent column */
                                    case ($lowercase_properties
                                        == 'permanent') :
                                        {
                                            $arguments['columns'][$name]['permanent']
                                                = 1;

                                            break;
                                        }

                                    /* Default value for this column */
                                    case ($lowercase_properties == 'default') :
                                        {
                                            $arguments['columns'][$name]['default']
                                                = $column_parser->getNextWord(
                                                false
                                            );

                                            break;
                                        }

                                    /* Type: String */
                                    case (substr($lowercase_properties, 0, 6)
                                        == 'string') :
                                        {
                                            $arguments['columns'][$name]['type']
                                                = 'string';

                                            /* Look for a maximum value */
                                            if (substr(
                                                    $lowercase_properties,
                                                    6,
                                                    1
                                                ) == '('
                                                && substr(
                                                    $lowercase_properties,
                                                    -1,
                                                    1
                                                ) == ')') {
                                                $end = strlen(
                                                        $lowercase_properties
                                                    ) - 8;
                                                $max = substr(
                                                    $lowercase_properties,
                                                    7,
                                                    $end
                                                );

                                                $arguments['columns'][$name]['max']
                                                    = empty($max) ? 0 : $max;
                                            } /* Syntax Error */
                                            elseif (strlen(
                                                    $lowercase_properties
                                                ) > 6) {
                                                $column_parser->throwSyntaxError(
                                                    $arguments
                                                );

                                                return false;
                                            }

                                            break;
                                        }

                                    /* Type: text */
                                    case (substr($lowercase_properties, 0, 4)
                                        == 'text') :
                                        {
                                            $arguments['columns'][$name]['type']
                                                = 'text';

                                            /* Look for a maximum value */
                                            if (substr(
                                                    $lowercase_properties,
                                                    4,
                                                    1
                                                ) == '('
                                                && substr(
                                                    $lowercase_properties,
                                                    -1,
                                                    1
                                                ) == ')') {
                                                $end = strlen(
                                                        $lowercase_properties
                                                    ) - 6;
                                                $max = substr(
                                                    $lowercase_properties,
                                                    5,
                                                    $end
                                                );

                                                $arguments['columns'][$name]['max']
                                                    = empty($max) ? 0 : $max;
                                            } /* Syntax error */
                                            elseif (strlen(
                                                    $lowercase_properties
                                                ) > 4) {
                                                $column_parser->throwSyntaxError(
                                                    $arguments
                                                );

                                                return false;
                                            }

                                            break;
                                        }

                                    /* Type: int */
                                    case (substr($lowercase_properties, 0, 3)
                                        == 'int') :
                                        {
                                            $arguments['columns'][$name]['type']
                                                = 'int';

                                            /* Look for a maximum value */
                                            if (substr(
                                                    $lowercase_properties,
                                                    3,
                                                    1
                                                ) == '('
                                                && substr(
                                                    $lowercase_properties,
                                                    -1,
                                                    1
                                                ) == ')') {
                                                $end = strlen(
                                                        $lowercase_properties
                                                    ) - 5;
                                                $max = substr(
                                                    $lowercase_properties,
                                                    4,
                                                    $end
                                                );

                                                $arguments['columns'][$name]['max']
                                                    = empty($max) ? 0 : $max;
                                            } /* Syntax Error */
                                            elseif (strlen(
                                                    $lowercase_properties
                                                ) > 3) {
                                                $column_parser->throwSyntaxError(
                                                    $arguments
                                                );

                                                return false;
                                            }

                                            break;
                                        }

                                    /* Type: bool */
                                    case ($lowercase_properties == 'bool') :
                                        {
                                            $arguments['columns'][$name]['type']
                                                = 'bool';

                                            break;
                                        }

                                    /* Type: date */
                                    case ($lowercase_properties == 'date') :
                                        {
                                            $arguments['columns'][$name]['type']
                                                = 'date';

                                            break;
                                        }

                                    /* Type: enum */
                                    case (substr($lowercase_properties, 0, 4)
                                        == 'enum') :
                                        {
                                            $arguments['columns'][$name]['type']
                                                = 'enum';

                                            /* Grab the enum values specified for this column */
                                            if (substr(
                                                    $lowercase_properties,
                                                    4,
                                                    1
                                                ) == '('
                                                && substr(
                                                    $lowercase_properties,
                                                    -1,
                                                    1
                                                ) == ')') {
                                                $end = strlen(
                                                        $lowercase_properties
                                                    ) - 6;
                                                $enum_statement = substr(
                                                    $lowercase_properties,
                                                    5,
                                                    $end
                                                );
                                                $enum_parser = new WordParser(
                                                    $enum_statement
                                                );

                                                while ($enumerations
                                                    = $enum_parser->getNextWord(
                                                    false,
                                                    $this->whitespace.","
                                                )) {
                                                    $arguments['columns'][$name]['enum_val'][]
                                                        = $enumerations;
                                                }

                                                unset($arguments['columns'][$name]['default']);
                                            } /* Syntax Error */
                                            else {
                                                $column_parser->throwSyntaxError(
                                                    $arguments
                                                );

                                                return false;
                                            }

                                            break;
                                        }

                                    /* Syntax Error */
                                    default :
                                        {
                                            $this->throwSyntaxError($arguments);

                                            return false;
                                        }
                                }
                            }
                        }
                    }

                    break;
                }

            /* Something went wrong */
            default :
                {
                    /* Syntax Error or incorrect action */
                    if ($create == '') {
                        $this->throwSyntaxError($arguments);
                    } else {
                        TxtSQL::_error(
                            E_USER_NOTICE,
                            'Action not supported: `CREATE '.$create.'`'
                        );
                    }

                    return false;
                }
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses an UPDATE query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseUpdate(&$arguments)
    {
        $arguments['action'] = 'update';
        $arguments['table'] = $this->getNextWord();

        if (strpos($arguments['table'], '.')) {
            list($arguments['db'], $arguments['table']) = explode(
                '.',
                $arguments['table']
            );
        }

        /* Look for some main keywords */
        while ($word = $this->getNextWord()) {
            switch (true) {
                /* Grab the value-set */
                case (strtolower(substr($word, 0, 3)) == 'set') :
                    {
                        $this->characterIndex -= strlen($word) + 1;
                        $this->getValueSet($arguments);

                        break;
                    }

                /* Parse the where clause */
                case (strtolower($word) == 'where') :
                    {
                        $this->parseWhere($arguments);
                        $this->characterIndex--;

                        break;
                    }

                /* Parse the orderby clause */
                case (strtolower($word) == 'orderby') :
                    {
                        $this->parseOrderBy($arguments);

                        break;
                    }

                /* Parse the limit clause */
                case (strtolower($word) == 'limit') :
                    {
                        $this->parseLimit($arguments);

                        break;
                    }
            }
        }

        /* Return whatever results we got back */

        return $arguments;
    }

    /**
     * Parses a GRANT PERMISSIONS query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseGrant(&$arguments)
    {
        /* Set our action */
        $arguments['action'] = 'grant permissions';

        if ((strtolower($this->getNextWord()) != 'permissions')
            || (strtolower(
                    $this->getNextWord()
                ) != 'to')) {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Grab the username, and the action */
        $user = $this->getNextWord();
        $action = strtolower($this->getNextWord());
        $set = $this->getNextWord(true, $this->whitespace);

        if (substr(strtolower($set), 0, 3) != 'set') {
            $this->throwSyntaxError($arguments);

            return false;
        } /* If there is a break between the letters 'SET' and the actual set, then fix it */
        elseif (strtolower($set) == 'set') {
            $set = $this->getNextWord();
        }

        /* Grab the passwords in the set */
        $passwords = substr($set, strpos($set, '(') + 1);
        $passwords = substr($passwords, 0, strrpos($passwords, ')'));
        $password_parser = new WordParser($passwords);

        /* Check for a valid action, and create the php code for it */
        switch ($action) {
            /* Add a user */
            case 'add' :

                /* Drop a user */
            case 'drop' :
                {
                    $pass = $password_parser->getNextWord();
                    $arguments['php'] = '$this->grant_permissions("'.$action
                        .'", "'.$user.'", "'.$pass.'");';

                    break;
                }

            /* Edit a user */
            case 'edit' :
                {
                    $pass = $password_parser->getNextWord(
                        false,
                        $this->whitespace.","
                    );
                    $newpass = $password_parser->getNextWord(
                        false,
                        $this->whitespace.","
                    );
                    $arguments['php'] = '$this->grant_permissions("'.$action
                        .'", "'.$user.'", "'.$pass.'", "'.$newpass.'");';

                    break;
                }

            /* Syntax Error */
            default:
                {
                    $this->throwSyntaxError($arguments);

                    return false;
                }
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses a LOCK [database] query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseLock(&$arguments)
    {
        /* Set our action */
        $arguments['action'] = 'lock db';

        /* Look for a valid db name */
        if (($arguments['db'] = $this->getNextWord()) === false
            || empty($arguments['db'])) {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses an UNLOCK [database] query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseUnlock(&$arguments)
    {
        /* Set our action */
        $arguments['action'] = 'unlock db';

        /* Look for a valid db name */
        if (($arguments['db'] = $this->getNextWord()) === false
            || empty($arguments['db'])) {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses an IS LOCKED [database] query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseIsLocked(&$arguments)
    {
        /* Set our action */
        $arguments['action'] = 'is locked';

        if (strtolower($this->getNextWord()) != 'locked') {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Look for a valid db name */
        if ((($arguments['db'] = $this->getNextWord()) === false)
            || empty($arguments['db'])) {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Return our arguments */

        return $arguments;
    }

    /**
     * Parses a USE [database] query
     *
     * @param mixed $arguments The arguments this far in the parsing
     *
     * @return mixed $arguments The new arguments after the changes have been
     *               applied
     */
    private function parseUse(&$arguments)
    {
        /* Set our action */
        $arguments['action'] = 'use database';

        /* Look for a valid db name */
        if (($arguments['db'] = $this->getNextWord()) === false
            || empty($arguments['db'])) {
            $this->throwSyntaxError($arguments);

            return false;
        }

        /* Return our arguments */

        return $arguments;
    }

}

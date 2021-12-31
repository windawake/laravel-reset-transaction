<?php

namespace Laravel\ResetTransaction\Database;


use Illuminate\Database\Query\Grammars\MySqlGrammar as Grammar;
use Laravel\ResetTransaction\Facades\RT;

class MySqlGrammar extends Grammar
{
    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param  string  $name
     * @return string
     */
    public function compileSavepoint($name)
    {
        $sql = 'SAVEPOINT '.$name;
        RT::saveQuery($sql, [], 0, 0);

        return $sql;
    }

    /**
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param  string  $name
     * @return string
     */
    public function compileSavepointRollBack($name)
    {
        $sql = 'ROLLBACK TO SAVEPOINT '.$name;
        RT::saveQuery($sql, [], 0, 0);

        return $sql;
    }
}

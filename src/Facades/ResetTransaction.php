<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

class ResetTransaction
{
    protected $transactIdArr = [];
    protected $transactRollback = [];

    public function beginTransaction()
    {
        $this->stmtBegin();

        $transactId = session_create_id();
        array_push($this->transactIdArr, $transactId);

        return $this->getTransactId();
    }

    public function commit()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            array_pop($this->transactIdArr);

            return true;
        }

        $this->logRT(RT::STATUS_COMMIT);

        
        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);
        foreach ($xidArr as $name => $xid) {
            foreach ($this->transactRollback as $transactId) {
                DB::connection($name)->table('reset_transact')->where('transact_id', 'like', $transactId . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
            }
        }

        foreach ($xidArr as $name => $xid) {
            $sqlCollects = DB::connection($name)->table('reset_transact')->where('transact_id', 'like', $this->getTransactId() . '%')->get();
            if ($sqlCollects->count() > 0) {
                foreach ($sqlCollects as $item) {
                    if ($item->transact_status != RT::STATUS_ROLLBACK) {
                        $result = DB::connection($name)->getPdo()->exec($item->sql);
                        if ($item->check_result && $result != $item->result) {
                            throw new ResetTransactionException("db had been changed by anothor transact_id");
                        }
                    }
                }
                DB::connection($name)->table('reset_transact')->where('transact_id', 'like', $this->getTransactId() . '%')->delete();
            }
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function middlewareBeginTransaction($transactId)
    {
        $transactIdArr = explode('-', $transactId);
        $sqlArr = DB::table('reset_transact')->where('transact_id', 'like', $transactIdArr[0].'%')->whereIn('transact_status', [RT::STATUS_START, RT::STATUS_COMMIT])->pluck('sql')->toArray();
        $sql = implode(';', $sqlArr);
        $this->stmtBegin();
        if ($sqlArr) {
            DB::unprepared($sql);
        }

        $this->setTransactId($transactId);
    }

    public function middlewareRollback()
    {
        $this->stmtRollback();
        $this->logRT(RT::STATUS_COMMIT);

        if ($this->transactRollback) {
            $xidArr = $this->getUsedXidArr();
            $this->xaBeginTransaction($xidArr);
            foreach ($xidArr as $name => $xid) {
                foreach ($this->transactRollback as $transactId) {
                    DB::connection($name)->table('reset_transact')->where('transact_id', 'like', $transactId . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
                }
            }
    
            $this->xaCommit($xidArr);
        }
        
        $this->removeRT();
    }

    public function rollBack()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            $transactId = $this->getTransactId();
            foreach ($this->transactRollback as $i => $txId) {
                if (strpos($txId, $transactId) === 0) {
                    unset($this->transactRollback[$i]);
                }
            }
            array_push($this->transactRollback, $transactId);
            array_pop($this->transactIdArr);
            return true;
        }

        $this->logRT(RT::STATUS_ROLLBACK);

        $xidArr = $this->getUsedXidArr();

        $this->xaBeginTransaction($xidArr);

        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->table('reset_transact')->where('transact_id', 'like', $this->getTransactId() . '%')->delete();
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function commitTest()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            array_pop($this->transactIdArr);

            return true;
        }

        $this->logRT(RT::STATUS_COMMIT);
    }

    public function rollBackTest()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            $transactId = $this->getTransactId();
            foreach ($this->transactRollback as $i => $txId) {
                if (strpos($txId, $transactId) === 0) {
                    unset($this->transactRollback[$i]);
                }
            }
            array_push($this->transactRollback, $transactId);
            array_pop($this->transactIdArr);
            return true;
        }

        $this->logRT(RT::STATUS_ROLLBACK);
    }

    public function setTransactId($transactId)
    {
        $this->transactIdArr = explode('-', $transactId);
    }


    public function getTransactId()
    {
        return implode('-', $this->transactIdArr);
    }

    public function getTransactRollback()
    {
        return $this->transactRollback;
    }

    private function getUsedXidArr()
    {
        $conList = config('rt_database.connections', []);
        $xidArr = [];
        foreach ($conList as $name => $config) {
            $count = DB::connection($name)->table('reset_transact')->where('transact_id', 'like', $this->getTransactId() . '%')->count();
            if ($count > 0) {
                $xid = session_create_id();
                $xidArr[$name] = $xid;
            }
        }

        return $xidArr;
    }

    public function logRT($status)
    {
        $sqlArr = session()->get('rt_transact_sql');
        $requestId = session()->get('rt_request_id');
        if (is_null($requestId)) {
            $requestId = $this->transactIdArr[0];
        }
        
        if ($sqlArr) {
            foreach ($sqlArr as $item) {
                DB::table('reset_transact')->insert([
                    'request_id' => $requestId,
                    'transact_id' => $item['transact_id'],
                    'transact_status' => $status,
                    'sql' => value($item['sql']),
                    'result' => $item['result'],
                    'check_result' => $item['check_result'],
                ]);
            }
        }
    }

    private function removeRT()
    {
        $this->transactIdArr = [];

        session()->remove('rt_transact_sql');
        session()->remove('rt_request_id');
    }


    /**
     * beginTransaction
     *
     */
    public function xaBeginTransaction($xidArr)
    {
        $this->_XAStart($xidArr);
    }

    /**
     * commit
     * @param $xidArr
     */
    public function xaCommit($xidArr)
    {
        $this->_XAEnd($xidArr);
        $this->_XAPrepare($xidArr);
        $this->_XACommit($xidArr);
    }

    /**
     * rollback
     * @param $xidArr
     */
    public function xaRollBack($xidArr)
    {
        $this->_XAEnd($xidArr);
        $this->_XAPrepare($xidArr);
        $this->_XARollback($xidArr);
    }

    private function _XAStart($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA START '{$xid}'");
        }
    }


    private function _XAEnd($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA END '{$xid}'");
        }
    }


    private function _XAPrepare($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA PREPARE '{$xid}'");
        }
    }


    private function _XACommit($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA COMMIT '{$xid}'");
        }
    }

    private function _XARollback($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA ROLLBACK '{$xid}'");
        }
    }

    public function saveQuery($query, $bindings, $result, $checkResult, $keyName = null, $id = null)
    {
        $rtTransactId = $this->getTransactId();
        if ($rtTransactId && $query && !strpos($query, 'reset_transact')) {
            $subString = strtolower(substr(trim($query), 0, 12));
            $actionArr = explode(' ', $subString);
            $action = $actionArr[0];

            $sql = str_replace("?", "'%s'", $query);
            $completeSql = vsprintf($sql, $bindings);

            if (in_array($action, ['insert', 'update', 'delete', 'set', 'savepoint', 'rollback'])) {
                $backupSql = $completeSql;
                if ($action == 'insert') {
                    // if only queryBuilder insert or batch insert then return false
                    if (is_null($id)) {
                        return false;
                    }

                    if (!strpos($query, "`{$keyName}`")) {
                        // extract variables from sql
                        preg_match("/insert into (.+) \((.+)\) values \((.+)\)/", $backupSql, $match);
                        $table = $match[1];
                        $columns = $match[2];
                        $parameters = $match[3];

                        $columns = "`{$keyName}`, " . $columns;
                        $parameters = "'{$id}', " . $parameters;
                        $backupSql = "insert into $table ($columns) values ($parameters)";
                    }
                }

                $sqlItem = [
                    'transact_id' => $rtTransactId, 
                    'sql' => $backupSql, 
                    'result' => $result,
                    'check_result' => (int) $checkResult,
                ];
                session()->push('rt_transact_sql', $sqlItem);
            }

        }
    }

    private function stmtBegin()
    {
        session()->put('rt_stmt', 'begin');
        DB::beginTransaction();
        session()->remove('rt_stmt', 'begin');
    }

    private function stmtRollback()
    {
        session()->put('rt_stmt', 'rollback');
        DB::rollBack();
        session()->remove('rt_stmt', 'rollback');
    }
}

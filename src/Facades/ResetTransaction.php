<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

class ResetTransaction
{
    private $transactId;
    private $checkResult;

    public function beginTransaction()
    {
        $this->transactId = session_create_id();
        DB::beginTransaction();
        session()->put('rt-transact_id', $this->transactId);

        return $this->transactId;
    }

    public function commit()
    {
        DB::rollBack();
        $this->logRT();
        
        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);

        foreach ($xidArr as $name => $xid) {
            $sqlCollects = DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->transactId . '%')->get();
            if ($sqlCollects->count() > 0) {
                foreach ($sqlCollects as $item) {
                    $result = DB::connection($name)->getPdo()->exec($item->sql);
                    if ($this->checkResult && $result != $item->result) {
                        throw new ResetTransactionException("db had been changed by anothor transact_id");
                    }
                }
                DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->transactId . '%')->delete();
            }
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function rollback()
    {
        DB::rollBack();
        $this->logRT();
        
        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);

        foreach($xidArr as $name => $xid) {
            DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->transactId.'%')->delete();
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function setTransactId(string $transactId)
    {
        $this->transactId = $transactId;
    }

    public function getTransactId()
    {
        return $this->transactId;
    }

    public function setCheckResult(bool $boolean)
    {
        $this->checkResult = $boolean;
    }

    public function getCheckResult()
    {
        return $this->checkResult;
    }

    private function getUsedXidArr()
    {
        $conList = config('rt_database.connections', []);
        $xidArr = [];
        foreach($conList as $name => $config) {
            $count = DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->transactId.'%')->count();
            if ($count > 0) {
                $xid = session_create_id();
                $xidArr[$name] = $xid;
            }
        }

        return $xidArr;
    }

    private function logRT()
    {        
        $sqlArr = session()->get('rt-transact_sql');
        if ($sqlArr) {
            foreach ($sqlArr as $item) {
                DB::table('reset_transaction')->insert([
                    'transact_id' => $this->transactId,
                    'sql' => value($item['sql']),
                    'result' => $item['result'],
                ]);
            }
        }
    }

    private function removeRT()
    {
        $this->transactId = null;
        $this->checkResult = false;
        session()->remove('rt-transact_id');
        session()->remove('rt-transact_sql');
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
        foreach($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA START '{$xid}'");
        }
    }


    private function _XAEnd($xidArr)
    {
        foreach($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA END '{$xid}'");
        }
    }


    private function _XAPrepare($xidArr)
    {
        foreach($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA PREPARE '{$xid}'");
        }
    }


    private function _XACommit($xidArr)
    {
        foreach($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA COMMIT '{$xid}'");
        }
    }

    private function _XARollback($xidArr)
    {
        foreach($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA ROLLBACK '{$xid}'");
        }
    }
}

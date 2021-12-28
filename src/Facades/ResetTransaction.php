<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

class ResetTransaction
{
    private $transactIdArr = [];
    private $transactRollback = [];
    private $checkResult;

    public function beginTransaction($transactId = '')
    {
        DB::beginTransaction();

        if ($transactId) {
            $this->setTransactId($transactId);
        } else {
            $transactId = session_create_id();
            array_push($this->transactIdArr, $transactId);
        }
        

        return $this->getTransactId();
    }

    public function commit()
    {
        DB::rollBack();
        $this->logRT();

        if (count($this->transactIdArr) > 1) {
            array_pop($this->transactIdArr);
            return true;
        }


        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);

        foreach ($xidArr as $name => $xid) {
            foreach ($this->transactRollback as $transactId) {
                DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->delete();
            }

            $sqlCollects = DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->getTransactId() . '%')->get();
            if ($sqlCollects->count() > 0) {
                foreach ($sqlCollects as $item) {
                    $result = DB::connection($name)->getPdo()->exec($item->sql);
                    if ($this->checkResult && $result != $item->result) {
                        throw new ResetTransactionException("db had been changed by anothor transact_id");
                    }
                }
                DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->getTransactId() . '%')->delete();
            }
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function rollBack()
    {
        DB::rollBack();
        $this->logRT();

        if (count($this->transactIdArr) > 1) {
            $transactId = $this->getTransactId();
            array_push($this->transactRollback, $transactId);

            array_pop($this->transactIdArr);
            return true;
        }

        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);

        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->getTransactId() . '%')->delete();
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function setTransactId($transactId)
    {
        $this->transactIdArr = explode('-', $transactId);
    }


    public function getTransactId()
    {
        return implode('-', $this->transactIdArr);
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
        foreach ($conList as $name => $config) {
            $count = DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->getTransactId() . '%')->count();
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
                    'transact_id' => $this->getTransactId(),
                    'sql' => value($item['sql']),
                    'result' => $item['result'],
                ]);
            }
        }
    }

    private function removeRT()
    {
        $this->transactIdArr = [];
        $this->transactRollback = [];
        $this->checkResult = false;

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
}

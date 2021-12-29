<?php

namespace Laravel\ResetTransaction\Facades;

use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

class ResetTransaction
{
    protected $transactIdArr = [];
    protected $checkResult;
    protected $transactRollback = [];

    public function beginTransaction()
    {
        DB::beginTransaction();

        $transactId = session_create_id();
        array_push($this->transactIdArr, $transactId);

        return $this->getTransactId();
    }

    public function commit()
    {
        DB::rollBack();

        if (count($this->transactIdArr) > 1) {
            array_pop($this->transactIdArr);

            return true;
        }

        $this->logRT(RT::STATUS_COMMIT);


        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);
        foreach ($xidArr as $name => $xid) {
            foreach ($this->transactRollback as $transactId) {
                DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
            }
        }

        foreach ($xidArr as $name => $xid) {
            $sqlCollects = DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->getTransactId() . '%')->get();
            if ($sqlCollects->count() > 0) {
                foreach ($sqlCollects as $item) {
                    if ($item->transact_status != RT::STATUS_ROLLBACK) {
                        $result = DB::connection($name)->getPdo()->exec($item->sql);
                        if ($this->checkResult && $result != $item->result) {
                            throw new ResetTransactionException("db had been changed by anothor transact_id");
                        }
                    }
                }
                DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $this->getTransactId() . '%')->delete();
            }
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function middlewareBeginTransaction($transactId)
    {
        $this->setTransactId($transactId);

        $sqlArr = DB::table('reset_transaction')->where('transact_id', 'like', $this->transactIdArr[0].'%')->whereIn('transact_status', [RT::STATUS_START, RT::STATUS_COMMIT])->pluck('sql')->toArray();
        $sql = implode(';', $sqlArr);
        DB::beginTransaction();
        if ($sqlArr) {
            DB::unprepared($sql);
        }
    }

    public function middlewareRollback()
    {

        DB::rollBack();
        $this->logRT(RT::STATUS_COMMIT);

        $xidArr = $this->getUsedXidArr();
        $this->xaBeginTransaction($xidArr);
        foreach ($xidArr as $name => $xid) {
            foreach ($this->transactRollback as $transactId) {
                DB::connection($name)->table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
            }
        }

        $this->xaCommit($xidArr);
        $this->removeRT();
    }

    public function rollBack()
    {
        DB::rollBack();

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

    public function getTransactRollback()
    {
        return $this->transactRollback;
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

    private function logRT($status)
    {
        $sqlArr = session()->get('rt-transact_sql');
        if ($sqlArr) {
            foreach ($sqlArr as $item) {
                DB::table('reset_transaction')->insert([
                    'transact_id' => $item['transact_id'],
                    'transact_status' => $status,
                    'sql' => value($item['sql']),
                    'result' => $item['result'],
                ]);
            }
        }
    }

    private function removeRT()
    {
        $this->transactIdArr = [];
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

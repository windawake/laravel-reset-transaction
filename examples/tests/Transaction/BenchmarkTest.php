<?php

namespace Tests\Transaction;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;
use App\Models\ResetAccountModel;
use App\Models\ResetStorageModel;

class BenchmarkTest extends TestCase
{
    protected $urlOne = 'http://127.0.0.1:8000/api';
    protected $urlTwo = 'http://127.0.0.1:8001/api';
    protected $urlThree = 'http://127.0.0.1:8002/api';
    protected $urlFour = 'http://127.0.0.1:8003/api';
    protected $urlFive = 'http://127.0.0.1:8004/api';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDeadlock01()
    {
        // $con = DB::connection('service_order');

        $shellOne = "ab -n 12 -c 4 {$this->urlOne}/resetOrderTest/deadlockWithLocal";
        $shellTwo = "ab -n 12 -c 4 {$this->urlTwo}/resetOrderTest/deadlockWithLocal";

        $shell = sprintf("%s & %s", $shellOne, $shellTwo);
        exec($shell, $output, $resultCode);

        // $sql = "SHOW ENGINE INNODB STATUS";
        // $ret = $con->select($sql);
        // Log::info($ret);
        
    }

    public function testDeadlock02()
    {
        $shellOne = "ab -n 12 -c 4 {$this->urlOne}/resetOrderTest/deadlockWithRt";
        $shellTwo = "ab -n 12 -c 4 {$this->urlTwo}/resetOrderTest/deadlockWithRt";

        $shell = sprintf("%s & %s", $shellOne, $shellTwo);
        exec($shell, $output, $resultCode);
    }

    public function testBatchCreate01()
    {
        $count1 = ResetOrderModel::count();

        $shellOne = "ab -n 100 -c 10 {$this->urlOne}/resetOrderTest/orderWithLocal";
        $shellTwo = "ab -n 100 -c 10 {$this->urlTwo}/resetOrderTest/orderWithLocal";
        $shellThree = "ab -n 100 -c 10 {$this->urlThree}/resetOrderTest/orderWithLocal";

        $shell = sprintf("%s & %s & %s", $shellOne, $shellTwo, $shellThree);
        exec($shell, $output, $resultCode);
        $count2 = ResetOrderModel::count();

        $this->assertTrue($count2 - $count1 == 300);
    }

    public function testBatchCreate02()
    {
        $count1 = ResetOrderModel::count();

        $shellOne = "ab -n 100 -c 10 {$this->urlOne}/resetOrderTest/orderWithRt";
        $shellTwo = "ab -n 100 -c 10 {$this->urlTwo}/resetOrderTest/orderWithRt";
        $shellThree = "ab -n 100 -c 10 {$this->urlThree}/resetOrderTest/orderWithRt";

        $shell = sprintf("%s & %s & %s", $shellOne, $shellTwo, $shellThree);
        exec($shell, $output, $resultCode);
        $count2 = ResetOrderModel::count();

        $this->assertTrue($count2 - $count1 == 300);
    }

    public function testBatchCreate03()
    {
        ResetOrderModel::where('id', '<=', 10)->delete();
        sleep(6);
        $shellOne = "ab -n 100 -c 10 {$this->urlOne}/resetOrderTest/disorderWithLocal";
        $shellTwo = "ab -n 100 -c 10 {$this->urlTwo}/resetOrderTest/disorderWithLocal";
        $shellThree = "ab -n 100 -c 10 {$this->urlThree}/resetOrderTest/disorderWithLocal";

        $shell = sprintf("%s & %s & %s", $shellOne, $shellTwo, $shellThree);
        exec($shell, $output, $resultCode);
    }

    public function testBatchCreate04()
    {
        ResetOrderModel::where('id', '<=', 10)->delete();
        sleep(6);
        $shellOne = "ab -n 100 -c 10 {$this->urlOne}/resetOrderTest/disorderWithRt";
        $shellTwo = "ab -n 100 -c 10 {$this->urlTwo}/resetOrderTest/disorderWithRt";
        $shellThree = "ab -n 100 -c 10 {$this->urlThree}/resetOrderTest/disorderWithRt";

        $shell = sprintf("%s & %s & %s", $shellOne, $shellTwo, $shellThree);
        exec($shell, $output, $resultCode);
    }

    public function testBatchCreate05()
    {
        ResetOrderModel::truncate();

        $amount1 = ResetAccountModel::where('id', 1)->value('amount');
        $stockQty1 = ResetStorageModel::where('id', 1)->value('stock_qty');

        $dataPath = __DIR__.'/data.txt';
        $shellOne = "ab -n 12 -c 4 -p '{$dataPath}' {$this->urlOne}/resetAccountTest/createOrdersCommit";
        $shellTwo = "ab -n 12 -c 4  -p '{$dataPath}' {$this->urlTwo}/resetAccountTest/createOrdersCommit";
        $shellThree = "ab -n 12 -c 4  -p '{$dataPath}' {$this->urlThree}/resetAccountTest/createOrdersCommit";

        $shell = sprintf("%s & %s & %s", $shellOne, $shellTwo, $shellThree);
        exec($shell, $output, $resultCode);

        $amount2 = ResetAccountModel::where('id', 1)->value('amount');
        $stockQty2 = ResetStorageModel::where('id', 1)->value('stock_qty');

        $amountSum = ResetOrderModel::sum('amount');
        $stockQtySum = ResetOrderModel::sum('stock_qty');
        $total = ResetOrderModel::count();

        $this->assertTrue($total == 36);
        $this->assertTrue(abs($amount1 - $amount2 - $amountSum) < 0.001);
        $this->assertTrue(($stockQty1 - $stockQty2) == $stockQtySum);

    }
}

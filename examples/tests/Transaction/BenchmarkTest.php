<?php

namespace Tests\Transaction;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;

class BenchmarkTest extends TestCase
{
    protected $urlOne = 'http://127.0.0.1:8000/api';
    protected $urlTwo = 'http://127.0.0.1:8001/api';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDeadlock01()
    {
        $shellOne = "ab -n 12 -c 4 {$this->urlOne}/resetOrderTest/deadlockWithLocal";
        $shellTwo = "ab -n 12 -c 4 {$this->urlTwo}/resetOrderTest/deadlockWithLocal";

        $shell = sprintf("%s & %s", $shellOne, $shellTwo);
        exec($shell, $output, $resultCode);
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
        $shellOne = "ab -n 50 -c 4 {$this->urlOne}/resetOrderTest/createWithLocal";
        $shellTwo = "ab -n 50 -c 4 {$this->urlTwo}/resetOrderTest/createWithLocal";

        $shell = sprintf("%s & %s", $shellOne, $shellTwo);
        exec($shell, $output, $resultCode);
        $count2 = ResetOrderModel::count();

        $this->assertTrue($count2 - $count1 == 100);
    }

    public function testBatchCreate02()
    {
        $count1 = ResetOrderModel::count();
        $shellOne = "ab -n 50 -c 4 {$this->urlOne}/resetOrderTest/createWithRt";
        $shellTwo = "ab -n 50 -c 4 {$this->urlTwo}/resetOrderTest/createWithRt";

        $shell = sprintf("%s & %s", $shellOne, $shellTwo);
        exec($shell, $output, $resultCode);
        $count2 = ResetOrderModel::count();

        $this->assertTrue($count2 - $count1 == 100);
    }
}

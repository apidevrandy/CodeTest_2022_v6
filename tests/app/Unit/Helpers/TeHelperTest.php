<?php

use Tests\TestCase;
use DTApi\Helpers\TeHelper;

use Carbon\Carbon;

class TeHelperTest extends TestCase
{
    public function test_willExpireAt_difference25_CreatedAtAddHours25_expected()
    {
        $due_time = '2024-10-16 00:00:00';
        $created_at = '2024-10-17 01:00:00';

        $helper = new TeHelper();
        $result = $helper->willExpireAt($due_time, $created_at);

        $expected = Carbon::parse($created_at)->addHours(16)->format('Y-m-d H:i:s');

        $this->assertSame($expected, $result);
    }

    public function test_willExpireAt_difference0_CreatedAtAddHours16_expected()
    {
        $due_time = '2024-10-16 00:00:00';
        $created_at = '2024-10-16 00:00:00';

        $helper = new TeHelper();
        $result = $helper->willExpireAt($due_time, $created_at);

        $expected = Carbon::parse($created_at)->addMinutes(90)->format('Y-m-d H:i:s');

        $this->assertSame($expected, $result);
    }

    public function test_willExpireAt_difference73_dueTime_expected()
    {
        $due_time = '2024-10-16 00:00:00';
        $created_at = '2024-10-19 01:00:00';

        $helper = new TeHelper();
        $result = $helper->willExpireAt($due_time, $created_at);

        $expected = Carbon::parse($due_time)->format('Y-m-d H:i:s');

        $this->assertSame($expected, $result);
    }

    public function test_willExpireAt_difference91_dueTimeSubHours48_expected()
    {
        $due_time = '2024-10-16 00:00:00';
        $created_at = '2024-10-19 19:00:00';

        $helper = new TeHelper();
        $result = $helper->willExpireAt($due_time, $created_at);

        $expected = Carbon::parse($due_time)->subHours(48)->format('Y-m-d H:i:s');

        $this->assertSame($expected, $result);
    }
}


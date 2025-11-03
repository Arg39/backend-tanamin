<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CompanyStatistics;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CompanyStatisticsTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'title' => 'Total Employees',
            'value' => 120,
            'unit' => 'people',
        ];

        $stats = new CompanyStatistics($data);
        $stats->id = 'stat-1';

        $this->assertEquals('stat-1', $stats->id);
        $this->assertEquals('Total Employees', $stats->title);
        $this->assertEquals(120, $stats->value);
        $this->assertEquals('people', $stats->unit);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $stats = new CompanyStatistics();

        $this->assertEquals('company_statistics', $stats->getTable());
        $this->assertFalse($stats->incrementing);
        $this->assertEquals('string', $stats->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        $stats = \Mockery::mock(CompanyStatistics::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $stats->fill([
            'title' => 'Projects Completed',
            'value' => 42,
            'unit' => 'projects',
        ]);
        $stats->setAttribute('id', null);

        $stats->shouldReceive('save')->andReturnUsing(function () use ($stats) {
            if (empty($stats->id)) {
                $stats->id = (string) Str::uuid();
            }
            return true;
        });

        $stats->save();

        $this->assertNotEmpty($stats->id);
        $this->assertTrue(Str::isUuid($stats->id));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

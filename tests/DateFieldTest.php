<?php

namespace Tests;

use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\Event;

class DateFieldTest extends SyncTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::truncate();
    }

    protected function tearDown(): void
    {
        Event::truncate();

        parent::tearDown();
    }

    private function featured(string $startsAt): string
    {
        return json_encode([
            (object) [
                'ref_id' => 'featured-1',
                'name' => 'Opening',
                'starts_at' => $startsAt,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
        ]);
    }

    public function test_md_field_with_a_date_is_stored_as_utc_date_time()
    {
        $event = (new Event)->storeWithSync(new Request, [
            'name' => 'Launch',
            'starts_at' => '2026-11-24 10:00:00',
            'featured' => $this->featured('2026-11-24 10:00:00'),
        ]);

        $this->assertEquals(
            new UTCDateTime(strtotime('2026-11-24 10:00:00') * 1000),
            new UTCDateTime($event->starts_at)
        );
        $this->assertEquals('Opening', $event->featured->name);
        $this->assertNotNull($event->featured->starts_at);
        $this->assertNotNull($event->featured->created_at);
        $this->assertNotNull($event->featured->updated_at);
    }

    public function test_md_field_with_an_empty_value_is_stored_as_null()
    {
        $event = (new Event)->storeWithSync(new Request, [
            'name' => 'Launch',
            'starts_at' => '',
            'featured' => $this->featured(''),
        ]);

        $this->assertNull($event->starts_at);
        $this->assertNull($event->featured->starts_at);
    }
}

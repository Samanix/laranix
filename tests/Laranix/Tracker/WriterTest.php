<?php
namespace Laranix\Tests\Laranix\Tracker;

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Laranix\Tracker\Events\BatchCreated;
use Laranix\Tracker\Tracker;
use Laranix\Tracker\Writer;
use Laranix\Tests\LaranixTestCase;
use Illuminate\Support\Facades\Event;
use Laranix\Tracker\Events\Created;
use Mockery as m;

class WriterTest extends LaranixTestCase
{
    /**
     * @var bool
     */
    protected $runMigrations = true;

    /**
     * Set up
     */
    public function setUp()
    {
        parent::setUp();

         $_SERVER['REQUEST_URI'] = '/foo/bar';
    }

    /**
     * Test tracking when buffer is 0
     */
    public function testTrackWithZeroBuffer()
    {
        $writer = $this->createWriter(0, false);

        $writer->register([
            'typeId'    => 100,
            'type'      => 'foo',
            'trackType' => 2,
            'data'      => 'test track',
        ]);

        Event::assertDispatched(Created::class, function ($event) {
            return $event->track->type === 'foo' && $event->track->typeId === 100 && $event->track->trackType === Tracker::TRACKER_TRAIL;
        });

        $this->assertDatabaseHas(config('tracker.table', 'tracker'), [
            'tracker_type_id'       => 100,
            'tracker_type'          => 'foo',
            'trackable_type'        => 2,
            'tracker_data'          => 'test track',
            'tracker_data_rendered' => null,
        ]);
    }

    /**
     * Test limited buffer
     */
    public function testTrackWithLimitedBuffer()
    {
        $writer = $this->createWriter(2);

        $writer->register([
            'typeId'    => 100,
            'type'      => 'foo',
            'trackType' => 2,
            'itemId'    => 1,
            'data'      => 'test track',
        ]);

        Event::assertNotDispatched(BatchCreated::class);

        $this->assertDatabaseMissing(config('tracker.table', 'tracker'), [
            'tracker_type_id'   => 100,
            'tracker_type'      => 'foo',
            'trackable_type'    => 2,
            'tracker_data'      => 'test track',
        ]);

        $writer->register([
            'typeId'    => 200,
            'type'      => 'bar',
            'trackType' => 2,
            'itemId'    => 1,
            'data'      => 'test track 2',
        ]);

        Event::assertDispatched(BatchCreated::class, function ($event) {
            return $event->count === 2;
        });

        $this->assertSame(2, Tracker::count());

        // Buffer should have reset
        $writer->register([
            'typeId'    => 300,
            'type'      => 'baz',
            'trackType' => 2,
            'data'      => 'test track 3',
        ]);

        $this->assertDatabaseMissing(config('tracker.table', 'tracker'), [
            'tracker_type_id'   => 300,
            'tracker_type'      => 'baz',
            'trackable_type'    => 2,
            'tracker_data'      => 'test track 3',
        ]);
    }

    /**
     * Test unlimited buffer
     */
    public function testUnlimitedBuffer()
    {
        $writer = $this->createWriter(-1);

        $writer->add([
            'typeId'    => 100,
            'type'      => 'foo',
            'itemId'    => 10,
            'trackType' => 2,
            'data'      => null,
        ])->add([
            'typeId'    => 200,
            'type'      => 'bar',
            'trackType' => 2,
            'data'      => 'test track 2',
        ])->add([
            'typeId'    => 300,
            'type'      => 'baz',
            'trackType' => 2,
            'data'      => 'test track 3',
        ])->add([
            'typeId'    => 400,
            'type'      => 'foobar',
            'trackType' => 2,
            'data'      => '**test track 4**',
            'method'    => 'POST',
            'url'       => 'not this one',
        ]);

        Event::assertNotDispatched(BatchCreated::class);

        $this->assertDatabaseMissing(config('tracker.table', 'tracker'), [
            'tracker_type_id'   => 100,
            'tracker_type'      => 'foo',
            'tracker_item_id'   => 10,
            'trackable_type'    => 2,
            'tracker_data'      => null,
        ]);

        $writer->flush();

        Event::assertDispatched(BatchCreated::class, function ($event) {
            return $event->count === 4;
        });

        $this->assertDatabaseHas(config('tracker.table', 'tracker'), [
            'request_method'        => 'GET',
            'request_url'           => urlTo('/foo/bar'),
            'tracker_type_id'       => 400,
            'tracker_type'          => 'foobar',
            'tracker_item_id'       => null,
            'trackable_type'        => 2,
            'tracker_data_rendered' => '<p><strong>test track 4</strong></p>',
        ]);

        $this->assertSame(4, Tracker::count());
    }

    /**
     * Test parse settings throws exception on invalid type
     */
    public function testParseSettingsThrowsExceptionOnInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createWriter()->parseSettings('invalid type');
    }

    /**
     * @param int  $buffer
     * @param bool $rendered
     * @return \Laranix\Tracker\Writer
     */
    protected function createWriter(int $buffer = 0, bool $rendered = true) : Writer
    {
        $request = m::mock(Request::class);

        $request->shouldReceive('user')->andReturn(null);
        $request->shouldReceive('getClientIp')->andReturn('127.0.0.1');
        $request->shouldReceive('server')->withAnyArgs()->andReturn('agent');
        $request->shouldReceive('getMethod')->withNoArgs()->andReturn('GET');

        return new Writer(new Repository([
            'tracker' => [
                'buffer' => $buffer,
                'save_rendered' => $rendered,
            ],
        ]), $request);
    }
}

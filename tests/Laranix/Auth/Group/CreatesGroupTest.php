<?php
namespace Laranix\Tests\Laranix\Auth\Group;

use Laranix\Auth\Group\CreatesGroup;
use Laranix\Auth\Group\Events\Created;
use Laranix\Auth\Group\Settings;
use Laranix\Tests\LaranixTestCase;
use Illuminate\Support\Facades\Event;

class CreatesGroupTest extends LaranixTestCase
{
    /**
     * @var bool
     */
    protected $runMigrations = true;

    /**
     * Test creating group
     */
    public function testCreateGroup()
    {
        $create = $this->getMockForTrait(CreatesGroup::class);

        $create->createGroup($this->createSettings());
        Event::assertDispatched(Created::class, function ($event) {
            return $event->group->name === 'foo';
        });


        $create->createGroup($this->createSettingsFromArray());
        Event::assertDispatched(Created::class, function ($event) {
            return $event->group->name === 'bar';
        });

        $this->assertDatabaseHas(config('laranixauth.group.table'), [
            'name'    => 'foo',
            'color'   => 'red',
            'icon'    => 'foo.png',
            'level'   => 100,
            'flags'   => json_encode(['a', 'b', 'c']),
            'hidden'  => 0,
        ]);

        $this->assertDatabaseHas(config('laranixauth.group.table'), [
            'name'    => 'bar',
            'color'   => 'blue',
            'icon'    => 'bar.png',
            'level'   => 50,
            'flags'   => json_encode(['d', 'e', 'f']),
            'hidden'  => 1,
        ]);
    }

    /**
     * @return \Laranix\Auth\Group\Settings
     */
    protected function createSettings()
    {
        return new Settings([
            'name'    => 'foo',
            'color'   => 'red',
            'icon'    => 'foo.png',
            'level'   => 100,
            'flags'   => ['a', 'b', 'c'],
            'hidden'  => false,
        ]);
    }

    /**
     * @return array
     */
    protected function createSettingsFromArray()
    {
        return [
            'name'    => 'bar',
            'color'   => 'blue',
            'icon'    => 'bar.png',
            'level'   => '50',
            'flags'   => ['d', 'e', 'f'],
            'hidden'  => true,
        ];
    }
}

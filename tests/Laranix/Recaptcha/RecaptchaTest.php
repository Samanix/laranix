<?php
namespace Laranix\Tests\Laranix\Recaptcha;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Mockery as m;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Laranix\Recaptcha\Recaptcha;
use Laranix\Tests\LaranixTestCase;

class RecaptchaTest extends LaranixTestCase
{
    /**
     * Test rendering when enabled
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function testWhenEnabled()
    {
        list($config, $request, $view) = $this->getConstructorArgs();

        $view->shouldReceive('exists')->andReturn(true);
        $view->shouldReceive('make')->andReturnSelf();
        $view->shouldReceive('render')->andReturn('output');

        $this->assertSame('output', (new Recaptcha($config, $request, $view))->render());
    }

    /**
     * Test when recaptcha disabled
     */
    public function testWhenEnabledIsFalse()
    {
        list($config, $request, $view) = $this->getConstructorArgs(false);

        $recaptcha = new Recaptcha($config, $request, $view);

        $this->assertFalse($recaptcha->enabled());
        $this->assertNull($recaptcha->render());
    }

    /**
     * Test when recaptcha disabled due to environment
     */
    public function testWhenEnvironmentIsDisabled()
    {
        list($config, $request, $view) = $this->getConstructorArgs(true, 'disabled1');

        $recaptcha = new Recaptcha($config, $request, $view);

        $this->assertFalse($recaptcha->enabled());
        $this->assertNull($recaptcha->render());
    }

    /**
     * Test when recaptcha disabled for users
     */
    public function testIsNotEnabledWhenNotEnabledForUsers()
    {
        list($config, $request, $view) = $this->getConstructorArgs(true, 'testing', true);

        $request->shouldReceive('user')->andReturn(1);

        $this->assertFalse((new Recaptcha($config, $request, $view))->enabled());
    }

    /**
     * Test when recaptcha disabled for users
     */
    public function testIsEnabledWhenEnabledForUsers()
    {
        list($config, $request, $view) = $this->getConstructorArgs();

        $request->shouldReceive('user')->andReturn(1);

        $this->assertTrue((new Recaptcha($config, $request, $view))->enabled());
    }

    /**
     * Test when recaptcha is enabled for guests
     */
    public function testIsEnabledWhenEnabledForUsersAndIsGuest()
    {
        list($config, $request, $view) = $this->getConstructorArgs();

        $request->shouldReceive('user')->andReturnNull();

        $this->assertTrue((new Recaptcha($config, $request, $view))->enabled());
    }

    /**
     * Test when view not found
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function testRenderThrowsExceptionWhenViewNotFound()
    {
        list($config, $request, $view) = $this->getConstructorArgs();

        $view->shouldReceive('exists')->andReturn(false);
        $view->shouldReceive('make')->andReturnSelf();
        $view->shouldReceive('render')->andReturn('foo');

        $this->expectException(\Illuminate\Contracts\Filesystem\FileNotFoundException::class);

        (new Recaptcha($config, $request, $view))->render('form');
    }

    /**
     * @param bool   $enabled
     * @param string $env
     * @param bool   $guests_only
     * @return array
     */
    protected function getConstructorArgs(bool $enabled = true, string $env = 'testing', bool $guests_only = false)
    {
        $config = new Repository([
            'app' => [
                'env' => $env,
            ],
            'recaptcha' => [
                // Turn on/off
                'enabled'   => $enabled,

                // Key and secret, obtain from Google
                'key'       => 'key',
                'secret'    => 'secret',

                // Default view to use
                'view'      => 'layout.recaptcha',

                // If true, recaptcha is disabled for logged in users
                'guests_only'    => $guests_only,

                // List of environments where Recaptcha is disabled
                'disabled_in'   => [
                    'disabled1',
                    'disabled2',
                ],
            ],
        ]);

        return [
            $config,
            m::mock(Request::class),
            m::mock(ViewFactory::class)
        ];
    }
}
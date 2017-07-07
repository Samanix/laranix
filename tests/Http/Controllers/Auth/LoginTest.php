<?php
namespace Tests\Http\Controllers\Auth;

use Laranix\Auth\Events\Login\Restricted;
use Laranix\Auth\User\User;
use Tests\LaranixTestCase;
use Laranix\Support\IO\Url\Url;
use Illuminate\Support\Facades\Event;


class LoginTest extends LaranixTestCase
{
    /**
     * @var bool
     */
    protected $runMigrations = true;

    /**
     * @var array
     */
    protected $factories = [
        User::class         => __DIR__ . '/../../../Factory/User',
    ];

    /**
     * Test get login page
     */
    public function testGetLogin()
    {
        $response = $this->get('login');

        $response->assertStatus(200);
    }

    /**
     * Test login when account is not active
     */
    public function testPostLoginWhenAccountNotActive()
    {
        $this->createFactories();

        $response = $this->post('login', ['email' => 'foo@baz.com', 'password' => 'secret'], ['HTTP_REFERER' => Url::to('login')]);

        $response->assertStatus(302);

        $response->assertRedirect('login');

        $response->assertSessionHasErrors([
            'login_account_status' => 'Your account is unverified',
        ]);

        Event::assertDispatched(Restricted::class, function ($event) {
            return $event->user->user_id === 3;
        });
    }

    /**
     * Test login when account is active
     */
    public function testPostLoginWhenAccountActive()
    {
        $this->createFactories();

        $response = $this->withSession([
            'url' => [
                'intended' => '/home',
            ]
        ])->post('login', ['email' => 'foo@bar.com', 'password' => 'secret']);

        $response->assertStatus(302);

        $response->assertRedirect('home');

        $response->assertSessionHas([
            'login_notice'          => true,
            'login_notice_header'   => 'Welcome back, foo!',
            'login_notice_message'  => 'You have been logged in successfully',
            'login_notice_is_error' => false,
        ]);
    }

    /**
     * Test logout
     */
    public function testPostLogout()
    {
        $this->createFactories();

        $response = $this->actingAs(User::find(1))->post('logout');

        $response->assertStatus(302);

        $response->assertRedirect('login');

        $response->assertSessionHas([
            'login_notice'          => true,
            'login_notice_header'   => 'See you soon, foo',
            'login_notice_message'  => 'You have been logged out',
            'login_notice_is_error' => false,
        ]);
    }
}

<?php
namespace Laranix\Auth\Email\Verification;

use Illuminate\Contracts\Auth\Authenticatable;
use Laranix\Auth\User\Token\Manager as BaseManager;
use Laranix\Auth\User\Token\MailSettings;
use Laranix\Auth\Email\Events\Updated as EmailUpdated;
use Laranix\Auth\Email\Verification\Events\Created;
use Laranix\Auth\Email\Verification\Events\Failed;
use Laranix\Auth\Email\Verification\Events\Updated;
use Laranix\Auth\Email\Verification\Events\Verified as VerifiedEvent;
use Laranix\Auth\Email\Verification\Mail as VerificationMail;
use Laranix\Auth\User\User;
use Laranix\Support\Exception\EmailExistsException;

class Manager extends BaseManager
{
    /**
     * The model for the tokens
     *
     * @var string
     */
    protected $model = Verification::class;

    /**
     * Key to use inside the laranixauth config
     *
     * @var string
     */
    protected $configKey = 'verification';

    /**
     * The mail class name to create the email from
     *
     * @var \Laranix\Support\Mail\Mail
     */
    protected $mailTemplateClass = VerificationMail::class;

    /**
    * The mail options class to use in the mail
    *
    * @var string
    */
    protected $mailOptionsClass = MailSettings::class;

    /**
     * Created event class name
     *
     * @var string
     */
    protected $createdEvent = Created::class;

    /**
     * Updated event class name
     *
     * @var string
     */
    protected $updatedEvent = Updated::class;

    /**
     * Failed event class name
     *
     * @var string
     */
    protected $failedEvent = Failed::class;

    /**
     * Completed event class name
     *
     * @var string
     */
    protected $completedEvent = VerifiedEvent::class;

    /**
     * Update user after token verified
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|User $user
     * @param string                                          $email
     * @param mixed                                           $extra
     * @return \Illuminate\Contracts\Auth\Authenticatable|\Laranix\Auth\User\User
     */
    protected function updateUser(Authenticatable $user, string $email, $extra = null): Authenticatable
    {
        if ($user->account_status === User::USER_UNVERIFIED) {
            $user->account_status = User::USER_ACTIVE;
        }

        $oldemail = $user->email;

        $user->email = $email;
        $user->save();

        event(new EmailUpdated($user, $oldemail));

        return $user;
    }

    /**
     * @param \Illuminate\Contracts\Auth\Authenticatable|User $user
     * @param string                                          $email
     * @return mixed
     * @throws \Laranix\Support\Exception\EmailExistsException
     */
    protected function canInsertToken(Authenticatable $user, string $email)
    {
        $existing = User::where('email', $email)->get();

        $count = $existing->count();

        if ($count === 0) {
            return true;
        }

        if ($count === 1 && $existing[0]->id === $user->id) {
            return true;
        }

        throw new EmailExistsException('Email already exists');
    }
}

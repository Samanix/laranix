<?php
namespace Laranix\Auth\User\Cage;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laranix\Auth\User\Cage\Events\Created;
use Laranix\Auth\User\Cage\Events\Deleted;
use Laranix\Auth\User\User;
use Laranix\Support\Database\Model;

class Cage extends Model
{
    use SoftDeletes;

    const CAGE_ACTIVE = 1;
    const CAGE_EXPIRED = 2;
    const CAGE_REMOVED = 4;

    /**
     * @var string
     */
    protected $primaryKey = 'cage_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['cage_level', 'cage_area', 'cage_time', 'cage_reason', 'cage_issuer', 'user_id', 'cage_status'];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = ['cage_reason_rendered'];

    /**
     * @var array
     */
    protected $events = [
        'created' => Created::class,
        'deleted' => Deleted::class,
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'deleted_at'
    ];

    /**
     * Returns when cage expires.
     *
     * @var \Carbon\Carbon
     */
    protected $cageExpires = null;

    /**
     * Returns cage reason
     *
     * @var array
     */
    protected $cageReason = null;

    /**
     * Rendered reason
     *
     * @var string|null
     */
    protected $renderedReason = null;

    /**
     * UserCage constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = $this->config->get('laranixauth.cage.table', 'user_cage');
    }

    /**
     * Get cage id attribute
     *
     * @return int
     */
    public function getIdAttribute() : int
    {
        return $this->getAttributeFromArray('cage_id');
    }

    /**
     * Get cage expiry time.
     *
     * @return \Carbon\Carbon
     */
    public function getExpiryAttribute() : Carbon
    {
        if ($this->cageExpires !== null) {
            return $this->cageExpires;
        }

        return $this->cageExpires = $this->created_at->addMinutes($this->getAttributeFromArray('cage_time'));
    }

    /**
     * Get cage level
     *
     * @return int
     */
    public function getLevelAttribute() : int
    {
        return $this->getAttributeFromArray('cage_level');
    }

    /**
     * Get cage area
     *
     * @return string
     */
    public function getAreaAttribute() : string
    {
        return $this->getAttributeFromArray('cage_area');
    }

    /**
     * Get cage level
     *
     * @return int
     */
    public function getTimeAttribute() : int
    {
        return $this->getAttributeFromArray('cage_time');
    }

    /**
     * Get cage reason
     *
     * @return string|null
     */
    public function getReasonAttribute() : ?string
    {
        return $this->getAttributeFromArray('cage_reason');
    }

    /**
     * Get rendered reason
     *
     * @return null|string
     */
    public function getRenderedReasonAttribute() : ?string
    {
        if ($this->renderedReason !== null) {
            return $this->renderedReason;
        }

        if ($this->config->get('laranixauth.cage.save_rendered', true) &&
            ($rendered = $this->getAttributeFromArray('cage_reason_rendered')) !== null) {

            return $this->renderedReason = $rendered;
        }

        if (($raw = $this->getAttributeFromArray('cage_reason')) !== null) {
            return $this->renderedReason = markdown($raw);
        }

        return null;
    }

    /**
     * Set & save rendered data
     *
     * @param   bool $save
     * @return  mixed
     */
    public function saveRenderedReason(bool $save = true)
    {
        $raw = $this->getAttributeFromArray('cage_reason');

        if (($rendered = markdown($raw)) === $this->getAttributeFromArray('cage_reason_rendered')) {
            return null;
        }

        $this->setAttribute('cage_reason_rendered', $rendered);

        if ($save) {
            $this->save();
        }

        $this->renderedReason = $rendered;

        return $this;
    }

    /**
     * Get cage reason
     *
     * @return int
     */
    public function getStatusAttribute() : int
    {
        return $this->getAttributeFromArray('cage_status');
    }

    /**
     * Only get active cages
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        $table = $this->config->get('laranixauth.cage.table', 'user_cage');
        $status = self::CAGE_ACTIVE;

        return $query->whereRaw("(`{$table}`.`cage_time` = 0 OR (TIMESTAMPDIFF(MINUTE, `{$table}`.`created_at`, NOW()) <= `{$table}`.`cage_time`) AND `{$table}`.`cage_status` = {$status})");
    }

    /**
     * Get cage issuer
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function issuer()
    {
        return $this->hasOne(User::class, 'user_id', 'issuer_id');
    }

    /**
     * Get caged user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne(User::class, 'user_id', 'user_id');
    }



    // TODO join
//    public function rawSelect()
//    {
//        return $this->newQuery()->select()
//    }
}

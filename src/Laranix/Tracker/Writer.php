<?php
namespace Laranix\Tracker;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Laranix\Support\Database\Model;
use Laranix\Tracker\Events\BatchCreated;

class Writer implements TrackWriter
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @var int
     */
    protected $buffersize;

    /**
     * @var array
     */
    protected $buffer = [];

    /**
     * @var int
     */
    protected $buffercount = 0;

    /**
     * Writer constructor.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     * @param \Illuminate\Http\Request                $request
     */
    public function __construct(Config $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;

        $this->enabled = (bool) $this->config->get('tracker.enabled', true);
        $this->buffersize = (int) $this->config->get('tracker.buffer', 0);
    }

    /**
     * Registers a new track
     *
     * @param \Laranix\Tracker\Settings|array $settings
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function register($settings)
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->buffersize === 0 || $this->buffersize === 1) {
            $this->write($settings);

            return;
        }

        $settings = $this->parseSettings($settings);

        $payload = $this->getPayload($settings);

        $this->buffer[] = $payload;

        ++$this->buffercount;

        if ($this->buffersize !== -1 && $this->buffercount >= $this->buffersize) {
            $this->flush();
        }
    }

    /**
     * Add a new track, allows for chaining
     *
     * @param \Laranix\Tracker\Settings|array $settings
     * @return $this
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function add($settings)
    {
        $this->register($settings);

        return $this;
    }

    /**
     * Writes registered tracks
     *
     * @param \Laranix\Tracker\Settings|array $settings
     * @return \Laranix\Support\Database\Model|\Laranix\Tracker\Tracker|null
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function write($settings) : ?Model
    {
        if (!$this->enabled) {
            return null;
        }

        $settings = $this->parseSettings($settings);

        return Tracker::createNew($this->getPayload($settings));
    }

    /**
     * @param \Laranix\Tracker\Settings|array $settings
     * @return \Laranix\Tracker\Settings
     */
    public function parseSettings($settings) : Settings
    {
        if ($settings instanceof Settings) {
            return $settings;
        }

        if (is_array($settings)) {
            return new Settings($this->request, $settings);
        }

        throw new \InvalidArgumentException('Settings is not a supported type');
    }

    /**
     * Parse settings to array
     *
     * @param \Laranix\Tracker\Settings $settings
     * @return array
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    protected function getPayload(Settings $settings) : array
    {
        $settings->hasRequiredSettings();

        $now = Carbon::now()->toDateTimeString();

        if ($this->config->get('tracker.save_rendered', true) && $settings->data !== null) {
            $rendered = markdown($settings->data);
        }

        return [
            'user_id'           => $settings->user,
            'ipv4'              => $settings->ipv4(),
            'user_agent'        => $settings->userAgent(),
            'request_method'    => $settings->requestMethod(),
            'request_url'       => $settings->requestUrl(),
            'type'              => strtolower($settings->type),
            'type_id'           => $settings->typeId,
            'item_id'           => $settings->itemId,
            'level'             => (int) $settings->flagLevel,
            'trackable_type'    => $settings->trackType !== Tracker::TRACKER_ANY ? $settings->trackType : Tracker::TRACKER_TRAIL,
            'data'              => $settings->data,
            'data_rendered'     => $rendered ?? null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];
    }

    /**
     * Flush buffer
     */
    public function flush()
    {
        if ($this->buffercount === 0 || $this->enabled === false) {
            return;
        }

        Tracker::insert($this->buffer);

        event(new BatchCreated($this->buffercount));

        $this->buffercount = 0;
        $this->buffer = [];
    }
}

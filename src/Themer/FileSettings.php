<?php
namespace Laranix\Themer;

use Laranix\Support\Settings as SettingsBase;

abstract class FileSettings extends SettingsBase
{
    /**
     * @var array
     */
    protected $required = [
        'key'   => 'string',
        'file'  => 'string',
        'order' => 'int',
    ];

    /**
     * Unique key
     *
     * @var string
     */
    public $key;

    /**
     * File name
     *
     * @var string
     */
    public $file;

    /**
     * URL of file if remote
     *
     * @var string
     */
    public $url;

    /**
     * Order to load file in
     *
     * @var int
     */
    public $order = -1;

    /**
     * Set to true to load from default theme if not found in given theme
     *
     * @var bool
     */
    public $defaultFallback = true;

    /**
     * If true, will search for .min files
     *
     * @var bool
     */
    public $automin = false;

    /**
     * Theme name for file
     *
     * @var string
     */
    public $themeName;

//    /**
//     * TODO Can be worked around by using remote script instead (manually set url)
//     * If true, will merge with other files of same type
//     *
//     * @var bool
//     *
//     */
//    public $merge = true;

    // Values below are auto set

    /**
     * Theme in use for file (auto set)
     *
     * @var \Laranix\Themer\Theme
     */
    public $theme;

    /**
     * File path
     *
     * @var string
     */
    public $filePath;

    /**
     * Stores existence state of file
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Stores the modified file time (auto set)
     *
     * @var int
     */
    public $mtime = 0;

    /**
     * Repository key for file (auto set)
     *
     * @var string
     */
    public $repositoryKey;
}

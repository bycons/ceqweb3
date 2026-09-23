<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Log\Handlers\FileHandler;

class Logger extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Error Logging Threshold
     * --------------------------------------------------------------------------
     *
     * You can enable error logging by setting a threshold over zero. The
     * threshold determines what gets logged.
     *
     * @var array|int
     */
    public $threshold = (ENVIRONMENT === 'production') ? [1, 2, 3, 4, 7] : 9;

    /**
     * --------------------------------------------------------------------------
     * Date Format for Logs
     * --------------------------------------------------------------------------
     *
     * Each item that is logged has an associated date.
     */
    public string $dateFormat = 'Y-m-d H:i:s';

    /**
     * --------------------------------------------------------------------------
     * Log Handlers
     * --------------------------------------------------------------------------
     *
     * The logging system supports multiple handlers.
     */
    public array $handlers = [

        // \CodeIgniter\Log\Handlers\FileHandler::class => [
        //     'handles' => ['critical', 'alert', 'emergency', 'debug', 'error', 'info', 'notice', 'warning'],
        //     'path'    => WRITEPATH . 'logs/',
        // ],

        // FileHandler::class => [
        //     'handles' => ['critical', 'alert', 'emergency', 'debug', 'error', 'info', 'notice', 'warning'],
        // ],

        // \App\Log\Handlers\WorkerHandler::class => [
        //     'handles' => ['info', 'error', 'debug'],
        //     'path'           => WRITEPATH . 'logs/cron/',  // ← corrigido
        //     'filenameFormat'  => 'workanalise-{date}',
        // ],

        // Handler exclusivo dos workers — caminho e formato próprios
        \App\Log\Handlers\WorkerHandler::class => [
            'handles'        => ['info', 'error', 'debug'],
            'path'           => WRITEPATH . 'logs/cron/',
            'filenameFormat' => 'workanalise-{date}',
            'dateFormat'     => 'd/m/Y H:i:s',
        ],

        \App\Log\Handlers\MultiLevelFileHandler::class => [
            'handles' => [
                'info',
                'debug',
                'error',
                'warning',
                'critical',
                'alert',
                'emergency',
                'notice'
            ],
            'path' => WRITEPATH . 'logs/',
        ],

        \App\Log\Handlers\LogEmailThrottleHandler::class => [
            'handles' => ['error', 'critical', 'alert', 'emergency'],
            // 'to' => 'douglas@4web.net.br',
            'path' => WRITEPATH . 'logs/',
        ],
    ];
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Drewlabs package.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'json' => [
        'version' => '1.0.0',
        'kewords' => ['rest', 'json', 'api'],
        'services' => [
            'storage' => [
                'type' => 'local',
                'name' => 'test-storage',
            ],
        ],
    ],
    'body' => [
        'version' => '2.0',
        'services' => [
            'web' => [
                'image' => 'nginx',
                'ports' => [
                    '80:8000',
                ],
                'restart' => 'always',
            ],
        ],
    ],
    'body2' => [
        'post_id' => 2,
        'comments' => [
            ['content' => 'Hello World!', 'likes' => 0],
            ['content' => 'Testing implementation of Psr7 client!', 'likes' => 5],
        ],
    ],
    'multipart' => [
        [
            'name' => 'version',
            'contents' => '2.0',
        ],
        [
            'name' => 'services',
            'contents' => [
                [
                    [
                        'name' => 'name',
                        'contents' => 'web',
                    ],
                    // 'name',
                    [
                        'name' => 'image',
                        'contents' => 'nginx',
                    ],
                    [
                        'name' => 'ports',
                        'contents' => ['80:8000'],
                    ],
                    [
                        'name' => 'restart',
                        'contents' => 'always',
                    ],
                    [
                        'name' => 'env',
                        'contents' => [
                            [
                                'name' => 'host',
                                'contents' => 'https://127.0.0.1/api/v1/transactions',
                            ],

                            [
                                'name' => 'key',
                                'contents' => 'MyAPIKey',
                            ],

                            [
                                'name' => 'secret',
                                'contents' => 'MyAPISecret',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'multipart2' => [
        [
            'name' => 'post_id',
            'contents' => '2',
        ],
        [
            'name' => 'comments',
            'contents' => [
                [
                    ['name' => 'content', 'contents' => 'Hello World!'],
                    ['name' => 'likes', 'contents' => 0],
                ],
                [
                    ['name' => 'content', 'contents' => 'Testing implementation of Psr7 client!'],
                    ['name' => 'likes', 'contents' => 5],
                ],
            ],
        ],
    ],
];

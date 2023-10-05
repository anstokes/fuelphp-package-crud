<?php

return [
    'inject' => [
        'crud' => [
            'parent' => 'modules',
            'menu'   => [
                'id'    => 'sidebarCrud',
                'label' => 'CRUD',
                'icon'  => ['class' => 'ti ti-database menu-icon'],
                'menu'  => [
                    [
                        'label' => 'Test',
                        'link'  => '/',
                    ],
                ],
            ],
        ],
    ],
];

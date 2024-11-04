<?php

use MauticPlugin\MauticTrelloBundle\Integration\Support\ConfigSupport;
use MauticPlugin\MauticTrelloBundle\Integration\TrelloIntegration;

return [
    'name'        => 'Mautic Trello',
    'description' => 'Create Trello cards from Mautic contacts',
    'version'     => '2.0.0',
    'author'      => 'Aivie',
    'routes'      => [
        'main' => [
            'plugin_create_cards_show_new' => [
                'path'       => '/trello/card/show-new/{contactId}',
                'controller' => 'MauticTrelloBundle:Card:showNewCard',
            ],
            'plugin_trello_card_add' => [
                'path'        => '/trello/card',
                'method'      => 'POST',
                'controller'  => 'MauticTrelloBundle:Card:add',
                'returnRoute' => '',
            ],
        ],
    ],
    'parameters' => [
        'favorite_board' => '',
    ],
    'services' => [
//        'forms' => [
//            'mautic.trello.form.card' => [
//                'class'     => 'MauticPlugin\MauticTrelloBundle\Form\NewCardType',
//                'arguments' => [
//                    'mautic.trello.service.trello_api',
//                    'monolog.logger.mautic',
//                ],
//            ],
//            'mautic.trello.form.config' => [
//                'class'     => 'MauticPlugin\MauticTrelloBundle\Form\ConfigType',
//                'arguments' => [
//                    'mautic.lead.model.field',
//                    'mautic.trello.service.trello_api',
//                    'monolog.logger.mautic',
//                ],
//            ],
//        ],
//        'events' => [
//            'mautic.channel.button.subscriber.trello' => [
//                'class'     => \MauticPlugin\MauticTrelloBundle\Event\ButtonSubscriber::class,
//                'arguments' => [
//                    'router',
//                    'translator',
//                    'request_stack',
//                    'mautic.helper.integration',
//                ],
//            ],
//            'mautic.trello.event.config' => [
//                'class'     => \MauticPlugin\MauticTrelloBundle\Event\ConfigSubscriber::class,
//                'arguments' => [
//                    'mautic.helper.integration',
//                    'monolog.logger.mautic',
//                ],
//            ],
//        ],
//        'others' => [
//            'mautic.trello.service.trello_api' => [
//                'class'     => \MauticPlugin\MauticTrelloBundle\Service\TrelloApiService::class,
//                'arguments' => [
//                    'mautic.helper.integration',
//                    'mautic.helper.core_parameters',
//                    'monolog.logger.mautic',
//                ],
//            ],
//        ],
        'integrations' => [
            // Basic definitions with name, display name and icon
            'mautic.integration.trello'               => [
                'class' => TrelloIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            // Provides the form types to use for the configuration UI
            'trello.integration.configuration' => [
                'class'     => ConfigSupport::class,
                'tags'      => [
                    'mautic.config_integration',
                ],
            ],
        ],
    ],
];

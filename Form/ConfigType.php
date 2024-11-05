<?php

/**
 * @author    Aivie
 * @copyright 2022 Aivie. All rights reserved
 *
 * @see https://aivie.ch
 *
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticTrelloBundle\Form;

use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\MauticTrelloBundle\Service\TrelloApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Configure Trello integration in main Mautic Configiguration.
 */
class ConfigType extends AbstractType
{
    /**
     * ConfigType constructor.
     */
    public function __construct(
        private FieldModel $fieldModel,
        private TrelloApiService $apiService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Creates the Settings section for Trello.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->fieldModel->getFieldList(false, false);

        $builder->add(
            'favorite_board',
            ChoiceType::class,
            [
                'choices'    => $this->getBoards(),
                'required'   => false,
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
            ]
        );
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'trello_config';
    }

    /**
     * Get all Trello boards.
     */
    protected function getBoards(): array
    {
        $boards = array_flip($this->apiService->getBoardsArray());

        return null !== $boards ? $boards : [];
    }
}

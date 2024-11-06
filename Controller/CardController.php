<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTrelloBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Controller\LeadAccessTrait;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticTrelloBundle\Form\NewCardType;
use MauticPlugin\MauticTrelloBundle\Openapi\lib\Model\Card;
use MauticPlugin\MauticTrelloBundle\Openapi\lib\Model\NewCard;
use MauticPlugin\MauticTrelloBundle\Service\TrelloApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set up a form and send it to Trello to create a new card.
 */
class CardController extends AbstractFormController
{
    use LeadAccessTrait;

    public function __construct(
        private LoggerInterface $logger,
        private TrelloApiService $apiService,
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    /**
     * Show a new Trello card form with prefilled information from the Contact.
     */
    public function showNewCardAction(Request $request, int $contactId): Response
    {
        // returns the Contact or an error Response to show to the user
        $contact = $this->checkLeadAccess($contactId, 'view');
        if ($contact instanceof Response) {
            return $contact;
        }

        // build the form
        $form = $this->getForm($request, $contactId);

        // display empty form
        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $form->createView(),
                ],
                'contentTemplate' => '@MauticTrello/Card/new.html.twig',
            ]
        );
    }

    /**
     * Add a new card by POST or handle the cancelation of the form.
     */
    public function addAction(Request $request): JsonResponse|Response
    {
        $returnRoute = $request->get('returnRoute', '');

        $contactId =  0;
        $data      = $request->request->get('new_card', false);
        if (is_array($data) && isset($data['contactId'])) {
            $contactId =  (int) $data['contactId'];
        }

        // returns the Contact or an error Response to show to the user
        $response = $this->checkLeadAccess($contactId, 'view');
        if ($response instanceof RedirectResponse) {
            return $response;
        }

        // Check for a submitted form and process it
        $form = $this->getForm($request);

        if ($this->isFormCancelled($form)) {
            return $this->closeModal();
        }

        // process form data from HTTP variables
        $form->handleRequest($request);

        // MauticPlugin\MauticTrelloBundle\Openapi\lib\Model\NewCard;
        $newCard = $form->getData();

        if (!$newCard->valid()) {
            $invalid = current($newCard->listInvalidProperties());
            $message = sprintf($this->translator->trans('mautic.trello.card_data_not_valid'), $invalid);
            $this->addFlash($message, [], 'error');

            return new JsonResponse(['error' => $message]);
        }

        // create an array from the object (workaround to remove Object)
        $cardArray = json_decode($newCard->__toString(), true);

        // remove other values from array, only leave id
        $cardArray['idList'] = $form->get('idList')->getData()->getId();
        $card                = $this->apiService->addNewCard($cardArray);

        if ($card instanceof Card) {
            // successfully added
            $this->addFlash(
                'plugin.trello.card_added',
                ['%url%' => $card->getUrl(), '%title%' => $card->getName()]
            );
        } else {
            // not successfully added
            $this->addFlash(
                'plugin.trello.card_not_added',
                ['not-added']
            );
        }

        return $this->closeAndRedirect($returnRoute, $contactId);
    }

    /**
     * Close the modal after adding a card in Trello.
     */
    protected function closeAndRedirect(string $returnRoute, int $contactId): Response
    {
        if (empty($returnRoute) || empty($contactId)) {
            $this->logger->warning('Trello: No return url or contact for add to Trello specified', ['contactId' => $contactId, 'returnRoute' => $returnRoute]);
        }

        // return user to contact overview
        if ('mautic_contact_index' === $returnRoute) {
            $func           = 'index';
            $viewParameters = [
                'page'         => $this->get('session')->get('mautic.lead.page', 1),
                'objectId'     => $contactId,
            ];
        } else {
            // return user to contact detail view
            $func           = 'view';
            $viewParameters = [
                'objectAction' => 'view',
                'objectId'     => $contactId,
            ];
        }

        return $this->postActionRedirect(
            [
                'returnUrl'       => $this->generateUrl($returnRoute, $viewParameters),
                'viewParameters'  => $viewParameters,
                'contentTemplate' => 'MauticLeadBundle:Lead:'.$func,
                'passthroughVars' => [
                    'mauticContent' => 'lead',
                    'closeModal'    => 1,
                ],
            ]
        );
    }

    /**
     * Just close the modal and return parameters.
     */
    protected function closeModal(): JsonResponse
    {
        $passthroughVars = [
            'closeModal'    => 1,
            'mauticContent' => 'trelloCardAddCanceled',
        ];

        return new JsonResponse($passthroughVars);
    }

    /**
     * Build the form.
     *
     * @param int|null $contactId
     */
    protected function getForm(Request $request, int $contactId = null): ?FormInterface
    {
        $returnRoute = $request->get('returnRoute');
        if (empty($returnRoute)) {
            $this->logger->warning('Trello: No return route for add to Trello specified', ['contactId' => $contactId]);
            $returnRoute = "mautic_contact_action"; //somehow the returnRoute can be empty, so set it to the contact detail by default
        }
        $card = new NewCard();

        if (!empty($contactId)) {
            $contact = $this->getExistingContact($contactId);
            if (empty($contact)) {
                $this->logger->warning('Trello: no contact found for id', [$contactId]);

                return null;
            }
            $card = $this->contactToCard($contact);
        }

        $action = $this->generateUrl('plugin_trello_card_add', ['returnRoute' => $returnRoute]);

        return $this->createForm(NewCardType::class, $card, ['action' => $action]);
    }

    /**
     * Get existing contact.
     *
     * @param int $contactId
     *
     * @return Lead|null
     */
    protected function getExistingContact($contactId)
    {
        // maybe use Use $model->checkForDuplicateContact directly instead
        $leadModel = $this->getModel('lead');

        return $leadModel->getEntity($contactId);
    }

    /**
     * Set the default values for the new card.
     */
    protected function contactToCard(Lead $contact): NewCard
    {
        // $desc = array('Contact:', $contact->getEmail(), $contact->getPhone(), $contact->getMobile());
        $siteUrl = rtrim($this->coreParametersHelper->get('site_url'), '/');

        return new NewCard(
            [
                'name'      => $contact->getName(),
                'desc'      => null,
                'idList'    => $this->getListForContact($contact),
                'urlSource' => $siteUrl.'/s/contacts/view/'.$contact->getId(),
                'contactId' => $contact->getId(),
                // 'due' => new \DateTime('next week'),
            ]
        );
    }

    /**
     * Get the current list name the contact is on based on the stage name.
     *
     * @param Lead $contact Mautic Lead (aka Contact)
     */
    protected function getListForContact(Lead $contact): string
    {
        $stage = $contact->getStage();
        $lists = $this->apiService->getListsOnBoard();
        if (!empty($stage) && is_array($lists)) {
            foreach ($lists as $list) {
                if ($list->getName() === $stage->getName()) {
                    $this->logger->debug('Trello: contact is on list', [$list->getName()]);

                    return $list->getName();
                }
            }
        }
        $this->logger->debug('Trello: stage is not a list', [$stage]);

        return '';
    }
}

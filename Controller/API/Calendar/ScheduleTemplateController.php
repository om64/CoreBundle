<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Controller\API\Calendar;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\View;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use Claroline\CoreBundle\Manager\Calendar\ScheduleTemplateManager;
use Claroline\CoreBundle\Manager\ApiManager;
use Claroline\CoreBundle\Form\Calendar\ScheduleTemplateType;
use Claroline\CoreBundle\Entity\Calendar\ScheduleTemplate;
use Symfony\Component\Form\FormFactory;
use Claroline\CoreBundle\Persistence\ObjectManager;

/**
 * @NamePrefix("api_")
 */
class ScheduleTemplateController extends FOSRestController
{
    /**
     * @DI\InjectParams({
     *     "formFactory"             = @DI\Inject("form.factory"),
     *     "scheduleTemplateManager" = @DI\Inject("claroline.manager.calendar.schedule_template_manager"),
     *     "request"                 = @DI\Inject("request"),
     *     "apiManager"              = @DI\Inject("claroline.manager.api_manager"),
     *     "om"                      = @DI\Inject("claroline.persistence.object_manager")
     * })
     */
    public function __construct(
        FormFactory             $formFactory,
        ScheduleTemplateManager $scheduleTemplateManager,
        ApiManager              $apiManager,
        ObjectManager           $om,
        Request                 $request
    )
    {
        $this->formFactory             = $formFactory;
        $this->scheduleTemplateManager = $scheduleTemplateManager;
        $this->om                      = $om;
        $this->request                 = $request;
        $this->apiManager              = $apiManager;
    }

    /**
     * @View(serializerGroups={"api"})
     * @ApiDoc(
     *     description="Returns the schduletemplate creation form",
     *     views = {"schedule_template"}
     * )
     */
    public function getCreateScheduleTemplateFormAction()
    {
        $formType = new ScheduleTemplateType();
        $formType->enableApi();
        $form = $this->createForm($formType);

        return $this->apiManager->handleFormView(
            'ClarolineCoreBundle:API:Calendar\createScheduleTemplateForm.html.twig', 
            $form
        );
    }

    /**
     * @View(serializerGroups={"api"})
     * @ApiDoc(
     *     description="Returns the schedule template list",
     *     views = {"schedule_template"}
     * )
     */
    public function getScheduleTemplatesAction()
    {
        return $this->scheduleTemplateManager->getAll();
    }
}

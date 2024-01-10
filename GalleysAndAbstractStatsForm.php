<?php

namespace APP\plugins\generic\galleysAndAbstractStats;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class GalleysAndAbstractStatsForm extends Form {
    
    public function __construct(public GalleysAndAbstractStatsPlugin $plugin)
    {
        parent::__construct($plugin->getTemplateResource('settings.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $context = Application::get()
            ->getRequest()
            ->getContext();

        $this->setData(
            'statsFooterText',
            $this->plugin->getSetting(
                $context->getId(),
                'statsFooterText'
            )
        );

        parent::initData();
    }

    public function readInputData()
    {
        $this->readUserVars(['statsFooterText']);

        parent::readInputData();
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $context = Application::get()
            ->getRequest()
            ->getContext();

        $this->plugin->updateSetting(
            $context->getId(),
            'statsFooterText',
            $this->getData('statsFooterText')
        );

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute();
    }
}
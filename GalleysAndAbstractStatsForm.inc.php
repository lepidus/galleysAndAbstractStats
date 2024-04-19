<?php

import('lib.pkp.classes.form.Form');

class GalleysAndAbstractStatsForm extends Form
{
    private $plugin;

    public function __construct(GalleysAndAbstractStatsPlugin $plugin)
    {
        $this->plugin = $plugin;
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
            NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute();
    }
}

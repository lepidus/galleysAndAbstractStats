<?php

namespace APP\plugins\generic\galleysAndAbstractStats;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use PKP\core\JSONMessage;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class GalleysAndAbstractStatsPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('Templates::Article::Details', [$this, 'viewDataStatement']);
            Hook::add('TemplateManager::display', function ($hookName, $args) {
                $request = Application::get()->getRequest();
                $templateMgr = TemplateManager::getManager($request);
                $pluginFullPath = $request->getBaseUrl() . '/' . $this->getPluginPath();
                $templateMgr->addStyleSheet('GalleysAndAbstractStats', $pluginFullPath . '/styles/index.css');
            });
        }
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.galleysAndAbstractStats.name');
    }

    public function getDescription()
    {
        return __('plugins.generic.galleysAndAbstractStats.description');
    }

    public function viewDataStatement(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];
        $request = Application::get()->getRequest();
        $publication = $templateMgr->getTemplateVars('publication');
        $submissionId = $publication->getData('submissionId');
        $contextId = $request->getContext()->getId();
        $statsService = Services::get('publicationStats');
        $metricsByType = $statsService->getTotalsByType($submissionId, $contextId, null, null);
        $galleys = Repo::galley()->getCollector()
                ->filterByPublicationIds(['publicationIds' => $publication->getId()])
                ->getMany();

        $galleysViews = [];
        foreach ($galleys as $galley) {
            $galleysViews[] = ['galleyLabel' => $galley->getGalleyLabel(), 'galleyViews' => $galley->getViews()];
        }
        $templateMgr->assign([
            'abstractViews' => $metricsByType['abstract'],
            'galleysViews' => $galleysViews,
            'statsFooterText' => $this->getSetting($contextId, 'statsFooterText')
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('index.tpl'));

        return false;
    }

    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);

        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        array_unshift($actions, $linkAction);

        return $actions;
    }

    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':

                $form = new GalleysAndAbstractStatsForm($this);

                if (!$request->getUserVar('save')) {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }

                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
        }
        return parent::manage($args, $request);
    }
}

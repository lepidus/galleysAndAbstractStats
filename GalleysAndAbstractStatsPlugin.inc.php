<?php

import('lib.pkp.classes.plugins.GenericPlugin');

class GalleysAndAbstractStatsPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Templates::Article::Details', [$this, 'viewDataStatement']);
            HookRegistry::register('TemplateManager::display', function ($hookName, $args) {
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

        $abstractViews = $this->getAbstractViews($publication, $contextId);

        $galleys = $publication->getData('galleys');

        $galleysViews = [];
        foreach ($galleys as $galley) {
            $galleysViews[] = ['galleyLabel' => $galley->getGalleyLabel(), 'galleyViews' => $galley->getViews()];
        }
        $templateMgr->assign([
            'abstractViews' => $abstractViews,
            'galleysViews' => $galleysViews,
            'statsFooterText' => $this->getSetting($contextId, 'statsFooterText')
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('index.tpl'));

        return false;
    }

    private function getAbstractViews($publication, $contextId)
    {
        $statsService = \Services::get('stats');
        $allowedParams['publicationIds'] = [$publication->getId()];
        $allowedParams['contextIds'] = $contextId;

        $abstractRecords = $statsService->getRecords(array_merge($allowedParams, [
            'assocTypes' => [ASSOC_TYPE_SUBMISSION],
        ]));

        return array_reduce($abstractRecords, [$statsService, 'sumMetric'], 0);
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
        import('plugins.generic.galleysAndAbstractStats.GalleysAndAbstractStatsForm');

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

<?php

namespace APP\plugins\generic\galleysAndAbstractStats;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\template\TemplateManager;

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

    /**
     * Get a description of the plugin.
     */
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
            $galleysViews[] = [$galley->getGalleyLabel(), $galley->getViews()];
        }

        $templateMgr->assign([
            'abstractViews' => $metricsByType['abstract'],
            'galleysViews' => $galleysViews,
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('index.tpl'));

        return false;
    }
}

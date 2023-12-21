<?php

namespace APP\plugins\generic\galleysAndAbstractStats;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class GalleysAndAbstractStatsPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('Templates::Article::Details', [$this, 'viewDataStatement']);
        }
        return $success;
    }

    public function getDisplayName()
    {
        return 'Galleys And Abstract Stats Plugin';
    }

    /**
     * Get a description of the plugin.
     */
    public function getDescription()
    {
        return 'Galleys And Abstract Stats Plugin';
    }

    public function viewDataStatement(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];
        $templateMgr->assign([
            'galleysAndAbstractStats' => 'Made with ❤ by the Lepidus Tecnologia',
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('index.tpl'));

        return false;
    }
}

<?php

namespace APP\plugins\blocks\galleysAndAbstractStats;

use PKP\plugins\BlockPlugin;

class GalleysAndAbstractStatsPlugin extends BlockPlugin
{
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

    public function getContents($templateMgr, $request = null)
    {
        $templateMgr->assign([
          'galleysAndAbstractStats' => 'Made with ❤ by the Lepidus Tecnologia',
        ]);

        return parent::getContents($templateMgr, $request);
    }
}

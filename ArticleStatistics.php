<?php

namespace APP\plugins\generic\galleysAndAbstractStats;

use APP\statistics\StatisticsHelper;

class ArticleStatistics
{
    /**
     * Batch the same lifetime file totals used by Galley::getViews() in OJS 3.4.
     * File IDs, rather than galley IDs, also preserve totals for shared files.
     */
    public function getGalleyViews($statsService, iterable $galleys, int $contextId): array
    {
        $galleyList = [];
        $fileIds = [];
        foreach ($galleys as $galley) {
            $galleyList[] = $galley;
            $fileId = $galley->getData('submissionFileId');
            if ($fileId) {
                $fileIds[$fileId] = $fileId;
            }
        }

        $viewsByFile = [];
        if ($fileIds) {
            // Keep Galley::getViews()'s filters, including the date boundary.
            // Do not restrict submission/publication or assoc type: the core does not.
            $filters = [
                'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
                'dateEnd' => date('Y-m-d', strtotime('yesterday')),
                'contextIds' => [$contextId],
                'submissionFileIds' => array_values($fileIds),
            ];
            $records = $statsService->getQueryBuilder($filters)
                ->getSum([StatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID])
                ->get();
            foreach ($records as $record) {
                $viewsByFile[$record->submission_file_id] = $record->metric ?: 0;
            }
        }

        $views = [];
        foreach ($galleyList as $galley) {
            $fileId = $galley->getData('submissionFileId');
            $views[] = [
                'galleyLabel' => $galley->getGalleyLabel(),
                'galleyViews' => $viewsByFile[$fileId] ?? 0,
            ];
        }
        return $views;
    }
}

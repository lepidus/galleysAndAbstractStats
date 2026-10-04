<?php

/**
 * Collect article totals through the core reporting API and cache completed imports.
 */
class ArticleStatistics
{
    private $application;
    private $cacheManager;
    private $usageStatsPath;

    public function __construct($application, $cacheManager, string $usageStatsPath)
    {
        $this->application = $application;
        $this->cacheManager = $cacheManager;
        $this->usageStatsPath = $usageStatsPath;
    }

    /**
     * Labels and ordering belong to the publication; only numeric totals are cached.
     *
     * @return array{abstractViews: int|string, fileViews: array}
     */
    public function get($context, $publication, array $fileIds): array
    {
        $fileIds = array_values(array_unique(array_filter($fileIds)));
        sort($fileIds, SORT_NUMERIC);
        $metricType = $context->getDefaultMetricType();
        // Only usageStats has the archive/processing lifecycle used for invalidation.
        if (!defined('OJS_METRIC_TYPE_COUNTER') || $metricType !== OJS_METRIC_TYPE_COUNTER) {
            return $this->collect($context->getId(), $publication->getData('submissionId'), $fileIds);
        }
        $key = sha1(json_encode([
            $context->getId(),
            $metricType,
            $context->getMetricTypes(),
            $publication->getData('submissionId'),
            $publication->getId(),
            $fileIds
        ]));
        $cache = $this->cacheManager->getFileCache('galleysAbstractStats', $key, null);
        // Serialize cold requests for this article without locking unrelated articles.
        $lock = isset($cache->filename) ? @fopen($cache->filename . '.lock', 'c') : false;
        $locked = $lock && flock($lock, LOCK_EX);
        try {
            if ($locked) {
                // Another worker may have populated this cache while we waited.
                $cache = $this->cacheManager->getFileCache('galleysAbstractStats', $key, null);
            }
            $generation = $this->getImportGeneration();
            $entry = $cache->getCache('statistics');
            if (
                $generation !== null && is_array($entry)
                && ($entry['generation'] ?? null) === $generation
                && isset($entry['created'], $entry['totals'])
                && $entry['created'] > time() - 86400
            ) {
                return $entry['totals'];
            }

            $totals = $this->collect($context->getId(), $publication->getData('submissionId'), $fileIds);
            // FileLoader archives only after loading metrics. Never persist a partial import.
            if ($generation !== null && $generation === $this->getImportGeneration()) {
                $cache->setEntireCache(['statistics' => [
                    'generation' => $generation,
                    'created' => time(),
                    'totals' => $totals
                ]]);
            }
            return $totals;
        } finally {
            if ($locked) {
                flock($lock, LOCK_UN);
            }
            if ($lock) {
                fclose($lock);
            }
        }
    }

    private function collect(int $contextId, int $submissionId, array $fileIds): array
    {
        $filter = [STATISTICS_DIMENSION_CONTEXT_ID => $contextId];
        $abstract = $this->application->getMetrics(null, [], $filter + [
            STATISTICS_DIMENSION_ASSOC_TYPE => ASSOC_TYPE_SUBMISSION,
            STATISTICS_DIMENSION_ASSOC_ID => $submissionId
        ]);
        $totals = ['abstractViews' => $abstract[0][STATISTICS_METRIC] ?? 0, 'fileViews' => []];
        if (!$fileIds) {
            return $totals;
        }
        $rows = $this->application->getMetrics(null, [STATISTICS_DIMENSION_ASSOC_ID], $filter + [
            STATISTICS_DIMENSION_ASSOC_TYPE => ASSOC_TYPE_SUBMISSION_FILE,
            STATISTICS_DIMENSION_ASSOC_ID => $fileIds
        ]);
        foreach ((array) $rows as $row) {
            $fileId = $row[STATISTICS_DIMENSION_ASSOC_ID];
            // Match getPrimaryMetricByAssoc: the first returned metric, not a sum of types.
            if (!array_key_exists($fileId, $totals['fileViews'])) {
                $totals['fileViews'][$fileId] = $row[STATISTICS_METRIC] ?? 0;
            }
        }
        return $totals;
    }

    /**
     * OJS 3.3 FileLoader moves processed logs into archive after writing metrics.
     * Missing directories or active processing disable caching conservatively.
     */
    private function getImportGeneration(): ?string
    {
        $archive = $this->usageStatsPath . '/archive';
        $processing = $this->usageStatsPath . '/processing';
        clearstatcache(true, $archive);
        if (!is_dir($archive) || !is_readable($processing)) {
            return null;
        }
        $files = glob($processing . '/*');
        if ($files === false || $files) {
            return null;
        }
        $stat = @stat($archive);
        // PHP stat timestamps have one-second resolution. Wait for a quiet interval
        // so two imports in the same second cannot reuse an earlier generation.
        if ($stat === false || max($stat['mtime'], $stat['ctime']) >= time() - 1) {
            return null;
        }
        return implode(':', [$stat['ino'], $stat['mtime'], $stat['ctime']]);
    }
}

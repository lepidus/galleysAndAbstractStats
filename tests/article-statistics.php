<?php

require dirname(__DIR__) . '/ArticleStatistics.inc.php';
define('OJS_METRIC_TYPE_COUNTER', 'ojs::counter');
foreach (['STATISTICS_DIMENSION_CONTEXT_ID', 'STATISTICS_DIMENSION_ASSOC_TYPE', 'STATISTICS_DIMENSION_ASSOC_ID', 'STATISTICS_METRIC', 'ASSOC_TYPE_SUBMISSION', 'ASSOC_TYPE_SUBMISSION_FILE'] as $constant) {
    define($constant, $constant);
}

class TestApplication
{
    public $queries = [];
    public $abstract = 37;
    public $files = [10 => 11, 11 => 22];

    public function getMetrics($metric, $columns, $filter)
    {
        if ($metric !== null || $filter[STATISTICS_DIMENSION_CONTEXT_ID] !== 20) {
            throw new Exception('Context/default metric contract changed');
        }
        $this->queries[] = [$columns, $filter];
        if ($filter[STATISTICS_DIMENSION_ASSOC_TYPE] === ASSOC_TYPE_SUBMISSION) {
            return [[STATISTICS_METRIC => $this->abstract]];
        }
        $rows = [];
        foreach ($filter[STATISTICS_DIMENSION_ASSOC_ID] as $id) {
            if (isset($this->files[$id])) {
                $rows[] = [STATISTICS_DIMENSION_ASSOC_ID => $id, STATISTICS_METRIC => $this->files[$id]];
                // A second metric must not be summed into the primary metric.
                $rows[] = [STATISTICS_DIMENSION_ASSOC_ID => $id, STATISTICS_METRIC => 999];
            }
        }
        return $rows;
    }
}

class TestContext
{
    public $metric = 'ojs::counter';
    public function getId()
    {
        return 20;
    }
    public function getDefaultMetricType()
    {
        return $this->metric;
    }
    public function getMetricTypes()
    {
        return ['ojs::counter', 'otherMetric'];
    }
}

class TestPublication
{
    public $id = 2;
    public function getId()
    {
        return $this->id;
    }
    public function getData($key)
    {
        return 99;
    }
}

class TestCacheManager
{
    public $entries = [];
    public function getFileCache($context, $key, $fallback)
    {
        return new TestCache($this, $key);
    }
}

class TestCache
{
    private $manager;
    private $key;
    public function __construct($manager, $key)
    {
        $this->manager = $manager;
        $this->key = $key;
    }
    public function getCache($key)
    {
        return $this->manager->entries[$this->key][$key] ?? null;
    }
    public function setEntireCache($value)
    {
        $this->manager->entries[$this->key] = $value;
    }
}

function check($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

$path = sys_get_temp_dir() . '/galleys-stats-' . uniqid();
mkdir($path);
mkdir($path . '/archive');
mkdir($path . '/processing');
$app = new TestApplication();
$cache = new TestCacheManager();
$context = new TestContext();
$publication = new TestPublication();
$stats = new ArticleStatistics($app, $cache, $path);
// ctime is not settable; let the freshly created archive become quiescent.
sleep(2);
try {
    $actual = $stats->get($context, $publication, [11, 10, 10, 0, null, 12]);
    check($actual === ['abstractViews' => 37, 'fileViews' => [10 => 11, 11 => 22]], 'Totals/duplicate files/zero/primary metric');
    check(count($app->queries) === 2, 'N+1 query count');
    check($app->queries[1][1][STATISTICS_DIMENSION_ASSOC_ID] === [10, 11, 12], 'File IDs, not galley IDs');
    check($stats->get($context, $publication, [12, 11, 10]) === $actual, 'Warm cache totals');
    check(count($app->queries) === 2, 'Warm cache queried metrics');
    $app->abstract = 38;
    touch($path . '/archive', time());
    sleep(2);
    check($stats->get($context, $publication, [10, 11, 12])['abstractViews'] === 38, 'Import did not invalidate cache');
    check(count($app->queries) === 4, 'Import did not recompute');
    file_put_contents($path . '/processing/log', 'active');
    $before = $cache->entries;
    $app->abstract = 39;
    check($stats->get($context, $publication, [10, 11, 12])['abstractViews'] === 39, 'Active import used stale cache');
    check($before === $cache->entries, 'Active import persisted partial results');
    unlink($path . '/processing/log');
    $context->metric = 'otherMetric';
    check($stats->get($context, $publication, [10])['abstractViews'] === 39, 'Changed metric reused cache');
    check(count($app->queries) === 8, 'Metric change did not recompute');
    $context->metric = 'ojs::counter';
    $publication->id = 1;
    check($stats->get($context, $publication, []) === ['abstractViews' => 39, 'fileViews' => []], 'Publication without files');
    check(count($app->queries) === 9, 'No-file article queried file metrics');
    check($stats->get($context, $publication, [])['abstractViews'] === 39, 'No-file warm totals');
    check(count($app->queries) === 9, 'No-file warm cache missed');
    $app->abstract = 40;
    foreach ($cache->entries as &$entry) {
        $entry['statistics']['created'] = time() - 86401;
    }
    unset($entry);
    check($stats->get($context, $publication, [])['abstractViews'] === 40, 'Expired totals');
    check(count($app->queries) === 10, 'Expired entry not refreshed');
    rmdir($path . '/archive');
    $before = $cache->entries;
    $app->abstract = 41;
    check($stats->get($context, $publication, [])['abstractViews'] === 41, 'Missing archive used cache');
    check($cache->entries === $before, 'Missing archive wrote cache');
    echo "OK: totals, batched queries, daily cache, import invalidation, metric/publication isolation, expiration and conservative fallback\n";
} finally {
    @unlink($path . '/processing/log');
    @rmdir($path . '/processing');
    @rmdir($path . '/archive');
    @rmdir($path);
}

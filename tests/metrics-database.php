<?php

/**
 * Run the unmodified OJS 3.3 metrics query builder against a real SQL database.
 * PKP_CORE points to a read-only core checkout; DB_DSN/DB_USER/DB_PASSWORD select
 * an isolated disposable database. Only the PDO execution adapter is substituted.
 */
$core = getenv('PKP_CORE');
if (!$core || !is_file($core . '/lib/pkp/classes/statistics/PKPMetricsDAO.inc.php')) {
    throw new Exception('PKP_CORE must point to an OJS 3.3 checkout');
}
function import($class)
{
}
require $core . '/lib/pkp/classes/statistics/PKPStatisticsHelper.inc.php';
define('ASSOC_TYPE_SUBMISSION', 1);
define('ASSOC_TYPE_SUBMISSION_FILE', 2);
define('OJS_METRIC_TYPE_COUNTER', 'ojs::counter');

class DAO
{
    public $pdo;
    public $queries = 0;
    public function retrieve($sql, $params = [])
    {
        $this->queries++;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }
}
require $core . '/lib/pkp/classes/statistics/PKPMetricsDAO.inc.php';
require dirname(__DIR__) . '/ArticleStatistics.inc.php';

class DatabaseApplication
{
    public $dao;
    public $metricType = 'ojs::counter';
    public function getMetrics($metricType, $columns, $filter)
    {
        return $this->dao->getMetrics($this->metricType, $columns, $filter);
    }
}
class DatabaseContext
{
    public function getId()
    {
        return 20;
    }
    public function getDefaultMetricType()
    {
        return 'ojs::counter';
    }
    public function getMetricTypes()
    {
        return ['ojs::counter'];
    }
}
class DatabasePublication
{
    public function getId()
    {
        return 7;
    }
    public function getData($key)
    {
        return 99;
    }
}
class NoCacheManager
{
    public function getFileCache($context, $key, $fallback)
    {
        return new NoCache();
    }
}
class NoCache
{
    public function getCache($key)
    {
        return null;
    }
    public function setEntireCache($contents)
    {
        throw new Exception('Unexpected cache write');
    }
}
function ensure($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

$pdo = new PDO(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE metrics (context_id INT, assoc_type INT, assoc_id INT, metric_type VARCHAR(64), metric INT)');
$insert = $pdo->prepare('INSERT INTO metrics (context_id, assoc_type, assoc_id, metric_type, metric) VALUES (?, ?, ?, ?, ?)');
foreach ([
    [20, 1, 99, 'ojs::counter', 30], [20, 1, 99, 'ojs::counter', 7],
    [20, 2, 10, 'ojs::counter', 8], [20, 2, 10, 'ojs::counter', 3],
    [20, 2, 11, 'ojs::counter', 22], [20, 2, 13, 'ojs::counter', 0],
    [21, 2, 10, 'ojs::counter', 900], [20, 1, 10, 'ojs::counter', 800],
    [20, 2, 10, 'custom', 700], [20, 1, 99, 'custom', 55]
] as $row) {
    $insert->execute($row);
}
try {
    $dao = new PKPMetricsDAO();
    $dao->pdo = $pdo;
    $app = new DatabaseApplication();
    $app->dao = $dao;
    $context = new DatabaseContext();
    $publication = new DatabasePublication();
    $statistics = new ArticleStatistics($app, new NoCacheManager(), '/nonexistent/usageStats');
    foreach ([[10, 11, 12, 13, 10, null], [10], []] as $ids) {
        $oldAbstract = $dao->getMetrics('ojs::counter', [], ['context_id' => 20, 'assoc_type' => 1, 'assoc_id' => 99]);
        $oldFiles = [];
        foreach (array_filter($ids) as $id) {
            $old = $dao->getMetrics('ojs::counter', [], ['context_id' => 20, 'assoc_type' => 2, 'assoc_id' => $id]);
            $oldFiles[$id] = $old[0]['metric'] ?? 0;
        }
        $before = $dao->queries;
        $actual = $statistics->get($context, $publication, $ids);
        ensure((string) $actual['abstractViews'] === (string) $oldAbstract[0]['metric'], 'Abstract total diverged');
        foreach ($oldFiles as $id => $expected) {
            ensure((string) ($actual['fileViews'][$id] ?? 0) === (string) $expected, 'File total diverged: ' . $id);
        }
        ensure($dao->queries - $before === ($ids ? 2 : 1), 'Batched query count changed');
    }
    $app->metricType = 'custom';
    $actual = $statistics->get($context, $publication, [10]);
    ensure((string) $actual['abstractViews'] === '55' && (string) $actual['fileViews'][10] === '700', 'Custom metric was forced to counter');
    echo 'OK: core PKPMetricsDAO on ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . '; totals match per-file reports, context/assoc/metric isolation and <=2 batched queries' . PHP_EOL;
} finally {
    $pdo->exec('DROP TABLE metrics');
}

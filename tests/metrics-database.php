<?php

/** Real OJS 3.4 service/query builders and Galley::getViews(), disposable SQL only. */

namespace APP\core {
    class Application
    {
        public const ASSOC_TYPE_SUBMISSION = 1;
        public const ASSOC_TYPE_SUBMISSION_FILE = 2;
        public const ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER = 3;
        public static function get(): self
        {
            return new self();
        }
        public function getRequest(): self
        {
            return $this;
        }
        public function getContext(): self
        {
            return $this;
        }
        public function getId(): int
        {
            return 20;
        }
    }
    class Services
    {
        public static $stats;
        public static function get($name)
        {
            return self::$stats;
        }
    }
}

namespace PKP\plugins {
    class Hook
    {
        public static array $called = [];
        public static bool $mutateAbstract = false;
        public static function call($name, $args): bool
        {
            self::$called[] = $name;
            if (self::$mutateAbstract && $name === 'StatsPublication::getTotalsByType::queryBuilder') {
                $args[0]->after(date('Y-m-d', strtotime('yesterday')));
            }
            return false;
        }
    }
}

namespace {
    define('PKP_STRICT_MODE', true);
    $core = getenv('PKP_CORE');
    require getenv('PKP_VENDOR') . '/autoload.php';
    spl_autoload_register(function ($class) use ($core) {
        foreach (['APP\\' => $core . '/classes/', 'PKP\\' => $core . '/lib/pkp/classes/'] as $prefix => $path) {
            if (strpos($class, $prefix) === 0) {
                $file = $path . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });
    require dirname(__DIR__) . '/ArticleStatistics.php';
    class RealStatsService extends \PKP\services\PKPStatsPublicationService
    {
    }
    class TestGalley extends \PKP\galley\Galley
    {
        public function getGalleyLabel()
        {
            return $this->getData('label');
        }
    }
    function ensure($condition, string $message): void
    {
        if (!$condition) {
            throw new \Exception($message);
        }
    }
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $capsule->addConnection([
        'driver' => getenv('DB_DRIVER'), 'host' => getenv('DB_HOST'),
        'database' => 'stats', 'username' => 'stats', 'password' => 'disposable-stats',
        'charset' => 'utf8', 'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    \Illuminate\Support\Facades\Facade::setFacadeApplication($capsule->getContainer());
    $capsule->getContainer()->instance('db', $capsule->getDatabaseManager());
    $db = $capsule->getConnection();
    $db->statement('CREATE TABLE metrics_submission (context_id INT, submission_id INT, submission_file_id INT, representation_id INT, assoc_type INT, file_type INT, date DATE, metric INT)');
    $earliest = \APP\statistics\StatisticsHelper::STATISTICS_EARLIEST_DATE;
    $yesterday = date('Y-m-d', strtotime('yesterday'));
    foreach ([
        [20, 99, null, null, 1, null, $earliest, 7],
        [20, 99, null, null, 1, null, $yesterday, 30],
        [20, 99, 10, 100, 2, 1, $earliest, 8],
        [20, 99, 10, 100, 2, 1, $yesterday, 3],
        [20, 99, 11, 101, 2, 1, $yesterday, 22],
        [20, 88, 10, 111, 3, 2, $yesterday, 5],
        [21, 99, 10, 100, 2, 1, $yesterday, 900],
        [20, 99, 10, 100, 2, 1, date('Y-m-d'), 800],
        [20, 99, 10, 100, 2, 1, '1900-01-01', 700],
    ] as $row) {
        $db->insert('INSERT INTO metrics_submission VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $row);
    }
    $service = new RealStatsService();
    \APP\core\Services::$stats = $service;
    $galleys = [];
    foreach ([10, 11, 10, null, 12] as $i => $fileId) {
        $galley = new TestGalley();
        $galley->setData('submissionFileId', $fileId);
        $galley->setData('label', 'Galley ' . $i);
        $galleys[] = $galley;
    }
    $db->enableQueryLog();
    $oldAbstract = $service->getTotalsByType(99, 20, null, null)['abstract'];
    $old = [];
    foreach ($galleys as $galley) {
        $old[] = ['galleyLabel' => $galley->getGalleyLabel(), 'galleyViews' => $galley->getViews()];
    }
    $oldCount = count($db->getQueryLog());
    $db->flushQueryLog();
    $newAbstract = $service->getTotalsByType(99, 20, null, null)['abstract'];
    $new = (new \APP\plugins\generic\galleysAndAbstractStats\ArticleStatistics())
        ->getGalleyViews($service, $galleys, 20);
    $newCount = count($db->getQueryLog());
    ensure($old == $new && $oldAbstract === $newAbstract && $newAbstract === 37, 'Counter parity');
    ensure(array_column($new, 'galleyViews') == [16, 22, 16, 0, 0], 'Dates/context/all-file semantics');
    ensure($oldCount === 5 && $newCount === 2, 'Expected 5 -> 2 queries');
    ensure(in_array('StatsPublication::getTotalsByType::queryBuilder', \PKP\plugins\Hook::$called), 'Abstract service hook preserved');
    \PKP\plugins\Hook::$mutateAbstract = true;
    ensure($service->getTotalsByType(99, 20, null, null)['abstract'] === 30, 'Abstract hook mutation honored');
    echo 'PASS ' . getenv('DB_DRIVER') . ': core service and Galley parity, 5 -> 2 queries, dates, context, old/shared/missing files, abstract hook' . PHP_EOL;
}

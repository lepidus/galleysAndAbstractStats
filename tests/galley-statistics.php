<?php

namespace APP\statistics {
    class StatisticsHelper
    {
        public const STATISTICS_EARLIEST_DATE = '2001-01-01';
        public const STATISTICS_DIMENSION_SUBMISSION_FILE_ID = 'submission_file_id';
    }
}

namespace PKP\plugins {
    class GenericPlugin
    {
        public function getSetting($contextId, $name)
        {
            return 'Daily statistics';
        }
        public function getTemplateResource($name)
        {
            return $name;
        }
    }
}

namespace APP\core {
    class Application
    {
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

namespace APP\facades {
    class Repo
    {
        public static $collector;
        public static function galley()
        {
            return self::$collector;
        }
    }
}

namespace {
    require dirname(__DIR__) . '/ArticleStatistics.php';
    require dirname(__DIR__) . '/GalleysAndAbstractStatsPlugin.php';

    class GalleyFixture
    {
        private $fileId;
        private string $label;
        public function __construct($fileId, string $label)
        {
            $this->fileId = $fileId;
            $this->label = $label;
        }
        public function getData($key)
        {
            return $this->fileId;
        }
        public function getGalleyLabel()
        {
            return $this->label;
        }
    }

    class StatsFixture
    {
        public array $filters = [];
        public int $queries = 0;
        public function getTotalsByType($submissionId, $contextId, $start, $end): array
        {
            if ($submissionId !== 99 || $contextId !== 20 || $start !== null || $end !== null) {
                throw new \Exception('Abstract service arguments changed');
            }
            return ['abstract' => 37];
        }
        public function getQueryBuilder(array $filters): self
        {
            $this->filters = $filters;
            $this->queries++;
            return $this;
        }
        public function getSum(array $groupBy): self
        {
            if ($groupBy !== ['submission_file_id']) {
                throw new \Exception('Wrong grouping');
            }
            return $this;
        }
        public function get(): array
        {
            return [(object) ['submission_file_id' => 10, 'metric' => '11']];
        }
    }

    $stats = new StatsFixture();
    $helper = new \APP\plugins\generic\galleysAndAbstractStats\ArticleStatistics();
    $galleys = [new GalleyFixture(10, 'PDF'), new GalleyFixture(10, 'Shared'),
        new GalleyFixture(null, 'Remote'), new GalleyFixture(11, 'No views')];
    $actual = $helper->getGalleyViews($stats, (function () use ($galleys) {
        yield from $galleys;
    })(), 20);
    $expected = [['galleyLabel' => 'PDF', 'galleyViews' => '11'],
        ['galleyLabel' => 'Shared', 'galleyViews' => '11'],
        ['galleyLabel' => 'Remote', 'galleyViews' => 0],
        ['galleyLabel' => 'No views', 'galleyViews' => 0]];
    if ($actual !== $expected || $stats->queries !== 1 || $stats->filters !== [
        'dateStart' => '2001-01-01', 'dateEnd' => date('Y-m-d', strtotime('yesterday')),
        'contextIds' => [20], 'submissionFileIds' => [10, 11],
    ]) {
        throw new \Exception('Counter or filter parity failed');
    }
    $helper->getGalleyViews($stats, [], 20);
    $helper->getGalleyViews($stats, [new GalleyFixture(null, 'Remote')], 20);
    if ($stats->queries !== 1) {
        throw new \Exception('Empty/remote galleys must not query metrics');
    }
    class CollectorFixture
    {
        public array $galleys;
        public function getCollector(): self
        {
            return $this;
        }
        public function filterByPublicationIds($ids): self
        {
            if ($ids !== ['publicationIds' => 7]) {
                throw new \Exception('Publication selection changed');
            }
            return $this;
        }
        public function getMany(): array
        {
            return $this->galleys;
        }
    }
    class PublicationFixture
    {
        public function getId(): int
        {
            return 7;
        }
        public function getData($key): int
        {
            return 99;
        }
    }
    class TemplateFixture
    {
        public array $assigned = [];
        public function getTemplateVars($name): PublicationFixture
        {
            return new PublicationFixture();
        }
        public function assign(array $vars): void
        {
            $this->assigned = $vars;
        }
        public function fetch($template): string
        {
            return json_encode($this->assigned);
        }
    }
    $collector = new CollectorFixture();
    $collector->galleys = $galleys;
    \APP\facades\Repo::$collector = $collector;
    \APP\core\Services::$stats = $stats;
    $template = new TemplateFixture();
    $output = 'Before';
    $params = [null, &$template, &$output];
    $plugin = new \APP\plugins\generic\galleysAndAbstractStats\GalleysAndAbstractStatsPlugin();
    $return = $plugin->viewDataStatement('Templates::Article::Details', $params);
    $expectedVars = ['abstractViews' => 37, 'galleysViews' => $expected, 'statsFooterText' => 'Daily statistics'];
    if ($return !== false || $template->assigned !== $expectedVars || $output !== 'Before' . json_encode($expectedVars)) {
        throw new \Exception('Article details callback changed its template contract');
    }
    echo "PASS article details callback, assigned values and appended template output\n";
    echo "PASS galley totals, labels, order, shared/missing files, context/date filters, one batch\n";
}

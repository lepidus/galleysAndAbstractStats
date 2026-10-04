# Article statistics in OJS 3.3

Version 1.0.1.2 preserves the publication's galley labels, ordering, file identifiers
and primary metric selection. It retrieves abstract totals in one report and all
unique galley file totals in a second report, using the core reporting API and the
current journal filter. It does not sum different metric types.

For the native `ojs::counter` metric, totals are cached for at most 24 hours in the
OJS file cache. The cache key includes the journal, effective default and available
metrics, publication, submission and unique file identifiers. Concurrent cold
requests for the same article share a filesystem lock; different articles remain
independent. A cache directory that cannot be written falls back to live reports.

The OJS 3.3 FileLoader archives each usage log after writing its metrics. Archive
inode and modification/change times invalidate these totals after imports. Caching
is disabled while processing contains files or the archive has changed within the
last two seconds, because PHP stat timestamps have one-second resolution. This
also prevents caching partial imports or confusing two imports in the same second.
Missing or inaccessible loader directories disable caching conservatively.

Other default metrics always use live batched reports: they do not have the native
usageStats import lifecycle. Direct database edits and custom importers that bypass
FileLoader require clearing the OJS data cache; otherwise native counter values
expire after 24 hours. File cache and lock files reside in the standard OJS cache
directory and follow its permissions and maintenance policy.

Run the focused behavioral suite with `php tests/article-statistics.php` in PHP
7.4 and PHP 8.2. Production validation must compare abstract and per-galley values
with the previous plugin on the same publications and effective reporting metric,
then prove a second request avoids metrics queries.

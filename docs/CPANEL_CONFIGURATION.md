# CHAMA WISE — cPanel Configuration

This document covers the specific cPanel settings CHAMA WISE needs once a real hosting account is
available. It is written ahead of having one (Part 27 permits documenting the intended
configuration while flagging what must still be verified against the real account) — the cron
entry and document-root guidance below have **not yet been verified against an actual cPanel
account** and must be checked before go-live.

## Cron: the Laravel scheduler

Laravel's scheduler (`bootstrap/app.php`'s `withSchedule()`) defines what runs and when —
`contributions:assess` daily at 00:10 Africa/Nairobi, for example (see
`app/Console/Commands/AssessContributionObligations.php`). cPanel's own cron UI should only ever
be given **one** entry: a call to `schedule:run` every minute, which then dispatches whatever is
actually due. Do not add per-task cron entries — add new jobs in `withSchedule()` instead.

In cPanel's "Cron Jobs" page, set:

```
* * * * * cd /home/<user>/<path-to-app> && php artisan schedule:run >> /dev/null 2>&1
```

Per Part 27's shared-hosting guidance, cron on shared hosting cannot be assumed to fire at the
exact minute requested — this is exactly why deadline/overdue logic is computed from stored
timestamps compared against the current instant (`ObligationGenerator::refreshStatus`), not by
relying on a job executing at a precise second. A job that runs a few minutes late still produces
the correct result.

## Document root

Laravel's `public/` directory should be the web server's document root, not the repository root —
never upload the whole project into `public_html` directly. If cPanel does not allow pointing the
domain's document root at `backend/public`, the fallback is to leave `public_html` as the document
root and have it contain only the contents of `backend/public`, with an adjusted `index.php` that
points `require` at the actual application path outside the public web root. This still needs to
be verified against the real cPanel account's capabilities before launch; see
`docs/SHARED_HOSTING_DEPLOYMENT.md` for the broader deployment picture once that document exists.

## Queues

No queued jobs exist yet (Phase 1–2 are synchronous). When one is introduced, the `database`
queue driver is the right default for shared hosting (Part 27) — processed via
`schedule:run`-dispatched `queue:work --stop-when-empty` calls, not a long-running `queue:work`
daemon, which shared hosting typically cannot keep alive.

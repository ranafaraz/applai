<?php

use Illuminate\Support\Facades\Schedule;

// Sync IMAP inboxes for all active accounts every 15 minutes
Schedule::command('crm:sync-inboxes')->everyFifteenMinutes();

// Process due follow-up emails every 30 minutes (legacy runner; kept as fallback)
Schedule::command('crm:process-follow-ups')->everyThirtyMinutes()->withoutOverlapping();

// Dispatch auto-send follow-ups every 5 minutes with overlap guard
Schedule::command('crm:dispatch-due-followups')->everyFiveMinutes()->withoutOverlapping();

// Cancel pending follow-ups for opportunities that received a reply (safety-net sweep)
Schedule::command('crm:cancel-followups-on-reply')->everyTenMinutes()->withoutOverlapping();

// Queue scheduled emails every 5 minutes (dispatches crm:send-scheduled emails whose scheduled_at <= now())
Schedule::command('crm:send-scheduled')->everyFiveMinutes()->withoutOverlapping();

// Reset emails stuck in 'sending' (crashed worker/timeout) every 5 minutes
Schedule::command('crm:unstick-sending-emails')->everyFiveMinutes();

// Reset daily email send counters at midnight
Schedule::command('crm:reset-daily-counters')->dailyAt('00:00');

// Publish scheduled social posts every 5 minutes (only approved posts are dispatched)
Schedule::command('social:publish-due-posts')->everyFiveMinutes();

// Sync LinkedIn analytics: recent posts every hour, full sweep daily at 03:00
Schedule::command('social:sync-linkedin-analytics --hours=72')->hourly();
Schedule::command('social:sync-linkedin-analytics --hours=720')->dailyAt('03:00');

// Hard-delete tenants whose 30-day deletion grace period has elapsed
Schedule::command('tenants:purge-deleted')->dailyAt('02:00');

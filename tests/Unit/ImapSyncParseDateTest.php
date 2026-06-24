<?php

namespace Tests\Unit;

use App\Services\ImapSyncService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression test for the parseDate() UTC normalisation fix.
 *
 * Before the fix, a Date header with a non-UTC offset (e.g. "08:58 -0500")
 * would have its local-time digits stored verbatim, making the CRM show the
 * reply ~5 hours earlier than it actually arrived.
 */
class ImapSyncParseDateTest extends TestCase
{
    private function parseDate(mixed $date): ?Carbon
    {
        $service = $this->getMockBuilder(ImapSyncService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $ref    = new ReflectionClass(ImapSyncService::class);
        $method = $ref->getMethod('parseDate');
        $method->setAccessible(true);

        return $method->invoke($service, $date);
    }

    public function test_us_central_date_header_is_normalised_to_utc(): void
    {
        // "08:58:11 -0500" is the same moment as "13:58:11 UTC"
        $result = $this->parseDate('Wed, 24 Jun 2026 08:58:11 -0500');

        $this->assertNotNull($result);
        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-06-24 13:58:11', $result->format('Y-m-d H:i:s'),
            'US Central offset (-0500) must be converted to UTC — stored value should be 13:58:11 not 08:58:11'
        );
    }

    public function test_pakistan_standard_time_date_header_is_normalised_to_utc(): void
    {
        // "18:58:11 +0500" is the same moment as "13:58:11 UTC"
        $result = $this->parseDate('Wed, 24 Jun 2026 18:58:11 +0500');

        $this->assertNotNull($result);
        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-06-24 13:58:11', $result->format('Y-m-d H:i:s'));
    }

    public function test_utc_date_header_is_unchanged(): void
    {
        $result = $this->parseDate('Wed, 24 Jun 2026 13:58:11 +0000');

        $this->assertNotNull($result);
        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-06-24 13:58:11', $result->format('Y-m-d H:i:s'));
    }

    public function test_carbon_instance_with_non_utc_timezone_is_normalised(): void
    {
        $pkCarbon = Carbon::createFromFormat('Y-m-d H:i:s', '2026-06-24 18:58:11', 'Asia/Karachi');

        $result = $this->parseDate($pkCarbon);

        $this->assertNotNull($result);
        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-06-24 13:58:11', $result->format('Y-m-d H:i:s'));
    }

    public function test_null_input_returns_null(): void
    {
        $this->assertNull($this->parseDate(null));
    }

    public function test_timeline_ordering_send_before_reply(): void
    {
        $sentAt    = Carbon::parse('2026-06-24 13:58:08 UTC');
        // Reply Date header: US Central = 08:58:11 -0500 → 13:58:11 UTC
        $replyHeader = 'Wed, 24 Jun 2026 08:58:11 -0500';
        $replyAt   = $this->parseDate($replyHeader);

        $this->assertNotNull($replyAt);
        $this->assertTrue(
            $replyAt->gt($sentAt),
            "Reply ({$replyAt}) should be AFTER send ({$sentAt}) in timeline order"
        );
        $this->assertLessThanOrEqual(
            10,
            $replyAt->diffInSeconds($sentAt),
            'Auto-reply arrived within 10 seconds of send'
        );
    }
}

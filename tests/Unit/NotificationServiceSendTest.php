<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\NotificationService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationServiceSendTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function send_notification_passes_uuid_id_type_and_data_to_relation_create(): void
    {
        $user = Mockery::mock(User::class);
        $relation = Mockery::mock();
        $payload = null;

        $user->shouldReceive('notifications')->once()->andReturn($relation);

        $relation->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) use (&$payload): bool {
                $payload = $attrs;

                return true;
            }));

        (new NotificationService())->sendNotification($user, 'order', [
            'type' => 'order',
            'title' => 'Hello',
        ]);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('id', $payload);
        $this->assertSame(36, strlen((string) $payload['id']));
        $this->assertSame('order', $payload['type']);
        $this->assertSame('Hello', $payload['data']['title'] ?? null);
    }
}

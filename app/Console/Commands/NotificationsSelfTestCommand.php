<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationsSelfTestCommand extends Command
{
    protected $signature = 'notifications:self-test
        {user? : User ID or email (default: first user in database)}';

    protected $description = 'Insert one in-app notification for a user and print DB + API-style counts (verifies UUID id + notifications table).';

    public function handle(NotificationService $notificationService): int
    {
        $arg = $this->argument('user');
        $user = null;
        if ($arg !== null && $arg !== '') {
            if (is_numeric($arg)) {
                $user = User::query()->find((int) $arg);
            } else {
                $user = User::query()->where('email', $arg)->first();
            }
        } else {
            $user = User::query()->orderBy('id')->first();
        }

        if (! $user) {
            $this->error('No user found. Pass a user id or email, or seed users first.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('notifications')) {
            $this->error('Table `notifications` does not exist. Run migrations.');

            return self::FAILURE;
        }

        $before = (int) DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();

        $notificationService->sendNotification($user, 'diagnostic', [
            'type' => 'system',
            'title_ar' => 'اختبار الإشعارات',
            'title_en' => 'Notification self-test',
            'message_ar' => 'إذا رأيت هذا، فإن الإدراج في جدول notifications يعمل.',
            'message_en' => 'If you see this, inserts into the notifications table work.',
        ]);

        $after = (int) DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();

        $last = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        $this->info("User #{$user->id} ({$user->email})");
        $this->info("notifications count: {$before} -> {$after}");
        if ($last) {
            $this->line('Last row id (UUID): ' . (string) $last->id);
            $this->line('Last row type: ' . (string) $last->type);
        }

        if ($after <= $before) {
            $this->error('Count did not increase — insert failed (check DB logs / SQL mode).');

            return self::FAILURE;
        }

        $this->info('OK — GET /api/user/notifications with this user\'s token should include the new row.');

        return self::SUCCESS;
    }
}

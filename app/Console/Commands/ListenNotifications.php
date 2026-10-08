<?php

namespace App\Console\Commands;

use App\Events\NotificationCreated;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:listen-notifications')]
#[Description('Listen for notification changes from PostgreSQL')]
class ListenNotifications extends Command
{
    public function handle()
    {
        $config = config('database.connections.pgsql');

        $connectionString = sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s',
            $config['host'],
            $config['port'] ?? 5432,
            $config['database'],
            $config['username'],
            $config['password']
        );

        $connection = pg_connect($connectionString);

        if (!$connection) {
            $this->error('PostgreSQL connection failed.');

            return self::FAILURE;
        }

        // Listen for PostgreSQL notification events
        pg_query($connection, 'LISTEN notification_created');

        $this->info('Listening for notification changes...');
        $this->info('Waiting for PostgreSQL notifications...');

        while (true) {
            $notify = pg_get_notify($connection, PGSQL_ASSOC);

            if ($notify === false) {
                usleep(100000);
                continue;
            }

            try {
                // PostgreSQL payload -> array
                $payload = json_decode(
                    $notify['payload'],
                    true
                );

                if (!is_array($payload)) {
                    $this->error('Invalid notification payload.');

                    continue;
                }

                $notificationId = isset($payload['notification_id'])
                    ? (int) $payload['notification_id']
                    : null;

                $userId = isset($payload['user_id'])
                    ? (int) $payload['user_id']
                    : null;

                $this->info(
                    'Notification created: ' .
                    'ID = ' . ($notificationId ?? 'N/A') .
                    ', User ID = ' . ($userId ?? 'N/A')
                );

                /*
                |--------------------------------------------------------------------------
                | Broadcast NotificationCreated Event
                |--------------------------------------------------------------------------
                */

                if (!$notificationId || !$userId) {
                    $this->error(
                        'notification_id or user_id is missing.'
                    );

                    continue;
                }

                event(new NotificationCreated(
                    $notificationId,
                    $userId,

                    isset($payload['type_id'])
                        ? (int) $payload['type_id']
                        : null,

                    isset($payload['order_id'])
                        ? (int) $payload['order_id']
                        : null,

                    isset($payload['payment_id'])
                        ? (int) $payload['payment_id']
                        : null,

                    isset($payload['shipment_id'])
                        ? (int) $payload['shipment_id']
                        : null,

                    isset($payload['return_id'])
                        ? (int) $payload['return_id']
                        : null,

                    $payload['title'] ?? null,
                    $payload['message'] ?? null,
                    $payload['action_url'] ?? null
                ));

                $this->info(
                    'NotificationCreated event dispatched to Reverb. ' .
                    'User ID = ' . $userId
                );
            } catch (\Throwable $e) {
                $this->error(
                    'Notification broadcast failed: ' .
                    $e->getMessage()
                );

                $this->error(
                    'File: ' .
                    $e->getFile() .
                    ':' .
                    $e->getLine()
                );
            }

            usleep(100000);
        }

        pg_close($connection);

        return self::SUCCESS;
    }
}

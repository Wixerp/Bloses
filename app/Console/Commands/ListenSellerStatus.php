<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Events\SellerStatusChanged;

#[Signature('app:listen-seller-status')]
#[Description('Listen for seller status changes from PostgreSQL')]
class ListenSellerStatus extends Command
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

        pg_query($connection, 'LISTEN seller_status_changed');

        $this->info('Listening for seller status changes...');
        $this->info('Waiting for PostgreSQL notifications...');

        while (true) {

            $notify = pg_get_notify($connection, PGSQL_ASSOC);

            if ($notify !== false) {

                $payload = json_decode($notify['payload'], true);

                if (!$payload) {
                    $this->error('Invalid notification payload.');
                    continue;
                }

                $this->info(
                    'Seller status changed: ' .
                    'Seller ID = ' . $payload['seller_id'] .
                    ', User ID = ' . $payload['user_id'] .
                    ', New Status = ' . $payload['status_id']
                );

                // Event yahan baad mein dispatch karenge
                SellerStatusChanged::dispatch(
                    (int) $payload['seller_id'],
                    (int) $payload['user_id'],
                    isset($payload['old_status_id']) ? (int) $payload['old_status_id'] : null,
                    isset($payload['status_id']) ? (int) $payload['status_id'] : null
                );
            }

            usleep(100000);
        }

        pg_close($connection);

        return self::SUCCESS;
    }
}

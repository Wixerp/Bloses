
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sms_settings', function (Blueprint $table) {
            $table->id();

            // Email / SMTP
            $table->string('mail_driver')->default('smtp');
            $table->string('mail_host')->nullable();
            $table->unsignedInteger('mail_port')->default(587);
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_encryption')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->boolean('email_enabled')->default(true);

            // SMS gateway
            $table->string('sms_provider')->nullable();
            $table->text('sms_api_url')->nullable();
            $table->text('sms_api_key')->nullable();
            $table->string('sms_sender_id')->nullable();
            $table->boolean('sms_enabled')->default(false);

            // Notification preferences
            $table->jsonb('notification_events')->default(
                json_encode([
                    'registration' => ['email' => true, 'sms' => false],
                    'order_confirmation' => ['email' => true, 'sms' => false],
                    'order_status' => ['email' => true, 'sms' => false],
                    'password_reset' => ['email' => true, 'sms' => false],
                    'seller_notification' => ['email' => true, 'sms' => false],
                ])
            );

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sms_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('notification_deliveries')
            ->select('channel', 'provider_reference')
            ->whereNotNull('provider_reference')
            ->groupBy('channel', 'provider_reference')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new \LogicException(
                'Cannot enforce unique notification provider references per channel while duplicate non-null references exist. '
                .'Reconcile the affected delivery records before retrying this migration.'
            );
        }

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->unique(['channel', 'provider_reference'], 'notification_deliveries_channel_provider_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropUnique('notification_deliveries_channel_provider_reference_unique');
        });
    }
};

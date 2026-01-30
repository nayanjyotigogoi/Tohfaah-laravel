<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FreeGift;
use App\Models\User;

class BackfillFreeGiftSenderIds extends Command
{
    protected $signature = 'free-gifts:backfill-sender-id {--dry-run}';
    protected $description = 'Backfill sender_id for old free gifts using email match';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info(
            $dryRun
                ? 'Running in DRY RUN mode'
                : 'Running in LIVE mode'
        );

        $gifts = FreeGift::whereNull('sender_id')
            ->whereNotNull('sender_name')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($gifts as $gift) {
            $senderName = strtolower(trim($gift->sender_name));

            // Match sender_name with user email ONLY
            $user = User::whereRaw(
                'LOWER(email) = ?',
                [$senderName]
            )->first();

            if (!$user) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(
                    "Would assign sender_id={$user->id} to gift {$gift->id}"
                );
            } else {
                $gift->update([
                    'sender_id' => $user->id,
                ]);
                $updated++;
            }
        }

        $this->info('Backfill complete');
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}

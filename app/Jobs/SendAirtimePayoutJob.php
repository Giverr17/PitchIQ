<?php

namespace App\Jobs;

use App\Models\AirtimePayout;
use App\Services\AirtimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a single airtime payout to the provider.
 *
 * One job per winner. The admin's "Settle" request only writes the pending
 * AirtimePayout rows and dispatches these jobs, so it returns immediately
 * instead of blocking on up to ~30s of provider HTTP per winner.
 *
 * tries = 1 on purpose: the provider call is NOT idempotent (each send builds
 * a fresh request_id), so a blind queue retry could double-pay. A payout that
 * lands in 'failed' is meant to be retried deliberately by the admin clicking
 * Settle again — settleAndPay() lets failed records through.
 */
class SendAirtimePayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $payoutId)
    {
    }

    public function handle(AirtimeService $airtime): void
    {
        $payout = AirtimePayout::find($this->payoutId);

        // Record gone, or already settled / in-flight from an earlier dispatch.
        if (!$payout || $payout->status !== 'pending') {
            return;
        }

        // Mark in-flight before the HTTP call so a stray duplicate dispatch
        // sees a non-pending status and bails instead of sending twice.
        $payout->update(['status' => 'processing']);

        $result = $airtime->send($payout->phone, $payout->amount);

        $payout->update([
            'status' => $result['success'] ? 'success' : 'failed',
            'provider_reference' => $result['reference'],
            'notes' => $result['message'],
        ]);

        // Email the winner only if the airtime actually sent
        if ($result['success']) {
            try {
                $payout->load(['user', 'tournament']);
                if ($payout->user && $payout->user->email) {
                    \Illuminate\Support\Facades\Mail::to($payout->user->email)->send(
                        new \App\Mail\PayoutSentMail(
                            user: $payout->user,
                            amount: $payout->amount,
                            phone: $payout->phone,
                            tournamentName: $payout->tournament?->name ?? 'PitchIQ',
                            matchday: $payout->matchday ?? 0,
                            position: $payout->rank,
                        )
                    );
                }
            } catch (\Throwable $e) {
                // Email is non-critical — never let it undo a successful payout
                \Illuminate\Support\Facades\Log::warning('Payout email failed', [
                    'payout_id' => $payout->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Runs if handle() throws unexpectedly (e.g. DB down mid-update). The
     * provider may or may not have sent — leave a durable failed marker and
     * log it so the discrepancy is visible rather than silently stuck.
     */
    public function failed(\Throwable $e): void
    {
        $payout = AirtimePayout::find($this->payoutId);

        if ($payout && in_array($payout->status, ['pending', 'processing'], true)) {
            $payout->update([
                'status' => 'failed',
                'notes' => 'Payout job crashed: ' . $e->getMessage(),
            ]);
        }

        Log::error('SendAirtimePayoutJob failed', [
            'payout_id' => $this->payoutId,
            'error' => $e->getMessage(),
        ]);
    }
}

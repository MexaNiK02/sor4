<?php

namespace App\Services;

use App\Models\GameSession;
use Illuminate\Support\Facades\Http;
use Throwable;

class LiveQuizBroadcaster
{
    public function sessionUpdated(GameSession $session, string $event = 'session.updated'): void
    {
        $this->broadcast($session->id, [
            'event' => $event,
            'session_id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'phase' => $session->current_phase,
        ]);
    }

    public function answerReceived(GameSession $session, int $participantId): void
    {
        $this->broadcast($session->id, [
            'event' => 'answer.received',
            'session_id' => $session->id,
            'code' => $session->code,
            'participant_id' => $participantId,
        ]);
    }

    private function broadcast(int $sessionId, array $payload): void
    {
        $endpoint = config('services.livequiz_ws.hook');

        if (! $endpoint) {
            return;
        }

        try {
            Http::timeout(1)->post($endpoint, [
                'channel' => 'session:'.$sessionId,
                'payload' => $payload,
            ]);
        } catch (Throwable) {
            // The websocket server is optional during tests and local setup.
        }
    }
}

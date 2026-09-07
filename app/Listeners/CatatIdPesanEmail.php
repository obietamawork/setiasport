<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

/**
 * Menyimpan id pesan dari penyedia ke email_logs.
 *
 * Tanpa id ini, webhook status tidak punya cara mencocokkan "email mana yang
 * bounce", dan justru itulah yang membuat admin tahu kapan harus menyerah pada
 * email dan menindaklanjuti lewat WhatsApp (docs/03).
 */
class CatatIdPesanEmail
{
    public function handle(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-Log-Id');

        if (! $header) {
            return;
        }

        $id = $event->sent->getMessageId();

        if (! $id) {
            return;
        }

        EmailLog::whereKey($header->getBodyAsString())->update(['resend_id' => $id]);
    }
}

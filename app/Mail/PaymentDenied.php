<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentDenied extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;
    public $denialReason;

    public function __construct(array $mailData, $denialReason)
    {
        $this->mailData = $mailData;
        $this->denialReason = $denialReason;
    }

    public function build()
    {
        return $this->subject('Payment Requires Attention - Action Required | Redat Learning Hub')
                    ->view('emails.payment-denied')
                    ->with(array_merge($this->mailData, [
                        'denial_reason' => $this->denialReason
                    ]))
                    ->withSwiftMessage(function ($message) {
                        // Add important headers for deliverability
                        $headers = $message->getHeaders();
                        $headers->addTextHeader('Precedence', 'bulk');
                        $headers->addTextHeader('Auto-Submitted', 'auto-generated');
                        $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
                        $headers->addTextHeader('List-Unsubscribe', '<mailto:support@redatlearninghub.com>');
                        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                        $headers->addTextHeader('X-PM-Message-Stream', 'outbound');
                    });
    }
}
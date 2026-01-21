<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;

    public function __construct(array $mailData)
    {
        $this->mailData = $mailData;
    }

    public function build()
    {
        return $this->subject('Payment Approved - Your Subscription is Now Active | Redat Learning Hub')
                    ->view('emails.payment-approved')
                    ->with($this->mailData)
                    ->withSwiftMessage(function ($message) {
                        // Add important headers for deliverability
                        $headers = $message->getHeaders();
                        $headers->addTextHeader('Precedence', 'bulk');
                        $headers->addTextHeader('Auto-Submitted', 'auto-generated');
                        $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
                        $headers->addTextHeader('List-Unsubscribe', '<mailto:support@redatlearninghub.com>');
                        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                    });
    }
}